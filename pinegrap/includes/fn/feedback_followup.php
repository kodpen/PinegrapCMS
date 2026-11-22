<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What follows a satisfaction survey's answer. Every answer the answer page
 * writes is announced as feedback.received. A low score (NPS 0-6, CSAT 1-2,
 * effort 6-7) gets, once per answer, a follow-up task for its owner (the
 * booking's staff user, the form's Form Editor, the conversation's
 * assignee, else the survey's fallback user), a line in the survey's
 * Workspace channel and the feedback.detractor event. The general job adds
 * the survey's escalation user to a follow-up still open after 48 hours;
 * closing the task from its drawer asks how it ended (called back, solved,
 * not reached), kept on the answer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Hours a follow-up stays open before the escalation user joins it. */
define('PG_FEEDBACK_ESCALATE_HOURS', 48);

/** Days after the answer a follow-up task is due. */
define('PG_FEEDBACK_FOLLOWUP_DAYS', 2);

/**
 * Days after the answer a follow-up is still looked at for its escalation:
 * the pass reads answers by the (survey_id, answered_at) key, never the
 * whole table.
 */
define('PG_FEEDBACK_ESCALATE_WINDOW_DAYS', 90);

/** Follow-ups escalated per pass. */
define('PG_FEEDBACK_ESCALATE_ROWS', 200);

/**
 * How a follow-up ended, as stored in feedback_responses.outcome (0 none).
 * English; callers translate.
 *
 * @return array outcome => label
 */
function pg_feedback_outcomes()
{
    return array(1 => 'Called back', 2 => 'Solved', 3 => 'Not reached');
}

/**
 * An outcome given as a request value, 0 when it is not one. Pure.
 *
 * @param mixed $value
 * @return int
 */
function pg_feedback_outcome_valid($value)
{
    if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]$/', (string) $value)) {
        return 0;
    }

    return isset(pg_feedback_outcomes()[(int) $value]) ? (int) $value : 0;
}

/**
 * The feedback.received payload of an answered row. Pure.
 *
 * @param array $row    a feedback_responses row, answered
 * @param array $survey id, name, kind, touchpoint
 * @param bool  $first  the person's first answer, not a change or the
 *                      comment added to the score
 * @return array
 */
function pg_feedback_event_payload(array $row, array $survey, $first)
{
    $kinds = array(PG_FEEDBACK_NPS => 'nps', PG_FEEDBACK_CSAT => 'csat', PG_FEEDBACK_EFFORT => 'effort');
    $bands = array(PG_FEEDBACK_BAND_LOW => 'low', PG_FEEDBACK_BAND_MIDDLE => 'middle', PG_FEEDBACK_BAND_HIGH => 'high');

    return array(
        'id'          => (int) $row['id'],
        'survey_id'   => (int) $survey['id'],
        'survey_name' => (string) $survey['name'],
        'kind'        => $kinds[(int) $survey['kind']] ?? '',
        'touchpoint'  => (string) $survey['touchpoint'],
        'record_type' => (string) $row['record_type'],
        'record_id'   => (int) $row['record_id'],
        'contact_id'  => ((int) $row['contact_id'] > 0) ? (int) $row['contact_id'] : null,
        'channel'     => (string) $row['channel'],
        'score'       => (int) $row['score'],
        'band'        => $bands[(int) $row['band']] ?? '',
        'comment'     => (string) ($row['comment'] ?? ''),
        'answered_at' => gmdate('Y-m-d\TH:i:s\Z', (int) $row['answered_at']),
        'first'       => (bool) $first,
    );
}

/**
 * The feedback.detractor payload: the answer as feedback.received gives it,
 * with the follow-up's owner and task (0 for none). Pure.
 *
 * @param array $received pg_feedback_event_payload()
 * @param int   $owner_id
 * @param int   $task_id
 * @return array
 */
function pg_feedback_detractor_payload(array $received, $owner_id, $task_id)
{
    unset($received['first']);

    return $received + array('owner_id' => (int) $owner_id, 'task_id' => (int) $task_id);
}

/**
 * Whether a write starts the follow-up: the score is low now, it was not
 * low before (a first answer, or a change from a higher score), and the
 * answer has no follow-up yet. Pure.
 *
 * @param array $before the row before the write: status, band, owner_user_id, task_id
 * @param int   $band   the band written
 * @return bool
 */
function pg_feedback_followup_due(array $before, $band)
{
    if (((int) $band !== PG_FEEDBACK_BAND_LOW) || ((int) $before['owner_user_id'] > 0) || ((int) $before['task_id'] > 0)) {
        return false;
    }

    return ((int) $before['status'] !== PG_FEEDBACK_ANSWERED) || ((int) $before['band'] !== PG_FEEDBACK_BAND_LOW);
}

