<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for build_url() (includes/fn/core.php): the parts of the given URL
 * that no property replaces are carried into the URL it builds.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A form's Send To address on a non-standard port keeps that port: the
// confirmation redirect went to the same host on port 80 or 443.
function test_build_url_keeps_port_of_given_url()
{
	pg_assert_same(
		'https://example.com:8443/thanks?a=1&5_save_confirmation=true',
		build_url(array(
			'url' => 'https://example.com:8443/thanks?a=1',
			'parameters' => array('5_save_confirmation' => 'true'))));
}

// User information in the given URL stays in front of the host.
function test_build_url_keeps_user_info_of_given_url()
{
	pg_assert_same(
		'http://user:secret@example.com:8080/a?x=1',
		build_url(array(
			'url' => 'http://user:secret@example.com:8080/a',
			'parameters' => array('x' => '1'))));
}

// A hostname property replaces the host and its port together: HOSTNAME
// already carries the port the site answers on.
function test_build_url_hostname_property_replaces_port()
{
	pg_assert_same(
		'https://site.test:9000/thanks?5_confirmation=true',
		build_url(array(
			'url' => 'http://old.test:8080/thanks',
			'scheme' => 'https://',
			'hostname' => 'site.test:9000',
			'parameters' => array('5_confirmation' => 'true'))));
}

// A path-only URL is unchanged.
function test_build_url_path_only_url()
{
	pg_assert_same(
		'/list?page=2',
		build_url(array(
			'url' => '/list',
			'parameters' => array('page' => 2))));
}

// A path without a leading slash placed after a host is made to start with
// one: a form's posted Send To "@evil.example/x" became
// "https://site.test@evil.example/x", where the browser takes the site's
// name as user information and opens evil.example.
function test_build_url_relative_path_after_host_starts_with_slash()
{
	pg_assert_same(
		'https://site.test/@evil.example/x?5_confirmation=true',
		build_url(array(
			'url' => '@evil.example/x',
			'scheme' => 'https://',
			'hostname' => 'site.test',
			'parameters' => array('5_confirmation' => 'true'))));

	pg_assert_same(
		'https://site.test/.evil.example/x',
		build_url(array(
			'url' => '.evil.example/x',
			'scheme' => 'https://',
			'hostname' => 'site.test')));
}

// A path-only URL without a host keeps its relative form.
function test_build_url_relative_path_without_host_unchanged()
{
	pg_assert_same(
		'kiosk.php?a=1',
		build_url(array(
			'url' => 'kiosk.php',
			'parameters' => array('a' => '1'))));
}
