<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for input and output helpers in includes/fn/core.php and the tax
 * rate parser in includes/fn/ecommerce.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A javascript: URL is not one of the accepted forms and is refused.
function test_core_escape_url_refuses_javascript()
{
	pg_assert_false(escape_url('javascript:alert(1)'));
}

// An absolute https URL is accepted and returned unchanged.
function test_core_escape_url_keeps_https()
{
	pg_assert_same('https://example.com/a', escape_url('https://example.com/a'));
}

// Anything other than asc/desc in an ORDER BY direction falls back to asc.
function test_core_sql_order_direction_whitelists()
{
	pg_assert_same('asc', sql_order_direction('DROP'));
}

// A comma decimal separator is read and the rate is stored with three places.
function test_core_parse_tax_rate_reads_comma()
{
	pg_assert_same('18.500', parse_tax_rate('18,5'));
}

// A parameter that arrived as an array is carried over in bracket form
// instead of stopping the page; a plain parameter is written as before.
function test_core_build_url_carries_array_parameter()
{
	pg_assert_same(
		'/members?a=1&q%5B0%5D=x&summary=most_recent',
		build_url(array('url' => '/members?a=1&q[]=x', 'parameters' => array('summary' => 'most_recent'))));
	pg_assert_same('/members?a=1&summary=x', build_url(array('url' => '/members?a=1&summary=y', 'parameters' => array('summary' => 'x'))));
}

// A NULL column or an unset value prints and escapes as nothing, without the
// PHP 8.1 "Passing null" deprecation (the runner turns it into an error).
function test_core_h_and_escape_take_null()
{
	pg_assert_same('', h(null));
	pg_assert_same('', escape(null));
	pg_assert_same('a &amp; &quot;b&quot;', h('a & "b"'));
	pg_assert_same('0', h(0));
}

// The JavaScript, CSV and URL path helpers do the same for null; other values
// are encoded as before.
function test_core_other_escape_helpers_take_null()
{
	pg_assert_same('', escape_javascript(null));
	pg_assert_same('', escape_csv(null));
	pg_assert_same('', encode_url_path(null));
	pg_assert_same("it\\'s", escape_javascript("it's"));
	pg_assert_same('a""b', escape_csv('a"b'));
	pg_assert_same('a%20b/c', encode_url_path('a b/c'));
}

// The short version in titles and the logo badge. The installer's VERSION is
// true, which names no version: '' then, and the title goes without one.
function test_core_short_version()
{
	pg_assert_same('26.11', pg_short_version('2026.11.0'), 'year and month');
	pg_assert_same('26.4', pg_short_version('2026.4.10'), 'a one-digit month');
	pg_assert_same('26', pg_short_version('2026'), 'the year alone');
	pg_assert_same('26', pg_short_version(2026), 'a number');
	pg_assert_same('26.11', pg_short_version('v2026.11.0'), 'a leading v');
	pg_assert_same('', pg_short_version(true), 'the installer\'s marker');
	pg_assert_same('', pg_short_version(null), 'nothing');
	pg_assert_same('', pg_short_version(array('2026')), 'not a version');
}

// A time is drawn in the signed-in person's own zone unless the caller asks for
// the site's. None of the callers of get_relative_time() names timezone_type,
// and the rule used to read the missing key as "site", so a person's zone was
// never applied to a relative time.
function test_core_time_in_user_zone_is_the_default()
{
	pg_assert_true(pg_time_in_user_zone(array('timestamp' => 1), true, 'America/Los_Angeles'), 'nothing asked: the person\'s zone');
	pg_assert_true(pg_time_in_user_zone(array('timezone_type' => 'user'), true, 'America/Los_Angeles'), 'asked for the person\'s zone');
	pg_assert_false(pg_time_in_user_zone(array('timezone_type' => 'site'), true, 'America/Los_Angeles'), 'asked for the site\'s zone');
	pg_assert_false(pg_time_in_user_zone(array(), false, 'America/Los_Angeles'), 'nobody signed in');
	pg_assert_false(pg_time_in_user_zone(array(), true, ''), 'a person who keeps the site\'s zone');
	pg_assert_false(pg_time_in_user_zone(null, false, ''), 'no properties at all');
}

// The runner signs nobody in and defines no USER_* constants: a relative time
// is then drawn in the site's zone, without an undefined constant error.
function test_core_relative_time_without_a_signed_in_person()
{
	pg_assert_false(pg_time_uses_user_zone(array()), 'nobody signed in');
}

// Every zone the pick lists offer is a current IANA name, so a PHP that reads
// the system's time zone database without its legacy links (Debian and
// Ubuntu's tzdata-legacy) knows each one. Etc/Greenwich is the one fixed GMT
// zone and has no current name of its own in the list of identifiers.
function test_core_timezone_list_offers_current_names()
{
	$known = timezone_identifiers_list();

	foreach (get_timezones() as $label => $zone) {
		if ($zone === 'Etc/Greenwich') {
			continue;
		}

		pg_assert_true(in_array($zone, $known, true), $label . ' (' . $zone . ') is a current zone name');
	}

	foreach (pg_timezone_legacy_names() as $old => $new) {
		pg_assert_true(in_array($new, $known, true), $old . ' becomes the current name ' . $new);
		pg_assert_false(in_array($old, get_timezones(), true), $old . ' is no longer offered');
	}
}

// A stored name is read as this PHP knows it: an old name as its current one,
// a current name as its old one where an older database lacks it, an unknown
// name as none (the site's zone).
function test_core_timezone_canonical_maps_old_names()
{
	pg_assert_same('America/New_York', pg_timezone_canonical('US/Eastern'), 'an old name becomes its current one');
	pg_assert_same('Asia/Kathmandu', pg_timezone_canonical(' Asia/Katmandu '), 'trimmed');
	pg_assert_same('Europe/Istanbul', pg_timezone_canonical('Europe/Istanbul'), 'a current name stays');
	pg_assert_same('UTC', pg_timezone_canonical('UTC'), 'UTC stays');
	pg_assert_same('', pg_timezone_canonical(''), 'none is none');
	pg_assert_same('', pg_timezone_canonical('Nowhere/Atlantis'), 'an unknown name is none');

	// A database older than tzdata 2020a has America/Godthab, not America/Nuuk.
	$old_database = function ($zone) {
		return $zone !== 'America/Nuuk';
	};

	pg_assert_same('America/Godthab', pg_timezone_canonical('America/Godthab', $old_database), 'the old name where the current one is missing');
	pg_assert_same('America/Godthab', pg_timezone_canonical('America/Nuuk', $old_database), 'and the current name falls back to it');

	// A system database without the legacy links.
	$no_links = function ($zone) {
		return !array_key_exists($zone, pg_timezone_legacy_names());
	};

	pg_assert_same('America/Chicago', pg_timezone_canonical('US/Central', $no_links), 'the current name where the old one is missing');

	pg_assert_same('America/New_York', pg_timezone_list_value('US/Eastern'), 'the pick list shows an old name as its current option');
	pg_assert_same('Europe/Istanbul', pg_timezone_list_value('Europe/Istanbul'), 'a current name as it is');
}

// A zone that cannot be built never stops the request: the site's zone,
// then UTC.
function test_core_timezone_object_never_throws()
{
	pg_assert_same('America/New_York', pg_timezone_object('US/Eastern')->getName(), 'an old name');
	pg_assert_same(date_default_timezone_get(), pg_timezone_object('Nowhere/Atlantis')->getName(), 'an unknown name: the default zone');
	pg_assert_same(date_default_timezone_get(), pg_timezone_object('')->getName(), 'none: the default zone');
}
