<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The text messages the shop sends on its own: an order received, shipped,
 * delivered, and the back-in-stock notice a customer asked for.
 *
 * Each event has a switch and a template on the Communication settings' SMS
 * Messages card. They are kept in connect_apps under 'sms_templates', beside
 * the provider accounts but not in one of them, so changing or removing the
 * provider keeps the texts. An empty template is the built-in text, in the
 * site's language when the message is queued.
 *
 * Every event is off until an operator switches it on: each message is paid
 * for, so turning SMS on for the test message does not start texting every
 * customer. A message is only queued (pg_sms_enqueue()), with a reference per
 * order and event, so the same event saved twice is texted once while its
 * queue row is kept. These are transactional messages: no promotion goes in
 * them, which the card says, and pg_sms_check() refuses a commercial kind.
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
 * The events that send a message, in the order the card lists them.
 *
 * @return array event => kind (pg_sms_kinds()), label, text (the built-in
 *               template), fields (the ^^name^^ fields it may use), note
 *               (optional: what switching it on changes)
 */
function pg_sms_message_events()
{
    return array(
        'order_received' => array(
            'kind'   => 'order',
            'label'  => lang('Order received'),
            'text'   => lang('^^store^^: Your order ^^order_number^^ has been received. Total: ^^total^^.'),
            'fields' => array('store', 'name', 'order_number', 'total'),
        ),
        'order_shipped' => array(
            'kind'   => 'shipping',
            'label'  => lang('Order shipped'),
            'text'   => lang('^^store^^: Your order ^^order_number^^ has been shipped. ^^carrier^^ ^^tracking_number^^ ^^tracking_url^^'),
            'fields' => array('store', 'name', 'order_number', 'carrier', 'tracking_number', 'tracking_url'),
        ),
        'order_delivered' => array(
            'kind'   => 'shipping',
            'label'  => lang('Order delivered'),
            'text'   => lang('^^store^^: Your order ^^order_number^^ has been delivered.'),
            'fields' => array('store', 'name', 'order_number'),
        ),
        // Sent once, to the number the customer gave when asking for this
        // product: an informational message, so it names the product and
        // nothing else.
        'stock_back' => array(
            'kind'   => 'stock',
            'label'  => lang('Product back in stock'),
            'text'   => lang('^^store^^: ^^product^^, which you asked us about, is back in stock. ^^url^^'),
            'fields' => array('store', 'product', 'url'),
        ),
        // The one-time code a cash-on-delivery order is confirmed with
        // (includes/fn/sms_codes.php): sent before the order exists, so it
        // has no order number.
        'cod_code' => array(
            'kind'   => 'otp',
            'label'  => lang('Cash-on-delivery confirmation code'),
            'text'   => lang('^^store^^: Your code to confirm your cash-on-delivery order is ^^code^^. Do not share it with anyone.'),
            'fields' => array('store', 'code'),
            'note'   => lang('While this is on, an order paid by offline payment (not one put on account) is placed only with the code texted to its billing phone number, which must be a mobile number.'),
        ),
        // A member's sign-in code, asked for at the second step in place of
        // the authenticator app's code.
        'signin_code' => array(
            'kind'   => 'otp',
            'label'  => lang('Sign-in code'),
            'text'   => lang('^^store^^: Your sign-in code is ^^code^^. Do not share it with anyone.'),
            'fields' => array('store', 'code'),
            'note'   => lang('While this is on, a member account (User role) with two-step verification may ask for a code to its contact\'s mobile phone number instead of the code from its app. Staff accounts are never offered it.'),
        ),
    );
}

/**
 * The longest template the card takes, in characters. Three SMS parts of
 * Turkish text; the fields filled in can make the message longer, up to
 * pg_sms_max_length().
 *
 * @return int
 */
function pg_sms_message_max_length()
{
    return 480;
}

/**
 * The ^^name^^ fields a template uses, in order of first use.
 *
 * @param string $template
 * @return string[]
 */
function pg_sms_message_template_fields($template)
{
    preg_match_all('/\^\^([A-Za-z0-9_]+)\^\^/', (string) $template, $matches);

    return array_values(array_unique($matches[1]));
}

/**
 * A template with its fields filled in. A field without a value (an unknown
 * one too) becomes nothing, and the spaces it leaves are closed up, so an
 * order shipped without a carrier's name does not leave a gap.
 *
 * @param string $template
 * @param array  $fields name => value
 * @return string
 */
