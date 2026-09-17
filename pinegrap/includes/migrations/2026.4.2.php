<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.4.2. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// 2026.4.2 — the release that follows 2026.4.1.
//
// One entry point, one step per subsystem. The steps are ordered, not
// independent: the keyword migration runs before the SEO step because the SEO
// step is what marks every record stale afterwards, and it has to be the last
// thing that touches those flags or the first analysis pass would read the old
// keyword values.
//
// Every step is defensive - CREATE TABLE IF NOT EXISTS, SHOW COLUMNS, SHOW
// INDEX - so an upgrade cut off halfway through can simply be run again. The
// version is only written once the whole function returns.
function upgrade_to_2026_4_2() {

	upgrade_2026_4_2_live_chat();

	upgrade_2026_4_2_dashboard_widgets();

	upgrade_2026_4_2_scheduled_task_health();

	upgrade_2026_4_2_page_keywords();

	upgrade_2026_4_2_seo_score();

	upgrade_2026_4_2_speed_signal();

	upgrade_2026_4_2_job_dispatch();
}

// Live chat.
//
// Two tables + twelve config columns. Design notes:
//  - InnoDB: the conversation row takes an UPDATE on every message and
//    poll; MyISAM's table-level lock would be wrong here from the start.
//  - Times are INT UNSIGNED unix timestamps (orders.order_date pattern) —
//    the DATE '' comparison trap cannot occur.
//  - ip_address VARCHAR(45): full IPv6 length.
//  - Deliberately few secondary indexes: a single composite index on
//    messages serves both the poll (id > since) and history reads.
//  - The schema carries both channels (backend/site) from the start; the
//    site channel is enabled in code only, no extra schema step.
//  - Attachment files go through the existing file pipeline into the files
//    table; the stored name is fully synthetic, so a non-ASCII filename
//    cannot reach the filesystem.
//
// The typing and attachment columns are declared in the CREATE TABLE rather
// than added by a later ALTER. No installation has these tables - chat has
// never shipped - so an ALTER guarded on a column of a table this same
// function just created would never fire.
//
// Every switch starts at 0 (off). Nothing changes on the site until the
// operator enables chat from the settings screen.
function upgrade_2026_4_2_live_chat() {

	db("CREATE TABLE IF NOT EXISTS chat_conversations (
		id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
		channel                ENUM('backend','site') NOT NULL DEFAULT 'site',
		status                 ENUM('open','closed') NOT NULL DEFAULT 'open',
		initiator_user_id      INT UNSIGNED NOT NULL DEFAULT 0,
		target_user_id         INT UNSIGNED NOT NULL DEFAULT 0,
		party_name             VARCHAR(100) NOT NULL DEFAULT '',
		party_email            VARCHAR(255) NOT NULL DEFAULT '',
		ip_address             VARCHAR(45)  NOT NULL DEFAULT '',
		page_url               VARCHAR(500) NOT NULL DEFAULT '',
		visitor_id             INT UNSIGNED NOT NULL DEFAULT 0,
		last_message_id        INT UNSIGNED NOT NULL DEFAULT 0,
		last_message_at        INT UNSIGNED NOT NULL DEFAULT 0,
		last_message_preview   VARCHAR(120) NOT NULL DEFAULT '',
		initiator_last_read_id INT UNSIGNED NOT NULL DEFAULT 0,
		target_last_read_id    INT UNSIGNED NOT NULL DEFAULT 0,
		initiator_last_seen    INT UNSIGNED NOT NULL DEFAULT 0,
		target_last_seen       INT UNSIGNED NOT NULL DEFAULT 0,
		initiator_typing_until INT UNSIGNED NOT NULL DEFAULT 0,
		target_typing_until    INT UNSIGNED NOT NULL DEFAULT 0,
		created_at             INT UNSIGNED NOT NULL DEFAULT 0,
		closed_at              INT UNSIGNED NOT NULL DEFAULT 0,
		closed_by              INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_target (target_user_id, status, last_message_at),
		KEY idx_initiator (initiator_user_id, status, last_message_at),
		KEY idx_channel (channel, status, last_message_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	db("CREATE TABLE IF NOT EXISTS chat_messages (
		id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
		conversation_id    INT UNSIGNED NOT NULL DEFAULT 0,
		sender_kind        ENUM('user','visitor','system') NOT NULL DEFAULT 'user',
		sender_user_id     INT UNSIGNED NOT NULL DEFAULT 0,
		body               TEXT NOT NULL,
		attachment_file_id INT UNSIGNED NOT NULL DEFAULT 0,
		attachment_kind    ENUM('none','image','file') NOT NULL DEFAULT 'none',
		attachment_name    VARCHAR(255) NOT NULL DEFAULT '',
		created_at         INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_conversation (conversation_id, id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// config exists on every installation, so these do need the per-column
	// guard. chat_widget_title empty means the language file's "Live Support".
	// The visitor file limit is deliberately fixed at one and is not a setting.
	$config_columns = array(
		'chat_enabled'              => "ALTER TABLE config ADD chat_enabled TINYINT(1) NOT NULL DEFAULT 0",
		'chat_site_enabled'         => "ALTER TABLE config ADD chat_site_enabled TINYINT(1) NOT NULL DEFAULT 0",
		'chat_operator_user_id'     => "ALTER TABLE config ADD chat_operator_user_id INT UNSIGNED NOT NULL DEFAULT 0",
		'chat_welcome_message'      => "ALTER TABLE config ADD chat_welcome_message VARCHAR(500) NOT NULL DEFAULT ''",
		'chat_offline_email'        => "ALTER TABLE config ADD chat_offline_email TINYINT(1) NOT NULL DEFAULT 1",
		'chat_captcha'              => "ALTER TABLE config ADD chat_captcha TINYINT(1) NOT NULL DEFAULT 1",
		'chat_retention_days'       => "ALTER TABLE config ADD chat_retention_days INT UNSIGNED NOT NULL DEFAULT 60",
		'chat_widget_theme'         => "ALTER TABLE config ADD chat_widget_theme VARCHAR(10) NOT NULL DEFAULT 'auto'",
		'chat_widget_color'         => "ALTER TABLE config ADD chat_widget_color VARCHAR(7) NOT NULL DEFAULT '#0d6efd'",
		'chat_widget_icon'          => "ALTER TABLE config ADD chat_widget_icon VARCHAR(20) NOT NULL DEFAULT 'chat'",
		'chat_widget_title'         => "ALTER TABLE config ADD chat_widget_title VARCHAR(100) NOT NULL DEFAULT ''",
		'chat_allow_files'          => "ALTER TABLE config ADD chat_allow_files TINYINT(1) NOT NULL DEFAULT 0",
		'chat_allow_images'         => "ALTER TABLE config ADD chat_allow_images TINYINT(1) NOT NULL DEFAULT 0",
		'chat_visitor_image_limit'  => "ALTER TABLE config ADD chat_visitor_image_limit SMALLINT UNSIGNED NOT NULL DEFAULT 5"
	);

	foreach ($config_columns as $column => $sql) {
		if (!db_item("SHOW COLUMNS FROM config LIKE '" . $column . "'")) {
			db($sql);
		}
	}
}

// Dashboard widget 18 changed from Admin Notes to File Management, so the
// column that stored the note body has nothing left reading or writing it —
// the Quill editor, its api.php action and the widget markup are all gone.
//
// Dropped rather than left in place: it is a free-text TEXT column that ends
// up in every backup and every database export for as long as it exists, and
// a note an administrator wrote to themselves is exactly the kind of content
// that should not outlive the feature that collected it.
//
// The id is deliberately reused, so nothing has to be done to
// dashboard.order_widgets — an existing "…,17,18,19,…" now points at the new
// widget in the slot the old one occupied.
//
// Guarded on SHOW COLUMNS: an install that never had the column (or an
// upgrade replayed after a partial run) must not fail here.
function upgrade_2026_4_2_dashboard_widgets() {

	if (db_item("SHOW COLUMNS FROM dashboard LIKE 'notes_widget_data'")) {
		db("ALTER TABLE dashboard DROP COLUMN notes_widget_data");
	}
}

// Scheduled-task health. The cron entries live outside the software — a
// crontab, Windows Task Scheduler, a hosting control panel — so nothing in the
// panel can ask whether a job is scheduled. Each job now records that it
// FINISHED, and the dashboard reads those timestamps: a job that used to
// finish and no longer does is a job whose schedule has stopped.
//
// A table rather than one config column per job. Nine jobs record here today
// and the shape repeats exactly, so adding the tenth should not need an ALTER.
// job_name is the primary key, which is what makes the recording write a
// single INSERT ... ON DUPLICATE KEY UPDATE with no read first.
//
// Starts empty on purpose. Every job reads as "never ran" until its next run,
// and the dashboard shows that in grey rather than red — an installation that
// was upgraded five minutes ago has not got a broken cron, it has no history.
function upgrade_2026_4_2_scheduled_task_health() {

	db("CREATE TABLE IF NOT EXISTS cron_runs (
		job_name    VARCHAR(64) NOT NULL,
		last_run_at INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (job_name)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// A page carried two keyword fields that did different halves of one job.
// page_meta_keywords fed the tag cloud and the search index; page_search_keywords
// promoted the page in site search. An operator had to fill in both to get the
// behaviour they expected from either, and the meta keywords field lost the only
// output of its own when the <meta name="keywords"> tag was dropped.
//
// Everything on a page now reads page_search_keywords. This merges the old meta
// keywords into it first, so that no site loses a tag cloud entry or a search
// promotion the moment the reads move over, then rebuilds the page rows in
// tag_cloud_keywords from the merged value.
//
// Only item_type = 'page' rows are rebuilt. Product and product group rows in
// that table are written by the search results page cross reference and are fed
// by their own keywords column, which is not changing.
//
// The work is done a page at a time: merge, then rewrite that one page's tag
// cloud rows. That matters on a large site. A single DELETE across the whole
// table followed by a long rebuild leaves the tag cloud empty for as long as the
// rebuild runs, and a server with a hard FastCGI or proxy timeout can cut the
// request off in the middle of it - at which point the version was never bumped,
// so the next attempt would start over from an emptied table. Per page, every
// step is complete on its own and repeating the upgrade is harmless.
//
// Runs before the SEO step, which is what marks every record stale. Reversing
// the two would leave the first analysis pass scoring the pre-merge keywords.
function upgrade_2026_4_2_page_keywords() {

	$pages = db_items(
		"SELECT page_id, page_search, page_search_keywords, page_meta_keywords
		FROM page
		WHERE (page_meta_keywords != '') OR (page_search_keywords != '')");

	foreach ($pages as $page) {

		// Search keywords are listed first so an operator's existing promotion
		// terms keep their order and the meta keywords are appended after them.
		$merged = merge_keyword_lists($page['page_search_keywords'], $page['page_meta_keywords']);

		if ($merged != $page['page_search_keywords']) {

			db(
				"UPDATE page
				SET page_search_keywords = '" . e($merged) . "'
				WHERE page_id = '" . e($page['page_id']) . "'");
		}

		// Rewrite this page's tag cloud rows from the merged list. They were
		// written from page_meta_keywords until now, so all of them are stale.
		//
		// The rebuild goes through the same function the rest of the software
		// uses rather than inserting directly, so the rows end up exactly as a
		// later edit of this page will expect to find them. That function diffs
		// the new list against the old one, and it decides two keywords are the
		// same by comparing the raw strings - a rebuild that deduplicated more
		// aggressively than that would leave rows it could no longer account
		// for, and the next edit would delete the page out of the cloud.
		db(
			"DELETE FROM tag_cloud_keywords
			WHERE (item_id = '" . e($page['page_id']) . "') AND (item_type = 'page')");

		update_tag_cloud_keywords_for_page($page['page_id'], $page['page_search'], $merged, 0, '');
	}

	// Clear rows left behind for pages that carry no keywords or are no longer in
	// the site search. Earlier versions did not refresh this table on every edit
	// that could have emptied it, so some sites have entries pointing at pages
	// that have not been taggable for a while.
	db(
		"DELETE FROM tag_cloud_keywords
		WHERE (item_type = 'page')
		AND (item_id NOT IN (
			SELECT page_id FROM page WHERE (page_search = '1') AND (page_search_keywords != '')))");
}

// SEO score engine.
//
// page, products and product_groups have carried seo_score / seo_analysis /
// seo_analysis_current since the LiveSite era, but nothing ever wrote them: the
// analyzer itself never shipped, only the schema and the invalidation writes
// (edit_page.php, save_region_content.php, edit_product_group.php set
// seo_analysis_current = 0) survived. The engine in seo.php now fills them.
//
// The score has four components and each keeps its own column, so that a
// record whose speed changed is not made to re-run the structure pass:
//
//   seo_meta_score   - database fields: title, description, keywords, content.
//   seo_struct_score - the rendered markup: heading order, alt text, nesting.
//   seo_link_score   - internal links, and how far the page is from home.
//   seo_speed_score  - measured server response time, from perf_stats.
//   seo_score        - the composed number the list screens sort on.
//
// The three component columns are NULL until their pass has run once, which is
// not the same as scoring zero: a record nobody has rendered yet keeps its meta
// score rather than being punished for a pass that has not happened.
//
// Two supporting tables. seo_issue holds findings that are unbounded in number
// and several per record, which neither the seo_flags bitmask nor the
// seo_analysis JSON can filter on. seo_link holds one row per link per source
// record, rewritten per record the same way seo_issue is; nothing in it is
// fetched over HTTP - a link is resolved against the database in the order
// router.php dispatches (file, then short link, then page), so "broken" here
// means the router would not find a destination either. seo_depth is on page
// only because clicks-from-home is a property of a page, not of a product.
//
// seo_flags is a bitmask of failed checks, one bit per problem, so "pages in
// the sitemap with no title" is a single indexless AND on an INT instead of a
// JSON scan. idx_seo_score exists because the list screens sort on it.
function upgrade_2026_4_2_seo_score() {

	db("CREATE TABLE IF NOT EXISTS seo_issue (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		entity_type  ENUM('page','product','product_group') NOT NULL,
		entity_id    INT UNSIGNED NOT NULL,
		code         VARCHAR(48) NOT NULL,
		severity     ENUM('error','warning','notice') NOT NULL DEFAULT 'notice',
		occurrences  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
		source       VARCHAR(32) NOT NULL DEFAULT '',
		detail       VARCHAR(255) NOT NULL DEFAULT '',
		KEY idx_entity (entity_type, entity_id),
		KEY idx_code (code, severity)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	db("CREATE TABLE IF NOT EXISTS seo_link (
		id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		from_type ENUM('page','product','product_group') NOT NULL,
		from_id   INT UNSIGNED NOT NULL,
		to_type   ENUM('page','product','product_group','file','short_link','external','unknown') NOT NULL,
		to_id     INT UNSIGNED NOT NULL DEFAULT 0,
		href      VARCHAR(512) NOT NULL,
		anchor    VARCHAR(160) NOT NULL DEFAULT '',
		rel       VARCHAR(64) NOT NULL DEFAULT '',
		KEY idx_from (from_type, from_id),
		KEY idx_to (to_type, to_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Guarded per column rather than per group: a development database that
	// stopped part way through an earlier form of this upgrade still gets the
	// columns it is missing instead of none of them.
	$seo_columns = array(
		'seo_flags'             => "ADD COLUMN seo_flags INT UNSIGNED NOT NULL DEFAULT 0",
		'seo_checked_at'        => "ADD COLUMN seo_checked_at INT UNSIGNED NOT NULL DEFAULT 0",
		'seo_meta_score'        => "ADD COLUMN seo_meta_score TINYINT UNSIGNED NOT NULL DEFAULT 0",
		'seo_struct_score'      => "ADD COLUMN seo_struct_score TINYINT UNSIGNED DEFAULT NULL",
		'seo_struct_current'    => "ADD COLUMN seo_struct_current TINYINT UNSIGNED NOT NULL DEFAULT 0",
		'seo_struct_checked_at' => "ADD COLUMN seo_struct_checked_at INT UNSIGNED NOT NULL DEFAULT 0",
		'seo_link_score'        => "ADD COLUMN seo_link_score TINYINT UNSIGNED DEFAULT NULL",
		'seo_speed_score'       => "ADD COLUMN seo_speed_score TINYINT UNSIGNED DEFAULT NULL"
	);

	foreach (array('page', 'products', 'product_groups') as $table) {

		foreach ($seo_columns as $column => $clause) {

			if (!db_item("SHOW COLUMNS FROM `" . $table . "` LIKE '" . $column . "'")) {
				db("ALTER TABLE `" . $table . "` " . $clause);
			}
		}

		if (!db_item("SHOW INDEX FROM `" . $table . "` WHERE Key_name = 'idx_seo_score'")) {
			db("ALTER TABLE `" . $table . "` ADD INDEX idx_seo_score (seo_score)");
		}

		// Seed the meta score from the legacy value for records the old
		// analyzer had already marked current, so the screens show a real
		// number instead of a zero until the first pass completes.
		//
		// This has to come before the reset below, not after. The two used to
		// be separate releases with weeks of live traffic between them, which
		// is what put rows back into seo_analysis_current = 1; run back to back
		// the reset would leave this UPDATE matching nothing at all.
		db("UPDATE `" . $table . "` SET seo_meta_score = seo_score WHERE seo_analysis_current = 1");

		// Everything is stale now: the engine is new, the keyword field it
		// reads was just merged, and no record has been through the structure,
		// link or speed passes at all. Until the first run the screens report
		// the score as not yet calculated rather than a false 0.
		db("UPDATE `" . $table . "` SET seo_analysis_current = 0");
	}

	if (!db_item("SHOW COLUMNS FROM page LIKE 'seo_depth'")) {
		db("ALTER TABLE page ADD COLUMN seo_depth TINYINT UNSIGNED DEFAULT NULL");
	}
}

// The speed component's two sources.
//
// perf_stats has been measuring every request for a while and the SEO score was
// ignoring it. What stopped the two from meeting is that perf_stats groups by
// URL and the score is per record, and turning one into the other by parsing
// the path is the trap this codebase has already paid for: '/katalog/urun-adi'
// resolves to the catalog detail template, not to the product, so every product
// would be filed under one page.
//
// The answer is not to parse the label afterwards but to write the identity
// while the request still knows it. perf_monitor_shutdown() runs after the
// response is sent, in the same process that just rendered the record, so the
// resolved item is still in memory there.
//
// The bucket key gains the record, because one label is not always one record:
// '[home]' is shared by every page carrying page_home, and a catalogue reached
// as '/urun-detay?pid=5' has its query string stripped. So existing rows stop
// being written to and age out within the retention window, and a label behind
// which several records live now produces one bucket per record per hour.
//
// On a large catalogue addressed that way this is a real increase - the same
// cardinality visitor_content_hourly already carries, but perf_stats has a row
// cap (PERF_MONITOR_MAX_ROWS, default 200000) that visitor_content_hourly does
// not. A shop with tens of thousands of SKUs behind one label should raise it,
// or the retention sweep will trim the window the speed score reads from.
//
// The second index is for the Impact column on the Pages screen, which joins
// visitor_content_hourly on page_id - a column that table had no index on,
// because the rollup was designed to be read by date and by item like every
// report before this one. Without it the join is a thirty-day range scan and a
// temporary table on every list render: exactly the cost the rollup exists to
// remove, quietly added back to the busiest screen. The product side already
// had idx_item and is unaffected.
function upgrade_2026_4_2_speed_signal() {

	if (db_item("SHOW TABLES LIKE 'perf_stats'")) {

		if (!db_item("SHOW COLUMNS FROM perf_stats LIKE 'entity_type'")) {
			db("ALTER TABLE perf_stats ADD COLUMN entity_type VARCHAR(16) NOT NULL DEFAULT ''");
		}

		if (!db_item("SHOW COLUMNS FROM perf_stats LIKE 'entity_id'")) {
			db("ALTER TABLE perf_stats ADD COLUMN entity_id INT UNSIGNED NOT NULL DEFAULT 0");
		}

		if (!db_items("SHOW INDEX FROM perf_stats WHERE Key_name = 'idx_entity'")) {
			db("ALTER TABLE perf_stats ADD INDEX idx_entity (entity_type, entity_id)");
		}
	}

	if (db_item("SHOW TABLES LIKE 'visitor_content_hourly'")) {

		if (!db_items("SHOW INDEX FROM visitor_content_hourly WHERE Key_name = 'idx_page_date'")) {
			db("ALTER TABLE visitor_content_hourly ADD INDEX idx_page_date (page_id, stat_date)");
		}
	}
}

// Optional dispatch of the other scheduled jobs from the general job.
//
// Every column ships off or empty. The e-mail campaign job in particular sends
// every campaign whose status is "Ready to Send" the moment it runs, so an
// upgrade that switched dispatching on by itself would post a site's old,
// abandoned campaigns. Nothing here changes behaviour until an operator makes
// a selection on the settings screen.
//
// The lock column is separate from the selection so that a run in progress and
// the operator's choice never overwrite each other.
function upgrade_2026_4_2_job_dispatch() {

	$config_columns = array(
		'job_dispatch_enabled'    => "ALTER TABLE config ADD job_dispatch_enabled TINYINT(1) NOT NULL DEFAULT 0",
		'job_dispatch'            => "ALTER TABLE config ADD job_dispatch VARCHAR(255) NOT NULL DEFAULT ''",
		'job_dispatch_lock_until' => "ALTER TABLE config ADD job_dispatch_lock_until INT UNSIGNED NOT NULL DEFAULT 0"
	);

	foreach ($config_columns as $column => $sql) {
		if (!db_item("SHOW COLUMNS FROM config LIKE '" . $column . "'")) {
			db($sql);
		}
	}
}
