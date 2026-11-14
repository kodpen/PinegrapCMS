<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Incoming e-mail outside the Workspace switch.
 *
 * Mailboxes, their conversations and the mail queue are kept by the
 * Workspace module's files (includes/workspace/mail*.php), but mail is read
 * and answered in Communication › Incoming e-mail whether or not the
 * Workspace is switched on: it has no switch of its own, and a site that
 * wants no mail closes its mailbox. This is the gate the places outside the
 * module ask before they load it (the general job, the menu, the bell, the
 * external API), and the one the module's mail entries ask instead of
 * ws_enabled().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Is incoming e-mail on at this site? Yes once the mailbox tables are there
 * (the 2026.11.0 upgrade), never by WORKSPACE_ENABLED. Read from the schema
 * cache, so asking costs nothing on a page that shows no mail.
 *
 * @return bool
 */
function ws_mail_on()
{
    return function_exists('pg_schema_has')
        && pg_schema_has('ws_mail_boxes')
        && pg_schema_has('ws_mail_conversations')
        && pg_schema_has('ws_mail_items');
}

/**
 * Does a site whose Workspace is off have a mailbox switched on, so the
 * general job and the menu load the Workspace's files for mail? One small
 * read, only once the gate holds.
 *
 * @return bool
 */
function ws_mail_in_use()
{
    if (!ws_mail_on()) {
        return false;
    }

    return (bool) db_value("SELECT id FROM ws_mail_boxes WHERE enabled = 1 LIMIT 1");
}
