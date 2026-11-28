/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a note in a channel: the card a channel draws for a note
 * shared in it, and the windows it opens.
 *
 * The card is look A's plain card as wide as the message column: the note
 * icon and title, the ⋯ menu, the note drawn whole up to a fold of about
 * 18rem, then a small quiet "Show all" / "Show less" button centred under
 * it in place of an inner scroll; a card opened stays open while the
 * screen is (a redraw after a change keeps it). A first line "Written up
 * to …" is drawn as a meta line. Its foot is one muted line naming the
 * owner, who changed it last (the server's changed_by: a person by handle,
 * an agent by its application) and when, and "Versions" with their count
 * at its end. Its menu: Read, Edit, Versions, Open on the notes screen
 * and, for the owner, the channel's edit switch ("People who may post here can
 * edit", ws_note_channel_edit) and "Move to this channel" (ws_note_move): the
 * note becomes the channel's. A channel's note offers "Copy to my notes"
 * (ws_note_copy_mine), the way back.
 *
 * Edit is a window over the channel with the note's writing box; a stale
 * save says who changed the note meanwhile and offers their version or
 * the writer's own, as the notes screen does. Versions is a drawer of the
 * kept versions read through ws_note_get (versions / version), the same
 * list the notes screen reads; Put back saves the chosen version's text as
 * a change of the note, for who may edit. Write a note (the composer's
 * attach menu) writes the note and its card in one move
 * (ws_note_channel_write); the note belongs to the channel, and its card's
 * foot names who wrote it ("Written by").
 *
 * The channel's Notes tab (tab()) lists the notes that belong to it as the
 * Files tab lists files: a bar with a search over the titles and "Write a
 * note" at its end, then one row per note (title, "Changed by <name> ·
 * <time>"), the latest change first, read a page at a time
 * (ws_channel_notes). A row opens the note; its menu holds Open, Versions,
 * Copy to my notes, Export and, for its writer and the channel's managers,
 * Delete.
 *
 * assets/js/workspace.js calls card() from noteCardNode() and write() from
 * the attach menu, lending its helpers through window.PGWsKit and the
 * screen's own (the writing box, the menu, the drawn text) through the
 * hooks given. The server answers (includes/workspace/notes.php); every
 * text comes from the #ws-config block.
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

    // The cards opened past their fold, by note and channel: kept while the
    // screen is, so a card drawn again after a change stays open.
    var opened = {};

    // The fold's height in rem; backend.src.css folds .ws-cn-fold.is-folded
    // at the same height.
    var FOLD_REM = 18;

    // Below this width the Versions drawer comes up from the bottom.
    var PHONE = '(max-width: 575.98px)';

    var seq = 0;

    // The key a card's fold is kept under. Pure
    // (tests/workspace_channel_notes_js_test.php).
    function foldKey(card) {
        return (card && card.id ? card.id : 0) + ':' + (card && card.channel_id ? card.channel_id : 0);
    }

    // The fold's state: "whole" when the note fits (no toggle), "folded"
    // when it is longer and not opened, "open" when opened. Pure.
    function foldState(isOpen, overflows) {
        if (!overflows) {
            return 'whole';
        }

        return isOpen ? 'open' : 'folded';
    }

    // Who changed the note last and when, or '' when nobody is named: the
    // server's words (changed_by, an agent by its application's name), then
    // the time. Pure.
    function changedWords(card, t) {
        if (!card || !card.changed_by) {
            return '';
        }

        return card.changed_by + (card.updated ? ' · ' + card.updated : '');
    }

    // Who saved a kept version: the server's words, as on the card, or the
    // plain "Person" when nobody is known. Pure.
    function versionWho(version, t) {
        return (version && version.changed_by) ? version.changed_by : t('notes_person');
    }

    // The foot's words, in order: the owner (for a channel's note, who
    // wrote it), then who changed it last. Pure.
    function footWords(card, t) {
        var words = [];

        if (card && card.owner) {
            words.push(t(card.owned ? 'cn_written_by' : 'notes_owner', card.owner.name));
        }

        var changed = changedWords(card, t);

        if (changed) {
            words.push(changed);
        }

        return words;
    }

    // The foot's one line: its words joined by a middle dot. Pure.
    function footLine(card, t) {
        return footWords(card, t).join(' · ');
    }

    // Whether a note's first line is the "Written up to …" line an agent's
    // note opens with (the PR or message it is written up to). Pure.
    function writtenUpTo(line) {
        return /^written up to\b/i.test(String(line || '').replace(/^\s+/, ''));
    }

    // Whether the reader may change the note. Pure.
    function mayEdit(card) {
        return !!card && (card.access === 'owner' || card.access === 'edit');
    }

    // The card menu's items a reader gets, in order: everyone reads it and
    // sees its versions; a person's note opens on the notes screen (a
    // channel's note is on nobody's); Edit for who may change it; the
    // channel's edit switch and Move to this channel for its owner on the
    // channel's own share (a group's share is the group's); Copy to my
    // notes on a channel's note. Pure.
    function menuItems(card) {
        var items = ['read'];

        if (mayEdit(card)) {
            items.push('edit');
        }

        items.push('versions');

        if (!card || !card.owned) {
            items.push('open');
        }

        if (card && card.access === 'owner' && (card.channel_id > 0) && !card.via_group) {
            items.push('channel_edit', 'move');
        }

        if (card && card.owned) {
            items.push('copy');
        }

        return items;
    }

    // A row menu's items in the Notes tab, in order: Open, Versions, Copy to
    // my notes, Export when the screen can export, Delete for who may. Pure.
    function rowItems(note, canExport) {
        var items = ['open', 'versions', 'copy'];

        if (canExport) {
            items.push('export');
        }

        if (note && note.can_delete) {
            items.push('delete');
        }

        return items;
    }

    // Whether the channel shows its Notes tab: once it has a note, or while
    // the tab is the one open (its last note was just deleted). Pure.
    function tabShown(counts, current) {
        return (current === 'notes') || (!!counts && (counts.notes > 0));
    }

    // A row's meta line: who changed it last and when. The server's
    // changed_by already carries its label ("Changed by @name",
    // ws_note_changed_by_words()), so it is printed as it comes, never
    // wrapped in the label again. Pure.
    function rowMeta(note, t) {
        if (!note || !note.changed_by) {
            return note && note.updated ? note.updated : '';
        }

        return note.changed_by + (note.updated ? ' · ' + note.updated : '');
    }

    // A Bootstrap 6 dialog with a header, a body and a footer, added to the
    // page and removed once closed.
    function windowNode(kit, title, iconName) {
        var node = kit.el('dialog', 'dialog dialog-lg dialog-scrollable md-down:dialog-fullscreen ws-cn-window');
        var id = 'ws-cn-window-' + (++seq);

        node.tabIndex = -1;
        node.setAttribute('aria-labelledby', id);
        node.innerHTML = '<div class="dialog-header"><h5 class="dialog-title text-truncate"></h5><button type="button" class="btn-close" data-bs-dismiss="dialog"></button></div>'
            + '<div class="dialog-body"></div><div class="dialog-footer d-flex align-items-center gap-3"></div>';

        var heading = node.querySelector('.dialog-title');

        heading.id = id;
        heading.appendChild(kit.icon(iconName, 'me-3'));
        heading.appendChild(document.createTextNode(title));
        node.querySelector('.btn-close').setAttribute('aria-label', kit.t('close'));
        document.body.appendChild(node);
        pgUI.dialog.on(node, 'hidden', function () { node.remove(); });

        return {
            node: node,
            body: node.querySelector('.dialog-body'),
            footer: node.querySelector('.dialog-footer')
        };
    }

    // A look A button: kind fill, ghost or quiet.
    function lookButton(kit, kind, label, iconName) {
        var node = kit.el('button', 'pg-button pg-button-' + kind);

        node.type = 'button';

        if (iconName) {
            node.appendChild(kit.icon(iconName));
        }

        node.appendChild(kit.el('span', 'pg-btn-label', label));

        return node;
    }

    // The note's writing box: the title and the text with its row of tools
    // (marks, lists, headings, a table, code), as the notes screen gives.
    function writingBox(kit, hooks, holder, title, body, labels) {
        var titleInput = kit.form.input('text', title || '');

        titleInput.maxLength = 200;
        titleInput.placeholder = kit.t('notes_untitled');
        holder.appendChild(kit.form.field(kit.t('notes_title'), titleInput));

        var wrap = kit.el('div', 'ws-nb-editor ws-cn-editor');
        var field = hooks.richField(wrap, {
            placeholder: kit.t('notes_text'),
            slashInserts: { insertLabel: kit.t('insert_to_note') },
            removeLabel: kit.t('block_remove_note'),
            headings: true
        });

        if (!field) {
            return null;
        }

        wrap.appendChild(field.input);
        wrap.appendChild(window.PGWsEditor.toolbar(field.rich, [
            ['undo', 'redo'],
            ['bold', 'italic', 'strike'],
            ['bullets', 'numbers', 'quote', 'checklist'],
            ['heading1', 'heading2', 'heading3', 'rule'],
            ['table', 'codeblock']
        ], {
            className: 'ws-nb-tools',
            groupClass: 'ws-nb-tool-group',
            label: kit.t('tool_bar_note'),
            insertLabel: kit.t('insert_to_note')
        }));
        holder.appendChild(wrap);
        field.set(body || '', labels || {});

        return { title: titleInput, field: field };
    }

    // The busy state of a window's main button: kept label, disabled while
    // its request runs.
    function busy(buttonNode, on) {
        buttonNode.disabled = !!on;
        buttonNode.setAttribute('aria-busy', on ? 'true' : 'false');
    }

    /**
     * Write a note: a window with a title and the note's writing box; Send
     * writes the note and its card in the channel in one move.
     *
     * @param {object} kit   window.PGWsKit
     * @param {object} hooks channelId, richField, sync
     */
    function write(kit, hooks) {
        var win = windowNode(kit, kit.t('cn_write'), 'bi-journal-plus');
        var box = writingBox(kit, hooks, win.body, '', '', {});

        if (!box) {
            win.node.remove();
            return;
        }

        win.footer.appendChild(kit.el('span', 'flex-grow-1'));

        var cancel = lookButton(kit, 'ghost', kit.t('cancel'));

        cancel.setAttribute('data-bs-dismiss', 'dialog');
        win.footer.appendChild(cancel);

        var send = lookButton(kit, 'fill', kit.t('send'), 'bi-send');

        win.footer.appendChild(send);

        send.addEventListener('click', function () {
            busy(send, true);

            kit.api('ws_note_channel_write', {
                channel_id: hooks.channelId,
                title: box.title.value,
                body: box.field.value()
            }).then(function () {
                pgUI.dialog.hide(win.node).then(function () {
                    if (hooks.sync) {
                        hooks.sync();
                    }
                });
            }).catch(function (error) {
                busy(send, false);
                kit.fail(error);
            });
        });

        pgUI.dialog.show(win.node);
    }

    // Edit: the note in its writing box over the channel. A save over a
    // change made meanwhile names who made it; their version can be loaded
    // or the writer's own kept.
    function edit(kit, hooks, card) {
        kit.api('ws_note_get', { note_id: card.id }).then(function (data) {
            var note = data.note;
            var win = windowNode(kit, kit.t('notes_card_edit'), 'bi-pencil-square');
            var stale = kit.el('div', 'd-none');
            var holder = kit.el('div');
            var box = null;

            win.body.appendChild(stale);
            win.body.appendChild(holder);

            var fill = function (fresh) {
                note = fresh;
                kit.clear(holder);
                box = writingBox(kit, hooks, holder, note.title, note.body, note.labels);
            };

            fill(note);

            if (!box) {
                win.node.remove();
                return;
            }

            win.footer.appendChild(kit.el('span', 'flex-grow-1'));

            var cancel = lookButton(kit, 'ghost', kit.t('cancel'));

            cancel.setAttribute('data-bs-dismiss', 'dialog');
            win.footer.appendChild(cancel);

            var save = lookButton(kit, 'fill', kit.t('save'));

            win.footer.appendChild(save);

            var send = function (force) {
                busy(save, true);
                stale.classList.add('d-none');

                kit.api('ws_note_save', {
                    note_id: note.id,
                    title: box.title.value,
                    body: box.field.value(),
                    revision: force ? 0 : (note.revision || 0),
                    base: force ? 0 : (note.updated_at || 0)
                }).then(function () {
                    pgUI.dialog.hide(win.node).then(function () {
                        kit.toast(kit.t('notes_saved'), 'success');

                        if (hooks.sync) {
                            hooks.sync();
                        }
                    });
                }).catch(function (error) {
                    busy(save, false);

                    if (error.field === 'conflict') {
                        showStale(error.message);
                        return;
                    }

                    kit.fail(error);
                });
            };

            var showStale = function (message) {
                kit.clear(stale);
                stale.className = 'alert theme-warning d-flex flex-wrap align-items-center gap-3 py-3 mb-4';
                stale.setAttribute('role', 'alert');
                stale.appendChild(kit.el('span', 'me-auto', message || kit.t('notes_conflict')));

                var load = lookButton(kit, 'quiet', kit.t('notes_conflict_load'));

                load.classList.add('pg-button-sm');
                load.addEventListener('click', function () {
                    kit.api('ws_note_get', { note_id: note.id }).then(function (got) {
                        stale.classList.add('d-none');
                        fill(got.note);
                    }).catch(kit.fail);
                });
                stale.appendChild(load);

                var keep = lookButton(kit, 'quiet', kit.t('notes_conflict_keep'));

                keep.classList.add('pg-button-sm');
                keep.addEventListener('click', function () { send(true); });
                stale.appendChild(keep);
            };

            save.addEventListener('click', function () { send(false); });
            pgUI.dialog.show(win.node);
        }).catch(kit.fail);
    }

    // Versions: a drawer of the kept versions with who and when; Show draws
    // one in the drawer, Put back saves its text as a change of the note.
    function versions(kit, hooks, card) {
        var node = kit.el('dialog', 'drawer drawer-end ws-cn-versions');
        var id = 'ws-cn-versions-' + (++seq);
        var editable = mayEdit(card);
        var chosen = null;

        node.tabIndex = -1;
        node.setAttribute('aria-labelledby', id);
        // 480 px from the end; a sheet from the bottom on a phone (its
        // sizes: .ws-cn-versions in backend.src.css).
        if (window.matchMedia && window.matchMedia(PHONE).matches) {
            node.classList.remove('drawer-end');
            node.classList.add('drawer-bottom');
        }

        node.innerHTML = '<div class="drawer-header"><h5 class="drawer-title text-truncate"></h5><button type="button" class="btn-close" data-bs-dismiss="drawer"></button></div>'
            + '<div class="drawer-body flex-grow-1 overflow-auto"></div>'
            + '<div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"></div>';

        var heading = node.querySelector('.drawer-title');

        heading.id = id;
        heading.textContent = kit.t('cn_versions') + ' · ' + card.name;
        node.querySelector('.btn-close').setAttribute('aria-label', kit.t('close'));

        var body = node.querySelector('.drawer-body');
        var footer = node.querySelector('.drawer-footer');
        var list = kit.el('div', 'ws-cn-version-list');
        var view = kit.el('div', 'ws-cn-version-view d-none');
        var stale = kit.el('div', 'd-none');

        body.appendChild(stale);
        body.appendChild(list);
        body.appendChild(view);

        footer.appendChild(kit.el('span', 'flex-grow-1'));

        var close = lookButton(kit, 'ghost', kit.t('close'));

        close.setAttribute('data-bs-dismiss', 'drawer');
        footer.appendChild(close);

        var putBack = lookButton(kit, 'fill', kit.t('notes_version_restore'), 'bi-arrow-counterclockwise');

        putBack.disabled = true;
        putBack.classList.toggle('d-none', !editable);
        footer.appendChild(putBack);

        var showOne = function (version, row) {
            Array.prototype.forEach.call(list.querySelectorAll('.is-active'), function (other) {
                other.classList.remove('is-active');
            });
            row.classList.add('is-active');

            kit.api('ws_note_get', { note_id: card.id, version: version.id }).then(function (got) {
                chosen = got.version;
                kit.clear(view);
                view.classList.remove('d-none');
                view.appendChild(kit.el('div', 'pg-text-meta mb-3', version.saved_full || version.saved));

                var text = kit.el('div', 'ws-msg-body ws-nb-read');

                hooks.setHtml(text, chosen.html || '');
                hooks.chipMenus(text);
                view.appendChild(text);
                putBack.disabled = !editable;
            }).catch(kit.fail);
        };

        putBack.addEventListener('click', function () {
            if (!chosen || !editable) {
                return;
            }

            busy(putBack, true);
            stale.classList.add('d-none');

            kit.api('ws_note_save', {
                note_id: card.id,
                body: chosen.body || '',
                revision: card.revision || 0,
                base: card.updated_at || 0
            }).then(function () {
                pgUI.drawer.hide(node).then(function () {
                    kit.toast(kit.t('cn_put_back_done'), 'success');

                    if (hooks.sync) {
                        hooks.sync();
                    }
                });
            }).catch(function (error) {
                busy(putBack, false);

                if (error.field === 'conflict') {
                    stale.className = 'alert theme-warning py-3 mb-4';
                    stale.setAttribute('role', 'alert');
                    stale.textContent = error.message || kit.t('notes_conflict');
                    return;
                }

                kit.fail(error);
            });
        });

        kit.api('ws_note_get', { note_id: card.id, versions: 1 }).then(function (data) {
            var rows = data.versions || [];

            if (!rows.length) {
                list.appendChild(kit.el('p', 'pg-text-meta', kit.t('notes_versions_none')));
                return;
            }

            rows.forEach(function (version) {
                var row = kit.el('div', 'pg-list-row ws-cn-version');
                var main = kit.el('div', 'pg-list-row-main');
                var head = kit.el('div', 'pg-list-row-head');

                head.appendChild(kit.el('span', 'pg-list-row-title', versionWho(version, kit.t)));
                head.appendChild(kit.el('span', 'pg-list-row-time', version.saved));
                main.appendChild(head);
                main.appendChild(kit.el('div', 'pg-list-row-text', kit.t('notes_version_size', version.size)));

                var actions = kit.el('div', 'pg-list-row-actions');
                var show = lookButton(kit, 'quiet', kit.t('cn_show'));

                show.classList.add('pg-button-sm');
                show.title = version.saved_full || '';
                show.addEventListener('click', function () { showOne(version, row); });
                actions.appendChild(show);
                main.appendChild(actions);
                row.appendChild(main);
                list.appendChild(row);
            });
        }).catch(kit.fail);

        document.body.appendChild(node);
        pgUI.drawer.on(node, 'hidden', function () { node.remove(); });
        pgUI.drawer.show(node);
    }

    // The owner's switch: may the channel's posters edit the note?
    function channelEdit(kit, hooks, card) {
        var on = !card.channel_edit;

        kit.api('ws_note_channel_edit', { note_id: card.id, channel_id: card.channel_id, can_edit: on ? 1 : 0 }).then(function () {
            kit.toast(kit.t(on ? 'cn_channel_edit_on' : 'cn_channel_edit_off'), 'success');

            if (hooks.sync) {
                hooks.sync();
            }
        }).catch(kit.fail);
    }

    // Move to this channel: the owner's note becomes the channel's, after a
    // question, since it leaves their notes and every other share.
    function moveHere(kit, hooks, card) {
        kit.ask(kit.t('cn_move_confirm'), kit.t('cn_move')).then(function (yes) {
            if (!yes) {
                return;
            }

            kit.api('ws_note_move', { note_id: card.id, channel_id: card.channel_id }).then(function () {
                kit.toast(kit.t('cn_moved'), 'success');

                if (hooks.sync) {
                    hooks.sync();
                }
            }).catch(kit.fail);
        });
    }

    // Copy to my notes: a channel's note copied into the reader's own.
    function copyMine(kit, noteId) {
        kit.api('ws_note_copy_mine', { note_id: noteId }).then(function () {
            kit.toast(kit.t('cn_copied'), 'success');
        }).catch(kit.fail);
    }

    // Draws the note's first line as a small meta line when it is the
    // "Written up to …" line. The line is the first text block's first line
    // (up to its <br>), or the text's own first line when the note is one
    // plain text; a note opening with a heading, a list or a table has none.
    function metaLine(kit, text) {
        var first = text.firstChild;

        while (first && first.nodeType === 3 && !first.nodeValue.trim()) {
            first = first.nextSibling;
        }

        if (!first) {
            return;
        }

        var inBlock = first.nodeType === 1 && first.classList.contains('ws-text');

        if (!inBlock && first.nodeType === 1 && /^(DIV|P|H[1-6]|UL|OL|TABLE|BLOCKQUOTE|HR|PRE|LABEL)$/.test(first.tagName)) {
            return;
        }

        var holder = inBlock ? first : text;
        var nodes = [];
        var words = '';

        for (var node = inBlock ? first.firstChild : first; node && !(node.nodeType === 1 && node.tagName === 'BR'); node = node.nextSibling) {
            nodes.push(node);
            words += node.textContent;
        }

        if (!nodes.length || !writtenUpTo(words)) {
            return;
        }

        var meta = kit.el('div', 'pg-text-meta ws-cn-meta');
        var after = nodes[nodes.length - 1].nextSibling;

        if (after) {
            holder.removeChild(after);
        }

        nodes.forEach(function (one) {
            meta.appendChild(one);
        });
        text.insertBefore(meta, text.firstChild);

        // The empty lines under it go: the meta line keeps its own gap.
        var rest = inBlock ? first.firstChild : meta.nextSibling;

        while (rest && ((rest.nodeType === 3 && !rest.nodeValue.trim()) || (rest.nodeType === 1 && rest.tagName === 'BR'))) {
            var gone = rest;

            rest = rest.nextSibling;
            gone.remove();
        }

        if (inBlock && !first.children.length && !first.textContent.trim()) {
            first.remove();
        }
    }

    // An empty line between two paragraphs of one text block is drawn as a
    // line break after a line break, a whole empty line high; in the card it
    // becomes a short gap (.ws-cn-gap) so paragraphs sit close together.
    // Only in running text: code and table cells keep their lines.
    function tightGaps(kit, text) {
        Array.prototype.forEach.call(text.querySelectorAll('br'), function (br) {
            var parent = br.parentNode;

            if (parent !== text && !(parent.classList && parent.classList.contains('ws-text'))) {
                return;
            }

            var before = br.previousSibling;

            while (before && before.nodeType === 3 && !before.nodeValue.trim()) {
                before = before.previousSibling;
            }

            if (before && before.nodeType === 1 && before.classList.contains('ws-cn-gap')) {
                // Several empty lines in a row: one gap.
                br.remove();
            } else if (before && before.nodeType === 1 && before.tagName === 'BR') {
                parent.replaceChild(kit.el('span', 'ws-cn-gap'), br);
            }
        });
    }

    // Lays the fold on the drawn note once it is on the page: a note
    // taller than the fold (18rem, as the stylesheet's) gets the toggle.
    // The toggle is a small quiet button centred under the note, in its own
    // row (more), hidden while the note fits.
    function fold(kit, card, text, more, toggle) {
        var key = foldKey(card);

        var draw = function () {
            var rem = parseFloat(window.getComputedStyle(document.documentElement).fontSize) || 16;
            var state = foldState(!!opened[key], text.scrollHeight > (FOLD_REM * rem) + 1);

            text.classList.toggle('is-folded', state === 'folded');
            more.classList.toggle('d-none', state === 'whole');
            toggle.setAttribute('aria-expanded', (state === 'open') ? 'true' : 'false');
            kit.clear(toggle);
            toggle.appendChild(kit.el('span', 'pg-btn-label', kit.t((state === 'open') ? 'cn_show_less' : 'cn_show_all')));
            toggle.appendChild(kit.icon((state === 'open') ? 'bi-chevron-up' : 'bi-chevron-down'));
        };

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            opened[key] = !opened[key];
            draw();
        });

        // Measured once the card is in the conversation, and again when its
        // pictures load.
        window.requestAnimationFrame(draw);
        Array.prototype.forEach.call(text.querySelectorAll('img'), function (image) {
            image.addEventListener('load', draw);
        });
    }

    /**
     * The card of a note shared in a channel.
     *
     * @param {object} kit   window.PGWsKit
     * @param {object} card  the message's note_card (ws_note_cards_map())
     * @param {object} hooks setHtml, chipMenus, menu(x, y, items),
     *                       read(noteId), notes(noteId), richField, sync,
     *                       share() the menu entries of a share link
     * @return {HTMLElement}
     */
    function cardNode(kit, card, hooks) {
        // Look A's plain card (pg-card), as wide as the message column.
        var box = kit.el('div', 'pg-card ws-cn-card');
        var top = kit.el('div', 'ws-nb-msg-card-head ws-cn-head');

        top.appendChild(kit.icon('bi-journal-text', 'ws-cn-icon'));
        top.appendChild(kit.el('span', 'ws-cn-title ws-grow text-truncate', card.name));

        var more = kit.el('button', 'pg-icon-button pg-icon-button-sm');

        more.type = 'button';
        more.setAttribute('aria-label', kit.t('cn_menu'));
        more.setAttribute('aria-haspopup', 'menu');
        more.title = kit.t('cn_menu');
        more.appendChild(kit.icon('bi-three-dots'));
        more.addEventListener('click', function (event) {
            var place = more.getBoundingClientRect();
            var actions = {
                read: { icon: 'bi-eye', label: kit.t('notes_card_open'), action: function () { hooks.read(card.id); } },
                edit: { icon: 'bi-pencil-square', label: kit.t('notes_card_edit'), action: function () { edit(kit, hooks, card); } },
                versions: { icon: 'bi-clock-history', label: kit.t('cn_versions'), action: function () { versions(kit, hooks, card); } },
                open: { icon: 'bi-box-arrow-up-right', label: kit.t('cn_open_notes'), action: function () { hooks.notes(card.id); } },
                channel_edit: { icon: 'bi-people', label: kit.t('cn_channel_edit'), checked: !!card.channel_edit, action: function () { channelEdit(kit, hooks, card); } },
                move: { icon: 'bi-box-arrow-in-down', label: kit.t('cn_move'), action: function () { moveHere(kit, hooks, card); } },
                copy: { icon: 'bi-copy', label: kit.t('cn_copy_mine'), action: function () { copyMine(kit, card.id); } }
            };

            event.stopPropagation();
            var items = menuItems(card).map(function (name) {
                return ((name === 'channel_edit') || (name === 'copy')) ? ['-', actions[name]] : [actions[name]];
            }).reduce(function (all, part) { return all.concat(part); }, []);

            // Share by link... or Stop sharing, from the screen (a note of
            // the channel's own in an agent channel).
            var share = hooks.share ? hooks.share() : [];

            hooks.menu(more, place.left, place.bottom + 4, share.length ? items.concat(['-'], share) : items);
        });
        top.appendChild(more);
        box.appendChild(top);

        if (card.html) {
            var text = kit.el('div', 'ws-nb-msg-card-text ws-nb-msg-card-html ws-cn-fold');
            var more = kit.el('div', 'ws-cn-more d-none');
            var toggle = kit.el('button', 'pg-button pg-button-quiet pg-button-sm');

            hooks.setHtml(text, card.html);
            metaLine(kit, text);
            tightGaps(kit, text);
            hooks.chipMenus(text);
            text.id = 'ws-cn-fold-' + (++seq);
            toggle.type = 'button';
            toggle.setAttribute('aria-controls', text.id);
            more.appendChild(toggle);
            box.appendChild(text);
            box.appendChild(more);
            fold(kit, card, text, more, toggle);
        } else if (card.excerpt) {
            box.appendChild(kit.el('div', 'ws-nb-msg-card-text', card.excerpt));
        }

        // The foot: one small muted line, cut short with an ellipsis where
        // it is too long, and Versions at its end.
        var foot = kit.el('div', 'ws-cn-foot pg-text-meta');
        var line = footLine(card, kit.t);
        var words = kit.el('span', 'ws-cn-foot-words text-truncate', line);

        words.title = line;
        foot.appendChild(words);

        if (card.versions > 0) {
            var list = lookButton(kit, 'ghost', kit.t('cn_versions'), 'bi-clock-history');

            // On a phone the label hides (the words keep the room); the
            // button is still named by its title.
            list.classList.add('pg-button-sm', 'ws-cn-versions-button');
            list.title = kit.t('cn_versions');
            list.appendChild(kit.el('span', 'pg-count', String(card.versions)));
            list.addEventListener('click', function (event) {
                event.stopPropagation();
                versions(kit, hooks, card);
            });
            foot.appendChild(list);
        }

        box.appendChild(foot);

        return box;
    }

    /**
     * The channel's Notes tab: its bar, then its notes a page at a time.
     *
     * @param {object}      kit     window.PGWsKit
     * @param {HTMLElement} pane    the tab's pane
     * @param {object}      channel the open channel (id, can_post)
     * @param {object}      hooks   noteHooks() of the screen, with
     *                              current() (whether the pane is still
     *                              shown) and counted() (the tab marks
     *                              may have changed)
     */
    function tab(kit, pane, channel, hooks) {
        var loaded = [];
        var after = 0;
        var afterId = 0;
        var query = '';
        var bar = kit.el('div', 'pg-toolbar ws-pane-toolbar ws-cn-bar');
        var search = kit.el('label', 'pg-input pg-input-sm ws-bar-search');
        var input = kit.el('input', '');
        var body = kit.el('div', 'ws-cn-tab-body');

        search.appendChild(kit.icon('bi-search', ''));
        input.type = 'search';
        input.placeholder = kit.t('cn_search');
        input.setAttribute('aria-label', kit.t('cn_search'));
        search.appendChild(input);
        bar.appendChild(search);
        bar.appendChild(kit.el('span', 'flex-grow-1'));

        if (channel.can_post) {
            var add = lookButton(kit, 'fill', kit.t('cn_write'), 'bi-journal-plus');

            add.classList.add('pg-button-sm');
            add.addEventListener('click', function () { write(kit, hooks); });
            bar.appendChild(add);
        }

        pane.appendChild(bar);
        pane.appendChild(body);

        var remove = function (note) {
            kit.ask(kit.t('cn_delete_confirm'), kit.t('notes_delete'), true).then(function (yes) {
                if (!yes) {
                    return;
                }

                kit.api('ws_note_delete', { note_id: note.id }).then(function () {
                    kit.toast(kit.t('notes_deleted'), 'success');
                    loaded = loaded.filter(function (one) { return one.id !== note.id; });
                    draw();

                    if (hooks.counted) {
                        hooks.counted();
                    }
                }).catch(kit.fail);
            });
        };

        var menu = function (note) {
            var url = kit.cfg.urls && kit.cfg.urls.notes_export;
            var actions = {
                open: { icon: 'bi-eye', label: kit.t('cn_open'), action: function () { hooks.read(note.id); } },
                versions: { icon: 'bi-clock-history', label: kit.t('cn_versions'), action: function () { versions(kit, hooks, note); } },
                copy: { icon: 'bi-copy', label: kit.t('cn_copy_mine'), action: function () { copyMine(kit, note.id); } },
                'export': { icon: 'bi-filetype-md', label: kit.t('notes_export_md'), action: function () { window.location.href = url + '?note=' + note.id; } },
                'delete': { icon: 'bi-trash', label: kit.t('notes_delete'), danger: true, action: function () { remove(note); } }
            };
            var items = [{ header: note.name }];

            rowItems(note, !!url).forEach(function (name) {
                if (name === 'delete') {
                    items.push('-');
                }

                items.push(actions[name]);
            });

            return items;
        };

        var draw = function () {
            kit.clear(body);

            if (!loaded.length) {
                var empty = kit.el('div', 'ws-empty');

                empty.appendChild(kit.icon('bi-journal-text'));
                empty.appendChild(document.createTextNode(kit.t(query ? 'cn_none_found' : 'cn_none')));
                body.appendChild(empty);
                return;
            }

            var list = kit.el('div', 'ws-file-list');

            loaded.forEach(function (note) {
                var row = kit.el('div', 'ws-file-row ws-file-shows ws-cn-row');
                var link = kit.el('button', 'ws-file-link');

                link.type = 'button';
                link.appendChild(kit.icon('bi-journal-text', 'ws-cn-row-icon'));
                link.appendChild(kit.el('span', 'text-truncate', note.name));
                link.addEventListener('click', function () { hooks.read(note.id); });
                row.appendChild(link);

                var meta = kit.el('small', 'fg-secondary ws-grow ws-file-meta', rowMeta(note, kit.t));

                meta.title = meta.textContent;
                row.appendChild(meta);

                var more = kit.el('button', 'pg-icon-button pg-icon-button-sm');

                more.type = 'button';
                more.setAttribute('aria-label', kit.t('cn_menu'));
                more.setAttribute('aria-haspopup', 'menu');
                more.title = kit.t('cn_menu');
                more.appendChild(kit.icon('bi-three-dots'));
                more.addEventListener('click', function (event) {
                    var place = more.getBoundingClientRect();

                    event.stopPropagation();
                    hooks.menu(more, place.left, place.bottom + 4, menu(note));
                });
                row.appendChild(more);
                row.addEventListener('click', function (event) {
                    if (!event.target.closest('button')) {
                        hooks.read(note.id);
                    }
                });
                kit.onContext(row, function () { return menu(note); });
                list.appendChild(row);
            });

            body.appendChild(list);

            if (afterId > 0) {
                var older = lookButton(kit, 'quiet', kit.t('cn_older'), 'bi-chevron-down');

                older.classList.add('pg-button-sm', 'ws-files-older');
                older.addEventListener('click', function () {
                    older.disabled = true;
                    load(false);
                });
                body.appendChild(older);
            }
        };

        // A page; a new search starts again from the top.
        var load = function (fresh) {
            var params = { channel_id: channel.id, q: query };

            if (!fresh && (afterId > 0)) {
                params.after = after;
                params.after_id = afterId;
            }

            kit.api('ws_channel_notes', params).then(function (data) {
                if (hooks.current && !hooks.current()) {
                    return;
                }

                loaded = fresh ? (data.notes || []) : loaded.concat(data.notes || []);
                after = data.after || 0;
                afterId = data.after_id || 0;
                draw();
            }).catch(kit.fail);
        };

        input.addEventListener('input', kit.debounce(function () {
            query = input.value.trim();
            load(true);
        }, 300));

        load(true);
    }

    window.PGWsChannelNotes = {
        card: cardNode,
        write: write,
        tab: tab,
        tabShown: tabShown
    };
})();
