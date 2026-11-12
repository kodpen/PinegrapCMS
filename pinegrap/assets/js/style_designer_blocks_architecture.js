/**
 * Pinegrap - Enterprise Website Platform
 *
 * Architecture block family: the sections an architecture, interior design
 * or landscape studio's site needs that the general palette lacks. The
 * plate hero (the studio in a sentence above one wide photograph, captioned
 * like a plate in a monograph with the project it shows), the project sheet
 * (a project's picture beside its facts and its credits, as a studio
 * publishes a finished building) and the material board (the materials a
 * studio works with, each mounted like a sample on a board, with its finish
 * and where it was used). The services, the stages of work, the team, the
 * principles, the awards and press and the questions are general blocks
 * (Services, Steps: Numbered, Team, Features, Logos, FAQ).
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
        // A wide photograph captioned like a plate: a hairline above the
        // caption, the plate number in tabular figures.
        'pg-arch-plate':  '.pg-arch-plate figcaption { border-top: 1px solid var(--bs-border-color); } ' +
                          '.pg-arch-plate .pg-arch-no { font-variant-numeric: tabular-nums; letter-spacing: .08em; color: var(--bs-secondary-color); }',
        // A project's facts: the label beside its value from sm up, each
        // pair on a hairline.
        'pg-arch-facts':  '.pg-arch-facts { display: grid; margin: 0; } ' +
                          '.pg-arch-facts > div { display: grid; gap: .25rem 1rem; padding: .625rem 0; border-bottom: 1px solid var(--bs-border-color); } ' +
                          '.pg-arch-facts dt { font-weight: 400; color: var(--bs-secondary-color); } ' +
                          '.pg-arch-facts dd { margin: 0; } ' +
                          '@media (min-width: 576px) { .pg-arch-facts > div { grid-template-columns: 10rem minmax(0, 1fr); } }',
        // A material mounted on the board: a margin of board around the
        // sample, its edge darkening on hover and keyboard focus.
        'pg-arch-swatch': '.pg-arch-swatch { padding: .75rem; border: 1px solid var(--bs-border-color); background: var(--bs-body-bg); transition: border-color .2s ease; } ' +
                          '.pg-arch-swatch:hover, .pg-arch-swatch:focus-within { border-color: var(--bs-emphasis-color); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'architecture-hero':      ['pg-arch-plate'],
        'architecture-sheet':     ['pg-arch-facts'],
        'architecture-materials': ['pg-arch-swatch']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };

    // The small spaced line above a title.
    var eyebrow = function (text, cls) {
        return kit.p(text, 'small text-uppercase fw-semibold text-body-secondary ' + (cls || 'mb-2'));
    };

    // ── Plate hero ──────────────────────────────────────────────────────
    var buildHero = function () {
        var words = kit.row(_sdT('Hero Row'), [
            kit.col(_sdT('Hero Title'), [
                eyebrow(_sdT('Architecture and interiors'), 'mb-3'),
                kit.h('h1', _sdT('Buildings shaped by light, place and the people inside'), 'display-4 fw-semibold mb-0')
            ], '8'),
            kit.col(_sdT('Hero Text'), [
                kit.p(_sdT('A small studio of architects and interior designers, with one partner leading each project from the first sketch to the keys.'), 'text-body-secondary mb-4'),
                kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                    kit.link(_sdT('See Our Projects'), '#', 'btn btn-primary rounded-0 px-4', _sdT('Projects Link')),
                    kit.link(_sdT('Start a Project'), '#', 'btn btn-outline-secondary rounded-0 px-4', _sdT('Enquiry Link'))
                ])
            ], '4')
        ], 'g-4 g-lg-5 align-items-end mb-4 mb-lg-5');
        var plate = kit.tag('figure', 'pg-arch-plate mb-0', _sdT('Plate'), [
            kit.div('ratio ratio-21x9 overflow-hidden bg-body-tertiary', _sdT('Picture'), [
                kit.img('architecture-hero', 1800, 770, _sdT('The courtyard of a stone house in the late afternoon light'), 'object-fit-cover')
            ]),
            kit.tag('figcaption', 'd-flex flex-wrap align-items-baseline gap-2 gap-sm-4 pt-3 mt-3 small', _sdT('Plate Caption'), [
                kit.h('span', '01', 'pg-arch-no'),
                kit.h('span', _sdT('The Stone Courtyard House'), 'fw-semibold'),
                kit.h('span', _sdT('A family house in a hillside village'), 'text-body-secondary'),
                kit.link(_sdT('See the Project'), '#', 'ms-sm-auto link-body-emphasis fw-semibold', _sdT('Project Link'))
            ])
        ]);
        return kit.section('pt-4 pt-lg-5 pb-5 overflow-hidden', _sdT('Architecture (Hero)'), [
            fx(words, { effect: 'slide-up', trigger: 'load', duration: 700 }),
            fx(plate, { effect: 'fade', trigger: 'load', duration: 1200, delay: 200 })
        ]);
    };

    // ── Project sheet ───────────────────────────────────────────────────
    // A fact of the project: its label and its value on one hairline.
    var fact = function (label, value) {
        return kit.div('', label, [
            kit.h('dt', label),
            kit.h('dd', value)
        ]);
    };
    // A credit: the role, then who filled it.
    var credit = function (role, name) {
        return kit.tag('li', 'd-flex flex-wrap gap-2 mb-1', role, [
            kit.h('span', role, 'text-body-secondary'),
            kit.h('span', name)
        ]);
    };
    var buildSheet = function () {
        var picture = kit.col(_sdT('Project Picture'), [
            kit.tag('figure', 'mb-0', _sdT('Photograph'), [
                kit.div('ratio ratio-4x3 overflow-hidden bg-body-tertiary', _sdT('Picture'), [
                    kit.img('architecture-sheet', 1400, 1050, _sdT('The living room of a stone house opening onto its courtyard'), 'object-fit-cover')
                ]),
                kit.tag('figcaption', 'small text-body-secondary mt-2', _sdT('Photo Caption'), [
                    kit.h('span', _sdT('The living room opens onto the courtyard on three sides.'))
                ])
            ])
        ], '7');
        var body = kit.col(_sdT('Project Facts'), [
            eyebrow(_sdT('House')),
            kit.h('h2', _sdT('The Stone Courtyard House'), 'h2 fw-semibold mb-3'),
            kit.p(_sdT('A family house built around a courtyard, in the stone of the hillside it stands on, with deep walls that keep the rooms cool in summer.'), 'text-body-secondary mb-4'),
            fx(kit.tag('dl', 'pg-arch-facts border-top mb-4', _sdT('Facts'), [
                fact(_sdT('Location'), _sdT('A hillside village by the coast')),
                fact(_sdT('Type'), _sdT('House')),
                fact(_sdT('Floor Area'), _sdT('About 240 square metres')),
                fact(_sdT('Stage'), _sdT('Completed')),
                fact(_sdT('Client'), _sdT('A private family')),
                fact(_sdT('Materials'), _sdT('Local stone, oak and lime plaster'))
            ]), { effect: 'slide-up', trigger: 'view', stagger: 80 }),
            kit.h('h3', _sdT('Credits'), 'h6 text-uppercase fw-semibold text-body-secondary mb-2'),
            kit.tag('ul', 'list-unstyled small mb-0', _sdT('Credits'), [
                credit(_sdT('Architecture and interiors:'), _sdT('The studio')),
                credit(_sdT('Structural engineer:'), _sdT('A local engineering practice')),
                credit(_sdT('Builder:'), _sdT('A family firm of stonemasons')),
                credit(_sdT('Landscape:'), _sdT('The studio\'s landscape team'))
            ])
        ], '5');
        return kit.section('py-5', _sdT('Architecture (Project Sheet)'), [
            kit.row(_sdT('Project Row'), [
                fx(picture, { effect: 'fade', trigger: 'view', duration: 900 }),
                body
            ], 'g-4 g-lg-5')
        ]);
    };

    // ── Material board ──────────────────────────────────────────────────
    // A material: its sample, its name, its finish and where the studio
    // used it.
    var material = function (seed, title, alt, finish, used) {
        return kit.tag('li', 'col', title, [
            kit.div('pg-arch-swatch h-100', _sdT('Sample'), [
                kit.div('ratio ratio-1x1 overflow-hidden bg-body-tertiary mb-3', _sdT('Picture'), [
                    kit.img('architecture-' + seed, 600, 600, alt, 'object-fit-cover')
                ]),
                kit.h('h3', title, 'h6 fw-semibold mb-1'),
                kit.p(finish, 'small text-body-secondary mb-2'),
                kit.p(used, 'small mb-0')
            ])
        ]);
    };
    var buildMaterials = function () {
        return kit.section('py-5 bg-body-tertiary', _sdT('Architecture (Material Board)'), [
            kit.div('col-lg-8 mb-5', _sdT('Section Header'), [
                eyebrow(_sdT('Materials')),
                kit.h('h2', _sdT('What our buildings are made of'), 'h2 fw-semibold mb-3'),
                kit.p(_sdT('A few honest materials, chosen for the place, the climate and how they age, and used again from one project to the next.'), 'text-body-secondary mb-0')
            ]),
            fx(kit.tag('ul', 'list-unstyled row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3 mb-0', _sdT('Materials'), [
                material('stone', _sdT('Local stone'), _sdT('A wall of rough local stone'), _sdT('Split face, laid dry'), _sdT('Walls and garden terraces')),
                material('oak', _sdT('Oak'), _sdT('Oiled oak boards'), _sdT('Oiled, wide boards'), _sdT('Floors, stairs and joinery')),
                material('lime', _sdT('Lime plaster'), _sdT('A wall of warm white lime plaster'), _sdT('Hand-trowelled, warm white'), _sdT('Walls and ceilings')),
                material('brick', _sdT('Handmade brick'), _sdT('Handmade bricks in a long bond'), _sdT('Long bond, pale mortar'), _sdT('Facades and courtyards')),
                material('terrazzo', _sdT('Terrazzo'), _sdT('A polished terrazzo floor'), _sdT('Polished, local aggregate'), _sdT('Kitchens and bathrooms')),
                material('brass', _sdT('Brass'), _sdT('Brushed brass handles'), _sdT('Brushed, left to darken'), _sdT('Handles, lamps and rails'))
            ]), { effect: 'slide-up', trigger: 'view', stagger: 80 })
        ]);
    };

    return {
        prefix: 'architecture-',
        group: { title: _sdT('Architecture'), icon: 'bi-rulers' },
        items: [
            { label: _sdT('Architecture: Plate Hero'),     type: 'component', icon: 'bi-image',       extra: { componentType: 'architecture-hero' },
              desc: _sdT('A headline, a short text and two buttons over a wide photograph captioned like a plate, with its number and a project link.') },
            { label: _sdT('Architecture: Project Sheet'),  type: 'component', icon: 'bi-card-list',   extra: { componentType: 'architecture-sheet' },
              desc: _sdT('A project photograph beside its name, a short text, a list of facts on hairlines and the credits of who built it.') },
            { label: _sdT('Architecture: Material Board'), type: 'component', icon: 'bi-grid-3x2',    extra: { componentType: 'architecture-materials' },
              desc: _sdT('Six material samples in a grid on a tinted band, each a square photo with its name, its finish and where it was used.') }
        ],
        build: function (ctype) {
            if (ctype === 'architecture-hero') return buildHero();
            if (ctype === 'architecture-sheet') return buildSheet();
            if (ctype === 'architecture-materials') return buildMaterials();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
