/**
 * Pinegrap - Enterprise Website Platform
 *
 * Edit Product's Performance card. Once the page has opened, api.php
 * (product_insights) is asked for the chosen period's figures and the body
 * it draws is put under the card's title; skeleton rows stand in meanwhile.
 * The period is the person's choice, kept in this browser. A failed read
 * shows one line and "Try again". The card writes nothing and is no part of
 * the product form's Save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var card = document.getElementById('pg_pi_card');

    if (!card) {
        return;
    }

    var body = card.querySelector('.pg-pi-body');
    var buttons = card.querySelectorAll('[data-pg-pi-period]');
    var skeleton = body.innerHTML;
    var storeKey = 'pg_product_insights_period';
    var periods = ['30d', '12m', 'all'];
    var asked = 0;

    // Storage can be blocked (a private window, cleared site data): the card
    // then starts at 30 days.
    function storedPeriod() {
        try {
            var value = window.localStorage.getItem(storeKey);
            return (periods.indexOf(value) !== -1) ? value : '30d';
        } catch (e) {
            return '30d';
        }
    }

    function keepPeriod(period) {
        try {
            window.localStorage.setItem(storeKey, period);
        } catch (e) {
            // Not kept; the next visit starts at 30 days.
        }
    }

    function mark(period) {
        Array.prototype.forEach.call(buttons, function (button) {
            button.setAttribute('aria-pressed', (button.getAttribute('data-pg-pi-period') === period) ? 'true' : 'false');
        });
    }

    function failed() {
        var line = document.createElement('p');
        var retry = document.createElement('button');

        line.className = 'pg-pi-failed';
        line.textContent = card.getAttribute('data-failed') + ' ';
        retry.type = 'button';
        retry.className = 'pg-button pg-button-ghost pg-button-sm';
        retry.textContent = card.getAttribute('data-retry');
        retry.addEventListener('click', function () {
            load(storedPeriod());
        });
        line.appendChild(retry);
        body.textContent = '';
        body.appendChild(line);
        body.setAttribute('aria-busy', 'false');
    }

    function load(period) {
        var ticket = ++asked;

        mark(period);
        body.innerHTML = skeleton;
        body.setAttribute('aria-busy', 'true');

        fetch(pg_api_url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'product_insights', product_id: card.getAttribute('data-product'), period: period })
        }).then(function (response) {
            return response.json();
        }).then(function (answer) {
            // A newer period was chosen while this one was read.
            if (ticket !== asked) {
                return;
            }

            if (answer && (answer.status === 'success') && answer.data && (typeof answer.data.html === 'string')) {
                // Drawn and escaped by pg_product_insights_card_html().
                body.innerHTML = answer.data.html;
                body.setAttribute('aria-busy', 'false');
                return;
            }

            if (answer && answer.message) {
                card.setAttribute('data-failed', answer.message);
            }

            failed();
        }).catch(function () {
            if (ticket === asked) {
                failed();
            }
        });
    }

    Array.prototype.forEach.call(buttons, function (button) {
        button.addEventListener('click', function () {
            var period = button.getAttribute('data-pg-pi-period');

            if (button.getAttribute('aria-pressed') === 'true') {
                return;
            }

            keepPeriod(period);
            load(period);
        });
    });

    load(storedPeriod());
})();
