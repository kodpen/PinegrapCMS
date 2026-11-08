/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - note groups on the My Notes screen: the person's groups drawn
 * as folders in the My notes list (colour dot, name, count, chevron; a lock
 * when locked), each holding its notes - pinned ones first, then the group's
 * order - and the notes in no group under them as before. A folder opens and
 * closes with a click; which ones are closed is kept in the browser. New
 * group, the group's menu (change its name and colour, delete it) and Move
 * to group in a note's menu.
 *
 * assets/js/workspace.js asks for the list and the menu items through
 * window.PGWsNoteGroups and hands over its helpers and its own note row
 * (startNotes()). The groups are the person's own; the server reads and
 * writes only theirs (includes/workspace/note_groups.php). Every text comes
 * from the #ws-config block.
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

    // Where the browser keeps the ids of the closed folders.
    var CLOSED_KEY = 'pg_ws_note_groups_closed';

    function closedRead() {
        try {
            var value = JSON.parse(window.localStorage.getItem(CLOSED_KEY) || '[]');

            return Array.isArray(value) ? value.map(Number) : [];
        } catch (error) {
            return [];
        }
    }

    function closedWrite(ids) {
        try {
            window.localStorage.setItem(CLOSED_KEY, JSON.stringify(ids));
        } catch (error) {
            // A private window or blocked storage: folders open each time.
        }
    }

    function isClosed(groupId) {
        return closedRead().indexOf(Number(groupId)) !== -1;
    }

    function setClosed(groupId, closed) {
        var ids = closedRead().filter(function (id) { return id !== Number(groupId); });

        if (closed) {
            ids.push(Number(groupId));
        }

        closedWrite(ids);
    }

    // The notes of a group, as a folder lists them: pinned first, then the
    // group's own order.
    function groupNotes(notes, groupId) {
        return notes.filter(function (note) {
            return Number(note.group_id || 0) === Number(groupId);
        }).sort(function (a, b) {
            if (!!a.pinned !== !!b.pinned) {
                return a.pinned ? -1 : 1;
            }

            return (Number(a.group_sort || 0) - Number(b.group_sort || 0)) || (Number(a.id) - Number(b.id));
        });
    }

    // The name and colour fields of the New group and Change the group
    // dialogs; value() answers { name, color }.
    function groupFields(ctx, group) {
        var kit = ctx.kit;
        var box = kit.el('div', 'ws-nb-group-form');
        var label = kit.el('label', 'form-label small', kit.t('notes_group_name'));
        var input = kit.el('input', 'form-control form-control-sm mb-4');
        var picker = ctx.colorPicker ? ctx.colorPicker(group ? group.color : 0) : null;

        input.type = 'text';
        input.maxLength = 100;
        input.value = group ? group.name : '';
        input.id = 'ws-nb-group-name';
        label.htmlFor = input.id;

        box.appendChild(label);
        box.appendChild(input);

        if (picker && (kit.cfg.palette || []).length) {
            box.appendChild(kit.el('div', 'form-label small', kit.t('color')));
            box.appendChild(picker);
        }

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();

                var ok = document.querySelector('#ws-ask [data-ws-ask-ok]');

                if (ok) {
                    ok.click();
                }
            }
        });

        window.setTimeout(function () { input.focus(); }, 60);

        box.value = function () {
            return { name: input.value, color: (picker && picker.value) ? picker.value() : 0 };
        };

        return box;
    }

    function editGroup(ctx, group) {
        var kit = ctx.kit;
        var fields = groupFields(ctx, group);

        kit.ask(kit.t(group ? 'notes_group_edit' : 'notes_group_new'), kit.t('save'), false, fields).then(function (yes) {
            if (!yes) {
                return;
            }

            var value = fields.value();
            var sent = { name: value.name, color: value.color };

            if (group) {
                sent.group_id = group.id;
            }

            kit.api('ws_note_group_save', sent).then(function (data) {
                ctx.setGroups(data.groups || []);
            }).catch(kit.fail);
        });
    }

    function deleteGroup(ctx, group) {
        var kit = ctx.kit;

        kit.ask(kit.t('notes_group_delete_ask', group.name), kit.t('delete'), true).then(function (yes) {
            if (!yes) {
                return;
            }

            kit.api('ws_note_group_delete', { group_id: group.id }).then(function () {
                setClosed(group.id, false);
                ctx.reload();
            }).catch(kit.fail);
        });
    }

    function groupMenu(ctx, group) {
        var kit = ctx.kit;

        return [
            { header: group.name },
            { icon: 'bi-pencil', label: kit.t('notes_group_edit'), action: function () { editGroup(ctx, group); } },
            '-',
            { icon: 'bi-trash', label: kit.t('notes_group_delete'), danger: true, action: function () { deleteGroup(ctx, group); } }
        ];
    }

    function folder(ctx, group, notes) {
        var kit = ctx.kit;
        // A search opens every folder holding what was found.
        var open = ctx.search ? true : !isClosed(group.id);
        var box = kit.el('div', 'ws-nb-group' + (open ? ' open' : ''));
        var head = kit.el('div', 'ws-nb-group-head');
        var toggle = kit.el('button', 'ws-nb-group-toggle');
        var body = kit.el('div', 'ws-nb-group-body');
        var dot = kit.el('span', 'ws-nb-group-dot' + (group.hex ? '' : ' ws-nb-group-dot-none'));

        body.id = 'ws-nb-group-' + group.id;
        toggle.type = 'button';
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-controls', body.id);
        toggle.appendChild(kit.icon('bi-chevron-right', 'ws-nb-group-chevron'));

        if (group.hex) {
            dot.style.background = group.hex;
        }

        dot.setAttribute('aria-hidden', 'true');
        toggle.appendChild(dot);
        toggle.appendChild(kit.el('span', 'ws-nb-group-name', group.name));

        if (group.locked) {
            var lock = kit.icon('bi-lock-fill', 'ws-nb-group-lock');
            lock.title = kit.t('notes_group_locked');
            lock.setAttribute('aria-label', kit.t('notes_group_locked'));
            toggle.appendChild(lock);
        } else {
            toggle.appendChild(kit.el('span', 'ws-nb-count', String(ctx.search ? notes.length : group.count)));
        }

        toggle.addEventListener('click', function () {
            open = !box.classList.contains('open');
            box.classList.toggle('open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            setClosed(group.id, !open);
        });

        head.appendChild(toggle);

        var more = kit.button('ws-nb-item-more', '', 'bi-three-dots', kit.t('more'));
        more.addEventListener('click', function (event) {
            var rect = more.getBoundingClientRect();

            event.stopPropagation();
            ctx.ctxMenu(rect.left, rect.bottom + 4, groupMenu(ctx, group));
        });
        head.appendChild(more);
        kit.onContext(head, function () { return groupMenu(ctx, group); });

        if (!notes.length) {
            body.appendChild(kit.el('div', 'ws-nb-section-empty', kit.t('notes_group_empty')));
        }

        notes.forEach(function (note) {
            body.appendChild(ctx.drawItem(note));
        });

        box.appendChild(head);
        box.appendChild(body);

        return box;
    }

    // The My notes section with the person's groups as folders, then their
    // own notes in no group. ctx: kit (window.PGWsKit), groups, notes (every
    // note listed: own and shared, each with group_id), mine (own notes),
    // search, title, emptyText, drawItem(note), ctxMenu, colorPicker,
    // setGroups(groups), reload().
    function mine(ctx) {
        var kit = ctx.kit;
        var section = kit.el('div', 'ws-nb-section ws-nb-section-groups');
        var head = kit.el('div', 'ws-nb-section-title');
        var groups = ctx.groups || [];
        var known = {};

        groups.forEach(function (group) { known[group.id] = true; });

        // A note whose group the list does not hold is drawn in no group.
        var loose = ctx.mine.filter(function (note) { return !known[Number(note.group_id || 0)]; });

        head.appendChild(kit.el('span', '', ctx.title));

        if (ctx.mine.length) {
            head.appendChild(kit.el('span', 'ws-nb-count', String(ctx.mine.length)));
        }

        var add = kit.button('btn btn-sm btn-text theme-secondary ms-auto ws-nb-group-add', kit.t('notes_group_new'), 'bi-folder-plus');
        add.addEventListener('click', function () { editGroup(ctx, null); });
        head.appendChild(add);
        section.appendChild(head);

        groups.forEach(function (group) {
            var notes = groupNotes(ctx.notes, group.id);

            // A search shows only the folders that hold what it found.
            if (ctx.search && !notes.length) {
                return;
            }

            section.appendChild(folder(ctx, group, notes));
        });

        if (!loose.length && (ctx.search || !groups.length)) {
            section.appendChild(kit.el('div', 'ws-nb-section-empty', ctx.emptyText));
        }

        loose.forEach(function (note) {
            section.appendChild(ctx.drawItem(note));
        });

        return section;
    }

    // Shared notes the person keeps in a group of theirs are drawn in that
    // folder, so the Shared with me list leaves them out.
    function sharedLoose(ctx, shared) {
        var known = {};

        (ctx.groups || []).forEach(function (group) { known[group.id] = true; });

        return shared.filter(function (note) { return !known[Number(note.group_id || 0)]; });
    }

    function moveNote(ctx, note) {
        var kit = ctx.kit;
        var options = [['0', kit.t('notes_group_none')]].concat((ctx.groups || []).map(function (group) {
            return [String(group.id), group.name];
        }));
        var select = kit.select(options, String(Number(note.group_id || 0)));

        kit.ask(kit.t('notes_group_move_to', note.name), kit.t('notes_group_move'), false, select).then(function (yes) {
            if (!yes || (Number(select.value) === Number(note.group_id || 0))) {
                return;
            }

            kit.api('ws_note_place', { note_id: note.id, group_id: Number(select.value), position: -1 }).then(function () {
                if (Number(select.value)) {
                    setClosed(Number(select.value), false);
                }

                ctx.reload();
            }).catch(kit.fail);
        });
    }

    // Move to group for a note's menu; nothing until the person has a group.
    function menuItems(ctx, note) {
        if (!note.id || !(ctx.groups || []).length) {
            return [];
        }

        return [{ icon: 'bi-folder-symlink', label: ctx.kit.t('notes_group_move'), action: function () { moveNote(ctx, note); } }];
    }

    // A new note written while a note of a group is open goes into that
    // group, last.
    function placeNew(ctx, noteId, groupId) {
        if (!noteId || !groupId) {
            return Promise.resolve();
        }

        return ctx.kit.api('ws_note_place', { note_id: noteId, group_id: groupId, position: -1 }).then(function () {
            ctx.reload();
        }).catch(ctx.kit.fail);
    }

    window.PGWsNoteGroups = {
        mine: mine,
        sharedLoose: sharedLoose,
        menuItems: menuItems,
        placeNew: placeNew,
        groupNotes: groupNotes
    };
}());
