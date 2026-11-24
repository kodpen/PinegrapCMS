<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pure rules of a prototype share link asking approval: a guest approves a
 * draft page or requests changes, and the verdict is sealed to the draft as
 * it was then.
 *
 * The seal is the draft's fingerprint: a sha1 (the shape of
 * page_revisions.tree_hash) over the draft's tree and the settings a revision
 * records. Revisions record publishes only, so a draft's state has no row to
 * point to; the fingerprint is worked out from the page row on read, and a
 * verdict whose fingerprint differs from the draft's now is "for an older
 * version". Nothing is written when the draft moves.
 *
 * For round two a verdict also keeps the draft's node fingerprints: one sha1
 * per node over the node's own values (everything but its children). Two
 * such maps give the nodes changed, added and removed since the verdict. A
 * node moved elsewhere in the tree keeps its own values, so it is not
 * outlined, while the draft's fingerprint does move.
 *
 * Nothing here reads the database or the request.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Fingerprints
   --------------------------------------------------------------------------- */

/**
 * A value with every string-keyed array sorted by key, at every depth, so two
 * arrays holding the same values in another key order encode alike. Lists
 * (0..n-1 keys) keep their order: the order of a node's classes or children
 * is part of the page.
 *
 * @param mixed $value
 * @return mixed
 */
function pg_proto_approval_canonical($value)
{
    if (!is_array($value)) {
        return $value;
    }

    foreach ($value as $key => $item) {
        $value[$key] = pg_proto_approval_canonical($item);
    }

    if (array_keys($value) !== range(0, count($value) - 1)) {
        ksort($value, SORT_STRING);
    }

    return $value;
}

/**
 * The draft's fingerprint: sha1 over the tree's own JSON (as stored, the way
 * page_revisions hashes it) and the page's settings in key order. Any change
 * to the tree, a moved node included, or to a recorded setting moves it.
 *
 * @param string $tree_json the draft's page_tree_json
 * @param array  $settings  pg_page_revision_settings_from_row() of the draft
 * @return string 40 hex characters
 */
