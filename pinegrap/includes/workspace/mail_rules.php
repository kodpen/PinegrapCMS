<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: a mailbox's incoming mail rules. A rule is one condition (the
 * sender's address, the sender's domain with its subdomains, or words in
 * the subject) and one action (give the conversation to a person, or close
 * it). Rules are kept in the box's settings JSON as `rules`, like its
 * routes, at most WS_MAIL_RULES_MAX a box, each with a short id of its own.
 *
 * ws_mail_ingest() runs them for a mail that opens a conversation in a
 * support box, after the block check and the conversation's write and
 * before the notices and the automatic answer; the first rule that fits
 * wins. A blocked, looping or held mail never gets that far, so it runs
 * none. A closed conversation gets no automatic answer. A person who may no
 * longer answer in the conversation's place is passed over.
 *
 * The list sits on the Mailboxes card beside the blocked senders, behind
 * the Workspace settings right; each change is a Site Log line. WhatsApp
 * and SMS boxes have no rules.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most rules one mailbox keeps. */
define('WS_MAIL_RULES_MAX', 20);

/** The longest words a subject rule looks for, in characters. */
define('WS_MAIL_RULE_WORDS_MAX', 100);

/**
 * One kept rule, cleaned; null when it is not a rule. Pure.
 *
 * @param mixed $rule
 * @return array|null id, field (address | domain | subject), value, action (assign | close), user_id
 */
function ws_mail_rule_clean($rule)
{
    if (!is_array($rule)) {
        return null;
    }

    $id = (string) ($rule['id'] ?? '');
    $field = (string) ($rule['field'] ?? '');
    $action = (string) ($rule['action'] ?? '');
    $user_id = (int) ($rule['user_id'] ?? 0);
    $value = ws_mail_rule_value($field, (string) ($rule['value'] ?? ''));

    if (!preg_match('/^[a-f0-9]{8}$/', $id) || ($value === '') || !in_array($action, array('assign', 'close'), true)) {
        return null;
    }

    if (($action === 'assign') && ($user_id <= 0)) {
        return null;
    }

    return array('id' => $id, 'field' => $field, 'value' => $value, 'action' => $action, 'user_id' => ($action === 'assign') ? $user_id : 0);
}

/**
 * What a condition keeps: an address or a domain as the block list keeps
 * them, words of the subject in small letters with their spaces tidied;
 * '' when it is not one. Pure.
 *
 * @param string $field address | domain | subject
 * @param string $value as typed
 * @return string
 */
function ws_mail_rule_value($field, $value)
{
    $value = trim($value);

    if ($field === 'address') {
        return ws_mail_block_value($value, 'address');
    }

    if ($field === 'domain') {
        // A domain may be typed bare or with its @, as the list shows it.
        $value = ltrim($value, '@');

        return ($value === '') ? '' : ws_mail_block_value('x@' . $value, 'domain');
    }

    if ($field === 'subject') {
        $value = mb_strtolower((string) preg_replace('/\s+/u', ' ', $value));

        return (($value !== '') && (mb_strlen($value) <= WS_MAIL_RULE_WORDS_MAX)) ? $value : '';
    }

    return '';
}

/**
 * A box's rules as kept in its settings, cleaned, in their order.
 *
 * @param array|null $box
 * @return array[] ws_mail_rule_clean() rows
 */
function ws_mail_rules($box)
{
    $settings = ws_mail_box_settings($box);
    $out = array();

    foreach ((array) ($settings['rules'] ?? array()) as $rule) {
        $clean = ws_mail_rule_clean($rule);

        if ($clean && (count($out) < WS_MAIL_RULES_MAX)) {
            $out[] = $clean;
        }
    }

    return $out;
}

/**
 * Checks the Add rule form. Its action is "close" or "assign:<user id>",
 * one choice per person. Pure.
 *
 * @param array $input    box_id, field, value, action
 * @param int[] $team_ids who may be given a conversation
 * @return array ok, error, field (the form field to mark), rule (field, value, action, user_id)
 */
