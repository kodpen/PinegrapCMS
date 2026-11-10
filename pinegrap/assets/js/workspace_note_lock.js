/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the notes lock on the My Notes screen: the toolbar's Lock
 * (a dialog setting a PIN and the time after which it is asked again; once
 * set, a menu with Lock now, Change PIN, Change the time and Turn off), Lock
 * and Unlock in a note's and an own group's menu, the lock mark of a listed
 * note, and the lock pane drawn in place of a note behind the closed lock:
 * its title, a masked PIN field, Open, and "Forgot your PIN?", which sends a
 * code by e-mail and then asks for the code and a new PIN. A note open while
 * the lock's time runs out with nothing done in it is closed again (the lock
 * is shut on the server too, Lock now), and so is one an answer refuses
 * with code note_locked.
 *
 * The store and its actions are includes/workspace/note_lock.php
 * (ws_note_lock_*, ws_note_lock_note, ws_note_group_lock). assets/js/
 * workspace.js hands over its helpers and the open note (startNotes(),
 * lockCtx()). The pure helpers sit in window.PGWsNoteLock.pure
 * (tests/workspace_note_lock_js_test.php). Every text comes from the
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

    // The minutes kept for "every time" and "until I sign out"
    // (WS_NOTE_LOCK_EVERY_TIME, WS_NOTE_LOCK_UNTIL_SIGN_OUT).
    var EVERY_TIME = 0;
    var UNTIL_SIGN_OUT = 65535;

    // The person's lock as the server last told it (ws_note_lock_state()).
    var state = null;

    // When something was last done in the open note, for the time-out.
    var lastUse = Date.now();
    var watchTimer = 0;

    // ── pure helpers ──

    // Whether a listed or open note is behind the person's lock: their own
    // locked note, or a note in one of their locked groups.
    function behind(note) {
        return !!(note && (note.locked || note.lock_group));
    }

    // Whether a PIN has the shape the server takes: 4 to 8 digits.
    function pinOk(pin) {
        return /^[0-9]{4,8}$/.test(String(pin === undefined || pin === null ? '' : pin));
    }

    // Whether an open lock has run out: the chosen minutes since the last
    // use. "Every time" holds while the note stays open (the server asks
    // again for any other note); "until I sign out" never runs out.
    function expired(minutes, last, now) {
        minutes = Number(minutes);

        if (!(minutes > 0) || minutes >= UNTIL_SIGN_OUT) {
            return false;
        }

        return (now - last) >= minutes * 60000;
    }

    // Seconds as whole minutes, rounded up, at least one.
    function waitMinutes(seconds) {
        return Math.max(1, Math.ceil(Number(seconds || 0) / 60));
    }

    // ── state ──

    function load(ctx) {
        return ctx.kit.api('ws_note_lock', {}).then(function (data) {
            state = data.lock || null;

            return state;
        });
    }

    function remember(data) {
        if (data && data.lock) {
            state = data.lock;
        }
    }

    function used() {
        lastUse = Date.now();
    }

    // ── fields ──

    function pinField(kit, label, id) {
        var wrap = kit.el('div', 'mb-4');
        var name = kit.el('label', 'form-label small', label);
        var input = kit.el('input', 'form-control form-control-sm ws-nb-lock-pin');

        input.type = 'password';
        input.inputMode = 'numeric';
        input.autocomplete = 'off';
        input.maxLength = 8;
        input.pattern = '[0-9]*';
        input.id = id;
        name.htmlFor = id;
        wrap.appendChild(name);
        wrap.appendChild(input);
        wrap.input = input;

        return wrap;
    }

    function timeField(kit, minutes) {
        var wrap = kit.el('div', 'mb-2');
        var name = kit.el('label', 'form-label small', kit.t('nlock_again_after'));
        var select = kit.el('select', 'form-control form-control-sm');

        select.id = 'ws-nb-lock-minutes';
        name.htmlFor = select.id;

        ((state && state.times) || []).forEach(function (time) {
            var option = kit.el('option', '', time.label);

            option.value = String(time.minutes);
            option.selected = Number(time.minutes) === Number(minutes);
            select.appendChild(option);
        });

        wrap.appendChild(name);
        wrap.appendChild(select);
        wrap.input = select;

        return wrap;
    }

    // A dialog of fields (kit.ask()); send(values) answers a promise, and a
    // refusal opens the dialog again with what was typed and the reason.
    function form(ctx, title, okLabel, fields, send, danger) {
        var kit = ctx.kit;
        var box = kit.el('div', 'ws-nb-lock-form');
        var note = kit.el('div', 'small fg-danger mt-2 d-none');

        // The reason of a refusal is read with each field and announced.
        note.id = 'ws-nb-lock-form-note';
        note.setAttribute('role', 'alert');
        fields.forEach(function (field) {
            box.appendChild(field);

            if (field.input) {
                field.input.setAttribute('aria-describedby', note.id);
            }
        });
        box.appendChild(note);

        function round(message) {
            note.textContent = message || '';
            note.classList.toggle('d-none', !message);
            fields.forEach(function (field) {
                if (field.input) {
                    field.input.setAttribute('aria-invalid', message ? 'true' : 'false');
                }
            });

            window.setTimeout(function () {
                var first = box.querySelector('input, select');

                if (first) {
                    first.focus();
                }
            }, 60);

            return kit.ask(title, okLabel, !!danger, box).then(function (yes) {
                if (!yes) {
                    return null;
                }

                return send().then(function (data) {
                    remember(data);

                    return data;
                }, function (error) {
                    remember(error);

                    return round(error.message);
                });
            });
        }

        return round('');
    }

    // The dialog that sets the lock up: a PIN twice and the time.
    function setUp(ctx) {
        var kit = ctx.kit;
        var pin = pinField(kit, kit.t('nlock_pin'), 'ws-nb-lock-setup-pin');
        var pin2 = pinField(kit, kit.t('nlock_pin_again'), 'ws-nb-lock-setup-pin2');
        var time = timeField(kit, state ? state.minutes : 5);
        var intro = kit.el('p', 'small fg-secondary mb-4', kit.t('nlock_intro'));

        return form(ctx, kit.t('nlock_set_up'), kit.t('nlock_set_up_ok'), [intro, pin, pin2, time], function () {
            return kit.api('ws_note_lock_setup', { pin: pin.input.value, pin2: pin2.input.value, minutes: Number(time.input.value) });
        }).then(function (data) {
            if (data) {
                kit.toast(kit.t('nlock_set_up_done'));
                used();
            }

            return data;
        });
    }

    function changePin(ctx) {
        var kit = ctx.kit;
        var current = pinField(kit, kit.t('nlock_pin_current'), 'ws-nb-lock-current');
        var pin = pinField(kit, kit.t('nlock_pin_new'), 'ws-nb-lock-new');
        var pin2 = pinField(kit, kit.t('nlock_pin_again'), 'ws-nb-lock-new2');

        form(ctx, kit.t('nlock_change_pin'), kit.t('save'), [current, pin, pin2], function () {
            return kit.api('ws_note_lock_change', { current: current.input.value, pin: pin.input.value, pin2: pin2.input.value });
        }).then(function (data) {
            if (data) {
                kit.toast(kit.t('nlock_pin_changed'));
                used();
            }
        });
    }

    function changeTime(ctx) {
        var kit = ctx.kit;
        var pin = pinField(kit, kit.t('nlock_pin'), 'ws-nb-lock-time-pin');
        var time = timeField(kit, state ? state.minutes : 5);

        form(ctx, kit.t('nlock_change_time'), kit.t('save'), [time, pin], function () {
            return kit.api('ws_note_lock_time', { pin: pin.input.value, minutes: Number(time.input.value) });
        }).then(function (data) {
            if (data) {
                kit.toast(kit.t('nlock_time_changed'));
                used();
            }
        });
    }

    function turnOff(ctx) {
        var kit = ctx.kit;
        var pin = pinField(kit, kit.t('nlock_pin'), 'ws-nb-lock-off-pin');
        var said = kit.el('p', 'small mb-4', kit.t('nlock_off_says'));

        form(ctx, kit.t('nlock_off'), kit.t('nlock_off'), [said, pin], function () {
            return kit.api('ws_note_lock_off', { pin: pin.input.value });
        }, true).then(function (data) {
            if (data) {
                kit.toast(kit.t('nlock_off_done'));
                ctx.reload();
            }
        });
    }

    // What is typed is saved first, then the lock shuts.
    function lockNow(ctx) {
        return (ctx.save ? ctx.save() : Promise.resolve()).then(function () {
            return ctx.kit.api('ws_note_lock_now', {});
        }).then(function (data) {
            remember(data);
            ctx.closed();
        }).catch(ctx.kit.fail);
    }

    // ── the toolbar ──

    function toolbarButton(ctx) {
        var kit = ctx.kit;
        var node = kit.button('btn btn-sm btn-text theme-secondary ws-nb-lock-button', kit.t('nlock_lock'), 'bi-lock');

        // A menu once the lock is set up; before that the set-up dialog.
        function popup() {
            node.setAttribute('aria-haspopup', (state && state.set_up) ? 'menu' : 'dialog');
        }

        popup();
        node.addEventListener('click', function (event) {
            var box = node.getBoundingClientRect();

            event.stopPropagation();

            (state ? Promise.resolve(state) : load(ctx)).then(function () {
                popup();

                if (!state || !state.set_up) {
                    setUp(ctx);
                    return;
                }

                ctx.ctxMenu(box.left, box.bottom + 4, [
                    { header: kit.t('nlock_lock') },
                    { icon: 'bi-lock-fill', label: kit.t('nlock_now'), action: function () { lockNow(ctx); } },
                    { icon: 'bi-key', label: kit.t('nlock_change_pin'), action: function () { changePin(ctx); } },
                    { icon: 'bi-clock', label: kit.t('nlock_change_time'), action: function () { changeTime(ctx); } },
                    '-',
                    { icon: 'bi-unlock', label: kit.t('nlock_off'), danger: true, action: function () { turnOff(ctx); } }
                ]);
            }).catch(kit.fail);
        });

        return node;
    }

    // ── locking a note or a group ──

    // Sends a lock or unlock, asking first when locking takes shares back
    // (code confirm) and for the PIN when unlocking finds the lock closed.
    function setLocked(ctx, action, sent, name) {
        var kit = ctx.kit;

        function go(extra) {
            var data = {};

            Object.keys(sent).forEach(function (key) { data[key] = sent[key]; });
            Object.keys(extra || {}).forEach(function (key) { data[key] = extra[key]; });

            return kit.api(action, data).then(function (answer) {
                remember(answer);
                used();
                kit.toast(kit.t(sent.locked ? 'nlock_locked_done' : 'nlock_unlocked_done', name));
                ctx.reload();
            }, function (error) {
                remember(error);

                if (error.code === 'confirm') {
                    return kit.ask(kit.t('nlock_shares_ask', name, String(error.shares || 0)), kit.t('nlock_lock'), true).then(function (yes) {
                        return yes ? go({ confirm: 1 }) : null;
                    });
                }

                if (error.code === 'pin' && !(extra && extra.pin)) {
                    var pin = pinField(kit, kit.t('nlock_pin'), 'ws-nb-lock-unlock-pin');

                    return form(ctx, kit.t('nlock_unlock_ask', name), kit.t('nlock_unlock'), [pin], function () {
                        var data = {};

                        Object.keys(sent).forEach(function (key) { data[key] = sent[key]; });
                        data.pin = pin.input.value;

                        return kit.api(action, data);
                    }).then(function (answer) {
                        if (answer) {
                            used();
                            kit.toast(kit.t('nlock_unlocked_done', name));
                            ctx.reload();
                        }
                    });
                }

                kit.fail(error);
            });
        }

        // No PIN yet: setting it up comes first.
        return (state ? Promise.resolve(state) : load(ctx)).then(function () {
            if (state && state.set_up) {
                return go();
            }

            return setUp(ctx).then(function (data) {
                return data ? go() : null;
            });
        }).catch(kit.fail);
    }

    function noteItems(ctx, note) {
        var kit = ctx.kit;

        if (!state || !state.ready || !note || note.access !== 'owner' || !note.id) {
            return [];
        }

        return [note.locked
            ? { icon: 'bi-unlock', label: kit.t('nlock_unlock'), action: function () { setLocked(ctx, 'ws_note_lock_note', { note_id: note.id, locked: 0 }, note.name); } }
            : { icon: 'bi-lock', label: kit.t('nlock_lock'), action: function () { setLocked(ctx, 'ws_note_lock_note', { note_id: note.id, locked: 1 }, note.name); } }];
    }

    function groupItems(ctx, group) {
        var kit = ctx.kit;

        if (!state || !state.ready || !group) {
            return [];
        }

        return [group.locked
            ? { icon: 'bi-unlock', label: kit.t('nlock_unlock'), action: function () { setLocked(ctx, 'ws_note_group_lock', { group_id: group.id, locked: 0 }, group.name); } }
            : { icon: 'bi-lock', label: kit.t('nlock_lock'), action: function () { setLocked(ctx, 'ws_note_group_lock', { group_id: group.id, locked: 1 }, group.name); } }];
    }

    // The mark of a note behind the lock in a list row; null otherwise.
    function mark(kit, note) {
        if (!behind(note)) {
            return null;
        }

        var node = kit.icon(note.lock_closed ? 'bi-lock-fill' : 'bi-unlock', 'ws-nb-item-lock');
        var label = kit.t(note.lock_closed ? 'nlock_mark_closed' : 'nlock_mark_open');

        // A named image: kit.icon() hides it from screen readers.
        node.title = label;
        node.removeAttribute('aria-hidden');
        node.setAttribute('role', 'img');
        node.setAttribute('aria-label', label);

        return node;
    }

    // ── the lock pane ──

    // Drawn in place of a note (noteId, title) behind the closed lock, or
    // of the list's locked groups (noteId 0). ctx.opened(noteId) follows a
    // right PIN or a new one.
    function pane(ctx, noteId, title) {
        var kit = ctx.kit;
        var box = kit.el('div', 'card ws-nb-card ws-nb-lock-pane');
        var body = kit.el('div', 'card-body d-flex flex-column align-items-center text-center gap-3 py-8');
        var formNode = kit.el('form', 'ws-nb-lock-form d-flex flex-column align-items-stretch gap-3');
        var message = kit.el('div', 'small fg-danger d-none');

        message.id = 'ws-nb-lock-pane-message';
        message.setAttribute('role', 'alert');
        body.appendChild(kit.icon('bi-lock-fill', 'ws-nb-lock-icon'));
        body.appendChild(kit.el('h2', 'h5 mb-0 ws-nb-lock-title', title || kit.t('nlock_locked_note')));
        body.appendChild(kit.el('p', 'small fg-secondary mb-2', kit.t(noteId ? 'nlock_pane_note' : 'nlock_pane_list')));
        box.appendChild(body);
        body.appendChild(formNode);

        // The message is read with the fields it is about, which are
        // marked invalid while it stands.
        function say(text) {
            message.textContent = text || '';
            message.classList.toggle('d-none', !text);
            Array.prototype.forEach.call(formNode.querySelectorAll('input'), function (input) {
                input.setAttribute('aria-describedby', message.id);
                input.setAttribute('aria-invalid', text ? 'true' : 'false');
            });
        }

        function busy(node, yes) {
            node.disabled = !!yes;
            node.setAttribute('aria-busy', yes ? 'true' : 'false');
        }

        function opened(data) {
            remember(data);
            used();
            ctx.opened(noteId);
        }

        function refused(error) {
            remember(error);
            say(error.code === 'wait' ? kit.t('nlock_wait', String(waitMinutes(error.wait))) : error.message);
        }

        // PIN, Open, Forgot your PIN?
        function drawPin() {
            kit.clear(formNode);

            var pin = pinField(kit, kit.t('nlock_pin'), 'ws-nb-lock-pane-pin');
            var open = kit.button('btn btn-sm btn-solid theme-primary rounded-pill px-5', kit.t('nlock_open'), 'bi-unlock');
            var forgot = kit.button('btn btn-sm btn-text theme-secondary', kit.t('nlock_forgot'));

            open.type = 'submit';
            forgot.type = 'button';
            formNode.appendChild(pin);
            formNode.appendChild(message);
            formNode.appendChild(open);
            formNode.appendChild(forgot);

            formNode.onsubmit = function (event) {
                event.preventDefault();

                if (!pinOk(pin.input.value)) {
                    say(kit.t('nlock_pin_shape'));
                    pin.input.focus();
                    return;
                }

                busy(open, true);
                kit.api('ws_note_lock_open', { pin: pin.input.value, note_id: noteId || 0 }).then(opened, function (error) {
                    refused(error);
                    pin.input.value = '';
                    pin.input.focus();
                }).then(function () {
                    busy(open, false);
                });
            };

            forgot.addEventListener('click', function () {
                busy(forgot, true);
                kit.api('ws_note_lock_code', {}).then(function (data) {
                    remember(data);
                    drawCode(data.email || (state && state.email) || '');
                }, refused).then(function () {
                    busy(forgot, false);
                });
            });

            if (state && state.wait) {
                say(kit.t('nlock_wait', String(waitMinutes(state.wait))));
            } else {
                say('');
            }

            window.setTimeout(function () { pin.input.focus(); }, 60);
        }

        // The code from the e-mail and a new PIN twice.
        function drawCode(email) {
            kit.clear(formNode);

            var sent = kit.el('p', 'small mb-1', kit.t('nlock_code_sent', email));
            var codeWrap = kit.el('div', 'mb-4');
            var codeName = kit.el('label', 'form-label small', kit.t('nlock_code'));
            var code = kit.el('input', 'form-control form-control-sm');
            var pin = pinField(kit, kit.t('nlock_pin_new'), 'ws-nb-lock-pane-new');
            var pin2 = pinField(kit, kit.t('nlock_pin_again'), 'ws-nb-lock-pane-new2');
            var save = kit.button('btn btn-sm btn-solid theme-primary rounded-pill px-5', kit.t('nlock_new_pin_save'), 'bi-key');
            var back = kit.button('btn btn-sm btn-text theme-secondary', kit.t('nlock_back_to_pin'));

            sent.setAttribute('role', 'status');
            code.id = 'ws-nb-lock-pane-code';
            code.inputMode = 'numeric';
            code.autocomplete = 'one-time-code';
            code.maxLength = 6;
            codeName.htmlFor = code.id;
            codeWrap.appendChild(codeName);
            codeWrap.appendChild(code);
            save.type = 'submit';
            back.type = 'button';

            formNode.appendChild(sent);
            formNode.appendChild(codeWrap);
            formNode.appendChild(pin);
            formNode.appendChild(pin2);
            formNode.appendChild(message);
            formNode.appendChild(save);
            formNode.appendChild(back);
            say('');

            formNode.onsubmit = function (event) {
                event.preventDefault();
                busy(save, true);
                kit.api('ws_note_lock_change', { code: code.value.trim(), pin: pin.input.value, pin2: pin2.input.value }).then(function (data) {
                    kit.toast(kit.t('nlock_pin_changed'));
                    opened(data);
                }, refused).then(function () {
                    busy(save, false);
                });
            };

            back.addEventListener('click', drawPin);
            window.setTimeout(function () { code.focus(); }, 60);
        }

        drawPin();

        return box;
    }

    // ── the time-out ──

    // Every half minute: the note open behind the lock, with nothing done
    // in it for the chosen time, is shut again.
    function watch(ctx) {
        window.clearInterval(watchTimer);

        ['keydown', 'pointerdown', 'input'].forEach(function (name) {
            document.addEventListener(name, used, true);
        });

        watchTimer = window.setInterval(function () {
            var note = ctx.current();

            if (!state || !state.set_up || !note || !note.id || !behind(note) || note.lock_closed) {
                return;
            }

            if (expired(state.minutes, lastUse, Date.now())) {
                lockNow(ctx);
            }
        }, 30000);
    }

    window.PGWsNoteLock = {
        load: load,
        state: function () { return state; },
        remember: remember,
        used: used,
        button: toolbarButton,
        noteItems: noteItems,
        groupItems: groupItems,
        mark: mark,
        pane: pane,
        watch: watch,
        behind: behind,
        pure: {
            behind: behind,
            pinOk: pinOk,
            expired: expired,
            waitMinutes: waitMinutes,
            EVERY_TIME: EVERY_TIME,
            UNTIL_SIGN_OUT: UNTIL_SIGN_OUT
        }
    };
}());
