/**
 * Pinegrap - Enterprise Website Platform
 *
 * Podcast block family: the sections a podcast's or a podcast network's
 * site needs that the general palette lacks. The episode hero (the newest
 * episode: its cover with a sound wave under it, the podcast, season and
 * length, the title, where to listen and the podcast apps, then its
 * chapters, each with the time it starts, beside the guest and a line
 * they said), the sponsor placements (where a sponsor's mention plays,
 * drawn on one episode and across a season, and the three ways to sponsor
 * with their prices) and the transcript (a part of an episode written out,
 * each turn with the time it starts and who speaks). The hosts, the
 * figures, the supporters' tiers, the steps of a guest pitch and the
 * questions are general blocks (Team, Stats, Pricing, Steps, FAQ).
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
        // The episode's cover, square, with a sound wave under it: bars in
        // the theme's colour cut to a rising and falling outline by a mask.
        'pg-podcast-cover':    '.pg-podcast-cover { max-width: 28rem; margin-inline: auto; } ' +
                               '.pg-podcast-cover img { display: block; width: 100%; aspect-ratio: 1 / 1; object-fit: cover; } ' +
                               '.pg-podcast-cover .pg-podcast-wave { height: 3rem; margin-top: 1.25rem; color: var(--bs-primary); ' +
                               'background: repeating-linear-gradient(90deg, currentColor 0 3px, transparent 3px 7px); ' +
                               '-webkit-mask-image: radial-gradient(ellipse 12% 40% at 7% 50%, #000 97%, transparent), radial-gradient(ellipse 14% 85% at 24% 50%, #000 97%, transparent), ' +
                               'radial-gradient(ellipse 12% 55% at 42% 50%, #000 97%, transparent), radial-gradient(ellipse 13% 100% at 59% 50%, #000 97%, transparent), ' +
                               'radial-gradient(ellipse 12% 45% at 77% 50%, #000 97%, transparent), radial-gradient(ellipse 10% 70% at 93% 50%, #000 97%, transparent); ' +
                               'mask-image: radial-gradient(ellipse 12% 40% at 7% 50%, #000 97%, transparent), radial-gradient(ellipse 14% 85% at 24% 50%, #000 97%, transparent), ' +
                               'radial-gradient(ellipse 12% 55% at 42% 50%, #000 97%, transparent), radial-gradient(ellipse 13% 100% at 59% 50%, #000 97%, transparent), ' +
                               'radial-gradient(ellipse 12% 45% at 77% 50%, #000 97%, transparent), radial-gradient(ellipse 10% 70% at 93% 50%, #000 97%, transparent); }',
        // The chapters: where each starts, in figures that line up, then
        // its title, on a hairline.
        'pg-podcast-chapters': '.pg-podcast-chapters { margin: 0; padding: 0; list-style: none; border-top: 1px solid var(--bs-border-color); } ' +
                               '.pg-podcast-chapters li { display: flex; align-items: baseline; gap: 1rem; padding: .75rem 0; border-bottom: 1px solid var(--bs-border-color); } ' +
                               '.pg-podcast-chapters time { flex-shrink: 0; min-width: 3.5rem; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--bs-primary-text-emphasis); }',
        // Where a sponsor's mention plays: one episode as a bar with the
        // opening and the middle mention in the theme's colour, each
        // labelled under the bar where it starts (set in by a margin, as a
        // positioned label would follow the canvas's wrapper), and a season
        // as ten episodes, each opening with its mention.
        'pg-podcast-track':    '.pg-podcast-track .pg-podcast-bar { height: 2.5rem; border-radius: var(--bs-border-radius-lg); ' +
                               'background: linear-gradient(90deg, var(--bs-primary) 0 7%, var(--bs-border-color) 7% 46%, var(--bs-primary) 46% 56%, var(--bs-border-color) 56%); } ' +
                               '.pg-podcast-track .pg-podcast-at { display: flex; align-items: center; gap: .5rem; margin: .625rem 0 0; font-size: .875rem; } ' +
                               '.pg-podcast-track .pg-podcast-at-mid { margin-left: 46%; } ' +
                               '.pg-podcast-track .pg-podcast-season { height: 1.5rem; border-radius: var(--bs-border-radius-sm); ' +
                               'background: repeating-linear-gradient(90deg, var(--bs-primary) 0 1.5%, var(--bs-border-color) 1.5% 9%, transparent 9% 10%); }',
        // A placement's number: a round mark in the theme's tint, the same
        // under the bar and on the placement's card.
        'pg-podcast-no':       '.pg-podcast-no { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 1.75rem; height: 1.75rem; ' +
                               'border-radius: 50%; font-size: .875rem; font-weight: 700; color: var(--bs-primary-text-emphasis); background-color: var(--bs-primary-bg-subtle); }',
        // The transcript: each turn the time it starts in a narrow column,
        // then who speaks and what they say; turns spaced by the list's gap.
        'pg-podcast-script':   '.pg-podcast-script { display: grid; gap: 1.25rem; } ' +
                               '.pg-podcast-script .pg-podcast-turn { display: grid; grid-template-columns: 3.5rem minmax(0, 1fr); gap: .25rem 1rem; align-items: baseline; } ' +
                               '.pg-podcast-script time { font-size: .875rem; font-variant-numeric: tabular-nums; color: var(--bs-secondary-color); }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'podcast-episode':    ['pg-podcast-cover', 'pg-podcast-chapters'],
        'podcast-sponsor':    ['pg-podcast-track', 'pg-podcast-no'],
        'podcast-transcript': ['pg-podcast-script']
    };

    // An interaction the operator can change or remove in the Interactions
    // section; pg_fx keeps the node still under reduced motion.
    var fx = function (node, spec) {
        node.props._fx = spec;
        return node;
    };

    // The small spaced line above a title. Small text in the theme's colour
    // takes its darker shade, readable on every surface.
    var eyebrow = function (text, cls) {
        return kit.p(text, 'small text-uppercase fw-semibold text-primary-emphasis ' + (cls || 'mb-2'));
    };

    // A time in an episode, minutes:seconds, with its machine-readable
    // duration from the start.
    var at = function (time, name) {
        var parts = time.split(':');
        return kit.time('PT' + parseInt(parts[0], 10) + 'M' + parseInt(parts[1], 10) + 'S', time, '', name);
    };

    // ── Episode hero ────────────────────────────────────────────────────
    // A fact of the episode with its icon: the podcast, the season, the
    // length.
    var fact = function (icon, node) {
        return kit.div('d-inline-flex align-items-center gap-2', _sdT('Detail'), [kit.icon(icon, 'text-primary-emphasis'), node]);
    };
    // A podcast app the episode plays in, as an icon button named for a
    // screen reader.
    var app = function (icon, name) {
        return kit.iconLink(icon, name, 'btn border link-body-emphasis d-inline-flex align-items-center justify-content-center px-2', '#');
    };
    // A chapter: where it starts and its title.
    var chapter = function (time, title) {
        return kit.tag('li', '', title, [at(time, _sdT('Start')), kit.h('span', title)]);
    };
    var buildEpisode = function () {
        var cover = fx(kit.div('pg-podcast-cover', _sdT('Cover'), [
            kit.img('podcast-episode', 1000, 1000, _sdT('The podcast\'s cover: two cups of tea and a microphone on a kitchen table'), 'rounded-4 shadow-lg'),
            fx(kit.div('pg-podcast-wave', _sdT('Sound Wave'), [], [{ name: 'aria-hidden', value: 'true' }]),
                { effect: 'slide-right', trigger: 'load', duration: 1200, delay: 500, distance: 60 })
        ]), { effect: 'scale', trigger: 'load', duration: 900 });
        var words = fx(kit.div('', _sdT('Episode'), [
            eyebrow(_sdT('New episode'), 'mb-3'),
            kit.div('d-flex flex-wrap gap-3 small text-body-secondary mb-3', _sdT('Details'), [
                fact('bi-mic', kit.link(_sdT('Kitchen Table Talks'), '#', 'fw-semibold link-body-emphasis link-offset-2', _sdT('Podcast Link'))),
                fact('bi-collection-play', kit.h('span', _sdT('Season 2, Episode 12'))),
                fact('bi-clock', kit.time('PT58M', _sdT('58 min'), '', _sdT('Length')))
            ]),
            kit.h('h1', _sdT('A Chef Who Cooks Only for Neighbours'), 'display-5 fw-bold mb-3'),
            kit.p(_sdT('Derya closed her restaurant and opened her kitchen to the street. We talk about cooking for people you know by name.'), 'lead text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('Listen Now'), '#', 'btn btn-primary btn-lg px-4', _sdT('Listen Link')),
                kit.link(_sdT('Episode Notes'), '#', 'btn btn-lg border px-4 link-body-emphasis', _sdT('Notes Link'))
            ]),
            kit.div('d-flex flex-wrap align-items-center gap-2 mt-4', _sdT('Podcast Apps'), [
                kit.p(_sdT('Also in your podcast app'), 'small text-body-secondary mb-0 me-1'),
                app('bi-apple', 'Apple Podcasts'),
                app('bi-spotify', 'Spotify'),
                app('bi-youtube', 'YouTube'),
                app('bi-rss', _sdT('RSS Feed'))
            ])
        ]), { effect: 'slide-up', trigger: 'load', duration: 700, delay: 150 });
        var chapters = kit.col(_sdT('Chapters'), [
            kit.h('h2', _sdT('Chapters'), 'h5 fw-bold mb-3'),
            fx(kit.tag('ol', 'pg-podcast-chapters', _sdT('Chapter List'), [
                chapter('0:00', _sdT('Hello and welcome')),
                chapter('4:10', _sdT('Closing the restaurant')),
                chapter('21:45', _sdT('The first neighbour dinner')),
                chapter('47:30', _sdT('What she cooks at home'))
            ]), { effect: 'slide-up', trigger: 'view', duration: 600, stagger: 100 })
        ], '7');
        var guest = kit.col(_sdT('Guest'), [
            fx(kit.div('p-4 rounded-4 bg-body-tertiary', _sdT('Guest Card'), [
                kit.h('h2', _sdT('Our guest'), 'h5 fw-bold mb-3'),
                kit.div('d-flex align-items-center gap-3 mb-3', _sdT('Name'), [
                    kit.img('podcast-guest', 96, 96, _sdT('Derya Aksoy in her kitchen'), 'rounded-circle object-fit-cover flex-shrink-0'),
                    kit.div('', _sdT('Name'), [
                        kit.p(_sdT('Derya Aksoy'), 'fw-semibold mb-0'),
                        kit.p(_sdT('Chef, cooks for her neighbours'), 'small text-body-secondary mb-0')
                    ])
                ]),
                kit.tag('blockquote', 'border-start border-3 border-primary ps-3 mb-3', _sdT('Quote'), [
                    kit.p(_sdT('I know every plate\'s name before I cook it.'), 'fst-italic mb-0')
                ]),
                kit.p(_sdT('Hosted by Selin Arda'), 'small text-body-secondary mb-0')
            ]), { effect: 'fade', trigger: 'view', duration: 800, delay: 200 })
        ], '5');
        return kit.section('py-5 overflow-hidden', _sdT('Podcast (Episode Hero)'), [
            kit.row(_sdT('Episode Row'), [
                kit.col(_sdT('Cover'), [cover], '5'),
                kit.col(_sdT('Episode'), [words], '7')
            ], 'g-4 g-lg-5 align-items-center'),
            kit.row(_sdT('In This Episode'), [chapters, guest], 'g-4 g-lg-5 mt-4 mt-lg-5')
        ]);
    };

    // ── Sponsor placements ──────────────────────────────────────────────
    // A placement: its number (as under the bar), its name, how long and
    // where it plays, what it is, and the price it starts from.
    var placement = function (no, title, when, text, price, per) {
        return kit.tag('li', 'col-md-4', title, [
            kit.div('h-100 p-4 rounded-4 border bg-body', _sdT('Card'), [
                kit.div('d-flex align-items-center gap-2 mb-3', _sdT('Title'), [
                    kit.h('span', no, 'pg-podcast-no'),
                    kit.h('h3', title, 'h5 fw-bold mb-0')
                ]),
                kit.p(when, 'small fw-semibold text-body-emphasis mb-2'),
                kit.p(text, 'small text-body-secondary mb-4'),
                kit.div('d-flex flex-wrap align-items-baseline gap-1', _sdT('Price'), [
                    kit.h('span', _sdT('Starting from'), 'small text-body-secondary'),
                    kit.h('span', price, 'fs-4 fw-bold text-body-emphasis'),
                    kit.h('span', per, 'small text-body-secondary')
                ])
            ])
        ]);
    };
    // A label under the episode bar: the placement's number and where it
    // starts.
    var mark = function (no, text, cls) {
        return kit.div('pg-podcast-at' + (cls ? ' ' + cls : ''), text, [kit.h('span', no, 'pg-podcast-no'), kit.h('span', text)]);
    };
    var buildSponsor = function () {
        var head = fx(kit.row(_sdT('Header'), [
            kit.col(_sdT('Title'), [
                eyebrow(_sdT('Sponsorship')),
                kit.h('h2', _sdT('Where your mention plays'), 'display-6 fw-bold mb-0')
            ], '6'),
            kit.col(_sdT('Text'), [
                kit.p(_sdT('Every mention is read by the host, in their own words, at one of three places. Listeners hear it the way they hear the rest of the episode.'), 'lead text-body-secondary mb-0')
            ], '6')
        ], 'g-3 align-items-end mb-5'), { effect: 'fade', trigger: 'view', duration: 800 });
        var track = fx(kit.div('pg-podcast-track mb-5', _sdT('Placements Drawing'), [
            kit.p(_sdT('One episode'), 'small fw-semibold text-body-emphasis mb-2'),
            kit.div('pg-podcast-bar', _sdT('Episode'), []),
            mark('1', _sdT('Before the episode starts'), ''),
            mark('2', _sdT('About halfway'), 'pg-podcast-at-mid'),
            kit.p(_sdT('A season'), 'small fw-semibold text-body-emphasis mt-4 mb-2'),
            kit.div('pg-podcast-season', _sdT('Season'), []),
            mark('3', _sdT('In every episode of the season'), '')
        ], [{ name: 'aria-hidden', value: 'true' }]), { effect: 'slide-right', trigger: 'view', duration: 900, delay: 150, distance: 60 });
        var list = fx(kit.tag('ol', 'row g-4 list-unstyled mb-4', _sdT('Placements'), [
            placement('1', _sdT('Opening Mention'), _sdT('30 seconds, before the episode starts'),
                _sdT('A thirty-second mention read by the host before the episode starts.'), '$250', _sdT('per episode')),
            placement('2', _sdT('Mid-episode Story'), _sdT('90 seconds, about halfway'),
                _sdT('A ninety-second story in the middle of the episode, when listeners are most attentive.'), '$600', _sdT('per episode')),
            placement('3', _sdT('Season Partner'), _sdT('Named in every episode'),
                _sdT('The only sponsor of a whole season of one podcast, named in every episode.'), '$6,000', _sdT('per season'))
        ]), { effect: 'slide-up', trigger: 'view', duration: 600, delay: 300, stagger: 120 });
        var ask = kit.div('d-flex flex-wrap align-items-center gap-3', _sdT('Call to Action'), [
            kit.link(_sdT('Send a Sponsorship Enquiry'), '#', 'btn btn-primary btn-lg px-4', _sdT('Enquiry Link')),
            kit.p(_sdT('Nothing is booked until you agree to the proposal.'), 'small text-body-secondary mb-0')
        ]);
        return kit.section('py-5 overflow-hidden', _sdT('Podcast (Sponsor Placements)'), [head, track, list, ask]);
    };

    // ── Transcript ──────────────────────────────────────────────────────
    // A turn: when it starts, who speaks (the guest in the theme's shade)
    // and what they say.
    var turn = function (time, who, words, guest) {
        return kit.div('pg-podcast-turn', who, [
            at(time, _sdT('Start')),
            kit.div('', _sdT('Words'), [
                kit.p(who, 'fw-semibold mb-1 ' + (guest ? 'text-primary-emphasis' : 'text-body-emphasis')),
                kit.p(words, 'mb-0')
            ])
        ]);
    };
    // A speaker of the episode, in the colour their name has in the turns.
    var speaker = function (icon, name, cls) {
        return kit.tag('li', 'd-flex align-items-center gap-2 mb-2', name, [kit.icon(icon, cls), kit.h('span', name)]);
    };
    var buildTranscript = function () {
        var host = _sdT('Selin Arda'), guest = _sdT('Derya Aksoy');
        var words = fx(kit.div('', _sdT('Text'), [
            eyebrow(_sdT('Read along')),
            kit.h('h2', _sdT('Transcript'), 'display-6 fw-bold mb-3'),
            kit.p(_sdT('Every episode is written out in full, for listeners who would rather read, or cannot hear it.'), 'text-body-secondary mb-4'),
            kit.p(_sdT('In this part'), 'small fw-semibold text-body-emphasis mb-2'),
            kit.tag('ul', 'list-unstyled small mb-4', _sdT('Speakers'), [
                speaker('bi-mic-fill', _sdT('Selin Arda, host'), 'text-body-emphasis'),
                speaker('bi-person-fill', _sdT('Derya Aksoy, guest'), 'text-primary-emphasis')
            ]),
            kit.link(_sdT('Read the Full Transcript'), '#', 'btn btn-primary px-4', _sdT('Transcript Link'))
        ]), { effect: 'slide-up', trigger: 'view', duration: 700 });
        var script = kit.div('p-4 p-lg-5 rounded-4 border', _sdT('Transcript'), [
            fx(kit.div('pg-podcast-script', _sdT('Turns'), [
                kit.div('pg-podcast-turn', _sdT('Chapter'), [
                    at('4:10', _sdT('Start')),
                    kit.h('h3', _sdT('Closing the restaurant'), 'h6 fw-bold text-uppercase mb-0')
                ]),
                turn('4:12', host, _sdT('The restaurant was full most nights. Why close it?'), false),
                turn('4:18', guest, _sdT('Because I stopped knowing who I was cooking for. Two hundred plates a night, and not one name.'), true),
                turn('4:31', host, _sdT('And now?'), false),
                turn('4:33', guest, _sdT('Now it is twelve people on a Thursday. I know who leaves the onions and whose daughter has just started school.'), true),
                turn('4:52', host, _sdT('Does it pay the rent?'), false),
                turn('4:55', guest, _sdT('Just. Ask me again next spring.'), true)
            ]), { effect: 'slide-up', trigger: 'view', duration: 600, delay: 200, stagger: 100 })
        ]);
        return kit.section('py-5', _sdT('Podcast (Transcript)'), [
            kit.row(_sdT('Transcript Row'), [
                kit.col(_sdT('Text'), [words], '4'),
                kit.col(_sdT('Transcript'), [script], '8')
            ], 'g-4 g-lg-5')
        ]);
    };

    return {
        prefix: 'podcast-',
        group: { title: _sdT('Podcast'), icon: 'bi-mic' },
        items: [
            { label: _sdT('Podcast: Episode Hero with Chapters'), type: 'component', icon: 'bi-play-circle',  extra: { componentType: 'podcast-episode' } },
            { label: _sdT('Podcast: Sponsor Placements'),         type: 'component', icon: 'bi-megaphone',    extra: { componentType: 'podcast-sponsor' } },
            { label: _sdT('Podcast: Transcript'),                 type: 'component', icon: 'bi-chat-quote',   extra: { componentType: 'podcast-transcript' } }
        ],
        build: function (ctype) {
            if (ctype === 'podcast-episode') return buildEpisode();
            if (ctype === 'podcast-sponsor') return buildSponsor();
            if (ctype === 'podcast-transcript') return buildTranscript();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
