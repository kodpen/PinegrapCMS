/**
 * Pinegrap - Enterprise Website Platform
 *
 * Music block family: the sections a band's or an artist's site needs that
 * the general palette lacks. The release hero (the newest record as a
 * sleeve with its disc sliding out, what it is, where to listen and its
 * numbered track listing with each song's length), the tour dates (one line
 * per show: the day, the city and venue, the ticket state as an icon and a
 * word, and the ticket link or the waiting list) and the press kit (the
 * one-sheet facts for press and promoters beside the press photos and the
 * files to download). The members, the videos, the merch, the mailing list
 * and the questions are general blocks (Team, Image, Services, Newsletter,
 * FAQ).
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
        // A record sleeve: a square cover with the disc behind it, half
        // out to the right from md up.
        'pg-music-sleeve':  '.pg-music-sleeve { position: relative; max-width: 30rem; margin-right: auto; } ' +
                            '.pg-music-sleeve > .pg-music-cover { position: relative; z-index: 1; aspect-ratio: 1 / 1; overflow: hidden; background: var(--bs-tertiary-bg); } ' +
                            '.pg-music-sleeve > .pg-music-cover > img { width: 100%; height: 100%; object-fit: cover; } ' +
                            '.pg-music-sleeve > .pg-music-disc { display: none; } ' +
                            '@media (min-width: 768px) { .pg-music-sleeve { margin-right: 18%; } ' +
                            '.pg-music-sleeve > .pg-music-disc { display: block; position: absolute; top: 4%; bottom: 4%; left: 24%; aspect-ratio: 1 / 1; border-radius: 50%; ' +
                            'background: radial-gradient(circle, var(--bs-primary) 0 15%, var(--bs-body-bg) 15% 17%, var(--bs-emphasis-color) 17% 100%); ' +
                            'box-shadow: inset 0 0 0 .5rem var(--bs-emphasis-color), inset 0 0 0 .5625rem var(--bs-secondary-color); } }',
        // The track listing: number, title and length on a hairline, in
        // two columns from md up; the figures line up.
        'pg-music-tracks':  '.pg-music-tracks { margin: 0; padding: 0; list-style: none; } ' +
                            '.pg-music-tracks > li { display: flex; gap: .75rem; align-items: baseline; padding: .5rem 0; border-bottom: 1px solid var(--bs-border-color); break-inside: avoid; } ' +
                            '.pg-music-tracks > li > :first-child { min-width: 1.5rem; color: var(--bs-secondary-color); font-variant-numeric: tabular-nums; } ' +
                            '.pg-music-tracks > li > :last-child { margin-left: auto; color: var(--bs-secondary-color); font-variant-numeric: tabular-nums; } ' +
                            '@media (min-width: 768px) { .pg-music-tracks { column-count: 2; column-gap: 2rem; } }',
        // A show's day: the weekday large above when it is, a fixed width
        // so the cities line up.
        'pg-music-day':     '.pg-music-day { flex-shrink: 0; min-width: 8rem; line-height: 1.1; color: var(--bs-emphasis-color); } ' +
                            '.pg-music-day > :first-child { font-size: 1.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; }',
        // The press kit's one-sheet: label and value side by side on a
        // hairline.
        'pg-music-sheet':   '.pg-music-sheet { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); margin: 0; border-top: 1px solid var(--bs-border-color); } ' +
                            '.pg-music-sheet > div { display: contents; } ' +
                            '.pg-music-sheet dt, .pg-music-sheet dd { margin: 0; padding: .5rem 0; border-bottom: 1px solid var(--bs-border-color); } ' +
                            '.pg-music-sheet dt { font-size: .875rem; color: var(--bs-secondary-color); font-weight: 400; } ' +
                            '.pg-music-sheet dd { font-weight: 600; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'music-release':  ['pg-music-sleeve', 'pg-music-tracks'],
        'music-tour':     ['pg-music-day'],
        'music-presskit': ['pg-music-sheet']
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

    // ── Release hero ────────────────────────────────────────────────────
    // A track: its number, its title and its length (minutes:seconds,
    // with the duration machine-readable).
    var track = function (no, title, length) {
        var parts = length.split(':');
        return kit.tag('li', '', title, [
            kit.h('span', no),
            kit.h('span', title),
            kit.time('PT' + parts[0] + 'M' + parts[1] + 'S', length, '', _sdT('Length'))
        ]);
    };
    var buildRelease = function () {
        var sleeve = kit.col(_sdT('Sleeve'), [
            kit.div('pg-music-sleeve', _sdT('Record Sleeve'), [
                fx(kit.div('pg-music-disc', _sdT('Disc'), [], [{ name: 'aria-hidden', value: 'true' }]),
                    { effect: 'slide-left', trigger: 'load', duration: 1200, delay: 300, distance: 120 }),
                kit.div('pg-music-cover rounded-2 shadow-lg', _sdT('Cover'), [
                    kit.img('music-release', 1000, 1000, _sdT('The album cover: a night bus with its windows lit, crossing a bridge over dark water'), '')
                ])
            ])
        ], '6');
        var words = kit.col(_sdT('Release'), [
            eyebrow(_sdT('Out Now'), 'mb-3'),
            kit.h('h1', _sdT('Night Bus Hymns'), 'display-3 fw-bold mb-2'),
            kit.p(_sdT('The second album') + ' · ' + _sdT('Eleven songs'), 'text-body-secondary fw-semibold mb-3'),
            kit.p(_sdT('Eleven songs written on the road: loud where the city is loud, quiet where it sleeps.'), 'lead mb-4'),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Listen Now'), '#', 'btn btn-primary btn-lg px-4', _sdT('Listen Link')),
                kit.link(_sdT('Buy on Vinyl'), '#', 'btn btn-outline-secondary btn-lg px-4', _sdT('Vinyl Link'))
            ])
        ], '6');
        var tracks = kit.div('mt-5', _sdT('Track Listing'), [
            kit.h('h2', _sdT('Track Listing'), 'h5 fw-bold text-uppercase mb-3'),
            kit.tag('ol', 'pg-music-tracks', _sdT('Tracks List'), [
                track('1', _sdT('Last Ferry'), '3:41'),
                track('2', _sdT('Neon on the Water'), '4:05'),
                track('3', _sdT('Night Bus Hymn'), '3:58'),
                track('4', _sdT('Paper Lanterns'), '3:12'),
                track('5', _sdT('Bakery Lights'), '4:27'),
                track('6', _sdT('Rooftop Static'), '3:33'),
                track('7', _sdT('Slow Traffic'), '5:02'),
                track('8', _sdT('Harbour Choir'), '3:49'),
                track('9', _sdT('Streetlight Waltz'), '3:20'),
                track('10', _sdT('Long Way Home'), '4:44'),
                track('11', _sdT('Morning Shift'), '6:10')
            ]),
            kit.p(_sdT('Recorded live in one room over ten days. Produced by the band, mixed by Selin Arda.'), 'small text-body-secondary mt-3 mb-0')
        ]);
        return kit.section('py-5 overflow-hidden', _sdT('Music (Release Hero)'), [
            kit.row(_sdT('Release Row'), [
                fx(sleeve, { effect: 'fade', trigger: 'load', duration: 900 }),
                fx(words, { effect: 'slide-up', trigger: 'load', duration: 700, delay: 150 })
            ], 'g-4 g-lg-5 align-items-center'),
            fx(tracks, { effect: 'fade', trigger: 'view', duration: 800 })
        ]);
    };

    // ── Tour dates ──────────────────────────────────────────────────────
    // The ticket states: a tinted pill with an icon and a word.
    var ticketState = function (state) {
        if (state === 'few_left') return { icon: 'bi-hourglass-split', text: _sdT('Few tickets left'), cls: 'bg-warning-subtle text-warning-emphasis' };
        if (state === 'sold_out') return { icon: 'bi-x-circle', text: _sdT('Sold out'), cls: 'bg-secondary-subtle text-secondary-emphasis' };
        return { icon: 'bi-ticket-perforated', text: _sdT('Tickets on sale'), cls: 'bg-primary-subtle text-primary-emphasis' };
    };
    // A show: its day, the city and the venue, its ticket state and the
    // ticket link, or the waiting list once it is sold out.
    var show = function (day, when, city, venue, state) {
        var s = ticketState(state);
        return kit.tag('li', 'd-flex flex-column flex-md-row align-items-md-center gap-2 gap-md-4 py-4 border-bottom', city, [
            kit.div('pg-music-day', _sdT('Day'), [
                kit.h('span', day, 'd-block'),
                kit.h('span', when, 'd-block small text-body-secondary')
            ]),
            kit.div('flex-grow-1', _sdT('Place'), [
                kit.h('h3', city, 'h4 fw-bold mb-0'),
                kit.p(venue, 'text-body-secondary mb-0')
            ]),
            kit.div('badge rounded-pill d-inline-flex align-items-center gap-1 align-self-start align-self-md-center ' + s.cls, _sdT('Ticket State'), [
                kit.icon(s.icon),
                kit.h('span', s.text)
            ]),
            state === 'sold_out'
                ? kit.link(_sdT('Waiting List'), '#', 'btn btn-outline-secondary px-4 align-self-start align-self-md-center', _sdT('Waiting List Link'))
                : kit.link(_sdT('Tickets'), '#', 'btn btn-primary px-4 align-self-start align-self-md-center', _sdT('Tickets Link'))
        ]);
    };
    var buildTour = function () {
        var head = kit.div('row g-4 align-items-end mb-4', _sdT('Tour Header'), [
            kit.div('col-lg-8', _sdT('Tour Title'), [
                eyebrow(_sdT('On Tour')),
                kit.h('h2', _sdT('Tour Dates'), 'display-5 fw-bold mb-2'),
                kit.p(_sdT('The Night Bus Hymns tour, from the home harbour to the clubs of the north.'), 'text-body-secondary mb-0')
            ]),
            kit.div('col-lg-4 text-lg-end', _sdT('Tour Links'), [
                kit.link(_sdT('Book us for your city'), '#', 'link-primary fw-semibold', _sdT('Booking Link'))
            ])
        ]);
        return kit.section('py-5', _sdT('Music (Tour Dates)'), [
            fx(head, { effect: 'fade', trigger: 'view', duration: 800 }),
            fx(kit.tag('ol', 'list-unstyled border-top mb-0', _sdT('Shows'), [
                show(_sdT('Fri'), _sdT('This week'), _sdT('Istanbul'), _sdT('Harbour Hall'), 'few_left'),
                show(_sdT('Sat'), _sdT('This week'), _sdT('Ankara'), _sdT('The Old Mill'), 'sold_out'),
                show(_sdT('Sun'), _sdT('This week'), _sdT('Izmir'), _sdT('Seafront Stage'), 'on_sale'),
                show(_sdT('Thu'), _sdT('In three weeks'), _sdT('Berlin'), _sdT('Canal Club'), 'on_sale'),
                show(_sdT('Sat'), _sdT('In three weeks'), _sdT('London'), _sdT('Riverside Ballroom'), 'on_sale')
            ]), { effect: 'slide-up', trigger: 'view', stagger: 100 })
        ]);
    };

    // ── Press kit ───────────────────────────────────────────────────────
    // A line of the one-sheet: its label and its value.
    var fact = function (label, value) {
        return kit.div('', label, [
            kit.h('dt', label),
            kit.h('dd', value)
        ]);
    };
    // A link that saves its file instead of opening it.
    var download = function (text, cls) {
        return kit.createNode('semantic', { tag: 'a', cssClass: cls, href: '#', target: '', text: text, customName: _sdT('Download Link'),
            _attrs: [{ name: 'download', value: '' }] });
    };
    // A press photo with its download link.
    var photo = function (seed, alt, caption) {
        return kit.tag('figure', 'col mb-0', caption, [
            kit.div('ratio ratio-4x3 rounded-3 overflow-hidden bg-body-tertiary mb-2', _sdT('Picture'), [
                kit.img(seed, 800, 600, alt, 'object-fit-cover')
            ]),
            kit.tag('figcaption', 'd-flex justify-content-between align-items-center gap-2 small', _sdT('Photo Caption'), [
                kit.h('span', caption, 'text-body-secondary'),
                download(_sdT('Download'), 'link-primary fw-semibold')
            ])
        ]);
    };
    // A file to download: its icon, its name, its kind and the link.
    var file = function (icon, name, kind) {
        return kit.tag('li', 'd-flex align-items-center gap-3 py-3 border-bottom', name, [
            kit.icon(icon, 'fs-4 text-primary'),
            kit.div('flex-grow-1', _sdT('File'), [
                kit.h('h4', name, 'h6 fw-semibold mb-0'),
                kit.p(kind, 'small text-body-secondary mb-0')
            ]),
            download(_sdT('Download'), 'btn btn-sm btn-outline-primary')
        ]);
    };
    var buildPresskit = function () {
        var sheet = kit.col(_sdT('One-sheet'), [
            eyebrow(_sdT('For press and promoters')),
            kit.h('h2', _sdT('Press Kit'), 'display-6 fw-bold mb-3'),
            kit.p(_sdT('A four-piece from the harbour who write loud, warm songs about night buses, late shifts and the long way home.'), 'mb-4'),
            kit.tag('dl', 'pg-music-sheet mb-4', _sdT('Facts'), [
                fact(_sdT('Genre'), _sdT('Indie rock')),
                fact(_sdT('From'), _sdT('Istanbul')),
                fact(_sdT('Line-up'), _sdT('Vocals, guitar, bass and drums')),
                fact(_sdT('Record Label'), _sdT('Released on our own label, distributed worldwide')),
                fact(_sdT('Latest Release'), _sdT('Night Bus Hymns'))
            ]),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Book the Band'), '#', 'btn btn-primary px-4', _sdT('Booking Link')),
                kit.link(_sdT('Write to the Press Office'), 'mailto:press@example.com', 'btn btn-outline-secondary px-4', _sdT('Press Link'))
            ])
        ], '5');
        var media = kit.col(_sdT('Press Material'), [
            kit.h('h3', _sdT('Press Photos'), 'h5 fw-bold mb-3'),
            fx(kit.div('row row-cols-1 row-cols-sm-3 g-3 mb-5', _sdT('Photos'), [
                photo('music-press-live', _sdT('The band on stage under blue lights, the singer leaning into the crowd'), _sdT('Live')),
                photo('music-press-portrait', _sdT('The four members standing on a quay at night'), _sdT('Portrait')),
                photo('music-press-studio', _sdT('The band recording together in one room, amplifiers facing each other'), _sdT('Studio'))
            ]), { effect: 'slide-up', trigger: 'view', stagger: 120 }),
            kit.h('h3', _sdT('Files'), 'h5 fw-bold mb-2'),
            fx(kit.tag('ul', 'list-unstyled border-top mb-0', _sdT('Files List'), [
                file('bi-file-earmark-text', _sdT('One-sheet and biography'), _sdT('PDF, two pages')),
                file('bi-file-earmark-zip', _sdT('Logos and album covers'), _sdT('ZIP, print and screen sizes')),
                file('bi-file-earmark-ruled', _sdT('Stage plot and technical rider'), _sdT('PDF, for the venue\'s crew'))
            ]), { effect: 'fade', trigger: 'view', duration: 800, delay: 200 })
        ], '7');
        return kit.section('py-5', _sdT('Music (Press Kit)'), [
            kit.row(_sdT('Press Kit Row'), [
                fx(sheet, { effect: 'fade', trigger: 'view', duration: 800 }),
                media
            ], 'g-4 g-lg-5')
        ]);
    };

    return {
        prefix: 'music-',
        group: { title: _sdT('Music'), icon: 'bi-music-note-beamed' },
        items: [
            { label: _sdT('Music: Release Hero'), type: 'component', icon: 'bi-vinyl',              extra: { componentType: 'music-release' },
              desc: _sdT('An album cover beside the title, buttons to listen and to buy the vinyl, and the track listing with the length of each song.') },
            { label: _sdT('Music: Tour Dates'),   type: 'component', icon: 'bi-ticket-perforated',  extra: { componentType: 'music-tour' },
              desc: _sdT('Tour dates as rows with the day, city and venue, the ticket state said with an icon and a word, and a ticket or waiting list link.') },
            { label: _sdT('Music: Press Kit'),    type: 'component', icon: 'bi-newspaper',          extra: { componentType: 'music-presskit' },
              desc: _sdT('A press kit with the band in facts, buttons to book the band or write to the press office, press photos to download and a list of files.') }
        ],
        build: function (ctype) {
            if (ctype === 'music-release') return buildRelease();
            if (ctype === 'music-tour') return buildTour();
            if (ctype === 'music-presskit') return buildPresskit();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
