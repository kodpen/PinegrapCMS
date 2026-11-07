<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Construction Firm design template's parts
 * (includes/design_templates/pinegrap-construction/): the firm's data
 * hangs together (sample projects naming a known sector, service and
 * status, a project in every sector, a team, job openings in a known
 * department and type, testimonials about sample projects, the forms' pick
 * lists without an answer twice), every page link of the shared parts
 * names a page of the finished template, the shared parts carry no cart,
 * the footer opens the project list filtered and links every service's
 * page, the cards read their form's fields, the classes are the
 * template's own, the sample carries no fixed date and every literal has
 * its Turkish.
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
function _test_cn_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-construction/parts.php';
	$project_cards = array();
	foreach (array_keys($projects) as $key) $project_cards[$key] = $project_card($key);
	$service_cards = array();
	foreach (array_keys($services) as $key) $service_cards[$key] = $service_card($key);
	$job_cards = array();
	foreach (array_keys($jobs) as $key) $job_cards[$key] = $job_card($key);
	$team_cards = array();
	foreach (array_keys($team) as $n) $team_cards[] = $team_card($n);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'firm'              => $firm,
		'services'          => $services,
		'sectors'           => $sectors,
		'projects'          => $projects,
		'project_statuses'  => $project_statuses,
		'scope_text'        => $scope_text,
		'process'           => $process,
		'team'              => $team,
		'safety_points'     => $safety_points,
		'accreditations'    => $accreditations,
		'why_us'            => $why_us,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'jobs'              => $jobs,
		'departments'       => $departments,
		'job_types'         => $job_types,
		'duties_text'       => $duties_text,
		'lists'             => array(
			'service_titles'          => $service_titles,
			'sector_titles'           => $sector_titles,
			'project_names'           => $project_names,
			'status_titles'           => $status_titles,
			'quote_service_choices'   => $quote_service_choices,
			'project_kinds'           => $project_kinds,
			'budget_choices'          => $budget_choices,
			'start_choices'           => $start_choices,
			'quote_statuses'          => $quote_statuses,
			'department_titles'       => $department_titles,
			'job_type_titles'         => $job_type_titles,
			'job_titles'              => $job_titles,
			'application_job_choices' => $application_job_choices,
			'application_statuses'    => $application_statuses,
		),
		'radios'            => array('contact_choices' => $contact_choices),
		'project_cards'     => $project_cards,
		'service_cards'     => $service_cards,
		'job_cards'         => $job_cards,
		'team_cards'        => $team_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $quote_band(), $staff_bar(), $post_card(), $ai_summary(), $project_facts(), $figure_row()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_cn_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cn_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_cn_trees()
{
	$d = _test_cn_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['project_cards']),
		array_values($d['service_cards']), array_values($d['job_cards']), $d['team_cards'], $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_cn_page_keys()
{
	$keys = array(
		'home', 'services', 'sectors', 'process', 'safety', 'about', 'team', 'faq',
		'projects', 'project', 'project_new',
		'quote', 'quote_view', 'my_quotes', 'staff_quotes', 'privacy', 'email_reply',
		'careers', 'job', 'job_new', 'application', 'application_view', 'my_applications', 'staff_applications',
		'email_quote_new', 'email_quote_received', 'email_application_new', 'email_application_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array('residential', 'commercial', 'renovation', 'civil', 'design_build', 'management') as $key) $keys[] = 'service_' . $key;
	return $keys;
}

function test_construction_data_hangs_together()
{
	$d = _test_cn_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'licence') as $f) pg_assert_true(trim($d['firm'][$f]) !== '', "firm: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['firm']['phone']), 'firm: phone number');
	pg_assert_true(strpos($d['firm']['email'], '@example.com') !== false, 'firm: a sample address');
	pg_assert_same(6, count($d['services']), 'six services');
	foreach ($d['services'] as $key => $s) {
		pg_assert_true((bool)preg_match('/^[a-z_]+$/', $key), "service $key: a page key");
		foreach (array('icon', 'title', 'summary', 'text') as $f) pg_assert_true(trim($s[$f]) !== '', "service $key: $f");
		pg_assert_same(4, count($s['points']), "service $key: four points");
	}
	pg_assert_same(6, count($d['projects']), 'six sample projects');
	$sectors = array();
	$featured = 0;
	foreach ($d['projects'] as $key => $p) {
		pg_assert_true(isset($d['sectors'][$p['sector']]), "project $key: known sector");
		pg_assert_true(isset($d['services'][$p['service']]), "project $key: known service");
		pg_assert_true(isset($d['project_statuses'][$p['status']]), "project $key: known status");
		foreach (array('name', 'location', 'size', 'duration', 'client', 'summary', 'quote') as $f) pg_assert_true(trim($p[$f]) !== '', "project $key: $f");
		pg_assert_true(count($p['scope']) >= 4, "project $key: scope");
		pg_assert_same(implode("\n", $p['scope']), call_user_func($d['scope_text'], $key), "project $key: a scope line per item");
		if ($p['featured']) $featured++;
		$sectors[$p['sector']] = true;
	}
	pg_assert_same(count($d['sectors']), count($sectors), 'a project in every sector');
	pg_assert_same(3, $featured, 'three projects on the home');
	pg_assert_same(5, count($d['process']), 'five steps of the process');
	pg_assert_same(6, count($d['team']), 'six people in the team');
	foreach ($d['team'] as $n => $t) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "team $n: $f");
	}
	pg_assert_same(4, count($d['safety_points']), 'four safety points');
	pg_assert_same(4, count($d['accreditations']), 'four accreditations');
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['projects'][$t['project']]), "testimonial $n: about a sample project");
		pg_assert_contains($d['projects'][$t['project']]['name'], $t['role'], "testimonial $n: names the project");
		pg_assert_same($d['projects'][$t['project']]['quote'], $t['quote'], "testimonial $n: the project's own words");
	}
	pg_assert_same(4, count($d['jobs']), 'four job openings');
	foreach ($d['jobs'] as $key => $j) {
		pg_assert_true(isset($d['departments'][$j['department']]), "job $key: known department");
		pg_assert_true(isset($d['job_types'][$j['type']]), "job $key: known type");
		foreach (array('title', 'location', 'summary') as $f) pg_assert_true(trim($j[$f]) !== '', "job $key: $f");
		pg_assert_same(implode("\n", $j['duties']), call_user_func($d['duties_text'], $key), "job $key: a duty line per item");
	}
	// The pick lists: one answer per service, sector, status, department,
	// type and job (plus "not sure", "open application"), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['services']), count($l['service_titles']), 'an answer per service');
	pg_assert_same(count($d['sectors']), count($l['sector_titles']), 'an answer per sector');
	pg_assert_same(count($d['projects']), count($l['project_names']), 'an answer per project');
	pg_assert_same(count($d['services']) + 1, count($l['quote_service_choices']), 'quote: an answer per service and not sure');
	pg_assert_same(count($d['jobs']) + 1, count($l['application_job_choices']), 'application: an answer per job and open');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['quote_statuses']), 'five states of a quote request');
	pg_assert_same(4, count($l['application_statuses']), 'four states of an application');
}

