/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's Visitors cards (assets/js/pg_dash.js draws the
 * frames): Visitor summaries, Visitor map, Online engagement, Google Search
 * and Google Analytics. The data is what
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
        chart.appendChild(pgDash.line(data.points, data.compare, data.chart_label, data.compare_label, data.tips));

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

    // The home country's provinces with a bubble per place sized by its
    // visits (area follows the figure), placed at the middle of the
    // province's outline. Every bubble's figure is also in the rows and on
    // hover and focus, so the size never carries it alone.
    var SVG = "http://www.w3.org/2000/svg";

    function bubbleMap(figure, map, data) {
        var svg = document.createElementNS(SVG, "svg");
        svg.setAttribute("viewBox", map.viewBox);
        svg.setAttribute("role", "img");
        svg.setAttribute("aria-label", data.map_label);
        var paths = {};
        map.shapes.forEach(function (shape) {
            var path = document.createElementNS(SVG, "path");
            path.setAttribute("d", shape.d);
            svg.appendChild(path);
            paths[shape.id] = path;
        });
        figure.appendChild(svg);

        var box = map.viewBox.split(/[\s,]+/).map(Number);
        var width = box[2] || 100;
        var smallest = width * 0.012;
        var largest = width * 0.045;
        var circles = [];
        var tips = [];
        (data.bubbles || []).forEach(function (bubble) {
            var path = paths[bubble.code];
            if (!path) {
                return;
            }
            var b = path.getBBox();
            var circle = document.createElementNS(SVG, "circle");
            circle.setAttribute("class", "pg-dash-map-bubble");
            circle.setAttribute("cx", String(b.x + b.width / 2));
            circle.setAttribute("cy", String(b.y + b.height / 2));
            circle.setAttribute("r", String(smallest + Math.sqrt(bubble.share || 0) * (largest - smallest)));
            svg.appendChild(circle);
            circles.push(circle);
            tips.push(bubble.tip);
        });
        pgDash.chartTips(svg, tips, {
            parts: circles,
            mark: function (i) {
                circles.forEach(function (circle, n) {
                    circle.toggleAttribute("data-active", n === i);
                });
            }
        });
    }

    pgDash.renderers.visitmap = function (body, data, card, config) {
        var el = pgDash.el;

        var head = el("div", "pg-dash-shop-head");
        head.appendChild(el("p", "pg-dash-shop-figure", data.figure));
        head.appendChild(el("p", "pg-dash-shop-sub", data.sub));
        body.appendChild(head);

        if (data.map_url && pgDash.mapShapes) {
            var figure = el("figure", "pg-dash-chart pg-dash-map");
            body.appendChild(figure);
            pgDash.mapShapes(data.map_url).then(function (map) {
                bubbleMap(figure, map, data);
            }).catch(function () {
                figure.remove();
            });
        }

        if (data.none) {
            body.appendChild(el("p", "pg-dash-card-note", data.none));
        }
        body.appendChild(pgDash.rows(data.rows_title, data.rows));
        more(body, data.url, config);
    };

    // The number on the site in a glowing round, its words beside it; the
    // minutes as green bars, the latest brightest; the pages each with a
    // live dot and its count.
    pgDash.renderers.engagement = function (body, data, card, config) {
        var el = pgDash.el;

        var head = el("div", "pg-dash-live");
        var round = el("span", "pg-dash-live-figure", data.figure);
        round.setAttribute("aria-hidden", "true");
        head.appendChild(round);
        var words = el("div", "pg-dash-live-words");
        var label = el("p", "pg-dash-live-label", data.label || data.headline);
        label.setAttribute("aria-hidden", "true");
        words.appendChild(label);
        words.appendChild(el("p", "visually-hidden", data.headline));
        words.appendChild(el("p", "pg-dash-live-note", data.note));
        head.appendChild(words);
        body.appendChild(head);

        var chart = el("figure", "pg-dash-chart pg-dash-live-bars");
        chart.appendChild(pgDash.bars(data.bars, data.bars_label, data.tips));
        body.appendChild(chart);

        if ((data.rows || []).length) {
            body.appendChild(el("h3", "pg-dash-rows-title", data.rows_title));
            var list = el("ul", "pg-dash-live-pages");
            data.rows.forEach(function (row) {
                var item = el("li", "pg-dash-live-page");
                item.appendChild(el("span", "pg-dash-live-path", row.text));
                item.appendChild(el("span", "pg-dash-live-count", row.note));
                list.appendChild(item);
            });
            body.appendChild(list);
        }
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
            bar.setAttribute("role", "img");
            bar.setAttribute("aria-label", data.sources_title);
            var parts = [];
            var tips = [];
            var keys = el("ul", "pg-dash-stack-keys");
            data.sources.forEach(function (source, i) {
                var part = el("span", "pg-dash-stack-part");
                part.setAttribute("data-part", String(i));
                part.style.setProperty("--share", String(source.share));
                bar.appendChild(part);
                parts.push(part);
                tips.push(source.text + ": " + source.note);

                var key = el("li", "pg-dash-stack-key");
                key.setAttribute("data-part", String(i));
                key.appendChild(el("span", "pg-dash-stack-name", source.text));
                key.appendChild(el("span", "pg-dash-stack-note", source.note));
                keys.appendChild(key);
            });
            body.appendChild(bar);
            body.appendChild(keys);
            pgDash.chartTips(bar, tips, {parts: parts});
        }

        if (data.url && data.link) {
            var link = el("a", "pg-dash-card-more", data.link);
            link.href = data.url;
            body.appendChild(link);
        }
    };
})();
