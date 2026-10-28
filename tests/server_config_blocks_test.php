<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The compression and static caching blocks of the web-root server rules
 * (includes/server_config.php): every default passes its own detects, a
 * repair adds what is missing at the right place, and a second repair adds
 * nothing. IIS gets one <staticContent> only, since a second one is a 500.19
 * for the whole site.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// What pg_server_config_repair() does to the text, without the file: every
// block whose detect misses is inserted at its anchor, in list order.
function server_config_blocks_test_repair($contents, $server)
{
	foreach (pg_server_config_blocks($server) as $block) {
		if (preg_match($block['detect'], $contents)) {
			continue;
		}

		$merged = ($server === 'iis')
			? pg_server_config_insert_iis($contents, $block)
			: pg_server_config_insert_apache($contents, $block);

		if ($merged !== null) {
			$contents = $merged;
		}
	}

	return $contents;
}

function server_config_blocks_test_xml_ok($contents)
{
	$previous = libxml_use_internal_errors(true);
	$ok = (simplexml_load_string($contents) !== false);
	libxml_clear_errors();
	libxml_use_internal_errors($previous);

	return $ok;
}

// The <clientCache> sits inside the one <staticContent>.
function server_config_blocks_test_cache_inside($contents)
{
	return (bool) preg_match('/<staticContent\b[^>]*>(?:(?!<\/staticContent>).)*<clientCache\b(?:(?!<\/staticContent>).)*<\/staticContent>/is', $contents);
}

function test_server_config_blocks_defaults_pass_their_detects()
{
	foreach (array('apache', 'iis', 'nginx') as $server) {
		$default = pg_server_config_default($server);

		foreach (pg_server_config_blocks($server) as $block) {
			pg_assert_true((bool) preg_match($block['detect'], $default), $server . ': ' . $block['key']);
		}
	}
}

function test_server_config_blocks_new_keys_are_recommended()
{
	foreach (array('apache', 'iis') as $server) {
		$levels = array();

		foreach (pg_server_config_blocks($server) as $block) {
			$levels[$block['key']] = $block['level'];
		}

		pg_assert_same('recommended', $levels['compression'] ?? '', $server . ': compression');
		pg_assert_same('recommended', $levels['cache'] ?? '', $server . ': cache');
	}
}

// Only static types: pages carry CSRF tokens, and server-sent events or the
// installer's streamed progress would be held back.
function test_server_config_blocks_compression_leaves_pages_and_streams_alone()
{
	foreach (array('apache', 'iis', 'nginx') as $server) {
		$default = pg_server_config_default($server);

		pg_assert_false((bool) preg_match('/text\/html/i', $default), $server . ': no text/html');
		pg_assert_false((bool) preg_match('/event-stream/i', $default), $server . ': no event-stream');
	}

	$iis = pg_server_config_default('iis');
	pg_assert_true((bool) preg_match('/<urlCompression\s+doStaticCompression="true"\s+doDynamicCompression="false"\s*\/>/', $iis), 'iis: static only');
	pg_assert_false((bool) preg_match('/<httpCompression\b/i', $iis), 'iis: httpCompression left to the server');

	// Apache: both blocks act on real files only, never on router.php.
	$apache = pg_server_config_default('apache');
	pg_assert_same(2, preg_match_all('/<FilesMatch "\\\\\.\((css\|js\|)[^"]*\)\$">/', $apache), 'apache: two static FilesMatch');
	pg_assert_false((bool) preg_match('/<FilesMatch "[^"]*php/i', $apache), 'apache: no php in FilesMatch');
}

function test_server_config_blocks_iis_default_one_static_content()
{
	$iis = pg_server_config_default('iis');

	pg_assert_same(1, preg_match_all('/<staticContent\b/i', $iis), 'one staticContent');
	pg_assert_true(server_config_blocks_test_cache_inside($iis), 'clientCache inside it');
	pg_assert_true(server_config_blocks_test_xml_ok($iis), 'parses');
}

// The charset block comes first: its detect is <staticContent>, and the cache
// block goes into the element it writes.
function test_server_config_blocks_iis_cache_after_charset()
{
	$keys = array();
	foreach (pg_server_config_blocks('iis') as $block) {
		$keys[] = $block['key'];
	}

	pg_assert_true(array_search('charset', $keys, true) < array_search('cache', $keys, true), 'charset before cache');
}

