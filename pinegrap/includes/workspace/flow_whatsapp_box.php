<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the Send a WhatsApp message box of a flow (step kind
 * whatsapp): one approved template of the site's WhatsApp account, its
 * variables filled with values of the run, to the mobile number of the
 * person of the run's record (recipient record_person, read as the SMS box
 * reads it) or to a typed number (never a value of the run, as no recipient
 * is). WhatsApp takes only approved templates outside the 24 hours after a
 * customer's own message, so the box never sends free text. The template's
 * category is Meta's: a marketing template goes only to a number with SMS
 * marketing consent, which pg_whatsapp_enqueue() asks. The message is queued,
 * never sent here.
 *
 * When there is no number, or the message could not be queued, the box can
 * send an e-mail in its place (fallback): to the record's person's address,
 * or to a typed one; a marketing template's e-mail goes to the record's
 * person only with their e-mail marketing consent and carries the
 * unsubscribe link. A failure WhatsApp reports later (a delivery the
 * webhook marks failed) sends no e-mail: the box has ended by then.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The variables a box fills: one setting each, v1 to v20, in the template's order. */
define('WS_STEP_WHATSAPP_VARS', 20);

/** The longest value of one variable the box keeps, before it is filled. */
define('WS_STEP_WHATSAPP_VAR_MAX', 1000);

/** Hours a queued marketing template may wait for the queue, as a marketing SMS. */
define('WS_STEP_WHATSAPP_MARKETING_HOURS', 6);

/**
 * Loads the WhatsApp layer, which the Workspace does not load on its own:
 * its account, queue and templates (includes/fn/whatsapp.php).
 */
function ws_step_whatsapp_load()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');
}

/**
 * Whether the site can send WhatsApp messages, so the box is offered.
 *
 * @return bool
 */
function ws_step_whatsapp_ready()
{
    ws_step_whatsapp_load();

    return pg_whatsapp_ready();
}

/**
 * Whom the box writes to: the number typed in to, or the person of the
 * run's record.
 *
 * @return string[]
 */
function ws_step_whatsapp_recipients()
{
    return array('typed', 'record_person');
}

/**
 * The settings that hold the template's variables, in the template's order
 * (pg_whatsapp_message_slots()): v1 is the first variable. Pure.
 *
 * @return string[]
 */
function ws_step_whatsapp_var_keys()
{
    $keys = array();

    for ($i = 1; $i <= WS_STEP_WHATSAPP_VARS; $i++) {
        $keys[] = 'v' . $i;
    }

    return $keys;
}

/**
 * The texts of the box that take values: the variables and the fallback
 * e-mail's subject and text. Pure.
 *
 * @return string[]
 */
function ws_step_whatsapp_texts()
{
    return array_merge(ws_step_whatsapp_var_keys(), array('fallback_subject', 'fallback_body'));
}

/**
 * A template as the box names it: its name and language, joined by |. Pure.
 *
 * @param string $name
 * @param string $language
 * @return string
 */
function ws_step_whatsapp_template_key($name, $language)
{
    return (string) $name . '|' . (string) $language;
}

/**
 * The name and language of a template the box names; empty strings when
 * the setting is not one. Pure.
 *
 * @param string $key ws_step_whatsapp_template_key()
 * @return array name, language
 */
function ws_step_whatsapp_template_split($key)
{
    $parts = explode('|', (string) $key);

    if ((count($parts) !== 2) || !preg_match('/^[a-z0-9_]{1,512}$/D', $parts[0]) || !preg_match('/^[a-z]{2,3}(_[A-Za-z0-9]{2,4})?$/D', $parts[1])) {
        return array('name' => '', 'language' => '');
    }

    return array('name' => $parts[0], 'language' => $parts[1]);
}

/**
 * The box's settings: to (a typed number), recipient, template (name|language),
 * v1-v20 (the variables, values allowed), fallback (1: an e-mail when there
 * is no number or the message could not be queued), fallback_to (a typed
 * address, for a typed number), fallback_subject, fallback_body. Unknown
 * words become the first. Pure.
 *
 * @param mixed $config
 * @return array
 */
