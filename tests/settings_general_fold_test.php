<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > General as folded rows: each part's line of state, the
 * rows' order, Organization's three groups, the IP as a line, the outgoing
 * proxy's line and link, the pg-switch, Date format's line, and the names
 * that stay (card ids, field names, the save, the search words).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/general_settings_fold.php');

function settings_general_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The part of general.php from one card's id to the next row's assignment.
function settings_general_fold_test_card($id)
{
	$source = settings_general_fold_test_source('includes/settings/general.php');
	$start = strpos($source, '<div id="' . $id . '" class="pg-set-card">');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, '$general_parts[', $start);

	return substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
}

function test_settings_general_fold_order_and_names()
{
	pg_assert_same(array('organization', 'server', 'software', 'datetime'), pg_general_fold_order(), 'the rows in the description\'s order');

	$names = array();

	foreach (pg_general_fold_order() as $key) {
		$face = pg_general_fold_face($key);
		$names[] = $face['name'];
		pg_assert_true(strpos($face['icon'], 'bi-') === 0, $key . ': an icon');
		pg_assert_false(stripos($face['name'], 'Enable') === 0, $key . ': a name never starts with Enable');
	}

	pg_assert_same(array(lang('Organization'), lang('Site address'), lang('Software'), lang('Date and time')), $names, 'the rows\' names');
}

function test_settings_general_fold_lines()
{
	pg_assert_same('Kodpen, İstanbul', pg_general_fold_organization('Kodpen', ' İstanbul ')['line'], 'the name and the city');
	pg_assert_same('Kodpen', pg_general_fold_organization('Kodpen', '')['line'], 'the name alone');
	pg_assert_same(lang('Not filled'), pg_general_fold_organization('', ' ')['line'], 'nothing filled');

	pg_assert_same('example.com', pg_general_fold_site('example.com')['line'], 'the Hostname');
	pg_assert_same(lang('No hostname set'), pg_general_fold_site('')['line'], 'no Hostname');

	pg_assert_same(lang('Licence set'), pg_general_fold_software(true, false)['line'], 'a key');
	pg_assert_same(lang('No licence key') . ' · ' . lang('verbose database errors on'), pg_general_fold_software(false, true)['line'], 'no key, verbose errors on');

	$noon = mktime(12, 0, 0, 2, 14, 2026);
	pg_assert_same('Europe/Istanbul, 14/2/2026', pg_general_fold_datetime('Europe/Istanbul', 'day_month', $noon)['line'], 'day first');
	pg_assert_same('Europe/Istanbul, 2/14/2026', pg_general_fold_datetime('Europe/Istanbul', 'month_day', $noon)['line'], 'month first');
	pg_assert_same('14/2/2026', pg_general_fold_datetime('', '', $noon)['line'], 'no zone named');

	foreach (array(pg_general_fold_organization('', ''), pg_general_fold_site(''), pg_general_fold_software(false, true), pg_general_fold_datetime('', '', $noon)) as $row) {
		pg_assert_same(null, $row['pill'], 'no pill');
		pg_assert_false($row['attention'], 'no row asks for attention');
	}
}

function test_settings_general_fold_pane_is_rows()
{
	$pane = settings_general_fold_test_source('includes/settings/general.php');

	pg_assert_contains('$pg_settings_cards[] = pg_settings_fold($general_rows);', $pane, 'the pane is one list of folded rows');
	pg_assert_same(1, substr_count($pane, '$pg_settings_cards[]'), 'no card outside the rows');
	pg_assert_same(0, substr_count($pane, 'class="switch"'), 'no old switch drawing');
	pg_assert_same(1, substr_count($pane, 'class="pg-switch"'), 'Verbose database errors is a pg-switch');

	foreach (array('Server & Domain', 'Date & Time', 'Proxy Address', 'Verbose Database Errors', 'Website IP Address') as $old) {
		pg_assert_false(strpos($pane, "lang('" . $old . "')") !== false, 'no "' . $old . '" label');
	}
}

