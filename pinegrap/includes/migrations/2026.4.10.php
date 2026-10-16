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
	upgrade_2026_4_10_erp_purchase_orders();    // 10.58
	upgrade_2026_4_10_connect();                // 10.72
	upgrade_2026_4_10_workspace_mcp();          // 10.82
	upgrade_2026_4_10_signin();                 // 10.74
	upgrade_2026_4_10_search_console();         // 10.73
	upgrade_2026_4_10_erp_storefront_prices();  // 10.59
	upgrade_2026_4_10_erp_open_account();       // 10.60
	upgrade_2026_4_10_signin_google();          // 10.75
	upgrade_2026_4_10_erp_late_fee();           // 10.61
	upgrade_2026_4_10_workspace_approval_guests(); // 10.84
	upgrade_2026_4_10_workspace_meetings();     // 10.86
	upgrade_2026_4_10_visitor_retention();      // 10.3
	upgrade_2026_4_10_workspace_mcp_agent();    // 10.89
	upgrade_2026_4_10_workspace_mcp_tasks();    // 10.110
	upgrade_2026_4_10_workspace_mcp_conversations(); // 10.111
	upgrade_2026_4_10_workspace_status();       // 10.87
	upgrade_2026_4_10_workspace_inbox_deleted(); // 10.88
	upgrade_2026_4_10_style_bin();              // 10.4
	upgrade_2026_4_10_noise_theme_file();       // 10.5
	upgrade_2026_4_10_webhook_channel_group();  // 10.76
	upgrade_2026_4_10_visitor_texts();          // 10.6

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

