/**
 * Pinegrap - Enterprise Website Platform
 *
 * General venue blocks the core's Hours & Locations group lacks. Spaces to
 * hire: a card per room, hall or terrace with its picture, how many it
 * seats, its size and layout, what it comes with, a starting price and a
 * way to ask for a date. A hotel's meeting rooms, an event venue's halls, a
 * coworking floor or a museum's rentals place it with their own content.
 * The core's Locations: Cards keeps the branch cards (address, hours,
 * phone). The floor directory: a building's floors from the top down, each
 * with its level, what is on it and who may go up, for a coworking house,
 * a hotel, a museum or a shopping arcade. Delivery zones: the rings around
 * a shop drawn beside each zone's places, when an order arrives and its
 * fee, then the delivery times and the last order time for the same day,
 * for a florist, a bakery, a market, a pharmacy or a restaurant.
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
        'pg-space-facts': '.pg-space-facts { font-variant-numeric: tabular-nums; }',
        // A floor's level: a square of one size whatever its figure or word.
        'pg-floor-level': '.pg-floor-level { display: inline-flex; align-items: center; justify-content: center; width: 4rem; height: 4rem; flex-shrink: 0; font-variant-numeric: tabular-nums; }',
        // The zones as rings around the shop, each inside the next: a ring
        // keeps its round shape from its width, and its padding is the
        // band the zone covers, so no ring is placed by position.
        'pg-delivery-map': '.pg-delivery-map { max-width: 22rem; margin-inline: auto; } ' +
                           '.pg-delivery-map .pg-delivery-ring { aspect-ratio: 1 / 1; padding: 15%; border-radius: 50%; } ' +
                           '.pg-delivery-map .pg-delivery-z1 { display: flex; align-items: center; justify-content: center; padding: 0; }',
        // A zone's key in the list, drawn like its ring; the outer zone's
        // line is dashed (the border utilities read the style from it).
        'pg-delivery-key': '.pg-delivery-key { width: 1.25rem; height: 1.25rem; flex-shrink: 0; margin-top: .25rem; border-radius: 50%; } ' +
                           '.pg-delivery-z3 { --bs-border-style: dashed; } ' +
                           '.pg-delivery-zones { font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'locations-spaces': ['pg-ratio-16x9', 'pg-space-facts'],
        'locations-floors': ['pg-floor-level'],
        'locations-delivery': ['pg-delivery-map', 'pg-delivery-key']
    };

    // ── Spaces to hire ──────────────────────────────────────────────────
    var fact = function (icon, text, name) {
        return kit.tag('li', 'd-flex align-items-center gap-2', name, [
            kit.icon(icon, 'text-primary flex-shrink-0'),
            kit.h('span', text)
        ]);
    };
    var amenity = function (text) {
        return kit.tag('li', '', text, [
            kit.h('span', text, 'badge rounded-pill text-wrap bg-body-secondary text-body-emphasis fw-normal')
        ]);
    };
    var space = function (n, seed, name, text, guests, area, layout, amenities, price) {
        return kit.col(_sdT('Space {var}', n), [
            kit.div('card h-100 border-0 shadow-sm overflow-hidden', _sdT('Space Card'), [
                kit.img(seed, 800, 450, name, 'card-img-top object-fit-cover pg-ratio-16x9'),
                kit.div('card-body d-flex flex-column p-4', _sdT('Card Body'), [
                    kit.h('h3', name, 'h5 fw-bold mb-2'),
                    kit.p(text, 'small text-body-secondary mb-3'),
                    kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-3 small fw-semibold mb-3 pg-space-facts', _sdT('Space Facts'), [
                        fact('bi-people', guests, _sdT('Capacity')),
                        fact('bi-arrows-fullscreen', area, _sdT('Floor Area')),
                        fact('bi-grid-3x2-gap', layout, _sdT('Layout'))
                    ]),
                    kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-4', _sdT('Amenities'), amenities.map(amenity)),
                    kit.div('d-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto pt-3 border-top', _sdT('Card Foot'), [
                        kit.div('', _sdT('Price'), [
                            kit.h('span', _sdT('From'), 'small text-body-secondary me-1'),
                            kit.h('span', price, 'fw-bold'),
                            kit.h('span', _sdT('/day'), 'small text-body-secondary')
                        ]),
                        kit.link(_sdT('Ask for a date'), '#', 'btn btn-sm btn-outline-primary', _sdT('Enquiry Link'))
                    ])
                ])
            ])
        ], '4', '6');
    };
    var buildSpaces = function () {
        var row = kit.row(_sdT('Spaces Row'), [
            space(1, 'space-hall', _sdT('Garden Hall'), _sdT('A bright hall opening onto the garden, for celebrations and dinners.'),
                _sdT('Up to {var} guests', '120'), _sdT('{var} m²', '180'), _sdT('Banquet or theatre'),
                [_sdT('Daylight'), _sdT('Sound system'), _sdT('Step-free access')], _sdT('$900')),
            space(2, 'space-library', _sdT('Library Room'), _sdT('A quiet room lined with books, for board meetings and workshops.'),
                _sdT('Up to {var} guests', '24'), _sdT('{var} m²', '45'), _sdT('Boardroom'),
                [_sdT('Screen and projector'), _sdT('Fast Wi-Fi'), _sdT('Coffee service')], _sdT('$350')),
            space(3, 'space-terrace', _sdT('Rooftop Terrace'), _sdT('An open terrace over the rooftops, for receptions in the warm months.'),
                _sdT('Up to {var} guests', '80'), _sdT('{var} m²', '140'), _sdT('Standing reception'),
                [_sdT('City view'), _sdT('Bar counter'), _sdT('Heaters and awning')], _sdT('$650'))
        ], 'justify-content-center');
        row.props._fx = { effect: 'slide-up', trigger: 'view', stagger: 120 };
        return kit.section('py-5 bg-body-tertiary', _sdT('Locations (Spaces to Hire)'), [
            kit.intro(_sdT('Spaces to hire'), _sdT('Three rooms for meetings, celebrations and workshops, each with its own entrance.')),
            row,
            kit.p(_sdT('Catering, staff and equipment can be booked with any space.'), 'small text-body-secondary text-center mt-4 mb-0')
        ]);
    };

    // ── Floor directory ─────────────────────────────────────────────────
    // A floor: its level in a square, its name and line, what is on it as
    // pills, and who may go up (an icon and the words).
    var floor = function (n, level, name, text, rooms, access) {
        return kit.tag('li', 'd-flex gap-4 py-4 border-bottom', _sdT('Floor {var}', n), [
            kit.h('span', level, 'pg-floor-level rounded-3 bg-primary-subtle text-primary-emphasis fs-4 fw-bold'),
            kit.div('flex-grow-1', _sdT('Floor Text'), [
                kit.h('h3', name, 'h5 fw-bold mb-1'),
                kit.p(text, 'text-body-secondary mb-3'),
                kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-3', _sdT('On This Floor'), rooms.map(amenity)),
                kit.div('d-flex align-items-center gap-2 small fw-semibold', _sdT('Access'), [
                    kit.icon('bi-key', 'text-primary'),
                    kit.h('span', access)
                ])
            ])
        ]);
    };
    var buildFloors = function () {
        var list = kit.tag('ol', 'list-unstyled mb-0 border-top', _sdT('Floors'), [
            floor(1, _sdT('Roof'), _sdT('Roof terrace'), _sdT('Tables in the open air and a view over the old harbour.'),
                [_sdT('Terrace'), _sdT('Barbecue'), _sdT('Plants')], _sdT('Members, 8 am to 8 pm')),
            floor(2, '3', _sdT('Private offices'), _sdT('Twelve lockable offices for two to ten people.'),
                [_sdT('Offices'), _sdT('Kitchenette'), _sdT('Lockers')], _sdT('Office teams, around the clock')),
            floor(3, '2', _sdT('Quiet floor'), _sdT('Fixed desks and focus booths, no calls at the desks.'),
                [_sdT('Fixed desks'), _sdT('Phone booths'), _sdT('Library')], _sdT('Members, around the clock')),
            floor(4, '1', _sdT('Meeting rooms'), _sdT('Five rooms from four to twenty seats, booked by the hour.'),
                [_sdT('Meeting rooms'), _sdT('Event hall'), _sdT('Screens')], _sdT('Members and their guests')),
            floor(5, '0', _sdT('Ground floor'), _sdT('The reception, the coffee bar and the hot desks.'),
                [_sdT('Reception'), _sdT('Coffee bar'), _sdT('Hot desks')], _sdT('Everyone, 7 am to 10 pm'))
        ]);
        list.props._fx = { effect: 'slide-up', trigger: 'view', stagger: 100 };
        return kit.section('py-5', _sdT('Locations (Floor Directory)'), [
            kit.intro(_sdT('Floor by floor'), _sdT('Where everything is, and who can go up.')),
            kit.div('col-lg-10 mx-auto', _sdT('Directory'), [ list ])
        ]);
    };

    // ── Delivery zones ──────────────────────────────────────────────────
    // The colours of a zone's ring and of its key in the list, from the
    // shop outwards; the outer zone is drawn with a dashed line.
    var rings = {
        1: 'text-bg-primary',
        2: 'bg-primary-subtle border border-2 border-primary-subtle',
        3: 'pg-delivery-z3 border border-2 border-primary'
    };
    // A zone: its key (hidden from a screen reader, which reads the name),
    // its name and fee, the places it covers and when an order arrives.
    var zone = function (n, name, text, when, today, fee) {
        return kit.tag('li', 'd-flex gap-3 py-3 border-bottom', _sdT('Zone {var}', n), [
            kit.div('pg-delivery-key ' + rings[n], _sdT('Zone Mark'), [], [{ name: 'aria-hidden', value: 'true' }]),
            kit.div('flex-grow-1', _sdT('Zone Text'), [
                kit.div('d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-1', _sdT('Title'), [
                    kit.h('h3', name, 'h5 fw-bold mb-0'),
                    kit.div('', _sdT('Delivery fee'), [
                        kit.h('span', _sdT('Delivery fee'), 'small text-body-secondary me-1'),
                        kit.h('span', fee, 'fw-bold')
                    ])
                ]),
                kit.p(text, 'small text-body-secondary mb-2'),
                kit.h('span', when, 'badge rounded-pill text-wrap ' + (today ? 'text-bg-primary' : 'bg-body-secondary text-body-emphasis'))
            ])
        ]);
    };
    // A delivery time: its name and the hours it runs.
    var slot = function (icon, name, from, to) {
        return kit.tag('li', 'col', name, [
            kit.div('h-100 p-3 rounded-3 border text-center', _sdT('Delivery Time'), [
                kit.icon(icon, 'd-block text-primary fs-4 mb-1'),
                kit.h('span', name, 'd-block fw-semibold'),
                kit.div('small text-body-secondary', _sdT('Hours'), [
                    kit.time(from, from, '', _sdT('Start Time')),
                    kit.h('span', '–', 'mx-1'),
                    kit.time(to, to, '', _sdT('End Time'))
                ])
            ])
        ]);
    };
    var buildDelivery = function () {
        var map = kit.div('pg-delivery-map', _sdT('Zone Drawing'), [
            kit.div('pg-delivery-ring ' + rings[3], _sdT('Zone {var}', 3), [
                kit.div('pg-delivery-ring ' + rings[2], _sdT('Zone {var}', 2), [
                    kit.div('pg-delivery-ring pg-delivery-z1 ' + rings[1], _sdT('Zone {var}', 1), [
                        kit.icon('bi-shop', 'fs-3')
                    ])
                ])
            ])
        ], [{ name: 'aria-hidden', value: 'true' }]);
        var row = kit.row(_sdT('Delivery Row'), [
            kit.col(_sdT('Zone Drawing'), [ map ], '5'),
            kit.col(_sdT('Zones'), [
                kit.tag('ul', 'pg-delivery-zones list-unstyled mb-4 border-top', _sdT('Zones'), [
                    zone(1, _sdT('Town Centre'), _sdT('The streets within three kilometres of the shop.'), _sdT('Same day'), true, _sdT('$5')),
                    zone(2, _sdT('Rest of the City'), _sdT('Every neighbourhood inside the ring road.'), _sdT('Same day'), true, _sdT('$9')),
                    zone(3, _sdT('Nearby Towns'), _sdT('Towns within an hour of the shop.'), _sdT('Next day'), false, _sdT('$15'))
                ]),
                kit.h('h3', _sdT('Delivery times'), 'h6 fw-semibold text-uppercase text-body-secondary mb-3'),
                kit.tag('ul', 'pg-delivery-zones list-unstyled row row-cols-1 row-cols-sm-3 g-3 mb-4', _sdT('Delivery Times'), [
                    slot('bi-sunrise', _sdT('Morning'), '09:00', '12:00'),
                    slot('bi-sun', _sdT('Afternoon'), '12:00', '17:00'),
                    slot('bi-sunset', _sdT('Evening'), '17:00', '20:00')
                ]),
                kit.div('pg-delivery-zones d-flex align-items-center gap-3 p-3 rounded-3 bg-primary-subtle text-primary-emphasis', _sdT('Last Order Time'), [
                    kit.icon('bi-clock-history', 'fs-3'),
                    kit.div('', _sdT('Text'), [
                        kit.div('d-flex flex-wrap align-items-baseline gap-2', _sdT('Title'), [
                            kit.h('span', _sdT('Same-day orders until'), 'fw-semibold'),
                            kit.time('14:00', '14:00', 'fs-5 fw-bold', _sdT('Last Order Time'))
                        ]),
                        kit.p(_sdT('Orders after this time go out the next morning.'), 'small mb-0')
                    ])
                ])
            ], '7')
        ], 'align-items-center g-4 g-lg-5');
        row.props._fx = { effect: 'slide-up', trigger: 'view', stagger: 150 };
        return kit.section('py-5', _sdT('Locations (Delivery Zones)'), [
            kit.intro(_sdT('Where we deliver'), _sdT('Three zones around the shop: the nearer you are, the sooner it comes and the less it costs.')),
            row
        ]);
    };

    return {
        prefix: 'locations-',
        items: [
            { label: _sdT('Locations: Spaces to Hire'), type: 'component', icon: 'bi-door-open', extra: { componentType: 'locations-spaces' } },
            { label: _sdT('Locations: Floor Directory'), type: 'component', icon: 'bi-building', extra: { componentType: 'locations-floors' } },
            { label: _sdT('Locations: Delivery Zones'), type: 'component', icon: 'bi-truck', extra: { componentType: 'locations-delivery' } }
        ],
        build: function (ctype) {
            if (ctype === 'locations-spaces') return buildSpaces();
            if (ctype === 'locations-floors') return buildFloors();
            if (ctype === 'locations-delivery') return buildDelivery();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
