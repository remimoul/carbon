<?php

/**
 * -------------------------------------------------------------------------
 * Carbon plugin for GLPI
 *
 * @copyright Copyright (C) 2024-2025 Teclib' and contributors.
 * @copyright 2015-2023 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
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

namespace GlpiPlugin\Carbon\Command;

use GlpiPlugin\Carbon\Impact\Embodied\NumEcoEval\Peripheral;
use GlpiPlugin\Carbon\DataSource\Lca\NumEcoEval\Config;
use Peripheral as GlpiPeripheral;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Override;

class TestNumEcoEvalCommand extends Command
{
    /** @var array Test peripheral definitions for NumEcoEval evaluation */
    private const TEST_PERIPHERALS = [
        [
            'name'          => 'TEST_Ecran_Dell_P2422H',
            'entities_id'   => 0,
            'is_deleted'    => 0,
        ],
        [
            'name'          => 'TEST_Ecran_HP_E24',
            'entities_id'   => 0,
            'is_deleted'    => 0,
        ],
        [
            'name'          => 'TEST_Clavier_Logitech_K120',
            'entities_id'   => 0,
            'is_deleted'    => 0,
        ],
    ];

    #[Override]
    protected function configure()
    {
        $this
            ->setName('plugins:carbon:test_numecoeval')
            ->setDescription('Run test NumEcoEval calculation (creates temporary test peripherals)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("<info>Starting NumEcoEval test calculation...</info>");
        $output->writeln('');

        // 1. Display configuration
        $expositionUrl = Config::getConfigurationValue('numecoeval_exposition_url');
        $indicatorsUrl = Config::getConfigurationValue('numecoeval_indicators_url');
        $referentialUrl = Config::getConfigurationValue('numecoeval_referential_url');
        $output->writeln("<comment>Configuration:</comment>");
        $output->writeln("  Exposition URL:  " . ($expositionUrl ?: '<error>NOT SET</error>'));
        $output->writeln("  Indicators URL:  " . ($indicatorsUrl ?: '<error>NOT SET</error>'));
        $output->writeln("  Referential URL: " . ($referentialUrl ?: '<error>NOT SET</error>'));
        $output->writeln('');

        if (empty($expositionUrl) || empty($indicatorsUrl) || empty($referentialUrl)) {
            $output->writeln("<error>One or more NumEcoEval URLs are not configured. Aborting.</error>");
            return Command::FAILURE;
        }

        // 2. Count existing peripherals
        /** @var \DBmysql $DB */
        global $DB;
        $count_result = $DB->request([
            'COUNT' => 'cnt',
            'FROM'  => 'glpi_peripherals',
        ]);
        $row = $count_result->current();
        $existing_count = $row['cnt'] ?? 0;
        $output->writeln("  Existing peripherals in DB: <info>$existing_count</info>");

        // 3. Create test peripherals
        $output->writeln('');
        $output->writeln("<comment>Creating test peripherals...</comment>");
        $created_ids = [];

        foreach (self::TEST_PERIPHERALS as $data) {
            $peripheral = new GlpiPeripheral();
            $id = $peripheral->add($data);
            if ($id !== false) {
                $created_ids[] = $id;
                $output->writeln("  ✓ Created: <info>{$data['name']}</info> (ID: $id)");
            } else {
                $output->writeln("  ✗ <error>Failed to create: {$data['name']}</error>");
            }
        }

        if (empty($created_ids)) {
            $output->writeln("<error>Could not create any test peripherals. Aborting.</error>");
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln("<comment>Running NumEcoEval calculation for " . count($created_ids) . " test peripheral(s)...</comment>");

        // 4. Run the evaluation
        try {
            $count = Peripheral::evaluateAll(GlpiPeripheral::class);
            $output->writeln('');
            if ($count > 0) {
                $output->writeln("<info>✓ Success! Evaluated $count item(s) via NumEcoEval.</info>");
            } else {
                $output->writeln("<comment>⚠ Calculation completed but 0 items were evaluated.</comment>");
                $output->writeln("  This may mean the NumEcoEval API returned no results.");
                $output->writeln("  Check that the NumEcoEval services are running and accessible.");
            }
        } catch (\Throwable $e) {
            $output->writeln('');
            $output->writeln("<error>✗ Error during evaluation: " . $e->getMessage() . "</error>");
            $output->writeln($e->getTraceAsString());
        }

        // 5. Cleanup: delete test peripherals
        $output->writeln('');
        $output->writeln("<comment>Cleaning up test data...</comment>");
        foreach ($created_ids as $id) {
            $peripheral = new GlpiPeripheral();
            if ($peripheral->delete(['id' => $id], true)) {
                $output->writeln("  ✓ Deleted test peripheral ID: $id");
            } else {
                $output->writeln("  ✗ <comment>Could not delete test peripheral ID: $id (manual cleanup needed)</comment>");
            }
        }

        // Also clean up any embodied impact records created for test items
        $embodied = new \GlpiPlugin\Carbon\EmbodiedImpact();
        foreach ($created_ids as $id) {
            $embodied->deleteByCriteria([
                'itemtype' => GlpiPeripheral::class,
                'items_id' => $id,
            ]);
        }

        $output->writeln('');
        $output->writeln("<info>Test complete.</info>");

        return Command::SUCCESS;
    }
}
