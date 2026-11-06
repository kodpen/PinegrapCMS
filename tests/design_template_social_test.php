<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Social Community design template's parts
 * (includes/design_templates/pinegrap-social/): the community's data hangs
 * together (every member has a known topic, a unique user name and two
 * posts with a caption, a described picture, a place and tags), tags,
 * topics and names open the pages that read them, the shared parts load,
 * every page link names a page of the finished template, the header's
 * search goes to the site search and its button to the new-post form, the
 * member bar names only the member's own pages, icons are hidden from a
 * screen reader, the classes are the template's own, no date is written
 * into the sample, and every literal has its Turkish.
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

// The parts as the template file reads them: parts.php first, the page
// files after it in the same scope. Read once per run.
function _test_social_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social';
	include $dir . '/parts.php';
	$cards = array();
	foreach (array_keys($members) as $key) {
		$cards[] = $profile_card($key);
		$cards[] = $post_card($key, 0);
		$cards[] = $post_tile($key, 1);
	}
	foreach (array_keys($topics) as $key) $cards[] = $topic_tile($key);
	$out = array(
		'topics'     => $topics,
		'members'    => $members,
		'posts'      => $social_posts,
		'rules'      => $community_rules,
		'steps'      => $social_steps,
		'tag_url'    => $tag_url,
		'topic_url'  => $topic_url,
		'member_url' => $member_url,
		'header'     => $header(),
		'member_bar' => $member_bar(),
		'shared'     => array($account_bar_tree(), $header(), $member_bar(), $footer(), $join_band(), $notice(), $staff_bar()),
		'parts'      => array_merge($cards, array(
			$rule_list(), $tag_links(array('film')), $comment_count(3),
			$page(array()), $member_page(array()), $staff_page(array()), $page_header(lang('People'), lang('Text')),
			$section_header(lang('People'), lang('People'), lang('Explore'), '{{page:explore}}'),
			$faq(1, lang('Question'), lang('Answer')), $news_card(), $step(1, lang('Title'), lang('Text')),
			$comment_thread(array('title' => 'T', 'empty' => 'E', 'heading' => 'H', 'label' => 'L', 'sign_in' => 'S', 'submit' => 'B',
				'watch_box' => 'W', 'follow' => 'F', 'unfollow' => 'U', 'close' => 'C', 'reopen' => 'R', 'reply' => true, 'watch' => true)),
		)),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_social_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_social_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_social_page_keys()
{
	return array(
		'home', 'topics', 'about', 'guidelines', 'faq', 'help', 'privacy', 'terms',
		'my_profile', 'people', 'member', 'staff_members',
		'post_new', 'explore', 'tag', 'post', 'my_posts', 'saved',
		'report', 'report_view', 'my_reports', 'staff_reports', 'staff_report', 'staff_posts',
		'email_reply', 'email_comment', 'email_report_new', 'email_report_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

function test_social_members_and_posts_hang_together()
{
	$d = _test_social_load();
	pg_assert_same(6, count($d['topics']), 'six topics');
	pg_assert_same(8, count($d['members']), 'eight members');
	$handles = array();
	$used_topics = array();
	foreach ($d['members'] as $key => $m) {
		pg_assert_true((bool)preg_match('/^[a-z0-9._]{3,30}$/', $m['handle']), "$key: a user name a sign-up accepts");
		pg_assert_true(isset($d['topics'][$m['topic']]), "$key: known topic");
		foreach (array('name', 'city', 'bio') as $f) pg_assert_true($m[$f] !== '', "$key: $f written");
		$handles[] = $m['handle'];
		$used_topics[$m['topic']] = true;
		pg_assert_true(isset($d['posts'][$key]), "$key: posts");
		pg_assert_same(2, count($d['posts'][$key]), "$key: two posts");
		foreach ($d['posts'][$key] as $n => $p) {
			foreach (array('caption', 'alt', 'place') as $f) pg_assert_true($p[$f] !== '', "$key post $n: $f written");
			pg_assert_true($p['alt'] !== $p['caption'], "$key post $n: the alt text describes the picture, not the caption");
			pg_assert_true(count($p['tags']) >= 1, "$key post $n: tags");
			foreach ($p['tags'] as $tag) pg_assert_true((bool)preg_match('/^[a-z]+$/', $tag), "$key post $n: tag $tag is one plain word");
			pg_assert_true(is_int($p['comments']) && $p['comments'] >= 0, "$key post $n: a comment count");
		}
	}
	pg_assert_same(count($handles), count(array_unique($handles)), 'user names are unique');
	pg_assert_same(array(), array_diff_key($d['posts'], $d['members']), 'no posts without a member');
	pg_assert_true(count($used_topics) >= 5, 'the members cover the topics');
	pg_assert_same(5, count($d['rules']), 'five guidelines');
	pg_assert_same(4, count($d['steps']), 'four steps');
}

// A tag opens its page with ?tag=, a topic the explore list with its
// title (the value the Posts form stores), a sample member's name the
// people list searched for that name (the display name of the member's
// sample profile record). The member page filters ?member= on the author
// name of a record, which a fictional sample member never has, so no
// static card links there, and no card shows a user name the site does
// not have.
function test_social_links_send_what_the_lists_read()
{
	$d = _test_social_load();
	pg_assert_same('{{page:tag}}?tag=film', call_user_func($d['tag_url'], 'film'));
	pg_assert_same('{{page:explore}}?topic=' . rawurlencode($d['topics']['food']['title']), call_user_func($d['topic_url'], 'food'));
	pg_assert_same('{{page:people}}?query=' . rawurlencode($d['members']['ayla']['name']), call_user_func($d['member_url'], 'ayla'));
	$json = json_encode($d['parts'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_contains('{{page:tag}}?tag=harbour', $json, 'a post card links its tags');
	pg_assert_contains('{{page:people}}?query=' . rawurlencode($d['members']['ayla']['name']), $json, 'a post card links its member');
	pg_assert_true(strpos($json, '{{page:member}}?member=') === false, 'no static card links the member page');
	foreach ($d['members'] as $key => $m) pg_assert_true(strpos($json, '@' . $m['handle']) === false, "$key: no user name on a card");
}

function test_social_page_links_name_template_pages()
{
	$d = _test_social_load();
	$known = array_flip(_test_social_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($d['shared'], $d['parts'])), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

function test_social_header_search_and_new_post()
{
	$d = _test_social_load();
	$nodes = array();
	_test_social_nodes($d['header'], $nodes);
	$menu = null;
	$search = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		if (($n['props']['tag'] ?? '') === 'form') $search = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
	pg_assert_true($search !== null, 'the header has a search');
	$attrs = array();
	foreach ($search['props']['_attrs'] as $a) $attrs[$a['name']] = $a['value'];
	pg_assert_same('{{page:search}}', $attrs['action'], 'the search goes to the site search');
	pg_assert_same('get', $attrs['method']);
	pg_assert_contains('"name","value":"query"', json_encode($search), 'the box sends ?query=, what the site search reads');
	pg_assert_contains('{{page:post_new}}', json_encode($d['header']), 'the header opens the new-post form');
}

// The member bar is the member's own menu: every page it names is one of
// the member's registration pages.
function test_social_member_bar_names_member_pages()
{
	$d = _test_social_load();
	preg_match_all('/\{\{page:([a-z0-9_]+)\}\}/', json_encode($d['member_bar']), $m);
	pg_assert_same(array('my_account', 'my_posts', 'saved', 'my_profile', 'my_reports'), $m[1]);
	$nodes = array();
	_test_social_nodes($d['member_bar'], $nodes);
	$marked = false;
	foreach ($nodes as $n) if (!empty($n['props']['smartActive'])) $marked = true;
	pg_assert_true($marked, 'the member bar marks the current page');
}

// Icons are decorative (the words beside them say what they mean), and
// a member's picture beside the name carries no second name.
function test_social_icons_and_avatars_stay_quiet()
{
	$d = _test_social_load();
	$nodes = array();
	foreach (array_merge($d['shared'], $d['parts']) as $t) _test_social_nodes($t, $nodes);
	$avatars = 0;
	foreach ($nodes as $n) {
		$c = (string)($n['props']['cssClass'] ?? '');
		if (strpos($c, 'bi ') === 0) pg_assert_contains('aria-hidden', json_encode($n), "icon $c hidden from a screen reader");
		if (strpos($c, 'psc-avatar') !== false) {
			$avatars++;
			pg_assert_same('', $n['props']['alt'], 'an avatar beside the name has an empty alt');
		}
		if (($n['props']['contentType'] ?? '') === 'image' && strpos($c, 'psc-avatar') === false) {
			pg_assert_true((string)$n['props']['alt'] !== '', 'a photo is described');
		}
	}
	pg_assert_true($avatars >= 16, 'profile and post cards show the avatar');
}

// Classes the template adds of its own start with psc-; another template's
// prefix copied in would stay unstyled (its rules are not in this one).
function test_social_classes_are_its_own()
{
	$d = _test_social_load();
	$nodes = array();
	foreach (array_merge($d['shared'], $d['parts']) as $t) _test_social_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|pmk|pht|pdl|pco|pc|pl|plx|plw|psl|pn)-/', $c), "class $c is not another template's");
			if (strpos($c, 'psc-') === 0) $own[$c] = true;
		}
	}
	foreach (array('psc-brand', 'psc-eyebrow', 'psc-lift', 'psc-band', 'psc-search', 'psc-avatar', 'psc-avatar-lg', 'psc-tile', 'psc-tile-info', 'psc-post') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The samples are never dated: a year or a month would read as stale a
// year later.
function test_social_no_fixed_dates()
{
	$d = _test_social_load();
	$json = json_encode(array($d['members'], $d['posts'], $d['topics'], $d['rules'], $d['steps'], $d['shared'], $d['parts']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The comment stars are SVG icons: their namespace address is no date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish; a post is a "gönderi" (never
// the blog's "yazı") and a report a "şikâyet", and the people keep their
// names.
function test_social_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
	}
	foreach (array('My Posts', 'Saved Posts', 'All Member Posts', 'Member Post') as $key) {
		pg_assert_contains('önderi', $map[$key] ?? '', "'$key' says gönderi");
	}
	foreach (array('My Reports', 'All Reports') as $key) {
		pg_assert_contains('şikâyet', mb_strtolower($map[$key] ?? '', 'UTF-8'), "'$key' says şikâyet");
	}
	foreach (array('Mira Kovač', 'Emre Yıldız') as $name) pg_assert_same($name, $map[$name] ?? '', "$name keeps the name");
}
