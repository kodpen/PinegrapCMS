/**
 * Pinegrap - Enterprise Website Platform
 *
 * Car Dealership block family: the sections a car dealer's site needs
 * that the general palette lacks. The showroom panel (one vehicle a full
 * window tall, its picture edge to edge, the name near the top with a
 * price and two actions, three of its figures at the foot), the stock
 * finder (the way into the stock by condition, body style and make, with
 * almost no chrome) and the three ways in (test drive, trade-in and
 * finance as tall picture tiles side by side). The figures of the dealer,
 * the services of the workshop, the customers' words and the questions
 * are general blocks (Marketing, Services, Social Proof, FAQ); the sticky
 * action bar is the general CTA (Sticky Bar).
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
        // The showroom panel: about a window tall, the photo (kept in the
        // style attribute, where Background edits it) keeping its middle.
        'pg-dl-panel':   '.pg-dl-panel { min-height: 92vh; background-size: cover; background-position: center; background-color: var(--bs-tertiary-bg); } ' +
                         '@supports (min-height: 92svh) { .pg-dl-panel { min-height: 92svh; } }',
        // The panel's veil: dark at the top under the name and at the foot
        // under the figures, clear in the middle where the vehicle stands.
        'pg-dl-veil':    '.pg-dl-veil { background-image: linear-gradient(180deg, rgba(0, 0, 0, .66) 0, rgba(0, 0, 0, .2) 32%, rgba(0, 0, 0, 0) 48%, rgba(0, 0, 0, .2) 64%, rgba(0, 0, 0, .8) 100%); }',
        // Thin, large type for names and headings; wide-spaced small capitals
        // for the line above them.
        'pg-dl-type':    '.pg-dl-type { font-weight: 300; letter-spacing: -.02em; line-height: 1.05; } ' +
                         '.pg-dl-kicker { letter-spacing: .18em; }',
        // A vehicle's figures: three side by side, the number large and thin
        // over its label (the dt comes first in the markup).
        'pg-dl-figures': '.pg-dl-figures { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin: 0; text-align: center; } ' +
                         '.pg-dl-figures .pg-dl-figure { display: flex; flex-direction: column-reverse; } ' +
                         '.pg-dl-figures dt { font-weight: 400; font-size: .8125rem; color: var(--bs-secondary-color); } ' +
                         '.pg-dl-figures dd { margin: 0; font-size: clamp(1.5rem, 4vw, 2.5rem); font-weight: 300; line-height: 1.1; font-variant-numeric: tabular-nums; color: var(--bs-emphasis-color); }',
        // The body styles of the stock finder: a row of large icons and words
        // between two hairlines; the one under the pointer or focus gets a
        // line in the theme's colour.
        'pg-dl-shapes':  '.pg-dl-shapes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0; padding: 0; list-style: none; border-top: 1px solid var(--bs-border-color); border-bottom: 1px solid var(--bs-border-color); } ' +
                         '@media (min-width: 768px) { .pg-dl-shapes { grid-template-columns: repeat(5, minmax(0, 1fr)); } } ' +
                         '.pg-dl-shapes .pg-dl-shape { display: flex; flex-direction: column; align-items: center; gap: .5rem; padding: 1.5rem .5rem; color: var(--bs-body-color); text-decoration: none; border-bottom: 2px solid transparent; transition: border-color .3s ease, color .3s ease; } ' +
                         '.pg-dl-shapes .pg-dl-shape .bi { font-size: 2.5rem; line-height: 1; } ' +
                         '.pg-dl-shapes .pg-dl-shape:hover, .pg-dl-shapes .pg-dl-shape:focus-visible { color: var(--bs-emphasis-color); border-bottom-color: var(--bs-primary); } ' +
                         '@media (prefers-reduced-motion: reduce) { .pg-dl-shapes .pg-dl-shape { transition: none; } }',
        // The three ways in: tall picture tiles side by side from lg up, one
        // under the other below; the photo in each tile's style attribute.
        'pg-dl-ways':    '.pg-dl-ways { display: grid; grid-template-columns: minmax(0, 1fr); gap: .25rem; } ' +
                         '@media (min-width: 992px) { .pg-dl-ways { grid-template-columns: repeat(3, minmax(0, 1fr)); } } ' +
                         '.pg-dl-ways .pg-dl-way { min-height: 60vh; height: 100%; background-size: cover; background-position: center; background-color: var(--bs-tertiary-bg); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'dealership-showroom': ['pg-dl-panel', 'pg-dl-veil', 'pg-dl-type', 'pg-dl-figures'],
        'dealership-finder':   ['pg-dl-type', 'pg-dl-shapes'],
        'dealership-ways':     ['pg-dl-type', 'pg-dl-ways', 'pg-shade-bottom']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };

    // A photo kept where the Background control edits it.
    var photo = function (seed) {
        return [{ name: 'style', value: 'background-image: url(https://picsum.photos/seed/' + seed + '/1920/1080); background-size: cover; background-position: center;' }];
    };

    // The wide-spaced small line above a name.
    var kicker = function (text, cls) {
        return kit.p(text, 'pg-dl-kicker small text-uppercase fw-semibold ' + (cls || 'mb-2'));
    };

    // ── Showroom panel ──────────────────────────────────────────────────
    // A figure of the vehicle: its label, then the number and its unit.
    var figure = function (label, value, unit) {
        return kit.div('pg-dl-figure', label, [
            kit.h('dt', label),
            kit.tag('dd', '', _sdT('Value'), [
                kit.h('span', value),
                kit.h('span', ' ' + unit, 'fs-6 fw-normal')
            ])
        ]);
    };
    var buildShowroom = function () {
        var head = kit.div('col-lg-8 mx-auto', _sdT('Panel Head'), [
            kicker(_sdT('New · Electric SUV')),
            kit.h('h2', 'Velo Volt', 'pg-dl-type display-3 mb-2'),
            kit.tag('p', 'lead mb-4', _sdT('Price'), [
                kit.h('span', _sdT('Cash price'), 'text-body-secondary me-2'),
                kit.h('span', '$41,900', 'fw-medium')
            ]),
            kit.div('d-flex flex-wrap justify-content-center gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Book a Test Drive'), '#', 'btn btn-primary btn-lg px-5', _sdT('Request Link')),
                kit.link(_sdT('More Like This'), '#', 'btn btn-secondary btn-lg px-5', _sdT('Stock Link'))
            ])
        ]);
        var figures = kit.tag('dl', 'pg-dl-figures col-lg-8 mx-auto', _sdT('Figures'), [
            figure(_sdT('Range'), '520', 'km'),
            figure(_sdT('Charge 10–80%'), '28', _sdT('min')),
            figure(_sdT('Power'), '300', _sdT('hp'))
        ]);
        return kit.tag('section', 'pg-dl-panel d-flex overflow-hidden', _sdT('Dealership (Showroom Panel)'), [
            kit.tag('div', 'pg-dl-veil d-flex flex-column justify-content-between w-100 py-5 text-body text-center', _sdT('Overlay'), [
                kit.createNode('container', { fluid: false, cssClass: 'pt-lg-4' }, [
                    fx(head, { effect: 'fade', trigger: 'view', duration: 900 })
                ]),
                kit.createNode('container', { fluid: false, cssClass: 'pt-5' }, [
                    fx(figures, { effect: 'slide-up', trigger: 'view', stagger: 120 })
                ])
            ], [{ name: 'data-bs-theme', value: 'dark' }])
        ], photo('dealership-panel'));
    };

    // ── Stock finder ────────────────────────────────────────────────────
    // A body style: a large icon and its word, opening the filtered list.
    var shape = function (icon, word) {
        return kit.tag('li', '', word, [
            kit.tag('a', 'pg-dl-shape', word, [
                kit.icon(icon),
                kit.h('span', word, 'fw-medium')
            ], [{ name: 'href', value: '#' }])
        ]);
    };
    var buildFinder = function () {
        var head = kit.div('text-center col-lg-8 mx-auto mb-5', _sdT('Section Header'), [
            kicker(_sdT('The stock'), 'text-primary mb-2'),
            kit.h('h2', _sdT('Find yours in the stock'), 'pg-dl-type display-5 mb-3'),
            kit.p(_sdT('Every car we have, new and used, with its photos, its history and its price.'), 'lead text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap justify-content-center gap-2', _sdT('Condition'), [
                kit.link(_sdT('New Cars'), '#', 'btn btn-primary rounded-pill px-4', _sdT('New Cars Link')),
                kit.link(_sdT('Used Cars'), '#', 'btn btn-outline-secondary rounded-pill px-4', _sdT('Used Cars Link')),
                kit.link(_sdT('All Cars'), '#', 'btn btn-link link-body-emphasis', _sdT('Stock Link'))
            ])
        ]);
        var makes = kit.div('d-flex flex-wrap justify-content-center align-items-center gap-3 gap-md-4 mt-4', _sdT('Makes'), [
            kit.h('span', _sdT('Makes'), 'small text-body-secondary'),
            kit.link('Aster', '#', 'link-body-emphasis fw-medium', 'Aster'),
            kit.link('Norda', '#', 'link-body-emphasis fw-medium', 'Norda'),
            kit.link('Velo', '#', 'link-body-emphasis fw-medium', 'Velo'),
            kit.link('Kestrel', '#', 'link-body-emphasis fw-medium', 'Kestrel')
        ]);
        return kit.section('py-5 my-lg-4', _sdT('Dealership (Stock Finder)'), [
            fx(head, { effect: 'fade', trigger: 'view', duration: 700 }),
            fx(kit.tag('ul', 'pg-dl-shapes', _sdT('Body Styles'), [
                shape('bi-car-front', _sdT('Hatchback')),
                shape('bi-car-front-fill', _sdT('Saloon')),
                shape('bi-car-front', _sdT('Estate')),
                shape('bi-truck-front', _sdT('SUV')),
                shape('bi-truck', _sdT('Van'))
            ]), { effect: 'slide-up', trigger: 'view', stagger: 80 }),
            makes
        ]);
    };

    // ── Three ways in ───────────────────────────────────────────────────
    // A tall tile: the photo, and at its foot the kind of request, a
    // heading, a line and the request's action.
    var way = function (seed, kind, title, text, action, variant) {
        return kit.tag('article', 'pg-dl-way d-flex', kind, [
            kit.tag('div', 'd-flex flex-column justify-content-end align-items-start w-100 p-4 p-lg-5 text-body pg-shade-bottom', _sdT('Overlay'), [
                kicker(kind),
                kit.h('h3', title, 'pg-dl-type h2 mb-2'),
                kit.p(text, 'mb-4'),
                kit.link(action, '#', 'btn ' + variant + ' px-4', _sdT('Request Link'))
            ], [{ name: 'data-bs-theme', value: 'dark' }])
        ], photo(seed));
    };
    var buildWays = function () {
        return kit.tag('section', 'py-5', _sdT('Dealership (Three Ways In)'), [
            kit.createNode('container', { fluid: false, cssClass: '' }, [
                fx(kit.div('text-center col-lg-8 mx-auto mb-5', _sdT('Section Header'), [
                    kit.h('h2', _sdT('Before you sign'), 'pg-dl-type display-5 mb-3'),
                    kit.p(_sdT('Drive the car, know what yours is worth and see the monthly figure, all before you decide.'), 'lead text-body-secondary mb-0')
                ]), { effect: 'fade', trigger: 'view', duration: 700 })
            ]),
            fx(kit.div('pg-dl-ways', _sdT('Ways'), [
                way('dealership-drive', _sdT('Test drive'), _sdT('Drive it on your own roads'),
                    _sdT('Choose a day and a time of day; we confirm within one working day.'), _sdT('Book a Test Drive'), 'btn-primary'),
                way('dealership-tradein', _sdT('Trade-in'), _sdT('Your car, valued while you drive'),
                    _sdT('Bring the car you drive now; the offer comes in writing.'), _sdT('Value My Car'), 'btn-secondary'),
                way('dealership-finance', _sdT('Finance'), _sdT('The monthly figure before you sign'),
                    _sdT('Hire purchase, a contract with three choices at the end, or cash: each figure explained.'), _sdT('Ways to Pay'), 'btn-secondary')
            ]), { effect: 'slide-up', trigger: 'view', stagger: 150 })
        ]);
    };

    return {
        prefix: 'dealership-',
        group: { title: _sdT('Car Dealership'), icon: 'bi-car-front' },
        items: [
            { label: _sdT('Dealership: Showroom Panel'), type: 'component', icon: 'bi-car-front',  extra: { componentType: 'dealership-showroom' },
              desc: _sdT('A full-width car photo with the model name, cash price and two buttons over it, and three large figures along its foot.') },
            { label: _sdT('Dealership: Stock Finder'),   type: 'component', icon: 'bi-funnel',     extra: { componentType: 'dealership-finder' },
              desc: _sdT('A centred heading with new, used and all cars buttons, a row of body style tiles with large icons and links to the makes.') },
            { label: _sdT('Dealership: Three Ways In'),  type: 'component', icon: 'bi-signpost-2', extra: { componentType: 'dealership-ways' },
              desc: _sdT('Three tall photo tiles for a test drive, a trade-in value and finance, each with a heading, a line and a button at its foot.') }
        ],
        build: function (ctype) {
            if (ctype === 'dealership-showroom') return buildShowroom();
            if (ctype === 'dealership-finder') return buildFinder();
            if (ctype === 'dealership-ways') return buildWays();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
