<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The WhatsApp messages the shop sends on its own, beside the text messages
 * of includes/fn/sms_messages.php: an order received, shipped, delivered,
 * and the back-in-stock notice a customer asked for.
 *
 * WhatsApp takes only approved templates outside a customer's own 24 hours,
 * so each event names one of the account's approved templates
 * (whatsapp_templates) and, for each of its variables, which of the event's
 * values fills it. They are kept in connect_apps under 'whatsapp_messages'
 * (not secret, no schema), apart from the account so forgetting it keeps
 * them. Every event is off until an operator switches it on: each message is
 * paid for. A message is only queued (pg_whatsapp_enqueue()); the e-mail and
 * the text message of the same event go as before, WhatsApp never replaces
 * them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The longest value a header variable and any other variable take; Meta's
// header text is at most 60 characters with its variable filled.
define('PG_WHATSAPP_MESSAGE_HEADER_VALUE', 60);
define('PG_WHATSAPP_MESSAGE_VALUE', 1000);

/**
 * The connect_apps key the events are kept under.
 *
 * @return string
 */
function pg_whatsapp_message_key()
{
    return 'whatsapp_messages';
}

/**
 * The events that send a WhatsApp message, in the order the card lists them.
 * The values each may use are the SMS event's fields of the same name
 * (pg_sms_message_events()).
 *
 * @return array event => kind (whatsapp_outbox.kind), label, fields
 */
function pg_whatsapp_message_events()
{
    return array(
        'order_received' => array(
            'kind'   => 'order',
            'label'  => lang('Order received'),
            'fields' => array('store', 'name', 'order_number', 'total'),
        ),
        'order_shipped' => array(
            'kind'   => 'shipping',
            'label'  => lang('Order shipped'),
            'fields' => array('store', 'name', 'order_number', 'carrier', 'tracking_number', 'tracking_url'),
        ),
        'order_delivered' => array(
            'kind'   => 'shipping',
            'label'  => lang('Order delivered'),
            'fields' => array('store', 'name', 'order_number'),
        ),
        'stock_back' => array(
            'kind'   => 'stock',
            'label'  => lang('Product back in stock'),
            'fields' => array('store', 'product', 'url'),
        ),
    );
}

/**
 * What each value is, as the card's lists name it.
 *
 * @return array field => label
 */
function pg_whatsapp_message_field_labels()
{
    return array(
        'store'           => lang('Store name'),
        'name'            => lang('Customer\'s first name'),
        'order_number'    => lang('Order number'),
        'total'           => lang('Order total'),
        'carrier'         => lang('Carrier'),
        'tracking_number' => lang('Tracking number'),
        'tracking_url'    => lang('Tracking link'),
        'product'         => lang('Product name'),
        'url'             => lang('Product link'),
    );
}

/**
 * The stored switches and templates, every event present.
 *
 * @param array|null $options connect_apps options of 'whatsapp_messages'
 * @return array event => on (bool), template ('name|language', '' for none),
 *               vars (slot key => field)
 */
function pg_whatsapp_message_normalize($options)
{
    $stored = (is_array($options) && isset($options['events']) && is_array($options['events'])) ? $options['events'] : array();
    $events = array();

    foreach (array_keys(pg_whatsapp_message_events()) as $event) {
        $row = (isset($stored[$event]) && is_array($stored[$event])) ? $stored[$event] : array();
        $vars = array();

        if (isset($row['vars']) && is_array($row['vars'])) {
            foreach ($row['vars'] as $slot => $field) {
                if (is_string($slot) && is_string($field) && ($field !== '')) {
                    $vars[$slot] = $field;
                }
            }
        }

        $template = (isset($row['template']) && is_string($row['template'])) ? $row['template'] : '';

        $events[$event] = array(
            'on'       => !empty($row['on']) && ($template !== ''),
            'template' => $template,
            'vars'     => $vars,
        );
    }

    return $events;
}

