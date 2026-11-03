<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Web Designer Portfolio design template's parts
 * (includes/design_templates/pinegrap-portfolio/): the designer's data
 * hangs together (services and sample case studies naming a known kind of
 * project, testimonials about a known case study, packages with what they
 * hold, the brief form's pick lists without an answer twice), every page
 * link of the shared parts names a page of the finished template, the
 * shared parts carry no class of another template, a project card reads
 * the Projects form's fields and a package card opens the brief with the
 * package chosen.
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
function _test_pd_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-portfolio';
	include $dir . '/parts.php';
	$project_cards = array();
	foreach (array_keys($projects) as $key) $project_cards[$key] = $project_card($key);
	$package_cards = array();
	foreach (array_keys($packages) as $key) $package_cards[$key] = $package_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'designer'          => $designer,
		'categories'        => $categories,
		'services'          => $services,
		'packages'          => $packages,
		'process'           => $process,
		'skills'            => $skills,
		'tools'             => $tools,
		'experience'        => $experience,
		'figures'           => $figures,
		'clients'           => $clients,
		'testimonials'      => $testimonials,
		'projects'          => $projects,
		'category_titles'   => $category_titles,
		'package_choices'   => $package_choices,
		'budget_choices'    => $budget_choices,
		'start_choices'     => $start_choices,
		'page_counts'       => $page_count_choices,
		'brand_choices'     => $brand_choices,
		'contact_choices'   => $contact_choices,
		'brief_statuses'    => $brief_statuses,
		'project_cards'     => $project_cards,
		'package_cards'     => $package_cards,
		'testimonial_cards' => $testimonial_cards,
		'shared'            => array($account_bar_tree(), $header(), $footer(), $brief_band(), $notice(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_pd_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_pd_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_pd_page_keys()
{
	return array(
		'home', 'services', 'process', 'about', 'faq',
		'project_new', 'work', 'project',
		'brief', 'brief_view', 'my_briefs', 'staff_briefs', 'privacy',
		'email_brief_new', 'email_brief_received', 'email_reply',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

function test_portfolio_data_hangs_together()
{
	$d = _test_pd_load();
	foreach (array('name', 'role', 'city', 'bio', 'available') as $f) pg_assert_true(trim($d['designer'][$f]) !== '', "designer: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['designer']['phone']), 'designer: phone number');
	pg_assert_true(strpos($d['designer']['email'], '@example.com') !== false, 'designer: a sample address');
	pg_assert_same(5, count($d['categories']), 'five kinds of project');
	foreach ($d['categories'] as $key => $c) {
		pg_assert_true((bool)preg_match('/^[a-z]+$/', $key), "category $key: key");
		pg_assert_true(strpos($c['icon'], 'bi-') === 0, "category $key: icon");
		pg_assert_true(trim($c['title']) !== '' && trim($c['text']) !== '', "category $key: words");
	}
	foreach ($d['services'] as $key => $s) {
		pg_assert_true(isset($d['categories'][$s['category']]), "service $key: known kind of project");
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
	pg_assert_same(6, count($d['projects']), 'six sample case studies');
	$kinds = array();
	foreach ($d['projects'] as $key => $p) {
		pg_assert_true(isset($d['categories'][$p['category']]), "project $key: known kind of project");
		pg_assert_true(in_array($p['client'], $d['clients'], true), "project $key: its client is in the client row");
		pg_assert_true(is_int($p['year']) && $p['year'] >= 2000 && $p['year'] <= 2100, "project $key: year is a number");
		foreach (array('title', 'industry', 'role', 'summary', 'challenge', 'approach', 'results', 'tools') as $f) {
			pg_assert_true(trim($p[$f]) !== '', "project $key: $f");
		}
		pg_assert_true($p['url'] === '' || strpos($p['url'], 'https://') === 0, "project $key: live address");
		$kinds[$p['category']] = true;
	}
	pg_assert_same(count($d['categories']), count($kinds), 'a sample of every kind of project');
	// The home shows the first three: newest first, so the years never rise.
	$years = array_column(array_values($d['projects']), 'year');
	$sorted = $years;
	rsort($sorted);
	pg_assert_same($sorted, $years, 'samples newest first');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['projects'][$t['project']]), "testimonial $n: about a sample case study");
		pg_assert_true(strpos($t['role'], $d['projects'][$t['project']]['client']) !== false, "testimonial $n: names the client");
	}
	// The pick lists: one answer per kind and package (plus "not sure"),
	// no answer twice; the states start with Received.
	pg_assert_same(count($d['categories']), count($d['category_titles']), 'an answer per kind of project');
	pg_assert_same(count($d['packages']) + 1, count($d['package_choices']), 'an answer per package');
	foreach (array('category_titles', 'package_choices', 'budget_choices', 'start_choices', 'page_counts', 'brief_statuses') as $list) {
		pg_assert_same($d[$list], array_values(array_unique($d[$list])), "$list unique");
	}
	foreach (array('brand_choices', 'contact_choices') as $list) {
		foreach ($d[$list] as $o) pg_assert_same($o[0], $o[1], "$list: the value is the answer's own words");
	}
	pg_assert_same(5, count($d['brief_statuses']), 'five states of a brief');
}

function test_portfolio_shared_parts_link_template_pages()
{
	$d = _test_pd_load();
	$known = array_flip(_test_pd_page_keys());
	$nodes = array();
	foreach (array_merge($d['shared'], array_values($d['project_cards']), array_values($d['package_cards']), $d['testimonial_cards']) as $tree) _test_pd_nodes($tree, $nodes);
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
		pg_assert_false((bool)preg_match('/\b(plw|psl|pre|pco|pev|pcl|pf)-[a-z]/', $class), 'no class of another template: ' . $class);
		$id = isset($node['props']['id']) ? (string)$node['props']['id'] : '';
		pg_assert_false((bool)preg_match('/^(lw|re)-/', $id), 'no field id of another template: ' . $id);
	}
	pg_assert_true($links > 20, 'the shared parts link the pages');
}

// The footer's work links open the work list filtered on each kind of
// project, in the words the Category list uses (the list reads ?category=).
function test_portfolio_footer_filters_the_work_list()
{
	$d = _test_pd_load();
	$nodes = array();
	_test_pd_nodes($d['shared'][2], $nodes);
	$seen = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], '{{page:work}}?') !== 0) continue;
		parse_str(substr($node['props']['href'], strlen('{{page:work}}?')), $q);
		pg_assert_true(in_array($q['category'], $d['category_titles'], true), 'the link names a Category answer');
		$seen[$q['category']] = true;
	}
	pg_assert_same(count($d['categories']), count($seen), 'a link per kind of project');
}

