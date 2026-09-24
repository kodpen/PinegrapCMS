<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - tries the e-document provider picked on the commerce settings card.
 *
 * Posted from the settings form (formaction), so it arrives with the form
 * token and everything typed on the card: the provider radio and that
 * provider's credential boxes. The boxes are tried as typed, a box left
 * empty falls back to what is stored, and nothing is saved here - saving is
 * the settings form's own button. The answer is a small page in the new tab
 * the button opened.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user, 'settings')) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

if (!$_POST) {
    output_error(lang('Use the button on the settings card.'));
    exit();
}

validate_token_field();

$code = isset($_POST['edoc_provider']) ? (string) $_POST['edoc_provider'] : '';
$lines = array();
$ok = false;
$pending = false;

if ($code === '') {
    $lines[] = lang('No provider is selected; documents stay PDF.');
    $ok = true;
} elseif (!erp_edoc_load($code)) {
    $lines[] = lang('Unknown e-document provider.');
} else {
    $info = erp_edoc_info($code);

    // What was typed over what is stored, the way the save would merge them.
    $credentials = erp_edoc_credentials($code);
    $typed_any = false;

    foreach (erp_edoc_fields($code) as $field) {
        $typed = trim((string) ($_POST['edoc_' . $code . '_' . $field['name']] ?? ''));

        // A text box comes back filled with the stored value, so only a
        // changed value counts as typed.
        if (($typed !== '') && ($typed !== trim((string) ($credentials[$field['name']] ?? '')))) {
            $credentials[$field['name']] = $typed;
            $typed_any = true;
        }
    }

    if (!erp_edoc_supports('ping', $code)) {
        $lines[] = lang('This driver has no connection test.');
    } else {
        // With nothing typed the stored credentials were tried, so the
        // "last checked" line on the card may record the answer; with typed
        // boxes the answer says nothing about what is stored.
        $result = $typed_any
            ? ((array) call_user_func('erp_edoc_' . $code . '_ping', $credentials)) + array('success' => false, 'message' => '', 'details' => array())
            : erp_edoc_test($code);
        $ok = !empty($result['success']);
        $pending = !empty($result['details']['pending']);
        $lines[] = $info['label'] . ': ' . (string) $result['message'];

        if (($code === 'parasut') && !$ok) {
            $lines[] = lang('The Paraşüt boxes on this card are read from what is saved; save the settings first when you have just changed them.');
        }
    }
}

$tone = $ok ? 'success' : ($pending ? 'info' : 'danger');
$icon = $ok ? 'bi-check-circle' : ($pending ? 'bi-info-circle' : 'bi-x-circle');

// The settings card asks for the answer as data and prints it under the
// button. The page below is what a direct post gets - the card used to open
// it in a tab, and a browser that refuses to open one left the operator
// with no answer at all.
if ((string) ($_POST['format'] ?? '') === 'json') {

    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    header('Cache-Control: private, no-store');

    echo json_encode(array(
        'tone' => $tone,
        'icon' => $icon,
        'lines' => array_values($lines),
        'note' => lang('Nothing was saved; use Save on the settings card to keep the credentials.'),
    ), JSON_UNESCAPED_UNICODE);

    exit();
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

echo '<!DOCTYPE html><html lang="' . h(defined('SOFTWARE_LANGUAGE') ? SOFTWARE_LANGUAGE : 'tr') . '"><head><meta charset="utf-8"><title>' . h(lang('Test the connection')) . '</title>'
    . '<link rel="stylesheet" href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY) . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css">'
    . '<link rel="stylesheet" href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY) . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css">'
    . '</head><body class="p-4"><div class="container" style="max-width:640px">'
    . '<div class="alert alert-' . $tone . ' d-flex align-items-start gap-2"><i class="bi ' . $icon . ' fs-4"></i><div>';

foreach ($lines as $line) {
    echo '<div>' . h($line) . '</div>';
}

echo '</div></div>'
    . '<p class="text-body-secondary small">' . h(lang('Nothing was saved; close this tab and use Save on the settings card to keep the credentials.')) . '</p>'
    . '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.close()">' . h(lang('Close')) . '</button>'
    . '</div></body></html>';
