<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.4.5. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// 2026.4.5 - the release that follows 2026.4.4.
//
// One entry point, one step per subsystem, called in the order the work was
// done. The numbers in the comments are labels only and follow the ranges
// 2026.4.4 used (the workspace from 5.80); the order of the calls is what
// runs. Every step is defensive, so an installation that already ran part of
// the version - a development database pulled back to 2026.4.4 - finds what
// is in place and moves on.
function upgrade_to_2026_4_5() {

	upgrade_2026_4_5_workspace_message_hides();  // 5.80

	upgrade_2026_4_5_workspace_scheduled();      // 5.81

	upgrade_2026_4_5_workspace_channel_colors(); // 5.82

	upgrade_2026_4_5_workspace_channel_eras();   // 5.83

	upgrade_2026_4_5_workspace_channel_groups(); // 5.84

	upgrade_2026_4_5_workspace_scheduled_chains(); // 5.85

	upgrade_2026_4_5_workspace_pins();           // 5.86

	upgrade_2026_4_5_workspace_forwards();       // 5.87

	upgrade_2026_4_5_workspace_blocks();         // 5.88

	upgrade_2026_4_5_workspace_channel_customer(); // 5.89

	upgrade_2026_4_5_design_framework();       // 5.1

	upgrade_2026_4_5_design_look();            // 5.3

	upgrade_2026_4_5_job_dispatch_list();      // 5.2

	upgrade_2026_4_5_workspace_ai();            // 5.110

	upgrade_2026_4_5_workspace_task_reminders(); // 5.111

	upgrade_2026_4_5_workspace_guests();        // 5.112

	upgrade_2026_4_5_design_ai();               // 5.70

	upgrade_2026_4_5_api_devices();             // 5.71

}

