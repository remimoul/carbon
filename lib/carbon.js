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

window.GLPIPlugin = window.GLPIPlugin || {};

window.GLPIPlugin.Carbon = {
    onSourceChange: function() {
        var value = document.querySelector("select[name='plugin_carbon_sources_id']").value;
        var zoneDropdown = document.querySelector("select[name='plugin_carbon_zones_id']").closest('div.form-field');
        if (value == 0 && !zoneDropdown.classList.contains('d-none')) {
            zoneDropdown.classList.add('d-none');
        } else if (value != 0 && zoneDropdown.classList.contains('d-none')) {
            zoneDropdown.classList.remove('d-none');
        }
    },

    /**
     * Helper: POST to the AJAX endpoint with a given action
     */
    _postAction: function(url, csrfToken, action, btn, originalHtml, loadingText) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + loadingText;

        var statusEl = document.getElementById('numecoeval-status');
        if (statusEl) {
            statusEl.innerHTML = '<i class="fas fa-hourglass-half text-warning"></i> ' + loadingText;
            statusEl.className = 'alert alert-info mt-2';
            statusEl.style.display = 'block';
        }

        fetch(url + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Glpi-Csrf-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: '_glpi_csrf_token=' + encodeURIComponent(csrfToken) + '&action=' + encodeURIComponent(action)
        })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (statusEl) {
                    if (data.success) {
                        statusEl.innerHTML = '<i class="fas fa-check-circle text-success"></i> ' + data.message;
                        statusEl.className = 'alert alert-success mt-2';
                    } else {
                        statusEl.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i> ' + data.message;
                        statusEl.className = 'alert alert-danger mt-2';
                    }
                    statusEl.style.display = 'block';
                }
                if (data.success && action === 'upload_inventory') {
                    // Enable step 2 button after successful upload
                    var step2Btn = document.getElementById('numecoeval-calcul-btn');
                    if (step2Btn) {
                        step2Btn.disabled = false;
                        step2Btn.classList.remove('btn-secondary');
                        step2Btn.classList.add('btn-warning');
                    }
                }
            })
            .catch(function(error) {
                console.error('NumEcoEval Error:', error);
                if (statusEl) {
                    statusEl.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Network error: ' + error.message;
                    statusEl.className = 'alert alert-danger mt-2';
                    statusEl.style.display = 'block';
                }
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
    },

    /**
     * Step 1: Upload the GLPI inventory CSV to NumEcoEval
     */
    uploadInventory: function(url, csrfToken) {
        var btn = document.getElementById('numecoeval-upload-btn');
        this._postAction(
            url, csrfToken, 'upload_inventory', btn,
            '<i class="fas fa-upload"></i> Upload inventory',
            'Uploading inventory to NumEcoEval...'
        );
    },

    /**
     * Step 2: Submit calculation to NumEcoEval
     */
    submitCalcul: function(url, csrfToken) {
        var btn = document.getElementById('numecoeval-calcul-btn');
        this._postAction(
            url, csrfToken, 'submit_calcul', btn,
            '<i class="fas fa-calculator"></i> Launch calculation',
            'Submitting calculation...'
        );
    },

    // Keep old function for backward compatibility
    triggerNumEcoEvalCalcul: function(url, csrfToken) {
        this.uploadInventory(url, csrfToken);
    }
};
