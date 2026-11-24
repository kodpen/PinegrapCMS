<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The store of a prototype share link asking approval (2026.11.0, 11.0.276):
 * the link's five approval columns (approval, publish_rule, approver_name,
 * approver_email, answer_by) and the prototype_verdicts table, one row per
 * "Approve this page" or "Request changes" a guest sent. The rules a verdict
 * is checked and read by are pure and live in prototype_approval.php.
 *
 * A verdict is deleted with its design (pg_proto_comments_delete_design())
 * and with its link's comments (the Share prototype dialog's Delete
 * comments, pg_proto_comments_delete_link()): a later design or link may be
 * given the same id.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Does the database carry verdicts and the links' approval columns
 * (2026.11.0, 11.0.276)?
 *
 * @return bool
 */
function pg_proto_approval_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('prototype_verdicts', 'fingerprint') && pg_schema_has('prototype_links', 'answer_by');
    }

    return $ready;
}

/**
 * A link row's approval settings as the code reads them: a link written
 * before the step, or a column holding nothing, asks nothing. Pure.
 *
 * @param array $link prototype_links row
 * @return array approval (bool), publish_rule (bool), approver_name,
 *               approver_email, answer_by (YYYY-MM-DD or '')
 */
function pg_proto_link_approval($link)
{
    $link = is_array($link) ? $link : array();
    $answer_by = isset($link['answer_by']) ? (string) $link['answer_by'] : '';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $answer_by) || ($answer_by === '0000-00-00')) {
        $answer_by = '';
    }

    $approval = !empty($link['approval']);

    return array(
        'approval'       => $approval,
        'publish_rule'   => $approval && !empty($link['publish_rule']),
        'approver_name'  => isset($link['approver_name']) ? (string) $link['approver_name'] : '',
        'approver_email' => isset($link['approver_email']) ? (string) $link['approver_email'] : '',
        'answer_by'      => $answer_by,
    );
}

/**
 * Whether a link asks for approval: its column is on and the store is there.
 *
 * @param array $link prototype_links row
 * @return bool
 */
function pg_proto_link_asks_approval($link)
{
    $approval = pg_proto_link_approval($link);

    return $approval['approval'] && pg_proto_approval_ready();
}

/**
 * A node fingerprint map as the column keeps it (JSON, node id => sha1), and
 * back. A map that is not one reads as empty. Pure.
 *
 * @param array $nodes pg_proto_node_fingerprints()
 * @return string
 */
function pg_proto_verdict_nodes_encode($nodes)
{
    $clean = array();

    foreach (is_array($nodes) ? $nodes : array() as $id => $hash) {
        if (is_string($id) && pg_proto_comment_node_shape($id) && is_string($hash) && preg_match('/^[0-9a-f]{40}$/D', $hash)) {
            $clean[$id] = $hash;
        }
    }

    return $clean ? (string) json_encode($clean) : '{}';
}

/**
 * The node fingerprints a verdict row keeps. Pure.
 *
 * @param array $row prototype_verdicts row
 * @return array node id => sha1
 */
function pg_proto_verdict_nodes($row)
{
    $json = (is_array($row) && isset($row['node_fingerprints'])) ? (string) $row['node_fingerprints'] : '';
    $nodes = ($json !== '') ? json_decode($json, true) : null;
    $clean = array();

    foreach (is_array($nodes) ? $nodes : array() as $id => $hash) {
        if (is_string($id) && pg_proto_comment_node_shape($id) && is_string($hash) && preg_match('/^[0-9a-f]{40}$/D', $hash)) {
            $clean[$id] = $hash;
        }
    }

    return $clean;
}

/**
 * The row a checked verdict is written as; null when a part is missing.
 * Pure.
 *
 * @param array  $checked     pg_proto_verdict_check()'s answer without error
 * @param array  $link        prototype_links row (id, style_id)
 * @param int    $page_id     the draft page reviewed
 * @param string $fingerprint pg_proto_draft_fingerprint() of the draft now
 * @param array  $nodes       pg_proto_node_fingerprints() of the draft now
 * @return array|null
 */
