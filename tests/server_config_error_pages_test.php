<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The IIS error pages block of the web-root server rules
 * (includes/server_config.php): IIS keeps the answer PHP sent
 * (existingResponse="PassThrough") and never shows its detailed error page
 * (errorMode="Custom"). The default, the repository's own web.config and a
 * repaired older file carry it once; an <httpErrors> the operator already has
 * is left alone, since a second one is a 500.19 for the whole site.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function server_config_error_pages_test_block()
{
	foreach (pg_server_config_blocks('iis') as $block) {
		if ($block['key'] === 'errors') {
			return $block;
		}
	}

	return null;
}

// Every block whose detect misses is inserted at its anchor, in list order,
// as pg_server_config_repair() does with the file.
function server_config_error_pages_test_repair($contents)
{
	foreach (pg_server_config_blocks('iis') as $block) {
		if (preg_match($block['detect'], $contents)) {
			continue;
		}

		$merged = pg_server_config_insert_iis($contents, $block);

		if ($merged !== null) {
			$contents = $merged;
		}
	}

	return $contents;
}

function server_config_error_pages_test_xml($contents)
{
	$previous = libxml_use_internal_errors(true);
	$xml = simplexml_load_string($contents);
	libxml_clear_errors();
	libxml_use_internal_errors($previous);

	return $xml;
}

// The one element, inside <system.webServer>, with both attributes.
function server_config_error_pages_test_assert_element($contents, $label)
{
	$xml = server_config_error_pages_test_xml($contents);
	pg_assert_true($xml !== false, $label . ': parses');

	if ($xml === false) {
		return;
	}

	$found = $xml->xpath('/configuration/system.webServer/httpErrors');
	pg_assert_same(1, is_array($found) ? count($found) : 0, $label . ': one httpErrors in system.webServer');
	pg_assert_same(1, preg_match_all('/<httpErrors\b/i', $contents), $label . ': one httpErrors in the file');

	if (is_array($found) && (count($found) === 1)) {
		pg_assert_same('Custom', (string) $found[0]['errorMode'], $label . ': errorMode');
		pg_assert_same('PassThrough', (string) $found[0]['existingResponse'], $label . ': existingResponse');
	}
}

function test_server_config_error_pages_block()
{
	$block = server_config_error_pages_test_block();

	pg_assert_true(is_array($block), 'the iis list has the errors block');
	pg_assert_same('recommended', $block['level'], 'level');
	pg_assert_same('web-server', $block['anchor'], 'anchor');
	pg_assert_same('        <httpErrors errorMode="Custom" existingResponse="PassThrough" />', $block['snippet'], 'snippet');
}

// Apache and nginx hand PHP's answer through as it is; the rule is IIS's.
function test_server_config_error_pages_iis_only()
{
	foreach (array('apache', 'nginx') as $server) {
		foreach (pg_server_config_blocks($server) as $block) {
			pg_assert_true($block['key'] !== 'errors', $server . ': no errors block');
		}
		pg_assert_false((bool) preg_match('/httpErrors/i', pg_server_config_default($server)), $server . ': default');
	}
}

function test_server_config_error_pages_iis_default()
{
	server_config_error_pages_test_assert_element(pg_server_config_default('iis'), 'default');
}

// IIS sites run with the repository's own web.config: it carries every block
// the scan looks for, this one included, so a site on it reports nothing
// missing.
function test_server_config_error_pages_shipped_web_config()
{
	$shipped = (string) file_get_contents(dirname(PG_FUNCTIONS_DIR) . '/web.config');

	server_config_error_pages_test_assert_element($shipped, 'web.config');

	foreach (pg_server_config_blocks('iis') as $block) {
		pg_assert_true((bool) preg_match($block['detect'], $shipped), 'web.config: ' . $block['key']);
	}
}

// A site installed before the block: the repair adds it once, the file still
// parses, and a second repair changes nothing.
function test_server_config_error_pages_repair_adds_it_once()
{
	$older = preg_replace('/\s*<httpErrors\b[^>]*\/>/i', '', pg_server_config_default('iis'));

	pg_assert_false((bool) preg_match('/<httpErrors\b/i', $older), 'the older file has none');

	$once = server_config_error_pages_test_repair($older);

	server_config_error_pages_test_assert_element($once, 'repaired');
	pg_assert_same($once, server_config_error_pages_test_repair($once), 'second repair adds nothing');
}

// An <httpErrors> of the operator's, a hosting panel's custom pages say, is
// theirs: it counts as present and nothing is written beside it.
function test_server_config_error_pages_own_element_kept()
{
	$own = '<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <httpErrors>
            <remove statusCode="500" subStatusCode="-1" />
            <error statusCode="500" path="/errors/500.html" responseMode="File" />
        </httpErrors>
        <rewrite>
            <rules>
            </rules>
        </rewrite>
    </system.webServer>
</configuration>
';

	pg_assert_true((bool) preg_match(server_config_error_pages_test_block()['detect'], $own), 'counts as present');

	$repaired = server_config_error_pages_test_repair($own);

	pg_assert_same(1, preg_match_all('/<httpErrors\b/i', $repaired), 'no second httpErrors');
	pg_assert_contains('/errors/500.html', $repaired, 'operator pages kept');
	pg_assert_true(server_config_error_pages_test_xml($repaired) !== false, 'parses');
}

// Nowhere to go: skipped rather than written outside <system.webServer>.
function test_server_config_error_pages_no_anchor()
{
	pg_assert_same(null, pg_server_config_insert_iis('<configuration></configuration>', server_config_error_pages_test_block()), 'no system.webServer');
}