function pg_sms_message_render($template, $fields)
{
    $text = preg_replace_callback('/\^\^([A-Za-z0-9_]+)\^\^/', function ($match) use ($fields) {
        if (!isset($fields[$match[1]]) || !is_scalar($fields[$match[1]])) {
            return '';
        }

        // A value is one line: a line break typed into a name stays out.
        return trim((string) preg_replace('/\s+/u', ' ', (string) $fields[$match[1]]));
    }, (string) $template);

    $text = preg_replace('/[ \t]{2,}/', ' ', (string) $text);
    $text = preg_replace('/[ \t]+(?=[.,;:!?)]|\n|$)/', '', (string) $text);

    return pg_sms_text($text);
}

/**
 * The stored switches and templates, every event present.
 *
 * @param array|null $options connect_apps options of 'sms_templates'
 * @return array event => on (bool), text ('' for the built-in one)
 */
function pg_sms_message_normalize($options)
{
    $stored = (is_array($options) && isset($options['events']) && is_array($options['events'])) ? $options['events'] : array();
    $events = array();

    foreach (array_keys(pg_sms_message_events()) as $event) {
        $row = (isset($stored[$event]) && is_array($stored[$event])) ? $stored[$event] : array();

        $events[$event] = array(
            'on'   => !empty($row['on']),
            'text' => (isset($row['text']) && is_string($row['text'])) ? $row['text'] : '',
        );
    }

    return $events;
}

/**
 * The site's switches and templates.
 *
 * @return array pg_sms_message_normalize()
 */
function pg_sms_message_settings()
{
    $app = (function_exists('pg_connect_schema_ready') && pg_connect_schema_ready()) ? pg_connect_app('sms_templates') : null;

    return pg_sms_message_normalize(is_array($app) ? $app['options'] : null);
}

/**
 * What a post of the SMS Messages card asks for; no database.
 *
 * @param array $post
 * @param array $before pg_sms_message_settings()
 * @return array|null events (pg_sms_message_normalize() shape), changed,
 *                    marks (field => sentence); null when the post has no
 *                    such card
 */
function pg_sms_message_settings_decide($post, $before)
{
    if (empty($post['sms_msg_card'])) {
        return null;
    }

    $definitions = pg_sms_message_events();
    $events = array();
    $marks = array();

    foreach ($definitions as $event => $definition) {
        $text = pg_sms_text(pg_sms_post($post, 'sms_msg_' . $event . '_text'));

        // The built-in text sent back unchanged stays the built-in one, so it
        // follows the site's language and a later wording.
        if ($text === pg_sms_text($definition['text'])) {
            $text = '';
        }

        $events[$event] = array(
            'on'   => !empty($post['sms_msg_' . $event . '_on']),
            'text' => $text,
        );

        if (mb_strlen($text, 'UTF-8') > pg_sms_message_max_length()) {
            $marks['sms_msg_' . $event . '_text'] = lang(array('string' => 'The message is longer than {var:1} characters.', 'vars' => array(pg_sms_message_max_length())));
            continue;
        }

        $used = pg_sms_message_template_fields($text);
        $unknown = array_diff($used, $definition['fields']);

        if ($unknown) {
            $marks['sms_msg_' . $event . '_text'] = lang(array(
                'string' => 'Unknown field {var:1}. This message can use: {var:2}',
                'vars'   => array(h('^^' . reset($unknown) . '^^'), h(pg_sms_message_field_list($definition['fields'])))));

        // A code's message without the code would confirm nothing.
        } elseif (($text !== '') && in_array('code', $definition['fields'], true) && !in_array('code', $used, true)) {
            $marks['sms_msg_' . $event . '_text'] = lang(array('string' => 'The message must contain {var:1}.', 'vars' => array(h('^^code^^'))));
        }
    }

    return array(
        'events'  => $events,
        'changed' => ($events != $before),
        'marks'   => $marks,
    );
}

/**
 * Fields written the way a template uses them.
 *
 * @param string[] $fields
 * @return string
 */
function pg_sms_message_field_list($fields)
{
    $list = array();

    foreach ($fields as $field) {
        $list[] = '^^' . $field . '^^';
    }

    return implode(' ', $list);
}

/**
 * Checks the SMS Messages card before the Communication settings are
 * written: a refused template is marked, so nothing of the category is.
 *
 * @param array    $post
 * @param liveform $liveform
 * @return array|null what pg_sms_message_settings_save() writes
 */
function pg_sms_message_settings_check($post, $liveform)
{
    $decision = pg_sms_message_settings_decide($post, pg_sms_message_settings());

    if ($decision === null) {
        return null;
    }

    foreach ($decision['marks'] as $field => $sentence) {
        $liveform->mark_error($field, $sentence);
    }

    if ($decision['changed'] && !$decision['marks'] && !pg_connect_schema_ready()) {
        $liveform->mark_error('sms_msg_order_received_on', lang('The database has not been upgraded yet. Run the software update first.'));
    }

    return $decision;
}

