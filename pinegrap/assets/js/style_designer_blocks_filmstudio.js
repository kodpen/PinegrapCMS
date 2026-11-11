/**
 * Pinegrap - Enterprise Website Platform
 *
 * Film Studio block family: the sections a film or video production
 * studio's site needs that the general palette lacks. The showreel hero
 * (the way in: the studio in a line over a cinema screen that plays the
 * reel, a slate with its running time and format, and the films the reel
 * is cut from, each at its timecode), the title card with end credits (one
 * film as a wide still with a play mark, its line and its facts, then the
 * crew rolling up as a film's credits do) and the cuts for every screen
 * (the versions a film is delivered in, each frame drawn at its own aspect
 * ratio with the lengths it is cut to). The services, industries, process,
 * studio spaces, crew, figures, clients and questions are general blocks
 * (Services, Features, Steps, Locations, Team, Stats, Logos, FAQ).
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
        // The cinema screen: the reel in a raised panel with viewfinder
        // corners in the theme's colour and a glow of it underneath; the
        // slate's figures line up.
        'pg-filmstudio-screen':   '.pg-filmstudio-screen { position: relative; padding: .75rem; border-radius: var(--bs-border-radius-xl); background-color: var(--bs-tertiary-bg); ' +
                                  'box-shadow: 0 2rem 5rem -1.5rem rgba(var(--bs-primary-rgb), .45); } ' +
                                  '.pg-filmstudio-screen::before, .pg-filmstudio-screen::after { content: ""; position: absolute; width: 2.5rem; height: 2.5rem; ' +
                                  'border: 3px solid var(--bs-primary); pointer-events: none; } ' +
                                  '.pg-filmstudio-screen::before { top: -.625rem; left: -.625rem; border-right: 0; border-bottom: 0; } ' +
                                  '.pg-filmstudio-screen::after { right: -.625rem; bottom: -.625rem; border-left: 0; border-top: 0; } ' +
                                  '.pg-filmstudio-screen .ratio { overflow: hidden; border-radius: var(--bs-border-radius-lg); background-color: var(--bs-body-bg); } ' +
                                  '.pg-filmstudio-screen time { font-family: var(--bs-font-monospace); font-variant-numeric: tabular-nums; }',
        // The films the reel is cut from, on a line like markers on an edit
        // timeline: a dot in the theme's colour at each one's timecode.
        'pg-filmstudio-chapters': '.pg-filmstudio-chapters li { position: relative; padding-top: 1rem; border-top: 1px solid var(--bs-border-color); } ' +
                                  '.pg-filmstudio-chapters li::before { content: ""; position: absolute; top: -.3125rem; left: 0; width: .625rem; height: .625rem; ' +
                                  'border-radius: 50%; background-color: var(--bs-primary); } ' +
                                  '.pg-filmstudio-chapters time { font-family: var(--bs-font-monospace); font-variant-numeric: tabular-nums; }',
        // A wide still at the cinema's 2.39:1 with the play mark in its
        // middle; the mark grows on hover and keyboard focus, and stays put
        // under reduced motion. Its halo replaces the button's focus ring, so
        // keyboard focus draws a ring of its own: the page's background
        // inside the primary colour, seen on a dark or a light still.
        'pg-filmstudio-scope':    '.pg-filmstudio-scope { position: relative; } ' +
                                  '.pg-filmstudio-scope .ratio { --bs-aspect-ratio: 41.84%; background-color: var(--bs-tertiary-bg); } ' +
                                  '.pg-filmstudio-scope .pg-filmstudio-play { position: absolute; top: 50%; left: 50%; z-index: 2; display: inline-flex; align-items: center; justify-content: center; ' +
                                  'width: 4.5rem; height: 4.5rem; padding: 0; font-size: 2rem; transform: translate(-50%, -50%); transition: transform .25s ease; ' +
                                  'box-shadow: 0 0 0 .5rem rgba(var(--bs-primary-rgb), .25); } ' +
                                  '.pg-filmstudio-scope .pg-filmstudio-play:hover, .pg-filmstudio-scope .pg-filmstudio-play:focus-visible { transform: translate(-50%, -50%) scale(1.12); } ' +
                                  '.pg-filmstudio-scope .pg-filmstudio-play:focus-visible { outline: 0; box-shadow: 0 0 0 .25rem var(--bs-body-bg), 0 0 0 .5rem var(--bs-primary); } ' +
                                  '@media (prefers-reduced-motion: reduce) { .pg-filmstudio-scope .pg-filmstudio-play { transition: none; } }',
        // End credits: each role set to the right of a centre line, each
        // name to its left, the way a film's credits roll.
        'pg-filmstudio-roll':     '.pg-filmstudio-roll > div { display: grid; grid-template-columns: 1fr 1fr; column-gap: 1.5rem; align-items: baseline; } ' +
                                  '.pg-filmstudio-roll > div + div { margin-top: .875rem; } ' +
                                  '.pg-filmstudio-roll dt { text-align: end; font-size: .75rem; font-weight: 400; letter-spacing: .12em; text-transform: uppercase; color: var(--bs-secondary-color); } ' +
                                  '.pg-filmstudio-roll dd { margin: 0; font-weight: 600; }',
        // A delivery frame drawn at its aspect ratio, every frame the same
        // height, so a wide cinema frame and a tall vertical one compare at
        // a glance.
        'pg-filmstudio-frame':    '.pg-filmstudio-frame { display: flex; align-items: flex-end; justify-content: center; height: 4.5rem; } ' +
                                  '.pg-filmstudio-frame > div { height: 100%; border: 2px solid var(--bs-primary); border-radius: .375rem; background-color: var(--bs-primary-bg-subtle); } ' +
                                  '.pg-filmstudio-frame .pg-filmstudio-r239 { aspect-ratio: 2.39 / 1; } .pg-filmstudio-frame .pg-filmstudio-r169 { aspect-ratio: 16 / 9; } ' +
                                  '.pg-filmstudio-frame .pg-filmstudio-r45 { aspect-ratio: 4 / 5; } .pg-filmstudio-frame .pg-filmstudio-r916 { aspect-ratio: 9 / 16; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'filmstudio-reel':    ['pg-filmstudio-screen', 'pg-filmstudio-chapters'],
        'filmstudio-credits': ['pg-filmstudio-scope', 'pg-filmstudio-roll'],
        'filmstudio-formats': ['pg-filmstudio-frame']
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

    // A dark band in every look and mode; the palette's accent stays.
    var dark = [{ name: 'data-bs-theme', value: 'dark' }];

    // ── Showreel hero ───────────────────────────────────────────────────
    // A film in the reel: where it starts (a duration from the reel's
    // first frame), its name and what kind of film it is.
    var chapter = function (at, seconds, name, kind) {
        return kit.tag('li', 'col', name, [
            kit.time('PT' + seconds + 'S', at, 'd-block small text-primary mb-1', _sdT('Time')),
            kit.h('h3', name, 'h6 fw-bold mb-1'),
            kit.p(kind, 'small text-body-secondary mb-0')
        ]);
    };
    var buildReel = function () {
        // On the dark band an outline button is too faint without a
        // palette, so the second way on is a solid secondary one.
        var words = fx(kit.div('text-center mb-5', _sdT('Hero Text'), [
            eyebrow(_sdT('Film and video production'), 'mb-3'),
            kit.h('h1', _sdT('Stories worth watching'), 'display-2 fw-bold text-uppercase mb-3'),
            kit.p(_sdT('Commercials, brand films and documentaries, from the first idea to the final cut'), 'lead text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap justify-content-center gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Start a Project'), '#', 'btn btn-primary btn-lg px-4', _sdT('Enquiry Link')),
                kit.link(_sdT('See Our Work'), '#', 'btn btn-secondary btn-lg px-4', _sdT('Projects Link'))
            ])
        ]), { effect: 'slide-up', trigger: 'load', duration: 800 });
        // The slate over the screen: what is playing, how long it runs and
        // the format; the player itself has no autoplay, the visitor starts it.
        var slate = kit.div('d-flex flex-wrap align-items-center gap-2 gap-md-4 px-1 pb-2 small text-body-secondary', _sdT('Facts'), [
            kit.div('d-inline-flex align-items-center gap-2 fw-semibold text-body', _sdT('Title'), [
                kit.icon('bi-record-circle', 'text-primary'),
                kit.h('span', _sdT('Showreel'))
            ]),
            kit.div('d-inline-flex align-items-center gap-1', _sdT('Running Time'), [
                kit.icon('bi-clock'),
                kit.time('PT2M30S', '02:30', '', _sdT('Running Time'))
            ]),
            kit.h('span', '4K · 16:9', 'ms-md-auto')
        ]);
        var screen = fx(kit.div('pg-filmstudio-screen', _sdT('Screen'), [
            slate,
            kit.div('ratio ratio-16x9', _sdT('Video Frame'), [
                kit.tag('video', 'w-100 h-100 object-fit-cover', _sdT('Video'), [
                    kit.p(_sdT('Your browser cannot play this video.'), 'p-3 mb-0')
                ], [
                    { name: 'controls', value: '' },
                    { name: 'preload', value: 'none' },
                    { name: 'playsinline', value: '' },
                    { name: 'poster', value: 'https://picsum.photos/seed/filmstudio-reel/1600/900' },
                    { name: 'title', value: _sdT('Our showreel') }
                ])
            ])
        ]), { effect: 'scale', trigger: 'load', duration: 1000, delay: 250 });
        var chapters = kit.div('mt-5', _sdT('Chapters'), [
            kit.h('h2', _sdT('In this reel'), 'h6 text-uppercase fw-semibold text-body-secondary mb-4'),
            fx(kit.tag('ol', 'pg-filmstudio-chapters list-unstyled row row-cols-2 row-cols-md-3 row-cols-lg-5 g-4 mb-0', _sdT('Films'), [
                chapter('00:00', 0, _sdT('Night Drive'), _sdT('Commercials')),
                chapter('00:24', 24, _sdT('Thread and Needle'), _sdT('Brand Films')),
                chapter('00:51', 51, _sdT('Slow Bloom'), _sdT('Music Videos')),
                chapter('01:18', 78, _sdT('The Harvest Table'), _sdT('Documentaries')),
                chapter('01:55', 115, _sdT('Build Day'), _sdT('Corporate and Event Films'))
            ]), { effect: 'slide-up', trigger: 'load', duration: 700, delay: 700, stagger: 100 })
        ]);
        return kit.tag('section', 'py-5 bg-body text-body overflow-hidden', _sdT('Film Studio (Showreel Hero)'), [
            kit.createNode('container', { fluid: false, cssClass: 'py-lg-5' }, [
                kit.row(_sdT('Hero Row'), [
                    kit.col(_sdT('Hero'), [words, screen, chapters], '10')
                ], 'justify-content-center')
            ])
        ], dark);
    };

    // ── Title card and end credits ──────────────────────────────────────
    // A credit: the role and the name, a pair on the roll.
    var credit = function (role, name) {
        return kit.div('', role, [
            kit.tag('dt', '', _sdT('Role'), [kit.h('span', role)]),
            kit.tag('dd', '', _sdT('Name'), [kit.h('span', name)])
        ]);
    };
    // A fact under the title, with its icon.
    var fact = function (icon, text) {
        return kit.tag('li', 'd-inline-flex align-items-center gap-1', text, [
            kit.icon(icon, 'text-primary'),
            kit.h('span', text)
        ]);
    };
    var buildCredits = function () {
        var head = kit.div('text-center mb-4', _sdT('Title Card'), [
            eyebrow(_sdT('Featured film')),
            kit.h('h2', _sdT('Night Drive'), 'display-4 fw-bold text-uppercase mb-3'),
            kit.p(_sdT('A launch spot for an electric car, shot in one night on an empty coast road with a camera car and a drone.'), 'lead text-body-secondary mb-3'),
            kit.tag('ul', 'list-unstyled d-flex flex-wrap justify-content-center gap-3 small text-body-secondary mb-0', _sdT('Facts'), [
                fact('bi-badge-tv', _sdT('Commercials')),
                fact('bi-car-front', _sdT('Automotive')),
                kit.tag('li', 'd-inline-flex align-items-center gap-1', _sdT('Running Time'), [
                    kit.icon('bi-clock', 'text-primary'),
                    kit.time('PT60S', _sdT('60 seconds'), '', _sdT('Running Time'))
                ])
            ])
        ]);
        var still = kit.div('pg-filmstudio-scope mb-5', _sdT('Still'), [
            kit.div('ratio rounded-4 overflow-hidden shadow-lg', _sdT('Picture'), [
                kit.img('filmstudio-night-drive', 1600, 670, _sdT('A car\'s headlights on an empty coast road at night'), 'object-fit-cover')
            ]),
            kit.iconLink('bi-play-fill', _sdT('Watch the film'), 'pg-filmstudio-play btn btn-primary rounded-circle', '#')
        ]);
        var roll = kit.div('', _sdT('Credits'), [
            kit.h('h3', _sdT('Credits'), 'h6 text-center text-uppercase fw-semibold text-body-secondary mb-4'),
            fx(kit.tag('dl', 'pg-filmstudio-roll mb-5', _sdT('Credits'), [
                credit(_sdT('Directed by'), _sdT('Ece Kaya')),
                credit(_sdT('Director of Photography'), _sdT('Tom Hughes')),
                credit(_sdT('Producer'), _sdT('Leyla Aksoy')),
                credit(_sdT('Editor'), _sdT('Can Yıldız')),
                credit(_sdT('Colourist'), _sdT('Mira Novak')),
                credit(_sdT('Sound Design'), _sdT('Baran Öz'))
            ]), { effect: 'slide-up', trigger: 'view', duration: 700, stagger: 110 })
        ]);
        var words = kit.tag('figure', 'text-center mb-0', _sdT('Quote'), [
            kit.tag('blockquote', 'blockquote mb-2', _sdT('Text'), [
                kit.p(_sdT('They lit a whole coast road for one night and gave us the best launch film we have had.'), 'fs-5 fst-italic mb-0')
            ]),
            kit.tag('figcaption', 'small text-body-secondary', _sdT('Client'), [kit.h('span', _sdT('Meridian Motors'))])
        ]);
        // The title and the credits keep to a reading width; the still runs
        // the container's full width, as a cinema screen does.
        return kit.section('py-5 overflow-hidden', _sdT('Film Studio (Title Card and Credits)'), [
            kit.row(_sdT('Row'), [
                kit.col(_sdT('Title Card'), [fx(head, { effect: 'fade', trigger: 'view', duration: 800 })], '8')
            ], 'justify-content-center'),
            fx(still, { effect: 'scale', trigger: 'view', duration: 900 }),
            kit.row(_sdT('Row'), [
                kit.col(_sdT('Credits'), [roll, fx(words, { effect: 'fade', trigger: 'view', duration: 800, delay: 300 })], '8')
            ], 'justify-content-center')
        ]);
    };

    // ── Cuts for every screen ───────────────────────────────────────────
    // A delivery format: its frame drawn at its aspect ratio (hidden from a
    // screen reader, which reads the ratio in words), the ratio, where it
    // is shown and the lengths it is cut to.
    var format = function (shape, ratio, use, lengths) {
        return kit.tag('li', 'col', ratio, [
            kit.div('h-100 p-3 rounded-4 border bg-body', _sdT('Card'), [
                kit.div('pg-filmstudio-frame mb-3', _sdT('Drawing'), [
                    kit.div(shape, _sdT('Frame'), [])
                ], [{ name: 'aria-hidden', value: 'true' }]),
                kit.h('h3', ratio, 'h4 fw-bold mb-1'),
                kit.p(use, 'small text-body-secondary mb-3'),
                kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-1 mb-0', _sdT('Lengths'), lengths.map(function (text) {
                    return kit.tag('li', 'badge rounded-pill text-bg-secondary fw-normal', text, [kit.h('span', text)]);
                }))
            ])
        ]);
    };
    var buildFormats = function () {
        var head = kit.div('row g-4 align-items-end mb-5', _sdT('Section Header'), [
            kit.div('col-lg-7', _sdT('Section Title'), [
                eyebrow(_sdT('Formats')),
                kit.h('h2', _sdT('A cut for every screen and length'), 'display-5 fw-bold mb-0')
            ]),
            kit.div('col-lg-5', _sdT('Lead'), [
                kit.p(_sdT('One shoot, delivered in every shape and length the film will be shown in. The frames are drawn to scale.'), 'lead text-body-secondary mb-0')
            ])
        ]);
        return kit.section('py-5 bg-body-tertiary', _sdT('Film Studio (Cuts for Every Screen)'), [
            fx(head, { effect: 'fade', trigger: 'view', duration: 800 }),
            fx(kit.tag('ul', 'list-unstyled row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 g-lg-4 mb-4', _sdT('Formats'), [
                format('pg-filmstudio-r239', '2.39:1', _sdT('Cinema, before the feature'), [_sdT('60 s'), _sdT('30 s')]),
                format('pg-filmstudio-r169', '16:9', _sdT('Television and the web'), [_sdT('60 s'), _sdT('30 s'), _sdT('15 s')]),
                format('pg-filmstudio-r45', '4:5', _sdT('Social media feeds'), [_sdT('30 s'), _sdT('15 s'), _sdT('6 s')]),
                format('pg-filmstudio-r916', '9:16', _sdT('Vertical video on phones'), [_sdT('15 s'), _sdT('6 s')])
            ]), { effect: 'scale', trigger: 'view', duration: 700, stagger: 120 }),
            fx(kit.div('d-flex flex-column flex-md-row align-items-md-center gap-3', _sdT('Note'), [
                kit.p(_sdT('Subtitles, captions and versions in other languages are cut from the same master.'), 'flex-grow-1 text-body-secondary mb-0'),
                kit.link(_sdT('Start a Project'), '#', 'btn btn-primary px-4 flex-shrink-0', _sdT('Enquiry Link'))
            ]), { effect: 'fade', trigger: 'view', duration: 800, delay: 200 })
        ]);
    };

    return {
        prefix: 'filmstudio-',
        group: { title: _sdT('Film Studio'), icon: 'bi-film' },
        items: [
            { label: _sdT('Film Studio: Showreel Hero'),          type: 'component', icon: 'bi-play-btn',     extra: { componentType: 'filmstudio-reel' } },
            { label: _sdT('Film Studio: Title Card and Credits'), type: 'component', icon: 'bi-camera-reels', extra: { componentType: 'filmstudio-credits' } },
            { label: _sdT('Film Studio: Cuts for Every Screen'),  type: 'component', icon: 'bi-aspect-ratio', extra: { componentType: 'filmstudio-formats' } }
        ],
        build: function (ctype) {
            if (ctype === 'filmstudio-reel') return buildReel();
            if (ctype === 'filmstudio-credits') return buildCredits();
            if (ctype === 'filmstudio-formats') return buildFormats();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
