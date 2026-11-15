<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Music Artist design template's e-mail builders and press
 * part (includes/design_templates/pinegrap-music/mail.php and press.php):
 * the pages and widgets with their keys, the press kit showing the band in
 * three lengths, every quote and member and the press files, the Press
 * Files collection keeping its files in the public press folder, the
 * booking enquiry (its fields, the staff's status, consent, CAPTCHA, the
 * ?event= choice, the e-mails), the enquiry page for its sender, the
 * lists, the e-mail pages, every page link naming a page of the finished
 * template and none a shop page, the template's own ids and classes, and
 * every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_music_test.php');

// parts.php, mail.php and press.php in one scope, as the template file
// reads them. Read once per run.
function _test_mupr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/press.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'members' => $members,
		'press_quotes' => $press_quotes, 'artist' => $artist, 'event_kinds' => $event_kinds,
		'capacity_choices' => $capacity_choices, 'fee_choices' => $fee_choices, 'booking_statuses' => $booking_statuses,
		'header' => $email_header_tree(), 'footer' => $email_footer_tree());
	return $out;
}

// Every node of a tree, depth first.
function _test_mupr_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_mupr_nodes($c, $out);
}

// The name, type, options and _cf of every form control of a tree.
function _test_mupr_controls($node)
{
	$nodes = array();
	_test_mupr_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		$type = '';
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
			if ($a['name'] === 'type') $type = $a['value'];
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		if ($type === 'radio' && isset($out[$name])) continue;
		$out[$name] = array('tag' => $tag, 'type' => $type, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

// The tokens a tree's bindings read.
function _test_mupr_tokens($tree)
{
	$nodes = array();
	_test_mupr_nodes($tree, $nodes);
	$tokens = array();
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $t) $tokens[$t] = true;
	}
	return array_keys($tokens);
}

// The texts of a tree.
function _test_mupr_texts($tree)
{
	$nodes = array();
	_test_mupr_nodes($tree, $nodes);
	$texts = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['text'])) $texts[] = $n['props']['text'];
	}
	return $texts;
}

function test_music_press_pages_and_widgets()
{
	$d = _test_mupr_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('press', 'press_file_new', 'booking', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy',
		'email_booking_new', 'email_booking_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('press_files', 'press_file_form', 'booking_form', 'booking_detail', 'booking_messages', 'my_bookings_list',
		'staff_bookings_list', 'email_booking_admin', 'email_booking_client', 'email_comment'), array_keys($d['widgets']));
	$folders = array('press' => 'public', 'booking' => 'public', 'privacy' => 'public', 'booking_view' => 'registration',
		'my_bookings' => 'registration', 'staff_bookings' => 'private', 'press_file_new' => 'private',
		'email_booking_new' => 'private', 'email_booking_received' => 'private', 'email_reply' => 'private');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_same($folders[$key], $p['folder'], "$key: its folder");
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		if (strpos($key, 'email_') === 0) {
			pg_assert_true(!empty($p['noindex']) && $p['search'] === false && $p['sitemap'] === false, "$key: an e-mail page out of search");
		}
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	// The reply e-mail keeps the System region after its card, for an
	// e-mail the widget does not draw.
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the System region');
}