function ws_step_whatsapp_normalize($config)
{
    $config = is_array($config) ? $config : array();
    $recipient = (string) ($config['recipient'] ?? '');
    $template = ws_step_whatsapp_template_split(is_scalar($config['template'] ?? '') ? (string) ($config['template'] ?? '') : '');
    $fallback_to = $config['fallback_to'] ?? '';

    $out = array(
        'to'        => ws_flow_line($config['to'] ?? '', 200),
        'recipient' => in_array($recipient, ws_step_whatsapp_recipients(), true) ? $recipient : 'typed',
        'template'  => ($template['name'] !== '') ? ws_step_whatsapp_template_key($template['name'], $template['language']) : '',
    );

    foreach (ws_step_whatsapp_var_keys() as $key) {
        $out[$key] = ws_flow_line($config[$key] ?? '', WS_STEP_WHATSAPP_VAR_MAX);
    }

    return $out + array(
        'fallback'         => empty($config['fallback']) ? 0 : 1,
        'fallback_to'      => ws_flow_lines(is_array($fallback_to) ? implode(', ', array_map('ws_flow_text', $fallback_to)) : $fallback_to, 2000),
        'fallback_subject' => ws_flow_line($config['fallback_subject'] ?? '', 250),
        'fallback_body'    => ws_flow_lines($config['fallback_body'] ?? '', 20000),
    );
}

/**
 * What keeps the box from being published, from its settings alone. A typed
 * number is read as the queue reads it. Pure but for lang().
 *
 * @param array  $config ws_step_whatsapp_normalize()
 * @param string $name   the box's name in the sentences
 * @return string[]
 */
function ws_step_whatsapp_problems($config, $name)
{
    $problems = array();

    if ($config['recipient'] === 'typed') {
        $to = trim((string) $config['to']);

        if ($to === '') {
            $problems[] = lang(array('string' => 'The box {var:1} needs a mobile number to write to.', 'vars' => $name));
        } elseif (pg_sms_number($to) === '') {
            $problems[] = lang(array('string' => 'The box {var:1} has a number a WhatsApp message cannot be sent to.', 'vars' => $name));
        }
    }

    if ((string) $config['template'] === '') {
        $problems[] = lang(array('string' => 'The box {var:1} needs the WhatsApp template it sends.', 'vars' => $name));
    }

    if (!empty($config['fallback'])) {
        if (($config['recipient'] === 'typed') && (trim((string) $config['fallback_to']) === '')) {
            $problems[] = lang(array('string' => 'The box {var:1} needs the address of the e-mail sent in the message\'s place.', 'vars' => $name));
        }

        if ((trim((string) $config['fallback_subject']) === '') || (trim((string) $config['fallback_body']) === '')) {
            $problems[] = lang(array('string' => 'The box {var:1} needs the subject and the text of the e-mail sent in the message\'s place.', 'vars' => $name));
        }
    }

    return $problems;
}

/**
 * What keeps the box's template from being sent with its settings: gone,
 * not approved, a variable no text value can fill (a media header, a
 * copy-code button), more variables than the box holds, or a variable the
 * box leaves empty. Pure but for lang().
 *
 * @param array      $config   ws_step_whatsapp_normalize()
 * @param array|null $template pg_whatsapp_template_find()
 * @param string     $name     the box's name in the sentences
 * @return string '' when it may be sent
 */
