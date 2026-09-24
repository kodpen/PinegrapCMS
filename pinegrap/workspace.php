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

// A team that has never used the workspace starts with one channel everybody
// is in, rather than an empty screen.
ws_ensure_first_channel($viewer);

echo
pg_page_shell([
        'title' => lang('Workspace'),
        'extra classes' => 'workspace workspace_channels',
        'icon' => 'workspace',
        'heading' => lang('Channels'),
        'heading_description' => lang('Conversations about customers and work, with the tasks that come out of them.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid p-0">
    <div id="ws-root" class="ws-app">
        <div class="ws-empty m-auto">' . h(lang('Loading…')) . '</div>
    </div>
</main>
' . ws_screen_assets($viewer, 'channels', array('open_channel' => (int) ($_GET['channel'] ?? 0))) .
output_footer();
