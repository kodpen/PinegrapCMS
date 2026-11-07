<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum", its learning pages: the sessions for schools
 * by age, the school visit request followed from the teacher's account and
 * the timed entry booking page. Included after parts.php, mail.php and
 * events.php in the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$learning_pages = array();
$learning_widgets = array();

return array('pages' => $learning_pages, 'widgets' => $learning_widgets);
