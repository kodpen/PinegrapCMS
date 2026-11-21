<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Visitors and privacy as folded rows: each part's line of
 * state, the rows' order, no popover or old switch left, every switch's
 * fields drawn whatever its state, the KVKK fields in a row of their own,
 * and the names that stay (card ids, field names, the save).
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
require_once(PG_FUNCTIONS_DIR . '/includes/settings/search.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_settings_fold.php');

function settings_privacy_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The part of privacy.php from one card's id to the next row's assignment.
function settings_privacy_fold_test_card($id)
{
	$source = settings_privacy_fold_test_source('includes/settings/privacy.php');
	$start = strpos($source, '<div id="' . $id . '" class="pg-set-card">');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, '$privacy_parts[', $start);

	return substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
}

function test_settings_privacy_fold_order_and_names()
{
	pg_assert_same(array('analytics', 'cookies', 'legal', 'kvkk', 'a11y-statement', 'privacy-centre'), pg_privacy_fold_order(), 'the rows in the description\'s order');

	$names = array();

	foreach (pg_privacy_fold_order() as $key) {
		$face = pg_privacy_fold_face($key);
		$names[] = $face['name'];
		pg_assert_true(strpos($face['icon'], 'bi-') === 0, $key . ': an icon');
		pg_assert_false(stripos($face['name'], 'Enable') === 0, $key . ': a name never starts with Enable');
	}

	pg_assert_same(array(lang('Visitor tracking'), lang('Cookie consent'), lang('Legal documents'), lang('KVKK notice'), lang('Accessibility statement'), lang('Privacy centre')), $names, 'the rows\' names');
}

function test_settings_privacy_fold_lines()
{
	$on = pg_privacy_fold_tracking(true, false);
	pg_assert_same(lang('Visits are recorded'), $on['line'], 'tracking on');
	pg_assert_same(array(lang('On'), 'pg-tone-teal'), $on['pill'], 'an On pill');
	pg_assert_false($on['attention'], 'no row asks for attention');

	$off = pg_privacy_fold_tracking(false, true);
	pg_assert_same(lang('Visits are not recorded') . ' · ' . lang('Google Analytics connected'), $off['line'], 'tracking off, Google Analytics connected');
	pg_assert_same(array(lang('Off'), 'pg-tone-gray'), $off['pill'], 'an Off pill');

	pg_assert_same(lang('Visitors choose which cookies are set'), pg_privacy_fold_cookies(true)['line'], 'consent asked');
	pg_assert_same(lang('Every cookie is set without asking'), pg_privacy_fold_cookies(false)['line'], 'consent not asked');
	pg_assert_same(lang('Customers accept both documents at checkout'), pg_privacy_fold_legal(true)['line'], 'legal documents on');
	pg_assert_same(lang('The checkout asks for no document'), pg_privacy_fold_legal(false)['line'], 'legal documents off');

	$kvkk = pg_privacy_fold_kvkk(true, 'I agree.', true);
	pg_assert_same(lang('Notice page chosen') . ' · ' . lang('a consent box') . ' · ' . lang('used while Legal documents is on'), $kvkk['line'], 'page and box');
	pg_assert_same(array(lang('In use'), 'pg-tone-teal'), $kvkk['pill'], 'in use while Legal documents is on');

	$kvkk = pg_privacy_fold_kvkk(true, '  ', false);
	pg_assert_same(lang('Notice page chosen') . ' · ' . lang('no consent box') . ' · ' . lang('used while Legal documents is on'), $kvkk['line'], 'a blank sentence is no box');
	pg_assert_same(array(lang('Not used'), 'pg-tone-gray'), $kvkk['pill'], 'not used while Legal documents is off');

	pg_assert_same(array(lang('Not used'), 'pg-tone-gray'), pg_privacy_fold_kvkk(false, '', true)['pill'], 'nothing filled: not used');

	pg_assert_same(lang(array('string' => 'Dated {var:1}', 'vars' => '20.11.2026')), pg_privacy_fold_a11y('20.11.2026')['line'], 'the statement\'s date');
	pg_assert_same(lang('Not filled'), pg_privacy_fold_a11y('')['line'], 'no date');
	pg_assert_same(null, pg_privacy_fold_a11y('')['pill'], 'no pill');

	pg_assert_contains('KVKK or GDPR', pg_privacy_fold_centre()['line'], 'the Privacy centre\'s sentence is its line');
}