/**
 * The site's switches and templates.
 *
 * @return array pg_whatsapp_message_normalize()
 */
function pg_whatsapp_message_settings()
{
    $app = (function_exists('pg_connect_schema_ready') && pg_connect_schema_ready()) ? pg_connect_app(pg_whatsapp_message_key()) : null;

    return pg_whatsapp_message_normalize(is_array($app) ? $app['options'] : null);
}

/**
 * A template's key as the card and the settings keep it.
 *
 * @param string $name
 * @param string $language
 * @return string
 */
function pg_whatsapp_message_template_key($name, $language)
{
    return (string) $name . '|' . (string) $language;
}

/**
 * The variables of a template's text in the order they are sent: positional
 * ones by number, named ones as they first appear.
 *
 * @param string $text
 * @return string[]
 */
function pg_whatsapp_message_text_variables($text)
{
    preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/', (string) $text, $matches);

    $names = array_values(array_unique($matches[1]));

    if ($names && (count(array_filter($names, 'ctype_digit')) === count($names))) {
        sort($names, SORT_NUMERIC);
    }

    return $names;
}

/**
 * The variables a template needs filled, and why it cannot be sent by an
 * event when it cannot (pure). A media or location header, a copy-code
 * button and Meta's other interactive buttons need more than a text value:
 * those wait for the template designer and the offer codes.
 *
 * @param array $components the template's components as Meta reads them
 * @return array slots (key, part: header|body|button, name, index (a
 *               button's position)), unsupported ('' or the reason)
 */
function pg_whatsapp_message_slots($components)
{
    $slots = array();
    $unsupported = '';

    foreach ((is_array($components) ? $components : array()) as $component) {
        if (!is_array($component) || !isset($component['type']) || !is_string($component['type'])) {
            continue;
        }

        $type = strtoupper($component['type']);

        if ($type === 'HEADER') {
            $format = isset($component['format']) ? strtoupper((string) $component['format']) : 'TEXT';

            if ($format !== 'TEXT') {
                $unsupported = lang('Its header is an image, a video, a document or a location.');
                continue;
            }

            foreach (pg_whatsapp_message_text_variables(isset($component['text']) ? $component['text'] : '') as $name) {
                $slots[] = array('key' => 'header:' . $name, 'part' => 'header', 'name' => $name, 'index' => 0);
            }
        } elseif ($type === 'BODY') {
            foreach (pg_whatsapp_message_text_variables(isset($component['text']) ? $component['text'] : '') as $name) {
                $slots[] = array('key' => 'body:' . $name, 'part' => 'body', 'name' => $name, 'index' => 0);
            }
        } elseif (($type === 'BUTTONS') && isset($component['buttons']) && is_array($component['buttons'])) {
            foreach (array_values($component['buttons']) as $index => $button) {
                $kind = (is_array($button) && isset($button['type'])) ? strtoupper((string) $button['type']) : '';

                if ($kind === 'URL') {
                    foreach (pg_whatsapp_message_text_variables(isset($button['url']) ? $button['url'] : '') as $name) {
                        $slots[] = array('key' => 'button:' . $index, 'part' => 'button', 'name' => $name, 'index' => $index);
                    }
                } elseif (!in_array($kind, array('QUICK_REPLY', 'PHONE_NUMBER'), true)) {
                    $unsupported = lang('It has a copy-code or another button that needs more than a text value.');
                }
            }
        }
    }

    return array('slots' => $slots, 'unsupported' => $unsupported);
}

/**
 * How a slot is named on the card.
 *
 * @param array $slot pg_whatsapp_message_slots() slot
 * @return string
 */
function pg_whatsapp_message_slot_label($slot)
{
    $variable = '{{' . $slot['name'] . '}}';

    if ($slot['part'] === 'header') {
        return lang(array('string' => '{var:1} in the header', 'vars' => array($variable)));
    }

    if ($slot['part'] === 'button') {
        return lang(array('string' => '{var:1} in the link of button {var:2}', 'vars' => array($variable, (int) $slot['index'] + 1)));
    }

    return lang(array('string' => '{var:1} in the message', 'vars' => array($variable)));
}

