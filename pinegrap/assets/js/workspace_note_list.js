/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the My Notes list's order and its section labels. The bar's
 * "⋯" picks the order (Last changed, Name, Created); it is kept in this
 * browser and applied to the notes the list has loaded, while the server
 * keeps its own order (the newest change first) for what it sends. Each
 * section of the list (My notes, Shared with me) stands under a group label
 * with its count that folds the section; which ones are folded is kept in
 * this browser too. The note last open is kept in this browser, one key per
 * person, and the screen opens on it (firstOpen()); deleting the open note
 * opens the next one (nextOpen()).
 *
 * assets/js/workspace.js (startNotes()) asks for the order and the menu's
 * items through window.PGWsNoteList and hands section() to
 * workspace_note_groups.js and workspace_note_group_shares.js, whose folders
 * keep their own folded lists. The pure parts (order(), firstOpen(),
 * nextOpen()) are tested in
 * tests/workspace_note_list_test.php.
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

    // Where the browser keeps the order picked and the folded sections.
    var ORDER_KEY = 'pg_ws_notes_order';
    var FOLD_KEY = 'pg_ws_notes_folded';
    // The note last open, followed by the person's id.
    var OPEN_KEY = 'pg_ws_notes_open_';

    // The orders the menu offers, each with its text key; the first one is
    // the server's own.
    var ORDERS = [['changed', 'notes_order_changed'], ['name', 'notes_order_name'], ['created', 'notes_order_created']];

    function known(how) {
        return ORDERS.some(function (item) { return item[0] === how; });
    }

    // The notes in the order asked, as a new array. Pinned notes stay first
    // (only own notes are pinned); then the newest change, the name, or the
    // newest note; the higher id breaks a tie, as on the server. locale is
    // the panel's language for comparing names.
    function order(notes, how, locale) {
        var by = known(how) ? how : 'changed';
        var field = (by === 'created') ? 'created_at' : 'updated_at';

        return (notes || []).slice().sort(function (a, b) {
            if (!!a.pinned !== !!b.pinned) {
                return a.pinned ? -1 : 1;
            }

            var diff = (by === 'name')
                ? String(a.name || '').localeCompare(String(b.name || ''), locale || undefined, { numeric: true, sensitivity: 'base' })
                : Number(b[field] || 0) - Number(a[field] || 0);

            return diff || (Number(b.id || 0) - Number(a.id || 0));
        });
    }

    // The notes of both sections as the list shows them: My notes, then
    // Shared with me, each in the order asked.
    function listedInOrder(lists, how, locale) {
        lists = lists || {};

        return order(lists.mine, how, locale).concat(order(lists.shared, how, locale));
    }

    function listedIndex(notes, noteId) {
        for (var at = 0; at < notes.length; at++) {
            if (Number(notes[at].id || 0) === noteId) {
                return at;
            }
        }

        return -1;
    }

    // The note the screen opens on: the one the address names (wanted), else
    // the one last open (kept) while it is still listed, else the first of
    // the list as ordered; 0 when there is none.
    function firstOpen(lists, wanted, kept, how, locale) {
        var notes = listedInOrder(lists, how, locale);

        wanted = Number(wanted || 0);
        kept = Number(kept || 0);

        if (wanted > 0) {
            return wanted;
        }

        if ((kept > 0) && (listedIndex(notes, kept) >= 0)) {
            return kept;
        }

        return notes.length ? Number(notes[0].id || 0) : 0;
    }

    // The note to open once noteId leaves the list (deleted, left): the one
    // after it as ordered, else the one before it; 0 when none is left.
    function nextOpen(lists, noteId, how, locale) {
        var notes = listedInOrder(lists, how, locale);
        var at = listedIndex(notes, Number(noteId || 0));

        if (at < 0) {
            return notes.length ? Number(notes[0].id || 0) : 0;
        }

        notes.splice(at, 1);

        return notes.length ? Number(notes[Math.min(at, notes.length - 1)].id || 0) : 0;
    }

    function openRead(personId) {
        try {
            return parseInt(window.localStorage.getItem(OPEN_KEY + personId) || '0', 10) || 0;
        } catch (error) {
            return 0;
        }
    }

    function openWrite(personId, noteId) {
        try {
            window.localStorage.setItem(OPEN_KEY + personId, String(noteId));
        } catch (error) {
            // Not kept: the screen opens on the first note next time.
        }
    }

    function orderRead() {
        try {
            var how = window.localStorage.getItem(ORDER_KEY);

            return known(how) ? how : ORDERS[0][0];
        } catch (error) {
            return ORDERS[0][0];
        }
    }

    function orderWrite(how) {
        try {
            window.localStorage.setItem(ORDER_KEY, how);
        } catch (error) {
            // Not kept: the list opens in the server's order next time.
        }
    }

    // The order part of the bar's "⋯": its heading, then each order with a
    // tick on the one in use; picking one keeps it and calls changed().
    function menuItems(kit, changed) {
        var now = orderRead();

        return [{ header: kit.t('notes_order') }].concat(ORDERS.map(function (item) {
            return {
                label: kit.t(item[1]),
                checked: now === item[0],
                action: function () {
                    orderWrite(item[0]);
                    changed();
                }
            };
        }));
    }

    function foldedRead() {
        try {
            var kept = JSON.parse(window.localStorage.getItem(FOLD_KEY) || '{}');

            return (kept && (typeof kept === 'object')) ? kept : {};
        } catch (error) {
            return {};
        }
    }

    function foldedWrite(key, shut) {
        var kept = foldedRead();

        if (shut) {
            kept[key] = 1;
        } else {
            delete kept[key];
        }

        try {
            window.localStorage.setItem(FOLD_KEY, JSON.stringify(kept));
        } catch (error) {
            // Not kept: the section opens again next time.
        }
    }

    // A section of the list: its head, a heading holding the group label
    // (a button with the chevron, the name and the count, which folds the
    // body; Enter and Space are the button's own), then the body. A search
    // shows the section open. Answers { box, head, body }: the caller puts
    // its buttons in head and its folders and rows in body.
    function section(kit, key, name, count, search, extraClass) {
        var box = kit.el('div', 'ws-nb-section' + (extraClass ? ' ' + extraClass : ''));
        var head = kit.el('div', 'ws-nb-section-head');
        var heading = kit.el('h2', 'ws-nb-section-title');
        var toggle = kit.el('button', 'pg-group-label ws-nb-fold');
        var body = kit.el('div', 'ws-nb-section-body');
        var shut = !search && !!foldedRead()[key];

        body.id = 'ws-nb-section-' + key;
        toggle.type = 'button';
        toggle.setAttribute('aria-controls', body.id);
        toggle.appendChild(kit.icon('bi-chevron-down', 'ws-nb-fold-chevron'));
        toggle.appendChild(document.createTextNode(name));
        toggle.appendChild(kit.el('span', 'pg-group-label-count', String(count)));

        function set(folded) {
            shut = folded;
            body.hidden = folded;
            toggle.setAttribute('aria-expanded', folded ? 'false' : 'true');
            toggle.title = folded ? kit.t('side_expand') : kit.t('side_collapse');
        }

        toggle.addEventListener('click', function () {
            set(!shut);
            foldedWrite(key, shut);
        });
        set(shut);

        heading.appendChild(toggle);
        head.appendChild(heading);
        box.appendChild(head);
        box.appendChild(body);

        return { box: box, head: head, body: body };
    }

    window.PGWsNoteList = {
        order: order,
        orderNow: orderRead,
        firstOpen: firstOpen,
        nextOpen: nextOpen,
        openRead: openRead,
        openWrite: openWrite,
        menuItems: menuItems,
        section: section
    };
}());
