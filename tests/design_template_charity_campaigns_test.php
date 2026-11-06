<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Charity design template's campaigns
 * (includes/design_templates/pinegrap-charity/campaigns.php): the
 * Campaigns form is a collection whose sample records are the campaigns of
 * parts.php with their cause, figures and featured mark; the form's fields
 * carry the kinds and roles a collection needs and the staff's fields are
 * office use only; the list filters by the cause the cause links send and
 * leaves the cards' figures to the record; the home list shows only
 * featured campaigns; a campaign's page shows its progress from the
 * record, leads to the donate page with the campaign named and carries its
 * updates as the page's comments; nothing needs the shop; every page link
 * names a page of the finished template; the classes are the template's
 * own and every literal has its Turkish.
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
// The page keys of the finished template, the shop's pages and the node walk.
require_once(__DIR__ . '/design_template_charity_test.php');

// The campaigns part as the template file reads it: parts.php, mail.php
// and forms.php first, then campaigns.php in the same scope. Read once per
// run.
function _test_charity_campaigns_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/forms.php';
	$part = include $dir . '/campaigns.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'campaigns' => $campaigns, 'causes' => $causes,
		'money' => $money, 'raised' => $raised, 'campaigns_in' => $campaigns_in);
	return $out;
}

// The named controls of a tree: name => node.
function _test_charity_campaigns_controls($tree)
{
	$nodes = array();
	_test_charity_nodes($tree, $nodes);
	$names = array();
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) if ($a['name'] === 'name') $names[$a['value']] = $n;
	}
	return $names;
}

function test_charity_campaigns_pages_and_widgets()
{
	$d = _test_charity_campaigns_load();
	pg_assert_same(array('campaigns', 'campaign', 'campaign_new'), array_keys($d['pages']));
	pg_assert_same(array('campaign_form', 'campaigns_list', 'home_campaigns', 'campaign_detail', 'campaign_updates'), array_keys($d['part']['widgets']));
	foreach ($d['pages'] as $key => $p) pg_assert_false(isset($p['requires']), "$key stands without the shop");
	foreach ($d['part']['widgets'] as $key => $w) pg_assert_false(isset($w['requires']), "widget $key stands without the shop");
	pg_assert_same('private', $d['pages']['campaign_new']['folder'], 'only the staff add campaigns');
	pg_assert_same('home', $d['part']['widgets']['home_campaigns']['page']);
	pg_assert_contains('"templateWidget":"campaigns_list"', json_encode($d['pages']['campaigns']));
	pg_assert_contains('"templateWidget":"campaign_detail"', json_encode($d['pages']['campaign']));
	pg_assert_contains('"templateWidget":"campaign_updates"', json_encode($d['pages']['campaign']));
}

// The form is a collection; its sample records are parts.php's campaigns,
// with the words a visitor sees and the figures the cards show.
function test_charity_campaigns_collection_and_samples()
{
	$d = _test_charity_campaigns_load();
	$form = $d['pages']['campaign_new']['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover', $form['share_image_field']);
	pg_assert_same(count($d['campaigns']), count($form['sample_records']), 'one record per campaign');
	$featured = 0;
	$i = 0;
	foreach ($d['campaigns'] as $key => $c) {
		$r = $form['sample_records'][$i++];
		pg_assert_same($c['title'], $r['title']);
		pg_assert_same($d['causes'][$c['cause']]['title'], $r['cause'], "$key: the cause by its title, as ?cause= sends it");
		pg_assert_same((string)$c['percent'], $r['percent']);
		pg_assert_same(call_user_func($d['money'], $c['goal']), $r['goal']);
		pg_assert_same(call_user_func($d['money'], call_user_func($d['raised'], $c['goal'], $c['percent'])), $r['raised']);
		pg_assert_true($r['cover'] !== '' && $r['summary'] !== '' && $r['story'] !== '' && $r['place'] !== '', "$key: cover and words");
		if (!empty($c['featured'])) $featured++;
		pg_assert_same(!empty($c['featured']), $r['featured'] === lang('Yes'), "$key: featured mark");
	}
	pg_assert_true($featured >= 3, 'the home page has three featured campaigns');
	$fields = _test_charity_campaigns_controls($d['part']['widgets']['campaign_form']['tree']);
	foreach (array_keys($form['sample_records'][0]) as $f) pg_assert_true(isset($fields[$f]), "the form has $f");
	pg_assert_same('number', $fields['percent']['props']['_cf']['kind'], 'percent is a number');
	pg_assert_same('image', $fields['cover']['props']['_cf']['kind'], 'the cover is an image');
	pg_assert_true(!empty($fields['featured']['props']['_cf']['office_use_only']), 'featured is the staff\'s');
	pg_assert_contains(json_encode($d['causes']['water']['title']), json_encode($fields['cause']), 'the causes are the choices');
	$nodes = array();
	_test_charity_nodes($d['part']['widgets']['campaign_form']['tree'], $nodes);
	foreach ($nodes as $n) {
		if (isset($n['props']['id'])) pg_assert_true(strpos($n['props']['id'], 'ch-cp-') === 0, 'field id ' . $n['props']['id'] . ' is the campaign\'s');
	}
}

