<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the credential-free discovery document
 * (api_discovery_document(), includes/api/resources/discovery.php): what
 * .well-known/pinegrap.json and GET integration.php/discovery answer, and
 * when device sign-in is reported as ready.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/discovery.php');

// What a site with the API on, device sign-in set up and a logo hands the
// builder.
function api_discovery_test_facts($changes = array())
{
	return array_merge(array(
		'version'        => '2026.11.0',
		'api_base'       => 'https://shop.example/pinegrap/integration.php',
		'title'          => 'Example Shop',
		'logo_url'       => 'https://shop.example/files/logo.png',
		'language'       => 'tr',
		'api_enabled'    => true,
		'https_required' => true,
		'devices_ready'  => true,
		'device_app'     => array('status' => 'active', 'expires_at' => 0),
		'ip_allowed'     => true,
		'pairing_ready'  => false,
		'now'            => 1800000000
	), $changes);
}

// Every field, in the order the issue gives, with its type.
function test_api_discovery_document_fields_and_types()
{
	$document = api_discovery_document(api_discovery_test_facts());

	pg_assert_same(array(
		'software', 'version', 'api_version', 'api_base', 'title', 'logo_url',
		'language', 'api_enabled', 'device_signin', 'pairing',
		'https_required', 'min_app_version'
	), array_keys($document), 'field set');

	pg_assert_same('pinegrap', $document['software'], 'software');
	pg_assert_same('2026', $document['version'], 'version');
	pg_assert_same(1, $document['api_version'], 'api_version');
	pg_assert_same('https://shop.example/pinegrap/integration.php', $document['api_base'], 'api_base');
	pg_assert_same('Example Shop', $document['title'], 'title');
	pg_assert_same('https://shop.example/files/logo.png', $document['logo_url'], 'logo_url');
	pg_assert_same('tr', $document['language'], 'language');
	pg_assert_same(true, $document['api_enabled'], 'api_enabled');
	pg_assert_same(true, $document['device_signin'], 'device_signin');
	pg_assert_same(false, $document['pairing'], 'pairing');
	pg_assert_same(true, $document['https_required'], 'https_required');
	pg_assert_same(null, $document['min_app_version'], 'min_app_version');
}

// Only the major line of the installed version is given, never the exact
// build; GET /meta answers the exact version after sign-in.
function test_api_discovery_document_version_is_the_major_line()
{
	$document = api_discovery_document(api_discovery_test_facts(array('version' => '2026.12.3')));

	pg_assert_same('2026', $document['version']);
}

// The major line of a version string, whatever shape it has.
function test_api_discovery_version_line()
{
	pg_assert_same('2026', api_discovery_version_line('2026.11.0'), 'full version');
	pg_assert_same('2027', api_discovery_version_line('2027.1.12'), 'another line');
	pg_assert_same('2026', api_discovery_version_line('2026'), 'no dot');
	pg_assert_same('2026', api_discovery_version_line(' 2026.4.10 '), 'spaces');
	pg_assert_same('', api_discovery_version_line(''), 'empty');
	pg_assert_same('', api_discovery_version_line(null), 'null');
}

// No logo set, or one that resolves to nothing, is null rather than ''.
function test_api_discovery_document_without_a_logo_is_null()
{
	pg_assert_same(null, api_discovery_document(api_discovery_test_facts(array('logo_url' => null)))['logo_url'], 'null');
	pg_assert_same(null, api_discovery_document(api_discovery_test_facts(array('logo_url' => '')))['logo_url'], 'empty');
}

// The API switched off: the document still answers, says so, and offers no
// device sign-in.
function test_api_discovery_document_with_the_api_off()
{
	$document = api_discovery_document(api_discovery_test_facts(array('api_enabled' => false)));

	pg_assert_same(false, $document['api_enabled'], 'api_enabled');
	pg_assert_same(false, $document['device_signin'], 'device_signin');
	pg_assert_same(false, $document['pairing'], 'pairing');
}

// Device sign-in is ready only as far as api_device_app_check() would let a
// sign-in through.
function test_api_discovery_device_signin_not_ready()
{
	$cases = array(
		'no devices table'  => array('devices_ready' => false),
		'no device app'     => array('device_app' => null),
		'app switched off'  => array('device_app' => array('status' => 'inactive', 'expires_at' => 0)),
		'app expired'       => array('device_app' => array('status' => 'active', 'expires_at' => 1700000000)),
		'off the allow list' => array('ip_allowed' => false)
	);

	foreach ($cases as $label => $changes) {
		pg_assert_same(false, api_discovery_document(api_discovery_test_facts($changes))['device_signin'], $label);
	}

	pg_assert_same(true, api_discovery_document(api_discovery_test_facts(array(
		'device_app' => array('status' => 'active', 'expires_at' => 1900000000)
	)))['device_signin'], 'expires later');
}

// Pairing needs device sign-in and the pairing table; neither alone.
function test_api_discovery_pairing()
{
	pg_assert_same(false, api_discovery_document(api_discovery_test_facts())['pairing'], 'no table');
	pg_assert_same(true, api_discovery_document(api_discovery_test_facts(array('pairing_ready' => true)))['pairing'], 'table and sign-in');
	pg_assert_same(false, api_discovery_document(api_discovery_test_facts(array('pairing_ready' => true, 'device_app' => null)))['pairing'], 'table, no sign-in');
}

// The language is the one GET /meta answers, through api_text_language_pick().
function test_api_discovery_language_follows_the_meta_helper()
{
	$has = function ($code) {
		return in_array($code, array('en', 'tr'), true);
	};

	$language = api_text_language_pick('', false, '', 'tr', $has);

	pg_assert_same('tr', api_discovery_document(api_discovery_test_facts(array('language' => $language)))['language'], 'tr');

	$language = api_text_language_pick('de', false, '', 'tr', $has);

	pg_assert_same('en', api_discovery_document(api_discovery_test_facts(array('language' => $language)))['language'], 'no file is en');
}

// The allow list test split from api_check_ip_allowlist(): an empty list
// admits everyone, otherwise the address must match an entry.
function test_api_discovery_allowlist_admits()
{
	pg_assert_true(api_ip_allowlist_admits('', '203.0.113.5'), 'empty list');
	pg_assert_true(api_ip_allowlist_admits("198.51.100.1, 203.0.113.5", '203.0.113.5'), 'listed');
	pg_assert_false(api_ip_allowlist_admits('198.51.100.1', '203.0.113.5'), 'not listed');
}
