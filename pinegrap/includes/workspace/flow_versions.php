<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the published versions of a flow and a paused flow made live
 * again: the versions listed, one read with its graph, one brought back into
 * the draft (a draft save, draft_rev raised), and resuming a paused flow on
 * the version it was paused at, without publishing the draft. Resuming runs
 * the publish checks on that version with the owner's rights now, since a
 * flow paused for weeks may meet a channel they left or a time gone by.
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
 * The published versions of a flow, newest first, without their graphs.
 *
 * @param int $flow_id
 * @param int $before_n only those numbered below it; 0 for the newest
 * @param int $limit
 * @return array rows (id, n, published_by, published_at), next (the n to read below, or null)
 */
function ws_flow_versions($flow_id, $before_n = 0, $limit = 50)
{
    if (!ws_flows_ready() || ((int) $flow_id <= 0)) {
        return array('rows' => array(), 'next' => null);
    }

    $limit = max(1, min(100, (int) $limit));
    $where = "flow_id = '" . (int) $flow_id . "'" . (((int) $before_n > 0) ? " AND n < '" . (int) $before_n . "'" : '');

    $rows = (array) db_items("SELECT id, n, published_by, published_at FROM ws_flow_versions
        WHERE " . $where . " ORDER BY n DESC LIMIT " . ($limit + 1));

    $next = null;

    if (count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
        $next = (int) $rows[$limit - 1]['n'];
    }

    return array('rows' => $rows, 'next' => $next);
}

/**
 * One published version of a flow by its number.
 *
 * @param int $flow_id
 * @param int $n
 * @return array|null
 */
function ws_flow_version_numbered($flow_id, $n)
{
    if (!ws_flows_ready() || ((int) $flow_id <= 0) || ((int) $n <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_flow_versions WHERE flow_id = '" . (int) $flow_id . "' AND n = '" . (int) $n . "'");

    return is_array($row) ? $row : null;
}

/**
 * Why a flow cannot be resumed as it stands, '' when it can: only a paused
 * flow, and only one with a live version to go back to. Pure.
 *
 * @param string $status     ws_flows.status
 * @param int    $version_id ws_flows.version_id
 * @return string '' | not_paused | no_version
 */
function ws_flow_resume_refusal($status, $version_id)
{
    if ((string) $status !== 'paused') {
        return 'not_paused';
    }

    return ((int) $version_id > 0) ? '' : 'no_version';
}

/**
 * Whether the person may resume the paused flow on the version it was
 * paused at. Its owner only, as publishing: the runs act with the owner's
 * rights. The version meets the publish checks again (ws_flow_publish_check()
 * reads the draft, so it is handed the version's graph in its place): a
 * channel they no longer write in, a time already gone, a person they may no
 * longer give work to refuse it, and the owner publishes a changed draft
 * instead.
 *
 * @param array $viewer
 * @param array $flow
 * @return array ok, error, status, field, version (its ws_flow_versions row), graph
 */
function ws_flow_resume_check($viewer, $flow)
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    if (!ws_flow_share_manager($viewer, $flow)) {
        return ws_flow_refusal(lang('Only the owner of a flow can resume it.'), 403);
    }

    $refusal = ws_flow_resume_refusal($flow['status'], $flow['version_id']);

    if ($refusal === 'not_paused') {
        return ws_flow_refusal(lang('Only a paused flow can be resumed.'));
    }

    $version = ws_flow_version($flow['version_id']);

    if (($refusal === 'no_version') || !$version || ((int) $version['flow_id'] !== (int) $flow['id'])) {
        return ws_flow_refusal(lang('This flow has no published version to resume. Publish it instead.'));
    }

    $check = ws_flow_publish_check($viewer, array('draft' => (string) $version['graph']) + $flow);

    if (!$check['ok']) {
        return $check;
    }

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'version' => $version, 'graph' => $check['graph']);
}

/**
 * Makes a paused flow live again on the version it was paused at; its draft
 * stays a draft. Its anchor is written again from that version, with the
 * rule's next time, as a publish writes it. A second resume of the same
 * moment changes no row and is refused with 409.
 *
 * @param array $viewer
 * @param array $flow
 * @return array ok, error, status, field, n (the version now live)
 */
function ws_flow_resume($viewer, $flow)
{
    $check = ws_flow_resume_check($viewer, $flow);

    if (!$check['ok']) {
        return $check + array('n' => 0);
    }

    db("UPDATE ws_flows SET status = 'live', updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . time() . "'
        WHERE id = '" . (int) $flow['id'] . "' AND status = 'paused' AND version_id = '" . (int) $check['version']['id'] . "'");

    if ((int) mysqli_affected_rows(db::$con) < 1) {
        return ws_flow_refusal(lang('Only a paused flow can be resumed.'), 409) + array('n' => 0);
    }

    ws_flow_anchor_sync($viewer, $flow, $check['graph']);

    $n = (int) $check['version']['n'];

    log_activity(lang(array('string' => 'workspace flow ({var:1}) was resumed on version {var:2}', 'vars' => array($flow['name'], $n))), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'n' => $n);
}

/**
 * Whether the person may bring a published version back into the draft: the
 * draft save's own checks (whoever may change the flow, the draft_rev it was
 * read at, the size), with the version's graph as the new draft. The flow
 * stays as it is (live, paused or a draft) until it is published.
 *
 * @param array $viewer
 * @param array $flow
 * @param int   $n    the version's number
 * @param array $data draft_rev (the one the draft was read at), optional
 * @return array ws_flow_save_check() with data (what ws_flow_save() is handed) and n
 */
function ws_flow_version_restore_check($viewer, $flow, $n, $data = array())
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    if (ws_flow_access($viewer, $flow) !== 'edit') {
        return ws_flow_refusal(lang('Only the owner of a flow can change it.'), 403);
    }

    $version = ws_flow_version_numbered($flow['id'], $n);

    if (!$version) {
        return ws_flow_refusal(lang('This version of the flow does not exist.'), 404, 'n');
    }

    $save = array('graph' => (string) $version['graph']);

    if (isset($data['draft_rev'])) {
        $save['draft_rev'] = (int) $data['draft_rev'];
    }

    return ws_flow_save_check($viewer, $save, $flow) + array('data' => $save, 'n' => (int) $version['n']);
}

/**
 * Brings a published version back into the flow's draft, as a draft save:
 * draft_rev raised, and refused with 409 when the draft was saved meanwhile.
 *
 * @param array $viewer
 * @param array $flow
 * @param int   $n
 * @param array $data see ws_flow_version_restore_check()
 * @return array ws_flow_save() with n
 */
function ws_flow_version_restore($viewer, $flow, $n, $data = array())
{
    $check = ws_flow_version_restore_check($viewer, $flow, $n, $data);

    if (!$check['ok']) {
        return $check + array('flow_id' => (int) $flow['id'], 'draft_rev' => 0, 'problems' => array(), 'n' => 0);
    }

    $result = ws_flow_save($viewer, $check['data'], $flow);

    if ($result['ok']) {
        log_activity(lang(array('string' => 'workspace flow ({var:1}) draft was brought back from version {var:2}', 'vars' => array($flow['name'], $check['n']))), (string) ($_SESSION['sessionusername'] ?? ''));
    }

    return $result + array('n' => $check['n']);
}
