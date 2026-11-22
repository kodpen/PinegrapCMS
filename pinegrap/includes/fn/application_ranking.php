<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// An application programme's entries once they are scored: the reviews of
// the current round combined into each entry's mean and corrected mean, the
// "needs consensus" flag and its settling, the ranking per category, the
// shortlist cut and moving the shortlist into the next round. Loaded with
// require_once by the programme's screen and by "My reviews"; the pure
// rules it builds on are in application_review.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The ranking settings of a programme's round, with their defaults: the
 * spread above which an entry needs consensus (null: never), the score that
 * ranks ('raw' or 'corrected') and the shortlist cut (mode '' when none).
 *
 * @param array $round one item of the programme's rounds JSON
 * @return array array('spread' => float|null, 'rank_by' => string,
 *               'cut_mode' => ''|'cutoff'|'top', 'cut_value' => float|int)
 */
function pg_application_round_settings(array $round)
{
    $spread = $round['spread'] ?? null;
    $cut = is_array($round['cut'] ?? null) ? $round['cut'] : array();
    $mode = in_array($cut['mode'] ?? '', array('cutoff', 'top'), true) ? $cut['mode'] : '';

    return array(
        'spread'    => (is_numeric($spread) && ($spread >= 0)) ? (float) $spread : null,
        'rank_by'   => (($round['rank_by'] ?? '') === 'corrected') ? 'corrected' : 'raw',
        'cut_mode'  => $mode,
        'cut_value' => ($mode === 'top') ? (int) $cut['value'] : (($mode === 'cutoff') ? (float) $cut['value'] : 0),
    );
}

/**
 * The Set-up tab's two ranking fields of a round read: the consensus bound
 * ('' for none, else 0 to 9 with up to two decimals) and the score that
 * ranks.
 *
 * @param array $round the posted round
 * @return array|false array('spread' => float|null, 'rank_by' => string);
 *                     false for a bound that is not such a number
 */
function pg_application_round_ranking_input(array $round)
{
    $spread = is_string($round['spread'] ?? null) ? str_replace(',', '.', trim($round['spread'])) : '';
    $rank_by = (($round['rank_by'] ?? '') === 'corrected') ? 'corrected' : 'raw';

    if ($spread === '') {
        return array('spread' => null, 'rank_by' => $rank_by);
    }

    if (!preg_match('/^[0-9](\.[0-9]{1,2})?$/', $spread)) {
        return false;
    }

    return array('spread' => (float) $spread, 'rank_by' => $rank_by);
}

/**
 * The shortlist cut as typed on the Entries tab: a cut-off score (1 to 10,
 * up to two decimals) or a top N (a whole number from 1).
 *
 * @param mixed $mode  'cutoff' or 'top'
 * @param mixed $value
 * @return array|false array('mode', 'value'); false when it cannot be used
 */
function pg_application_cut_input($mode, $value)
{
    $value = is_string($value) ? str_replace(',', '.', trim($value)) : '';

    if ($mode === 'cutoff' && preg_match('/^[0-9]{1,2}(\.[0-9]{1,2})?$/', $value) && ((float) $value >= 1) && ((float) $value <= 10)) {
        return array('mode' => 'cutoff', 'value' => (float) $value);
    }

    if ($mode === 'top' && preg_match('/^[0-9]{1,5}$/', $value) && ((int) $value >= 1)) {
        return array('mode' => 'top', 'value' => (int) $value);
    }

    return false;
}

/**
 * Keeps each round's shortlist cut across a Set-up save: the cut is set on
 * the Entries tab, so the Set-up form does not post it.
 *
 * @param array $stored the stored rounds
 * @param array $posted the rounds read from the Set-up form
 * @return array $posted with each round's stored 'cut'
 */
function pg_application_rounds_keep_cut(array $stored, array $posted)
{
    foreach ($posted as $i => $round) {
        if (is_array($stored[$i]['cut'] ?? null)) {
            $posted[$i]['cut'] = $stored[$i]['cut'];
        }
    }

    return $posted;
}

/**
 * Combines a round's sent reviews into each entry's scores: the mean of the
 * weighted totals, the corrected mean (each reviewer's totals evened out
 * against their own mean and spread, pg_application_leniency_correct(),
 * from that reviewer's fifth sent review), the spread and whether it needs
 * consensus. The spread is taken on the score that ranks, so with the
 * corrected mean a strict and a lenient reviewer who agree on the order do
 * not flag an entry.
 *
 * @param array          $entry_ids List of entry ids.
 * @param array          $reviews   List of array('entry', 'reviewer', 'total'),
 *                                  the sent reviews of the round.
 * @param float|int|null $bound     The round's consensus bound.
 * @param string         $rank_by   'raw' or 'corrected'.
 * @return array Entry id => array('done' => int, 'raw' => float|null,
 *               'corrected' => float|null, 'spread' => float,
 *               'needs' => bool); null scores for an entry with no review.
 */
