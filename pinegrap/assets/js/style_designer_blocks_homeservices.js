/**
 * Pinegrap - Enterprise Website Platform
 *
 * Home Services block family: the sections a home services company's site
 * (cleaning, plumbing, electrical work, repairs) needs that the general
 * palette lacks. The hero with a job sheet (the way in: the company in a
 * line, the booking and estimate buttons and the emergency line, beside a
 * visit's job sheet: the job, the arrival window, where, the price agreed
 * first, the guarantee, what the technician brings and who is coming) and
 * the emergency line (a dark band with the number to call, how soon a
 * technician comes, the jobs that cannot wait, and what to do safely in the
 * minutes before the technician arrives). The arrival windows are a general
 * booking block (Booking: Arrival Windows); the services, the price guide,
 * the steps, the team, the reviews and the areas are general blocks too.
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
        // The job sheet: a paper with a stripe in the theme's colour on top
        // of a second sheet tinted with it and turned a little; the job's
        // number in the monospace face and a stamp that says the visit is
        // confirmed.
        'pg-homeservices-sheet': '.pg-homeservices-sheet { position: relative; isolation: isolate; } ' +
                                 '.pg-homeservices-sheet::before { content: ""; position: absolute; inset: 0; z-index: -1; border-radius: var(--bs-border-radius-xl); ' +
                                 'background-color: var(--bs-primary-bg-subtle); transform: rotate(2deg) translate(.375rem, .25rem); } ' +
                                 '.pg-homeservices-sheet .pg-homeservices-paper { border-top: .375rem solid var(--bs-primary); border-radius: var(--bs-border-radius-xl); ' +
                                 'background-color: var(--bs-body-bg); box-shadow: 0 1.5rem 3rem -1.25rem rgba(var(--bs-emphasis-color-rgb), .3); } ' +
                                 '.pg-homeservices-sheet .pg-homeservices-no { font-family: var(--bs-font-monospace); } ' +
                                 '.pg-homeservices-sheet .pg-homeservices-stamp { display: inline-block; padding: .125rem .5rem; border: 2px solid currentColor; ' +
                                 'border-radius: var(--bs-border-radius); font-weight: 700; letter-spacing: .06em; color: var(--bs-success-text-emphasis); transform: rotate(-6deg); } ' +
                                 '.pg-homeservices-sheet img { width: 3rem; height: 3rem; }',
        // The visit's facts under a dashed line, as on a tear-off form: the
        // label with its icon on the left, the fact on the right. Classes
        // and descendant selectors only, so the canvas (an .sd-wrap around
        // every node) draws the rows the same.
        'pg-homeservices-facts': '.pg-homeservices-facts { margin: 0; padding-top: .25rem; border-top: 2px dashed var(--bs-border-color); } ' +
                                 '.pg-homeservices-facts .pg-homeservices-fact { display: grid; grid-template-columns: 7.5rem 1fr; gap: .75rem; padding: .625rem 0; ' +
                                 'border-bottom: 1px solid var(--bs-border-color-translucent); } ' +
                                 '.pg-homeservices-facts dt { display: flex; align-items: center; gap: .5rem; font-weight: 400; color: var(--bs-secondary-color); } ' +
                                 '.pg-homeservices-facts dd { margin: 0; font-weight: 600; }',
        // The emergency line's phone mark: three rings go out from it when
        // the page opens, then it rests; none under reduced motion.
        'pg-homeservices-call':  '.pg-homeservices-call { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 3.5rem; height: 3.5rem; ' +
                                 'border-radius: 50%; font-size: 1.5rem; animation: pg-homeservices-ring 1.8s ease-out 3; } ' +
                                 '@keyframes pg-homeservices-ring { from { box-shadow: 0 0 0 0 rgba(var(--bs-danger-rgb), .55); } to { box-shadow: 0 0 0 1.25rem rgba(var(--bs-danger-rgb), 0); } } ' +
                                 '@media (prefers-reduced-motion: reduce) { .pg-homeservices-call { animation: none; } }',
        // What to do before the technician arrives: numbered steps, each
        // number in a round mark in the warning tint. The gap is the list's,
        // as a sibling selector would miss the canvas's wrapped items.
        'pg-homeservices-safety': '.pg-homeservices-safety { display: grid; gap: 1.25rem; counter-reset: pg-homeservices-step; margin: 0; padding: 0; list-style: none; } ' +
                                  '.pg-homeservices-safety li { position: relative; padding-left: 3rem; counter-increment: pg-homeservices-step; } ' +
                                  '.pg-homeservices-safety li::before { content: counter(pg-homeservices-step); position: absolute; top: 0; left: 0; display: flex; ' +
                                  'align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 50%; font-weight: 700; ' +
                                  'color: var(--bs-warning-text-emphasis); background-color: var(--bs-warning-bg-subtle); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'homeservices-jobsheet':  ['pg-homeservices-sheet', 'pg-homeservices-facts'],
        'homeservices-emergency': ['pg-homeservices-call', 'pg-homeservices-safety']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };

    // The small spaced line above a title.
    var eyebrow = function (text, cls) {
        return kit.p(text, 'small text-uppercase fw-semibold ' + (cls || 'text-primary-emphasis mb-2'));
    };

    // A dark band in every look and mode; the palette's accent stays.
    var dark = [{ name: 'data-bs-theme', value: 'dark' }];

    // The sample emergency number, the same in both blocks.
    var phone = '+90 555 000 00 99', phoneHref = 'tel:+905550000099';

    // ── Hero with a job sheet ───────────────────────────────────────────
    // A fact on the job sheet: its label with an icon, and the fact.
    var fact = function (icon, label, text) {
        return kit.div('pg-homeservices-fact', label, [
            kit.tag('dt', '', _sdT('Label'), [kit.icon(icon, 'text-primary'), kit.h('span', label)]),
            kit.tag('dd', '', _sdT('Value'), [kit.h('span', text)])
        ]);
    };
    // A thing the technician brings, ticked off.
    var tick = function (text) {
        return kit.tag('li', 'd-flex align-items-start gap-2 mb-2', text, [
            kit.icon('bi-check-circle-fill', 'text-success flex-shrink-0'),
            kit.h('span', text)
        ]);
    };
    var buildJobSheet = function () {
        var words = fx(kit.div('', _sdT('Hero Text'), [
            eyebrow(_sdT('One call for every job at home'), 'text-primary-emphasis mb-3'),
            kit.h('h1', _sdT('Your home, looked after'), 'display-4 fw-bold mb-3'),
            kit.p(_sdT('Cleaning, plumbing, electrical work and repairs, with the price agreed before we start and a twelve-month guarantee on the work.'), 'lead text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Book a Visit'), '#', 'btn btn-primary btn-lg px-4', _sdT('Booking Link')),
                kit.link(_sdT('Get a Free Estimate'), '#', 'btn btn-outline-primary btn-lg px-4', _sdT('Estimate Link'))
            ]),
            kit.div('d-flex flex-wrap align-items-center gap-2 small mt-4', _sdT('Emergency Line'), [
                kit.icon('bi-telephone-fill', 'text-danger'),
                kit.h('span', _sdT('Burst pipe or no power? Our emergency line:'), 'text-body-secondary'),
                kit.link(phone, phoneHref, 'fw-semibold link-primary text-nowrap', _sdT('Emergency Link'))
            ])
        ]), { effect: 'slide-up', trigger: 'load', duration: 800 });
        var top = kit.div('d-flex align-items-start justify-content-between gap-3 mb-3', _sdT('Job'), [
            kit.div('', _sdT('Title'), [
                kit.div('d-flex gap-2 small text-body-secondary mb-1', _sdT('Job Number'), [
                    kit.h('span', _sdT('Job sheet')),
                    kit.h('span', '#0427', 'pg-homeservices-no')
                ]),
                kit.h('h2', _sdT('Leaking kitchen tap'), 'h4 fw-bold mb-0')
            ]),
            kit.p(_sdT('Confirmed'), 'pg-homeservices-stamp small flex-shrink-0 mt-1 mb-0')
        ]);
        var facts = kit.tag('dl', 'pg-homeservices-facts mb-4', _sdT('Visit Details'), [
            fact('bi-clock', _sdT('Arrival time'), _sdT('Tuesday, 08:00 to 12:00')),
            fact('bi-geo-alt', _sdT('Where'), _sdT('Kitchen, second-floor flat')),
            fact('bi-tag', _sdT('Price'), _sdT('Fixed, agreed before we start')),
            fact('bi-shield-check', _sdT('Guarantee'), _sdT('Twelve months on the work'))
        ]);
        var brings = kit.div('', _sdT('Checklist'), [
            kit.h('h3', _sdT('We bring'), 'h6 fw-semibold mb-2'),
            fx(kit.tag('ul', 'list-unstyled small mb-0', _sdT('List'), [
                tick(_sdT('The parts the job is likely to need')),
                tick(_sdT('Shoe covers and dust sheets')),
                tick(_sdT('A message when we are on the way'))
            ]), { effect: 'slide-up', trigger: 'load', duration: 600, delay: 900, stagger: 150 })
        ]);
        var who = kit.div('d-flex align-items-center gap-3 pt-3 mt-3 border-top', _sdT('Technician'), [
            kit.img('homeservices-technician', 96, 96, _sdT('The technician coming to the visit'), 'rounded-circle object-fit-cover flex-shrink-0'),
            kit.div('', _sdT('Name'), [
                kit.p(_sdT('Mert Yılmaz'), 'fw-semibold mb-0'),
                kit.p(_sdT('Plumber, eight years with us'), 'small text-body-secondary mb-0')
            ])
        ]);
        var sheet = fx(kit.div('pg-homeservices-sheet', _sdT('Job Sheet'), [
            kit.div('pg-homeservices-paper p-4', _sdT('Paper'), [top, facts, brings, who])
        ]), { effect: 'scale', trigger: 'load', duration: 900, delay: 250 });
        return kit.section('py-5 overflow-hidden', _sdT('Home Services (Hero with Job Sheet)'), [
            kit.row(_sdT('Hero Row'), [
                kit.col(_sdT('Hero'), [words], '6'),
                kit.col(_sdT('Job Sheet'), [sheet], '6')
            ], 'align-items-center g-lg-5 py-lg-4')
        ]);
    };

    // ── Emergency line ──────────────────────────────────────────────────
    // A promise beside the number, with its icon.
    var promise = function (icon, text) {
        return kit.tag('li', 'd-inline-flex align-items-center gap-2', text, [
            kit.icon(icon, 'text-danger-emphasis'),
            kit.h('span', text)
        ]);
    };
    // A job that cannot wait.
    var chip = function (text) {
        return kit.tag('li', 'badge rounded-pill border bg-body-tertiary text-body fw-normal', text, [kit.h('span', text)]);
    };
    // A step to take before the technician arrives: what, then how.
    var step = function (title, text) {
        return kit.tag('li', '', title, [
            kit.p(title, 'fw-semibold mb-1'),
            kit.p(text, 'small text-body-secondary mb-0')
        ]);
    };
    var buildEmergency = function () {
        var line = kit.div('d-flex align-items-center gap-3 mb-4', _sdT('Emergency Line'), [
            kit.tag('span', 'pg-homeservices-call text-bg-danger', _sdT('Icon'), [kit.icon('bi-telephone-fill')], [{ name: 'aria-hidden', value: 'true' }]),
            kit.div('', _sdT('Phone Number'), [
                kit.p(_sdT('Emergency line, day and night'), 'small text-body-secondary mb-0'),
                kit.link(phone, phoneHref, 'fs-3 fw-bold link-body-emphasis link-underline-opacity-0 text-nowrap', _sdT('Emergency Link'))
            ])
        ]);
        var words = fx(kit.div('', _sdT('Text'), [
            eyebrow(_sdT('Emergencies'), 'text-danger-emphasis mb-2'),
            kit.h('h2', _sdT('Burst pipe, no power, locked out?'), 'display-6 fw-bold mb-3'),
            kit.p(_sdT('Call the emergency line. A technician is on the way within the hour, day and night, every day.'), 'lead text-body-secondary mb-4'),
            line,
            kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-3 small mb-4', _sdT('Promises'), [
                promise('bi-stopwatch', _sdT('On the way within the hour')),
                promise('bi-moon-stars', _sdT('Day and night, every day')),
                promise('bi-tag', _sdT('Emergency rate agreed on the phone'))
            ]),
            kit.h('h3', _sdT('We come out for'), 'h6 fw-semibold mb-2'),
            kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-4', _sdT('Emergencies'), [
                chip(_sdT('Burst pipe')),
                chip(_sdT('Leak through the ceiling')),
                chip(_sdT('No power')),
                chip(_sdT('Blocked drain')),
                chip(_sdT('No heating or hot water')),
                chip(_sdT('Locked out')),
                chip(_sdT('Broken window'))
            ]),
            kit.link(_sdT('Not urgent? Book a visit instead'), '#', 'small link-body-emphasis link-offset-2', _sdT('Booking Link'))
        ]), { effect: 'slide-up', trigger: 'view', duration: 800 });
        var card = fx(kit.div('p-4 p-lg-5 rounded-4 border bg-body-tertiary', _sdT('Before We Arrive'), [
            kit.h('h3', _sdT('Before we arrive'), 'h4 fw-bold mb-1'),
            kit.p(_sdT('What you can do safely while you wait.'), 'small text-body-secondary mb-4'),
            fx(kit.tag('ol', 'pg-homeservices-safety mb-4', _sdT('Steps'), [
                step(_sdT('Turn off the water'), _sdT('Close the stopcock, usually under the kitchen sink or beside the water meter.')),
                step(_sdT('Make the electrics safe'), _sdT('If water is near sockets or lights, switch off the power at the fuse box. Do not touch wet switches.')),
                step(_sdT('Catch the water'), _sdT('Put a bucket under the leak and move what you can out of its way.')),
                step(_sdT('Take photos'), _sdT('Pictures of the damage help your insurer, and help the technician bring the right parts.'))
            ]), { effect: 'slide-up', trigger: 'view', duration: 600, delay: 300, stagger: 120 }),
            kit.div('d-flex gap-3 p-3 rounded-3 bg-warning-subtle text-warning-emphasis', _sdT('Gas Warning'), [
                kit.icon('bi-exclamation-triangle-fill', 'fs-5 flex-shrink-0'),
                kit.p(_sdT('Smell gas? Do not use switches or flames. Leave the building first, then call the gas emergency number.'), 'small mb-0')
            ])
        ]), { effect: 'slide-up', trigger: 'view', duration: 800, delay: 150 });
        return kit.tag('section', 'py-5 bg-body text-body overflow-hidden', _sdT('Home Services (Emergency Line)'), [
            kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                kit.row(_sdT('Row'), [
                    kit.col(_sdT('Emergency Line'), [words], '6'),
                    kit.col(_sdT('Before We Arrive'), [card], '6')
                ], 'align-items-center g-lg-5')
            ])
        ], dark);
    };

    return {
        prefix: 'homeservices-',
        group: { title: _sdT('Home Services'), icon: 'bi-tools' },
        items: [
            { label: _sdT('Home Services: Hero with Job Sheet'), type: 'component', icon: 'bi-clipboard-check', extra: { componentType: 'homeservices-jobsheet' } },
            { label: _sdT('Home Services: Emergency Line'),      type: 'component', icon: 'bi-telephone-plus',  extra: { componentType: 'homeservices-emergency' } }
        ],
        build: function (ctype) {
            if (ctype === 'homeservices-jobsheet') return buildJobSheet();
            if (ctype === 'homeservices-emergency') return buildEmergency();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
