<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'spacing', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The spacing box of Style › Spacing, the corner box of Style › Border and
 * corner, and a length per screen size, drawn when the section is first
 * shown: their pure mappings (window.PGSpacingBox, window.PGCornerBox,
 * window.PGSizeStyle), then the part.
 *
 * Memory: docs/memory/designer/designer-spacing-box.md
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
		'assets/js/pg_spacing_box.js',
		'assets/js/pg_corner_box.js',
		'assets/js/pg_size_style.js',
		'assets/js/style_designer_spacing.js',
	),
	'when'    => 'always',
);
