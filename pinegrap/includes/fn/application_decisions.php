<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// An application programme's decisions: accepted, rejected or waitlisted,
// one by one or for the ticked rows; a declined acceptance and the call of
// the next on the waitlist; the decision e-mails from the programme's texts
// with the person's ^^fields^^, previewed with their counts and sent only on
// a press; and the result shown to the submitter once mailed. Loaded with
// require_once by the programme's screen and the form item view.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_ranking.php');

/**
 * The stored decision of a word: accepted 1, rejected 2, waitlisted 3,
 * declined 4; anything else 0 (none).
 *
 * @param mixed $word
 * @return int
 */
function pg_application_decision_code($word)
{
    $codes = array('accepted' => 1, 'rejected' => 2, 'waitlisted' => 3, 'declined' => 4);

    return (is_string($word) && isset($codes[$word])) ? $codes[$word] : 0;
}

/**
 * The word of a stored decision; '' for none or an unknown one.
 *
 * @param mixed $code
 * @return string
 */
function pg_application_decision_key($code)
{
    $keys = array(1 => 'accepted', 2 => 'rejected', 3 => 'waitlisted', 4 => 'declined');

    return $keys[(int) $code] ?? '';
}

/**
 * Whether an entry's decision may change from one to another: to none or
 * one of the three from anything but declined; declined only after an
 * acceptance. A declined place is filled from the waitlist, never handed
 * back by a change of decision.
 *
 * @param int $from
 * @param int $to
 * @return bool
 */
function pg_application_decision_allowed($from, $to)
{
    $from = (int) $from;
    $to = (int) $to;

    if (($from === $to) || ($to < 0) || ($to > 4) || ($from === 4)) {
        return false;
    }

    return ($to !== 4) || ($from === 1);
}

/**
 * The waitlisted entry called when a place in a category is declined: of the
 * category's waitlisted entries, the one of the latest round (it got
 * furthest), then the highest score by the score that ranks, then the
 * earliest entry.
 *
 * @param array  $entries  entry id => array('category', 'decision', 'round',
 *                         'score_raw', 'score_adjusted') (scores times 100)
 * @param string $category
 * @param string $rank_by  'raw' or 'corrected'
 * @return int the entry id; 0 when nobody waits
 */
function pg_application_waitlist_next(array $entries, $category, $rank_by)
{
    $column = ($rank_by === 'corrected') ? 'score_adjusted' : 'score_raw';
    $best = 0;
    $best_key = null;

    foreach ($entries as $id => $entry) {
        if (((int) $entry['decision'] !== 3) || ((string) $entry['category'] !== (string) $category)) {
            continue;
        }

        $key = array((int) $entry['round'], (int) $entry[$column], -(int) $id);

        if (($best_key === null) || ($key > $best_key)) {
            $best = (int) $id;
            $best_key = $key;
        }
    }

    return $best;
}

/**
 * The decision e-mails waiting to go, per decision: how many can be sent,
 * how many have no e-mail address, and whether the programme's text for it
 * is written (a subject and a text).
 *
 * @param array $rows  each array('decision', 'mailed_at', 'email')
 * @param array $texts the programme's texts
 * @return array 'accepted'|'rejected'|'waitlisted' => array('send',
 *               'no_address', 'written')
 */
function pg_application_mail_counts(array $rows, array $texts)
{
    $counts = array();

    foreach (array('accepted', 'rejected', 'waitlisted') as $key) {
        $text = $texts[$key] ?? array();
        $counts[$key] = array('send' => 0, 'no_address' => 0, 'written' => (($text['subject'] ?? '') !== '') && (($text['body'] ?? '') !== ''));
    }

    foreach ($rows as $row) {
        $key = pg_application_decision_key($row['decision']);

        if (!isset($counts[$key]) || ((int) $row['mailed_at'] > 0)) {
            continue;
        }

        $counts[$key][((string) $row['email'] !== '') ? 'send' : 'no_address']++;
    }

    return $counts;
}

/**
 * How many decision e-mails a press would send: those with an address of the
 * decisions whose text is written.
 *
 * @param array $counts pg_application_mail_counts()
 * @return int
 */
function pg_application_mail_sendable(array $counts)
{
    $total = 0;

    foreach ($counts as $count) {
        $total += $count['written'] ? $count['send'] : 0;
    }

    return $total;
}

/**
 * Whether the submitter sees an entry's result: a decision that was mailed.
 *
 * @param int $decision
 * @param int $mailed_at
 * @return bool
 */
