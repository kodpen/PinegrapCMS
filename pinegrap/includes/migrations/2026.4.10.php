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
	upgrade_2026_4_10_erp_storefront_prices();  // 10.59

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