/**
 * One value as a parameter takes it: one line, no tab, never empty (Meta
 * refuses an empty parameter, so a missing carrier's name is a dash), and a
 * button's value percent-encoded, as the end of a link.
 *
 * @param mixed  $value
 * @param string $part header, body or button
 * @return string
 */
function pg_whatsapp_message_value($value, $part)
{
    $value = is_scalar($value) ? trim((string) preg_replace('/\s+/u', ' ', (string) $value)) : '';
    $value = mb_substr($value, 0, ($part === 'header') ? PG_WHATSAPP_MESSAGE_HEADER_VALUE : PG_WHATSAPP_MESSAGE_VALUE, 'UTF-8');

    if ($value === '') {
        return '-';
    }

    return ($part === 'button') ? rawurlencode($value) : $value;
}

/**
 * The components a send carries, from a template, the event's choice of
 * value per variable and the values (pure).
 *
 * @param array  $template row of whatsapp_templates: status, parameter_format,
 *                         components (decoded)
 * @param array  $vars     slot key => field
 * @param array  $values   field => value
 * @return array ok, components, error
 */
function pg_whatsapp_message_components($template, $vars, $values)
{
    $fail = function ($error) {
        return array('ok' => false, 'components' => array(), 'error' => $error);
    };

    if (!is_array($template) || !isset($template['status']) || (strtoupper((string) $template['status']) !== 'APPROVED')) {
        return $fail(lang('The template is not approved on WhatsApp.'));
    }

    $read = pg_whatsapp_message_slots(isset($template['components']) ? $template['components'] : array());

    if ($read['unsupported'] !== '') {
        return $fail($read['unsupported']);
    }

    $named = isset($template['parameter_format']) && (strtoupper((string) $template['parameter_format']) === 'NAMED');
    $header = array();
    $body = array();
    $buttons = array();

    foreach ($read['slots'] as $slot) {
        if (!isset($vars[$slot['key']]) || !is_string($vars[$slot['key']]) || ($vars[$slot['key']] === '')) {
            return $fail(lang(array('string' => 'Nothing is chosen to fill {var:1}.', 'vars' => array('{{' . $slot['name'] . '}}'))));
        }

        $field = $vars[$slot['key']];
        $parameter = array('type' => 'text');

        if ($named && ($slot['part'] !== 'button')) {
            $parameter['parameter_name'] = $slot['name'];
        }

        $parameter['text'] = pg_whatsapp_message_value(isset($values[$field]) ? $values[$field] : '', $slot['part']);

        if ($slot['part'] === 'header') {
            $header[] = $parameter;
        } elseif ($slot['part'] === 'body') {
            $body[] = $parameter;
        } else {
            $buttons[$slot['index']] = $parameter;
        }
    }

    $components = array();

    if ($header) {
        $components[] = array('type' => 'header', 'parameters' => $header);
    }

    if ($body) {
        $components[] = array('type' => 'body', 'parameters' => $body);
    }

    ksort($buttons);

    foreach ($buttons as $index => $parameter) {
        $components[] = array('type' => 'button', 'sub_type' => 'url', 'index' => (string) $index, 'parameters' => array($parameter));
    }

    return array('ok' => true, 'components' => $components, 'error' => '');
}

/**
 * The templates the card offers: approved, and fillable from an event's
 * values (pg_whatsapp_message_slots()).
 *
 * @param array $rows whatsapp_templates rows: name, language, category,
 *                    status, parameter_format, components (JSON or decoded)
 * @return array 'name|language' => name, language, category, slots
 */
