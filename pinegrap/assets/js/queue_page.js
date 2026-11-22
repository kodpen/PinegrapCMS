/**
 * Pinegrap - Enterprise Website Platform
 *
 * The person's page of a walk-in queue number (queue.php): reads where the
 * number stands every 15 seconds while the page is shown, and stops once
 * the number is over. "Leave the queue" asks first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var leave = document.querySelector('[data-pg-queue-leave]');
    var source = document.querySelector('[data-pg-queue-read]');
    var timer = null;

    if (leave) {
        leave.addEventListener('submit', function (event) {
            if (!window.confirm(leave.getAttribute('data-pg-queue-leave'))) {
                event.preventDefault();
            }
        });
    }

    if (!source || !window.fetch) {
        return;
    }

    function show(status) {
        if (!status || !status.ok) {
            return;
        }

        ['headline', 'line'].forEach(function (name) {
            var field = document.querySelector('[data-pg-queue-field="' + name + '"]');

            if (field && (field.textContent !== status[name])) {
                field.textContent = status[name];
            }
        });

        if (leave && (status.state !== 'waiting')) {
            leave.hidden = true;
        }

        if (status.final) {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', wake);
        }
    }

    function read() {
        if (document.hidden) {
            return;
        }

        window.fetch(source.getAttribute('data-pg-queue-read'), {credentials: 'same-origin', cache: 'no-store'})
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(show)
            .catch(function () {});
    }

    function wake() {
        if (!document.hidden) {
            read();
        }
    }

    timer = window.setInterval(read, 15000);
    document.addEventListener('visibilitychange', wake);
})();
