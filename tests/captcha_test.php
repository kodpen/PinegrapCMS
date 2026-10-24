<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the CAPTCHA providers (includes/fn/captcha.php): the provider
 * list, what a siteverify answer means, the widget markup, the posted token,
 * the security policy sources and what a post of the Spam Protection card
 * decides.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

// Every provider names what the widget, the check and the policy need, over
// https only.
function test_captcha_providers_shape()
{
	pg_assert_same(array('turnstile', 'hcaptcha'), array_keys(pg_captcha_providers()));

	foreach (pg_captcha_providers() as $key => $definition) {
		foreach (array('label', 'portal', 'script', 'class', 'field', 'verify') as $name) {
			pg_assert_true(isset($definition[$name]) && is_string($definition[$name]) && ($definition[$name] !== ''), $key . ' ' . $name);
		}

		foreach (array('portal', 'script', 'verify') as $name) {
			pg_assert_same(0, strpos($definition[$name], 'https://'), $key . ' ' . $name . ' over https');
		}

		pg_assert_true(isset($definition['csp']['script-src'], $definition['csp']['frame-src']), $key . ' script and frame sources');
	}

	pg_assert_same(null, pg_captcha_provider_definition('recaptcha'));
	pg_assert_same(null, pg_captcha_provider_definition(''));
	pg_assert_same('captcha_turnstile', pg_captcha_app_key('turnstile'));
}

// A good token passes; a refused token asks the visitor again.
function test_captcha_verify_outcome_pass_and_retry()
{
	pg_assert_same('pass', pg_captcha_verify_outcome(200, '{"success":true,"error-codes":[]}'));
	pg_assert_same('pass', pg_captcha_verify_outcome(200, '{"success":true}'), 'hCaptcha leaves the codes out');

	foreach (array('missing-input-response', 'invalid-input-response', 'timeout-or-duplicate', 'expired-input-response', 'already-seen-response', 'not-using-dummy-passcode') as $code) {
		pg_assert_same('retry', pg_captcha_verify_outcome(200, '{"success":false,"error-codes":["' . $code . '"]}'), $code);
	}

	pg_assert_same('retry', pg_captcha_verify_outcome(200, '{"success":false}'), 'refused without a reason');
	pg_assert_same('retry', pg_captcha_verify_outcome(200, '{"success":false,"error-codes":["INVALID-INPUT-RESPONSE"]}'), 'case of a code');
}

// Keys the provider refuses are the site's fault, whatever the token.
function test_captcha_verify_outcome_misconfigured()
{
	foreach (array('missing-input-secret', 'invalid-input-secret', 'sitekey-secret-mismatch', 'invalid-sitekey') as $code) {
		pg_assert_same('misconfigured', pg_captcha_verify_outcome(200, '{"success":false,"error-codes":["' . $code . '"]}'), $code);
	}

	pg_assert_same('misconfigured', pg_captcha_verify_outcome(200, '{"success":false,"error-codes":["invalid-input-response","invalid-input-secret"]}'), 'the keys before the token');
	pg_assert_same('misconfigured', pg_captcha_verify_outcome(400, '{"success":false,"error-codes":["invalid-input-secret"]}'), 'with an error status');
}

// No answer, an unreadable one or an error of the provider: it could not
// judge, and the form fails closed.
function test_captcha_verify_outcome_unavailable()
{
	pg_assert_same('unavailable', pg_captcha_verify_outcome(0, false), 'no answer');
	pg_assert_same('unavailable', pg_captcha_verify_outcome(200, ''), 'empty');
	pg_assert_same('unavailable', pg_captcha_verify_outcome(200, '<html>Bad gateway</html>'), 'not JSON');
	pg_assert_same('unavailable', pg_captcha_verify_outcome(200, '{"error":"x"}'), 'no success field');
	pg_assert_same('unavailable', pg_captcha_verify_outcome(200, '[true]'), 'a list');
	pg_assert_same('unavailable', pg_captcha_verify_outcome(200, '{"success":false,"error-codes":["internal-error"]}'), 'internal error');
	pg_assert_same('unavailable', pg_captcha_verify_outcome(200, '{"success":false,"error-codes":["bad-request"]}'), 'bad request');
	pg_assert_same('unavailable', pg_captcha_verify_outcome(502, '{"success":false}'), 'a gateway error');
	pg_assert_same('unavailable', pg_captcha_verify_outcome(500, '{"success":true}'), 'success under an error status');
	pg_assert_same('unavailable', pg_captcha_verify_outcome(200, '{"success":"true"}'), 'success as a string');
}

