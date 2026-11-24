/**
 * Pinegrap - Enterprise Website Platform
 *
 * The walk-in queue's kiosk (queue_kiosk.php): after a minute with no touch,
 * no key and no scroll it goes back to its start, so the next person never
 * finds what the last one typed, and the start reads the counts afresh.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var screen = document.querySelector('[data-pg-kiosk-start]');

    if (!screen) {
        return;
    }

    var start = screen.getAttribute('data-pg-kiosk-start');
    var seconds = parseInt(screen.getAttribute('data-pg-kiosk-idle'), 10) || 60;
    var timer = null;

    function restart() {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
            window.location.replace(start);
        }, seconds * 1000);
    }

    ['pointerdown', 'keydown', 'input', 'scroll', 'touchstart'].forEach(function (name) {
        document.addEventListener(name, restart, {passive: true, capture: true});
    });

    restart();
})();
