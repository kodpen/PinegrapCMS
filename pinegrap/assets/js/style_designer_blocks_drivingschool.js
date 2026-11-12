/**
 * Pinegrap - Enterprise Website Platform
 *
 * Driving School block family: the sections a driving school's site needs
 * that the general palette lacks. The licence classes (each class letter
 * drawn as a number plate, with its minimum age, lessons and gearbox), the
 * theory topics shown as road signs beside the pass mark, and the
 * practical test sheet (how the test day runs and how the examiner marks
 * faults). Every value is sample text the staff edit in the designer.
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
        'pg-drv-plate':     '.pg-drv-plate { display: inline-flex; align-items: center; justify-content: center; min-width: 2.4em; height: 2.4em; padding: 0 .4em; font-weight: 700; line-height: 1; letter-spacing: .04em; color: var(--bs-emphasis-color); background-color: var(--bs-body-bg); border: 2px solid currentColor; border-radius: .4rem; }',
        'pg-drv-sign':      '.pg-drv-sign { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 3.25rem; height: 3.25rem; font-weight: 700; font-size: 1.1rem; line-height: 1; }',
        'pg-drv-sign-warn': '.pg-drv-sign-warn { padding-top: .9rem; clip-path: polygon(50% 0, 100% 100%, 0 100%); background-color: var(--bs-warning); color: var(--bs-dark); }',
        'pg-drv-sign-ban':  '.pg-drv-sign-ban { border-radius: 50%; border: .35rem solid var(--bs-danger); background-color: var(--bs-body-bg); color: var(--bs-emphasis-color); }',
        'pg-drv-sheet':     '.pg-drv-sheet { font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'drivingschool-licences':  ['pg-drv-plate'],
        'drivingschool-signs':     ['pg-drv-sign', 'pg-drv-sign-warn', 'pg-drv-sign-ban'],
        'drivingschool-test-sheet': ['pg-drv-sheet']
    };

    // An entrance the operator can change or remove in the Interactions
    // section; the children rise one after another while the row scrolls
    // into view. pg_fx keeps them still under reduced motion.
    var rise = function (node, stagger) {
        node.props._fx = { effect: 'slide-up', trigger: 'view', stagger: stagger || 90 };
        return node;
    };

    var header = function (title, lead, more) {
        var kids = [kit.div('', _sdT('Section Title'), [
            kit.h('h2', title, 'fw-bold mb-1'),
            kit.p(lead, 'text-body-secondary mb-0')
        ])];
        if (more) kids.push(more);
        return kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Section Header'), kids);
    };

    // ── Licence classes ─────────────────────────────────────────────────
    // A class letter as a number plate, the vehicle it covers, then its
    // facts as a description list.
    var fact = function (label, value) {
        return kit.div('d-flex justify-content-between gap-3 py-2 border-top', label, [
            kit.h('dt', label, 'fw-normal text-body-secondary'),
            kit.h('dd', value, 'fw-semibold text-end mb-0')
        ]);
    };
    var licence = function (n, code, icon, name, text, age, lessons, gearbox) {
        return kit.col(_sdT('Licence {var}', n), [
            kit.div('card h-100 border-0 shadow-sm', _sdT('Licence Card'), [
                kit.div('card-body d-flex flex-column p-4', _sdT('Card Body'), [
                    kit.div('d-flex align-items-center justify-content-between gap-3 mb-3', _sdT('Card Head'), [
                        kit.h('span', code, 'pg-drv-plate fs-4'),
                        kit.icon(icon, 'fs-2 text-primary')
                    ]),
                    kit.h('h3', name, 'h5 fw-bold mb-2'),
                    kit.p(text, 'small text-body-secondary mb-3'),
                    kit.tag('dl', 'small mb-4', _sdT('Licence Facts'), [
                        fact(_sdT('Minimum age'), age),
                        fact(_sdT('Lessons'), lessons),
                        fact(_sdT('Gearbox'), gearbox)
                    ]),
                    kit.link(_sdT('See the class'), '#', 'btn btn-outline-primary mt-auto', _sdT('Class Link'))
                ])
            ])
        ], '3', '6');
    };
    var buildLicences = function () {
        return kit.section('py-5 bg-body-tertiary', _sdT('Driving School (Licence Classes)'), [
            header(_sdT('Choose your licence class'), _sdT('Each class has its own minimum age, lessons and test.'),
                kit.link(_sdT('All licence classes'), '#', 'btn btn-primary', _sdT('Classes Link'))),
            rise(kit.row(_sdT('Classes Row'), [
                licence(1, 'B', 'bi-car-front-fill', _sdT('Car'), _sdT('Cars with up to eight passenger seats, and a light trailer behind them.'),
                    _sdT('{var} years old', '18'), _sdT('From {var} lessons', '20'), _sdT('Manual or automatic')),
                licence(2, 'A2', 'bi-bicycle', _sdT('Motorcycle'), _sdT('Motorcycles of medium power, after the training on the closed course.'),
                    _sdT('{var} years old', '20'), _sdT('From {var} lessons', '16'), _sdT('Manual')),
                licence(3, 'C1', 'bi-truck', _sdT('Light truck'), _sdT('Trucks and vans between 3.5 and 7.5 tonnes, with a light trailer.'),
                    _sdT('{var} years old', '21'), _sdT('From {var} lessons', '14'), _sdT('Manual')),
                licence(4, 'BE', 'bi-truck-flatbed', _sdT('Car and trailer'), _sdT('A car with a heavy trailer, a horsebox or a large caravan.'),
                    _sdT('Holds class B'), _sdT('From {var} lessons', '8'), _sdT('Manual or automatic'))
            ])),
            kit.p(_sdT('Driving only automatics? Class B can be learned and tested in an automatic car.'), 'small text-body-secondary text-center mt-4 mb-0')
        ]);
    };

    // ── Theory topics as road signs ─────────────────────────────────────
    // The sign is decoration: the topic is named in its heading.
    var sign = function (shape, icon, text) {
        var cls = {
            warn: 'pg-drv-sign pg-drv-sign-warn',
            ban:  'pg-drv-sign pg-drv-sign-ban',
            info: 'pg-drv-sign text-bg-primary rounded-3'
        }[shape];
        return kit.div(cls, _sdT('Road Sign'), [text ? kit.h('span', text) : kit.icon(icon)], [
            { name: 'aria-hidden', value: 'true' }
        ]);
    };
    var topic = function (n, shape, icon, text, title, about, questions) {
        return kit.div('col', _sdT('Topic {var}', n), [
            kit.div('d-flex gap-3 h-100 p-3 rounded-3 border bg-body', _sdT('Topic Card'), [
                sign(shape, icon, text),
                kit.div('', _sdT('Topic Copy'), [
                    kit.h('h3', title, 'h6 fw-bold mb-1'),
                    kit.p(about, 'small text-body-secondary mb-1'),
                    kit.h('span', _sdT('{var} questions', questions), 'small fw-semibold')
                ])
            ])
        ]);
    };
    var buildSigns = function () {
        return kit.section('py-5', _sdT('Driving School (Theory Topics)'), [
            kit.row(_sdT('Theory Row'), [
                kit.col(_sdT('Pass Mark Column'), [
                    kit.h('h2', _sdT('What the theory test asks'), 'fw-bold mb-2'),
                    kit.p(_sdT('Six topics, learned one evening at a time and practised at home until the answers come easily.'), 'text-body-secondary mb-4'),
                    kit.div('p-4 rounded-4 border border-primary border-2 mb-4', _sdT('Pass Mark'), [
                        kit.h('span', _sdT('Pass mark'), 'd-block small fw-semibold text-uppercase text-primary mb-1'),
                        kit.h('span', _sdT('{var:1} of {var:2}', ['43', '50']), 'd-block display-5 fw-bold lh-1 mb-2'),
                        kit.p(_sdT('Questions answered right to pass, with an hour to answer them.'), 'small text-body-secondary mb-0')
                    ]),
                    kit.link(_sdT('Try the practice questions'), '#', 'btn btn-primary', _sdT('Practice Link'))
                ], '4'),
                kit.col(_sdT('Topics Column'), [
                    rise(kit.div('row row-cols-1 row-cols-sm-2 g-3', _sdT('Topics'), [
                        topic(1, 'warn', 'bi-exclamation-lg', '', _sdT('Warning signs'), _sdT('Bends, crossings and hazards ahead, and what each asks of you.'), '9'),
                        topic(2, 'ban', '', '50', _sdT('Speed limits'), _sdT('Limits by road and vehicle, and speed that suits the weather.'), '8'),
                        topic(3, 'info', 'bi-signpost-split', '', _sdT('Junctions and lanes'), _sdT('Priority, roundabouts, lane markings and turning safely.'), '10'),
                        topic(4, 'warn', 'bi-eye', '', _sdT('Hazard perception'), _sdT('Spotting a hazard early: a child near the kerb, a door opening.'), '8'),
                        topic(5, 'info', 'bi-tools', '', _sdT('Vehicle checks'), _sdT('Tyres, lights, fluids and the checks before every drive.'), '7'),
                        topic(6, 'info', 'bi-bandaid', '', _sdT('First aid'), _sdT('What to do first at the scene of an accident.'), '8')
                    ]))
                ], '8')
            ], 'g-lg-5 align-items-center')
        ]);
    };

    // ── Practical test sheet ────────────────────────────────────────────
    var stage = function (n, minutes, title, text) {
        return kit.tag('li', 'd-flex gap-3 pb-3', _sdT('Stage {var}', n), [
            kit.h('span', minutes, 'badge rounded-pill text-bg-primary align-self-start text-nowrap'),
            kit.div('', _sdT('Stage Copy'), [
                kit.h('h3', title, 'h6 fw-bold mb-1'),
                kit.p(text, 'small text-body-secondary mb-0')
            ])
        ]);
    };
    var cell = function (tag, text, cls, scope) {
        var attrs = scope ? [{ name: 'scope', value: scope }] : null;
        return kit.tag(tag, cls || '', tag === 'th' ? _sdT('Header Cell') : _sdT('Cell'), [kit.h('span', text)], attrs);
    };
    // A skill's row: the faults marked against it in each column.
    var skill = function (name, minor, serious, dangerous) {
        return kit.tag('tr', '', name, [
            cell('th', name, 'fw-normal', 'row'),
            cell('td', minor, 'text-center fw-semibold'),
            cell('td', serious, 'text-center fw-semibold'),
            cell('td', dangerous, 'text-center fw-semibold')
        ]);
    };
    var level = function (icon, cls, name, rule) {
        return kit.tag('li', 'd-flex align-items-center gap-2', name, [
            kit.icon(icon, cls + ' flex-shrink-0'),
            kit.h('span', name, 'fw-semibold'),
            kit.h('span', rule, 'text-body-secondary')
        ]);
    };
    var buildTestSheet = function () {
        var table = kit.tag('table', 'table align-middle mb-0', _sdT('Faults Table'), [
            kit.tag('caption', 'visually-hidden', _sdT('Table Caption'), [kit.h('span', _sdT('Faults marked on a sample test sheet'))]),
            kit.tag('thead', '', _sdT('Table Head'), [
                kit.tag('tr', '', _sdT('Head Row'), [
                    cell('th', _sdT('Skill'), '', 'col'),
                    cell('th', _sdT('Driving faults'), 'text-center', 'col'),
                    cell('th', _sdT('Serious faults'), 'text-center', 'col'),
                    cell('th', _sdT('Dangerous faults'), 'text-center', 'col')
                ])
            ]),
            kit.tag('tbody', '', _sdT('Table Body'), [
                skill(_sdT('Mirrors'), '2', '0', '0'),
                skill(_sdT('Signals'), '1', '0', '0'),
                skill(_sdT('Junctions'), '0', '0', '0'),
                skill(_sdT('Speed'), '1', '0', '0'),
                skill(_sdT('Road position'), '0', '0', '0'),
                skill(_sdT('Manoeuvre'), '0', '0', '0')
            ])
        ]);
        return kit.section('py-5 bg-body-tertiary', _sdT('Driving School (Test Sheet)'), [
            header(_sdT('How the practical test goes'), _sdT('About forty minutes on real roads, then the result in the car.')),
            kit.row(_sdT('Test Row'), [
                kit.col(_sdT('Stages Column'), [
                    rise(kit.tag('ol', 'list-unstyled mb-0', _sdT('Test Stages'), [
                        stage(1, _sdT('{var} min', '2'), _sdT('Eyesight check'), _sdT('Reading a number plate from twenty metres.')),
                        stage(2, _sdT('{var} min', '3'), _sdT('Safety questions'), _sdT('Show me, tell me: a check under the bonnet or at the wheel.')),
                        stage(3, _sdT('{var} min', '20'), _sdT('General driving'), _sdT('Town roads, a main road and the directions the examiner gives.')),
                        stage(4, _sdT('{var} min', '10'), _sdT('Driving on your own'), _sdT('Following road signs to a place the examiner names.')),
                        stage(5, _sdT('{var} min', '5'), _sdT('One manoeuvre'), _sdT('Parking in a bay or at the kerb, slowly and under control.')),
                        stage(6, _sdT('{var} min', '5'), _sdT('Result and feedback'), _sdT('The examiner goes through the sheet with you and your instructor.'))
                    ], null), 70)
                ], '5'),
                kit.col(_sdT('Sheet Column'), [
                    kit.div('card border-0 shadow-sm pg-drv-sheet', _sdT('Test Sheet'), [
                        kit.div('card-body p-4', _sdT('Card Body'), [
                            kit.div('d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', _sdT('Card Head'), [
                                kit.h('h3', _sdT('What the examiner marks'), 'h5 fw-bold mb-0'),
                                kit.tag('span', 'badge rounded-pill text-wrap bg-success-subtle text-success-emphasis', _sdT('Status Badge'), [
                                    kit.icon('bi-check-circle-fill', 'me-1'),
                                    kit.h('span', _sdT('Passed'))
                                ])
                            ]),
                            kit.div('table-responsive mb-3', _sdT('Table Wrap'), [table], kit.region(_sdT('Faults marked on a sample test sheet'))),
                            kit.tag('ul', 'list-unstyled d-grid gap-2 small mb-3', _sdT('Fault Levels'), [
                                level('bi-dash-circle', 'text-body-secondary', _sdT('Driving fault'), _sdT('up to {var} allowed', '15')),
                                level('bi-exclamation-circle-fill', 'text-warning', _sdT('Serious fault'), _sdT('one means a retake')),
                                level('bi-x-octagon-fill', 'text-danger', _sdT('Dangerous fault'), _sdT('one means a retake'))
                            ]),
                            kit.p(_sdT('This sheet: {var:1} driving faults of {var:2} allowed, none serious or dangerous.', ['4', '15']), 'small fw-semibold mb-0')
                        ])
                    ])
                ], '7')
            ], 'g-lg-5')
        ]);
    };

    return {
        prefix: 'drivingschool-',
        group: { title: _sdT('Driving School'), icon: 'bi-car-front' },
        items: [
            { label: _sdT('Driving School: Licence Classes'), type: 'component', icon: 'bi-postcard',         extra: { componentType: 'drivingschool-licences' },
              desc: _sdT('Four licence class cards, each with its letter on a number plate, the vehicle, minimum age, lessons, gearbox and a link.') },
            { label: _sdT('Driving School: Theory Topics'),   type: 'component', icon: 'bi-sign-stop',        extra: { componentType: 'drivingschool-signs' },
              desc: _sdT('The pass mark in a framed box with a practice button, beside six theory topics drawn as road signs with their question counts.') },
            { label: _sdT('Driving School: Test Sheet'),      type: 'component', icon: 'bi-clipboard-check',  extra: { componentType: 'drivingschool-test-sheet' },
              desc: _sdT('The six stages of the driving test with their minutes, beside a sample sheet of the faults marked per skill and the result.') }
        ],
        build: function (ctype) {
            if (ctype === 'drivingschool-licences')   return buildLicences();
            if (ctype === 'drivingschool-signs')      return buildSigns();
            if (ctype === 'drivingschool-test-sheet') return buildTestSheet();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
