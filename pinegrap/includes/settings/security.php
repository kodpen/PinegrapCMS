<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Security cards: who gets an account and how they sign in.
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and <key>.save.php writes what they edit. The variables they read
 * are prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// Form submission limits: the counters custom_form.php holds a submission
// against, and the StopForumSpam switch. Not drawn before the 2026.11.0 step
// adds the columns, rather than offering fields that save nowhere.
$output_form_limits = '';

if ($form_limits_ready) {
    $form_limit_inputs = '';

    foreach (array(
        array('form_limit_ip_hour', lang('Per visitor address'), $form_limit_ip_hour, lang('per hour')),
        array('form_limit_recipient_day', lang('Per e-mail address'), $form_limit_recipient_day, lang('per day')),
        array('form_limit_domain_hour', lang('Per e-mail domain'), $form_limit_domain_hour, lang('per hour')),
    ) as $form_limit_input) {
        $form_limit_inputs .= '
                                                        <div class="pg-f-sm">
                                                            <label for="' . $form_limit_input[0] . '" class="form-label">' . h($form_limit_input[1]) . '</label>
                                                            <div class="input-group">
                                                                <input type="text" name="' . $form_limit_input[0] . '" id="' . $form_limit_input[0] . '" class="form-control" value="' . (int) $form_limit_input[2] . '" size="5" maxlength="5" inputmode="numeric" style="text-align: right;"/>
                                                                <span class="input-group-text">' . h($form_limit_input[3]) . '</span>
                                                            </div>
                                                        </div>';
    }

    $output_form_limits = '
                                        <div class="col-12">
                                            <div class="form-label fw-bold mb-1">' . lang('Form Submission Limits') . '</div>
                                            <div class="form-text mt-0">' . lang('How many custom form submissions are accepted from one visitor address, and for one e-mail address or e-mail domain the form mails, subscribes or registers. A form refused by a limit is shown again with what was typed. Staff are not counted. 0 turns a limit off. The e-mail domain limit is off by default: every visitor with an address at that domain shares it, so a few submissions could refuse a whole provider such as gmail.com for an hour.') . '</div>
                                            <div class="row gy-3 mt-0">' . $form_limit_inputs . '
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . ($form_spam_blacklist ? ' checked="checked"' : '') . ' class="form-check-input" type="checkbox" id="form_spam_blacklist" name="form_spam_blacklist"/>
                                                <label class="form-check-label" for="form_spam_blacklist">' . lang('Check visitors against StopForumSpam') . '</label>
                                                <div class="form-text">' . lang('Before a form, comment or registration is accepted, the IP address of the visitor is sent to stopforumspam.com, and a listed address is refused. When the service cannot be reached, the submission is accepted.') . '</div>
                                            </div>
                                        </div>';
}

// ── Session & Password ──
$pg_settings_cards[] = '
    <div id="pgset-session" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Session & Password') . '</span>
                <a href="view_sessions.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-shield-lock me-1"></i>' . lang('Sessions') . '</a>
            </div>
            <div class="card-body">
                <div class="row gy-3">
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $mass_deletion_checked . ' class="form-check-input" type="checkbox" id="mass_deletion" name="mass_deletion"/>
                                                <label class="form-check-label" for="mass_deletion">' . lang('Allow Mass Deletion') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $captcha_checked . ' class="form-check-input" type="checkbox" id="captcha" name="captcha"/>
                                                <label class="form-check-label" for="captcha">' . lang('Enable CAPTCHA') . ' (' . lang('spam protection') . ')</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $strong_password_checked . ' class="form-check-input" type="checkbox" id="strong_password" name="strong_password"/>
                                                <label class="form-check-label" for="strong_password">' . lang('Require Strong Password') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $password_hint_checked . ' class="form-check-input" type="checkbox" id="password_hint" name="password_hint"/>
                                                <label class="form-check-label" for="password_hint">' . lang('Allow Password Hint') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $remember_me_checked . ' class="form-check-input" type="checkbox" id="remember_me" name="remember_me"/>
                                                <label class="form-check-label" for="remember_me">' . lang('Allow Remember Me') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $forgot_password_link_checked . ' class="form-check-input" type="checkbox" id="forgot_password_link" name="forgot_password_link"/>
                                                <label class="form-check-label" for="forgot_password_link">' . lang('Forgot Password Link') . '</label>
                                            </div>
                                        </div>