function ws_mail_rule_input($input, $team_ids)
{
    $fail = function ($error, $field) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'rule' => array());
    };

    $input = is_array($input) ? $input : array();
    $field = (string) ($input['field'] ?? '');

    if ((int) ($input['box_id'] ?? 0) <= 0) {
        return $fail(lang('Choose the mailbox the rule is for.'), 'box_id');
    }

    if (!in_array($field, array('address', 'domain', 'subject'), true)) {
        return $fail(lang('Choose what the rule looks at.'), 'field');
    }

    $value = ws_mail_rule_value($field, (string) ($input['value'] ?? ''));

    if ($value === '') {
        $errors = array(
            'address' => lang('Enter an e-mail address.'),
            'domain'  => lang('Enter a domain, such as example.com.'),
            'subject' => lang(array('string' => 'Enter the words to look for, at most {var:1} characters.', 'vars' => WS_MAIL_RULE_WORDS_MAX)),
        );

        return $fail($errors[$field], 'value');
    }

    $action = (string) ($input['action'] ?? '');

    if ($action === 'close') {
        return array('ok' => true, 'error' => '', 'field' => '', 'rule' => array('field' => $field, 'value' => $value, 'action' => 'close', 'user_id' => 0));
    }

    $user_id = (strncmp($action, 'assign:', 7) === 0) ? (int) substr($action, 7) : 0;

    if (($user_id <= 0) || !in_array($user_id, array_map('intval', (array) $team_ids), true)) {
        return $fail(lang('Choose what the rule does.'), 'action');
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'rule' => array('field' => $field, 'value' => $value, 'action' => 'assign', 'user_id' => $user_id));
}

/**
 * The first rule that fits a mail; null when none does. An address is
 * matched whole, a domain with its subdomains, words anywhere in the
 * subject regardless of capitals. Pure.
 *
 * @param array[] $rules      ws_mail_rules()
 * @param string  $from_email the sender
 * @param string  $subject
 * @return array|null
 */
function ws_mail_rule_match($rules, $from_email, $subject)
{
    $address = ws_mail_block_value($from_email, 'address');
    $domains = ($address !== '') ? ws_mail_block_domains($address) : array();
    $subject = mb_strtolower((string) preg_replace('/\s+/u', ' ', (string) $subject));

    foreach ((array) $rules as $rule) {
        $value = (string) ($rule['value'] ?? '');

        if ($value === '') {
            continue;
        }

        $field = (string) ($rule['field'] ?? '');

        if ((($field === 'address') && ($address !== '') && ($value === $address))
            || (($field === 'domain') && in_array($value, $domains, true))
            || (($field === 'subject') && (mb_strpos($subject, $value) !== false))) {
            return $rule;
        }
    }

    return null;
}

/**
 * A rule told in a line: its condition, then its action. Pure.
 *
 * @param array  $rule
 * @param string $person the name of who it gives the conversation to
 * @return string plain text
 */
function ws_mail_rule_text($rule, $person)
{
    $value = (string) $rule['value'];
    $when = array(
        'address' => lang(array('string' => 'When the sender is {var:1}', 'vars' => $value)),
        'domain'  => lang(array('string' => 'When the sender is at {var:1}', 'vars' => '@' . $value)),
        'subject' => lang(array('string' => 'When the subject has "{var:1}"', 'vars' => $value)),
    );
    $then = ((string) $rule['action'] === 'close')
        ? lang('close it')
        : lang(array('string' => 'give it to {var:1}', 'vars' => ($person !== '') ? $person : lang('somebody who left the team')));

    return ($when[(string) $rule['field']] ?? '') . ' → ' . $then;
}

/**
 * The Site Log line of a rule added or deleted. Pure.
 *
 * @param bool   $added
 * @param array  $rule
 * @param string $box_name
 * @param string $person
 * @return string
 */
function ws_mail_rule_log_line($added, $rule, $box_name, $person)
{
    $text = ws_mail_rule_text($rule, $person);

    return $added
        ? lang(array('string' => 'an incoming mail rule was added to the mailbox {var:1}: {var:2}', 'vars' => array($box_name, $text)))
        : lang(array('string' => 'an incoming mail rule was deleted from the mailbox {var:1}: {var:2}', 'vars' => array($box_name, $text)));
}

/**
 * A box that may keep rules: a support e-mail box, not a WhatsApp or SMS
 * one, since only a support box has a status and a holder.
 *
 * @param array|null $box
 * @return bool
 */
function ws_mail_rule_box_ok($box)
{
    return is_array($box) && !ws_mail_box_is_phone($box) && ws_mail_box_is_support($box);
}

/**
 * Writes a box's rules into its settings, read again just before so a save
 * of the box's own form in between keeps its fields.
 *
 * @param int     $box_id
 * @param array[] $rules
 */
function ws_mail_rules_write($box_id, $rules)
{
    $settings = ws_mail_box_settings(ws_mail_box($box_id));
    $settings['rules'] = array_values($rules);

    db("UPDATE ws_mail_boxes SET settings = '" . e(json_encode($settings)) . "', updated_at = '" . time() . "' WHERE id = '" . (int) $box_id . "'");
}

/**
 * Adds a rule from the card's form.
 *
 * @param array  $viewer
 * @param array  $input ws_mail_rule_input()'s
 * @param string $username for the Site Log line
 * @return array ok, error, field
 */
