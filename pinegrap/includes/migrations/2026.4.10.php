<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.4.10. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// 2026.4.10 - the release that follows 2026.4.9.
//
// One entry point, one step per subsystem, called in the order the work was
// done. The numbers in the comments are labels only and follow the ranges
// 2026.4.9 used (general work from 10.1, ERP 10.58-10.69 and from 10.90, API
// 10.70-10.79, the workspace 10.80-10.89 and 10.110-10.119); the order of the
// calls is what runs.
function upgrade_to_2026_4_10() {

	upgrade_2026_4_10_oauth();                  // 10.70
	upgrade_2026_4_10_oauth_metadata();         // 10.71
	upgrade_2026_4_10_language_redirect();      // 10.1
	upgrade_2026_4_10_waf_countries();          // 10.2
	upgrade_2026_4_10_workspace_delegation();   // 10.80
	upgrade_2026_4_10_workspace_calendar_feeds(); // 10.81

}

// The site as an OAuth 2.1 authorization server (2026.4.10, 10.70; oauth.php,
// includes/oauth/).
//
// An outside application - an MCP client, another product - asks a person to
// let it act for them. The person signs in on the site itself, sees what the
// application asks for and agrees or refuses; the application gets tokens and
// never sees the password.
//
// api_apps.kind gains 'oauth'. Like a 'device' application it carries no
// secret: it is the operator's switch and ceiling for every OAuth connection.
//
// oauth_clients holds the applications that registered themselves (RFC 7591)
// or were added by the operator. client_id is public; a confidential client's
// secret is kept as an HMAC only, like an application secret.
//
// oauth_codes holds authorization codes for the minute between the consent
// screen and the token request. A code is marked used rather than deleted, so
// that one presented a second time can be recognised and the connection it
// produced ended (RFC 6749 4.1.2).
//
// The tokens themselves are api_devices rows: a connection is one more way of
// a person being signed in through the API, and every path that already ends
// a person's devices - a new password, signing out everywhere, a deleted
// account - ends their OAuth connections with them. The three new columns say
// which client the row belongs to, which scopes the person agreed to, and
// which resource (RFC 8707) the tokens were issued for.
function upgrade_2026_4_10_oauth() {

	// The enum is widened from what is there, never narrowed: reading it first
	// keeps any value a later release may have added.
	$kind = install_column_info('api_apps', 'kind');

	if (is_array($kind) && (stripos((string)$kind['Type'], "'oauth'") === false)) {

		install_modify_column('api_apps', 'kind', "ENUM('server','device','oauth') NOT NULL DEFAULT 'server'");

	}

	install_create_table('oauth_clients', "CREATE TABLE oauth_clients (
		id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
		client_id           VARCHAR(64) NOT NULL,
		secret_hash         CHAR(64) NOT NULL DEFAULT '',
		name                VARCHAR(100) NOT NULL DEFAULT '',
		client_uri          VARCHAR(255) NOT NULL DEFAULT '',
		redirect_uris       TEXT NULL,
		auth_method         ENUM('none','client_secret_basic','client_secret_post') NOT NULL DEFAULT 'none',
		software_id         VARCHAR(100) NOT NULL DEFAULT '',
		software_version    VARCHAR(40) NOT NULL DEFAULT '',
		registered_by       ENUM('dynamic','panel') NOT NULL DEFAULT 'dynamic',
		status              ENUM('active','disabled') NOT NULL DEFAULT 'active',
		created_user_id     INT UNSIGNED NOT NULL DEFAULT 0,
		created_ip          VARCHAR(45) NOT NULL DEFAULT '',
		created_timestamp   INT UNSIGNED NOT NULL DEFAULT 0,
		last_used_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_client_id (client_id),
		KEY idx_registered (registered_by, last_used_timestamp)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('oauth_codes', "CREATE TABLE oauth_codes (
		code_hash           CHAR(64) NOT NULL,
		client_id           INT UNSIGNED NOT NULL DEFAULT 0,
		user_id             INT UNSIGNED NOT NULL DEFAULT 0,
		redirect_uri        TEXT NULL,
		scopes              TEXT NULL,
		resource            VARCHAR(255) NOT NULL DEFAULT '',
		code_challenge      VARCHAR(128) NOT NULL DEFAULT '',
		expires             INT UNSIGNED NOT NULL DEFAULT 0,
		used_timestamp      INT UNSIGNED NOT NULL DEFAULT 0,
		device_id           INT UNSIGNED NOT NULL DEFAULT 0,
		created_timestamp   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (code_hash),
		KEY idx_expires (expires)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('api_devices', 'oauth_client_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('api_devices', 'scopes', "TEXT NULL");

	install_add_column('api_devices', 'resource', "VARCHAR(255) NOT NULL DEFAULT ''");

	install_add_index('api_devices', 'idx_oauth_client', "INDEX idx_oauth_client (oauth_client_id)");

	install_note('External API: outside applications (an MCP client such as Claude) can connect to the site with OAuth 2.1 once a "Connected apps (OAuth)" application is switched on in API settings. Each connection is agreed by a person on the site and can be ended at any time.');

}

// Clients identified by a metadata document (2026.4.10, 10.71; Client ID
// Metadata Document, includes/oauth/server.php).
//
// An MCP client may name itself with an https address instead of
// registering: the site reads the client's name and redirect addresses from
// that address and keeps them as an oauth_clients row marked 'metadata'.
//
// client_id grows to hold such an address. It is ascii and compared
// byte-for-byte - a client_id is case-sensitive, and an ascii column keeps
// the unique key inside the 767-byte index limit of older MySQL row formats,
// which a 255-character utf8mb4 column would not.
//
// metadata_expires is when the copy of the document should be read again.
function upgrade_2026_4_10_oauth_metadata() {

	$client_id = install_column_info('oauth_clients', 'client_id');

	if (is_array($client_id) && (stripos((string)$client_id['Type'], 'varchar(255)') === false)) {

		install_modify_column('oauth_clients', 'client_id', "VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL");

	}

	$registered_by = install_column_info('oauth_clients', 'registered_by');

	if (is_array($registered_by) && (stripos((string)$registered_by['Type'], "'metadata'") === false)) {

		install_modify_column('oauth_clients', 'registered_by', "ENUM('dynamic','panel','metadata') NOT NULL DEFAULT 'dynamic'");

	}

	install_add_column('oauth_clients', 'metadata_expires', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('External API: the site now has an MCP server (mcp.php). An AI application such as Claude connects to it with OAuth and works with the site through the same permissions as the API.');

}

// Automatic language redirect (2026.4.10, 10.1; includes/fn/language_redirect.php,
// includes/geo.php).
//
// A visitor who arrives on a page in the source language is sent to the same
// page in the language they read, when the site is served in it. Four config
// columns: the switch (off), what decides the language - the browser's
// language, the visitor's country, or the browser first and the country
// after it - whether the home page alone or every page of a visual design is
// redirected, and the language a visitor gets whose languages the site has
// none of ('' keeps the page as it is). The country comes from the edge's
// header or from the site's own IP-to-country file under data/geo/, which is
// fetched by the general job and is not a table.
function upgrade_2026_4_10_language_redirect() {

	install_add_column('config', 'language_redirect', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('config', 'language_redirect_mode', "ENUM('language','country','both') NOT NULL DEFAULT 'both'");

	install_add_column('config', 'language_redirect_scope', "ENUM('home','all') NOT NULL DEFAULT 'home'");

	install_add_column('config', 'language_redirect_fallback', "TEXT NULL");

	install_note('Languages: visitors can be sent to the language they read automatically, by their browser\'s language or their location. It is off until it is switched on under Site Settings, Languages and Translation.');

}

// Firewall country restriction (2026.4.10, 10.2; waf_handle_country() in
// waf.php, the country from includes/geo.php).
//
// Off by default. The mode - refuse the listed countries, or every country but
// the listed ones - the list of two-letter codes, whether a request no source
// can place is refused (no: a site without a location database must not shut
// out everyone), and three exemptions that are on: the control panel, the
// external API with the MCP and OAuth endpoints, and search engine crawlers
// whose address proves their name.
function upgrade_2026_4_10_waf_countries() {

	install_add_column('config', 'waf_country_mode', "ENUM('off','allow','block') NOT NULL DEFAULT 'off'");

	install_add_column('config', 'waf_country_list', "TEXT NULL");

	install_add_column('config', 'waf_country_unknown', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('config', 'waf_country_exempt_panel', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_add_column('config', 'waf_country_exempt_api', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_add_column('config', 'waf_country_exempt_bots', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_note('Firewall: requests can be refused by country, or let in only from the countries chosen. It is off until it is set under Site Settings, Firewall.');

}

// A task handed to an assistant (2026.4.10, 10.80; includes/workspace/delegation.php).
//
// The people on a task stay the ones who answer for it; an assistant - Pinegrap
// AI, Claude, and later an MCP server's alias - is the task's delegate and does
// the work it can. ws_tasks.delegate names the assistant by its key ('' for
// none), with who handed the task over and when.
//
// Every run of the assistant on a task is a request in the queue both
// assistants already work from, tied to the task by ws_ai_requests.task_id.
// Like a request from a note or from the page editor it has no message: the
// instruction is kept in note_text and the answer in note_reply.
//
// What the assistant proposes on a task waits in ws_ai_changes like a change
// proposed in a channel, tied to the task by task_id instead of to a message.
function upgrade_2026_4_10_workspace_delegation() {

	install_add_column('ws_tasks', 'delegate', "VARCHAR(32) NOT NULL DEFAULT ''");

	install_add_column('ws_tasks', 'delegated_by', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('ws_tasks', 'delegated_at', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_index('ws_tasks', 'idx_delegate', "INDEX idx_delegate (delegate, status)");

	install_add_column('ws_ai_requests', 'task_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_index('ws_ai_requests', 'idx_task', "INDEX idx_task (task_id)");

	install_add_column('ws_ai_changes', 'task_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_index('ws_ai_changes', 'idx_task', "INDEX idx_task (task_id)");

	install_note('Workspace: a task can be handed to an assistant (Pinegrap AI or Claude). The people on it stay responsible; what the assistant proposes waits for them to apply it.');

}

// Workspace calendar subscriptions (2026.4.10, 10.81;
// includes/workspace/calendar_feed.php, workspace_ical.php).
//
// A member of the team subscribes to their own work in their phone's or
// desktop's calendar: the tasks given to them, the plan items about them, the
// days off and the approvals waiting for them, read from an address of their
// own. One row per person (uk_user). token_hash is the SHA-256 of the token in
// the address and what a request is looked up by (uk_token); token_secret is
// the same token encrypted with the site's key, "<ciphertext>:<iv>", so the
// screen can show the address again ('' where the site has no key). kinds is
// what the calendar carries (tasks, plan, holidays, approvals), details
// whether descriptions and notes go out with it. fetched_* is the last read,
// written once a minute at most, so the person sees whether a calendar reads
// the address and from where.
function upgrade_2026_4_10_workspace_calendar_feeds() {

	install_create_table('ws_calendar_feeds', "CREATE TABLE ws_calendar_feeds (
		id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id        INT UNSIGNED NOT NULL,
		token_hash     CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		token_secret   TEXT NULL,
		kinds          VARCHAR(100) NOT NULL DEFAULT '',
		details        TINYINT(1) NOT NULL DEFAULT 0,
		created_at     INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at     INT UNSIGNED NOT NULL DEFAULT 0,
		fetched_at     INT UNSIGNED NOT NULL DEFAULT 0,
		fetched_ip     VARCHAR(45) NOT NULL DEFAULT '',
		fetched_agent  VARCHAR(255) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		UNIQUE KEY uk_user (user_id),
		UNIQUE KEY uk_token (token_hash)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: every member can subscribe to their tasks, plan items, days off and approval deadlines in their phone or desktop calendar (Apple, Google, Outlook) with a private address of their own, from the account menu.');

}
