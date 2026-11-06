<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Music Artist", its public pages: home, about the band,
 * videos, the questions page and the mailing list. Included after parts.php
 * in the same scope (its builders and the artist's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($artist, $members, $releases,
 * $shows, $press_quotes, $videos, $listen_on, $figures), so a change made
 * there reaches every page here. No page here links a shop page: the
 * template stands on a site without the shop.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$public_pages = array();
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
