/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - answering an e-mail conversation by e-mail on the channel
 * screen: the "Reply by e-mail" button under a conversation's card, the
 * drawer the answer is written in (with the ; list of saved replies of
 * assets/js/workspace_replies.js, put in as plain text), and the
 * conversation's status and holder, given to anybody of the channel
 * (includes/workspace/mail_status.php). On the E-mails screen the list,
 * its reading pane and the unread marks (screen()).
 *
 * assets/js/workspace.js draws the conversation and calls in here with its
 * own helpers. Whether a card may be answered is decided by the server
 * (includes/workspace/mail_reply.php) and checked again when the answer is
 * sent; the answer then arrives in the card's thread like any message.
 * Every text comes from the #ws-config block.
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // The drawer: whom the answer goes to, Reply to all, Cc and Bcc, the
    // answer, its files, Send. The server says first whom it goes to and
    // whom Reply to all adds (ws_mail_reply_form); the answer then leaves
    // through the mail queue like a new mail (ws_mail_reply, mail_send.php)
    // and reads Waiting to be sent or Not sent until it has left. A draft
    // fills the answer; nothing leaves before the member presses Send. all:
    // Reply to all is on from the start.
    function replyForm(message, view, h, draft, all) {
        h.api('ws_mail_reply_form', { conversation_id: message.mail_reply }).then(function (data) {
            answerForm(message.mail_reply, data, view, h, draft, all);
        }).catch(function (error) {
            h.fail(error);
        });
    }

    function answerForm(conversation, data, view, h, draft, all) {
        var t = h.t;
        var el = h.el;
        var node = h.offcanvas('ws-mail-reply', t('mail_reply'));
        var body = h.clear(node.querySelector('.drawer-body'));
        var footer = h.clear(node.querySelector('.drawer-footer'));
        var others = data.others || [];
        var files = [];
        var form = el('form', 'ws-mail-answer');
        var everyone = null;

        node.querySelector('.drawer-title').textContent = t('mail_reply');
        footer.classList.remove('d-none');
        form.noValidate = true;
        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });
        body.appendChild(form);
        form.appendChild(el('div', 'small fg-secondary mb-3', t('mail_reply_to', data.name ? data.name + ' <' + data.to + '>' : data.to)));

        function addresses(name, label) {
            var field = el('input', 'form-control form-control-sm');

            field.type = 'text';
            field.name = name;
            field.autocomplete = 'off';
            field.inputMode = 'email';
            field.spellcheck = false;
            field.setAttribute('autocapitalize', 'off');

            return { field: field, row: h.formRow(label, field, t('mail_new_addresses')) };
        }

        var cc = addresses('cc', t('mail_new_cc'));
        var bcc = addresses('bcc', t('mail_new_bcc'));

        suggest(h, cc.field);
        suggest(h, bcc.field);
        var copies = el('div', 'ws-mail-new-copies');
        var showCopies = h.button('btn btn-sm btn-text theme-secondary ws-mail-new-cc-toggle', t('mail_new_cc_bcc'), 'bi-plus-lg');

        function setCopies(open) {
            copies.hidden = !open;
            showCopies.hidden = open;
        }

        // Reply to all: the answered mail's other people as copies, taken
        // out of Cc again when it is switched off.
        if (others.length) {
            var allRow = el('div', 'form-field mb-3');

            everyone = el('input', 'check');
            everyone.type = 'checkbox';
            everyone.id = h.nextId('ws-mail-reply-all-');

            var allLabel = el('label', '', t('mail_reply_all'));

            allLabel.htmlFor = everyone.id;
            allRow.appendChild(everyone);
            allRow.appendChild(allLabel);
            form.appendChild(allRow);
            form.appendChild(el('div', 'small fg-secondary mb-3 text-break', t('mail_reply_all_to', others.join(', '))));
            everyone.addEventListener('change', function () {
                var typed = cc.field.value.split(/[\s,;]+/).filter(function (address) {
                    return address && (others.indexOf(address.toLowerCase()) === -1);
                });

                cc.field.value = (everyone.checked ? others.concat(typed) : typed).join(', ');
                setCopies(!!(cc.field.value || bcc.field.value));
            });
        }

        form.appendChild(showCopies);
        copies.appendChild(cc.row);
        copies.appendChild(bcc.row);
        form.appendChild(copies);
        showCopies.addEventListener('click', function () {
            setCopies(true);
            cc.field.focus();
        });

        var body = mailText(h, view, draft || '', t('mail_reply_text'), false);
        var text = body.input;
        text.id = h.nextId('ws-mail-reply-');

        var box = el('div', 'ws-mail-reply-box position-relative');
        box.appendChild(body.node);
        form.appendChild(h.formRow(t('mail_reply_text'), box, t('mail_reply_hint')));

        // The writing box offers them itself; the text area's own list.
        if (!body.rich) {
            savedReplies(text, box, view, h);
        }

        // Files, as New e-mail takes them: from Files or the computer.
        var list = el('ul', 'ws-mail-new-files list-unstyled mb-0');
        var library = [];
        var fileRow = h.formRow(t('mail_new_files'), filesField(h, data.file_field, library, files, function () {
            fileRows(h, list, [library, files]);
        }), t('mail_new_files_hint'));

        fileRow.appendChild(list);
        form.appendChild(fileRow);

        var send = h.button('btn btn-solid theme-primary rounded-pill px-5', t('mail_reply_send'), 'bi-send');

        function sendAnswer(anyway) {
            if (!body.value().trim()) {
                body.focus();
                return;
            }

            send.disabled = true;
            domainWarningClear(footer);

            var post = { conversation_id: conversation, cc: cc.field.value, bcc: bcc.field.value, text: body.value() };

            if (library.length) {
                post.file_ids = library.map(function (file) { return file.id; }).join(',');
            }

            if (body.rich) {
                post.format = 'markup';
            }

            if (anyway) {
                post.send_anyway = 1;
            }

            formPost(h, 'ws_mail_reply', post, files).then(function (result) {
                h.hideOffcanvas(node);
                h.toast(result.state === 'failed' ? t('mail_new_failed') : t('mail_new_sent'), result.state === 'failed' ? 'warning' : 'success');
                view.sync();
            }).catch(function (error) {
                send.disabled = false;

                if (error.code === 'unknown_domain') {
                    domainWarning(h, footer, error.message, function () { sendAnswer(true); }, cc.field.value ? cc.field : bcc.field);
                    return;
                }

                h.fail(error);
            });
        }

        send.addEventListener('click', function () {
            sendAnswer(false);
        });

        footer.appendChild(send);
        setCopies(false);

        if (all && everyone) {
            everyone.checked = true;
            everyone.dispatchEvent(new Event('change'));
        }

        h.showOffcanvas(node);
        body.focus();
    }

    // Recipient suggestions under an address field: the word after the last
    // comma (two characters at least) is asked of the server, which answers
    // the contacts the person may open and the addresses their mailboxes
    // know, a bounced one marked. Arrow keys move, Enter or a click takes
    // the address in place of the word, Escape closes.
    function suggest(h, field) {
        var t = h.t;
        var el = h.el;
        var host = el('div', 'ws-mail-suggest-host');
        var picker = el('div', 'ws-picker ws-mail-suggest');
        var list = el('div', 'ws-picker-list');
        var items = [];
        var index = -1;
        var timer = 0;
        var asked = 0;
        var id = 'ws-mail-suggest-' + field.name + '-' + Math.random().toString(36).slice(2, 8);

        field.parentNode.insertBefore(host, field);
        host.appendChild(field);
        host.appendChild(picker);
        picker.appendChild(list);
        picker.id = id;
        picker.hidden = true;
        picker.setAttribute('role', 'listbox');
        picker.setAttribute('aria-label', t('mail_suggest_label'));
        field.setAttribute('role', 'combobox');
        field.setAttribute('aria-autocomplete', 'list');
        field.setAttribute('aria-controls', id);
        field.setAttribute('aria-expanded', 'false');

        function close() {
            picker.hidden = true;
            items = [];
            index = -1;
            field.setAttribute('aria-expanded', 'false');
            field.removeAttribute('aria-activedescendant');
        }

        function mark(next) {
            index = next;
            Array.prototype.forEach.call(list.children, function (row, at) {
                row.classList.toggle('active', at === index);
                row.setAttribute('aria-selected', at === index ? 'true' : 'false');

                if (at === index) {
                    field.setAttribute('aria-activedescendant', row.id);
                }
            });
        }

        // The typed word gives way to the address, and the next one may follow.
        function take(item) {
            var value = field.value;
            var cut = Math.max(value.lastIndexOf(','), value.lastIndexOf(';'));
            var head = (cut === -1) ? '' : value.slice(0, cut + 1) + ' ';

            field.value = head + item.address + ', ';
            close();
            field.focus();
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function draw(found) {
            h.clear(list);
            items = found;

            if (!items.length) {
                close();
                return;
            }

            items.forEach(function (item, at) {
                var row = el('div', 'ws-picker-item');
                var text = el('span', 'ws-picker-text');

                row.id = id + '-' + at;
                row.setAttribute('role', 'option');
                text.appendChild(el('span', 'ws-picker-label', item.name || item.address));

                if (item.name) {
                    text.appendChild(el('span', 'ws-picker-detail', item.address));
                }

                row.appendChild(text);

                if (item.bounced) {
                    row.appendChild(el('span', 'pg-pill pg-tone-rose', t('mail_suggest_bounced')));
                }

                // Taken before the field loses its focus.
                row.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    take(item);
                });
                list.appendChild(row);
            });

            picker.hidden = false;
            field.setAttribute('aria-expanded', 'true');
            mark(0);
        }

        field.addEventListener('input', function (event) {
            if (event.isTrusted === false) {
                return;
            }

            window.clearTimeout(timer);

            var value = field.value;
            var word = value.split(/[,;]/).pop().trim();

            if (word.length < 2) {
                close();
                return;
            }

            timer = window.setTimeout(function () {
                var mine = ++asked;

                h.api('ws_mail_suggest', { q: value }).then(function (data) {
                    // Only the answer to the latest word is drawn.
                    if (mine === asked && field.value === value) {
                        draw(data.items || []);
                    }
                }).catch(close);
            }, 200);
        });

        field.addEventListener('keydown', function (event) {
            if (picker.hidden || !items.length) {
                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                mark((index + (event.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length);
            } else if (event.key === 'Enter' && index > -1) {
                event.preventDefault();
                take(items[index]);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                close();
            }
        });

        field.addEventListener('blur', close);
    }

    // The files a form sends, each group's in order, each with a button that
    // takes it out of its group.
    function fileRows(h, list, groups) {
        h.clear(list);
        groups.forEach(function (group) {
            group.forEach(function (file, index) {
                var row = h.el('li', 'd-flex align-items-center gap-2 small mt-2');
                var drop = h.button('btn btn-sm btn-text theme-secondary py-0', '', 'bi-x-lg', h.t('mail_new_remove') + ': ' + file.name);

                row.appendChild(h.icon('bi-paperclip'));
                row.appendChild(h.el('span', 'text-truncate', file.name));
                drop.addEventListener('click', function () {
                    group.splice(index, 1);
                    fileRows(h, list, groups);
                });
                row.appendChild(drop);
                list.appendChild(row);
            });
        });
    }

    // A saved reply as plain text for an e-mail: its tags by their names
    // (the server's labels), its lines kept. {customer} and {order} were
    // already filled for the channel by the server.
    function plain(item) {
        var labels = item.labels || {};

        return String(item.text || item.body || '').replace(/<[@#][a-z_]+:[0-9]{1,10}>/g, function (token) {
            return labels[token] || '';
        });
    }

    // The Files field of a mail's forms: the panel's picker field over Files
    // (the server's file_field, as WhatsApp's answer has it), each pick
    // joining the list under it and the field emptied for the next; "Upload
    // from your computer" opens the browser's chooser without drawing its
    // own control. picked: { id, name } of Files; uploads: File objects.
    function filesField(h, markup, picked, uploads, redraw) {
        var wrap = h.el('div', 'ws-mail-files');

        if (markup) {
            var field = h.el('div', 'ws-mail-files-pick');

            field.innerHTML = markup;
            wrap.appendChild(field);

            var value = field.querySelector('[data-pg-picker-value]');
            var root = field.querySelector('[data-pg-picker-field]');

            if (value) {
                value.addEventListener('change', function () {
                    var id = parseInt(value.value, 10) || 0;
                    var input = field.querySelector('.pg-pfield-input');

                    if (id <= 0) {
                        return;
                    }

                    if (!picked.some(function (file) { return file.id === id; })) {
                        picked.push({ id: id, name: (input && input.value) || ('#' + id) });
                        redraw();
                    }

                    // The field's own clear, else by hand: the next pick starts empty.
                    if (root && root.pgPickerField) {
                        root.pgPickerField.set(null);
                    } else {
                        value.value = '';

                        if (input) {
                            input.value = '';
                        }

                        if (root) {
                            root.classList.add('is-empty');
                        }
                    }
                });
            }
        }

        var chooser = h.el('input');
        var upload = h.button('pg-button pg-button-quiet pg-button-sm mt-2', h.t('mail_upload'), 'bi-upload');

        chooser.type = 'file';
        chooser.multiple = true;
        chooser.hidden = true;
        upload.addEventListener('click', function () {
            chooser.click();
        });
        chooser.addEventListener('change', function () {
            Array.prototype.forEach.call(chooser.files || [], function (file) {
                uploads.push(file);
            });
            chooser.value = '';
            redraw();
        });
        wrap.appendChild(chooser);
        wrap.appendChild(upload);

        return wrap;
    }

    // The text of a written mail: the Workspace's writing box (bold, italic,
    // links, lists and a quote, ";" for a saved reply, @ and # as in a
    // channel), posted as its markup with format "markup", which the server
    // turns into the mail's HTML and text parts (ws_mail_markup_parts()).
    // Where the box is missing (plain), a text area as before.
    function mailText(h, view, value, label, plain) {
        var wrap = h.el('div', 'ws-mail-rich');
        var field = (!plain && h.richField && window.PGWsEditor) ? h.richField(wrap, {}) : null;

        if (!field) {
            var area = h.el('textarea', 'form-control');

            area.name = 'text';
            area.rows = 10;
            area.maxLength = 50000;
            area.value = value || '';
            wrap.appendChild(area);

            return {
                node: wrap, input: area, rich: false,
                value: function () { return area.value; },
                set: function (text) { area.value = text || ''; },
                focus: function () { area.focus(); }
            };
        }

        // The channel's ";" saved replies, read for the channel the mail is in.
        field.savedReplies = true;
        field.channel = (view && view.channel) ? view.channel : null;
        field.input.setAttribute('aria-label', label);
        field.input.classList.add('form-control');
        wrap.appendChild(field.input);
        wrap.appendChild(window.PGWsEditor.toolbar(field.rich, ['bold', 'italic', 'link', 'bullets', 'numbers', 'quote'], { className: 'ws-mail-rich-tools' }));

        if (value) {
            field.set(value);
        }

        return {
            node: wrap, input: field.input, rich: true,
            value: function () { return field.value(); },
            set: function (text) { field.set(text || ''); },
            focus: function () { field.input.focus(); }
        };
    }

    // The ; list of the person's saved replies under the answer, as in the
    // channel's composer (window.PGWsReplies): ";" and the start of a
    // shortcut suggest them, a pick puts the reply in as plain text in
    // place of ";word". Arrows move, Enter or Tab picks, Escape leaves
    // that ";" alone.
    function savedReplies(text, box, view, h) {
        var R = window.PGWsReplies;

        if (!R || !h.cfg || !h.cfg.saved_replies) {
            return;
        }

        var channelId = view.channel ? view.channel.id : 0;
        var list = null;
        var items = [];
        var active = 0;
        var semi = null;
        // The ; Escape closed, by its place: it does not open again.
        var skip = -1;

        function close() {
            if (list && list.parentNode) {
                list.parentNode.removeChild(list);
            }

            list = null;
            items = [];
        }

        function mark() {
            Array.prototype.forEach.call(list.querySelectorAll('.ws-picker-item'), function (row, index) {
                row.classList.toggle('active', index === active);
            });
        }

        function pick(item) {
            var caret = text.selectionStart;
            var start = semi.start;
            var value = plain(item);

            close();
            text.focus();
            text.setSelectionRange(start, caret);

            // insertText keeps the browser's undo; setRangeText where it is gone.
            if (!document.execCommand || !document.execCommand('insertText', false, value)) {
                text.setRangeText(value, start, caret, 'end');
            }
        }

        function draw() {
            close();
            list = h.el('div', 'ws-picker');
            var inner = h.el('div', 'ws-picker-list');

            items.forEach(function (item, index) {
                var row = h.el('button', 'ws-picker-item');
                row.type = 'button';
                row.appendChild(h.icon('bi-chat-square-quote'));
                row.appendChild(h.el('span', 'ws-picker-label', item.title));
                row.appendChild(h.el('span', 'ws-picker-meta', ';' + item.shortcut));
                row.title = R.preview(item, 160);
                // Keep the caret in the answer until the pick.
                row.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                row.addEventListener('click', function () {
                    pick(item);
                });

                if (index === active) {
                    row.classList.add('active');
                }

                inner.appendChild(row);
            });

            list.appendChild(inner);
            box.appendChild(list);
        }

        function check() {
            var caret = text.selectionStart;

            semi = (caret === text.selectionEnd && document.activeElement === text) ? R.at(text.value.slice(0, caret)) : null;

            if (!semi || semi.start === skip) {
                close();
                return;
            }

            var replies = R.cached(channelId, h.api, check);
            var found = R.match(replies || [], semi.word, 8);

            if (!found.length) {
                close();
                return;
            }

            items = found;
            active = 0;
            draw();
        }

        text.addEventListener('input', function () {
            if (skip >= text.value.length || text.value.charAt(skip) !== ';') {
                skip = -1;
            }

            check();
        });
        text.addEventListener('click', check);
        text.addEventListener('blur', close);
        text.addEventListener('keydown', function (event) {
            if (!list || !items.length || event.isComposing) {
                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                active = (active + (event.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length;
                mark();
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                pick(items[active]);
            } else if (event.key === 'Escape') {
                // The drawer stays open; only the list closes.
                event.preventDefault();
                event.stopPropagation();
                skip = semi ? semi.start : -1;
                close();
            }
        });
    }

    // The GSM 03.38 default alphabet (one place each) and its extension
    // (two places each): the twin of pg_sms_gsm_chars() and
    // pg_sms_gsm_extension_chars() (includes/fn/sms_reply.php).
    var SMS_GSM = '@\u00a3$\u00a5\u00e8\u00e9\u00f9\u00ec\u00f2\u00c7\n\u00d8\u00f8\r\u00c5\u00e5\u0394_\u03a6\u0393\u039b\u03a9\u03a0\u03a8\u03a3\u0398\u039e'
        + '\u00c6\u00e6\u00df\u00c9 !"#\u00a4%&\'()*+,-./0123456789:;<=>?\u00a1ABCDEFGHIJKLMNOPQRSTUVWXYZ\u00c4\u00d6\u00d1\u00dc\u00a7\u00bf'
        + 'abcdefghijklmnopqrstuvwxyz\u00e4\u00f6\u00f1\u00fc\u00e0';
    var SMS_GSM_EXTENSION = '^{}\\[~]|\u20ac\f';

    // The length of an SMS text and the messages it makes, as
    // pg_sms_parts() counts it: GSM-7 (160, then 153 a part) while every
    // character is in the alphabet, else UCS-2 in UTF-16 units (70, then 67).
    function smsParts(text) {
        var plain = String(text).replace(/\r\n?/g, '\n').replace(/\t/g, ' ').replace(/[\x00-\x08\x0B-\x1F\x7F]/g, '').trim();
        var gsm = true;
        var units = 0;

        Array.from(plain).forEach(function (char) {
            if (!gsm) {
                return;
            }

            if (SMS_GSM.indexOf(char) !== -1) {
                units++;
            } else if (SMS_GSM_EXTENSION.indexOf(char) !== -1) {
                units += 2;
            } else {
                gsm = false;
            }
        });

        if (!gsm) {
            units = plain.length;
        }

        var single = gsm ? 160 : 70;
        var perPart = units > single ? (gsm ? 153 : 67) : single;
        var parts = units === 0 ? 0 : Math.ceil(units / perPart);

        return { encoding: gsm ? 'gsm' : 'ucs2', units: units, parts: parts, per_part: perPart, left: Math.max(1, parts) * perPart - units };
    }

    // Reply by WhatsApp or by SMS: whom it goes to and the time left (the
    // customer's 24 hours, or 7 days after their last SMS), asked of the
    // server first (ws_whatsapp_reply_form, ws_sms_reply_form); the answer
    // then waits in its queue (mail_phone.php) and reads Waiting to be sent
    // or Not sent until it left. An SMS answer is text only and counts the
    // messages it makes.
    function whatsappForm(message, view, h, sms) {
        var conversation = sms ? message.sms_reply : message.whatsapp_reply;

        h.api(sms ? 'ws_sms_reply_form' : 'ws_whatsapp_reply_form', { conversation_id: conversation }).then(function (data) {
            whatsappAnswer(conversation, data, view, h, sms);
        }).catch(function (error) {
            h.fail(error);
        });
    }

    // Inside the 24 hours: the answer and one file of Files (its words the
    // caption); after them: an approved template, its preview and a field per
    // variable. Both go through ws_whatsapp_reply as a form.
    function whatsappAnswer(conversation, data, view, h, sms) {
        var t = h.t;
        var el = h.el;
        var title = t(sms ? 'sms_reply' : 'wa_reply');
        var node = h.offcanvas('ws-wa-reply', title);
        var body = h.clear(node.querySelector('.drawer-body'));
        var footer = h.clear(node.querySelector('.drawer-footer'));
        var form = el('form', 'ws-wa-answer');
        var open = data.seconds > 0;
        var templates = data.templates || [];
        var send = h.button('btn btn-solid theme-primary rounded-pill px-5', t(sms ? 'sms_reply_send' : 'wa_reply_send'), sms ? 'bi-chat-dots' : 'bi-whatsapp');
        var post;

        node.querySelector('.drawer-title').textContent = title;
        footer.classList.remove('d-none');
        form.noValidate = true;
        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });
        body.appendChild(form);
        form.appendChild(el('div', 'small fg-secondary mb-1', t('wa_reply_to', data.name ? data.name + ' (' + data.to + ')' : data.to)));
        form.appendChild(el('div', 'small fg-secondary mb-3', data.window));

        if (open) {
            post = whatsappText(form, data, view, h, sms);
        } else if (templates.length) {
            post = whatsappTemplate(form, templates, h);
        } else {
            form.appendChild(el('p', 'small mb-0', t('wa_reply_no_templates')));
            h.showOffcanvas(node);
            return;
        }

        send.addEventListener('click', function () {
            var request = post();

            if (!request) {
                return;
            }

            send.disabled = true;
            request.fields.conversation_id = conversation;

            formPost(h, sms ? 'ws_sms_reply' : 'ws_whatsapp_reply', request.fields, request.files).then(function (result) {
                h.hideOffcanvas(node);
                h.toast(result.state === 'failed' ? t('wa_reply_failed') : t(sms ? 'sms_reply_sent' : 'wa_reply_sent'), result.state === 'failed' ? 'warning' : 'success');
                view.sync();
            }).catch(function (error) {
                send.disabled = false;
                h.fail(error);
            });
        });

        footer.appendChild(send);
        h.showOffcanvas(node);
    }

    // The answer's words, the saved replies and one file of Files in the
    // panel's file field (pg_picker_field(), its markup from the server).
    // An SMS answer has no file field and counts its length and parts.
    // Returns what the send posts, or null after pointing at what is missing.
    function whatsappText(form, data, view, h, sms) {
        var t = h.t;
        var el = h.el;
        var text = el('textarea', 'form-control');

        text.id = h.nextId('ws-wa-reply-');
        text.name = 'text';
        text.rows = 8;
        text.maxLength = data.max || 4096;

        var box = el('div', 'ws-mail-reply-box position-relative');
        box.appendChild(text);
        form.appendChild(h.formRow(t('wa_reply_text'), box, t(sms ? 'sms_reply_hint' : 'wa_reply_hint')));
        savedReplies(text, box, view, h);

        if (sms) {
            var count = el('div', 'small fg-secondary mb-1');
            var kind = el('div', 'small fg-secondary');
            var counted = function () {
                var parts = smsParts(text.value);

                count.textContent = t('sms_reply_count', Array.from(text.value.trim()).length, text.maxLength, parts.parts);
                kind.textContent = t(parts.encoding === 'gsm' ? 'sms_reply_gsm' : 'sms_reply_ucs2');
            };

            count.setAttribute('aria-live', 'polite');
            text.setAttribute('aria-describedby', count.id = h.nextId('ws-sms-count-'));
            text.addEventListener('input', counted);
            form.appendChild(count);
            form.appendChild(kind);
            counted();
        }

        var value = null;

        if (data.file_field) {
            var field = el('div');

            field.innerHTML = data.file_field;
            form.appendChild(h.formRow(t('wa_reply_file'), field, t('wa_reply_file_hint')));
            value = field.querySelector('[data-pg-picker-value]');
        }

        setTimeout(function () {
            text.focus();
        }, 0);

        return function () {
            var file = value ? value.value : '';

            if (!text.value.trim() && !file) {
                text.focus();
                return null;
            }

            return { fields: { text: text.value, file_id: file }, files: [] };
        };
    }

    // A template's text as WhatsApp draws it: *bold*, _italic_, ~strike~,
    // each variable as its typed value, or its name while it is empty, in a
    // mark.
    function whatsappFormat(source, part, values, names, offer) {
        var marks = [];
        var html = String(source || '').replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/g, function (all, key) {
            var slot = part + ':' + key;
            var typed = values[slot] && values[slot].trim();

            marks.push(typed || names[slot] || offer);
            return '\u0001' + (marks.length - 1) + '\u0002';
        });

        html = html.replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
        html = html.replace(/\*([^*\n]+)\*/g, '<b>$1</b>').replace(/_([^_\n]+)_/g, '<i>$1</i>').replace(/~([^~\n]+)~/g, '<s>$1</s>');

        return html.replace(/\u0001([0-9]+)\u0002/g, function (all, index) {
            return '<mark>' + String(marks[index]).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            }) + '</mark>';
        }).replace(/\n/g, '<br>');
    }

    // The chosen template as the customer reads it: the template designer's
    // WhatsApp message (.pg-wa-msg in its own colours), redrawn as the values
    // are typed.
    function whatsappPreview(item, values, h) {
        var el = h.el;
        var names = {};
        var shell = el('div', 'pg-wa-designer mb-5');
        var chat = el('div', 'pg-wa-chat rounded');
        var message = el('div', 'pg-wa-msg');
        var offer = h.t('wa_reply_offer');

        item.slots.forEach(function (slot) {
            names[slot.key] = slot.label;
        });

        chat.style.background = 'var(--pg-wa-chat-bg)';
        chat.style.minHeight = '0';

        if (item.header) {
            var head = el('div', 'pg-wa-msg-head');

            head.innerHTML = whatsappFormat(item.header, 'header', values, names, offer);
            message.appendChild(head);
        }

        var body = el('div', 'pg-wa-msg-body');

        body.innerHTML = whatsappFormat(item.body, 'body', values, names, offer);
        message.appendChild(body);

        if (item.footer) {
            message.appendChild(el('div', 'pg-wa-msg-footer', item.footer));
        }

        var time = el('time', '', new Date().toTimeString().slice(0, 5));

        message.appendChild(time);
        (item.buttons || []).forEach(function (label) {
            message.appendChild(el('div', 'pg-wa-msg-button', label));
        });

        chat.appendChild(message);
        shell.appendChild(el('div', 'form-label', h.t('wa_reply_preview')));
        shell.appendChild(chat);

        return shell;
    }

    // The approved templates, the chosen one as the customer reads it and a
    // field per variable, named as the template designer named it.
    function whatsappTemplate(form, templates, h) {
        var t = h.t;
        var el = h.el;
        var choice = h.select([['', t('wa_reply_template_pick')]].concat(templates.map(function (item) {
            return [item.key, item.name + ' (' + item.language + ')'];
        })), '');
        var holder = el('div');
        var inputs = [];

        form.appendChild(h.formRow(t('wa_reply_template'), choice, t('wa_reply_template_hint')));
        form.appendChild(holder);

        function chosen() {
            return templates.filter(function (item) {
                return item.key === choice.value;
            })[0] || null;
        }

        function typed() {
            var values = {};

            inputs.forEach(function (input) {
                values[input.dataset.slot] = input.value;
            });

            return values;
        }

        choice.addEventListener('change', function () {
            var item = chosen();
            var preview = null;

            h.clear(holder);
            inputs = [];

            if (!item) {
                return;
            }

            function redraw() {
                var next = whatsappPreview(item, typed(), h);

                holder.replaceChild(next, preview);
                preview = next;
            }

            preview = whatsappPreview(item, {}, h);
            holder.appendChild(preview);

            if (item.marketing) {
                holder.appendChild(el('p', 'small fg-secondary mb-5', t('wa_reply_marketing')));
            }

            item.slots.forEach(function (slot) {
                var input = el('input', 'form-control');

                input.type = 'text';
                input.maxLength = 1000;
                input.dataset.slot = slot.key;
                input.addEventListener('input', redraw);
                holder.appendChild(h.formRow(slot.label, input));
                inputs.push(input);
            });
        });

        return function () {
            var item = chosen();
            var empty = null;

            if (!item) {
                choice.focus();
                return null;
            }

            inputs.forEach(function (input) {
                if (!empty && !input.value.trim()) {
                    empty = input;
                }
            });

            if (empty) {
                empty.focus();
                return null;
            }

            return { fields: { template: item.key, values: JSON.stringify(typed()) }, files: [] };
        };
    }

    // Under a conversation's card: Reply, Forward, Send again, the status
    // and holder. Under an answer that could not leave: Send again only. A
    // WhatsApp conversation's card: Reply by WhatsApp while the customer's
    // 24 hours are open, the status and holder.
    function node(message, view, h) {
        var row = h.el('div', 'ws-mail-reply mt-1');

        // After the 24 hours the drawer offers the approved templates.
        if (message.whatsapp_reply) {
            var answer = h.button('btn btn-sm btn-outline theme-success py-0', h.t('wa_reply'), 'bi-whatsapp');

            answer.addEventListener('click', function (event) {
                event.stopPropagation();
                whatsappForm(message, view, h);
            });
            row.appendChild(answer);

            if (!(message.whatsapp_window > 0)) {
                row.appendChild(h.el('span', 'small fg-secondary ms-2', h.t('wa_reply_closed')));
            }
        }

        // An SMS goes only within 7 days of the number's last SMS.
        if (message.sms_reply) {
            if (message.sms_window > 0) {
                var sms = h.button('btn btn-sm btn-outline theme-primary py-0', h.t('sms_reply'), 'bi-chat-dots');

                sms.addEventListener('click', function (event) {
                    event.stopPropagation();
                    whatsappForm(message, view, h, true);
                });
                row.appendChild(sms);
            } else {
                row.appendChild(h.el('span', 'small fg-secondary', h.t('sms_reply_closed')));
            }
        }

        if (message.mail_reply) {
            var open = h.button('btn btn-sm btn-outline theme-primary py-0', h.t('mail_reply'), 'bi-reply');

            open.addEventListener('click', function (event) {
                event.stopPropagation();
                replyForm(message, view, h);
            });

            row.appendChild(open);
        }

        // Forward as e-mail: the New e-mail form filled with this mail.
        if (message.mail_reply && message.mail_forward && h.cfg.mail_forward) {
            var pass = h.button('btn btn-sm btn-outline theme-secondary py-0 ms-2', h.t('mail_fwd'), 'bi-envelope-arrow-up');

            pass.addEventListener('click', function (event) {
                event.stopPropagation();
                forward(h, { kind: 'mail', ids: [message.mail_forward] }, { sent: function () { view.sync(); } });
            });
            row.appendChild(pass);
        }

        if (message.mail_resend) {
            row.appendChild(resend(message, view, h));
        }

        if (message.mail_state) {
            state(row, message, view, h);
        }

        return row;
    }

    // Send again, under a mail that could not leave: pressed once, the
    // button stays off until the screen draws the card again.
    function resend(message, view, h) {
        var again = h.button('btn btn-sm btn-outline theme-danger py-0 ms-2', h.t('mail_send_again'), 'bi-arrow-repeat');

        again.addEventListener('click', function (event) {
            event.stopPropagation();
            again.disabled = true;

            h.api('ws_mail_resend', { item_id: message.mail_resend }).then(function () {
                h.toast(h.t('mail_send_again_done'), 'success');
                view.sync();
            }).catch(function (error) {
                again.disabled = false;
                h.fail(error);
            });
        });

        return again;
    }

    // The conversation's status and holder, changed at once.
    function state(row, message, view, h) {
        var t = h.t;
        var current = message.mail_state;
        var change = function (data, control) {
            data.conversation_id = message.mail_reply || message.whatsapp_reply || message.sms_reply;
            control.disabled = true;

            h.api('ws_mail_conversation', data).then(function () {
                view.sync();
            }).catch(function (error) {
                control.disabled = false;
                h.fail(error);
            });
        };

        var status = h.select(['open', 'waiting', 'closed'].map(function (key) {
            return [key, t('mail_status_' + key)];
        }), current.status);

        status.classList.add('d-inline-block', 'w-auto', 'ms-2', 'py-0');
        status.setAttribute('aria-label', t('mail_status'));
        status.addEventListener('change', function () {
            change({ status: status.value }, status);
        });

        // Who holds it: nobody, the viewer, or a member of the channel.
        var people = [['', t('mail_nobody')], [String(current.me), t('mail_me')]];
        var seen = {};

        seen[people[1][0]] = true;
        ((view.channel && view.channel.members) || []).forEach(function (member) {
            var id = String(member.id);

            if (!seen[id]) {
                seen[id] = true;
                people.push([id, member.name]);
            }
        });

        // A holder who is not among the members; the card names them.
        if (current.assignee && !seen[String(current.assignee)]) {
            people.push([String(current.assignee), t('mail_other')]);
        }

        var hold = h.select(people, current.assignee ? String(current.assignee) : '');

        hold.classList.add('d-inline-block', 'w-auto', 'ms-2', 'py-0');
        hold.setAttribute('aria-label', t('mail_holder'));
        hold.addEventListener('change', function () {
            change({ assign: hold.value === '' ? 'none' : hold.value }, hold);
        });

        row.appendChild(status);
        row.appendChild(hold);
    }

    // Under Pinegrap AI's suggestion: its reply draft, opened in the drawer.
    function draftNode(message, view, h) {
        var row = h.el('div', 'ws-mail-draft mt-1');
        var open = h.button('btn btn-sm btn-outline theme-primary py-0', h.t('mail_draft_open'), 'bi-pencil-square');
        var offer = message.mail_draft;

        open.addEventListener('click', function (event) {
            event.stopPropagation();
            replyForm({ mail_reply: offer.conversation_id, sender: { title: offer.to } }, view, h, offer.text);
        });

        row.appendChild(open);

        return row;
    }

    // ── A mail's HTML ──────────────────────────────────────────────────
    //
    // The pane draws a mail's kept HTML in a guarded frame (mail_pane.php
    // ws_mail_pane_body_html(), includes/fn/guarded_frame.php's mail mode).
    // The frame's height follows the mail once its shell reports it. Its
    // pictures from outside wait for Show pictures, which loads the frame's
    // second address and is remembered for the sender in this browser, one
    // list per site and person.

    var PICTURES_PREFIX = 'ws-mail-pictures:';
    var PICTURES_MAX = 500;
    var FRAME_MAX = 100000;

    function picturesKey(site, viewer) {
        return PICTURES_PREFIX + String(site || '') + '|' + (parseInt(viewer, 10) || 0);
    }

    // The remembered senders, lower case, newest first; [] for anything else.
    function picturesSenders(stored) {
        var value = null;
        var out = [];

        try {
            value = JSON.parse(String(stored || ''));
        } catch (error) {
            return out;
        }

        if (!Array.isArray(value)) {
            return out;
        }

        value.forEach(function (sender) {
            if (typeof sender !== 'string') {
                return;
            }

            sender = sender.trim().toLowerCase();

            if (sender !== '' && sender.length <= 320 && out.indexOf(sender) < 0 && out.length < PICTURES_MAX) {
                out.push(sender);
            }
        });

        return out;
    }

    // The list with the sender first; the oldest fall off past the limit.
    function picturesAdd(senders, sender) {
        sender = String(sender || '').trim().toLowerCase();

        var out = (sender !== '' && sender.length <= 320) ? [sender] : [];

        (senders || []).forEach(function (one) {
            if (one !== sender && out.length < PICTURES_MAX) {
                out.push(one);
            }
        });

        return out;
    }

    // A height a frame reports, as whole pixels within bounds; -1 if none.
    function frameHeight(value) {
        if (typeof value !== 'number' || !isFinite(value) || value < 0) {
            return -1;
        }

        return Math.min(Math.round(value), FRAME_MAX);
    }

    // The E-mails screen (workspace_mail.php): a row opens its conversation
    // in the reading pane beside the list, Load more reads the next page
    // under the rows, Mark all as read clears the marks and the menu's
    // number. A row's address is this screen with its conversation, whose
    // pane the server draws, so before the script starts, or without it, a
    // row still opens the mail, never a channel. The pane's HTML is the
    // server's, its texts escaped there (includes/workspace/mail_pane.php).
    function screen(root, h) {
        var words = (h.cfg && h.cfg.mail && h.cfg.mail.strings) || {};
        var pane = root.querySelector('[data-ws-mail-pane]');
        var current = 0;

        function w(key) {
            return words[key] || key;
        }

        function each(selector, callback) {
            Array.prototype.forEach.call(document.querySelectorAll(selector), callback);
        }

        // The number on an E-mails tile, where a page still draws one; the
        // Communication row of the menu counts again on the next page.
        function badge(count) {
            each('[data-ws-nav="mail"]', function (tile) {
                var node = tile.querySelector('.ws-count');

                if (!count) {
                    if (node) {
                        node.parentNode.removeChild(node);
                    }

                    return;
                }

                if (!node) {
                    node = h.el('span', 'ws-count');
                    tile.appendChild(node);
                }

                node.textContent = (count > 99) ? '99+' : String(count);
            });
        }

        function read(row) {
            var hidden = row.querySelector('.ws-mail-who + .visually-hidden');

            row.classList.remove('ws-mail-unread');

            if (hidden) {
                hidden.parentNode.removeChild(hidden);
            }
        }

        // The open conversation stays in the address, for a reload.
        function address(id) {
            if (!window.history || !window.URL) {
                return;
            }

            var url = new URL(window.location.href);

            if (id) {
                url.searchParams.set('conversation', String(id));
            } else {
                url.searchParams.delete('conversation');
            }

            window.history.replaceState(window.history.state, '', url.toString());
        }

        function mark(row) {
            each('.ws-mail-row[aria-current]', function (node) {
                node.removeAttribute('aria-current');
            });

            if (row) {
                row.setAttribute('aria-current', 'true');
            }
        }

        var slot = picturesKey(h.cfg && h.cfg.api_url, h.cfg && h.cfg.me);

        // Every read and write of the list may fail (a private window,
        // blocked storage, a full quota): the pictures then show for this
        // reading only.
        function remembered() {
            var stored = null;

            try {
                stored = window.localStorage.getItem(slot);
            } catch (error) {
                stored = null;
            }

            return picturesSenders(stored);
        }

        function remember(sender) {
            try {
                window.localStorage.setItem(slot, JSON.stringify(picturesAdd(remembered(), sender)));
            } catch (error) {
                // Shown, not remembered.
            }
        }

        // Each of the sender's frames loads with its pictures; its line goes.
        function pictures(sender) {
            Array.prototype.forEach.call(pane.querySelectorAll('.ws-mail-frame[data-ws-mail-pictures-src]'), function (frame) {
                if (frame.getAttribute('data-ws-mail-sender') !== sender) {
                    return;
                }

                var body = frame.closest('[data-ws-mail-body]');
                var line = body && body.querySelector('[data-ws-mail-pictures]');

                frame.setAttribute('src', frame.getAttribute('data-ws-mail-pictures-src'));
                frame.removeAttribute('data-ws-mail-pictures-src');

                if (line) {
                    line.parentNode.removeChild(line);
                }
            });
        }

        // A drawn pane: the senders whose pictures were asked for before.
        function dress() {
            var senders = remembered();

            Array.prototype.forEach.call(pane.querySelectorAll('.ws-mail-frame[data-ws-mail-pictures-src]'), function (frame) {
                var sender = frame.getAttribute('data-ws-mail-sender');

                if (sender && senders.indexOf(sender) >= 0) {
                    pictures(sender);
                }
            });
        }

        // Plain text and back: the frame or the mail's text, one at a time.
        function plainText(button) {
            var body = button.closest('[data-ws-mail-body]');
            var on = button.getAttribute('aria-pressed') !== 'true';
            var frame = body.querySelector('.ws-mail-frame');
            var text = body.querySelector('.ws-mail-text');
            var line = body.querySelector('[data-ws-mail-pictures]');

            button.setAttribute('aria-pressed', on ? 'true' : 'false');
            frame.hidden = on;
            text.hidden = !on;

            if (line) {
                line.hidden = on;
            }
        }

        // A frame's shell reports its mail's height; only a frame of this
        // pane, from this site, is heard.
        window.addEventListener('message', function (event) {
            var data = event.data;

            if (!data || typeof data !== 'object' || data.pg !== 'guarded-frame' || event.origin !== window.location.origin) {
                return;
            }

            var height = frameHeight(data.height);

            if (height < 0) {
                return;
            }

            Array.prototype.forEach.call(pane.querySelectorAll('.ws-mail-frame'), function (frame) {
                if (frame.contentWindow && frame.contentWindow === event.source) {
                    frame.style.height = height + 'px';
                }
            });
        });

        function close() {
            current = 0;
            pane.hidden = true;
            h.clear(pane);
            root.classList.remove('ws-mail-reading');
            mark(null);
            address(0);
        }

        // The answer and the status controls, for whoever may write in the
        // conversation's channel; a change draws the pane again.
        function tools(data) {
            var place = pane.querySelector('.ws-mail-pane-tools');
            var shut = h.button('btn btn-sm btn-text theme-secondary', w('close'), 'bi-x-lg');

            shut.addEventListener('click', close);
            place.appendChild(shut);

            if (!data.reply) {
                return;
            }

            var message = { mail_reply: data.reply, sender: { title: data.to }, mail_state: data.state };
            var view = {
                channel: { id: data.channel_id, members: data.members || [] },
                sync: function () {
                    open(data.reply);
                }
            };
            var answer = h.button('btn btn-sm btn-outline theme-primary', h.t('mail_reply'), 'bi-reply');

            answer.addEventListener('click', function () {
                replyForm(message, view, h);
            });
            place.insertBefore(answer, place.firstChild);

            // Reply to all, when the last mail named anybody besides the
            // customer and the mailbox.
            if (data.others) {
                var everyone = h.button('btn btn-sm btn-outline theme-primary', h.t('mail_reply_all'), 'bi-reply-all');

                everyone.addEventListener('click', function () {
                    replyForm(message, view, h, '', true);
                });
                place.insertBefore(everyone, answer.nextSibling);
            }

            if (data.state) {
                state(place, message, view, h);
            }
        }

        function open(id) {
            var row = root.querySelector('[data-ws-mail-row="' + id + '"]');
            var again = (current === id);

            current = id;
            mark(row);
            pane.hidden = false;
            root.classList.add('ws-mail-reading');

            if (!again) {
                h.clear(pane).appendChild(h.el('div', 'ws-empty', w('loading')));
            }

            h.api('ws_mail_pane', { conversation_id: id }).then(function (data) {
                if (current !== id) {
                    return;
                }

                // A redraw replaces the button that had focus (a sent answer
                // returns focus to its Reply button), so focus would fall to
                // the page; the heading keeps the keyboard in the pane.
                var focused = document.activeElement;
                var keep = again && (focused === document.body || pane.contains(focused));

                pane.innerHTML = data.html;
                dress();
                tools(data);
                address(id);
                badge(data.unread || 0);

                if (row) {
                    read(row);
                }

                if (!again) {
                    pane.scrollTop = 0;
                    pane.querySelector('h2').focus();
                } else if (keep) {
                    pane.querySelector('h2').focus({ preventScroll: true });
                }
            }).catch(function (error) {
                if (current === id) {
                    close();
                }

                h.fail(error);
            });
        }

        function more(link) {
            var data = {};

            new URLSearchParams(link.search).forEach(function (value, key) {
                data[key] = value;
            });

            link.classList.add('disabled');
            link.setAttribute('aria-disabled', 'true');

            h.api('ws_mail_rows', data).then(function (result) {
                var holder = link.parentNode;

                root.querySelector('[data-ws-mail-rows]').insertAdjacentHTML('beforeend', result.html);
                holder.insertAdjacentHTML('afterend', result.more || '');
                holder.parentNode.removeChild(holder);
            }).catch(function (error) {
                link.classList.remove('disabled');
                link.removeAttribute('aria-disabled');
                h.fail(error);
            });
        }

        // Check e-mail: the server reads the person's boxes now (or the box
        // the list is narrowed to) and answers the line over the list; when
        // mail came, the list as the address filters it, the open
        // conversation still marked.
        function check(button) {
            var line = root.querySelector('[data-ws-mail-check-line]');
            var data = { box_id: button.getAttribute('data-ws-mail-check') || '0', conversation: String(current || 0) };

            new URLSearchParams(window.location.search).forEach(function (value, key) {
                if (key !== 'before' && key !== 'conversation') {
                    data[key] = value;
                }
            });

            // A read can hold the request for many seconds: the button says
            // it is working until the answer comes.
            var label = button.innerHTML;

            function done() {
                button.disabled = false;
                button.removeAttribute('aria-busy');
                button.innerHTML = label;
            }

            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.innerHTML = '';
            button.appendChild(h.el('span', 'spinner-border spinner-border-sm me-1')).setAttribute('aria-hidden', 'true');
            button.appendChild(document.createTextNode(w('checking')));

            h.api('ws_mail_check', data).then(function (result) {
                var list = root.querySelector('.ws-mail-list');

                done();

                if (line) {
                    line.innerHTML = result.html;
                    line.hidden = false;
                }

                if (result.list && list) {
                    list.innerHTML = result.list;
                }
            }).catch(function (error) {
                done();
                h.fail(error);
            });
        }

        // The mail's own buttons, in the pane however it was drawn.
        pane.addEventListener('click', function (event) {
            var target = event.target.closest('[data-ws-mail-plain], [data-ws-mail-show-pictures]');

            if (!target) {
                return;
            }

            if (target.hasAttribute('data-ws-mail-plain')) {
                plainText(target);

                return;
            }

            var body = target.closest('[data-ws-mail-body]');
            var frame = body && body.querySelector('.ws-mail-frame[data-ws-mail-sender]');
            var sender = frame ? frame.getAttribute('data-ws-mail-sender') : '';
            var plain = body && body.querySelector('[data-ws-mail-plain]');

            if (!sender) {
                return;
            }

            remember(sender);
            pictures(sender);

            // The pressed button is gone; the keyboard stays on the mail.
            if (plain) {
                plain.focus();
            }
        });

        root.addEventListener('click', function (event) {
            var target = event.target.closest('[data-ws-mail-more], [data-ws-mail-read-all], [data-ws-mail-check], [data-ws-mail-row], [data-ws-mail-new], [data-ws-mail-forward], [data-ws-mail-calendar], [data-ws-mail-close]');

            // A new tab or window keeps the link's own way.
            if (!target || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();

            if (target.hasAttribute('data-ws-mail-check')) {
                check(target);
            } else if (target.hasAttribute('data-ws-mail-new')) {
                // The new conversation opens in the pane once sent.
                compose(h, { sent: function (data) { open(data.conversation_id); } });
            } else if (target.hasAttribute('data-ws-mail-forward')) {
                // A mail of the pane forwarded; the new conversation opens.
                forward(h, { kind: 'mail', ids: [parseInt(target.getAttribute('data-ws-mail-forward'), 10)] }, { sent: function (data) { open(data.conversation_id); } });
            } else if (target.hasAttribute('data-ws-mail-calendar')) {
                // Add to my calendar (workspace_my_calendar.js).
                if (window.PGWsMyCalendar) {
                    window.PGWsMyCalendar.fromMail(h, parseInt(target.getAttribute('data-ws-mail-calendar'), 10));
                }
            } else if (target.hasAttribute('data-ws-mail-more')) {
                more(target);
            } else if (target.hasAttribute('data-ws-mail-row')) {
                open(parseInt(target.getAttribute('data-ws-mail-row'), 10));
            } else if (target.hasAttribute('data-ws-mail-close')) {
                // The server's Close, until the pane is read again.
                close();
            } else {
                target.disabled = true;

                h.api('ws_mail_read_all').then(function () {
                    target.disabled = false;
                    each('.ws-mail-unread', read);
                    badge(0);
                    h.toast(w('read_all'), 'success');
                }).catch(function (error) {
                    target.disabled = false;
                    h.fail(error);
                });
            }
        });

        var start = parseInt(new URLSearchParams(window.location.search).get('conversation') || '0', 10);

        if (start > 0) {
            // The server drew this conversation's pane for the address
            // (workspace_mail.php): it is read again for the answer and the
            // status controls, without a loading line in between.
            if (pane.querySelector('h2')) {
                current = start;
                dress();
            }

            open(start);
        }
    }

    // ── New e-mail ─────────────────────────────────────────────────────
    //
    // The form (From, Kept in, To, Cc, Bcc, Subject, the text, files) in a
    // drawer, opened from the E-mails screen's toolbar and from the menu of
    // a channel a mailbox keeps its mail in. The mailboxes, their channels
    // and Signatures come from the server (ws_mail_compose); Send posts the
    // form with its files as form parts (ws_mail_new), and the mail leaves
    // through the mail queue (includes/workspace/mail_send.php). The unsent
    // form is kept in this browser, one slot per site and person, like the
    // composer's unsent text (workspace.js draftKey()).
    //
    // Forward as e-mail opens the same form filled from chosen channel
    // messages or one mail (ws_mail_forward, includes/workspace/
    // mail_forward.php): a subject, the original quoted under the person's
    // words and its files carried. Send names the source again
    // (forward_kind, forward_ids, forward_files) and the server reads it
    // anew. An unsent forward has a slot of its own, kept for that source.

    var NEW_PREFIX = 'ws-mail-new:';
    var NEW_DAYS = 14;
    var NEW_FIELDS = ['to', 'cc', 'bcc', 'subject', 'text'];

    function newDraftKey(site, viewer) {
        return NEW_PREFIX + String(site || '') + '|' + (parseInt(viewer, 10) || 0);
    }

    // A kept form as { box, channel, to, cc, bcc, subject, text, at } while
    // it is younger than NEW_DAYS and holds words; null otherwise. A kept
    // forward also names its source.
    function newDraftFresh(stored, now) {
        var value = null;

        try {
            value = JSON.parse(String(stored || ''));
        } catch (error) {
            return null;
        }

        if (!value || (typeof value !== 'object') || (typeof value.at !== 'number') || !(value.at > now - NEW_DAYS * 86400000)) {
            return null;
        }

        var out = { box: parseInt(value.box, 10) || 0, channel: parseInt(value.channel, 10) || 0, at: value.at };
        var words = false;

        if (typeof value.source === 'string') {
            out.source = value.source;
        }

        NEW_FIELDS.forEach(function (field) {
            out[field] = (typeof value[field] === 'string') ? value[field] : '';
            words = words || (out[field].trim() !== '');
        });

        return words ? out : null;
    }

    // Every read and write of the slot may fail (a private window, blocked
    // storage, a full quota): the form then works without it.
    function newDraftStore(key, value) {
        try {
            if (value) {
                window.localStorage.setItem(key, JSON.stringify(value));
            } else {
                window.localStorage.removeItem(key);
            }
        } catch (error) {
            // Nothing kept.
        }
    }

    function newDraftRead(key) {
        try {
            var stored = window.localStorage.getItem(key);
            var value = newDraftFresh(stored, Date.now());

            if (stored && !value) {
                window.localStorage.removeItem(key);
            }

            return value;
        } catch (error) {
            return null;
        }
    }

    // A form posted with its files as form parts (New e-mail, the answer);
    // read as api() reads an answer.
    // The warning of an address whose domain does not exist (code
    // unknown_domain), over the drawer's buttons. Send anyway posts the same
    // form again with send_anyway; Change the address puts the cursor in the
    // address field. Either takes the warning away.
    function domainWarning(h, footer, message, again, field) {
        domainWarningClear(footer);

        var box = h.el('div', 'ws-mail-domain-warning alert theme-warning small mx-4 mb-3', '');
        var actions = h.el('div', 'd-flex flex-wrap gap-2 mt-2', '');
        var anyway = h.button('btn btn-sm btn-solid theme-warning rounded-pill', h.t('mail_send_anyway'), 'bi-send');
        var change = h.button('btn btn-sm btn-outline theme-secondary rounded-pill', h.t('mail_change_address'), 'bi-pencil');

        box.setAttribute('role', 'alert');
        box.appendChild(h.el('div', '', message));
        anyway.addEventListener('click', function () {
            domainWarningClear(footer);
            again();
        });
        change.addEventListener('click', function () {
            domainWarningClear(footer);
            field.focus();
        });
        actions.appendChild(anyway);
        actions.appendChild(change);
        box.appendChild(actions);
        footer.parentNode.insertBefore(box, footer);
        anyway.focus();
    }

    function domainWarningClear(footer) {
        var old = footer.parentNode ? footer.parentNode.querySelector('.ws-mail-domain-warning') : null;

        if (old) {
            old.parentNode.removeChild(old);
        }
    }

    function formPost(h, action, fields, files) {
        var form = new FormData();

        form.append('action', action);
        form.append('token', h.cfg.token);
        Object.keys(fields).forEach(function (key) {
            form.append(key, fields[key]);
        });
        files.forEach(function (file) {
            form.append('files[]', file, file.name);
        });

        return fetch(h.cfg.api_url, { method: 'POST', credentials: 'same-origin', body: form }).then(function (response) {
            return response.json().catch(function () {
                throw new Error(h.t('error_generic'));
            });
        }).then(function (json) {
            if (!json || json.status !== 'success') {
                var error = new Error((json && json.message) ? json.message : h.t('error_generic'));

                error.field = json ? json.field : '';
                error.code = (json && json.code) ? json.code : '';
                throw error;
            }

            return json.data || {};
        });
    }

    // options.channel: the channel the form was opened from; its first
    // mailbox is chosen. options.sent(data) runs after a send.
    function compose(h, options) {
        options = options || {};

        h.api('ws_mail_compose').then(function (data) {
            newForm(h, data.boxes || [], data.writer || '', options, data.file_field || '');
        }).catch(function (error) {
            h.fail(error);
        });
    }

    // source: { kind: 'messages' | 'mail', ids }. options.channel and
    // options.sent as compose() takes them; the source's channel is the
    // default.
    function forward(h, source, options) {
        options = options || {};

        h.api('ws_mail_forward', { kind: source.kind, ids: source.ids.join(',') }).then(function (data) {
            var fill = data.forward || null;

            newForm(h, data.boxes || [], data.writer || '', {
                channel: options.channel || (fill ? fill.channel_id : 0),
                sent: options.sent,
                fill: fill
            });
        }).catch(function (error) {
            h.fail(error);
        });
    }

    function newForm(h, boxes, writer, options, fileField) {
        var t = h.t;
        var el = h.el;
        var fill = options.fill || null;
        var title = fill ? t('mail_fwd') : t('mail_new');
        var node = h.offcanvas('ws-mail-compose', title);
        var body = h.clear(node.querySelector('.drawer-body'));
        var footer = h.clear(node.querySelector('.drawer-footer'));
        // A forward keeps its unsent form apart, and only for its source.
        var key = newDraftKey(h.cfg.api_url, h.cfg.me) + (fill ? '|fwd' : '');
        var kept = newDraftRead(key);
        var files = [];
        // The source's files the forward carries; each can be taken out.
        var carried = fill ? fill.files.slice() : [];
        var timer = 0;

        if (fill && kept && (kept.source !== fill.source)) {
            kept = null;
        }

        node.querySelector('.drawer-title').textContent = title;

        if (!boxes.length) {
            footer.classList.add('d-none');
            body.appendChild(el('div', 'ws-empty', t('mail_new_none')));
            h.showOffcanvas(node);
            return;
        }

        footer.classList.remove('d-none');

        var form = el('form', 'ws-mail-new');
        form.noValidate = true;
        form.setAttribute('data-ws-mail-new-form', '');
        body.appendChild(form);

        function boxOf(id) {
            return boxes.filter(function (box) { return box.id === id; })[0] || null;
        }

        // The mailbox: the one keeping its mail in the channel the form was
        // opened from, else the kept form's, else the first. A kept forward
        // keeps the mailbox it was given.
        var start = (fill && kept) ? boxOf(kept.box) : null;

        if (!start && options.channel) {
            start = boxes.filter(function (box) {
                return box.channels.some(function (channel) { return channel.id === options.channel; });
            })[0] || null;
        }

        start = start || (kept && boxOf(kept.box)) || boxes[0];

        var from = h.select(boxes.map(function (box) {
            return [String(box.id), box.name + ' <' + box.address + '>'];
        }), String(start.id));
        from.name = 'box_id';
        form.appendChild(h.formRow(t('mail_new_from'), from));

        var where = el('select', 'form-control form-control-sm');
        where.name = 'channel_id';
        form.appendChild(h.formRow(t('mail_new_channel'), where, t('mail_new_channel_hint')));

        function input(name, label, type, hint) {
            var field = el('input', 'form-control form-control-sm');

            field.type = type || 'text';
            field.name = name;
            field.autocomplete = 'off';

            if (name !== 'subject') {
                // Several addresses, separated by commas.
                field.inputMode = 'email';
                field.spellcheck = false;
                field.setAttribute('autocapitalize', 'off');
            } else {
                field.maxLength = 200;
            }

            var row = h.formRow(label, field, hint);

            form.appendChild(row);

            return { field: field, row: row };
        }

        var to = input('to', t('mail_new_to'), 'text', t('mail_new_addresses'));
        var copies = el('div', 'ws-mail-new-copies');
        var showCopies = h.button('btn btn-sm btn-text theme-secondary ws-mail-new-cc-toggle', t('mail_new_cc_bcc'), 'bi-plus-lg');

        form.appendChild(showCopies);
        form.appendChild(copies);

        var cc = input('cc', t('mail_new_cc'));
        var bcc = input('bcc', t('mail_new_bcc'));

        copies.appendChild(cc.row);
        copies.appendChild(bcc.row);
        suggest(h, to.field);
        suggest(h, cc.field);
        suggest(h, bcc.field);

        var subject = input('subject', t('mail_new_subject'));
        // A forward's quote is plain text, so it keeps the text area.
        var body = mailText(h, null, '', t('mail_new_text'), !!fill);

        form.appendChild(h.formRow(t('mail_new_text'), body.node));

        // The mailbox's Signature (or its name), as the mail will carry it.
        var signature = el('div', 'ws-mail-new-signature small fg-secondary');

        body.node.parentNode.appendChild(signature);

        var list = el('ul', 'ws-mail-new-files list-unstyled mb-0');
        // Files picked from Files, posted by id (file_ids).
        var library = [];

        // The forward's carried files first, then the person's own.
        function drawFiles() {
            fileRows(h, list, [carried, library, files]);
        }

        var fileRow = h.formRow(t('mail_new_files'), filesField(h, fileField, library, files, drawFiles), t('mail_new_files_hint'));

        fileRow.appendChild(list);
        form.appendChild(fileRow);

        function drawBox(channelId) {
            var box = boxOf(parseInt(from.value, 10)) || boxes[0];
            var chosen = channelId || parseInt(where.value, 10) || 0;

            h.clear(where);
            box.channels.forEach(function (channel) {
                // A mailbox kept in no channel keeps the mail itself.
                var option = el('option', '', channel.mailbox ? t('mail_new_in_mailbox') : '#' + channel.name);

                option.value = String(channel.id);
                option.selected = (channel.id === chosen);
                where.appendChild(option);
            });

            h.clear(signature);
            signature.appendChild(el('div', '', t('mail_new_signature')));
            signature.appendChild(el('div', 'ws-mail-new-signature-text', '-- \n' + (writer ? writer + '\n' : '') + (box.signature || box.name)));
        }

        function current() {
            var value = {
                box: parseInt(from.value, 10) || 0,
                channel: parseInt(where.value, 10) || 0,
                to: to.field.value,
                cc: cc.field.value,
                bcc: bcc.field.value,
                subject: subject.field.value,
                text: body.value(),
                at: Date.now()
            };

            if (fill) {
                value.source = fill.source;
            }

            return value;
        }

        // The forward's subject and quote, the person's words to go above.
        function filled() {
            subject.field.value = fill.subject;
            body.set(fill.text);
            body.input.setSelectionRange(0, 0);
        }

        // Saved 400 ms after typing pauses; an empty form drops the slot.
        function keep() {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                var value = current();

                newDraftStore(key, newDraftFresh(JSON.stringify(value), value.at) ? value : null);
            }, 400);
        }

        function setCopies(open) {
            copies.hidden = !open;
            showCopies.hidden = open;
        }

        if (kept) {
            to.field.value = kept.to;
            cc.field.value = kept.cc;
            bcc.field.value = kept.bcc;
            subject.field.value = kept.subject;
            body.set(kept.text);
        } else if (fill) {
            filled();
        }

        var keptChannel = (kept && (start.id === kept.box)) ? kept.channel : 0;

        drawBox(fill ? (keptChannel || options.channel || 0) : (options.channel || keptChannel));
        setCopies(!!(cc.field.value || bcc.field.value));
        drawFiles();

        // The source's files that do not fit a mail are named, not carried.
        if (fill && fill.left.length) {
            form.insertBefore(el('div', 'alert theme-warning small mb-5', t('mail_fwd_left', fill.left.join(', '))), form.firstChild);
        }

        from.addEventListener('change', function () {
            drawBox(0);
            keep();
        });
        where.addEventListener('change', keep);
        showCopies.addEventListener('click', function () {
            setCopies(true);
            cc.field.focus();
        });
        form.addEventListener('input', keep);

        if (kept) {
            var note = el('div', 'alert theme-info small d-flex align-items-center gap-3 mb-5', '');
            var discard = h.button('btn btn-sm btn-text theme-secondary ms-auto py-0', t('mail_new_discard'), 'bi-trash');

            note.appendChild(el('span', '', t('mail_new_draft')));
            discard.addEventListener('click', function () {
                window.clearTimeout(timer);
                newDraftStore(key, null);
                form.reset();
                body.set('');
                files = [];
                carried = fill ? fill.files.slice() : [];
                library.length = 0;

                if (fill) {
                    filled();
                }

                drawFiles();
                drawBox(0);
                setCopies(false);
                note.parentNode.removeChild(note);
                to.field.focus();
            });
            note.appendChild(discard);
            form.insertBefore(note, form.firstChild);
        }

        var send = h.button('btn btn-solid theme-primary rounded-pill px-5', t('mail_new_send'), 'bi-send');

        function sendNew(anyway) {
            var fields = current();
            var empty = [[to.field, fields.to], [subject.field, fields.subject], [body.input, fields.text]].filter(function (pair) {
                return !pair[1].trim();
            })[0];

            if (empty) {
                empty[0].focus();
                return;
            }

            send.disabled = true;
            domainWarningClear(footer);

            var post = {
                box_id: fields.box, channel_id: fields.channel, to: fields.to, cc: fields.cc, bcc: fields.bcc,
                subject: fields.subject, text: fields.text
            };

            if (body.rich) {
                post.format = 'markup';
            }

            if (anyway) {
                post.send_anyway = 1;
            }

            if (fill) {
                post.forward_kind = fill.kind;
                post.forward_ids = fill.ids.join(',');
                post.forward_files = carried.map(function (file) { return file.id; }).join(',');
            }

            if (library.length) {
                post.file_ids = library.map(function (file) { return file.id; }).join(',');
            }

            formPost(h, 'ws_mail_new', post, files).then(function (data) {
                window.clearTimeout(timer);
                newDraftStore(key, null);
                send.disabled = false;
                h.hideOffcanvas(node);
                h.toast(data.state === 'failed' ? t('mail_new_failed') : t('mail_new_sent'), data.state === 'failed' ? 'warning' : 'success');

                if (options.sent) {
                    options.sent(data);
                }
            }).catch(function (error) {
                send.disabled = false;

                if (error.code === 'unknown_domain') {
                    domainWarning(h, footer, error.message, function () { sendNew(true); }, to.field);
                    return;
                }

                h.fail(error);
            });
        }

        send.addEventListener('click', function () {
            sendNew(false);
        });

        footer.appendChild(send);
        h.showOffcanvas(node);
        (to.field.value ? body.input : to.field).focus();
    }

    window.PGWsMail = {
        node: node,
        draft: draftNode,
        plain: plain,
        screen: screen,
        compose: compose,
        forward: forward,
        newDraftKey: newDraftKey,
        newDraftFresh: newDraftFresh,
        picturesKey: picturesKey,
        picturesSenders: picturesSenders,
        picturesAdd: picturesAdd,
        frameHeight: frameHeight
    };
})();
