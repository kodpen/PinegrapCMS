<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.4.8. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// 2026.4.8 - the release that follows 2026.4.7.
//
// One entry point, one step per subsystem, called in the order the work was
// done. The numbers in the comments are labels only and follow the ranges
// 2026.4.7 used (general work from 8.1, ERP 8.58-8.69 and from 8.90, API
// 8.70-8.79, the workspace 8.80-8.89); the order of the calls is what runs.
function upgrade_to_2026_4_8() {

	upgrade_2026_4_8_ai_license();              // 8.80
	upgrade_2026_4_8_scheduled_messages();      // 8.81
	upgrade_2026_4_8_channel_shares();          // 8.82
	upgrade_2026_4_8_threads();                 // 8.83
	upgrade_2026_4_8_bulk_changes();            // 8.84

	upgrade_2026_4_8_perf_queries();            // 8.15

	upgrade_2026_4_8_innodb_orders();           // 8.10

	upgrade_2026_4_8_innodb_products();         // 8.11

	upgrade_2026_4_8_innodb_people();           // 8.12

	upgrade_2026_4_8_innodb_site();             // 8.13

	upgrade_2026_4_8_innodb_search();           // 8.14

}

// Pinegrap AI works with the site's subscription key (2026.4.8, 8.80;
// includes/workspace/ai.php). Pinegrap AI is part of Pinegrap Premium: the
// gateway in front of the model is sent config.subscription_key, the key the
// General settings keep, and the separate key the Pinegrap AI card used to
// store (config.ws_ai_license, encrypted) is dropped. What the gateway last
// said about a key (ws_ai_license_state, _checked, _expires) stays and now
// follows the subscription key; it is cleared so the key is asked about
// afresh.
function upgrade_2026_4_8_ai_license() {

	if (install_drop_column('config', 'ws_ai_license') && install_column_exists('config', 'ws_ai_license_state')) {
		db("UPDATE config SET ws_ai_license_state = '', ws_ai_license_checked = 0, ws_ai_license_expires = 0");
	}

	install_note('Workspace: Pinegrap AI works with the subscription key under Settings › General; it no longer has a licence key of its own.');

}

// Scheduled messages and greetings in the workspace (2026.4.8, 8.81;
// includes/workspace/scheduled.php, scheduled_messages.php).
//
// ws_scheduled_actions.kind says what a row is: 'action' (every row before
// this step: a scheduled action, staff's) or 'message' (one message written
// in a channel's writing box to be posted at a time, by anybody in the team,
// seen by its writer alone until it is posted and deleted then). idx_kind
// serves each writer's list.
//
// ws_scheduled_queue.context carries what a start is about when something
// other than another action wrote it: the join rule queues an action for
// each person who joins a channel, as JSON {newcomer, channel_id}.
function upgrade_2026_4_8_scheduled_messages() {

	install_add_column('ws_scheduled_actions', 'kind', "ENUM('action','message') NOT NULL DEFAULT 'action' AFTER name");
	install_add_index('ws_scheduled_actions', 'idx_kind', "INDEX idx_kind (kind, created_by, status)");
	install_add_column('ws_scheduled_queue', 'context', "TEXT NULL");

	install_note('Workspace: a message can be scheduled from the writing box by anybody in the team, and a scheduled action can greet the people who join a channel.');

}

// A channel of the team shared with a guest (2026.4.8, 8.82;
// includes/workspace/guests.php). A guest was always the guest of a room of
// their own (ws_channels.kind 'guest'); a ws_guests row may now belong to a
// public or private channel of the team as well, which is the channel shared
// with them through the same kind of link. ws_guests.access says what they
// may do there: 'write' (every guest before this step: read, write, react,
// vote, tick) or 'read' (read the conversation only).
function upgrade_2026_4_8_channel_shares() {

	install_add_column('ws_guests', 'access', "ENUM('write','read') NOT NULL DEFAULT 'write' AFTER name");

	install_note('Workspace: a channel can be shared with somebody outside the team through a one-time or timed link, to read only or to read and write.');

}

