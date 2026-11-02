/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's Content cards and the Activity card (assets/js/
 * pg_dash.js draws the frames): Content and design, Comment moderation,
 * File management and Activity. The data is what
 * includes/dashboard/v2/cards/<id>.php returns; every text comes from it.
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

    function link(body, url, text) {
        if (!url || !text) {
            return;
        }
        var more = pgDash.el("a", "pg-dash-card-more", text);
        more.href = url;
        body.appendChild(more);
    }

    function svg(tag, attrs) {
        var node = document.createElementNS(SVG, tag);
        Object.keys(attrs || {}).forEach(function (name) {
            node.setAttribute(name, attrs[name]);
        });
        return node;
    }

    // ── Content and design ─────────────────────────────────────────────────

    pgDash.renderers.content = function (body, data) {
        var el = pgDash.el;
        var list = el("ul", "pg-dash-pages");

        (data.pages || []).forEach(function (page) {
            var item = el("li", "pg-dash-page");
            var open = el("a", "pg-dash-page-link");
            open.href = page.url;

            // A drawn sketch of a page, not a picture of it.
            var sketch = el("span", "pg-dash-page-sketch");
            sketch.setAttribute("aria-hidden", "true");
            sketch.setAttribute("data-state", page.state);
            for (var i = 0; i < 3; i++) {
                sketch.appendChild(el("span", "pg-dash-page-sketch-line"));
            }
            open.appendChild(sketch);
            open.appendChild(el("span", "pg-dash-page-name", page.text));

            var meta = el("span", "pg-dash-page-meta");
            var chip = el("span", "pg-dash-state", page.chip);
            chip.setAttribute("data-state", page.state);
            meta.appendChild(chip);
            meta.appendChild(el("span", "pg-dash-page-when", page.note));
            open.appendChild(meta);

            item.appendChild(open);
            list.appendChild(item);
        });

        body.appendChild(list);
        link(body, data.url, data.link);
    };

    // ── Comment moderation ─────────────────────────────────────────────────

    pgDash.renderers.comments = function (body, data) {
        var el = pgDash.el;

        var head = el("p", "pg-dash-count");
        head.appendChild(el("span", "pg-dash-count-label", data.title));
        head.appendChild(el("span", "pg-dash-count-value", data.count));
        body.appendChild(head);

        var list = el("ul", "pg-dash-bubbles");

        (data.rows || []).forEach(function (row) {
            var item = el("li", "pg-dash-bubble");
            var avatar = el("span", "pg-dash-avatar", row.initials);
            avatar.setAttribute("aria-hidden", "true");
            item.appendChild(avatar);

            var main = el("div", "pg-dash-bubble-main");
            var who = el("p", "pg-dash-bubble-who", row.name);
            if (row.rating > 0) {
                var stars = el("span", "pg-dash-stars");
                stars.setAttribute("role", "img");
                stars.setAttribute("aria-label", String(data.rating || "").replace("{var:1}", row.rating));
                stars.textContent = new Array(row.rating + 1).join("★");
                who.appendChild(stars);
            }
            main.appendChild(who);
            main.appendChild(el("p", "pg-dash-bubble-text", row.text));

            var foot = el("div", "pg-dash-bubble-foot");
            foot.appendChild(el("span", "pg-dash-bubble-note", row.note));
            var approve = el("a", "btn btn-outline theme-secondary btn-sm pg-dash-approve", data.approve);
            approve.href = row.url;
            foot.appendChild(approve);
            main.appendChild(foot);

            item.appendChild(main);
            list.appendChild(item);
        });

        body.appendChild(list);

        if (data.more) {
            body.appendChild(el("p", "pg-dash-card-note", data.more));
        }
        link(body, data.url, data.link);
    };

    // ── File management ────────────────────────────────────────────────────

    // The storage ring: one arc per part, the used size in the middle. The
    // parts are listed beside it with their sizes, so the ring is never the
    // only place the figures are.
    function ring(parts, label, used, usedNote) {
        var box = pgDash.el("div", "pg-dash-ring");
        var total = 0;
        parts.forEach(function (part) {
            total += part.value || 0;
        });

        var radius = 15.915;
        var picture = svg("svg", {viewBox: "0 0 36 36", role: "img", "class": "pg-dash-ring-svg"});
        var title = svg("title", {});
        title.textContent = label;
        picture.appendChild(title);
        picture.appendChild(svg("circle", {cx: 18, cy: 18, r: radius, "class": "pg-dash-ring-track"}));

        var offset = 0;
        parts.forEach(function (part) {
            if (!total || !part.value) {
                return;
            }
            var share = (part.value / total) * 100;
            picture.appendChild(svg("circle", {
                cx: 18, cy: 18, r: radius,
                "class": "pg-dash-ring-part",
                "data-part": part.key,
                "stroke-dasharray": share.toFixed(2) + " " + (100 - share).toFixed(2),
                "stroke-dashoffset": (25 - offset).toFixed(2)
            }));
            offset += share;
        });
        box.appendChild(picture);

        var middle = pgDash.el("span", "pg-dash-ring-middle");
        middle.appendChild(pgDash.el("span", "pg-dash-ring-value", used));
        middle.appendChild(pgDash.el("span", "pg-dash-ring-note", usedNote));
        box.appendChild(middle);
        return box;
    }

    pgDash.renderers.files = function (body, data) {
        var el = pgDash.el;
        var top = el("div", "pg-dash-storage");
        top.appendChild(ring(data.parts || [], data.ring_label, data.used, data.used_note));

        var keys = el("ul", "pg-dash-storage-keys");
        (data.parts || []).forEach(function (part) {
            var item = el("li", "pg-dash-storage-key");
            item.setAttribute("data-part", part.key);
            item.appendChild(el("span", "pg-dash-storage-label", part.label));
            item.appendChild(el("span", "pg-dash-storage-note", part.note));
            keys.appendChild(item);
        });
        top.appendChild(keys);
        body.appendChild(top);

        if ((data.rows || []).length) {
            body.appendChild(el("h3", "pg-dash-rows-title", data.rows_title));
            var list = el("ul", "pg-dash-items");
            data.rows.forEach(function (row) {
                var item = el("li", "pg-dash-item");
                var open = el("a", "pg-dash-item-link");
                open.href = row.url;
                var tag = el("span", "pg-dash-item-tag", row.type);
                tag.setAttribute("aria-hidden", "true");
                open.appendChild(tag);
                var text = el("span", "pg-dash-item-main");
                text.appendChild(el("span", "pg-dash-item-text", row.text));
                text.appendChild(el("span", "pg-dash-item-note", row.note));
                open.appendChild(text);
                item.appendChild(open);
                list.appendChild(item);
            });
            body.appendChild(list);
        }
        link(body, data.url, data.link);
    };

    // ── Activity ───────────────────────────────────────────────────────────

    // The chosen chip, kept across redraws so a refresh shows the same one.
    var activityChip = "all";

    pgDash.renderers.activity = function (body, data) {
        var el = pgDash.el;
        var shown = data.shown || 5;
        var chosen = activityChip;

        var chips = el("div", "pg-dash-chips");
        chips.setAttribute("role", "group");
        chips.setAttribute("aria-label", data.chips_label);

        var list = el("ol", "pg-dash-timeline");
        var none = el("p", "pg-dash-card-empty", data.none);

        function fill() {
            list.textContent = "";
            var rows = (data.rows || []).filter(function (row) {
                return (chosen === "all") || (row.chip === chosen);
            }).slice(0, shown);

            rows.forEach(function (row) {
                var item = el("li", "pg-dash-event");
                item.setAttribute("data-chip", row.chip || "other");
                var main = el(row.url ? "a" : "span", "pg-dash-event-main");
                if (row.url) {
                    main.href = row.url;
                }
                main.appendChild(el("span", "pg-dash-event-text", row.text));
                if (row.note) {
                    main.appendChild(el("span", "pg-dash-event-note", row.note));
                }
                item.appendChild(main);
                item.appendChild(el("span", "pg-dash-event-when", row.when));
                list.appendChild(item);
            });

            none.hidden = rows.length > 0;
            Array.prototype.forEach.call(chips.children, function (button) {
                button.setAttribute("aria-pressed", button.getAttribute("data-chip") === chosen ? "true" : "false");
            });
        }

        (data.chips || []).forEach(function (chip) {
            var button = el("button", "pg-dash-chip", chip.label);
            button.type = "button";
            button.setAttribute("data-chip", chip.key);
            button.addEventListener("click", function () {
                chosen = chip.key;
                activityChip = chosen;
                fill();
            });
            chips.appendChild(button);
        });

        body.appendChild(chips);
        body.appendChild(list);
        body.appendChild(none);
        fill();
        link(body, data.url, data.link);
    };
})();