' . $output_login_throttle . '
' . $output_form_limits . '
                </div>
            </div>
        </div>
    </div>';

// ── Device ──
$pg_settings_cards[] = '
    <div id="pgset-device" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Device') . '</span>
            </div>
            <div class="card-body">
                <div class="row gy-3">
                                        <div class="col-12 ">
                                            <div class="form-check form-switch ">
                                                <input value="1"' . $remember_me_device_limit_enabled_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="remember_me_device_limit_enabled" name="remember_me_device_limit_enabled" data-bs-target="#remember_me_device_limit_row"/>
                                                <label class="form-check-label" for="remember_me_device_limit_enabled">' . lang('Limit devices per account') . '</label>
                                                <div class="form-text">' . lang('Applies only while "Limit devices per account" is on. When a new device would exceed the limit, the visitor is asked to sign out the oldest.') . '</div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="remember_me_device_limit_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row gy-3">
                                                        <div class="pg-f-xs">
                                                            <label for="remember_me_device_limit" class="form-label">' . lang('Max devices per account') . '</label>
                                                            <input type="number" min="1" step="1" name="remember_me_device_limit" id="remember_me_device_limit" class="form-control" value="' . h(max(1, (int) $remember_me_device_limit)) . '"/>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $remember_me_device_limit_strict_checked . ' class="form-check-input" type="checkbox" id="remember_me_device_limit_strict" name="remember_me_device_limit_strict"/>
                                                                <label class="form-check-label" for="remember_me_device_limit_strict">' . lang('Refuse new sign-ins when the limit is full') . '</label>
                                                                <div class="form-text">' . lang('The visitor is not offered "sign out my other devices" - reaching the limit refuses the sign-in instead. That offer is what makes a shared account workable, so locking closes it. A device releases its place when it signs out, or after 12 hours of inactivity; you can also free one from the sessions screen.') . '</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                </div>
            </div>
        </div>
    </div>';

// ── Two-Step Verification ──
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

$pg_settings_cards[] = '
    <div id="pgset-mfa" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Two-Step Verification') . '</span>
            </div>
            <div class="card-body">
                <div class="row gy-3">
                                        <div class="col-12">
                                            <label for="mfa_required_role" class="form-label">' . lang('Required for') . '</label>
                                            <select name="mfa_required_role" id="mfa_required_role" class="form-select pg-f-md"' . ($mfa_available ? '' : ' disabled="disabled"') . '>' . $mfa_role_options . '</select>
                                            <div class="form-text">' . lang('Two-step verification asks for a code from an authenticator app after the password. People in a required role who have not set it up are asked to when they next sign in. Anybody can turn it on from their account page.') . '</div>
                                            ' . ($mfa_available ? '' : '<div class="alert alert-warning mt-2 mb-0" role="alert"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>' . lang('Two-step verification needs an encryption key in config.php; set one from Settings › Commerce (Reset Encryption Key) first.') . '</div>') . '
                                        </div>
                </div>
            </div>
        </div>
    </div>';

