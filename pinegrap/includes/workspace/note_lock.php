<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the notes lock: a person's PIN for their own notes and groups.
 * Off until the person sets a PIN in My Notes; there is no site setting.
 *
 * The PIN's hash, how long an opened lock stays open and the wrong tries are
 * a row of ws_note_locks. The open lock and an e-mailed code live in the
 * session, so signing out (or another browser) closes it. Five wrong PINs in
 * a row pause entry for 15 minutes; a code sent by e-mail (six digits, ten
 * minutes, once, five wrong guesses end it, three sends an hour) sets a new
 * PIN when the old one is forgotten.
 *
 * Which notes are behind the lock and what refuses them while it is closed
 * is the gate's work (ws_note_behind_lock()); this file keeps the PIN and
 * says whether the lock is open. Until the 2026.11.0 step 11.0.119 ran
 * (ws_note_lock_ready() in notes.php), nobody has a lock.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * open_minutes of "ask for the PIN every time a locked note is opened".
 */
define('WS_NOTE_LOCK_EVERY_TIME', 0);

/**
 * open_minutes of "until I sign out": the open lock lasts as long as the
 * session.
 */
define('WS_NOTE_LOCK_UNTIL_SIGN_OUT', 65535);

/**
 * Wrong PINs in a row before entry pauses, and how long it pauses (seconds).
 */
define('WS_NOTE_LOCK_MISSES', 5);
define('WS_NOTE_LOCK_PAUSE', 900);

/**
 * With "every time", how long a note opened with the PIN stays open while
 * it is not used (seconds): the screen's saves keep it open while it is
 * being written; a note left alone asks again.
 */
define('WS_NOTE_LOCK_EVERY_TIME_IDLE', 900);

/**
 * The times a person can choose, in minutes, with their label.
 *
 * @return array minutes => label
 */
function ws_note_lock_times()
{
    return array(
        WS_NOTE_LOCK_EVERY_TIME     => lang('Every time'),
        1                           => lang('1 minute'),
        5                           => lang('5 minutes'),
        15                          => lang('15 minutes'),
        30                          => lang('30 minutes'),
        60                          => lang('60 minutes'),
        WS_NOTE_LOCK_UNTIL_SIGN_OUT => lang('Until I sign out'),
    );
}

/**
 * A chosen time as it is kept: one of 0, 1, 5, 15, 30, 60 or 65535 minutes;
 * anything else is null. Pure.
 *
 * @param mixed $value
 * @return int|null
 */
function ws_note_lock_minutes($value)
{
    if (!is_scalar($value) || is_bool($value) || !preg_match('/^\d{1,5}$/', trim((string) $value))) {
        return null;
    }

    $minutes = (int) trim((string) $value);

    return in_array($minutes, array(WS_NOTE_LOCK_EVERY_TIME, 1, 5, 15, 30, 60, WS_NOTE_LOCK_UNTIL_SIGN_OUT), true) ? $minutes : null;
}

/**
 * Whether a typed PIN has the PIN's shape: 4 to 8 digits, nothing else.
 * Pure.
 *
 * @param mixed $pin
 * @return bool
 */
function ws_note_lock_pin_valid($pin)
{
    return is_string($pin) && (preg_match('/^[0-9]{4,8}$/D', $pin) === 1);
}

/**
 * A new PIN typed twice: '' when it can be kept, else the field to point
 * at ('pin' its shape, 'pin2' the two differ). Pure.
 *
 * @param mixed $pin
 * @param mixed $pin2
 * @return string
 */
function ws_note_lock_new_pin_refusal($pin, $pin2)
{
    if (!ws_note_lock_pin_valid($pin)) {
        return 'pin';
    }

    if (!is_string($pin2) || !hash_equals($pin, $pin2)) {
        return 'pin2';
    }

    return '';
}

/**
 * Whether an open lock kept in the session still holds. Pure.
 *
 * It holds for the person it was opened for, and: until sign-out always;
 * with a time in minutes while it was used within that time; with "every
 * time" only for what it was opened for, while used within
 * WS_NOTE_LOCK_EVERY_TIME_IDLE: one note, or (note 0) the list, where a
 * locked group shows its notes' titles while each note still asks.
 *
 * @param mixed $open    the session's value: user_id, used_at, note_id
 * @param int   $user_id
 * @param int   $minutes the person's open_minutes
 * @param int   $now
 * @param int   $note_id the note asked about; 0 for the lock as a whole
 * @return bool
 */
