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

    return $registry;
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

            if ($result['status'] !== 'done') {
                $counts[$area][$result['status']]++;

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

            $counts[$area][$written ? 'done' : 'failed']++;
        }
    }

    return array('areas' => $counts, 'seconds' => round(microtime(true) - $started, 3));
}