function pg_application_combine(array $entry_ids, array $reviews, $bound, $rank_by = 'raw')
{
    $known = array_flip(array_map('strval', $entry_ids));
    $scored = array();

    foreach ($reviews as $review) {
        if (isset($known[(string) $review['entry']])) {
            $scored[] = array('reviewer' => $review['reviewer'], 'entry' => $review['entry'], 'score' => (float) $review['total']);
        }
    }

    $raw = array();
    $corrected = array();

    foreach (pg_application_leniency_correct($scored) as $review) {
        $raw[(string) $review['entry']][] = $review['score'];
        $corrected[(string) $review['entry']][] = $review['corrected'];
    }

    $combined = array();

    foreach ($entry_ids as $id) {
        $totals = $raw[(string) $id] ?? array();
        $ranking = ($rank_by === 'corrected') ? ($corrected[(string) $id] ?? array()) : $totals;

        $combined[$id] = array(
            'done'      => count($totals),
            'raw'       => $totals ? pg_application_mean($totals) : null,
            'corrected' => $totals ? pg_application_mean($corrected[(string) $id]) : null,
            'spread'    => pg_application_spread($ranking),
            'needs'     => pg_application_needs_consensus($ranking, $bound),
        );
    }

    return $combined;
}

/**
 * An entry's stored consensus state after its scores changed: a settled
 * entry (2) stays settled, a flagged one needs consensus (1), else none (0).
 *
 * @param bool $needs  pg_application_combine()'s flag
 * @param int  $stored application_entries.consensus
 * @return int
 */
function pg_application_consensus_state($needs, $stored)
{
    if ((int) $stored === 2) {
        return 2;
    }

    return $needs ? 1 : 0;
}

/**
 * The round's entries ranked per category by the chosen score, with the
 * shortlist the cut gives in each category. Only eligible or let in entries
 * are ranked; an entry with no sent review is not ranked.
 *
 * @param array  $entries Entry id => array('category', 'eligible', 'raw',
 *                        'corrected').
 * @param string $rank_by 'raw' or 'corrected'
 * @param string $mode    the cut: '', 'cutoff' or 'top' (per category)
 * @param mixed  $value
 * @return array Category => array('ranked' => list of entry ids,
 *               'shortlist' => list of entry ids), categories in name order.
 */
function pg_application_ranking(array $entries, $rank_by, $mode, $value)
{
    $groups = array();

    foreach ($entries as $id => $entry) {
        $category = (string) ($entry['category'] ?? '');

        if (!isset($groups[$category])) {
            $groups[$category] = array();
        }

        if (in_array((int) ($entry['eligible'] ?? 0), array(1, 3), true)) {
            $groups[$category][$id] = ($rank_by === 'corrected') ? $entry['corrected'] : $entry['raw'];
        }
    }

    ksort($groups, SORT_STRING);
    $ranking = array();

    foreach ($groups as $category => $scores) {
        $ranking[$category] = array(
            'ranked'    => pg_application_rank($scores),
            'shortlist' => ($mode !== '') ? pg_application_shortlist($scores, $mode, $value) : array(),
        );
    }

    return $ranking;
}

/**
 * Combines the current round's sent reviews and writes each entry's mean,
 * corrected mean, spread (all times 100) and consensus state where they
 * changed.
 *
 * @param array $program decoded
 * @return array Entry id => the entry row (form_id, reference_code, category,
 *               eligible, consensus, shortlisted) with pg_application_combine()'s
 *               values added.
 */