// The press kit shows the band in three lengths, every quote and member,
// the files and the addresses for press and bookings.
function test_music_press_kit_shows_the_band()
{
	$d = _test_mupr_load();
	$tree = $d['pages']['press']['tree'];
	$texts = _test_mupr_texts($tree);
	pg_assert_true(in_array($d['artist']['tagline'] . '.', $texts, true), 'the one-line bio');
	pg_assert_true(in_array($d['artist']['story'], $texts, true), 'the paragraph bio');
	foreach ($d['members'] as $key => $m) pg_assert_true(in_array($m['name'], $texts, true), "member $key is shown");
	foreach ($d['press_quotes'] as $n => $q) pg_assert_true(in_array($q['quote'], $texts, true), "quote $n is shown");
	$json = json_encode($tree);
	pg_assert_contains('"templateWidget":"press_files"', $json, 'the press files');
	pg_assert_contains('mailto:' . $d['artist']['press'], $json, 'the press address');
	pg_assert_contains('{{page:booking}}', $json, 'the way to a booking enquiry');
	// The Press Files collection: its file goes to the public press folder.
	$form = $d['pages']['press_file_new']['form'];
	pg_assert_same(1, $form['collection'], 'press files are a collection');
	$controls = _test_mupr_controls($d['widgets']['press_file_form']['tree']);
	pg_assert_same(array('title', 'kind', 'summary', 'file'), array_keys($controls), 'the press file fields');
	pg_assert_same('{{folder:press}}', $controls['file']['cf']['upload_folder_id'] ?? '', 'the file is kept in the press folder');
	pg_assert_true(count($form['sample_records']) >= 4, 'sample press files');
	foreach ($form['sample_records'] as $n => $r) {
		foreach ($r as $name => $value) {
			pg_assert_true(isset($controls[$name]), "press sample $n: field $name exists");
			pg_assert_true($value !== '', "press sample $n: $name filled");
		}
		pg_assert_true(in_array($r['kind'], $controls['kind']['options'], true), "press sample $n: a known kind");
	}
	foreach (_test_mupr_tokens($d['widgets']['press_files']['tree']) as $t) {
		pg_assert_true(isset($controls[$t]), "the press file card reads $t, a field");
	}
}

