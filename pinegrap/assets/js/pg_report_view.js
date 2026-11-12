/**
 * Pinegrap - Enterprise Website Platform
 *
 * The report screens' shared view (the order, visitor, shipping and ERP
 * tax reports) in the panel's look: a card per summary figure and, under
 * them, the chart of the report's first level: a line over time with its
 * axes and a box of figures for the point under the pointer or the
 * keyboard, rows of bars for a named level, the world map for a country
 * (drawn with the dashboard's pgDash.mapShapes and chartTips), or one line
 * saying that no chart fits. The data comes ready from the server in
 * #pg_report_view_data (includes/fn/report_view.php); every text is
 * written with textContent.
 *
 * It also opens the report's settings drawer at once where the server asked
 * for it (data-pg-open-at-load: a plain new report, a save that came back
 * with an error), and when a save from outside the drawer meets a field
 * the browser refuses.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    "use strict";

    var SVG = "http://www.w3.org/2000/svg";
    // The plot's height and the room it keeps around the lines for the
    // axis texts, in pixels.
    var PLOT = {height: 232, left: 46, right: 10, top: 10, bottom: 26};
    var TONES = ["blue", "violet", "amber", "teal", "rose", "gray"];

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if ((text !== undefined) && (text !== null)) {
            node.textContent = String(text);
        }
        return node;
    }

    function svgEl(tag, attrs) {
        var node = document.createElementNS(SVG, tag);
        Object.keys(attrs || {}).forEach(function (name) {
            node.setAttribute(name, attrs[name]);
        });
        return node;
    }

    function icon(name) {
        var node = el("i", "bi " + (/^bi-[a-z0-9-]+$/.test(name || "") ? name : "bi-dot"));
        node.setAttribute("aria-hidden", "true");
        return node;
    }

    function tile(name, tone) {
        var node = el("span", "pg-icon-tile pg-tone-" + ((TONES.indexOf(tone) >= 0) ? tone : "gray"));
        node.appendChild(icon(name));
        return node;
    }

    // ── The summary figures ────────────────────────────────────────────────

    // "₺12.632,91", "%45,23" or "45.23%": the unit (a currency symbol, a
    // percent sign) is drawn smaller than the number beside it.
    function figureValue(text) {
        var box = el("dd", "pg-report-fig-value");
        var first = /^(-?)([^\d\s.,-]+)\s?(\d[\d.,\s]*)$/.exec(text);
        var last = first ? null : /^(-?\d[\d.,\s]*?)\s?([^\d\s.,-]+)$/.exec(text);
        if (first) {
            box.appendChild(document.createTextNode(first[1]));
            box.appendChild(el("small", "", first[2]));
            box.appendChild(document.createTextNode(first[3]));
        } else if (last) {
            box.appendChild(document.createTextNode(last[1]));
            box.appendChild(el("small", "", last[2]));
        } else {
            box.textContent = text;
        }
        return box;
    }

    // One card per figure: up to five share one row; more go in two even
    // rows of at most four, so no card is left alone on a row.
    function figures(box, list) {
        var grid = el("dl", "pg-report-figs");
        var columns = (list.length <= 5) ? list.length : Math.min(4, Math.ceil(list.length / 2));
        grid.style.setProperty("--pg-report-figs", String(Math.max(1, columns)));
        list.forEach(function (figure) {
            var card = el("div", "pg-card pg-report-fig");
            var name = el("dt", "pg-report-fig-name");
            name.appendChild(tile(figure.icon, figure.tint));
            name.appendChild(document.createTextNode(figure.label));
            card.appendChild(name);
            card.appendChild(figureValue(String(figure.text)));
            if (figure.note) {
                card.appendChild(el("dd", "pg-report-fig-note" + ((figure.tone === "bad") ? " is-bad" : ""), figure.note));
            }
            grid.appendChild(card);
        });
        box.appendChild(grid);
    }

    // ── The line over time ─────────────────────────────────────────────────

    // The y axis: from zero up in about four steps of 1, 2, 2.5 or 5 times
    // a power of ten; whole steps when every value is whole.
    function axisSteps(most, whole) {
        var rough = Math.max(most, whole ? 4 : 0.04) / 4;
        var power = Math.pow(10, Math.floor(Math.log(rough) / Math.LN10));
        var lead = rough / power;
        var step = 10 * power;
        (whole ? [5, 2, 1] : [5, 2.5, 2, 1]).forEach(function (size) {
            if (lead <= size * 1.0000001) {
                step = size * power;
            }
        });
        var steps = [];
        for (var value = 0; value < most + step * 0.999999; value += step) {
            steps.push(value);
        }
        return steps;
    }

    // "10 B" for ten thousand on a Turkish panel, "10K" on an English one.
    function axisText(value) {
        try {
            return new Intl.NumberFormat(document.documentElement.lang || "en", {notation: "compact", maximumFractionDigits: 2}).format(value);
        } catch (e) {
            return String(value);
        }
    }

    function legend(text, compare) {
        var item = el("span", "pg-report-legend");
        item.appendChild(el("i", "pg-report-legend-mark" + (compare ? " is-compare" : "")));
        item.appendChild(document.createTextNode(text));
        return item;
    }

    function line(card, head, chart) {
        var values = chart.values || [];
        var compare = (chart.compare && chart.compare.length) ? chart.compare : null;
        var names = chart.names || [];
        var count = Math.max(values.length, 1);
        var series = chart.series || chart.label;
        var whole = values.concat(compare || []).every(function (value) {
            return Math.floor(value) === value;
        });
        var steps = axisSteps(Math.max.apply(null, values.concat(compare || [], [0])), whole);
        var top = steps[steps.length - 1];
        var texts = steps.map(axisText);
        var left = Math.max(PLOT.left, 10 + 7 * Math.max.apply(null, texts.map(function (text) {
            return text.length;
        })));

        if (compare) {
            head.appendChild(legend(series, false));
            head.appendChild(legend(chart.compare_label || "", true));
        }

        var plot = el("div", "pg-report-plot");
        plot.tabIndex = 0;
        plot.setAttribute("role", "img");
        plot.setAttribute("aria-label", chart.label + (compare ? " · " + (chart.compare_label || "") : ""));
        var tip = el("div", "pg-report-tip");
        tip.hidden = true;
        tip.setAttribute("aria-hidden", "true");
        plot.appendChild(tip);
        // The point's sentence for a screen reader, said on keyboard moves.
        var live = el("span", "visually-hidden");
        live.setAttribute("aria-live", "polite");
        card.appendChild(plot);
        card.appendChild(live);

        var svg = null;
        var marks = null;
        var width = 0;
        var current = -1;
        var focused = false;

        function x(i) {
            var inner = width - left - PLOT.right;
            return (count > 1) ? left + (inner * i) / (count - 1) : left + inner / 2;
        }

        function y(value) {
            return PLOT.top + (PLOT.height - PLOT.top - PLOT.bottom) * (1 - (value || 0) / top);
        }

        function path(list) {
            return list.map(function (value, i) {
                return (i ? "L" : "M") + x(i).toFixed(1) + " " + y(value).toFixed(1);
            }).join(" ");
        }

        function dot(className, value, radius) {
            return svgEl("circle", {"class": className, cx: 0, cy: y(value).toFixed(1), r: radius});
        }

        // Drawn at the box's own width, so texts and dots keep their shape.
        function draw() {
            width = Math.max(plot.clientWidth, 240);
            var next = svgEl("svg", {viewBox: "0 0 " + width + " " + PLOT.height, "aria-hidden": "true", focusable: "false"});
            steps.forEach(function (value, n) {
                next.appendChild(svgEl("line", {"class": "pg-report-plot-grid", x1: left, x2: width - PLOT.right, y1: y(value), y2: y(value)}));
                var text = svgEl("text", {x: left - 10, y: y(value) + 4, "text-anchor": "end"});
                text.textContent = texts[n];
                next.appendChild(text);
            });
            // As many names under the axis as its width holds, the first
            // and the last among them.
            var shown = Math.min(count, Math.max(2, Math.min(7, Math.floor((width - left - PLOT.right) / 120))));
            for (var n = 0; n < shown; n++) {
                var i = (shown > 1) ? Math.round((n * (count - 1)) / (shown - 1)) : 0;
                var name = svgEl("text", {x: x(i), y: PLOT.height - 6, "text-anchor": (count === 1) ? "middle" : ((n === 0) ? "start" : ((n === shown - 1) ? "end" : "middle"))});
                name.textContent = names[i] || "";
                next.appendChild(name);
            }
            if (values.length > 1) {
                next.appendChild(svgEl("path", {"class": "pg-report-plot-area", d: path(values) + " L" + x(values.length - 1).toFixed(1) + " " + y(0) + " L" + x(0).toFixed(1) + " " + y(0) + " Z"}));
            }
            if (compare) {
                next.appendChild(svgEl("path", {"class": "pg-report-plot-compare", d: path(compare)}));
            }
            next.appendChild(svgEl("path", {"class": "pg-report-plot-line", d: path(values)}));
            // The point under the pointer or the keyboard: a rule down the
            // plot and a ringed dot on each line. A lone point always shows.
            marks = svgEl("g", {});
            marks.appendChild(svgEl("line", {"class": "pg-report-plot-cross", x1: 0, x2: 0, y1: PLOT.top, y2: y(0)}));
            if (compare) {
                marks.appendChild(dot("pg-report-plot-dot is-compare", 0, 4));
            }
            marks.appendChild(dot("pg-report-plot-dot", 0, 5));
            next.appendChild(marks);
            if (svg) {
                plot.replaceChild(next, svg);
            } else {
                plot.insertBefore(next, tip);
            }
            svg = next;
            mark((current >= 0) ? current : ((values.length === 1) ? 0 : -1), current >= 0);
        }

        function mark(i, crossed) {
            marks.setAttribute("visibility", (i < 0) ? "hidden" : "visible");
            if (i < 0) {
                return;
            }
            marks.setAttribute("transform", "translate(" + x(i).toFixed(1) + " 0)");
            marks.firstChild.setAttribute("visibility", crossed ? "inherit" : "hidden");
            var dots = marks.querySelectorAll("circle");
            dots[dots.length - 1].setAttribute("cy", y(values[i]).toFixed(1));
            if (compare) {
                dots[0].setAttribute("cy", y(compare[i]).toFixed(1));
            }
        }

        function tipRow(markClass, label, text) {
            var row = el("div", "pg-report-tip-row" + (markClass ? "" : " is-quiet"));
            if (markClass) {
                row.appendChild(el("i", markClass));
            }
            row.appendChild(el("span", "", label));
            row.appendChild(el("span", "pg-report-tip-figure", text));
            return row;
        }

        function show(i, announce) {
            current = Math.max(0, Math.min(values.length - 1, i));
            mark(current, true);
            tip.textContent = "";
            tip.appendChild(el("b", "", names[current] || ""));
            tip.appendChild(tipRow("pg-report-legend-mark", series, (chart.notes || [])[current]));
            if (compare) {
                tip.appendChild(tipRow("pg-report-legend-mark is-compare", chart.compare_label || "", (chart.compare_notes || [])[current]));
            }
            var extra = (chart.extras || [])[current];
            if (extra) {
                tip.appendChild(tipRow("", extra.label, extra.text));
            }
            tip.hidden = false;
            // Beside the point, on the side that has room.
            var at = x(current) + 16;
            if (at + tip.offsetWidth > width) {
                at = x(current) - 16 - tip.offsetWidth;
            }
            var high = y(Math.max(values[current] || 0, compare ? compare[current] || 0 : 0));
            tip.style.left = Math.round(Math.max(0, at)) + "px";
            tip.style.top = Math.round(Math.max(0, Math.min(high - tip.offsetHeight / 2, PLOT.height - PLOT.bottom - tip.offsetHeight))) + "px";
            if (announce) {
                live.textContent = (chart.tips || [])[current] || "";
            }
        }

        function hide() {
            current = -1;
            tip.hidden = true;
            mark((values.length === 1) ? 0 : -1, false);
        }

        plot.addEventListener("mousemove", function (event) {
            var inner = width - left - PLOT.right;
            var i = (count > 1) ? Math.round(((event.clientX - plot.getBoundingClientRect().left - left) / inner) * (count - 1)) : 0;
            i = Math.max(0, Math.min(values.length - 1, i));
            if ((i !== current) || tip.hidden) {
                show(i, false);
            }
        });
        plot.addEventListener("mouseleave", function () {
            if (!focused) {
                hide();
            }
        });
        plot.addEventListener("focus", function () {
            focused = true;
            show((current >= 0) ? current : values.length - 1, true);
        });
        plot.addEventListener("blur", function () {
            focused = false;
            hide();
        });
        // One tab stop: Left/Right (Home/End) move between the points,
        // Escape hides the box.
        plot.addEventListener("keydown", function (event) {
            var keys = {ArrowLeft: current - 1, ArrowDown: current - 1, ArrowRight: current + 1, ArrowUp: current + 1, Home: 0, End: values.length - 1};
            if (event.key === "Escape") {
                hide();
            } else if (Object.prototype.hasOwnProperty.call(keys, event.key)) {
                event.preventDefault();
                show(keys[event.key], true);
            }
        });

        draw();
        if (window.ResizeObserver) {
            new window.ResizeObserver(function () {
                if (Math.max(plot.clientWidth, 240) !== width) {
                    draw();
                }
            }).observe(plot);
        }
    }

    // ── Rows of bars for a named level ─────────────────────────────────────

    // A row: the name, a bar as long as its share of the largest row, the
    // figure and, where the server gave one, its share of every group.
    function bars(rows) {
        var list = el("ol", "pg-report-bars");
        var most = 0;
        var shares = false;
        (rows || []).forEach(function (row) {
            most = Math.max(most, row.value || 0);
            shares = shares || !!row.share;
        });
        if (shares) {
            list.classList.add("has-share");
        }
        (rows || []).forEach(function (row) {
            var item = el("li", "pg-report-bar");
            var name = el("span", "pg-report-bar-name", row.text);
            name.title = row.text;
            item.appendChild(name);
            var track = el("span", "pg-report-bar-track");
            track.setAttribute("aria-hidden", "true");
            var fill = el("b");
            fill.style.width = ((most > 0) ? Math.max(0, ((row.value || 0) / most) * 100) : 0) + "%";
            track.appendChild(fill);
            item.appendChild(track);
            item.appendChild(el("span", "pg-report-bar-figure", row.note));
            if (shares) {
                item.appendChild(el("span", "pg-report-bar-share", row.share || ""));
            }
            list.appendChild(item);
        });
        return list;
    }

    // ── The world map for a country level ──────────────────────────────────

    function map(card, chart, data) {
        var figure = el("figure", "pg-dash-map pg-report-map");
        var body = el("div", "pg-report-map-body");
        body.appendChild(figure);
        // The rows say every figure the map shades.
        body.appendChild(bars(chart.rows));
        card.appendChild(body);
        if (!window.pgDash || !window.pgDash.mapShapes) {
            figure.remove();
            return;
        }

        window.pgDash.mapShapes(data.maps + chart.map).then(function (shapes) {
            var svg = svgEl("svg", {viewBox: shapes.viewBox, role: "img", "aria-label": chart.label});
            var steps = chart.shades || {};
            var paths = {};
            shapes.shapes.forEach(function (shape) {
                var path = svgEl("path", {d: shape.d});
                if (steps[shape.id]) {
                    path.setAttribute("data-step", String(steps[shape.id]));
                }
                paths[shape.id] = path;
                svg.appendChild(path);
            });
            var places = (chart.places || []).filter(function (place) {
                return paths[place.id];
            });
            var ring = svgEl("path", {"class": "pg-dash-map-ring"});
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
            var ends = el("figcaption", "pg-dash-map-legend");
            ends.appendChild(el("span", "", data.strings.legend[0]));
            [1, 2, 3, 4].forEach(function (step) {
                var swatch = el("span", "pg-dash-map-step");
                swatch.setAttribute("data-step", String(step));
                ends.appendChild(swatch);
            });
            ends.appendChild(el("span", "", data.strings.legend[1]));
            figure.appendChild(ends);
        }).catch(function () {
            figure.remove();
        });
    }

    // ── The view ───────────────────────────────────────────────────────────

    function draw(root, data) {
        figures(root.querySelector(".pg-report-view-figures"), data.figures || []);

        var slot = root.querySelector(".pg-report-view-chart");
        var chart = data.chart;
        if (chart && (chart.kind === "line") && !(chart.values || []).length) {
            return;
        }
        if (!chart) {
            // A level with no fitting chart says so; no empty plot.
            if (data.no_chart) {
                var none = el("p", "pg-card pg-report-nochart");
                none.appendChild(tile("bi-info-lg", "blue"));
                none.appendChild(document.createTextNode(data.strings.no_chart));
                slot.appendChild(none);
            }
            return;
        }
        var card = el("div", "pg-card pg-report-chart is-" + ((chart.kind === "line") ? "line" : ((chart.kind === "map") ? "map" : "bars")));
        var head = el("div", "pg-report-chart-head");
        head.appendChild(el("h3", "pg-report-chart-title", chart.label));
        card.appendChild(head);
        if (chart.kind === "line") {
            // The plot measures its box, so the card joins the page first.
            slot.appendChild(card);
            line(card, head, chart);
            return;
        }
        if (chart.kind === "map") {
            map(card, chart, data);
        } else {
            card.appendChild(bars(chart.rows));
        }
        slot.appendChild(card);
    }

    function openSettings() {
        var drawer = document.querySelector("dialog[data-pg-open-at-load]");
        if (drawer && window.pgUI) {
            window.pgUI.ready(function () {
                window.pgUI.drawer.show(drawer);
            });
        }
    }

    // "Save" in the screen's head submits the settings form from outside
    // its drawer. A field the browser refuses (the name left empty) sits in
    // the closed drawer, where its message cannot be shown: the drawer
    // opens and the form reports the field again.
    function watchSettings() {
        var drawer = document.getElementById("report_settings");
        var form = drawer ? drawer.closest("form") : null;
        if (!form || !window.pgUI) {
            return;
        }
        form.addEventListener("invalid", function () {
            if (drawer.open) {
                return;
            }
            window.pgUI.drawer.show(drawer);
            window.setTimeout(function () {
                form.reportValidity();
            }, 400);
        }, true);
    }

    function start() {
        openSettings();
        watchSettings();
        var root = document.getElementById("pg_report_view");
        var node = document.getElementById("pg_report_view_data");
        if (!root || !node) {
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
