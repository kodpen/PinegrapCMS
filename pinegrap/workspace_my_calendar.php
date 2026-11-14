<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - My calendar: the Work Calendar opened on the reader alone (own
 * tasks, the plan items they are in, bookings, own iCal layer), by month,
 * week or as a list. Read by ws_my_calendar() (includes/workspace/
 * my_calendar.php), drawn by assets/js/workspace_my_calendar.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// Team accounts only: a channel guest is no member and has no calendar.
$viewer = ws_screen_gate($user);

echo
pg_page_shell([
        'title' => lang('My calendar'),
        'extra classes' => 'workspace workspace_my_calendar',
        'icon' => 'workspace',
        'heading' => lang('My calendar'),
        'heading_description' => lang('Only your own work and plans; nobody else sees them.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid p-0">
    <div class="ws-shell">
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
' . ws_screen_assets($viewer, 'my_calendar', array('my_calendar' => array('strings' => ws_my_calendar_js_strings() + ws_personal_plan_js_strings() + ws_personal_plan_steps_js_strings()))) .
output_footer();
