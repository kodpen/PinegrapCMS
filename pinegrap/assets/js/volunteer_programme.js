/**
 * Pinegrap - Enterprise Website Platform
 *
 * The volunteer programmes list and a programme's screen
 * (view_volunteer_programmes.php, edit_volunteer_programme.php): opens the
 * Add programme drawer and, with the screen, the profile drawer; adds and
 * removes Set-up rows (roles, coordinators) from their <template>s, a new
 * role with its own row token, a coordinator from the user search; switches
 * the messages' tabs. Nothing is saved here: the forms post it all.
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
    var added = 0;

    // A drawer opens through pgUI, from the right or, on a phone, as a sheet
    // from the bottom.
    function openDrawer(drawer) {
        if (!drawer || !window.pgUI) {
            return;
        }
        drawer.classList.toggle('drawer-end', !phone.matches);
        drawer.classList.toggle('drawer-bottom', phone.matches);
        window.pgUI.drawer.show(drawer);
    }

    // A row from its template; a new role's token names its trainings field.
    function newRow(kind) {
        var template = document.querySelector('[data-pg-vp-template="' + kind + '"]');
        var box = document.createElement('div');
        added++;
        box.innerHTML = template.innerHTML.replace(/__ROW__/g, 'n' + added);
        return box.firstElementChild;
    }

    function addPerson(list) {
        var pick = document.getElementById('vp_person_pick');
        var option = pick && pick.selectedOptions[0];
        if (!option || !option.value || list.querySelector('input[value="' + option.value + '"]')) {
            return null;
        }
        var row = newRow('person');
        row.querySelector('input[type="hidden"]').value = option.value;
        row.querySelector('[data-pg-vp-person]').textContent = option.textContent;
        return row;
    }

    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-pg-vp-drawer]');
        if (opener) {
            openDrawer(document.getElementById(opener.getAttribute('data-pg-vp-drawer')));
            return;
        }

        var add = event.target.closest('[data-pg-vp-add]');
        if (add) {
            var kind = add.getAttribute('data-pg-vp-add');
            var list = document.querySelector('[data-pg-vp-list="' + kind + '"]');
            var row = (kind === 'person') ? addPerson(list) : newRow(kind);
            if (!row) {
                return;
            }
            list.appendChild(row);
            if (window.pgFields) {
                window.pgFields.init(row);
            }
            var first = row.querySelector('input:not([type="hidden"])');
            if (first) {
                first.focus();
            }
            return;
        }

        var remove = event.target.closest('[data-pg-vp-remove]');
        if (remove) {
            remove.closest('[data-pg-vp-row]').remove();
            return;
        }

        var tab = event.target.closest('[data-pg-vp-tab]');
        if (tab) {
            var key = tab.getAttribute('data-pg-vp-tab');
            tab.parentElement.querySelectorAll('[data-pg-vp-tab]').forEach(function (other) {
                other.setAttribute('aria-pressed', other === tab ? 'true' : 'false');
            });
            document.querySelectorAll('[data-pg-vp-pane]').forEach(function (pane) {
                pane.hidden = pane.getAttribute('data-pg-vp-pane') !== key;
            });
        }
    });

    // pgUI comes with the footer, after this script.
    window.addEventListener('load', function () {
        document.querySelectorAll('dialog[data-open]').forEach(openDrawer);
    });
})();
