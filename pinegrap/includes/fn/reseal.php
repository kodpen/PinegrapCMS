<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: what a reset of ENCRYPTION_KEY seals again.
//
// Reset Encryption Key (Settings > Accounts and sign-in) writes the new key
// to config.php and then, in the same request, while the ENCRYPTION_KEY
// constant still holds the old key, opens every value the registry lists with
// the old key and seals it with the new one. A value that does not open with
// the old key was unreadable already and is left as it is.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Every stored value sealed with the site's key, one entry per table and
 * column.
 *
 * An entry holds:
 * - area:      the group the counts are given under
 * - table, column: where the sealed value is
 * - key:       the row's primary key column, '' for the one-row config table
 * - kind:      'card' (encrypt_credit_card_number()), 'gateway'
 *              (pg_gateway_secret_seal()), 'iv' (encrypt_string_with_iv()) or
 *              'connect' (pg_connect_seal())
 * - where:     the SQL condition that picks the sealed rows (code, never input)
 * - iv_column: optional, for an 'iv' value whose IV is kept in its own column
 *              instead of the "<ciphertext>:<iv>" shape
 * - width:     the column's width, for 'gateway'
 * - json:      optional, true when an 'iv' value is JSON: one that does not
 *              decode after opening is taken as not sealed with the old key
 * - fields:    optional, for a column holding JSON with sealed values inside:
 *              the paths of those values ("formats.*.token_secret", '*' for
 *              every key at that level); each is sealed by its kind
 * - json_flags: optional, the json_encode() flags the column is written with
 *
 * @return array
 */
function pg_reseal_registry()
{
    $registry = array(
        array(
            'area'   => 'cards',
            'table'  => 'orders',
            'column' => 'card_number',
            'key'    => 'id',
            'kind'   => 'card',
            'where'  => "(card_number != '') AND (SUBSTRING(card_number, 1, 1) != '*')",
        ),
    );

    foreach (pg_gateway_secret_fields() as $column => $width) {
        $registry[] = array(
            'area'   => 'gateway',
            'table'  => 'config',
            'column' => $column,
            'key'    => '',
            'kind'   => 'gateway',
            'where'  => $column . " != ''",
            'width'  => $width,
        );
    }

    $config = function ($area, $column, $kind, array $extra = array()) {
        return $extra + array(
            'area'   => $area,
            'table'  => 'config',
            'column' => $column,
            'key'    => '',
            'kind'   => $kind,
            'where'  => $column . " <> ''",
        );
    };

    $config_text = function ($area, $name, $kind, array $extra = array()) {
        return $extra + array(
            'area'   => $area,
            'table'  => 'config_text',
            'column' => 'value',
            'key'    => 'name',
            'kind'   => $kind,
            'where'  => "name = '" . $name . "' AND value <> ''",
        );
    };

    $rows = function ($area, $table, $column, $key, $kind, array $extra = array()) {
        return $extra + array(
            'area'   => $area,
            'table'  => $table,
            'column' => $column,
            'key'    => $key,
            'kind'   => $kind,
            'where'  => $column . " <> ''",
        );
    };

    // Connected accounts and applications: every provider's row, the service
    // rows (Google Analytics, CAPTCHA, SMS, mail transport, storage, carriers,
    // payment drivers) included.
    $registry[] = $rows('connect', 'connect_apps', 'secret', 'provider', 'connect');
    $registry[] = $rows('connect', 'connect_accounts', 'secret', 'id', 'connect');

    $registry[] = $rows('marketplace', 'marketplace_accounts', 'credentials', 'id', 'iv', array('json' => true));
    $registry[] = $rows('edoc', 'erp_edoc_providers', 'credentials_enc', 'id', 'iv', array('json' => true));
    $registry[] = $config('parasut', 'parasut_credentials_enc', 'iv', array('json' => true));
    $registry[] = $config('backup', 'backup_remote_settings', 'iv', array('json' => true));
    $registry[] = $config('product_feeds', 'product_feeds', 'iv', array(
        'where'      => "product_feeds LIKE '%token_secret%'",
        'fields'     => array('formats.*.token_secret'),
        'json_flags' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ));
    $registry[] = $config('google', 'oauth_google_client_secret', 'iv', array('iv_column' => 'oauth_google_secret_iv'));

    // Two-step keys, the one in use and the one waiting for its first code.
    $registry[] = $rows('mfa', 'user_mfa', 'totp_secret', 'user_id', 'iv');
    $registry[] = $rows('mfa', 'user_mfa', 'pending_secret', 'user_id', 'iv');

    // A card_ref may hold a service's plain token: only the sealed shape is
    // picked, and a plain one that slips through does not open and is skipped.
    $registry[] = $rows('subscriptions', 'subscriptions', 'card_ref', 'id', 'iv', array('where' => "card_ref LIKE '%:%=='"));

    $registry[] = $config('translation', 'translation_google_key', 'iv');
    $registry[] = $config_text('activitypub', 'ap_keys', 'connect', array('fields' => array('private')));

    $registry[] = $rows('workspace', 'ws_mcp_servers', 'secret', 'id', 'iv', array('json' => true));
    $registry[] = $rows('workspace', 'ws_calendar_feeds', 'token_secret', 'id', 'iv');
    $registry[] = $rows('workspace', 'ws_mail_boxes', 'settings', 'id', 'connect', array(
        'where'  => "settings LIKE '%\"secret\"%'",
        'fields' => array('secret'),
    ));
    // A flow's web address secret, one row per flow (flow_web_start.php).
    $registry[] = $rows('workspace', 'config_text', 'value', 'name', 'iv', array('where' => "name LIKE 'ws\\_flow\\_web\\_%' AND value <> ''"));
    $registry[] = $config('workspace', 'ws_claude_token', 'iv');
    $registry[] = $config_text('workspace', 'ws_voice_key', 'iv');
    $registry[] = $config_text('workspace', 'ws_voice_speech_key', 'iv');

    return $registry;
}

