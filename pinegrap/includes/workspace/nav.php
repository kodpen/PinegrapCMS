<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the way around it on the screens other than the channels: a
 * narrow rail of icons at the left edge of the tasks, the notes, the board,
 * the work calendar, the decision timeline and the settings, and the whole
 * list - the channels, the work, the records - in a drawer the rail opens.
 * Drawn here, with the page, so it is there before any script has run. The
 * channel screen draws the same groups in its own sidebar
 * (assets/js/workspace.js).
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
 * The places of the workspace, in the groups the sidebar shows them in.
 * Items with 'rail' false are left off the rail and shown in the drawer only.
 *
 * @param array $viewer
 * @return array[] top, work, records, foot => items (key, label, icon, url)
 */
function ws_nav_groups($viewer)
{
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

    $groups = array(
        'top' => array(
            array('key' => 'home', 'label' => lang('Overview'), 'icon' => 'bi-house', 'url' => $base . 'workspace.php?view=home'),
            array('key' => 'inbox', 'label' => lang('Inbox'), 'icon' => 'bi-inbox', 'url' => $base . 'workspace.php?view=home&inbox=1', 'inbox' => true),
        ),
        'work' => array(
            array('key' => 'tasks', 'label' => lang('My Tasks'), 'icon' => 'bi-check2-square', 'url' => $base . 'workspace_tasks.php'),
            array('key' => 'notes', 'label' => lang('My Notes'), 'icon' => 'bi-journal-text', 'url' => $base . 'workspace_notes.php'),
            array('key' => 'board', 'label' => lang('Planning Board'), 'icon' => 'bi-calendar-week', 'url' => $base . 'workspace_board.php'),
            array('key' => 'calendar', 'label' => lang('Work Calendar'), 'icon' => 'bi-calendar3', 'url' => $base . 'workspace_calendar.php'),
        ),
        'records' => array(
            array('key' => 'timeline', 'label' => lang('Decision Timeline'), 'icon' => 'bi-clock-history', 'url' => $base . 'workspace_timeline.php'),
            array('key' => 'archived', 'label' => lang('Archived channels'), 'icon' => 'bi-archive', 'url' => $base . 'workspace.php?view=home&panel=archived', 'rail' => false),
        ),
        'foot' => array(),
    );

    if ($viewer['role'] < 3) {
        $groups['records'][] = array('key' => 'audit', 'label' => lang('Private channels'), 'icon' => 'bi-shield-lock', 'url' => $base . 'workspace.php?view=home&panel=audit', 'rail' => false);
    }

    // Scheduled actions are staff's (scheduled.php).
    if (function_exists('ws_can_schedule') && ws_can_schedule($viewer)) {
        $groups['work'][] = array('key' => 'scheduled', 'label' => lang('Scheduled actions'), 'icon' => 'bi-alarm', 'url' => $base . 'workspace.php?view=scheduled');
    }

    if ($viewer['settings']) {
        $groups['foot'][] = array('key' => 'settings', 'label' => lang('Workspace Settings'), 'icon' => 'bi-sliders', 'url' => $base . 'workspace_settings.php');
    }

    return $groups;
}

/**
 * The titles of the groups.
 *
 * @return array
 */
function ws_nav_group_titles()
{
    return array(
        'work'    => lang('Work and planning'),
        'records' => lang('Decisions and archive'),
    );
}

/**
 * The rail and its drawer, for the left edge of a workspace screen.
 *
 * @param array  $viewer
 * @param string $current the key of the screen shown (tasks, notes, board, calendar, timeline, settings)
 * @return string
 */