function pg_application_result_visible($decision, $mailed_at)
{
    return ((int) $decision >= 1) && ((int) $decision <= 4) && ((int) $mailed_at > 0);
}

/**
 * The posted entry ids that belong to the list on the screen, each once.
 *
 * @param mixed $posted
 * @param array $allowed entry id => anything
 * @return array of int
 */
function pg_application_posted_ids($posted, array $allowed)
{
    $ids = array();

    foreach (is_array($posted) ? $posted : array() as $value) {
        if ((is_string($value) || is_int($value)) && preg_match('/^[0-9]{1,10}$/', (string) $value) && isset($allowed[(int) $value])) {
            $ids[(int) $value] = (int) $value;
        }
    }

    return array_values($ids);
}

/**
 * The entries the Decisions tab lists, best first: the current round's
 * shortlist, the current round, or every round; each with its reference
 * code, the score that ranks (times 100) and its decision.
 *
 * @param array  $program decoded
 * @param string $filter  'shortlist', 'round' or 'all'
 * @return array entry id => row
 */
function pg_application_decision_rows(array $program, $filter)
{
    $round = max(1, (int) $program['round']);
    $rank_by = pg_application_round_settings($program['rounds'][$round - 1] ?? array())['rank_by'];
    $score = ($rank_by === 'corrected') ? 'application_entries.score_adjusted' : 'application_entries.score_raw';
    $where = 'application_entries.program_id = ?';
    $params = array((int) $program['id']);

    if ($filter !== 'all') {
        $where .= ' AND application_entries.round = ?';
        $params[] = $round;
    }

    if ($filter === 'shortlist') {
        // A decided entry stays in sight once the shortlist is cut again.
        $where .= ' AND (application_entries.shortlisted = 1 OR application_entries.decision > 0)';
    }

    $rows = array();

    foreach (_pg_application_rows("SELECT application_entries.id, application_entries.form_id, application_entries.category, application_entries.round,
            application_entries.eligible, application_entries.shortlisted, application_entries.decision, application_entries.decided_at, application_entries.mailed_at,
            application_entries.score_raw, application_entries.score_adjusted, " . $score . " AS score, forms.reference_code
        FROM application_entries LEFT JOIN forms ON forms.id = application_entries.form_id
        WHERE " . $where . "
        ORDER BY application_entries.round DESC, " . $score . " DESC, application_entries.id", $params) as $row) {
        foreach (array('id', 'form_id', 'round', 'eligible', 'shortlisted', 'decision', 'decided_at', 'mailed_at', 'score_raw', 'score_adjusted', 'score') as $column) {
            $row[$column] = (int) $row[$column];
        }

        $rows[$row['id']] = $row;
    }

    return $rows;
}

/**
 * Records a decision on entries of the programme, each where the change is
 * allowed (pg_application_decision_allowed()). A new decision waits for its
 * e-mail (mailed_at 0); declined keeps the mailed acceptance and, when the
 * programme calls the waitlist by itself, calls the next. Each change is an
 * audit_log line on the entry and is announced as application.decided.
 *
 * @param array $program  decoded
 * @param array $entry_ids
 * @param int   $decision 0 to 4
 * @param int   $user_id
 * @return array array('changed' => int, 'called' => list of entry ids)
 */
function pg_application_decide(array $program, array $entry_ids, $decision, $user_id)
{
    $decision = (int) $decision;
    $result = array('changed' => 0, 'called' => array());

    foreach ($entry_ids as $entry_id) {
        $rows = _pg_application_rows("SELECT id, decision FROM application_entries WHERE id = ? AND program_id = ?", array((int) $entry_id, (int) $program['id']));

        if (!$rows || !pg_application_decision_allowed((int) $rows[0]['decision'], $decision)) {
            continue;
        }

        $from = (int) $rows[0]['decision'];
        $mailed = ($decision === 4) ? 'mailed_at' : '0';
        $ok = db_q("UPDATE application_entries SET decision = ?, decided_at = UNIX_TIMESTAMP(), decided_by = ?, mailed_at = " . $mailed . ", updated_at = UNIX_TIMESTAMP()
            WHERE id = ? AND decision = ?", array($decision, (int) $user_id, (int) $entry_id, $from));

        if (!$ok || (mysqli_affected_rows(db::$con) < 1)) {
            continue;
        }

        $result['changed']++;
        pg_audit('decision', 'application_entry', (int) $entry_id, array('decision' => $from), array('decision' => $decision));
        $row = pg_application_event_row((int) $entry_id);

        if ($row !== null) {
            pg_announce('application.decided', pg_application_event_payload($row, $program) + array('previous_decision' => (pg_application_decision_key($from) !== '') ? pg_application_decision_key($from) : 'none'));
        }

        if (($decision === 4) && ((int) ($program['waitlist_auto'] ?? 0) === 1)) {
            $called = pg_application_waitlist_call($program, (int) $entry_id, $user_id);

            if ($called > 0) {
                $result['called'][] = $called;
            }
        }
    }

    return $result;
}

/**
 * Whether a declined entry's place was already given to the next on the
 * waitlist: its audit_log line waitlist_call.
 *
 * @param int $entry_id
 * @return bool
 */
function pg_application_waitlist_called($entry_id)
{
    return (int) db_value("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'application_entry' AND entity_id = '" . (int) $entry_id . "' AND action = 'waitlist_call'") > 0;
}

/**
 * The name of the lock a declined entry's waitlist call runs under. GET_LOCK
 * names are server-wide, so the database name keeps two sites on one server
 * apart; at most 64 characters.
 *
 * @param int $declined_id
 * @return string
 */
function pg_application_waitlist_lock_name($declined_id)
{
    $database = defined('DB_DATABASE') ? (string) DB_DATABASE : '';

    return 'pg_app_waitlist_' . substr(md5($database), 0, 8) . '_' . (int) $declined_id;
}

/**
 * Gives a declined entry's place to the next on the waitlist of its
 * category (pg_application_waitlist_next()): accepted, its e-mail waiting
 * for the press. Once per declined entry; an audit_log line on both, and
 * application.decided for the called one.
 *
 * Two presses at the same moment (two managers, two tabs, or the automatic
 * call) would both pass the waitlist_call check, which is written only after
 * the accept, and accept two entries for one place: the check, the pick and
 * the accept run under the declined entry's lock.
 *
 * @param array $program  decoded
 * @param int   $declined_id
 * @param int   $user_id
 * @return int the called entry's id; 0 when nobody was called
 */
function pg_application_waitlist_call(array $program, $declined_id, $user_id)
{
    $lock = pg_application_waitlist_lock_name($declined_id);

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 10)") !== 1) {
        return 0;
    }

    $called = pg_application_waitlist_call_locked($program, $declined_id, $user_id);
    db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

    return $called;
}

