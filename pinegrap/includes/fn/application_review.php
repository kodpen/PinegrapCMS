<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the rules of application review on a form, with
// no database: eligibility over the answers, weighted criterion sums, the
// mean, the leniency correction, the spread flag, balanced reviewer
// assignment, ranking and the shortlist cut. The screens and the store call
// these; nothing here reads or writes a table.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Scores are compared and returned at this many decimals, so float noise from
 * the weighted sums never splits a tie or misses a cut-off.
 */
if (!defined('PG_APPLICATION_SCORE_DECIMALS')) {
    define('PG_APPLICATION_SCORE_DECIMALS', 4);
}

/**
 * A reviewer's scores are corrected for leniency only from this many scored
 * entries on; fewer give no reliable mean and spread.
 */
if (!defined('PG_APPLICATION_LENIENCY_MIN_SCORES')) {
    define('PG_APPLICATION_LENIENCY_MIN_SCORES', 5);
}

/**
 * Checks an entry's answers against the programme's eligibility rules.
 *
 * Rules (each an array with 'type' and 'field'):
 * - 'file': the field holds an uploaded file (a non-empty answer).
 * - 'range': the field is a number within 'min' and 'max' (either may be
 *   null or left out), both ends included.
 * - 'choice': the field's answer, or one of its answers for a multi-choice
 *   field, is in 'values'.
 * A rule of an unknown type is skipped. Eligibility never refuses an entry:
 * the caller marks a failing one "not eligible" with these reasons.
 *
 * @param array $rules   List of rules.
 * @param array $answers Field name => answer (string or list of strings).
 * @return array array('eligible' => bool, 'reasons' => list of
 *               array('rule' => index, 'field' => name, 'code' => missing |
 *               not_number | below | above | not_listed)).
 */
function pg_application_eligibility(array $rules, array $answers)
{
    $reasons = array();

    foreach (array_values($rules) as $index => $rule) {
        if (!is_array($rule) || !isset($rule['type'], $rule['field'])) {
            continue;
        }

        $field = (string) $rule['field'];
        $values = pg_application_answer_values(isset($answers[$field]) ? $answers[$field] : null);
        $code = '';

        switch ($rule['type']) {
            case 'file':
                if (!$values) {
                    $code = 'missing';
                }

                break;

            case 'range':
                if (!$values) {
                    $code = 'missing';
                } elseif (!is_numeric($values[0])) {
                    $code = 'not_number';
                } elseif (isset($rule['min']) && is_numeric($rule['min']) && (float) $values[0] < (float) $rule['min']) {
                    $code = 'below';
                } elseif (isset($rule['max']) && is_numeric($rule['max']) && (float) $values[0] > (float) $rule['max']) {
                    $code = 'above';
                }

                break;

            case 'choice':
                $allowed = array();

                foreach (isset($rule['values']) && is_array($rule['values']) ? $rule['values'] : array() as $allowed_value) {
                    $allowed[] = trim((string) $allowed_value);
                }

                if (!$values) {
                    $code = 'missing';
                } elseif (!array_intersect($values, $allowed)) {
                    $code = 'not_listed';
                }

                break;

            default:
                continue 2;
        }

        if ($code !== '') {
            $reasons[] = array('rule' => $index, 'field' => $field, 'code' => $code);
        }
    }

    return array('eligible' => !$reasons, 'reasons' => $reasons);
}

/**
 * An answer as a list of its non-empty trimmed values.
 *
 * @param mixed $answer A string, a number, a list or null.
 * @return array
 */
function pg_application_answer_values($answer)
{
    $values = array();

    foreach (is_array($answer) ? $answer : array($answer) as $value) {
        if (is_scalar($value)) {
            $value = trim((string) $value);

            if ($value !== '') {
                $values[] = $value;
            }
        }
    }

    return $values;
}

/**
 * Whether a round's criteria are usable: at least one, unique non-empty ids,
 * whole positive weights that add up to 100.
 *
 * @param array $criteria List of array('id' => string, 'weight' => int).
 * @return bool
 */
