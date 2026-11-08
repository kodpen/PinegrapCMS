<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Recruitment Agency design template's parts
 * (includes/design_templates/pinegrap-recruitment/): the agency's data
 * hangs together (sample jobs naming a known sector, city, contract type
 * and work mode, a job in every sector and every city, a consultant per
 * sector, the forms' pick lists without an answer twice), every page link
 * of the shared parts names a page of the finished template, the shared
 * parts carry no cart, the footer opens the job list filtered on each
 * sector and city, the cards read their form's fields, the classes are the
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
function _test_rc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/parts.php';
	$job_cards = array();
	foreach (array_keys($jobs) as $key) $job_cards[$key] = $job_card($key);
	$sector_cards = array();
	foreach (array_keys($sectors) as $key) $sector_cards[$key] = $sector_card($key);
	$city_cards = array();
	foreach (array_keys($cities) as $key) $city_cards[$key] = $city_card($key);
	$service_cards = array();
	foreach (array_keys($employer_services) as $key) $service_cards[$key] = $service_card($key);
	$consultant_cards = array();
	foreach (array_keys($consultants) as $n) $consultant_cards[] = $consultant_card($n);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'agency'            => $agency,
		'sectors'           => $sectors,
		'cities'            => $cities,
		'contract_types'    => $contract_types,
		'work_modes'        => $work_modes,
		'jobs'              => $jobs,
		'duties_text'       => $duties_text,
		'requirements_text' => $requirements_text,
		'consultants'       => $consultants,
		'consultant_of'     => $consultant_of,
		'candidate_steps'   => $candidate_steps,
		'employer_steps'    => $employer_steps,
		'employer_services' => $employer_services,
		'why_us'            => $why_us,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'testimonial_kinds' => $testimonial_kinds,
		'lists'             => array(
			'sector_titles'              => $sector_titles,
			'city_titles'                => $city_titles,
			'contract_titles'            => $contract_titles,
			'mode_titles'                => $mode_titles,
			'consultant_names'           => $consultant_names,
			'job_titles'                 => $job_titles,
			'application_job_choices'    => $application_job_choices,
			'application_sector_choices' => $application_sector_choices,
			'application_city_choices'   => $application_city_choices,
			'notice_choices'             => $notice_choices,
			'application_statuses'       => $application_statuses,
			'enquiry_sector_choices'     => $enquiry_sector_choices,
			'enquiry_service_choices'    => $enquiry_service_choices,
			'headcount_choices'          => $headcount_choices,
			'start_choices'              => $start_choices,
			'enquiry_statuses'           => $enquiry_statuses,
		),
		'radios'            => array('contact_choices' => $contact_choices),
		'job_cards'         => $job_cards,
		'sector_cards'      => $sector_cards,
		'city_cards'        => $city_cards,
		'service_cards'     => $service_cards,
		'consultant_cards'  => $consultant_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $call_band(), $staff_bar(), $post_card(), $ai_summary(), $job_facts(), $figure_row()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_rc_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rc_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_rc_trees()
{
	$d = _test_rc_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['job_cards']),
		array_values($d['sector_cards']), array_values($d['city_cards']), array_values($d['service_cards']),
		$d['consultant_cards'], $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_rc_page_keys()
{
	$keys = array(
		'home', 'sectors', 'cities', 'candidates', 'employers', 'about', 'team', 'faq',
		'jobs', 'job', 'job_new',
		'apply', 'application_view', 'my_applications', 'staff_applications', 'privacy', 'email_reply',
		'email_application_new', 'email_application_received',
		'hire', 'enquiry_view', 'my_enquiries', 'staff_enquiries', 'email_enquiry_new', 'email_enquiry_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array('technology', 'finance', 'engineering', 'healthcare', 'sales', 'logistics') as $key) $keys[] = 'sector_' . $key;
	return $keys;
}

function test_recruitment_data_hangs_together()
{
	$d = _test_rc_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'licence') as $f) pg_assert_true(trim($d['agency'][$f]) !== '', "agency: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['agency']['phone']), 'agency: phone number');
	pg_assert_true(strpos($d['agency']['email'], '@example.com') !== false, 'agency: a sample address');
	pg_assert_same(6, count($d['sectors']), 'six sectors');
	foreach ($d['sectors'] as $key => $s) {
		pg_assert_true((bool)preg_match('/^[a-z_]+$/', $key), "sector $key: a page key");
		foreach (array('icon', 'title', 'summary', 'text') as $f) pg_assert_true(trim($s[$f]) !== '', "sector $key: $f");
		pg_assert_same(4, count($s['roles']), "sector $key: four roles");
		pg_assert_same($key, $d['consultants'][array_search(call_user_func($d['consultant_of'], $key), $d['consultants'], true)]['sector'], "sector $key: its own consultant");
	}
	pg_assert_same(5, count($d['cities']), 'five cities');
	pg_assert_same(count($d['sectors']), count($d['consultants']), 'a consultant per sector');
	pg_assert_same(8, count($d['jobs']), 'eight sample jobs');
	$sectors = array();
	$cities = array();
	$featured = 0;
	foreach ($d['jobs'] as $key => $j) {
		pg_assert_true(isset($d['sectors'][$j['sector']]), "job $key: known sector");
		pg_assert_true(isset($d['cities'][$j['city']]), "job $key: known city");
		pg_assert_true(isset($d['contract_types'][$j['type']]), "job $key: known contract type");
		pg_assert_true(isset($d['work_modes'][$j['mode']]), "job $key: known work mode");
		foreach (array('title', 'employer', 'salary', 'summary') as $f) pg_assert_true(trim($j[$f]) !== '', "job $key: $f");
		pg_assert_same(4, count($j['duties']), "job $key: four duties");
		pg_assert_same(4, count($j['requirements']), "job $key: four requirements");
		pg_assert_same(implode("\n", $j['duties']), call_user_func($d['duties_text'], $key), "job $key: a duty line per item");
		pg_assert_same(implode("\n", $j['requirements']), call_user_func($d['requirements_text'], $key), "job $key: a requirement line per item");
		if ($j['featured']) $featured++;
		$sectors[$j['sector']] = true;
		$cities[$j['city']] = true;
	}
	pg_assert_same(count($d['sectors']), count($sectors), 'a job in every sector');
	pg_assert_same(count($d['cities']), count($cities), 'a job in every city');
	pg_assert_same(3, $featured, 'three jobs on the home');
	pg_assert_same(4, count($d['candidate_steps']), 'four steps for candidates');
	pg_assert_same(4, count($d['employer_steps']), 'four steps for employers');
	pg_assert_same(4, count($d['employer_services']), 'four services for employers');
	foreach ($d['employer_services'] as $key => $s) pg_assert_same(3, count($s['points']), "service $key: three points");
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	pg_assert_same(4, count($d['figures']), 'four figures');
	$kinds = array();
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['testimonial_kinds'][$t['kind']]), "testimonial $n: a candidate or an employer");
		$kinds[$t['kind']] = true;
	}
	pg_assert_same(2, count($kinds), 'candidates and employers both speak');
	// The pick lists: one answer per sector, city, type, mode and job
	// (plus "open application", "another sector"), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['sectors']), count($l['sector_titles']), 'an answer per sector');
	pg_assert_same(count($d['cities']), count($l['city_titles']), 'an answer per city');
	pg_assert_same(count($d['jobs']) + 1, count($l['application_job_choices']), 'application: an answer per job and open');
	pg_assert_same(count($d['sectors']) + 1, count($l['enquiry_sector_choices']), 'staff request: an answer per sector and another');
	pg_assert_same(count($d['employer_services']), count($l['enquiry_service_choices']), 'staff request: an answer per service');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(6, count($l['application_statuses']), 'six states of an application');
	pg_assert_same(5, count($l['enquiry_statuses']), 'five states of a staff request');
}

