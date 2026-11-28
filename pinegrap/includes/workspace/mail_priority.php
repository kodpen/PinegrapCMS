<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: a support request's priority and where it came from, both on
 * its mail conversation (ws_mail_conversations.priority, source, source_id).
 * The priority is Low, Normal, High or Urgent, set in the Incoming e-mail
 * screen's reading pane and filtered by on its list; a support box may give
 * Low, High and Urgent their own first-answer hours beside "First answer
 * within (hours)", which stays Normal's. The source names the door a
 * conversation came in by: a mail, a WhatsApp or SMS message, a site form
 * (support_forms.php), the panel's chat window (chat_requests.php) or My
 * Account (support_requests.php); the list shows the last three as a label
 * that leads back to the form record or the chat.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The priority a conversation stored before the column, or without one, has. */
define('WS_MAIL_PRIORITY_NORMAL', 2);

/**
 * The priorities and their words, lowest first. Pure but for the words.
 *
 * @return array 1..4 => label
 */
function ws_mail_priorities()
{
    return array(1 => lang('Low'), 2 => lang('Normal'), 3 => lang('High'), 4 => lang('Urgent'));
}

/**
 * Whether the priority and source columns are there (the open version's
 * step 11.0.288 ran). Files land before the upgrade does, so nothing reads
 * or writes them until then.
 *
 * @return bool
 */
function ws_mail_priority_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_mail_conversations', 'priority')
            && pg_schema_has('ws_mail_conversations', 'source');
    }

    return $ready;
}

/**
 * A conversation's priority; a row without one, or with an unknown value,
 * is Normal. Pure.
 *
 * @param array $conversation ws_mail_conversations row
 * @return int 1..4
 */
function ws_mail_priority_of($conversation)
{
    $priority = (int) ($conversation['priority'] ?? WS_MAIL_PRIORITY_NORMAL);

    return isset(ws_mail_priorities()[$priority]) ? $priority : WS_MAIL_PRIORITY_NORMAL;
}

/**
 * The list's priority filter as asked in the address: '' for every
 * priority, else 1..4. Pure.
 *
 * @param mixed $value
 * @return int 0 for every priority
 */
function ws_mail_priority_filter($value)
{
    $value = is_scalar($value) ? (string) $value : '';

    return (ctype_digit($value) && isset(ws_mail_priorities()[(int) $value])) ? (int) $value : 0;
}

/**
 * A conversation's priority as a badge beside its state; nothing for
 * Normal, which most are. Pure but for the words.
 *
 * @param array $conversation
 * @return string HTML
 */
function ws_mail_priority_html($conversation)
{
    $priority = ws_mail_priority_of($conversation);

    if ($priority === WS_MAIL_PRIORITY_NORMAL) {
        return '';
    }

    $themes = array(1 => 'theme-secondary', 3 => 'theme-warning', 4 => 'theme-danger');
    $icons = array(1 => 'bi-arrow-down', 3 => 'bi-arrow-up', 4 => 'bi-exclamation-triangle');

    return '<span class="badge ' . $themes[$priority] . ' ms-1"><i class="bi ' . $icons[$priority] . ' me-1" aria-hidden="true"></i>'
        . h(ws_mail_priorities()[$priority]) . '</span>';
}

/**
 * What a priority change writes, for ws_mail_conversation_changes(). Pure.
 *
 * @param array $conversation
 * @param mixed $priority the posted priority ('' for no change)
 * @return array ok, error (priority | ''), fields (column => value)
 */
function ws_mail_priority_change($conversation, $priority)
{
    $value = is_scalar($priority) ? trim((string) $priority) : '';

    if ($value === '') {
        return array('ok' => true, 'error' => '', 'fields' => array());
    }

    if (!ctype_digit($value) || !isset(ws_mail_priorities()[(int) $value])) {
        return array('ok' => false, 'error' => 'priority', 'fields' => array());
    }

    if ((int) $value === ws_mail_priority_of($conversation)) {
        return array('ok' => true, 'error' => '', 'fields' => array());
    }

    return array('ok' => true, 'error' => '', 'fields' => array('priority' => (int) $value));
}

