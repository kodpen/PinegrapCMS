<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// An application programme's reviewers (application_reviewers) and the run
// that gives its entries out to them (application_reviews): who is in which
// category's pool, adding and removing a reviewer, the conflicts declared.
// Loaded with require_once by the screens that use it; the pure assignment
// rule is pg_application_assign() in application_review.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_picker.php');

/**
 * A reviewer's pool as stored: null for every category, else the list of
 * category values.
 *
 * @param mixed $json application_reviewers.categories
 * @return array|null
 */
function pg_application_reviewer_categories($json)
{
    $value = is_string($json) ? json_decode($json, true) : null;

    if (!is_array($value)) {
        return null;
    }

    return array_values(array_unique(array_filter(array_map('strval', $value), 'strlen')));
}

/**
 * Whether a reviewer serves an entry's category: one with no categories
 * serves every one, and a programme with no categories puts everyone in one
 * pool.
 *
 * @param array|null $categories     pg_application_reviewer_categories()
 * @param string     $category       the entry's category
 * @param bool       $has_categories the programme groups its entries
 * @return bool
 */
function pg_application_reviewer_in_pool($categories, $category, $has_categories)
{
    return !$has_categories || ($categories === null) || in_array((string) $category, $categories, true);
}

/**
 * The assignment run of a round worked out: which entry gets which reviewer
 * and which unstarted assignments go. Each category's entries get N
 * reviewers from that category's pool (pg_application_assign()), a reviewer
 * in several pools carrying their load from one to the next. Started and
 * sent reviews are always kept; an unstarted one is kept while its reviewer
 * is still in the entry's pool; a conflict is never given again.
 *
 * @param array $entries        list of array('id', 'category'), the round's
 *                              eligible entries
 * @param array $reviewers      list of array('id', 'categories' => decoded,
 *                              'revoked_at')
 * @param array $reviews        list of the round's application_reviews rows
 *                              (id, entry_id, reviewer_id, status)
 * @param int   $n              reviewers per entry
 * @param bool  $has_categories the programme groups its entries
 * @param bool  $random         break load ties at random
 * @return array array('inserts' => list of array(entry id, reviewer id),
 *               'deletes' => list of review ids, 'short' => entry id =>
 *               reviewers it lacks, 'entries' => entries getting someone new,
 *               'reviewers' => reviewers in a pool)
 */
function pg_application_assignment_plan(array $entries, array $reviewers, array $reviews, $n, $has_categories, $random = true)
{
    $active = array();

    foreach ($reviewers as $reviewer) {
        if ((int) ($reviewer['revoked_at'] ?? 0) === 0) {
            $active[(string) (int) $reviewer['id']] = $reviewer['categories'] ?? null;
        }
    }

    $category_of = array();
    $groups = array();

    foreach ($entries as $entry) {
        $id = (int) $entry['id'];
        $category = $has_categories ? (string) $entry['category'] : '';
        $category_of[$id] = $category;
        $groups[$category][] = $id;
    }

    $existing = array();
    $conflicts = array();
    $unstarted = array();

    foreach ($reviews as $review) {
        $entry = (int) $review['entry_id'];
        $reviewer = (string) (int) $review['reviewer_id'];
        $status = (int) $review['status'];

        if (!isset($category_of[$entry])) {
            continue;
        }

        if ($status === 3) {
            $conflicts[$entry][] = $reviewer;
        } elseif ($status > 0) {
            $existing[$entry][] = $reviewer;
        } else {
            $unstarted[$entry][$reviewer] = (int) $review['id'];

            if (isset($active[$reviewer]) && pg_application_reviewer_in_pool($active[$reviewer], $category_of[$entry], $has_categories)) {
                $existing[$entry][] = $reviewer;
            }
        }
    }

    ksort($groups, SORT_STRING);
    $load = array();
    $plan = array('inserts' => array(), 'deletes' => array(), 'short' => array(), 'entries' => 0, 'reviewers' => count($active));

    foreach ($groups as $category => $ids) {
        $pool = array();

        foreach ($active as $reviewer => $categories) {
            if (pg_application_reviewer_in_pool($categories, $category, $has_categories)) {
                $pool[] = (string) $reviewer;
            }
        }

        $result = pg_application_assign($ids, $pool, $n, $existing, $conflicts, $random, $load);

        foreach ($result['assigned'] as $entry => $given) {
            $new = false;

            foreach ($given as $reviewer) {
                $load[$reviewer] = ($load[$reviewer] ?? 0) + 1;

                if (!isset($unstarted[$entry][$reviewer]) && !in_array($reviewer, $existing[$entry] ?? array(), true)) {
                    $plan['inserts'][] = array((int) $entry, (int) $reviewer);
                    $new = true;
                }
            }

            foreach ($unstarted[$entry] ?? array() as $reviewer => $review_id) {
                if (!in_array((string) $reviewer, $given, true)) {
                    $plan['deletes'][] = $review_id;
                }
            }

            $plan['entries'] += $new ? 1 : 0;
        }

        $plan['short'] += $result['short'];
    }

    return $plan;
}

