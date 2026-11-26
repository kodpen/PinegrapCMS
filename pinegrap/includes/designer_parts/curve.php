<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'curve', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The easing curve editor under an Easing row (Transition, Interactions):
 * the curve's numbers (window.PGCurve), then the editor.
 *
 * Memory: docs/memory/designer/designer-options-curve.md
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
		'assets/js/pg_easing_curve.js',
		'assets/js/style_designer_curve.js',
	),
	'when'    => 'always',
);
