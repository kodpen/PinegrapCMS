<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Mobile App Developer design template's parts
 * (includes/design_templates/pinegrap-appstudio/): the developer's data
 * hangs together (sample apps naming a known kind and platform, the store
 * addresses following the platforms, release notes about a known app with
 * the app's version on the newest one, testimonials about a known app,
 * packages with what they hold, the forms' pick lists without an answer
 * twice), every page link of the shared parts names a page of the finished
 * template, the shared parts carry no class of another template, an app
 * card and a release note read their forms' fields, the store buttons leave
 * with an empty address and a package card opens the project request with
 * the package chosen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts as the template file reads them: parts.php first, then each
// page file in the same scope. Read once per run.
function _test_ma_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-appstudio';
	include $dir . '/parts.php';
	$app_cards = array();
	$store_rows = array();
	foreach (array_keys($apps) as $key) {
		$app_cards[$key] = $app_card($key);
		$store_rows[$key] = $store_buttons($key);
	}
	$release_cards = array();
	foreach (array_keys($releases) as $n) $release_cards[] = $release_card($n);
	$package_cards = array();
	foreach (array_keys($packages) as $key) $package_cards[$key] = $package_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'developer'         => $developer,
		'categories'        => $categories,
		'platforms'         => $platforms,
		'platform_choices'  => $platform_choices,
		'price_models'      => $price_models,
		'apps'              => $apps,
		'releases'          => $releases,
		'project_types'     => $project_types,
		'services'          => $services,
		'packages'          => $packages,
		'process'           => $process,
		'skills'            => $skills,
		'tools'             => $tools,
		'experience'        => $experience,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'category_titles'   => $category_titles,
		'app_names'         => $app_names,
		'support_apps'      => $support_app_choices,
		'ticket_kinds'      => $ticket_kinds,
		'ticket_statuses'   => $ticket_statuses,
		'project_choices'   => $project_type_choices,
		'package_choices'   => $package_choices,
		'budget_choices'    => $budget_choices,
		'start_choices'     => $start_choices,
		'design_choices'    => $design_choices,
		'contact_choices'   => $contact_choices,
		'project_statuses'  => $project_statuses,
		'app_cards'         => $app_cards,
		'store_rows'        => $store_rows,
		'release_cards'     => $release_cards,
		'package_cards'     => $package_cards,
		'testimonial_cards' => $testimonial_cards,
		'shared'            => array($account_bar_tree(), $header(), $footer(), $project_band(), $notice(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_ma_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ma_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_ma_page_keys()
{
	return array(
		'home', 'services', 'process', 'about', 'faq', 'press',
		'app_new', 'apps', 'app',
		'release_new', 'releases', 'beta', 'beta_view', 'staff_beta',
		'support', 'support_view', 'my_tickets', 'staff_tickets', 'privacy', 'email_reply',
		'hire', 'project_view', 'my_projects', 'staff_projects',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

function test_appstudio_data_hangs_together()
{
	$d = _test_ma_load();
	foreach (array('name', 'role', 'city', 'bio', 'available') as $f) pg_assert_true(trim($d['developer'][$f]) !== '', "developer: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['developer']['phone']), 'developer: phone number');
	pg_assert_true(strpos($d['developer']['email'], '@example.com') !== false, 'developer: a sample address');
	pg_assert_same(5, count($d['categories']), 'five kinds of app');
	foreach ($d['categories'] as $key => $c) {
		pg_assert_true((bool)preg_match('/^[a-z]+$/', $key), "category $key: key");
		pg_assert_true(strpos($c['icon'], 'bi-') === 0, "category $key: icon");
		pg_assert_true(trim($c['title']) !== '' && trim($c['text']) !== '', "category $key: words");
	}
	pg_assert_same(5, count($d['apps']), 'five sample apps');
	$kinds = array();
	foreach ($d['apps'] as $key => $a) {
		pg_assert_true(isset($d['categories'][$a['category']]), "app $key: known kind of app");
		pg_assert_true(in_array($a['platforms'], array('ios', 'android', 'both'), true), "app $key: known platforms");
		pg_assert_true(isset($d['price_models'][$a['price']]), "app $key: known price model");
		pg_assert_true((bool)preg_match('/^\d+\.\d+\.\d+$/', $a['version']), "app $key: version x.y.z");
		pg_assert_true(is_float($a['rating']) && $a['rating'] >= 1 && $a['rating'] <= 5, "app $key: rating is a number of stars");
		foreach (array('name', 'tagline', 'summary', 'description', 'privacy') as $f) pg_assert_true(trim($a[$f]) !== '', "app $key: $f");
		pg_assert_true(count($a['features']) >= 3, "app $key: features");
		$kinds[$a['category']] = true;
	}
	pg_assert_same(count($d['categories']), count($kinds), 'a sample of every kind of app');
	// Release notes: newest first, each about a sample app, the newest of
	// each app on the app's own version, and every app has one.
	$dates = array_column($d['releases'], 'date');
	$sorted = $dates;
	rsort($sorted);
	pg_assert_same($sorted, $dates, 'release notes newest first');
	$newest = array();
	foreach ($d['releases'] as $n => $r) {
		pg_assert_true(isset($d['apps'][$r['app']]), "release $n: about a sample app");
		pg_assert_true((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $r['date']), "release $n: a date");
		$app = $d['apps'][$r['app']];
		pg_assert_true($r['platform'] === $app['platforms'] || $app['platforms'] === 'both', "release $n: a platform of the app");
		if (!isset($newest[$r['app']])) $newest[$r['app']] = $r['version'];
		else pg_assert_true(version_compare($r['version'], $newest[$r['app']], '<'), "release $n: older notes carry older versions");
	}
	foreach ($d['apps'] as $key => $a) {
		pg_assert_true(isset($newest[$key]), "app $key: has a release note");
		pg_assert_same($a['version'], $newest[$key], "app $key: version is its newest note's");
	}
	foreach ($d['services'] as $key => $s) {
		pg_assert_true(isset($d['project_types'][$s['type']]), "service $key: known kind of project");
		pg_assert_true(count($s['points']) >= 3, "service $key: points");
	}
	pg_assert_same(3, count($d['packages']), 'three packages');
	$featured = 0;
	foreach ($d['packages'] as $key => $p) {
		pg_assert_true(count($p['points']) >= 3, "package $key: points");
		pg_assert_true((bool)preg_match('/\d/', $p['price']), "package $key: a price");
		if ($p['featured']) $featured++;
	}
	pg_assert_same(1, $featured, 'one package marked most chosen');
	pg_assert_same(4, count($d['process']), 'four steps of the process');
	pg_assert_same(4, count($d['figures']), 'four figures');
	pg_assert_true(count($d['skills']) >= 6 && count($d['tools']) >= 4, 'skills and tools');
	pg_assert_same(3, count($d['experience']), 'three jobs');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['apps'][$t['app']]), "testimonial $n: about a sample app");
		pg_assert_true(strpos($t['role'], $d['apps'][$t['app']]['name']) !== false, "testimonial $n: names the app");
	}
	// The pick lists: one answer per kind, platform, app and package (plus
	// "not about one app", "not sure"), no answer twice.
	pg_assert_same(count($d['categories']), count($d['category_titles']), 'an answer per kind of app');
	pg_assert_same(count($d['platforms']) + 1, count($d['platform_choices']), 'an answer per platform and both');
	pg_assert_same(count($d['apps']), count($d['app_names']), 'an answer per app');
	pg_assert_same(count($d['apps']) + 1, count($d['support_apps']), 'support: an answer per app and one for none');
	pg_assert_same(count($d['project_types']), count($d['project_choices']), 'an answer per kind of project');
	pg_assert_same(count($d['packages']) + 1, count($d['package_choices']), 'an answer per package');
	foreach (array('category_titles', 'platform_choices', 'price_models', 'app_names', 'support_apps', 'ticket_kinds', 'ticket_statuses',
	               'project_choices', 'package_choices', 'budget_choices', 'start_choices', 'project_statuses') as $list) {
		pg_assert_same($d[$list], array_values(array_unique($d[$list])), "$list unique");
	}
	foreach (array('design_choices', 'contact_choices') as $list) {
		foreach ($d[$list] as $o) pg_assert_same($o[0], $o[1], "$list: the value is the answer's own words");
	}
	pg_assert_same(5, count($d['ticket_statuses']), 'five states of a support request');
	pg_assert_same(5, count($d['project_statuses']), 'five states of a project request');
}

// "iOS and Android" must contain each platform's own name: the app list
// filters ?platform= with "contains", so an app on both shows under each.
function test_appstudio_platform_filter_contains_each_platform()
{
	$d = _test_ma_load();
	$both = $d['platform_choices'][2];
	foreach ($d['platforms'] as $key => $p) {
		pg_assert_true(strpos($both, $p['title']) !== false, "the answer for both platforms contains $key");
	}
}

function test_appstudio_shared_parts_link_template_pages()
{
	$d = _test_ma_load();
	$known = array_flip(_test_ma_page_keys());
	$nodes = array();
	foreach (array_merge($d['shared'], array_values($d['app_cards']), array_values($d['store_rows']), $d['release_cards'],
	                     array_values($d['package_cards']), $d['testimonial_cards']) as $tree) _test_ma_nodes($tree, $nodes);
	$links = 0;
	foreach ($nodes as $node) {
		foreach (array('href', 'text') as $prop) {
			if (!isset($node['props'][$prop]) || !is_string($node['props'][$prop])) continue;
			preg_match_all('/\{\{page:([a-z0-9_]+)\}\}/', $node['props'][$prop], $m);
			foreach ($m[1] as $key) {
				$links++;
				pg_assert_true(isset($known[$key]), "link to a page of the template: $key");
			}
		}
		$class = isset($node['props']['cssClass']) ? (string)$node['props']['cssClass'] : '';
		pg_assert_false((bool)preg_match('/\b(plw|psl|pre|pco|pev|pcl|pf|pdp|pm)-[a-z]/', $class), 'no class of another template: ' . $class);
		$id = isset($node['props']['id']) ? (string)$node['props']['id'] : '';
		pg_assert_false((bool)preg_match('/^(lw|re|pd)-/', $id), 'no field id of another template: ' . $id);
	}
	pg_assert_true($links > 20, 'the shared parts link the pages');
}

// The footer's app links open the app list filtered on each kind of app
// and each platform, in the words the Category and Platforms lists use.
function test_appstudio_footer_filters_the_app_list()
{
	$d = _test_ma_load();
	$nodes = array();
	_test_ma_nodes($d['shared'][2], $nodes);
	$seen = array('category' => array(), 'platform' => array());
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], '{{page:apps}}?') !== 0) continue;
		parse_str(substr($node['props']['href'], strlen('{{page:apps}}?')), $q);
		if (isset($q['category'])) {
			pg_assert_true(in_array($q['category'], $d['category_titles'], true), 'the link names a Category answer');
			$seen['category'][$q['category']] = true;
		} else {
			pg_assert_true(in_array($q['platform'], $d['platform_choices'], true), 'the link names a Platforms answer');
			$seen['platform'][$q['platform']] = true;
		}
	}
	pg_assert_same(count($d['categories']), count($seen['category']), 'a link per kind of app');
	pg_assert_same(count($d['platforms']), count($seen['platform']), 'a link per platform');
}

