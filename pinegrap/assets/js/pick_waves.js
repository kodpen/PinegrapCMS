/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pick waves (view_pick_waves.php, pick_wave.php): opens the Make a wave
 * drawer and keeps its live line ("42 addresses, 118 units match") with the
 * fields, read from the list's ?preview=1; on a wave's pick list, posts a
 * ticked or unticked line, greys a ticked row and moves it to the end (an
 * unticked one back to its shelf place) and updates the bar and the pill.
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
    // from the bottom.
    function openDrawer(drawer) {
        if (!drawer || !window.pgUI) {
            return;
        }
        drawer.classList.toggle('drawer-end', !phone.matches);
        drawer.classList.toggle('drawer-bottom', phone.matches);
        window.pgUI.drawer.show(drawer);
        preview(drawer.querySelector('[data-pg-pick-preview]'));
    }

    var previewTimer = null;
    var previewRun = 0;

    // The drawer's live line: what the chosen rule would take now. The Make
    // button waits while nothing matches.
    function preview(form) {
        if (!form) {
            return;
        }
        var line = form.querySelector('[data-pg-pick-line]');
        var make = form.querySelector('[data-pg-pick-make]');
        var params = new URLSearchParams();
        form.querySelectorAll('[data-pg-pick-rule]').forEach(function (field) {
            if (field.type === 'checkbox') {
                if (field.checked) {
                    params.set(field.name, field.value);
                }
            } else if (field.value !== '') {
                params.set(field.name, field.value);
            }
        });
        var run = ++previewRun;
        line.setAttribute('aria-busy', 'true');
        fetch(form.getAttribute('data-pg-pick-preview') + '&' + params.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }
                return response.json();
            })
            .then(function (answer) {
                if (run !== previewRun) {
                    return;
                }
                line.textContent = answer.line;
                make.disabled = !answer.addresses;
            }, function () {
                if (run === previewRun) {
                    line.textContent = '';
                    make.disabled = false;
                }
            })
            .then(function () {
                line.removeAttribute('aria-busy');
            });
    }

    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-pg-pick-drawer]');
        if (opener) {
            openDrawer(document.getElementById(opener.getAttribute('data-pg-pick-drawer')));
        }
    });

    document.addEventListener('change', function (event) {
        var field = event.target.closest('[data-pg-pick-rule]');
        if (field) {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(function () {
                preview(field.closest('[data-pg-pick-preview]'));
            }, 150);
            return;
        }

        var tick = event.target.closest('[data-pg-pick-tick]');
        if (tick) {
            send(tick);
        }
    });

    // Rows still to pick keep shelf order; a ticked row goes to the end.
    function place(list, row) {
        if (row.classList.contains('is-read')) {
            list.appendChild(row);
            return;
        }
        var at = Number(row.getAttribute('data-pg-pick-place'));
        var before = null;
        list.querySelectorAll('[data-pg-pick-row]').forEach(function (other) {
            if (before || other === row) {
                return;
            }
            if (other.classList.contains('is-read') || Number(other.getAttribute('data-pg-pick-place')) > at) {
                before = other;
            }
        });
        list.insertBefore(row, before);
    }

    function send(tick) {
        var list = tick.closest('[data-pg-pick-list]');
        var row = tick.closest('[data-pg-pick-row]');
        var on = tick.checked;
        var body = new URLSearchParams();
        body.set('ajax', '1');
        body.set('id', list.getAttribute('data-pg-pick-id'));
        body.set('key', row.getAttribute('data-pg-pick-key'));
        body.set('on', on ? '1' : '0');
        body.set('token', list.getAttribute('data-pg-pick-token'));
        tick.disabled = true;

        fetch(list.getAttribute('data-pg-pick-url'), { method: 'POST', credentials: 'same-origin', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                return response.json().then(function (answer) {
                    if (!response.ok || !answer.ok) {
                        throw new Error(answer.message || String(response.status));
                    }
                    return answer;
                });
            })
            .then(function (answer) {
                row.classList.toggle('is-read', on);
                place(list, row);
                var bar = document.querySelector('[data-pg-pick-bar]');
                if (bar) {
                    bar.textContent = answer.line;
                }
                var pill = document.querySelector('[data-pg-pick-pill]');
                if (pill && window.jQuery) {
                    window.jQuery(pill).html(answer.pill);
                }
                tick.disabled = false;
                tick.focus();
            }, function (error) {
                tick.checked = !on;
                tick.disabled = false;
                if (typeof window.pgToast === 'function') {
                    window.pgToast({ message: error.message, variant: 'danger' });
                }
            });
    }
})();
