<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what Mailboxes writes: a mailbox from its drawer
 * (ws_mail_box_save, value its id, 0 for a new one, the fields
 * ws_mail[<id>][...]), then the job of the card button that asked for the
 * save: a mailbox's Test connection (ws_mail_test) or .eml file
 * (ws_mail_eml, posted with ws_mail_eml_file by a form of its own).
 *
 * Runs in the scope of includes/settings/screen.php or of
 * pg_settings_apply(), after the token check and after post_value() is
 * defined. The box is checked before anything is written
 * (ws_mail_box_save_check()), and a refusal is marked on its field; a
 * button's refusal after the save is a warning.
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

// A mailbox's drawer asked for this save: its own fields, by its id,
// checked before anything is written; a refusal is marked on its field.
$ws_box_id = post_value('ws_mail_box_save');
$ws_box = null;
$ws_box_checked = null;

if (($ws_box_id !== null) && is_scalar($ws_box_id) && ws_mail_ready()) {
    $ws_box_id = max(0, (int) $ws_box_id);
    $ws_box = ($ws_box_id > 0) ? ws_mail_box($ws_box_id) : null;
    // A WhatsApp or SMS account's box is kept by its own card.
    $ws_box = ws_mail_box_is_phone($ws_box) ? null : $ws_box;

    if (($ws_box_id > 0) && !$ws_box) {
        $liveform->mark_error('ws_mail[' . $ws_box_id . '][name]', lang('That mailbox could not be found.'));
        return;
    }

    $ws_box_checked = ws_mail_box_save_check($ws_viewer, $ws_box, ws_mail_settings_dialog_input($_POST, $ws_box_id));

    if (!$ws_box_checked['ok']) {
        // A field posted as a list (the routes, the readers of a box kept in
        // no channel) is marked by its list name.
        $liveform->mark_error('ws_mail[' . $ws_box_id . '][' . $ws_box_checked['field'] . (in_array($ws_box_checked['field'], array('route_match', 'room_people'), true) ? '][' : '') . ']', h($ws_box_checked['error']));
        return;
    }
}

$ws_warn = function ($sentence) use ($liveform) {
    $liveform->add_warning($sentence);
};

if ($ws_box_checked !== null) {
    ws_mail_box_save_write($ws_viewer, $ws_box, $ws_box_checked, (string) ($_SESSION['sessionusername'] ?? ''));
    $liveform->add_notice(lang('The mailbox was saved.'));
}

// A mailbox's Test connection, and Bring in an .eml file (a form of its own
// in the box's drawer, posted to this category's page with its file).
$ws_value = post_value('ws_mail_test');

if (($ws_value !== null) && is_scalar($ws_value)) {
    ws_mail_settings_post($ws_viewer, 'mail_test', $liveform, array('box_id' => (int) $ws_value), null, $ws_warn);
}

$ws_value = post_value('ws_mail_eml');

if (($ws_value !== null) && is_scalar($ws_value)) {
    ws_mail_settings_post($ws_viewer, 'mail_eml', $liveform, array('box_id' => (int) $ws_value), $_FILES['ws_mail_eml_file'] ?? array(), $ws_warn);
}
