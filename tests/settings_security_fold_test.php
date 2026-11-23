<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Accounts and sign-in as folded rows: each part's line of
 * state, the rows' order and names, no popover or old switch left, every
 * switch's fields drawn whatever its state, the providers as connect rows,
 * Sign-in attempts and Deleting many at once on rows of their own, the
 * allowed list linked, and the names that stay (card ids, field names, the
 * save, the old anchors).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/search.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/security_settings_fold.php');

function settings_security_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The part of security.php from one card's drawing to the next part.
function settings_security_fold_test_card($id)
{
	$source = settings_security_fold_test_source('includes/settings/security.php');
	$start = strpos($source, "\$security_card('" . $id . "'");

	if ($start === false) {
		return '';
	}

	$end = strpos($source, '// ── ', $start);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_settings_security_fold_order_and_names()
{
	pg_assert_same(array('session', 'throttle', 'mfa', 'device', 'signin', 'captcha', 'membership', 'audit', 'deletion', 'encryption'), pg_security_fold_order(), 'the rows in the description\'s order');

	$names = array();

	foreach (pg_security_fold_order() as $key) {
		$face = pg_security_fold_face($key);
		$names[] = $face['name'];
		pg_assert_true(strpos($face['icon'], 'bi-') === 0, $key . ': an icon');
		pg_assert_false(stripos($face['name'], 'Enable') === 0, $key . ': a name never starts with Enable');
	}

	pg_assert_same(array(lang('Passwords and sessions'), lang('Sign-in attempts'), lang('Two-step verification'), lang('Devices'), lang('Sign in with Google, Microsoft and Apple'), lang('Forms and spam'), lang('Registration and membership'), lang('Audit trail'), lang('Deleting many at once'), lang('Encryption key')), $names, 'the rows\' names');
}

function test_settings_security_fold_lines()
{
	pg_assert_same(lang('Strong passwords required') . ' · ' . lang('Remember me offered'), pg_security_fold_session(true, true)['line'], 'strong, remember me');
	pg_assert_same(lang('Any password accepted') . ' · ' . lang('no Remember me'), pg_security_fold_session(false, false)['line'], 'neither');

	$throttle = pg_security_fold_throttle(true, true, 10, 15, 60);
	pg_assert_same(lang(array('string' => '{var:1} failures in {var:2} minutes lock out for {var:3} minutes', 'vars' => array(10, 15, 60))), $throttle['line'], 'the throttle\'s numbers');
	pg_assert_same(array(lang('On'), 'pg-tone-teal'), $throttle['pill'], 'an On pill');
	pg_assert_same(lang('Failed sign-ins are not limited'), pg_security_fold_throttle(true, false, 10, 15, 60)['line'], 'off');
	pg_assert_same(lang('Waits for the database upgrade'), pg_security_fold_throttle(false, true, 10, 15, 60)['line'], 'before the upgrade');

	pg_assert_same(lang('Not required'), pg_security_fold_mfa(true, 99, false)['line'], 'nobody must');
	pg_assert_same(lang('Required for administrators and designers') . ' · ' . lang('passkey sign-in on'), pg_security_fold_mfa(true, 1, true)['line'], 'admins and designers, with passkeys');
	pg_assert_true(pg_security_fold_mfa(false, 0, false)['attention'], 'no key: the row asks for attention');

	pg_assert_same(lang('Any number of devices'), pg_security_fold_device(false, 3, true)['line'], 'no device limit');
	pg_assert_same(lang(array('string' => 'At most {var:1} devices per account', 'vars' => 3)) . ' · ' . lang('new sign-ins refused when full'), pg_security_fold_device(true, 3, true)['line'], 'three, strict');
	pg_assert_same(lang('At most 1 device per account') . ' · ' . lang('the oldest can be signed out'), pg_security_fold_device(true, 0, false)['line'], 'at least one');

	pg_assert_same(lang('No sign-in button shown'), pg_security_fold_signin(array(), array())['line'], 'no button');
	$signin = pg_security_fold_signin(array('Google'), array('Apple'));
	pg_assert_same(lang(array('string' => 'Shown: {var:1}', 'vars' => 'Google')) . ' · ' . lang(array('string' => 'waiting for setup: {var:1}', 'vars' => 'Apple')), $signin['line'], 'one shown, one waiting');
	pg_assert_true($signin['attention'], 'a provider waiting for its setup opens the row');

	pg_assert_same(lang('CAPTCHA on') . ' · ' . lang('StopForumSpam checked') . ' · ' . lang(array('string' => '{var:1} addresses blocked', 'vars' => 2)), pg_security_fold_forms(true, true, 2)['line'], 'all on');
	pg_assert_same(lang('No CAPTCHA') . ' · ' . lang('1 address blocked'), pg_security_fold_forms(false, false, 1)['line'], 'no CAPTCHA, one blocked');

	pg_assert_same(lang(array('string' => 'Expiration warning sent {var:1} day{suffix:1} before', 'vars' => 7, 'suffix' => array('s'))), pg_security_fold_membership(true, 7)['line'], 'warning a week before');
	pg_assert_same(lang('No expiration warning'), pg_security_fold_membership(false, 7)['line'], 'no warning');

	pg_assert_same(lang('Every line kept'), pg_security_fold_audit(0)['line'], 'kept for ever');
	pg_assert_same(lang(array('string' => 'Lines kept {var:1} year{suffix:1}', 'vars' => 2, 'suffix' => array('s'))), pg_security_fold_audit(2)['line'], 'two years');

	pg_assert_same(array(lang('Off'), 'pg-tone-gray'), pg_security_fold_deletion(false)['pill'], 'mass deletion off');
	pg_assert_same(lang('Everything a filter matches can be deleted'), pg_security_fold_deletion(true)['line'], 'mass deletion on');

	pg_assert_true(pg_security_fold_encryption(false, true)['attention'], 'no key: the row asks for attention');
	pg_assert_false(pg_security_fold_encryption(true, true)['attention'], 'a key');
}

function test_settings_security_fold_pane_is_rows()
{
	$pane = settings_security_fold_test_source('includes/settings/security.php');

	pg_assert_contains('$pg_settings_cards[] = pg_settings_fold($security_rows);', $pane, 'the parts are one list of folded rows');
	pg_assert_same(1, substr_count($pane, '$pg_settings_cards[]'), 'the pane is the rows alone');
	pg_assert_same(0, substr_count($pane, 'popover'), 'no popover is left');
	pg_assert_same(0, substr_count($pane, 'collapse-switcher'), 'no switch hides fields');
	pg_assert_same(0, substr_count($pane, 'class="switch"'), 'no old switch drawing');
	pg_assert_false(strpos(settings_security_fold_test_source('includes/settings/prep.php'), 'login_throttle_row') !== false, 'prep.php no longer draws the throttle');

	foreach (array('Session & Password', 'Spam Protection', 'Allow Mass Deletion', 'Require Strong Password', 'Allow Password Hint', 'Allow Remember Me', 'Enable CAPTCHA', 'Enable Google Sign-In', 'Enable Microsoft Sign-In', 'Enable Apple Sign-In', 'Send Expiration Warning E-mail to Members', 'Registration & Membership', 'Two-Step Verification', 'Encryption Key', 'Audit Trail') as $old) {
		pg_assert_false(strpos($pane, "lang('" . $old . "')") !== false, 'no "' . $old . '" label');
	}
}

function test_settings_security_fold_cards_and_fields()
{
	$session = settings_security_fold_test_card('pgset-session');
	pg_assert_false(strpos($session, 'mass_deletion') !== false, 'mass deletion left Passwords and sessions');
	pg_assert_false(strpos($session, 'login_throttle') !== false, 'the throttle left Passwords and sessions');

	$throttle = settings_security_fold_test_card('pgset-throttle');

	foreach (array('login_throttle', 'login_throttle_attempts', 'login_throttle_minutes', 'login_throttle_lockout', 'login_throttle_captcha_after') as $name) {
		pg_assert_true(strpos($throttle, "'" . $name . "'") !== false || strpos($throttle, 'name="' . $name . '"') !== false, $name . ' in Sign-in attempts');
	}

	pg_assert_contains('$security_allowed_list', $throttle, 'the shared-address line links the allowed list');
	pg_assert_contains('href="#settings/firewall/pgset-iplists"', settings_security_fold_test_source('includes/settings/security.php'), 'the allowed list opens Addresses');

	pg_assert_contains("'mass_deletion'", settings_security_fold_test_card('pgset-deletion'), 'mass deletion on its own row');

	$device = settings_security_fold_test_card('pgset-device');

	foreach (array('remember_me_device_limit_enabled', 'remember_me_device_limit', 'remember_me_device_limit_strict') as $name) {
		pg_assert_contains($name, $device, $name . ' drawn in Devices');
	}

	$membership = settings_security_fold_test_card('pgset-membership');

	foreach (array('membership_expiration_warning_email', 'membership_expiration_warning_email_subject', 'membership_expiration_warning_email_page_id', 'membership_expiration_warning_email_days_before_expiration') as $name) {
		pg_assert_contains($name, $membership, $name . ' drawn in Registration and membership');
	}

	$pane = settings_security_fold_test_source('includes/settings/security.php');

	foreach (array('oauth_google_enabled', 'signin_microsoft', 'signin_apple') as $name) {
		pg_assert_contains("\$signin_connect_row('" . $name . "'", $pane, $name . ' is a connect row');
	}

	pg_assert_contains('class="pg-card-inner-row connections-row"', $pane, 'the connect row\'s drawing');
	pg_assert_contains("lang('Set up in Connections')", $pane, 'Set up in Connections');
	pg_assert_contains("lang('Not set up')", $pane, 'a provider not set up says so');
	pg_assert_contains("pg_settings_link('contact', 'pgset-chat-site')", settings_security_fold_test_card('pgset-captcha'), 'the chat\'s own puzzle is linked');
}

function test_settings_security_fold_names_stay()
{
	$pane = settings_security_fold_test_source('includes/settings/security.php');

	foreach (array('strong_password', 'password_hint', 'remember_me', 'forgot_password_link', 'mfa_required_role', 'passkey_signin', 'captcha', 'form_spam_blacklist', 'banned_email_addresses', 'registration_contact_group_id', 'registration_email_address', 'member_id_label', 'membership_contact_group_id', 'membership_email_address', 'audit_log_retention_years', 'mass_deletion', 'ecommerce_reset_encryption_key') as $name) {
		pg_assert_true(strpos($pane, 'name="' . $name . '"') !== false || strpos($pane, "'" . $name . "'") !== false, $name . ' posts under its name');
	}

	pg_assert_false(strpos(settings_security_fold_test_source('includes/settings/security.save.php'), 'pg_settings_fold') !== false, 'the save knows nothing of rows');
}

function test_settings_security_fold_registry_and_anchors()
{
	$source = settings_security_fold_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        'security' => array(");
	$security = substr($source, $start, strpos($source, "\n        ),\n", $start) - $start);

	pg_assert_contains("'pgset-throttle'   => lang('Sign-in attempts')", $security, 'Sign-in attempts is a section');
	pg_assert_contains("'pgset-deletion'   => lang('Deleting many at once')", $security, 'Deleting many at once is a section');

	foreach (array('throttle', 'passkey', 'stopforumspam') as $word) {
		pg_assert_contains($word, $security, 'the search finds ' . $word);
	}

	$anchors = pg_settings_legacy_anchors();
	pg_assert_same(array('security', 'pgset-throttle'), $anchors['login_throttle'], 'the throttle opens Sign-in attempts');
	pg_assert_same(array('security', 'pgset-deletion'), $anchors['mass_deletion'], 'mass deletion opens Deleting many at once');
	pg_assert_same(array('security', 'pgset-device'), $anchors['pgsub-devices'], 'an old devices link');
	pg_assert_same(array('security', 'pgset-signin'), $anchors['pgsub-signin'], 'an old sign-in link');
}
