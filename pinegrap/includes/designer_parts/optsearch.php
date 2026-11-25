<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'optsearch', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The Options panel's search: a row of any tab found by its label,
 * section, CSS property or class prefix, listed with its path. Its pure
 * fold-and-match module (window.PGOptionsSearch), then the part.
 *
 * Memory: docs/memory/designer/designer-options-search.md
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
		'assets/js/pg_options_search.js',
		'assets/js/style_designer_optsearch.js',
	),
	'when'    => 'always',
);