// ── Sign in with Google, Microsoft and Apple ──
// One card, a switch per provider. Google's application and switch are the
// config columns it always had, and its return address is still
// google_auth.php. The other switches go to config.signin_providers, their
// applications to connect_apps: Microsoft's is the one of Connected
// accounts (mail and the other features use it too), Apple's is entered
// here because Apple only signs people in. Until the 10.74 upgrade has run
// the card says so and only Google's fields are there.
$signin_google_body = '
                                        <div class="col-12 ">
                                            <div class="form-check form-switch ">
                                                <input value="1"' . $oauth_google_enabled_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="oauth_google_enabled" name="oauth_google_enabled" data-bs-target="#oauth_google_row"/>
                                                <label class="form-check-label" for="oauth_google_enabled">' . lang('Enable Google Sign-In') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="oauth_google_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row gy-3">
                                                        <div class="col-12">
                                                            <label class="form-label" for="oauth_google_client_id">' . lang('Google Client ID') . '</label>
                                                            <input class="form-control" type="text" id="oauth_google_client_id" name="oauth_google_client_id" value="' . h($oauth_google_client_id) . '" autocomplete="off"/>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="oauth_google_client_secret">' . lang('Google Client Secret') . '</label>
                                                            <input class="form-control" type="text" id="oauth_google_client_secret" name="oauth_google_client_secret" value="' . h($oauth_google_client_secret_value) . '" autocomplete="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true"/>
                                                            <div class="form-text">' . lang('Shown as plain text on purpose: a password-type box invites the browser\'s password manager to overwrite it, which silently replaces the key on save.') . '</div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="oauth_google_redirect">' . lang('Authorized redirect URI (add this to your Google Cloud OAuth client)') . '</label>
                                                            <input class="form-control font-monospace" type="text" id="oauth_google_redirect" readonly onclick="this.select()" value="' . h(URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/google_auth.php') . '"/>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';

$signin_redirect_field = function ($id, $label) {
    return '
                                                        <div class="col-12">
                                                            <label class="form-label" for="' . $id . '">' . h($label) . '</label>
                                                            <input class="form-control font-monospace" type="text" id="' . $id . '" readonly onclick="this.select()" value="' . h(pg_signin_redirect_uri()) . '"/>
                                                        </div>';
};
$signin_not_ready = '<div class="alert alert-info mb-0" role="alert">' . h(lang('The database has not been upgraded yet. Run the software update first.')) . '</div>';

// Which buttons visitors see now. pg_signin_buttons() prints them together,
// in this order, on every sign-in, registration and membership screen; a
// provider switched on whose application is incomplete stays hidden, and
// the card says so instead of leaving the operator to wonder.
$signin_state = function_exists('pg_signin_button_state') ? pg_signin_button_state() : array('shown' => array(), 'hidden' => array());
$signin_state_html = '<div class="col-12"><div class="small mb-0">'
    . ($signin_state['shown']
        ? '<i class="bi bi-eye me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Buttons visitors see now, together and in this order on every sign-in, registration and membership screen: {var:1}', 'vars' => implode(', ', $signin_state['shown']))))
        : '<i class="bi bi-eye-slash me-1" aria-hidden="true"></i>' . h(lang('No sign-in button is shown to visitors now.')))
    . '</div>'
    . ($signin_state['hidden']
        ? '<div class="alert alert-warning py-2 small mt-2 mb-0" role="alert"><i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>'
            . h(lang(array('string' => 'Switched on but hidden until their settings are complete: {var:1}', 'vars' => implode(', ', $signin_state['hidden'])))) . '</div>'
        : '')
    . '</div>';

