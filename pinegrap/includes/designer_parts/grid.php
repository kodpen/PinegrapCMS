<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'grid', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * A grid box's Columns and Rows under Advanced › Layout, a value per screen
 * size, drawn when the section is first shown: the track values
 * (window.PGGridTracks), then the part. The per size writer
 * (window.PGSizeStyle) comes with the part 'spacing', which the editor
 * loads first.
 *
 * Memory: docs/memory/designer/designer-grid-columns-rows.md
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

return array(
	'sources' => array(
		'assets/js/pg_grid_tracks.js',
		'assets/js/style_designer_grid.js',
	),
	'when'    => 'always',
);
