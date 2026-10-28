<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_main_host_redirect_url() in includes/fn/seo.php: a page asked
 * for on another host name of the site goes to the same address on its main
 * host, and nothing else is redirected.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The other name moves to the main host with its path and query.
function test_main_host_redirect_other_name()
{
	pg_assert_same('https://www.example.com/blog?page_number=2', pg_main_host_redirect_url(true, 'GET', 'example.com', 'www.example.com', '/blog?page_number=2', 'https://'));
	pg_assert_same('https://example.com/', pg_main_host_redirect_url(true, 'HEAD', 'WWW.Example.com', 'example.com', '/', 'https://'));
	pg_assert_same('http://example.com:8080/a', pg_main_host_redirect_url(true, 'get', 'www.example.com:8080', 'example.com:8080', '/a', 'http://'));
}

// Off, another method, the main host itself (case, trailing dot), no host.
function test_main_host_redirect_not_needed()
{
	pg_assert_same('', pg_main_host_redirect_url(false, 'GET', 'example.com', 'www.example.com', '/', 'https://'));
	pg_assert_same('', pg_main_host_redirect_url(true, 'POST', 'example.com', 'www.example.com', '/', 'https://'));
	pg_assert_same('', pg_main_host_redirect_url(true, 'GET', 'WWW.EXAMPLE.COM', 'www.example.com', '/', 'https://'));
	pg_assert_same('', pg_main_host_redirect_url(true, 'GET', 'www.example.com.', 'www.example.com', '/', 'https://'));
	pg_assert_same('', pg_main_host_redirect_url(true, 'GET', 'www.example.com.:443', 'www.example.com:443', '/', 'https://'));
	pg_assert_same('', pg_main_host_redirect_url(true, 'GET', '', 'www.example.com', '/', 'https://'));
	pg_assert_same('', pg_main_host_redirect_url(true, 'GET', 'example.com', '', '/', 'https://'));
	pg_assert_same('', pg_main_host_redirect_url(true, 'GET', 'example.com', 'bad host/', '/', 'https://'));
}

// A server reached by its address or a local copy is never sent away.
function test_main_host_redirect_addresses_and_localhost()
{
	foreach (array('192.0.2.10', '192.0.2.10:8080', '[2001:db8::1]', '[2001:db8::1]:443', 'localhost', 'localhost:8000', 'site.localhost') as $host) {
		pg_assert_same('', pg_main_host_redirect_url(true, 'GET', $host, 'www.example.com', '/', 'https://'), $host);
	}
}

// The path never names another host: a protocol-relative or bare address
// becomes the site root.
function test_main_host_redirect_path_stays_on_the_site()
{
	pg_assert_same('https://www.example.com/', pg_main_host_redirect_url(true, 'GET', 'example.com', 'www.example.com', '//evil.example/x', 'https://'));
	pg_assert_same('https://www.example.com/', pg_main_host_redirect_url(true, 'GET', 'example.com', 'www.example.com', 'https://evil.example/', 'https://'));
	pg_assert_same('https://www.example.com/', pg_main_host_redirect_url(true, 'GET', 'example.com', 'www.example.com', "/a\r\nSet-Cookie: x=1", 'https://'));
}

// get_page.php asks first thing after init.php, with a 301; the setting is
// read with a fallback, written by the SEO card once its column is there,
// and added off by its step.
function test_main_host_redirect_wiring()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php');
	$init = strpos($page, "include ('init.php');");
	$redirect = strpos($page, "pg_main_host_redirect_url(true, \$_SERVER['REQUEST_METHOD'] ?? '', \$_SERVER['HTTP_HOST'] ?? '', HOSTNAME_SETTING, REQUEST_URL, URL_SCHEME);");
	$maintenance = strpos($page, 'pg_maintenance_gate();');
	pg_assert_true(($init !== false) && ($redirect !== false) && ($maintenance !== false) && ($init < $redirect) && ($redirect < $maintenance));
	pg_assert_contains("header('Location: ' . \$pg_main_host_url, true, 301);", $page);

	pg_assert_contains("define('MAIN_HOST_REDIRECT', (int) (\$row['main_host_redirect'] ?? 0) == 1);", (string) file_get_contents(PG_FUNCTIONS_DIR . '/init.php'));
	pg_assert_contains("pg_schema_has('config', 'main_host_redirect')", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/seo.save.php'));

	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_main_host_redirect();', $migration);
	pg_assert_contains("install_add_column('config', 'main_host_redirect', \"TINYINT(1) UNSIGNED NOT NULL DEFAULT 0\");", $migration);
	pg_assert_false(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php'), 'main_host_redirect') !== false, 'not switched on for new sites');
}
