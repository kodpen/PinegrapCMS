<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Templates (messages_templates.php): the texts a send
 * starts from, drawn in look A's card and row recipes. Now the SMS texts
 * the site sends on its own (the SMS Messages card,
 * includes/fn/sms_messages.php): each event with its kind, whether it is on
 * and the text it sends, its own or the built-in one. The texts are still
 * edited on their card; each row leads there. The WhatsApp templates read
 * from Meta (includes/fn/whatsapp.php) with their state and Refresh; e-mail
 * designs are announced in a card of their own.
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

/**
 * The SMS texts as rows: every event the SMS Messages card offers (an ERP
 * event while the module is on, a booking event once bookings are set up),
 * in the card's order. A row's text is its own when one is saved, the
 * built-in one otherwise, marked so. Pure.
 *
 * @param array $definitions pg_sms_message_events()
 * @param array $settings    pg_sms_message_settings()
 * @param bool  $erp_on
 * @param bool  $booking_on
 * @param array $kinds       kind => label (pg_sms_queue_list_kinds())
 * @return array list of event, label, kind, on, own, text
 */
function pg_messages_template_sms_rows(array $definitions, array $settings, $erp_on, $booking_on, array $kinds)
{
    $rows = array();

    foreach ($definitions as $event => $definition) {
        if (!pg_sms_message_event_shown($definition, $erp_on, $booking_on)) {
            continue;
        }

        $stored = isset($settings[$event]) && is_array($settings[$event]) ? $settings[$event] : array();
        $own = isset($stored['text']) && is_string($stored['text']) && (trim($stored['text']) !== '');

        $rows[] = array(
            'event' => (string) $event,
            'label' => (string) $definition['label'],
            'kind' => isset($kinds[$definition['kind']]) ? $kinds[$definition['kind']] : (string) $definition['kind'],
            'on' => !empty($stored['on']),
            'own' => $own,
            'text' => $own ? (string) $stored['text'] : (string) $definition['text'],
        );
    }

    return $rows;
}

/**
 * A card of the tab: its section title, a line under it, an optional
 * button at the right, and its rows.
 *
 * @param string $title
 * @param string $sub    plain text, '' for none
 * @param string $button markup at the title's right, '' for none
 * @param string $rows   markup
 * @return string
 */
function pg_messages_template_card($title, $sub, $button, $rows)
{
    return '<section class="pg-card pg-messages-card">'
        . '<div class="pg-messages-card-head"><div class="pg-messages-card-heading"><h2 class="pg-title-section">' . h($title) . '</h2>'
        . (($sub !== '') ? '<p class="pg-card-sub">' . h($sub) . '</p>' : '')
        . '</div>' . $button . '</div>'
        . '<div class="pg-messages-card-rows">' . $rows . '</div>'
        . '</section>';
}

/**
 * One row of a card: a tile, the title (a link when an address is given)
 * with a meta word at its right, at most two lines of text, a pill.
 *
 * @param array $row icon, tone, title, url, meta, text (markup), pill, pill_tone
 * @return string
 */
function pg_messages_template_row(array $row)
{
    $title = h((string) $row['title']);

    if (!empty($row['url'])) {
        $title = '<a class="pg-messages-row-link" href="' . h((string) $row['url']) . '">' . $title . '</a>';
    }

    return '<div class="pg-list-row">'
        . '<span class="pg-icon-tile pg-tone-' . h((string) $row['tone']) . '" aria-hidden="true"><span class="bi ' . h((string) $row['icon']) . '"></span></span>'
        . '<div class="pg-list-row-main">'
        . '<div class="pg-list-row-head"><span class="pg-list-row-title">' . $title . '</span>'
        . (((string) ($row['meta'] ?? '') !== '') ? '<span class="pg-list-row-time">' . h((string) $row['meta']) . '</span>' : '')
        . '</div>'
        . (((string) ($row['text'] ?? '') !== '') ? '<div class="pg-list-row-text">' . $row['text'] . '</div>' : '')
        . '</div>'
        . '<span class="pg-pill pg-tone-' . h((string) $row['pill_tone']) . ' pg-messages-row-pill">' . h((string) $row['pill']) . '</span>'
        . '</div>';
}

/**
 * The SMS texts card.
 *
 * @param array  $rows pg_messages_template_sms_rows()
 * @param string $url  the SMS Messages card's address
 * @return string
 */
function pg_messages_template_sms_card(array $rows, $url)
{
    $html = '';

    foreach ($rows as $row) {
        $html .= pg_messages_template_row(array(
            'icon' => 'bi-chat-text',
            'tone' => 'amber',
            'title' => $row['label'],
            'url' => $url,
            // The kind leads the line under the title, so a long name keeps
            // its width on a phone.
            'meta' => '',
            'text' => h($row['kind']) . ' · ' . ($row['own'] ? '' : '<b>' . h(lang('Built-in')) . '</b> · ') . h($row['text']),
            'pill' => $row['on'] ? lang('On') : lang('Off'),
            'pill_tone' => $row['on'] ? 'teal' : 'gray',
        ));
    }

    $button = '<a class="pg-button pg-button-sm" href="' . h($url) . '"><span class="bi bi-pencil" aria-hidden="true"></span>' . h(lang('Edit the texts')) . '</a>';

    return pg_messages_template_card(
        lang('SMS texts'),
        lang('The texts the site sends on its own. A text left empty on the card is the built-in one, in the site\'s language.'),
        $button,
        $html
    );
}

/**
 * The pill tone of a WhatsApp template's state: approved teal, waiting or
 * held amber, refused or stopped rose, the rest gray.
 *
 * @param string $status Meta's status, or DRAFT
 * @return string
 */
