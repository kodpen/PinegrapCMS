<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Satisfaction surveys reaching people and their answers coming back. The
 * general job's pass (pg_feedback_job_run()) finds pg_feedback_message_send()
 * through pg_feedback_sender(), which loads this file: the e-mail with a
 * signed link per score and "Don't ask me again", or one short signed link
 * by SMS or in the survey's WhatsApp template (feedback_whatsapp.php). The links open the public answer page (feedback.php): opening one
 * writes nothing, so a mail scanner's visit never counts; the page's own
 * request writes the score, the comment and the extra answers. After the
 * answer the page shows the same review invitation to everyone, and the
 * score can be changed for 7 days.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/feedback_surveys.php');

/** Days an answer may still be changed. */
define('PG_FEEDBACK_CHANGE_DAYS', 7);

/** The longest comment and short-text answer kept, in characters. */
define('PG_FEEDBACK_COMMENT_MAX', 2000);
define('PG_FEEDBACK_SHORT_MAX', 300);

/**
 * The channels a message is built for. A WhatsApp row still waits queued
 * while its survey's template cannot be sent (pg_feedback_due_channel_sql()).
 *
 * @return string[]
 */
function pg_feedback_message_channels()
{
    return array('email', 'sms', 'whatsapp');
}

/**
 * The address of the answer page. Links leave the request, so the site's
 * set host name is used.
 *
 * @return string
 */
function pg_feedback_page_url()
{
    return (defined('URL_SCHEME') ? URL_SCHEME . HOSTNAME_SETTING : '') . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/feedback.php';
}

/**
 * A row's links, signed at its due time as its stored hash was: the page,
 * the page with each score chosen, and "Don't ask me again". Empty without
 * a key. Pure.
 *
 * @param string $page feedback.php's address
 * @param int    $id
 * @param int    $due_at
 * @param int    $kind
 * @param string $key
 * @return array page, scores (score => address), stop; array() without a key
 */
function pg_feedback_links($page, $id, $due_at, $kind, $key)
{
    $answer = pg_feedback_token_make('answer', $id, $due_at, $key);
    $stop = pg_feedback_token_make('stop', $id, $due_at, $key);

    if (($answer === '') || ($stop === '')) {
        return array();
    }

    $links = array('page' => $page . '?t=' . $answer, 'scores' => array(), 'stop' => $page . '?x=' . $stop);
    $range = pg_feedback_range($kind);

    for ($score = $range[0]; $score <= $range[1]; $score++) {
        $links['scores'][$score] = $links['page'] . '&s=' . $score;
    }

    return $links;
}

/**
 * The survey's question and comment label in a language: its own text, the
 * first language's, else the kind's default. Pure but for lang().
 *
 * @param array  $survey pg_feedback_survey_decode()
 * @param string $lang
 * @param string $first  the site's first language
 * @return array question, comment
 */
function pg_feedback_texts_in(array $survey, $lang, $first)
{
    $defaults = pg_feedback_default_texts($survey['kind']);
    $out = array();

    foreach (array('question', 'comment') as $name) {
        $text = pg_feedback_text_in($survey['texts'][$name], $lang, $first);
        $out[$name] = ($text !== '') ? $text : $defaults[$name];
    }

    return $out;
}

/**
 * The SMS of a survey: the store's name, the question and the link, with
 * "Reminder:" before a reminder's question. Nothing else: a service message
 * holds no promotion. Pure but for lang().
 *
 * @param string $store
 * @param string $question
 * @param string $url
 * @param bool   $reminder
 * @return string
 */
function pg_feedback_sms_text($store, $question, $url, $reminder)
{
    $question = $reminder ? lang(array('string' => 'Reminder: {var:1}', 'vars' => array($question))) : $question;

    return trim(((trim((string) $store) !== '') ? trim((string) $store) . ': ' : '') . $question . ' ' . $url);
}

/**
 * Sends one survey's message: the e-mail, the SMS or the WhatsApp template,
 * in the site's first language. Called by the general job's pass for a due row (already marked
 * sent) and for its one reminder. A commercial survey's SMS goes as the
 * commercial kind, with its identity line and its consent asked again at
 * the send; a service survey's as kind feedback.
 *
 * @param array $row      a feedback_responses row
 * @param array $survey   pg_feedback_survey_decode()
 * @param bool  $reminder
 * @return bool whether it went (an e-mail queued, an SMS queued)
 */
