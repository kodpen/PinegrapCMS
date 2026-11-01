/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard (includes/dashboard/v2/page.php): the grid and the card
 * frame. Reads the page's config from #pg_dash_config, the only source of
 * the card list and of every text it shows; the steps that follow draw the
 * card frames, load their data and refresh them here, and edit mode and the
 * card renderers come as files of their own in the same bundle
 * (pg_js_bundles()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var PGDash = window.PGDash = window.PGDash || {};

    PGDash.config = null;

    // The card renderers register their draw functions here by card id.
    PGDash.renderers = PGDash.renderers || {};

    PGDash.register = function (id, draw) {
        PGDash.renderers[id] = draw;
    };

    function readConfig() {
        var node = document.getElementById('pg_dash_config');

        if (!node) {
            return null;
        }

        try {
            return JSON.parse(node.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    function init() {
        PGDash.config = readConfig();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