function pg_messages_template_whatsapp_tone($status)
{
    $status = (string) $status;

    if ($status === 'APPROVED') {
        return 'teal';
    }

    if (in_array($status, array('PENDING', 'IN_APPEAL', 'PAUSED', 'FLAGGED'), true)) {
        return 'amber';
    }

    if (in_array($status, array('REJECTED', 'DISABLED', 'LIMIT_EXCEEDED'), true)) {
        return 'rose';
    }

    return 'gray';
}

/**
 * A rejection's reason as the list says it; a code Meta adds later is
 * shown as it comes.
 *
 * @param string $reason Meta's rejected_reason, '' for none
 * @return string
 */
function pg_messages_template_whatsapp_reason($reason)
{
    $labels = array(
        'ABUSIVE_CONTENT'      => lang('Abusive content'),
        'INVALID_FORMAT'       => lang('Invalid format'),
        'PROMOTIONAL'          => lang('Promotional content'),
        'TAG_CONTENT_MISMATCH' => lang('The content does not match the category'),
        'SCAM'                 => lang('Seen as a scam'),
        'INCORRECT_CATEGORY'   => lang('Wrong category'),
    );

    $reason = (string) $reason;

    return isset($labels[$reason]) ? $labels[$reason] : $reason;
}

/**
 * The WhatsApp templates as rows of the card. Pure.
 *
 * @param array $templates pg_whatsapp_templates_list() rows
 * @return array list of name, language, category, status, pill, tone, reason
 */
function pg_messages_template_whatsapp_rows(array $templates)
{
    $rows = array();

    foreach ($templates as $template) {
        $status = (string) $template['status'];
        $reason = (string) $template['reason'];

        $rows[] = array(
            'name' => (string) $template['name'],
            'language' => (string) $template['language'],
            'category' => pg_whatsapp_template_category_label((string) $template['category']),
            'status' => $status,
            'pill' => pg_whatsapp_template_status_label($status),
            'tone' => pg_messages_template_whatsapp_tone($status),
            // A reason is kept only while it explains the state: Meta leaves
            // the last one on a template that was approved after an edit.
            'reason' => (($status === 'REJECTED') && ($reason !== '')) ? pg_messages_template_whatsapp_reason($reason) : '',
        );
    }

    return $rows;
}

/**
 * The WhatsApp templates card: the line on the last read from Meta, the
 * Refresh button for who may press it, a failed read as a row of its own,
 * then the templates.
 *
 * @param array $rows  pg_messages_template_whatsapp_rows()
 * @param array $state sub (the line under the title), error (the last
 *                     read's failure, '' for none), refresh (the action
 *                     address, '' for no button), token (the CSRF field),
 *                     settings (the WhatsApp card's address, '' for none),
 *                     empty (the line when there is no template)
 * @return string
 */
function pg_messages_template_whatsapp_card(array $rows, array $state)
{
    $button = '';

    if ((string) $state['refresh'] !== '') {
        $button = '<form method="post" action="' . h((string) $state['refresh']) . '" class="disable_shortcut">'
            . (string) $state['token']
            . '<input type="hidden" name="templates_action" value="whatsapp_refresh">'
            . '<button type="submit" class="pg-button pg-button-sm"><span class="bi bi-arrow-clockwise" aria-hidden="true"></span>' . h(lang('Refresh')) . '</button>'
            . '</form>';
    } elseif ((string) $state['settings'] !== '') {
        $button = '<a class="pg-button pg-button-sm" href="' . h((string) $state['settings']) . '"><span class="bi bi-gear" aria-hidden="true"></span>' . h(lang('WhatsApp settings')) . '</a>';
    }

    $html = '';

    if ((string) $state['error'] !== '') {
        $html .= pg_messages_template_row(array(
            'icon' => 'bi-exclamation-triangle',
            'tone' => 'rose',
            'title' => lang('The last read from Meta failed'),
            'url' => (string) $state['settings'],
            'meta' => '',
            'text' => h((string) $state['error']),
            'pill' => lang('Error'),
            'pill_tone' => 'rose',
        ));
    }

    foreach ($rows as $row) {
        $text = h($row['category']);

        if ($row['reason'] !== '') {
            $text .= ' · <b>' . h(lang('Reason')) . ':</b> ' . h($row['reason']);
        }

        $html .= pg_messages_template_row(array(
            'icon' => 'bi-whatsapp',
            'tone' => 'teal',
            'title' => $row['name'],
            'url' => '',
            'meta' => $row['language'],
            'text' => $text,
            'pill' => $row['pill'],
            'pill_tone' => $row['tone'],
        ));
    }

    if (!$rows && ((string) $state['empty'] !== '')) {
        $html .= '<p class="pg-card-sub pg-messages-card-empty">' . h((string) $state['empty']) . '</p>';
    }

    return pg_messages_template_card(lang('WhatsApp templates'), (string) $state['sub'], $button, $html);
}

/**
 * The card of the templates still to come: e-mail designs.
 *
 * @return string
 */
function pg_messages_template_coming_card()
{
    $rows = pg_messages_template_row(array(
        'icon' => 'bi-envelope',
        'tone' => 'blue',
        'title' => lang('E-mail designs'),
        'url' => '',
        'meta' => '',
        'text' => h(lang('The designs campaigns are sent with, ready ones beside your own.')),
        'pill' => lang('Soon'),
        'pill_tone' => 'gray',
    ));

    return pg_messages_template_card(lang('Other templates'), '', '', $rows);
}