function ws_note_lock_open_holds($open, $user_id, $minutes, $now, $note_id = 0)
{
    if (!is_array($open) || !isset($open['user_id'], $open['used_at'])
        || ((int) $user_id <= 0) || ((int) $open['user_id'] !== (int) $user_id)
        || ((int) $open['used_at'] > (int) $now)) {
        return false;
    }

    $idle = (int) $now - (int) $open['used_at'];
    $minutes = (int) $minutes;

    if ($minutes === WS_NOTE_LOCK_UNTIL_SIGN_OUT) {
        return true;
    }

    if ($minutes === WS_NOTE_LOCK_EVERY_TIME) {
        return ((int) ($open['note_id'] ?? 0) === (int) $note_id) && ($idle <= WS_NOTE_LOCK_EVERY_TIME_IDLE);
    }

    return $idle <= ($minutes * 60);
}

/**
 * Keeps an e-mailed code in the session's value: its hash, never the code.
 * A newer code replaces the older. Pure on $store.
 *
 * @param mixed  $store   by reference
 * @param int    $user_id
 * @param string $code
 * @param int    $now
 */
function ws_note_lock_code_keep(&$store, $user_id, $code, $now)
{
    $store = array(
        'user_id' => (int) $user_id,
        'hash'    => hash('sha256', 'note-lock-code:' . (int) $user_id . ':' . (string) $code),
        'time'    => (int) $now,
        'misses'  => 0,
    );
}

/**
 * Checks a typed e-mailed code. A right one is used up; the fifth wrong one
 * ends it; a code works ten minutes. Pure on $store.
 *
 * @param mixed  $store   by reference
 * @param int    $user_id
 * @param string $code    white space is ignored
 * @param int    $now
 * @return string '' accepted, 'code' wrong, 'code_expired' when there is
 *                none to check (never sent, too old, used, too many wrong
 *                guesses, another person's)
 */
function ws_note_lock_code_check(&$store, $user_id, $code, $now)
{
    if (!is_array($store) || !isset($store['user_id'], $store['hash'], $store['time'], $store['misses'])
        || ((int) $store['user_id'] !== (int) $user_id)) {
        return 'code_expired';
    }

    if ((((int) $now - (int) $store['time']) > 600) || ((int) $store['time'] > (int) $now) || ((int) $store['misses'] >= 5)) {
        $store = null;

        return 'code_expired';
    }

    $code = preg_replace('/\s+/', '', (string) $code);

    if (hash_equals((string) $store['hash'], hash('sha256', 'note-lock-code:' . (int) $user_id . ':' . $code))) {
        $store = null;

        return '';
    }

    $store['misses'] = (int) $store['misses'] + 1;

    if ($store['misses'] >= 5) {
        $store = null;

        return 'code_expired';
    }

    return 'code';
}

/**
 * An e-mail address with its name part hidden (e***@example.com). Pure.
 *
 * @param mixed $email
 * @return string
 */
function ws_note_lock_mask_email($email)
{
    $email = is_string($email) ? trim($email) : '';
    $at = mb_strrpos($email, '@', 0, 'UTF-8');

    if (($email === '') || ($at === false) || ($at === 0)) {
        return '';
    }

    return mb_substr($email, 0, 1, 'UTF-8') . '***' . mb_substr($email, $at, null, 'UTF-8');
}

/**
 * The person's lock row: pin_hash, open_minutes, fails, wait_until; null
 * when they set no PIN or the schema is not there.
 *
 * @param int $user_id
 * @return array|null
 */
function ws_note_lock_row($user_id)
{
    if (((int) $user_id <= 0) || !ws_note_lock_ready()) {
        return null;
    }

    $row = db_item("SELECT user_id, pin_hash, open_minutes, fails, wait_until FROM ws_note_locks WHERE user_id = '" . (int) $user_id . "'");

    return (is_array($row) && ((string) $row['pin_hash'] !== '')) ? $row : null;
}

/**
 * Whether the person set a PIN, read once a request (the list asks it for
 * every note). ws_note_lock_setup() and ws_note_lock_turn_off() change it.
 *
 * @param int       $user_id
 * @param bool|null $set     to keep a known answer
 * @return bool
 */
function ws_note_lock_has($user_id, $set = null)
{
    static $known = array();

    $user_id = (int) $user_id;

    if ($set !== null) {
        $known[$user_id] = (bool) $set;
    }

    if (!array_key_exists($user_id, $known)) {
        $known[$user_id] = (bool) ws_note_lock_row($user_id);
    }

    return $known[$user_id];
}

/**
 * The open lock kept in this session, by reference.
 *
 * @return mixed
 */
function &ws_note_lock_session()
{
    if (!isset($_SESSION['software']) || !is_array($_SESSION['software'])) {
        $_SESSION['software'] = array();
    }

    if (!array_key_exists('ws_note_lock', $_SESSION['software'])) {
        $_SESSION['software']['ws_note_lock'] = null;
    }

    return $_SESSION['software']['ws_note_lock'];
}

/**
 * Whether the person's lock is open in this session, for a note or as a
 * whole (ws_note_lock_open_holds()). A person without a PIN has nothing
 * locked, so it counts as open.
 *
 * @param int $user_id
 * @param int $note_id
 * @return bool
 */