/**
 * pg_application_waitlist_call()'s work, run while its lock is held.
 *
 * @param array $program  decoded
 * @param int   $declined_id
 * @param int   $user_id
 * @return int the called entry's id; 0 when nobody was called
 */
function pg_application_waitlist_call_locked(array $program, $declined_id, $user_id)
{
    $declined = _pg_application_rows("SELECT id, category FROM application_entries WHERE id = ? AND program_id = ? AND decision = 4", array((int) $declined_id, (int) $program['id']));

    if (!$declined || pg_application_waitlist_called($declined_id)) {
        return 0;
    }

    $round = max(1, (int) $program['round']);
    $rank_by = pg_application_round_settings($program['rounds'][$round - 1] ?? array())['rank_by'];
    $waiting = array();

    foreach (_pg_application_rows("SELECT id, category, decision, round, score_raw, score_adjusted FROM application_entries WHERE program_id = ? AND decision = 3 AND category = ?", array((int) $program['id'], (string) $declined[0]['category'])) as $row) {
        $waiting[(int) $row['id']] = $row;
    }

    $next = pg_application_waitlist_next($waiting, (string) $declined[0]['category'], $rank_by);

    if ($next === 0) {
        return 0;
    }

    $ok = db_q("UPDATE application_entries SET decision = 1, decided_at = UNIX_TIMESTAMP(), decided_by = ?, mailed_at = 0, updated_at = UNIX_TIMESTAMP() WHERE id = ? AND decision = 3", array((int) $user_id, $next));

    if (!$ok || (mysqli_affected_rows(db::$con) < 1)) {
        return 0;
    }

    pg_audit('waitlist_call', 'application_entry', (int) $declined_id, array(), array('called' => $next));
    pg_audit('decision', 'application_entry', $next, array('decision' => 3), array('decision' => 1, 'called_for' => (int) $declined_id));
    $row = pg_application_event_row($next);

    if ($row !== null) {
        pg_announce('application.decided', pg_application_event_payload($row, $program) + array('previous_decision' => 'waitlisted'));
    }

    return $next;
}

/**
 * The entries whose decision e-mail waits, with the address it goes to.
 *
 * @param array $program decoded
 * @return array list of array('id', 'form_id', 'decision', 'mailed_at', 'email')
 */
