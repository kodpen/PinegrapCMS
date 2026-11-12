/**
 * Pinegrap - Enterprise Website Platform
 *
 * Photo block family: the sections a photography studio's site needs that
 * the general palette lacks. The filmstrip hero (the studio in a sentence
 * over a strip of numbered frames, one per kind of work), the proof
 * contact sheet (numbered proofs, the client's picks marked, and how a
 * selection is sent) and behind the shot (one photograph with the story
 * of how it was made and its camera settings). The packages, the add-ons,
 * the steps of a shoot, the preparing tips and the galleries are general
 * blocks (Pricing: Tiers, Services: Price List, Steps: Numbered, Features:
 * Grid, Gallery: Polaroid, Image: Masonry).
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
        // A strip of film: sprocket holes along both edges, the frames in a
        // row that scrolls sideways on a narrow screen.
        'pg-photo-strip': '.pg-photo-strip { position: relative; padding: 1.75rem .75rem; background: var(--bs-emphasis-color); color: var(--bs-body-bg); } ' +
                          '.pg-photo-strip::before, .pg-photo-strip::after { content: ""; position: absolute; left: 0; right: 0; height: .5rem; background: repeating-linear-gradient(90deg, var(--bs-body-bg) 0 .75rem, transparent .75rem 1.5rem); opacity: .6; } ' +
                          '.pg-photo-strip::before { top: .625rem; } .pg-photo-strip::after { bottom: .625rem; } ' +
                          '.pg-photo-strip > ol > li { flex: 0 0 15rem; } ' +
                          '.pg-photo-strip img { width: 100%; aspect-ratio: 3 / 2; object-fit: cover; background: var(--bs-secondary-bg); }',
        // Proofs on a contact sheet; a picked frame is ringed in the theme
        // colour.
        'pg-photo-sheet': '.pg-photo-sheet { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; padding: 1rem; background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); } ' +
                          '.pg-photo-sheet > li { position: relative; } ' +
                          '.pg-photo-sheet img { width: 100%; aspect-ratio: 3 / 2; object-fit: cover; background: var(--bs-secondary-bg); } ' +
                          '.pg-photo-sheet .pg-photo-picked { outline: 3px solid var(--bs-primary); outline-offset: 2px; }',
        // A photograph's settings: figures in even columns, values in the
        // monospace face like a camera's readout.
        'pg-photo-exif':  '.pg-photo-exif { font-variant-numeric: tabular-nums; border-top: 1px solid var(--bs-border-color); } ' +
                          '.pg-photo-exif dd { font-family: var(--bs-font-monospace); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'photo-hero':     ['pg-photo-strip'],
        'photo-proofing': ['pg-photo-sheet'],
        'photo-shot':     ['pg-photo-exif']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };

    // ── Filmstrip hero ──────────────────────────────────────────────────
    // A frame of the strip: its picture, its number and the kind of work.
    var frame = function (n, seed, alt, kind) {
        return kit.tag('li', '', _sdT('Frame {var}', n), [
            kit.tag('figure', 'mb-0', _sdT('Frame'), [
                kit.img(seed, 600, 400, alt, 'rounded-1'),
                kit.tag('figcaption', 'd-flex justify-content-between gap-2 small mt-2', _sdT('Caption'), [
                    kit.h('span', ('0' + n).slice(-2), 'font-monospace'),
                    kit.h('span', kind, 'fw-semibold text-uppercase')
                ])
            ])
        ]);
    };
    var buildHero = function () {
        var text = kit.div('text-center col-lg-8 mx-auto mb-5', _sdT('Hero Text'), [
            kit.p(_sdT('Photography studio'), 'small text-uppercase fw-semibold text-primary mb-3'),
            kit.h('h1', _sdT('Photos that feel like you'), 'display-3 mb-3'),
            kit.p(_sdT('Weddings, portraits, families and brands, photographed in natural light and delivered in a private gallery.'), 'lead text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap justify-content-center gap-2', _sdT('Buttons'), [
                kit.link(_sdT('See the Galleries'), '#', 'btn btn-primary btn-lg rounded-pill px-4', _sdT('Galleries Link')),
                kit.link(_sdT('Book a Shoot'), '#', 'btn btn-outline-primary btn-lg rounded-pill px-4', _sdT('Booking Link'))
            ])
        ]);
        var strip = kit.div('pg-photo-strip rounded-3', _sdT('Filmstrip'), [
            kit.tag('ol', 'list-unstyled d-flex gap-3 overflow-x-auto mb-0', _sdT('Frames'), [
                frame(1, 'photo-strip-wedding', _sdT('A couple laughing in soft evening light'), _sdT('Weddings')),
                frame(2, 'photo-strip-portrait', _sdT('A portrait by a north window'), _sdT('Portraits')),
                frame(3, 'photo-strip-family', _sdT('A family walking on a beach'), _sdT('Families')),
                frame(4, 'photo-strip-newborn', _sdT('A sleeping newborn in a knitted blanket'), _sdT('Newborns')),
                frame(5, 'photo-strip-brand', _sdT('A potter at work for a brand story'), _sdT('Brands'))
            ], kit.region(_sdT('Our work in five frames')))
        ]);
        return kit.section('py-5 overflow-hidden', _sdT('Photo (Filmstrip Hero)'), [
            fx(text, { effect: 'fade', trigger: 'load', duration: 800 }),
            fx(strip, { effect: 'slide-left', trigger: 'load', duration: 900, delay: 200 })
        ]);
    };

    // ── Proof contact sheet ─────────────────────────────────────────────
    // A proof on the sheet: its frame number and, when the client chose
    // it, the ring and a heart with its words for a screen reader.
    var proof = function (n, picked) {
        var number = ('0' + n).slice(-2);
        var kids = [
            kit.img('photo-proof-' + n, 360, 240, _sdT('Proof frame {var}', number), picked ? 'pg-photo-picked rounded-1' : 'rounded-1'),
            kit.h('span', number, 'position-absolute top-0 start-0 m-1 badge bg-body text-body font-monospace')
        ];
        if (picked) {
            kids.push(kit.div('position-absolute top-0 end-0 m-1 badge bg-primary', _sdT('Picked'), [
                kit.icon('bi-heart-fill'),
                kit.h('span', _sdT('Selected'), 'visually-hidden')
            ]));
        }
        return kit.tag('li', '', _sdT('Frame {var}', number), kids);
    };
    var how = function (n, title, text) {
        return kit.tag('li', 'd-flex gap-3 mb-3', _sdT('Step {var}', n), [
            kit.h('span', String(n), 'd-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-primary-subtle text-primary-emphasis fw-semibold p-2 lh-1'),
            kit.div('', _sdT('Step Text'), [
                kit.h('h3', title, 'h6 mb-1'),
                kit.p(text, 'small text-body-secondary mb-0')
            ])
        ]);
    };
    var buildProofing = function () {
        var picks = [4, 7, 11];
        var sheet = [];
        for (var n = 1; n <= 12; n++) sheet.push(proof(n, picks.indexOf(n) !== -1));
        return kit.section('py-5 bg-body-tertiary', _sdT('Photo (Proof Contact Sheet)'), [
            kit.row(_sdT('Proofing Row'), [
                kit.col(_sdT('Proofing Text'), [
                    fx(kit.div('', _sdT('Proofing Body'), [
                        kit.p(_sdT('After the shoot'), 'small text-uppercase fw-semibold text-primary mb-2'),
                        kit.h('h2', _sdT('Choosing your favourites'), 'display-6 mb-3'),
                        kit.p(_sdT('Within two weeks your proofs wait in a private gallery that only you can open after signing in. Each photo carries a frame number.'), 'text-body-secondary mb-4'),
                        kit.tag('ol', 'list-unstyled mb-4', _sdT('Steps'), [
                            how(1, _sdT('Look through your proofs'), _sdT('Take your time; the gallery stays open until you send your selection.')),
                            how(2, _sdT('Note the frame numbers'), _sdT('Write down the numbers of the photos you love and anything to retouch.')),
                            how(3, _sdT('Send your selection'), _sdT('We edit your favourites by hand and tell you when they are ready.'))
                        ]),
                        kit.p(_sdT('Your selection: {var}', '04, 07, 11'), 'small fw-semibold font-monospace mb-3'),
                        kit.link(_sdT('Open the Client Area'), '#', 'btn btn-outline-primary rounded-pill px-4', _sdT('Client Area Link'))
                    ]), { effect: 'slide-up', trigger: 'view', duration: 700 })
                ], '5'),
                kit.col(_sdT('Contact Sheet'), [
                    fx(kit.tag('ol', 'pg-photo-sheet list-unstyled rounded-3 mb-0', _sdT('Proofs'), sheet),
                        { effect: 'fade', trigger: 'view', duration: 600, stagger: 60 })
                ], '7')
            ], 'g-lg-5 align-items-center')
        ]);
    };

    // ── Behind the shot ─────────────────────────────────────────────────
    // A camera setting: what it is and its value.
    var setting = function (term, value) {
        return kit.div('col', term, [
            kit.tag('dt', 'small fw-normal text-body-secondary', _sdT('Term'), [kit.h('span', term)]),
            kit.tag('dd', 'fw-semibold mb-0', _sdT('Value'), [kit.h('span', value)])
        ]);
    };
    var buildShot = function () {
        return kit.section('py-5', _sdT('Photo (Behind the Shot)'), [
            kit.row(_sdT('Shot Row'), [
                kit.col(_sdT('Shot Picture'), [
                    fx(kit.tag('figure', 'mb-0', _sdT('Photograph'), [
                        kit.img('photo-shot', 1200, 1500, _sdT('A bride turning towards the window, lit from the side'), 'img-fluid rounded-4 w-100'),
                        kit.tag('figcaption', 'small text-body-secondary mt-2', _sdT('Caption'), [kit.h('span', _sdT('Photographed by Deniz, in the studio'))])
                    ]), { effect: 'fade', trigger: 'view', duration: 900 })
                ], '7'),
                kit.col(_sdT('Shot Text'), [
                    fx(kit.div('', _sdT('Shot Body'), [
                        kit.p(_sdT('Behind the shot'), 'small text-uppercase fw-semibold text-primary mb-2'),
                        kit.h('h2', _sdT('One window, one minute of light'), 'display-6 mb-3'),
                        kit.p(_sdT('Late in the afternoon the sun reaches the north window at an angle for a few minutes. We waited for it, asked her to turn slowly, and took this on the third turn.'), 'text-body-secondary mb-4'),
                        kit.h('h3', _sdT('Camera settings'), 'h6 text-uppercase fw-semibold mb-2'),
                        kit.tag('dl', 'pg-photo-exif row row-cols-2 row-cols-sm-3 g-3 pt-3 mb-4', _sdT('Settings'), [
                            setting(_sdT('Lens'), '85 mm'),
                            setting(_sdT('Aperture'), 'f/1.8'),
                            setting(_sdT('Shutter'), '1/250 s'),
                            setting(_sdT('ISO'), '400'),
                            setting(_sdT('Light'), _sdT('Window light')),
                            setting(_sdT('Edit'), _sdT('By hand'))
                        ]),
                        kit.link(_sdT('See the gallery'), '#', 'btn btn-outline-primary rounded-pill px-4', _sdT('Gallery Link'))
                    ]), { effect: 'slide-up', trigger: 'view', duration: 700, delay: 150 })
                ], '5')
            ], 'g-lg-5 align-items-center')
        ]);
    };

    return {
        prefix: 'photo-',
        group: { title: _sdT('Photo'), icon: 'bi-camera' },
        items: [
            { label: _sdT('Photo: Filmstrip Hero'),       type: 'component', icon: 'bi-film',          extra: { componentType: 'photo-hero' },
              desc: _sdT('A centred headline, a short text and two buttons over a filmstrip of numbered frames, each captioned with its kind of work.') },
            { label: _sdT('Photo: Proof Contact Sheet'),  type: 'component', icon: 'bi-grid-3x3',      extra: { componentType: 'photo-proofing' },
              desc: _sdT('A contact sheet of proofs with the chosen frames ringed and marked, beside the steps for picking favourites and a client area button.') },
            { label: _sdT('Photo: Behind the Shot'),      type: 'component', icon: 'bi-camera',        extra: { componentType: 'photo-shot' },
              desc: _sdT('A tall photograph beside the story of how it was lit, its camera settings as figures and a link to the gallery.') }
        ],
        build: function (ctype) {
            if (ctype === 'photo-hero')     return buildHero();
            if (ctype === 'photo-proofing') return buildProofing();
            if (ctype === 'photo-shot')     return buildShot();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
