<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The download link of an information request's answer
 * (includes/fn/privacy_request_link.php).
 *
 *   GET privacy_download.php?r=<request>&e=<end>&n=<file>&s=<signature>
 *
 * Sends the person's exported data as a file when the signature is the
 * site's, the 7 days have not passed and the file is still kept. A wrong,
 * ended or withdrawn link reads one sentence with status 410, the same
 * whether a request exists. Each download is an audit line of the request.
 * Counted on the firewall's sensitive allowance (waf_sensitive_scripts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_requests.php');

// Personal data: never kept by a cache, never indexed, and the signed
// address is not handed to another site as a referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$pd_link = pg_privacy_link_parse($_GET);
$pd_request = null;
$pd_path = '';

if (($pd_link !== null) && pg_privacy_requests_ready()) {
    $pd_request = db_item("SELECT id, kind, status FROM privacy_requests WHERE id = '" . (int) $pd_link['r'] . "'");
    $pd_path = pg_privacy_link_dir() . '/' . pg_privacy_link_file_name($pd_link['r'], $pd_link['e'], $pd_link['n']);
}

if (!pg_privacy_link_valid($pd_link, is_array($pd_request) ? $pd_request : null, pg_fs_key(), time(), ($pd_path !== '') && is_file($pd_path))) {
    $pd_title = lang('Your personal data');

    http_response_code(410);
    print output_header_secure(array('title' => $pd_title))
        . pg_secure_card('bi-shield-check', $pd_title, '<p>' . h(lang('This link has ended. Ask the site for a new one.')) . '</p>')
        . output_footer_secure();
    exit();
}

pg_audit_as_site(true);
pg_audit('download', 'privacy_request', (int) $pd_request['id'], array(), array(), '');
pg_audit_as_site(false);

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . pg_privacy_export_filename(array(), 'json', $pd_link['e'] - pg_privacy_link_ttl()) . '"');
header('Content-Length: ' . (int) filesize($pd_path));
header('X-Content-Type-Options: nosniff');

readfile($pd_path);
exit();
