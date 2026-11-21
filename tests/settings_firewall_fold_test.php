<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Firewall and HTTPS as folded rows: each part's line of
 * state, the rows' order, no popover or old switch left in the rows, every
 * switch's fields drawn whatever its state, the Limits row, the bans drawer,
 * the two segmented controls, and the names that stay (card ids, field names,
 * the save, the Release form).
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/firewall_settings_fold.php');

function settings_firewall_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The part of firewall.php from one card's id to the next part's assignment.
function settings_firewall_fold_test_card($id)
{
	$source = settings_firewall_fold_test_source('includes/settings/firewall.php');
	$start = strpos($source, '<div id="' . $id . '" class="pg-set-card">');

	if ($start === false) {
		return '';
	}

	$ends = array();

	foreach (array("') + pg_firewall_fold_", '$pg_settings_cards[]') as $mark) {
		$at = strpos($source, $mark, $start);

		if ($at !== false) {
			$ends[] = $at;
		}
	}

	return $ends ? substr($source, $start, min($ends) - $start) : substr($source, $start);
}

// The rows' part of firewall.php: everything before the HTTPS card.
function settings_firewall_fold_test_rows()
{
	$source = settings_firewall_fold_test_source('includes/settings/firewall.php');

	return substr($source, 0, (int) strpos($source, '// ── HTTPS and Security Headers ──'));
}

function test_settings_firewall_fold_order_and_names()
{
	pg_assert_same(array('waf', 'limits', 'bots', 'iplists', 'countries', 'proxies'), pg_firewall_fold_order(), 'the rows in the description\'s order');

	$names = array();

	foreach (pg_firewall_fold_order() as $key) {
		$face = pg_firewall_fold_face($key);
		$names[] = $face['name'];
		pg_assert_true(strpos($face['icon'], 'bi-') === 0, $key . ': an icon');
		pg_assert_false(stripos($face['name'], 'Enable') === 0, $key . ': a name never starts with Enable');
	}

	pg_assert_same(array(lang('Firewall'), lang('Limits'), lang('Bots'), lang('Addresses'), lang('Countries'), lang('Trusted proxies')), $names, 'the rows\' names');
}

function test_settings_firewall_fold_lines()
{
	$waf = pg_firewall_fold_waf(true, true, 'monitor', false);
	pg_assert_same(lang('On, Monitor'), $waf['line'], 'on in Monitor mode');
	pg_assert_same(array(lang('On'), 'pg-tone-teal'), $waf['pill'], 'an On pill');
	pg_assert_false($waf['attention'], 'nothing to look at');

	pg_assert_same(lang('On, Block'), pg_firewall_fold_waf(true, true, 'block', false)['line'], 'on in Block mode');

	$waf = pg_firewall_fold_waf(true, false, 'block', true);
	pg_assert_same(lang('Off') . ' · ' . lang('your own address is banned'), $waf['line'], 'off, the operator banned');
	pg_assert_same(array(lang('Off'), 'pg-tone-gray'), $waf['pill'], 'an Off pill');
	pg_assert_true($waf['attention'], 'a banned own address opens the row');
	pg_assert_true(pg_firewall_fold_waf(false, false, 'monitor', false)['attention'], 'no schema: the row asks for attention');

	pg_assert_same(lang(array('string' => '{var:1} requests a minute from one address', 'vars' => 300)) . ' · ' . lang(array('string' => 'banned after {var:1} events', 'vars' => 5)), pg_firewall_fold_limits(true, 300, true, 5)['line'], 'both limits on');
	pg_assert_same(lang('No rate limit') . ' · ' . lang('no automatic bans'), pg_firewall_fold_limits(false, 300, false, 5)['line'], 'both off');

	pg_assert_same(lang('Unknown bots are refused'), pg_firewall_fold_bots(true, 0, 0)['line'], 'unknown bots refused');
	pg_assert_same(lang('Unknown bots are let in') . ' · ' . lang(array('string' => '{var:1} allowed by you', 'vars' => 2)) . ' · ' . lang(array('string' => '{var:1} blocked by you', 'vars' => 1)), pg_firewall_fold_bots(false, 2, 1)['line'], 'let in, with the operator\'s lists');

	pg_assert_same(lang('No address is banned by the firewall now'), pg_firewall_fold_bans_sentence(0), 'no ban');
	pg_assert_same(lang('1 address banned now'), pg_firewall_fold_bans_sentence(1), 'one ban');
	pg_assert_same(lang(array('string' => '{var:1} addresses banned now', 'vars' => 2)), pg_firewall_fold_bans_sentence(2), 'two bans');
	pg_assert_same(lang(array('string' => '{var:1} addresses banned now', 'vars' => 2)) . ' · ' . lang(array('string' => '{var:1} banned by hand', 'vars' => 3)), pg_firewall_fold_addresses(0, 3, 2)['line'], 'the bans, then the hand-written list');

	pg_assert_same(lang('Every country is let in'), pg_firewall_fold_countries(true, 'off', 3, false)['line'], 'no restriction');
	pg_assert_same(array(lang('Off'), 'pg-tone-gray'), pg_firewall_fold_countries(true, 'off', 3, false)['pill'], 'off');
	pg_assert_same(lang('Refuses 1 country'), pg_firewall_fold_countries(true, 'block', 1, false)['line'], 'one refused');
	$countries = pg_firewall_fold_countries(true, 'allow', 4, true);
	pg_assert_same(lang(array('string' => 'Lets in only {var:1} countries', 'vars' => 4)) . ' · ' . lang('yours is refused'), $countries['line'], 'only four, the operator\'s refused');
	pg_assert_true($countries['attention'], 'the operator\'s own country refused opens the row');
	pg_assert_same(lang('Arrives with the 2026.4.10 upgrade'), pg_firewall_fold_countries(false, 'off', 0, false)['line'], 'before the upgrade');

	pg_assert_same(lang('None listed; Cloudflare is recognised on its own'), pg_firewall_fold_proxies(0, false)['line'], 'none listed');
	$proxies = pg_firewall_fold_proxies(2, true);
	pg_assert_same(lang(array('string' => '{var:1} proxies listed', 'vars' => 2)) . ' · ' . lang('a proxy is not listed'), $proxies['line'], 'two, and one waiting');
	pg_assert_true($proxies['attention'], 'an unlisted proxy opens the row');
}

