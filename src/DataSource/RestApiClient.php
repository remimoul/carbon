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

namespace GlpiPlugin\Carbon\DataSource;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Message;
use Override;
use Toolbox;

class RestApiClient implements RestApiClientInterface
{
    public const DEFAULT_TIMEOUT = 30;
    public const DEFAULT_HEADERS = [
        'Accept' => 'application/json; charset=utf-8',
    ];
    public const DEFAULT_HTTP_VERSION = '1.1';

    protected $api_client = null;
    protected $last_error = '';

    public function __construct(array $params = [])
    {
        $local_params = [
            'timeout'         => self::DEFAULT_TIMEOUT,
            'connect_timeout' => self::DEFAULT_TIMEOUT,
            'headers'         => self::DEFAULT_HEADERS,
            'version'         => self::DEFAULT_HTTP_VERSION,
            'http_errors'     => false,
            'debug'           => false, // ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE),
            // This is insecure and not recommanded, but...
            // 'verify'          => false,
        ];

        // Merge params: use array_replace for top-level keys to avoid
        // array_merge_recursive nesting headers into arrays
        $merged = array_replace($local_params, $params);
        if (isset($params['headers'])) {
            $merged['headers'] = array_replace($local_params['headers'], $params['headers']);
        }
        $this->api_client = new Client($merged);
    }

    #[Override]
    public function request(string $method = 'GET', string $uri = '', array $options = [])
    {
        try {
            $request = $this->api_client;
            $raw = $options['raw_response'] ?? false;
            unset($options['raw_response']);
            $response = $request->request($method, $uri, $options);
        } catch (RequestException $e) {
            $this->last_error = [
                'title'     => "Plugins API error",
                'exception' => $e->getMessage(),
                'request'   => Message::toString($e->getRequest()),
            ];
            if ($e->hasResponse()) {
                $this->last_error['response'] = Message::toString($e->getResponse());
            }

            Toolbox::logDebug($this->last_error);

            return false;
        }

        // Check for HTTP error status codes (http_errors is disabled)
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->last_error = [
                'title'    => "API HTTP error",
                'status'   => $statusCode,
                'response' => (string) $response->getBody(),
            ];
            Toolbox::logDebug($this->last_error);

            return false;
        }

        if ($raw) {
            return (string)$response->getBody();
        }

        return json_decode($response->getBody(), true);
    }
}
