<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sending the site's e-mail from a connected Google or Microsoft account
 * instead of an SMTP server (SMTP Settings; includes/fn/mail.php). Microsoft
 * ends password sign-in for SMTP, and Google allows it only with app
 * passwords; a connected account needs neither.
 *
 * Besides the usual purpose fields (includes/connect/purposes/account.php)
 * this one says, per provider, how a finished message is handed over:
 *
 *   url           the address the message is sent to
 *   content_type  the type the message is sent as
 *   encoding      'raw' (the MIME text as it is) or 'base64'
 *   max_bytes     the largest request the provider takes, after encoding
 *   scopes        what the access token for the call must carry
 *
 * The message is built by PHPMailer as for SMTP (attachments, DKIM, headers),
 * so every provider receives the same text.
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
    'label'       => lang('Sending e-mail'),
    'description' => lang('Sends the site\'s e-mail from this account instead of an SMTP server.'),
    'owner'       => 'site',
    'scopes'      => array(
        'google'    => array('https://www.googleapis.com/auth/gmail.send'),
        'microsoft' => array('https://graph.microsoft.com/Mail.Send'),
    ),
    'send'        => array(
        // Gmail: the media upload takes the message itself, up to 35 MB.
        'google'    => array(
            'url'          => 'https://gmail.googleapis.com/upload/gmail/v1/users/me/messages/send?uploadType=media',
            'content_type' => 'message/rfc822',
            'encoding'     => 'raw',
            'max_bytes'    => 36700160,
            'scopes'       => array(),
        ),
        // Microsoft Graph: the message in base64 as text/plain, the whole
        // request under 4 MB.
        'microsoft' => array(
            'url'          => 'https://graph.microsoft.com/v1.0/me/sendMail',
            'content_type' => 'text/plain',
            'encoding'     => 'base64',
            'max_bytes'    => 4194304,
            'scopes'       => array('https://graph.microsoft.com/Mail.Send'),
        ),
    ),
);
