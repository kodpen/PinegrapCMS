/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's Visitors cards (assets/js/pg_dash.js draws the
 * frames): Visitor summaries and Online engagement. The data is what
 * includes/dashboard/v2/cards/<id>.php returns.
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

    function more(body, url, config) {
        if (!url) {
            return;
        }
        var link = pgDash.el("a", "pg-dash-card-more", config.strings.all);
        link.href = url;
        body.appendChild(link);
    }

    pgDash.renderers.visitors = function (body, data, card, config) {
        var el = pgDash.el;

        body.appendChild(pgDash.tiles(data.tiles));

        var chart = el("figure", "pg-dash-chart");
        chart.appendChild(pgDash.line(data.points, data.compare, data.chart_label, data.compare_label));

        var caption = el("figcaption", "pg-dash-chart-caption");
        caption.appendChild(el("span", "pg-dash-key pg-dash-key-main", data.chart_label));
        if (data.compare_label) {
            caption.appendChild(el("span", "pg-dash-key pg-dash-key-compare", data.compare_label));
        }
        chart.appendChild(caption);
        body.appendChild(chart);

        body.appendChild(pgDash.rows(data.rows_title, data.rows));
        more(body, data.url, config);
    };

    pgDash.renderers.engagement = function (body, data, card, config) {
        var el = pgDash.el;

        var head = el("div", "pg-dash-live");
        head.appendChild(el("span", "pg-dash-live-dot"));
        head.appendChild(el("p", "pg-dash-live-figure", data.headline));
        body.appendChild(head);
        body.appendChild(el("p", "pg-dash-live-note", data.note));

        var chart = el("figure", "pg-dash-chart");
        chart.appendChild(pgDash.bars(data.bars, data.bars_label));
        body.appendChild(chart);

        body.appendChild(pgDash.rows(data.rows_title, data.rows));
        more(body, data.url, config);
    };
})();
