/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's edit mode (includes/dashboard/v2/page.php). "Edit
 * dashboard" in the switch row, or a card's "…" button, opens it: the edit
 * bar (presets, Back to default, Cancel, Save), a grip, a tool row and
 * settings on every card, the greeting's shortcuts and tiles and the System
 * Status card's Hide. Cancel puts back the state taken on entry; Save sends
 * the layout to api.php's dashboard_layout_save. Leaving the page with
 * changes not saved asks first. Every text comes from the page's config.
 *
 * Outside edit mode a card's period pill saves the layout at once.
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

    var config = null;
    var S = {};
    var root = null;
    var grid = null;
    var bar = null;

    // What is saved besides the cards: the preset, the top-section
    // switches, the shortcuts and the tiles.
    var state = null;
    var editing = false;
    // Taken on entry: the layout as saved and the grid's own entries.
    var snapshot = null;
    var entries = null;
    var greetingCopy = null;
    var greetingDirty = false;

    function fmt(text, one, two) {
        return String(text || "").replace("{var:1}", one).replace("{var:2}", two);
    }

    function copy(value) {
        return JSON.parse(JSON.stringify(value));
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

    function icon(name) {
        var node = el("i", "bi " + name);
        node.setAttribute("aria-hidden", "true");
        return node;
    }

    // ── The layout ─────────────────────────────────────────────────────────

    function current() {
        return {
            v: 1,
            preset: state.preset,
            cards: pgDash.layout().map(function (entry) {
                return {id: entry.id, w: entry.w, per: entry.per === null ? 0 : entry.per, cmp: entry.cmp, badge: entry.badge ? 1 : 0};
            }),
            top: state.top,
            actions: state.actions,
            tiles: state.tiles
        };
    }

    function dirty() {
        return editing && (greetingDirty || (JSON.stringify(current()) !== JSON.stringify(snapshot)));
    }

    function save(quiet, saveButton) {
        if (saveButton) {
            saveButton.disabled = true;
        }

        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({action: "dashboard_layout_save", token: software_token, layout: current()}),
            type: "POST",
            dataType: "json"
        }).done(function (answer) {
            if (!answer || answer.status !== "success") {
                failed(answer && answer.message);
            } else if (!quiet) {
                saved();
            }
        }).fail(function () {
            failed();
        }).always(function () {
            if (saveButton) {
                saveButton.disabled = false;
            }
        });
    }

    function failed(message) {
        if (typeof window.pgToast === "function") {
            window.pgToast({variant: "danger", message: message || S.failed});
        }
    }

    // The greeting's figures and counts are drawn by the server, so a
    // changed greeting is shown by loading the page again.
    function saved() {
        if (greetingDirty) {
            editing = false;
            window.location.reload();
            return;
        }

        leave();
        config.edit.actions = state.actions;
        config.edit.tiles = state.tiles;

        var row = document.getElementById("pg_dash_switch_row");
        var chip = el("span", "pg-dash-saved", S.saved);
        chip.setAttribute("role", "status");
        chip.prepend(icon("bi-check2"));
        (row.querySelector(".pg-dash-switch-end") || row).prepend(chip);
        window.setTimeout(function () {
            chip.remove();
        }, 4000);
    }

    // ── Entering and leaving ───────────────────────────────────────────────

    function enter(cardId) {
        if (config.edit.locked) {
            return;
        }

        if (!editing) {
            editing = true;
            snapshot = copy(current());
            entries = copy(pgDash.layout());
            greetingDirty = false;

            var greeting = root.querySelector(".pg-dash-greeting");
            greetingCopy = greeting ? greeting.cloneNode(true) : null;

            root.classList.add("pg-dash-editing");
            root.querySelector(".pg-dash-top").before(drawBar());
            editButton(true);
            decorateAll();
            drawGreeting();
            drawStatus();
        }

        if (cardId && pgDash.frame(cardId)) {
            openSettings(pgDash.frame(cardId), true);
        } else {
            bar.querySelector(".pg-dash-bar-title").focus();
        }
    }

    function leave() {
        editing = false;
        snapshot = null;
        root.classList.remove("pg-dash-editing");

        if (bar) {
            bar.remove();
            bar = null;
        }

        pgDash.layout().forEach(function (entry) {
            var item = pgDash.frame(entry.id);
            item.box.querySelectorAll(".pg-dash-grip, .pg-dash-tools, .pg-dash-settings").forEach(function (node) {
                node.remove();
            });
            item.body.inert = false;
        });

        var greeting = root.querySelector(".pg-dash-greeting");
        if (greeting && greetingCopy) {
            greeting.replaceWith(greetingCopy);
        }
        greetingCopy = null;

        drawStatus();
        editButton(false);
        document.getElementById("pg_dash_edit_btn").focus();
    }

    function cancel() {
        state.preset = snapshot.preset;
        state.top = copy(snapshot.top);
        state.actions = copy(snapshot.actions);
        state.tiles = copy(snapshot.tiles);
        pgDash.place(entries);
        leave();
    }

    function editButton(hidden) {
        var node = document.getElementById("pg_dash_edit_btn");
        if (node) {
            node.hidden = hidden;
        }
    }

    // ── The edit bar ───────────────────────────────────────────────────────

    function drawBar() {
        bar = el("section", "card pg-dash-bar");
        bar.setAttribute("aria-labelledby", "pg_dash_bar_title");

        var text = el("div", "pg-dash-bar-text");
        var title = el("h2", "pg-dash-bar-title", S.editing);
        title.id = "pg_dash_bar_title";
        title.tabIndex = -1;
        text.appendChild(title);
        var hint = el("p", "pg-dash-bar-hint", S.hint);
        hint.id = "pg_dash_edit_hint";
        text.appendChild(hint);
        bar.appendChild(text);

        var presets = el("div", "pg-dash-bar-presets");
        var presetId = "pg_dash_preset_label";
        presets.appendChild(el("span", "pg-dash-bar-label", S.preset)).id = presetId;
        var group = el("div", "btn-group btn-group-sm pg-dash-segment");
        group.setAttribute("role", "group");
        group.setAttribute("aria-labelledby", presetId);

        Object.keys(config.edit.presets).forEach(function (key) {
            var pick = button("btn btn-outline theme-secondary", config.edit.presets[key].title);
            pick.setAttribute("data-preset", key);
            pick.setAttribute("aria-pressed", key === state.preset ? "true" : "false");
            pick.addEventListener("click", function () {
                usePreset(key);
            });
            group.appendChild(pick);
        });
        presets.appendChild(group);
        bar.appendChild(presets);

        var end = el("div", "pg-dash-bar-end");
        var reset = button("btn btn-outline theme-secondary btn-sm", S["default"]);
        reset.addEventListener("click", backToDefault);
        var stop = button("btn btn-outline theme-secondary btn-sm", S.cancel);
        stop.addEventListener("click", cancel);
        var keep = button("btn theme-primary btn-sm", S.save);
        keep.addEventListener("click", function () {
            save(false, keep);
        });
        end.appendChild(reset);
        end.appendChild(stop);
        end.appendChild(keep);
        bar.appendChild(end);

        return bar;
    }

    function drawPresetPicks() {
        bar.querySelectorAll("[data-preset]").forEach(function (pick) {
            pick.setAttribute("aria-pressed", pick.getAttribute("data-preset") === state.preset ? "true" : "false");
        });
    }

    // A preset replaces the cards, the shortcuts and the tiles.
    function usePreset(key) {
        var preset = config.edit.presets[key];

        state.preset = key;
        state.actions = preset.actions.filter(function (action) {
            return !!config.edit.shortcuts[action];
        }).slice(0, 6);
        state.tiles = tilesFrom(preset.tiles);
        greetingDirty = true;

        pgDash.place(copy(preset.cards));
        decorateAll();
        drawGreeting();
        drawPresetPicks();
        announce(preset.title);
    }

    // The preset's cards at their defaults, every top-section part on.
    function backToDefault() {
        var top = {since: 1, actions: 1, tiles: 1, status: 1};

        if (JSON.stringify(top) !== JSON.stringify(state.top)) {
            greetingDirty = true;
        }

        state.top = top;
        pgDash.place(copy(config.edit.presets[state.preset].cards));
        decorateAll();
        drawGreeting();
        drawStatus();
        announce(S["default"]);
    }

    // Four tiles from the allowed figures, the wished ones first.
    function tilesFrom(wished) {
        var allowed = Object.keys(config.edit.figures);
        var tiles = wished.filter(function (key, i) {
            return (allowed.indexOf(key) !== -1) && (wished.indexOf(key) === i);
        });

        allowed.forEach(function (key) {
            if ((tiles.length < 4) && (tiles.indexOf(key) === -1)) {
                tiles.push(key);
            }
        });

        return tiles.slice(0, 4);
    }

    // ── Cards ──────────────────────────────────────────────────────────────

    function decorateAll() {
        pgDash.layout().forEach(function (entry) {
            var item = pgDash.frame(entry.id);
            if (!item.box.querySelector(".pg-dash-tools")) {
                decorate(item);
            }
        });
        refreshTools();
    }

    function decorate(item) {
        var meta = config.cards[item.entry.id];
        var head = item.box.querySelector(".pg-dash-card-head");

        var grip = button("pg-dash-grip", "", fmt(S.move, meta.title));
        grip.setAttribute("aria-describedby", "pg_dash_edit_hint");
        grip.appendChild(icon("bi-grip-vertical"));
        grip.addEventListener("keydown", function (event) {
            var step = {ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1}[event.key];
            if (step) {
                event.preventDefault();
                move(item, step, grip);
            }
        });
        grip.addEventListener("pointerdown", function (event) {
            drag(event, item, grip);
        });
        head.appendChild(grip);

        var tools = el("div", "pg-dash-tools");

        var widths = el("div", "btn-group btn-group-sm pg-dash-segment");
        widths.setAttribute("role", "group");
        widths.setAttribute("aria-label", S.width);
        [["S", S.narrow], ["M", S.medium], ["L", S.full]].forEach(function (pair) {
            var pick = button("btn btn-outline theme-secondary", pair[1]);
            pick.setAttribute("data-w", pair[0]);
            pick.addEventListener("click", function () {
                item.entry.w = pair[0];
                item.box.setAttribute("data-width", pair[0]);
                refreshTools();
            });
            widths.appendChild(pick);
        });
        tools.appendChild(widths);

        var gear = button("btn btn-outline theme-secondary btn-sm pg-dash-tool-settings", "", S.settings);
        gear.appendChild(icon("bi-sliders"));
        gear.setAttribute("aria-expanded", "false");
        gear.addEventListener("click", function () {
            openSettings(item, gear.getAttribute("aria-expanded") !== "true");
        });
        tools.appendChild(gear);

        var forward = button("btn btn-outline theme-secondary btn-sm pg-dash-tool-forward", "", S.forward);
        forward.appendChild(icon("bi-chevron-left"));
        forward.addEventListener("click", function () {
            move(item, -1, forward);
        });
        tools.appendChild(forward);

        var back = button("btn btn-outline theme-secondary btn-sm pg-dash-tool-back", "", S.back);
        back.appendChild(icon("bi-chevron-right"));
        back.addEventListener("click", function () {
            move(item, 1, back);
        });
        tools.appendChild(back);

        var remove = button("btn btn-outline theme-danger btn-sm pg-dash-tool-remove", "", fmt(S.remove_item, meta.title));
        remove.appendChild(icon("bi-x-lg"));
        remove.addEventListener("click", function () {
            removeCard(item);
        });
        tools.appendChild(remove);

        head.after(tools);
        item.body.inert = true;
    }

    // Width pressed states, and Bring forward / Move back off at the ends.
    function refreshTools() {
        var entries = pgDash.layout();
        entries.forEach(function (entry, i) {
            var box = pgDash.frame(entry.id).box;
            box.querySelectorAll("[data-w]").forEach(function (pick) {
                pick.setAttribute("aria-pressed", pick.getAttribute("data-w") === entry.w ? "true" : "false");
            });
            var forward = box.querySelector(".pg-dash-tool-forward");
            var back = box.querySelector(".pg-dash-tool-back");
            if (forward) {
                forward.disabled = (i === 0);
                back.disabled = (i === entries.length - 1);
            }
        });
    }

    function move(item, step, focus) {
        var boxes = Array.prototype.slice.call(grid.querySelectorAll(".pg-dash-cardbox"));
        var from = boxes.indexOf(item.box);
        var to = Math.max(0, Math.min(boxes.length - 1, from + step));

        if (to === from) {
            return;
        }

        grid.insertBefore(item.box, step < 0 ? boxes[to] : boxes[to].nextSibling);
        moved(item, focus);
    }

    function moved(item, focus) {
        var boxes = Array.prototype.slice.call(grid.querySelectorAll(".pg-dash-cardbox"));
        refreshTools();
        if (focus) {
            (focus.disabled ? item.box.querySelector(".pg-dash-grip") : focus).focus({preventScroll: false});
        }
        announce(fmt(S.moved, boxes.indexOf(item.box) + 1, boxes.length));
    }

    function removeCard(item) {
        var entries = pgDash.layout();
        var index = entries.indexOf(item.entry);
        var title = config.cards[item.entry.id].title;

        entries.splice(index, 1);
        pgDash.place(entries);
        refreshTools();

        var next = entries[Math.min(index, entries.length - 1)];
        (next ? pgDash.frame(next.id).box.querySelector(".pg-dash-grip") : bar.querySelector(".pg-dash-bar-title")).focus();
        announce(fmt(S.removed, title));
    }

    // Dragging by the grip: the mouse picks the card up once it moves a few
    // pixels, a finger after a short hold, so scrolling past does not.
    function drag(event, item, grip) {
        if ((event.pointerType === "mouse") && (event.button !== 0)) {
            return;
        }

        var touch = event.pointerType !== "mouse";
        var x = event.clientX;
        var y = event.clientY;
        var active = false;
        var target = null;
        var timer = touch ? window.setTimeout(begin, 200) : null;

        grip.setPointerCapture(event.pointerId);

        function begin() {
            active = true;
            item.box.classList.add("pg-dash-dragging");
        }

        function over(next) {
            if (target) {
                target.classList.remove("pg-dash-drop");
            }
            target = next;
            if (target) {
                target.classList.add("pg-dash-drop");
            }
        }

        function onMove(e) {
            var far = Math.abs(e.clientX - x) + Math.abs(e.clientY - y) > 5;

            if (!active) {
                if (far && touch) {
                    end(false);
                } else if (far) {
                    begin();
                }
                return;
            }

            e.preventDefault();
            var under = document.elementFromPoint(e.clientX, e.clientY);
            var box = under && under.closest(".pg-dash-cardbox");
            over((box && (box !== item.box) && grid.contains(box)) ? box : null);
        }

        function end(drop) {
            window.clearTimeout(timer);
            grip.removeEventListener("pointermove", onMove);
            grip.removeEventListener("pointerup", onUp);
            grip.removeEventListener("pointercancel", onCancel);
            item.box.classList.remove("pg-dash-dragging");

            if (drop && active && target) {
                var boxes = Array.prototype.slice.call(grid.querySelectorAll(".pg-dash-cardbox"));
                var later = boxes.indexOf(item.box) < boxes.indexOf(target);
                grid.insertBefore(item.box, later ? target.nextSibling : target);
                moved(item, grip);
            }

            over(null);
        }

        function onUp() {
            end(true);
        }

        function onCancel() {
            end(false);
        }

        grip.addEventListener("pointermove", onMove);
        grip.addEventListener("pointerup", onUp);
        grip.addEventListener("pointercancel", onCancel);
    }

    // ── Card settings ──────────────────────────────────────────────────────

    function openSettings(item, open) {
        var gear = item.box.querySelector(".pg-dash-tool-settings");
        var panel = item.box.querySelector(".pg-dash-settings");

        if (!open) {
            if (panel) {
                panel.remove();
            }
            gear.setAttribute("aria-expanded", "false");
            gear.focus();
            return;
        }

        if (panel) {
            panel.querySelector("button, input").focus();
            return;
        }

        panel = el("div", "pg-dash-settings");
        panel.id = "pg_dash_settings_" + item.entry.id;
        gear.setAttribute("aria-controls", panel.id);
        gear.setAttribute("aria-expanded", "true");

        if (config.cards[item.entry.id].period !== null) {
            panel.appendChild(segment(S.per_title, config.periods, function () {
                return item.entry.per;
            }, function (value) {
                item.entry.per = value;
                pgDash.reload(item.entry);
            }));
            panel.appendChild(segment(S.cmp_title, S.cmp, function () {
                return item.entry.cmp;
            }, function (value) {
                item.entry.cmp = value;
                pgDash.reload(item.entry);
            }));
        }

        var check = el("div", "form-field pg-dash-settings-badge");
        var box = el("input", "check");
        box.type = "checkbox";
        box.id = "pg_dash_badge_" + item.entry.id;
        box.checked = !!item.entry.badge;
        box.addEventListener("change", function () {
            item.entry.badge = box.checked ? 1 : 0;
            item.box.setAttribute("data-badge", String(item.entry.badge));
        });
        check.appendChild(box);
        var label = el("label", "", S.badge);
        label.setAttribute("for", box.id);
        check.appendChild(label);
        panel.appendChild(check);

        var done = button("btn btn-outline theme-secondary btn-sm pg-dash-settings-done", S.done);
        done.addEventListener("click", function () {
            openSettings(item, false);
        });
        panel.appendChild(done);

        panel.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                event.stopPropagation();
                openSettings(item, false);
            }
        });

        item.box.querySelector(".pg-dash-tools").after(panel);
        panel.querySelector("button, input").focus();
    }

    // A labelled row of pressed buttons; values are the list's indexes.
    function segment(title, names, read, write) {
        var box = el("div", "pg-dash-settings-row");
        var label = el("span", "pg-dash-settings-label", title);
        label.id = "pg_dash_seg_" + Math.random().toString(36).slice(2);
        box.appendChild(label);

        var group = el("div", "btn-group btn-group-sm pg-dash-segment");
        group.setAttribute("role", "group");
        group.setAttribute("aria-labelledby", label.id);

        Object.keys(names).forEach(function (key) {
            var value = parseInt(key, 10);
            var pick = button("btn btn-outline theme-secondary", names[key]);
            pick.setAttribute("aria-pressed", read() === value ? "true" : "false");
            pick.addEventListener("click", function () {
                write(value);
                group.querySelectorAll("button").forEach(function (other) {
                    other.setAttribute("aria-pressed", other === pick ? "true" : "false");
                });
            });
            group.appendChild(pick);
        });

        box.appendChild(group);
        return box;
    }

    // ── Greeting and System Status ─────────────────────────────────────────

    // The shortcuts with a remove button each and "Add shortcut", and the
    // tiles with a figure select each. A shortcut keeps the label the
    // server drew (with its count); one added here shows its plain label.
    function drawGreeting() {
        var greeting = root.querySelector(".pg-dash-greeting");

        if (!greeting || !greetingCopy) {
            return;
        }

        if (state.top.actions) {
            var nav = part(greeting, "nav", "pg-dash-actions", "pg-dash-tiles");
            nav.textContent = "";

            state.actions.forEach(function (key, i) {
                var drawn = greetingCopy.querySelector('[data-shortcut="' + key + '"] .pg-btn-label');
                var label = drawn ? drawn.textContent : config.edit.shortcuts[key].label;
                var chip = el("span", "pg-dash-action-edit" + (i === 0 ? " pg-dash-action-main" : ""));
                chip.appendChild(el("span", "pg-btn-label", label));
                var drop = button("pg-dash-action-remove", "", fmt(S.remove_item, label));
                drop.appendChild(icon("bi-x-lg"));
                drop.addEventListener("click", function () {
                    state.actions.splice(i, 1);
                    greetingDirty = true;
                    drawGreeting();
                    focusIn(".pg-dash-action-remove", i, ".pg-dash-action-add");
                });
                chip.appendChild(drop);
                nav.appendChild(chip);
            });

            var spare = config.edit.offer.concat(Object.keys(config.edit.shortcuts)).filter(function (key) {
                return config.edit.shortcuts[key] && (state.actions.indexOf(key) === -1);
            });

            if ((state.actions.length < 6) && spare.length) {
                var add = button("btn btn-outline theme-secondary pg-dash-action-add", "+ " + S.add_action);
                add.addEventListener("click", function () {
                    state.actions.push(spare[0]);
                    greetingDirty = true;
                    drawGreeting();
                    focusIn(".pg-dash-action-remove", state.actions.length - 1, ".pg-dash-action-add");
                });
                nav.appendChild(add);
            }
        }

        if (state.top.tiles) {
            var list = part(greeting, "ul", "pg-dash-tiles", null);
            list.textContent = "";

            state.tiles.forEach(function (key, i) {
                var drawn = greetingCopy.querySelector('[data-figure="' + key + '"]');
                var item = el("li", "pg-dash-tile pg-dash-tile-edit");
                var id = "pg_dash_tile_" + i;
                var label = el("label", "pg-dash-tile-label", fmt(S.tile, i + 1));
                label.setAttribute("for", id);
                item.appendChild(label);

                var select = el("select", "form-control form-control-sm");
                select.id = id;
                Object.keys(config.edit.figures).forEach(function (figure) {
                    var option = el("option", "", config.edit.figures[figure]);
                    option.value = figure;
                    option.selected = figure === key;
                    select.appendChild(option);
                });
                select.addEventListener("change", function () {
                    // A figure already on another tile swaps places with it.
                    var other = state.tiles.indexOf(select.value);
                    if (other !== -1) {
                        state.tiles[other] = key;
                    }
                    state.tiles[i] = select.value;
                    greetingDirty = true;
                    drawGreeting();
                    document.getElementById(id).focus();
                });
                item.appendChild(select);

                var value = drawn && drawn.querySelector(".pg-dash-tile-value");
                item.appendChild(el("span", "pg-dash-tile-value", value ? value.textContent : "—"));
                list.appendChild(item);
            });
        }
    }

    // The greeting's nav or list, made where the server drew none.
    function part(greeting, tag, className, before) {
        var node = greeting.querySelector("." + className);
        if (!node) {
            node = el(tag, className);
            var next = before ? greeting.querySelector("." + before) : null;
            greeting.insertBefore(node, next);
        }
        return node;
    }

    function focusIn(selector, index, fallback) {
        var greeting = root.querySelector(".pg-dash-greeting");
        var all = greeting.querySelectorAll(selector);
        var node = all[Math.min(index, all.length - 1)] || greeting.querySelector(fallback);
        if (node) {
            node.focus();
        }
    }

    // In edit mode a hidden System Status card shows dimmed with Show.
    function drawStatus() {
        var card = document.getElementById("pg_dash_status");

        if (!card) {
            return;
        }

        var head = card.querySelector(".pg-dash-status-head");
        var toggle = head.querySelector(".pg-dash-status-toggle");
        var off = !state.top.status;

        card.toggleAttribute("data-off", off);
        card.hidden = off && !editing;

        if (!editing) {
            if (toggle) {
                toggle.remove();
            }
            return;
        }

        if (!toggle) {
            toggle = button("btn btn-outline theme-secondary btn-sm pg-dash-status-toggle", "");
            toggle.addEventListener("click", function () {
                state.top.status = state.top.status ? 0 : 1;
                drawStatus();
                toggle.focus();
            });
            head.appendChild(toggle);
        }

        toggle.textContent = off ? S.show : S.hide;
        toggle.setAttribute("aria-pressed", off ? "true" : "false");
    }

    // ── Start ──────────────────────────────────────────────────────────────

    function start() {
        config = pgDash.config;
        root = document.getElementById("content");
        grid = document.getElementById("pg_dash_grid");

        if (!config || !config.edit || !root || !grid) {
            return;
        }

        S = config.strings;
        state = {
            preset: config.edit.preset,
            top: copy(config.edit.top),
            actions: copy(config.edit.actions),
            tiles: copy(config.edit.tiles)
        };

        pgDash.edit = {enter: enter};

        // Outside edit mode a changed period is kept at once.
        pgDash.periodChanged = function () {
            if (!editing && !config.edit.locked) {
                save(true);
            }
        };

        var open = document.getElementById("pg_dash_edit_btn");
        if (open) {
            open.addEventListener("click", function () {
                enter(null);
            });
        }

        window.addEventListener("beforeunload", function (event) {
            if (dirty()) {
                event.preventDefault();
                event.returnValue = S.leave;
                return S.leave;
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start);
    } else {
        start();
    }
})();
