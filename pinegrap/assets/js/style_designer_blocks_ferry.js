/**
 * Pinegrap - Enterprise Website Platform
 *
 * Ferry block family: the sections a ferry line's site needs that the
 * general palette lacks. The departure board (today's next sailings with a
 * status each), a route's timetable (departure times from either port), the
 * crossing strip (each route drawn port to port with its crossing time) and
 * the fares by traveller and vehicle (a price per crossing length). Every
 * value is sample text the staff edit in the designer: no live feed, no
 * ship tracking, no price engine.
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
        'pg-ferry-figures': '.pg-ferry-figures { font-variant-numeric: tabular-nums; }',
        'pg-ferry-wake':    '.pg-ferry-wake { border-top: 2px dashed var(--bs-primary-border-subtle); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'ferry-departures': ['pg-ferry-figures'],
        'ferry-timetable':  ['pg-ferry-figures'],
        'ferry-crossings':  ['pg-ferry-wake'],
        'ferry-fares':      ['pg-ferry-figures']
    };

    // An entrance the operator can change or remove in the Interactions
    // section; the children rise one after another while they scroll into
    // view. pg_fx keeps them still under reduced motion.
    var rise = function (node, stagger) {
        node.props._fx = { effect: 'slide-up', trigger: 'view', stagger: stagger || 90 };
        return node;
    };

    // A table cell; a head cell names what it heads with scope.
    var cell = function (tag, kids, cls, scope, more) {
        var attrs = scope ? [{ name: 'scope', value: scope }] : [];
        if (more) attrs = attrs.concat(more);
        return kit.tag(tag, cls || '', tag === 'th' ? _sdT('Header Cell') : _sdT('Cell'), kids, attrs.length ? attrs : null);
    };

    // A sailing's status: an icon and a word, so the colour is never the
    // only cue.
    var status = function (state) {
        var look = {
            ontime:    ['bg-success-subtle text-success-emphasis', 'bi-check-circle-fill',       _sdT('On time')],
            boarding:  ['bg-info-subtle text-info-emphasis',       'bi-box-arrow-in-right',      _sdT('Boarding')],
            delayed:   ['bg-warning-subtle text-warning-emphasis', 'bi-hourglass-split',         _sdT('Delayed')],
            cancelled: ['bg-danger-subtle text-danger-emphasis',   'bi-x-circle-fill',           _sdT('Cancelled')]
        }[state];
        return kit.tag('span', 'badge rounded-pill text-wrap ' + look[0], _sdT('Status Badge'), [
            kit.icon(look[1], 'me-1'),
            kit.h('span', look[2])
        ]);
    };

    // ── Departure board ─────────────────────────────────────────────────
    var sailing = function (n, time, route, ship, state, note) {
        var statusKids = [status(state)];
        if (note) statusKids.push(kit.h('span', note, 'd-block small text-body-secondary mt-1'));
        return kit.tag('tr', '', _sdT('Sailing {var}', n), [
            cell('th', [kit.time(time, time, 'fs-5 fw-bold', _sdT('Departure Time'))], 'text-nowrap', 'row'),
            cell('td', [kit.h('span', route, 'fw-semibold')], ''),
            cell('td', [kit.h('span', ship, 'text-body-secondary')], 'text-nowrap'),
            cell('td', statusKids, 'text-end')
        ]);
    };
    var buildDepartures = function () {
        var table = kit.tag('table', 'table table-borderless align-middle mb-0 pg-ferry-figures', _sdT('Departures Table'), [
            kit.tag('caption', 'visually-hidden', _sdT('Table Caption'), [kit.h('span', _sdT('Next sailings from every port'))]),
            kit.tag('thead', 'border-bottom', _sdT('Table Head'), [
                kit.tag('tr', '', _sdT('Head Row'), [
                    cell('th', [kit.h('span', _sdT('Departs'))], 'small text-body-secondary fw-semibold', 'col'),
                    cell('th', [kit.h('span', _sdT('Route'))], 'small text-body-secondary fw-semibold', 'col'),
                    cell('th', [kit.h('span', _sdT('Vessel'))], 'small text-body-secondary fw-semibold', 'col'),
                    cell('th', [kit.h('span', _sdT('Status'))], 'small text-body-secondary fw-semibold text-end', 'col')
                ])
            ]),
            rise(kit.tag('tbody', '', _sdT('Table Body'), [
                sailing(1, '09:30', _sdT('Pine Bay – Gull Island'),       _sdT('Seabreeze'),   'boarding'),
                sailing(2, '11:00', _sdT('Pine Bay – Cedar Island'),      _sdT('Island Star'), 'ontime'),
                sailing(3, '11:00', _sdT('North Harbour – Cedar Island'), _sdT('Kestrel'),     'delayed', _sdT('Now leaving at {var}', '11:20')),
                sailing(4, '12:30', _sdT('Pine Bay – Gull Island'),       _sdT('Seabreeze'),   'ontime'),
                sailing(5, '16:00', _sdT('Gull Island – Cedar Island'),   _sdT('Seabreeze'),   'cancelled', _sdT('Strong winds on the open side'))
            ]), 60)
        ]);
        var section = kit.section('py-5 bg-body text-body', _sdT('Ferry (Departure Board)'), [
            kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Board Head'), [
                kit.div('', _sdT('Board Title'), [
                    kit.h('h2', _sdT('Next sailings'), 'display-6 fw-bold mb-1'),
                    kit.p(_sdT('The harbour office updates this board as the ships come and go.'), 'text-body-secondary mb-0')
                ]),
                kit.div('d-flex align-items-center gap-2', _sdT('Board Note'), [
                    kit.icon('bi-broadcast', 'text-primary'),
                    kit.h('span', _sdT('Check-in closes 15 minutes before departure'), 'small fw-semibold')
                ])
            ]),
            kit.div('table-responsive rounded-4 border p-2 p-md-3 mb-4', _sdT('Table Wrap'), [table], kit.region(_sdT('Next sailings'))),
            kit.div('d-flex flex-wrap align-items-center gap-3', _sdT('Board Foot'), [
                kit.p(_sdT('A delay or a cancellation is also posted on the travel notices page.'), 'small text-body-secondary mb-0 me-auto'),
                kit.link(_sdT('Travel notices'), '#', 'btn btn-secondary', _sdT('Notices Link')),
                kit.link(_sdT('Full timetables'), '#', 'btn btn-primary', _sdT('Timetables Link'))
            ])
        ]);
        section.props._attrs = [{ name: 'data-bs-theme', value: 'dark' }];
        return section;
    };

    // ── Route timetable ─────────────────────────────────────────────────
    // The departure times from one port, as a grid of times that read
    // left to right, then a line on the days the list holds.
    var departures = function (n, port, days, times) {
        return kit.col(_sdT('Direction {var}', n), [
            kit.div('h-100 p-4 rounded-4 border bg-body', _sdT('Direction Card'), [
                kit.div('d-flex align-items-center gap-2 mb-1', _sdT('Direction Head'), [
                    kit.icon('bi-geo-alt-fill', 'text-primary'),
                    kit.h('h3', _sdT('Departures from {var}', port), 'h5 fw-bold mb-0')
                ]),
                kit.p(days, 'small text-body-secondary mb-3'),
                rise(kit.tag('ul', 'list-unstyled row row-cols-3 row-cols-sm-4 g-2 mb-0', _sdT('Departure Times'), times.map(function (t, i) {
                    return kit.tag('li', 'col', _sdT('Time {var}', i + 1), [
                        kit.time(t, t, 'd-block py-2 rounded-3 bg-body-tertiary text-center fw-semibold', _sdT('Departure Time'))
                    ]);
                })), 40)
            ])
        ], '6');
    };
    var fact = function (icon, value, label) {
        return kit.div('d-flex align-items-center gap-2', label, [
            kit.icon(icon, 'text-primary'),
            kit.h('span', value, 'fw-semibold'),
            kit.h('span', label, 'small text-body-secondary')
        ]);
    };
    var buildTimetable = function () {
        return kit.section('py-5 bg-body-tertiary pg-ferry-figures', _sdT('Ferry (Route Timetable)'), [
            kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Section Header'), [
                kit.div('', _sdT('Section Title'), [
                    kit.h('span', _sdT('Summer Timetable'), 'badge rounded-pill text-bg-primary mb-2'),
                    kit.h('h2', _sdT('Pine Bay – Gull Island'), 'fw-bold mb-2'),
                    kit.div('d-flex flex-wrap gap-3', _sdT('Route Facts'), [
                        fact('bi-clock',     _sdT('50 minutes'),        _sdT('Crossing')),
                        fact('bi-signpost',  _sdT('9 nautical miles'),  _sdT('Distance')),
                        fact('bi-water',     _sdT('Seabreeze'),         _sdT('Vessel'))
                    ])
                ]),
                kit.link(_sdT('Book this route'), '#', 'btn btn-primary', _sdT('Booking Link'))
            ]),
            kit.row(_sdT('Directions Row'), [
                departures(1, _sdT('Pine Bay'), _sdT('Every day, late spring and summer'), ['07:00', '08:30', '10:00', '11:30', '13:00', '14:30', '16:00', '17:30', '19:00', '20:30']),
                departures(2, _sdT('Gull Island'), _sdT('Every day, late spring and summer'), ['07:50', '09:20', '10:50', '12:20', '13:50', '15:20', '16:50', '18:20', '19:50', '21:20'])
            ]),
            kit.div('d-flex flex-wrap gap-3 mt-4', _sdT('Timetable Notes'), [
                kit.div('d-flex gap-2 small', _sdT('Note {var}', 1), [
                    kit.icon('bi-person-walking', 'text-primary'),
                    kit.h('span', _sdT('Foot passengers board up to 15 minutes before departure.'))
                ]),
                kit.div('d-flex gap-2 small', _sdT('Note {var}', 2), [
                    kit.icon('bi-car-front', 'text-primary'),
                    kit.h('span', _sdT('Vehicles check in 30 minutes before departure.'))
                ])
            ])
        ]);
    };

    // ── Crossing strip ──────────────────────────────────────────────────
    // A route drawn port to port: the two ports at the ends, the wake
    // between them with the crossing time above it and the distance below.
    var port = function (name, note, end) {
        return kit.div('flex-shrink-0' + (end ? ' text-end' : ''), _sdT('Port End'), [
            kit.h('span', name, 'd-block fw-bold'),
            kit.h('span', note, 'd-block small text-body-secondary')
        ]);
    };
    var crossing = function (n, title, from, fromNote, to, toNote, time, miles, ship, sailings) {
        return kit.tag('li', 'card border-0 shadow-sm', _sdT('Route {var}', n), [
            kit.div('card-body p-4', _sdT('Card Body'), [
                kit.div('d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', _sdT('Route Head'), [
                    kit.h('h3', title, 'h5 fw-bold mb-0'),
                    kit.h('span', _sdT('{var:1} · {var:2}', [ship, sailings]), 'small text-body-secondary')
                ]),
                kit.div('d-flex align-items-center gap-3', _sdT('Crossing Line'), [
                    port(from, fromNote, false),
                    kit.div('flex-grow-1 text-center', _sdT('Wake'), [
                        kit.h('span', time, 'd-block small fw-semibold mb-1'),
                        kit.div('position-relative pg-ferry-wake', _sdT('Wake Line'), [
                            kit.icon('bi-arrow-right-circle-fill', 'position-absolute top-50 start-50 translate-middle bg-body text-primary fs-5')
                        ]),
                        kit.h('span', miles, 'd-block small text-body-secondary mt-1')
                    ]),
                    port(to, toNote, true)
                ]),
                kit.link(_sdT('Timetable and fares'), '#', 'stretched-link small fw-semibold link-primary d-inline-block mt-3', _sdT('Route Link'))
            ])
        ]);
    };
    var buildCrossings = function () {
        return kit.section('py-5', _sdT('Ferry (Crossing Strip)'), [
            kit.intro(_sdT('Our crossings'), _sdT('Four routes link the mainland with both islands, and the islands with each other.')),
            rise(kit.tag('ol', 'list-unstyled d-grid gap-3 mb-0', _sdT('Routes List'), [
                crossing(1, _sdT('Pine Bay – Gull Island'), _sdT('Pine Bay'), _sdT('Town centre'), _sdT('Gull Island'), _sdT('Harbour village'),
                    _sdT('50 minutes'), _sdT('9 nautical miles'), _sdT('Seabreeze'), _sdT('{var} sailings a day', '10')),
                crossing(2, _sdT('Pine Bay – Cedar Island'), _sdT('Pine Bay'), _sdT('Town centre'), _sdT('Cedar Island'), _sdT('Cedar Town'),
                    _sdT('2 hours 10 minutes'), _sdT('24 nautical miles'), _sdT('Island Star'), _sdT('{var} sailings a day', '4')),
                crossing(3, _sdT('North Harbour – Cedar Island'), _sdT('North Harbour'), _sdT('Coast road'), _sdT('Cedar Island'), _sdT('Cedar Town'),
                    _sdT('40 minutes'), _sdT('7 nautical miles'), _sdT('Kestrel'), _sdT('{var} sailings a day', '10')),
                crossing(4, _sdT('Gull Island – Cedar Island'), _sdT('Gull Island'), _sdT('Harbour village'), _sdT('Cedar Island'), _sdT('Cedar Town'),
                    _sdT('1 hour'), _sdT('12 nautical miles'), _sdT('Seabreeze'), _sdT('{var} sailings a day', '2'))
            ]), 120)
        ]);
    };

    // ── Fares by traveller and vehicle ──────────────────────────────────
    // Row groups (foot passengers, vehicles, extras) with a row head per
    // kind: its icon, its name and what the fare covers; a column per
    // crossing length. A kind a crossing does not offer says so in words.
    var kind = function (icon, name, note, short, long) {
        var price = function (text) {
            var none = text === _sdT('Not offered');
            return cell('td', [kit.h('span', text, none ? 'small text-body-secondary' : 'fs-5 fw-bold')], 'text-end text-nowrap');
        };
        return kit.tag('tr', '', name, [
            cell('th', [
                kit.div('d-flex align-items-center gap-3', _sdT('Kind'), [
                    kit.icon(icon, 'fs-4 text-primary flex-shrink-0'),
                    kit.div('', _sdT('Kind Name'), [
                        kit.h('span', name, 'd-block fw-semibold'),
                        kit.h('span', note, 'd-block small fw-normal text-body-secondary')
                    ])
                ])
            ], '', 'row'),
            price(short),
            price(long)
        ]);
    };
    var group = function (name, rows) {
        return kit.tag('tbody', '', name, [
            kit.tag('tr', '', _sdT('Group Row'), [
                cell('th', [kit.h('span', name)], 'pt-4 small text-uppercase text-body-secondary fw-semibold', 'rowgroup', [{ name: 'colspan', value: '3' }])
            ])
        ].concat(rows));
    };
    var buildFares = function () {
        var table = kit.tag('table', 'table align-middle mb-0 pg-ferry-figures', _sdT('Fares Table'), [
            kit.tag('caption', 'visually-hidden', _sdT('Table Caption'), [kit.h('span', _sdT('Single fares by traveller and vehicle'))]),
            kit.tag('thead', '', _sdT('Table Head'), [
                kit.tag('tr', '', _sdT('Head Row'), [
                    cell('th', [kit.h('span', _sdT('Who or what travels'), 'visually-hidden')], '', 'col'),
                    cell('th', [
                        kit.h('span', _sdT('Short crossing'), 'd-block fw-bold'),
                        kit.h('span', _sdT('Up to one hour'), 'd-block small fw-normal text-body-secondary')
                    ], 'text-end', 'col'),
                    cell('th', [
                        kit.h('span', _sdT('Long crossing'), 'd-block fw-bold'),
                        kit.h('span', _sdT('Pine Bay – Cedar Island'), 'd-block small fw-normal text-body-secondary')
                    ], 'text-end', 'col')
                ])
            ]),
            group(_sdT('Foot passengers'), [
                kind('bi-person',         _sdT('Adult'),  _sdT('Ages 12 and over'), _sdT('$6'), _sdT('$14')),
                kind('bi-person-arms-up', _sdT('Child'),  _sdT('Ages 4 to 11; under 4 travel free'), _sdT('$3'), _sdT('$7')),
                kind('bi-person-heart',   _sdT('Senior'), _sdT('Ages 65 and over'), _sdT('$4'), _sdT('$10'))
            ]),
            group(_sdT('Vehicles with their driver'), [
                kind('bi-scooter',   _sdT('Motorcycle'),       _sdT('With its rider'), _sdT('$12'), _sdT('$26')),
                kind('bi-car-front', _sdT('Car'),              _sdT('Up to 5 metres long and 2 metres high, with its driver'), _sdT('$28'), _sdT('$62')),
                kind('bi-truck',     _sdT('Van or Campervan'), _sdT('Up to 7 metres long and 3 metres high, with its driver'), _sdT('$45'), _sdT('$98'))
            ]),
            group(_sdT('Extras'), [
                kind('bi-bicycle',     _sdT('Bicycle'), _sdT('Added to a foot passenger\'s fare'), _sdT('$2'), _sdT('$4')),
                kind('bi-door-closed', _sdT('Cabin'),   _sdT('For up to four people, on the long crossing only'), _sdT('Not offered'), _sdT('$30'))
            ])
        ]);
        return kit.section('py-5', _sdT('Ferry (Fares by Vehicle)'), [
            kit.row(_sdT('Fares Row'), [
                kit.col(_sdT('Fares Intro'), [
                    kit.h('h2', _sdT('Fares'), 'display-6 fw-bold mb-3'),
                    kit.p(_sdT('A single crossing for each traveller and vehicle. A return costs twice the single fare.'), 'lead text-body-secondary mb-4'),
                    rise(kit.tag('ul', 'list-unstyled d-grid gap-2 mb-4', _sdT('Included'), [
                        _sdT('A seat in the lounges and on the open decks'),
                        _sdT('Luggage you can carry, and a pushchair'),
                        _sdT('Wi-Fi on board')
                    ].map(function (text, i) {
                        return kit.tag('li', 'd-flex gap-2', _sdT('Item {var}', i + 1), [
                            kit.icon('bi-check2', 'text-primary flex-shrink-0'),
                            kit.h('span', text)
                        ]);
                    }))),
                    kit.link(_sdT('Request a booking'), '#', 'btn btn-primary', _sdT('Booking Link'))
                ], '4'),
                kit.col(_sdT('Fares Column'), [
                    kit.div('table-responsive rounded-4 border bg-body p-2 p-md-3', _sdT('Table Wrap'), [table], kit.region(_sdT('Fares'))),
                    kit.p(_sdT('The booking office confirms the fare when it confirms your booking.'), 'small text-body-secondary mt-3 mb-0')
                ], '8')
            ], 'g-lg-5')
        ]);
    };

    return {
        prefix: 'ferry-',
        group: { title: _sdT('Ferry'), icon: 'bi-water' },
        items: [
            { label: _sdT('Ferry: Departure Board'),  type: 'component', icon: 'bi-clock-history',   extra: { componentType: 'ferry-departures' } },
            { label: _sdT('Ferry: Route Timetable'),  type: 'component', icon: 'bi-table',           extra: { componentType: 'ferry-timetable' } },
            { label: _sdT('Ferry: Crossing Strip'),   type: 'component', icon: 'bi-arrow-left-right', extra: { componentType: 'ferry-crossings' } },
            { label: _sdT('Ferry: Fares by Vehicle'), type: 'component', icon: 'bi-car-front',       extra: { componentType: 'ferry-fares' } }
        ],
        build: function (ctype) {
            if (ctype === 'ferry-departures') return buildDepartures();
            if (ctype === 'ferry-timetable')  return buildTimetable();
            if (ctype === 'ferry-crossings')  return buildCrossings();
            if (ctype === 'ferry-fares')      return buildFares();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
