/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the Animate tool on a phone once the document holds a
 * video. The sheet holds the play bar, then one list of rows: each name
 * above its lane under one time ruler, the playhead across them all, the
 * clip's row tall with finger-size trim grips, and the sound rows folded
 * into one line ("Sound · 2 rows") that opens them. Split joins Duplicate
 * and Delete in the selection bar (pg_image_editor_actions.js).
 *
 * The rows are the tracks' and the sound part's own nodes, put into the
 * sheet instead of the stage's dock, so their drags, snapping and undo stay
 * theirs; this part only keeps the sheet from being drawn again under a
 * finger that has just pressed a bar.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor_upload.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, icon = X.icon;

    // The tracks' and the sound rows' own query: a phone either way up.
    var PHONE = '(max-width: 599.98px), (max-height: 499.98px)';

    function onPhone() {
        return !!(window.matchMedia && window.matchMedia(PHONE).matches);
    }

    // Whether the sheet holds the rows: a phone, the Animate tool, a clip.
    Editor.prototype.phoneRowsOn = function () {
        return onPhone() && (this.tool === 'motion') && M.phoneRows(this.doc.layers);
    };

    // On a phone the tracks and the sound rows show only in the sheet's
    // list, and only with a clip; elsewhere as before.
    var tracksShown = Editor.prototype.tracksShown;

    Editor.prototype.tracksShown = function () {
        return onPhone() ? this.phoneRowsOn() : tracksShown.call(this);
    };

    var soundsShown = Editor.prototype.soundsShown;

    Editor.prototype.soundsShown = function () {
        return onPhone() ? this.phoneRowsOn() : soundsShown.call(this);
    };

    // The rows under the play bar: the tracks, then the sounds folded into
    // one line while they are closed.
    Editor.prototype.phoneRowsNode = function () {
        var self = this, tracks = this.tracksNode(), sounds = this.soundsNode(), n = this.sounds().length;
        var line = M.phoneSoundLine(n), open = !!this.phoneSoundsOpen, parts = [];

        if (tracks) {
            parts.push(tracks);
        }

        if (sounds) {
            var label = t(line.key).replace('{n}', String(line.n));
            var fold = el('button', { type: 'button', class: 'pgie-phfold' + (open ? ' on' : ''), 'aria-expanded': open ? 'true' : 'false', onclick: function () {
                self.phoneSoundsOpen = !self.phoneSoundsOpen;
                self.renderPanel();
            } }, [icon('music'), el('span', { text: label }), el('span', { class: 'pgie-phwave', 'aria-hidden': 'true' }), icon('chevron')]);

            parts.push(fold);
            sounds.hidden = !open;
            parts.push(sounds);
        }

        this.phoneEls = parts.length ? el('div', { class: 'pgie-phrows' }, parts) : null;

        return this.phoneEls;
    };

    // A press on a bar selects it and draws the panel again before its drag
    // starts: on a phone that would replace the bar under the finger, so the
    // sheet is kept until the release (which draws everything again).
    ['trackDown', 'clipDown', 'soundDown'].forEach(function (name) {
        var down = Editor.prototype[name];

        if (!down) {
            return;
        }

        Editor.prototype[name] = function () {
            // A press while playing only pauses, which draws the sheet anew.
            this.phoneHold = !this.playing && !!this.phoneEls && this.phoneEls.isConnected;

            try {
                return down.apply(this, arguments);
            } finally {
                this.phoneHold = false;
            }
        };
    });

    var renderPanel = Editor.prototype.renderPanel;

    Editor.prototype.renderPanel = function () {
        var held = this.phoneEls && this.phoneEls.isConnected;

        if (held && (this.phoneHold || this.trackDragging || this.soundDragging)) {
            return;
        }

        // Drawn again, the sheet keeps where it was scrolled to.
        var scroller = this.panel ? this.panel.querySelector('.pgie-pb') : null, top = this.panel ? this.panel.scrollTop : 0;
        var inner = scroller ? scroller.scrollTop : 0;

        renderPanel.apply(this, arguments);
        this.phoneEls = null;

        if (!this.panel || !this.phoneRowsOn()) {
            return;
        }

        var bar = this.panel.querySelector('.pgie-mbar'), rows = bar ? this.phoneRowsNode() : null;

        if (rows) {
            bar.parentNode.insertBefore(rows, bar.nextSibling);
        }

        if (held) {
            this.panel.scrollTop = top;
            scroller = this.panel.querySelector('.pgie-pb');

            if (scroller) {
                scroller.scrollTop = inner;
            }
        }
    };

    X.icons.chevron = X.icons.chevron || 'M9 6l6 6-6 6';

    Editor.inits.push(function () {
        this.phoneEls = null;
        this.phoneHold = false;
        this.phoneSoundsOpen = false;
    });
}(window.PgImageEditor.parts));
