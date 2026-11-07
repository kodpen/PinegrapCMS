<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum", the account and staff part: signing in, up
 * and out, My Account and its pages, the staff directory, the staff area,
 * the account e-mails, and the widgets that draw them (the account bar's
 * login region and My Account included). Included after the other page
 * files in the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$account_pages = array();
$account_widgets = array();

return array('pages' => $account_pages, 'widgets' => $account_widgets);
