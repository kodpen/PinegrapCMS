<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the tasks: the ones given to me, the ones I gave, my
 * departments' and, for somebody who holds the team board, everyone's. The
 * list and the task drawer are drawn by assets/js/workspace.js.
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
        'title' => lang('My Tasks'),
        'extra classes' => 'workspace workspace_tasks',
        'icon' => 'workspace',
        'heading' => lang('My Tasks'),
        'heading_description' => lang('The work handed to you and the work you handed out, with its due dates.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid p-0">
    <div class="ws-shell">
        ' . ws_screen_rail($viewer, 'tasks') . '
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
' . ws_screen_assets($viewer, 'tasks') .
output_footer();
