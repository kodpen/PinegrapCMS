/**
 * Pinegrap - Enterprise Website Platform
 *
 * City Hall block family: the sections a city hall's or a municipality's
 * site needs that the general palette lacks. The task hero (the page's
 * title above the things residents come to do, each a tile of its own, and
 * the citizen line), the service guide (one service as a resident reads it
 * before going: who applies, where, how long it takes, what it costs, the
 * documents to bring and the department in charge) and the council agenda
 * (the next council session, open to the public, beside its numbered
 * agenda items, each with the committee that brought it and where it
 * stands). The service areas, the departments, the service points, the
 * numbers, the mayor and the council members and the questions are
 * general blocks (Services, Contact, Hours & Locations, Team, FAQ).
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
        // A task tile: the whole tile is the link (stretched), its edge
        // and its arrow follow hover and keyboard focus.
        'pg-cityhall-task':   '.pg-cityhall-task { position: relative; border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); background: var(--bs-body-bg); transition: border-color .2s ease, box-shadow .2s ease; } ' +
                              '.pg-cityhall-task:hover, .pg-cityhall-task:focus-within { border-color: var(--bs-primary); box-shadow: 0 .25rem .75rem rgba(var(--bs-body-color-rgb), .08); } ' +
                              '.pg-cityhall-task .pg-cityhall-go { color: var(--bs-primary); transition: transform .2s ease; } ' +
                              '.pg-cityhall-task:hover .pg-cityhall-go, .pg-cityhall-task:focus-within .pg-cityhall-go { transform: translateX(.25rem); } ' +
                              '@media (prefers-reduced-motion: reduce) { .pg-cityhall-task .pg-cityhall-go { transition: none; } }',
        // A service's facts: the label above its value, side by side
        // from sm up, each pair on a hairline.
        'pg-cityhall-guide':  '.pg-cityhall-guide { display: grid; margin: 0; } ' +
                              '.pg-cityhall-guide > div { display: grid; gap: .25rem 1rem; padding: .75rem 0; border-bottom: 1px solid var(--bs-border-color); } ' +
                              '.pg-cityhall-guide dt { font-weight: 600; color: var(--bs-secondary-color); } ' +
                              '.pg-cityhall-guide dd { margin: 0; } ' +
                              '@media (min-width: 576px) { .pg-cityhall-guide > div { grid-template-columns: 11rem minmax(0, 1fr); } }',
        // The agenda: each item's number in tabular figures on a rule
        // that joins the items.
        'pg-cityhall-agenda': '.pg-cityhall-agenda > li { position: relative; padding-left: 3.5rem; padding-bottom: 1.5rem; } ' +
                              '.pg-cityhall-agenda > li::before { content: ""; position: absolute; left: 1.125rem; top: 2.5rem; bottom: 0; border-left: 1px solid var(--bs-border-color); } ' +
                              '.pg-cityhall-agenda > li:last-child { padding-bottom: 0; } ' +
                              '.pg-cityhall-agenda > li:last-child::before { content: none; } ' +
                              '.pg-cityhall-agenda .pg-cityhall-no { position: absolute; left: 0; top: 0; width: 2.25rem; height: 2.25rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'cityhall-hero':    ['pg-cityhall-task'],
        'cityhall-service': ['pg-cityhall-guide'],
        'cityhall-council': ['pg-cityhall-agenda']
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

    // ── Task hero ───────────────────────────────────────────────────────
    // A task: its icon, its name as the tile's link and a line on it.
    var task = function (icon, title, text) {
        return kit.tag('li', 'col', title, [
            kit.div('pg-cityhall-task h-100 d-flex align-items-start gap-3 p-3', _sdT('Task'), [
                kit.icon(icon, 'fs-4 text-primary flex-shrink-0'),
                kit.div('flex-grow-1', _sdT('Task Text'), [
                    kit.link(title, '#', 'stretched-link fw-semibold link-body-emphasis text-decoration-none', _sdT('Task Link')),
                    kit.p(text, 'small text-body-secondary mb-0 mt-1')
                ]),
                kit.icon('bi-arrow-right', 'pg-cityhall-go flex-shrink-0')
            ])
        ]);
    };
    var buildHero = function () {
        var words = kit.div('col-lg-8 mb-4 mb-lg-5', _sdT('Hero Title'), [
            eyebrow(_sdT('City Hall'), 'mb-3'),
            kit.h('h1', _sdT('How can we help you today?'), 'display-5 fw-bold mb-3'),
            kit.p(_sdT('Your city hall: services, notices and the council\'s work, open to every resident'), 'lead text-body-secondary mb-0')
        ]);
        var tasks = kit.tag('nav', '', _sdT('Common tasks'), [
            fx(kit.tag('ul', 'list-unstyled row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3 mb-0', _sdT('Tasks'), [
                task('bi-exclamation-triangle', _sdT('Report a problem'), _sdT('A pothole, a dark street or rubbish left behind')),
                task('bi-calendar-check', _sdT('Book a meeting'), _sdT('Meet the mayor or a department')),
                task('bi-megaphone', _sdT('Public Notices'), _sdT('Tenders, council decisions and vacancies')),
                task('bi-people', _sdT('Council meetings'), _sdT('The agenda and the decisions')),
                task('bi-geo-alt', _sdT('Service points'), _sdT('Where to go and when they are open')),
                task('bi-telephone', _sdT('Useful numbers'), _sdT('Faults, emergencies and the citizen line'))
            ]), { effect: 'slide-up', trigger: 'load', stagger: 80, delay: 200 })
        ], [{ name: 'aria-label', value: _sdT('Common tasks') }]);
        var line = kit.div('d-flex flex-wrap align-items-center gap-2 gap-sm-3 mt-4 pt-4 border-top', _sdT('Citizen line'), [
            kit.icon('bi-headset', 'fs-4 text-primary flex-shrink-0'),
            kit.h('span', _sdT('Citizen line'), 'fw-semibold'),
            kit.link('+90 555 000 01 53', 'tel:+905550000153', 'fw-semibold link-primary', _sdT('Phone Link')),
            kit.h('span', _sdT('Every request and question, around the clock'), 'small text-body-secondary')
        ]);
        return kit.section('py-5 bg-body-tertiary', _sdT('City Hall (Hero)'), [
            fx(words, { effect: 'slide-up', trigger: 'load', duration: 700 }),
            tasks,
            line
        ]);
    };

    // ── Service guide ───────────────────────────────────────────────────
    // A fact of the service: its label and its value on one hairline.
    var fact = function (label, value) {
        return kit.div('', label, [
            kit.h('dt', label),
            kit.h('dd', value)
        ]);
    };
    // A document to bring, ticked off in the list.
    var doc = function (text, i) {
        return kit.tag('li', 'd-flex gap-2 mb-2', _sdT('Item {var}', i + 1), [
            kit.icon('bi-file-earmark-text', 'text-primary flex-shrink-0'),
            kit.h('span', text)
        ]);
    };
    var buildService = function () {
        var body = kit.col(_sdT('Service'), [
            eyebrow(_sdT('Building and Zoning')),
            kit.h('h2', _sdT('Building permit'), 'h2 fw-bold mb-3'),
            kit.p(_sdT('Permission to build a new building or to extend one.'), 'lead text-body-secondary mb-4'),
            fx(kit.tag('dl', 'pg-cityhall-guide border-top mb-4', _sdT('Facts'), [
                fact(_sdT('Who applies'), _sdT('The owner of the plot, or someone with their written consent')),
                fact(_sdT('Where to apply'), _sdT('Citizen Desk')),
                fact(_sdT('How long it takes'), _sdT('About a month once the file is complete')),
                fact(_sdT('Fee'), _sdT('Set by the floor area; the department tells you before you pay'))
            ]), { effect: 'slide-up', trigger: 'view', stagger: 80 }),
            kit.h('h3', _sdT('What to bring'), 'h5 fw-bold mb-3'),
            kit.tag('ul', 'list-unstyled mb-4', _sdT('Documents'), [
                _sdT('Title deed'),
                _sdT('Architectural project'),
                _sdT('Static report'),
                _sdT('The applicant\'s ID')
            ].map(doc)),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Make a Request'), '#', 'btn btn-primary px-4', _sdT('Request Link')),
                kit.link(_sdT('All Services'), '#', 'btn btn-outline-secondary px-4', _sdT('Services Link'))
            ])
        ], '7');
        var desk = kit.col(_sdT('Department'), [
            kit.tag('aside', 'card border-0 shadow-sm', _sdT('Department Card'), [
                kit.div('card-body p-4', _sdT('Card Body'), [
                    kit.icon('bi-rulers', 'fs-2 text-primary d-block mb-3'),
                    kit.p(_sdT('Responsible Department'), 'small text-uppercase fw-semibold text-body-secondary mb-1'),
                    kit.h('h3', _sdT('Zoning and Urban Planning'), 'h5 fw-bold mb-2'),
                    kit.p(_sdT('Zoning plans, building permits and occupancy certificates.'), 'text-body-secondary mb-3'),
                    kit.tag('ul', 'list-unstyled small mb-0', _sdT('Department Details'), [
                        kit.tag('li', 'd-flex gap-2 mb-2', _sdT('Floor'), [
                            kit.icon('bi-building', 'text-primary flex-shrink-0'),
                            kit.h('span', _sdT('Main building, 3rd floor'))
                        ]),
                        kit.tag('li', 'd-flex gap-2 mb-2', _sdT('Phone'), [
                            kit.icon('bi-telephone', 'text-primary flex-shrink-0'),
                            kit.link('+90 555 000 02 10', 'tel:+905550000210', 'link-body-emphasis', _sdT('Phone Link'))
                        ]),
                        kit.tag('li', 'd-flex gap-2', _sdT('Hours'), [
                            kit.icon('bi-clock', 'text-primary flex-shrink-0'),
                            kit.h('span', _sdT('City hall: Monday to Friday, 8:30 to 17:30'))
                        ])
                    ])
                ])
            ])
        ], '5');
        return kit.section('py-5', _sdT('City Hall (Service Guide)'), [
            kit.row(_sdT('Service Row'), [
                body,
                fx(desk, { effect: 'fade', trigger: 'view', duration: 900 })
            ], 'g-4 g-lg-5')
        ]);
    };

    // ── Council agenda ──────────────────────────────────────────────────
    // A status: an icon and a word, so it never rests on colour alone.
    var status = function (icon, text, cls) {
        return kit.div('badge rounded-pill d-inline-flex align-items-center gap-1 ' + cls, _sdT('Status'), [
            kit.icon(icon),
            kit.h('span', text)
        ]);
    };
    // An agenda item: its number, its subject, the committee that brought
    // it and where it stands.
    var item = function (no, title, committee, state) {
        return kit.tag('li', '', title, [
            kit.h('span', no, 'pg-cityhall-no small fw-bold'),
            kit.h('h3', title, 'h6 fw-semibold mb-1'),
            kit.p(committee, 'small text-body-secondary mb-2'),
            state
        ]);
    };
    var buildCouncil = function () {
        var session = kit.col(_sdT('Next Session'), [
            eyebrow(_sdT('City Council')),
            kit.h('h2', _sdT('The next council session'), 'h2 fw-bold mb-3'),
            kit.p(_sdT('The council meets on the first Tuesday of each month at 14:00 in the council hall. Meetings are open to the public, and the agenda is published as a notice a week before.'), 'text-body-secondary mb-4'),
            kit.tag('ul', 'list-unstyled mb-4', _sdT('Session Details'), [
                kit.tag('li', 'd-flex gap-2 mb-2', _sdT('When'), [
                    kit.icon('bi-calendar-event', 'text-primary flex-shrink-0'),
                    kit.h('span', _sdT('First Tuesday of the month, 14:00'))
                ]),
                kit.tag('li', 'd-flex gap-2 mb-2', _sdT('Where'), [
                    kit.icon('bi-bank', 'text-primary flex-shrink-0'),
                    kit.h('span', _sdT('Council hall, Republic Square 1'))
                ]),
                kit.tag('li', 'd-flex gap-2', _sdT('Open to the Public'), [
                    kit.icon('bi-door-open', 'text-primary flex-shrink-0'),
                    kit.h('span', _sdT('Open to every resident; seats are first come, first served'))
                ])
            ]),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Council Decisions'), '#', 'btn btn-primary px-4', _sdT('Decisions Link')),
                kit.link(_sdT('Meet the Council'), '#', 'btn btn-outline-secondary px-4', _sdT('Council Link'))
            ])
        ], '5');
        var agenda = kit.col(_sdT('Agenda'), [
            kit.h('h3', _sdT('On the agenda'), 'h5 fw-bold mb-4'),
            fx(kit.tag('ol', 'pg-cityhall-agenda list-unstyled mb-0', _sdT('Agenda Items'), [
                item('1', _sdT('A new playground in Linden Park'), _sdT('Culture and Youth Committee'),
                    status('bi-check2-circle', _sdT('Decided'), 'text-bg-success')),
                item('2', _sdT('A zoning plan change in the Station neighbourhood'), _sdT('Planning and Zoning Committee'),
                    status('bi-arrow-repeat', _sdT('Sent back to committee'), 'text-bg-warning')),
                item('3', _sdT('Home care for older residents in the outer neighbourhoods'), _sdT('Social Affairs Committee'),
                    status('bi-chat-left-text', _sdT('To be discussed'), 'text-bg-secondary')),
                item('4', _sdT('The accounts of the past year'), _sdT('Budget Committee'),
                    status('bi-chat-left-text', _sdT('To be discussed'), 'text-bg-secondary'))
            ]), { effect: 'slide-up', trigger: 'view', stagger: 100 })
        ], '7');
        return kit.section('py-5', _sdT('City Hall (Council Agenda)'), [
            kit.row(_sdT('Council Row'), [
                fx(session, { effect: 'fade', trigger: 'view', duration: 800 }),
                agenda
            ], 'g-4 g-lg-5')
        ]);
    };

    return {
        prefix: 'cityhall-',
        group: { title: _sdT('City Hall'), icon: 'bi-building' },
        items: [
            { label: _sdT('City Hall: Task Hero'),      type: 'component', icon: 'bi-signpost-split', extra: { componentType: 'cityhall-hero' },
              desc: _sdT('A question as the headline over six task tiles with an icon each, then the citizen phone line, on a tinted band.') },
            { label: _sdT('City Hall: Service Guide'),  type: 'component', icon: 'bi-card-checklist', extra: { componentType: 'cityhall-service' },
              desc: _sdT('One service as a guide: who applies, where, how long and the fee, the papers to bring, two buttons and a department card.') },
            { label: _sdT('City Hall: Council Agenda'), type: 'component', icon: 'bi-list-ol',        extra: { componentType: 'cityhall-council' },
              desc: _sdT('The next council session with its time, place and two buttons, beside a numbered agenda whose items carry a status badge.') }
        ],
        build: function (ctype) {
            if (ctype === 'cityhall-hero') return buildHero();
            if (ctype === 'cityhall-service') return buildService();
            if (ctype === 'cityhall-council') return buildCouncil();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
