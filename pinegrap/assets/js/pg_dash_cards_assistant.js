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
                var link = el("a", button.main ? "pg-dash-btn pg-dash-btn-main" : "pg-dash-btn", button.label);
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

    // The daily summary as Claude's message: the avatar beside a bubble with
    // the kicker (when it was written) and three lines, each opening the
    // message in its channel.
    function summary(body, data) {
        var el = pgDash.el;
        var part = data.summary;

        if (!part || !(part.lines || []).length) {
            return;
        }

        var chat = el("div", "pg-dash-ai-chat");
        var avatar = el("span", "pg-dash-ai-avatar");
        avatar.setAttribute("aria-hidden", "true");
        avatar.appendChild(el("i", "bi bi-stars"));
        chat.appendChild(avatar);

        var bubble = el("div", "pg-dash-ai-bubble");
        var head = el("h3", "pg-dash-rows-title", part.title);
        if (part.when) {
            head.appendChild(el("span", "pg-dash-ai-summary-when", " · " + part.when));
        }
        bubble.appendChild(head);

        var list = el("ul", "pg-dash-ai-summary");
        part.lines.forEach(function (line) {
            var item = el("li", "");
            var link = el("a", "pg-dash-ai-summary-line");
            link.href = line.url;
            link.appendChild(el("span", "pg-dash-ai-summary-text", line.text));
            if (part.open) {
                link.appendChild(el("span", "pg-dash-ai-summary-open", part.open));
            }
            item.appendChild(link);
            list.appendChild(item);
        });
        bubble.appendChild(list);
        chat.appendChild(bubble);
        body.appendChild(chat);
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

    // Giving a job, or a chat line, posts through the Workspace's own send
    // action; asking an assistant to start is a call of its own. Every call
    // checks the person again.
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

    function messageUrl(channelId, messageId) {
        return "workspace.php?channel=" + channelId + "&message=" + messageId;
    }

    // A labelled field and the gradient send button, with a status line read
    // out when it changes; the job box and the chat box share it.
    function box(part, inputId, className) {
        var el = pgDash.el;
        var form = el("form", "pg-dash-ai-job" + (className ? " " + className : ""));
        var label = el("label", "visually-hidden", part.label);
        label.htmlFor = inputId;

        var input = el("input", "form-control pg-dash-ai-input");
        input.type = "text";
        input.id = inputId;
        input.maxLength = part.max;
        input.placeholder = part.placeholder;
        input.autocomplete = "off";

        var button = el("button", "btn btn-solid theme-primary pg-dash-ai-send");
        button.type = "submit";
        button.setAttribute("aria-label", part.send);
        var arrow = el("i", "bi bi-arrow-right");
        arrow.setAttribute("aria-hidden", "true");
        button.appendChild(arrow);

        form.appendChild(label);
        form.appendChild(input);
        form.appendChild(button);

        var status = el("p", "pg-dash-card-note pg-dash-ai-status", part.note);
        status.setAttribute("role", "status");
        status.setAttribute("aria-live", "polite");

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

        // Enter submits the form; an empty field or a send under way does
        // nothing.
        function onSend(handler) {
            form.addEventListener("submit", function (event) {
                event.preventDefault();
                var text = input.value.trim();

                if (!text || button.disabled) {
                    return;
                }

                button.disabled = true;
                handler(text, function () {
                    button.disabled = false;
                });
            });
        }

        return {form: form, input: input, status: status, say: say, onSend: onSend};
    }

    function job(body, data) {
        var part = data.job;

        if (!part) {
            body.appendChild(pgDash.el("p", "pg-dash-card-note", data.no_job));
            return;
        }

        var ui = box(part, "pg_dash_ai_job");
        body.appendChild(ui.form);
        body.appendChild(ui.status);

        ui.onSend(function (text, done) {
            send({action: "ws_send", channel_id: part.channel_id, body: part.tag + " " + text}).done(function (answer) {
                done();
                if (!answer || answer.status !== "success") {
                    ui.say((answer && answer.message) || part.failed, "bad");
                    return;
                }
                ui.input.value = "";
                ui.say(part.sent, "good", (answer.data && answer.data.message_id) ? messageUrl(part.channel_id, answer.data.message_id) : "");
                send({action: "ws_claude_kick"});
            }).fail(function () {
                done();
                ui.say(part.failed, "bad");
            });
        });
    }

    // A message's text without its markup: the Workspace drew it, and a
    // parsed document runs nothing and loads nothing.
    function plain(html) {
        if (!html || !window.DOMParser) {
            return "";
        }

        return (new DOMParser().parseFromString(html, "text/html").body.textContent || "").replace(/\s+/g, " ").trim();
    }

    // The chat polls the channel for answers this long after the last line
    // sent, this often.
    var CHAT_WAIT = 10 * 60 * 1000;
    var CHAT_EVERY = 8000;

    // The chat box: a reply chain in the chosen assistant's channel. The
    // first line is a new message, the next ones answer it; every message
    // answering one of the chain's joins it and shows here.
    function chat(body, data) {
        var el = pgDash.el;
        var part = data.chat;

        if (!part) {
            return;
        }

        if (!(part.options || []).length) {
            body.appendChild(el("p", "pg-dash-card-note", part.none));
            return;
        }

        var section = el("section", "pg-dash-ai-talk");
        section.setAttribute("aria-labelledby", "pg_dash_ai_chat_title");
        var title = el("h3", "pg-dash-rows-title", part.title);
        title.id = "pg_dash_ai_chat_title";
        section.appendChild(title);

        var picker = null;

        if (part.options.length > 1) {
            var row = el("div", "pg-dash-ai-pick");
            var pickLabel = el("label", "form-label pg-dash-ai-pick-label", part.pick);
            pickLabel.htmlFor = "pg_dash_ai_chat_to";
            picker = el("select", "form-control form-control-sm pg-dash-ai-pick-select");
            picker.id = "pg_dash_ai_chat_to";
            part.options.forEach(function (option, index) {
                var item = el("option", "", option.name + " · #" + option.channel);
                item.value = String(index);
                picker.appendChild(item);
            });
            row.appendChild(pickLabel);
            row.appendChild(picker);
            section.appendChild(row);
        }

        var log = el("ol", "pg-dash-ai-log");
        log.setAttribute("role", "log");
        log.setAttribute("aria-live", "polite");
        log.hidden = true;
        section.appendChild(log);

        var first = part.options[0];
        var ui = box({label: part.label, max: first.max, placeholder: part.placeholder, send: part.send, note: first.note, open: part.open}, "pg_dash_ai_chat", "pg-dash-ai-chat-form");
        section.appendChild(ui.form);
        section.appendChild(ui.status);
        body.appendChild(section);

        // The conversation: its channel, the ids in it, the newest id read.
        var talk = null;
        var timer = 0;

        function chosen() {
            return part.options[picker ? (parseInt(picker.value, 10) || 0) : 0];
        }

        if (picker) {
            picker.addEventListener("change", function () {
                var option = chosen();
                ui.input.maxLength = option.max;
                ui.say(option.note, "");
            });
        }

        function line(who, text, mine, url) {
            var item = el("li", "pg-dash-ai-line");
            item.setAttribute("data-mine", mine ? "1" : "0");
            item.appendChild(el("span", "pg-dash-ai-line-who", who));
            item.appendChild(el("span", "pg-dash-ai-line-text", text));
            if (url) {
                var open = el("a", "pg-dash-ai-line-open", part.open);
                open.href = url;
                item.appendChild(open);
            }
            log.appendChild(item);
            log.hidden = false;
        }

        function poll() {
            timer = 0;

            if (!talk || (Date.now() > talk.until)) {
                if (talk && talk.waiting) {
                    talk.waiting = false;
                    ui.say(part.late, "");
                }
                return;
            }

            send({action: "ws_sync", channel_id: talk.channel_id, since_id: talk.last, panel: 1}).done(function (answer) {
                var rows = (answer && answer.status === "success" && answer.data) ? (answer.data.messages || []) : [];

                rows.forEach(function (message) {
                    talk.last = Math.max(talk.last, message.id);

                    if (message.deleted || message.mine || (talk.ids.indexOf(message.parent_id) < 0) || (talk.ids.indexOf(message.id) >= 0)) {
                        return;
                    }

                    talk.ids.push(message.id);
                    var text = plain(message.html);

                    if (text) {
                        talk.waiting = false;
                        line((message.sender && message.sender.name) || "", text, false, messageUrl(talk.channel_id, message.id));
                    }
                });

                // Pinegrap AI, a model assistant and an MCP agent go on a step
                // at a time, carried on by a screen that waits for them.
                if (talk.ai) {
                    send({action: "ws_ai_kick"});
                }
                if (talk.agent) {
                    send({action: "ws_mcp_agent_kick"});
                }
            }).always(function () {
                timer = window.setTimeout(poll, CHAT_EVERY);
            });
        }

        ui.onSend(function (text, done) {
            var option = chosen();

            // Another assistant's channel starts a conversation of its own.
            if (talk && (talk.channel_id !== option.channel_id)) {
                talk = null;
            }

            var request = {action: "ws_send", channel_id: option.channel_id, body: option.prefix + text};

            if (talk) {
                request.parent_id = talk.root;
            }

            send(request).done(function (answer) {
                done();
                var sent = (answer && answer.status === "success" && answer.data) ? answer.data : null;

                if (!sent || !sent.message_id) {
                    ui.say((answer && answer.message) || part.failed, "bad");
                    return;
                }

                if (!talk) {
                    talk = {channel_id: option.channel_id, root: sent.message_id, ids: [], last: sent.message_id - 1};
                }

                talk.ids.push(sent.message_id);
                talk.until = Date.now() + CHAT_WAIT;
                talk.waiting = true;
                talk.ai = talk.ai || !!sent.ai;
                talk.agent = talk.agent || !!sent.agent;
                ui.input.value = "";
                line(part.you, text, true, "");
                ui.say(option.sent + " " + part.wait, "good", messageUrl(option.channel_id, sent.message_id));

                if (sent.claude) {
                    send({action: "ws_claude_kick"});
                }
                if (!timer) {
                    poll();
                }
            }).fail(function () {
                done();
                ui.say(part.failed, "bad");
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
        chat(body, data);
    };
})();