if ($signin_ready) {
    $signin_microsoft_provider = pg_connect_provider('microsoft');
    $signin_microsoft_app_ready = is_array($signin_microsoft_provider) && pg_signin_app_ready($signin_microsoft_provider, $signin_microsoft_app);

    $signin_microsoft_body = '
                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $signin_microsoft_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="signin_microsoft" name="signin_microsoft" data-bs-target="#signin_microsoft_row"/>
                                                <label class="form-check-label" for="signin_microsoft">' . lang('Enable Microsoft Sign-In') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="signin_microsoft_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row gy-3">
                                                        <div class="col-12">
                                                            ' . ($signin_microsoft_app_ready
                                                                ? '<div class="alert alert-success py-2 small mb-2">' . h(lang('Work, school and personal Microsoft accounts sign in through the Microsoft application of Connected accounts.')) . '</div>'
                                                                : '<div class="alert alert-warning py-2 small mb-2">' . h(lang('Set up the Microsoft application on Connected accounts first. Until then the button stays hidden.')) . '</div>') . '
                                                            <a href="connections.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plug me-1" aria-hidden="true"></i>' . h(lang('Connected accounts')) . '</a>
                                                        </div>'
                                                        . $signin_redirect_field('signin_microsoft_redirect', lang('Redirect URI (add this as a further Web redirect URI to the Microsoft application)')) . '
                                                        <div class="col-12">
                                                            <div class="form-text">' . h(lang('A personal account\'s address counts as verified, so it can sign in to an existing account with the same address. A work or school address counts only when the directory confirms it owns the domain: in the Microsoft application, under Token configuration, add the optional claim xms_edov to the ID token. Without it, work and school accounts are refused.')) . '</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';

    $signin_apple_provider = pg_connect_provider('apple');
    $signin_apple_steps = '';

    foreach ((is_array($signin_apple_provider) ? $signin_apple_provider['setup'] : array()) as $signin_apple_step) {
        $signin_apple_steps .= '<li>' . h($signin_apple_step) . '</li>';
    }

    $signin_apple_key_stored = is_array($signin_apple_app) && $signin_apple_app['secret_stored'];
    $signin_apple_option = function ($name) use ($signin_apple_app) {
        return (is_array($signin_apple_app) && isset($signin_apple_app['options'][$name])) ? (string) $signin_apple_app['options'][$name] : '';
    };

    $signin_apple_body = '
                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $signin_apple_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="signin_apple" name="signin_apple" data-bs-target="#signin_apple_row"/>
                                                <label class="form-check-label" for="signin_apple">' . lang('Enable Apple Sign-In') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="signin_apple_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row gy-3">
                                                        ' . (($signin_apple_provider && !pg_signin_app_ready($signin_apple_provider, $signin_apple_app))
                                                            ? '<div class="col-12"><div class="alert alert-warning py-2 small mb-0">' . h(lang('Fill in the Services ID, the Team ID, the Key ID and the private key. Until then the button stays hidden.')) . '</div></div>'
                                                            : '') . '
                                                        <div class="col-12">
                                                            <ol class="small ps-3 mb-0">' . $signin_apple_steps . '</ol>
                                                        </div>'
                                                        . $signin_redirect_field('signin_apple_redirect', lang('Return URL (add this to the Services ID)')) . '
                                                        <div class="col-12">
                                                            <label class="form-label" for="apple_client_id">' . lang('Services ID') . '</label>
                                                            <input class="form-control" type="text" id="apple_client_id" name="apple_client_id" value="' . h(is_array($signin_apple_app) ? $signin_apple_app['client_id'] : '') . '" maxlength="255" autocomplete="off" spellcheck="false" placeholder="com.example.signin"/>
                                                        </div>
                                                        <div class="col-12 col-md-6">
                                                            <label class="form-label" for="apple_team_id">' . lang('Team ID') . '</label>
                                                            <input class="form-control font-monospace" type="text" id="apple_team_id" name="apple_team_id" value="' . h($signin_apple_option('team_id')) . '" maxlength="10" autocomplete="off" spellcheck="false"/>
                                                        </div>
                                                        <div class="col-12 col-md-6">
                                                            <label class="form-label" for="apple_key_id">' . lang('Key ID') . '</label>
                                                            <input class="form-control font-monospace" type="text" id="apple_key_id" name="apple_key_id" value="' . h($signin_apple_option('key_id')) . '" maxlength="10" autocomplete="off" spellcheck="false"/>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="apple_private_key">' . lang('Private key (.p8)') . '</label>
                                                            <textarea class="form-control font-monospace small" id="apple_private_key" name="apple_private_key" rows="4" autocomplete="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" placeholder="' . h($signin_apple_key_stored ? lang('Stored. Leave empty to keep it.') : '-----BEGIN PRIVATE KEY-----') . '"></textarea>
                                                            <div class="form-text">' . h(lang('Kept encrypted with the site\'s key and never shown again.')) . '</div>
                                                        </div>
                                                        <div class="col-12">
                                                            <a class="btn btn-sm btn-outline-secondary" href="' . h(is_array($signin_apple_provider) ? $signin_apple_provider['console_url'] : '') . '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Open the {var:1} console', 'vars' => 'Apple'))) . '</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
} else {
    $signin_microsoft_body = '';
    $signin_apple_body = '';
}