/**
 * Writes the switches and templates, only when they changed. Not secret, so
 * nothing is sealed and no encryption key is needed.
 *
 * @param array|null $decision pg_sms_message_settings_check()
 * @param int        $user_id
 */
function pg_sms_message_settings_save($decision, $user_id)
{
    if (!is_array($decision) || !$decision['changed'] || $decision['marks'] || !pg_connect_schema_ready()) {
        return;
    }

    $options = e(json_encode(array('events' => $decision['events'])));

    db("INSERT INTO connect_apps
        SET
            provider = 'sms_templates',
            options = '" . $options . "',
            enabled = 1,
            updated_user_id = '" . (int) $user_id . "',
            updated_timestamp = UNIX_TIMESTAMP()
        ON DUPLICATE KEY UPDATE
            options = VALUES(options),
            updated_user_id = VALUES(updated_user_id),
            updated_timestamp = VALUES(updated_timestamp)");
}

/**
 * The SMS Messages card's fields.
 *
 * @return string
 */
function pg_sms_message_settings_fields()
{
    $settings = pg_sms_message_settings();

    $html = '
                                        <input type="hidden" name="sms_msg_card" value="1"/>
                                        <div class="col-12 form-text">' . lang('Sent to the order\'s billing phone number, or for a product back in stock to the number the customer gave, if it is a mobile number and SMS is set up. These are informational messages: put no promotion, discount or campaign in them, or they need the recipient\'s consent recorded in İYS. Leave a text empty to send the built-in one.') . '</div>';

    if (!pg_sms_ready()) {
        $html .= '
                                        <div class="col-12"><div class="alert theme-warning mb-0">' . lang('SMS is not set up: no message is sent until an SMS account is saved and switched on.') . '</div></div>';
    }

    foreach (pg_sms_message_events() as $event => $definition) {
        $name = 'sms_msg_' . $event . '_';
        $text = ($settings[$event]['text'] !== '') ? $settings[$event]['text'] : $definition['text'];

        $html .= '
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input type="checkbox" name="' . h($name) . 'on" id="' . h($name) . 'on" value="1"' . ($settings[$event]['on'] ? ' checked="checked"' : '') . ' class=""/></div>
                                                <label class="" for="' . h($name) . 'on" id="' . h($name) . 'label">' . h($definition['label']) . '</label>
                                            </div>
                                            <textarea name="' . h($name) . 'text" id="' . h($name) . 'text" aria-labelledby="' . h($name) . 'label" class="form-control mt-3" rows="2" maxlength="' . (int) pg_sms_message_max_length() . '">' . h($text) . '</textarea>
                                            <div class="form-text">' . lang(array('string' => 'Fields: {var:1}', 'vars' => array(h(pg_sms_message_field_list($definition['fields']))))) . '</div>' . (isset($definition['note']) ? '
                                            <div class="form-text">' . h($definition['note']) . '</div>' : '') . '
                                        </div>';
    }

    return $html;
}

/**
 * One event's message with its fields filled in: the site's template, or the
 * built-in one while the template is empty.
 *
 * @param string     $event    pg_sms_message_events()
 * @param array      $fields   name => value; store is added when missing
 * @param array|null $settings pg_sms_message_settings(), read when null
 * @return string
 */
function pg_sms_message_text($event, $fields, $settings = null)
{
    $definitions = pg_sms_message_events();
    $settings = is_array($settings) ? $settings : pg_sms_message_settings();
    $template = (isset($settings[$event]) && ($settings[$event]['text'] !== '')) ? $settings[$event]['text'] : $definitions[$event]['text'];

    $fields += array('store' => defined('ORGANIZATION_NAME') ? ORGANIZATION_NAME : '');

    return pg_sms_message_render($template, $fields);
}

/**
 * Queues one event's message. Quiet: an event switched off, SMS not set up or
 * a number that is not a mobile one sends nothing and stops nothing.
 *
 * @param string $event  pg_sms_message_events()
 * @param string $to     the phone number as the customer typed it
 * @param array  $fields name => value
 * @param string $reference
 * @return bool whether a message is queued (or already was)
 */
