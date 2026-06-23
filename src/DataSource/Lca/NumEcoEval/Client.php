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

namespace GlpiPlugin\Carbon\DataSource\Lca\NumEcoEval;

use GlpiPlugin\Carbon\DataSource\Lca\AbstractClient;
use GlpiPlugin\Carbon\DataSource\RestApiClientInterface;
use GlpiPlugin\Carbon\DataTracking\TrackedFloat;
use GlpiPlugin\Carbon\Impact\Type;
use Override;
use RuntimeException;

class Client extends AbstractClient
{
    private RestApiClientInterface $client;
    private static string $source_name = 'NumEcoEval';

    /** @var array Mapping between NumEcoEval criteria names and Carbon impact types */
    private static array $criteria_mapping = [
        'Changement climatique'                          => 'gwp',
        'Climate change'                                 => 'gwp',
        'Climate Change'                                 => 'gwp',
        'Épuisement des ressources (minéraux et métaux)' => 'adp',
        'Resource use (minerals and metals)'             => 'adp',
        'Total Primary Energy'                           => 'pe',
        'Énergie primaire totale'                        => 'pe',
        'Acidification'                                  => 'ap',
        'Ionising radiation'                             => 'ir',
        'Rayonnements ionisants'                         => 'ir',
        'Particulate matter and respiratory inorganics'  => 'pm',
        'Particulate matter'                             => 'pm',
        'Particules fines'                               => 'pm',
        'Resource use fossils'                           => 'adpf',
        'Épuisement des ressources fossiles'              => 'adpf',
    ];

    public function __construct(RestApiClientInterface $client)
    {
        $this->client = $client;
    }

    #[Override]
    public function getSourceName(): string
    {
        return self::$source_name;
    }

    /**
     * Upload inventory CSV to NumEcoEval Exposition service
     *
     * @param string $csvContent
     * @param string $lotName
     * @param string $organization
     * @return bool
     */
    public function uploadCsv(string $csvContent, string $lotName, string $organization = 'GLPI'): bool
    {
        $url = rtrim(Config::getConfigurationValue('numecoeval_exposition_url'), '/');
        if (empty($url)) {
            throw new RuntimeException('NumEcoEval Exposition URL is not configured');
        }

        $response = $this->client->request('POST', $url . '/entrees/csv', [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'nomOrganisation' => $organization,
                'nomLot'          => $lotName,
            ],
            'multipart' => [
                [
                    'name'     => 'csvEquipementPhysique',
                    'contents' => $csvContent,
                    'filename' => 'equipement_physique.csv',
                    'headers'  => ['Content-Type' => 'text/csv']
                ],
                ['name' => 'csvDataCenter', 'contents' => '', 'filename' => ''],
                ['name' => 'csvEquipementVirtuel', 'contents' => '', 'filename' => ''],
                ['name' => 'csvApplication', 'contents' => '', 'filename' => ''],
                ['name' => 'csvOperationNonIT', 'contents' => '', 'filename' => ''],
                ['name' => 'csvMessagerie', 'contents' => '', 'filename' => ''],
                ['name' => 'csvEntite', 'contents' => '', 'filename' => '']
            ]
        ]);

