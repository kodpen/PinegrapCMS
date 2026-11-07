<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Architecture & Interiors Studio design template's parts
 * (includes/design_templates/pinegrap-architecture/): the studio's data
 * hangs together (sample projects naming a known type, location, service
 * and stage, a project of every type and in every location, credits on
 * every project, awards about sample projects, a team, job openings in a
 * known team and type, testimonials about sample projects, the forms'
 * pick lists without an answer twice), every page link of the shared
 * parts names a page of the finished template, the shared parts carry no
 * cart, the footer opens the project list filtered by type and by place
 * and links every service's page, the cards read their form's fields, the
 * classes are the template's own, the sample carries no fixed date and
 * every literal has its Turkish.
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
function _test_ai_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture/parts.php';
	$project_cards = array();
	foreach (array_keys($projects) as $key) $project_cards[$key] = $project_card($key);
	$service_cards = array();
	foreach (array_keys($services) as $key) $service_cards[$key] = $service_card($key);
	$job_cards = array();
	foreach (array_keys($jobs) as $key) $job_cards[$key] = $job_card($key);
	$people = array();
	foreach (array_keys($team) as $n) $people[] = $team_card($n);
	foreach (array_keys($testimonials) as $n) $people[] = $testimonial_card($n);
	foreach (array_keys($awards) as $n) $people[] = $award_card($n);
	foreach (array_keys($press) as $n) $people[] = $press_card($n);
	$out = array(
		'studio'          => $studio,
		'services'        => $services,
		'project_types'   => $project_types,
		'locations'       => $locations,
		'project_stages'  => $project_stages,
		'projects'        => $projects,
		'credits_text'    => $credits_text,
		'stages'          => $stages,
		'principles'      => $principles,
		'team'            => $team,
		'award_results'   => $award_results,
		'awards'          => $awards,
		'press'           => $press,
		'figures'         => $figures,
		'testimonials'    => $testimonials,
		'jobs'            => $jobs,
		'departments'     => $departments,
		'job_types'       => $job_types,
		'duties_text'     => $duties_text,
		'lists'           => array(
			'service_titles'          => $service_titles,
			'type_titles'             => $type_titles,
			'location_titles'         => $location_titles,
			'project_names'           => $project_names,
			'stage_titles'            => $stage_titles,
			'enquiry_service_choices' => $enquiry_service_choices,
			'project_kinds'           => $project_kinds,
			'site_choices'            => $site_choices,
			'budget_choices'          => $budget_choices,
			'start_choices'           => $start_choices,
			'enquiry_statuses'        => $enquiry_statuses,
			'consultation_places'     => $consultation_places,
			'consultation_statuses'   => $consultation_statuses,
			'department_titles'       => $department_titles,
			'job_type_titles'         => $job_type_titles,
			'job_titles'              => $job_titles,
			'application_job_choices' => $application_job_choices,
			'application_statuses'    => $application_statuses,
		),
		'radios'          => array('contact_choices' => $contact_choices),
		'project_cards'   => $project_cards,
		'service_cards'   => $service_cards,
		'job_cards'       => $job_cards,
		'people'          => $people,
		'header'          => $header(),
		'footer'          => $footer(),
		'facts'           => $project_facts(),
		'credits'         => $credits_block(),
		'gallery'         => $gallery(),
		'shared'          => array($account_bar_tree(), $notice(), $enquiry_band(), $staff_bar(), $post_card(), $ai_summary(), $figure_row(),
			$page_header(lang('Projects'), lang('Text')), $section_header(lang('Text'), lang('Projects'), lang('All Projects'), '{{page:projects}}')),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_ai_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ai_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_ai_trees()
{
	$d = _test_ai_load();
	return array_merge(array($d['header'], $d['footer'], $d['facts'], $d['credits'], $d['gallery']), $d['shared'],
		array_values($d['project_cards']), array_values($d['service_cards']), array_values($d['job_cards']), $d['people']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_ai_page_keys()
{
	$keys = array(
		'home', 'services', 'studio', 'team', 'process', 'awards', 'faq',
		'projects', 'project', 'project_new',
		'enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'consultation', 'consultation_view', 'my_consultations', 'staff_consultations', 'privacy', 'email_reply',
		'email_enquiry_new', 'email_enquiry_received', 'email_consultation_new', 'email_consultation_received',
		'careers', 'job', 'job_new', 'application', 'application_view', 'my_applications', 'staff_applications',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array('architecture', 'interiors', 'adaptive_reuse', 'masterplanning', 'landscape', 'furniture') as $key) $keys[] = 'service_' . $key;
	return $keys;
}

function test_architecture_data_hangs_together()
{
	$d = _test_ai_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'licence') as $f) pg_assert_true(trim($d['studio'][$f]) !== '', "studio: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['studio']['phone']), 'studio: phone number');
	pg_assert_true(strpos($d['studio']['email'], '@example.com') !== false, 'studio: a sample address');
	pg_assert_same(array_keys($d['services']), array('architecture', 'interiors', 'adaptive_reuse', 'masterplanning', 'landscape', 'furniture'), 'the six services of the page keys');
	foreach ($d['services'] as $key => $s) {
		foreach (array('icon', 'title', 'summary', 'text') as $f) pg_assert_true(trim($s[$f]) !== '', "service $key: $f");
		pg_assert_same(4, count($s['points']), "service $key: four points");
	}
	pg_assert_same(6, count($d['projects']), 'six sample projects');
	$types = array();
	$places = array();
	$featured = 0;
	foreach ($d['projects'] as $key => $p) {
		pg_assert_true(isset($d['project_types'][$p['type']]), "project $key: known type");
		pg_assert_true(isset($d['locations'][$p['location']]), "project $key: known location");
		pg_assert_true(isset($d['services'][$p['service']]), "project $key: known service");
		pg_assert_true(isset($d['project_stages'][$p['stage']]), "project $key: known stage");
		foreach (array('name', 'place', 'size', 'client', 'summary', 'brief', 'quote') as $f) pg_assert_true(trim($p[$f]) !== '', "project $key: $f");
		pg_assert_true(count($p['credits']) >= 3, "project $key: credits");
		$lines = explode("\n", call_user_func($d['credits_text'], $key));
		pg_assert_same(count($p['credits']), count($lines), "project $key: a credit line per credit");
		foreach ($lines as $line) pg_assert_true((bool)preg_match('/^[^:\n]+: \S/u', $line), "project $key: credit '$line' reads Role: Name");
		if ($p['featured']) $featured++;
		$types[$p['type']] = true;
		$places[$p['location']] = true;
	}
	pg_assert_same(count($d['project_types']), count($types), 'a project of every type');
	pg_assert_same(count($d['locations']), count($places), 'a project in every location');
	pg_assert_same(3, $featured, 'three projects on the home');
	pg_assert_same(6, count($d['stages']), 'six stages of work');
	pg_assert_same(4, count($d['principles']), 'four principles');
	pg_assert_same(6, count($d['team']), 'six people in the team');
	foreach ($d['team'] as $n => $t) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "team $n: $f");
	}
	foreach ($d['awards'] as $n => $a) {
		pg_assert_true(isset($d['projects'][$a['project']]), "award $n: about a sample project");
		pg_assert_true(isset($d['award_results'][$a['result']]), "award $n: known result");
	}
	pg_assert_same(3, count($d['press']), 'three press articles');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['projects'][$t['project']]), "testimonial $n: about a sample project");
		pg_assert_contains($d['projects'][$t['project']]['name'], $t['role'], "testimonial $n: names the project");
		pg_assert_same($d['projects'][$t['project']]['quote'], $t['quote'], "testimonial $n: the project's own words");
	}
	pg_assert_same(4, count($d['jobs']), 'four job openings');
	foreach ($d['jobs'] as $key => $j) {
		pg_assert_true(isset($d['departments'][$j['department']]), "job $key: known team");
		pg_assert_true(isset($d['job_types'][$j['type']]), "job $key: known type");
		foreach (array('title', 'location', 'summary') as $f) pg_assert_true(trim($j[$f]) !== '', "job $key: $f");
		pg_assert_same(implode("\n", $j['duties']), call_user_func($d['duties_text'], $key), "job $key: a duty line per item");
	}
	// The pick lists: one answer per service, type, place, stage, team,
	// job type and job (plus "not sure", "open application"), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['services']), count($l['service_titles']), 'an answer per service');
	pg_assert_same(count($d['project_types']), count($l['type_titles']), 'an answer per type');
	pg_assert_same(count($d['locations']), count($l['location_titles']), 'an answer per location');
	pg_assert_same(count($d['project_stages']), count($l['stage_titles']), 'an answer per stage');
	pg_assert_same(count($d['projects']), count($l['project_names']), 'an answer per project');
	pg_assert_same(count($d['services']) + 1, count($l['enquiry_service_choices']), 'enquiry: an answer per service and not sure');
	pg_assert_same(count($d['jobs']) + 1, count($l['application_job_choices']), 'application: an answer per job and open');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['enquiry_statuses']), 'five states of an enquiry');
	pg_assert_same(5, count($l['consultation_statuses']), 'five states of a consultation');
	pg_assert_same(4, count($l['application_statuses']), 'four states of an application');
}

