<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum", its events and talks: the Events collection,
 * the list by kind, the event's own page, the home page's coming events
 * and the place requests followed from the visitor's account. Included
 * after parts.php, mail.php, exhibitions.php and objects.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$event_pages = array();
$event_widgets = array();

return array('pages' => $event_pages, 'widgets' => $event_widgets);
