/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's grid (includes/dashboard/v2/page.php). It reads the
 * configuration the page printed in #pg_dash_config, draws a frame for every
 * card of the layout (icon, title, chip and badge slots, the period pill),
 * asks api.php's dashboard_cards for their data in one batch (slow cards on
 * their own beside it), and hands each answer to the card's renderer. The
 * card groups' files register renderers on window.pgDash.renderers:
 *
 *     pgDash.renderers.<id> = function (body, data, card, config) { ... }
 *
 * drawing with DOM methods only; every text comes from the server. A card
 * the server answers "denied" is removed, one that fails shows a line and a
 * Try again button. The cards of the refresh list are asked for again at the
 * session's refresh time while the tab is visible.
 *
 * pg_dash_edit.js arranges the grid through pgDash.place(entries), reads it
 * back with pgDash.layout() and asks one card again with pgDash.reload().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    "use strict";

    var pgDash = window.pgDash = window.pgDash || {};
    var SVG = "http://www.w3.org/2000/svg";

    pgDash.renderers = pgDash.renderers || {};

    // ── Small DOM helpers the renderers share ──────────────────────────────

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

    pgDash.el = el;

    // Figure tiles: label, value and a toned note.
    pgDash.tiles = function (tiles) {
        var box = el("div", "pg-dash-figs");
        (tiles || []).forEach(function (tile) {
            var item = el("div", "pg-dash-fig");
            item.appendChild(el("span", "pg-dash-fig-label", tile.label));
            item.appendChild(el("span", "pg-dash-fig-value", tile.text));
            if (tile.note) {
                var note = el("span", "pg-dash-fig-note", tile.note);
                if (tile.tone) {
                    note.setAttribute("data-tone", tile.tone);
                }
                item.appendChild(note);
            }
            box.appendChild(item);
        });
        return box;
    };

    // ── Figures on hover and keyboard focus ────────────────────────────────

    // One floating box for the whole page shows the figure of the point,
    // bar or part under the pointer or the keyboard. It is aria-hidden: on
    // focus the same text goes to a live region beside the chart.
    var tip = null;

    function tipShow(text, x, y) {
        if (!tip) {
            tip = el("div", "pg-dash-tip");
            tip.setAttribute("aria-hidden", "true");
            document.body.appendChild(tip);
        }
        tip.textContent = text;
        tip.hidden = false;
        var width = tip.offsetWidth;
        var height = tip.offsetHeight;
        var left = Math.min(Math.max(8, x - width / 2), window.innerWidth - width - 8);
        var top = y - height - 10;
        if (top < 8) {
            top = y + 14;
        }
        tip.style.left = Math.round(left) + "px";
        tip.style.top = Math.round(top) + "px";
    }

    function tipHide() {
        if (tip) {
            tip.hidden = true;
        }
    }

    // Makes a chart show texts[i] for its i-th point. The chart is one tab
    // stop; Left/Right (Home/End) move between points, Escape hides the box.
    // opts.parts: elements, one per text, hovered on their own (a ring, a
    // stacked bar); without them the pointer's x picks the point.
    // opts.at(i): where point i is, {x, y} in viewport pixels (default: the
    // top centre of its part, or of its slice of the chart's width).
    // opts.mark(i): highlight point i (-1: none).
    pgDash.chartTips = function (target, texts, opts) {
        opts = opts || {};
        var count = (texts || []).length;
        if (!target || !count) {
            return;
        }
        var parts = opts.parts || null;
        var current = -1;
        var focused = false;
        var live = null;

        function at(i) {
            if (opts.at) {
                return opts.at(i);
            }
            var r = (parts && parts[i] ? parts[i] : target).getBoundingClientRect();
            if (parts) {
                return {x: r.left + r.width / 2, y: r.top};
            }
            return {x: r.left + ((i + 0.5) * r.width) / count, y: r.top};
        }

        function show(i, announce) {
            current = Math.max(0, Math.min(count - 1, i));
            var point = at(current);
            tipShow(texts[current], point.x, point.y);
            if (opts.mark) {
                opts.mark(current);
            }
            if (announce) {
                if (!live && target.parentNode) {
                    live = el("span", "visually-hidden");
                    live.setAttribute("aria-live", "polite");
                    target.parentNode.insertBefore(live, target.nextSibling);
                }
                if (live) {
                    live.textContent = texts[current];
                }
            }
        }

        function hide() {
            tipHide();
            if (opts.mark) {
                opts.mark(-1);
            }
        }

        target.setAttribute("tabindex", "0");
        target.classList.add("pg-dash-tips");

        if (parts) {
            parts.forEach(function (part, i) {
                if (part) {
                    part.addEventListener("mouseenter", function () {
                        show(i, false);
                    });
                }
            });
        } else {
            target.addEventListener("mousemove", function (event) {
                var r = target.getBoundingClientRect();
                var i = r.width ? Math.floor(((event.clientX - r.left) / r.width) * count) : 0;
                if (Math.max(0, Math.min(count - 1, i)) !== current || (tip && tip.hidden)) {
                    show(i, false);
                }
            });
        }

        target.addEventListener("mouseleave", function () {
            if (focused) {
                show(current, false);
            } else {
                hide();
            }
        });
        target.addEventListener("focus", function () {
            focused = true;
            show((current >= 0) ? current : count - 1, true);
        });
        target.addEventListener("blur", function () {
            focused = false;
            hide();
        });
        target.addEventListener("keydown", function (event) {
            var keys = {ArrowLeft: current - 1, ArrowDown: current - 1, ArrowRight: current + 1, ArrowUp: current + 1, Home: 0, End: count - 1};
            if (event.key === "Escape") {
                hide();
                return;
            }
            if (Object.prototype.hasOwnProperty.call(keys, event.key)) {
                event.preventDefault();
                show(keys[event.key], true);
            }
        });
    };

    // A marker for bar charts: the bar under the pointer or the keyboard
    // gets data-active.
    pgDash.markBars = function (svg) {
        return function (i) {
            Array.prototype.forEach.call(svg.querySelectorAll("rect"), function (rect, n) {
                if (n === i) {
                    rect.setAttribute("data-active", "1");
                } else {
                    rect.removeAttribute("data-active");
                }
            });
        };
    };

    // Horizontal bars under a small heading: text, the figure, and a bar
    // as long as its share of the largest row.
    pgDash.rows = function (title, rows) {
        var box = el("div", "pg-dash-rows");
        if (!rows || !rows.length) {
            return box;
        }
        box.appendChild(el("h3", "pg-dash-rows-title", title));
        var list = el("ol", "pg-dash-rows-list");
        var most = 0;
        rows.forEach(function (row) {
            most = Math.max(most, row.value || 0);
        });
        rows.forEach(function (row) {
            var item = el("li", "pg-dash-row");
            var text = el("span", "pg-dash-row-text", row.text);
            text.title = row.text;
            item.appendChild(text);
            item.appendChild(el("span", "pg-dash-row-note", row.note));
            var bar = el("span", "pg-dash-row-bar");
            bar.style.setProperty("--share", most > 0 ? String((row.value || 0) / most) : "0");
            item.appendChild(bar);
            list.appendChild(item);
        });
        box.appendChild(list);
        return box;
    };

    // A line of points with an optional dashed comparison line, as inline
    // SVG scaled to its box. The label says what it shows; the figures are
    // also in the tiles, so the picture is never the only place they are.
    pgDash.line = function (points, compare, label, compareLabel, tips) {
        var width = 600;
        var height = 160;
        var pad = 6;
        var all = (points || []).concat(compare || []);
        var max = Math.max.apply(null, all.concat([1]));
        var count = Math.max((points || []).length, (compare || []).length, 2);

        var svg = svgEl("svg", {viewBox: "0 0 " + width + " " + height, preserveAspectRatio: "none", role: "img", "class": "pg-dash-line"});
        var title = svgEl("title", {});
        title.textContent = label + (compareLabel ? " · " + compareLabel : "");
        svg.appendChild(title);

        function path(series) {
            return series.map(function (value, i) {
                var x = pad + (i * (width - 2 * pad)) / (count - 1);
                var y = height - pad - ((value || 0) / max) * (height - 2 * pad);
                return (i ? "L" : "M") + x.toFixed(1) + " " + y.toFixed(1);
            }).join(" ");
        }

        if (compare && compare.length) {
            svg.appendChild(svgEl("path", {d: path(compare), "class": "pg-dash-line-compare", "vector-effect": "non-scaling-stroke"}));
        }
        if (points && points.length) {
            svg.appendChild(svgEl("path", {d: path(points), "class": "pg-dash-line-main", "vector-effect": "non-scaling-stroke"}));
        }

        // The hovered or focused point: a thin rule across the chart. The
        // line is stretched to its box, so a dot would turn into an oval.
        if (tips && tips.length) {
            var rule = svgEl("line", {y1: 0, y2: height, "class": "pg-dash-line-rule", "vector-effect": "non-scaling-stroke", visibility: "hidden"});
            svg.appendChild(rule);
            var xOf = function (i) {
                return pad + (i * (width - 2 * pad)) / (count - 1);
            };
            pgDash.chartTips(svg, tips, {
                at: function (i) {
                    var r = svg.getBoundingClientRect();
                    var value = Math.max((points || [])[i] || 0, (compare || [])[i] || 0);
                    return {x: r.left + (xOf(i) / width) * r.width, y: r.top + ((height - pad - (value / max) * (height - 2 * pad)) / height) * r.height};
                },
                mark: function (i) {
                    rule.setAttribute("visibility", (i < 0) ? "hidden" : "visible");
                    if (i >= 0) {
                        rule.setAttribute("x1", xOf(i).toFixed(1));
                        rule.setAttribute("x2", xOf(i).toFixed(1));
                    }
                }
            });
        }
        return svg;
    };

    // Vertical bars, one per value, as inline SVG.
    pgDash.bars = function (values, label, tips) {
        var width = 300;
        var height = 60;
        var max = Math.max.apply(null, (values || []).concat([1]));
        var step = width / Math.max((values || []).length, 1);
        var svg = svgEl("svg", {viewBox: "0 0 " + width + " " + height, preserveAspectRatio: "none", role: "img", "class": "pg-dash-bars"});
        var title = svgEl("title", {});
        title.textContent = label;
        svg.appendChild(title);
        (values || []).forEach(function (value, i) {
            var h = Math.max(1, ((value || 0) / max) * height);
            svg.appendChild(svgEl("rect", {x: (i * step + step * 0.15).toFixed(1), y: (height - h).toFixed(1), width: (step * 0.7).toFixed(1), height: h.toFixed(1), rx: 1.5}));
        });
        if (tips && tips.length) {
            pgDash.chartTips(svg, tips, {mark: pgDash.markBars(svg)});
        }
        return svg;
    };

    // ── Card frames ────────────────────────────────────────────────────────

    var config = null;
    var frames = {};
    // Frames taken off the grid (a removed card, another preset) wait here,
    // so putting one back needs no new request.
    var parked = {};

    function frame(entry) {
        var meta = config.cards[entry.id];
        var box = el("section", "card pg-dash-card pg-dash-cardbox");
        var titleId = "pg_dash_card_title_" + entry.id;

        box.setAttribute("data-card", entry.id);
        box.setAttribute("data-width", entry.w);
        box.setAttribute("data-badge", entry.badge ? "1" : "0");
        box.setAttribute("data-category", meta.category);
        box.setAttribute("aria-labelledby", titleId);
        box.setAttribute("aria-busy", "true");

        var head = el("header", "pg-dash-card-head");
        var icon = el("span", "pg-dash-card-icon");
        icon.setAttribute("aria-hidden", "true");
        icon.appendChild(el("i", "bi " + meta.icon));
        head.appendChild(icon);

        var title = el("h2", "pg-dash-card-title", meta.title);
        title.id = titleId;
        title.tabIndex = -1;
        head.appendChild(title);

        var chip = el("span", "pg-dash-card-chip");
        chip.hidden = true;
        head.appendChild(chip);

        var badge = el("span", "pg-dash-card-badge");
        badge.hidden = true;
        head.appendChild(badge);

        if ((meta.period !== null) && (entry.per !== null)) {
            var pill = el("button", "pg-dash-card-period");
            pill.type = "button";
            head.appendChild(pill);
            pill.addEventListener("click", function () {
                var current = frames[entry.id].entry;
                current.per = (current.per + 1) % 3;
                pgDash.reload(current);
                if (typeof pgDash.periodChanged === "function") {
                    pgDash.periodChanged(current);
                }
            });
        }

        if (!config.edit.locked) {
            var more = el("button", "pg-dash-card-edit");
            more.type = "button";
            more.setAttribute("aria-label", (config.strings.edit_card || "").replace("{var:1}", meta.title));
            more.appendChild(el("i", "bi bi-three-dots"));
            more.firstChild.setAttribute("aria-hidden", "true");
            more.addEventListener("click", function () {
                if (pgDash.edit) {
                    pgDash.edit.enter(entry.id);
                }
            });
            head.appendChild(more);
        }

        box.appendChild(head);

        var body = el("div", "pg-dash-card-body");
        body.appendChild(skeleton());
        box.appendChild(body);

        frames[entry.id] = {box: box, body: body, entry: entry};
        drawPeriod(entry);

        return box;
    }

    function drawPeriod(entry) {
        var item = frames[entry.id];
        var pill = item && item.box.querySelector(".pg-dash-card-period");
        if (!pill) {
            return;
        }
        var name = config.periods[entry.per] || "";
        pill.textContent = name;
        pill.setAttribute("aria-label", (config.strings.period || "").replace("{var:1}", name));
    }

    function skeleton() {
        var box = el("div", "pg-dash-skeleton");
        box.setAttribute("aria-hidden", "true");
        for (var i = 0; i < 3; i++) {
            box.appendChild(el("span", "pg-dash-skeleton-line"));
        }
        return box;
    }

    // Draw a card's body again in place. Focus inside it goes back to the
    // same kind of control, or to the card's title.
    function draw(id, answer) {
        var item = frames[id];
        if (!item) {
            return;
        }

        // A figure box over a chart that is about to go would stay behind.
        tipHide();

        var hadFocus = item.body.contains(document.activeElement);
        var focusClass = hadFocus ? document.activeElement.className : "";

        if (answer.status === "denied") {
            item.box.remove();
            delete frames[id];
            emptyCheck();
            return;
        }

        item.body.textContent = "";
        item.box.removeAttribute("aria-busy");

        if (answer.status !== "success") {
            item.body.appendChild(error(item.entry));
        } else if (answer.data && answer.data.empty) {
            item.body.appendChild(el("p", "pg-dash-card-empty", answer.data.empty));
        } else if (typeof pgDash.renderers[id] === "function") {
            try {
                pgDash.renderers[id](item.body, answer.data || {}, item.entry, config);
            } catch (e) {
                item.body.textContent = "";
                item.body.appendChild(error(item.entry));
            }
        }

        if (hadFocus) {
            var again = focusClass ? item.body.querySelector("." + focusClass.split(" ")[0]) : null;
            (again || item.box.querySelector(".pg-dash-card-title")).focus({preventScroll: true});
        }
    }

    function error(entry) {
        var box = el("div", "pg-dash-card-error");
        box.appendChild(el("p", "", config.strings.error));
        var retry = el("button", "btn btn-outline theme-secondary btn-sm pg-dash-retry", config.strings.retry);
        retry.type = "button";
        retry.addEventListener("click", function () {
            load([entry]);
        });
        box.appendChild(retry);
        return box;
    }

    function emptyCheck() {
        var grid = document.getElementById("pg_dash_grid");
        if (grid && !grid.querySelector(".pg-dash-cardbox") && !grid.querySelector(".pg-dash-empty")) {
            grid.appendChild(el("p", "pg-dash-empty", config.strings.empty));
        }
    }

    // ── Arranging the grid ─────────────────────────────────────────────────

    // The grid in this order: frames already drawn move, parked ones come
    // back, new ones are made; the rest are parked. A card whose period or
    // compare differs from its frame's is asked for again.
    pgDash.place = function (entries) {
        var grid = document.getElementById("pg_dash_grid");
        var keep = {};
        var fresh = [];

        entries.forEach(function (entry) {
            if (!config.cards[entry.id] || keep[entry.id]) {
                return;
            }
            keep[entry.id] = true;

            var item = frames[entry.id] || parked[entry.id];

            if (!item) {
                grid.appendChild(frame(entry));
                fresh.push(entry);
                return;
            }

            delete parked[entry.id];
            frames[entry.id] = item;

            if ((item.entry.per !== entry.per) || (item.entry.cmp !== entry.cmp)) {
                fresh.push(entry);
            }

            item.entry = entry;
            item.box.setAttribute("data-width", entry.w);
            item.box.setAttribute("data-badge", entry.badge ? "1" : "0");
            grid.appendChild(item.box);
            drawPeriod(entry);
        });

        Object.keys(frames).forEach(function (id) {
            if (!keep[id]) {
                parked[id] = frames[id];
                frames[id].box.remove();
                delete frames[id];
            }
        });

        var empty = grid.querySelector(".pg-dash-empty");
        if (empty) {
            empty.remove();
        }
        emptyCheck();

        // The Add card tile (pg_dash_panel.js) stays the grid's last cell.
        var tile = grid.querySelector(".pg-dash-addtile");
        if (tile) {
            grid.appendChild(tile);
        }

        loadAll(fresh);
    };

    // The cards on the grid in their order, as layout entries.
    pgDash.layout = function () {
        var grid = document.getElementById("pg_dash_grid");
        return Array.prototype.map.call(grid.querySelectorAll(".pg-dash-cardbox"), function (box) {
            return frames[box.getAttribute("data-card")].entry;
        });
    };

    pgDash.frame = function (id) {
        return frames[id] || null;
    };

    pgDash.reload = function (entry) {
        drawPeriod(entry);
        load([entry]);
    };

    // ── Data ───────────────────────────────────────────────────────────────

    function load(entries) {
        if (!entries.length) {
            return;
        }

        var asked = entries.map(function (entry) {
            return {id: entry.id, per: entry.per === null ? 0 : entry.per, cmp: entry.cmp};
        });

        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({action: "dashboard_cards", cards: asked}),
            type: "POST",
            dataType: "json"
        }).done(function (answer) {
            var cards = (answer && answer.cards) || {};
            entries.forEach(function (entry) {
                draw(entry.id, cards[entry.id] || {status: "error"});
            });
        }).fail(function () {
            entries.forEach(function (entry) {
                draw(entry.id, {status: "error"});
            });
        });
    }

    // One batch for the ordinary cards, and each slow card on its own.
    function loadAll(entries) {
        var solo = config.timing.solo || [];
        var batch = [];

        entries.forEach(function (entry) {
            if (solo.indexOf(entry.id) !== -1) {
                load([entry]);
            } else {
                batch.push(entry);
            }
        });

        load(batch);
    }

    function refresh() {
        if (document.visibilityState !== "visible") {
            return;
        }

        var list = config.timing.refresh || [];
        loadAll(Object.keys(frames).filter(function (id) {
            return list.indexOf(id) !== -1;
        }).map(function (id) {
            return frames[id].entry;
        }));
    }

    function start() {
        var grid = document.getElementById("pg_dash_grid");
        var node = document.getElementById("pg_dash_config");

        if (!grid || !node) {
            return;
        }

        try {
            config = JSON.parse(node.textContent || "{}");
        } catch (e) {
            return;
        }

        pgDash.config = config;
        grid.textContent = "";

        var entries = (config.layout || []).filter(function (entry) {
            return !!config.cards[entry.id];
        });

        entries.forEach(function (entry) {
            grid.appendChild(frame(entry));
        });

        emptyCheck();
        loadAll(entries);

        // A hidden tab is skipped by refresh(); coming back asks at once.
        var seconds = Math.max(10, parseInt(config.refresh, 10) || 60);
        window.setInterval(refresh, seconds * 1000);
        document.addEventListener("visibilitychange", refresh);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start);
    } else {
        start();
    }
})();
