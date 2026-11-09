<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the risk tiers of what an agent does: 1 reading and messages,
 * 2 a reversible record change, 3 an irreversible one (a deletion, money, an
 * order's status, accounting, a user account). A channel's manager allows a
 * tier to an agent on its channel-agent row (agents.php, every tier above 1
 * off by default); a change above the allowed tier stays a proposal the
 * person applies. The tier of a change is read from the catalogue
 * (ws_change_types(), changes.php) and is pure here. A change within the
 * tier is applied at once and its ws_ai_changes row is the undo journal: a
 * member may put the record back for 7 days while nobody changed it since.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

define('WS_TIER_READ', 1);
define('WS_TIER_REVERSIBLE', 2);
define('WS_TIER_IRREVERSIBLE', 3);

/**
 * The kinds every change of which is irreversible: accounting records and
 * user accounts (an e-mail or a role changed reaches the person at once).
 *
 * @return string[]
 */
function ws_change_tier3_types()
{
    return array('erp_account', 'erp_quote', 'user');
}

/**
 * The tier of one record change. A deletion, any money field, an order's
 * status (a cancellation, a completed order) and every change of the kinds of
 * ws_change_tier3_types() are tier 3; any other change of a known kind is
 * tier 2. An unknown kind or action counts as tier 3, so nothing new runs
 * unattended before it is classed. Pure but for the catalogue.
 *
 * @param string   $type   a key of ws_change_types()
 * @param string   $action update, create, delete, add, remove, invoice
 * @param string[] $fields the fields the change writes
 * @param array|null $types the catalogue, ws_change_types() when null
 * @return int 2 or 3
 */
function ws_change_tier($type, $action, $fields = array(), $types = null)
{
    $types = ($types === null) ? ws_change_types() : $types;
    $kind = $types[(string) $type] ?? null;

    if (!$kind || !in_array((string) $action, (array) ($kind['actions'] ?? array()), true)) {
        return WS_TIER_IRREVERSIBLE;
    }

    if (in_array((string) $type, ws_change_tier3_types(), true) || in_array((string) $action, array('delete', 'invoice'), true)) {
        return WS_TIER_IRREVERSIBLE;
    }

    foreach ((array) $fields as $field) {
        $spec = $kind['fields'][(string) $field] ?? null;

        if (!$spec || ($spec[0] === 'money')) {
            return WS_TIER_IRREVERSIBLE;
        }

        if (((string) $type === 'order') && in_array((string) $field, array('status', 'cancellation_reason'), true)) {
            return WS_TIER_IRREVERSIBLE;
        }
    }

    return WS_TIER_REVERSIBLE;
}

/**
 * The tier of a whole answer: its highest change. Pure but for the catalogue.
 *
 * @param array[]    $changes each type, action, fields (field => value, or a list of names)
 * @param array|null $types
 * @return int
 */
function ws_changes_tier($changes, $types = null)
{
    $tier = WS_TIER_READ;

    foreach ((array) $changes as $change) {
        $fields = (array) ($change['fields'] ?? array());
        $names = (array_values($fields) === $fields) ? $fields : array_keys($fields);
        $tier = max($tier, ws_change_tier((string) ($change['type'] ?? ''), (string) ($change['action'] ?? 'update'), $names, $types));
    }

    return $tier;
}

/**
 * Whether an agent allowed a tier in a channel may do what needs another.
 * A row's tier out of range counts as 1. Pure.
 *
 * @param int $allowed the channel-agent row's tier
 * @param int $needed
 * @return bool
 */
function ws_agent_tier_allows($allowed, $needed)
{
    $allowed = (int) $allowed;

    if (($allowed < WS_TIER_READ) || ($allowed > WS_TIER_IRREVERSIBLE)) {
        $allowed = WS_TIER_READ;
    }

    return (int) $needed <= $allowed;
}

/**
 * How long an agent's applied change may be undone.
 */
define('WS_AGENT_UNDO_SECONDS', 7 * 86400);

/**
 * The tier of one ws_ai_changes row, from its kind, action and the names of
 * the fields it writes. Pure but for the catalogue.
 *
 * @param array      $row   ws_ai_changes (record_type, action, fields as JSON)
 * @param array|null $types
 * @return int
 */
function ws_change_row_tier($row, $types = null)
{
    $fields = json_decode((string) ($row['fields'] ?? ''), true);
    $names = array();

    foreach (is_array($fields) ? $fields : array() as $item) {
        $names[] = (string) ($item['name'] ?? '');
    }

    return ws_change_tier((string) ($row['record_type'] ?? ''), (string) ($row['action'] ?? 'update'), $names, $types);
}

/**
 * Whether the changes of one answer are applied at once: every one of them
 * is a change to a record within the tier the agent is allowed in the
 * channel, and that tier is above reading. One change above it keeps the
 * whole answer a proposal, so a person never meets half an answer. Pure but
 * for the catalogue.
 *
 * @param array[]    $rows    ws_ai_changes, pending
 * @param int        $allowed the channel-agent row's tier (1 with no row)
 * @param array|null $types
 * @return bool
 */
function ws_agent_applies_at_once($rows, $allowed, $types = null)
{
    if (empty($rows) || !ws_agent_tier_allows($allowed, WS_TIER_REVERSIBLE)) {
        return false;
    }

    foreach ((array) $rows as $row) {
        if (!ws_agent_tier_allows($allowed, ws_change_row_tier($row, $types))) {
            return false;
        }
    }

    return true;
}

/**
 * The change that puts a record back as it was before an agent's change: an
 * update written back from its before values, a new record deleted. Null when
 * the change cannot be undone: a tier 3 change (it was allowed as
 * irreversible), a kind that cannot delete what it made, or a field whose
 * value the record does not keep (nothing to compare it with). Pure but for
 * the catalogue.
 *
 * @param array      $row   ws_ai_changes
 * @param array|null $types
 * @return array|null action (update | delete), fields (name, from, to: the
 *                    value the record should hold now, the one it gets back)
 */
function ws_agent_undo_reverse($row, $types = null)
{
    $types = ($types === null) ? ws_change_types() : $types;
    $type = (string) ($row['record_type'] ?? '');
    $action = (string) ($row['action'] ?? 'update');
    $kind = $types[$type] ?? null;

    if (!$kind || ((int) ($row['record_id'] ?? 0) <= 0) || (ws_change_row_tier($row, $types) !== WS_TIER_REVERSIBLE)) {
        return null;
    }

    if ($action === 'create') {
        if (!in_array('delete', (array) ($kind['actions'] ?? array()), true)) {
            return null;
        }
    } elseif ($action !== 'update') {
        return null;
    }

    $fields = json_decode((string) ($row['fields'] ?? ''), true);
    $out = array();

    foreach (is_array($fields) ? $fields : array() as $item) {
        $spec = $kind['fields'][(string) ($item['name'] ?? '')] ?? null;

        if (!$spec || ((string) ($spec[3] ?? '') === '')) {
            return null;
        }

        $out[] = array(
            'name' => (string) $item['name'],
            'from' => $item['to'] ?? '',
            'to'   => ($action === 'create') ? '' : ($item['from'] ?? ''),
        );
    }

    if (empty($out)) {
        return null;
    }

    return array('action' => ($action === 'create') ? 'delete' : 'update', 'fields' => $out);
}

/**
 * Whether an applied change can still be undone: the agent's tier applied it,
 * it is applied and not undone, and it is less than 7 days old. Pure.
 *
 * @param array $row ws_ai_changes
 * @param int   $now
 * @return bool
 */
function ws_agent_undo_open($row, $now)
{
    return ((string) ($row['applied_by_kind'] ?? 'user') === 'app')
        && ((string) ($row['status'] ?? '') === 'applied')
        && ((int) ($row['decided_at'] ?? 0) > 0)
        && (((int) $now - (int) $row['decided_at']) <= WS_AGENT_UNDO_SECONDS);
}

/**
 * Is the undo journal there? Files land before the upgrade (11.0.174).
 *
 * @return bool
 */
function ws_agent_undo_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_ai_changes', 'applied_by_kind') && pg_schema_has('ws_ai_changes', 'undone_at');
    }

    return $ready;
}

