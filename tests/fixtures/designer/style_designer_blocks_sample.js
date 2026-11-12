/**
 * Pinegrap - Enterprise Website Platform
 *
 * A sample block family file for tests/designer_block_families_test.php: the
 * shape of assets/js/style_designer_blocks_<family>.js. It registers one
 * palette group with two blocks, their builders and the block rules they
 * need; one of them asks for a core rule as well.
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
        'pg-sample-band':  '.pg-sample-band { min-height: 40vh; }',
        'pg-sample-tile':  '.pg-sample-tile { border-radius: 1.5rem; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'sample-band':     ['pg-sample-band', 'pg-ratio-16x9'],
        'sample-tiles':    ['pg-sample-tile']
    };

    return {
        prefix: 'sample-',
        group: { title: _sdT('Sample Family'), icon: 'bi-box' },
        items: [
            { label: _sdT('Sample: Band'),  type: 'component', icon: 'bi-square', extra: { componentType: 'sample-band' },
              desc: _sdT('A band with a heading over a wide picture.') },
            { label: _sdT('Sample: Tiles'), type: 'component', icon: 'bi-grid',   extra: { componentType: 'sample-tiles' },
              desc: _sdT('A heading over two rounded tiles side by side.') }
        ],
        build: function (ctype) {
            if (ctype === 'sample-band') {
                return kit.section('py-5 pg-sample-band', _sdT('Sample Band'), [
                    kit.h('h2', _sdT('Sample heading'), 'fw-bold'),
                    kit.img('sample', 1200, 675, _sdT('Sample picture'), 'img-fluid pg-ratio-16x9')
                ]);
            }
            if (ctype === 'sample-tiles') {
                return kit.section('py-5', _sdT('Sample Tiles'), [
                    kit.intro(_sdT('Sample tiles'), _sdT('Two tiles side by side.')),
                    kit.row(_sdT('Tiles Row'), [
                        kit.col(_sdT('Tile {var}', 1), [kit.div('p-4 bg-body-tertiary pg-sample-tile', _sdT('Tile'), [kit.p(_sdT('First tile'))])], '6'),
                        kit.col(_sdT('Tile {var}', 2), [kit.div('p-4 bg-body-tertiary pg-sample-tile', _sdT('Tile'), [kit.p(_sdT('Second tile'))])], '6')
                    ])
                ]);
            }
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
