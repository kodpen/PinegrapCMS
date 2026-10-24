<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitor beacon of the page cache: a page the router answered from a
 * stored copy posts here once it is shown, so the view is counted as if the
 * page had been drawn.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The stored page carries a payload signed with the site's key: the page, the
// address the visitor used, the catalogue item and the record a record page
// showed (pg_page_cache_beacon_payload()). Only a payload this site signed is
// counted, so nobody can count views of pages of their choosing; a replayed
// one counts what a reload would. The answer is always an empty 204, whether
// or not anything was counted.
//
// A session, on purpose: the visitor's row in visitors is found again through
// it on the next page, as when pages are drawn.

include('init.php');

header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit();
}

http_response_code(204);

if (pg_post_body_discarded()) {
    exit();
}

$page_view_payload = (isset($_POST['p']) && is_string($_POST['p'])) ? $_POST['p'] : '';
$page_view_signature = (isset($_POST['s']) && is_string($_POST['s'])) ? $_POST['s'] : '';

$page_view = pg_page_cache_beacon_verify($page_view_payload, $page_view_signature, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '')
    ? pg_page_cache_beacon_read($page_view_payload)
    : null;

// Crawlers, scripts and other Pinegrap installations are not counted, as on
// a drawn page.
if (($page_view === null) || pg_request_is_automated()) {
    exit();
}

if (defined('VISITOR_TRACKING') && VISITOR_TRACKING) {

    // init_tracking() reads the referring site from the request; here that
    // is the beacon's own page, so the page's referrer is put in its place.
    $page_view_referer = pg_page_cache_referer($_POST['r'] ?? '');

    if ($page_view_referer !== '') {
        $_SERVER['HTTP_REFERER'] = $page_view_referer;
    } else {
        unset($_SERVER['HTTP_REFERER']);
    }

    if ($page_view['pretty'] !== '') {
        define('PRETTY_URL_PATH', $page_view['pretty']);
    }

    if (($page_view['item_type'] !== '') && ($page_view['item_id'] > 0)) {
        pg_track_content($page_view['item_type'], $page_view['item_id']);
    }

    init_tracking();

    update_visitor_page_data($page_view['page_name'], $page_view['page_id']);
}

// The record a record page showed counts one view, outside the visitor
// tracking switch, as get_page.php counts it.
$page_view_record = array('form_id' => $page_view['form_id'], 'page_id' => $page_view['record_page_id']);

if (pg_sw_should_count_record_view($page_view_record, 'GET', false)) {
    pg_sfv_count_view($page_view_record['form_id'], $page_view_record['page_id']);
}
