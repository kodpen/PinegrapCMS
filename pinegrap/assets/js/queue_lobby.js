/**
 * Pinegrap - Enterprise Website Platform
 *
 * The walk-in queue's lobby screen (queue_lobby.php): reads the board every
 * few seconds while the page is shown, draws the numbers now served and
 * the calls before them again, and chimes when a call is new (a new stamp).
 * A browser plays no sound before a click on the page, so the chime waits
 * for "Turn the sound on". "Full screen" asks the browser for the whole
 * screen. Text is set with textContent only: the board is data.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var main = document.querySelector('[data-pg-lobby-read]');

    if (!main) {
        return;
    }

    var nowList = main.querySelector('[data-pg-lobby-now]');
    var empty = main.querySelector('[data-pg-lobby-empty]');
    var pastWrap = main.querySelector('[data-pg-lobby-past-wrap]');
    var pastList = main.querySelector('[data-pg-lobby-past]');
    var closed = main.querySelector('[data-pg-lobby-closed]');
    var soundButton = main.querySelector('[data-pg-lobby-sound]');
    var fullButton = main.querySelector('[data-pg-lobby-full]');
    var stamp = main.getAttribute('data-pg-lobby-stamp') || '';
    var seconds = parseInt(main.getAttribute('data-pg-lobby-seconds'), 10) || 5;
    var audio = null;

    // Two soft tones, the second higher: a call, not an alarm.
    function chime() {
        if (!audio) {
            return;
        }

        [[659.25, 0], [880, 0.35]].forEach(function (tone) {
            var start = audio.currentTime + tone[1];
            var oscillator = audio.createOscillator();
            var gain = audio.createGain();

            oscillator.type = 'sine';
            oscillator.frequency.value = tone[0];
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.4, start + 0.03);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.9);
            oscillator.connect(gain);
            gain.connect(audio.destination);
            oscillator.start(start);
            oscillator.stop(start + 1);
        });
    }

    if (soundButton) {
        var Context = window.AudioContext || window.webkitAudioContext;

        if (!Context) {
            soundButton.hidden = true;
        } else {
            soundButton.addEventListener('click', function () {
                audio = audio || new Context();

                if (audio.resume) {
                    audio.resume();
                }

                soundButton.hidden = true;
                chime();
            });
        }
    }

    if (fullButton) {
        if (!document.documentElement.requestFullscreen) {
            fullButton.hidden = true;
        } else {
            fullButton.addEventListener('click', function () {
                document.documentElement.requestFullscreen().catch(function () {});
            });

            document.addEventListener('fullscreenchange', function () {
                fullButton.hidden = !!document.fullscreenElement;
            });
        }
    }

    function element(tag, className, text, style) {
        var node = document.createElement(tag);

        node.className = className;

        if (text !== undefined) {
            node.textContent = text;
        }

        if (style) {
            node.setAttribute('style', style);
        }

        return node;
    }

    function draw(board) {
        if (!board || !board.ok) {
            return;
        }

        nowList.textContent = '';
        board.now.forEach(function (call) {
            var card = element('div', 'pg-card pg-figure');

            card.appendChild(element('span', 'pg-figure-value', call.number, 'font-size:clamp(2.5rem,7vw,6rem)'));
            card.appendChild(element('span', 'pg-figure-label', call.desk, 'font-size:clamp(1.125rem,2.2vw,2rem)'));
            nowList.appendChild(card);
        });
        empty.hidden = board.now.length > 0;

        pastList.textContent = '';
        board.past.forEach(function (call) {
            var row = element('div', 'pg-card-inner-row');

            row.appendChild(element('strong', 'pg-tabular flex-grow-1 text-nowrap', call.number, 'font-size:clamp(1.25rem,2.5vw,2.25rem)'));
            row.appendChild(element('span', 'pg-text-2', call.desk, 'font-size:clamp(1rem,2vw,1.75rem)'));
            pastList.appendChild(row);
        });
        pastWrap.hidden = board.past.length === 0;

        if (closed) {
            closed.hidden = !!board.open;
        }

        if (board.stamp !== stamp) {
            if (board.stamp !== '') {
                chime();
            }

            stamp = board.stamp;
        }
    }

    function read() {
        if (document.hidden || !window.fetch) {
            return;
        }

        window.fetch(main.getAttribute('data-pg-lobby-read'), {credentials: 'same-origin', cache: 'no-store'})
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(draw)
            .catch(function () {});
    }

    window.setInterval(read, seconds * 1000);
    document.addEventListener('visibilitychange', read);
})();