function ws_note_lock_is_open($user_id, $note_id = 0)
{
    $row = ws_note_lock_row($user_id);

    if (!$row) {
        return true;
    }

    return ws_note_lock_open_holds(ws_note_lock_session(), $user_id, (int) $row['open_minutes'], time(), $note_id);
}

/**
 * Renews the open lock on a use of a locked note: the time it stays open
 * starts again. Does nothing while the lock is closed.
 *
 * @param int $user_id
 * @param int $note_id
 */
function ws_note_lock_touch($user_id, $note_id = 0)
{
    if (!ws_note_lock_is_open($user_id, $note_id)) {
        return;
    }

    // Most actions close the session early (ws_handle_action()); it is
    // opened again for this one write while the answer is not yet sent. A
    // request that never had a session (the external API) starts none.
    $reopened = false;

    if ((session_status() !== PHP_SESSION_ACTIVE) && (session_id() !== '') && !headers_sent()) {
        $reopened = @session_start();
    }

    $open = &ws_note_lock_session();

    if (is_array($open)) {
        $open['used_at'] = time();
    }

    if ($reopened) {
        session_write_close();
    }
}

/**
 * Opens the lock in this session (for $note_id alone when the person chose
 * "every time").
 *
 * @param int $user_id
 * @param int $note_id
 */
function ws_note_lock_open_session($user_id, $note_id = 0)
{
    $open = &ws_note_lock_session();
    $open = array('user_id' => (int) $user_id, 'used_at' => time(), 'note_id' => max(0, (int) $note_id));
}

/**
 * Lock now: the open lock of this session closes.
 */
function ws_note_lock_close()
{
    $open = &ws_note_lock_session();
    $open = null;
}

/**
 * What the screen needs to know of the person's lock.
 *
 * @param array $viewer
 * @return array ready, set_up, minutes, times, open, wait (seconds of the
 *               pause left), email (masked)
 */
function ws_note_lock_state($viewer)
{
    $user_id = (int) $viewer['id'];
    $row = ws_note_lock_row($user_id);
    $times = array();

    foreach (ws_note_lock_times() as $minutes => $label) {
        $times[] = array('minutes' => (int) $minutes, 'label' => $label);
    }

    return array(
        'ready'   => ws_note_lock_ready(),
        'set_up'  => (bool) $row,
        'minutes' => $row ? (int) $row['open_minutes'] : 5,
        'times'   => $times,
        'open'    => $row ? ws_note_lock_is_open($user_id) : false,
        'wait'    => $row ? max(0, (int) $row['wait_until'] - time()) : 0,
        'email'   => ws_note_lock_mask_email(ws_note_lock_email($user_id)),
    );
}

/**
 * The address the person's code goes to: their account's.
 *
 * @param int $user_id
 * @return string
 */
function ws_note_lock_email($user_id)
{
    return trim((string) db_value("SELECT user_email FROM user WHERE user_id = '" . (int) $user_id . "'"));
}

/**
 * A refusal: ok false, error (for the person), field, code (for the screen).
 *
 * @param string $code
 * @param string $error
 * @param string $field
 * @param int    $wait  seconds of a pause left
 * @return array
 */
function ws_note_lock_refusal($code, $error, $field = '', $wait = 0)
{
    return array('ok' => false, 'code' => $code, 'error' => $error, 'field' => $field, 'wait' => (int) $wait);
}

/**
 * Checks the person's PIN, counting a wrong one (five in a row pause entry)
 * and clearing the count on a right one.
 *
 * @param int   $user_id
 * @param mixed $pin
 * @return array ok; or a refusal: no_lock, wait, pin
 */
