/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's Assistant card (assets/js/pg_dash.js draws the frame).
 * The data is what includes/dashboard/v2/cards/ai.php returns; every text in
 * it was translated on the server.
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

    // The status chip in the card's header slot: its words say the state,
    // the tone only repeats it.
    function headChip(body, chip) {
        var box = body.closest ? body.closest(".pg-dash-card") : null;
        var slot = box ? box.querySelector(".pg-dash-card-chip") : null;

        if (!slot || !chip) {
            return;
        }

        slot.textContent = chip.text;
        slot.setAttribute("data-tone", chip.tone || "");
        slot.hidden = false;
    }

    function offline(body, data) {
        var el = pgDash.el;
        var row = el("div", "pg-dash-ai-off");
        var icon = el("span", "pg-dash-ai-off-icon");
        icon.setAttribute("aria-hidden", "true");
        icon.appendChild(el("i", "bi bi-plug"));
        row.appendChild(icon);

        var main = el("div", "pg-dash-ai-off-main");
        main.appendChild(el("h3", "pg-dash-ai-off-title", data.title));
        main.appendChild(el("p", "pg-dash-ai-off-text", data.text));

        if ((data.buttons || []).length) {
            var buttons = el("div", "pg-dash-ai-buttons");
            data.buttons.forEach(function (button) {
                var link = el("a", "btn btn-sm " + (button.main ? "btn-solid theme-primary" : "btn-outline theme-secondary"), button.label);
                link.href = button.url;
                buttons.appendChild(link);
            });
            main.appendChild(buttons);
        } else if (data.ask) {
            main.appendChild(el("p", "pg-dash-ai-off-text", data.ask));
        }

        main.appendChild(el("p", "pg-dash-card-note", data.note));
        row.appendChild(main);
        body.appendChild(row);
    }

    // The daily summary: its three lines, each opening the message in its
    // channel, and when it was written.
    function summary(body, data) {
        var el = pgDash.el;
        var part = data.summary;

        if (!part || !(part.lines || []).length) {
            return;
        }

        var head = el("h3", "pg-dash-rows-title", part.title);
        if (part.when) {
            head.appendChild(el("span", "pg-dash-ai-summary-when", " · " + part.when));
        }
        body.appendChild(head);

        var list = el("ul", "pg-dash-ai-summary");
        part.lines.forEach(function (line) {
            var item = el("li", "");
            var link = el("a", "pg-dash-ai-summary-line", line.text);
            link.href = line.url;
            item.appendChild(link);
            list.appendChild(item);
        });
        body.appendChild(list);
    }

    function proposals(body, data) {
        var el = pgDash.el;
        var part = data.proposals || {};
        body.appendChild(el("h3", "pg-dash-rows-title", part.title));

        if (!(part.chips || []).length) {
            body.appendChild(el("p", "pg-dash-card-empty", part.none));
            return;
        }

        var list = el("ul", "pg-dash-ai-chips");
        part.chips.forEach(function (chip) {
            var item = el("li", "");
            var link = el(chip.url ? "a" : "span", "pg-dash-ai-chip", chip.text);
            link.setAttribute("data-kind", chip.kind);
            if (chip.url) {
                link.href = chip.url;
            }
            item.appendChild(link);
            list.appendChild(item);
        });
        body.appendChild(list);

        if (part.more) {
            body.appendChild(el("p", "pg-dash-card-note", part.more));
        }
    }

    // Giving a job posts "@Claude <text>" through the Workspace's own send
    // action, then asks it to start a run; both check the person again.
    function send(request) {
        request.token = (typeof software_token !== "undefined") ? software_token : "";

        return $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify(request),
            type: "POST",
            dataType: "json"
        });
    }

    function job(body, data) {
        var el = pgDash.el;
        var part = data.job;

        if (!part) {
            body.appendChild(el("p", "pg-dash-card-note", data.no_job));
            return;
        }

        var form = el("form", "pg-dash-ai-job");
        var inputId = "pg_dash_ai_job";
        var label = el("label", "visually-hidden", part.label);
        label.htmlFor = inputId;
        form.appendChild(label);

        var input = el("input", "form-control pg-dash-ai-input");
        input.type = "text";
        input.id = inputId;
        input.maxLength = part.max;
        input.placeholder = part.placeholder;
        input.autocomplete = "off";
        form.appendChild(input);

        var button = el("button", "btn btn-solid theme-primary pg-dash-ai-send");
        button.type = "submit";
        button.setAttribute("aria-label", part.send);
        var arrow = el("i", "bi bi-arrow-right");
        arrow.setAttribute("aria-hidden", "true");
        button.appendChild(arrow);
        form.appendChild(button);
        body.appendChild(form);

        var status = el("p", "pg-dash-card-note pg-dash-ai-status", part.note);
        status.setAttribute("role", "status");
        body.appendChild(status);

        function say(text, tone, url) {
            status.textContent = text;
            status.setAttribute("data-tone", tone || "");
            if (url) {
                status.appendChild(document.createTextNode(" "));
                var open = el("a", "", part.open);
                open.href = url;
                status.appendChild(open);
            }
        }

        form.addEventListener("submit", function (event) {
            event.preventDefault();
            var text = input.value.trim();

            if (!text || button.disabled) {
                return;
            }

            button.disabled = true;
            send({action: "ws_send", channel_id: part.channel_id, body: part.tag + " " + text}).done(function (answer) {
                button.disabled = false;
                if (!answer || answer.status !== "success") {
                    say((answer && answer.message) || part.failed, "bad");
                    return;
                }
                input.value = "";
                say(part.sent, "good", (answer.data && answer.data.message_id) ? "workspace.php?channel=" + part.channel_id + "&message=" + answer.data.message_id : "");
                send({action: "ws_claude_kick"});
            }).fail(function () {
                button.disabled = false;
                say(part.failed, "bad");
            });
        });
    }

    pgDash.renderers.ai = function (body, data) {
        headChip(body, data.chip);

        if (!data.connected) {
            offline(body, data);
            return;
        }

        summary(body, data);
        proposals(body, data);
        job(body, data);
    };
})();
