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

namespace GlpiPlugin\Carbon\Impact\Embodied\NumEcoEval;

use GlpiPlugin\Carbon\DataSource\Lca\NumEcoEval\Client;
use GlpiPlugin\Carbon\EmbodiedImpact;
use GlpiPlugin\Carbon\Impact\Embodied\AbstractEmbodiedImpact;
use Override;
use RuntimeException;

abstract class AbstractAsset extends AbstractEmbodiedImpact implements AssetInterface
{
    /** @var string $engine Name of the calculation engine */
    protected string $engine = 'NumEcoEval';

    /** @var Client instance of the HTTP client */
    protected ?Client $client = null;

    /**
     * Set the REST API client to use for requests
     *
     * @param Client $client
     * @return void
     */
    #[Override]
    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Bulk evaluation of all assets of a certain type
     *
     * @param string $itemtype
     * @return int Number of successfully evaluated items
     */
    public static function evaluateAll(string $itemtype): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        // Bypass entity restriction in CLI/batch context (no active session)
        $crit = [
            [
                'OR' => [
                    EmbodiedImpact::getTableField('id') => null,
                    EmbodiedImpact::getTableField('recalculate') => 1,
                ],
            ],
        ];
        $query = self::getEvaluableQuery($itemtype, $crit, false);
        $iterator = $DB->request($query);
        $items = [];
        $engine_instance = null;

        foreach ($iterator as $row) {
            $item = new $itemtype();
            if ($item->getFromDB($row['id'])) {
                $items[] = $item;
                if ($engine_instance === null) {
                    $engine_instance = new static($item);
                }
            }
        }

        if (empty($items) || $engine_instance === null) {
            return 0;
        }

        $client = new Client(new \GlpiPlugin\Carbon\DataSource\RestApiClient());
        $engine_instance->setClient($client);

        $csvContent = $engine_instance->generateCsv($items);
        $lotName = 'GLPI_BULK_' . (new \DateTime())->format('Ymd_His');
        $organization = 'GLPI';

        // 1. Upload CSV
        if (!$client->uploadCsv($csvContent, $lotName, $organization)) {
            return 0;
        }

        // 2. Submit calculation
        $steps = $client->fetchSteps();
        $criteria = $client->fetchCriteria();
        if (empty($steps) || empty($criteria)) {
             $steps = ['FABRICATION', 'DISTRIBUTION', 'UTILISATION', 'FIN_DE_VIE'];
             $criteria = ['Changement climatique'];
        }

        if (!$client->submitCalcul($lotName, $steps, $criteria, $organization)) {
            return 0;
        }

        // 3. Poll for results — NumEcoEval calculation is asynchronous
        $all_results = [];
        $maxAttempts = 10;
        $delaySeconds = 3;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            sleep($delaySeconds);
            $all_results = $client->fetchResults($lotName, $organization);
            if (!empty($all_results)) {
                break;
            }
        }
        $success_count = 0;

        foreach ($items as $item) {
            $assetName = $item->fields['name'];
            $results = $all_results[$assetName] ?? null;
            if ($results !== null) {
                $item_engine = new static($item);
                if ($item_engine->updateImpacts($results)) {
                    $success_count++;
                }
            }
        }

        return $success_count;
    }

    #[Override]
    protected function getVersion(): string
    {
        // NumEcoEval versioning might be handled differently, for now we use a placeholder
        // or we could query the referential service if it has a version endpoint.
        self::$engine_version = '2.2.1';
        return self::$engine_version;
    }

    /**
     * Map the asset to NumEcoEval CSV columns
     *
     * @return array
     */
    abstract protected function getCsvData(): array;

    /**
     * Generate CSV content for the asset(s)
     *
     * @param array $items List of items to include in CSV
     * @return string
     */
    protected function generateCsv(array $items = []): string
    {
        if (empty($items)) {
            $items = [$this->item];
        }

        $output = fopen('php://temp', 'r+');
        $header_written = false;

        foreach ($items as $item) {
            $this->item = $item;
            $data = $this->getCsvData();
            if (!$header_written) {
                fputcsv($output, array_keys($data), ';');
                $header_written = true;
            }
            fputcsv($output, array_values($data), ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Public wrapper for generateCsv, usable from AJAX controllers
     *
     * @param array $items List of CommonDBTM items to include
     * @return string CSV content
     */
    public function generateCsvPublic(array $items = []): string
    {
        return $this->generateCsv($items);
    }

    /**
     * Public wrapper to update impact data in the database for the asset
     *
     * @param array $impacts
     * @return bool
     */
    public function updateAssetImpacts(array $impacts): bool
    {
        return $this->updateImpacts($impacts);
    }

    /**
     * Update the impact data in the database for the current item
     *
     * @param array $impacts
     * @return bool
     */
    protected function updateImpacts(array $impacts): bool
    {
        $itemtype = get_class($this->item);
        $input = [
            'itemtype' => $itemtype,
            'items_id' => $this->item->getID(),
        ];
        $embodied_impact = new \GlpiPlugin\Carbon\EmbodiedImpact();
        $embodied_impact->getFromDBByCrit($input);
        $impact_types = \GlpiPlugin\Carbon\Impact\Type::getImpactTypes();

        $input['recalculate'] = 0;
        $input['engine'] = $this->engine;
        $input['engine_version'] = self::$engine_version;

        foreach ($impacts as $type => $value) {
            $key = $impact_types[$type];
            $key_quality = "{$key}_quality";
            $input[$key] = null;
            $input[$key_quality] = \GlpiPlugin\Carbon\DataTracking\AbstractTracked::DATA_QUALITY_UNSPECIFIED;
            if ($value !== null) {
                $input[$key] = $value->getValue();
                $input[$key_quality] = $value->getLowestSource();
            }
        }

        if ($embodied_impact->isNewItem()) {
            return $embodied_impact->add($input) !== false;
        } else {
            unset($input['itemtype'], $input['items_id']);
            return $embodied_impact->update(['id' => $embodied_impact->getID()] + $input);
        }
    }

    #[Override]
    protected function doEvaluation(): ?array
    {
        if ($this->client === null) {
            throw new RuntimeException('NumEcoEval client is not set');
        }

        $csvContent = $this->generateCsv();
        $lotName = 'GLPI_' . $this->item->getType() . '_' . $this->item->getID() . '_' . time();
        $organization = 'GLPI';

        // 1. Upload CSV
        if (!$this->client->uploadCsv($csvContent, $lotName, $organization)) {
            return null;
        }

        // 2. Submit calculation
        $steps = $this->client->fetchSteps();
        $criteria = $this->client->fetchCriteria();

        if (empty($steps) || empty($criteria)) {
             // Fallback if referential is not available or empty
             $steps = ['FABRICATION', 'DISTRIBUTION', 'UTILISATION', 'FIN_DE_VIE'];
             $criteria = ['Changement climatique'];
        }

        if (!$this->client->submitCalcul($lotName, $steps, $criteria, $organization)) {
            return null;
        }

        // 3. Poll for results — NumEcoEval calculation is asynchronous
        $results = [];
        $maxAttempts = 10;
        $delaySeconds = 3;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            sleep($delaySeconds);
            $results = $this->client->fetchResults($lotName, $organization);
            if (!empty($results)) {
                break;
            }
        }

        // NumEcoEval returns impacts by asset name. Our asset name in CSV is usually $this->item->fields['name']
        $assetName = $this->item->fields['name'];

        return $results[$assetName] ?? null;
    }
}
