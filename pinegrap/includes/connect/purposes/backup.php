<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sending the automatic backup to the site's own cloud storage: Google Drive,
 * OneDrive or Dropbox (Site Settings › General › Backups;
 * includes/fn/backup_cloud.php).
 *
 * Each scope reaches only what the site itself writes there: Drive's
 * drive.file the files this application created, OneDrive's and Dropbox's
 * app folder the folder under Apps that bears the application's name.
 * drive.file is not a sensitive scope, so Google asks for no verification.
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
    'label'       => lang('Backups'),
    'description' => lang('Sends the automatic backup to this account\'s cloud storage, into a folder only the site writes to.'),
    'owner'       => 'site',
    'scopes'      => array(
        'google'    => array('https://www.googleapis.com/auth/drive.file'),
        'microsoft' => array('https://graph.microsoft.com/Files.ReadWrite.AppFolder'),
        'dropbox'   => array('files.metadata.read', 'files.content.write'),
    ),
);
