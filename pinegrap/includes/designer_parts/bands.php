<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'bands', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The spacing bands and their handles on the selected element over the
 * canvas, drawn on the first selection that can show them: their geometry
 * (window.PGCanvasBands), the first-use tips' state (window.PGDesignerTour),
 * then the part. What the Spacing box writes (window.PGSpacingBox) comes
 * with the part 'spacing', which the core loads first.
 *
 * Memory: docs/memory/designer/designer-canvas-guides-bands.md
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
		'assets/js/pg_canvas_bands.js',
		'assets/js/pg_designer_tour.js',
		'assets/js/style_designer_bands.js',
	),
	'when'    => 'always',
);
