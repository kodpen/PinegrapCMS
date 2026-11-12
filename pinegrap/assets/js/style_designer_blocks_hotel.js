/**
 * Pinegrap - Enterprise Website Platform
 *
 * Hotel block family: the sections a hotel's, a guest house's or an inn's
 * site needs that the general palette lacks. The room showcase (one room
 * as a guest weighs it before asking: its pictures, the view, who it
 * sleeps, its size and beds, what is in it and the rate from a night) and
 * the stay clock (arrival and departure as times on one line, what an
 * early arrival or a late departure can ask for, and what runs during the
 * stay). The kinds of room, the board plans, the offers, the restaurant,
 * the spa treatments, the spaces to hire, the facilities, the guests'
 * words and the questions are general blocks (Services, Pricing, Hours &
 * Locations, Social Proof, FAQ).
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
        // The room's pictures: one large on the left, two stacked beside
        // it from md up; one under the other below.
        'pg-hotel-gallery': '.pg-hotel-gallery { display: grid; gap: .75rem; grid-template-columns: minmax(0, 1fr); margin: 0; } ' +
                            '.pg-hotel-gallery img { width: 100%; height: 100%; object-fit: cover; border-radius: var(--bs-border-radius-lg); background: var(--bs-tertiary-bg); } ' +
                            '.pg-hotel-gallery .pg-hotel-shot { aspect-ratio: 4 / 3; } ' +
                            '@media (min-width: 768px) { .pg-hotel-gallery { grid-template-columns: 2fr 1fr; grid-template-rows: 1fr 1fr; } .pg-hotel-gallery .pg-hotel-main { grid-row: span 2; aspect-ratio: auto; } }',
        // The room's facts: an icon, the label and the value on a hairline.
        'pg-hotel-facts':   '.pg-hotel-facts { display: grid; gap: 0 1.5rem; margin: 0; } ' +
                            '.pg-hotel-facts > div { display: flex; align-items: baseline; gap: .5rem; padding: .5rem 0; border-bottom: 1px solid var(--bs-border-color); } ' +
                            '.pg-hotel-facts dt { font-weight: 600; color: var(--bs-secondary-color); min-width: 6.5rem; } ' +
                            '.pg-hotel-facts dd { margin: 0; } ' +
                            '@media (min-width: 576px) { .pg-hotel-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); } }',
        // A rate and a stay's times in tabular figures, so they line up.
        'pg-hotel-figure':  '.pg-hotel-figure { font-variant-numeric: tabular-nums; color: var(--bs-emphasis-color); line-height: 1; }',
        // The stay clock: arrival and departure joined by a dashed line
        // from lg up, the stay between them.
        'pg-hotel-clock':   '.pg-hotel-clock { position: relative; } ' +
                            '.pg-hotel-clock .pg-hotel-end { position: relative; z-index: 1; border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); background: var(--bs-body-bg); } ' +
                            '@media (min-width: 992px) { .pg-hotel-clock::before { content: ""; position: absolute; left: 12%; right: 12%; top: 4.25rem; border-top: 2px dashed var(--bs-primary-border-subtle); } }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'hotel-room': ['pg-hotel-gallery', 'pg-hotel-facts', 'pg-hotel-figure'],
        'hotel-stay': ['pg-hotel-clock', 'pg-hotel-figure']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };

    // The small spaced line above a title.
    var eyebrow = function (text, cls) {
        return kit.p(text, 'small text-uppercase fw-semibold text-primary ' + (cls || 'mb-2'));
    };

    // An icon and a line: an amenity, a note of the stay.
    var line = function (icon, text) {
        return kit.tag('li', 'd-flex gap-2 mb-2', text, [
            kit.icon(icon, 'text-primary flex-shrink-0'),
            kit.h('span', text)
        ]);
    };

    // ── Room showcase ───────────────────────────────────────────────────
    // A picture of the room in the gallery grid.
    var shot = function (cls, seed, alt) {
        return kit.div('pg-hotel-shot ' + cls, _sdT('Picture'), [
            kit.img(seed, 1200, 900, alt, '')
        ]);
    };
    // A fact of the room: an icon, its label and its value.
    var fact = function (icon, label, value) {
        return kit.div('', label, [
            kit.h('dt', label, 'd-flex align-items-center gap-2'),
            kit.h('dd', value)
        ]);
    };
    var buildRoom = function () {
        var view = kit.div('badge rounded-pill text-bg-primary d-inline-flex align-items-center gap-1 position-absolute top-0 start-0 m-3', _sdT('View'), [
            kit.icon('bi-water'),
            kit.h('span', _sdT('Sea view'))
        ]);
        var gallery = kit.tag('figure', 'pg-hotel-gallery position-relative', _sdT('Pictures'), [
            shot('pg-hotel-main', 'hotel-suite', _sdT('A suite with a free-standing bath facing the window and the sea')),
            shot('', 'hotel-terrace', _sdT('Breakfast laid on a private terrace over the bay')),
            shot('', 'hotel-bed', _sdT('A king-size bed made with linen sheets')),
            view
        ]);
        var rate = kit.div('d-flex align-items-baseline gap-2 mb-4', _sdT('Price'), [
            kit.h('span', _sdT('Starting from'), 'small text-body-secondary'),
            kit.h('span', _sdT('$240'), 'pg-hotel-figure fs-2 fw-bold'),
            kit.h('span', _sdT('/ night'), 'text-body-secondary')
        ]);
        var words = kit.col(_sdT('Room'), [
            eyebrow(_sdT('Suites')),
            kit.h('h2', _sdT('Junior Suite'), 'h2 fw-bold mb-3'),
            kit.p(_sdT('A large room with a sitting corner, a terrace for breakfast outside and a bath with a view.'), 'lead text-body-secondary mb-3'),
            rate,
            fx(kit.tag('dl', 'pg-hotel-facts border-top mb-4', _sdT('Facts'), [
                fact('bi-people', _sdT('Guests'), _sdT('Up to 3')),
                fact('bi-arrows-angle-expand', _sdT('Floor Area'), _sdT('38 m²')),
                fact('bi-moon', _sdT('Beds'), _sdT('One king-size bed and a sofa bed')),
                fact('bi-water', _sdT('Room view'), _sdT('Sea view'))
            ]), { effect: 'slide-up', trigger: 'view', stagger: 80 }),
            kit.h('h3', _sdT('In the room'), 'h5 fw-bold mb-3'),
            kit.tag('ul', 'list-unstyled row row-cols-1 row-cols-sm-2 g-0 mb-4', _sdT('Amenities'), [
                line('bi-check2', _sdT('Private terrace')),
                line('bi-check2', _sdT('Free-standing bath')),
                line('bi-check2', _sdT('Sofa bed for a third guest')),
                line('bi-check2', _sdT('Espresso machine'))
            ]),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Ask for This Room'), '#', 'btn btn-primary px-4', _sdT('Request Link')),
                kit.link(_sdT('All Rooms'), '#', 'btn btn-outline-secondary px-4', _sdT('Rooms Link'))
            ])
        ], '5');
        return kit.section('py-5', _sdT('Hotel (Room Showcase)'), [
            kit.row(_sdT('Room Row'), [
                kit.col(_sdT('Gallery'), [
                    fx(gallery, { effect: 'fade', trigger: 'view', duration: 900 })
                ], '7'),
                words
            ], 'g-4 g-lg-5 align-items-center')
        ]);
    };

    // ── Stay clock ──────────────────────────────────────────────────────
    // One end of the stay: its icon, its name, the time and what a guest
    // can ask for around it.
    var end = function (icon, title, lead, time, notes) {
        return kit.col(title, [
            kit.div('pg-hotel-end h-100 p-4 text-center', title, [
                kit.icon(icon, 'fs-3 text-primary d-block mb-2'),
                kit.h('h3', title, 'h5 fw-bold mb-1'),
                kit.p(lead, 'small text-body-secondary mb-2'),
                kit.time(time, time, 'pg-hotel-figure d-block display-5 fw-bold mb-3', _sdT('Time')),
                kit.tag('ul', 'list-unstyled small text-start mb-0', _sdT('Notes'), notes)
            ])
        ], '4', '6');
    };
    var buildStay = function () {
        var during = kit.col(_sdT('During Your Stay'), [
            kit.div('pg-hotel-end h-100 p-4 text-center', _sdT('During Your Stay'), [
                kit.icon('bi-moon-stars', 'fs-3 text-primary d-block mb-2'),
                kit.h('h3', _sdT('During your stay'), 'h5 fw-bold mb-1'),
                kit.p(_sdT('Reception is open day and night'), 'small text-body-secondary mb-3'),
                kit.tag('ul', 'list-unstyled small text-start mb-0', _sdT('Notes'), [
                    line('bi-cup-hot', _sdT('Breakfast from 7:30 to 10:30')),
                    line('bi-water', _sdT('Pool, garden and beach towels')),
                    line('bi-wifi', _sdT('Fast Wi-Fi')),
                    line('bi-p-circle', _sdT('Free Parking'))
                ])
            ])
        ], '4', '12');
        return kit.section('py-5 bg-body-tertiary', _sdT('Hotel (Stay Clock)'), [
            fx(kit.div('text-center col-lg-8 mx-auto mb-5', _sdT('Section Header'), [
                eyebrow(_sdT('Your stay')),
                kit.h('h2', _sdT('From arrival to departure'), 'h2 fw-bold mb-2'),
                kit.p(_sdT('Tell us when you arrive with your request; we keep the room ready and your luggage safe.'), 'lead text-body-secondary mb-0')
            ]), { effect: 'fade', trigger: 'view', duration: 700 }),
            fx(kit.row(_sdT('Stay Row'), [
                end('bi-door-open', _sdT('Arrival'), _sdT('Your room is ready from'), '15:00', [
                    line('bi-suitcase', _sdT('Arriving early? We keep your luggage and the pool is yours.')),
                    line('bi-car-front', _sdT('A driver at the airport on the day you arrive, if you ask for it with your booking.'))
                ]),
                during,
                end('bi-box-arrow-right', _sdT('Departure'), _sdT('Please leave the room by'), '11:00', [
                    line('bi-clock-history', _sdT('Late check-out until 14:00 when the room is free; ask at the reception.')),
                    line('bi-bag', _sdT('Leaving later? Your bags wait at the reception.'))
                ])
            ], 'pg-hotel-clock g-4 justify-content-center'), { effect: 'slide-up', trigger: 'view', stagger: 120 }),
            kit.div('d-flex flex-wrap align-items-center justify-content-center gap-2 gap-sm-3 mt-4', _sdT('Reception'), [
                kit.icon('bi-telephone', 'fs-5 text-primary flex-shrink-0'),
                kit.h('span', _sdT('Reception'), 'fw-semibold'),
                kit.link('+90 555 000 00 00', 'tel:+905550000000', 'fw-semibold link-primary', _sdT('Phone Link'))
            ])
        ]);
    };

    return {
        prefix: 'hotel-',
        group: { title: _sdT('Hotel'), icon: 'bi-buildings' },
        items: [
            { label: _sdT('Hotel: Room Showcase'), type: 'component', icon: 'bi-door-open', extra: { componentType: 'hotel-room' },
              desc: _sdT('A gallery of room photos with a view badge beside the room name, price a night, its facts, amenities and two buttons.') },
            { label: _sdT('Hotel: Stay Clock'),    type: 'component', icon: 'bi-clock',     extra: { componentType: 'hotel-stay' },
              desc: _sdT('Arrival and departure times joined by a dashed line around what the stay offers, with notes for each and the reception phone.') }
        ],
        build: function (ctype) {
            if (ctype === 'hotel-room') return buildRoom();
            if (ctype === 'hotel-stay') return buildStay();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
