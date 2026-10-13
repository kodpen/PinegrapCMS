<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 28 - Waiting on Me: what colleagues in the workspace are
 * waiting for the reader to do - approval requests to answer, messages to
 * confirm as read, and mentions and replies.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_28($request, $user)
{
    $rights = pg_dashboard_workspace_rights($user);

    if ($rights === null) {
        return array('status' => 'error', 'message' => 'Access denied');
    }

    // The same three readers the workspace overview uses, so the card and
    // that screen never disagree about what is waiting. Each one already
    // drops what sits in a channel the reader can no longer open.
    $approvals = function_exists('ws_approvals_waiting') ? ws_approvals_waiting($rights, 8) : array();
    $acks = function_exists('ws_acks_waiting') ? ws_acks_waiting($rights, 8) : array();
    $talk = ws_home_talk($rights, 8);

    $channel = function ($item) {
        return ($item['channel']['private'] ? '' : '#') . $item['channel']['name'];
    };

    $output_approvals = '';

    foreach ($approvals as $item) {
        $output_approvals .= pg_widget_row(array(
            'href'  => h($item['url']),
            'badge' => '<i class="bi bi-patch-question"></i>',
            'name'  => h($item['title']),
            'aside' => h($item['time']),
            'meta'  => h($item['asker']) . ' &middot; ' . h($channel($item))
                . (($item['closes'] !== '') ? ' &middot; ' . h(lang(array('string' => 'closes {var:1}', 'vars' => $item['closes']))) : ''),
        ));
    }

    $output_acks = '';

    foreach ($acks as $item) {
        $output_acks .= pg_widget_row(array(
            'href'  => h($item['url']),
            'badge' => '<i class="bi bi-check2-all"></i>',
            'name'  => h($item['text']),
            'aside' => h($item['time']),
            'meta'  => h($item['asker']) . ' &middot; ' . h($channel($item)),
        ));
    }

    $output_talk = '';
    $unread = 0;

    foreach ($talk as $item) {

        if ($item['unread']) {
            $unread++;
        }

        $sender = is_array($item['sender']) ? $item['sender'] : array();

        $output_talk .= pg_widget_row(array(
            'href'  => h($item['url']),
            'badge' => !empty($sender['avatar'])
                ? '<img src="' . h($sender['avatar']) . '" alt="">'
                : '<i class="bi ' . (($item['kind'] === 'mention') ? 'bi-at' : 'bi-reply') . '"></i>',
            'name'  => h(($sender['name'] ?? '') !== '' ? $sender['name'] : lang('Unknown')),
            'aside' => h($item['time']),
            'meta'  => h($item['text']) . ' &middot; ' . h($channel($item)),
            // Unread ones carry a dot after the name; read ones stay in the
            // list, because "who wrote to me" is still worth a glance.
            'class' => $item['unread'] ? 'is-unread' : '',
        ));
    }

    $lanes = array(
        'approve' => array(
            'label'  => lang('Approvals'),
            'icon'   => 'bi-patch-question',
            'color'  => '#8b5cf6',
            'count'  => count($approvals),
            'urgent' => true,
            'sub'    => h(lang('Waiting on your answer')),
            'body'   => ($output_approvals !== '')
                ? '<div class="pg-list">' . $output_approvals . '</div>'
                : pg_widget_empty('bi-check2-circle', lang('No approval requests waiting.'), 'good'),
        ),
        'ack' => array(
            'label' => lang('To read'),
            'icon'  => 'bi-check2-all',
            'color' => '#0ea5e9',
            'count' => count($acks),
            'sub'   => h(lang('Confirm you have read')),
            'body'  => ($output_acks !== '')
                ? '<div class="pg-list">' . $output_acks . '</div>'
                : pg_widget_empty('bi-check2-circle', lang('Nothing to confirm.'), 'good'),
        ),
        'talk' => array(
            'label' => lang('Mentions'),
            'icon'  => 'bi-at',
            'color' => '#f59e0b',
            'count' => $unread,
            'sub'   => h(lang('Replies and mentions')),
            'body'  => ($output_talk !== '')
                ? '<div class="pg-list">' . $output_talk . '</div>'
                : pg_widget_empty('bi-chat', lang('Nobody has written to you lately.'), 'good'),
        ),
    );

    return array(
        'status' => 'success',
        'message' => lang('Data Received successfully.'),
        'data' => pg_widget_lanes('ws-waiting', lang('Waiting on Me'), $lanes),
    );
}
