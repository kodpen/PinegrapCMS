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
 * the e-mail pages), and the account and staff parts place only defined
 * widgets and build every page the links name. Its comment threads are
 * drawn by the Comments widget (a post's comments, a booking's messages, a
 * conversation's replies, the reply e-mail's card) and a Google Reviews
 * widget stands beside the sample words of the home page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/template_threads.php');

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
	$account = include $dir . '/account.php';
	$out = array(
		'categories' => $categories,
		'services'   => $services,
		'team'       => $team,
		'pages'      => $public,
		'booking'    => $booking,
		'standard'   => $standard,
		'account'    => $account,
		'samples'    => $sample_posts,
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
		'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory',
		'staff_entry', 'email_account_welcome', 'email_password_reset',
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
	$json = json_encode(array($d['pages'], $d['booking'], $d['standard'], $d['account'], $d['shared']), JSON_UNESCAPED_UNICODE);
	foreach (array('doctor', 'patient', 'practice', 'prescription', 'vaccination', 'clinic', 'health letter', 'cl-') as $word) {
		pg_assert_true(stripos($json, $word) === false, "no '$word'");
	}
}

// The account pages and the staff area, their folders; every page or tab
// placeholder names a page of the template; every widget sits on a
// template page or in a shared part, and every widget a page places is
// defined by one of the parts.
function test_salon_account_pages()
{
	$d = _test_salon_load();
	$keys = array();
	foreach ($d['account']['pages'] as $p) {
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		$keys[$p['key']] = $p['folder'];
	}
	pg_assert_same(array(
		'login' => 'public', 'register' => 'public', 'forgot_password' => 'public', 'set_password' => 'public', 'logout' => 'public',
		'my_account' => 'registration', 'profile' => 'registration', 'change_password' => 'registration',
		'email_preferences' => 'registration', 'address_book' => 'registration', 'staff_directory' => 'registration',
		'staff' => 'private', 'blog_new' => 'private', 'staff_entry' => 'private',
		'email_account_welcome' => 'private', 'email_password_reset' => 'private',
	), $keys);

	$known = array_flip(_test_salon_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['account']), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "placeholder names a template page: $key");
	foreach ($d['account']['widgets'] as $wkey => $w) {
		pg_assert_true($w['page'] === '' || isset($known[$w['page']]), "widget $wkey sits on a template page");
	}

	// Every page key of every part is one of the finished template's, once.
	$all = array();
	foreach (array($d['pages'], $d['booking']['pages'], $d['standard']['pages'], $d['account']['pages']) as $list) {
		foreach ($list as $p) $all[] = $p['key'];
	}
	pg_assert_same(count($all), count(array_unique($all)), 'no page key twice');
	$missing = array_diff(_test_salon_page_keys(), $all);
	pg_assert_same(array(), array_values($missing), 'every page the links name is built');

	// Every widget placed anywhere is defined, and defined once.
	$widgets = array_merge($d['booking']['widgets'], $d['standard']['widgets'], $d['account']['widgets']);
	pg_assert_same(count($d['booking']['widgets']) + count($d['standard']['widgets']) + count($d['account']['widgets']), count($widgets), 'no widget key twice');
	$trees = $d['shared'];
	foreach (array($d['pages'], $d['booking']['pages'], $d['standard']['pages'], $d['account']['pages']) as $list) {
		foreach ($list as $p) $trees[] = $p['tree'];
	}
	$nodes = array();
	foreach ($trees as $t) _test_salon_nodes($t, $nodes);
	$placed = 0;
	foreach ($nodes as $n) {
		if (!isset($n['props']['templateWidget'])) continue;
		$placed++;
		pg_assert_true(isset($widgets[$n['props']['templateWidget']]), 'placed widget defined: ' . $n['props']['templateWidget']);
	}
	pg_assert_true($placed > 20, 'widgets placed');
}

