/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's personal cards and the Performance card
 * (assets/js/pg_dash.js draws the frames): My goals, Pinned, My notes and
 * Performance. The data is what includes/dashboard/v2/cards/<id>.php
 * returns; every text comes from it. Goals, pins and notes are saved one
 * part at a time through api.php's dashboard_personal_save action.
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

    function fill(text, value) {
        return String(text || "").replace("{var:1}", value);
    }

    function button(className, text, label) {
        var node = pgDash.el("button", className, text);
        node.type = "button";
        if (label) {
            node.setAttribute("aria-label", label);
        }
        return node;
    }

    function icon(name) {
        var node = pgDash.el("i", "bi " + name);
        node.setAttribute("aria-hidden", "true");
        return node;
    }

    function link(body, url, text) {
        if (!url || !text) {
            return;
        }
        var more = pgDash.el("a", "pg-dash-card-more", text);
        more.href = url;
        body.appendChild(more);
    }

    // A status line read out when it changes.
    function status(className) {
        var node = pgDash.el("p", "pg-dash-personal-status " + (className || ""));
        node.setAttribute("role", "status");
        return node;
    }

    // Save one personal part. done(value) runs on success, failed(message)
    // on any other answer.
    function save(part, value, done, failed) {
        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({
                action: "dashboard_personal_save",
                token: (typeof software_token !== "undefined") ? software_token : "",
                part: part,
                value: value
            }),
            type: "POST",
            dataType: "json"
        }).done(function (answer) {
            if (answer && answer.status === "success") {
                done(answer.value);
                return;
            }
            failed(answer && answer.message);
        }).fail(function () {
            failed("");
        });
    }

    // One arc from the top, as long as its share; the figure in the middle.
    // The label says what the ring shows, and the figure is also text.
    function ring(share, label, value, tone) {
        var box = pgDash.el("span", "pg-dash-goal-ring");
        var picture = document.createElementNS(SVG, "svg");
        picture.setAttribute("viewBox", "0 0 36 36");
        picture.setAttribute("role", "img");
        picture.setAttribute("class", "pg-dash-goal-ring-svg");
        var title = document.createElementNS(SVG, "title");
        title.textContent = label;
        picture.appendChild(title);

        var radius = "15.915";
        var track = document.createElementNS(SVG, "circle");
        track.setAttribute("cx", "18");
        track.setAttribute("cy", "18");
        track.setAttribute("r", radius);
        track.setAttribute("class", "pg-dash-goal-ring-track");
        picture.appendChild(track);

        var part = Math.max(0, Math.min(1, share || 0)) * 100;
        if (part > 0) {
            var arc = document.createElementNS(SVG, "circle");
            arc.setAttribute("cx", "18");
            arc.setAttribute("cy", "18");
            arc.setAttribute("r", radius);
            arc.setAttribute("class", "pg-dash-goal-ring-arc");
            arc.setAttribute("stroke-dasharray", part.toFixed(2) + " " + (100 - part).toFixed(2));
            arc.setAttribute("stroke-dashoffset", "25");
            picture.appendChild(arc);
        }

        box.setAttribute("data-tone", tone || "");
        box.appendChild(picture);
        box.appendChild(pgDash.el("span", "pg-dash-goal-ring-value", value));
        return box;
    }

    // ── My goals ───────────────────────────────────────────────────────────

    function goalForm(body, data, entry, list) {
        var el = pgDash.el;
        var L = data.labels;
        var form = el("form", "pg-dash-goal-form");
        var note = status();

        function field(name, label, control) {
            var row = el("div", "pg-dash-goal-field");
            var id = "pg_dash_goal_" + name;
            var text = el("label", "form-label", label);
            text.setAttribute("for", id);
            control.id = id;
            row.appendChild(text);
            row.appendChild(control);
            form.appendChild(row);
            return control;
        }

        function select(choices) {
            var node = el("select", "form-control form-control-sm");
            Object.keys(choices).forEach(function (key) {
                var option = el("option", "", choices[key]);
                option.value = key;
                node.appendChild(option);
            });
            return node;
        }

        var name = field("name", L.name, el("input", "form-control form-control-sm"));
        name.type = "text";
        name.maxLength = 60;
        name.required = true;
        var metric = field("metric", L.metric, select(data.metrics));
        var target = field("target", L.target, el("input", "form-control form-control-sm"));
        target.type = "number";
        target.min = "1";
        target.step = "1";
        target.required = true;
        var span = field("span", L.span, select({month: L.month, week: L.week}));

        var actions = el("div", "pg-dash-goal-actions");
        var keep = el("button", "btn theme-primary btn-sm", L.save);
        keep.type = "submit";
        var stop = button("btn btn-outline theme-secondary btn-sm", L.cancel);
        actions.appendChild(keep);
        actions.appendChild(stop);
        form.appendChild(actions);
        form.appendChild(note);

        stop.addEventListener("click", function () {
            pgDash.reload(entry);
        });

        form.addEventListener("submit", function (event) {
            event.preventDefault();
            var goals = list.concat([{
                name: name.value,
                metric: metric.value,
                target: parseInt(target.value, 10) || 0,
                span: span.value
            }]);
            keep.disabled = true;
            save("goals", goals, function () {
                pgDash.reload(entry);
            }, function (message) {
                keep.disabled = false;
                note.textContent = message || L.failed;
            });
        });

        body.appendChild(form);
        name.focus();
    }

    pgDash.renderers.goals = function (body, data, entry) {
        var el = pgDash.el;
        var L = data.labels || {};
        var stored = data.stored || [];
        var note = status();

        if (!(data.goals || []).length) {
            body.appendChild(el("p", "pg-dash-item-none", L.none));
        }

        var list = el("ul", "pg-dash-goals");
        (data.goals || []).forEach(function (goal) {
            var item = el("li", "pg-dash-goal");
            item.appendChild(ring(goal.ring, goal.label, goal.pct, goal.tone));

            var main = el("div", "pg-dash-goal-main");
            main.appendChild(el("span", "pg-dash-goal-name", goal.name));
            main.appendChild(el("span", "pg-dash-goal-figure", goal.value + " " + goal.of));
            var state = el("span", "pg-dash-item-note", goal.note);
            state.setAttribute("data-tone", goal.tone);
            main.appendChild(state);
            item.appendChild(main);

            if (!data.locked) {
                var remove = button("btn btn-icon btn-sm pg-dash-goal-remove", "", fill(L.remove, goal.name));
                remove.appendChild(icon("bi-x-lg"));
                remove.addEventListener("click", function () {
                    remove.disabled = true;
                    save("goals", stored.filter(function (_goal, i) {
                        return i !== goal.index;
                    }), function () {
                        pgDash.reload(entry);
                    }, function (message) {
                        remove.disabled = false;
                        note.textContent = message || L.failed;
                    });
                });
                item.appendChild(remove);
            }
            list.appendChild(item);
        });
        body.appendChild(list);

        if (!data.locked && (stored.length < (data.most || 3)) && Object.keys(data.metrics || {}).length) {
            var add = button("btn btn-outline theme-secondary btn-sm pg-dash-personal-add", " " + L.add);
            add.insertBefore(icon("bi-plus-lg"), add.firstChild);
            add.addEventListener("click", function () {
                add.remove();
                goalForm(body, data, entry, stored);
            });
            body.appendChild(add);
        }
        body.appendChild(note);
    };

    // ── Pinned ─────────────────────────────────────────────────────────────

    // What the panel's search found, as pins: its screen shortcuts and the
    // records of a type a pin may open.
    function pinChoices(answer, types) {
        var out = [];
        ((answer && answer.actions) || []).forEach(function (action) {
            var url = String(action.url || "").split("/").pop();
            if (url) {
                out.push({pin: {t: "screen", u: url, l: action.label, i: action.icon}, text: action.label, icon: action.icon || "bi-window"});
            }
        });
        ((answer && answer.results) || []).forEach(function (result) {
            if (types.indexOf(result.type) !== -1) {
                out.push({pin: {t: result.type, id: result.id, l: result.name}, text: result.name, note: result.sub || "", icon: ""});
            }
        });
        return out;
    }

    function pinSearch(body, data, entry) {
        var el = pgDash.el;
        var L = data.labels;
        var box = el("div", "pg-dash-pin-search");
        var label = el("label", "visually-hidden", L.search);
        label.setAttribute("for", "pg_dash_pin_search");
        var input = el("input", "form-control form-control-sm");
        input.type = "search";
        input.id = "pg_dash_pin_search";
        input.placeholder = L.search;
        input.autocomplete = "off";
        var found = el("ul", "pg-dash-pin-found");
        var note = status();
        var stop = button("btn btn-outline theme-secondary btn-sm", L.cancel);
        var timer = null;
        var asked = 0;

        stop.addEventListener("click", function () {
            pgDash.reload(entry);
        });

        function show(choices) {
            found.textContent = "";
            if (!choices.length) {
                note.textContent = L.nothing;
                return;
            }
            note.textContent = "";
            choices.slice(0, 8).forEach(function (choice) {
                var item = el("li");
                var pick = button("pg-dash-pin-choice", "");
                if (choice.icon) {
                    pick.appendChild(icon(choice.icon));
                }
                pick.appendChild(el("span", "pg-dash-pin-choice-text", choice.text));
                if (choice.note) {
                    pick.appendChild(el("span", "pg-dash-pin-choice-note", choice.note));
                }
                pick.addEventListener("click", function () {
                    pick.disabled = true;
                    save("pins", (data.stored || []).concat([choice.pin]), function () {
                        pgDash.reload(entry);
                    }, function (message) {
                        pick.disabled = false;
                        note.textContent = message || L.failed;
                    });
                });
                item.appendChild(pick);
                found.appendChild(item);
            });
        }

        input.addEventListener("input", function () {
            window.clearTimeout(timer);
            var query = input.value.trim();
            if (query.length < 2) {
                found.textContent = "";
                note.textContent = "";
                return;
            }
            timer = window.setTimeout(function () {
                var mine = ++asked;
                $.ajax({
                    contentType: "application/json",
                    url: "api.php",
                    data: JSON.stringify({action: "backend_search", search: query, offset: 0}),
                    type: "POST",
                    dataType: "json"
                }).done(function (answer) {
                    if (mine === asked) {
                        show(pinChoices(answer, data.types || []));
                    }
                }).fail(function () {
                    if (mine === asked) {
                        note.textContent = L.failed;
                    }
                });
            }, 250);
        });

        box.appendChild(label);
        box.appendChild(input);
        box.appendChild(found);
        box.appendChild(note);
        box.appendChild(stop);
        body.appendChild(box);
        input.focus();
    }

    pgDash.renderers.pins = function (body, data, entry) {
        var el = pgDash.el;
        var L = data.labels || {};
        var stored = data.stored || [];
        var note = status();

        if (!(data.pins || []).length) {
            body.appendChild(el("p", "pg-dash-item-none", L.none));
        }

        var list = el("ul", "pg-dash-pins");
        (data.pins || []).forEach(function (pin, index) {
            var item = el("li", "pg-dash-pin");
            var open = el("a", "pg-dash-pin-link");
            open.href = pin.url;
            open.appendChild(icon(pin.icon));
            open.appendChild(el("span", "pg-dash-pin-text", pin.text));
            open.title = pin.text;
            item.appendChild(open);

            if (!data.locked) {
                var remove = button("btn btn-icon btn-sm pg-dash-pin-remove", "", fill(L.remove, pin.text));
                remove.appendChild(icon("bi-x-lg"));
                remove.addEventListener("click", function () {
                    remove.disabled = true;
                    save("pins", stored.filter(function (_pin, i) {
                        return i !== index;
                    }), function () {
                        pgDash.reload(entry);
                    }, function (message) {
                        remove.disabled = false;
                        note.textContent = message || L.failed;
                    });
                });
                item.appendChild(remove);
            }
            list.appendChild(item);
        });
        body.appendChild(list);

        if (!data.locked) {
            if (stored.length < (data.most || 8)) {
                var add = button("btn btn-outline theme-secondary btn-sm pg-dash-personal-add", " " + L.add);
                add.insertBefore(icon("bi-plus-lg"), add.firstChild);
                add.addEventListener("click", function () {
                    add.remove();
                    pinSearch(body, data, entry);
                });
                body.appendChild(add);
            } else {
                body.appendChild(el("p", "pg-dash-personal-hint", L.full));
            }
        }
        body.appendChild(note);
    };

    // ── My notes ───────────────────────────────────────────────────────────

    pgDash.renderers.notes = function (body, data, entry) {
        var el = pgDash.el;
        var L = data.labels || {};
        var most = data.most || 2000;
        var box = el("textarea", "form-control pg-dash-notes");
        var id = "pg_dash_notes_" + entry.id;
        var hint = el("p", "pg-dash-personal-hint", L.hint);
        var note = status("pg-dash-notes-status");
        var timer = null;
        var sent = data.text || "";

        box.id = id;
        box.value = sent;
        box.maxLength = most;
        box.rows = 6;
        box.setAttribute("aria-label", L.label);
        hint.id = id + "_hint";
        box.setAttribute("aria-describedby", hint.id);
        box.readOnly = !!data.locked;

        function left() {
            return fill(L.left, String(Math.max(0, most - box.value.length)));
        }

        // Saved after a pause in typing, and when the box is left.
        function send() {
            window.clearTimeout(timer);
            var text = box.value;
            if (text === sent) {
                return;
            }
            note.textContent = L.saving;
            save("notes", text, function (value) {
                sent = (typeof value === "string") ? value : text;
                note.textContent = L.saved + " · " + left();
            }, function (message) {
                note.textContent = message || L.failed;
            });
        }

        if (!data.locked) {
            box.addEventListener("input", function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(send, 800);
            });
            box.addEventListener("blur", send);
        }

        body.appendChild(box);
        body.appendChild(hint);
        body.appendChild(note);
    };

    // ── Performance ────────────────────────────────────────────────────────

    pgDash.renderers.perf = function (body, data) {
        var el = pgDash.el;
        var top = el("div", "pg-dash-perf");
        top.appendChild(ring(data.ring, data.ring_label, data.ring_text, data.grade_tone));

        var main = el("div", "pg-dash-perf-main");
        var grade = el("span", "pg-dash-perf-grade", data.grade);
        grade.setAttribute("data-tone", data.grade_tone || "");
        main.appendChild(grade);
        main.appendChild(el("span", "pg-dash-perf-note", data.grade_note));
        main.appendChild(el("span", "pg-dash-perf-note", data.ring_label));
        top.appendChild(main);
        body.appendChild(top);

        body.appendChild(pgDash.tiles(data.tiles));
        body.appendChild(pgDash.rows(data.rows_title, data.rows));
        link(body, data.url, data.link);
    };
})();
