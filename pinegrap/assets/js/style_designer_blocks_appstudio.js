/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual designer's App Studio blocks (prefix appstudio-): the sections
 * of an app maker's site, apps shown on phone frames. A dark hero whose
 * three phones rise in a fan on load, an app switch that puts the chosen
 * app's screen on the phone without script, a release told as one chapter
 * with its changes and a phone that moves slower than the page, and the two
 * platforms side by side with their native tools. The phone frame is drawn
 * by one rule (pg-appstudio-phone) from the palette's surface colours.
 * Motion comes from the nodes' interactions (props._fx, played by
 * pg_fx.src.js, which honours reduced motion); the feature tiles are the
 * Creative group's bento-features with an interaction on its row.
 *
 * Registered with StyleDesigner.registerBlockFamily() and bundled after the
 * designer core (pg_designer_js_sources()); see
 * docs/memory/designer/designer-blocks-appstudio.md.
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
        // A phone frame around a screenshot: a bezel of the palette's second
        // surface, a speaker pill, the large shadow. The corner radius is the
        // device's own shape, not the look's, so it stays under every look.
        'pg-appstudio-phone':    '.pg-appstudio-phone { position: relative; aspect-ratio: 9 / 19.5; max-width: 100%; margin-inline: auto; overflow: hidden; ' +
                                 'border: .55rem solid var(--bs-secondary-bg); border-radius: 2.75rem; background-color: var(--bs-body-bg); ' +
                                 'box-shadow: 0 0 0 1px var(--bs-border-color), var(--bs-box-shadow-lg); } ' +
                                 '.pg-appstudio-phone::before { content: ""; position: absolute; z-index: 1; top: .5rem; left: 50%; width: 30%; height: 1.25rem; ' +
                                 'transform: translateX(-50%); border-radius: 1rem; background-color: var(--bs-secondary-bg); } ' +
                                 '.pg-appstudio-phone img { display: block; width: 100%; height: 100%; object-fit: cover; }',
        // Very large, tight headlines: the look's heading tracking pulled in
        // and a size that grows with the window.
        'pg-appstudio-tight':    '.pg-appstudio-tight { font-size: clamp(2.5rem, 7vw, 5.5rem); line-height: 1.02; letter-spacing: calc(var(--pg-heading-tracking, 0em) - .035em); text-wrap: balance; }',
        // The hero's fan: three phones side by side, the middle one larger,
        // cut by the band's lower edge. On a narrow screen the row is wider
        // than the window and the band clips both sides alike. The floor is
        // a wash of the palette under the phones.
        'pg-appstudio-fan':      '.pg-appstudio-fan { display: flex; justify-content: center; align-items: flex-end; gap: clamp(.75rem, 3vw, 2.5rem); margin-bottom: -5rem; padding-top: 3rem; } ' +
                                 '.pg-appstudio-fan .pg-appstudio-phone { flex: none; width: clamp(7.5rem, 24vw, 16rem); margin-inline: 0; } ' +
                                 '.pg-appstudio-fan .pg-appstudio-lead { width: clamp(9rem, 29vw, 19rem); } ' +
                                 '.pg-appstudio-floor { background: linear-gradient(to bottom, transparent 55%, var(--bs-secondary-bg) 100%); } ' +
                                 '@supports (background: color-mix(in srgb, red 50%, blue)) { .pg-appstudio-floor { background: linear-gradient(to bottom, transparent 50%, color-mix(in srgb, var(--bs-primary) 30%, var(--bs-body-bg)) 100%); } }',
        // The app switch: radio buttons drawn as a segmented row; the checked
        // one shows its screen and its words (each set stacked in one grid
        // cell). :has() and descendant selectors only, so the canvas (an
        // .sd-wrap around every node) matches too.
        'pg-appstudio-switch':   '.pg-appstudio-switch .pg-appstudio-stage { display: grid; } ' +
                                 '.pg-appstudio-switch .pg-appstudio-stage .pg-appstudio-phone { width: min(70vw, 18rem); } ' +
                                 '.pg-appstudio-switch .pg-appstudio-screen, .pg-appstudio-switch .pg-appstudio-note { grid-area: 1 / 1; opacity: 0; visibility: hidden; transition: opacity .45s ease, visibility .45s; } ' +
                                 '.pg-appstudio-switch:has(.pg-appstudio-pick-1:checked) .pg-appstudio-shows-1, .pg-appstudio-switch:has(.pg-appstudio-pick-2:checked) .pg-appstudio-shows-2, ' +
                                 '.pg-appstudio-switch:has(.pg-appstudio-pick-3:checked) .pg-appstudio-shows-3 { opacity: 1; visibility: visible; } ' +
                                 '.pg-appstudio-switch .pg-appstudio-tab:has(.btn-check:checked) .btn { color: var(--pg-on-primary, var(--bs-body-bg)); background-color: var(--bs-primary); border-color: var(--bs-primary); } ' +
                                 '.pg-appstudio-switch .pg-appstudio-tab:has(.btn-check:focus-visible) .btn { outline: 2px solid currentColor; outline-offset: 2px; } ' +
                                 '@media (prefers-reduced-motion: reduce) { .pg-appstudio-switch .pg-appstudio-screen, .pg-appstudio-switch .pg-appstudio-note { transition: none; } }',
        // Version numbers that line up.
        'pg-appstudio-figure':   '.pg-appstudio-figure { font-variant-numeric: tabular-nums; }',
        // A phone that peeks out of the bottom of its card, cut by the card.
        'pg-appstudio-peek':     '.pg-appstudio-peek { width: min(62%, 15rem); margin-bottom: -40%; border-color: var(--bs-body-bg); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'appstudio-hero':      ['pg-appstudio-phone', 'pg-appstudio-tight', 'pg-appstudio-fan'],
        'appstudio-switch':    ['pg-appstudio-phone', 'pg-appstudio-tight', 'pg-appstudio-switch'],
        'appstudio-release':   ['pg-appstudio-phone', 'pg-appstudio-tight', 'pg-appstudio-figure'],
        'appstudio-platforms': ['pg-appstudio-phone', 'pg-appstudio-tight', 'pg-appstudio-peek']
    };

    // A node with an interaction (props._fx, designer-interactions.md).
    var fx = function (node, effect) {
        node.props._fx = effect;
        return node;
    };
    // A dark band: the palette's dark colours apply inside it.
    var dark = [{ name: 'data-bs-theme', value: 'dark' }];
    // A phone frame with a sample screen (9:19.5, the frame's own ratio).
    var phone = function (seed, alt, cls) {
        return kit.div('pg-appstudio-phone' + (cls ? ' ' + cls : ''), _sdT('Phone Frame'), [
            kit.img(seed, 540, 1170, alt, '')
        ]);
    };
    var eyebrow = function (text, cls) {
        return kit.h('span', text, 'd-block mb-3 small fw-semibold text-uppercase text-primary' + (cls ? ' ' + cls : ''));
    };

    return {
        prefix: 'appstudio-',
        group: { title: _sdT('App Studio'), icon: 'bi-app-indicator' },
        items: [
            { label: _sdT('App Studio: Phone Fan Hero'),  type: 'component', icon: 'bi-phone',           extra: { componentType: 'appstudio-hero' } },
            { label: _sdT('App Studio: App Switch'),      type: 'component', icon: 'bi-toggles',         extra: { componentType: 'appstudio-switch' } },
            { label: _sdT('App Studio: Release Chapter'), type: 'component', icon: 'bi-rocket-takeoff',  extra: { componentType: 'appstudio-release' } },
            { label: _sdT('App Studio: Two Platforms'),   type: 'component', icon: 'bi-phone-flip',      extra: { componentType: 'appstudio-platforms' } }
        ],
        build: function (ctype) {

            // App Studio: Phone Fan Hero — a dark band: one very large line,
            // a short one and two buttons fade in on load; under them three
            // phones rise one after another, cut by the band's lower edge.
            if (ctype === 'appstudio-hero') {
                return kit.tag('section', 'pt-5 bg-body text-body text-center overflow-hidden pg-appstudio-floor', _sdT('App Studio Hero'), [
                    kit.createNode('container', { fluid: false, cssClass: 'pt-lg-5' }, [
                        fx(kit.div('mx-auto col-lg-10', _sdT('Hero Text'), [
                            eyebrow(_sdT('Independent app studio')),
                            kit.h('h1', _sdT('Apps you open every day.'), 'pg-appstudio-tight fw-bold mb-4'),
                            kit.p(_sdT('Small, calm apps for iPhone and Android, made by one studio from the first sketch to the store.'), 'lead text-body-secondary mx-auto mb-4 col-lg-8'),
                            kit.div('d-flex flex-wrap justify-content-center gap-3', _sdT('Hero Buttons'), [
                                kit.btn(_sdT('See the apps'), 'primary', 'lg'),
                                kit.btn(_sdT('Start a project'), 'secondary', 'lg')
                            ])
                        ]), { effect: 'fade', trigger: 'load', duration: 900 })
                    ]),
                    fx(kit.div('pg-appstudio-fan', _sdT('Phone Fan'), [
                        phone('appstudio-fan-1', _sdT('A habit tracker\'s month view')),
                        phone('appstudio-fan-2', _sdT('A running plan for the week'), 'pg-appstudio-lead'),
                        phone('appstudio-fan-3', _sdT('A trip plan on an offline map'))
                    ]), { effect: 'slide-up', trigger: 'load', duration: 1000, delay: 200, stagger: 150, distance: 120 })
                ], dark);
            }

            // App Studio: App Switch — a segmented row of apps; the checked one
            // puts its screen on the phone and its line under it, no script.
            if (ctype === 'appstudio-switch') {
                var _asName = kit.uid('as_app');
                var _asTab = function (n, label, checked) {
                    var id = _asName + '_' + n;
                    var attrs = [{ name: 'type', value: 'radio' }, { name: 'name', value: _asName }, { name: 'id', value: id }, { name: 'autocomplete', value: 'off' }];
                    if (checked) attrs.push({ name: 'checked', value: 'checked' });
                    return kit.div('pg-appstudio-tab', _sdT('App Choice {var}', n), [
                        kit.tag('input', 'btn-check pg-appstudio-pick-' + n, _sdT('Choice'), [], attrs),
                        kit.tag('label', 'btn btn-outline-primary rounded-pill px-4', _sdT('Choice Label'), [ kit.h('span', label) ], [{ name: 'for', value: id }])
                    ]);
                };
                var _asNote = function (n, name, line) {
                    return kit.div('pg-appstudio-note pg-appstudio-shows-' + n, _sdT('App Words {var}', n), [
                        kit.h('h3', name, 'h4 fw-bold mb-1'),
                        kit.p(line, 'text-body-secondary mb-0')
                    ]);
                };
                return kit.section('py-5 text-center pg-appstudio-switch', _sdT('App Studio Switch'), [
                    fx(kit.div('mb-4', _sdT('Section Header'), [
                        eyebrow(_sdT('Pick an app')),
                        kit.h('h2', _sdT('One idea per app.'), 'pg-appstudio-tight fw-bold mb-3'),
                        kit.p(_sdT('Each app does one thing and does it well. Choose one to see it on the phone.'), 'lead text-body-secondary mx-auto mb-0 col-lg-7')
                    ]), { effect: 'slide-up', trigger: 'view', duration: 700 }),
                    kit.tag('fieldset', 'd-flex justify-content-center flex-wrap gap-2 border-0 p-0 mb-5', _sdT('App Choices'), [
                        kit.tag('legend', 'visually-hidden', _sdT('Choices Legend'), [ kit.h('span', _sdT('App')) ]),
                        _asTab(1, 'Tally', true),
                        _asTab(2, 'Stride', false),
                        _asTab(3, 'Wayfarer', false)
                    ]),
                    fx(kit.div('pg-appstudio-stage mb-4', _sdT('Phone Stage'), [
                        phone('appstudio-switch-1', _sdT('A habit tracker\'s month view'), 'pg-appstudio-screen pg-appstudio-shows-1'),
                        phone('appstudio-switch-2', _sdT('A running plan for the week'), 'pg-appstudio-screen pg-appstudio-shows-2'),
                        phone('appstudio-switch-3', _sdT('A trip plan on an offline map'), 'pg-appstudio-screen pg-appstudio-shows-3')
                    ]), { effect: 'scale', trigger: 'view', duration: 800 }),
                    kit.div('pg-appstudio-stage mx-auto col-lg-6', _sdT('Words Stage'), [
                        _asNote(1, 'Tally', _sdT('Habits you keep, one tap a day.')),
                        _asNote(2, 'Stride', _sdT('A running coach that listens to your legs.')),
                        _asNote(3, 'Wayfarer', _sdT('Your whole trip, even with no signal.'))
                    ])
                ]);
            }

            // App Studio: Release Chapter — one release as a dark chapter: its
            // name and version very large, what changed tagged New, Improved
            // or Fixed, rising one after another; the phone beside it moves
            // slower than the page.
            if (ctype === 'appstudio-release') {
                var _arChange = function (n, tag, tagCls, text) {
                    return kit.tag('li', 'd-flex align-items-start gap-3 mb-3', _sdT('Change {var}', n), [
                        kit.h('span', tag, 'badge rounded-pill flex-shrink-0 mt-1 ' + tagCls),
                        kit.h('span', text, 'text-body')
                    ]);
                };
                return kit.tag('section', 'py-5 bg-body text-body overflow-hidden', _sdT('App Studio Release'), [
                    kit.createNode('container', { fluid: false, cssClass: 'py-lg-5' }, [
                        kit.row(_sdT('Release Row'), [
                            kit.col(_sdT('Release Copy'), [
                                eyebrow(_sdT('Just released')),
                                kit.h('h2', 'Stride 2.5', 'pg-appstudio-tight pg-appstudio-figure fw-bold mb-3'),
                                kit.p(_sdT('Interval runs with voice cues, and a calmer screen after every run.'), 'lead text-body-secondary mb-4'),
                                kit.h('h3', _sdT('What changed'), 'h6 fw-semibold text-uppercase text-body-secondary mb-3'),
                                fx(kit.tag('ul', 'list-unstyled mb-4', _sdT('Changes'), [
                                    _arChange(1, _sdT('New'), 'text-bg-primary', _sdT('Interval runs with a voice that counts you through each one.')),
                                    _arChange(2, _sdT('Improved'), 'bg-primary-subtle text-primary-emphasis', _sdT('A calmer summary after every run, with only what matters.')),
                                    _arChange(3, _sdT('Bug fix'), 'bg-body-secondary text-body-emphasis border', _sdT('Runs from the watch come in again after a system update.'))
                                ]), { effect: 'slide-up', trigger: 'view', duration: 600, stagger: 120 }),
                                kit.div('d-flex flex-wrap gap-3', _sdT('Release Buttons'), [
                                    kit.btn(_sdT('Update now'), 'primary', 'lg'),
                                    kit.btn(_sdT('All release notes'), 'secondary', 'lg')
                                ])
                            ], '6', '', '', 'order-2 order-lg-1'),
                            kit.col(_sdT('Release Picture'), [
                                fx(kit.div('px-5 px-lg-0', _sdT('Phone Stage'), [
                                    phone('appstudio-release', _sdT('A running app\'s interval screen'), 'col-lg-8')
                                ]), { effect: 'parallax', speed: 15 })
                            ], '5', '', '', 'order-1 order-lg-2 offset-lg-1')
                        ], 'align-items-center')
                    ])
                ], dark);
            }

            // App Studio: Two Platforms — the two platforms side by side, each
            // with its tools and a phone peeking out of the card; the cards
            // rise one after the other.
            if (ctype === 'appstudio-platforms') {
                var _apCard = function (n, icon, title, text, tools, seed, alt) {
                    return kit.col(_sdT('Platform {var}', n), [
                        kit.div('h-100 d-flex flex-column align-items-center pt-5 px-4 text-center bg-body-tertiary rounded-4 overflow-hidden', _sdT('Platform Card'), [
                            kit.icon(icon, 'fs-1 mb-3 text-body-emphasis'),
                            kit.h('h3', title, 'h3 fw-bold mb-2'),
                            kit.p(text, 'text-body-secondary mb-3'),
                            kit.tag('ul', 'list-unstyled d-flex flex-wrap justify-content-center gap-2 mb-5', _sdT('Tools'), tools.map(function (t) {
                                return kit.tag('li', 'badge rounded-pill bg-body text-body-emphasis border fw-normal', _sdT('Tool'), [ kit.h('span', t) ]);
                            })),
                            phone(seed, alt, 'pg-appstudio-peek mt-auto')
                        ])
                    ], '6');
                };
                return kit.section('py-5', _sdT('App Studio Platforms'), [
                    kit.div('text-center mb-5', _sdT('Section Header'), [
                        eyebrow(_sdT('Native on both')),
                        kit.h('h2', _sdT('Made for the phone in your hand.'), 'pg-appstudio-tight fw-bold mb-3'),
                        kit.p(_sdT('Written in each platform\'s own language, so every app feels at home and stays fast.'), 'lead text-body-secondary mx-auto mb-0 col-lg-7')
                    ]),
                    fx(kit.row(_sdT('Platforms Row'), [
                        _apCard(1, 'bi-apple', _sdT('iPhone, iPad and Apple Watch'), _sdT('Widgets, Live Activities and a watch app that works on its own.'),
                            ['Swift', 'SwiftUI', 'Xcode'], 'appstudio-ios', _sdT('An app on an iPhone')),
                        _apCard(2, 'bi-android2', _sdT('Android phones, tablets and Wear OS'), _sdT('Material You colours, home screen widgets and offline sync.'),
                            ['Kotlin', 'Jetpack Compose', 'Android Studio'], 'appstudio-android', _sdT('An app on an Android phone'))
                    ]), { effect: 'slide-up', trigger: 'view', duration: 700, stagger: 150 })
                ]);
            }

            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