function pg_application_scores_refresh(array $program)
{
    $round = max(1, (int) $program['round']);
    $settings = pg_application_round_settings($program['rounds'][$round - 1] ?? array());
    $entries = array();

    foreach (_pg_application_rows("SELECT application_entries.id, application_entries.form_id, application_entries.category, application_entries.eligible,
            application_entries.score_raw, application_entries.score_adjusted, application_entries.spread, application_entries.consensus, application_entries.shortlisted,
            forms.reference_code
        FROM application_entries LEFT JOIN forms ON forms.id = application_entries.form_id
        WHERE application_entries.program_id = ? AND application_entries.round = ? ORDER BY application_entries.id", array((int) $program['id'], $round)) as $row) {
        $entries[(int) $row['id']] = $row;
    }

    $reviews = array();

    foreach (_pg_application_rows("SELECT entry_id, reviewer_id, total FROM application_reviews WHERE program_id = ? AND round = ? AND status = 2", array((int) $program['id'], $round)) as $row) {
        $reviews[] = array('entry' => (int) $row['entry_id'], 'reviewer' => (int) $row['reviewer_id'], 'total' => ((int) $row['total']) / 100);
    }

    foreach (pg_application_combine(array_keys($entries), $reviews, $settings['spread'], $settings['rank_by']) as $id => $combined) {
        $row = $entries[$id];
        $consensus = pg_application_consensus_state($combined['needs'], $row['consensus']);
        $stored = array((int) round(($combined['raw'] ?? 0) * 100), (int) round(($combined['corrected'] ?? 0) * 100), (int) round($combined['spread'] * 100), $consensus);

        if ($stored !== array((int) $row['score_raw'], (int) $row['score_adjusted'], (int) $row['spread'], (int) $row['consensus'])) {
            db_q("UPDATE application_entries SET score_raw = ?, score_adjusted = ?, spread = ?, consensus = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ?", array($stored[0], $stored[1], $stored[2], $stored[3], $id));
        }

        $entries[$id] = array_merge($row, $combined, array('consensus' => $consensus, 'eligible' => (int) $row['eligible'], 'shortlisted' => (int) $row['shortlisted']));
    }

    return $entries;
}

/**
 * Applies a shortlist cut to the current round: keeps the cut on the round,
 * marks the entries it takes in each category shortlisted and the others
 * not. An audit_log line on the programme.
 *
 * @param array $program decoded
 * @param array $cut     pg_application_cut_input()
 * @return int how many entries are shortlisted
 */
function pg_application_cut_apply(array $program, array $cut)
{
    $round = max(1, (int) $program['round']);

    if (!isset($program['rounds'][$round - 1])) {
        return 0;
    }

    $rounds = $program['rounds'];
    $rounds[$round - 1]['cut'] = $cut;
    $settings = pg_application_round_settings($rounds[$round - 1]);
    $entries = pg_application_scores_refresh($program);
    $listed = array();

    foreach (pg_application_ranking($entries, $settings['rank_by'], $cut['mode'], $cut['value']) as $group) {
        $listed = array_merge($listed, $group['shortlist']);
    }

    db_q("UPDATE application_programs SET rounds = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ?", array(json_encode($rounds, JSON_UNESCAPED_UNICODE), (int) $program['id']));
    db_q("UPDATE application_entries SET shortlisted = 0 WHERE program_id = ? AND round = ? AND decision = 0", array((int) $program['id'], $round));

    if ($listed) {
        db("UPDATE application_entries SET shortlisted = 1 WHERE program_id = '" . (int) $program['id'] . "' AND round = '" . $round . "' AND id IN (" . implode(',', array_map('intval', $listed)) . ")");
    }

    pg_audit('shortlist', 'application_program', (int) $program['id'], array(), array('round' => $round, 'cut' => $cut, 'shortlisted' => count($listed)), $program['name']);

    return count($listed);
}

/**
 * Settles an entry that needs consensus, with the manager's note, which is
 * kept in the entry's audit_log line.
 *
 * @param array  $program decoded
 * @param int    $entry_id
 * @param string $note
 * @return bool false for an empty note or an entry of this programme that
 *              does not need consensus
 */
function pg_application_consensus_settle(array $program, $entry_id, $note)
{
    $note = mb_substr(trim((string) $note), 0, 1000);

    if ($note === '') {
        return false;
    }

    $ok = db_q("UPDATE application_entries SET consensus = 2, updated_at = UNIX_TIMESTAMP() WHERE id = ? AND program_id = ? AND consensus = 1", array((int) $entry_id, (int) $program['id']));

    if (!$ok || (mysqli_affected_rows(db::$con) < 1)) {
        return false;
    }

    pg_audit('consensus', 'application_entry', (int) $entry_id, array('consensus' => 1), array('consensus' => 2, 'note' => $note));

    return true;
}

/**
 * Whether the programme can move its shortlist into the next round: it is
 * open or closed, a next round is set up and the current round has a
 * shortlist.
 *
 * @param array $program     decoded
 * @param int   $shortlisted the current round's shortlisted entries
 * @return bool
 */
function pg_application_next_round_ready(array $program, $shortlisted)
{
    return ((int) $program['status'] > 0) && isset($program['rounds'][max(1, (int) $program['round'])]) && ((int) $shortlisted > 0);
}

/**
 * Moves the shortlist into the next round: the shortlisted entries change
 * round with their scores and flags cleared, to be given out to the round's
 * reviewers with its own criteria; the others stay in the round they ended
 * in, and every review keeps its round. An audit_log line on the programme.
 *
 * @param array $program decoded
 * @return int how many entries moved; 0 when it cannot be done
 */
function pg_application_next_round(array $program)
{
    $round = max(1, (int) $program['round']);
    $shortlisted = (int) db_value("SELECT COUNT(*) FROM application_entries WHERE program_id = '" . (int) $program['id'] . "' AND round = '" . $round . "' AND shortlisted = 1");

    if (!pg_application_next_round_ready($program, $shortlisted)) {
        return 0;
    }

    db_q("UPDATE application_programs SET round = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ? AND round = ?", array($round + 1, (int) $program['id'], $round));

    if (mysqli_affected_rows(db::$con) < 1) {
        return 0;
    }

    db_q("UPDATE application_entries SET round = ?, shortlisted = 0, score_raw = 0, score_adjusted = 0, spread = 0, consensus = 0, updated_at = UNIX_TIMESTAMP()
        WHERE program_id = ? AND round = ? AND shortlisted = 1", array($round + 1, (int) $program['id'], $round));
    pg_audit('next_round', 'application_program', (int) $program['id'], array('round' => $round), array('round' => $round + 1, 'entries' => $shortlisted), $program['name']);

    return $shortlisted;
}
