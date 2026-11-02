<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Assistant card. Connected (ws_claude_ready()): the proposals waiting
 * for the person (record changes they asked for, ws_ai_changes, and task
 * drafts from their requests, ws_ai_drafts) as chips opening each card, and
 * a box that gives Claude a job by posting "@Claude …" in a channel where
 * Claude may be asked; above them the first three lines of the daily summary
 * Claude writes at the chosen hour (config.ws_claude_summary_*), when it is
 * on and the person reads its channel. Not connected: what connecting
 * brings, Connect Claude (Workspace settings) and Connections (Application
 * Access) for the people who may open them, and the MCP note.
 *
 * The job is posted through the Workspace's own ws_send action, which checks
 * the channel, the person and Claude's limits again; nothing here writes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * The proposal chips shown; the count says how many wait in all.
 */
if (!defined('PG_DASHBOARD_V2_AI_CHIPS')) {
    define('PG_DASHBOARD_V2_AI_CHIPS', 4);
}

/**
 * The longest chip text, in characters.
 */
if (!defined('PG_DASHBOARD_V2_AI_CHIP_TEXT')) {
    define('PG_DASHBOARD_V2_AI_CHIP_TEXT', 60);
}

/**
 * The longest job the box takes, in characters.
 */
if (!defined('PG_DASHBOARD_V2_AI_JOB_MAX')) {
    define('PG_DASHBOARD_V2_AI_JOB_MAX', 2000);
}

/**
 * A text cut to a chip's length on a word where it can be. Pure.
 *
 * @param string $text
 * @param int    $max
 * @return string
 */
function pg_dashboard_v2_ai_cut($text, $max = PG_DASHBOARD_V2_AI_CHIP_TEXT)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

    if (mb_strlen($text) <= $max) {
        return $text;
    }

    $cut = mb_substr($text, 0, $max - 1);
    $space = mb_strrpos($cut, ' ');

    if (($space !== false) && ($space > ($max / 2))) {
        $cut = mb_substr($cut, 0, $space);
    }

    return rtrim($cut, " ,.;:-") . '…';
}

/**
 * Where a proposal's card is: its message in the channel, or the task it was
 * proposed on. Pure.
 *
 * @param int $channel_id
 * @param int $message_id
 * @param int $task_id
 * @return string empty when it has neither
 */
function pg_dashboard_v2_ai_proposal_url($channel_id, $message_id, $task_id)
{
    if ((int) $channel_id > 0) {
        return 'workspace.php?' . http_build_query(array('channel' => (int) $channel_id, 'message' => (int) $message_id));
    }

    if ((int) $task_id > 0) {
        return 'workspace_tasks.php?task=' . (int) $task_id;
    }

    return '';
}

/**
 * What a proposed change is called on its chip: its reason, else the kind
 * of record and what is done to it. Pure.
 *
 * @param array $change record_type, action, reason
 * @param array $types  ws_change_types()
 * @return string
 */
function pg_dashboard_v2_ai_change_title($change, $types)
{
    $reason = trim((string) ($change['reason'] ?? ''));

    if ($reason !== '') {
        return $reason;
    }

    $type = (string) ($change['record_type'] ?? '');
    $label = (string) ($types[$type]['label'] ?? $type);

    switch ((string) ($change['action'] ?? 'update')) {
        case 'create':
            return lang(array('string' => 'New {var:1}', 'vars' => $label));
        case 'delete':
            return lang(array('string' => 'Delete {var:1}', 'vars' => $label));
    }

    return lang(array('string' => 'Update {var:1}', 'vars' => $label));
}

/**
 * A proposal as a chip: "Task: …" or "Change: …", the newest first. Pure.
 *
 * @param string $kind  task or change
 * @param string $title
 * @param string $url
 * @param int    $time  when it was proposed
 * @return array kind, text, url, time
 */
function pg_dashboard_v2_ai_chip($kind, $title, $url, $time)
{
    $kind = ($kind === 'task') ? 'task' : 'change';
    $prefix = ($kind === 'task') ? lang('Task') : lang('Change');

    return array(
        'kind' => $kind,
        'text' => $prefix . ': ' . pg_dashboard_v2_ai_cut($title),
        'url'  => (string) $url,
        'time' => (int) $time,
    );
}

/**
 * The newest chips first, at most $limit of them. Pure.
 *
 * @param array $chips pg_dashboard_v2_ai_chip() rows
 * @param int   $limit
 * @return array
 */
function pg_dashboard_v2_ai_chips_order($chips, $limit = PG_DASHBOARD_V2_AI_CHIPS)
{
    usort($chips, function ($a, $b) {
        return $b['time'] <=> $a['time'];
    });

    return array_slice($chips, 0, $limit);
}

