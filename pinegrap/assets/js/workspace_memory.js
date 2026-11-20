/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's memory on the channel screen: its entries (a
 * title, a project, a text, tags, pinned, an expiry, who wrote and changed
 * it) as rows under folding project headings, in an agent channel after the
 * general memory the agent channels share, and the form that adds, changes,
 * moves and deletes them. A channel with an agent shows it
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
     * Draws a memory into a node: in an agent channel the general memory and
     * the channel's own as two parts, each record a row under its project's
     * heading, which folds.
     *
     * @param {HTMLElement} pane
     * @param {object}      channel the channel as the screen holds it (id),
     *                              or {general: true} for the general memory
     * @param {object}      [fill]  opens New entry with these (title, text),
     *                              as /memory without a text asks
     */
    function mount(pane, channel, fill) {
        var h = window.PGWsKit;

        if (!h || !pane || !channel) {
            return;
        }

        var t = h.t;
        var el = h.el;
        var part = h.form;
        var root = el('div', 'ws-memory');
        // The projects already named, offered when a record is filed.
        var projects = [];
        // Which headings the reader folded, kept while the tab is open.
        var folded = {};

        pane.appendChild(root);

        // Where a list or a write goes: the general memory, or the channel.
        function target(general) {
            return general ? { general: 1 } : { channel_id: channel.id };
        }

        function iconButton(iconName, label, className) {
            return h.button('pg-icon-button pg-button-sm' + (className ? ' ' + className : ''), '', iconName, label);
        }

        function rowNode(entry, info) {
            var row = el('div', 'ws-memory-row' + (entry.expired ? ' ws-memory-expired' : ''));
            var pin = el('span', 'ws-memory-pin');

            if (entry.pinned) {
                pin.appendChild(h.icon('bi-pin-angle-fill', 'fg-emphasis-primary'));
                pin.title = t('mem_pin_mark');
                pin.setAttribute('aria-label', t('mem_pin_mark'));
            }

            row.appendChild(pin);

            var main = el('div', 'ws-memory-main');
            var name = el('div', 'ws-memory-name', entry.title);

            if (entry.expired) {
                name.appendChild(el('span', 'pg-pill ms-2', t('mem_expired')));
            }

            main.appendChild(name);

            if (entry.first_line) {
                var line = el('div', 'ws-memory-line pg-text-meta', entry.first_line);
                line.title = entry.text || '';
                main.appendChild(line);
            }

            row.appendChild(main);

            var tags = el('div', 'ws-memory-tags');

            entry.tags.forEach(function (tag) {
                tags.appendChild(el('span', 'pg-pill', '#' + tag));
            });

            row.appendChild(tags);

            var meta = [t('mem_by', entry.author.name || '?')];

            if ((entry.updated_by.kind !== entry.author.kind) || (entry.updated_by.id !== entry.author.id)) {
                meta.push(t('mem_changed_by', entry.updated_by.name || '?'));
            }

            if (entry.expires_at && !entry.expired) {
                meta.push(t('mem_until', dayShown(entry.expires_at)));
            }

            var when = el('div', 'ws-memory-date pg-text-meta', dayShown(entry.updated_at));
            when.title = t('mem_changed_on', dayShown(entry.updated_at)) + ' · ' + meta.join(' · ');
            row.appendChild(when);

            var actions = el('div', 'ws-memory-actions');

            if (info.can_move && !entry.general) {
                var move = iconButton('bi-box-arrow-up-right', t('mem_move'));
                move.addEventListener('click', function () {
                    h.ask(t('mem_move_ask'), t('mem_move')).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        h.api('ws_memory_move', { id: entry.id }).then(function () {
                            h.toast(t('mem_moved'));
                            list();
                        }).catch(h.fail);
                    });
                });
                actions.appendChild(move);
            }

            if (info.can_post) {
                var edit = iconButton('bi-pencil', t('mem_edit'));
                edit.addEventListener('click', function () { form(entry, null, entry.general); });
                actions.appendChild(edit);

                var remove = iconButton('bi-trash', t('mem_delete'), 'ws-memory-delete');
                remove.addEventListener('click', function () {
                    h.ask(t('mem_delete_ask'), t('mem_delete'), true).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        h.api('ws_memory_delete', { id: entry.id }).then(list).catch(h.fail);
                    });
                });
                actions.appendChild(remove);
            }

            row.appendChild(actions);

            return row;
        }

        // A project's heading, which folds its rows; open unless folded.
        function groupNode(group, info) {
            var key = info.key + ':' + group.project.toLowerCase();
            var box = el('details', 'ws-memory-project');
            var head = el('summary', 'ws-memory-project-head');

            box.open = !folded[key];
            head.appendChild(h.icon('bi-chevron-right', 'ws-memory-chevron'));
            head.appendChild(el('span', 'ws-memory-project-name', group.project || t('mem_no_project')));
            head.appendChild(el('span', 'pg-pill', String(group.entries.length)));
            box.appendChild(head);
            box.addEventListener('toggle', function () {
                folded[key] = !box.open;
            });

            var rows = el('div', 'ws-memory-rows');

            group.entries.forEach(function (entry) {
                rows.appendChild(rowNode(entry, info));
            });

            box.appendChild(rows);

            return box;
        }

        function partNode(info, alone) {
            var general = info.key === 'general';
            var section = el('section', 'ws-memory-part');
            var head = el('div', 'ws-memory-part-head');
            var words = el('div', 'min-w-0');

            words.appendChild(el('h3', 'ws-memory-part-title pg-title-status', general ? t('mem_general') : t('mem_channel')));
            words.appendChild(el('p', 'pg-text-meta mb-0', general ? t('mem_general_help') : (alone ? t('mem_help') : '')));
            head.appendChild(words);

            if (info.can_post) {
                var add = h.button(alone ? 'pg-button pg-button-fill pg-button-sm' : 'pg-button pg-button-quiet pg-button-sm', t('mem_add'), 'bi-plus-lg');
                add.addEventListener('click', function () { form(null, null, general); });
                head.appendChild(add);
            }

            section.appendChild(head);

            if (!info.groups.length) {
                var empty = el('div', 'ws-empty');
                empty.appendChild(h.icon('bi-bookmark'));
                empty.appendChild(document.createTextNode(general ? t('mem_general_empty') : t('mem_empty')));
                section.appendChild(empty);
                return section;
            }

            info.groups.forEach(function (group) {
                section.appendChild(groupNode(group, info));
            });

            return section;
        }

        function list() {
            h.clear(root);

            h.api('ws_memory_list', target(!!channel.general)).then(function (data) {
                var parts = data.parts || [];

                h.clear(root);
                projects = [];
                parts.forEach(function (info) {
                    info.groups.forEach(function (group) {
                        if (group.project && projects.indexOf(group.project) < 0) {
                            projects.push(group.project);
                        }
                    });
                });

                // With two parts the channel's hint opens the tab once.
                if (parts.length > 1) {
                    root.appendChild(el('p', 'pg-text-meta mb-0', t('mem_help')));
                }

                parts.forEach(function (info) {
                    root.appendChild(partNode(info, parts.length === 1));
                });
            }).catch(h.fail);
        }

        function form(entry, given, general) {
            h.clear(root);
            given = given || {};

            root.appendChild(el('h3', 'ws-memory-part-title pg-title-status', general ? t('mem_general') : t('mem_channel')));

            var title = part.input('text', entry ? entry.title : (given.title || ''));
            title.maxLength = 150;
            root.appendChild(part.field(t('mem_title'), title));

            var project = part.input('text', entry ? entry.project : '');
            var known = el('datalist');
            known.id = 'ws-mem-projects-' + Math.random().toString(36).slice(2);
            projects.forEach(function (name) {
                var option = el('option');
                option.value = name;
                known.appendChild(option);
            });
            project.maxLength = 80;
            project.setAttribute('list', known.id);
            root.appendChild(part.field(t('mem_project'), project, t('mem_project_help')));
            root.appendChild(known);

            var text = part.textarea(entry ? entry.text : (given.text || ''), 6);
            text.maxLength = 4000;
            root.appendChild(part.field(t('mem_text'), text));

            var tags = part.input('text', entry ? entry.tags.join(', ') : '');
            tags.maxLength = 400;
            root.appendChild(part.field(t('mem_tags'), tags, t('mem_tags_help')));

            var expires = part.input('date', (entry && entry.expires_at) ? day(entry.expires_at) : '');
            root.appendChild(part.field(t('mem_expires'), expires, t('mem_expires_help')));

            var pinned = part.switchRow(t('mem_pinned'), !!(entry && entry.pinned));
            root.appendChild(pinned.row);

            var error = part.error('');
            error.setAttribute('role', 'alert');
            root.appendChild(error);

            var actions = el('div', 'd-flex gap-3 justify-content-end');
            var back = h.button('pg-button pg-button-ghost pg-button-sm', t('mem_cancel'));
            back.addEventListener('click', list);
            actions.appendChild(back);

            var save = part.save(t('mem_save'));
            actions.appendChild(save);
            root.appendChild(actions);

            save.addEventListener('click', function () {
                var fields = target(general);

                save.disabled = true;
                error.classList.add('d-none');

                fields.id = entry ? entry.id : 0;
                fields.title = title.value;
                fields.project = project.value;
                fields.text = text.value;
                fields.tags = tags.value;
                fields.expires = expires.value;
                fields.pinned = pinned.input.checked ? 1 : 0;

                h.api('ws_memory_save', fields).then(function () {
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

        if (fill) {
            form(null, fill, false);
        } else {
            list();
        }
    }

    window.PGWsMemory = { mount: mount };
}());