function ws_screen_rail($viewer, $current)
{
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    $groups = ws_nav_groups($viewer);
    $titles = ws_nav_group_titles();
    $channels = ws_channels_for($viewer);
    $inbox = ws_inbox_unread_count((int) $viewer['id']);

    $badge = function ($link) use ($inbox) {
        return (!empty($link['inbox']) && ($inbox > 0)) ? $inbox : 0;
    };

    $rail_item = function ($link) use ($current, $badge) {
        $count = $badge($link);

        return '<a class="ws-rail-item' . (($link['key'] === $current) ? ' active' : '') . '" href="' . h($link['url']) . '" title="' . h($link['label']) . '" aria-label="' . h($link['label']) . '"'
            . ' data-ws-nav="' . h($link['key']) . '"'
            . (!empty($link['inbox']) ? ' data-ws-inbox="1"' : '')
            . (($link['key'] === $current) ? ' aria-current="page"' : '') . '>'
            . '<i class="bi ' . h($link['icon']) . '" aria-hidden="true"></i>'
            . (($count > 0) ? '<span class="ws-rail-badge">' . (($count > 99) ? '99+' : (int) $count) . '</span>' : '')
            . '</a>';
    };

    $list_item = function ($link) use ($current, $badge) {
        $count = $badge($link);

        return '<a class="ws-channel-link' . (($link['key'] === $current) ? ' active' : '') . '" href="' . h($link['url']) . '"'
            . ' data-ws-nav="' . h($link['key']) . '"'
            . (!empty($link['inbox']) ? ' data-ws-inbox="1"' : '') . '>'
            . '<i class="bi ' . h($link['icon']) . '" aria-hidden="true"></i>'
            . '<span class="ws-channel-name">' . h($link['label']) . '</span>'
            . (($count > 0) ? '<span class="ws-count ws-count-mention">' . (int) $count . '</span>' : '')
            . '</a>';
    };

    $channel_link = function ($channel) use ($base) {
        $icon = ws_channel_icon($channel);
        $count = '';

        if ($channel['mentions']) {
            $count = '<span class="ws-count ws-count-mention">' . (int) $channel['mentions'] . '</span>';
        } elseif ($channel['unread'] && ($channel['notify'] !== 'none')) {
            $count = '<span class="ws-count">' . (($channel['unread'] > 99) ? '99+' : (int) $channel['unread']) . '</span>';
        }

        return '<a class="ws-channel-link' . ($channel['unread'] ? ' ws-unread' : '') . ($channel['joined'] ? '' : ' ws-notjoined') . '" href="' . h($base . 'workspace.php?channel=' . (int) $channel['id']) . '"'
            . (($channel['hex'] !== '') ? ' style="--ws-ch: ' . h($channel['hex']) . '" data-ws-colored="1"' : '') . '>'
            . '<i class="bi ' . $icon . ' ws-ch-icon" aria-hidden="true"></i>'
            . '<span class="ws-channel-name">' . h($channel['name']) . '</span>' . $count . '</a>';
    };

    $pinned = array();
    $mine = array();
    $others = array();

    // Channels in a group are listed under it (pinned ones stay pinned).
    $group_rows = ws_groups_for($viewer);
    $known_groups = array();

    foreach ($group_rows as $group) {
        $known_groups[$group['id']] = true;
    }

    $in_group = array();

    foreach ($channels as $channel) {
        if (!$channel['pinned'] && ($channel['group_id'] > 0) && isset($known_groups[$channel['group_id']])) {
            $in_group[$channel['group_id']][] = $channel;
            continue;
        }

        if (!$channel['joined']) {
            $others[] = $channel;
        } elseif ($channel['pinned']) {
            $pinned[] = $channel;
        } else {
            $mine[] = $channel;
        }
    }

    // ── the rail ──

    $rail = '<nav class="ws-rail" aria-label="' . h(lang('Workspace')) . '">'
        . '<button type="button" class="ws-rail-item ws-rail-open" data-bs-toggle="offcanvas" data-bs-target="#ws-nav-drawer" aria-controls="ws-nav-drawer" title="' . h(lang('Open the menu')) . '" aria-label="' . h(lang('Open the menu')) . '">'
        . '<i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i></button>';

    foreach ($groups['top'] as $link) {
        $rail .= $rail_item($link);
    }

    $rail .= '<span class="ws-rail-sep" aria-hidden="true"></span>';

    // The pinned channels by their first letter; the channel screen itself
    // opens on the first of them.
    foreach (array_slice($pinned, 0, 8) as $channel) {
        $initial = mb_strtoupper(mb_substr(ltrim((string) $channel['name'], '#'), 0, 1));

        $rail .= '<a class="ws-rail-item ws-rail-channel" href="' . h($base . 'workspace.php?channel=' . (int) $channel['id']) . '" title="#' . h($channel['name']) . '" aria-label="#' . h($channel['name']) . '"'
            . (($channel['hex'] !== '') ? ' style="--ws-ch: ' . h($channel['hex']) . '" data-ws-colored="1"' : '') . '>'
            . '<span class="ws-rail-initial">' . h($initial) . '</span>'
            . (($channel['unread'] || $channel['mentions']) ? '<span class="ws-rail-dot' . ($channel['mentions'] ? ' ws-rail-dot-mention' : '') . '"></span>' : '')
            . '</a>';
    }

    $rail .= '<a class="ws-rail-item" href="' . h($base . 'workspace.php') . '" title="' . h(lang('Channels')) . '" aria-label="' . h(lang('Channels')) . '"><i class="bi bi-chat-square-text" aria-hidden="true"></i></a>';

    foreach (array('work', 'records') as $group) {
        $rail .= '<span class="ws-rail-sep" aria-hidden="true"></span>';

        foreach ($groups[$group] as $link) {
            if (!isset($link['rail']) || $link['rail']) {
                $rail .= $rail_item($link);
            }
        }
    }

    $rail .= '<span class="ws-rail-grow"></span>';

    foreach ($groups['foot'] as $link) {
        $rail .= $rail_item($link);
    }

    $rail .= '</nav>';

    // ── the drawer: the whole list ──

    $section = function ($title, $items) {
        return '<div class="ws-side-section">' . (($title !== '') ? '<div class="ws-side-title"><span>' . h($title) . '</span></div>' : '') . $items . '</div>';
    };

    $top = '';

    foreach ($groups['top'] as $link) {
        $top .= $list_item($link);
    }

    $list = $section('', $top);

    if (!empty($pinned)) {
        $list .= $section(lang('Pinned'), implode('', array_map($channel_link, $pinned)));
    }

    // The groups, one inside another, each with its channels; a group with
    // nothing to show in it is left out here.
    $children = array();

    foreach ($group_rows as $group) {
        $children[$group['parent_id']][] = $group;
    }

    $draw_group = function ($group, $depth) use (&$draw_group, $children, $in_group, $channel_link) {
        $inner = implode('', array_map($channel_link, $in_group[$group['id']] ?? array()));

        foreach ($children[$group['id']] ?? array() as $child) {
            $inner .= $draw_group($child, $depth + 1);
        }

        if ($inner === '') {
            return '';
        }

        return '<div class="ws-side-group' . (($depth > 0) ? ' ws-side-subgroup' : '') . '"' . (($group['hex'] !== '') ? ' style="--ws-group: ' . h($group['hex']) . '"' : '') . '>'
            . '<div class="ws-side-title ws-group-title"><span><i class="bi bi-folder2 me-1 ws-group-icon" aria-hidden="true"></i>' . h($group['name']) . '</span></div>'
            . $inner . '</div>';
    };

    foreach ($children[0] ?? array() as $group) {
        $drawn = $draw_group($group, 0);

        if ($drawn !== '') {
            $list .= '<div class="ws-side-section">' . $drawn . '</div>';
        }
    }

    $list .= $section(lang('My channels'), empty($mine) ? '<div class="small text-body-secondary px-2">' . h(lang('No channels yet.')) . '</div>' : implode('', array_map($channel_link, $mine)));

    if (!empty($others)) {
        $list .= $section(lang('Other channels'), implode('', array_map($channel_link, $others)));
    }

    foreach (array('work', 'records') as $group) {
        $items = '';

        foreach ($groups[$group] as $link) {
            $items .= $list_item($link);
        }

        $list .= $section($titles[$group], $items);
    }

    $foot = '';

    foreach ($groups['foot'] as $link) {
        $foot .= $list_item($link);
    }

    $drawer = '<div class="offcanvas offcanvas-start ws-nav-drawer" tabindex="-1" id="ws-nav-drawer" aria-labelledby="ws-nav-drawer-title">'
        . '<div class="offcanvas-header border-bottom">'
        . '<h5 class="offcanvas-title" id="ws-nav-drawer-title"><i class="bi bi-clipboard2-check me-2" aria-hidden="true"></i>' . h(lang('Workspace')) . '</h5>'
        . '<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="' . h(lang('Close')) . '"></button>'
        . '</div>'
        . '<div class="offcanvas-body p-0 d-flex flex-column">'
        . '<div class="ws-side-scroll">' . $list . '</div>'
        . (($foot !== '') ? '<div class="ws-side-foot">' . $foot . '</div>' : '')
        . '</div></div>';

    return $rail . $drawer;
}
