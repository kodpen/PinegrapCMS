<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'similar', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * "Select similar": every element like the selected one chosen with one
 * press, and the line above the Options panel with its two lists. The
 * matcher (pg_select_similar.js) runs before the part that draws it.
 *
 * Memory: docs/memory/designer/designer-select-similar-part.md
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
		'assets/js/pg_select_similar.js',
		'assets/js/style_designer_similar.js',
	),
	'when'    => 'always',
);
