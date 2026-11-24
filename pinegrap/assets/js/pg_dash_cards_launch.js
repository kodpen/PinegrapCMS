/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's set-up card (assets/js/pg_dash.js draws the frame):
 * the steps before going live, blockers first, "Do it" opening the screen
 * where each is done, "Not for this site" on a step's menu, "Change
 * purpose" and "Hide set-up" on the card's menu, and the purpose dialog
 * ("What is this site for?"); the foot "Ready to go live?" opens the
 * go-live check ("Must be fixed", "Worth a look", "Go live"). The data is what
 * includes/dashboard/v2/cards/launch.php returns; every text in it was
 * translated on the server. Choices are saved through the panel action
 * launch_journey_save, then the card is drawn anew from the server.
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

    // The purpose dialog opens by itself once per page, not on every redraw.
    var asked = false;
    var current = null;

    function save(request, done, failed) {
        request.action = "launch_journey_save";
        request.token = (typeof software_token !== "undefined") ? software_token : "";

        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify(request),
            type: "POST",
            dataType: "json"
        }).done(function (answer) {
            if (answer && answer.status === "success") {
                done();
            } else {
                failed((answer && answer.message) || "");
            }
        }).fail(function () {
            failed("");
        });
    }

    function redraw() {
        // A card taken off the dashboard (closed, or every step done) is not
        // asked for again.
        if (current && pgDash.reload && pgDash.frame && pgDash.frame(current.entry.id)) {
            pgDash.reload(current.entry);
        }
    }

    function failedNote(body, text) {
        var note = body.querySelector(".pg-dash-launch-note");
        if (note) {
            note.textContent = text;
            note.hidden = false;
        }
    }

    // A "more" menu: an icon button and a Bootstrap menu of buttons.
    function menu(label, items) {
        var el = pgDash.el;
        var wrap = el("span", "pg-menu-wrap");
        var button = el("button", "pg-icon-button pg-icon-button-sm");
        button.type = "button";
        button.setAttribute("data-bs-toggle", "menu");
        button.setAttribute("aria-expanded", "false");
        button.setAttribute("aria-label", label);
        var icon = el("i", "bi bi-three-dots");
        icon.setAttribute("aria-hidden", "true");
        button.appendChild(icon);
        wrap.appendChild(button);

        var list = el("ul", "menu pg-menu-end");
        items.forEach(function (item) {
            var li = el("li");
            var entry = el("button", "menu-item", item.text);
            entry.type = "button";
            entry.addEventListener("click", item.run);
            li.appendChild(entry);
            list.appendChild(li);
        });
        wrap.appendChild(list);

        return wrap;
    }

    // "What is this site for?": eight radio cards, two a row (one on a
    // phone), "Choose" the fill and "Later" quiet.
    function purposeDialog(data) {
        var el = pgDash.el;
        var labels = data.labels;
        var parts = pgUI.dialog.build({
            id: "pg_dash_launch_purpose",
            title: labels.ask,
            size: "lg",
            scrollable: true,
            closeLabel: labels.close
        });

        parts.body.appendChild(el("p", "fg-2 mb-4", labels.ask_line));

        var grid = el("div", "pg-start pg-dash-launch-purposes");
        grid.setAttribute("role", "radiogroup");
        grid.setAttribute("aria-labelledby", parts.title.id);

        data.purposes.forEach(function (choice) {
            var id = "pg_dash_launch_purpose_" + choice.key;
            var field = el("div", "form-field form-field-card");
            var input = el("input", "radio");
            input.type = "radio";
            input.name = "pg_dash_launch_purpose";
            input.value = choice.key;
            input.id = id;
            input.checked = (choice.key === data.purpose);
            input.setAttribute("aria-describedby", id + "_help");
            field.appendChild(input);

            var head = el("label", "pg-start-head");
            head.setAttribute("for", id);
            var tile = el("span", "pg-icon-tile pg-icon-tile-lg pg-tone-" + choice.tone);
            tile.setAttribute("aria-hidden", "true");
            tile.appendChild(el("i", "bi " + choice.icon));
            head.appendChild(tile);
            head.appendChild(el("span", "fw-bold", choice.name));
            field.appendChild(head);

            var help = el("div", "form-text mt-1", choice.line);
            help.id = id + "_help";
            field.appendChild(help);
            grid.appendChild(field);
        });

        parts.body.appendChild(grid);

        var note = el("p", "pg-dash-launch-note fg-emphasis-danger mt-3 mb-0");
        note.setAttribute("role", "status");
        note.hidden = true;
        parts.body.appendChild(note);

        var later = el("button", "pg-button pg-button-quiet", labels.later);
        later.type = "button";
        later.addEventListener("click", function () {
            pgUI.dialog.hide(parts.root);
        });

        var choose = el("button", "pg-button pg-button-fill", labels.choose);
        choose.type = "button";
        choose.addEventListener("click", function () {
            var picked = grid.querySelector("input:checked");
            if (!picked) {
                grid.querySelector("input").focus();
                return;
            }
            choose.disabled = true;
            save({purpose: picked.value}, function () {
                // The card is drawn anew once the dialog has closed.
                pgUI.dialog.hide(parts.root).then(redraw);
            }, function (text) {
                choose.disabled = false;
                note.textContent = text || labels.failed;
                note.hidden = false;
            });
        });

        parts.footer.appendChild(later);
        parts.footer.appendChild(choose);

        pgUI.dialog.on(parts.root, "hidden", function () {
            parts.root.remove();
        }, {once: true});

        document.body.appendChild(parts.root);
        pgUI.dialog.show(parts.root);
    }

    // One group of the go-live check: a heading and its gathered lines,
    // each with "Fix", or "Nothing to fix".
    function golivegroup(title, rows, labels) {
        var el = pgDash.el;
        var group = el("section", "pg-dash-golive-group");
        group.appendChild(el("h3", "pg-list-row-title mb-2", title));

        if (!rows.length) {
            group.appendChild(el("p", "pg-text-meta mb-0", labels.nothing));
            return group;
        }

        var gather = el("div", "pg-gather");
        rows.forEach(function (step) {
            var line = el("div", "pg-gather-line");
            line.appendChild(el("b", "", step.name));
            line.appendChild(el("span", "", step.line));
            var end = el("span", "pg-gather-line-end");
            var fix = el("a", "pg-button pg-button-quiet pg-button-sm", labels.fix);
            fix.href = step.url;
            fix.setAttribute("aria-label", labels.fix + ": " + step.name);
            end.appendChild(fix);
            line.appendChild(end);
            gather.appendChild(line);
        });
        group.appendChild(gather);

        return group;
    }

    // The go-live check: "Must be fixed" and "Worth a look", then "Go live".
    // A second press shows what "Go live" changes itself and, with a blocker
    // left, names each and asks for "I know, go live anyway".
    function goliveDialog(data) {
        var el = pgDash.el;
        var labels = data.labels;
        var golive = data.golive;
        var parts = pgUI.dialog.build({
            id: "pg_dash_launch_golive",
            title: labels.check,
            size: "lg",
            scrollable: true,
            closeLabel: labels.close
        });

        var check = el("div", "pg-dash-golive");
        check.appendChild(golivegroup(labels.must, golive.must, labels));
        check.appendChild(golivegroup(labels.worth, golive.worth, labels));
        parts.body.appendChild(check);

        var confirm = el("div", "pg-dash-golive-confirm");
        confirm.hidden = true;
        var anyway = null;

        if (golive.changes.length) {
            confirm.appendChild(el("p", "fw-bold mb-1", labels.changes));
            var changes = el("ul", "mb-3");
            golive.changes.forEach(function (text) {
                changes.appendChild(el("li", "", text));
            });
            confirm.appendChild(changes);
        }

        if (golive.left.length) {
            confirm.appendChild(el("p", "fw-bold mb-1", labels.left));
            var left = el("ul", "mb-3");
            golive.left.forEach(function (step) {
                left.appendChild(el("li", "", step.name + ": " + step.line));
            });
            confirm.appendChild(left);

            var field = el("div", "d-flex align-items-center gap-2");
            anyway = el("input", "pg-checkbox");
            anyway.type = "checkbox";
            anyway.id = "pg_dash_launch_golive_anyway";
            field.appendChild(anyway);
            var label = el("label", "pg-text-2", labels.anyway);
            label.setAttribute("for", anyway.id);
            field.appendChild(label);
            confirm.appendChild(field);
        }
        parts.body.appendChild(confirm);

        var note = el("p", "pg-dash-launch-note fg-emphasis-danger mt-3 mb-0");
        note.setAttribute("role", "status");
        note.hidden = true;
        parts.body.appendChild(note);

        var cancel = el("button", "pg-button pg-button-quiet", labels.cancel);
        cancel.type = "button";
        cancel.addEventListener("click", function () {
            pgUI.dialog.hide(parts.root);
        });

        var go = el("button", "pg-button pg-button-fill", labels.go_live);
        go.type = "button";
        go.addEventListener("click", function () {
            // The first press shows the confirmation when there is anything
            // to name; nothing to name goes live at once.
            if (confirm.hidden && (golive.changes.length || golive.left.length)) {
                confirm.hidden = false;
                (anyway || go).focus();
                return;
            }
            if (anyway && !anyway.checked) {
                anyway.focus();
                return;
            }
            go.disabled = true;
            save({go_live: 1, anyway: anyway && anyway.checked ? 1 : 0}, function () {
                pgUI.dialog.hide(parts.root).then(redraw);
            }, function (text) {
                go.disabled = false;
                note.textContent = text || labels.failed;
                note.hidden = false;
            });
        });

        parts.footer.appendChild(cancel);
        parts.footer.appendChild(go);

        pgUI.dialog.on(parts.root, "hidden", function () {
            parts.root.remove();
        }, {once: true});

        document.body.appendChild(parts.root);
        pgUI.dialog.show(parts.root);
    }

    function row(step, data, body) {
        var el = pgDash.el;
        var labels = data.labels;
        var item = el("li", "pg-list-row pg-dash-launch-row" + (step.done ? " is-read" : ""));
        item.setAttribute("data-level", step.level);

        var tile = el("span", "pg-icon-tile pg-tone-" + step.tone);
        tile.setAttribute("aria-hidden", "true");
        tile.appendChild(el("i", "bi " + step.icon));
        item.appendChild(tile);

        var main = el("div", "pg-list-row-main");
        var head = el("div", "pg-list-row-head");
        var title = el("span", "pg-list-row-title", step.name);
        // A blocker says so in words, never by its colour alone.
        if (step.level === "blocker") {
            title.appendChild(document.createTextNode(" "));
            title.appendChild(el("span", "pg-pill pg-tone-rose", labels.blocker));
        }
        head.appendChild(title);
        main.appendChild(head);
        main.appendChild(el("div", "pg-list-row-text", step.line));
        item.appendChild(main);

        var side = el("div", "pg-list-row-side pg-dash-launch-side");

        if (step.done) {
            var check = el("i", "bi bi-check-circle-fill pg-dash-launch-done");
            check.setAttribute("role", "img");
            check.setAttribute("aria-label", labels.done);
            side.appendChild(check);
        } else {
            var link = el("a", "pg-button pg-button-quiet pg-button-sm", labels.do_it);
            link.href = step.url;
            link.setAttribute("aria-label", labels.do_it + ": " + step.name);
            side.appendChild(link);

            if (step.hide) {
                side.appendChild(menu(labels.more.replace("{var:1}", step.name), [{
                    text: labels.not_here,
                    run: function () {
                        save({hide: step.key}, redraw, function (text) {
                            failedNote(body, text || labels.failed);
                        });
                    }
                }]));
            }
        }

        item.appendChild(side);
        return item;
    }

    pgDash.renderers.launch = function (body, data, entry) {
        var el = pgDash.el;
        var labels = data.labels || {};
        current = {entry: entry, data: data};

        var top = el("div", "pg-dash-launch-top");
        top.appendChild(el("span", "pg-dash-launch-head", data.head));
        top.appendChild(el("span", "pg-pill pg-tone-" + data.status.tone, data.status.text));
        top.appendChild(el("span", "flex-grow-1"));
        top.appendChild(menu(labels.menu, [
            {text: labels.purpose, run: function () { purposeDialog(data); }},
            {text: labels.hide, run: function () {
                save({close: 1}, redraw, function (text) {
                    failedNote(body, text || labels.failed);
                });
            }}
        ]));
        body.appendChild(top);

        var note = el("p", "pg-dash-launch-note fg-emphasis-danger mb-2");
        note.setAttribute("role", "status");
        note.hidden = true;
        body.appendChild(note);

        var list = el("ul", "pg-dash-launch-rows");
        (data.rows || []).forEach(function (step) {
            list.appendChild(row(step, data, body));
        });
        body.appendChild(list);

        // The foot: "Ready to go live?" opens the go-live check; once the
        // site went live, the time it did.
        if (data.golive) {
            var foot = el("div", "pg-dash-launch-foot");
            if (data.live_at) {
                foot.appendChild(el("span", "pg-text-meta", labels.live.replace("{var:1}", data.live_at)));
            } else if (window.pgUI) {
                var ready = el("button", "pg-button pg-button-fill pg-button-sm", labels.ready);
                ready.type = "button";
                ready.addEventListener("click", function () { goliveDialog(data); });
                foot.appendChild(ready);
            }
            body.appendChild(foot);
        }

        if (data.ask && !asked && window.pgUI) {
            asked = true;
            purposeDialog(data);
        }
    };

    // The states are read again when the tab is shown again and when the
    // Site Settings dialog closes, where most steps are done.
    document.addEventListener("visibilitychange", function () {
        if (document.visibilityState === "visible") {
            redraw();
        }
    });
    document.addEventListener("close", function (event) {
        if (event.target && event.target.id === "pg_settings_modal") {
            redraw();
        }
    }, true);
})();
