<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Whether this browser's session is still signed in, and its view key, as
 * {"signed_in": bool, "view": string}. A panel screen shown by Back or
 * Forward asks it on pageshow (pg_preloader_markup() in
 * includes/fn/output.php) and loads itself again unless the session is
 * signed in with the view key the page carries (pg_session_view_key()), so
 * a page of a signed-out or different user is never shown. It names nobody.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-cache');
header_remove('Pragma');
header_remove('Expires');
header('X-Robots-Tag: noindex');

$signed_in = (bool) pg_session_signed_in();

echo json_encode(array('signed_in' => $signed_in, 'view' => $signed_in ? pg_session_view_key() : ''));
