<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Kodpen API client base (includes/fn/vendor_api.php): the
 * switch line the hosting platform reads, the base address and its test
 * override.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/vendor_api.php');

// The switch stays off until the release that turns it on, and its line keeps
// the one form the hosting platform reads the file for: the on form appears
// nowhere in the file, comments included.
function test_vendor_api_switch_is_off_on_its_own_line()
{
	pg_assert_same(false, PG_VENDOR_API_V1, 'the switch is off');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/vendor_api.php');

	pg_assert_same(1, preg_match_all("/^[ \\t]*define\\('PG_VENDOR_API_V1', (true|false)\\);[ \\t]*\\r?$/m", $source, $lines), 'one switch line');
	pg_assert_same('false', isset($lines[1][0]) ? $lines[1][0] : null, 'in its off form');
	pg_assert_same(0, preg_match("/^[ \\t]*define\\('PG_VENDOR_API_V1', true\\);[ \\t]*\\r?$/m", $source), 'the platform reads it as off');
	pg_assert_same(false, strpos($source, "'PG_VENDOR_API_V1', true"), 'the on form nowhere in the file');
	pg_assert_same(1, substr_count($source, "define('PG_VENDOR_API_V1'"), 'defined once');
}

function test_vendor_api_base_and_its_test_override()
{
	pg_assert_same('https://api.kodpen.com/v1', pg_vendor_api_base_from(null), 'not set: the production server');

	foreach (array('https://localhost:8443/v1', 'https://api.kodpen.test/v1', 'https://dev.example.com/kodpen/v1', 'https://kodpen_api/v1') as $override) {
		pg_assert_same($override, pg_vendor_api_base_from($override), 'taken: ' . $override);
	}

	// Anything else calls nothing, the production server neither.
	foreach (array('', 'http://localhost/v1', 'https://localhost/v2', 'https://localhost/v1/', 'https://localhost', 'api.kodpen.com/v1',
		'https:///v1', 'https://user@host/v1', 'https://user:pass@host/v1', 'https://host/v1?x=1', 'https://host/v1#a',
		'https://ho st/v1', "https://host/v1\n", "https://host\r\n/v1", 'HTTPS://host/v1', 'https://host:/v1', true, 1) as $override) {
		pg_assert_same('', pg_vendor_api_base_from($override), 'refused: ' . var_export($override, true));
	}

	if (!defined('PG_VENDOR_API_URL')) {
		pg_assert_same('https://api.kodpen.com/v1', pg_vendor_api_base());
		pg_assert_same('https://api.kodpen.com/v1/licenses/verify', pg_vendor_api_url('/licenses/verify'));
	}
}
