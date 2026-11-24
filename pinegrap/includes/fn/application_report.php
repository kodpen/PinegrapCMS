<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// An application programme's report: each reviewer's progress in a round,
// the scores' spread by criterion and reviewer, and the entries by state
// per round and category; the same three tables as an Excel workbook.
// Loaded with require_once by the programme's screen.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_reviewers.php');

/**
 * A figure of the report to two decimals, null kept. Pure.
 *
 * @param float|null $value
 * @return float|null
 */
function _pg_application_report_two($value)
{
    return ($value === null) ? null : round((float) $value, 2);
}

/**
 * Each reviewer's progress in a round: reviews given (not counting
 * conflicts), started, sent, conflicts, and the mean, lowest and highest
 * weighted total of the sent ones. Pure.
 *
 * @param array $reviewers list of rows with id and display
 * @param array $reviews   application_reviews rows of the round: reviewer_id,
 *                         status, total (times 100)
 * @return array list, in the reviewers' order, of id, name, given, started,
 *               sent, conflicts, mean, lowest, highest (null with nothing sent)
 */
function pg_application_report_reviewers(array $reviewers, array $reviews)
{
    $lines = array();

    foreach ($reviewers as $reviewer) {
        $lines[(int) $reviewer['id']] = array('id' => (int) $reviewer['id'], 'name' => (string) $reviewer['display'], 'given' => 0, 'started' => 0, 'sent' => 0, 'conflicts' => 0, 'totals' => array());
    }

    foreach ($reviews as $review) {
        $id = (int) $review['reviewer_id'];

        if (!isset($lines[$id])) {
            continue;
        }

        $status = (int) $review['status'];

        if ($status === 3) {
            $lines[$id]['conflicts']++;
            continue;
        }

        $lines[$id]['given']++;

        if ($status === 1) {
            $lines[$id]['started']++;
        } elseif ($status === 2) {
            $lines[$id]['sent']++;
            $lines[$id]['totals'][] = pg_application_export_score($review['total']);
        }
    }

    foreach ($lines as &$line) {
        $line['mean'] = _pg_application_report_two(pg_application_mean($line['totals']));
        $line['lowest'] = $line['totals'] ? min($line['totals']) : null;
        $line['highest'] = $line['totals'] ? max($line['totals']) : null;
        unset($line['totals']);
    }

    unset($line);

    return array_values($lines);
}

/**
 * The spread of the sent scores of a round by criterion and reviewer: each
 * reviewer's mean per criterion, and per criterion the mean, lowest,
 * highest and standard deviation over every sent review. Only a criterion's
 * whole score counts; a reviewer with nothing sent is left out. Pure.
 *
 * @param array $criteria  the round's criteria: id, name, weight
 * @param array $reviewers list of rows with id and display
 * @param array $reviews   application_reviews rows: reviewer_id, status,
 *                         scores (the stored JSON)
 * @return array array('reviewers' => list of name and means (criterion id
 *               => float|null), 'criteria' => list of id, name, weight,
 *               count, mean, lowest, highest, deviation)
 */
function pg_application_report_criteria(array $criteria, array $reviewers, array $reviews)
{
    $names = array();

    foreach ($reviewers as $reviewer) {
        $names[(int) $reviewer['id']] = (string) $reviewer['display'];
    }

    $by_reviewer = array();
    $all = array();

    foreach ($reviews as $review) {
        $id = (int) $review['reviewer_id'];

        if (((int) $review['status'] !== 2) || !isset($names[$id])) {
            continue;
        }

        foreach (pg_application_export_review($review)['scores'] as $criterion => $score) {
            $by_reviewer[$id][$criterion][] = $score;
            $all[$criterion][] = $score;
        }
    }

    $out = array('reviewers' => array(), 'criteria' => array());

    foreach ($names as $id => $name) {
        if (!isset($by_reviewer[$id])) {
            continue;
        }

        $means = array();

        foreach ($criteria as $criterion) {
            $means[(string) $criterion['id']] = _pg_application_report_two(pg_application_mean($by_reviewer[$id][(string) $criterion['id']] ?? array()));
        }

        $out['reviewers'][] = array('name' => $name, 'means' => $means);
    }

    foreach ($criteria as $criterion) {
        $scores = $all[(string) $criterion['id']] ?? array();
        $out['criteria'][] = array(
            'id'        => (string) $criterion['id'],
            'name'      => (string) $criterion['name'],
            'weight'    => (int) $criterion['weight'],
            'count'     => count($scores),
            'mean'      => _pg_application_report_two(pg_application_mean($scores)),
            'lowest'    => $scores ? min($scores) : null,
            'highest'   => $scores ? max($scores) : null,
            'deviation' => $scores ? round(pg_application_deviation($scores), 2) : null,
        );
    }

    return $out;
}

/**
 * The entries by state, one line per round and category: entries, not
 * eligible, let in, reviewed (at least one sent review in its round),
 * shortlisted, and each decision. Pure.
 *
 * @param array $entries rows with round, category, eligible, shortlisted,
 *                       decision, sent (sent reviews in the entry's round)
 * @return array list ordered by round then category of round, category and
 *               the counts entries, not_eligible, let_in, reviewed,
 *               shortlisted, accepted, rejected, waitlisted, declined
 */
