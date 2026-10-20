/**
 * Pinegrap - Enterprise Website Platform
 *
 * The card typed into the payment service's own fields on the checkout page
 * (_eo_hosted_card_html() in includes/fn/widgets_express_order.php). The box
 * carries the driver's configuration; this script draws the service's fields
 * in it, puts it in the form's card section in place of the site's own card
 * inputs (which are switched off, so no card number is posted), and on
 * submit, while the card method is chosen, turns what the visitor entered
 * into the service's token, writes it into the hidden payment_token input
 * and submits the form again. Nothing else in the form changes.
 *
 * One adapter per service; the server only ever sees the token.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    if (window.pgPaymentHosted) {
        return;
    }

    var CARD = 'Credit/Debit Card';

    var adapters = {
        // Stripe's Payment Element in deferred mode: the PaymentIntent is
        // created on the server, for the order's own total, only when the
        // form is submitted; the page makes a ConfirmationToken.
        stripe: function (mount, client) {
            if (typeof window.Stripe !== 'function') {
                return null;
            }

            var stripe = window.Stripe(client.publishable_key, { locale: client.locale || 'auto' });
            var elements = stripe.elements({
                mode: 'payment',
                amount: client.amount,
                currency: client.currency,
                paymentMethodTypes: client.payment_method_types
            });

            elements.create('payment').mount(mount);

            return {
                token: function (billing) {
                    return elements.submit().then(function (result) {
                        if (result && result.error) {
                            throw result.error;
                        }

                        return stripe.createConfirmationToken({
                            elements: elements,
                            params: { payment_method_data: { billing_details: billing } }
                        });
                    }).then(function (result) {
                        if (!result || result.error || !result.confirmationToken) {
                            throw (result && result.error) ? result.error : new Error('');
                        }

                        return result.confirmationToken.id;
                    });
                }
            };
        }
    };

    function chosenMethod(form) {
        var radio = form.querySelector('input[name="payment_method"]:checked');

        if (radio) {
            return radio.value;
        }

        var single = form.querySelector('input[type="hidden"][name="payment_method"]');

        return single ? single.value : '';
    }

    function value(form, name) {
        var input = form.querySelector('[name="' + name + '"]');

        return (input && typeof input.value === 'string') ? input.value.trim() : '';
    }

    // What the visitor typed in the billing fields, for the card's owner.
    function billing(form) {
        var details = {};
        var name = (value(form, 'billing_first_name') + ' ' + value(form, 'billing_last_name')).trim();
        var email = value(form, 'billing_email_address');
        var phone = value(form, 'billing_phone_number');

        if (name !== '') {
            details.name = name;
        }

        if (email !== '') {
            details.email = email;
        }

        if (phone !== '') {
            details.phone = phone;
        }

        return details;
    }

    // The site's own card inputs are not the card: they are switched off (not
    // posted, not required) and their section shows only the service's box.
    function takeCardSection(form, box) {
        var section = form.querySelector('[data-pg-eo-cc-fields]');
        var names = ['card_number', 'expiration', 'expiration_month', 'expiration_year', 'card_verification_number', 'cardholder'];

        names.forEach(function (name) {
            form.querySelectorAll('input[name="' + name + '"]').forEach(function (input) {
                input.disabled = true;
                input.removeAttribute('required');
                input.removeAttribute('data-pg-cc-required');
            });
        });

        if (!section || section.contains(box)) {
            return section;
        }

        Array.prototype.forEach.call(section.children, function (child) {
            child.style.display = 'none';
        });
        section.insertBefore(box, section.firstChild);

        return section;
    }

    function setUp(box) {
        var form = box.closest('form');

        if (!form || box.pgHostedReady) {
            return;
        }

        box.pgHostedReady = true;

        // One set of the service's fields per form.
        if (form.pgHosted) {
            box.parentNode.removeChild(box);
            return;
        }

        var config;

        try {
            config = JSON.parse(box.getAttribute('data-pg-payment-hosted') || '{}');
        } catch (error) {
            return;
        }

        var error = box.querySelector('.pg-eo-hosted-card-error');
        var token = form.querySelector('input[name="payment_token"]');
        var messages = config.messages || {};
        var adapter = adapters[config.driver];

        // Moved before the service draws its fields: a frame that is moved
        // later loads again and loses them.
        var section = takeCardSection(form, box);
        var instance = adapter ? adapter(box.querySelector('.pg-eo-hosted-card-element'), config.client || {}) : null;

        if (!instance || !token) {
            if (error) {
                error.textContent = messages.unavailable || '';
            }

            return;
        }

        var state = { instance: instance, token: token, error: error, messages: messages, ready: false, busy: false, submitter: null };
        form.pgHosted = state;

        // A box outside the card section follows the payment method itself.
        if (!section) {
            var sync = function () {
                box.style.display = (chosenMethod(form) === CARD) ? '' : 'none';
            };

            form.addEventListener('change', function (event) {
                if (event.target && event.target.name === 'payment_method') {
                    sync();
                }
            });
            sync();
        }

        // Browsers without SubmitEvent.submitter: the button last pressed.
        form.addEventListener('click', function (event) {
            var button = event.target && event.target.closest ? event.target.closest('button, input[type="submit"]') : null;

            if (button && button.form === form) {
                state.submitter = button;
            }
        }, true);
    }

    // Capture on the document runs before any listener on the form, so the
    // form's own submit handlers run once: on the second pass, with the token.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var state = form ? form.pgHosted : null;

        if (!state) {
            return;
        }

        if (chosenMethod(form) !== CARD) {
            state.token.value = '';
            return;
        }

        if (state.ready) {
            state.ready = false;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        if (state.busy) {
            return;
        }

        state.busy = true;
        state.token.value = '';

        if (state.error) {
            state.error.textContent = '';
        }

        var submitter = event.submitter || state.submitter;

        state.instance.token(billing(form)).then(function (id) {
            state.token.value = id;
            state.ready = true;
            state.busy = false;

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
            } else if (submitter && typeof submitter.click === 'function') {
                submitter.click();
            } else {
                state.ready = false;
                form.submit();
            }
        }, function (reason) {
            state.busy = false;

            if (state.error) {
                state.error.textContent = (reason && reason.message) ? reason.message : (state.messages.invalid || '');
            }
        });
    }, true);

    function setUpAll() {
        document.querySelectorAll('[data-pg-payment-hosted]').forEach(setUp);
    }

    window.pgPaymentHosted = { setUp: setUpAll };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setUpAll);
    } else {
        setUpAll();
    }
})();
