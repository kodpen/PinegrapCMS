<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a person's card in a channel in the external API and MCP
 * (person_card.php): what the channel's ws_person_card action shows its
 * reader. Read by the team only, as in the panel: a person who is not on
 * the team (a guest, a former member, an application) has no card, and the
 * last seen is given only to a manager of the channel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The person card route, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_person_card_api_routes()
{
    return array(
        array(
            'id'          => 'workspace.channels.members.card',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/members/{user_id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_member_card',
            'returns'     => 'WorkspacePersonCard',
            'summary'     => 'A colleague\'s card in a channel',
            'description' => 'The card the channel shows when a name is clicked: the person\'s name, role and title, presence, departments with their leads, working day, away days ahead (leaves on the plan that apply to them and their mail away range, within 90 days, at most 5) and local time. The person need not be in the channel, only on the team. seen (when they were last seen) is given only when the owner of the application manages the channel, else null. Refusals: the owner not a member of the workspace 403 forbidden; a channel the owner may not read, or a person who is not on the team (a guest, a former member, an application), 404 not_found.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'user_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),
    );
}

function ws_api_channel_member_card($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    // The panel's own reading: null for anyone not on the team.
    $card = ws_person_card($viewer, $channel, (int) $params['user_id'], time());

    if (!is_array($card)) {
        api_fail_not_found(lang('Person'));
    }

    api_ok(ws_api_person_card_present($card, ws_can_manage_channel($viewer, $channel)));
}

/**
 * The card as the API gives it. seen is null unless the reader manages
 * the channel, whatever the card carries. Pure.
 *
 * @param array $card       ws_person_card()
 * @param bool  $can_manage
 * @return array
 */
function ws_api_person_card_present($card, $can_manage)
{
    $departments = array();

    foreach ((array) ($card['departments'] ?? array()) as $department) {
        $leads = array();

        foreach ((array) ($department['leads'] ?? array()) as $lead) {
            $leads[] = array('user_id' => (int) $lead['id'], 'name' => (string) $lead['name']);
        }

        $departments[] = array(
            'id'    => (int) $department['id'],
            'name'  => (string) $department['name'],
            'color' => (string) $department['color'],
            'lead'  => !empty($department['lead']),
            'leads' => $leads,
        );
    }

    $away = array();

    foreach ((array) ($card['away'] ?? array()) as $range) {
        $away[] = array(
            'start'  => (string) $range['start'],
            'end'    => (string) $range['end'],
            'now'    => !empty($range['now']),
            'source' => (string) $range['source'],
            'title'  => (string) $range['title'],
        );
    }

    $workday = (array) ($card['workday'] ?? array());
    $clock = (array) ($card['clock'] ?? array());
    $seen = (string) ($card['seen'] ?? '');

    return array(
        'user_id'     => (int) $card['id'],
        'name'        => (string) $card['name'],
        'username'    => (string) $card['username'],
        'role'        => (int) $card['role'],
        'role_label'  => (string) ($card['role_label'] ?? ''),
        'title'       => (string) ($card['title'] ?? ''),
        'avatar'      => (string) ($card['avatar'] ?? ''),
        'presence'    => (string) ($card['presence'] ?? 'offline'),
        'departments' => $departments,
        'workday'     => array(
            'minutes' => (int) ($workday['minutes'] ?? 0),
            'hours'   => (string) ($workday['hours'] ?? ''),
            'days'    => array_values(array_map('intval', (array) ($workday['days'] ?? array()))),
        ),
        'away'        => $away,
        'clock'       => array(
            'zone'  => (string) ($clock['zone'] ?? ''),
            'time'  => (string) ($clock['time'] ?? ''),
            'other' => !empty($clock['other']),
        ),
        // Only for a manager of the channel, as the panel's card.
        'seen'        => ($can_manage && ($seen !== '')) ? $seen : null,
    );
}

function ws_api_person_card_schema()
{
    return array(
        'user_id'     => 'integer',
        'name'        => 'string',
        'username'    => 'string',
        'role'        => 'integer',
        'role_label'  => 'string',
        'title'       => 'string',
        'avatar'      => 'string',
        'presence'    => 'string',
        'departments' => 'WorkspacePersonCardDepartment[]',
        'workday'     => 'WorkspacePersonCardWorkday',
        'away'        => 'WorkspacePersonCardAway[]',
        'clock'       => 'WorkspacePersonCardClock',
        'seen'        => 'string?',
    );
}

// A department of the person, primary first; leads are the other leads.
function ws_api_person_card_department_schema()
{
    return array(
        'id'    => 'integer',
        'name'  => 'string',
        'color' => 'string',
        'lead'  => 'boolean',
        'leads' => 'WorkspacePerson[]',
    );
}

// The working day: minutes, the hours in words, days as ISO numbers (Monday 1).
function ws_api_person_card_workday_schema()
{
    return array('minutes' => 'integer', 'hours' => 'string', 'days' => 'integer[]');
}

// One away range, dates as Y-m-d; source is plan or mail.
function ws_api_person_card_away_schema()
{
    return array('start' => 'string', 'end' => 'string', 'now' => 'boolean', 'source' => 'string', 'title' => 'string');
}

// The person's local time (H:i) in their zone; other when not the site's.
function ws_api_person_card_clock_schema()
{
    return array('zone' => 'string', 'time' => 'string', 'other' => 'boolean');
}
