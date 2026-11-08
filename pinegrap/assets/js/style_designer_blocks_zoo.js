/**
 * Pinegrap - Enterprise Website Platform
 *
 * Zoo block family: the sections a zoo or aquarium's site needs that the
 * general palette lacks. Today's schedule (feeding times and keeper talks as
 * a timeline of the day with the next one marked), the animal spotlight with
 * its fact sheet and conservation status scale, the park map with numbered
 * habitats by zone, and the conservation projects with their status and
 * impact. Every value is sample text the staff edit in the designer: no live
 * schedule, no interactive map.
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
        'pg-zoo-time': '.pg-zoo-time { font-variant-numeric: tabular-nums; min-width: 4.5rem; }',
        'pg-zoo-pin':  '.pg-zoo-pin { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 1.75rem; height: 1.75rem; border-radius: 50%; font-size: .8125rem; font-weight: 700; font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'zoo-today':        ['pg-zoo-time'],
        'zoo-map':          ['pg-zoo-pin'],
        'zoo-conservation': ['pg-zoo-time']
    };

    // An entrance the operator can change or remove in the Interactions
    // section; the children rise one after another while the list scrolls
    // into view. pg_fx keeps them still under reduced motion.
    var rise = function (node) {
        node.props._fx = { effect: 'slide-up', trigger: 'view', stagger: 80 };
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
    var kinds = {
        feed: function () { return pill('bg-success-subtle text-success-emphasis', 'bi-basket2-fill', _sdT('Feeding')); },
        talk: function () { return pill('bg-info-subtle text-info-emphasis', 'bi-mic-fill', _sdT('Keeper talk')); }
    };

    // ── Today's schedule ────────────────────────────────────────────────
    var slot = function (n, at, kind, title, place, next) {
        var body = [
            kit.div('d-flex flex-wrap align-items-center gap-2 mb-1', _sdT('Slot Head'), [
                kit.h('h3', title, 'h5 fw-bold mb-0'),
                kinds[kind]()
            ]),
            kit.p(place, 'small text-body-secondary mb-0')
        ];
        if (next) {
            body[0].children.push(kit.h('span', _sdT('Next up'), 'badge rounded-pill text-bg-primary'));
        }
        var item = kit.tag('li', 'd-flex gap-3 p-3 rounded-3' + (next ? ' bg-body shadow-sm' : ''), _sdT('Slot {var}', n), [
            kit.time(at, at, 'pg-zoo-time h4 fw-bold mb-0 text-primary', _sdT('Slot Time')),
            kit.div('flex-grow-1 border-start border-2 ps-3', _sdT('Slot Details'), body)
        ]);
        if (next) item.props._attrs = [{ name: 'aria-current', value: 'time' }];
        return item;
    };
    var buildToday = function () {
        return kit.section('py-5 bg-body-tertiary', _sdT('Zoo (Today\'s Schedule)'), [
            kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Section Header'), [
                kit.div('', _sdT('Section Title'), [
                    kit.h('h2', _sdT('Today at the zoo'), 'display-6 fw-bold mb-1'),
                    kit.p(_sdT('Open from 9:00 to 18:00 today. Last entry at 17:00.'), 'text-body-secondary mb-0')
                ]),
                kit.div('d-flex flex-wrap gap-2', _sdT('Kind Legend'), [kinds.feed(), kinds.talk()])
            ]),
            kit.row(_sdT('Schedule Row'), [
                kit.col(_sdT('Schedule Column'), [
                    rise(kit.tag('ol', 'list-unstyled d-grid gap-2 mb-0', _sdT('Schedule'), [
                        slot(1, '10:30', 'feed', _sdT('Penguin feeding'),   _sdT('Habitat {var:1} · {var:2}', ['7', _sdT('Polar Coast')]), false),
                        slot(2, '11:15', 'talk', _sdT('Meet the giraffes'), _sdT('Habitat {var:1} · {var:2}', ['3', _sdT('Savanna')]), true),
                        slot(3, '13:00', 'feed', _sdT('Shark feeding'),     _sdT('Habitat {var:1} · {var:2}', ['12', _sdT('Aquarium')]), false),
                        slot(4, '14:30', 'talk', _sdT('Orangutan talk'),    _sdT('Habitat {var:1} · {var:2}', ['9', _sdT('Rainforest')]), false),
                        slot(5, '15:45', 'feed', _sdT('Otter feeding'),     _sdT('Habitat {var:1} · {var:2}', ['14', _sdT('River Walk')]), false)
                    ]))
                ], '8'),
                kit.col(_sdT('Tips Column'), [
                    kit.div('card h-100 border-0 shadow-sm', _sdT('Tips Card'), [
                        kit.div('card-body p-4', _sdT('Card Body'), [
                            kit.h('h3', _sdT('Good to know'), 'h5 fw-bold mb-3'),
                            kit.tag('ul', 'list-unstyled d-grid gap-3 mb-4', _sdT('Tips'), [
                                kit.tag('li', 'd-flex gap-2', _sdT('Tip {var}', 1), [kit.icon('bi-clock', 'text-primary flex-shrink-0'), kit.h('span', _sdT('A talk lasts about fifteen minutes; come five minutes early for a front spot.'))]),
                                kit.tag('li', 'd-flex gap-2', _sdT('Tip {var}', 2), [kit.icon('bi-heart', 'text-primary flex-shrink-0'), kit.h('span', _sdT('The animals set the pace: a feeding can move a little.'))]),
                                kit.tag('li', 'd-flex gap-2', _sdT('Tip {var}', 3), [kit.icon('bi-universal-access', 'text-primary flex-shrink-0'), kit.h('span', _sdT('Every habitat has a step-free viewing spot.'))])
                            ]),
                            kit.link(_sdT('Find them on the park map'), '#', 'btn btn-outline-primary', _sdT('Map Link'))
                        ])
                    ])
                ], '4')
            ], 'g-lg-5')
        ]);
    };

    // ── Animal spotlight ────────────────────────────────────────────────
    var fact = function (n, label, value) {
        return kit.div('col', _sdT('Fact {var}', n), [
            kit.h('dt', label, 'small fw-normal text-body-secondary'),
            kit.h('dd', value, 'fw-semibold mb-0')
        ]);
    };
    // The seven levels of the red list; the animal's own level is current.
    var level = function (n, code, name, current) {
        var item = kit.tag('li', '', _sdT('Level {var}', n), [
            kit.h('span', code, 'badge rounded-pill ' + (current ? 'text-bg-primary fs-6' : 'bg-secondary-subtle text-secondary-emphasis')),
            kit.h('span', name, 'visually-hidden')
        ]);
        if (current) item.props._attrs = [{ name: 'aria-current', value: 'true' }];
        return item;
    };
    var buildAnimal = function () {
        var latin = kit.h('span', _sdT('Giraffa reticulata'), 'fst-italic');
        latin.props._attrs = [{ name: 'lang', value: 'la' }];
        var photo = kit.img('zoo-giraffe', 900, 1100, _sdT('Amani the giraffe reaching for acacia leaves'), 'img-fluid w-100 rounded-4 shadow');
        photo.props._fx = { effect: 'scale', trigger: 'view', duration: 800 };
        return kit.section('py-5', _sdT('Zoo (Animal Spotlight)'), [
            kit.row(_sdT('Spotlight Row'), [
                kit.col(_sdT('Photo Column'), [
                    kit.tag('figure', 'mb-0', _sdT('Animal Photo'), [
                        photo,
                        kit.tag('figcaption', 'small text-body-secondary mt-2', _sdT('Photo Caption'), [
                            kit.h('span', _sdT('Amani on the Savanna, habitat {var}', '3'))
                        ])
                    ])
                ], '5', '', '', 'mb-4 mb-lg-0'),
                kit.col(_sdT('Story Column'), [
                    kit.h('span', _sdT('Animal spotlight'), 'd-block small fw-bold text-uppercase text-primary mb-2'),
                    kit.h('h2', _sdT('Meet Amani'), 'display-5 fw-bold mb-1'),
                    kit.div('mb-3', _sdT('Species'), [
                        kit.h('span', _sdT('Reticulated giraffe'), 'fw-semibold me-2'),
                        latin
                    ]),
                    kit.p(_sdT('Amani was born here and is the tallest of our herd. She greets the keepers every morning and never shares her favourite branch.'), 'lead mb-4'),
                    kit.tag('dl', 'row row-cols-2 row-cols-sm-4 g-3 mb-4', _sdT('Fact Sheet'), [
                        fact(1, _sdT('Habitat'),  _sdT('Savanna')),
                        fact(2, _sdT('Diet'),     _sdT('Leaves and twigs')),
                        fact(3, _sdT('Height'),   _sdT('{var} m', '4.6')),
                        fact(4, _sdT('Lifespan'), _sdT('About {var} years', '25'))
                    ]),
                    kit.h('h3', _sdT('Conservation status'), 'h6 fw-bold mb-2'),
                    kit.tag('ol', 'list-unstyled d-flex flex-wrap align-items-center gap-1 mb-2', _sdT('Status Scale'), [
                        level(1, 'LC', _sdT('Least Concern'), false),
                        level(2, 'NT', _sdT('Near Threatened'), false),
                        level(3, 'VU', _sdT('Vulnerable'), false),
                        level(4, 'EN', _sdT('Endangered'), true),
                        level(5, 'CR', _sdT('Critically Endangered'), false),
                        level(6, 'EW', _sdT('Extinct in the Wild'), false),
                        level(7, 'EX', _sdT('Extinct'), false)
                    ]),
                    kit.p(_sdT('Endangered: fewer than sixteen thousand live in the wild.'), 'small text-body-secondary mb-4'),
                    kit.div('d-flex flex-wrap gap-2', _sdT('Actions'), [
                        kit.link(_sdT('Adopt Amani'), '#', 'btn btn-primary', _sdT('Adopt Link')),
                        kit.link(_sdT('Find her on the map'), '#', 'btn btn-outline-secondary', _sdT('Map Link'))
                    ])
                ], '7')
            ], 'g-lg-5 align-items-center')
        ]);
    };

    // ── Park map ────────────────────────────────────────────────────────
    var habitat = function (num, name, pin) {
        return kit.tag('li', 'd-flex align-items-center gap-2', name, [
            kit.h('span', num, 'pg-zoo-pin ' + pin),
            kit.h('span', name)
        ]);
    };
    var zone = function (n, title, pin, habitats) {
        return kit.div('col', _sdT('Zone {var}', n), [
            kit.h('h3', title, 'h6 fw-bold mb-2'),
            kit.tag('ul', 'list-unstyled d-grid gap-2 mb-0', _sdT('Habitats'), habitats.map(function (h) {
                return habitat(h[0], h[1], pin);
            }))
        ]);
    };
    var amenity = function (n, icon, text) {
        return kit.tag('li', 'd-flex align-items-center gap-2', _sdT('Amenity {var}', n), [
            kit.icon(icon, 'text-primary'),
            kit.h('span', text)
        ]);
    };
    var buildMap = function () {
        return kit.section('py-5', _sdT('Zoo (Park Map)'), [
            kit.intro(_sdT('Park map'), _sdT('Twenty habitats in five zones, on one loop of about two and a half kilometres.')),
            kit.row(_sdT('Map Row'), [
                kit.col(_sdT('Map Column'), [
                    kit.tag('figure', 'mb-0', _sdT('Park Map'), [
                        kit.img('zoo-park-map', 1200, 900, _sdT('Map of the zoo with its numbered habitats and the step-free loop'), 'img-fluid w-100 rounded-4 border'),
                        kit.tag('figcaption', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-3', _sdT('Map Caption'), [
                            kit.h('span', _sdT('The numbers on the map match the signs at each habitat.'), 'small text-body-secondary'),
                            kit.link(_sdT('Download the map (PDF)'), '#', 'link-primary fw-semibold', _sdT('Map Download Link'))
                        ])
                    ])
                ], '7'),
                kit.col(_sdT('Legend Column'), [
                    rise(kit.div('row row-cols-1 row-cols-sm-2 g-4 mb-4', _sdT('Zones'), [
                        zone(1, _sdT('Savanna'),     'text-bg-warning', [['1', _sdT('Lions')], ['2', _sdT('Zebras')], ['3', _sdT('Giraffes')], ['4', _sdT('Rhinos')]]),
                        zone(2, _sdT('Polar Coast'), 'text-bg-info',    [['5', _sdT('Seals')], ['6', _sdT('Puffins')], ['7', _sdT('Penguins')]]),
                        zone(3, _sdT('Rainforest'),  'text-bg-success', [['8', _sdT('Tree frogs')], ['9', _sdT('Orangutans')], ['10', _sdT('Butterfly house')]]),
                        zone(4, _sdT('Aquarium'),    'text-bg-primary', [['11', _sdT('Coral reef')], ['12', _sdT('Shark tunnel')], ['13', _sdT('Jellyfish')]])
                    ])),
                    kit.h('h3', _sdT('Along the way'), 'h6 fw-bold mb-2'),
                    kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-3 small mb-4', _sdT('Amenities'), [
                        amenity(1, 'bi-cup-hot', _sdT('Café')),
                        amenity(2, 'bi-badge-wc', _sdT('Toilets')),
                        amenity(3, 'bi-bandaid', _sdT('First aid')),
                        amenity(4, 'bi-basket', _sdT('Picnic area')),
                        amenity(5, 'bi-universal-access', _sdT('Step-free route'))
                    ]),
                    kit.link(_sdT('Today\'s feeding times'), '#', 'btn btn-primary', _sdT('Schedule Link'))
                ], '5')
            ], 'g-lg-5')
        ]);
    };

    // ── Conservation projects ───────────────────────────────────────────
    var project = function (n, code, status, species, where, text, figure, unit) {
        return kit.col(_sdT('Project {var}', n), [
            kit.tag('article', 'card h-100 border-0 shadow-sm', _sdT('Project Card'), [
                kit.div('card-body d-flex flex-column p-4', _sdT('Card Body'), [
                    kit.div('mb-3', _sdT('Status'), [
                        pill('bg-danger-subtle text-danger-emphasis', 'bi-exclamation-diamond-fill', _sdT('{var:1} · {var:2}', [code, status]))
                    ]),
                    kit.h('h3', species, 'h5 fw-bold mb-1'),
                    kit.p(where, 'small text-body-secondary mb-3'),
                    kit.p(text, 'mb-4'),
                    kit.div('d-flex align-items-baseline gap-2 mt-auto pt-3 border-top', _sdT('Impact'), [
                        kit.h('span', figure, 'pg-zoo-time display-6 fw-bold text-primary lh-1'),
                        kit.h('span', unit, 'small')
                    ])
                ])
            ])
        ], '4', '6');
    };
    var buildConservation = function () {
        return kit.section('py-5 bg-success-subtle', _sdT('Zoo (Conservation Projects)'), [
            kit.div('col-lg-8 mb-5', _sdT('Section Header'), [
                kit.h('h2', _sdT('Conservation in the wild'), 'display-6 fw-bold mb-2'),
                kit.p(_sdT('A share of every ticket, membership and adoption goes to the field teams we work with.'), 'lead mb-0')
            ]),
            rise(kit.row(_sdT('Projects Row'), [
                project(1, 'CR', _sdT('Critically Endangered'), _sdT('Sumatran tiger'), _sdT('Sumatra · with a ranger team'),
                    _sdT('Camera traps follow the tigers through the forest so the rangers know where poachers set snares.'), '38', _sdT('camera traps in the forest')),
                project(2, 'EN', _sdT('Endangered'), _sdT('African penguin'), _sdT('South African coast · with a seabird centre'),
                    _sdT('Chicks left behind by their parents are raised by hand and returned to the colony.'), '120', _sdT('chicks released this season')),
                project(3, 'VU', _sdT('Vulnerable'), _sdT('Staghorn coral'), _sdT('Caribbean reef · with a marine lab'),
                    _sdT('Coral grown in our aquarium is planted back on damaged reef.'), '900', _sdT('coral pieces planted'))
            ], 'mb-5')),
            kit.div('d-flex flex-wrap gap-2', _sdT('Actions'), [
                kit.link(_sdT('Adopt an animal'), '#', 'btn btn-primary', _sdT('Adopt Link')),
                kit.link(_sdT('Conservation news'), '#', 'btn btn-outline-primary', _sdT('News Link'))
            ])
        ]);
    };

    return {
        prefix: 'zoo-',
        group: { title: _sdT('Zoo'), icon: 'bi-tree' },
        items: [
            { label: _sdT('Zoo: Today\'s Schedule'),        type: 'component', icon: 'bi-clock',          extra: { componentType: 'zoo-today' } },
            { label: _sdT('Zoo: Animal Spotlight'),         type: 'component', icon: 'bi-binoculars',     extra: { componentType: 'zoo-animal' } },
            { label: _sdT('Zoo: Park Map'),                 type: 'component', icon: 'bi-map',            extra: { componentType: 'zoo-map' } },
            { label: _sdT('Zoo: Conservation Projects'),    type: 'component', icon: 'bi-globe-americas', extra: { componentType: 'zoo-conservation' } }
        ],
        build: function (ctype) {
            if (ctype === 'zoo-today')        return buildToday();
            if (ctype === 'zoo-animal')       return buildAnimal();
            if (ctype === 'zoo-map')          return buildMap();
            if (ctype === 'zoo-conservation') return buildConservation();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
