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
use Monitor as GlpiMonitor;
use MonitorModel;
use MonitorType;
use GlpiPlugin\Carbon\Location;

class Monitor extends AbstractAsset
{
    protected static string $itemtype = GlpiMonitor::class;

    #[Override]
    protected function getCsvData(): array
    {
        $model = new MonitorModel();
        $model_name = '';
        if ($model->getFromDB($this->item->fields['monitormodels_id'])) {
            $model_name = $model->fields['name'];
        }

        $type = new MonitorType();
        $type_name = 'Ecran'; // Monitors are always mapped to Ecran in NumEcoEval
        if ($type->getFromDB($this->item->fields['monitortypes_id'])) {
            $type_name = 'Ecran';
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
