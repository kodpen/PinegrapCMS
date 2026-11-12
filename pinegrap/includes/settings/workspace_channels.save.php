<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what Channels, mail and voice writes: the voice message
 * transcripts and spoken answers with the pane's Save (ws_voice_*), then the
 * job of the card button that asked for the save: a channel template's
 * archive or bring back (ws_template_archive, ws_template_restore, value the
 * template's id), a GitHub link's removal (ws_github_link_remove, value the
 * link's id) or Link a repository from its drawer (ws_github_link_save, the
 * fields ws_github[...]).
 *
 * Runs in the scope of includes/settings/screen.php or of
 * pg_settings_apply(), after the token check and after post_value() is
 * defined. The checks and the writes are the ones Workspace Settings uses
 * (ws_voice_settings_check() and _write(), ws_templates_settings_post(),
 * ws_github_links_settings_post()), so a form still open there saves the
 * same way. The voice card is an administrator's: for anybody else its
 * fields are drawn disabled and not posted. A refused voice field is marked
 * and nothing is stored; a button's refusal after the save is a warning.
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

// The voice card: written only when its fields came and differ from what
// is stored, as a write clears the services' last errors and the wait.
$ws_voice = null;

if (((int) $ws_viewer['role'] === 0) && ws_voice_schema_ready() && isset($_POST['ws_voice_url'])) {
    $ws_voice = ws_voice_settings_input($_POST, 'ws_');

    if (ws_voice_settings_changed($ws_voice, ws_voice_config(true), ws_voice_speech_config(true))) {
        foreach (ws_voice_settings_check($ws_voice) as $ws_field => $ws_sentence) {
            $liveform->mark_error('ws_voice_' . $ws_field, $ws_sentence);
        }

        if ($liveform->check_form_errors()) {
            return;
        }
    } else {
        $ws_voice = null;
    }
}

$ws_warn = function ($sentence) use ($liveform) {
    $liveform->add_warning($sentence);
};

if ($ws_voice !== null) {
    ws_voice_settings_write($ws_voice, $liveform, $ws_warn);
}

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
