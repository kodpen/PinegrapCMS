/**
 * Pinegrap - Enterprise Website Platform
 *
 * The order screen's Create Label chooser (pg_shipment_label_chooser_dialog()).
 * A Create Label button with data-pg-label-chooser opens the drawer (a sheet
 * from the bottom under 576 px); its rows come from api.php
 * (shipment_label_choose) while "Reading prices…" shows. Create label posts
 * shipment-label-form with label_request create-<ship_to_id> and the chosen
 * label_driver radio; it stays disabled until the rows are there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var PHONE = '(max-width: 575.98px)';
    var drawer = document.getElementById('shipment-label-chooser');

    if (!drawer || !window.pgUI || (typeof pg_api_url !== 'function')) {
        return;
    }

    var rows = document.getElementById('shipment-label-chooser-rows');
    var create = document.getElementById('shipment-label-chooser-create');
    var token = (typeof software_token !== 'undefined') ? software_token : '';
    // Each opening counts, so an answer for an earlier address is dropped.
    var opened = 0;

    function line(text) {
        var row = document.createElement('div');

        row.className = 'pg-card-inner-row pg-text-meta';
        row.textContent = text;
        rows.replaceChildren(row);
    }

    function open(shipToId) {
        var count = ++opened;

        drawer.classList.toggle('drawer-bottom', !!(window.matchMedia && window.matchMedia(PHONE).matches));
        drawer.classList.toggle('drawer-end', !drawer.classList.contains('drawer-bottom'));
        create.value = 'create-' + shipToId;
        create.disabled = true;
        line(drawer.getAttribute('data-reading'));
        pgUI.drawer.show(drawer);

        fetch(pg_api_url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'shipment_label_choose', token: token, ship_to_id: shipToId })
        }).then(function (response) {
            return response.json();
        }).then(function (answer) {
            if (count !== opened) {
                return;
            }

            if (!answer || (answer.status !== 'success')) {
                line((answer && answer.message) ? answer.message : drawer.getAttribute('data-failed'));
                return;
            }

            // The address no longer needs a choice (its method changed):
            // today's pick stands, so the label is created without one.
            if (!answer.due) {
                rows.replaceChildren();
                create.disabled = false;
                return;
            }

            $(rows).html(answer.html);
            create.disabled = !rows.querySelector('input[name="label_driver"]');
        }).catch(function () {
            if (count === opened) {
                line(drawer.getAttribute('data-failed'));
            }
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-pg-label-chooser]') : null;

        if (button) {
            event.preventDefault();
            open(parseInt(button.getAttribute('data-pg-label-chooser'), 10) || 0);
        }
    });

    // Closed without Create label: no choice stays with the form.
    pgUI.drawer.on(drawer, 'hidden', function () {
        rows.replaceChildren();
        create.disabled = true;
    });
}());
