/**
 * Pinegrap - Enterprise Website Platform
 *
 * Gym block family: the sections a gym's or a fitness studio's site needs
 * that the general palette lacks. The free pass hero (the way in: the
 * gym's promise, its hours today and a trial pass card over a picture of
 * the floor) and the class finder (the classes by what they train, each
 * with its length, its level and its intensity on a five-step meter). The
 * weekly timetable is a general block (Booking: Weekly Timetable); plans,
 * coaches, facilities, members' words and the questions are general blocks
 * too (Pricing, Team, Marketing, Social Proof, FAQ).
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
        // A tall picture of the floor with the pass card over its lower
        // edge from md up; below md the card follows the picture.
        'pg-gym-photo': '.pg-gym-photo { position: relative; } ' +
                        '.pg-gym-photo > .ratio { aspect-ratio: 4 / 5; background: var(--bs-tertiary-bg); } ' +
                        '.pg-gym-photo > .ratio::before { display: none; } ' +
                        '@media (min-width: 768px) { .pg-gym-photo > .pg-gym-pass { position: absolute; left: -1.5rem; bottom: 1.5rem; max-width: 20rem; } }',
        // The trial pass: a ticket with a perforated edge in the theme's
        // colour.
        'pg-gym-pass':  '.pg-gym-pass { padding: 1.25rem 1.25rem 1.25rem 1.5rem; border-left: 4px dashed var(--bs-primary); border-radius: var(--bs-border-radius-lg); background: var(--bs-body-bg); box-shadow: var(--bs-box-shadow); }',
        // The intensity meter: five steps, the ones reached in the theme's
        // colour.
        'pg-gym-meter': '.pg-gym-meter { display: inline-flex; align-items: flex-end; gap: 3px; } ' +
                        '.pg-gym-meter > span { width: .375rem; border-radius: 2px; background: var(--bs-border-color); } ' +
                        '.pg-gym-meter > span:nth-child(1) { height: .5rem; } .pg-gym-meter > span:nth-child(2) { height: .7rem; } ' +
                        '.pg-gym-meter > span:nth-child(3) { height: .9rem; } .pg-gym-meter > span:nth-child(4) { height: 1.1rem; } ' +
                        '.pg-gym-meter > span:nth-child(5) { height: 1.3rem; } ' +
                        '.pg-gym-meter > .pg-gym-on { background: var(--bs-primary); }'
    };

    // The pass before the frame: the frame's rule names `.pg-gym-pass`, and
    // a rule whose class the stylesheet already names is taken as present.
    var _SD_BLOCK_CSS_NEEDS = {
        'gym-hero':    ['pg-gym-pass', 'pg-gym-photo'],
        'gym-classes': ['pg-gym-meter']
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

    // A line of a list with a tick before it.
    var tick = function (text) {
        return kit.tag('li', 'd-flex align-items-start gap-2 mb-1', text, [
            kit.icon('bi-check2', 'text-primary'),
            kit.h('span', text)
        ]);
    };

    // ── Free pass hero ──────────────────────────────────────────────────
    var buildHero = function () {
        var words = kit.col(_sdT('Words'), [
            kit.div('badge rounded-pill bg-primary-subtle text-primary-emphasis d-inline-flex align-items-center gap-1 mb-3', _sdT('Open Today'), [
                kit.icon('bi-clock'),
                kit.h('span', _sdT('Open today')),
                kit.time('06:00', '06:00', '', _sdT('Opening Time')),
                kit.h('span', '–'),
                kit.time('23:00', '23:00', '', _sdT('Closing Time'))
            ]),
            kit.h('h1', _sdT('Train with a team that knows your name'), 'display-4 fw-bold text-uppercase mb-3'),
            kit.p(_sdT('Ask for a free trial pass, try any class on the timetable and meet the coaches before you choose a plan.'), 'lead text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Get a Free Pass'), '#', 'btn btn-primary btn-lg px-4', _sdT('Pass Link')),
                kit.link(_sdT('Class Timetable'), '#', 'btn btn-outline-secondary btn-lg px-4', _sdT('Timetable Link'))
            ])
        ], '6');
        var pass = fx(kit.div('pg-gym-pass mt-3 mt-md-0', _sdT('Trial Pass'), [
            kit.p(_sdT('Free trial pass'), 'small text-uppercase fw-semibold text-primary mb-1'),
            kit.p(_sdT('Seven days of every class and the weights floor'), 'fw-bold mb-3'),
            kit.tag('ul', 'list-unstyled small mb-3', _sdT('Check List'), [
                tick(_sdT('Every group class')),
                tick(_sdT('Weights floor and studios at any time')),
                tick(_sdT('A welcome session with a coach'))
            ]),
            kit.p(_sdT('Nothing is paid now.'), 'small text-body-secondary mb-0')
        ]), { effect: 'slide-up', trigger: 'load', delay: 500 });
        var picture = kit.col(_sdT('Picture'), [
            kit.div('pg-gym-photo ms-md-4', _sdT('Picture Frame'), [
                fx(kit.div('ratio rounded-4 overflow-hidden bg-body-tertiary shadow', _sdT('Picture'), [
                    kit.img('gym-hero', 960, 1200, _sdT('A member lifts a barbell on the weights floor while a coach watches'), 'object-fit-cover')
                ]), { effect: 'fade', trigger: 'load', duration: 1000, delay: 200 }),
                pass
            ])
        ], '6');
        return kit.section('py-5 overflow-hidden', _sdT('Gym (Free Pass Hero)'), [
            kit.row(_sdT('Hero Row'), [
                fx(words, { effect: 'slide-up', trigger: 'load', duration: 700 }),
                picture
            ], 'g-5 align-items-center')
        ]);
    };

    // ── Class finder ────────────────────────────────────────────────────
    // The intensity meter: five steps, the first `level` of them reached,
    // named in words for a screen reader.
    var meter = function (level) {
        var steps = [];
        for (var i = 1; i <= 5; i++) {
            steps.push(kit.tag('span', i <= level ? 'pg-gym-on' : '', _sdT('Step'), []));
        }
        var label = _sdT('Intensity {var} of 5', level);
        return kit.div('d-flex align-items-center gap-2 small mb-4', _sdT('Intensity'), [
            kit.div('pg-gym-meter', label, steps, [{ name: 'role', value: 'img' }, { name: 'aria-label', value: label }]),
            kit.h('span', label, 'text-body-secondary')
        ]);
    };
    // A class: its icon, what it trains, its name, a line, its length and
    // level, its intensity and a link that books it.
    var lesson = function (icon, focus, name, text, length, level, intensity) {
        return kit.tag('li', 'col', name, [
            kit.tag('article', 'card h-100 border-0 shadow-sm', _sdT('Class Card'), [
                kit.div('card-body d-flex flex-column p-4', _sdT('Card Body'), [
                    kit.div('d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary-emphasis fs-4 mb-3 align-self-start p-2', _sdT('Icon'), [
                        kit.icon(icon)
                    ]),
                    kit.p(focus, 'small text-uppercase fw-semibold text-primary mb-1'),
                    kit.h('h3', name, 'h4 fw-bold mb-2'),
                    kit.p(text, 'small text-body-secondary mb-3'),
                    kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-3', _sdT('Class Facts'), [
                        kit.tag('li', 'd-inline-flex align-items-center gap-1', _sdT('Length'), [kit.icon('bi-stopwatch', 'text-primary'), kit.h('span', length)]),
                        kit.tag('li', 'd-inline-flex align-items-center gap-1', _sdT('Level'), [kit.icon('bi-person-check', 'text-primary'), kit.h('span', level)])
                    ]),
                    meter(intensity),
                    kit.link(_sdT('Book this class'), '#', 'btn btn-outline-primary mt-auto', _sdT('Book Link'))
                ])
            ])
        ]);
    };
    var buildClasses = function () {
        var head = kit.div('row g-4 align-items-end mb-4', _sdT('Section Header'), [
            kit.div('col-lg-7', _sdT('Classes Title'), [
                eyebrow(_sdT('Group Classes')),
                kit.h('h2', _sdT('Find your class'), 'h2 fw-bold mb-3'),
                kit.p(_sdT('Every class shows how long it lasts, who it suits and how hard it is, so you know what to expect before you come.'), 'text-body-secondary mb-0')
            ]),
            kit.div('col-lg-5', _sdT('Focus Links'), [
                kit.tag('ul', 'nav nav-pills gap-2 justify-content-lg-end', _sdT('Focus'), [
                    kit.tag('li', 'nav-item', _sdT('Strength Training'), [kit.link(_sdT('Strength Training'), '#', 'nav-link border', _sdT('Focus Link'))]),
                    kit.tag('li', 'nav-item', _sdT('Cardio and Conditioning'), [kit.link(_sdT('Cardio and Conditioning'), '#', 'nav-link border', _sdT('Focus Link'))]),
                    kit.tag('li', 'nav-item', _sdT('Mind and Body'), [kit.link(_sdT('Mind and Body'), '#', 'nav-link border', _sdT('Focus Link'))])
                ])
            ])
        ]);
        return kit.section('py-5', _sdT('Gym (Class Finder)'), [
            fx(head, { effect: 'fade', trigger: 'view', duration: 800 }),
            fx(kit.tag('ul', 'list-unstyled row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-0', _sdT('Classes'), [
                lesson('bi-trophy', _sdT('Strength Training'), _sdT('Barbell Strength'),
                    _sdT('Squat, press and deadlift in small groups, with a coach watching every rep and a plan that grows with you.'),
                    _sdT('{var} min', 60), _sdT('Some experience'), 4),
                lesson('bi-fire', _sdT('Cardio and Conditioning'), _sdT('HIIT Burn'),
                    _sdT('Short bursts of hard work and short rests: forty-five minutes that raise your heart rate and your fitness.'),
                    _sdT('{var} min', 45), _sdT('Every level'), 5),
                lesson('bi-flower3', _sdT('Mind and Body'), _sdT('Flow Yoga'),
                    _sdT('Moving from pose to pose with the breath, for mobility, balance and a quieter head.'),
                    _sdT('{var} min', 60), _sdT('Every level'), 2)
            ]), { effect: 'slide-up', trigger: 'view', stagger: 120 })
        ]);
    };

    return {
        prefix: 'gym-',
        group: { title: _sdT('Gym'), icon: 'bi-lightning-charge' },
        items: [
            { label: _sdT('Gym: Free Pass Hero'), type: 'component', icon: 'bi-ticket-perforated', extra: { componentType: 'gym-hero' },
              desc: _sdT('A large headline, the opening hours of the day and two buttons beside a tall training photo with a free trial pass card.') },
            { label: _sdT('Gym: Class Finder'),   type: 'component', icon: 'bi-speedometer2',      extra: { componentType: 'gym-classes' },
              desc: _sdT('A heading with three focus pills over class cards, each with its icon, length, level, a five-step intensity meter and a link.') }
        ],
        build: function (ctype) {
            if (ctype === 'gym-hero') return buildHero();
            if (ctype === 'gym-classes') return buildClasses();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
