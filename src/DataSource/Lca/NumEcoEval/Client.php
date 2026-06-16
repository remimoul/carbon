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
        'Changement climatique' => 'gwp',
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
     * @return bool
     */
    public function uploadCsv(string $csvContent): bool
    {
        $url = Config::getConfigurationValue('numecoeval_exposition_url');
        if (empty($url)) {
            throw new RuntimeException('NumEcoEval Exposition URL is not configured');
        }

        $response = $this->client->request('POST', $url . '/entrees/csv', [
            'multipart' => [
                [
                    'name'     => 'file',
                    'contents' => $csvContent,
                    'filename' => 'equipement_physique.csv'
                ]
            ]
        ]);

        return $response !== false;
    }

    /**
     * Trigger calculation in NumEcoEval
     *
     * @param string $lotName
     * @param array $steps
     * @param array $criterias
     * @return bool
     */
    public function submitCalcul(string $lotName, array $steps = [], array $criterias = []): bool
    {
        $url = Config::getConfigurationValue('numecoeval_exposition_url');
        if (empty($url)) {
            throw new RuntimeException('NumEcoEval Exposition URL is not configured');
        }

        $payload = [
            'nomLot'   => $lotName,
            'etapes'   => $steps,
            'criteres' => $criterias
        ];

        $response = $this->client->request('POST', $url . '/entrees/calculs/soumission', [
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
        $url = Config::getConfigurationValue('numecoeval_indicators_url');
        if (empty($url)) {
            throw new RuntimeException('NumEcoEval Indicators URL is not configured');
        }

        $csvData = $this->client->request('GET', $url . '/indicateur/equipementPhysiqueCsv', [
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

        $impacts_by_asset = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            $data = str_getcsv($line, ";");
            if (count($data) < 5) {
                continue;
            }

            $assetName = $data[0]; // nom_equipement
            $value     = (float)str_replace(',', '.', $data[2]); // impact_unitaire
            $unit      = $data[3]; // unite
            $criteria  = $data[4]; // critere

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

            $impacts_by_asset[$assetName][$impactId] = new TrackedFloat(
                $value,
                null,
                TrackedFloat::DATA_QUALITY_ESTIMATED
            );
        }

        return $impacts_by_asset;
    }
}
