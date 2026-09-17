/**
 * Pinegrap - Enterprise Website Platform
 *
 * Signature capture for signature form fields.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Points closer together than this are dropped. A finger on a touch screen
    // reports far more points than the shape needs, and every one of them is
    // stored and sent.
    var MIN_DISTANCE = 1.2;

    // Strings come from the page: this file is static and cannot call lang().
    function text(pad, key, fallback) {
        var value = pad.getAttribute('data-pg-' + key);

        return (value === null || value === '') ? fallback : value;
    }

    function setup(pad) {
        if (pad.getAttribute('data-pg-signature-ready') === '1') {
            return;
        }

        pad.setAttribute('data-pg-signature-ready', '1');

        var canvas = pad.querySelector('canvas');
        var valueInput = pad.querySelector('[data-pg-signature-value]');
        var strokeInput = pad.querySelector('[data-pg-signature-strokes]');
        var clearButton = pad.querySelector('[data-pg-signature-clear]');
        var status = pad.querySelector('[data-pg-signature-status]');

        if (!canvas || !valueInput || !strokeInput) {
            return;
        }

        var context = canvas.getContext('2d');
        var strokes = [];
        var current = null;
        var started = 0;
        var ratio = 1;

        // The canvas is sized in device pixels and scaled back with CSS, so the
        // line is not a stack of blurred squares on a phone. Redrawing on resize
        // rather than leaving the bitmap alone matters because changing width or
        // height clears a canvas.
        function size() {
            var box = canvas.getBoundingClientRect();

            if ((box.width < 1) || (box.height < 1)) {
                return;
            }

            ratio = window.devicePixelRatio || 1;

            canvas.width = Math.round(box.width * ratio);
            canvas.height = Math.round(box.height * ratio);

            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            context.lineWidth = 2;
            context.lineCap = 'round';
            context.lineJoin = 'round';
            context.strokeStyle = pad.getAttribute('data-pg-ink') || '#111111';

            redraw();
        }

        function redraw() {
            context.clearRect(0, 0, canvas.width, canvas.height);

            for (var i = 0; i < strokes.length; i++) {
                draw(strokes[i]);
            }
        }

        function draw(stroke) {
            if (stroke.length === 0) {
                return;
            }

            // A single tap is a dot, and a dot is a legitimate part of a
            // signature; stroking a zero-length path draws nothing.
            if (stroke.length === 1) {
                context.beginPath();
                context.arc(stroke[0][0], stroke[0][1], context.lineWidth / 2, 0, Math.PI * 2);
                context.fillStyle = context.strokeStyle;
                context.fill();

                return;
            }

            context.beginPath();
            context.moveTo(stroke[0][0], stroke[0][1]);

            for (var i = 1; i < stroke.length; i++) {
                context.lineTo(stroke[i][0], stroke[i][1]);
            }

            context.stroke();
        }

        function position(event) {
            var box = canvas.getBoundingClientRect();

            return [
                Math.round((event.clientX - box.left) * 100) / 100,
                Math.round((event.clientY - box.top) * 100) / 100
            ];
        }

        function begin(event) {
            if (pad.getAttribute('data-pg-signature-locked') === '1') {
                return;
            }

            // Only the primary button, and only a real pointer: a synthetic
            // event is not someone signing.
            if (event.button !== undefined && event.button !== 0) {
                return;
            }

            event.preventDefault();

            if (started === 0) {
                started = Date.now();
            }

            var point = position(event);

            current = [[point[0], point[1], Date.now() - started, pressure(event)]];
            strokes.push(current);

            if (canvas.setPointerCapture && event.pointerId !== undefined) {
                try { canvas.setPointerCapture(event.pointerId); } catch (e) {}
            }

            announce();
        }

        function extend(event) {
            if (!current) {
                return;
            }

            event.preventDefault();

            var point = position(event);
            var last = current[current.length - 1];
            var dx = point[0] - last[0];
            var dy = point[1] - last[1];

            if (((dx * dx) + (dy * dy)) < (MIN_DISTANCE * MIN_DISTANCE)) {
                return;
            }

            current.push([point[0], point[1], Date.now() - started, pressure(event)]);

            context.beginPath();
            context.moveTo(last[0], last[1]);
            context.lineTo(point[0], point[1]);
            context.stroke();
        }

        function finish() {
            if (!current) {
                return;
            }

            if (current.length === 1) {
                draw(current);
            }

            current = null;

            publish();
        }

        // A device without pressure reports a constant 0.5, and 0 for a mouse
        // button that is down. Neither is a reading, so both are stored as 0 and
        // the evidence does not claim a measurement it does not have.
        function pressure(event) {
            var value = event.pressure;

            if (typeof value !== 'number' || value === 0.5 || value === 0) {
                return 0;
            }

            return Math.round(value * 1000) / 1000;
        }

        // The stored image is cropped to the ink. An uncropped pad is mostly
        // empty pixels, and the crop is also what makes the signature usable at
        // a different size later without looking lost in its own box.
        function trimmed() {
            var minX = null, minY = null, maxX = null, maxY = null;

            for (var i = 0; i < strokes.length; i++) {
                for (var j = 0; j < strokes[i].length; j++) {
                    var x = strokes[i][j][0];
                    var y = strokes[i][j][1];

                    if (minX === null || x < minX) { minX = x; }
                    if (maxX === null || x > maxX) { maxX = x; }
                    if (minY === null || y < minY) { minY = y; }
                    if (maxY === null || y > maxY) { maxY = y; }
                }
            }

            if (minX === null) {
                return '';
            }

            var pad2 = 6;
            var left = Math.max(0, Math.floor(minX - pad2));
            var top = Math.max(0, Math.floor(minY - pad2));
            var right = Math.min(canvas.width / ratio, Math.ceil(maxX + pad2));
            var bottom = Math.min(canvas.height / ratio, Math.ceil(maxY + pad2));

            var width = Math.max(1, right - left);
            var height = Math.max(1, bottom - top);

            var out = document.createElement('canvas');
            out.width = Math.round(width * ratio);
            out.height = Math.round(height * ratio);

            var outContext = out.getContext('2d');
            outContext.drawImage(
                canvas,
                Math.round(left * ratio), Math.round(top * ratio),
                out.width, out.height,
                0, 0,
                out.width, out.height
            );

            return out.toDataURL('image/png');
        }

        function publish() {
            valueInput.value = trimmed();
            strokeInput.value = (strokes.length === 0) ? '' : JSON.stringify(strokes);

            announce();
        }

        function announce() {
            if (!status) {
                return;
            }

            status.textContent = (strokes.length === 0)
                ? text(pad, 'empty-text', '')
                : text(pad, 'signed-text', '');
        }

        function clear() {
            strokes = [];
            current = null;
            started = 0;

            redraw();
            publish();
        }

        canvas.addEventListener('pointerdown', begin);
        canvas.addEventListener('pointermove', extend);
        canvas.addEventListener('pointerup', finish);
        canvas.addEventListener('pointercancel', finish);
        canvas.addEventListener('pointerleave', finish);

        if (clearButton) {
            clearButton.addEventListener('click', function (event) {
                event.preventDefault();
                clear();
            });
        }

        // The pad keeps its size from the layout, and the layout can change after
        // load (a font arriving, a panel opening). Sizing once on load leaves the
        // bitmap and the box disagreeing, which reads as the line being offset
        // from the pointer.
        if (window.ResizeObserver) {
            new ResizeObserver(size).observe(canvas);
        } else {
            window.addEventListener('resize', size);
        }

        // A form that comes back with another field's error must come back with
        // the signature too. The drawing is rebuilt from the strokes rather than
        // from the image, so the evidence and the picture stay the same object.
        if (strokeInput.value !== '') {
            try {
                var restored = JSON.parse(strokeInput.value);

                if (Object.prototype.toString.call(restored) === '[object Array]') {
                    strokes = restored;
                }
            } catch (e) {}
        }

        // The strokes are the evidence, but a redisplay may only have the image
        // left. Drawing it back keeps the pad honest: the hidden input still holds
        // a signature, so the pad must not look empty.
        if ((strokes.length === 0) && (valueInput.value.indexOf('data:image/png') === 0)) {
            var restoredImage = new Image();

            restoredImage.onload = function () {
                context.drawImage(restoredImage, 0, 0, restoredImage.width / ratio, restoredImage.height / ratio);

                if (status) {
                    status.textContent = text(pad, 'signed-text', '');
                }
            };

            restoredImage.src = valueInput.value;
        }

        size();
        announce();
    }

    function setupAll() {
        var pads = document.querySelectorAll('[data-pg-signature]');

        for (var i = 0; i < pads.length; i++) {
            setup(pads[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupAll);
    } else {
        setupAll();
    }

    window.pgSignatureSetup = setupAll;
})();
