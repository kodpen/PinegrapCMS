<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the month calendar: the work falling due each day, the plan
 * items, the channels opened and how much was talked about, with the month's
 * figures beside it - who carries the most work, who the least, what is late.
 * Drawn by assets/js/workspace.js.
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

echo
pg_page_shell([
        'title' => lang('Work Calendar'),
        'extra classes' => 'workspace workspace_calendar',
        'icon' => 'workspace',
        'heading' => lang('Work Calendar'),
        'heading_description' => lang('The month at a glance: work falling due, conversations and who carries what.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid p-0">
    <div class="ws-shell">
        ' . ws_screen_rail($viewer, 'calendar') . '
        <div class="ws-shell-main">
            <div class="row">
                <div class="col-12">
                    <div id="ws-root">
                        <div class="ws-empty">' . h(lang('Loading…')) . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
' . ws_screen_assets($viewer, 'calendar') .
output_footer();