// Only well-formed codes reach the log.
function test_captcha_error_codes()
{
	pg_assert_same(array('invalid-input-secret', 'bad-request'), pg_captcha_error_codes('{"success":false,"error-codes":["invalid-input-secret","Bad-Request",["x"],"<script>"]}'));
	pg_assert_same(array(), pg_captcha_error_codes('{"success":false,"error-codes":"invalid-input-secret"}'));
	pg_assert_same(array(), pg_captcha_error_codes(false));
}

// The widget carries the site key escaped; the script comes only when asked.
function test_captcha_widget_html()
{
	pg_assert_same(
		'<div class="cf-turnstile software_captcha_widget" data-sitekey="0x4AAA&quot;&gt;&lt;x"></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>',
		pg_captcha_widget_html('turnstile', '0x4AAA"><x', true));

	pg_assert_same(
		'<div class="h-captcha software_captcha_widget" data-sitekey="10000000-ffff-ffff-ffff-000000000001"></div>',
		pg_captcha_widget_html('hcaptcha', '10000000-ffff-ffff-ffff-000000000001', false));

	pg_assert_same('', pg_captcha_widget_html('recaptcha', 'x', true));
}

// The token is read from the provider's own field, as one string.
function test_captcha_token()
{
	pg_assert_same('abc', pg_captcha_token('turnstile', array('cf-turnstile-response' => ' abc ')));
	pg_assert_same('def', pg_captcha_token('hcaptcha', array('h-captcha-response' => 'def', 'cf-turnstile-response' => 'abc')));
	pg_assert_same('', pg_captcha_token('hcaptcha', array('cf-turnstile-response' => 'abc')), 'the other provider\'s field');
	pg_assert_same('', pg_captcha_token('turnstile', array('cf-turnstile-response' => array('abc'))), 'an array');
	pg_assert_same('', pg_captcha_token('turnstile', array('cf-turnstile-response' => str_repeat('a', 4097))), 'too long');
	pg_assert_same('', pg_captcha_token('', array('cf-turnstile-response' => 'abc')), 'no provider');
}

// The provider's hosts join the policy, beside a payment service's frame
// sources whichever comes first; the built-in question adds nothing.
function test_captcha_csp_sources_merge()
{
	$built_in = "default-src 'self' blob:; script-src 'self' 'unsafe-inline'; style-src 'self'; connect-src 'self'; object-src 'none'";

	pg_assert_same($built_in, waf_csp_add_sources($built_in, pg_captcha_csp_sources('')), 'built-in question');

	pg_assert_same(
		"default-src 'self' blob:; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com; style-src 'self'; connect-src 'self'; object-src 'none'; frame-src 'self' blob: https://challenges.cloudflare.com",
		waf_csp_add_sources($built_in, pg_captcha_csp_sources('turnstile')));

	$payment = array('script-src' => array('https://js.stripe.com'), 'frame-src' => array('https://js.stripe.com'));

	pg_assert_same(
		"default-src 'self' blob:; script-src 'self' 'unsafe-inline' https://js.stripe.com https://challenges.cloudflare.com; style-src 'self'; connect-src 'self'; object-src 'none'; frame-src 'self' blob: https://js.stripe.com https://challenges.cloudflare.com",
		waf_csp_add_sources(waf_csp_add_sources($built_in, $payment), pg_captcha_csp_sources('turnstile')),
		'payment first');

	pg_assert_same(
		"default-src 'self' blob:; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com https://js.stripe.com; style-src 'self'; connect-src 'self'; object-src 'none'; frame-src 'self' blob: https://challenges.cloudflare.com https://js.stripe.com",
		waf_csp_add_sources(waf_csp_add_sources($built_in, pg_captcha_csp_sources('turnstile')), $payment),
		'CAPTCHA first');

	pg_assert_same(
		"default-src 'self' blob:; script-src 'self' 'unsafe-inline' https://hcaptcha.com https://*.hcaptcha.com; style-src 'self' https://hcaptcha.com https://*.hcaptcha.com; connect-src 'self' https://hcaptcha.com https://*.hcaptcha.com; object-src 'none'; frame-src 'self' blob: https://hcaptcha.com https://*.hcaptcha.com",
		waf_csp_add_sources($built_in, pg_captcha_csp_sources('hcaptcha')));

	pg_assert_same('script-src https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com', pg_captcha_csp_text('turnstile'));
}

