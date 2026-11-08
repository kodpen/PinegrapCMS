/**
 * Pinegrap - Enterprise Website Platform
 *
 * Ski Resort block family: the sections a ski resort's site needs that the
 * general palette lacks. The conditions board (today's figures on a dark
 * band), lift status by area, the slope map with its difficulty legend, the
 * pass cards with a price line per age group, and the snow report (depth by
 * station, new snow, avalanche danger scale). Every value is sample text the
 * staff edit in the designer: no live feed, no weather service, no
 * interactive map.
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
        'pg-ski-board':  '.pg-ski-board { font-variant-numeric: tabular-nums; }',
        'pg-ski-green':  '.pg-ski-green { color: var(--bs-success); }',
        'pg-ski-blue':   '.pg-ski-blue { color: var(--bs-blue); }',
        'pg-ski-red':    '.pg-ski-red { color: var(--bs-red); }',
        'pg-ski-black':  '.pg-ski-black { color: var(--bs-emphasis-color); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'skiresort-conditions': ['pg-ski-board'],
        'skiresort-lifts':      ['pg-ski-board'],
        'skiresort-slope-map':  ['pg-ski-green', 'pg-ski-blue', 'pg-ski-red', 'pg-ski-black'],
        'skiresort-snow':       ['pg-ski-board']
    };

    // An entrance the operator can change or remove in the Interactions
    // section; the children rise one after another while the row scrolls
    // into view. pg_fx keeps them still under reduced motion.
    var rise = function (node) {
        node.props._fx = { effect: 'slide-up', trigger: 'view', stagger: 90 };
        return node;
    };

    // A status pill: an icon and a word, so the colour is never the only cue.
    var status = function (state) {
        var look = {
            open:   ['bg-success-subtle text-success-emphasis', 'bi-check-circle-fill', _sdT('Open')],
            closed: ['bg-danger-subtle text-danger-emphasis',   'bi-x-circle-fill',     _sdT('Closed')],
            hold:   ['bg-warning-subtle text-warning-emphasis', 'bi-pause-circle-fill', _sdT('On Hold')]
        }[state];
        return kit.tag('span', 'badge rounded-pill text-wrap ' + look[0], _sdT('Status Badge'), [
            kit.icon(look[1], 'me-1'),
            kit.h('span', look[2])
        ]);
    };

    // ── Conditions board ────────────────────────────────────────────────
    var boardTile = function (n, icon, value, label, note) {
        return kit.col(_sdT('Figure {var}', n), [
            kit.div('h-100 p-3 rounded-3 border', _sdT('Figure Tile'), [
                kit.icon(icon, 'd-block fs-4 mb-2 text-primary'),
                kit.h('span', value, 'd-block display-6 fw-bold lh-1 mb-1'),
                kit.h('span', label, 'd-block fw-semibold'),
                kit.h('span', note, 'd-block small text-body-secondary')
            ])
        ], '2', '4', '6');
    };
    var buildConditions = function () {
        var section = kit.section('py-5 bg-body text-body pg-ski-board', _sdT('Ski Resort (Conditions Board)'), [
            kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Board Head'), [
                kit.div('', _sdT('Board Title'), [
                    kit.h('h2', _sdT('Today on the mountain'), 'display-6 fw-bold mb-1'),
                    kit.p(_sdT('Updated by the slope team every morning before the first lift.'), 'text-body-secondary mb-0')
                ]),
                status('open')
            ]),
            rise(kit.row(_sdT('Figures Row'), [
                boardTile(1, 'bi-signpost-split',   _sdT('{var:1} / {var:2}', ['18', '24']), _sdT('Slopes open'), _sdT('All areas')),
                boardTile(2, 'bi-arrow-up-right',   _sdT('{var:1} / {var:2}', ['9', '11']),  _sdT('Lifts open'),  _sdT('Gondola running')),
                boardTile(3, 'bi-snow2',            _sdT('{var} cm', '120'), _sdT('Snow depth'), _sdT('At the summit')),
                boardTile(4, 'bi-cloud-snow',       _sdT('{var} cm', '15'),  _sdT('New snow'),   _sdT('Last 24 hours')),
                boardTile(5, 'bi-thermometer-snow', '-6°',                   _sdT('Temperature'), _sdT('At the summit')),
                boardTile(6, 'bi-wind',             _sdT('{var} km/h', '20'), _sdT('Wind'),      _sdT('Light, from the north'))
            ])),
            kit.div('d-flex flex-wrap align-items-center gap-3 mt-4 pt-4 border-top', _sdT('Board Foot'), [
                kit.div('d-flex align-items-center gap-2 me-auto', _sdT('Avalanche Danger'), [
                    kit.icon('bi-exclamation-triangle-fill', 'text-warning'),
                    kit.h('span', _sdT('Avalanche danger: {var}', _sdT('2 – Moderate')), 'fw-semibold')
                ]),
                kit.link(_sdT('Lift status'), '#', 'btn btn-secondary', _sdT('Lift Status Link')),
                kit.link(_sdT('Snow report'), '#', 'btn btn-primary', _sdT('Snow Report Link'))
            ])
        ]);
        section.props._attrs = [{ name: 'data-bs-theme', value: 'dark' }];
        return section;
    };

    // ── Lift status ─────────────────────────────────────────────────────
    var lift = function (name, icon, kind, ride, seats, state) {
        return kit.tag('li', 'list-group-item d-flex align-items-center gap-3 px-0 py-3', name, [
            kit.icon(icon, 'fs-4 text-primary flex-shrink-0'),
            kit.div('flex-grow-1', _sdT('Lift Details'), [
                kit.h('h4', name, 'h6 fw-semibold mb-1'),
                kit.p(_sdT('{var:1} · {var:2} · {var:3}', [kind, ride, seats]), 'small text-body-secondary mb-0')
            ]),
            status(state)
        ]);
    };
    var liftArea = function (n, title, note, lifts) {
        return kit.col(_sdT('Area {var}', n), [
            kit.div('card h-100 border-0 shadow-sm', _sdT('Area Card'), [
                kit.div('card-body p-4', _sdT('Card Body'), [
                    kit.h('h3', title, 'h5 fw-bold mb-1'),
                    kit.p(note, 'small text-body-secondary mb-2'),
                    kit.tag('ul', 'list-group list-group-flush', _sdT('Lift List'), lifts)
                ])
            ])
        ], '6');
    };
    var buildLifts = function () {
        return kit.section('py-5 bg-body-tertiary pg-ski-board', _sdT('Ski Resort (Lift Status)'), [
            kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Section Header'), [
                kit.div('', _sdT('Section Title'), [
                    kit.h('h2', _sdT('Lift status'), 'fw-bold mb-1'),
                    kit.p(_sdT('Lifts run from 8:30 to 16:30. A lift on hold waits for the wind to drop.'), 'text-body-secondary mb-0')
                ]),
                kit.h('span', _sdT('{var:1} of {var:2} lifts open', ['9', '11']), 'fw-semibold')
            ]),
            rise(kit.row(_sdT('Areas Row'), [
                liftArea(1, _sdT('Summit area'), _sdT('Red and black slopes above the tree line'), [
                    lift(_sdT('Summit Gondola'), 'bi-arrow-up-right', _sdT('Gondola'),   _sdT('{var} min', '9'), _sdT('{var} seats', '10'), 'open'),
                    lift(_sdT('Ridge Chair'),    'bi-arrow-up-right', _sdT('Chairlift'), _sdT('{var} min', '7'), _sdT('{var} seats', '6'),  'hold'),
                    lift(_sdT('Glacier Tow'),    'bi-arrow-up',       _sdT('Drag Lift'), _sdT('{var} min', '5'), _sdT('{var} seat', '1'),   'closed')
                ]),
                liftArea(2, _sdT('Village area'), _sdT('Gentle slopes and the ski school meadow'), [
                    lift(_sdT('Pine Chair'),     'bi-arrow-up-right', _sdT('Chairlift'),    _sdT('{var} min', '6'), _sdT('{var} seats', '4'), 'open'),
                    lift(_sdT('Meadow Tow'),     'bi-arrow-up',       _sdT('Drag Lift'),    _sdT('{var} min', '3'), _sdT('{var} seat', '1'),  'open'),
                    lift(_sdT('Learner Carpet'), 'bi-arrow-right',    _sdT('Magic Carpet'), _sdT('{var} min', '2'), _sdT('Walk on'),           'open')
                ])
            ]))
        ]);
    };

    // ── Slope map ───────────────────────────────────────────────────────
    var grade = function (n, icon, cls, name, level, count) {
        return kit.tag('li', 'd-flex align-items-center gap-3 py-2 border-bottom', _sdT('Grade {var}', n), [
            kit.icon(icon, 'fs-4 flex-shrink-0 ' + cls),
            kit.div('flex-grow-1', _sdT('Grade Name'), [
                kit.h('h4', name, 'h6 fw-semibold mb-0'),
                kit.h('span', level, 'small text-body-secondary')
            ]),
            kit.h('span', count, 'small fw-semibold text-nowrap')
        ]);
    };
    var buildSlopeMap = function () {
        return kit.section('py-5', _sdT('Ski Resort (Slope Map)'), [
            kit.intro(_sdT('Slope map'), _sdT('Twenty-four slopes over three areas, from the village meadow to the summit ridge.')),
            kit.row(_sdT('Map Row'), [
                kit.col(_sdT('Map Column'), [
                    kit.tag('figure', 'mb-0', _sdT('Slope Map'), [
                        kit.img('ski-slope-map', 1200, 800, _sdT('Slope map of the resort with its three areas'), 'img-fluid w-100 rounded-4 shadow-sm'),
                        kit.tag('figcaption', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-3', _sdT('Map Caption'), [
                            kit.h('span', _sdT('Slopes are groomed every night; a closed slope is roped off.'), 'small text-body-secondary'),
                            kit.link(_sdT('Download the map (PDF)'), '#', 'link-primary fw-semibold', _sdT('Map Download Link'))
                        ])
                    ])
                ], '8'),
                kit.col(_sdT('Legend Column'), [
                    kit.h('h3', _sdT('Difficulty'), 'h5 fw-bold mb-2'),
                    kit.tag('ul', 'list-unstyled mb-4', _sdT('Difficulty Legend'), [
                        grade(1, 'bi-circle-fill',  'pg-ski-green', _sdT('Green slope'), _sdT('Beginner'),     _sdT('{var} slopes', '5')),
                        grade(2, 'bi-square-fill',  'pg-ski-blue',  _sdT('Blue slope'),  _sdT('Easy'),         _sdT('{var} slopes', '8')),
                        grade(3, 'bi-diamond-fill', 'pg-ski-red',   _sdT('Red slope'),   _sdT('Intermediate'), _sdT('{var} slopes', '7')),
                        grade(4, 'bi-diamond-fill', 'pg-ski-black', _sdT('Black slope'), _sdT('Expert'),       _sdT('{var} slopes', '4'))
                    ]),
                    kit.h('h3', _sdT('Areas'), 'h5 fw-bold mb-2'),
                    kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-4', _sdT('Area List'), [
                        kit.tag('li', '', _sdT('Area {var}', 1), [kit.link(_sdT('Summit'),  '#', 'btn btn-sm btn-outline-secondary', _sdT('Area Link'))]),
                        kit.tag('li', '', _sdT('Area {var}', 2), [kit.link(_sdT('Forest'),  '#', 'btn btn-sm btn-outline-secondary', _sdT('Area Link'))]),
                        kit.tag('li', '', _sdT('Area {var}', 3), [kit.link(_sdT('Village'), '#', 'btn btn-sm btn-outline-secondary', _sdT('Area Link'))])
                    ]),
                    kit.link(_sdT('All slopes'), '#', 'btn btn-primary', _sdT('Slopes Link'))
                ], '4')
            ], 'g-lg-5')
        ]);
    };

    // ── Pass cards ──────────────────────────────────────────────────────
    // One card per pass: a price line per age group, then what it includes.
    var priceLine = function (who, price) {
        return kit.div('d-flex justify-content-between gap-3 py-2 border-bottom', who, [
            kit.h('dt', who, 'fw-normal'),
            kit.h('dd', price, 'fw-bold mb-0')
        ]);
    };
    var pass = function (n, name, lead, prices, includes, featured) {
        var head = [kit.h('h3', name, 'h4 fw-bold mb-1')];
        if (featured) {
            head = [kit.div('d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', _sdT('Card Head'), [
                kit.h('h3', name, 'h4 fw-bold mb-0'),
                kit.h('span', _sdT('Most chosen'), 'badge rounded-pill text-bg-primary')
            ])];
        }
        return kit.col(_sdT('Pass {var}', n), [
            kit.div('card h-100 ' + (featured ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), _sdT('Pass Card'), [
                kit.div('card-body d-flex flex-column p-4', _sdT('Card Body'), head.concat([
                    kit.p(lead, 'text-body-secondary mb-3'),
                    kit.tag('dl', 'mb-4', _sdT('Prices'), prices),
                    kit.tag('ul', 'list-unstyled d-grid gap-2 small mb-4', _sdT('Included'), includes.map(function (text, i) {
                        return kit.tag('li', 'd-flex gap-2', _sdT('Item {var}', i + 1), [
                            kit.icon('bi-check2', 'text-primary flex-shrink-0'),
                            kit.h('span', text)
                        ]);
                    })),
                    kit.link(_sdT('Buy this pass'), '#', 'btn mt-auto ' + (featured ? 'btn-primary' : 'btn-outline-primary'), _sdT('Buy Link'))
                ]))
            ])
        ], '4', '6');
    };
    var buildPasses = function () {
        return kit.section('py-5', _sdT('Ski Resort (Pass Cards)'), [
            kit.intro(_sdT('Lift passes'), _sdT('One pass for every lift in all three areas. Children under six ski free with an adult.')),
            rise(kit.row(_sdT('Passes Row'), [
                pass(1, _sdT('Day pass'), _sdT('For a first try or a single day on the mountain.'), [
                    priceLine(_sdT('Adult'), _sdT('$58')),
                    priceLine(_sdT('Young (13–17)'), _sdT('$44')),
                    priceLine(_sdT('Child (6–12)'), _sdT('$31'))
                ], [_sdT('Every lift, all day'), _sdT('Ski bus from the village')], false),
                pass(2, _sdT('Three-day pass'), _sdT('Three days of your choice within one week.'), [
                    priceLine(_sdT('Adult'), _sdT('$156')),
                    priceLine(_sdT('Young (13–17)'), _sdT('$117')),
                    priceLine(_sdT('Child (6–12)'), _sdT('$84'))
                ], [_sdT('Every lift, all day'), _sdT('Ski bus from the village'), _sdT('One evening on the floodlit slope')], true),
                pass(3, _sdT('Season pass'), _sdT('Every day of the season, from the first snow to the last.'), [
                    priceLine(_sdT('Adult'), _sdT('$690')),
                    priceLine(_sdT('Young (13–17)'), _sdT('$490')),
                    priceLine(_sdT('Child (6–12)'), _sdT('$310'))
                ], [_sdT('Every lift, all season'), _sdT('Ski bus from the village'), _sdT('Every evening on the floodlit slope'), _sdT('10% off rental and lessons')], false)
            ], 'justify-content-center')),
            kit.p(_sdT('Collect your pass at the ticket office with your receipt.'), 'small text-body-secondary text-center mt-4 mb-0')
        ]);
    };

    // ── Snow report ─────────────────────────────────────────────────────
    var station = function (n, name, height, depth, percent) {
        return kit.tag('li', 'mb-4', _sdT('Station {var}', n), [
            kit.div('d-flex justify-content-between gap-3 mb-2', _sdT('Station Head'), [
                kit.div('', _sdT('Station Name'), [
                    kit.h('h4', name, 'h6 fw-semibold mb-0'),
                    kit.h('span', height, 'small text-body-secondary')
                ]),
                kit.h('span', depth, 'h4 fw-bold mb-0')
            ]),
            kit.div('progress', _sdT('Depth Bar'), [
                kit.div('progress-bar w-' + percent, _sdT('Depth'))
            ], [
                { name: 'role', value: 'progressbar' },
                { name: 'aria-label', value: _sdT('Snow depth at {var}', name) },
                { name: 'aria-valuenow', value: String(percent) },
                { name: 'aria-valuemin', value: '0' },
                { name: 'aria-valuemax', value: '100' }
            ])
        ]);
    };
    var fresh = function (n, value, label) {
        return kit.div('col', _sdT('New Snow {var}', n), [
            kit.div('h-100 p-3 rounded-3 bg-body text-center', _sdT('New Snow Tile'), [
                kit.h('span', value, 'd-block h3 fw-bold mb-0'),
                kit.h('span', label, 'd-block small text-body-secondary')
            ])
        ]);
    };
    var danger = function (n, name, today) {
        var kids = [
            kit.h('span', String(n), 'badge rounded-pill ' + (today ? 'text-bg-primary' : 'text-bg-secondary')),
            kit.h('span', name, today ? 'fw-bold' : '')
        ];
        if (today) kids.push(kit.h('span', _sdT('Today'), 'badge rounded-pill bg-warning-subtle text-warning-emphasis ms-auto'));
        var item = kit.tag('li', 'd-flex align-items-center gap-2 py-1', _sdT('Level {var}', n), kids);
        if (today) item.props._attrs = [{ name: 'aria-current', value: 'true' }];
        return item;
    };
    var buildSnow = function () {
        return kit.section('py-5 bg-body-tertiary pg-ski-board', _sdT('Ski Resort (Snow Report)'), [
            kit.div('mb-4', _sdT('Section Header'), [
                kit.h('h2', _sdT('Snow report'), 'fw-bold mb-1'),
                kit.p(_sdT('Measured at three stations every morning at seven.'), 'text-body-secondary mb-0')
            ]),
            kit.row(_sdT('Report Row'), [
                kit.col(_sdT('Depth Column'), [
                    kit.h('h3', _sdT('Snow depth'), 'h5 fw-bold mb-3'),
                    kit.tag('ul', 'list-unstyled mb-0', _sdT('Stations'), [
                        station(1, _sdT('Summit'),      _sdT('{var} m', _sdT('2,450')), _sdT('{var} cm', '160'), 75),
                        station(2, _sdT('Mid station'), _sdT('{var} m', _sdT('1,900')), _sdT('{var} cm', '120'), 50),
                        station(3, _sdT('Village'),     _sdT('{var} m', _sdT('1,350')), _sdT('{var} cm', '60'),  25)
                    ])
                ], '6'),
                kit.col(_sdT('Fresh Column'), [
                    kit.h('h3', _sdT('New snow'), 'h5 fw-bold mb-3'),
                    rise(kit.div('row row-cols-3 g-2 mb-3', _sdT('New Snow Tiles'), [
                        fresh(1, _sdT('{var} cm', '15'), _sdT('24 hours')),
                        fresh(2, _sdT('{var} cm', '25'), _sdT('48 hours')),
                        fresh(3, _sdT('{var} cm', '40'), _sdT('7 days'))
                    ])),
                    kit.p(_sdT('Last snowfall: last night, light and dry. Powder on the upper slopes, packed snow below.'), 'mb-4'),
                    kit.h('h3', _sdT('Avalanche danger'), 'h5 fw-bold mb-2'),
                    kit.tag('ol', 'list-unstyled mb-2', _sdT('Danger Scale'), [
                        danger(1, _sdT('Low danger'), false),
                        danger(2, _sdT('Moderate danger'), true),
                        danger(3, _sdT('Considerable danger'), false),
                        danger(4, _sdT('High danger'), false),
                        danger(5, _sdT('Very high danger'), false)
                    ]),
                    kit.p(_sdT('Off-piste only with a guide, transceiver, probe and shovel.'), 'small text-body-secondary mb-0')
                ], '6')
            ], 'g-lg-5')
        ]);
    };

    return {
        prefix: 'skiresort-',
        group: { title: _sdT('Ski Resort'), icon: 'bi-snow' },
        items: [
            { label: _sdT('Ski Resort: Conditions Board'), type: 'component', icon: 'bi-speedometer2',   extra: { componentType: 'skiresort-conditions' } },
            { label: _sdT('Ski Resort: Lift Status'),      type: 'component', icon: 'bi-arrow-up-right', extra: { componentType: 'skiresort-lifts' } },
            { label: _sdT('Ski Resort: Slope Map'),        type: 'component', icon: 'bi-map',            extra: { componentType: 'skiresort-slope-map' } },
            { label: _sdT('Ski Resort: Pass Cards'),       type: 'component', icon: 'bi-ticket-perforated', extra: { componentType: 'skiresort-passes' } },
            { label: _sdT('Ski Resort: Snow Report'),      type: 'component', icon: 'bi-snow2',          extra: { componentType: 'skiresort-snow' } }
        ],
        build: function (ctype) {
            if (ctype === 'skiresort-conditions') return buildConditions();
            if (ctype === 'skiresort-lifts')      return buildLifts();
            if (ctype === 'skiresort-slope-map')  return buildSlopeMap();
            if (ctype === 'skiresort-passes')     return buildPasses();
            if (ctype === 'skiresort-snow')       return buildSnow();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
