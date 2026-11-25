<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'review', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * "Only what I changed": the list of the changed rows of all three Options
 * tabs with their reset arrows and "Reset all". Its pure list and reset
 * loop (window.PGOptionsReview), then the part.
 *
 * Memory: docs/memory/designer/designer-options-review.md
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
		'assets/js/pg_options_review.js',
		'assets/js/style_designer_review.js',
	),
	'when'    => 'always',
);