// A stored provider's keys as the card's decision reads them.
function pg_captcha_test_app($site_key, $secret, $enabled)
{
	return array('site_key' => $site_key, 'secret' => $secret, 'secret_stored' => ($secret !== ''), 'enabled' => $enabled);
}

function pg_captcha_test_post($provider, $fields)
{
	return array_merge(array(
		'captcha_provider'            => $provider,
		'captcha_turnstile_site_key'  => '',
		'captcha_turnstile_secret'    => '',
		'captcha_hcaptcha_site_key'   => '',
		'captcha_hcaptcha_secret'     => '',
	), $fields);
}

// No card, nothing stored, nothing typed: nothing to write or ask.
function test_captcha_settings_decide_nothing()
{
	$empty = array('turnstile' => pg_captcha_test_app('', '', false), 'hcaptcha' => pg_captcha_test_app('', '', false));

	pg_assert_same(null, pg_captcha_settings_decide(array('captcha' => '1'), $empty, ''), 'a post without the card');

	$decision = pg_captcha_settings_decide(pg_captcha_test_post('', array()), $empty, '');

	pg_assert_same('', $decision['provider']);
	pg_assert_same('none', $decision['apps']['turnstile']['action']);
	pg_assert_same('none', $decision['apps']['hcaptcha']['action']);
	pg_assert_same('', $decision['ask']);
	pg_assert_same(array(), $decision['marks']);

	pg_assert_same('', pg_captcha_settings_decide(pg_captcha_test_post('recaptcha', array()), $empty, '')['provider'], 'an unknown provider is the built-in question');
}

// Choosing a provider with new keys saves them, switches it on and asks the
// provider about them.
function test_captcha_settings_decide_choose_new()
{
	$empty = array('turnstile' => pg_captcha_test_app('', '', false), 'hcaptcha' => pg_captcha_test_app('', '', false));

	$decision = pg_captcha_settings_decide(pg_captcha_test_post('turnstile', array(
		'captcha_turnstile_site_key' => ' 0x4AAAAAAAB ',
		'captcha_turnstile_secret'   => '0x4AAAAAAAsecret',
	)), $empty, '');

	pg_assert_same('turnstile', $decision['provider']);
	pg_assert_same(array('action' => 'save', 'site_key' => '0x4AAAAAAAB', 'secret' => '0x4AAAAAAAsecret', 'enabled' => true), $decision['apps']['turnstile']);
	pg_assert_same('none', $decision['apps']['hcaptcha']['action']);
	pg_assert_same('turnstile', $decision['ask']);
	pg_assert_same('0x4AAAAAAAsecret', $decision['secret']);
	pg_assert_same(array(), $decision['marks']);
}