function pg_application_criteria_valid(array $criteria)
{
    if (!$criteria) {
        return false;
    }

    $ids = array();
    $total = 0;

    foreach ($criteria as $criterion) {
        if (!is_array($criterion) || !isset($criterion['id'], $criterion['weight'])) {
            return false;
        }

        $id = (string) $criterion['id'];
        $weight = $criterion['weight'];

        if ($id === '' || isset($ids[$id]) || !pg_application_is_whole($weight) || (int) $weight < 1) {
            return false;
        }

        $ids[$id] = true;
        $total += (int) $weight;
    }

    return $total === 100;
}

/**
 * Whether a value is a whole number (an int, or a numeric string or float
 * with no fraction).
 *
 * @param mixed $value
 * @return bool
 */
function pg_application_is_whole($value)
{
    if (is_int($value)) {
        return true;
    }

    if (is_bool($value) || !is_numeric($value)) {
        return false;
    }

    return (float) $value == floor((float) $value);
}

/**
 * One review's weighted sum: each criterion's 1 to 10 score times its weight,
 * over 100, so the result is on the same 1 to 10 scale.
 *
 * @param array $criteria List of array('id' => string, 'weight' => int),
 *                        weights adding up to 100.
 * @param array $scores   Criterion id => whole score from 1 to 10.
 * @return float|null Null while any criterion is unscored or out of range.
 */
function pg_application_weighted_sum(array $criteria, array $scores)
{
    if (!pg_application_criteria_valid($criteria)) {
        return null;
    }

    $sum = 0;

    foreach ($criteria as $criterion) {
        $id = (string) $criterion['id'];

        if (!isset($scores[$id]) || !pg_application_is_whole($scores[$id])) {
            return null;
        }

        $score = (int) $scores[$id];

        if ($score < 1 || $score > 10) {
            return null;
        }

        $sum += $score * (int) $criterion['weight'];
    }

    return round($sum / 100, PG_APPLICATION_SCORE_DECIMALS);
}

/**
 * The mean of a list of scores.
 *
 * @param array $values Numbers.
 * @return float|null Null for an empty list.
 */
function pg_application_mean(array $values)
{
    if (!$values) {
        return null;
    }

    return round(array_sum($values) / count($values), PG_APPLICATION_SCORE_DECIMALS);
}

/**
 * The population standard deviation of a list of numbers, unrounded.
 *
 * @param array $values Numbers.
 * @return float
 */
function pg_application_deviation(array $values)
{
    $count = count($values);

    if ($count < 2) {
        return 0.0;
    }

    $mean = array_sum($values) / $count;
    $squares = 0.0;

    foreach ($values as $value) {
        $squares += ($value - $mean) * ($value - $mean);
    }

    return sqrt($squares / $count);
}

/**
 * The leniency correction: each reviewer's scores standardised against that
 * reviewer's own mean and spread, then mapped back onto the mean and spread
 * of all the reviews given, kept within 1 to 10. A strict and a lenient
 * reviewer who order the same entries alike end up with the same scores.
 *
 * A reviewer with fewer than PG_APPLICATION_LENIENCY_MIN_SCORES scores, or who
 * gave every entry the same score, is not corrected: 'corrected' carries the
 * raw score. The raw score is always kept beside the corrected one.
 *
 * @param array $reviews List of array('reviewer' => id, 'entry' => id,
 *                       'score' => number), one per scored review.
 * @return array The same list, each review with 'corrected' (float) and
 *               'corrected_applied' (bool) added.
 */