function test_settings_firewall_fold_pane_is_rows()
{
	$pane = settings_firewall_fold_test_source('includes/settings/firewall.php');
	$rows = settings_firewall_fold_test_rows();

	pg_assert_contains('$pg_settings_cards[] = pg_settings_fold($firewall_rows);', $pane, 'the firewall parts are one list of folded rows');
	pg_assert_same(2, substr_count($pane, '$pg_settings_cards[]'), 'the rows, then the HTTPS card');
	pg_assert_same(0, substr_count($rows, 'popover'), 'no popover is left in the rows');
	pg_assert_same(0, substr_count($rows, 'collapse-switcher'), 'no switch hides fields');
	pg_assert_same(0, substr_count($rows, 'class="switch"'), 'no old switch drawing');
	pg_assert_same(0, substr_count($rows, 'class="radio"'), 'no plain radio');
	pg_assert_same(2, substr_count($rows, '<div class="pg-seg flex-wrap">'), 'Mode and Restriction are segmented controls');

	foreach (array('Enable Firewall', 'Rate Limiting', 'Automatic Temporary Bans', 'Block Unknown Bots', 'Bot Filtering', 'IP Lists', 'Country Restriction') as $old) {
		pg_assert_false(strpos($rows, "lang('" . $old . "')") !== false, 'no "' . $old . '" label');
	}
}

function test_settings_firewall_fold_cards_and_fields()
{
	$waf = settings_firewall_fold_test_card('pgset-waf');
	pg_assert_contains("<legend class=\"form-label pg-field-label\">' . lang('Mode')", $waf, 'Mode keeps its legend');
	pg_assert_contains('$output_waf_ai_state', $waf, 'the AI crawlers\' state line');
	pg_assert_false(strpos($waf, 'waf_rate_limit') !== false, 'Rate limiting left the Firewall row');
	pg_assert_false(strpos($waf, 'waf_auto_ban') !== false, 'the bans left the Firewall row');

	$limits = settings_firewall_fold_test_card('pgset-limits');

	foreach (array('waf_rate_limit', 'waf_rate_limit_requests', 'waf_rate_limit_sensitive', 'waf_rate_limit_api', 'waf_inflight_limit', 'waf_auto_ban', 'waf_auto_ban_threshold', 'waf_auto_ban_minutes', 'waf_auto_ban_max_minutes') as $name) {
		pg_assert_contains('name="' . $name . '"', $limits, $name . ' in Limits');
	}

	$bots = settings_firewall_fold_test_card('pgset-bots');
	pg_assert_contains('name="allowed_bots"', $bots, 'Allowed bots drawn under the switch');
	pg_assert_contains('name="waf_blocked_agents"', $bots, 'Blocked bots drawn under the switch');

	$addresses = settings_firewall_fold_test_card('pgset-iplists');
	pg_assert_contains('pg_firewall_fold_bans_sentence($waf_auto_ban_count)', $addresses, 'the bans\' state line');
	pg_assert_contains('data-bs-toggle="drawer" data-bs-target="#waf_bans_drawer"', $addresses, 'See the bans opens the drawer');
	pg_assert_false(strpos($addresses, '$output_auto_bans . \'</tbody>') !== false, 'the table left the row');

	$pane = settings_firewall_fold_test_source('includes/settings/firewall.php');
	pg_assert_contains("pg_settings_drawer('waf_bans_drawer', h(lang('Active automatic bans'))", $pane, 'the bans in a drawer');
	pg_assert_contains('$output_auto_bans . \'</tbody>', $pane, 'with today\'s table');

	$countries = settings_firewall_fold_test_card('pgset-countries');
	pg_assert_contains('$output_waf_country', $countries, 'the restriction\'s fields');
	pg_assert_contains('<label class="pg-seg-item" for="waf_country_mode_', $pane, 'Restriction is a segmented control');

	pg_assert_contains('name="waf_trusted_proxies"', settings_firewall_fold_test_card('pgset-proxies'), 'Trusted proxies');
}

