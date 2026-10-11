/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the "New account" dialog of the document screens.
 *
 * A link carrying data-erp-account-quick opens the dialog that
 * erp_account_quick_modal() prints; the account is opened by
 * erp_account_quick.php and lands, picked, in the select the link names in
 * data-target. data-kind says whether a customer or a supplier is opened;
 * "auto" reads the screen's #direction (a purchase, or a cheque given, opens
 * a supplier). The link keeps its href, so a middle or modified click still
 * opens the full account form.
 *
 * Loaded with a plain <script src> by the dialog's markup; no .min twin.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var modal = document.getElementById('erp_account_quick_modal');
    if (!modal) {
        return;
    }

    var form = modal.querySelector('form');
    var go = form.querySelector('[data-erp-quick-go]');
    var formError = form.querySelector('[data-erp-quick-error="_form"]');
    var target = null;
    var busy = false;

    function kindFor(trigger) {
        var kind = trigger.getAttribute('data-kind') || 'auto';
        if (kind === 'customer' || kind === 'supplier') {
            return kind;
        }
        var direction = document.getElementById('direction');
        var value = direction ? direction.value : '';
        return (value === 'purchase' || value === 'given') ? 'supplier' : 'customer';
    }

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(function (node) {
            node.classList.remove('is-invalid');
        });
        form.querySelectorAll('[data-erp-quick-error]').forEach(function (node) {
            node.textContent = '';
        });
        formError.classList.add('d-none');
    }

    function showFormError(message) {
        formError.textContent = message || modal.getAttribute('data-text-failed');
        formError.classList.remove('d-none');
    }

    function open(trigger) {
        var selector = trigger.getAttribute('data-target') || '';
        var select = selector ? document.querySelector(selector) : null;
        if (!select || select.tagName !== 'SELECT' || !window.bootstrap || !window.fetch || !window.FormData) {
            return false;
        }
        target = select;
        form.reset();
        clearErrors();
        var kind = kindFor(trigger);
        form.elements.kind.value = kind;
        // A supplier is usually a firm; a person is the usual buyer.
        form.querySelector('input[name="is_person"][value="' + (kind === 'supplier' ? '0' : '1') + '"]').checked = true;
        bootstrap.Modal.getOrCreateInstance(modal).show();
        return true;
    }

    // On the window in the capture phase, so the click is claimed before the
    // loading curtain (a capture listener on the document) reads it as a
    // navigation.
    window.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        var trigger = (event.target && event.target.closest) ? event.target.closest('[data-erp-account-quick]') : null;
        if (trigger && open(trigger)) {
            event.preventDefault();
        }
    }, true);

    modal.addEventListener('shown.bs.modal', function () {
        form.elements.title.focus();
    });

    function pick(answer) {
        var id = String(parseInt(answer.id, 10));
        var option = null;
        for (var i = 0; i < target.options.length; i++) {
            if (target.options[i].value === id) {
                option = target.options[i];
                break;
            }
        }
        if (!option) {
            option = document.createElement('option');
            option.value = id;
            option.textContent = String(answer.title || '');
            target.appendChild(option);
        }
        target.value = id;
        // Native listeners (the invoice editor refreshes the zone rates) hear
        // only a native event. select2 hears it too, through jQuery; the
        // namespaced trigger refreshes its view without running the other
        // jQuery change handlers a second time.
        target.dispatchEvent(new Event('change', { bubbles: true }));
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery(target).trigger('change.select2');
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (busy || !target) {
            return;
        }
        clearErrors();

        var body = new FormData(form);
        body.append('token', modal.getAttribute('data-token') || '');
        busy = true;
        go.disabled = true;

        fetch(modal.getAttribute('data-url'), {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (response) {
                return response.text();
            })
            .then(function (text) {
                try {
                    return JSON.parse(text);
                } catch (error) {
                    return null;
                }
            })
            .catch(function () {
                return null;
            })
            .then(function (answer) {
                busy = false;
                go.disabled = false;

                if (!answer) {
                    showFormError('');
                    return;
                }

                if (answer.ok) {
                    pick(answer);
                    bootstrap.Modal.getOrCreateInstance(modal).hide();
                    if (typeof window.pgToast === 'function' && answer.message) {
                        window.pgToast({ message: answer.message, variant: 'success' });
                    }
                    return;
                }

                var errors = answer.field_errors || {};
                var first = null;
                Object.keys(errors).forEach(function (field) {
                    var input = form.elements[field];
                    var feedback = form.querySelector('[data-erp-quick-error="' + field + '"]');
                    if (input && input.classList) {
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
                    showFormError(answer.message);
                }
            });
    });
})();