// The staff area places the coming bookings; the blog's form carries the
// sample posts with the fields the blog widgets read; the account bar's
// login region links the account and staff pages.
function test_salon_staff_and_account()
{
	$d = _test_salon_load();
	$pages = array();
	foreach ($d['account']['pages'] as $p) $pages[$p['key']] = $p;
	pg_assert_true(strpos(json_encode($pages['staff']['tree']), '"staff_appointments"') !== false, 'staff page places staff_appointments');
	pg_assert_same('ecommerce', $pages['address_book']['requires']);
	pg_assert_true(!empty($pages['staff']['noindex']), 'staff area is noindex');

	$form = $pages['blog_new']['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same(4, count($form['sample_records']));
	foreach ($form['sample_records'] as $r) {
		pg_assert_same(array('title', 'summary', 'content', 'cover_image'), array_keys($r), 'sample post fields');
	}
	pg_assert_same('{{tab:staff_directory}}', $pages['staff_entry']['form']['confirmation_page_id']);

	$w = $d['account']['widgets'];
	pg_assert_same('', $w['login_region']['page'], 'login region sits in the account bar');
	pg_assert_same('{{tab:my_account}}', $w['login_region']['config']['account_page_id']);
	pg_assert_same('{{tab:staff}}', $w['login_region']['config']['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);
	pg_assert_same('{{tab:staff_entry}}', $w['staff_directory']['config']['custom_form_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_true(strpos(json_encode($w['account_email_reset']['tree']), '__set_password_url') !== false, 'reset e-mail carries the set password link');
}

// The assembled template: the parts' pages and widgets, in their order,
// with the shared parts, the contact group and the template's own look.
// Every psl- class the trees use has its rule in User Styles, and every
// psl- rule is used.
function test_salon_assembled_template()
{
	$tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-salon.php');
	pg_assert_true(is_array($tpl), 'the template loads');
	$d = _test_salon_load();
	$keys = array();
	foreach ($tpl['pages'] as $p) $keys[] = $p['key'];
	$expected = array();
	foreach (array($d['pages'], $d['booking']['pages'], $d['standard']['pages'], $d['account']['pages']) as $list) {
		foreach ($list as $p) $expected[] = $p['key'];
	}
	pg_assert_same($expected, $keys, 'every part\'s pages, in order');
	pg_assert_same(count($d['booking']['widgets']) + count($d['standard']['widgets']) + count($d['account']['widgets']), count($tpl['widgets']));
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'booking_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	pg_assert_true(isset($tpl['contact_groups']['newsletter']), 'the newsletter contact group');
	pg_assert_same('1.1.0', $tpl['version']);

	// The contact page's details are a search section.
	foreach ($tpl['pages'] as $p) {
		if ($p['key'] !== 'contact') continue;
		$nodes = array();
		_test_salon_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_true(in_array('contact-details', $ids, true) && in_array('getting-here', $ids, true), 'contact search sections');
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(psl-[a-z0-9-]+)/', $css, $m);
	$ruled = array_unique($m[1]);
	preg_match_all('/\bpsl-[a-z0-9-]+/', json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared'])), $m);
	$used = array_unique($m[0]);
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'psl- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

// The salon's threads: widget key => its page, the record view above it,
// its options, Reply and Share, the follow boxes.
function _test_salon_threads()
{
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'appointment_messages' => array('page' => 'appointment_view', 'view' => 'appointment_detail', 'reply' => false, 'watch' => false,
			'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0)),
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation', 'reply' => false, 'watch' => false,
			'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0)),
	);
}

// No fixed comments block is left; the widget kinds the salon ships.
function test_salon_comment_widgets()
{
	$tpl = _tpl_threads_tpl('pinegrap-salon');
	_tpl_threads_check_no_fixed_block('pinegrap-salon', array(
		'appointment_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments', 'post_comments' => 'comments',
	));
	pg_assert_same('google_reviews', pg_sw_widget_kind($tpl['widgets']['google_reviews']['config']), 'and a Google Reviews widget');
	// Each thread keeps its page's comment settings: signed-in writers,
	// published at once, its label and its e-mail.
	$labels = array('blog_post' => lang('Comment'), 'appointment_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
}

function test_salon_comment_threads()
{
	foreach (_test_salon_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-salon', $key, $t);
		_tpl_threads_check_draws('pinegrap-salon', $key, $t);
	}
	// A booking's messages are as private as the booking: the view above
	// them shows it to its client only.
	$tpl = _tpl_threads_tpl('pinegrap-salon');
	foreach (array('appointment_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the client\'s own');
	}
}

function test_salon_comment_email()
{
	_tpl_threads_check_email_card('pinegrap-salon', 'email_comment', 'email_reply', array('blog_post', 'appointment_view', 'conversation'));
}

function test_salon_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-salon', 'google_reviews', 'home', lang('Testimonials'));
	// The sample words stay, before the widget.
	$tpl = _tpl_threads_tpl('pinegrap-salon');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	$sample = strpos($home, lang('“Leyla did my wedding make-up. It looked perfect in every photo and lasted until the last dance.”'));
	$widget = strpos($home, '"templateWidget":"google_reviews"');
	pg_assert_true($sample !== false && $widget !== false && $sample < $widget, 'the sample words stay, the widget after them');
}

// The booking links of the team and services pages carry what was chosen
// (#4801): each value lands in a booking form field that starts with it
// from the page address, and a pick list's value is one of its answers.
function test_salon_booking_links_fill_the_form()
{
	$d = _test_salon_load();
	$controls = _pg_cf_controls($d['booking']['widgets']['appointment_form']['tree']);
	$map = array();
	foreach ($controls as $key => $c) {
		$map[$key] = array('type' => isset($c['type']) ? $c['type'] : '', 'office_use_only' => '0', 'maxlength' => '0');
	}
	foreach (array('service' => 'service', 'stylist' => 'stylist', 'notes' => 'notes') as $field => $param) {
		pg_assert_same($param, isset($controls[$field]['url_param']) ? $controls[$field]['url_param'] : '', $field . ' starts from ?' . $param);
	}

	$links = array();
	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_salon_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			$href = isset($n['props']['href']) ? (string)$n['props']['href'] : '';
			if (strpos($href, '{{page:appointment}}?') === 0) $links[] = array($p['key'], $href);
		}
	}
	$by_page = array();
	foreach ($links as $l) $by_page[$l[0]][] = $l[1];
	pg_assert_same(count($d['team']), count(isset($by_page['team']) ? $by_page['team'] : array()), 'each stylist\'s card books with them');
	pg_assert_same(3, count(isset($by_page['services']) ? $by_page['services'] : array()), 'each package books it');

	$stylists = 0;
	$notes = 0;
	foreach ($links as $l) {
		parse_str((string)parse_url(substr($l[1], strlen('{{page:appointment}}')), PHP_URL_QUERY), $request);
		pg_assert_true(!empty($request), $l[0] . ': a choice in ' . $l[1]);
		$values = pg_cf_url_prefill_values($controls, $map, $request);
		foreach ($request as $param => $value) {
			$field = array_search($param, array('service' => 'service', 'stylist' => 'stylist', 'notes' => 'notes'), true);
			pg_assert_true($field !== false, $l[0] . ': ?' . $param . ' names a field');
			pg_assert_same($value, isset($values[$field]) ? $values[$field] : null, $l[0] . ': ' . $field . ' starts with ' . $value);
			if (!empty($controls[$field]['options'])) {
				$options = array();
				foreach ($controls[$field]['options'] as $o) $options[] = (string)$o['value'];
				pg_assert_same($value, pg_cf_match_option($value, $options), $l[0] . ': ' . $value . ' is an answer of ' . $field);
			}
			$stylists += ($field === 'stylist') ? 1 : 0;
			$notes += ($field === 'notes') ? 1 : 0;
		}
	}
	pg_assert_same(count($d['team']), $stylists, 'every stylist named as the list names them');
	pg_assert_same(3, $notes, 'every package goes into the notes');
}
