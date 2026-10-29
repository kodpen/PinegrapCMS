<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - incoming e-mail read from each mailbox's IMAP server.
 *
 * ws_mail_fetch() reads one box: it signs in with the box's sealed password,
 * looks for mail past the position it stopped at (uidvalidity / last_uid),
 * skips by its Message-ID what is already in the box without downloading it,
 * hands the rest to ws_mail_ingest(), then marks what it took as read or
 * moves it. A mail held back (a sender over the hourly limit) is left as it
 * is and keeps the position before it, so the next run reads it again.
 * ws_mail_fetch_run() reads every box that is due, within a time budget; the
 * job is workspace_mail_job.php. The client is includes/fn/imap_client.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Mails downloaded from one box in one run. */
define('WS_MAIL_FETCH_MAX', 25);

/** New UIDs looked at (headers only) in one run. */
define('WS_MAIL_FETCH_LOOK', 100);

/** Seconds one box may take in one run. */
define('WS_MAIL_FETCH_BUDGET', 15);

/** Seconds between two reads of one box. */
define('WS_MAIL_FETCH_EVERY', 60);

/** The largest mail downloaded, in bytes; a larger one stays in the box. */
define('WS_MAIL_FETCH_MAX_BYTES', 26214400);

/**
 * What to search for. A box read for the first time, or whose folder was
 * renumbered (UIDVALIDITY changed), reads by date from the day before it was
 * added (or before its last good read), so years of old mail are not brought
 * in; otherwise every mail past the last UID, read or not, so mail somebody
 * opened in a mail program still reaches the channel.
 *
 * @param array $box      uidvalidity, last_uid, created_at, ok_at
 * @param array $selected pg_imap_select(): uidvalidity
 * @return array reset (bool: the position starts again), criteria
 */
function ws_mail_fetch_plan($box, $selected)
{
    $known = (int) $box['uidvalidity'];

    if (($known === 0) || ($known !== (int) $selected['uidvalidity'])) {
        $from = (($known !== 0) && ((int) $box['ok_at'] > 0)) ? (int) $box['ok_at'] : (int) $box['created_at'];

        return array('reset' => true, 'criteria' => 'SINCE ' . pg_imap_search_date(max(0, $from - 86400)));
    }

    return array('reset' => false, 'criteria' => 'UID ' . ((int) $box['last_uid'] + 1) . ':*');
}

/**
 * The UIDs to look at this run, smallest first. "UID n:*" also answers the
 * newest mail when nothing is past n, so what is not past the position is
 * dropped.
 *
 * @param int[] $uids  the search's answer
 * @param bool  $reset
 * @param int   $last_uid
 * @return int[]
 */
function ws_mail_fetch_candidates($uids, $reset, $last_uid)
{
    $out = array();

    foreach ((array) $uids as $uid) {
        if ($reset || ((int) $uid > (int) $last_uid)) {
            $out[] = (int) $uid;
        }
    }

    sort($out);

    return array_slice(array_values(array_unique($out)), 0, WS_MAIL_FETCH_LOOK);
}

/**
 * The new position: past every candidate in a row that is finished, never
 * past one held back or not reached. A fresh position starts just before
 * the first candidate, or at the newest mail when there is none.
 *
 * @param int   $last_uid
 * @param bool  $reset
 * @param int   $uidnext  pg_imap_select()
 * @param array $outcomes uid => done | held | waiting, smallest first
 * @return int
 */
function ws_mail_fetch_cursor($last_uid, $reset, $uidnext, $outcomes)
{
    ksort($outcomes);

    if ($reset) {
        $last_uid = $outcomes ? ((int) key($outcomes) - 1) : max(0, (int) $uidnext - 1);
    }

    foreach ($outcomes as $uid => $outcome) {
        if ($outcome !== 'done') {
            break;
        }

        $last_uid = max((int) $last_uid, (int) $uid);
    }

    return (int) $last_uid;
}

/**
 * The Message-ID a header block names, without brackets; '' for none.
 *
 * @param string $headers
 * @return string
 */
function ws_mail_fetch_message_id($headers)
{
    $parsed = pg_mail_parse(rtrim((string) $headers, "\r\n") . "\r\n\r\n");

    return (string) $parsed['message_id'];
}

