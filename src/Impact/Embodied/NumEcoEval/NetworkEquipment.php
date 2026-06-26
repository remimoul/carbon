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
use NetworkEquipment as GlpiNetworkEquipment;
use NetworkEquipmentModel;
use NetworkEquipmentType;
use GlpiPlugin\Carbon\Location;

class NetworkEquipment extends AbstractAsset
{
    protected static string $itemtype = GlpiNetworkEquipment::class;

    #[Override]
    protected function getCsvData(): array
    {
        $model = new NetworkEquipmentModel();
        $model_name = '';
        if ($model->getFromDB($this->item->fields['networkequipmentmodels_id'])) {
            $model_name = $model->fields['name'];
        }

        $type = new NetworkEquipmentType();
        $type_name = 'Commutateur'; // Default fallback
        if ($type->getFromDB($this->item->fields['networkequipmenttypes_id'])) {
            $raw_type = $type->fields['name'];
            if (stripos($raw_type, 'switch') !== false || stripos($raw_type, 'commutateur') !== false) {
                $type_name = 'Commutateur';
            } elseif (stripos($raw_type, 'routeur') !== false || stripos($raw_type, 'router') !== false) {
                $type_name = 'Routeur';
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
