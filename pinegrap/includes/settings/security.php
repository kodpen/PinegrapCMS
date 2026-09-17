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

// ── Sign in with Google ──
$pg_settings_cards[] = '
    <div id="pgset-signin" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Sign in with Google') . '</span>
            </div>
            <div class="card-body">
                <div class="row gy-3">
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
                                                            <label class="form-label">' . lang('Authorized redirect URI (add this to your Google Cloud OAuth client)') . '</label>
                                                            <input class="form-control" type="text" readonly onclick="this.select()" value="' . h(URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/google_auth.php') . '"/>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