function test_architecture_page_links_name_template_pages()
{
	$known = array_flip(_test_ai_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_ai_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The studio sells nothing online: no shared part places the cart or links
// a shop page, and the menu marks the current page itself.
function test_architecture_shared_parts_have_no_shop()
{
	$json = json_encode(_test_ai_trees());
	pg_assert_false(strpos($json, '"cart_link"') !== false, 'no cart in the account bar');
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_ai_load();
	pg_assert_contains('{{page:enquiry}}', json_encode($d['header']), 'the header starts a project');
	$nodes = array();
	_test_ai_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the project list filtered on each type and each place,
// in the words the Type and Location lists use, and links every service's
// own page.
function test_architecture_footer_filters_projects_and_links_services()
{
	$d = _test_ai_load();
	$nodes = array();
	_test_ai_nodes($d['footer'], $nodes);
	$seen = array('type' => array(), 'location' => array());
	$prefix = '{{page:projects}}?';
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		pg_assert_same(1, count($q), 'one filter per link');
		if (isset($q['type'])) {
			pg_assert_true(in_array($q['type'], $d['lists']['type_titles'], true), 'the link names a type answer');
			$seen['type'][$q['type']] = true;
		} else {
			pg_assert_true(isset($q['location']) && in_array($q['location'], $d['lists']['location_titles'], true), 'the link names a location answer');
			$seen['location'][$q['location']] = true;
		}
	}
	pg_assert_same(count($d['project_types']), count($seen['type']), 'a link per type');
	pg_assert_same(count($d['locations']), count($seen['location']), 'a link per location');
	$json = json_encode($d['footer']);
	foreach (array_keys($d['services']) as $key) pg_assert_contains('{{page:service_' . $key . '}}', $json, "the footer links service_$key");
}

// The values a part reads from its form's fields, and its texts.
function _test_ai_reads($tree)
{
	$nodes = array();
	_test_ai_nodes($tree, $nodes);
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
// the facts, the credits and the gallery read their fields.
function test_architecture_cards_read_their_records()
{
	$d = _test_ai_load();
	foreach ($d['project_cards'] as $key => $card) {
		list($tokens, $texts) = _test_ai_reads($card);
		foreach (array('photo_1', 'name', 'form_item_view', 'type', 'location', 'summary', 'stage') as $t) {
			pg_assert_true(isset($tokens[$t]), "project $key: card reads $t");
		}
		$p = $d['projects'][$key];
		pg_assert_true(in_array($p['name'], $texts, true), "project $key: sample name on the canvas");
		pg_assert_true(in_array($d['project_types'][$p['type']]['title'], $texts, true), "project $key: sample type on the canvas");
		pg_assert_true(in_array($d['locations'][$p['location']], $texts, true), "project $key: sample location on the canvas");
		pg_assert_true(in_array($d['project_stages'][$p['stage']], $texts, true), "project $key: sample stage on the canvas");
	}
	list($tokens) = _test_ai_reads($d['facts']);
	foreach (array('type', 'location', 'place', 'service', 'size', 'stage') as $t) pg_assert_true(isset($tokens[$t]), "project facts read $t");
	list($tokens, $texts) = _test_ai_reads($d['credits']);
	pg_assert_true(isset($tokens['credits']), 'the credits read their field');
	pg_assert_true(in_array(call_user_func($d['credits_text'], 'courtyard_house'), $texts, true), 'the sample credits on the canvas');
	list($tokens) = _test_ai_reads($d['gallery']);
	foreach (array('photo_1', 'photo_2', 'photo_3', 'photo_4') as $t) pg_assert_true(isset($tokens[$t]), "the gallery reads $t");
	foreach ($d['job_cards'] as $key => $card) {
		list($tokens, $texts) = _test_ai_reads($card);
		foreach (array('title', 'form_item_view', 'department', 'type', 'location', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "job $key: card reads $t");
		}
		pg_assert_true(in_array($d['jobs'][$key]['title'], $texts, true), "job $key: sample title on the canvas");
	}
	foreach ($d['service_cards'] as $key => $card) {
		pg_assert_contains('{{page:service_' . $key . '}}', json_encode($card), "service $key: the card opens its page");
	}
}

// Classes the template adds of its own start with pai-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with ai-.
function test_architecture_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_ai_trees() as $t) _test_ai_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^opacity-/', $c), "no faded text ($c)");
			if (strpos($c, 'pai-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'ai-') === 0, "id $id is the template's");
	}
	foreach (array('pai-brand', 'pai-display', 'pai-eyebrow', 'pai-lift', 'pai-stat', 'pai-quote', 'pai-index') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as an old
// project a year later; a project has a stage, an award its prize only.
function test_architecture_no_fixed_dates()
{
	$d = _test_ai_load();
	$json = json_encode(array($d['projects'], $d['awards'], $d['press'], $d['jobs'], $d['team'], $d['studio'], _test_ai_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a project is "proje", an enquiry "talep", a
// consultation "görüşme" (the shared staff key says "danışma"), the
// journal "Günce".
function test_architecture_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bProjects?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'proje', 0, 'UTF-8') !== false, "'$key' says proje");
		if (preg_match('/\benquir(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'talep', 0, 'UTF-8') !== false, "'$key' says talep");
		if (preg_match('/\bconsultation\b/i', $key)) pg_assert_true((bool)preg_match('/görüşme|danışma/iu', $tr), "'$key' says görüşme or danışma");
		if ($key === 'Journal') pg_assert_same('Günce', $tr, 'the Journal is Günce');
	}
}
