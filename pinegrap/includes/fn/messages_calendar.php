<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Sends, Calendar view: what is waiting to be sent, drawn by
 * day and hour. Scheduled e-mail and SMS campaigns, the next runs of the
 * automation flows that start at a time and send a message, and the e-mails
 * waiting under Send later. It only reads: nothing is sent or moved from it.
 *
 * Loaded where it is needed (require_once), not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}
