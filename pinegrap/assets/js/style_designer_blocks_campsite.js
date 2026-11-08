/**
 * Pinegrap - Enterprise Website Platform
 *
 * Campsite block family: the sections a campsite's or a holiday park's
 * site needs that the general palette lacks. The campsite hero (the park
 * in a sentence over a wide picture, a way into each kind of stay and the
 * arrival and departure times), the comfort scale (the kinds of stay laid
 * from the wildest to the cosiest, with what a guest brings and what is
 * ready for them at each) and the pitch drawing (a pitch seen from above,
 * its hook-up, tap, car place and hedges numbered, with the legend
 * beside it). The park map, the facilities, the seasons and their prices,
 * the activities, the rules and the questions are other blocks (Zoo:
 * Park Map, Features, Hours: By Season, FAQ).
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
        // The text card lies over the picture's lower edge from md up.
        'pg-campsite-lift':   '.pg-campsite-lift { position: relative; z-index: 1; } ' +
                              '@media (min-width: 768px) { .pg-campsite-lift { margin-top: -6rem; } }',
        // A way into a kind of stay: a bordered tile whose edge takes the
        // theme colour on hover and keyboard focus.
        'pg-campsite-kinds':  '.pg-campsite-kinds li { border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); background: var(--bs-body-bg); transition: border-color .2s ease; } ' +
                              '.pg-campsite-kinds li:hover, .pg-campsite-kinds li:focus-within { border-color: var(--bs-primary); }',
        // From the wildest to the cosiest stay: a line that fills with the
        // theme colour, each stay a dot on it from lg up.
        'pg-campsite-scale':  '.pg-campsite-scale { height: .375rem; border-radius: 1rem; background: linear-gradient(90deg, var(--bs-border-color), var(--bs-primary)); }',
        'pg-campsite-stop':   '.pg-campsite-stop { position: relative; } ' +
                              '@media (min-width: 992px) { .pg-campsite-stop::before { content: ""; position: absolute; top: -2.125rem; left: 1.5rem; width: 1rem; height: 1rem; border-radius: 50%; background: var(--bs-body-bg); border: .25rem solid var(--bs-primary); } }',
        // A pitch from above: grass in the theme's success tint inside a
        // dashed boundary; the car place and the tent area drawn in it. A
        // rule names only its own class: a rule that named another's would
        // make the drop take that one as present and leave it out.
        'pg-campsite-plot':   '.pg-campsite-plot { background: var(--bs-success-bg-subtle); border: 2px dashed var(--bs-success-border-subtle); border-radius: var(--bs-border-radius-lg); }',
        'pg-campsite-area':   '.pg-campsite-area { display: flex; align-items: center; justify-content: center; font-size: .8125rem; font-weight: 600; border: 1px dashed var(--bs-success-text-emphasis); border-radius: var(--bs-border-radius); color: var(--bs-success-text-emphasis); }',
        // A numbered point on the drawing and the same number in its legend.
        'pg-campsite-marker': '.pg-campsite-marker { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 1.75rem; height: 1.75rem; border-radius: 50%; background: var(--bs-primary); color: var(--bs-body-bg); font-size: .8125rem; font-weight: 700; font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'campsite-hero':    ['pg-campsite-lift', 'pg-campsite-kinds'],
        'campsite-comfort': ['pg-campsite-scale', 'pg-campsite-stop'],
        'campsite-pitch':   ['pg-campsite-plot', 'pg-campsite-area', 'pg-campsite-marker']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };

    // The small coloured line above a title.
    var eyebrow = function (text, cls) {
        return kit.p(text, 'small text-uppercase fw-semibold text-primary ' + (cls || 'mb-2'));
    };

    // The kinds of stay of the sample park, from the wildest to the
    // cosiest: icon, name, who it sleeps, what a guest brings and what is
    // ready for them.
    var kinds = function () {
        return [
            { icon: 'bi-triangle', title: _sdT('Tent Pitches'), sleeps: _sdT('Room for a tent, a car and up to six people'),
                bring: [_sdT('Your tent and sleeping bags'), _sdT('A camping stove')],
                ready: [_sdT('Electric hook-up on most pitches'), _sdT('Washing block within two minutes\' walk')] },
            { icon: 'bi-truck', title: _sdT('Touring Pitches'), sleeps: _sdT('For a caravan or a motorhome and up to six people'),
                bring: [_sdT('Your caravan or motorhome')],
                ready: [_sdT('Electric hook-up on every pitch'), _sdT('Fresh water and waste point')] },
            { icon: 'bi-stars', title: _sdT('Glamping Tents'), sleeps: _sdT('Sleeps two to five, beds made'),
                bring: [_sdT('Towels and a torch')],
                ready: [_sdT('Real beds with linen'), _sdT('Wood stove and lanterns')] },
            { icon: 'bi-house', title: _sdT('Cabins and Lodges'), sleeps: _sdT('Sleeps two to six, open all year'),
                bring: [_sdT('Only yourselves')],
                ready: [_sdT('Kitchen, shower and heating'), _sdT('Bed linen and towels included')] }
        ];
    };

    // ── Campsite hero ───────────────────────────────────────────────────
    // A kind of stay in the chooser: its icon, then its name as the link
    // that makes the whole tile a way in, and who it sleeps.
    var kindTile = function (k) {
        return kit.tag('li', 'col position-relative d-flex align-items-center gap-3 p-3', k.title, [
            kit.icon(k.icon, 'fs-4 text-primary flex-shrink-0'),
            kit.div('', _sdT('Kind Text'), [
                kit.link(k.title, '#', 'stretched-link d-block fw-semibold text-body text-decoration-none', _sdT('Stay Link')),
                kit.h('span', k.sleeps, 'd-block small text-body-secondary')
            ])
        ]);
    };
    // A fact of the stay beside the buttons: icon and words.
    var fact = function (n, icon, text) {
        return kit.tag('li', 'd-flex align-items-center gap-2', _sdT('Fact {var}', n), [
            kit.icon(icon, 'text-primary flex-shrink-0'),
            kit.h('span', text)
        ]);
    };
    var buildHero = function () {
        var picture = kit.div('ratio ratio-21x9 rounded-4 overflow-hidden bg-body-tertiary', _sdT('Hero Picture'), [
            kit.img('campsite-hero', 1800, 770, _sdT('Tents in a meadow at the forest edge, the lake behind them at sunset'), 'object-fit-cover')
        ]);
        var card = kit.div('pg-campsite-lift col-lg-10 mx-auto p-4 p-lg-5 rounded-4 bg-body shadow', _sdT('Hero Card'), [
            eyebrow(_sdT('Campsite by the lake'), 'mb-3'),
            kit.h('h1', _sdT('Room to pitch, park or simply sleep under wood'), 'display-5 fw-bold mb-3'),
            kit.p(_sdT('A family-run campsite between the lake and the forest, with room to pitch, park or simply sleep under wood'), 'lead text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap align-items-center gap-3 mb-4', _sdT('Buttons'), [
                kit.link(_sdT('Request a Stay'), '#', 'btn btn-primary btn-lg px-4', _sdT('Request Link')),
                kit.link(_sdT('Prices and Seasons'), '#', 'btn btn-outline-secondary btn-lg px-4', _sdT('Prices Link')),
                kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-3 small mb-0', _sdT('Stay Facts'), [
                    fact(1, 'bi-clock', _sdT('Arrival from 14:00, departure by 11:00')),
                    fact(2, 'bi-calendar-range', _sdT('Open from spring to late autumn; cabins all year round'))
                ])
            ]),
            kit.tag('nav', 'pg-campsite-kinds', _sdT('Kinds of Stay'), [
                kit.p(_sdT('Choose how you want to stay'), 'small fw-semibold mb-2'),
                kit.tag('ul', 'list-unstyled row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-2 mb-0', _sdT('Kinds'), kinds().map(kindTile))
            ], [{ name: 'aria-label', value: _sdT('Kinds of Stay') }])
        ]);
        return kit.section('py-5 overflow-hidden', _sdT('Campsite (Hero)'), [
            fx(picture, { effect: 'fade', trigger: 'load', duration: 1000 }),
            fx(card, { effect: 'slide-up', trigger: 'load', duration: 700, delay: 200 })
        ]);
    };

    // ── Comfort scale ───────────────────────────────────────────────────
    // A short list under a small title: what to bring or what is ready.
    var list = function (title, icon, items, name) {
        return kit.div('mb-3', name, [
            kit.h('h4', title, 'small text-uppercase fw-semibold text-body-secondary mb-2'),
            kit.tag('ul', 'list-unstyled small mb-0', _sdT('Items'), items.map(function (t, i) {
                return kit.tag('li', 'd-flex gap-2 mb-1', _sdT('Item {var}', i + 1), [
                    kit.icon(icon, 'text-primary flex-shrink-0'),
                    kit.h('span', t)
                ]);
            }))
        ]);
    };
    // A kind of stay on the scale: a card with its dot on the line.
    var stop = function (k) {
        return kit.tag('li', 'col d-flex', k.title, [
            kit.div('pg-campsite-stop d-flex flex-column w-100 p-4 rounded-4 border bg-body', _sdT('Stay Card'), [
                kit.icon(k.icon, 'd-block fs-3 text-primary mb-2'),
                kit.h('h3', k.title, 'h5 mb-1'),
                kit.p(k.sleeps, 'small text-body-secondary mb-3'),
                list(_sdT('You bring'), 'bi-backpack', k.bring, _sdT('You bring')),
                list(_sdT('Ready for you'), 'bi-check-lg', k.ready, _sdT('Ready for you')),
                kit.link(_sdT('See the {var}', k.title), '#', 'link-primary fw-semibold small mt-auto', _sdT('Stay Link'))
            ])
        ]);
    };
    var buildComfort = function () {
        return kit.section('py-5 bg-body-tertiary', _sdT('Campsite (Comfort Scale)'), [
            kit.div('text-center col-lg-8 mx-auto mb-5', _sdT('Section Header'), [
                eyebrow(_sdT('Ways to stay')),
                kit.h('h2', _sdT('From a tent in the meadow to a cabin with a stove'), 'display-6 fw-bold mb-3'),
                kit.p(_sdT('Every way to stay with us, from the wildest to the cosiest: what you bring, and what is ready when you arrive.'), 'lead text-body-secondary mb-0')
            ]),
            kit.div('d-none d-lg-flex justify-content-between small fw-semibold text-body-secondary mb-2', _sdT('Scale Ends'), [
                kit.h('span', _sdT('Closest to nature')),
                kit.h('span', _sdT('Most comfort'))
            ], [{ name: 'aria-hidden', value: 'true' }]),
            kit.div('pg-campsite-scale d-none d-lg-block mb-5', _sdT('Scale'), [], [{ name: 'aria-hidden', value: 'true' }]),
            fx(kit.tag('ol', 'list-unstyled row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-0', _sdT('Kinds'), kinds().map(stop)),
                { effect: 'slide-up', trigger: 'view', stagger: 120 })
        ]);
    };

    // ── Pitch drawing ───────────────────────────────────────────────────
    // A numbered point on the drawing, placed with position utilities.
    var marker = function (n, place) {
        return kit.h('span', String(n), 'pg-campsite-marker position-absolute translate-middle shadow-sm ' + place);
    };
    // The same number in the legend, with what it marks.
    var legend = function (n, title, text) {
        return kit.tag('li', 'd-flex gap-3 mb-3', _sdT('Point {var}', n), [
            kit.h('span', String(n), 'pg-campsite-marker'),
            kit.div('', _sdT('Point Text'), [
                kit.h('h3', title, 'h6 mb-1'),
                kit.p(text, 'small text-body-secondary mb-0')
            ])
        ]);
    };
    var buildPitch = function () {
        var drawing = kit.div('ratio ratio-4x3', _sdT('Drawing'), [
            kit.div('pg-campsite-plot', _sdT('Pitch'), [
                kit.div('pg-campsite-area position-absolute top-0 bottom-0 start-0 m-3 w-25', _sdT('Car Place'), [
                    kit.h('span', _sdT('Car'))
                ]),
                kit.div('pg-campsite-area position-absolute top-0 end-0 m-3 w-50 h-75', _sdT('Tent Area'), [
                    kit.h('span', _sdT('Tent or caravan'))
                ]),
                marker(1, 'top-0 start-0 mt-3 ms-3'),
                marker(2, 'top-0 start-50'),
                marker(3, 'top-100 start-50'),
                marker(4, 'top-50 start-100')
            ], [{ name: 'aria-hidden', value: 'true' }])
        ]);
        return kit.section('py-5', _sdT('Campsite (Pitch Drawing)'), [
            kit.row(_sdT('Pitch Row'), [
                fx(kit.col(_sdT('Drawing Column'), [
                    kit.tag('figure', 'mb-0 p-3', _sdT('Pitch Drawing'), [
                        drawing,
                        kit.tag('figcaption', 'small text-body-secondary text-center mt-4', _sdT('Caption'), [
                            kit.h('span', _sdT('A meadow pitch of about 100 m², seen from above'))
                        ])
                    ])
                ], '6'), { effect: 'scale', trigger: 'view', duration: 700 }),
                kit.col(_sdT('Legend Column'), [
                    eyebrow(_sdT('Your pitch')),
                    kit.h('h2', _sdT('What a pitch holds'), 'display-6 fw-bold mb-3'),
                    kit.p(_sdT('Our tent pitches are wide and flat, marked by hedges and young trees so every family has its own corner.'), 'text-body-secondary mb-4'),
                    fx(kit.tag('ol', 'list-unstyled mb-4', _sdT('Legend'), [
                        legend(1, _sdT('Car beside the tent'), _sdT('One car parks on the pitch; a second car parks at the gate.')),
                        legend(2, _sdT('Electric hook-up'), _sdT('A post at the pitch\'s edge; bring a long cable with a campsite plug.')),
                        legend(3, _sdT('The park road'), _sdT('Walking pace on the park roads.')),
                        legend(4, _sdT('Hedges between the pitches'), _sdT('Hedges and young trees give every family its own corner.'))
                    ]), { effect: 'slide-up', trigger: 'view', stagger: 100 }),
                    kit.link(_sdT('See all pitches and cabins'), '#', 'btn btn-outline-primary px-4', _sdT('Pitches Link'))
                ], '6')
            ], 'align-items-center g-4 g-lg-5')
        ]);
    };

    return {
        prefix: 'campsite-',
        group: { title: _sdT('Campsite'), icon: 'bi-tree' },
        items: [
            { label: _sdT('Campsite: Hero'),          type: 'component', icon: 'bi-tree',         extra: { componentType: 'campsite-hero' } },
            { label: _sdT('Campsite: Comfort Scale'), type: 'component', icon: 'bi-sliders',      extra: { componentType: 'campsite-comfort' } },
            { label: _sdT('Campsite: Pitch Drawing'), type: 'component', icon: 'bi-bounding-box', extra: { componentType: 'campsite-pitch' } }
        ],
        build: function (ctype) {
            if (ctype === 'campsite-hero')    return buildHero();
            if (ctype === 'campsite-comfort') return buildComfort();
            if (ctype === 'campsite-pitch')   return buildPitch();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
