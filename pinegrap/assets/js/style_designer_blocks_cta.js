/**
 * Pinegrap - Enterprise Website Platform
 *
 * General call-to-action blocks the core's Marketing group lacks. The
 * sticky bar: a slim band that stays at the top of the window while the
 * visitor scrolls through the section it sits in, with a short title, a
 * line and the page's two main actions (order and test drive on a car
 * page, buy and compare on a product page, book and ask on a venue page).
 * The core's CTA: Banner, Inline and Gradient keep the bands that scroll
 * away with the page.
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
        // The bar lets the page show faintly through it where the browser
        // can blur what lies behind; elsewhere it is the page's own colour.
        'pg-sticky-bar': '.pg-sticky-bar { background-color: var(--bs-body-bg); } ' +
                         '@supports ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) { .pg-sticky-bar { background-color: rgba(var(--bs-body-bg-rgb), .86); -webkit-backdrop-filter: saturate(1.4) blur(12px); backdrop-filter: saturate(1.4) blur(12px); } }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'cta-sticky': ['pg-sticky-bar']
    };

    // ── Sticky bar ──────────────────────────────────────────────────────
    // The Sticky interaction keeps it at the top of the window; it sticks
    // within the element it is placed in, so it goes at the top of the
    // page's body or of the section it belongs to.
    var buildSticky = function () {
        var bar = kit.tag('section', 'pg-sticky-bar border-bottom py-2', _sdT('CTA (Sticky Bar)'), [
            kit.createNode('container', { fluid: false, cssClass: '' }, [
                kit.div('d-flex flex-wrap align-items-center gap-2 gap-md-3', _sdT('Bar'), [
                    kit.h('h2', _sdT('Ready when you are'), 'h6 mb-0 me-auto'),
                    kit.p(_sdT('Answers within one working day.'), 'small text-body-secondary mb-0 d-none d-md-block'),
                    kit.div('d-flex gap-2', _sdT('Buttons'), [
                        kit.link(_sdT('Get Started'), '#', 'btn btn-primary btn-sm px-3', _sdT('Primary Link')),
                        kit.link(_sdT('Ask a Question'), '#', 'btn btn-outline-secondary btn-sm px-3', _sdT('Secondary Link'))
                    ])
                ])
            ])
        ]);
        bar.props._fx = { effect: 'sticky' };
        return bar;
    };

    return {
        prefix: 'cta-',
        items: [
            { label: _sdT('CTA: Sticky Bar'), type: 'component', icon: 'bi-pin-angle', extra: { componentType: 'cta-sticky' } }
        ],
        build: function (ctype) {
            if (ctype === 'cta-sticky') return buildSticky();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
