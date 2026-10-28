<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - flows: the list of the person's flows and of the live ones
 * their channels may run, and the canvas a flow is drawn on (boxes and the
 * arrows between them), saved as a draft and published. Staff build
 * flows; somebody who may only run one sees it without editing. Drawn by
 * assets/js/workspace_flow.js; what it may do is decided by
 * includes/workspace/flows.php.
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
        'title' => lang('Flows'),
        'extra classes' => 'workspace workspace_flows',
        'icon' => 'workspace',
        'heading' => lang('Flows'),
        'heading_description' => lang('A process drawn as boxes and arrows: what starts it and the tasks that follow one another.'),
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
' . ws_screen_assets($viewer, 'flows') .
output_footer();
