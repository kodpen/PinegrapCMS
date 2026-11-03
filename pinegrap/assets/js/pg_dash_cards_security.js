/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's Security card (assets/js/pg_dash.js draws the frame):
 * Firewall. The data is what includes/dashboard/v2/cards/firewall.php
 * returns.
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

    var SVG = "http://www.w3.org/2000/svg";

    // Bars with the ones well above the rest marked in the danger colour.
    // The picture's title lists every figure, so colour is not the only
    // place they are.
    function hotBars(values, hot, label, tips) {
        var width = 300;
        var height = 60;
        var max = Math.max.apply(null, (values || []).concat([1]));
        var step = width / Math.max((values || []).length, 1);
        var svg = document.createElementNS(SVG, "svg");
        svg.setAttribute("viewBox", "0 0 " + width + " " + height);
        svg.setAttribute("preserveAspectRatio", "none");
        svg.setAttribute("role", "img");
        svg.setAttribute("class", "pg-dash-bars pg-dash-fw-bars");
        var title = document.createElementNS(SVG, "title");
        title.textContent = label + ": " + (values || []).join(", ");
        svg.appendChild(title);
        (values || []).forEach(function (value, i) {
            var h = Math.max(1, ((value || 0) / max) * height);
            var rect = document.createElementNS(SVG, "rect");
            rect.setAttribute("x", (i * step + step * 0.15).toFixed(1));
            rect.setAttribute("y", (height - h).toFixed(1));
            rect.setAttribute("width", (step * 0.7).toFixed(1));
            rect.setAttribute("height", h.toFixed(1));
            rect.setAttribute("rx", "1.5");
            if (hot && hot[i]) {
                rect.setAttribute("class", "is-hot");
            }
            svg.appendChild(rect);
        });
        if (tips && tips.length) {
            pgDash.chartTips(svg, tips, {mark: pgDash.markBars(svg)});
        }
        return svg;
    }

    pgDash.renderers.firewall = function (body, data) {
        var el = pgDash.el;

        var tiles = pgDash.tiles(data.tiles);
        // The tone of a tile without a note goes on its value (failed
        // sign-ins in red).
        (data.tiles || []).forEach(function (tile, i) {
            var value = tiles.children[i] && tiles.children[i].querySelector(".pg-dash-fig-value");
            if (value && tile.tone && !tile.note) {
                value.setAttribute("data-tone", tile.tone);
            }
        });
        body.appendChild(tiles);

        if (data.mode) {
            body.appendChild(el("p", "pg-dash-fw-mode", data.mode));
        }

        var chart = el("figure", "pg-dash-chart");
        chart.appendChild(hotBars(data.bars, data.hot, data.bars_label, data.tips));
        chart.appendChild(el("figcaption", "pg-dash-chart-caption", data.bars_label));
        body.appendChild(chart);

        var rows = pgDash.rows(data.rows_title, data.rows);
        rows.classList.add("pg-dash-fw-rows");
        body.appendChild(rows);

        if (data.url) {
            var link = el("a", "pg-dash-card-more", data.url_text);
            link.href = data.url;
            body.appendChild(link);
        }
    };
})();
