/**
 * PineGrap — product builder (add_product2.php)
 *
 * Owns the client half of one rule:
 *
 *   more than one attribute combination -> a product group plus one product
 *                                          per combination
 *   one combination or none             -> a single product, no group
 *
 * The PHP twin of that rule is pg_pb_mode() in product_builder.php. If the
 * threshold ever changes it has to change in both places, otherwise the summary
 * line promises one thing and the server does another.
 *
 * All user-visible strings arrive from PHP through window.PinegrapProductBuilder
 * so they stay inside lang().
 */
(function ($) {
    'use strict';

    var cfg    = window.PinegrapProductBuilder || {};
    var labels = cfg.labels || {};
    var symbol = cfg.currencySymbol || '';

    var $form = $('#pg_pb_form');

    if (!$form.length) {
        return;
    }

    var PREVIEW_LIMIT = 24;

    /* ------------------------------------------------------------- helpers */

    function esc(value) {
        return $('<div>').text(value === undefined || value === null ? '' : value).html();
    }

    function label(key) {
        return labels[key] !== undefined ? labels[key] : key;
    }

    function imagePath(filename) {
        filename = filename || '';
        if (/^https?:\/\//i.test(filename)) {
            return filename;
        }
        return (typeof path !== 'undefined' ? path : '') + filename;
    }

    /* ------------------------------------------------- section navigation */

    /* The screen is long enough that "where are the SEO fields" is a real
       question. The nav answers it, and the active state answers "where am I". */
    (function sectionNav() {

        var $links = $('#pg_pb_nav [data-pg-pb-nav]');

        if (!$links.length) {
            return;
        }

        $links.on('click', function (event) {
            var target = document.getElementById($(this).attr('data-pg-pb-nav'));
            if (target) {
                event.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        if (!('IntersectionObserver' in window)) {
            return;
        }

        var visible = {};

        var observer = new IntersectionObserver(function (entries) {

            entries.forEach(function (entry) {
                visible[entry.target.id] = entry.isIntersecting;
            });

            /* Highest section currently on screen wins — scrolling down through
               a tall card should not flicker the highlight between neighbours. */
            var active = null;

            $links.each(function () {
                var id = $(this).attr('data-pg-pb-nav');
                if (active === null && visible[id]) {
                    active = id;
                }
            });

            $links.removeClass('pg-pb-nav-active');

            if (active) {
                $links.filter('[data-pg-pb-nav="' + active + '"]').addClass('pg-pb-nav-active');
            }

        }, { rootMargin: '-10% 0px -70% 0px', threshold: 0 });

        $links.each(function () {
            var target = document.getElementById($(this).attr('data-pg-pb-nav'));
            if (target) {
                observer.observe(target);
            }
        });
    }());

    /* --------------------------------------------------- main image picker */

    /* The cover image is the first one in the list, which is invisible unless
       we say so — and the list is sortable, so "first" changes. */
    function refreshCoverBadge() {

        var $items = $('#software_image_picker_container .item');

        $items.find('.pg-pb-cover-badge').remove();

        if ($items.length) {
            $items.first().append(
                '<span class="badge text-bg-primary pg-pb-cover-badge">' + esc(label('Cover')) + '</span>');
        }

        $('#pg_pb_image_empty').toggleClass('d-none', $items.length > 0);
    }

    (function mainImages() {

        var $container = $('#software_image_picker_container');

        if (!$container.length) {
            return;
        }

        if ($.fn.sortable) {
            $container.sortable({
                items: '> .item',
                placeholder: 'col',
                revert: 100,
                tolerance: 'pointer',
                cursor: 'move',
                update: refreshCoverBadge
            });
        }

        /* The picker appends tiles from outside this file and the remove button
           is an inline onclick, so neither fires anything we can hook. Watch the
           container instead of guessing when it changed. */
        if ('MutationObserver' in window) {
            new MutationObserver(refreshCoverBadge).observe($container[0], { childList: true });
        }

        refreshCoverBadge();
    }());

    /* -------------------------------------------------- per-variant images */

    function imageChip(filename) {
        return '<span class="pg-pb-chip" title="' + esc(filename) + '">' +
            '<img src="' + esc(imagePath(filename)) + '" alt="" onerror="this.style.visibility=\'hidden\'" />' +
            '<button type="button" class="pg-pb-chip-remove" tabindex="-1" title="' + esc(label('Remove')) + '"><i class="bi bi-x-lg"></i></button>' +
            '<input type="hidden" class="pg-pb-chip-input" value="' + esc(filename) + '" />' +
            '</span>';
    }

    function imageAddChip() {
        return '<button type="button" class="btn btn-sm btn-outline-secondary pg-pb-chip-add" title="' + esc(label('Add Image')) + '">' +
            '<i class="bi bi-plus-lg"></i></button>';
    }

    /* The shared picker returns its result to whichever list last asked for it.
       Without this it always writes to the main container, because that is the
       only target the original implementation knows about. */
    var pickerTarget  = null;
    var pickerClaimed = false;

    (function patchImagePicker() {

        if (typeof window.software_image_picker !== 'function') {
            return;
        }

        var original = window.software_image_picker;

        window.software_image_picker = function (props) {

            if (props.initialize !== undefined) {
                if (!pickerClaimed) {
                    pickerTarget = null;
                }
                pickerClaimed = false;
                original.call(this, props);
                return;
            }

            if (props.return !== undefined && pickerTarget) {

                var filename = decodeURIComponent(props.image_name || '');

                if (filename) {
                    var duplicate = false;

                    pickerTarget.find('.pg-pb-chip-input').each(function () {
                        if ($(this).val() === filename) {
                            duplicate = true;
                            return false;
                        }
                    });

                    if (!duplicate) {
                        pickerTarget.find('.pg-pb-chip-add').before(imageChip(filename));
                    }
                }
                return;
            }

            original.call(this, props);
        };
    }());

    $('#pg_pb_matrix').on('click', '.pg-pb-chip-add', function () {
        pickerTarget  = $(this).closest('.pg-pb-strip');
        pickerClaimed = true;
        window.software_image_picker({ initialize: true });
    });

    $('#pg_pb_matrix').on('click', '.pg-pb-chip-remove', function () {
        $(this).closest('.pg-pb-chip').remove();
    });

    /* ------------------------------------------------- reading the picker */

    /* One entry per attribute with at least one option ticked, in the order the
       attribute cards currently sit in the DOM. Order matters: it is the order
       the variant picker renders on the product page. */
    function selectedDimensions() {

        var dimensions = [];

        $('.pg-pb-attribute').each(function () {

            var $card   = $(this);
            var options = [];

            $card.find('.pg-pb-option:checked').each(function () {
                options.push({
                    attr_id:   String($card.data('attr-id')),
                    attr_name: String($card.data('attr-name')),
                    option_id: $(this).val(),
                    label:     $(this).data('label')
                });
            });

            if (options.length) {
                dimensions.push(options);
            }
        });

        return dimensions;
    }

    function cartesian(dimensions) {

        if (!dimensions.length) {
            return [];
        }

        var result = [[]];

        for (var i = 0; i < dimensions.length; i++) {
            var next = [];
            for (var j = 0; j < result.length; j++) {
                for (var k = 0; k < dimensions[i].length; k++) {
                    next.push(result[j].concat([dimensions[i][k]]));
                }
            }
            result = next;
        }

        return result;
    }

    /* Stable identity for a combination, used to carry typed values across a
       regeneration. Option ids are already unique across attributes. */
    function comboKey(combo) {
        return combo.map(function (part) { return part.option_id; }).join('|');
    }

    function comboLabel(combo) {
        return combo.map(function (part) { return part.label; }).join(' / ');
    }

    /* Token matching is deliberately forgiving.
       The operator types the attribute name from memory into a free text box:
       "Ofis Koltuğu Rengi" against a stored "Ofis Koltuğu rengi" is the same
       intent, and an exact string compare answers "no" and leaves {Ofis Koltuğu
       Rengi} sitting in the description. Case, surrounding space and repeated
       inner spaces are all normalised away.

       toLocaleLowerCase('tr') matters here: the ASCII lowercase of "İ" is not
       "i", so a Turkish attribute name would fail to match itself. */
    function normalizeToken(value) {
        return String(value === undefined || value === null ? '' : value)
            .trim()
            .replace(/\s+/g, ' ')
            .toLocaleLowerCase('tr');
    }

    function comboTokenMap(combo) {

        var map = {};

        for (var i = 0; i < combo.length; i++) {
            map[normalizeToken(combo[i].attr_name)] = combo[i].label;
        }

        return map;
    }

    /* Replace every {token} the combination knows about. Tokens it does not
       know are left visible on purpose — a silently emptied placeholder hides
       the fact that the attribute is not among the ones being varied. */
    function applyTemplate(template, combo) {

        var map = comboTokenMap(combo);

        return String(template).replace(/\{([^{}]*)\}/g, function (whole, token) {
            var key = normalizeToken(token);
            return (map[key] !== undefined) ? map[key] : whole;
        });
    }

    /* SKU suffix from the template, e.g. "{Renk}-{Beden}" -> "Kirmizi-S".
       With no template the option labels are joined with a dash. */
    function comboSku(template, combo) {

        template = (template || '').trim();

        if (template === '') {
            return combo.map(function (part) { return part.label; }).join('-');
        }

        return applyTemplate(template, combo);
    }

    function fullSku(base, suffix) {
        base = (base || '').trim();
        return base ? (base + '-' + suffix) : suffix;
    }

    function variantShortDescription(combo) {

        var template = ($('#short_description_template').val() || '').trim();

        if (template !== '') {
            return applyTemplate(template, combo);
        }

        /* No template: the product's own description plus what makes this row
           different. Bare "Kırmızı / S" tells a customer nothing on its own. */
        var base = ($('#short_description').val() || '').trim();

        return base ? (base + ' ' + comboLabel(combo)) : comboLabel(combo);
    }

    /* The images picked for the product as a whole. */
    function mainImages() {

        var images = [];

        $('#software_image_picker_container input[name="selected_images[]"]').each(function () {
            images.push($(this).val());
        });

        return images;
    }

    /* ------------------------------------------------------ attribute chips */

    /* Per-card state: how many options are ticked, whether the default-option
       select is worth showing, and what the "select all" button should say. */
    function refreshAttributeCards() {

        $('.pg-pb-attribute').each(function () {

            var $card    = $(this);
            var total    = $card.find('.pg-pb-option').length;
            var selected = $card.find('.pg-pb-option:checked').length;

            $card.find('.pg-pb-attr-count')
                .text(selected)
                .toggleClass('d-none', selected === 0);

            /* The default option only means anything for an attribute this
               product actually varies on. */
            $card.find('.pg-pb-attr-default-wrap')
                .toggleClass('d-none', selected === 0)
                .toggleClass('d-flex', selected > 0);

            $card.find('.pg-pb-attr-toggle-all')
                .text(selected === total ? label('Clear') : label('Select All'));
        });
    }

    /* What the current selection would produce, before anything is generated.
       Seeing "Kırmızı / S · Kırmızı / M · ..." is what makes the count on the
       summary line concrete. */
    function refreshComboPreview(combos) {

        var $preview = $('#pg_pb_combo_preview');

        if (!$preview.length) {
            return;
        }

        if (combos.length < 2) {
            $preview.addClass('d-none').empty();
            return;
        }

        var html = '';

        for (var i = 0; i < combos.length && i < PREVIEW_LIMIT; i++) {
            html += '<span class="badge text-bg-light border fw-normal">' + esc(comboLabel(combos[i])) + '</span>';
        }

        if (combos.length > PREVIEW_LIMIT) {
            html += '<span class="badge text-bg-secondary fw-normal">+' + (combos.length - PREVIEW_LIMIT) + '</span>';
        }

        $preview.removeClass('d-none').html(
            '<div class="form-label small text-muted mb-1">' + esc(label('Combinations')) + '</div>' +
            '<div class="d-flex flex-wrap gap-1">' + html + '</div>');
    }

    /* ------------------------------------------------------ matrix render */

    /* Values the operator typed, keyed by combination, so ticking one more
       option does not wipe the prices already entered. */
    var typedValues = {};

    function captureTypedValues() {

        $('#pg_pb_matrix .pg-pb-variant').each(function () {

            var $row   = $(this);
            var images = [];

            $row.find('.pg-pb-chip-input').each(function () {
                images.push($(this).val());
            });

            typedValues[$row.data('combo-key')] = {
                name:               $row.find('.pg-pb-v-name').val(),
                short_description:  $row.find('.pg-pb-v-short').val(),
                price:              $row.find('.pg-pb-v-price').val(),
                inventory_quantity: $row.find('.pg-pb-v-stock').val(),
                images:             images,
                touched:            $row.data('touched') === true
            };
        });
    }

    function cellLabel(text) {
        return '<span class="d-lg-none d-block text-muted small">' + esc(text) + '</span>';
    }

    /* -------------------------------------------- per-variant recurring */

    /* Recurring is a product-wide switch, but the schedule is not: a yearly
       licence and a monthly one can be two variants of the same product. The
       toggle only exists while recurring is on — an "advanced settings" button
       that opens a panel about a feature the product does not use is furniture. */
    function recurringIsOn() {
        return $('#recurring').is(':checked');
    }

    /* ClearCommerce has no start delay, so the main form does not render the
       field. The per-variant panel follows whatever the main form offers rather
       than deciding for itself. */
    function hasStartField() {
        return $('#start').length > 0;
    }

    function advancedToggle(index) {

        if (!recurringIsOn()) {
            return '';
        }

        return '<div class="mt-1">' +
            '<button type="button" class="btn btn-sm btn-link text-decoration-none p-0 pg-pb-adv-toggle" data-pg-pb-adv="' + index + '">' +
            '<i class="bi bi-chevron-down me-1"></i>' + esc(label('Advanced Settings')) +
            '</button></div>';
    }

    function advancedRow(index, defaults) {

        if (!recurringIsOn()) {
            return '';
        }

        var startField = '';

        if (hasStartField()) {
            startField =
                '<div class="col-12 col-sm-6 col-lg-3">' +
                '<label class="form-label small">' + esc(label('Start (days)')) + '</label>' +
                '<input type="number" min="0" class="form-control form-control-sm pg-pb-v-start" value="' + esc(defaults.start) + '" />' +
                '</div>';
        }

        /* A second <tr>, hidden with d-none rather than Bootstrap collapse.
           Collapse animates height on the element it is applied to, and a <tr>
           with height:0 and overflow:hidden renders differently in every
           browser. The cell keeps its colspan and is deliberately NOT given
           d-block: a cell taken out of table layout loses colspan entirely
           (CLAUDE.md, "Tablo Widget'larında Dar Ekran"). */
        return '<tr class="pg-pb-adv-row d-none" data-pg-pb-adv="' + index + '">' +
            '<td colspan="6" class="bg-body-tertiary">' +
            '<div class="row g-2 align-items-end">' +

            '<div class="col-12">' +
            '<div class="form-check form-switch">' +
            '<input class="form-check-input pg-pb-v-editable" type="checkbox" role="switch" id="pg_pb_v_editable_' + index + '" />' +
            '<label class="form-check-label small" for="pg_pb_v_editable_' + index + '">' + esc(label('Allow customer to set schedule')) + '</label>' +
            '</div></div>' +

            '<div class="col-12 col-sm-6 col-lg-3">' +
            '<label class="form-label small">' + esc(label('Number of Payments')) + '</label>' +
            '<input type="number" min="0" class="form-control form-control-sm pg-pb-v-payments" value="' + esc(defaults.payments) + '" />' +
            '</div>' +

            '<div class="col-12 col-sm-6 col-lg-3">' +
            '<label class="form-label small">' + esc(label('Payment Period')) + '</label>' +
            '<select class="form-select form-select-sm pg-pb-v-period">' + defaults.periodOptions + '</select>' +
            '</div>' +

            startField +

            '</div></td></tr>';
    }

    $(document).on('click', '.pg-pb-adv-toggle', function () {

        var index = $(this).attr('data-pg-pb-adv');
        var $row  = $('#pg_pb_matrix .pg-pb-adv-row[data-pg-pb-adv="' + index + '"]');
        var open  = $row.hasClass('d-none');

        $row.toggleClass('d-none', !open);
        $(this).find('i').attr('class', open ? 'bi bi-chevron-up me-1' : 'bi bi-chevron-down me-1');
    });

    function variantRow(index, combo, defaults) {

        var key   = comboKey(combo);
        var saved = typedValues[key] || {};

        var suffix = comboSku($('#sku_template').val(), combo);
        var sku    = saved.touched ? saved.name : fullSku(defaults.baseSku, suffix);
        var short  = saved.touched ? saved.short_description : variantShortDescription(combo);
        var price  = saved.price !== undefined ? saved.price : defaults.price;
        var stock  = saved.inventory_quantity !== undefined ? saved.inventory_quantity : defaults.stock;
        var images = saved.images || [];

        var attributes = combo.map(function (part) {
            return { attribute_id: part.attr_id, option_id: part.option_id };
        });

        var chips = '';

        for (var i = 0; i < images.length; i++) {
            chips += imageChip(images[i]);
        }

        return '' +
            '<tr class="pg-pb-variant d-block d-lg-table-row"' +
            ' data-pg-pb-adv="' + index + '"' +
            ' data-combo-key="' + esc(key) + '"' +
            ' data-combo-suffix="' + esc(suffix) + '"' +
            ' data-combo=\'' + JSON.stringify(combo).replace(/'/g, '&#39;') + '\'' +
            ' data-attributes=\'' + JSON.stringify(attributes).replace(/'/g, '&#39;') + '\'>' +

            '<td class="d-block d-lg-table-cell align-middle">' +
            '<span class="badge text-bg-secondary me-1">' + (index + 1) + '</span>' +
            '<span class="fw-semibold">' + esc(comboLabel(combo)) + '</span>' +
            advancedToggle(index) +
            '</td>' +

            '<td class="d-block d-lg-table-cell align-middle">' +
            cellLabel(label('Product ID / SKU')) +
            '<input type="text" class="form-control form-control-sm pg-pb-v-name" value="' + esc(sku) + '" />' +
            '</td>' +

            '<td class="d-block d-lg-table-cell align-middle">' +
            cellLabel(label('Short Description')) +
            '<input type="text" class="form-control form-control-sm pg-pb-v-short" value="' + esc(short) + '" />' +
            '</td>' +

            '<td class="d-block d-lg-table-cell align-middle">' +
            cellLabel(label('Unit Price')) +
            '<div class="input-group input-group-sm">' +
            '<input type="number" step="0.01" min="0" class="form-control pg-pb-v-price" value="' + esc(price) + '" />' +
            /* Not escaped: the currency symbol is a setting, and for most
               non-Latin currencies it is stored as an HTML entity ("&#8378;"
               for the lira). Escaping it prints the entity instead of the
               symbol. Every legacy screen outputs it raw. */
            '<span class="input-group-text">' + symbol + '</span>' +
            '</div></td>' +

            '<td class="d-block d-lg-table-cell align-middle">' +
            cellLabel(label('Inventory Quantity')) +
            '<input type="number" min="0" class="form-control form-control-sm pg-pb-v-stock" value="' + esc(stock) + '" />' +
            '</td>' +

            '<td class="d-block d-lg-table-cell align-middle">' +
            cellLabel(label('Images')) +
            '<div class="pg-pb-strip">' + chips + imageAddChip() + '</div>' +
            '</td>' +

            '</tr>' +

            advancedRow(index, defaults);
    }

    function currentDefaults() {
        return {
            baseSku:       $('#name').val(),
            price:         $('#price').val() || '0',
            stock:         $('#inventory_quantity').val() || '',
            /* The advanced panel starts from the product-wide schedule, so a
               variant the operator never opens matches what the main form says. */
            payments:      $('#number_of_payments').val() || '',
            start:         $('#start').val() || '0',
            periodOptions: $('#payment_period').html() || ''
        };
    }

    /* The template boxes are free text, so the operator has to know which
       tokens exist right now. Listing them removes the guessing, and their
       absence answers the other half of the question: an attribute with no
       ticked options is not part of any combination, so its token cannot be
       substituted and will not appear here. */
    function refreshTokenHints(dimensions) {

        var html = '';

        for (var i = 0; i < dimensions.length; i++) {
            var name = dimensions[i][0].attr_name;
            html += '<button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 pg-pb-token" ' +
                'data-pg-pb-token="' + esc(name) + '">{' + esc(name) + '}</button>';
        }

        $('.pg-pb-tokens').html(html);
    }

    /* Insert a token at the caret rather than appending: templates are usually
       built by typing around the tokens. */
    $(document).on('click', '.pg-pb-token', function () {

        var $box   = $(this).closest('.pg-pb-tokens');
        var target = document.getElementById($box.attr('data-pg-pb-target'));

        if (!target) {
            return;
        }

        var token = '{' + $(this).attr('data-pg-pb-token') + '}';
        var start = (target.selectionStart !== null) ? target.selectionStart : target.value.length;
        var end   = (target.selectionEnd !== null) ? target.selectionEnd : target.value.length;

        target.value = target.value.slice(0, start) + token + target.value.slice(end);
        target.focus();
        target.setSelectionRange(start + token.length, start + token.length);

        $(target).trigger('input');
    });

    function renderMatrix() {

        captureTypedValues();
        refreshAttributeCards();

        var dimensions = selectedDimensions();
        var combos     = cartesian(dimensions);

        refreshTokenHints(dimensions);

        updateSummary(combos.length);
        refreshComboPreview(combos);

        if (combos.length < 2) {
            $('#pg_pb_matrix').empty();
            $('#pg_pb_matrix_wrapper').addClass('d-none');
            return combos;
        }

        var defaults = currentDefaults();
        var html     = '';

        for (var i = 0; i < combos.length; i++) {
            html += variantRow(i, combos[i], defaults);
        }

        $('#pg_pb_matrix').html(html);
        $('#pg_pb_variant_count').text(combos.length);
        $('#pg_pb_matrix_wrapper').removeClass('d-none');

        return combos;
    }

    /* ----------------------------------------------------------- summary */

    /* The screen has to say plainly what the save button will do. A form that
       silently creates a catalog group is how an operator ends up with a
       catalog full of one-product groups they never asked for. */
    function updateSummary(comboCount) {

        var $box  = $('#pg_pb_summary');
        var $icon = $('#pg_pb_summary_icon');
        var $text = $('#pg_pb_summary_text');

        if (comboCount > 1) {
            $box.removeClass('alert-secondary').addClass('alert-primary');
            $icon.attr('class', 'bi bi-diagram-3 me-2 fs-5');
            $text.text(label('group_summary').replace('{count}', comboCount));
            $('.pg-pb-group-only').removeClass('d-none');
            $('.pg-pb-single-only').addClass('d-none');
        } else {
            $box.removeClass('alert-primary').addClass('alert-secondary');
            $icon.attr('class', 'bi bi-box me-2 fs-5');
            $text.text(label('single_summary'));
            $('.pg-pb-group-only').addClass('d-none');
            $('.pg-pb-single-only').removeClass('d-none');
        }
    }

    /* ------------------------------------------------------- interactions */

    $(document).on('change', '.pg-pb-option', renderMatrix);
    $('#sku_template, #short_description_template').on('input', renderMatrix);

    /* The list of consequences only makes sense once there are days to grant.
       At zero none of it happens, and a list describing a thing that does not
       occur is worse than no list. */
    $('#membership_renewal').on('input', function () {

        var days = parseInt($(this).val(), 10);

        if (!days || days < 1) {
            $('#pg_pb_membership_effects').addClass('d-none');
            return;
        }

        $('#pg_pb_membership_summary').text(label('membership_summary').replace('{days}', days));
        $('#pg_pb_membership_effects').removeClass('d-none');
    }).trigger('input');

    /* The save button says where it goes. With a product form switched on the
       save cannot finish here — the fields are drawn on the next screen — so the
       button stops promising that it is the last step. */
    $('#product_form').on('change', function () {
        $('#pg_pb_create .btn-text').text(this.checked ? label('Create & Continue') : label('Create'));
    }).trigger('change');

    /* Turning the variants switch off has to actually clear the selection.
       Leaving the ticks in place behind a collapsed panel would mean a hidden
       control still decides whether a catalog group gets created — the operator
       switched variants off and would get a variant group anyway. */
    $('#pg_pb_has_variants').on('change', function () {
        if (!this.checked) {
            $('.pg-pb-option').prop('checked', false);
            renderMatrix();
        }
    });

    /* Turning recurring on or off changes what a variant row offers, so the
       matrix is rebuilt. Typed prices and SKUs survive — captureTypedValues()
       carries them across by combination. */
    $('#recurring').on('change', renderMatrix);

    $(document).on('click', '.pg-pb-attr-toggle-all', function () {
        var $card = $(this).closest('.pg-pb-attribute');
        var all   = $card.find('.pg-pb-option').length === $card.find('.pg-pb-option:checked').length;
        $card.find('.pg-pb-option').prop('checked', !all);
        renderMatrix();
    });

    /* Once a SKU or description is edited by hand, stop overwriting it when the
       matrix regenerates. */
    $('#pg_pb_matrix').on('input', '.pg-pb-v-name, .pg-pb-v-short', function () {
        $(this).closest('.pg-pb-variant').data('touched', true);
    });

    /* Base SKU and description feed untouched variant rows. Rebuilding the
       whole matrix on every keystroke would blow away focus, so only the two
       derived fields are rewritten. */
    $('#name, #short_description').on('input', function () {

        var defaults = currentDefaults();

        $('#pg_pb_matrix .pg-pb-variant').each(function () {

            var $row = $(this);

            if ($row.data('touched') === true) {
                return;
            }

            $row.find('.pg-pb-v-name').val(fullSku(defaults.baseSku, $row.data('combo-suffix')));

            var combo = $row.data('combo');

            if (combo) {
                $row.find('.pg-pb-v-short').val(variantShortDescription(combo));
            }
        });
    });

    /* Delegated from document: these three buttons live inside the variant
       switch panel, which is markup this file does not own and may be
       re-rendered. A direct binding made at load survives nothing. */
    $(document).on('click', '#pg_pb_apply_price', function () {
        $('#pg_pb_matrix .pg-pb-v-price').val($('#price').val() || '0');
    });

    $(document).on('click', '#pg_pb_apply_stock', function () {
        $('#pg_pb_matrix .pg-pb-v-stock').val($('#inventory_quantity').val() || '');
    });

    /* Copy the product's images onto every variant row.
       The server already falls back to these when a row has none, but a variant
       that shows the shared photo only because nothing was set is a different
       thing from one that owns it — and only the second survives editing a
       single variant later. */
    $(document).on('click', '#pg_pb_apply_images', function () {

        var images = mainImages();

        if (!images.length) {
            notifyError(label('no_images_to_apply'));
            return;
        }

        var chips = images.map(function (filename) { return imageChip(filename); }).join('');
        var rows  = 0;

        $('#pg_pb_matrix .pg-pb-strip').each(function () {
            var $strip = $(this);
            $strip.find('.pg-pb-chip').remove();
            $strip.find('.pg-pb-chip-add').before(chips);
            rows++;
        });

        /* Say it worked. Copying an image the operator is already looking at
           into rows they may have scrolled past otherwise looks like nothing
           happened. */
        if (rows && typeof window.pgToast === 'function') {
            window.pgToast({
                message: label('images_applied').replace('{count}', rows),
                variant: 'success'
            });
        }
    });

    /* Attribute ordering — the order these cards sit in is the order the
       variant picker renders on the product page. */
    $(document).on('click', '.pg-pb-attr-up', function () {
        var $card = $(this).closest('.pg-pb-attribute');
        var $prev = $card.prev('.pg-pb-attribute');
        if ($prev.length) {
            $card.insertBefore($prev);
            renderMatrix();
        }
    });

    $(document).on('click', '.pg-pb-attr-down', function () {
        var $card = $(this).closest('.pg-pb-attribute');
        var $next = $card.next('.pg-pb-attribute');
        if ($next.length) {
            $card.insertAfter($next);
            renderMatrix();
        }
    });

    /* -------------------------------------------------------- gift card */

    /* The consequences only apply once the switch is on. Listing them beside a
       switch that is off would describe something that does not happen. */
    $('#gift_card').on('change', function () {
        $('#pg_pb_giftcard_effects').toggleClass('d-none', !this.checked);
    }).trigger('change');

    /* ^^code^^ and friends are replaced by submit_order.php and appear nowhere
       else in the interface. Clicking inserts at the caret, because these go
       inside a sentence rather than at the end of one. */
    $(document).on('click', '.pg-pb-giftvar', function () {

        var $box   = $(this).closest('[data-pg-pb-giftvar-target]');
        var target = document.getElementById($box.attr('data-pg-pb-giftvar-target'));

        if (!target) {
            return;
        }

        var token = '^^' + $(this).attr('data-pg-pb-giftvar') + '^^';
        var start = (target.selectionStart !== null) ? target.selectionStart : target.value.length;
        var end   = (target.selectionEnd !== null) ? target.selectionEnd : target.value.length;

        target.value = target.value.slice(0, start) + token + target.value.slice(end);
        target.focus();
        target.setSelectionRange(start + token.length, start + token.length);
    });

    /* ----------------------------------------- google product category */

    /* The taxonomy is thousands of categories, so none of it is in the page:
       select2 asks the server as you type. tags:true keeps the field usable as
       free text — a value already stored, or a category newer than the cached
       list, must still be typeable. */
    (function googleCategory() {

        var $field = $('#google_product_category');

        if (!$field.length || !$.fn.select2) {
            return;
        }

        $field.select2({
            /* Without the Bootstrap 5 theme select2 renders its own default
               skin: a thinner control with a white background that does not
               match the form-select fields beside it, and ignores dark mode.
               Every other select2 on this screen gets the theme from the
               shared initialiser in backend.src.js; this one is set up here,
               so it has to ask for it itself. */
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            tags: true,
            placeholder: label('Google Product Category'),
            minimumInputLength: 2,
            ajax: {
                url: 'google_taxonomy_action.php',
                dataType: 'json',
                delay: 200,
                data: function (params) {
                    return { action: 'search', q: params.term };
                },
                processResults: function (data) {
                    return { results: (data && data.results) ? data.results : [] };
                },
                cache: true
            }
        });

    }());

    /* ------------------------------------------- attributes and options */

    /* Creating an attribute or an option must not cost the operator the form
       they have half filled in, which is the whole reason these go through an
       endpoint instead of a link to add_product_attribute.php. */

    /* The card chips have nowhere to put an inline error message, so failures
       surface through the shared toast helper (backend.src.js). */
    function notifyError(message) {
        if (typeof window.pgToast === 'function') {
            window.pgToast({ message: message, variant: 'danger' });
        } else {
            window.alert(message);
        }
    }

    function postAction(payload) {

        payload.token = cfg.token || '';

        return $.post('product_attribute_action.php', payload, null, 'json')
            .then(function (response) {
                if (response && response.error) {
                    return $.Deferred().reject(response.error).promise();
                }
                return response;
            }, function () {
                /* Network failure, or an HTML error page where JSON was
                   expected. Either way the operator needs a sentence, not a
                   silent no-op. */
                return $.Deferred().reject(label('request_failed')).promise();
            });
    }

    /* ---- inline "add option" on an attribute card */

    $(document).on('click', '.pg-pb-option-add-open', function () {
        var $wrap = $(this).closest('.pg-pb-option-add');
        $(this).addClass('d-none');
        $wrap.find('.pg-pb-option-add-form').removeClass('d-none').find('.pg-pb-option-add-input').val('').trigger('focus');
    });

    function closeOptionAdd($wrap) {
        $wrap.find('.pg-pb-option-add-form').addClass('d-none');
        $wrap.find('.pg-pb-option-add-open').removeClass('d-none');
    }

    $(document).on('click', '.pg-pb-option-add-cancel', function () {
        closeOptionAdd($(this).closest('.pg-pb-option-add'));
    });

    $(document).on('keydown', '.pg-pb-option-add-input', function (event) {
        if (event.key === 'Enter') {
            /* Enter inside the product form would submit the product form. */
            event.preventDefault();
            $(this).closest('.pg-pb-option-add').find('.pg-pb-option-add-save').trigger('click');
        } else if (event.key === 'Escape') {
            closeOptionAdd($(this).closest('.pg-pb-option-add'));
        }
    });

    $(document).on('click', '.pg-pb-option-add-save', function () {

        var $button = $(this);
        var $wrap   = $button.closest('.pg-pb-option-add');
        var $card   = $button.closest('.pg-pb-attribute');
        var $input  = $wrap.find('.pg-pb-option-add-input');
        var value   = $input.val().trim();

        if (!value) {
            $input.addClass('is-invalid').trigger('focus');
            return;
        }

        $button.prop('disabled', true);

        postAction({
            action:       'create_option',
            attribute_id: $card.data('attr-id'),
            label:        value
        }).done(function (option) {

            /* Insert before the add control so the "+" stays at the end. */
            $wrap.before(option.html);
            closeOptionAdd($wrap);

            /* The default-option select is a second list of the same options. */
            $card.find('.pg-pb-attr-default').append(
                $('<option>').attr('value', option.id).text(option.label));

            renderMatrix();

        }).fail(function (message) {
            $input.addClass('is-invalid');
            notifyError(message);
        }).always(function () {
            $button.prop('disabled', false);
        });
    });

    /* ---- "new attribute" modal */

    function optionRow() {
        return '<div class="input-group input-group-sm mb-2 pg-pb-attr-option">' +
            '<input type="text" class="form-control pg-pb-attr-option-label" maxlength="255" placeholder="' + esc(label('Label')) + '" />' +
            '<span class="input-group-text">' +
            '<input class="form-check-input mt-0 me-2 pg-pb-attr-option-novalue" type="checkbox" />' +
            '<span class="small">' + esc(label("'No Thanks' Option")) + '</span>' +
            '</span>' +
            '<button type="button" class="btn btn-outline-danger pg-pb-attr-option-remove" title="' + esc(label('Remove')) + '"><i class="bi bi-trash"></i></button>' +
            '</div>';
    }

    function resetAttributeModal() {
        $('#pg_pb_attribute_modal_error').addClass('d-none').text('');
        $('#pg_pb_attr_name').val('').removeClass('is-invalid');
        $('#pg_pb_attr_label').val('');
        $('#pg_pb_attr_options').html(optionRow() + optionRow());
    }

    $('#pg_pb_attribute_modal').on('show.bs.modal', resetAttributeModal);

    $('#pg_pb_attr_add_option').on('click', function () {
        $('#pg_pb_attr_options').append(optionRow());
        $('#pg_pb_attr_options .pg-pb-attr-option-label').last().trigger('focus');
    });

    $(document).on('click', '.pg-pb-attr-option-remove', function () {
        var $rows = $('#pg_pb_attr_options .pg-pb-attr-option');
        if ($rows.length > 1) {
            $(this).closest('.pg-pb-attr-option').remove();
        } else {
            $rows.find('.pg-pb-attr-option-label').val('');
        }
    });

    $('#pg_pb_attr_save').on('click', function () {

        var $button = $(this);
        var $error  = $('#pg_pb_attribute_modal_error');
        var $name   = $('#pg_pb_attr_name');

        $error.addClass('d-none').text('');

        if (!$name.val().trim()) {
            $name.addClass('is-invalid').trigger('focus');
            return;
        }

        /* Keys are written without "[]" on purpose. jQuery's $.param appends
           its own brackets for array values, so "option_label[]" would go out
           as option_label[][]= and arrive in PHP as an array of arrays. */
        var payload = {
            action:          'create_attribute',
            name:            $name.val().trim(),
            label:           $('#pg_pb_attr_label').val().trim(),
            option_label:    [],
            option_no_value: []
        };

        /* Parallel arrays: index N of the labels belongs to index N of the
           checkboxes, so blank rows are sent too and dropped server-side. */
        $('#pg_pb_attr_options .pg-pb-attr-option').each(function () {
            payload.option_label.push($(this).find('.pg-pb-attr-option-label').val());
            payload.option_no_value.push($(this).find('.pg-pb-attr-option-novalue').is(':checked') ? '1' : '');
        });

        $button.prop('disabled', true);

        postAction(payload).done(function (attribute) {

            $('#pg_pb_attributes').append(attribute.html);
            $('#pg_pb_attributes_empty').addClass('d-none');

            var modal = window.bootstrap && bootstrap.Modal.getInstance(document.getElementById('pg_pb_attribute_modal'));
            if (modal) {
                modal.hide();
            }

            renderMatrix();

        }).fail(function (message) {
            $error.removeClass('d-none').text(message);
        }).always(function () {
            $button.prop('disabled', false);
        });
    });

    /* -------------------------------------------------------- validation */

    /* Mark the field, say why underneath it, and jump the nav to the section
       that holds it. A form this long can hide its own error offscreen. */
    function markInvalid($field, message) {

        $field.addClass('is-invalid');

        var $feedback = $field.siblings('.invalid-feedback');

        if ($feedback.length && message) {
            $feedback.text(message);
        }
    }

    $(document).on('input change', '.is-invalid', function () {
        $(this).removeClass('is-invalid');
    });

    /* ---------------------------------------------------------- serialise */

    function collectVariants() {

        var combos = cartesian(selectedDimensions());

        /* No attributes ticked: nothing to describe, the main fields are the
           whole product. */
        if (!combos.length) {
            return [];
        }

        /* Exactly one combination: still a single product, but the attribute
           pair has to reach the server so the product records what it is. */
        if (combos.length === 1) {
            return [{
                name:               $('#name').val(),
                short_description:  $('#short_description').val(),
                price:              $('#price').val() || '0',
                inventory:          $('#inventory').is(':checked') ? '1' : '',
                inventory_quantity: $('#inventory_quantity').val() || '',
                images:             [],
                attributes:         combos[0].map(function (part) {
                    return { attribute_id: part.attr_id, option_id: part.option_id };
                })
            }];
        }

        var trackInventory = $('#inventory').is(':checked') ? '1' : '';
        var variants       = [];

        $('#pg_pb_matrix .pg-pb-variant').each(function () {

            var $row   = $(this);
            var images = [];

            $row.find('.pg-pb-chip-input').each(function () {
                images.push($(this).val());
            });

            var variant = {
                name:               $row.find('.pg-pb-v-name').val(),
                short_description:  $row.find('.pg-pb-v-short').val(),
                price:              $row.find('.pg-pb-v-price').val(),
                inventory:          trackInventory,
                inventory_quantity: $row.find('.pg-pb-v-stock').val(),
                images:             images,
                attributes:         $row.data('attributes') || []
            };

            /* The advanced row is a sibling <tr>, not a descendant, so it is
               found by index rather than through the row. Sent only while
               recurring is on; otherwise the server keeps the product-wide
               values and nothing here can quietly override them. */
            if (recurringIsOn()) {

                var $advanced = $('#pg_pb_matrix .pg-pb-adv-row[data-pg-pb-adv="' + $row.attr('data-pg-pb-adv') + '"]');

                if ($advanced.length) {
                    variant.recurring = {
                        recurring_schedule_editable_by_customer: $advanced.find('.pg-pb-v-editable').is(':checked') ? '1' : '',
                        number_of_payments: $advanced.find('.pg-pb-v-payments').val(),
                        payment_period:     $advanced.find('.pg-pb-v-period').val()
                    };

                    if ($advanced.find('.pg-pb-v-start').length) {
                        variant.recurring.start = $advanced.find('.pg-pb-v-start').val();
                    }
                }
            }

            variants.push(variant);
        });

        return variants;
    }

    function collectAttributeMeta() {

        var meta = [];

        $('.pg-pb-attribute').each(function () {

            var $card = $(this);

            if (!$card.find('.pg-pb-option:checked').length) {
                return;
            }

            meta.push({
                id:                String($card.data('attr-id')),
                default_option_id: $card.find('.pg-pb-attr-default').val() || ''
            });
        });

        return meta;
    }

    $form.on('submit', function (event) {

        var $name = $('#name');

        /* A group with a blank name has nothing to show in the catalog, and a
           product with a blank SKU cannot be told apart in the order table. */
        if (!$name.val().trim()) {
            event.preventDefault();
            markInvalid($name, label('required_sku'));

            var section = document.getElementById('pg_pb_sec_basic');
            if (section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            $name.trigger('focus');
            return false;
        }

        /* A blank SKU on a variant row would be dropped server-side without
           saying so, which reads as "it lost my row". */
        var blankRow = null;

        $('#pg_pb_matrix .pg-pb-v-name').each(function () {
            if (!$(this).val().trim()) {
                blankRow = $(this);
                return false;
            }
        });

        if (blankRow) {
            event.preventDefault();
            markInvalid(blankRow, label('required_sku'));
            blankRow[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            blankRow.trigger('focus');
            return false;
        }

        $('#variants_json').val(JSON.stringify(collectVariants()));
        $('#attributes_meta_json').val(JSON.stringify(collectAttributeMeta()));

        /* TinyMCE writes back on submit for its own textareas, but the group
           description is read server-side from $_POST, so make sure the
           textarea is in sync before the form leaves. */
        if (window.tinymce) {
            tinymce.triggerSave();
        }

        return true;
    });

    /* ---------------------------------------------------------- first run */

    renderMatrix();

}(jQuery));