// Discussions in the workspace (2026.4.8, 8.83; includes/workspace/threads.php).
//
// A discussion is a channel row of kind 'thread' (ws_channels.kind widened
// to carry it; the enum is read first and only widened when the value is
// missing, keeping the values it has) talking over one message of another
// channel: ws_threads ties the two - parent_channel_id and message_id - with
// who started it and when, closed_at / closed_by when somebody concluded it,
// purge_at when it is deleted (30 days after), and assistants, the
// assistants asked in it ('ai', 'claude', comma-separated). ws_thread_copies
// is the channel's copy of a decision, a note or a task card of a
// discussion: channel_message_id the copy in the channel, thread_message_id
// the original, title the discussion's title, kept when the discussion is
// deleted so the copy can still say where it came from.
function upgrade_2026_4_8_threads() {

	$kind = install_column_info('ws_channels', 'kind');

	if (is_array($kind) && (strpos((string) $kind['Type'], "'thread'") === false)) {
		install_modify_column('ws_channels', 'kind', "ENUM('public','private','guest','thread') NOT NULL DEFAULT 'public'");
	} else {
		install_skipped(lang(array('string' => '{var:1} already exists', 'vars' => 'ws_channels.kind thread')));
	}

	install_create_table('ws_threads', "CREATE TABLE ws_threads (
		channel_id        INT UNSIGNED NOT NULL,
		parent_channel_id INT UNSIGNED NOT NULL DEFAULT 0,
		message_id        INT UNSIGNED NOT NULL DEFAULT 0,
		created_by        INT UNSIGNED NOT NULL DEFAULT 0,
		created_at        INT UNSIGNED NOT NULL DEFAULT 0,
		closed_at         INT UNSIGNED NOT NULL DEFAULT 0,
		closed_by         INT UNSIGNED NOT NULL DEFAULT 0,
		purge_at          INT UNSIGNED NOT NULL DEFAULT 0,
		assistants        VARCHAR(20) NOT NULL DEFAULT '',
		PRIMARY KEY (channel_id),
		KEY idx_parent (parent_channel_id, closed_at),
		KEY idx_message (message_id),
		KEY idx_purge (purge_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_thread_copies', "CREATE TABLE ws_thread_copies (
		channel_message_id INT UNSIGNED NOT NULL,
		thread_channel_id  INT UNSIGNED NOT NULL DEFAULT 0,
		thread_message_id  INT UNSIGNED NOT NULL DEFAULT 0,
		title              VARCHAR(80) NOT NULL DEFAULT '',
		created_at         INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (channel_message_id),
		KEY idx_thread (thread_channel_id),
		KEY idx_source (thread_message_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: a message can be talked over in a discussion of its own beside the channel; its decisions and tasks go into the channel.');

}

// Bulk changes the assistants propose (2026.4.8, 8.84;
// includes/workspace/bulk.php). A bulk change is a ws_ai_changes row with
// action 'bulk' (the column is a VARCHAR and takes it as it is): fields holds
// the rule, snapshot what it reached and how far applying it has got.
// config.ws_ai_bulk_delete is the administrator's choice whether the
// assistants may propose deleting records in bulk; off (0) by default.
function upgrade_2026_4_8_bulk_changes() {

	install_add_column('config', 'ws_ai_bulk_delete', "TINYINT(1) NOT NULL DEFAULT 0");

	install_note('Workspace: Pinegrap AI and Claude can propose one change for many records at once; deleting in bulk stays off until an administrator allows it.');

}

// ─── The remaining tables move to InnoDB (2026.4.8, 8.10-8.14) ──────────────
//
// Every table of the starter dumps was created on MyISAM, and until now only
// visitors (2026.3.6), user and files (2026.4.4) had been moved. MyISAM locks
// a whole table for every write: one slow UPDATE of an order holds back every
// read of `orders`, and a busy shop queues on its own tables. A server that
// stops uncleanly leaves MyISAM tables marked as crashed until REPAIR TABLE
// runs; InnoDB recovers by itself from its log. And the ERP writes its
// documents inside a transaction that cannot reach a MyISAM table: on InnoDB
// a rollback takes the change to `orders` and `products` back with the rest.
//
// The conversion itself is what once brought a site down. ALTER TABLE ...
// ENGINE=InnoDB copies the table while writes to it wait; on a large table
// the request ran into the web server's limit, the retries started further
// ALTERs behind the first one's metadata lock, and the site stopped
// answering. So the steps below go through pg_innodb_convert_table()
// (includes/fn/innodb.php), which:
//
//  - skips a table that is already on InnoDB, so the step can run any number
//    of times;
//  - leaves a table over the limits of upgrade_2026_4_8_innodb_limits() on
//    MyISAM: no upgrade request copies a table of that size. The operator
//    converts it from the Database Engine screen, when the site is quiet;
//  - does not start while an ALTER on the same table is still running from
//    an earlier request, and leaves alone a table whose earlier attempt never
//    finished;
//  - waits at most 20 s for the table's metadata lock;
//  - answers every MySQL error as a note, never as a failed version: the site
//    works on either engine.
//
// The tables are taken smallest first, so the work that is certain to fit is
// done before the work that might not. Each pass of the version keeps
// starting tables for install_pause_budget() seconds and then pauses; the
// upgrade screen sends a new request for the same version, which skips what
// is done and carries on.

// The limits above which the upgrade leaves a table on MyISAM for the Database
// Engine screen. Written once here; the step reads nothing else.
function upgrade_2026_4_8_innodb_limits() {

	return array('max_rows' => 250000, 'max_bytes' => 134217728);

}

// Moves the tables of one group of pg_innodb_table_groups(), smallest first,
// and says what it did.
function upgrade_2026_4_8_innodb_group($group, $label) {

	global $install_runner;

	// Tables converted in this pass of the version, by every group together:
	// the budget is the pass's, not the group's. Keyed by the moment the pass
	// began, so a new pass starts from nothing.
	static $pass_converted = array();

	$pass_key = (string) $install_runner['pass_started'];

	if (!isset($pass_converted[$pass_key])) {

		$pass_converted = array($pass_key => 0);

	}

	if (!function_exists('pg_innodb_capability')) {

		install_skipped(lang(array('string' => '{var:1}: the tables stay on MyISAM: {var:2}', 'vars' => array($label, 'includes/fn/innodb.php'))));

		return;

	}

	$capability = pg_innodb_capability();

	if (!$capability['ok']) {

		install_skipped(lang(array('string' => '{var:1}: the tables stay on MyISAM: {var:2}', 'vars' => array($label, $capability['reason']))));

		return;

	}

	$groups = pg_innodb_table_groups();

	$tables = $groups[$group];

	$status = pg_innodb_table_status($tables);

	$pending = array();

	$on_innodb = 0;

	foreach ($tables as $table) {

		if (!isset($status[$table])) {

			install_skipped(pg_innodb_state_text(array('state' => 'missing', 'table' => $table)));

			continue;

		}

		if ($status[$table]['engine'] === 'innodb') {

			$on_innodb++;

			install_skipped(pg_innodb_state_text(array('state' => 'already', 'table' => $table)));

			continue;

		}

		$pending[$table] = $status[$table]['bytes'];

	}

	asort($pending);

	$limits = upgrade_2026_4_8_innodb_limits();

	$options = array('max_rows' => $limits['max_rows'], 'max_bytes' => $limits['max_bytes'], 'retry' => false);

	$moved = 0;

	$remaining = count($pending);

	$left = array();

	foreach ($pending as $table => $bytes) {

		// No new table once the pass has used its time, and only after this
		// pass has converted something. A pause that did no work would be
		// followed by a pass that does no work either: the screen would keep
		// sending requests, and without the screen the runner would repeat
		// the version in one request until it gave up.
		if (($pass_converted[$pass_key] > 0) && (install_pass_seconds() >= install_pause_budget())) {

			install_pause(lang(array(
				'string' => '{var:1}: {var:2} tables moved so far, {var:3} remain; continuing',
				'vars' => array($label, $on_innodb + $moved, $remaining)
			)));

		}

		$result = install_move_to_innodb($table, $options);

		$remaining--;

		if ($result['state'] === 'running') {

			install_pause(lang(array('string' => '{var:1}: waiting for the ALTER TABLE on {var:2} that is still running', 'vars' => array($label, $table))), 10);

		}

		if ($result['state'] === 'busy') {

			install_pause(lang(array('string' => '{var:1}: {var:2} is in use; trying again in a moment', 'vars' => array($label, $table))), 5);

		}

		if ($result['state'] === 'converted') {

			$moved++;

			$pass_converted[$pass_key]++;

		} else {

			$left[] = $table;

		}

	}

	install_note(lang(array(
		'string' => '{var:1}: {var:2} of {var:3} tables are on InnoDB, {var:4} moved by this run.',
		'vars' => array($label, $on_innodb + $moved, count($status), $moved)
	)));

	if (count($left) > 0) {

		install_note(lang(array(
			'string' => '{var:1}: left on MyISAM: {var:2}',
			'vars' => array($label, implode(', ', $left))
		)));

	}

	// once per request, after the first group that converted or left a table
	static $warned = false;

	if ((!$warned) && (($moved > 0) || (count($left) > 0))) {

		$warned = true;

		install_note(lang('Large tables are rewritten whole while they are converted; writes to a table wait until its conversion ends.'));

	}

}

// Orders and everything hanging off them (8.10): order lines, gift cards,
// addresses, shipping, offers, refunds, the counter shop's sales history.
// next_order_number is handed out under LOCK TABLES ... WRITE; that keeps
// working on InnoDB unchanged.
function upgrade_2026_4_8_innodb_orders() {

	upgrade_2026_4_8_innodb_group('orders', lang('Orders'));

}

// The catalogue (8.11). The ERP writes stock moves in a transaction and
// changes the stock count of `products` after the commit
// (erp_stock_apply_pending()); that order of work stays as it is.
function upgrade_2026_4_8_innodb_products() {

	upgrade_2026_4_8_innodb_group('products', lang('Products'));

}

// Contacts, the activity log, e-mail campaigns, comments, forms and their
// entries, notifications (8.12). `log` and `email_recipients` are the tables
// most likely to be over the limit and left for the Database Engine screen.
function upgrade_2026_4_8_innodb_people() {

	upgrade_2026_4_8_innodb_group('people', lang('Contacts'));

}

// The site itself (8.13): settings, pages, styles, regions, folders, menus,
// calendars, zones and the rest. `config` is the reason the conversion asks
// for the DYNAMIC row format first (pg_innodb_capability()).
function upgrade_2026_4_8_innodb_site() {

	upgrade_2026_4_8_innodb_group('site', lang('Site'));

}

// The search index (8.14), last and on its own: it is the one table with
// FULLTEXT indexes (four), which InnoDB supports from MySQL 5.6 and MariaDB
// 10.0.5. The full-text engine is not the same one. InnoDB indexes words from
// three letters (innodb_ft_min_token_size; MyISAM's ft_min_word_len is four),
// in boolean and natural language mode alike; its stopword list is much
// shorter ("and" is not on it, "the" is); and natural language searches have
// no 50% threshold, under which MyISAM drops a word that appears in half of
// the rows. With the default settings a search can therefore find more than
// it did; the relevance scores are worked out differently, so results that
// match equally may come back in another order. The twelve MATCH ... AGAINST queries in
// get_search_results.php and includes/fn/widgets.php are not changed.
function upgrade_2026_4_8_innodb_search() {

	upgrade_2026_4_8_innodb_group('search', lang('Search'));

}

// How many queries a request sends, in the performance monitor (2026.4.8,
// 8.15; perf_monitor_shutdown() in includes/fn/seo.php, view_performance_log.php).
//
// perf_stats keeps one row per page per hour; total_queries is the sum over
// the hour's requests (BIGINT, it grows with the hits) and max_queries the
// largest single request. perf_log.query_count is the count of the one slow
// request the row records. The count is the connection's own Questions
// counter, so the plain mysqli_query() calls are in it too; where that cannot
// be read, the count of pg_db_run() (the db helpers) is used.
//
// The monitor writes the columns only once pg_schema_has() finds them, so a
// site with the new files and the old schema keeps recording as before. Both
// tables have existed since 2026.1.23 / 2026.3.2; each is asked for first all
// the same, because a site that never ran those steps must still get through
// this one. The schema cache is cleared at the end so the monitor sees the
// columns on its next request.
function upgrade_2026_4_8_perf_queries() {

	if (install_table_exists('perf_stats')) {
		install_add_column('perf_stats', 'total_queries', "BIGINT UNSIGNED NOT NULL DEFAULT 0");
		install_add_column('perf_stats', 'max_queries', "INT UNSIGNED NOT NULL DEFAULT 0");
	} else {
		install_skipped(lang(array('string' => 'table {var:1} does not exist, skipped', 'vars' => 'perf_stats')));
	}

	if (install_table_exists('perf_log')) {
		install_add_column('perf_log', 'query_count', "INT UNSIGNED NOT NULL DEFAULT 0");
	} else {
		install_skipped(lang(array('string' => 'table {var:1} does not exist, skipped', 'vars' => 'perf_log')));
	}

	if (function_exists('pg_schema_cache_clear')) {
		pg_schema_cache_clear();
	}

	install_note('Performance Log: the number of database queries a request sends is recorded per page and for every slow request.');

}
