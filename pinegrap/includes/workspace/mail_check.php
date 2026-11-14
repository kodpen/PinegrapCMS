<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - Check e-mail: a person reads now, from Communication ›
 * Incoming e-mail, the IMAP mailboxes whose mail they read.
 *
 * Each box goes through ws_mail_fetch(), so the job's lock (one reader per
 * box) and its time budget per box hold here too. A box read in the last
 * WS_MAIL_CHECK_GAP seconds, by the job or by anybody's press, is not read
 * again; a person may press WS_MAIL_CHECK_PER_MINUTE times a minute. The list
 * then says how many mails came and, for a box that failed, the words its
 * Mailboxes card shows. A demonstration site reads nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Seconds before one box is read again on a press. */
define('WS_MAIL_CHECK_GAP', 15);

/** Presses a minute per person. */
define('WS_MAIL_CHECK_PER_MINUTE', 4);

/** Seconds one press may spend on all its boxes. */
define('WS_MAIL_CHECK_BUDGET', 30);

/**
 * Whether the box writes into one of the channels the person reads: its own
 * channel or room, or a route's channel. Pure.
 *
 * @param array $box         ws_mail_boxes row (channel_id, settings)
 * @param int[] $channel_ids channels the person may read
 * @return bool
 */
function ws_mail_check_reads_box($box, $channel_ids)
{
    $wanted = array((int) ($box['channel_id'] ?? 0));

    foreach (ws_mail_routes($box) as $route) {
        $wanted[] = (int) $route['channel_id'];
    }

    foreach ($wanted as $channel_id) {
        if (($channel_id > 0) && in_array($channel_id, array_map('intval', (array) $channel_ids), true)) {
            return true;
        }
    }

    return false;
}

/**
 * Whether a press may read the box now: it was not read in the last
 * WS_MAIL_CHECK_GAP seconds. Pure.
 *
 * @param array $box checked_at
 * @param int   $now
 * @return bool
 */
function ws_mail_check_due($box, $now)
{
    return (int) ($box['checked_at'] ?? 0) <= ((int) $now - WS_MAIL_CHECK_GAP);
}

/**
 * Whether a person pressed too often this minute. Pure.
 *
 * @param int $hits the count waf_rate_record() returned, this press included
 *                  (0 when the counter could not be written: never refused)
 * @return bool
 */
function ws_mail_check_over_limit($hits)
{
    return (int) $hits > WS_MAIL_CHECK_PER_MINUTE;
}

/**
 * "3 new e-mails" or "No new e-mail". Pure.
 *
 * @param int $stored
 * @return string
 */
function ws_mail_check_text($stored)
{
    $stored = (int) $stored;

    if ($stored === 0) {
        return lang('No new e-mail');
    }

    if ($stored === 1) {
        return lang('1 new e-mail');
    }

    return lang(array('string' => '{var:1} new e-mails', 'vars' => $stored));
}

/**
 * The line over the list after a check: the count, then each box that
 * failed with the words of its Mailboxes card. When no box could be read
 * the count says nothing true, so only the failures are written. Pure.
 *
 * @param array $result ws_mail_check_run(): stored, read (boxes read or
 *                      fresh from a read in the last WS_MAIL_CHECK_GAP
 *                      seconds), failed (name, error, detail, host), waiting
 *                      (boxes another reader held or the press had no time
 *                      left for)
 * @return string HTML
 */
