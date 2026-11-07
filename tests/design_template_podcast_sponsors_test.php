<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Podcast Network design template's e-mail builders and
 * sponsorship part (includes/design_templates/pinegrap-podcast/mail.php and
 * sponsors.php): the pages and widgets with their keys and folders, the
 * sponsorship page showing every figure, package and past sponsor's words,
 * the sponsorship enquiry and the guest pitch (their settings, fields, the
 * choices a link makes, the staff's status, consent, CAPTCHA, the private
 * attachment, the e-mails), the request pages for their sender, My
 * Enquiries holding both lists, the staff's searches, the e-mail pages,
 * every page link naming a page of the finished template and none a shop
 * page, the template's own ids and classes, the e-mails' sunset colours,
 * no fixed date and every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template, the shop's pages and the node
// walk.
require_once(__DIR__ . '/design_template_podcast_test.php');

// parts.php, mail.php and sponsors.php in one scope, as the template file
// reads them. Read once per run.
function _test_ppds_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/sponsors.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'network' => $network,
		'audience' => $audience, 'packages' => $packages, 'sponsor_quotes' => $sponsor_quotes,
		'package_titles' => $package_titles, 'podcast_titles' => $podcast_titles, 'sponsor_goals' => $sponsor_goals,
		'budget_choices' => $budget_choices, 'enquiry_statuses' => $enquiry_statuses, 'pitch_statuses' => $pitch_statuses,
		'contact_choices' => $contact_choices, 'header' => $email_header_tree(), 'footer' => $email_footer_tree());
	return $out;
}

// The name, type, options, required state and _cf of every form control
// of a tree; a radio group once, under its name.
function _test_ppds_controls($node)
{
	$nodes = array();
	_test_ppd_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$attrs = array();
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) $attrs[$a['name']] = $a['value'];
		$name = $attrs['name'] ?? '';
		$type = $attrs['type'] ?? '';
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		if ($type === 'radio') {
			if (!isset($out[$name])) $out[$name] = array('tag' => $tag, 'type' => $type, 'options' => array(), 'required' => false, 'cf' => array());
			$out[$name]['options'][] = $attrs['value'];
			continue;
		}
		$out[$name] = array('tag' => $tag, 'type' => $type, 'options' => $options, 'required' => isset($attrs['required']), 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

// The tokens a tree's bindings read.
function _test_ppds_tokens($tree)
{
	$nodes = array();
	_test_ppd_nodes($tree, $nodes);
	$tokens = array();
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $t) $tokens[$t] = true;
	}
	return array_keys($tokens);
}

// The texts of a tree.
function _test_ppds_texts($tree)
{
	$nodes = array();
	_test_ppd_nodes($tree, $nodes);
	$texts = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['text'])) $texts[] = $n['props']['text'];
	}
	return $texts;
}

function test_podcast_sponsors_pages_and_widgets()
{
	$d = _test_ppds_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$folders = array('sponsor' => 'public', 'sponsor_enquiry' => 'public', 'enquiry_view' => 'registration', 'guest_pitch' => 'public',
		'pitch_view' => 'registration', 'my_enquiries' => 'registration', 'staff_enquiries' => 'private', 'staff_pitches' => 'private',
		'privacy' => 'public', 'email_enquiry_new' => 'private', 'email_enquiry_received' => 'private', 'email_pitch_new' => 'private',
		'email_pitch_received' => 'private', 'email_reply' => 'private');
	pg_assert_same(array_keys($folders), array_keys($d['pages']));
	pg_assert_same(array('sponsor_enquiry_form', 'sponsor_enquiry_detail', 'sponsor_enquiry_messages', 'guest_pitch_form', 'guest_pitch_detail',
		'guest_pitch_messages', 'my_enquiries_list', 'my_pitches_list', 'staff_enquiries_list', 'staff_pitches_list', 'email_enquiry_admin',
		'email_enquiry_client', 'email_pitch_admin', 'email_pitch_client', 'email_comment'), array_keys($d['widgets']));
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_same($folders[$key], $p['folder'], "$key: its folder");
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_false(isset($names[$p['name']]), "$key: its page name once");
		$names[$p['name']] = true;
		if (strpos($key, 'email_') === 0) {
			pg_assert_true(!empty($p['noindex']) && $p['search'] === false && $p['sitemap'] === false, "$key: an e-mail page out of search");
		}
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_false(isset($w['requires']), "$key stands without the shop");
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	// The reply e-mail keeps the System region after its card, for an
	// e-mail the widget does not draw.
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the System region');
}

