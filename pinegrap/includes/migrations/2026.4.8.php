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
