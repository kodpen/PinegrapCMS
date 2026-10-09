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
	upgrade_2026_4_8_email_retry();             // 8.30
	upgrade_2026_4_8_mail_outbox();             // 8.31
	upgrade_2026_4_8_cron_locks();              // 8.32
	upgrade_2026_4_8_backup_settings();         // 8.33
	upgrade_2026_4_8_mfa();                     // 8.40

	upgrade_2026_4_8_perf_queries();            // 8.15

	upgrade_2026_4_8_design_templates();        // 8.16

	upgrade_2026_4_8_innodb_orders();           // 8.10

	upgrade_2026_4_8_innodb_products();         // 8.11

	upgrade_2026_4_8_innodb_people();           // 8.12

	upgrade_2026_4_8_config_text_columns();     // 8.17

	upgrade_2026_4_8_innodb_site();             // 8.13

	upgrade_2026_4_8_innodb_search();           // 8.14

	upgrade_2026_4_8_cookie_consent();          // 8.18

	upgrade_2026_4_8_workspace_events();        // 8.85

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

// Retries for the e-mail campaign job (2026.4.8, 8.30; email_campaign_job.php).
// The job used to send under LOCK TABLES and mark every recipient complete
// whether or not the message left. A run now claims its recipients with one
// conditional UPDATE (claimed_at, claim_token: the time and the run's random
// token) and sends with no table locked. A failed send counts an attempt,
// keeps the error and waits next_attempt_at out before the next one (60 s,
// 5 min, 30 min, 2 h, 6 h, 24 h); after six the recipient is complete and
// failed, so the campaign can finish and the campaign screen counts it.
// idx_pending serves the job's look for due recipients.
function upgrade_2026_4_8_email_retry() {

	install_add_column('email_recipients', 'claimed_at', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('email_recipients', 'claim_token', "VARCHAR(32) NOT NULL DEFAULT ''");
	install_add_column('email_recipients', 'attempts', "TINYINT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('email_recipients', 'last_error', "VARCHAR(500) NOT NULL DEFAULT ''");
	install_add_column('email_recipients', 'next_attempt_at', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('email_recipients', 'failed', "TINYINT(1) NOT NULL DEFAULT 0");
	install_add_index('email_recipients', 'idx_pending', "INDEX idx_pending (complete, next_attempt_at)");

	install_note('E-mail campaigns: a message that cannot be sent is tried again up to six times over about a day and a half; the campaign screen shows how many could not be delivered.');

}

// The outgoing mail queue (2026.4.8, 8.31; includes/fn/mail_queue.php,
// mail_job.php). email() with 'queue' => true writes a row here instead of
// talking to the SMTP server inside the visitor's request, as long as the
// general job has run in the last fifteen minutes; the general job sends it
// and retries on the campaign job's schedule. properties is the email()
// call as JSON (attachment bytes base64-encoded); mail_type, recipient and
// subject are copies for the mail queue screen. InnoDB, so the claim
// (UPDATE ... WHERE status = 'queued') is a row lock, not a table lock.
function upgrade_2026_4_8_mail_outbox() {

	install_create_table('mail_outbox', "CREATE TABLE mail_outbox (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		created_at  INT UNSIGNED NOT NULL DEFAULT 0,
		send_after  INT UNSIGNED NOT NULL DEFAULT 0,
		status      ENUM('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
		attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
		last_error  VARCHAR(500) NOT NULL DEFAULT '',
		claimed_at  INT UNSIGNED NOT NULL DEFAULT 0,
		sent_at     INT UNSIGNED NOT NULL DEFAULT 0,
		mail_type   VARCHAR(16) NOT NULL DEFAULT 'system',
		recipient   VARCHAR(255) NOT NULL DEFAULT '',
		subject     VARCHAR(255) NOT NULL DEFAULT '',
		properties  MEDIUMTEXT NOT NULL,
		PRIMARY KEY (id),
		KEY idx_due (status, send_after),
		KEY idx_created (created_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('E-mail: password reset, order, form and comment e-mails are handed to the scheduled general job when it is running, so a slow mail server no longer holds up the visitor; the queue is under Settings › Jobs › Mail queue.');

}

// A dispatch lock per scheduled job (2026.4.8, 8.32; includes/fn/cron.php).
// The general job locked the whole rotation with config.job_dispatch_lock_until,
// so a backup running for an hour held up campaigns, synchronisation and every
// daily job behind it. cron_runs.locked_until is now each job's own lock, and
// the catalogue puts every job in a lane, light or heavy, with at most one
// locked job per lane: a running backup no longer stops the short jobs, two
// heavy jobs never overlap and no job runs twice.
//
// The lane lives in pg_cron_jobs(), not in a column: nothing would read it
// from the table. config.job_dispatch_lock_until stays, a released schema, and
// is what the dispatcher falls back to until this column is there.
function upgrade_2026_4_8_cron_locks() {

	install_add_column('cron_runs', 'locked_until', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Scheduled jobs: each job run with the general job takes a lock of its own, so a long backup no longer holds up campaigns and the other short jobs.');

}

// Automatic backup retention and remote copy (2026.4.8, 8.33;
// includes/fn/backup.php). backup_keep is how many weekly automatic backups
// stay in data/backups (0 keeps all of them). backup_remote_type is '' (none),
// 'ftp' or 's3'; backup_remote_settings holds that destination's address and
// credentials as encrypted JSON ("<ciphertext>:<iv>"), TEXT because the
// config row is near the row size limit. backup_remote_error is the message
// of the last failed copy, emptied by the next one that succeeds, and
// backup_remote_sent_at when a copy last succeeded.
function upgrade_2026_4_8_backup_settings() {

	install_add_column('config', 'backup_keep', "INT UNSIGNED NOT NULL DEFAULT 4");
	install_add_column('config', 'backup_remote_type', "VARCHAR(8) NOT NULL DEFAULT ''");
	install_add_column('config', 'backup_remote_settings', "TEXT NULL");
	install_add_column('config', 'backup_remote_error', "TEXT NULL");
	install_add_column('config', 'backup_remote_sent_at', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Backups: the automatic backup is written as one zip archive per week, keeps the last four weeks by default, and can send a copy to an FTP server or an S3-compatible bucket (Settings › General › Backups).');

}

// Two-step sign-in (2026.4.8, 8.40; includes/fn/mfa.php, mfa.php).
//
// user_mfa holds one row per account that has, or is setting up, a second
// factor. totp_secret is the base32 key encrypted with ENCRYPTION_KEY in the
// "cipher:iv" shape the connector credentials use; it is read back on every
// sign-in, so it cannot be a hash. last_step is the last accepted TOTP
// counter, kept so a code cannot be replayed within its window.
// pending_secret/pending_at hold a key that was generated but not yet
// confirmed with a first code. user_mfa_recovery holds the one-time recovery
// codes as SHA-256 hashes; a used code keeps its row with used_at set so the
// account screen can say how many are left.
// config.mfa_required_role: accounts whose role is <= this value must have a
// second factor and are asked to set one up when they sign in; 99 means no
// role is required to.
function upgrade_2026_4_8_mfa() {

	install_create_table('user_mfa', "CREATE TABLE user_mfa (
		user_id        INT UNSIGNED NOT NULL,
		method         VARCHAR(8) NOT NULL DEFAULT 'totp',
		totp_secret    VARCHAR(255) CHARACTER SET ascii NOT NULL DEFAULT '',
		enabled_at     INT UNSIGNED NOT NULL DEFAULT 0,
		last_step      INT UNSIGNED NOT NULL DEFAULT 0,
		pending_secret VARCHAR(255) CHARACTER SET ascii NOT NULL DEFAULT '',
		pending_at     INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('user_mfa_recovery', "CREATE TABLE user_mfa_recovery (
		id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id   INT UNSIGNED NOT NULL,
		code_hash CHAR(64) CHARACTER SET ascii NOT NULL,
		used_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		INDEX idx_user (user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('config', 'mfa_required_role', "TINYINT NOT NULL DEFAULT 99");

	install_note('Two-step sign-in: an account can require a code from an authenticator app after its password, and Settings › Security can require it of a role.');

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
//  - leaves a table whose widest possible row is over InnoDB's row limit on
//    MyISAM without starting the copy ('too_wide', pg_innodb_row_estimate());
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

// The text columns of `config` become TEXT (2026.4.8, 8.17), before the
// site group moves `config` to InnoDB (8.13).
//
// `config` is one row of some 410 columns and sits against two row limits.
//
//  - The server's own: 65,535 bytes, whatever the engine, counting every
//    VARCHAR at its longest (VARCHAR(255) is 1022 bytes in utf8mb4) and a
//    TEXT column as 10. The stock table came to 64,069 bytes; one more
//    VARCHAR(255) would refuse the next install_add_column() on every server.
//  - InnoDB's: 8126 bytes on a 16 KB page. A column of 256 bytes or more, and
//    every TEXT column, can leave the page and counts 41 bytes (21 on
//    MariaDB 10.4+); a VARCHAR of 255 bytes or less - VARCHAR(63) and below
//    in utf8mb4 - never leaves it and counts in full. MySQL 8.0 counted the
//    stock table at 8468 bytes and refused to convert it with error 1118;
//    MySQL 5.7 (6988) and MariaDB (5868) took it.
//
// Widening the short VARCHAR columns to 256 bytes would satisfy InnoDB but
// adds some 5 KB under the first limit, which has 1.4 KB left: the ALTER is
// refused there instead. TEXT satisfies both: 41 bytes in InnoDB's count
// like a long VARCHAR (MySQL 8.0: 7064 bytes), 10 in the server's (2126
// bytes). Every VARCHAR of the table changes, the long ones too: they are
// what fills the server's row, and no VARCHAR is left for the next
// migration to copy.
// TEXT holds all a VARCHAR held; the table has no index, the code asks only
// whether a column exists, never its type.
//
// No DEFAULT is written. MySQL accepts none on TEXT before 8.0.13 and only
// the expression form DEFAULT ('x') after it. The row is created once, by
// the installer's dump, and only ever updated, so a default would never be
// used.
//
// Asking first: only VARCHAR columns are picked, so a second run finds none
// and changes nothing.
function upgrade_2026_4_8_config_text_columns() {

	if (!install_table_exists('config')) {

		install_skipped(lang(array('string' => 'table {var:1} does not exist, skipped', 'vars' => 'config')));

		return;

	}

	$columns = db_items(
		"SELECT COLUMN_NAME AS column_name
		FROM information_schema.COLUMNS
		WHERE
			(TABLE_SCHEMA = DATABASE())
			AND (TABLE_NAME = 'config')
			AND (DATA_TYPE = 'varchar')
		ORDER BY ORDINAL_POSITION");

	if (count($columns) == 0) {

		install_skipped(lang('config: every text column is already TEXT'));

		return;

	}

	// The rest of each definition as the table has it now.
	$definitions = array();

	foreach (db_items("SHOW FULL COLUMNS FROM `config`") as $row) {

		$definitions[(string) $row['Field']] = $row;

	}

	$changed = 0;

	foreach ($columns as $column) {

		$name = (string) $column['column_name'];

		if (!isset($definitions[$name])) {

			continue;

		}

		$row = $definitions[$name];

		$definition = 'TEXT';

		// utf8mb4_unicode_ci: the character set is the part before the first
		// underscore. Both names come from the server and are checked all
		// the same before they go into the statement.
		$collation = isset($row['Collation']) ? (string) $row['Collation'] : '';

		if (preg_match('/^([a-z0-9]+)_[a-z0-9_]+$/', $collation, $match)) {

			$definition .= ' CHARACTER SET ' . $match[1] . ' COLLATE ' . $collation;

		}

		$definition .= (strtoupper((string) $row['Null']) === 'YES') ? ' NULL' : ' NOT NULL';

		if (isset($row['Comment']) && ((string) $row['Comment'] !== '')) {

			$definition .= " COMMENT '" . e((string) $row['Comment']) . "'";

		}

		install_modify_column('config', $name, $definition);

		$changed++;

	}

	install_note(lang(array('string' => '{var:1} config columns changed from VARCHAR to TEXT', 'vars' => $changed)));

	if (function_exists('pg_innodb_row_estimate')) {

		$estimate = pg_innodb_row_estimate('config');

		if (is_array($estimate)) {

			install_note(lang(array(
				'string' => 'config: the widest possible row is now {var:1} of {var:2} bytes on InnoDB ({var:3} count)',
				'vars' => array($estimate['bytes'], $estimate['limit'], $estimate['rule'])
			)));

		}

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

// Design templates made from a design (2026.4.8, 8.16;
// includes/fn/design_templates_custom.php). The operator turns a visual
// design into a template, offered under Choose a Template beside the ones
// that ship with the software and opened the same way. template_json is the
// whole template - pages, widgets, shared components, folders, form
// settings, assets - in the shape a file under includes/design_templates/
// returns, so it can be carried to another site as it is. template_key is
// the id the template is offered under ('custom-<id>'); name, description,
// version, framework, look_key and palette_key are the parts the gallery
// lists without reading the document; source_style_id the design it was
// made from (kept for reference only: the design may be deleted since).
function upgrade_2026_4_8_design_templates() {

	install_create_table('design_template', "CREATE TABLE IF NOT EXISTS design_template (
		id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		template_key    VARCHAR(64)  NOT NULL DEFAULT '',
		name            VARCHAR(255) NOT NULL DEFAULT '',
		description     TEXT         NOT NULL,
		version         VARCHAR(20)  NOT NULL DEFAULT '1.0.0',
		framework       VARCHAR(32)  NOT NULL DEFAULT 'bootstrap5',
		look_key        VARCHAR(100) NOT NULL DEFAULT '',
		palette_key     VARCHAR(100) NOT NULL DEFAULT '',
		source_style_id INT UNSIGNED NOT NULL DEFAULT 0,
		template_json   LONGTEXT     NOT NULL,
		created_by      INT UNSIGNED NOT NULL DEFAULT 0,
		created_at      INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at      INT UNSIGNED NOT NULL DEFAULT 0,
		UNIQUE KEY uk_template_key (template_key)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	if (function_exists('pg_schema_cache_clear')) {
		pg_schema_cache_clear();
	}

	install_note('Visual editor: a design can be turned into a template (Design settings › Turn this design into a template); it is listed under Choose a Template with the built-in ones.');

}

// Cookie consent (2026.4.8, 8.18; includes/fn/consent.php). cookie_consent
// turns on the notice in the bottom-left corner of visitor pages: necessary
// cookies stay, Google Analytics and the statistics and referral cookies the
// software sets itself wait until the visitor allows them. On by default,
// for new and upgraded sites alike, because asking first is what the law of
// most of the sites' visitors requires. cookie_consent_policy_url is the
// optional "Cookie policy" link of the notice; TEXT, because the config row
// is near the row size limit.
function upgrade_2026_4_8_cookie_consent() {

	install_add_column('config', 'cookie_consent', "TINYINT NOT NULL DEFAULT 1");
	install_add_column('config', 'cookie_consent_policy_url', "TEXT NULL");

	install_note('Cookie consent: visitor pages ask before setting optional cookies; Google Analytics and the visitor statistics cookies start only after the visitor allows them (Settings › SEO › Cookie Consent).');

}

// The site's events in the workspace (2026.4.8, 8.85;
// includes/workspace/watch.php, includes/fn/events.php). ws_events_in is the
// workspace's inbox of what happened on the site - a new order, a form
// submitted, a product low in stock: pg_event_record() writes one row
// (event, payload as JSON, created_at) where the event is announced, and the
// workspace's run takes it later (taken_at, kept seven days then swept;
// idx_open is how the run finds the ones not taken yet and the sweep the
// old ones). ws_channels.watch is the channel's choice whether the events
// of its customer and of the records tagged in it are written into it as a
// line; on (1) by default.
function upgrade_2026_4_8_workspace_events() {

	install_create_table('ws_events_in', "CREATE TABLE ws_events_in (
		id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
		event      VARCHAR(60)  NOT NULL DEFAULT '',
		payload    TEXT         NOT NULL,
		created_at INT UNSIGNED NOT NULL DEFAULT 0,
		taken_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_open (taken_at, id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('ws_channels', 'watch', "TINYINT(1) NOT NULL DEFAULT 1");

	// pg_event_record() asks pg_schema_has() on every announced event, and
	// the answer is cached on disk: a "missing" kept from before this step
	// would leave the inbox unwritten.
	if (function_exists('pg_schema_cache_clear')) {
		pg_schema_cache_clear();
	}

	install_note('Workspace: a scheduled action can start when something happens on the site (a new order, a form submitted, a product low in stock), and a channel hears about the orders of its customer and the records tagged in it.');

}