function pg_feedback_message_send($row, $survey, $reminder)
{
    $links = pg_feedback_links(pg_feedback_page_url(), (int) $row['id'], (int) $row['due_at'], (int) $survey['kind'], pg_feedback_key());

    if (!$links || !in_array((string) $row['channel'], pg_feedback_message_channels(), true)) {
        return false;
    }

    $first = array_keys(pg_feedback_survey_languages())[0];
    $texts = pg_feedback_texts_in($survey, $first, $first);
    $store = defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : '';

    if ((string) $row['channel'] === 'whatsapp') {
        $question = $reminder ? lang(array('string' => 'Reminder: {var:1}', 'vars' => array($texts['question']))) : $texts['question'];

        return pg_feedback_whatsapp_send($row, $survey, $store, $question, $links['page'], (bool) $reminder);
    }

    if ((string) $row['channel'] === 'sms') {
        if (!function_exists('pg_sms_enqueue')) {
            return false;
        }

        $queued = pg_sms_enqueue((string) $row['recipient'], pg_feedback_sms_text($store, $texts['question'], $links['page'], (bool) $reminder),
            ((int) $survey['purpose'] === 1) ? 'marketing' : 'feedback',
            array('reference' => 'feedback:' . (int) $row['id'] . ($reminder ? ':r' : ''), 'expires_at' => time() + 86400));

        return !empty($queued['ok']);
    }

    return (bool) email(array(
        'to'                 => (string) $row['recipient'],
        'from_name'          => $store,
        'from_email_address' => defined('EMAIL_ADDRESS') ? EMAIL_ADDRESS : '',
        'subject'            => $reminder ? lang(array('string' => 'Reminder: {var:1}', 'vars' => array($texts['question']))) : $texts['question'],
        'format'             => 'html',
        'body'               => pg_feedback_mail_html($survey['kind'], $texts['question'], $texts['comment'], $links),
        'queue'              => true,
    ));
}

/**
 * Where an answer stands for the page. Pure.
 *
 * open: sent and not answered; changeable: answered less than 7 days ago;
 * answered: answered earlier, no longer changed; closed: not sent, cancelled
 * or left unanswered.
 *
 * @param array $row status, answered_at
 * @param int   $now
 * @return string
 */
function pg_feedback_answer_state(array $row, $now)
{
    $status = (int) ($row['status'] ?? -1);

    if ($status === PG_FEEDBACK_SENT) {
        return 'open';
    }

    if ($status === PG_FEEDBACK_ANSWERED) {
        return ((int) $now - (int) $row['answered_at'] < PG_FEEDBACK_CHANGE_DAYS * 86400) ? 'changeable' : 'answered';
    }

    return 'closed';
}

/**
 * A text the visitor typed, trimmed, cut to its length, control characters
 * but line breaks dropped. Pure.
 *
 * @param mixed $value
 * @param int   $max
 * @return string
 */
function pg_feedback_typed($value, $max)
{
    if (!is_string($value)) {
        return '';
    }

    $value = (string) preg_replace('/[^\P{Cc}\n]/u', '', str_replace("\r\n", "\n", $value));

    return trim(mb_substr(trim($value), 0, (int) $max));
}

/**
 * What the answer page posted, checked against the survey: the score in
 * the kind's range, the comment, and each extra question's answer (a short
 * text, or one of its choices in the page's language, kept in the first
 * language's words). Null when the score is missing or invalid. Pure but
 * for the survey's texts.
 *
 * @param array  $post
 * @param array  $survey pg_feedback_survey_decode()
 * @param string $lang   the page's language
 * @param string $first  the site's first language
 * @return array|null score, comment, answers (list of question, answer)
 */
function pg_feedback_answer_input(array $post, array $survey, $lang, $first)
{
    $score = $post['score'] ?? null;

    if (!pg_feedback_score_valid($survey['kind'], $score)) {
        return null;
    }

    $answers = array();

    foreach ($survey['texts']['extra'] as $index => $extra) {
        $label = pg_feedback_text_in($extra['label'], $first, $first);
        $value = $post['extra'][$index] ?? '';

        if ($extra['type'] === 'choice') {
            $shown = $extra['choices'][$lang] ?? array();
            $stored = $extra['choices'][$first] ?? array();
            $at = (is_string($value) && ($value !== '')) ? array_search($value, $shown ?: $stored, true) : false;
            $value = ($at === false) ? '' : (string) ($stored[$at] ?? $value);
        } else {
            $value = pg_feedback_typed($value, PG_FEEDBACK_SHORT_MAX);
        }

        if (($label !== '') && ($value !== '')) {
            $answers[] = array('question' => $label, 'answer' => $value);
        }
    }

    return array(
        'score'   => (int) $score,
        'comment' => pg_feedback_typed($post['comment'] ?? '', PG_FEEDBACK_COMMENT_MAX),
        'answers' => $answers,
    );
}

/**
 * The review invitation's address, the same for every score: a delivery's
 * first product's review form, any other survey Google's write-a-review
 * page of the site's place. '' when the survey does not invite or there is
 * nowhere to go. Pure.
 *
 * @param array  $survey      review_invite, touchpoint
 * @param string $place_id    the Google place id (pg_grev_state())
 * @param string $product_url the delivered order's first review form
 * @return string
 */
function pg_feedback_review_url(array $survey, $place_id, $product_url)
{
    if (empty($survey['review_invite'])) {
        return '';
    }

    if (($survey['touchpoint'] ?? '') === 'delivery') {
        return (string) $product_url;
    }

    $place_id = trim((string) $place_id);

    return ($place_id === '') ? '' : 'https://search.google.com/local/writereview?placeid=' . rawurlencode($place_id);
}

