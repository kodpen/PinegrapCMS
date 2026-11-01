/**
 * Pinegrap - Enterprise Website Platform
 *
 * The switch row at the top of both dashboards (pg_dashboard_v2_switch_row(),
 * includes/dashboard/v2/layout.php). Flipping the switch saves the person's
 * choice (api.php dashboard_switch) and loads welcome.php again, which draws
 * the dashboard the choice names. A failed save puts the switch back and
 * says so with the text the server printed on the row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    function failed(input, row) {
        input.checked = !input.checked;
        input.disabled = false;

        var text = row.getAttribute('data-error-text') || '';

        if (typeof window.pgToast === 'function') {
            window.pgToast({ message: text, variant: 'danger' });
        } else if (typeof window.pgAnnounce === 'function') {
            window.pgAnnounce(text);
        }
    }

    function init() {
        var row = document.getElementById('pg_dash_switch_row');
        var input = document.getElementById('pg_dash_switch');

        if (!row || !input) {
            return;
        }

        input.addEventListener('change', function () {
            input.disabled = true;

            fetch('api.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'dashboard_switch',
                    token: (typeof software_token !== 'undefined') ? software_token : '',
                    on: input.checked ? 1 : 0
                })
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (data && data.status === 'success') {
                    window.location.replace('welcome.php');
                    return;
                }
                failed(input, row);
            }).catch(function () {
                failed(input, row);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