function test_settings_privacy_fold_pane_is_rows()
{
	$pane = settings_privacy_fold_test_source('includes/settings/privacy.php');

	pg_assert_contains('$pg_settings_cards[] = pg_settings_fold($privacy_rows);', $pane, 'the pane is one list of folded rows');
	pg_assert_same(1, substr_count($pane, '$pg_settings_cards[]'), 'no card outside the rows');
	pg_assert_same(0, substr_count($pane, 'popover'), 'no popover is left');
	pg_assert_same(0, substr_count($pane, 'collapse-switcher'), 'no switch hides fields');
	pg_assert_same(0, substr_count($pane, 'class="switch"'), 'no old switch drawing');
	pg_assert_same(4, substr_count($pane, 'class="pg-switch"'), 'Record visits, the daily summary, cookie consent and legal documents are pg-switch');
	pg_assert_same(0, substr_count($pane, '<details><details'), 'nothing more than one fold deep');

	foreach (array('Pay Per Click', 'Tracking Code Duration', 'Enable Visitor Tracking') as $old) {
		pg_assert_false(strpos($pane, "lang('" . $old) !== false, 'no "' . $old . '" label');
	}
}

function test_settings_privacy_fold_cards_and_fields()
{
	$tracking = settings_privacy_fold_test_card('pgset-analytics');
	pg_assert_contains("lang('Record visits')", $tracking, 'the switch is Record visits');
	pg_assert_contains("lang('Remember a tracking code for')", $tracking, 'the tracking code\'s days');
	pg_assert_contains("lang('Paid tracking code mark')", $tracking, 'the paid mark');
	pg_assert_contains('class="pg-card-inner connections-list"', $tracking, 'Google Analytics as a connect row');
	pg_assert_contains("lang('Connect in Connections')", $tracking, 'which leads to Connections');

	$cookies = settings_privacy_fold_test_card('pgset-cookies');
	$details = substr($cookies, (int) strpos($cookies, '<details>'));
	pg_assert_contains("lang('Your own scripts')", $details, 'the held scripts under Your own scripts');
	pg_assert_contains('data-pg-consent-name="Hotjar"', $details, 'the held script help');
	pg_assert_contains('Google Consent Mode v2', $details, 'the Google tags help');

	$legal = settings_privacy_fold_test_card('pgset-legal');
	pg_assert_contains('$legal_template_fields', $legal, 'the two templates');
	pg_assert_contains("lang('Fields you can use in a template')", $legal, 'the fields table under a details');
	pg_assert_false(strpos($legal, 'ecommerce_kvkk') !== false, 'the KVKK fields left Legal documents');

	$kvkk = settings_privacy_fold_test_card('pgset-kvkk');
	pg_assert_contains("pg_picker_field('page', 'ecommerce_kvkk_page_id'", $kvkk, 'KVKK Notice Page in its own row');
	pg_assert_contains('name="ecommerce_kvkk_consent_text"', $kvkk, 'Explicit Consent Sentence in its own row');

	$a11y = settings_privacy_fold_test_card('pgset-a11y-statement');
	pg_assert_contains('<a class="pg-button pg-button-quiet pg-button-sm mt-2" href="\' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . \'/view_accessibility.php?statement=1\')', $a11y, 'Print the statement is quiet');

	$centre = settings_privacy_fold_test_card('pgset-privacy-centre');
	pg_assert_contains("lang('Open the Privacy centre')", $centre, 'Open the Privacy centre');
	pg_assert_false(strpos($centre, 'KVKK or GDPR') !== false, 'its sentence stands once, as the row\'s line');
}

