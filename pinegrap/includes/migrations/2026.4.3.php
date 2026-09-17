<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.4.3. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// 2026.4.3 - per-page search engine indexing.
//
// A version of its own rather than another step under 2026.4.2. That number has
// already been run - the installation the work was done on is past it - and the
// upgrade loop only calls a version whose key is greater than the one in the
// database. A step added to a version that has been run is a step that never
// runs.
//
// A page could be kept out of sitemap.xml but there was no way to tell a
// crawler to stay away from it. Two cases needed one: a page whose content is
// rendered as a widget somewhere else (a "latest three posts" list dropped in
// the footer) is a duplicate of a page that is already indexed, and the site
// search page multiplies into one URL per query while indexing none of them.
//
// Two TINYINT columns rather than one string. noindex is the switch the rest of
// the software branches on - sitemap generation, robots.txt, the page screen -
// and an integer column keeps those tests the same shape as the sitemap column
// right next to it. nofollow only qualifies the directive and is forced back to
// 0 whenever noindex is off, so the pair has three meaningful states and no way
// to store a fourth.
//
// No index on either column. The sitemap and robots.txt builders read the whole
// page table once per request and pages are counted in hundreds; an index here
// would be paid for on every page save and never used.
//
// Both default to 0, which is exactly today's behaviour, so there is nothing to
// backfill.
function upgrade_to_2026_4_3() {

	$page_columns = array(
		'noindex'  => "ALTER TABLE page ADD noindex TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER sitemap",
		'nofollow' => "ALTER TABLE page ADD nofollow TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER noindex"
	);

	foreach ($page_columns as $column => $sql) {
		if (!db_item("SHOW COLUMNS FROM page LIKE '" . $column . "'")) {
			db($sql);
		}
	}
}