function pg_application_report_states(array $entries)
{
    $keys = array(1 => 'accepted', 2 => 'rejected', 3 => 'waitlisted', 4 => 'declined');
    $lines = array();

    foreach ($entries as $entry) {
        $round = (int) $entry['round'];
        $category = (string) $entry['category'];
        $key = $round . "\0" . $category;

        if (!isset($lines[$key])) {
            $lines[$key] = array('round' => $round, 'category' => $category, 'entries' => 0, 'not_eligible' => 0, 'let_in' => 0, 'reviewed' => 0, 'shortlisted' => 0, 'accepted' => 0, 'rejected' => 0, 'waitlisted' => 0, 'declined' => 0);
        }

        $lines[$key]['entries']++;
        $lines[$key]['not_eligible'] += ((int) $entry['eligible'] === 2) ? 1 : 0;
        $lines[$key]['let_in'] += ((int) $entry['eligible'] === 3) ? 1 : 0;
        $lines[$key]['reviewed'] += ((int) $entry['sent'] > 0) ? 1 : 0;
        $lines[$key]['shortlisted'] += ((int) $entry['shortlisted'] === 1) ? 1 : 0;

        if (isset($keys[(int) $entry['decision']])) {
            $lines[$key][$keys[(int) $entry['decision']]]++;
        }
    }

    uasort($lines, function ($a, $b) {
        return ($a['round'] - $b['round']) ?: strcmp($a['category'], $b['category']);
    });

    return array_values($lines);
}

/**
 * A heading and lines as the typed rows of pg_xlsx_write_book(): a number a
 * numeric cell, null or '' a gap, anything else text (never a formula). Pure.
 *
 * @param array $head
 * @param array $lines list of lists of cells
 * @return array row number => cells
 */
function pg_application_report_sheet(array $head, array $lines)
{
    $cell = function ($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return array('t' => 'n', 'v' => (string) $value);
        }

        return array('t' => 's', 'v' => (string) $value);
    };
    $rows = array(1 => array_map($cell, array_values($head)));

    foreach (array_values($lines) as $index => $line) {
        $rows[$index + 2] = array_map($cell, array_values($line));
    }

    return $rows;
}

/**
 * The report's three tables as the workbook's sheets: Reviewers, Scores
 * (each reviewer's mean per criterion, then a line per criterion's figures)
 * and Entries. Pure.
 *
 * @param array $report  pg_application_report()
 * @param array $choices category value => label
 * @return array list of array('name' => sheet name, 'rows' => typed rows)
 */
function pg_application_report_sheets(array $report, array $choices)
{
    $reviewers = array();

    foreach ($report['reviewers'] as $line) {
        $reviewers[] = array($line['name'], $line['given'], $line['started'], $line['sent'], $line['conflicts'], $line['mean'], $line['lowest'], $line['highest']);
    }

    $head = array(lang('Reviewer'));
    $scores = array();

    foreach ($report['spread']['criteria'] as $criterion) {
        $head[] = $criterion['name'];
    }

    foreach ($report['spread']['reviewers'] as $line) {
        $scores[] = array_merge(array($line['name']), array_values($line['means']));
    }

    $figures = array(lang('Mean') => 'mean', lang('Lowest') => 'lowest', lang('Highest') => 'highest', lang('Deviation') => 'deviation');

    foreach ($figures as $label => $key) {
        $scores[] = array_merge(array($label), array_map(function ($criterion) use ($key) {
            return $criterion[$key];
        }, $report['spread']['criteria']));
    }

    $states = array();

    foreach ($report['states'] as $line) {
        $category = $choices[$line['category']] ?? (($line['category'] !== '') ? $line['category'] : lang('No category'));
        $states[] = array($line['round'], $category, $line['entries'], $line['not_eligible'], $line['let_in'], $line['reviewed'], $line['shortlisted'], $line['accepted'], $line['rejected'], $line['waitlisted'], $line['declined']);
    }

    return array(
        array('name' => lang('Reviewers'), 'rows' => pg_application_report_sheet(array(lang('Reviewer'), lang('Given'), lang('Started'), lang('Sent'), lang('Conflicts'), lang('Mean'), lang('Lowest'), lang('Highest')), $reviewers)),
        array('name' => lang('Scores'), 'rows' => pg_application_report_sheet($head, $scores)),
        array('name' => lang('Entries'), 'rows' => pg_application_report_sheet(array(lang('Round'), lang('Category'), lang('Entries'), lang('Not eligible'), lang('Let in'), lang('Reviewed'), lang('Shortlisted'), lang('Accepted'), lang('Rejected'), lang('Waitlisted'), lang('Declined the place')), $states)),
    );
}

/**
 * A programme's report of a round: its reviewers' progress, the spread of
 * its scores, and every round's entries by state.
 *
 * @param array $program pg_application_program_get()
 * @param int   $round   1 to the programme's round
 * @return array array('reviewers' => pg_application_report_reviewers(),
 *               'spread' => pg_application_report_criteria(),
 *               'states' => pg_application_report_states())
 */
function pg_application_report(array $program, $round)
{
    $round = (int) $round;
    $reviewers = pg_application_reviewers($program);
    $reviews = _pg_application_rows("SELECT reviewer_id, status, total, scores FROM application_reviews WHERE program_id = ? AND round = ?", array((int) $program['id'], $round));
    $entries = _pg_application_rows("SELECT application_entries.round, application_entries.category, application_entries.eligible, application_entries.shortlisted, application_entries.decision,
            (SELECT COUNT(*) FROM application_reviews WHERE application_reviews.entry_id = application_entries.id AND application_reviews.round = application_entries.round AND application_reviews.status = 2) AS sent
        FROM application_entries WHERE application_entries.program_id = ?", array((int) $program['id']));

    return array(
        'reviewers' => pg_application_report_reviewers($reviewers, $reviews),
        'spread'    => pg_application_report_criteria($program['rounds'][$round - 1]['criteria'] ?? array(), $reviewers, $reviews),
        'states'    => pg_application_report_states($entries),
    );
}
