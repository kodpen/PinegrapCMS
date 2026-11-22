<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One connected Google or Microsoft account for sending the site's e-mail
 * and for reading Workspace mailboxes. An account connected for mailboxes
 * (purpose mail_read) holds the provider's send permission too, so How
 * e-mail leaves offers it as it is; an account connected for sending
 * (purpose mail) chosen for a mailbox asks the provider for reading on the
 * same account, whose row keeps the purposes it had. Disconnect and Remove
 * mailbox say what the account still serves. The Connect buttons of both
 * tabs of Site Settings › E-mail are drawn here.
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
 * Whether an account with these purposes may send the site's e-mail:
 * sending (mail) asked for it, reading mailboxes (mail_read) carries it
 * (Gmail's full mail scope, Microsoft Graph's Mail.Send). Pure.
 *
 * @param string[] $purposes pg_connect_account()['purposes']
 * @return bool
 */
function pg_mail_account_sends($purposes)
{
    return (bool) array_intersect(array('mail', 'mail_read'), (array) $purposes);
}

/**
 * The site's accounts that may send its e-mail, newest first, each once.
 *
 * @return array[] pg_connect_account() rows
 */
function pg_mail_sending_accounts()
{
    $accounts = array();

    foreach (array('mail', 'mail_read') as $purpose) {
        foreach (pg_connect_accounts(array('user_id' => 0, 'purpose' => $purpose)) as $account) {
            $accounts[(int) $account['id']] = $account;
        }
    }

    krsort($accounts);

    return array_values($accounts);
}

/**
 * The words beside an account offered for sending that was connected for a
 * mailbox only; '' for one connected for sending. Pure.
 *
 * @param array $account pg_connect_account()
 * @return string
 */
function pg_mail_account_mark($account)
{
    $purposes = (array) ($account['purposes'] ?? array());

    return (in_array('mail_read', $purposes, true) && !in_array('mail', $purposes, true)) ? lang('Connected for a mailbox') : '';
}

/**
 * Whether a site account chosen for a mailbox must first ask its provider
 * for reading: it sends the site's e-mail and does not read yet. Pure.
 *
 * @param array $account pg_connect_account()
 * @return bool
 */
function pg_mail_box_account_asks($account)
{
    $purposes = (array) ($account['purposes'] ?? array());

    return ((int) ($account['user_id'] ?? -1) === 0)
        && in_array((string) ($account['provider'] ?? ''), array('google', 'microsoft'), true)
        && in_array('mail', $purposes, true)
        && !in_array('mail_read', $purposes, true);
}

/**
 * The form a Connect button of Site Settings › E-mail submits, printed
 * outside the settings form (forms cannot be nested). With an account's
 * number it asks the provider again for that account: its address as the
 * login hint, the purposes it holds kept, its row written (connect.php,
 * pg_connect_start()).
 *
 * @param string $id            the form's id, the button's form attribute
 * @param string $provider      google or microsoft
 * @param string $purpose       mail or mail_read
 * @param string $return        the screen connect.php returns to
 * @param int    $connection_id the account to ask again for, 0 for a new one
 * @return string HTML
 */
function pg_mail_connect_form($id, $provider, $purpose, $return, $connection_id = 0)
{
    return '
            <form method="post" action="connect.php" id="' . h($id) . '" class="disable_shortcut d-none">
                ' . pg_connect_start_fields($provider, $purpose, $return, 'site', (int) $connection_id) . '
            </form>';
}

/**
 * A Connect button of Site Settings › E-mail: it sits in the settings form
 * and submits its own form (pg_mail_connect_form()) by the form attribute.
 *
 * @param string $form  the form's id
 * @param string $label
 * @param string $icon  a Bootstrap icon
 * @return string HTML
 */
function pg_mail_connect_button($form, $label, $icon = 'bi-plus-lg')
{
    return '<button type="submit" form="' . h($form) . '" class="pg-button pg-button-quiet pg-button-sm">'
        . '<i class="bi ' . h($icon) . '" aria-hidden="true"></i><span class="pg-btn-label">' . h($label) . '</span></button>';
}

