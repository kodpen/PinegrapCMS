/**
 * Pinegrap - Enterprise Website Platform
 *
 * The WhatsApp template designer (whatsapp_template.php): the type buttons,
 * the formatting buttons and Add variable, the character count, the sample
 * value rows kept in step with the message's variables, and the phone
 * preview redrawn as the message, footer and samples change.
 *
 * pgWaFormat() draws a text as WhatsApp shows it; it is the twin of
 * pg_whatsapp_design_format_html() in includes/fn/whatsapp_design.php, and
 * the two are kept alike. The server checks everything again on save.
 *
 * Loaded by the screen with a plain <script src>; no jQuery.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var form = document.getElementById('pg_wa_form');

    if (!form) {
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
    var previewBody = document.getElementById('pg_wa_preview_body');
    var previewFooter = document.getElementById('pg_wa_preview_footer');
    var variablePattern = /\{\{\s*([a-z0-9_]{1,64})\s*\}\}/g;

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[c];
        });
    }

    // The variables of a text in the order they first appear.
    function variables(text) {
        var seen = [];
        var match;

        variablePattern.lastIndex = 0;

        while ((match = variablePattern.exec(text)) !== null) {
            if (seen.indexOf(match[1]) === -1) {
                seen.push(match[1]);
            }
        }

        return seen;
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

    function samples() {
        var values = {};

        Array.prototype.forEach.call(rows.querySelectorAll('[data-pg-wa-key]'), function (row) {
            var input = row.querySelector('input[name^="sample_"]');
            values[row.getAttribute('data-pg-wa-key')] = input ? input.value.trim() : '';
        });

        return values;
    }

    function fill(template, value) {
        return String(template || '').replace('{var:1}', value);
    }

    function sampleRow(key) {
        var row = document.createElement('div');
        var name = '{{' + key + '}}';

        row.className = 'pg-wa-sample';
        row.setAttribute('data-pg-wa-key', key);
        row.innerHTML = '<code class="pg-wa-sample-key">' + escapeHtml(name) + '</code>'
            + '<div class="pg-wa-sample-fields">'
            + '<input type="text" class="pg-input pg-input-sm" name="label_' + escapeHtml(key) + '" maxlength="200" placeholder="' + escapeHtml(data.label || '') + '" aria-label="' + escapeHtml(fill(data.labelOf, name)) + '">'
            + '<input type="text" class="pg-input pg-input-sm" name="sample_' + escapeHtml(key) + '" maxlength="200" placeholder="' + escapeHtml(data.sample || '') + '" aria-label="' + escapeHtml(fill(data.sampleOf, name)) + '">'
            + '</div>';

        return row;
    }

    // The rows follow the message: one per variable in its order; a row
    // whose variable left the message keeps its values till the next save,
    // in case the variable comes back.
    var parked = {};

    function syncRows() {
        if (!data.editable) {
            return;
        }

        var keys = variables(body.value).slice(0, data.variables || 20);
        var current = {};

        Array.prototype.forEach.call(rows.querySelectorAll('[data-pg-wa-key]'), function (row) {
            current[row.getAttribute('data-pg-wa-key')] = row;
        });

        Object.keys(current).forEach(function (key) {
            if (keys.indexOf(key) === -1) {
                parked[key] = current[key];
                rows.removeChild(current[key]);
            }
        });

        keys.forEach(function (key) {
            var row = current[key] || parked[key] || sampleRow(key);
            rows.appendChild(row);
            delete parked[key];
        });

        none.hidden = keys.length > 0;
    }

    function update() {
        var used = Array.from(body.value.replace(/\r\n?/g, '\n').trim()).length;

        if (count) {
            count.textContent = used + ' / ' + data.max;
            count.classList.toggle('fg-danger', used > data.max);
        }

        syncRows();
        previewBody.innerHTML = pgWaFormat(body.value, samples());

        if (footer && previewFooter) {
            previewFooter.textContent = footer.value;
            previewFooter.hidden = footer.value.trim() === '';
        }
    }

    // Wraps the selection in a WhatsApp marker, or puts a pair at the caret.
    function wrap(marker) {
        var start = body.selectionStart;
        var end = body.selectionEnd;
        var chosen = body.value.slice(start, end);

        body.setRangeText(marker + chosen + marker, start, end, 'end');

        if (chosen === '') {
            body.setSelectionRange(start + marker.length, start + marker.length);
        }

        body.focus();
        update();
    }

    // Puts the next free number at the caret and fills its label.
    function addVariable(label) {
        var numbers = variables(body.value).map(function (key) {
            return parseInt(key, 10) || 0;
        });
        var next = (numbers.length ? Math.max.apply(null, numbers) : 0) + 1;
        var token = '{{' + next + '}}';
        var start = body.selectionStart;
        var before = body.value.slice(0, start);
        var spaced = (before !== '' && !/\s$/.test(before) ? ' ' : '') + token;

        body.setRangeText(spaced, start, body.selectionEnd, 'end');
        body.focus();
        update();

        var row = rows.querySelector('[data-pg-wa-key="' + next + '"]');
        var input = row ? row.querySelector('input[name^="label_"]') : null;

        if (input && label && input.value === '') {
            input.value = label;
        }
    }

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
        button.addEventListener('click', function () {
            wrap(button.getAttribute('data-pg-wa-wrap'));
        });
    });

    var add = document.getElementById('pg_wa_add');

    if (add) {
        add.addEventListener('change', function () {
            if (add.value !== '') {
                addVariable(add.value === '-' ? '' : add.value);
            }

            add.value = '';
        });
    }

    body.addEventListener('input', update);

    if (footer) {
        footer.addEventListener('input', update);
    }

    rows.addEventListener('input', function () {
        previewBody.innerHTML = pgWaFormat(body.value, samples());
    });

    update();
})();
