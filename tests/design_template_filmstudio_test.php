<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Film Studio design template's parts
 * (includes/design_templates/pinegrap-filmstudio/): the studio's data
 * hangs together (sample projects naming a known industry, service and
 * status, a project in every industry, a crew, spaces, crew calls in a
 * known department and type, testimonials about sample projects, the
 * forms' pick lists without an answer twice), every page link of the
 * shared parts names a page of the finished template, the shared parts
 * carry no cart, the footer opens the work list filtered and links every
 * service's page, the cards read their form's fields, the space cards open
 * the hire request with the space chosen, the classes are the template's
 * own, the sample carries no fixed date and every literal has its Turkish.
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
function _test_fm_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio/parts.php';
	$project_cards = array();
	foreach (array_keys($projects) as $key) $project_cards[$key] = $project_card($key);
	$service_cards = array();
	foreach (array_keys($services) as $key) $service_cards[$key] = $service_card($key);
	$space_cards = array();
	foreach (array_keys($spaces) as $key) $space_cards[$key] = $space_card($key);
	$job_cards = array();
	foreach (array_keys($jobs) as $key) $job_cards[$key] = $job_card($key);
	$crew_cards = array();
	foreach (array_keys($crew) as $n) $crew_cards[] = $crew_card($n);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'studio'            => $studio,
		'services'          => $services,
		'industries'        => $industries,
		'projects'          => $projects,
		'project_statuses'  => $project_statuses,
		'credits_text'      => $credits_text,
		'process'           => $process,
		'spaces'            => $spaces,
		'crew'              => $crew,
		'why_us'            => $why_us,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'jobs'              => $jobs,
		'departments'       => $departments,
		'job_types'         => $job_types,
		'duties_text'       => $duties_text,
		'lists'             => array(
			'service_titles'          => $service_titles,
			'industry_titles'         => $industry_titles,
			'project_names'           => $project_names,
			'status_titles'           => $status_titles,
			'space_titles'            => $space_titles,
			'enquiry_service_choices' => $enquiry_service_choices,
			'screen_choices'          => $screen_choices,
			'length_choices'          => $length_choices,
			'budget_choices'          => $budget_choices,
			'delivery_choices'        => $delivery_choices,
			'enquiry_statuses'        => $enquiry_statuses,
			'hire_lengths'            => $hire_lengths,
			'hire_statuses'           => $hire_statuses,
			'department_titles'       => $department_titles,
			'job_type_titles'         => $job_type_titles,
			'job_titles'              => $job_titles,
			'application_job_choices' => $application_job_choices,
			'application_statuses'    => $application_statuses,
		),
		'radios'            => array('contact_choices' => $contact_choices),
		'project_cards'     => $project_cards,
		'service_cards'     => $service_cards,
		'space_cards'       => $space_cards,
		'job_cards'         => $job_cards,
		'crew_cards'        => $crew_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $enquiry_band(), $staff_bar(), $post_card(), $ai_summary(),
			$project_facts(), $figure_row(), $showreel(), $credits_list()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_fm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_fm_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_fm_trees()
{
	$d = _test_fm_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['project_cards']),
		array_values($d['service_cards']), array_values($d['space_cards']), array_values($d['job_cards']),
		$d['crew_cards'], $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_fm_page_keys()
{
	$keys = array(
		'home', 'services', 'process', 'studio', 'crew', 'about', 'faq',
		'projects', 'project', 'project_new',
		'enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'studio_hire', 'studio_hire_view', 'my_studio_hires', 'staff_studio_hires', 'privacy', 'email_reply',
		'email_enquiry_new', 'email_enquiry_received', 'email_studio_hire_new', 'email_studio_hire_received',
		'careers', 'job', 'job_new', 'application', 'application_view', 'my_applications', 'staff_applications',
		'email_application_new', 'email_application_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
		'email_account_welcome', 'email_password_reset',
	);
	foreach (array('commercials', 'brand_films', 'music_videos', 'documentary', 'corporate', 'post') as $key) $keys[] = 'service_' . $key;
	return $keys;
}

function test_filmstudio_data_hangs_together()
{
	$d = _test_fm_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'note') as $f) pg_assert_true(trim($d['studio'][$f]) !== '', "studio: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['studio']['phone']), 'studio: phone number');
	pg_assert_true(strpos($d['studio']['email'], '@example.com') !== false, 'studio: a sample address');
	pg_assert_same(array_keys($d['services']), array('commercials', 'brand_films', 'music_videos', 'documentary', 'corporate', 'post'), 'six services, the page keys of the test list');
	foreach ($d['services'] as $key => $s) {
		foreach (array('icon', 'title', 'summary', 'text') as $f) pg_assert_true(trim($s[$f]) !== '', "service $key: $f");
		pg_assert_same(4, count($s['points']), "service $key: four points");
	}
	pg_assert_same(6, count($d['projects']), 'six sample projects');
	$industries = array();
	$featured = 0;
	foreach ($d['projects'] as $key => $p) {
		pg_assert_true(isset($d['industries'][$p['industry']]), "project $key: known industry");
		pg_assert_true(isset($d['services'][$p['service']]), "project $key: known service");
		pg_assert_true(isset($d['project_statuses'][$p['status']]), "project $key: known status");
		foreach (array('name', 'client', 'location', 'runtime', 'summary', 'quote') as $f) pg_assert_true(trim($p[$f]) !== '', "project $key: $f");
		pg_assert_true(count($p['credits']) >= 4, "project $key: credits");
		foreach ($p['credits'] as $line) pg_assert_true(strpos($line, ': ') !== false, "project $key: a credit is Role: Name");
		pg_assert_same(implode("\n", $p['credits']), call_user_func($d['credits_text'], $key), "project $key: a credit line each");
		if ($p['featured']) $featured++;
		$industries[$p['industry']] = true;
	}
	pg_assert_same(count($d['industries']), count($industries), 'a project in every industry');
	pg_assert_same(3, $featured, 'three projects on the home');
	pg_assert_same(5, count($d['process']), 'five steps of a production');
	pg_assert_same(4, count($d['spaces']), 'four spaces of the studio');
	pg_assert_same(6, count($d['crew']), 'six people in the crew');
	foreach ($d['crew'] as $n => $t) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "crew $n: $f");
	}
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['projects'][$t['project']]), "testimonial $n: about a sample project");
		pg_assert_contains($d['projects'][$t['project']]['name'], $t['role'], "testimonial $n: names the project");
		pg_assert_same($d['projects'][$t['project']]['quote'], $t['quote'], "testimonial $n: the project's own words");
	}
	pg_assert_same(4, count($d['jobs']), 'four crew calls');
	foreach ($d['jobs'] as $key => $j) {
		pg_assert_true(isset($d['departments'][$j['department']]), "job $key: known department");
		pg_assert_true(isset($d['job_types'][$j['type']]), "job $key: known type");
		foreach (array('title', 'location', 'summary') as $f) pg_assert_true(trim($j[$f]) !== '', "job $key: $f");
		pg_assert_same(implode("\n", $j['duties']), call_user_func($d['duties_text'], $key), "job $key: a duty line per item");
	}
	// The pick lists: one answer per service, industry, status, space,
	// department, type and job (plus "not sure", "open application"), no
	// answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['services']), count($l['service_titles']), 'an answer per service');
	pg_assert_same(count($d['industries']), count($l['industry_titles']), 'an answer per industry');
	pg_assert_same(count($d['projects']), count($l['project_names']), 'an answer per project');
	pg_assert_same(count($d['spaces']), count($l['space_titles']), 'an answer per space');
	pg_assert_same(count($d['services']) + 1, count($l['enquiry_service_choices']), 'enquiry: an answer per service and not sure');
	pg_assert_same(count($d['jobs']) + 1, count($l['application_job_choices']), 'application: an answer per job and open');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['enquiry_statuses']), 'five states of an enquiry');
	pg_assert_same(3, count($l['hire_statuses']), 'three states of a hire request');
	pg_assert_same(4, count($l['application_statuses']), 'four states of an application');
}

