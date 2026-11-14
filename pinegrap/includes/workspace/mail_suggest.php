<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - recipient suggestions in To, Cc and Bcc of New e-mail and Reply
 * by e-mail.
 *
 * The word typed after the last comma (two characters at least) is looked up
 * among the contacts the person may open, then among the addresses the
 * mailboxes they send from have heard from or written to. At most
 * WS_MAIL_SUGGEST_MAX come back, each address once; one on the bounce list is
 * marked. Nothing is written, and a guest gets nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Suggestions in one answer. */
define('WS_MAIL_SUGGEST_MAX', 10);

/** The shortest word looked up. */
define('WS_MAIL_SUGGEST_PREFIX_MIN', 2);

/** The longest word looked up. */
define('WS_MAIL_SUGGEST_PREFIX_MAX', 100);

/** Recent items of a box whose To and Cc are read. */
define('WS_MAIL_SUGGEST_ITEMS', 500);

/**
 * The word to look up: what follows the last comma or semicolon of the
 * field, lower case; '' when it is shorter than two characters. Pure.
 *
 * @param mixed $typed the field's whole value
 * @return string
 */
function ws_mail_suggest_prefix($typed)
{
    if (!is_string($typed)) {
        return '';
    }

    $parts = preg_split('/[,;]/', $typed);
    $word = mb_strtolower(trim((string) end($parts)), 'UTF-8');

    if (mb_strlen($word, 'UTF-8') < WS_MAIL_SUGGEST_PREFIX_MIN) {
        return '';
    }

    return mb_substr($word, 0, WS_MAIL_SUGGEST_PREFIX_MAX, 'UTF-8');
}

/**
 * Whether an address or a name starts with the word: the address itself, or
 * any word of the name. Pure.
 *
 * @param string $address lower case
 * @param string $name
 * @param string $prefix  ws_mail_suggest_prefix()
 * @return bool
 */
