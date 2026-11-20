<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Satisfaction surveys (NPS, CSAT, effort): the pure rules. A score's band
 * by its survey kind, the NPS, CSAT and effort sums, the fatigue rule (one
 * survey per person in 30 days, never staff or listed test addresses, never
 * again after "Don't ask me again"), the low-score rule, the chooser of a
 * low score's owner and the signed token of an answer link. Every function
 * here works on plain values; the tables and the sends live elsewhere.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Survey kinds, as stored in feedback_surveys.kind. */
define('PG_FEEDBACK_NPS', 1);
define('PG_FEEDBACK_CSAT', 2);
define('PG_FEEDBACK_EFFORT', 3);

/** Score bands, as stored in feedback_responses.band. */
define('PG_FEEDBACK_BAND_LOW', 1);
define('PG_FEEDBACK_BAND_MIDDLE', 2);
define('PG_FEEDBACK_BAND_HIGH', 3);

/** Days between two surveys to the same person, across all surveys. */
define('PG_FEEDBACK_REST_DAYS', 30);

/** Days an answer link stays valid. */
define('PG_FEEDBACK_TOKEN_DAYS', 30);

/** Seconds a token's issue time may lie ahead of this server's clock. */
define('PG_FEEDBACK_TOKEN_SKEW', 300);

/**
 * The lowest and highest score of a survey kind, array() for an unknown
 * kind. Pure.
 *
 * @param int $kind PG_FEEDBACK_NPS, PG_FEEDBACK_CSAT or PG_FEEDBACK_EFFORT
 * @return array  array(min, max)
 */
function pg_feedback_range($kind)
{
    switch ((int) $kind) {
        case PG_FEEDBACK_NPS:
            return array(0, 10);
        case PG_FEEDBACK_CSAT:
            return array(1, 5);
        case PG_FEEDBACK_EFFORT:
            return array(1, 7);
    }

    return array();
}

/**
 * Whether a value is a whole score inside its kind's range. A link's score
 * arrives as a string, so digits are read; anything else is refused. Pure.
 *
 * @param int   $kind
 * @param mixed $score
 * @return bool
 */
function pg_feedback_score_valid($kind, $score)
{
    $range = pg_feedback_range($kind);

    if (!$range) {
        return false;
    }

    if (is_int($score)) {
        $value = $score;
    } elseif (is_string($score) && (preg_match('/^[0-9]{1,2}$/', $score) === 1)) {
        $value = (int) $score;
    } else {
        return false;
    }

    return ($value >= $range[0]) && ($value <= $range[1]);
}

/**
 * A score's band: NPS 0-6 low, 7-8 middle, 9-10 high; CSAT 1-2 low, 3
 * middle, 4-5 high; effort (1 = very easy) 6-7 low, 3-5 middle, 1-2 high.
 * 0 for an invalid score or kind. Pure.
 *
 * @param int   $kind
 * @param mixed $score
 * @return int  PG_FEEDBACK_BAND_* or 0
 */
function pg_feedback_band($kind, $score)
{
    if (!pg_feedback_score_valid($kind, $score)) {
        return 0;
    }

    $score = (int) $score;

    switch ((int) $kind) {
        case PG_FEEDBACK_NPS:
            return ($score <= 6) ? PG_FEEDBACK_BAND_LOW : (($score <= 8) ? PG_FEEDBACK_BAND_MIDDLE : PG_FEEDBACK_BAND_HIGH);
        case PG_FEEDBACK_CSAT:
            return ($score <= 2) ? PG_FEEDBACK_BAND_LOW : (($score === 3) ? PG_FEEDBACK_BAND_MIDDLE : PG_FEEDBACK_BAND_HIGH);
        case PG_FEEDBACK_EFFORT:
            return ($score >= 6) ? PG_FEEDBACK_BAND_LOW : (($score >= 3) ? PG_FEEDBACK_BAND_MIDDLE : PG_FEEDBACK_BAND_HIGH);
    }

    return 0;
}

/**
 * Whether an answer is a low score that is followed up (a task for its
 * owner, a channel line, the detractor event). Pure.
 *
 * @param int   $kind
 * @param mixed $score
 * @return bool
 */
