/**
 * Pinegrap - Enterprise Website Platform
 *
 * The contact screen's Journey card (pg_lead_journey_contact_card()): the
 * filter segments (All, Visits, Forms and bookings, Orders), "Show earlier"
 * adding the next rows, and a visit's Pages toggle. The rows are all printed
 * by the server; this only shows and hides them.
 *
 * Loaded with a plain <script src> by edit_contact.php; no .min twin.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var card = document.getElementById('lead_journey');

    if (!card) {
        return;
    }

    var step = parseInt(card.getAttribute('data-lead-page'), 10) || 20;
    var shown = step;
    var filter = '';
    var more = card.querySelector('[data-lead-more]');
    var none = card.querySelector('[data-lead-none]');

    // The rows of the filter in force, the first `shown` of them, each
    // day's heading over its first row in view.
    function draw() {
        var rows = card.querySelectorAll('[data-lead-row]');
        var days = {};
        var matching = 0;

        Array.prototype.forEach.call(rows, function (row) {
            var fits = (filter === '') || (row.getAttribute('data-lead-filter') === filter);
            var visible = fits && (matching < shown);

            matching += fits ? 1 : 0;
            row.hidden = !visible;

            if (visible) {
                days[row.getAttribute('data-lead-day')] = true;
            }
        });

        Array.prototype.forEach.call(card.querySelectorAll('[data-lead-day-head]'), function (head) {
            head.hidden = !days[head.getAttribute('data-lead-day-head')];
        });

        more.hidden = (matching <= shown);
        none.hidden = (matching > 0);
    }

    card.addEventListener('click', function (event) {
        var segment = event.target.closest('[data-lead-filter-button]');
        var toggle = event.target.closest('[data-lead-toggle]');

        if (segment) {
            filter = segment.getAttribute('data-lead-filter-button');
            shown = step;
            Array.prototype.forEach.call(card.querySelectorAll('[data-lead-filter-button]'), function (item) {
                item.setAttribute('aria-pressed', (item === segment) ? 'true' : 'false');
            });
            draw();
        } else if (event.target.closest('[data-lead-more]')) {
            shown += step;
            draw();
        } else if (toggle) {
            var pages = toggle.parentNode.querySelector('[data-lead-pages]');
            var open = toggle.getAttribute('aria-expanded') !== 'true';

            pages.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.querySelector('.bi').className = 'bi ' + (open ? 'bi-chevron-up' : 'bi-chevron-down');
        }
    });
}());