function ws_mail_suggest_matches($address, $name, $prefix)
{
    if (strncmp((string) $address, $prefix, strlen($prefix)) === 0) {
        return true;
    }

    foreach (preg_split('/[\s,."\'()]+/u', mb_strtolower((string) $name, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) as $word) {
        if (strncmp($word, $prefix, strlen($prefix)) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * The addresses of a To or Cc list (as ws_mail_items keeps it) that match
 * the word, with the name written beside each. Pure.
 *
 * @param string $list   "Name <a@b.c>, d@e.f, \"Last, First\" <g@h.i>"
 * @param string $prefix
 * @return array address (lower case) => name
 */
function ws_mail_suggest_list_addresses($list, $prefix)
{
    $out = array();

    // A quoted name may hold a comma, so each entry is read by its shape
    // rather than by splitting the list.
    preg_match_all('/\s*(?:"([^"]*)"|([^,<"]*?))\s*<([^<>\s,]+)>|([^\s,<>"]+@[^\s,<>"]+)/u', (string) $list, $found, PREG_SET_ORDER);

    foreach ($found as $entry) {
        $address = mb_strtolower(trim(($entry[3] ?? '') !== '' ? $entry[3] : ($entry[4] ?? '')), 'UTF-8');
        $name = trim(($entry[1] ?? '') !== '' ? $entry[1] : ($entry[2] ?? ''));

        if (!filter_var($address, FILTER_VALIDATE_EMAIL) || isset($out[$address])) {
            continue;
        }

        if (ws_mail_suggest_matches($address, $name, $prefix)) {
            $out[$address] = $name;
        }
    }

    return $out;
}

/**
 * The answer: contacts first, then the addresses heard, each address once,
 * at most WS_MAIL_SUGGEST_MAX, the bounced marked. Pure.
 *
 * @param array[] $contacts list of address, name
 * @param array   $heard    address => name, newest first
 * @param array   $bounced  lower-case address => true
 * @return array[] list of address, name, bounced
 */
function ws_mail_suggest_merge($contacts, $heard, $bounced)
{
    $rows = array();

    foreach ((array) $contacts as $contact) {
        $rows[] = array((string) ($contact['address'] ?? ''), (string) ($contact['name'] ?? ''));
    }

    foreach ((array) $heard as $address => $name) {
        $rows[] = array((string) $address, (string) $name);
    }

    $out = array();
    $seen = array();

    foreach ($rows as $row) {
        $address = mb_strtolower(trim($row[0]), 'UTF-8');

        if (isset($seen[$address]) || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $seen[$address] = true;
        $out[] = array('address' => $address, 'name' => trim($row[1]), 'bounced' => isset($bounced[$address]));

        if (count($out) >= WS_MAIL_SUGGEST_MAX) {
            break;
        }
    }

    return $out;
}

/**
 * The contacts the person may open whose address, first or last name starts
 * with the word.
 *
 * @param array  $viewer ws_viewer()
 * @param string $prefix
 * @return array[] list of address, name
 */
function ws_mail_suggest_contacts($viewer, $prefix)
{
    if (empty($viewer['contacts'])) {
        return array();
    }

    $like = e(escape_like($prefix)) . '%';
    $scope = ((int) $viewer['role'] < 3) ? '' : ' AND ' . pg_contact_owner_sql((int) $viewer['id'], 'contacts.id');
    $out = array();

    foreach ((array) db_items("SELECT first_name, last_name, email_address FROM contacts
        WHERE email_address <> '' AND (email_address LIKE '" . $like . "' OR first_name LIKE '" . $like . "' OR last_name LIKE '" . $like . "')" . $scope . "
        ORDER BY last_name, first_name, id LIMIT " . WS_MAIL_SUGGEST_MAX) as $row) {
        $out[] = array('address' => (string) $row['email_address'], 'name' => trim($row['first_name'] . ' ' . $row['last_name']));
    }

    return $out;
}

/**
 * The addresses the boxes the person sends from have heard from (a sender)
 * or written to (To and Cc of their last WS_MAIL_SUGGEST_ITEMS items).
 *
 * @param array  $viewer
 * @param string $prefix
 * @return array address => name, newest first
 */
function ws_mail_suggest_heard($viewer, $prefix)
{
    $box_ids = array();

    foreach (ws_mail_send_boxes($viewer) as $box) {
        $box_ids[] = (int) $box['box']['id'];
    }

    if (!$box_ids) {
        return array();
    }

    $in = implode(',', $box_ids);
    $like = e(escape_like($prefix)) . '%';
    $out = array();

    // A draft is not mail the box wrote (a new mail's draft is its writer's
    // alone), and a blocked sender's mail is outside every conversation.
    $kept = ' AND ' . ws_mail_drafts_excluded_sql()
        . ((function_exists('ws_mail_blocks_ready') && ws_mail_blocks_ready()) ? ' AND blocked_at = 0' : '');

    foreach ((array) db_items("SELECT from_email, from_name FROM ws_mail_items
        WHERE box_id IN (" . $in . ")" . $kept . " AND (from_email LIKE '" . $like . "' OR from_name LIKE '" . $like . "' OR from_name LIKE '" . e('% ' . escape_like($prefix)) . "%')
        ORDER BY created_at DESC LIMIT 50") as $row) {
        $address = mb_strtolower(trim((string) $row['from_email']), 'UTF-8');

        if (($address !== '') && !isset($out[$address])) {
            $out[$address] = (string) $row['from_name'];
        }
    }

    foreach ((array) db_items("SELECT to_list, cc_list FROM ws_mail_items WHERE box_id IN (" . $in . ")" . $kept . "
        ORDER BY id DESC LIMIT " . WS_MAIL_SUGGEST_ITEMS) as $row) {
        $out += ws_mail_suggest_list_addresses($row['to_list'] . ', ' . $row['cc_list'], $prefix);
    }

    return $out;
}

/**
 * The bounce-listed among the addresses.
 *
 * @param string[] $addresses lower case
 * @return array address => true
 */
function ws_mail_suggest_bounced($addresses)
{
    if (!$addresses || !function_exists('pg_mail_bounces_ready') || !pg_mail_bounces_ready()) {
        return array();
    }

    $quoted = array();

    foreach ($addresses as $address) {
        $quoted[] = "'" . e(pg_mail_bounce_address($address)) . "'";
    }

    $out = array();

    foreach ((array) db_values("SELECT address FROM mail_bounces WHERE suppressed = 1 AND address IN (" . implode(',', $quoted) . ")") as $address) {
        $out[mb_strtolower((string) $address, 'UTF-8')] = true;
    }

    return $out;
}

/**
 * The suggestions for what the person typed.
 *
 * @param array $viewer
 * @param mixed $typed the field's whole value
 * @return array[] ws_mail_suggest_merge()
 */
function ws_mail_suggest($viewer, $typed)
{
    $prefix = ws_mail_suggest_prefix($typed);

    if (($prefix === '') || empty($viewer['member'])) {
        return array();
    }

    $contacts = ws_mail_suggest_contacts($viewer, $prefix);
    $heard = ws_mail_suggest_heard($viewer, $prefix);
    $found = ws_mail_suggest_merge($contacts, $heard, array());

    return ws_mail_suggest_merge($contacts, $heard, ws_mail_suggest_bounced(array_column($found, 'address')));
}
