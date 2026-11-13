<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the warning before a Workspace e-mail goes to a domain that does
 * not exist (mail_send.php): which domains are looked up, how often, which
 * answers warn, the near spelling of the mailbox's own domain, the words,
 * and the wiring of Send anyway in the panel, the form and the API. The
 * lookup is given, so no test asks DNS.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_parse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_mail_domain_warning_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_workspace_mail_domain_warning_answers()
{
	$answers = array('kodpen.com' => true, 'kopden.com' => false, 'unknown.test' => null);
	$lookup = function ($domain) use ($answers) {
		return array_key_exists($domain, $answers) ? $answers[$domain] : true;
	};

	pg_assert_same(array(), ws_mail_domain_warning(array('a@kodpen.com'), 'kodpen.com', $lookup), 'a domain that exists');
	pg_assert_same(array(), ws_mail_domain_warning(array('a@unknown.test'), 'kodpen.com', $lookup), 'a lookup that cannot tell warns of nothing');
	pg_assert_same(array(array('domain' => 'kopden.com', 'suggest' => 'kodpen.com')),
		ws_mail_domain_warning(array('tugba@kopden.com'), 'kodpen.com', $lookup), 'two letters swapped: the own domain offered');
	pg_assert_same(array(array('domain' => 'kopden.com', 'suggest' => '')),
		ws_mail_domain_warning(array('tugba@kopden.com'), 'example.org', $lookup), 'far from the own domain: no offer');
	pg_assert_same(array(array('domain' => 'kopden.com', 'suggest' => '')),
		ws_mail_domain_warning(array('tugba@kopden.com'), '', $lookup), 'no own domain');
}

function test_workspace_mail_domain_warning_lookups()
{
	$asked = array();
	$lookup = function ($domain) use (&$asked) {
		$asked[] = $domain;

		return false;
	};

	$found = ws_mail_domain_warning(array('a@one.test', 'b@ONE.test', 'c@two.test', 'd@[192.0.2.1]', 'e@localhost',
		'f@three.test', 'g@four.test', 'h@five.test', 'i@six.test'), 'kodpen.com', $lookup);

	pg_assert_same(array('one.test', 'two.test', 'three.test', 'four.test', 'five.test'), $asked,
		'once a domain, in order, at most five; no literal or dotless host');
	pg_assert_same(5, count($found), 'each one named');
	pg_assert_same(WS_MAIL_DOMAIN_LOOKUPS, 5);
}

function test_workspace_mail_domain_warning_words()
{
	$text = ws_mail_domain_warning_text(array(array('domain' => 'kopden.com', 'suggest' => 'kodpen.com'), array('domain' => 'x.test', 'suggest' => '')));

	pg_assert_contains('kopden.com', $text, 'the domain named');
	pg_assert_contains('kodpen.com', $text, 'the near spelling offered');
	pg_assert_contains('x.test', $text, 'every domain');
	pg_assert_same($text, ws_mail_send_error_text('unknown_domain', $text), 'the panel shows the words as built');

	$strings = ws_mail_send_js_strings();
	pg_assert_true(isset($strings['mail_send_anyway'], $strings['mail_change_address']), 'the form\'s two buttons');
}

function test_workspace_mail_domain_warning_wiring()
{
	$send = workspace_mail_domain_warning_test_source('includes/workspace/mail_send.php');
	pg_assert_contains("if (empty(\$mail['send_anyway'])) {", $send, 'Send anyway skips the lookup');
	pg_assert_contains("\$typed = \$conversation ? array_merge(\$input['cc'], \$input['bcc']) : array_merge(\$input['to'], \$input['cc'], \$input['bcc']);", $send,
		'an answer\'s To is the customer\'s own address');

	$compose = workspace_mail_domain_warning_test_source('includes/workspace/mail_compose.php');
	pg_assert_contains("'send_anyway' =>", $compose, 'the form carries the mark');
	pg_assert_true(ws_mail_compose_mail(array('send_anyway' => '1'), array())['send_anyway'], 'marked');
	pg_assert_true(!ws_mail_compose_mail(array('send_anyway' => false), array())['send_anyway'], 'false from the API');
	pg_assert_true(!ws_mail_compose_mail(array(), array())['send_anyway'], 'not marked');

	$actions = workspace_mail_domain_warning_test_source('includes/workspace/actions.php');
	pg_assert_contains("'send_anyway' => \$mail['send_anyway']", $actions, 'the answer carries the mark');
	pg_assert_contains("+ array('code' => \$result['error'])", $actions, 'the form hears the code');

	$refusal = ws_api_mail_send_refusal('unknown_domain');
	pg_assert_same(array('status' => 409, 'code' => 'unknown_domain', 'field' => 'to'), $refusal, 'the API warns');
	pg_assert_true(isset(api_error_catalogue()['unknown_domain']), 'the code is documented');

	$js = workspace_mail_domain_warning_test_source('assets/js/workspace_mail.js');
	pg_assert_contains("error.code === 'unknown_domain'", $js, 'the drawer shows the warning');
	pg_assert_contains('post.send_anyway = 1;', $js, 'Send anyway posts the mark');
}