// A project card reads every value from the Projects form's fields, with
// the sample's own words on the canvas.
function test_portfolio_project_card_reads_the_project()
{
	$d = _test_pd_load();
	foreach ($d['project_cards'] as $key => $card) {
		$nodes = array();
		_test_pd_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
		}
		foreach (array('cover', 'title', 'form_item_view', 'category', 'year', 'client', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "project $key: card reads $t");
		}
		$p = $d['projects'][$key];
		pg_assert_true(in_array($p['title'], $texts, true), "project $key: sample title on the canvas");
		pg_assert_true(in_array($p['client'], $texts, true), "project $key: sample client on the canvas");
		pg_assert_true(in_array($d['categories'][$p['category']]['title'], $texts, true), "project $key: sample kind on the canvas");
	}
}

// A package card opens the brief with that package chosen, in the words the
// Package list uses (the form starts from ?package=).
function test_portfolio_package_card_chooses_the_package()
{
	$d = _test_pd_load();
	foreach ($d['package_cards'] as $key => $card) {
		$nodes = array();
		_test_pd_nodes($card, $nodes);
		$hrefs = array();
		foreach ($nodes as $node) {
			if (isset($node['props']['href']) && strpos($node['props']['href'], '{{page:brief}}?') === 0) $hrefs[] = $node['props']['href'];
		}
		pg_assert_same(1, count($hrefs), "package $key: one brief link");
		parse_str(substr($hrefs[0], strlen('{{page:brief}}?')), $q);
		pg_assert_true(in_array($q['package'], $d['package_choices'], true), "package $key: the link names a Package answer");
	}
}
