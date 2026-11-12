/**
 * Pinegrap - Enterprise Website Platform
 *
 * Restaurant block family (prefix restaurant-): the sections of a
 * restaurant's site the palette lacks. The evening hero (the restaurant's
 * line in very large type, a booking and a menu button and tonight's slip:
 * the dinner sitting's first and last table, when the kitchen closes and
 * the seats kept for guests who walk in; beside them one dish on its plate,
 * seen from above, running past the band's edge), the tasting menu card
 * (one menu set out as the printed card a table is handed: its courses in
 * the order they come, the wine poured with each, the price per guest and
 * of the pairing, between two pictures that drift as the page scrolls) and
 * the chef's counter (the seats at the kitchen counter drawn in front of
 * the kitchen's stations, the evening there hour by hour, its price and a
 * booking button). The menu, the specials, the diet key, the wine list,
 * the private rooms, the events and the team are general blocks (Menu,
 * Locations, Events, Team). Motion comes from the nodes' interactions
 * (props._fx, played by pg_fx.src.js, which honours reduced motion).
 *
 * Registered with StyleDesigner.registerBlockFamily() and bundled after the
 * designer core (pg_designer_js_sources()); see
 * docs/memory/designer/designer-blocks-restaurant.md.
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
        // The dish on its plate: a round photo inside a rim of the palette's
        // second surface, a hairline edge and the large shadow. From lg up
        // the plate is wider than its column and runs past the band's right
        // edge, which the band clips. The plate keeps its round shape while
        // the photo is still loading or missing.
        'pg-restaurant-plate':   '.pg-restaurant-plate { width: min(100%, 30rem); aspect-ratio: 1 / 1; margin-inline: auto; padding: clamp(.75rem, 2.5vw, 1.5rem); border-radius: 50%; ' +
                                 'background-color: var(--bs-secondary-bg); box-shadow: 0 0 0 1px var(--bs-border-color), var(--bs-box-shadow-lg); } ' +
                                 '.pg-restaurant-plate img { display: block; width: 100%; aspect-ratio: 1 / 1; object-fit: cover; border-radius: 50%; } ' +
                                 '@media (min-width: 992px) { .pg-restaurant-plate { width: 115%; margin-inline: 0; } }',
        // Tonight's slip: a dashed tear line under its title, its times in
        // figures that line up.
        'pg-restaurant-slip':    '.pg-restaurant-slip { max-width: 30rem; } ' +
                                 '.pg-restaurant-slip .pg-restaurant-tear { border-bottom: 1px dashed var(--bs-border-color); } ' +
                                 '.pg-restaurant-slip time { font-variant-numeric: tabular-nums; }',
        // The tasting menu card: a second frame drawn inside its edge, as on
        // a printed menu; its courses centred, each after a short rule in
        // the theme's colour; the prices in figures that line up.
        'pg-restaurant-menu':    '.pg-restaurant-menu { outline: 1px solid var(--bs-border-color); outline-offset: -.75rem; } ' +
                                 '.pg-restaurant-menu .pg-restaurant-courses { margin: 0; padding: 0; list-style: none; } ' +
                                 '.pg-restaurant-menu .pg-restaurant-courses li::before { content: ""; display: block; width: 2.5rem; margin: 1.25rem auto; border-top: 1px solid var(--bs-primary); } ' +
                                 '.pg-restaurant-menu .pg-restaurant-price { font-variant-numeric: tabular-nums; }',
        // The chef's counter: the kitchen's stations on a band warmed by the
        // theme's colour, the counter as a thick bar in that colour, the
        // seats as numbered rounds in a row under it. A seat's size follows
        // the window, so the row fits a phone.
        'pg-restaurant-counter': '.pg-restaurant-counter .pg-restaurant-kitchen { padding: 1.5rem 1rem; border-radius: var(--bs-border-radius-xl) var(--bs-border-radius-xl) 0 0; ' +
                                 'background-color: var(--bs-secondary-bg); } ' +
                                 '@supports (background: color-mix(in srgb, red 50%, blue)) { .pg-restaurant-counter .pg-restaurant-kitchen { ' +
                                 'background: radial-gradient(ellipse 75% 130% at 50% 0%, color-mix(in srgb, var(--bs-primary) 35%, var(--bs-secondary-bg)), var(--bs-secondary-bg) 75%); } } ' +
                                 '.pg-restaurant-counter .pg-restaurant-bar { height: 1.25rem; border-radius: 0 0 var(--bs-border-radius-lg) var(--bs-border-radius-lg); background-color: var(--bs-primary); } ' +
                                 '.pg-restaurant-counter .pg-restaurant-seats { display: flex; justify-content: center; gap: clamp(.5rem, 3vw, 1.75rem); margin-top: 1rem; } ' +
                                 '.pg-restaurant-counter .pg-restaurant-seat { display: flex; align-items: center; justify-content: center; width: clamp(2rem, 9vw, 3.25rem); aspect-ratio: 1 / 1; ' +
                                 'border: 2px solid var(--bs-primary); border-radius: 50%; font-weight: 700; font-variant-numeric: tabular-nums; ' +
                                 'color: var(--bs-emphasis-color); background-color: var(--bs-body-bg); }',
        // The evening at the counter: each moment's time in a narrow column
        // beside what happens, on hairlines under a rule in the theme's
        // colour. The times are in the body's strongest colour: a dark
        // palette's primary, as Burgundy's, is under 4.5:1 for small text on
        // a dark page.
        'pg-restaurant-evening': '.pg-restaurant-evening { margin: 0; padding: 0; list-style: none; border-top: 2px solid var(--bs-primary); } ' +
                                 '.pg-restaurant-evening li { display: grid; grid-template-columns: 3.5rem minmax(0, 1fr); gap: 1rem; align-items: baseline; padding: .75rem 0; ' +
                                 'border-bottom: 1px solid var(--bs-border-color); } ' +
                                 '.pg-restaurant-evening time { font-weight: 600; font-variant-numeric: tabular-nums; color: var(--bs-emphasis-color); }',
        // The secondary button's focus ring, in the body's strongest colour:
        // a button without a colour variant has no ring colour of its own,
        // so the keyboard focus would not show.
        'pg-restaurant-second':  '.pg-restaurant-second { --bs-btn-focus-shadow-rgb: var(--bs-emphasis-color-rgb); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'restaurant-hero':    ['pg-display-xl', 'pg-restaurant-plate', 'pg-restaurant-slip', 'pg-restaurant-second'],
        'restaurant-tasting': ['pg-restaurant-menu', 'pg-restaurant-second'],
        'restaurant-counter': ['pg-restaurant-counter', 'pg-restaurant-evening', 'pg-restaurant-second']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };
    // A dark band: the palette's dark colours apply inside it.
    var dark = [{ name: 'data-bs-theme', value: 'dark' }];

    // The small spaced line above a title, in the body's second colour:
    // small text in the theme's colour, even its darker shade, is 4.47:1 on a
    // dark page with the template's Burgundy palette. The icons beside the
    // slip's lines keep the theme's colour, as a graphic needs 3:1.
    var eyebrow = function (text, cls) {
        return kit.p(text, 'small text-uppercase fw-semibold text-body-secondary ' + (cls || 'mb-2'));
    };
    // A secondary button: the body's text colour and a border, readable in
    // both modes (an outline button in the theme's colour is not), with its
    // own focus ring (pg-restaurant-second).
    var second = function (text, name, size) {
        return kit.link(text, '#', 'btn ' + (size ? size + ' ' : '') + 'border px-4 link-body-emphasis pg-restaurant-second', name);
    };
    // A column that takes half a phone's width and a quarter from lg up.
    var quarter = function (name, kids, cls) {
        return kit.createNode('col', { xs: '6', sm: '', md: '', lg: '3', xl: '', xxl: '', offsetXs: '', offsetMd: '', order: '', cssClass: cls, customName: name }, kids);
    };

    // ── Evening hero ────────────────────────────────────────────────────
    // A line of tonight's slip: an icon, what it is and a note under it,
    // and on the right its time or times.
    var slipLine = function (icon, title, note, times, last) {
        return kit.tag('li', 'd-flex align-items-center gap-3 py-3' + (last ? '' : ' border-bottom'), title, [
            kit.icon(icon, 'fs-5 text-primary-emphasis flex-shrink-0'),
            kit.div('flex-grow-1', _sdT('Text'), [
                kit.p(title, 'fw-semibold text-body-emphasis mb-0'),
                kit.p(note, 'small text-body-secondary mb-0')
            ]),
            kit.tag('p', 'fs-5 fw-semibold text-body-emphasis text-nowrap mb-0', _sdT('Hours'), times)
        ]);
    };
    var buildHero = function () {
        var slip = fx(kit.div('pg-restaurant-slip mt-5 rounded-4 border bg-body-tertiary', _sdT('This evening'), [
            kit.div('pg-restaurant-tear d-flex align-items-center gap-2 px-4 py-3', _sdT('Title'), [
                kit.icon('bi-moon-stars', 'text-primary-emphasis'),
                kit.h('h2', _sdT('This evening'), 'h6 text-uppercase fw-bold mb-0')
            ]),
            kit.tag('ul', 'list-unstyled px-4 mb-0', _sdT('Details'), [
                slipLine('bi-door-open', _sdT('Dinner'), _sdT('First and last table'), [
                    kit.time('18:30', '18:30', '', _sdT('Time')),
                    kit.h('span', '–', 'mx-1'),
                    kit.time('21:30', '21:30', '', _sdT('Time'))
                ], false),
                slipLine('bi-fire', _sdT('Kitchen'), _sdT('Last plates leave the pass'), [
                    kit.time('23:30', '23:30', '', _sdT('Time'))
                ], false),
                kit.tag('li', 'd-flex align-items-center gap-3 py-3', _sdT('Walk-ins'), [
                    kit.icon('bi-person-walking', 'fs-5 text-primary-emphasis flex-shrink-0'),
                    kit.p(_sdT('Two seats at the counter are kept for guests who walk in.'), 'small text-body-secondary mb-0')
                ])
            ])
        ]), { effect: 'fade', trigger: 'load', duration: 800, delay: 600 });
        var words = fx(kit.div('', _sdT('Hero Text'), [
            eyebrow(_sdT('Neighbourhood restaurant'), 'mb-3'),
            kit.h('h1', _sdT('Cooked with the seasons'), 'pg-display-xl mb-4'),
            kit.p(_sdT('What the farms bring in the morning is on the table at night, cooked over a wood fire.'), 'lead text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Reserve a Table'), '#', 'btn btn-primary btn-lg px-4', _sdT('Booking Link')),
                second(_sdT('See the menu'), _sdT('Menu'), 'btn-lg')
            ]),
            slip
        ]), { effect: 'slide-up', trigger: 'load', duration: 800 });
        var dish = fx(kit.tag('figure', 'm-0', _sdT('Signature Dish'), [
            kit.div('pg-restaurant-plate', _sdT('Dinner Plate'), [
                kit.img('restaurant-plate', 1000, 1000, _sdT('Slow-roasted lamb shoulder with smoked aubergine on a stoneware plate, seen from above'), '')
            ]),
            kit.tag('figcaption', 'mt-4 text-center text-lg-start', _sdT('Caption'), [
                kit.p(_sdT('Slow-Roasted Lamb Shoulder'), 'fw-semibold text-body-emphasis mb-1'),
                kit.p(_sdT('Seven hours in the wood oven, smoked aubergine, yoghurt and mint.'), 'small text-body-secondary mb-0')
            ])
        ]), { effect: 'scale', trigger: 'load', duration: 1100, delay: 150 });
        return kit.tag('section', 'py-5 bg-body text-body overflow-hidden', _sdT('Restaurant (Evening Hero)'), [
            kit.createNode('container', { fluid: false, cssClass: 'py-lg-5' }, [
                kit.row(_sdT('Hero Row'), [
                    kit.col(_sdT('Hero Text'), [words], '6'),
                    kit.col(_sdT('Signature Dish'), [dish], '6')
                ], 'g-lg-5 align-items-center')
            ])
        ], dark);
    };

    // ── Tasting menu card ───────────────────────────────────────────────
    // A course: its name and, in italics under it, the wine poured with it.
    var course = function (name, wine) {
        return kit.tag('li', '', name, [
            kit.h('h3', name, 'h5 fw-semibold mb-1'),
            kit.p(wine, 'small fst-italic text-body-secondary mb-0')
        ]);
    };
    // A price at the card's foot: what it is for, the amount, per guest.
    var price = function (label, amount) {
        return kit.div('', label, [
            kit.p(label, 'small text-uppercase fw-semibold text-body-secondary mb-1'),
            kit.tag('p', 'mb-0', _sdT('Price'), [
                kit.h('span', amount, 'pg-restaurant-price fs-2 fw-semibold text-body-emphasis'),
                kit.h('span', _sdT('per guest'), 'small text-body-secondary ms-1')
            ])
        ]);
    };
    // A picture beside the card that drifts as the page scrolls.
    var side = function (seed, alt) {
        return fx(kit.img(seed, 600, 800, alt, 'img-fluid w-100 rounded-4 shadow-sm'), { effect: 'parallax', speed: 12 });
    };
    var buildTasting = function () {
        var card = fx(kit.div('pg-restaurant-menu bg-body border rounded-4 shadow-sm text-center px-4 py-5 p-lg-5', _sdT('Menu Card'), [
            eyebrow(_sdT('Tasting menu')),
            kit.h('h2', _sdT('Seasons Menu'), 'display-5 mb-3'),
            kit.p(_sdT('Seven courses that follow the week\'s market, served to the whole table.'), 'text-body-secondary mb-0'),
            fx(kit.tag('ol', 'pg-restaurant-courses', _sdT('Course List'), [
                course(_sdT('Oysters with Green Apple'), _sdT('Valley Brut')),
                course(_sdT('Sea Bass Crudo'), _sdT('Hillside White')),
                course(_sdT('Wild Mushroom Broth'), _sdT('Stone Terrace')),
                course(_sdT('Catch of the Day'), _sdT('Summer Garden')),
                course(_sdT('Slow-Roasted Lamb Shoulder'), _sdT('Old Vines Red')),
                course(_sdT('Cheese from the Valley'), _sdT('River Bend')),
                course(_sdT('Dark Chocolate Tart'), _sdT('Late Harvest'))
            ]), { effect: 'fade', trigger: 'view', duration: 700, delay: 200, stagger: 120 }),
            kit.div('d-flex flex-wrap justify-content-center gap-4 gap-lg-5 border-top pt-4 mt-5', _sdT('Prices'), [
                price(_sdT('Menu'), '$95'),
                price(_sdT('Wine pairing'), '$60')
            ]),
            kit.p(_sdT('Every guest at the table takes the same menu. Tell us about allergies when you book.'), 'small text-body-secondary mt-4 mb-4'),
            kit.div('d-flex flex-wrap justify-content-center gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Reserve with the menu chosen'), '#', 'btn btn-primary px-4', _sdT('Booking Link')),
                second(_sdT('Wine List'), _sdT('Wine List'), '')
            ])
        ]), { effect: 'slide-up', trigger: 'view', duration: 800 });
        return kit.section('py-5 bg-body-tertiary overflow-hidden', _sdT('Restaurant (Tasting Menu)'), [
            kit.row(_sdT('Row'), [
                quarter(_sdT('Picture'), [side('restaurant-crudo', _sdT('Sea bass crudo with citrus and green chilli on a stone plate'))], 'order-2 order-lg-1 pt-lg-5'),
                kit.col(_sdT('Menu Card'), [card], '6', '', '', 'order-1 order-lg-2'),
                quarter(_sdT('Picture'), [side('restaurant-pour', _sdT('The sommelier pouring a glass of red at the table'))], 'order-3 mt-lg-5 pt-lg-5')
            ], 'g-4 g-lg-5 align-items-start')
        ]);
    };

    // ── Chef's counter ──────────────────────────────────────────────────
    // A moment of the evening at the counter: when, and what happens.
    var moment = function (time, text) {
        return kit.tag('li', '', text, [
            kit.time(time, time, '', _sdT('Time')),
            kit.p(text, 'mb-0')
        ]);
    };
    // A station of the kitchen behind the counter, as a pill.
    var station = function (icon, text) {
        return kit.div('badge rounded-pill border bg-body text-body-emphasis text-wrap fw-semibold d-inline-flex align-items-center gap-2 px-3 py-2', text, [
            kit.icon(icon),
            kit.h('span', text)
        ]);
    };
    var buildCounter = function () {
        var seats = [];
        for (var n = 1; n <= 6; n++) seats.push(kit.h('span', String(n), 'pg-restaurant-seat'));
        var words = fx(kit.div('', _sdT('Text'), [
            eyebrow(_sdT('The Chef\'s Table')),
            kit.h('h2', _sdT('Six seats beside the fire'), 'display-6 fw-semibold mb-3'),
            kit.p(_sdT('One sitting a night at the kitchen counter: the chef cooks in front of you, talks you through every plate and pours a glass to go with it. Pick a free evening online.'), 'lead text-body-secondary mb-4'),
            kit.h('h3', _sdT('The evening'), 'h6 text-uppercase fw-bold mb-2'),
            kit.tag('ol', 'pg-restaurant-evening mb-4', _sdT('Evening'), [
                moment('19:00', _sdT('A glass at the counter while the fire settles')),
                moment('19:20', _sdT('The first plates, handed across by the chef')),
                moment('20:30', _sdT('The lamb carved in front of you')),
                moment('21:45', _sdT('Dessert and a sweet wine with the pastry chef'))
            ]),
            kit.div('d-flex flex-wrap align-items-baseline gap-1 mb-4', _sdT('Price'), [
                kit.h('span', _sdT('Starting from'), 'small text-body-secondary'),
                kit.h('span', '$95', 'fs-3 fw-bold text-body-emphasis'),
                kit.h('span', _sdT('per guest'), 'small text-body-secondary')
            ]),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Book the chef\'s table'), '#', 'btn btn-primary px-4', _sdT('Booking Link')),
                second(_sdT('See the tasting menus'), _sdT('Tasting Menus'), '')
            ])
        ]), { effect: 'slide-up', trigger: 'view', duration: 700 });
        var drawing = fx(kit.div('pg-restaurant-counter', _sdT('Drawing'), [
            kit.div('pg-restaurant-kitchen', _sdT('Kitchen'), [
                kit.p(_sdT('The kitchen'), 'small text-uppercase fw-semibold text-body-emphasis text-center mb-3'),
                kit.div('d-flex flex-wrap justify-content-center gap-2', _sdT('Stations'), [
                    station('bi-fire', _sdT('Wood oven')),
                    station('bi-grid-3x3', _sdT('Grill')),
                    station('bi-cake2', _sdT('Pastry')),
                    station('bi-bell', _sdT('The pass'))
                ])
            ]),
            kit.div('pg-restaurant-bar', _sdT('Counter'), []),
            fx(kit.div('pg-restaurant-seats', _sdT('Counter Seats'), seats),
                { effect: 'scale', trigger: 'view', duration: 500, delay: 300, stagger: 90 }),
            kit.p(_sdT('Every seat faces the fire.'), 'small text-body-secondary text-center mt-3 mb-0')
        ], [{ name: 'aria-hidden', value: 'true' }]), { effect: 'fade', trigger: 'view', duration: 900, delay: 150 });
        return kit.section('py-5 overflow-hidden', _sdT('Restaurant (Chef\'s Counter)'), [
            kit.row(_sdT('Row'), [
                kit.col(_sdT('Text'), [words], '5'),
                kit.col(_sdT('Drawing'), [drawing], '7')
            ], 'g-lg-5 align-items-center')
        ]);
    };

    return {
        prefix: 'restaurant-',
        group: { title: _sdT('Restaurant'), icon: 'bi-egg-fried' },
        items: [
            { label: _sdT('Restaurant: Evening Hero'),     type: 'component', icon: 'bi-moon-stars', extra: { componentType: 'restaurant-hero' },
              desc: _sdT('A dark band with one very large line, two buttons and the evening\'s sitting on a slip, beside a dish on its plate seen from above.') },
            { label: _sdT('Restaurant: Tasting Menu Card'), type: 'component', icon: 'bi-card-text',  extra: { componentType: 'restaurant-tasting' },
              desc: _sdT('A tasting menu set out as a printed card, each course with its wine, the price per guest and of the pairing, between two pictures.') },
            { label: _sdT('Restaurant: Chef\'s Counter'),   type: 'component', icon: 'bi-fire',       extra: { componentType: 'restaurant-counter' },
              desc: _sdT('The seats at the kitchen counter drawn in front of the stations, beside the evening hour by hour, a price and a booking button.') }
        ],
        build: function (ctype) {
            if (ctype === 'restaurant-hero') return buildHero();
            if (ctype === 'restaurant-tasting') return buildTasting();
            if (ctype === 'restaurant-counter') return buildCounter();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