/**
 * The row an answer link names: the token checked, then found by its hash
 * and its id. Null for a wrong, old or replaced link.
 *
 * @param mixed $token
 * @param int   $now
 * @return array|null
 */
function pg_feedback_answer_find($token, $now)
{
    $id = pg_feedback_token_check($token, 'answer', $now, pg_feedback_key());

    if (($id <= 0) || !pg_feedback_sends_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT * FROM feedback_responses
        WHERE (token_hash = '" . e(pg_feedback_token_hash($token)) . "') AND (id = '" . (int) $id . "')");

    return is_array($row) ? $row : null;
}

/**
 * The row a "Don't ask me again" link names: signed for the row's current
 * due time, as its answer link is. Null otherwise.
 *
 * @param mixed $token
 * @param int   $now
 * @return array|null
 */
function pg_feedback_stop_find($token, $now)
{
    $key = pg_feedback_key();
    $id = pg_feedback_token_check($token, 'stop', $now, $key);

    if (($id <= 0) || !pg_feedback_sends_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM feedback_responses WHERE id = '" . (int) $id . "'");

    return (is_array($row) && hash_equals(pg_feedback_token_make('stop', $id, (int) $row['due_at'], $key), (string) $token)) ? $row : null;
}

/**
 * A row's survey as stored, switched off or archived too: an answer to a
 * survey sent before it was switched off still counts.
 *
 * @param int $id
 * @return array|null
 */
function pg_feedback_answer_survey($id)
{
    $row = db_item("SELECT * FROM feedback_surveys WHERE id = '" . (int) $id . "'");

    return is_array($row) ? pg_feedback_survey_decode($row) : null;
}

/**
 * Writes an answer: the first one, or a change within 7 days of it (the
 * first answer's time is kept). Only the score when it is the page's own
 * write on opening; the comment and the extra answers with "Send".
 *
 * @param int   $id
 * @param int   $kind
 * @param array $answer pg_feedback_answer_input()
 * @param bool  $score_only
 * @param int   $now
 * @return bool whether the row changed
 */
function pg_feedback_answer_write($id, $kind, array $answer, $score_only, $now)
{
    $set = "score = '" . (int) $answer['score'] . "', band = '" . pg_feedback_band($kind, (int) $answer['score']) . "'";

    if (!$score_only) {
        $set .= ", comment = " . (($answer['comment'] === '') ? 'NULL' : "'" . e($answer['comment']) . "'")
            . ", answers = " . ($answer['answers'] ? "'" . e(json_encode($answer['answers'], JSON_UNESCAPED_UNICODE)) . "'" : 'NULL');
    }

    // A score-only write never overwrites an answer already given.
    $where = $score_only ? "(status = " . PG_FEEDBACK_SENT . ")"
        : "((status = " . PG_FEEDBACK_SENT . ") OR ((status = " . PG_FEEDBACK_ANSWERED . ") AND (answered_at > '" . ((int) $now - PG_FEEDBACK_CHANGE_DAYS * 86400) . "')))";

    db("UPDATE feedback_responses
        SET " . $set . ", status = " . PG_FEEDBACK_ANSWERED . ", answered_at = IF(answered_at = 0, '" . (int) $now . "', answered_at)
        WHERE (id = '" . (int) $id . "') AND " . $where);

    return mysqli_affected_rows(db::$con) === 1;
}

/**
 * "Don't ask me again": kept on the row for good, and every survey still
 * waiting for the same person is cancelled.
 *
 * @param array $row
 * @return void
 */
function pg_feedback_stop(array $row)
{
    db("UPDATE feedback_responses SET stopped = 1 WHERE id = '" . (int) $row['id'] . "'");

    if ((string) $row['person_hash'] !== '') {
        db("UPDATE feedback_responses SET status = " . PG_FEEDBACK_CANCELLED . "
            WHERE (person_hash = '" . e((string) $row['person_hash']) . "') AND (status = " . PG_FEEDBACK_QUEUED . ")");
    }
}

/**
 * The review invitation's address for a row (see pg_feedback_review_url()).
 *
 * @param array $survey
 * @param array $row
 * @return string
 */
function pg_feedback_review_url_of(array $survey, array $row)
{
    $product_url = '';

    if (!empty($survey['review_invite']) && ($survey['touchpoint'] === 'delivery') && ((string) $row['record_type'] === 'order')) {
        $detail_page = function_exists('pg_sw_catalog_detail_page_name') ? (string) pg_sw_catalog_detail_page_name(0) : '';
        $targets = ($detail_page !== '') ? pg_review_request_targets((int) $row['record_id']) : array();

        if ($targets) {
            $product_url = pg_review_request_url(URL_SCHEME . HOSTNAME_SETTING . PATH, $detail_page, $targets[0]['address_name']);
        }
    }

    $place_id = function_exists('pg_grev_state') ? (string) pg_grev_state()['place_id'] : '';

    return pg_feedback_review_url($survey, $place_id, $product_url);
}
