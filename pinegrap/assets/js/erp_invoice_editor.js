/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the invoice line editor.
 *
 * Adds and removes lines, looks products up to fill a line from, previews the
 * totals as they are typed and, with foreign currency on, fetches the recorded
 * rate of the issue date into an empty rate box.
 *
 * The arithmetic here mirrors the money helpers on the server (erp_kurus,
 * erp_line_total, erp_apply_rate): whole kurus in integers, rounding half away
 * from zero at the same points. The server works everything out again on
 * save; what is shown here is a preview and must agree with it. A line's VAT
 * withholding is the share of its VAT its code carries (the list comes from
 * the server in data-withholding-rates; a code it does not know keeps the
 * share stored on the line), and comes off the total.
 *
 * Loaded by the invoice editor screens with a plain <script src>; it does not
 * depend on jQuery, but uses it for the date fields when it is present because
 * the date picker raises its change through jQuery only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var root = document.querySelector('[data-erp-editor]');
    if (!root) {
        return;
    }

    var tbody = root.querySelector('[data-erp-lines]');
    var template = root.querySelector('[data-erp-line-template]');
    var addButton = root.querySelector('[data-erp-add-line]');
    var totalsBox = root.querySelector('[data-erp-totals]');
    var directionSelect = document.getElementById('direction');
    var currencySelect = document.getElementById('currency');
    var rateInput = document.getElementById('exchange_rate');
    var issueDateInput = document.getElementById('issue_date');

    var productsUrl = root.getAttribute('data-products-url');
    var accountSelect = document.getElementById('account_id');
    var rateUrl = root.getAttribute('data-rate-url');
    var fxOn = root.getAttribute('data-fx') === '1';
    var baseCurrency = root.getAttribute('data-base-currency') || '';
    var dateFormat = root.getAttribute('data-date-format') || 'day_month';
    // How a single comma with three digits behind it is read (erp_decimal_comma()).
    var decimalComma = root.getAttribute('data-decimal-comma') !== '0';
    var maxLines = parseInt(root.getAttribute('data-max-lines'), 10) || 200;
    var textNoResults = root.getAttribute('data-text-no-results') || '';
    var textMaxLines = root.getAttribute('data-text-max-lines') || '';
    var texts = {
        stock: root.getAttribute('data-text-stock') || 'In stock: {var:1}',
        noStockTracking: root.getAttribute('data-text-no-stock-tracking') || '',
        outOfStock: root.getAttribute('data-text-out-of-stock') || '',
        disabled: root.getAttribute('data-text-disabled') || '',
        campaign: root.getAttribute('data-text-campaign') || '',
        sku: root.getAttribute('data-text-sku') || 'SKU',
        zoneRate: root.getAttribute('data-text-zone-rate') || '',
        notFound: root.getAttribute('data-text-not-found') || '',
        overStock: root.getAttribute('data-text-over-stock') || '',
        lastCost: root.getAttribute('data-text-last-cost') || '',
        avgCost: root.getAttribute('data-text-avg-cost') || '',
        accountPrice: root.getAttribute('data-text-account-price') || '',
        accountDiscount: root.getAttribute('data-text-account-discount') || ''
    };
    var barcodeInput = root.querySelector('[data-erp-barcode]');
    var withholdingToggle = root.querySelector('[data-erp-withholding-toggle]');
    var withholdingRates = {};
    try {
        withholdingRates = JSON.parse(root.getAttribute('data-withholding-rates') || '{}') || {};
    } catch (error) {
        withholdingRates = {};
    }

    /* ------------------------------------------------------------ parsing */

    // erp_kurus(): both separators in either role; the last separator with at
    // most two digits behind it is the decimal point. Returns whole kurus.
    function parseKurus(raw) {
        raw = String(raw || '').trim();
        if (raw === '') {
            return 0;
        }
        var negative = raw.indexOf('-') !== -1;
        var digits = raw.replace(/[^0-9.,]/g, '');
        if (digits === '') {
            return 0;
        }
        var lastDot = digits.lastIndexOf('.');
        var lastComma = digits.lastIndexOf(',');
        var position = Math.max(lastDot, lastComma);
        var separator = '';
        if (position >= 0 && (digits.length - position - 1) <= 2) {
            separator = digits.charAt(position);
        }
        var whole;
        var fraction;
        if (separator === '') {
            whole = digits.replace(/[^0-9]/g, '');
            fraction = '';
        } else {
            var parts = digits.split(separator);
            fraction = parts.pop().replace(/[^0-9]/g, '');
            whole = parts.join('').replace(/[^0-9]/g, '');
        }
        fraction = (fraction + '00').substring(0, 2);
        var kurus = (parseInt(whole || '0', 10) * 100) + parseInt(fraction, 10);
        return negative ? -kurus : kurus;
    }

    // erp_fx_rate_in(): the last separator is the decimal one, the others group.
    function parseDecimal(raw) {
        raw = String(raw || '').replace(/[^0-9.,]/g, '');
        if (raw === '') {
            return 0;
        }
        var position = Math.max(raw.lastIndexOf('.'), raw.lastIndexOf(','));
        if (position < 0) {
            return parseFloat(raw) || 0;
        }
        var whole = raw.substring(0, position).replace(/[^0-9]/g, '');
        var fraction = raw.substring(position + 1).replace(/[^0-9]/g, '');
        return parseFloat((whole || '0') + '.' + (fraction || '0')) || 0;
    }

    // erp_quantity_in(): with both separators the last is the decimal one; a
    // separator seen more than once groups; a single point is decimal; a
    // single comma with three digits behind it groups where the store writes
    // a decimal point.
    function parseQuantity(raw) {
        raw = String(raw || '').replace(/[^0-9.,]/g, '');
        if (raw === '') {
            return 0;
        }
        var dots = raw.split('.').length - 1;
        var commas = raw.split(',').length - 1;
        var decimal = '';
        if (dots > 0 && commas > 0) {
            decimal = raw.lastIndexOf('.') > raw.lastIndexOf(',') ? '.' : ',';
        } else if (dots === 1) {
            decimal = '.';
        } else if (commas === 1) {
            var behind = raw.length - raw.lastIndexOf(',') - 1;
            decimal = (behind === 3 && !decimalComma) ? '' : ',';
        }
        if (decimal === '') {
            return parseFloat(raw.replace(/[^0-9]/g, '')) || 0;
        }
        var position = raw.lastIndexOf(decimal);
        var whole = raw.substring(0, position).replace(/[^0-9]/g, '');
        var fraction = raw.substring(position + 1).replace(/[^0-9]/g, '');
        return parseFloat((whole || '0') + '.' + (fraction || '0')) || 0;
    }

    // round() in PHP: half away from zero.
    function roundHalfAway(value) {
        return value < 0 ? -Math.round(-value) : Math.round(value);
    }

    function applyRate(kurus, rate) {
        return roundHalfAway(kurus * rate / 100);
    }

    function lineTotal(unitPrice, quantity) {
        return roundHalfAway(unitPrice * quantity);
    }

    /* --------------------------------------------------------- formatting */

    var numberFormat = null;
    try {
        numberFormat = new Intl.NumberFormat(document.documentElement.lang || undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } catch (error) {
        numberFormat = null;
    }

    function currentCurrency() {
        if (fxOn && currencySelect && currencySelect.value) {
            return currencySelect.value;
        }
        return baseCurrency;
    }

    function formatMoney(kurus) {
        var amount = kurus / 100;
        var text = numberFormat ? numberFormat.format(amount) : amount.toFixed(2);
        var code = currentCurrency();
        return code ? (text + ' ' + code) : text;
    }

    /* -------------------------------------------------------------- lines */

    function rows() {
        return Array.prototype.slice.call(tbody.querySelectorAll('[data-erp-line]'));
    }

    function field(row, name) {
        return row.querySelector('[name$="[' + name + ']"]');
    }

    function nextIndex() {
        var highest = -1;
        rows().forEach(function (row) {
            var input = row.querySelector('[name^="lines["]');
            if (!input) {
                return;
            }
            var match = /^lines\[(\d+)\]/.exec(input.getAttribute('name'));
            if (match) {
                highest = Math.max(highest, parseInt(match[1], 10));
            }
        });
        return highest + 1;
    }

    function renumber() {
        rows().forEach(function (row, position) {
            var cell = row.querySelector('[data-erp-line-no]');
            if (cell) {
                cell.textContent = String(position + 1);
            }
        });
    }

    function addLine() {
        if (!template) {
            return null;
        }
        if (rows().length >= maxLines) {
            if (textMaxLines) {
                window.alert(textMaxLines);
            }
            return null;
        }
        var index = nextIndex();
        var html = template.innerHTML.replace(/__INDEX__/g, String(index));
        var holder = document.createElement('tbody');
        holder.innerHTML = html;
        var row = holder.querySelector('[data-erp-line]');
        if (!row) {
            return null;
        }
        showWithholding(row, withholdingOn());
        tbody.appendChild(row);
        renumber();
        recalc();
        return row;
    }

    function removeLine(row) {
        // The last row is emptied rather than removed, so there is always a
        // line to type into.
        if (rows().length <= 1) {
            Array.prototype.forEach.call(row.querySelectorAll('input'), function (input) {
                input.value = (input.getAttribute('type') === 'hidden') ? '0' : '';
            });
            var unit = field(row, 'unit_code');
            if (unit) {
                unit.selectedIndex = 0;
            }
            var code = field(row, 'withholding_code');
            if (code) {
                code.value = '';
            }
        } else {
            row.parentNode.removeChild(row);
        }
        renumber();
        recalc();
    }

    function lineFigures(row) {
        var quantity = parseQuantity(field(row, 'quantity') ? field(row, 'quantity').value : '');
        var unitPrice = parseKurus(field(row, 'unit_price') ? field(row, 'unit_price').value : '');
        var discountRate = parseDecimal(field(row, 'discount_rate') ? field(row, 'discount_rate').value : '');
        var offerRate = parseDecimal(field(row, 'offer_discount_rate') ? field(row, 'offer_discount_rate').value : '');
        var taxRate = parseDecimal(field(row, 'tax_rate') ? field(row, 'tax_rate').value : '');
        var tax2Rate = parseDecimal(field(row, 'tax2_rate') ? field(row, 'tax2_rate').value : '');

        // The campaign first, the typed discount on what it leaves; the same
        // order erp_manual_lines_build() uses.
        var total = lineTotal(unitPrice, quantity);
        var offer = offerRate > 0 ? applyRate(total, offerRate) : 0;
        var typed = discountRate > 0 ? applyRate(total - offer, discountRate) : 0;
        var discount = offer + typed;
        var tax = applyRate(total - discount, taxRate);
        // A second tax, where the store names one: its own rate on the same
        // net; withholding stays a share of the first.
        var tax2 = tax2Rate > 0 ? applyRate(total - discount, tax2Rate) : 0;
        var share = withholdingShare(row);
        var withholding = share > 0 ? applyRate(tax, share) : 0;

        return {
            lineTotal: total,
            discount: discount,
            tax: tax,
            tax2: tax2,
            withholding: withholding,
            payable: total - discount + tax + tax2 - withholding,
            isEmpty: (quantity === 0 && unitPrice === 0)
        };
    }

    /* ---------------------------------------------------------- withholding */

    // The share of the VAT a line withholds: the list's for a code it knows,
    // the stored one for a code it does not, nothing without a code.
    function withholdingShare(row) {
        var code = field(row, 'withholding_code');
        var value = code ? code.value : '';
        if (value === '') {
            return 0;
        }
        if (Object.prototype.hasOwnProperty.call(withholdingRates, value)) {
            return Number(withholdingRates[value]) || 0;
        }
        var stored = field(row, 'withholding_rate');
        return stored ? parseDecimal(stored.value) : 0;
    }

    function withholdingOn() {
        return !!(withholdingToggle && withholdingToggle.checked);
    }

    function showWithholding(scope, on) {
        Array.prototype.forEach.call(scope.querySelectorAll('[data-erp-withholding-col]'), function (cell) {
            cell.classList.toggle('d-none', !on);
        });
        if (scope.matches && scope.matches('[data-erp-withholding-col]')) {
            scope.classList.toggle('d-none', !on);
        }
    }

    function applyWithholdingToggle() {
        var on = withholdingOn();
        showWithholding(root, on);
        // Turned off means none: a hidden code would still be sent.
        if (!on) {
            rows().forEach(function (row) {
                var code = field(row, 'withholding_code');
                var stored = field(row, 'withholding_rate');
                if (code) {
                    code.value = '';
                }
                if (stored) {
                    stored.value = '';
                }
            });
        }
        recalc();
    }

    function recalc() {
        var subtotal = 0;
        var discountTotal = 0;
        var taxTotal = 0;
        var tax2Total = 0;
        var withholdingTotal = 0;

        rows().forEach(function (row) {
            var figures = lineFigures(row);
            var cell = row.querySelector('[data-erp-line-total]');
            if (cell) {
                cell.textContent = figures.isEmpty ? '' : formatMoney(figures.payable);
            }
            subtotal += figures.lineTotal;
            discountTotal += figures.discount;
            taxTotal += figures.tax;
            tax2Total += figures.tax2;
            withholdingTotal += figures.withholding;
        });

        if (!totalsBox) {
            return;
        }

        var totals = {
            subtotal: subtotal,
            discount_total: discountTotal,
            tax_total: taxTotal,
            tax2_total: tax2Total,
            withholding_total: withholdingTotal,
            grand_total: subtotal - discountTotal + taxTotal + tax2Total - withholdingTotal
        };

        var withholdingRow = totalsBox.querySelector('[data-erp-withholding-total]');
        if (withholdingRow) {
            withholdingRow.classList.toggle('d-none', !(withholdingOn() || withholdingTotal !== 0));
        }

        Object.keys(totals).forEach(function (key) {
            var target = totalsBox.querySelector('[data-erp-total="' + key + '"]');
            if (target) {
                target.textContent = formatMoney(totals[key]);
            }
        });
    }

    /* ----------------------------------------------------- product lookup */

    var lookupTimer = null;
    var lookupRequest = 0;

    function closeResults(except) {
        Array.prototype.forEach.call(root.querySelectorAll('[data-erp-product-results].show'), function (menu) {
            if (menu !== except) {
                menu.classList.remove('show');
                menu.innerHTML = '';
            }
        });
    }

    function formatPrice(kurus) {
        return (kurus / 100).toFixed(2);
    }

    function trimNumber(value) {
        var text = String(value);
        if (text.indexOf('.') === -1) {
            return text;
        }
        return text.replace(/0+$/, '').replace(/\.$/, '');
    }

    function isPurchase() {
        return !!(directionSelect && directionSelect.value === 'purchase');
    }

    function fill(text, value) {
        return String(text).replace('{var:1}', String(value));
    }

    // What the line says about its product under the search box: the SKU,
    // the stock, the campaign, and a warning when the quantity typed is more
    // than the shelf holds.
    function updateHint(row) {
        var hint = row.querySelector('[data-erp-product-hint]');
        if (!hint) {
            return;
        }
        var idInput = row.querySelector('[data-erp-product-id]');
        if (!idInput || parseInt(idInput.value, 10) <= 0 || !row.dataset.erpSku) {
            hint.textContent = '';
            hint.className = 'form-text small mt-1';
            return;
        }

        var parts = [];
        parts.push(texts.sku + ': ' + row.dataset.erpSku);

        var warning = false;
        if (row.dataset.erpStock !== undefined && row.dataset.erpStock !== '' && isPurchase()) {
            // Goods coming in: the count is information, not a limit.
            parts.push(fill(texts.stock, parseInt(row.dataset.erpStock, 10)));
        } else if (row.dataset.erpStock !== undefined && row.dataset.erpStock !== '') {
            var stock = parseInt(row.dataset.erpStock, 10);
            var quantity = parseQuantity(field(row, 'quantity') ? field(row, 'quantity').value : '');
            if (stock <= 0) {
                parts.push(texts.outOfStock);
                warning = true;
            } else if (quantity > stock) {
                parts.push(fill(texts.overStock, stock));
                warning = true;
            } else {
                parts.push(fill(texts.stock, stock));
            }
        } else if (texts.noStockTracking) {
            parts.push(texts.noStockTracking);
        }
        if (row.dataset.erpDisabled === '1' && texts.disabled) {
            parts.push(texts.disabled);
            warning = true;
        }
        if (row.dataset.erpOffer) {
            parts.push(texts.campaign + ' ' + row.dataset.erpOffer);
        }
        if (row.dataset.erpTerms) {
            parts.push(row.dataset.erpTerms);
        }
        // What the product has cost: the last purchase on a purchase, the
        // average on a sale (includes/erp/stock.php).
        if (isPurchase() && row.dataset.erpLastCost && texts.lastCost) {
            parts.push(fill(texts.lastCost, row.dataset.erpLastCost));
        } else if (!isPurchase() && row.dataset.erpAvgCost && texts.avgCost) {
            parts.push(fill(texts.avgCost, row.dataset.erpAvgCost));
        }

        // Where the VAT rate came from is said on the box, not in the line.
        var taxRate = field(row, 'tax_rate');
        if (taxRate) {
            taxRate.title = (row.dataset.erpZoneRate === '1' && texts.zoneRate) ? texts.zoneRate : '';
        }

        hint.textContent = parts.join(' \u00b7 ');
        hint.className = 'form-text small mt-1' + (warning ? ' text-warning-emphasis' : '');
    }

    function fillFromProduct(row, product) {
        var idInput = row.querySelector('[data-erp-product-id]');
        var nameInput = field(row, 'product_name');
        var description = field(row, 'description');
        var unitPrice = field(row, 'unit_price');
        var taxRate = field(row, 'tax_rate');
        var quantity = field(row, 'quantity');
        var offerId = row.querySelector('[data-erp-offer-id]');
        var offerRate = field(row, 'offer_discount_rate');

        if (idInput) {
            idInput.value = String(product.id);
        }
        if (nameInput) {
            nameInput.value = product.name;
        }
        if (description) {
            description.value = product.name;
        }
        // A purchase is priced at what the product last cost, not at the
        // shelf price; with no purchase yet the box is left to be typed. The
        // cost is kept in the base currency, so a bill in another currency
        // leaves the box to be typed too (the hint still shows the cost).
        if (unitPrice && isPurchase()) {
            unitPrice.value = (product.cost && product.cost.last > 0 && currentCurrency() === baseCurrency)
                ? formatPrice(product.cost.last) : '';
        } else if (unitPrice) {
            unitPrice.value = formatPrice(product.price);
        }

        // Terms agreed with the account (includes/erp/price_lists.php): its
        // own price, or its discount in the discount box. They stand in for
        // the store's campaign; a purchase has neither.
        var terms = (!isPurchase() && product.account_terms) ? product.account_terms : null;
        var termsText = '';
        if (terms && terms.price > 0 && unitPrice) {
            unitPrice.value = formatPrice(terms.price);
            termsText = fill(texts.accountPrice, formatPrice(product.price));
        } else if (terms && terms.discount_rate > 0 && field(row, 'discount_rate')) {
            field(row, 'discount_rate').value = trimNumber(Number(terms.discount_rate).toFixed(3));
            termsText = fill(texts.accountDiscount, trimNumber(Number(terms.discount_rate).toFixed(3)) + '%');
        }
        if (taxRate && product.tax_rate !== null && product.tax_rate !== undefined) {
            taxRate.value = trimNumber(Number(product.tax_rate).toFixed(3));
        }
        if (quantity && quantity.value.trim() === '') {
            quantity.value = '1';
        }

        // The store's campaign is a discount on a sale; a purchase bill has
        // none. The delivery note form has neither box, hence the checks.
        var offer = (!isPurchase() && product.offer && !terms) ? product.offer : null;
        if (offerId) {
            offerId.value = offer ? String(offer.id) : '0';
        }
        if (offerRate) {
            offerRate.value = offer ? trimNumber(Number(offer.rate).toFixed(3)) : '';
        }

        showThumb(row, product.image_url || '');

        row.dataset.erpSku = product.sku || '';
        row.dataset.erpStock = (product.stock === null || product.stock === undefined) ? '' : String(product.stock);
        row.dataset.erpDisabled = (product.enabled === false) ? '1' : '0';
        row.dataset.erpOffer = offer ? ('\u2212' + trimNumber(Number(offer.rate).toFixed(3)) + '%') : '';
        row.dataset.erpTerms = termsText;
        row.dataset.erpLastCost = (product.cost && product.cost.last > 0)
            ? (product.cost.last_text + (product.cost.last_date ? ' (' + product.cost.last_date + ')' : ''))
            : '';
        row.dataset.erpAvgCost = (product.cost && product.cost.avg > 0) ? product.cost.avg_text : '';
        row.dataset.erpZoneRate = (product.tax_rate_source === 'zone') ? '1' : '0';

        updateHint(row);
        recalc();
    }

    /**
     * The picked product's picture, or nothing. Hidden rather than left
     * showing the last product's picture: a row that has been cleared must
     * not still look like it holds something.
     */
    function showThumb(row, url) {

        var thumb = row.querySelector('[data-erp-product-thumb]');

        if (!thumb) {
            return;
        }

        if (url) {
            thumb.src = url;
            thumb.classList.remove('d-none');
        } else {
            thumb.removeAttribute('src');
            thumb.classList.add('d-none');
        }
    }


    function badge(text, className) {
        var span = document.createElement('span');
        span.className = 'badge ' + className + ' ms-1';
        span.textContent = text;
        return span;
    }

    function renderResults(row, menu, products) {
        menu.innerHTML = '';

        if (!products.length) {
            var empty = document.createElement('span');
            empty.className = 'dropdown-item-text text-body-secondary small';
            empty.textContent = textNoResults;
            menu.appendChild(empty);
        }

        products.forEach(function (product) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'dropdown-item text-truncate no-submit';

            var name = document.createElement('span');
            name.textContent = product.name;
            item.appendChild(name);

            if (product.sku && product.sku !== product.name) {
                var sku = document.createElement('span');
                sku.className = 'text-body-secondary small ms-2';
                sku.textContent = product.sku;
                item.appendChild(sku);
            }

            var price = document.createElement('span');
            price.className = 'text-body-secondary small ms-2';
            price.textContent = formatPrice(product.price);
            item.appendChild(price);

            if (product.stock !== null && product.stock !== undefined) {
                item.appendChild(product.stock > 0
                    ? badge(fill(texts.stock, product.stock), 'text-bg-light border')
                    : badge(texts.outOfStock, 'text-bg-warning'));
            }
            if (product.enabled === false && texts.disabled) {
                item.appendChild(badge(texts.disabled, 'text-bg-secondary'));
            }
            if (product.offer && !isPurchase()) {
                item.appendChild(badge('\u2212' + trimNumber(Number(product.offer.rate).toFixed(3)) + '%', 'text-bg-success'));
            }

            item.addEventListener('click', function () {
                fillFromProduct(row, product);
                closeResults();
            });
            menu.appendChild(item);
        });

        menu.classList.add('show');
    }

    /* ------------------------------------------------------------- barcode */

    // A blank row is one nothing has been typed into: no product, no text,
    // no quantity.
    function rowIsBlank(row) {
        var idInput = row.querySelector('[data-erp-product-id]');
        if (idInput && parseInt(idInput.value, 10) > 0) {
            return false;
        }
        return ['product_name', 'description', 'quantity', 'unit_price'].every(function (name) {
            var input = field(row, name);
            return !input || input.value.trim() === '';
        });
    }

    function rowForProduct(productId) {
        return rows().filter(function (row) {
            var idInput = row.querySelector('[data-erp-product-id]');
            return idInput && parseInt(idInput.value, 10) === productId;
        })[0] || null;
    }

    // A scan lands the product on the line that already carries it (one more
    // of the same), on the first blank line, or on a new one.
    function placeProduct(product) {
        var existing = rowForProduct(product.id);
        if (existing) {
            var quantity = field(existing, 'quantity');
            if (quantity) {
                quantity.value = trimNumber((parseQuantity(quantity.value) + 1).toFixed(4));
            }
            updateHint(existing);
            recalc();
            existing.classList.add('table-active');
            window.setTimeout(function () { existing.classList.remove('table-active'); }, 600);
            return existing;
        }

        var target = rows().filter(rowIsBlank)[0] || addLine();
        if (!target) {
            return null;
        }
        fillFromProduct(target, product);
        target.classList.add('table-active');
        window.setTimeout(function () { target.classList.remove('table-active'); }, 600);
        return target;
    }

    function scan(code) {
        code = String(code || '').trim();
        if (!code || !productsUrl) {
            return;
        }
        barcodeInput.disabled = true;
        fetch(productsUrl + '?barcode=' + encodeURIComponent(code) + accountParam(), { credentials: 'same-origin' })
            .then(function (response) {
                return response.ok ? response.json() : { product: null };
            })
            .then(function (data) {
                barcodeInput.disabled = false;
                if (data && data.product) {
                    barcodeInput.classList.remove('is-invalid');
                    barcodeInput.value = '';
                    placeProduct(data.product);
                } else {
                    barcodeInput.classList.add('is-invalid');
                    barcodeInput.title = texts.notFound;
                    barcodeInput.select();
                }
                barcodeInput.focus();
            })
            .catch(function () {
                barcodeInput.disabled = false;
                barcodeInput.focus();
            });
    }

    function lookup(row, input) {
        var menu = row.querySelector('[data-erp-product-results]');
        var query = input.value.trim();

        // The typed name no longer names the product that was picked.
        var idInput = row.querySelector('[data-erp-product-id]');
        if (idInput) {
            idInput.value = '0';
        }
        row.dataset.erpSku = '';

        if (!menu || !productsUrl) {
            return;
        }
        if (query.length < 2) {
            menu.classList.remove('show');
            menu.innerHTML = '';
            return;
        }

        var request = ++lookupRequest;

        window.clearTimeout(lookupTimer);
        lookupTimer = window.setTimeout(function () {
            fetch(productsUrl + '?q=' + encodeURIComponent(query) + accountParam(), { credentials: 'same-origin' })
                .then(function (response) {
                    return response.ok ? response.json() : { results: [] };
                })
                .then(function (data) {
                    if (request !== lookupRequest || document.activeElement !== input) {
                        return;
                    }
                    closeResults(menu);
                    renderResults(row, menu, (data && data.results) ? data.results : []);
                })
                .catch(function () {
                    menu.classList.remove('show');
                });
        }, 250);
    }

    /* ------------------------------------------------------ tax zone */

    // The account the document is for: a product without a rate of its own is
    // offered at the rate of that account's tax zone (erp_account_zone_rate()).
    function accountParam() {
        var id = accountSelect ? parseInt(accountSelect.value, 10) : 0;
        return (id > 0) ? ('&account_id=' + id) : '';
    }

    // A new account moves the lines that took the zone's rate to the new
    // account's zone; a rate typed by hand or the product's own stays.
    function refreshZoneRates() {
        if (!productsUrl) {
            return;
        }
        var zoneRows = rows().filter(function (row) {
            return row.dataset.erpZoneRate === '1' && field(row, 'tax_rate');
        });
        if (!zoneRows.length) {
            return;
        }
        fetch(productsUrl + '?zone_rate=1' + accountParam(), { credentials: 'same-origin' })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (data) {
                if (!data || data.rate === undefined || data.rate === null) {
                    return;
                }
                zoneRows.forEach(function (row) {
                    field(row, 'tax_rate').value = trimNumber(Number(data.rate).toFixed(3));
                });
                recalc();
            })
            .catch(function () {});
    }

    if (accountSelect) {
        accountSelect.addEventListener('change', refreshZoneRates);
        if (window.jQuery) {
            window.jQuery(accountSelect).on('select2:select', refreshZoneRates);
        }
    }

    /* ------------------------------------------------------ exchange rate */

    // The date as the operator sees it, back to Y-m-d for the server.
    function isoDate(typed) {
        var parts = String(typed || '').trim().split(/[\/.\-]/);
        if (parts.length !== 3) {
            return '';
        }
        var day;
        var month;
        var year;
        if (dateFormat === 'month_day') {
            month = parts[0];
            day = parts[1];
        } else {
            day = parts[0];
            month = parts[1];
        }
        year = parts[2];
        if (!/^\d{4}$/.test(year) || !/^\d{1,2}$/.test(month) || !/^\d{1,2}$/.test(day)) {
            return '';
        }
        return year + '-' + ('0' + month).slice(-2) + '-' + ('0' + day).slice(-2);
    }

    var rateRequest = 0;

    function refreshRate() {
        if (!fxOn || !rateInput || !currencySelect || !rateUrl) {
            return;
        }

        var currency = currencySelect.value;

        if (!currency || currency === baseCurrency) {
            // The base has no rate; a rate left over from another currency
            // would be sent as a typed one.
            if (rateInput.getAttribute('data-erp-rate-auto') === '1') {
                rateInput.value = '';
                rateInput.removeAttribute('data-erp-rate-auto');
            }
            return;
        }

        // A rate the operator typed is never overwritten; only an empty box
        // or one this script filled follows the date.
        if (rateInput.value.trim() !== '' && rateInput.getAttribute('data-erp-rate-auto') !== '1') {
            return;
        }

        var date = issueDateInput ? isoDate(issueDateInput.value) : '';
        var request = ++rateRequest;

        fetch(rateUrl + '?currency=' + encodeURIComponent(currency) + (date ? ('&date=' + encodeURIComponent(date)) : ''), { credentials: 'same-origin' })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (data) {
                if (request !== rateRequest || !rateInput) {
                    return;
                }
                if (data && data.found) {
                    rateInput.value = data.rate;
                    rateInput.setAttribute('data-erp-rate-auto', '1');
                } else if (rateInput.getAttribute('data-erp-rate-auto') === '1') {
                    rateInput.value = '';
                    rateInput.removeAttribute('data-erp-rate-auto');
                }
            })
            .catch(function () {});
    }

    /* ---------------------------------------------------------- direction */

    function applyDirection() {
        var isPurchase = directionSelect && directionSelect.value === 'purchase';
        Array.prototype.forEach.call(root.querySelectorAll('[data-erp-purchase-only]'), function (block) {
            block.classList.toggle('d-none', !isPurchase);
        });
    }

    /* -------------------------------------------------------------- wiring */

    if (addButton) {
        addButton.addEventListener('click', function () {
            var row = addLine();
            if (row) {
                var first = field(row, 'product_name');
                if (first) {
                    first.focus();
                }
            }
        });
    }

    tbody.addEventListener('click', function (event) {
        var button = event.target.closest('[data-erp-remove-line]');
        if (button) {
            var row = button.closest('[data-erp-line]');
            if (row) {
                removeLine(row);
            }
        }
    });

    tbody.addEventListener('input', function (event) {
        var target = event.target;
        var row = target.closest('[data-erp-line]');
        if (!row) {
            return;
        }
        if (target.classList.contains('erp-product-search')) {
            lookup(row, target);
            updateHint(row);
            return;
        }
        // A rate typed over the zone's is the operator's: a later change of
        // account leaves it alone (refreshZoneRates()).
        if (target === field(row, 'tax_rate')) {
            row.dataset.erpZoneRate = '0';
        }
        updateHint(row);
        recalc();
    });

    if (barcodeInput) {
        // A scanner types the code and sends Enter; a person does the same.
        barcodeInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                scan(barcodeInput.value);
            }
        });
        barcodeInput.addEventListener('input', function () {
            barcodeInput.classList.remove('is-invalid');
        });
    }

    // A save that came back refused marked the field; take the operator there.
    (function () {
        var invalid = document.querySelector('form .is-invalid');
        if (invalid) {
            invalid.scrollIntoView({ block: 'center' });
            if (typeof invalid.focus === 'function') {
                invalid.focus();
            }
        }
    }());

    tbody.addEventListener('change', function (event) {
        // A code picked from the list brings its share with it; the stored
        // share of an unknown code is kept.
        if (event.target.matches('[data-erp-withholding-code]')) {
            var row = event.target.closest('[data-erp-line]');
            var stored = row ? field(row, 'withholding_rate') : null;
            if (stored) {
                var code = event.target.value;
                stored.value = Object.prototype.hasOwnProperty.call(withholdingRates, code) ? String(withholdingRates[code]) : (code === '' ? '' : stored.value);
            }
        }
        recalc();
    });

    if (withholdingToggle) {
        withholdingToggle.addEventListener('change', applyWithholdingToggle);
    }

    tbody.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeResults();
        }
        // Enter on a line adds the next one instead of submitting the form:
        // a form with two submit buttons would otherwise issue the invoice
        // from a keystroke meant for the next row.
        if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
            event.preventDefault();
            var row = event.target.closest('[data-erp-line]');
            var all = rows();
            if (row && all.indexOf(row) === all.length - 1) {
                var added = addLine();
                if (added && field(added, 'product_name')) {
                    field(added, 'product_name').focus();
                }
            } else if (row) {
                var next = all[all.indexOf(row) + 1];
                if (next && field(next, 'product_name')) {
                    field(next, 'product_name').focus();
                }
            }
        }
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-erp-product-results], .erp-product-search')) {
            closeResults();
        }
    });

    if (directionSelect) {
        directionSelect.addEventListener('change', applyDirection);
    }

    if (currencySelect) {
        currencySelect.addEventListener('change', function () {
            refreshRate();
            recalc();
        });
    }

    if (rateInput) {
        rateInput.addEventListener('input', function () {
            rateInput.removeAttribute('data-erp-rate-auto');
        });
    }

    if (issueDateInput) {
        // The date picker raises its change through jQuery; a native listener
        // would miss a date picked from the calendar.
        if (window.jQuery) {
            window.jQuery(issueDateInput).on('change', refreshRate);
        } else {
            issueDateInput.addEventListener('change', refreshRate);
        }
    }

    applyDirection();
    recalc();
    refreshRate();
}());