function ws_step_whatsapp_template_problem($config, $template, $name)
{
    if (!is_array($template)) {
        return lang(array('string' => 'The box {var:1} sends a WhatsApp template the account no longer has.', 'vars' => $name));
    }

    if (strtoupper((string) ($template['status'] ?? '')) !== 'APPROVED') {
        return lang(array('string' => 'The box {var:1} sends a WhatsApp template that is not approved.', 'vars' => $name));
    }

    $read = pg_whatsapp_message_slots($template['components'] ?? array(), $template['offer'] ?? '');

    if ($read['unsupported'] !== '') {
        return lang(array('string' => 'The box {var:1} cannot fill its WhatsApp template: {var:2}', 'vars' => array($name, $read['unsupported'])));
    }

    if (count($read['slots']) > WS_STEP_WHATSAPP_VARS) {
        return lang(array('string' => 'The box {var:1} sends a WhatsApp template with more than {var:2} variables.', 'vars' => array($name, WS_STEP_WHATSAPP_VARS)));
    }

    foreach (array_values($read['slots']) as $i => $slot) {
        if (trim((string) ($config['v' . ($i + 1)] ?? '')) === '') {
            return lang(array('string' => 'The box {var:1} needs a value for variable {var:2} of its WhatsApp template.', 'vars' => array($name, $i + 1)));
        }
    }

    return '';
}

/**
 * The components a send carries: the box's variables, filled, in the
 * template's order. Pure but for lang().
 *
 * @param array $template pg_whatsapp_template_find()
 * @param array $action   the box's settings, their values filled
 * @return array ok, components, error (pg_whatsapp_message_components())
 */
function ws_step_whatsapp_components($template, $action)
{
    $read = pg_whatsapp_message_slots(is_array($template) ? ($template['components'] ?? array()) : array(), is_array($template) ? ($template['offer'] ?? '') : '');
    $vars = array();
    $values = array();

    foreach (array_values($read['slots']) as $i => $slot) {
        $key = 'v' . ($i + 1);
        $vars[$slot['key']] = $key;
        $values[$key] = (string) ($action[$key] ?? '');
    }

    return pg_whatsapp_message_components($template, $vars, $values);
}

/**
 * Checks the box as its owner publishes it and as it runs: its settings,
 * that WhatsApp is set up, and its template.
 *
 * @param array $viewer
 * @param array $config
 * @return array ok, error, paused, action
 */
function ws_step_whatsapp_check($viewer, $config)
{
    $config = ws_step_whatsapp_normalize($config);
    $name = lang('Send a WhatsApp message');
    $problems = ws_step_whatsapp_problems($config, $name);
    $refuse = function ($error) {
        return array('ok' => false, 'error' => (string) $error, 'paused' => false, 'action' => null);
    };

    if (!empty($problems)) {
        return $refuse($problems[0]);
    }

    // With the e-mail in its place, WhatsApp switched off or a template no
    // longer approved does not stop the box: it sends that e-mail
    // (ws_step_whatsapp_do()).
    $ok = array('ok' => true, 'error' => '', 'paused' => false);

    $ready = ws_step_whatsapp_ready();

    if (!empty($config['fallback']) && !$ready) {
        return $ok + array('action' => array('type' => 'whatsapp', 'category' => '') + $config);
    }

    if (!$ready) {
        return $refuse(lang('WhatsApp is not set up on this site.'));
    }

    $key = ws_step_whatsapp_template_split($config['template']);
    $template = pg_whatsapp_template_find($key['name'], $key['language']);
    $problem = ws_step_whatsapp_template_problem($config, $template, $name);

    if (($problem !== '') && empty($config['fallback'])) {
        return $refuse($problem);
    }

    return $ok + array('action' => array('type' => 'whatsapp', 'category' => is_array($template) ? strtoupper((string) $template['category']) : '') + $config);
}

/**
 * The queue's reference of the box's message: one per run, box and pass,
 * so a box that runs again after a stop does not queue it twice. Pure.
 *
 * @param int    $run_id
 * @param string $node_id
 * @param int    $pass
 * @return string
 */
function ws_step_whatsapp_reference($run_id, $node_id, $pass)
{
    $node = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $node_id);

    return substr('flow:' . (int) $run_id . ':' . (int) $pass . ':' . $node, 0, 64);
}

/**
 * The options the box queues its message with: kind flow, its reference,
 * and for a marketing template the hours it may wait, so a promotion is
 * not sent the next morning after a stalled queue. Pure.
 *
 * @param string $category  the template's category
 * @param string $reference ws_step_whatsapp_reference()
 * @param int    $now
 * @return array
 */
