/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual designer's Recruitment blocks (prefix recruitment-): the
 * sections of a recruitment agency's site the palette lacks. A hero whose
 * way in is a job search (a keyword and a city), with popular searches
 * under it and the newest jobs rising beside it; a job board, each job a
 * card with its sector, the employer described, the city, the contract,
 * the way of working and the salary band, the contracts as filters above;
 * and a salary guide, a table of monthly ranges per job, each range drawn
 * as a bar of ten steps under its figures. Sectors, steps, consultants,
 * offices and the employers' call are placed with general blocks. Motion
 * comes from the nodes' interactions (props._fx, played by pg_fx.src.js,
 * which honours reduced motion).
 *
 * Registered with StyleDesigner.registerBlockFamily() and bundled after the
 * designer core (pg_designer_js_sources()); see
 * docs/memory/designer/designer-blocks-recruitment.md.
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
        // The search bar: the keyword field takes the room left, the city
        // keeps a select's width beside it from 768 px up.
        'pg-recruitment-search': '@media (min-width: 768px) { .pg-recruitment-search .form-select { width: 12rem; flex-shrink: 0; } }',
        // A job: a card whose title link covers it; its border takes the
        // primary colour under the pointer and with the keyboard on its link.
        'pg-recruitment-job':    '.pg-recruitment-job { position: relative; border: var(--bs-border-width) solid var(--bs-border-color); } ' +
                                 '.pg-recruitment-job:hover, .pg-recruitment-job:focus-within { border-color: var(--bs-primary); }',
        // The sector's icon in a square of one size, whatever the icon.
        'pg-recruitment-icon':   '.pg-recruitment-icon { display: inline-flex; align-items: center; justify-content: center; width: 3rem; height: 3rem; flex-shrink: 0; }',
        // Salaries that line up and never break between a figure and its
        // currency.
        'pg-recruitment-figure': '.pg-recruitment-figure { font-variant-numeric: tabular-nums; white-space: nowrap; }',
        // A salary range as ten steps of one width; the steps the range
        // covers take the primary colour.
        'pg-recruitment-scale':  '.pg-recruitment-scale { display: grid; grid-template-columns: repeat(10, minmax(0, 1fr)); gap: .1875rem; min-width: 7.5rem; margin-top: .375rem; } ' +
                                 '.pg-recruitment-scale .pg-recruitment-step { height: .5rem; border-radius: 50rem; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'recruitment-search':   ['pg-recruitment-search', 'pg-recruitment-job', 'pg-recruitment-icon', 'pg-recruitment-figure'],
        'recruitment-board':    ['pg-recruitment-job', 'pg-recruitment-icon', 'pg-recruitment-figure'],
        'recruitment-salaries': ['pg-recruitment-scale', 'pg-recruitment-figure']
    };

    // A node with an interaction (props._fx, designer-interactions.md).
    var fx = function (node, effect) {
        node.props._fx = effect;
        return node;
    };
    // The salary bars are drawings: the figures above them say the range.
    var hidden = [{ name: 'aria-hidden', value: 'true' }];
    // Small primary text keeps its contrast on bg-body only; on a tinted
    // band the eyebrow takes another colour.
    var eyebrow = function (text, cls) {
        return kit.h('span', text, 'd-block mb-3 small fw-semibold text-uppercase ' + (cls || 'text-primary'));
    };
    // A monthly salary band: the two figures and the period, each its own
    // node, so a price is translated alone.
    var salary = function (low, high, cls) {
        return kit.div('pg-recruitment-figure ' + cls, _sdT('Salary'), [
            kit.h('span', low),
            kit.h('span', ' – '),
            kit.h('span', high),
            kit.h('span', _sdT('/mo'), 'fw-normal text-body-secondary')
        ]);
    };
    // The sector's icon in its square.
    var sectorIcon = function (icon) {
        return kit.div('pg-recruitment-icon rounded-3 bg-primary-subtle text-primary-emphasis', _sdT('Sector Icon'), [ kit.icon(icon, 'fs-4') ]);
    };
    // A job's title as a heading whose link covers its card.
    var jobTitle = function (title, cls) {
        return kit.tag('h3', cls + ' mb-0', _sdT('Job Title'), [
            kit.link(title, '#', 'stretched-link link-body-emphasis text-decoration-none', _sdT('Job Link'))
        ]);
    };
    // A line with an icon in front.
    var fact = function (icon, text, cls) {
        return kit.tag('li', 'd-inline-flex align-items-center gap-2 ' + (cls || ''), _sdT('Item'), [
            kit.icon(icon, 'text-primary'),
            kit.h('span', text)
        ]);
    };

    return {
        prefix: 'recruitment-',
        group: { title: _sdT('Recruitment'), icon: 'bi-person-workspace' },
        items: [
            { label: _sdT('Recruitment: Job Search Hero'), type: 'component', icon: 'bi-search',     extra: { componentType: 'recruitment-search' },
              desc: _sdT('A large headline over a job search by keyword and city, popular searches as pill links and the newest jobs as cards beside it.') },
            { label: _sdT('Recruitment: Job Board'),       type: 'component', icon: 'bi-briefcase',  extra: { componentType: 'recruitment-board' },
              desc: _sdT('Open jobs as cards with the sector, the employer, the city, the contract, the way of working and the salary, under contract filters.') },
            { label: _sdT('Recruitment: Salary Guide'),    type: 'component', icon: 'bi-cash-stack', extra: { componentType: 'recruitment-salaries' },
              desc: _sdT('Monthly salary ranges per job in a table, each range drawn as a bar of ten steps under its figures, beside three notes and a button.') }
        ],
        build: function (ctype) {

            // Recruitment: Job Search Hero — the words rise on load and the
            // search bar fades in under them: a keyword, a city and the
            // button, sent as a search (GET), so the filters stay in the
            // address. Popular searches follow as pills; beside them the
            // newest jobs rise one after another.
            if (ctype === 'recruitment-search') {
                var rsId = kit.uid('rs');
                var _rsOption = function (text, value) {
                    return kit.createNode('semantic', { tag: 'option', text: text, customName: _sdT('Option'), _attrs: [{ name: 'value', value: value }] });
                };
                var _rsPopular = function (text) {
                    return kit.tag('li', '', text, [ kit.link(text, '#', 'btn btn-sm btn-outline-primary rounded-pill', text) ]);
                };
                var _rsJob = function (icon, title, city, mode, low, high) {
                    return kit.tag('li', '', title, [
                        kit.div('pg-recruitment-job d-flex align-items-center gap-3 p-3 rounded-3 bg-body', _sdT('Job'), [
                            sectorIcon(icon),
                            kit.div('flex-grow-1', _sdT('Job Text'), [
                                jobTitle(title, 'h6 fw-bold'),
                                kit.tag('ul', 'list-unstyled d-flex flex-wrap column-gap-3 small text-body-secondary my-1', _sdT('Job Facts'), [
                                    fact('bi-geo-alt', city),
                                    fact('bi-laptop', mode)
                                ]),
                                salary(low, high, 'small fw-semibold text-body-emphasis')
                            ])
                        ])
                    ]);
                };
                return kit.tag('section', 'py-5 overflow-hidden', _sdT('Recruitment Hero'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                        kit.row(_sdT('Hero Row'), [
                            kit.col(_sdT('Hero Text'), [
                                fx(kit.div('', _sdT('Body'), [
                                    eyebrow(_sdT('Recruitment agency')),
                                    kit.h('h1', _sdT('Find the job that fits your next step'), 'display-4 fw-bold mb-4'),
                                    kit.p(_sdT('Checked jobs in six sectors, and a consultant who reads every CV and calls you back.'), 'lead text-body-secondary mb-4')
                                ]), { effect: 'slide-up', trigger: 'load', duration: 800 }),
                                fx(kit.tag('form', 'pg-recruitment-search d-flex flex-column flex-md-row gap-2 p-2 mb-4 rounded-4 border bg-body shadow-sm', _sdT('Job Search'), [
                                    kit.tag('label', 'visually-hidden', _sdT('Keyword Label'), [ kit.h('span', _sdT('Job title or keyword')) ], [{ name: 'for', value: rsId + '_q' }]),
                                    kit.tag('input', 'form-control form-control-lg border-0 flex-grow-1', _sdT('Keyword'), [], [
                                        { name: 'type',        value: 'search' },
                                        { name: 'id',          value: rsId + '_q' },
                                        { name: 'name',        value: 'q' },
                                        { name: 'placeholder', value: _sdT('Job title, skill or company') }
                                    ]),
                                    kit.tag('label', 'visually-hidden', _sdT('City Label'), [ kit.h('span', _sdT('City')) ], [{ name: 'for', value: rsId + '_city' }]),
                                    kit.tag('select', 'form-select form-select-lg border-0', _sdT('City'), [
                                        _rsOption(_sdT('All cities'), ''),
                                        _rsOption(_sdT('Istanbul'), 'Istanbul'),
                                        _rsOption(_sdT('London'), 'London'),
                                        _rsOption(_sdT('Berlin'), 'Berlin')
                                    ], [
                                        { name: 'id',   value: rsId + '_city' },
                                        { name: 'name', value: 'city' }
                                    ]),
                                    kit.tag('button', 'btn btn-primary btn-lg d-inline-flex align-items-center justify-content-center gap-2 text-nowrap', _sdT('Search Button'), [
                                        kit.icon('bi-search'),
                                        kit.h('span', _sdT('Search Jobs'))
                                    ], [{ name: 'type', value: 'submit' }])
                                ], [{ name: 'role', value: 'search' }]), { effect: 'fade', trigger: 'load', duration: 700, delay: 400 }),
                                kit.div('d-flex flex-wrap align-items-center gap-2', _sdT('Popular Searches'), [
                                    kit.h('span', _sdT('Popular searches'), 'small fw-semibold text-body-secondary me-1'),
                                    kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', _sdT('Searches'), [
                                        _rsPopular(_sdT('Developer')),
                                        _rsPopular(_sdT('Accountant')),
                                        _rsPopular(_sdT('Nurse')),
                                        _rsPopular(_sdT('Warehouse'))
                                    ])
                                ])
                            ], '7'),
                            kit.col(_sdT('Newest Jobs'), [
                                kit.div('p-3 p-lg-4 rounded-4 bg-body-tertiary', _sdT('Job Stack'), [
                                    kit.h('h2', _sdT('Just added'), 'h6 fw-bold text-uppercase mb-3'),
                                    fx(kit.tag('ul', 'list-unstyled d-grid gap-2 mb-3', _sdT('Jobs'), [
                                        _rsJob('bi-cpu', _sdT('Senior Software Developer'), _sdT('Istanbul'), _sdT('Hybrid'), _sdT('$3,500'), _sdT('$5,000')),
                                        _rsJob('bi-calculator', _sdT('Accountant'), _sdT('London'), _sdT('On-site'), _sdT('$1,800'), _sdT('$2,400')),
                                        _rsJob('bi-heart-pulse', _sdT('Staff Nurse'), _sdT('Berlin'), _sdT('On-site'), _sdT('$1,500'), _sdT('$2,000'))
                                    ]), { effect: 'slide-up', trigger: 'load', duration: 600, delay: 600, stagger: 120, distance: 24 }),
                                    kit.div('d-flex align-items-center gap-2 small text-body-secondary', _sdT('Promise'), [
                                        kit.icon('bi-shield-check', 'text-primary'),
                                        kit.h('span', _sdT('Free for candidates'))
                                    ])
                                ])
                            ], '5')
                        ], 'align-items-center g-4 g-lg-5')
                    ])
                ]);
            }

            // Recruitment: Job Board — the header fades in on view; the
            // contracts sit above as filters, the current one marked for
            // screen readers too; the jobs rise one after another, each a
            // card whose title link opens the job.
            if (ctype === 'recruitment-board') {
                var _rbFilter = function (text, current) {
                    var link = kit.link(text, '#', 'nav-link' + (current ? ' active' : ''), text);
                    if (current) link.props._attrs = [{ name: 'aria-current', value: 'true' }];
                    return kit.tag('li', 'nav-item', text, [ link ]);
                };
                var _rbJob = function (icon, title, employer, city, contract, mode, low, high, featured) {
                    var head = [ jobTitle(title, 'h5 fw-bold') ];
                    if (featured) {
                        head.push(kit.tag('span', 'badge rounded-pill text-bg-primary d-inline-flex align-items-center gap-1', _sdT('Featured Badge'), [
                            kit.icon('bi-star-fill'),
                            kit.h('span', _sdT('Featured'))
                        ]));
                    }
                    return kit.tag('li', '', title, [
                        kit.div('pg-recruitment-job d-flex flex-column flex-md-row align-items-md-center gap-3 p-3 p-md-4 rounded-4 bg-body', _sdT('Job'), [
                            sectorIcon(icon),
                            kit.div('flex-grow-1', _sdT('Job Text'), [
                                kit.div('d-flex flex-wrap align-items-center gap-2 mb-1', _sdT('Job Title Row'), head),
                                kit.p(employer, 'small text-body-secondary mb-2'),
                                kit.tag('ul', 'list-unstyled d-flex flex-wrap column-gap-3 row-gap-1 small mb-0', _sdT('Job Facts'), [
                                    fact('bi-geo-alt', city),
                                    fact('bi-file-earmark-text', contract),
                                    fact('bi-laptop', mode)
                                ])
                            ]),
                            salary(low, high, 'fw-bold text-body-emphasis text-md-end')
                        ])
                    ]);
                };
                return kit.section('py-5', _sdT('Recruitment Job Board'), [
                    fx(kit.div('d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4', _sdT('Section Header'), [
                        kit.div('', _sdT('Section Title'), [
                            eyebrow(_sdT('Open jobs')),
                            kit.h('h2', _sdT('Jobs our consultants are filling now'), 'display-6 fw-bold mb-0')
                        ]),
                        kit.btn(_sdT('All Jobs'), 'outline-primary', '')
                    ]), { effect: 'fade', trigger: 'view', duration: 700 }),
                    kit.tag('nav', 'mb-4', _sdT('Contract Filter'), [
                        kit.tag('ul', 'nav nav-pills gap-2', _sdT('Contracts'), [
                            _rbFilter(_sdT('All'), true),
                            _rbFilter(_sdT('Permanent')),
                            _rbFilter(_sdT('Fixed-term contract')),
                            _rbFilter(_sdT('Temporary')),
                            _rbFilter(_sdT('Part-time'))
                        ])
                    ], [{ name: 'aria-label', value: _sdT('Contract type') }]),
                    fx(kit.tag('ul', 'list-unstyled d-grid gap-3 mb-4', _sdT('Jobs'), [
                        _rbJob('bi-cpu', _sdT('Senior Software Developer'), _sdT('A fast-growing online payments company'), _sdT('Istanbul'), _sdT('Permanent'), _sdT('Hybrid'), _sdT('$3,500'), _sdT('$5,000'), true),
                        _rbJob('bi-calculator', _sdT('Accountant'), _sdT('A family-owned food exporter'), _sdT('London'), _sdT('Permanent'), _sdT('On-site'), _sdT('$1,800'), _sdT('$2,400')),
                        _rbJob('bi-heart-pulse', _sdT('Staff Nurse'), _sdT('A private hospital'), _sdT('Berlin'), _sdT('Fixed-term contract'), _sdT('On-site'), _sdT('$1,500'), _sdT('$2,000')),
                        _rbJob('bi-truck', _sdT('Warehouse Shift Supervisor'), _sdT('An international logistics company'), _sdT('Istanbul'), _sdT('Temporary'), _sdT('On-site'), _sdT('$1,200'), _sdT('$1,500'))
                    ]), { effect: 'slide-up', trigger: 'view', duration: 500, stagger: 80, distance: 20 }),
                    kit.div('d-flex flex-column flex-sm-row align-items-sm-center gap-3 p-3 p-md-4 rounded-4 border', _sdT('Open Application'), [
                        kit.icon('bi-envelope-paper', 'fs-3 text-primary'),
                        kit.p(_sdT('Not the job you are looking for? Send an open application and a consultant will call you.'), 'mb-0 flex-grow-1'),
                        kit.btn(_sdT('Send an Open Application'), 'primary', '')
                    ])
                ]);
            }

            // Recruitment: Salary Guide — the words fade in on view; beside
            // them a table of monthly ranges per job, its rows appearing one
            // after another. Each range is written as two figures and drawn
            // under them as a bar of ten steps (hidden from screen readers),
            // in a table that scrolls sideways on a narrow phone.
            if (ctype === 'recruitment-salaries') {
                var cell = function (tag, kids, cls, scope) {
                    var attrs = scope ? [{ name: 'scope', value: scope }] : null;
                    return kit.tag(tag, cls || '', tag === 'th' ? _sdT('Header Cell') : _sdT('Cell'), kids, attrs);
                };
                // The steps a range covers, from the first to the last of ten.
                var _rgScale = function (first, last) {
                    var steps = [];
                    for (var i = 1; i <= 10; i++) {
                        steps.push(kit.div('pg-recruitment-step ' + (i >= first && i <= last ? 'bg-primary' : 'bg-secondary-subtle'), _sdT('Step'), []));
                    }
                    return kit.div('pg-recruitment-scale', _sdT('Salary Bar'), steps, hidden);
                };
                var _rgRow = function (title, sector, low, high, first, last) {
                    return kit.tag('tr', '', title, [
                        cell('th', [ kit.h('span', title) ], 'fw-semibold', 'row'),
                        cell('td', [ kit.h('span', sector) ], 'small text-body-secondary'),
                        cell('td', [ salary(low, high, 'fw-semibold text-body-emphasis'), _rgScale(first, last) ], '')
                    ]);
                };
                var table = kit.tag('table', 'table align-middle mb-0', _sdT('Salary Table'), [
                    kit.tag('caption', 'visually-hidden', _sdT('Table Caption'), [ kit.h('span', _sdT('Monthly salary ranges per job')) ]),
                    kit.tag('thead', '', _sdT('Table Head'), [
                        kit.tag('tr', '', _sdT('Head Row'), [
                            cell('th', [ kit.h('span', _sdT('Job title')) ], '', 'col'),
                            cell('th', [ kit.h('span', _sdT('Sector')) ], '', 'col'),
                            cell('th', [ kit.h('span', _sdT('Monthly salary')) ], 'text-nowrap', 'col')
                        ])
                    ]),
                    fx(kit.tag('tbody', '', _sdT('Table Body'), [
                        _rgRow(_sdT('Senior Software Developer'), _sdT('Technology'), _sdT('$3,500'), _sdT('$5,000'), 8, 10),
                        _rgRow(_sdT('Data Analyst'), _sdT('Technology'), _sdT('$2,200'), _sdT('$3,000'), 5, 6),
                        _rgRow(_sdT('Mechanical Engineer'), _sdT('Engineering and Manufacturing'), _sdT('$2,000'), _sdT('$2,800'), 5, 6),
                        _rgRow(_sdT('Accountant'), _sdT('Finance and Accounting'), _sdT('$1,800'), _sdT('$2,400'), 4, 5),
                        _rgRow(_sdT('Staff Nurse'), _sdT('Healthcare'), _sdT('$1,500'), _sdT('$2,000'), 4, 4),
                        _rgRow(_sdT('Warehouse Shift Supervisor'), _sdT('Logistics and Supply Chain'), _sdT('$1,200'), _sdT('$1,500'), 3, 3)
                    ]), { effect: 'fade', trigger: 'view', duration: 500, stagger: 90 })
                ]);
                return kit.tag('section', 'py-5 bg-body-tertiary', _sdT('Recruitment Salary Guide'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                        kit.row(_sdT('Row'), [
                            kit.col(_sdT('Text'), [
                                fx(kit.div('', _sdT('Body'), [
                                    eyebrow(_sdT('Salary guide'), 'text-body-secondary'),
                                    kit.h('h2', _sdT('What the jobs pay'), 'display-6 fw-bold mb-3'),
                                    kit.p(_sdT('Monthly ranges from the offers our candidates accepted, so you know what to ask for before the interview.'), 'lead text-body-secondary mb-4')
                                ]), { effect: 'fade', trigger: 'view', duration: 700 }),
                                kit.tag('ul', 'list-unstyled d-grid gap-2 mb-4', _sdT('Notes'), [
                                    fact('bi-cash-coin', _sdT('Gross pay, before tax')),
                                    fact('bi-check2-circle', _sdT('From offers our candidates accepted')),
                                    fact('bi-plus-circle', _sdT('Bonuses and commission come on top'))
                                ]),
                                kit.btn(_sdT('Ask a Consultant'), 'primary', 'lg')
                            ], '4'),
                            kit.col(_sdT('Ranges'), [
                                kit.div('table-responsive position-relative rounded-4 border bg-body p-2 p-md-3', _sdT('Table Wrap'), [ table ], kit.region(_sdT('Monthly salary ranges')))
                            ], '8')
                        ], 'align-items-center g-4 g-lg-5')
                    ])
                ]);
            }

            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
