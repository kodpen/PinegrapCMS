/**
 * Pinegrap - Enterprise Website Platform
 *
 * Museum block family: the sections a museum's or an art gallery's site
 * needs that the general palette lacks. The exhibition hero (the exhibition
 * on now as a poster: its title, when and where it is on, who curated it and
 * what it costs, beside its picture, with the other exhibitions on under
 * it), the look-closer spotlight (one work with its wall label and three
 * numbered details to look for) and the highlights trail (the works not to
 * miss, numbered along a route through the galleries, each with its room).
 * Opening hours, admission, membership, the departments of the collection,
 * the floors, the people and the questions are general blocks (Hours &
 * Locations, Pricing, Services, Team, FAQ).
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
        // A portrait poster frame: four by five, the picture covering it.
        'pg-museum-poster':  '.pg-museum-poster { position: relative; aspect-ratio: 4 / 5; overflow: hidden; background: var(--bs-tertiary-bg); } ' +
                             '.pg-museum-poster > img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }',
        // The exhibition's facts: four short pairs in a row from sm up,
        // each label small above its value, on a hairline.
        'pg-museum-facts':   '.pg-museum-facts { display: grid; gap: 1rem 1.5rem; margin: 0; padding-top: 1rem; border-top: 1px solid var(--bs-border-color); } ' +
                             '.pg-museum-facts dt { font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; color: var(--bs-secondary-color); } ' +
                             '.pg-museum-facts dd { margin: 0; font-weight: 600; } ' +
                             '@media (min-width: 576px) { .pg-museum-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); } }',
        // A wall label: the lines of a museum's caption beside a rule in
        // the theme's colour.
        'pg-museum-label':   '.pg-museum-label { padding-left: 1rem; border-left: 3px solid var(--bs-primary); } ' +
                             '.pg-museum-label > * { margin-bottom: .125rem; }',
        // A detail to look for and a stop on the trail: a round number in
        // tabular figures.
        'pg-museum-no':      '.pg-museum-no { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 2.25rem; height: 2.25rem; border-radius: 50%; background: var(--bs-primary); color: var(--bs-body-bg); font-weight: 700; font-variant-numeric: tabular-nums; }',
        // The trail: from lg up a dashed route runs through the stops'
        // numbers, which sit on it.
        'pg-museum-trail':   '.pg-museum-trail > li { position: relative; } ' +
                             '.pg-museum-trail .pg-museum-no { position: relative; z-index: 1; box-shadow: 0 0 0 .375rem var(--bs-body-bg); } ' +
                             '@media (min-width: 992px) { .pg-museum-trail > li::before { content: ""; position: absolute; top: 1.125rem; left: calc(var(--bs-gutter-x) * .5); right: calc(var(--bs-gutter-x) * -.5); border-top: 2px dashed var(--bs-border-color); } ' +
                             '.pg-museum-trail > li:last-child::before { right: calc(var(--bs-gutter-x) * .5); } }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'museum-exhibition': ['pg-museum-poster', 'pg-museum-facts'],
        'museum-object':     ['pg-museum-label', 'pg-museum-no'],
        'museum-trail':      ['pg-museum-no', 'pg-museum-trail']
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

    // ── Exhibition hero ─────────────────────────────────────────────────
    // A fact of the exhibition: its label and its value.
    var fact = function (label, value) {
        return kit.div('', label, [
            kit.h('dt', label),
            kit.h('dd', value)
        ]);
    };
    // Another exhibition on: its title and when and where it is on.
    var also = function (title, when) {
        return kit.tag('li', 'py-3 border-bottom', title, [
            kit.h('h3', title, 'h6 fw-semibold mb-1'),
            kit.p(when, 'small text-body-secondary mb-0')
        ]);
    };
    var buildExhibition = function () {
        var words = kit.col(_sdT('Exhibition'), [
            eyebrow(_sdT('Now On'), 'mb-3'),
            kit.h('h1', _sdT('Painting the Light'), 'display-4 fw-bold mb-3'),
            kit.p(_sdT('Sixty landscapes that chase the light of morning, noon and evening, from the museum and from collections abroad.'), 'lead text-body-secondary mb-4'),
            kit.tag('dl', 'pg-museum-facts mb-4', _sdT('Facts'), [
                fact(_sdT('When'), _sdT('Until the end of spring')),
                fact(_sdT('Where'), _sdT('The Garden Galleries')),
                fact(_sdT('Curator'), _sdT('Selin Aydın')),
                fact(_sdT('Admission'), _sdT('A ticketed exhibition: members free'))
            ]),
            kit.div('d-flex flex-wrap gap-2 mb-5', _sdT('Buttons'), [
                kit.link(_sdT('Book Tickets'), '#', 'btn btn-primary btn-lg px-4', _sdT('Tickets Link')),
                kit.link(_sdT('About the Exhibition'), '#', 'btn btn-outline-secondary btn-lg px-4', _sdT('Exhibition Link'))
            ]),
            fx(kit.div('', _sdT('Also On'), [
                kit.h('h2', _sdT('Also on'), 'small text-uppercase fw-semibold text-body-secondary border-bottom pb-2 mb-0'),
                kit.tag('ul', 'list-unstyled mb-0', _sdT('Exhibitions'), [
                    also(_sdT('Made of Clay'), _sdT('Until the summer holidays') + ' · ' + _sdT('Gallery 7')),
                    also(_sdT('Faces of the City'), _sdT('Opens in the autumn') + ' · ' + _sdT('The Garden Galleries'))
                ])
            ]), { effect: 'slide-up', trigger: 'load', delay: 400 })
        ], '7');
        var poster = kit.col(_sdT('Poster'), [
            kit.tag('figure', 'mb-0', _sdT('Exhibition Picture'), [
                kit.div('pg-museum-poster rounded-3 shadow', _sdT('Picture'), [
                    kit.img('museum-exhibition', 960, 1200, _sdT('A landscape painting of a river at sunrise, lit from the side in a dark gallery'), '')
                ]),
                kit.tag('figcaption', 'small text-body-secondary mt-2', _sdT('Photo Caption'), [
                    kit.h('span', _sdT('One of sixty landscapes in the exhibition.'))
                ])
            ])
        ], '5');
        return kit.section('py-5 overflow-hidden', _sdT('Museum (Exhibition Hero)'), [
            kit.row(_sdT('Exhibition Row'), [
                fx(words, { effect: 'slide-up', trigger: 'load', duration: 700 }),
                fx(poster, { effect: 'fade', trigger: 'load', duration: 1000, delay: 200 })
            ], 'g-4 g-lg-5 align-items-center')
        ]);
    };

    // ── Look closer ─────────────────────────────────────────────────────
    // A detail to look for: its number, a close-up, what it is and why.
    var detail = function (no, seed, alt, title, text) {
        return kit.tag('li', 'd-flex gap-3 mb-4', title, [
            kit.h('span', no, 'pg-museum-no'),
            kit.div('flex-grow-1', _sdT('Detail Text'), [
                kit.div('ratio ratio-16x9 rounded-3 overflow-hidden bg-body-tertiary mb-2', _sdT('Close-up'), [
                    kit.img(seed, 640, 360, alt, 'object-fit-cover')
                ]),
                kit.h('h3', title, 'h6 fw-semibold mb-1'),
                kit.p(text, 'small text-body-secondary mb-0')
            ])
        ]);
    };
    var buildObject = function () {
        var work = kit.col(_sdT('Work'), [
            kit.tag('figure', 'mb-0', _sdT('Work Picture'), [
                kit.div('ratio ratio-4x3 rounded-3 overflow-hidden bg-body-tertiary mb-3', _sdT('Picture'), [
                    kit.img('museum-object', 1400, 1050, _sdT('Fishing boats come home under a pink sky while the lamps of the quay are lit'), 'object-fit-cover')
                ]),
                kit.tag('figcaption', 'pg-museum-label small', _sdT('Wall Label'), [
                    kit.p(_sdT('A painter of the city\'s harbour school'), 'fw-semibold'),
                    kit.p(_sdT('Harbour at Dusk'), 'fst-italic'),
                    kit.p(_sdT('Late 19th century'), ''),
                    kit.p(_sdT('Oil on canvas'), 'text-body-secondary'),
                    kit.p(_sdT('Gallery 3'), 'text-body-secondary mb-0')
                ])
            ])
        ], '7');
        var look = kit.col(_sdT('Look Closer'), [
            eyebrow(_sdT('Look closer')),
            kit.h('h2', _sdT('Harbour at Dusk'), 'h2 fw-bold mb-3'),
            kit.p(_sdT('The painting was the first work the museum was given and has hung in the same gallery ever since.'), 'text-body-secondary mb-4'),
            fx(kit.tag('ol', 'list-unstyled mb-4', _sdT('Details'), [
                detail('1', 'museum-detail-sky', _sdT('A pink evening sky over the masts'), _sdT('The sky'),
                    _sdT('The painter laid the pink over a grey ground, so the evening seems to glow from behind the clouds.')),
                detail('2', 'museum-detail-lamps', _sdT('Small dots of yellow along the quay'), _sdT('The lamps'),
                    _sdT('A single touch of yellow for each lamp: step back and the quay lights up.')),
                detail('3', 'museum-detail-boat', _sdT('A fishing boat with a red sail'), _sdT('The red sail'),
                    _sdT('The only red in the painting leads your eye to the boat coming home last.'))
            ]), { effect: 'slide-up', trigger: 'view', stagger: 120 }),
            kit.link(_sdT('Explore the collection'), '#', 'btn btn-outline-primary px-4', _sdT('Collection Link'))
        ], '5');
        return kit.section('py-5', _sdT('Museum (Look Closer)'), [
            kit.row(_sdT('Work Row'), [
                fx(work, { effect: 'fade', trigger: 'view', duration: 900 }),
                look
            ], 'g-4 g-lg-5')
        ]);
    };

    // ── Highlights trail ────────────────────────────────────────────────
    // A stop: its number on the route, the work, its maker and its room.
    var stop = function (no, seed, alt, title, maker, room) {
        return kit.tag('li', 'col', title, [
            kit.h('span', no, 'pg-museum-no mb-3'),
            kit.div('ratio ratio-4x3 rounded-3 overflow-hidden bg-body-tertiary mb-3', _sdT('Picture'), [
                kit.img(seed, 800, 600, alt, 'object-fit-cover')
            ]),
            kit.h('h3', title, 'h5 fw-semibold mb-1'),
            kit.p(maker, 'small text-body-secondary mb-2'),
            kit.div('badge rounded-pill bg-primary-subtle text-primary-emphasis d-inline-flex align-items-center gap-1', _sdT('Room'), [
                kit.icon('bi-geo-alt'),
                kit.h('span', room)
            ])
        ]);
    };
    var buildTrail = function () {
        var head = kit.div('row g-4 align-items-end mb-5', _sdT('Trail Header'), [
            kit.div('col-lg-7', _sdT('Trail Title'), [
                eyebrow(_sdT('Highlights trail')),
                kit.h('h2', _sdT('Four works not to miss'), 'h2 fw-bold mb-3'),
                kit.p(_sdT('A walk through the galleries from the oldest work to the newest. Follow the numbers on the floor plan you get at the desk.'), 'text-body-secondary mb-0')
            ]),
            kit.div('col-lg-5 d-flex flex-wrap gap-3 justify-content-lg-end align-items-center', _sdT('Trail Details'), [
                kit.div('d-flex align-items-center gap-2 small', _sdT('Length'), [
                    kit.icon('bi-clock', 'text-primary'),
                    kit.h('span', _sdT('About an hour'))
                ]),
                kit.div('d-flex align-items-center gap-2 small', _sdT('Start'), [
                    kit.icon('bi-signpost-split', 'text-primary'),
                    kit.h('span', _sdT('Meet at the information desk'))
                ]),
                kit.link(_sdT('Highlights Tour'), '#', 'btn btn-primary px-4', _sdT('Tour Link'))
            ])
        ]);
        return kit.section('py-5', _sdT('Museum (Highlights Trail)'), [
            fx(head, { effect: 'fade', trigger: 'view', duration: 800 }),
            fx(kit.tag('ol', 'pg-museum-trail list-unstyled row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4 mb-0', _sdT('Stops'), [
                stop('1', 'museum-trail-coin', _sdT('A small silver coin stamped with an owl'), _sdT('Silver Coin with an Owl'),
                    _sdT('A mint of the ancient city') + ' · ' + _sdT('5th century BC'), _sdT('Gallery 1')),
                stop('2', 'museum-trail-harbour', _sdT('Fishing boats in a harbour under a pink evening sky'), _sdT('Harbour at Dusk'),
                    _sdT('A painter of the city\'s harbour school') + ' · ' + _sdT('Late 19th century'), _sdT('Gallery 3')),
                stop('3', 'museum-trail-dancer', _sdT('A bronze dancer sitting on a step to tie her shoe'), _sdT('Resting Dancer'),
                    _sdT('A sculptor trained in the city\'s academy') + ' · ' + _sdT('Early 20th century'), _sdT('Gallery 5')),
                stop('4', 'museum-trail-tile', _sdT('Blue, turquoise and red tiles painted with tulips'), _sdT('Tile Panel with Tulips'),
                    _sdT('Workshops of the region') + ' · ' + _sdT('16th century'), _sdT('Gallery 7'))
            ]), { effect: 'slide-up', trigger: 'view', stagger: 120 })
        ]);
    };

    return {
        prefix: 'museum-',
        group: { title: _sdT('Museum'), icon: 'bi-bank2' },
        items: [
            { label: _sdT('Museum: Exhibition Hero'),  type: 'component', icon: 'bi-easel',          extra: { componentType: 'museum-exhibition' } },
            { label: _sdT('Museum: Look Closer'),      type: 'component', icon: 'bi-zoom-in',        extra: { componentType: 'museum-object' } },
            { label: _sdT('Museum: Highlights Trail'), type: 'component', icon: 'bi-signpost-split', extra: { componentType: 'museum-trail' } }
        ],
        build: function (ctype) {
            if (ctype === 'museum-exhibition') return buildExhibition();
            if (ctype === 'museum-object') return buildObject();
            if (ctype === 'museum-trail') return buildTrail();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
