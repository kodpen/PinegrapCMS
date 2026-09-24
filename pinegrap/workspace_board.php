<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the planning board: people as rows, days as columns, the work
 * and the leave on each day and how full the day is. Somebody without the
 * team board sees themselves and the departments they lead. Drawn by
 * assets/js/workspace.js.
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
        'title' => lang('Planning Board'),
        'extra classes' => 'workspace workspace_board',
        'icon' => 'workspace',
        'heading' => lang('Planning Board'),
        'heading_description' => lang('Who has what on which day, and where the week is too full.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid p-0">
    <div class="ws-shell">
        ' . ws_screen_rail($viewer, 'board') . '
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
' . ws_screen_assets($viewer, 'board') .
output_footer();
