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

use Override;
use Peripheral as GlpiPeripheral;
use PeripheralModel;
use PeripheralType;
use GlpiPlugin\Carbon\Location;

class Peripheral extends AbstractAsset
{
    protected static string $itemtype = GlpiPeripheral::class;

    #[Override]
    protected function getCsvData(): array
    {
        $model = new PeripheralModel();
        $model_name = '';
        if ($model->getFromDB($this->item->fields['peripheralmodels_id'])) {
            $model_name = $model->fields['name'];
        }

        $type = new PeripheralType();
        $type_name = 'Ecran'; // Default for peripheral in this context
        if ($type->getFromDB($this->item->fields['peripheraltypes_id'])) {
            $raw_type = $type->fields['name'];
            if (stripos($raw_type, 'ecran') !== false || stripos($raw_type, 'monitor') !== false || stripos($raw_type, 'screen') !== false) {
                $type_name = 'Ecran';
            }
        }

        $country_code = Location::getZoneCode($this->item);
        $country = 'France';
        if (!empty($country_code)) {
            if ($country_code === 'FRA') {
                $country = 'France';
            } else {
                $country = $country_code;
            }
        }

        return [
            'nomEquipementPhysique' => $this->item->fields['name'],
            'modele'                => $model_name,
            'quantite'              => 1,
            'nomCourtDatacenter'    => '',
            'dateAchat'             => $this->item->fields['date_purchase'] ?? '',
            'dateRetrait'           => '',
            'type'                  => $type_name,
            'statut'                => 'actif',
            'paysDUtilisation'      => $country,
            'consoElecAnnuelle'     => '',
            'utilisateur'           => '',
            'nomSourceDonnee'       => '',
            'nomEntite'             => '',
            'nbCoeur'               => '',
            'nbJourUtiliseAn'       => '365',
            'goTelecharge'          => '',
            'modeUtilisation'       => '',
            'tauxUtilisation'       => ''
        ];
    }
}