/**
 * What one ws_mail_ingest() answer means for the mailbox: taken (marked or
 * moved, the position passes it), held (left untouched, the position stops
 * before it) or stop (the box itself is wrong: its channel is gone or
 * nothing could be saved; the run ends and says why).
 *
 * @param array $result ws_mail_ingest()
 * @return string taken | held | stop
 */
function ws_mail_fetch_outcome($result)
{
    if ($result['status'] === 'held') {
        return 'held';
    }

    if (($result['status'] === 'refused') && in_array((string) $result['reason'], array('no_channel', 'not_saved'), true)) {
        return 'stop';
    }

    return 'taken';
}

/**
 * Reads one box.
 *
 * @param array $box a ws_mail_boxes row
 * @return array ok, error, detail, stored (mails brought in), skipped (larger
 *               than the limit, left in the box)
 */
function ws_mail_fetch($box)
{
    if (!function_exists('pg_imap_connect')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/imap_client.php');
    }

    $box_id = (int) $box['id'];
    $lock = 'pg_ws_mail_' . $box_id;
    $out = array('ok' => false, 'error' => '', 'detail' => '', 'stored' => 0, 'skipped' => 0);

    // One reader per box: a second job tick, or a run started by hand.
    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 0)") !== 1) {
        return array_merge($out, array('error' => 'locked'));
    }

    $result = ws_mail_fetch_box($box, $out);
    $now = time();
    $set = "checked_at = '" . $now . "', last_error = '" . e($result['ok'] ? ($result['skipped'] ? ws_mail_last_error('too_large', '') : '') : ws_mail_last_error($result['error'], $result['detail'])) . "'"
        . ($result['ok'] ? ", ok_at = '" . $now . "'" : '');

    if (isset($result['uidvalidity'])) {
        $set .= ", uidvalidity = '" . (int) $result['uidvalidity'] . "', last_uid = '" . (int) $result['last_uid'] . "'";
    }

    db("UPDATE ws_mail_boxes SET " . $set . " WHERE id = '" . $box_id . "'");
    db("SELECT RELEASE_LOCK('" . e($lock) . "')");

    unset($result['uidvalidity'], $result['last_uid']);

    return $result;
}

/**
 * The IMAP half of ws_mail_fetch(), under the box's lock.
 *
 * @param array $box
 * @param array $out
 * @return array $out, with uidvalidity and last_uid when the position moves
 */