// A salary is a monthly band in the site's base currency, written as the
// site writes money.
function test_recruitment_salaries_are_money_bands()
{
	$d = _test_rc_load();
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	foreach ($d['jobs'] as $key => $j) {
		pg_assert_contains($symbol, $j['salary'], "job $key: the salary carries the currency");
		pg_assert_true((bool)preg_match('/[0-9].*[0-9]/', $j['salary']), "job $key: two figures");
	}
}

function test_recruitment_page_links_name_template_pages()
{
	$known = array_flip(_test_rc_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_rc_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The agency sells nothing online: no shared part places the cart or links
// a shop page, and the menu marks the current page itself.
function test_recruitment_shared_parts_have_no_shop()
{
	$json = json_encode(_test_rc_trees());
	pg_assert_false(strpos($json, '"cart_link"') !== false, 'no cart in the account bar');
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_rc_load();
	$header = json_encode($d['header']);
	pg_assert_contains('{{page:apply}}', $header, 'the header asks for a CV');
	pg_assert_contains('{{page:hire}}', $header, 'the header asks employers in');
	$nodes = array();
	_test_rc_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The links of a tree that open the job list filtered, by filter name.
function _test_rc_job_filters($tree)
{
	$nodes = array();
	_test_rc_nodes($tree, $nodes);
	$seen = array();
	$prefix = '{{page:jobs}}?';
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		foreach ($q as $name => $value) $seen[$name][$value] = true;
	}
	return $seen;
}

// The footer opens the job list filtered on each sector and each city, in
// the words the Sector and City lists use; the sector, city and
// consultant cards do the same.
function test_recruitment_links_filter_the_job_list()
{
	$d = _test_rc_load();
	$seen = _test_rc_job_filters($d['footer']);
	pg_assert_same(array('sector', 'city'), array_keys($seen), 'the footer filters by sector and city only');
	pg_assert_same($d['lists']['sector_titles'], array_keys($seen['sector']), 'a link per sector, in its own words');
	pg_assert_same($d['lists']['city_titles'], array_keys($seen['city']), 'a link per city, in its own words');
	foreach ($d['sector_cards'] as $key => $card) {
		$s = _test_rc_job_filters($card);
		pg_assert_same(array($d['sectors'][$key]['title']), array_keys($s['sector']), "sector $key: its card opens its jobs");
		pg_assert_contains('{{page:sector_' . $key . '}}', json_encode($card), "sector $key: the card opens its page");
	}
	foreach ($d['city_cards'] as $key => $card) {
		$s = _test_rc_job_filters($card);
		pg_assert_same(array($d['cities'][$key]['title']), array_keys($s['city']), "city $key: its card opens its jobs");
	}
	foreach ($d['consultant_cards'] as $n => $card) {
		$s = _test_rc_job_filters($card);
		$sector = $d['consultants'][$n]['sector'];
		pg_assert_same(array($d['sectors'][$sector]['title']), array_keys($s['sector']), "consultant $n: the sector's jobs");
	}
}

// The values a card reads from its form's fields, and its texts.
function _test_rc_card_reads($card)
{
	$nodes = array();
	_test_rc_nodes($card, $nodes);
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

// A job card reads every value from the Jobs form's fields, with the
// sample's own words on the canvas; the job facts read every fact field.
function test_recruitment_cards_read_their_records()
{
	$d = _test_rc_load();
	foreach ($d['job_cards'] as $key => $card) {
		list($tokens, $texts) = _test_rc_card_reads($card);
		foreach (array('title', 'form_item_view', 'sector', 'employer', 'summary', 'city', 'contract_type', 'work_mode', 'salary') as $t) {
			pg_assert_true(isset($tokens[$t]), "job $key: card reads $t");
		}
		$j = $d['jobs'][$key];
		foreach (array($j['title'], $j['employer'], $j['salary'], $d['sectors'][$j['sector']]['title'], $d['cities'][$j['city']]['title'],
			$d['contract_types'][$j['type']], $d['work_modes'][$j['mode']]) as $text) {
			pg_assert_true(in_array($text, $texts, true), "job $key: sample '$text' on the canvas");
		}
	}
	list($tokens) = _test_rc_card_reads($d['shared'][6]);
	foreach (array('sector', 'city', 'contract_type', 'work_mode', 'salary') as $t) pg_assert_true(isset($tokens[$t]), "job facts read $t");
}

// Classes the template adds of its own start with prc-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with rc-.
function test_recruitment_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_rc_trees() as $t) _test_rc_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psh|pmz|pai)-/', $c), "class $c is not another template's");
			if (strpos($c, 'prc-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'rc-') === 0, "id $id is the template's");
	}
	foreach (array('prc-brand', 'prc-display', 'prc-eyebrow', 'prc-lift', 'prc-stat', 'prc-quote', 'prc-salary') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as an old
// job opening a year later.
function test_recruitment_no_fixed_dates()
{
	$d = _test_rc_load();
	$json = json_encode(array($d['jobs'], $d['consultants'], $d['agency'], $d['testimonials'], _test_rc_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: an application is "başvuru", a candidate
// "aday", an employer "işveren", a consultant "danışman".
function test_recruitment_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	$words = array(
		'/\bapplications?\b/i' => 'başvuru',
		'/\bcandidates?\b/i'   => 'aday',
		'/\bemployers?\b/i'    => 'işveren',
		'/\bconsultants?\b/i'  => 'danışman',
	);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		// The dotted capital İ does not fold to i in mb_stripos().
		$value = str_replace('İ', 'i', $map[$key]);
		foreach ($words as $re => $tr) {
			// A web application is software, not a job application.
			if ($tr === 'başvuru' && stripos($key, 'web applications') !== false) continue;
			if (preg_match($re, $key)) pg_assert_true(mb_stripos($value, $tr, 0, 'UTF-8') !== false, "'$key' says $tr");
		}
	}
}
