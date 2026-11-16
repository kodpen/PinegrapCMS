<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's open items: the polls and agent questions still
 * taking answers, the check-in still running and the approval requests still
 * waiting, listed over the conversation (the open items line in .ws-strip,
 * assets/js/workspace.js, drawOpenItems()) so that a reader who scrolled past one
 * still sees it. Each names its kind, its message, a short text and whether
 * it waits for the reader's own answer; the reader's own come first.
 *
 * It reads what the channel's cards read (interact.php, agent_question.php,
 * approvals.php, status.php) and gives no more than those cards show the
 * reader: the current conversation only, nothing deleted or hidden for them.
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
 * The most items the line lists.
 */
define('WS_OPEN_ITEMS_MAX', 10);

/**
 * A check-in counts as running this many days after it was asked, until a
 * newer one is asked in the channel or everybody answered. A check-in has
 * no close of its own; the scheduled one asks every week.
 */
define('WS_OPEN_ITEMS_CHECKIN_DAYS', 7);

/**
 * The open items in the order the line shows them, cut to the bound: the
 * ones waiting for the reader first, then the newest message first. Pure.
 *
 * @param array[] $items kind, message_id, text, mine
 * @param int     $limit
 * @return array[]
 */
function ws_open_items_order($items, $limit = WS_OPEN_ITEMS_MAX)
{
    $items = array_values(array_filter((array) $items, function ($item) {
        return is_array($item) && ((int) ($item['message_id'] ?? 0) > 0);
    }));

    usort($items, function ($a, $b) {
        $mine = (int) !empty($b['mine']) - (int) !empty($a['mine']);

        return ($mine !== 0) ? $mine : ((int) $b['message_id'] - (int) $a['message_id']);
    });

    return array_slice($items, 0, max(0, (int) $limit));
}

/**
 * Is a check-in still running? The channel's newest one, asked less than
 * WS_OPEN_ITEMS_CHECKIN_DAYS ago, while somebody has not answered. Pure.
 *
 * @param int $asked_at
 * @param int $answered  answers given
 * @param int $members   people of the channel
 * @param int $now
 * @return bool
 */
function ws_open_items_checkin_running($asked_at, $answered, $members, $now)
{
    return ((int) $asked_at > (int) $now - WS_OPEN_ITEMS_CHECKIN_DAYS * 86400) && ((int) $answered < (int) $members);
}

/**
 * A short text for the line: one line, at most 120 characters. Pure.
 *
 * @param string $text
 * @return string
 */
function ws_open_items_short($text)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

    return (mb_strlen($text) > 120) ? rtrim(mb_substr($text, 0, 119)) . '…' : $text;
}

/**
 * Whose answer an item waits for, for the caller. Pure.
 *
 * The person (null) keeps the marks the list was built with. An application
 * reading for itself (its agent's request value, 'claude', or '' for one with
 * no agent) answers no poll, question or check-in in the person's name: only
 * an approval request that asked its own agent and still waits for that
 * agent's answer is its own.
 *
 * @param array[]     $items  with agent_pending on approvals
 * @param string|null $caller
 * @return array[] kind, message_id, text, mine
 */
function ws_open_items_for_caller($items, $caller)
{
    $out = array();

    foreach ((array) $items as $item) {
        if ($caller !== null) {
            $item['mine'] = ((string) $caller !== '') && (($item['kind'] ?? '') === 'approval') && !empty($item['agent_pending']);
        }

        unset($item['agent_pending']);
        $out[] = $item;
    }

    return $out;
}

/**
 * The channel's open items this reader may see, in the line's order.
 *
 * @param array       $viewer
 * @param array       $channel
 * @param string|null $caller null: the person's own marks; otherwise the
 *                            application reading for itself, as
 *                            ws_open_items_for_caller() takes it
 * @return array[] kind (poll, question, checkin, approval), message_id, text, mine
 */
