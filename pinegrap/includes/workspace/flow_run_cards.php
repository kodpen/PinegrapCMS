<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - flows started from a channel's composer (Add › Start a flow)
 * and the card a run started there leaves in the channel.
 *
 * The composer lists the flows the member may start in the channel: live,
 * started by hand, run or edit access, and an owner still on the staff who
 * may write in the channel (the run's tasks carry the owner's name). The
 * start itself is the flow canvas's own (ws_flow_run_start() with the
 * channel named, ws_flow_run_channel_check()), so the list and the start
 * cannot disagree for long.
 *
 * A run started there writes one system line into the channel. The line is
 * tied to its run by a ws_refs row (ref_type flow_run), which no record
 * list reads, so no column is needed; the screen draws the run's flag under
 * the line (window.PGWsFlow.runFlag()) and reads the run again while it is
 * open. A reader of the channel reads the run as every run is read: by its
 * channel (ws_flow_run_access()).
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
 * May this flow be started from the channel's composer? Live with a
 * published version, started by hand, run or edit access for the person,
 * and an owner on the staff who may write in the channel.
 *
 * @param array  $row      ws_flows row with access
 * @param string $start    ws_flow_start_kind() of its live version
 * @param bool   $owner_ok the owner is on the staff and may write in the channel
 * @return bool
 */
function ws_flow_runnable_here($row, $start, $owner_ok)
{
    return ((string) ($row['status'] ?? '') === 'live')
        && ((int) ($row['version_id'] ?? 0) > 0)
        && in_array((string) ($row['access'] ?? ''), array('run', 'edit'), true)
        && ((string) $start === 'manual')
        && $owner_ok;
}

/**
 * The flows the person may start in a channel, by name. None in a channel
 * a run cannot name (a direct message, a customer channel) or one they do
 * not read.
 *
 * @param array $viewer
 * @param int   $channel_id
 * @return array[] ws_flows rows with access
 */
function ws_flows_runnable_in($viewer, $channel_id)
{
    $channel = ws_channel((int) $channel_id);

    if (!ws_flows_ready() || !ws_flow_run_channel_allowed($channel, is_array($channel) && ws_can_read_channel($viewer, $channel), true)) {
        return array();
    }

    $rows = ws_flows_list($viewer, 'all', array('status' => 'live'));

    if (empty($rows)) {
        return array();
    }

    $starts = ws_flows_start_kinds($rows);
    $owners = array();
    $out = array();

    foreach ($rows as $row) {
        $owner_id = (int) $row['owner_id'];

        if (!isset($owners[$owner_id])) {
            $owner = ws_change_viewer_for_id($owner_id);
            $owners[$owner_id] = is_array($owner) && ws_can_schedule($owner) && ws_can_post_channel($owner, $channel);
        }

        if (ws_flow_runnable_here($row, $starts[(int) $row['id']] ?? '', $owners[$owner_id])) {
            $out[] = $row;
        }
    }

    usort($out, function ($a, $b) {
        return strcasecmp((string) $a['name'], (string) $b['name']);
    });

    return $out;
}

/**
 * The line a run started from the composer writes into its channel, tied to
 * the run so the screen draws the run's card under it.
 *
 * @param array $viewer     the starter
 * @param array $flow       ws_flows row
 * @param int   $run_id
 * @param int   $channel_id
 * @return int the message id, 0 when it could not be written
 */
function ws_flow_run_card_post($viewer, $flow, $run_id, $channel_id)
{
    $message_id = ws_message_system((int) $channel_id, lang(array('string' => '{var:1} started the flow {var:2}.',
        'vars' => array(ws_person_name($viewer['id']), ws_flow_run_card_name($flow['name'])))));

    if ($message_id > 0) {
        ws_refs_store('message', $message_id, (int) $channel_id, array(array('type' => 'flow_run', 'id' => (int) $run_id)));
    }

    return $message_id;
}

/**
 * A flow's name as plain words in a system line: the characters that would
 * make a tag, a mark or a link of it are taken out.
 *
 * @param string $name
 * @return string
 */
function ws_flow_run_card_name($name)
{
    return trim(str_replace(array('<', '>', '*', '_', '`', '[', ']', '#', '~'), '', (string) $name));
}

/**
 * What the screen draws under a run's line: the run as the flow screens read
 * it (without its boxes), the flow's name, and whether the reader may open
 * the run on the canvas (any access to the flow; a reader who reaches the
 * run only by its channel sees the flag alone).
 *
 * @param array  $run         ws_api_flow_run_present() answer
 * @param string $flow_name
 * @param string $flow_access ws_flow_access() of the reader
 * @return array run, flow_id, flow_name, can_open
 */
function ws_flow_run_card_payload($run, $flow_name, $flow_access)
{
    unset($run['steps'], $run['graph']);

    return array(
        'run'       => $run,
        'flow_id'   => (int) $run['flow_id'],
        'flow_name' => (string) $flow_name,
        'can_open'  => ((string) $flow_access !== ''),
    );
}

/**
 * The run cards of a page of messages, as the reader reaches each run: a
 * run they may not read (or one deleted with its flow) has none, and its
 * line stays a plain line.
 *
 * @param array $viewer
 * @param int[] $ids message ids
 * @return array message id => ws_flow_run_card_payload()
 */
function ws_flow_run_cards_map($viewer, $ids)
{
    $ids = array_filter(array_map('intval', (array) $ids));

    if (empty($ids) || !ws_flows_ready()) {
        return array();
    }

    $links = (array) db_items("SELECT source_id, ref_id FROM ws_refs
        WHERE source_type = 'message' AND ref_type = 'flow_run' AND source_id IN (" . implode(',', $ids) . ")");

    if (empty($links)) {
        return array();
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');

    $runs = array();
    $by_message = array();

    foreach ($links as $link) {
        $run = ws_flow_run_for($viewer, (int) $link['ref_id']);

        if ($run) {
            $runs[(int) $run['id']] = $run;
            $by_message[(int) $link['source_id']] = (int) $run['id'];
        }
    }

    $results = ws_flow_runs_results($runs);
    $numbers = ws_flow_version_numbers($runs);
    $out = array();

    foreach ($by_message as $message_id => $run_id) {
        $run = $runs[$run_id];
        $present = ws_api_flow_run_present($viewer, $run, $numbers[(int) $run['version_id']] ?? 0, null, null, $results[$run_id] ?? '');
        $out[$message_id] = ws_flow_run_card_payload($present, (string) $run['flow']['name'], ws_flow_access($viewer, $run['flow']));
    }

    return $out;
}
