<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'findreplace', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * "Find and Replace" (Ctrl+F, the canvas bar's Find): the window with its
 * three scopes, This page, All pages and Code files, the list of what would
 * change per page before "Replace All", and one undo step a page. The
 * matcher (pg_find_replace.js) runs before the part that draws it.
 *
 * Memory: docs/memory/designer/designer-find-replace-scopes.md
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
		'assets/js/pg_find_replace.js',
		'assets/js/style_designer_findreplace.js',
	),
	'when'    => 'always',
);