function ws_channel_open_items($viewer, $channel, $caller = null)
{
    $me = (int) ($viewer['id'] ?? 0);

    if (($me <= 0) || ((int) ($viewer['guest_id'] ?? 0) > 0) || !is_array($channel) || !ws_can_read_channel($viewer, $channel)) {
        return array();
    }

    $channel_id = (int) $channel['id'];
    $floor = function_exists('ws_channel_floor') ? ws_channel_floor($channel_id) : 0;
    $now = time();
    $can_post = ws_can_post_channel($viewer, $channel);
    $items = array();

    // Polls and agent questions still taking answers.
    if (ws_interact_ready()) {
        $question_ready = ws_agent_question_ready();
        $rows = (array) db_items("SELECT p.*, m.body FROM ws_polls p
            INNER JOIN ws_messages m ON m.id = p.message_id AND m.deleted_at = 0
            WHERE p.channel_id = '" . $channel_id . "' AND p.closed_at = 0
                AND (p.closes_at = 0 OR p.closes_at > '" . $now . "')
                AND p.message_id > '" . (int) $floor . "'"
            . (ws_poll_again_ready() ? ' AND p.withdrawn_at = 0' : '') . "
            ORDER BY p.id DESC LIMIT 30");
        $voted = array();

        if (!empty($rows)) {
            $ids = implode(',', array_map(function ($row) { return (int) $row['id']; }, $rows));
            $voted = array_flip(array_map('intval', (array) db_values("SELECT DISTINCT poll_id FROM ws_poll_votes
                WHERE poll_id IN (" . $ids . ") AND user_id = '" . $me . "'")));
        }

        foreach ($rows as $row) {
            $question = $question_ready && ws_agent_question_is($row);
            $mine = $question
                ? ws_agent_question_waits_for($row, ws_question_asked_ids($channel, ws_tokens($row['body'])), $me)
                : ($can_post && !isset($voted[(int) $row['id']]));

            $items[] = array(
                'kind'       => $question ? 'question' : 'poll',
                'message_id' => (int) $row['message_id'],
                'text'       => ws_open_items_short(ws_plain_text($viewer, (string) $row['body'])),
                'mine'       => $mine,
            );
        }
    }

    // Approval requests still waiting.
    if (ws_approvals_ready()) {
        // The calling application's own agent, when it was asked too.
        $agent_join = ((string) $caller !== '') && function_exists('ws_approval_agents_ready') && ws_approval_agents_ready();
        $rows = (array) db_items("SELECT a.id, a.message_id, a.title, p.decision" . ($agent_join ? ', aa.decision AS agent_decision' : '') . " FROM ws_approvals a
            INNER JOIN ws_messages m ON m.id = a.message_id AND m.deleted_at = 0
            LEFT JOIN ws_approval_people p ON p.approval_id = a.id AND p.user_id = '" . $me . "'"
            . ($agent_join ? " LEFT JOIN ws_approval_agents aa ON aa.approval_id = a.id AND aa.agent = '" . e((string) $caller) . "'" : '') . "
            WHERE a.channel_id = '" . $channel_id . "' AND a.closed_at = 0
                AND (a.closes_at = 0 OR a.closes_at > '" . $now . "')
                AND a.message_id > '" . (int) $floor . "'
            ORDER BY a.id DESC LIMIT 30");

        foreach ($rows as $row) {
            $items[] = array(
                'kind'       => 'approval',
                'message_id' => (int) $row['message_id'],
                'text'       => ws_open_items_short($row['title']),
                'mine'       => $can_post && ((string) ($row['decision'] ?? '') === 'pending'),
                'agent_pending' => ((string) ($row['agent_decision'] ?? '') === 'pending'),
            );
        }
    }

    // The running check-in: only the channel's newest one.
    if (function_exists('ws_status_ready') && ws_status_ready()) {
        $checkin = db_item("SELECT c.id, c.message_id, c.question, c.created_at FROM ws_checkins c
            INNER JOIN ws_messages m ON m.id = c.message_id AND m.deleted_at = 0
            WHERE c.channel_id = '" . $channel_id . "' AND c.message_id > '" . (int) $floor . "'
            ORDER BY c.id DESC LIMIT 1");

        if (is_array($checkin)) {
            $members = array_map('intval', (array) db_values("SELECT user_id FROM ws_channel_members WHERE channel_id = '" . $channel_id . "'"));
            $answered = array_map('intval', (array) db_values("SELECT a.user_id FROM ws_checkin_answers a
                INNER JOIN ws_channel_members cm ON cm.channel_id = '" . $channel_id . "' AND cm.user_id = a.user_id
                WHERE a.checkin_id = '" . (int) $checkin['id'] . "'"));

            if (ws_open_items_checkin_running((int) $checkin['created_at'], count($answered), count($members), $now)) {
                $items[] = array(
                    'kind'       => 'checkin',
                    'message_id' => (int) $checkin['message_id'],
                    'text'       => ws_open_items_short($checkin['question']),
                    'mine'       => $can_post && in_array($me, $members, true) && !in_array($me, $answered, true),
                );
            }
        }
    }

    // A message the reader hid for themselves is not shown to them.
    if (!empty($items) && function_exists('ws_message_hidden_ids')) {
        $hidden = (array) ws_message_hidden_ids($me, array_map(function ($item) { return $item['message_id']; }, $items));
        $items = array_filter($items, function ($item) use ($hidden) {
            return !isset($hidden[(int) $item['message_id']]);
        });
    }

    return ws_open_items_order(ws_open_items_for_caller($items, $caller));
}

/**
 * The words the screen needs for the open items line.
 *
 * @return array
 */
function ws_open_items_js_strings()
{
    return array(
        'open_kind_poll'     => lang('Poll'),
        'open_kind_question' => lang('Agent question'),
        'open_kind_checkin'  => lang('Check-in'),
        'open_kind_approval' => lang('Approval request'),
        'open_go'            => lang('Go'),
        'open_go_title'      => lang('Go to the message'),
        'open_list'          => lang('Open items in this channel'),
        'open_yours'         => lang('Waiting for you'),
    );
}
