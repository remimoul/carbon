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

/** @var DBmysql $DB */
global $DB;

// Create the missing glpi_plugin_carbon_peripheraltypes table.
// This table is required by AbstractImpact::getItemsToEvaluate() which dynamically
// builds a LEFT JOIN on 'glpi_plugin_carbon_peripheraltypes' when processing the
// 'Peripheral' itemtype. It stores Carbon-specific metadata for GLPI peripheral types
// (power_consumption and is_ignore flag), following the same pattern as
// glpi_plugin_carbon_monitortypes and glpi_plugin_carbon_networkequipmenttypes.
$migration->addInfoMessage('Create table glpi_plugin_carbon_peripheraltypes');

if (!$DB->tableExists('glpi_plugin_carbon_peripheraltypes')) {
    $DB->doQuery(
        "CREATE TABLE `glpi_plugin_carbon_peripheraltypes` (
          `id`                  int unsigned NOT NULL AUTO_INCREMENT,
          `peripheraltypes_id`  int unsigned NOT NULL DEFAULT '0',
          `power_consumption`   int          DEFAULT '0',
          `is_ignore`           tinyint      NOT NULL DEFAULT '0' COMMENT 'Ignored from calculations',
          PRIMARY KEY (`id`),
          UNIQUE KEY `unicity` (`peripheraltypes_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
