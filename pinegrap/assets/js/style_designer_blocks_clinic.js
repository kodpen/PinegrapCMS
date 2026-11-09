/**
 * Pinegrap - Enterprise Website Platform
 *
 * Clinic block family: the sections a doctors' practice's, a health
 * centre's or a polyclinic's site needs that the general palette lacks.
 * The doctors' week (who sees patients on which day, in the morning or the
 * afternoon, read across the week as one table) and the care guide (where
 * to go with what: an emergency, a problem that needs a doctor today, and
 * one that can wait for a booked visit, each with its examples and its
 * one action). The services, the fees, the check-up packages, the doctors'
 * profiles, how booking works, the figures, the patients' words and the
 * questions are general blocks (Services, Pricing, Team, Steps, Social
 * Proof, FAQ).
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
        // The doctors' week: a doctor per row, a day per column; a session
        // is a tinted pill, a day off a muted word.
        'pg-clinic-rota': '.pg-clinic-rota { min-width: 46rem; margin: 0; } ' +
                          '.pg-clinic-rota th, .pg-clinic-rota td { padding: .85rem .75rem; vertical-align: middle; } ' +
                          '.pg-clinic-rota thead th { text-align: center; font-size: .875em; color: var(--bs-secondary-color); border-bottom: 2px solid var(--bs-primary-border-subtle); } ' +
                          '.pg-clinic-rota tbody th { min-width: 12rem; } ' +
                          '.pg-clinic-rota td { text-align: center; } ' +
                          '.pg-clinic-rota .pg-clinic-session { display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .65rem; border-radius: 50rem; font-size: .8125em; font-weight: 600; background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); } ' +
                          '.pg-clinic-rota .pg-clinic-off { font-size: .8125em; color: var(--bs-tertiary-color); }',
        // The care guide: a card per level, its tone on a thick top edge.
        'pg-clinic-care': '.pg-clinic-care { height: 100%; border: 1px solid var(--bs-border-color); border-top: .375rem solid var(--bs-primary); border-radius: var(--bs-border-radius-lg); background: var(--bs-body-bg); } ' +
                          '.pg-clinic-care.pg-clinic-now { border-top-color: var(--bs-danger); } ' +
                          '.pg-clinic-care.pg-clinic-today { border-top-color: var(--bs-warning); } ' +
                          '.pg-clinic-care .pg-clinic-level { display: inline-flex; align-items: center; justify-content: center; width: 3rem; height: 3rem; border-radius: 50%; font-size: 1.35rem; background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); } ' +
                          '.pg-clinic-care.pg-clinic-now .pg-clinic-level { background: var(--bs-danger-bg-subtle); color: var(--bs-danger-text-emphasis); } ' +
                          '.pg-clinic-care.pg-clinic-today .pg-clinic-level { background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'clinic-week': ['pg-clinic-rota'],
        'clinic-care': ['pg-clinic-care']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };

    // The small spaced line above a title.
    var eyebrow = function (text) {
        return kit.p(text, 'small text-uppercase fw-semibold text-primary mb-2');
    };

    // The section's eyebrow, h2 and line, centred.
    var header = function (kicker, title, lead) {
        return fx(kit.div('text-center col-lg-8 mx-auto mb-5', _sdT('Section Header'), [
            eyebrow(kicker),
            kit.h('h2', title, 'h2 fw-bold mb-2'),
            kit.p(lead, 'lead text-body-secondary mb-0')
        ]), { effect: 'fade', trigger: 'view', duration: 700 });
    };

    // ── Doctors' week ───────────────────────────────────────────────────
    var cell = kit.tag;
    var scope = function (value) {
        return [{ name: 'scope', value: value }];
    };
    // A day's session: M (morning), A (afternoon), B (both) or '' (off).
    var session = function (code) {
        if (code === 'M') {
            return cell('td', '', _sdT('Day'), [kit.tag('span', 'pg-clinic-session', _sdT('Time'), [kit.icon('bi-sunrise'), kit.h('span', _sdT('Morning'))])]);
        }
        if (code === 'A') {
            return cell('td', '', _sdT('Day'), [kit.tag('span', 'pg-clinic-session', _sdT('Time'), [kit.icon('bi-sunset'), kit.h('span', _sdT('Afternoon'))])]);
        }
        if (code === 'B') {
            return cell('td', '', _sdT('Day'), [kit.tag('span', 'pg-clinic-session', _sdT('Time'), [kit.icon('bi-sun'), kit.h('span', _sdT('All day'))])]);
        }
        return cell('td', '', _sdT('Day'), [kit.h('span', _sdT('Day off'), 'pg-clinic-off')]);
    };
    // A doctor's row: the name and specialty as the row head, then a cell
    // per day from Monday to Saturday.
    var doctor = function (name, specialty, codes) {
        return cell('tr', '', name, [
            cell('th', '', _sdT('Doctor'), [
                kit.h('span', name, 'd-block fw-bold'),
                kit.h('span', specialty, 'd-block small text-body-secondary fw-normal')
            ], scope('row'))
        ].concat(codes.map(session)));
    };
    var buildWeek = function () {
        var days = [_sdT('Monday'), _sdT('Tuesday'), _sdT('Wednesday'), _sdT('Thursday'), _sdT('Friday'), _sdT('Saturday')];
        var head = cell('thead', '', _sdT('Table Head'), [
            cell('tr', '', _sdT('Row'), [
                cell('th', 'text-start', _sdT('Column'), [kit.h('span', _sdT('Doctor'))], scope('col'))
            ].concat(days.map(function (d) {
                return cell('th', '', _sdT('Column'), [kit.h('span', d)], scope('col'));
            })))
        ]);
        var body = cell('tbody', '', _sdT('Rows'), [
            doctor(_sdT('Dr. Jane Cooper'), _sdT('Family Medicine'), ['B', 'B', 'M', 'B', 'B', '']),
            doctor(_sdT('Dr. Michael Brooks'), _sdT('Cardiology'), ['', 'B', '', 'B', '', '']),
            doctor(_sdT('Dr. Emily Turner'), _sdT('Paediatrics'), ['M', '', 'A', '', 'M', '']),
            doctor(_sdT('Dr. Daniel Ross'), _sdT('Dermatology'), ['', '', 'A', '', '', 'M'])
        ]);
        return kit.section('py-5', _sdT('Clinic (Doctors\' Week)'), [
            header(_sdT('The team'), _sdT('Who sees patients when'), _sdT('Choose the day that suits you; the receptionist books you with the doctor on duty.')),
            fx(kit.div('table-responsive border rounded-4 bg-body', _sdT('Table Frame'), [
                cell('table', 'table pg-clinic-rota align-middle mb-0', _sdT('Doctors\' Week'), [head, body])
            ], kit.region(_sdT('Doctors\' Week'))), { effect: 'slide-up', trigger: 'view', duration: 700 }),
            kit.div('d-flex flex-wrap align-items-center justify-content-center gap-3 small text-body-secondary mt-4', _sdT('Note'), [
                kit.div('d-inline-flex align-items-center gap-1', _sdT('Morning'), [kit.icon('bi-sunrise', 'text-primary'), kit.h('span', _sdT('Morning 8:30 to 12:30'))]),
                kit.div('d-inline-flex align-items-center gap-1', _sdT('Afternoon'), [kit.icon('bi-sunset', 'text-primary'), kit.h('span', _sdT('Afternoon 13:30 to 18:00'))]),
                kit.link(_sdT('Book an Appointment'), '#', 'btn btn-primary px-4', _sdT('Booking Link'))
            ])
        ]);
    };

    // ── Care guide ──────────────────────────────────────────────────────
    // A level of care: its tone, icon, name, when it applies, examples and
    // the one thing to do.
    var level = function (tone, icon, title, lead, examples, action) {
        return kit.col(title, [
            kit.div('pg-clinic-care ' + tone + ' d-flex flex-column p-4', title, [
                kit.tag('span', 'pg-clinic-level mb-3', _sdT('Icon'), [kit.icon(icon)]),
                kit.h('h3', title, 'h5 fw-bold mb-2'),
                kit.p(lead, 'text-body-secondary mb-3'),
                kit.tag('ul', 'list-unstyled small mb-4', _sdT('Examples'), examples.map(function (t) {
                    return kit.tag('li', 'd-flex gap-2 mb-2', t, [
                        kit.icon('bi-dot', 'flex-shrink-0'),
                        kit.h('span', t)
                    ]);
                })),
                kit.div('mt-auto', _sdT('Action'), [action])
            ])
        ], '4');
    };
    var buildCare = function () {
        var cards = [
            level('pg-clinic-now', 'bi-exclamation-octagon', _sdT('Call 112 now'), _sdT('When a life may be at risk, do not wait for the practice.'), [
                _sdT('Chest pain or pressure'),
                _sdT('Trouble breathing'),
                _sdT('Sudden weakness of the face, an arm or a leg'),
                _sdT('Heavy bleeding or a serious injury')
            ], kit.link(_sdT('Call 112'), 'tel:112', 'btn btn-danger w-100', _sdT('Emergency Link'))),
            level('pg-clinic-today', 'bi-telephone', _sdT('See a doctor today'), _sdT('Call us in the morning; we keep same-day times for these.'), [
                _sdT('A high fever that does not come down'),
                _sdT('A child who will not drink'),
                _sdT('Pain that keeps you from sleeping'),
                _sdT('A rash that spreads fast')
            ], kit.link(_sdT('Call the Practice'), 'tel:+905550000000', 'btn btn-warning w-100', _sdT('Phone Link'))),
            level('', 'bi-calendar2-check', _sdT('Book a visit'), _sdT('For what can wait a few days, choose a time online.'), [
                _sdT('A cough or a cold that lingers'),
                _sdT('Repeat prescriptions'),
                _sdT('Test results to go through'),
                _sdT('A yearly check-up')
            ], kit.link(_sdT('Book an Appointment'), '#', 'btn btn-primary w-100', _sdT('Booking Link')))
        ];
        return kit.section('py-5 bg-body-tertiary', _sdT('Clinic (Care Guide)'), [
            header(_sdT('Where to go'), _sdT('Not sure where to turn?'), _sdT('Three answers, from an emergency to a visit that can wait.')),
            fx(kit.row(_sdT('Levels'), cards, 'g-4'), { effect: 'slide-up', trigger: 'view', stagger: 120 }),
            kit.p(_sdT('This guide does not replace a doctor\'s advice. When in doubt, call.'), 'small text-body-secondary text-center mt-4 mb-0')
        ]);
    };

    return {
        prefix: 'clinic-',
        group: { title: _sdT('Clinic'), icon: 'bi-hospital' },
        items: [
            { label: _sdT('Clinic: Doctors\' Week'), type: 'component', icon: 'bi-calendar-week', extra: { componentType: 'clinic-week' } },
            { label: _sdT('Clinic: Care Guide'),     type: 'component', icon: 'bi-signpost-split', extra: { componentType: 'clinic-care' } }
        ],
        build: function (ctype) {
            if (ctype === 'clinic-week') return buildWeek();
            if (ctype === 'clinic-care') return buildCare();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
