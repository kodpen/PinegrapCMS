<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Accounts and sign-in cards: passwords and sessions,
 * failed sign-ins, two-step verification, devices, the sign-in providers,
 * forms and spam, registration and membership, the audit trail, deleting
 * many at once and the encryption key.
 *
 * Each part is one folded row (includes/settings/fold.php) holding its card;
 * the rows' order, names and lines are includes/fn/security_settings_fold.php.
 * A switch's fields sit under it, drawn whatever its state. Which cards
 * belong here is said once, in includes/settings/registry.php; this file
 * holds them, and security.save.php writes what they edit. The variables they
 * read are prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/security_settings_fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');

// One switch with its label and help line, as the Firewall and HTTPS rows
// draw it.
$security_switch = function ($field, $name, $checked, $label, $help = '') {
    return '
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . ($checked ? ' checked="checked"' : '') . ' id="' . $field . '" name="' . $name . '"' . (($help !== '') ? ' aria-describedby="' . $field . '_help"' : '') . '/>
                            <label class="pg-text-2" for="' . $field . '">' . $label . '</label>
                        </div>' . (($help !== '') ? '
                        <div class="form-text" id="' . $field . '_help">' . $help . '</div>' : '') . '
                    </div>';
};

// A number box with its unit after it.
$security_number = function ($id, $label, $value, $unit, $size, $maxlength, $help = '', $width = 'pg-f-sm') {
    return '
                    <div class="' . $width . '">
                        <label for="' . $id . '" class="form-label">' . $label . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . $unit . '</span>
                            <input type="text" name="' . $id . '" id="' . $id . '" class="form-ghost text-end" value="' . (int) $value . '" size="' . (int) $size . '" maxlength="' . (int) $maxlength . '" inputmode="numeric"/>
                        </label>' . (($help !== '') ? '
                        <div class="form-text">' . $help . '</div>' : '') . '
                    </div>';
};

// A card inside its row: the id the search index, the anchors and the save
// read, its title, and its fields.
$security_card = function ($id, $title, $body, $gy = 'gy-5') {
    return '
    <div id="' . $id . '" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . $title . '
            </div>
            <div class="card-body">
                <div class="row ' . $gy . '">' . $body . '
                </div>
            </div>
        </div>
    </div>';
};

// The firewall's allowed list, where an office's shared address is let past
// the address counters.
$security_allowed_list = '<a class="pg-link-secondary" href="#settings/firewall/pgset-iplists">' . h(lang('the allowed list')) . '</a>';

// Each part's card and its line of state, by row key; drawn as rows in
// pg_security_fold_order() once every card is built.
$security_parts = array();