// The list reads ?cause= (the cause links send the cause's title); the
// filter links above it are the same addresses; the cards take every
// figure from the record.
function test_charity_campaigns_list_filters_by_cause()
{
	$d = _test_charity_campaigns_load();
	$w = $d['part']['widgets'];
	$filters = $w['campaigns_list']['config']['filters'];
	pg_assert_same(array(array('field' => 'cause', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'cause')), $filters);
	$json = json_encode($d['pages']['campaigns']);
	foreach (array_keys($d['causes']) as $key) {
		pg_assert_contains(json_encode(call_user_func($d['campaigns_in'], $key)), $json, "a link filters to $key");
	}
	foreach (array('campaigns_list', 'home_campaigns') as $k) {
		pg_assert_same('{{tab:campaign_new}}', $w[$k]['config']['custom_form_page_id']);
		pg_assert_same('{{tab:campaign}}', $w[$k]['config']['detail_page_id']);
		$tree = json_encode($w[$k]['tree']);
		foreach (array('"text":"title"', '"href":"form_item_view"', '"src":"cover"', '"text":"raised"', '"text":"goal"', '"text":"summary"') as $b) {
			pg_assert_contains($b, $tree, "$k binds $b");
		}
		pg_assert_contains('{{percent}}', $tree, "$k: the bar takes the record's percent");
	}
	$home = $w['home_campaigns']['config'];
	pg_assert_same(3, $home['max_results']);
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $home['filters'], 'the home shows the featured ones');
}

// A campaign's page: public, its bar from the record, a donate link that
// names it, its cause's other campaigns, and its updates as comments that
// a visitor may follow.
function test_charity_campaign_page()
{
	$d = _test_charity_campaigns_load();
	$w = $d['part']['widgets'];
	pg_assert_same('form_item_view', $w['campaign_detail']['config']['regionType']);
	pg_assert_same('public', $w['campaign_detail']['config']['access_control']);
	$tree = json_encode($w['campaign_detail']['tree']);
	pg_assert_contains('{{percent}}', $tree, 'the bar takes the record\'s percent');
	pg_assert_contains('"page_id":"{{tab:donate}}","params":[{"name":"campaign","token":"title"}]', $tree, 'Donate names the campaign');
	pg_assert_contains('"page_id":"{{tab:campaigns}}","params":[{"name":"cause","token":"cause"}]', $tree, 'the cause opens its campaigns');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $tree);
	$comments = $d['pages']['campaign']['comments'];
	pg_assert_true($comments['login'] && $comments['allow_new'], 'signed-in visitors may write');
	pg_assert_false($comments['auto_publish'], 'a visitor\'s message waits for the staff');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	$u = $w['campaign_updates'];
	pg_assert_same('campaign', $u['page']);
	pg_assert_same('comments', $u['config']['regionType']);
	pg_assert_same('newest', $u['config']['order']);
	pg_assert_contains('"comment_watch_form"', json_encode($u['tree']), 'a visitor may follow the updates');
}

function test_charity_campaigns_links_classes_and_words()
{
	$d = _test_charity_campaigns_load();
	$json = json_encode($d['part']);
	$known = array_flip(_test_charity_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_charity_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "campaigns do not link to $key");
	$nodes = array();
	foreach ($d['part']['pages'] as $p) _test_charity_nodes($p['tree'], $nodes);
	foreach ($d['part']['widgets'] as $w) if (is_array($w['tree'])) _test_charity_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|pmk|pc|pl|plx|plw|psl)-/', $c), "class $c is not another template's");
		}
	}
	$plain = json_encode($d['part'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$plain = str_replace('www.w3.org/2000/svg', '', $plain);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $plain), 'no year in the sample');
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity/campaigns.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (preg_match('/\bcampaigns?\b/i', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'kampanya', 0, 'UTF-8') !== false, "'$key' says kampanya");
		}
	}
}
