<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Content-Security-Policy report endpoint
 *
 * Browsers POST a small JSON document here for every policy violation they
 * see on a page that carried a Content-Security-Policy or
 * Content-Security-Policy-Report-Only header. The reports are folded into
 * data/temp/csp_reports.json by waf_csp_report_store() and shown on the
 * firewall log screen, where an operator reads which third-party sources a
 * policy would refuse before deciding to enforce one.
 *
 * Deliberately standalone: no init.php, no functions.php, no database and no
 * session. A busy site's visitors post here continuously and the endpoint
 * has to cost about what a static file costs. waf.php is loaded for the
 * store function only; it has no side effects at include time.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

// Sixteen kilobytes is several times a real report. Anything larger is not
// one, and is not read past this point.
$raw = @file_get_contents('php://input', false, null, 0, 16384);

require_once(dirname(__FILE__) . '/waf.php');

waf_csp_report_store($raw);

http_response_code(204);
header('Cache-Control: no-store');
exit;
