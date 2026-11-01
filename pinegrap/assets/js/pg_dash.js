/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's grid (includes/dashboard/v2/page.php). It reads the
 * configuration the page printed in #pg_dash_config and draws the grid;
 * the card groups' files register their draw functions on
 * window.pgDash.renderers. A card with no renderer is not drawn.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    "use strict";

    var pgDash = window.pgDash = window.pgDash || {};

    pgDash.renderers = pgDash.renderers || {};

    function readConfig() {
        var node = document.getElementById("pg_dash_config");

        if (!node) {
            return null;
        }

        try {
            return JSON.parse(node.textContent || "{}");
        } catch (e) {
            return null;
        }
    }

    function drawGrid(grid, config) {
        var drawn = 0;

        grid.textContent = "";

        (config.cards || []).forEach(function (card) {
            if (typeof pgDash.renderers[card.id] !== "function") {
                return;
            }

            var box = document.createElement("section");
            box.className = "card pg-dash-card";
            box.setAttribute("data-card", card.id);
            box.setAttribute("data-width", card.width);
            grid.appendChild(box);
            pgDash.renderers[card.id](box, card, config);
            drawn++;
        });

        if (!drawn) {
            var empty = document.createElement("p");
            empty.className = "pg-dash-empty";
            empty.textContent = (config.strings && config.strings.empty) || "";
            grid.appendChild(empty);
        }
    }

    function start() {
        var grid = document.getElementById("pg_dash_grid");
        var config = readConfig();

        if (!grid || !config) {
            return;
        }

        pgDash.config = config;
        drawGrid(grid, config);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start);
    } else {
        start();
    }
})();