// ERP: purchase orders (2026.4.10, 10.58; includes/erp/purchase_orders.php).
//
// What the store asks a supplier for, before the supplier's bill exists. Like
// a quote it moves no money, no stock and no tax, so it has a table of its own
// rather than a doc_type on erp_invoices, where every report would have to
// learn to leave it out. It takes a number of its own series when first saved
// (erp_document_series, doc_kind 'purchase_order'). line_data are the lines as
// they were worked out, shaped like erp_invoice_items rows, each with a key of
// its own (source_line) that stays with the line while the order is edited.
// expected_date is the day the goods are expected; sent_at is when the order
// first went to the supplier (by e-mail, or marked sent by hand).
//
// The order becomes purchase invoice drafts at the quantities still to come,
// once or several times for a delivery in parts. erp_invoices.purchase_order_id
// names the order an invoice was made from, and erp_invoice_items.source_line
// the order line each invoice line stands for. The order's status reads the
// issued purchase invoices back through those two: partial while some lines
// are still to come, received once every line has come.
//
// erp_document_series.doc_kind is widened from what is there, never narrowed:
// a value the ENUM does not know is stored as nothing and the series never
// opens (4.46).
function upgrade_2026_4_10_erp_purchase_orders() {

	install_create_table('erp_purchase_orders', "CREATE TABLE erp_purchase_orders (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		series            VARCHAR(10) NOT NULL DEFAULT '',
		number            INT UNSIGNED NOT NULL DEFAULT 0,
		issue_year        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		full_number       VARCHAR(32) NOT NULL DEFAULT '',
		account_id        INT UNSIGNED NOT NULL DEFAULT 0,
		issue_date        DATE NOT NULL DEFAULT '0000-00-00',
		expected_date     DATE NOT NULL DEFAULT '0000-00-00',
		currency          CHAR(3) NOT NULL DEFAULT 'TRY',
		exchange_rate     DECIMAL(15,6) NOT NULL DEFAULT 1.000000,
		subtotal          BIGINT NOT NULL DEFAULT 0,
		discount_total    BIGINT NOT NULL DEFAULT 0,
		tax_total         BIGINT NOT NULL DEFAULT 0,
		tax2_total        BIGINT NOT NULL DEFAULT 0,
		withholding_total BIGINT NOT NULL DEFAULT 0,
		grand_total       BIGINT NOT NULL DEFAULT 0,
		grand_total_base  BIGINT NOT NULL DEFAULT 0,
		status            ENUM('open','sent','partial','received','cancelled') NOT NULL DEFAULT 'open',
		line_data         MEDIUMTEXT NULL,
		notes             TEXT NULL,
		created_by        INT UNSIGNED NOT NULL DEFAULT 0,
		created_at        INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at        INT UNSIGNED NOT NULL DEFAULT 0,
		sent_at           INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_number (series, number, issue_year),
		KEY idx_account (account_id, issue_date),
		KEY idx_status (status, expected_date)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('erp_invoices', 'purchase_order_id', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_index('erp_invoices', 'idx_purchase_order', "INDEX idx_purchase_order (purchase_order_id)");

	install_add_column('erp_invoice_items', 'source_line', "VARCHAR(16) NOT NULL DEFAULT ''");

	$doc_kind = install_column_info('erp_document_series', 'doc_kind');

	if (is_array($doc_kind) && (stripos((string)$doc_kind['Type'], "'purchase_order'") === false)) {

		// Every value the column holds today is kept, the new one is added.
		preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", (string)$doc_kind['Type'], $matches);
		$values = array();

		foreach ($matches[1] as $value) {
			$values[] = "'" . $value . "'";
		}

		$values[] = "'purchase_order'";

		install_modify_column('erp_document_series', 'doc_kind', "ENUM(" . implode(',', $values) . ") NOT NULL DEFAULT 'sales_invoice'");

	} elseif (is_array($doc_kind)) {

		install_skipped('erp_document_series.doc_kind already knows about purchase orders');

	}

	install_note('ERP: purchase orders to suppliers, with their own number, printed and e-mailed, turned into purchase invoice drafts as the goods arrive (in one go or in parts). An order moves no money and no stock; the purchase invoice does.');

}

// Connected accounts (2026.4.10, 10.72; includes/fn/connect.php,
// connections.php, connect.php).
//
// The site as an OAuth client: it signs in to the site owner's or a person's
// Google or Microsoft account and acts for them there (sending mail, backups,
// Search Console), each site with an application of its own in the
// provider's console.
//
// connect_apps holds that application per provider: the client ID, the
// client secret sealed with the site's encryption key (never shown back) and
// the provider's options (Microsoft's directory).
//
// connect_accounts holds the accounts connected through it. user_id 0 is the
// site's own account, otherwise the person's. external_id is who the account
// is at the provider (the sub claim), so connecting the same account again
// updates its row. purposes are the features it was connected for, scopes
// what the provider granted. secret is the refresh token and the access
// tokens fetched with it, sealed together. status 'reauth' means the provider
// refused the refresh token and the account has to be connected again.
// Tokens a feature fetches by other means (an accounting service's password
// grant) are rows with provider 'service', keyed by external_id.
function upgrade_2026_4_10_connect() {

	install_create_table('connect_apps', "CREATE TABLE connect_apps (
		provider          VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		client_id         VARCHAR(255) NOT NULL DEFAULT '',
		secret            TEXT NULL,
		options           TEXT NULL,
		enabled           TINYINT(1) NOT NULL DEFAULT 1,
		updated_user_id   INT UNSIGNED NOT NULL DEFAULT 0,
		updated_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (provider)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('connect_accounts', "CREATE TABLE connect_accounts (
		id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
		provider            VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		user_id             INT UNSIGNED NOT NULL DEFAULT 0,
		external_id         VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
		label               VARCHAR(255) NOT NULL DEFAULT '',
		purposes            VARCHAR(255) NOT NULL DEFAULT '',
		scopes              TEXT NULL,
		secret              MEDIUMTEXT NULL,
		status              ENUM('active','reauth','disabled') NOT NULL DEFAULT 'active',
		last_error          VARCHAR(255) NOT NULL DEFAULT '',
		created_user_id     INT UNSIGNED NOT NULL DEFAULT 0,
		created_timestamp   INT UNSIGNED NOT NULL DEFAULT 0,
		refreshed_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		last_used_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uk_account (provider, user_id, external_id),
		KEY idx_user (user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Connected accounts: the site can connect Google and Microsoft accounts with an application of its own (Settings, Connections), keeping their tokens encrypted and renewing them, so that features such as sending mail through them can follow.');

}

// MCP servers in the Workspace (2026.4.10, 10.82; includes/mcp/client.php,
// includes/workspace/mcp.php).
//
// An administrator adds another service's MCP server under an alias of its
// own (@github, @docs); the site then speaks to that server as a client.
// ws_mcp_servers holds one row per server. alias is what people write and is
// unique. auth_kind says how the site proves who it is: no credential, a
// bearer token, a header of the service's own, user name and password, or an
// OAuth connection; secret is that credential as JSON encrypted with the
// site's key, "<ciphertext>:<iv>", and never shown again. protocol and
// session_id are what the last handshake agreed on, so a request does not
// shake hands again every time (session_id stays '' for a server of the
// current generation, which has none). tools is the server's tool list as it
// was last read (JSON), tools_at when, tools_ttl for how long the server said
// it may be kept. tool_rules is the administrator's word on each tool, by
// name: a tool left out of it is not used.
//
// ws_mcp_calls is the record of every tool called: by whom, for which
// request or channel, with which arguments, and how it ended. A call that
// waits for somebody's approval is a row in status pending.
//
// ws_ai_requests.mcp_server_id ties a request written to an alias to its
// server: Pinegrap AI answers it with that server's tools only.
function upgrade_2026_4_10_workspace_mcp() {

	install_create_table('ws_mcp_servers', "CREATE TABLE ws_mcp_servers (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		alias        VARCHAR(32) NOT NULL,
		name         VARCHAR(100) NOT NULL DEFAULT '',
		url          VARCHAR(500) NOT NULL DEFAULT '',
		auth_kind    ENUM('none','bearer','header','basic','oauth') NOT NULL DEFAULT 'none',
		secret       TEXT NULL,
		status       ENUM('active','disabled') NOT NULL DEFAULT 'active',
		protocol     VARCHAR(20) NOT NULL DEFAULT '',
		session_id   VARCHAR(255) NOT NULL DEFAULT '',
		server_info  VARCHAR(200) NOT NULL DEFAULT '',
		instructions TEXT NULL,
		tools        MEDIUMTEXT NULL,
		tools_at     INT UNSIGNED NOT NULL DEFAULT 0,
		tools_ttl    INT UNSIGNED NOT NULL DEFAULT 0,
		tool_rules   TEXT NULL,
		last_error   VARCHAR(255) NOT NULL DEFAULT '',
		last_used    INT UNSIGNED NOT NULL DEFAULT 0,
		created_by   INT UNSIGNED NOT NULL DEFAULT 0,
		created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uk_alias (alias)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_mcp_calls', "CREATE TABLE ws_mcp_calls (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		server_id    INT UNSIGNED NOT NULL DEFAULT 0,
		tool         VARCHAR(128) NOT NULL DEFAULT '',
		arguments    MEDIUMTEXT NULL,
		via          ENUM('ai','form') NOT NULL DEFAULT 'ai',
		requested_by INT UNSIGNED NOT NULL DEFAULT 0,
		channel_id   INT UNSIGNED NOT NULL DEFAULT 0,
		message_id   INT UNSIGNED NOT NULL DEFAULT 0,
		request_id   INT UNSIGNED NOT NULL DEFAULT 0,
		status       ENUM('pending','done','failed','dismissed') NOT NULL DEFAULT 'done',
		approved_by  INT UNSIGNED NOT NULL DEFAULT 0,
		error        VARCHAR(255) NOT NULL DEFAULT '',
		duration_ms  INT UNSIGNED NOT NULL DEFAULT 0,
		created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		finished_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_server (server_id, id),
		KEY idx_status (status, id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('ws_ai_requests', 'mcp_server_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Workspace: an administrator can add other services\' MCP servers under an alias (Workspace Settings › MCP servers) and choose which of their tools may be used.');

}

// Sign in with Microsoft and Apple (2026.4.10, 10.74; includes/fn/signin.php,
// signin.php).
//
// user_identities ties an account to who the person is at a provider: the
// provider key (a file in includes/connect/providers/), subject (the stable
// id the provider gives the person: Apple's sub, Microsoft's directory and
// object id) and the e-mail address it last reported. One row per person and
// provider; the same subject cannot belong to two accounts. Google kept its
// own column (user.user_google_id) until 10.75 moved its links here.
//
// config.signin_providers lists the providers whose sign-in buttons are
// shown, comma separated; empty means none, so a site upgrading changes
// nothing until the operator turns one on. The applications themselves live
// in connect_apps (10.72).
function upgrade_2026_4_10_signin() {

	install_create_table('user_identities', "CREATE TABLE user_identities (
		id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id             INT UNSIGNED NOT NULL DEFAULT 0,
		provider            VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		subject             VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		email               VARCHAR(255) NOT NULL DEFAULT '',
		created_timestamp   INT UNSIGNED NOT NULL DEFAULT 0,
		last_used_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uk_subject (provider, subject),
		UNIQUE KEY uk_user_provider (user_id, provider)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('config', 'signin_providers', "TEXT NULL");

	install_note('Sign in with Microsoft and Apple: members and staff can sign in with a Microsoft or Apple account next to Google, once the operator sets up the application and turns the button on in Settings, Security.');

}

// Google Search Console (2026.4.10, 10.73; includes/fn/search_console.php,
// search_console.php, seo_search_job.php).
//
// The site reads from Search Console which searches bring people to it and
// how often its pages are shown, through a connected Google account
// (connect_accounts, purpose search_console).
//
// seo_search_daily keeps the figures per day at three levels, told apart by
// kind: 'site' (the day's totals), 'page' (one row per address) and 'query'
// (one row per search). row_key is a hash of the kind, the page and the
// query, so a day re-read from Google replaces its rows instead of adding
// to them. position is Google's average position for the row; averages over
// several days are weighted by impressions.
//
// config.search_console is the setup as JSON: the connected account, the
// Search Console property and the state of the daily download.
// config.google_site_verification is the code Google asks the home page to
// carry in a meta tag, written by the one-click verification and kept when
// the site is unlinked, so Google does not drop the verification.
function upgrade_2026_4_10_search_console() {

	install_create_table('seo_search_daily', "CREATE TABLE seo_search_daily (
		day         DATE NOT NULL,
		row_key     CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		kind        ENUM('site','page','query') NOT NULL,
		page        VARCHAR(2048) NOT NULL DEFAULT '',
		query       VARCHAR(500) NOT NULL DEFAULT '',
		clicks      INT UNSIGNED NOT NULL DEFAULT 0,
		impressions INT UNSIGNED NOT NULL DEFAULT 0,
		position    DECIMAL(8,2) NOT NULL DEFAULT 0,
		PRIMARY KEY (day, row_key),
		KEY idx_kind_day (kind, day)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('config', 'search_console', "TEXT NULL");
	install_add_column('config', 'google_site_verification', "TEXT NULL");

	install_note('Google Search Console: once a Google account is connected for it, the site verifies itself, adds itself to Search Console, submits its sitemap and downloads every day which searches brought visitors and how often its pages were shown.');

}

// The prices an account has agreed, in the store (2026.4.10, 10.59;
// pg_erp_member_prices() in includes/fn/ecommerce.php, the rule in
// erp_storefront_price(), includes/erp/storefront.php).
//
// One switch, off: a member whose contact is linked to an active customer
// account sees and pays the account's own price for a product, or the list
// price less the account's discount (erp_account_prices, erp_accounts.
// discount_rate), wherever the store shows a price and in the cart; a
// campaign that goes lower still wins, the two never stack. Off, the store's
// prices are untouched.
function upgrade_2026_4_10_erp_storefront_prices() {

	install_add_column('config', 'erp_storefront_account_prices', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_note('ERP: the prices agreed with an account can be shown and charged in the store to the member linked to it. It is off until it is switched on under Settings, Ecommerce, ERP.');

}

// Open-account payment at checkout (2026.4.10, 10.60; pg_erp_open_account()
// in includes/fn/ecommerce.php, the rule in erp_storefront_on_account(),
// includes/erp/storefront.php).
//
// One switch, off: a member whose contact is linked to an active customer
// account with a credit limit can put an order on that account at checkout.
// The order is an offline payment as far as orders.payment_method goes (no
// new value of the ENUM); orders.erp_on_account says which kind it was, so
// the job that cancels bank transfers that never arrived leaves it alone,
// the screens can say so, and the orders on the account that are not
// invoiced yet count against its limit. The index is for that sum.
function upgrade_2026_4_10_erp_open_account() {

	install_add_column('config', 'erp_open_account_payment', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('orders', 'erp_on_account', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");
	install_add_index('orders', 'idx_erp_on_account', "KEY idx_erp_on_account (erp_on_account, erp_account_id)");

	install_note('ERP: a member whose account has a credit limit can put an order on the account at checkout. It is off until it is switched on under Settings, Ecommerce, ERP.');

}

// Sign in with Google on signin.php (2026.4.10, 10.75; includes/fn/signin.php,
// google_auth.php).
//
// Google identities move from user.user_google_id to user_identities
// (provider 'google', subject = Google's sub, the account's e-mail address),
// next to Microsoft's and Apple's, so every screen reads one table. Each
// moved link is cleared from the old column, which is what makes a second
// run find nothing to move. A link the move cannot take (that Google account
// is already tied to another account here) is left in the column and
// counted in the note. The column itself is kept.
function upgrade_2026_4_10_signin_google() {

	if (!install_table_exists('user_identities') || !install_column_exists('user', 'user_google_id')) {

		install_skipped('user_identities or user.user_google_id does not exist, skipped');

		return;

	}

	$waiting = (int) db_value("SELECT COUNT(*) FROM user WHERE user_google_id IS NOT NULL AND user_google_id <> ''");

	if ($waiting === 0) {

		install_skipped('No Google link left in user.user_google_id');

		install_note('Sign in with Google now runs through the same flow as Microsoft and Apple. Nothing changes in Google Cloud Console: the redirect address is still google_auth.php.');

		return;

	}

	// INSERT IGNORE: an account that already has a Google identity, or a sub
	// already held by another account, keeps what user_identities says.
	db("INSERT IGNORE INTO user_identities (user_id, provider, subject, email, created_timestamp, last_used_timestamp)
		SELECT user_id, 'google', user_google_id, user_email, UNIX_TIMESTAMP(), 0
		FROM user
		WHERE user_google_id IS NOT NULL AND user_google_id <> ''");

	db("UPDATE user
		INNER JOIN user_identities
			ON user_identities.user_id = user.user_id
			AND user_identities.provider = 'google'
			AND user_identities.subject = CONVERT(user.user_google_id USING ascii) COLLATE ascii_bin
		SET user.user_google_id = NULL");

	$moved = max(0, (int) mysqli_affected_rows(db::$con));
	$left = $waiting - $moved;

	if ($moved === 0) {

		install_skipped('No Google link could be moved from user.user_google_id, ' . $left . ' already tied to another account');

	} else {

		install_ran('Google sign-in: ' . $moved . ' link(s) moved from user.user_google_id to user_identities'
			. (($left > 0) ? ', ' . $left . ' already tied to another account left in the column' : ''));

	}

	install_note('Sign in with Google now runs through the same flow as Microsoft and Apple. Nothing changes in Google Cloud Console: the redirect address is still google_auth.php.');

}

// ERP: late payment fees (2026.4.10, 10.61; includes/erp/late_fee.php).
//
// config.erp_late_fee_rate is the monthly rate on what a customer owes past
// the due date, 0 (the default) for off; config.erp_late_fee_tax_rate the
// tax rate of the fee's invoice line, NULL for the rate the account is sold
// at when a product names none. Both are DECIMAL like every other rate of
// the module, so they cost config's row a few bytes.
//
// erp_invoices.late_fee_through is the last day a fee invoice charged for,
// NULL on every other invoice. Written on the draft, it counts once the
// draft is issued: the next fee of the account starts the day after, so no
// day is charged twice, and a deleted draft charges nothing. The index
// answers "the latest charged day of this account".
function upgrade_2026_4_10_erp_late_fee() {

	install_add_column('config', 'erp_late_fee_rate', "DECIMAL(6,3) NOT NULL DEFAULT 0.000");
	install_add_column('config', 'erp_late_fee_tax_rate', "DECIMAL(6,3) NULL DEFAULT NULL");

	install_add_column('erp_invoices', 'late_fee_through', "DATE NULL DEFAULT NULL");
	install_add_index('erp_invoices', 'idx_late_fee', "INDEX idx_late_fee (account_id, late_fee_through)");

	install_note('ERP: late payment fees. A monthly rate set under ERP Settings is worked out day by day on what a customer owes past the due date; the account screen shows it and writes it on a sales invoice draft. It is off until a rate is set.');

}

// Approval requests answered from outside the team (2026.4.10, 10.84;
// includes/workspace/approval_guests.php, workspace_approval.php).
//
// Staff ask somebody with no account on the site - a customer - to approve
// something, by e-mail. ws_approval_guests is one row per person asked so,
// beside ws_approval_people's rows for the team: name and email as staff
// wrote them; token_hash, the SHA-256 of the token in the link they were
// sent (the token itself is kept nowhere), token_hint its first characters,
// so a link can be told apart in the site log; expires_at when that link
// stops opening; sent_at / sent_by the last link given and who gave it. The
// answer is decision, note and decided_at, with decided_ip, decided_agent
// and decided_hint - from where, with what, and through which link it came.
// A new link replaces the hash, so only the last one opens.
function upgrade_2026_4_10_workspace_approval_guests() {

	install_create_table('ws_approval_guests', "CREATE TABLE ws_approval_guests (
		id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
		approval_id   INT UNSIGNED NOT NULL,
		name          VARCHAR(60) NOT NULL DEFAULT '',
		email         VARCHAR(255) NOT NULL DEFAULT '',
		token_hash    CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		token_hint    VARCHAR(8) NOT NULL DEFAULT '',
		expires_at    INT UNSIGNED NOT NULL DEFAULT 0,
		sent_at       INT UNSIGNED NOT NULL DEFAULT 0,
		sent_by       INT UNSIGNED NOT NULL DEFAULT 0,
		decision      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
		note          VARCHAR(500) NOT NULL DEFAULT '',
		decided_at    INT UNSIGNED NOT NULL DEFAULT 0,
		decided_ip    VARCHAR(45) NOT NULL DEFAULT '',
		decided_agent VARCHAR(255) NOT NULL DEFAULT '',
		decided_hint  VARCHAR(8) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		UNIQUE KEY uk_token (token_hash),
		KEY idx_approval (approval_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: an approval request can ask a customer, or anybody outside the team, by e-mail. They approve or reject it from a link of their own, with no account; the answer lands on the card, in the channel and on the task it is about.');

}

// Meetings on the plan (2026.4.10, 10.86; includes/workspace/schedule.php,
// includes/workspace/reminders.php).
//
// A plan item carries the address of its video call: a Jitsi room the form
// makes up, or a Google Meet, Teams or Zoom link pasted in. meeting_url is
// that address. A timed item with one tells its people a few minutes before
// it starts: remind_at is that moment, worked out as the item is saved, and
// reminded_at says it went, so finding the ones that are due is one indexed
// read, as for a task's reminder.
//
// ws_inbox.event_id names the plan item an inbox line is about, the way
// task_id names a task.
function upgrade_2026_4_10_workspace_meetings() {

	install_add_column('ws_events', 'meeting_url', "VARCHAR(500) NOT NULL DEFAULT ''");
	install_add_column('ws_events', 'remind_at', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('ws_events', 'reminded_at', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_index('ws_events', 'idx_remind', "INDEX idx_remind (reminded_at, remind_at)");
	install_add_column('ws_inbox', 'event_id', "INT UNSIGNED NOT NULL DEFAULT 0 AFTER task_id");

	install_note('Workspace: a meeting on the plan can carry its video call link (a Jitsi room made up on the spot, or a Google Meet, Teams or Zoom link), with a Join button and a notice to its people a few minutes before it starts; a meeting note can be opened from it, and its decisions and actions brought into a channel.');

}

// MCP servers that are agents themselves (2026.4.10, 10.89;
// includes/workspace/mcp_agent.php).
//
// Some servers are another service's own agent - another Pinegrap site, a
// company's assistant - that takes a message and answers it. For such a
// server ws_mcp_servers.agent_tool names the tool a message written to the
// alias goes to, as it is; '' leaves the alias to Pinegrap AI, which answers
// with the server's tools. The request waits in ws_ai_requests with agent
// 'mcp' (a VARCHAR already), and the call is kept in ws_mcp_calls with via
// 'agent'. The enum is widened first, so the new column also says the enum
// has its value.
function upgrade_2026_4_10_workspace_mcp_agent() {

	$info = install_column_info('ws_mcp_calls', 'via');

	if (is_array($info) && (strpos((string) $info['Type'], "'agent'") === false)) {
		install_modify_column('ws_mcp_calls', 'via', "ENUM('ai','form','agent') NOT NULL DEFAULT 'ai'");
	} else {
		install_skipped(lang(array('string' => '{var:1} already exists', 'vars' => 'ws_mcp_calls.via agent')));
	}

	install_add_column('ws_mcp_servers', 'agent_tool', "VARCHAR(128) NOT NULL DEFAULT '' AFTER tool_rules");

	install_note('Workspace: an MCP server that is an agent itself can answer its alias on its own. An administrator chooses the tool that takes the messages; what is written to the alias goes to it and its answer comes back under the message, without Pinegrap AI.');

}

// MCP agents that work in the background (2026.4.10, 10.110;
// includes/mcp/client.php, includes/workspace/mcp_agent.php).
//
// An agent whose work takes longer than a call answers with a task (the
// tasks extension of the protocol's current generation): a handle the site
// asks about until the task ends. ws_ai_requests.mcp_task is that handle,
// as the server gave it ('' for a request answered at once), mcp_task_poll
// when it is asked about next (moved on before each question, so two
// screens never ask together), and mcp_task_note what the server last said
// of its progress, shown under the message. The call's record in
// ws_mcp_calls stays in status 'running' until the task ends. The enum is
// widened first, so the new columns also say the enum has its value.
function upgrade_2026_4_10_workspace_mcp_tasks() {

	$info = install_column_info('ws_mcp_calls', 'status');

	if (is_array($info) && (strpos((string) $info['Type'], "'running'") === false)) {
		install_modify_column('ws_mcp_calls', 'status', "ENUM('pending','done','failed','dismissed','running') NOT NULL DEFAULT 'done'");
	} else {
		install_skipped(lang(array('string' => '{var:1} already exists', 'vars' => 'ws_mcp_calls.status running')));
	}

	install_add_column('ws_ai_requests', 'mcp_task', "VARCHAR(255) NOT NULL DEFAULT '' AFTER mcp_server_id");
	install_add_column('ws_ai_requests', 'mcp_task_poll', "INT UNSIGNED NOT NULL DEFAULT 0 AFTER mcp_task");
	install_add_column('ws_ai_requests', 'mcp_task_note', "VARCHAR(255) NOT NULL DEFAULT '' AFTER mcp_task_poll");

	install_note('Workspace: an MCP agent may work on a message in the background. The site asks the service how the work goes until it ends, the line under the message says it is working, and the person who asked can stop it.');

}

// Conversations an MCP agent keeps (2026.4.10, 10.111;
// includes/workspace/mcp_agent.php).
//
// An agent that keeps a conversation of its own gives its id back in the
// structured part of its answer; ws_ai_requests.mcp_conversation keeps it
// ('' when it gave none), and the next request that follows that answer - a
// reply to it in the channel, the next run on the same task - sends it back,
// so the agent goes on with what it already knows instead of reading its own
// answer quoted. The index on reply_message_id finds the request an answer
// came from: the channel screen asks it for every page of messages, to show
// the agent's answers under the agent's own name.
function upgrade_2026_4_10_workspace_mcp_conversations() {

	install_add_column('ws_ai_requests', 'mcp_conversation', "VARCHAR(255) NOT NULL DEFAULT '' AFTER mcp_task_note");
	install_add_index('ws_ai_requests', 'idx_reply', "INDEX idx_reply (reply_message_id)");

	install_note('Workspace: an MCP agent that keeps a conversation goes on with it when somebody replies to its answer, and its answers show under its own name in the channel.');

}

// Visitor records kept for a period (2026.4.10, 10.3; includes/fn/visitor_retention.php,
// the Visitor Tracking card of the SEO settings, the Database Engine screen).
//
// One `visitors` row was written per visit and none was ever removed; on a
// busy site the table reached millions of rows and most of a gigabyte.
// config.visitor_retention_days is how many days of rows are kept (0 keeps
// them all), config.visitor_summary whether each day is added up into
// visitor_stats_daily before its rows go. Nothing is removed here: the
// general job removes the old rows a chunk at a time after the upgrade, and
// the Database Engine screen can start it by hand.
//
// visitor_stats_daily holds, per day, the visits and what they led to by
// each thing the visitor report groups by (dimension, dim_value), the top
// values of a dimension each on a row and the rest added up on one row
// (is_rest). The row with dimension '' is the day's total and is written
// last, which is what marks a day as summarized.
//
// visitor_reports.summarize_by_1..3 gain 'device': the summary counts the
// device a visit came from, and the report can now group by it.
//
// Two indexes of `visitors` go, on InnoDB only, where dropping an index is a
// change of the table's definition and not a copy of the table. ip_address
// is the first column of idx_dedup (2026.1.2), which answers every lookup it
// did. idx_user_agent (2026.2.5) is a prefix index no query uses: grouping
// by the user agent cannot be served by a prefix, and the day's rows are
// found through start_timestamp. Together they were about a fifth of the
// table's size. On MyISAM both are left for the operator's conversion.
function upgrade_2026_4_10_visitor_retention() {

	install_add_column('config', 'visitor_retention_days', "SMALLINT UNSIGNED NOT NULL DEFAULT 90");
	install_add_column('config', 'visitor_summary', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_create_table('visitor_stats_daily', "CREATE TABLE visitor_stats_daily (
		id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
		stat_date          DATE NOT NULL,
		dimension          VARCHAR(32) NOT NULL DEFAULT '',
		dim_value          VARCHAR(255) NOT NULL DEFAULT '',
		is_rest            TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
		visits             INT UNSIGNED NOT NULL DEFAULT 0,
		page_views         INT UNSIGNED NOT NULL DEFAULT 0,
		forms_submitted    INT UNSIGNED NOT NULL DEFAULT 0,
		orders_created     INT UNSIGNED NOT NULL DEFAULT 0,
		orders_retrieved   INT UNSIGNED NOT NULL DEFAULT 0,
		orders_checked_out INT UNSIGNED NOT NULL DEFAULT 0,
		orders_completed   INT UNSIGNED NOT NULL DEFAULT 0,
		order_total        BIGINT NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_dimension_date (dimension, stat_date),
		KEY idx_date (stat_date)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Widened from what is there, never narrowed: reading the enum first keeps
	// any value a later release may have added.
	foreach (array('summarize_by_1', 'summarize_by_2', 'summarize_by_3') as $column) {

		$info = install_column_info('visitor_reports', $column);

		if (is_array($info) && (stripos((string)$info['Type'], "'device'") === false) && (preg_match('/^enum\(.*\)$/is', trim((string)$info['Type'])) === 1)) {

			install_modify_column('visitor_reports', $column, preg_replace('/\)$/', ",'device')", trim((string)$info['Type'])) . " NOT NULL DEFAULT ''");

		}

	}

	if (install_table_engine('visitors') === 'innodb') {

		if (install_index_exists('visitors', 'idx_dedup')) {
			install_drop_index('visitors', 'ip_address');
		}

		install_drop_index('visitors', 'idx_user_agent');

	}

	install_note('Visitor records: rows older than 90 days are removed a little at a time by the general job, after each day is added up into a daily summary the visitor report and the API go on reading. The period, or no limit, is set in Site Settings, SEO, Visitor Tracking; the Database Engine screen shows the table and can start the cleanup by hand.');

}

// How a channel's work stands (2026.4.10, 10.87; includes/workspace/status.php).
//
// ws_status_updates is one row per status update: the message that says it
// (message_id, uk_message, so a page of messages finds its updates with one
// read), the channel, and health - on_track, at_risk or off_track. The text is
// the message's; the newest row of a channel whose message is not deleted is
// the state its header shows (idx_channel).
//
// ws_checkins is one row per check-in: its message, its channel and the
// question as asked. ws_checkin_answers keeps one answer per person and
// check-in (the primary key), changed in place; an empty answer deletes the
// row.
function upgrade_2026_4_10_workspace_status() {

	install_create_table('ws_status_updates', "CREATE TABLE ws_status_updates (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		channel_id  INT UNSIGNED NOT NULL DEFAULT 0,
		message_id  INT UNSIGNED NOT NULL DEFAULT 0,
		health      ENUM('on_track','at_risk','off_track') NOT NULL DEFAULT 'on_track',
		created_by  INT UNSIGNED NOT NULL DEFAULT 0,
		created_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uk_message (message_id),
		KEY idx_channel (channel_id, id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_checkins', "CREATE TABLE ws_checkins (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		channel_id  INT UNSIGNED NOT NULL DEFAULT 0,
		message_id  INT UNSIGNED NOT NULL DEFAULT 0,
		question    VARCHAR(255) NOT NULL DEFAULT '',
		created_by  INT UNSIGNED NOT NULL DEFAULT 0,
		created_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uk_message (message_id),
		KEY idx_channel (channel_id, id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_checkin_answers', "CREATE TABLE ws_checkin_answers (
		checkin_id  INT UNSIGNED NOT NULL,
		user_id     INT UNSIGNED NOT NULL,
		body        TEXT NULL,
		created_at  INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (checkin_id, user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: a channel can post a status update - on track, at risk or off track - drafted from its tasks and decisions, and ask its people a check-in question they each answer on one card; a scheduled action can ask it every week.');

}

// Inbox lines taken away (2026.4.10, 10.88; includes/workspace/notify.php,
// api_inbox.php).
//
// A line of the workspace inbox is deleted for good when what it says went:
// a mention in a message deleted since, a note deleted or no longer shared, a
// discussion purged. ws_inbox_deleted keeps for a while which ones went -
// the row's own id, its person and kind, when it was written and when it went
// - so an app holding the line learns it from GET /workspace/inbox with
// changed_since, as it learns of a deleted message. Rows older than 30 days
// are removed whenever another line goes (WS_INBOX_DELETED_KEEP).
function upgrade_2026_4_10_workspace_inbox_deleted() {

	install_create_table('ws_inbox_deleted', "CREATE TABLE ws_inbox_deleted (
		id          INT UNSIGNED NOT NULL,
		user_id     INT UNSIGNED NOT NULL DEFAULT 0,
		kind        VARCHAR(20) NOT NULL DEFAULT '',
		channel_id  INT UNSIGNED NOT NULL DEFAULT 0,
		created_at  INT UNSIGNED NOT NULL DEFAULT 0,
		deleted_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_user (user_id, deleted_at),
		KEY idx_deleted (deleted_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: an app that keeps the inbox on a phone learns which lines were taken away - a mention in a deleted message, a note deleted or no longer shared - for 30 days after they go.');

}

// Page styles reach the recycle bin (2026.4.10, 10.4; view_folder_and_files_f.php,
// pg_recycle_style()).
//
// Deleting a design in the visual editor sent its pages to the bin but deleted
// the style itself, and the page styles list deleted styles outright. A style
// is not a row in a folder, so it cannot be moved into the bin folder the way a
// page is: its rows are written into its restore record and then deleted, and
// restoring writes them back. item_data holds that record. It is LONGTEXT
// because a style row carries several LONGTEXT columns of its own.
//
// item_type is widened with 'style', keeping every value it already has: the
// list is read first, so a value another step added is never dropped. MODIFY
// on an enum rewrites the table, so it runs only when 'style' is missing.
function upgrade_2026_4_10_style_bin() {

	install_add_column('recycle_bin', 'item_data', "LONGTEXT NULL");

	$item_type = install_column_info('recycle_bin', 'item_type');

	if ($item_type && (strpos((string) $item_type['Type'], "'style'") === false)) {

		preg_match_all("/'((?:[^']|'')*)'/", (string) $item_type['Type'], $matches);

		$values = $matches[1];
		$values[] = 'style';

		install_modify_column('recycle_bin', 'item_type', "ENUM('" . implode("','", $values) . "') NOT NULL");

	}

	install_note('Recycle Bin: a page style deleted in the Visual Page Editor or from the page styles list goes to the Recycle Bin with its pages, and comes back from there with its settings and the folders and pages that used it.');

}

// The "noise" theme of the Turkish starter site gets its file back
// (2026.4.10, 10.5; no table changes).
//
// The Turkish starter site carried the theme's file as "theme_ noise.css",
// with a space, while its files row is theme__noise.css. On every site
// installed from it the theme had no file on the disk: its CSS editor opened
// empty and the theme served no stylesheet. The starter site carries the
// right name since then; this renames the file on the sites already
// installed from it. Saving the Edit Theme screen of that theme stored the
// row as theme_noise.css (file names lose doubled underscores), so either
// name is the one the file takes.
//
// The file is renamed only when it is there, a theme row has one of those
// names and nothing is on the disk under that name: a site that renamed the
// file by hand, removed it, or never had it is left as it is, and a second
// run finds nothing to do.
function upgrade_2026_4_10_noise_theme_file() {

	if (!defined('FILE_DIRECTORY_PATH')) {

		return;

	}

	$source = FILE_DIRECTORY_PATH . '/theme_ noise.css';

	if (!is_file($source)) {

		return;

	}

	foreach (array('theme__noise.css', 'theme_noise.css') as $name) {

		$recorded = (int) db_value("SELECT COUNT(*) FROM files WHERE (name = '" . $name . "') AND (design = '1')");

		if (($recorded > 0) && !file_exists(FILE_DIRECTORY_PATH . '/' . $name)) {

			if (@rename($source, FILE_DIRECTORY_PATH . '/' . $name)) {

				install_note('Themes: the file of the "noise" theme was named "theme_ noise.css" on sites installed from the Turkish starter site, so the theme had no stylesheet. It is now ' . $name . ', the name the theme is recorded under.');

			} else {

				install_note('Themes: the file "theme_ noise.css" in data/files could not be renamed to ' . $name . ' (the name the "noise" theme is recorded under). Rename it by hand so the theme has its stylesheet.');

			}

			return;

		}

	}

}

// Event subscriptions narrowed to one group of workspace channels
// (2026.4.10, 10.76; includes/api/resources/webhooks.php, the "Event
// subscriptions narrowed to a group" part of includes/workspace/groups.php).
//
// 0 is what every subscription was until now: the whole site. A group's id
// narrows it to the workspace events that happen in a channel of that group
// or of a group inside it, so one address can hear a department's channels
// and nothing else. The channel's group is read when the event happens, so a
// channel moved into the group is heard from then on.
function upgrade_2026_4_10_webhook_channel_group() {

	install_add_column('api_webhooks', 'channel_group_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('External API: a webhook subscription can be narrowed to one group of workspace channels (channel_group_id); it then hears the workspace events of the channels in that group and in the groups inside it.');

}

// The referring address and the browser of a visit kept once each (2026.4.10, 10.6;
// includes/fn/visitor_texts.php, init_tracking() in get_page.php).
//
// visitors.http_referer and visitors.user_agent were up to 255 characters in every row and
// most of its size, while a site sees the same browsers and the same few search engine
// addresses over and over. visitor_texts keeps each value once: kind (1 the referring
// address, 2 the user agent), the MD5 of the text (uk_text, a short key that tells "Mozilla"
// from "mozilla", which the column's collation would not), the text, and last_used, which
// the cleanup reads to remove the values no remaining visit names (idx_last_used). A visit
// written from now on carries their numbers in http_referer_id and user_agent_id and leaves
// the two text columns empty; the rows already there keep their texts and are read as
// before, and the retention period removes them in time. No row is rewritten here.
//
// The two columns are added without copying the table where the server can do that
// (install_add_columns_instant()). Where it cannot, a table of up to 250,000 visits is
// copied, which takes seconds. A larger one is left as it is: copying it could run past
// what the web server allows a request, and with every visit waiting on MyISAM. Visits are
// then written with their texts as before, and running this version's steps again from
// Software Repair, once the cleanup has made the table smaller, adds the columns.
function upgrade_2026_4_10_visitor_texts() {

	install_create_table('visitor_texts', "CREATE TABLE visitor_texts (
		text_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
		kind       TINYINT UNSIGNED NOT NULL DEFAULT 0,
		text_hash  BINARY(16) NOT NULL,
		text_value VARCHAR(255) NOT NULL DEFAULT '',
		last_used  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (text_id),
		UNIQUE KEY uk_text (kind, text_hash),
		KEY idx_last_used (last_used)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	$columns = array(
		'http_referer_id' => "INT UNSIGNED NOT NULL DEFAULT 0",
		'user_agent_id'   => "INT UNSIGNED NOT NULL DEFAULT 0",
	);

	if (!install_add_columns_instant('visitors', $columns)) {

		$rows = (int) db_value("SELECT COUNT(*) FROM (SELECT 1 FROM visitors LIMIT 250001) AS visitor_rows");

		if ($rows <= 250000) {

			foreach ($columns as $column => $definition) {

				install_add_column('visitors', $column, $definition);

			}

		} else {

			install_note('Visitor records: the visitors table holds more than 250,000 visits and this database server cannot add columns to it without copying it, so it was left as it is and visits keep their referring address and browser in their own row. Once the cleanup has made the table smaller, run the database steps of this version again from Software Repair.');

			return;

		}

	}

	install_note('Visitor records: the referring address and the browser of a visit are kept once each in a list and named by their number, which makes each new visitor record about half as large. Records written before keep theirs and are read as before.');

}
