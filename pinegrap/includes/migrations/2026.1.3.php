<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.3. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_3() {
	// Add hash columns for fast, single-query API authentication (no decrypt loops).
	// user.secret_key_hash     → WHERE lookup instead of decrypt-all-users
	// custom_apps.api_key_hash → WHERE lookup instead of decrypt-all-apps

	install_add_column('user', 'secret_key_hash', "VARCHAR(64) DEFAULT NULL");
	install_add_index('user', 'idx_secret_key_hash', "INDEX idx_secret_key_hash (secret_key_hash)");

	install_add_column('custom_apps', 'api_key_hash', "VARCHAR(64) DEFAULT NULL");
	install_add_index('custom_apps', 'idx_api_key_hash', "INDEX idx_api_key_hash (api_key_hash)");

	// Backfill users: compute HMAC of each plaintext secret key.  The hash is a function of the
	// key, so computing it again on a second run writes the same value.
	$users = db_items("SELECT user_id, secret_key, secret_key_iv FROM user WHERE secret_key != '' AND secret_key IS NOT NULL");
	foreach ($users as $row) {
		$plain = decode_ssl_keys($row['secret_key'], $row['secret_key_iv']);
		if ($plain === '') continue;
		$hash = hash_hmac('sha256', $plain, ENCRYPTION_KEY);
		db("UPDATE user SET secret_key_hash = '" . escape($hash) . "' WHERE user_id = " . (int)$row['user_id']);
	}

	// Backfill apps: compute HMAC of each plaintext API key.
	$apps = db_items("SELECT id, api_key, api_key_iv FROM custom_apps WHERE api_key != '' AND api_key IS NOT NULL");
	foreach ($apps as $row) {
		$plain = decode_ssl_keys($row['api_key'], $row['api_key_iv']);
		if ($plain === '') continue;
		$hash = hash_hmac('sha256', $plain, ENCRYPTION_KEY);
		db("UPDATE custom_apps SET api_key_hash = '" . escape($hash) . "' WHERE id = " . (int)$row['id']);
	}
}
