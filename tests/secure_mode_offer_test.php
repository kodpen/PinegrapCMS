<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Secure Mode one-step offer: the Firewall screen lists the
 * peer that sent an untrusted https claim under Trusted Proxies as Secure
 * Mode is turned on (includes/settings/firewall.php, firewall.save.php), and
 * the diagnostics page test_secure_mode.php names the peer, its kind and the
 * entries without opening a database or loading init.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function secure_mode_offer_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The save lists the peer only on the ticked box, through the same list
// normaliser the screen uses, and checks the peer against that list before
// anything is written; the listed peer is logged.
function test_secure_mode_offer_save_checks_the_peer_before_writing()
{
	$source = secure_mode_offer_test_source('includes/settings/firewall.save.php');

	pg_assert_contains("post_value('secure_mode_trust_proxy')", $source, 'the box decides');
	pg_assert_contains('pg_https_peer_offer($secure_mode_peer)', $source, 'the peer offer');
	pg_assert_contains("waf_table_has_column('config', 'waf_trusted_proxies')", $source, 'no offer before the column exists');
	pg_assert_contains('$secure_mode_list = waf_proxy_list_on_save(', $source, 'the list as it will be stored');
	pg_assert_contains('pg_https_peer_trusted($secure_mode_peer, $secure_mode_list)', $source, 'checked against that list');
	pg_assert_contains("implode(',', \$waf_secure_mode_entries)", $source, 'the entries go in with the list');
	pg_assert_contains('Secure Mode was turned on and the proxy at {var:1} was added to Trusted Proxies as {var:2}', $source, 'logged');

	// The refusal comes before every write, and the write of the entries
	// comes before the UPDATE that stores the list.
	$refusal = strpos($source, '$url_scheme = URL_SCHEME;');
	$entries = strpos($source, "implode(',', \$waf_secure_mode_entries)");
	$update = strpos($source, 'waf_trusted_proxies = \'" . escape($waf_post_proxies)');
	pg_assert_true(($refusal !== false) && ($entries !== false) && ($update !== false), 'all three present');
	pg_assert_true($refusal < $entries && $entries < $update, 'refusal, entries, then the UPDATE');

	// No text names the config constant as the fix any more.
	pg_assert_same(false, strpos($source, 'Enable TRUST_PROXY_SSL_HEADERS'), 'firewall.save.php');
	pg_assert_same(false, strpos(secure_mode_offer_test_source('init.php'), 'Enable TRUST_PROXY_SSL_HEADERS'), 'init.php');
	pg_assert_same(false, strpos(secure_mode_offer_test_source('get_file.php'), 'Enable TRUST_PROXY_SSL_HEADERS'), 'get_file.php');
	pg_assert_same(false, strpos(secure_mode_offer_test_source('includes/settings/firewall.php'), 'set TRUST_PROXY_SSL_HEADERS'), 'firewall.php');
}

// The box is drawn only while Secure Mode is off and this request's peer is
// an untrusted one with entries to offer; a public peer gets a sentence, not
// a box.
function test_secure_mode_offer_box_only_for_an_offerable_peer()
{
	$source = secure_mode_offer_test_source('includes/settings/firewall.php');

	$start = strpos($source, '$output_secure_mode_offer = \'\';');
	$end = strpos($source, '$pg_settings_cards[] = \'
    <div id="pgset-headers"');
	pg_assert_true(($start !== false) && ($end !== false) && ($start < $end), 'the offer block stands before the card');

	$block = substr($source, $start, $end - $start);
	pg_assert_contains("(\$url_scheme !== 'https://') && !check_if_request_is_secure() && pg_forwarded_https(\$_SERVER)", $block, 'the state');
	pg_assert_same(1, substr_count($block, 'name="secure_mode_trust_proxy"'), 'one box');
	pg_assert_true(strpos($block, 'name="secure_mode_trust_proxy"') < strpos($block, "elseif (\$secure_mode_offer['kind'] === 'public')"), 'the box is the entries branch');
	pg_assert_contains("' . \$output_secure_mode_offer . '", $source, 'drawn in the card');
}

// The diagnostics page stays standalone: no init.php, no database, and it
// decides with the same module Secure Mode uses.
function test_secure_mode_offer_test_page_opens_no_database()
{
	$source = secure_mode_offer_test_source('test_secure_mode.php');

	pg_assert_same(0, preg_match('/(require|include)[^;]*init\.php/', $source), 'no init.php');
	pg_assert_same(false, strpos($source, 'mysqli'), 'no connection');
	pg_assert_same(false, strpos($source, 'db::'), 'no db class');
	pg_assert_same(false, strpos($source, 'functions.php\')'), 'no functions.php');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/request_scheme.php');", $source, 'the scheme module');
	pg_assert_contains('$proxy_tls         = pg_forwarded_https($_SERVER);', $source, 'the parser decides');
	pg_assert_contains('pg_https_peer_offer($peer)', $source, 'the peer offer');
	pg_assert_contains("pg_https_peer_trusted(\$peer, '')", $source, 'trusted without the stored list');
	pg_assert_contains("return 'X-ARR-SSL';", $source, 'names the IIS ARR header');
	pg_assert_same(false, strpos($source, "define('TRUST_PROXY_SSL_HEADERS', true);"), 'no config line to copy');
	pg_assert_contains("define('REQUIRE_SECURE_MODE', false);", $source, 'the lockout release stays');
}
