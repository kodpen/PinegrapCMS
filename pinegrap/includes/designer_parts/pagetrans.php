<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'pagetrans', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The design's "Page transition" (Settings, Theme) and "Keep across
 * pages" under an element's Interactions: the values (window.PGPageTrans),
 * then the rows.
 *
 * Memory: docs/memory/designer/designer-options-page-transition.md
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
		'assets/js/pg_page_transition.js',
		'assets/js/style_designer_pagetrans.js',
	),
	'when'    => 'always',
);
