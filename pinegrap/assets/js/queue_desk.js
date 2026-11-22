/**
 * Pinegrap - Enterprise Website Platform
 *
 * The walk-in queue desk (queue_desk.php): reads the live part (the
 * now-serving block and the waiting rows) again every 15 seconds while the
 * tab is shown, so numbers taken meanwhile appear without a reload. It waits
 * while a drawer or a menu is open, and stops when the answer is not the
 * live part (a signed-out session). The buttons post the page's form;
 * Not here asks first.
 *
 * Loaded with a plain <script src> by the screen; no .min twin.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var live = document.querySelector('[data-pg-desk-live]');
    if (!live || !window.fetch) {
        return;
    }

    var url = live.getAttribute('data-pg-desk-live');
    var timer = null;

    function busy() {
        return document.hidden
            || document.querySelector('dialog[open]')
            || document.querySelector('.menu.show')
            || live.contains(document.activeElement) && document.activeElement !== live;
    }

    function read() {
        if (busy()) {
            return;
        }
        fetch(url, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (response) {
                return response.ok ? response.text() : '';
            })
            .then(function (html) {
                // The live part always starts with its cards; anything else
                // (a sign-in page) ends the reading.
                if (html.indexOf('<section class="pg-card') !== 0) {
                    window.clearInterval(timer);
                    return;
                }
                if (!busy()) {
                    live.innerHTML = html;
                }
            })
            .catch(function () {});
    }

    // The panel's confirm handler binds to the buttons there at load, before
    // a read replaces them: Not here (marked no-submit) asks here.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-pg-desk-live] button.no-submit[data-confirm-content]');
        if (button && !window.confirm(button.getAttribute('data-confirm-content'))) {
            event.preventDefault();
        }
    });

    timer = window.setInterval(read, 15000);
})();