function pg_whatsapp_message_offered($rows)
{
    $offered = array();

    foreach ((is_array($rows) ? $rows : array()) as $row) {
        if (!is_array($row) || !isset($row['status']) || (strtoupper((string) $row['status']) !== 'APPROVED')) {
            continue;
        }

        $components = isset($row['components']) ? $row['components'] : array();
        $components = is_string($components) ? json_decode($components, true) : $components;
        $read = pg_whatsapp_message_slots($components);

        if ($read['unsupported'] !== '') {
            continue;
        }

        $offered[pg_whatsapp_message_template_key($row['name'], $row['language'])] = array(
            'name'     => (string) $row['name'],
            'language' => (string) $row['language'],
            'category' => strtoupper((string) $row['category']),
            'slots'    => $read['slots'],
        );
    }

    return $offered;
}

/**
 * The name of one variable's list on the card: short, a form field's name,
 * the same for the same template and position.
 *
 * @param string $event
 * @param string $template 'name|language'
 * @param int    $position the slot's place in the template
 * @return string
 */
function pg_whatsapp_message_var_name($event, $template, $position)
{
    return 'wa_msg_' . $event . '_' . substr(md5((string) $template), 0, 8) . '_' . (int) $position;
}

/**
 * What a post of the WhatsApp Messages card asks for; no database.
 *
 * A template no longer offered (no longer approved) is kept while its event
 * is off, so a template Meta paused does not lose the choices made for it.
 *
 * @param array $post
 * @param array $before  pg_whatsapp_message_settings()
 * @param array $offered pg_whatsapp_message_offered()
 * @return array|null events, changed, marks (field => sentence); null when
 *                    the post has no such card
 */
function pg_whatsapp_message_settings_decide($post, $before, $offered)
{
    if (empty($post['wa_msg_card'])) {
        return null;
    }

    $fields = pg_whatsapp_message_field_labels();
    $events = array();
    $marks = array();

    foreach (pg_whatsapp_message_events() as $event => $definition) {
        $on = !empty($post['wa_msg_' . $event . '_on']);
        $template = (isset($post['wa_msg_' . $event . '_template']) && is_string($post['wa_msg_' . $event . '_template'])) ? trim($post['wa_msg_' . $event . '_template']) : '';
        $vars = array();

        if ($template === '') {
            if ($on) {
                $marks['wa_msg_' . $event . '_template'] = lang('Choose a template to switch this message on.');
            }

            $events[$event] = array('on' => false, 'template' => '', 'vars' => array());
            continue;
        }

        if (!isset($offered[$template])) {
            if ($on) {
                $marks['wa_msg_' . $event . '_template'] = lang('This template is not approved on WhatsApp, or it has a part an order\'s values cannot fill.');
            }

            $kept = ($template === $before[$event]['template']) ? $before[$event]['vars'] : array();
            $events[$event] = array('on' => false, 'template' => $template, 'vars' => $kept);
            continue;
        }

        foreach ($offered[$template]['slots'] as $position => $slot) {
            $name = pg_whatsapp_message_var_name($event, $template, $position);
            $field = (isset($post[$name]) && is_string($post[$name])) ? $post[$name] : '';

            if (($field !== '') && (!in_array($field, $definition['fields'], true) || !isset($fields[$field]))) {
                $marks[$name] = lang('This value is not one of the message\'s values.');
                continue;
            }

            if ($field === '') {
                if ($on) {
                    $marks[$name] = lang(array('string' => 'Choose what fills {var:1}.', 'vars' => array(h('{{' . $slot['name'] . '}}'))));
                }

                continue;
            }

            $vars[$slot['key']] = $field;
        }

        $events[$event] = array('on' => $on, 'template' => $template, 'vars' => $vars);
    }

    return array(
        'events'  => $events,
        'changed' => ($events !== $before),
        'marks'   => $marks,
    );
}

/**
 * The account's templates the card offers.
 *
 * @return array pg_whatsapp_message_offered()
 */
