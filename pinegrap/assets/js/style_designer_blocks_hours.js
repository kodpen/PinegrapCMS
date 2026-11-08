/**
 * Pinegrap - Enterprise Website Platform
 *
 * General opening-hours blocks the core's Hours & Locations group lacks.
 * Seasonal hours: one table with a column per season and a row per day, for
 * a venue whose hours change with the season (a museum, a campsite, a
 * marina, a pool, a ferry), with the notes a visitor asks about under it.
 * The core's Hours: Card and Hours: Location keep the single-week layouts.
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
        'pg-hours-table': '.pg-hours-table { font-variant-numeric: tabular-nums; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'hours-seasons': ['pg-hours-table']
    };

    // ── Seasonal hours ──────────────────────────────────────────────────
    var cell = function (tag, kids, cls, scope) {
        var attrs = scope ? [{ name: 'scope', value: scope }] : null;
        return kit.tag(tag, cls || '', tag === 'th' ? _sdT('Header Cell') : _sdT('Cell'), kids, attrs);
    };
    // A season's column head: its icon, its name and the months it runs.
    var season = function (icon, name, months) {
        return cell('th', [
            kit.div('d-flex align-items-center gap-2', _sdT('Season'), [
                kit.icon(icon, 'text-primary fs-5'),
                kit.div('', _sdT('Season Name'), [
                    kit.h('span', name, 'd-block fw-bold'),
                    kit.h('span', months, 'd-block small fw-normal text-body-secondary')
                ])
            ])
        ], 'text-nowrap', 'col');
    };
    // A day's row; a closed day says so in a word, never by a dash alone.
    var day = function (name, summer, winter) {
        var time = function (text) {
            var closed = text === _sdT('Closed');
            return cell('td', [kit.h('span', text, closed ? 'text-body-secondary' : 'fw-semibold')], 'text-nowrap');
        };
        return kit.tag('tr', '', name, [
            cell('th', [kit.h('span', name)], 'fw-normal', 'row'),
            time(summer),
            time(winter)
        ]);
    };
    var note = function (n, icon, title, text) {
        return kit.col(_sdT('Note {var}', n), [
            kit.div('d-flex gap-3 h-100 p-3 rounded-3 bg-body', _sdT('Note Card'), [
                kit.icon(icon, 'fs-4 text-primary flex-shrink-0'),
                kit.div('', _sdT('Note Copy'), [
                    kit.h('h3', title, 'h6 fw-bold mb-1'),
                    kit.p(text, 'small text-body-secondary mb-0')
                ])
            ])
        ], '4');
    };
    var buildSeasons = function () {
        var table = kit.tag('table', 'table align-middle mb-0 pg-hours-table', _sdT('Hours Table'), [
            kit.tag('caption', 'visually-hidden', _sdT('Table Caption'), [kit.h('span', _sdT('Opening hours in summer and in winter'))]),
            kit.tag('thead', '', _sdT('Table Head'), [
                kit.tag('tr', '', _sdT('Head Row'), [
                    cell('th', [kit.h('span', _sdT('Day'), 'visually-hidden')], '', 'col'),
                    season('bi-sun', _sdT('Summer'), _sdT('April – October')),
                    season('bi-snow', _sdT('Winter'), _sdT('November – March'))
                ])
            ]),
            kit.tag('tbody', '', _sdT('Table Body'), [
                day(_sdT('Monday'), _sdT('Closed'), _sdT('Closed')),
                day(_sdT('Tuesday – Friday'), '09:00 – 19:00', '10:00 – 17:00'),
                day(_sdT('Saturday'), '09:00 – 20:00', '10:00 – 18:00'),
                day(_sdT('Sunday'), '10:00 – 18:00', '10:00 – 16:00'),
                day(_sdT('Public holidays'), '10:00 – 16:00', _sdT('Closed'))
            ])
        ]);
        var notes = kit.row(_sdT('Notes Row'), [
            note(1, 'bi-door-open', _sdT('Last entry'), _sdT('Last entry is thirty minutes before closing.')),
            note(2, 'bi-calendar-x', _sdT('Closures'), _sdT('Closed days are posted on the front page a week ahead.')),
            note(3, 'bi-people', _sdT('Groups'), _sdT('Groups of ten or more book a time in advance.'))
        ], 'mt-1');
        notes.props._fx = { effect: 'slide-up', trigger: 'view', stagger: 90 };
        return kit.section('py-5 bg-body-tertiary', _sdT('Hours (Seasons)'), [
            kit.div('d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', _sdT('Section Header'), [
                kit.div('', _sdT('Section Title'), [
                    kit.h('h2', _sdT('Opening hours by season'), 'fw-bold mb-1'),
                    kit.p(_sdT('Longer days in summer, shorter ones in winter.'), 'text-body-secondary mb-0')
                ]),
                kit.link(_sdT('Get Directions'), '#', 'btn btn-primary', _sdT('Directions Link'))
            ]),
            kit.div('table-responsive rounded-4 border bg-body p-2 p-md-3 mb-4', _sdT('Table Wrap'), [table], kit.region(_sdT('Opening hours by season'))),
            notes
        ]);
    };

    return {
        prefix: 'hours-',
        items: [
            { label: _sdT('Hours: Seasons'), type: 'component', icon: 'bi-calendar3-range', extra: { componentType: 'hours-seasons' } }
        ],
        build: function (ctype) {
            if (ctype === 'hours-seasons') return buildSeasons();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
