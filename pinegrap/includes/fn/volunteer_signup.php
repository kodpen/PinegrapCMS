<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A volunteer's own shifts, from My Account or the signed link
 * (volunteer.php): which actions a sign-up of theirs offers (Cancel until
 * the programme's cut-off, Offer my place past it, Keep my place), which
 * shifts they fit (Sign up, Join the waitlist, Take this place); then the
 * store: the link issued and found, the view of a volunteer's programmes,
 * each action written under the shift's lock, the waitlist pass after a
 * freed place and the coordinator's answer to a swap. Loaded by
 * volunteer.php, the my_account widget and the programme screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_board.php');

// How far ahead "Shifts for you" looks, in days, and how many it lists.
if (!defined('PG_VOLUNTEER_AHEAD_DAYS')) {
    define('PG_VOLUNTEER_AHEAD_DAYS', 60);
}

if (!defined('PG_VOLUNTEER_AHEAD_SHIFTS')) {
    define('PG_VOLUNTEER_AHEAD_SHIFTS', 100);
}

/**
 * For each offered place of a shift, the waiting sign-up that asks to take
 * it (its swap_from), when the swap waits for a coordinator. Pure.
 *
 * @param array $signups the shift's sign-ups: id, state, swap_from
 * @return array offered sign-up id => asking sign-up id
 */
function pg_volunteer_swaps_pending(array $signups)
{
    $pending = array();

    foreach ($signups as $row) {
        if (((string) $row['state'] === 'waiting') && ((int) ($row['swap_from'] ?? 0) > 0)) {
            $pending[(int) $row['swap_from']] = (int) $row['id'];
        }
    }

    return $pending;
}

/**
 * What a volunteer can do with a sign-up of theirs: 'cancel' (a signed
 * place until the cut-off, a waitlist place or a swap asked until the
 * start), 'offer' (a signed place past the cut-off), 'keep' (take an offer
 * back while nobody asked for it). Nothing once the shift started. Pure.
 *
 * @param array  $signup       state, offered_at, swap_from
 * @param array  $shift        start
 * @param string $now
 * @param int    $cutoff_hours
 * @param bool   $asked        another volunteer waits to take this place
 * @return array
 */
function pg_volunteer_signup_actions(array $signup, array $shift, $now, $cutoff_hours, $asked = false)
{
    $start = pg_volunteer_minutes($shift['start'] ?? '');
    $at = pg_volunteer_minutes($now);

    if (($start === null) || ($at === null) || ($at >= $start)) {
        return array();
    }

    if ((string) $signup['state'] === 'waiting') {
        return array('cancel');
    }

    if ((string) $signup['state'] !== 'signed') {
        return array();
    }

    if (pg_volunteer_can_cancel($shift['start'], $now, $cutoff_hours)) {
        return array('cancel');
    }

    if ((int) ($signup['offered_at'] ?? 0) > 0) {
        return $asked ? array() : array('keep');
    }

    return array('offer');
}

/**
 * What a volunteer with no live sign-up on a shift can do there, role by
 * role: 'sign_up' while the role has a free place, 'take' an offered place
 * nobody asked for yet (the first by id), else 'waitlist'. Only roles the
 * volunteer fits, only before the start. Pure.
 *
 * @param array  $volunteer pg_volunteer_fit_reasons()' volunteer
 * @param array  $roles     the programme's roles: key => skills, trainings,
 *                          documents
 * @param array  $shift     id, start, end, roles (key => count)
 * @param array  $signups   the shift's sign-ups: id, volunteer_id, role,
 *                          state, offered_at, swap_from
 * @param string $now
 * @return array rows of role, action, signup (the offered row for 'take')
 */
function pg_volunteer_shift_choices(array $volunteer, array $roles, array $shift, array $signups, $now)
{
    $start = pg_volunteer_minutes($shift['start'] ?? '');
    $at = pg_volunteer_minutes($now);

    if (($start === null) || ($at === null) || ($at >= $start)) {
        return array();
    }

    foreach ($signups as $row) {
        if (((int) $row['volunteer_id'] === (int) ($volunteer['id'] ?? 0)) && in_array((string) $row['state'], array('signed', 'waiting'), true)) {
            return array();
        }
    }

    $asked = pg_volunteer_swaps_pending($signups);
    $choices = array();

    foreach ((array) ($shift['roles'] ?? array()) as $key => $count) {
        $key = (string) $key;

        if (!isset($roles[$key]) || !pg_volunteer_fits($volunteer, $roles[$key], $shift)) {
            continue;
        }

        $filled = 0;
        $offered = array();

        foreach ($signups as $row) {
            if (((string) $row['role'] === $key) && ((string) $row['state'] === 'signed')) {
                $filled++;

                if (((int) ($row['offered_at'] ?? 0) > 0) && !isset($asked[(int) $row['id']])) {
                    $offered[] = (int) $row['id'];
                }
            }
        }

        sort($offered);

        if ($filled < (int) $count) {
            $choices[] = array('role' => $key, 'action' => 'sign_up', 'signup' => 0);
        } elseif ($offered) {
            $choices[] = array('role' => $key, 'action' => 'take', 'signup' => $offered[0]);
        } elseif ((int) $count > 0) {
            $choices[] = array('role' => $key, 'action' => 'waitlist', 'signup' => 0);
        }
    }

    return $choices;
}

/**
 * Whether this address opened volunteer.php too often: 120 times in five
 * minutes, on the firewall's counters (the tickets page's way).
 *
 * @return int seconds to wait, 0 when it may go on
 */
function pg_volunteer_page_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return 0;
    }

    $ip = (string) waf_client_ip();

    if (($ip === '') || (function_exists('waf_ip_is_counted') && !waf_ip_is_counted($ip)) || !waf_rate_exceeded(waf_ip_subject($ip), 'volunteer_page', 120, 300)) {
        return 0;
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-volunteer-page'));
    }

    return 300;
}

