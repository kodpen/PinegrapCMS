/**
 * Pinegrap - Enterprise Website Platform
 *
 * Conference block family (prefix conference-): the sections of a
 * conference's site the palette lacks. The opening hero (the conference's
 * line in very large type on a dark band lit by the theme's colour, where
 * and when, two buttons and the early registration note, the figures
 * counting up, and beside them an attendee's badge hanging on its lanyard
 * from the band's top edge), the day agenda (one day of the programme by
 * time: breaks as quiet lines, the keynote across the width, parallel
 * sessions side by side, each with its track, speaker, kind and room) and
 * the speaker line-up (the speakers set like a festival poster: the
 * keynotes in giant type, then each track's names in a row). The passes,
 * the venue, the sponsors, the events between two conferences and the
 * attendees' words are general blocks (Pricing, Locations, Logos, Events,
 * Testimonials). Motion comes from the nodes' interactions (props._fx,
 * played by pg_fx.src.js, which honours reduced motion).
 *
 * Registered with StyleDesigner.registerBlockFamily() and bundled after the
 * designer core (pg_designer_js_sources()); see
 * docs/memory/designer/designer-blocks-conference.md.
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
        // The opening band: the theme's colour glows behind the badge and,
        // fainter, at the foot of the text, where the browser mixes colours;
        // an older one shows the plain dark surface. The title's lines are
        // balanced, so a long translation does not leave one word alone.
        'pg-conference-hero':    '@supports (background: color-mix(in srgb, red 50%, blue)) { .pg-conference-hero { background-image: ' +
                                 'radial-gradient(ellipse 45% 60% at 85% 35%, color-mix(in srgb, var(--bs-primary) 30%, transparent), transparent 70%), ' +
                                 'radial-gradient(ellipse 40% 40% at 0% 100%, color-mix(in srgb, var(--bs-primary) 14%, transparent), transparent 70%); } } ' +
                                 '.pg-conference-hero h1 { text-wrap: balance; } ' +
                                 '.pg-conference-hero .pg-conference-figure { font-variant-numeric: tabular-nums; }',
        // The attendee's badge on its lanyard: the strap in the theme's colour
        // runs up from the clip, on a phone a short way and from lg up to the
        // band's top edge, which the band clips; the badge hangs from the
        // clip at a slight angle and straightens under the pointer. Its
        // barcode is drawn in the badge's strongest text colour.
        'pg-conference-lanyard': '.pg-conference-lanyard { position: relative; max-width: 22rem; margin-inline: auto; padding-top: 4rem; } ' +
                                 '.pg-conference-lanyard::before { content: ""; position: absolute; top: 0; left: 50%; width: 2.25rem; height: 4.5rem; ' +
                                 'transform: translateX(-50%); background-color: var(--bs-primary); } ' +
                                 '.pg-conference-lanyard .pg-conference-badge { position: relative; transform-origin: 50% -1rem; } ' +
                                 '.pg-conference-lanyard .pg-conference-badge::before { content: ""; position: absolute; z-index: 1; top: -1.25rem; left: 50%; width: 3.5rem; height: 1.75rem; ' +
                                 'transform: translateX(-50%); border: 2px solid var(--bs-border-color); border-radius: .5rem; background-color: var(--bs-tertiary-bg); } ' +
                                 '.pg-conference-lanyard .pg-conference-code { width: 7.5rem; height: 2.25rem; background: repeating-linear-gradient(90deg, ' +
                                 'var(--bs-emphasis-color) 0 2px, transparent 2px 4px, var(--bs-emphasis-color) 4px 5px, transparent 5px 8px, ' +
                                 'var(--bs-emphasis-color) 8px 11px, transparent 11px 12px, var(--bs-emphasis-color) 12px 13px, transparent 13px 16px); } ' +
                                 '@media (min-width: 768px) { .pg-conference-lanyard .pg-conference-badge { transform: rotate(-4deg); transition: transform .5s ease; } ' +
                                 '.pg-conference-lanyard:hover .pg-conference-badge { transform: none; } } ' +
                                 '@media (min-width: 992px) { .pg-conference-lanyard::before { top: auto; bottom: calc(100% - 4.5rem); height: 100vh; } } ' +
                                 '@media (prefers-reduced-motion: reduce) { .pg-conference-lanyard .pg-conference-badge { transition: none; } }',
        // The day agenda: each time slot on a hairline, its time in figures
        // that line up in a narrow column from md up (above the sessions on
        // a phone); a break's line is quieter. The keynote carries a bar in
        // the theme's colour on its starting edge; a speaker's face is round.
        'pg-conference-agenda':  '.pg-conference-agenda { margin: 0; padding: 0; list-style: none; } ' +
                                 '.pg-conference-agenda .pg-conference-slot { display: grid; gap: .75rem; padding-block: 1.25rem; border-top: 1px solid var(--bs-border-color); } ' +
                                 '@media (min-width: 768px) { .pg-conference-agenda .pg-conference-slot { grid-template-columns: 5rem minmax(0, 1fr); gap: 1.5rem; align-items: start; } } ' +
                                 '.pg-conference-agenda .pg-conference-time { font-size: 1.25rem; font-weight: 700; line-height: 1.3; font-variant-numeric: tabular-nums; color: var(--bs-emphasis-color); } ' +
                                 '.pg-conference-agenda .pg-conference-break { padding-block: .875rem; } ' +
                                 '.pg-conference-agenda .pg-conference-break .pg-conference-time { font-size: 1rem; font-weight: 600; line-height: 1.5; color: var(--bs-secondary-color); } ' +
                                 '.pg-conference-agenda .pg-conference-keynote { box-shadow: inset .375rem 0 0 var(--bs-primary); } ' +
                                 '[dir="rtl"] .pg-conference-agenda .pg-conference-keynote { box-shadow: inset -.375rem 0 0 var(--bs-primary); } ' +
                                 '.pg-conference-agenda .pg-conference-face { width: 3.5rem; height: 3.5rem; flex-shrink: 0; }',
        // The line-up: a row per group on a hairline, its name in a narrow
        // column from lg up; the names in the heading font, large and set
        // close, the keynotes giant, each but the last followed by a slash in
        // the theme's colour that a screen reader skips, so a wrapped row
        // ends on a slash instead of starting with one.
        'pg-conference-lineup':  '.pg-conference-lineup .pg-conference-group { display: grid; gap: .5rem; padding-block: 1.5rem; border-top: 1px solid var(--bs-border-color); } ' +
                                 '@media (min-width: 992px) { .pg-conference-lineup .pg-conference-group { grid-template-columns: 13rem minmax(0, 1fr); gap: 2rem; align-items: baseline; } } ' +
                                 '.pg-conference-lineup .pg-conference-names { display: flex; flex-wrap: wrap; gap: .25rem 1rem; margin: 0; padding: 0; list-style: none; ' +
                                 'font-family: var(--pg-font-heading, inherit); font-size: clamp(1.5rem, 1rem + 2.2vw, 3rem); font-weight: 700; line-height: 1.15; letter-spacing: -.02em; } ' +
                                 '.pg-conference-lineup .pg-conference-keynotes { font-size: clamp(2.25rem, 1.25rem + 4.5vw, 5.5rem); font-weight: 800; line-height: 1.05; } ' +
                                 '.pg-conference-lineup .pg-conference-slash::after { content: "/"; content: "/" / ""; margin-inline-start: .75rem; font-weight: 300; color: var(--bs-primary); }',
        // The secondary button's focus ring, in the body's strongest colour:
        // a button without a colour variant has no ring colour of its own,
        // so the keyboard focus would not show.
        'pg-conference-second':  '.pg-conference-second { --bs-btn-focus-shadow-rgb: var(--bs-emphasis-color-rgb); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'conference-hero':   ['pg-display-xl', 'pg-conference-hero', 'pg-conference-lanyard', 'pg-conference-second'],
        'conference-agenda': ['pg-conference-agenda', 'pg-conference-second'],
        'conference-lineup': ['pg-conference-lineup', 'pg-conference-second']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };
    var dark = [{ name: 'data-bs-theme', value: 'dark' }];
    var hidden = [{ name: 'aria-hidden', value: 'true' }];

    // The small spaced line above a title, in the body's second colour: small
    // text in the theme's colour is under 4.5:1 on a grey band or a dark
    // page with some palettes. Icons keep the theme's colour (3:1 is enough).
    var eyebrow = function (text, cls) {
        return kit.p(text, 'small text-uppercase fw-semibold text-body-secondary ' + (cls || 'mb-2'));
    };
    // A secondary button: the body's text colour and a border, readable in
    // both modes (an outline button in the theme's colour is not on a grey
    // band), with its own focus ring (pg-conference-second).
    var second = function (text, name, size) {
        return kit.link(text, '#', 'btn ' + (size ? size + ' ' : '') + 'border px-4 link-body-emphasis pg-conference-second', name);
    };
    // An icon and a short text side by side, as one line of details.
    var detail = function (icon, text, cls) {
        return kit.tag('span', 'd-inline-flex align-items-center gap-1' + (cls ? ' ' + cls : ''), text, [
            kit.icon(icon),
            kit.h('span', text)
        ]);
    };

    // The tracks of the programme: their name and icon on a pill in a
    // Bootstrap tint, so the four read apart without the theme's colour
    // standing for one of them; the name always goes with the colour.
    var TRACKS = {
        product:     { icon: 'bi-bezier2',        name: _sdT('Product & Design'),   tone: 'primary' },
        engineering: { icon: 'bi-cpu',            name: _sdT('Engineering'),        tone: 'success' },
        growth:      { icon: 'bi-graph-up-arrow', name: _sdT('Growth & Marketing'), tone: 'warning' },
        leadership:  { icon: 'bi-people',         name: _sdT('Leadership'),         tone: 'danger' }
    };
    var chip = function (key, tag) {
        var t = TRACKS[key];
        return kit.tag(tag || 'span', 'badge rounded-pill bg-' + t.tone + '-subtle text-' + t.tone + '-emphasis text-wrap fw-semibold d-inline-flex align-items-center gap-1 px-3 py-2', t.name, [
            kit.icon(t.icon),
            kit.h('span', t.name)
        ]);
    };

    // ── Opening hero ────────────────────────────────────────────────────
    // A figure of the conference: its number, which counts up when the band
    // is seen, and what it counts.
    var figure = function (value, label) {
        return kit.createNode('col', { xs: '6', sm: '', md: '3', lg: '', xl: '', xxl: '', offsetXs: '', offsetMd: '', order: '', cssClass: '', customName: value }, [
            fx(kit.p(value, 'pg-conference-figure display-6 fw-bold text-body-emphasis mb-1'), { effect: 'count', duration: 1600, delay: 300 }),
            kit.p(label, 'small text-body-secondary mb-0')
        ]);
    };
    var buildHero = function () {
        var words = fx(kit.div('', _sdT('Hero Text'), [
            kit.tag('p', 'd-flex flex-wrap align-items-center column-gap-3 row-gap-1 small fw-semibold text-body-secondary mb-4', _sdT('Event Line'), [
                detail('bi-calendar2-week', _sdT('Two days in autumn'), 'gap-2'),
                detail('bi-geo-alt', _sdT('Harbour Congress Centre'), 'gap-2')
            ]),
            kit.h('h1', _sdT('The conference for people who build products'), 'pg-display-xl fw-bold mb-4'),
            kit.p(_sdT('Keynotes, four tracks and hands-on workshops by the people behind products you use every day. Meet them, and the 1,200 others who come to learn.'), 'lead text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap gap-2 mb-3', _sdT('Buttons'), [
                kit.link(_sdT('Register'), '#', 'btn btn-primary btn-lg px-4', _sdT('Registration Link')),
                second(_sdT('See the programme'), _sdT('Programme Link'), 'btn-lg')
            ]),
            detail('bi-ticket-perforated', _sdT('Early registration is open: the first 200 Conference Passes at the lower price.'), 'small text-body-secondary gap-2')
        ]), { effect: 'slide-up', trigger: 'load', duration: 800 });
        var badge = fx(kit.div('pg-conference-lanyard', _sdT('Lanyard'), [
            kit.div('pg-conference-badge bg-body text-body rounded-4 shadow-lg', _sdT('Attendee Badge'), [
                kit.div('text-bg-primary d-flex align-items-center justify-content-between gap-3 rounded-top-4 px-4 pt-4 pb-3', _sdT('Header'), [
                    kit.tag('p', 'd-flex align-items-center gap-2 fw-bold mb-0', _sdT('Brand'), [
                        kit.icon('bi-calendar2-event'),
                        kit.h('span', 'ACME')
                    ]),
                    kit.p(_sdT('2 days'), 'small fw-semibold mb-0')
                ]),
                kit.div('px-4 pt-4 pb-3', _sdT('Details'), [
                    kit.h('h2', _sdT('Conference Pass'), 'h6 text-uppercase fw-bold text-body-secondary mb-3'),
                    kit.p(_sdT('Jane Cooper'), 'fs-2 fw-bold lh-sm text-body-emphasis mb-1'),
                    kit.p('Vertex', 'fs-5 fw-semibold text-body-emphasis mb-0'),
                    kit.p(_sdT('Design Director'), 'small text-body-secondary mb-4'),
                    kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', _sdT('Included'), [
                        kit.tag('li', 'badge rounded-pill border text-body-emphasis fw-semibold', _sdT('Day 1'), [kit.h('span', _sdT('Day 1'))]),
                        kit.tag('li', 'badge rounded-pill border text-body-emphasis fw-semibold', _sdT('Day 2'), [kit.h('span', _sdT('Day 2'))]),
                        kit.tag('li', 'badge rounded-pill border text-body-emphasis fw-semibold text-wrap', _sdT('The evening reception'), [kit.h('span', _sdT('The evening reception'))])
                    ])
                ]),
                kit.div('d-flex align-items-end justify-content-between gap-3 border-top px-4 py-3', _sdT('Footer'), [
                    kit.div('pg-conference-code', _sdT('Barcode'), [], hidden),
                    kit.p('0427', 'small font-monospace text-body-secondary mb-0')
                ])
            ], [{ name: 'data-bs-theme', value: 'light' }])
        ]), { effect: 'slide-down', trigger: 'load', duration: 1000, delay: 250, distance: 120 });
        return kit.tag('section', 'pg-conference-hero py-5 bg-body text-body overflow-hidden', _sdT('Conference (Opening Hero)'), [
            kit.createNode('container', { fluid: false, cssClass: 'pt-lg-4' }, [
                kit.row(_sdT('Hero Row'), [
                    kit.col(_sdT('Hero Text'), [words], '7'),
                    kit.col(_sdT('Attendee Badge'), [badge], '5')
                ], 'g-5 align-items-center'),
                kit.div('border-top mt-5 pt-4', _sdT('Figures'), [
                    kit.row(_sdT('Figures Row'), [
                        figure(_sdT('2 days'), _sdT('of talks and workshops')),
                        figure(_sdT('40+'), _sdT('speakers')),
                        figure(_sdT('4 tracks'), _sdT('running side by side')),
                        figure(_sdT('1,200'), _sdT('attendees last time'))
                    ])
                ])
            ])
        ], dark);
    };

    // ── Day agenda ──────────────────────────────────────────────────────
    var slotTime = function (time) {
        return kit.time(time, time, 'pg-conference-time', _sdT('Time'));
    };
    // A break or the evening: its time and a quiet line with where it is.
    var pause = function (time, icon, title, room) {
        return kit.tag('li', 'pg-conference-slot pg-conference-break', title, [
            slotTime(time),
            kit.tag('p', 'd-flex flex-wrap align-items-center column-gap-3 row-gap-1 text-body-secondary mb-0', _sdT('Break'), [
                detail(icon, title, 'gap-2 fw-semibold text-body-emphasis'),
                detail('bi-door-open', room, 'small')
            ])
        ]);
    };
    // The keynote across the width: its kind, its title in large type, the
    // speaker with a face, the role and company, and the room.
    var keynote = function (time, title, name, role, company, seed, room) {
        return kit.tag('li', 'pg-conference-slot', title, [
            slotTime(time),
            kit.div('pg-conference-keynote rounded-4 border bg-body p-4 p-lg-5', _sdT('Keynote'), [
                kit.tag('p', 'd-flex align-items-center gap-2 small text-uppercase fw-semibold text-body-secondary mb-2', _sdT('Kind'), [
                    kit.icon('bi-mic', 'text-primary-emphasis'),
                    kit.h('span', _sdT('Keynote'))
                ]),
                kit.h('h3', title, 'h3 fw-bold mb-4'),
                kit.div('d-flex flex-wrap align-items-center gap-3', _sdT('Speaker'), [
                    kit.img(seed, 160, 160, name, 'pg-conference-face rounded-circle object-fit-cover'),
                    kit.div('flex-grow-1', _sdT('Text'), [
                        kit.p(name, 'fw-semibold text-body-emphasis mb-0'),
                        kit.p(role + ', ' + company, 'small text-body-secondary mb-0')
                    ]),
                    detail('bi-door-open', room, 'small text-body-secondary')
                ])
            ])
        ]);
    };
    // A session of a track: its track, title, speaker, kind and room.
    var session = function (track, title, name, kind, kindIcon, room) {
        var kids = [
            chip(track),
            kit.h('h3', title, 'h6 fw-bold mt-3 mb-2')
        ];
        if (name) kids.push(kit.p(name, 'small fw-semibold text-body-emphasis mb-2'));
        kids.push(kit.tag('p', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary mb-0', _sdT('Session Details'), [
            detail(kindIcon, kind),
            detail('bi-door-open', room)
        ]));
        return kit.div('h-100 rounded-3 border bg-body p-3', title, kids);
    };
    // Sessions at the same time, side by side from md up.
    var parallel = function (time, sessions) {
        var md = sessions.length > 2 ? '4' : '6';
        return kit.tag('li', 'pg-conference-slot', _sdT('Parallel Sessions'), [
            slotTime(time),
            kit.row(_sdT('Sessions'), sessions.map(function (s) {
                return kit.col(_sdT('Session'), [s], '', md);
            }), 'g-3')
        ]);
    };
    var buildAgenda = function () {
        var legend = [];
        Object.keys(TRACKS).forEach(function (key) { legend.push(chip(key, 'li')); });
        var head = fx(kit.row(_sdT('Header'), [
            kit.col(_sdT('Title'), [
                eyebrow(_sdT('Day 1')),
                kit.h('h2', _sdT('Building what matters'), 'display-5 fw-bold mb-3'),
                kit.p(_sdT('Two days, four tracks and a keynote to open each morning. Choose your sessions on the day; every talk is recorded.'), 'lead text-body-secondary mb-0')
            ], '7'),
            kit.col(_sdT('Tracks'), [
                kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 justify-content-lg-end mb-3', _sdT('Tracks'), legend),
                kit.div('d-flex flex-wrap gap-2 justify-content-lg-end', _sdT('Buttons'), [
                    kit.link(_sdT('Register'), '#', 'btn btn-primary px-4', _sdT('Registration Link')),
                    second(_sdT('See the programme'), _sdT('Programme Link'), '')
                ])
            ], '5')
        ], 'g-4 align-items-end mb-5'), { effect: 'fade', trigger: 'view', duration: 700 });
        var slots = fx(kit.tag('ol', 'pg-conference-agenda', _sdT('Sessions'), [
            pause('08:30', 'bi-cup-hot', _sdT('Registration and breakfast'), _sdT('Foyer')),
            keynote('09:45', _sdT('Opening keynote: Calm teams ship better products'), _sdT('Elif Arslan'), _sdT('Chief Product Officer'), _sdT('Northwind Labs'), 'conference-keynote', _sdT('Main Hall')),
            pause('10:45', 'bi-cup-hot', _sdT('Coffee break'), _sdT('Foyer')),
            parallel('11:15', [
                session('product', _sdT('One design system, eleven languages'), _sdT('Ayşe Korkmaz'), _sdT('Talk'), 'bi-easel', _sdT('Hall A')),
                session('engineering', _sdT('Boring infrastructure on purpose'), _sdT('Marcus Lindqvist'), _sdT('Talk'), 'bi-easel', _sdT('Hall B')),
                session('growth', _sdT('Growing a newsletter without ads'), _sdT('Daniel Okafor'), _sdT('Talk'), 'bi-easel', _sdT('Hall C'))
            ]),
            pause('12:30', 'bi-cup-hot', _sdT('Lunch and the sponsors\' area'), _sdT('Foyer')),
            parallel('14:00', [
                session('product', _sdT('Workshop: Mapping a product\'s first year'), _sdT('Ayşe Korkmaz'), _sdT('Workshop'), 'bi-tools', _sdT('Workshop Room')),
                session('engineering', _sdT('Lightning talks from the community'), '', _sdT('Talk'), 'bi-easel', _sdT('Hall B'))
            ]),
            pause('18:30', 'bi-stars', _sdT('Evening reception on the terrace'), _sdT('Terrace'))
        ]), { effect: 'slide-up', trigger: 'view', duration: 600, stagger: 90 });
        return kit.section('py-5 bg-body-tertiary', _sdT('Conference (Day Agenda)'), [head, slots]);
    };

    // ── Speaker line-up ─────────────────────────────────────────────────
    // A group of the line-up: its name, then its speakers' names in a row,
    // each a link to the speaker's profile.
    var group = function (title, names, giant) {
        return kit.div('pg-conference-group', title, [
            kit.h('h3', title, 'h6 text-uppercase fw-semibold text-body-secondary mb-0'),
            kit.tag('ul', 'pg-conference-names' + (giant ? ' pg-conference-keynotes' : ''), _sdT('Names'), names.map(function (name, i) {
                return kit.tag('li', i < names.length - 1 ? 'pg-conference-slash' : '', name, [
                    kit.link(name, '#', 'link-body-emphasis link-underline-opacity-0 link-underline-opacity-100-hover link-offset-2', _sdT('Speaker Link'))
                ]);
            }))
        ]);
    };
    var buildLineup = function () {
        var head = fx(kit.row(_sdT('Header'), [
            kit.col(_sdT('Title'), [
                eyebrow(_sdT('On stage')),
                kit.h('h2', _sdT('The line-up'), 'display-5 fw-bold mb-3'),
                kit.p(_sdT('Two keynotes open the mornings; the people behind the products you use fill the four tracks.'), 'lead text-body-secondary mb-0')
            ], '8')
        ], 'mb-4'), { effect: 'fade', trigger: 'view', duration: 700 });
        var groups = fx(kit.div('pg-conference-lineup', _sdT('Line-up'), [
            group(_sdT('Keynotes'), [_sdT('Elif Arslan'), _sdT('Zeynep Yıldız')], true),
            group(TRACKS.product.name, [_sdT('Ayşe Korkmaz'), _sdT('Emily Turner')]),
            group(TRACKS.engineering.name, [_sdT('Marcus Lindqvist'), _sdT('Sofia Marino'), _sdT('Michael Brooks')]),
            group(TRACKS.growth.name, [_sdT('Daniel Okafor'), _sdT('Kerem Doğan'), _sdT('Jane Cooper')]),
            group(TRACKS.leadership.name, [_sdT('Hannah Weiss'), _sdT('Daniel Ross')])
        ]), { effect: 'slide-up', trigger: 'view', duration: 700, stagger: 120 });
        return kit.section('py-5 overflow-hidden', _sdT('Conference (Speaker Line-up)'), [
            head,
            groups,
            kit.div('d-flex flex-wrap align-items-center justify-content-between gap-3 border-top pt-4', _sdT('Footer'), [
                kit.p(_sdT('And more than thirty others, in talks, workshops and the lightning round.'), 'text-body-secondary mb-0'),
                kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                    kit.link(_sdT('All speakers'), '#', 'btn btn-primary px-4', _sdT('Speakers')),
                    second(_sdT('Propose a talk'), _sdT('Propose a talk'), '')
                ])
            ])
        ]);
    };

    return {
        prefix: 'conference-',
        group: { title: _sdT('Conference'), icon: 'bi-mic' },
        items: [
            { label: _sdT('Conference: Opening Hero'),    type: 'component', icon: 'bi-person-badge',        extra: { componentType: 'conference-hero' },
              desc: _sdT('A dark band with the conference\'s line in giant type, two buttons, figures that count up and an attendee\'s badge on its lanyard.') },
            { label: _sdT('Conference: Day Agenda'),      type: 'component', icon: 'bi-layout-three-columns', extra: { componentType: 'conference-agenda' },
              desc: _sdT('One day by the hour: breaks as quiet lines, the keynote across the width, parallel sessions side by side with track, speaker and room.') },
            { label: _sdT('Conference: Speaker Line-up'), type: 'component', icon: 'bi-megaphone',           extra: { componentType: 'conference-lineup' },
              desc: _sdT('The speakers set like a festival poster: keynotes in giant type, each track\'s names in a row, links to all speakers and to propose a talk.') }
        ],
        build: function (ctype) {
            if (ctype === 'conference-hero') return buildHero();
            if (ctype === 'conference-agenda') return buildAgenda();
            if (ctype === 'conference-lineup') return buildLineup();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