function test_construction_page_links_name_template_pages()
{
	$known = array_flip(_test_cn_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_cn_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The firm sells nothing online: no shared part places the cart or links a
// shop page, and the menu marks the current page itself.
function test_construction_shared_parts_have_no_shop()
{
	$json = json_encode(_test_cn_trees());
	pg_assert_false(strpos($json, '"cart_link"') !== false, 'no cart in the account bar');
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_cn_load();
	pg_assert_contains('{{page:quote}}', json_encode($d['header']), 'the header asks for a quote');
	$nodes = array();
	_test_cn_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the project list filtered on each sector, in the words
// the Sector list uses, and links every service's own page.
function test_construction_footer_filters_projects_and_links_services()
{
	$d = _test_cn_load();
	$nodes = array();
	_test_cn_nodes($d['footer'], $nodes);
	$seen = array();
	$prefix = '{{page:projects}}?';
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		pg_assert_true(isset($q['sector']) && in_array($q['sector'], $d['lists']['sector_titles'], true), 'the link names a sector answer');
		$seen[$q['sector']] = true;
	}
	pg_assert_same(count($d['sectors']), count($seen), 'a link per sector');
	$json = json_encode($d['footer']);
	foreach (array_keys($d['services']) as $key) pg_assert_contains('{{page:service_' . $key . '}}', $json, "the footer links service_$key");
}

// The values a card reads from its form's fields, and its texts.
function _test_cn_card_reads($card)
{
	$nodes = array();
	_test_cn_nodes($card, $nodes);
	$tokens = array();
	$texts = array();
	foreach ($nodes as $node) {
		if (!empty($node['props']['_bindings'])) {
			foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
		}
		if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
	}
	return array($tokens, $texts);
}

// A project card reads every value from the Projects form's fields, a job
// card from the Jobs form's, with the sample's own words on the canvas;
// the project facts read every fact field.
function test_construction_cards_read_their_records()
{
	$d = _test_cn_load();
	foreach ($d['project_cards'] as $key => $card) {
		list($tokens, $texts) = _test_cn_card_reads($card);
		foreach (array('photo_1', 'name', 'form_item_view', 'sector', 'location', 'summary', 'status') as $t) {
			pg_assert_true(isset($tokens[$t]), "project $key: card reads $t");
		}
		$p = $d['projects'][$key];
		pg_assert_true(in_array($p['name'], $texts, true), "project $key: sample name on the canvas");
		pg_assert_true(in_array($d['sectors'][$p['sector']]['title'], $texts, true), "project $key: sample sector on the canvas");
		pg_assert_true(in_array($d['project_statuses'][$p['status']], $texts, true), "project $key: sample status on the canvas");
	}
	list($tokens) = _test_cn_card_reads($d['shared'][6]);
	foreach (array('location', 'sector', 'service', 'size', 'duration', 'status') as $t) pg_assert_true(isset($tokens[$t]), "project facts read $t");
	foreach ($d['job_cards'] as $key => $card) {
		list($tokens, $texts) = _test_cn_card_reads($card);
		foreach (array('title', 'form_item_view', 'department', 'type', 'location', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "job $key: card reads $t");
		}
		pg_assert_true(in_array($d['jobs'][$key]['title'], $texts, true), "job $key: sample title on the canvas");
	}
	foreach ($d['service_cards'] as $key => $card) {
		pg_assert_contains('{{page:service_' . $key . '}}', json_encode($card), "service $key: the card opens its page");
	}
}

// Classes the template adds of its own start with pcn-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with cn-.
function test_construction_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_cn_trees() as $t) _test_cn_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pcn-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'cn-') === 0, "id $id is the template's");
	}
	foreach (array('pcn-brand', 'pcn-display', 'pcn-eyebrow', 'pcn-lift', 'pcn-stat', 'pcn-quote') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as an old
// project a year later; a project is "Completed" or "In progress".
function test_construction_no_fixed_dates()
{
	$d = _test_cn_load();
	$json = json_encode(array($d['projects'], $d['jobs'], $d['team'], $d['firm'], _test_cn_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a project is "proje", a quote "teklif", a
// site visit "keşif".
function test_construction_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-construction/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bProjects?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'proje', 0, 'UTF-8') !== false, "'$key' says proje");
		// 'Quote' alone is a testimonial's quotation, not a price.
		if (preg_match('/\bquotes?\b/i', $key) && !in_array($key, array('Quote', 'Client Quote'), true)) pg_assert_true(mb_stripos($tr, 'teklif', 0, 'UTF-8') !== false, "'$key' says teklif");
		if (preg_match('/\bsite visit\b/i', $key)) pg_assert_true(mb_stripos($tr, 'keşif', 0, 'UTF-8') !== false, "'$key' says keşif");
	}
}