function ws_mail_fetch_box($box, $out)
{
    $settings = ws_mail_box_settings($box);

    if ((string) ($settings['host'] ?? '') === '') {
        return array_merge($out, array('error' => 'no_server'));
    }

    $credentials = ws_mail_box_credentials($box);

    if (!$credentials['ok']) {
        return array_merge($out, array('error' => $credentials['error'], 'detail' => $credentials['detail']));
    }

    $connected = pg_imap_connect((string) $settings['host'], (int) ($settings['port'] ?? 993), array('budget' => WS_MAIL_FETCH_BUDGET));

    if (!$connected['ok']) {
        return array_merge($out, array('error' => $connected['error'], 'detail' => $connected['detail']));
    }

    $session = $connected['session'];
    $failed = function () use (&$session, &$out) {
        $said = array_merge($out, array('error' => $session['error'] ?: 'protocol', 'detail' => $session['detail']));
        pg_imap_logout($session);
        return $said;
    };

    if (!ws_mail_box_login($session, $box, $credentials)) {
        return $failed();
    }

    $selected = pg_imap_select($session, (string) $box['folder'], false);

    if (!$selected['ok'] || ($selected['uidvalidity'] <= 0)) {
        return $failed();
    }

    $plan = ws_mail_fetch_plan($box, $selected);
    $found = pg_imap_uid_search($session, $plan['criteria']);

    if ($found === null) {
        return $failed();
    }

    $candidates = ws_mail_fetch_candidates($found, $plan['reset'], (int) $box['last_uid']);
    $outcomes = array_fill_keys($candidates, 'waiting');
    $taken = array();
    $stop = null;

    if ($candidates) {
        $heads = pg_imap_uid_fetch($session, $candidates, '(UID RFC822.SIZE BODY.PEEK[HEADER.FIELDS (MESSAGE-ID)])');

        if ($heads === null) {
            return $failed();
        }

        // Mails already in the box, by Message-ID, are not downloaded again.
        $hashes = array();

        foreach ($heads as $uid => $head) {
            $message_id = ws_mail_fetch_message_id((string) pg_imap_fetch_section($head, 'BODY[HEADER'));

            if ($message_id !== '') {
                $hashes[$uid] = ws_mail_hash($message_id, '');
            }
        }

        $known = array();

        if ($hashes) {
            foreach ((array) db_items("SELECT rfc_message_hash FROM ws_mail_items WHERE box_id = '" . (int) $box['id'] . "'
                AND rfc_message_hash IN ('" . implode("','", array_map('e', array_values($hashes))) . "')") as $row) {
                $known[(string) $row['rfc_message_hash']] = true;
            }
        }

        $downloads = 0;

        foreach ($candidates as $uid) {
            if (!isset($heads[$uid])) {
                // Gone between the search and the fetch (moved or expunged).
                $outcomes[$uid] = 'done';
                continue;
            }

            if (isset($hashes[$uid], $known[$hashes[$uid]])) {
                $outcomes[$uid] = 'done';
                $taken[] = $uid;
                continue;
            }

            if ((int) ($heads[$uid]['RFC822.SIZE'] ?? 0) > WS_MAIL_FETCH_MAX_BYTES) {
                $outcomes[$uid] = 'done';
                $out['skipped']++;
                continue;
            }

            if ($downloads >= WS_MAIL_FETCH_MAX) {
                break;
            }

            $body = pg_imap_uid_fetch($session, array($uid), '(UID BODY.PEEK[])');
            $downloads++;

            if (($body === null) || !isset($body[$uid])) {
                break;
            }

            $ingested = ws_mail_ingest((string) pg_imap_fetch_section($body[$uid], 'BODY[]'), $box);
            $outcome = ws_mail_fetch_outcome($ingested);

            if ($outcome === 'stop') {
                $stop = $ingested['reason'];
                break;
            }

            if ($outcome === 'held') {
                $outcomes[$uid] = 'held';
                continue;
            }

            $outcomes[$uid] = 'done';
            $taken[] = $uid;

            if ($ingested['status'] === 'stored') {
                $out['stored']++;
            }
        }
    }

    if ($taken && ($session['error'] === '')) {
        if ((string) $box['after_fetch'] === 'move') {
            pg_imap_uid_move($session, $taken, (string) ($settings['move_folder'] ?? ''));
        } else {
            pg_imap_uid_mark_seen($session, $taken);
        }
    }

    $out['uidvalidity'] = $selected['uidvalidity'];
    $out['last_uid'] = ws_mail_fetch_cursor((int) $box['last_uid'], $plan['reset'], $selected['uidnext'], $outcomes);

    if ($session['error'] !== '') {
        return $failed();
    }

    pg_imap_logout($session);

    if ($stop !== null) {
        return array_merge($out, array('error' => $stop));
    }

    return array_merge($out, array('ok' => true));
}

/**
 * Reads every box that is on, has a server and was not read in the last
 * minute, the longest waiting first, until $budget seconds are spent.
 *
 * @param int $budget
 * @return array boxes (read), stored, failed
 */
function ws_mail_fetch_run($budget = 40)
{
    $summary = array('boxes' => 0, 'stored' => 0, 'failed' => 0);

    if (!ws_mail_ready()) {
        return $summary;
    }

    $until = microtime(true) + (int) $budget;

    foreach ((array) db_items("SELECT * FROM ws_mail_boxes
        WHERE enabled = 1 AND transport = 'imap' AND settings LIKE '%\"host\":\"_%' AND checked_at <= '" . (time() - WS_MAIL_FETCH_EVERY) . "'
        ORDER BY checked_at, id LIMIT 50") as $box) {
        // A box needs its whole budget; one that cannot have it waits.
        if (microtime(true) + WS_MAIL_FETCH_BUDGET > $until) {
            break;
        }

        $result = ws_mail_fetch($box);

        if ($result['error'] === 'locked') {
            continue;
        }

        $summary['boxes']++;
        $summary['stored'] += $result['stored'];
        $summary['failed'] += $result['ok'] ? 0 : 1;
    }

    return $summary;
}
