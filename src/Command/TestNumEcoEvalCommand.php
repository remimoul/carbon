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
use Peripheral as GlpiPeripheral;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Override;

class TestNumEcoEvalCommand extends Command
{
    #[Override]
    protected function configure()
    {
        $this
            ->setName('plugins:carbon:test_numecoeval')
            ->setDescription('Run test NumEcoEval calculation');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("<info>Starting NumEcoEval test calculation...</info>");

        try {
            $count = Peripheral::evaluateAll(GlpiPeripheral::class);
            $output->writeln("<info>Success! Evaluated $count items.</info>");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
