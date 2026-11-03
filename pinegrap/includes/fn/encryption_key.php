<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: what a reset of ENCRYPTION_KEY leaves unreadable.
//
// Reset Encryption Key (Settings > Accounts and sign-in) seals the card
// numbers and the payment gateway secrets again with the new key; every other
// value sealed with encrypt_string_with_iv() or pg_connect_seal() stays under
// the old key and no longer opens. The card lists those values per area and
// the save asks for a confirmation before it resets the key.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * How many product feed formats keep a sealed token in config.product_feeds.
 *
 * @param string $json The stored column.
 * @return int
 */
function pg_encryption_key_reset_feed_tokens($json)
{
    $stored = json_decode((string) $json, true);

    if (!is_array($stored) || !isset($stored['formats']) || !is_array($stored['formats'])) {
        return 0;
    }

    $count = 0;

    foreach ($stored['formats'] as $entry) {
        if (is_array($entry) && isset($entry['token_secret']) && is_string($entry['token_secret']) && (trim($entry['token_secret']) !== '')) {
            $count++;
        }
    }

    return $count;
}

/**
 * The sealed values per area, from the raw counts the database gave.
 *
 * Pure: $source holds the row counts (connect_accounts, connect_apps,
 * marketplace_accounts, edoc_providers, mfa_keys) and the config row's
 * sealed columns (config). Areas with nothing sealed are left out; the
 * order is the order the card lists them in.
 *
 * @param array $source
 * @return array area => count, every count above zero
 */
function pg_encryption_key_reset_counts(array $source)
{
    $config = (isset($source['config']) && is_array($source['config'])) ? $source['config'] : array();

    $filled = function ($column) use ($config) {
        return (isset($config[$column]) && is_scalar($config[$column]) && (trim((string) $config[$column]) !== '')) ? 1 : 0;
    };

    $rows = function ($key) use ($source) {
        return (isset($source[$key]) && is_numeric($source[$key])) ? max(0, (int) $source[$key]) : 0;
    };

    $counts = array(
        'connect'       => $rows('connect_accounts') + $rows('connect_apps'),
        'marketplace'   => $rows('marketplace_accounts'),
        'edoc'          => $rows('edoc_providers'),
        'parasut'       => $filled('parasut_credentials_enc'),
        'backup'        => $filled('backup_remote_settings'),
        'product_feeds' => pg_encryption_key_reset_feed_tokens(isset($config['product_feeds']) && is_string($config['product_feeds']) ? $config['product_feeds'] : ''),
        'google'        => $filled('oauth_google_client_secret'),
        'mfa'           => $rows('mfa_keys'),
    );

    return array_filter($counts, function ($count) {
        return $count > 0;
    });
}

/**
 * The card's label of each area.
 *
 * @return array area => translated label
 */
function pg_encryption_key_reset_labels()
{
    return array(
        'connect'       => lang('Connected accounts and applications'),
        'marketplace'   => lang('Marketplace connectors'),
        'edoc'          => lang('E-document credentials'),
        'parasut'       => lang('Paraşüt credentials'),
        'backup'        => lang('Backup storage settings'),
        'product_feeds' => lang('Product feed tokens'),
        'google'        => lang('Google sign-in client secret'),
        'mfa'           => lang('Two-step verification keys'),
    );
}

/**
 * The raw counts of this site for pg_encryption_key_reset_counts(). A table
 * or column an older schema lacks counts as nothing sealed.
 *
 * @return array
 */
function pg_encryption_key_reset_source()
{
    $source = array('config' => array());

    $tables = array(
        'connect_accounts'     => array('connect_accounts', 'secret'),
        'connect_apps'         => array('connect_apps', 'secret'),
        'marketplace_accounts' => array('marketplace_accounts', 'credentials'),
        'edoc_providers'       => array('erp_edoc_providers', 'credentials_enc'),
    );

    foreach ($tables as $key => $table) {
        if (pg_schema_has($table[0], $table[1])) {
            $source[$key] = (int) db_value("SELECT COUNT(*) FROM " . $table[0] . " WHERE " . $table[1] . " <> ''");
        }
    }

    if (pg_schema_has('user_mfa', 'totp_secret')) {
        $source['mfa_keys'] = (int) db_value("SELECT COUNT(*) FROM user_mfa WHERE enabled_at > 0 AND totp_secret <> ''");
    }

    $columns = array();

    foreach (array('parasut_credentials_enc', 'backup_remote_settings', 'product_feeds', 'oauth_google_client_secret') as $column) {
        if (pg_schema_has('config', $column)) {
            $columns[] = $column;
        }
    }

    if ($columns) {
        $row = db_item("SELECT " . implode(', ', $columns) . " FROM config LIMIT 1");
        $source['config'] = is_array($row) ? $row : array();
    }

    return $source;
}