/**
 * The kinds of mail E-mail sending sends through an account.
 *
 * @param int $account_id
 * @return string[] system, campaign
 */
function pg_mail_account_sending_kinds($account_id)
{
    $kinds = array();

    foreach (array('system' => 'SYSTEM_MAIL_CONNECTION', 'campaign' => 'CAMPAIGN_MAIL_CONNECTION') as $kind => $constant) {
        if (((int) $account_id > 0) && defined($constant) && ((int) constant($constant) === (int) $account_id)) {
            $kinds[] = $kind;
        }
    }

    return $kinds;
}

/**
 * What an account serves the site's mail with: the kinds of mail sent
 * through it (E-mail sending's choices) and the mailboxes read with it.
 *
 * @param int $account_id
 * @return array sending (system, campaign), boxes (names)
 */
function pg_mail_account_uses($account_id)
{
    $account_id = (int) $account_id;
    $uses = array('sending' => pg_mail_account_sending_kinds($account_id), 'boxes' => array());

    // The boxes with an account, read once a request: Connections asks for
    // every account it lists.
    static $boxes = null;

    if ($boxes === null) {
        $boxes = array();

        if (function_exists('waf_table_has_column') && waf_table_has_column('ws_mail_boxes', 'settings')) {
            foreach (db_items("SELECT name, settings FROM ws_mail_boxes WHERE settings LIKE '%connection_id%' ORDER BY name") as $box) {
                $settings = json_decode((string) $box['settings'], true);

                if (is_array($settings) && ((int) ($settings['connection_id'] ?? 0) > 0)) {
                    $boxes[] = array((int) $settings['connection_id'], (string) $box['name']);
                }
            }
        }
    }

    foreach ($boxes as $box) {
        if ($box[0] === $account_id) {
            $uses['boxes'][] = $box[1];
        }
    }

    return $uses;
}

/**
 * Disconnect's question on Connections: what stops with the account, both
 * its sending and its mailboxes. Pure.
 *
 * @param string $label the account's address
 * @param array  $uses  pg_mail_account_uses()
 * @return string
 */
function pg_mail_account_disconnect_confirm($label, $uses)
{
    $sending = (array) ($uses['sending'] ?? array());
    $boxes = (array) ($uses['boxes'] ?? array());
    $stops = array();

    if ($sending) {
        $kinds = array();

        foreach (array('system' => lang('system e-mail'), 'campaign' => lang('campaigns')) as $kind => $words) {
            if (in_array($kind, $sending, true)) {
                $kinds[] = $words;
            }
        }

        $stops[] = (count($kinds) > 1)
            ? lang(array('string' => '{var:1} and {var:2} stop leaving through it', 'vars' => $kinds))
            : lang(array('string' => '{var:1} stops leaving through it', 'vars' => $kinds));
    }

    if ($boxes) {
        $stops[] = (count($boxes) > 1)
            ? lang(array('string' => 'the mailboxes {var:1} stop being read', 'vars' => implode(', ', $boxes)))
            : lang(array('string' => 'the mailbox {var:1} stops being read', 'vars' => $boxes[0]));
    }

    if (!$stops) {
        return lang(array('string' => 'The site will no longer use {var:1}, and every feature that does stops until an account is connected again.', 'vars' => $label));
    }

    return lang(array('string' => 'The site will no longer use {var:1}: {var:2} until an account is connected again.', 'vars' => array($label, implode('; ', $stops))));
}

/**
 * What Remove mailbox's question adds when the box's account sends the
 * site's e-mail: removing a box never disconnects an account. Pure.
 *
 * @param string $label the account's address
 * @param array  $uses  pg_mail_account_uses()
 * @return string '' when sending does not use it
 */
function pg_mail_box_remove_kept($label, $uses)
{
    if (empty($uses['sending'])) {
        return '';
    }

    return lang(array('string' => '{var:1} stays connected: the site\'s e-mail is still sent through it.', 'vars' => $label));
}
