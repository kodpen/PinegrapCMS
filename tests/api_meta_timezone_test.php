<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the site's time zone in GET /meta (api_meta_timezone(),
 * includes/api/resources/meta.php): the zone PHP runs in once init.php has
 * set it, the server's own when the site setting is empty.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/meta.php');

// The zone date_default_timezone_get() answers, whichever it is.
function test_api_meta_timezone_follows_php()
{
	$before = date_default_timezone_get();

	date_default_timezone_set('Europe/Istanbul');
	pg_assert_same('Europe/Istanbul', api_meta_timezone(), 'site setting');

	// An empty site setting leaves the server's zone in place.
	date_default_timezone_set('UTC');
	pg_assert_same('UTC', api_meta_timezone(), 'server zone');

	date_default_timezone_set($before);

	pg_assert_true(api_meta_timezone() !== '', 'never empty');
}

// GET /meta answers it under site and declares it.
function test_api_meta_timezone_declared()
{
	$schema = api_meta_schema();

	pg_assert_same('string', isset($schema['site']['timezone']) ? $schema['site']['timezone'] : null, 'schema');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/meta.php');

	pg_assert_true(strpos($source, "'timezone' => api_meta_timezone()") !== false, 'answered');
}
