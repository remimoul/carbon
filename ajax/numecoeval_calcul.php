<?php

/**
 * -------------------------------------------------------------------------
 * Carbon plugin for GLPI
 *
 * @copyright Copyright (C) 2024-2025 Teclib' and contributors.
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPLv3+
 * @link      https://github.com/pluginsGLPI/carbon
 *
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Carbon plugin for GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * -------------------------------------------------------------------------
 */

include __DIR__ . '/../../../inc/includes.php';

use GlpiPlugin\Carbon\DataSource\Lca\NumEcoEval\Client;
use GlpiPlugin\Carbon\DataSource\Lca\NumEcoEval\Config as NumEcoEvalConfig;
use GlpiPlugin\Carbon\DataSource\RestApiClient;
use GlpiPlugin\Carbon\Impact\Embodied\NumEcoEval\Peripheral as NumEcoEvalPeripheral;
use GlpiPlugin\Carbon\Impact\Embodied\NumEcoEval\Computer as NumEcoEvalComputer;
use GlpiPlugin\Carbon\Impact\Embodied\NumEcoEval\Monitor as NumEcoEvalMonitor;
use GlpiPlugin\Carbon\Impact\Embodied\NumEcoEval\NetworkEquipment as NumEcoEvalNetworkEquipment;
use Computer as GlpiComputer;
use Monitor as GlpiMonitor;
use NetworkEquipment as GlpiNetworkEquipment;
use Peripheral as GlpiPeripheral;

header('Content-Type: application/json');

Session::checkRight('carbon:report', READ);

$engine_name = \GlpiPlugin\Carbon\Config::getEmbodiedImpactEngine();
if (strpos($engine_name, 'NumEcoEval') === false) {
    echo json_encode(['success' => false, 'message' => __('NumEcoEval is not the active engine', 'carbon')]);
    exit;
}

$action = $_REQUEST['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'upload_inventory':
        handleUploadInventory();
        break;

    case 'submit_calcul':
        handleSubmitCalcul();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
        break;
}

/**
 * Step 1: Build the CSV from GLPI assets and POST it to NumEcoEval Exposition API
 */
function handleUploadInventory(): void
{
    // Map GLPI itemtypes to their NumEcoEval engine classes
    $engineMap = [
        GlpiComputer::class          => NumEcoEvalComputer::class,
        GlpiMonitor::class           => NumEcoEvalMonitor::class,
        GlpiNetworkEquipment::class  => NumEcoEvalNetworkEquipment::class,
        GlpiPeripheral::class        => NumEcoEvalPeripheral::class,
    ];

    try {
        /** @var \DBmysql $DB */
        global $DB;

        $items = [];
        $engine_instance = null;
        $types_searched = [];

        foreach (PLUGIN_CARBON_TYPES as $glpiItemtype) {
            if (!isset($engineMap[$glpiItemtype])) {
                continue;
            }

            $engineClass = $engineMap[$glpiItemtype];
            $types_searched[] = $glpiItemtype;

            // Fetch ALL non-deleted, non-template assets of this type
            $table = $glpiItemtype::getTable();
            $all_iterator = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => $table,
                'WHERE'  => [
                    'is_deleted'  => 0,
                    'is_template' => 0,
                ],
            ]);

            foreach ($all_iterator as $row) {
                $item = new $glpiItemtype();
                if ($item->getFromDB($row['id'])) {
                    $items[] = $item;
                    if ($engine_instance === null) {
                        $engine_instance = new $engineClass($item);
                    }
                }
            }
        }

        if (empty($items) || $engine_instance === null) {
            echo json_encode([
                'success' => false,
                'message' => sprintf(
                    __('No assets found in GLPI inventory. Searched types: %s', 'carbon'),
                    implode(', ', $types_searched)
                )
            ]);
            return;
        }

        // Generate CSV
        $csvContent = $engine_instance->generateCsvPublic($items);

        // Generate lot name and store it in session for step 2
        $lotName = 'GLPI_BULK_' . (new \DateTime())->format('Ymd_His');
        $organization = 'GLPI';
        $_SESSION['numecoeval_lot_name'] = $lotName;
        $_SESSION['numecoeval_organization'] = $organization;

        // Create client and upload
        $client = new Client(new RestApiClient());
        $uploaded = $client->uploadCsv($csvContent, $lotName, $organization);

        if (!$uploaded) {
            echo json_encode([
                'success' => false,
                'message' => __('Failed to upload CSV to NumEcoEval Exposition API.', 'carbon')
                    . ' URL: ' . (NumEcoEvalConfig::getConfigurationValue('numecoeval_exposition_url') ?? 'NOT CONFIGURED')
            ]);
            return;
        }

        echo json_encode([
            'success'      => true,
            'message'      => sprintf(
                __('Inventory uploaded successfully. %d assets sent. Lot: %s', 'carbon'),
                count($items),
                $lotName
            ),
            'lot_name'     => $lotName,
            'item_count'   => count($items),
        ]);
    } catch (\Throwable $e) {
        \Toolbox::logDebug([
            'title'     => 'NumEcoEval upload error',
            'exception' => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
        ]);
        echo json_encode([
            'success' => false,
            'message' => __('Upload error: ', 'carbon') . $e->getMessage()
        ]);
    }
}