        return $response !== false;
    }

    /**
     * Fetch valid steps from Referential service
     *
     * @return array
     */
    public function fetchSteps(): array
    {
        $url = rtrim(Config::getConfigurationValue('numecoeval_referential_url'), '/');
        if (empty($url)) {
            throw new RuntimeException('NumEcoEval Referential URL is not configured');
        }

        $response = $this->client->request('GET', $url . '/referentiel/etapes', []);
        if (empty($response) || !is_array($response)) {
            return [];
        }

        return array_column($response, 'code');
    }

    /**
     * Fetch valid criteria from Referential service
     *
     * @return array
     */
    public function fetchCriteria(): array
    {
        $url = rtrim(Config::getConfigurationValue('numecoeval_referential_url'), '/');
        if (empty($url)) {
            throw new RuntimeException('NumEcoEval Referential URL is not configured');
        }

        $response = $this->client->request('GET', $url . '/referentiel/criteres', []);
        if (empty($response) || !is_array($response)) {
            return [];
        }

        return array_column($response, 'nomCritere');
    }

    /**
     * Trigger calculation in NumEcoEval
     *
     * @param string $lotName
     * @param array $steps
     * @param array $criterias
     * @return bool
     */
    public function submitCalcul(string $lotName, array $steps = [], array $criterias = [], string $organization = 'GLPI'): bool
    {
        $url = rtrim(Config::getConfigurationValue('numecoeval_exposition_url'), '/');
        if (empty($url)) {
            throw new RuntimeException('NumEcoEval Exposition URL is not configured');
        }

        $payload = [
            'nomLot'          => $lotName,
            'nomOrganisation' => $organization,
            'etapes'          => $steps,
            'criteres'        => $criterias
        ];

        $response = $this->client->request('POST', $url . '/entrees/calculs/soumission', [
            'query' => [
                'nomOrganisation' => $organization,
            ],
            'json' => $payload
        ]);

        return $response !== false;
    }

    /**
     * Fetch results from NumEcoEval Indicators service
     *
     * @param string $lotName
     * @param string $organization
     * @return array Impacts indexed by asset name
     */
    public function fetchResults(string $lotName, string $organization = 'GLPI'): array
    {
        $url = rtrim(Config::getConfigurationValue('numecoeval_indicators_url'), '/');
        if (empty($url)) {
            throw new RuntimeException('NumEcoEval Indicators URL is not configured');
        }

        $csvData = $this->client->request('GET', $url . '/indicateur/equipementPhysiqueCsv', [
            'headers' => [
                'Accept' => 'text/csv, text/plain, */*',
            ],
            'query' => [
                'nomLot'          => $lotName,
                'nomOrganisation' => $organization,
                'fields'          => 'nom_equipement,etapeacv,impact_unitaire,unite,critere'
            ],
            'raw_response' => true
        ]);

        if (empty($csvData) || !is_string($csvData)) {
            return [];
        }

        return $this->parseCsvResults($csvData);
    }

    /**
     * Parse the results CSV and map them to Carbon impact types
     *
     * @param string $csvData
     * @return array
     */
    private function parseCsvResults(string $csvData): array
    {
        $lines = explode("\n", str_replace("\r", "", $csvData));
        $headerLine = array_shift($lines);
        if ($headerLine === null) {
            return [];
        }

        $raw_impacts = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            $data = str_getcsv($line, ",");
            if (count($data) < 5) {
                // If it is semicolon separated, try semicolon as fallback
                $data = str_getcsv($line, ";");
                if (count($data) < 5) {
                    continue;
                }
            }

            $assetName = $data[0]; // nom_equipement
            $stage     = strtoupper($data[1]); // etapeacv
            $value     = (float)str_replace(',', '.', $data[2]); // impact_unitaire
            $unit      = $data[3]; // unite
            $criteria  = $data[4]; // critere

            // Exclude UTILISATION stage for embodied impact
            if ($stage === 'UTILISATION') {
                continue;
            }

            $impactType = self::$criteria_mapping[$criteria] ?? null;
            if ($impactType === null) {
                continue;
            }

            $impactId = Type::getImpactId($impactType);
            if ($impactId === false) {
                continue;
            }

            // Conversion to grams if it's kg
            if (stripos($unit, 'kg') !== false) {
                $value *= 1000;
            }
            // Conversion to Joules if it's MJ
            if (stripos($unit, 'mj') !== false) {
                $value *= 1000000;
            }

            if (!isset($raw_impacts[$assetName][$impactId])) {
                $raw_impacts[$assetName][$impactId] = 0.0;
            }
            $raw_impacts[$assetName][$impactId] += $value;
        }

        $impacts_by_asset = [];
        foreach ($raw_impacts as $assetName => $impacts) {
            foreach ($impacts as $impactId => $val) {
                $impacts_by_asset[$assetName][$impactId] = new TrackedFloat(
                    $val,
                    null,
                    TrackedFloat::DATA_QUALITY_ESTIMATED
                );
            }
        }

        return $impacts_by_asset;
    }
}
