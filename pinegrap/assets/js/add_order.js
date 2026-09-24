/**
 * Pinegrap - Enterprise Website Platform
 *
 * The counter sale screen (add_order.php): the request queue, the barcode
 * scanner, the product and customer search, the payment card and the
 * keyboard.
 *
 * Every change goes to the server as one POST and comes back as the parts of
 * the screen it changed; this script only places them. It works out nothing
 * the server owns - lines, totals, VAT - and keeps only what is the
 * operator's own: the view, the payment method and till, the amount handed
 * over and the change.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var root = document.getElementById('pg_sale');
    var configNode = document.getElementById('pg_sale_config');

    if (!root || !configNode) {
        return;
    }

    var cfg = JSON.parse(configNode.textContent);
    var t = cfg.i18n;

    function byId(id) {
        return document.getElementById(id);
    }

    function fill(text, vars) {
        return String(text).replace(/\{var:(\d+)\}/g, function (all, n) {
            var value = vars[Number(n) - 1];
            return (value === undefined) ? all : value;
        });
    }

    function esc(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Browser storage is a convenience here (the view, the error sound); a
    // private window that refuses it just gets the defaults.
    function remember(key, value) {
        try {
            if (value === undefined) {
                return window.localStorage.getItem(key);
            }
            window.localStorage.setItem(key, value);
        } catch (e) {}
        return null;
    }

    // Scanners and keyboards say "Enter"; some synthetic events only carry
    // the key code.
    function isEnter(event) {
        return (event.key === 'Enter') || (event.keyCode === 13);
    }

    function toast(message, variant) {
        if (!message) {
            return;
        }
        if (typeof window.pgToast === 'function') {
            window.pgToast({ message: message, variant: variant || 'info', delay: 3500 });
        }
    }

    var el = {
        product: byId('pg_sale_product'),
        qty: byId('pg_sale_qty'),
        add: byId('pg_sale_add'),
        foot: byId('pg_sale_product_foot'),
        results: byId('pg_sale_results'),
        customer: byId('pg_sale_customer'),
        rows: byId('pg_sale_rows'),
        count: byId('pg_sale_count'),
        summary: byId('pg_sale_summary'),
        summaryCard: byId('pg_sale_summary_card'),
        pay: byId('pg_sale_pay'),
        invoice: byId('pg_sale_invoice'),
        ledger: byId('pg_sale_ledger'),
        done: byId('pg_sale_done'),
        complete: byId('pg_sale_complete'),
        received: byId('pg_sale_received'),
        quick: byId('pg_sale_quick'),
        change: byId('pg_sale_change'),
        till: byId('pg_sale_till'),
        extra: byId('pg_sale_extra'),
        savebar: byId('pg_sale_savebar'),
        barTotal: byId('pg_sale_bar_total'),
        barHow: byId('pg_sale_bar_how'),
        barGo: byId('pg_sale_bar_go'),
        clearBtn: byId('pg_sale_clear_btn'),
        recentBtn: byId('pg_sale_recent_btn'),
        recent: byId('pg_sale_recent'),
        recentBody: byId('pg_sale_recent_body'),
        info: byId('pg_sale_info'),
        live: byId('pg_sale_live'),
        soundTick: byId('pg_sale_sound_tick'),
        accountModal: byId('pg_sale_account_modal'),
        accountForm: byId('pg_sale_account_form')
    };

    var payBody = el.pay ? el.pay.querySelector('.pg-sale-pay') : null;

    var state = {
        total: Number(cfg.total) || 0,
        count: el.rows.querySelectorAll('tr[data-line]').length,
        pending: 0,
        done: false,
        completing: false,
        customerHtml: el.customer.innerHTML,
        picker: false,
        sound: remember('pg_sale_sound') !== '0',
        method: payBody ? (payBody.getAttribute('data-method') || 'cash') : 'cash',
        tillTouched: false
    };

    /* ── Money, as local_sale_money() writes it ───────────────────────── */

    // The separators come from the server: the panel language's with the
    // ERP on (1.234,56 / 1,234.56), the store's e-commerce format otherwise.
    var decimalMark = (typeof cfg.decimal === 'string' && cfg.decimal !== '') ? cfg.decimal : '.';
    var thousandsMark = (typeof cfg.thousands === 'string') ? cfg.thousands : ',';

    function amountText(cents) {
        var parts = (Math.abs(cents) / 100).toFixed(2).split('.');
        return parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsMark) + decimalMark + parts[1];
    }

    function money(cents) {
        return ((cents < 0) ? '-' : '') + cfg.symbol + amountText(cents);
    }

    // "2500", "2.500", "2500,5" and "1,666.20" all read as people meant them:
    // the last separator followed by one or two digits is the decimal one.
    function parseAmount(value) {
        var text = String(value || '').replace(/[^\d.,]/g, '');
        if (text === '') {
            return 0;
        }
        var at = Math.max(text.lastIndexOf(','), text.lastIndexOf('.'));
        var whole = text;
        var fraction = '';
        if ((at > -1) && ((text.length - at - 1) <= 2)) {
            whole = text.slice(0, at);
            fraction = text.slice(at + 1);
        }
        whole = whole.replace(/[.,]/g, '');
        return Math.round(parseFloat((whole || '0') + '.' + (fraction || '0')) * 100);
    }

    /* ── Sound and announcements ──────────────────────────────────────── */

    var audio = null;

    // A short low tone when a scan fails: at a till people look at the goods,
    // not the screen.
    function beep() {
        if (!state.sound) {
            return;
        }
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            var osc = audio.createOscillator();
            var gain = audio.createGain();
            osc.type = 'square';
            osc.frequency.value = 220;
            gain.gain.value = 0.05;
            osc.connect(gain);
            gain.connect(audio.destination);
            osc.start();
            osc.stop(audio.currentTime + 0.18);
        } catch (e) {}
    }

    var announceTimer = null;

    function announce(text) {
        clearTimeout(announceTimer);
        announceTimer = setTimeout(function () {
            el.live.textContent = text;
        }, 150);
    }

    /* ── The product box's foot line: ready, working, or what went wrong ─ */

    var footError = '';

    function renderFoot() {
        if (footError !== '') {
            el.foot.className = 'pg-sale-foot is-error';
            el.foot.setAttribute('role', 'alert');
            el.foot.innerHTML = '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>' + esc(footError) + '</span>';
            return;
        }
        el.foot.className = 'pg-sale-foot';
        el.foot.removeAttribute('role');
        el.foot.innerHTML = '<span class="pg-sale-dot" aria-hidden="true"></span><span>' + esc(state.pending > 0 ? t.working : t.ready) + '</span><kbd>F2</kbd>';
    }

    function productError(message) {
        footError = message || '';
        renderFoot();
    }

    /* ── The request queue ────────────────────────────────────────────────
       Requests go one at a time, in the order they were made: ten fast scans
       are ten adds in a row, never two answers crossing. Each answer carries
       the whole cart, so the last one to land is always the truth. */

    var queue = Promise.resolve();

    function busy() {
        root.classList.toggle('is-busy', state.pending > 0);
        if (footError === '') {
            renderFoot();
        }
    }

    function post(action, data) {
        var body = new FormData();
        body.append('action', action);
        body.append('token', cfg.token);
        Object.keys(data || {}).forEach(function (key) {
            body.append(key, data[key]);
        });

        return fetch(cfg.url, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (response) {
            return response.text().then(function (text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw { kind: 'bad' };
                }
            });
        }, function () {
            throw { kind: 'offline' };
        });
    }

    function send(action, data, options) {
        options = options || {};
        state.pending++;
        busy();

        var job = queue.then(function () {
            return post(action, data).then(function (answer) {
                apply(answer, options);
                return answer;
            }, function (error) {
                fault(error);
                return null;
            });
        });

        queue = job.then(function () {
            state.pending--;
            busy();
            refreshButtons();
        });

        return job;
    }

    function getJson(params) {
        var query = Object.keys(params).map(function (key) {
            return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
        }).join('&');

        return fetch(cfg.url + '?' + query, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json();
        });
    }

    // The screen may no longer match the server after a failed request, so
    // it is redrawn from the server rather than guessed.
    function refresh() {
        return getJson({ request: 'state' }).then(function (answer) {
            apply(answer, { quiet: true });
        }).catch(function () {});
    }

    function fault(error) {
        productError((error && error.kind === 'bad') ? t.badAnswer : t.offline);
        beep();
        refresh();
    }

    function showSession(message) {
        productError(message || t.session);
        toast(message || t.session, 'danger');
    }

    /* ── Placing an answer ────────────────────────────────────────────── */

    function apply(d, options) {
        options = options || {};

        if (!d) {
            return;
        }

        if ((d.code === 'session') || (d.code === 'denied')) {
            showSession(d.message);
            return;
        }

        if (d.cart) {
            renderCart(d.cart);
        }
        if (d.customer) {
            renderCustomer(d.customer);
        }
        if (d.result) {
            showDone(d.result);
        }
        if ((typeof d.recent === 'string') && el.recentBody) {
            el.recentBody.innerHTML = d.recent;
        }

        if (options.quiet) {
            return;
        }

        var lineError = !d.ok && (d.line_id > 0);

        if (!d.ok && ((d.field === 'product') || (!lineError && (options.field === 'product')))) {
            productError(d.message);
            beep();
        } else if (lineError) {
            // The row carries the message itself.
            beep();
            announce(d.message);
        } else if (!d.ok) {
            toast(d.message, (d.level === 'warning') ? 'warning' : ((d.level === 'info') ? 'info' : 'danger'));
            if (d.level !== 'info') {
                beep();
            }
        } else if (options.announce && d.message) {
            announce(d.message + ' ' + fill(t.cartTotal, [d.cart ? d.cart.total_text : money(state.total)]));
        } else if (options.toast && d.message) {
            toast(d.message, (d.level === 'success') ? 'success' : 'info');
        }
    }

    function renderCart(cart) {
        el.rows.innerHTML = cart.rows;
        el.count.textContent = cart.count_text || '';
        el.summary.innerHTML = cart.summary;
        state.total = Number(cart.total_cents) || 0;
        state.count = Number(cart.count) || 0;

        var flash = el.rows.querySelector('.pg-sale-flash');
        if (flash) {
            setTimeout(function () {
                flash.classList.remove('pg-sale-flash');
            }, 1600);
        }

        refreshButtons();
        refreshChange();
        refreshSplit();
        refreshSavebar();
    }

    function renderCustomer(customer) {
        state.customerHtml = customer.html;
        if (!state.picker && (el.customer.innerHTML !== customer.html)) {
            el.customer.innerHTML = customer.html;
        }
        if (el.invoice) {
            el.invoice.innerHTML = customer.invoice || '';
        }
        if (el.ledger) {
            el.ledger.innerHTML = customer.ledger || '';
        }
        applyWhen();
    }

    /* ── Done / new sale ──────────────────────────────────────────────── */

    function showDone(result) {
        el.done.innerHTML = result.html;
        el.done.hidden = false;
        el.summaryCard.hidden = true;
        if (el.pay) {
            el.pay.hidden = true;
        }
        el.ledger.hidden = true;
        state.done = true;
        if (el.received) {
            el.received.value = '';
        }
        if (state.method === 'split') {
            resetSplit();
            setMethod('cash', false);
        }
        refreshChange();
        refreshSavebar();
        el.product.focus();

        var head = el.done.querySelector('.pg-sale-done-head b');
        if (head) {
            announce(head.textContent);
        }
    }

    function newSale() {
        if (!state.done) {
            return;
        }
        state.done = false;
        el.done.hidden = true;
        el.done.innerHTML = '';
        el.summaryCard.hidden = false;
        if (el.pay) {
            el.pay.hidden = false;
        }
        el.ledger.hidden = false;
        refreshSavebar();
        el.product.focus();
    }

    /* ── Buttons, save bar, toolbar line ──────────────────────────────── */

    function methodLabel() {
        var label = cfg.methods[state.method] || '';
        if (state.method === 'split') {
            var names = splitParts().map(function (part) {
                return (cfg.methods[part.method] || '') + ' ' + money(part.amount);
            });
            return names.length ? label + ' · ' + names.join(' + ') : label;
        }
        if ((state.method !== 'later') && el.till && el.till.selectedIndex > -1 && !isHiddenByMethod(el.till)) {
            label += ' · ' + el.till.options[el.till.selectedIndex].text;
        }
        return label;
    }

    // data-sale-when and data-sale-when-not hold one method or several,
    // separated by spaces.
    function methodIn(list) {
        return String(list || '').split(/\s+/).indexOf(state.method) > -1;
    }

    function isHiddenByMethod(node) {
        var box = node.closest('[data-sale-when-not]');
        return !!box && methodIn(box.getAttribute('data-sale-when-not'));
    }

    function refreshButtons() {
        var empty = (state.count === 0) && (state.pending === 0);
        if (el.complete) {
            el.complete.disabled = empty || state.completing;
            el.complete.classList.toggle('is-working', state.completing);
            var label = el.complete.querySelector('span');
            if (label) {
                label.textContent = state.completing ? t.completing : t.complete;
            }
        }
        if (el.barGo) {
            el.barGo.disabled = empty || state.completing;
        }
        if (el.clearBtn) {
            el.clearBtn.disabled = (state.count === 0);
        }
    }

    function refreshSavebar() {
        if (!el.savebar) {
            return;
        }
        el.savebar.hidden = state.done || (state.count === 0);
        el.barTotal.textContent = money(state.total);
        el.barHow.textContent = cfg.erp ? methodLabel() : '';
        if (el.info) {
            el.info.textContent = (cfg.erp && payBody && payBody.querySelector('[data-sale-method]')) ? methodLabel() : '';
        }
    }

    /* ── Payment: method, till, amount received, change ───────────────── */

    function applyWhen() {
        root.querySelectorAll('[data-sale-when]').forEach(function (node) {
            node.hidden = !methodIn(node.getAttribute('data-sale-when'));
        });
        root.querySelectorAll('[data-sale-when-not]').forEach(function (node) {
            node.hidden = methodIn(node.getAttribute('data-sale-when-not'));
        });
    }

    var tillKinds = {
        cash: ['cash'],
        card: ['pos', 'credit_card', 'bank'],
        transfer: ['bank'],
        cheque: ['cash', 'bank'],
        other: ['cash', 'bank', 'pos', 'credit_card']
    };

    function pickTill(method) {
        if (!el.till || state.tillTouched) {
            return;
        }
        pickTillIn(el.till, method);
    }

    function pickTillIn(select, method) {
        var kinds = tillKinds[method];
        if (!select || !kinds) {
            return;
        }
        for (var k = 0; k < kinds.length; k++) {
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].getAttribute('data-kind') === kinds[k]) {
                    select.selectedIndex = i;
                    return;
                }
            }
        }
    }

    /* ── Split payment: two ways of paying, the rest on the account ───── */

    var splitBox = payBody ? payBody.querySelector('.pg-sale-split') : null;
    var splitRows = splitBox ? Array.prototype.slice.call(splitBox.querySelectorAll('[data-sale-part]')) : [];

    function splitParts() {
        return (splitRows || []).map(function (row) {
            return {
                method: row.querySelector('[data-part-method]').value,
                till: row.querySelector('[data-part-till]').value,
                amount: parseAmount(row.querySelector('[data-part-amount]').value)
            };
        }).filter(function (part) {
            return part.amount > 0;
        });
    }

    // The second part follows what the first leaves until someone types in
    // it; the line under the parts says what stays on the account.
    function refreshSplit() {
        if (!splitBox) {
            return;
        }
        if (splitRows[1] && !splitRows[1].hasAttribute('data-touched')) {
            var first = parseAmount(splitRows[0].querySelector('[data-part-amount]').value);
            var rest = Math.max(0, state.total - first);
            splitRows[1].querySelector('[data-part-amount]').value = (first > 0 && rest > 0) ? amountText(rest).split(thousandsMark).join('') : '';
        }
        var sum = 0;
        splitParts().forEach(function (part) {
            sum += part.amount;
        });
        var line = byId('pg_sale_split_rest');
        if (line) {
            var over = sum > state.total;
            line.classList.toggle('text-danger', over);
            line.textContent = (sum <= 0) ? '' : (over ? t.splitOver : ((sum === state.total) ? t.splitPaid : fill(t.splitRest, [money(state.total - sum)])));
        }
    }

    function resetSplit() {
        splitRows.forEach(function (row, index) {
            row.removeAttribute('data-touched');
            row.removeAttribute('data-till-touched');
            row.querySelector('[data-part-amount]').value = '';
            row.querySelector('[data-part-method]').value = (index === 0) ? 'cash' : 'card';
            pickTillIn(row.querySelector('[data-part-till]'), row.querySelector('[data-part-method]').value);
        });
        refreshSplit();
    }

    splitRows.forEach(function (row, index) {
        var method = row.querySelector('[data-part-method]');
        var till = row.querySelector('[data-part-till]');
        var amount = row.querySelector('[data-part-amount]');
        method.addEventListener('change', function () {
            if (!row.hasAttribute('data-till-touched')) {
                pickTillIn(till, method.value);
            }
            refreshSavebar();
        });
        till.addEventListener('change', function () {
            row.setAttribute('data-till-touched', '1');
        });
        amount.addEventListener('input', function () {
            if (index > 0) {
                row.setAttribute('data-touched', '1');
            }
            refreshSplit();
            refreshSavebar();
        });
        amount.addEventListener('keydown', function (event) {
            if (isEnter(event)) {
                event.preventDefault();
                complete();
            }
        });
    });

    function setMethod(method, fromUser) {
        state.method = method;
        if (payBody) {
            payBody.setAttribute('data-method', method);
            payBody.querySelectorAll('.pg-sale-method[data-sale-method]').forEach(function (button) {
                button.setAttribute('aria-pressed', String(button.getAttribute('data-sale-method') === method));
            });
        }
        if (el.extra) {
            var extra = (method === 'cheque') || (method === 'other');
            el.extra.value = extra ? method : '';
            if (!extra && fromUser) {
                el.extra.hidden = true;
                var show = root.querySelector('[data-sale-extra-show]');
                if (show) {
                    show.hidden = false;
                }
            }
        }
        if (fromUser) {
            pickTill(method);
        }
        applyWhen();
        if ((method === 'split') && fromUser && splitRows[0]) {
            refreshSplit();
            splitRows[0].querySelector('[data-part-amount]').focus();
        }
        refreshChange();
        refreshSavebar();
    }

    function quickAmounts(total) {
        var lira = Math.ceil(total / 100);
        var out = [];
        [50, 100, 500, 1000].forEach(function (step) {
            var value = Math.ceil(lira / step) * step;
            if ((value > lira) && (out.indexOf(value) === -1) && (out.length < 3)) {
                out.push(value);
            }
        });
        return out;
    }

    function refreshChange() {
        if (!el.received) {
            return;
        }

        if (el.quick) {
            var chips = '';
            if (state.total > 0) {
                chips += '<button type="button" class="no-submit" data-sale-recv="' + state.total + '">' + esc(t.exact) + '</button>';
                quickAmounts(state.total).forEach(function (value) {
                    chips += '<button type="button" class="no-submit" data-sale-recv="' + (value * 100) + '">' + esc(money(value * 100).slice(0, -(decimalMark.length + 2))) + '</button>';
                });
            }
            el.quick.innerHTML = chips;
        }

        var received = parseAmount(el.received.value);
        var box = el.change;

        if ((received <= 0) || (state.total <= 0) || state.done) {
            box.hidden = true;
            return;
        }

        box.hidden = false;
        box.classList.toggle('is-short', received < state.total);
        box.querySelector('span').textContent = (received >= state.total) ? t.change : t.short;
        box.querySelector('b').textContent = money(Math.abs(received - state.total));
    }

    /* ── Adding products ──────────────────────────────────────────────── */

    function qtyValue() {
        var value = parseInt(el.qty.value, 10);
        return (isNaN(value) || value < 1) ? 1 : Math.min(value, 9999);
    }

    function addItem(what, quantity) {
        newSale();
        productError('');
        what.quantity = quantity || 1;
        el.qty.value = 1;
        send('add_item', what, { announce: true, field: 'product' });
    }

    /* A small combobox: a text box, a listbox under it, arrow keys moving an
       active option that aria-activedescendant points at. Used by the product
       search and the customer search. */
    function combo(input, list, onPick) {
        var items = [];
        var active = -1;

        function paint() {
            list.querySelectorAll('[role="option"]').forEach(function (node, index) {
                var on = (index === active);
                node.classList.toggle('is-active', on);
                node.setAttribute('aria-selected', String(on));
                if (on) {
                    input.setAttribute('aria-activedescendant', node.id);
                    node.scrollIntoView({ block: 'nearest' });
                }
            });
            if (active < 0) {
                input.removeAttribute('aria-activedescendant');
            }
        }

        list.addEventListener('mousedown', function (event) {
            // Keep the focus in the box, or the blur closes the list first.
            event.preventDefault();
        });

        list.addEventListener('click', function (event) {
            var node = event.target.closest('[role="option"]');
            if (!node || node.getAttribute('aria-disabled') === 'true') {
                return;
            }
            onPick(items[Number(node.getAttribute('data-index'))]);
        });

        return {
            show: function (html, data) {
                items = data;
                active = -1;
                list.innerHTML = html;
                list.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                paint();
            },
            close: function () {
                items = [];
                active = -1;
                list.hidden = true;
                list.innerHTML = '';
                input.setAttribute('aria-expanded', 'false');
                input.removeAttribute('aria-activedescendant');
            },
            open: function () {
                return !list.hidden;
            },
            move: function (delta) {
                if (!items.length) {
                    return;
                }
                active = (active < 0)
                    ? ((delta > 0) ? 0 : items.length - 1)
                    : (active + delta + items.length) % items.length;
                paint();
            },
            current: function () {
                return (active > -1) ? items[active] : null;
            }
        };
    }

    var productList = combo(el.product, el.results, function (item) {
        if (!item) {
            return;
        }
        el.product.value = '';
        productList.close();
        addItem({ product_id: item.id }, qtyValue());
        el.product.focus();
    });

    var productTimer = null;
    var productTicket = 0;

    function searchProducts() {
        var q = el.product.value.trim();
        var ticket = ++productTicket;

        if (q.length < 2) {
            productList.close();
            return;
        }

        getJson({ request: 'search_products', q: q }).then(function (data) {
            if ((ticket !== productTicket) || (el.product.value.trim() !== q)) {
                return;
            }
            var results = (data && data.results) || [];
            if (!results.length) {
                productList.show('<div class="pg-sale-res-empty">' + esc(t.noProductsEnter) + '</div>', []);
                return;
            }
            var html = results.map(function (p, index) {
                var title = p.short_description || p.name;
                var out = (p.inventory === 1) && (p.inventory_quantity <= 0);
                var sub = (p.name !== title) ? '<span class="font-monospace">' + esc(p.name) + '</span>' : '';
                if (p.inventory === 1) {
                    sub += (sub ? ' · ' : '') + (out ? '<span class="text-danger-emphasis">' + esc(t.outOfStock) + '</span>' : esc(fill(t.inStock, [p.inventory_quantity])));
                }
                return '<div class="pg-sale-res" role="option" id="pg_sale_opt_' + index + '" data-index="' + index + '" aria-selected="false"' + (out ? ' aria-disabled="true"' : '') + '>'
                    + '<b>' + esc(title) + '</b><small>' + sub + '</small>'
                    + '<span class="pg-sale-res-price">' + esc(p.price_text) + '<small>' + esc(t.vatIncluded) + '</small></span></div>';
            }).join('');
            productList.show(html, results);
        }).catch(function () {
            if (ticket === productTicket) {
                productList.show('<div class="pg-sale-res-empty">' + esc(t.searchFailed) + '</div>', []);
            }
        });
    }

    function addFromBox() {
        var code = el.product.value.trim();
        var picked = productList.current();

        clearTimeout(productTimer);
        productTicket++;
        productList.close();

        if (picked) {
            el.product.value = '';
            addItem({ product_id: picked.id }, qtyValue());
            return;
        }
        if (code === '') {
            return;
        }
        el.product.value = '';
        addItem({ barcode: code.replace(/\*/g, '-') }, qtyValue());
    }

    el.product.addEventListener('input', function () {
        if (footError !== '') {
            productError('');
        }
        clearTimeout(productTimer);
        productTimer = setTimeout(searchProducts, 250);
    });

    el.product.addEventListener('keydown', function (event) {
        if ((event.key === 'ArrowDown') || (event.key === 'ArrowUp')) {
            if (productList.open()) {
                event.preventDefault();
                productList.move(event.key === 'ArrowDown' ? 1 : -1);
            }
        } else if (isEnter(event)) {
            if (event.ctrlKey || event.metaKey) {
                return;
            }
            event.preventDefault();
            if ((el.product.value.trim() === '') && state.done) {
                newSale();
                return;
            }
            addFromBox();
        } else if (event.key === 'Escape') {
            if (productList.open()) {
                event.stopPropagation();
                productList.close();
            } else if (el.product.value !== '') {
                el.product.value = '';
            }
        }
    });

    el.product.addEventListener('blur', function () {
        setTimeout(productList.close, 120);
    });

    el.add.addEventListener('click', function () {
        addFromBox();
        el.product.focus();
    });

    /* ── The cart ─────────────────────────────────────────────────────── */

    el.rows.addEventListener('click', function (event) {
        var button = event.target.closest('button');
        if (!button) {
            return;
        }
        var row = button.closest('tr[data-line]');
        var input = row ? row.querySelector('[data-sale-qty]') : null;
        var current = input ? (parseInt(input.value, 10) || 0) : 0;

        if (button.hasAttribute('data-sale-inc')) {
            send('set_qty', { item_id: button.getAttribute('data-sale-inc'), quantity: current + 1 });
        } else if (button.hasAttribute('data-sale-dec')) {
            send('set_qty', { item_id: button.getAttribute('data-sale-dec'), quantity: current - 1 });
        } else if (button.hasAttribute('data-sale-rm')) {
            send('set_qty', { item_id: button.getAttribute('data-sale-rm'), quantity: 0 }, { toast: true });
        }
    });

    el.rows.addEventListener('change', function (event) {
        var input = event.target.closest('[data-sale-qty]');
        if (!input) {
            return;
        }
        var value = parseInt(input.value, 10);
        send('set_qty', { item_id: input.getAttribute('data-sale-qty'), quantity: isNaN(value) ? 0 : value });
    });

    el.rows.addEventListener('keydown', function (event) {
        var input = event.target.closest('[data-sale-qty]');
        if (!input) {
            return;
        }
        if (isEnter(event)) {
            event.preventDefault();
            input.blur();
        } else if ((event.key === 'ArrowUp') || (event.key === 'ArrowDown')) {
            event.preventDefault();
            var value = (parseInt(input.value, 10) || 0) + ((event.key === 'ArrowUp') ? 1 : -1);
            send('set_qty', { item_id: input.getAttribute('data-sale-qty'), quantity: value });
        }
    });

    /* ── The customer ─────────────────────────────────────────────────── */

    var customerList = null;
    var customerTimer = null;
    var customerTicket = 0;
    var customerQuery = '';

    function openPicker() {
        if (state.picker) {
            byId('pg_sale_cust_q').focus();
            return;
        }
        state.picker = true;
        el.customer.innerHTML =
            '<div class="pg-sale-ig pg-sale-ig-sm form-control">'
            + '<span class="pg-sale-ig-icon" aria-hidden="true"><i class="bi bi-person"></i></span>'
            + '<input type="text" id="pg_sale_cust_q" autocomplete="off" spellcheck="false" role="combobox" aria-expanded="false" aria-controls="pg_sale_cust_results" aria-autocomplete="list" aria-labelledby="pg_sale_customer_label" placeholder="' + esc(t.customerPlaceholder) + '">'
            + '<button type="button" class="btn btn-sm btn-ghost no-submit" data-sale-cust="cancel" aria-label="' + esc(t.cancel) + '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>'
            + '</div>'
            + '<div class="pg-sale-results" id="pg_sale_cust_results" role="listbox" hidden></div>';

        var input = byId('pg_sale_cust_q');
        customerList = combo(input, byId('pg_sale_cust_results'), pickCustomer);

        input.addEventListener('input', function () {
            clearTimeout(customerTimer);
            customerTimer = setTimeout(searchCustomers, 250);
        });
        input.addEventListener('keydown', function (event) {
            if ((event.key === 'ArrowDown') || (event.key === 'ArrowUp')) {
                event.preventDefault();
                customerList.move(event.key === 'ArrowDown' ? 1 : -1);
            } else if (isEnter(event)) {
                event.preventDefault();
                var picked = customerList.current();
                if (picked) {
                    pickCustomer(picked);
                }
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                closePicker();
                el.product.focus();
            }
        });
        input.focus();
    }

    function closePicker() {
        if (!state.picker) {
            return;
        }
        state.picker = false;
        customerTicket++;
        clearTimeout(customerTimer);
        el.customer.innerHTML = state.customerHtml;
    }

    function searchCustomers() {
        var input = byId('pg_sale_cust_q');
        if (!input) {
            return;
        }
        var q = input.value.trim();
        var ticket = ++customerTicket;
        customerQuery = q;

        if (q.length < 2) {
            if (cfg.canErp && (q.length > 0)) {
                showCustomers([], q);
            } else {
                customerList.close();
            }
            return;
        }

        getJson({ request: 'search_customers', q: q }).then(function (data) {
            if (ticket === customerTicket) {
                showCustomers((data && data.results) || [], q);
            }
        }).catch(function () {
            if (ticket === customerTicket) {
                customerList.show('<div class="pg-sale-res-empty">' + esc(t.searchFailed) + '</div>', []);
            }
        });
    }

    function showCustomers(results, q) {
        var items = results.slice();
        var html = results.map(function (c, index) {
            return '<div class="pg-sale-res" role="option" id="pg_sale_copt_' + index + '" data-index="' + index + '" aria-selected="false">'
                + '<b><i class="bi ' + (c.kind === 'account' ? 'bi-journal-text' : 'bi-person') + ' me-2" aria-hidden="true"></i>' + esc(c.label) + '</b>'
                + (c.sub ? '<small>' + esc(c.sub) + '</small>' : '') + '</div>';
        }).join('');

        if (!results.length) {
            html = '<div class="pg-sale-res-empty">' + esc(t.noCustomers) + '</div>';
        }

        // Somebody the shop has not met yet: a new account from the name typed.
        if (cfg.canErp && el.accountModal) {
            var index = items.length;
            items.push({ kind: 'new' });
            html += '<div class="pg-sale-res pg-sale-res-new" role="option" id="pg_sale_copt_' + index + '" data-index="' + index + '" aria-selected="false">'
                + '<b><i class="bi bi-person-plus me-2" aria-hidden="true"></i>' + esc(q ? fill(t.newAccount, [q]) : t.newAccountEmpty) + '</b></div>';
        }

        customerList.show(html, items);
    }

    function pickCustomer(item) {
        if (!item) {
            return;
        }
        if (item.kind === 'new') {
            openAccountModal(customerQuery);
            return;
        }
        state.picker = false;
        el.customer.innerHTML = state.customerHtml;
        send('set_customer', { contact_id: item.contact_id, account_id: item.account_id }, { toast: true });
        el.product.focus();
    }

    el.customer.addEventListener('click', function (event) {
        var button = event.target.closest('[data-sale-cust]');
        if (!button) {
            return;
        }
        var what = button.getAttribute('data-sale-cust');
        if (what === 'change') {
            openPicker();
        } else if (what === 'cancel') {
            closePicker();
            el.product.focus();
        } else if (what === 'clear') {
            send('clear_customer', {}, { toast: true });
        }
    });

    /* ── Quick account ────────────────────────────────────────────────── */

    function openAccountModal(name) {
        if (!el.accountModal || !window.bootstrap) {
            return;
        }
        var form = el.accountForm;
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(function (node) {
            node.classList.remove('is-invalid');
        });
        form.elements.title.value = name || '';
        bootstrap.Modal.getOrCreateInstance(el.accountModal).show();
    }

    if (el.accountModal) {
        el.accountModal.addEventListener('shown.bs.modal', function () {
            el.accountForm.elements.title.focus();
        });

        el.accountForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var form = el.accountForm;
            var go = byId('pg_sale_account_go');
            form.querySelectorAll('.is-invalid').forEach(function (node) {
                node.classList.remove('is-invalid');
            });
            go.disabled = true;

            send('quick_account', {
                title: form.elements.title.value,
                is_person: form.querySelector('input[name="is_person"]:checked').value,
                phone: form.elements.phone.value,
                email: form.elements.email.value,
                tax_number: form.elements.tax_number.value
            }, { quiet: true }).then(function (answer) {
                go.disabled = false;
                if (!answer) {
                    return;
                }
                if (answer.ok) {
                    state.picker = false;
                    el.customer.innerHTML = state.customerHtml;
                    bootstrap.Modal.getOrCreateInstance(el.accountModal).hide();
                    toast(answer.message, 'success');
                    el.product.focus();
                    return;
                }
                var errors = answer.field_errors || {};
                var first = null;
                Object.keys(errors).forEach(function (field) {
                    var input = form.elements[field];
                    var feedback = form.querySelector('[data-sale-error="' + field + '"]');
                    if (input) {
                        input.classList.add('is-invalid');
                        first = first || input;
                    }
                    if (feedback) {
                        feedback.textContent = errors[field];
                    }
                });
                if (first) {
                    first.focus();
                } else {
                    toast(answer.message, 'danger');
                }
            });
        });
    }

    /* ── The account drawer (the ERP's own, from get_erp_drawer.php) ──── */

    root.addEventListener('click', function (event) {
        var button = event.target.closest('[data-sale-ledger]');
        var panel = byId('erp_drawer');
        if (!button || !panel || !window.bootstrap) {
            return;
        }
        var title = byId('erp_drawer_title');
        var body = byId('erp_drawer_body');
        var open = byId('erp_drawer_open');
        title.textContent = '';
        open.classList.add('d-none');
        body.innerHTML = '<div class="d-flex justify-content-center py-5"><span class="spinner-border text-secondary" role="status" aria-hidden="true"></span></div>';
        bootstrap.Offcanvas.getOrCreateInstance(panel).show();

        fetch(cfg.drawerUrl + '?type=account&id=' + encodeURIComponent(button.getAttribute('data-sale-ledger')), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (!data || data.error || !data.html) {
                throw new Error('drawer');
            }
            title.textContent = data.title || '';
            open.setAttribute('href', data.url);
            open.classList.remove('d-none');
            body.innerHTML = data.html;
        }).catch(function () {
            body.innerHTML = '<div class="alert alert-warning mb-0"></div>';
            body.firstChild.textContent = t.loadFailed;
        });
    });

    /* ── Payment controls ─────────────────────────────────────────────── */

    if (payBody) {
        payBody.addEventListener('click', function (event) {
            var method = event.target.closest('[data-sale-method]');
            if (method) {
                setMethod(method.getAttribute('data-sale-method'), true);
                return;
            }
            if (event.target.closest('[data-sale-extra-show]')) {
                event.target.closest('[data-sale-extra-show]').hidden = true;
                el.extra.hidden = false;
                el.extra.focus();
                return;
            }
            var chip = event.target.closest('[data-sale-recv]');
            if (chip) {
                var cents = Number(chip.getAttribute('data-sale-recv'));
                el.received.value = amountText(cents).split(thousandsMark).join('');
                refreshChange();
            }
        });
    }

    if (el.extra) {
        el.extra.addEventListener('change', function () {
            if (el.extra.value) {
                setMethod(el.extra.value, true);
            }
        });
    }

    if (el.till) {
        el.till.addEventListener('change', function () {
            state.tillTouched = true;
            refreshSavebar();
        });
    }

    if (el.received) {
        el.received.addEventListener('input', refreshChange);
        el.received.addEventListener('keydown', function (event) {
            if (isEnter(event)) {
                event.preventDefault();
                complete();
            }
        });
    }

    /* ── Completing the sale ──────────────────────────────────────────── */

    function complete() {
        if (state.done || state.completing || ((state.count === 0) && (state.pending === 0))) {
            return;
        }
        state.completing = true;
        refreshButtons();

        send('complete', {
            pay_method: state.method,
            pay_till: el.till ? el.till.value : 0,
            received: el.received ? parseAmount(el.received.value) : 0,
            pay_parts: (state.method === 'split') ? JSON.stringify(splitParts()) : ''
        }).then(function () {
            state.completing = false;
            refreshButtons();
        });
    }

    if (el.complete) {
        el.complete.addEventListener('click', complete);
    }
    if (el.barGo) {
        el.barGo.addEventListener('click', complete);
    }

    el.done.addEventListener('click', function (event) {
        if (event.target.closest('[data-sale-new]')) {
            newSale();
        }
    });

    // Issue a missing invoice: the retry on the done card, and the button on
    // a recent sale that has none.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-sale-invoice]');
        if (!button) {
            return;
        }
        button.disabled = true;
        send('issue_invoice', { order_id: button.getAttribute('data-sale-invoice') }, { toast: true }).then(function (answer) {
            if (answer && answer.ok && el.done.contains(button)) {
                var note = document.createElement('span');
                note.className = 'badge text-bg-success align-self-center';
                note.textContent = answer.message;
                button.replaceWith(note);
            } else {
                button.disabled = false;
            }
        });
    });

    /* ── Toolbar ──────────────────────────────────────────────────────── */

    function setMode(mode) {
        if ((mode === 'belge') && !cfg.canErp) {
            mode = 'kasa';
        }
        root.setAttribute('data-mode', mode);
        root.querySelectorAll('[data-sale-mode]').forEach(function (button) {
            var on = (button.getAttribute('data-sale-mode') === mode);
            button.classList.toggle('active', on);
            button.setAttribute('aria-pressed', String(on));
        });
    }

    root.querySelectorAll('[data-sale-mode]').forEach(function (button) {
        button.addEventListener('click', function () {
            var mode = button.getAttribute('data-sale-mode');
            setMode(mode);
            remember('pg_sale_mode', mode);
        });
    });

    if (el.clearBtn) {
        el.clearBtn.addEventListener('click', function () {
            bootstrap.Modal.getOrCreateInstance(byId('pg_sale_clear')).show();
        });
        byId('pg_sale_clear_go').addEventListener('click', function () {
            bootstrap.Modal.getOrCreateInstance(byId('pg_sale_clear')).hide();
            send('clear_cart', {}, { toast: true });
            el.product.focus();
        });
    }

    document.querySelectorAll('[data-sale-keys]').forEach(function (button) {
        button.addEventListener('click', function () {
            bootstrap.Modal.getOrCreateInstance(byId('pg_sale_keys')).show();
        });
    });

    document.querySelectorAll('[data-sale-sound]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            state.sound = !state.sound;
            remember('pg_sale_sound', state.sound ? '1' : '0');
            el.soundTick.style.visibility = state.sound ? 'visible' : 'hidden';
        });
    });

    if (el.recent && window.bootstrap) {
        el.recentBtn.addEventListener('click', function () {
            bootstrap.Offcanvas.getOrCreateInstance(el.recent).show();
        });
        el.recent.addEventListener('show.bs.offcanvas', function () {
            document.body.classList.add('pg-side-panel-open');
            el.recentBody.innerHTML = '<div class="d-flex justify-content-center py-5"><span class="spinner-border text-secondary" role="status" aria-hidden="true"></span></div>';
            getJson({ request: 'recent' }).then(function (data) {
                el.recentBody.innerHTML = (data && data.html) ? data.html : '';
            }).catch(function () {
                el.recentBody.innerHTML = '<div class="alert alert-warning m-3"></div>';
                el.recentBody.firstChild.textContent = t.loadFailed;
            });
        });
        el.recent.addEventListener('hidden.bs.offcanvas', function () {
            document.body.classList.remove('pg-side-panel-open');
        });
    }

    /* ── Keyboard ─────────────────────────────────────────────────────── */

    document.addEventListener('keydown', function (event) {
        if (document.querySelector('.modal.show')) {
            return;
        }
        if (event.key === 'F2') {
            event.preventDefault();
            el.product.focus();
            el.product.select();
        } else if (event.key === 'F4') {
            event.preventDefault();
            openPicker();
        } else if ((event.key === 'F9') || (isEnter(event) && (event.ctrlKey || event.metaKey))) {
            event.preventDefault();
            complete();
        } else if ((event.key === 'Escape') && state.picker) {
            closePicker();
        }
    });

    // Another tab (or a sale completed elsewhere) may have changed the cart
    // while this one was hidden.
    document.addEventListener('visibilitychange', function () {
        if ((document.visibilityState === 'visible') && (state.pending === 0) && !state.completing && !state.picker) {
            refresh();
        }
    });

    /* ── The barcode scanner ──────────────────────────────────────────────
       A scanner types fast and ends with Enter. With the product box focused
       its own Enter handles the code; anywhere else on the page the detector
       below catches it, so the operator never has to click the box first. */

    /*
     * jQuery Scanner Detection
     * Copyright (c) 2013 Julien Maurel — MIT License
     * https://github.com/julien-maurel/jQuery-Scanner-Detection
     * Version: 1.2.1
     */
    !function(e){e.fn.scannerDetection=function(n){if("string"==typeof n)return this.each(function(){this.scannerDetectionTest(n)}),this;if(!1===n)return this.each(function(){this.scannerDetectionOff()}),this;var t={onComplete:!1,onError:!1,onReceive:!1,onKeyDetect:!1,timeBeforeScanTest:200,avgTimeByChar:30,minLength:2,endChar:[9,13],startChar:[],ignoreIfFocusOn:!1,scanButtonKeyCode:!0,scanButtonLongPressThreshold:3,onScanButtonLongPressed:!1,stopPropagation:!1,preventDefault:!1};return"function"==typeof n&&(n={onComplete:n}),n="object"!=typeof n?e.extend({},t):e.extend({},t,n),this.each(function(){var t=this,o=e(t),r=0,i=0,c="",s=!1,a=!1,f=0,u=function(){r=0,c="",f=0};t.scannerDetectionOff=function(){o.unbind("keydown.scannerDetection"),o.unbind("keypress.scannerDetection")},t.isFocusOnIgnoredElement=function(){if(!n.ignoreIfFocusOn)return!1;if("string"==typeof n.ignoreIfFocusOn)return e(":focus").is(n.ignoreIfFocusOn);if("object"==typeof n.ignoreIfFocusOn&&n.ignoreIfFocusOn.length)for(var t=e(":focus"),o=0;o<n.ignoreIfFocusOn.length;o++)if(t.is(n.ignoreIfFocusOn[o]))return!0;return!1},t.scannerDetectionTest=function(e){return e&&(r=i=0,c=e),f||(f=1),c.length>=n.minLength&&i-r<c.length*n.avgTimeByChar?(n.onScanButtonLongPressed&&f>n.scanButtonLongPressThreshold?n.onScanButtonLongPressed.call(t,c,f):n.onComplete&&n.onComplete.call(t,c,f),o.trigger("scannerDetectionComplete",{string:c}),u(),!0):(n.onError&&n.onError.call(t,c),o.trigger("scannerDetectionError",{string:c}),u(),!1)},o.data("scannerDetection",{options:n}).unbind(".scannerDetection").bind("keydown.scannerDetection",function(e){if(!1!==n.scanButtonKeyCode&&e.which==n.scanButtonKeyCode)f++,e.preventDefault(),e.stopImmediatePropagation();else if(r&&-1!==n.endChar.indexOf(e.which)||!r&&-1!==n.startChar.indexOf(e.which)){var i=jQuery.Event("keypress",e);i.type="keypress.scannerDetection",o.triggerHandler(i),e.preventDefault(),e.stopImmediatePropagation()}n.onKeyDetect&&n.onKeyDetect.call(t,e),o.trigger("scannerDetectionKeyDetect",{evt:e})}).bind("keypress.scannerDetection",function(e){this.isFocusOnIgnoredElement()||(n.stopPropagation&&e.stopImmediatePropagation(),n.preventDefault&&e.preventDefault(),r&&-1!==n.endChar.indexOf(e.which)?(e.preventDefault(),e.stopImmediatePropagation(),s=!0):r||-1===n.startChar.indexOf(e.which)?(void 0!==e.which&&(c+=String.fromCharCode(e.which)),s=!1):(e.preventDefault(),e.stopImmediatePropagation(),s=!1),r||(r=Date.now()),i=Date.now(),a&&clearTimeout(a),s?(t.scannerDetectionTest(),a=!1):a=setTimeout(t.scannerDetectionTest,n.timeBeforeScanTest),n.onReceive&&n.onReceive.call(t,e),o.trigger("scannerDetectionReceive",{evt:e}))})}),this}}(jQuery);

    if (window.jQuery && jQuery.fn.scannerDetection) {
        jQuery(document).scannerDetection({
            timeBeforeScanTest: 200,
            avgTimeByChar: 100,
            ignoreIfFocusOn: 'input, textarea, select, [contenteditable="true"]',
            onComplete: function (code) {
                if (document.querySelector('.modal.show')) {
                    return;
                }
                var dot = el.foot.querySelector('.pg-sale-dot');
                if (dot) {
                    dot.classList.remove('is-pulse');
                    void dot.offsetWidth;
                    dot.classList.add('is-pulse');
                }
                addItem({ barcode: String(code).replace(/\*/g, '-') }, qtyValue());
            }
        });
    }

    /* ── First paint ──────────────────────────────────────────────────── */

    setMode(root.getAttribute('data-mode') || cfg.defaultMode);
    el.soundTick.style.visibility = state.sound ? 'visible' : 'hidden';
    resetSplit();
    setMethod(state.method, false);
    renderFoot();
    refreshButtons();
    refreshChange();
    refreshSavebar();
    el.product.focus();
})();
