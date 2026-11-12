/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual designer's Car Rental blocks (prefix carrental-): the sections
 * of a car rental company's site. A hero whose trip slip stands over the
 * picture of the car, with a small car that drives from the pick-up desk to
 * the drop-off desk on load; a sideways line-up of car classes, small to
 * big, each with the model it guarantees "or similar", its seats, bags and
 * gearbox, its day rate and deposit; the cover levels with their price
 * against their excess, the excess drawn as a meter that fills as it
 * scrolls in; and a sample quote set out as a receipt whose lines print one
 * after another. Motion comes from the nodes' interactions (props._fx,
 * played by pg_fx.src.js, which honours reduced motion).
 *
 * Registered with StyleDesigner.registerBlockFamily() and bundled after the
 * designer core (pg_designer_js_sources()); see
 * docs/memory/designer/designer-blocks-carrental.md.
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
        // The hero's trip slip stands over the picture: over its lower edge
        // on a phone (the picture comes first there), over its left side
        // from lg up. Pulled by negative margins in the flow, no absolute
        // position, so it keeps its height.
        'pg-carrental-slip':    '.pg-carrental-slip { position: relative; z-index: 1; margin: -4rem 1rem 0; } ' +
                                '@media (min-width: 992px) { .pg-carrental-slip { margin: 0 -6rem 0 0; } }',
        // A wide picture of the car.
        'pg-carrental-photo':   '.pg-carrental-photo { aspect-ratio: 4 / 3; }',
        // The trip from one desk to the other: a dashed road between them
        // and a car at its far end. The car's node is as wide as the road
        // and the road clips it, so an interaction that slides it in from
        // the left drives the car along the road.
        'pg-carrental-trip':    '.pg-carrental-trip .pg-carrental-road { flex: 1 1 auto; align-self: center; min-width: 2.5rem; overflow: hidden; ' +
                                'padding-bottom: .375rem; border-bottom: 2px dashed var(--bs-border-color); } ' +
                                '.pg-carrental-trip .pg-carrental-car { display: flex; justify-content: flex-end; font-size: 1.5rem; line-height: 1; }',
        // Prices and figures that line up.
        'pg-carrental-figure':  '.pg-carrental-figure { font-variant-numeric: tabular-nums; line-height: 1; }',
        // The class line-up: a rail that scrolls sideways and stops on each
        // card, wide enough on a large screen to show that there is more.
        'pg-carrental-lineup':  '.pg-carrental-rail { overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 1rem; } ' +
                                '.pg-carrental-lineup { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(16.5rem, 1fr); gap: 1.5rem; } ' +
                                '.pg-carrental-lineup .pg-carrental-class { scroll-snap-align: start; }',
        // The quote as a receipt: a dashed edge and figures in columns.
        'pg-carrental-receipt': '.pg-carrental-receipt { border: 2px dashed var(--bs-border-color); border-radius: 1rem; font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'carrental-hero':    ['pg-carrental-slip', 'pg-carrental-photo', 'pg-carrental-trip', 'pg-carrental-figure'],
        'carrental-lineup':  ['pg-carrental-lineup', 'pg-carrental-figure', 'pg-ratio-16x9'],
        'carrental-cover':   ['pg-carrental-figure'],
        'carrental-quote':   ['pg-carrental-receipt', 'pg-carrental-figure']
    };

    // A node with an interaction (props._fx, designer-interactions.md).
    var fx = function (node, effect) {
        node.props._fx = effect;
        return node;
    };
    // A dark band: the palette's dark colours apply inside it.
    var dark = [{ name: 'data-bs-theme', value: 'dark' }];
    // The road and the meters are decoration: the words beside them say it.
    var hidden = [{ name: 'aria-hidden', value: 'true' }];
    var eyebrow = function (text, cls) {
        return kit.h('span', text, 'd-block mb-3 small fw-semibold text-uppercase ' + (cls || 'text-primary'));
    };
    // An amount and the period after it, as two nodes.
    var rate = function (amount, cls) {
        return kit.div('d-flex align-items-baseline gap-1', _sdT('Daily rate'), [
            kit.h('span', amount, 'pg-carrental-figure fw-bold text-body-emphasis ' + (cls || 'fs-3')),
            kit.h('span', _sdT('/day'), 'small text-body-secondary')
        ]);
    };
    // The model a class guarantees, or one like it.
    var model = function (name, cls) {
        return kit.div('d-flex flex-wrap gap-1 ' + (cls || ''), _sdT('Model'), [
            kit.h('span', name, 'fw-semibold text-body-emphasis'),
            kit.h('span', _sdT('or similar'), 'text-body-secondary')
        ]);
    };
    // A line with an icon in front.
    var tick = function (icon, text, cls) {
        return kit.tag('li', 'd-flex align-items-center gap-2 ' + (cls || ''), _sdT('Item'), [
            kit.icon(icon, 'text-primary'),
            kit.h('span', text)
        ]);
    };

    return {
        prefix: 'carrental-',
        group: { title: _sdT('Car Rental'), icon: 'bi-key' },
        items: [
            { label: _sdT('Car Rental: Trip Hero'),     type: 'component', icon: 'bi-car-front',     extra: { componentType: 'carrental-hero' },
              desc: _sdT('A very large headline over a trip slip of desks, dates, car class, day rate and two buttons, on a car photo, then three facts.') },
            { label: _sdT('Car Rental: Class Line-up'), type: 'component', icon: 'bi-layout-three-columns', extra: { componentType: 'carrental-lineup' },
              desc: _sdT('Car classes from small to big on a sideways rail, each card with a sample model, seats, bags, gearbox, day rate and deposit.') },
            { label: _sdT('Car Rental: Cover Levels'),  type: 'component', icon: 'bi-shield-check',  extra: { componentType: 'carrental-cover' },
              desc: _sdT('A dark band of three cover levels, each with its day price, the excess left as a filling meter and what it covers.') },
            { label: _sdT('Car Rental: Sample Quote'),  type: 'component', icon: 'bi-receipt',       extra: { componentType: 'carrental-quote' },
              desc: _sdT('What a week costs set out as a receipt, line by line, with the total and the deposit at its foot, beside a booking button.') }
        ],
        build: function (ctype) {

            // Car Rental: Trip Hero — a very large line rises on load; under
            // it the trip slip (pick-up and drop-off desk, day and hour, the
            // class, its day rate, two buttons) stands over the picture of
            // the car, which slides in from the right; a small car drives
            // along the slip's road; three facts fade in last.
            if (ctype === 'carrental-hero') {
                var _chEnd = function (label, place, day, datetime, cls) {
                    return kit.div(cls || '', label, [
                        kit.h('span', label, 'd-block small text-body-secondary'),
                        kit.h('span', place, 'd-block fw-semibold text-body-emphasis'),
                        kit.div('d-flex gap-1 small', _sdT('Day and time'), [
                            kit.h('span', day),
                            kit.time(datetime, datetime, 'pg-carrental-figure', _sdT('Time'))
                        ])
                    ]);
                };
                return kit.tag('section', 'py-5 overflow-hidden', _sdT('Car Rental Hero'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                        fx(kit.div('col-lg-10 mb-5', _sdT('Hero Text'), [
                            eyebrow(_sdT('Car rental at the airport and in town')),
                            kit.h('h1', _sdT('Your car is ready when you land.'), 'display-2 fw-bold lh-1 mb-4'),
                            kit.p(_sdT('Clean, checked cars at the airport, in town and by the sea'), 'lead text-body-secondary mb-0')
                        ]), { effect: 'slide-up', trigger: 'load', duration: 900 }),
                        kit.row(_sdT('Hero Row'), [
                            kit.col(_sdT('Trip Slip'), [
                                fx(kit.div('pg-carrental-slip card border-0 shadow-lg', _sdT('Trip Slip'), [
                                    kit.div('card-body p-3 p-sm-4 p-xl-5', _sdT('Card Body'), [
                                        kit.h('h2', _sdT('Plan your trip'), 'h5 fw-bold mb-4'),
                                        kit.div('pg-carrental-trip d-flex gap-3 mb-4', _sdT('Route'), [
                                            _chEnd(_sdT('Pick-up desk'), _sdT('Airport Desk'), _sdT('Friday'), '10:00'),
                                            kit.div('pg-carrental-road', _sdT('Road'), [
                                                fx(kit.div('pg-carrental-car text-primary', _sdT('Car'), [ kit.icon('bi-car-front-fill') ]),
                                                    { effect: 'slide-right', trigger: 'load', duration: 1800, delay: 900, distance: 400, easing: 'ease-in-out' })
                                            ], hidden),
                                            _chEnd(_sdT('Return desk'), _sdT('Coast Office'), _sdT('Monday'), '09:00', 'text-end')
                                        ]),
                                        kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 py-3 border-top border-bottom mb-4', _sdT('Price Row'), [
                                            kit.div('', _sdT('Car Class'), [
                                                kit.h('span', _sdT('Mid-size'), 'd-block small text-body-secondary'),
                                                model(_sdT('Lumo Five'))
                                            ]),
                                            rate(_sdT('$36'), 'fs-2')
                                        ]),
                                        kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                                            kit.btn(_sdT('Book a Car'), 'primary', 'lg'),
                                            kit.btn(_sdT('Our Fleet'), 'outline-primary', 'lg')
                                        ])
                                    ])
                                ]), { effect: 'slide-up', trigger: 'load', duration: 800, delay: 400, distance: 60 })
                            ], '5', '', '', 'order-2 order-lg-1'),
                            kit.col(_sdT('Hero Picture'), [
                                fx(kit.img('carrental-hero', 1200, 900, _sdT('A rental car on a coast road by the sea'), 'pg-carrental-photo w-100 object-fit-cover rounded-4 shadow'),
                                    { effect: 'slide-left', trigger: 'load', duration: 1400, delay: 100, distance: 400 })
                            ], '7', '', '', 'order-1 order-lg-2')
                        ], 'align-items-center g-lg-0'),
                        fx(kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-3 gap-lg-5 small mt-5 mb-0', _sdT('Facts'), [
                            tick('bi-calendar-check', _sdT('Free cancellation up to 48 hours before')),
                            tick('bi-infinity', _sdT('Unlimited mileage')),
                            tick('bi-airplane', _sdT('Airport desk open around the clock'))
                        ]), { effect: 'fade', trigger: 'load', duration: 800, delay: 1200, stagger: 120 })
                    ])
                ]);
            }

            // Car Rental: Class Line-up — the classes from small to big on a
            // rail that scrolls sideways; each card names the model it
            // guarantees "or similar", its seats, bags and gearbox, its day
            // rate and its deposit. The cards drive in from the right one
            // after another as the rail scrolls in.
            if (ctype === 'carrental-lineup') {
                var _clClass = function (n, seed, alt, name, car, seats, bags, gearbox, price, deposit, badge) {
                    var head = [ kit.h('h3', name, 'h4 fw-bold mb-0') ];
                    if (badge) head.push(kit.h('span', badge, 'badge rounded-pill text-bg-primary text-wrap'));
                    return kit.tag('li', 'pg-carrental-class', _sdT('Card {var}', n), [
                        kit.tag('article', 'card h-100 border-0 shadow-sm overflow-hidden', _sdT('Card'), [
                            kit.img(seed, 800, 450, alt, 'pg-ratio-16x9 w-100 object-fit-cover'),
                            kit.div('card-body p-4 d-flex flex-column', _sdT('Card Body'), [
                                kit.div('d-flex flex-wrap align-items-center gap-2 mb-1', _sdT('Title'), head),
                                model(car, 'small mb-3'),
                                kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-4', _sdT('Facts'), [
                                    tick('bi-people', _sdT('{var} seats', seats)),
                                    tick('bi-briefcase', _sdT('{var} bags', bags)),
                                    tick('bi-gear', gearbox)
                                ]),
                                kit.tag('dl', 'd-flex align-items-end justify-content-between gap-3 mt-auto mb-0 pt-3 border-top', _sdT('Prices'), [
                                    kit.div('', _sdT('Daily rate'), [
                                        kit.tag('dt', 'small fw-normal text-body-secondary mb-1', _sdT('Label'), [ kit.h('span', _sdT('Daily rate')) ]),
                                        kit.tag('dd', 'mb-0', _sdT('Amount'), [ rate(price) ])
                                    ]),
                                    kit.div('text-end', _sdT('Security deposit'), [
                                        kit.tag('dt', 'small fw-normal text-body-secondary mb-1', _sdT('Label'), [ kit.h('span', _sdT('Security deposit')) ]),
                                        kit.tag('dd', 'pg-carrental-figure fw-semibold mb-0', _sdT('Amount'), [ kit.h('span', deposit) ])
                                    ])
                                ])
                            ])
                        ])
                    ]);
                };
                return kit.section('py-5', _sdT('Car Rental Class Line-up'), [
                    fx(kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Section Header'), [
                        kit.div('col-lg-7', _sdT('Text'), [
                            eyebrow(_sdT('The fleet')),
                            kit.h('h2', _sdT('Choose a car class'), 'display-5 fw-bold mb-3'),
                            kit.p(_sdT('You book a class, not a model: the car at the desk is the one shown or one just like it.'), 'lead text-body-secondary mb-0')
                        ]),
                        kit.btn(_sdT('All cars'), 'outline-primary', 'lg')
                    ]), { effect: 'fade', trigger: 'view', duration: 700 }),
                    kit.div('pg-carrental-rail', _sdT('Class Rail'), [
                        fx(kit.tag('ol', 'pg-carrental-lineup list-unstyled mb-0', _sdT('Car Classes'), [
                            _clClass(1, 'carrental-economy', _sdT('A small three-door city car'), _sdT('Economy'), _sdT('Lumo One'), '4', '1', _sdT('Manual'), _sdT('$29'), _sdT('$250')),
                            _clClass(2, 'carrental-midsize', _sdT('A five-door family hatchback'), _sdT('Mid-size'), _sdT('Lumo Five'), '5', '2', _sdT('Automatic'), _sdT('$36'), _sdT('$300'), _sdT('Most chosen')),
                            _clClass(3, 'carrental-electric', _sdT('An electric car at a charging point'), _sdT('Electric'), _sdT('Vela Spark'), '5', '2', _sdT('Automatic'), _sdT('$49'), _sdT('$400')),
                            _clClass(4, 'carrental-suv', _sdT('A high SUV on a gravel road'), _sdT('SUV'), _sdT('Ferra Trek'), '5', '4', _sdT('Automatic'), _sdT('$55'), _sdT('$450')),
                            _clClass(5, 'carrental-van', _sdT('A nine-seat people carrier'), _sdT('People Carrier'), _sdT('Ferra Nine'), '9', '6', _sdT('Manual'), _sdT('$69'), _sdT('$500')),
                            _clClass(6, 'carrental-premium', _sdT('A long premium saloon'), _sdT('Premium'), _sdT('Odin Grand'), '5', '3', _sdT('Automatic'), _sdT('$92'), _sdT('$800'))
                        ]), { effect: 'slide-left', trigger: 'view', duration: 700, stagger: 90, distance: 80 })
                    ], kit.region(_sdT('Car classes')))
                ]);
            }

            // Car Rental: Cover Levels — a dark band of three covers: what
            // each costs a day against the excess it leaves, the excess drawn
            // as a meter that fills as the cards scroll in, and what each
            // covers. The middle one is marked as the most chosen.
            if (ctype === 'carrental-cover') {
                var _ccLine = function (text, covered) {
                    if (covered) return tick('bi-check-circle-fill', text, 'mb-2');
                    return kit.tag('li', 'd-flex align-items-center gap-2 mb-2 text-body-secondary', _sdT('Item'), [
                        kit.icon('bi-dash-circle'),
                        kit.h('span', _sdT('Not covered:'), 'visually-hidden'),
                        kit.h('span', text, 'text-decoration-line-through')
                    ]);
                };
                var _ccCover = function (n, name, text, price, excess, bar, lines, chosen) {
                    var head = [ kit.h('h3', name, 'h4 fw-bold mb-0') ];
                    if (chosen) head.push(kit.h('span', _sdT('Most chosen'), 'badge rounded-pill text-bg-primary text-wrap'));
                    var meter = bar ? [ fx(kit.div('progress-bar ' + bar, _sdT('Bar')), { effect: 'slide-right', trigger: 'view', duration: 1400, delay: 300 + n * 150, easing: 'ease-in-out' }) ] : [];
                    return kit.div('col', _sdT('Card {var}', n), [
                        kit.tag('article', 'card h-100 p-4 ' + (chosen ? 'border-primary border-2 shadow-lg' : 'border-0'), _sdT('Card'), [
                            kit.div('d-flex flex-wrap align-items-center gap-2 mb-2', _sdT('Title'), head),
                            kit.p(text, 'small text-body-secondary mb-4'),
                            price ? rate(price, 'fs-2') : kit.h('span', _sdT('Included'), 'pg-carrental-figure fs-2 fw-bold text-body-emphasis'),
                            kit.tag('dl', 'my-4', _sdT('Excess'), [
                                kit.div('d-flex align-items-baseline justify-content-between gap-2 mb-2', _sdT('Excess'), [
                                    kit.tag('dt', 'small fw-normal text-body-secondary', _sdT('Label'), [ kit.h('span', _sdT('Excess')) ]),
                                    kit.tag('dd', 'pg-carrental-figure fs-5 fw-semibold mb-0', _sdT('Amount'), [ kit.h('span', excess) ])
                                ])
                            ]),
                            kit.div('progress mb-4', _sdT('Progress Bar'), meter, hidden),
                            kit.tag('ul', 'list-unstyled small mb-0', _sdT('Lines'), lines)
                        ])
                    ]);
                };
                var _ccLines = function (glass, roadside) {
                    return [
                        _ccLine(_sdT('Third-party liability'), true),
                        _ccLine(_sdT('Theft and fire'), true),
                        _ccLine(_sdT('Glass, tyres and mirrors'), glass),
                        _ccLine(_sdT('Roadside help around the clock'), roadside)
                    ];
                };
                return kit.tag('section', 'py-5 bg-body text-body', _sdT('Car Rental Cover Levels'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                        fx(kit.div('col-lg-8 mb-5', _sdT('Section Header'), [
                            eyebrow(_sdT('Insurance')),
                            kit.h('h2', _sdT('Choose your cover'), 'display-5 fw-bold mb-3'),
                            kit.p(_sdT('Every rental is insured. The cover sets the excess: the most you pay if the car comes back damaged.'), 'lead text-body-secondary mb-0')
                        ]), { effect: 'fade', trigger: 'view', duration: 700 }),
                        fx(kit.div('row row-cols-1 row-cols-lg-3 g-4 align-items-stretch', _sdT('Cards'), [
                            _ccCover(1, _sdT('Basic Cover'), _sdT('The cover every rental comes with.'), '', _sdT('$900'), 'w-100', _ccLines(false, false)),
                            _ccCover(2, _sdT('Plus Cover'), _sdT('A lower excess, with glass and tyres covered.'), _sdT('$9'), _sdT('$450'), 'w-50', _ccLines(true, false), true),
                            _ccCover(3, _sdT('Full Cover'), _sdT('No excess to pay for damage, tyres or glass, and a smaller deposit at pick-up.'), _sdT('$15'), _sdT('$0'), '', _ccLines(true, true))
                        ]), { effect: 'slide-up', trigger: 'view', duration: 700, stagger: 120, distance: 40 }),
                        kit.div('d-flex align-items-center justify-content-center gap-2 mt-4 small text-body-secondary text-center', _sdT('Note'), [
                            kit.icon('bi-info-circle'),
                            kit.h('span', _sdT('Ask us before you take the car on a ferry or across a border: we prepare the papers and the cover.'))
                        ])
                    ])
                ], dark);
            }

            // Car Rental: Sample Quote — what a week costs, set out as a
            // receipt: the class, the cover, what comes free and the deal,
            // each line printing a moment after the one before; the total
            // and the deposit held at the foot. Beside it the words and the
            // way to book.
            if (ctype === 'carrental-quote') {
                var _cqCell = function (tag, kids, cls, attrs) {
                    return kit.tag(tag, cls, tag === 'th' ? _sdT('Header cell') : _sdT('Cell'), kids, attrs);
                };
                var _cqLine = function (name, detail, amount, cls) {
                    var label = [ kit.h('span', name, 'd-block fw-semibold') ];
                    if (detail) label.push(kit.h('span', detail, 'd-block small text-body-secondary'));
                    return kit.tag('tr', '', name, [
                        _cqCell('th', label, 'fw-normal py-3', [{ name: 'scope', value: 'row' }]),
                        _cqCell('td', [ kit.h('span', amount) ], 'text-end fw-semibold py-3 ' + (cls || ''))
                    ]);
                };
                return kit.tag('section', 'py-5 bg-body-tertiary', _sdT('Car Rental Sample Quote'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                        kit.row(_sdT('Row'), [
                            kit.col(_sdT('Text'), [
                                fx(kit.div('', _sdT('Body'), [
                                    eyebrow(_sdT('Sample quote'), 'text-body-emphasis'),
                                    kit.h('h2', _sdT('What a week costs'), 'display-5 fw-bold mb-3'),
                                    kit.p(_sdT('A mid-size car for seven days with Full Cover: every line on the bill, nothing added at the desk.'), 'lead text-body-secondary mb-4'),
                                    kit.tag('ul', 'list-unstyled mb-4', _sdT('Facts'), [
                                        tick('bi-infinity', _sdT('Unlimited mileage'), 'mb-2'),
                                        tick('bi-calendar-check', _sdT('Free cancellation up to 48 hours before'), 'mb-2'),
                                        tick('bi-fuel-pump', _sdT('Full to full: you collect the car full and return it full. Electric cars come back with at least half a charge.'), 'mb-2')
                                    ]),
                                    kit.btn(_sdT('Book a Car'), 'primary', 'lg')
                                ]), { effect: 'fade', trigger: 'view', duration: 800 })
                            ], '5'),
                            kit.col(_sdT('Your quote'), [
                                fx(kit.div('pg-carrental-receipt bg-body p-4 p-lg-5 shadow', _sdT('Card'), [
                                    kit.div('d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3', _sdT('Header'), [
                                        kit.h('span', _sdT('Your quote'), 'small fw-semibold text-uppercase text-primary'),
                                        model(_sdT('Lumo Five'), 'small')
                                    ]),
                                    kit.tag('table', 'table table-borderless align-middle mb-0', _sdT('Table'), [
                                        fx(kit.tag('tbody', '', _sdT('Table Body'), [
                                            _cqLine(_sdT('Mid-size'), _sdT('7 days'), _sdT('$252')),
                                            _cqLine(_sdT('Full Cover'), _sdT('7 days'), _sdT('$105')),
                                            _cqLine(_sdT('Unlimited mileage'), '', _sdT('Included'), 'text-body-secondary'),
                                            _cqLine(_sdT('Seven Days for Six'), _sdT('One day free'), _sdT('−$36'), 'text-primary')
                                        ]), { effect: 'fade', trigger: 'view', duration: 500, delay: 300, stagger: 180 }),
                                        kit.tag('tfoot', '', _sdT('Table Foot'), [
                                            kit.tag('tr', '', _sdT('Total'), [
                                                _cqCell('th', [ kit.h('span', _sdT('Total')) ], 'border-top pt-3 fs-5', [{ name: 'scope', value: 'row' }]),
                                                _cqCell('td', [ kit.h('span', _sdT('$321')) ], 'pg-carrental-figure border-top pt-3 text-end fs-2 fw-bold text-body-emphasis')
                                            ])
                                        ])
                                    ]),
                                    kit.div('d-flex align-items-start gap-3 mt-4 pt-3 border-top small', _sdT('Security deposit'), [
                                        kit.icon('bi-shield-lock', 'text-primary fs-5'),
                                        kit.div('flex-grow-1', _sdT('Text'), [
                                            kit.div('d-flex justify-content-between gap-2 fw-semibold', _sdT('Price Row'), [
                                                kit.h('span', _sdT('Security deposit')),
                                                kit.h('span', _sdT('$300'), 'pg-carrental-figure')
                                            ]),
                                            kit.p(_sdT('Held on your card at pick-up and released when the car is back.'), 'text-body-secondary mb-0')
                                        ])
                                    ])
                                ]), { effect: 'slide-up', trigger: 'view', duration: 800, distance: 60 })
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
