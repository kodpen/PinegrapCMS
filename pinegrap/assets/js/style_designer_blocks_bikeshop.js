/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bike shop block family: the sections a bicycle shop and repair workshop's
 * site needs that the general palette lacks. The workshop board (how busy the
 * workshop is this week and how long each job takes), the frame size chart
 * with its lettered geometry, the group rides of the week with their pace,
 * distance and drop rule, and the bike fit with its numbered contact points
 * and fit sheet. Every value is sample text the staff edit in the designer:
 * no live queue, no size calculator.
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
        'pg-bike-num': '.pg-bike-num { font-variant-numeric: tabular-nums; }',
        'pg-bike-key': '.pg-bike-key { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 1.75rem; height: 1.75rem; border-radius: .375rem; font-size: .8125rem; font-weight: 700; font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'bikeshop-workshop': ['pg-bike-num'],
        'bikeshop-sizes':    ['pg-bike-num', 'pg-bike-key'],
        'bikeshop-rides':    ['pg-bike-num'],
        'bikeshop-fit':      ['pg-bike-num', 'pg-bike-key']
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

    // A square key that ties a list row to the same letter or number drawn
    // on a picture.
    var key = function (mark, cls) {
        return kit.h('span', mark, 'pg-bike-key ' + cls);
    };

    // ── Workshop board ──────────────────────────────────────────────────
    // The four load levels; this week's level is current.
    var load = function (n, name, current) {
        var item = kit.tag('li', '', _sdT('Level {var}', n), [
            kit.h('span', name, 'badge rounded-pill ' + (current ? 'text-bg-warning fs-6' : 'bg-secondary-subtle text-secondary-emphasis'))
        ]);
        if (current) item.props._attrs = [{ name: 'aria-current', value: 'true' }];
        return item;
    };
    var job = function (n, title, text, icon, turnaround) {
        return kit.tag('li', 'd-flex flex-wrap flex-sm-nowrap align-items-start gap-3 py-3 border-bottom', _sdT('Job {var}', n), [
            kit.div('flex-grow-1', _sdT('Job Details'), [
                kit.h('h3', title, 'h5 fw-bold mb-1'),
                kit.p(text, 'small text-body-secondary mb-0')
            ]),
            pill('bg-body-secondary text-body-emphasis pg-bike-num', icon, turnaround)
        ]);
    };
    var buildWorkshop = function () {
        return kit.section('py-5 bg-body-tertiary', _sdT('Bike Shop (Workshop Board)'), [
            kit.row(_sdT('Workshop Row'), [
                kit.col(_sdT('Load Column'), [
                    kit.h('span', _sdT('Workshop'), 'd-block small fw-bold text-uppercase text-primary mb-2'),
                    kit.h('h2', _sdT('The workshop this week'), 'display-6 fw-bold mb-3'),
                    kit.tag('ol', 'list-unstyled d-flex flex-wrap gap-1 mb-2', _sdT('Load Scale'), [
                        load(1, _sdT('Quiet'), false),
                        load(2, _sdT('Steady'), false),
                        load(3, _sdT('Busy'), true),
                        load(4, _sdT('Full'), false)
                    ]),
                    kit.p(_sdT('Busy: a bike dropped off today is ready in about {var} working days.', '3'), 'mb-4'),
                    kit.div('card border-0 shadow-sm', _sdT('Drop-off Card'), [
                        kit.div('card-body p-4', _sdT('Card Body'), [
                            kit.h('h3', _sdT('Next free drop-off'), 'h6 fw-bold mb-1'),
                            kit.p(_sdT('Thursday from 8:30'), 'h4 fw-bold text-primary pg-bike-num mb-3'),
                            kit.tag('ul', 'list-unstyled d-grid gap-2 small mb-4', _sdT('Bring Along'), [
                                kit.tag('li', 'd-flex gap-2', _sdT('Tip {var}', 1), [kit.icon('bi-key', 'text-primary flex-shrink-0'), kit.h('span', _sdT('The key of any lock left on the bike.'))]),
                                kit.tag('li', 'd-flex gap-2', _sdT('Tip {var}', 2), [kit.icon('bi-battery-charging', 'text-primary flex-shrink-0'), kit.h('span', _sdT('An e-bike\'s battery and charger.'))]),
                                kit.tag('li', 'd-flex gap-2', _sdT('Tip {var}', 3), [kit.icon('bi-chat-left-text', 'text-primary flex-shrink-0'), kit.h('span', _sdT('A word on what the bike does wrong: noises, slipping, rubbing.'))])
                            ]),
                            kit.link(_sdT('Request a service'), '#', 'btn btn-primary w-100', _sdT('Request Link'))
                        ])
                    ])
                ], '5', '', '', 'mb-4 mb-lg-0'),
                kit.col(_sdT('Jobs Column'), [
                    kit.h('h3', _sdT('How long it takes'), 'h5 fw-bold mb-2'),
                    rise(kit.tag('ul', 'list-unstyled mb-3', _sdT('Jobs'), [
                        job(1, _sdT('Puncture and tube'), _sdT('Tube, tyre check and pressure set; while you wait if the stand is free.'), 'bi-lightning-charge', _sdT('Same day')),
                        job(2, _sdT('Safety check'), _sdT('Brakes, gears, bolts and tyres looked at and set, with a list of what needs doing.'), 'bi-clock', _sdT('{var} day', '1')),
                        job(3, _sdT('Full service'), _sdT('Drivetrain cleaned, cables and pads as needed, wheels trued, bearings checked.'), 'bi-clock', _sdT('{var} days', '3')),
                        job(4, _sdT('Overhaul'), _sdT('The bike stripped to the frame, every bearing serviced, then built up again.'), 'bi-clock', _sdT('{var} days', '5')),
                        job(5, _sdT('Suspension service'), _sdT('Fork or shock seals and oil; sent to the maker when it needs more.'), 'bi-truck', _sdT('{var} days', '10'))
                    ])),
                    kit.p(_sdT('We call before any work beyond the price we agreed.'), 'small text-body-secondary mb-0')
                ], '7')
            ], 'g-lg-5')
        ]);
    };

    // ── Frame size chart ────────────────────────────────────────────────
    var cell = function (tag, text, cls, scope) {
        var attrs = scope ? [{ name: 'scope', value: scope }] : null;
        return kit.tag(tag, cls || '', tag === 'th' ? _sdT('Header Cell') : _sdT('Cell'), [kit.h('span', text)], attrs);
    };
    var head = function (mark, text) {
        return kit.tag('th', 'text-nowrap', _sdT('Header Cell'), [
            key(mark, 'text-bg-primary me-1'),
            kit.h('span', text)
        ], [{ name: 'scope', value: 'col' }]);
    };
    var size = function (name, rider, stack, reach, seat, top) {
        return kit.tag('tr', '', _sdT('Size Row'), [
            cell('th', name, 'fw-bold', 'row'),
            cell('td', rider), cell('td', stack), cell('td', reach), cell('td', seat), cell('td', top)
        ]);
    };
    var measure = function (mark, title, text) {
        return kit.tag('li', 'd-flex gap-2', title, [
            key(mark, 'text-bg-primary'),
            kit.div('', _sdT('Measure Text'), [
                kit.h('h3', title, 'h6 fw-bold mb-0'),
                kit.p(text, 'small text-body-secondary mb-0')
            ])
        ]);
    };
    var buildSizes = function () {
        var mm = function (v) { return _sdT('{var} mm', v); };
        return kit.section('py-5', _sdT('Bike Shop (Frame Sizes)'), [
            kit.div('col-lg-8 mb-4', _sdT('Section Header'), [
                kit.h('h2', _sdT('Find your frame size'), 'display-6 fw-bold mb-2'),
                kit.p(_sdT('Start from your height, then compare stack and reach with the bike you ride now.'), 'lead mb-0')
            ]),
            kit.row(_sdT('Sizes Row'), [
                kit.col(_sdT('Geometry Column'), [
                    kit.tag('figure', 'mb-4', _sdT('Geometry Drawing'), [
                        kit.img('bike-geometry', 900, 600, _sdT('Side view of a frame with stack, reach, seat tube and top tube marked A to D'), 'img-fluid w-100 rounded-3 border'),
                        kit.tag('figcaption', 'small text-body-secondary mt-2', _sdT('Drawing Caption'), [
                            kit.h('span', _sdT('The letters on the drawing match the table.'))
                        ])
                    ]),
                    kit.tag('ul', 'list-unstyled d-grid gap-3 mb-0', _sdT('Measures'), [
                        measure('A', _sdT('Stack'), _sdT('How high the bars sit: more stack, a more upright ride.')),
                        measure('B', _sdT('Reach'), _sdT('How far forward the bars sit: more reach, a longer, lower ride.'))
                    ])
                ], '5', '', '', 'mb-4 mb-lg-0'),
                kit.col(_sdT('Table Column'), [
                    kit.div('table-responsive', _sdT('Table Wrap'), [
                        kit.tag('table', 'table align-middle pg-bike-num mb-0', _sdT('Size Table'), [
                            kit.tag('caption', 'small text-body-secondary', _sdT('Table Caption'), [kit.h('span', _sdT('Frame sizes of our trail hardtail; every model has its own chart.'))]),
                            kit.tag('thead', '', _sdT('Table Head'), [
                                kit.tag('tr', '', _sdT('Header Row'), [
                                    cell('th', _sdT('Size'), '', 'col'),
                                    cell('th', _sdT('Rider height'), 'text-nowrap', 'col'),
                                    head('A', _sdT('Stack')),
                                    head('B', _sdT('Reach')),
                                    head('C', _sdT('Seat tube')),
                                    head('D', _sdT('Top tube'))
                                ])
                            ]),
                            kit.tag('tbody', '', _sdT('Table Body'), [
                                size('S',  _sdT('{var:1}–{var:2} cm', ['155', '167']), mm('598'), mm('420'), mm('380'), mm('575')),
                                size('M',  _sdT('{var:1}–{var:2} cm', ['167', '178']), mm('616'), mm('445'), mm('420'), mm('605')),
                                size('L',  _sdT('{var:1}–{var:2} cm', ['178', '188']), mm('634'), mm('470'), mm('460'), mm('632')),
                                size('XL', _sdT('{var:1}–{var:2} cm', ['188', '198']), mm('652'), mm('495'), mm('500'), mm('660'))
                            ])
                        ])
                    ]),
                    kit.div('d-flex flex-wrap align-items-center gap-3 mt-4', _sdT('Actions'), [
                        kit.p(_sdT('Between two sizes? A fitting settles it.'), 'mb-0'),
                        kit.link(_sdT('Book a bike fit'), '#', 'btn btn-outline-primary', _sdT('Fit Link'))
                    ])
                ], '7')
            ], 'g-lg-5')
        ]);
    };

    // ── Group rides ─────────────────────────────────────────────────────
    var paces = {
        social: function () { return pill('bg-success-subtle text-success-emphasis', 'bi-cup-hot', _sdT('Social')); },
        steady: function () { return pill('bg-info-subtle text-info-emphasis', 'bi-speedometer2', _sdT('Steady')); },
        fast:   function () { return pill('bg-danger-subtle text-danger-emphasis', 'bi-lightning-charge', _sdT('Fast')); }
    };
    var stat = function (n, label, value) {
        return kit.div('col', _sdT('Fact {var}', n), [
            kit.h('dt', label, 'small fw-normal text-body-secondary'),
            kit.h('dd', value, 'fw-semibold pg-bike-num mb-0')
        ]);
    };
    var ride = function (n, day, at, title, pace, nodrop, km, climb, speed, meet, next) {
        var drop = nodrop
            ? pill('bg-body-secondary text-body-emphasis', 'bi-people', _sdT('No-drop'))
            : pill('bg-body-secondary text-body-emphasis', 'bi-signpost-split', _sdT('Drop ride'));
        var heads = [kit.h('h3', title, 'h5 fw-bold mb-0'), paces[pace](), drop];
        if (next) heads.push(kit.h('span', _sdT('Next ride'), 'badge rounded-pill text-bg-primary'));
        var card = kit.tag('article', 'card h-100 ' + (next ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), _sdT('Ride Card'), [
            kit.div('card-body p-4', _sdT('Card Body'), [
                kit.div('d-flex align-items-baseline gap-2 mb-2', _sdT('Ride Time'), [
                    kit.h('span', day, 'fw-bold text-uppercase small text-primary'),
                    kit.time(at, at, 'pg-bike-num h4 fw-bold mb-0', _sdT('Start Time'))
                ]),
                kit.div('d-flex flex-wrap align-items-center gap-2 mb-3', _sdT('Ride Head'), heads),
                kit.tag('dl', 'row row-cols-3 g-2 mb-3', _sdT('Ride Facts'), [
                    stat(1, _sdT('Distance'), _sdT('{var} km', km)),
                    stat(2, _sdT('Climbing'), _sdT('{var} m', climb)),
                    stat(3, _sdT('Average'),  _sdT('{var} km/h', speed))
                ]),
                kit.p(meet, 'small text-body-secondary mb-0')
            ])
        ]);
        if (next) card.props._attrs = [{ name: 'aria-current', value: 'true' }];
        return kit.col(_sdT('Ride {var}', n), [card], '4', '6');
    };
    var buildRides = function () {
        var shop = _sdT('Meet at the shop, coffee after.');
        return kit.section('py-5', _sdT('Bike Shop (Group Rides)'), [
            kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Section Header'), [
                kit.div('', _sdT('Section Title'), [
                    kit.h('h2', _sdT('Ride with us this week'), 'display-6 fw-bold mb-1'),
                    kit.p(_sdT('Free and open to every rider; bring lights once the evenings draw in.'), 'text-body-secondary mb-0')
                ]),
                kit.div('d-flex flex-wrap gap-2', _sdT('Pace Legend'), [paces.social(), paces.steady(), paces.fast()])
            ]),
            rise(kit.row(_sdT('Rides Row'), [
                ride(1, _sdT('Tue'), '18:30', _sdT('Evening loop'),      'steady', true,  '42', '380', '26', shop, true),
                ride(2, _sdT('Thu'), '06:45', _sdT('Before-work chaingang'), 'fast', false, '55', '420', '32', shop, false),
                ride(3, _sdT('Sat'), '09:00', _sdT('Café ride'),         'social', true,  '35', '250', '20', _sdT('Meet at the park gates, cake at the halfway café.'), false)
            ], 'mb-4')),
            kit.div('d-flex flex-wrap align-items-center gap-3', _sdT('Actions'), [
                kit.link(_sdT('All rides and routes'), '#', 'btn btn-primary', _sdT('Calendar Link')),
                kit.link(_sdT('How we ride together'), '#', 'link-primary fw-semibold', _sdT('Rules Link'))
            ])
        ]);
    };

    // ── Bike fit ────────────────────────────────────────────────────────
    var point = function (n, title, text) {
        return kit.tag('li', 'd-flex gap-3', _sdT('Point {var}', n), [
            key(String(n), 'text-bg-primary'),
            kit.div('', _sdT('Point Text'), [
                kit.h('h3', title, 'h6 fw-bold mb-0'),
                kit.p(text, 'small text-body-secondary mb-0')
            ])
        ]);
    };
    var reading = function (n, label, value) {
        return kit.div('col', _sdT('Reading {var}', n), [
            kit.h('dt', label, 'small fw-normal text-body-secondary'),
            kit.h('dd', value, 'h5 fw-bold pg-bike-num mb-0')
        ]);
    };
    var buildFit = function () {
        var photo = kit.img('bike-fit', 1000, 800, _sdT('A rider on a bike in the fit studio, the five contact points numbered'), 'img-fluid w-100 rounded-3 shadow');
        photo.props._fx = { effect: 'fade', trigger: 'view', duration: 700 };
        return kit.section('py-5 bg-body-tertiary', _sdT('Bike Shop (Bike Fit)'), [
            kit.row(_sdT('Fit Row'), [
                kit.col(_sdT('Photo Column'), [
                    kit.tag('figure', 'mb-0', _sdT('Fit Photo'), [
                        photo,
                        kit.tag('figcaption', 'small text-body-secondary mt-2', _sdT('Photo Caption'), [
                            kit.h('span', _sdT('The numbers on the photo match the list.'))
                        ])
                    ])
                ], '6', '', '', 'mb-4 mb-lg-0'),
                kit.col(_sdT('Fit Column'), [
                    kit.h('span', _sdT('Bike fit'), 'd-block small fw-bold text-uppercase text-primary mb-2'),
                    kit.h('h2', _sdT('Five points where you meet the bike'), 'display-6 fw-bold mb-3'),
                    kit.p(_sdT('A fit takes about two hours on your own bike. We watch you pedal, measure, change one thing at a time and ride again.'), 'mb-4'),
                    rise(kit.tag('ol', 'list-unstyled d-grid gap-3 mb-4', _sdT('Contact Points'), [
                        point(1, _sdT('Saddle height'), _sdT('Set from the knee angle at the bottom of the stroke.')),
                        point(2, _sdT('Saddle setback'), _sdT('Where the saddle sits over the pedals.')),
                        point(3, _sdT('Reach to the bars'), _sdT('Stem length and bar position for a relaxed back.')),
                        point(4, _sdT('Bar drop'), _sdT('How far the bars sit below the saddle.')),
                        point(5, _sdT('Cleats'), _sdT('Angle and position under the foot, for knees that track straight.'))
                    ])),
                    kit.div('card border-0 shadow-sm mb-4', _sdT('Fit Sheet'), [
                        kit.div('card-body p-4', _sdT('Card Body'), [
                            kit.h('h3', _sdT('What you take home'), 'h6 fw-bold mb-3'),
                            kit.tag('dl', 'row row-cols-2 row-cols-sm-4 g-3 mb-0', _sdT('Fit Readings'), [
                                reading(1, _sdT('Saddle height'), _sdT('{var} mm', '742')),
                                reading(2, _sdT('Setback'),       _sdT('{var} mm', '58')),
                                reading(3, _sdT('Reach'),         _sdT('{var} mm', '512')),
                                reading(4, _sdT('Bar drop'),      _sdT('{var} mm', '64'))
                            ])
                        ])
                    ]),
                    kit.link(_sdT('Book a bike fit'), '#', 'btn btn-primary', _sdT('Fit Link'))
                ], '6')
            ], 'g-lg-5 align-items-center')
        ]);
    };

    return {
        prefix: 'bikeshop-',
        group: { title: _sdT('Bike Shop'), icon: 'bi-bicycle' },
        items: [
            { label: _sdT('Bike Shop: Workshop Board'), type: 'component', icon: 'bi-tools',         extra: { componentType: 'bikeshop-workshop' },
              desc: _sdT('The workshop load this week on a four-step scale, a card with the next free drop-off, and how long each repair takes.') },
            { label: _sdT('Bike Shop: Frame Sizes'),    type: 'component', icon: 'bi-rulers',        extra: { componentType: 'bikeshop-sizes' },
              desc: _sdT('A frame drawing with lettered measures beside a size table by rider height, stack, reach and tubes, and a fit button.') },
            { label: _sdT('Bike Shop: Group Rides'),    type: 'component', icon: 'bi-people',        extra: { componentType: 'bikeshop-rides' },
              desc: _sdT('Three group ride cards with day, start time, pace and drop badges, distance, climbing and speed, the next ride marked.') },
            { label: _sdT('Bike Shop: Bike Fit'),       type: 'component', icon: 'bi-person-bounding-box', extra: { componentType: 'bikeshop-fit' },
              desc: _sdT('A fitting photo beside five numbered contact points, a card of the measures you take home and a booking button.') }
        ],
        build: function (ctype) {
            if (ctype === 'bikeshop-workshop') return buildWorkshop();
            if (ctype === 'bikeshop-sizes')    return buildSizes();
            if (ctype === 'bikeshop-rides')    return buildRides();
            if (ctype === 'bikeshop-fit')      return buildFit();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
