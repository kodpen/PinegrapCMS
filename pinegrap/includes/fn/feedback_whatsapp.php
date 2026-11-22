<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The WhatsApp message of a satisfaction survey: one of the account's
 * approved templates, chosen on the survey's "Sent by" card, and what fills
 * each of its variables (the store's name, the question, the answer link, or
 * the link's code for a button whose address ends in it). The choice is kept
 * in the survey's texts JSON under "whatsapp": {"template": "name|language",
 * "vars": {slot: value}}; no column of its own.
 *
 * A WhatsApp survey whose template is not chosen, not approved, or not
 * fully filled keeps its rows queued, as does every WhatsApp survey while
 * WhatsApp is not set up: the general job's pass only takes the rows of the
 * surveys pg_feedback_whatsapp_ready_surveys() names. The message is only
 * queued (pg_whatsapp_enqueue(), kind feedback); the general job sends it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_messages.php');

/** The field prefix of the "Sent by" card's template and variable lists. */
define('PG_FEEDBACK_WA_PREFIX', 'fs_wa');

/**
 * A stored or posted choice with every key (pure).
 *
 * @param mixed $data the texts JSON's "whatsapp" entry
 * @return array template ('name|language' or ''), vars (slot key => value)
 */
function pg_feedback_whatsapp_normalize($data)
{
    $data = is_array($data) ? $data : array();
    $template = (isset($data['template']) && is_string($data['template'])) ? trim($data['template']) : '';
    $vars = array();

    foreach ((isset($data['vars']) && is_array($data['vars'])) ? $data['vars'] : array() as $slot => $value) {
        if (is_string($slot) && is_string($value) && ($value !== '') && isset(pg_feedback_whatsapp_value_names()[$value])) {
            $vars[$slot] = $value;
        }
    }

    return array('template' => $template, 'vars' => ($template === '') ? array() : $vars);
}

/**
 * A survey's choice, read from its stored texts JSON (pure).
 *
 * @param string|null $json feedback_surveys.texts
 * @return array pg_feedback_whatsapp_normalize()
 */
function pg_feedback_whatsapp_from_texts($json)
{
    $data = json_decode((string) $json, true);

    return pg_feedback_whatsapp_normalize(is_array($data) ? ($data['whatsapp'] ?? null) : null);
}

/**
 * The values a variable may take (pure but for lang()).
 *
 * @return array value => label
 */
function pg_feedback_whatsapp_value_names()
{
    return array(
        'store'    => lang('Store name'),
        'question' => lang('Question'),
        'link'     => lang('Answer link'),
        'code'     => lang('Answer link code (for a button link ending in it)'),
    );
}

/**
 * The values of one message (pure): the link is the answer page with the
 * row's token; the code is the token alone, for a URL button whose address
 * in the template ends in feedback.php?t=.
 *
 * @param string $store
 * @param string $question
 * @param string $page_link pg_feedback_links()['page']
 * @return array value => text
 */
function pg_feedback_whatsapp_values($store, $question, $page_link)
{
    $query = (string) parse_url((string) $page_link, PHP_URL_QUERY);
    parse_str($query, $parts);

    return array(
        'store'    => (string) $store,
        'question' => (string) $question,
        'link'     => (string) $page_link,
        'code'     => (isset($parts['t']) && is_string($parts['t'])) ? $parts['t'] : '',
    );
}

/**
 * Whether a choice can be sent now (pure): its template offered (approved,
 * fillable) and every variable given a value.
 *
 * @param array $choice  pg_feedback_whatsapp_normalize()
 * @param array $offered pg_whatsapp_message_offered()
 * @return bool
 */
function pg_feedback_whatsapp_choice_ready(array $choice, array $offered)
{
    if (($choice['template'] === '') || !isset($offered[$choice['template']])) {
        return false;
    }

    foreach ($offered[$choice['template']]['slots'] as $slot) {
        if (!isset($choice['vars'][$slot['key']])) {
            return false;
        }
    }

    return true;
}

