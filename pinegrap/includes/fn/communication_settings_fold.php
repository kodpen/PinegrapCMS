<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Communication as folded rows (includes/settings/fold.php):
 * the rows' order, names and icons, and each part's one line of state with
 * its pill. The pg_contact_fold_<part>() lines are pure but for lang();
 * includes/settings/contact.php reads the facts they take.
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
 * The rows' keys in reading order. A key is its card's id without pgset-.
 *
 * @return string[]
 */
function pg_contact_fold_order()
{
    return array('chat', 'chat-site', 'sms', 'sms-messages', 'whatsapp', 'whatsapp-messages', 'iys');
}

/**
 * A row's name, icon and tone.
 *
 * @param string $key one of pg_contact_fold_order()
 * @return array name, icon, tone
 */
function pg_contact_fold_face($key)
{
    switch ($key) {
        case 'chat':
            return array('name' => lang('Chat'), 'icon' => 'bi-chat-dots', 'tone' => 'pg-tone-blue');
        case 'chat-site':
            return array('name' => lang('Chat on the site'), 'icon' => 'bi-chat-square-heart', 'tone' => 'pg-tone-teal');
        case 'sms':
            return array('name' => lang('SMS'), 'icon' => 'bi-phone', 'tone' => 'pg-tone-violet');
        case 'sms-messages':
            return array('name' => lang('SMS messages'), 'icon' => 'bi-chat-left-text', 'tone' => 'pg-tone-violet');
        case 'whatsapp':
            return array('name' => lang('WhatsApp'), 'icon' => 'bi-whatsapp', 'tone' => 'pg-tone-shop');
        case 'whatsapp-messages':
            return array('name' => lang('WhatsApp messages'), 'icon' => 'bi-chat-right-text', 'tone' => 'pg-tone-shop');
        default:
            return array('name' => lang('İYS'), 'icon' => 'bi-shield-check', 'tone' => 'pg-tone-amber');
    }
}

/**
 * The On or Off pill of a part one switch turns on.
 *
 * @param bool $on
 * @return array words, tone class
 */
function pg_contact_fold_switch_pill($on)
{
    return $on ? array(lang('On'), 'pg-tone-teal') : array(lang('Off'), 'pg-tone-gray');
}

/**
 * Chat's line: the switch serves the panel's team chat and the site's.
 *
 * @param bool $on the Chat switch (chat_enabled)
 * @return array line, pill, attention
 */
function pg_contact_fold_chat($on)
{
    return array(
        'line'      => $on ? lang('Team members chat in the panel') : lang('No chat shows anywhere'),
        'pill'      => pg_contact_fold_switch_pill($on),
        'attention' => false,
    );
}

/**
 * Chat on the site's line: whether the bubble shows and who answers
 * visitors. Visitors who are not signed in see the bubble only while an
 * operator is chosen (pg_chat_site_enabled()).
 *
 * @param bool   $chat_on   the Chat switch
 * @param bool   $bubble_on the bubble switch (chat_site_enabled)
 * @param string $operator  the chosen operator's name, '' for none
 * @return array line, pill, attention
 */
function pg_contact_fold_chat_site($chat_on, $bubble_on, $operator)
{
    $operator = trim((string) $operator);

    if (!$chat_on) {
        return array('line' => lang('Chat is off: nothing shows on the site'), 'pill' => pg_contact_fold_switch_pill(false), 'attention' => false);
    }

    if (!$bubble_on) {
        return array('line' => lang('The bubble is off'), 'pill' => pg_contact_fold_switch_pill(false), 'attention' => false);
    }

    if ($operator === '') {
        return array('line' => lang('Only signed-in members see the bubble: no one answers visitors'), 'pill' => array(lang('No one answers'), 'pg-tone-amber'), 'attention' => true);
    }

    return array(
        'line'      => lang(array('string' => 'On, answered by {var:1}', 'vars' => $operator)),
        'pill'      => pg_contact_fold_switch_pill(true),
        'attention' => false,
    );
}

/**
 * SMS's line: the provider it sends through, or what is missing.
 *
 * @param bool   $on        the Send SMS switch
 * @param bool   $connected the chosen provider's account is complete
 * @param string $provider  the provider's name
 * @return array line, pill, attention
 */
function pg_contact_fold_sms($on, $connected, $provider)
{
    if ($on && !$connected) {
        return array('line' => lang('Switched on, but the provider is not connected'), 'pill' => array(lang('Not connected'), 'pg-tone-amber'), 'attention' => true);
    }

    if ($on) {
        return array('line' => lang(array('string' => 'On, sent through {var:1}', 'vars' => (string) $provider)), 'pill' => pg_contact_fold_switch_pill(true), 'attention' => false);
    }

    return array(
        'line'      => $connected ? lang(array('string' => '{var:1} is connected; sending is off', 'vars' => (string) $provider)) : lang('Not set up'),
        'pill'      => pg_contact_fold_switch_pill(false),
        'attention' => false,
    );
}

/**
 * The line of SMS messages or WhatsApp messages: how many of the offered
 * messages are switched on, or that the channel is not set up.
 *
 * @param bool $ready   the channel sends (pg_sms_ready(), pg_whatsapp_ready())
 * @param int  $on      the messages switched on
 * @param int  $offered the messages the card offers
 * @return array line, pill, attention
 */
function pg_contact_fold_messages($ready, $on, $offered)
{
    $on = (int) $on;
    $offered = (int) $offered;

    if (!$ready) {
        return array('line' => lang('Not set up: no message is sent'), 'pill' => null, 'attention' => false);
    }

    return array(
        'line'      => ($on > 0)
            ? lang(array('string' => '{var:1} of {var:2} messages on', 'vars' => array($on, $offered)))
            : lang('No message is switched on'),
        'pill'      => pg_contact_fold_switch_pill($on > 0),
        'attention' => false,
    );
}

/**
 * The line of a part whose account is kept on Connections (WhatsApp, İYS).
 *
 * @param bool $connected
 * @return array line, pill, attention
 */
function pg_contact_fold_connection($connected)
{
    return array(
        'line'      => $connected ? lang('Connected') : lang('Not set up'),
        'pill'      => $connected ? array(lang('Connected'), 'pg-tone-teal') : array(lang('Not connected'), 'pg-tone-gray'),
        'attention' => false,
    );
}