// The sponsorship page shows the network's figures, every package with
// the way to ask about it, every past sponsor's words and the ways to an
// enquiry.
function test_podcast_sponsors_page_shows_everything()
{
	$d = _test_ppds_load();
	$tree = $d['pages']['sponsor']['tree'];
	$texts = _test_ppds_texts($tree);
	foreach ($d['audience'] as $n => $f) {
		pg_assert_true(in_array($f['value'], $texts, true) && in_array($f['label'], $texts, true), "figure $n is shown");
	}
	foreach ($d['packages'] as $key => $p) {
		pg_assert_true(in_array($p['title'], $texts, true), "package $key is shown");
		pg_assert_contains(json_encode('{{page:sponsor_enquiry}}?package=' . rawurlencode($p['title'])), json_encode($tree), "package $key opens the enquiry with it chosen");
	}
	foreach ($d['sponsor_quotes'] as $n => $q) pg_assert_true(in_array($q['quote'], $texts, true), "quote $n is shown");
	$json = json_encode($tree);
	pg_assert_contains('{{page:sponsor_enquiry}}', $json, 'the way to an enquiry');
	pg_assert_contains('mailto:' . $d['network']['sponsorship'], $json, 'the sponsorship address');
	pg_assert_contains('"templateShared":"subscribe_band"', $json, 'the page ends with the subscribe band');
	foreach (array('pd-sponsor-audience', 'pd-sponsor-packages') as $id) pg_assert_contains('"id":"' . $id . '","searchSection":true', $json, "$id is a search section");
	pg_assert_contains('"id":"pd-your-data","searchSection":true', json_encode($d['pages']['privacy']['tree']), 'the privacy text is a search section');
}

// Both forms: an account for a new sender, the request's own page, the
// e-mails to the network and to the sender.
function test_podcast_sponsors_form_settings()
{
	$d = _test_ppds_load();
	$forms = array(
		'sponsor_enquiry' => array(lang('Sponsorship Enquiries'), 'enquiry_view', 'email_enquiry_new', 'email_enquiry_received', '^^company^^'),
		'guest_pitch'     => array(lang('Guest Pitches'), 'pitch_view', 'email_pitch_new', 'email_pitch_received', '^^guest_name^^'),
	);
	foreach ($forms as $key => $f) {
		$form = $d['pages'][$key]['form'];
		pg_assert_same($f[0], $form['form_name'], "$key: its form");
		pg_assert_same(1, $form['auto_registration'], "$key: a sender gets an account");
		pg_assert_same('{{tab:' . $f[1] . '}}', $form['confirmation_page_id'], "$key opens its own page");
		pg_assert_same('{{site_email}}', $form['notify_email'], "$key: the network is e-mailed");
		pg_assert_same('{{tab:' . $f[2] . '}}', $form['notify_page_id'], "$key: the network's e-mail");
		pg_assert_same(1, $form['confirm_email'], "$key: the sender is e-mailed");
		pg_assert_same('{{tab:' . $f[3] . '}}', $form['confirm_page_id'], "$key: the sender's e-mail");
		pg_assert_contains($f[4], $form['notify_subject'], "$key: the subject names the request");
		pg_assert_false(isset($form['collection']), "$key: requests, not a collection");
	}
}

