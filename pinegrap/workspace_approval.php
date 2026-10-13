<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page an approval link leads to (includes/workspace/approval_guests.php):
 * somebody outside the team reads what they are asked to approve, then
 * approves it or rejects it with a reason. No account and no session: the
 * token in the address is the credential, and it opens this one request.
 *
 *   GET  workspace_approval.php?t=...   the request, or where it stands
 *   POST workspace_approval.php         t, decision (approve | reject), note
 *
 * Opening the page decides nothing; the POST does, and it spends the link.
 * After an answer the browser is sent back to the address, so reloading
 * the page does not send the answer again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// No session: the token is the credential, and a panel cookie in the same
// browser must never decide what the page shows or who answers. $_SESSION
// stays an array so the shared code that looks into it finds nothing.
define('PG_NO_SESSION', true);

$_SESSION = array();

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// Nothing of the request is kept by a browser or a proxy, indexed, framed
// by another site or passed on as the referrer.
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');

$self = PATH . SOFTWARE_DIRECTORY . '/workspace_approval.php';
$token = '';
$error = '';
$note = '';
$counted = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = (isset($_POST['t']) && is_string($_POST['t'])) ? $_POST['t'] : '';
    $note = (isset($_POST['note']) && is_string($_POST['note'])) ? $_POST['note'] : '';

    $result = ws_approval_guest_decide($token, (isset($_POST['decision']) && is_string($_POST['decision'])) ? $_POST['decision'] : '', $note);

    if ($result['ok']) {
        header('Location: ' . $self . '?t=' . rawurlencode($token), true, 303);
        exit;
    }

    $error = (string) $result['error'];
    $counted = true;
} else {
    $token = (isset($_GET['t']) && is_string($_GET['t'])) ? $_GET['t'] : '';
}

$view = ws_approval_guest_view($token, !$counted);

if ($view['state'] === 'invalid') {
    http_response_code(404);
}

$software = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;
$version = function ($path) {
    return (string) @filemtime(PG_FUNCTIONS_DIR . $path);
};
$site = defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : '';

// The body of the card for each state the link can be in.
$card = '';

if ($view['state'] === 'open') {
    $card .= '<h1 class="pg-approval-title">' . h($view['title']) . '</h1>'
        . '<p class="pg-approval-meta">'
        . h(lang(array('string' => '{var:1} asks {var:2} to approve this.', 'vars' => array($view['asker'], $view['name']))))
        . (($view['closes'] !== '') ? ' ' . h(lang(array('string' => 'An answer is needed by {var:1}.', 'vars' => $view['closes']))) : '')
        . '</p>';

    if ($view['details'] !== '') {
        $card .= '<div class="pg-approval-details">' . $view['details'] . '</div>';
    }

    if ($error !== '') {
        $card .= '<div class="alert alert-warning py-2" role="alert">' . h($error) . '</div>';
    }

    $card .= '<form method="post" action="' . h($self) . '" class="pg-approval-form">'
        . '<input type="hidden" name="t" value="' . h($token) . '">'
        . '<label class="form-label" for="pg-approval-note">' . h(lang('Your note')) . '</label>'
        . '<textarea class="form-control" id="pg-approval-note" name="note" rows="3" maxlength="' . (int) WS_APPROVAL_GUEST_NOTE_MAX . '">' . h($note) . '</textarea>'
        . '<div class="form-text">' . h(lang('Optional when you approve. When you reject it, say why.')) . '</div>'
        . '<div class="pg-approval-buttons">'
        . '<button type="submit" name="decision" value="approve" class="btn btn-success"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang('Approve')) . '</button>'
        . '<button type="submit" name="decision" value="reject" class="btn btn-outline-danger"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>' . h(lang('Reject')) . '</button>'
        . '</div>'
        . '<p class="pg-approval-fine">' . h(lang(array('string' => 'Your answer goes to {var:1} and cannot be changed afterwards. This link works until {var:2}.', 'vars' => array($view['asker'], $view['expires'])))) . '</p>'
        . '</form>';
} elseif ($view['state'] === 'answered') {
    $approved = ($view['decision'] === 'approved');

    $card .= '<div class="pg-approval-result pg-approval-' . ($approved ? 'approved' : 'rejected') . '">'
        . '<i class="bi ' . ($approved ? 'bi-patch-check-fill' : 'bi-patch-exclamation-fill') . '" aria-hidden="true"></i>'
        . '<div>'
        . '<h1 class="pg-approval-title">' . h($view['title']) . '</h1>'
        . '<p class="mb-0">' . h($approved
            ? lang(array('string' => 'You approved this on {var:1}.', 'vars' => $view['decided']))
            : lang(array('string' => 'You rejected this on {var:1}.', 'vars' => $view['decided']))) . '</p>'
        . '</div></div>';

    if ($view['note'] !== '') {
        $card .= '<blockquote class="pg-approval-quote">' . h($view['note']) . '</blockquote>';
    }

    $card .= '<p class="pg-approval-meta">' . h(lang(array('string' => 'Thank you. {var:1} has been told.', 'vars' => $view['asker']))) . '</p>';
} elseif ($view['state'] === 'closed') {
    $card .= '<h1 class="pg-approval-title">' . h($view['title']) . '</h1>'
        . '<p class="pg-approval-meta">' . h(($view['outcome'] !== '')
            ? lang(array('string' => 'This approval request is closed: {var:1}.', 'vars' => $view['outcome']))
            : lang('The approval request is closed.')) . '</p>';
} elseif ($view['state'] === 'expired') {
    $card .= '<h1 class="pg-approval-title">' . h($view['title']) . '</h1>'
        . '<p class="pg-approval-meta">' . h(lang(array('string' => 'This link is no longer valid. {var:1} can send you a new one.', 'vars' => $view['asker']))) . '</p>';
} else {
    $card .= '<h1 class="pg-approval-title">' . h(lang('This link is not valid.')) . '</h1>'
        . '<p class="pg-approval-meta">' . h(lang('Check that the whole address from the e-mail was opened, or ask for a new link.')) . '</p>';
}

$title = h(lang('Approval request') . (($site !== '') ? ' · ' . $site : ''));

echo '<!DOCTYPE html>
<html lang="' . h(lang(array('info' => ''))) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>' . $title . '</title>
<link rel="stylesheet" href="' . $software . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css?v=' . $version('/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css') . '">
<link rel="stylesheet" href="' . $software . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css?v=' . $version('/assets/fonts/bootstrap-icons/bootstrap-icons.min.css') . '">
<link rel="stylesheet" href="' . $software . '/assets/css/workspace_approval.css?v=' . $version('/assets/css/workspace_approval.css') . '">
</head>
<body class="pg-approval">
<main class="pg-approval-card">
    <div class="pg-approval-site"><i class="bi bi-patch-question me-1" aria-hidden="true"></i>' . h(lang('Approval request')) . (($site !== '') ? ' · ' . h($site) : '') . '</div>
    ' . $card . '
</main>
</body>
</html>';