// The booking enquiry: its fields, the event chosen from a link, the date
// from today on, the staff's status, consent with the privacy link, the
// CAPTCHA, an account for a new sender and both e-mails.
function test_music_press_booking_form()
{
	$d = _test_mupr_load();
	$form = $d['pages']['booking']['form'];
	pg_assert_same(1, $form['auto_registration'], 'a promoter gets an account');
	pg_assert_same('{{tab:booking_view}}', $form['confirmation_page_id'], 'the enquiry opens its own page');
	pg_assert_same('{{tab:email_booking_new}}', $form['notify_page_id'], 'the band is e-mailed');
	pg_assert_same('{{tab:email_booking_received}}', $form['confirm_page_id'], 'the promoter is e-mailed');
	pg_assert_same('{{site_email}}', $form['notify_email']);
	$c = _test_mupr_controls($d['widgets']['booking_form']['tree']);
	foreach (array('event', 'event_name', 'event_date', 'city', 'venue', 'capacity', 'fee', 'details', 'attachment',
		'first_name', 'last_name', 'organisation', 'email', 'phone', 'contact_by', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the enquiry asks $name");
	}
	pg_assert_same('{{folder:private}}', $c['attachment']['cf']['upload_folder_id'] ?? '', 'a promoter\'s attachment is kept in the private folder, not the public press folder');
	pg_assert_same('event', $c['event']['cf']['url_param'] ?? '', 'the kind of event starts from ?event=');
	pg_assert_same($d['event_kinds'], $c['event']['options'], 'the kinds of event');
	pg_assert_same($d['capacity_choices'], $c['capacity']['options'], 'the capacities');
	pg_assert_same($d['fee_choices'], $c['fee']['options'], 'the fee bands');
	pg_assert_same('date', $c['event_date']['type'], 'the event date is a date');
	pg_assert_same('today', $c['event_date']['cf']['rules']['min_date'] ?? '', 'no event date in the past');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the staff\'s');
	pg_assert_same($d['booking_statuses'], $c['status']['options'], 'the statuses');
	foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone', 'organisation' => 'company') as $name => $field) {
		pg_assert_same($field, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
	}
	$json = json_encode($d['widgets']['booking_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $json, 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $json, 'a CAPTCHA');
	// Every value an e-mail or the enquiry page reads is a field of the
	// form, or the renderer's own.
	$own = array('form_item_view', 'not_found', 'reference_code', 'number_of_comments', '__edit_url', 'can_edit', '__new_account_email',
		'__new_account_set_password_url', 'has_set_password_link', 'has_new_account', 'record_found', 'record_not_found', 'submitted_this_session');
	foreach (array('booking_detail', 'my_bookings_list', 'staff_bookings_list', 'email_booking_admin', 'email_booking_client') as $key) {
		foreach (_test_mupr_tokens($d['widgets'][$key]['tree']) as $t) {
			pg_assert_true(isset($c[$t]) || in_array($t, $own, true), "$key reads $t, a field of the enquiry");
		}
		if ($key !== 'my_bookings_list' && $key !== 'staff_bookings_list') {
			pg_assert_same('{{tab:booking}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the enquiries");
		}
	}
	foreach ($d['widgets']['staff_bookings_list']['config']['search_fields'] as $f) {
		pg_assert_true(isset($c[$f]), "the staff search reads $f, a field");
	}
}

// The enquiry page is its sender's (and the staff's); My Enquiries lists
// the viewer's own, the staff's list everybody's; the messages go by
// e-mail.
function test_music_press_enquiry_pages()
{
	$d = _test_mupr_load();
	$w = $d['widgets'];
	pg_assert_same('submitter_only', $w['booking_detail']['config']['access_control'], 'only its sender and the staff open an enquiry');
	foreach (array('email_booking_admin', 'email_booking_client') as $key) {
		pg_assert_same('public', $w[$key]['config']['access_control'], "$key is drawn for the submission just sent");
	}
	pg_assert_true(!empty($w['my_bookings_list']['config']['viewer_filter_submitter']), 'My Enquiries lists the viewer\'s own');
	pg_assert_false(isset($w['staff_bookings_list']['config']['viewer_filter']), 'the staff list is everybody\'s');
	pg_assert_true(!empty($w['staff_bookings_list']['config']['search_enabled']), 'the staff list has a search');
	foreach (array('my_bookings_list', 'staff_bookings_list') as $key) {
		pg_assert_same('{{tab:booking_view}}', $w[$key]['config']['detail_page_id'], "$key opens the enquiry");
	}
	$c = $d['pages']['booking_view']['comments'];
	pg_assert_true($c['allow_new'] && $c['login'], 'signed-in messages');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'messages go by e-mail');
	pg_assert_same('comments', $w['booking_messages']['config']['regionType']);
	pg_assert_same('comments', $w['email_comment']['config']['regionType']);
	$json = json_encode($w['booking_detail']['tree']);
	foreach (array('record_found', 'record_not_found', 'has_new_account', 'submitted_this_session') as $flag) {
		pg_assert_contains('"eo_visible_if":"' . $flag . '"', $json, "the enquiry page shows $flag");
	}
}

function test_music_press_links_ids_and_classes()
{
	$d = _test_mupr_load();
	$known = array_flip(_test_mu_page_keys());
	$trees = array($d['header'], $d['footer']);
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_mu_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_mupr_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_mupr_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mu-') === 0, "id $id is the template's");
	}
	// The e-mail frame names the band's booking address, and every
	// colour of the e-mails is the template's midnight blue family.
	pg_assert_contains($d['artist']['booking'], json_encode($d['footer']), 'the e-mail footer names the booking address');
	$mail = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music/mail.php');
	preg_match_all('/#[0-9a-f]{6}\b/i', $mail, $colors);
	foreach (array_unique($colors[0]) as $hex) {
		pg_assert_true(in_array(strtolower($hex), array('#1e3a8a', '#bfdbfe', '#eff6ff', '#ffffff', '#1f2937', '#4b5563', '#b45309'), true), "e-mail colour $hex is the template's");
	}
}

// Every literal has its Turkish; an enquiry reads "konser talebi" (never
// the shared "Bookings", which is "Randevular"), a show "konser".
function test_music_press_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'press.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			if (preg_match('/\b[Bb]ooking [Ee]nquir(y|ies)\b/', $key)) pg_assert_true(mb_stripos($tr, 'konser tale', 0, 'UTF-8') !== false, "'$key' says konser talebi");
			if (preg_match('/\bShows?\b/', $key)) pg_assert_true(mb_stripos($tr, 'konser', 0, 'UTF-8') !== false, "'$key' says konser");
			if (preg_match('/\b[Pp]ress [Kk]it\b/', $key)) pg_assert_true(mb_stripos($tr, 'basın kit', 0, 'UTF-8') !== false, "'$key' says basın kiti");
			pg_assert_false(in_array($key, array('Bookings', 'Booking', 'Show', 'Shows', 'Release', 'Releases', 'tour'), true), "$file: '$key' means something else in Turkish");
		}
	}
}
