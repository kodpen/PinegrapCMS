<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A reviewer's own work on application programmes: the programmes and the
// queue of "My reviews", the entry as a reviewer may see it (blind review),
// saving and sending a review, "I know this applicant" passing the entry on,
// and the reminders before a round's end. Every read is of the signed-in
// reviewer's own reviews. Loaded with require_once by my_reviews.php and the
// general job.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_reviewers.php');

/**
 * Whether blind review hides a form field from reviewers: the contact fields
 * and e-mail fields always, then the fields the programme names. With blind
 * review off nothing is hidden.
 *
 * @param array $field        array('name', 'type', 'contact_field')
 * @param bool  $blind
 * @param array $blind_fields the programme's field names
 * @return bool
 */
function pg_application_blind_hides(array $field, $blind, array $blind_fields)
{
    if (!$blind) {
        return false;
    }

    return ((string) ($field['contact_field'] ?? '') !== '')
        || ((string) ($field['type'] ?? '') === 'email address')
        || in_array((string) ($field['name'] ?? ''), array_map('strval', $blind_fields), true);
}

/**
 * A review's scores as posted, kept as stored: criterion id => array('score'
 * => whole 1 to 10 or null, 'note'). Unknown criteria and scores out of
 * range are dropped.
 *
 * @param array $criteria the round's criteria, list of array('id', ...)
 * @param mixed $scores   posted criterion id => score
 * @param mixed $notes    posted criterion id => note
 * @return array
 */
function pg_application_review_scores_input(array $criteria, $scores, $notes)
{
    $scores = is_array($scores) ? $scores : array();
    $notes = is_array($notes) ? $notes : array();
    $kept = array();

    foreach ($criteria as $criterion) {
        $id = (string) $criterion['id'];
        $score = (isset($scores[$id]) && is_string($scores[$id]) && preg_match('/^(10|[1-9])$/', $scores[$id])) ? (int) $scores[$id] : null;
        $note = (isset($notes[$id]) && is_string($notes[$id])) ? mb_substr(trim($notes[$id]), 0, 1000) : '';
        $kept[$id] = array('score' => $score, 'note' => $note);
    }

    return $kept;
}

/**
 * The stored scores JSON read back: criterion id => array('score', 'note').
 *
 * @param mixed $json application_reviews.scores
 * @return array
 */
function pg_application_review_scores_stored($json)
{
    $value = is_string($json) ? json_decode($json, true) : null;
    $scores = array();

    foreach (is_array($value) ? $value : array() as $id => $criterion) {
        if (is_array($criterion)) {
            $score = $criterion['score'] ?? null;
            $scores[(string) $id] = array('score' => is_int($score) ? $score : null, 'note' => (string) ($criterion['note'] ?? ''));
        }
    }

    return $scores;
}

/**
 * Criterion id => whole score, the shape pg_application_weighted_sum() reads.
 *
 * @param array $scores pg_application_review_scores_input()
 * @return array
 */
function pg_application_review_score_map(array $scores)
{
    $map = array();

    foreach ($scores as $id => $criterion) {
        if ($criterion['score'] !== null) {
            $map[(string) $id] = (int) $criterion['score'];
        }
    }

    return $map;
}

/**
 * Whole days left until a round's end, counted up (half a day left is 1);
 * 0 once it has passed, null when the round has no end.
 *
 * @param int $ends_at
 * @param int $now
 * @return int|null
 */
function pg_application_days_left($ends_at, $now)
{
    if ((int) $ends_at <= 0) {
        return null;
    }

    return max(0, (int) ceil(((int) $ends_at - (int) $now) / 86400));
}

/**
 * The reminder a reviewer is owed now: 3 within three days of the round's
 * end, 1 within the last day, each once; 0 for none. A reminder sent at or
 * after a stage's start counts for it, so the last day's comes even when the
 * three days' was missed, and never twice.
 *
 * @param int $ends_at       the round's end
 * @param int $reminded_at   the latest reminder sent on the reviewer's open
 *                           reviews, 0 for none
 * @param int $now
 * @return int 3, 1 or 0
 */
function pg_application_reminder_stage($ends_at, $reminded_at, $now)
{
    $ends_at = (int) $ends_at;
    $now = (int) $now;

    if (($ends_at <= 0) || ($now >= $ends_at)) {
        return 0;
    }

    foreach (array(1, 3) as $stage) {
        $start = $ends_at - $stage * 86400;

        if ($now >= $start) {
            return ((int) $reminded_at < $start) ? $stage : 0;
        }
    }

    return 0;
}

