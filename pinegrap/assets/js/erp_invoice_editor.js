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
 * erp_line_total, erp_apply_rate, erp_allocate, erp_rate_vat): whole kurus in
 * integers, rounding half away from zero at the same points. The server works
 * everything out again on save; what is shown here is a preview and must
 * agree with it. The taxes are taken per group of lines, not line by line:
 * the VAT of the lines with one VAT rate and one withholding rate on their
 * sum, then spread back over them. A line's VAT withholding is the share of
 * its VAT its code carries (the list comes from the server in
 * data-withholding-rates; a code it does not know keeps the share stored on
 * the line), rounded once for the document, and comes off the total.
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
    // A polite status line for what the product search finds: the popup
    // moves to the overlay host, and a plain line in it is not read out.
    var liveStatus = document.createElement('div');
    var announceTimer = null;
    liveStatus.className = 'visually-hidden';
    liveStatus.setAttribute('role', 'status');
    liveStatus.setAttribute('aria-live', 'polite');
    liveStatus.setAttribute('aria-atomic', 'true');
    root.appendChild(liveStatus);
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
        accountDiscount: root.getAttribute('data-text-account-discount') || '',
        percent: root.getAttribute('data-text-percent') || '{var:1}%'
    };
    // The panel language's decimal mark for figures shown, not for typed input.
    var decimalMark = root.getAttribute('data-decimal-mark') || '.';
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

    // erp_decimal_units(): a decimal as a whole number of its smallest unit
    // at the given places, half away from zero, rounded on the digits
    // toFixed() writes (the ones PHP's sprintf() writes), not on a product of
    // floats.
    function decimalUnits(value, places) {
        value = Number(value) || 0;
        var parts = Math.abs(value).toFixed(10).split('.');
        var units = parseInt(parts[0] + parts[1].substring(0, places), 10);
        if (parseInt(parts[1].charAt(places), 10) >= 5) {
            units++;
        }
        return value < 0 ? -units : units;
    }

    // erp_quantity_round(): the four places a line's quantity is stored with.
    function quantityRound(quantity) {
        return decimalUnits(quantity, 4) / 10000;
    }

    // round() in PHP: half away from zero.
    function roundHalfAway(value) {
        return value < 0 ? -Math.round(-value) : Math.round(value);
    }

    // erp_multiply_units(): amount x units / scale, half away from zero, in
    // whole numbers only. Both factors are split at the scale, so every step
    // is an exact integer below 2^53 while the result is, and a product of
    // floats never lands a hair beside a half (1310 * 1.15 is 1506.4999...).
    function multiplyUnits(amount, units, scale) {
        var negative = (amount < 0) !== (units < 0);
        amount = Math.abs(amount);
        units = Math.abs(units);
        var amountRest = amount % scale;
        var amountWhole = (amount - amountRest) / scale;
        var unitsRest = units % scale;
        var unitsWhole = (units - unitsRest) / scale;
        var rest = amountRest * unitsRest;
        var restRest = rest % scale;
        var result = (amountWhole * unitsWhole * scale) + (amountWhole * unitsRest)
            + (amountRest * unitsWhole) + ((rest - restRest) / scale);
        if (restRest * 2 >= scale) {
            result++;
        }
        return (negative && result !== 0) ? -result : result;
    }

    // erp_apply_rate(): the rate at three places, the exact product rounded.
    function applyRate(kurus, rate) {
        return multiplyUnits(kurus, decimalUnits(rate, 3), 100000);
    }

    // erp_line_total(): the quantity at four places, the exact product rounded.
    function lineTotal(unitPrice, quantity) {
        return multiplyUnits(unitPrice, decimalUnits(quantity, 4), 10000);
    }

    // erp_allocate(): the total split in proportion to the weights, each its
    // floor share, the kurus left over to the largest remainders - the first
    // of equal ones first, as PHP's stable arsort() leaves them. The shares
    // always add up to the total.
    function allocate(total, weights) {
        var shares = weights.map(function () { return 0; });
        var sum = 0;
        weights.forEach(function (weight) {
            sum += weight;
        });
        if (sum <= 0 || weights.length === 0) {
            if (weights.length > 0) {
                shares[0] = total;
            }
            return shares;
        }
        var remainders = [];
        var allocated = 0;
        weights.forEach(function (weight, index) {
            var exact = (total * weight) / sum;
            var floor = Math.floor(exact);
            shares[index] = floor;
            remainders.push({ index: index, value: exact - floor });
            allocated += floor;
        });
        var left = total - allocated;
        if (left !== 0) {
            var step = left > 0 ? 1 : -1;
            // Array.prototype.sort is stable, so equals keep their order.
            remainders.sort(function (a, b) {
                return b.value - a.value;
            });
            if (left < 0) {
                remainders.reverse();
            }
            var position = 0;
            while (left !== 0) {
                shares[remainders[position % remainders.length].index] += step;
                left -= step;
                position++;
            }
        }
        return shares;
    }

    // A rate as the server keys its groups by: three places, the rate
    // columns' own scale.
    function rateKey(rate) {
        return Number(rate).toFixed(3);
    }

    // erp_rate_vat(): each tax taken once on the sum of the lines at its
    // rate and spread back over them. The VAT per group of one VAT rate and
    // one withholding rate; the withholding is every group's VAT times its
    // rate, summed and rounded once, spread over the withheld lines by what
    // each withholds; the second tax per its own rate. Lines are
    // {net, taxRate, withholdingRate, tax2Rate}; the answer is per line
    // {vat, tax2, withholding}, in the same order.
    function rateTaxes(lines) {
        var out = lines.map(function () {
            return { vat: 0, tax2: 0, withholding: 0 };
        });
        var groups = [];
        var groupByKey = {};
        var tax2Groups = [];
        var tax2ByKey = {};

        lines.forEach(function (line, index) {
            // Nothing is withheld from a line that carries no VAT.
            var withholdingRate = line.taxRate > 0 ? line.withholdingRate : 0;
            var key = rateKey(line.taxRate) + '|' + rateKey(withholdingRate);
            if (!Object.prototype.hasOwnProperty.call(groupByKey, key)) {
                groupByKey[key] = groups.length;
                groups.push({ taxRate: line.taxRate, withholdingRate: withholdingRate, net: 0, members: [] });
            }
            var group = groups[groupByKey[key]];
            group.net += line.net;
            group.members.push(index);

            if (line.tax2Rate > 0) {
                var tax2Key = rateKey(line.tax2Rate);
                if (!Object.prototype.hasOwnProperty.call(tax2ByKey, tax2Key)) {
                    tax2ByKey[tax2Key] = tax2Groups.length;
                    tax2Groups.push({ rate: line.tax2Rate, net: 0, members: [] });
                }
                var tax2Group = tax2Groups[tax2ByKey[tax2Key]];
                tax2Group.net += line.net;
                tax2Group.members.push(index);
            }
        });

        var withheld = 0;
        var withheldLines = [];
        var withheldWeights = [];

        groups.forEach(function (group) {
            var vat = applyRate(group.net, group.taxRate);
            var shares = allocate(vat, group.members.map(function (index) {
                return lines[index].net;
            }));
            group.members.forEach(function (index, position) {
                out[index].vat = shares[position];
            });
            if (group.withholdingRate > 0) {
                withheld += vat * group.withholdingRate;
                // Whole weights: thousandths of a kurus keep a rate's three
                // places.
                group.members.forEach(function (index) {
                    withheldLines.push(index);
                    withheldWeights.push(roundHalfAway(out[index].vat * group.withholdingRate * 1000));
                });
            }
        });

        var withholdingTotal = roundHalfAway(withheld / 100);
        if (withholdingTotal !== 0) {
            allocate(withholdingTotal, withheldWeights).forEach(function (share, position) {
                out[withheldLines[position]].withholding = share;
            });
        }

        tax2Groups.forEach(function (group) {
            var tax2 = applyRate(group.net, group.rate);
            var shares = allocate(tax2, group.members.map(function (index) {
                return lines[index].net;
            }));
            group.members.forEach(function (index, position) {
                out[index].tax2 = shares[position];
            });
        });

        return out;
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
        describeSearch(row);
        tbody.appendChild(row);
        renumber();
        recalc();
        return row;
    }

    function removeLine(row) {
        if (openList && openList.row === row) {
            closeResults();
        }
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
            // Focus goes to the next line's product name, else the previous
            // line's, so it does not fall to the page top with the row.
            var list = rows();
            var neighbour = list[list.indexOf(row) + 1] || list[list.indexOf(row) - 1];
            var target = neighbour ? field(neighbour, 'product_name') : null;
            row.parentNode.removeChild(row);
            if (target) {
                target.focus();
            }
        }
        renumber();
        recalc();
    }

    function lineFigures(row) {
        // Priced at the places it is saved with, as the server prices it.
        var quantity = quantityRound(parseQuantity(field(row, 'quantity') ? field(row, 'quantity').value : ''));
        var unitPrice = parseKurus(field(row, 'unit_price') ? field(row, 'unit_price').value : '');
        var discountRate = parseDecimal(field(row, 'discount_rate') ? field(row, 'discount_rate').value : '');
        var offerRate = parseDecimal(field(row, 'offer_discount_rate') ? field(row, 'offer_discount_rate').value : '');
        var taxRate = parseDecimal(field(row, 'tax_rate') ? field(row, 'tax_rate').value : '');
        var tax2Rate = parseDecimal(field(row, 'tax2_rate') ? field(row, 'tax2_rate').value : '');

        // The campaign first, the typed discount on what it leaves; the same
        // order erp_manual_lines_build() uses. The taxes come from the
        // document's groups (rateTaxes()): a second tax, where the store
        // names one, is its own rate on the same net, and withholding stays a
        // share of the first.
        var total = lineTotal(unitPrice, quantity);
        var offer = offerRate > 0 ? applyRate(total, offerRate) : 0;
        var typed = discountRate > 0 ? applyRate(total - offer, discountRate) : 0;
        var discount = offer + typed;

        return {
            lineTotal: total,
            discount: discount,
            net: total - discount,
            taxRate: taxRate,
            tax2Rate: tax2Rate,
            withholdingRate: withholdingShare(row),
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
        var lineRows = rows();
        var figures = lineRows.map(lineFigures);
        var taxes = rateTaxes(figures);

        lineRows.forEach(function (row, index) {
            var line = figures[index];
            var lineTaxes = taxes[index];
            var cell = row.querySelector('[data-erp-line-total]');
            if (cell) {
                cell.textContent = line.isEmpty ? '' : formatMoney(line.net + lineTaxes.vat + lineTaxes.tax2 - lineTaxes.withholding);
            }
            subtotal += line.lineTotal;
            discountTotal += line.discount;
            taxTotal += lineTaxes.vat;
            tax2Total += lineTaxes.tax2;
            withholdingTotal += lineTaxes.withholding;
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

    // The open suggestion list: { menu, row, input, cell, next }. While open
    // the menu sits on the overlay host, not in its cell: the lines table's
    // .table-responsive scrolls and contains layout, so a list inside it is
    // clipped at the table's edge, even when placed fixed.
    var openList = null;

    // Where the list goes for a search box at rect, in a viewport of
    // width x height: under the box (above it when the room below is short
    // and there is more above), at least 320px wide but never past the
    // viewport's 8px margins, and at most 320px high.
    function resultsPlacement(rect, width, height) {
        var margin = 8;
        var gap = 2;
        var limit = 320;
        var listWidth = Math.min(Math.max(rect.width, limit), width - 2 * margin);
        var left = Math.max(margin, Math.min(rect.left, width - listWidth - margin));
        var below = height - rect.bottom - gap - margin;
        var above = rect.top - gap - margin;
        var place = { left: Math.round(left), width: Math.round(listWidth) };

        if (below < Math.min(limit, 200) && above > below) {
            place.above = true;
            place.bottom = Math.round(height - rect.top + gap);
            place.maxHeight = Math.round(Math.min(limit, above));
        } else {
            place.above = false;
            place.top = Math.round(rect.bottom + gap);
            place.maxHeight = Math.round(Math.min(limit, Math.max(below, 0)));
        }
        return place;
    }

    // The row's results menu, in its cell or on the overlay host.
    function resultsMenu(row) {
        if (openList && openList.row === row) {
            return openList.menu;
        }
        return row.querySelector('[data-erp-product-results]');
    }

    function placeResults() {
        if (!openList) {
            return;
        }
        var rect = openList.input.getBoundingClientRect();
        var height = window.innerHeight || document.documentElement.clientHeight;
        if (rect.bottom < 0 || rect.top > height) {
            closeResults();
            return;
        }
        var place = resultsPlacement(rect, document.documentElement.clientWidth || window.innerWidth, height);
        var style = openList.menu.style;
        style.position = 'fixed';
        style.left = place.left + 'px';
        style.width = place.width + 'px';
        style.top = place.above ? 'auto' : (place.top + 'px');
        style.bottom = place.above ? (place.bottom + 'px') : 'auto';
        style.setProperty('--bs-menu-max-height', place.maxHeight + 'px');
        style.setProperty('--bs-menu-overflow-y', 'auto');
    }

    function openResults(row, input, menu) {
        if (!openList || openList.menu !== menu) {
            closeResults();
            openList = { menu: menu, row: row, input: input, cell: menu.parentNode, next: menu.nextSibling };
            var host = (window.pgUI && typeof window.pgUI.overlayHost === 'function') ? window.pgUI.overlayHost(input) : document.body;
            (host || document.body).appendChild(menu);
        }
        menu.classList.add('show');
        input.setAttribute('aria-expanded', 'true');
        placeResults();
    }

    // Closes the open list and puts its menu back in its cell.
    function closeResults(except) {
        if (!openList || openList.menu === except) {
            return;
        }
        var list = openList;
        openList = null;
        list.menu.classList.remove('show');
        list.menu.innerHTML = '';
        list.menu.removeAttribute('style');
        list.input.setAttribute('aria-expanded', 'false');
        if (list.cell) {
            list.cell.insertBefore(list.menu, (list.next && list.next.parentNode === list.cell) ? list.next : null);
        }
    }

    // The search box names its list and says whether it is open.
    function describeSearch(row) {
        var input = row.querySelector('.erp-product-search');
        var menu = row.querySelector('[data-erp-product-results]');
        if (!input || !menu || !menu.id) {
            return;
        }
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', menu.id);
        input.setAttribute('aria-expanded', 'false');
    }

    // Emptied first and filled a moment later, so the same sentence twice in
    // a row is read again.
    function announce(text) {
        window.clearTimeout(announceTimer);
        liveStatus.textContent = '';
        announceTimer = window.setTimeout(function () {
            liveStatus.textContent = text;
        }, 150);
    }

    function resultItems() {
        return openList ? Array.prototype.slice.call(openList.menu.querySelectorAll('.menu-item')) : [];
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

    // A rate as the panel language writes it: '%12,5' in Turkish, '12.5%' in English.
    function percentText(rate) {
        return fill(texts.percent, trimNumber(Number(rate).toFixed(3)).replace('.', decimalMark));
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
        // Where the VAT rate came from: the product has none of its own.
        if (row.dataset.erpZoneRate === '1' && texts.zoneRate) {
            parts.push(texts.zoneRate);
        }

        hint.textContent = parts.join(' \u00b7 ');
        hint.className = 'form-text small mt-1' + (warning ? ' fg-emphasis-warning' : '');
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
            termsText = fill(texts.accountDiscount, percentText(terms.discount_rate));
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
        row.dataset.erpOffer = offer ? ('\u2212' + percentText(offer.rate)) : '';
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

    function renderResults(row, input, menu, products) {
        menu.innerHTML = '';

        if (!products.length) {
            var empty = document.createElement('span');
            empty.className = 'menu-text fg-secondary small';
            empty.textContent = textNoResults;
            menu.appendChild(empty);
            announce(textNoResults);
        }

        products.forEach(function (product) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'menu-item text-truncate no-submit';

            var name = document.createElement('span');
            name.textContent = product.name;
            item.appendChild(name);

            if (product.sku && product.sku !== product.name) {
                var sku = document.createElement('span');
                sku.className = 'fg-secondary small ms-3';
                sku.textContent = product.sku;
                item.appendChild(sku);
            }

            var price = document.createElement('span');
            price.className = 'fg-secondary small ms-3';
            price.textContent = formatPrice(product.price);
            item.appendChild(price);

            if (product.stock !== null && product.stock !== undefined) {
                item.appendChild(product.stock > 0
                    ? badge(fill(texts.stock, product.stock), 'bg-1 fg-body border')
                    : badge(texts.outOfStock, 'theme-warning'));
            }
            if (product.enabled === false && texts.disabled) {
                item.appendChild(badge(texts.disabled, 'theme-secondary'));
            }
            if (product.offer && !isPurchase()) {
                item.appendChild(badge('\u2212' + percentText(product.offer.rate), 'theme-success'));
            }

            item.addEventListener('click', function () {
                fillFromProduct(row, product);
                closeResults();
                input.focus();
            });
            menu.appendChild(item);
        });

        if (!menu.erpKeys) {
            menu.erpKeys = true;
            menu.addEventListener('keydown', resultsKeydown);
        }
        openResults(row, input, menu);
    }

    // Off the row, the list is past the end of the page in the tab order:
    // the arrows move through it, Escape and Tab go back to the search box.
    function resultsKeydown(event) {
        if (!openList) {
            return;
        }
        var input = openList.input;
        var items = resultItems();
        var at = items.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (event.key === 'ArrowUp' && at <= 0) {
                input.focus();
            } else if (items.length) {
                items[Math.min(items.length - 1, Math.max(0, at + (event.key === 'ArrowDown' ? 1 : -1)))].focus();
            }
        } else if (event.key === 'Escape' || event.key === 'Tab') {
            event.preventDefault();
            closeResults();
            input.focus();
        }
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

    // Marks the barcode box with its "not found" message under it, or clears
    // both; the class alone when the panel helper is missing.
    function markBarcode(on) {
        if (typeof window.pgFieldInvalid === 'function') {
            window.pgFieldInvalid(barcodeInput, on, texts.notFound);
        } else {
            barcodeInput.classList.toggle('is-invalid', on);
        }
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
                    markBarcode(false);
                    barcodeInput.value = '';
                    placeProduct(data.product);
                } else {
                    markBarcode(true);
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
        var menu = resultsMenu(row);
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
            closeResults();
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
                    renderResults(row, input, menu, (data && data.results) ? data.results : []);
                })
                .catch(function () {
                    closeResults();
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
            markBarcode(false);
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

    rows().forEach(describeSearch);

    tbody.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeResults();
        }
        if (event.key === 'ArrowDown' && openList && event.target === openList.input && resultItems().length) {
            event.preventDefault();
            resultItems()[0].focus();
            return;
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

    // Focus leaving the search box for anything but its list closes it.
    document.addEventListener('focusin', function (event) {
        if (openList && event.target !== openList.input && !openList.menu.contains(event.target)) {
            closeResults();
        }
    });

    // The list follows its box when the page scrolls or resizes; a scroll
    // of the lines table closes it, as the box may have moved out of sight
    // under the table's edge. The list's own scroll is left alone.
    window.addEventListener('resize', placeResults);
    window.addEventListener('scroll', function (event) {
        if (!openList) {
            return;
        }
        var target = event.target;
        if (target === document || target === document.documentElement || target === document.body) {
            placeResults();
        } else if (target.contains && target.contains(openList.input)) {
            if (target === openList.input.closest('.table-responsive')) {
                closeResults();
            } else {
                placeResults();
            }
        }
    }, true);

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
