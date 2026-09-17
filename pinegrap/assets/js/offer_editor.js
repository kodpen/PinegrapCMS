/*
 * Offer editor (edit_offer.php).
 *
 * One state object holds the offer, its condition rows and its result rows.
 * Every change re-renders the rows, the summary strip and the save button;
 * Save posts the whole state as JSON to api.php (action "offer_editor",
 * type "offer_save") and edit_offer_f.php writes the tables. The condition
 * and result type lists here mirror _pg_offer_condition_types() and
 * _pg_offer_action_types() in that file: a type missing on either side can
 * be neither added nor saved.
 *
 * Amounts travel as integer cents; the inputs show and accept decimals.
 * Dates travel as Y-m-d; the inputs show the site's date format.
 */
(function () {
    'use strict';

    var dataEl = document.getElementById('pg-offer-editor-data');
    var root = document.getElementById('pg-offer-editor');
    if (!dataEl || !root) {
        return;
    }
    var D = JSON.parse(dataEl.textContent);
    var L = D.labels;
    var S = D.state;
    var C = D.context;
    var PRODUCTS = D.products;
    var METHODS = D.shipping_methods;
    var GROUPS = D.product_groups || [];
    var CONDITION_TYPES = D.condition_types;
    var ACTION_TYPES = D.action_types;
    var isNew = (S.offer.id <= 0);
    var templateChosen = false;
    var dirty = false;
    var serverErrors = {};

    // ── helpers ──────────────────────────────────────────────────────────
    function $(sel, ctx) { return (ctx || root).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }
    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fmt(template, vars) {
        return String(template).replace(/\{var(?::(\d+))?\}/g, function (m, n) {
            var i = n ? parseInt(n, 10) - 1 : 0;
            return (vars[i] === undefined || vars[i] === null) ? '' : String(vars[i]);
        });
    }
    // Cents to the site's money format (prepare_amount(): symbol, thousands
    // commas, two decimals).
    function money(cents) {
        var n = (parseInt(cents, 10) || 0) / 100;
        var neg = n < 0; if (neg) { n = -n; }
        var parts = n.toFixed(2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return (neg ? '-' : '') + C.currency + parts.join('.');
    }
    function centsToInput(cents) {
        var n = (parseInt(cents, 10) || 0);
        return n ? (n / 100).toFixed(2).replace(/\.00$/, '') : '';
    }
    // "100", "100.5", "100,50", "1.250,00" → cents. A comma that is followed
    // by exactly two digits at the end is a decimal comma; every other comma
    // is a thousands separator.
    function inputToCents(text) {
        var s = String(text || '').replace(/\s/g, '');
        if (s === '') { return 0; }
        if (/,\d{1,2}$/.test(s) && s.indexOf('.') < 0) {
            s = s.replace(/\./g, '').replace(',', '.');
        } else {
            s = s.replace(/,/g, '');
        }
        var n = parseFloat(s);
        if (isNaN(n)) { return 0; }
        return Math.round(n * 100);
    }
    function pct(text) {
        var n = parseFloat(String(text || '').replace(',', '.'));
        return isNaN(n) ? 0 : Math.round(n);
    }
    function productLabel(id) {
        for (var i = 0; i < PRODUCTS.length; i++) {
            if (PRODUCTS[i].id == id) { return PRODUCTS[i].label; }
        }
        return '';
    }
    // full: the path ("Mağaza › Sandalyeler"), for the picker. Short name
    // everywhere else, or a condition naming five groups fills the row.
    function groupLabel(id, full) {
        for (var i = 0; i < GROUPS.length; i++) {
            if (GROUPS[i].id == id) { return full ? GROUPS[i].label : GROUPS[i].name; }
        }
        return '';
    }
    function methodName(id) {
        for (var i = 0; i < METHODS.length; i++) {
            if (METHODS[i].id == id) { return METHODS[i].name; }
        }
        return '';
    }
    // Y-m-d ↔ the site's display format (d/m/yyyy or m/d/yyyy).
    function isoToDisplay(iso) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
        if (!m) { return ''; }
        var d = String(parseInt(m[3], 10)), mo = String(parseInt(m[2], 10));
        return (C.date_format === 'month_day' ? (mo + '/' + d) : (d + '/' + mo)) + '/' + m[1];
    }
    function displayToIso(text) {
        var p = String(text || '').split(/[\/\-.]/);
        if (p.length !== 3) { return ''; }
        var d, mo, y = p[2];
        if (C.date_format === 'month_day') { mo = p[0]; d = p[1]; } else { d = p[0]; mo = p[1]; }
        if (!/^\d{4}$/.test(y) || !/^\d{1,2}$/.test(d) || !/^\d{1,2}$/.test(mo)) { return ''; }
        return y + '-' + ('0' + mo).slice(-2) + '-' + ('0' + d).slice(-2);
    }
    function icon(name) { return '<i class="bi ' + name + '" aria-hidden="true"></i>'; }
    // Bootstrap's display utilities beat the hidden attribute on anything that
    // also carries d-flex or a grid class, so visibility is toggled with
    // d-none throughout.
    function show(el, on) { if (el) { el.classList.toggle('d-none', !on); } }
    // One row of a section: the type on the left, its fields in grid columns,
    // the remove button pushed right, the error under all of it.
    function rowHtml(kind, index, type, columns, err, removeAction, removeTitle) {
        return '<div class="list-group-item' + (err ? ' bg-danger-subtle' : '') + '" data-row="' + kind + '" data-index="' + index + '">' +
            '<div class="row g-2 align-items-center">' +
            '<div class="col-12 col-md-auto d-flex align-items-center gap-2">' + icon(type.icon) + '<span class="fw-medium">' + esc(type.label) + '</span></div>' +
            columns +
            '<div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-ghost" data-on="' + removeAction + '" data-index="' + index + '" title="' + esc(removeTitle) + '">' + icon('bi-x-lg') + '</button></div>' +
            '<div class="col-12 small text-danger-emphasis' + (err ? '' : ' d-none') + '" data-error>' + esc(err) + '</div>' +
            '</div></div>';
    }
    function tagHtml(label, action, index, id, variant) {
        return '<span class="badge rounded-pill text-bg-' + variant + ' d-inline-flex align-items-center gap-1 fw-normal">' + esc(label) +
            '<a href="#" class="link-light text-decoration-none" data-on="' + action + '" data-index="' + index + '" data-id="' + id + '" title="' + esc(L.remove) + '">&times;</a></span>';
    }

    // ── condition rows ───────────────────────────────────────────────────
    function conditionError(c, index) {
        var key = 'conditions.' + index;
        if (c.type === 'subtotal') {
            if (!(c.amount > 0)) { return L.enterSubtotal; }
            return serverErrors[key + '.amount'] || serverErrors[key] || '';
        }
        if (c.type === 'products') {
            if (!c.product_ids.length) { return L.selectProducts; }
            if (!(c.quantity >= 1)) { return L.enterQuantity; }
            return serverErrors[key + '.product_ids'] || serverErrors[key + '.quantity'] || serverErrors[key] || '';
        }
        if (c.type === 'product group') {
            if (!c.group_ids.length) { return L.selectGroups; }
            if (!(c.quantity >= 1)) { return L.enterQuantity; }
            return serverErrors[key + '.group_ids'] || serverErrors[key + '.quantity'] || serverErrors[key] || '';
        }
        if (c.type === 'cart quantity') {
            if (!(c.quantity >= 1)) { return L.enterQuantity; }
            return serverErrors[key + '.quantity'] || serverErrors[key] || '';
        }
        if (c.type === 'usage limit') {
            if (!(c.total_limit >= 1) && !(c.customer_limit >= 1)) { return L.enterLimit; }
            return serverErrors[key + '.total_limit'] || serverErrors[key + '.customer_limit'] || serverErrors[key] || '';
        }
        if (c.type === 'new customer') {
            if (c.mode !== 'no_orders') {
                if (!(c.days >= 1)) { return L.enterDays; }
                if (c.days > 3650) { return L.maxDays; }
            }
            return serverErrors[key + '.days'] || serverErrors[key] || '';
        }
        return '';
    }
    // The up-sell nudge counts the customer down to a subtotal or a quantity
    // they can still reach; a condition about the customer gives it nothing to
    // count. edit_offer_f.php makes the same distinction on save.
    function cartConditions() {
        return S.conditions.filter(function (c) { return (c.type !== 'new customer') && (c.type !== 'usage limit'); });
    }
    function conditionText(c) {
        if (c.type === 'subtotal') {
            return fmt(L.subtotalText, ['<b>' + esc(money(c.amount)) + '</b>']);
        }
        if (c.type === 'product group') {
            var groups = c.group_ids.map(groupLabel).filter(Boolean);
            return fmt(L.groupsText, ['<b>' + esc(c.quantity || '?') + '</b>', '<b>' + esc(groups.length ? groups.join(' / ') : '?') + '</b>']);
        }
        if (c.type === 'cart quantity') {
            return fmt(L.cartQuantityText, ['<b>' + esc(c.quantity || '?') + '</b>']);
        }
        if (c.type === 'usage limit') {
            var bits = [];
            if (c.total_limit > 0) { bits.push(fmt(L.usageTotalText, ['<b>' + esc(c.total_limit) + '</b>'])); }
            if (c.customer_limit > 0) { bits.push(fmt(L.usageCustomerText, ['<b>' + esc(c.customer_limit) + '</b>'])); }
            return bits.length ? bits.join(' ' + esc(L.and) + ' ') : esc(L.noUsageLimit);
        }
        if (c.type === 'new customer') {
            if (c.mode === 'no_orders') { return esc(L.newCustomerNever); }
            var days = '<b>' + esc(c.days || '?') + '</b>';
            return fmt(c.mode === 'both' ? L.newCustomerBoth : L.newCustomerDays, [days]);
        }
        var names = c.product_ids.map(productLabel).filter(Boolean);
        return fmt(L.productsText, ['<b>' + esc(c.quantity || '?') + '</b>', '<b>' + esc(names.length ? names.join(' / ') : '?') + '</b>']);
    }
    function productPicker(onchange, index, exclude, selectedId, placeholder) {
        var h = '<select class="form-select form-select-sm" data-on="' + esc(onchange) + '" data-index="' + index + '">';
        h += '<option value="">' + esc(placeholder) + '</option>';
        PRODUCTS.forEach(function (p) {
            if (exclude && exclude.indexOf(p.id) >= 0) { return; }
            h += '<option value="' + p.id + '"' + (selectedId == p.id ? ' selected' : '') + '>' + esc(p.label) + (p.code ? ' · ' + esc(p.code) : '') + (p.enabled ? '' : ' [' + esc(L.disabledProduct) + ']') + '</option>';
        });
        return h + '</select>';
    }
    function groupPicker(index, exclude) {
        var h = '<select class="form-select form-select-sm" data-on="cond-group-add" data-index="' + index + '">';
        h += '<option value="">' + esc(GROUPS.length ? L.addGroup : L.noGroups) + '</option>';
        GROUPS.forEach(function (g) {
            if (exclude && exclude.indexOf(g.id) >= 0) { return; }
            h += '<option value="' + g.id + '">' + esc(g.label) + '</option>';
        });
        return h + '</select>';
    }
    function renderConditions() {
        var box = $('#pg-if-conditions');
        var h = '';
        if (!S.conditions.length) {
            h = '<div class="list-group-item text-body-secondary">' + icon('bi-info-circle') + ' ' + esc(L.noConditions) + '</div>';
        }
        S.conditions.forEach(function (c, i) {
            var t = CONDITION_TYPES[c.type] || { label: c.type, icon: 'bi-question' };
            var err = conditionError(c, i);
            var cols = '';
            if (c.type === 'subtotal') {
                cols = '<div class="col-7 col-sm-5 col-md-3 col-xl-2">' +
                    '<div class="input-group input-group-sm"><input type="text" class="form-control text-end" inputmode="decimal" value="' + esc(centsToInput(c.amount)) + '" placeholder="0.00" data-on="cond-amount" data-index="' + i + '"><span class="input-group-text">' + esc(C.currency) + '</span></div>' +
                    '</div>';
            } else if (c.type === 'products') {
                var tags = c.product_ids.map(function (id) {
                    return tagHtml(productLabel(id) || ('#' + id), 'cond-product-remove', i, id, 'primary');
                }).join('');
                cols = (tags ? '<div class="col-12 col-lg-auto d-flex flex-wrap align-items-center gap-1">' + tags + '</div>' : '') +
                    '<div class="col-12 col-sm-7 col-lg-4 col-xxl-3">' + productPicker('cond-product-add', i, c.product_ids, 0, L.addProduct) + '</div>' +
                    '<div class="col-auto text-body-secondary small">' + esc(L.atLeast) + '</div>' +
                    '<div class="col-3 col-sm-2 col-lg-1">' +
                    '<input type="text" class="form-control form-control-sm text-center" inputmode="numeric" value="' + esc(c.quantity || '') + '" data-on="cond-quantity" data-index="' + i + '">' +
                    '</div>' +
                    '<div class="col-auto text-body-secondary small">' + esc(L.pieces) + '</div>';
            } else if (c.type === 'product group') {
                var gtags = c.group_ids.map(function (id) {
                    return tagHtml(groupLabel(id) || ('#' + id), 'cond-group-remove', i, id, 'primary');
                }).join('');
                cols = (gtags ? '<div class="col-12 col-lg-auto d-flex flex-wrap align-items-center gap-1">' + gtags + '</div>' : '') +
                    '<div class="col-12 col-sm-7 col-lg-4 col-xxl-3">' + groupPicker(i, c.group_ids) + '</div>' +
                    '<div class="col-auto text-body-secondary small">' + esc(L.atLeast) + '</div>' +
                    '<div class="col-3 col-sm-2 col-lg-1">' +
                    '<input type="text" class="form-control form-control-sm text-center" inputmode="numeric" value="' + esc(c.quantity || '') + '" data-on="cond-quantity" data-index="' + i + '">' +
                    '</div>' +
                    '<div class="col-auto text-body-secondary small">' + esc(L.pieces) + '</div>';
            } else if (c.type === 'cart quantity') {
                cols = '<div class="col-auto text-body-secondary small">' + esc(L.atLeast) + '</div>' +
                    '<div class="col-3 col-sm-2 col-lg-1">' +
                    '<input type="text" class="form-control form-control-sm text-center" inputmode="numeric" value="' + esc(c.quantity || '') + '" data-on="cond-quantity" data-index="' + i + '">' +
                    '</div>' +
                    '<div class="col-auto text-body-secondary small">' + esc(L.pieces) + '</div>';
            } else if (c.type === 'usage limit') {
                cols = '<div class="col-auto text-body-secondary small">' + esc(L.usageTotalLabel) + '</div>' +
                    '<div class="col-3 col-sm-2 col-lg-1">' +
                    '<input type="text" class="form-control form-control-sm text-center" inputmode="numeric" placeholder="∞" value="' + esc(c.total_limit || '') + '" data-on="cond-total-limit" data-index="' + i + '">' +
                    '</div>' +
                    '<div class="col-auto text-body-secondary small">' + esc(L.usageCustomerLabel) + '</div>' +
                    '<div class="col-3 col-sm-2 col-lg-1">' +
                    '<input type="text" class="form-control form-control-sm text-center" inputmode="numeric" placeholder="∞" value="' + esc(c.customer_limit || '') + '" data-on="cond-customer-limit" data-index="' + i + '">' +
                    '</div>' +
                    '<div class="col-12 col-xl small text-body-secondary">' + esc(L.usageHint) + '</div>';
            } else if (c.type === 'new customer') {
                var modes = L.newCustomerModes || {};
                var ms = '<select class="form-select form-select-sm" data-on="cond-mode" data-index="' + i + '">';
                Object.keys(modes).forEach(function (key) {
                    ms += '<option value="' + esc(key) + '"' + (c.mode === key ? ' selected' : '') + '>' + esc(modes[key]) + '</option>';
                });
                ms += '</select>';
                cols = '<div class="col-12 col-sm-7 col-lg-4 col-xxl-3">' + ms + '</div>' +
                    '<div class="col-auto text-body-secondary small' + (c.mode === 'no_orders' ? ' d-none' : '') + '" data-days-label>' + esc(L.atLeast) + '</div>' +
                    '<div class="col-3 col-sm-2 col-lg-1' + (c.mode === 'no_orders' ? ' d-none' : '') + '" data-days-input>' +
                    '<input type="text" class="form-control form-control-sm text-center" inputmode="numeric" value="' + esc(c.days || '') + '" data-on="cond-days" data-index="' + i + '">' +
                    '</div>' +
                    '<div class="col-auto text-body-secondary small' + (c.mode === 'no_orders' ? ' d-none' : '') + '" data-days-unit>' + esc(L.days) + '</div>';
            }
            h += rowHtml('condition', i, t, cols, err, 'cond-remove', L.removeCondition);
        });
        box.innerHTML = h;
        show($('#pg-if-conditions-all'), S.conditions.length > 1);
        // Add-condition menu: a type already in the offer is shown but cannot
        // be added twice (the schema holds one rule row per offer).
        var menu = $('#pg-if-add-condition-menu');
        var mh = '';
        Object.keys(CONDITION_TYPES).forEach(function (type) {
            var has = S.conditions.some(function (c) { return c.type === type; });
            mh += '<li><a class="dropdown-item d-flex align-items-center gap-2' + (has ? ' disabled' : '') + '" href="#" data-on="cond-add" data-type="' + esc(type) + '">' + icon(CONDITION_TYPES[type].icon) + ' <span>' + esc(CONDITION_TYPES[type].label) + '</span>' + (has ? '<span class="ms-auto small text-body-secondary">' + esc(L.added) + '</span>' : '') + '</a></li>';
        });
        menu.innerHTML = mh;
    }

    // ── result rows ──────────────────────────────────────────────────────
    // A product discount takes a product off the shelf or a whole group off
    // the catalogue; everything else about the row is the same.
    function targetsGroup(a) {
        return (a.type === 'discount product') && (a.target === 'group');
    }
    function targetsCheapest(a) {
        return (a.type === 'discount product') && (a.target === 'cheapest');
    }
    // "Spend 100 get 5%, spend 250 get 10%". Only an order discount charged as
    // a percentage can carry one, and only where the schema holds it.
    function hasTiers(a) {
        return (a.type === 'discount order') && !!(a.tiers && a.tiers.length);
    }
    function tiersAllowed(a) {
        return (a.type === 'discount order') && (a.unit === 'percent') && !!C.offer_conditions;
    }
    function actionError(a, index) {
        var key = 'actions.' + index;
        if (targetsCheapest(a)) {
            // The cart picks the line; there is nothing to choose here.
        } else if (targetsGroup(a)) {
            if (!(a.group_id > 0)) { return L.selectGroup; }
        } else if ((a.type === 'discount product' || a.type === 'add product') && !(a.product_id > 0)) { return L.selectProduct; }
        if (hasTiers(a)) {
            for (var t = 0; t < a.tiers.length; t++) {
                if (!(a.tiers[t].percent > 0) || a.tiers[t].percent > 100) { return L.tierPercent; }
            }
            return serverErrors[key] || '';
        }
        if (a.type !== 'add product' && !(a.value > 0)) { return L.enterValue; }
        if (a.unit === 'percent' && a.value > 100) { return L.percentMax; }
        if (a.type === 'discount shipping' && !a.shipping_method_ids.length) { return L.selectMethod; }
        return serverErrors[key + '.value'] || serverErrors[key + '.product_id'] || serverErrors[key + '.group_id'] || serverErrors[key + '.shipping_method_ids'] || serverErrors[key] || '';
    }
    function valueText(a) {
        return a.unit === 'amount' ? money(a.value) : ('%' + (parseInt(a.value, 10) || 0));
    }
    function actionText(a) {
        var q = Math.max(1, parseInt(a.quantity, 10) || 1);
        switch (a.type) {
            case 'discount order':
                if (hasTiers(a)) {
                    return fmt(L.tiersText, ['<b>' + a.tiers.map(function (t) {
                        return esc(money(t.min)) + '+ → %' + esc(t.percent);
                    }).join('</b> · <b>') + '</b>']);
                }
                return fmt(L.orderText, ['<b>' + esc(valueText(a)) + '</b>']);
            case 'discount product':
                if (targetsCheapest(a)) {
                    if (a.unit === 'percent' && a.value >= 100) { return esc(L.cheapestFree); }
                    return fmt(L.cheapestText, ['<b>' + esc(valueText(a)) + '</b>']);
                }
                if (targetsGroup(a)) {
                    return fmt(L.groupProductText, ['<b>' + esc(valueText(a)) + '</b>', '<b>' + esc(groupLabel(a.group_id) || '?') + '</b>']);
                }
                return fmt(L.productText, ['<b>' + esc(valueText(a)) + '</b>', '<b>' + esc(productLabel(a.product_id) || '?') + '</b>']);
            case 'add product':
                if (a.unit === 'percent' && a.value >= 100) { return fmt(L.giftFree, ['<b>' + q + '</b>', '<b>' + esc(productLabel(a.product_id) || '?') + '</b>']); }
                if (!(a.value > 0)) { return fmt(L.giftFull, ['<b>' + q + '</b>', '<b>' + esc(productLabel(a.product_id) || '?') + '</b>']); }
                return fmt(L.giftDiscount, ['<b>' + q + '</b>', '<b>' + esc(productLabel(a.product_id) || '?') + '</b>', '<b>' + esc(valueText(a)) + '</b>']);
            case 'discount shipping':
                var names = a.shipping_method_ids.map(methodName).filter(Boolean);
                return fmt(L.shippingText, ['<b>' + (parseInt(a.value, 10) || 0) + '</b>', esc(names.length ? names.join(', ') : '?')]);
        }
        return '';
    }
    function unitToggle(a, i) {
        var units = (ACTION_TYPES[a.type] && ACTION_TYPES[a.type].units) || ['percent'];
        if (units.length < 2) {
            return '<span class="input-group-text">%</span>';
        }
        return '<button type="button" class="btn ' + (a.unit === 'percent' ? 'btn-secondary' : 'btn-outline-secondary') + '" data-on="act-unit" data-index="' + i + '" data-unit="percent">%</button>' +
            '<button type="button" class="btn ' + (a.unit === 'amount' ? 'btn-secondary' : 'btn-outline-secondary') + '" data-on="act-unit" data-index="' + i + '" data-unit="amount">' + esc(C.currency) + '</button>';
    }
    function valueInput(a, i) {
        var shown = a.unit === 'amount' ? centsToInput(a.value) : (a.value || '');
        return '<div class="col-7 col-sm-5 col-md-4 col-xl-3 col-xxl-2">' +
            '<div class="input-group input-group-sm"><input type="text" class="form-control text-end" inputmode="decimal" value="' + esc(shown) + '" placeholder="0" data-on="act-value" data-index="' + i + '">' + unitToggle(a, i) + '</div>' +
            '</div>';
    }
    // Shown only where the schema can store a group (C.group_discount); on a
    // site that has not run the upgrade the result behaves as it always did.
    function targetToggle(a, i) {
        if (!C.group_discount && !C.cheapest_target) { return ''; }
        var on = targetsGroup(a);
        return '<div class="col-auto"><div class="btn-group btn-group-sm" role="group">' +
            '<button type="button" class="btn ' + (on ? 'btn-outline-secondary' : 'btn-secondary') + '" data-on="act-target" data-index="' + i + '" data-target="product">' + esc(L.targetProduct) + '</button>' +
            '<button type="button" class="btn ' + (on ? 'btn-secondary' : 'btn-outline-secondary') + '" data-on="act-target" data-index="' + i + '" data-target="group">' + esc(L.targetGroup) + '</button>' +
            (C.cheapest_target
                ? '<button type="button" class="btn ' + (targetsCheapest(a) ? 'btn-secondary' : 'btn-outline-secondary') + '" data-on="act-target" data-index="' + i + '" data-target="cheapest">' + esc(L.targetCheapest) + '</button>'
                : '') +
            '</div></div>';
    }
    function actionGroupPicker(a, i) {
        var h = '<div class="col-12 col-sm-7 col-lg-4 col-xxl-3"><select class="form-select form-select-sm" data-on="act-group" data-index="' + i + '">';
        h += '<option value="">' + esc(GROUPS.length ? L.selectGroupPlaceholder : L.noGroups) + '</option>';
        GROUPS.forEach(function (g) {
            h += '<option value="' + g.id + '"' + (a.group_id == g.id ? ' selected' : '') + '>' + esc(g.label) + '</option>';
        });
        return h + '</select></div>';
    }
    function tierToggle(a, i) {
        if (!tiersAllowed(a) && !hasTiers(a)) { return ''; }
        var on = hasTiers(a);
        return '<div class="col-auto"><div class="btn-group btn-group-sm" role="group">' +
            '<button type="button" class="btn ' + (on ? 'btn-outline-secondary' : 'btn-secondary') + '" data-on="act-tier-mode" data-index="' + i + '" data-mode="flat">' + esc(L.flatRate) + '</button>' +
            '<button type="button" class="btn ' + (on ? 'btn-secondary' : 'btn-outline-secondary') + '" data-on="act-tier-mode" data-index="' + i + '" data-mode="tiers">' + esc(L.tiered) + '</button>' +
            '</div></div>';
    }
    function tierRows(a, i) {
        var h = '<div class="col-12"><div class="row g-2">';
        a.tiers.forEach(function (t, k) {
            h += '<div class="col-12">' +
                '<div class="row g-2 align-items-center">' +
                '<div class="col-auto text-body-secondary small">' + esc(L.tierFrom) + '</div>' +
                '<div class="col-6 col-sm-4 col-md-3 col-xl-2">' +
                '<div class="input-group input-group-sm"><input type="text" class="form-control text-end" inputmode="decimal" value="' + esc(centsToInput(t.min)) + '" placeholder="0.00" data-on="tier-min" data-index="' + i + '" data-tier="' + k + '"><span class="input-group-text">' + esc(C.currency) + '</span></div>' +
                '</div>' +
                '<div class="col-auto text-body-secondary small">→</div>' +
                '<div class="col-4 col-sm-3 col-md-2 col-xl-1">' +
                '<div class="input-group input-group-sm"><input type="text" class="form-control text-end" inputmode="numeric" value="' + esc(t.percent || '') + '" data-on="tier-percent" data-index="' + i + '" data-tier="' + k + '"><span class="input-group-text">%</span></div>' +
                '</div>' +
                '<div class="col-auto"><button type="button" class="btn btn-sm btn-ghost" data-on="tier-remove" data-index="' + i + '" data-tier="' + k + '" title="' + esc(L.remove) + '">' + icon('bi-x-lg') + '</button></div>' +
                '</div></div>';
        });
        h += '<div class="col-12"><button type="button" class="btn btn-sm btn-outline-secondary" data-on="tier-add" data-index="' + i + '">' + icon('bi-plus-lg') + ' ' + esc(L.addTier) + '</button>' +
            '<span class="ms-2 small text-body-secondary">' + esc(L.tierHint) + '</span></div>';
        return h + '</div></div>';
    }
    function renderActions() {
        var box = $('#pg-if-actions');
        var h = '';
        if (!S.actions.length) {
            h = '<div class="list-group-item text-warning-emphasis">' + icon('bi-exclamation-triangle') + ' ' + esc(L.noActions) + '</div>';
        }
        S.actions.forEach(function (a, i) {
            var t = ACTION_TYPES[a.type] || { label: a.type, icon: 'bi-question' };
            var err = actionError(a, i);
            var picker = '<div class="col-12 col-sm-7 col-lg-4 col-xxl-3">' + productPicker('act-product', i, null, a.product_id, L.selectProductPlaceholder) + '</div>';
            var cols = '';
            if (a.type === 'discount order') {
                cols = tierToggle(a, i) + (hasTiers(a) ? tierRows(a, i) : valueInput(a, i));
            } else if (a.type === 'discount product') {
                cols = targetToggle(a, i)
                    + (targetsCheapest(a) ? '' : (targetsGroup(a) ? actionGroupPicker(a, i) : picker))
                    + valueInput(a, i)
                    + (targetsCheapest(a) ? '<div class="col-12 col-xl small text-body-secondary">' + esc(L.cheapestHint) + '</div>' : '');
            } else if (a.type === 'add product') {
                cols = picker +
                    '<div class="col-auto text-body-secondary small">' + esc(L.quantity) + '</div>' +
                    '<div class="col-3 col-sm-2 col-lg-1">' +
                    '<input type="text" class="form-control form-control-sm text-center" inputmode="numeric" value="' + esc(a.quantity || 1) + '" data-on="act-quantity" data-index="' + i + '">' +
                    '</div>' +
                    '<div class="col-auto text-body-secondary small">' + esc(L.discount) + '</div>' + valueInput(a, i) +
                    '<div class="col-auto text-body-secondary small">' + esc(L.giftHint) + '</div>';
            } else if (a.type === 'discount shipping') {
                var tags = a.shipping_method_ids.map(function (id) {
                    return tagHtml(methodName(id) || ('#' + id), 'act-method-remove', i, id, 'secondary');
                }).join('');
                var sel = '<select class="form-select form-select-sm" data-on="act-method-add" data-index="' + i + '"><option value="">' + esc(L.addMethod) + '</option>';
                METHODS.forEach(function (m) {
                    if (a.shipping_method_ids.indexOf(m.id) < 0) { sel += '<option value="' + m.id + '">' + esc(m.name) + '</option>'; }
                });
                sel += '</select>';
                cols = valueInput(a, i) +
                    '<div class="col-auto text-body-secondary small">' + esc(L.methods) + '</div>' +
                    (tags ? '<div class="col-12 col-lg-auto d-flex flex-wrap align-items-center gap-1">' + tags + '</div>' : '') +
                    (a.shipping_method_ids.length < METHODS.length ? '<div class="col-12 col-sm-7 col-lg-3 col-xxl-2">' + sel + '</div>' : '');
            }
            h += rowHtml('action', i, t, cols, err, 'act-remove', L.removeResult);
        });
        box.innerHTML = h;
        var menu = $('#pg-if-add-action-menu');
        var mh = '';
        Object.keys(ACTION_TYPES).forEach(function (type) {
            mh += '<li><a class="dropdown-item d-flex align-items-center gap-2" href="#" data-on="act-add" data-type="' + esc(type) + '">' + icon(ACTION_TYPES[type].icon) + ' <span>' + esc(ACTION_TYPES[type].label) + '</span></a></li>';
        });
        menu.innerHTML = mh;
    }

    // ── summary strip, options, save button ──────────────────────────────
    function hardErrorCount() {
        var n = 0;
        if (!S.offer.code.trim()) { n++; }
        S.conditions.forEach(function (c, i) { if (conditionError(c, i)) { n++; } });
        S.actions.forEach(function (a, i) { if (actionError(a, i)) { n++; } });
        if (S.offer.period === 'range' && (!S.offer.start_date || !S.offer.end_date)) { n++; }
        if (S.offer.period === 'range' && S.offer.start_date && S.offer.end_date && S.offer.start_date > S.offer.end_date) { n++; }
        return n;
    }
    function periodText() {
        if (S.offer.period === 'open') { return L.openEnded; }
        return (isoToDisplay(S.offer.start_date) || '?') + ' – ' + (isoToDisplay(S.offer.end_date) || '?');
    }
    function renderSummary() {
        var trigger = S.offer.require_code
            ? fmt(L.whenCode, ['<span class="text-primary">' + esc(S.offer.code || '?') + '</span>'])
            : '<span class="text-primary">' + esc(L.automatic) + '</span>';
        var conds = S.conditions.length ? S.conditions.map(conditionText).join(' ' + esc(L.and) + ' ') : esc(L.everyOrder);
        var acts = S.actions.length
            ? '<span class="text-success">' + S.actions.map(actionText).join('</span> + <span class="text-success">') + '</span>'
            : '<span class="text-danger">' + esc(L.nothingHappens) + '</span>';
        var meta = [periodText(), S.offer.enabled ? L.enabledWord : L.disabledWord];
        if (S.offer.upsell.enabled && cartConditions().length) { meta.push(L.upsellOn); }
        $('#pg-if-sentence').innerHTML = trigger + ' · ' + conds + ' → ' + acts + ' <span class="text-body-secondary">· ' + esc(meta.join(' · ')) + '</span>';

        var n = hardErrorCount();
        var btn = $('#pg-if-save');
        btn.disabled = n > 0;
        var verb = isNew ? L.create : L.save;
        var label = n ? (verb + ' · ' + fmt(L.incomplete, [n])) : verb;
        var slot = btn.querySelector('.btn-text');
        if (slot) { slot.textContent = label; } else { btn.textContent = label; }
        btn.classList.toggle('btn-warning', n > 0);
        btn.classList.toggle('btn-success', n === 0);

        var status = $('#pg-if-status');
        var text, cls;
        if (!S.offer.enabled) {
            text = L.statusDisabled; cls = 'text-bg-secondary';
        } else if (S.offer.period === 'range' && S.offer.start_date > C.today) {
            text = L.statusScheduled; cls = 'text-bg-info';
        } else if (S.offer.period === 'range' && S.offer.end_date < C.today) {
            text = L.statusExpired; cls = 'text-bg-warning';
        } else {
            text = L.statusActive; cls = 'text-bg-success';
        }
        status.textContent = text + (dirty ? ' · ' + L.unsavedChanges : '');
        status.className = 'badge rounded-pill ' + cls;

        var os = [periodText(), (S.offer.upsell.enabled && cartConditions().length) ? L.upsellOn : L.upsellOff];
        if (C.same_code_count > 0 && S.offer.only_apply_best_offer) { os.push(L.bestOffer); }
        $('#pg-if-options-summary').textContent = os.join(' · ');
    }
    function renderHeader() {
        $('#pg-if-code').value = S.offer.code;
        $('#pg-if-description').value = S.offer.description;
        $('#pg-if-enabled').checked = !!S.offer.enabled;
        $('#pg-if-require-code').checked = !!S.offer.require_code;
        $('#pg-if-require-code-hint').textContent = S.offer.require_code ? L.requireCodeOn : L.requireCodeOff;
        show($('#pg-if-keycodes'), S.offer.require_code && !isNew);
        var generate = $('[data-on="keycodes-generate"]');
        if (generate) { generate.disabled = dirty; }
        show($('#pg-if-orders'), !isNew && C.order_count > 0);
        $('#pg-if-orders-count').textContent = fmt(L.orderCount, [C.order_count]);
        $('#pg-if-keycodes-count').textContent = fmt(L.keyCodes, [C.key_code_count]);
        show($('#pg-if-keycodes-delete-wrap'), !isNew && C.key_code_count > 0);
        show($('#pg-if-quick'), isNew && !templateChosen && !S.conditions.length && !S.actions.length);
        $$('[data-saved-only]').forEach(function (el) { show(el, !isNew); });
        renderSteps();
    }
    // The step numbers carry the state of their own section: green once the
    // section is answered, red while the offer cannot do anything at checkout.
    function renderSteps() {
        step('#pg-if-step-offer', S.offer.code.trim() !== '', false);
        step('#pg-if-step-conditions', S.conditions.length > 0 && !S.conditions.some(function (c, i) { return conditionError(c, i); }), false);
        step('#pg-if-step-actions', S.actions.length > 0 && !S.actions.some(function (a, i) { return actionError(a, i); }), !S.actions.length);
    }
    function step(sel, done, todo) {
        var el = $(sel);
        if (!el) { return; }
        el.classList.toggle('pg-step-done', !!done && !todo);
        el.classList.toggle('pg-step-todo', !!todo);
    }
    function renderOptions() {
        $('#pg-if-period-open').classList.toggle('active', S.offer.period === 'open');
        $('#pg-if-period-range').classList.toggle('active', S.offer.period === 'range');
        show($('#pg-if-dates'), S.offer.period === 'range');
        var startEl = $('#pg-if-start'), endEl = $('#pg-if-end');
        if (document.activeElement !== startEl) { startEl.value = isoToDisplay(S.offer.start_date); }
        if (document.activeElement !== endEl) { endEl.value = isoToDisplay(S.offer.end_date); }
        var dateErr = '';
        if (S.offer.period === 'range') {
            if (!S.offer.start_date || !S.offer.end_date) { dateErr = L.enterDates; }
            else if (S.offer.start_date > S.offer.end_date) { dateErr = L.dateOrder; }
        }
        var dateErrEl = $('#pg-if-dates-error');
        dateErrEl.textContent = dateErr || (serverErrors['offer.dates'] || '');
        show(dateErrEl, dateErrEl.textContent !== '');

        show($('#pg-if-upsell-row'), cartConditions().length > 0);
        $('#pg-if-upsell').checked = !!S.offer.upsell.enabled;
        show($('#pg-if-upsell-body'), S.offer.upsell.enabled && cartConditions().length > 0);
        if (document.activeElement !== $('#pg-if-upsell-message')) { $('#pg-if-upsell-message').value = S.offer.upsell.message; }
        if (document.activeElement !== $('#pg-if-upsell-subtotal')) { $('#pg-if-upsell-subtotal').value = centsToInput(S.offer.upsell.trigger_subtotal); }
        if (document.activeElement !== $('#pg-if-upsell-quantity')) { $('#pg-if-upsell-quantity').value = S.offer.upsell.trigger_quantity || ''; }
        if (document.activeElement !== $('#pg-if-upsell-button')) { $('#pg-if-upsell-button').value = S.offer.upsell.button_label; }
        $('#pg-if-upsell-page').value = String(S.offer.upsell.page_id || '');
        show($('#pg-if-upsell-quantity-wrap'), S.conditions.some(function (c) { return c.type === 'products' || c.type === 'product group'; }));

        show($('#pg-if-best-row'), C.same_code_count > 0);
        $('#pg-if-best-count').textContent = String(C.same_code_count);
        $('#pg-if-best').checked = !!S.offer.only_apply_best_offer;

        show($('#pg-if-scope-row'), C.multi_recipient);
        $('#pg-if-scope-order').classList.toggle('active', S.offer.scope !== 'recipient');
        $('#pg-if-scope-recipient').classList.toggle('active', S.offer.scope === 'recipient');
        show($('#pg-if-multiple-wrap'), S.offer.scope === 'recipient');
        $('#pg-if-multiple').checked = !!S.offer.multiple_recipients;
    }
    function enhance() {
        // Select2 for the product pickers when the backend has it loaded; the
        // grid column it sits in decides the width.
        if (window.jQuery && jQuery.fn.select2) {
            $$('.list-group select.form-select').forEach(function (el) {
                if (el.options.length > 12) {
                    jQuery(el).select2({ theme: 'bootstrap-5', width: '100%' });
                }
            });
        }
    }
    function render() {
        renderHeader();
        renderConditions();
        renderActions();
        renderOptions();
        renderSummary();
        enhance();
    }
    function softRow(sel, err) {
        var row = $(sel);
        if (!row) { return; }
        row.classList.toggle('bg-danger-subtle', !!err);
        var em = row.querySelector('[data-error]');
        em.textContent = err;
        show(em, !!err);
    }
    // A keystroke inside a row updates the state and the texts that depend
    // on it without rebuilding the row, so the field keeps its focus.
    function soft() {
        S.conditions.forEach(function (c, i) {
            softRow('[data-row="condition"][data-index="' + i + '"]', conditionError(c, i));
        });
        S.actions.forEach(function (a, i) {
            softRow('[data-row="action"][data-index="' + i + '"]', actionError(a, i));
        });
        renderSummary();
        renderSteps();
    }
    function touch() { dirty = true; }

    // ── events ───────────────────────────────────────────────────────────
    root.addEventListener('click', function (e) {
        var el = e.target.closest('[data-on]');
        if (!el) { return; }
        var on = el.getAttribute('data-on');
        var i = parseInt(el.getAttribute('data-index'), 10);
        if (el.tagName === 'A' || el.tagName === 'BUTTON') { e.preventDefault(); }
        if (el.classList.contains('disabled')) { return; }
        switch (on) {
            case 'cond-add':
                addCondition(el.getAttribute('data-type')); break;
            case 'cond-remove':
                S.conditions.splice(i, 1); touch(); render(); break;
            case 'cond-group-remove':
                S.conditions[i].group_ids = S.conditions[i].group_ids.filter(function (id) { return id != el.getAttribute('data-id'); });
                touch(); render(); break;
            case 'cond-product-remove':
                S.conditions[i].product_ids = S.conditions[i].product_ids.filter(function (id) { return id != el.getAttribute('data-id'); });
                touch(); render(); break;
            case 'act-add':
                addAction(el.getAttribute('data-type')); break;
            case 'act-remove':
                S.actions.splice(i, 1); touch(); render(); break;
            case 'act-target':
                var target = el.getAttribute('data-target');
                if (S.actions[i].target !== target) {
                    S.actions[i].target = target;
                    S.actions[i].product_id = 0;
                    S.actions[i].group_id = 0;
                    touch(); render();
                }
                break;
            case 'act-tier-mode':
                var mode = el.getAttribute('data-mode');
                if (mode === 'tiers' && !hasTiers(S.actions[i])) {
                    S.actions[i].tiers = [{ min: 0, percent: S.actions[i].value > 0 ? S.actions[i].value : 5 }];
                    touch(); render();
                } else if (mode === 'flat' && hasTiers(S.actions[i])) {
                    S.actions[i].value = S.actions[i].tiers[0].percent || 0;
                    S.actions[i].tiers = [];
                    touch(); render();
                }
                break;
            case 'tier-add':
                var last = S.actions[i].tiers[S.actions[i].tiers.length - 1];
                S.actions[i].tiers.push({ min: last ? last.min + 10000 : 0, percent: last ? Math.min(100, last.percent + 5) : 5 });
                touch(); render(); break;
            case 'tier-remove':
                S.actions[i].tiers.splice(parseInt(el.getAttribute('data-tier'), 10), 1);
                if (!S.actions[i].tiers.length) { S.actions[i].value = 0; }
                touch(); render(); break;
            case 'act-unit':
                if (S.actions[i].unit !== el.getAttribute('data-unit')) {
                    S.actions[i].unit = el.getAttribute('data-unit');
                    S.actions[i].value = 0;
                    touch(); render();
                }
                break;
            case 'act-method-remove':
                S.actions[i].shipping_method_ids = S.actions[i].shipping_method_ids.filter(function (id) { return id != el.getAttribute('data-id'); });
                touch(); render(); break;
            case 'period':
                S.offer.period = el.getAttribute('data-period'); touch(); render(); break;
            case 'scope':
                S.offer.scope = el.getAttribute('data-scope'); touch(); render(); break;
            case 'quick':
                quickStart(el.getAttribute('data-template')); break;
            case 'save':
                save(); break;
            case 'delete':
                remove(); break;
            case 'duplicate':
                if (!isNew) { window.location.href = C.edit_url + '?duplicate=' + S.offer.id; }
                break;
            case 'keycodes-generate':
                generateKeyCodes(el); break;
            case 'keycodes-delete':
                deleteKeyCodes(el); break;
        }
    });
    // Select2 and the datepicker announce their changes through jQuery, and a
    // jQuery-triggered event never reaches a native listener; so when jQuery
    // is on the page the change handler is bound through it (it sees native
    // events as well), and natively otherwise.
    function onChange(el) {
        var on = el.getAttribute('data-on');
        var i = parseInt(el.getAttribute('data-index'), 10);
        switch (on) {
            case 'cond-product-add':
                if (el.value) { S.conditions[i].product_ids.push(parseInt(el.value, 10)); touch(); render(); }
                break;
            case 'cond-group-add':
                if (el.value) { S.conditions[i].group_ids.push(parseInt(el.value, 10)); touch(); render(); }
                break;
            case 'cond-mode':
                S.conditions[i].mode = el.value;
                if (!(S.conditions[i].days >= 1)) { S.conditions[i].days = 30; }
                touch(); render(); break;
            case 'act-product':
                S.actions[i].product_id = parseInt(el.value, 10) || 0; touch(); render(); break;
            case 'act-group':
                S.actions[i].group_id = parseInt(el.value, 10) || 0; touch(); render(); break;
            case 'act-method-add':
                if (el.value) { S.actions[i].shipping_method_ids.push(parseInt(el.value, 10)); touch(); render(); }
                break;
            case 'enabled':
                S.offer.enabled = el.checked ? 1 : 0; touch(); renderSummary(); break;
            case 'require-code':
                S.offer.require_code = el.checked ? 1 : 0; touch(); renderHeader(); renderSummary(); break;
            case 'upsell':
                S.offer.upsell.enabled = el.checked ? 1 : 0; touch(); renderOptions(); renderSummary(); break;
            case 'upsell-page':
                S.offer.upsell.page_id = parseInt(el.value, 10) || 0; touch(); break;
            case 'best':
                S.offer.only_apply_best_offer = el.checked ? 1 : 0; touch(); renderSummary(); break;
            case 'multiple':
                S.offer.multiple_recipients = el.checked ? 1 : 0; touch(); break;
            case 'start':
                S.offer.start_date = displayToIso(el.value); touch(); renderOptions(); renderSummary(); break;
            case 'end':
                S.offer.end_date = displayToIso(el.value); touch(); renderOptions(); renderSummary(); break;
        }
    }
    if (window.jQuery) {
        jQuery(root).on('change', '[data-on]', function () { onChange(this); });
    } else {
        root.addEventListener('change', function (e) {
            var el = e.target.closest('[data-on]');
            if (el) { onChange(el); }
        });
    }
    root.addEventListener('input', function (e) {
        var el = e.target.closest('[data-on]');
        if (!el) { return; }
        var on = el.getAttribute('data-on');
        var i = parseInt(el.getAttribute('data-index'), 10);
        switch (on) {
            case 'code':
                S.offer.code = el.value; touch();
                delete serverErrors['offer.code'];
                show($('#pg-if-code-error'), false);
                renderSummary(); break;
            case 'description':
                S.offer.description = el.value; touch(); break;
            case 'cond-amount':
                S.conditions[i].amount = inputToCents(el.value); touch(); soft(); break;
            case 'cond-quantity':
                S.conditions[i].quantity = parseInt(el.value, 10) || 0; touch(); soft(); break;
            case 'cond-days':
                S.conditions[i].days = parseInt(el.value, 10) || 0; touch(); soft(); break;
            case 'cond-total-limit':
                S.conditions[i].total_limit = parseInt(el.value, 10) || 0; touch(); soft(); break;
            case 'cond-customer-limit':
                S.conditions[i].customer_limit = parseInt(el.value, 10) || 0; touch(); soft(); break;
            case 'tier-min':
                S.actions[i].tiers[parseInt(el.getAttribute('data-tier'), 10)].min = inputToCents(el.value); touch(); soft(); break;
            case 'tier-percent':
                S.actions[i].tiers[parseInt(el.getAttribute('data-tier'), 10)].percent = pct(el.value); touch(); soft(); break;
            case 'act-value':
                S.actions[i].value = S.actions[i].unit === 'amount' ? inputToCents(el.value) : pct(el.value); touch(); soft(); break;
            case 'act-quantity':
                S.actions[i].quantity = parseInt(el.value, 10) || 0; touch(); soft(); break;
            case 'upsell-message':
                S.offer.upsell.message = el.value; touch(); break;
            case 'upsell-subtotal':
                S.offer.upsell.trigger_subtotal = inputToCents(el.value); touch(); break;
            case 'upsell-quantity':
                S.offer.upsell.trigger_quantity = parseInt(el.value, 10) || 0; touch(); break;
            case 'upsell-button':
                S.offer.upsell.button_label = el.value; touch(); break;
            case 'start':
                S.offer.start_date = displayToIso(el.value); touch(); renderSummary(); break;
            case 'end':
                S.offer.end_date = displayToIso(el.value); touch(); renderSummary(); break;
        }
    });
    // The jQuery datepicker writes the field without an input event.
    if (window.jQuery && jQuery.fn.datepicker && window.datetimepicker_options) {
        jQuery('#pg-if-start, #pg-if-end').datepicker(jQuery.extend({}, window.datetimepicker_options, {
            onSelect: function (text, inst) {
                jQuery(inst.input).trigger('change');
            }
        }));
    }
    window.addEventListener('beforeunload', function (e) {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    function addCondition(type) {
        if (!CONDITION_TYPES[type] || S.conditions.some(function (c) { return c.type === type; })) { return; }
        if (type === 'subtotal') { S.conditions.push({ type: 'subtotal', amount: 0 }); }
        if (type === 'products') { S.conditions.push({ type: 'products', product_ids: [], quantity: 1 }); }
        if (type === 'product group') { S.conditions.push({ type: 'product group', group_ids: [], quantity: 1 }); }
        if (type === 'cart quantity') { S.conditions.push({ type: 'cart quantity', quantity: 2 }); }
        // One per customer is what this condition is nearly always for.
        if (type === 'usage limit') { S.conditions.push({ type: 'usage limit', total_limit: 0, customer_limit: 1 }); }
        if (type === 'new customer') { S.conditions.push({ type: 'new customer', mode: 'no_orders', days: 30 }); }
        touch(); render();
    }
    function addAction(type) {
        if (!ACTION_TYPES[type]) { return; }
        var a = newAction(type, {});
        if (type === 'add product') { a.quantity = 1; a.value = 100; }
        if (type === 'discount shipping') { a.value = 100; a.shipping_method_ids = allMethods(); }
        S.actions.push(a);
        touch(); render();
    }
    // The templates on an empty offer: they fill rows, nothing more.
    // One place that knows the shape of an action row, so a template says what
    // it means instead of repeating ten defaults.
    function newAction(type, props) {
        var a = { id: 0, type: type, value: 0, unit: 'percent', target: 'product',
                  product_id: 0, group_id: 0, quantity: 0, tiers: [], shipping_method_ids: [] };
        for (var k in props) { if (Object.prototype.hasOwnProperty.call(props, k)) { a[k] = props[k]; } }
        return a;
    }
    function allMethods() {
        return METHODS.map(function (m) { return m.id; });
    }
    function quickStart(key) {
        var firstProduct = PRODUCTS.length ? PRODUCTS[0].id : 0;
        // The deepest group is the most likely campaign target: the top of the
        // tree is usually "All product groups", which discounts the catalogue.
        var firstGroup = 0;
        for (var gi = 0; gi < GROUPS.length; gi++) {
            if (GROUPS[gi].label.indexOf('\u203a') >= 0) { firstGroup = GROUPS[gi].id; break; }
        }
        if (!firstGroup && GROUPS.length) { firstGroup = GROUPS[0].id; }
        templateChosen = true;
        // Picking a second template must not leave the first one's trigger
        // behind: only the two code templates ask the customer to type
        // something, so the switch is set from the template rather than left
        // as it was found.
        S.offer.require_code = (key === 'code' || key === 'oncecode') ? 1 : 0;
        if (key === 'blank') {
            renderHeader();
            var code = $('#pg-if-code');
            if (code) { code.focus(); }
            return;
        }
        // ── the plain ones ───────────────────────────────────────────────
        if (key === 'code') {
            S.offer.require_code = 1;
            S.actions = [newAction('discount order', { value: 10 })];
        } else if (key === 'basket') {
            S.conditions = [{ type: 'subtotal', amount: 50000 }];
            S.actions = [newAction('discount order', { value: 10 })];
        } else if (key === 'shipping') {
            S.conditions = [{ type: 'subtotal', amount: 25000 }];
            S.actions = [newAction('discount shipping', { value: 100, shipping_method_ids: allMethods() })];
        } else if (key === 'gift') {
            S.conditions = [{ type: 'products', product_ids: firstProduct ? [firstProduct] : [], quantity: 2 }];
            S.actions = [newAction('add product', { value: 100, quantity: 1 })];
        } else if (key === 'product') {
            S.actions = [newAction('discount product', { value: 10 })];
        } else if (key === 'group') {
            S.actions = [newAction('discount product', { value: 15, target: 'group', group_id: firstGroup })];

        // ── buy-and-get ──────────────────────────────────────────────────
        } else if (key === 'cheapest') {
            S.conditions = [{ type: 'cart quantity', quantity: 2 }];
            S.actions = [newAction('discount product', { value: 100, target: 'cheapest' })];
        } else if (key === 'groupcheapest') {
            S.conditions = [{ type: 'product group', group_ids: firstGroup ? [firstGroup] : [], quantity: 3 }];
            S.actions = [newAction('discount product', { value: 100, target: 'cheapest' })];
        } else if (key === 'bigcart') {
            S.conditions = [{ type: 'cart quantity', quantity: 3 }];
            S.actions = [newAction('discount shipping', { value: 100, shipping_method_ids: allMethods() })];
        } else if (key === 'tiered') {
            S.actions = [newAction('discount order', {
                value: 5,
                tiers: [{ min: 10000, percent: 5 }, { min: 25000, percent: 10 }, { min: 50000, percent: 20 }]
            })];

        // ── who, and how often ───────────────────────────────────────────
        } else if (key === 'welcome') {
            // A welcome discount that a customer can take twice is not a
            // welcome discount, so the limit comes with it.
            S.conditions = [
                { type: 'new customer', mode: 'no_orders', days: 30 },
                { type: 'usage limit', total_limit: 0, customer_limit: 1 }];
            S.actions = [newAction('discount order', { value: 15 })];
        } else if (key === 'oncecode') {
            S.offer.require_code = 1;
            S.conditions = [{ type: 'usage limit', total_limit: 0, customer_limit: 1 }];
            S.actions = [newAction('discount order', { value: 15 })];
        } else if (key === 'firstorders') {
            S.conditions = [{ type: 'usage limit', total_limit: 100, customer_limit: 1 }];
            S.actions = [newAction('discount order', { value: 20 })];
        } else if (key === 'groupbuy') {
            S.conditions = [{ type: 'product group', group_ids: firstGroup ? [firstGroup] : [], quantity: 1 }];
            S.actions = [newAction('discount order', { value: 10 })];
        }
        touch(); render();
    }

    // Codes the customer types instead of the offer code. The offer does not
    // need them; this is one button and a count, and the key code screen keeps
    // the rest.
    function generateKeyCodes(button) {
        if (isNew || dirty) { return; }
        // The stepper keeps the field in range while it is clicked, but the
        // field is typeable, so the value is clamped here as well. The server
        // clamps it again.
        var quantity = parseInt($('#pg-if-keycodes-quantity').value, 10) || 1;
        if (quantity < 1) { quantity = 1; }
        if (quantity > 100) { quantity = 100; }
        $('#pg-if-keycodes-quantity').value = quantity;
        button.disabled = true;
        api({ type: 'offer_key_codes', id: S.offer.id, quantity: quantity })
            .then(function (res) {
                button.disabled = false;
                if (res.status !== 'success') { toast(res.message || L.requestFailed, 'danger'); return; }
                C.key_code_count = res.count;
                renderHeader();
                var box = $('#pg-if-keycodes-new');
                $('#pg-if-keycodes-list').value = (res.codes || []).join('\n');
                show(box, (res.codes || []).length > 0);
                toast(res.message, 'success');
            })
            .catch(function () { button.disabled = false; toast(L.requestFailed, 'danger'); });
    }

    // Removes every key code that hangs off this offer's code. Permanent, so
    // it asks first and names the number it is about to remove.
    function deleteKeyCodes(button) {
        if (isNew || !(C.key_code_count > 0)) { return; }
        var go = function () {
            button.disabled = true;
            api({ type: 'offer_key_codes_delete', id: S.offer.id })
                .then(function (res) {
                    button.disabled = false;
                    if (res.status !== 'success') { toast(res.message || L.requestFailed, 'danger'); return; }
                    C.key_code_count = res.count;
                    show($('#pg-if-keycodes-new'), false);
                    $('#pg-if-keycodes-list').value = '';
                    renderHeader();
                    toast(res.message, 'success');
                })
                .catch(function () { button.disabled = false; toast(L.requestFailed, 'danger'); });
        };
        var message = fmt(L.keyCodesDeleteConfirm, [C.key_code_count]);
        if (window.pgConfirm) {
            window.pgConfirm({ title: L.keyCodesDeleteTitle, message: message, confirmText: L.deleteButton, cancelText: L.cancelButton, variant: 'danger' })
                .then(function (ok) { if (ok) { go(); } });
        } else if (window.confirm(message)) {
            go();
        }
    }

    // ── server ───────────────────────────────────────────────────────────
    function api(body) {
        body.action = 'offer_editor';
        body.token = D.token;
        return fetch(C.api_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }
    function toast(message, variant) {
        if (window.pgToast) { window.pgToast({ message: message, variant: variant || 'info' }); }
        else if (variant === 'danger') { window.alert(message); }
    }
    var saving = false;
    function save() {
        if (saving || hardErrorCount() > 0) { return; }
        saving = true;
        var btn = $('#pg-if-save');
        btn.disabled = true;
        serverErrors = {};
        api({ type: 'offer_save', offer: S.offer, conditions: S.conditions, actions: S.actions })
            .then(function (res) {
                saving = false;
                if (res.status === 'success') {
                    // The screen is done: the list is where the operator goes
                    // next, and it shows the offer they just wrote.
                    dirty = false;
                    window.location.href = C.list_url;
                } else {
                    serverErrors = res.errors || {};
                    if (serverErrors['offer.code']) {
                        var ce = $('#pg-if-code-error'); ce.textContent = serverErrors['offer.code']; show(ce, true);
                    }
                    render();
                    btn.disabled = false;
                    toast(res.message || L.requestFailed, 'danger');
                }
            })
            .catch(function () {
                saving = false;
                btn.disabled = false;
                toast(L.requestFailed, 'danger');
            });
    }
    function remove() {
        if (isNew) { return; }
        var go = function () {
            api({ type: 'offer_delete', id: S.offer.id }).then(function (res) {
                if (res.status === 'success') {
                    dirty = false;
                    window.location.href = C.list_url;
                } else {
                    toast(res.message || L.requestFailed, 'danger');
                }
            }).catch(function () { toast(L.requestFailed, 'danger'); });
        };
        if (window.pgConfirm) {
            window.pgConfirm({ title: L.deleteTitle, message: L.deleteConfirm, confirmText: L.deleteButton, cancelText: L.cancelButton, variant: 'danger' })
                .then(function (ok) { if (ok) { go(); } });
        } else if (window.confirm(L.deleteConfirm)) {
            go();
        }
    }

    render();
})();
