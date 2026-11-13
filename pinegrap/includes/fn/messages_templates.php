<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Templates (messages_templates.php): the texts a send
 * starts from. Now the SMS texts the site sends on its own (the SMS
 * Messages card, includes/fn/sms_messages.php): each event with its kind,
 * whether it is on and the text it sends, its own or the built-in one. The
 * texts are still edited on their card; a row leads there. E-mail designs
 * and WhatsApp templates are not listed yet.
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_queue_list.php');

/**
 * The SMS texts list's columns.
 *
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_messages_template_columns()
{
    return array(
        'name' => array('heading' => lang('Message'), 'width' => '16rem'),
        'kind' => array('heading' => lang('Kind'), 'width' => '9rem'),
        'state' => array('heading' => lang('State'), 'width' => '7rem', 'kind' => 'badge'),
        'text' => array('heading' => lang('Text'), 'width' => '28rem'),
        'fields' => array('heading' => lang('Fields'), 'width' => '14rem'),
    );
}

/**
 * The SMS texts as list rows: every event the SMS Messages card offers (an
 * ERP event while the module is on, a booking event once bookings are set
 * up), in the card's order. A row's text is its own when one is saved, the
 * built-in one otherwise, marked so; its name leads to the card. Pure.
 *
 * @param array  $definitions pg_sms_message_events()
 * @param array  $settings    pg_sms_message_settings()
 * @param bool   $erp_on
 * @param bool   $booking_on
 * @param string $url         the SMS Messages card's address
 * @return array rows: id, class, cells
 */
function pg_messages_template_sms_rows(array $definitions, array $settings, $erp_on, $booking_on, $url)
{
    $kinds = pg_sms_queue_list_kinds();
    $rows = array();
    $number = 0;

    foreach ($definitions as $event => $definition) {
        $number++;

        if (!pg_sms_message_event_shown($definition, $erp_on, $booking_on)) {
            continue;
        }

        $stored = isset($settings[$event]) && is_array($settings[$event]) ? $settings[$event] : array('on' => false, 'text' => '');
        $own = isset($stored['text']) && is_string($stored['text']) && (trim($stored['text']) !== '');
        $text = $own ? (string) $stored['text'] : (string) $definition['text'];
        $on = !empty($stored['on']);
        $kind = isset($kinds[$definition['kind']]) ? $kinds[$definition['kind']] : (string) $definition['kind'];

        $fields = array();

        foreach ((array) $definition['fields'] as $field) {
            $fields[] = '^^' . $field . '^^';
        }

        $rows[] = array('id' => $number, 'class' => '', 'cells' => array(
            'name' => pg_list_cell('<a href="' . h($url) . '" data-pg-open>' . h((string) $definition['label']) . '</a>'),
            'kind' => pg_list_cell(h($kind)),
            'state' => pg_list_cell('<span class="badge ' . ($on ? 'theme-success' : 'theme-secondary') . '">' . h($on ? lang('On') : lang('Off')) . '</span>', 'align-middle', array('data-pg-sort-value' => $on ? 1 : 0)),
            'text' => pg_list_cell(pg_mail_queue_list_wrap(h($text) . ($own ? '' : ' <span class="badge theme-secondary">' . h(lang('Built-in')) . '</span>'))),
            'fields' => pg_list_cell('<span class="small fg-secondary">' . h(implode(' ', $fields)) . '</span>'),
        ));
    }

    return $rows;
}