function test_settings_privacy_fold_names_stay()
{
	$pane = settings_privacy_fold_test_source('includes/settings/privacy.php');

	foreach (array('visitor_tracking', 'tracking_code_duration', 'pay_per_click_flag', 'visitor_retention_days', 'visitor_ip_days', 'visitor_summary', 'cookie_consent', 'cookie_consent_policy_url', 'ecommerce_legal_documents', 'ecommerce_kvkk_consent_text', 'a11y_statement_card') as $name) {
		pg_assert_contains('name="' . $name . '"', $pane, $name . ' posts under its name');
	}

	foreach (array('pgset-analytics', 'pgset-cookies', 'pgset-legal', 'pgset-kvkk', 'pgset-a11y-statement', 'pgset-privacy-centre') as $id) {
		pg_assert_contains('<div id="' . $id . '" class="pg-set-card">', $pane, $id . ' keeps its card inside its row');
	}

	pg_assert_false(strpos(settings_privacy_fold_test_source('includes/settings/privacy.save.php'), 'fold') !== false, 'the save knows nothing of rows');
}

function test_settings_privacy_fold_registry_and_search()
{
	$source = settings_privacy_fold_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        'privacy' => array(");
	$privacy = substr($source, $start, strpos($source, "\n        ),\n", $start) - $start);

	pg_assert_contains("'pgset-kvkk'      => lang('KVKK notice')", $privacy, 'the KVKK notice is a section');
	pg_assert_true(strpos($privacy, "'pgset-legal'     => lang(") < strpos($privacy, "'pgset-kvkk'      => lang(") && strpos($privacy, "'pgset-kvkk'      => lang(") < strpos($privacy, "'pgset-a11y-statement' => lang("), 'after Legal documents, before the statement');
	pg_assert_contains("lang('kvkk, kvkk notice,", $privacy, 'the search finds KVKK');
	pg_assert_contains('retention, ip address', $privacy, 'the search finds retention and IP address');

	// An old link still lands on the card, which sits in its row.
	$anchors = pg_settings_legacy_anchors();
	pg_assert_same(array('privacy', 'pgset-legal'), $anchors['pgset-legal'], 'an old Legal documents link');

	// Inside the rows the search index still reads each card's labels apart.
	$html = pg_settings_fold(array(
		array('key' => 'legal', 'name' => 'Legal documents', 'icon' => 'bi-file-earmark-text', 'tone' => '', 'line' => 'x', 'pill' => null, 'attention' => false,
			'html' => '<div id="pgset-legal" class="pg-set-card"><div class="card"><div class="card-body"><label for="a">Distance sales contract</label><textarea id="a"></textarea></div></div></div>'),
		array('key' => 'kvkk', 'name' => 'KVKK notice', 'icon' => 'bi-shield-lock', 'tone' => '', 'line' => 'y', 'pill' => null, 'attention' => false,
			'html' => '<div id="pgset-kvkk" class="pg-set-card"><div class="card"><div class="card-body"><label for="b">KVKK Notice Page</label><input id="b"></div></div></div>'),
	));
	$labels = pg_settings_card_labels($html, array('pgset-legal', 'pgset-kvkk'));

	pg_assert_same(array('Distance sales contract'), $labels['pgset-legal'], 'Legal documents\' labels, none of the KVKK row');
	pg_assert_same(array('KVKK Notice Page'), $labels['pgset-kvkk'], 'the KVKK row\'s label');
	pg_assert_contains('id="pg-fold-legal"', $html, 'the first row arrives open');
	pg_assert_contains('aria-expanded="true" aria-controls="pg-fold-legal-body"', $html, 'open');
}
