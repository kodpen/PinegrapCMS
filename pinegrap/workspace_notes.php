<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the person's own notes: the ones they wrote, the ones kept from
 * messages and the ones colleagues handed them, with calculation sheets
 * among them. Drawn by assets/js/workspace.js; the rules are in
 * includes/workspace/notes.php.
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
        'title' => lang('My Notes'),
        'extra classes' => 'workspace workspace_notes',
        'icon' => 'workspace',
        'heading' => lang('My Notes'),
        'heading_description' => lang('Your own notes and calculation sheets, and the messages you kept.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid p-0">
    <div class="ws-shell">
        ' . ws_screen_rail($viewer, 'notes') . '
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
' . ws_screen_assets($viewer, 'notes') .
output_footer();
