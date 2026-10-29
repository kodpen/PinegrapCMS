/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - saved replies on the channel screen: the ; list of the
 * composer (";" and the start of a shortcut suggest the person's replies,
 * their {customer} and {order} already filled for the channel by the server)
 * and the drawer that lists, puts in, adds, changes and deletes them.
 *
 * assets/js/workspace.js asks for both through window.PGWsReplies and hands
 * over its own helpers. The replies are the person's own; the server reads
 * and writes only theirs (includes/workspace/saved_replies.php). Every text
 * comes from the #ws-config block.
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

    // A list read for a channel is used again for this long; the latest
    // order of its customer may change meanwhile.
    var KEEP_MS = 60000;

    // channel id => { at, items, loading }
    var cache = {};

    // The ; list at the caret, from what is written before it (chips as
    // U+FFFC): ";" and up to 32 letters, digits, - or _ at a line's start,
    // after a space or a chip. Never after a letter or digit, so "a;b" and
    // "&amp;" do not open it. Answers null or { start, word }.
    function at(before) {
        var match = /(^|[\s￼]);([0-9A-Za-zÇĞİÖŞÜÂÎÛçğıöşüâîû_-]{0,32})$/.exec(before);

        return match ? { start: before.length - match[2].length - 1, word: match[2] } : null;
    }

    // The replies a typed word picks: shortcuts starting with it first, then
    // titles holding it, in Turkish lower case; at most limit.
    function match(items, word, limit) {
        var typed = String(word || '').toLocaleLowerCase('tr');
        var first = [];
        var rest = [];

        (items || []).forEach(function (item) {
            if (String(item.shortcut).toLocaleLowerCase('tr').indexOf(typed) === 0) {
                first.push(item);
            } else if (typed !== '' && String(item.title).toLocaleLowerCase('tr').indexOf(typed) !== -1) {
                rest.push(item);
            }
        });

        return first.concat(rest).slice(0, limit);
    }

    // A reply's text on one line, its tags by their names, for a row.
    function preview(item, length) {
        var labels = item.labels || {};
        var text = String(item.text || item.body || '').replace(/<[@#][a-z_]+:[0-9]{1,10}>/g, function (token) {
            return labels[token] || '';
        }).replace(/\s+/g, ' ').trim();

        return (text.length > length) ? text.slice(0, length - 1) + '…' : text;
    }

    // The list for a channel, or null while it is read; done() is called
    // once a list read for the first time is in.
    function cached(channelId, api, done) {
        var key = String(channelId || 0);
        var entry = cache[key];

        if (entry && entry.items && (Date.now() - entry.at < KEEP_MS)) {
            return entry.items;
        }

        if (!entry || !entry.loading) {
            cache[key] = { at: 0, items: entry ? entry.items : null, loading: true };

            api('ws_saved_replies', { channel_id: channelId || 0 }).then(function (data) {
                cache[key] = { at: Date.now(), items: data.items || [], loading: false };

                if (done) {
                    done();
                }
            }).catch(function () {
                cache[key] = { at: Date.now(), items: [], loading: false };
            });
        }

        return entry ? entry.items : null;
    }

    // After a change every channel's list is read again.
    function forget() {
        cache = {};
    }

    // The drawer: the person's replies, each to put into the box, change or
    // delete, and a form for a new one or a change.
    function drawer(composer, h) {
        var t = h.t;
        var el = h.el;
        var node = h.offcanvas('ws-replies', t('replies_title'));
        var body = h.clear(node.querySelector('.drawer-body'));
        var footer = h.clear(node.querySelector('.drawer-footer'));
        var channelId = composer.channel ? composer.channel.id : 0;

        footer.classList.add('d-none');

        function list() {
            h.clear(body);
            footer.classList.add('d-none');

            var add = h.button('btn btn-sm btn-solid theme-primary rounded-pill px-5 mb-5', t('reply_new'), 'bi-plus-lg');
            add.addEventListener('click', function () { form(null); });
            body.appendChild(add);

            var holder = el('div', 'list-group');
            body.appendChild(holder);
            holder.appendChild(el('div', 'small fg-secondary', t('loading')));

            h.api('ws_saved_replies', { channel_id: channelId }).then(function (data) {
                var items = data.items || [];

                h.clear(holder);

                if (!data.ready) {
                    add.disabled = true;
                    holder.appendChild(el('div', 'small fg-secondary', t('replies_not_ready')));
                    return;
                }

                if (!items.length) {
                    holder.appendChild(el('div', 'small fg-secondary', t('replies_none')));
                    return;
                }

                items.forEach(function (item) {
                    holder.appendChild(row(item));
                });
            }).catch(h.fail);
        }

        function row(item) {
            var line = el('div', 'list-group-item d-flex align-items-start gap-3');
            var text = el('div', 'flex-grow-1 min-w-0');

            text.appendChild(el('div', 'fw-semibold text-truncate', item.title));
            text.appendChild(el('div', 'small fg-secondary', ';' + item.shortcut));
            text.appendChild(el('div', 'small text-truncate', preview(item, 120)));
            line.appendChild(text);

            if (composer.rich && composer.channel && composer.channel.can_post) {
                var use = h.button('btn btn-sm btn-text theme-primary', '', 'bi-box-arrow-in-down-left', t('reply_use'));
                use.addEventListener('click', function () {
                    h.hideOffcanvas(node);
                    composer.rich.insertStored(0, item.text, item.labels || {});
                    composer.autosize();
                });
                line.appendChild(use);
            }

            var edit = h.button('btn btn-sm btn-text theme-secondary', '', 'bi-pencil', t('edit'));
            edit.addEventListener('click', function () { form(item); });
            line.appendChild(edit);

            var remove = h.button('btn btn-sm btn-text theme-danger', '', 'bi-trash', t('delete'));
            remove.addEventListener('click', function () {
                h.ask(t('reply_delete_ask', ';' + item.shortcut), t('delete'), true).then(function (yes) {
                    if (!yes) {
                        return;
                    }

                    h.api('ws_saved_reply_delete', { id: item.id }).then(function () {
                        forget();
                        h.toast(t('reply_deleted'));
                        list();
                    }).catch(h.fail);
                });
            });
            line.appendChild(remove);

            return line;
        }

        function form(item) {
            h.clear(body);

            var shortcut = el('input', 'form-control');
            shortcut.type = 'text';
            shortcut.maxLength = 33;
            shortcut.value = item ? ';' + item.shortcut : ';';
            shortcut.setAttribute('autocomplete', 'off');
            body.appendChild(h.formRow(t('reply_shortcut'), shortcut, t('reply_shortcut_help')));

            var title = el('input', 'form-control');
            title.type = 'text';
            title.maxLength = 100;
            title.value = item ? item.title : '';
            body.appendChild(h.formRow(t('title'), title));

            // The text takes @, # and the lists like the writing box does.
            var wrap = el('div', 'ws-note-compose');
            var field = h.richField(wrap, { placeholder: t('reply_body') });
            var area = null;

            if (field) {
                field.caretPicker = true;
                wrap.appendChild(field.input);
                wrap.appendChild(field.tools(true));
                field.set(item ? item.body : '', item ? item.labels : {});
            } else {
                area = el('textarea', 'form-control form-control-sm');
                area.rows = 6;
                area.value = item ? item.body : '';
                wrap.appendChild(area);
            }

            var textRow = el('div', 'mb-5');
            textRow.appendChild(el('div', 'form-label', t('reply_body')));
            textRow.appendChild(wrap);
            textRow.appendChild(el('div', 'form-text', t('reply_body_help')));
            body.appendChild(textRow);

            var error = el('div', 'small fg-emphasis-danger d-none');
            error.setAttribute('role', 'alert');
            body.appendChild(error);

            h.clear(footer).classList.remove('d-none');

            var back = h.button('btn btn-sm btn-text theme-secondary', t('cancel'));
            back.addEventListener('click', list);
            footer.appendChild(back);

            var save = h.button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('save'));
            footer.appendChild(save);

            save.addEventListener('click', function () {
                save.disabled = true;
                error.classList.add('d-none');

                h.api('ws_saved_reply_save', {
                    id: item ? item.id : 0,
                    shortcut: shortcut.value,
                    title: title.value,
                    body: field ? field.value() : area.value
                }).then(function () {
                    save.disabled = false;
                    forget();
                    h.toast(t('saved'));
                    list();
                }).catch(function (problem) {
                    save.disabled = false;
                    error.textContent = (problem && problem.message) ? problem.message : String(problem || '');
                    error.classList.remove('d-none');
                });
            });

            setTimeout(function () { shortcut.focus(); }, 50);
        }

        list();
        h.showOffcanvas(node);
    }

    window.PGWsReplies = { at: at, match: match, preview: preview, cached: cached, forget: forget, drawer: drawer };
}());
