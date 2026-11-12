/**
 * Pinegrap - Enterprise Website Platform
 *
 * Construction block family: the sections a building contractor's site
 * needs that the general palette lacks. The site board hero (a site
 * photograph with the firm's board laid over its edge, striped like a site
 * barrier, with the facts a site board shows), the project progress (one
 * project's stages from groundworks to handover, the stage on site now and
 * the share done) and the neighbour notice (the notice a contractor puts up
 * for the street: what is being built, the working hours, deliveries and
 * who to call). The services, the sectors, the process, the team, the
 * figures, the clients' words and the questions are general blocks
 * (Services, Steps: Numbered, Team, Stats, Testimonials, FAQ).
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
        // The diagonal stripes of a site barrier, in the theme's primary
        // colour on its emphasis colour.
        'pg-cn-stripe': '.pg-cn-stripe { height: .5rem; background-image: repeating-linear-gradient(-45deg, var(--bs-primary) 0 .75rem, var(--bs-emphasis-color) .75rem 1.5rem); }',
        // The firm's board laid over the photograph's edge from lg up; the
        // facts in two columns from sm up.
        'pg-cn-board':  '.pg-cn-board { position: relative; background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); } ' +
                        '.pg-cn-board dl { display: grid; gap: .75rem 1.5rem; margin: 0; } ' +
                        '.pg-cn-board dt { font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; color: var(--bs-secondary-color); } ' +
                        '.pg-cn-board dd { margin: 0; } ' +
                        '@media (min-width: 576px) { .pg-cn-board dl { grid-template-columns: repeat(2, minmax(0, 1fr)); } } ' +
                        '@media (min-width: 992px) { .pg-cn-board { margin-left: -6rem; box-shadow: 0 1rem 2rem rgba(var(--bs-emphasis-color-rgb), .15); } }',
        // A project's stages on one line down the left: a dot per stage,
        // filled when done, ringed while the crew is on site.
        'pg-cn-stages': '.pg-cn-stages > li { position: relative; padding: 0 0 1.5rem 2.75rem; } ' +
                        '.pg-cn-stages > li::before { content: ""; position: absolute; left: .875rem; top: 1.75rem; bottom: 0; border-left: 2px solid var(--bs-border-color); } ' +
                        '.pg-cn-stages > li:last-child { padding-bottom: 0; } ' +
                        '.pg-cn-stages > li:last-child::before { display: none; } ' +
                        '.pg-cn-stages .pg-cn-dot { position: absolute; left: 0; top: 0; display: flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: 50%; border: 2px solid var(--bs-border-color); background: var(--bs-body-bg); color: var(--bs-secondary-color); } ' +
                        '.pg-cn-stages .pg-cn-done .pg-cn-dot { border-color: var(--bs-primary); background: var(--bs-primary); color: var(--bs-body-bg); } ' +
                        '.pg-cn-stages .pg-cn-now .pg-cn-dot { border-color: var(--bs-primary); color: var(--bs-primary); box-shadow: 0 0 0 .25rem rgba(var(--bs-primary-rgb), .2); }',
        // The street notice: a framed sheet under the barrier stripe, the
        // times in tabular figures.
        'pg-cn-notice': '.pg-cn-notice { border: 2px solid var(--bs-emphasis-color); background: var(--bs-body-bg); } ' +
                        '.pg-cn-notice .pg-cn-hours { font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'construction-hero':     ['pg-cn-stripe', 'pg-cn-board'],
        'construction-progress': ['pg-cn-stages'],
        'construction-notice':   ['pg-cn-stripe', 'pg-cn-notice']
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

    // The barrier stripe: decoration only.
    var stripe = function () {
        return kit.div('pg-cn-stripe', _sdT('Stripe'), [], [{ name: 'aria-hidden', value: 'true' }]);
    };

    // A fact on a board or a notice: its label and its value.
    var fact = function (label, value, cls) {
        return kit.div('', label, [
            kit.h('dt', label),
            kit.h('dd', value, cls || '')
        ]);
    };

    // ── Site board hero ─────────────────────────────────────────────────
    var buildHero = function () {
        var picture = kit.col(_sdT('Site Photograph'), [
            kit.div('ratio ratio-4x3 overflow-hidden bg-body-tertiary', _sdT('Picture'), [
                kit.img('construction-hero', 1600, 1200, _sdT('A new school building in its frame, with a crane over the site'), 'object-fit-cover')
            ])
        ], '7');
        var board = kit.col(_sdT('Site Board'), [
            kit.div('pg-cn-board', _sdT('Board'), [
                stripe(),
                kit.div('p-4 p-lg-5', _sdT('Board Body'), [
                    eyebrow(_sdT('Builders and contractors'), 'mb-3'),
                    kit.h('h1', _sdT('We build what we promise, on time and on budget'), 'display-5 fw-bold mb-3'),
                    kit.p(_sdT('Homes, schools and workplaces built by our own crews, with one site manager you can call from the first visit to the handover.'), 'text-body-secondary mb-4'),
                    kit.div('d-flex flex-wrap gap-2 mb-4', _sdT('Buttons'), [
                        kit.link(_sdT('Request a Quote'), '#', 'btn btn-primary rounded-0 px-4', _sdT('Quote Link')),
                        kit.link(_sdT('See Our Projects'), '#', 'btn btn-outline-secondary rounded-0 px-4', _sdT('Projects Link'))
                    ]),
                    kit.tag('dl', 'border-top pt-4', _sdT('Board Facts'), [
                        fact(_sdT('We build'), _sdT('New builds, extensions and refurbishments')),
                        fact(_sdT('Site visit'), _sdT('Free, within a week of your call')),
                        fact(_sdT('Our builders'), _sdT('Employed by us, never passed on')),
                        fact(_sdT('Every site'), _sdT('Insured, fenced and kept tidy'))
                    ])
                ])
            ])
        ], '5');
        return kit.section('py-4 py-lg-5 overflow-hidden', _sdT('Construction (Hero)'), [
            kit.row(_sdT('Hero Row'), [
                fx(picture, { effect: 'fade', trigger: 'load', duration: 1000 }),
                fx(board, { effect: 'slide-up', trigger: 'load', duration: 700, delay: 200 })
            ], 'g-0 align-items-center')
        ]);
    };

    // ── Project progress ────────────────────────────────────────────────
    // A stage of the build: its state ('done', 'now' or 'next'), its name
    // and what happens in it.
    var stage = function (state, title, text) {
        var marks = {
            done: { icon: 'bi-check-lg', word: _sdT('Done'), badge: 'text-bg-primary' },
            now:  { icon: 'bi-cone-striped', word: _sdT('On site now'), badge: 'text-bg-warning' },
            next: { icon: 'bi-circle', word: _sdT('Coming next'), badge: 'text-bg-secondary' }
        };
        var m = marks[state];
        return kit.tag('li', 'pg-cn-' + state, title, [
            kit.div('pg-cn-dot', _sdT('Stage Mark'), [kit.icon(m.icon, '')], [{ name: 'aria-hidden', value: 'true' }]),
            kit.div('d-flex flex-wrap align-items-center gap-2 mb-1', _sdT('Stage Head'), [
                kit.h('h3', title, 'h6 fw-semibold mb-0'),
                kit.h('span', m.word, 'badge rounded-0 ' + m.badge)
            ]),
            kit.p(text, 'small text-body-secondary mb-0')
        ]);
    };
    var buildProgress = function () {
        var about = kit.col(_sdT('Project'), [
            eyebrow(_sdT('On site now')),
            kit.h('h2', _sdT('The New Primary School'), 'h2 fw-bold mb-3'),
            kit.p(_sdT('A two-storey school for four hundred children, built while the old school next door stays open.'), 'text-body-secondary mb-4'),
            kit.div('d-flex justify-content-between small fw-semibold mb-2', _sdT('Progress Head'), [
                kit.h('span', _sdT('Work done')),
                kit.h('span', '50%')
            ]),
            kit.div('progress rounded-0 mb-4', _sdT('Progress Bar'), [
                kit.div('progress-bar w-50', _sdT('Progress'))
            ], [
                { name: 'role', value: 'progressbar' },
                { name: 'aria-label', value: _sdT('Work done') },
                { name: 'aria-valuenow', value: '50' },
                { name: 'aria-valuemin', value: '0' },
                { name: 'aria-valuemax', value: '100' }
            ]),
            kit.div('ratio ratio-16x9 overflow-hidden bg-body-tertiary', _sdT('Picture'), [
                kit.img('construction-progress', 1200, 675, _sdT('Roofers laying the roof of a new school building'), 'object-fit-cover')
            ]),
            kit.p(_sdT('The latest photograph from the site.'), 'small text-body-secondary mt-2 mb-0')
        ], '5');
        var stages = kit.col(_sdT('Stages'), [
            fx(kit.tag('ol', 'pg-cn-stages list-unstyled mb-0', _sdT('Stages'), [
                stage('done', _sdT('Groundworks'), _sdT('The site cleared, the foundations and drains laid.')),
                stage('done', _sdT('Structural frame'), _sdT('The steel frame and the floors in place.')),
                stage('now', _sdT('Roof and walls'), _sdT('The roof going on and the walls closing the building in.')),
                stage('next', _sdT('Services and fit-out'), _sdT('Heating, wiring, plumbing, then the classrooms finished.')),
                stage('next', _sdT('Handover'), _sdT('Checked with the school, the keys handed over, the site cleared.'))
            ]), { effect: 'slide-up', trigger: 'view', stagger: 100 })
        ], '7');
        return kit.section('py-5', _sdT('Construction (Project Progress)'), [
            kit.row(_sdT('Progress Row'), [
                fx(about, { effect: 'fade', trigger: 'view', duration: 800 }),
                stages
            ], 'g-4 g-lg-5')
        ]);
    };

    // ── Neighbour notice ────────────────────────────────────────────────
    // A fact of the notice, half the width from sm up.
    var notice = function (label, value, cls) {
        return kit.div('col-sm-6', label, [
            kit.h('dt', label, 'small text-uppercase text-body-secondary'),
            kit.h('dd', value, cls)
        ]);
    };
    var buildNotice = function () {
        var sheet = kit.div('pg-cn-notice', _sdT('Notice'), [
            stripe(),
            kit.div('p-4 p-lg-5', _sdT('Notice Body'), [
                kit.div('d-flex align-items-center gap-3 mb-3', _sdT('Notice Head'), [
                    kit.icon('bi-cone-striped', 'text-primary', '2rem'),
                    kit.h('h2', _sdT('Building work on your street'), 'h3 fw-bold mb-0')
                ]),
                kit.p(_sdT('We are building six new homes on the corner plot. Thank you for your patience while we work; here is what to expect.'), 'mb-4'),
                kit.tag('dl', 'row g-3 mb-4', _sdT('Notice Facts'), [
                    notice(_sdT('Working hours'), _sdT('Monday to Friday, 8:00 to 18:00; Saturday, 8:00 to 13:00'), 'pg-cn-hours mb-0'),
                    notice(_sdT('Deliveries'), _sdT('Between 9:30 and 15:00, away from the school run'), 'pg-cn-hours mb-0'),
                    notice(_sdT('Noisy work'), _sdT('Kept to weekday mornings, never on Sundays'), 'mb-0'),
                    notice(_sdT('How long'), _sdT('About ten months'), 'mb-0')
                ]),
                kit.div('d-flex flex-wrap align-items-center gap-3 border-top pt-4', _sdT('Notice Contact'), [
                    kit.p(_sdT('A question or a problem? Call the site manager.'), 'fw-semibold mb-0 me-auto'),
                    kit.link(_sdT('Call the Site'), '#', 'btn btn-primary rounded-0 px-4', _sdT('Phone Link'))
                ])
            ])
        ]);
        return kit.section('py-5 bg-body-tertiary', _sdT('Construction (Neighbour Notice)'), [
            kit.div('col-lg-9 col-xl-8 mx-auto', _sdT('Notice Column'), [
                fx(sheet, { effect: 'slide-up', trigger: 'view', duration: 700 })
            ])
        ]);
    };

    return {
        prefix: 'construction-',
        group: { title: _sdT('Construction'), icon: 'bi-bricks' },
        items: [
            { label: _sdT('Construction: Site Board Hero'),   type: 'component', icon: 'bi-image',       extra: { componentType: 'construction-hero' },
              desc: _sdT('A site photo beside a striped board with the headline, two square buttons and four facts about how the firm builds.') },
            { label: _sdT('Construction: Project Progress'),  type: 'component', icon: 'bi-bar-chart-steps', extra: { componentType: 'construction-progress' },
              desc: _sdT('A project text, a progress bar and the latest site photo, beside five stages marked done, on site now or coming next.') },
            { label: _sdT('Construction: Neighbour Notice'),  type: 'component', icon: 'bi-signpost',    extra: { componentType: 'construction-notice' },
              desc: _sdT('A striped notice to neighbours: working hours, deliveries, noisy work, how long it lasts and a button to call the site.') }
        ],
        build: function (ctype) {
            if (ctype === 'construction-hero') return buildHero();
            if (ctype === 'construction-progress') return buildProgress();
            if (ctype === 'construction-notice') return buildNotice();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
