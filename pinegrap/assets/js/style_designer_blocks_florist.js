/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual designer's Florist blocks (prefix florist-): the sections of a
 * florist's site the palette lacks. A hero whose bouquet stands in an
 * arched frame with the gift card tucked into it, the last order time for
 * delivery today under the words and the occasions as a row of ways in;
 * the card message band, the card drawn large with a sample message beside
 * what the card is and sample words for four occasions; and the flower
 * subscription, three plans whose twelve weeks are drawn as a strip with a
 * flower on each week a bouquet comes. Where and when the flowers are
 * delivered is a general block (Locations: Delivery Zones); bouquets,
 * sizes, seasons and the wedding work are placed with general blocks.
 * Motion comes from the nodes' interactions (props._fx, played by
 * pg_fx.src.js, which honours reduced motion).
 *
 * Registered with StyleDesigner.registerBlockFamily() and bundled after the
 * designer core (pg_designer_js_sources()); see
 * docs/memory/designer/designer-blocks-florist.md.
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
        // The hero's picture and the card tucked into it: the card overlaps
        // the frame's lower edge on a phone (it follows the picture there)
        // and sits over its lower left corner from md up.
        'pg-florist-frame':  '.pg-florist-frame { position: relative; max-width: 28rem; margin-inline: auto; } ' +
                             '.pg-florist-frame .pg-florist-tuck { position: relative; z-index: 1; max-width: 17rem; margin: -3rem auto 0; } ' +
                             '@media (min-width: 768px) { .pg-florist-frame .pg-florist-tuck { position: absolute; left: -2rem; bottom: 2rem; width: 16rem; margin: 0; } }',
        // The bouquet in an arched frame, like a shop window: a half circle
        // over small lower corners. The radii are a share of each side, so
        // the arch stays round at the frame's 4:5 shape; a picture of
        // another shape is cropped to it.
        'pg-florist-arch':   '.pg-florist-arch { aspect-ratio: 4 / 5; overflow: hidden; border-radius: 50% 50% 1.5rem 1.5rem / 40% 40% 1.5rem 1.5rem; } ' +
                             '.pg-florist-arch img { width: 100%; height: 100%; object-fit: cover; }',
        // The gift card: paper with a band of the theme colour on top, set
        // at a slight slant from md up. The slant is the card's own; an
        // entrance sits on the node around it, so the two never meet.
        'pg-florist-note':   '.pg-florist-note { padding: 1.75rem 1.5rem 1.5rem; border-top: .375rem solid var(--bs-primary); border-radius: var(--bs-border-radius-lg); ' +
                             'background-color: var(--bs-body-bg); color: var(--bs-body-color); box-shadow: var(--bs-box-shadow-lg); } ' +
                             '@media (min-width: 768px) { .pg-florist-note { transform: rotate(-3deg); } }',
        // A plan's twelve weeks: a square per week, a flower on each week a
        // bouquet comes. The squares share the card's width, so the strip
        // never runs past a phone screen.
        'pg-florist-weeks':  '.pg-florist-weeks { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: .25rem; } ' +
                             '.pg-florist-weeks .pg-florist-week { display: flex; align-items: center; justify-content: center; aspect-ratio: 1 / 1; ' +
                             'border-radius: .375rem; font-size: .75rem; line-height: 1; }',
        // Prices and times that line up.
        'pg-florist-figure': '.pg-florist-figure { font-variant-numeric: tabular-nums; line-height: 1; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'florist-hero':         ['pg-florist-frame', 'pg-florist-arch', 'pg-florist-note', 'pg-florist-figure'],
        'florist-card':         ['pg-florist-note'],
        'florist-subscription': ['pg-florist-weeks', 'pg-florist-figure']
    };

    // A node with an interaction (props._fx, designer-interactions.md).
    var fx = function (node, effect) {
        node.props._fx = effect;
        return node;
    };
    // The week strip is decoration: the words under it say it.
    var hidden = [{ name: 'aria-hidden', value: 'true' }];
    // Small primary text keeps its contrast on bg-body only; on a tinted
    // band the eyebrow takes another colour.
    var eyebrow = function (text, cls) {
        return kit.h('span', text, 'd-block mb-3 small fw-semibold text-uppercase ' + (cls || 'text-primary'));
    };
    // A line with an icon in front.
    var tick = function (icon, text, cls) {
        return kit.tag('li', 'd-flex align-items-center gap-2 ' + (cls || ''), _sdT('Item'), [
            kit.icon(icon, 'text-primary'),
            kit.h('span', text)
        ]);
    };
    // The gift card: who it is for, the message in italics and who it is
    // from, the words a customer would type when ordering.
    var note = function (to, message, from, cls) {
        return kit.div('pg-florist-note ' + (cls || ''), _sdT('Gift Card'), [
            kit.h('span', to, 'd-block fw-semibold mb-2'),
            kit.p(message, 'fst-italic mb-3'),
            kit.h('span', from, 'd-block text-end fw-semibold')
        ]);
    };

    return {
        prefix: 'florist-',
        group: { title: _sdT('Florist'), icon: 'bi-flower1' },
        items: [
            { label: _sdT('Florist: Bouquet Hero'),        type: 'component', icon: 'bi-flower1',        extra: { componentType: 'florist-hero' } },
            { label: _sdT('Florist: Card Message'),        type: 'component', icon: 'bi-envelope-heart', extra: { componentType: 'florist-card' } },
            { label: _sdT('Florist: Flower Subscription'), type: 'component', icon: 'bi-calendar-heart', extra: { componentType: 'florist-subscription' } }
        ],
        build: function (ctype) {

            // Florist: Bouquet Hero — the words rise on load with the last
            // order time for delivery today under them; the bouquet stands
            // in an arched frame that grows into place, and the gift card
            // slides up into it a moment later; the occasions follow, one
            // after another, as a row of ways in.
            if (ctype === 'florist-hero') {
                var _fhOccasion = function (icon, text) {
                    return kit.tag('li', '', text, [
                        kit.createNode('semantic', { tag: 'a', cssClass: 'btn btn-outline-primary rounded-pill d-inline-flex align-items-center gap-2', href: '#', target: '', customName: text }, [
                            kit.icon(icon),
                            kit.h('span', text)
                        ])
                    ]);
                };
                return kit.tag('section', 'py-5 overflow-hidden', _sdT('Florist Hero'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                        kit.row(_sdT('Hero Row'), [
                            kit.col(_sdT('Hero Text'), [
                                fx(kit.div('', _sdT('Body'), [
                                    eyebrow(_sdT('Florist and flower delivery')),
                                    kit.h('h1', _sdT('Flowers that say it for you'), 'display-3 fw-bold mb-4'),
                                    kit.p(_sdT('Hand-tied bouquets made the same morning, delivered across the city with a card written by hand.'), 'lead text-body-secondary mb-4'),
                                    kit.div('d-flex flex-wrap gap-2 mb-4', _sdT('Buttons'), [
                                        kit.btn(_sdT('Send Flowers'), 'primary', 'lg'),
                                        kit.btn(_sdT('See the Bouquets'), 'outline-primary', 'lg')
                                    ]),
                                    kit.div('d-inline-flex align-items-center gap-3 px-4 py-3 rounded-4 bg-body-tertiary', _sdT('Same-day Delivery'), [
                                        kit.icon('bi-truck', 'text-primary fs-2'),
                                        kit.div('', _sdT('Text'), [
                                            kit.h('span', _sdT('Last order for delivery today'), 'd-block small text-body-secondary mb-1'),
                                            kit.time('14:00', '14:00', 'pg-florist-figure fs-3 fw-bold text-body-emphasis', _sdT('Last Order Time'))
                                        ])
                                    ])
                                ]), { effect: 'slide-up', trigger: 'load', duration: 800 })
                            ], '6'),
                            kit.col(_sdT('Hero Picture'), [
                                kit.div('pg-florist-frame', _sdT('Picture Frame'), [
                                    fx(kit.div('pg-florist-arch shadow-lg', _sdT('Picture'), [
                                        kit.img('florist-hero', 960, 1200, _sdT('A hand-tied bouquet of sunflowers and white roses wrapped in paper'), 'w-100')
                                    ]), { effect: 'scale', trigger: 'load', duration: 1000 }),
                                    fx(kit.div('pg-florist-tuck', _sdT('Card Holder'), [
                                        note(_sdT('Dear Mira,'), _sdT('Happy birthday! Wishing you days as bright as these sunflowers.'), _sdT('With love, Sam'))
                                    ]), { effect: 'slide-up', trigger: 'load', duration: 800, delay: 700, distance: 60 })
                                ])
                            ], '6')
                        ], 'align-items-center g-4 g-lg-5'),
                        kit.div('mt-5 pt-4 border-top', _sdT('Occasions'), [
                            kit.h('h2', _sdT('Who are the flowers for?'), 'h6 text-uppercase fw-semibold text-body-secondary mb-3'),
                            fx(kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', _sdT('Occasions'), [
                                _fhOccasion('bi-balloon', _sdT('Birthday')),
                                _fhOccasion('bi-heart', _sdT('Love and Romance')),
                                _fhOccasion('bi-emoji-smile', _sdT('Thank You')),
                                _fhOccasion('bi-stars', _sdT('New Baby')),
                                _fhOccasion('bi-sun', _sdT('Get Well')),
                                _fhOccasion('bi-flower3', _sdT('Sympathy'))
                            ]), { effect: 'fade', trigger: 'load', duration: 600, delay: 1000, stagger: 80 })
                        ])
                    ])
                ]);
            }

            // Florist: Card Message — the gift card drawn large with a
            // sample message, growing into place as it scrolls in; beside it
            // what the card is (written in ink, how long the message may be,
            // unsigned for a surprise) and sample words for four occasions,
            // which rise one after another.
            if (ctype === 'florist-card') {
                var _fcSample = function (icon, occasion, words) {
                    return kit.tag('li', 'col', occasion, [
                        kit.div('h-100 p-3 p-lg-4 rounded-4 bg-body shadow-sm', _sdT('Sample'), [
                            kit.div('d-flex align-items-center gap-2 mb-2 text-primary', _sdT('Title'), [
                                kit.icon(icon),
                                kit.h('h3', occasion, 'h6 fw-semibold mb-0')
                            ]),
                            kit.p(words, 'fst-italic small mb-0')
                        ])
                    ]);
                };
                return kit.tag('section', 'py-5 bg-body-tertiary overflow-hidden', _sdT('Florist Card Message'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-4' }, [
                        kit.row(_sdT('Row'), [
                            kit.col(_sdT('The Card'), [
                                fx(kit.div('px-3 px-sm-5 px-lg-0', _sdT('Card Holder'), [
                                    note(_sdT('Dear Ada,'), _sdT('Thank you for the long dinners, the stories and the room by the sea. We miss you already.'), _sdT('Love, Jon and Lea'), 'p-4 p-lg-5 fs-5')
                                ]), { effect: 'scale', trigger: 'view', duration: 900 })
                            ], '5'),
                            kit.col(_sdT('Text'), [
                                fx(kit.div('', _sdT('Body'), [
                                    eyebrow(_sdT('The card'), 'text-body-secondary'),
                                    kit.h('h2', _sdT('Every bouquet comes with a card, written by hand'), 'display-6 fw-bold mb-3'),
                                    kit.p(_sdT('Type your message when you order: we write it out in ink and tuck the card into the flowers.'), 'lead text-body-secondary mb-4'),
                                    kit.tag('ul', 'list-unstyled d-flex flex-wrap gap-3 gap-lg-4 small mb-0', _sdT('Facts'), [
                                        tick('bi-pen', _sdT('Written in ink by the florist')),
                                        tick('bi-chat-left-text', _sdT('Up to 200 characters')),
                                        tick('bi-incognito', _sdT('Unsigned, if it is a surprise'))
                                    ])
                                ]), { effect: 'fade', trigger: 'view', duration: 700 }),
                                kit.p(_sdT('Not sure what to write? Start from one of these.'), 'fw-semibold mt-5 mb-3'),
                                fx(kit.tag('ul', 'list-unstyled row row-cols-1 row-cols-sm-2 g-3 mb-4', _sdT('Sample Messages'), [
                                    _fcSample('bi-balloon', _sdT('Birthday'), _sdT('Happy birthday! Here is to a year as bright as these flowers.')),
                                    _fcSample('bi-emoji-smile', _sdT('Thank You'), _sdT('Thank you for everything. It meant more than flowers can say.')),
                                    _fcSample('bi-stars', _sdT('New Baby'), _sdT('Welcome to the world, little one, and congratulations to you both.')),
                                    _fcSample('bi-flower3', _sdT('Sympathy'), _sdT('Thinking of you, and holding you close in our thoughts.'))
                                ]), { effect: 'slide-up', trigger: 'view', duration: 600, stagger: 120, distance: 30 }),
                                kit.btn(_sdT('Send Flowers'), 'primary', 'lg')
                            ], '6', '', '', 'offset-lg-1')
                        ], 'align-items-center g-4 g-lg-5')
                    ])
                ]);
            }

            // Florist: Flower Subscription — three plans side by side, each
            // with its twelve weeks drawn as a strip: a flower on every week
            // a bouquet comes, the flowers popping in one after another as
            // the card scrolls in. The price is per delivery; what the
            // twelve weeks come to is said under it. The fortnightly plan is
            // marked as the most chosen.
            if (ctype === 'florist-subscription') {
                var _fsPlan = function (n, name, every, text, count, step, price, total, lines, chosen) {
                    var weeks = [];
                    for (var w = 0; w < 12; w++) {
                        weeks.push(w % step === 0
                            ? kit.div('pg-florist-week text-bg-primary', _sdT('Delivery Week'), [ kit.icon('bi-flower1') ])
                            : kit.div('pg-florist-week bg-body-secondary', _sdT('Week'), []));
                    }
                    var head = [ kit.h('h3', name, 'h4 fw-bold mb-0') ];
                    // The three buttons share their words; the label starts
                    // with them and names the plan, so a list of links
                    // tells them apart.
                    var start = kit.btn(_sdT('Start this plan'), chosen ? 'primary' : 'outline-primary', '');
                    start.props._attrs = [{ name: 'aria-label', value: _sdT('Start this plan: {var}', name) }];
                    if (chosen) head.push(kit.h('span', _sdT('Most chosen'), 'badge rounded-pill text-bg-primary text-wrap'));
                    return kit.tag('li', 'col', _sdT('Plan {var}', n), [
                        kit.tag('article', 'card h-100 p-4 ' + (chosen ? 'border-primary border-2 shadow-lg' : 'border-0 shadow-sm'), _sdT('Card'), [
                            kit.div('d-flex flex-wrap align-items-center gap-2 mb-1', _sdT('Title'), head),
                            kit.h('span', every, 'd-block small fw-semibold text-primary mb-3'),
                            kit.p(text, 'small text-body-secondary mb-4'),
                            fx(kit.div('pg-florist-weeks mb-2', _sdT('Twelve Weeks'), weeks, hidden),
                                { effect: 'scale', trigger: 'view', duration: 400, delay: 200, stagger: 60 }),
                            kit.p(_sdT('{var} deliveries in twelve weeks', count), 'small text-body-secondary mb-4'),
                            kit.div('d-flex flex-wrap align-items-baseline gap-1', _sdT('Price'), [
                                kit.h('span', price, 'pg-florist-figure fs-2 fw-bold text-body-emphasis'),
                                kit.h('span', _sdT('per delivery'), 'small text-body-secondary')
                            ]),
                            kit.p(_sdT('{var} for the twelve weeks', total), 'small text-body-secondary mb-4'),
                            kit.tag('ul', 'list-unstyled small mb-4', _sdT('Includes'), lines.map(function (line) {
                                return tick('bi-check2', line, 'mb-2');
                            })),
                            kit.div('mt-auto', _sdT('Buttons'), [ start ])
                        ])
                    ]);
                };
                return kit.section('py-5', _sdT('Florist Flower Subscription'), [
                    fx(kit.div('row g-4 align-items-end mb-5', _sdT('Section Header'), [
                        kit.div('col-lg-7', _sdT('Section Title'), [
                            eyebrow(_sdT('Flower subscription')),
                            kit.h('h2', _sdT('Fresh flowers, as often as you like'), 'display-5 fw-bold mb-0')
                        ]),
                        kit.div('col-lg-5', _sdT('Lead'), [
                            kit.p(_sdT('Flowers of the season, chosen by our florists and delivered on the day you choose. Skip a delivery or stop whenever you like.'), 'lead text-body-secondary mb-0')
                        ])
                    ]), { effect: 'fade', trigger: 'view', duration: 800 }),
                    fx(kit.tag('ul', 'list-unstyled row row-cols-1 row-cols-lg-3 g-4 mb-0', _sdT('Plans'), [
                        _fsPlan(1, _sdT('Weekly Flowers'), _sdT('Every week'), _sdT('A fresh bouquet every week, so the vase is never empty.'), '12', 1, _sdT('$40'), _sdT('$480'),
                            [_sdT('A new seasonal bouquet each time'), _sdT('Free delivery on your chosen day'), _sdT('Pause or skip a week when you are away')]),
                        _fsPlan(2, _sdT('Fortnightly Flowers'), _sdT('Every two weeks'), _sdT('Our most loved plan: a bouquet every other week, while the last one fades.'), '6', 2, _sdT('$45'), _sdT('$270'),
                            [_sdT('A new seasonal bouquet each time'), _sdT('Free delivery on your chosen day'), _sdT('Pause or skip a delivery when you are away')], true),
                        _fsPlan(3, _sdT('Monthly Flowers'), _sdT('Every month'), _sdT('A treat once a month, for your home, your office or someone you love.'), '3', 4, _sdT('$60'), _sdT('$180'),
                            [_sdT('A larger seasonal bouquet each time'), _sdT('Free delivery on your chosen day'), _sdT('A vase with the first delivery')])
                    ]), { effect: 'slide-up', trigger: 'view', duration: 700, stagger: 120, distance: 40 }),
                    kit.div('d-flex align-items-center justify-content-center gap-2 mt-4 small text-body-secondary text-center', _sdT('Note'), [
                        kit.icon('bi-info-circle'),
                        kit.h('span', _sdT('Change the day, skip a delivery or pause from your account.'))
                    ])
                ]);
            }

            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
