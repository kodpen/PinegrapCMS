<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'guides', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The canvas guides: Measure, the distances drawn while Alt is held with
 * one element selected. Their pure geometry (window.PGCanvasGuides), then
 * the part.
 *
 * Memory: docs/memory/designer/designer-canvas-guides-measure.md
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
		'assets/js/pg_canvas_guides.js',
		'assets/js/style_designer_guides.js',
	),
	'when'    => 'always',
);