/**
 * The "Sent by" card's post as a choice, with what is wrong (pure but for
 * lang()). Only a WhatsApp survey whose template is offered is checked: no
 * template, or one no longer approved, is saved as it is and its rows wait.
 *
 * @param array  $post
 * @param string $channel the survey's channel as read from the post
 * @param array  $before  the stored choice
 * @param array  $offered pg_whatsapp_message_offered()
 * @return array choice, errors (list of sentences)
 */
function pg_feedback_whatsapp_input(array $post, $channel, array $before, array $offered)
{
    $prefix = PG_FEEDBACK_WA_PREFIX;
    $template = (isset($post[$prefix . '_template']) && is_string($post[$prefix . '_template'])) ? trim($post[$prefix . '_template']) : '';
    $post[$prefix . '_on'] = (($channel === 'whatsapp') && isset($offered[$template])) ? '1' : '';
    $decided = pg_whatsapp_choice_decide($post, $prefix, $before, $offered, pg_feedback_whatsapp_value_names());
    $choice = pg_feedback_whatsapp_normalize($decided['row']);

    return array('choice' => $choice, 'errors' => array_values($decided['marks']));
}

/**
 * The template part of the "Sent by" card, shown while WhatsApp is the
 * channel: the template list and, per offered template, a list per
 * variable. Only the chosen template's lists show; feedback_survey.js shows
 * another's on change.
 *
 * @param array $choice  pg_feedback_whatsapp_normalize()
 * @param array $offered pg_whatsapp_message_offered()
 * @param bool  $ready   pg_whatsapp_ready()
 * @param bool  $shown   whether WhatsApp is the chosen channel
 * @return string
 */
function pg_feedback_whatsapp_card_html(array $choice, array $offered, $ready, $shown)
{
    $prefix = PG_FEEDBACK_WA_PREFIX;
    $choices = pg_feedback_whatsapp_value_names();
    $options = '<option value="">' . h(lang('Choose a template')) . '</option>';

    // A template chosen before and no longer offered stays listed, named as such.
    if (($choice['template'] !== '') && !isset($offered[$choice['template']])) {
        $options .= '<option value="' . h($choice['template']) . '" selected>' . h(str_replace('|', ' · ', $choice['template']) . ' (' . lang('not approved') . ')') . '</option>';
    }

    $blocks = '';

    foreach ($offered as $key => $template) {
        $category = function_exists('pg_whatsapp_template_category_label') ? pg_whatsapp_template_category_label($template['category']) : $template['category'];
        $options .= '<option value="' . h($key) . '"' . (($key === $choice['template']) ? ' selected' : '') . '>' . h($template['name'] . ' · ' . $template['language'] . ' · ' . $category) . '</option>';
        $hidden = ($key === $choice['template']) ? '' : ' hidden';
        $marks = ' data-fs-wa-vars="' . h($key) . '"';

        foreach ($template['slots'] as $position => $slot) {
            $name = pg_whatsapp_choice_var_name($prefix, $key, $position);
            $chosen = (($key === $choice['template']) && isset($choice['vars'][$slot['key']])) ? $choice['vars'][$slot['key']] : '';
            $list = '<option value="">' . h(lang('Choose a value')) . '</option>';

            foreach ($choices as $value => $label) {
                $list .= '<option value="' . h($value) . '"' . (($value === $chosen) ? ' selected' : '') . '>' . h($label) . '</option>';
            }

            $blocks .= '<div class="col-12 md:col-6"' . $marks . $hidden . '><label class="pg-field-label" for="' . h($name) . '">' . pg_whatsapp_message_slot_chip($slot, $template['labels'] ?? array()) . '</label>'
                . '<select class="pg-input" id="' . h($name) . '" name="' . h($name) . '">' . $list . '</select></div>';
        }

        if ($template['slots'] === array()) {
            $blocks .= '<div class="col-12 pg-field-help"' . $marks . $hidden . '>' . h(lang('This template has no variables.')) . '</div>';
        }
    }

    $notes = '';

    if (!$ready) {
        $notes .= '<p class="pg-text-2 mb-0">' . h(lang('WhatsApp is not set up: the surveys wait until the WhatsApp account is saved and switched on in the Communication settings.')) . '</p>';
    }

    if (!$offered) {
        $notes .= '<p class="pg-text-2 mb-0">' . h(lang('No approved template can be chosen yet. Templates are read from Meta every six hours and after the WhatsApp card is saved; one with an image, video or document header or a copy-code button cannot be used here.')) . '</p>';
    }

    return '<div class="row gy-5 mt-0" data-fs-wa' . ($shown ? '' : ' hidden') . '>'
        . '<div class="col-12 md:col-6"><label class="pg-field-label" for="' . $prefix . '_template">' . h(lang('WhatsApp template')) . '</label>'
        . '<select class="pg-input" id="' . $prefix . '_template" name="' . $prefix . '_template" data-fs-wa-template aria-describedby="' . $prefix . '_help">' . $options . '</select>'
        . '<div class="pg-field-help" id="' . $prefix . '_help">' . h(lang('Choose a utility template with the answer link in its text or in a button. Until one is chosen, the surveys wait.')) . '</div></div>'
        . $blocks . ($notes !== '' ? '<div class="col-12">' . $notes . '</div>' : '') . '</div>';
}

