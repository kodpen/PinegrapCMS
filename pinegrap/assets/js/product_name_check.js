/**
 * Pinegrap - Enterprise Website Platform
 *
 * The product screen's code field (Product ID / SKU). While the operator
 * types, api.php (product_name_check) is asked whether another product
 * already holds the code, and the field says which one before the form is
 * posted, so nothing typed is lost to a refused save. A save while the code
 * is known to be taken is stopped here; pg_pb_validate_product_post()
 * refuses it again on the server.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var field = document.getElementById('name');
    var form = document.getElementById('pg_pb_form');

    if (!field || !form || (typeof pg_api_url !== 'function')) {
        return;
    }

    var feedback = field.parentNode.querySelector('.invalid-feedback');
    var groupHint = field.parentNode.querySelector('.pg-pb-group-only');
    var requiredText = feedback ? feedback.textContent : '';
    var initial = field.defaultValue.trim();
    var exceptId = parseInt(field.getAttribute('data-pg-name-except'), 10) || 0;
    var token = (window.PinegrapProductBuilder && window.PinegrapProductBuilder.token)
        || ((typeof software_token !== 'undefined') ? software_token : '');

    // The last answer that found the typed code taken, while it still is.
    var taken = null;
    var timer = null;

    // In a variant set the typed code is only the prefix of the rows' codes
    // and is not written as a product's code; product_builder.js shows the
    // hint under the field in that mode.
    function prefixOnly() {
        return !!groupHint && !groupHint.classList.contains('d-none');
    }

    function clear() {
        taken = null;

        if (feedback && feedback.hasAttribute('data-pg-name-taken')) {
            feedback.removeAttribute('data-pg-name-taken');
            feedback.textContent = requiredText;
            field.classList.remove('is-invalid');
        }
    }

    function show(answer) {
        if (!feedback) {
            return;
        }

        var link = document.createElement('a');

        link.href = answer.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = answer.link;

        feedback.textContent = answer.message + ' ';
        feedback.appendChild(link);
        feedback.setAttribute('data-pg-name-taken', '1');
        field.classList.add('is-invalid');
    }

    function check() {
        clearTimeout(timer);

        var code = field.value.trim();

        if ((code === '') || (code === initial) || prefixOnly()) {
            clear();
            return;
        }

        fetch(pg_api_url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'product_name_check', token: token, name: code, product_id: exceptId })
        }).then(function (response) {
            return response.json();
        }).then(function (answer) {
            // Typed on since: the newer code gets its own answer.
            if (field.value.trim() !== code) {
                return;
            }

            if (answer && (answer.status === 'success') && answer.taken) {
                taken = { code: code, answer: answer };
                show(answer);
            } else {
                clear();
            }
        }).catch(function () {
            // Left to the server's check on save.
        });
    }

    field.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(check, 400);
    });

    field.addEventListener('change', check);

    if (groupHint && window.MutationObserver) {
        new MutationObserver(check).observe(groupHint, { attributes: true, attributeFilter: ['class'] });
    }

    form.addEventListener('submit', function (event) {
        if (event.defaultPrevented || !taken || (field.value.trim() !== taken.code) || prefixOnly()) {
            return;
        }

        event.preventDefault();
        show(taken.answer);
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        field.focus();
    });
})();
