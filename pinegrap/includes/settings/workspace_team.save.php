<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what Team and working hours writes: the Workspace defaults.
 *
 * Runs in the scope of includes/settings/screen.php or of
 * pg_settings_apply(), after the token check and after post_value() is
 * defined. The checks and the write are ws_settings_defaults_save()'s, which
 * a form still open on Workspace Settings asks too; a refused hour is marked
 * on its field and nothing is written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// The card's fields missing from a hand-made request leave the defaults
// alone: an empty box means the default, so a missing one would reset it.
if (isset($_POST['ws_day_hours'], $_POST['ws_default_task_hours'])) {

    $ws_refused = ws_settings_defaults_save(post_value('ws_day_hours'), post_value('ws_workdays'), post_value('ws_default_task_hours'));

    foreach ($ws_refused as $ws_field => $ws_sentence) {
        $liveform->mark_error('ws_' . $ws_field, $ws_sentence);
    }
}