function test_settings_general_fold_cards()
{
	$organization = settings_general_fold_test_card('pgset-organization');
	$groups = array(
		strpos($organization, "<h3 class=\"pg-group-label mb-0\">' . lang('Name and address')"),
		strpos($organization, "<label for=\"setting_email_address\" class=\"pg-group-label d-block mb-0\">' . lang('Support E-mail Address')"),
		strpos($organization, "<h3 class=\"pg-group-label mb-0\">' . lang('Seller')"),
	);
	pg_assert_true(!in_array(false, $groups, true) && ($groups[0] < $groups[1]) && ($groups[1] < $groups[2]), 'Organization in three groups, in order');
	pg_assert_true(strpos($organization, 'name="organization_country"') < $groups[1], 'the address under Name and address');
	pg_assert_true(strpos($organization, '$output_organization_logo_picker') > $groups[2], 'the logo under Seller');
	pg_assert_true(strpos($organization, 'name="erp_seller_vkn"') > $groups[2], 'the tax number under Seller');
	pg_assert_contains("lang('System mail is sent from this address; DKIM signs with its domain when none is set.')", $organization, 'the support address says what it reaches');

	$server = settings_general_fold_test_card('pgset-server');
	pg_assert_false(strpos($server, 'id="ip"') !== false, 'the IP is no box');
	pg_assert_contains("'This server\\'s IP: {var:1}', 'vars' => \$server_addr", $server, 'the IP is a line');
	pg_assert_contains("<label for=\"proxy_address\" class=\"form-label\">' . lang('Outgoing proxy') . '</label>", $server, 'Proxy Address reads Outgoing proxy');
	pg_assert_contains('<a href="#settings/firewall/pgset-proxies">', $server, 'its line leads to Trusted proxies');
	pg_assert_contains('aria-describedby="proxy_address_help"', $server, 'and describes the box');

	$software = settings_general_fold_test_card('pgset-software');
	pg_assert_contains('<input type="checkbox" class="pg-switch" value="1"\' . $debug_checked . \' id="debug" name="debug"', $software, 'the debug switch');
	pg_assert_contains("lang('Verbose database errors')", $software, 'in sentence case');

	$datetime = settings_general_fold_test_card('pgset-datetime');
	pg_assert_contains("lang('A page served in another language uses that language\\'s date format')", $datetime, 'Date format says other languages keep theirs');
	pg_assert_contains('<a href="#settings/languages/pgset-languages">', $datetime, 'and leads to Languages');
	pg_assert_contains("<div class=\"pg-text-meta\">' . lang('Current Site Time') . ': '", $datetime, 'the site time is a meta line');
}

function test_settings_general_fold_names_stay()
{
	$pane = settings_general_fold_test_source('includes/settings/general.php');

	foreach (array('organization_name', 'organization_address_1', 'organization_address_2', 'organization_city', 'organization_state', 'organization_zip_code', 'organization_country', 'email_address', 'erp_seller_vkn', 'erp_seller_tax_office', 'hostname', 'proxy_address', 'subscription_key', 'debug', 'timezone', 'date_format', 'time_format') as $name) {
		pg_assert_contains('name="' . $name . '"', $pane, $name . ' posts under its name');
	}

	$at = 0;

	foreach (array('pgset-organization', 'pgset-server', 'pgset-software', 'pgset-datetime') as $id) {
		$here = strpos($pane, '<div id="' . $id . '" class="pg-set-card">');
		pg_assert_true(($here !== false) && ($here > $at), $id . ' keeps its card inside its row, in order');
		$at = (int) $here;
	}

	pg_assert_false(strpos(settings_general_fold_test_source('includes/settings/general.save.php'), 'fold') !== false, 'the save knows nothing of rows');
}

function test_settings_general_fold_registry_and_search()
{
	$source = settings_general_fold_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        'general' => array(");
	$general = substr($source, $start, strpos($source, "\n        ),\n", $start) - $start);

	preg_match_all("/'(pgset-[a-z-]+)'\s*=> lang\(/", substr($general, 0, (int) strpos($general, "'keywords'")), $sections);
	pg_assert_same(array('pgset-organization', 'pgset-server', 'pgset-software', 'pgset-datetime'), $sections[1], 'the card ids stay, in order');
	pg_assert_contains("'pgset-server'   => lang('Site address')", $general, 'Server & Domain reads Site address');
	pg_assert_contains("'pgset-datetime' => lang('Date and time')", $general, 'Date & Time reads Date and time');

	preg_match("/'pgset-server'\s*=> pg_settings_keywords\(lang\('([^']*)'\)\)/", $general, $server);
	$words = pg_settings_keywords(isset($server[1]) ? $server[1] : '');
	pg_assert_true(in_array('outgoing proxy', $words, true), 'the search finds outgoing proxy');
	pg_assert_true(in_array('proxy address', $words, true), 'and still proxy address');
	pg_assert_true(in_array('hostname', $words, true), 'and hostname');
	pg_assert_contains(', vkn, seller', $general, 'VKN');
	pg_assert_contains("lang('date, time, timezone, format')", $general, 'timezone');

	$anchors = pg_settings_legacy_anchors();
	pg_assert_same(array('general', 'pgset-server'), $anchors['pgset-general'], 'an old General link lands on Site address');
}