/**
 * The tier an application's agent is allowed in a channel: the highest of its
 * allowed channel-agent rows, 1 with none. An OAuth connection and the
 * assistants have no agent row: their changes stay proposals.
 *
 * @param int    $channel_id
 * @param string $agent_key ws_ai_requests.agent (app:<id>)
 * @return int
 */
function ws_channel_agent_tier_of($channel_id, $agent_key)
{
    $parsed = function_exists('ws_proposal_agent_parse') ? ws_proposal_agent_parse($agent_key) : null;

    if (!$parsed || ($parsed['kind'] !== 'app') || ((int) $channel_id <= 0) || !ws_agents_ready()) {
        return WS_TIER_READ;
    }

    $tier = (int) db_value("SELECT MAX(ca.tier) FROM ws_channel_agents ca
        JOIN ws_agents a ON a.id = ca.agent_id
        WHERE ca.channel_id = '" . (int) $channel_id . "' AND ca.allowed = 1
            AND a.kind = 'app' AND a.app_id = '" . (int) $parsed['id'] . "'");

    return ws_agent_tier_allows($tier, $tier) ? $tier : WS_TIER_READ;
}

/**
 * Applies at once the changes an application's agent proposed in a channel
 * when the tier it is allowed there covers all of them, in the name of the
 * person it acts for, each marked as applied by the agent so a member may
 * undo it. Anything else stays a proposal on its card.
 *
 * @param array  $viewer     the person the application acts for
 * @param int    $request_id the proposal's ws_ai_requests row
 * @param string $agent_key
 * @param array  $channel
 * @return int how many were applied
 */
function ws_agent_apply_within_tier($viewer, $request_id, $agent_key, $channel)
{
    if (!ws_agent_undo_ready() || !$channel) {
        return 0;
    }

    $rows = (array) db_items("SELECT * FROM ws_ai_changes WHERE request_id = '" . (int) $request_id . "' AND status = 'pending' ORDER BY id");

    if (!ws_agent_applies_at_once($rows, ws_channel_agent_tier_of((int) $channel['id'], $agent_key))) {
        return 0;
    }

    $applied = 0;

    foreach ($rows as $row) {
        $done = ws_change_apply($viewer, (int) $row['id'], true);

        if ($done['ok']) {
            $applied++;
        }
    }

    return $applied;
}

/**
 * Puts a record back as it was before an agent's change: by a member who may
 * post in the channel and change that kind of record, within 7 days, and
 * only while the record still holds what the agent wrote. A locked decision
 * under the change says who undid it.
 *
 * @param array $viewer
 * @param int   $change_id
 * @return array ok, error, decision_id
 */
function ws_agent_change_undo($viewer, $change_id)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'decision_id' => 0);
    };

    if (!ws_changes_ready() || !ws_agent_undo_ready()) {
        return $fail(lang('Invalid request.'));
    }

    $change = db_item("SELECT * FROM ws_ai_changes WHERE id = '" . (int) $change_id . "'");

    if (!is_array($change)) {
        return $fail(lang('That proposal could not be found.'));
    }

    $channel = ((int) $change['channel_id'] > 0) ? ws_channel($change['channel_id']) : null;

    if (!$channel || !ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('You cannot post in that channel.'));
    }

    $reverse = ws_agent_undo_reverse($change);

    if (!ws_agent_undo_open($change, time()) || ($reverse === null)) {
        return $fail(lang('This change can no longer be undone.'));
    }

    $type = (string) $change['record_type'];

    if (!ws_change_allowed($viewer, $type, (int) $change['record_id'])) {
        return $fail(lang('You may not change a record of this kind.'));
    }

    if (function_exists('ws_ai_voice_change')) {
        ws_ai_voice_change($change);
    }

    // Taken first, so two clicks cannot both write the record back.
    db("UPDATE ws_ai_changes SET status = 'undone', undone_by = '" . (int) $viewer['id'] . "', undone_at = '" . time() . "'
        WHERE id = '" . (int) $change['id'] . "' AND status = 'applied' AND applied_by_kind = 'app'");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return $fail(lang('This change can no longer be undone.'));
    }

    $release = function () use ($change) {
        db("UPDATE ws_ai_changes SET status = 'applied', undone_by = 0, undone_at = 0 WHERE id = '" . (int) $change['id'] . "' AND status = 'undone'");
    };

    $back = $change;
    $back['action'] = $reverse['action'];
    $back['fields'] = json_encode($reverse['fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $laid = ws_change_apply_record_check($viewer, $back);

    if (!$laid['ok']) {
        $release();

        return $fail($laid['stale'] ? lang('The record changed after this change, so it cannot be undone.') : $laid['error']);
    }

    $done = ws_change_write($viewer, $type, $reverse['action'], $laid['record'], ($reverse['action'] === 'delete') ? array() : $laid['values'], false);

    if (!$done['ok']) {
        $release();

        return $fail($done['error']);
    }

    $types = ws_change_types();
    $proposer = function_exists('ws_change_proposer') ? ws_change_proposer($change) : '';

    if ($reverse['action'] === 'delete') {
        $tag = '“' . ws_change_record_name($type, $laid['record']) . '” (' . $types[$type]['label'] . ' #' . (int) $change['record_id'] . ')';
        $body = lang(array('string' => 'Undone: {var:1}, which {var:2} added, was taken away.', 'vars' => array($tag, $proposer)));
    } else {
        $tag = ($types[$type]['tag'] !== '')
            ? '<#' . $types[$type]['tag'] . ':' . (int) $change['record_id'] . '>'
            : '“' . ws_change_record_name($type, $laid['record']) . '” (' . $types[$type]['label'] . ')';
        $body = lang(array('string' => 'Undone: {var:1} is back as it was before {var:2} changed it.', 'vars' => array($tag, $proposer)));
    }

    $sent = ws_message_send($viewer, $channel, mb_substr($body, 0, WS_MESSAGE_MAX), array('kind' => 'decision', 'parent_id' => (int) $change['message_id']));
    $decision_id = $sent['ok'] ? (int) $sent['message_id'] : 0;

    if ($decision_id > 0) {
        db("UPDATE ws_messages SET locked = 1 WHERE id = '" . $decision_id . "'");

        if (function_exists('ws_thread_copy_sync')) {
            ws_thread_copy_sync($decision_id);
        }
    }

    ws_message_touch($change['message_id']);

    return array('ok' => true, 'error' => '', 'decision_id' => $decision_id);
}
