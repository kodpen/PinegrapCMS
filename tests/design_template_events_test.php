<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Events and Conferences design template's parts
 * (includes/design_templates/pinegrap-events/): the conference's data hangs
 * together (tracks, speakers and their sessions, passes), the public pages
 * load with their keys and root trees, every page link names a page of the
 * finished template, the anchors a link or a search result opens exist and
 * are unique, no fixed date is written into the sample, and the home page
 * places the Google Reviews widget beside its sample testimonials; the
 * events are dated records of the Events form, listed from today on; the
 * registration form keeps what the request flow relies on (field names,
 * the event and pass filled from the address, the office-only status, the
 * consent link, the e-mail pages); its messages and the reply e-mail run
 * on the Comments widget, with no fixed comments block.
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
function _test_events_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-events';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$public = include $dir . '/pages_public.php';
	$events = include $dir . '/events.php';
	$out = array(
		'tracks'    => $tracks,
		'speakers'  => $speakers,
		'programme' => $programme,
		'kinds'     => $session_kinds,
		'passes'    => $passes,
		'sponsors'  => $sponsors,
		'pages'     => $public['pages'],
		'widgets'   => $public['widgets'],
		'events'    => $events,
		'samples'   => $sample_events,
		'shared'    => array($account_bar_tree(), $header(), $footer(), $register_band(), $notice(), $staff_bar(), $email_header_tree(), $email_footer_tree()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_events_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_events_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_events_page_keys()
{
	return array(
		'home', 'programme', 'speakers', 'venue', 'about',
		'events', 'event_view', 'event_new', 'registration', 'registration_view', 'my_registrations', 'privacy',
		'email_registration_new', 'email_registration_received', 'email_reply',
		'tickets', 'product', 'cart',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

function test_events_data_hangs_together()
{
	$d = _test_events_load();
	$sessions = array();
	foreach ($d['programme'] as $day => $p) {
		pg_assert_true(count($p['sessions']) > 3, "$day has sessions");
		foreach ($p['sessions'] as $s) {
			pg_assert_false(isset($sessions[$s['key']]), 'session key ' . $s['key'] . ' is unique');
			$sessions[$s['key']] = $s;
			pg_assert_true(isset($d['kinds'][$s['kind']]), $s['key'] . ': known kind');
			pg_assert_true($s['track'] === '' || isset($d['tracks'][$s['track']]), $s['key'] . ': known track');
			pg_assert_true($s['speaker'] === '' || isset($d['speakers'][$s['speaker']]), $s['key'] . ': known speaker');
			pg_assert_true((bool)preg_match('/^\d{2}:\d{2}$/', $s['time']), $s['key'] . ': time');
		}
	}
	foreach ($d['speakers'] as $key => $s) {
		pg_assert_true(isset($d['tracks'][$s['track']]), "speaker $key: known track");
		pg_assert_true(isset($sessions[$s['session']]), "speaker $key: session in the programme");
		pg_assert_same($key, $sessions[$s['session']]['speaker'], "speaker $key: the session is theirs");
	}
	$skus = array();
	foreach ($d['passes'] as $key => $p) {
		pg_assert_true((bool)preg_match('/^EVENTS-PASS-[A-Z]+$/', $p['sku']), "pass $key: SKU");
		$skus[] = $p['sku'];
		pg_assert_true(count($p['items']) > 0, "pass $key: what it includes");
	}
	pg_assert_same(count($skus), count(array_unique($skus)), 'pass SKUs are unique');
	pg_assert_true(isset($d['sponsors']['headline'], $d['sponsors']['gold'], $d['sponsors']['partner']), 'sponsor tiers');
}

function test_events_public_pages_load()
{
	$d = _test_events_load();
	$keys = array();
	foreach ($d['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['meta_description']), 'page has key, name, title, tree, description');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		pg_assert_same('public', $p['folder'], $p['key'] . ': public folder');
		$keys[] = $p['key'];
	}
	pg_assert_same(array('home', 'programme', 'speakers', 'venue', 'about'), $keys);
}

// A {{page:<key>}} placeholder anywhere in a page or shared part names a
// page of the finished template.
function test_events_page_links_name_template_pages()
{
	$d = _test_events_load();
	$known = array_flip(_test_events_page_keys());
	$trees = $d['shared'];
	foreach (array_merge($d['pages'], $d['events']['pages']) as $p) $trees[] = $p['tree'];
	foreach (array_merge($d['widgets'], $d['events']['widgets']) as $w) $trees[] = $w['tree'];
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json . json_encode($d['events']), $m);
	pg_assert_true(count($m[1]) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// Every id on a page is unique, and a link to an anchor of one of these
// pages (#speaker-elif, #session-d1-keynote, #track-product, #sponsors)
// finds it there.
function test_events_anchors_exist_and_are_unique()
{
	$d = _test_events_load();
	$ids = array();
	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_events_nodes($p['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			if (!isset($n['props']['id']) || $n['props']['id'] === '') continue;
			$id = $n['props']['id'];
			pg_assert_false(isset($seen[$id]), $p['key'] . ": id $id is unique");
			$seen[$id] = true;
		}
		$ids[$p['key']] = $seen;
	}
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	preg_match_all('/\{\{page:(home|programme|speakers|venue|about)\}\}#([a-z0-9-]+)/', json_encode($trees), $m, PREG_SET_ORDER);
	pg_assert_true(count($m) > 8, 'anchor links found');
	foreach ($m as $hit) pg_assert_true(isset($ids[$hit[1]][$hit[2]]), "#{$hit[2]} exists on {$hit[1]}");
}

// The sample never reads as a past edition: no year and no calendar date
// is written into a page, and every widget the pages place is one of the
// finished template's.
function test_events_no_fixed_dates()
{
	$d = _test_events_load();
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	$text = json_encode($trees, JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
	pg_assert_false((bool)preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text), 'no ISO date');
}

function test_events_home_places_widgets()
{
	$d = _test_events_load();
	$home = $d['pages'][0];
	$nodes = array();
	_test_events_nodes($home['tree'], $nodes);
	$placed = array();
	foreach ($nodes as $n) if (isset($n['props']['templateWidget'])) $placed[] = $n['props']['templateWidget'];
	foreach (array('google_reviews', 'home_events', 'home_posts', 'newsletter_band') as $w) {
		pg_assert_true(in_array($w, $placed, true), "home places $w");
	}
	$g = $d['widgets']['google_reviews'];
	pg_assert_same('google_reviews', $g['config']['regionType']);
	pg_assert_same('home', $g['page']);
	$json = json_encode($g['tree']);
	foreach (array('has_reviews', '__review_author_url', '__review_author_name', '__review_url', '__place_maps_url', '__google_attribution') as $token) {
		pg_assert_true(strpos($json, '"' . $token . '"') !== false, "google_reviews binds $token");
	}
}

// A page or widget of the events part by its key.
function _test_events_part($kind, $key)
{
	$d = _test_events_load();
	if ($kind === 'widgets') return isset($d['events']['widgets'][$key]) ? $d['events']['widgets'][$key] : null;
	foreach ($d['events']['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

// The form fields of a tree: name => node.
function _test_events_fields($tree)
{
	$nodes = array();
	_test_events_nodes($tree, $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) {
			if ($a['name'] === 'name' && in_array($n['props']['tag'], array('input', 'select', 'textarea'), true)) $fields[$a['value']] = $n;
		}
	}
	return $fields;
}

function test_events_pages_and_widgets_of_the_events_part()
{
	$d = _test_events_load();
	$keys = array();
	foreach ($d['events']['pages'] as $p) {
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		$keys[] = $p['key'];
	}
	pg_assert_same(array('events', 'event_view', 'event_new', 'registration', 'registration_view', 'my_registrations', 'privacy',
		'email_registration_new', 'email_registration_received', 'email_reply'), $keys);

	// Every widget a page of the part places is defined by the part (or
	// another step's: the newsletter band and the login region), and every
	// widget names a page of the template.
	$own = $d['events']['widgets'];
	$later = array('newsletter_band', 'login_region');
	$known = array_flip(_test_events_page_keys());
	foreach ($d['events']['pages'] as $p) {
		$nodes = array();
		_test_events_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (!isset($n['props']['templateWidget'])) continue;
			$w = $n['props']['templateWidget'];
			pg_assert_true(isset($own[$w]) || in_array($w, $later, true), $p['key'] . ": places a known widget $w");
		}
		pg_assert_false(strpos(json_encode($p['tree']), 'comments_block') !== false, $p['key'] . ': no fixed comments block');
	}
	foreach ($own as $key => $w) {
		pg_assert_true(isset($known[$w['page']]), "widget $key: page {$w['page']}");
		pg_assert_true(isset($w['config']['regionType'], $w['tree']), "widget $key: config and tree");
	}
}

function test_events_samples_are_coming_and_listed_from_today()
{
	$d = _test_events_load();
	$today = date('Y-m-d');
	pg_assert_true(count($d['samples']) >= 4, 'sample events');
	foreach ($d['samples'] as $e) {
		pg_assert_true($e['event_date'] > $today, $e['title'] . ': after today');
		foreach (array('title', 'event_time', 'kind', 'location', 'summary', 'price', 'image') as $f) pg_assert_true($e[$f] !== '', $e['title'] . ": $f");
	}
	$new = _test_events_part('pages', 'event_new');
	pg_assert_same($d['samples'], $new['form']['sample_records']);
	pg_assert_same(1, $new['form']['collection']);
	pg_assert_same('private', $new['folder']);
	$fields = _test_events_fields(_test_events_part('widgets', 'event_form')['tree']);
	foreach (array_keys($d['samples'][0]) as $f) pg_assert_true(isset($fields[$f]), "events form has the field $f");
	foreach (array('home_events', 'events_list') as $key) {
		$c = _test_events_part('widgets', $key)['config'];
		pg_assert_same('{{tab:event_new}}', $c['custom_form_page_id'], "$key: the Events form");
		pg_assert_same('{{tab:event_view}}', $c['detail_page_id'], "$key: the event's page");
		pg_assert_same('event_date', $c['order_by_field'], "$key: by date");
		pg_assert_same('ASC', $c['order_by_direction'], "$key: nearest first");
		pg_assert_same(array(array('field' => 'event_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date')),
			$c['filters'], "$key: from today on");
	}
	// The register button of a card and of the event's page fills the form.
	$json = json_encode(_test_events_part('widgets', 'events_list')['tree']) . json_encode(_test_events_part('widgets', 'event_detail')['tree']);
	pg_assert_same(2, substr_count($json, '"params":[{"name":"event","token":"title"}]'), 'register links send the event');
}

function test_events_registration_form_keeps_the_request_flow()
{
	$page = _test_events_part('pages', 'registration');
	$form = $page['form'];
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:registration_view}}', $form['confirmation_page_id']);
	pg_assert_same('{{tab:email_registration_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_registration_received}}', $form['confirm_page_id']);
	pg_assert_same('{{site_email}}', $form['notify_email']);

	$tree = _test_events_part('widgets', 'registration_form')['tree'];
	$fields = _test_events_fields($tree);
	foreach (array('event', 'pass', 'first_name', 'last_name', 'email', 'phone', 'company', 'job_title', 'diet', 'needs', 'status', 'consent') as $f) {
		pg_assert_true(isset($fields[$f]), "registration form has $f");
	}
	pg_assert_same('event', $fields['event']['props']['_cf']['url_param']);
	pg_assert_same('pass', $fields['pass']['props']['_cf']['url_param']);
	pg_assert_true(!empty($fields['status']['props']['_cf']['office_use_only']), 'status is office use only');
	pg_assert_same('email_address', $fields['email']['props']['_cf']['contact_field']);
	pg_assert_contains('{{page:privacy}}', json_encode($tree), 'consent links the privacy page');
	pg_assert_contains('"section":"captcha"', json_encode($tree), 'CAPTCHA');

	// Every pass of the conference can be chosen, by its title.
	$d = _test_events_load();
	$options = json_encode($fields['pass']['children']);
	foreach ($d['passes'] as $p) pg_assert_contains(json_encode($p['title']), $options, 'pass ' . $p['sku'] . ' is a choice');
}

function test_events_registration_page_thread_and_emails()
{
	$view = _test_events_part('pages', 'registration_view');
	pg_assert_same('registration', $view['folder']);
	pg_assert_same('{{tab:email_reply}}', $view['comments']['email_page']);
	pg_assert_same('{{site_email}}', $view['comments']['notify_email']);
	pg_assert_true($view['comments']['label'] !== lang('Reply'), 'a noun as the comments label');

	$detail = _test_events_part('widgets', 'registration_detail');
	pg_assert_same('submitter_only', $detail['config']['access_control']);
	pg_assert_same('{{tab:registration}}', $detail['config']['custom_form_page_id']);

	$thread = _test_events_part('widgets', 'registration_messages');
	pg_assert_same('comments', $thread['config']['regionType']);
	pg_assert_same('registration_view', $thread['page']);
	$json = json_encode($thread['tree']);
	foreach (array('"comment_form"', '"comment_submit"', '"can_comment"', '"__comment_message_html"') as $token) pg_assert_contains($token, $json, "thread binds $token");
	pg_assert_false(strpos($json, '"comment_reply"') !== false, 'a two-sided thread has no Reply');

	// The reply e-mail: the comment card before the System region, its
	// button bound to the comment's own address.
	$reply = _test_events_part('pages', 'email_reply');
	$nodes = array();
	_test_events_nodes($reply['tree'], $nodes);
	$card = $system = -1;
	foreach ($nodes as $i => $n) {
		if (isset($n['props']['templateWidget']) && $n['props']['templateWidget'] === 'email_comment') $card = $i;
		if (isset($n['props']['regionType']) && $n['props']['regionType'] === 'system') $system = $i;
	}
	pg_assert_true($card >= 0 && $system > $card, 'comment card before the System region');
	pg_assert_contains('"__comment_url"', json_encode(_test_events_part('widgets', 'email_comment')['tree']), 'card links the comment');

	foreach (array('email_registration_admin', 'email_registration_client') as $key) {
		$w = _test_events_part('widgets', $key);
		pg_assert_same('{{tab:registration}}', $w['config']['custom_form_page_id'], "$key: the Registrations form");
		pg_assert_contains('{{page:registration_view}}?r=^^reference_code^^', json_encode($w['tree']), "$key: links the registration");
	}
	foreach (array('email_registration_new', 'email_registration_received', 'email_reply') as $key) {
		$p = _test_events_part('pages', $key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
}
