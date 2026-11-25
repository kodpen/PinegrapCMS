<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'a11yview', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The canvas's Accessibility view: the card in the canvas's corner and its
 * marks for tab order, headings and names, worked out by the pure reader.
 *
 * Memory: docs/memory/designer/accessibility-reader.md
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
		'assets/js/pg_a11y_reader.js',
		'assets/js/style_designer_a11yview.js',
	),
	'when'    => 'always',
);