function test_server_config_blocks_iis_repair_into_existing_static_content()
{
	$file = '<?xml version="1.0" encoding="UTF-8"?>
<configuration>
	<system.webServer>
		<staticContent>
			<mimeMap fileExtension=".webmanifest" mimeType="application/manifest+json" />
		</staticContent>
		<rewrite>
			<rules>
			</rules>
		</rewrite>
	</system.webServer>
</configuration>
';

	$once = server_config_blocks_test_repair($file, 'iis');

	pg_assert_same(1, preg_match_all('/<staticContent\b/i', $once), 'still one staticContent');
	pg_assert_true(server_config_blocks_test_cache_inside($once), 'clientCache inside it');
	pg_assert_contains('.webmanifest', $once, 'operator mapping kept');
	pg_assert_same(1, preg_match_all('/<urlCompression\b/i', $once), 'compression added');
	pg_assert_true(server_config_blocks_test_xml_ok($once), 'parses');
	pg_assert_same($once, server_config_blocks_test_repair($once, 'iis'), 'second repair adds nothing');
}

function test_server_config_blocks_iis_repair_without_static_content()
{
	$file = '<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <rewrite>
            <rules>
            </rules>
        </rewrite>
    </system.webServer>
</configuration>
';

	$once = server_config_blocks_test_repair($file, 'iis');

	pg_assert_same(1, preg_match_all('/<staticContent\b/i', $once), 'one staticContent');
	pg_assert_true(server_config_blocks_test_cache_inside($once), 'clientCache inside the charset element');
	pg_assert_true(server_config_blocks_test_xml_ok($once), 'parses');
	pg_assert_same($once, server_config_blocks_test_repair($once, 'iis'), 'second repair adds nothing');
}

function test_server_config_blocks_iis_cache_anchor()
{
	$block = null;
	foreach (pg_server_config_blocks('iis') as $candidate) {
		if ($candidate['key'] === 'cache') {
			$block = $candidate;
		}
	}

	pg_assert_same('static-content', $block['anchor'], 'anchor');

	// A self-closing element is opened up, not doubled.
	$self_closing = "<configuration>\n    <system.webServer>\n        <staticContent />\n    </system.webServer>\n</configuration>\n";
	$merged = pg_server_config_insert_iis($self_closing, $block);
	pg_assert_same(1, preg_match_all('/<staticContent\b/i', $merged), 'self-closing: one staticContent');
	pg_assert_true(server_config_blocks_test_cache_inside($merged), 'self-closing: inside');
	pg_assert_true(server_config_blocks_test_xml_ok($merged), 'self-closing: parses');

	// No element at all: created inside <system.webServer>.
	$bare = "<configuration>\n    <system.webServer>\n    </system.webServer>\n</configuration>\n";
	$merged = pg_server_config_insert_iis($bare, $block);
	pg_assert_true(server_config_blocks_test_cache_inside($merged), 'absent: created');
	pg_assert_true(server_config_blocks_test_xml_ok($merged), 'absent: parses');

	// Nowhere to go: skipped.
	pg_assert_same(null, pg_server_config_insert_iis('<configuration></configuration>', $block), 'no system.webServer');
}

function test_server_config_blocks_apache_repair()
{
	$file = "RewriteEngine on\n\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . pinegrap/router.php [L]\n";

	$once = server_config_blocks_test_repair($file, 'apache');

	pg_assert_same(1, preg_match_all('/#\s*pg-compression\b/', $once), 'compression added once');
	pg_assert_same(1, preg_match_all('/#\s*pg-static-cache\b/', $once), 'cache added once');
	pg_assert_true(strpos($once, 'pg-static-cache') < strpos($once, 'RewriteEngine on'), 'above the rewrite rules');
	pg_assert_same($once, server_config_blocks_test_repair($once, 'apache'), 'second repair adds nothing');

	// The operator's own compression and Expires count as present.
	$own = "SetOutputFilter DEFLATE\nExpiresActive On\n" . $file;
	$keys = array();
	foreach (pg_server_config_blocks('apache') as $block) {
		if (!preg_match($block['detect'], $own)) {
			$keys[] = $block['key'];
		}
	}
	pg_assert_false(in_array('compression', $keys, true), 'own compression');
	pg_assert_false(in_array('cache', $keys, true), 'own expires');
}

// The static location comes after the deny rules (the first matching regular
// expression answers) and still hands a missing file to the router, which
// serves uploaded files at root addresses.
function test_server_config_blocks_nginx_static_location()
{
	$sample = pg_server_config_default('nginx');

	$deny = strpos($sample, 'deny all;');
	$static = strpos($sample, 'location ~* ^/.+\.(css|js|');
	$router = strpos($sample, 'location / {');

	pg_assert_true(is_int($deny) && is_int($static) && ($deny < $static), 'after the deny rules');
	pg_assert_true(is_int($router) && ($static < $router), 'before the router location');
	pg_assert_true((bool) preg_match('/location ~\* \^\/\.\+[^{]*\{[^}]*gzip on;[^}]*expires 7d;[^}]*try_files \$uri \/pinegrap\/router\.php;[^}]*\}/', $sample), 'gzip, expires and the router fallback in the static location');
	pg_assert_false((bool) preg_match('/^gzip on;/m', $sample), 'gzip not switched on for the whole server');
}
