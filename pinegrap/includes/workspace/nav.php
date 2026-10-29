<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the way around it: one list of the channels, the discussions,
 * the groups and the workspace's own screens, in the Workspace panel of the
 * main menu on every panel page (assets/js/workspace_side.js draws it). This
 * file decides what the list holds and for whom, and builds its data: printed
 * with the page on the workspace's own screens, asked for with the ws_side
 * action (includes/workspace/actions.php) the first time the panel opens
 * anywhere else.
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
 * The places of the workspace, in the groups the list shows them in, each
 * under the gate of the screen it opens.
 *
 * @param array      $viewer
 * @param array|null $flags  what the gates found, for a test (notes,
 *                           calendar_feed, actions, messages, flows, mcp, base);
 *                           null asks them
 * @return array[] top, work, records, foot => items (key, label, icon, url)
 */
function ws_nav_groups($viewer, $flags = null)
{
    if ($flags === null) {
        $flags = array(
            'notes'         => function_exists('ws_notes_ready') && ws_notes_ready(),
            // Every member keeps tables of their own (wtables.php).
            'wtables'       => function_exists('ws_wtables_ready') && ws_wtables_ready(),
            'calendar_feed' => function_exists('ws_calendar_feed_ready') && ws_calendar_feed_ready(),
            // Staff's scheduled actions, until Flows lists them
            // (ws_flows_scheduled()); their scheduled messages stay here.
            'actions'       => function_exists('ws_can_schedule') && ws_can_schedule($viewer)
                && !(function_exists('ws_flows_scheduled') && ws_flows_scheduled($viewer)),
            'messages'      => function_exists('ws_can_schedule_messages') && ws_can_schedule_messages($viewer),
            // Staff draw flows, and whoever may run a channel's live flow
            // finds it here too (flows.php).
            'flows'         => function_exists('ws_flows_nav_visible') && ws_flows_nav_visible($viewer),
            // Each person's own accounts on the MCP servers and their tool
            // forms, while a server is on (mcp_form.php).
            'mcp'           => function_exists('ws_mcp_active_servers') && (bool) ws_mcp_active_servers(),
            // A mailbox writes into a channel the person reads (mail_list.php).
            'mail'          => function_exists('ws_mail_list_visible') && ws_mail_list_visible($viewer),
        );
    }

    $base = isset($flags['base']) ? (string) $flags['base'] : OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

    $groups = array(
        'top' => array(
            array('key' => 'home', 'label' => lang('Overview'), 'icon' => 'bi-house', 'url' => $base . 'workspace.php?view=home'),
            array('key' => 'inbox', 'label' => lang('Inbox'), 'icon' => 'bi-inbox', 'url' => $base . 'workspace.php?view=home&inbox=1', 'inbox' => true),
        ),
        'work' => array(
            array('key' => 'tasks', 'label' => lang('My Tasks'), 'icon' => 'bi-check2-square', 'url' => $base . 'workspace_tasks.php'),
        ),
        'records' => array(
            array('key' => 'timeline', 'label' => lang('Decision Timeline'), 'icon' => 'bi-clock-history', 'url' => $base . 'workspace_timeline.php'),
            array('key' => 'archived', 'label' => lang('Archived channels'), 'icon' => 'bi-archive', 'url' => $base . 'workspace.php?view=home&panel=archived'),
        ),
        'foot' => array(),
    );

    if (!empty($flags['mail'])) {
        $groups['work'][] = array('key' => 'mail', 'label' => lang('E-mails'), 'icon' => 'bi-envelope', 'url' => $base . 'workspace_mail.php');
    }

    if (!empty($flags['notes'])) {
        $groups['work'][] = array('key' => 'notes', 'label' => lang('My Notes'), 'icon' => 'bi-journal-text', 'url' => $base . 'workspace_notes.php');
    }

    if (!empty($flags['wtables'])) {
        $groups['work'][] = array('key' => 'wtables', 'label' => lang('Tables'), 'icon' => 'bi-table', 'url' => $base . 'workspace_wtables.php');
    }

    $groups['work'][] = array('key' => 'board', 'label' => lang('Planning Board'), 'icon' => 'bi-calendar-week', 'url' => $base . 'workspace_board.php');
    $groups['work'][] = array('key' => 'calendar', 'label' => lang('Work Calendar'), 'icon' => 'bi-calendar3', 'url' => $base . 'workspace_calendar.php');

    if (!empty($flags['calendar_feed'])) {
        $groups['work'][] = array('key' => 'calendar_feed', 'label' => lang('Calendar subscription'), 'icon' => 'bi-calendar-plus', 'url' => $base . 'workspace_calendar_feed.php');
    }

    if (!empty($flags['flows'])) {
        $groups['work'][] = array('key' => 'flows', 'label' => lang('Flows'), 'icon' => 'bi-diagram-3', 'url' => $base . 'workspace_flows.php');
    }

    // Scheduled actions are staff's (scheduled.php); everybody else finds
    // their own scheduled messages on the same screen.
    if (!empty($flags['actions'])) {
        $groups['work'][] = array('key' => 'scheduled', 'label' => lang('Scheduled actions'), 'icon' => 'bi-alarm', 'url' => $base . 'workspace.php?view=scheduled');
    } elseif (!empty($flags['messages'])) {
        $groups['work'][] = array('key' => 'scheduled', 'label' => lang('Scheduled messages'), 'icon' => 'bi-alarm', 'url' => $base . 'workspace.php?view=scheduled');
    }

    if (!empty($flags['mcp'])) {
        $groups['work'][] = array('key' => 'mcp', 'label' => lang('My MCP connections'), 'icon' => 'bi-plug', 'url' => $base . 'workspace_mcp.php');
    }

    // The private channels' audit is staff's (ws_viewer(): role below 3).
    if ((int) $viewer['role'] < 3) {
        $groups['records'][] = array('key' => 'audit', 'label' => lang('Private channels'), 'icon' => 'bi-shield-lock', 'url' => $base . 'workspace.php?view=home&panel=audit');
    }

    if (!empty($viewer['settings'])) {
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
 * Which place of the workspace a screen is, by its file: the list marks it,
 * carries its data in the page and opens by itself there.
 *
 * @param string $script the screen's path or file name ($_SERVER['SCRIPT_NAME'])
 * @return string the key of ws_nav_groups(), 'channels' for the channel
 *                screen, '' for any other file
 */
function ws_side_screen($script)
{
    $screens = array(
        'workspace.php'               => 'channels',
        'workspace_tasks.php'         => 'tasks',
        'workspace_notes.php'         => 'notes',
        'workspace_wtables.php'       => 'wtables',
        'workspace_board.php'         => 'board',
        'workspace_calendar.php'      => 'calendar',
        'workspace_calendar_feed.php' => 'calendar_feed',
        'workspace_timeline.php'      => 'timeline',
        'workspace_flows.php'         => 'flows',
        'workspace_mcp.php'           => 'mcp',
        'workspace_mail.php'          => 'mail',
        'workspace_settings.php'      => 'settings',
        // A template is written from the settings (templates.php).
        'workspace_template.php'      => 'settings',
    );

    $file = basename(str_replace('\\', '/', (string) $script));

    return $screens[$file] ?? '';
}

/**
 * The places as the list draws them: the groups in their order, each item
 * with only what the browser needs.
 *
 * @param array $groups ws_nav_groups()
 * @return array top, work, records, foot => [key, label, icon, url, inbox]
 */
function ws_side_nav($groups)
{
    $out = array();

    foreach (array('top', 'work', 'records', 'foot') as $group) {
        $out[$group] = array();

        foreach ((array) ($groups[$group] ?? array()) as $item) {
            $out[$group][] = array(
                'key'   => (string) $item['key'],
                'label' => (string) $item['label'],
                'icon'  => (string) $item['icon'],
                'url'   => (string) $item['url'],
                'inbox' => !empty($item['inbox']),
            );
        }
    }

    return $out;
}

/**
 * The keys of the words the list uses (assets/js/workspace_side.js), each
 * read from the screen's own set so a word is written in one place.
 *
 * @return string[]
 */
function ws_side_js_keys()
{
    return array(
        'search_messages', 'new_channel', 'tour_start', 'pal_shortcut', 'pal_open', 'pinned', 'pin', 'unpin',
        'th_section', 'th_show_all', 'th_show_fewer', 'my_channels', 'other_channels', 'no_channels',
        'side_expand', 'side_collapse', 'grp_expand', 'grp_collapse', 'grp_access', 'grp_empty', 'grp_new',
        'more', 'loading', 'error_generic',
    );
}

/**
 * The words of the list, translated.
 *
 * @return array key => text
 */
function ws_side_js_strings()
{
    $all = array_merge(
        ws_js_strings(),
        ws_threads_js_strings(),
        ws_groups_js_strings(),
        function_exists('ws_palette_js_strings') ? ws_palette_js_strings() : array(),
        function_exists('ws_tour_js_strings') ? ws_tour_js_strings() : array()
    );

    $out = array();

    foreach (ws_side_js_keys() as $key) {
        if (isset($all[$key])) {
            $out[$key] = (string) $all[$key];
        }
    }

    return $out;
}

/**
 * Everything the list draws, for one person: their channels, discussions and
 * groups as the channel screen gets them (ws_bootstrap), the places their
 * rights open and the words.
 *
 * Only the access functions choose what is in it - ws_channels_for() (public
 * channels and the private ones the person is in), ws_groups_for() (for
 * anybody but staff only the groups holding such a channel),
 * ws_threads_for() (the person's own discussions in channels they still
 * read) and ws_nav_groups() - so the browser draws what it gets and hides
 * nothing itself.
 *
 * @param array  $viewer ws_viewer()
 * @param string $screen ws_side_screen() of the page, '' elsewhere
 * @return array empty for anybody the workspace does not let in
 */
function ws_side_payload($viewer, $screen)
{
    if (empty($viewer['member'])) {
        return array();
    }

    return array(
        'v'        => 1,
        'screen'   => (string) $screen,
        'urls'     => array('workspace' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php'),
        'inbox'    => ws_inbox_unread_count((int) $viewer['id']),
        'nav'      => ws_side_nav(ws_nav_groups($viewer)),
        'titles'   => ws_nav_group_titles(),
        'channels' => ws_channels_for($viewer),
        'groups'   => ws_groups_for($viewer),
        'threads'  => ws_threads_for($viewer),
        'strings'  => ws_side_js_strings(),
    );
}

/**
 * The list's place in the Workspace panel of the main menu: an empty box
 * with grey rows standing for the list, which the script fills.
 *
 * @param array $args api (the panel's api.php), target ('_parent' in the
 *                    page toolbar's frame), auto (open the panel by itself),
 *                    loading, error, retry (the words shown before the words
 *                    of the list have arrived)
 * @return string
 */
function ws_side_markup($args)
{
    $attributes = ' data-ws-side-api="' . h((string) ($args['api'] ?? '')) . '"'
        . ' data-ws-side-loading="' . h((string) ($args['loading'] ?? '')) . '"'
        . ' data-ws-side-error="' . h((string) ($args['error'] ?? '')) . '"'
        . ' data-ws-side-retry="' . h((string) ($args['retry'] ?? '')) . '"';

    if (($args['target'] ?? '') !== '') {
        $attributes .= ' data-ws-side-target="' . h((string) $args['target']) . '"';
    }

    if (!empty($args['auto'])) {
        $attributes .= ' data-pg-menu-auto';
    }

    $rows = str_repeat('<div class="ws-skeleton-row" aria-hidden="true"><span class="ws-skeleton-dot"></span><span class="ws-skeleton-bar"></span></div>', 6);

    return '<div id="ws-menu-side" class="ws-menu-side" data-ws-side' . $attributes . '>'
        . '<div class="ws-skeleton ws-side-loading" role="status" aria-busy="true"><span class="visually-hidden">' . h((string) ($args['loading'] ?? '')) . '</span>' . $rows . '</div>'
        . '</div>';
}

/**
 * The list's data in the page, for the script to draw at once.
 *
 * @param array $payload ws_side_payload()
 * @return string '' for an empty payload or one that cannot be written
 */
function ws_side_inline($payload)
{
    if (empty($payload)) {
        return '';
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    if (!is_string($json) || ($json === '')) {
        return '';
    }

    return '<script type="application/json" id="ws-side-data">' . $json . '</script>';
}
