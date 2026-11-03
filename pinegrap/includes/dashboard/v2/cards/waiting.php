<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Waiting on me card: approval requests to answer, messages to confirm
 * as read and unread mentions, as "Ayşe → you · 1 day" rows with Open. The
 * same three readers widget 28 and the Workspace overview use, so they never
 * disagree; each already drops what sits in a channel the reader can no
 * longer open.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/team.php');

function pg_dashboard_v2_card_waiting($user, $period, $compare)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

    $rights = pg_dashboard_workspace_rights($user);

    if ($rights === null) {
        return array('status' => 'denied');
    }

    $approvals = function_exists('ws_approvals_waiting') ? ws_approvals_waiting($rights, 5) : array();
    $acks = function_exists('ws_acks_waiting') ? ws_acks_waiting($rights, 5) : array();
    $talk = ws_home_talk($rights, 8);

    $note = function ($from, $time) {
        return lang(array('string' => '{var:1} → you · {var:2}', 'vars' => array($from, $time)));
    };

    $rows = array();

    foreach ($approvals as $item) {
        $rows[] = array(
            'person' => pg_dashboard_v2_person(array('name' => (string) $item['asker'])),
            'text'   => (string) $item['title'],
            'note'   => $note((string) $item['asker'], (string) $item['time']),
            'tone'   => 'warn',
            'url'    => (string) $item['url'],
        );
    }

    foreach ($acks as $item) {
        $rows[] = array(
            'person' => pg_dashboard_v2_person(array('name' => (string) $item['asker'])),
            'text'   => (string) $item['text'],
            'note'   => $note((string) $item['asker'], (string) $item['time']),
            'tone'   => '',
            'url'    => (string) $item['url'],
        );
    }

    // Mentions and replies only while unread: a read one waits on nobody.
    foreach ($talk as $item) {
        if (empty($item['unread'])) {
            continue;
        }

        $sender = is_array($item['sender']) ? $item['sender'] : array();
        $name = (string) (($sender['name'] ?? '') !== '' ? $sender['name'] : lang('Unknown'));

        $rows[] = array(
            'person' => pg_dashboard_v2_person(array('name' => $name) + $sender),
            'text'   => (string) $item['text'],
            'note'   => $note($name, (string) $item['time']),
            'tone'   => '',
            'url'    => (string) $item['url'],
        );
    }

    return array('status' => 'success', 'data' => array(
        'rows'     => array_slice($rows, 0, 5),
        'empty'    => empty($rows) ? lang('Nothing is waiting on you.') : '',
        'open'     => lang('Open'),
        'url'      => 'workspace.php',
        'url_text' => lang('Open the Workspace'),
    ));
}
