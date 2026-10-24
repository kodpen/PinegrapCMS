<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Connects the installation to Paraşüt with the authorization code the
 * operator pasted on the Paraşüt card of the commerce settings.
 *
 * Posted from the settings card's Connect button with the form token and
 * the parasut_* boxes as they stand. The code is exchanged for a token pair
 * (parasut_connect_with_code()); on success the client id, the secret and
 * the company id are saved and the sealed token is kept, so the card has
 * to be reopened before anything else on the page is saved. No password is
 * involved. The answer goes back as data for the card (format=json) or as a
 * small page for a direct post.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'));
    exit();
}

$user = validate_user();
validate_area_access($user, 'manager');

if (!$_POST) {
    output_error(lang('Use the button on the settings card.'));
    exit();
}

validate_token_field();

// Each box is one value; one posted as an array counts as not given.
pg_request_single_values(array('parasut_auth_code', 'parasut_client_id', 'parasut_client_secret', 'parasut_company_id', 'format'));

$lines = array();
$ok = false;

// What the card puts in its boxes and its status line after a connection,
// so the open page holds what was saved and its own Save writes it again.
$fill = array();
$status = array();

if (!function_exists('parasut_connect_with_code')) {
    $lines[] = lang('The Paraşüt client is not loaded on this installation.');
} else {
    $result = parasut_connect_with_code(
        (string) ($_POST['parasut_auth_code'] ?? ''),
        (string) ($_POST['parasut_client_id'] ?? ''),
        (string) ($_POST['parasut_client_secret'] ?? ''),
        (string) ($_POST['parasut_company_id'] ?? '')
    );

    $ok = !empty($result['success']);

    if ($ok) {
        // Whom and which company; also when the saved company was given up
        // for the authorized user's one company.
        $lines = array_merge($lines, parasut_connect_lines($result));

        $fill = array(
            'parasut_company_id' => (string) $result['company_id'],
            'parasut_auth_code' => '',
            'parasut_client_secret' => '',
        );
        // Read with the owner the token was written with: this request's
        // constants still hold the settings from before the connection (a
        // client id or company typed on the card and not saved yet), and
        // the token would read as someone else's.
        $status = function_exists('parasut_connection_line') && function_exists('parasut_connection_status')
            ? parasut_connection_line(parasut_connection_status(((string) ($result['owner'] ?? '') !== '') ? (string) $result['owner'] : null))
            : array();

        log_activity(lang(array('string' => 'parasut connection was authorized for company {var:1}', 'vars' => (string) $result['company_id'])),
            (string) ($_SESSION['sessionusername'] ?? ''));
    } else {
        $lines[] = (string) $result['error'];
    }
}

$tone = $ok ? 'success' : 'danger';
$icon = $ok ? 'bi-check-circle' : 'bi-x-circle';

if ((string) ($_POST['format'] ?? '') === 'json') {

    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    header('Cache-Control: private, no-store');

    echo json_encode(array(
        'tone' => $tone,
        'icon' => $icon,
        'lines' => array_values($lines),
        'note' => $ok ? '' : lang('Nothing was saved.'),
        'fill' => (object) $fill,
        'status' => (object) $status,
    ), JSON_UNESCAPED_UNICODE);

    exit();
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

echo '<!DOCTYPE html><html lang="' . h(defined('SOFTWARE_LANGUAGE') ? SOFTWARE_LANGUAGE : 'tr') . '"><head><meta charset="utf-8"><title>' . h(lang('Connect')) . '</title>'
    . '<link rel="stylesheet" href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY) . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css">'
    . '<link rel="stylesheet" href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY) . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css">'
    . '</head><body class="p-4"><div class="container" style="max-width:640px">'
    . '<div class="alert alert-' . $tone . ' d-flex align-items-start gap-2"><i class="bi ' . $icon . ' fs-4"></i><div>';

foreach ($lines as $line) {
    echo '<div>' . h($line) . '</div>';
}

echo '</div></div>'
    . '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.close()">' . h(lang('Close')) . '</button>'
    . '</div></body></html>';
