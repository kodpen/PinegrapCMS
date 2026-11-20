/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - calls, the browser's side: the voice and video buttons in a
 * channel's head (and its Join button while a call is on there), the card
 * that rings the other person of a conversation of two (Accept, Decline),
 * and the call window that stays at the bottom of the screen while the
 * person moves between channels: a grid of up to four, the one speaking
 * marked; mute, the camera, the microphone and camera in use, hang up.
 *
 * Sound and picture go from browser to browser (WebRTC). The site carries
 * only who is in the call and the browsers' connection messages
 * (includes/workspace/calls.php): the window reads them with ws_call_sync
 * every second and sends its own with ws_call_signal. Every two members
 * hold one connection (a mesh); of each pair the one who joined later (the
 * higher member id) makes the offer, so both never offer at once. assets/js/workspace.js draws the head and hands the
 * rings of its look (ws_sync's rings) in here with its own helpers.
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

    // How often the window reads its call (milliseconds), how often it says
    // it is still there (the server ends a call silent for 2 minutes), and
    // how long a broken connection may last before the call is given up.
    var LOOK_EVERY = 1000;
    var PING_EVERY = 30000;
    var LOST_AFTER = 15000;

    // A group's call stays while the person waits alone in it, for others
    // to come or come back, up to this long (milliseconds).
    var ALONE_FOR = 300000;

    // How often the voices are measured, how loud is speaking (0-1), and
    // how long the mark stays after the voice drops (milliseconds).
    var LEVEL_EVERY = 150;
    var SPEAKING_LEVEL = 0.04;
    var SPEAKING_HOLD = 600;

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
    // or more the viewer is a member of, open in its current version.
    function callable(channel, view) {
        if (!channel || !view || !view.calls || view.era || view.thread) {
            return false;
        }

        if (!channel.joined || channel.archived || (['mailbox', 'thread', 'guest'].indexOf(channel.kind) >= 0)) {
            return false;
        }

        return (channel.members || []).length >= 2;
    }

    // Is a channel's call a group's? More than two members: nobody is rung,
    // the call is joined from the channel's head, and it stays while one
    // waits alone.
    function isGroup(channel) {
        return !!channel && ((channel.members || []).length > 2);
    }

    // The head's Join button against the open channel's call (the look's
    // call part): hidden without a call or while the viewer is in it; full
    // at max members.
    function joinState(part, current, max) {
        var live = part && part.call;

        if (!live || part.member_id || (live.id === current)) {
            return { show: false, full: false, count: 0, callId: 0, kind: '' };
        }

        var count = (live.members || []).length;

        return { show: true, full: count >= (max || 4), count: count, callId: live.id, kind: live.kind };
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
    function mediaAsk(kind, devices) {
        var audio = { echoCancellation: true, noiseSuppression: true, autoGainControl: true };

        if (devices && devices.audio) {
            audio.deviceId = { exact: devices.audio };
        }

        var video = false;

        if (kind === 'video') {
            video = { width: { ideal: 1280 }, height: { ideal: 720 } };

            if (devices && devices.video) {
                video.deviceId = { exact: devices.video };
            }
        }

        return { audio: audio, video: video };
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

        var box = h().el('span', 'ws-call-head ws-head-wide');
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
                begin(channel.id, head.state.kind, head.state.callId, callName(channel), group);
            }
        });
        box.appendChild(join);

        head = { channelId: channel.id, join: join, starts: starts, state: joinState(null, 0, 0) };

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

        var state = joinState(part, call ? call.id : 0, hooks.max);

        head.state = state;
        head.join.hidden = !state.show;
        head.join.disabled = state.full;
        head.starts.forEach(function (node) { node.hidden = state.show || !!(part && part.member_id); });

        if (state.show) {
            relabel(head.join, state.full ? t('call_full') : t('call_join_count', state.count, hooks.max || 4));
        }
    }

    // Starts a call (callId 0) or joins one, once the devices are given.
    function begin(channelId, kind, callId, name, group) {
        if (call) {
            h().toast(t('call_busy'), 'warning');
            return;
        }

        if (!supported()) {
            h().toast(t('call_unsupported'), 'warning');
            return;
        }

        // Held at once, so a second click does not start a second call.
        call = { id: callId, channelId: channelId, kind: kind, name: name, group: !!group, memberId: 0, after: 0, peers: {}, names: {}, outgoing: !callId, since: Date.now(), aloneSince: Date.now(), devices: {}, muted: false, cameraOff: false, ever: false, sending: Promise.resolve() };

        var mine = call;

        navigator.mediaDevices.getUserMedia(mediaAsk(kind, null)).then(function (stream) {
            mine.stream = stream;

            return callId ? h().api('ws_call_join', { channel_id: channelId, call_id: callId })
                : h().api('ws_call_start', { channel_id: channelId, kind: kind });
        }).then(function (result) {
            if (call !== mine) {
                return;
            }

            mine.id = result.call_id;
            mine.memberId = result.member_id;
            // The member's own servers, the relay's credentials among them.
            mine.ice = result.ice || null;
            draw();
            look();
            mine.pinger = setInterval(function () { send(0, 'ping', ''); }, PING_EVERY);
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
            return h().api('ws_call_signal', { call_id: mine.id, to_member_id: to, kind: kind, body: body });
        }).catch(function () {});
    }

    function look() {
        var mine = call;

        if (!mine) {
            return;
        }

        h().api('ws_call_sync', { call_id: mine.id, call_after: mine.after }).then(function (data) {
            if (call === mine) {
                heard(mine, data.call);
            }
        }).catch(function () {
            // The call is gone (ended, or no longer readable).
            if (call === mine) {
                hangUp(mine.ever ? 'call_ended' : 'call_unanswered');
            }
        }).then(function () {
            if (call === mine) {
                mine.timer = setTimeout(look, LOOK_EVERY);
            }
        });
    }

    function heard(mine, part) {
        if (!part || !part.call || (part.call.id !== mine.id) || !part.member_id) {
            hangUp(mine.ever ? 'call_ended' : 'call_unanswered');
            return;
        }

        (part.call.members || []).forEach(function (member) {
            mine.names[member.member_id] = member.name || '';
        });

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
            var outcome = aloneOutcome(mine.group, mine.ever, mine.ever ? mine.aloneSince : mine.since, Date.now(), hooks.ring || 45);

            if (outcome) {
                hangUp(outcome);
                return;
            }
        }

        names();
        status();
    }

    function peer(mine, id, offer) {
        var pc = new RTCPeerConnection({ iceServers: mine.ice || hooks.ice || [] });
        var held = { pc: pc, queue: [], lostAt: 0, tile: tile(mine, id) };

        mine.peers[id] = held;
        mine.stream.getTracks().forEach(function (track) {
            pc.addTrack(track, mine.stream);
        });

        pc.onicecandidate = function (event) {
            if (event.candidate) {
                send(id, 'candidate', JSON.stringify(event.candidate));
            }
        };

        pc.ontrack = function (event) {
            var stream = event.streams && event.streams[0];

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

    // ── The window ──

    function draw() {
        var el = h().el;
        var mine = call;
        var win = el('div', 'pg-card ws-call');

        win.setAttribute('role', 'region');
        win.setAttribute('aria-label', t(mine.kind === 'video' ? 'call_video' : 'call_voice'));
        win.setAttribute('data-kind', mine.kind);

        // The grid: one tile a member, one's own first (its picture never
        // plays its own sound back).
        mine.grid = el('div', 'ws-call-stage');
        mine.grid.setAttribute('role', 'list');
        win.appendChild(mine.grid);
        mine.selfTile = tile(mine, 0);
        mine.selfTile.video.muted = true;
        mine.selfTile.video.srcObject = mine.stream;
        listen(mine, mine.selfTile, mine.stream);
        Object.keys(mine.peers).forEach(function (id) {
            mine.peers[id].tile = mine.peers[id].tile || tile(mine, Number(id));
        });

        var bar = el('div', 'ws-call-bar');
        var name = makeButton('ws-call-name', mine.name, '', t('call_open'));

        name.addEventListener('click', function () { hooks.openChannel(mine.channelId); });
        bar.appendChild(name);
        mine.state = el('span', 'ws-call-state');
        bar.appendChild(mine.state);
        win.appendChild(bar);

        var tools = el('div', 'ws-call-tools');

        mine.muteButton = makeButton('pg-icon-button', '', 'bi-mic', t('call_mute'));
        mine.muteButton.addEventListener('click', function () { mute(!mine.muted); });
        tools.appendChild(mine.muteButton);

        if (mine.kind === 'video') {
            mine.cameraButton = makeButton('pg-icon-button', '', 'bi-camera-video', t('call_camera_off'));
            mine.cameraButton.addEventListener('click', function () { camera(!mine.cameraOff); });
            tools.appendChild(mine.cameraButton);
        }

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

        [mine.muteButton, mine.cameraButton, devices].forEach(function (node) {
            if (node) {
                node.setAttribute('aria-label', node.title);
            }
        });

        mine.node = win;
        document.body.appendChild(win);
        mine.clock = setInterval(status, 1000);
        mine.meter = setInterval(function () { measure(mine); }, LEVEL_EVERY);
        names();
        status();
    }

    // A member's tile: the picture (with the sound), the name, and the mark
    // of the one speaking. Member 0 is the viewer.
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

        var held = { id: id, node: node, video: video, name: name, loudAt: 0, speaking: false };

        mine.grid.appendChild(node);
        layout(mine);

        return held;
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
        var count = mine.grid ? mine.grid.children.length : 0;

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

        var label = function (held, text) {
            if (held && (held.name.textContent !== text)) {
                held.name.textContent = text;
                held.node.setAttribute('aria-label', text);
            }
        };

        label(mine.selfTile, t('call_you'));
        Object.keys(mine.peers).forEach(function (id) {
            label(mine.peers[id].tile, mine.names[id] || '');
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

            var loudness = (held === mine.selfTile && mine.muted) ? 0 : level(held.bytes);

            if (loudness >= SPEAKING_LEVEL) {
                held.loudAt = now;
            }

            var on = speaking(loudness, held.loudAt, now);

            if (on !== held.speaking) {
                held.speaking = on;
                held.node.classList.toggle('is-speaking', on);
            }
        });
    }

    function status() {
        var mine = call;

        if (!mine || !mine.state) {
            return;
        }

        var ids = Object.keys(mine.peers);
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

    // The microphones and cameras of the device, each a select; a choice
    // takes the new device's track into every connection.
    function drawDevices(panel) {
        var mine = call;

        navigator.mediaDevices.enumerateDevices().then(function (list) {
            if (call !== mine) {
                return;
            }

            h().clear(panel);

            [['audio', 'audioinput', 'call_microphone'], ['video', 'videoinput', 'call_camera']].forEach(function (one) {
                if ((one[0] === 'video') && (mine.kind !== 'video')) {
                    return;
                }

                var track = (one[0] === 'audio') ? mine.stream.getAudioTracks()[0] : mine.stream.getVideoTracks()[0];
                var using = (track && track.getSettings) ? (track.getSettings().deviceId || '') : '';
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

                select.addEventListener('change', function () { useDevice(one[0], select.value); });
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
                mine.peers[id].pc.getSenders().forEach(function (sender) {
                    if (sender.track && (sender.track.kind === fresh.kind)) {
                        sender.replaceTrack(fresh).catch(function () {});
                    }
                });
            });

            if (old) {
                mine.stream.removeTrack(old);
                old.stop();
            }

            mine.stream.addTrack(fresh);

            // The speaking mark hears the new microphone.
            if (kind === 'audio') {
                listen(mine, mine.selfTile, mine.stream);
            }
        }).catch(function () {
            h().toast(t('call_denied'), 'danger');
        });
    }

    // Leaves the call and closes the window; a reason is told in a toast.
    function hangUp(reason) {
        var mine = call;

        if (!mine) {
            return;
        }

        if (mine.id && mine.memberId) {
            h().api('ws_call_leave', { call_id: mine.id }, { keepalive: true }).catch(function () {});
        }

        finish(mine, reason);
    }

    function finish(mine, reason) {
        if (call === mine) {
            call = null;
        }

        clearTimeout(mine.timer);
        clearInterval(mine.pinger);
        clearInterval(mine.clock);
        clearInterval(mine.meter);
        Object.keys(mine.peers).forEach(function (id) { peerClose(mine, Number(id)); });

        if (mine.stream) {
            mine.stream.getTracks().forEach(function (track) { track.stop(); });
        }

        if (mine.audio) {
            mine.audio.close().catch(function () {});
        }

        if (mine.node) {
            mine.node.remove();
        }

        if (reason) {
            h().toast(t(reason));
        }
    }

    // A tab closed or reloaded leaves its call.
    window.addEventListener('pagehide', function (event) {
        if (call && !event.persisted) {
            hangUp('');
        }
    });

    window.PGWsCalls = {
        supported: supported,
        headButtons: headButtons,
        rings: rings,
        channelCall: channelCall,
        busy: function () { return !!call; },
        // The pure parts, for the tests.
        callable: callable,
        isGroup: isGroup,
        joinState: joinState,
        aloneOutcome: aloneOutcome,
        level: level,
        speaking: speaking,
        offers: offers,
        peersDiff: peersDiff,
        ringsToShow: ringsToShow,
        ringsGone: ringsGone,
        clock: clock,
        mediaAsk: mediaAsk
    };
})();