function pg_proto_draft_fingerprint($tree_json, $settings)
{
    $settings = pg_proto_approval_canonical(is_array($settings) ? $settings : array());

    return sha1(sha1((string) $tree_json) . "\n" . (string) json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * The draft's fingerprint from its page row (SELECT * FROM page). Empty for
 * no row.
 *
 * @param array|null $row
 * @return string
 */
function pg_proto_draft_fingerprint_of_row($row)
{
    if (!is_array($row)) {
        return '';
    }

    $tree_json = isset($row['page_tree_json']) ? (string) $row['page_tree_json'] : '';

    return pg_proto_draft_fingerprint($tree_json, pg_page_revision_settings_from_row($row));
}

/**
 * The fingerprint of every node of a draft tree, in the tree's order (depth
 * first): node id => sha1 of the node's own values (all keys but children,
 * in key order). A node without an id of the node shape is skipped (its
 * children are still read); a repeated id keeps its first node.
 *
 * @param array|string|null $tree the tree, or its JSON
 * @return array<string, string>
 */
function pg_proto_node_fingerprints($tree)
{
    if (is_string($tree)) {
        $tree = json_decode($tree, true);
    }

    $out = array();

    if (is_array($tree)) {
        pg_proto_node_fingerprints_walk($tree, $out, 0);
    }

    return $out;
}

/**
 * pg_proto_node_fingerprints()'s walk. The depth is bounded as the tree's
 * readers bound it, so a hostile tree cannot exhaust the stack.
 *
 * @param array $node
 * @param array $out
 * @param int   $depth
 * @return void
 */
function pg_proto_node_fingerprints_walk($node, &$out, $depth)
{
    if ($depth > 200) {
        return;
    }

    $own = $node;
    unset($own['children']);

    $id = isset($node['_id']) ? $node['_id'] : null;

    if (pg_proto_comment_node_shape($id) && !isset($out[$id])) {
        $out[$id] = sha1((string) json_encode(pg_proto_approval_canonical($own), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $child) {
            if (is_array($child)) {
                pg_proto_node_fingerprints_walk($child, $out, $depth + 1);
            }
        }
    }
}

/**
 * The nodes changed, added and removed between two node fingerprint maps.
 * Changed and added keep the later draft's order, removed the earlier's.
 *
 * @param array|null $before pg_proto_node_fingerprints() at the verdict
 * @param array|null $after  pg_proto_node_fingerprints() now
 * @return array changed, added, removed (lists of node ids)
 */
function pg_proto_nodes_diff($before, $after)
{
    $before = is_array($before) ? $before : array();
    $after  = is_array($after) ? $after : array();
    $out    = array('changed' => array(), 'added' => array(), 'removed' => array());

    foreach ($after as $id => $print) {
        if (!array_key_exists($id, $before)) {
            $out['added'][] = (string) $id;
        } elseif ((string) $before[$id] !== (string) $print) {
            $out['changed'][] = (string) $id;
        }
    }

    foreach ($before as $id => $print) {
        if (!array_key_exists($id, $after)) {
            $out['removed'][] = (string) $id;
        }
    }

    return $out;
}

/* ---------------------------------------------------------------------------
   Verdicts
   --------------------------------------------------------------------------- */

/**
 * The two verdicts a guest may give a page.
 *
 * @return string[]
 */
function pg_proto_verdict_kinds()
{
    return array('approved', 'changes_requested');
}

/**
 * A guest's verdict checked: a known verdict, the name on one line (1 to the
 * comment's name limit), the note plain (pg_proto_comment_plain()) and within
 * the comment's text limit; Request changes needs a note, Approve does not.
 *
 * @param mixed $input verdict, name, note
 * @return array error ('' | verdict | name | note), verdict, name, note
 */
function pg_proto_verdict_check($input)
{
    $out = array('error' => '', 'verdict' => '', 'name' => '', 'note' => '');

    if (!is_array($input) || !isset($input['verdict']) || !in_array($input['verdict'], pg_proto_verdict_kinds(), true)) {
        $out['error'] = 'verdict';
        return $out;
    }

    $limits = pg_proto_comment_limits();
    $name   = pg_proto_comment_plain(isset($input['name']) ? $input['name'] : '', false);
    $note   = pg_proto_comment_plain(isset($input['note']) ? $input['note'] : '', true);

    $out['verdict'] = $input['verdict'];

    if ($name === null || $name === '' || mb_strlen($name, 'UTF-8') > $limits['name']) {
        $out['error'] = 'name';
        return $out;
    }

    if ($note === null || mb_strlen($note, 'UTF-8') > $limits['text']
        || ($note === '' && $input['verdict'] === 'changes_requested')) {
        $out['error'] = 'note';
        return $out;
    }

    $out['name'] = $name;
    $out['note'] = $note;

    return $out;
}

/**
 * The verdict that stands on each page: the newest of the given rows of one
 * link (by created_at, then id). Rows without a page are skipped.
 *
 * @param array $rows rows with id, page_id, created_at
 * @return array page id => row
 */
function pg_proto_verdicts_newest($rows)
{
    $out = array();

    foreach ((array) $rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $page = isset($row['page_id']) ? (int) $row['page_id'] : 0;

        if ($page <= 0) {
            continue;
        }

        if (!isset($out[$page]) || pg_proto_verdict_is_newer($row, $out[$page])) {
            $out[$page] = $row;
        }
    }

    return $out;
}

/**
 * Whether verdict row $a was given after row $b.
 *
 * @param array $a
 * @param array $b
 * @return bool
 */
function pg_proto_verdict_is_newer($a, $b)
{
    $at = isset($a['created_at']) ? (int) $a['created_at'] : 0;
    $bt = isset($b['created_at']) ? (int) $b['created_at'] : 0;

    if ($at !== $bt) {
        return $at > $bt;
    }

    return (isset($a['id']) ? (int) $a['id'] : 0) > (isset($b['id']) ? (int) $b['id'] : 0);
}

/**
 * A page's state for one link from the verdict that stands: not_reviewed,
 * approved or changes_requested, and whether that verdict was given to an
 * older version (its fingerprint is not the draft's now).
 *
 * @param array|null $verdict     the standing row (verdict, fingerprint)
 * @param string     $fingerprint the draft's fingerprint now
 * @return array state, older
 */
function pg_proto_verdict_state($verdict, $fingerprint)
{
    if (!is_array($verdict) || !isset($verdict['verdict']) || !in_array($verdict['verdict'], pg_proto_verdict_kinds(), true)) {
        return array('state' => 'not_reviewed', 'older' => false);
    }

    $sealed = isset($verdict['fingerprint']) ? (string) $verdict['fingerprint'] : '';

    return array(
        'state' => $verdict['verdict'],
        'older' => ($sealed === '' || $sealed !== (string) $fingerprint),
    );
}

/**
 * Whether the standing verdict approves the draft as it is now: the publish
 * rule's test.
 *
 * @param array|null $verdict
 * @param string     $fingerprint
 * @return bool
 */
function pg_proto_verdict_approves($verdict, $fingerprint)
{
    $state = pg_proto_verdict_state($verdict, $fingerprint);

    return $state['state'] === 'approved' && !$state['older'];
}

/* ---------------------------------------------------------------------------
   "Answer by" and the approver's mails
   --------------------------------------------------------------------------- */

/**
 * The link's "Answer by" date checked: empty (no date), or a real
 * YYYY-MM-DD day from today on and, for a link that expires, no later than
 * the day it expires. Days are the site's (date()).
 *
 * @param mixed $value      as posted
 * @param int   $expires_at the link's expiry (0: never)
 * @param int   $now
 * @return array error ('' | date | past | after_expiry), date
 */
function pg_proto_answer_by_check($value, $expires_at, $now)
{
    if (!is_string($value)) {
        return array('error' => 'date', 'date' => '');
    }

    $value = trim($value);

    if ($value === '') {
        return array('error' => '', 'date' => '');
    }

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return array('error' => 'date', 'date' => '');
    }

    if ($value < date('Y-m-d', (int) $now)) {
        return array('error' => 'past', 'date' => '');
    }

    if ((int) $expires_at > 0 && $value > date('Y-m-d', (int) $expires_at)) {
        return array('error' => 'after_expiry', 'date' => '');
    }

    return array('error' => '', 'date' => $value);
}

/**
 * Whether the "Answer by" reminder goes today: the day before the date, with
 * pages still undecided, and not already sent today.
 *
 * @param string $answer_by YYYY-MM-DD, or '' for none
 * @param int    $now
 * @param int    $undecided pages of the link without a standing verdict
 * @param string $sent_on   the day the reminder last went (YYYY-MM-DD), or ''
 * @return bool
 */
function pg_proto_answer_by_reminder_due($answer_by, $now, $undecided, $sent_on)
{
    $answer_by = (string) $answer_by;

    if ($answer_by === '' || (int) $undecided <= 0 || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $answer_by, $m)) {
        return false;
    }

    $day_before = date('Y-m-d', mktime(12, 0, 0, (int) $m[2], (int) $m[3] - 1, (int) $m[1]));
    $today      = date('Y-m-d', (int) $now);

    return $today === $day_before && (string) $sent_on !== $today;
}

/**
 * The rate of the mail telling the approver the designer answered: one per
 * link in an hour, counted by waf_rate_exceeded() with the link's id as the
 * subject, as the bell line to the link's maker is.
 *
 * @return array limit, window (seconds)
 */
function pg_proto_answer_mail_rate()
{
    return array('limit' => 1, 'window' => 3600);
}

/**
 * Whether the answer mail goes now: the link has a valid approver address,
 * there are answers to tell of, and the hour's mail has not gone yet.
 *
 * @param string $email         the approver's address
 * @param int    $answers       the designer's answers since the last mail
 * @param bool   $rate_exceeded the hourly counter is past its limit
 * @return bool
 */
function pg_proto_answer_mail_due($email, $answers, $rate_exceeded)
{
    return (int) $answers > 0
        && !$rate_exceeded
        && filter_var((string) $email, FILTER_VALIDATE_EMAIL) !== false;
}