function ws_mail_check_line_html($result)
{
    $failed = (array) ($result['failed'] ?? array());
    $counted = !$failed || ((int) ($result['read'] ?? 0) > 0) || ((int) $result['stored'] > 0);
    $html = !$counted ? '' : '<p class="mb-1"><i class="bi bi-' . ((int) $result['stored'] > 0 ? 'envelope-check fg-success' : 'envelope fg-secondary') . ' me-2" aria-hidden="true"></i>'
        . '<strong>' . h(ws_mail_check_text($result['stored'])) . '</strong></p>';

    foreach ($failed as $box) {
        $html .= '<p class="mb-1 small"><i class="bi bi-exclamation-triangle fg-danger me-2" aria-hidden="true"></i>'
            . h((string) $box['name'] . ': ' . ws_mail_error_text((string) $box['error'], (string) $box['detail'], (string) $box['host'])) . '</p>';
    }

    if ((int) ($result['waiting'] ?? 0) > 0) {
        $html .= '<p class="mb-1 small fg-secondary">' . h(lang('Some mailboxes are being read already; their e-mail comes in a moment.')) . '</p>';
    }

    return $html;
}

/**
 * The IMAP boxes the person reads, all of them or the one asked for.
 *
 * @param array $viewer
 * @param int   $box_id 0 for every box
 * @return array[] ws_mail_boxes rows
 */
function ws_mail_check_boxes($viewer, $box_id = 0)
{
    if (!ws_mail_ready() || empty($viewer['member'])) {
        return array();
    }

    $readable = array();
    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_mail_boxes
        WHERE enabled = 1 AND transport = 'imap' AND settings LIKE '%\"host\":\"_%'" . (((int) $box_id > 0) ? " AND id = '" . (int) $box_id . "'" : '') . "
        ORDER BY checked_at, id LIMIT 50") as $box) {
        $wanted = array((int) $box['channel_id']);

        foreach (ws_mail_routes($box) as $route) {
            $wanted[] = (int) $route['channel_id'];
        }

        foreach (array_unique($wanted) as $channel_id) {
            if (($channel_id > 0) && !isset($readable[$channel_id])) {
                $channel = ws_channel($channel_id);
                $readable[$channel_id] = $channel && ws_can_read_channel($viewer, $channel);
            }
        }

        if (ws_mail_check_reads_box($box, array_keys(array_filter($readable)))) {
            $out[] = $box;
        }
    }

    return $out;
}

/**
 * Reads the person's boxes now.
 *
 * @param array $viewer
 * @param int   $box_id 0 for every box the person reads
 * @return array ok, error ('' | 'demo' | 'limit' | 'no_box'), stored, read,
 *               failed (name, error, detail, host), waiting
 */
function ws_mail_check_run($viewer, $box_id = 0)
{
    $out = array('ok' => false, 'error' => '', 'stored' => 0, 'read' => 0, 'failed' => array(), 'waiting' => 0);

    // A demonstration site's boxes are examples: nothing is read.
    if (pg_demo()) {
        return array_merge($out, array('error' => 'demo'));
    }

    $boxes = ws_mail_check_boxes($viewer, $box_id);

    if (!$boxes) {
        return array_merge($out, array('error' => 'no_box'));
    }

    if (function_exists('waf_rate_record') && ws_mail_check_over_limit(waf_rate_record('u:' . (int) $viewer['id'], 'wsmailchk', 60))) {
        return array_merge($out, array('error' => 'limit'));
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_fetch.php');

    $out['ok'] = true;
    $until = microtime(true) + WS_MAIL_CHECK_BUDGET;

    foreach ($boxes as $box) {
        // Read moments ago: its mail is already in.
        if (!ws_mail_check_due($box, time())) {
            $out['read']++;
            continue;
        }

        // A box needs its whole budget, as in the job's run.
        if (microtime(true) + WS_MAIL_FETCH_BUDGET > $until) {
            $out['waiting']++;
            continue;
        }

        $result = ws_mail_fetch($box);

        if ($result['error'] === 'locked') {
            $out['waiting']++;
            continue;
        }

        $out['stored'] += (int) $result['stored'];

        if ($result['ok']) {
            $out['read']++;
        } else {
            $out['failed'][] = array(
                'name'   => (string) $box['name'],
                'error'  => (string) $result['error'],
                'detail' => (string) $result['detail'],
                'host'   => (string) (ws_mail_box_settings($box)['host'] ?? ''),
            );
        }
    }

    return $out;
}