/* ---------------------------------------------------------------------------
   First-answer hours per priority
   --------------------------------------------------------------------------- */

/**
 * A support box's first-answer hours per priority: settings JSON key
 * priority_hours, {"1": h, "3": h, "4": h}. Normal has none of its own: it
 * is "First answer within (hours)". 0 or missing follows Normal's.
 *
 * @param array|null $box  ws_mail_boxes row
 * @param bool       $kept the kept hours, whether or not the box is a support box
 * @return array 1 | 3 | 4 => hours (0 for Normal's)
 */
function ws_mail_priority_hours($box, $kept = false)
{
    $settings = is_array($box) ? json_decode((string) ($box['settings'] ?? ''), true) : null;
    $stored = (is_array($settings) && is_array($settings['priority_hours'] ?? null) && ($kept || ws_mail_box_is_support($box)))
        ? $settings['priority_hours'] : array();
    $out = array();

    foreach (array(1, 3, 4) as $priority) {
        $out[$priority] = max(0, min(WS_MAIL_FIRST_REPLY_MAX, (int) ($stored[$priority] ?? ($stored[(string) $priority] ?? 0))));
    }

    return $out;
}

/**
 * The first-answer target that holds for a conversation: its priority's
 * hours when the box sets them, else the box's own. Pure.
 *
 * @param int   $hours          the box's "First answer within (hours)", 0 for none
 * @param array $priority_hours ws_mail_priority_hours()
 * @param array $conversation
 * @return int hours, 0 for none
 */
function ws_mail_priority_reply_hours($hours, $priority_hours, $conversation)
{
    $own = (int) ($priority_hours[ws_mail_priority_of($conversation)] ?? 0);

    return ($own > 0) ? $own : max(0, (int) $hours);
}

/**
 * The first-answer target of a conversation in a box whose own target is
 * already known: each box's hours per priority are read once a request.
 *
 * @param array $conversation ws_mail_conversations row
 * @param int   $hours        the box's own target (ws_mail_first_reply_hours())
 * @return int
 */
function ws_mail_conversation_reply_hours($conversation, $hours)
{
    static $boxes = null;

    if ($boxes === null) {
        $boxes = array();

        foreach ((array) db_items("SELECT id, settings FROM ws_mail_boxes") as $box) {
            $boxes[(int) $box['id']] = ws_mail_priority_hours($box);
        }
    }

    return ws_mail_priority_reply_hours($hours, $boxes[(int) ($conversation['box_id'] ?? 0)] ?? array(), $conversation);
}

/**
 * The fields of a box's form for the hours per priority, beside "First
 * answer within (hours)".
 *
 * @param string     $prefix
 * @param array|null $box
 * @return string HTML
 */
function ws_mail_priority_hours_fields($prefix, $box)
{
    $hours = ws_mail_priority_hours($box, true);
    $words = ws_mail_priorities();
    $html = '
                <div class="md:col-12">
                    <div class="pg-field-label">' . h(lang('First answer within (hours) by priority')) . '</div>
                    <div class="row g-3">';

    foreach ($hours as $priority => $value) {
        $html .= '
                        <div class="md:col-4">
                            <label class="pg-field-label" for="' . $prefix . 'priority_hours_' . $priority . '">' . h($words[$priority]) . '</label>
                            <input class="pg-input" type="number" name="priority_hours_' . $priority . '" id="' . $prefix . 'priority_hours_' . $priority . '" min="0" max="' . WS_MAIL_FIRST_REPLY_MAX . '" value="' . (($value > 0) ? $value : '') . '" placeholder="' . h(lang('As Normal')) . '">
                        </div>';
    }

    return $html . '
                    </div>
                    <div class="pg-field-help">' . h(lang('Left empty, a priority has the first answer\'s time above, which is Normal\'s.')) . '</div>
                </div>';
}