// An app card reads every value from the Apps form's fields, with the
// sample's own words on the canvas.
function test_appstudio_app_card_reads_the_app()
{
	$d = _test_ma_load();
	foreach ($d['app_cards'] as $key => $card) {
		$nodes = array();
		_test_ma_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
		}
		foreach (array('icon', 'name', 'form_item_view', 'tagline', 'summary', 'category', 'platforms', 'rating', 'price_model') as $t) {
			pg_assert_true(isset($tokens[$t]), "app $key: card reads $t");
		}
		$a = $d['apps'][$key];
		pg_assert_true(in_array($a['name'], $texts, true), "app $key: sample name on the canvas");
		pg_assert_true(in_array($d['categories'][$a['category']]['title'], $texts, true), "app $key: sample kind on the canvas");
		pg_assert_true(in_array($d['price_models'][$a['price']], $texts, true), "app $key: sample price model on the canvas");
	}
}

// A store button is bound to its address and judged by it: the address is
// the hidden value of a box that leaves when it is empty; the sample shows
// a button only for the stores the app is in.
function test_appstudio_store_buttons_follow_the_address()
{
	$d = _test_ma_load();
	foreach ($d['store_rows'] as $key => $row) {
		$boxes = 0;
		$shown = array();
		foreach ($row['children'] as $box) {
			$boxes++;
			pg_assert_true(strpos($box['props']['cssClass'], 'pg-hide-if-empty') !== false, "app $key: the box leaves when empty");
			$value = $box['children'][0];
			$button = $box['children'][1];
			pg_assert_true(strpos($value['props']['cssClass'], 'pg-field-value') !== false, "app $key: the box is judged by its value");
			pg_assert_same($value['props']['_bindings']['text'], $button['props']['_bindings']['href'], "app $key: value and button read one field");
			pg_assert_same('_blank', $button['props']['target'], "app $key: the store opens in a new window");
			if ($value['props']['text'] !== '') $shown[] = $value['props']['_bindings']['text'];
		}
		pg_assert_same(2, $boxes, "app $key: two stores");
		$p = $d['apps'][$key]['platforms'];
		$want = array();
		if ($p !== 'android') $want[] = 'app_store_url';
		if ($p !== 'ios') $want[] = 'google_play_url';
		pg_assert_same($want, $shown, "app $key: sample buttons follow the platforms");
	}
}

