/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - calls between two people, the browser's side: the voice and
 * video buttons in a conversation's head, the card that rings the other
 * person (Accept, Decline), and the small call window that stays at the
 * bottom of the screen while the person moves between channels (mute, the
 * camera, the microphone and camera in use, hang up).
 *
 * Sound and picture go from browser to browser (WebRTC). The site carries
 * only who is in the call and the browsers' connection messages
 * (includes/workspace/calls.php): the window reads them with ws_call_sync
 * every second and sends its own with ws_call_signal. Of two members, the
 * one who joined later (the higher member id) makes the offer, so both
 * never offer at once. assets/js/workspace.js draws the head and hands the
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

    // The one call of this screen, the rings shown and the ones answered.
    var call = null;
    var shown = {};
    var answered = {};
    var ringTone = { ctx: null, timer: null };
    var hooks = null;

    // ── Pure parts (tests/workspace_calls_screen_test.php) ──

    // Calls need a secure context (getUserMedia) and WebRTC.
    function supported() {
        return !!(window.RTCPeerConnection && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    // Does a conversation's head carry the call buttons? A conversation of
    // two people the viewer is in, open in its current version.
    function callable(channel, view) {
        if (!channel || !view || !view.calls || view.era || view.thread) {
            return false;
        }

        if (!channel.joined || channel.archived || (['mailbox', 'thread', 'guest'].indexOf(channel.kind) >= 0)) {
            return false;
        }

        return (channel.members || []).length === 2;
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
            begin(ring.channel_id, ring.kind, ring.call_id, ring.caller);
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

    // The head's two buttons, or null where the conversation holds no call.
    function headButtons(channel, view, given) {
        hooks = given;

        if (!supported() || !callable(channel, view)) {
            return null;
        }

        var group = h().el('span', 'ws-call-head ws-head-wide');

        [['voice', 'bi-telephone', 'call_voice'], ['video', 'bi-camera-video', 'call_video']].forEach(function (one) {
            var node = makeButton('pg-icon-button', '', one[1], t(one[2]));

            node.setAttribute('aria-label', t(one[2]));
            node.addEventListener('click', function () {
                begin(channel.id, one[0], 0, otherName(channel));
            });
            group.appendChild(node);
        });

        return group;
    }

    function otherName(channel) {
        var other = (channel.members || []).filter(function (member) { return member.id !== hooks.me; })[0];

        return other ? (other.name || '') : (channel.name || '');
    }

    // Starts a call (callId 0) or joins one, once the devices are given.
    function begin(channelId, kind, callId, name) {
        if (call) {
            h().toast(t('call_busy'), 'warning');
            return;
        }

        if (!supported()) {
            h().toast(t('call_unsupported'), 'warning');
            return;
        }

        // Held at once, so a second click does not start a second call.
        call = { id: callId, channelId: channelId, kind: kind, name: name, memberId: 0, after: 0, peers: {}, outgoing: !callId, since: Date.now(), devices: {}, muted: false, cameraOff: false, ever: false, sending: Promise.resolve() };

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

        // The other one left: the call of two is over.
        if (mine.ever && !Object.keys(mine.peers).length) {
            hangUp('call_ended');
            return;
        }

        // Nobody came while it rang.
        if (!mine.ever && mine.outgoing && ((Date.now() - mine.since) > (hooks.ring || 45) * 1000)) {
            hangUp('call_unanswered');
            return;
        }

        status();
    }

    function peer(mine, id, offer) {
        var pc = new RTCPeerConnection({ iceServers: hooks.ice || [] });
        var held = { pc: pc, queue: [], lostAt: 0 };

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
            if (mine.remote && event.streams && event.streams[0] && (mine.remote.srcObject !== event.streams[0])) {
                mine.remote.srcObject = event.streams[0];
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

        var stage = el('div', 'ws-call-stage');

        mine.remote = el('video', 'ws-call-remote');
        mine.remote.autoplay = true;
        mine.remote.playsInline = true;
        stage.appendChild(mine.remote);

        if (mine.kind === 'video') {
            mine.self = el('video', 'ws-call-self');
            mine.self.autoplay = true;
            mine.self.playsInline = true;
            mine.self.muted = true;
            mine.self.srcObject = mine.stream;
            stage.appendChild(mine.self);
        } else {
            stage.appendChild(el('div', 'ws-call-who', mine.name));
        }

        win.appendChild(stage);

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
        status();
    }

    function status() {
        var mine = call;

        if (!mine || !mine.state) {
            return;
        }

        var lost = Object.keys(mine.peers).some(function (id) {
            var held = mine.peers[id];

            return held.lostAt && ((Date.now() - held.lostAt) > LOST_AFTER);
        });

        if (lost) {
            hangUp('call_lost');
            return;
        }

        var connected = Object.keys(mine.peers).some(function (id) {
            return mine.peers[id].pc.connectionState === 'connected';
        });

        mine.state.textContent = connected ? clock((Date.now() - mine.connectedAt) / 1000)
            : t(mine.ever ? 'call_connecting' : 'call_calling');
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
        Object.keys(mine.peers).forEach(function (id) { peerClose(mine, Number(id)); });

        if (mine.stream) {
            mine.stream.getTracks().forEach(function (track) { track.stop(); });
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
        busy: function () { return !!call; },
        // The pure parts, for the tests.
        callable: callable,
        offers: offers,
        peersDiff: peersDiff,
        ringsToShow: ringsToShow,
        ringsGone: ringsGone,
        clock: clock,
        mediaAsk: mediaAsk
    };
})();