function pg_sms_message_queue($event, $to, $fields, $reference)
{
    $definitions = pg_sms_message_events();

    if (!isset($definitions[$event]) || (trim((string) $to) === '')) {
        return false;
    }

    $settings = pg_sms_message_settings();

    if (!$settings[$event]['on'] || !pg_sms_ready()) {
        return false;
    }

    // News of an order or of stock is no use days late: a queue held up that
    // long (the general job stopped) drops it instead.
    $queued = pg_sms_enqueue($to, pg_sms_message_text($event, $fields, $settings), $definitions[$event]['kind'], array(
        'reference'  => $reference,
        'expires_at' => time() + 2 * 86400,
    ));

    return $queued['ok'];
}

/**
 * Whether the shop texts the customer of an order (pure): not for an order a
 * marketplace sold (orders.type 'marketplace'). The marketplace tells its own
 * buyer, and the phone on the order may be the marketplace's relay; the
 * cancellation e-mail stays away from it too
 * (pg_order_cancellation_mails_customer()).
 *
 * @param array $order a row with `type`
 * @return bool
 */
function pg_sms_order_texted($order)
{
    return !(isset($order['type']) && ((string) $order['type'] === 'marketplace'));
}

/**
 * The order's own fields and its customer's number; null for an order the
 * shop does not text (pg_sms_order_texted()).
 *
 * @param int $order_id
 * @return array|null phone, fields
 */
function pg_sms_message_order($order_id)
{
    $order = db_item(
        "SELECT order_number, billing_first_name, billing_phone_number, total, type
        FROM orders
        WHERE id = '" . (int) $order_id . "'");

    if (!$order || !pg_sms_order_texted($order)) {
        return null;
    }

    return array(
        'phone'  => (string) $order['billing_phone_number'],
        'fields' => array(
            'order_number' => (string) $order['order_number'],
            'name'         => (string) $order['billing_first_name'],
            'total'        => pg_money_text((int) $order['total'] / 100, BASE_CURRENCY_SYMBOL),
        ),
    );
}

/**
 * An order was placed: called by submit_order() once the order has its
 * number.
 *
 * @param int $order_id
 * @return bool
 */
function pg_sms_order_received($order_id)
{
    if (!pg_sms_message_settings()['order_received']['on']) {
        return false;
    }

    $order = pg_sms_message_order($order_id);

    return $order && pg_sms_message_queue('order_received', $order['phone'], $order['fields'], 'order:' . (int) $order_id . ':received');
}

/**
 * An address of an order gained its tracking numbers: called by
 * update_order(), for the order screen, the Workspace and the external API
 * alike, unless the caller keeps the customer's notice back. A marketplace's
 * order is not texted: the marketplace tells its buyer.
 *
 * One message per address: a number added to it later is not texted again.
 *
 * @param int      $order_id
 * @param int      $ship_to_id
 * @param string[] $numbers the address's numbers as stored
 * @return bool
 */
function pg_sms_order_shipped($order_id, $ship_to_id, $numbers)
{
    if (!pg_sms_message_settings()['order_shipped']['on']) {
        return false;
    }

    $order = pg_sms_message_order($order_id);

    if (!$order) {
        return false;
    }

    $method = (string) db_value("SELECT shipping_method_code FROM ship_tos WHERE id = '" . (int) $ship_to_id . "'");
    $default = pg_shipping_default_carrier();
    $numbers = array_map('strtolower', array_map('strval', (array) $numbers));
    $first = null;
    $listed = array();

    foreach (pg_shipment_rows($ship_to_id) as $row) {
        if (in_array(strtolower((string) $row['number']), $numbers, true)) {
            $view = pg_shipment_view($row, $method, $default);
            $listed[] = $view['number'];
            $first = ($first === null) ? $view : $first;
        }
    }

    $order['fields'] += array(
        'carrier'         => $first ? $first['carrier_name'] : '',
        'tracking_number' => implode(', ', $listed),
        'tracking_url'    => $first ? $first['url'] : '',
    );

    return pg_sms_message_queue('order_shipped', $order['phone'], $order['fields'], 'order:' . (int) $order_id . ':shipped:' . (int) $ship_to_id);
}

/**
 * An address's goods arrived: called by pg_shipment_delivered() the one time
 * it announces the delivery, when the customer is to be told.
 *
 * @param int $order_id
 * @param int $ship_to_id
 * @return bool
 */
function pg_sms_order_delivered($order_id, $ship_to_id)
{
    if (!pg_sms_message_settings()['order_delivered']['on']) {
        return false;
    }

    $order = pg_sms_message_order($order_id);

    return $order && pg_sms_message_queue('order_delivered', $order['phone'], $order['fields'], 'order:' . (int) $order_id . ':delivered:' . (int) $ship_to_id);
}
