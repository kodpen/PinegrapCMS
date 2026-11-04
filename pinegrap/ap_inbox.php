<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The ActivityPub inbox and shared inbox of the site's actor: other
 * Fediverse servers POST signed activities here (Follow, Undo, Like,
 * Announce, Delete, replies). The work is in
 * includes/fn/activitypub_inbox.php; while the feature is off it answers 404.
 * Called by other servers, it runs with no session.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_NO_SESSION')) {
    define('PG_NO_SESSION', true);
}

$_SESSION = array();

// Whatever the shared code prints on the way through would sit in front of
// the answer; pg_ap_inbox_answer() drops it.
ob_start();

include(dirname(__FILE__) . '/init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_comments.php');

pg_ap_inbox_receive();
