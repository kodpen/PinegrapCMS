/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bakery block family: the sections a bakery's or a patisserie's site
 * needs that the general palette lacks. The fresh-from-the-oven hero (the
 * way in: the bakery's promise, the times today's batches come out of the
 * oven and an order tag with the last order time for the next day, hung
 * over a picture of the bake) and the cake size guide (celebration cakes
 * drawn to scale beside how many each serves and its starting price, then
 * the sponges and the fillings to choose from). The daily bake is a menu
 * block (Menu: Tabs, Menu: Image Cards) with its diet key (Menu: Diet Key);
 * wholesale, the order steps, the opening hours, figures and customers'
 * words are general blocks too (Catering, Steps, Hours, Stats, Social
 * Proof).
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
        // A tall picture of the bake with the order tag hung over its lower
        // corner from md up; below md the tag follows the picture.
        'pg-bakery-photo': '.pg-bakery-photo { position: relative; } ' +
                           '.pg-bakery-photo .ratio { aspect-ratio: 4 / 5; background-color: var(--bs-tertiary-bg); } ' +
                           '.pg-bakery-photo .ratio::before { display: none; } ' +
                           '.pg-bakery-photo .pg-bakery-hang { max-width: 18rem; } ' +
                           '@media (min-width: 768px) { .pg-bakery-photo .pg-bakery-hang { position: absolute; right: -1rem; bottom: 2.5rem; width: 15rem; } }',
        // The order tag: a card with a punched hole, hung at a slant from md
        // up, straight again on hover. The slant is the tag's own, the
        // entrance sits on the node around it, so the two never meet.
        'pg-bakery-tag':   '.pg-bakery-tag { position: relative; padding: 2.25rem 1.5rem 1.5rem; border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); ' +
                           'background-color: var(--bs-body-bg); box-shadow: var(--bs-box-shadow-lg); } ' +
                           '.pg-bakery-tag::before { content: ""; position: absolute; top: .875rem; left: 50%; width: .75rem; height: .75rem; margin-left: -.375rem; border-radius: 50%; ' +
                           'background-color: var(--bs-tertiary-bg); box-shadow: inset 0 0 0 1px var(--bs-border-color); } ' +
                           '.pg-bakery-tag time { font-variant-numeric: tabular-nums; } ' +
                           '@media (min-width: 768px) { .pg-bakery-tag { transform: rotate(-4deg); transition: transform .3s ease; } .pg-bakery-tag:hover { transform: none; } } ' +
                           '@media (prefers-reduced-motion: reduce) { .pg-bakery-tag { transition: none; } }',
        // Today's batches: a rule in the theme's colour over each, the
        // times lined up.
        'pg-bakery-oven':  '.pg-bakery-oven li { padding-top: .75rem; border-top: 3px solid var(--bs-primary); } ' +
                           '.pg-bakery-oven time { font-variant-numeric: tabular-nums; }',
        // A cake drawn from the side on a plate line: layers of sponge under
        // a band of icing; the widths follow the diameters (15, 20 and 25 cm
        // as 60, 80 and 100 per cent), so the sizes compare at a glance.
        // Each layer centres itself with auto margins: a container that
        // centred its items would shrink the editor's wrapper around a layer
        // to nothing, and the percentage width with it.
        'pg-bakery-cake':  '.pg-bakery-cake { display: flex; flex-direction: column; justify-content: flex-end; height: 7.5rem; ' +
                           'padding-bottom: .375rem; border-bottom: 4px solid var(--bs-border-color); } ' +
                           '.pg-bakery-cake .pg-bakery-layer { height: 3.25rem; margin-inline: auto; border-top: .625rem solid var(--bs-primary); border-radius: .75rem .75rem .25rem .25rem; ' +
                           'background-color: var(--bs-primary-bg-subtle); box-shadow: inset 0 -.5rem 0 var(--bs-secondary-bg); } ' +
                           '.pg-bakery-cake .pg-bakery-d15 { width: 60%; } .pg-bakery-cake .pg-bakery-d20 { width: 80%; } .pg-bakery-cake .pg-bakery-d25 { width: 100%; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'bakery-hero':  ['pg-bakery-photo', 'pg-bakery-tag', 'pg-bakery-oven'],
        'bakery-cakes': ['pg-bakery-cake']
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

    // ── Fresh from the oven hero ────────────────────────────────────────
    // A batch of the day: when it comes out of the oven and what it is.
    var batch = function (time, name) {
        return kit.tag('li', 'col', name, [
            kit.time(time, time, 'd-block fs-4 fw-bold lh-1 mb-2', _sdT('Out of the Oven')),
            kit.h('h3', name, 'h6 fw-semibold mb-0')
        ]);
    };
    var buildHero = function () {
        var words = fx(kit.div('mb-5', _sdT('Hero Text'), [
            kit.div('badge rounded-pill bg-primary-subtle text-primary-emphasis d-inline-flex align-items-center gap-1 mb-3', _sdT('Ovens Lit'), [
                kit.icon('bi-fire'),
                kit.h('span', _sdT('Ovens lit at')),
                kit.time('04:00', '04:00', '', _sdT('Lighting Time'))
            ]),
            kit.h('h1', _sdT('Fresh from the oven, every morning'), 'display-3 fw-bold mb-3'),
            kit.p(_sdT('Sourdough and pastries from the stone oven before the street wakes up, celebration cakes made for their day, and a counter that is empty by closing.'), 'lead text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('See the daily bake'), '#', 'btn btn-primary btn-lg rounded-pill px-4', _sdT('Daily Bake Link')),
                kit.link(_sdT('Order a Cake'), '#', 'btn btn-outline-primary btn-lg rounded-pill px-4', _sdT('Cake Order Link'))
            ])
        ]), { effect: 'slide-up', trigger: 'load', duration: 700 });
        var oven = kit.div('', _sdT('Oven Times'), [
            kit.h('h2', _sdT('Out of the oven today'), 'h6 text-uppercase fw-semibold text-body-secondary mb-3'),
            fx(kit.tag('ol', 'pg-bakery-oven list-unstyled row row-cols-3 g-3 mb-0', _sdT('Batches'), [
                batch('07:00', _sdT('Sourdough and rye')),
                batch('08:30', _sdT('Croissants and cardamom buns')),
                batch('11:00', _sdT('Focaccia and the cakes of the day'))
            ]), { effect: 'slide-up', trigger: 'load', delay: 400, stagger: 120 })
        ]);
        var tag = kit.div('pg-bakery-tag text-center', _sdT('Tag'), [
            eyebrow(_sdT('Order ahead'), 'mb-1'),
            kit.time('14:00', '14:00', 'd-block display-6 fw-bold lh-1 mb-1', _sdT('Last Order Time')),
            kit.p(_sdT('Last orders for the next day'), 'fw-semibold mb-2'),
            kit.p(_sdT('Collected at the counter, with your name on the bag.'), 'small text-body-secondary mb-0')
        ]);
        var picture = kit.div('pg-bakery-photo ms-lg-4', _sdT('Picture Frame'), [
            fx(kit.div('ratio rounded-4 overflow-hidden shadow-lg', _sdT('Picture'), [
                kit.img('bakery-hero', 960, 1200, _sdT('Loaves of sourdough cooling on a rack beside the stone oven'), 'object-fit-cover')
            ]), { effect: 'scale', trigger: 'load', duration: 1000 }),
            fx(kit.div('pg-bakery-hang mx-auto mt-4 mt-md-0', _sdT('Tag Holder'), [tag]),
                { effect: 'slide-left', trigger: 'load', duration: 800, delay: 700 })
        ]);
        return kit.section('py-5 overflow-hidden', _sdT('Bakery (Fresh from the Oven Hero)'), [
            kit.row(_sdT('Hero Row'), [
                kit.col(_sdT('Words'), [words, oven], '6'),
                kit.col(_sdT('Picture'), [picture], '6')
            ], 'g-5 align-items-center')
        ]);
    };

    // ── Cake size guide ─────────────────────────────────────────────────
    // A size: the cake drawn to scale (hidden from a screen reader, which
    // reads the size in words), its name, how many it serves and the price
    // it starts at. `widths` are the layers from the top down, each one's
    // diameter class.
    var size = function (name, servings, price, widths) {
        var layers = widths.map(function (w) {
            return kit.div('pg-bakery-layer ' + w, _sdT('Cake Layer'), []);
        });
        return kit.tag('li', 'col', name, [
            kit.div('h-100 p-4 rounded-4 bg-body-tertiary', _sdT('Size Card'), [
                kit.div('pg-bakery-cake mb-3', _sdT('Drawing'), layers, [{ name: 'aria-hidden', value: 'true' }]),
                kit.h('h3', name, 'h5 fw-bold mb-1'),
                kit.div('d-flex align-items-center gap-2 text-body-secondary mb-3', _sdT('Servings'), [
                    kit.icon('bi-people'),
                    kit.h('span', servings)
                ]),
                kit.div('d-flex flex-wrap align-items-baseline gap-2', _sdT('Price'), [
                    kit.h('span', _sdT('Starting from'), 'small text-body-secondary'),
                    kit.h('span', price, 'fs-4 fw-bold')
                ])
            ])
        ]);
    };
    // The sponges or the fillings: a title over a row of pills.
    var choices = function (title, items) {
        return kit.col(title, [
            kit.h('h3', title, 'h5 fw-bold mb-3'),
            kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', _sdT('Choices'), items.map(function (text) {
                return kit.tag('li', 'rounded-pill border px-3 py-1', text, [kit.h('span', text)]);
            }))
        ], '6');
    };
    var buildCakes = function () {
        var head = kit.div('row g-4 align-items-end mb-5', _sdT('Section Header'), [
            kit.div('col-lg-7', _sdT('Section Title'), [
                eyebrow(_sdT('Celebration Cakes')),
                kit.h('h2', _sdT('How big should the cake be?'), 'display-5 fw-bold mb-0')
            ]),
            kit.div('col-lg-5', _sdT('Lead'), [
                kit.p(_sdT('Choose the size by how many it should serve, then the sponge and the filling. Every cake is baked for its day.'), 'lead text-body-secondary mb-0')
            ])
        ]);
        return kit.section('py-5', _sdT('Bakery (Cake Sizes)'), [
            fx(head, { effect: 'fade', trigger: 'view', duration: 800 }),
            fx(kit.tag('ol', 'list-unstyled row row-cols-2 row-cols-lg-4 g-3 g-lg-4 mb-5', _sdT('Sizes'), [
                size(_sdT('15 cm round'), _sdT('8 to 10 slices'), _sdT('$45'), ['pg-bakery-d15']),
                size(_sdT('20 cm round'), _sdT('14 to 18 slices'), _sdT('$65'), ['pg-bakery-d20']),
                size(_sdT('25 cm round'), _sdT('24 to 30 slices'), _sdT('$80'), ['pg-bakery-d25']),
                size(_sdT('Two tiers'), _sdT('40 to 60 slices'), _sdT('$240'), ['pg-bakery-d15', 'pg-bakery-d25'])
            ]), { effect: 'scale', trigger: 'view', duration: 700, stagger: 150 }),
            fx(kit.div('', _sdT('Choices and Order'), [
                kit.row(_sdT('Row'), [
                    choices(_sdT('Sponges'), [_sdT('Vanilla'), _sdT('Dark chocolate'), _sdT('Lemon and poppy seed'), _sdT('Carrot and walnut'), _sdT('Red velvet')]),
                    choices(_sdT('Fillings'), [_sdT('Vanilla buttercream'), _sdT('Chocolate ganache'), _sdT('Salted caramel'), _sdT('Raspberry jam and cream'), _sdT('Lemon curd')])
                ], 'g-4 mb-4'),
                kit.div('d-flex flex-column flex-md-row align-items-md-center gap-3 p-4 rounded-4 border', _sdT('Note'), [
                    kit.div('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis fs-4 p-2 align-self-start align-self-md-center', _sdT('Icon'), [
                        kit.icon('bi-calendar-heart')
                    ]),
                    kit.p(_sdT('Custom cakes: ask at least a week ahead; tiered wedding cakes a month ahead.'), 'flex-grow-1 mb-0'),
                    kit.link(_sdT('Order a Cake'), '#', 'btn btn-primary rounded-pill px-4 flex-shrink-0', _sdT('Cake Order Link'))
                ])
            ]), { effect: 'fade', trigger: 'view', duration: 800, delay: 200 })
        ]);
    };

    return {
        prefix: 'bakery-',
        group: { title: _sdT('Bakery'), icon: 'bi-cake2' },
        items: [
            { label: _sdT('Bakery: Fresh from the Oven Hero'), type: 'component', icon: 'bi-fire',         extra: { componentType: 'bakery-hero' },
              desc: _sdT('A large headline, a text and two buttons over the oven times of the day, beside a tall photo with an order tag hung on it.') },
            { label: _sdT('Bakery: Cake Sizes'),               type: 'component', icon: 'bi-cake2',        extra: { componentType: 'bakery-cakes' },
              desc: _sdT('Four cake sizes drawn to scale with servings and a starting price, then sponges and fillings as pills and an order note.') }
        ],
        build: function (ctype) {
            if (ctype === 'bakery-hero') return buildHero();
            if (ctype === 'bakery-cakes') return buildCakes();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