/**
 * The ids of the switched-on WhatsApp surveys whose message can be sent
 * now; none while WhatsApp is not set up.
 *
 * @return int[]
 */
function pg_feedback_whatsapp_ready_surveys()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');

    if (!pg_whatsapp_ready()) {
        return array();
    }

    $offered = pg_whatsapp_message_templates();
    $ids = array();

    foreach ((array) db_items("SELECT id, texts FROM feedback_surveys WHERE (channel = 'whatsapp') AND (enabled = 1) AND (archived_at = 0)") as $survey) {
        if (pg_feedback_whatsapp_choice_ready(pg_feedback_whatsapp_from_texts($survey['texts']), $offered)) {
            $ids[] = (int) $survey['id'];
        }
    }

    return $ids;
}

/**
 * Queues one survey's WhatsApp message.
 *
 * @param array  $row      a feedback_responses row
 * @param array  $survey   pg_feedback_survey_decode()
 * @param string $store
 * @param string $question the question, "Reminder: " before a reminder's
 * @param string $page_link
 * @param bool   $reminder
 * @return bool whether it was queued
 */
function pg_feedback_whatsapp_send(array $row, array $survey, $store, $question, $page_link, $reminder)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');

    if (!pg_whatsapp_ready()) {
        return false;
    }

    $choice = $survey['whatsapp'] ?? pg_feedback_whatsapp_normalize(null);
    list($name, $language) = array_pad(explode('|', $choice['template'], 2), 2, '');
    $template = ($name !== '') ? pg_whatsapp_template_find($name, $language) : null;
    $filled = pg_whatsapp_message_components($template, $choice['vars'], pg_feedback_whatsapp_values($store, $question, $page_link));

    if (!$filled['ok']) {
        if (function_exists('log_activity')) {
            log_activity('WhatsApp message of survey "' . $survey['name'] . '" was not queued: ' . $filled['error']);
        }

        return false;
    }

    $queued = pg_whatsapp_enqueue((string) $row['recipient'], array(
        'id'         => (int) $template['id'],
        'name'       => $template['name'],
        'language'   => $template['language'],
        'category'   => $template['category'],
        'components' => $filled['components'],
    ), array(
        'kind'       => 'feedback',
        'reference'  => 'feedback:' . (int) $row['id'] . ($reminder ? ':r' : ''),
        'contact_id' => (int) ($row['contact_id'] ?? 0),
        'expires_at' => time() + 86400,
    ));

    return !empty($queued['ok']);
}

/**
 * The channel condition of the pass's due rows (pure): e-mail and SMS
 * always, WhatsApp only for the surveys whose message can be sent.
 *
 * @param int[] $whatsapp_ids pg_feedback_whatsapp_ready_surveys()
 * @return string SQL
 */
function pg_feedback_due_channel_sql(array $whatsapp_ids)
{
    $sql = "channel IN ('email', 'sms')";
    $ids = array_values(array_unique(array_filter(array_map('intval', $whatsapp_ids))));

    if ($ids) {
        $sql = "(" . $sql . " OR ((channel = 'whatsapp') AND (survey_id IN (" . implode(', ', $ids) . "))))";
    }

    return $sql;
}
