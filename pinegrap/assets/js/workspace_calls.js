/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - calls, the browser's side: the voice and video buttons in a
 * channel's head (and its Join button while a call is on there), the card
 * that rings the other person of a conversation of two (Accept, Decline),
 * and the call window that stays at the bottom of the screen while the
 * person moves between channels: a grid of up to four, the one speaking
 * marked; mute, the camera, sharing one's screen (several members at once,
 * one share each), the microphone, camera and speaker in use (remembered
 * in this browser for the next call, with whether the person was muted or
 * had the camera off), raising a hand, hang up. A tile shows a raised hand
 * and a weak connection; the person speaking while muted is told so.
 * Another member's shared screen is shown large in the same window (the
 * stage), the tiles beside it, or under it on a narrow screen, the other
 * shares among them as tiles a click puts on the stage. A click on a tile
 * pins it large the same way (one's own too). The window's head can make
 * it larger or smaller at any time (remembered with the choices) or full
 * screen; its menu hides one's own picture on this screen.
 *
 * Sound and picture go from browser to browser (WebRTC). The site carries
 * only who is in the call and the browsers' connection messages
 * (includes/workspace/calls.php): the window reads them with ws_call_sync
 * every second and sends its own with ws_call_signal. Every two members
 * hold one connection (a mesh); of each pair the one who joined later (the
 * higher member id) makes the offer, so both never offer at once. Every
 * connection carries a line for a shared screen from its first offer, so a
 * share only puts the screen's track on it and needs no new offer; the
 * server says who shares (the call part's sharers). assets/js/workspace.js
 * draws the head and hands the rings of its look (ws_sync's rings) in here
 * with its own helpers. The guest page (assets/js/workspace_guest.js) shows
 * its room's running call with a Join button only (joinButton()): a guest
 * never starts a call.
 *
 * Served minified inside assets/js/workspace.min.js and
 * assets/js/workspace_guest.min.js (pg_js_bundles(), includes/fn/output.php);
 * after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // How often the window reads its call (milliseconds), how often it says
    // it is still there (the server ends a call silent for 2 minutes), and
    // how long a broken connection may last before the call is given up.
    var LOOK_EVERY = 1000;
    var PING_EVERY = 30000;
    var LOST_AFTER = 30000;

    // A connection broken this long (or failed) is restarted by its offerer
    // (an ICE restart finds a new route, as after a phone moves from Wi-Fi to
    // its mobile network), again at most every RESTART_EVERY while it stays
    // broken (milliseconds).
    var RESTART_AFTER = 2000;
    var RESTART_EVERY = 5000;

    // How long the call's reads may fail on the network (offline, a network
    // being changed) before the call is given up here (milliseconds); the
    // server keeps a member for 90 seconds without a word from it.
    var READ_LOST_AFTER = 45000;

    // How long a new share's own reads may still say nobody shares, read
    // before it was written (milliseconds).
    var SHARE_SETTLE = 5000;

    // A group's call stays while the person waits alone in it, for others
    // to come or come back, up to this long (milliseconds).
    var ALONE_FOR = 300000;

    // How often the voices are measured, how loud is speaking (0-1), and
    // how long the mark stays after the voice drops (milliseconds).
    var LEVEL_EVERY = 150;
    var SPEAKING_LEVEL = 0.04;
    var SPEAKING_HOLD = 600;

    // The browser's own key for the person's last choices: the microphone,
    // camera and speaker, and whether they were muted or had the camera
    // off. Device ids mean something only in this browser, so they are
    // never sent to the site.
    var CHOICES_KEY = 'pg_ws_call_choices';

    // How often each connection's received packets are counted (getStats),
    // and the share of them lost that marks its tile as weak (0-1).
    var STATS_EVERY = 2000;
    var WEAK_LOSS = 0.05;

    // How long "You are muted" stays after the muted voice drops
    // (milliseconds).
    var MUTED_HINT_HOLD = 3000;

    // The one call of this screen, the rings shown and the ones answered.
    var call = null;
    var shown = {};
    var answered = {};
    var ringTone = { ctx: null, timer: null };
    var hooks = null;

    // The open channel's head: its Join button, shown while a call is on.
    var head = null;

    // ── Pure parts (tests/workspace_calls_screen_test.php) ──

    // Calls need a secure context (getUserMedia) and WebRTC.
    function supported() {
        return !!(window.RTCPeerConnection && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    // Does a channel's head carry the call buttons? A channel of two people
    // or more the viewer is a member of, open in its current version; a
    // guest room with one of the team in it too, as its guest joins from
    // the guest page.
    function callable(channel, view) {
        if (!channel || !view || !view.calls || view.era || view.thread) {
            return false;
        }

        if (!channel.joined || channel.archived || (['mailbox', 'thread'].indexOf(channel.kind) >= 0)) {
            return false;
        }

        return (channel.members || []).length >= ((channel.kind === 'guest') ? 1 : 2);
    }

    // Is a channel's call a group's? More than two members, or a guest
    // room (its guest is not among the members and is never rung): nobody
    // is rung, the call is joined from the channel's head or the guest
    // page, and it stays while one waits alone.
    function isGroup(channel) {
        return !!channel && ((channel.kind === 'guest') || ((channel.members || []).length > 2));
    }

    // The head's Join button against the open channel's call (the look's
    // call part): hidden without a call or in the window that is in it; full
    // at max members. A person in the call in another window gets Join here
    // (here), which takes that window's place over: one person is one
    // member, never two.
    function joinState(part, current, max) {
        var live = part && part.call;

        if (!live || (live.id === current)) {
            return { show: false, full: false, here: false, count: 0, callId: 0, kind: '' };
        }

        var count = (live.members || []).length;
        var here = !!part.member_id;

        return { show: true, full: !here && (count >= (max || 4)), here: here, count: count, callId: live.id, kind: live.kind };
    }

    // Is a connection's ICE restart due? Only its offerer restarts it (one
    // offer a pair): failed, or disconnected for RESTART_AFTER, or any
    // connection once the device's network changed (network), spaced by
    // RESTART_EVERY.
    function restartDue(state, offerer, lostAt, lastRestart, now, network) {
        if (!offerer || (lastRestart && ((now - lastRestart) < RESTART_EVERY))) {
            return false;
        }

        if (network || (state === 'failed')) {
            return true;
        }

        return (state === 'disconnected') && !!lostAt && ((now - lostAt) >= RESTART_AFTER);
    }

    // What a window's read says of its place in the call: '' it goes on;
    // another window of the person took the place (call_moved); the server
    // took it out as unheard while the call goes on (call_lost); the call is
    // over (call_ended, or call_unanswered when nobody ever came).
    function placeOutcome(part, callId, memberId, ever) {
        if (part && part.call && (part.call.id === callId)) {
            if (part.member_id === memberId) {
                return '';
            }

            return part.member_id ? 'call_moved' : 'call_lost';
        }

        return ever ? 'call_ended' : 'call_unanswered';
    }

    // Does a failed read end the call here? A refusal from the server (the
    // call ended, or is no longer readable) at once; a read lost on the
    // network (fetch's TypeError) only after READ_LOST_AFTER without one
    // read through.
    function readGiveUp(error, lastRead, now) {
        if (!error || (error.name !== 'TypeError')) {
            return true;
        }

        return (now - lastRead) > READ_LOST_AFTER;
    }

    // What becomes of a call left with nobody else in it: a conversation of
    // two ends once the other one was there; a group's waits, up to
    // ALONE_FOR; nobody coming while a call of two rings ends it unanswered.
    function aloneOutcome(group, ever, aloneSince, now, ring) {
        if (group) {
            return ((now - aloneSince) > ALONE_FOR) ? 'call_ended' : '';
        }

        if (ever) {
            return 'call_ended';
        }

        return ((now - aloneSince) > ring * 1000) ? 'call_unanswered' : '';
    }

    // A voice's loudness, 0-1: the root mean square of an analyser's
    // time-domain bytes (128 is silence).
    function level(bytes) {
        var sum = 0;

        if (!bytes || !bytes.length) {
            return 0;
        }

        for (var i = 0; i < bytes.length; i++) {
            var value = (bytes[i] - 128) / 128;

            sum += value * value;
        }

        return Math.sqrt(sum / bytes.length);
    }

    // Is this one speaking? Loud now, or loud a moment ago, so the mark
    // does not flicker between words.
    function speaking(loudness, loudAt, now) {
        return (loudness >= SPEAKING_LEVEL) || (!!loudAt && ((now - loudAt) < SPEAKING_HOLD));
    }

    // The marks the keep-alive sends: a share and a raised hand are renewed
    // with it (the server lets them lapse otherwise), a plain ping when
    // neither is on.
    function keepAlive(sharing, hand) {
        var kinds = [];

        if (sharing) {
            kinds.push('share');
        }

        if (hand) {
            kinds.push('hand');
        }

        return kinds.length ? kinds : ['ping'];
    }

    // The hand button's label and icon, raised or not.
    function handState(on) {
        return on ? { label: 'call_hand_lower', icon: 'bi-hand-index-fill', pressed: 'true' }
            : { label: 'call_hand_raise', icon: 'bi-hand-index', pressed: 'false' };
    }

    // A connection's received and lost packets, summed over its incoming
    // lines, from the entries of a getStats() report.
    function inbound(entries) {
        var counts = { received: 0, lost: 0 };

        (entries || []).forEach(function (entry) {
            if (entry && (entry.type === 'inbound-rtp')) {
                counts.received += Number(entry.packetsReceived) || 0;
                counts.lost += Number(entry.packetsLost) || 0;
            }
        });

        return counts;
    }

    // The share of packets lost between two counts (0-1); 0 when there is
    // no earlier count or nothing came.
    function lossRatio(before, after) {
        if (!before || !after) {
            return 0;
        }

        var lost = Math.max(0, after.lost - before.lost);
        var received = Math.max(0, after.received - before.received);

        return (lost + received) ? lost / (lost + received) : 0;
    }

    // Is a connection weak: broken while its route is restarted, or losing
    // WEAK_LOSS of its packets or more?
    function weak(state, ratio) {
        return (state === 'disconnected') || (state === 'failed') || (ratio >= WEAK_LOSS);
    }

    // Is "You are muted" shown: muted, and the voice heard a moment ago.
    function mutedHint(muted, talkAt, now) {
        return !!muted && !!talkAt && ((now - talkAt) < MUTED_HINT_HOLD);
    }

    // Does this member make the offer to that one? The later joiner does.
    function offers(mine, theirs) {
        return mine > theirs;
    }

    // The call's other members against the connections held: who came,
    // who went.
    function peersDiff(members, me, known) {
        var now = [];

        (members || []).forEach(function (member) {
            if (member.member_id !== me) {
                now.push(member.member_id);
            }
        });

        return {
            add: now.filter(function (id) { return known.indexOf(id) < 0; }),
            remove: known.filter(function (id) { return now.indexOf(id) < 0; })
        };
    }

    // The rings of a look still to be shown: not shown already, not
    // answered here, not the call this screen is in.
    function ringsToShow(rings, seen, done, current) {
        return (rings || []).filter(function (ring) {
            return !seen[ring.call_id] && !done[ring.call_id] && (ring.call_id !== current);
        });
    }

    // The shown rings no longer in the look: the caller gave up, or it was
    // answered elsewhere.
    function ringsGone(rings, seen) {
        var still = (rings || []).map(function (ring) { return ring.call_id; });

        return Object.keys(seen).map(Number).filter(function (id) { return still.indexOf(id) < 0; });
    }

    // A call's length: m:ss, or h:mm:ss past an hour.
    function clock(seconds) {
        seconds = Math.max(0, Math.floor(seconds));

        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var rest = seconds % 60;
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };

        return hours ? hours + ':' + pad(minutes) + ':' + pad(rest) : minutes + ':' + pad(rest);
    }

    // What is asked of the devices: the chosen ones where there is a choice.
    // A remembered device (soft) is only preferred, so one no longer there
    // gives way to the browser's default instead of failing the call; a
    // device picked in the window is asked for exactly.
    function mediaAsk(kind, devices, soft) {
        var audio = { echoCancellation: true, noiseSuppression: true, autoGainControl: true };
        var pick = function (id) { return soft ? { ideal: id } : { exact: id }; };

        if (devices && devices.audio) {
            audio.deviceId = pick(devices.audio);
        }

        var video = false;

        if (kind === 'video') {
            video = { width: { ideal: 1280 }, height: { ideal: 720 } };

            if (devices && devices.video) {
                video.deviceId = pick(devices.video);
            }
        }

        return { audio: audio, video: video };
    }

    // The remembered choices from their stored text: anything missing,
    // broken or of the wrong type reads as no choice.
    function choicesRead(text) {
        var read = null;
        var out = { audio: '', video: '', speaker: '', muted: false, cameraOff: false, size: '' };

        try {
            read = JSON.parse(text || 'null');
        } catch (error) {
            read = null;
        }

        if (!read || (typeof read !== 'object')) {
            return out;
        }

        ['audio', 'video', 'speaker'].forEach(function (name) {
            if ((typeof read[name] === 'string') && (read[name].length <= 512)) {
                out[name] = read[name];
            }
        });
        out.muted = read.muted === true;
        out.cameraOff = read.cameraOff === true;
        // The window's size: made larger or smaller, else '' (large only
        // while something is on its stage).
        out.size = ((read.size === 'large') || (read.size === 'small')) ? read.size : '';

        return out;
    }

    // The choices with one change made (only the known names are taken).
    function choicesWith(choices, change) {
        var out = choicesRead(JSON.stringify(choices || {}));

        Object.keys(change || {}).forEach(function (name) {
            if (!Object.prototype.hasOwnProperty.call(out, name)) {
                return;
            }

            out[name] = (typeof out[name] === 'boolean') ? (change[name] === true) : String(change[name] || '');
        });

        // Read again, so an unknown size reads as none.
        return choicesRead(JSON.stringify(out));
    }

    // The speaker to show as chosen among the outputs: the remembered one
    // while it is there, else the browser's default ('').
    function speakerOf(outputs, chosen) {
        return (chosen && (outputs || []).some(function (device) { return device.deviceId === chosen; })) ? chosen : '';
    }

    // The mid of a connection's screen line, among its video lines' mids:
    // the camera's line comes first in a video call, so the screen's is the
    // second there and the only one in a voice call; -1 before it is there.
    function screenSlot(mids, kind) {
        var sorted = (mids || []).map(Number).sort(function (a, b) { return a - b; });
        var at = (kind === 'video') ? 1 : 0;

        return (sorted.length > at) ? sorted[at] : -1;
    }

    // The share button against the call's sharers: shown where the browser
    // can share a screen, pressed while this member shares; others' shares
    // never hold it (several at once, one each).
    function shareState(sharers, me, can) {
        if (!can) {
            return { show: false, on: false };
        }

        return { show: true, on: (sharers || []).indexOf(me) >= 0 };
    }

    // What the window shows: on its stage the share the viewer chose
    // (chosen), else the first other one, or a member's tile pinned large
    // (view.pin: 0 one's own, a member id, -1 none; a pin of a member no
    // longer there, or of one's own hidden picture, is none), which puts
    // every share on the rail; the notice while this member shares; and
    // the labels of the head's size and full screen buttons. size is the
    // window's chosen size ('large', 'small', or '': large while something
    // is on the stage); full screen is always large. One's own share is on
    // neither stage nor rail. view: { pin, tiles (the other members' ids),
    // selfHidden }.
    function stageState(sharers, me, size, can, full, chosen, view) {
        var see = view || {};
        var tiles = (see.tiles || []).map(Number);
        var pin = (typeof see.pin === 'number') ? see.pin : -1;

        if (((pin === 0) && see.selfHidden) || ((pin > 0) && (tiles.indexOf(pin) < 0))) {
            pin = -1;
        }

        var others = (sharers || []).filter(function (id) { return id !== me; });
        var main = (pin >= 0) ? 0 : ((others.indexOf(chosen) >= 0) ? chosen : (others[0] || 0));
        var showing = !!main || (pin >= 0);
        var chosenLarge = (size === 'large') || ((size !== 'small') && showing);
        var share = shareState(sharers, me, can);

        return {
            classes: { 'is-showing': showing, 'is-stage': chosenLarge || !!full, 'is-pinned': pin >= 0, 'is-self-sharing': share.on },
            share: share,
            main: main,
            pin: pin,
            rail: others.filter(function (id) { return id !== main; }),
            size: chosenLarge ? { label: 'call_smaller', icon: 'bi-arrows-angle-contract', next: 'small' } : { label: 'call_larger', icon: 'bi-arrows-angle-expand', next: 'large' },
            full: full ? { label: 'call_full_exit', icon: 'bi-fullscreen-exit' } : { label: 'call_full_screen', icon: 'bi-fullscreen' }
        };
    }

    // Whether a click on a link would take this page elsewhere and so end
    // the call: a link to another address of the site or outside it, in
    // this tab. A hash on the same address, a script or mail link, a
    // download and a link already aimed at another tab or window stay.
    // link: { raw (the attribute), href (resolved), target, download };
    // here: { origin, pathname, search }.
    function leavesPage(link, here) {
        var raw = String((link && link.raw) || '').trim();
        var target = String((link && link.target) || '').toLowerCase();
        var url;

        if (!raw || (raw.charAt(0) === '#') || /^(javascript|mailto|tel|sms|data|blob):/i.test(raw)) {
            return false;
        }

        if (link.download || (target && (target !== '_self') && (target !== '_top') && (target !== '_parent'))) {
            return false;
        }

        try {
            url = new URL(String(link.href || ''));
        } catch (error) {
            return false;
        }

        if ((url.protocol !== 'http:') && (url.protocol !== 'https:')) {
            return false;
        }

        return !((url.origin === here.origin) && (url.pathname === here.pathname) && (url.search === here.search));
    }

    // ── The ring ──

    function h() {
        return hooks.helpers;
    }

    function t() {
        return h().t.apply(null, arguments);
    }

    function makeButton(className, label, iconName, title) {
        return hooks.helpers.button(className, label, iconName, title);
    }

    // The remembered choices; a browser that keeps nothing (a private
    // window, site data blocked) remembers nothing and the call goes on.
    function choicesLoad() {
        try {
            return choicesRead(window.localStorage.getItem(CHOICES_KEY));
        } catch (error) {
            return choicesRead('');
        }
    }

    function choicesSave(change) {
        try {
            window.localStorage.setItem(CHOICES_KEY, JSON.stringify(choicesWith(choicesLoad(), change)));
        } catch (error) {
            // Nothing kept: the next call starts with the browser's defaults.
        }
    }

    // Whether the browser can play a picture's sound on a chosen speaker.
    function sinkSupported() {
        return (typeof HTMLMediaElement !== 'undefined') && ('setSinkId' in HTMLMediaElement.prototype);
    }

    // Plays the element's sound on the speaker ('' the browser's default);
    // a speaker no longer there leaves it on the one it had.
    function sinkSet(video, speaker) {
        if (video && video.setSinkId) {
            video.setSinkId(speaker || '').catch(function () {});
        }
    }

    // Two short tones every two seconds while a card rings, when the
    // person's sound switch is on (the mention's switch).
    function ringStart() {
        if (ringTone.timer || !hooks.soundOn()) {
            return;
        }

        var Context = window.AudioContext || window.webkitAudioContext;

        if (!Context) {
            return;
        }

        var beep = function () {
            try {
                ringTone.ctx = ringTone.ctx || new Context();

                var ctx = ringTone.ctx;

                [[523, 0], [659, 0.25]].forEach(function (note) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    var at = ctx.currentTime + note[1];

                    osc.frequency.value = note[0];
                    gain.gain.setValueAtTime(0.0001, at);
                    gain.gain.exponentialRampToValueAtTime(0.12, at + 0.03);
                    gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.22);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(at);
                    osc.stop(at + 0.25);
                });
            } catch (error) {
                // A page not clicked yet may not sound; the card still shows.
            }
        };

        beep();
        ringTone.timer = setInterval(beep, 2000);
    }

    function ringStop() {
        clearInterval(ringTone.timer);
        ringTone.timer = null;
    }

    function ringHost() {
        var host = document.querySelector('.ws-call-rings');

        if (!host) {
            host = h().el('div', 'ws-call-rings');
            host.setAttribute('aria-live', 'assertive');
            document.body.appendChild(host);
        }

        return host;
    }

    function ringDrop(callId) {
        var node = shown[callId];

        delete shown[callId];

        if (node && node.parentNode) {
            node.remove();
        }

        if (!Object.keys(shown).length) {
            ringStop();
        }
    }

    function ringCard(ring) {
        var el = h().el;
        var card = el('div', 'pg-card ws-call-ring');
        var text = el('div', 'ws-call-ring-text');

        card.setAttribute('role', 'alertdialog');
        text.appendChild(h().icon(ring.kind === 'video' ? 'bi-camera-video' : 'bi-telephone'));
        text.appendChild(el('span', '', t(ring.kind === 'video' ? 'call_ringing_video' : 'call_ringing', ring.caller)));
        card.setAttribute('aria-label', text.textContent);
        card.appendChild(text);

        var tools = el('div', 'ws-call-ring-tools');
        var decline = makeButton('pg-button pg-button-danger pg-button-sm', t('call_decline'), 'bi-telephone-x');
        var accept = makeButton('pg-button pg-button-fill pg-button-sm', t('call_accept'), 'bi-telephone');

        decline.addEventListener('click', function () {
            answered[ring.call_id] = true;
            ringDrop(ring.call_id);
            h().api('ws_call_decline', { call_id: ring.call_id }).catch(function () {});
        });

        accept.addEventListener('click', function () {
            answered[ring.call_id] = true;
            ringDrop(ring.call_id);
            begin(ring.channel_id, ring.kind, ring.call_id, ring.caller, false);
        });

        tools.appendChild(decline);
        tools.appendChild(accept);
        card.appendChild(tools);

        return card;
    }

    // The rings of the channel screen's look (ws_sync).
    function rings(list, given) {
        hooks = given;

        ringsGone(list, shown).forEach(ringDrop);

        if (!supported()) {
            return;
        }

        ringsToShow(list, shown, answered, call ? call.id : 0).forEach(function (ring) {
            // In a call already: this one is not shown; the caller reads
            // that it was not answered.
            if (call) {
                return;
            }

            shown[ring.call_id] = ringCard(ring);
            ringHost().appendChild(shown[ring.call_id]);
            ringStart();
        });
    }

    // ── The call ──

    // The head's two buttons and its Join button, or null where the channel
    // holds no call.
    function headButtons(channel, view, given) {
        hooks = given;
        head = null;

        if (!supported() || !callable(channel, view)) {
            return null;
        }

        // Kept in a narrow (phone) head, where the head's other tools move
        // into the channel menu.
        var box = h().el('span', 'ws-call-head');
        var starts = [];
        var group = isGroup(channel);

        [['voice', 'bi-telephone', 'call_voice'], ['video', 'bi-camera-video', 'call_video']].forEach(function (one) {
            var node = makeButton('pg-icon-button', '', one[1], t(one[2]));

            node.setAttribute('aria-label', t(one[2]));
            node.addEventListener('click', function () {
                begin(channel.id, one[0], 0, callName(channel), group);
            });
            box.appendChild(node);
            starts.push(node);
        });

        var join = makeButton('pg-button pg-button-fill pg-button-sm ws-call-join', t('call_join'), 'bi-telephone-plus');

        join.hidden = true;
        join.addEventListener('click', function () {
            if (head && head.state.callId) {
                begin(channel.id, head.state.kind, head.state.callId, callName(channel), group, head.state.here);
            }
        });
        box.appendChild(join);

        head = { channelId: channel.id, join: join, starts: starts, state: joinState(null, 0, 0) };

        return box;
    }

    // The Join button alone, for a page that joins a running call and never
    // starts one (the guest page); channelCall() shows and labels it.
    function joinButton(channelId, name, given) {
        hooks = given;
        head = null;

        if (!supported()) {
            return null;
        }

        var box = h().el('span', 'ws-call-head');
        var join = makeButton('pg-button pg-button-fill pg-button-sm ws-call-join', t('call_join'), 'bi-telephone-plus');

        join.hidden = true;
        join.addEventListener('click', function () {
            if (head && head.state.callId) {
                begin(channelId, head.state.kind, head.state.callId, name, true, head.state.here);
            }
        });
        box.appendChild(join);
        head = { channelId: channelId, join: join, starts: [], state: joinState(null, 0, 0) };

        return box;
    }

    // The name a call window carries: the other person of a conversation
    // of two, a group's channel name.
    function callName(channel) {
        if (isGroup(channel)) {
            return channel.name || '';
        }

        var other = (channel.members || []).filter(function (member) { return member.id !== hooks.me; })[0];

        return other ? (other.name || '') : (channel.name || '');
    }

    // A button's own words, its icon kept.
    function relabel(node, text) {
        var words = node.lastChild;

        if (words && (words.nodeType === 3)) {
            words.nodeValue = text;
        } else {
            node.appendChild(document.createTextNode(text));
        }

        node.title = text;
        node.setAttribute('aria-label', text);
    }

    // The open channel's call from its look (ws_sync's call part): the
    // head shows Join with how many are in it, or that it is full, in place
    // of the two start buttons.
    function channelCall(channelId, part, given) {
        hooks = given;

        if (!head || (head.channelId !== channelId) || !head.join.isConnected) {
            return;
        }

        // A call this window is starting has no id until its start answers.
        var current = call ? (call.id || ((call.channelId === channelId && part && part.call) ? part.call.id : 0)) : 0;
        var state = joinState(part, current, hooks.max);

        head.state = state;
        head.join.hidden = !state.show;
        head.join.disabled = state.full;
        head.join.classList.toggle('pg-button-fill', !state.here);
        head.starts.forEach(function (node) { node.hidden = state.show || !!(part && part.member_id); });

        if (state.show) {
            relabel(head.join, state.here ? t('call_join_here') : (state.full ? t('call_full') : t('call_join_count', state.count, hooks.max || 4)));
            head.join.title = state.here ? t('call_elsewhere') : head.join.title;
        }
    }

    // Starts a call (callId 0) or joins one, once the devices are given;
    // again takes over the place the person holds in another window.
    function begin(channelId, kind, callId, name, group, again) {
        if (call) {
            h().toast(t('call_busy'), 'warning');
            return;
        }

        if (!supported()) {
            h().toast(t('call_unsupported'), 'warning');
            return;
        }

        // The devices and the mute and camera of the person's last call in
        // this browser.
        var chosen = choicesLoad();

        // Held at once, so a second click does not start a second call.
        call = { id: callId, channelId: channelId, kind: kind, name: name, group: !!group, memberId: 0, after: 0, peers: {}, names: {}, outgoing: !callId, since: Date.now(), aloneSince: Date.now(), devices: { audio: chosen.audio, video: chosen.video }, speaker: chosen.speaker, muted: false, cameraOff: false, size: chosen.size, pin: -1, selfHidden: false, ever: false, sending: Promise.resolve() };
        // Added only for the call's length: a beforeunload listener keeps
        // some browsers from holding the page in their back-forward cache.
        window.addEventListener('beforeunload', askBeforeLeaving);

        var mine = call;

        navigator.mediaDevices.getUserMedia(mediaAsk(kind, mine.devices, true)).then(function (stream) {
            mine.stream = stream;
            // Silent from the first moment when the last call ended muted.
            stream.getAudioTracks().forEach(function (track) { track.enabled = !chosen.muted; });
            stream.getVideoTracks().forEach(function (track) { track.enabled = !chosen.cameraOff; });

            var ask = { channel_id: channelId, call_id: callId };

            if (again) {
                ask.again = 1;
            }

            return callId ? h().api('ws_call_join', ask)
                : h().api('ws_call_start', { channel_id: channelId, kind: kind });
        }).then(function (result) {
            if (call !== mine) {
                return;
            }

            mine.id = result.call_id;
            mine.memberId = result.member_id;
            // The member's own servers, the relay's credentials among them.
            mine.ice = result.ice || null;
            mine.readAt = Date.now();
            draw();

            if (chosen.muted) {
                mute(true);
            }

            if (chosen.cameraOff && (mine.kind === 'video')) {
                camera(true);
            }

            look();
            // The keep-alive renews a share and a raised hand too.
            mine.pinger = setInterval(function () {
                keepAlive(mine.sharing, mine.hand).forEach(function (kind) { send(0, kind, ''); });
            }, PING_EVERY);
        }).catch(function (error) {
            var denied = error && (error.name === 'NotAllowedError' || error.name === 'NotFoundError' || error.name === 'SecurityError');

            finish(mine, false);
            h().toast(denied ? t('call_denied') : ((error && error.message) || t('call_ended')), 'danger');
        });
    }

    // Signals leave one after another, in the order they were made.
    function send(to, kind, body) {
        var mine = call;

        if (!mine || !mine.id) {
            return;
        }

        mine.sending = mine.sending.then(function () {
            return h().api('ws_call_signal', { call_id: mine.id, to_member_id: to, kind: kind, body: body, member_id: mine.memberId });
        }).catch(function () {});
    }

    function look() {
        var mine = call;

        if (!mine) {
            return;
        }

        h().api('ws_call_sync', { call_id: mine.id, call_after: mine.after }).then(function (data) {
            if (call === mine) {
                mine.readAt = Date.now();
                heard(mine, data.call);
            }
        }).catch(function (error) {
            // The call is gone (ended, or no longer readable); a read lost
            // on the network is tried again a while.
            if ((call === mine) && readGiveUp(error, mine.readAt, Date.now())) {
                hangUp((error && (error.name === 'TypeError')) ? 'call_lost' : (mine.ever ? 'call_ended' : 'call_unanswered'));
            }
        }).then(function () {
            if (call === mine) {
                mine.timer = setTimeout(look, LOOK_EVERY);
            }
        });
    }

    function heard(mine, part) {
        var outcome = placeOutcome(part, mine.id, mine.memberId, mine.ever);

        if (outcome) {
            // Its place is gone: nothing is left to leave on the server.
            finish(mine, outcome);
            return;
        }

        (part.call.members || []).forEach(function (member) {
            mine.names[member.member_id] = member.name || '';
        });

        mine.sharers = (part.call.sharers || []).map(Number);
        mine.hands = part.call.hands || [];

        // The server no longer holds this member's share (it lapsed): the
        // screen stops. A read sent before the share was written is not
        // taken for that.
        if (mine.sharing && (mine.sharers.indexOf(mine.memberId) < 0)) {
            if ((Date.now() - mine.sharedAt) > SHARE_SETTLE) {
                shareStop(mine, false);
            } else {
                mine.sharers.push(mine.memberId);
            }
        }

        shareDraw(mine);

        var diff = peersDiff(part.call.members, mine.memberId, Object.keys(mine.peers).map(Number));

        diff.add.forEach(function (id) {
            mine.ever = true;
            peer(mine, id, offers(mine.memberId, id));
        });
        diff.remove.forEach(function (id) {
            peerClose(mine, id);
        });

        (part.signals || []).forEach(function (signal) {
            mine.after = Math.max(mine.after, signal.id);
            signalHeard(mine, signal);
        });

        // Alone in it: a call of two is over once the other one left, or
        // when nobody came while it rang; a group's waits a while.
        if (Object.keys(mine.peers).length) {
            mine.aloneSince = Date.now();
        } else if (mine.ever || mine.outgoing || mine.group) {
            var alone = aloneOutcome(mine.group, mine.ever, mine.ever ? mine.aloneSince : mine.since, Date.now(), hooks.ring || 45);

            if (alone) {
                hangUp(alone);
                return;
            }
        }

        names();
        marksDraw(mine);
        stageDraw(mine);
        status();
    }

    function peer(mine, id, offer) {
        var pc = new RTCPeerConnection({ iceServers: mine.ice || hooks.ice || [] });
        var held = { id: id, pc: pc, queue: [], lostAt: 0, restartAt: 0, offerer: !!offer, tile: tile(mine, id) };

        mine.peers[id] = held;
        mine.stream.getTracks().forEach(function (track) {
            pc.addTrack(track, mine.stream);
        });

        // The screen's line, last, in every offer; the answerer finds it in
        // the offer (signalHeard).
        if (offer) {
            held.slot = pc.addTransceiver('video', { direction: 'sendrecv' });
            screenOn(held, mine.screenTrack);
        }

        pc.onicecandidate = function (event) {
            if (event.candidate) {
                send(id, 'candidate', JSON.stringify(event.candidate));
            }
        };

        pc.ontrack = function (event) {
            var stream = event.streams && event.streams[0];

            // The screen's line carries no stream of the member's own.
            if ((held.slot && (event.transceiver === held.slot)) || !stream) {
                held.screen = new MediaStream([event.track]);
                stageDraw(mine);
                return;
            }

            if (held.tile && stream && (held.tile.video.srcObject !== stream)) {
                held.tile.video.srcObject = stream;
                listen(mine, held.tile, stream);
            }
        };

        pc.onconnectionstatechange = function () {
            var state = pc.connectionState;

            if (state === 'connected') {
                held.lostAt = 0;
                mine.connectedAt = mine.connectedAt || Date.now();
            } else if ((state === 'failed') || (state === 'disconnected')) {
                held.lostAt = held.lostAt || Date.now();
            }

            status();
        };

        if (offer) {
            pc.createOffer().then(function (description) {
                return pc.setLocalDescription(description);
            }).then(function () {
                send(id, 'offer', JSON.stringify(pc.localDescription));
            }).catch(function () {});
        }

        return held;
    }

    // An ICE restart of one connection, by its offerer: a new offer that
    // gathers new routes; the answerer answers it as any offer. Not while an
    // offer is still unanswered.
    function restart(mine, held) {
        var pc = held.pc;

        if (pc.signalingState !== 'stable') {
            return;
        }

        held.restartAt = Date.now();

        pc.createOffer({ iceRestart: true }).then(function (description) {
            return pc.setLocalDescription(description);
        }).then(function () {
            send(held.id, 'offer', JSON.stringify(pc.localDescription));
        }).catch(function () {});
    }

    // Restarts the connections that are due; network: the device's network
    // changed, so every connection this window offers looks for a new route.
    function restartAll(mine, network) {
        var now = Date.now();

        Object.keys(mine.peers).forEach(function (id) {
            var held = mine.peers[id];

            if (restartDue(held.pc.connectionState, held.offerer, held.lostAt, held.restartAt, now, network)) {
                restart(mine, held);
            }
        });
    }

    function peerClose(mine, id) {
        var held = mine.peers[id];

        delete mine.peers[id];

        if (held) {
            try { held.pc.close(); } catch (error) { /* closed already */ }

            tileDrop(mine, held.tile);
        }
    }

    function signalHeard(mine, signal) {
        var held = mine.peers[signal.from_member_id] || peer(mine, signal.from_member_id, false);
        var pc = held.pc;
        var body = null;

        try {
            body = JSON.parse(signal.body);
        } catch (error) {
            return;
        }

        var drain = function () {
            held.queue.splice(0).forEach(function (candidate) {
                pc.addIceCandidate(candidate).catch(function () {});
            });
        };

        if (signal.kind === 'offer') {
            pc.setRemoteDescription(body).then(function () {
                drain();
                screenSlotTake(mine, held);
                return pc.createAnswer();
            }).then(function (description) {
                return pc.setLocalDescription(description);
            }).then(function () {
                send(signal.from_member_id, 'answer', JSON.stringify(pc.localDescription));
            }).catch(function () {});
        } else if (signal.kind === 'answer') {
            pc.setRemoteDescription(body).then(drain).catch(function () {});
        } else if (signal.kind === 'candidate') {
            // A candidate may come before the description it belongs to.
            if (pc.remoteDescription) {
                pc.addIceCandidate(body).catch(function () {});
            } else {
                held.queue.push(body);
            }
        }
    }

    // The answerer's screen line: the offer's, made to send too, so this
    // member can share without a new offer.
    function screenSlotTake(mine, held) {
        if (held.slot) {
            return;
        }

        var videos = held.pc.getTransceivers().filter(function (one) {
            return (one.mid !== null) && one.receiver && one.receiver.track && (one.receiver.track.kind === 'video');
        });
        var mid = screenSlot(videos.map(function (one) { return one.mid; }), mine.kind);
        var slot = videos.filter(function (one) { return Number(one.mid) === mid; })[0];

        if (slot) {
            slot.direction = 'sendrecv';
            held.slot = slot;
            screenOn(held, mine.screenTrack);
        }
    }

    // Puts the screen's track (or none) on one connection's screen line.
    function screenOn(held, track) {
        if (held.slot && held.slot.sender) {
            held.slot.sender.replaceTrack(track || null).catch(function () {});
        }
    }

    // Starts sharing: the browser lets the person choose a screen, a window
    // or a tab; the server is told before the screen goes out.
    function shareStart(mine) {
        if (mine.sharing || mine.shareAsking) {
            return;
        }

        mine.shareAsking = true;

        navigator.mediaDevices.getDisplayMedia({ video: true, audio: false }).then(function (stream) {
            var track = stream.getVideoTracks()[0];

            if ((call !== mine) || !track) {
                stream.getTracks().forEach(function (one) { one.stop(); });
                mine.shareAsking = false;
                return;
            }

            return h().api('ws_call_signal', { call_id: mine.id, to_member_id: 0, kind: 'share', body: '', member_id: mine.memberId }).then(function () {
                mine.shareAsking = false;

                if (call !== mine) {
                    track.stop();
                    return;
                }

                if ('contentHint' in track) {
                    track.contentHint = 'detail';
                }

                mine.sharing = true;
                mine.sharedAt = Date.now();
                mine.screenTrack = track;
                mine.sharers = (mine.sharers || []).filter(function (id) { return id !== mine.memberId; }).concat([mine.memberId]);
                // The browser's own "Stop sharing" ends it as the button does.
                track.onended = function () { shareStop(mine, true); };
                Object.keys(mine.peers).forEach(function (id) { screenOn(mine.peers[id], track); });
                shareDraw(mine);
            }, function (error) {
                mine.shareAsking = false;
                track.stop();
                if (error && error.message) {
                    h().toast(error.message, 'warning');
                }
            });
        }).catch(function () {
            // The person closed the browser's chooser: nothing is shared.
            mine.shareAsking = false;
        });
    }

    // Stops sharing; tell says the server is told (not when it ended it).
    function shareStop(mine, tell) {
        var track = mine.screenTrack;

        mine.sharing = false;
        mine.screenTrack = null;

        mine.sharers = (mine.sharers || []).filter(function (id) { return id !== mine.memberId; });

        Object.keys(mine.peers).forEach(function (id) { screenOn(mine.peers[id], null); });

        if (track) {
            track.onended = null;
            track.stop();
        }

        if (tell) {
            send(0, 'unshare', '');
        }

        shareDraw(mine);
    }

    function shareDraw(mine) {
        stageDraw(mine);
    }

    // The window against its stage: the share or the pinned tile on it,
    // the notice, the share button and the head's size and full screen.
    function stageDraw(mine) {
        if (!mine.node) {
            return;
        }

        var full = !!document.fullscreenElement && (document.fullscreenElement === mine.node);
        var state = stageState(mine.sharers || [], mine.memberId, mine.size || '', !!mine.shareButton, full, mine.watching || 0, {
            pin: (typeof mine.pin === 'number') ? mine.pin : -1,
            tiles: Object.keys(mine.peers).map(Number),
            selfHidden: !!mine.selfHidden
        });
        var held = state.main ? mine.peers[state.main] : null;
        var pinned = (state.pin > 0) ? (mine.peers[state.pin] && mine.peers[state.pin].tile) : ((state.pin === 0) ? mine.selfTile : null);
        var picture = (held && held.screen) || (pinned && pinned.video.srcObject) || null;

        // A pinned member gone, or one's own picture hidden: nothing pinned.
        mine.pin = state.pin;

        Object.keys(state.classes).forEach(function (name) {
            mine.node.classList.toggle(name, state.classes[name]);
        });
        mine.shareArea.hidden = !state.classes['is-showing'];
        mine.notice.hidden = !state.classes['is-self-sharing'];

        if (mine.shareVideo.srcObject !== picture) {
            mine.shareVideo.srcObject = picture;
        }

        // The pinned tile shows on the stage, so not in the rail; without a
        // picture (a voice call, a camera off) its person icon.
        [mine.selfTile].concat(Object.keys(mine.peers).map(function (id) { return mine.peers[id].tile; })).forEach(function (one) {
            if (one) {
                one.node.classList.toggle('is-pinned', one === pinned);
                one.pin.setAttribute('aria-pressed', (one === pinned) ? 'true' : 'false');
            }
        });
        mine.shareIcon.hidden = !pinned || ((mine.kind === 'video') && !pinned.node.classList.contains('is-camera-off'));
        mine.unpinButton.hidden = !pinned;

        if (held) {
            mine.shareName.textContent = t('call_sharing', mine.names[state.main] || '');
            mine.shareName.insertBefore(h().icon('bi-display'), mine.shareName.firstChild);
        } else if (pinned) {
            mine.shareName.textContent = (state.pin === 0) ? t('call_you') : (mine.names[state.pin] || '');
            mine.shareName.insertBefore(h().icon('bi-pin-angle-fill'), mine.shareName.firstChild);
        } else {
            mine.shareName.textContent = '';
        }
        iconSwap(mine.sizeButton, state.size.icon, t(state.size.label));
        mine.sizeButton.dataset.next = state.size.next;

        if (mine.fullButton) {
            iconSwap(mine.fullButton, state.full.icon, t(state.full.label));
            mine.fullButton.setAttribute('aria-pressed', full ? 'true' : 'false');
            mine.fullButton.classList.toggle('is-active', full);
        }

        if (mine.shareButton) {
            mine.shareButton.setAttribute('aria-pressed', state.share.on ? 'true' : 'false');
            mine.shareButton.classList.toggle('is-active', state.share.on);
            iconSwap(mine.shareButton, state.share.on ? 'bi-display-fill' : 'bi-display', t(state.share.on ? 'call_share_stop' : 'call_share'));
        }

        railDraw(mine, state.rail);
        names();
    }

    // The other shares as tiles at the rail's end, after the members'
    // tiles: the screen (never cut) and its sharer's name by a screen icon;
    // a click puts it on the stage, the one there taking its place here.
    function railDraw(mine, rail) {
        var el = h().el;

        mine.screens = mine.screens || {};
        Object.keys(mine.screens).forEach(function (id) {
            if (rail.indexOf(Number(id)) < 0) {
                mine.screens[id].node.remove();
                delete mine.screens[id];
            }
        });

        rail.forEach(function (id) {
            var held = mine.screens[id];

            if (!held) {
                var node = el('button', 'ws-call-tile is-screen');
                var video = el('video', 'ws-call-video');

                node.type = 'button';
                video.autoplay = true;
                video.playsInline = true;
                video.muted = true;
                node.appendChild(video);
                held = { node: node, video: video, name: el('div', 'ws-call-tile-name'), text: null };
                node.appendChild(held.name);
                node.addEventListener('click', function () {
                    mine.watching = id;
                    mine.pin = -1;
                    stageDraw(mine);
                });
                mine.screens[id] = held;
            }

            // In the rail's order, after the members' tiles.
            mine.grid.appendChild(held.node);

            var screen = (mine.peers[id] && mine.peers[id].screen) || null;
            var text = mine.names[id] || '';

            if (held.video.srcObject !== screen) {
                held.video.srcObject = screen;
            }

            if (held.text !== text) {
                held.text = text;
                held.name.textContent = text;
                held.name.insertBefore(h().icon('bi-display'), held.name.firstChild);
                held.node.setAttribute('aria-label', t('call_share_show', text));
                held.node.title = t('call_share_show', text);
            }
        });

        layout(mine);
    }

    // The share area: the shared screen or the pinned tile's picture
    // (never cut: contain), the sharer's or the pinned member's name, and
    // Unpin while a tile is pinned.
    function shareAreaDraw(mine) {
        var el = h().el;
        var area = el('div', 'ws-call-share');
        var tools = el('div', 'ws-call-share-tools');

        area.hidden = true;
        mine.shareVideo = el('video', 'ws-call-share-video');
        mine.shareVideo.autoplay = true;
        mine.shareVideo.playsInline = true;
        // The sound comes from the member's tile.
        mine.shareVideo.muted = true;
        area.appendChild(mine.shareVideo);
        mine.shareIcon = h().icon('bi-person-circle');
        mine.shareIcon.hidden = true;
        area.appendChild(mine.shareIcon);
        mine.shareName = el('div', 'ws-call-share-name');
        area.appendChild(mine.shareName);

        mine.unpinButton = makeButton('pg-icon-button', '', 'bi-pin-angle-fill', t('call_unpin'));
        mine.unpinButton.setAttribute('aria-label', t('call_unpin'));
        mine.unpinButton.hidden = true;
        mine.unpinButton.addEventListener('click', function () {
            mine.pin = -1;
            stageDraw(mine);
        });
        tools.appendChild(mine.unpinButton);

        area.appendChild(tools);
        mine.shareArea = area;

        return area;
    }

    // The window's head: its name, the call's state, Make larger or
    // smaller (remembered for the next call), Full screen where the page
    // may, and the window's menu.
    function headDraw(mine, win) {
        var el = h().el;
        var bar = el('div', 'ws-call-bar');

        // The name opens the call's conversation; on a page that is that
        // conversation (the guest page) it is the name only.
        if (hooks.openChannel) {
            var name = makeButton('ws-call-name', mine.name, '', t('call_open'));

            name.addEventListener('click', function () { hooks.openChannel(mine.channelId); });
            bar.appendChild(name);
        } else {
            bar.appendChild(el('span', 'ws-call-name', mine.name));
        }
        mine.state = el('span', 'ws-call-state');
        bar.appendChild(mine.state);

        mine.sizeButton = makeButton('pg-icon-button', '', 'bi-arrows-angle-expand', t('call_larger'));
        mine.sizeButton.addEventListener('click', function () {
            mine.size = mine.sizeButton.dataset.next || 'large';
            choicesSave({ size: mine.size });
            stageDraw(mine);
        });
        bar.appendChild(mine.sizeButton);

        if (document.fullscreenEnabled && win.requestFullscreen) {
            mine.fullButton = makeButton('pg-icon-button', '', 'bi-fullscreen', t('call_full_screen'));
            mine.fullButton.setAttribute('aria-pressed', 'false');
            mine.fullButton.addEventListener('click', function () {
                if (document.fullscreenElement) {
                    document.exitFullscreen().catch(function () {});
                } else {
                    win.requestFullscreen().catch(function () {});
                }
            });
            bar.appendChild(mine.fullButton);
        }

        bar.appendChild(menuDraw(mine));

        return bar;
    }

    // The window's menu: Hide my picture / Show my picture, which hides
    // one's own tile on this screen only (the others still see it).
    function menuDraw(mine) {
        var el = h().el;
        var wrap = el('div', 'ws-call-menu-wrap');
        var toggle = makeButton('pg-icon-button', '', 'bi-three-dots-vertical', t('call_more'));
        var menu = el('ul', 'menu ws-call-menu');
        var li = el('li');
        var item = makeButton('menu-item', t('call_self_hide'), 'bi-person-slash');
        var open = function (on) {
            menu.classList.toggle('show', on);
            toggle.setAttribute('aria-expanded', on ? 'true' : 'false');
        };

        toggle.setAttribute('aria-label', t('call_more'));
        toggle.setAttribute('aria-haspopup', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.addEventListener('click', function () { open(!menu.classList.contains('show')); });
        item.addEventListener('click', function () {
            mine.selfHidden = !mine.selfHidden;
            mine.selfTile.node.hidden = mine.selfHidden;
            relabel(item, t(mine.selfHidden ? 'call_self_show' : 'call_self_hide'));
            iconSwap(item, mine.selfHidden ? 'bi-person' : 'bi-person-slash', t(mine.selfHidden ? 'call_self_show' : 'call_self_hide'));
            open(false);
            stageDraw(mine);
        });
        li.appendChild(item);
        menu.appendChild(li);
        wrap.appendChild(toggle);
        wrap.appendChild(menu);

        // A click elsewhere or Esc closes it.
        mine.onMenuAway = function (event) {
            if ((event.type === 'keydown') ? (event.key === 'Escape') : !wrap.contains(event.target)) {
                open(false);
            }
        };
        document.addEventListener('click', mine.onMenuAway);
        document.addEventListener('keydown', mine.onMenuAway);

        return wrap;
    }

    // The notice while this member shares: its own screen is not shown.
    function noticeDraw(mine) {
        var el = h().el;
        var notice = el('div', 'alert theme-info small ws-call-notice');
        var stop = makeButton('pg-button pg-button-danger pg-button-sm', t('call_share_stop'), '');

        notice.hidden = true;
        notice.setAttribute('role', 'status');
        notice.appendChild(el('span', '', t('call_self_sharing')));
        stop.addEventListener('click', function () { shareStop(mine, true); });
        notice.appendChild(stop);
        mine.notice = notice;

        return notice;
    }

    // "You are muted", with Unmute, while the muted person speaks.
    function mutedDraw(mine) {
        var el = h().el;
        var notice = el('div', 'alert theme-info small ws-call-notice');
        var unmute = makeButton('pg-button pg-button-sm', t('call_unmute'), '');

        notice.hidden = true;
        notice.setAttribute('role', 'status');
        notice.appendChild(el('span', '', t('call_muted_talk')));
        unmute.addEventListener('click', function () { mute(false); });
        notice.appendChild(unmute);
        mine.mutedNotice = notice;

        return notice;
    }

    // ── The window ──

    function draw() {
        var el = h().el;
        var mine = call;
        var win = el('div', 'pg-card ws-call');

        win.setAttribute('role', 'region');
        win.setAttribute('aria-label', t(mine.kind === 'video' ? 'call_video' : 'call_voice'));
        win.setAttribute('data-kind', mine.kind);

        win.appendChild(headDraw(mine, win));

        // The grid: one tile a member, one's own first (its picture never
        // plays its own sound back).
        var body = el('div', 'ws-call-body');

        body.appendChild(shareAreaDraw(mine));
        mine.grid = el('div', 'ws-call-stage');
        mine.grid.setAttribute('role', 'list');
        body.appendChild(mine.grid);
        win.appendChild(body);
        mine.selfTile = tile(mine, 0);
        mine.selfTile.video.muted = true;
        mine.selfTile.video.srcObject = mine.stream;
        selfListen(mine);
        Object.keys(mine.peers).forEach(function (id) {
            mine.peers[id].tile = mine.peers[id].tile || tile(mine, Number(id));
        });

        win.appendChild(noticeDraw(mine));
        win.appendChild(mutedDraw(mine));

        var tools = el('div', 'ws-call-tools');

        mine.muteButton = makeButton('pg-icon-button', '', 'bi-mic', t('call_mute'));
        mine.muteButton.addEventListener('click', function () {
            mute(!mine.muted);
            choicesSave({ muted: mine.muted });
        });
        tools.appendChild(mine.muteButton);

        if (mine.kind === 'video') {
            mine.cameraButton = makeButton('pg-icon-button', '', 'bi-camera-video', t('call_camera_off'));
            mine.cameraButton.addEventListener('click', function () {
                camera(!mine.cameraOff);
                choicesSave({ cameraOff: mine.cameraOff });
            });
            tools.appendChild(mine.cameraButton);
        }

        // Sharing one's screen, where the browser can (not on a phone).
        if (shareState(0, 0, !!navigator.mediaDevices.getDisplayMedia).show) {
            mine.shareButton = makeButton('pg-icon-button', '', 'bi-display', t('call_share'));
            mine.shareButton.setAttribute('aria-pressed', 'false');
            mine.shareButton.addEventListener('click', function () {
                if (mine.sharing) {
                    shareStop(mine, true);
                } else {
                    shareStart(mine);
                }
            });
            tools.appendChild(mine.shareButton);
        }

        mine.handButton = makeButton('pg-icon-button', '', 'bi-hand-index', t('call_hand_raise'));
        mine.handButton.setAttribute('aria-pressed', 'false');
        mine.handButton.addEventListener('click', function () { hand(!mine.hand); });
        tools.appendChild(mine.handButton);

        var devices = makeButton('pg-icon-button', '', 'bi-gear', t('call_devices'));
        var panel = el('div', 'ws-call-devices');

        panel.hidden = true;
        devices.setAttribute('aria-expanded', 'false');
        devices.addEventListener('click', function () {
            panel.hidden = !panel.hidden;
            devices.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');

            if (!panel.hidden) {
                drawDevices(panel);
            }
        });
        tools.appendChild(devices);

        var hang = makeButton('pg-button pg-button-danger pg-button-sm', t('call_hang_up'), 'bi-telephone-x');

        hang.addEventListener('click', function () { hangUp(''); });
        tools.appendChild(hang);
        win.appendChild(tools);
        win.appendChild(panel);

        [mine.muteButton, mine.cameraButton, mine.shareButton, mine.handButton, devices].forEach(function (node) {
            if (node) {
                node.setAttribute('aria-label', node.title);
            }
        });

        mine.node = win;
        document.body.appendChild(win);
        // Esc, or the browser's own way out of full screen.
        mine.onFull = function () { stageDraw(mine); };
        document.addEventListener('fullscreenchange', mine.onFull);
        mine.clock = setInterval(status, 1000);
        mine.meter = setInterval(function () { measure(mine); }, LEVEL_EVERY);
        mine.stats = setInterval(function () { statsRead(mine); }, STATS_EVERY);
        stageDraw(mine);
        status();
    }

    // A member's tile: the picture (with the sound), the name, the mark of
    // the one speaking, and the marks of a raised hand and a weak
    // connection. Member 0 is the viewer.
    function tile(mine, id) {
        if (!mine.grid) {
            return null;
        }

        var el = h().el;
        var node = el('div', 'ws-call-tile' + (id ? '' : ' is-self'));
        var video = el('video', 'ws-call-video');
        var name = el('div', 'ws-call-tile-name');

        node.setAttribute('role', 'listitem');
        video.autoplay = true;
        video.playsInline = true;
        node.appendChild(video);
        node.appendChild(h().icon('bi-person-circle'));
        node.appendChild(name);

        var marks = el('div', 'ws-call-tile-marks');
        var handMark = mark('bi-hand-index-fill', t('call_hand_up'));
        var weakMark = mark('bi-reception-1', t('call_weak'));

        weakMark.classList.add('is-weak');

        marks.appendChild(handMark);
        marks.appendChild(weakMark);
        node.appendChild(marks);

        // The whole tile is a button that pins it large (one's own too); a
        // second click, or Unpin on the stage, lets it go.
        var pin = el('button', 'ws-call-tile-pin');

        pin.type = 'button';
        pin.title = t('call_pin');
        pin.setAttribute('aria-label', t('call_pin'));
        pin.setAttribute('aria-pressed', 'false');
        pin.addEventListener('click', function () {
            mine.pin = (mine.pin === id) ? -1 : id;
            stageDraw(mine);
        });
        node.appendChild(pin);

        var held = { id: id, node: node, video: video, name: name, pin: pin, loudAt: 0, speaking: false, handMark: handMark, weakMark: weakMark };

        // Another member's sound plays on the chosen speaker.
        if (id && mine.speaker && sinkSupported()) {
            sinkSet(video, mine.speaker);
        }

        // Before the other shares' tiles, which close the rail.
        mine.grid.insertBefore(node, mine.grid.querySelector('.is-screen'));
        layout(mine);

        return held;
    }

    // One mark of a tile: an icon named for the screen reader, hidden till on.
    function mark(iconName, label) {
        var node = h().el('span', 'ws-call-tile-mark');

        node.hidden = true;
        node.title = label;
        node.setAttribute('role', 'img');
        node.setAttribute('aria-label', label);
        node.appendChild(h().icon(iconName));

        return node;
    }

    // The tiles' raised hands (one's own as this window holds it, the
    // others' as the call part says) and weak connections.
    function marksDraw(mine) {
        var show = function (node, on) {
            if (node && (node.hidden === on)) {
                node.hidden = !on;
            }
        };

        if (mine.selfTile) {
            show(mine.selfTile.handMark, !!mine.hand);
        }

        Object.keys(mine.peers).forEach(function (id) {
            var held = mine.peers[id];

            if (held.tile) {
                show(held.tile.handMark, (mine.hands || []).indexOf(Number(id)) >= 0);
                show(held.tile.weakMark, weak(held.pc.connectionState, held.loss || 0));
            }
        });
    }

    // Counts each connection's received and lost packets; the loss since
    // the last count marks a weak connection.
    function statsRead(mine) {
        Object.keys(mine.peers).forEach(function (id) {
            var held = mine.peers[id];

            if (held.pc.connectionState !== 'connected') {
                held.counts = null;
                held.loss = 0;
                return;
            }

            held.pc.getStats().then(function (report) {
                var entries = [];

                report.forEach(function (entry) { entries.push(entry); });

                var counts = inbound(entries);

                held.loss = lossRatio(held.counts, counts);
                held.counts = counts;

                if (call === mine) {
                    marksDraw(mine);
                }
            }).catch(function () {});
        });
    }

    // Raises or lowers this member's hand; the others read it from the
    // call part, and the keep-alive renews it while it is up.
    function hand(on) {
        var mine = call;
        var state = handState(on);

        mine.hand = on;
        send(0, on ? 'hand' : 'unhand', '');
        mine.handButton.setAttribute('aria-pressed', state.pressed);
        mine.handButton.classList.toggle('is-active', on);
        iconSwap(mine.handButton, state.icon, t(state.label));
        marksDraw(mine);
    }

    function tileDrop(mine, held) {
        if (!held) {
            return;
        }

        if (held.source) {
            try { held.source.disconnect(); } catch (error) { /* gone already */ }
        }

        held.node.remove();
        layout(mine);
    }

    function layout(mine) {
        // The members' tiles only: the other shares' tiles are not people.
        var count = mine.grid ? mine.grid.querySelectorAll('.ws-call-tile:not(.is-screen)').length : 0;

        // The stylesheet lays the tiles out by their number.
        if (mine.grid) {
            mine.grid.setAttribute('data-count', String(count));
        }

        if (mine.node) {
            mine.node.classList.toggle('is-group', count > 2);
        }
    }

    // The tiles' names, as the call's members are known to the look.
    function names() {
        var mine = call;

        if (!mine || !mine.grid) {
            return;
        }

        var label = function (held, text, sharing) {
            if (held && ((held.name.textContent !== text) || (!!held.sharing !== sharing))) {
                held.name.textContent = text;
                held.sharing = sharing;
                held.node.setAttribute('aria-label', sharing ? t('call_sharing', text) : text);

                // The sharer's tile stays, its name marked with a screen.
                if (sharing) {
                    held.name.insertBefore(h().icon('bi-display'), held.name.firstChild);
                }
            }
        };

        label(mine.selfTile, t('call_you'), !!mine.sharing);
        Object.keys(mine.peers).forEach(function (id) {
            label(mine.peers[id].tile, mine.names[id] || '', (mine.sharers || []).indexOf(Number(id)) >= 0);
        });
    }

    // A stream's voice into an analyser of the call's one sound context.
    function listen(mine, held, stream) {
        var Context = window.AudioContext || window.webkitAudioContext;

        if (!held || !Context || !stream || !stream.getAudioTracks().length) {
            return;
        }

        try {
            mine.audio = mine.audio || new Context();

            if (held.source) {
                held.source.disconnect();
            }

            held.source = mine.audio.createMediaStreamSource(stream);
            held.analyser = mine.audio.createAnalyser();
            held.analyser.fftSize = 512;
            held.bytes = new Uint8Array(held.analyser.fftSize);
            held.source.connect(held.analyser);
        } catch (error) {
            // No mark of who speaks; the call itself goes on.
            held.analyser = null;
        }
    }

    // The person's own voice is heard from a copy of the microphone's track
    // that stays on while muted (a muted track gives silence), so "You are
    // muted" can be shown; the copy goes to no connection.
    function selfListen(mine) {
        var track = mine.stream.getAudioTracks()[0];
        var copy = null;

        if (mine.hearCopy) {
            mine.hearCopy.stop();
            mine.hearCopy = null;
        }

        try {
            copy = (track && window.MediaStream) ? track.clone() : null;
        } catch (error) {
            copy = null;
        }

        if (copy) {
            copy.enabled = true;
            mine.hearCopy = copy;
        }

        listen(mine, mine.selfTile, copy ? new window.MediaStream([copy]) : mine.stream);
    }

    // Marks the tiles of the ones speaking now.
    function measure(mine) {
        var now = Date.now();
        var tiles = [mine.selfTile].concat(Object.keys(mine.peers).map(function (id) { return mine.peers[id].tile; }));

        if (mine.audio && (mine.audio.state === 'suspended')) {
            mine.audio.resume().catch(function () {});
        }

        tiles.forEach(function (held) {
            if (!held || !held.analyser) {
                return;
            }

            held.analyser.getByteTimeDomainData(held.bytes);

            var heard = level(held.bytes);
            var loudness = (held === mine.selfTile && mine.muted) ? 0 : heard;

            if ((held === mine.selfTile) && mine.muted && (heard >= SPEAKING_LEVEL)) {
                mine.talkAt = now;
            }

            if (loudness >= SPEAKING_LEVEL) {
                held.loudAt = now;
            }

            var on = speaking(loudness, held.loudAt, now);

            if (on !== held.speaking) {
                held.speaking = on;
                held.node.classList.toggle('is-speaking', on);
            }
        });

        var hint = mutedHint(mine.muted, mine.talkAt, now);

        if (mine.mutedNotice && (mine.mutedNotice.hidden === hint)) {
            mine.mutedNotice.hidden = !hint;
        }
    }

    function status() {
        var mine = call;

        if (!mine || !mine.state) {
            return;
        }

        var ids = Object.keys(mine.peers);

        restartAll(mine, false);

        var lost = ids.filter(function (id) {
            var held = mine.peers[id];

            return held.lostAt && ((Date.now() - held.lostAt) > LOST_AFTER);
        });

        // Every connection broken: the call is lost here. One of a group's
        // broken: its tile says so while the others go on.
        if (ids.length && (lost.length === ids.length)) {
            hangUp('call_lost');
            return;
        }

        ids.forEach(function (id) {
            var held = mine.peers[id];

            if (held.tile) {
                held.tile.node.classList.toggle('is-lost', lost.indexOf(id) >= 0);
            }
        });

        marksDraw(mine);

        var connected = Object.keys(mine.peers).some(function (id) {
            return mine.peers[id].pc.connectionState === 'connected';
        });

        mine.state.textContent = connected ? clock((Date.now() - mine.connectedAt) / 1000)
            : t(ids.length ? 'call_connecting' : (mine.group ? 'call_waiting' : (mine.ever ? 'call_connecting' : 'call_calling')));
        mine.node.classList.toggle('is-connected', connected);
    }

    function iconSwap(node, name, title) {
        var mark = node.querySelector('.bi');

        if (mark) {
            mark.className = 'bi ' + name;
        }

        node.title = title;
        node.setAttribute('aria-label', title);
    }

    function mute(on) {
        var mine = call;

        mine.muted = on;
        mine.talkAt = 0;

        if (mine.mutedNotice) {
            mine.mutedNotice.hidden = true;
        }

        mine.stream.getAudioTracks().forEach(function (track) { track.enabled = !on; });
        mine.muteButton.setAttribute('aria-pressed', on ? 'true' : 'false');
        iconSwap(mine.muteButton, on ? 'bi-mic-mute' : 'bi-mic', t(on ? 'call_unmute' : 'call_mute'));
    }

    function camera(off) {
        var mine = call;

        mine.cameraOff = off;
        mine.stream.getVideoTracks().forEach(function (track) { track.enabled = !off; });
        mine.cameraButton.setAttribute('aria-pressed', off ? 'true' : 'false');
        iconSwap(mine.cameraButton, off ? 'bi-camera-video-off' : 'bi-camera-video', t(off ? 'call_camera_on' : 'call_camera_off'));
        mine.node.classList.toggle('is-camera-off', off);
        mine.selfTile.node.classList.toggle('is-camera-off', off);
    }

    // The microphones, cameras and speakers of the device, each a select
    // (speakers where the browser can choose one); a microphone or camera
    // chosen takes its track into every connection, a speaker plays every
    // member's sound. Each choice is remembered for the next call.
    function drawDevices(panel) {
        var mine = call;

        navigator.mediaDevices.enumerateDevices().then(function (list) {
            if (call !== mine) {
                return;
            }

            h().clear(panel);

            [['audio', 'audioinput', 'call_microphone'], ['video', 'videoinput', 'call_camera'], ['speaker', 'audiooutput', 'call_speaker']].forEach(function (one) {
                if ((one[0] === 'video') && (mine.kind !== 'video')) {
                    return;
                }

                if ((one[0] === 'speaker') && !sinkSupported()) {
                    return;
                }

                var track = (one[0] === 'audio') ? mine.stream.getAudioTracks()[0] : mine.stream.getVideoTracks()[0];
                var using = (track && track.getSettings) ? (track.getSettings().deviceId || '') : '';

                if (one[0] === 'speaker') {
                    using = speakerOf(list.filter(function (device) { return device.kind === 'audiooutput'; }), mine.speaker);
                }
                var id = 'ws-call-' + one[0];
                var label = h().el('label', 'pg-field-label', t(one[2]));
                var select = h().el('select', 'pg-input pg-input-sm');

                label.htmlFor = id;
                select.id = id;

                list.filter(function (device) { return device.kind === one[1]; }).forEach(function (device, index) {
                    var option = h().el('option', '', device.label || (t(one[2]) + ' ' + (index + 1)));

                    option.value = device.deviceId;
                    option.selected = device.deviceId === using;
                    select.appendChild(option);
                });

                select.addEventListener('change', function () {
                    if (one[0] === 'speaker') {
                        useSpeaker(select.value);
                    } else {
                        useDevice(one[0], select.value);
                    }
                });
                panel.appendChild(label);
                panel.appendChild(select);
            });
        }).catch(function () {});
    }

    function useDevice(kind, deviceId) {
        var mine = call;
        var ask = {};

        mine.devices[kind] = deviceId;
        ask[kind] = mediaAsk(mine.kind, mine.devices)[kind];

        navigator.mediaDevices.getUserMedia(ask).then(function (stream) {
            var fresh = (kind === 'audio') ? stream.getAudioTracks()[0] : stream.getVideoTracks()[0];

            if ((call !== mine) || !fresh) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                return;
            }

            var old = (kind === 'audio') ? mine.stream.getAudioTracks()[0] : mine.stream.getVideoTracks()[0];

            fresh.enabled = (kind === 'audio') ? !mine.muted : !mine.cameraOff;

            Object.keys(mine.peers).forEach(function (id) {
                var slot = mine.peers[id].slot;

                mine.peers[id].pc.getSenders().forEach(function (sender) {
                    // The screen's line keeps the shared screen.
                    if (sender.track && (sender.track.kind === fresh.kind) && !(slot && (slot.sender === sender))) {
                        sender.replaceTrack(fresh).catch(function () {});
                    }
                });
            });

            if (old) {
                mine.stream.removeTrack(old);
                old.stop();
            }

            mine.stream.addTrack(fresh);
            choicesSave(kind === 'audio' ? { audio: deviceId } : { video: deviceId });

            // The speaking mark hears the new microphone.
            if (kind === 'audio') {
                selfListen(mine);
            }
        }).catch(function () {
            h().toast(t('call_denied'), 'danger');
        });
    }

    function useSpeaker(deviceId) {
        var mine = call;

        mine.speaker = deviceId;
        Object.keys(mine.peers).forEach(function (id) {
            if (mine.peers[id].tile) {
                sinkSet(mine.peers[id].tile.video, deviceId);
            }
        });
        choicesSave({ speaker: deviceId });
    }

    // Leaves the call and closes the window; a reason is told in a toast.
    function hangUp(reason) {
        var mine = call;

        if (!mine) {
            return;
        }

        if (mine.id && mine.memberId) {
            h().api('ws_call_leave', { call_id: mine.id, member_id: mine.memberId }, { keepalive: true }).catch(function () {});
        }

        finish(mine, reason);
    }

    function finish(mine, reason) {
        if (call === mine) {
            call = null;
            window.removeEventListener('beforeunload', askBeforeLeaving);
        }

        clearTimeout(mine.timer);
        clearInterval(mine.pinger);
        clearInterval(mine.clock);
        clearInterval(mine.meter);
        clearInterval(mine.stats);
        Object.keys(mine.peers).forEach(function (id) { peerClose(mine, Number(id)); });

        if (mine.stream) {
            mine.stream.getTracks().forEach(function (track) { track.stop(); });
        }

        if (mine.hearCopy) {
            mine.hearCopy.stop();
            mine.hearCopy = null;
        }

        if (mine.screenTrack) {
            mine.screenTrack.onended = null;
            mine.screenTrack.stop();
            mine.screenTrack = null;
        }

        if (mine.audio) {
            mine.audio.close().catch(function () {});
        }

        if (mine.onMenuAway) {
            document.removeEventListener('click', mine.onMenuAway);
            document.removeEventListener('keydown', mine.onMenuAway);
        }

        if (mine.onFull) {
            document.removeEventListener('fullscreenchange', mine.onFull);
        }

        if (document.fullscreenElement && mine.node && (document.fullscreenElement === mine.node)) {
            document.exitFullscreen().catch(function () {});
        }

        if (mine.node) {
            mine.node.remove();
        }

        if (reason) {
            h().toast(t(reason));
        }
    }

    // A reload or a close during a call asks first; the browser writes the
    // question itself.
    function askBeforeLeaving(event) {
        if (call) {
            event.preventDefault();
            event.returnValue = '';
        }
    }

    // During a call a link that leaves the page (an order, a customer, a
    // page, the panel's menu) opens in a new tab, so the call's page and
    // its connections stay. Heard on the window, after the Workspace's own
    // handlers: a link they handle in the page (defaultPrevented) is left
    // alone, and a click with a modifier key keeps the browser's own way.
    window.addEventListener('click', function (event) {
        if (!call || event.defaultPrevented || (event.button !== 0) || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }

        var link = (event.target && event.target.closest) ? event.target.closest('a[href]') : null;

        if (!link || !leavesPage({ raw: link.getAttribute('href'), href: link.href, target: link.getAttribute('target'), download: link.hasAttribute('download') }, window.location)) {
            return;
        }

        event.preventDefault();
        window.open(link.href, '_blank', 'noopener');

        if (!call.toldNewTab) {
            call.toldNewTab = true;
            h().toast(t('call_new_tab'));
        }
    });

    // A tab closed or reloaded leaves its call: the leave is sent as the
    // page goes (keepalive). Where it never arrives (a phone that dropped
    // the page) the server takes the member out once it is unheard for 90
    // seconds.
    window.addEventListener('pagehide', function (event) {
        if (call && !event.persisted) {
            hangUp('');
        }
    });

    // The device back online, or on another network (a phone from Wi-Fi to
    // its mobile network): every connection this window offers is restarted
    // at once, before it is seen to break.
    window.addEventListener('online', function () {
        if (call && call.node) {
            restartAll(call, true);
        }
    });

    if (navigator.connection && navigator.connection.addEventListener) {
        navigator.connection.addEventListener('change', function () {
            if (call && call.node) {
                restartAll(call, true);
            }
        });
    }

    window.PGWsCalls = {
        supported: supported,
        headButtons: headButtons,
        joinButton: joinButton,
        rings: rings,
        channelCall: channelCall,
        busy: function () { return !!call; },
        // The pure parts, for the tests.
        callable: callable,
        isGroup: isGroup,
        joinState: joinState,
        restartDue: restartDue,
        placeOutcome: placeOutcome,
        readGiveUp: readGiveUp,
        aloneOutcome: aloneOutcome,
        level: level,
        speaking: speaking,
        offers: offers,
        peersDiff: peersDiff,
        ringsToShow: ringsToShow,
        ringsGone: ringsGone,
        clock: clock,
        mediaAsk: mediaAsk,
        choicesRead: choicesRead,
        choicesWith: choicesWith,
        speakerOf: speakerOf,
        screenSlot: screenSlot,
        shareState: shareState,
        stageState: stageState,
        leavesPage: leavesPage,
        keepAlive: keepAlive,
        handState: handState,
        inbound: inbound,
        lossRatio: lossRatio,
        weak: weak,
        mutedHint: mutedHint
    };
})();
