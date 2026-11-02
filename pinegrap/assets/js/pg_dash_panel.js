/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's side panel (includes/dashboard/v2/page.php): in edit
 * mode it stands beside the greeting, status and card grid (below them on a
 * narrow screen) with three sections:
 *
 *   Add card     search, category pills, and every card the person may see
 *                with a small sketch, NEW and RECOMMENDED tags, its line and
 *                Add or "On the dashboard · Remove";
 *   Top section  switches for the greeting's parts and System Status;
 *   Look         card style, backdrop, density, lights and shadows, shown
 *                at once and saved with the layout;
 *   Share        (administrators and designers) the layout and look on
 *                screen as a role's default, locked or not, sent at once
 *                to api.php's dashboard_role_default.
 *
 * It also draws the Add card tile that ends the grid, which opens edit mode
 * on the gallery. pg_dash_edit.js owns the state and calls the hooks on
 * pgDash.panel (opened, closed, cancel, saved, changed). Every text comes
 * from the page's config.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    "use strict";

    var pgDash = window.pgDash = window.pgDash || {};
    var el = pgDash.el;
    var SVG = "http://www.w3.org/2000/svg";

    var config = null;
    var S = {};
    var root = null;
    var aside = null;
    var work = null;
    var list = null;
    var category = "all";
    var query = "";
    // The look shown and the look saved; they differ while a pick is unsaved.
    var look = null;
    var savedLook = null;
    var looked = false;

    function fmt(text, one) {
        return String(text || "").replace("{var:1}", one);
    }

    function announce(text) {
        if (typeof window.pgAnnounce === "function") {
            window.pgAnnounce(text);
        }
    }

    function button(className, text, label) {
        var node = el("button", className, text);
        node.type = "button";
        if (label) {
            node.setAttribute("aria-label", label);
        }
        return node;
    }

    // Case, Turkish dotted and dotless i and marks folded, so "isik" finds
    // "Işık" and "HARITA" finds "Harita".
    function fold(text) {
        var s = (typeof window.pgFoldTR === "function") ? window.pgFoldTR(text) : String(text).toLowerCase();
        return s.normalize ? s.normalize("NFD").replace(/[̀-ͯ]/g, "") : s;
    }

    // ── Sketches ───────────────────────────────────────────────────────────

    // A few shapes in place of a picture of each card; colours come from
    // the stylesheet. Kinds are pg_dashboard_v2_sketches().
    var sketches = {
        line: function (box) {
            var svg = document.createElementNS(SVG, "svg");
            svg.setAttribute("viewBox", "0 0 120 56");
            svg.setAttribute("preserveAspectRatio", "none");
            var area = document.createElementNS(SVG, "polygon");
            area.setAttribute("points", "0,56 0,48 15,46 30,40 45,42 60,30 75,26 90,18 105,20 120,10 120,56");
            var line = document.createElementNS(SVG, "polyline");
            line.setAttribute("points", "0,48 15,46 30,40 45,42 60,30 75,26 90,18 105,20 120,10");
            svg.appendChild(area);
            svg.appendChild(line);
            box.appendChild(svg);
        },
        bars: function (box) {
            [40, 55, 48, 70, 62, 85, 74, 92].forEach(function (h) {
                box.appendChild(el("span")).style.height = h + "%";
            });
        },
        funnel: function (box) {
            [92, 66, 44, 26].forEach(function (h) {
                box.appendChild(el("span")).style.height = h + "%";
            });
        },
        rows: function (box) {
            [100, 80, 60].forEach(function (w) {
                var row = box.appendChild(el("span", "pg-dash-sk-row"));
                row.appendChild(el("span", "pg-dash-sk-dot"));
                row.appendChild(el("span", "pg-dash-sk-line")).style.width = w + "%";
            });
        },
        timeline: function (box) {
            sketches.rows(box);
        },
        rings: function (box) {
            [86, 62, 35].forEach(function (p) {
                box.appendChild(el("span", "pg-dash-sk-ring")).style.setProperty("--pg-sk-p", p + "%");
            });
        },
        avatars: function (box) {
            for (var i = 0; i < 4; i++) {
                box.appendChild(el("span", "pg-dash-sk-ring"));
            }
        },
        chat: function (box) {
            box.appendChild(el("span", "pg-dash-sk-bubble"));
            box.appendChild(el("span", "pg-dash-sk-bubble pg-dash-sk-mine"));
            box.appendChild(el("span", "pg-dash-sk-line")).style.width = "40%";
        },
        note: function (box) {
            [80, 65, 72].forEach(function (w) {
                box.appendChild(el("span", "pg-dash-sk-line")).style.width = w + "%";
            });
        },
        boxes: function (box) {
            for (var i = 0; i < 4; i++) {
                box.appendChild(el("span"));
            }
        },
        cells: function (box) {
            for (var i = 0; i < 21; i++) {
                box.appendChild(el("span", (i === 11) ? "pg-dash-sk-on" : ""));
            }
        },
        map: function (box) {
            var land = box.appendChild(el("span", "pg-dash-sk-land"));
            [[20, 30], [55, 55], [78, 35]].forEach(function (at) {
                var dot = land.appendChild(el("span", "pg-dash-sk-dot"));
                dot.style.left = at[0] + "%";
                dot.style.top = at[1] + "%";
            });
        },
        live: function (box) {
            box.appendChild(el("span", "pg-dash-sk-pulse"));
            sketches.bars(box);
        },
        gauge: function (box) {
            box.appendChild(el("span", "pg-dash-sk-ring"));
        },
        flow: function (box) {
            for (var i = 0; i < 5; i++) {
                box.appendChild(el("span", (i < 3) ? "pg-dash-sk-on" : ""));
            }
        }
    };

    function sketch(kind) {
        var box = el("span", "pg-dash-sketch");
        box.setAttribute("aria-hidden", "true");
        box.setAttribute("data-sketch", sketches[kind] ? kind : "boxes");
        (sketches[kind] || sketches.boxes)(box);
        return box;
    }

    // ── The gallery ────────────────────────────────────────────────────────

    function onBoard() {
        var ids = {};
        pgDash.layout().forEach(function (entry) {
            ids[entry.id] = true;
        });
        return ids;
    }

    function recommended() {
        var preset = config.edit.presets[pgDash.edit.state().preset];
        return (preset && preset.recommended) || [];
    }

    // The cards the category and the search let through, in the list's order.
    function matches() {
        var words = fold(query).split(/\s+/).filter(Boolean);
        var picks = recommended();

        return Object.keys(config.cards).filter(function (id) {
            var card = config.cards[id];
            if ((category === "recommended") && (picks.indexOf(id) === -1)) {
                return false;
            }
            if ((category !== "all") && (category !== "recommended") && (card.category !== category)) {
                return false;
            }
            var text = fold(card.title + " " + card.desc);
            return words.every(function (word) {
                return text.indexOf(word) !== -1;
            });
        });
    }

    function drawList() {
        if (!list) {
            return;
        }

        var focused = document.activeElement && list.contains(document.activeElement)
            ? document.activeElement.getAttribute("data-gal")
            : null;
        var board = onBoard();
        var picks = recommended();
        var ids = matches();

        list.textContent = "";

        if (!ids.length) {
            list.appendChild(el("p", "pg-dash-gal-none", S.no_match));
        }

        ids.forEach(function (id) {
            var card = config.cards[id];
            var on = !!board[id];
            var item = el("div", "pg-dash-gal-item");
            item.setAttribute("data-on", on ? "1" : "0");
            item.appendChild(sketch(card.sketch));

            var head = el("span", "pg-dash-gal-head");
            head.appendChild(el("strong", "pg-dash-gal-title", card.title));
            if (card["new"]) {
                head.appendChild(el("span", "pg-dash-tag pg-dash-tag-new", S.tag_new));
            }
            if (!on && (picks.indexOf(id) !== -1)) {
                head.appendChild(el("span", "pg-dash-tag pg-dash-tag-rec", S.recommended));
            }
            item.appendChild(head);
            item.appendChild(el("span", "pg-dash-gal-desc", card.desc));

            var act = on
                ? button("btn btn-outline theme-secondary btn-sm pg-dash-gal-act", S.on_board, fmt(S.remove_named, card.title))
                : button("btn btn-outline theme-primary btn-sm pg-dash-gal-act", S.add, fmt(S.add_named, card.title));
            act.setAttribute("data-gal", id);
            act.addEventListener("click", function () {
                if (on) {
                    pgDash.edit.remove(id);
                } else {
                    add(id);
                }
            });
            item.appendChild(act);
            list.appendChild(item);
        });

        if (focused) {
            var again = list.querySelector('[data-gal="' + focused + '"]');
            (again || aside.querySelector(".pg-dash-gal-search")).focus();
        }
    }

    // An added card goes last at its default width and period.
    function add(id) {
        var card = config.cards[id];
        var entries = pgDash.layout().slice();

        entries.push({id: id, w: card.width, per: card.period, cmp: 0, badge: 1});
        pgDash.place(entries);
        pgDash.edit.cardsChanged();
        announce(fmt(S.added, card.title));
    }

    function drawGallery() {
        var section = el("section", "pg-dash-side-part");
        section.setAttribute("aria-labelledby", "pg_dash_gal_title");
        section.appendChild(el("h2", "pg-dash-side-title", S.add_card)).id = "pg_dash_gal_title";

        var label = el("label", "visually-hidden", S.search_label);
        label.setAttribute("for", "pg_dash_gal_search");
        section.appendChild(label);
        var search = el("input", "form-control form-control-sm pg-dash-gal-search");
        search.type = "search";
        search.id = "pg_dash_gal_search";
        search.placeholder = S.search;
        search.value = query;
        search.addEventListener("input", function () {
            query = search.value;
            drawList();
        });
        section.appendChild(search);

        var pills = el("div", "pg-dash-gal-pills");
        pills.setAttribute("role", "group");
        pills.setAttribute("aria-labelledby", "pg_dash_gal_title");
        var names = {all: S.cat_all, recommended: S.recommended};
        Object.keys(config.categories).forEach(function (key) {
            names[key] = config.categories[key];
        });
        Object.keys(names).forEach(function (key) {
            var pill = button("pg-dash-pill", names[key]);
            pill.setAttribute("data-cat", key);
            pill.setAttribute("aria-pressed", key === category ? "true" : "false");
            pill.addEventListener("click", function () {
                category = key;
                pills.querySelectorAll("[data-cat]").forEach(function (other) {
                    other.setAttribute("aria-pressed", other === pill ? "true" : "false");
                });
                drawList();
            });
            pills.appendChild(pill);
        });
        section.appendChild(pills);

        list = el("div", "pg-dash-gal-list");
        section.appendChild(list);
        drawList();

        return section;
    }

    // ── Top section ────────────────────────────────────────────────────────

    function drawTop() {
        var section = el("section", "pg-dash-side-part");
        section.setAttribute("aria-labelledby", "pg_dash_top_title");
        section.appendChild(el("h2", "pg-dash-side-title", S.top_title)).id = "pg_dash_top_title";

        ["since", "actions", "tiles", "status"].forEach(function (part) {
            if ((part === "status") && !document.getElementById("pg_dash_status")) {
                return;
            }

            var field = el("div", "form-field");
            var box = el("input", "check");
            box.type = "checkbox";
            box.id = "pg_dash_top_" + part;
            box.setAttribute("data-top", part);
            box.addEventListener("change", function () {
                pgDash.edit.state().top[part] = box.checked ? 1 : 0;
                if (part === "status") {
                    pgDash.edit.statusChanged();
                } else {
                    pgDash.edit.greetingChanged();
                }
            });
            field.appendChild(box);
            var label = el("label", "", S["top_" + part]);
            label.setAttribute("for", box.id);
            field.appendChild(label);
            section.appendChild(field);
        });

        return section;
    }

    function syncTop() {
        if (!aside) {
            return;
        }
        var top = pgDash.edit.state().top;
        aside.querySelectorAll("[data-top]").forEach(function (box) {
            box.checked = !!top[box.getAttribute("data-top")];
        });
    }

    // ── Look ───────────────────────────────────────────────────────────────

    function applyLook() {
        var choices = config.edit.choices;
        root.setAttribute("data-widget-theme", look.style);
        root.setAttribute("data-panel-backdrop", (look.backdrop === "auto") ? choices.auto[look.style] : look.backdrop);
        root.classList.toggle("pg-dash-compact", !!look.dense);
        root.classList.toggle("pg-dash-calm", !!look.calm);
    }

    // A labelled row of pressed buttons for one look value.
    function lookRow(key, title, names) {
        var row = el("div", "pg-dash-settings-row");
        var label = el("span", "pg-dash-settings-label", title);
        label.id = "pg_dash_look_" + key;
        row.appendChild(label);

        var group = el("div", "btn-group btn-group-sm pg-dash-segment");
        group.setAttribute("role", "group");
        group.setAttribute("aria-labelledby", label.id);

        Object.keys(names).forEach(function (value) {
            var stored = (typeof look[key] === "number") ? parseInt(value, 10) : value;
            var pick = button("btn btn-outline theme-secondary", names[value]);
            pick.setAttribute("aria-pressed", look[key] === stored ? "true" : "false");
            pick.addEventListener("click", function () {
                look[key] = stored;
                looked = true;
                applyLook();
                group.querySelectorAll("button").forEach(function (other) {
                    other.setAttribute("aria-pressed", other === pick ? "true" : "false");
                });
            });
            group.appendChild(pick);
        });

        row.appendChild(group);
        return row;
    }

    function drawLook() {
        var section = el("section", "pg-dash-side-part");
        section.setAttribute("aria-labelledby", "pg_dash_look_title");
        section.appendChild(el("h2", "pg-dash-side-title", S.look_title)).id = "pg_dash_look_title";

        section.appendChild(lookRow("style", S.look_style, config.edit.choices.style));
        section.appendChild(lookRow("backdrop", S.look_back, config.edit.choices.backdrop));
        section.appendChild(lookRow("dense", S.look_dense, S.dense));
        var calm = lookRow("calm", S.look_calm, S.calm);
        var hint = el("p", "pg-dash-side-hint", S.calm_hint);
        hint.id = "pg_dash_calm_hint";
        calm.querySelector("[role=group]").setAttribute("aria-describedby", hint.id);
        calm.appendChild(hint);
        section.appendChild(calm);

        return section;
    }

    // ── Share with the team ────────────────────────────────────────────────

    function drawShare(share) {
        var section = el("section", "pg-dash-side-part");
        section.setAttribute("aria-labelledby", "pg_dash_share_title");
        section.appendChild(el("h2", "pg-dash-side-title", share.title)).id = "pg_dash_share_title";
        section.appendChild(el("p", "pg-dash-side-hint", share.hint));

        var label = el("label", "pg-dash-settings-label", share.label);
        label.setAttribute("for", "pg_dash_share_role");
        section.appendChild(label);
        var select = el("select", "form-control form-control-sm");
        select.id = "pg_dash_share_role";
        share.roles.forEach(function (pair) {
            var option = el("option", "", pair[1]);
            option.value = String(pair[0]);
            select.appendChild(option);
        });
        section.appendChild(select);

        var field = el("div", "form-field");
        var lock = el("input", "check");
        lock.type = "checkbox";
        lock.id = "pg_dash_share_lock";
        field.appendChild(lock);
        var lockLabel = el("label", "", share.lock);
        lockLabel.setAttribute("for", lock.id);
        field.appendChild(lockLabel);
        section.appendChild(field);

        var row = el("div", "pg-dash-share-row");
        var make = button("btn btn-outline theme-secondary btn-sm", share.button);
        var done = el("span", "pg-dash-saved");
        done.setAttribute("role", "status");
        done.hidden = true;
        row.appendChild(make);
        row.appendChild(done);
        section.appendChild(row);

        // A new choice hides the last confirmation, which named another one.
        [select, lock].forEach(function (input) {
            input.addEventListener("change", function () {
                done.hidden = true;
            });
        });

        make.addEventListener("click", function () {
            make.disabled = true;
            done.hidden = true;
            $.ajax({
                contentType: "application/json",
                url: "api.php",
                data: JSON.stringify({
                    action: "dashboard_role_default",
                    token: software_token,
                    role: parseInt(select.value, 10),
                    locked: lock.checked ? 1 : 0,
                    layout: pgDash.edit.layout(),
                    look: look
                }),
                type: "POST",
                dataType: "json"
            }).done(function (answer) {
                if (answer && answer.status === "success") {
                    done.textContent = answer.message;
                    done.hidden = false;
                } else if (typeof window.pgToast === "function") {
                    window.pgToast({variant: "danger", message: (answer && answer.message) || S.failed});
                }
            }).fail(function () {
                if (typeof window.pgToast === "function") {
                    window.pgToast({variant: "danger", message: S.failed});
                }
            }).always(function () {
                make.disabled = false;
            });
        });

        return section;
    }

    // ── Opening and closing ────────────────────────────────────────────────

    // The greeting, status and grid move into the work area's main column
    // and the panel stands beside them; closing puts them back.
    function open() {
        var top = root.querySelector(".pg-dash-top");
        var grid = document.getElementById("pg_dash_grid");

        look = JSON.parse(JSON.stringify(savedLook));
        looked = false;

        work = el("div", "pg-dash-work");
        var main = el("div", "pg-dash-work-main");
        top.before(work);
        work.appendChild(main);
        main.appendChild(top);
        main.appendChild(grid);

        aside = el("aside", "card pg-dash-side");
        aside.setAttribute("aria-label", S.panel);
        aside.appendChild(drawGallery());
        aside.appendChild(drawTop());
        aside.appendChild(drawLook());
        if (config.edit.share) {
            aside.appendChild(drawShare(config.edit.share));
        }
        work.appendChild(aside);
        syncTop();
    }

    function close() {
        if (!work) {
            return;
        }

        var main = work.querySelector(".pg-dash-work-main");
        while (main.firstChild) {
            work.before(main.firstChild);
        }
        work.remove();
        work = null;
        aside = null;
        list = null;
    }

    pgDash.panel = {
        opened: open,
        closed: close,
        cancel: function () {
            look = JSON.parse(JSON.stringify(savedLook));
            looked = false;
            applyLook();
        },
        saved: function () {
            if (looked) {
                savedLook = JSON.parse(JSON.stringify(look));
                looked = false;
            }
        },
        changed: function () {
            syncTop();
            drawList();
        },
        // The look to save, or null while the person has not picked one.
        look: function () {
            return looked ? look : null;
        },
        // Edit mode on the gallery, All and no search, the search focused.
        gallery: function () {
            category = "all";
            query = "";
            pgDash.edit.enter(null);
            if (!aside) {
                return;
            }
            var search = aside.querySelector(".pg-dash-gal-search");
            search.value = "";
            aside.querySelectorAll("[data-cat]").forEach(function (pill) {
                pill.setAttribute("aria-pressed", pill.getAttribute("data-cat") === "all" ? "true" : "false");
            });
            drawList();
            search.focus();
        }
    };

    // ── Start ──────────────────────────────────────────────────────────────

    function start() {
        config = pgDash.config;
        root = document.getElementById("content");
        var grid = document.getElementById("pg_dash_grid");

        if (!config || !config.edit || !config.edit.choices || !root || !grid || !pgDash.edit) {
            return;
        }

        S = config.strings;
        savedLook = JSON.parse(JSON.stringify(config.edit.look));

        if (config.edit.locked) {
            return;
        }

        var tile = button("pg-dash-addtile");
        var mark = el("span", "pg-dash-addtile-icon");
        var plus = el("i", "bi bi-plus-lg");
        plus.setAttribute("aria-hidden", "true");
        mark.appendChild(plus);
        tile.appendChild(mark);
        tile.appendChild(el("span", "pg-dash-addtile-title", S.add_card));
        tile.appendChild(el("span", "pg-dash-addtile-note", fmt(S.add_from, Object.keys(config.cards).length)));
        tile.addEventListener("click", pgDash.panel.gallery);
        grid.appendChild(tile);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start);
    } else {
        start();
    }
})();
