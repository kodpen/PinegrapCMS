/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual designer's Library blocks (prefix library-): the sections of a
 * public library's site the palette lacks. A hero whose shelf fills with
 * book spines as the page opens, today's opening hours under it and the
 * formats to browse as a row of ways in; the shelf mark guide, a spine
 * label taken apart (the subject number, the author's letters, fiction
 * apart) beside the ten subject classes the non-fiction shelves run
 * through; and the library card, drawn with its barcode beside what it
 * lends, how many at a time, for how long and how often a loan is renewed.
 * Branches, hours, events, rooms and services are placed with general
 * blocks. Motion comes from the nodes' interactions (props._fx, played by
 * pg_fx.src.js, which honours reduced motion).
 *
 * Registered with StyleDesigner.registerBlockFamily() and bundled after the
 * designer core (pg_designer_js_sources()); see
 * docs/memory/designer/designer-blocks-library.md.
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
        // The shelf: a grid that gives every spine a column of the same
        // width, so any number of books fits a phone screen, standing on a
        // plank drawn as the bottom border. The tallest spine sets the
        // shelf's height, so no book stands over the plank. The spines'
        // heights are fixed (a share of the shelf's height would collapse
        // inside the canvas's wrappers); English spines read from the top
        // down.
        'pg-library-shelf':   '.pg-library-shelf { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); align-items: end; gap: .25rem; ' +
                              'padding-inline: .5rem; border-bottom: .75rem solid var(--bs-secondary-color); } ' +
                              '.pg-library-shelf .pg-library-spine { display: flex; align-items: center; justify-content: center; height: 15rem; overflow: hidden; border-radius: .25rem .25rem 0 0; } ' +
                              '.pg-library-shelf .pg-library-mid { height: 12.5rem; } ' +
                              '.pg-library-shelf .pg-library-low { height: 10.5rem; } ' +
                              '.pg-library-shelf .pg-library-title { writing-mode: vertical-rl; white-space: nowrap; font-size: .75rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; }',
        // The shelf mark drawn on the lower end of a spine, as narrow as a
        // book.
        'pg-library-label':   '.pg-library-label { max-width: 15rem; margin-inline: auto; }',
        // The library card at a bank card's shape; on a narrow phone it
        // grows taller rather than cutting its words.
        'pg-library-card':    '.pg-library-card { aspect-ratio: 856 / 540; max-width: 24rem; margin-inline: auto; }',
        // The card's barcode: bars of the text colour, so it follows the
        // surface it is drawn on.
        'pg-library-barcode': '.pg-library-barcode { height: 2.5rem; background-image: repeating-linear-gradient(90deg, currentColor 0 .125rem, transparent .125rem .3rem, ' +
                              'currentColor .3rem .4rem, transparent .4rem .55rem, currentColor .55rem .75rem, transparent .75rem .875rem); }',
        // Numbers, times and shelf marks that line up.
        'pg-library-figure':  '.pg-library-figure { font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'library-hero':      ['pg-library-shelf', 'pg-library-figure'],
        'library-shelfmark': ['pg-library-label', 'pg-library-figure'],
        'library-card':      ['pg-library-card', 'pg-library-barcode', 'pg-library-figure']
    };

    // A node with an interaction (props._fx, designer-interactions.md).
    var fx = function (node, effect) {
        node.props._fx = effect;
        return node;
    };
    // The shelf, the spine label and the card are drawings: the words
    // beside them say what they show.
    var hidden = [{ name: 'aria-hidden', value: 'true' }];
    // Small primary text keeps its contrast on bg-body only; on a tinted
    // band the eyebrow takes another colour.
    var eyebrow = function (text, cls) {
        return kit.h('span', text, 'd-block mb-3 small fw-semibold text-uppercase ' + (cls || 'text-primary'));
    };
    // A line with an icon in front.
    var tick = function (icon, text) {
        return kit.tag('li', 'd-flex align-items-center gap-2', _sdT('Item'), [
            kit.icon(icon, 'text-primary'),
            kit.h('span', text)
        ]);
    };

    return {
        prefix: 'library-',
        group: { title: _sdT('Library'), icon: 'bi-book' },
        items: [
            { label: _sdT('Library: Shelf Hero'),       type: 'component', icon: 'bi-book',          extra: { componentType: 'library-hero' },
              desc: _sdT('A large headline and two buttons beside a bookcase of coloured spines, the opening hours for today and six formats as pill links.') },
            { label: _sdT('Library: Shelf Mark Guide'), type: 'component', icon: 'bi-bookmark',      extra: { componentType: 'library-shelfmark' },
              desc: _sdT('A book spine with its label drawn large and read part by part, beside the ten subject classes with the example marked.') },
            { label: _sdT('Library: Card and Loans'),   type: 'component', icon: 'bi-person-vcard',  extra: { componentType: 'library-card' },
              desc: _sdT('A library card in the main colour of the design beside a loans table per format, three short facts and two buttons.') }
        ],
        build: function (ctype) {

            // Library: Shelf Hero — the words rise on load; beside them a
            // shelf fills with books, set down one after another, and
            // today's opening hours appear under it; the formats follow as a
            // row of ways into the catalogue.
            if (ctype === 'library-hero') {
                var _lhBook = function (title, cls) {
                    return kit.div('pg-library-spine ' + cls, _sdT('Book Spine'), [
                        kit.h('span', title, 'pg-library-title')
                    ]);
                };
                var _lhFormat = function (icon, text) {
                    return kit.tag('li', '', text, [
                        kit.createNode('semantic', { tag: 'a', cssClass: 'btn btn-outline-primary rounded-pill d-inline-flex align-items-center gap-2', href: '#', target: '', customName: text }, [
                            kit.icon(icon),
                            kit.h('span', text)
                        ])
                    ]);
                };
                return kit.tag('section', 'py-5 overflow-hidden', _sdT('Library Hero'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                        kit.row(_sdT('Hero Row'), [
                            kit.col(_sdT('Hero Text'), [
                                fx(kit.div('', _sdT('Body'), [
                                    eyebrow(_sdT('Your public library')),
                                    kit.h('h1', _sdT('A shelf full of stories, free to borrow'), 'display-3 fw-bold mb-4'),
                                    kit.p(_sdT('Books, eBooks, films and audiobooks for every age, a quiet place to study and something on every week. All you need is a library card.'), 'lead text-body-secondary mb-4'),
                                    kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                                        kit.btn(_sdT('Search the Catalogue'), 'primary', 'lg'),
                                        kit.btn(_sdT('Get a Library Card'), 'outline-primary', 'lg')
                                    ])
                                ]), { effect: 'slide-up', trigger: 'load', duration: 800 })
                            ], '6'),
                            kit.col(_sdT('Hero Picture'), [
                                kit.div('p-4 p-lg-5 rounded-4 bg-body-tertiary', _sdT('Bookcase'), [
                                    fx(kit.div('pg-library-shelf', _sdT('Shelf'), [
                                        _lhBook(_sdT('Poems'), 'text-bg-primary'),
                                        _lhBook(_sdT('Atlas'), 'pg-library-mid bg-body-secondary text-body-emphasis'),
                                        _lhBook(_sdT('Mysteries'), 'text-bg-secondary'),
                                        _lhBook(_sdT('Cookbook'), 'pg-library-low bg-primary-subtle text-primary-emphasis'),
                                        _lhBook(_sdT('Fairy Tales'), 'pg-library-mid text-bg-primary'),
                                        _lhBook(_sdT('The Sea'), 'bg-body text-body-emphasis border'),
                                        _lhBook(_sdT('Stars'), 'pg-library-low text-bg-secondary'),
                                        _lhBook(_sdT('Gardening'), 'bg-primary-subtle text-primary-emphasis'),
                                        _lhBook(_sdT('Short Stories'), 'text-bg-primary'),
                                        _lhBook(_sdT('Birds'), 'pg-library-mid bg-body-secondary text-body-emphasis'),
                                        _lhBook(_sdT('Novels'), 'pg-library-low text-bg-secondary')
                                    ], hidden), { effect: 'slide-down', trigger: 'load', duration: 500, delay: 200, stagger: 60, distance: 30 }),
                                    fx(kit.div('d-flex flex-wrap align-items-center gap-3 mt-4 p-3 rounded-4 bg-body shadow-sm', _sdT('Open Today'), [
                                        kit.icon('bi-door-open', 'text-primary fs-2'),
                                        kit.div('', _sdT('Text'), [
                                            kit.h('span', _sdT('Open today'), 'd-block small text-body-secondary mb-1'),
                                            kit.div('pg-library-figure fs-4 fw-bold text-body-emphasis', _sdT('Opening Hours'), [
                                                kit.time('09:00', '09:00', '', _sdT('Opening Time')),
                                                kit.h('span', ' – '),
                                                kit.time('20:00', '20:00', '', _sdT('Closing Time'))
                                            ])
                                        ]),
                                        kit.h('span', _sdT('Central Library'), 'small fw-semibold ms-sm-auto')
                                    ]), { effect: 'fade', trigger: 'load', duration: 600, delay: 900 })
                                ])
                            ], '6')
                        ], 'align-items-center g-4 g-lg-5'),
                        kit.div('mt-5 pt-4 border-top', _sdT('Formats'), [
                            kit.h('h2', _sdT('Browse by format'), 'h6 text-uppercase fw-semibold text-body-secondary mb-3'),
                            fx(kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', _sdT('Formats'), [
                                _lhFormat('bi-book', _sdT('Books')),
                                _lhFormat('bi-tablet', _sdT('eBooks')),
                                _lhFormat('bi-headphones', _sdT('Audiobooks')),
                                _lhFormat('bi-film', _sdT('Films')),
                                _lhFormat('bi-newspaper', _sdT('Magazines')),
                                _lhFormat('bi-music-note-beamed', _sdT('Music'))
                            ]), { effect: 'fade', trigger: 'load', duration: 600, delay: 1100, stagger: 80 })
                        ])
                    ])
                ]);
            }

            // Library: Shelf Mark Guide — the label on a book's spine drawn
            // large and taken apart beside it: the subject number, the
            // author's first letters and the fiction shelves. Beside that
            // the ten subject classes, rising one after another, with the
            // one the example belongs to marked in words as well as colour.
            if (ctype === 'library-shelfmark') {
                var _lsPart = function (code, text) {
                    return kit.div('d-flex gap-3 mb-3', _sdT('Label Part'), [
                        kit.tag('dt', 'pg-library-figure font-monospace fw-bold text-primary flex-shrink-0', _sdT('Term'), [ kit.h('span', code) ]),
                        kit.tag('dd', 'mb-0 text-body-secondary', _sdT('Description'), [ kit.h('span', text) ])
                    ]);
                };
                var _lsClass = function (number, name, example) {
                    var kids = [
                        kit.h('span', number, 'pg-library-figure font-monospace fw-bold'),
                        kit.h('span', name)
                    ];
                    if (example) kids.push(kit.h('span', _sdT('Example'), 'badge rounded-pill text-bg-primary ms-auto'));
                    return kit.tag('li', 'col', number, [
                        kit.div('d-flex align-items-center gap-3 h-100 px-3 py-2 rounded-3 ' + (example ? 'bg-primary-subtle text-primary-emphasis' : 'bg-body-tertiary'), _sdT('Subject'), kids)
                    ]);
                };
                return kit.section('py-5', _sdT('Library Shelf Mark Guide'), [
                    fx(kit.div('row g-4 align-items-end mb-5', _sdT('Section Header'), [
                        kit.div('col-lg-7', _sdT('Section Title'), [
                            eyebrow(_sdT('Finding a book')),
                            kit.h('h2', _sdT('Every book has its own place on the shelf'), 'display-5 fw-bold mb-0')
                        ]),
                        kit.div('col-lg-5', _sdT('Lead'), [
                            kit.p(_sdT('The label on the spine is the address of a book in the library: a number for its subject and three letters for its author.'), 'lead text-body-secondary mb-0')
                        ])
                    ]), { effect: 'fade', trigger: 'view', duration: 800 }),
                    kit.row(_sdT('Row'), [
                        kit.col(_sdT('The Label'), [
                            fx(kit.div('pg-library-label px-4 pt-5 pb-4 rounded-top-4 text-bg-primary shadow', _sdT('Spine'), [
                                kit.h('span', _sdT('Simple Suppers'), 'd-block text-center small fw-semibold text-uppercase mb-5'),
                                kit.div('px-3 py-3 rounded-3 bg-body text-body-emphasis text-center font-monospace', _sdT('Spine Label'), [
                                    kit.h('span', '641.5', 'pg-library-figure d-block fs-2 fw-bold lh-1 mb-2'),
                                    kit.h('span', 'OLI', 'd-block fs-3 fw-bold lh-1')
                                ])
                            ], hidden), { effect: 'scale', trigger: 'view', duration: 800 }),
                            kit.h('h3', _sdT('How to read the label'), 'h5 fw-bold mt-4 mb-3'),
                            kit.tag('dl', 'mb-0', _sdT('Label Parts'), [
                                _lsPart('641.5', _sdT('The subject: 600 is technology, 640 the home and 641.5 cooking.')),
                                _lsPart('OLI', _sdT('The author: the first three letters of the surname.')),
                                _lsPart('F', _sdT('Fiction: novels and stories stand apart, from A to Z by surname.'))
                            ])
                        ], '5', '', '', 'col-xl-4'),
                        kit.col(_sdT('Subjects'), [
                            kit.h('h3', _sdT('The ten subjects'), 'h5 fw-bold mb-2'),
                            kit.p(_sdT('Non-fiction runs along the shelves from 000 to 999.'), 'text-body-secondary mb-4'),
                            fx(kit.tag('ol', 'list-unstyled row row-cols-1 row-cols-sm-2 g-2 mb-4', _sdT('Subjects'), [
                                _lsClass('000', _sdT('General knowledge and computers')),
                                _lsClass('100', _sdT('Philosophy and psychology')),
                                _lsClass('200', _sdT('Religion')),
                                _lsClass('300', _sdT('Society, law and education')),
                                _lsClass('400', _sdT('Languages')),
                                _lsClass('500', _sdT('Science and nature')),
                                _lsClass('600', _sdT('Technology, health and cooking'), true),
                                _lsClass('700', _sdT('Arts, music and sport')),
                                _lsClass('800', _sdT('Literature and poetry')),
                                _lsClass('900', _sdT('History, geography and travel'))
                            ]), { effect: 'slide-up', trigger: 'view', duration: 500, stagger: 60, distance: 20 }),
                            kit.btn(_sdT('Ask a Librarian'), 'outline-primary', '')
                        ], '7', '', '', 'offset-xl-1')
                    ], 'g-5')
                ]);
            }

            // Library: Card and Loans — the card drawn at a bank card's
            // shape slides in with its barcode; beside it what a card
            // lends: each format with how many at a time, for how long and
            // how often a loan is renewed, in a table that scrolls sideways
            // on a narrow phone.
            if (ctype === 'library-card') {
                var cell = function (tag, kids, cls, scope) {
                    var attrs = scope ? [{ name: 'scope', value: scope }] : null;
                    return kit.tag(tag, cls || '', tag === 'th' ? _sdT('Header Cell') : _sdT('Cell'), kids, attrs);
                };
                var _lcRow = function (icon, format, count, loan, renew) {
                    return kit.tag('tr', '', format, [
                        cell('th', [
                            kit.div('d-flex align-items-center gap-2', _sdT('Format'), [
                                kit.icon(icon, 'text-primary'),
                                kit.h('span', format)
                            ])
                        ], 'fw-semibold', 'row'),
                        cell('td', [ kit.h('span', count) ], 'pg-library-figure'),
                        cell('td', [ kit.h('span', loan) ], 'text-nowrap'),
                        cell('td', [ kit.h('span', renew) ], 'text-nowrap')
                    ]);
                };
                var table = kit.tag('table', 'table align-middle mb-0 pg-library-figure', _sdT('Loans Table'), [
                    kit.tag('caption', 'visually-hidden', _sdT('Table Caption'), [ kit.h('span', _sdT('What a library card lends, and for how long')) ]),
                    kit.tag('thead', '', _sdT('Table Head'), [
                        kit.tag('tr', '', _sdT('Head Row'), [
                            cell('th', [ kit.h('span', _sdT('Format')) ], '', 'col'),
                            cell('th', [ kit.h('span', _sdT('Items at once')) ], 'text-nowrap', 'col'),
                            cell('th', [ kit.h('span', _sdT('Loan')) ], '', 'col'),
                            cell('th', [ kit.h('span', _sdT('Renewals')) ], '', 'col')
                        ])
                    ]),
                    kit.tag('tbody', '', _sdT('Table Body'), [
                        _lcRow('bi-book', _sdT('Books'), '20', _sdT('3 weeks'), _sdT('3 times')),
                        _lcRow('bi-headphones', _sdT('Audiobooks and music'), '10', _sdT('3 weeks'), _sdT('3 times')),
                        _lcRow('bi-film', _sdT('Films'), '5', _sdT('1 week'), _sdT('Once')),
                        _lcRow('bi-newspaper', _sdT('Magazines'), '5', _sdT('1 week'), _sdT('Not renewed')),
                        _lcRow('bi-tablet', _sdT('eBooks and eAudiobooks'), '10', _sdT('3 weeks'), _sdT('Once'))
                    ])
                ]);
                return kit.tag('section', 'py-5 bg-body-tertiary overflow-hidden', _sdT('Library Card and Loans'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                        kit.row(_sdT('Row'), [
                            kit.col(_sdT('The Card'), [
                                fx(kit.div('pg-library-card d-flex flex-column justify-content-between gap-3 p-4 rounded-4 text-bg-primary shadow-lg', _sdT('Card'), [
                                    kit.div('d-flex align-items-start justify-content-between gap-3', _sdT('Card Top'), [
                                        kit.div('d-flex align-items-center gap-2', _sdT('Library Name'), [
                                            kit.icon('bi-book', 'fs-4'),
                                            kit.h('span', _sdT('City Library'), 'fw-bold')
                                        ]),
                                        kit.h('span', _sdT('Library Card'), 'small fw-semibold text-uppercase')
                                    ]),
                                    kit.h('span', _sdT('Ada Reader'), 'fs-4 fw-semibold'),
                                    kit.div('p-2 rounded-3 bg-body text-body-emphasis', _sdT('Barcode'), [
                                        kit.div('pg-library-barcode', _sdT('Barcode Lines'), []),
                                        kit.h('span', '7731 0458 2264', 'pg-library-figure d-block text-center small font-monospace mt-1')
                                    ])
                                ], hidden), { effect: 'slide-right', trigger: 'view', duration: 900, distance: 80 })
                            ], '5'),
                            kit.col(_sdT('Text'), [
                                fx(kit.div('', _sdT('Body'), [
                                    eyebrow(_sdT('Library card'), 'text-body-secondary'),
                                    kit.h('h2', _sdT('One free card opens the whole library'), 'display-6 fw-bold mb-3'),
                                    kit.p(_sdT('Join at any branch with proof of address, or start online and collect your card on your first visit. Children join with a parent or carer.'), 'lead text-body-secondary mb-4')
                                ]), { effect: 'fade', trigger: 'view', duration: 700 }),
                                kit.div('table-responsive rounded-4 border bg-body p-2 p-md-3 mb-4', _sdT('Table Wrap'), [ table ], kit.region(_sdT('What a library card lends'))),
                                kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-3 gap-lg-4 small mb-4', _sdT('Facts'), [
                                    tick('bi-arrow-repeat', _sdT('Renew online, by phone or at any branch')),
                                    tick('bi-tablet', _sdT('eBooks return by themselves')),
                                    tick('bi-emoji-smile', _sdT('No late fees for children'))
                                ]),
                                kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                                    kit.btn(_sdT('Join the Library'), 'primary', 'lg'),
                                    kit.btn(_sdT('Renew Your Loans'), 'secondary', 'lg')
                                ])
                            ], '7')
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
