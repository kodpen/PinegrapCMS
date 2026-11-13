/**
 * Pinegrap - Enterprise Website Platform
 *
 * The WhatsApp template designer (whatsapp_template.php): the type buttons,
 * the message editor with its variables as chips (pg_var_chips.js), the
 * formatting buttons, the character count, the sample value rows kept in
 * step with the message's variables, and the phone preview redrawn as the
 * message, footer and samples change.
 *
 * The form still posts the message with Meta's {{1}}, {{2}} …: the chips'
 * editor keeps the hidden body field filled, and each sample row posts
 * label_<n> (what the variable stands for) and sample_<n> under the number
 * its variable has in the message now.
 *
 * pgWaFormat() draws a text as WhatsApp shows it; it is the twin of
 * pg_whatsapp_design_format_html() in includes/fn/whatsapp_design.php, and
 * the two are kept alike. The server checks everything again on save.
 *
 * Loaded by the screen with a plain <script src> after pg_var_chips.js; no
 * jQuery.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var form = document.getElementById('pg_wa_form');

    if (!form || !window.pgVarChips) {
        return;
    }

    var data = {};

    try {
        data = JSON.parse(form.getAttribute('data-pg-wa') || '{}');
    } catch (e) {
        data = {};
    }

    var body = document.getElementById('body');
    var footer = document.getElementById('footer');
    var count = document.getElementById('body_count');
    var rows = document.getElementById('pg_wa_samples');
    var none = document.getElementById('pg_wa_samples_none');
    var palette = document.getElementById('pg_wa_palette');
    var previewBody = document.getElementById('pg_wa_preview_body');
    var previewFooter = document.getElementById('pg_wa_preview_footer');
    var variablePattern = /\{\{\s*([a-z0-9_]{1,64})\s*\}\}/g;
    var labels = [];

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[c];
        });
    }

    function fill(template, value) {
        return String(template || '').replace('{var:1}', value);
    }

    // Twin of pg_whatsapp_design_format_html().
    function pgWaFormat(text, samples) {
        var marks = [];

        text = String(text).replace(variablePattern, function (all, key) {
            var value = (samples[key] !== undefined && samples[key] !== '') ? samples[key] : '{{' + key + '}}';
            marks.push('<mark>' + escapeHtml(value) + '</mark>');

            return '\u0001' + (marks.length - 1) + '\u0002';
        });

        var html = escapeHtml(text);
        html = html.replace(/```([^`]+?)```/g, '<code>$1</code>');
        html = html.replace(/(^|[^\w*])\*([^*\n]+?)\*(?![\w*])/g, '$1<b>$2</b>');
        html = html.replace(/(^|[^\w_])_([^_\n]+?)_(?![\w_])/g, '$1<i>$2</i>');
        html = html.replace(/(^|[^\w~])~([^~\n]+?)~(?![\w~])/g, '$1<s>$2</s>');
        html = html.replace(/\u0001([0-9]+)\u0002/g, function (all, index) {
            return marks[parseInt(index, 10)];
        });

        return html.replace(/\n/g, '<br>');
    }

    // The samples by the variable's number in the message now; a variable
    // without a sample is shown by its name.
    function samples() {
        var values = {};

        Array.prototype.forEach.call(rows.querySelectorAll('[data-pg-wa-label]'), function (row) {
            var number = row.getAttribute('data-pg-wa-number');
            var input = row.querySelector('input[data-pg-wa-sample]');

            if (number) {
                values[number] = (input && input.value.trim() !== '') ? input.value.trim() : row.getAttribute('data-pg-wa-label');
            }
        });

        return values;
    }

    function sampleRow(label) {
        var row = document.createElement('div');

        row.className = 'pg-wa-sample';
        row.setAttribute('data-pg-wa-label', label);
        row.appendChild(window.pgVarChips.chip(label));
        row.insertAdjacentHTML('beforeend', '<input type="hidden" data-pg-wa-label-field value="' + escapeHtml(label) + '">'
            + '<input type="text" class="pg-input pg-input-sm" data-pg-wa-sample maxlength="200" placeholder="' + escapeHtml(data.sample || '') + '" aria-label="' + escapeHtml(fill(data.sampleOf, label)) + '">');

        return row;
    }

    // The rows follow the message: one per variable in its order, named by
    // its number now; a row whose variable left the message keeps its value
    // till the page is left, in case the chip comes back.
    var parked = {};

    function syncRows() {
        var current = {};

        Array.prototype.forEach.call(rows.querySelectorAll('[data-pg-wa-label]'), function (row) {
            current[row.getAttribute('data-pg-wa-label')] = row;
        });

        Object.keys(current).forEach(function (label) {
            if (labels.indexOf(label) === -1) {
                parked[label] = current[label];
                rows.removeChild(current[label]);
            }
        });

        labels.forEach(function (label, index) {
            var row = current[label] || parked[label] || sampleRow(label);
            var number = String(index + 1);

            row.setAttribute('data-pg-wa-number', number);
            row.querySelector('[data-pg-wa-label-field]').name = 'label_' + number;
            row.querySelector('[data-pg-wa-sample]').name = 'sample_' + number;
            rows.appendChild(row);
            delete parked[label];
        });

        none.hidden = labels.length > 0;
    }

    function update() {
        var used = Array.from(body.value.replace(/\r\n?/g, '\n').trim()).length;

        if (count) {
            count.textContent = used + ' / ' + data.max;
            count.classList.toggle('fg-danger', used > data.max);
        }

        if (data.editable) {
            syncRows();
        }

        previewBody.innerHTML = pgWaFormat(body.value, samples());

        if (footer && previewFooter) {
            previewFooter.textContent = footer.value;
            previewFooter.hidden = footer.value.trim() === '';
        }
    }

    // The labels the message was opened with, by number.
    var opened = {};

    Array.prototype.forEach.call(rows.querySelectorAll('[data-pg-wa-label]'), function (row) {
        opened[row.getAttribute('data-pg-wa-number')] = row.getAttribute('data-pg-wa-label');
    });

    var editor = window.pgVarChips.mount(body, {
        labels: opened,
        palette: palette,
        max: data.variables || 20,
        readonly: !data.editable,
        texts: {remove: data.remove, fallback: data.fallback},
        onChange: function (text, list) {
            labels = list;
            update();
        },
    });

    labels = editor.serialize().labels;

    Array.prototype.forEach.call(form.querySelectorAll('[data-pg-wa-category]'), function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-pg-wa-category');
            var help = document.getElementById('category_help');

            document.getElementById('category').value = value;

            Array.prototype.forEach.call(form.querySelectorAll('[data-pg-wa-category]'), function (other) {
                other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
            });

            if (help && data.help && data.help[value] !== undefined) {
                help.textContent = data.help[value];
            }
        });
    });

    Array.prototype.forEach.call(form.querySelectorAll('[data-pg-wa-wrap]'), function (button) {
        // The caret stays in the message while the button is pressed.
        button.addEventListener('mousedown', function (event) {
            event.preventDefault();
        });

        button.addEventListener('click', function () {
            editor.wrap(button.getAttribute('data-pg-wa-wrap'));
        });
    });

    // A variable of the person's own name: a chip for the palette, and in
    // the message at once.
    var own = document.getElementById('pg_wa_own');
    var ownAdd = document.getElementById('pg_wa_own_add');

    function addOwn() {
        var name = own.value.replace(/\s+/g, ' ').trim().slice(0, 200);

        if (name === '') {
            own.focus();
            return;
        }

        if (!palette.querySelector('[data-pg-var-label="' + (window.CSS && CSS.escape ? CSS.escape(name) : name) + '"]')) {
            var made = window.pgVarChips.chip(name, {drag: true});
            var button = document.createElement('button');

            button.type = 'button';
            button.className = made.className;
            button.setAttribute('draggable', 'true');
            button.setAttribute('data-pg-var-label', name);
            button.innerHTML = made.innerHTML;
            palette.insertBefore(button, own.closest('.pg-wa-own'));
        }

        own.value = '';
        editor.insert(name);
    }

    if (own && ownAdd) {
        ownAdd.addEventListener('click', addOwn);
        own.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addOwn();
            }
        });
    }

    if (footer) {
        footer.addEventListener('input', update);
    }

    rows.addEventListener('input', function () {
        previewBody.innerHTML = pgWaFormat(body.value, samples());
    });

    update();
})();
