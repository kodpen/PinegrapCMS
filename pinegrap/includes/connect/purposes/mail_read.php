<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Reading a Workspace mailbox and answering from it through a connected
 * Microsoft 365, Outlook.com, Gmail or Google Workspace account
 * (includes/workspace/mail_settings.php): the box signs in to
 * outlook.office365.com or imap.gmail.com with the account's token
 * (XOAUTH2), and its answers leave through Microsoft Graph or the Gmail API
 * as the site's own mail does (includes/fn/mail.php, purpose mail's send
 * entry). Gmail's IMAP takes only https://mail.google.com/, which also
 * covers sending through the Gmail API.
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
    'label'       => lang('Reading mailboxes'),
    'description' => lang('Reads a Workspace mailbox of this account and sends its answers from it.'),
    'owner'       => 'site',
    'scopes'      => array(
        'microsoft' => array('https://outlook.office.com/IMAP.AccessAsUser.All', 'https://graph.microsoft.com/Mail.Send'),
        'google'    => array('https://mail.google.com/'),
    ),
    // What the IMAP sign-in's token must carry: Microsoft gives one token
    // per resource; Google one token for every scope granted.
    'imap_scopes' => array(
        'microsoft' => array('https://outlook.office.com/IMAP.AccessAsUser.All'),
        'google'    => array('https://mail.google.com/'),
    ),
);