function pg_feedback_is_low($kind, $score)
{
    return pg_feedback_band($kind, $score) === PG_FEEDBACK_BAND_LOW;
}

/**
 * The valid scores of a list as integers. Pure.
 *
 * @param int   $kind
 * @param array $scores
 * @return int[]
 */
function pg_feedback_valid_scores($kind, $scores)
{
    $valid = array();

    foreach ((array) $scores as $score) {
        if (pg_feedback_score_valid($kind, $score)) {
            $valid[] = (int) $score;
        }
    }

    return $valid;
}

/**
 * A whole percentage of a part, null when there is no whole. Pure.
 *
 * @param int $part
 * @param int $whole
 * @return int|null
 */
function pg_feedback_percent($part, $whole)
{
    return ($whole > 0) ? (int) round($part * 100 / $whole) : null;
}

/**
 * The average of a list with one decimal, null for an empty list. Pure.
 *
 * @param int[] $values
 * @return float|null
 */
function pg_feedback_average($values)
{
    return $values ? round(array_sum($values) / count($values), 1) : null;
}

/**
 * The NPS of a list of answers: the share of promoters (9-10) less the share
 * of detractors (0-6), a whole number from -100 to 100; null with no
 * answers. Invalid scores are left out. Pure.
 *
 * @param array $scores
 * @return array  count, promoters, passives, detractors, score
 */
function pg_feedback_nps($scores)
{
    $sum = array('count' => 0, 'promoters' => 0, 'passives' => 0, 'detractors' => 0, 'score' => null);

    foreach (pg_feedback_valid_scores(PG_FEEDBACK_NPS, $scores) as $score) {
        $band = pg_feedback_band(PG_FEEDBACK_NPS, $score);
        $sum['count']++;
        $sum[($band === PG_FEEDBACK_BAND_HIGH) ? 'promoters' : (($band === PG_FEEDBACK_BAND_LOW) ? 'detractors' : 'passives')]++;
    }

    if ($sum['count'] > 0) {
        $sum['score'] = (int) round(($sum['promoters'] - $sum['detractors']) * 100 / $sum['count']);
    }

    return $sum;
}

/**
 * The CSAT of a list of answers: the share of 4 and 5 as a whole percentage,
 * and the average; null with no answers. Pure.
 *
 * @param array $scores
 * @return array  count, satisfied, score, average
 */
function pg_feedback_csat($scores)
{
    $valid = pg_feedback_valid_scores(PG_FEEDBACK_CSAT, $scores);
    $satisfied = 0;

    foreach ($valid as $score) {
        if (pg_feedback_band(PG_FEEDBACK_CSAT, $score) === PG_FEEDBACK_BAND_HIGH) {
            $satisfied++;
        }
    }

    return array(
        'count'     => count($valid),
        'satisfied' => $satisfied,
        'score'     => pg_feedback_percent($satisfied, count($valid)),
        'average'   => pg_feedback_average($valid),
    );
}

/**
 * The effort sum of a list of answers: the share of easy answers (1-2) as a
 * whole percentage, and the average (lower is easier); null with no
 * answers. Pure.
 *
 * @param array $scores
 * @return array  count, easy, score, average
 */
function pg_feedback_effort($scores)
{
    $valid = pg_feedback_valid_scores(PG_FEEDBACK_EFFORT, $scores);
    $easy = 0;

    foreach ($valid as $score) {
        if (pg_feedback_band(PG_FEEDBACK_EFFORT, $score) === PG_FEEDBACK_BAND_HIGH) {
            $easy++;
        }
    }

    return array(
        'count'   => count($valid),
        'easy'    => $easy,
        'score'   => pg_feedback_percent($easy, count($valid)),
        'average' => pg_feedback_average($valid),
    );
}

/**
 * The sum of a kind's answers, array() for an unknown kind. Pure.
 *
 * @param int   $kind
 * @param array $scores
 * @return array
 */
function pg_feedback_sum($kind, $scores)
{
    switch ((int) $kind) {
        case PG_FEEDBACK_NPS:
            return pg_feedback_nps($scores);
        case PG_FEEDBACK_CSAT:
            return pg_feedback_csat($scores);
        case PG_FEEDBACK_EFFORT:
            return pg_feedback_effort($scores);
    }

    return array();
}

