/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the side drawer of the account, invoice and delivery note lists.
 *
 * The Quick look button of a row (erp_drawer_button()) opens the drawer
 * printed by erp_drawer_markup() and fills it from get_erp_drawer.php. A
 * click on the row itself selects it (list table driver) and the record's
 * name opens its full screen; the drawer is the quicker look.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var panel = document.getElementById('erp_drawer');

    // This runs while the page is parsed. Bootstrap is reached only through
    // pgUI and only on a click, so a Bootstrap that loads later (6 is a
    // deferred module) is in place by then.
    if (!panel || !window.pgUI) {
        return;
    }

    var title = document.getElementById('erp_drawer_title');
    var body = document.getElementById('erp_drawer_body');
    var open = document.getElementById('erp_drawer_open');
    var type = panel.getAttribute('data-erp-drawer-type');
    var endpoint = panel.getAttribute('data-erp-drawer-url');
    var failure = panel.getAttribute('data-erp-drawer-error');
    var loading = panel.getAttribute('data-erp-drawer-loading') || '';

    // The request in flight; a second click supersedes the first, so a slow
    // answer for the previous row never lands in the drawer of the next.
    var current = 0;
    var activeRow = null;

    function mark(row) {
        if (activeRow) {
            activeRow.classList.remove('table-active');
        }
        activeRow = row;
        if (row) {
            row.classList.add('table-active');
        }
    }

    function show(id, row) {
        var ticket = ++current;

        mark(row);
        // The dialog is named by its title, so the title reads the loading
        // text until the record arrives; the spinner carries the same text.
        title.textContent = loading;
        panel.setAttribute('aria-busy', 'true');
        open.classList.add('d-none');
        body.innerHTML = '<div class="d-flex justify-content-center py-12"><span class="spinner-border fg-secondary" style="font-size:2rem" role="status"><span class="visually-hidden"></span></span></div>';
        body.querySelector('.visually-hidden').textContent = loading;
        pgUI.drawer.show(panel);

        fetch(endpoint + '?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json().catch(function () {
                return { error: failure };
            });
        }).then(function (data) {
            if (ticket !== current) {
                return;
            }
            panel.removeAttribute('aria-busy');
            if (!data || data.error || !data.html) {
                body.innerHTML = '<div class="alert theme-warning mb-0"></div>';
                body.firstChild.textContent = (data && data.error) ? data.error : failure;
                return;
            }
            title.textContent = data.title || '';
            // Open in full comes back to this list with its filters.
            open.setAttribute('href', data.url + (data.url.indexOf('?') === -1 ? '?' : '&') + 'send_to=' + encodeURIComponent(window.location.pathname + window.location.search));
            open.classList.remove('d-none');
            body.innerHTML = data.html;
        }).catch(function () {
            if (ticket === current) {
                panel.removeAttribute('aria-busy');
                body.innerHTML = '<div class="alert theme-warning mb-0"></div>';
                body.firstChild.textContent = failure;
            }
        });
    }

    // Delegated, because the list table driver draws the rows again on a
    // sort or a search. Enter and Space on the button are clicks too.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('button[data-erp-drawer-id]');

        if (!button) {
            return;
        }

        show(button.getAttribute('data-erp-drawer-id'), button.closest('tr'));
    });

    // The chat launcher sits above the offcanvas; the shared class moves it
    // out of the way (backend.src.css, body.pg-side-panel-open).
    pgUI.drawer.on(panel, 'show', function () {
        document.body.classList.add('pg-side-panel-open');
    });
    pgUI.drawer.on(panel, 'hidden', function () {
        document.body.classList.remove('pg-side-panel-open');
        mark(null);
    });
})();