// The fields of each form, the choices a link makes, the staff's status,
// consent with the privacy link, the CAPTCHA and the private attachment.
function test_podcast_sponsors_form_fields()
{
	$d = _test_ppds_load();
	$se = _test_ppds_controls($d['widgets']['sponsor_enquiry_form']['tree']);
	pg_assert_same(array('package', 'podcast', 'goal', 'budget', 'start', 'details', 'attachment', 'first_name', 'last_name', 'company',
		'website', 'email', 'phone', 'contact_by', 'status', 'consent'), array_keys($se), 'the enquiry\'s fields');
	pg_assert_same($d['package_titles'], $se['package']['options'], 'the packages');
	pg_assert_same('package', $se['package']['cf']['url_param'] ?? '', 'the package starts from ?package=');
	pg_assert_same($d['podcast_titles'], $se['podcast']['options'], 'the podcasts');
	pg_assert_same('podcast', $se['podcast']['cf']['url_param'] ?? '', 'the podcast starts from ?podcast=');
	pg_assert_false($se['podcast']['required'], 'any podcast may fit');
	pg_assert_same($d['sponsor_goals'], $se['goal']['options'], 'the goals');
	pg_assert_same($d['budget_choices'], $se['budget']['options'], 'the budget bands');
	pg_assert_false($se['budget']['required'], 'the budget may be agreed later');
	pg_assert_same('date', $se['start']['type'], 'the start is a date');
	pg_assert_same('today', $se['start']['cf']['rules']['min_date'] ?? '', 'no start in the past');
	pg_assert_false($se['start']['required'], 'the start may be open');
	pg_assert_same('url', $se['website']['type'], 'the website is an address');
	pg_assert_true($se['company']['required'], 'the company is asked');
	foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone', 'company' => 'company') as $name => $field) {
		pg_assert_same($field, $se[$name]['cf']['contact_field'] ?? '', "enquiry: $name fills the contact");
	}
	$gp = _test_ppds_controls($d['widgets']['guest_pitch_form']['tree']);
	pg_assert_same(array('who', 'guest_name', 'podcast', 'topic', 'story', 'links', 'attachment', 'first_name', 'last_name', 'email',
		'phone', 'contact_by', 'status', 'consent'), array_keys($gp), 'the pitch\'s fields');
	pg_assert_same(array(lang('Myself'), lang('Someone I know')), $gp['who']['options'], 'who the guest is');
	pg_assert_same($d['podcast_titles'], $gp['podcast']['options'], 'the podcasts');
	pg_assert_same('podcast', $gp['podcast']['cf']['url_param'] ?? '', 'the podcast starts from ?podcast=');
	pg_assert_false($gp['links']['required'], 'links are optional');
	$forms = array('sponsor_enquiry_form' => array($se, $d['enquiry_statuses']), 'guest_pitch_form' => array($gp, $d['pitch_statuses']));
	foreach ($forms as $key => $f) {
		list($c, $statuses) = $f;
		pg_assert_true(!empty($c['status']['cf']['office_use_only']), "$key: the status is the staff's");
		pg_assert_same($statuses, $c['status']['options'], "$key: the statuses");
		pg_assert_same('{{folder:private}}', $c['attachment']['cf']['upload_folder_id'] ?? '', "$key: an attachment is kept in the private folder");
		pg_assert_same('file', $c['attachment']['type'], "$key: the attachment is a file");
		pg_assert_true($c['consent']['required'], "$key: consent is required");
		$contact = array();
		foreach ($d['contact_choices'] as $o) $contact[] = $o[0];
		pg_assert_same($contact, $c['contact_by']['options'], "$key: how to reach the sender");
		$json = json_encode($d['widgets'][$key]['tree']);
		pg_assert_contains('{{page:privacy}}', $json, "$key: the consent links the privacy page");
		pg_assert_contains('"section":"captcha"', $json, "$key: a CAPTCHA");
		pg_assert_same(array('regionType' => 'custom_form', 'form_source' => 'page'), $d['widgets'][$key]['config'], "$key: the page's form");
	}
}