/**
 * The Workspace records a follow-up is tagged with: the person's contact,
 * then the booking, form or order, or a ticket's calendar event. Pure.
 *
 * @param array $row contact_id, record_type, record_id, subject_id
 * @return array[] type, id
 */
function pg_feedback_followup_refs(array $row)
{
    $refs = array();

    if ((int) $row['contact_id'] > 0) {
        $refs[] = array('type' => 'contact', 'id' => (int) $row['contact_id']);
    }

    $kinds = array('booking' => array('booking', 'record_id'), 'form' => array('form', 'record_id'), 'order' => array('order', 'record_id'), 'ticket' => array('calendar_event', 'subject_id'));
    $kind = $kinds[(string) $row['record_type']] ?? null;

    if ($kind && ((int) $row[$kind[1]] > 0)) {
        $refs[] = array('type' => $kind[0], 'id' => (int) $row[$kind[1]]);
    }

    return $refs;
}

/**
 * Words a customer or the shop typed, made safe to stand in a Workspace
 * text: angle brackets turned so they never make a tag or a mention. Pure.
 *
 * @param mixed $text
 * @return string
 */
function pg_feedback_ws_words($text)
{
    return trim(str_replace(array('<', '>'), array('‹', '›'), (string) $text));
}

/**
 * The follow-up task of a low score, as ws_followup_task_open() takes it:
 * for its owner, on the survey's channel, tagged with the person and the
 * record, due in two days and reminded then, the comment quoted as data.
 * Null when there is no record to tag. Pure but for lang().
 *
 * @param array $row        the answered row
 * @param array $survey     name, kind
 * @param int   $owner_id
 * @param int   $channel_id the survey's Workspace channel, 0 for none
 * @param int   $now
 * @return array|null
 */
function pg_feedback_followup_input(array $row, array $survey, $owner_id, $channel_id, $now)
{
    $refs = pg_feedback_followup_refs($row);

    if (!$refs) {
        return null;
    }

    $range = pg_feedback_range($survey['kind']);
    $name = pg_feedback_ws_words($survey['name']);
    $description = lang(array('string' => 'The answer to the survey {var:1}: {var:2} of {var:3}.', 'vars' => array($name, (int) $row['score'], (int) ($range[1] ?? 0))));
    $comment = pg_feedback_ws_words($row['comment'] ?? '');

    if ($comment !== '') {
        $lines = array();

        foreach (preg_split('/\R/u', $comment) as $line) {
            $lines[] = rtrim('> ' . $line);
        }

        $description .= "\n" . implode("\n", $lines);
    }

    return array(
        'title'       => lang(array('string' => 'Low score in {var:1}: follow up', 'vars' => array($name))),
        'description' => $description,
        'refs'        => $refs,
        'channel_id'  => max(0, (int) $channel_id),
        'assignees'   => array((int) $owner_id),
        'priority'    => 'high',
        'due_date'    => date('Y-m-d', (int) $now + PG_FEEDBACK_FOLLOWUP_DAYS * 86400),
    );
}

/**
 * The line a low score writes into the survey's channel: the score, the
 * survey, the task and the contact as tags; never the comment, which the
 * task's readers see. Pure but for lang().
 *
 * @param array $row
 * @param array $survey
 * @param int   $task_id 0 for none
 * @return string
 */
function pg_feedback_followup_line(array $row, array $survey, $task_id)
{
    $range = pg_feedback_range($survey['kind']);
    $line = lang(array('string' => 'Low score in the survey {var:1}: {var:2} of {var:3}.', 'vars' => array(pg_feedback_ws_words($survey['name']), (int) $row['score'], (int) ($range[1] ?? 0))));

    if ((int) $task_id > 0) {
        $line .= ' <#task:' . (int) $task_id . '>';
    }

    if ((int) $row['contact_id'] > 0) {
        $line .= ' <#contact:' . (int) $row['contact_id'] . '>';
    }

    return $line;
}

/**
 * Whether a follow-up's escalation user joins it now: the task still open
 * 48 hours after it was opened, not escalated before, an escalation user
 * set. Pure.
 *
 * @param array  $row             task_id, escalated_at
 * @param string $task_status     ws_tasks.status
 * @param int    $task_created_at
 * @param int    $escalation_user_id
 * @param int    $now
 * @return bool
 */
function pg_feedback_escalation_due(array $row, $task_status, $task_created_at, $escalation_user_id, $now)
{
    return ((int) $row['task_id'] > 0) && ((int) $row['escalated_at'] === 0) && ((int) $escalation_user_id > 0)
        && in_array((string) $task_status, array('todo', 'doing', 'waiting'), true)
        && ((int) $task_created_at <= (int) $now - PG_FEEDBACK_ESCALATE_HOURS * 3600);
}

/**
 * Whether the Workspace is on and its follow-ups can be opened; loads it.
 *
 * @return bool
 */
