/**
 * Pinegrap - Enterprise Website Platform
 *
 * Online Courses block family (prefix courses-): the sections of an online
 * school's site the palette lacks. The classroom hero (the school's line and
 * two buttons beside a lesson player: the free lesson's picture with a play
 * button, how far it has played, the lesson and its instructor, and the
 * lessons up next, locked until one enrols), the course shelf (the courses
 * as cards with their subject, a level meter, the instructor, the length and
 * the price, the subjects as links above them) and the curriculum (one
 * course's modules on a dashed path, each module's lessons with their length
 * and the free preview marked, the course's project at the end). The plans,
 * the instructors, the figures, the students' words and the questions are
 * general blocks (Pricing, Team, Stats, Testimonials, FAQ). Motion comes from
 * the nodes' interactions (props._fx, played by pg_fx.src.js, which honours
 * reduced motion).
 *
 * Registered with StyleDesigner.registerBlockFamily() and bundled after the
 * designer core (pg_designer_js_sources()); see
 * docs/memory/designer/designer-blocks-courses.md.
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
        // The classroom band: the theme's colour glows faintly behind the
        // player where the browser mixes colours; an older one shows the
        // plain page. The title's lines are balanced.
        'pg-courses-hero':       '@supports (background: color-mix(in srgb, red 50%, blue)) { .pg-courses-hero { background-image: ' +
                                 'radial-gradient(ellipse 50% 70% at 80% 45%, color-mix(in srgb, var(--bs-primary) 14%, transparent), transparent 70%); } } ' +
                                 '.pg-courses-hero h1 { text-wrap: balance; }',
        // The lesson player: a card on a second sheet in the theme's tint,
        // set a little lower and to the end (less on a phone, so it stays
        // inside the page). On the picture a round play button that grows
        // under the pointer and draws its own focus ring; under it the bar of
        // how far the lesson has played. Times line up as figures.
        'pg-courses-player':     '.pg-courses-desk { position: relative; isolation: isolate; } ' +
                                 '.pg-courses-desk::before { content: ""; position: absolute; z-index: -1; inset-block: 1rem -.75rem; inset-inline: 1rem -.75rem; ' +
                                 'border-radius: var(--bs-border-radius-xl); background-color: var(--bs-primary-bg-subtle); } ' +
                                 '@media (min-width: 992px) { .pg-courses-desk::before { inset-block: 1.75rem -1.75rem; inset-inline: 1.75rem -1.75rem; } } ' +
                                 '.pg-courses-player .pg-courses-screen { position: relative; } ' +
                                 '.pg-courses-player .pg-courses-play { position: absolute; top: 50%; left: 50%; z-index: 2; display: inline-flex; align-items: center; justify-content: center; ' +
                                 'width: 4rem; height: 4rem; padding: 0; font-size: 1.75rem; transform: translate(-50%, -50%); transition: transform .25s ease; ' +
                                 'box-shadow: 0 0 0 .5rem var(--bs-primary-bg-subtle); } ' +
                                 '.pg-courses-player .pg-courses-play:hover, .pg-courses-player .pg-courses-play:focus-visible { transform: translate(-50%, -50%) scale(1.1); } ' +
                                 '.pg-courses-player .pg-courses-play:focus-visible { outline: 0; box-shadow: 0 0 0 .25rem var(--bs-body-bg), 0 0 0 .5rem var(--bs-primary); } ' +
                                 '.pg-courses-player .pg-courses-scrub { position: relative; height: .3125rem; background-color: var(--bs-secondary-bg); } ' +
                                 '.pg-courses-player .pg-courses-scrub::before { content: ""; position: absolute; inset-block: 0; inset-inline-start: 0; width: 35%; background-color: var(--bs-primary); } ' +
                                 '.pg-courses-player .pg-courses-face { width: 2.5rem; height: 2.5rem; flex-shrink: 0; } ' +
                                 '.pg-courses-player time { font-variant-numeric: tabular-nums; } ' +
                                 '@media (prefers-reduced-motion: reduce) { .pg-courses-player .pg-courses-play { transition: none; } }',
        // The course cards rise a little under the pointer or with keyboard
        // focus inside; the instructor's face is small and round, the price
        // in figures that line up.
        'pg-courses-shelf':      '.pg-courses-shelf .pg-courses-card { transition: transform .2s ease, box-shadow .2s ease; } ' +
                                 '.pg-courses-shelf .pg-courses-card:hover, .pg-courses-shelf .pg-courses-card:focus-within { transform: translateY(-4px); box-shadow: var(--bs-box-shadow); } ' +
                                 '.pg-courses-shelf .pg-courses-face { width: 2rem; height: 2rem; flex-shrink: 0; } ' +
                                 '.pg-courses-shelf .pg-courses-price { font-variant-numeric: tabular-nums; } ' +
                                 '@media (prefers-reduced-motion: reduce) { .pg-courses-shelf .pg-courses-card { transition: none; } ' +
                                 '.pg-courses-shelf .pg-courses-card:hover, .pg-courses-shelf .pg-courses-card:focus-within { transform: none; } }',
        // A course's level as three rising bars, filled in the theme's colour
        // up to its level; the level's name always stands beside it.
        'pg-courses-level':      '.pg-courses-level { display: inline-flex; align-items: flex-end; gap: 2px; height: .875rem; } ' +
                                 '.pg-courses-level .pg-courses-bar { display: block; width: .3125rem; height: .375rem; border-radius: 1px; background-color: var(--bs-border-color); } ' +
                                 '.pg-courses-level .pg-courses-mid { height: .625rem; } ' +
                                 '.pg-courses-level .pg-courses-tall { height: .875rem; } ' +
                                 '.pg-courses-level .pg-courses-on { background-color: var(--bs-primary); }',
        // The curriculum: each module hangs on a dashed path from its round
        // marker to the next one; the project, the last stop, ends it. The
        // course's facts stand in two columns.
        'pg-courses-modules':    '.pg-courses-modules { margin: 0; padding: 0; list-style: none; } ' +
                                 '.pg-courses-modules .pg-courses-module { position: relative; padding-inline-start: 3.75rem; padding-bottom: 2.5rem; } ' +
                                 '.pg-courses-modules .pg-courses-module::before { content: ""; position: absolute; top: 3.25rem; bottom: .5rem; inset-inline-start: 1.3125rem; ' +
                                 'border-inline-start: 2px dashed var(--bs-border-color); } ' +
                                 '.pg-courses-modules .pg-courses-project { padding-bottom: 0; } ' +
                                 '.pg-courses-modules .pg-courses-project::before { content: none; } ' +
                                 '.pg-courses-modules .pg-courses-marker { position: absolute; top: 0; inset-inline-start: 0; display: flex; align-items: center; justify-content: center; ' +
                                 'width: 2.75rem; height: 2.75rem; border-radius: 50%; font-weight: 700; font-variant-numeric: tabular-nums; } ' +
                                 '.pg-courses-modules time { font-variant-numeric: tabular-nums; } ' +
                                 '.pg-courses-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem 1.5rem; margin: 0; } ' +
                                 '.pg-courses-facts dd { margin: 0; }',
        // The secondary button's focus ring, in the body's strongest colour:
        // a button without a colour variant has no ring colour of its own,
        // so the keyboard focus would not show.
        'pg-courses-second':     '.pg-courses-second { --bs-btn-focus-shadow-rgb: var(--bs-emphasis-color-rgb); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'courses-hero':       ['pg-courses-hero', 'pg-courses-player', 'pg-courses-second'],
        'courses-shelf':      ['pg-courses-shelf', 'pg-courses-level', 'pg-courses-second'],
        'courses-curriculum': ['pg-courses-modules', 'pg-courses-level', 'pg-courses-second']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };
    var hidden = [{ name: 'aria-hidden', value: 'true' }];

    // The small spaced line above a title, in the body's second colour: small
    // text in the theme's colour is under 4.5:1 on a grey band or a dark
    // page with some palettes. Icons keep the theme's colour (3:1 is enough).
    var eyebrow = function (text, cls) {
        return kit.p(text, 'small text-uppercase fw-semibold text-body-secondary ' + (cls || 'mb-2'));
    };
    // A secondary button: the body's text colour and a border, readable on
    // any surface in both modes, with its own focus ring (pg-courses-second).
    var second = function (text, name, size) {
        return kit.link(text, '#', 'btn ' + (size ? size + ' ' : '') + 'border px-4 link-body-emphasis pg-courses-second', name);
    };
    // An icon and a short text side by side, as one line of details.
    var detail = function (icon, text, cls) {
        return kit.tag('span', 'd-inline-flex align-items-center gap-1' + (cls ? ' ' + cls : ''), text, [
            kit.icon(icon),
            kit.h('span', text)
        ]);
    };
    // A length in minutes, machine-readable as a duration.
    var minutes = function (n, cls) {
        return kit.time('PT' + n + 'M', _sdT('{var} min', String(n)), cls || 'small text-body-secondary text-nowrap', _sdT('Length'));
    };
    // What a screen reader hears beside a locked lesson; the lock is a
    // picture only.
    var locked = function () {
        return kit.h('span', _sdT('Enrolled students only'), 'visually-hidden');
    };
    // The free preview's mark: a pill at the size of the text around it (a
    // badge would be a quarter smaller still beside small text).
    var preview = function () {
        return kit.h('span', _sdT('Free preview'), 'd-inline-block rounded-pill px-2 lh-base fw-semibold text-nowrap bg-success-subtle text-success-emphasis');
    };

    // The subjects the courses belong to: their icon and name on a pill in a
    // Bootstrap tint, so they read apart without the theme's colour standing
    // for one of them; the name always goes with the colour.
    var SUBJECTS = {
        web:    { icon: 'bi-code-slash',     name: _sdT('Web Development'),  tone: 'primary' },
        data:   { icon: 'bi-bar-chart-line', name: _sdT('Data & Analytics'), tone: 'success' },
        design: { icon: 'bi-palette',        name: _sdT('Design'),           tone: 'danger' }
    };
    var LEVELS = [_sdT('Beginner'), _sdT('Intermediate'), _sdT('Advanced')];

    // A course's level: three bars filled up to it, then its name.
    var level = function (n) {
        var bar = function (i, cls) {
            return kit.tag('span', 'pg-courses-bar' + (cls ? ' ' + cls : '') + (i <= n ? ' pg-courses-on' : ''), _sdT('Bar'), []);
        };
        return kit.tag('span', 'd-inline-flex align-items-center gap-2 small fw-semibold text-body-emphasis', _sdT('Level'), [
            kit.tag('span', 'pg-courses-level', _sdT('Level Meter'), [bar(1, ''), bar(2, 'pg-courses-mid'), bar(3, 'pg-courses-tall')], hidden),
            kit.h('span', LEVELS[n - 1])
        ]);
    };

    // ── Classroom hero ──────────────────────────────────────────────────
    // A lesson up next: locked until one enrols, its title and its length.
    var upNext = function (title, n) {
        return kit.tag('li', 'd-flex align-items-center gap-3 py-2 border-top', title, [
            kit.icon('bi-lock', 'text-body-secondary'),
            kit.p(title, 'flex-grow-1 small fw-semibold text-body-emphasis mb-0'),
            minutes(n),
            locked()
        ]);
    };
    var buildHero = function () {
        var words = fx(kit.div('', _sdT('Hero Text'), [
            kit.tag('p', 'd-inline-flex align-items-center gap-2 small text-uppercase fw-semibold text-body-secondary mb-3', _sdT('Tagline'), [
                kit.icon('bi-play-circle', 'text-primary-emphasis'),
                kit.h('span', _sdT('Learn online, at your own pace'))
            ]),
            kit.h('h1', _sdT('Learn a skill you will use on Monday'), 'display-4 fw-bold mb-3'),
            kit.p(_sdT('Short video lessons, a project in every course and instructors who answer your questions. Start with a free lesson; keep the course once you buy it.'), 'lead text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap gap-2 mb-4', _sdT('Buttons'), [
                kit.link(_sdT('Browse courses'), '#', 'btn btn-primary btn-lg px-4', _sdT('Courses Link')),
                second(_sdT('Watch a free lesson'), _sdT('Free Lesson Link'), 'btn-lg')
            ]),
            kit.tag('ul', 'list-unstyled d-flex flex-wrap column-gap-4 row-gap-2 small text-body-secondary mb-0', _sdT('Promises'), [
                kit.tag('li', '', _sdT('First lesson free'), [detail('bi-unlock', _sdT('First lesson free'), 'gap-2')]),
                kit.tag('li', '', _sdT('No end date'), [detail('bi-infinity', _sdT('No end date'), 'gap-2')]),
                kit.tag('li', '', _sdT('Instructors who answer'), [detail('bi-chat-dots', _sdT('Instructors who answer'), 'gap-2')])
            ])
        ]), { effect: 'slide-up', trigger: 'load', duration: 800 });
        var player = fx(kit.div('pg-courses-desk', _sdT('Desk'), [
            kit.tag('article', 'pg-courses-player card border-0 shadow-lg rounded-4 overflow-hidden', _sdT('Lesson Player'), [
                kit.div('pg-courses-screen', _sdT('Screen'), [
                    kit.div('ratio ratio-16x9 bg-body-tertiary', _sdT('Picture'), [
                        kit.img('courses-lesson', 1200, 675, _sdT('An instructor\'s screen with a web page and its code side by side'), 'object-fit-cover')
                    ]),
                    kit.iconLink('bi-play-fill', _sdT('Play the free lesson'), 'pg-courses-play btn btn-primary rounded-circle', '#')
                ]),
                kit.div('pg-courses-scrub', _sdT('Progress'), [], hidden),
                kit.div('card-body p-4', _sdT('Card Body'), [
                    kit.tag('p', 'd-flex flex-wrap align-items-center column-gap-3 row-gap-1 small text-body-secondary mb-2', _sdT('Course Line'), [
                        detail('bi-collection-play', _sdT('Build Your First Website')),
                        preview()
                    ]),
                    kit.h('h2', _sdT('How a web page works'), 'h4 fw-bold mb-3'),
                    kit.div('d-flex align-items-center gap-3 mb-4', _sdT('Instructor'), [
                        kit.img('courses-selin', 80, 80, _sdT('Selin Aydın'), 'pg-courses-face rounded-circle object-fit-cover'),
                        kit.div('flex-grow-1', _sdT('Text'), [
                            kit.p(_sdT('Selin Aydın'), 'small fw-semibold text-body-emphasis mb-0'),
                            kit.p(_sdT('Senior Front-End Engineer'), 'small text-body-secondary mb-0')
                        ]),
                        minutes(12)
                    ]),
                    kit.h('h3', _sdT('Up next'), 'small text-uppercase fw-semibold text-body-secondary mb-1'),
                    kit.tag('ol', 'list-unstyled mb-0', _sdT('Lessons'), [
                        upNext(_sdT('Your first page in HTML'), 18),
                        upNext(_sdT('Colours, fonts and spacing with CSS'), 22),
                        upNext(_sdT('A layout that fits every screen'), 25)
                    ])
                ])
            ])
        ]), { effect: 'scale', trigger: 'load', duration: 900, delay: 200 });
        return kit.tag('section', 'pg-courses-hero py-5 overflow-hidden', _sdT('Online Courses (Classroom Hero)'), [
            kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                kit.row(_sdT('Hero Row'), [
                    kit.col(_sdT('Hero Text'), [words], '6'),
                    kit.col(_sdT('Lesson Player'), [player], '6')
                ], 'g-lg-5 align-items-center')
            ])
        ]);
    };

    // ── Course shelf ────────────────────────────────────────────────────
    // A subject as a pill link to its courses.
    var subjectLink = function (key) {
        var s = SUBJECTS[key];
        return kit.tag('li', '', s.name, [
            kit.createNode('semantic', { tag: 'a', cssClass: 'btn rounded-pill border-0 d-inline-flex align-items-center gap-2 px-3 py-1 bg-' + s.tone + '-subtle text-' + s.tone + '-emphasis', href: '#', target: '', customName: s.name }, [
                kit.icon(s.icon),
                kit.h('span', s.name)
            ])
        ]);
    };
    // A course as a card: its picture with the length on it, its subject,
    // title (the card's link), what it teaches, its level, the instructor
    // and the price.
    var card = function (seed, subject, title, summary, lvl, length, teacher, face, price) {
        var s = SUBJECTS[subject];
        return kit.col(title, [
            kit.tag('article', 'pg-courses-card card h-100 border-0 shadow-sm rounded-4 overflow-hidden', title, [
                kit.div('position-relative', _sdT('Picture'), [
                    kit.div('ratio ratio-16x9 bg-body-tertiary', _sdT('Picture Frame'), [
                        kit.img(seed, 800, 450, title, 'object-fit-cover')
                    ]),
                    kit.tag('span', 'position-absolute bottom-0 end-0 m-3 badge rounded-pill bg-body text-body-emphasis d-inline-flex align-items-center gap-1 shadow-sm', _sdT('Length'), [
                        kit.icon('bi-clock'),
                        kit.h('span', length)
                    ])
                ]),
                kit.div('card-body d-flex flex-column p-4', _sdT('Card Body'), [
                    kit.tag('p', 'd-flex align-items-center gap-2 small fw-semibold text-body-secondary mb-2', _sdT('Subject'), [
                        kit.icon(s.icon, 'text-' + s.tone + '-emphasis'),
                        kit.h('span', s.name)
                    ]),
                    kit.tag('h3', 'h5 fw-bold', _sdT('Course Title'), [
                        kit.link(title, '#', 'stretched-link link-body-emphasis text-decoration-none', _sdT('Course Link'))
                    ]),
                    kit.p(summary, 'small text-body-secondary mb-3'),
                    kit.div('mb-3', _sdT('Level'), [level(lvl)]),
                    kit.div('d-flex align-items-center gap-2 border-top pt-3 mt-auto', _sdT('Card Footer'), [
                        kit.img(face, 64, 64, teacher, 'pg-courses-face rounded-circle object-fit-cover'),
                        kit.p(teacher, 'small fw-semibold text-body-emphasis mb-0'),
                        kit.p(price, 'pg-courses-price ms-auto fs-5 fw-bold text-body-emphasis mb-0')
                    ])
                ])
            ])
        ], '4', '6');
    };
    var buildShelf = function () {
        var head = fx(kit.row(_sdT('Header'), [
            kit.col(_sdT('Title'), [
                eyebrow(_sdT('Start here')),
                kit.h('h2', _sdT('Our courses'), 'display-6 fw-bold mb-3'),
                kit.p(_sdT('Every course starts with a free lesson. Pick a subject, watch, and enrol when it fits.'), 'lead text-body-secondary mb-0')
            ], '6'),
            kit.col(_sdT('Subjects'), [
                kit.tag('nav', '', _sdT('Subjects'), [
                    kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 justify-content-lg-end mb-0', _sdT('Subjects'), [
                        subjectLink('web'), subjectLink('data'), subjectLink('design')
                    ])
                ], [{ name: 'aria-label', value: _sdT('Subjects') }])
            ], '6')
        ], 'g-4 align-items-end mb-5'), { effect: 'fade', trigger: 'view', duration: 700 });
        var cards = fx(kit.row(_sdT('Courses'), [
            card('courses-web', 'web', _sdT('Build Your First Website'), _sdT('HTML and CSS from the first tag to a site you can publish, with a small project in every lesson.'),
                1, _sdT('6 hours of video'), _sdT('Selin Aydın'), 'courses-selin', _sdT('$79')),
            card('courses-data', 'data', _sdT('Data Analysis with Spreadsheets'), _sdT('From a messy export to a clear chart: cleaning, formulas, pivot tables and a report people read.'),
                1, _sdT('5 hours of video'), _sdT('Jonas Becker'), 'courses-jonas', _sdT('$89')),
            card('courses-ux', 'design', _sdT('UX Design Fundamentals'), _sdT('Interviews, user flows, wireframes and a clickable prototype you test with real people.'),
                2, _sdT('7 hours of video'), _sdT('Maya Collins'), 'courses-maya', _sdT('$99'))
        ], 'g-4'), { effect: 'slide-up', trigger: 'view', duration: 600, stagger: 100 });
        return kit.tag('section', 'pg-courses-shelf py-5', _sdT('Online Courses (Course Shelf)'), [
            kit.createNode('container', { fluid: false, cssClass: '' }, [
                head,
                cards,
                kit.div('text-center mt-5', _sdT('Footer'), [second(_sdT('All courses'), _sdT('Courses Link'), '')])
            ])
        ]);
    };

    // ── Curriculum ──────────────────────────────────────────────────────
    // A lesson of a module: open (the free preview, a link) or locked, what
    // it covers and its length.
    var lesson = function (title, text, n, open) {
        return kit.tag('li', 'd-flex align-items-start gap-3 py-3 border-top', title, [
            kit.icon(open ? 'bi-play-circle-fill' : 'bi-lock', (open ? 'text-primary-emphasis' : 'text-body-secondary') + ' fs-5 lh-1 mt-1'),
            kit.div('flex-grow-1', _sdT('Lesson'), [
                open ? kit.tag('p', 'd-flex flex-wrap align-items-center column-gap-2 row-gap-1 fw-semibold mb-1', _sdT('Lesson Title'), [
                           kit.link(title, '#', 'link-body-emphasis', _sdT('Lesson Link')),
                           kit.tag('span', 'small', _sdT('Preview'), [preview()])
                       ])
                     : kit.p(title, 'fw-semibold text-body-emphasis mb-1'),
                kit.p(text, 'small text-body-secondary mb-0')
            ]),
            kit.div('flex-shrink-0 text-end', _sdT('Lesson Details'), open ? [minutes(n)] : [minutes(n), locked()])
        ]);
    };
    // A module: its number on the path, its title, how many lessons and
    // minutes it holds, then its lessons.
    var module = function (n, title, total, lessons) {
        return kit.tag('li', 'pg-courses-module', title, [
            kit.tag('span', 'pg-courses-marker bg-body border border-2 border-primary text-body-emphasis', _sdT('Marker'), [kit.h('span', String(n))], hidden),
            kit.h('h3', _sdT('Module {var}', String(n)) + ': ' + title, 'h5 fw-bold mb-1'),
            kit.tag('p', 'd-flex flex-wrap column-gap-3 small text-body-secondary mb-2', _sdT('Module Details'), [
                detail('bi-collection-play', _sdT('{var} lessons', String(lessons.length))),
                detail('bi-clock', _sdT('{var} min', String(total)))
            ]),
            kit.tag('ol', 'list-unstyled mb-0', _sdT('Lessons'), lessons)
        ]);
    };
    var fact = function (term, value) {
        return kit.div('', term, [
            kit.tag('dt', 'small fw-normal text-body-secondary', term, [kit.h('span', term)]),
            kit.tag('dd', 'fw-semibold text-body-emphasis', value, [kit.h('span', value)])
        ]);
    };
    var buildCurriculum = function () {
        var course = fx(kit.div('', _sdT('Course'), [
            eyebrow(_sdT('Inside a course')),
            kit.h('h2', _sdT('Build Your First Website'), 'display-6 fw-bold mb-3'),
            kit.p(_sdT('HTML and CSS from the first tag to a site you can publish, with a small project in every lesson.'), 'text-body-secondary mb-4'),
            kit.tag('dl', 'pg-courses-facts mb-4', _sdT('Facts'), [
                kit.div('', _sdT('Level'), [
                    kit.tag('dt', 'small fw-normal text-body-secondary', _sdT('Level'), [kit.h('span', _sdT('Level'))]),
                    kit.tag('dd', '', _sdT('Level'), [level(1)])
                ]),
                fact(_sdT('Instructor'), _sdT('Selin Aydın')),
                fact(_sdT('Length'), _sdT('6 hours of video')),
                fact(_sdT('Access'), _sdT('No end date'))
            ]),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Watch the free lesson'), '#', 'btn btn-primary px-4', _sdT('Free Lesson Link')),
                second(_sdT('Enrol'), _sdT('Enrol Link'), '')
            ])
        ]), { effect: 'fade', trigger: 'view', duration: 700 });
        var path = fx(kit.tag('ol', 'pg-courses-modules', _sdT('Modules'), [
            module(1, _sdT('Getting started'), 30, [
                lesson(_sdT('How a web page works'), _sdT('What a browser asks for, what the server sends back and the three languages a page is made of.'), 12, true),
                lesson(_sdT('Your first page in HTML'), _sdT('Headings, paragraphs, links and pictures: a page about yourself in twenty lines.'), 18, false)
            ]),
            module(2, _sdT('Layout and style'), 47, [
                lesson(_sdT('Colours, fonts and spacing with CSS'), _sdT('One stylesheet for the whole site, and the few rules that make text easy to read.'), 22, false),
                lesson(_sdT('A layout that fits every screen'), _sdT('Flexible boxes and one media query: the same page on a phone and on a desk.'), 25, false)
            ]),
            kit.tag('li', 'pg-courses-module pg-courses-project', _sdT('Your project'), [
                kit.tag('span', 'pg-courses-marker text-bg-primary', _sdT('Marker'), [kit.icon('bi-flag-fill')], hidden),
                kit.h('h3', _sdT('Your project'), 'h5 fw-bold mb-2'),
                kit.p(_sdT('Publish a one-page site about something you love and send us its address.'), 'mb-2'),
                detail('bi-chat-left-text', _sdT('Written feedback from your instructor'), 'small text-body-secondary gap-2')
            ])
        ]), { effect: 'slide-up', trigger: 'view', duration: 600, stagger: 120 });
        return kit.section('py-5', _sdT('Online Courses (Curriculum)'), [
            kit.row(_sdT('Curriculum'), [
                kit.col(_sdT('Course'), [course], '4'),
                kit.col(_sdT('Modules'), [path], '8')
            ], 'g-lg-5')
        ]);
    };

    return {
        prefix: 'courses-',
        group: { title: _sdT('Online Courses'), icon: 'bi-mortarboard' },
        items: [
            { label: _sdT('Online Courses: Classroom Hero'), type: 'component', icon: 'bi-play-btn',       extra: { componentType: 'courses-hero' },
              desc: _sdT('The school\'s line and two buttons beside a lesson player: the free lesson with a play button, its instructor and the lessons up next.') },
            { label: _sdT('Online Courses: Course Shelf'),   type: 'component', icon: 'bi-collection-play', extra: { componentType: 'courses-shelf' },
              desc: _sdT('The courses as cards with subject, a level meter, instructor, length and price, below the subjects as links.') },
            { label: _sdT('Online Courses: Curriculum'),     type: 'component', icon: 'bi-list-ol',        extra: { componentType: 'courses-curriculum' },
              desc: _sdT('One course\'s modules on a path: each lesson with its length, the free preview marked, the others locked, the project at the end.') }
        ],
        build: function (ctype) {
            if (ctype === 'courses-hero') return buildHero();
            if (ctype === 'courses-shelf') return buildShelf();
            if (ctype === 'courses-curriculum') return buildCurriculum();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