function pg_application_mail_queue(array $program)
{
    $rows = array();

    foreach (_pg_application_rows("SELECT id, form_id, decision, mailed_at FROM application_entries WHERE program_id = ? AND decision IN (1, 2, 3) AND mailed_at = 0 ORDER BY id", array((int) $program['id'])) as $row) {
        $email = trim((string) get_submitter_email_address((int) $row['form_id']));
        $row['email'] = validate_email_address($email) ? $email : '';
        $rows[] = $row;
    }

    return $rows;
}

/**
 * One decision e-mail: the programme's subject and text for the decision
 * with the entry's ^^fields^^ filled in, as plain text.
 *
 * @param array  $program decoded
 * @param string $key     'accepted', 'rejected' or 'waitlisted'
 * @param int    $form_id
 * @return array array('subject', 'body')
 */
function pg_application_mail_render(array $program, $key, $form_id)
{
    $text = $program['texts'][$key] ?? array();

    return array(
        'subject' => trim(get_variable_submitted_form_data_for_content((int) $program['page_id'], (int) $form_id, (string) ($text['subject'] ?? ''), false)),
        'body' => get_variable_submitted_form_data_for_content((int) $program['page_id'], (int) $form_id, (string) ($text['body'] ?? ''), false),
    );
}

/**
 * Sends the waiting decision e-mails of the decisions whose text is written,
 * each claimed by a conditional UPDATE of mailed_at first, so two presses
 * never send one twice. Each mail is an audit_log line on the entry.
 *
 * @param array $program decoded
 * @return int e-mails sent
 */
function pg_application_mail_send(array $program)
{
    $queue = pg_application_mail_queue($program);
    $counts = pg_application_mail_counts($queue, $program['texts']);
    $sent = 0;

    foreach ($queue as $row) {
        $key = pg_application_decision_key($row['decision']);

        if (($row['email'] === '') || !$counts[$key]['written']) {
            continue;
        }

        $claim = db_q("UPDATE application_entries SET mailed_at = UNIX_TIMESTAMP() WHERE id = ? AND decision = ? AND mailed_at = 0", array((int) $row['id'], (int) $row['decision']));

        if (!$claim || (mysqli_affected_rows(db::$con) < 1)) {
            continue;
        }

        $mail = pg_application_mail_render($program, $key, (int) $row['form_id']);

        email(array(
            'to'                 => $row['email'],
            'from_name'          => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'subject'            => $mail['subject'],
            'format'             => 'plain_text',
            'body'               => $mail['body'],
            'queue'              => true,
        ));
        pg_audit('mail', 'application_entry', (int) $row['id'], array(), array('decision' => (int) $row['decision']));
        $sent++;
    }

    return $sent;
}

/**
 * The result block of a submitted form for its submitter on the form item
 * view: the programme's name and the mailed decision. '' for anyone else,
 * for a form in no programme and before the decision e-mail went.
 *
 * @param array $submitted_form the item view's row (id, submitter_id,
 *                              reference_code)
 * @return string HTML
 */
function pg_application_result_html(array $submitted_form)
{
    if (!pg_application_program_ready()) {
        return '';
    }

    $rows = _pg_application_rows("SELECT application_entries.decision, application_entries.mailed_at, application_programs.name
        FROM application_entries INNER JOIN application_programs ON application_programs.id = application_entries.program_id
        WHERE application_entries.form_id = ?", array((int) $submitted_form['id']));

    if (!$rows || !pg_application_result_visible($rows[0]['decision'], $rows[0]['mailed_at'])) {
        return '';
    }

    // Only the submitter: by this browser session, the account that sent it,
    // or the account whose address is the form's contact e-mail.
    $codes = $_SESSION['software']['submitted_form_reference_codes'] ?? array();
    $submitter = (is_array($codes) && in_array($submitted_form['reference_code'], $codes, true))
        || (USER_LOGGED_IN && ((int) USER_ID > 0) && ((int) USER_ID === (int) $submitted_form['submitter_id']))
        || (USER_LOGGED_IN && pg_email_address_matches(USER_EMAIL_ADDRESS, (string) get_submitter_email_address((int) $submitted_form['id'])));

    if (!$submitter) {
        return '';
    }

    $words = array(1 => lang('Accepted'), 2 => lang('Not accepted'), 3 => lang('On the waitlist'), 4 => lang('Place declined'));

    return '<div class="software_application_result"><p><strong>' . h($rows[0]['name']) . '</strong>: ' . h($words[(int) $rows[0]['decision']]) . '</p></div>';
}
