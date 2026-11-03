<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Whether this browser's session is still signed in, as {"signed_in": bool}.
 * A panel screen restored from the browser's back/forward cache asks it on
 * pageshow (pg_preloader_markup() in includes/fn/output.php) and loads itself
 * again when the answer is false, so the server sends it to sign-in. It
 * reads the session only and names nobody.
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

echo json_encode(array('signed_in' => (bool) pg_session_signed_in()));
