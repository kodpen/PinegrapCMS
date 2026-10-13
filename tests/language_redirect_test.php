<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the automatic language redirect in
 * includes/fn/language_redirect.php: reading Accept-Language, matching a tag
 * to the site's languages, the languages of a country, the order in which the
 * signals decide, and telling an entry from a move inside the site.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// By weight, the written order where weights are equal; q=0, * and junk dropped.
function test_language_redirect_accept_parse_orders_by_weight()
{
	pg_assert_same(array('de-at', 'de', 'en'), pg_lang_accept_parse('de-AT,de;q=0.9,en;q=0.8'), 'browser default');
	pg_assert_same(array('fr', 'en'), pg_lang_accept_parse('en;q=0.5, fr'), 'weight beats order');
	pg_assert_same(array('tr', 'en'), pg_lang_accept_parse('tr, en'), 'equal weights keep order');
	pg_assert_same(array('en'), pg_lang_accept_parse('*;q=0.1, de;q=0, en'), 'wildcard and q=0');
	pg_assert_same(array('zh-hant-tw'), pg_lang_accept_parse('zh-Hant-TW;Q=0.7, <script>, 12'), 'malformed');
	pg_assert_same(array(), pg_lang_accept_parse(''), 'empty');
}

// The tag's own form, then its language, then another variety of it.
function test_language_redirect_match_reads_regions()
{
	$codes = array('tr', 'en', 'de', 'pt-BR');

	pg_assert_same('en', pg_lang_match('en-GB', $codes), 'region falls back');
	pg_assert_same('de', pg_lang_match('DE-ch', $codes), 'any case');
	pg_assert_same('pt-BR', pg_lang_match('pt-BR', $codes), 'Brazil');
	pg_assert_same('pt-BR', pg_lang_match('pt-PT', $codes), 'another variety');
	pg_assert_same('', pg_lang_match('fr', $codes), 'not served');
	pg_assert_same('', pg_lang_match('', $codes), 'empty');

	pg_assert_same('en-GB', pg_lang_match('en-gb', array('en', 'en-GB')), 'exact region first');
	pg_assert_same('pt', pg_lang_match('pt-BR', array('pt')), 'pt-BR reads pt');
}

// Chinese by script or region; old and alternative codes.
function test_language_redirect_match_reads_scripts_and_aliases()
{
	$codes = array('en', 'zh-CN', 'zh-TW', 'he', 'no', 'id', 'tl');

	pg_assert_same('zh-TW', pg_lang_match('zh-Hant', $codes), 'Traditional script');
	pg_assert_same('zh-TW', pg_lang_match('zh-HK', $codes), 'Hong Kong');
	pg_assert_same('zh-CN', pg_lang_match('zh-Hans-CN', $codes), 'Simplified');
	pg_assert_same('zh-CN', pg_lang_match('zh', $codes), 'bare zh');
	pg_assert_same('zh-TW', pg_lang_match('zh', array('en', 'zh-TW')), 'only Traditional served');
	pg_assert_same('he', pg_lang_match('iw', $codes), 'iw');
	pg_assert_same('no', pg_lang_match('nb-NO', $codes), 'nb');
	pg_assert_same('no', pg_lang_match('nn', $codes), 'nn');
	pg_assert_same('id', pg_lang_match('in', $codes), 'in');
	pg_assert_same('tl', pg_lang_match('fil-PH', $codes), 'fil');
}

// Most widely read first; an unknown country has none.
function test_language_redirect_country_languages()
{
	pg_assert_same(array('tr'), pg_lang_country_languages('TR'), 'Turkey');
	pg_assert_same(array('de', 'fr', 'it'), pg_lang_country_languages('ch'), 'Switzerland, any case');
	pg_assert_same(array('pt-BR'), pg_lang_country_languages('BR'), 'Brazil');
	pg_assert_same(array(), pg_lang_country_languages('T1'), 'Tor');
	pg_assert_same(array(), pg_lang_country_languages(''), 'unknown');
}

