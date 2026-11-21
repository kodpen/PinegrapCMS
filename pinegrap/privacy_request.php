<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A person's request about their personal data (includes/fn/privacy_request_form.php).
 *
 *   GET            the form: signed in always (from My Account), a visitor
 *                  only while the Privacy centre's "Public request form" is
 *                  on; otherwise 404
 *   POST send      kind, message (a visitor: email, name, the spam check)
 *   GET  r, t, s   the proof link a visitor's request mails: a button
 *   POST proof     the button: the request is proven, its 30 days start
 *
 * A visitor gets the same answer whether the address is known. Sends are
 * counted per address an hour in the firewall's rate store, also with the
 * firewall off.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_requests.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_request_form.php');

// A personal answer: never kept by a cache, never indexed, and the signed
// address is not handed to another site as a referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$pr_post = (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');
$pr_action = $pr_post && is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$pr_user_id = pg_credit_checkout_user();
$pr_form_on = pg_privacy_request_form_on();
$pr_access = pg_privacy_request_form_access($pr_form_on, $pr_user_id, pg_privacy_requests_ready());
$pr_title = lang('Request about your personal data');
$pr_self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/privacy_request.php';

if ($pr_post && pg_post_body_discarded()) {
    output_error(lang('The request was too large.'), 413);
}

// The proof link: only while the public form is on, as the form itself.
if (($pr_action === 'proof') || (!$pr_post && isset($_GET['r']))) {
    if (!$pr_form_on || !pg_privacy_requests_ready()) {
        output_error(lang('This page is not available.'), 404);
    }

    if ($pr_post) {
        validate_token_field();
    }

    $pr_link = pg_privacy_request_proof_parse($pr_post ? $_POST : $_GET);
    $pr_request = ($pr_link !== null) ? pg_privacy_request_proof_row($pr_link['r']) : null;
    $pr_state = 'invalid';

    if (($pr_request !== null) && ($pr_request['source'] === 'form')) {
        $pr_state = pg_privacy_request_proof_state(
            $pr_link,
            pg_privacy_request_proof_token($pr_request['id'], $pr_request['email'], $pr_link['t'], pg_fs_key()),
            time(),
            ((int) $pr_request['received_at'] > 0));
    }

    if ($pr_post && ($pr_state === 'confirm')) {
        $pr_state = pg_privacy_request_prove($pr_request) ? 'proven_now' : 'failed';
    }

    $pr_messages = array(
        'confirm'    => lang('Press the button below to send your request.'),
        'proven_now' => lang('Your request was sent. You will get the answer by e-mail within 30 days.'),
        'proven'     => lang('This request was sent already. You will get the answer by e-mail within 30 days.'),
        'expired'    => lang('This link has expired. Send the request again to get a new one.'),
        'failed'     => lang('Your request could not be sent. Please try again.'),
        'invalid'    => lang('This link cannot be used. Open the whole link from the e-mail, or send the request again.'),
    );

    $pr_content = '<p>' . h($pr_messages[$pr_state]) . '</p>';

    if (($pr_state === 'confirm') || ($pr_state === 'failed')) {
        $pr_content .=
            '<form method="post" action="' . h($pr_self) . '" class="mt-5">'
            . get_token_field()
            . '<input type="hidden" name="action" value="proof">'
            . '<input type="hidden" name="r" value="' . (int) $pr_link['r'] . '">'
            . '<input type="hidden" name="t" value="' . (int) $pr_link['t'] . '">'
            . '<input type="hidden" name="s" value="' . h($pr_link['s']) . '">'
            . '<button type="submit" class="pg-button pg-button-fill w-100">' . h(lang('Send my request')) . '</button>'
            . '</form>';
    }

    print output_header_secure(array('title' => $pr_title))
        . pg_secure_card('bi-shield-check', $pr_title, $pr_content)
        . output_footer_secure();
    exit();
}

if ($pr_access === 'off') {
    output_error(lang('This page is not available.'), 404);
}

$pr_person = ($pr_access === 'account') ? pg_privacy_request_account_person($pr_user_id) : array('user_id' => 0, 'email' => '', 'name' => '');

// The answer after a send, the same whether the address is known.
if (!$pr_post && isset($_GET['sent'])) {
    $pr_content = '<p>' . h(($pr_access === 'account')
        ? lang('Your request was received. You will get the answer by e-mail within 30 days.')
        : lang('Thank you. Check your e-mail: open the link we sent within 48 hours to send your request.')) . '</p>';

    print output_header_secure(array('title' => $pr_title))
        . pg_secure_card('bi-shield-check', $pr_title, $pr_content)
        . output_footer_secure();
    exit();
}

include_once('liveform.class.php');
$pr_form = new liveform('privacy_request');