// A chosen provider needs both keys; a stored secret counts, one the site
// cannot read does not.
function test_captcha_settings_decide_required_keys()
{
	$empty = array('turnstile' => pg_captcha_test_app('', '', false), 'hcaptcha' => pg_captcha_test_app('', '', false));

	$decision = pg_captcha_settings_decide(pg_captcha_test_post('hcaptcha', array('captcha_hcaptcha_site_key' => 'abc')), $empty, '');
	pg_assert_true(isset($decision['marks']['captcha_hcaptcha_secret']), 'secret missing');
	pg_assert_same('', $decision['ask'], 'not asked with a mark');

	$decision = pg_captcha_settings_decide(pg_captcha_test_post('hcaptcha', array('captcha_hcaptcha_secret' => '0xsecret')), $empty, '');
	pg_assert_true(isset($decision['marks']['captcha_hcaptcha_site_key']), 'site key missing');

	$stored = array('turnstile' => pg_captcha_test_app('', '', false), 'hcaptcha' => pg_captcha_test_app('abc', '0xstored', false));
	$decision = pg_captcha_settings_decide(pg_captcha_test_post('hcaptcha', array('captcha_hcaptcha_site_key' => 'abc')), $stored, '');
	pg_assert_same(array(), $decision['marks'], 'the stored secret');
	pg_assert_same('hcaptcha', $decision['ask'], 'switching on is checked');
	pg_assert_same('0xstored', $decision['secret']);
	pg_assert_same('', $decision['apps']['hcaptcha']['secret'], 'the stored secret is not written again');

	$unreadable = array('turnstile' => pg_captcha_test_app('', '', false), 'hcaptcha' => array('site_key' => 'abc', 'secret' => '', 'secret_stored' => true, 'enabled' => false));
	$decision = pg_captcha_settings_decide(pg_captcha_test_post('hcaptcha', array('captcha_hcaptcha_site_key' => 'abc')), $unreadable, '');
	pg_assert_true(isset($decision['marks']['captcha_hcaptcha_secret']), 'a secret the site cannot read');

	$decision = pg_captcha_settings_decide(pg_captcha_test_post('turnstile', array('captcha_turnstile_site_key' => 'a b', 'captcha_turnstile_secret' => 'x y')), $empty, '');
	pg_assert_true(isset($decision['marks']['captcha_turnstile_site_key'], $decision['marks']['captcha_turnstile_secret']), 'malformed keys');
}

// Moving to another provider switches the old one off without touching its
// keys; the built-in question with emptied keys forgets them.
function test_captcha_settings_decide_switch_and_forget()
{
	$before = array('turnstile' => pg_captcha_test_app('0xsite', '0xsecret', true), 'hcaptcha' => pg_captcha_test_app('abc', '0xh', false));

	$decision = pg_captcha_settings_decide(pg_captcha_test_post('hcaptcha', array(
		'captcha_turnstile_site_key' => '0xsite',
		'captcha_hcaptcha_site_key'  => 'abc',
	)), $before, '');

	pg_assert_same('disable', $decision['apps']['turnstile']['action']);
	pg_assert_same('save', $decision['apps']['hcaptcha']['action']);
	pg_assert_same(true, $decision['apps']['hcaptcha']['enabled']);
	pg_assert_same('hcaptcha', $decision['ask']);

	$decision = pg_captcha_settings_decide(pg_captcha_test_post('', array('captcha_hcaptcha_site_key' => 'abc')), $before, '');
	pg_assert_same('forget', $decision['apps']['turnstile']['action'], 'emptied');
	pg_assert_same('none', $decision['apps']['hcaptcha']['action'], 'kept as it was');
	pg_assert_same('', $decision['ask']);

	$decision = pg_captcha_settings_decide(pg_captcha_test_post('turnstile', array('captcha_turnstile_site_key' => '0xsite', 'captcha_hcaptcha_site_key' => 'abc')), $before, '');
	pg_assert_same('none', $decision['apps']['turnstile']['action'], 'unchanged is not written');
	pg_assert_same('', $decision['ask'], 'nor asked about');
}

// Without an encryption key nothing new is kept: the chosen provider is
// refused on the card, another one is only switched off.
function test_captcha_settings_decide_refusal()
{
	$before = array('turnstile' => pg_captcha_test_app('', '', false), 'hcaptcha' => pg_captcha_test_app('abc', '', true));

	$decision = pg_captcha_settings_decide(pg_captcha_test_post('turnstile', array(
		'captcha_turnstile_site_key' => '0xsite',
		'captcha_turnstile_secret'   => '0xsecret',
		'captcha_hcaptcha_site_key'  => 'abcd',
	)), $before, 'no key');

	pg_assert_same('no key', $decision['marks']['captcha_provider']);
	pg_assert_same('none', $decision['apps']['turnstile']['action']);
	pg_assert_same('disable', $decision['apps']['hcaptcha']['action']);
	pg_assert_same(1, count($decision['notices']));
	pg_assert_same('', $decision['ask']);
}