function ws_note_lock_verify($user_id, $pin)
{
    $row = ws_note_lock_row($user_id);

    if (!$row) {
        return ws_note_lock_refusal('no_lock', lang('You have not set a PIN for your notes.'));
    }

    $now = time();

    if ((int) $row['wait_until'] > $now) {
        return ws_note_lock_refusal('wait', lang('Too many wrong PINs. Try again in 15 minutes or use a code by e-mail.'), 'pin', (int) $row['wait_until'] - $now);
    }

    if (ws_note_lock_pin_valid($pin) && password_verify((string) $pin, (string) $row['pin_hash'])) {
        if ((int) $row['fails'] > 0) {
            db("UPDATE ws_note_locks SET fails = 0 WHERE user_id = '" . (int) $user_id . "'");
        }

        return array('ok' => true);
    }

    // Counted in the database in one statement, so wrong PINs sent at once
    // from several tabs all count. MySQL sets the columns left to right, so
    // wait_until reads fails before it is changed: the fifth wrong PIN in a
    // row pauses entry and starts the count again.
    db("UPDATE ws_note_locks
        SET wait_until = IF(fails + 1 >= " . WS_NOTE_LOCK_MISSES . ", " . ($now + WS_NOTE_LOCK_PAUSE) . ", wait_until),
            fails = IF(fails + 1 >= " . WS_NOTE_LOCK_MISSES . ", 0, fails + 1)
        WHERE user_id = '" . (int) $user_id . "'");

    $wait = (int) db_value("SELECT wait_until FROM ws_note_locks WHERE user_id = '" . (int) $user_id . "'") - $now;

    if ($wait > 0) {
        return ws_note_lock_refusal('wait', lang('Too many wrong PINs. Try again in 15 minutes or use a code by e-mail.'), 'pin', $wait);
    }

    return ws_note_lock_refusal('pin', lang('That PIN is not right.'), 'pin');
}

/**
 * The refusal of a new PIN typed twice, for the person; null when it can be
 * kept.
 *
 * @param mixed $pin
 * @param mixed $pin2
 * @return array|null
 */
function ws_note_lock_new_pin_check($pin, $pin2)
{
    $field = ws_note_lock_new_pin_refusal($pin, $pin2);

    if ($field === 'pin') {
        return ws_note_lock_refusal('pin_shape', lang('A PIN is 4 to 8 digits.'), 'pin');
    }

    if ($field === 'pin2') {
        return ws_note_lock_refusal('pin_twice', lang('The two PINs are not the same.'), 'pin2');
    }

    return null;
}

/**
 * Sets the person's PIN up: a new PIN typed twice and the time. The lock
 * opens in this session.
 *
 * @param array $viewer
 * @param mixed $pin
 * @param mixed $pin2
 * @param mixed $minutes
 * @return array ok; or a refusal
 */
function ws_note_lock_setup($viewer, $pin, $pin2, $minutes)
{
    $user_id = (int) $viewer['id'];

    if (!ws_note_lock_ready()) {
        return ws_note_lock_refusal('not_ready', lang('Locking notes needs the latest database update.'));
    }

    if (ws_note_lock_row($user_id)) {
        return ws_note_lock_refusal('set_up', lang('You have already set a PIN for your notes.'));
    }

    $refusal = ws_note_lock_new_pin_check($pin, $pin2);

    if ($refusal) {
        return $refusal;
    }

    $minutes = ws_note_lock_minutes($minutes);

    if ($minutes === null) {
        $minutes = 5;
    }

    $now = time();

    db("INSERT INTO ws_note_locks (user_id, pin_hash, open_minutes, fails, wait_until, created_at, updated_at)
        VALUES ('" . $user_id . "', '" . e(password_hash((string) $pin, PASSWORD_DEFAULT)) . "', '" . (int) $minutes . "', 0, 0, '" . $now . "', '" . $now . "')
        ON DUPLICATE KEY UPDATE pin_hash = VALUES(pin_hash), open_minutes = VALUES(open_minutes), fails = 0, wait_until = 0, updated_at = VALUES(updated_at)");

    ws_note_lock_has($user_id, true);
    ws_note_lock_open_session($user_id);

    return array('ok' => true);
}

/**
 * Opens the lock with the PIN (for the note asked, when the person chose
 * "every time").
 *
 * @param array $viewer
 * @param mixed $pin
 * @param int   $note_id
 * @return array ok; or a refusal
 */
function ws_note_lock_open($viewer, $pin, $note_id = 0)
{
    $verified = ws_note_lock_verify((int) $viewer['id'], $pin);

    if (!$verified['ok']) {
        return $verified;
    }

    ws_note_lock_open_session((int) $viewer['id'], $note_id);

    return array('ok' => true);
}

/**
 * A new PIN typed twice, proven with the current PIN or with the code sent
 * by e-mail (ws_note_lock_code_send()). Either opens the lock and ends a
 * pause of wrong PINs.
 *
 * @param array  $viewer
 * @param string $proof  'pin' or 'code'
 * @param mixed  $secret the current PIN or the code
 * @param mixed  $pin
 * @param mixed  $pin2
 * @return array ok; or a refusal
 */
function ws_note_lock_change($viewer, $proof, $secret, $pin, $pin2)
{
    $user_id = (int) $viewer['id'];

    if (!ws_note_lock_row($user_id)) {
        return ws_note_lock_refusal('no_lock', lang('You have not set a PIN for your notes.'));
    }

    $refusal = ws_note_lock_new_pin_check($pin, $pin2);

    if ($refusal) {
        return $refusal;
    }

    if ($proof === 'code') {
        $store = &$_SESSION['software']['ws_note_lock_code'];
        $result = ws_note_lock_code_check($store, $user_id, (string) $secret, time());

        if ($result === 'code') {
            return ws_note_lock_refusal('code', lang('That code is not right.'), 'code');
        }

        if ($result !== '') {
            return ws_note_lock_refusal('code_expired', lang('That code no longer works. Ask for a new one.'), 'code');
        }
    } else {
        $verified = ws_note_lock_verify($user_id, $secret);

        if (!$verified['ok']) {
            return $verified;
        }
    }

    db("UPDATE ws_note_locks SET pin_hash = '" . e(password_hash((string) $pin, PASSWORD_DEFAULT)) . "', fails = 0, wait_until = 0, updated_at = '" . time() . "'
        WHERE user_id = '" . $user_id . "'");

    ws_note_lock_open_session($user_id);

    return array('ok' => true);
}

/**
 * A new time the lock stays open, proven with the PIN.
 *
 * @param array $viewer
 * @param mixed $pin
 * @param mixed $minutes
 * @return array ok; or a refusal
 */
function ws_note_lock_set_time($viewer, $pin, $minutes)
{
    $minutes = ws_note_lock_minutes($minutes);

    if ($minutes === null) {
        return ws_note_lock_refusal('minutes', lang('Choose when the PIN is asked again.'), 'minutes');
    }

    $verified = ws_note_lock_verify((int) $viewer['id'], $pin);

    if (!$verified['ok']) {
        return $verified;
    }

    db("UPDATE ws_note_locks SET open_minutes = '" . (int) $minutes . "', updated_at = '" . time() . "' WHERE user_id = '" . (int) $viewer['id'] . "'");

    ws_note_lock_open_session((int) $viewer['id']);

    return array('ok' => true);
}

/**
 * Turns the lock off with the PIN: the PIN is forgotten and every note and
 * group of the person is unlocked.
 *
 * @param array $viewer
 * @param mixed $pin
 * @return array ok; or a refusal
 */
function ws_note_lock_turn_off($viewer, $pin)
{
    $user_id = (int) $viewer['id'];
    $verified = ws_note_lock_verify($user_id, $pin);

    if (!$verified['ok']) {
        return $verified;
    }

    db("UPDATE ws_notes SET locked = 0 WHERE user_id = '" . $user_id . "' AND locked = 1");
    db("UPDATE ws_note_groups SET locked = 0 WHERE user_id = '" . $user_id . "' AND locked = 1");
    db("DELETE FROM ws_note_locks WHERE user_id = '" . $user_id . "'");
    ws_note_lock_has($user_id, false);

    ws_note_lock_close();
    unset($_SESSION['software']['ws_note_lock_code']);

    return array('ok' => true);
}

/**
 * E-mails the person a code that sets a new PIN, to their account's address,
 * in their language, and keeps it in the session. At most three an hour.
 *
 * @param array $viewer
 * @return array ok, email (masked); or a refusal: no_lock, email_none,
 *               email_limit, email_send
 */
function ws_note_lock_code_send($viewer)
{
    $user_id = (int) $viewer['id'];

    if (!ws_note_lock_row($user_id)) {
        return ws_note_lock_refusal('no_lock', lang('You have not set a PIN for your notes.'));
    }

    $email = ws_note_lock_email($user_id);

    if (($email === '') || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ws_note_lock_refusal('email_none', lang('Your account has no e-mail address to send a code to.'));
    }

    if (function_exists('waf_rate_exceeded') && waf_rate_exceeded((string) $user_id, 'note-lock-mail', 3, 3600)) {
        return ws_note_lock_refusal('email_limit', lang('Three codes were sent this hour. Try again later.'));
    }

    $code = sprintf('%06d', random_int(0, 999999));
    $site = (defined('HOSTNAME_SETTING') && (HOSTNAME_SETTING !== '')) ? (string) HOSTNAME_SETTING : (string) ORGANIZATION_NAME;

    // Sent now, not queued: the person waits for it on the screen. Marked
    // secret, so the code stays out of the failure log and notice.
    $sent = email(array(
        'to'                 => $email,
        'from_name'          => ORGANIZATION_NAME,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => lang('Your code for a new notes PIN'),
        'body'               =>
            lang(array('string' => 'Your code for a new PIN for your notes at {var:1}:', 'vars' => $site)) . "\n"
            . "\n"
            . $code . "\n"
            . "\n"
            . lang('It works for 10 minutes.') . "\n"
            . "\n"
            . lang('If you did not ask for it, change your password and your notes PIN now.'),
        'secret'             => true));

    if (!$sent) {
        return ws_note_lock_refusal('email_send', lang('The code could not be sent. Try again later.'));
    }

    ws_note_lock_code_keep($_SESSION['software']['ws_note_lock_code'], $user_id, $code, time());

    return array('ok' => true, 'email' => ws_note_lock_mask_email($email));
}

// ── The gate ────────────────────────────────────────────────────────────────

/**
 * Whether a note is behind a person's lock: their own locked note, or a
 * note in one of their locked groups. Pure.
 *
 * @param array $note             id, user_id, locked
 * @param int   $user_id
 * @param bool  $has_lock         the person set a PIN
 * @param int[] $locked_group_ids notes the person keeps in their locked groups
 * @return bool
 */
function ws_note_lock_behind($note, $user_id, $has_lock, $locked_group_ids)
{
    if (!$has_lock || !is_array($note) || ((int) $user_id <= 0)) {
        return false;
    }

    if (((int) ($note['user_id'] ?? 0) === (int) $user_id) && !empty($note['locked'])) {
        return true;
    }

    return in_array((int) ($note['id'] ?? 0), array_map('intval', (array) $locked_group_ids), true);
}

/**
 * The notes a person keeps in their own locked groups.
 *
 * @param int $user_id
 * @return int[]
 */
function ws_note_lock_group_note_ids($user_id)
{
    if (((int) $user_id <= 0) || !ws_note_lock_ready() || !ws_note_groups_ready()) {
        return array();
    }

    return array_map('intval', (array) db_values("SELECT p.note_id FROM ws_note_places p
        INNER JOIN ws_note_groups g ON g.id = p.group_id
        WHERE p.user_id = '" . (int) $user_id . "' AND g.user_id = '" . (int) $user_id . "' AND g.locked = 1"));
}

/**
 * Whether a note is behind the person's lock (ws_note_lock_behind()).
 *
 * @param int   $user_id
 * @param array $note
 * @return bool
 */
function ws_note_behind_lock($user_id, $note)
{
    if (!ws_note_lock_ready() || !is_array($note) || !ws_note_lock_has($user_id)) {
        return false;
    }

    if (((int) $note['user_id'] === (int) $user_id) && !empty($note['locked'])) {
        return true;
    }

    return (bool) db_value("SELECT 1 FROM ws_note_places p
        INNER JOIN ws_note_groups g ON g.id = p.group_id
        WHERE p.user_id = '" . (int) $user_id . "' AND p.note_id = '" . (int) $note['id'] . "'
            AND g.user_id = '" . (int) $user_id . "' AND g.locked = 1
        LIMIT 1");
}

/**
 * Whether a note is behind the person's lock and the lock is closed for it.
 *
 * @param array $viewer
 * @param array $note
 * @return bool
 */
function ws_note_lock_closed($viewer, $note)
{
    return ws_note_behind_lock((int) $viewer['id'], $note) && !ws_note_lock_is_open((int) $viewer['id'], (int) $note['id']);
}

/**
 * The gate of every use of a note on the screen: '' when the person may go
 * on (the open lock renewed when the note is behind it), else the sentence
 * of note_locked.
 *
 * @param array $viewer
 * @param array $note
 * @return string
 */
function ws_note_lock_gate($viewer, $note)
{
    if (!ws_note_behind_lock((int) $viewer['id'], $note)) {
        return '';
    }

    if (!ws_note_lock_is_open((int) $viewer['id'], (int) $note['id'])) {
        return lang('This note is locked. Open it with your PIN.');
    }

    ws_note_lock_touch((int) $viewer['id'], (int) $note['id']);

    return '';
}

/**
 * Why a note cannot be shared or sent to a channel: it is locked, or sits
 * in a locked group of its owner. '' when it can.
 *
 * @param array $note
 * @return string
 */
function ws_note_lock_share_refusal($note)
{
    if (!is_array($note) || !ws_note_behind_lock((int) $note['user_id'], $note)) {
        return '';
    }

    return lang('A locked note cannot be shared. Unlock it first.');
}

/**
 * A note as the list shows it while the lock is closed: its title and the
 * lock, nothing of its text. Pure.
 *
 * @param array $present ws_notes_present()
 * @return array
 */
function ws_note_lock_present_closed($present)
{
    $present['excerpt'] = '';
    $present['lock_closed'] = true;

    if (trim((string) ($present['title'] ?? '')) === '') {
        $present['name'] = lang('Locked note');
    }

    foreach (array('body' => '', 'html' => '', 'labels' => array(), 'claude' => array(), 'scheduled' => array()) as $key => $empty) {
        if (array_key_exists($key, $present)) {
            $present[$key] = $empty;
        }
    }

    return $present;
}

/**
 * The shares a lock would take back from a note: its direct shares and the
 * ones its groups give it.
 *
 * @param int $note_id
 * @return int
 */
function ws_note_lock_share_count($note_id)
{
    return (int) db_value("SELECT COUNT(*) FROM ws_note_shares WHERE note_id = '" . (int) $note_id . "'");
}

/**
 * Takes back a note's own shares (the people and channels it was shared
 * with by itself), as its owner would one by one.
 *
 * @param array $viewer the owner
 * @param array $note
 */
function ws_note_lock_unshare_direct($viewer, $note)
{
    $where = ws_note_groups_ready() ? " AND via_group = 0" : '';

    foreach ((array) db_values("SELECT id FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "'" . $where) as $share_id) {
        ws_note_unshare($viewer, $note, (int) $share_id);
    }
}

/**
 * Locks or unlocks one of the person's own notes. Locking a shared note
 * asks first ($confirm) and then takes its shares back; unlocking needs the
 * lock open for the note, or the PIN.
 *
 * @param array  $viewer
 * @param array  $note    the person's own
 * @param bool   $locked
 * @param bool   $confirm
 * @param string $pin     for unlocking while the lock is closed
 * @return array ok, shares (taken back); or a refusal (confirm: shares)
 */
function ws_note_lock_note($viewer, $note, $locked, $confirm = false, $pin = '')
{
    $user_id = (int) $viewer['id'];

    if (!is_array($note) || ((int) $note['user_id'] !== $user_id)) {
        return ws_note_lock_refusal('owner', lang('Only the one who wrote a note can lock it.'));
    }

    if (!ws_note_lock_has($user_id)) {
        return ws_note_lock_refusal('no_lock', lang('Set a PIN for your notes first.'));
    }

    if (!$locked) {
        $refusal = ws_note_lock_unlock_proof($viewer, (int) $note['id'], $pin);

        if ($refusal) {
            return $refusal;
        }

        db("UPDATE ws_notes SET locked = 0 WHERE id = '" . (int) $note['id'] . "'");
        ws_note_group_shares_note_changed($note, $user_id);

        return array('ok' => true, 'shares' => 0);
    }

    $shares = ws_note_lock_share_count($note['id']);

    if (($shares > 0) && !$confirm) {
        return ws_note_lock_refusal('confirm', lang('Locking takes this note\'s shares back: nobody else will see it.')) + array('shares' => $shares);
    }

    db("UPDATE ws_notes SET locked = 1 WHERE id = '" . (int) $note['id'] . "'");
    $note['locked'] = 1;

    ws_note_lock_unshare_direct($viewer, $note);
    ws_note_group_shares_note_changed($note, $user_id);

    return array('ok' => true, 'shares' => $shares);
}

/**
 * Locks or unlocks one of the person's own groups. Locking a shared group,
 * or one holding notes of the person that are shared, asks first and then
 * takes back the group's shares and those of the person's notes in it.
 *
 * @param array  $viewer
 * @param array  $group   ws_note_group_for()
 * @param bool   $locked
 * @param bool   $confirm
 * @param string $pin     for unlocking while the lock is closed
 * @return array ok, shares; or a refusal (confirm: shares)
 */
function ws_note_lock_group($viewer, $group, $locked, $confirm = false, $pin = '')
{
    $user_id = (int) $viewer['id'];

    if (!is_array($group) || ((int) $group['user_id'] !== $user_id)) {
        return ws_note_lock_refusal('owner', lang('Only the owner of a group can lock it.'));
    }

    if (!ws_note_lock_has($user_id)) {
        return ws_note_lock_refusal('no_lock', lang('Set a PIN for your notes first.'));
    }

    if (!$locked) {
        $refusal = ws_note_lock_unlock_proof($viewer, 0, $pin);

        if ($refusal) {
            return $refusal;
        }

        db("UPDATE ws_note_groups SET locked = 0, updated_at = '" . time() . "' WHERE id = '" . (int) $group['id'] . "'");
        ws_note_group_share_sync((int) $group['id'], $user_id);

        return array('ok' => true, 'shares' => 0);
    }

    $own = (array) db_items("SELECT n.* FROM ws_note_places p INNER JOIN ws_notes n ON n.id = p.note_id
        WHERE p.group_id = '" . (int) $group['id'] . "' AND n.user_id = '" . $user_id . "'");

    $shares = (int) db_value("SELECT COUNT(*) FROM ws_note_group_shares WHERE group_id = '" . (int) $group['id'] . "'");

    foreach ($own as $note) {
        $shares += (int) db_value("SELECT COUNT(*) FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "'" . " AND via_group = 0");
    }

    if (($shares > 0) && !$confirm) {
        return ws_note_lock_refusal('confirm', lang('Locking takes back the shares of this group and of your notes in it: nobody else will see them.')) + array('shares' => $shares);
    }

    db("UPDATE ws_note_groups SET locked = 1, updated_at = '" . time() . "' WHERE id = '" . (int) $group['id'] . "'");
    $group['locked'] = 1;

    ws_note_group_shares_forget($group, $user_id);

    foreach ($own as $note) {
        ws_note_lock_unshare_direct($viewer, $note);
        ws_note_group_shares_note_changed($note, $user_id);
    }

    return array('ok' => true, 'shares' => $shares);
}

/**
 * What unlocking asks: the lock open (for the note, when one is named), or
 * the PIN typed with it. Null when it may go on.
 *
 * @param array  $viewer
 * @param int    $note_id
 * @param string $pin
 * @return array|null a refusal
 */
function ws_note_lock_unlock_proof($viewer, $note_id, $pin)
{
    if (is_string($pin) && ($pin !== '')) {
        $verified = ws_note_lock_verify((int) $viewer['id'], $pin);

        return $verified['ok'] ? null : $verified;
    }

    if (ws_note_lock_is_open((int) $viewer['id'], (int) $note_id)) {
        return null;
    }

    return ws_note_lock_refusal('note_locked', lang('Type your PIN to unlock it.'), 'pin');
}

/**
 * A note going into a group: a note of the person's own that is shared,
 * going into their locked group, asks first ($confirm) and then loses its
 * shares (after the move, ws_note_lock_place_after()).
 *
 * @param array $viewer
 * @param array $note
 * @param int   $group_id
 * @param bool  $confirm
 * @return array|null a refusal (confirm: shares); null when it may go on
 */
function ws_note_lock_place_check($viewer, $note, $group_id, $confirm)
{
    if (((int) $group_id <= 0) || !ws_note_lock_ready() || ((int) $note['user_id'] !== (int) $viewer['id'])) {
        return null;
    }

    $group = ws_note_group_for($viewer, (int) $group_id);

    if (!$group || empty($group['locked'])) {
        return null;
    }

    $shares = ws_note_lock_share_count($note['id']);

    if (($shares > 0) && !$confirm) {
        return ws_note_lock_refusal('confirm', lang('This note is shared. In a locked group its shares are taken back: nobody else will see it.')) + array('shares' => $shares);
    }

    return null;
}

/**
 * After a note of the person's own went into their locked group: its own
 * shares go (the group's are kept in step by the move itself).
 *
 * @param array $viewer
 * @param array $note
 * @param int   $group_id
 */
function ws_note_lock_place_after($viewer, $note, $group_id)
{
    if (((int) $group_id > 0) && ((int) $note['user_id'] === (int) $viewer['id'])
        && ws_note_lock_ready() && ws_note_behind_lock((int) $viewer['id'], $note)) {
        ws_note_lock_unshare_direct($viewer, $note);
        ws_note_group_shares_note_changed($note, (int) $viewer['id']);
    }
}

/**
 * The person's lock as the list reads it, once: whether they set a PIN, the
 * notes in their locked groups, and what the session's open lock holds.
 *
 * @param int $user_id
 * @return array has, group_note_ids, minutes, open
 */
function ws_note_lock_view($user_id)
{
    $row = ws_note_lock_ready() ? ws_note_lock_row($user_id) : null;

    return array(
        'has'            => (bool) $row,
        'group_note_ids' => $row ? ws_note_lock_group_note_ids($user_id) : array(),
        'minutes'        => $row ? (int) $row['open_minutes'] : 0,
        'open'           => $row ? ws_note_lock_session() : null,
    );
}

/**
 * The lock marks of a presented note, and its text taken away while the
 * lock is closed for it (ws_note_lock_present_closed()). Pure.
 *
 * @param array $present ws_notes_present()
 * @param array $row     the note's row (user_id, locked)
 * @param int   $user_id the reader
 * @param array $view    ws_note_lock_view()
 * @param int   $now
 * @return array with locked (the owner's mark), lock_group (in a locked
 *               group of the reader), lock_closed
 */
function ws_note_lock_present($present, $row, $user_id, $view, $now)
{
    $id = (int) $present['id'];
    $present['locked'] = ((int) $row['user_id'] === (int) $user_id) && !empty($row['locked']);
    $present['lock_group'] = in_array($id, (array) $view['group_note_ids'], true);
    $present['lock_closed'] = false;

    if (ws_note_lock_behind($row, $user_id, $view['has'], $view['group_note_ids'])
        && !ws_note_lock_open_holds($view['open'], $user_id, $view['minutes'], $now, $id)) {
        $present = ws_note_lock_present_closed($present);
    }

    return $present;
}

/**
 * The list while the lock is closed: notes in a locked group are not
 * listed until the list is opened (only the group's name shows), and a
 * search finds a locked note by its title alone. Pure.
 *
 * @param array[] $notes     ws_note_lock_present() rows
 * @param bool    $list_open the lock is open for the list
 * @param string  $search
 * @return array[]
 */
function ws_note_lock_list_filter($notes, $list_open, $search)
{
    $search = trim((string) $search);
    $out = array();

    foreach ((array) $notes as $note) {
        if (!empty($note['lock_group']) && !$list_open) {
            continue;
        }

        if (!empty($note['lock_closed']) && ($search !== '') && (mb_stripos((string) ($note['title'] ?? ''), $search) === false)) {
            continue;
        }

        $out[] = $note;
    }

    return $out;
}

/**
 * The person's groups while the lock is closed for the list: a locked group
 * says nothing of its notes, not even how many. Pure.
 *
 * @param array[] $groups    ws_note_group_present()
 * @param bool    $list_open
 * @return array[] with lock_closed
 */
function ws_note_lock_groups_redact($groups, $list_open)
{
    foreach ((array) $groups as $index => $group) {
        $closed = !empty($group['locked']) && !$list_open;
        $groups[$index]['lock_closed'] = $closed;

        if ($closed) {
            $groups[$index]['count'] = null;
        }
    }

    return $groups;
}
