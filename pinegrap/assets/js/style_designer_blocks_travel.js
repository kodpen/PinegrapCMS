/**
 * Pinegrap - Enterprise Website Platform
 *
 * Travel block family: the sections a travel agency's or a tour operator's
 * site needs that the general palette lacks. The day-by-day itinerary (a
 * tour's days in order on a route line, each with where the night is spent
 * and the meals, beside the trip at a glance and its price a traveller) and
 * the destination guide (one place before a traveller books it: its
 * region, the seasons marked by how good each is for going, the practical
 * facts and the way to its tours). The tours and destinations themselves,
 * the deals, the travel styles, the booking steps, what a tour includes,
 * the travellers' words and the questions are general blocks (Services,
 * Pricing, Features, Social Proof, FAQ) or collection lists.
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
        // The days: a marker per day on a vertical route line that runs
        // from the first marker to the last.
        'pg-travel-days':    '.pg-travel-days { list-style: none; margin: 0; padding: 0; } ' +
                             '.pg-travel-days > li { position: relative; display: flex; gap: 1.25rem; padding-bottom: 2rem; } ' +
                             '.pg-travel-days > li:last-child { padding-bottom: 0; } ' +
                             '.pg-travel-days > li:not(:last-child)::before { content: ""; position: absolute; left: 1.75rem; top: 3.5rem; bottom: 0; border-left: 2px dashed var(--bs-primary-border-subtle); }',
        // A day's marker: the word and the number in a round badge.
        'pg-travel-day':     '.pg-travel-day { display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; width: 3.5rem; height: 3.5rem; border-radius: 50%; ' +
                             'background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); line-height: 1.1; font-variant-numeric: tabular-nums; }',
        // The trip's facts: the label above the value on a hairline.
        'pg-travel-glance':  '.pg-travel-glance { margin: 0; } ' +
                             '.pg-travel-glance > div { padding: .5rem 0; border-bottom: 1px solid var(--bs-border-color); } ' +
                             '.pg-travel-glance dt { font-size: .875rem; font-weight: 600; color: var(--bs-secondary-color); } ' +
                             '.pg-travel-glance dd { margin: 0; }',
        // A price in tabular figures.
        'pg-travel-figure':  '.pg-travel-figure { font-variant-numeric: tabular-nums; color: var(--bs-emphasis-color); line-height: 1; }',
        // The seasons: four tiles in a row from sm up; a good season is
        // tinted and also carries its word and a check, never colour alone.
        'pg-travel-seasons': '.pg-travel-seasons { display: grid; gap: .5rem; grid-template-columns: repeat(2, minmax(0, 1fr)); list-style: none; margin: 0; padding: 0; } ' +
                             '.pg-travel-seasons > li { border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); padding: .75rem; text-align: center; } ' +
                             '.pg-travel-seasons > li.pg-travel-best { background: var(--bs-primary-bg-subtle); border-color: var(--bs-primary-border-subtle); color: var(--bs-primary-text-emphasis); } ' +
                             '@media (min-width: 576px) { .pg-travel-seasons { grid-template-columns: repeat(4, minmax(0, 1fr)); } }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'travel-itinerary':   ['pg-travel-days', 'pg-travel-day', 'pg-travel-glance', 'pg-travel-figure'],
        'travel-destination': ['pg-travel-seasons', 'pg-travel-glance']
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

    // An icon and a few words on one line.
    var note = function (icon, text, name) {
        return kit.div('d-flex align-items-center gap-2', name, [
            kit.icon(icon, 'text-primary flex-shrink-0'),
            kit.h('span', text)
        ]);
    };

    // A fact: its label and its value.
    var fact = function (label, value) {
        return kit.div('', label, [
            kit.h('dt', label),
            kit.h('dd', value)
        ]);
    };

    // ── Day by day ──────────────────────────────────────────────────────
    // One day of the tour: its marker, its title, what happens and where
    // the night is spent with the meals.
    var day = function (n, title, text, night, meals) {
        return kit.tag('li', '', title, [
            kit.div('pg-travel-day', _sdT('Day'), [
                kit.h('span', _sdT('Day'), 'small text-uppercase fw-semibold'),
                kit.h('span', n, 'fs-5 fw-bold')
            ]),
            kit.div('flex-grow-1 pt-1', _sdT('Text'), [
                kit.h('h3', title, 'h5 fw-bold mb-1'),
                kit.p(text, 'text-body-secondary mb-2'),
                kit.div('d-flex flex-wrap gap-3 small', _sdT('Notes'), [
                    note('bi-moon', night, _sdT('Overnight')),
                    note('bi-cup-hot', meals, _sdT('Meals'))
                ])
            ])
        ]);
    };
    var buildItinerary = function () {
        var days = fx(kit.tag('ol', 'pg-travel-days', _sdT('Itinerary'), [
            day('1', _sdT('Arrival in Göreme'), _sdT('Transfer from the airport, a welcome dinner and a short walk to the sunset point.'), _sdT('Cave hotel in Göreme'), _sdT('Dinner')),
            day('2', _sdT('Rose and Red Valleys'), _sdT('A morning walk through the valleys, cave churches and lunch in a village.'), _sdT('Cave hotel in Göreme'), _sdT('Breakfast and lunch')),
            day('3', _sdT('Balloons and the underground city'), _sdT('The balloon flight at dawn, then the underground city and the pottery town of Avanos.'), _sdT('Stone house in Ürgüp'), _sdT('Breakfast and dinner')),
            day('4', _sdT('Farewell breakfast'), _sdT('A last breakfast on the terrace and the transfer to the airport.'), _sdT('The journey home'), _sdT('Breakfast'))
        ]), { effect: 'slide-up', trigger: 'view', stagger: 100 });
        var glance = kit.tag('aside', 'card border-0 shadow-sm rounded-4 sticky-lg-top', _sdT('Trip at a Glance'), [
            kit.div('card-body p-4', _sdT('Card Body'), [
                kit.h('h3', _sdT('Trip at a glance'), 'h5 fw-bold mb-3'),
                kit.tag('dl', 'pg-travel-glance mb-4', _sdT('Facts'), [
                    fact(_sdT('Duration'), _sdT('4 days, 3 nights')),
                    fact(_sdT('Group size'), _sdT('Up to 12 travellers')),
                    fact(_sdT('Starts and ends'), _sdT('Kayseri airport')),
                    fact(_sdT('Departures'), _sdT('Every Friday'))
                ]),
                kit.div('d-flex align-items-baseline flex-wrap gap-2 mb-3', _sdT('Price'), [
                    kit.h('span', _sdT('Starting from'), 'small text-body-secondary'),
                    kit.h('span', _sdT('$890'), 'pg-travel-figure fs-2 fw-bold'),
                    kit.h('span', _sdT('per traveller'), 'text-body-secondary')
                ]),
                kit.link(_sdT('Book a Tour'), '#', 'btn btn-primary rounded-pill w-100 mb-2', _sdT('Booking Link')),
                kit.link(_sdT('Plan a Tailor-made Trip'), '#', 'btn btn-outline-primary rounded-pill w-100', _sdT('Enquiry Link'))
            ])
        ]);
        return kit.section('py-5', _sdT('Travel (Day by Day)'), [
            fx(kit.div('col-lg-8 mb-5', _sdT('Section Header'), [
                eyebrow(_sdT('Itinerary')),
                kit.h('h2', _sdT('Cappadocia Valleys and Balloons'), 'h2 fw-bold mb-2'),
                kit.p(_sdT('Four days among the fairy chimneys: valley walks, an underground city and a balloon flight at sunrise.'), 'lead text-body-secondary mb-0')
            ]), { effect: 'fade', trigger: 'view', duration: 700 }),
            kit.row(_sdT('Itinerary Row'), [
                kit.col(_sdT('Days'), [days], '8'),
                kit.col(_sdT('Trip at a Glance'), [glance], '4')
            ], 'g-4 g-lg-5 align-items-start')
        ]);
    };

    // ── Destination guide ───────────────────────────────────────────────
    // A season tile: its icon, its name and how good it is for going.
    var season = function (icon, title, verdict, best) {
        return kit.tag('li', best ? 'pg-travel-best' : '', title, [
            kit.icon(icon, 'fs-4 d-block mb-1'),
            kit.h('span', title, 'd-block fw-semibold'),
            kit.div('d-flex align-items-center justify-content-center gap-1 small', _sdT('Verdict'), best ? [
                kit.icon('bi-check2-circle', 'flex-shrink-0'),
                kit.h('span', verdict)
            ] : [
                kit.h('span', verdict)
            ])
        ]);
    };
    var buildDestination = function () {
        var region = kit.div('badge rounded-pill text-bg-primary d-inline-flex align-items-center gap-1 position-absolute top-0 start-0 m-3', _sdT('Region'), [
            kit.icon('bi-sun'),
            kit.h('span', _sdT('Mediterranean'))
        ]);
        var picture = kit.tag('figure', 'position-relative m-0', _sdT('Picture'), [
            kit.div('ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', _sdT('Picture Frame'), [
                kit.img('travel-cappadocia', 1200, 900, _sdT('Balloons over the fairy chimneys of Cappadocia at sunrise'), 'w-100 h-100 object-fit-cover')
            ]),
            region
        ]);
        var words = kit.col(_sdT('Destination'), [
            eyebrow(_sdT('Türkiye')),
            kit.h('h2', _sdT('Cappadocia'), 'h2 fw-bold mb-2'),
            kit.p(_sdT('Fairy chimneys, cave hotels and balloons over the valleys at sunrise.'), 'lead text-body-secondary mb-4'),
            kit.h('h3', _sdT('Best time to go'), 'h6 fw-bold mb-2'),
            fx(kit.tag('ul', 'pg-travel-seasons mb-4', _sdT('Seasons'), [
                season('bi-flower1', _sdT('Spring'), _sdT('Best time'), true),
                season('bi-sun', _sdT('Summer'), _sdT('Hot days'), false),
                season('bi-tree', _sdT('Autumn'), _sdT('Best time'), true),
                season('bi-snow', _sdT('Winter'), _sdT('Snow and quiet'), false)
            ]), { effect: 'slide-up', trigger: 'view', stagger: 80 }),
            kit.tag('dl', 'pg-travel-glance row row-cols-1 row-cols-sm-2 g-0 mb-4', _sdT('Facts'), [
                fact(_sdT('Getting there'), _sdT('A short flight to Kayseri or Nevşehir')),
                fact(_sdT('Language'), _sdT('Turkish')),
                fact(_sdT('Currency'), _sdT('Turkish lira')),
                fact(_sdT('Entry visa'), _sdT('Many travellers apply online before they go'))
            ]),
            kit.div('d-flex flex-wrap gap-2', _sdT('Buttons'), [
                kit.link(_sdT('See the tours here'), '#', 'btn btn-primary rounded-pill px-4', _sdT('Tours Link')),
                kit.link(_sdT('Plan a Tailor-made Trip'), '#', 'btn btn-outline-primary rounded-pill px-4', _sdT('Enquiry Link'))
            ])
        ], '6');
        return kit.section('py-5 bg-body-tertiary', _sdT('Travel (Destination Guide)'), [
            kit.row(_sdT('Destination Row'), [
                kit.col(_sdT('Picture'), [
                    fx(picture, { effect: 'fade', trigger: 'view', duration: 900 })
                ], '6'),
                words
            ], 'g-4 g-lg-5 align-items-center')
        ]);
    };

    return {
        prefix: 'travel-',
        group: { title: _sdT('Travel'), icon: 'bi-airplane' },
        items: [
            { label: _sdT('Travel: Day by Day'),        type: 'component', icon: 'bi-signpost-split', extra: { componentType: 'travel-itinerary' } },
            { label: _sdT('Travel: Destination Guide'), type: 'component', icon: 'bi-geo-alt',        extra: { componentType: 'travel-destination' } }
        ],
        build: function (ctype) {
            if (ctype === 'travel-itinerary') return buildItinerary();
            if (ctype === 'travel-destination') return buildDestination();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
