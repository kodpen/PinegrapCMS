/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual designer's Product Launch blocks (prefix launch-): the sections
 * of a product page in the manner of a phone launch. A dark hero that comes
 * in on load, a sticky picture beside panels revealed on scroll, a camera
 * band with parallax, a spec comparison with a sticky head row, an
 * in-the-box strip and the buy band. Motion comes from the nodes'
 * interactions (props._fx, played by pg_fx.src.js, which honours reduced
 * motion); the feature tile grid is the Creative group's bento-features with
 * an interaction on its row, and the finish switch is the Marketing group's
 * Variant Picker (variant-picker).
 *
 * Registered with StyleDesigner.registerBlockFamily() and bundled after the
 * designer core (pg_designer_js_sources()); see
 * docs/memory/designer/designer-blocks-launch.md.
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
        // The hero's phone: as tall as the band allows, never wider than its column.
        'pg-launch-device':  '.pg-launch-device { width: auto; max-width: 100%; max-height: 70vh; object-fit: contain; }',
        // A soft glow of the palette behind the hero's phone; a plain body
        // colour where color-mix() is not known.
        'pg-launch-glow':    '.pg-launch-glow { background: radial-gradient(circle at 50% 35%, var(--bs-secondary-bg) 0, transparent 60%); } ' +
                             '@supports (background: color-mix(in srgb, red 50%, blue)) { .pg-launch-glow { background: radial-gradient(circle at 50% 35%, color-mix(in srgb, var(--bs-primary) 35%, transparent) 0, transparent 60%); } }',
        // The showcase's panels: each about one screen tall from 992 px up, so the
        // sticky picture beside them stays in view while they pass.
        'pg-launch-panel':   '@media (min-width: 992px) { .pg-launch-panel { min-height: 70vh; display: flex; flex-direction: column; justify-content: center; } }',
        // The camera band: a tall picture band whose photo keeps its middle.
        'pg-launch-band':    '.pg-launch-band { min-height: 80vh; background-size: cover; background-position: center; }',
        // Figures that line up: tabular digits in the spec table and the figure rows.
        'pg-launch-figure':  '.pg-launch-figure { font-variant-numeric: tabular-nums; }',
        // The spec table: scrolls sideways on a phone; from 992 px up it fits
        // and its head row stays at the top of the window while the rows pass.
        'pg-launch-specs':   '.pg-launch-specs { overflow-x: auto; } .pg-launch-specs table { font-variant-numeric: tabular-nums; } ' +
                             '@media (min-width: 992px) { .pg-launch-specs { overflow: visible; } .pg-launch-specs thead th { position: sticky; top: 0; z-index: 1; background-color: var(--bs-body-bg); } }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'launch-hero':       ['pg-launch-device', 'pg-launch-glow', 'pg-launch-figure'],
        'launch-showcase':   ['pg-launch-panel', 'pg-ratio-1x1'],
        'launch-camera':     ['pg-launch-band', 'pg-shade-bottom', 'pg-launch-figure'],
        'launch-specs':      ['pg-launch-specs'],
        'launch-box':        ['pg-ratio-1x1'],
        'launch-buy':        ['pg-launch-figure', 'pg-ratio-1x1']
    };

    // A node with an interaction (props._fx, designer-interactions.md).
    var fx = function (node, effect) {
        node.props._fx = effect;
        return node;
    };
    // A dark band: the palette's dark colours apply inside it.
    var dark = [{ name: 'data-bs-theme', value: 'dark' }];
    // Price and period are two nodes, so neither is written into the other's key.
    var price = function (amount, after, cls) {
        return kit.div('d-flex flex-wrap align-items-baseline gap-1', _sdT('Price'), [
            kit.h('span', amount, 'fw-semibold pg-launch-figure' + (cls ? ' ' + cls : '')),
            kit.h('span', after, 'small text-body-secondary')
        ]);
    };

    return {
        prefix: 'launch-',
        group: { title: _sdT('Product Launch'), icon: 'bi-phone' },
        items: [
            { label: _sdT('Launch: Product Hero'),     type: 'component', icon: 'bi-phone',              extra: { componentType: 'launch-hero' } },
            { label: _sdT('Launch: Sticky Showcase'),  type: 'component', icon: 'bi-layout-sidebar',     extra: { componentType: 'launch-showcase' } },
            { label: _sdT('Launch: Camera Band'),      type: 'component', icon: 'bi-camera',             extra: { componentType: 'launch-camera' } },
            { label: _sdT('Launch: Spec Comparison'),  type: 'component', icon: 'bi-table',              extra: { componentType: 'launch-specs' } },
            { label: _sdT('Launch: In the Box'),       type: 'component', icon: 'bi-box-seam',           extra: { componentType: 'launch-box' } },
            { label: _sdT('Launch: Buy Band'),         type: 'component', icon: 'bi-bag',                extra: { componentType: 'launch-buy' } }
        ],
        build: function (ctype) {

            // Launch: Product Hero — a dark band: name, line, price and two
            // buttons that rise in on load, beside a large phone that scales in.
            if (ctype === 'launch-hero') {
                return kit.tag('section', 'py-5 bg-body text-body overflow-hidden', _sdT('Launch Hero'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-5' }, [
                        kit.row(_sdT('Hero Row'), [
                            kit.col(_sdT('Hero Copy'), [
                                fx(kit.div('', _sdT('Hero Text'), [
                                    kit.h('span', _sdT('New'), 'd-inline-block mb-3 small fw-semibold text-uppercase text-primary'),
                                    kit.h('h1', _sdT('ACME One'), 'display-3 fw-bold mb-3'),
                                    kit.p(_sdT('Thinner, brighter, and built to last all day.'), 'lead text-body-secondary mb-4'),
                                    price(_sdT('From $799'), _sdT('or $33.29/mo. for 24 mo.'), 'fs-5'),
                                    kit.div('d-flex flex-wrap gap-3 mt-4', _sdT('Hero Buttons'), [
                                        kit.btn(_sdT('Buy'), 'primary', 'lg'),
                                        kit.btn(_sdT('Learn more'), 'secondary', 'lg')
                                    ])
                                ]), { effect: 'slide-up', trigger: 'load', duration: 800, stagger: 120 })
                            ], '6', '', '', 'order-2 order-lg-1 text-center text-lg-start'),
                            kit.col(_sdT('Hero Picture'), [
                                fx(kit.div('pg-launch-glow text-center', _sdT('Phone Stage'), [
                                    kit.img('launch-hero-phone', 600, 1100, _sdT('The ACME One, seen from the front and the back'), 'img-fluid pg-launch-device')
                                ]), { effect: 'scale', trigger: 'load', duration: 1000 })
                            ], '6', '', '', 'order-1 order-lg-2')
                        ], 'align-items-center')
                    ])
                ], dark);
            }

            // Launch: Sticky Showcase — the phone stays beside the text from
            // 992 px up while panels, one screen each, slide up as they come in.
            if (ctype === 'launch-showcase') {
                var _lsPanel = function (n, eyebrow, title, text) {
                    return fx(kit.div('pg-launch-panel py-4', _sdT('Panel {var}', n), [
                        kit.h('span', eyebrow, 'd-block mb-2 small fw-semibold text-uppercase text-primary'),
                        kit.h('h3', title, 'h2 fw-bold mb-3'),
                        kit.p(text, 'fs-5 text-body-secondary mb-0')
                    ]), { effect: 'slide-up', trigger: 'view', duration: 700 });
                };
                return kit.section('py-5', _sdT('Launch Showcase'), [
                    kit.div('mb-4 mb-lg-0', _sdT('Section Header'), [
                        kit.h('h2', _sdT('Designed around you'), 'display-5 fw-bold mb-2'),
                        kit.p(_sdT('Every part of the ACME One was made to feel effortless.'), 'lead text-body-secondary mb-0')
                    ]),
                    kit.row(_sdT('Showcase Row'), [
                        kit.col(_sdT('Picture Column'), [
                            fx(kit.div('py-lg-5', _sdT('Sticky Picture'), [
                                kit.img('launch-showcase', 900, 900, _sdT('The ACME One lying on a table'), 'img-fluid w-100 rounded-4 object-fit-cover pg-ratio-1x1')
                            ]), { effect: 'sticky', offset: 96 })
                        ], '6'),
                        kit.col(_sdT('Panels Column'), [
                            _lsPanel(1, _sdT('Display'), _sdT('A screen that keeps up'), _sdT('A 6.1-inch display that scrolls smoothly and stays clear in bright sunlight.')),
                            _lsPanel(2, _sdT('Chip'), _sdT('Fast at everything'), _sdT('The new chip opens apps in an instant and still saves battery.')),
                            _lsPanel(3, _sdT('Battery'), _sdT('All day, and then some'), _sdT('Up to 22 hours of video, and half a charge in 30 minutes.'))
                        ], '6')
                    ])
                ]);
            }

            // Launch: Camera Band — a tall photo band that moves slower than the
            // page, its text and three figures on the shared scrim.
            if (ctype === 'launch-camera') {
                var _lcFigure = function (n, value, label) {
                    return kit.col(_sdT('Figure {var}', n), [
                        kit.h('span', value, 'd-block display-6 fw-bold pg-launch-figure'),
                        kit.h('span', label, 'd-block small text-body-secondary')
                    ], '', '', '4');
                };
                // The photo sits in the style attribute, the Background control's store,
                // as on Hero: Image Background.
                return fx(kit.tag('section', 'd-flex pg-launch-band', _sdT('Launch Camera'), [
                    kit.div('d-flex flex-column justify-content-end w-100 py-5 text-body pg-shade-bottom', _sdT('Overlay'), [
                        kit.createNode('container', { fluid: false, cssClass: '' }, [
                            kit.h('span', _sdT('Camera'), 'd-block mb-2 small fw-semibold text-uppercase text-primary'),
                            kit.h('h2', _sdT('Pro photos without the pro'), 'display-5 fw-bold mb-3'),
                            kit.p(_sdT('A main camera that catches the light, even after sunset.'), 'lead mb-4'),
                            kit.row(_sdT('Figures Row'), [
                                _lcFigure(1, _sdT('48 MP'), _sdT('Main camera')),
                                _lcFigure(2, _sdT('5×'), _sdT('Optical zoom')),
                                _lcFigure(3, _sdT('4K'), _sdT('Video at 60 fps'))
                            ], 'text-center text-sm-start')
                        ])
                    ], dark)
                ], [{ name: 'style', value: 'background-image: url(https://picsum.photos/seed/launch-camera/1920/1080);' }]), { effect: 'parallax', speed: 20 });
            }

            // Launch: Spec Comparison — the models side by side, specs grouped
            // under row headers, the head row sticky from 992 px up.
            if (ctype === 'launch-specs') {
                var _lsCell = function (tag, text, cls, scope) {
                    var attrs = scope ? [{ name: 'scope', value: scope }] : null;
                    return kit.tag(tag, cls || '', tag === 'th' ? _sdT('Header Cell') : _sdT('Cell'), [ kit.h('span', text) ], attrs);
                };
                var _lsRow = function (label, a, b, c) {
                    return kit.tag('tr', '', _sdT('Spec Row'), [
                        _lsCell('th', label, 'fw-normal text-body-secondary', 'row'),
                        _lsCell('td', a), _lsCell('td', b), _lsCell('td', c)
                    ]);
                };
                var _lsGroup = function (title, rows) {
                    return kit.tag('tbody', '', _sdT('Spec Group'), [
                        kit.tag('tr', '', _sdT('Group Row'), [
                            kit.tag('th', 'pt-4 h5 fw-bold border-0', _sdT('Group Header'), [ kit.h('span', title) ], [{ name: 'scope', value: 'rowgroup' }, { name: 'colspan', value: '4' }])
                        ])
                    ].concat(rows));
                };
                var _lsModel = function (name, from) {
                    return kit.tag('th', 'text-center pb-3', _sdT('Model Header'), [
                        kit.h('span', name, 'd-block h5 fw-bold mb-1'),
                        kit.h('span', from, 'd-block small fw-normal text-body-secondary')
                    ], [{ name: 'scope', value: 'col' }]);
                };
                return kit.section('py-5', _sdT('Launch Specs'), [
                    kit.intro(_sdT('Which ACME One is right for you?'), _sdT('Compare the three models, spec by spec.')),
                    kit.div('pg-launch-specs', _sdT('Table Wrap'), [
                        kit.tag('table', 'table align-middle text-center mb-0', _sdT('Spec Table'), [
                            kit.tag('caption', 'visually-hidden', _sdT('Table Caption'), [ kit.h('span', _sdT('Compare the three models, spec by spec.')) ]),
                            kit.tag('thead', '', _sdT('Table Head'), [
                                kit.tag('tr', '', _sdT('Header Row'), [
                                    kit.tag('td', '', _sdT('Corner Cell')),
                                    _lsModel(_sdT('One mini'), _sdT('From $699')),
                                    _lsModel(_sdT('ACME One'), _sdT('From $799')),
                                    _lsModel(_sdT('One Plus'), _sdT('From $999'))
                                ])
                            ]),
                            _lsGroup(_sdT('Display'), [
                                _lsRow(_sdT('Size'), _sdT('5.4 in'), _sdT('6.1 in'), _sdT('6.7 in')),
                                _lsRow(_sdT('Refresh rate'), _sdT('60 Hz'), _sdT('120 Hz'), _sdT('120 Hz'))
                            ]),
                            _lsGroup(_sdT('Camera'), [
                                _lsRow(_sdT('Main camera'), _sdT('12 MP'), _sdT('48 MP'), _sdT('48 MP')),
                                _lsRow(_sdT('Optical zoom'), _sdT('2×'), _sdT('3×'), _sdT('5×'))
                            ]),
                            _lsGroup(_sdT('Battery'), [
                                _lsRow(_sdT('Video playback'), _sdT('Up to 17 hours'), _sdT('Up to 22 hours'), _sdT('Up to 29 hours')),
                                _lsRow(_sdT('Weight'), _sdT('141 g'), _sdT('171 g'), _sdT('203 g'))
                            ])
                        ])
                    ])
                ]);
            }

            // Launch: In the Box — what comes in the box, as a strip of
            // pictures with their names.
            if (ctype === 'launch-box') {
                var _lbItem = function (n, seed, alt, name) {
                    return kit.tag('li', 'col', _sdT('Box Item {var}', n), [
                        kit.img(seed, 400, 400, alt, 'img-fluid w-100 mb-3 rounded-4 bg-body-tertiary object-fit-cover pg-ratio-1x1'),
                        kit.h('span', name, 'd-block fw-semibold')
                    ]);
                };
                return kit.section('py-5 text-center', _sdT('Launch Box'), [
                    kit.intro(_sdT('In the box'), _sdT('Less packaging, nothing you will not use.')),
                    kit.tag('ul', 'row row-cols-2 row-cols-md-4 g-4 list-unstyled mb-0', _sdT('Box List'), [
                        _lbItem(1, 'launch-box-phone', _sdT('The ACME One'), _sdT('ACME One')),
                        _lbItem(2, 'launch-box-cable', _sdT('A braided USB-C cable'), _sdT('USB-C cable, 1 m')),
                        _lbItem(3, 'launch-box-case', _sdT('A clear case'), _sdT('Clear case')),
                        _lbItem(4, 'launch-box-guide', _sdT('A quick start guide'), _sdT('Quick start guide'))
                    ])
                ]);
            }

            // Launch: Buy Band — a card per model with its price and buttons,
            // the cards revealed one after another as the band comes in.
            if (ctype === 'launch-buy') {
                var _lbCard = function (n, seed, name, line, from, monthly, featured) {
                    return kit.col(_sdT('Model {var}', n), [
                        kit.div('h-100 p-4 text-center bg-body border rounded-4' + (featured ? ' border-primary shadow' : ''), _sdT('Model Card'), [
                            kit.img(seed, 500, 500, name, 'img-fluid w-100 mb-4 object-fit-cover pg-ratio-1x1'),
                            kit.h('h3', name, 'h4 fw-bold mb-1'),
                            kit.p(line, 'text-body-secondary mb-3'),
                            kit.div('d-flex justify-content-center', _sdT('Price Wrap'), [ price(from, monthly, 'fs-5') ]),
                            kit.div('d-flex justify-content-center flex-wrap gap-2 mt-4', _sdT('Card Buttons'), [
                                kit.btn(_sdT('Buy'), 'primary'),
                                kit.btn(_sdT('Learn more'), 'outline-primary')
                            ])
                        ])
                    ], '4', '', '', '');
                };
                return kit.section('py-5 bg-body-tertiary', _sdT('Launch Buy'), [
                    kit.intro(_sdT('Get yours'), _sdT('Free delivery, and free returns within 14 days.')),
                    fx(kit.row(_sdT('Models Row'), [
                        _lbCard(1, 'launch-buy-mini', _sdT('One mini'), _sdT('Small enough for any pocket.'), _sdT('From $699'), _sdT('or $29.13/mo.'), false),
                        _lbCard(2, 'launch-buy-one', _sdT('ACME One'), _sdT('The one most people choose.'), _sdT('From $799'), _sdT('or $33.29/mo.'), true),
                        _lbCard(3, 'launch-buy-plus', _sdT('One Plus'), _sdT('The biggest screen and battery.'), _sdT('From $999'), _sdT('or $41.63/mo.'), false)
                    ]), { effect: 'slide-up', trigger: 'view', duration: 700, stagger: 150 })
                ]);
            }

            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
