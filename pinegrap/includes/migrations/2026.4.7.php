<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.4.7. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// 2026.4.7 - the release that follows 2026.4.6.
//
// One entry point, one step per subsystem, called in the order the work was
// done. The numbers in the comments are labels only and follow the ranges
// 2026.4.6 used (general work from 7.1, ERP 7.58-7.69 and from 7.90, API
// 7.70-7.79, the workspace 7.80-7.89); the order of the calls is what runs.
function upgrade_to_2026_4_7() {

	upgrade_2026_4_7_page_drafts();             // 7.1

}

// Drafts of visual pages (2026.4.7, 7.1; pg_page_draft_*() in
// includes/fn/designer.php). A page taken off the site is moved into one
// private folder, so every reader that already respects folder access - the
// router, the site map, the site search - keeps visitors out while an
// administrator can still open it. The folder is created on first use and
// only its id is kept in config, the way the recycle bin keeps its own.
//
// page_drafts remembers the folder a draft goes back to when it is published
// again, with when and by whom it was taken off the site. A small table of
// its own rather than a column on `page`: that table carries the LONGTEXT
// page trees, and adding a column rebuilds it on every server that cannot
// alter it in place.
function upgrade_2026_4_7_page_drafts() {

	install_add_column('config', 'draft_folder_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_create_table('page_drafts', "CREATE TABLE page_drafts (
		page_id    INT UNSIGNED NOT NULL,
		folder_id  INT UNSIGNED NOT NULL DEFAULT 0,
		drafted_at INT UNSIGNED NOT NULL DEFAULT 0,
		drafted_by INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (page_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Pages of the Visual Page Editor can be kept as drafts: a draft is off the site for visitors and stays open to the administrators.');

}
