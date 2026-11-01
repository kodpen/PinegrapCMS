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
    pgDash.line = function (points, compare, label, compareLabel) {
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
        return svg;
    };

    // Vertical bars, one per value, as inline SVG.
    pgDash.bars = function (values, label) {
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
        return svg;
    };

    // ── Card frames ────────────────────────────────────────────────────────

    var config = null;
    var frames = {};

    function frame(entry) {
        var meta = config.cards[entry.id];
        var box = el("section", "card pg-dash-card pg-dash-cardbox");
        var titleId = "pg_dash_card_title_" + entry.id;

        box.setAttribute("data-card", entry.id);
        box.setAttribute("data-width", entry.w);
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
                entry.per = (entry.per + 1) % 3;
                drawPeriod(entry);
                load([entry]);
            });
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