function ws_step_whatsapp_queue_options($category, $reference, $now)
{
    $options = array('kind' => 'flow', 'reference' => (string) $reference);

    if (strtoupper((string) $category) === 'MARKETING') {
        $options['expires_at'] = (int) $now + WS_STEP_WHATSAPP_MARKETING_HOURS * 3600;
    }

    return $options;
}

/**
 * Whom the e-mail in the message's place goes to, and how: the record's
 * person's address (marketing only with their consent), or the typed
 * addresses. Pure but for the consent, given.
 *
 * @param array    $action   the box's settings (recipient, fallback_to,
 *                           category); its values filled
 * @param string   $email    the record's person's address, '' for none
 * @param callable $refusal  address => why no marketing e-mail may go, ''
 * @return array to (addresses), marketing, error ('' when it may go)
 */
function ws_step_whatsapp_fallback_to($action, $email, $refusal)
{
    $marketing = (strtoupper((string) ($action['category'] ?? '')) === 'MARKETING');

    if ((string) ($action['recipient'] ?? '') === 'record_person') {
        if ((string) $email === '') {
            return array('to' => array(), 'marketing' => $marketing, 'error' => lang('The person of the record the run is about has no e-mail address either.'));
        }

        $refused = $marketing ? (string) call_user_func($refusal, (string) $email) : '';

        return ($refused !== '')
            ? array('to' => array(), 'marketing' => true, 'error' => $refused)
            : array('to' => array((string) $email), 'marketing' => $marketing, 'error' => '');
    }

    $addresses = ws_scheduled_addresses((string) ($action['fallback_to'] ?? ''));

    if (!$addresses['ok']) {
        return array('to' => array(), 'marketing' => false, 'error' => (string) $addresses['error']);
    }

    // A typed address is the owner's own choice, as the e-mail box's.
    return array('to' => (array) $addresses['to'], 'marketing' => false, 'error' => '');
}

/**
 * Sends the e-mail in the message's place, when the box asks for one.
 *
 * @param array  $action  the box's settings, its values filled
 * @param array  $context what the placeholders read (ws_scheduled_fill())
 * @param string $email   the record's person's address, '' for none
 * @param string $why     why no WhatsApp message went
 * @return array ok, text, data (sent 0, emailed)
 */
function ws_step_whatsapp_fallback($action, $context, $email, $why)
{
    if (empty($action['fallback'])) {
        return array('ok' => true, 'text' => $why, 'data' => array('sent' => 0, 'emailed' => 0));
    }

    $to = ws_step_whatsapp_fallback_to($action, $email, 'ws_flow_mail_marketing_refusal');

    if ($to['error'] !== '') {
        return array('ok' => true, 'text' => $why . ' ' . $to['error'], 'data' => array('sent' => 0, 'emailed' => 0));
    }

    $limits = ws_scheduled_text_limits();
    $subject = ws_scheduled_fill_cut(ws_scheduled_fill((string) $action['fallback_subject'], $context), $limits['email_subject']);
    $body = ws_scheduled_fill_cut(ws_scheduled_fill((string) $action['fallback_body'], $context), $limits['email_body']);
    $source = !empty($context['flow']['id']) ? 'flow:' . (int) $context['flow']['id'] : '';
    $sent = ws_scheduled_mail($to['to'], $subject, $body, 'plain_text', $to['marketing'], $source);

    if ($sent === 0) {
        return array('ok' => false, 'text' => $why . ' ' . lang('The e-mail in its place could not be sent either.'), 'data' => array('sent' => 0, 'emailed' => 0));
    }

    return array('ok' => true, 'text' => $why . ' ' . lang(array('string' => 'An e-mail went in its place to {var:1}.', 'vars' => implode(', ', $to['to']))),
        'data' => array('sent' => 0, 'emailed' => $sent));
}

/**
 * Queues the box's message: the number read, the template's variables
 * filled; the e-mail in its place when it could not be queued.
 *
 * @param array  $action    ws_step_whatsapp_check(), its texts filled
 * @param array  $context   what the placeholders read (ws_scheduled_fill())
 * @param string $reference ws_step_whatsapp_reference()
 * @param array  $person    ws_step_whatsapp_person() for the record's
 *                          person: email, error (why they have no number)
 * @return array ok, text, data (sent, emailed)
 */
