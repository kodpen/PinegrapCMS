/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - note tabs on the My Notes screen: an open note's group drawn
 * as tabs over the note (the group's colour and name, then one tab per note
 * of the group in the group's order with lock and people marks, the open one
 * selected, and + for a new note in the group). A note in no group has no
 * tabs. A tab opens its note the way a list click does, so the note being
 * written is saved first. Arrow keys, Home and End move between the tabs;
 * Enter or Space opens one; Ctrl+Shift with an arrow moves the focused tab
 * along. A tab dragged along the row changes the group's order through
 * ws_note_place with a position. On a phone the row scrolls sideways.
 *
 * assets/js/workspace.js draws the row through window.PGWsNoteTabs.draw()
 * and hands over its helpers, the open note and the listed notes
 * (startNotes()). The order and the drop position are worked out by the pure
 * helpers in window.PGWsNoteTabs.pure (tests/workspace_note_tabs_js_test.php).
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

    // How many characters of a title a tab shows.
    var TITLE_MAX = 24;

    // The note whose tab takes the focus when the row is drawn next: the
    // one moved with the keyboard or the focused one of a row drawn again.
    var focusAfter = 0;

    // ── pure helpers ──

    // The notes of a group as its tabs show them: the group's own order
    // alone (a pinned note keeps its place, so a tab's index is the position
    // ws_note_place counts), a tie broken by id.
    function order(notes, groupId) {
        groupId = Number(groupId || 0);

        if (!groupId) {
            return [];
        }

        return (notes || []).filter(function (note) {
            return Number(note.group_id || 0) === groupId;
        }).sort(function (a, b) {
            return (Number(a.group_sort || 0) - Number(b.group_sort || 0)) || (Number(a.id) - Number(b.id));
        });
    }

    // The tab an arrow key moves to from index in a row of count tabs, or -1
    // for a key that moves nothing. Right and left wrap round; in a
    // right-to-left page they swap.
    function step(key, index, count, rtl) {
        if (count < 1) {
            return -1;
        }

        var next = rtl ? 'ArrowLeft' : 'ArrowRight';
        var back = rtl ? 'ArrowRight' : 'ArrowLeft';

        if (key === next) {
            return (index + 1) % count;
        }

        if (key === back) {
            return (index - 1 + count) % count;
        }

        if (key === 'Home') {
            return 0;
        }

        if (key === 'End') {
            return count - 1;
        }

        return -1;
    }

    // Where a dragged tab lands: the number of the other tabs whose middle
    // lies before the pointer. centers are the middles of every tab in the
    // row (the dragged one at from included), x the pointer; right to left
    // counts from the right edge. The answer is the position ws_note_place
    // takes (0 the first); from itself means no move.
    function dropIndex(centers, from, x, rtl) {
        var position = 0;

        (centers || []).forEach(function (center, index) {
            if (index !== from && (rtl ? (center > x) : (center < x))) {
                position++;
            }
        });

        return position;
    }

    // A title as a tab shows it: spaces run together, cut with an ellipsis.
    function short(name, max) {
        var chars = Array.from(String(name || '').replace(/\s+/g, ' ').trim());

        if (chars.length <= max) {
            return chars.join('');
        }

        return chars.slice(0, Math.max(1, max - 1)).join('').trim() + '…';
    }

    // ── the row ──

    function shared(note) {
        return note.access !== 'owner' || (note['with'] || []).length > 0 || (note['in'] || []).length > 0;
    }

    function isRtl(node) {
        try {
            return window.getComputedStyle(node).direction === 'rtl';
        } catch (error) {
            return false;
        }
    }

    function mark(kit, name, label) {
        var node = kit.icon(name, 'ws-nb-tab-mark');

        node.title = label;
        node.setAttribute('aria-label', label);
        node.setAttribute('role', 'img');

        return node;
    }

    // The tab row of the open note's group, or null when it has none.
    // ctx: kit (window.PGWsKit), note (the open note; a new one carries
    // newGroup), notes (every note listed), groups, search, panel (the
    // note's card, made the tabs' tabpanel), open(noteId) - saves the note
    // being written first -, newNote(), reload().
    function draw(ctx) {
        var kit = ctx.kit;
        var note = ctx.note;
        var panel = ctx.panel || null;

        if (panel) {
            panel.removeAttribute('role');
            panel.removeAttribute('aria-labelledby');
        }

        if (!note) {
            return null;
        }

        var groupId = Number((note.id ? note.group_id : note.newGroup) || 0);
        var group = null;

        (ctx.groups || []).forEach(function (one) {
            if (Number(one.id) === groupId) {
                group = one;
            }
        });

        if (!group) {
            return null;
        }

        var notes = order(ctx.notes, groupId);
        var focused = document.activeElement;

        // The row is drawn again after each save: a focused tab stays focused.
        if (!focusAfter && focused && focused.classList && focused.classList.contains('ws-nb-tab')) {
            focusAfter = Number(focused.dataset.noteId || 0);
        }

        var box = kit.el('div', 'ws-nb-tabs');
        var head = kit.el('div', 'ws-nb-tabs-group');
        var row = kit.el('div', 'ws-nb-tabs-row');
        var dot = kit.el('span', 'ws-nb-group-dot' + (group.hex ? '' : ' ws-nb-group-dot-none'));
        var tabs = [];
        var active = -1;
        // A search lists only the notes it found, so their order is not the
        // group's whole order: dragging waits until the search is cleared.
        var canMove = !ctx.search && notes.length > 1;

        if (group.hex) {
            dot.style.background = group.hex;
        }

        dot.setAttribute('aria-hidden', 'true');
        head.appendChild(dot);
        head.appendChild(kit.el('span', 'ws-nb-tabs-name', group.name));

        if (group.locked) {
            head.appendChild(mark(kit, 'bi-lock-fill', kit.t('notes_group_locked')));
        }

        if (group.shared) {
            head.appendChild(mark(kit, 'bi-people', kit.t('notes_group_shared')));
        }

        box.appendChild(head);

        row.setAttribute('role', 'tablist');
        row.setAttribute('aria-label', group.name);
        row.setAttribute('aria-orientation', 'horizontal');

        notes.forEach(function (one, index) {
            var selected = !!note.id && Number(one.id) === Number(note.id);
            var tab = kit.el('button', 'ws-nb-tab' + (selected ? ' active' : ''));
            var name = one.name || kit.t('notes_title');

            tab.type = 'button';
            tab.id = 'ws-nb-tab-' + one.id;
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            tab.tabIndex = -1;
            tab.title = name;
            tab.dataset.noteId = String(one.id);
            var shown = short(name, TITLE_MAX);
            var nameNode = kit.el('span', 'ws-nb-tab-name', shown);

            tab.appendChild(nameNode);

            // A cut title is read whole: the short one is for the eye only.
            if (shown !== name) {
                nameNode.setAttribute('aria-hidden', 'true');
                tab.appendChild(kit.el('span', 'visually-hidden', name));
            }

            if (one.locked) {
                tab.appendChild(mark(kit, 'bi-lock-fill', kit.t('notes_group_locked')));
            }

            if (shared(one)) {
                tab.appendChild(mark(kit, 'bi-people', kit.t('notes_tab_shared')));
            }

            if (selected) {
                active = index;

                if (panel && panel.id) {
                    tab.setAttribute('aria-controls', panel.id);
                    panel.setAttribute('role', 'tabpanel');
                    panel.setAttribute('aria-labelledby', tab.id);
                }
            }

            tab.addEventListener('click', function () {
                if (!selected) {
                    ctx.open(one.id);
                }
            });

            if (canMove) {
                tab.draggable = true;
                // The keyboard's way of dragging a tab along (the keydown below).
                tab.setAttribute('aria-keyshortcuts', 'Control+Shift+ArrowLeft Control+Shift+ArrowRight');
            }

            tabs.push(tab);
            row.appendChild(tab);
        });

        // One tab in the row is reached with Tab: the open one, else the first.
        if (tabs.length) {
            tabs[active === -1 ? 0 : active].tabIndex = 0;
        }

        function focusTab(index) {
            tabs.forEach(function (tab, i) { tab.tabIndex = (i === index) ? 0 : -1; });
            tabs[index].focus();
            scrollTo(tabs[index]);
        }

        function place(from, position) {
            if (position === from || from < 0) {
                return;
            }

            var noteId = notes[from].id;

            kit.api('ws_note_place', { note_id: noteId, group_id: groupId, position: position }).then(function () {
                // The screen draws the row again after the list comes back;
                // focus follows the moved tab.
                focusAfter = Number(noteId);
                ctx.reload();
            }).catch(kit.fail);
        }

        row.addEventListener('keydown', function (event) {
            var index = tabs.indexOf(event.target);

            if (index === -1) {
                return;
            }

            var rtl = isRtl(row);

            if (event.ctrlKey && event.shiftKey && canMove && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
                var forward = (event.key === 'ArrowRight') !== rtl;
                var to = forward ? index + 1 : index - 1;

                event.preventDefault();

                if (to >= 0 && to < tabs.length) {
                    place(index, to);
                }

                return;
            }

            var next = step(event.key, index, tabs.length, rtl);

            if (next !== -1) {
                event.preventDefault();
                focusTab(next);
            }
        });

        // ── dragging a tab along the row ──

        var dragFrom = -1;
        var dropAt = -1;

        function centers() {
            return tabs.map(function (tab) {
                var rect = tab.getBoundingClientRect();

                return rect.left + rect.width / 2;
            });
        }

        function showDrop(position) {
            tabs.forEach(function (tab) {
                tab.classList.remove('ws-nb-tab-drop-before', 'ws-nb-tab-drop-after');
            });

            if (position === -1 || position === dragFrom) {
                return;
            }

            // The others in order, without the dragged tab: the line goes
            // before the one now at position, or after the last.
            var others = tabs.filter(function (tab, i) { return i !== dragFrom; });

            if (position < others.length) {
                others[position].classList.add('ws-nb-tab-drop-before');
            } else if (others.length) {
                others[others.length - 1].classList.add('ws-nb-tab-drop-after');
            }
        }

        if (canMove) {
            row.addEventListener('dragstart', function (event) {
                dragFrom = tabs.indexOf(event.target);

                if (dragFrom === -1) {
                    return;
                }

                event.target.classList.add('ws-nb-tab-dragging');

                try {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', event.target.title);
                } catch (error) {
                    // Some browsers refuse data on a button; the drag still runs.
                }
            });

            row.addEventListener('dragover', function (event) {
                if (dragFrom === -1) {
                    return;
                }

                event.preventDefault();
                dropAt = dropIndex(centers(), dragFrom, event.clientX, isRtl(row));
                showDrop(dropAt);
            });

            row.addEventListener('drop', function (event) {
                if (dragFrom === -1) {
                    return;
                }

                event.preventDefault();
                place(dragFrom, dropIndex(centers(), dragFrom, event.clientX, isRtl(row)));
            });

            row.addEventListener('dragend', function () {
                if (dragFrom !== -1 && tabs[dragFrom]) {
                    tabs[dragFrom].classList.remove('ws-nb-tab-dragging');
                }

                dragFrom = -1;
                dropAt = -1;
                showDrop(-1);
            });
        }

        box.appendChild(row);

        // + : a new note in this group. The screen saves the open one first
        // and places the new one last in the group (startNew()). A group
        // shared with the person to read only takes none.
        if (group.reach !== 'view') {
            var add = kit.button('ws-nb-tabs-add', '', 'bi-plus-lg', kit.t('notes_tab_new'));
            add.title = kit.t('notes_tab_new');
            add.addEventListener('click', function () { ctx.newNote(); });
            box.appendChild(add);
        }

        // The owner shares the group from here too (workspace_note_group_shares.js).
        if (ctx.share && !group.reach && !group.locked) {
            var shareButton = kit.button('ws-nb-tabs-add', '', 'bi-person-plus', kit.t('notes_group_share'));
            shareButton.title = kit.t('notes_group_share');
            shareButton.addEventListener('click', function () { ctx.share(group); });
            box.appendChild(shareButton);
        }

        var focusIndex = notes.map(function (one) { return Number(one.id); }).indexOf(focusAfter);

        if (focusIndex === -1) {
            focusAfter = 0;
        }

        // Once the row is in the page: the tab to focus gets it, else the
        // open tab is scrolled into view.
        window.setTimeout(function () {
            if (focusIndex !== -1 && box.isConnected) {
                focusAfter = 0;
                focusTab(focusIndex);
            } else if (active !== -1) {
                scrollTo(tabs[active]);
            }
        }, 0);

        return box;
    }

    // Brings a tab into the row's view without moving the page.
    function scrollTo(tab) {
        var row = tab && tab.parentNode;

        if (!row || !row.isConnected) {
            return;
        }

        var left = tab.offsetLeft - row.offsetLeft;
        var right = left + tab.offsetWidth;

        if (left < row.scrollLeft) {
            row.scrollLeft = left;
        } else if (right > row.scrollLeft + row.clientWidth) {
            row.scrollLeft = right - row.clientWidth;
        }
    }

    window.PGWsNoteTabs = {
        draw: draw,
        pure: {
            order: order,
            step: step,
            dropIndex: dropIndex,
            short: short
        }
    };
}());
