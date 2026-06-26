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

namespace GlpiPlugin\Carbon;

use CommonDBTM;
use Html;
use MassiveAction;
use Override;
use PeripheralType as GlpiPeripheralType;

class PeripheralType extends AbstractType
{
    public static $itemtype = GlpiPeripheralType::class;
    public static $items_id = 'peripheraltypes_id';

    #[Override]
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case 'MassUpdatePower':
                echo '<div>';
                echo __('Power consumption', 'carbon') . '&nbsp;';
                echo Html::input('power_consumption', ['type' => 'number']);
                echo '</div>';
                echo '<br /><br />' . Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
                return true;
        }

        return parent::showMassiveActionsSubForm($ma);
    }

    #[Override]
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        switch ($ma->getAction()) {
            case 'MassUpdatePower':
                foreach ($ids as $id) {
                    if ($item->getFromDB($id) && self::updatePowerConsumption($item, $ma->POST['power_consumption'])) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                    }
                }
                return;
        }
    }

    /**
     * Update the power consumption associated to a peripheral type
     *
     * @param CommonDBTM $item Peripheral to update
     * @param int $power power consumption to set
     * @return bool
     */
    public static function updatePowerConsumption(CommonDBTM $item, int $power)
    {
        $peripheral_type = new PeripheralType();
        $core_peripheral_type_id = $item->getID();
        $peripheral_type->getFromDBByCrit([
            'peripheraltypes_id' => $core_peripheral_type_id,
        ]);
        if ($peripheral_type->isNewItem()) {
            $id = $peripheral_type->add([
                'peripheraltypes_id'  => $core_peripheral_type_id,
                'power_consumption' => $power,
            ]);
            return !$peripheral_type->isNewId($id);
        } else {
            return $peripheral_type->update([
                'id'                => $peripheral_type->getID(),
                'power_consumption' => $power,
            ]);
        }
    }
}
