<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The picker in frame mode, for the rich text editor on a site page in Edit
 * mode: the site page loads none of the panel's stylesheets or scripts, so
 * its Link and Image dialogs open this page in a same-origin frame inside a
 * native <dialog> (pgCkeditorPicker, assets/js/pg_ckeditor_picker.js). This
 * page prints the panel's head, the picker and the bridge, and
 * pgCkeditorPicker.frameChild() opens the picker with the options the
 * opener posts and posts the pick back.
 *
 *   GET ?kind=<page|file|image>#<key>
 *
 * The key is the opener's, made for that opening; every message carries it.
 * The rights are the picker endpoint's (picker.php): a signed-in user whose
 * role may use the kind.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');
header('X-Frame-Options: SAMEORIGIN');

$user = validate_user();

$kind_name = pg_editor_frame_kind($_GET['kind'] ?? '');
$kinds = pg_picker_kinds();

if (($kind_name === '') || !isset($kinds[$kind_name])) {
    http_response_code(404);
    exit(h(lang('Page not found.')));
}

if (pg_picker_access_verdict($kind_name, $user['role'], ((int) $user['role'] >= 3) && no_acl_check($user['id'])) !== 'ok') {
    log_activity(lang('access denied because user attempted to access area for higher role'), $_SESSION['sessionusername'] ?? '');
    http_response_code(403);
    exit(h(lang('Access denied')));
}

echo output_header_secure(array('title' => lang('Select'), 'extra classes' => 'pg-picker-frame'))
    . '<style>html, body.pg-picker-frame { background: transparent !important; }</style>'
    . '<div id="pg_picker_frame" data-kind="' . h($kind_name) . '" hidden></div>'
    . pg_editor_bridge_script()
    . '<script>window.addEventListener("DOMContentLoaded", function () { if (window.pgCkeditorPicker && window.pgCkeditorPicker.frameChild) { window.pgCkeditorPicker.frameChild(); } });</script>'
    . pg_output_email_off_close()
    . '</body></html>';