/**
 * The reviews before and after one in a reviewer's queue.
 *
 * @param array $ids     the queue's review ids in order
 * @param int   $current
 * @return array array(previous id, next id), 0 where there is none
 */
function pg_application_queue_neighbours(array $ids, $current)
{
    $ids = array_values(array_map('intval', $ids));
    $at = array_search((int) $current, $ids, true);

    if ($at === false) {
        return array(0, 0);
    }

    return array($ids[$at - 1] ?? 0, $ids[$at + 1] ?? 0);
}

/**
 * The programmes a panel user reviews in, open or closed (never a draft),
 * each with their reviewer row, the current round's end and their assigned
 * and sent reviews of it.
 *
 * @param int $user_id
 * @return array list of decoded programmes with 'reviewer_id', 'ends_at',
 *               'assigned', 'done'
 */
function pg_application_my_programs($user_id)
{
    if (!pg_application_program_ready() || ((int) $user_id <= 0)) {
        return array();
    }

    $programs = array();
    $rows = _pg_application_rows("SELECT application_programs.*, application_reviewers.id AS reviewer_id,
            (SELECT COUNT(*) FROM application_reviews WHERE application_reviews.reviewer_id = application_reviewers.id AND application_reviews.round = application_programs.round AND application_reviews.status IN (0, 1, 2)) AS assigned,
            (SELECT COUNT(*) FROM application_reviews WHERE application_reviews.reviewer_id = application_reviewers.id AND application_reviews.round = application_programs.round AND application_reviews.status = 2) AS done
        FROM application_reviewers
        INNER JOIN application_programs ON application_programs.id = application_reviewers.program_id
        WHERE application_reviewers.user_id = ? AND application_reviewers.revoked_at = 0 AND application_programs.status IN (1, 2)
        ORDER BY application_programs.id DESC", array((int) $user_id));

    foreach ($rows as $row) {
        $program = pg_application_program_decode($row);
        $program['reviewer_id'] = (int) $row['reviewer_id'];
        $program['assigned'] = (int) $row['assigned'];
        $program['done'] = (int) $row['done'];
        $program['ends_at'] = (int) ($program['rounds'][max(1, $program['round']) - 1]['ends_at'] ?? 0);
        $programs[] = $program;
    }

    return $programs;
}

/**
 * A reviewer's queue in the programme's current round: their reviews to do,
 * started and sent, in entry order; conflicts are not in it.
 *
 * @param int $reviewer_id
 * @param int $round
 * @return array list of rows with id, status, entry_id, category, form_id,
 *               reference_code
 */
function pg_application_my_queue($reviewer_id, $round)
{
    return _pg_application_rows("SELECT application_reviews.id, application_reviews.status, application_reviews.entry_id, application_entries.category, application_entries.form_id, forms.reference_code
        FROM application_reviews
        INNER JOIN application_entries ON application_entries.id = application_reviews.entry_id
        LEFT JOIN forms ON forms.id = application_entries.form_id
        WHERE application_reviews.reviewer_id = ? AND application_reviews.round = ? AND application_reviews.status IN (0, 1, 2)
        ORDER BY application_reviews.entry_id", array((int) $reviewer_id, max(1, (int) $round)));
}

/**
 * One review of the signed-in panel user, with its entry and programme; null
 * when it is not theirs (or they were removed), a conflict, or its programme
 * is a draft. This is the check on every read of the scoring screen.
 *
 * @param int $review_id
 * @param int $user_id
 * @return array|null array('review', 'entry', 'program', 'criteria',
 *                    'scores', 'open' => it can still be changed)
 */
function pg_application_my_review($review_id, $user_id)
{
    $rows = _pg_application_rows("SELECT application_reviews.* FROM application_reviews
        INNER JOIN application_reviewers ON application_reviewers.id = application_reviews.reviewer_id
        WHERE application_reviews.id = ? AND application_reviewers.user_id = ? AND application_reviewers.revoked_at = 0 AND application_reviews.status IN (0, 1, 2)", array((int) $review_id, (int) $user_id));

    if (!$rows || ((int) $user_id <= 0)) {
        return null;
    }

    $review = $rows[0];
    $entries = _pg_application_rows("SELECT application_entries.*, forms.reference_code FROM application_entries LEFT JOIN forms ON forms.id = application_entries.form_id WHERE application_entries.id = ?", array((int) $review['entry_id']));
    $program = pg_application_program_get($review['program_id']);

    if (!$entries || ($program === null) || ($program['status'] === 0)) {
        return null;
    }

    return array(
        'review'   => $review,
        'entry'    => $entries[0],
        'program'  => $program,
        'criteria' => $program['rounds'][max(1, (int) $review['round']) - 1]['criteria'] ?? array(),
        'scores'   => pg_application_review_scores_stored($review['scores']),
        'open'     => ((int) $review['status'] < 2) && ((int) $review['round'] === max(1, $program['round'])),
    );
}

/**
 * What a reviewer sees of an entry, field by field in the form's order: the
 * label and the answer as HTML, or null where blind review hides it. Fields
 * for office use only are not the applicant's and are left out; an upload
 * is a link to its file, whatever blind review says.
 *
 * @param array $program
 * @param int   $form_id
 * @return array list of array('label', 'html' => string|null)
 */
function pg_application_entry_view(array $program, $form_id)
{
    $fields = array();
    $rows = _pg_application_rows("SELECT form_fields.id, form_fields.name, form_fields.label, form_fields.type, form_fields.contact_field, form_fields.wysiwyg,
            form_data.data, form_data.file_id, files.name AS file_name
        FROM form_fields
        LEFT JOIN form_data ON form_data.form_field_id = form_fields.id AND form_data.form_id = ?
        LEFT JOIN files ON files.id = form_data.file_id AND form_data.file_id > 0
        WHERE form_fields.page_id = ? AND form_fields.type != 'information' AND form_fields.office_use_only = 0
        ORDER BY form_fields.sort_order, form_data.id", array((int) $form_id, (int) $program['page_id']));

    foreach ($rows as $row) {
        $id = (int) $row['id'];

        if (!isset($fields[$id])) {
            $fields[$id] = array(
                'label' => ((string) $row['label'] !== '') ? (string) $row['label'] : (string) $row['name'],
                'hidden' => pg_application_blind_hides($row, (bool) $program['blind'], $program['blind_fields']),
                'parts' => array(),
            );
        }

        if ((string) ($row['file_name'] ?? '') !== '') {
            $fields[$id]['parts'][] = '<a href="' . h(OUTPUT_PATH . encode_url_path($row['file_name'])) . '" target="_blank" rel="noopener"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>' . h($row['file_name']) . '</a>';
        } elseif (trim((string) $row['data']) !== '') {
            $type = in_array($row['type'], array('date', 'date and time', 'time'), true) ? $row['type'] : 'text';
            $data = $row['wysiwyg'] ? trim(html_entity_decode(strip_tags((string) $row['data']), ENT_QUOTES, 'UTF-8')) : (string) $row['data'];
            $fields[$id]['parts'][] = prepare_form_data_for_output($data, $type);
        }
    }

    $view = array();

    foreach ($fields as $field) {
        $view[] = array('label' => $field['label'], 'html' => $field['hidden'] ? null : implode('<br>', $field['parts']));
    }

    return $view;
}

/**
 * Saves a reviewer's scores as typed; with $send, sends the review: every
 * criterion must be scored, the weighted total is kept, and it can no
 * longer be changed. Sending is an audit_log line on the entry.
 *
 * @param array $context pg_application_my_review(), open
 * @param array $scores  pg_application_review_scores_input()
 * @param bool  $send
 * @return bool false when sending lacks a score or the write failed
 */
function pg_application_review_save(array $context, array $scores, $send)
{
    $review = $context['review'];
    $total = pg_application_weighted_sum($context['criteria'], pg_application_review_score_map($scores));

    if ($send && ($total === null)) {
        return false;
    }

    $json = json_encode($scores, JSON_UNESCAPED_UNICODE);

    if (!$send) {
        return (bool) db_q("UPDATE application_reviews SET scores = ?, status = 1 WHERE id = ? AND status IN (0, 1)", array($json, (int) $review['id']));
    }

    $ok = db_q("UPDATE application_reviews SET scores = ?, total = ?, status = 2, submitted_at = UNIX_TIMESTAMP() WHERE id = ? AND status IN (0, 1)", array($json, (int) round($total * 100), (int) $review['id']));

    if (!$ok || (mysqli_affected_rows(db::$con) < 1)) {
        return false;
    }

    pg_audit('score', 'application_entry', (int) $review['entry_id'], array(), array('review_id' => (int) $review['id'], 'round' => (int) $review['round'], 'total' => $total));

    return true;
}

/**
 * "I know this applicant": the review becomes a conflict with its reason,
 * kept for the programme's managers, and the entry goes to another reviewer
 * of its category's pool who has not got it, the fewest given out first.
 * Only this entry is given out again; nothing else of the round changes.
 *
 * @param array  $context pg_application_my_review(), open
 * @param string $reason
 * @return array array('ok' => bool, 'moved' => bool: someone else got it)
 */
function pg_application_review_conflict(array $context, $reason)
{
    $review = $context['review'];
    $reason = mb_substr(trim((string) $reason), 0, 500);

    if ($reason === '') {
        return array('ok' => false, 'moved' => false);
    }

    $ok = db_q("UPDATE application_reviews SET status = 3, conflict_reason = ?, scores = NULL, total = 0 WHERE id = ? AND status IN (0, 1)", array($reason, (int) $review['id']));

    if (!$ok || (mysqli_affected_rows(db::$con) < 1)) {
        return array('ok' => false, 'moved' => false);
    }

    pg_audit('conflict', 'application_entry', (int) $review['entry_id'], array(), array('review_id' => (int) $review['id'], 'round' => (int) $review['round'], 'reason' => $reason));

    $moved = false;

    foreach (pg_application_assignment_read($context['program'], true)['inserts'] as $insert) {
        if ($insert[0] === (int) $review['entry_id']) {
            $moved = (bool) db_q("INSERT IGNORE INTO application_reviews (program_id, entry_id, reviewer_id, round, status, assigned_at) VALUES (?, ?, ?, ?, 0, UNIX_TIMESTAMP())", array((int) $review['program_id'], $insert[0], $insert[1], (int) $review['round'])) || $moved;
        }
    }

    return array('ok' => true, 'moved' => $moved);
}

/**
 * The general job's pass: three days and one day before a round's end, each
 * panel user with open reviews in it gets one e-mail with how many are left
 * and the link to "My reviews". Each reviewer's open reviews are claimed by
 * one conditional UPDATE of reminded_at before the mail, so two runs at once
 * never both send. Outside jurors are reminded with their own link (AR4).
 *
 * @param int|null $now
 * @return int e-mails sent
 */
function pg_application_reminders_run($now = null)
{
    if (!pg_application_program_ready()) {
        return 0;
    }

    $now = ($now === null) ? time() : (int) $now;
    $sent = 0;

    foreach (_pg_application_rows("SELECT * FROM application_programs WHERE status IN (1, 2)") as $row) {
        $program = pg_application_program_decode($row);
        $round = max(1, $program['round']);
        $ends_at = (int) ($program['rounds'][$round - 1]['ends_at'] ?? 0);

        if (pg_application_reminder_stage($ends_at, 0, $now) === 0) {
            continue;
        }

        $reviewers = _pg_application_rows("SELECT application_reviews.reviewer_id, COUNT(*) AS open_reviews, MAX(application_reviews.reminded_at) AS reminded_at, user.user_email
            FROM application_reviews
            INNER JOIN application_reviewers ON application_reviewers.id = application_reviews.reviewer_id
            INNER JOIN user ON user.user_id = application_reviewers.user_id
            WHERE application_reviews.program_id = ? AND application_reviews.round = ? AND application_reviews.status IN (0, 1)
                AND application_reviewers.user_id > 0 AND application_reviewers.revoked_at = 0
            GROUP BY application_reviews.reviewer_id, user.user_email", array($program['id'], $round));

        foreach ($reviewers as $reviewer) {
            $stage = pg_application_reminder_stage($ends_at, (int) $reviewer['reminded_at'], $now);

            if (($stage === 0) || !validate_email_address((string) $reviewer['user_email'])) {
                continue;
            }

            $claim = db_q("UPDATE application_reviews SET reminded_at = ? WHERE program_id = ? AND reviewer_id = ? AND round = ? AND status IN (0, 1) AND reminded_at < ?", array($now, $program['id'], (int) $reviewer['reviewer_id'], $round, $ends_at - $stage * 86400));

            if (!$claim || (mysqli_affected_rows(db::$con) < 1)) {
                continue;
            }

            $subject = lang(array('string' => '{var:1}: the round ends in {var:2} days', 'vars' => array($program['name'], $stage)));
            $body = lang(array('string' => 'You have {var:1} entries left to review in "{var:2}". The round ends on {var:3}.', 'vars' => array((int) $reviewer['open_reviews'], $program['name'], get_absolute_time(array('timestamp' => $ends_at, 'format' => 'plain_text')))));

            email(array(
                'to'                 => (string) $reviewer['user_email'],
                'from_email_address' => EMAIL_ADDRESS,
                'subject'            => $subject,
                'format'             => 'plain_text',
                'body'               => $body . "\n\n" . URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/my_reviews.php',
                'type'               => 'system',
                'queue'              => true,
            ));
            $sent++;
        }
    }

    return $sent;
}
