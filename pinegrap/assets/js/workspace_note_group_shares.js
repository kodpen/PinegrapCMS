/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - shared note groups on the screen: the group's Share dialog
 * (colleagues to read or to add their own notes, or a channel; who has it,
 * each share taken back), the groups shared with the person drawn as folders
 * under Shared with me (owner, count, Leave the group), the card a channel
 * shows for a group shared in it (the group's notes, each read there), and
 * workspace_notes.php?group=N opening a group.
 *
 * assets/js/workspace.js hands over its helpers and lists through the same
 * context it gives workspace_note_groups.js (startNotes()), and draws a
 * channel's group card through card(). The server keeps the shares
 * (includes/workspace/note_group_shares.php); every text comes from the
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

    // Where the browser keeps the ids of the closed shared folders.
    var CLOSED_KEY = 'pg_ws_note_groups_shared_closed';

    function closedRead() {
        try {
            var value = JSON.parse(window.localStorage.getItem(CLOSED_KEY) || '[]');

            return Array.isArray(value) ? value.map(Number) : [];
        } catch (error) {
            return [];
        }
    }

    function setClosed(groupId, closed) {
        var ids = closedRead().filter(function (id) { return id !== Number(groupId); });

        if (closed) {
            ids.push(Number(groupId));
        }

        try {
            window.localStorage.setItem(CLOSED_KEY, JSON.stringify(ids));
        } catch (error) {
            // A private window or blocked storage: folders open each time.
        }
    }

    // The notes drawn in a shared folder, in the group's order.
    function folderNotes(notes, groupId) {
        return (notes || []).filter(function (note) {
            return Number(note.group_id || 0) === Number(groupId);
        }).sort(function (a, b) {
            return (Number(a.group_sort || 0) - Number(b.group_sort || 0)) || (Number(a.id) - Number(b.id));
        });
    }

    function dot(kit, group) {
        var node = kit.el('span', 'ws-nb-group-dot' + (group.hex ? '' : ' ws-nb-group-dot-none'));

        if (group.hex) {
            node.style.background = group.hex;
        }

        node.setAttribute('aria-hidden', 'true');

        return node;
    }

    // ── the Share dialog ──

    function sharesList(ctx, group, shares, done) {
        var kit = ctx.kit;
        var box = kit.el('div', 'ws-nb-shares');

        box.appendChild(kit.el('div', 'form-label mb-1', kit.t('notes_shares')));

        if (!shares.length) {
            box.appendChild(kit.el('div', 'small fg-secondary', kit.t('notes_group_only_you')));
        }

        shares.forEach(function (share) {
            var row = kit.el('div', 'ws-nb-share-row');

            row.appendChild(kit.icon(share.channel_id ? 'bi-hash' : 'bi-person'));
            row.appendChild(kit.el('span', 'ws-grow text-truncate', share.name || '?'));
            row.appendChild(kit.el('span', 'small fg-secondary', share.channel_id ? kit.t('notes_can_view') : kit.t(share.can_edit ? 'notes_group_can_add' : 'notes_can_view')));

            var remove = kit.button('btn btn-sm btn-text theme-secondary ws-tool-danger', '', 'bi-x-lg', kit.t('notes_unshare'));
            remove.addEventListener('click', function () {
                remove.disabled = true;
                kit.api('ws_note_group_unshare', { group_id: group.id, share_id: share.id }).then(function () {
                    row.remove();
                    done();
                }).catch(function (error) {
                    remove.disabled = false;
                    kit.fail(error);
                });
            });
            row.appendChild(remove);
            box.appendChild(row);
        });

        return box;
    }

    // The owner's Share dialog: people (read, or add their own notes) or a
    // channel, and who has the group now.
    function share(ctx, group) {
        var kit = ctx.kit;

        if (group.locked) {
            kit.toast(kit.t('notes_group_share_locked'));
            return;
        }

        kit.api('ws_note_group_shares', { group_id: group.id }).then(function (data) {
            var details = kit.el('div');
            var others = (ctx.people || []).filter(function (id) { return id !== kit.cfg.me; });
            var picker = kit.peoplePicker([], others);
            var right = kit.select([[0, kit.t('notes_can_view')], [1, kit.t('notes_group_can_add')]], 1);
            var channels = (ctx.channels || []).filter(function (channel) {
                return !channel.archived && (channel.joined || channel.kind === 'public');
            });
            var channel = kit.select([['0', kit.t('notes_group_no_channel')]].concat(channels.map(function (one) {
                return [String(one.id), (one.kind === 'private' ? '🔒 ' : '#') + one.name];
            })), '0');

            right.classList.add('w-auto', 'mt-3');
            right.setAttribute('aria-label', kit.t('notes_share_right'));
            channel.classList.add('mt-2');
            channel.setAttribute('aria-label', kit.t('notes_group_channel'));
            details.appendChild(picker);
            details.appendChild(right);
            details.appendChild(kit.el('div', 'form-text', kit.t('notes_group_share_help')));
            details.appendChild(kit.el('div', 'form-label small mt-4 mb-0', kit.t('notes_group_channel')));
            details.appendChild(channel);
            details.appendChild(sharesList(ctx, group, data.shares || [], function () { ctx.reload(); }));

            return kit.ask(kit.t('notes_group_share_title', group.name), kit.t('notes_share'), false, details).then(function (yes) {
                var sends = [];

                if (!yes) {
                    return;
                }

                if (picker.value().length) {
                    sends.push(kit.api('ws_note_group_share', { group_id: group.id, user_ids: picker.value(), can_edit: right.value === '1' }));
                }

                if (Number(channel.value)) {
                    sends.push(kit.api('ws_note_group_share', { group_id: group.id, channel_id: Number(channel.value) }));
                }

                if (!sends.length) {
                    return;
                }

                return Promise.all(sends).then(function () {
                    kit.toast(kit.t('notes_group_shared_ok'), 'success');
                    ctx.reload();
                });
            });
        }).catch(kit.fail);
    }

    // Share the group for an own group's menu (or the tab row).
    function menuItems(ctx, group) {
        if (!group || group.owner || group.reach) {
            return [];
        }

        return [{ icon: 'bi-people', label: ctx.kit.t('notes_group_share'), action: function () { share(ctx, group); } }];
    }

    // ── Shared with me ──

    function leave(ctx, group) {
        var kit = ctx.kit;

        kit.ask(kit.t('notes_group_leave_ask', group.name), kit.t('notes_group_leave'), true).then(function (yes) {
            if (!yes) {
                return;
            }

            kit.api('ws_note_group_unshare', { group_id: group.id, share_id: 0 }).then(function () {
                setClosed(group.id, false);
                ctx.reload();
            }).catch(kit.fail);
        });
    }

    function sharedMenu(ctx, group) {
        return [
            { header: group.name },
            { icon: 'bi-box-arrow-left', label: ctx.kit.t('notes_group_leave'), danger: true, action: function () { leave(ctx, group); } }
        ];
    }

    function folder(ctx, group, notes) {
        var kit = ctx.kit;
        var open = ctx.search ? true : (closedRead().indexOf(Number(group.id)) === -1);
        var box = kit.el('div', 'ws-nb-group ws-nb-group-shared' + (open ? ' open' : ''));
        var head = kit.el('div', 'ws-nb-group-head');
        var toggle = kit.el('button', 'ws-nb-group-toggle');
        var body = kit.el('div', 'ws-nb-group-body');
        var label = kit.el('span', 'ws-nb-group-name');

        box.dataset.groupId = String(group.id);
        body.id = 'ws-nb-shared-group-' + group.id;
        toggle.type = 'button';
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-controls', body.id);
        toggle.appendChild(kit.icon('bi-chevron-right', 'ws-nb-group-chevron'));
        toggle.appendChild(dot(kit, group));
        label.appendChild(document.createTextNode(group.name));

        if (group.owner) {
            label.appendChild(kit.el('span', 'ws-nb-group-owner', kit.t('notes_owner', group.owner.name)));
        }

        toggle.appendChild(label);
        toggle.appendChild(kit.el('span', 'ws-nb-count', String(ctx.search ? notes.length : group.count)));

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
            ctx.ctxMenu(rect.left, rect.bottom + 4, sharedMenu(ctx, group));
        });
        head.appendChild(more);
        kit.onContext(head, function () { return sharedMenu(ctx, group); });

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

    // The Shared with me section: the groups shared with the person as
    // folders, then the notes shared one by one. ctx: as for
    // workspace_note_groups.js, with sharedGroups, and notes holding every
    // listed note; loose: the shared notes in no folder of the person.
    function section(ctx, loose, title, emptyText) {
        var kit = ctx.kit;
        var box = kit.el('div', 'ws-nb-section ws-nb-section-groups');
        var head = kit.el('div', 'ws-nb-section-title');
        var groups = ctx.sharedGroups || [];
        var known = {};
        var drawn = 0;

        groups.forEach(function (group) { known[group.id] = true; });

        var single = loose.filter(function (note) { return !known[Number(note.group_id || 0)]; });

        head.appendChild(kit.el('span', '', title));
        box.appendChild(head);

        groups.forEach(function (group) {
            var notes = folderNotes(ctx.notes, group.id);

            if (ctx.search && !notes.length) {
                return;
            }

            drawn++;
            box.appendChild(folder(ctx, group, notes));
        });

        if (single.length) {
            head.appendChild(kit.el('span', 'ws-nb-count', String(single.length)));
        }

        if (!single.length && !drawn) {
            box.appendChild(kit.el('div', 'ws-nb-section-empty', emptyText));
        }

        single.forEach(function (note) {
            box.appendChild(ctx.drawItem(note));
        });

        return box;
    }

    // workspace_notes.php?group=N: the group's folder opened and brought into
    // view, and its first note opened; a group not among the person's notes
    // says so.
    function openGroup(ctx, groupId, openNote) {
        var kit = ctx.kit;
        var own = (ctx.groups || []).some(function (group) { return Number(group.id) === Number(groupId); });
        var shared = (ctx.sharedGroups || []).some(function (group) { return Number(group.id) === Number(groupId); });

        if (!own && !shared) {
            kit.toast(kit.t('notes_group_not_listed'));
            return;
        }

        var first = folderNotes(ctx.notes, groupId)[0];

        if (shared) {
            setClosed(groupId, false);
        }

        if (first) {
            openNote(first.id);
        }
    }

    // ── a channel's card ──

    // The card of a group shared in a channel: its name and owner, then its
    // notes, each read where it was shared (view(noteId)); the group itself
    // in My Notes for whoever has it there.
    function card(kit, data, view) {
        var box = kit.el('div', 'ws-nb-msg-card ws-nb-group-card');
        var top = kit.el('div', 'ws-nb-msg-card-head');

        top.appendChild(kit.icon('bi-folder2'));
        top.appendChild(kit.el('span', 'ws-nb-msg-card-kind', kit.t('notes_group_card')));
        top.appendChild(dot(kit, data));
        top.appendChild(kit.el('b', 'ws-grow text-truncate', data.name));
        box.appendChild(top);

        var list = kit.el('ul', 'ws-nb-group-card-notes list-unstyled mb-0');

        if (!(data.notes || []).length) {
            list.appendChild(kit.el('li', 'small fg-secondary', kit.t('notes_group_empty')));
        }

        (data.notes || []).forEach(function (note) {
            var item = kit.el('li');
            var link = kit.el('button', 'ws-nb-group-card-note');

            link.type = 'button';
            link.appendChild(kit.icon('bi-journal-text'));
            link.appendChild(kit.el('span', 'ws-grow text-truncate', note.name));
            link.appendChild(kit.el('span', 'small fg-secondary', note.updated));
            link.addEventListener('click', function (event) {
                event.stopPropagation();
                view(note.id);
            });
            item.appendChild(link);
            list.appendChild(item);
        });

        box.appendChild(list);

        var foot = kit.el('div', 'ws-nb-msg-card-foot');

        if (data.owner) {
            foot.appendChild(kit.el('span', '', kit.t('notes_owner', data.owner.name)));
        }

        if (data.url) {
            var open = kit.el('a', 'btn btn-sm btn-outline theme-secondary rounded-pill py-0 px-3 ms-auto', kit.t('notes_group_open'));
            open.href = data.url;
            open.addEventListener('click', function (event) { event.stopPropagation(); });
            foot.appendChild(open);
        }

        box.appendChild(foot);

        return box;
    }

    window.PGWsNoteGroupShares = {
        section: section,
        share: share,
        menuItems: menuItems,
        openGroup: openGroup,
        card: card,
        folderNotes: folderNotes
    };
}());
