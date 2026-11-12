/**
 * Pinegrap - Enterprise Website Platform
 *
 * Coworking block family: the sections a coworking space's site needs that
 * the general palette lacks. The workspace hero asks how the visitor works
 * and answers with a way in for each (a desk for the day, a desk of one's
 * own, a private office, a meeting room by the hour), each with its
 * starting price, beside a mosaic of the space. The rooms to hire, the
 * plans, the amenities and the building's floors are general blocks
 * (Locations: Spaces to Hire, Pricing, Features, Locations: Floor Directory).
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
        // A way in: a bordered row that turns the palette colour under the
        // pointer or the keyboard and moves a little to the side; still
        // under reduced motion.
        'pg-cw-way':    '.pg-cw-way { border: 1px solid var(--bs-border-color); transition: border-color .2s ease, transform .2s ease; } ' +
                        '.pg-cw-way:hover, .pg-cw-way:focus-visible { border-color: var(--bs-primary); transform: translateX(4px); } ' +
                        '@media (prefers-reduced-motion: reduce) { .pg-cw-way { transition: none; } .pg-cw-way:hover, .pg-cw-way:focus-visible { transform: none; } }',
        // Two pictures of the space side by side, the second shorter and
        // raised, the note card under them.
        'pg-cw-mosaic': '.pg-cw-mosaic { display: grid; grid-template-columns: 3fr 2fr; gap: 1rem; align-items: start; } ' +
                        '.pg-cw-mosaic .pg-cw-tall { aspect-ratio: 3 / 4; } .pg-cw-mosaic .pg-cw-short { aspect-ratio: 1 / 1; margin-top: 3rem; } ' +
                        '.pg-cw-mosaic img { width: 100%; object-fit: cover; }',
        // Prices in the ways list line up.
        'pg-cw-price':  '.pg-cw-price { font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'coworking-hero': ['pg-cw-way', 'pg-cw-mosaic', 'pg-cw-price']
    };

    var fx = function (node, effect) {
        node.props._fx = effect;
        return node;
    };

    // ── Workspace hero ──────────────────────────────────────────────────
    // A way in: its icon, its name and line, "From" + price + unit as
    // separate nodes (a site writes its own currency and unit), an arrow.
    var way = function (n, icon, name, text, price, unit) {
        return kit.tag('li', '', _sdT('Way {var}', n), [
            kit.createNode('semantic', { tag: 'a', cssClass: 'pg-cw-way d-flex align-items-center gap-3 p-3 rounded-3 text-body text-decoration-none', href: '#', target: '', customName: name }, [
                kit.div('d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary-emphasis p-2 lh-1', _sdT('Icon Box'), [
                    kit.icon(icon, 'fs-4')
                ]),
                kit.div('flex-grow-1', _sdT('Way Text'), [
                    kit.h('span', name, 'd-block fw-bold'),
                    kit.h('span', text, 'd-block small text-body-secondary')
                ]),
                kit.div('text-end text-nowrap pg-cw-price', _sdT('Price'), [
                    kit.h('span', _sdT('Starting at'), 'd-block small text-body-secondary'),
                    kit.h('span', price, 'fw-bold'),
                    kit.h('span', unit, 'small text-body-secondary')
                ]),
                kit.icon('bi-arrow-right', 'text-primary')
            ])
        ]);
    };
    var buildHero = function () {
        var ways = kit.tag('ul', 'list-unstyled d-grid gap-2 mb-0', _sdT('Ways In'), [
            way(1, 'bi-cup-hot', _sdT('Hot desk'), _sdT('Any free desk, for a day or a month'), _sdT('$25'), _sdT('/day')),
            way(2, 'bi-pc-display', _sdT('Fixed desk'), _sdT('Your own desk, your screen stays'), _sdT('$290'), _sdT('/month')),
            way(3, 'bi-door-closed', _sdT('Private office'), _sdT('A lockable room for a team of two to ten'), _sdT('$950'), _sdT('/month')),
            way(4, 'bi-easel', _sdT('Meeting room'), _sdT('Booked by the hour, screen and coffee included'), _sdT('$35'), _sdT('/hour'))
        ], [{ name: 'aria-label', value: _sdT('Ways to work') }]);
        return kit.tag('section', 'py-5 overflow-hidden', _sdT('Coworking Hero'), [
            kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                kit.row(_sdT('Hero Row'), [
                    kit.col(_sdT('Hero Copy'), [
                        fx(kit.div('mb-4', _sdT('Hero Text'), [
                            kit.h('span', _sdT('Open every day, 7 am to 10 pm'), 'd-inline-block mb-3 small fw-semibold text-uppercase text-primary'),
                            kit.h('h1', _sdT('A place to do your best work'), 'display-4 fw-bold mb-3'),
                            kit.p(_sdT('Desks, offices and meeting rooms in a bright old warehouse, with people worth meeting at the coffee bar. How do you work?'), 'lead text-body-secondary mb-0')
                        ]), { effect: 'slide-up', trigger: 'load', duration: 700, stagger: 100 }),
                        fx(ways, { effect: 'slide-up', trigger: 'load', duration: 600, delay: 300, stagger: 90 }),
                        kit.p(_sdT('Not sure yet? Book a free tour and work a day on us.'), 'small text-body-secondary mt-3 mb-0')
                    ], '6'),
                    kit.col(_sdT('Hero Pictures'), [
                        fx(kit.div('pg-cw-mosaic', _sdT('Picture Mosaic'), [
                            kit.img('coworking-hall', 600, 800, _sdT('Members at long desks under the warehouse windows'), 'rounded-4 pg-cw-tall'),
                            kit.img('coworking-booth', 500, 500, _sdT('A phone booth with a lamp and a stool'), 'rounded-4 pg-cw-short')
                        ]), { effect: 'fade', trigger: 'load', duration: 1000 })
                    ], '6')
                ], 'align-items-center g-lg-5')
            ])
        ]);
    };

    return {
        prefix: 'coworking-',
        group: { title: _sdT('Coworking'), icon: 'bi-laptop' },
        items: [
            { label: _sdT('Coworking: Workspace Hero'), type: 'component', icon: 'bi-signpost-split', extra: { componentType: 'coworking-hero' },
              desc: _sdT('A headline over four ways to work, each with an icon, a starting price and an arrow, beside a mosaic of two photos.') }
        ],
        build: function (ctype) {
            if (ctype === 'coworking-hero') return buildHero();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
