<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'focus', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The Focus control of an Image's and a background picture's options: a
 * picture's focus point dragged on the whole picture, with the cut on each
 * screen, drawn when first shown. What it shows (window.PGImageFocus), then
 * the part.
 *
 * Memory: docs/memory/designer/image-focus-control.md
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
		'assets/js/pg_image_focus.js',
		'assets/js/style_designer_focus.js',
	),
	'when'    => 'always',
);
