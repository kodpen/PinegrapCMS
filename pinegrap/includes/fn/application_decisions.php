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
