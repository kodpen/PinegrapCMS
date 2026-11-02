/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's Visitors cards (assets/js/pg_dash.js draws the
 * frames): Visitor summaries, Online engagement, Google Search and Google
 * Analytics. The data is what
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

    pgDash.renderers.gsearch = function (body, data, card, config) {
        var el = pgDash.el;

        body.appendChild(pgDash.tiles(data.tiles));
        body.appendChild(el("p", "pg-dash-card-note", data.range));

        if ((data.rows || []).length) {
            body.appendChild(el("h3", "pg-dash-rows-title", data.rows_title));
            var list = el("ul", "pg-dash-items");
            data.rows.forEach(function (row) {
                var item = el("li", "pg-dash-item");
                var main = el("span", "pg-dash-item-main");
                main.appendChild(el("span", "pg-dash-item-text", row.text));
                main.appendChild(el("span", "pg-dash-item-note", row.note));
                item.appendChild(main);
                item.appendChild(el("span", "pg-dash-item-value", row.value));
                list.appendChild(item);
            });
            body.appendChild(list);
        } else {
            body.appendChild(el("p", "pg-dash-card-note", data.none));
        }

        if (data.url) {
            var link = el("a", "pg-dash-card-more", data.link);
            link.href = data.url;
            body.appendChild(link);
        }
    };

    // Traffic sources as one stacked bar; the legend carries every share
    // as text, so the colours are never the only place they are.
    pgDash.renderers.ga = function (body, data) {
        var el = pgDash.el;

        body.appendChild(pgDash.tiles(data.tiles));
        body.appendChild(el("p", "pg-dash-card-note", data.range));

        if ((data.sources || []).length) {
            body.appendChild(el("h3", "pg-dash-rows-title", data.sources_title));
            var bar = el("div", "pg-dash-stack");
            bar.setAttribute("aria-hidden", "true");
            var keys = el("ul", "pg-dash-stack-keys");
            data.sources.forEach(function (source, i) {
                var part = el("span", "pg-dash-stack-part");
                part.setAttribute("data-part", String(i));
                part.style.setProperty("--share", String(source.share));
                bar.appendChild(part);

                var key = el("li", "pg-dash-stack-key");
                key.setAttribute("data-part", String(i));
                key.appendChild(el("span", "pg-dash-stack-name", source.text));
                key.appendChild(el("span", "pg-dash-stack-note", source.note));
                keys.appendChild(key);
            });
            body.appendChild(bar);
            body.appendChild(keys);
        }

        if (data.url && data.link) {
            var link = el("a", "pg-dash-card-more", data.link);
            link.href = data.url;
            body.appendChild(link);
        }
    };
})();
