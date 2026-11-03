/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's shop cards (assets/js/pg_dash.js draws the frames):
 * Ecommerce summary, Sales map, Operations Desk, Orders, Offers and Exchange
 * rates. The data is what includes/dashboard/v2/cards/<id>.php returns; every
 * text in it comes from the server.
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

    function more(body, url, config) {
        if (!url) {
            return;
        }
        var link = pgDash.el("a", "pg-dash-card-more", config.strings.all);
        link.href = url;
        body.appendChild(link);
    }

    // The big figure, the line under it and the change beside it.
    function headline(data) {
        var el = pgDash.el;
        var box = el("div", "pg-dash-shop-head");
        box.appendChild(el("p", "pg-dash-shop-figure", data.figure));
        var sub = el("p", "pg-dash-shop-sub", data.sub);
        if (data.change) {
            var change = el("span", "pg-dash-shop-change", data.change);
            if (data.tone) {
                change.setAttribute("data-tone", data.tone);
            }
            sub.appendChild(change);
        }
        box.appendChild(sub);
        return box;
    }

    // A list of linked rows: main text, a line under it, and an end figure
    // with an optional toned note.
    function list(rows, parts) {
        var el = pgDash.el;
        var box = el("ul", "pg-dash-shop-list");
        rows.forEach(function (row) {
            var item = el("li", "pg-dash-shop-item");
            var link = el("a", "pg-dash-shop-link");
            link.href = row.url;
            var main = el("span", "pg-dash-shop-main");
            main.appendChild(el("span", "pg-dash-shop-name", parts.name(row)));
            var meta = parts.meta(row);
            if (meta) {
                main.appendChild(el("span", "pg-dash-shop-meta", meta));
            }
            link.appendChild(main);
            var end = el("span", "pg-dash-shop-end");
            var value = parts.value(row);
            if (value) {
                end.appendChild(el("span", "pg-dash-shop-value", value));
            }
            var note = parts.note(row);
            if (note) {
                var tag = el("span", "pg-dash-shop-tag", note);
                if (row.tone) {
                    tag.setAttribute("data-tone", row.tone);
                }
                end.appendChild(tag);
            }
            if (parts.extra) {
                parts.extra(row, end);
            }
            link.appendChild(end);
            item.appendChild(link);
            box.appendChild(item);
        });
        return box;
    }

    pgDash.renderers.shop = function (body, data, card, config) {
        var el = pgDash.el;
        body.appendChild(headline(data));

        var chart = el("figure", "pg-dash-chart pg-dash-shop-days");
        var bars = pgDash.bars(data.bars, data.bars_label);
        if (bars.lastChild) {
            bars.lastChild.setAttribute("class", "is-today");
        }
        chart.appendChild(bars);
        body.appendChild(chart);

        body.appendChild(pgDash.rows(data.rows_title, data.rows));
        more(body, data.url, config);
    };

    // The map file is fetched once a page and its outlines copied into a
    // fresh SVG: only path ids and shapes are taken from the file.
    var mapFiles = {};

    function mapShapes(url) {
        if (!mapFiles[url]) {
            mapFiles[url] = fetch(url, {credentials: "same-origin"}).then(function (answer) {
                if (!answer.ok) {
                    throw new Error("map");
                }
                return answer.text();
            }).then(function (text) {
                var doc = new DOMParser().parseFromString(text, "image/svg+xml");
                var source = doc.documentElement;
                var shapes = [];
                Array.prototype.forEach.call(doc.querySelectorAll("path[id]"), function (path) {
                    shapes.push({id: path.getAttribute("id"), d: path.getAttribute("d")});
                });
                return {viewBox: source.getAttribute("viewBox") || "0 0 100 100", shapes: shapes};
            });
        }
        return mapFiles[url];
    }

    pgDash.renderers.salesmap = function (body, data, card, config) {
        var el = pgDash.el;
        body.appendChild(headline(data));

        if (data.map_url) {
            var figure = el("figure", "pg-dash-chart pg-dash-map");
            body.appendChild(figure);
            mapShapes(data.map_url).then(function (map) {
                var svg = document.createElementNS(SVG, "svg");
                svg.setAttribute("viewBox", map.viewBox);
                svg.setAttribute("role", "img");
                svg.setAttribute("aria-label", data.map_label);
                var shades = data.shades || {};
                map.shapes.forEach(function (shape) {
                    var path = document.createElementNS(SVG, "path");
                    path.setAttribute("d", shape.d);
                    if (shades[shape.id]) {
                        path.setAttribute("data-step", String(shades[shape.id]));
                    }
                    svg.appendChild(path);
                });
                figure.appendChild(svg);

                var legend = el("figcaption", "pg-dash-map-legend");
                legend.appendChild(el("span", "", (data.legend || [])[0]));
                [1, 2, 3, 4].forEach(function (step) {
                    var swatch = el("span", "pg-dash-map-step");
                    swatch.setAttribute("data-step", String(step));
                    legend.appendChild(swatch);
                });
                legend.appendChild(el("span", "", (data.legend || [])[1]));
                figure.appendChild(legend);
            }).catch(function () {
                figure.remove();
            });
        }

        body.appendChild(pgDash.rows(data.rows_title, data.rows));
        more(body, data.url, config);
    };

    pgDash.renderers.orders = function (body, data, card, config) {
        body.appendChild(list(data.rows || [], {
            name: function (row) { return row.text; },
            meta: function (row) { return row.number + " · " + row.when; },
            value: function (row) { return row.amount; },
            note: function (row) { return row.note; }
        }));
        more(body, data.url, config);
    };

    pgDash.renderers.offers = function (body, data, card, config) {
        body.appendChild(list(data.rows || [], {
            name: function (row) { return row.code; },
            meta: function (row) { return row.note; },
            value: function () { return ""; },
            note: function (row) { return row.days; }
        }));
        more(body, data.url, config);
    };

    // Three lanes side by side: a label, the count, up to three rows and a
    // link to the rest. A late row says how long it waited beside its tone.
    pgDash.renderers.ops = function (body, data) {
        var el = pgDash.el;
        var box = el("div", "pg-dash-ops");
        (data.lanes || []).forEach(function (lane) {
            var item = el("section", "pg-dash-ops-lane");
            item.setAttribute("data-lane", lane.id);
            var head = el("header", "pg-dash-ops-head");
            head.appendChild(el("h3", "pg-dash-ops-label", lane.label));
            var count = el("span", "pg-dash-ops-count", lane.count_text);
            if (lane.tone) {
                count.setAttribute("data-tone", lane.tone);
            }
            head.appendChild(count);
            item.appendChild(head);
            if (lane.sub) {
                item.appendChild(el("p", "pg-dash-ops-sub", lane.sub));
            }
            if (lane.empty) {
                item.appendChild(el("p", "pg-dash-ops-empty", lane.empty));
            } else {
                item.appendChild(list(lane.rows, {
                    name: function (row) { return row.text; },
                    meta: function () { return ""; },
                    value: function () { return ""; },
                    note: function (row) { return row.note; }
                }));
            }
            if (lane.more && lane.url) {
                var more = el("a", "pg-dash-card-more", lane.more);
                more.href = lane.url;
                item.appendChild(more);
            }
            box.appendChild(item);
        });
        body.appendChild(box);
    };

    // A small line of the seven days; the change is written beside it.
    function spark(points, label) {
        var svg = document.createElementNS(SVG, "svg");
        svg.setAttribute("viewBox", "0 0 70 20");
        svg.setAttribute("preserveAspectRatio", "none");
        svg.setAttribute("class", "pg-dash-spark");
        svg.setAttribute("aria-hidden", "true");
        if (!points || points.length < 2) {
            return svg;
        }
        var min = Math.min.apply(null, points);
        var max = Math.max.apply(null, points);
        var span = (max - min) || 1;
        var d = points.map(function (value, i) {
            var x = (i * 70) / (points.length - 1);
            var y = 18 - ((value - min) / span) * 16;
            return (i ? "L" : "M") + x.toFixed(1) + " " + y.toFixed(1);
        }).join(" ");
        var path = document.createElementNS(SVG, "path");
        path.setAttribute("d", d);
        path.setAttribute("vector-effect", "non-scaling-stroke");
        svg.appendChild(path);
        svg.setAttribute("data-label", label);
        return svg;
    }

    pgDash.renderers.fx = function (body, data, card, config) {
        body.appendChild(pgDash.el("p", "pg-dash-shop-note", data.note));
        body.appendChild(list(data.rows || [], {
            name: function (row) { return row.code; },
            meta: function (row) { return row.name; },
            value: function (row) { return row.rate; },
            note: function (row) { return row.change; },
            extra: function (row, end) {
                end.insertBefore(spark(row.points, data.line_label), end.firstChild);
            }
        }));
        more(body, data.url, config);
    };
})();