function test_filmstudio_page_links_name_template_pages()
{
	$known = array_flip(_test_fm_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_fm_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The studio sells nothing online: no shared part places the cart or links
// a shop page, and the menu marks the current page itself.
function test_filmstudio_shared_parts_have_no_shop()
{
	$json = json_encode(_test_fm_trees());
	pg_assert_false(strpos($json, '"cart_link"') !== false, 'no cart in the account bar');
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_fm_load();
	pg_assert_contains('{{page:enquiry}}', json_encode($d['header']), 'the header starts a project');
	$nodes = array();
	_test_fm_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The links of a tree that open $page with a query, as parsed queries.
function _test_fm_filter_links($tree, $page)
{
	$nodes = array();
	_test_fm_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:' . $page . '}}?';
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

// The footer opens the work list filtered on each industry, in the words
// the Industry list uses, and links every service's own page; a space card
// opens the hire request with its space chosen in the Space list's words.
function test_filmstudio_filter_links_use_the_lists_words()
{
	$d = _test_fm_load();
	$seen = array();
	foreach (_test_fm_filter_links($d['footer'], 'projects') as $q) {
		pg_assert_true(isset($q['industry']) && in_array($q['industry'], $d['lists']['industry_titles'], true), 'the link names an industry answer');
		$seen[$q['industry']] = true;
	}
	pg_assert_same(count($d['industries']), count($seen), 'a link per industry');
	$json = json_encode($d['footer']);
	foreach (array_keys($d['services']) as $key) pg_assert_contains('{{page:service_' . $key . '}}', $json, "the footer links service_$key");
	foreach ($d['space_cards'] as $key => $card) {
		$links = _test_fm_filter_links($card, 'studio_hire');
		pg_assert_same(1, count($links), "space $key: one hire link");
		pg_assert_same($d['spaces'][$key]['title'], $links[0]['space'] ?? '', "space $key: the hire request opens with it chosen");
	}
}

// The values a card reads from its form's fields, and its texts.
function _test_fm_card_reads($card)
{
	$nodes = array();
	_test_fm_nodes($card, $nodes);
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

// A project card reads every value from the Projects form's fields, a crew
// call card from the Jobs form's, with the sample's own words on the
// canvas; the project facts read every fact field, the credits theirs.
function test_filmstudio_cards_read_their_records()
{
	$d = _test_fm_load();
	foreach ($d['project_cards'] as $key => $card) {
		list($tokens, $texts) = _test_fm_card_reads($card);
		foreach (array('still_1', 'name', 'form_item_view', 'industry', 'client', 'runtime', 'summary', 'status') as $t) {
			pg_assert_true(isset($tokens[$t]), "project $key: card reads $t");
		}
		$p = $d['projects'][$key];
		pg_assert_true(in_array($p['name'], $texts, true), "project $key: sample name on the canvas");
		pg_assert_true(in_array($d['industries'][$p['industry']]['title'], $texts, true), "project $key: sample industry on the canvas");
		pg_assert_true(in_array($d['project_statuses'][$p['status']], $texts, true), "project $key: sample status on the canvas");
	}
	list($tokens) = _test_fm_card_reads($d['shared'][6]);
	foreach (array('client', 'industry', 'service', 'location', 'runtime', 'status') as $t) pg_assert_true(isset($tokens[$t]), "project facts read $t");
	list($tokens) = _test_fm_card_reads($d['shared'][9]);
	pg_assert_true(isset($tokens['credits']), 'the credits read their field');
	foreach ($d['job_cards'] as $key => $card) {
		list($tokens, $texts) = _test_fm_card_reads($card);
		foreach (array('title', 'form_item_view', 'department', 'type', 'location', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "job $key: card reads $t");
		}
		pg_assert_true(in_array($d['jobs'][$key]['title'], $texts, true), "job $key: sample title on the canvas");
	}
	foreach ($d['service_cards'] as $key => $card) {
		pg_assert_contains('{{page:service_' . $key . '}}', json_encode($card), "service $key: the card opens its page");
	}
}

// The showreel is a player the studio fills: controls, no autoplay, the
// sample still as its poster.
function test_filmstudio_showreel_is_a_player()
{
	$d = _test_fm_load();
	$nodes = array();
	_test_fm_nodes($d['shared'][8], $nodes);
	$video = null;
	foreach ($nodes as $n) if (($n['props']['tag'] ?? '') === 'video') $video = $n;
	pg_assert_true($video !== null, 'a video element');
	$attrs = array();
	foreach ($video['props']['_attrs'] as $a) $attrs[$a['name']] = $a['value'];
	pg_assert_true(isset($attrs['controls']), 'the player has controls');
	pg_assert_false(isset($attrs['autoplay']), 'the player never starts by itself');
	pg_assert_contains('pinegrap-filmstudio-', $attrs['poster'] ?? '', 'a sample poster');
}

// Classes the template adds of its own start with pfm-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with fm-.
function test_filmstudio_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_fm_trees() as $t) _test_fm_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pfm-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'fm-') === 0, "id $id is the template's");
	}
	foreach (array('pfm-brand', 'pfm-display', 'pfm-eyebrow', 'pfm-lift', 'pfm-stat', 'pfm-quote', 'pfm-hero', 'pfm-play') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as an old film a
// year later; a project is "Released" or "In post-production".
function test_filmstudio_no_fixed_dates()
{
	$d = _test_fm_load();
	$json = json_encode(array($d['projects'], $d['jobs'], $d['crew'], $d['studio'], _test_fm_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a project is "proje", an enquiry "talep", the
// crew "ekip".
function test_filmstudio_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bProjects?\b/', $key)) pg_assert_true(mb_stripos($tr, 'proje', 0, 'UTF-8') !== false, "'$key' says proje");
		if (preg_match('/\benquir(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'talep', 0, 'UTF-8') !== false, "'$key' says talep");
		if (preg_match('/\bthe crew\b|\bcrew call/i', $key)) pg_assert_true(mb_stripos($tr, 'ekip', 0, 'UTF-8') !== false, "'$key' says ekip");
	}
}
