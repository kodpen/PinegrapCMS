<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the accountant's link.
 *
 * Opens the pack a link was sent for, without a login: the accountant has no
 * account here. The link carries a random token; only its hash is kept, it
 * stops working after a few days, and sending the pack again replaces it
 * (includes/erp/accountant.php). Anything else - a wrong, spent or guessed
 * token - gets the same plain page, so the page says nothing about which
 * packs exist.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$package = null;

if (defined('ERP_ENABLED') && ERP_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
    $package = erp_accountant_package_by_token((string) ($_GET['t'] ?? ''));
}

if (is_array($package) && erp_accountant_stream($package)) {
    erp_accountant_count_download((int) $package['id']);

    if (function_exists('log_activity')) {
        log_activity(lang(array('string' => 'Accountant pack for {var:1} downloaded by link.', 'vars' => erp_accountant_period_label((string) $package['period_from'], (string) $package['period_to']))), 'ERP');
    }

    exit();
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

echo '<!DOCTYPE html>
<html lang="' . h(defined('SOFTWARE_LANGUAGE') ? substr((string) SOFTWARE_LANGUAGE, 0, 2) : 'en') . '">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<title>' . h(lang('This link no longer works')) . '</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #222222; margin: 0; padding: 40px 20px;">
<div style="max-width: 520px; margin: 0 auto;">
<h1 style="font-size: 20px;">' . h(lang('This link no longer works')) . '</h1>
<p style="font-size: 15px; line-height: 1.5;">' . h(lang('A link to an accounting pack works for a few days, and a new one replaces it. Ask the business that sent it for a new link.')) . '</p>
</div>
</body>
</html>';
