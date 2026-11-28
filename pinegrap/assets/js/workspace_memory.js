/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's memory on the channel screen: its entries (a
 * title, a project, a text, tags, pinned, an expiry, who wrote and changed
 * it) as rows under folding project headings, in an agent channel after the
 * general memory the agent channels share, and the form drawer beside them
 * that adds, changes and deletes them; a row moves and deletes too. A
 * channel with an agent shows it as its Memory tab; any other channel opens
 * it from the channel settings.
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
     * @param {object}      [start] opens New entry with these (title, text),
     *                              as /memory without a text asks; not named
     *                              fill, which the list's fill() would hide
     */
    function mount(pane, channel, start) {
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
        // The words searched, kept while the tab is open.
        var query = '';
        // Which answer of ws_memory_list is the newest asked for.
        var asked = 0;

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
            row.setAttribute('data-ws-memory-id', String(entry.id));
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

        function partNode(info, alone, searching) {
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
                empty.appendChild(h.icon(searching ? 'bi-search' : 'bi-bookmark'));
                empty.appendChild(document.createTextNode(searching ? t('mem_search_none') : (general ? t('mem_general_empty') : t('mem_empty'))));
                section.appendChild(empty);
                return section;
            }

            info.groups.forEach(function (group) {
                section.appendChild(groupNode(group, info));
            });

            if (info.more) {
                section.appendChild(el('p', 'ws-memory-more pg-text-meta mb-0', t('mem_search_more')));
            }

            return section;
        }

        // The parts, filled from ws_memory_list with the words searched;
        // an answer overtaken by a newer search is dropped.
        function fill(results, drawn) {
            var fields = target(!!channel.general);
            var mine = ++asked;

            fields.search = query;

            h.api('ws_memory_list', fields).then(function (data) {
                if (mine !== asked) {
                    return;
                }

                var parts = data.parts || [];

                h.clear(results);

                // The projects offered by the form: every one, not the hits'.
                if (!data.search) {
                    projects = [];
                    parts.forEach(function (info) {
                        info.groups.forEach(function (group) {
                            if (group.project && projects.indexOf(group.project) < 0) {
                                projects.push(group.project);
                            }
                        });
                    });
                }

                // With two parts the channel's hint opens the tab once.
                if (parts.length > 1) {
                    results.appendChild(el('p', 'pg-text-meta mb-0', t('mem_help')));
                }

                parts.forEach(function (info) {
                    results.appendChild(partNode(info, parts.length === 1, !!data.search));
                });

                if (drawn) {
                    drawn();
                }
            }).catch(h.fail);
        }

        // The search box over both parts, then the parts; drawn runs once
        // they are on the page.
        function list(drawn) {
            var bar = el('div', 'pg-toolbar ws-pane-toolbar ws-memory-bar');
            var search = el('label', 'pg-input pg-input-sm ws-bar-search');
            var input = el('input', '');
            var results = el('div', 'ws-memory-results');

            h.clear(root);
            search.appendChild(h.icon('bi-search', ''));
            input.type = 'search';
            input.value = query;
            input.placeholder = t('mem_search');
            input.setAttribute('aria-label', t('mem_search'));
            search.appendChild(input);
            bar.appendChild(search);
            root.appendChild(bar);
            root.appendChild(results);

            input.addEventListener('input', h.debounce(function () {
                if (input.value.trim() !== query) {
                    query = input.value.trim();
                    fill(results);
                }
            }, 300));

            fill(results, typeof drawn === 'function' ? drawn : null);
        }

        // The rows' tint once a save has listed them again: the saved row
        // is scrolled into view and its ground lit for a moment.
        function showSaved(id) {
            var row = root.querySelector('.ws-memory-row[data-ws-memory-id="' + id + '"]');

            if (!row) {
                return;
            }

            var box = row.closest('details');

            if (box && !box.open) {
                box.open = true;
            }

            row.scrollIntoView({ block: 'nearest' });
            row.classList.remove('ws-memory-row-saved');
            void row.offsetWidth;
            row.classList.add('ws-memory-row-saved');
        }

        // New entry and Edit: the look's form drawer from the right, so the
        // list, its search words and its folds stay behind it. A refused
        // field is said under that field and the drawer stays open; closing
        // it with changed fields asks first.
        function form(entry, given, general) {
            var old = document.getElementById('ws-memory-form');

            if (old) {
                old.remove();
            }

            given = given || {};

            var node = h.drawer.make('ws-memory-form', entry ? t('mem_edit_title') : t('mem_add'));
            var head = node.querySelector('.drawer-header');
            var heading = node.querySelector('.drawer-title');
            var words = el('div', 'flex-grow-1 min-w-0');
            var body = node.querySelector('.drawer-body');
            var foot = node.querySelector('.drawer-footer');
            var errors = {};
            // Set once the drawer may close without asking: saved, deleted,
            // or the changes given up.
            var leaving = false;

            node.style.width = 'min(46rem, 100vw)';
            heading.id = 'ws-memory-form-title';
            node.setAttribute('aria-labelledby', heading.id);
            head.classList.add('gap-3', 'p-5');
            words.appendChild(heading);
            words.appendChild(el('p', 'pg-text-meta mb-0 mt-1', general ? t('mem_general_kept') : t('mem_channel')));
            head.insertBefore(words, head.firstChild);
            body.classList.add('p-5');
            foot.classList.remove('d-none');

            // A field with the line its refusal is said in.
            function add(label, control, help, key) {
                var field = part.field(label, control, help);
                var error = part.error('');

                error.setAttribute('role', 'alert');
                field.appendChild(error);
                errors[key] = error;
                body.appendChild(field);
            }

            var title = part.input('text', entry ? entry.title : (given.title || ''));
            title.maxLength = 150;
            add(t('mem_title'), title, '', 'title');

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
            add(t('mem_project'), project, t('mem_project_help'), 'project');
            body.appendChild(known);

            var text = part.textarea(entry ? entry.text : (given.text || ''), 8);
            text.maxLength = 4000;
            add(t('mem_text'), text, '', 'text');

            // The text box grows with what is typed, never below its 8 rows.
            function grow() {
                text.style.height = '';

                if (text.scrollHeight > text.clientHeight) {
                    text.style.height = (text.scrollHeight + text.offsetHeight - text.clientHeight) + 'px';
                }
            }

            text.addEventListener('input', grow);

            var tags = part.input('text', entry ? entry.tags.join(', ') : '');
            tags.maxLength = 400;
            add(t('mem_tags'), tags, t('mem_tags_help'), 'tags');

            var expires = part.input('date', (entry && entry.expires_at) ? day(entry.expires_at) : '');
            add(t('mem_expires'), expires, t('mem_expires_help'), 'expires_at');

            var pinned = part.switchRow(t('mem_pinned'), !!(entry && entry.pinned));
            body.appendChild(pinned.row);

            // A refusal no field owns.
            var error = part.error('');
            error.setAttribute('role', 'alert');
            body.appendChild(error);

            function values() {
                return [title.value, project.value, text.value, tags.value, expires.value, pinned.input.checked ? 1 : 0].join('\u0000');
            }

            var start = values();

            function close(then) {
                leaving = true;
                h.drawer.hide(node, then);
            }

            if (entry) {
                var remove = part.remove(t('mem_delete'));
                remove.addEventListener('click', function () {
                    h.ask(t('mem_delete_ask'), t('mem_delete'), true).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        remove.disabled = true;
                        h.api('ws_memory_delete', { id: entry.id }).then(function () {
                            close(list);
                        }).catch(function (problem) {
                            remove.disabled = false;
                            h.fail(problem);
                        });
                    });
                });
                foot.appendChild(remove);
            }

            var back = h.button('pg-button pg-button-quiet pg-button-sm', t('mem_cancel'));
            back.addEventListener('click', function () { h.drawer.hide(node); });
            foot.appendChild(back);

            var save = part.save(t('mem_save'));
            foot.appendChild(save);

            save.addEventListener('click', function () {
                var fields = target(general);

                save.disabled = true;
                error.classList.add('d-none');
                Object.keys(errors).forEach(function (key) {
                    errors[key].classList.add('d-none');
                });

                fields.id = entry ? entry.id : 0;
                fields.title = title.value;
                fields.project = project.value;
                fields.text = text.value;
                fields.tags = tags.value;
                fields.expires = expires.value;
                fields.pinned = pinned.input.checked ? 1 : 0;

                h.api('ws_memory_save', fields).then(function (data) {
                    var id = (data && data.entry) ? data.entry.id : (entry ? entry.id : 0);

                    h.toast(t('mem_saved'));
                    close(function () {
                        list(function () { showSaved(id); });
                    });
                }).catch(function (problem) {
                    var line = (problem && problem.field && errors[problem.field]) ? errors[problem.field] : error;

                    save.disabled = false;
                    line.textContent = (problem && problem.message) ? problem.message : String(problem || '');
                    line.classList.remove('d-none');
                });
            });

            // Esc, the cross or Cancel with changed fields asks first.
            window.pgUI.drawer.on(node, 'hide', function (event) {
                if (leaving || (values() === start)) {
                    return;
                }

                event.preventDefault();
                h.ask(t('mem_unsaved'), t('mem_discard'), true).then(function (yes) {
                    if (yes) {
                        close();
                    }
                });
            });

            window.pgUI.drawer.on(node, 'hidden', function () {
                node.remove();

                // Opened over the General memory drawer, which stays open.
                if (document.querySelector('dialog.drawer[open]')) {
                    document.body.classList.add('ws-drawer-open');
                }
            });

            h.drawer.show(node);
            setTimeout(function () {
                grow();
                title.focus();
            }, 50);
        }

        list();

        if (start) {
            form(null, start, false);
        }
    }

    window.PGWsMemory = { mount: mount };
}());