/**
 * A person's address or number folded for comparing: an e-mail address
 * trimmed and lower-cased, a number with its digits only. '' for nothing.
 * Pure.
 *
 * @param mixed $person e-mail address or phone number
 * @return string
 */
function pg_feedback_fold_person($person)
{
    $person = trim((string) $person);

    if (strpos($person, '@') !== false) {
        return function_exists('mb_strtolower') ? mb_strtolower($person, 'UTF-8') : strtolower($person);
    }

    return (string) preg_replace('/[^0-9]/', '', $person);
}

/**
 * The person hash of feedback_responses.person_hash: the SHA-1 of the folded
 * address or number, 40 hex characters; '' for nothing. Pure.
 *
 * @param mixed $person
 * @return string
 */
function pg_feedback_person_hash($person)
{
    $folded = pg_feedback_fold_person($person);

    return ($folded === '') ? '' : sha1($folded);
}

/**
 * The person hashes of a list of addresses and numbers, each once and in
 * the order first met; an empty or unreadable one is left out. The
 * personal data export and erasure find a person's answers by them. Pure.
 *
 * @param array $people e-mail addresses and phone numbers
 * @return array
 */
function pg_feedback_person_hashes(array $people)
{
    $hashes = array();

    foreach ($people as $person) {
        $hash = pg_feedback_person_hash($person);

        if (($hash !== '') && !in_array($hash, $hashes, true)) {
            $hashes[] = $hash;
        }
    }

    return $hashes;
}

/**
 * One feedback_responses row as the personal data export gives it: the
 * score only once the survey was answered (0 is an NPS answer, so an
 * unanswered row's 0 is not one), the extra answers decoded from JSON
 * (null when unreadable) and the ids and times as numbers. Pure.
 *
 * @param array $row selected columns of feedback_responses
 * @return array
 */
function pg_feedback_export_row(array $row)
{
    foreach (array('id', 'record_id', 'status', 'sent_at', 'answered_at', 'band', 'stopped', 'outcome', 'created_at', 'kind') as $column) {
        if (isset($row[$column])) {
            $row[$column] = (int) $row[$column];
        }
    }

    if (array_key_exists('score', $row)) {
        $row['score'] = empty($row['answered_at']) ? null : (int) $row['score'];
    }

    if (array_key_exists('answers', $row)) {
        $answers = is_string($row['answers']) ? json_decode($row['answers'], true) : null;
        $row['answers'] = is_array($answers) ? $answers : null;
    }

    return $row;
}

/**
 * Why a person is not asked now, '' when they may be. In order: 'none' (no
 * address or number), 'staff' (a staff account's address), 'test' (a listed
 * test address), 'stopped' ("Don't ask me again" was said once, for good),
 * 'recent' (a survey went to them in the last 30 days, any survey). Pure.
 *
 * @param string $person  e-mail address or phone number
 * @param array  $history last_asked_at (unix time, 0 for never), stopped (bool)
 * @param array  $rules   staff (addresses or numbers), test (addresses or numbers)
 * @param int    $now
 * @return string
 */
function pg_feedback_fatigue($person, $history, $rules, $now)
{
    $folded = pg_feedback_fold_person($person);

    if ($folded === '') {
        return 'none';
    }

    foreach (array('staff', 'test') as $list) {
        foreach (isset($rules[$list]) ? (array) $rules[$list] : array() as $listed) {
            if (pg_feedback_fold_person($listed) === $folded) {
                return $list;
            }
        }
    }

    if (!empty($history['stopped'])) {
        return 'stopped';
    }

    $last = isset($history['last_asked_at']) ? (int) $history['last_asked_at'] : 0;

    if (($last > 0) && ((int) $now - $last < PG_FEEDBACK_REST_DAYS * 86400)) {
        return 'recent';
    }

    return '';
}

/**
 * The user who follows up a low score: a booking's staff user
 * (booking_staff.user_id), a form's Form Editor, a mail conversation's
 * assignee; any other record, or one without that user, goes to the
 * survey's fallback owner. 0 when there is none. Pure.
 *
 * @param string $record_type booking, ticket, course, form, order, conversation
 * @param array  $facts       staff_user_id, form_editor_user_id, assignee_user_id
 * @param int    $fallback_user_id
 * @return int
 */
