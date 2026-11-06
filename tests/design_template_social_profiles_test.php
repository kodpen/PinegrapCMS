<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Social Community template's profiles
 * (includes/design_templates/pinegrap-social/profiles.php): its pages are
 * the plan's, each widget sits on its page, the Profiles form is a
 * collection whose samples fill its fields, the lists read the address the
 * links send, the member's page filters on the author name (never the
 * login username) and leaves hidden posts out, a member's address is never
 * made a link, and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');
require_once(__DIR__ . '/design_template_social_test.php');

// parts.php, then profiles.php in the same scope, as the template file
// reads them. Read once per run.
function _test_social_profiles_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social';
	include $dir . '/parts.php';
	$part = include $dir . '/profiles.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'members' => $members, 'topics' => $topics);
	return $out;
}

function test_social_profiles_pages_are_the_plans()
{
	$d = _test_social_profiles_load();
	pg_assert_same(array('people', 'member', 'my_profile', 'staff_members'), array_keys($d['pages']));
	$known = array_flip(_test_social_page_keys());
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a page of the finished template");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
	}
	pg_assert_same('registration', $d['pages']['my_profile']['folder'], 'the own profile needs an account');
	pg_assert_same('private', $d['pages']['staff_members']['folder'], 'the staff list is the staff\'s');
	pg_assert_false($d['pages']['member']['sitemap'], 'the member page depends on its address');
	foreach (array('my_profile', 'staff_members') as $key) {
		pg_assert_false($d['pages'][$key]['search'], "$key stays out of the site search");
		pg_assert_true($d['pages'][$key]['noindex'], "$key stays out of search engines");
	}
}

function test_social_profiles_widgets_are_placed_on_their_pages()
{
	$d = _test_social_profiles_load();
	$json_pages = array();
	foreach ($d['pages'] as $key => $p) $json_pages[$key] = json_encode($p['tree']);
	foreach ($d['widgets'] as $wkey => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$wkey sits on a page of this part");
		pg_assert_contains('"' . $wkey . '"', $json_pages[$w['page']], "$wkey is placed on {$w['page']}");
	}
	foreach ($json_pages as $key => $json) {
		preg_match_all('/"templateWidget":"([a-z0-9_]+)"/', $json, $m);
		foreach (array_unique($m[1]) as $wkey) pg_assert_true(isset($d['widgets'][$wkey]), "$key places the defined widget $wkey");
	}
}

// The samples fill exactly the form's fields; the interests are a topic's
// title, the value ?topic= filters on.
function test_social_profiles_collection_and_samples()
{
	$d = _test_social_profiles_load();
	$form = $d['pages']['my_profile']['form'];
	pg_assert_same(1, $form['collection'], 'Profiles is a collection');
	pg_assert_same(count($d['members']), count($form['sample_records']), 'one sample profile per member');
	$tree = json_encode($d['widgets']['profile_form']['tree']);
	preg_match_all('/"name","value":"([a-z_]+)"/', $tree, $m);
	$names = array_values(array_unique($m[1]));
	sort($names);
	$titles = array();
	foreach ($d['topics'] as $t) $titles[] = $t['title'];
	foreach ($form['sample_records'] as $n => $r) {
		$keys = array_keys($r);
		sort($keys);
		pg_assert_same($names, $keys, "sample $n fills the form's fields");
		pg_assert_true(in_array($r['interests'], $titles, true), "sample $n loves a known topic");
	}
	pg_assert_same('avatar', $form['share_image_field']);
	pg_assert_contains('"upload_folder_id":"{{folder:avatars}}"', $tree, 'pictures go to the avatars folder');
	preg_match_all('/"id":"([^"]+)"/', $tree, $ids);
	foreach ($ids[1] as $id) pg_assert_true(strpos($id, 'sc-') === 0, "field id $id is the template's own");
}

// The people list reads ?topic= on the interests, the member page ?member=
// on the author name; every link to the member page sends that name.
function test_social_profiles_lists_read_what_links_send()
{
	$d = _test_social_profiles_load();
	$w = $d['widgets'];
	pg_assert_same('topic', $w['people_list']['config']['filters'][0]['dynamic_value_attribute']);
	pg_assert_same('interests', $w['people_list']['config']['filters'][0]['field']);
	pg_assert_contains('{{page:people}}?topic=' . rawurlencode($d['topics']['food']['title']), json_encode($d['pages']['people']['tree']));
	foreach (array('member_profile', 'member_posts') as $key) {
		$f = $w[$key]['config']['filters'][0];
		pg_assert_same(array('submitter_name', 'member'), array($f['field'], $f['dynamic_value_attribute']), "$key reads ?member= as the author name");
	}
	pg_assert_contains('"is not equal to","value":"' . lang('Hidden') . '"', json_encode($w['member_posts']['config']['filters']), 'hidden posts stay off');
	pg_assert_same('{{tab:post_new}}', $w['member_posts']['config']['custom_form_page_id']);
	pg_assert_same('{{tab:post}}', $w['member_posts']['config']['detail_page_id']);
	pg_assert_true($w['my_profile_card']['config']['viewer_filter_submitter'], 'the own profile is the member\'s');
	pg_assert_false($w['my_profile_card']['config']['viewer_filter_editor'], 'the staff see their own there too');
	$json = json_encode($w);
	pg_assert_false(strpos($json, '"submitter"') !== false, 'the login username is never read');
	preg_match_all('/"name":"member","token":"([a-z_]+)"/', $json, $m);
	pg_assert_true(count($m[1]) >= 3, 'links to the member page');
	foreach ($m[1] as $token) pg_assert_same('submitter_name', $token);
	pg_assert_false((bool)preg_match('/"href":"website"/', $json), 'a member\'s address is never a link');
}

// A post's byline is the account's name (submitter_name), not the profile's
// display name: the field's hint says so instead of promising the posts.
function test_social_profiles_display_name_hint_tells_where_it_shows()
{
	$d = _test_social_profiles_load();
	$tree = json_encode($d['widgets']['profile_form']['tree']);
	pg_assert_false(strpos($tree, json_encode(lang('The name members see on your page and beside your posts.'))) !== false, 'the hint does not promise the posts');
	pg_assert_contains(substr(json_encode(lang('The name members see on your page and in the people list. Your posts show the name in your account details.')), 1, -1), $tree, 'the hint names the account details');
}

function test_social_profiles_links_name_template_pages()
{
	$d = _test_social_profiles_load();
	$known = array_flip(_test_social_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($d['pages'], $d['widgets'])), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
}

function test_social_profiles_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social/profiles.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
	}
}