// The browser first, then the country, then the fallback; nothing to go on is no redirect.
function test_language_redirect_pick_follows_the_order()
{
	$codes = array('tr', 'en', 'de');

	pg_assert_same(array('code' => 'de', 'reason' => 'language'), pg_lang_pick(array('fr', 'de'), 'TR', $codes, 'both'), 'second browser language');
	pg_assert_same(array('code' => 'tr', 'reason' => 'country'), pg_lang_pick(array('fr'), 'TR', $codes, 'both'), 'country after browser');
	pg_assert_same(array('code' => 'en', 'reason' => 'fallback'), pg_lang_pick(array('fr'), 'FR', $codes, 'both', 'en'), 'fallback');
	pg_assert_same(array('code' => '', 'reason' => 'none'), pg_lang_pick(array('fr'), 'FR', $codes, 'both'), 'no fallback');
	pg_assert_same(array('code' => '', 'reason' => 'none'), pg_lang_pick(array(), '', $codes, 'both', 'en'), 'nothing to go on');
	pg_assert_same(array('code' => '', 'reason' => 'none'), pg_lang_pick(array('fr'), 'FR', $codes, 'both', 'ja'), 'fallback not served');
}

// Each mode reads only its own signal; the country is asked only when needed.
function test_language_redirect_pick_respects_the_mode()
{
	$codes = array('tr', 'en', 'de');
	$asked = 0;
	$country = function () use (&$asked) {
		$asked++;
		return 'DE';
	};

	pg_assert_same('en', pg_lang_pick(array('en'), $country, $codes, 'both')['code'], 'browser decides');
	pg_assert_same(0, $asked, 'country not asked');

	pg_assert_same('de', pg_lang_pick(array('en'), $country, $codes, 'country')['code'], 'country only');
	pg_assert_same(1, $asked, 'country asked');

	pg_assert_same(array('code' => '', 'reason' => 'none'), pg_lang_pick(array('fr'), 'DE', $codes, 'language'), 'language only ignores country');
	pg_assert_same('en', pg_lang_pick(array('fr'), 'DE', $codes, 'language', 'en')['code'], 'language only, fallback');
}

// A page of this site gives its language; another host, or none, starts a visit.
function test_language_redirect_referer_language()
{
	$hosts = array('www.example.com', 'www.example.com');
	$prefixes = array('en' => 'en', 'de' => 'de');

	pg_assert_same('en', pg_lang_referer_language('https://www.example.com/en/about', $hosts, '/', $prefixes, 'tr'), 'language directory');
	pg_assert_same('tr', pg_lang_referer_language('https://www.example.com/about?x=1', $hosts, '/', $prefixes, 'tr'), 'source page');
	pg_assert_same('tr', pg_lang_referer_language('https://WWW.example.com/', $hosts, '/', $prefixes, 'tr'), 'origin only, any case');
	pg_assert_same('tr', pg_lang_referer_language('https://www.example.com/english', $hosts, '/', $prefixes, 'tr'), 'a name that starts like a prefix');
	pg_assert_same(null, pg_lang_referer_language('https://www.google.com/', $hosts, '/', $prefixes, 'tr'), 'search engine');
	pg_assert_same(null, pg_lang_referer_language('', $hosts, '/', $prefixes, 'tr'), 'none');
	pg_assert_same(null, pg_lang_referer_language('not a url', $hosts, '/', $prefixes, 'tr'), 'malformed');

	pg_assert_same('de', pg_lang_referer_language('http://localhost:8080/site/de/', array('localhost:8080'), '/site/', $prefixes, 'tr'), 'port and sub-folder');
	pg_assert_same('tr', pg_lang_referer_language('http://localhost:8080/other/de/', array('localhost:8080'), '/site/', $prefixes, 'tr'), 'outside the site folder');
}

// Off unless the setting is on (the constant is undefined here).
function test_language_redirect_is_off_without_the_setting()
{
	pg_assert_false(pg_lang_redirect_enabled());
}
