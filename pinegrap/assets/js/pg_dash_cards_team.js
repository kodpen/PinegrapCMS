/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's Team cards (assets/js/pg_dash.js draws the frames):
 * Team pulse, My tasks, Waiting on me, My week and Calendars. The data is
 * what includes/dashboard/v2/cards/<id>.php returns; every text in it comes
 * from the server.
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

    function more(body, data) {
        if (!data.url) {
            return;
        }
        var link = pgDash.el("a", "pg-dash-card-more", data.url_text);
        link.href = data.url;
        body.appendChild(link);
    }

    // A person's round: their photo, or their letters on their tone. The
    // presence ring is drawn by a class; its word is in the label.
    function avatar(person, presenceLabel) {
        var el = pgDash.el;
        var box = el("span", "avatar avatar-sm pg-dash-avatar " + (person.photo ? "" : (person.theme || "")));
        if (person.photo) {
            var img = el("img", "avatar-img");
            img.src = person.photo;
            img.alt = "";
            img.loading = "lazy";
            box.appendChild(img);
        } else {
            box.appendChild(document.createTextNode(person.initials || "?"));
        }
        if (presenceLabel) {
            box.setAttribute("data-presence", person.presence);
            box.setAttribute("role", "img");
            box.setAttribute("aria-label", person.name + ", " + presenceLabel);
            box.title = person.name + " · " + presenceLabel;
        } else {
            box.setAttribute("aria-hidden", "true");
        }
        return box;
    }

    // A row of a list: a lead (avatar, date or box), the text and its note,
    // and an optional action at the end.
    function item(lead, text, note, tone, url) {
        var el = pgDash.el;
        var row = el("li", "pg-dash-item");
        if (lead) {
            row.appendChild(lead);
        }
        var main = el("div", "pg-dash-item-main");
        if (url) {
            var link = el("a", "pg-dash-item-text", text);
            link.href = url;
            main.appendChild(link);
        } else {
            main.appendChild(el("span", "pg-dash-item-text", text));
        }
        var line = el("span", "pg-dash-item-note", note);
        if (tone) {
            line.setAttribute("data-tone", tone);
        }
        main.appendChild(line);
        row.appendChild(main);
        return row;
    }

    function dateBox(date) {
        var el = pgDash.el;
        var box = el("span", "pg-dash-date");
        box.setAttribute("aria-hidden", "true");
        box.appendChild(el("span", "pg-dash-date-day", date.day));
        box.appendChild(el("span", "pg-dash-date-month", date.month));
        return box;
    }

    function list(rows) {
        var box = pgDash.el("ul", "pg-dash-items");
        rows.forEach(function (row) {
            box.appendChild(row);
        });
        return box;
    }

    function datedItems(items) {
        return list((items || []).map(function (entry) {
            var row = item(dateBox(entry.date), entry.text, entry.note, "", entry.url);
            // The date box is hidden from screen readers; the date goes in
            // front of the text for them.
            var hidden = pgDash.el("span", "visually-hidden", entry.date.day + " " + entry.date.month + ": ");
            row.querySelector(".pg-dash-item-main").insertBefore(hidden, row.querySelector(".pg-dash-item-text"));
            return row;
        }));
    }

    // ── Team pulse ─────────────────────────────────────────────────────────

    pgDash.renderers.team = function (body, data) {
        var el = pgDash.el;

        var people = el("div", "pg-dash-people");
        (data.people || []).forEach(function (person) {
            people.appendChild(avatar(person, (data.presence || {})[person.presence] || ""));
        });
        if (data.more) {
            people.appendChild(el("span", "pg-dash-people-more", "+" + data.more));
        }
        body.appendChild(people);

        var summary = el("p", "pg-dash-summary");
        (data.summary || []).forEach(function (part, i) {
            if (i) {
                summary.appendChild(document.createTextNode(" · "));
            }
            var span = el("span", "", part.text);
            if (part.tone) {
                span.setAttribute("data-tone", part.tone);
            }
            summary.appendChild(span);
        });
        body.appendChild(summary);

        body.appendChild(el("h3", "pg-dash-rows-title", data.rows_title));
        if ((data.rows || []).length) {
            body.appendChild(list(data.rows.map(function (row) {
                return item(avatar(row.person, ""), row.text, row.note, "", row.url);
            })));
        } else {
            body.appendChild(el("p", "pg-dash-item-none", data.none));
        }
        more(body, data);
    };

    // ── My tasks ───────────────────────────────────────────────────────────

    // Tick a task done through the Workspace's own action, or back to the
    // status it had. The box is put back when the answer is not a success.
    function tick(box, row, entry, data) {
        var done = box.checked;
        box.disabled = true;
        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({
                action: "ws_task_status",
                token: (typeof software_token !== "undefined") ? software_token : "",
                task_id: entry.id,
                status: done ? "done" : entry.status
            }),
            type: "POST",
            dataType: "json"
        }).done(function (answer) {
            if (!answer || answer.status !== "success") {
                box.checked = !done;
                pgDash.taskFailed(row, data.labels.failed, answer && answer.message);
                return;
            }
            row.classList.toggle("is-done", done);
            var note = row.querySelector(".pg-dash-item-note");
            note.textContent = done ? data.labels.done : entry.note;
            note.setAttribute("data-tone", done ? "good" : (entry.tone || ""));
        }).fail(function () {
            box.checked = !done;
            pgDash.taskFailed(row, data.labels.failed);
        }).always(function () {
            box.disabled = false;
        });
    }

    pgDash.taskFailed = function (row, text, detail) {
        var note = row.querySelector(".pg-dash-item-note");
        note.textContent = detail || text;
        note.setAttribute("data-tone", "bad");
        note.setAttribute("role", "status");
    };

    pgDash.renderers.mytasks = function (body, data) {
        var el = pgDash.el;

        body.appendChild(el("p", "pg-dash-summary", data.summary));

        body.appendChild(list((data.rows || []).map(function (entry) {
            var box = el("input", "check pg-dash-tick");
            box.type = "checkbox";
            box.setAttribute("aria-label", data.labels.tick.replace("{var:1}", entry.text));
            var row = item(box, entry.text, entry.note, entry.tone, entry.url);
            box.addEventListener("change", function () {
                tick(box, row, entry, data);
            });
            return row;
        })));
        more(body, data);
    };

    // ── Waiting on me ──────────────────────────────────────────────────────

    pgDash.renderers.waiting = function (body, data) {
        var el = pgDash.el;

        body.appendChild(list((data.rows || []).map(function (entry) {
            var row = item(avatar(entry.person, ""), entry.text, entry.note, entry.tone, "");
            var open = el("a", "btn btn-outline theme-secondary btn-sm pg-dash-item-action", data.open);
            open.href = entry.url;
            open.setAttribute("aria-label", data.open + ": " + entry.text);
            row.appendChild(open);
            return row;
        })));
        more(body, data);
    };

    // ── My week ────────────────────────────────────────────────────────────

    function dayBox(day, tag) {
        var el = pgDash.el;
        var box = el(tag || "li", "pg-dash-day" + (day.today ? " is-today" : "") + (day.items ? " has-items" : ""));
        if (day.today) {
            box.setAttribute("aria-current", "date");
        }
        return box;
    }

    pgDash.renderers.week = function (body, data) {
        var el = pgDash.el;

        var days = el("ol", "pg-dash-week");
        days.setAttribute("aria-label", data.days_label);
        (data.days || []).forEach(function (day) {
            var box = dayBox(day);
            box.setAttribute("aria-label", day.label);
            box.appendChild(el("span", "pg-dash-day-name", day.name));
            box.appendChild(el("span", "pg-dash-day-number", day.day));
            var dots = el("span", "pg-dash-day-dots");
            for (var i = 0; i < Math.min(day.items, 3); i++) {
                dots.appendChild(el("span", "pg-dash-dot"));
            }
            box.appendChild(dots);
            days.appendChild(box);
        });
        body.appendChild(days);

        if ((data.items || []).length) {
            body.appendChild(datedItems(data.items));
        } else {
            body.appendChild(el("p", "pg-dash-item-none", data.none));
        }
        more(body, data);
    };

    // ── Calendars ──────────────────────────────────────────────────────────

    pgDash.renderers.calendars = function (body, data) {
        var el = pgDash.el;

        var head = el("div", "pg-dash-cal-head");
        head.appendChild(el("span", "pg-dash-cal-range", data.range));
        head.appendChild(el("span", "pg-dash-cal-count", data.count));
        body.appendChild(head);

        var grid = el("div", "pg-dash-cal");
        grid.setAttribute("role", "group");
        grid.setAttribute("aria-label", data.range);
        (data.weekdays || []).forEach(function (name) {
            var cell = el("span", "pg-dash-cal-weekday", name);
            cell.setAttribute("aria-hidden", "true");
            grid.appendChild(cell);
        });
        (data.days || []).forEach(function (day) {
            var cell = dayBox(day, "span");
            cell.classList.add("pg-dash-cal-day");
            cell.textContent = day.day;
            cell.setAttribute("role", "img");
            cell.setAttribute("aria-label", day.label);
            grid.appendChild(cell);
        });
        body.appendChild(grid);

        if ((data.items || []).length) {
            body.appendChild(datedItems(data.items));
        } else {
            body.appendChild(el("p", "pg-dash-item-none", data.none));
        }
        more(body, data);
    };
})();