/**
 * The sealed values inside a JSON column, each sealed again by the entry's
 * kind. The row's status is 'done' when at least one value was, and its
 * counts are per value.
 *
 * @param array  $entry   a registry entry with 'fields'
 * @param string $stored
 * @param string $old_key
 * @param string $new_key
 * @return array status, value, iv, counts
 */
function pg_reseal_json_value(array $entry, $stored, $old_key, $new_key)
{
    $counts = array('done' => 0, 'skipped' => 0, 'failed' => 0);
    $data = json_decode((string) $stored, true);

    if (!is_array($data)) {
        $counts['skipped']++;

        return array('status' => 'skipped', 'value' => (string) $stored, 'iv' => '', 'counts' => $counts);
    }

    $leaf_entry = $entry;
    unset($leaf_entry['fields']);

    foreach ((array) $entry['fields'] as $path) {
        pg_reseal_json_walk($data, explode('.', (string) $path), $leaf_entry, $old_key, $new_key, $counts);
    }

    if ($counts['done'] === 0) {
        return array('status' => ($counts['failed'] > 0) ? 'failed' : 'skipped', 'value' => (string) $stored, 'iv' => '', 'counts' => $counts);
    }

    $json = json_encode($data, isset($entry['json_flags']) ? (int) $entry['json_flags'] : 0);

    if ($json === false) {
        $counts['failed'] += $counts['done'];
        $counts['done'] = 0;

        return array('status' => 'failed', 'value' => (string) $stored, 'iv' => '', 'counts' => $counts);
    }

    return array('status' => 'done', 'value' => $json, 'iv' => '', 'counts' => $counts);
}

/**
 * Follows one path into $node and seals the string at its end again.
 *
 * @param mixed  $node     changed in place
 * @param array  $segments the path's keys; '*' is every key at that level
 * @param array  $entry    the entry without 'fields'
 * @param string $old_key
 * @param string $new_key
 * @param array  $counts   changed in place
 * @return void
 */