/**
 * A reviewer's name as the panel shows it: an outside juror's own, a panel
 * user's "First Last (username)".
 *
 * @param array $row application_reviewers row with username, first_name,
 *                   last_name of its user
 * @return string
 */
function pg_application_reviewer_name(array $row)
{
    if ((int) $row['user_id'] === 0) {
        return (string) $row['name'];
    }

    $text = pg_user_picker_text($row['first_name'] ?? '', $row['last_name'] ?? '', $row['username'] ?? '');

    return ($text !== '') ? $text : '#' . (int) $row['user_id'] . ' ' . lang('(deleted)');
}

/**
 * The choices of a programme's category field: value => label.
 *
 * @param array $program
 * @return array
 */
function pg_application_category_choices(array $program)
{
    $choices = array();

    if ((int) $program['category_field_id'] > 0) {
        foreach (_pg_application_rows("SELECT label, value FROM form_field_options WHERE form_field_id = ? ORDER BY sort_order, id", array((int) $program['category_field_id'])) as $row) {
            $value = mb_substr((string) $row['value'], 0, 100);
            $choices[$value] = ((string) $row['label'] !== '') ? (string) $row['label'] : $value;
        }
    }

    return $choices;
}

/**
 * A programme's reviewers still in it, each with its name, its pool and
 * its assigned and sent reviews of the current round.
 *
 * @param array $program
 * @return array list of rows with 'display', 'categories', 'assigned', 'done'
 */