function pg_proto_verdict_row($checked, $link, $page_id, $fingerprint, $nodes)
{
    if (!is_array($checked) || !empty($checked['error']) || !is_array($link)) {
        return null;
    }

    $verdict = isset($checked['verdict']) ? (string) $checked['verdict'] : '';
    $link_id = isset($link['id']) ? (int) $link['id'] : 0;
    $style_id = isset($link['style_id']) ? (int) $link['style_id'] : 0;

    if (!in_array($verdict, pg_proto_verdict_kinds(), true) || ($link_id <= 0) || ($style_id <= 0) || ((int) $page_id <= 0)
        || !is_string($fingerprint) || !preg_match('/^[0-9a-f]{40}$/D', $fingerprint)) {
        return null;
    }

    return array(
        'link_id'           => $link_id,
        'style_id'          => $style_id,
        'page_id'           => (int) $page_id,
        'verdict'           => $verdict,
        'author_name'       => isset($checked['name']) ? (string) $checked['name'] : '',
        'note'              => isset($checked['note']) ? (string) $checked['note'] : '',
        'fingerprint'       => $fingerprint,
        'node_fingerprints' => pg_proto_verdict_nodes_encode($nodes),
    );
}

/**
 * Writes a verdict (pg_proto_verdict_row()) and gives its id; 0 when it could
 * not be written.
 *
 * @param array $row
 * @param int   $now
 * @return int
 */
function pg_proto_verdict_save($row, $now = 0)
{
    if (!is_array($row) || !pg_proto_approval_ready()) {
        return 0;
    }

    $now = ((int) $now > 0) ? (int) $now : time();

    $ok = db_q(
        "INSERT INTO prototype_verdicts (link_id, style_id, page_id, verdict, author_name, note, fingerprint, node_fingerprints, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        array(
            (int) $row['link_id'], (int) $row['style_id'], (int) $row['page_id'], (string) $row['verdict'],
            (string) $row['author_name'], (string) $row['note'], (string) $row['fingerprint'],
            (string) $row['node_fingerprints'], $now,
        )
    );

    return $ok ? (int) mysqli_insert_id(db::$con) : 0;
}

/**
 * Every verdict of a link, by page and in the order given (idx_link_page);
 * pg_proto_verdicts_newest() picks the standing one of each page.
 *
 * @param int $link_id
 * @return array[]
 */
function pg_proto_verdicts_of_link($link_id)
{
    if (((int) $link_id <= 0) || !pg_proto_approval_ready()) {
        return array();
    }

    $rows = db_items("SELECT * FROM prototype_verdicts WHERE link_id = '" . (int) $link_id . "' ORDER BY page_id, id");

    return is_array($rows) ? $rows : array();
}

/**
 * Every verdict of a draft page, of every link, in the order given
 * (idx_page).
 *
 * @param int $page_id
 * @return array[]
 */
function pg_proto_verdicts_of_page($page_id)
{
    if (((int) $page_id <= 0) || !pg_proto_approval_ready()) {
        return array();
    }

    $rows = db_items("SELECT * FROM prototype_verdicts WHERE page_id = '" . (int) $page_id . "' ORDER BY id");

    return is_array($rows) ? $rows : array();
}

/**
 * Deletes every verdict of a link (with the link's comments).
 *
 * @param int $link_id
 * @return int how many rows were deleted
 */
function pg_proto_verdicts_delete_link($link_id)
{
    if (((int) $link_id <= 0) || !pg_proto_approval_ready()) {
        return 0;
    }

    db("DELETE FROM prototype_verdicts WHERE link_id = '" . (int) $link_id . "'");

    return max(0, (int) mysqli_affected_rows(db::$con));
}

/**
 * The statement that deletes the verdicts of designs; '' for no design.
 *
 * @param int|int[] $style_ids
 * @return string
 */
function pg_proto_verdicts_delete_design_sql($style_ids)
{
    $ids = array();

    foreach ((array) $style_ids as $id) {
        if ((int) $id > 0) {
            $ids[(int) $id] = (int) $id;
        }
    }

    return $ids ? "DELETE FROM prototype_verdicts WHERE style_id IN (" . implode(',', $ids) . ")" : '';
}

/**
 * Deletes the verdicts of designs whose style rows are being deleted (with
 * their comments).
 *
 * @param int|int[] $style_ids
 * @return int how many rows were deleted
 */
function pg_proto_verdicts_delete_design($style_ids)
{
    $sql = pg_proto_verdicts_delete_design_sql($style_ids);

    if (($sql === '') || !pg_proto_approval_ready()) {
        return 0;
    }

    db($sql);

    return max(0, (int) mysqli_affected_rows(db::$con));
}
