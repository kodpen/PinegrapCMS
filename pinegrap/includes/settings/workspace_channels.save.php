<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what Channels writes: the link previews switch with the
 * pane's Save, then the job of the card button that asked for the save: a channel template's archive or bring back (ws_template_archive,
 * ws_template_restore, value the template's id), a GitHub link's removal (ws_github_link_remove, value the
 * link's id) or Link a repository from its drawer (ws_github_link_save, the
 * fields ws_github[...]).
 *
 * Runs in the scope of includes/settings/screen.php or of
 * pg_settings_apply(), after the token check and after post_value() is
 * defined. The checks and the writes are the ones Workspace Settings uses
 * (ws_templates_settings_post(), ws_github_links_settings_post()), so a form
 * still open there saves the same way. A button's refusal after the save is
 * a warning.
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

$ws_viewer = ws_viewer($user);

$ws_warn = function ($sentence) use ($liveform) {
    $liveform->add_warning($sentence);
};

// The link previews switch (link_preview.php), with the pane's Save.
ws_link_preview_settings_save($ws_viewer, $_POST);

// A channel template's Archive or Bring it back.
foreach (array('ws_template_archive' => true, 'ws_template_restore' => false) as $ws_button => $ws_archive) {
    $ws_value = post_value($ws_button);

    if (($ws_value !== null) && is_scalar($ws_value)) {
        ws_templates_settings_post($ws_viewer, $liveform, (int) $ws_value, $ws_archive, $ws_warn);
    }
}

// A GitHub link's Remove, or Link a repository from its drawer.
$ws_value = post_value('ws_github_link_remove');

if (($ws_value !== null) && is_scalar($ws_value) && ws_github_links_ready()) {
    ws_github_links_settings_post($ws_viewer, 'github_link_remove', $liveform, array('link_id' => (int) $ws_value), $ws_warn);
}

if ((post_value('ws_github_link_save') !== null) && ws_github_links_ready()) {
    ws_github_links_settings_post($ws_viewer, 'github_link_save', $liveform, ws_github_links_settings_dialog_input($_POST), $ws_warn);
}
