/**
 * Pinegrap - Enterprise Website Platform
 *
 * The marks of the texts written with AI on the product screen and the page
 * properties screen. Every field that can carry one has a marker beside its
 * label (pg_ai_fields_marker() in includes/fn/ai_write.php), hidden while the
 * field has no mark, and the form a hidden input listing the marked columns
 * as JSON (pg_ai_fields_input()), which the save stores in products.ai_fields
 * or page.ai_fields with a fingerprint of each text.
 *
 * A mark belongs to the text it was put on. When "Use this text" puts a draft
 * into a field, the draft panel (assets/js/pg_ai_write.js) sends
 * pg:ai-write-used naming the field once the draft is in: its column joins
 * the list, its marker shows and the field's text is remembered. From then on
 * the mark follows the text: editing it by hand takes the mark off, bringing
 * the same text back (an undo) puts it on again. The marker's button takes it
 * off until another draft is used. The fields are looked at once more when
 * the form is sent. A field saved empty, or a text changed without this
 * script, loses its mark on the server.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // The text each marked field had when its mark was put on, by the id of
    // the field's element; null once the person removed the mark.
    var texts = {};

    function remembered(field) {
        return Object.prototype.hasOwnProperty.call(texts, field) && (texts[field] !== null);
    }

    function editorOf(id) {
        var editors = (window.CKEDITOR && window.CKEDITOR.instances) ? window.CKEDITOR.instances : null;

        return (editors && editors[id]) ? editors[id] : null;
    }

    // The field's text as the form sends it: the rich text editor's data once
    // one runs on the field, the element's value otherwise.
    function textOf(id) {
        var editor = editorOf(id);
        var element = document.getElementById(id);

        if (editor && (editor.status === 'ready')) {
            return String(editor.getData() || '');
        }

        return element ? String(element.value || '') : '';
    }

    function formOf(element) {
        return element ? (element.form || (element.closest ? element.closest('form') : null)) : null;
    }

    // The list of marked columns a form posts.
    function inputOf(form) {
        return form ? form.querySelector('input[data-pg-ai-fields]') : null;
    }

    function read(input) {
        try {
            var list = JSON.parse(input.value || '[]');

            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    // The marker of a field, in the form that posts its list.
    function markerOf(form, field) {
        var markers = form.querySelectorAll('[data-pg-ai-mark]');

        for (var i = 0; i < markers.length; i++) {
            if (markers[i].getAttribute('data-pg-ai-field') === field) {
                return markers[i];
            }
        }

        return null;
    }

    // A tooltip left open on a marker that is hidden would stay on the
    // screen.
    function hideTooltips(marker) {
        var tips = marker.querySelectorAll('[data-bs-toggle="tooltip"]');

        if (!window.bootstrap || !window.bootstrap.Tooltip || (typeof window.bootstrap.Tooltip.getInstance !== 'function')) {
            return;
        }

        for (var i = 0; i < tips.length; i++) {
            var tip = window.bootstrap.Tooltip.getInstance(tips[i]);

            if (tip) {
                tip.hide();
            }
        }
    }

    // Shows the marker and puts its column on the list, or hides it and
    // takes the column off.
    function setMarked(input, marker, marked) {
        var column = marker.getAttribute('data-pg-ai-mark');
        var list = read(input).filter(function (name) {
            return name !== column;
        });

        if (marked) {
            list.push(column);
        } else {
            hideTooltips(marker);
        }

        input.value = JSON.stringify(list);
        marker.classList.toggle('d-none', !marked);
    }

    // The mark of a field follows its text: on while the text is the one the
    // mark was put on, off as soon as it is not.
    function follow(field) {
        if (!remembered(field)) {
            return;
        }

        var form = formOf(document.getElementById(field));
        var input = inputOf(form);
        var marker = input ? markerOf(form, field) : null;

        if (!marker) {
            return;
        }

        var marked = (textOf(field) === texts[field]);

        if (marked === marker.classList.contains('d-none')) {
            setMarked(input, marker, marked);
        }
    }

    // The field the removed mark was on, its rich text editor when one runs
    // on it: the button that had the focus is gone.
    function focusField(id) {
        var editor = editorOf(id);
        var element = document.getElementById(id);

        if (editor) {
            editor.focus();
        } else if (element) {
            element.focus();
        }
    }

    // The markers show what the list holds, and each marked field's text now
    // is the one its mark follows: a form whose values the browser put back
    // (the Back button) may carry a list and texts the page was not drawn
    // with.
    function sync() {
        var inputs = document.querySelectorAll('input[data-pg-ai-fields]');

        for (var i = 0; i < inputs.length; i++) {
            var list = read(inputs[i]);
            var markers = inputs[i].form ? inputs[i].form.querySelectorAll('[data-pg-ai-mark]') : [];

            for (var j = 0; j < markers.length; j++) {
                var field = markers[j].getAttribute('data-pg-ai-field');
                var marked = list.indexOf(markers[j].getAttribute('data-pg-ai-mark')) !== -1;

                markers[j].classList.toggle('d-none', !marked);

                if (marked && !remembered(field)) {
                    texts[field] = textOf(field);
                }
            }
        }
    }

    // The rich text editor raises no input events on the page: its own
    // change event is followed, and blur and a switch to or from the source
    // view as well, which change does not always cover. A mark put on before
    // the editor ran is put on the text the editor shows.
    function watchEditor(editor) {
        if (!editor || editor._pgAiFields) {
            return;
        }

        var field = editor.name;
        var update = function () {
            follow(field);
        };

        editor._pgAiFields = true;

        if (remembered(field)) {
            texts[field] = String(editor.getData() || '');
        }

        editor.on('change', update);
        editor.on('blur', update);
        editor.on('mode', update);
    }

    document.addEventListener('pg:ai-write-used', function (event) {
        var detail = event.detail || {};
        var field = String(detail.field || '');
        var form = formOf(field ? document.getElementById(field) : null);
        var input = inputOf(form);
        var marker = input ? markerOf(form, field) : null;

        if (marker) {
            texts[field] = textOf(field);
            setMarked(input, marker, true);
        }
    });

    // Typing, pasting, cutting, and a value another script sets and tells
    // about.
    function edited(event) {
        var target = event.target;

        if (target && target.id) {
            follow(target.id);
        }
    }

    document.addEventListener('input', edited, true);
    document.addEventListener('change', edited, true);

    // The last look before the save: whatever way a text changed, the list
    // that goes with the form says how the fields are now.
    document.addEventListener('submit', function (event) {
        var input = inputOf(event.target);
        var markers = input ? event.target.querySelectorAll('[data-pg-ai-mark]') : [];

        for (var i = 0; i < markers.length; i++) {
            follow(markers[i].getAttribute('data-pg-ai-field'));
        }
    }, true);

    document.addEventListener('click', function (event) {
        var target = event.target;
        var button = (target && target.closest) ? target.closest('.pg-ai-mark-remove') : null;
        var marker = button ? button.closest('[data-pg-ai-mark]') : null;
        var input = marker ? inputOf(marker.closest('form')) : null;

        if (!input) {
            return;
        }

        var field = marker.getAttribute('data-pg-ai-field');

        event.preventDefault();
        texts[field] = null;
        setMarked(input, marker, false);
        focusField(field);
    });

    sync();

    if (window.CKEDITOR && (typeof window.CKEDITOR.on === 'function')) {
        window.CKEDITOR.on('instanceReady', function (event) {
            watchEditor(event.editor);
        });

        for (var name in window.CKEDITOR.instances) {
            if (Object.prototype.hasOwnProperty.call(window.CKEDITOR.instances, name) && (window.CKEDITOR.instances[name].status === 'ready')) {
                watchEditor(window.CKEDITOR.instances[name]);
            }
        }
    }

    window.addEventListener('pageshow', sync);
}());