// Every value a request page, a list or an e-mail reads is a field of its
// form, or the renderer's own; the request pages are their sender's, the
// e-mails drawn for the submission just sent.
function test_podcast_sponsors_request_pages()
{
	$d = _test_ppds_load();
	$w = $d['widgets'];
	$own = array('form_item_view', 'not_found', 'reference_code', 'number_of_comments', '__edit_url', 'can_edit', '__new_account_email',
		'__new_account_set_password_url', 'has_set_password_link', 'has_new_account', 'record_found', 'record_not_found', 'submitted_this_session');
	$forms = array(
		'sponsor_enquiry' => array('sponsor_enquiry_form', 'enquiry_view', 'sponsor_enquiry_detail', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client'),
		'guest_pitch'     => array('guest_pitch_form', 'pitch_view', 'guest_pitch_detail', 'my_pitches_list', 'staff_pitches_list', 'email_pitch_admin', 'email_pitch_client'),
	);
	foreach ($forms as $form_key => $f) {
		$c = _test_ppds_controls($w[$f[0]]['tree']);
		foreach (array_slice($f, 2) as $key) {
			foreach (_test_ppds_tokens($w[$key]['tree']) as $t) {
				pg_assert_true(isset($c[$t]) || in_array($t, $own, true), "$key reads $t, a field of $form_key");
			}
			pg_assert_same('{{tab:' . $form_key . '}}', $w[$key]['config']['custom_form_page_id'], "$key reads $form_key");
		}
		pg_assert_same('submitter_only', $w[$f[2]]['config']['access_control'], "only its sender and the staff open a $form_key");
		pg_assert_contains('"eo_visible_if":"record_not_found"', json_encode($w[$f[2]]['tree']), "$f[2]: the not-found note");
		foreach (array('record_found', 'has_new_account', 'submitted_this_session', 'has_set_password_link', 'can_edit') as $flag) {
			pg_assert_contains('"eo_visible_if":"' . $flag . '"', json_encode($w[$f[2]]['tree']), "$f[2] shows $flag");
		}
		foreach (array_slice($f, 5) as $key) {
			pg_assert_same('public', $w[$key]['config']['access_control'], "$key is drawn for the submission just sent");
			pg_assert_contains('{{page:' . $f[1] . '}}?r=^^reference_code^^', json_encode($w[$key]['tree']), "$key opens the request");
		}
		pg_assert_contains('"eo_visible_if":"has_new_account"', json_encode($w[$f[6]]['tree']), "$f[6]: the new account note");
		pg_assert_contains('"reference_code"', json_encode($w[$f[5]]['tree']), "$f[5]: the reference code");
		// The messages under the request, by e-mail both ways.
		$comments = $d['pages'][$f[1]]['comments'];
		pg_assert_true($comments['allow_new'] && $comments['login'], "$f[1]: signed-in messages");
		pg_assert_same('{{tab:email_reply}}', $comments['email_page'], "$f[1]: messages go by e-mail");
		pg_assert_same('{{site_email}}', $comments['notify_email'], "$f[1]: the network hears of each");
		$messages = str_replace('_detail', '_messages', $f[2]);
		pg_assert_same($f[1], $w[$messages]['page'], "$messages sits on $f[1]");
		pg_assert_same(array('comments', 'oldest', 1, 0), array($w[$messages]['config']['regionType'], $w[$messages]['config']['order'],
			$w[$messages]['config']['max_depth'], $w[$messages]['config']['per_page']), "$messages: oldest first, flat, on one page");
		pg_assert_false(strpos(json_encode($w[$messages]['tree']), '"comment_reply"') !== false, "$messages: no Reply");
	}
	pg_assert_same('comments', $w['email_comment']['config']['regionType']);
	pg_assert_contains('__comment_url', json_encode($w['email_comment']['tree']), 'the reply e-mail links the comment');
}

// My Enquiries lists the viewer's own enquiries and pitches; the staff's
// lists are everybody's, with a search over fields of the form.
function test_podcast_sponsors_lists()
{
	$d = _test_ppds_load();
	$w = $d['widgets'];
	$lists = array(
		'my_enquiries_list'    => array('sponsor_enquiry', 'enquiry_view', false),
		'my_pitches_list'      => array('guest_pitch', 'pitch_view', false),
		'staff_enquiries_list' => array('sponsor_enquiry', 'enquiry_view', true),
		'staff_pitches_list'   => array('guest_pitch', 'pitch_view', true),
	);
	foreach ($lists as $key => $l) {
		$config = $w[$key]['config'];
		pg_assert_same('form_list_view', $config['regionType'], "$key: a list");
		pg_assert_same('{{tab:' . $l[0] . '}}', $config['custom_form_page_id'], "$key lists $l[0]");
		pg_assert_same('{{tab:' . $l[1] . '}}', $config['detail_page_id'], "$key opens $l[1]");
		pg_assert_same(array('submitted_date_and_time', 'DESC', 20), array($config['order_by_field'], $config['order_by_direction'], $config['items_per_page']), "$key: newest first");
		pg_assert_true(trim($config['empty_message']) !== '', "$key: words for an empty list");
		pg_assert_contains('"href":"form_item_view"', json_encode($w[$key]['tree']), "$key: each row opens its request");
		pg_assert_contains('"text":"status"', json_encode($w[$key]['tree']), "$key: each row shows the status");
		if ($l[2]) {
			pg_assert_false(isset($config['viewer_filter']), "$key is everybody's");
			pg_assert_true(!empty($config['search_enabled']) && $config['search_label'] !== '', "$key: a search");
			$fields = _test_ppds_controls($w[$l[0] . '_form']['tree']);
			foreach ($config['search_fields'] as $f) pg_assert_true(isset($fields[$f]), "$key searches $f, a field");
			pg_assert_contains('"text":"phone"', json_encode($w[$key]['tree']), "$key: the staff's row adds the phone");
		} else {
			foreach (array('viewer_filter', 'viewer_filter_submitter', 'viewer_filter_watcher', 'viewer_filter_editor') as $f) {
				pg_assert_true(!empty($config[$f]), "$key: $f");
			}
			pg_assert_contains('"text":"number_of_comments"', json_encode($w[$key]['tree']), "$key: the sender's row shows the messages");
		}
	}
	$json = json_encode($d['pages']['my_enquiries']['tree']);
	foreach (array('my_enquiries_list', 'my_pitches_list') as $key) pg_assert_contains('"templateWidget":"' . $key . '"', $json, "My Enquiries holds $key");
	foreach (array('sponsor_enquiry', 'guest_pitch') as $key) pg_assert_contains('{{page:' . $key . '}}', $json, "My Enquiries links $key");
	pg_assert_same('staff_enquiries', $w['staff_enquiries_list']['page']);
	pg_assert_same('staff_pitches', $w['staff_pitches_list']['page']);
	foreach (array('staff_enquiries', 'staff_pitches') as $key) {
		pg_assert_contains('"templateShared":"staff_bar"', json_encode($d['pages'][$key]['tree']), "$key: a staff page");
	}
}

function test_podcast_sponsors_links_ids_and_classes()
{
	$d = _test_ppds_load();
	$known = array_flip(_test_ppd_page_keys());
	$trees = array($d['header'], $d['footer']);
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_ppd_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
		pg_assert_false(strpos($json, '{{tab:' . $key . '}}') !== false, "no setting names $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_ppd_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_ppd_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmu|pmk|psc|pmz|pai|psh|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'pd-') === 0, "id $id is the template's");
	}
}

// The e-mails are drawn in the sunset palette, each colour of a style with
// its fallback inside var(); the footer names the sponsorship address.
function test_podcast_sponsors_mail_colours()
{
	$d = _test_ppds_load();
	pg_assert_contains($d['network']['sponsorship'], json_encode($d['footer']), 'the e-mail footer names the sponsorship address');
	pg_assert_contains($d['network']['based'], json_encode($d['footer']), 'the e-mail footer says where the network records');
	$mail = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/mail.php');
	preg_match_all('/#[0-9a-f]{6}\b/i', $mail, $colors);
	foreach (array_unique($colors[0]) as $hex) {
		pg_assert_true(in_array(strtolower($hex), array('#c2410c', '#fed7aa', '#fff7ed', '#ffffff', '#1f2937', '#4b5563'), true), "e-mail colour $hex is the template's");
	}
	foreach (array('#c2410c', '#fed7aa', '#fff7ed') as $hex) pg_assert_contains($hex, $mail, "the sunset colour $hex is used");
	preg_match_all('/var\(--[a-z-]+\)/', $mail, $bare);
	pg_assert_same(array(), $bare[0], 'no var() without its fallback');
	pg_assert_contains('var(--bs-primary,#c2410c)', $mail, 'the button keeps its colour in a client that blocks the CSS');
}

// No year or month in the sources: a sponsorship's start is counted from
// today.
function test_podcast_sponsors_no_fixed_dates()
{
	foreach (array('mail', 'sponsors') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/' . $file . '.php');
		$src = preg_replace('/@copyright[^\n]*/', '', $src);
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), "$file: no year in the sample");
		pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), "$file: no month in the sample");
	}
}

