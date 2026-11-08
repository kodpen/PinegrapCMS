/**
 * Pinegrap - Enterprise Website Platform
 *
 * School block family: the sections a nursery's, a kindergarten's or a
 * primary school's site needs that the general palette lacks. The school
 * hero (the school in a sentence beside a picture, the next open morning
 * on it, and a way into each programme by the child's age), the age
 * ladder (every programme laid on one age scale, so a family sees the
 * path from the first day of nursery to the last day of primary, with the
 * class groups under it) and the school year (its terms side by side,
 * what each brings and the holiday after it). A day hour by hour, the
 * admission steps, the staff, the fees, the campus and the questions are
 * general blocks (Timeline: Vertical, Steps: Numbered, Team, Features,
 * FAQ).
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
        // A way into each programme by age: a bordered tile whose edge
        // takes the theme colour on hover and keyboard focus.
        'pg-school-finder': '.pg-school-finder li { border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); background: var(--bs-body-bg); transition: border-color .2s ease; } ' +
                            '.pg-school-finder li:hover, .pg-school-finder li:focus-within { border-color: var(--bs-primary); }',
        // One age scale from 2 to 10 in eight columns from lg up: a
        // programme spans from the age it starts at to the age it ends at;
        // the scale's own ticks are its div children.
        'pg-school-ages':   '.pg-school-ages { display: grid; gap: .75rem; } ' +
                            '.pg-school-ages > div { border-inline-start: 1px solid var(--bs-border-color); padding-inline-start: .375rem; color: var(--bs-secondary-color); } ' +
                            '@media (min-width: 992px) { .pg-school-ages { grid-template-columns: repeat(8, minmax(0, 1fr)); } ' +
                            '.pg-school-from-2 { grid-column-start: 1; } .pg-school-from-3 { grid-column-start: 2; } .pg-school-from-4 { grid-column-start: 3; } .pg-school-from-5 { grid-column-start: 4; } ' +
                            '.pg-school-from-6 { grid-column-start: 5; } .pg-school-from-7 { grid-column-start: 6; } .pg-school-from-8 { grid-column-start: 7; } .pg-school-from-9 { grid-column-start: 8; } ' +
                            '.pg-school-to-3 { grid-column-end: 2; } .pg-school-to-4 { grid-column-end: 3; } .pg-school-to-5 { grid-column-end: 4; } .pg-school-to-6 { grid-column-end: 5; } ' +
                            '.pg-school-to-7 { grid-column-end: 6; } .pg-school-to-8 { grid-column-end: 7; } .pg-school-to-9 { grid-column-end: 8; } .pg-school-to-10 { grid-column-end: 9; } }',
        // A term's card: a band of the theme colour along its top.
        'pg-school-term':   '.pg-school-term { border-top: .375rem solid var(--bs-primary); }',
        // The holiday after a term: a dashed line above quieter words.
        'pg-school-break':  '.pg-school-break { border-top: 1px dashed var(--bs-border-color); color: var(--bs-secondary-color); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'school-hero': ['pg-school-finder'],
        'school-ages': ['pg-school-ages'],
        'school-year': ['pg-school-term', 'pg-school-break']
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

    // The programmes of the sample school: icon, name, ages in words and
    // the ages a programme starts and ends at on the scale.
    var programmes = function () {
        return [
            { icon: 'bi-balloon-heart', title: _sdT('Nursery'),            ages: _sdT('Ages 2 to 3'), from: 2, to: 3,  main: true },
            { icon: 'bi-palette',       title: _sdT('Kindergarten'),       ages: _sdT('Ages 3 to 6'), from: 3, to: 6,  main: true },
            { icon: 'bi-book',          title: _sdT('Primary School'),     ages: _sdT('Ages 6 to 10'), from: 6, to: 10, main: true },
            { icon: 'bi-puzzle',        title: _sdT('After-School Clubs'), ages: _sdT('Ages 4 to 10'), from: 4, to: 10 },
            { icon: 'bi-sun',           title: _sdT('Summer School'),      ages: _sdT('Ages 3 to 10'), from: 3, to: 10 }
        ];
    };

    // ── School hero ─────────────────────────────────────────────────────
    // A programme in the age finder: its ages, then its name as the link
    // that makes the whole tile a way in.
    var finderItem = function (p) {
        return kit.tag('li', 'position-relative px-3 py-2', p.title, [
            kit.h('span', p.ages, 'd-block small text-body-secondary'),
            kit.link(p.title, '#', 'stretched-link fw-semibold text-body text-decoration-none', _sdT('Programme Link'))
        ]);
    };
    var buildHero = function () {
        var text = kit.col(_sdT('Hero Text'), [
            eyebrow(_sdT('Nursery, kindergarten and primary school'), 'mb-3'),
            kit.h('h1', _sdT('A school where every child is known'), 'display-4 fw-bold mb-3'),
            kit.p(_sdT('A private nursery, kindergarten and primary school with small groups, a big garden and teachers who know every child.'), 'lead text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap gap-2 mb-4', _sdT('Buttons'), [
                kit.link(_sdT('Apply Now'), '#', 'btn btn-primary btn-lg rounded-pill px-4', _sdT('Apply Link')),
                kit.link(_sdT('Book a School Visit'), '#', 'btn btn-outline-secondary btn-lg rounded-pill px-4', _sdT('Visit Link'))
            ]),
            kit.tag('nav', 'pg-school-finder', _sdT('Programmes by Age'), [
                kit.p(_sdT('Find a programme by your child\'s age'), 'small fw-semibold mb-2'),
                kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', _sdT('Programmes'), programmes().map(finderItem))
            ], [{ name: 'aria-label', value: _sdT('Programmes by Age') }])
        ], '6');
        var picture = kit.col(_sdT('Hero Picture'), [
            kit.div('position-relative', _sdT('Picture Frame'), [
                kit.div('ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', _sdT('Picture'), [
                    kit.img('school-hero', 1400, 1050, _sdT('The school garden with a vegetable patch and a wooden playhouse'), 'object-fit-cover')
                ]),
                kit.div('position-absolute bottom-0 start-0 m-3 p-3 rounded-4 bg-body shadow d-flex align-items-center gap-3 col-10 col-sm-8', _sdT('Open Morning Card'), [
                    kit.div('d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-primary-subtle text-primary-emphasis p-2 lh-1', _sdT('Icon Box'), [
                        kit.icon('bi-door-open', 'fs-4')
                    ]),
                    kit.div('', _sdT('Open Morning'), [
                        kit.p(_sdT('Open Morning for New Families'), 'fw-semibold mb-0'),
                        kit.p(_sdT('A Saturday morning each month, 10:00 to 12:00'), 'small text-body-secondary mb-0')
                    ])
                ])
            ])
        ], '6');
        return kit.section('py-5 overflow-hidden', _sdT('School (Hero)'), [
            kit.row(_sdT('Hero Row'), [
                fx(text, { effect: 'slide-up', trigger: 'load', duration: 700 }),
                fx(picture, { effect: 'fade', trigger: 'load', duration: 1000, delay: 200 })
            ], 'align-items-center g-4 g-lg-5')
        ]);
    };

    // ── Age ladder ──────────────────────────────────────────────────────
    // A programme on the scale: it spans its ages from lg up; the path
    // every child walks (nursery, kindergarten, primary) is tinted.
    var rung = function (p) {
        var card = p.main ? 'bg-primary-subtle border-primary-subtle' : 'bg-body';
        return kit.tag('li', 'pg-school-from-' + p.from + ' pg-school-to-' + p.to, p.title, [
            kit.div('position-relative h-100 p-3 rounded-4 border ' + card, _sdT('Programme Card'), [
                kit.icon(p.icon, 'd-block fs-4 text-primary mb-2'),
                kit.tag('h3', 'h6 mb-1', _sdT('Title'), [
                    kit.link(p.title, '#', 'stretched-link text-body text-decoration-none', _sdT('Programme Link'))
                ]),
                kit.h('span', p.ages, 'small text-body-secondary')
            ])
        ]);
    };
    // A tick of the scale: the age at its left edge; the last tick also
    // carries the scale's end.
    var tick = function (age) {
        var kids = [kit.h('span', String(age))];
        if (age === 9) kids.push(kit.h('span', '10'));
        return kit.div('pg-school-from-' + age + ' pg-school-to-' + (age + 1) + ' small d-flex justify-content-between pe-1', _sdT('Age {var}', age), kids);
    };
    var group = function (name, ages) {
        return kit.tag('li', 'd-flex align-items-center gap-2 px-3 py-2 rounded-pill border bg-body small', name, [
            kit.h('span', name, 'fw-semibold'),
            kit.h('span', ages, 'text-body-secondary')
        ]);
    };
    var buildAges = function () {
        var ticks = [];
        for (var age = 2; age <= 9; age++) ticks.push(tick(age));
        return kit.section('py-5', _sdT('School (Age Ladder)'), [
            kit.div('text-center col-lg-8 mx-auto mb-5', _sdT('Section Header'), [
                eyebrow(_sdT('From two to ten')),
                kit.h('h2', _sdT('Our programmes'), 'display-6 fw-bold mb-3'),
                kit.p(_sdT('One school from the first day of nursery to the last day of primary, with clubs after school and weeks of summer school.'), 'lead text-body-secondary mb-0')
            ]),
            kit.div('pg-school-ages d-none d-lg-grid mb-2', _sdT('Age Scale'), ticks, [{ name: 'aria-hidden', value: 'true' }]),
            fx(kit.tag('ol', 'pg-school-ages list-unstyled mb-5', _sdT('Programmes'), programmes().map(rung)),
                { effect: 'slide-up', trigger: 'view', stagger: 100 }),
            kit.div('text-center', _sdT('Class Groups'), [
                kit.h('h3', _sdT('Our class groups'), 'h6 text-uppercase fw-semibold text-body-secondary mb-3'),
                kit.tag('ul', 'list-unstyled d-flex flex-wrap justify-content-center gap-2 mb-0', _sdT('Groups'), [
                    group(_sdT('Acorns'), _sdT('Ages 2 to 3')),
                    group(_sdT('Saplings'), _sdT('Ages 3 to 4')),
                    group(_sdT('Oaks'), _sdT('Ages 4 to 5')),
                    group(_sdT('Pines'), _sdT('Ages 5 to 6')),
                    group(_sdT('Years 1 and 2'), _sdT('Ages 6 to 8')),
                    group(_sdT('Years 3 and 4'), _sdT('Ages 8 to 10'))
                ])
            ])
        ]);
    };

    // ── School year ─────────────────────────────────────────────────────
    // A term: how long it is, its name, a line, what it brings, the report
    // home at its end and the holiday after it.
    var term = function (n, length, title, text, items, report, rest) {
        var kids = [
            kit.p(length, 'small text-uppercase fw-semibold text-primary mb-2'),
            kit.h('h3', title, 'h4 mb-2'),
            kit.p(text, 'text-body-secondary mb-3'),
            kit.tag('ul', 'list-unstyled mb-3', _sdT('Highlights'), items.map(function (t, i) {
                return kit.tag('li', 'd-flex gap-2 mb-2', _sdT('Highlight {var}', i + 1), [
                    kit.icon('bi-star', 'text-primary flex-shrink-0'),
                    kit.h('span', t)
                ]);
            })),
            kit.div('small fw-semibold d-flex gap-2 mt-auto', _sdT('Report'), [
                kit.icon('bi-file-earmark-text', 'text-primary flex-shrink-0'),
                kit.h('span', report)
            ])
        ];
        var card = [kit.div('pg-school-term d-flex flex-column h-100 p-4 rounded-4 bg-body shadow-sm', _sdT('Term Card'), kids)];
        if (rest) {
            card.push(kit.div('pg-school-break d-flex align-items-center gap-2 mt-3 pt-3 small', _sdT('Holiday'), [
                kit.icon(rest.icon, 'flex-shrink-0'),
                kit.h('span', rest.text)
            ]));
        }
        return kit.tag('li', 'col d-flex flex-column', _sdT('Term {var}', n), card);
    };
    var buildYear = function () {
        return kit.section('py-5 bg-body-tertiary', _sdT('School (School Year)'), [
            kit.div('text-center col-lg-8 mx-auto mb-5', _sdT('Section Header'), [
                eyebrow(_sdT('The school year')),
                kit.h('h2', _sdT('A year in three terms'), 'display-6 fw-bold mb-3'),
                kit.p(_sdT('Each term has its projects, its celebrations and a word home at its end, with a holiday between them.'), 'lead text-body-secondary mb-0')
            ]),
            fx(kit.tag('ol', 'list-unstyled row row-cols-1 row-cols-lg-3 g-4 mb-4', _sdT('Terms'), [
                term(1, _sdT('About fourteen weeks'), _sdT('Autumn term'),
                    _sdT('New classes settle in and the year\'s first projects begin.'),
                    [_sdT('Settling in for new children'), _sdT('Harvest from the vegetable patch'), _sdT('A meeting with every family')],
                    _sdT('A short note on how your child has settled'),
                    { icon: 'bi-snow', text: _sdT('Then a winter holiday of about two weeks') }),
                term(2, _sdT('About twelve weeks'), _sdT('Spring term'),
                    _sdT('The longest projects of the year, and the stage for the school show.'),
                    [_sdT('Science week'), _sdT('Book week and a visiting author'), _sdT('The school show')],
                    _sdT('A meeting with the class teacher'),
                    { icon: 'bi-flower1', text: _sdT('Then a spring holiday of one week') }),
                term(3, _sdT('About eleven weeks'), _sdT('Summer term'),
                    _sdT('Days outdoors, trips close to the school and a goodbye to the oldest children.'),
                    [_sdT('Sports day'), _sdT('Trips close to the school'), _sdT('Moving-up day for every class')],
                    _sdT('The written report of the year'),
                    null)
            ]), { effect: 'slide-up', trigger: 'view', stagger: 120 }),
            kit.div('d-flex flex-column flex-sm-row align-items-sm-center justify-content-center gap-3 text-center', _sdT('Year Foot'), [
                kit.p(_sdT('In the long holiday, summer school is open to every child.'), 'small text-body-secondary mb-0'),
                kit.link(_sdT('Summer School'), '#', 'btn btn-outline-primary rounded-pill px-4 text-nowrap', _sdT('Summer School Link'))
            ])
        ]);
    };

    return {
        prefix: 'school-',
        group: { title: _sdT('School'), icon: 'bi-backpack' },
        items: [
            { label: _sdT('School: Hero'),        type: 'component', icon: 'bi-backpack',      extra: { componentType: 'school-hero' } },
            { label: _sdT('School: Age Ladder'),  type: 'component', icon: 'bi-bar-chart-steps', extra: { componentType: 'school-ages' } },
            { label: _sdT('School: School Year'), type: 'component', icon: 'bi-calendar3',     extra: { componentType: 'school-year' } }
        ],
        build: function (ctype) {
            if (ctype === 'school-hero') return buildHero();
            if (ctype === 'school-ages') return buildAges();
            if (ctype === 'school-year') return buildYear();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
