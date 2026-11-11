/**
 * Pinegrap - Enterprise Website Platform
 *
 * General booking blocks the core's Services & Booking group lacks. The
 * weekly class timetable: the seven days side by side, each with its
 * classes in the order they start (the start as a <time>, the class, who
 * leads it, where and how long, a link to book), for any house that runs
 * classes on a weekly rhythm (a gym, a yoga or dance studio, a pool, a
 * climbing wall, a music school). The arrival windows: the parts of the
 * day a visit or a delivery can be booked in, each with its times drawn on
 * the working day, when it is offered and a note, for anyone who comes to
 * the customer (home services, deliveries, removals, a mobile repair van).
 * The core's Booking: CTA and Booking: Split keep the single booking call.
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
        // The week: one column per day from md up, scrolling sideways when
        // seven columns do not fit; stacked days on a phone.
        'pg-week':      '.pg-week { overflow-x: auto; } ' +
                        '.pg-week > ol { display: grid; gap: 1rem; margin: 0; padding: 0; list-style: none; } ' +
                        '@media (min-width: 768px) { .pg-week > ol { grid-template-columns: repeat(7, minmax(9.5rem, 1fr)); gap: .75rem; } } ' +
                        '.pg-week > ol > li { padding: .75rem; border-radius: var(--bs-border-radius-lg); background: var(--bs-tertiary-bg); }',
        // A class in the week: a rule in the theme's colour beside it, the
        // start in tabular figures.
        'pg-week-slot': '.pg-week-slot { padding: .5rem .75rem; border-left: 3px solid var(--bs-primary); border-radius: var(--bs-border-radius); background: var(--bs-body-bg); } ' +
                        '.pg-week-slot > time { display: block; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--bs-primary-text-emphasis); }',
        // An arrival window: a round icon mark, its times in tabular
        // figures at full strength and its tag set on the card's top edge.
        'pg-window':     '.pg-window { position: relative; } ' +
                         '.pg-window .pg-window-icon { display: inline-flex; align-items: center; justify-content: center; width: 3rem; height: 3rem; } ' +
                         '.pg-window time { font-variant-numeric: tabular-nums; color: var(--bs-emphasis-color); } ' +
                         '.pg-window .pg-window-tag { position: absolute; top: 0; right: 1.5rem; transform: translateY(-50%); }',
        // The working day from 08:00 to 19:00 as a bar, the window's hours
        // filled in the theme's colour, so the windows compare at a glance.
        'pg-window-day': '.pg-window-day { position: relative; height: .5rem; border-radius: 50rem; background-color: var(--bs-secondary-bg); } ' +
                         '.pg-window-day > div { position: absolute; top: 0; bottom: 0; border-radius: inherit; background-color: var(--bs-primary); } ' +
                         '.pg-window-day .pg-window-am { left: 0; width: 36.36%; } ' +
                         '.pg-window-day .pg-window-pm { left: 36.36%; width: 36.36%; } ' +
                         '.pg-window-day .pg-window-eve { left: 72.72%; width: 27.28%; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'booking-week':    ['pg-week', 'pg-week-slot'],
        'booking-windows': ['pg-window', 'pg-window-day']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };

    // ── Weekly class timetable ──────────────────────────────────────────
    // A class on a day: its start, its name, who leads it, where and how
    // long, and a link that books it.
    var slot = function (time, name, who, where, length) {
        return kit.tag('li', 'pg-week-slot mb-2', name, [
            kit.time(time, time, 'small', _sdT('Start Time')),
            kit.p(name, 'fw-semibold small mb-0'),
            kit.p(who + ' · ' + where + ' · ' + length, 'small text-body-secondary mb-1'),
            kit.link(_sdT('Book'), '#', 'small fw-semibold link-primary', _sdT('Book Link'))
        ]);
    };
    // A day: its name and its classes in the order they start.
    var day = function (title, slots) {
        return kit.tag('li', '', title, [
            kit.h('h3', title, 'h6 fw-bold text-uppercase mb-3'),
            kit.tag('ul', 'list-unstyled mb-0', _sdT('Day Classes'), slots)
        ]);
    };
    var buildWeek = function () {
        var m45 = _sdT('{var} min', 45), m60 = _sdT('{var} min', 60), m50 = _sdT('{var} min', 50);
        var deniz = _sdT('Deniz Kaya'), selin = _sdT('Selin Aydın'), marcus = _sdT('Marcus Reed'), ayse = _sdT('Ayşe Demir');
        var main = _sdT('Main Studio'), calm = _sdT('Calm Studio'), cycle = _sdT('Cycle Studio'), floor = _sdT('Weights Floor');
        var head = kit.div('row g-4 align-items-end mb-4', _sdT('Section Header'), [
            kit.div('col-lg-8', _sdT('Timetable Title'), [
                kit.p(_sdT('Class Timetable'), 'small text-uppercase fw-semibold text-primary mb-2'),
                kit.h('h2', _sdT('A week of classes'), 'h2 fw-bold mb-3'),
                kit.p(_sdT('Every class at the same time each week. Pick one and book your place; the front desk confirms it.'), 'text-body-secondary mb-0')
            ]),
            kit.div('col-lg-4 text-lg-end', _sdT('Buttons'), [
                kit.link(_sdT('Book a Class'), '#', 'btn btn-primary px-4', _sdT('Booking Link'))
            ])
        ]);
        return kit.section('py-5', _sdT('Booking (Weekly Timetable)'), [
            fx(head, { effect: 'fade', trigger: 'view', duration: 800 }),
            kit.div('pg-week', _sdT('Week'), [
                fx(kit.tag('ol', '', _sdT('Days'), [
                    day(_sdT('Monday'), [
                        slot('07:00', _sdT('HIIT Burn'), marcus, main, m45),
                        slot('12:30', _sdT('Mat Pilates'), selin, calm, m45),
                        slot('18:30', _sdT('Barbell Strength'), deniz, floor, m60)
                    ]),
                    day(_sdT('Tuesday'), [
                        slot('07:00', _sdT('Rhythm Cycle'), ayse, cycle, m45),
                        slot('18:00', _sdT('Boxing Fit'), marcus, main, m50)
                    ]),
                    day(_sdT('Wednesday'), [
                        slot('07:00', _sdT('Kettlebell Circuit'), deniz, floor, m45),
                        slot('18:30', _sdT('Mat Pilates'), selin, calm, m45),
                        slot('19:30', _sdT('Boxing Fit'), marcus, main, m50)
                    ]),
                    day(_sdT('Thursday'), [
                        slot('07:00', _sdT('Flow Yoga'), selin, calm, m60),
                        slot('18:00', _sdT('Barbell Strength'), deniz, floor, m60)
                    ]),
                    day(_sdT('Friday'), [
                        slot('07:00', _sdT('HIIT Burn'), marcus, main, m45),
                        slot('18:00', _sdT('Kettlebell Circuit'), deniz, floor, m45)
                    ]),
                    day(_sdT('Saturday'), [
                        slot('09:00', _sdT('Rhythm Cycle'), ayse, cycle, m45),
                        slot('10:30', _sdT('Barbell Strength'), deniz, floor, m60)
                    ]),
                    day(_sdT('Sunday'), [
                        slot('10:00', _sdT('Flow Yoga'), selin, calm, m60)
                    ])
                ]), { effect: 'slide-up', trigger: 'view', stagger: 80 })
            ], kit.region(_sdT('Class Timetable')))
        ]);
    };

    // ── Arrival windows ─────────────────────────────────────────────────
    // A window: its icon and name, its start and end, its hours on the
    // working day (a drawing hidden from a screen reader, which reads the
    // times), what it suits, and when it is offered.
    var win = function (icon, name, from, to, span, text, days, tag) {
        var kids = [
            kit.tag('span', 'pg-window-icon rounded-circle bg-primary-subtle text-primary-emphasis fs-4 mb-3', _sdT('Icon'), [kit.icon(icon)], [{ name: 'aria-hidden', value: 'true' }]),
            kit.h('h3', name, 'h5 fw-bold mb-1'),
            kit.div('fs-3 fw-bold mb-3', _sdT('Time Range'), [
                kit.time(from, from, '', _sdT('Start Time')),
                kit.h('span', '–', 'mx-1 text-body-secondary'),
                kit.time(to, to, '', _sdT('End Time'))
            ]),
            kit.div('pg-window-day mb-4', _sdT('Drawing'), [
                kit.div(span, _sdT('Window'), [])
            ], [{ name: 'aria-hidden', value: 'true' }]),
            kit.p(text, 'text-body-secondary mb-3'),
            kit.p(days, 'small fw-semibold mb-0')
        ];
        if (tag) kids.unshift(kit.p(tag, 'pg-window-tag badge rounded-pill text-bg-primary mb-0'));
        return kit.tag('li', 'col', name, [
            kit.div('pg-window h-100 p-4 rounded-4 border bg-body', _sdT('Card'), kids)
        ]);
    };
    var buildWindows = function () {
        var head = kit.div('row g-4 align-items-end mb-5', _sdT('Section Header'), [
            kit.div('col-lg-7', _sdT('Section Title'), [
                kit.p(_sdT('Arrival windows'), 'small text-uppercase fw-semibold text-primary mb-2'),
                kit.h('h2', _sdT('Choose when we come'), 'display-6 fw-bold mb-0')
            ]),
            kit.div('col-lg-5', _sdT('Lead'), [
                kit.p(_sdT('Pick a window when you book. We arrive inside it, send a message when we are on the way and call if we are running late.'), 'text-body-secondary mb-0')
            ])
        ]);
        return kit.section('py-5 bg-body-tertiary', _sdT('Booking (Arrival Windows)'), [
            fx(head, { effect: 'fade', trigger: 'view', duration: 800 }),
            fx(kit.tag('ul', 'list-unstyled row row-cols-1 row-cols-md-3 g-4 mb-4', _sdT('Windows'), [
                win('bi-sunrise', _sdT('Morning'), '08:00', '12:00', 'pg-window-am',
                    _sdT('The first visits of the day, so we are rarely held up on the way.'), _sdT('Monday to Saturday'), _sdT('Most booked')),
                win('bi-sun', _sdT('Afternoon'), '12:00', '16:00', 'pg-window-pm',
                    _sdT('For when you are home after lunch, or can leave us a key.'), _sdT('Monday to Saturday')),
                win('bi-sunset', _sdT('Evening'), '16:00', '19:00', 'pg-window-eve',
                    _sdT('After work, for the jobs that fit in a short visit.'), _sdT('Monday to Friday; an evening rate applies'))
            ]), { effect: 'slide-up', trigger: 'view', duration: 700, stagger: 120 }),
            fx(kit.div('d-flex flex-column flex-md-row align-items-md-center gap-3', _sdT('Note'), [
                kit.icon('bi-chat-dots', 'fs-4 text-primary d-none d-md-inline'),
                kit.p(_sdT('Need us at an exact time? Ask when you book and we will tell you whether we can.'), 'flex-grow-1 text-body-secondary mb-0'),
                kit.link(_sdT('Book a Visit'), '#', 'btn btn-primary px-4 flex-shrink-0', _sdT('Booking Link'))
            ]), { effect: 'fade', trigger: 'view', duration: 800, delay: 200 })
        ]);
    };

    return {
        prefix: 'booking-',
        items: [
            { label: _sdT('Booking: Weekly Timetable'), type: 'component', icon: 'bi-calendar-week', extra: { componentType: 'booking-week' } },
            { label: _sdT('Booking: Arrival Windows'),  type: 'component', icon: 'bi-clock',         extra: { componentType: 'booking-windows' } }
        ],
        build: function (ctype) {
            if (ctype === 'booking-week') return buildWeek();
            if (ctype === 'booking-windows') return buildWindows();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