function pg_feedback_owner($record_type, $facts, $fallback_user_id)
{
    $fields = array(
        'booking'      => 'staff_user_id',
        'form'         => 'form_editor_user_id',
        'conversation' => 'assignee_user_id',
    );
    $record_type = (string) $record_type;

    if (isset($fields[$record_type]) && !empty($facts[$fields[$record_type]]) && ((int) $facts[$fields[$record_type]] > 0)) {
        return (int) $facts[$fields[$record_type]];
    }

    return max(0, (int) $fallback_user_id);
}

/**
 * The purposes an answer link's token may carry: 'answer' (the score
 * buttons and the answer page) and 'stop' ("Don't ask me again"). Pure.
 *
 * @return string[]
 */
function pg_feedback_token_purposes()
{
    return array('answer', 'stop');
}

/**
 * The signature of a token: an HMAC of its purpose, response and issue time
 * under the site's key. Pure.
 *
 * @param string $purpose
 * @param int    $response_id
 * @param int    $issued_at
 * @param string $key
 * @return string 64 hex characters
 */
function pg_feedback_token_sign($purpose, $response_id, $issued_at, $key)
{
    return hash_hmac('sha256', 'feedback-' . $purpose . ':' . (int) $response_id . ':' . (int) $issued_at, (string) $key);
}

/**
 * A signed token for a response's link: the response id and issue time in
 * base 36, then the signature, joined by dots. '' without a response, a
 * known purpose or a site key. Only its hash is stored. Pure.
 *
 * @param string $purpose     one of pg_feedback_token_purposes()
 * @param int    $response_id feedback_responses.id
 * @param int    $issued_at
 * @param string $key         the site's ENCRYPTION_KEY (pg_feedback_key())
 * @return string
 */
function pg_feedback_token_make($purpose, $response_id, $issued_at, $key)
{
    $response_id = (int) $response_id;
    $issued_at = (int) $issued_at;

    if (($response_id <= 0) || ($issued_at <= 0) || ((string) $key === '') || !in_array((string) $purpose, pg_feedback_token_purposes(), true)) {
        return '';
    }

    return base_convert((string) $response_id, 10, 36) . '.' . base_convert((string) $issued_at, 10, 36) . '.'
        . pg_feedback_token_sign($purpose, $response_id, $issued_at, $key);
}

/**
 * The response a token names when it was made for this purpose under this
 * key and is not older than 30 days; 0 otherwise. Pure.
 *
 * @param mixed  $token
 * @param string $purpose
 * @param int    $now
 * @param string $key
 * @return int
 */
function pg_feedback_token_check($token, $purpose, $now, $key)
{
    if (!is_string($token) || ((string) $key === '') || !in_array((string) $purpose, pg_feedback_token_purposes(), true)
        || (preg_match('/^([0-9a-z]{1,10})\.([0-9a-z]{1,10})\.([0-9a-f]{64})$/', $token, $parts) !== 1)) {
        return 0;
    }

    $response_id = (int) base_convert($parts[1], 36, 10);
    $issued_at = (int) base_convert($parts[2], 36, 10);

    // Only the spelling pg_feedback_token_make() writes, so a token has one
    // form and one stored hash.
    if (($response_id <= 0) || ($issued_at <= 0)
        || ($parts[1] !== base_convert((string) $response_id, 10, 36))
        || ($parts[2] !== base_convert((string) $issued_at, 10, 36))
        || !hash_equals(pg_feedback_token_sign($purpose, $response_id, $issued_at, $key), $parts[3])) {
        return 0;
    }

    $now = (int) $now;

    if (($issued_at > $now + PG_FEEDBACK_TOKEN_SKEW) || ($now - $issued_at > PG_FEEDBACK_TOKEN_DAYS * 86400)) {
        return 0;
    }

    return $response_id;
}

/**
 * The hash of a token kept in feedback_responses.token_hash, 64 hex
 * characters. Pure.
 *
 * @param string $token
 * @return string
 */
function pg_feedback_token_hash($token)
{
    return hash('sha256', (string) $token);
}

/**
 * The site's key for the answer tokens, '' when none is set.
 *
 * @return string
 */
function pg_feedback_key()
{
    return defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
}
