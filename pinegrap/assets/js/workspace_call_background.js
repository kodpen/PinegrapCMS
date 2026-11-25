/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a call's "Blur background" and "Remove background" for one's
 * own camera (assets/js/workspace_calls.js draws the buttons and swaps the
 * track it sends).
 *
 * Where the camera blurs by itself (the MediaStreamTrack backgroundBlur
 * constraint, platform effects in Chromium), "Blur background" asks for
 * that and loads nothing. Otherwise the person is cut out of each frame by
 * MediaPipe's Selfie Segmenter (tasks-vision, Apache-2.0): the module, its
 * WASM and the model are served by the site itself
 * (workspace_call_background.php, only while the Calls card's "Background
 * effects" switch is on) and loaded the first time an effect is switched
 * on. The frame, its blurred copy or a plain colour and the person's mask
 * are drawn on a canvas whose own track is what the call sends. Nothing
 * leaves the browser.
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

    // Frames a second drawn on the canvas, the blur of the background
    // (CSS pixels), the colour behind the person when the background is
    // removed, and the softening of the mask's edge.
    var FPS = 24;
    var BLUR = 14;
    var REMOVED = '#3a3f47';
    var EDGE = 2;

    // The smallest module using a WASM SIMD instruction: the runtime is
    // built for SIMD only.
    var SIMD_PROBE = [0, 97, 115, 109, 1, 0, 0, 0, 1, 5, 1, 96, 0, 1, 123, 3, 2, 1, 0, 10, 10, 1, 8, 0, 65, 0, 253, 15, 253, 98, 11];

    var segmenter = null;

    // ── Pure parts (tests/workspace_calls_background_test.php) ──

    // A stored or asked mode: 'blur', 'remove', or '' for none.
    function modeRead(value) {
        return ((value === 'blur') || (value === 'remove')) ? value : '';
    }

    // Which buttons are drawn, from what this browser and site can do:
    // native (the camera blurs by itself), files (the switch is on and the
    // files are there), simd, canvas (a canvas can be sent as a track),
    // filter (a canvas can blur).
    function offer(can) {
        can = can || {};

        var model = !!(can.files && can.simd && can.canvas);

        return {
            blur: !!can.native || (model && !!can.filter),
            remove: model
        };
    }

    // How a mode runs: 'native', 'model', or '' when it cannot here.
    function route(mode, can) {
        var shown = offer(can);

        if (modeRead(mode) === 'blur') {
            return (can && can.native) ? 'native' : (shown.blur ? 'model' : '');
        }

        if (modeRead(mode) === 'remove') {
            return shown.remove ? 'model' : '';
        }

        return '';
    }

    // The mask's alpha from the person's confidence (0-1) of each pixel;
    // the colour is white, only the alpha cuts.
    function alphaFill(rgba, confidence) {
        for (var i = 0, n = confidence.length; i < n; i++) {
            var value = confidence[i];

            rgba[i * 4] = 255;
            rgba[i * 4 + 1] = 255;
            rgba[i * 4 + 2] = 255;
            rgba[i * 4 + 3] = value <= 0 ? 0 : (value >= 1 ? 255 : Math.round(value * 255));
        }

        return rgba;
    }

    // A camera that blurs by itself lists true among backgroundBlur's values.
    function nativeFrom(capabilities) {
        var blur = capabilities && capabilities.backgroundBlur;

        return Array.isArray(blur) ? blur.indexOf(true) >= 0 : (blur === true);
    }

    // ── Browser parts ──

    function simd() {
        try {
            return !!(window.WebAssembly && WebAssembly.validate(new Uint8Array(SIMD_PROBE)));
        } catch (error) {
            return false;
        }
    }

    // What this browser can do with this camera track and these files.
    function can(track, files) {
        var caps = null;

        try {
            caps = (track && track.getCapabilities) ? track.getCapabilities() : null;
        } catch (error) {
            caps = null;
        }

        return {
            native: nativeFrom(caps),
            files: !!(files && files.bundle && files.loader && files.wasm && files.model),
            simd: simd(),
            canvas: !!(window.HTMLCanvasElement && HTMLCanvasElement.prototype.captureStream),
            filter: !!(window.CanvasRenderingContext2D && ('filter' in CanvasRenderingContext2D.prototype))
        };
    }

    // One segmenter a page, made the first time it is needed; a failed load
    // is tried again next time.
    function load(files) {
        if (!segmenter) {
            segmenter = import(files.bundle).then(function (vision) {
                return vision.ImageSegmenter.createFromOptions({ wasmLoaderPath: files.loader, wasmBinaryPath: files.wasm }, {
                    baseOptions: { modelAssetPath: files.model, delegate: 'CPU' },
                    runningMode: 'VIDEO',
                    outputCategoryMask: false,
                    outputConfidenceMasks: true
                });
            });
            segmenter.catch(function () { segmenter = null; });
        }

        return segmenter;
    }

    function canvas(width, height) {
        var node = document.createElement('canvas');

        node.width = width;
        node.height = height;

        return node;
    }

    // Draws the camera's frames with the effect on a canvas; its track is
    // what the call sends. The camera's own track is left running for the
    // caller to stop.
    function pipeline(track, mode, seg) {
        var settings = track.getSettings ? track.getSettings() : {};
        var out = canvas(settings.width || 640, settings.height || 480);
        var person = canvas(out.width, out.height);
        var mask = canvas(1, 1);
        var ctx = out.getContext('2d');
        var pctx = person.getContext('2d');
        var mctx = mask.getContext('2d');
        var video = document.createElement('video');
        var image = null;
        var stopped = false;
        var timer = 0;
        var last = 0;

        video.muted = true;
        video.playsInline = true;
        video.srcObject = new MediaStream([track]);
        video.play().catch(function () {});

        var draw = function (result) {
            var confidence = result && result.confidenceMasks && result.confidenceMasks[0];
            var width = out.width;
            var height = out.height;

            if (!confidence) {
                return;
            }

            if (!image || (image.width !== confidence.width) || (image.height !== confidence.height)) {
                mask.width = confidence.width;
                mask.height = confidence.height;
                image = mctx.createImageData(confidence.width, confidence.height);
            }

            alphaFill(image.data, confidence.getAsFloat32Array());
            mctx.putImageData(image, 0, 0);

            // The person: the mask, softened, then the frame inside it.
            pctx.globalCompositeOperation = 'copy';
            pctx.filter = 'blur(' + EDGE + 'px)';
            pctx.drawImage(mask, 0, 0, width, height);
            pctx.filter = 'none';
            pctx.globalCompositeOperation = 'source-in';
            pctx.drawImage(video, 0, 0, width, height);

            // The background, then the person over it.
            ctx.globalCompositeOperation = 'copy';

            if (mode === 'blur') {
                ctx.filter = 'blur(' + BLUR + 'px)';
                ctx.drawImage(video, 0, 0, width, height);
                ctx.filter = 'none';
            } else {
                ctx.fillStyle = REMOVED;
                ctx.fillRect(0, 0, width, height);
            }

            ctx.globalCompositeOperation = 'source-over';
            ctx.drawImage(person, 0, 0);
        };

        var frame = function () {
            if (stopped) {
                return;
            }

            timer = setTimeout(frame, 1000 / FPS);

            if ((video.readyState < 2) || !video.videoWidth) {
                return;
            }

            if ((out.width !== video.videoWidth) || (out.height !== video.videoHeight)) {
                out.width = person.width = video.videoWidth;
                out.height = person.height = video.videoHeight;
            }

            // The segmenter wants a timestamp that always grows.
            last = Math.max(last + 1, Math.round(performance.now()));

            try {
                seg.segmentForVideo(video, last, draw);
            } catch (error) {
                // A frame the segmenter refused is skipped.
            }
        };

        var sent = out.captureStream(FPS).getVideoTracks()[0];

        frame();

        return {
            track: sent,
            stop: function () {
                stopped = true;
                clearTimeout(timer);
                sent.stop();
                video.srcObject = null;
            }
        };
    }

    // Starts a mode on the camera's track: resolves { track, stop }, track
    // being the one to send (the same track for the camera's own blur).
    function apply(track, mode, files) {
        var how = route(mode, can(track, files));

        if (how === 'native') {
            var constraints = {};
            var current = track.getConstraints ? track.getConstraints() : {};

            Object.keys(current || {}).forEach(function (name) { constraints[name] = current[name]; });
            constraints.backgroundBlur = true;

            return track.applyConstraints(constraints).then(function () {
                return {
                    track: track,
                    stop: function () {
                        constraints.backgroundBlur = false;
                        track.applyConstraints(constraints).catch(function () {});
                    }
                };
            });
        }

        if (how !== 'model') {
            return Promise.reject(new Error('unsupported'));
        }

        return load(files).then(function (seg) {
            return pipeline(track, mode, seg);
        });
    }

    window.PGWsCallBackground = {
        can: can,
        apply: apply,
        // The pure parts, for the tests.
        modeRead: modeRead,
        offer: offer,
        route: route,
        alphaFill: alphaFill,
        nativeFrom: nativeFrom
    };
})();
