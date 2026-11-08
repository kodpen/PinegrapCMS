/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sports Club block family: the sections a sports club's site needs that
 * the general palette lacks. The match-day band (the next match with both
 * crests, kick-off and ground, beside the last result), the fixtures and
 * results board, the league table with its promotion and relegation
 * places, and the squad grouped by position with shirt numbers. Every
 * value is sample text the staff edit in the designer.
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
        'pg-sc-crest':  '.pg-sc-crest { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 4.5rem; height: 4.5rem; font-weight: 800; font-size: 1.35rem; letter-spacing: .04em; line-height: 1; border-radius: 50%; color: var(--bs-primary-text-emphasis); background-color: var(--bs-primary-bg-subtle); border: 3px solid var(--bs-primary-border-subtle); }',
        'pg-sc-score':  '.pg-sc-score { font-variant-numeric: tabular-nums; font-weight: 800; white-space: nowrap; }',
        'pg-sc-up':     '.pg-sc-up > :first-child { box-shadow: inset 4px 0 0 var(--bs-success); }',
        'pg-sc-down':   '.pg-sc-down > :first-child { box-shadow: inset 4px 0 0 var(--bs-danger); }',
        'pg-sc-mark':   '.pg-sc-mark { display: inline-block; width: 4px; height: 1em; vertical-align: -.125em; }',
        'pg-sc-number': '.pg-sc-number { font-variant-numeric: tabular-nums; font-weight: 800; font-size: 2rem; line-height: 1; color: var(--bs-primary); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'sportsclub-matchday': ['pg-sc-crest', 'pg-sc-score'],
        'sportsclub-fixtures': ['pg-sc-score'],
        'sportsclub-table':    ['pg-sc-score', 'pg-sc-up', 'pg-sc-down', 'pg-sc-mark'],
        'sportsclub-squad':    ['pg-sc-number', 'pg-ratio-1x1']
    };

    // An entrance the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, effect, trigger, stagger) {
        node.props._fx = { effect: effect, trigger: trigger || 'view' };
        if (stagger) node.props._fx.stagger = stagger;
        return node;
    };

    var header = function (title, lead, more) {
        var kids = [kit.div('', _sdT('Section Title'), [
            kit.h('h2', title, 'fw-bold mb-1'),
            kit.p(lead, 'text-body-secondary mb-0')
        ])];
        if (more) kids.push(more);
        return kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Section Header'), kids);
    };

    // A result said with an icon and a word, never by colour alone.
    var outcome = function (kind) {
        var spec = {
            won:   ['bi-check-circle-fill', 'bg-success-subtle text-success-emphasis', _sdT('Won')],
            drawn: ['bi-dash-circle-fill', 'bg-secondary-subtle text-secondary-emphasis', _sdT('Drawn')],
            lost:  ['bi-x-circle-fill', 'bg-danger-subtle text-danger-emphasis', _sdT('Lost')]
        }[kind];
        return kit.tag('span', 'badge rounded-pill ' + spec[1], _sdT('Result Badge'), [
            kit.icon(spec[0], 'me-1'),
            kit.h('span', spec[2])
        ]);
    };

    // ── Match day ───────────────────────────────────────────────────────
    // The next match as two crests facing each other, kick-off between
    // them; the last result beside it. A crest is the club's initials in
    // a ring: a site swaps it for its badge picture.
    var side = function (initials, name, where) {
        return kit.div('d-flex flex-column align-items-center text-center gap-2', name, [
            kit.h('span', initials, 'pg-sc-crest'),
            kit.h('span', name, 'fw-bold fs-5 lh-sm'),
            kit.h('span', where, 'small text-body-secondary')
        ]);
    };
    var buildMatchday = function () {
        return kit.tag('section', 'py-5 bg-body text-body', _sdT('Sports Club (Match Day)'), [
            kit.createNode('container', { fluid: false, cssClass: '' }, [
                kit.row(_sdT('Match Day Row'), [
                    kit.col(_sdT('Next Match Column'), [
                        kit.div('d-flex flex-wrap align-items-center gap-2 mb-2', _sdT('Match Label'), [
                            kit.h('span', _sdT('League · Matchday {var}', '12'), 'badge rounded-pill text-bg-primary'),
                            kit.h('span', _sdT('Next match'), 'small fw-semibold text-uppercase text-body-secondary')
                        ]),
                        kit.h('h2', _sdT('Mill Lane against Riverside Rovers'), 'visually-hidden'),
                        fx(kit.div('d-flex align-items-center justify-content-center gap-3 gap-md-5 py-4', _sdT('Teams'), [
                            side(_sdT('ML'), _sdT('Mill Lane'), _sdT('Home')),
                            kit.div('text-center', _sdT('Kick-off'), [
                                kit.h('span', _sdT('Saturday'), 'd-block small text-body-secondary'),
                                kit.time('15:00', _sdT('3:00 pm'), 'd-block display-6 pg-sc-score', _sdT('Kick-off Time')),
                                kit.h('span', _sdT('vs'), 'd-block small fw-semibold text-uppercase text-primary')
                            ]),
                            side(_sdT('RR'), _sdT('Riverside Rovers'), _sdT('Away'))
                        ]), 'slide-up', 'view', 150),
                        kit.p(_sdT('Mill Lane Ground · gates open an hour before kick-off'), 'text-center text-body-secondary mb-4'),
                        kit.div('d-flex flex-wrap justify-content-center gap-2', _sdT('Buttons'), [
                            kit.link(_sdT('Buy tickets'), '#', 'btn btn-primary', _sdT('Tickets Link')),
                            kit.link(_sdT('Match preview'), '#', 'btn btn-outline-primary', _sdT('Preview Link'))
                        ])
                    ], '8'),
                    kit.col(_sdT('Last Result Column'), [
                        fx(kit.div('card h-100 border-0 bg-body-tertiary', _sdT('Last Result'), [
                            kit.div('card-body p-4 d-flex flex-column', _sdT('Card Body'), [
                                kit.div('d-flex align-items-center justify-content-between gap-2 mb-3', _sdT('Card Head'), [
                                    kit.h('h3', _sdT('Last result'), 'h6 fw-bold text-uppercase mb-0'),
                                    outcome('won')
                                ]),
                                kit.div('d-flex align-items-center justify-content-between gap-3 mb-3', _sdT('Score Line'), [
                                    kit.h('span', _sdT('Mill Lane'), 'fw-semibold'),
                                    kit.h('span', _sdT('{var:1} – {var:2}', ['2', '1']), 'fs-2 pg-sc-score'),
                                    kit.h('span', _sdT('Hillside Athletic'), 'fw-semibold text-end')
                                ]),
                                kit.p(_sdT('A late header from a corner settled it in front of a full stand.'), 'small text-body-secondary mb-3'),
                                kit.link(_sdT('Match report'), '#', 'link-primary fw-semibold mt-auto', _sdT('Report Link'))
                            ])
                        ]), 'fade', 'view')
                    ], '4')
                ], 'align-items-stretch g-lg-5')
            ])
        ], [{ name: 'data-bs-theme', value: 'dark' }]);
    };

    // ── Fixtures and results ────────────────────────────────────────────
    var fixture = function (n, day, time, label, home, opponent, competition) {
        return kit.tag('li', 'd-flex align-items-center gap-3 py-3 border-bottom', _sdT('Fixture {var}', n), [
            kit.div('text-center flex-shrink-0', _sdT('When'), [
                kit.h('span', day, 'd-block small text-body-secondary'),
                kit.time(time, label, 'd-block fw-bold pg-sc-score', _sdT('Kick-off Time'))
            ]),
            kit.div('flex-grow-1', _sdT('Match'), [
                kit.h('h4', opponent, 'h6 fw-bold mb-0'),
                kit.h('span', competition, 'small text-body-secondary')
            ]),
            kit.h('span', home ? _sdT('Home') : _sdT('Away'), 'badge rounded-pill ' + (home ? 'text-bg-primary' : 'bg-body-secondary text-body'))
        ]);
    };
    var result = function (n, opponent, competition, score, kind) {
        return kit.tag('li', 'd-flex align-items-center gap-3 py-3 border-bottom', _sdT('Result {var}', n), [
            kit.div('flex-grow-1', _sdT('Match'), [
                kit.h('h4', opponent, 'h6 fw-bold mb-0'),
                kit.h('span', competition, 'small text-body-secondary')
            ]),
            kit.h('span', score, 'fs-5 pg-sc-score'),
            outcome(kind)
        ]);
    };
    var buildFixtures = function () {
        return kit.section('py-5', _sdT('Sports Club (Fixtures and Results)'), [
            header(_sdT('Fixtures and results'), _sdT('Every first-team match this season, home and away.'),
                kit.link(_sdT('Full fixture list'), '#', 'btn btn-outline-primary', _sdT('Fixtures Link'))),
            kit.row(_sdT('Board Row'), [
                kit.col(_sdT('Coming Up Column'), [
                    kit.h('h3', _sdT('Coming up'), 'h5 fw-bold mb-2'),
                    fx(kit.tag('ol', 'list-unstyled mb-0', _sdT('Fixtures'), [
                        fixture(1, _sdT('Saturday'), '15:00', _sdT('3:00 pm'), true, _sdT('Riverside Rovers'), _sdT('League')),
                        fixture(2, _sdT('Wednesday'), '19:45', _sdT('7:45 pm'), false, _sdT('Old Town United'), _sdT('County Cup')),
                        fixture(3, _sdT('Next Saturday'), '15:00', _sdT('3:00 pm'), false, _sdT('Eastgate Wanderers'), _sdT('League')),
                        fixture(4, _sdT('In two weeks'), '14:00', _sdT('2:00 pm'), true, _sdT('Harbour Sports Club'), _sdT('League'))
                    ]), 'slide-up', 'view', 70)
                ], '6'),
                kit.col(_sdT('Played Column'), [
                    kit.h('h3', _sdT('Played'), 'h5 fw-bold mb-2'),
                    fx(kit.tag('ol', 'list-unstyled mb-0', _sdT('Results'), [
                        result(1, _sdT('Hillside Athletic'), _sdT('League · home'), _sdT('{var:1} – {var:2}', ['2', '1']), 'won'),
                        result(2, _sdT('Harbour Sports Club'), _sdT('County Cup · away'), _sdT('{var:1} – {var:2}', ['1', '1']), 'drawn'),
                        result(3, _sdT('Old Town United'), _sdT('League · away'), _sdT('{var:1} – {var:2}', ['0', '2']), 'lost'),
                        result(4, _sdT('Eastgate Wanderers'), _sdT('League · home'), _sdT('{var:1} – {var:2}', ['3', '0']), 'won')
                    ]), 'slide-up', 'view', 70)
                ], '6')
            ], 'g-lg-5')
        ]);
    };

    // ── League table ────────────────────────────────────────────────────
    // A short column head is shown, its full name is read out.
    var head = function (short, full, cls) {
        return kit.tag('th', cls || 'text-center', full, [
            kit.h('span', short, ''),
            kit.h('span', full, 'visually-hidden')
        ], [{ name: 'scope', value: 'col' }]);
    };
    var num = function (text, cls) {
        return kit.tag('td', cls || 'text-center', _sdT('Cell'), [kit.h('span', text)]);
    };
    var standing = function (pos, club, figures, zone, ours) {
        var attrs = ours ? [{ name: 'aria-current', value: 'true' }] : null;
        var cls = (zone === 'up' ? 'pg-sc-up' : (zone === 'down' ? 'pg-sc-down' : '')) + (ours ? ' table-primary' : '');
        var kids = [
            num(pos, 'text-center fw-semibold'),
            kit.tag('th', ours ? 'fw-bold' : 'fw-normal', _sdT('Club'), [kit.h('span', club)], [{ name: 'scope', value: 'row' }])
        ];
        figures.forEach(function (f, i) { kids.push(num(f, i === figures.length - 1 ? 'text-center fw-bold' : 'text-center')); });
        return kit.tag('tr', cls.trim(), club, kids, attrs);
    };
    var zoneKey = function (cls, text) {
        return kit.tag('li', 'd-flex align-items-center gap-2', text, [
            kit.h('span', '', 'pg-sc-mark ' + cls),
            kit.h('span', text)
        ]);
    };
    var buildTable = function () {
        var table = kit.tag('table', 'table align-middle mb-0 pg-sc-score', _sdT('League Table'), [
            kit.tag('caption', 'visually-hidden', _sdT('Table Caption'), [kit.h('span', _sdT('League table after twelve matches'))]),
            kit.tag('thead', '', _sdT('Table Head'), [
                kit.tag('tr', '', _sdT('Head Row'), [
                    head(_sdT('#'), _sdT('Position')),
                    head(_sdT('Club'), _sdT('Club'), ''),
                    head(_sdT('P'), _sdT('Played')),
                    head(_sdT('W'), _sdT('Won')),
                    head(_sdT('D'), _sdT('Drawn')),
                    head(_sdT('L'), _sdT('Lost')),
                    head(_sdT('GD'), _sdT('Goal difference')),
                    head(_sdT('Pts'), _sdT('Points'))
                ])
            ]),
            fx(kit.tag('tbody', '', _sdT('Table Body'), [
                standing('1', _sdT('Old Town United'), ['12', '9', '2', '1', '+15', '29'], 'up', false),
                standing('2', _sdT('Mill Lane'), ['12', '8', '2', '2', '+11', '26'], 'up', true),
                standing('3', _sdT('Harbour Sports Club'), ['12', '7', '3', '2', '+8', '24'], '', false),
                standing('4', _sdT('Hillside Athletic'), ['12', '5', '3', '4', '+2', '18'], '', false),
                standing('5', _sdT('Eastgate Wanderers'), ['12', '3', '2', '7', '-9', '11'], 'down', false),
                standing('6', _sdT('Riverside Rovers'), ['12', '1', '2', '9', '-27', '5'], 'down', false)
            ]), 'fade', 'view', 60)
        ]);
        return kit.section('py-5 bg-body-tertiary', _sdT('Sports Club (League Table)'), [
            header(_sdT('League table'), _sdT('Where the first team stands, updated after every match.'),
                kit.link(_sdT('All results'), '#', 'btn btn-outline-primary', _sdT('Results Link'))),
            kit.div('card border-0 shadow-sm', _sdT('Table Card'), [
                kit.div('card-body p-0 p-md-3', _sdT('Card Body'), [
                    kit.div('table-responsive', _sdT('Table Wrap'), [table], kit.region(_sdT('League table after twelve matches')))
                ])
            ]),
            kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-4 small text-body-secondary mt-3 mb-0', _sdT('Table Key'), [
                zoneKey('bg-success', _sdT('Promotion places')),
                zoneKey('bg-danger', _sdT('Relegation places'))
            ])
        ]);
    };

    // ── Squad by position ───────────────────────────────────────────────
    var player = function (seed, number, name, line) {
        return kit.col(name, [
            kit.div('card h-100 border-0 shadow-sm position-relative', _sdT('Player Card'), [
                kit.img(seed, 400, 400, name, 'card-img-top pg-ratio-1x1'),
                kit.div('card-body d-flex align-items-center gap-3', _sdT('Card Body'), [
                    kit.h('span', number, 'pg-sc-number'),
                    kit.div('', _sdT('Player Name'), [
                        kit.link(name, '#', 'd-block fw-bold text-body text-decoration-none stretched-link', _sdT('Player Link')),
                        kit.h('span', line, 'small text-body-secondary')
                    ])
                ])
            ])
        ], '3', '4', '6');
    };
    var position = function (title, players) {
        return kit.div('mb-5', title, [
            kit.h('h3', title, 'h5 fw-bold text-uppercase border-bottom pb-2 mb-3'),
            fx(kit.row(_sdT('Players Row'), players), 'slide-up', 'view', 80)
        ]);
    };
    var buildSquad = function () {
        return kit.section('py-5', _sdT('Sports Club (Squad)'), [
            header(_sdT('First team squad'), _sdT('The players wearing the shirt this season, by position.'),
                kit.link(_sdT('Meet every team'), '#', 'btn btn-outline-primary', _sdT('Teams Link'))),
            position(_sdT('Goalkeepers'), [
                player('squad-keeper', '1', _sdT('Okan Yıldız'), _sdT('Captain')),
                player('squad-keeper-2', '13', _sdT('Daniel Price'), _sdT('Joined from the academy'))
            ]),
            position(_sdT('Defenders'), [
                player('squad-defender', '5', _sdT('Barış Çelik'), _sdT('Centre back')),
                player('squad-defender-2', '2', _sdT('Luke Bennett'), _sdT('Right back')),
                player('squad-defender-3', '3', _sdT('Emre Kaya'), _sdT('Left back'))
            ]),
            position(_sdT('Midfielders'), [
                player('squad-midfielder', '8', _sdT('Tom Hughes'), _sdT('Player of the season')),
                player('squad-midfielder-2', '6', _sdT('Can Demir'), _sdT('Holding midfield'))
            ]),
            position(_sdT('Forwards'), [
                player('squad-forward', '9', _sdT('Kerem Aydın'), _sdT('Top scorer')),
                player('squad-forward-2', '11', _sdT('Sam Carter'), _sdT('Winger'))
            ])
        ]);
    };

    return {
        prefix: 'sportsclub-',
        group: { title: _sdT('Sports Club'), icon: 'bi-trophy' },
        items: [
            { label: _sdT('Sports Club: Match Day'),             type: 'component', icon: 'bi-stopwatch',     extra: { componentType: 'sportsclub-matchday' } },
            { label: _sdT('Sports Club: Fixtures and Results'),  type: 'component', icon: 'bi-calendar2-week', extra: { componentType: 'sportsclub-fixtures' } },
            { label: _sdT('Sports Club: League Table'),          type: 'component', icon: 'bi-list-ol',       extra: { componentType: 'sportsclub-table' } },
            { label: _sdT('Sports Club: Squad by Position'),     type: 'component', icon: 'bi-people',        extra: { componentType: 'sportsclub-squad' } }
        ],
        build: function (ctype) {
            if (ctype === 'sportsclub-matchday') return buildMatchday();
            if (ctype === 'sportsclub-fixtures') return buildFixtures();
            if (ctype === 'sportsclub-table')    return buildTable();
            if (ctype === 'sportsclub-squad')    return buildSquad();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
