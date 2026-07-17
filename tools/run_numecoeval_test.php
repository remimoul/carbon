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

if (PHP_SAPI != 'cli') {
    exit("CLI only\n");
}

// Bootstrap GLPI
include __DIR__ . '/../../../inc/includes.php';

// Manually require the files to see if it works
require_once __DIR__ . '/../src/Impact/Embodied/NumEcoEval/AbstractAsset.php';
require_once __DIR__ . '/../src/Impact/Embodied/NumEcoEval/Peripheral.php';

use GlpiPlugin\Carbon\Impact\Embodied\NumEcoEval\Peripheral;
use Peripheral as GlpiPeripheral;

echo "Running NumEcoEval calculation...\n";
try {
    $count = Peripheral::evaluateAll(GlpiPeripheral::class);
    echo "Success! Evaluated: $count items\n";
} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
}
