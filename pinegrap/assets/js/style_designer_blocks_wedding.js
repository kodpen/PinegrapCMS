/**
 * Pinegrap - Enterprise Website Platform
 *
 * Wedding block family: the sections a wedding planner's or a wedding
 * venue's site needs that the general palette lacks. The invitation hero
 * (a wide photo with a card laid over it like a printed invitation, the
 * planner's facts in it), a real wedding's story (a picture mosaic, the
 * couple, their day in facts, their words and the suppliers who made it)
 * and the planning checklist (what to do and when, months before the day).
 * The packages, the spaces, the order of the day and the questions are
 * general blocks (Catering: Packages, Locations: Spaces to Hire, Timeline:
 * Vertical, FAQ).
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
        // The invitation card: a thin inner frame like a printed card's,
        // laid over the photo's lower edge from lg up.
        'pg-wedding-invite': '.pg-wedding-invite { outline: 1px solid var(--bs-primary-border-subtle); outline-offset: -.75rem; } ' +
                             '@media (min-width: 992px) { .pg-wedding-invite { margin-top: -8rem; } }',
        // A divider with an ornament in its middle: two hairlines and the
        // icon between them.
        'pg-wedding-rule':   '.pg-wedding-rule { display: flex; align-items: center; gap: .75rem; color: var(--bs-primary); } ' +
                             '.pg-wedding-rule::before, .pg-wedding-rule::after { content: ""; flex: 1 1 0; border-top: 1px solid var(--bs-primary-border-subtle); }',
        // Three pictures of one wedding: a tall one beside two stacked.
        'pg-wedding-mosaic': '.pg-wedding-mosaic { display: grid; grid-template-columns: 3fr 2fr; grid-template-rows: 1fr 1fr; gap: 1rem; aspect-ratio: 5 / 4; } ' +
                             '.pg-wedding-mosaic > :first-child { grid-row: span 2; } ' +
                             '.pg-wedding-mosaic img { width: 100%; height: 100%; object-fit: cover; background: var(--bs-tertiary-bg); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'wedding-hero':      ['pg-wedding-invite', 'pg-wedding-rule'],
        'wedding-story':     ['pg-wedding-mosaic'],
        'wedding-checklist': ['pg-wedding-rule']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };

    // The ornament between two hairlines.
    var ornament = function (cls) {
        return kit.div('pg-wedding-rule ' + (cls || ''), _sdT('Divider'), [kit.icon('bi-suit-heart')], [{ name: 'aria-hidden', value: 'true' }]);
    };

    // ── Invitation hero ─────────────────────────────────────────────────
    // A fact of the planner in the card: an icon, what it is and its value.
    var detail = function (icon, term, value) {
        return kit.div('col', term, [
            kit.icon(icon, 'd-block fs-4 text-primary mb-1'),
            kit.tag('dt', 'small text-uppercase fw-semibold text-body-secondary', _sdT('Term'), [kit.h('span', term)]),
            kit.tag('dd', 'fw-semibold mb-0', _sdT('Value'), [kit.h('span', value)])
        ]);
    };
    var buildHero = function () {
        var photo = kit.div('ratio ratio-21x9 rounded-4 overflow-hidden bg-body-tertiary', _sdT('Picture Frame'), [
            kit.img('wedding-hero', 1600, 690, _sdT('Long tables laid for a wedding dinner under olive trees'), 'object-fit-cover')
        ]);
        var card = kit.div('pg-wedding-invite position-relative col-lg-8 col-xl-7 mx-auto mt-4 p-4 p-md-5 rounded-4 bg-body shadow text-center', _sdT('Invitation Card'), [
            kit.p(_sdT('Planning and a venue by the sea'), 'small text-uppercase fw-semibold text-primary mb-3'),
            kit.h('h1', _sdT('Your day, beautifully planned'), 'display-4 mb-3'),
            ornament('col-8 col-md-6 mx-auto mb-3'),
            kit.p(_sdT('Wedding planning and a garden venue by the sea, for one wedding a day.'), 'lead text-body-secondary mb-4'),
            kit.tag('dl', 'row row-cols-1 row-cols-sm-3 g-3 mb-4', _sdT('Details'), [
                detail('bi-calendar-heart', _sdT('Weddings'), _sdT('One a day')),
                detail('bi-people', _sdT('Guests'), _sdT('From 40 to 300')),
                detail('bi-tree', _sdT('Spaces'), _sdT('Four, indoors and out'))
            ]),
            kit.div('d-flex flex-wrap justify-content-center gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Book a Venue Visit'), '#', 'btn btn-primary btn-lg rounded-pill px-4', _sdT('Visit Link')),
                kit.link(_sdT('See the Packages'), '#', 'btn btn-outline-primary btn-lg rounded-pill px-4', _sdT('Packages Link'))
            ])
        ]);
        return kit.section('py-5 overflow-hidden', _sdT('Wedding (Invitation Hero)'), [
            fx(photo, { effect: 'fade', trigger: 'load', duration: 1000 }),
            fx(card, { effect: 'slide-up', trigger: 'load', duration: 700, delay: 300 })
        ]);
    };

    // ── Real wedding story ──────────────────────────────────────────────
    // One wedding told in a band: three pictures, the couple, their day in
    // facts, their own words and the suppliers who made it.
    var fact = function (icon, value, label) {
        return kit.tag('li', 'd-flex align-items-center gap-2', label, [
            kit.icon(icon, 'text-primary'),
            kit.h('span', label, 'visually-hidden'),
            kit.h('span', value, 'fw-semibold')
        ]);
    };
    var supplier = function (role, name) {
        return [
            kit.tag('dt', 'col-5 fw-normal text-body-secondary', _sdT('Role'), [kit.h('span', role)]),
            kit.tag('dd', 'col-7 fw-semibold', _sdT('Supplier'), [kit.h('span', name)])
        ];
    };
    var buildStory = function () {
        var suppliers = [].concat(
            supplier(_sdT('Photography'), _sdT('Light and Lens Studio')),
            supplier(_sdT('Flowers'), _sdT('Wild Stem Florals')),
            supplier(_sdT('Music'), _sdT('The Evening Quartet'))
        );
        return kit.section('py-5', _sdT('Wedding (Real Wedding Story)'), [
            kit.row(_sdT('Story Row'), [
                kit.col(_sdT('Story Pictures'), [
                    fx(kit.div('pg-wedding-mosaic', _sdT('Picture Mosaic'), [
                        kit.img('wedding-story-arch', 720, 960, _sdT('The couple under the stone arch'), 'rounded-4'),
                        kit.img('wedding-story-table', 480, 360, _sdT('A long table under string lights'), 'rounded-4'),
                        kit.img('wedding-story-dance', 480, 360, _sdT('The first dance in the Grand Hall'), 'rounded-4')
                    ]), { effect: 'fade', trigger: 'view', duration: 900 })
                ], '6'),
                kit.col(_sdT('Story Text'), [
                    fx(kit.div('', _sdT('Story Body'), [
                        kit.p(_sdT('Real wedding'), 'small text-uppercase fw-semibold text-primary mb-2'),
                        kit.h('h2', _sdT('Selin and Emre'), 'display-6 mb-3'),
                        kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-3 mb-3', _sdT('Wedding Facts'), [
                            fact('bi-tree', _sdT('The Olive Garden'), _sdT('Venue Space')),
                            fact('bi-sun', _sdT('Summer'), _sdT('Season')),
                            fact('bi-people', _sdT('{var} guests', '160'), _sdT('Guests'))
                        ]),
                        kit.p(_sdT('A long summer evening under the olive trees, with a table of family recipes and dancing until the stars came out.'), 'text-body-secondary mb-4'),
                        kit.tag('figure', 'border-start border-3 border-primary ps-3 mb-4', _sdT('Quote'), [
                            kit.tag('blockquote', 'blockquote fs-5 fst-italic mb-2', _sdT('Quote Text'), [
                                kit.p(_sdT('Everyone still talks about the dinner under the trees. We did not worry about a single thing all day.'), 'mb-0')
                            ]),
                            kit.tag('figcaption', 'small text-body-secondary', _sdT('Caption'), [kit.h('span', _sdT('Selin and Emre'))])
                        ]),
                        kit.h('h3', _sdT('The suppliers'), 'h6 text-uppercase fw-semibold mb-2'),
                        kit.tag('dl', 'row small mb-4', _sdT('Suppliers'), suppliers),
                        kit.link(_sdT('Read their story'), '#', 'btn btn-outline-primary rounded-pill px-4', _sdT('Story Link'))
                    ]), { effect: 'slide-up', trigger: 'view', duration: 700, stagger: 80 })
                ], '6')
            ], 'align-items-center g-lg-5')
        ]);
    };

    // ── Planning checklist ──────────────────────────────────────────────
    // A card per stage: how long before the day, its name and the tasks
    // to tick off then.
    var stage = function (n, icon, when, title, tasks) {
        return kit.tag('li', 'col', _sdT('Stage {var}', n), [
            kit.div('h-100 p-4 rounded-4 border bg-body', _sdT('Stage Card'), [
                kit.div('d-flex align-items-center gap-3 mb-3', _sdT('Stage Head'), [
                    kit.div('d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-primary-subtle text-primary-emphasis p-2 lh-1', _sdT('Icon Box'), [
                        kit.icon(icon, 'fs-5')
                    ]),
                    kit.h('span', when, 'small text-uppercase fw-semibold text-primary')
                ]),
                kit.h('h3', title, 'h5 mb-3'),
                kit.tag('ul', 'list-unstyled small mb-0', _sdT('Tasks'), tasks.map(function (t, i) {
                    return kit.tag('li', 'd-flex gap-2 mb-2', _sdT('Task {var}', i + 1), [
                        kit.icon('bi-check2-square', 'text-primary flex-shrink-0'),
                        kit.h('span', t)
                    ]);
                }))
            ])
        ]);
    };
    var buildChecklist = function () {
        return kit.section('py-5 bg-body-tertiary', _sdT('Wedding (Planning Checklist)'), [
            kit.div('text-center col-lg-8 mx-auto mb-5', _sdT('Section Header'), [
                kit.p(_sdT('Planning Timeline'), 'small text-uppercase fw-semibold text-primary mb-2'),
                kit.h('h2', _sdT('From the first idea to the last dance'), 'display-6 mb-3'),
                ornament('col-6 col-md-4 mx-auto mb-3'),
                kit.p(_sdT('What to do and when, from the first idea to the last week.'), 'lead text-body-secondary mb-0')
            ]),
            fx(kit.tag('ol', 'list-unstyled row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4', _sdT('Stages'), [
                stage(1, 'bi-chat-heart', _sdT('Twelve months before'), _sdT('Dream and decide'),
                    [_sdT('Agree on a budget and a rough guest count'), _sdT('Visit the venue and choose a date'), _sdT('Book your planner and hold the date')]),
                stage(2, 'bi-camera', _sdT('Nine months before'), _sdT('Book the people who make the day'),
                    [_sdT('Book the photographer and the music'), _sdT('Send save-the-dates'), _sdT('Start looking for the dress and the suit')]),
                stage(3, 'bi-palette', _sdT('Six months before'), _sdT('Shape the look'),
                    [_sdT('Choose flowers, colours and styling'), _sdT('Book hair and make-up'), _sdT('Plan where guests will stay')]),
                stage(4, 'bi-envelope-heart', _sdT('Three months before'), _sdT('Invite and taste'),
                    [_sdT('Send the invitations'), _sdT('Taste the menu and choose the cake'), _sdT('Plan the ceremony and its words')]),
                stage(5, 'bi-list-check', _sdT('One month before'), _sdT('Fix the details'),
                    [_sdT('Confirm the final guest count'), _sdT('Make the seating plan'), _sdT('Agree the running order of the day')]),
                stage(6, 'bi-balloon-heart', _sdT('The week of the wedding'), _sdT('Hand it over and enjoy it'),
                    [_sdT('Rehearse the ceremony'), _sdT('Hand everything to your coordinator'), _sdT('Rest, and enjoy every moment of the day')])
            ]), { effect: 'slide-up', trigger: 'view', stagger: 100 }),
            kit.div('d-flex flex-column flex-sm-row align-items-sm-center justify-content-center gap-3 text-center', _sdT('Checklist Foot'), [
                kit.p(_sdT('With full or partial planning, your planner keeps this list with you and reminds you of each step in time.'), 'small text-body-secondary mb-0'),
                kit.link(_sdT('See the planning services'), '#', 'btn btn-outline-primary rounded-pill px-4 text-nowrap', _sdT('Services Link'))
            ])
        ]);
    };

    return {
        prefix: 'wedding-',
        group: { title: _sdT('Wedding'), icon: 'bi-balloon-heart' },
        items: [
            { label: _sdT('Wedding: Invitation Hero'),     type: 'component', icon: 'bi-envelope-paper-heart', extra: { componentType: 'wedding-hero' } },
            { label: _sdT('Wedding: Real Wedding Story'),  type: 'component', icon: 'bi-heart',                extra: { componentType: 'wedding-story' } },
            { label: _sdT('Wedding: Planning Checklist'),  type: 'component', icon: 'bi-list-check',           extra: { componentType: 'wedding-checklist' } }
        ],
        build: function (ctype) {
            if (ctype === 'wedding-hero')      return buildHero();
            if (ctype === 'wedding-story')     return buildStory();
            if (ctype === 'wedding-checklist') return buildChecklist();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