function pg_feedback_workspace()
{
    if (!defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED || !is_file(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php')) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    return function_exists('ws_followup_task_open') && function_exists('ws_change_viewer_for_id') && pg_schema_has('ws_tasks');
}

/**
 * After the answer page wrote an answer: feedback.received, and for a
 * score that became low the follow-up. Quiet: a failure is logged and the
 * person's answer stands.
 *
 * @param array $before the row before the write
 * @param array $survey pg_feedback_answer_survey()
 * @param int   $now
 * @return void
 */
function pg_feedback_answered(array $before, array $survey, $now)
{
    try {
        $row = db_item("SELECT * FROM feedback_responses WHERE id = '" . (int) $before['id'] . "'");

        if (!is_array($row) || ((int) $row['status'] !== PG_FEEDBACK_ANSWERED)) {
            return;
        }

        $received = pg_feedback_event_payload($row, $survey, (int) $before['status'] !== PG_FEEDBACK_ANSWERED);
        pg_announce('feedback.received', $received);

        if (pg_feedback_followup_due($before, (int) $row['band'])) {
            pg_feedback_followup_start($row, $survey, $received, $now);
        }
    } catch (\Throwable $exception) {
        log_activity(pg_log_lang(array(
            'string' => 'A satisfaction survey answer could not be followed up: {var:1}',
            'vars'   => array($exception->getMessage()),
        )), 'UNKNOWN');
    }
}

/**
 * The user who follows up a row's low score (pg_feedback_owner()).
 *
 * @param array $row
 * @param array $survey
 * @return int
 */
function pg_feedback_followup_owner(array $row, array $survey)
{
    $facts = array('staff_user_id' => (int) $row['staff_user_id']);

    if ((string) $row['record_type'] === 'form') {
        $facts['form_editor_user_id'] = (int) db_value("SELECT form_editor_user_id FROM forms WHERE id = '" . (int) $row['record_id'] . "'");
    } elseif (((string) $row['record_type'] === 'conversation') && pg_schema_has('ws_mail_conversations')) {
        $facts['assignee_user_id'] = (int) db_value("SELECT assignee_id FROM ws_mail_conversations WHERE id = '" . (int) $row['record_id'] . "'");
    }

    return pg_feedback_owner($row['record_type'], $facts, (int) $survey['fallback_user_id']);
}

/**
 * The follow-up of a low score: the owner claimed on the row, the task
 * opened as the owner (on the survey's channel when they may write there),
 * the line in the channel, then feedback.detractor.
 *
 * @param array $row      the answered row
 * @param array $survey
 * @param array $received pg_feedback_event_payload()
 * @param int   $now
 * @return void
 */
function pg_feedback_followup_start(array $row, array $survey, array $received, $now)
{
    $owner_id = pg_feedback_followup_owner($row, $survey);

    // Claimed first, so a page sent twice at once opens one task.
    if ($owner_id > 0) {
        db("UPDATE feedback_responses SET owner_user_id = '" . $owner_id . "'
            WHERE (id = '" . (int) $row['id'] . "') AND (owner_user_id = 0) AND (task_id = 0)");

        if (mysqli_affected_rows(db::$con) !== 1) {
            return;
        }
    }

    $task_id = 0;
    $channel = null;

    if (pg_feedback_workspace()) {
        $channel = ((int) $survey['ws_channel_id'] > 0) ? ws_channel((int) $survey['ws_channel_id']) : null;
        $channel = (is_array($channel) && ((int) $channel['archived_at'] === 0)) ? $channel : null;
        $viewer = ($owner_id > 0) ? ws_change_viewer_for_id($owner_id) : null;
        $input = pg_feedback_followup_input($row, $survey, $owner_id, $channel ? (int) $channel['id'] : 0, $now);

        if (is_array($viewer) && !empty($viewer['member']) && $input) {
            $opened = ws_followup_task_open($viewer, $input);
            $task_id = $opened['ok'] ? (int) $opened['task_id'] : 0;
        }

        if ($task_id > 0) {
            db("UPDATE feedback_responses SET task_id = '" . $task_id . "' WHERE id = '" . (int) $row['id'] . "'");
        }

        if ($channel) {
            ws_message_system((int) $channel['id'], pg_feedback_followup_line($row, $survey, $task_id), $task_id);
        }
    }

    pg_announce('feedback.detractor', pg_feedback_detractor_payload($received, $owner_id, $task_id));
}

/**
 * The general job's escalation: a follow-up still open 48 hours after its
 * task was opened gets the survey's escalation user, told as anyone handed
 * a task is (the inbox and the task's own notices). Claimed first, once.
 *
 * @param int $now
 * @return void
 */
function pg_feedback_escalation_run($now)
{
    if (!pg_feedback_workspace()) {
        return;
    }

    $rows = (array) db_items(
        "SELECT r.id, r.task_id, r.escalated_at, s.escalation_user_id, t.status AS task_status, t.created_at AS task_created_at, t.creator_id
        FROM feedback_responses r
        JOIN feedback_surveys s ON s.id = r.survey_id
        JOIN ws_tasks t ON t.id = r.task_id
        WHERE (s.escalation_user_id > 0) AND (r.answered_at > '" . ((int) $now - PG_FEEDBACK_ESCALATE_WINDOW_DAYS * 86400) . "')
            AND (r.answered_at <= '" . ((int) $now - PG_FEEDBACK_ESCALATE_HOURS * 3600) . "') AND (r.task_id > 0) AND (r.escalated_at = 0)
            AND (t.status IN ('todo', 'doing', 'waiting')) AND (t.created_at <= '" . ((int) $now - PG_FEEDBACK_ESCALATE_HOURS * 3600) . "')
        ORDER BY r.id
        LIMIT " . PG_FEEDBACK_ESCALATE_ROWS);

    foreach ($rows as $row) {
        if (!pg_feedback_escalation_due($row, $row['task_status'], $row['task_created_at'], $row['escalation_user_id'], $now)) {
            continue;
        }

        db("UPDATE feedback_responses SET escalated_at = '" . (int) $now . "' WHERE (id = '" . (int) $row['id'] . "') AND (escalated_at = 0)");

        if (mysqli_affected_rows(db::$con) !== 1) {
            continue;
        }

        $people = ws_task_assignee_ids((int) $row['task_id']);
        $people[] = (int) $row['escalation_user_id'];
        ws_task_set_assignees(array('id' => (int) $row['creator_id']), (int) $row['task_id'], $people);
    }
}

/**
 * Where an answer whose follow-up carries these tags is found, as SQL
 * conditions on feedback_responses: by its keyed contact or record, so a
 * task drawer never reads the whole table. array() for a task with none of
 * the tags a follow-up gets. Pure.
 *
 * @param array[] $tags the task's tags: type, id
 * @return string[]
 */
function pg_feedback_followup_where(array $tags)
{
    $where = array();

    foreach ($tags as $tag) {
        $id = (int) ($tag['id'] ?? 0);
        $type = (string) ($tag['type'] ?? '');

        if ($id <= 0) {
            continue;
        }

        if ($type === 'contact') {
            $where[] = "(r.contact_id = '" . $id . "')";
        } elseif (in_array($type, array('booking', 'form', 'order'), true)) {
            $where[] = "((r.record_type = '" . $type . "') AND (r.record_id = '" . $id . "'))";
        } elseif ($type === 'calendar_event') {
            $where[] = "((r.record_type = 'ticket') AND (r.subject_id = '" . $id . "'))";
        }
    }

    return $where;
}

/**
 * The survey answer a follow-up task is about, for its drawer: the survey,
 * the score, the comment and the outcome with its choices. Null for any
 * other task.
 *
 * @param int     $task_id
 * @param array[] $tags the task's tags: type, id
 * @return array|null
 */
function pg_feedback_followup_part($task_id, array $tags)
{
    $where = pg_feedback_followup_where($tags);

    if (((int) $task_id <= 0) || !$where || !pg_feedback_sends_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT r.score, r.comment, r.outcome, s.name, s.kind
        FROM feedback_responses r
        JOIN feedback_surveys s ON s.id = r.survey_id
        WHERE (r.task_id = '" . (int) $task_id . "') AND (" . implode(' OR ', $where) . ")
        ORDER BY r.id DESC
        LIMIT 1");

    if (!is_array($row)) {
        return null;
    }

    $range = pg_feedback_range($row['kind']);
    $outcomes = array();

    foreach (pg_feedback_outcomes() as $value => $label) {
        $outcomes[] = array($value, lang($label));
    }

    return array(
        'title'    => lang('Survey answer'),
        'score'    => lang(array('string' => 'Score in {var:1}: {var:2} of {var:3}', 'vars' => array((string) $row['name'], (int) $row['score'], (int) ($range[1] ?? 0)))),
        'comment'  => (string) ($row['comment'] ?? ''),
        'outcome'  => pg_feedback_outcome_valid((string) $row['outcome']),
        'outcomes' => $outcomes,
        'label'    => lang('How did it end?'),
    );
}

/**
 * Keeps how a follow-up ended on the answer its task is about.
 *
 * @param int $task_id
 * @param int $outcome pg_feedback_outcome_valid()
 * @return void
 */
function pg_feedback_outcome_set($task_id, $outcome)
{
    if (((int) $task_id <= 0) || ((int) $outcome <= 0) || !pg_feedback_sends_ready()) {
        return;
    }

    db("UPDATE feedback_responses SET outcome = '" . (int) $outcome . "' WHERE task_id = '" . (int) $task_id . "'");
}