// A release note reads the Releases form's fields, with the sample's own
// app name and version on the canvas.
function test_appstudio_release_card_reads_the_note()
{
	$d = _test_ma_load();
	foreach ($d['release_cards'] as $n => $card) {
		$nodes = array();
		_test_ma_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
		}
		foreach (array('app', 'version', 'platform', 'release_date', 'notes') as $t) pg_assert_true(isset($tokens[$t]), "release $n: reads $t");
		$r = $d['releases'][$n];
		pg_assert_true(in_array($d['apps'][$r['app']]['name'], $texts, true), "release $n: sample app on the canvas");
		pg_assert_true(in_array($r['version'], $texts, true), "release $n: sample version on the canvas");
	}
}

// A package card opens the project request with that package chosen, in
// the words the Package list uses (the form starts from ?package=).
function test_appstudio_package_card_chooses_the_package()
{
	$d = _test_ma_load();
	foreach ($d['package_cards'] as $key => $card) {
		$nodes = array();
		_test_ma_nodes($card, $nodes);
		$hrefs = array();
		foreach ($nodes as $node) {
			if (isset($node['props']['href']) && strpos($node['props']['href'], '{{page:hire}}?') === 0) $hrefs[] = $node['props']['href'];
		}
		pg_assert_same(1, count($hrefs), "package $key: one project link");
		parse_str(substr($hrefs[0], strlen('{{page:hire}}?')), $q);
		pg_assert_true(in_array($q['package'], $d['package_choices'], true), "package $key: the link names a Package answer");
	}
}