/**
 * The hours per priority as posted with a box form, checked. Pure.
 *
 * @param array $input priority_hours_1, priority_hours_3, priority_hours_4
 * @return array ok, error, field (the wrong field's name), hours (ws_mail_priority_hours() shape)
 */
function ws_mail_priority_hours_input($input)
{
    $hours = array();

    foreach (array(1, 3, 4) as $priority) {
        $name = 'priority_hours_' . $priority;
        $value = is_scalar($input[$name] ?? '') ? trim((string) ($input[$name] ?? '')) : '';

        if ($value === '') {
            $hours[$priority] = 0;
            continue;
        }

        if (!ctype_digit($value) || ((int) $value > WS_MAIL_FIRST_REPLY_MAX)) {
            return array('ok' => false, 'error' => lang(array('string' => 'The first answer\'s time is a whole number of hours, at most {var:1}.', 'vars' => WS_MAIL_FIRST_REPLY_MAX)),
                'field' => $name, 'hours' => array());
        }

        $hours[$priority] = (int) $value;
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'hours' => $hours);
}

/* ---------------------------------------------------------------------------
   Where a conversation came from
   --------------------------------------------------------------------------- */

/**
 * The doors a conversation comes in by. Pure.
 *
 * @return string[]
 */
function ws_mail_sources()
{
    return array('mail', 'whatsapp', 'sms', 'form', 'chat', 'account');
}

/**
 * Records where a conversation that was just opened came from. Quiet before
 * the upgrade ran, for an unknown source, and for a mail (the default).
 *
 * @param int    $conversation_id
 * @param string $source    ws_mail_sources()
 * @param int    $source_id the forms or chat_conversations row, 0 for none
 * @return bool written
 */
function ws_mail_source_set($conversation_id, $source, $source_id = 0)
{
    $source = (string) $source;

    if (((int) $conversation_id <= 0) || ($source === 'mail') || !in_array($source, ws_mail_sources(), true) || !ws_mail_priority_ready()) {
        return false;
    }

    db("UPDATE ws_mail_conversations SET source = '" . e($source) . "', source_id = '" . max(0, (int) $source_id) . "'
        WHERE id = '" . (int) $conversation_id . "'");

    return true;
}

/**
 * The list row's label of where a conversation came from: Form, Live chat
 * or My Account; a mail, WhatsApp or SMS has none of its own (the transport
 * badge says the last two). In the reading pane a form's label opens its
 * record and a chat's opens it in the panel's chat window
 * (workspace_mail.js); a list row is a link itself, so its label is plain.
 * Pure but for the words.
 *
 * @param array  $conversation ws_mail_conversations row
 * @param string $base         the panel's address, ending in /
 * @param bool   $link         lead back to the form record or the chat
 * @return string HTML
 */
function ws_mail_source_html($conversation, $base = '', $link = false)
{
    $source = (string) ($conversation['source'] ?? 'mail');
    $id = (int) ($conversation['source_id'] ?? 0);
    $words = array('form' => array(lang('Form'), 'bi-ui-checks'), 'chat' => array(lang('Live chat'), 'bi-chat-dots'), 'account' => array(lang('My Account'), 'bi-person'));

    if (!isset($words[$source])) {
        return '';
    }

    $inner = '<i class="bi ' . $words[$source][1] . ' me-1" aria-hidden="true"></i>' . h($words[$source][0]);

    if ($link && ($source === 'form') && ($id > 0)) {
        return '<a class="badge theme-info ms-1" href="' . h($base . 'edit_submitted_form.php?id=' . $id) . '">' . $inner . '</a>';
    }

    if ($link && ($source === 'chat') && ($id > 0)) {
        return '<a class="badge theme-info ms-1" href="#" data-ws-mail-source-chat="' . $id . '">' . $inner . '</a>';
    }

    return '<span class="badge theme-info' . ($link ? ' ms-1' : '') . '">' . $inner . '</span>';
}
