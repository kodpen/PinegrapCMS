<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the firewall's block page wording (waf.php): which kind of
 * refusal a request is (waf_deny_kind()), the end of the ban covering an
 * address (waf_ban_end_for()) and the title and sentence per kind
 * (waf_deny_texts()), which name no rule and no matched text.
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

function test_waf_deny_kind()
{
	pg_assert_same('ban', waf_deny_kind(403, 'ip-list', 'iplist'), 'the IP list');
	pg_assert_same('rate', waf_deny_kind(429, 'rate-sensitive', 'rate'), 'a rate limit');
	pg_assert_same('rate', waf_deny_kind(429, 'rate-password-reset', 'rate'), 'the reset ceiling');
	pg_assert_same('other-429', waf_deny_kind(429, 'concurrent', 'rate'), 'the open-at-once cap keeps its text');
	pg_assert_same('country', waf_deny_kind(403, 'geo-country', 'country'));

	foreach (array('sqli', 'xss', 'lfi', 'rce', 'protocol') as $category) {
		pg_assert_same('content', waf_deny_kind(403, $category . '-x', $category), $category);
	}

	pg_assert_same('bot', waf_deny_kind(403, 'bot-bad', 'bot'), 'a bot');
	pg_assert_same('bot', waf_deny_kind(403, 'ua-sqlmap', 'tool'), 'an attack tool');
	pg_assert_same('other', waf_deny_kind(403, 'auto-ban', 'ban'), 'a ban event is decided by waf_deny()');
	pg_assert_same('other', waf_deny_kind(403, 'api-key', 'auth'), 'a wrong key');
}

function test_waf_ban_end_for()
{
	$expiries = array('203.0.113.7' => 1800000000, '203.0.113.0/24' => 1800003600, '198.51.100.9' => 0);

	pg_assert_same(1800003600, waf_ban_end_for('203.0.113.7', $expiries), 'the latest end of the entries covering it');
	pg_assert_same(1800003600, waf_ban_end_for('203.0.113.8', $expiries), 'a range');
	pg_assert_same(0, waf_ban_end_for('198.51.100.9', $expiries), 'a ban with no end');
	pg_assert_same(0, waf_ban_end_for('192.0.2.1', $expiries), 'not covered');
	pg_assert_same(0, waf_ban_end_for('203.0.113.7', array('203.0.113.7' => 1800000000, '203.0.113.0/24' => 0)), 'no end wins');
}

// Each kind has its own title and sentence; none names a rule or what
// matched, and a ban names its end in the site's clock time.
function test_waf_deny_texts()
{
	$end = 1800000000;
	list($title, $body) = waf_deny_texts('ban', $end);
	pg_assert_same('Access Paused', $title);
	pg_assert_contains('until ' . date('Y-m-d H:i T', $end) . '.', $body);

	list($title, $body) = waf_deny_texts('ban', 0);
	pg_assert_same('Your address has been blocked by the site firewall. If you believe this is a mistake, please contact the site owner and quote the reference below.', $body, 'a ban without an end drops the time');

	pg_assert_same('Too Many Requests', current(waf_deny_texts('rate')));
	pg_assert_same('Not Available in Your Region', current(waf_deny_texts('country')));
	pg_assert_same('Request Blocked', current(waf_deny_texts('content')));
	pg_assert_same('Automated Access Blocked', current(waf_deny_texts('bot')));

	list($title, $body) = waf_deny_texts('other-429');
	pg_assert_same('Too many requests from your address. Please wait a moment and try again.', $body, 'the open-at-once cap');

	list($title, $body) = waf_deny_texts('other');
	pg_assert_same('This request was blocked by the site firewall. If you believe this is a mistake, please contact the site owner and quote the reference below.', $body, 'the general text');

	$seen = array();

	foreach (array('ban', 'rate', 'country', 'content', 'bot', 'other', 'other-429') as $kind) {
		$texts = waf_deny_texts($kind, $end);
		$seen[$texts[1]] = true;
		pg_assert_same(0, preg_match('/sqli|xss|lfi|rce|rule|ip-list|geo-/i', $texts[0] . ' ' . $texts[1]), $kind . ': no rule name');
	}

	pg_assert_same(7, count($seen), 'one sentence per kind');
}

// waf_deny() asks for the kind and, for a ban event logged after the
// refusal, whether a ban now covers the address.
function test_waf_deny_uses_the_kind()
{
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$start = strpos($waf, 'function waf_deny($status');
	$body = substr($waf, $start, strpos($waf, "\nfunction ", $start + 1) - $start);

	pg_assert_contains('waf_deny_kind($status, waf_last_rule(), waf_last_category())', $body);
	pg_assert_contains("waf_deny_kind(\$status, '', waf_refusal_category())", $body);
	pg_assert_contains('list($title, $body) = waf_deny_texts($kind, $end);', $body);
}
