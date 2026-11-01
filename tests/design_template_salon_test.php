<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Beauty Salon design template's parts
 * (includes/design_templates/pinegrap-salon/): the salon's data hangs
 * together, the public pages load with their keys and root trees, every
 * page link names a page of the template, the search anchors are unique,
 * and the booking form keeps what the Clinic's request flow relies on
 * (field names, the date rule, the office-only status, the consent link,
 * the e-mail pages).
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
function _test_salon_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-salon';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$public = include $dir . '/pages_public.php';
	$booking = include $dir . '/booking.php';
	$standard = include $dir . '/standard.php';
	$out = array(
		'categories' => $categories,
		'services'   => $services,
		'team'       => $team,
		'pages'      => $public,
		'booking'    => $booking,
		'standard'   => $standard,
		'shared'     => array($account_bar_tree(), $header(), $footer(), $booking_band(), $notice(), $email_header_tree(), $email_footer_tree()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_salon_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_salon_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_salon_page_keys()
{
	return array(
		'home', 'services', 'team', 'lookbook', 'about',
		'appointment', 'appointment_view', 'my_appointments', 'privacy',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post',
		'my_account', 'search', 'staff', 'error', 'forgot_password',
		'email_appointment_new', 'email_appointment_received', 'email_reply',
		'email_new_message', 'email_message_received', 'email_welcome',
		'blog_new', 'login', 'register',
	);
}

function test_salon_data_hangs_together()
{
	$d = _test_salon_load();
	foreach ($d['services'] as $key => $s) {
		pg_assert_true(isset($d['categories'][$s['category']]), "service $key: known category");
		pg_assert_true($s['title'] !== '' && $s['price'] !== '' && $s['duration'] !== '', "service $key: title, price, duration");
	}
	foreach ($d['team'] as $key => $t) {
		foreach ($t['categories'] as $c) pg_assert_true(isset($d['categories'][$c]), "team $key: known category $c");
	}
	foreach ($d['categories'] as $key => $c) {
		$used = false;
		foreach ($d['services'] as $s) if ($s['category'] === $key) $used = true;
		pg_assert_true($used, "category $key has a service");
	}
}

function test_salon_public_pages_load()
{
	$d = _test_salon_load();
	$keys = array();
	foreach ($d['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree']), 'page has key, name, title, tree');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		pg_assert_same('public', $p['folder'], $p['key'] . ': public folder');
		$keys[] = $p['key'];
	}
	pg_assert_same(array('home', 'services', 'team', 'lookbook', 'about'), $keys);
}

// A {{page:<key>}} placeholder anywhere in a page or shared part names a
// page of the finished template.
function test_salon_page_links_name_template_pages()
{
	$d = _test_salon_load();
	$known = array_flip(_test_salon_page_keys());
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	$json = json_encode($trees);
	preg_match_all('/\{\{page:([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count($m[1]) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// Search anchors are unique per page, and every category and team member
// has one on its page.
function test_salon_search_sections()
{
	$d = _test_salon_load();
	$by_page = array();
	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_salon_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) {
			if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		}
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		$by_page[$p['key']] = $ids;
	}
	foreach (array_keys($d['categories']) as $key) pg_assert_true(in_array('category-' . $key, $by_page['services'], true), "services: #category-$key");
	foreach (array_keys($d['team']) as $key) pg_assert_true(in_array('member-' . $key, $by_page['team'], true), "team: #member-$key");
}

// A link's window is its target prop, never an _attrs target
// (designer/link-target-in-attrs.md); text is never left empty on a link.
function test_salon_links_have_no_attrs_target()
{
	$d = _test_salon_load();
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	$nodes = array();
	foreach ($trees as $t) _test_salon_nodes($t, $nodes);
	foreach ($nodes as $n) {
		foreach (isset($n['props']['_attrs']) ? $n['props']['_attrs'] : array() as $a) {
			pg_assert_true($a['name'] !== 'target', 'no target in _attrs');
		}
	}
}

