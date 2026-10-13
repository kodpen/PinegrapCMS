<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the address a person's calendar subscribes to:
 *
 *   GET workspace_ical.php?token=...   text/calendar
 *
 * Read by a calendar service (Google, Outlook, Apple's Calendar) from its own
 * servers, every few hours, with the token as its only credential. What it
 * carries and why is in includes/workspace/calendar_feed.php; the person
 * makes, renews and ends the address on workspace_calendar_feed.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// No session: the token is the credential, and a panel cookie in the same
// browser must never decide what the address shows. $_SESSION stays an array
// so the shared code that looks into it finds nothing rather than warning.
define('PG_NO_SESSION', true);

$_SESSION = array();

// Whatever the shared code prints on the way through would sit in front of
// the calendar; it is dropped before the answer is sent.
ob_start();

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

ws_calendar_feed_serve((isset($_GET['token']) && is_string($_GET['token'])) ? $_GET['token'] : '');
