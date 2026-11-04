<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What a Reset Encryption Key leaves unreadable: the values sealed with the
 * site's key that the reset does not seal again (only the card numbers kept
 * with orders and the payment gateway secrets are). The Encryption Key card
 * lists them per area, and the save refuses the reset until the person
 * confirms they will be entered again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Two sections. The pure helpers order and filter the counts and count the
// sealed product feed tokens in parsed settings (tests/key_reset_test.php).
// pg_key_reset_sealed_counts() reads the tables; every query is guarded by
// pg_schema_has(), so a site whose upgrade has not added a table counts 0.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Pure helpers ────────────────────────────────────────────────────────────

/**
 * The areas whose sealed values a key reset leaves unreadable, in the order
 * the card lists them.
 *
 * @return string[]
 */
function pg_key_reset_areas()
{
    return array(
        'connect',
        'marketplace',
        'edocument',
        'parasut',
        'backup',
        'product_feeds',
        'google_signin',
        'mfa',
    );
}

/**
 * The areas that hold at least one sealed value, in card order.
 *
 * @param array $counts area => number of non-empty sealed values; unknown
 *                      areas are ignored, a missing one counts 0
 * @return array area => count (int, > 0)
 */
function pg_key_reset_sealed_list(array $counts)
{
    $list = array();

    foreach (pg_key_reset_areas() as $area) {
        $count = isset($counts[$area]) ? (int) $counts[$area] : 0;

        if ($count > 0) {
            $list[$area] = $count;
        }
    }

    return $list;
}

/**
 * How many product feed formats keep a sealed token to show their address
 * again.
 *
 * @param array $settings pg_product_feed_settings_parse() output
 * @return int
 */
function pg_key_reset_feed_token_count($settings)
{
    $count = 0;

    if (!is_array($settings) || !isset($settings['formats']) || !is_array($settings['formats'])) {
        return 0;
    }

    foreach ($settings['formats'] as $entry) {
        if (is_array($entry) && isset($entry['token_secret']) && is_string($entry['token_secret']) && ($entry['token_secret'] !== '')) {
            $count++;
        }
    }

    return $count;
}

// ── Database ────────────────────────────────────────────────────────────────

/**
 * The non-empty sealed values per area on this site.
 *
 * @return array area => int, every area of pg_key_reset_areas()
 */
function pg_key_reset_sealed_counts()
{
    $count_rows = function ($table, $column, $extra = '') {
        if (!pg_schema_has($table, $column)) {
            return 0;
        }

        return (int) db_value("SELECT COUNT(*) FROM " . $table . " WHERE " . $column . " != ''" . $extra);
    };

    // One value in the single config row: 1 when it is filled.
    $config_filled = function ($column) {
        if (!pg_schema_has('config', $column)) {
            return 0;
        }

        return ((string) db_value("SELECT " . $column . " FROM config LIMIT 1") !== '') ? 1 : 0;
    };

    $feed_tokens = 0;

    if (pg_schema_has('config', 'product_feeds') && function_exists('pg_product_feed_settings_parse')) {
        $feed_tokens = pg_key_reset_feed_token_count(pg_product_feed_settings_parse((string) db_value("SELECT product_feeds FROM config LIMIT 1")));
    }

    return array(
        'connect'       => $count_rows('connect_accounts', 'secret') + $count_rows('connect_apps', 'secret'),
        'marketplace'   => $count_rows('marketplace_accounts', 'credentials'),
        'edocument'     => $count_rows('erp_edoc_providers', 'credentials_enc'),
        'parasut'       => $config_filled('parasut_credentials_enc'),
        'backup'        => $config_filled('backup_remote_settings'),
        'product_feeds' => $feed_tokens,
        'google_signin' => $config_filled('oauth_google_client_secret'),
        'mfa'           => $count_rows('user_mfa', 'totp_secret', ' AND enabled_at > 0'),
    );
}