function pg_reseal_json_walk(&$node, array $segments, array $entry, $old_key, $new_key, array &$counts)
{
    if (!$segments) {
        if (!is_string($node) || ($node === '')) {
            return;
        }

        $result = pg_reseal_value($entry, $node, '', $old_key, $new_key);
        $counts[$result['status']]++;

        if ($result['status'] === 'done') {
            $node = $result['value'];
        }

        return;
    }

    if (!is_array($node)) {
        return;
    }

    $segment = array_shift($segments);
    $keys = ($segment === '*') ? array_keys($node) : (array_key_exists($segment, $node) ? array($segment) : array());

    foreach ($keys as $key) {
        pg_reseal_json_walk($node[$key], $segments, $entry, $old_key, $new_key, $counts);
    }
}

/**
 * One stored value opened with $old_key and sealed with $new_key.
 *
 * Pure apart from the cipher. Status 'done' carries the new value (and the
 * new IV for an entry with an iv_column); 'skipped' means it does not open
 * with $old_key and stays as it is; 'failed' means it opened but could not
 * be sealed again.
 *
 * @param array  $entry   a pg_reseal_registry() entry
 * @param string $stored  the column's value
 * @param string $iv      the iv_column's value, '' without one
 * @param string $old_key
 * @param string $new_key
 * @return array status, value, iv
 */
function pg_reseal_value(array $entry, $stored, $iv, $old_key, $new_key)
{
    $stored = (string) $stored;
    $iv = (string) $iv;
    $old_key = (string) $old_key;
    $new_key = (string) $new_key;

    $skipped = array('status' => 'skipped', 'value' => $stored, 'iv' => $iv);
    $failed = array('status' => 'failed', 'value' => $stored, 'iv' => $iv);

    if (($stored === '') || ($new_key === '')) {
        return $skipped;
    }

    if (!empty($entry['fields'])) {
        return pg_reseal_json_value($entry, $stored, $old_key, $new_key);
    }

    switch (isset($entry['kind']) ? $entry['kind'] : '') {
        case 'card':
            // A value up to 16 characters is a plain number from before
            // encryption, sealed for the first time.
            if (mb_strlen($stored) > 16) {
                $plain = decrypt_credit_card_number($stored, $old_key);

                if (!is_numeric($plain)) {
                    return $skipped;
                }
            } else {
                $plain = $stored;
            }

            $sealed = (string) encrypt_credit_card_number($plain, $new_key);

            return ($sealed === '') ? $failed : array('status' => 'done', 'value' => $sealed, 'iv' => '');

        case 'gateway':
            // A plain value is sealed for the first time; one that does not
            // open, or is too long to seal into its column, comes back as it
            // was stored.
            $sealed = (string) pg_gateway_secret_for_save('', $stored, $old_key, $new_key, isset($entry['width']) ? (int) $entry['width'] : 100);

            return ($sealed === $stored) ? $skipped : array('status' => 'done', 'value' => $sealed, 'iv' => '');

        case 'connect':
            $data = pg_connect_unseal($stored, $old_key);

            if (!$data) {
                return $skipped;
            }

            $sealed = pg_connect_seal($data, $new_key);

            return ($sealed === '') ? $failed : array('status' => 'done', 'value' => $sealed, 'iv' => '');

        case 'iv':
            $own_iv = !empty($entry['iv_column']);

            if ($own_iv) {
                $parts = array($stored, $iv);
            } else {
                $parts = explode(':', $stored, 2);

                if (count($parts) !== 2) {
                    return $skipped;
                }
            }

            $plain = pg_ssl_keys_open($parts[0], $parts[1], $old_key);

            // CBC has no MAC, so a wrong key can open to garbage about once
            // in 256 tries; a JSON value is checked so that garbage is not
            // taken for a value.
            if (($plain === null) || (!empty($entry['json']) && !is_array(json_decode($plain, true)))) {
                return $skipped;
            }

            $sealed = encrypt_string_with_iv($plain, $new_key);

            if (!is_array($sealed) || ((string) $sealed[0] === '') || ((string) $sealed[1] === '')) {
                return $failed;
            }

            return $own_iv
                ? array('status' => 'done', 'value' => (string) $sealed[0], 'iv' => (string) $sealed[1])
                : array('status' => 'done', 'value' => $sealed[0] . ':' . $sealed[1], 'iv' => '');
    }

    return $failed;
}

