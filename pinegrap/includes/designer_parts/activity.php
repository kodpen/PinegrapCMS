<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'activity', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * History's "Activity" tab: the design's events, newest first, under their
 * day, the three choosers (person, page, kind) and the version rows that
 * open Compare. The pure rules (window.PgDesignerActivity), then the tab.
 *
 * Memory: docs/memory/designer/designer-activity-tab.md
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
		'assets/js/pg_designer_activity.js',
		'assets/js/style_designer_activity.js',
	),
	'when'    => 'always',
);
