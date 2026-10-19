<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the decision timeline: every decision taken in the channels the
 * person may read, newest first, grouped by day, so nobody has to walk the
 * channels one by one to see what was decided. The list is drawn by
 * assets/js/workspace.js; the rules are in includes/workspace/timeline.php.
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
        'title' => lang('Decision Timeline'),
        'extra classes' => 'workspace workspace_timeline',
        'icon' => 'workspace',
        'heading' => lang('Decision Timeline'),
        'heading_description' => lang('Every decision taken in the channels you can read, newest first, on one screen.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid p-0">
    <div class="ws-shell">
        ' . ws_screen_rail($viewer, 'timeline') . '
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
' . ws_screen_assets($viewer, 'timeline') .
output_footer();