$pg_settings_cards[] = '
    <div id="pgset-signin" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Sign in with Google, Microsoft and Apple') . '</span>
            </div>
            <div class="card-body">
                <div class="row gy-3">
                                        <div class="col-12">
                                            <div class="form-text mt-0">' . h(lang('Every provider follows the same rules: an account is found by the provider account connected to it, else by the same verified e-mail address. The staff login never opens an account, the registration and membership pages do. Two-step verification still applies.')) . '</div>
                                        </div>'
                    . ($signin_ready ? $signin_state_html : '<div class="col-12">' . $signin_not_ready . '</div>')
                    . $signin_google_body . $signin_microsoft_body . $signin_apple_body . '
                </div>
            </div>
        </div>
    </div>';

// ── Registration & Membership ──
$pg_settings_cards[] = '
                        <div id="pgset-membership" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Registration & Membership') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-3">
                                        <div class="pg-f-lg">
                                            <label for="registration_contact_group_id" class="form-label">' . lang('Registration Contact Group') . '</label>
                                            <select class="form-select" id="registration_contact_group_id" name="registration_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Contact Group') ) )) . '-</option>' . select_contact_group($registration_contact_group_id, $user) . '</select>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="registration_email_address" class="form-label">' . lang('Registration E-mail Address') . '</label>
                                            <input type="text" name="registration_email_address" id="registration_email_address" class="form-control" value="' . h($registration_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="member_id_label" class="form-label">' . lang('Member ID Label') . '</label>
                                            <input type="text" name="member_id_label" id="member_id_label" class="form-control" value="' . h($member_id_label) . '"/>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="membership_contact_group_id" class="form-label">' . lang('Membership Contact Group') . '</label>
                                            <select class="form-select" id="membership_contact_group_id" name="membership_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Contact Group') ) )) . '-</option>' . select_contact_group($membership_contact_group_id, $user) . '</select>
                                        </div>
                                        <div class="pg-f-lg">
                                            <label for="membership_email_address" class="form-label">' . lang('Membership E-mail Address') . '</label>
                                            <input type="text" name="membership_email_address" id="membership_email_address" class="form-control" value="' . h($membership_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                                        </div>
                                        <div class="col-12  ">
                                            <div class="form-check form-switch">
                                                <input type="checkbox" name="membership_expiration_warning_email" id="membership_expiration_warning_email" value="1"' . $membership_expiration_warning_email_checked . ' class="form-check-input collapse-switcher" data-bs-target="#membership_expiration_warning_email_row"/>
                                                <label class="form-check-label" for="membership_expiration_warning_email">' . lang('Send Expiration Warning E-mail to Members') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="membership_expiration_warning_email_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(90px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-3">
                                                        <div class="col-12">
                                                            <div class="alert alert-warning">' . lang('Requires scheduled task for membership job') . '</div>
                                                        </div>
                                                        <div class="pg-f-lg">
                                                            <label for="membership_expiration_warning_email_subject" class="form-label">' . lang('Subject') . '</label>
                                                            <input type="text" name="membership_expiration_warning_email_subject" id="membership_expiration_warning_email_subject" class="form-control" value="' . h($membership_expiration_warning_email_subject) . '"/>
                                                            <div class="form-text text-end">' . lang('Member\'s Expiration Date will be appended to Subject') . '</div>
                                                        </div>
                                                        <div class="pg-f-lg">
                                                            <label for="membership_expiration_warning_email_page_id" class="form-label">' . lang('Page') . '</label>
                                                            <select class="form-select" id="membership_expiration_warning_email_page_id" name="membership_expiration_warning_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page') ) )) . '-</option>' . select_page($membership_expiration_warning_email_page_id) . '</select>
                                                        </div>
                                                        <div class="pg-f-xs">
                                                            <label for="membership_expiration_warning_email_days_before_expiration" class="form-label">' . lang('Send') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" name="membership_expiration_warning_email_days_before_expiration" id="membership_expiration_warning_email_days_before_expiration" class="form-control text-end" value="' . h($membership_expiration_warning_email_days_before_expiration) . '" maxlength="4" inputmode="numeric" data-inputmask-alias="decimal"/>
                                                                <span class="input-group-text" title="' . lang('day(s) before expiration date') . '">' . lang('day(s)') . '</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div> 
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