/**
 * The address of a volunteer's signed page.
 *
 * @param int    $volunteer_id
 * @param string $token
 * @return string
 */
function pg_volunteer_link_url($volunteer_id, $token)
{
    return URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/volunteer.php?v=' . (int) $volunteer_id . '&t=' . $token;
}

/**
 * Issues a new signed link for a volunteer (the old one stops working) and
 * e-mails it to them.
 *
 * @param array $programme
 * @param array $volunteer pg_volunteer_people() row
 * @return bool whether the e-mail was queued
 */
function pg_volunteer_link_send(array $programme, array $volunteer)
{
    $issued = time();
    $token = pg_volunteer_link_token((int) $volunteer['id'], $issued, defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : '');

    if (($token === '') || !validate_email_address((string) $volunteer['email_address'])) {
        return false;
    }

    if (!db_q("UPDATE volunteers SET link_hash = ?, link_issued_at = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ?", array(pg_volunteer_link_token_hash($token), $issued, (int) $volunteer['id']))) {
        return false;
    }

    _pg_volunteer_mail((string) $volunteer['email_address'], array(
        'subject' => strtr(lang('{programme}: your shifts'), array('{programme}' => $programme['name'])),
        'body'    => strtr(lang('Hello {name},') . "\n\n" . lang('Sign up for the shifts of {programme}, cancel or offer your place on this page. Keep the link to yourself: it opens your shifts without a password.'), array('{name}' => (string) $volunteer['first_name'], '{programme}' => $programme['name']))
            . "\n\n" . pg_volunteer_link_url((int) $volunteer['id'], $token),
    ));

    return true;
}

/**
 * The volunteer a signed link opens: the token checked against the stored
 * hash; null for a wrong or withdrawn link or a volunteer who left.
 *
 * @param mixed $id    ?v=
 * @param mixed $token ?t=
 * @return int|null volunteer id
 */