function ws_step_whatsapp_do($action, $context, $reference, $person = array())
{
    ws_step_whatsapp_load();
    $email = (string) ($person['email'] ?? '');
    $number = pg_sms_number((string) $action['to']);

    if ($number === '') {
        $why = ((string) ($person['error'] ?? '') !== '') ? (string) $person['error'] : lang('This is not a mobile number a WhatsApp message can be sent to.');

        return ws_step_whatsapp_fallback($action, $context, $email, $why);
    }

    // The scheduled actions' words ({{customer}}, {{date}} …) in each variable.
    foreach (ws_step_whatsapp_var_keys() as $var) {
        if (function_exists('ws_scheduled_fill') && ((string) ($action[$var] ?? '') !== '')) {
            $action[$var] = ws_scheduled_fill((string) $action[$var], $context);
        }
    }

    $key = ws_step_whatsapp_template_split($action['template']);
    $template = pg_whatsapp_template_find($key['name'], $key['language']);

    // The category as Meta has it now decides the e-mail's consent too.
    if (is_array($template)) {
        $action['category'] = strtoupper((string) $template['category']);
    }

    $problem = ws_step_whatsapp_template_problem($action, $template, lang('Send a WhatsApp message'));
    $filled = ($problem === '') ? ws_step_whatsapp_components($template, $action) : array('ok' => false, 'error' => $problem);

    if (!$filled['ok']) {
        return ws_step_whatsapp_fallback($action, $context, $email, lang(array('string' => 'The WhatsApp message was not queued: {var:1}', 'vars' => (string) $filled['error'])));
    }

    $category = $action['category'];
    $queued = pg_whatsapp_enqueue($number, array(
        'id'         => (int) $template['id'],
        'name'       => $key['name'],
        'language'   => $key['language'],
        'category'   => $category,
        'components' => $filled['components'],
    ), ws_step_whatsapp_queue_options($category, $reference, time()));

    if (!$queued['ok']) {
        return ws_step_whatsapp_fallback($action, $context, $email,
            lang(array('string' => 'The WhatsApp message was not queued: {var:1}', 'vars' => (string) $queued['error'])));
    }

    return array('ok' => true, 'text' => lang(array('string' => 'WhatsApp message queued to {var:1}.', 'vars' => ws_step_sms_number_shown($number))),
        'data' => array('sent' => 1, 'emailed' => 0));
}

/**
 * The person of the run's record the box writes to: their mobile number,
 * and, for the e-mail in the message's place, their address.
 *
 * @param array $owner
 * @param array $run
 * @param bool  $fallback whether the box may send an e-mail
 * @return array number (E.164 digits, '' for none), email, error (why
 *               there is no number), email_error
 */
function ws_step_whatsapp_person($owner, $run, $fallback)
{
    $phone = ws_step_sms_person($owner, $run);
    $mail = $fallback ? ws_flow_mail_person($owner, $run) : array('email' => '', 'error' => '');
    $email = ((string) ($mail['error'] ?? '') === '') ? (string) ($mail['email'] ?? '') : '';
    return array('number' => $phone['number'], 'email' => $email, 'error' => $phone['error'], 'email_error' => (string) ($mail['error'] ?? ''));
}

/**
 * What the box does, in words, for a try. Nothing is queued in a try.
 *
 * @param array $action ws_step_whatsapp_check()
 * @return string
 */
function ws_step_whatsapp_text($action)
{
    $key = ws_step_whatsapp_template_split((string) ($action['template'] ?? ''));
    $fallback = !empty($action['fallback']) ? ' ' . lang('Without a number, or when it cannot be queued, an e-mail goes in its place.') : '';

    if ((string) ($action['recipient'] ?? '') === 'record_person') {
        return lang(array('string' => 'Queues the WhatsApp template {var:1} to the person of the record the run is about.', 'vars' => $key['name'])) . $fallback;
    }

    $number = pg_sms_number((string) ($action['to'] ?? ''));
    $to = ($number !== '') ? ws_step_sms_number_shown($number) : (string) ($action['to'] ?? '');

    return lang(array('string' => 'Queues the WhatsApp template {var:1} to {var:2}.', 'vars' => array($key['name'], $to))) . $fallback;
}

