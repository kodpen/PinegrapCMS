<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'compare', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The Compare window: two versions of a page (the page now, the page on the
 * site, any revision) as two pictures under a slider handle or side by side,
 * and their markup's line diff. The change reader (window.PgCompareChanges),
 * the Look tab's pure rules (window.PgCompareLook), then the part.
 *
 * Memory: docs/memory/designer/designer-compare-look-tab.md
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
		'assets/js/pg_compare_changes.js',
		'assets/js/pg_compare_look.js',
		'assets/js/style_designer_compare.js',
	),
	'when'    => 'always',
);
