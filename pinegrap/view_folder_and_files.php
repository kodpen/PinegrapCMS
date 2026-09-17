<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The file manager was born at this address and then took over the classic
// folders screen; it lives at view_folders.php now. This stub keeps every
// old link, bookmark and send_to working, query string and all.

include('init.php');

validate_user();

$query_string = (isset($_SERVER['QUERY_STRING']) && ($_SERVER['QUERY_STRING'] != '')) ? '?' . $_SERVER['QUERY_STRING'] : '';

header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_folders.php' . $query_string);
exit();
