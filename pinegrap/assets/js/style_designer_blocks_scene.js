/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scroll scenes: blocks that tell a story while the page scrolls. Pinned
 * Story is a tall section whose stage stays on the screen while its layers
 * (a picture and a short text each) take turns: the scene interaction
 * (pg_fx.src.js) writes the step the scroll has reached and marks that
 * step's layer, and the pg-scene rule pins the stage and shows the marked
 * layer alone. Without the script, under reduced motion and on the
 * editor's canvas the layers stand one under the other as plain bands.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.registerBlockFamily(function (kit) {
    'use strict';
    var _sdT = kit._sdT;

    var _SD_BLOCK_CSS = {
        // Only while the script has written a step: the section is one
        // window tall per layer, the stage sticks to the window's top and
        // the layers share its one grid cell, the marked one shown. The
        // picture shrinks so text and picture fit one window on a phone.
        'pg-scene': '.pg-scene[data-pg-scene-step] { height: calc(var(--pg-scene-steps, 3) * 100vh); height: calc(var(--pg-scene-steps, 3) * 100svh); }\n' +
                    '.pg-scene[data-pg-scene-step] .pg-scene-stage { position: sticky; top: 0; display: grid; height: 100vh; height: 100svh; overflow: hidden; }\n' +
                    '.pg-scene[data-pg-scene-step] [data-pg-scene-layer] { grid-area: 1 / 1; transition: opacity .6s ease, visibility .6s; }\n' +
                    '.pg-scene[data-pg-scene-step] [data-pg-scene-layer]:not([data-pg-scene-on]) { opacity: 0; visibility: hidden; }\n' +
                    '.pg-scene[data-pg-scene-step] .pg-scene-shot { max-height: 45svh; width: 100%; object-fit: cover; }\n' +
                    '@media (prefers-reduced-motion: reduce) { .pg-scene [data-pg-scene-layer] { transition: none; } }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'scene-pinned-story': ['pg-scene']
    };

    // ── Pinned Story ────────────────────────────────────────────────────
    // One layer: its number, a heading and a line beside a picture; the
    // picture comes first on a phone, the text beside it from lg up.
    var layer = function (n, seed, title, text, alt) {
        return kit.div('d-flex align-items-center py-5', _sdT('Scene Layer {var}', n), [
            kit.createNode('container', { fluid: false, cssClass: '' }, [
                kit.row(_sdT('Layer Row'), [
                    kit.col(_sdT('Layer Copy'), [
                        kit.p('0' + n, 'fs-5 fw-semibold text-primary mb-2'),
                        kit.h('h2', title, 'display-5 fw-bold mb-3'),
                        kit.p(text, 'lead text-body-secondary mb-0')
                    ], '5', '', '', 'order-2 order-lg-1'),
                    kit.col(_sdT('Layer Picture'), [
                        kit.img(seed, 1200, 900, alt, 'img-fluid rounded-4 shadow pg-scene-shot')
                    ], '7', '', '', 'order-1 order-lg-2')
                ], 'align-items-center g-4 g-lg-5')
            ])
        ], [{ name: 'data-pg-scene-layer', value: '' }]);
    };
    var buildPinnedStory = function () {
        var section = kit.tag('section', 'pg-scene bg-body', _sdT('Pinned Story'), [
            kit.div('pg-scene-stage', _sdT('Scene Stage'), [
                layer(1, 'scene-sketch', _sdT('Shaped by hand'), _sdT('Every curve starts as a sketch and ends as a part you can hold.'), _sdT('Designer sketching a product on paper')),
                layer(2, 'scene-field', _sdT('Tested outside'), _sdT('Months in rain and frost before a single one ships.'), _sdT('Product being tested outdoors in the rain')),
                layer(3, 'scene-repair', _sdT('Made to last'), _sdT('Parts you can replace, and repairs for as long as you own it.'), _sdT('Hands replacing a part of the product'))
            ])
        ]);
        section.props._fx = { effect: 'scene' };
        return section;
    };

    return {
        prefix: 'scene-',
        group: { title: _sdT('Scroll Scenes'), icon: 'bi-film' },
        items: [
            { label: _sdT('Pinned Story'), type: 'component', icon: 'bi-layers', extra: { componentType: 'scene-pinned-story' },
              desc: _sdT('A section that stays on the screen while the page scrolls, showing three pictures with their text one after another.') }
        ],
        build: function (ctype) {
            if (ctype === 'scene-pinned-story') return buildPinnedStory();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