function pg_application_leniency_correct(array $reviews)
{
    if (!$reviews) {
        return array();
    }

    $all = array();
    $by_reviewer = array();

    foreach ($reviews as $review) {
        $score = (float) $review['score'];
        $all[] = $score;
        $by_reviewer[(string) $review['reviewer']][] = $score;
    }

    $pool_mean = array_sum($all) / count($all);
    $pool_deviation = pg_application_deviation($all);

    $stats = array();

    foreach ($by_reviewer as $reviewer => $scores) {
        $deviation = pg_application_deviation($scores);

        $stats[$reviewer] = array(
            'mean'      => array_sum($scores) / count($scores),
            'deviation' => $deviation,
            'applies'   => count($scores) >= PG_APPLICATION_LENIENCY_MIN_SCORES && $deviation > 0,
        );
    }

    $result = array();

    foreach ($reviews as $key => $review) {
        $score = (float) $review['score'];
        $stat = $stats[(string) $review['reviewer']];

        if ($stat['applies']) {
            $corrected = $pool_mean + (($score - $stat['mean']) / $stat['deviation']) * $pool_deviation;
            $corrected = min(10.0, max(1.0, $corrected));
        } else {
            $corrected = $score;
        }

        $review['corrected'] = round($corrected, PG_APPLICATION_SCORE_DECIMALS);
        $review['corrected_applied'] = $stat['applies'];
        $result[$key] = $review;
    }

    return $result;
}

/**
 * The spread of an entry's scores: highest minus lowest.
 *
 * @param array $scores Numbers.
 * @return float 0 for fewer than two scores.
 */
function pg_application_spread(array $scores)
{
    if (count($scores) < 2) {
        return 0.0;
    }

    return round((float) max($scores) - (float) min($scores), PG_APPLICATION_SCORE_DECIMALS);
}

/**
 * Whether an entry's scores lie further apart than the programme's bound,
 * which flags it "needs consensus".
 *
 * @param array          $scores Numbers.
 * @param float|int|null $bound  The programme's bound; null means none.
 * @return bool
 */
function pg_application_needs_consensus(array $scores, $bound)
{
    if ($bound === null || $bound === '' || !is_numeric($bound) || count($scores) < 2) {
        return false;
    }

    return pg_application_spread($scores) > round((float) $bound, PG_APPLICATION_SCORE_DECIMALS);
}

/**
 * Gives each entry N reviewers from its pool: the fewest assigned first, ties
 * at random (or in pool order), never one reviewer twice on an entry and
 * never one who declared a conflict on it. Reviewers already given (a re-run
 * after a change) are kept and count towards their load; a kept reviewer who
 * since declared a conflict is dropped and replaced.
 *
 * @param array $entries   List of entry ids, in the order to serve them.
 * @param array $pool      List of reviewer ids.
 * @param int   $n         Reviewers per entry.
 * @param array $existing  Entry id => list of reviewer ids already given.
 * @param array $conflicts Entry id => list of reviewer ids who declared a
 *                         conflict.
 * @param bool  $random    Break load ties at random; false keeps pool order.
 * @return array array('assigned' => entry id => list of reviewer ids,
 *               'short' => entry id => how many reviewers it still lacks,
 *               only for entries the pool could not fill).
 */
function pg_application_assign(array $entries, array $pool, $n, array $existing = array(), array $conflicts = array(), $random = true)
{
    $n = max(0, (int) $n);
    $pool = array_values(array_unique(array_map('strval', $pool)));

    $order = $pool;

    if ($random) {
        shuffle($order);
    }

    $tie = array_flip($order);
    $load = array_fill_keys($pool, 0);
    $kept = array();

    foreach ($entries as $entry) {
        $blocked = isset($conflicts[$entry]) ? array_map('strval', (array) $conflicts[$entry]) : array();
        $kept[$entry] = array();

        foreach (isset($existing[$entry]) ? (array) $existing[$entry] : array() as $reviewer) {
            $reviewer = (string) $reviewer;

            if (in_array($reviewer, $blocked, true) || in_array($reviewer, $kept[$entry], true)) {
                continue;
            }

            $kept[$entry][] = $reviewer;

            if (isset($load[$reviewer])) {
                $load[$reviewer]++;
            }
        }
    }

    $assigned = array();
    $short = array();

    foreach ($entries as $entry) {
        $blocked = isset($conflicts[$entry]) ? array_map('strval', (array) $conflicts[$entry]) : array();
        $given = $kept[$entry];
        $needed = $n - count($given);

        while ($needed > 0) {
            $best = null;

            foreach ($pool as $reviewer) {
                if (in_array($reviewer, $given, true) || in_array($reviewer, $blocked, true)) {
                    continue;
                }

                if ($best === null
                    || $load[$reviewer] < $load[$best]
                    || ($load[$reviewer] === $load[$best] && $tie[$reviewer] < $tie[$best])
                ) {
                    $best = $reviewer;
                }
            }

            if ($best === null) {
                break;
            }

            $given[] = $best;
            $load[$best]++;
            $needed--;
        }

        $assigned[$entry] = $given;

        if ($needed > 0) {
            $short[$entry] = $needed;
        }
    }

    return array('assigned' => $assigned, 'short' => $short);
}