function ws_mail_rule_add($viewer, $input, $username)
{
    if (empty($viewer['settings'])) {
        return array('ok' => false, 'error' => lang('You do not have access to Workspace settings.'), 'field' => '');
    }

    $checked = ws_mail_rule_input($input, ws_team_ids());

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => $checked['error'], 'field' => $checked['field']);
    }

    $box = ws_mail_box((int) $input['box_id']);

    if (!ws_mail_rule_box_ok($box)) {
        return array('ok' => false, 'error' => lang('Rules are kept by a support e-mail box only.'), 'field' => 'box_id');
    }

    $rules = ws_mail_rules($box);

    if (count($rules) >= WS_MAIL_RULES_MAX) {
        return array('ok' => false, 'error' => lang(array('string' => 'A mailbox keeps at most {var:1} rules.', 'vars' => WS_MAIL_RULES_MAX)), 'field' => 'value');
    }

    $rule = $checked['rule'];

    foreach ($rules as $kept) {
        if (($kept['field'] === $rule['field']) && ($kept['value'] === $rule['value'])) {
            return array('ok' => false, 'error' => lang('This mailbox already has a rule for that.'), 'field' => 'value');
        }
    }

    $rule = array('id' => bin2hex(random_bytes(4))) + $rule;
    $rules[] = $rule;

    ws_mail_rules_write((int) $box['id'], $rules);
    log_activity(ws_mail_rule_log_line(true, $rule, (string) $box['name'], ws_person_name($rule['user_id'])), $username);

    return array('ok' => true, 'error' => '', 'field' => '');
}

/**
 * Deletes a rule named by the card's Delete button.
 *
 * @param array  $viewer
 * @param string $value "<box id>:<rule id>"
 * @param string $username
 * @return array ok, error
 */
function ws_mail_rule_delete($viewer, $value, $username)
{
    if (empty($viewer['settings'])) {
        return array('ok' => false, 'error' => lang('You do not have access to Workspace settings.'));
    }

    $parts = explode(':', (string) $value, 2);
    $box = ws_mail_box((int) $parts[0]);
    $rule_id = (string) ($parts[1] ?? '');
    $rules = $box ? ws_mail_rules($box) : array();
    $kept = array();
    $gone = null;

    foreach ($rules as $rule) {
        if ($rule['id'] === $rule_id) {
            $gone = $rule;
        } else {
            $kept[] = $rule;
        }
    }

    if (!$gone) {
        return array('ok' => false, 'error' => lang('That rule could not be found.'));
    }

    ws_mail_rules_write((int) $box['id'], $kept);
    log_activity(ws_mail_rule_log_line(false, $gone, (string) $box['name'], ws_person_name($gone['user_id'])), $username);

    return array('ok' => true, 'error' => '');
}

/**
 * Who a rule may give a conversation to: the team, by name.
 *
 * @return array user id => name
 */
function ws_mail_rule_people()
{
    $out = array();

    foreach (ws_team_members() as $person) {
        $out[(int) $person['id']] = (string) $person['name'];
    }

    return $out;
}

/**
 * Runs a box's rules on a conversation a mail has just opened, and does
 * what the first rule that fits says. Called by ws_mail_ingest() before the
 * notices and the automatic answer.
 *
 * @param array  $box
 * @param int    $conversation_id
 * @param array  $channel    the conversation's place
 * @param int    $message_id its card
 * @param string $from_email
 * @param string $subject
 * @param int    $now
 * @return string what was done: assign | close | '' (nothing)
 */
