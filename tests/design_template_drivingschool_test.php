<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Driving School design template's parts
 * (includes/design_templates/pinegrap-drivingschool/): the school's data
 * hangs together (sample courses naming a known licence, format and
 * gearbox with a price and what they include, a course for every licence,
 * instructors teaching known licences, pass stories about a known licence
 * and instructor, the forms' pick lists without an answer twice), every
 * page link of the shared parts names a page of the finished template,
 * the shared parts carry no cart, the footer opens the course list
 * filtered and links every licence's page, the cards read their form's
 * fields, the instructor cards open the lesson request with the
 * instructor chosen, the classes are the template's own, the sample
 * carries no fixed date and every literal has its Turkish.
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
function _test_ds_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool/parts.php';
	$licence_cards = array();
	foreach (array_keys($licences) as $key) $licence_cards[$key] = $licence_card($key);
	$course_cards = array();
	foreach (array_keys($courses) as $key) $course_cards[$key] = $course_card($key);
	$instructor_cards = array();
	foreach (array_keys($instructors) as $n) $instructor_cards[] = $instructor_card($n);
	$story_cards = array();
	foreach (array_keys($stories) as $key) $story_cards[$key] = $story_card($key);
	$out = array(
		'school'           => $school,
		'licences'         => $licences,
		'formats'          => $formats,
		'gearboxes'        => $gearboxes,
		'courses'          => $courses,
		'price_text'       => $price_text,
		'included_text'    => $included_text,
		'instructors'      => $instructors,
		'theory_topics'    => $theory_topics,
		'test_day'         => $test_day,
		'branches'         => $branches,
		'why_us'           => $why_us,
		'figures'          => $figures,
		'stories'          => $stories,
		'try_choices'      => $try_choices,
		'lists'            => array(
			'licence_titles'           => $licence_titles,
			'format_titles'            => $format_titles,
			'gearbox_titles'           => $gearbox_titles,
			'course_names'             => $course_names,
			'enrolment_course_choices' => $enrolment_course_choices,
			'instructor_names'         => $instructor_names,
			'instructor_choices'       => $instructor_choices,
			'branch_titles'            => $branch_titles,
			'lesson_time_choices'      => $lesson_time_choices,
			'lesson_lengths'           => $lesson_lengths,
			'theory_choices'           => $theory_choices,
			'try_choices'              => $try_choices,
			'enrolment_statuses'       => $enrolment_statuses,
			'lesson_statuses'          => $lesson_statuses,
		),
		'radios'           => array('contact_choices' => $contact_choices),
		'licence_cards'    => $licence_cards,
		'course_cards'     => $course_cards,
		'instructor_cards' => $instructor_cards,
		'story_cards'      => $story_cards,
		'header'           => $header(),
		'footer'           => $footer(),
		'course_facts'     => $course_facts(),
		'included_list'    => $included_list(),
		'shared'           => array($account_bar_tree(), $notice(), $enrol_band(), $staff_bar(), $post_card(), $ai_summary(),
			$figure_row(), $course_facts(), $included_list()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_ds_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ds_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_ds_trees()
{
	$d = _test_ds_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['licence_cards']),
		array_values($d['course_cards']), $d['instructor_cards'], array_values($d['story_cards']));
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_ds_page_keys()
{
	$keys = array(
		'home', 'licences', 'theory', 'practical_test', 'instructors', 'about', 'faq',
		'courses', 'course', 'course_new',
		'enrol', 'enrolment_view', 'my_enrolments', 'staff_enrolments', 'privacy', 'email_reply',
		'email_enrolment_new', 'email_enrolment_received',
		'lesson_request', 'lesson_request_view', 'my_lesson_requests', 'staff_lesson_requests', 'book_online',
		'stories', 'story', 'story_new',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array('car', 'automatic', 'motorcycle', 'light_truck', 'trailer') as $key) $keys[] = 'licence_' . $key;
	return $keys;
}

function test_drivingschool_data_hangs_together()
{
	$d = _test_ds_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'note') as $f) pg_assert_true(trim($d['school'][$f]) !== '', "school: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['school']['phone']), 'school: phone number');
	pg_assert_true(strpos($d['school']['email'], '@example.com') !== false, 'school: a sample address');
	pg_assert_same(array('car', 'automatic', 'motorcycle', 'light_truck', 'trailer'), array_keys($d['licences']), 'five licences, the page keys of the test list');
	foreach ($d['licences'] as $key => $l) {
		foreach (array('code', 'icon', 'title', 'summary', 'text', 'age', 'lessons') as $f) pg_assert_true(trim($l[$f]) !== '', "licence $key: $f");
		pg_assert_same(4, count($l['points']), "licence $key: four points");
	}
	pg_assert_same(6, count($d['courses']), 'six sample courses');
	$licences = array();
	$featured = 0;
	foreach ($d['courses'] as $key => $c) {
		pg_assert_true(isset($d['licences'][$c['licence']]), "course $key: known licence");
		pg_assert_true(isset($d['formats'][$c['format']]), "course $key: known format");
		pg_assert_true(isset($d['gearboxes'][$c['gearbox']]), "course $key: known gearbox");
		foreach (array('name', 'lessons', 'hours', 'summary') as $f) pg_assert_true(trim($c[$f]) !== '', "course $key: $f");
		foreach (array('USD', 'EUR', 'GBP', 'TRY') as $cur) pg_assert_true(is_int($c['price'][$cur]) && $c['price'][$cur] > 0, "course $key: a $cur price");
		pg_assert_true(trim(call_user_func($d['price_text'], $key)) !== '', "course $key: the price in words");
		pg_assert_true(count($c['included']) >= 3, "course $key: what is included");
		pg_assert_same(implode("\n", $c['included']), call_user_func($d['included_text'], $key), "course $key: an item a line");
		if ($c['featured']) $featured++;
		$licences[$c['licence']] = true;
	}
	pg_assert_same(3, $featured, 'three courses on the home');
	pg_assert_same(count($d['licences']) - 1, count($licences), 'a course for every licence but the light truck');
	pg_assert_same(5, count($d['instructors']), 'five instructors');
	foreach ($d['instructors'] as $n => $t) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "instructor $n: $f");
		foreach ($t['licences'] as $key) pg_assert_true(isset($d['licences'][$key]), "instructor $n: teaches a known licence");
	}
	pg_assert_same(6, count($d['theory_topics']), 'six theory topics');
	pg_assert_same(4, count($d['test_day']), 'four steps of the test day');
	pg_assert_same(2, count($d['branches']), 'two branches');
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	pg_assert_same(4, count($d['figures']), 'four figures');
	$featured = 0;
	foreach ($d['stories'] as $key => $s) {
		pg_assert_true(isset($d['licences'][$s['licence']]), "story $key: known licence");
		pg_assert_true(isset($d['try_choices'][$s['try']]), "story $key: known try");
		pg_assert_true(isset($d['instructors'][$s['instructor']]), "story $key: known instructor");
		pg_assert_true(in_array($s['licence'], $d['instructors'][$s['instructor']]['licences'], true), "story $key: the instructor teaches the licence");
		foreach (array('name', 'lessons', 'quote') as $f) pg_assert_true(trim($s[$f]) !== '', "story $key: $f");
		if ($s['featured']) $featured++;
	}
	pg_assert_same(3, $featured, 'three stories on the home');
	// The pick lists: one answer per licence, format, gearbox, course,
	// instructor and branch (plus "not sure", "any instructor"), no answer
	// twice.
	$l = $d['lists'];
	pg_assert_same(count($d['licences']), count($l['licence_titles']), 'an answer per licence');
	pg_assert_same(count($d['formats']), count($l['format_titles']), 'an answer per format');
	pg_assert_same(count($d['courses']), count($l['course_names']), 'an answer per course');
	pg_assert_same(count($d['courses']) + 1, count($l['enrolment_course_choices']), 'enrolment: an answer per course and not sure');
	pg_assert_same(count($d['instructors']) + 1, count($l['instructor_choices']), 'lesson: an answer per instructor and any');
	pg_assert_same(count($d['branches']), count($l['branch_titles']), 'an answer per branch');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['enrolment_statuses']), 'five states of an enrolment');
	pg_assert_same(3, count($l['lesson_statuses']), 'three states of a lesson request');
}

