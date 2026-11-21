/**
 * Pinegrap - Enterprise Website Platform
 *
 * The walk-in queue's places list and a place's screen (view_queue_places.php,
 * edit_queue_place.php): opens the Add place and Add desk drawers, adds and
 * removes the Set-up rows (services, days off, priority groups) from their
 * <template>s, gives a new service the first free letter and switches the
 * notice texts' tabs. Nothing is saved here: the forms post it all.
 *
 * Loaded with a plain <script src> by the screens; no .min twin.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var phone = window.matchMedia('(max-width: 575.98px)');

    // A drawer opens through pgUI, from the right or, on a phone, as a sheet
    // from the bottom; one carrying data-open opens with the screen.
    function openDrawer(drawer) {
        if (!drawer || !window.pgUI) {
            return;
        }
        drawer.classList.toggle('drawer-end', !phone.matches);
        drawer.classList.toggle('drawer-bottom', phone.matches);
        window.pgUI.drawer.show(drawer);
    }

    function freeLetter(list) {
        var taken = {};
        list.querySelectorAll('[data-pg-queue-letter]').forEach(function (input) {
            taken[input.value.toUpperCase()] = true;
        });
        for (var code = 65; code <= 90; code++) {
            if (!taken[String.fromCharCode(code)]) {
                return String.fromCharCode(code);
            }
        }
        return '';
    }

    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-pg-queue-drawer]');
        if (opener) {
            openDrawer(document.getElementById(opener.getAttribute('data-pg-queue-drawer')));
            return;
        }

        var add = event.target.closest('[data-pg-queue-add]');
        if (add) {
            var kind = add.getAttribute('data-pg-queue-add');
            var list = document.querySelector('[data-pg-queue-list="' + kind + '"]');
            var row = document.querySelector('[data-pg-queue-template="' + kind + '"]').content.firstElementChild.cloneNode(true);
            var letter = row.querySelector('[data-pg-queue-letter]');
            if (letter) {
                letter.value = freeLetter(list);
            }
            list.appendChild(row);
            if (window.pgFields) {
                window.pgFields.init(row);
            }
            var first = row.querySelector('input:not([type="hidden"]):not([data-pg-queue-letter])');
            if (first) {
                first.focus();
            }
            return;
        }

        var remove = event.target.closest('[data-pg-queue-remove]');
        if (remove) {
            remove.closest('[data-pg-queue-row]').remove();
            return;
        }

        var tab = event.target.closest('[data-pg-queue-tab]');
        if (tab) {
            var key = tab.getAttribute('data-pg-queue-tab');
            tab.parentElement.querySelectorAll('[data-pg-queue-tab]').forEach(function (other) {
                other.setAttribute('aria-pressed', other === tab ? 'true' : 'false');
            });
            document.querySelectorAll('[data-pg-queue-pane]').forEach(function (pane) {
                pane.hidden = pane.getAttribute('data-pg-queue-pane') !== key;
            });
        }
    });

    // pgUI comes with the footer, after this script.
    window.addEventListener('load', function () {
        document.querySelectorAll('dialog[data-open]').forEach(openDrawer);
    });
})();