// Every page and widget tree of the booking part, with the shared parts.
function _test_salon_booking_trees()
{
	$d = _test_salon_load();
	$trees = $d['shared'];
	foreach ($d['booking']['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['booking']['widgets'] as $w) $trees[] = $w['tree'];
	return $trees;
}

// The booking pages, their folders, and every page or tab placeholder in
// pages, widgets and settings names a page of the template.
function test_salon_booking_pages()
{
	$d = _test_salon_load();
	$keys = array();
	foreach ($d['booking']['pages'] as $p) {
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		$keys[$p['key']] = $p['folder'];
	}
	pg_assert_same(array(
		'appointment' => 'public', 'appointment_view' => 'registration', 'my_appointments' => 'registration', 'privacy' => 'public',
		'email_appointment_new' => 'private', 'email_appointment_received' => 'private', 'email_reply' => 'private',
	), $keys);

	$known = array_flip(_test_salon_page_keys());
	$json = json_encode(array($d['booking'], _test_salon_booking_trees()));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "placeholder names a template page: $key");
	foreach ($d['booking']['widgets'] as $wkey => $w) {
		pg_assert_true($w['page'] === '' || isset($known[$w['page']]), "widget $wkey sits on a template page");
	}
}

// The appointment form: the form settings, the named fields, the date
// rule, the office-only status and the consent box with its privacy link.
function test_salon_booking_form()
{
	$d = _test_salon_load();
	$form = null;
	foreach ($d['booking']['pages'] as $p) if ($p['key'] === 'appointment') $form = $p['form'];
	pg_assert_same(1, $form['auto_registration'], 'auto-registration');
	pg_assert_same('{{tab:appointment_view}}', $form['confirmation_page_id']);
	pg_assert_same('{{tab:email_appointment_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_appointment_received}}', $form['confirm_page_id']);

	$nodes = array();
	_test_salon_nodes($d['booking']['widgets']['appointment_form']['tree'], $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		foreach (isset($n['props']['_attrs']) ? $n['props']['_attrs'] : array() as $a) {
			if ($a['name'] === 'name') $fields[$a['value']] = $n;
		}
	}
	foreach (array('service', 'stylist', 'preferred_date', 'preferred_time', 'first_name', 'last_name', 'email', 'phone', 'first_visit', 'notes', 'status', 'consent') as $name) {
		pg_assert_true(isset($fields[$name]), "field $name");
	}
	pg_assert_same(array('min_date' => 'today'), $fields['preferred_date']['props']['_cf']['rules'], 'no date in the past');
	pg_assert_true(!empty($fields['status']['props']['_cf']['office_use_only']), 'status is office use only');
	pg_assert_same(lang('Requested'), $fields['status']['children'][0]['props']['text'], 'every request starts as Requested');
	pg_assert_same(count($d['services']) + 1, count($fields['service']['children']), 'a prompt and every service');
	pg_assert_same(count($d['team']) + 1, count($fields['stylist']['children']), 'No preference and every stylist');

	$consent = null;
	foreach ($nodes as $n) if (isset($n['props']['_label']) && $n['props']['_label'] === lang('Consent')) $consent = $n;
	pg_assert_true($consent !== null, 'consent box');
	pg_assert_same('{{page:privacy}}', $consent['children'][2]['props']['href'], 'consent links the privacy page');
}

// The request's page keeps it to its client; the lists read the form.
function test_salon_booking_views()
{
	$w = _test_salon_load()['booking']['widgets'];
	pg_assert_same('submitter_only', $w['appointment_detail']['config']['access_control']);
	foreach (array('appointment_detail', 'my_appointments_list', 'staff_appointments', 'email_appointment_admin', 'email_appointment_client') as $key) {
		pg_assert_same('{{tab:appointment}}', $w[$key]['config']['custom_form_page_id'], "$key reads the Appointments form");
	}
	pg_assert_true(!empty($w['my_appointments_list']['config']['viewer_filter_submitter']), 'My Appointments shows the client\'s own');
	pg_assert_same('current date', $w['staff_appointments']['config']['filters'][0]['dynamic_value'], 'staff list from today on');

	// The booking card's values carry their bindings on the dd itself.
	$nodes = array();
	_test_salon_nodes($w['appointment_detail']['tree'], $nodes);
	$bound = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['tag']) && $n['props']['tag'] === 'dd' && isset($n['props']['_bindings']['text'])) $bound[] = $n['props']['_bindings']['text'];
	}
	pg_assert_same(array('preferred_date', 'preferred_time', 'stylist', 'reference_code'), $bound, 'detail values bound');
}

// The standard pages of the first part, their folders, and every page or
// tab placeholder in them names a page of the template; every widget sits
// on a template page or in a shared part.
function test_salon_standard_pages()
{
	$d = _test_salon_load();
	$keys = array();
	foreach ($d['standard']['pages'] as $p) {
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		$keys[$p['key']] = $p['folder'];
	}
	pg_assert_same(array(
		'contact' => 'public', 'newsletter' => 'public', 'blog' => 'public', 'blog_post' => 'public',
		'inbox' => 'registration', 'conversation' => 'registration', 'search' => 'public', 'error' => 'public',
		'email_new_message' => 'private', 'email_message_received' => 'private', 'email_welcome' => 'private',
	), $keys);

	$known = array_flip(_test_salon_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['standard']), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "placeholder names a template page: $key");
	foreach ($d['standard']['widgets'] as $wkey => $w) {
		pg_assert_true($w['page'] === '' || isset($known[$w['page']]), "widget $wkey sits on a template page");
	}
}

// A message becomes a conversation the sender follows from the inbox; the
// newsletter's sign-ups land in its contact group, from the page and the
// band alike.
function test_salon_contact_and_newsletter()
{
	$d = _test_salon_load();
	$forms = array();
	foreach ($d['standard']['pages'] as $p) if (isset($p['form'])) $forms[$p['key']] = $p['form'];
	pg_assert_same(1, $forms['contact']['auto_registration']);
	pg_assert_same('{{tab:conversation}}', $forms['contact']['confirmation_page_id']);
	pg_assert_same('{{contact_group:newsletter}}', $forms['newsletter']['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $forms['newsletter']['confirm_page_id']);

	$w = $d['standard']['widgets'];
	pg_assert_same('{{tab:newsletter}}', $w['newsletter_band']['config']['form_id'], 'the band posts to the newsletter page form');
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control']);
	pg_assert_same('{{tab:contact}}', $w['inbox_list']['config']['custom_form_page_id']);
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key reads the blog form");
	}
}

// No word of the Clinic template the salon parts were shaped after is left
// in what a visitor reads.
function test_salon_no_clinic_words()
{
	$d = _test_salon_load();
	$json = json_encode(array($d['pages'], $d['booking'], $d['standard'], $d['shared']), JSON_UNESCAPED_UNICODE);
	foreach (array('doctor', 'patient', 'practice', 'prescription', 'vaccination', 'clinic', 'health letter', 'cl-') as $word) {
		pg_assert_true(stripos($json, $word) === false, "no '$word'");
	}
}