// A pane that does not draw a provider's fields leaves its keys alone.
function test_captcha_settings_decide_fields_not_drawn()
{
	$before = array('turnstile' => pg_captcha_test_app('0xsite', '0xsecret', true), 'hcaptcha' => pg_captcha_test_app('', '', false));

	$decision = pg_captcha_settings_decide(array('captcha_provider' => ''), $before, '');

	pg_assert_same('none', $decision['apps']['turnstile']['action']);
	pg_assert_same('none', $decision['apps']['hcaptcha']['action']);
}

// The provider's answer to the settings check: refused keys are marked on the
// secret, a provider this server cannot reach is kept off with its keys saved,
// and a good answer changes nothing.
function test_captcha_settings_answer()
{
	$empty = array('turnstile' => pg_captcha_test_app('', '', false), 'hcaptcha' => pg_captcha_test_app('', '', false));
	$decision = pg_captcha_settings_decide(pg_captcha_test_post('turnstile', array(
		'captcha_turnstile_site_key' => '0xsite',
		'captcha_turnstile_secret'   => '0xsecret',
	)), $empty, '');

	pg_assert_same($decision, pg_captcha_settings_answer($decision, array('outcome' => 'retry', 'detail' => 'invalid-input-response')), 'keys accepted');
	pg_assert_same($decision, pg_captcha_settings_answer($decision, array('outcome' => 'pass', 'detail' => 'HTTP 200')), 'passed');

	$refused = pg_captcha_settings_answer($decision, array('outcome' => 'misconfigured', 'detail' => 'invalid-input-secret'));
	pg_assert_true(isset($refused['marks']['captcha_turnstile_secret']), 'refused keys are marked');
	pg_assert_true(strpos($refused['marks']['captcha_turnstile_secret'], 'invalid-input-secret') !== false, 'with the code');

	$unreachable = pg_captcha_settings_answer($decision, array('outcome' => 'unavailable', 'detail' => 'Could not resolve host'));
	pg_assert_same(array(), $unreachable['marks'], 'not a mistake in the keys');
	pg_assert_same('save', $unreachable['apps']['turnstile']['action'], 'the keys are kept');
	pg_assert_same(false, $unreachable['apps']['turnstile']['enabled'], 'but not switched on');
	pg_assert_same('', $unreachable['provider'], 'forms keep the built-in question');
	pg_assert_same(1, count($unreachable['notices']));
}

// Settings without cURL are refused, so no provider is switched on that
// could never be asked.
function test_captcha_settings_refusal_without_curl()
{
	if (function_exists('curl_init')) {
		pg_assert_true(strpos(pg_captcha_settings_refusal(), 'cURL') === false, 'cURL present');
	} else {
		pg_assert_true(strpos(pg_captcha_settings_refusal(), 'cURL') !== false, 'cURL missing');
	}
}

// A liveform stand-in that only keeps errors.
class pg_captcha_test_form
{
	public $errors = array();

	public function mark_error($field, $sentence)
	{
		$this->errors[$field] = $sentence;
	}

	public function check_form_errors()
	{
		return count($this->errors) > 0;
	}
}

// A missing token is sent back without asking the provider; a form that
// already has an error is not checked at all.
function test_captcha_check_without_asking()
{
	$active = array('provider' => 'turnstile', 'site_key' => '0xsite', 'secret' => '0xsecret');
	$post = $_POST;

	$_POST = array();
	$form = new pg_captcha_test_form();
	pg_assert_same('retry', pg_captcha_check($form, $active), 'no token');
	pg_assert_true(isset($form->errors['captcha_submitted_answer']), 'marked on the answer');

	$_POST = array('cf-turnstile-response' => 'made-up');
	$form = new pg_captcha_test_form();
	$form->mark_error('email', 'required');
	pg_assert_same('skipped', pg_captcha_check($form, $active), 'an earlier error');
	pg_assert_same(array('email'), array_keys($form->errors), 'nothing added');

	$_POST = $post;
}