/**
 * Empty counts for each area of $registry, in registry order.
 *
 * @param array $registry
 * @return array area => array(done, skipped, failed)
 */
function pg_reseal_empty_counts(array $registry)
{
    $counts = array();

    foreach ($registry as $entry) {
        $counts[(string) $entry['area']] = array('done' => 0, 'skipped' => 0, 'failed' => 0);
    }

    return $counts;
}

/**
 * Seals every registry value again: opened with $old_key, sealed with
 * $new_key, written back only where the column still holds the value read.
 *
 * Runs after pg_write_config_file() wrote the new key. A table or column an
 * older schema lacks counts as nothing sealed; a row whose write fails is
 * counted as failed and keeps its old value.
 *
 * @param string     $old_key
 * @param string     $new_key
 * @param array|null $registry null is pg_reseal_registry()
 * @return array 'areas' => area => array(done, skipped, failed), and
 *               'seconds' the run took
 */
function pg_reseal_run($old_key, $new_key, $registry = null)
{
    $started = microtime(true);
    $registry = is_array($registry) ? $registry : pg_reseal_registry();
    $counts = pg_reseal_empty_counts($registry);

    foreach ($registry as $entry) {
        $area = (string) $entry['area'];
        $table = (string) $entry['table'];
        $column = (string) $entry['column'];
        $key = isset($entry['key']) ? (string) $entry['key'] : '';
        $iv_column = !empty($entry['iv_column']) ? (string) $entry['iv_column'] : '';

        if (!pg_schema_has($table, $column) || (($iv_column !== '') && !pg_schema_has($table, $iv_column))) {
            continue;
        }

        $select = array();

        if ($key !== '') {
            $select[] = $key . ' AS reseal_key';
        }

        $select[] = $column . ' AS reseal_value';

        if ($iv_column !== '') {
            $select[] = $iv_column . ' AS reseal_iv';
        }

        $rows = db_items("SELECT " . implode(', ', $select) . " FROM " . $table . " WHERE " . $entry['where']);

        foreach ($rows as $row) {
            $stored = (string) $row['reseal_value'];
            $stored_iv = ($iv_column !== '') ? (string) $row['reseal_iv'] : '';
            $result = pg_reseal_value($entry, $stored, $stored_iv, $old_key, $new_key);

            $row_counts = isset($result['counts']) ? $result['counts'] : array($result['status'] => 1);

            if ($result['status'] !== 'done') {
                foreach ($row_counts as $status => $count) {
                    $counts[$area][$status] += $count;
                }

                continue;
            }

            $set = array($column . ' = ?');
            $values = array((string) $result['value']);

            if ($iv_column !== '') {
                $set[] = $iv_column . ' = ?';
                $values[] = (string) $result['iv'];
            }

            $where = array($column . ' = ?');
            $values[] = $stored;

            if ($key !== '') {
                $where[] = $key . ' = ?';
                $values[] = (string) $row['reseal_key'];
            }

            $written = db_q("UPDATE " . $table . " SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $where), $values);

            foreach ($row_counts as $status => $count) {
                $counts[$area][(($status === 'done') && !$written) ? 'failed' : $status] += $count;
            }
        }
    }

    return array('areas' => $counts, 'seconds' => round(microtime(true) - $started, 3));
}
