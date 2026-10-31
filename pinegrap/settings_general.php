<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the organization, the server and domain, the software itself, date and time.
 *
 * One of the nine category screens the settings were split into on
 * 2026-09-12. Everything the screen does lives in the shared flow and in the
 * two modules named after this key; a wrapper exists because the root of the
 * software holds the files a URL asks for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// The Backups card moved to Updates and maintenance. A cloud account
// connection started from this screen before the move still returns here
// with ?backup_connect=; it is sent on to the card, which reads it.
if (isset($_GET['backup_connect'])) {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/'
        . 'settings_maintenance.php?' . http_build_query(array('backup_connect' => (is_string($_GET['backup_connect']) ? preg_replace('/[^a-z]/', '', $_GET['backup_connect']) : ''))) . '#pgset-backup');
    exit;
}

define('PG_SETTINGS_KEY', 'general');

include('includes/settings/screen.php');
