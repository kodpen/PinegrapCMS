/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pharmacy block family: the sections a pharmacy's site needs that the
 * general palette lacks. The duty panel (what to do when the pharmacy is
 * closed, the night window and the emergency number), the reference
 * readings (what a blood pressure or blood sugar figure means), the travel
 * vaccine planner (which vaccine, for whom, how many doses and how long
 * before the journey) and the medicine schedule (which medicine at which
 * time of day). Every value is sample text the pharmacists edit in the
 * designer: no patient record, no rota by date. A quiet sector: the blocks
 * stay still.
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
        'pg-pharmacy-figures': '.pg-pharmacy-figures { font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'pharmacy-readings': ['pg-pharmacy-figures'],
        'pharmacy-vaccines': ['pg-pharmacy-figures'],
        'pharmacy-schedule': ['pg-pharmacy-figures']
    };

    // A table cell; a head cell names what it heads with scope.
    var cell = function (tag, kids, cls, scope) {
        return kit.tag(tag, cls || '', tag === 'th' ? _sdT('Header Cell') : _sdT('Cell'), kids, scope ? [{ name: 'scope', value: scope }] : null);
    };
    // A table in a frame that scrolls sideways on a phone: a named,
    // focusable region, so a keyboard can scroll it.
    var frame = function (label, table) {
        return kit.div('table-responsive rounded-4 border bg-body p-2 p-md-3', _sdT('Table Wrap'), [table], kit.region(label));
    };
    // What a reading means: an icon and a word, so the colour is never the
    // only cue.
    var level = function (state, word) {
        var look = {
            good:  ['bg-success-subtle text-success-emphasis', 'bi-check-circle-fill'],
            watch: ['bg-warning-subtle text-warning-emphasis', 'bi-exclamation-circle-fill'],
            high:  ['bg-danger-subtle text-danger-emphasis',   'bi-exclamation-triangle-fill']
        }[state];
        return kit.tag('span', 'badge rounded-pill text-wrap ' + look[0], _sdT('Status Badge'), [
            kit.icon(look[1], 'me-1'),
            kit.h('span', word)
        ]);
    };

    // ── Duty panel ──────────────────────────────────────────────────────
    var answer = function (n, icon, title, text) {
        return kit.tag('li', 'd-flex gap-3 py-3 border-bottom', _sdT('Answer {var}', n), [
            kit.div('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 align-self-start', _sdT('Icon Box'), [
                kit.icon(icon, 'fs-5')
            ]),
            kit.div('', _sdT('Answer Text'), [
                kit.h('h3', title, 'h5 fw-bold mb-1'),
                kit.p(text, 'text-body-secondary mb-0')
            ])
        ]);
    };
    var buildDuty = function () {
        return kit.section('py-5', _sdT('Pharmacy (Duty Panel)'), [
            kit.row(_sdT('Duty Row'), [
                kit.col(_sdT('Answers Column'), [
                    kit.h('h2', _sdT('When we are closed'), 'fw-bold mb-2'),
                    kit.p(_sdT('Pharmacies take turns to stay open at night and on holidays, so there is always one on duty near you.'), 'lead text-body-secondary mb-3'),
                    kit.tag('ul', 'list-unstyled mb-0', _sdT('Answers'), [
                        answer(1, 'bi-moon-stars', _sdT('Our duty nights'), _sdT('When the rota puts us on duty, we stay open through the night and the following holiday.')),
                        answer(2, 'bi-signpost-split', _sdT('Closed right now?'), _sdT('The pharmacy on duty tonight is named on the notice in our window and on the chamber of pharmacists\' list.')),
                        answer(3, 'bi-telephone-plus', _sdT('An emergency'), _sdT('Chest pain, breathing difficulty, a serious injury or a poisoning cannot wait for a pharmacy.'))
                    ])
                ], '7'),
                kit.col(_sdT('Night Window Column'), [
                    kit.div('card h-100 border-0 bg-body-tertiary', _sdT('Night Window Card'), [
                        kit.div('card-body p-4', _sdT('Card Body'), [
                            kit.h('h3', _sdT('At the night window'), 'h5 fw-bold mb-3'),
                            kit.tag('ol', 'mb-4', _sdT('Steps'), [
                                kit.tag('li', 'mb-2', _sdT('Step {var}', 1), [kit.h('span', _sdT('Ring the night bell at the side window.'))]),
                                kit.tag('li', 'mb-2', _sdT('Step {var}', 2), [kit.h('span', _sdT('Show your prescription to the pharmacist on duty.'))]),
                                kit.tag('li', '', _sdT('Step {var}', 3), [kit.h('span', _sdT('A small duty fee is added at night and on holidays.'))])
                            ]),
                            kit.h('h4', _sdT('What to bring'), 'h6 fw-bold mb-2'),
                            kit.tag('ul', 'list-unstyled d-grid gap-2 mb-0', _sdT('What to Bring'), [
                                _sdT('Your prescription or its number'),
                                _sdT('Your identity card'),
                                _sdT('A list of your other medicines')
                            ].map(function (text, i) {
                                return kit.tag('li', 'd-flex gap-2', _sdT('Item {var}', i + 1), [
                                    kit.icon('bi-check2', 'text-primary flex-shrink-0'),
                                    kit.h('span', text)
                                ]);
                            }))
                        ])
                    ])
                ], '5')
            ], 'g-lg-5'),
            kit.div('alert alert-danger d-flex flex-wrap align-items-center gap-3 mt-4 mb-0', _sdT('Emergency Line'), [
                kit.icon('bi-telephone-fill', 'fs-4 flex-shrink-0'),
                kit.p(_sdT('A medical emergency cannot wait for a pharmacy. Call the emergency number at once.'), 'fw-semibold mb-0 me-auto'),
                kit.link(_sdT('Call 112'), 'tel:112', 'btn btn-danger', _sdT('Emergency Link'))
            ])
        ]);
    };

    // ── Reference readings ──────────────────────────────────────────────
    // A table per measurement: a row per band, its figures and what to do.
    var band = function (name, state, figures, todo) {
        return kit.tag('tr', '', name, [
            cell('th', [level(state, name)], '', 'row'),
            cell('td', [kit.h('span', figures, 'fw-semibold')], 'text-nowrap'),
            cell('td', [kit.h('span', todo, 'small text-body-secondary')], '')
        ]);
    };
    var readingTable = function (title, unit, caption, rows) {
        return kit.col(title, [
            kit.h('h3', title, 'h5 fw-bold mb-1'),
            kit.p(unit, 'small text-body-secondary mb-3'),
            frame(title, kit.tag('table', 'table align-middle mb-0 pg-pharmacy-figures', _sdT('Readings Table'), [
                kit.tag('caption', 'visually-hidden', _sdT('Table Caption'), [kit.h('span', caption)]),
                kit.tag('thead', '', _sdT('Table Head'), [
                    kit.tag('tr', '', _sdT('Head Row'), [
                        cell('th', [kit.h('span', _sdT('Your reading'))], 'small text-body-secondary fw-semibold', 'col'),
                        cell('th', [kit.h('span', _sdT('Figures'))], 'small text-body-secondary fw-semibold', 'col'),
                        cell('th', [kit.h('span', _sdT('What to do'))], 'small text-body-secondary fw-semibold', 'col')
                    ])
                ]),
                kit.tag('tbody', '', _sdT('Table Body'), rows)
            ]))
        ], '6');
    };
    var buildReadings = function () {
        return kit.section('py-5 bg-body-tertiary', _sdT('Pharmacy (Reference Readings)'), [
            kit.intro(_sdT('Know your numbers'), _sdT('What a reading taken at rest means, and when to see your doctor. Your doctor\'s own targets come first.')),
            kit.row(_sdT('Readings Row'), [
                readingTable(_sdT('Blood pressure'), _sdT('Upper / lower figure, in mmHg'), _sdT('Blood pressure readings and what they mean'), [
                    band(_sdT('Normal'),       'good',  _sdT('Below {var}', '120 / 80'),                 _sdT('Check again at your next visit.')),
                    band(_sdT('Slightly high'),       'watch', _sdT('{var:1} to {var:2}', ['120 / 80', '139 / 89']), _sdT('Measure again in a week, rested.')),
                    band(_sdT('High'),         'high',  _sdT('{var} or above', '140 / 90'),              _sdT('Book a visit to your doctor.'))
                ]),
                readingTable(_sdT('Blood sugar'), _sdT('Before breakfast, in mg/dL'), _sdT('Fasting blood sugar readings and what they mean'), [
                    band(_sdT('Normal'),       'good',  _sdT('{var:1} to {var:2}', ['70', '99']),   _sdT('Check again at your next visit.')),
                    band(_sdT('Slightly high'),       'watch', _sdT('{var:1} to {var:2}', ['100', '125']), _sdT('Ask us about food and exercise.')),
                    band(_sdT('High'),         'high',  _sdT('{var} or above', '126'),              _sdT('Book a visit to your doctor.'))
                ])
            ], 'g-lg-5'),
            kit.div('d-flex flex-wrap align-items-center gap-3 mt-4', _sdT('Readings Foot'), [
                kit.p(_sdT('A single reading is not a diagnosis. We measure in private, free of rush.'), 'small text-body-secondary mb-0 me-auto'),
                kit.link(_sdT('Book a health check'), '#', 'btn btn-primary', _sdT('Booking Link'))
            ])
        ]);
    };

    // ── Travel vaccine planner ──────────────────────────────────────────
    var vaccine = function (name, who, doses, before) {
        return kit.tag('tr', '', name, [
            cell('th', [kit.h('span', name, 'fw-semibold')], '', 'row'),
            cell('td', [kit.h('span', who, 'small')], ''),
            cell('td', [kit.h('span', doses)], 'text-nowrap'),
            cell('td', [kit.h('span', before, 'fw-semibold')], 'text-nowrap')
        ]);
    };
    var buildVaccines = function () {
        return kit.section('py-5', _sdT('Pharmacy (Travel Vaccines)'), [
            kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Section Header'), [
                kit.div('', _sdT('Section Title'), [
                    kit.h('h2', _sdT('Vaccines before you travel'), 'fw-bold mb-1'),
                    kit.p(_sdT('Start in good time: some vaccines take several doses or weeks to protect you.'), 'text-body-secondary mb-0')
                ]),
                kit.link(_sdT('Book a travel consultation'), '#', 'btn btn-primary', _sdT('Booking Link'))
            ]),
            frame(_sdT('Vaccines before you travel'), kit.tag('table', 'table align-middle mb-0 pg-pharmacy-figures', _sdT('Vaccines Table'), [
                kit.tag('caption', 'visually-hidden', _sdT('Table Caption'), [kit.h('span', _sdT('Travel vaccines, who needs them, doses and when to start'))]),
                kit.tag('thead', '', _sdT('Table Head'), [
                    kit.tag('tr', '', _sdT('Head Row'), [
                        cell('th', [kit.h('span', _sdT('Vaccine'))], 'small text-body-secondary fw-semibold', 'col'),
                        cell('th', [kit.h('span', _sdT('For whom'))], 'small text-body-secondary fw-semibold', 'col'),
                        cell('th', [kit.h('span', _sdT('Doses'))], 'small text-body-secondary fw-semibold', 'col'),
                        cell('th', [kit.h('span', _sdT('Start before the trip'))], 'small text-body-secondary fw-semibold', 'col')
                    ])
                ]),
                kit.tag('tbody', '', _sdT('Table Body'), [
                    vaccine(_sdT('Hepatitis A'),  _sdT('Most destinations outside Europe'),  _sdT('{var} doses', '2'), _sdT('{var} weeks', '2')),
                    vaccine(_sdT('Typhoid'),      _sdT('Where food and water may be unsafe'), _sdT('{var} dose', '1'),  _sdT('{var} weeks', '2')),
                    vaccine(_sdT('Yellow fever'), _sdT('Parts of Africa and South America'), _sdT('{var} dose', '1'),  _sdT('{var} days', '10')),
                    vaccine(_sdT('Rabies'),       _sdT('Long stays, hiking, work with animals'), _sdT('{var} doses', '3'), _sdT('{var} weeks', '4'))
                ])
            ])),
            kit.p(_sdT('Bring your vaccination card: the pharmacist checks what you already have.'), 'small text-body-secondary mt-3 mb-0')
        ]);
    };

    // ── Medicine schedule ───────────────────────────────────────────────
    // A row per medicine, a column per time of day: how many to take, or
    // "No dose" in words; a note on food under the name.
    var dose = function (text) {
        var none = text === _sdT('No dose');
        return cell('td', [kit.h('span', text, none ? 'small text-body-secondary' : 'fw-bold')], 'text-center');
    };
    var medicine = function (name, note, doses) {
        return kit.tag('tr', '', name, [
            cell('th', [
                kit.h('span', name, 'd-block fw-semibold'),
                kit.h('span', note, 'd-block small fw-normal text-body-secondary')
            ], '', 'row')
        ].concat(doses.map(dose)));
    };
    var timeHead = function (icon, name) {
        return cell('th', [
            kit.icon(icon, 'd-block text-primary fs-5 mb-1'),
            kit.h('span', name, 'small fw-semibold')
        ], 'text-center', 'col');
    };
    var buildSchedule = function () {
        return kit.section('py-5', _sdT('Pharmacy (Medicine Schedule)'), [
            kit.row(_sdT('Schedule Row'), [
                kit.col(_sdT('Schedule Intro'), [
                    kit.h('h2', _sdT('Your medicines through the day'), 'fw-bold mb-3'),
                    kit.p(_sdT('After a medicine review we write your day out like this, so every medicine has its time and nothing is taken twice.'), 'text-body-secondary mb-4'),
                    kit.link(_sdT('Book a medicine review'), '#', 'btn btn-primary', _sdT('Booking Link'))
                ], '4'),
                kit.col(_sdT('Schedule Column'), [
                    frame(_sdT('Your medicines through the day'), kit.tag('table', 'table align-middle mb-0 pg-pharmacy-figures', _sdT('Schedule Table'), [
                        kit.tag('caption', 'visually-hidden', _sdT('Table Caption'), [kit.h('span', _sdT('Sample medicine schedule by time of day'))]),
                        kit.tag('thead', '', _sdT('Table Head'), [
                            kit.tag('tr', '', _sdT('Head Row'), [
                                cell('th', [kit.h('span', _sdT('Medicine'), 'small fw-semibold')], '', 'col'),
                                timeHead('bi-sunrise', _sdT('Morning')),
                                timeHead('bi-sun', _sdT('Midday')),
                                timeHead('bi-sunset', _sdT('Evening')),
                                timeHead('bi-moon', _sdT('Bedtime'))
                            ])
                        ]),
                        kit.tag('tbody', '', _sdT('Table Body'), [
                            medicine(_sdT('Blood pressure tablet'), _sdT('With water, before breakfast'), ['1', _sdT('No dose'), _sdT('No dose'), _sdT('No dose')]),
                            medicine(_sdT('Diabetes tablet'),       _sdT('With a meal'),                ['1', _sdT('No dose'), '1', _sdT('No dose')]),
                            medicine(_sdT('Cholesterol tablet'),    _sdT('At the same time each night'), [_sdT('No dose'), _sdT('No dose'), _sdT('No dose'), '1']),
                            medicine(_sdT('Vitamin D'),             _sdT('With food'),                  [_sdT('No dose'), '1', _sdT('No dose'), _sdT('No dose')])
                        ])
                    ])),
                    kit.p(_sdT('A sample: never change a medicine or its time without asking your doctor or pharmacist.'), 'small text-body-secondary mt-3 mb-0')
                ], '8')
            ], 'g-lg-5')
        ]);
    };

    return {
        prefix: 'pharmacy-',
        group: { title: _sdT('Pharmacy'), icon: 'bi-capsule' },
        items: [
            { label: _sdT('Pharmacy: Duty Panel'),         type: 'component', icon: 'bi-moon-stars',     extra: { componentType: 'pharmacy-duty' } },
            { label: _sdT('Pharmacy: Reference Readings'), type: 'component', icon: 'bi-heart-pulse',    extra: { componentType: 'pharmacy-readings' } },
            { label: _sdT('Pharmacy: Travel Vaccines'),    type: 'component', icon: 'bi-airplane',       extra: { componentType: 'pharmacy-vaccines' } },
            { label: _sdT('Pharmacy: Medicine Schedule'),  type: 'component', icon: 'bi-calendar2-week', extra: { componentType: 'pharmacy-schedule' } }
        ],
        build: function (ctype) {
            if (ctype === 'pharmacy-duty')     return buildDuty();
            if (ctype === 'pharmacy-readings') return buildReadings();
            if (ctype === 'pharmacy-vaccines') return buildVaccines();
            if (ctype === 'pharmacy-schedule') return buildSchedule();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