function pg_whatsapp_message_templates()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');

    if (!pg_whatsapp_templates_ready()) {
        return array();
    }

    return pg_whatsapp_message_offered((array) db_items(
        "SELECT name, language, category, status, parameter_format, components
        FROM whatsapp_templates
        WHERE status = 'APPROVED'
        ORDER BY name, language"));
}

/**
 * Checks the WhatsApp Messages card before the Communication settings are
 * written: a refused choice is marked, so nothing of the category is.
 *
 * @param array    $post
 * @param liveform $liveform
 * @return array|null what pg_whatsapp_message_settings_save() writes
 */
function pg_whatsapp_message_settings_check($post, $liveform)
{
    if (empty($post['wa_msg_card'])) {
        return null;
    }

    $decision = pg_whatsapp_message_settings_decide($post, pg_whatsapp_message_settings(), pg_whatsapp_message_templates());

    foreach ($decision['marks'] as $field => $sentence) {
        $liveform->mark_error($field, $sentence);
    }

    if ($decision['changed'] && !$decision['marks'] && !pg_connect_schema_ready()) {
        $liveform->mark_error('wa_msg_order_received_on', lang('The database has not been upgraded yet. Run the software update first.'));
    }

    return $decision;
}

/**
 * Writes the switches and templates, only when they changed.
 *
 * @param array|null $decision pg_whatsapp_message_settings_check()
 * @param int        $user_id
 */
function pg_whatsapp_message_settings_save($decision, $user_id)
{
    if (!is_array($decision) || !$decision['changed'] || $decision['marks'] || !pg_connect_schema_ready()) {
        return;
    }

    $options = e(json_encode(array('events' => $decision['events']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    db("INSERT INTO connect_apps
        SET
            provider = '" . e(pg_whatsapp_message_key()) . "',
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
 * One event on the card: its switch, its template and, per template, what
 * fills each variable. Only the chosen template's list shows; the card's
 * script shows another when the template is changed.
 *
 * @param string $event
 * @param array  $definition pg_whatsapp_message_events() row
 * @param array  $stored     pg_whatsapp_message_settings() row
 * @param array  $offered    pg_whatsapp_message_offered()
 * @return string
 */
function pg_whatsapp_message_event_html($event, $definition, $stored, $offered)
{
    $name = 'wa_msg_' . $event . '_';
    $labels = pg_whatsapp_message_field_labels();

    $options = '<option value="">' . h(lang('Choose a template')) . '</option>';

    // A template chosen before and no longer offered stays listed, named as
    // such, so a save while the event is off keeps it.
    if (($stored['template'] !== '') && !isset($offered[$stored['template']])) {
        $options .= '<option value="' . h($stored['template']) . '" selected="selected">' . h(str_replace('|', ' · ', $stored['template']) . ' (' . lang('not approved') . ')') . '</option>';
    }

    $blocks = '';

    foreach ($offered as $key => $template) {
        $category = function_exists('pg_whatsapp_template_category_label') ? pg_whatsapp_template_category_label($template['category']) : $template['category'];
        $options .= '<option value="' . h($key) . '"' . (($key === $stored['template']) ? ' selected="selected"' : '') . '>' . h($template['name'] . ' · ' . $template['language'] . ' · ' . $category) . '</option>';

        $rows = '';

        foreach ($template['slots'] as $position => $slot) {
            $field_name = pg_whatsapp_message_var_name($event, $key, $position);
            $chosen = (($key === $stored['template']) && isset($stored['vars'][$slot['key']])) ? $stored['vars'][$slot['key']] : '';
            $choices = '<option value="">' . h(lang('Choose a value')) . '</option>';

            foreach ($definition['fields'] as $field) {
                $choices .= '<option value="' . h($field) . '"' . (($field === $chosen) ? ' selected="selected"' : '') . '>' . h($labels[$field]) . '</option>';
            }

            $rows .= '
                                                    <div class="col-12 md:col-6">
                                                        <label class="form-label" for="' . h($field_name) . '">' . h(pg_whatsapp_message_slot_label($slot)) . '</label>
                                                        <select name="' . h($field_name) . '" id="' . h($field_name) . '" class="form-control">' . $choices . '</select>
                                                    </div>';
        }

        if ($rows === '') {
            $rows = '
                                                    <div class="col-12 form-text">' . lang('This template has no variables.') . '</div>';
        }

        $blocks .= '
                                                <div class="row gy-3 mt-0" data-pg-wa-vars="' . h($key) . '"' . (($key === $stored['template']) ? '' : ' hidden') . '>' . $rows . '
                                                </div>';
    }

    return '
                                        <div class="col-12" data-pg-wa-event="' . h($event) . '">
                                            <div class="form-field">
                                                <div class="switch"><input type="checkbox" name="' . h($name) . 'on" id="' . h($name) . 'on" value="1"' . ($stored['on'] ? ' checked="checked"' : '') . ' class=""/></div>
                                                <label class="" for="' . h($name) . 'on" id="' . h($name) . 'label">' . h($definition['label']) . '</label>
                                            </div>
                                            <label class="form-label mt-3" for="' . h($name) . 'template">' . lang('Template') . '</label>
                                            <select name="' . h($name) . 'template" id="' . h($name) . 'template" class="form-control" data-pg-wa-template>' . $options . '</select>' . $blocks . '
                                        </div>';
}

/**
 * The WhatsApp Messages card's fields.
 *
 * @return string
 */
function pg_whatsapp_message_settings_fields()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');

    $settings = pg_whatsapp_message_settings();
    $offered = pg_whatsapp_message_templates();

    $html = '
                                        <input type="hidden" name="wa_msg_card" value="1"/>
                                        <div class="col-12 form-text">' . lang('Sent to the order\'s billing phone number, or for a product back in stock to the number the customer gave, with one of your approved WhatsApp templates. Choose a utility template: a marketing one goes only to a number with marketing consent. The e-mail and the SMS of the same moment are still sent; switch the SMS off if WhatsApp is to take its place.') . '</div>';

    if (!pg_whatsapp_ready()) {
        $html .= '
                                        <div class="col-12"><div class="alert theme-warning mb-0">' . lang('WhatsApp is not set up: no message is sent until the WhatsApp account above is saved and switched on.') . '</div></div>';
    }

    if (!$offered) {
        $html .= '
                                        <div class="col-12"><div class="alert theme-info mb-0">' . lang('No approved template can be chosen yet. Templates are read from Meta every six hours and after the WhatsApp card is saved; one with an image, video or document header or a copy-code button cannot be used here.') . '</div></div>';
    }

    foreach (pg_whatsapp_message_events() as $event => $definition) {
        $html .= pg_whatsapp_message_event_html($event, $definition, $settings[$event], $offered);
    }

    return $html;
}

/**
 * Shows the chosen template's variable lists. Bound once on the document:
 * the pane's markup is replaced on every visit.
 *
 * @return string
 */
function pg_whatsapp_message_settings_script()
{
    return '
    <script>
        (function () {
            if (window.pgWhatsappMessagesBound) {
                return;
            }

            window.pgWhatsappMessagesBound = true;

            document.addEventListener("change", function (event) {
                var select = event.target.closest("[data-pg-wa-template]");
                var block = select ? select.closest("[data-pg-wa-event]") : null;

                if (!block) {
                    return;
                }

                block.querySelectorAll("[data-pg-wa-vars]").forEach(function (vars) {
                    vars.hidden = (vars.getAttribute("data-pg-wa-vars") !== select.value);
                });
            });
        })();
    </script>';
}

/**
 * Queues one event's WhatsApp message. Quiet: an event switched off,
 * WhatsApp not set up, a template no longer approved or a number WhatsApp
 * cannot take sends nothing and stops nothing.
 *
 * @param string $event  pg_whatsapp_message_events()
 * @param string $to     the phone number as the customer typed it
 * @param array  $values field => value; store is added when missing
 * @param string $reference
 * @return bool whether a message is queued (or already was)
 */
function pg_whatsapp_message_queue($event, $to, $values, $reference)
{
    $definitions = pg_whatsapp_message_events();

    if (!isset($definitions[$event]) || (trim((string) $to) === '')) {
        return false;
    }

    $settings = pg_whatsapp_message_settings();

    if (!$settings[$event]['on']) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');

    if (!pg_whatsapp_ready()) {
        return false;
    }

    list($name, $language) = array_pad(explode('|', $settings[$event]['template'], 2), 2, '');
    $template = pg_whatsapp_template_find($name, $language);
    $values += array('store' => defined('ORGANIZATION_NAME') ? ORGANIZATION_NAME : '');
    $filled = pg_whatsapp_message_components($template, $settings[$event]['vars'], $values);

    if (!$filled['ok']) {
        if (function_exists('log_activity')) {
            log_activity('WhatsApp message "' . $definitions[$event]['label'] . '" was not queued: ' . $filled['error']);
        }

        return false;
    }

    // News of an order or of stock is no use days late: a queue held up that
    // long (the general job stopped) drops it instead.
    $queued = pg_whatsapp_enqueue($to, array(
        'id'         => (int) $template['id'],
        'name'       => $template['name'],
        'language'   => $template['language'],
        'category'   => $template['category'],
        'components' => $filled['components'],
    ), array(
        'kind'       => $definitions[$event]['kind'],
        'reference'  => $reference,
        'expires_at' => time() + 2 * 86400,
    ));

    return $queued['ok'];
}

/**
 * An order was placed: called by submit_order() beside the text message.
 *
 * @param int $order_id
 * @return bool
 */
function pg_whatsapp_order_received($order_id)
{
    if (!pg_whatsapp_message_settings()['order_received']['on']) {
        return false;
    }

    $order = pg_sms_message_order($order_id);

    return $order && pg_whatsapp_message_queue('order_received', $order['phone'], $order['fields'], 'order:' . (int) $order_id . ':received');
}

/**
 * An address of an order gained its tracking numbers: called by
 * update_order() where the text message is, under the same rule.
 *
 * @param int      $order_id
 * @param int      $ship_to_id
 * @param string[] $numbers
 * @return bool
 */
function pg_whatsapp_order_shipped($order_id, $ship_to_id, $numbers)
{
    if (!pg_whatsapp_message_settings()['order_shipped']['on']) {
        return false;
    }

    $order = pg_sms_message_order($order_id);

    if (!$order) {
        return false;
    }

    $values = $order['fields'] + pg_sms_order_shipped_fields($ship_to_id, $numbers);

    return pg_whatsapp_message_queue('order_shipped', $order['phone'], $values, 'order:' . (int) $order_id . ':shipped:' . (int) $ship_to_id);
}

/**
 * An address's goods arrived: called by pg_shipment_delivered() beside the
 * text message.
 *
 * @param int $order_id
 * @param int $ship_to_id
 * @return bool
 */
function pg_whatsapp_order_delivered($order_id, $ship_to_id)
{
    if (!pg_whatsapp_message_settings()['order_delivered']['on']) {
        return false;
    }

    $order = pg_sms_message_order($order_id);

    return $order && pg_whatsapp_message_queue('order_delivered', $order['phone'], $order['fields'], 'order:' . (int) $order_id . ':delivered:' . (int) $ship_to_id);
}

/**
 * Whether the back-in-stock event is switched on and WhatsApp set up, for the request form's
 * number field (pg_stock_notify_phone_asked()).
 *
 * @return bool
 */
function pg_whatsapp_stock_back_on()
{
    if (!pg_whatsapp_message_settings()['stock_back']['on']) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');

    return pg_whatsapp_ready();
}