function pg_application_reviewers(array $program)
{
    $rows = _pg_application_rows("SELECT application_reviewers.*, user.user_username AS username, contacts.first_name, contacts.last_name,
            (SELECT COUNT(*) FROM application_reviews WHERE application_reviews.reviewer_id = application_reviewers.id AND application_reviews.round = ? AND application_reviews.status IN (0, 1, 2)) AS assigned,
            (SELECT COUNT(*) FROM application_reviews WHERE application_reviews.reviewer_id = application_reviewers.id AND application_reviews.round = ? AND application_reviews.status = 2) AS done
        FROM application_reviewers
        LEFT JOIN user ON user.user_id = application_reviewers.user_id AND application_reviewers.user_id > 0
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE application_reviewers.program_id = ? AND application_reviewers.revoked_at = 0
        ORDER BY application_reviewers.id", array((int) $program['round'], (int) $program['round'], (int) $program['id']));

    foreach ($rows as &$row) {
        $row['display'] = pg_application_reviewer_name($row);
        $row['categories'] = pg_application_reviewer_categories($row['categories']);
        $row['assigned'] = (int) $row['assigned'];
        $row['done'] = (int) $row['done'];
    }

    unset($row);

    return $rows;
}

/**
 * Adds a reviewer to a programme, a panel user by id or an outside juror by
 * name and e-mail. Adding one who is already there (or was removed) puts
 * them back with the categories given.
 *
 * @param array      $program
 * @param int        $user_id    0 for an outside juror
 * @param string     $name       an outside juror's
 * @param string     $email      an outside juror's
 * @param array|null $categories null for every category
 * @return int the reviewer id, 0 on failure
 */
function pg_application_reviewer_add(array $program, $user_id, $name, $email, $categories)
{
    $user_id = (int) $user_id;
    $name = ($user_id > 0) ? '' : mb_substr(trim((string) $name), 0, 150);
    $email = ($user_id > 0) ? '' : mb_strtolower(mb_substr(trim((string) $email), 0, 190));
    $json = ($categories === null) ? null : json_encode(array_values($categories), JSON_UNESCAPED_UNICODE);

    if (($user_id === 0) && (($name === '') || ($email === ''))) {
        return 0;
    }

    $ok = db_q("INSERT INTO application_reviewers (program_id, user_id, name, email, categories, created_at) VALUES (?, ?, ?, ?, ?, UNIX_TIMESTAMP())
        ON DUPLICATE KEY UPDATE name = VALUES(name), categories = VALUES(categories), revoked_at = 0", array((int) $program['id'], $user_id, $name, $email, $json));

    if (!$ok) {
        return 0;
    }

    $id = (int) db_value("SELECT id FROM application_reviewers WHERE program_id = '" . (int) $program['id'] . "' AND user_id = '" . $user_id . "' AND email = '" . e($email) . "'");

    if ($id) {
        pg_audit('reviewer_add', 'application_program', (int) $program['id'], array(), array('reviewer_id' => $id, 'user_id' => $user_id, 'categories' => $categories), $program['name']);
    }

    return $id;
}

/**
 * Takes a reviewer off a programme: the entries they have not sent go back
 * to be given out again; the reviews they sent and the conflicts they
 * declared stay, and so does their row, marked removed. One with neither is
 * deleted.
 *
 * @param array $program
 * @param int   $reviewer_id
 * @return bool false when the reviewer is not in this programme
 */
function pg_application_reviewer_remove(array $program, $reviewer_id)
{
    $reviewer_id = (int) $reviewer_id;

    if (!(int) db_value("SELECT COUNT(*) FROM application_reviewers WHERE id = '" . $reviewer_id . "' AND program_id = '" . (int) $program['id'] . "' AND revoked_at = 0")) {
        return false;
    }

    db_q("DELETE FROM application_reviews WHERE reviewer_id = ? AND status IN (0, 1)", array($reviewer_id));

    if ((int) db_value("SELECT COUNT(*) FROM application_reviews WHERE reviewer_id = '" . $reviewer_id . "'")) {
        db_q("UPDATE application_reviewers SET revoked_at = UNIX_TIMESTAMP(), token_hash = NULL WHERE id = ?", array($reviewer_id));
    } else {
        db_q("DELETE FROM application_reviewers WHERE id = ?", array($reviewer_id));
    }

    pg_audit('reviewer_remove', 'application_program', (int) $program['id'], array('reviewer_id' => $reviewer_id), array(), $program['name']);

    return true;
}

/**
 * The assignment run of the programme's current round, read from the
 * database and worked out (pg_application_assignment_plan()). Entries that
 * are eligible or let in are given out; a not eligible one gets no one.
 *
 * @param array $program
 * @param bool  $random
 * @return array the plan
 */
function pg_application_assignment_read(array $program, $random = true)
{
    $round = max(1, (int) $program['round']);
    $n = (int) ($program['rounds'][$round - 1]['reviewers'] ?? $program['reviewers_per_entry']);
    $entries = _pg_application_rows("SELECT id, category FROM application_entries WHERE program_id = ? AND round = ? AND eligible IN (1, 3) ORDER BY id", array((int) $program['id'], $round));
    $reviewers = array();

    foreach (_pg_application_rows("SELECT id, categories, revoked_at FROM application_reviewers WHERE program_id = ?", array((int) $program['id'])) as $row) {
        $row['categories'] = pg_application_reviewer_categories($row['categories']);
        $reviewers[] = $row;
    }

    $reviews = _pg_application_rows("SELECT id, entry_id, reviewer_id, status FROM application_reviews WHERE program_id = ? AND round = ?", array((int) $program['id'], $round));

    return pg_application_assignment_plan($entries, $reviewers, $reviews, max(1, $n), (int) $program['category_field_id'] > 0, $random);
}

/**
 * Gives the current round's entries out: writes the plan's new assignments
 * and drops the unstarted ones it no longer keeps.
 *
 * @param array $program
 * @return array the plan that was written
 */
function pg_application_assignment_run(array $program)
{
    $plan = pg_application_assignment_read($program, true);
    $round = max(1, (int) $program['round']);

    foreach ($plan['deletes'] as $review_id) {
        db_q("DELETE FROM application_reviews WHERE id = ? AND status = 0", array((int) $review_id));
    }

    foreach ($plan['inserts'] as $insert) {
        db_q("INSERT IGNORE INTO application_reviews (program_id, entry_id, reviewer_id, round, status, assigned_at) VALUES (?, ?, ?, ?, 0, UNIX_TIMESTAMP())", array((int) $program['id'], $insert[0], $insert[1], $round));
    }

    pg_audit('assign', 'application_program', (int) $program['id'], array(), array('round' => $round, 'given' => count($plan['inserts']), 'dropped' => count($plan['deletes']), 'short' => count($plan['short'])), $program['name']);

    return $plan;
}

/**
 * The conflicts declared in a programme, newest first: the reviewer, the
 * entry's submission and the reason.
 *
 * @param array $program
 * @return array list of rows with 'display', 'form_id', 'reference_code',
 *               'conflict_reason', 'round'
 */
function pg_application_conflicts(array $program)
{
    $rows = _pg_application_rows("SELECT application_reviews.round, application_reviews.conflict_reason, application_entries.form_id, forms.reference_code,
            application_reviewers.user_id, application_reviewers.name, user.user_username AS username, contacts.first_name, contacts.last_name
        FROM application_reviews
        INNER JOIN application_reviewers ON application_reviewers.id = application_reviews.reviewer_id
        INNER JOIN application_entries ON application_entries.id = application_reviews.entry_id
        LEFT JOIN forms ON forms.id = application_entries.form_id
        LEFT JOIN user ON user.user_id = application_reviewers.user_id AND application_reviewers.user_id > 0
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE application_reviews.program_id = ? AND application_reviews.status = 3
        ORDER BY application_reviews.id DESC", array((int) $program['id']));

    foreach ($rows as &$row) {
        $row['display'] = pg_application_reviewer_name($row);
    }

    unset($row);

    return $rows;
}

/**
 * The Add reviewer drawer's post read: a panel user (picked by id, or a
 * username typed by a forms manager who cannot list users) or an outside
 * juror's name and e-mail, and the categories ticked (none: every one).
 *
 * @param array $post
 * @param array $choices pg_application_category_choices()
 * @return array array('user_id', 'name', 'email', 'categories' => list or
 *               null, 'error' => text, '' when it can be added)
 */
function pg_application_reviewer_input(array $post, array $choices)
{
    $text = function ($key, $max) use ($post) {
        return is_string($post[$key] ?? null) ? mb_substr(trim($post[$key]), 0, $max) : '';
    };
    $ticked = is_array($post['reviewer_categories'] ?? null) ? array_filter($post['reviewer_categories'], 'is_string') : array();
    $categories = array_values(array_intersect(array_keys($choices), $ticked));
    $input = array('user_id' => 0, 'name' => '', 'email' => '', 'categories' => $categories ? array_map('strval', $categories) : null, 'error' => '');

    if ($text('reviewer_kind', 10) === 'juror') {
        $input['name'] = $text('reviewer_name', 150);
        $input['email'] = $text('reviewer_email', 190);

        if (($input['name'] === '') || !validate_email_address($input['email'])) {
            $input['error'] = lang('Give the outside juror a name and a valid e-mail address.');
        }

        return $input;
    }

    $user_id = (int) $text('reviewer_user_id', 11);
    $username = $text('reviewer_username', 100);

    if ($user_id > 0) {
        $user_id = (int) db_value("SELECT user_id FROM user WHERE user_id = '" . $user_id . "'" . pg_user_enabled_sql());
    } elseif ($username !== '') {
        $user_id = (int) db_value("SELECT user_id FROM user WHERE user_username = '" . e($username) . "'" . pg_user_enabled_sql());
    }

    if ($user_id <= 0) {
        $input['error'] = lang('Choose a panel user who can sign in.');
    }

    $input['user_id'] = max(0, $user_id);

    return $input;
}
