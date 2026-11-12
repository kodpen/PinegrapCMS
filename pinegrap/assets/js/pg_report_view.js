/**
 * Pinegrap - Enterprise Website Platform
 *
 * The report screens' shared view (the order, visitor, shipping and ERP
 * tax reports): the summary figures and the chart over the report's table,
 * drawn with the dashboard's own drawing (assets/js/pg_dash.js:
 * pgDash.tiles, line, rows, chartTips, mapShapes). The data comes ready
 * from the server in #pg_report_view_data (includes/fn/report_view.php);
 * every text is written with textContent.
 *
 * It also opens the report's settings drawer at once where the server asked
 * for it (data-pg-open-at-load: a plain new report, a save that came back
 * with an error).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    "use strict";

    var SVG = "http://www.w3.org/2000/svg";

    function card(className) {
        var box = document.createElement("div");
        box.className = "card pg-dash-card pg-dash-cardbox " + className;
        box.setAttribute("data-category", "shop");
        return box;
    }

    function line(box, chart) {
        var el = window.pgDash.el;
        var figure = el("figure", "pg-dash-chart pg-report-view-line");
        // A second, dashed line where the report gives one (the ERP tax
        // report's deductible tax beside the calculated).
        figure.appendChild(window.pgDash.line(chart.values, chart.compare || null, chart.label, chart.compare_label || "", chart.tips));
        var ends = el("figcaption", "pg-report-view-ends");
        ends.setAttribute("aria-hidden", "true");
        ends.appendChild(el("span", "", chart.first));
        ends.appendChild(el("span", "", chart.last));
        figure.appendChild(ends);
        box.appendChild(el("h2", "pg-dash-rows-title", chart.label));
        box.appendChild(figure);
    }

    function map(box, chart, data) {
        var el = window.pgDash.el;
        var figure = el("figure", "pg-dash-chart pg-dash-map");
        box.appendChild(el("h2", "pg-dash-rows-title", chart.label));
        box.appendChild(figure);

        window.pgDash.mapShapes(data.maps + chart.map).then(function (shapes) {
            var svg = document.createElementNS(SVG, "svg");
            svg.setAttribute("viewBox", shapes.viewBox);
            svg.setAttribute("role", "img");
            svg.setAttribute("aria-label", chart.label);
            var steps = chart.shades || {};
            var paths = {};
            shapes.shapes.forEach(function (shape) {
                var path = document.createElementNS(SVG, "path");
                path.setAttribute("d", shape.d);
                if (steps[shape.id]) {
                    path.setAttribute("data-step", String(steps[shape.id]));
                }
                paths[shape.id] = path;
                svg.appendChild(path);
            });
            var places = (chart.places || []).filter(function (place) {
                return paths[place.id];
            });
            var ring = document.createElementNS(SVG, "path");
            ring.setAttribute("class", "pg-dash-map-ring");
            svg.appendChild(ring);
            figure.appendChild(svg);
            window.pgDash.chartTips(svg, places.map(function (place) {
                return place.text;
            }), {
                parts: places.map(function (place) {
                    return paths[place.id];
                }),
                start: 0,
                mark: function (i) {
                    if (i < 0) {
                        ring.removeAttribute("d");
                    } else {
                        ring.setAttribute("d", paths[places[i].id].getAttribute("d"));
                    }
                }
            });
            var legend = el("figcaption", "pg-dash-map-legend");
            legend.appendChild(el("span", "", data.strings.legend[0]));
            [1, 2, 3, 4].forEach(function (step) {
                var swatch = el("span", "pg-dash-map-step");
                swatch.setAttribute("data-step", String(step));
                legend.appendChild(swatch);
            });
            legend.appendChild(el("span", "", data.strings.legend[1]));
            figure.appendChild(legend);
        }).catch(function () {
            figure.remove();
        });
        // The rows say every figure the map shades; the heading above the
        // map already names them.
        var rows = window.pgDash.rows("", chart.rows);
        var title = rows.querySelector(".pg-dash-rows-title");
        if (title) {
            title.remove();
        }
        box.appendChild(rows);
    }

    function draw(root, data) {
        var figures = root.querySelector(".pg-report-view-figures");
        var tiles = card("pg-report-view-tiles");
        tiles.appendChild(window.pgDash.tiles((data.figures || []).map(function (figure) {
            return {label: figure.label, text: figure.text, note: figure.note || "", tone: figure.tone || ""};
        })));
        figures.appendChild(tiles);

        var chart = data.chart;
        if (!chart) {
            return;
        }
        var box = card("pg-report-view-chartbox");
        if (chart.kind === "line") {
            line(box, chart);
        } else if (chart.kind === "map") {
            map(box, chart, data);
        } else {
            box.appendChild(window.pgDash.rows(chart.label, chart.rows));
        }
        root.querySelector(".pg-report-view-chart").appendChild(box);
    }

    function openSettings() {
        var drawer = document.querySelector("dialog[data-pg-open-at-load]");
        if (drawer && window.pgUI) {
            window.pgUI.ready(function () {
                window.pgUI.drawer.show(drawer);
            });
        }
    }

    function start() {
        openSettings();
        var root = document.getElementById("pg_report_view");
        var node = document.getElementById("pg_report_view_data");
        if (!root || !node || !window.pgDash || !window.pgDash.tiles) {
            return;
        }
        var data;
        try {
            data = JSON.parse(node.textContent || "{}");
        } catch (e) {
            return;
        }
        draw(root, data);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start);
    } else {
        start();
    }
})();
