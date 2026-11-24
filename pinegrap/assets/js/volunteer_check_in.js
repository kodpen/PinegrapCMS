/**
 * Pinegrap - Enterprise Website Platform
 *
 * The scan button and camera box of a volunteer's check-in page
 * (volunteer_check_in.php): reads a place's QR poster with the browser's own
 * barcode reader (BarcodeDetector), the ticket check-in's way, and opens the
 * poster's address when it is this site's check-in page. Texts come from
 * the page (data-texts) and are written with textContent only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var root = document.getElementById('vc_scan');

    if (!root) {
        return;
    }

    var texts = {};

    try {
        texts = JSON.parse(root.getAttribute('data-texts') || '{}');
    } catch (e) {
        texts = {};
    }

    var page = root.getAttribute('data-page') || '';
    var video = document.getElementById('vc_video');
    var button = document.getElementById('vc_camera');
    var hint = document.getElementById('vc_hint');
    var stream = null;
    var detector = null;
    var reading = false;

    function showHint(text) {
        hint.textContent = text || '';
    }

    function stopCamera() {
        reading = false;

        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
            stream = null;
        }

        video.classList.add('d-none');
        button.querySelector('.pg-btn-label').textContent = texts.scan;
    }

    // Only a poster of this site's own check-in page is opened.
    function posterUrl(raw) {
        var url;

        try {
            url = new URL(String(raw || ''), window.location.href);
        } catch (e) {
            return '';
        }

        return (url.origin === window.location.origin && url.pathname === page && url.searchParams.get('c')) ? url.href : '';
    }

    function readFrame() {
        if (!reading) {
            return;
        }

        detector.detect(video).then(function (codes) {
            if (codes.length) {
                var url = posterUrl(codes[0].rawValue);

                if (url) {
                    stopCamera();
                    window.location.href = url;

                    return;
                }

                showHint(texts.other);
            }
        }).catch(function () {
            // A frame the reader cannot take yet (the video still starting) is skipped.
        }).then(function () {
            if (reading) {
                window.setTimeout(readFrame, 250);
            }
        });
    }

    function startCamera() {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showHint(texts.no_reader);

            return;
        }

        detector = detector || new window.BarcodeDetector({ formats: ['qr_code'] });

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }).then(function (media) {
            stream = media;
            video.srcObject = media;
            video.classList.remove('d-none');

            return video.play();
        }).then(function () {
            reading = true;
            button.querySelector('.pg-btn-label').textContent = texts.stop;
            showHint(texts.aim);
            readFrame();
        }).catch(function () {
            stopCamera();
            showHint(texts.no_camera);
        });
    }

    button.addEventListener('click', function () {
        if (stream) {
            stopCamera();
            showHint('');
        } else {
            startCamera();
        }
    });
})();
