/**
 * Pinegrap - Enterprise Website Platform
 *
 * The survey edit screen (edit_feedback_survey.php): the segmented choices
 * that write their hidden field, the language tabs of the texts, the extra
 * questions (three slots, shown and cleared), the form's choice fields
 * narrowed to the chosen form, and the e-mail preview following the score
 * and the question. Served as written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var form = document.getElementById('fs_form');

    if (!form) {
        return;
    }

    var openTab = form.querySelector('[data-fs-tab][aria-selected="true"]');
    var active = openTab ? openTab.getAttribute('data-fs-tab') : '';

    // The texts of the chosen language, and a choice question's choices only.
    function apply() {
        form.querySelectorAll('[data-fs-lang]').forEach(function (element) {
            if (!element.hasAttribute('data-fs-choices')) {
                element.hidden = (element.getAttribute('data-fs-lang') !== active);
            }
        });
        form.querySelectorAll('[data-fs-choices]').forEach(function (input) {
            var type = input.closest('[data-fs-extra]').querySelector('[data-fs-extra-type]').value;
            var language = input.getAttribute('data-fs-lang');
            input.hidden = (type !== 'choice') || ((language !== null) && (language !== active));
        });
    }

    // The preview's question and comment: the first language's text, else
    // the default the field shows.
    function preview() {
        ['question', 'comment'].forEach(function (key, index) {
            var input = form.querySelector('[data-fs-text="' + key + '"]');
            var text = input ? (input.value.trim() || input.getAttribute('placeholder') || '') : '';

            form.querySelectorAll('[data-fs-preview]').forEach(function (box) {
                var lines = box.querySelectorAll('p');
                var line = (index === 0) ? lines[0] : lines[lines.length - 1];

                if (line) {
                    line.textContent = text;
                }
            });
        });
    }

    function showFields() {
        var formSelect = document.getElementById('fs_filter_form');
        var fieldSelect = document.getElementById('fs_filter_field');

        if (!formSelect || !fieldSelect) {
            return;
        }

        Array.prototype.forEach.call(fieldSelect.options, function (option) {
            var owner = option.getAttribute('data-fs-form');
            option.hidden = (owner !== null) && (formSelect.value !== '0') && (owner !== formSelect.value);
        });

        if (fieldSelect.selectedOptions[0] && fieldSelect.selectedOptions[0].hidden) {
            fieldSelect.value = '0';
        }

        form.querySelector('[data-fs-value-box]').hidden = (fieldSelect.value === '0');
    }

    function showAdd() {
        form.querySelector('[data-fs-extra-add]').hidden = !form.querySelector('[data-fs-extra][hidden]');
    }

    form.addEventListener('click', function (event) {
        var item = event.target.closest('[data-fs-seg] .pg-seg-item');
        var tab = event.target.closest('[data-fs-tab]');
        var remove = event.target.closest('[data-fs-extra-remove]');

        if (item) {
            var group = item.closest('[data-fs-seg]');
            group.querySelectorAll('.pg-seg-item').forEach(function (button) {
                button.setAttribute('aria-pressed', (button === item) ? 'true' : 'false');
            });
            group.nextElementSibling.value = item.getAttribute('data-fs-value');

            if (group.getAttribute('data-fs-seg') === 'kind') {
                form.querySelectorAll('[data-fs-text]').forEach(function (input) {
                    input.setAttribute('placeholder', item.getAttribute('data-fs-' + input.getAttribute('data-fs-text')) || '');
                });
                form.querySelectorAll('[data-fs-preview]').forEach(function (box) {
                    box.hidden = (box.getAttribute('data-fs-preview') !== item.getAttribute('data-fs-value'));
                });
                preview();
            }
        } else if (tab) {
            active = tab.getAttribute('data-fs-tab');
            form.querySelectorAll('[data-fs-tab]').forEach(function (button) {
                button.setAttribute('aria-selected', (button === tab) ? 'true' : 'false');
            });
            apply();
        } else if (event.target.closest('[data-fs-extra-add]')) {
            var slot = form.querySelector('[data-fs-extra][hidden]');

            if (slot) {
                slot.hidden = false;
                slot.querySelector('input:not([hidden])').focus();
            }

            showAdd();
        } else if (remove) {
            var row = remove.closest('[data-fs-extra]');
            row.querySelectorAll('input').forEach(function (input) {
                input.value = '';
            });
            row.querySelector('[data-fs-extra-type]').value = 'text';
            row.hidden = true;
            apply();
            showAdd();
            form.querySelector('[data-fs-extra-add]').focus();
        }
    });

    form.addEventListener('change', function (event) {
        if (event.target.matches('[data-fs-extra-type]')) {
            apply();
        } else if (event.target.matches('#fs_filter_form, #fs_filter_field')) {
            showFields();
        }
    });

    form.addEventListener('input', function (event) {
        if (event.target.matches('[data-fs-text]')) {
            preview();
        }
    });

    showFields();
}());