/**
 * Entries ranked by score, highest first; equal scores (at
 * PG_APPLICATION_SCORE_DECIMALS) by entry id. An unscored entry (null) is
 * left out.
 *
 * @param array $scores Entry id => score or null.
 * @return array List of entry ids.
 */
function pg_application_rank(array $scores)
{
    $rows = array();

    foreach ($scores as $entry => $score) {
        if ($score !== null && is_numeric($score)) {
            $rows[] = array($entry, round((float) $score, PG_APPLICATION_SCORE_DECIMALS));
        }
    }

    usort($rows, function ($a, $b) {
        if ($a[1] !== $b[1]) {
            return $a[1] < $b[1] ? 1 : -1;
        }

        if (is_numeric($a[0]) && is_numeric($b[0])) {
            return $a[0] < $b[0] ? -1 : ($a[0] > $b[0] ? 1 : 0);
        }

        return strcmp((string) $a[0], (string) $b[0]);
    });

    $ranked = array();

    foreach ($rows as $row) {
        $ranked[] = $row[0];
    }

    return $ranked;
}

/**
 * Ranks entries within each category.
 *
 * @param array $entries Entry id => array('category' => string,
 *                       'score' => number or null).
 * @return array Category => list of entry ids, categories in first-seen order.
 */
function pg_application_rank_by_category(array $entries)
{
    $groups = array();

    foreach ($entries as $entry => $row) {
        $category = isset($row['category']) ? (string) $row['category'] : '';
        $groups[$category][$entry] = isset($row['score']) ? $row['score'] : null;
    }

    $ranked = array();

    foreach ($groups as $category => $scores) {
        $ranked[$category] = pg_application_rank($scores);
    }

    return $ranked;
}

/**
 * The shortlist: by a cut-off score ('cutoff', every entry scoring at least
 * the value) or the top N ('top'). Entries tied with the last place of the
 * top N are all taken, so a tie is never split by entry order.
 *
 * @param array     $scores Entry id => score or null.
 * @param string    $mode   'cutoff' or 'top'.
 * @param float|int $value  The cut-off score or N.
 * @return array List of entry ids in rank order; empty for an unknown mode.
 */
function pg_application_shortlist(array $scores, $mode, $value)
{
    $ranked = pg_application_rank($scores);

    if ($mode === 'cutoff') {
        $floor = round((float) $value, PG_APPLICATION_SCORE_DECIMALS);
    } elseif ($mode === 'top') {
        $top = (int) $value;

        if ($top < 1 || !$ranked) {
            return array();
        }

        $last = $ranked[min($top, count($ranked)) - 1];
        $floor = round((float) $scores[$last], PG_APPLICATION_SCORE_DECIMALS);
    } else {
        return array();
    }

    $list = array();

    foreach ($ranked as $entry) {
        if (round((float) $scores[$entry], PG_APPLICATION_SCORE_DECIMALS) >= $floor) {
            $list[] = $entry;
        }
    }

    return $list;
}