function pg_volunteer_link_find($id, $token)
{
    if (!is_scalar($id) || !ctype_digit((string) $id) || !is_string($token) || !pg_volunteer_ready()) {
        return null;
    }

    $row = db_item("SELECT id, link_hash, state FROM volunteers WHERE id = '" . (int) $id . "'");

    if (!$row || ((string) $row['state'] === 'left') || !pg_volunteer_link_token_valid($token, (string) $row['link_hash'])) {
        return null;
    }

    return (int) $row['id'];
}

/**
 * A volunteer as pg_volunteer_fit_reasons() reads them, with their
 * programme: skills, free hours, documents, completed courses and their
 * signed shifts from yesterday on.
 *
 * @param int $volunteer_id
 * @return array|null volunteer with 'programme'
 */
function pg_volunteer_self_load($volunteer_id)
{
    $row = db_item("SELECT volunteers.*, contacts.first_name, contacts.last_name, contacts.email_address FROM volunteers LEFT JOIN contacts ON contacts.id = volunteers.contact_id WHERE volunteers.id = '" . (int) $volunteer_id . "' AND volunteers.state <> 'left'");
    $programme = $row ? pg_volunteer_programme_get((int) $row['programme_id']) : null;

    if ($programme === null) {
        return null;
    }

    $row['id'] = (int) $row['id'];
    $row['programme'] = $programme;
    $row['skills'] = pg_volunteer_list_text((array) json_decode((string) $row['skills'], true));
    $row['hours'] = pg_booking_hours_clean($row['hours']);
    $row['documents'] = (array) db_items("SELECT volunteer_documents.kind, volunteer_documents.valid_until, files.name AS file_name FROM volunteer_documents
        LEFT JOIN files ON (files.id = volunteer_documents.file_id) AND (volunteer_documents.file_id > 0)
        WHERE volunteer_documents.volunteer_id = '" . $row['id'] . "' ORDER BY volunteer_documents.valid_until DESC");
    $row['trainings'] = array();
    $row['shifts'] = array();

    foreach ((array) db_items("SELECT volunteer_shifts.id, volunteer_shifts.start_time, volunteer_shifts.end_time FROM volunteer_signups INNER JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
        WHERE volunteer_signups.volunteer_id = '" . $row['id'] . "' AND volunteer_signups.state = 'signed' AND volunteer_shifts.end_time >= '" . escape(pg_volunteer_add_days(date('Y-m-d'), -1)) . "'") as $shift) {
        $row['shifts'][] = array('id' => (int) $shift['id'], 'start' => $shift['start_time'], 'end' => $shift['end_time']);
    }

    if (pg_course_ready() && ((int) $row['contact_id'] > 0)) {
        $row['trainings'] = array_map('intval', (array) db_values("SELECT course_progress.course_id FROM course_progress INNER JOIN user ON user.user_id = course_progress.user_id
            WHERE course_progress.kind = '" . (int) pg_course_kind('complete') . "' AND user.user_contact = '" . (int) $row['contact_id'] . "'"));
    }

    return $row;
}

/**
 * The sign-ups of shifts, by shift id.
 *
 * @param array $shift_ids
 * @return array shift id => rows of id, volunteer_id, role, state,
 *               offered_at, swap_from, created_at
 */
function pg_volunteer_shift_signups(array $shift_ids)
{
    $by = array();

    if ($shift_ids) {
        foreach ((array) db_items("SELECT id, shift_id, volunteer_id, role, state, offered_at, swap_from, created_at FROM volunteer_signups WHERE shift_id IN (" . implode(', ', array_map('intval', $shift_ids)) . ") AND state IN ('signed', 'waiting') ORDER BY id") as $row) {
            $by[(int) $row['shift_id']][] = $row;
        }
    }

    return $by;
}

/**
 * What a volunteer's page shows of one profile: the programme, the
 * volunteer's coming sign-ups with their actions, the shifts they fit with
 * their choices (active volunteers of an open programme only) and their
 * documents' kinds and dates.
 *
 * @param int    $volunteer_id
 * @param string $now
 * @return array|null programme, volunteer, mine, open, documents
 */
function pg_volunteer_self_view($volunteer_id, $now)
{
    $volunteer = pg_volunteer_self_load($volunteer_id);

    if ($volunteer === null) {
        return null;
    }

    $programme = $volunteer['programme'];
    $mine = array();

    foreach ((array) db_items("SELECT volunteer_signups.*, volunteer_shifts.place, volunteer_shifts.start_time, volunteer_shifts.end_time FROM volunteer_signups INNER JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
        WHERE volunteer_signups.volunteer_id = '" . $volunteer['id'] . "' AND volunteer_signups.state IN ('signed', 'waiting') AND volunteer_shifts.end_time >= '" . escape($now) . "' ORDER BY volunteer_shifts.start_time, volunteer_signups.id") as $row) {
        $mine[(int) $row['shift_id']] = $row;
    }

    $open = array();

    if (($programme['state'] === 'open') && ($volunteer['state'] === 'active')) {
        $shifts = array();

        foreach ((array) db_items("SELECT * FROM volunteer_shifts WHERE programme_id = '" . (int) $programme['id'] . "' AND start_time > '" . escape($now) . "' AND start_time < '" . escape(pg_volunteer_add_days(substr($now, 0, 10), PG_VOLUNTEER_AHEAD_DAYS)) . "' ORDER BY start_time, id LIMIT " . (int) PG_VOLUNTEER_AHEAD_SHIFTS) as $row) {
            $shifts[(int) $row['id']] = pg_volunteer_shift_decode($row);
        }

        $signups = pg_volunteer_shift_signups(array_keys($shifts));

        foreach ($shifts as $id => $shift) {
            $choices = isset($mine[$id]) ? array() : pg_volunteer_shift_choices($volunteer, $programme['roles'], $shift, $signups[$id] ?? array(), $now);

            if ($choices) {
                $open[] = array('shift' => $shift, 'choices' => $choices);
            }
        }
    }

    $mine_signups = pg_volunteer_shift_signups(array_keys($mine));

    foreach ($mine as $id => $row) {
        $asked = pg_volunteer_swaps_pending($mine_signups[$id] ?? array());
        $mine[$id]['shift'] = array('id' => $id, 'start' => $row['start_time'], 'end' => $row['end_time'], 'place' => $row['place']);
        $mine[$id]['actions'] = pg_volunteer_signup_actions($row, $mine[$id]['shift'], $now, $programme['cutoff_hours'], isset($asked[(int) $row['id']]));
        $mine[$id]['asked'] = isset($asked[(int) $row['id']]);
    }

    return array('programme' => $programme, 'volunteer' => $volunteer, 'mine' => array_values($mine), 'open' => $open, 'documents' => $volunteer['documents']);
}

/**
 * Runs the waitlist pass of a role on a shift after a place was freed: the
 * first waiting volunteers who still fit move up (pg_volunteer_waitlist_pass())
 * and are told by e-mail. A swap waiting for a coordinator is not part of
 * the waitlist. Called under the shift's lock.
 *
 * @param array  $programme
 * @param int    $shift_id
 * @param string $role
 * @param array  $signed the sign-ups moved up are added as array(id, 'waitlist'),
 *                       for the caller to announce after the lock
 * @return int volunteers moved up
 */
function pg_volunteer_waitlist_write(array $programme, $shift_id, $role, &$signed = array())
{
    $shift = pg_volunteer_shift_get((int) $programme['id'], (int) $shift_id);

    if (($shift === null) || !isset($shift['roles'][$role]) || !isset($programme['roles'][$role])) {
        return 0;
    }

    $people = pg_volunteer_call_people($programme, $shift);
    $rows = array();

    foreach ($shift['signups'] as $signup) {
        // A swap waiting for a coordinator is no waitlist place.
        if (($signup['role'] === $role) && !(($signup['state'] === 'waiting') && ((int) $signup['swap_from'] > 0))) {
            $person = $people[(int) $signup['volunteer_id']] ?? null;
            $rows[] = array('id' => (int) $signup['id'], 'state' => $signup['state'], 'created' => date('Y-m-d H:i:s', (int) $signup['created_at']),
                'fits' => ($person !== null) && pg_volunteer_fits($person, $programme['roles'][$role], $shift), 'person' => $signup);
        }
    }

    $moved = 0;

    foreach (pg_volunteer_waitlist_pass($shift['roles'][$role], $rows) as $id) {
        if (!db_q("UPDATE volunteer_signups SET state = 'signed', updated_at = UNIX_TIMESTAMP() WHERE id = ? AND state = 'waiting'", array($id)) || (mysqli_affected_rows(db::$con) < 1)) {
            continue;
        }

        $moved++;
        $signed[] = array((int) $id, 'waitlist');

        foreach ($rows as $row) {
            if (($row['id'] === $id) && validate_email_address((string) $row['person']['email_address'])) {
                _pg_volunteer_mail((string) $row['person']['email_address'], array(
                    'subject' => strtr(lang('{programme}: a place opened for you'), array('{programme}' => $programme['name'])),
                    'body'    => strtr(lang('Hello {name},') . "\n\n" . lang('A place opened on the shift you waited for. You are signed up now.'), array('{name}' => (string) $row['person']['first_name']))
                        . "\n\n" . pg_volunteer_shift_when($shift),
                ));
            }
        }
    }

    return $moved;
}

/**
 * Finishes a swap: the offered place is cancelled and the volunteer who
 * asked for it takes it; both are told. Called under the shift's lock.
 *
 * @param array $programme
 * @param array $shift   pg_volunteer_shift_get()
 * @param array $offered the offered sign-up (from $shift['signups'])
 * @param int   $taker   the taking sign-up's id
 * @return bool
 */
function _pg_volunteer_swap_finish(array $programme, array $shift, array $offered, $taker)
{
    if (!db_q("UPDATE volunteer_signups SET state = 'cancelled', cancelled_at = UNIX_TIMESTAMP(), updated_at = UNIX_TIMESTAMP() WHERE id = ? AND state = 'signed' AND offered_at > 0", array((int) $offered['id'])) || (mysqli_affected_rows(db::$con) < 1)) {
        return false;
    }

    db_q("UPDATE volunteer_signups SET state = 'signed', swap_from = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ?", array((int) $offered['id'], (int) $taker));

    foreach ($shift['signups'] as $signup) {
        $taken = ((int) $signup['id'] === (int) $offered['id']);

        if (($taken || ((int) $signup['id'] === (int) $taker)) && validate_email_address((string) $signup['email_address'])) {
            _pg_volunteer_mail((string) $signup['email_address'], array(
                'subject' => strtr(lang('{programme}: a shift changed hands'), array('{programme}' => $programme['name'])),
                'body'    => strtr(lang('Hello {name},') . "\n\n" . ($taken ? lang('Another volunteer took the place you offered. You do not need to come.') : lang('You took the offered place. You are signed up now.')), array('{name}' => (string) $signup['first_name']))
                    . "\n\n" . pg_volunteer_shift_when($shift),
            ));
        }
    }

    return true;
}

/**
 * One action of a volunteer on a shift, written under the shift's lock so
 * a role's places never go over its count: 'sign_up', 'waitlist', 'take'
 * (an offered place: at once, or waiting for a coordinator when the
 * programme asks), 'cancel' (then the waitlist pass), 'offer', 'keep';
 * 'hours' adds hours outside a shift from the posted day, hours and note.
 *
 * @param int    $volunteer_id
 * @param string $action
 * @param int    $shift_id
 * @param string $role   for sign_up and waitlist
 * @param int    $target the offered sign-up for take
 * @return string the outcome: signed, waiting, asked, cancelled, offered,
 *                kept, hours_added, or a refusal ('refused', and for hours
 *                'day', 'hours', 'closed')
 */
function pg_volunteer_self_act($volunteer_id, $action, $shift_id, $role = '', $target = 0)
{
    // Hours worked outside a shift take no shift and no lock: their fields
    // come with the posted form (includes/fn/volunteer_hours.php).
    if ($action === 'hours') {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_hours.php');

        return pg_volunteer_outside_add($volunteer_id, $_POST);
    }

    $volunteer = pg_volunteer_self_load($volunteer_id);
    $programme = ($volunteer !== null) ? $volunteer['programme'] : null;
    $lock = 'pg_volunteer_shift_' . (int) $shift_id;

    if (($programme === null) || ((int) db_value("SELECT GET_LOCK('" . escape($lock) . "', 5)") !== 1)) {
        return 'refused';
    }

    $now = date('Y-m-d H:i:s');
    $shift = pg_volunteer_shift_get((int) $programme['id'], (int) $shift_id);
    $answer = 'refused';
    $own = null;
    // The places taken, announced once the lock is released.
    $signed = array();

    foreach (($shift !== null) ? $shift['signups'] : array() as $signup) {
        if ((int) $signup['volunteer_id'] === $volunteer['id']) {
            $own = $signup;
        }
    }

    if (($shift !== null) && in_array($action, array('sign_up', 'waitlist', 'take'), true) && ($programme['state'] === 'open')) {
        $choices = pg_volunteer_shift_choices($volunteer, $programme['roles'], $shift, $shift['signups'], $now);

        foreach ($choices as $choice) {
            if (($choice['action'] !== $action) || (($action === 'take') ? ($choice['signup'] !== (int) $target) : ($choice['role'] !== (string) $role))) {
                continue;
            }

            $state = ($action === 'sign_up') ? 'signed' : 'waiting';
            $swap = ($action === 'take') ? (int) $target : 0;
            // A cancelled row of this volunteer on this shift is used again:
            // the unique key holds one row a volunteer a shift.
            $written = ($own !== null)
                ? db_q("UPDATE volunteer_signups SET role = ?, state = ?, swap_from = ?, offered_at = 0, cancelled_at = 0, created_at = UNIX_TIMESTAMP(), updated_at = UNIX_TIMESTAMP() WHERE id = ? AND state = 'cancelled'", array($choice['role'], $state, $swap, (int) $own['id'])) && (mysqli_affected_rows(db::$con) > 0)
                : db_q("INSERT INTO volunteer_signups (programme_id, shift_id, volunteer_id, role, state, swap_from, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())", array((int) $programme['id'], (int) $shift['id'], $volunteer['id'], $choice['role'], $state, $swap));

            $row_id = ($own !== null) ? (int) $own['id'] : (int) mysqli_insert_id(db::$con);

            if ($written && ($action === 'take')) {
                $taker = $row_id;
                $answer = 'asked';

                if (!$programme['swap_approval']) {
                    $offered = null;

                    foreach ($shift['signups'] as $signup) {
                        $offered = ((int) $signup['id'] === (int) $target) ? $signup : $offered;
                    }

                    $shift = pg_volunteer_shift_get((int) $programme['id'], (int) $shift_id);
                    $answer = ($offered !== null) && _pg_volunteer_swap_finish($programme, $shift, $offered, $taker) ? 'signed' : 'asked';
                    $signed = ($answer === 'signed') ? array(array($taker, 'swap')) : array();
                }
            } elseif ($written) {
                $answer = $state;
                $signed = ($state === 'signed') ? array(array($row_id, 'sign_up')) : array();
            }

            break;
        }
    } elseif (($shift !== null) && ($own !== null)) {
        $asked = isset(pg_volunteer_swaps_pending($shift['signups'])[(int) $own['id']]);

        if (in_array($action, pg_volunteer_signup_actions($own, $shift, $now, $programme['cutoff_hours'], $asked), true)) {
            if (($action === 'cancel') && db_q("UPDATE volunteer_signups SET state = 'cancelled', offered_at = 0, cancelled_at = UNIX_TIMESTAMP(), updated_at = UNIX_TIMESTAMP() WHERE id = ? AND state = ?", array((int) $own['id'], $own['state'])) && (mysqli_affected_rows(db::$con) > 0)) {
                $answer = 'cancelled';

                // A swap asked of this place is cancelled with it.
                db_q("UPDATE volunteer_signups SET state = 'cancelled', cancelled_at = UNIX_TIMESTAMP(), updated_at = UNIX_TIMESTAMP() WHERE shift_id = ? AND swap_from = ? AND state = 'waiting'", array((int) $shift['id'], (int) $own['id']));

                if ($own['state'] === 'signed') {
                    pg_volunteer_waitlist_write($programme, (int) $shift['id'], (string) $own['role'], $signed);
                }
            } elseif (($action === 'offer') || ($action === 'keep')) {
                db_q("UPDATE volunteer_signups SET offered_at = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ? AND state = 'signed'", array(($action === 'offer') ? time() : 0, (int) $own['id']));
                $answer = ($action === 'offer') ? 'offered' : 'kept';
            }
        }
    }

    // A volunteer leaving the waitlist never held a place: the row keeps no
    // cancel stamp, so the report never counts it as a late cancel.
    if (($answer === 'cancelled') && ($own['state'] === 'waiting')) {
        db_q("UPDATE volunteer_signups SET cancelled_at = 0 WHERE id = ? AND state = 'cancelled'", array((int) $own['id']));
    }

    db_value("SELECT RELEASE_LOCK('" . escape($lock) . "')");

    foreach ($signed as $place) {
        pg_volunteer_announce('shift.signed_up', $place[0], $place[1]);
    }

    return $answer;
}

/**
 * A coordinator's answer to a swap waiting for approval: Approve hands the
 * offered place over, Refuse cancels the request (the offer stays).
 *
 * @param array $programme
 * @param int   $shift_id
 * @param int   $taker   the asking (waiting) sign-up
 * @param bool  $approve
 * @return bool
 */
function pg_volunteer_swap_decide(array $programme, $shift_id, $taker, $approve)
{
    $lock = 'pg_volunteer_shift_' . (int) $shift_id;

    if ((int) db_value("SELECT GET_LOCK('" . escape($lock) . "', 5)") !== 1) {
        return false;
    }

    $shift = pg_volunteer_shift_get((int) $programme['id'], (int) $shift_id);
    $asking = null;
    $offered = null;

    foreach (($shift !== null) ? $shift['signups'] : array() as $signup) {
        $asking = (((int) $signup['id'] === (int) $taker) && ($signup['state'] === 'waiting') && ((int) $signup['swap_from'] > 0)) ? $signup : $asking;
    }

    foreach (($asking !== null) ? $shift['signups'] : array() as $signup) {
        $offered = ((int) $signup['id'] === (int) $asking['swap_from']) ? $signup : $offered;
    }

    $done = ($offered !== null) && ($approve
        ? _pg_volunteer_swap_finish($programme, $shift, $offered, (int) $taker)
        : (db_q("UPDATE volunteer_signups SET state = 'cancelled', cancelled_at = UNIX_TIMESTAMP(), updated_at = UNIX_TIMESTAMP() WHERE id = ? AND state = 'waiting'", array((int) $taker)) && (mysqli_affected_rows(db::$con) > 0)));

    db_value("SELECT RELEASE_LOCK('" . escape($lock) . "')");

    if ($done && $approve) {
        pg_volunteer_announce('shift.signed_up', (int) $taker, 'swap');
    }

    return $done;
}
