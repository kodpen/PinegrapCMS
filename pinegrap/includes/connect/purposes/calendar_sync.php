<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Writing the person's Workspace tasks to their own calendar
 * (includes/workspace/calendar_sync.php).
 *
 * Each person connects their own account: the calendar is theirs, and nobody
 * writes into a colleague's. Google's calendar.app.created lets the site make
 * a calendar of its own and manage the events in it; it cannot see or change
 * any other calendar of the person.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

return array(
    'label'       => lang('Calendar sync'),
    'description' => lang('Writes this person\'s Workspace tasks to a calendar of their own in their account.'),
    'owner'       => 'person',
    'scopes'      => array(
        'google' => array('https://www.googleapis.com/auth/calendar.app.created'),
    ),
);
