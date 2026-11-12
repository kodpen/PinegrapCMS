/**
 * Pinegrap - Enterprise Website Platform
 *
 * Marina block family: the sections a marina's site needs that the general
 * palette lacks. The pontoon plan (a card per pontoon with its berth
 * lengths, the largest boat it takes, the depth alongside and what each
 * berth has) and the arrival by sea (the radio call, the approach position,
 * the entrance and the steps from the breakwater to the reception
 * pontoon). Every value is sample text the staff edit in the designer: no
 * live berth availability, no tide or weather feed.
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
        'pg-marina-pontoon': '.pg-marina-pontoon { display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 50%; font-weight: 700; font-size: 1.25rem; line-height: 1; color: var(--bs-primary); border: 2px solid currentColor; }',
        'pg-marina-figures': '.pg-marina-figures { font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'marina-pontoons': ['pg-marina-pontoon', 'pg-marina-figures'],
        'marina-arrival':  ['pg-marina-figures']
    };

    // An entrance the operator can change or remove in the Interactions
    // section; the children rise one after another while the row scrolls
    // into view. pg_fx keeps them still under reduced motion.
    var rise = function (node, stagger) {
        node.props._fx = { effect: 'slide-up', trigger: 'view', stagger: stagger || 90 };
        return node;
    };

    // ── Pontoon plan ────────────────────────────────────────────────────
    var fact = function (label, value) {
        return kit.div('d-flex justify-content-between gap-3 py-2 border-top', label, [
            kit.h('dt', label, 'fw-normal text-body-secondary'),
            kit.h('dd', value, 'fw-semibold text-end mb-0')
        ]);
    };
    // What a berth on the pontoon has: an icon and its word each.
    var amenity = function (icon, text) {
        return kit.tag('li', 'd-inline-flex align-items-center gap-1 badge rounded-pill text-bg-secondary fw-normal', text, [
            kit.icon(icon),
            kit.h('span', text)
        ]);
    };
    var pontoon = function (n, letter, name, use, berths, length, boat, depth, amenities) {
        return kit.col(_sdT('Pontoon {var}', n), [
            kit.div('card h-100 border-0 shadow-sm', _sdT('Pontoon Card'), [
                kit.div('card-body d-flex flex-column p-4', _sdT('Card Body'), [
                    kit.div('d-flex align-items-center gap-3 mb-3', _sdT('Card Head'), [
                        kit.h('span', letter, 'pg-marina-pontoon flex-shrink-0'),
                        kit.div('', _sdT('Pontoon Name'), [
                            kit.h('h3', name, 'h5 fw-bold mb-0'),
                            kit.h('span', use, 'small text-body-secondary')
                        ])
                    ]),
                    kit.tag('dl', 'small mb-3', _sdT('Pontoon Facts'), [
                        fact(_sdT('Berths'), berths),
                        fact(_sdT('Berth length'), length),
                        fact(_sdT('Largest boat'), boat),
                        fact(_sdT('Depth alongside'), depth)
                    ]),
                    kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0 mt-auto', _sdT('On Every Berth'), amenities)
                ])
            ])
        ], '3', '6');
    };
    var buildPontoons = function () {
        return kit.section('py-5 bg-body-tertiary pg-marina-figures', _sdT('Marina (Pontoon Plan)'), [
            kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Section Header'), [
                kit.div('', _sdT('Section Title'), [
                    kit.h('h2', _sdT('Find your pontoon'), 'fw-bold mb-1'),
                    kit.p(_sdT('Four pontoons behind the breakwater, from small day boats to yachts of twenty metres.'), 'text-body-secondary mb-0')
                ]),
                kit.link(_sdT('Ask for a berth'), '#', 'btn btn-primary', _sdT('Berth Link'))
            ]),
            rise(kit.row(_sdT('Pontoons Row'), [
                pontoon(1, 'A', _sdT('Pontoon A'), _sdT('Visiting boats'), '40', _sdT('{var:1} – {var:2} m', ['10', '15']), _sdT('{var} m', '15'), _sdT('{var} m', '3.5'),
                    [amenity('bi-plug', _sdT('Shore power')), amenity('bi-droplet', _sdT('Water')), amenity('bi-wifi', _sdT('Wi-Fi'))]),
                pontoon(2, 'B', _sdT('Pontoon B'), _sdT('Seasonal and annual'), '60', _sdT('{var:1} – {var:2} m', ['8', '12']), _sdT('{var} m', '12'), _sdT('{var} m', '3'),
                    [amenity('bi-plug', _sdT('Shore power')), amenity('bi-droplet', _sdT('Water'))]),
                pontoon(3, 'C', _sdT('Pontoon C'), _sdT('Large yachts'), '18', _sdT('{var:1} – {var:2} m', ['15', '20']), _sdT('{var} m', '20'), _sdT('{var} m', '4.5'),
                    [amenity('bi-plug', _sdT('Shore power')), amenity('bi-droplet', _sdT('Water')), amenity('bi-wifi', _sdT('Wi-Fi'))]),
                pontoon(4, 'D', _sdT('Pontoon D'), _sdT('Day boats and tenders'), '50', _sdT('{var:1} – {var:2} m', ['5', '8']), _sdT('{var} m', '8'), _sdT('{var} m', '2'),
                    [amenity('bi-droplet', _sdT('Water'))])
            ])),
            kit.p(_sdT('The length overall of your boat decides the pontoon; the harbour office gives you the berth number on arrival.'), 'small text-body-secondary text-center mt-4 mb-0')
        ]);
    };

    // ── Arrival by sea ──────────────────────────────────────────────────
    // A fact of the approach: what it is, then the value as the skipper
    // reads it out.
    var call = function (n, icon, label, value, note) {
        return kit.col(_sdT('Fact {var}', n), [
            kit.div('h-100 p-3 rounded-3 border', _sdT('Fact Tile'), [
                kit.div('d-flex align-items-center gap-2 small text-body-secondary mb-1', _sdT('Fact Label'), [
                    kit.icon(icon, 'text-primary'),
                    kit.h('span', label)
                ]),
                kit.h('span', value, 'd-block h5 fw-bold mb-1'),
                kit.h('span', note, 'd-block small text-body-secondary')
            ])
        ], '6', '6');
    };
    var leg = function (n, title, text) {
        return kit.tag('li', 'd-flex gap-3 pb-3', _sdT('Leg {var}', n), [
            kit.h('span', String(n), 'badge rounded-pill text-bg-primary align-self-start'),
            kit.div('', _sdT('Leg Copy'), [
                kit.h('h4', title, 'h6 fw-bold mb-1'),
                kit.p(text, 'small text-body-secondary mb-0')
            ])
        ]);
    };
    var buildArrival = function () {
        var section = kit.section('py-5 bg-body text-body pg-marina-figures', _sdT('Marina (Arrival by Sea)'), [
            kit.row(_sdT('Arrival Row'), [
                kit.col(_sdT('Radio Column'), [
                    kit.h('h2', _sdT('Arriving by sea'), 'fw-bold mb-2'),
                    kit.p(_sdT('Call the harbour office before you pass the breakwater and we meet you at the reception pontoon.'), 'text-body-secondary mb-4'),
                    rise(kit.row(_sdT('Facts Row'), [
                        call(1, 'bi-broadcast', _sdT('VHF radio'), _sdT('Channel {var}', '72'), _sdT('Call sign: Pine Bay Marina')),
                        call(2, 'bi-telephone', _sdT('Phone'), '+90 555 000 00 00', _sdT('Day and night')),
                        call(3, 'bi-geo-alt', _sdT('Approach'), '40° 39.0′ N  29° 16.4′ E', _sdT('Half a mile off the entrance')),
                        call(4, 'bi-arrows-collapse', _sdT('Entrance'), _sdT('{var} m deep', '5'), _sdT('Green light to starboard'))
                    ], 'g-3'), 70)
                ], '6'),
                kit.col(_sdT('Steps Column'), [
                    kit.div('h-100 p-4 rounded-4 border', _sdT('Approach Card'), [
                        kit.h('h3', _sdT('From the breakwater to your berth'), 'h5 fw-bold mb-3'),
                        kit.tag('ol', 'list-unstyled mb-3', _sdT('Approach Steps'), [
                            leg(1, _sdT('Call on the radio'), _sdT('Give the name, length and draught of the boat, and how many nights you stay.')),
                            leg(2, _sdT('Enter slowly'), _sdT('Three knots inside the breakwater, keeping the green light to starboard.')),
                            leg(3, _sdT('Tie up at reception'), _sdT('The fuel and reception pontoon is straight ahead; the marina team takes your lines.')),
                            leg(4, _sdT('Check in at the office'), _sdT('Bring the papers and insurance of the boat; we give you your berth and the gate code.'))
                        ]),
                        kit.div('d-flex gap-2 small', _sdT('Night Note'), [
                            kit.icon('bi-moon-stars', 'text-primary flex-shrink-0'),
                            kit.h('span', _sdT('Arriving after dark? The harbour master on watch answers the radio all night.'))
                        ])
                    ])
                ], '6')
            ], 'g-lg-5 align-items-center')
        ]);
        section.props._attrs = [{ name: 'data-bs-theme', value: 'dark' }];
        return section;
    };

    return {
        prefix: 'marina-',
        group: { title: _sdT('Marina'), icon: 'bi-life-preserver' },
        items: [
            { label: _sdT('Marina: Pontoon Plan'),    type: 'component', icon: 'bi-signpost-2', extra: { componentType: 'marina-pontoons' },
              desc: _sdT('Cards for four pontoons, each with its letter, who it is for, berths, berth length, largest boat and depth, and what every berth has.') },
            { label: _sdT('Marina: Arrival by Sea'),  type: 'component', icon: 'bi-broadcast',  extra: { componentType: 'marina-arrival' },
              desc: _sdT('A dark band with the radio channel, phone, position and entrance depth as tiles, beside the steps from the breakwater to your berth.') }
        ],
        build: function (ctype) {
            if (ctype === 'marina-pontoons') return buildPontoons();
            if (ctype === 'marina-arrival')  return buildArrival();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