function test_drivingschool_page_links_name_template_pages()
{
	$known = array_flip(_test_ds_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_ds_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The school sells nothing online: no shared part places the cart or links
// a shop page, and the menu marks the current page itself.
function test_drivingschool_shared_parts_have_no_shop()
{
	$json = json_encode(_test_ds_trees());
	pg_assert_false(strpos($json, '"cart_link"') !== false, 'no cart in the account bar');
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_ds_load();
	pg_assert_contains('{{page:enrol}}', json_encode($d['header']), 'the header opens the enrolment');
	$nodes = array();
	_test_ds_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The links of a tree that open $page with a query, as parsed queries.
function _test_ds_filter_links($tree, $page)
{
	$nodes = array();
	_test_ds_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:' . $page . '}}?';
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

// The footer and the licence cards open the course list filtered on each
// licence, in the words the Licence list uses, and the footer links every
// licence's own page; an instructor card opens the lesson request with the
// instructor chosen in the Instructor list's words.
function test_drivingschool_filter_links_use_the_lists_words()
{
	$d = _test_ds_load();
	$seen = array();
	foreach (_test_ds_filter_links($d['footer'], 'courses') as $q) {
		pg_assert_true(isset($q['licence']) && in_array($q['licence'], $d['lists']['licence_titles'], true), 'the link names a licence answer');
		$seen[$q['licence']] = true;
	}
	pg_assert_same(count($d['licences']), count($seen), 'a link per licence');
	$json = json_encode($d['footer']);
	foreach (array_keys($d['licences']) as $key) pg_assert_contains('{{page:licence_' . $key . '}}', $json, "the footer links licence_$key");
	foreach ($d['licence_cards'] as $key => $card) {
		$links = _test_ds_filter_links($card, 'courses');
		pg_assert_same(1, count($links), "licence $key: one course link");
		pg_assert_same($d['licences'][$key]['title'], $links[0]['licence'] ?? '', "licence $key: the list opens on it");
		pg_assert_contains('{{page:licence_' . $key . '}}', json_encode($card), "licence $key: the card opens its page");
	}
	foreach ($d['instructor_cards'] as $n => $card) {
		$links = _test_ds_filter_links($card, 'lesson_request');
		pg_assert_same(1, count($links), "instructor $n: one lesson link");
		$name = $links[0]['instructor'] ?? '';
		pg_assert_same($d['instructors'][$n]['name'], $name, "instructor $n: the request opens with them chosen");
		pg_assert_true(in_array($name, $d['lists']['instructor_choices'], true), "instructor $n: an answer of the list");
	}
}

// The values a card reads from its form's fields, and its texts.
function _test_ds_card_reads($card)
{
	$nodes = array();
	_test_ds_nodes($card, $nodes);
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

// A course card reads every value from the Courses form's fields, a story
// card from the Pass Stories form's, with the sample's own words on the
// canvas; the course facts read every fact field, the included list its.
function test_drivingschool_cards_read_their_records()
{
	$d = _test_ds_load();
	foreach ($d['course_cards'] as $key => $card) {
		list($tokens, $texts) = _test_ds_card_reads($card);
		foreach (array('photo_1', 'name', 'form_item_view', 'format', 'price', 'lessons', 'summary', 'licence') as $t) {
			pg_assert_true(isset($tokens[$t]), "course $key: card reads $t");
		}
		$c = $d['courses'][$key];
		pg_assert_true(in_array($c['name'], $texts, true), "course $key: sample name on the canvas");
		pg_assert_true(in_array(call_user_func($d['price_text'], $key), $texts, true), "course $key: sample price on the canvas");
		pg_assert_true(in_array($d['licences'][$c['licence']]['title'], $texts, true), "course $key: sample licence on the canvas");
	}
	list($tokens) = _test_ds_card_reads($d['course_facts']);
	foreach (array('licence', 'format', 'gearbox', 'lessons', 'duration') as $t) pg_assert_true(isset($tokens[$t]), "course facts read $t");
	list($tokens) = _test_ds_card_reads($d['included_list']);
	pg_assert_true(isset($tokens['included']), 'the included list reads its field');
	foreach ($d['story_cards'] as $key => $card) {
		list($tokens, $texts) = _test_ds_card_reads($card);
		foreach (array('photo_1', 'name', 'form_item_view', 'licence', 'try', 'words', 'instructor') as $t) {
			pg_assert_true(isset($tokens[$t]), "story $key: card reads $t");
		}
		$s = $d['stories'][$key];
		pg_assert_true(in_array($s['name'], $texts, true), "story $key: sample name on the canvas");
		pg_assert_true(in_array($d['instructors'][$s['instructor']]['name'], $texts, true), "story $key: sample instructor on the canvas");
	}
}

// Classes the template adds of its own start with pdr-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with drv-.
function test_drivingschool_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_ds_trees() as $t) _test_ds_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pfm|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|plb|pvt)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pdr-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'drv-') === 0, "id $id is the template's");
	}
	foreach (array('pdr-brand', 'pdr-display', 'pdr-eyebrow', 'pdr-lift', 'pdr-stat', 'pdr-quote', 'pdr-hero', 'pdr-plate', 'pdr-badge') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as an old offer
// a year later.
function test_drivingschool_no_fixed_dates()
{
	$d = _test_ds_load();
	$json = json_encode(array($d['courses'], $d['stories'], $d['instructors'], $d['school'], _test_ds_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a licence is "ehliyet", an instructor
// "eğitmen", a course "kurs", a lesson "ders".
function test_drivingschool_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/^Licence/', $key)) pg_assert_true(mb_stripos($tr, 'ehliyet', 0, 'UTF-8') !== false, "'$key' says ehliyet");
		if (preg_match('/\bInstructors?$/', $key)) pg_assert_true(mb_stripos($tr, 'eğitmen', 0, 'UTF-8') !== false, "'$key' says eğitmen");
		if (preg_match('/^Courses?\b/', $key)) pg_assert_true(mb_stripos($tr, 'kurs', 0, 'UTF-8') !== false, "'$key' says kurs");
		if (preg_match('/\bLessons?\b/', $key)) pg_assert_true(mb_stripos($tr, 'ders', 0, 'UTF-8') !== false, "'$key' says ders");
	}
}
