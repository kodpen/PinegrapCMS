<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'publishcheck', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The Publish check: each ticked page's result in the Publish sheet, its
 * findings in five groups, a finding's element selected. Its pure finding
 * shape (window.PgPublishFindings) and Content check
 * (window.PgPublishContent), then the part.
 *
 * Memory: docs/memory/designer/designer-publish-check-results.md
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
		'assets/js/pg_publish_findings.js',
		'assets/js/pg_publish_content.js',
		'assets/js/style_designer_publishcheck.js',
	),
	'when'    => 'publishcheck',
);
