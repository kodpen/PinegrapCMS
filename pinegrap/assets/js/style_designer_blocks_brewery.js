/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual designer's Brewery blocks (prefix brewery-): the sections of a
 * brewery and taproom's site. A dark hero whose glass fills on load over a
 * photograph of the brewhouse, a tap board of numbered taps with each
 * beer's strength and bitterness, a tasting flight whose four glasses are
 * poured one after another from light to dark, and a fresh release told as
 * one chapter with its facts and tasting notes. The glass is drawn by one
 * rule (pg-brewery-glass) and the beer takes its colour from the palette,
 * so an amber palette pours amber beer and every other palette its own.
 * Motion comes from the nodes' interactions (props._fx, played by
 * pg_fx.src.js, which honours reduced motion).
 *
 * Registered with StyleDesigner.registerBlockFamily() and bundled after the
 * designer core (pg_designer_js_sources()); see
 * docs/memory/designer/designer-blocks-brewery.md.
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
        // A glass: a tapered shape tinted by the text colour, the beer at its
        // foot under a head of foam. The beer is a node of its own so an
        // interaction can pour it; its height comes from its own ratio, not
        // from a percentage, so it holds inside the canvas's wrappers too.
        // Tones 1 to 4 go from a pale straw to a stout, mixed from the
        // palette's primary colour; without color-mix() every tone is the
        // primary colour and the stout is the dark one.
        'pg-brewery-glass':   '.pg-brewery-glass { position: relative; display: flex; flex-direction: column; justify-content: flex-end; aspect-ratio: 3 / 5; overflow: hidden; ' +
                              'clip-path: polygon(0 0, 100% 0, 88% 100%, 12% 100%); background-color: var(--bs-secondary-bg); } ' +
                              '.pg-brewery-glass .pg-brewery-beer { display: block; width: 100%; aspect-ratio: 3 / 4.3; background-color: var(--bs-primary); ' +
                              'background-image: linear-gradient(to bottom, var(--bs-light) 0 13%, transparent 13%); } ' +
                              '.pg-brewery-glass .pg-brewery-tone-4 { background-color: var(--bs-dark); } ' +
                              '.pg-brewery-mini { flex: none; width: 1.75rem; } ' +
                              '@supports (background: color-mix(in srgb, red 50%, blue)) { ' +
                              '.pg-brewery-glass { background-color: color-mix(in srgb, var(--bs-body-color) 12%, transparent); } ' +
                              '.pg-brewery-glass::after { content: ""; position: absolute; inset: 0; pointer-events: none; ' +
                              'background: linear-gradient(100deg, transparent 22%, color-mix(in srgb, var(--bs-light) 45%, transparent) 28%, transparent 36%); } ' +
                              '.pg-brewery-glass .pg-brewery-beer { background-image: linear-gradient(to bottom, color-mix(in srgb, var(--bs-light) 88%, var(--bs-primary)) 0 13%, transparent 13%); } ' +
                              '.pg-brewery-glass .pg-brewery-tone-1 { background-color: color-mix(in srgb, var(--bs-primary) 55%, var(--bs-light)); } ' +
                              '.pg-brewery-glass .pg-brewery-tone-2 { background-color: var(--bs-primary); } ' +
                              '.pg-brewery-glass .pg-brewery-tone-3 { background-color: color-mix(in srgb, var(--bs-primary) 60%, var(--bs-dark)); } ' +
                              '.pg-brewery-glass .pg-brewery-tone-4 { background-color: color-mix(in srgb, var(--bs-primary) 18%, var(--bs-dark)); } }',
        // The hero's glass stands over the photograph's lower edge: pulled up
        // by a negative margin in the flow (no absolute position), centred on
        // a phone, over the left corner from lg up.
        'pg-brewery-pour':    '.pg-brewery-pour { position: relative; z-index: 1; width: clamp(5.5rem, 22vw, 9.5rem); margin: -7rem auto 0; } ' +
                              '@media (min-width: 992px) { .pg-brewery-pour { margin: -12rem 0 0 -2.5rem; } }',
        // A tall photograph.
        'pg-brewery-photo':   '.pg-brewery-photo { aspect-ratio: 4 / 5; }',
        // A tap's number in a ring of the text's colour.
        'pg-brewery-tap':     '.pg-brewery-tap { display: inline-flex; flex: none; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; ' +
                              'border: 2px solid currentColor; border-radius: 50%; font-variant-numeric: tabular-nums; }',
        // Strengths and figures that line up.
        'pg-brewery-figure':  '.pg-brewery-figure { font-variant-numeric: tabular-nums; }',
        // The flight's paddle: each taster stands on its part of one board,
        // the board drawn under the glasses of a row with no gutter.
        'pg-brewery-paddle':  '.pg-brewery-paddle .pg-brewery-slot { display: flex; justify-content: center; align-items: flex-end; min-height: 8.5rem; padding: 0 .5rem; ' +
                              'border-bottom: .75rem solid var(--bs-secondary-color); } ' +
                              '.pg-brewery-paddle .pg-brewery-taster { width: 4.5rem; max-width: 100%; clip-path: polygon(6% 0, 94% 0, 78% 100%, 22% 100%); } ' +
                              '@supports (background: color-mix(in srgb, red 50%, blue)) { .pg-brewery-paddle .pg-brewery-slot { border-bottom-color: color-mix(in srgb, var(--bs-primary) 30%, var(--bs-dark)); } }',
        // A round label stuck on the release's picture at a slant, pulled up
        // over the picture's lower corner.
        'pg-brewery-sticker': '.pg-brewery-sticker { position: relative; z-index: 1; display: flex; align-items: center; justify-content: center; width: 7.5rem; aspect-ratio: 1 / 1; ' +
                              'margin: -5rem 1rem 0 auto; padding: .75rem; border-radius: 50%; text-align: center; line-height: 1.15; transform: rotate(-10deg); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'brewery-hero':    ['pg-brewery-glass', 'pg-brewery-pour', 'pg-brewery-photo'],
        'brewery-taps':    ['pg-brewery-glass', 'pg-brewery-tap', 'pg-brewery-figure'],
        'brewery-flight':  ['pg-brewery-glass', 'pg-brewery-paddle', 'pg-brewery-figure'],
        'brewery-release': ['pg-brewery-photo', 'pg-brewery-sticker', 'pg-brewery-figure']
    };

    // A node with an interaction (props._fx, designer-interactions.md).
    var fx = function (node, effect) {
        node.props._fx = effect;
        return node;
    };
    // A dark band: the palette's dark colours apply inside it.
    var dark = [{ name: 'data-bs-theme', value: 'dark' }];
    // The glasses are decoration: the words beside them say the style.
    var hidden = [{ name: 'aria-hidden', value: 'true' }];
    var eyebrow = function (text, cls) {
        return kit.h('span', text, 'd-block mb-3 small fw-semibold text-uppercase ' + (cls || 'text-primary'));
    };
    // The beer inside a glass, in tone 1 (pale) to 4 (dark).
    var beer = function (tone) {
        return kit.div('pg-brewery-beer pg-brewery-tone-' + tone, _sdT('Beer'));
    };
    // A strength figure and the word after it.
    var abv = function (figure, cls) {
        return kit.div('d-flex align-items-baseline gap-1', _sdT('Beer Strength'), [
            kit.h('span', figure, 'pg-brewery-figure fw-bold ' + (cls || 'fs-4')),
            kit.h('span', _sdT('ABV'), 'small text-body-secondary')
        ]);
    };

    return {
        prefix: 'brewery-',
        group: { title: _sdT('Brewery'), icon: 'bi-cup-straw' },
        items: [
            { label: _sdT('Brewery: Pour Hero'),     type: 'component', icon: 'bi-cup-straw',  extra: { componentType: 'brewery-hero' } },
            { label: _sdT('Brewery: Tap Board'),     type: 'component', icon: 'bi-list-ol',    extra: { componentType: 'brewery-taps' } },
            { label: _sdT('Brewery: Tasting Flight'), type: 'component', icon: 'bi-cup',       extra: { componentType: 'brewery-flight' } },
            { label: _sdT('Brewery: Fresh Release'), type: 'component', icon: 'bi-stars',      extra: { componentType: 'brewery-release' } }
        ],
        build: function (ctype) {

            // Brewery: Pour Hero — a dark band: a very large line, a short one,
            // two buttons and three facts rise on load; beside them a tall
            // photograph of the brewhouse fades in and the glass over its
            // corner fills from the foot up.
            if (ctype === 'brewery-hero') {
                var _bhFact = function (icon, text) {
                    return kit.tag('li', 'd-flex align-items-center gap-2', _sdT('Fact'), [
                        kit.icon(icon, 'text-primary'),
                        kit.h('span', text)
                    ]);
                };
                return kit.tag('section', 'py-5 bg-body text-body overflow-hidden', _sdT('Brewery Hero'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-5' }, [
                        kit.row(_sdT('Hero Row'), [
                            kit.col(_sdT('Hero Text'), [
                                fx(kit.div('', _sdT('Hero Words'), [
                                    eyebrow(_sdT('Brewery and taproom')),
                                    kit.h('h1', _sdT('Fresh from the tank.'), 'display-1 fw-bold text-uppercase lh-1 mb-4'),
                                    kit.p(_sdT('Small-batch beer brewed a few steps from the bar and poured the day it is ready.'), 'lead text-body-secondary mb-4'),
                                    kit.div('d-flex flex-wrap gap-3', _sdT('Hero Buttons'), [
                                        kit.btn(_sdT('See what is on tap'), 'primary', 'lg'),
                                        kit.btn(_sdT('Book a tour'), 'secondary', 'lg')
                                    ])
                                ]), { effect: 'slide-up', trigger: 'load', duration: 900 }),
                                fx(kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-3 gap-lg-4 small mt-5 mb-0', _sdT('Facts'), [
                                    _bhFact('bi-cup-straw', _sdT('Six beers on tap')),
                                    _bhFact('bi-signpost', _sdT('Brewery tours on Saturdays')),
                                    _bhFact('bi-clock', _sdT('Taproom open from noon'))
                                ]), { effect: 'fade', trigger: 'load', duration: 800, delay: 600, stagger: 120 })
                            ], '6'),
                            kit.col(_sdT('Hero Picture'), [
                                kit.div('px-4 px-lg-0 pb-5 pb-lg-0', _sdT('Picture Stage'), [
                                    fx(kit.img('brewery-hero', 960, 1200, _sdT('Copper brewing kettles in a brick brewhouse'), 'pg-brewery-photo w-100 object-fit-cover rounded-4 shadow-lg'),
                                        { effect: 'fade', trigger: 'load', duration: 1200, delay: 200 }),
                                    kit.div('pg-brewery-glass pg-brewery-pour', _sdT('Beer Glass'), [
                                        fx(beer(2), { effect: 'slide-up', trigger: 'load', duration: 1800, delay: 700, distance: 400 })
                                    ], hidden)
                                ])
                            ], '5', '', '', 'offset-lg-1')
                        ], 'align-items-center g-lg-5')
                    ])
                ], dark);
            }

            // Brewery: Tap Board — a dark board of numbered taps, two columns
            // from lg up: each beer with a small glass of its colour, its
            // style, a badge where it has news, its strength and bitterness.
            // The taps come in one after another as the board scrolls in.
            if (ctype === 'brewery-taps') {
                var _btTap = function (n, tone, name, style, strength, ibu, badge, badgeCls) {
                    var title = [ kit.h('h3', name, 'h5 fw-bold mb-0') ];
                    if (badge) title.push(kit.h('span', badge, 'badge rounded-pill ' + badgeCls));
                    return kit.tag('li', 'col', _sdT('Tap {var}', n), [
                        kit.div('d-flex align-items-center gap-3 py-3 border-bottom', _sdT('Tap Row'), [
                            kit.h('span', '0' + n, 'pg-brewery-tap text-primary fw-bold'),
                            kit.div('pg-brewery-glass pg-brewery-mini', _sdT('Beer Glass'), [ beer(tone) ], hidden),
                            kit.div('flex-grow-1', _sdT('Beer'), [
                                kit.div('d-flex flex-wrap align-items-center gap-2 mb-1', _sdT('Beer Name'), title),
                                kit.p(style, 'small text-body-secondary mb-0')
                            ]),
                            kit.div('text-end', _sdT('Facts'), [
                                abv(strength),
                                kit.h('span', _sdT('{var} IBU', ibu), 'pg-brewery-figure small text-body-secondary')
                            ])
                        ])
                    ]);
                };
                return kit.tag('section', 'py-5 bg-body text-body', _sdT('Brewery Tap Board'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                        fx(kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Section Header'), [
                            kit.div('', _sdT('Header Words'), [
                                eyebrow(_sdT('On tap now')),
                                kit.h('h2', _sdT('Six taps, poured fresh.'), 'display-5 fw-bold mb-0')
                            ]),
                            kit.p(_sdT('Taps change when a keg runs out: ask at the bar what is next.'), 'text-body-secondary mb-0 col-lg-4')
                        ]), { effect: 'fade', trigger: 'view', duration: 700 }),
                        fx(kit.tag('ol', 'list-unstyled row row-cols-1 row-cols-lg-2 gx-lg-5 mb-0', _sdT('Taps'), [
                            _btTap(1, 1, _sdT('Harbour Lager'), _sdT('Lager'), '4.8%', '22'),
                            _btTap(2, 2, _sdT('Crane Pale Ale'), _sdT('Pale Ale'), '5.2%', '35'),
                            _btTap(3, 2, _sdT('Lighthouse IPA'), _sdT('IPA'), '6.5%', '60', _sdT('Fresh keg'), 'text-bg-primary'),
                            _btTap(4, 1, _sdT('Dockside Wheat'), _sdT('Wheat Beer'), '5.0%', '14'),
                            _btTap(5, 3, _sdT('Low Tide Sour'), _sdT('Sour'), '4.2%', '8', _sdT('Last keg'), 'bg-body-secondary text-body-emphasis border'),
                            _btTap(6, 4, _sdT('Anchor Stout'), _sdT('Stout'), '6.0%', '40', _sdT('Seasonal'), 'bg-primary-subtle text-primary-emphasis')
                        ]), { effect: 'slide-up', trigger: 'view', duration: 600, stagger: 90, distance: 24 })
                    ])
                ], dark);
            }

            // Brewery: Tasting Flight — four tasters on one paddle, light to
            // dark; each glass fills a moment after the one before it as the
            // paddle scrolls in. Beside them what a flight is and the way to
            // a tasting.
            if (ctype === 'brewery-flight') {
                var _bfTaster = function (n, name, style, strength) {
                    return kit.tag('li', 'col text-center', _sdT('Taster {var}', n), [
                        kit.div('pg-brewery-slot', _sdT('Paddle'), [
                            kit.div('pg-brewery-glass pg-brewery-taster', _sdT('Beer Glass'), [
                                fx(beer(n), { effect: 'slide-up', trigger: 'view', duration: 1200, delay: 150 + n * 250, distance: 200 })
                            ], hidden)
                        ]),
                        kit.div('pt-3 px-2', _sdT('Taster Words'), [
                            kit.h('h3', name, 'h6 fw-bold mb-1'),
                            kit.p(style, 'small text-body-secondary mb-1'),
                            kit.div('d-flex justify-content-center', _sdT('Beer Strength'), [ abv(strength, 'fs-6') ])
                        ])
                    ]);
                };
                var _bfTick = function (text) {
                    return kit.tag('li', 'd-flex gap-2 mb-2', _sdT('Item'), [
                        kit.icon('bi-check2-circle', 'text-primary'),
                        kit.h('span', text)
                    ]);
                };
                return kit.section('py-5', _sdT('Brewery Tasting Flight'), [
                    kit.row(_sdT('Flight Row'), [
                        kit.col(_sdT('Flight Words'), [
                            fx(kit.div('', _sdT('Flight Copy'), [
                                eyebrow(_sdT('Tasting flight')),
                                kit.h('h2', _sdT('Four beers, one paddle.'), 'display-5 fw-bold mb-3'),
                                kit.p(_sdT('Four small glasses poured from light to dark, so you taste the whole tap list before you pick a pint.'), 'lead text-body-secondary mb-4'),
                                kit.tag('ul', 'list-unstyled mb-4', _sdT('Flight Facts'), [
                                    _bfTick(_sdT('Four tasters of 150 ml each')),
                                    _bfTick(_sdT('The beers change with the taps')),
                                    _bfTick(_sdT('Water and bread to clear the palate'))
                                ]),
                                kit.div('d-flex flex-wrap gap-3', _sdT('Flight Buttons'), [
                                    kit.btn(_sdT('Book a tasting'), 'primary', 'lg'),
                                    kit.btn(_sdT('See all beers'), 'outline-primary', 'lg')
                                ])
                            ]), { effect: 'slide-up', trigger: 'view', duration: 700 })
                        ], '5'),
                        kit.col(_sdT('Flight Paddle'), [
                            kit.tag('ol', 'list-unstyled row row-cols-2 row-cols-md-4 g-0 mb-0 pg-brewery-paddle', _sdT('Flight'), [
                                _bfTaster(1, _sdT('Harbour Lager'), _sdT('Lager'), '4.8%'),
                                _bfTaster(2, _sdT('Crane Pale Ale'), _sdT('Pale Ale'), '5.2%'),
                                _bfTaster(3, _sdT('Low Tide Sour'), _sdT('Sour'), '4.2%'),
                                _bfTaster(4, _sdT('Anchor Stout'), _sdT('Stout'), '6.0%')
                            ])
                        ], '6', '', '', 'offset-lg-1')
                    ], 'align-items-center g-lg-5')
                ]);
            }

            // Brewery: Fresh Release — one new beer as a chapter: its name very
            // large, its strength, bitterness and batch as large figures that
            // rise one after another, its tasting notes and the ways to buy
            // or drink it; the can's picture with a sticker moves slower than
            // the page.
            if (ctype === 'brewery-release') {
                var _brFact = function (n, label, figure) {
                    return kit.div('col border-top border-2 pt-3', _sdT('Fact {var}', n), [
                        kit.tag('dt', 'small fw-normal text-body-secondary mb-1', _sdT('Fact Label'), [ kit.h('span', label) ]),
                        kit.tag('dd', 'pg-brewery-figure fs-2 fw-bold lh-1 mb-0', _sdT('Fact Figure'), [ kit.h('span', figure) ])
                    ]);
                };
                return kit.tag('section', 'py-5 bg-body-tertiary overflow-hidden', _sdT('Brewery Release'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-5' }, [
                        kit.row(_sdT('Release Row'), [
                            kit.col(_sdT('Release Picture'), [
                                fx(kit.div('px-4 px-lg-0', _sdT('Picture Stage'), [
                                    kit.img('brewery-release', 960, 1200, _sdT('A can of sour beer beside a glass on the bar'), 'pg-brewery-photo w-100 object-fit-cover rounded-4 shadow'),
                                    kit.div('pg-brewery-sticker text-bg-primary small fw-bold text-uppercase shadow', _sdT('Sticker'), [
                                        kit.h('span', _sdT('Limited release'))
                                    ])
                                ]), { effect: 'parallax', speed: 15 })
                            ], '5'),
                            kit.col(_sdT('Release Words'), [
                                eyebrow(_sdT('Fresh release'), 'text-body-emphasis'),
                                kit.h('h2', _sdT('Low Tide Sour'), 'display-2 fw-bold text-uppercase lh-1 mb-3'),
                                kit.p(_sdT('A tart summer sour with sour cherries from the hills behind the town.'), 'lead text-body-secondary mb-4'),
                                fx(kit.tag('dl', 'row row-cols-3 g-3 mb-4', _sdT('Release Facts'), [
                                    _brFact(1, _sdT('Alcohol'), '4.2%'),
                                    _brFact(2, _sdT('Bitterness'), _sdT('{var} IBU', '8')),
                                    _brFact(3, _sdT('Batch'), _sdT('{var} cans', '1,200'))
                                ]), { effect: 'slide-up', trigger: 'view', duration: 600, stagger: 120 }),
                                kit.h('h3', _sdT('Tasting notes'), 'h6 fw-semibold text-uppercase text-body-secondary mb-2'),
                                kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-4', _sdT('Notes'), [_sdT('Sour cherry'), _sdT('Lemon'), _sdT('Light, dry finish')].map(function (t) {
                                    return kit.tag('li', 'badge rounded-pill bg-body text-body-emphasis border fw-normal', _sdT('Note'), [ kit.h('span', t) ]);
                                })),
                                kit.div('d-flex flex-wrap gap-3', _sdT('Release Buttons'), [
                                    kit.btn(_sdT('Buy a pack'), 'primary', 'lg'),
                                    kit.btn(_sdT('Find it on tap'), 'secondary', 'lg')
                                ])
                            ], '6', '', '', 'offset-lg-1')
                        ], 'align-items-center g-lg-5')
                    ])
                ]);
            }

            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
