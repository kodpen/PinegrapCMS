/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's memory on the channel screen: its entries (a
 * title, a text, tags, pinned, an expiry, who wrote and changed it) and the
 * form that adds, changes and deletes them. A channel with an agent shows it
 * as its Memory tab; any other channel opens it from the channel settings.
 * Agents see the index of the same entries in every brief
 * (includes/workspace/channel_memory.php).
 *
 * assets/js/workspace.js mounts it through window.PGWsMemory.mount() and
 * lends its helpers through window.PGWsKit. Every text comes from the
 * #ws-config block.
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

    // A day as the date field takes it (YYYY-MM-DD), in the reader's time.
    function day(unix) {
        var date = new Date(unix * 1000);
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };

        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function dayShown(unix) {
        return new Date(unix * 1000).toLocaleDateString();
    }

    /**
     * Draws a channel's memory into a node.
     *
     * @param {HTMLElement} pane
     * @param {object}      channel the channel as the screen holds it (id)
     */
    function mount(pane, channel) {
        var h = window.PGWsKit;

        if (!h || !pane || !channel) {
            return;
        }

        var t = h.t;
        var el = h.el;
        var root = el('div', 'ws-memory p-5');
        var canPost = false;

        pane.appendChild(root);

        function formRow(label, field, help) {
            var row = el('div', 'mb-5');
            var id = 'ws-mem-' + Math.random().toString(36).slice(2);
            var name = el('label', 'form-label', label);

            field.id = id;
            name.setAttribute('for', id);
            row.appendChild(name);
            row.appendChild(field);

            if (help) {
                row.appendChild(el('div', 'form-text', help));
            }

            return row;
        }

        function entryNode(entry) {
            var item = el('div', 'list-group-item d-flex align-items-start gap-3' + (entry.expired ? ' opacity-75' : ''));
            var main = el('div', 'flex-grow-1 min-w-0');
            var head = el('div', 'd-flex align-items-center gap-2 flex-wrap');

            if (entry.pinned) {
                var pin = h.icon('bi-pin-angle-fill', 'fg-emphasis-primary');
                pin.setAttribute('title', t('mem_pin_mark'));
                head.appendChild(pin);
            }

            head.appendChild(el('span', 'fw-semibold', entry.title));

            entry.tags.forEach(function (tag) {
                head.appendChild(el('span', 'badge theme-secondary', '#' + tag));
            });

            if (entry.expired) {
                head.appendChild(el('span', 'badge theme-warning', t('mem_expired')));
            }

            main.appendChild(head);

            if (entry.text) {
                var text = el('div', 'small mt-1', entry.text);
                text.style.whiteSpace = 'pre-wrap';
                main.appendChild(text);
            }

            var meta = [t('mem_by', entry.author.name || '?')];

            if ((entry.updated_by.kind !== entry.author.kind) || (entry.updated_by.id !== entry.author.id)) {
                meta.push(t('mem_changed_by', entry.updated_by.name || '?'));
            }

            if (entry.expires_at && !entry.expired) {
                meta.push(t('mem_until', dayShown(entry.expires_at)));
            }

            main.appendChild(el('div', 'small fg-secondary mt-1', meta.join(' · ')));
            item.appendChild(main);

            if (canPost) {
                var edit = h.button('btn btn-sm btn-text theme-secondary', '', 'bi-pencil', t('mem_edit'));
                edit.setAttribute('aria-label', t('mem_edit'));
                edit.addEventListener('click', function () { form(entry); });
                item.appendChild(edit);

                var remove = h.button('btn btn-sm btn-text theme-danger', '', 'bi-trash', t('mem_delete'));
                remove.setAttribute('aria-label', t('mem_delete'));
                remove.addEventListener('click', function () {
                    h.ask(t('mem_delete_ask'), t('mem_delete'), true).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        h.api('ws_memory_delete', { id: entry.id }).then(list).catch(h.fail);
                    });
                });
                item.appendChild(remove);
            }

            return item;
        }

        function list() {
            h.clear(root);
            root.appendChild(el('p', 'small fg-secondary', t('mem_help')));

            var holder = el('div', 'list-group');
            root.appendChild(holder);

            h.api('ws_memory_list', { channel_id: channel.id }).then(function (data) {
                canPost = !!data.can_post;

                if (canPost) {
                    var add = h.button('btn btn-sm btn-solid theme-primary rounded-pill px-5 mb-5', t('mem_add'), 'bi-plus-lg');
                    add.addEventListener('click', function () { form(null); });
                    root.insertBefore(add, holder);
                }

                if (!data.entries || !data.entries.length) {
                    holder.appendChild(el('div', 'small fg-secondary', t('mem_empty')));
                    return;
                }

                data.entries.forEach(function (entry) {
                    holder.appendChild(entryNode(entry));
                });
            }).catch(h.fail);
        }

        function form(entry) {
            h.clear(root);

            var title = el('input', 'form-control');
            title.type = 'text';
            title.maxLength = 150;
            title.value = entry ? entry.title : '';
            root.appendChild(formRow(t('mem_title'), title));

            var text = el('textarea', 'form-control');
            text.rows = 6;
            text.maxLength = 4000;
            text.value = entry ? entry.text : '';
            root.appendChild(formRow(t('mem_text'), text));

            var tags = el('input', 'form-control');
            tags.type = 'text';
            tags.maxLength = 400;
            tags.value = entry ? entry.tags.join(', ') : '';
            root.appendChild(formRow(t('mem_tags'), tags, t('mem_tags_help')));

            var expires = h.dateField(el('input', 'form-control'), 'date');
            expires.value = (entry && entry.expires_at) ? day(entry.expires_at) : '';
            root.appendChild(formRow(t('mem_expires'), expires, t('mem_expires_help')));

            var pinRow = el('div', 'form-field mb-5');
            var pinned = el('input', 'check');
            pinned.type = 'checkbox';
            pinned.id = 'ws-mem-pinned';
            pinned.checked = !!(entry && entry.pinned);
            var pinLabel = el('label', '', t('mem_pinned'));
            pinLabel.setAttribute('for', pinned.id);
            pinRow.appendChild(pinned);
            pinRow.appendChild(pinLabel);
            root.appendChild(pinRow);

            var error = el('div', 'small fg-emphasis-danger d-none mb-5');
            error.setAttribute('role', 'alert');
            root.appendChild(error);

            var actions = el('div', 'd-flex gap-3 justify-content-end');
            var back = h.button('btn btn-sm btn-text theme-secondary', t('mem_cancel'));
            back.addEventListener('click', list);
            actions.appendChild(back);

            var save = h.button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('mem_save'));
            actions.appendChild(save);
            root.appendChild(actions);

            save.addEventListener('click', function () {
                save.disabled = true;
                error.classList.add('d-none');

                h.api('ws_memory_save', {
                    channel_id: channel.id,
                    id: entry ? entry.id : 0,
                    title: title.value,
                    text: text.value,
                    tags: tags.value,
                    expires: expires.value,
                    pinned: pinned.checked ? 1 : 0
                }).then(function () {
                    h.toast(t('mem_saved'));
                    list();
                }).catch(function (problem) {
                    save.disabled = false;
                    error.textContent = (problem && problem.message) ? problem.message : String(problem || '');
                    error.classList.remove('d-none');
                });
            });

            setTimeout(function () { title.focus(); }, 50);
        }

        list();
    }

    window.PGWsMemory = { mount: mount };
}());
