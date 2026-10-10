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

	upgrade_2026_4_8_tab_layout();              // 8.19

	upgrade_2026_4_8_workspace_events();        // 8.85
	upgrade_2026_4_8_workspace_templates();     // 8.88
	upgrade_2026_4_8_workspace_task_time();     // 8.86

	upgrade_2026_4_8_workspace_task_links();    // 8.110
	upgrade_2026_4_8_workspace_approvals();     // 8.87

	upgrade_2026_4_8_workspace_acks();          // 8.89

	upgrade_2026_4_8_erp_quotes();              // 8.58

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

// The order and the groups of a design's page tabs in the visual editor
// (2026.4.8, 8.19; pg_designer_tab_layout_normalize() in
// includes/fn/designer.php). style_tab_layout holds them as JSON, written by
// the designer/tab_layout endpoint; NULL until the operator rearranges the
// tabs, which then follow page_id as before.
function upgrade_2026_4_8_tab_layout() {

	install_add_column('style', 'style_tab_layout', "TEXT NULL");

	install_note('Visual editor: the page tabs can be put in any order and gathered into named, coloured groups that fold up.');

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

// Channel templates in the workspace (2026.4.8, 8.88;
// includes/workspace/templates.php). A template is what a new channel - or
// one already open - is set up with: a set of tasks with dates relative to
// the day it is applied, the rule for whom each goes to and their
// checklists, notes to share, the summary, the message pinned to the top
// and a first message. name, description, icon (a Bootstrap Icons class) and
// color (a place in the channel palette, 0 for none) are what the pickers
// show; kind and department_id the channel suggested; summary, pinned and
// welcome the texts written into the channel; body the tasks and the notes
// as JSON (ws_template_body_clean()). uses counts how often it was applied;
// archived takes it out of the pickers. idx_live serves the pickers' list.
// The built-in templates are kept in code, not here.
function upgrade_2026_4_8_workspace_templates() {

	install_create_table('ws_templates', "CREATE TABLE ws_templates (
		id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
		name          VARCHAR(100) NOT NULL DEFAULT '',
		description   VARCHAR(255) NOT NULL DEFAULT '',
		icon          VARCHAR(40)  NOT NULL DEFAULT '',
		color         TINYINT UNSIGNED NOT NULL DEFAULT 0,
		kind          ENUM('public','private') NOT NULL DEFAULT 'public',
		department_id INT UNSIGNED NOT NULL DEFAULT 0,
		summary       TEXT NULL,
		pinned        TEXT NULL,
		welcome       TEXT NULL,
		body          TEXT NULL,
		created_by    INT UNSIGNED NOT NULL DEFAULT 0,
		created_at    INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at    INT UNSIGNED NOT NULL DEFAULT 0,
		archived      TINYINT(1) NOT NULL DEFAULT 0,
		uses          INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_live (archived, name)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: channel templates set a new or an open channel up with a ready set of tasks, notes, a summary, a pinned message and a welcome (Workspace Settings › Channel templates).');

}

// Time spent on workspace tasks (2026.4.8, 8.86; includes/workspace/task_time.php).
// One row is one stretch of work by one person on one task: minutes on the
// day worked_on, with an optional note. A timer started in the task drawer
// is a row with started_at set and minutes 0 until it is stopped (a person
// has one running at most); time written by hand has started_at 0. billable
// says whether the time may be invoiced; invoice_id is the ERP invoice draft
// it went on (0 for none), so the same hour is not billed twice. InnoDB: the
// rows are locked while the invoice draft is written in one transaction.
// idx_task serves the drawer and the channel's card, idx_user a person's own
// time and the planning board, idx_invoice the tie to a draft.
function upgrade_2026_4_8_workspace_task_time() {

	install_create_table('ws_task_time', "CREATE TABLE ws_task_time (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		task_id     INT UNSIGNED NOT NULL DEFAULT 0,
		user_id     INT UNSIGNED NOT NULL DEFAULT 0,
		started_at  INT UNSIGNED NOT NULL DEFAULT 0,
		minutes     INT UNSIGNED NOT NULL DEFAULT 0,
		worked_on   DATE NOT NULL,
		note        VARCHAR(255) NOT NULL DEFAULT '',
		billable    TINYINT(1) NOT NULL DEFAULT 1,
		invoice_id  INT UNSIGNED NOT NULL DEFAULT 0,
		created_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_task (task_id, worked_on),
		KEY idx_user (user_id, worked_on),
		KEY idx_invoice (invoice_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: the time spent on a task can be written in its drawer, with a timer or by hand; a channel adds it up, and with the ERP it becomes an invoice draft for the channel\'s customer.');

}

// Tasks that wait for other tasks (2026.4.8, 8.110;
// includes/workspace/task_links.php). A row says task_id cannot start until
// blocked_by_task_id is done; created_by and created_at say who linked them
// and when. The primary key keeps a pair once; idx_blocker finds the tasks
// that wait for a task when it is done. A loop of links is refused by the
// code, not the schema.
function upgrade_2026_4_8_workspace_task_links() {

	install_create_table('ws_task_links', "CREATE TABLE ws_task_links (
		task_id            INT UNSIGNED NOT NULL,
		blocked_by_task_id INT UNSIGNED NOT NULL,
		created_by         INT UNSIGNED NOT NULL DEFAULT 0,
		created_at         INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (task_id, blocked_by_task_id),
		KEY idx_blocker (blocked_by_task_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: a task can wait for other tasks; it shows what it waits for, and its people hear when it can start.');

}

// Approval requests in the workspace (2026.4.8, 8.87;
// includes/workspace/approvals.php). A request is a message of a channel
// with a card under it: ws_approvals holds the card - message_id the request
// (one card per message), title, rule ('any': the first approval settles it,
// 'all': everybody has to approve), record_type / record_id the first record
// tagged in the text, closes_at the optional deadline, reminded_at when the
// people still waiting were reminded the day before it, closed_at / closed_by
// / outcome once it is settled, result_message_id the locked decision (or
// the system line) it wrote into the channel. ws_approval_people is one row
// per approver with their decision, its optional note and when it was given.
// idx_open serves a channel's open cards, idx_due the scheduled run's look
// for deadlines that come or have passed, idx_user what waits for a person.
function upgrade_2026_4_8_workspace_approvals() {

	install_create_table('ws_approvals', "CREATE TABLE ws_approvals (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		message_id        INT UNSIGNED NOT NULL DEFAULT 0,
		channel_id        INT UNSIGNED NOT NULL DEFAULT 0,
		title             VARCHAR(255) NOT NULL DEFAULT '',
		rule              ENUM('any','all') NOT NULL DEFAULT 'any',
		record_type       VARCHAR(20) NOT NULL DEFAULT '',
		record_id         INT UNSIGNED NOT NULL DEFAULT 0,
		closes_at         INT UNSIGNED NOT NULL DEFAULT 0,
		reminded_at       INT UNSIGNED NOT NULL DEFAULT 0,
		closed_at         INT UNSIGNED NOT NULL DEFAULT 0,
		closed_by         INT UNSIGNED NOT NULL DEFAULT 0,
		outcome           ENUM('open','approved','rejected','withdrawn','expired') NOT NULL DEFAULT 'open',
		result_message_id INT UNSIGNED NOT NULL DEFAULT 0,
		created_by        INT UNSIGNED NOT NULL DEFAULT 0,
		created_at        INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uk_message (message_id),
		KEY idx_open (channel_id, closed_at, closes_at),
		KEY idx_due (closed_at, closes_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_approval_people', "CREATE TABLE ws_approval_people (
		approval_id INT UNSIGNED NOT NULL,
		user_id     INT UNSIGNED NOT NULL,
		decision    ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
		note        VARCHAR(255) NOT NULL DEFAULT '',
		decided_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (approval_id, user_id),
		KEY idx_user (user_id, decision)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: a channel can ask chosen people to approve something; the result is written into the channel as a locked decision.');

}

// Read receipts in the workspace (2026.4.8, 8.89; includes/workspace/acks.php).
// ws_ack_requests marks a message whose readers are asked to say they read
// it: who asked and when, reminded_at when the people who had not read it
// a day later were reminded. A table of its own rather than a column of
// ws_messages, which is wide already. ws_acks is one row per person who said
// so. idx_remind serves the scheduled run's look for reminders that are due.
function upgrade_2026_4_8_workspace_acks() {

	install_create_table('ws_ack_requests', "CREATE TABLE ws_ack_requests (
		message_id   INT UNSIGNED NOT NULL,
		requested_by INT UNSIGNED NOT NULL DEFAULT 0,
		requested_at INT UNSIGNED NOT NULL DEFAULT 0,
		reminded_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (message_id),
		KEY idx_remind (reminded_at, requested_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_acks', "CREATE TABLE ws_acks (
		message_id INT UNSIGNED NOT NULL,
		user_id    INT UNSIGNED NOT NULL,
		acked_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (message_id, user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: a message can ask its readers to confirm they read it; the writer sees who has and who has not.');

}

// ERP: quotes about to run out, the invoice's quote and the customer's
// signature (2026.4.8, 8.58; includes/erp/alerts.php, quotes.php,
// signatures.php).
//
// erp_quotes.expiry_notified_at is when an open quote was announced as about
// to run out (0: not yet); the scheduled run announces each quote once, and
// opening it again or changing its date clears the stamp. The run's look
// for them is served by the idx_status (status, valid_until) index the
// table was created with (4.92). config.erp_notify_quote_expiry switches the
// notice (on by default) and erp_notify_quote_expiry_days says how many days
// ahead it looks.
//
// erp_invoices.quote_id names the quote an invoice was made from, so the
// invoice can lead back to it; erp_quotes.invoice_id, the other way, is
// cleared when a quote is opened again and cannot serve. The invoices made
// from a quote before this step are linked from erp_quotes.invoice_id where
// it still names them.
//
// erp_signatures is a signature drawn under an ERP document - today a quote
// signed on the screen by the customer. doc_type / doc_id name the document
// (one signature each), file_id the drawing, a files row kept the way the
// ERP keeps its documents (erp_doc_type 'quote_signature'). image_hash is the
// SHA-256 of the drawing, document_hash that of what was signed (the lines
// and totals), seal an HMAC over the record with the site's key. A row is
// evidence and is never deleted.
function upgrade_2026_4_8_erp_quotes() {

	install_add_column('erp_quotes', 'expiry_notified_at', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_index('erp_quotes', 'idx_status', "INDEX idx_status (status, valid_until)");

	install_add_column('config', 'erp_notify_quote_expiry', "TINYINT(1) NOT NULL DEFAULT 1");
	install_add_column('config', 'erp_notify_quote_expiry_days', "TINYINT UNSIGNED NOT NULL DEFAULT 3");

	install_add_column('erp_invoices', 'quote_id', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_index('erp_invoices', 'idx_quote', "INDEX idx_quote (quote_id)");

	install_create_table('erp_signatures', "CREATE TABLE erp_signatures (
		id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
		doc_type      VARCHAR(20) NOT NULL,
		doc_id        INT UNSIGNED NOT NULL,
		file_id       INT UNSIGNED NOT NULL,
		image_hash    CHAR(64) NOT NULL DEFAULT '',
		document_hash CHAR(64) NOT NULL DEFAULT '',
		signer_name   VARCHAR(255) NOT NULL DEFAULT '',
		signed_at     INT UNSIGNED NOT NULL DEFAULT 0,
		ip_address    VARCHAR(45) NOT NULL DEFAULT '',
		user_agent    VARCHAR(255) NOT NULL DEFAULT '',
		user_id       INT UNSIGNED NOT NULL DEFAULT 0,
		seal          CHAR(64) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		UNIQUE KEY uniq_doc (doc_type, doc_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Invoices made from a quote before this step: the quote still names its
	// invoice. Only links not yet set are written, so a second run changes
	// nothing; a quote opened again had its invoice_id cleared and is not
	// found.
	db("UPDATE erp_invoices i
		INNER JOIN erp_quotes q ON q.invoice_id = i.id
		SET i.quote_id = q.id
		WHERE i.quote_id = 0 AND q.invoice_id > 0");

	$linked = (int) mysqli_affected_rows(db::$con);

	if ($linked > 0) {
		install_ran('erp_invoices.quote_id set for ' . $linked . ' invoice(s) made from a quote');
	} else {
		install_skipped('erp_invoices.quote_id: no invoice made from a quote left to link');
	}

	install_note('Quotes: a notice when open quotes are about to run out (Settings › Commerce › ERP), an invoice that leads back to the quote it was made from, and a quote the customer signs on the screen, printed with the signature.');

}