function ws_mail_rules_run($box, $conversation_id, $channel, $message_id, $from_email, $subject, $now)
{
    if (!ws_mail_rule_box_ok($box)) {
        return '';
    }

    $rule = ws_mail_rule_match(ws_mail_rules($box), $from_email, $subject);

    if (!$rule) {
        return '';
    }

    if ($rule['action'] === 'close') {
        db("UPDATE ws_mail_conversations SET status = 'closed', closed_at = '" . (int) $now . "', updated_at = '" . (int) $now . "'
            WHERE id = '" . (int) $conversation_id . "'");

        return 'close';
    }

    // A person who left the team, or may no longer answer there, is passed
    // over: the conversation stays with nobody, as without the rule.
    $person = ws_change_viewer_for_id((int) $rule['user_id']);

    if (!$person || !in_array((int) $rule['user_id'], ws_team_ids(), true) || !ws_mail_can_answer($person, $channel)) {
        return '';
    }

    db("UPDATE ws_mail_conversations SET assignee_id = '" . (int) $rule['user_id'] . "', updated_at = '" . (int) $now . "'
        WHERE id = '" . (int) $conversation_id . "'");
    ws_notify((int) $rule['user_id'], 'mail_assigned', array('channel_id' => (int) $channel['id'], 'message_id' => (int) $message_id, 'actor_id' => 0));

    return 'assign';
}

/**
 * The rules on the Mailboxes card, under the boxes and beside the blocked
 * senders: every support box's rules in their order, each with Delete, and
 * the form that adds one. The buttons submit the settings pane's form.
 *
 * @param array[] $boxes  the card's boxes (ws_mail_settings_boxes())
 * @param array   $people user id => name, who may be given a conversation
 * @param string  $form   the id of the pane's form; '' draws no buttons
 * @return string HTML
 */
function ws_mail_rules_html($boxes, $people, $form)
{
    $rows = '';
    $choices = '';

    foreach ((array) $boxes as $box) {
        if (!ws_mail_rule_box_ok($box)) {
            continue;
        }

        $choices .= '<option value="' . (int) $box['id'] . '">' . h((string) $box['name']) . '</option>';

        foreach (ws_mail_rules($box) as $rule) {
            $person = (string) ($people[(int) $rule['user_id']] ?? '');
            $icon = ($rule['action'] === 'close') ? 'bi-check2-circle' : 'bi-person-check';

            $rows .= '<div class="pg-list-row"><span class="pg-icon-tile pg-tone-blue" aria-hidden="true"><span class="bi ' . $icon . '"></span></span>'
                . '<div class="pg-list-row-main"><div class="pg-list-row-head"><span class="pg-list-row-title">' . h(ws_mail_rule_text($rule, $person)) . '</span></div>'
                . '<div class="pg-list-row-text">' . h((string) $box['name']) . '</div>'
                . (($form !== '') ? '<div class="pg-list-row-actions"><button type="submit" name="ws_mail_rule_delete" value="' . (int) $box['id'] . ':' . h($rule['id']) . '" form="' . h($form) . '" class="pg-button pg-button-quiet pg-button-sm">'
                    . h(lang('Delete')) . '</button></div>' : '')
                . '</div></div>';
        }
    }

    // No support box: nothing a rule could do, so nothing is drawn.
    if ($choices === '') {
        return '';
    }

    $add = '';

    if ($form !== '') {
        $does = '<option value="close">' . h(lang('Close it')) . '</option>';

        foreach ((array) $people as $id => $name) {
            $does .= '<option value="assign:' . (int) $id . '">' . h(lang(array('string' => 'Give it to {var:1}', 'vars' => (string) $name))) . '</option>';
        }

        $f = ' form="' . h($form) . '"';
        $add = '<div class="ws-mail-rule-add mt-5">'
            . '<div><label class="pg-field-label" for="ws-mail-rule-box">' . h(lang('Mailbox')) . '</label><select class="pg-input" id="ws-mail-rule-box" name="ws_mail_rule[box_id]"' . $f . '>' . $choices . '</select></div>'
            . '<div><label class="pg-field-label" for="ws-mail-rule-field">' . h(lang('When')) . '</label><select class="pg-input" id="ws-mail-rule-field" name="ws_mail_rule[field]"' . $f . '>'
                . '<option value="address">' . h(lang('The sender is')) . '</option>'
                . '<option value="domain">' . h(lang('The sender\'s domain is')) . '</option>'
                . '<option value="subject">' . h(lang('The subject has')) . '</option></select></div>'
            . '<div><label class="pg-field-label" for="ws-mail-rule-value">' . h(lang('Address, domain or words')) . '</label><input type="text" class="pg-input" id="ws-mail-rule-value" name="ws_mail_rule[value]" maxlength="255" autocomplete="off"' . $f . '></div>'
            . '<div><label class="pg-field-label" for="ws-mail-rule-action">' . h(lang('Then')) . '</label><select class="pg-input" id="ws-mail-rule-action" name="ws_mail_rule[action]"' . $f . '>' . $does . '</select></div>'
            . '<div class="ws-mail-rule-add-end"><button type="submit" name="ws_mail_rule_add" value="1"' . $f . ' class="pg-button pg-button-sm">' . h(lang('Add rule')) . '</button></div>'
            . '</div>';
    }

    return '<div class="ws-mail-rules mt-7" id="pgset-ws-mail-rules"><div class="pg-title-section">' . h(lang('Incoming mail rules')) . '</div>'
        . '<p class="pg-card-sub">' . h(lang('A new conversation in a support box is checked against its rules in order; the first that fits gives it to a person or closes it.')) . '</p>'
        . (($rows !== '') ? '<div>' . $rows . '</div>' : '<p class="pg-text-meta">' . h(lang('No rules yet.')) . '</p>')
        . $add . '</div>';
}