/**
 * Step 2: Submit the calculation request to NumEcoEval
 */
function handleSubmitCalcul(): void
{
    try {
        /** @var \DBmysql $DB */
        global $DB;

        $lotName = $_SESSION['numecoeval_lot_name'] ?? null;
        $organization = $_SESSION['numecoeval_organization'] ?? 'GLPI';

        if (empty($lotName)) {
            echo json_encode([
                'success' => false,
                'message' => __('No lot name found. Please upload the inventory first (Step 1).', 'carbon')
            ]);
            return;
        }

        $client = new Client(new RestApiClient());

        // Fetch steps and criteria from referential
        $steps = [];
        $criteria = [];
        try {
            $steps = $client->fetchSteps();
            $criteria = $client->fetchCriteria();
        } catch (\Throwable $e) {
            // Referential might not be available, use defaults
            \Toolbox::logDebug([
                'title'   => 'NumEcoEval referential warning',
                'message' => $e->getMessage(),
            ]);
        }

        if (empty($steps)) {
            $steps = ['FABRICATION', 'DISTRIBUTION', 'UTILISATION', 'FIN_DE_VIE'];
        }
        if (empty($criteria)) {
            $criteria = ['Changement climatique'];
        }

        // Submit
        $submitted = $client->submitCalcul($lotName, $steps, $criteria, $organization);

        if (!$submitted) {
            echo json_encode([
                'success' => false,
                'message' => __('Failed to submit calculation to NumEcoEval.', 'carbon')
                    . ' URL: ' . (NumEcoEvalConfig::getConfigurationValue('numecoeval_exposition_url') ?? 'NOT CONFIGURED')
                    . ' Lot: ' . $lotName
            ]);
            return;
        }

        // Poll for results — NumEcoEval calculation is asynchronous
        $all_results = [];
        $maxAttempts = 12;
        $delaySeconds = 3;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            sleep($delaySeconds);
            $all_results = $client->fetchResults($lotName, $organization);
            if (!empty($all_results)) {
                break;
            }
        }

        if (empty($all_results)) {
            echo json_encode([
                'success' => false,
                'message' => __('Calculation submitted, but failed to retrieve results from Indicators API (Timeout).', 'carbon')
            ]);
            return;
        }

        // Map GLPI itemtypes to their NumEcoEval engine classes
        $engineMap = [
            GlpiComputer::class          => NumEcoEvalComputer::class,
            GlpiMonitor::class           => NumEcoEvalMonitor::class,
            GlpiNetworkEquipment::class  => NumEcoEvalNetworkEquipment::class,
            GlpiPeripheral::class        => NumEcoEvalPeripheral::class,
        ];

        // Update the DB for each item
        $success_count = 0;
        foreach (PLUGIN_CARBON_TYPES as $glpiItemtype) {
            if (!isset($engineMap[$glpiItemtype])) {
                continue;
            }
            $engineClass = $engineMap[$glpiItemtype];
            $table = $glpiItemtype::getTable();

            $all_iterator = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => $table,
                'WHERE'  => [
                    'is_deleted'  => 0,
                    'is_template' => 0,
                ],
            ]);

            foreach ($all_iterator as $row) {
                $item = new $glpiItemtype();
                if ($item->getFromDB($row['id'])) {
                    $assetName = $item->fields['name'];
                    $results = $all_results[$assetName] ?? null;
                    if ($results !== null) {
                        $item_engine = new $engineClass($item);
                        if ($item_engine->updateAssetImpacts($results)) {
                            $success_count++;
                        }
                    }
                }
            }
        }

        // Store last run info in configuration
        \GlpiPlugin\Carbon\Config::setPluginConfigurationValues([
            'numecoeval_last_lot_name'         => $lotName,
            'numecoeval_last_calculation_date' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        echo json_encode([
            'success'  => true,
            'message'  => sprintf(
                __('Calculation submitted and results retrieved successfully! Updated %d assets in GLPI.', 'carbon'),
                $success_count
            ),
            'lot_name' => $lotName,
            'steps'    => $steps,
            'criteria' => $criteria,
        ]);
    } catch (\Throwable $e) {
        \Toolbox::logDebug([
            'title'     => 'NumEcoEval calcul submission error',
            'exception' => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
        ]);
        echo json_encode([
            'success' => false,
            'message' => __('Calculation submission error: ', 'carbon') . $e->getMessage()
        ]);
    }
}
