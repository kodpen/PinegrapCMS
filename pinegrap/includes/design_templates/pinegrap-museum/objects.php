<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum", its collection highlights: the staff page that
 * adds a highlight (the Collection Highlights form, a collection, with the
 * sample highlights written in when it is made), the collection page with
 * its department filter and the object's own page. Included after parts.php
 * and exhibitions.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$object_pages = array();
$object_widgets = array();

return array('pages' => $object_pages, 'widgets' => $object_widgets);
