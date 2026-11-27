/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Markets screen (view_markets.php): opens the Add market drawer from its
 * button and, with the screen, a drawer that carries data-open (a market
 * opened from the list, or a refused save drawn again). Nothing is saved
 * here: the drawer's form posts it.
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

    var phone = window.matchMedia('(max-width: 575.98px)');

    // From the right, or on a phone as a sheet from the bottom.
    function openDrawer(drawer) {
        if (!drawer || !window.pgUI) {
            return;
        }
        drawer.classList.toggle('drawer-end', !phone.matches);
        drawer.classList.toggle('drawer-bottom', phone.matches);
        window.pgUI.drawer.show(drawer);
    }

    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-pg-market-drawer]');
        if (opener) {
            openDrawer(document.getElementById(opener.getAttribute('data-pg-market-drawer')));
        }
    });

    function start() {
        document.querySelectorAll('dialog[data-pg-market-open]').forEach(openDrawer);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