if ($pr_action === 'send') {
    validate_token_field();
    $pr_form->add_fields_to_session();

    $pr_input = array(
        'kind'    => (string) $pr_form->get_field_value('kind'),
        'email'   => (string) $pr_form->get_field_value('email'),
        'name'    => (string) $pr_form->get_field_value('name'),
        'message' => (string) $pr_form->get_field_value('request_message'),
    );
    $pr_check = pg_privacy_request_form_row($pr_input, $pr_access, $pr_person, time());

    if (isset($pr_check['errors']['kind'])) {
        $pr_form->mark_error('kind', lang('Choose what you ask for.'));
    }

    if (isset($pr_check['errors']['email']) || isset($pr_check['errors']['account'])) {
        $pr_form->mark_error(($pr_access === 'account') ? '' : 'email', ($pr_access === 'account') ? lang('Your account has no e-mail address. Add one to your profile first.') : lang('Enter your e-mail address.'));
    }

    // The spam check, for a visitor only: a signed-in person is known.
    if (($pr_access === 'form') && (CAPTCHA == true)) {
        validate_captcha_answer($pr_form);
    }

    // Counted once the request is known to come from this page and is whole.
    if (!$pr_form->check_form_errors() && pg_privacy_request_throttled($pr_check['row']['email'])) {
        http_response_code(429);
        header('Retry-After: ' . pg_privacy_request_form_limits()['window']);
        $pr_form->mark_error('', lang('Too many requests for this address. Please try again in an hour.'));
    }

    if (!$pr_form->check_form_errors()) {
        $pr_sent = pg_privacy_request_submit($pr_input, $pr_access, $pr_person);

        if ($pr_sent['status'] === 'ok') {
            $pr_form->remove();
            go($pr_self . '?sent=1');
        }

        $pr_form->mark_error('', lang('Your request could not be sent. Please try again.'));
    }
}

// The form.
// The options are filled by the form, so the chosen kind stays after an error.
$pr_options = array(h(lang('Choose…')) => '');

foreach (pg_privacy_request_labels()['kinds'] as $pr_key => $pr_label) {
    $pr_options[h($pr_label)] = $pr_key;
}

$pr_form->set('kind', 'options', $pr_options);

$pr_required = '<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span>';
$pr_field = function ($id, $label, $control, $required) use ($pr_required) {
    return '<div class="mb-5"><label class="pg-field-label" for="' . $id . '">' . h($label) . ($required ? $pr_required : '') . '</label>' . $control . '</div>';
};
$pr_fields = '';

if ($pr_access === 'account') {
    $pr_fields .= '<p class="mb-5">' . h(lang(array('string' => 'The answer goes to {var:1}.', 'vars' => array($pr_person['email'])))) . '</p>';
} else {
    $pr_fields .=
        $pr_field('email', lang('E-mail'), '<input type="email" class="pg-input" id="email" name="email" autocomplete="email" spellcheck="false" maxlength="100" required>', true)
        . $pr_field('name', lang('Name'), '<input type="text" class="pg-input" id="name" name="name" autocomplete="name" maxlength="100">', false);
}

$pr_fields .=
    $pr_field('kind', lang('What do you ask for?'), '<select class="pg-input" id="kind" name="kind" required></select>', true)
    . $pr_field('request_message', lang('Your request'), '<textarea class="pg-input" id="request_message" name="request_message" rows="5" maxlength="' . (int) pg_privacy_request_form_limits()['message'] . '"></textarea>', false);

if (($pr_access === 'form') && (CAPTCHA == true)) {
    $pr_captcha = get_captcha_info($pr_form);

    if (is_array($pr_captcha)) {
        $pr_fields .= $pr_captcha['system'];

        if ($pr_captcha['question'] !== '') {
            $pr_fields .= $pr_field('captcha_submitted_answer', $pr_captcha['question'], '<input type="number" class="pg-input" id="captcha_submitted_answer" name="captcha_submitted_answer" min="0" max="18" required>', true);
        }
    }
}

$pr_content = $pr_form->prepare(
    $pr_form->get_messages()
    . '<p class="mb-7">' . h(lang('Ask what personal data we keep about you, or to correct or erase it. We answer within 30 days.')) . '</p>'
    . '<form method="post" action="' . h($pr_self) . '">'
    . get_token_field()
    . '<input type="hidden" name="action" value="send">'
    . $pr_fields
    . '<button type="submit" class="pg-button pg-button-fill w-100">' . h(lang('Send the request')) . '</button>'
    . '</form>');

$pr_form->remove();

print output_header_secure(array('title' => $pr_title))
    . pg_secure_card('bi-shield-check', $pr_title, $pr_content)
    . output_footer_secure();
