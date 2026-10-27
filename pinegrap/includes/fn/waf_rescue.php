<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Firewall self-rescue and self-lockout guards: the block entries of a
 * Settings save that would shut the operator out, and the command-line
 * waf_unban.php that lifts a ban from the server when the panel cannot be
 * reached.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php. The matching itself is waf.php's
// (waf_ip_matches(), waf_valid_ip_pattern()), so a pattern judged here is
// judged the same way at request time and by the ban shield.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The block entries that cover this address, unless an allow entry does.
 *
 * Settings › Firewall refuses a save with such an entry: the IP list applies
 * in every mode and in the ban shield, never expires and the Release button
 * lifts only automatic bans, so a mistyped range or a 0.0.0.0/0 left from a
 * test shuts the operator out of the screen that would undo it. The allow
 * list wins at request time, so an allow entry covering the address makes
 * the save safe. Entries that are not patterns are skipped: the save drops
 * them anyway.
 *
 * @param string $ip    the operator's address (waf_client_ip())
 * @param array  $block block entries as submitted
 * @param array  $allow allow entries as submitted
 * @return array the covering block entries, trimmed, in submitted order
 */
function pg_waf_own_address_blocks($ip, array $block, array $allow)
{
    $ip = trim((string) $ip);

    if (!waf_is_ip($ip)) {
        return array();
    }

    foreach ($allow as $entry) {
        $entry = trim((string) $entry);

        if (($entry !== '') && waf_valid_ip_pattern($entry) && waf_ip_matches($ip, $entry)) {
            return array();
        }
    }

    $covering = array();

    foreach ($block as $entry) {
        $entry = trim((string) $entry);

        if (($entry !== '') && waf_valid_ip_pattern($entry) && waf_ip_matches($ip, $entry)) {
            $covering[] = $entry;
        }
    }

    return $covering;
}

/**
 * The arguments of waf_unban.php, without the script name.
 *
 * One address lifts every block row covering it; one range or wildcard lifts
 * the row that names it; --all lifts every automatic ban.
 *
 * @param array $args
 * @return array|null array('mode' => address|entry|all, 'target' => string),
 *                    or null for the usage text
 */
function pg_waf_unban_parse_args(array $args)
{
    if (count($args) !== 1) {
        return null;
    }

    $arg = trim((string) reset($args));

    if ($arg === '--all') {
        return array('mode' => 'all', 'target' => '');
    }

    if (waf_is_ip($arg)) {
        return array('mode' => 'address', 'target' => $arg);
    }

    if (waf_valid_ip_pattern($arg)) {
        return array('mode' => 'entry', 'target' => $arg);
    }

    return null;
}

/**
 * The same entry written another way: a range compares its base address as
 * packed bytes and its prefix, anything else its lower-case text.
 */
function pg_waf_unban_entry_key($entry)
{
    $entry = strtolower(trim((string) $entry));
    $parts = explode('/', $entry, 2);

    if ((count($parts) === 2) && (($packed = waf_ip_packed($parts[0])) !== false)) {
        return bin2hex($packed) . '/' . (int) $parts[1];
    }

    return $entry;
}

/**
 * The block rows waf_unban.php removes.
 *
 * An address lifts manual rows too: the operator who runs it is shut out,
 * and a manual range covering their own address is one of the ways that
 * happens (the Settings guard refuses it only from then on).
 *
 * @param array $rows   block rows, each with ip_address and source
 * @param array $parsed pg_waf_unban_parse_args() result
 * @return array the rows to remove, in the given order
 */
function pg_waf_unban_rows(array $rows, array $parsed)
{
    $remove = array();

    foreach ($rows as $row) {
        $entry = (string) $row['ip_address'];

        if ($parsed['mode'] === 'all') {
            $hit = ($row['source'] === 'auto');
        } elseif ($parsed['mode'] === 'address') {
            $hit = waf_ip_matches($parsed['target'], $entry);
        } else {
            $hit = (pg_waf_unban_entry_key($entry) === pg_waf_unban_entry_key($parsed['target']));
        }

        if ($hit) {
            $remove[] = $row;
        }
    }

    return $remove;
}

/**
 * The command that lifts a ban from the server, as the ban e-mail and the
 * usage text name it. Run from the software directory.
 */
function pg_waf_unban_command($target)
{
    return 'php waf_unban.php ' . $target;
}

/**
 * Lift the bans pg_waf_unban_rows() picks: the rows, the offence buckets
 * that earned them (or the next refusal puts the ban straight back) and the
 * ban shield's block lines, rewritten now rather than at its next refresh.
 *
 * @param array $parsed pg_waf_unban_parse_args() result
 * @return array|false the removed rows, or false without the table
 */
function pg_waf_unban(array $parsed)
{
    $has_source = waf_table_has_column('banned_ip_addresses', 'source');

    $result = mysqli_query(
        db::$con,
        $has_source
            ? "SELECT ip_address, source FROM banned_ip_addresses WHERE list_type = 'block'"
            : "SELECT ip_address, 'manual' AS source FROM banned_ip_addresses"
    );

    if (!$result) {
        return false;
    }

    $removed = pg_waf_unban_rows(mysqli_fetch_items($result), $parsed);

    foreach ($removed as $row) {
        mysqli_query(
            db::$con,
            "DELETE FROM banned_ip_addresses
             WHERE ip_address = '" . escape($row['ip_address']) . "'"
             . ($has_source ? " AND list_type = 'block'" : '')
        );

        waf_offence_clear($row['ip_address']);
    }

    if ($parsed['mode'] === 'address') {
        waf_offence_clear($parsed['target']);
    }

    waf_ip_lists(true);
    waf_ban_shield_refresh(true);

    return $removed;
}