function test_settings_firewall_fold_names_stay()
{
	$pane = settings_firewall_fold_test_source('includes/settings/firewall.php');

	foreach (array('waf_enabled', 'waf_mode', 'waf_sensitivity', 'waf_log_retention_days', 'waf_log_max_rows', 'waf_signature_scan', 'waf_block_attack_tools', 'waf_verify_bots', 'waf_exclusions', 'block_unknown_bots', 'allowed_bots', 'waf_blocked_agents', 'allowed_ip_addresses', 'banned_ip_addresses', 'waf_country_mode', 'waf_country_list', 'waf_country_unknown', 'waf_trusted_proxies', 'waf_trust_unlisted_proxy', 'secure_mode') as $name) {
		pg_assert_contains('name="' . $name . '"', $pane, $name . ' posts under its name');
	}

	foreach (array('pgset-waf', 'pgset-limits', 'pgset-bots', 'pgset-iplists', 'pgset-countries', 'pgset-proxies', 'pgset-headers') as $id) {
		pg_assert_contains('<div id="' . $id . '" class="pg-set-card">', $pane, $id . ' keeps its card');
	}

	pg_assert_contains('id="pgset_waf_release"', $pane, 'the Release form stays');
	pg_assert_contains('action="view_waf_log.php"', $pane, 'and posts to the firewall log');
	pg_assert_false(strpos(settings_firewall_fold_test_source('includes/settings/firewall.save.php'), 'pg_settings_fold') !== false, 'the save knows nothing of rows');
}

function test_settings_firewall_fold_registry_and_anchors()
{
	$source = settings_firewall_fold_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        'firewall' => array(");
	$firewall = substr($source, $start, strpos($source, "\n        ),\n", $start) - $start);

	pg_assert_contains("'pgset-limits'  => lang('Limits')", $firewall, 'Limits is a section');
	pg_assert_contains("lang('rate limit, requests per minute, ban,", $firewall, 'the search finds rate limit and ban in Limits');
	pg_assert_contains('fail2ban', $firewall, 'and fail2ban');
	pg_assert_contains('hsts', $firewall, 'and HSTS');

	// Old links land on the cards; the client opens the row a card sits in.
	$anchors = pg_settings_legacy_anchors();
	pg_assert_same(array('firewall', 'pgset-waf'), $anchors['pgset-waf'], 'an old Firewall link');
	pg_assert_same(array('firewall', 'pgset-bots'), $anchors['pgsub-bots'], 'an old bots link');
	pg_assert_same(array('firewall', 'pgset-iplists'), $anchors['pgsub-iplists'], 'an old IP lists link');
	pg_assert_contains('window.pgSettingsFoldReveal(section);', settings_firewall_fold_test_source('assets/js/backend.src.js'), 'an anchor opens its row');

	$html = pg_settings_fold(array(
		array('key' => 'waf', 'name' => 'Firewall', 'icon' => 'bi-shield-check', 'tone' => '', 'line' => 'x', 'pill' => null, 'attention' => false,
			'html' => '<div id="pgset-waf" class="pg-set-card"><div class="card"><div class="card-body"><label for="a">Sensitivity</label><select id="a"></select></div></div></div>'),
		array('key' => 'limits', 'name' => 'Limits', 'icon' => 'bi-speedometer2', 'tone' => '', 'line' => 'y', 'pill' => null, 'attention' => true,
			'html' => '<div id="pgset-limits" class="pg-set-card"><div class="card"><div class="card-body"><label for="b">All Requests</label><input id="b"></div></div></div>'),
	));
	$labels = pg_settings_card_labels($html, array('pgset-waf', 'pgset-limits'));

	pg_assert_same(array('Sensitivity'), $labels['pgset-waf'], 'the Firewall row\'s labels only');
	pg_assert_same(array('All Requests'), $labels['pgset-limits'], 'the Limits row\'s label');
	pg_assert_contains('aria-expanded="true" aria-controls="pg-fold-limits-body"', $html, 'the row that asks for attention arrives open');
}