// Every literal has its Turkish; an episode reads "bölüm", a guest
// "konuk", a sponsorship "sponsorluk", a pitch "öneri", and no key falls on
// a shared word that reads otherwise.
function test_podcast_sponsors_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail', 'sponsors') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/' . $file . '.php');
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			pg_assert_false(in_array($key, array('Show', 'Shows', 'Season', 'Show Notes', 'Guest', 'Guests', 'Host', 'Listening'), true), "$file: '$key' reads otherwise in Turkish");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			// A page name is an address: written without Turkish letters.
			if (preg_match('/^[a-z-]+$/', $key)) {
				pg_assert_true((bool)preg_match('/^[a-z-]+$/', $tr), "$file: the address '$tr' has no Turkish letters");
				continue;
			}
			if (preg_match('/\b[Ee]pisodes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bölüm', 0, 'UTF-8') !== false, "$file: '$key' says bölüm");
			if (preg_match('/\b[Gg]uests?\b/', $key)) pg_assert_true((bool)preg_match('/konu[kğ]/iu', $tr), "$file: '$key' says konuk");
			if (preg_match('/\b[Ss]ponsorship\b/', $key)) pg_assert_true((bool)preg_match('/sponsorlu[kğ]/iu', $tr), "$file: '$key' says sponsorluk");
			if (preg_match('/\b[Pp]itch(es)?\b/', $key)) pg_assert_true(mb_stripos($tr, 'öneri', 0, 'UTF-8') !== false, "$file: '$key' says öneri");
		}
	}
}
