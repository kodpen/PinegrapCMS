<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'shape', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * "Clip shape" and "Mask" of Style › Shadow and effect › More: the model
 * (window.PGClipShape), then the rows, the canvas while a slider is
 * dragged and the dashed outline of the element's whole box.
 *
 * Memory: docs/memory/designer/designer-options-shape.md
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
		'assets/js/pg_clip_shape.js',
		'assets/js/style_designer_shape.js',
	),
	'when'    => 'always',
);
