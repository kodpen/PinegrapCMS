<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the @herkes hint of a discussion and the channel's agents
 * added to a discussion in one go. In a discussion a person's @herkes wakes
 * the discussion's own application agents only; the channel's agents answer
 * there when named (ws_agent_wake_matches(), ws_agent_reach_merge()). When
 * the tag woke nobody and the channel has agents asked by name, one system
 * line under the message says why and what to do. The people panel's
 * "Agents in this discussion" offers the channel's agents as a ticked list
 * and adds the picked ones with one request, each checked as a single add.
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
 * The handles of the application agents a discussion reaches through its
 * channel, asked there by name only. A model agent is never woken by
 * @herkes, so it is not named. Pure.
 *
 * @param array[] $rows ws_agent_reach_rows(): kind, handle, reach_from
 * @return string[]
 */
function ws_everyone_hint_by_name($rows)
{
    $handles = array();

    foreach ((array) $rows as $row) {
        if (((string) ($row['kind'] ?? '') === 'app') && ((int) ($row['reach_from'] ?? 0) > 0) && ((string) ($row['handle'] ?? '') !== '')) {
            $handles[] = (string) $row['handle'];
        }
    }

    return $handles;
}

/**
 * Did the tag wake an agent? An own application agent of the room among
 * those woken, the ones a person's @herkes wakes; a channel's agent woken
 * by its name or a model agent is not the tag's. Pure.
 *
 * @param array[]  $rows  ws_agent_reach_rows()
 * @param string[] $woken agent values ws_agent_after_send() made requests for
 * @return bool
 */
function ws_everyone_hint_tag_woke($rows, $woken)
{
    $woken = (array) $woken;

    foreach ((array) $rows as $row) {
        if (((string) ($row['kind'] ?? '') === 'app') && ((int) ($row['reach_from'] ?? 0) === 0)
            && in_array(ws_agent_request_value($row), $woken, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Is the hint written under a message? A person's message or edit in a
 * discussion whose @herkes counts, that woke no agent, while the channel
 * has an agent asked there by name. Pure.
 *
 * @param bool     $discussion the room is a discussion
 * @param bool     $by_person  a person wrote it (no application, no OAuth client)
 * @param string   $body       the message, or the tags an edit added
 * @param bool     $tag_woke   ws_everyone_hint_tag_woke()
 * @param string[] $by_name    ws_everyone_hint_by_name()
 * @return bool
 */
function ws_everyone_hint_due($discussion, $by_person, $body, $tag_woke, $by_name)
{
    return $discussion && $by_person && !$tag_woke && !empty($by_name) && ws_message_everyone((string) $body);
}

/**
 * The hint's text: plain words and handles, no tag, so the line rings and
 * wakes nobody.
 *
 * @param string[] $by_name ws_everyone_hint_by_name()
 * @return string
 */
function ws_everyone_hint_line($by_name)
{
    $handles = implode(', ', array_map(function ($handle) {
        return '@' . $handle;
    }, (array) $by_name));

    return lang(array(
        'string' => '@herkes woke no agent here. The channel\'s agents ({var:1}) answer in a discussion only when named: write their name, or add them under Agents in this discussion in the people panel and write the tag again.',
        'vars'   => $handles,
    ));
}

/**
 * After a person's message or edit was asked (ws_message_ask_assistants(),
 * ws_message_ask_added()): writes the hint under it when it is due.
 *
 * @param array    $channel
 * @param bool     $by_person
 * @param string   $body  the message, or the tags an edit added
 * @param string[] $woken the agent values woken
 * @return int the hint's message id, 0 when none was written
 */
function ws_everyone_hint_after($channel, $by_person, $body, $woken)
{
    // The cheap checks first: most messages carry no tag.
    if (!$by_person || !is_array($channel) || !ws_message_everyone((string) $body) || !ws_channel_is_thread($channel)) {
        return 0;
    }

    $rows = ws_agent_reach_rows($channel);
    $by_name = ws_everyone_hint_by_name($rows);

    if (!ws_everyone_hint_due(true, true, $body, ws_everyone_hint_tag_woke($rows, $woken), $by_name)) {
        return 0;
    }

    return ws_message_system((int) $channel['id'], ws_everyone_hint_line($by_name));
}

/**
 * The agents the one-go list offers in a discussion: those of its channel
 * that may still be added here, in the add list's order. Pure.
 *
 * @param array[] $addable   ws_channel_agents_addable() of the discussion
 * @param int[]   $channel_ids the agent ids the channel allows
 * @return array[]
 */
function ws_channel_agents_from_channel($addable, $channel_ids)
{
    $channel_ids = array_map('intval', (array) $channel_ids);

    return array_values(array_filter((array) $addable, function ($agent) use ($channel_ids) {
        return in_array((int) ($agent['agent_id'] ?? 0), $channel_ids, true);
    }));
}

/**
 * Adds several agents, each through $try as a single add is made. Ids are
 * read once each; a refused agent is named with its reason. Pure but for
 * $try.
 *
 * @param array    $ids the picked agent ids
 * @param callable $try function (int $id): array name, error ('' when added)
 * @return array added: int[]; refused: string[] "name: reason"
 */
function ws_channel_agents_add_many($ids, $try)
{
    $out = array('added' => array(), 'refused' => array());
    $seen = array();

    foreach ((array) $ids as $id) {
        $id = (int) $id;

        if (($id <= 0) || isset($seen[$id])) {
            continue;
        }

        $seen[$id] = true;
        $result = (array) $try($id);
        $error = (string) ($result['error'] ?? '');

        if ($error === '') {
            $out['added'][] = $id;
        } else {
            $out['refused'][] = (string) ($result['name'] ?? '') . ': ' . $error;
        }
    }

    return $out;
}
