<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the channels: the team's conversations, with people, records
 * and tasks tagged in them. Everything on the screen is drawn by
 * assets/js/workspace.js; this page only opens the door and hands it its
 * settings.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

// A mailbox kept in no channel has no place among the channels: an address
// that names its room leads to the E-mails screen (mail_room.php).
$ws_mail_room_url = ws_mail_room_redirect($viewer, $_GET, OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/');

if ($ws_mail_room_url !== '') {
    go($ws_mail_room_url);
}

// A team that has never used the workspace starts with one channel everybody
// is in, rather than an empty screen.
ws_ensure_first_channel($viewer);

// The heading of the view asked for; the script swaps it as the view changes.
$headings = ws_channels_screen_headings();
$heading = ((($_GET['view'] ?? '') === 'home') && empty($_GET['channel'])) ? $headings['home'] : $headings['channel'];

echo
pg_page_shell([
        'title' => lang('Workspace'),
        'extra classes' => 'workspace workspace_channels',
        'icon' => 'workspace',
        'heading' => $heading['heading'],
        'heading_description' => $heading['description'],
        'cancel' => false,
        'tour' => function_exists('ws_tour_key') ? ws_tour_key() : '',
        // Today is drawn in the new dashboard's cards (includes/workspace/home.php).
        'head' => ws_today_stylesheets(),
    ]) . '
<main id="content" class="container-fluid p-0">
    <div id="ws-root" class="ws-app">
        <div class="ws-skeleton ws-boot" role="status" aria-busy="true"><span class="visually-hidden">' . h(lang('Loading…')) . '</span>'
            . str_repeat('<div class="ws-skeleton-row" aria-hidden="true"><span class="ws-skeleton-dot"></span><span class="ws-skeleton-bar"></span></div>', 5) . '</div>
    </div>
</main>
' . ws_screen_assets($viewer, 'channels', array(
    'open_channel' => (int) ($_GET['channel'] ?? 0),
    'headings'     => $headings,
    'today_look'   => ws_today_look_for($user),
    // Back from connecting an account to an MCP server, or asked to offer it.
    'mcp'          => function_exists('ws_mcp_screen_config') ? ws_mcp_screen_config($viewer) : null,
)) .
output_footer();
