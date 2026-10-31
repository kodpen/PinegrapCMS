<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Picking a file from the person's own cloud storage on the Upload File
 * screen (add_file.php; includes/fn/cloud_file_picker.php). The browser only
 * hands back the chosen file's id; the server downloads it with this
 * connection's token and stores it through the same rules as an upload.
 *
 * Each person connects their own account, so nobody browses the cloud
 * storage of the site or of a colleague. Google's drive.file reaches only
 * the files the person picks in the Google Picker (and those the
 * application created), and it is not a sensitive scope, so Google asks for
 * no verification. Microsoft's Files.Read is read-only; the OneDrive picker
 * itself needs a token for the OneDrive or SharePoint resource, asked for
 * with the same consent (includes/fn/cloud_file_picker.php). Dropbox's Chooser needs no connected account, only the
 * application's app key, and is offered when that application is set up.
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
    'label'       => lang('Picking files'),
    'description' => lang('Lets this person pick a file from their own cloud storage when uploading.'),
    'owner'       => 'person',
    'scopes'      => array(
        'google'    => array('https://www.googleapis.com/auth/drive.file'),
        'microsoft' => array('https://graph.microsoft.com/Files.Read'),
    ),
);