/**
 * The proposals waiting for the person: changes they asked for and may
 * apply, and task drafts from their own requests in channels where they may
 * decide. Read as the Workspace decides them (ws_change_apply_check(),
 * ws_claude_drafts_map()): an agent's change on a task the person no longer
 * sees, or in a channel they cannot post in, is left out.
 *
 * @param array $viewer ws_viewer()
 * @return array chips (all of them, unordered)
 */
function pg_dashboard_v2_ai_proposals($viewer)
{
    $chips = array();
    $me = (int) $viewer['id'];
    $postable = array();
    $can_post = function ($channel_id) use ($viewer, &$postable) {
        $channel_id = (int) $channel_id;

        if (!isset($postable[$channel_id])) {
            $channel = ws_channel($channel_id);
            $postable[$channel_id] = $channel && ws_can_post_channel($viewer, $channel);
        }

        return $postable[$channel_id];
    };

    if (function_exists('ws_changes_ready') && ws_changes_ready()) {
        $types = ws_change_types();

        foreach ((array) db_items("SELECT * FROM ws_ai_changes
            WHERE status = 'pending' AND requested_by = '" . $me . "' ORDER BY id DESC LIMIT 50") as $change) {
            $channel_id = (int) $change['channel_id'];
            $task_id = ($channel_id === 0) ? (int) ($change['task_id'] ?? 0) : 0;

            if ($channel_id > 0) {
                if (!$can_post($channel_id)) {
                    continue;
                }
            } elseif (($task_id <= 0) || !ws_can_see_task($viewer, ws_task($task_id))) {
                continue;
            }

            $chips[] = pg_dashboard_v2_ai_chip('change', pg_dashboard_v2_ai_change_title($change, $types),
                pg_dashboard_v2_ai_proposal_url($channel_id, (int) $change['message_id'], $task_id), (int) $change['created_at']);
        }
    }

    if (ws_claude_schema_ready()) {
        foreach ((array) db_items("SELECT d.id, d.channel_id, d.message_id, d.title, d.created_at
            FROM ws_ai_drafts d
            INNER JOIN ws_ai_requests r ON r.id = d.request_id
            WHERE d.status = 'pending' AND r.requested_by = '" . $me . "'
            ORDER BY d.id DESC LIMIT 50") as $draft) {
            if (!$can_post($draft['channel_id'])) {
                continue;
            }

            $chips[] = pg_dashboard_v2_ai_chip('task', (string) $draft['title'],
                pg_dashboard_v2_ai_proposal_url((int) $draft['channel_id'], (int) $draft['message_id'], 0), (int) $draft['created_at']);
        }
    }

    return $chips;
}

/**
 * The channel a job goes to: the one the person last asked Claude in, else
 * the oldest public channel; either only where they may post and Claude may
 * be asked.
 *
 * @param array $viewer ws_viewer()
 * @return array|null id, name
 */
function pg_dashboard_v2_ai_job_channel($viewer)
{
    $ids = array();
    $last = (int) db_value("SELECT channel_id FROM ws_ai_requests
        WHERE requested_by = '" . (int) $viewer['id'] . "' AND channel_id > 0" . ws_claude_only() . "
        ORDER BY id DESC LIMIT 1");

    if ($last > 0) {
        $ids[] = $last;
    }

    foreach ((array) db_values("SELECT id FROM ws_channels WHERE kind = 'public' AND archived_at = 0 ORDER BY id LIMIT 20") as $id) {
        $ids[] = (int) $id;
    }

    foreach (array_unique($ids) as $id) {
        $channel = ws_channel($id);

        if ($channel && ((int) ($channel['archived_at'] ?? 0) === 0) && ws_can_post_channel($viewer, $channel) && ws_claude_channel_allowed($channel)) {
            return array('id' => (int) $channel['id'], 'name' => (string) $channel['name']);
        }
    }

    return null;
}

/**
 * The first lines of a summary message as plain text, empty ones left out.
 * Pure.
 *
 * @param string $text the message as plain text
 * @param int    $limit
 * @return string[]
 */
function pg_dashboard_v2_ai_summary_lines($text, $limit = 3)
{
    $lines = array();

    foreach (preg_split('/\R/u', (string) $text) as $line) {
        // A list marker the answer may start its lines with.
        $line = trim(preg_replace('/^\s*(?:[-*•]|[0-9]+[.)])\s+/u', '', $line));

        if ($line !== '') {
            $lines[] = pg_dashboard_v2_ai_cut($line, 140);
        }

        if (count($lines) >= $limit) {
            break;
        }
    }

    return $lines;
}

/**
 * Today's summary for the person: the latest one Claude wrote, when the
 * summary is on and the person reads its channel.
 *
 * @param array $viewer ws_viewer()
 * @return array|null title, lines (text, url), when
 */
function pg_dashboard_v2_ai_summary($viewer)
{
    if (!function_exists('ws_claude_summary_ready') || !ws_claude_summary_ready()) {
        return null;
    }

    $config = ws_claude_summary_config();

    if (($config['hour'] === WS_CLAUDE_SUMMARY_OFF) || ($config['message_id'] <= 0)) {
        return null;
    }

    $message = ws_message($config['message_id']);
    $channel = $message ? ws_channel($message['channel_id']) : null;

    if (!$message || ((int) $message['deleted_at'] !== 0) || !$channel || !ws_can_read_channel($viewer, $channel)) {
        return null;
    }

    $url = pg_dashboard_v2_ai_proposal_url($channel['id'], $message['id'], 0);
    $lines = array();

    foreach (pg_dashboard_v2_ai_summary_lines(ws_plain_text($viewer, (string) $message['body'])) as $line) {
        $lines[] = array('text' => $line, 'url' => $url);
    }

    if (empty($lines)) {
        return null;
    }

    return array(
        'title' => lang('Daily summary'),
        'lines' => $lines,
        'when'  => get_relative_time(array('timestamp' => (int) $message['created_at'], 'format' => 'plain_text')),
    );
}

/**
 * The card when Claude is not connected: who may connect it gets the
 * buttons, everyone the note. Pure.
 *
 * @param bool $settings may open the Workspace settings
 * @param bool $manager  may open Application Access
 * @return array
 */
function pg_dashboard_v2_ai_offline($settings, $manager)
{
    $buttons = array();

    if ($settings) {
        $buttons[] = array('label' => lang('Connect Claude'), 'url' => 'workspace_settings.php#ws-claude', 'main' => true);
    }

    if ($manager) {
        $buttons[] = array('label' => lang('Connections'), 'url' => 'api_settings.php', 'main' => false);
    }

    return array(
        'connected' => false,
        'chip'      => array('text' => lang('Not connected'), 'tone' => ''),
        'title'     => lang('Connect Claude'),
        'text'      => lang('Once connected you see a daily summary here and give Claude jobs with @Claude in the channels. Claude changes nothing by itself; you approve its proposals.'),
        'ask'       => $buttons ? '' : lang('An administrator connects Claude in the Workspace settings.'),
        'buttons'   => $buttons,
        'note'      => lang('The site is also open as an MCP server: Claude and other MCP applications can connect with OAuth.'),
    );
}

function pg_dashboard_v2_card_ai($user, $period, $compare)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

    if (pg_dashboard_workspace_rights($user) === null) {
        return array('status' => 'denied');
    }

    $viewer = ws_viewer($user);

    if (!ws_claude_schema_ready() || !ws_claude_ready()) {
        return array('status' => 'success', 'data' => pg_dashboard_v2_ai_offline(!empty($viewer['settings']), ((int) $user['role'] < 3)));
    }

    $chips = pg_dashboard_v2_ai_proposals($viewer);
    $channel = pg_dashboard_v2_ai_job_channel($viewer);
    $job = null;

    if ($channel) {
        $job = array(
            'channel_id'  => $channel['id'],
            'tag'         => ws_claude_tag(),
            'max'         => PG_DASHBOARD_V2_AI_JOB_MAX,
            'label'       => lang('Give Claude a job'),
            'placeholder' => lang('Give Claude a job: "Prepare this week\'s report"'),
            'send'        => lang('Send'),
            'note'        => lang(array('string' => 'The answer comes to the #{var:1} channel in the Workspace and may take a few minutes. Claude asks for your approval before it changes anything.', 'vars' => $channel['name'])),
            'sent'        => lang(array('string' => 'Sent to #{var:1}. Claude answers there in a few minutes.', 'vars' => $channel['name'])),
            'open'        => lang('Open'),
            'failed'      => lang('That could not be sent. Please try again.'),
        );
    }

    return array('status' => 'success', 'data' => array(
        'connected' => true,
        'chip'      => array('text' => lang('Claude · connected'), 'tone' => 'good'),
        'proposals' => array(
            'title' => lang(array('string' => 'Proposals waiting for your approval · {var:1}', 'vars' => pg_format_number(count($chips), 0))),
            'chips' => pg_dashboard_v2_ai_chips_order($chips),
            'more'  => (count($chips) > PG_DASHBOARD_V2_AI_CHIPS)
                ? lang(array('string' => '{var:1} more in the Workspace', 'vars' => pg_format_number(count($chips) - PG_DASHBOARD_V2_AI_CHIPS, 0)))
                : '',
            'none'  => lang('Nothing is waiting for your approval.'),
        ),
        'summary'   => pg_dashboard_v2_ai_summary($viewer),
        'job'       => $job,
        'no_job'    => $job ? '' : lang('There is no channel where you may ask Claude. Somebody who manages a channel can allow it from the channel menu.'),
    ));
}
