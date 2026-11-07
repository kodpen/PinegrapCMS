<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum", the pages every template carries: contact and
 * the inbox (a message becomes a conversation), the newsletter, the blog
 * as "News" and its post page, the help center that searches the whole
 * site, the error page, the message and newsletter e-mails, the home
 * page's news, and the widgets that draw them. The privacy page and the
 * reply e-mail of the threads (email_reply, its widget email_comment) are
 * the events part's. Included after parts.php and mail.php in the same
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

$standard_pages = array();
$standard_widgets = array();

return array('pages' => $standard_pages, 'widgets' => $standard_widgets);
