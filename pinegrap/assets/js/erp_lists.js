/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the side drawer of the account, invoice and delivery note lists.
 *
 * A click on a row (anywhere but a link, a button or a checkbox) opens the
 * drawer printed by erp_drawer_markup() and fills it from get_erp_drawer.php.
 * The row's own buttons keep going to the document's full screen, so nothing
 * that worked before changes; the drawer is the quicker look.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var panel = document.getElementById('erp_drawer');

    if (!panel || !window.bootstrap || !bootstrap.Offcanvas) {
        return;
    }

    var title = document.getElementById('erp_drawer_title');
    var body = document.getElementById('erp_drawer_body');
    var open = document.getElementById('erp_drawer_open');
    var type = panel.getAttribute('data-erp-drawer-type');
    var endpoint = panel.getAttribute('data-erp-drawer-url');
    var failure = panel.getAttribute('data-erp-drawer-error');
    var drawer = bootstrap.Offcanvas.getOrCreateInstance(panel);

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
        title.textContent = '';
        open.classList.add('d-none');
        body.innerHTML = '<div class="d-flex justify-content-center py-5"><span class="spinner-border text-secondary" role="status" aria-hidden="true"></span></div>';
        drawer.show();

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
            if (!data || data.error || !data.html) {
                body.innerHTML = '<div class="alert alert-warning mb-0"></div>';
                body.firstChild.textContent = (data && data.error) ? data.error : failure;
                return;
            }
            title.textContent = data.title || '';
            open.setAttribute('href', data.url);
            open.classList.remove('d-none');
            body.innerHTML = data.html;
        }).catch(function () {
            if (ticket === current) {
                body.innerHTML = '<div class="alert alert-warning mb-0"></div>';
                body.firstChild.textContent = failure;
            }
        });
    }

    // Delegated, because DataTables redraws the rows on every page and sort.
    // Listened for in the capture phase and stopped there: the register's
    // row-click selection (multiselectCheckbox, syncEvent "click") would
    // otherwise tick the row as well. The checkbox itself still ticks.
    document.addEventListener('click', function (event) {
        var row = event.target.closest('tr[data-erp-drawer-id]');

        if (!row || event.target.closest('a, button, input, label, select, textarea')) {
            return;
        }

        // A selection is someone copying a number, not asking for the drawer.
        if (window.getSelection && String(window.getSelection()).length > 0) {
            return;
        }

        event.stopPropagation();
        show(row.getAttribute('data-erp-drawer-id'), row);
    }, true);

    // Keyboard: Enter on a focused row does what a click does.
    document.addEventListener('keydown', function (event) {
        if ((event.key !== 'Enter') || !event.target.matches || !event.target.matches('tr[data-erp-drawer-id]')) {
            return;
        }
        event.preventDefault();
        show(event.target.getAttribute('data-erp-drawer-id'), event.target);
    });

    // The chat launcher sits above the offcanvas; the shared class moves it
    // out of the way (backend.src.css, body.pg-side-panel-open).
    panel.addEventListener('show.bs.offcanvas', function () {
        document.body.classList.add('pg-side-panel-open');
    });
    panel.addEventListener('hidden.bs.offcanvas', function () {
        document.body.classList.remove('pg-side-panel-open');
        mark(null);
    });
})();
