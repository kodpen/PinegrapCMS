/**
 * Pinegrap - Enterprise Website Platform
 *
 * Planetarium block family: the sections a planetarium or public
 * observatory's site needs that the general palette lacks. Tonight's sky
 * (the dark hours, whether the telescopes open, the cloud cover and the
 * planets up tonight), the dome shows by day with their showtimes and seats
 * left, the sky objects shown through the telescope with what each needs to
 * be seen, and the moon's phases with the telescope nights marked. Every
 * value is sample text the staff edit in the designer: no live sky data, no
 * weather feed, no seat booking.
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
        'pg-sky-time':  '.pg-sky-time { font-variant-numeric: tabular-nums; letter-spacing: .02em; }',
        'pg-sky-orb':   '.pg-sky-orb { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 3rem; height: 3rem; border-radius: 50%; font-size: 1.5rem; }',
        'pg-sky-wane':  '.pg-sky-wane { display: inline-block; transform: scaleX(-1); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'planetarium-tonight': ['pg-sky-time', 'pg-sky-orb'],
        'planetarium-shows':   ['pg-sky-time'],
        'planetarium-objects': ['pg-sky-orb'],
        'planetarium-moon':    ['pg-sky-orb', 'pg-sky-wane']
    };

    // An entrance the operator can change or remove in the Interactions
    // section; the children come in one after another while the list
    // scrolls into view. pg_fx keeps them still under reduced motion.
    var enter = function (node, effect) {
        node.props._fx = { effect: effect || 'fade', trigger: 'view', stagger: 90, duration: 700 };
        return node;
    };

    // A pill that says its meaning with an icon and a word, so the colour is
    // never the only cue.
    var pill = function (cls, icon, text) {
        return kit.tag('span', 'badge rounded-pill text-wrap ' + cls, _sdT('Status Badge'), [
            kit.icon(icon, 'me-1'),
            kit.h('span', text)
        ]);
    };
    // A round marker that only decorates: screen readers skip it, the word
    // beside it carries the meaning.
    var orb = function (cls, kids) {
        return kit.tag('span', 'pg-sky-orb ' + cls, _sdT('Marker'), kids, [{ name: 'aria-hidden', value: 'true' }]);
    };
    // What an object needs to be seen: the eye, binoculars or a telescope.
    var needs = {
        eye:   function () { return pill('bg-success-subtle text-success-emphasis', 'bi-eye-fill', _sdT('Naked eye')); },
        bino:  function () { return pill('bg-info-subtle text-info-emphasis', 'bi-binoculars-fill', _sdT('Binoculars')); },
        scope: function () { return pill('bg-primary-subtle text-primary-emphasis', 'bi-search', _sdT('Telescope')); }
    };

    // ── Tonight's sky ───────────────────────────────────────────────────
    var moment = function (n, icon, label, at) {
        return kit.div('col', _sdT('Moment {var}', n), [
            kit.h('dt', label, 'small fw-normal text-body-secondary d-flex align-items-center gap-2'),
            kit.tag('dd', 'mb-0 d-flex align-items-center gap-2', _sdT('Moment Time'), [
                kit.icon(icon, 'text-primary'),
                kit.time(at, at, 'pg-sky-time h4 fw-bold mb-0', _sdT('Time'))
            ])
        ]);
    };
    // Five steps of cloud cover; tonight's is current and said in words.
    var cover = function (n, icon, name, current) {
        var item = kit.tag('li', 'text-center', _sdT('Cover {var}', n), [
            kit.icon(icon, current ? 'fs-3 text-primary' : 'fs-5 text-body-secondary'),
            kit.h('span', name, current ? 'd-block small fw-bold' : 'visually-hidden')
        ]);
        if (current) item.props._attrs = [{ name: 'aria-current', value: 'true' }];
        return item;
    };
    var planet = function (n, name, where, rises, sets, need) {
        return kit.tag('li', 'list-group-item d-flex flex-wrap align-items-center gap-3 py-3 bg-transparent', _sdT('Planet {var}', n), [
            orb('bg-body-secondary text-body-emphasis fw-bold', [kit.h('span', name.charAt(0))]),
            kit.div('flex-grow-1', _sdT('Planet Details'), [
                kit.h('h4', name, 'h6 fw-bold mb-0'),
                kit.p(where, 'small text-body-secondary mb-0')
            ]),
            kit.div('small text-nowrap', _sdT('Planet Times'), [
                kit.h('span', _sdT('Rises'), 'text-body-secondary me-1'),
                kit.time(rises, rises, 'pg-sky-time fw-semibold me-2', _sdT('Rises')),
                kit.h('span', _sdT('Sets'), 'text-body-secondary me-1'),
                kit.time(sets, sets, 'pg-sky-time fw-semibold', _sdT('Sets'))
            ]),
            needs[need]()
        ]);
    };
    var buildTonight = function () {
        return kit.section('py-5 bg-body-tertiary', _sdT('Planetarium (Tonight\'s Sky)'), [
            kit.row(_sdT('Tonight Row'), [
                kit.col(_sdT('Sky Column'), [
                    kit.h('span', _sdT('Tonight\'s sky'), 'd-block small fw-bold text-uppercase text-primary mb-2'),
                    kit.h('h2', _sdT('Clear enough to look up'), 'display-6 fw-bold mb-3'),
                    kit.div('mb-4', _sdT('Telescope Status'), [
                        pill('text-bg-success', 'bi-check-circle-fill', _sdT('Telescopes open tonight'))
                    ]),
                    kit.tag('dl', 'row row-cols-2 g-3 mb-4', _sdT('Dark Hours'), [
                        moment(1, 'bi-sunset', _sdT('Sunset'), '18:42'),
                        moment(2, 'bi-stars', _sdT('Full darkness'), '20:15'),
                        moment(3, 'bi-moon', _sdT('Moonrise'), '23:58'),
                        moment(4, 'bi-door-closed', _sdT('Dome closes'), '23:00')
                    ]),
                    kit.h('h3', _sdT('Cloud cover'), 'h6 fw-bold mb-2'),
                    kit.tag('ol', 'list-unstyled d-flex align-items-end gap-3 mb-2', _sdT('Cover Scale'), [
                        cover(1, 'bi-moon-stars', _sdT('Clear'), false),
                        cover(2, 'bi-cloud-moon', _sdT('Mostly clear'), true),
                        cover(3, 'bi-cloud', _sdT('Partly cloudy'), false),
                        cover(4, 'bi-clouds', _sdT('Mostly cloudy'), false),
                        cover(5, 'bi-cloud-drizzle', _sdT('Overcast'), false)
                    ]),
                    kit.p(_sdT('We decide at 17:00. If the sky closes in, the evening moves into the dome.'), 'small text-body-secondary mb-0')
                ], '5', '', '', 'mb-4 mb-lg-0'),
                kit.col(_sdT('Planets Column'), [
                    kit.div('card border-0 shadow-sm', _sdT('Planets Card'), [
                        kit.div('card-body p-4', _sdT('Card Body'), [
                            kit.h('h3', _sdT('Planets up tonight'), 'h5 fw-bold mb-3'),
                            enter(kit.tag('ul', 'list-group list-group-flush mb-4', _sdT('Planets'), [
                                planet(1, _sdT('Venus'),   _sdT('Low in the west after sunset'),     '18:50', '20:30', 'eye'),
                                planet(2, _sdT('Jupiter'), _sdT('High in the south by ten'),         '19:10', '04:20', 'bino'),
                                planet(3, _sdT('Saturn'),  _sdT('In the south-east, rings tilted'),  '21:05', '02:40', 'scope'),
                                planet(4, _sdT('Mars'),    _sdT('Rising in the east before midnight'), '23:30', '05:50', 'eye')
                            ]), 'slide-up'),
                            kit.div('d-flex flex-wrap gap-2', _sdT('Actions'), [
                                kit.link(_sdT('Book a telescope night'), '#', 'btn btn-primary', _sdT('Telescope Link')),
                                kit.link(_sdT('What is a telescope night?'), '#', 'btn btn-outline-secondary', _sdT('About Link'))
                            ])
                        ])
                    ])
                ], '7')
            ], 'g-lg-5 align-items-center')
        ]);
    };

    // ── Dome shows by day ───────────────────────────────────────────────
    var day = function (n, label, current) {
        var cls = 'btn btn-sm rounded-pill ' + (current ? 'btn-primary' : 'btn-outline-secondary');
        var link = kit.link(label, '#', cls, _sdT('Day {var}', n));
        if (current) link.props._attrs = [{ name: 'aria-current', value: 'date' }];
        return kit.tag('li', '', _sdT('Day Item {var}', n), [link]);
    };
    // A showtime: a link to the tickets while seats are left, a crossed-out
    // time with the words "Sold out" when none are.
    var showtime = function (at, state) {
        if (state === 'sold') {
            return kit.tag('li', 'd-flex align-items-center gap-1', _sdT('Showtime'), [
                kit.tag('span', 'd-inline-block px-2 py-1 border rounded small text-body-secondary text-decoration-line-through', _sdT('Showtime Box'), [
                    kit.time(at, at, 'pg-sky-time', _sdT('Time'))
                ]),
                kit.h('span', _sdT('Sold out'), 'small text-body-secondary')
            ]);
        }
        var link = kit.tag('a', 'btn btn-sm btn-outline-primary', _sdT('Showtime Link'), [
            kit.time(at, at, 'pg-sky-time', _sdT('Time'))
        ]);
        link.props.href = '#';
        link.props.target = '';
        var kids = [link];
        if (state === 'few') kids.push(pill('bg-warning-subtle text-warning-emphasis', 'bi-hourglass-split', _sdT('Few seats left')));
        return kit.tag('li', 'd-flex align-items-center gap-1', _sdT('Showtime'), kids);
    };
    var show = function (n, seed, title, text, minutes, age, lang, times) {
        return kit.col(_sdT('Show {var}', n), [
            kit.tag('article', 'card h-100 border-0 shadow-sm overflow-hidden', _sdT('Show Card'), [
                kit.img(seed, 800, 500, _sdT('Still from the dome show {var}', title), 'card-img-top'),
                kit.div('card-body d-flex flex-column p-4', _sdT('Card Body'), [
                    kit.h('h3', title, 'h5 fw-bold mb-2'),
                    kit.tag('ul', 'list-inline small text-body-secondary mb-2', _sdT('Show Facts'), [
                        kit.tag('li', 'list-inline-item', _sdT('Length'), [kit.icon('bi-clock', 'me-1'), kit.h('span', _sdT('{var} min', minutes))]),
                        kit.tag('li', 'list-inline-item', _sdT('Age'), [kit.icon('bi-people', 'me-1'), kit.h('span', _sdT('Ages {var}+', age))]),
                        kit.tag('li', 'list-inline-item', _sdT('Language'), [kit.icon('bi-translate', 'me-1'), kit.h('span', lang)])
                    ]),
                    kit.p(text, 'mb-4'),
                    kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mt-auto mb-0', _sdT('Showtimes'), times.map(function (t) {
                        return showtime(t[0], t[1]);
                    }))
                ])
            ])
        ], '4', '6');
    };
    var buildShows = function () {
        return kit.section('py-5', _sdT('Planetarium (Dome Shows)'), [
            kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Section Header'), [
                kit.div('', _sdT('Section Title'), [
                    kit.h('h2', _sdT('In the dome'), 'display-6 fw-bold mb-1'),
                    kit.p(_sdT('Full-dome shows under a sixteen-metre sky. Doors open ten minutes before each show.'), 'text-body-secondary mb-0')
                ]),
                kit.tag('ol', 'list-unstyled d-flex flex-wrap gap-2 mb-0', _sdT('Days'), [
                    day(1, _sdT('Today'), true),
                    day(2, _sdT('Tomorrow'), false),
                    day(3, _sdT('Saturday'), false),
                    day(4, _sdT('Sunday'), false)
                ])
            ]),
            enter(kit.row(_sdT('Shows Row'), [
                show(1, 'sky-show-solar', _sdT('Journey to the Planets'),
                    _sdT('Fly past every planet of the solar system and land on the ice of a distant moon.'), '35', '6', _sdT('Turkish'),
                    [['11:00', 'sold'], ['13:30', 'few'], ['16:00', '']]),
                show(2, 'sky-show-live', _sdT('Tonight\'s Sky, Live'),
                    _sdT('An astronomer turns the dome to tonight\'s sky and shows you what to look for when you step outside.'), '45', '10', _sdT('Turkish'),
                    [['19:00', ''], ['20:30', 'few']]),
                show(3, 'sky-show-kids', _sdT('Little Star Seekers'),
                    _sdT('A gentle first show for small children: the moon, the sun and the brightest stars.'), '25', '4', _sdT('Turkish and English'),
                    [['10:30', ''], ['12:00', ''], ['14:30', '']])
            ], 'mb-4'), 'slide-up'),
            kit.div('d-flex flex-wrap align-items-center gap-3', _sdT('Footer'), [
                kit.link(_sdT('Buy tickets'), '#', 'btn btn-primary', _sdT('Tickets Link')),
                kit.p(_sdT('Late arrivals cannot enter once the dome goes dark.'), 'small text-body-secondary mb-0')
            ])
        ]);
    };

    // ── Through the telescope ───────────────────────────────────────────
    var object = function (n, icon, kind, name, where, text, mag, need) {
        return kit.col(_sdT('Object {var}', n), [
            kit.tag('article', 'card h-100 border-0 bg-body-tertiary', _sdT('Object Card'), [
                kit.div('card-body d-flex flex-column p-4', _sdT('Card Body'), [
                    kit.div('d-flex align-items-center gap-3 mb-3', _sdT('Object Head'), [
                        orb('bg-primary-subtle text-primary-emphasis', [kit.icon(icon)]),
                        kit.div('', _sdT('Object Kind'), [
                            kit.h('span', kind, 'd-block small fw-bold text-uppercase text-primary'),
                            kit.h('h3', name, 'h5 fw-bold mb-0')
                        ])
                    ]),
                    kit.p(where, 'small text-body-secondary mb-2'),
                    kit.p(text, 'mb-4'),
                    kit.tag('dl', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto pt-3 border-top mb-0', _sdT('Object Facts'), [
                        kit.div('', _sdT('Brightness'), [
                            kit.h('dt', _sdT('Brightness'), 'visually-hidden'),
                            kit.h('dd', _sdT('Magnitude {var}', mag), 'small fw-semibold mb-0')
                        ]),
                        kit.div('', _sdT('Seen With'), [
                            kit.h('dt', _sdT('Seen with'), 'visually-hidden'),
                            kit.tag('dd', 'mb-0', _sdT('Seen With Value'), [needs[need]()])
                        ])
                    ])
                ])
            ])
        ], '3', '6');
    };
    var buildObjects = function () {
        return kit.section('py-5', _sdT('Planetarium (Through the Telescope)'), [
            kit.intro(_sdT('Through the telescope'), _sdT('What our astronomers point the big telescope at this season, and what you need to see each one at home.')),
            enter(kit.row(_sdT('Objects Row'), [
                object(1, 'bi-circle-half', _sdT('Planet'), _sdT('Saturn'), _sdT('In Aquarius, south-east after nine'),
                    _sdT('The rings and the largest moon, Titan, fit in one view.'), '0.8', 'scope'),
                object(2, 'bi-stars', _sdT('Star cluster'), _sdT('The Pleiades'), _sdT('In Taurus, high in the east'),
                    _sdT('Six stars to the eye, dozens in binoculars: the best first target there is.'), '1.6', 'eye'),
                object(3, 'bi-cloud-haze2', _sdT('Nebula'), _sdT('Orion Nebula'), _sdT('In Orion, below the belt'),
                    _sdT('A cloud where new stars are forming, with four young stars at its heart.'), '4.0', 'bino'),
                object(4, 'bi-hurricane', _sdT('Galaxy'), _sdT('Andromeda Galaxy'), _sdT('In Andromeda, overhead early evening'),
                    _sdT('The farthest thing you can see without help: its light left two and a half million years ago.'), '3.4', 'scope')
            ], 'mb-4'), 'fade'),
            kit.div('d-flex flex-wrap align-items-center gap-2', _sdT('Legend'), [
                kit.h('span', _sdT('Seen with:'), 'small text-body-secondary me-1'),
                needs.eye(), needs.bino(), needs.scope()
            ])
        ]);
    };

    // ── Moon phases and observing nights ────────────────────────────────
    var phase = function (n, icon, wane, name, when, night) {
        var kids = [
            kit.div('d-flex justify-content-center mb-2', _sdT('Phase Orb'), [
                orb('bg-body-secondary text-body-emphasis', [kit.icon(icon, wane ? 'pg-sky-wane' : '')])
            ]),
            kit.h('h3', name, 'h6 fw-bold mb-1'),
            kit.p(when, 'small text-body-secondary mb-2')
        ];
        if (night) kids.push(pill('text-bg-primary', 'bi-stars', _sdT('Telescope night')));
        return kit.tag('li', 'col text-center', _sdT('Phase {var}', n), kids);
    };
    var buildMoon = function () {
        return kit.section('py-5 bg-body-tertiary', _sdT('Planetarium (Moon and Observing Nights)'), [
            kit.div('col-lg-8 mb-5', _sdT('Section Header'), [
                kit.h('h2', _sdT('The moon and our telescope nights'), 'display-6 fw-bold mb-2'),
                kit.p(_sdT('We open the telescopes when the moon is young and the sky is dark; a full moon is the night to look at the moon itself.'), 'lead mb-0')
            ]),
            enter(kit.tag('ol', 'list-unstyled row row-cols-2 row-cols-sm-4 row-cols-lg-8 g-4 mb-4', _sdT('Phases'), [
                phase(1, 'bi-circle', false,      _sdT('New moon'),        _sdT('Today'), false),
                phase(2, 'bi-moon', true,         _sdT('Waxing crescent'), _sdT('In {var} days', '4'), true),
                phase(3, 'bi-circle-half', true,  _sdT('First quarter'),   _sdT('In {var} days', '7'), true),
                phase(4, 'bi-circle-fill', false, _sdT('Waxing gibbous'),  _sdT('In {var} days', '11'), false),
                phase(5, 'bi-circle-fill', false, _sdT('Full moon'),       _sdT('In {var} days', '15'), true),
                phase(6, 'bi-circle-fill', false, _sdT('Waning gibbous'),  _sdT('In {var} days', '18'), false),
                phase(7, 'bi-circle-half', false, _sdT('Last quarter'),    _sdT('In {var} days', '22'), false),
                phase(8, 'bi-moon', false,        _sdT('Waning crescent'), _sdT('In {var} days', '26'), false)
            ]), 'slide-up'),
            kit.div('d-flex flex-wrap align-items-center gap-3', _sdT('Footer'), [
                kit.link(_sdT('See all telescope nights'), '#', 'btn btn-primary', _sdT('Nights Link')),
                kit.p(_sdT('A telescope night is called off by 17:00 if the sky is overcast; your ticket moves to the next one.'), 'small text-body-secondary mb-0')
            ])
        ]);
    };

    return {
        prefix: 'planetarium-',
        group: { title: _sdT('Planetarium'), icon: 'bi-stars' },
        items: [
            { label: _sdT('Planetarium: Tonight\'s Sky'),           type: 'component', icon: 'bi-moon-stars',  extra: { componentType: 'planetarium-tonight' } },
            { label: _sdT('Planetarium: Dome Shows'),               type: 'component', icon: 'bi-film',        extra: { componentType: 'planetarium-shows' } },
            { label: _sdT('Planetarium: Through the Telescope'),    type: 'component', icon: 'bi-binoculars',  extra: { componentType: 'planetarium-objects' } },
            { label: _sdT('Planetarium: Moon and Observing Nights'), type: 'component', icon: 'bi-moon',       extra: { componentType: 'planetarium-moon' } }
        ],
        build: function (ctype) {
            if (ctype === 'planetarium-tonight') return buildTonight();
            if (ctype === 'planetarium-shows')   return buildShows();
            if (ctype === 'planetarium-objects') return buildObjects();
            if (ctype === 'planetarium-moon')    return buildMoon();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