// ── Passwords and sessions ──
$security_parts['session'] = array('html' => $security_card('pgset-session', lang('Passwords and sessions'),
    $security_switch('strong_password', 'strong_password', $strong_password_checked !== '', lang('Require a strong password'),
        lang('A new or changed password needs at least ten characters, a capital letter, two numbers and a symbol.'))
    . $security_switch('setting_password_hint', 'password_hint', $password_hint_checked !== '', lang('Password hint'),
        lang('People may store a hint with their password.'))
    . $security_switch('remember_me', 'remember_me', $remember_me_checked !== '', lang('Remember me'),
        lang('The sign-in screens offer to keep the person signed in on that device.'))
    . $security_switch('forgot_password_link', 'forgot_password_link', $forgot_password_link_checked !== '', lang('Forgot password link'),
        lang('The sign-in screens link to the forgotten password screen.'))
    . '
                    <div class="col-12">
                        <a href="view_sessions.php" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-shield-lock" aria-hidden="true"></i>' . lang('Sessions') . '</a>
                    </div>'))
    + pg_security_fold_session($strong_password_checked !== '', $remember_me_checked !== '');

// ── Sign-in attempts ──
// Drawn once the 2026.4.4 columns exist; the question after a few failures
// once the security columns do.
if ($login_throttle_ready) {
    $security_parts['throttle'] = array('html' => $security_card('pgset-throttle', lang('Sign-in attempts'),
        $security_switch('login_throttle', 'login_throttle', $login_throttle == 1, lang('Limit failed sign-ins'),
            lang('Counts failed sign-ins against both the visitor\'s address and the account being tried, and locks that pair out for a while once the limit is passed. Successful sign-ins are never counted.'))
        . '
                    <div class="pg-f-xs">
                        <label for="login_throttle_attempts" class="form-label">' . lang('Failed attempts') . '</label>
                        <input type="text" name="login_throttle_attempts" id="login_throttle_attempts" class="form-control text-end" value="' . (int) $login_throttle_attempts . '" size="5" maxlength="4" inputmode="numeric"/>
                    </div>'
        . $security_number('login_throttle_minutes', lang('Counted over'), $login_throttle_minutes, lang('minutes'), 5, 4)
        . $security_number('login_throttle_lockout', lang('Locked out for'), $login_throttle_lockout, lang('minutes'), 5, 5)
        . ($security_ready
            ? $security_number('login_throttle_captcha_after', lang('Ask a question after'), $login_throttle_captcha_after, lang('failures'), 4, 3,
                lang('From this many failures on, a sign-in attempt must also answer a simple arithmetic question before the password is checked. A person answers it in a second; a password list cannot. 0 turns it off.'), 'pg-f-xs')
            : '') . '
                    <div class="col-12">
                        <div class="form-text mt-0">' . lang(array('string' => 'Offices and schools share one address across many people. If visitors report being locked out, raise the number of attempts or add the address to {var:1}.', 'vars' => $security_allowed_list)) . '</div>
                    </div>'))
        + pg_security_fold_throttle(true, $login_throttle == 1, $login_throttle_attempts, $login_throttle_minutes, $login_throttle_lockout);
}

// ── Two-step verification ──
//
// Without the tables, ENCRYPTION_KEY or openssl the select is disabled: a
// disabled control is not posted, and security.save.php leaves the column as
// it is when the field is missing.
$mfa_role_options = '';

foreach (array(
    99 => lang('Not required (each person may turn it on for their own account)'),
    0  => lang('Administrators'),
    1  => lang('Administrators and designers'),
    2  => lang('Administrators, designers and managers'),
    3  => lang('Everyone who signs in'),
) as $mfa_role_value => $mfa_role_label) {
    $mfa_role_options .= '<option value="' . $mfa_role_value . '"' . (($mfa_required_role === $mfa_role_value) ? ' selected="selected"' : '') . '>' . h($mfa_role_label) . '</option>';
}

$security_parts['mfa'] = array('html' => $security_card('pgset-mfa', lang('Two-step verification'), '
                    <div class="col-12">
                        <label for="mfa_required_role" class="form-label">' . lang('Required for') . '</label>
                        <select name="mfa_required_role" id="mfa_required_role" class="form-control pg-f-md"' . ($mfa_available ? '' : ' disabled="disabled"') . '>' . $mfa_role_options . '</select>
                        <div class="form-text">' . lang('Two-step verification asks for a code from an authenticator app after the password. People in a required role who have not set it up are asked to when they next sign in. Anybody can turn it on from their account page.') . '</div>
                        ' . ($mfa_available ? '' : '<div class="alert theme-warning mt-3 mb-0 py-3 small" role="alert"><i class="bi bi-exclamation-triangle-fill me-3" aria-hidden="true"></i>' . lang('Two-step verification needs an encryption key in config.php. Make one under Encryption key: switch on Reset the encryption key and save.') . ' <a class="pg-link-secondary" href="' . h(pg_settings_link('security', 'pgset-encryption')) . '">' . lang('Encryption key') . '</a></div>') . '
                    </div>'
    . ($passkey_signin_ready
        ? $security_switch('passkey_signin', 'passkey_signin', (bool) $passkey_signin, h(lang('Sign in with a passkey alone')),
            h(lang('The sign-in screens show a "Sign in with a passkey" button. A passkey verified on the device (PIN, fingerprint or face) signs the person in without a password or code. People add passkeys from their account page.'))
            . ($passkeys_available ? '' : '<div class="alert theme-warning mt-3 mb-0 py-3 small" role="alert"><i class="bi bi-exclamation-triangle-fill me-3" aria-hidden="true"></i>' . h(pg_passkey_refusal('unavailable', 'signin')) . '</div>'))
        : '')))
    + pg_security_fold_mfa($mfa_available, $mfa_required_role, $passkey_signin_ready && $passkey_signin);

// ── Devices ──
$security_parts['device'] = array('html' => $security_card('pgset-device', lang('Devices'),
    $security_switch('remember_me_device_limit_enabled', 'remember_me_device_limit_enabled', $remember_me_device_limit_enabled_checked !== '', lang('Limit devices per account'),
        lang('When a new device would exceed the limit, the visitor is asked to sign out the oldest.'))
    . '
                    <div class="pg-f-xs">
                        <label for="remember_me_device_limit" class="form-label">' . lang('Max devices per account') . '</label>
                        <input type="number" min="1" step="1" name="remember_me_device_limit" id="remember_me_device_limit" class="form-control" value="' . h(max(1, (int) $remember_me_device_limit)) . '"/>
                    </div>'
    . $security_switch('remember_me_device_limit_strict', 'remember_me_device_limit_strict', $remember_me_device_limit_strict_checked !== '', lang('Refuse new sign-ins when the limit is full'),
        lang('The visitor is not offered "sign out my other devices" - reaching the limit refuses the sign-in instead. That offer is what makes a shared account workable, so locking closes it. A device releases its place when it signs out, or after 12 hours of inactivity; you can also free one from the sessions screen.'))))
    + pg_security_fold_device($remember_me_device_limit_enabled_checked !== '', $remember_me_device_limit, $remember_me_device_limit_strict_checked !== '');

// ── Sign in with Google, Microsoft and Apple ──
// A connect row per provider: its state, its sentence, its switch and the
// page of Connections where its application is typed. Google's switch is the
// config column it always had; its application (config.oauth_google_*) is on
// Google's page and its return address is still google_auth.php. The other
// switches go to config.signin_providers, their applications to
// connect_apps: Microsoft's is the one on Microsoft's page (mail and the
// other features use it too), Apple's is on Sign in with Apple's page
// because Apple only signs people in. Until the 10.74 upgrade has run the
// row says so and only Google's switch is there.
$signin_connect_row = function ($id, $checked, $icon, $tone, $name, $ready, $sentence, $href, $more = '') {
    return '
                            <div class="pg-card-inner-row connections-row" id="' . $id . '_row">
                                <span class="pg-icon-tile pg-icon-tile-lg pg-tone-' . $tone . '" aria-hidden="true"><i class="bi ' . $icon . '"></i></span>
                                <span class="connections-row-main">
                                    <label class="connections-row-name" for="' . $id . '">' . $name . '</label>
                                    <span class="pg-text-meta d-block">' . h($sentence) . '</span>' . (($more !== '') ? '
                                    <span class="pg-text-meta d-block mt-1">' . h($more) . '</span>' : '') . '
                                </span>
                                <span class="connections-row-side flex-wrap">
                                    <span class="pg-pill ' . ($ready ? 'pg-tone-teal' : 'pg-tone-gray') . '">' . h($ready ? lang('Ready') : lang('Not set up')) . '</span>
                                    <input type="checkbox" class="pg-switch" value="1"' . $checked . ' id="' . $id . '" name="' . $id . '"/>
                                    <a href="' . h($href) . '" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Set up in Connections')) . '</a>
                                </span>
                            </div>';
};

$signin_google_app = function_exists('pg_signin_google_app') ? pg_signin_google_app() : null;
$signin_google_app_ready = is_array($signin_google_app) && ($signin_google_app['client_secret'] !== '');

$signin_rows = $signin_connect_row('oauth_google_enabled', $oauth_google_enabled_checked, 'bi-google', 'blue',
    lang(array('string' => 'Sign in with {var:1}', 'vars' => '<span lang="en">Google</span>')), $signin_google_app_ready,
    $signin_google_app_ready
        ? lang('Visitors and staff sign in through the Sign in with Google application set up on Google\'s page of Connections.')
        : lang('Set up the Sign in with Google application on Google\'s page of Connections first. Until then the button stays hidden.'),
    pg_connections_lead_url('google', 'security', 'pgset-signin', 'connections-signin'));

if ($signin_ready) {
    $signin_microsoft_provider = pg_connect_provider('microsoft');
    $signin_microsoft_app_ready = is_array($signin_microsoft_provider) && pg_signin_app_ready($signin_microsoft_provider, $signin_microsoft_app);

    $signin_rows .= $signin_connect_row('signin_microsoft', $signin_microsoft_checked, 'bi-microsoft', 'blue',
        lang(array('string' => 'Sign in with {var:1}', 'vars' => '<span lang="en">Microsoft</span>')), $signin_microsoft_app_ready,
        $signin_microsoft_app_ready
            ? lang('Work, school and personal Microsoft accounts sign in through the Microsoft application on Microsoft\'s page of Connections.')
            : lang('Set up the Microsoft application on Microsoft\'s page of Connections first. Until then the button stays hidden.'),
        pg_connections_lead_url('microsoft', 'security', 'pgset-signin', 'connections-signin'),
        lang('A personal account\'s address counts as verified, so it can sign in to an existing account with the same address. A work or school address counts only when the directory confirms it owns the domain: in the Microsoft application, under Token configuration, add the optional claim xms_edov to the ID token. Without it, work and school accounts are refused.'));

    $signin_apple_provider = pg_connect_provider('apple');
    $signin_apple_app_ready = is_array($signin_apple_provider) && pg_signin_app_ready($signin_apple_provider, $signin_apple_app);

    $signin_rows .= $signin_connect_row('signin_apple', $signin_apple_checked, 'bi-apple', 'gray',
        lang(array('string' => 'Sign in with {var:1}', 'vars' => '<span lang="en">Apple</span>')), $signin_apple_app_ready,
        $signin_apple_app_ready
            ? lang('Visitors sign in through the Sign in with Apple application set up on its page of Connections.')
            : lang('Set up the Sign in with Apple application on its page of Connections first. Until then the button stays hidden.'),
        pg_connections_lead_url('signin_apple', 'security', 'pgset-signin'));
}

// Which buttons visitors see now. pg_signin_buttons() prints them together,
// in this order, on every sign-in, registration and membership screen; a
// provider switched on whose application is incomplete stays hidden, and
// the row says so instead of leaving the operator to wonder.
$signin_state = function_exists('pg_signin_button_state') ? pg_signin_button_state() : array('shown' => array(), 'hidden' => array());

$security_parts['signin'] = array('html' => $security_card('pgset-signin',
    lang(array('string' => 'Sign in with {var:1}, {var:2} and {var:3}', 'vars' => array('<span lang="en">Google</span>', '<span lang="en">Microsoft</span>', '<span lang="en">Apple</span>'))), '
                    <div class="col-12">
                        <div class="pg-card-inner connections-list">' . $signin_rows . '
                        </div>
                    </div>
                    <div class="col-12">
                        ' . ($signin_ready ? '' : '<div class="alert theme-info mb-3 py-3 small" role="alert">' . h(lang('The database has not been upgraded yet. Run the software update first.')) . '</div>') . '
                        <div class="form-text mt-0">' . h(lang('Every provider follows the same rules: an account is found by the provider account connected to it, else by the same verified e-mail address. The staff login never opens an account, the registration and membership pages do. Two-step verification still applies.')) . '</div>
                    </div>'))
    + pg_security_fold_signin($signin_state['shown'], $signin_state['hidden']);

// ── Forms and spam ──
// The CAPTCHA switch keeps its config column and post name (captcha); the
// provider chosen here switches its connect_apps row on, written after the
// config row by security.save.php. Its keys are typed on the provider's
// page of Connections (includes/fn/captcha.php). The form submission limits
// and the StopForumSpam switch are not drawn before the 2026.11.0 step adds
// their columns, rather than offering fields that save nowhere.
$output_form_limits = '';

if ($form_limits_ready) {
    $output_form_limits = $security_switch('form_spam_blacklist', 'form_spam_blacklist', (bool) $form_spam_blacklist, lang('Check visitors against StopForumSpam'),
        lang('Before a form, comment or registration is accepted, the IP address of the visitor is sent to stopforumspam.com, and a listed address is refused. When the service cannot be reached, the submission is accepted.'))
        . '
                    <div class="col-12">
                        <div class="pg-group-label mb-1">' . lang('Form submission limits') . '</div>
                        <div class="form-text mt-0">' . lang('How many custom form submissions are accepted from one visitor address, and for one e-mail address or e-mail domain the form mails, subscribes or registers. A form refused by a limit is shown again with what was typed. Staff are not counted. 0 turns a limit off. The e-mail domain limit is off by default: every visitor with an address at that domain shares it, so a few submissions could refuse a whole provider such as gmail.com for an hour.') . '</div>
                    </div>'
        . $security_number('form_limit_ip_hour', h(lang('Per visitor address')), $form_limit_ip_hour, h(lang('per hour')), 5, 5)
        . $security_number('form_limit_recipient_day', h(lang('Per e-mail address')), $form_limit_recipient_day, h(lang('per day')), 5, 5)
        . $security_number('form_limit_domain_hour', h(lang('Per e-mail domain')), $form_limit_domain_hour, h(lang('per hour')), 5, 5);
}

$security_blocked_emails = count(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $banned_email_addresses))));

$security_parts['captcha'] = array('html' => $security_card('pgset-captcha', lang('Forms and spam'),
    $security_switch('captcha', 'captcha', $captcha_checked !== '', lang('CAPTCHA'),
        lang('Forms ask a question, or show a provider\'s check, that a script cannot pass.'))
    . (function_exists('pg_captcha_settings_fields') ? pg_captcha_settings_fields() : '')
    . $output_form_limits . '
                    <div class="col-12">
                        <label for="banned_email_addresses" class="form-label"><i class="bi bi-envelope-slash fg-danger me-1"></i>' . lang('Blocked e-mail addresses') . '</label>
                        ' . pg_fields_script() . '
                        <input type="text" value="' . h($banned_email_addresses) . '" name="banned_email_addresses" id="banned_email_addresses" class="form-control tagin min-height-tagin border-subtle-danger" data-pg-chips data-placeholder="' . lang('Block an address') . '"/>
                        <div class="form-text">' . lang('A whole address or a domain. For example: spam@example.com, tempmail.com, @disposable.net. A blocked address cannot register through any form or connect a sign-in provider, and a member with it cannot sign in. Staff accounts are not stopped.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="form-text mt-0">' . h(lang('The chat asks its own puzzle before a visitor\'s first message:')) . ' <a class="pg-link-secondary" href="' . h(pg_settings_link('contact', 'pgset-chat-site')) . '">' . h(lang('Communication')) . ' › ' . h(lang('Chat on the site')) . '</a></div>
                    </div>'))
    + pg_security_fold_forms($captcha_checked !== '', $form_limits_ready && $form_spam_blacklist, $security_blocked_emails);

// ── Registration and membership ──
$security_parts['membership'] = array('html' => $security_card('pgset-membership', lang('Registration and membership'), '
                    <div class="pg-f-lg">
                        <label for="registration_contact_group_id" class="form-label">' . lang('Registration contact group') . '</label>
                        <select class="form-control" id="registration_contact_group_id" name="registration_contact_group_id"><option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Contact Group')))) . '-</option>' . select_contact_group($registration_contact_group_id, $user) . '</select>
                    </div>
                    <div class="pg-f-lg">
                        <label for="registration_email_address" class="form-label">' . lang('Registration e-mail address') . '</label>
                        <input type="text" name="registration_email_address" id="registration_email_address" class="form-control" value="' . h($registration_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                    </div>
                    <div class="pg-f-lg">
                        <label for="member_id_label" class="form-label">' . lang('Member ID label') . '</label>
                        <input type="text" name="member_id_label" id="member_id_label" class="form-control" value="' . h($member_id_label) . '"/>
                    </div>
                    <div class="pg-f-lg">
                        <label for="membership_contact_group_id" class="form-label">' . lang('Membership contact group') . '</label>
                        <select class="form-control" id="membership_contact_group_id" name="membership_contact_group_id"><option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Contact Group')))) . '-</option>' . select_contact_group($membership_contact_group_id, $user) . '</select>
                    </div>
                    <div class="pg-f-lg">
                        <label for="membership_email_address" class="form-label">' . lang('Membership e-mail address') . '</label>
                        <input type="text" name="membership_email_address" id="membership_email_address" class="form-control" value="' . h($membership_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                    </div>'
    . $security_switch('membership_expiration_warning_email', 'membership_expiration_warning_email', $membership_expiration_warning_email_checked !== '', lang('Send an expiration warning to members'),
        lang('Sent by the membership job, so it needs that scheduled task to run.'))
    . '
                    <div class="pg-f-lg">
                        <label for="membership_expiration_warning_email_subject" class="form-label">' . lang('Subject') . '</label>
                        <input type="text" name="membership_expiration_warning_email_subject" id="membership_expiration_warning_email_subject" class="form-control" value="' . h($membership_expiration_warning_email_subject) . '"/>
                        <div class="form-text">' . lang('Member\'s Expiration Date will be appended to Subject') . '</div>
                    </div>
                    <div class="pg-f-lg">
                        <label for="membership_expiration_warning_email_page_id" class="form-label">' . lang('Page') . '</label>
                        ' . pg_picker_field('page', 'membership_expiration_warning_email_page_id', $membership_expiration_warning_email_page_id, array('placeholder' => lang('Choose a page'))) . pg_picker_script() . '
                    </div>
                    <div class="pg-f-xs">
                        <label for="membership_expiration_warning_email_days_before_expiration" class="form-label">' . lang('Days before') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                            <input type="text" name="membership_expiration_warning_email_days_before_expiration" id="membership_expiration_warning_email_days_before_expiration" class="form-ghost text-end" value="' . h($membership_expiration_warning_email_days_before_expiration) . '" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal"/>
                        </label>
                    </div>'))
    + pg_security_fold_membership($membership_expiration_warning_email_checked !== '', $membership_expiration_warning_email_days_before_expiration);

// ── Audit trail ──
// How long the trail keeps its lines; the general job removes older ones
// (includes/fn/privacy_retention.php). Not drawn before the 2026.11.0 step
// adds the column, rather than offering a field that saves nowhere.
if ($audit_retention_ready) {
    $audit_retention_limits = pg_privacy_retention_limits();

    $security_parts['audit'] = array('html' => $security_card('pgset-audit', lang('Audit trail'),
        $security_number('audit_log_retention_years', lang('Keep lines for'), $audit_retention_years, lang('years'), 3, 2,
            h(lang(array('string' => 'Who changed what, with the values before and after. Older lines are removed by the general job once a day. 0 keeps every line; otherwise {var:1} to {var:2} years. The consent log keeps its history for {var:3} years, and always the answer in force.', 'vars' => array($audit_retention_limits['audit_min'], $audit_retention_limits['audit_max'], $audit_retention_limits['consent_years'])))) . ' ' . h(lang(array('string' => 'Erasures and retention rule runs are destruction records: their lines stay at least {var:1} years, whatever is set here.', 'vars' => array($audit_retention_limits['record_years'])))),
            'col-12')
        . '
                    <div class="col-12">
                        <a class="pg-button pg-button-quiet pg-button-sm" href="view_audit_log.php">' . lang('Open it') . '</a>
                    </div>'))
        + pg_security_fold_audit($audit_retention_years);
}

// ── Deleting many at once ──
$security_parts['deletion'] = array('html' => $security_card('pgset-deletion', lang('Deleting many at once'),
    $security_switch('mass_deletion', 'mass_deletion', $mass_deletion_checked !== '', lang('Delete everything a filter matches'),
        lang('Contacts and Submitted Forms offer a button that deletes every item their filters match, and everyone who can open the site log or the performance log may clear it. Off, items are deleted one at a time and only administrators clear the logs.'))))
    + pg_security_fold_deletion($mass_deletion_checked !== '');

// ── Encryption key ──
// The key in config.php that seals stored card numbers, the payment gateway
// secrets and the other stored secrets. The switch keeps its post name;
// security.save.php makes the key and seals again with it what the re-seal
// registry lists. prep.php lists under the switch what is carried over and
// what is lost; the save requires the confirmation box under the second list.
$security_parts['encryption'] = array('html' => $security_card('pgset-encryption', lang('Encryption key'), '
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $ecommerce_reset_encryption_key_disabled . ' id="ecommerce_reset_encryption_key" name="ecommerce_reset_encryption_key" aria-describedby="ecommerce_reset_encryption_key_help"/>
                            <label class="pg-text-2" for="ecommerce_reset_encryption_key">' . lang('Reset the encryption key') . '</label>
                        </div>
                        <div class="form-text" id="ecommerce_reset_encryption_key_help">' . lang('Makes a new key in config.php and seals the stored secrets listed below again with it in the same save. Signed-in devices have to pair again.') . $ecommerce_reset_encryption_key_disabled_message . '</div>' . $encryption_key_reset_losses . '
                    </div>'))
    + pg_security_fold_encryption(defined('ENCRYPTION_KEY') && (ENCRYPTION_KEY !== ''), extension_loaded('openssl'));

// ── The rows ──
$security_rows = array();

foreach (pg_security_fold_order() as $security_key) {
    if (isset($security_parts[$security_key])) {
        $security_rows[] = array('key' => $security_key) + pg_security_fold_face($security_key) + $security_parts[$security_key];
    }
}

$pg_settings_cards[] = pg_settings_fold($security_rows);
