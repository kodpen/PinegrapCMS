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
