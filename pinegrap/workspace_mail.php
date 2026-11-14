<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Workspace's E-mails screen, now a door: the e-mail conversations are
 * read in Communication › Incoming e-mail (messages_mail.php). The address
 * and its words (a conversation, a view, the filters) are carried across
 * for bookmarks, inbox lines and mails that still name it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');

// The new screen's gate answers, so this door asks no right of its own.
validate_user();
go(PATH . SOFTWARE_DIRECTORY . '/' . pg_messages_mail_door($_GET));
