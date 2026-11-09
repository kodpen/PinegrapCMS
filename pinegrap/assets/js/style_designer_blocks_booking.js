/**
 * Pinegrap - Enterprise Website Platform
 *
 * General booking blocks the core's Services & Booking group lacks. The
 * weekly class timetable: the seven days side by side, each with its
 * classes in the order they start (the start as a <time>, the class, who
 * leads it, where and how long, a link to book), for any house that runs
 * classes on a weekly rhythm (a gym, a yoga or dance studio, a pool, a
 * climbing wall, a music school). The core's Booking: CTA and Booking:
 * Split keep the single booking call.
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
                        '.pg-week-slot > time { display: block; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--bs-primary-text-emphasis); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'booking-week': ['pg-week', 'pg-week-slot']
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

    return {
        prefix: 'booking-',
        items: [
            { label: _sdT('Booking: Weekly Timetable'), type: 'component', icon: 'bi-calendar-week', extra: { componentType: 'booking-week' } }
        ],
        build: function (ctype) {
            if (ctype === 'booking-week') return buildWeek();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
