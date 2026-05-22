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

use GlpiPlugin\Carbon\Config as PluginConfig;
use GlpiPlugin\Carbon\DataSource\ConfigInterface;
use Override;

class Config implements ConfigInterface
{
    public const ENV_NUMECOEVAL_EXPOSITION_URL = 'GLPI_PLUGIN_CARBON_NUMECOEVAL_EXPOSITION_URL';
    public const ENV_NUMECOEVAL_INDICATORS_URL = 'GLPI_PLUGIN_CARBON_NUMECOEVAL_INDICATORS_URL';
    public const ENV_NUMECOEVAL_REFERENTIAL_URL = 'GLPI_PLUGIN_CARBON_NUMECOEVAL_REFERENTIAL_URL';

    #[Override]
    public static function getSecuredConfigs(): array
    {
        return [];
    }

    #[Override]
    public function getConfigTemplate(): string
    {
        $exposition_env = getenv(self::ENV_NUMECOEVAL_EXPOSITION_URL);
        $indicators_env = getenv(self::ENV_NUMECOEVAL_INDICATORS_URL);
        $referential_env = getenv(self::ENV_NUMECOEVAL_REFERENTIAL_URL);

        $twig = <<<TWIG
        {% import "components/form/fields_macros.html.twig" as fields %}

        {{ fields.largeTitle(
            __('NumEcoEval', 'carbon'),
            'fas fa-leaf'
        ) }}
TWIG;

        if ($exposition_env === false) {
            $twig .= <<<TWIG
            {{ fields.textField(
                'numecoeval_exposition_url',
                current_config['numecoeval_exposition_url'],
                __('NumEcoEval Exposition URL (api-expositiondonneesentrees)', 'carbon')
            ) }}
TWIG;
        }

        if ($indicators_env === false) {
            $twig .= <<<TWIG
            {{ fields.textField(
                'numecoeval_indicators_url',
                current_config['numecoeval_indicators_url'],
                __('NumEcoEval Indicators URL (api-event-calculs)', 'carbon')
            ) }}
TWIG;
        }

        if ($referential_env === false) {
            $twig .= <<<TWIG
            {{ fields.textField(
                'numecoeval_referential_url',
                current_config['numecoeval_referential_url'],
                __('NumEcoEval Referential URL (api-referentiel)', 'carbon')
            ) }}
TWIG;
        }

        return $twig;
    }

    #[Override]
    public function configUpdate(array $input): array
    {
        return $input;
    }

    /**
     * Get a configuration value, prioritizing environment variables
     *
     * @param string $name
     * @return string|null
     */
    public static function getConfigurationValue(string $name): ?string
    {
        switch ($name) {
            case 'numecoeval_exposition_url':
                $value = getenv(self::ENV_NUMECOEVAL_EXPOSITION_URL);
                if ($value !== false) {
                    return $value;
                }
                break;
            case 'numecoeval_indicators_url':
                $value = getenv(self::ENV_NUMECOEVAL_INDICATORS_URL);
                if ($value !== false) {
                    return $value;
                }
                break;
            case 'numecoeval_referential_url':
                $value = getenv(self::ENV_NUMECOEVAL_REFERENTIAL_URL);
                if ($value !== false) {
                    return $value;
                }
                break;
        }

        return PluginConfig::getPluginConfigurationValue($name);
    }
}