// A message taken out of one person's view in a workspace channel (2026.4.5,
// 5.80; includes/workspace/messages.php). Deleting a message for everyone
// clears it where it stood, as before; deleting it for oneself leaves it for
// the others and only hides it from the one who asked, so it is a row per
// person and message rather than a column on the message. Nothing is backfilled:
// until somebody hides one, every message is in everybody's view.
function upgrade_2026_4_5_workspace_message_hides() {

	install_create_table('ws_message_hides', "CREATE TABLE ws_message_hides (
		message_id  INT UNSIGNED NOT NULL,
		user_id     INT UNSIGNED NOT NULL,
		hidden_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (message_id, user_id),
		KEY idx_user (user_id, message_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace channels: a message can be deleted for oneself only, and one deleted for everyone goes without a trace.');

}

// Scheduled actions (2026.4.5, 5.81; includes/workspace/scheduled.php). Staff
// write something to be done at a time - post in a channel, send an e-mail,
// change a record - with conditions that must hold then and one more action
// to follow. The rules and the actions are kept as JSON: their shape differs
// by kind and nothing ever queries inside them; what is queried is the state
// and the next time, which idx_due covers for the run that looks for work.
// running_at is the claim a run takes on a row before it acts, so two
// requests that meet do not both send the e-mail. Every run is kept in
// ws_scheduled_runs, with what each step did.
function upgrade_2026_4_5_workspace_scheduled() {

	install_create_table('ws_scheduled_actions', "CREATE TABLE ws_scheduled_actions (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		name         VARCHAR(160) NOT NULL DEFAULT '',
		channel_id   INT UNSIGNED NOT NULL DEFAULT 0,
		message_id   INT UNSIGNED NOT NULL DEFAULT 0,
		note_id      INT UNSIGNED NOT NULL DEFAULT 0,
		created_by   INT UNSIGNED NOT NULL DEFAULT 0,
		rules        MEDIUMTEXT NULL,
		action       MEDIUMTEXT NULL,
		then_action  MEDIUMTEXT NULL,
		status       ENUM('active','paused','done','failed','cancelled') NOT NULL DEFAULT 'active',
		next_run_at  INT UNSIGNED NOT NULL DEFAULT 0,
		last_run_at  INT UNSIGNED NOT NULL DEFAULT 0,
		run_count    INT UNSIGNED NOT NULL DEFAULT 0,
		running_at   INT UNSIGNED NOT NULL DEFAULT 0,
		updated_by   INT UNSIGNED NOT NULL DEFAULT 0,
		created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_due (status, next_run_at),
		KEY idx_channel (channel_id),
		KEY idx_message (message_id),
		KEY idx_note (note_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_scheduled_runs', "CREATE TABLE ws_scheduled_runs (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		action_id    INT UNSIGNED NOT NULL DEFAULT 0,
		started_at   INT UNSIGNED NOT NULL DEFAULT 0,
		finished_at  INT UNSIGNED NOT NULL DEFAULT 0,
		status       ENUM('done','skipped','failed') NOT NULL DEFAULT 'done',
		detail       TEXT NULL,
		message_id   INT UNSIGNED NOT NULL DEFAULT 0,
		by_hand      TINYINT(1) NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_action (action_id, id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace scheduled actions: staff can have something done at a time - write in a channel, send an e-mail or a page, change a record - once or on a repeat, with conditions that must hold then and one more action to follow.');

}

// A colour for a workspace channel (2026.4.5, 5.82; includes/workspace/
// channels.php, ws_palette()). 0 is none; 1-20 are the places in the palette
// of solid, matte colours the groups of channels use too, so a colour is a
// number rather than a code: the palette can be tuned without touching the
// rows. It is the channel's, the same for everybody, and set by whoever may
// change the channel.
function upgrade_2026_4_5_workspace_channel_colors() {

	install_add_column('ws_channels', 'color', "TINYINT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Workspace channels can have a colour.');

}

// Clearing a channel's conversation (2026.4.5, 5.83; includes/workspace/
// eras.php). Staff clear a channel and start its conversation again; what
// was written stays, as an earlier version the channel's settings list and
// open to read. ws_messages is not touched: the messages of a channel whose
// id is at or below ws_channels.era_floor belong to a version before the
// current one, and ws_channel_eras keeps where each version begins and ends
// (message ids, which only grow, so a version is a range). That keeps the
// change off the biggest table of the workspace, and every earlier message
// stays where links to it point.
function upgrade_2026_4_5_workspace_channel_eras() {

	install_add_column('ws_channels', 'era_floor', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_create_table('ws_channel_eras', "CREATE TABLE ws_channel_eras (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		channel_id        INT UNSIGNED NOT NULL DEFAULT 0,
		number            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		title             VARCHAR(160) NOT NULL DEFAULT '',
		first_message_id  INT UNSIGNED NOT NULL DEFAULT 0,
		last_message_id   INT UNSIGNED NOT NULL DEFAULT 0,
		message_count     INT UNSIGNED NOT NULL DEFAULT 0,
		started_at        INT UNSIGNED NOT NULL DEFAULT 0,
		ended_at          INT UNSIGNED NOT NULL DEFAULT 0,
		cleared_by        INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_channel (channel_id, number)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace channels: staff can clear a conversation; the one before is kept as an earlier version to read.');

}

// Groups of workspace channels (2026.4.5, 5.84; includes/workspace/
// groups.php). A named heading with a colour from the palette of 5.82, in the
// order staff give it, and inside another group if it is one's part
// (parent_id; 0 is the top). A channel is in one group or none
// (ws_channels.group_id). The groups are the workspace's, the same for
// everybody, and staff arrange them.
// Access given on a group (ws_channel_group_access) reaches every channel in
// it and in the groups inside it, the private ones too: the person is made a
// member of each, and ws_channel_members.via_group says which group brought
// them, so taking the access back takes out only those memberships (0: in the
// channel by themselves). A channel that later moves into the group lets the
// people with access in too.
function upgrade_2026_4_5_workspace_channel_groups() {

	install_create_table('ws_channel_groups', "CREATE TABLE ws_channel_groups (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		parent_id   INT UNSIGNED NOT NULL DEFAULT 0,
		name        VARCHAR(80) NOT NULL DEFAULT '',
		color       TINYINT UNSIGNED NOT NULL DEFAULT 0,
		sort        INT NOT NULL DEFAULT 0,
		created_by  INT UNSIGNED NOT NULL DEFAULT 0,
		created_at  INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_parent (parent_id, sort)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_channel_group_access', "CREATE TABLE ws_channel_group_access (
		group_id    INT UNSIGNED NOT NULL,
		user_id     INT UNSIGNED NOT NULL,
		granted_by  INT UNSIGNED NOT NULL DEFAULT 0,
		granted_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (group_id, user_id),
		KEY idx_user (user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('ws_channels', 'group_id', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_index('ws_channels', 'idx_group', "INDEX idx_group (group_id)");
	install_add_column('ws_channel_members', 'via_group', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Workspace channels can be put in groups, one inside another; access given on a group reaches every channel in it.');

}

// Follow-ups and chains of scheduled actions (2026.4.5, 5.85; includes/
// workspace/scheduled.php). ws_scheduled_actions.follow keeps what is done
// after the action by how it went - a list in JSON, like the rules - and
// takes over from then_action, which stays for the rows written before and is
// read as a follow-up "if it went well".
// ws_scheduled_queue holds the starts one action gives another: due now or
// later, with the depth of the chain and the run that gave it, so a chain
// can be followed back and stopped at its depth; idx_due is what the run
// reads, idx_action what a cancelled action clears and the hourly limit
// counts. The runs say which action and which run started them.
// ws_scheduled_notices keeps the words of a "let people know" line: an inbox
// row names what it is about, not what it says, so the words sit beside it,
// one row per inbox line (an unread line of the same action is brought up to
// date rather than written again).
function upgrade_2026_4_5_workspace_scheduled_chains() {

	install_add_column('ws_scheduled_actions', 'follow', "MEDIUMTEXT NULL");

	install_add_column('ws_scheduled_runs', 'source_action_id', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('ws_scheduled_runs', 'source_run_id', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('ws_scheduled_runs', 'depth', "TINYINT UNSIGNED NOT NULL DEFAULT 0");

	install_create_table('ws_scheduled_queue', "CREATE TABLE ws_scheduled_queue (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		action_id         INT UNSIGNED NOT NULL DEFAULT 0,
		due_at            INT UNSIGNED NOT NULL DEFAULT 0,
		source_action_id  INT UNSIGNED NOT NULL DEFAULT 0,
		source_run_id     INT UNSIGNED NOT NULL DEFAULT 0,
		depth             TINYINT UNSIGNED NOT NULL DEFAULT 0,
		status            ENUM('waiting','taken','done','dropped') NOT NULL DEFAULT 'waiting',
		created_at        INT UNSIGNED NOT NULL DEFAULT 0,
		taken_at          INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_due (status, due_at),
		KEY idx_action (action_id, created_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_scheduled_notices', "CREATE TABLE ws_scheduled_notices (
		inbox_id    INT UNSIGNED NOT NULL,
		action_id   INT UNSIGNED NOT NULL DEFAULT 0,
		text        VARCHAR(1000) NOT NULL DEFAULT '',
		created_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (inbox_id),
		KEY idx_action (action_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace scheduled actions: follow-ups by how an action went, chains of actions that start one another, counts, web checks, webhooks, summaries, tasks and notices.');

}

// The message pinned to the top of a channel (2026.4.5, 5.86; includes/
// workspace/pins.php). One per channel, so it is a column of the channel;
// ws_channel_members.pin_hidden is the pinned message a member closed, so a
// new pin shows again to everybody without anything being reset.
function upgrade_2026_4_5_workspace_pins() {

	install_add_column('ws_channels', 'pinned_message_id', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('ws_channels', 'pinned_by', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('ws_channels', 'pinned_at', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('ws_channel_members', 'pin_hidden', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Workspace channels: a message can be pinned to the top of a channel.');

}

// Messages chosen together (2026.4.5, 5.87; includes/workspace/forward.php).
// An answer to several keeps its first as the parent, as ever, and the others
// in ws_message_quotes. A forward is a message of its own; ws_message_forwards
// keeps a copy of each message it carries - words, writer, time, channel - so
// what was forwarded does not change with the original or with who may read
// its channel. ws_messages itself is not touched.
function upgrade_2026_4_5_workspace_forwards() {

	install_create_table('ws_message_quotes', "CREATE TABLE ws_message_quotes (
		message_id  INT UNSIGNED NOT NULL,
		quoted_id   INT UNSIGNED NOT NULL,
		position    TINYINT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (message_id, quoted_id),
		KEY idx_quoted (quoted_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_message_forwards', "CREATE TABLE ws_message_forwards (
		id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
		message_id          INT UNSIGNED NOT NULL DEFAULT 0,
		position            TINYINT UNSIGNED NOT NULL DEFAULT 0,
		source_message_id   INT UNSIGNED NOT NULL DEFAULT 0,
		source_channel_id   INT UNSIGNED NOT NULL DEFAULT 0,
		source_sender_kind  ENUM('user','app','system') NOT NULL DEFAULT 'user',
		source_sender_id    INT UNSIGNED NOT NULL DEFAULT 0,
		source_created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		body                TEXT NULL,
		file_name           VARCHAR(255) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY idx_message (message_id, position),
		KEY idx_source (source_message_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace messages: several can be answered at once, and forwarded to another channel or into a note.');

}

// Titled blocks (2026.4.5, 5.88; includes/workspace/blocks.php). A table,
// checklist, block of code or calculation with a line "::: Title" above it is
// indexed here, so its title finds it from another conversation or note.
// Only where it is - message or note, which block of it - its kind, title and
// writer are kept; the words stay in the message or note and are read from
// there when pulled, for somebody who may read them. Written as messages and
// notes are saved; there is nothing to backfill, the title line is new.
function upgrade_2026_4_5_workspace_blocks() {

	install_create_table('ws_blocks', "CREATE TABLE ws_blocks (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		source_kind  ENUM('message','note') NOT NULL DEFAULT 'message',
		source_id    INT UNSIGNED NOT NULL DEFAULT 0,
		channel_id   INT UNSIGNED NOT NULL DEFAULT 0,
		position     TINYINT UNSIGNED NOT NULL DEFAULT 0,
		kind         ENUM('table','checklist','code','calc') NOT NULL DEFAULT 'table',
		title        VARCHAR(160) NOT NULL DEFAULT '',
		author_id    INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_source (source_kind, source_id),
		KEY idx_updated (updated_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: tables, checklists, code and calculations can have a title, and be pulled by it into another conversation or note.');

}

// The customer of a workspace channel as a kind and a record (2026.4.5, 5.89;
// includes/workspace/customer.php): a contact, a user or a current account,
// where contact_id alone could only name a contact. contact_id stays, as the
// contact the customer stands for, since the address book, the record
// screens and the API find a customer's channels by it. The channels that
// had a contact have it as their customer.
function upgrade_2026_4_5_workspace_channel_customer() {

	install_add_column('ws_channels', 'customer_type', "VARCHAR(20) NOT NULL DEFAULT ''");
	install_add_column('ws_channels', 'customer_id', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_index('ws_channels', 'idx_customer', "INDEX idx_customer (customer_type, customer_id)");

	db("UPDATE ws_channels SET customer_type = 'contact', customer_id = contact_id WHERE contact_id > 0 AND customer_type = ''");

	install_note('Workspace channels: the customer of a channel can be a contact, a user or a current account, shown with the records tied to it.');

}

// The framework a visual-editor design is built on, and the template it
// started from (2026.4.5, 5.1; includes/fn/designer.php, pg_design_frameworks()
// and pg_design_templates()). A design picks its framework when it is created
// and keeps it: 'bootstrap5', 'custom' (no framework files at all), and the
// Bootstrap 6 to come is one more value. Every design made before this step
// was built on Bootstrap 5, which is what the default gives the rows already
// there. style_template / style_template_version record which template, in
// which edition, a design started from; empty for a design started blank.
function upgrade_2026_4_5_design_framework() {

	install_add_column('style', 'style_framework', "VARCHAR(20) NOT NULL DEFAULT 'bootstrap5'");
	install_add_column('style', 'style_template', "VARCHAR(64) NOT NULL DEFAULT ''");
	install_add_column('style', 'style_template_version', "VARCHAR(20) NOT NULL DEFAULT ''");

	install_note('Visual Page Editor: a design is created on Bootstrap 5 or as a custom design without a framework, and can start from a template.');

}

// The look and the colour palette of a visual-editor design (2026.4.5, 5.3;
// includes/fn/design_themes.php). style_look is the shape-and-type theme,
// style_palette the colours; each is empty (plain Bootstrap), a built-in key
// (pg_design_looks(), pg_design_palettes()) or file-<id> for one the operator
// saved from the editor as a design CSS file. The default leaves every
// design made before this step looking exactly as it did.
function upgrade_2026_4_5_design_look() {

	install_add_column('style', 'style_look', "VARCHAR(40) NOT NULL DEFAULT ''");
	install_add_column('style', 'style_palette', "VARCHAR(40) NOT NULL DEFAULT ''");

	install_note('Visual Page Editor: a design wears a look (corners, shadows, type) and a colour palette, chosen and changed under Settings, Design.');

}

// The list of scheduled jobs the general job may run (2026.4.5, 5.2).
//
// config.job_dispatch holds their names, comma separated. VARCHAR(255) fitted
// the catalogue of 2026.4.2; with the ERP and workspace jobs the full list is
// past 300 characters. The runtime session is not in strict mode, so the
// server cut the list at 255 without a word and the jobs at the end of the
// catalogue switched themselves off on every save. TEXT leaves room for any
// catalogue; the values already stored are kept as they are, and every
// reader treats NULL as an empty list.
function upgrade_2026_4_5_job_dispatch_list() {

	$column = install_column_info('config', 'job_dispatch');

	if (!is_array($column) || !isset($column['Type'])) {

		// Arrives with 2026.4.2, which always runs first.
		install_skipped(lang(array('string' => '{var:1} does not exist, skipped', 'vars' => 'config.job_dispatch')));

	} else if (stripos((string) $column['Type'], 'text') === false) {

		install_modify_column('config', 'job_dispatch', "TEXT NULL");

	} else {

		install_skipped(lang(array('string' => '{var:1} is already wide enough', 'vars' => 'config.job_dispatch')));

	}

	install_note('Scheduled jobs: any number of jobs can be switched on at once; the saved selection no longer loses the jobs at the end of the list.');

}

// Pinegrap AI in the workspace (2026.4.5, 5.110; includes/workspace/ai.php).
// The model at ai.pinegrap.com is asked with @ai the way Claude is asked with
// @Claude, and its requests wait in the same table: ws_ai_requests.agent says
// whose a request is ('claude', what every row before this step was, or
// 'ai'). The site holds the conversation with the model itself, one call at a
// time: ai_state is the conversation so far (JSON, cleared once answered),
// ai_steps the calls it has taken and ai_attempts the calls in a row that
// failed or were cut off. config holds the one site-wide connection: whether
// it is on, the application it writes through, the licence key (encrypted,
// in the "<ciphertext>:<iv>" shape encrypt_string_with_iv() answers), what
// the gateway last said about the key (valid | pending | invalid | expired),
// when, and until when it is valid, the model found, the last error and the
// time a failed call holds the requests back to. ws_channels.ai_access
// follows claude_access: 0 goes with the kind of channel (public yes, private
// no), 1 allowed, 2 not allowed.
function upgrade_2026_4_5_workspace_ai() {

	install_add_column('ws_ai_requests', 'agent', "VARCHAR(16) NOT NULL DEFAULT 'claude' AFTER status");
	install_add_column('ws_ai_requests', 'ai_state', "MEDIUMTEXT NULL");
	install_add_column('ws_ai_requests', 'ai_steps', "TINYINT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('ws_ai_requests', 'ai_attempts', "TINYINT UNSIGNED NOT NULL DEFAULT 0");
	install_add_index('ws_ai_requests', 'idx_agent', "INDEX idx_agent (agent, status, id)");

	install_add_column('ws_channels', 'ai_access', "TINYINT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('config', 'ws_ai_enabled', "TINYINT(1) NOT NULL DEFAULT 0");
	install_add_column('config', 'ws_ai_app_id', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('config', 'ws_ai_license', "TEXT NULL");
	install_add_column('config', 'ws_ai_license_state', "VARCHAR(16) NOT NULL DEFAULT ''");
	install_add_column('config', 'ws_ai_license_checked', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('config', 'ws_ai_license_expires', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('config', 'ws_ai_model', "TEXT NULL");
	install_add_column('config', 'ws_ai_error', "TEXT NULL");
	install_add_column('config', 'ws_ai_hold_until', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Workspace: Pinegrap AI can be asked in the channels and the notes with @ai, with the site\'s licence key.');

}

// A task's e-mail reminder in the workspace (2026.4.5, 5.111;
// includes/workspace/reminders.php). ws_tasks.due_time is the time on the due
// date, optional (NULL: the day, reckoned from 09:00 for a reminder);
// remind_minutes how long before it the reminder goes (NULL: none, 0: at the
// time itself); remind_at the moment worked out from them when the task is
// saved (0: nothing to send) and reminded_at when it went (0: not yet), so
// the reminders that are due are found by idx_remind alone.
// ws_task_recurrences.remind_each says whether every copy of a repeating
// task is reminded, the question its drawer asks. Nothing is backfilled: no
// task had a reminder before this step.
function upgrade_2026_4_5_workspace_task_reminders() {

	install_add_column('ws_tasks', 'due_time', "TIME NULL DEFAULT NULL AFTER due_date");
	install_add_column('ws_tasks', 'remind_minutes', "INT UNSIGNED NULL DEFAULT NULL AFTER due_time");
	install_add_column('ws_tasks', 'remind_at', "INT UNSIGNED NOT NULL DEFAULT 0 AFTER remind_minutes");
	install_add_column('ws_tasks', 'reminded_at', "INT UNSIGNED NOT NULL DEFAULT 0 AFTER remind_at");
	install_add_index('ws_tasks', 'idx_remind', "INDEX idx_remind (reminded_at, remind_at)");

	install_add_column('ws_task_recurrences', 'remind_each', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_note('Workspace: a task can carry a time on its due date and an e-mail reminder to the people on it.');

}

// Short links opened once or until a time, and guests in the workspace
// (2026.4.5, 5.112; includes/fn/core.php, router.php,
// includes/workspace/guests.php, workspace_guest.php).
//
// short_links.link_mode says how a link may be opened: 'permanent' (every
// row before this step), 'once' (the first visit spends it; used_at is when)
// or 'timed' (until expires_at). A link can have a token for its address
// instead of a name: token_hash is the SHA-256 of it (the token itself is
// shown once, when the link is made, and kept nowhere) and token_hint its
// first characters, so a list can tell two apart. use_count and last_used_at
// count the visits of a once or timed link. destination_type
// 'workspace_guest' is a guest's way into their room in the workspace
// (ws_guest_id): made from the workspace alone, never listed with the site's
// short links.
//
// ws_channels.kind 'guest' is a room for a conversation with one guest,
// somebody with no account: ws_guests is the guest (name, who started the
// conversation, when it ended, when the guest was last there), and
// ws_messages.sender_kind / ws_reactions.sender_kind 'guest' with sender_id
// the ws_guests row is what the guest writes (and
// ws_message_forwards.source_sender_kind 'guest' what of it staff forward
// to their own channels). ws_guest_sessions is a browser
// the guest came in with: session_hash the SHA-256 of its cookie, csrf the
// token its requests carry, ip the first part of its address, expires_at the
// end of the link it came by (0: a one-time link, kept while it is used).
function upgrade_2026_4_5_workspace_guests() {

	install_add_column('short_links', 'link_mode', "ENUM('permanent','once','timed') NOT NULL DEFAULT 'permanent'");
	install_add_column('short_links', 'token_hash', "CHAR(64) NOT NULL DEFAULT ''");
	install_add_column('short_links', 'token_hint', "VARCHAR(8) NOT NULL DEFAULT ''");
	install_add_column('short_links', 'expires_at', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('short_links', 'used_at', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('short_links', 'use_count', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('short_links', 'last_used_at', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('short_links', 'ws_guest_id', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_index('short_links', 'idx_token', "INDEX idx_token (token_hash)");
	install_add_index('short_links', 'idx_ws_guest', "INDEX idx_ws_guest (ws_guest_id)");

	// An enum is widened only when it does not carry the value yet, so a
	// second run does not rebuild the tables for nothing.
	$widen = function ($table, $column, $value, $definition) {

		$info = install_column_info($table, $column);

		if (is_array($info) && (strpos((string) $info['Type'], "'" . $value . "'") === false)) {
			install_modify_column($table, $column, $definition);
		} else {
			install_skipped(lang(array('string' => '{var:1} already exists', 'vars' => $table . '.' . $column . ' ' . $value)));
		}
	};

	$widen('short_links', 'destination_type', 'workspace_guest', "ENUM('page','product_group','product','url','file','workspace_guest') NOT NULL DEFAULT 'page'");
	$widen('ws_channels', 'kind', 'guest', "ENUM('public','private','guest') NOT NULL DEFAULT 'public'");
	$widen('ws_messages', 'sender_kind', 'guest', "ENUM('user','app','system','guest') NOT NULL DEFAULT 'user'");
	$widen('ws_reactions', 'sender_kind', 'guest', "ENUM('user','app','guest') NOT NULL DEFAULT 'user'");
	$widen('ws_message_forwards', 'source_sender_kind', 'guest', "ENUM('user','app','system','guest') NOT NULL DEFAULT 'user'");

	// The guest votes in the room's polls and ticks its checklists, the same
	// ones staff use: a vote or a tick is a user's (guest_id 0) or a guest's
	// (user_id 0). A guest's vote is one row per option like a user's, so the
	// key of the votes takes guest_id in.
	install_add_column('ws_poll_votes', 'guest_id', "INT UNSIGNED NOT NULL DEFAULT 0 AFTER user_id");
	install_add_column('ws_checks', 'guest_id', "INT UNSIGNED NOT NULL DEFAULT 0 AFTER user_id");

	$vote_key = array_map(function ($row) {
		return (string) $row['Column_name'];
	}, (array) db_items("SHOW INDEX FROM ws_poll_votes WHERE Key_name = 'PRIMARY'"));

	if (!in_array('guest_id', $vote_key, true)) {
		db("ALTER TABLE ws_poll_votes DROP PRIMARY KEY, ADD PRIMARY KEY (poll_id, option_id, user_id, guest_id)");
		install_ran(lang(array('string' => '{var:1} changed', 'vars' => 'ws_poll_votes PRIMARY')));
	} else {
		install_skipped(lang(array('string' => '{var:1} already exists', 'vars' => 'ws_poll_votes PRIMARY (guest_id)')));
	}

	install_create_table('ws_guests', "CREATE TABLE ws_guests (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		channel_id   INT UNSIGNED NOT NULL DEFAULT 0,
		name         VARCHAR(60) NOT NULL DEFAULT '',
		created_by   INT UNSIGNED NOT NULL DEFAULT 0,
		created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		ended_at     INT UNSIGNED NOT NULL DEFAULT 0,
		ended_by     INT UNSIGNED NOT NULL DEFAULT 0,
		last_seen_at INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_channel (channel_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_guest_sessions', "CREATE TABLE ws_guest_sessions (
		id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
		guest_id        INT UNSIGNED NOT NULL DEFAULT 0,
		link_id         INT UNSIGNED NOT NULL DEFAULT 0,
		session_hash    CHAR(64) NOT NULL DEFAULT '',
		csrf            CHAR(32) NOT NULL DEFAULT '',
		ip              VARCHAR(45) NOT NULL DEFAULT '',
		user_agent_hash CHAR(40) NOT NULL DEFAULT '',
		created_at      INT UNSIGNED NOT NULL DEFAULT 0,
		last_seen_at    INT UNSIGNED NOT NULL DEFAULT 0,
		expires_at      INT UNSIGNED NOT NULL DEFAULT 0,
		ended_at        INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_session (session_hash),
		KEY idx_guest (guest_id, ended_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Short links: a link can be opened once or until a time, with a generated address. Workspace: staff can talk with a guest who has no account, in a room of their own.');

}


// Page changes an assistant proposes in the Visual Page Editor (2026.4.5,
// 5.70; includes/designer_ai.php). Claude or Pinegrap AI is asked from the
// editor (the selected part or the whole page) or from a workspace channel;
// the request waits in ws_ai_requests with the page it is about: page_id,
// node_id (the selected node, '' for the whole page) and design_base (the
// tree the editor tab held when it asked, since that tab may not have been
// saved). A request from the editor keeps its words in note_text and its
// answer in note_reply, like a request from a note: it has no channel
// message. design_proposals is what comes back: ops the operations (JSON)
// against the tree whose fingerprint is base_hash, steps what each of them
// does in words (JSON), summary the assistant's one paragraph, agent claude |
// ai | app (another application of the external API) with app_id,
// channel_id / message_id the answer it was proposed under (0 from the
// editor). applied_to says where it was applied: editor (onto an open tab,
// saved from there) or page (onto the saved page, from the workspace), in
// which case before_tree is the page's tree before and after_hash what it
// became, so the change can be taken back while nobody has saved over it.
function upgrade_2026_4_5_design_ai() {

	install_add_column('ws_ai_requests', 'page_id', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('ws_ai_requests', 'node_id', "VARCHAR(64) NOT NULL DEFAULT ''");
	install_add_column('ws_ai_requests', 'design_base', "MEDIUMTEXT NULL");
	install_add_index('ws_ai_requests', 'idx_page', "INDEX idx_page (page_id, status)");

	install_create_table('design_proposals', "CREATE TABLE design_proposals (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		request_id   INT UNSIGNED NOT NULL DEFAULT 0,
		agent        VARCHAR(16) NOT NULL DEFAULT '',
		app_id       INT UNSIGNED NOT NULL DEFAULT 0,
		page_id      INT UNSIGNED NOT NULL DEFAULT 0,
		style_id     INT UNSIGNED NOT NULL DEFAULT 0,
		node_id      VARCHAR(64) NOT NULL DEFAULT '',
		ops          MEDIUMTEXT NULL,
		steps        TEXT NULL,
		summary      VARCHAR(1000) NOT NULL DEFAULT '',
		base_hash    CHAR(40) NOT NULL DEFAULT '',
		status       ENUM('pending','applied','dismissed','stale','failed','reverted') NOT NULL DEFAULT 'pending',
		applied_to   VARCHAR(10) NOT NULL DEFAULT '',
		before_tree  MEDIUMTEXT NULL,
		after_hash   CHAR(40) NOT NULL DEFAULT '',
		requested_by INT UNSIGNED NOT NULL DEFAULT 0,
		channel_id   INT UNSIGNED NOT NULL DEFAULT 0,
		message_id   INT UNSIGNED NOT NULL DEFAULT 0,
		decided_by   INT UNSIGNED NOT NULL DEFAULT 0,
		decided_at   INT UNSIGNED NOT NULL DEFAULT 0,
		created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		error        VARCHAR(255) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY idx_page (page_id, status, id),
		KEY idx_request (request_id),
		KEY idx_message (message_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Visual Page Editor: Claude or Pinegrap AI can be asked to change the selected part of a page or the whole page, from the editor or from a workspace channel. The change comes as a proposal that is previewed and applied by a person.');

}

// People signing in to the external API from their own device (2026.4.5, 5.71;
// includes/api/devices.php).
//
// Until now every call was an application's: a key and a secret held by a
// server, acting with its owner's rights. A phone in a staff member's pocket
// cannot hold a secret - anything shipped inside an app can be read back out
// of it - and should not act as whoever created the key either. It acts as the
// person who signed in on it.
//
// api_apps.kind tells the two apart. A 'device' application carries no secret
// at all; it is the operator's switch and ceiling for device sign-in: its
// status turns sign-in on and off, its scopes cap what any device may reach,
// and its rate limit applies per device.
//
// api_devices is one row per signed-in device. Neither token is stored: only
// their HMACs, the same way an application secret is kept. The access token is
// short lived and looked up on every call; the refresh token is rotated every
// time it is used, and the previous one is remembered for a moment so that an
// app whose connection dropped mid-refresh is not signed out, while one that is
// replayed later is recognised as stolen and ends the device.
//
// api_device_rate counts calls per device and minute, the same single upsert
// api_rate_bucket uses per application: sharing the application's bucket would
// let one busy phone use up the allowance of every other person's.
function upgrade_2026_4_5_api_devices() {

	install_add_column('api_apps', 'kind', "ENUM('server','device') NOT NULL DEFAULT 'server'");

	install_create_table('api_devices', "CREATE TABLE api_devices (
		id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
		app_id              INT UNSIGNED NOT NULL DEFAULT 0,
		user_id             INT UNSIGNED NOT NULL DEFAULT 0,
		name                VARCHAR(100) NOT NULL DEFAULT '',
		platform            VARCHAR(20) NOT NULL DEFAULT '',
		app_version         VARCHAR(40) NOT NULL DEFAULT '',
		access_hash         CHAR(64) NOT NULL,
		access_expires      INT UNSIGNED NOT NULL DEFAULT 0,
		refresh_hash        CHAR(64) NOT NULL,
		refresh_expires     INT UNSIGNED NOT NULL DEFAULT 0,
		refresh_prev_hash   CHAR(64) NOT NULL DEFAULT '',
		refresh_rotated_at  INT UNSIGNED NOT NULL DEFAULT 0,
		created_timestamp   INT UNSIGNED NOT NULL DEFAULT 0,
		last_used_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		last_used_ip        VARCHAR(45) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		UNIQUE KEY uq_access (access_hash),
		UNIQUE KEY uq_refresh (refresh_hash),
		KEY idx_refresh_prev (refresh_prev_hash),
		KEY idx_user (user_id),
		KEY idx_app (app_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('api_device_rate', "CREATE TABLE api_device_rate (
		device_id    INT UNSIGNED NOT NULL,
		window_start INT UNSIGNED NOT NULL,
		hits         INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (device_id, window_start)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('External API: staff can sign in from a device (a mobile app) with their own account, once a "Team devices" application is switched on in API settings.');

}