/**
 * The templates the canvas offers the box: approved and fillable with text
 * values (pg_whatsapp_message_offered()), at most the variables the box
 * holds, each variable named by where it sits.
 *
 * @return array rows: key, name, language, category, vars (label, part)
 */
function ws_step_whatsapp_offered()
{
    if (!ws_step_whatsapp_ready() || !pg_whatsapp_templates_ready()) {
        return array();
    }

    $rows = db_items("SELECT name, language, category, status, parameter_format, components, samples, offer FROM whatsapp_templates WHERE status = 'APPROVED' ORDER BY name, language");
    $rows = is_array($rows) ? $rows : array();
    $names = array();

    foreach ($rows as $row) {
        $names[ws_step_whatsapp_template_key($row['name'], $row['language'])] = ws_step_whatsapp_sample_labels($row['samples'] ?? '');
    }

    return ws_step_whatsapp_offered_rows(pg_whatsapp_message_offered($rows), $names);
}

/**
 * What the template designer named the message's variables (its samples
 * column, {"body":[{"label","value"}]} in the body's order), by the
 * variable's number; a template read from Meta has none. Pure.
 *
 * @param mixed $samples the column's JSON, or decoded
 * @return array number (string) => label
 */
function ws_step_whatsapp_sample_labels($samples)
{
    $samples = is_string($samples) ? json_decode($samples, true) : $samples;
    $labels = array();

    foreach ((is_array($samples) && isset($samples['body']) && is_array($samples['body'])) ? array_values($samples['body']) : array() as $i => $sample) {
        $label = (is_array($sample) && isset($sample['label']) && is_scalar($sample['label'])) ? trim((string) $sample['label']) : '';

        if ($label !== '') {
            $labels[(string) ($i + 1)] = mb_substr($label, 0, 200, 'UTF-8');
        }
    }

    return $labels;
}

/**
 * The canvas's list from pg_whatsapp_message_offered(): a template with
 * more variables than the box holds is left out; each variable says where
 * it sits (message, header, a button's link), its place, and its chip's
 * name: the designer's name of a message variable, else "Variable N", never
 * Meta's {{n}}. Pure but for lang().
 *
 * @param array $offered pg_whatsapp_message_offered()
 * @param array $names   template key => ws_step_whatsapp_sample_labels()
 * @return array rows: key, name, language, category, vars (n, part, name,
 *               label, chip)
 */
function ws_step_whatsapp_offered_rows($offered, $names = array())
{
    $out = array();

    foreach ((array) $offered as $template) {
        $key = ws_step_whatsapp_template_key($template['name'], $template['language']);

        if (count($template['slots']) > WS_STEP_WHATSAPP_VARS) {
            continue;
        }

        $vars = array();

        foreach (array_values($template['slots']) as $i => $slot) {
            $where = ($slot['part'] === 'header') ? lang('In the header')
                : (($slot['part'] === 'button') ? lang(array('string' => 'In the link of button {var:1}', 'vars' => (int) $slot['index'] + 1)) : lang('In the message'));
            $named = ($slot['part'] === 'body') ? ($names[$key][(string) $slot['name']] ?? '') : '';
            $vars[] = array('n' => $i + 1, 'part' => (string) $slot['part'], 'name' => (string) $slot['name'], 'label' => $where,
                'chip' => ($named !== '') ? $named : lang(array('string' => 'Variable {var:1}', 'vars' => $i + 1)));
        }

        $out[] = array(
            'key'      => $key,
            'name'     => (string) $template['name'],
            'language' => (string) $template['language'],
            'category' => (string) $template['category'],
            'vars'     => $vars,
        );
    }

    return $out;
}
