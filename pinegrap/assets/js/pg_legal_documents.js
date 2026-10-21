/**
 * Pinegrap - Enterprise Website Platform
 *
 * The checkout's legal document links (legal_document.php?type=...) filled
 * with what the checkout form holds at the moment of the click.
 *
 * A plain link shows the document with the values the checkout page was drawn
 * with, so a name or an address typed since then is missing from it. Here a
 * click on such a link posts the form's billing fields, its payment method and
 * its token to the same address instead, in the window the link names, and
 * the page fills the document with them (pg_legal_document_typed_snapshot()).
 * Nothing is stored. A modified click (new tab, new window), a link to a kept
 * copy (order_id), a link to another site or a form without a token is left
 * to the browser, and the plain link still works.
 *
 * Loaded by pg_legal_documents_inject() on every checkout that shows the
 * documents: the designer's express order, the classic express order and the
 * classic order preview.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // A page with two checkouts loads the script twice; one listener posts once.
    if (window.pgLegalDocumentLinks || !document.addEventListener || !window.URL || !window.Element || !Element.prototype.closest) {
        return;
    }

    window.pgLegalDocumentLinks = true;

    // The billing fields the documents print (pg_legal_document_billing_fields()).
    var BILLING = [
        'billing_first_name', 'billing_last_name', 'billing_company',
        'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state',
        'billing_zip_code', 'billing_country', 'billing_phone_number', 'billing_email_address'
    ];

    // The address to post to, or null: the cart's document on this site, never
    // a kept copy, never another host (the form's token goes with the post).
    function documentAddress(link) {
        var url;

        try {
            url = new URL(link.href, window.location.href);
        } catch (error) {
            return null;
        }

        if ((url.origin !== window.location.origin)
            || !/\/legal_document\.php$/.test(url.pathname)
            || !url.searchParams.get('type')
            || url.searchParams.has('order_id')) {
            return null;
        }

        return url.href;
    }

    // The checkout form: the one around the link when it is the form with the
    // purchase button, else the purchase button's own form (a designer tree may
    // place the boxes outside it, tied to it with form="...").
    function checkoutForm(link) {
        var own = link.closest('form');

        if (own && own.elements.namedItem('submit_purchase_now')) {
            return own;
        }

        var button = document.querySelector('[name="submit_purchase_now"]');

        return (button && button.form) ? button.form : null;
    }

    // A field's value as the form would send it, or null when it would send
    // none: the ticked choice of a radio group, the selected option of a list,
    // the text of an input. Hidden inputs count only where asked: a billing
    // value the page keeps hidden is not one the visitor sees, and the
    // server's snapshot of the page leaves it out too
    // (pg_legal_document_form_snapshot()).
    function fieldValue(form, name, hiddenToo) {
        var field = form.elements.namedItem(name);
        var list = !field ? [] : (field.tagName ? [field] : field);

        for (var index = 0; index < list.length; index++) {
            var item = list[index];
            var type = String(item.type || '').toLowerCase();

            if (item.disabled) {
                continue;
            }

            if ((type === 'radio') || (type === 'checkbox')) {
                if (item.checked) {
                    return String(item.value);
                }

                continue;
            }

            if ((type === 'hidden') && !hiddenToo) {
                continue;
            }

            return String(item.value);
        }

        return null;
    }

    // Post the values in a form of their own, so nothing of the checkout form
    // itself (its action, its other fields) is touched.
    function post(address, target, values) {
        var form = document.createElement('form');

        form.method = 'post';
        form.action = address;
        form.target = target;
        form.style.display = 'none';

        Object.keys(values).forEach(function (name) {
            var input = document.createElement('input');

            input.type = 'hidden';
            input.name = name;
            input.value = values[name];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();

        window.setTimeout(function () {
            if (form.parentNode) {
                form.parentNode.removeChild(form);
            }
        }, 0);
    }

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || (event.button !== 0) || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        var link = (event.target && event.target.closest) ? event.target.closest('a[href]') : null;
        var address = link ? documentAddress(link) : null;
        var form = address ? checkoutForm(link) : null;

        if (!form) {
            return;
        }

        var token = fieldValue(form, 'token', true) || ((typeof window.software_token === 'string') ? window.software_token : '');

        if (!token) {
            return;
        }

        var values = { token: token };

        BILLING.forEach(function (name) {
            var value = fieldValue(form, name, false);

            if (value !== null) {
                values[name] = value;
            }
        });

        // A single payment method is a hidden field; it is still the method.
        var method = fieldValue(form, 'payment_method', true);

        if (method !== null) {
            values.payment_method = method;
        }

        event.preventDefault();
        post(address, link.getAttribute('target') || '_self', values);
    });
})();
