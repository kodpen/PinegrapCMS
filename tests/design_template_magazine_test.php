<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Long-Read Magazine design template
 * (includes/design_templates/pinegrap-magazine.php): the Stories form and
 * its office-use-only fields, the issue pick list's stored default, the
 * live rule every public list and the story page carry, the one vocabulary
 * of sections, statuses, placements, issues and contributors, the sample
 * stories and their Turkish pull quotes, the home bands, the archives, the
 * e-mail pages, the template's own CSS, the authoring contract it shares
 * with the News Portal template, its Turkish wording and the installer
 * preview. Pure: the template file is read as the template loader reads
 * it, and the trees are walked, with no database.
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

// -- helpers -----------------------------------------------------------------

// The template file.
function _test_mag_file()
{
	return PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-magazine.php';
}

// The template as the loader reads it, read once per run.
function _test_mag_tpl()
{
	static $tpl = null;
	if ($tpl === null) {
		$tpl = _pg_tpl_include(_test_mag_file());
	}
	return $tpl;
}

// The News Portal template, null when it is not there.
function _test_mag_news_tpl()
{
	static $tpl = false;
	if ($tpl === false) {
		$file = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-news.php';
		$tpl = is_file($file) ? _pg_tpl_include($file) : null;
	}
	return $tpl;
}

// The Turkish translations, as lang() reads them.
function _test_mag_tr()
{
	return pg_test_language_map();
}

// The pages of a template by key.
function _test_mag_pages($tpl = null)
{
	$out = array();
	foreach (($tpl === null ? _test_mag_tpl() : $tpl)['pages'] as $p) {
		$out[$p['key']] = $p;
	}
	return $out;
}

// The sections in menu order: page key => the title the Section field stores.
function _test_mag_sections()
{
	return array(
		'culture' => lang('Culture'),
		'ideas' => lang('Ideas'),
		'travel' => lang('Travel'),
		'food' => lang('Food'),
		'design_home' => lang('Design & Home'),
		'wellbeing' => lang('Wellbeing'),
	);
}

// The statuses, the placements and the issues as the fields store them.
function _test_mag_statuses()
{
	return array(lang('Awaiting review'), lang('In review'), lang('Changes requested'), lang('Live on the site'), lang('Not accepted'), lang('Withdrawn'));
}

function _test_mag_placements()
{
	return array(lang('Standard'), lang('Cover story'), lang('Editor\'s letter'), lang('Editors\' pick'));
}

function _test_mag_issues()
{
	return array(lang('The Small Places Issue'), lang('The Water Issue'), lang('The Slow Issue'));
}

// The issue pick list's answers: the real default first, then the issues.
function _test_mag_issue_options()
{
	return array_merge(array(lang('Online only')), _test_mag_issues());
}

// The contributors (the keys of their names).
function _test_mag_contributor_keys()
{
	return array('Emily Turner', 'Jane Cooper', 'Michael Brooks', 'Daniel Ross', 'Nora Whitfield');
}

// The contributors' names as the byline stores them.
function _test_mag_contributor_names()
{
	$out = array();
	foreach (_test_mag_contributor_keys() as $key) {
		$out[] = lang($key);
	}
	return $out;
}

// The attributes of a node: name => value.
function _test_mag_attrs($node)
{
	$out = array();
	if (isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) {
		foreach ($node['props']['_attrs'] as $a) {
			if (is_array($a) && isset($a['name'])) {
				$out[(string) $a['name']] = isset($a['value']) ? (string) $a['value'] : '';
			}
		}
	}
	return $out;
}

// The classes of a node.
function _test_mag_classes($node)
{
	$class = (is_array($node) && isset($node['props']['cssClass']) && is_string($node['props']['cssClass'])) ? $node['props']['cssClass'] : '';
	return preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY);
}

// The tag of a node ('' when it has none).
function _test_mag_tag($node)
{
	return (is_array($node) && isset($node['props']['tag']) && is_string($node['props']['tag'])) ? strtolower($node['props']['tag']) : '';
}

// The flag a node shows under ('' when it always shows).
function _test_mag_flag($node)
{
	return (is_array($node) && isset($node['props']['_bindings']['eo_visible_if'])) ? (string) $node['props']['_bindings']['eo_visible_if'] : '';
}

// Every node of a tree, in document order.
function _test_mag_nodes($node)
{
	$out = array();
	if (!is_array($node)) {
		return $out;
	}
	if (isset($node['type']) || isset($node['props'])) {
		$out[] = $node;
	}
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) {
			$out = array_merge($out, _test_mag_nodes($child));
		}
	}
	return $out;
}

// Every node of a tree with the classes of its ancestors: list of
// array(node, ancestor classes, ancestor flags).
function _test_mag_nodes_in_context($node, $classes = array(), $flags = array())
{
	$out = array();
	if (!is_array($node)) {
		return $out;
	}
	$out[] = array($node, $classes, $flags);
	$classes = array_merge($classes, _test_mag_classes($node));
	if (_test_mag_flag($node) !== '') {
		$flags[] = _test_mag_flag($node);
	}
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) {
			$out = array_merge($out, _test_mag_nodes_in_context($child, $classes, $flags));
		}
	}
	return $out;
}

// A tree with every placed shared component and widget put in its place: a
// shared component by its tree, a widget by a 'widget' node holding its tree
// (none for a starter tree), named in props._widget.
function _test_mag_resolve($node)
{
	if (!is_array($node)) {
		return $node;
	}
	$tpl = _test_mag_tpl();
	if (isset($node['type']) && $node['type'] === 'shared_ref') {
		if (isset($node['props']['templateShared'])) {
			$key = $node['props']['templateShared'];
			return isset($tpl['shared'][$key]['tree']) ? _test_mag_resolve($tpl['shared'][$key]['tree']) : null;
		}
		if (isset($node['props']['templateWidget'])) {
			$key = $node['props']['templateWidget'];
			$tree = isset($tpl['widgets'][$key]['tree']) ? $tpl['widgets'][$key]['tree'] : null;
			return array('type' => 'widget', 'props' => array('_widget' => $key, 'starter' => !is_array($tree)),
				'children' => is_array($tree) ? array(_test_mag_resolve($tree)) : array());
		}
	}
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $i => $child) {
			$node['children'][$i] = _test_mag_resolve($child);
		}
	}
	return $node;
}

// The keys of the widgets a resolved tree places, in order, once each.
function _test_mag_placed($resolved)
{
	$out = array();
	foreach (_test_mag_nodes($resolved) as $n) {
		if (isset($n['type']) && $n['type'] === 'widget' && !in_array($n['props']['_widget'], $out, true)) {
			$out[] = $n['props']['_widget'];
		}
	}
	return $out;
}

// The pages every visitor or member opens (every folder but 'private' and
// 'contributors': 'public' and 'registration'), with their trees resolved.
function _test_mag_open_trees()
{
	$out = array();
	foreach (_test_mag_pages() as $key => $p) {
		if (!in_array($p['folder'], array('private', 'contributors'), true)) {
			$out[$key] = _test_mag_resolve($p['tree']);
		}
	}
	return $out;
}

// Every tree of the template: pages, widgets and shared components.
function _test_mag_all_trees()
{
	$tpl = _test_mag_tpl();
	$trees = array();
	foreach ($tpl['pages'] as $p) {
		$trees['page ' . $p['key']] = $p['tree'];
	}
	foreach ($tpl['widgets'] as $key => $w) {
		if (is_array($w['tree'])) {
			$trees['widget ' . $key] = $w['tree'];
		}
	}
	foreach ($tpl['shared'] as $key => $s) {
		$trees['shared ' . $key] = $s['tree'];
	}
	return $trees;
}

// Every node of every tree of the template.
function _test_mag_all_nodes()
{
	$nodes = array();
	foreach (_test_mag_all_trees() as $tree) {
		$nodes = array_merge($nodes, _test_mag_nodes($tree));
	}
	return $nodes;
}

// The form control named $name in a tree, null when there is none.
function _test_mag_control($tree, $name)
{
	foreach (_test_mag_nodes($tree) as $n) {
		if (in_array(_test_mag_tag($n), array('input', 'select', 'textarea'), true)) {
			$a = _test_mag_attrs($n);
			if (isset($a['name']) && $a['name'] === $name) {
				return $n;
			}
		}
	}
	return null;
}

// The options of a select node: list of array(value, selected).
function _test_mag_options($select)
{
	$out = array();
	foreach (is_array($select) && isset($select['children']) ? $select['children'] : array() as $o) {
		if (_test_mag_tag($o) !== 'option') {
			continue;
		}
		$a = _test_mag_attrs($o);
		$value = isset($a['value']) ? $a['value'] : (isset($o['props']['text']) ? (string) $o['props']['text'] : '');
		$out[] = array($value, array_key_exists('selected', $a));
	}
	return $out;
}

// The answers of the Stories form's pick list $name, prompt left out.
function _test_mag_answers($name)
{
	$tpl = _test_mag_tpl();
	$out = array();
	foreach (_test_mag_options(_test_mag_control($tpl['widgets']['article_form']['tree'], $name)) as $o) {
		if ($o[0] !== '') {
			$out[] = $o[0];
		}
	}
	return $out;
}

// The Stories form's controls as the form stores them.
function _test_mag_controls()
{
	$tpl = _test_mag_tpl();
	return _pg_cf_controls($tpl['widgets']['article_form']['tree'], 0);
}

// The live rule as the template writes it.
function _test_mag_live_rows()
{
	$statuses = _test_mag_answers('status');
	return array(
		array('field' => 'status', 'operator' => 'is equal to', 'value' => isset($statuses[3]) ? $statuses[3] : '?', 'dynamic_value' => ''),
		array('field' => 'publish_date', 'operator' => 'is greater than or equal to', 'value' => '2000-01-01 00:00:00', 'dynamic_value' => ''),
		array('field' => 'publish_date', 'operator' => 'is less than or equal to', 'value' => '', 'dynamic_value' => 'current date and time'),
	);
}

// What a view config lacks of the live rule: one line per missing row.
function _test_mag_live_problems($cfg)
{
	$problems = array();
	$filters = (isset($cfg['filters']) && is_array($cfg['filters'])) ? $cfg['filters'] : array();
	foreach (_test_mag_live_rows() as $row) {
		$found = false;
		foreach ($filters as $f) {
			if (isset($f['field'], $f['operator'], $f['value'])
				&& $f['field'] === $row['field'] && $f['operator'] === $row['operator'] && $f['value'] === $row['value']
				&& (isset($f['dynamic_value']) ? $f['dynamic_value'] : '') === $row['dynamic_value']) {
				$found = true;
			}
		}
		if (!$found) {
			$problems[] = $row['field'] . ' ' . $row['operator'] . ' ' . ($row['dynamic_value'] !== '' ? $row['dynamic_value'] : $row['value']);
		}
	}
	return $problems;
}

// Whether a widget config lists or shows the stories.
function _test_mag_is_story_view($cfg)
{
	return isset($cfg['regionType'], $cfg['custom_form_page_id'])
		&& in_array($cfg['regionType'], array('form_list_view', 'form_item_view'), true)
		&& $cfg['custom_form_page_id'] === '{{tab:blog_new}}';
}

// The template's own CSS: the User Styles entry without the block rules.
function _test_mag_own_css()
{
	$tpl = _test_mag_tpl();
	$list = json_decode($tpl['assets']['css'], true);
	$css = '';
	foreach (is_array($list) ? $list : array() as $entry) {
		if (isset($entry['meta']) && $entry['meta'] === 'user-styles') {
			$css .= (string) $entry['content'];
		}
	}
	$source = _pg_designer_block_css_source();
	$css = str_replace($source['mark'] . "\n", '', $css);
	foreach ($source['rules'] as $rule) {
		$css = str_replace($rule . "\n", '', $css);
	}
	return $css;
}

// The selectors of a style sheet: comments and at-rule preludes left out.
function _test_mag_selectors($css)
{
	$plain = preg_replace('~/\*.*?\*/~s', '', $css);
	preg_match_all('/([^{}]+)\{/', $plain, $m);
	$out = array();
	foreach ($m[1] as $selector) {
		$selector = trim($selector);
		if ($selector !== '' && strpos($selector, '@') !== 0) {
			$out[] = $selector;
		}
	}
	return $out;
}

// A selector's specificity as one number: ids, classes and pseudo-classes,
// types and pseudo-elements.
function _test_mag_specificity($selector)
{
	$s = $selector;
	$elements = preg_match_all('/::[\w-]+/', $s);
	$s = preg_replace('/::[\w-]+/', ' ', $s);
	$ids = preg_match_all('/#[\w-]+/', $s);
	$s = preg_replace('/#[\w-]+/', ' ', $s);
	$classes = preg_match_all('/\.[\w-]+/', $s) + preg_match_all('/:[\w-]+(\([^)]*\))?/', $s);
	$s = preg_replace(array('/\.[\w-]+/', '/:[\w-]+(\([^)]*\))?/'), ' ', $s);
	$elements += preg_match_all('/[a-z][\w-]*/i', $s);
	return $ids * 10000 + $classes * 100 + $elements;
}

// The text a visitor sees in a node: its own and its children's, leaving
// out what only a screen reader hears.
function _test_mag_visible_text($node)
{
	if (!is_array($node) || in_array('visually-hidden', _test_mag_classes($node), true)) {
		return '';
	}
	$text = isset($node['props']['text']) && is_string($node['props']['text']) ? $node['props']['text'] : '';
	foreach (!empty($node['children']) && is_array($node['children']) ? $node['children'] : array() as $child) {
		$text .= _test_mag_visible_text($child);
	}
	return trim($text);
}

// The addresses a node links to: its href prop and its href attribute.
function _test_mag_hrefs($node)
{
	$out = array();
	if (isset($node['props']['href']) && is_string($node['props']['href'])) {
		$out[] = $node['props']['href'];
	}
	$a = _test_mag_attrs($node);
	if (isset($a['href'])) {
		$out[] = $a['href'];
	}
	return $out;
}

// The loop of a widget tree, null when it has none.
function _test_mag_loop_parent($tree)
{
	$find = function ($node) use (&$find) {
		if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) {
			return null;
		}
		foreach ($node['children'] as $child) {
			if (is_array($child) && isset($child['type']) && $child['type'] === 'loop_area') {
				return array($node, $child);
			}
		}
		foreach ($node['children'] as $child) {
			$found = $find($child);
			if ($found !== null) {
				return $found;
			}
		}
		return null;
	};
	return $find($tree);
}

// The sample stories.
function _test_mag_samples()
{
	$pages = _test_mag_pages();
	return $pages['blog_new']['form']['sample_records'];
}

// The lang() literals of the template file, as written (\' unescaped).
function _test_mag_lang_literals()
{
	$source = (string) file_get_contents(_test_mag_file());
	preg_match_all("/\\blang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $source, $m);
	$out = array();
	foreach ($m[1] as $literal) {
		$out[] = strtr($literal, array("\\'" => "'", '\\\\' => '\\'));
	}
	return array_values(array_unique($out));
}

// -- 1. the file ---------------------------------------------------------------

// The file loads without a notice (the runner turns one into an error),
// carries the template keys, and its order, look, palette and thumbnail are
// known to the platform.
function test_magazine_template_loads()
{
	$tpl = _test_mag_tpl();
	pg_assert_true(is_array($tpl), 'the template is an array');
	foreach (array('name', 'version', 'framework', 'order', 'icon', 'thumb', 'description', 'look', 'palette', 'highlights', 'folders', 'contact_groups', 'pages', 'widgets', 'shared', 'assets') as $key) {
		pg_assert_true(array_key_exists($key, $tpl), 'key ' . $key);
	}
	pg_assert_same(lang('Long-Read Magazine'), $tpl['name'], 'name');
	pg_assert_same(1, preg_match('/^\d+\.\d+\.\d+$/', $tpl['version']), 'version x.y.z');
	pg_assert_same('bootstrap5', $tpl['framework'], 'framework');
	pg_assert_same(80, $tpl['order'], 'order');
	pg_assert_same('bi-journal-richtext', $tpl['icon'], 'icon');
	pg_assert_same('magazine', $tpl['thumb'], 'thumb');
	pg_assert_same(array('look' => 'editorial', 'palette' => 'sage'), pg_design_template_theme($tpl), 'a built-in look and palette');
	pg_assert_true(function_exists('_pg_design_thumb_magazine'), 'the thumbnail is drawn by its own function');
	pg_assert_same(4, count($tpl['highlights']), 'four highlights');

	$orders = array();
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php') as $file) {
		$other = _pg_tpl_include($file);
		if (is_array($other) && isset($other['order'])) {
			$orders[basename($file, '.php')] = (int) $other['order'];
		}
	}
	pg_assert_same(array('pinegrap-magazine'), array_keys($orders, 80, true), 'order 80 is the magazine\'s alone');
	pg_assert_true(isset(pg_design_templates()['pinegrap-magazine']), 'the template is listed');
	pg_assert_same(47, count($tpl['pages']), '47 pages');
}

// -- 2. placeholders -----------------------------------------------------------

// Every page, tab, folder, contact group, widget and shared component a
// placeholder names exists, and every widget sits on a page or on none.
function test_magazine_placeholders_resolve()
{
	$tpl = _test_mag_tpl();
	$pages = _test_mag_pages();
	$json = json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']));

	preg_match_all('/\{\{(page|tab):([a-z0-9_-]+)\}\}/', $json, $m, PREG_SET_ORDER);
	pg_assert_true(count($m) > 50, 'page and tab placeholders found');
	foreach ($m as $x) {
		pg_assert_true(isset($pages[$x[2]]), '{{' . $x[1] . ':' . $x[2] . '}} names a page');
	}
	preg_match_all('/\{\{folder:([a-z0-9_-]+)\}\}/', $json, $m, PREG_SET_ORDER);
	pg_assert_true(count($m) > 0, 'folder placeholders found');
	foreach ($m as $x) {
		pg_assert_true(isset($tpl['folders'][$x[1]]), '{{folder:' . $x[1] . '}} names a folder');
	}
	preg_match_all('/\{\{contact_group:([a-z0-9_-]+)\}\}/', $json, $m, PREG_SET_ORDER);
	pg_assert_true(count($m) > 0, 'contact group placeholders found');
	foreach ($m as $x) {
		pg_assert_true(isset($tpl['contact_groups'][$x[1]]), '{{contact_group:' . $x[1] . '}} names a contact group');
	}
	preg_match_all('/"templateWidget":"([^"]+)"/', $json, $m);
	foreach ($m[1] as $key) {
		pg_assert_true(isset($tpl['widgets'][$key]), 'widget ' . $key . ' exists');
	}
	foreach ($tpl['widgets'] as $key => $w) {
		pg_assert_true(in_array($key, $m[1], true), 'widget ' . $key . ' is placed');
		pg_assert_true($w['page'] === '' || isset($pages[$w['page']]), 'widget ' . $key . ' page');
	}
	preg_match_all('/"templateShared":"([^"]+)"/', $json, $m);
	foreach ($m[1] as $key) {
		pg_assert_true(isset($tpl['shared'][$key]), 'shared ' . $key . ' exists');
	}
	foreach ($tpl['folders'] as $key => $folder) {
		pg_assert_true(!isset($folder['parent']) || isset($tpl['folders'][$folder['parent']]), 'folder ' . $key . ' parent');
	}
	foreach ($pages as $key => $p) {
		pg_assert_true(isset($tpl['folders'][$p['folder']]), 'page ' . $key . ' folder');
	}
}

// -- 3. the live rule ------------------------------------------------------------

// Every list and record view of the stories placed on a page every visitor
// or member opens carries all three live rows; none of them is a submitter
// or viewer view; no story view sits on a registration page; and every
// story view of the file filters on fields the form has, with operators
// the renderer knows.
function test_magazine_public_views_carry_live_rule()
{
	$tpl = _test_mag_tpl();
	$pages = _test_mag_pages();
	$checked = array();
	foreach (_test_mag_open_trees() as $page_key => $tree) {
		foreach (_test_mag_placed($tree) as $key) {
			$cfg = $tpl['widgets'][$key]['config'];
			// A member's own inbox and conversation are viewer views of the
			// contact form on registration pages; nothing on a public page is.
			if ($pages[$page_key]['folder'] === 'public' || _test_mag_is_story_view($cfg)) {
				pg_assert_true(empty($cfg['viewer_filter']), $page_key . ' ' . $key . ' has no viewer filter');
				pg_assert_true(!isset($cfg['access_control']) || $cfg['access_control'] !== 'submitter_only', $page_key . ' ' . $key . ' is not submitter only');
			}
			if (!_test_mag_is_story_view($cfg)) {
				continue;
			}
			pg_assert_false($pages[$page_key]['folder'] === 'registration', $page_key . ' ' . $key . ': no story view on a registration page');
			pg_assert_same(array(), _test_mag_live_problems($cfg), $page_key . ' ' . $key . ' carries the live rule');
			$checked[$key] = true;
		}
	}
	foreach (array('home_cover', 'home_posts', 'home_quote', 'home_letter', 'home_picks', 'home_issues', 'most_read',
		'section_culture', 'section_ideas', 'section_travel', 'section_food', 'section_design_home', 'section_wellbeing',
		'blog_list', 'issue_list', 'issue_index', 'author_list', 'article_more', 'blog_post') as $key) {
		pg_assert_true(isset($checked[$key]), $key . ' is checked');
	}

	// The check itself: a view without the empty-date row is caught.
	$cfg = $tpl['widgets']['home_posts']['config'];
	foreach ($cfg['filters'] as $i => $f) {
		if ($f['field'] === 'publish_date' && $f['value'] === '2000-01-01 00:00:00') {
			unset($cfg['filters'][$i]);
		}
	}
	pg_assert_same(array('publish_date is greater than or equal to 2000-01-01 00:00:00'), _test_mag_live_problems($cfg), 'a missing empty-date row is caught');

	// Fields and operators: the constants of pg_sw_form_list_filter_sql().
	$operators = array('contains', 'does not contain', 'is equal to', 'is not equal to',
		'is less than', 'is less than or equal to', 'is greater than', 'is greater than or equal to');
	$controls = _test_mag_controls();
	$rows = 0;
	foreach ($tpl['widgets'] as $key => $w) {
		if (!_test_mag_is_story_view($w['config'])) {
			continue;
		}
		foreach (isset($w['config']['filters']) ? $w['config']['filters'] : array() as $f) {
			$rows++;
			pg_assert_true(isset($controls[$f['field']]), $key . ' filters on a field of the form: ' . $f['field']);
			pg_assert_true(in_array($f['operator'], $operators, true), $key . ' filters with a known operator: ' . $f['operator']);
		}
	}
	pg_assert_true($rows > 60, 'filter rows were checked');
}

// -- 4. private views ------------------------------------------------------------

// The author's own story page and the story e-mails are submitter views in
// private folders; the public story page filters on the live rule; story
// addresses keep their reference code; the desk and its directory are
// staff only.
function test_magazine_private_views()
{
	$tpl = _test_mag_tpl();
	$pages = _test_mag_pages();

	$sv = $tpl['widgets']['submission_view'];
	pg_assert_same('submitter_only', $sv['config']['access_control'], 'submission_view access');
	pg_assert_true(empty($sv['config']['filters']), 'submission_view has no filters');
	pg_assert_same('contributors', $pages[$sv['page']]['folder'], 'submission page folder');

	foreach (array('email_article_admin', 'email_article_author') as $key) {
		$w = $tpl['widgets'][$key];
		pg_assert_same('submitter_only', $w['config']['access_control'], $key . ' access');
		pg_assert_same('private', $pages[$w['page']]['folder'], $key . ' page folder');
	}

	pg_assert_same(_test_mag_live_rows(), $tpl['widgets']['blog_post']['config']['filters'], 'the story page filters are the live rule');
	pg_assert_same('public', $tpl['widgets']['blog_post']['config']['access_control'], 'the story page is public');
	pg_assert_same(0, $pages['blog_new']['form']['pretty_urls'], 'story addresses use the reference code');
	foreach (array('blog_new', 'my_articles', 'submission') as $key) {
		pg_assert_same('contributors', $pages[$key]['folder'], $key . ' folder');
	}
	foreach (array('staff', 'staff_directory', 'staff_entry') as $key) {
		pg_assert_same('private', $pages[$key]['folder'], $key . ' folder');
	}
	pg_assert_same('private', $tpl['folders']['contributors']['parent'], 'contributors sits under private');
	pg_assert_same('private', $tpl['folders']['contributors']['access'], 'contributors is private');
	pg_assert_true(!empty($tpl['widgets']['my_articles_list']['config']['viewer_filter']), 'My Stories lists the viewer\'s own stories');
}

// -- 5. office-use-only fields ---------------------------------------------------

// The fields the editors fill are office use only, store a default an
// author cannot change (a real option: an empty one is never stored, and the
// control panel would save the first issue on the next status change), and
// leave nothing of themselves, not even an issue's name, on an author's form.
function test_magazine_office_fields()
{
	$tpl = _test_mag_tpl();
	$tree = $tpl['widgets']['article_form']['tree'];
	$office = array('status' => 'pick list', 'publish_date' => 'date and time', 'placement' => 'pick list',
		'issue' => 'pick list', 'reading_time' => 'text box', 'correction' => 'text area');

	foreach ($office as $name => $type) {
		$n = _test_mag_control($tree, $name);
		pg_assert_true(is_array($n), $name . ' exists');
		pg_assert_true(isset($n['props']['_cf']['office_use_only']) && $n['props']['_cf']['office_use_only'] === true, $name . ' is office use only');
		pg_assert_false(array_key_exists('required', _test_mag_attrs($n)), $name . ' is not required');
		if (_test_mag_tag($n) === 'select') {
			$selected = 0;
			foreach (_test_mag_options($n) as $o) {
				pg_assert_true($o[0] !== '', $name . ' has no option whose value is empty');
				$selected += $o[1] ? 1 : 0;
			}
			pg_assert_same(1, $selected, $name . ' has one selected option');
		}
	}
	$issue_options = _test_mag_options(_test_mag_control($tree, 'issue'));
	pg_assert_same(array(lang('Online only'), true), $issue_options[0], 'the issue\'s first option is Online only, selected');

	$controls = _pg_cf_controls($tree, 0);
	pg_assert_same(lang('Awaiting review'), $controls['status']['default_value'], 'status default');
	pg_assert_same(lang('Standard'), $controls['placement']['default_value'], 'placement default');
	pg_assert_same(lang('Online only'), $controls['issue']['default_value'], 'issue default');
	$stored = array();
	foreach ($controls['issue']['options'] as $o) {
		$stored[] = $o['value'];
	}
	pg_assert_same(_test_mag_issue_options(), $stored, 'every issue option is stored');
	foreach ($office as $name => $type) {
		pg_assert_same($type, $controls[$name]['type'], $name . ' field type');
	}

	$publish = _test_mag_control($tree, 'publish_date');
	pg_assert_same('datetime-local', _test_mag_attrs($publish)['type'], 'publish_date is a date and time');

	$body = _test_mag_control($tree, 'body');
	pg_assert_same('textarea', _test_mag_tag($body), 'body is a text area');
	pg_assert_true(isset($body['props']['_cf']['wysiwyg']) && $body['props']['_cf']['wysiwyg'] === true, 'body is rich text');
	pg_assert_true(array_key_exists('required', _test_mag_attrs($body)), 'body is required');

	foreach (array('title' => 'title', 'summary' => 'description', 'section' => 'category', 'cover_image' => 'media') as $name => $role) {
		$n = _test_mag_control($tree, $name);
		pg_assert_same($role, isset($n['props']['_cf']['rss_field']) ? $n['props']['_cf']['rss_field'] : '', $name . ' role');
	}

	// What an author sees: the office controls, their labels and their
	// options are gone, and nothing of them stands between the author's last
	// field and the CAPTCHA.
	$words = array();
	foreach ($office as $name => $type) {
		$words[] = $controls[$name]['label'];
		foreach ($controls[$name]['options'] as $o) {
			$words[] = $o['value'];
			$words[] = $o['label'];
		}
	}
	$map = array();
	foreach ($office as $name => $type) {
		$map[$name] = array('id' => count($map) + 1, 'office_use_only' => true, 'type' => $type, 'default_value' => '', 'folder_default' => false);
	}
	$author = $tree;
	pg_cf_apply_field_bindings($author, $map, null, 0, false);
	$nodes = _test_mag_nodes($author);
	$last_field = -1;
	$captcha = -1;
	foreach ($nodes as $i => $n) {
		if (isset($n['props']['text']) && is_string($n['props']['text'])) {
			pg_assert_false(in_array($n['props']['text'], $words, true), 'no office label or option left: ' . $n['props']['text']);
		}
		if (in_array(_test_mag_tag($n), array('input', 'select', 'textarea'), true)) {
			$a = _test_mag_attrs($n);
			pg_assert_false(isset($a['name']) && isset($office[$a['name']]), 'no office control left');
			if ($captcha < 0) {
				$last_field = $i;
			}
		}
		if (isset($n['props']['_bindings']['section']) && $n['props']['_bindings']['section'] === 'captcha') {
			$captcha = $i;
		}
	}
	foreach (_test_mag_issues() as $issue) {
		pg_assert_false(strpos(json_encode($author, JSON_UNESCAPED_UNICODE), json_encode($issue, JSON_UNESCAPED_UNICODE)) !== false, 'an author\'s form names no issue: ' . $issue);
	}
	pg_assert_true($last_field >= 0 && $captcha > $last_field, 'the CAPTCHA follows the author\'s fields');
	for ($i = $last_field + 1; $i < $captcha; $i++) {
		$tag = _test_mag_tag($nodes[$i]);
		pg_assert_false(in_array($tag, array('fieldset', 'legend'), true), 'no ' . $tag . ' before the CAPTCHA');
		pg_assert_false(in_array('form-text', _test_mag_classes($nodes[$i]), true), 'no help text before the CAPTCHA');
	}
	foreach (array('title', 'summary', 'body', 'pull_quote', 'byline', 'note_to_editors', 'rights') as $name) {
		pg_assert_true(is_array(_test_mag_control($author, $name)), $name . ' stays on the author\'s form');
	}
}

// -- 6. one vocabulary -----------------------------------------------------------

// Options, pages, menus, filters, contributor links and the sample stories
// use the same section titles, statuses, placements, issues and names.
function test_magazine_one_vocabulary()
{
	$tpl = _test_mag_tpl();
	$pages = _test_mag_pages();
	$sections = _test_mag_sections();
	$names = _test_mag_contributor_names();

	pg_assert_same(array_values($sections), _test_mag_answers('section'), 'the Section options are the section titles, in order');
	pg_assert_same(_test_mag_statuses(), _test_mag_answers('status'), 'statuses');
	pg_assert_same(_test_mag_placements(), _test_mag_answers('placement'), 'placements');
	pg_assert_same(_test_mag_issue_options(), _test_mag_answers('issue'), 'issues: Online only, then the issues, newest first');

	foreach ($sections as $key => $title) {
		pg_assert_true(isset($pages[$key]), 'section page ' . $key);
		pg_assert_same($title, isset($pages[$key]) ? $pages[$key]['title'] : null, 'section page title ' . $key);
	}

	// The masthead's bar and drawer, the footer and the home tiles link every section.
	$masthead = json_encode($tpl['shared']['masthead']['tree']);
	$footer = json_encode($tpl['shared']['footer']['tree']);
	$home = json_encode($pages['home']['tree']);
	foreach ($sections as $key => $title) {
		pg_assert_true(substr_count($masthead, '"{{page:' . $key . '}}"') >= 2, 'the masthead links ' . $key . ' in the bar and the drawer');
		pg_assert_true(substr_count($footer, '"{{page:' . $key . '}}"') >= 1, 'the footer links ' . $key);
		pg_assert_true(substr_count($home, '"{{page:' . $key . '}}"') >= 1, 'the home tiles link ' . $key);
	}
	preg_match_all('/\{\{page:([a-z0-9_]+)\}\}/', $masthead . $footer, $m);
	foreach (array_unique($m[1]) as $key) {
		pg_assert_true(isset($pages[$key]), 'a menu link names a page: ' . $key);
	}

	// Every filter on a vocabulary field compares with one of its options.
	$answers = array('section' => _test_mag_answers('section'), 'issue' => _test_mag_answers('issue'),
		'placement' => _test_mag_answers('placement'), 'status' => _test_mag_answers('status'));
	foreach ($tpl['widgets'] as $key => $w) {
		foreach (isset($w['config']['filters']) ? $w['config']['filters'] : array() as $f) {
			if (isset($answers[$f['field']]) && (!isset($f['dynamic_value']) || $f['dynamic_value'] === '')) {
				pg_assert_true(in_array($f['value'], $answers[$f['field']], true), $key . ' filters ' . $f['field'] . ' on an option: ' . $f['value']);
			}
		}
	}

	// Every contributor link names a contributor.
	preg_match_all('/\{\{page:author\}\}\?author=([^"#&]+)/', json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $m);
	pg_assert_true(count($m[1]) >= 10, 'contributor links found');
	foreach ($m[1] as $encoded) {
		pg_assert_true(in_array(rawurldecode($encoded), $names, true), 'contributor link ' . $encoded);
	}

	// The sample stories: bylines are the contributors, values are options.
	$bylines = array();
	foreach (_test_mag_samples() as $i => $r) {
		$bylines[$r['byline']] = true;
		foreach ($answers as $field => $options) {
			pg_assert_true(in_array($r[$field], $options, true), 'sample ' . $i . ' ' . $field);
		}
	}
	$found = array_keys($bylines);
	sort($found);
	sort($names);
	pg_assert_same($names, $found, 'the sample bylines are the contributors');
}

// -- 7. sample stories -----------------------------------------------------------

// Seventeen live stories in three issues and one online only, each a
// complete short piece whose pull quote is a sentence of its own text, in
// English and in Turkish, with no date, weather, health claim or name that
// would mislead or go stale.
function test_magazine_sample_stories()
{
	$samples = _test_mag_samples();
	$tr = _test_mag_tr();
	pg_assert_same(17, count($samples), 'seventeen stories');
	pg_assert_true(count($samples) <= 24, 'within the seeder\'s cap');

	$controls = _test_mag_controls();
	$dates = '/\b(January|February|March|April|May|June|July|August|September|October|November|December|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday|spring|summer|autumn|winter)\b/i';
	$weather = '/\b(weather|forecast|temperature|°F|mph)\b/iu';
	$health = '/\b(cure|heal|treat|prevent|disease|symptom|lower(s)? (your )?(blood|risk))\b/i';
	$people = array();
	foreach (_test_mag_contributor_keys() as $key) {
		foreach (preg_split('/\s+/u', $key) as $w) {
			$people[] = $w;
		}
		foreach (preg_split('/\s+/u', isset($tr[$key]) ? $tr[$key] : '') as $w) {
			if ($w !== '') {
				$people[] = $w;
			}
		}
	}
	pg_assert_true(count($people) >= 20, 'contributor names in both languages');

	$count = array('placement' => array(), 'issue' => array(), 'section' => array());
	$cover_letter = array();
	$captions = 0;
	$corrections = 0;
	foreach ($samples as $i => $r) {
		foreach ($r as $key => $value) {
			pg_assert_true(isset($controls[mb_strtolower($key, 'UTF-8')]), 'sample ' . $i . ' ' . $key . ' is a field of the form');
		}
		$body = $r['body'];
		$p = substr_count($body, '<p>');
		$h2 = substr_count($body, '<h2>');
		pg_assert_true($p >= 4 && $p <= 6, 'sample ' . $i . ' has 4 to 6 paragraphs');
		pg_assert_true($h2 <= 1, 'sample ' . $i . ' has at most one subhead');
		pg_assert_same(0, strpos($body, '<p>'), 'sample ' . $i . ' starts with a paragraph');
		pg_assert_same(0, preg_match('~<(?!/?(p|h2)>)~', $body), 'sample ' . $i . ' has no other markup (escaped words)');
		$text = html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8');
		pg_assert_true($r['pull_quote'] !== '' && strpos($text, $r['pull_quote']) !== false, 'sample ' . $i . ' pull quote is a sentence of its text');
		pg_assert_same(0, preg_match('/\b(says|said)\b/', $r['pull_quote']), 'sample ' . $i . ' pull quote stands alone');
		pg_assert_same('', $r['reading_time'], 'sample ' . $i . ' claims no reading time');
		pg_assert_same(1, preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $r['publish_date']), 'sample ' . $i . ' publish_date format');
		pg_assert_true(strtotime($r['publish_date']) <= time(), 'sample ' . $i . ' is published');
		pg_assert_same(lang('Live on the site'), $r['status'], 'sample ' . $i . ' is live');
		pg_assert_same(lang('Sample photo'), $r['image_credit'], 'sample ' . $i . ' credit');
		$captions += (isset($r['image_caption']) && $r['image_caption'] !== '') ? 1 : 0;
		if (isset($r['correction']) && $r['correction'] !== '') {
			$corrections++;
			pg_assert_false(stripos($r['correction'], 'Correction') === 0, 'sample ' . $i . ' correction does not repeat its label');
		}
		foreach ($r as $key => $value) {
			if (!is_string($value)) {
				continue;
			}
			pg_assert_same(0, preg_match($dates, $value), 'sample ' . $i . ' ' . $key . ' names no month, weekday or season');
			pg_assert_same(0, preg_match($weather, $value), 'sample ' . $i . ' ' . $key . ' has no weather');
			if (in_array($key, array('byline', 'byline_bio', 'byline_photo'), true)) {
				continue;
			}
			// A name stays in the name fields: a canon name is one key and
			// never sits inside a longer one.
			foreach ($people as $w) {
				pg_assert_same(0, preg_match('/\b' . preg_quote($w, '/') . '\b/u', $value), 'sample ' . $i . ' ' . $key . ' names ' . $w);
				if (isset($tr[$value])) {
					pg_assert_same(0, preg_match('/\b' . preg_quote($w, '/') . '\b/iu', $tr[$value]), 'sample ' . $i . ' ' . $key . ' names ' . $w . ' in Turkish');
				}
			}
		}
		if ($r['section'] === lang('Wellbeing')) {
			pg_assert_same(0, preg_match($health, $text), 'sample ' . $i . ' makes no health claim');
		}
		foreach (array('placement', 'issue', 'section') as $field) {
			$count[$field][$r[$field]] = isset($count[$field][$r[$field]]) ? $count[$field][$r[$field]] + 1 : 1;
		}
		if (in_array($r['placement'], array(lang('Cover story'), lang('Editor\'s letter')), true)) {
			$cover_letter[$r['issue']][$r['placement']] = isset($cover_letter[$r['issue']][$r['placement']]) ? $cover_letter[$r['issue']][$r['placement']] + 1 : 1;
		}

		// The paragraph that holds the pull quote, in both languages. A
		// cover's line is not set between quotation marks: the home band
		// credits the story, not a speaker.
		preg_match_all('~<(p|h2)>(.*?)</\1>~', $body, $m);
		$holder = null;
		foreach ($m[2] as $part) {
			$part = html_entity_decode($part, ENT_QUOTES, 'UTF-8');
			pg_assert_true(isset($tr[$part]), 'sample ' . $i . ' paragraph has a Turkish value');
			if ($holder === null && strpos($part, $r['pull_quote']) !== false) {
				$holder = $part;
			}
		}
		pg_assert_true($holder !== null, 'sample ' . $i . ' has the paragraph of its pull quote');
		if ($holder !== null) {
			if ($r['placement'] === lang('Cover story')) {
				pg_assert_same(0, preg_match('/["\x{201C}][^"\x{201D}]*' . preg_quote($r['pull_quote'], '/') . '/u', $holder), 'sample ' . $i . ' cover quote is narration');
			}
			pg_assert_true(isset($tr[$r['pull_quote']], $tr[$holder]) && strpos($tr[$holder], $tr[$r['pull_quote']]) !== false,
				'sample ' . $i . ' Turkish pull quote is a sentence of its Turkish paragraph');
		}
		foreach (array('title', 'summary', 'pull_quote', 'tags') as $field) {
			pg_assert_true(isset($tr[$r[$field]]), 'sample ' . $i . ' ' . $field . ' has a Turkish value');
		}
	}
	pg_assert_same(3, $count['placement'][lang('Cover story')], 'three Cover stories');
	pg_assert_same(3, $count['placement'][lang('Editor\'s letter')], 'three Editor\'s letters');
	pg_assert_true($count['placement'][lang('Editors\' pick')] >= 5, 'five Editors\' picks');
	foreach (_test_mag_issues() as $issue) {
		pg_assert_same(1, isset($cover_letter[$issue][lang('Cover story')]) ? $cover_letter[$issue][lang('Cover story')] : 0, $issue . ' has one Cover story');
		pg_assert_same(1, isset($cover_letter[$issue][lang('Editor\'s letter')]) ? $cover_letter[$issue][lang('Editor\'s letter')] : 0, $issue . ' has one Editor\'s letter');
	}
	pg_assert_false(isset($cover_letter[lang('Online only')][lang('Editor\'s letter')]), 'no Editor\'s letter is online only');
	pg_assert_same(1, $count['issue'][lang('Online only')], 'one story is online only');
	pg_assert_true($captions <= 1, 'at most one caption');
	pg_assert_true($corrections <= 1, 'at most one correction');
	foreach (_test_mag_sections() as $key => $title) {
		pg_assert_true(isset($count['section'][$title]) && $count['section'][$title] >= 2, 'section ' . $key . ' has two stories');
	}
}

// -- 8. pagination ---------------------------------------------------------------

// Lists on one page share the page number: one list per page pages at most.
function test_magazine_one_paginated_list_per_page()
{
	$tpl = _test_mag_tpl();
	foreach (_test_mag_pages() as $page_key => $p) {
		$paged = array();
		foreach (_test_mag_placed(_test_mag_resolve($p['tree'])) as $key) {
			$cfg = $tpl['widgets'][$key]['config'];
			if (!isset($cfg['regionType']) || $cfg['regionType'] !== 'form_list_view') {
				continue;
			}
			$max = isset($cfg['max_results']) ? (int) $cfg['max_results'] : 0;
			$ipp = isset($cfg['items_per_page']) ? (int) $cfg['items_per_page'] : 10;
			if ($max === 0 || $max > $ipp) {
				$paged[] = $key;
			}
		}
		pg_assert_true(count($paged) <= 1, $page_key . ' pages one list at most: ' . implode(', ', $paged));
	}
	pg_assert_same(30, $tpl['widgets']['issue_list']['config']['max_results'], 'the contents list is capped');
	pg_assert_same(0, $tpl['widgets']['issue_index']['config']['max_results'], 'the issues pages');
}

// -- 9. the home bands -----------------------------------------------------------

// The home page's single-story bands show one story and leave while empty;
// The latest leaves out every story with a place of its own; the picks
// shelf scrolls with the keyboard.
function test_magazine_home_bands()
{
	$tpl = _test_mag_tpl();
	$w = $tpl['widgets'];
	foreach (array('home_cover' => 1, 'home_quote' => 1, 'home_letter' => 1, 'home_posts' => 6, 'home_picks' => 6, 'home_issues' => 3) as $key => $n) {
		pg_assert_same($n, $w[$key]['config']['items_per_page'], $key . ' items per page');
		pg_assert_same($n, $w[$key]['config']['max_results'], $key . ' max results');
		pg_assert_same('home', $w[$key]['page'], $key . ' is on home');
	}

	$placements = _test_mag_placements();
	$excluded = array();
	foreach ($w['home_posts']['config']['filters'] as $f) {
		if ($f['field'] === 'placement' && $f['operator'] === 'is not equal to') {
			$excluded[] = $f['value'];
		}
	}
	sort($excluded);
	$want = array($placements[1], $placements[2], $placements[3]);
	sort($want);
	pg_assert_same($want, $excluded, 'The latest leaves out covers, letters and picks');

	// Over the samples, newest first: no story is both in The latest and on the shelf.
	$samples = _test_mag_samples();
	uasort($samples, function ($a, $b) {
		return strcmp($b['publish_date'], $a['publish_date']);
	});
	$latest = array();
	$picks = array();
	foreach ($samples as $i => $r) {
		if (!in_array($r['placement'], $want, true) && count($latest) < 6) {
			$latest[] = $i;
		}
		if ($r['placement'] === $placements[3] && count($picks) < 6) {
			$picks[] = $i;
		}
	}
	pg_assert_same(array(3, 4, 5, 9, 10, 16), $latest, 'The latest over the samples');
	pg_assert_same(array(), array_intersect($latest, $picks), 'no story shows twice');
	pg_assert_same(5, count($picks), 'five picks');

	$home = _test_mag_resolve(_test_mag_pages()['home']['tree']);
	$own = _test_mag_own_css();
	foreach (array('home_quote' => 'pm-quote-band', 'home_letter' => 'pm-letter-band') as $key => $class) {
		$band = null;
		foreach (_test_mag_nodes_in_context($home) as $item) {
			if (isset($item[0]['type']) && $item[0]['type'] === 'widget' && $item[0]['props']['_widget'] === $key) {
				$band = $item[1];
			}
		}
		pg_assert_true(is_array($band) && in_array($class, $band, true), $key . ' sits in a ' . $class . ' band');
		pg_assert_true(strpos($own, '.' . $class . ':has(.pg-sw-empty)') !== false, $class . ' leaves while its list is empty');
	}
	pg_assert_same(1, preg_match('/\.pm-quote-band:has\(\.pg-sw-empty\),\.pm-letter-band:has\(\.pg-sw-empty\)\{display:none\}/', $own), 'the empty bands are hidden');

	list($parent, $loop) = _test_mag_loop_parent($w['home_picks']['tree']);
	$classes = _test_mag_classes($parent);
	foreach (array('pg-snap', 'overflow-x-auto', 'pm-shelf', 'flex-nowrap') as $c) {
		pg_assert_true(in_array($c, $classes, true), 'the shelf carries ' . $c);
	}
	$a = _test_mag_attrs($parent);
	pg_assert_same('region', isset($a['role']) ? $a['role'] : null, 'the shelf is a region');
	pg_assert_same('0', isset($a['tabindex']) ? $a['tabindex'] : null, 'the shelf takes focus');
	pg_assert_true(isset($a['aria-label']) && $a['aria-label'] !== '', 'the shelf is named');
	foreach ($loop['children'] as $item) {
		pg_assert_true(in_array('pg-snap-item', _test_mag_classes($item), true), 'a shelf item snaps');
	}
}

// -- 10. ids ---------------------------------------------------------------------

// An id goes in props.id: the renderer drops one written as an attribute.
function test_magazine_no_ids_in_attrs()
{
	$found = array();
	foreach (_test_mag_all_nodes() as $n) {
		if (isset(_test_mag_attrs($n)['id'])) {
			$found[] = _test_mag_attrs($n)['id'];
		}
	}
	pg_assert_same(array(), $found, 'no id in _attrs');
}

// -- 11. the archives ------------------------------------------------------------

// An issue's contents and a contributor's stories read their value from
// the address; the issues index lists the issues' live letters; the most
// read counts the story page's views.
function test_magazine_archives()
{
	$tpl = _test_mag_tpl();
	$pages = _test_mag_pages();
	foreach (array('issue_list' => array('issue', 'issue', 'issues'), 'author_list' => array('byline', 'author', 'author')) as $key => $want) {
		$w = $tpl['widgets'][$key];
		pg_assert_same($want[2], $w['page'], $key . ' page');
		$row = null;
		foreach ($w['config']['filters'] as $f) {
			if (isset($f['dynamic_value']) && $f['dynamic_value'] === 'url parameter') {
				$row = $f;
			}
		}
		pg_assert_true(is_array($row), $key . ' reads the address');
		pg_assert_same($want[0], $row['field'], $key . ' field');
		pg_assert_same('is equal to', $row['operator'], $key . ' exact value');
		pg_assert_same($want[1], $row['dynamic_value_attribute'], $key . ' parameter');
		pg_assert_same($want[1], pg_sw_url_param_name($want[1]), $key . ' parameter name is accepted as it is');
		pg_assert_false(isset($w['config']['empty_message']), $key . ' has no empty message (an unmatched value cuts the block)');

		$flagged = array();
		foreach (_test_mag_nodes($w['tree']) as $n) {
			if (_test_mag_flag($n) === 'has_filter_value') {
				$flagged[] = $n;
			}
		}
		pg_assert_same(1, count($flagged), $key . ' has one flagged block');
		pg_assert_contains('"__filter_value"', json_encode($flagged), $key . ' heading names the value');

		$h1 = 0;
		foreach (_test_mag_nodes_in_context($pages[$want[2]]['tree']) as $item) {
			if (_test_mag_tag($item[0]) === 'h1') {
				$h1++;
				pg_assert_same(array(), $item[2], $want[2] . ' h1 is not flagged');
				pg_assert_false(isset($item[0]['props']['_bindings']), $want[2] . ' h1 is static');
			}
		}
		pg_assert_same(1, $h1, 'one h1 on ' . $want[2]);
	}
	pg_assert_same(lang('Stories by {value}'), $tpl['widgets']['author_list']['config']['filter_title'], 'a contributor\'s page title');
	pg_assert_false(isset($tpl['widgets']['issue_list']['config']['filter_title']), 'an issue\'s page title is its name');
	pg_assert_same(30, $tpl['widgets']['issue_list']['config']['items_per_page'], 'contents: thirty');
	list($parent) = _test_mag_loop_parent($tpl['widgets']['issue_list']['tree']);
	pg_assert_same('ol', _test_mag_tag($parent), 'the contents are a numbered list');
	pg_assert_true(in_array('pm-rank', _test_mag_classes($parent), true), 'the contents are counted');

	$placements = _test_mag_placements();
	foreach (array('issue_index', 'home_issues') as $key) {
		$filters = $tpl['widgets'][$key]['config']['filters'];
		pg_assert_same(array(), _test_mag_live_problems($tpl['widgets'][$key]['config']), $key . ' is live');
		pg_assert_true(in_array(array('field' => 'placement', 'operator' => 'is equal to', 'value' => $placements[2], 'dynamic_value' => ''), $filters, true), $key . ' lists Editor\'s letters');
		pg_assert_true(in_array(array('field' => 'issue', 'operator' => 'is not equal to', 'value' => lang('Online only'), 'dynamic_value' => ''), $filters, true), $key . ' leaves out Online only');
	}

	$cfg = $tpl['widgets']['most_read']['config'];
	pg_assert_same('recent_views', $cfg['order_by_field'], 'ordered by recent views');
	pg_assert_same(30, $cfg['order_recent_days'], 'over thirty days');
	pg_assert_same('{{tab:blog_post}}', $cfg['detail_page_id'], 'counted on the story page');
	pg_assert_same(5, $cfg['max_results'], 'five');
	pg_assert_same(5, $cfg['items_per_page'], 'one page');
	pg_assert_true(in_array('recent_views', pg_sw_form_order_counters(), true), 'recent_views is a counter order');
}

// -- 12. e-mail pages ------------------------------------------------------------

function test_magazine_email_pages()
{
	$tpl = _test_mag_tpl();
	$pages = _test_mag_pages();
	$keys = array_keys(pg_design_template_email_pages($tpl));
	sort($keys);
	$want = array('email_account_welcome', 'email_article_new', 'email_article_received', 'email_desk_message', 'email_message_received',
		'email_new_message', 'email_password_reset', 'email_reply', 'email_welcome');
	pg_assert_same($want, $keys, 'the e-mail pages');
	foreach ($want as $key) {
		pg_assert_same('private', $pages[$key]['folder'], $key . ' folder');
		pg_assert_same(false, $pages[$key]['search'], $key . ' search');
		pg_assert_same(false, $pages[$key]['sitemap'], $key . ' sitemap');
		pg_assert_same(true, $pages[$key]['noindex'], $key . ' noindex');
	}

	$wired = 0;
	foreach ($tpl['widgets'] as $w) {
		$cfg = $w['config'];
		if ($cfg['regionType'] === 'registration') {
			pg_assert_same('{{tab:email_account_welcome}}', $cfg['welcome_email_page_id'], 'welcome page');
			pg_assert_true(trim($cfg['welcome_email_subject']) !== '', 'welcome subject');
			$wired++;
		} elseif ($cfg['regionType'] === 'forgot_password') {
			pg_assert_same('{{tab:email_password_reset}}', $cfg['reset_email_page_id'], 'reset page');
			pg_assert_true(trim($cfg['reset_email_subject']) !== '', 'reset subject');
			$wired++;
		}
	}
	pg_assert_same(2, $wired, 'registration and forgot password are wired');

	// The mail record views open only for the submitter or an editor, and
	// filter nothing (the server names the record).
	foreach ($tpl['widgets'] as $key => $w) {
		if (strpos($w['page'], 'email_') === 0 && in_array($w['config']['regionType'], array('form_item_view', 'form_list_view'), true)) {
			pg_assert_same('submitter_only', $w['config']['access_control'], $key . ' access');
			pg_assert_true(empty($w['config']['filters']), $key . ' filters');
		}
	}
	pg_assert_same('{{tab:email_desk_message}}', $pages['submission']['comments']['email_page'], 'the desk thread has its own e-mail');
	pg_assert_same('{{tab:email_article_new}}', $pages['blog_new']['form']['notify_page_id'], 'the editors\' e-mail');
	pg_assert_same('{{tab:email_article_received}}', $pages['blog_new']['form']['confirm_page_id'], 'the author\'s e-mail');
	pg_assert_same('{{tab:submission}}', $pages['blog_new']['form']['confirmation_page_id'], 'an author lands on the sent story');
}

// -- 13. private fields ----------------------------------------------------------

// Notes to editors and the desk's own fields never show on a page every
// visitor or member opens.
function test_magazine_private_fields_stay_private()
{
	$private = array('note_to_editors', 'status', 'placement');
	$checked = 0;
	foreach (_test_mag_open_trees() as $page_key => $tree) {
		foreach (_test_mag_nodes($tree) as $n) {
			$bindings = isset($n['props']['_bindings']) && is_array($n['props']['_bindings']) ? $n['props']['_bindings'] : array();
			foreach (array('text', 'html', 'src', 'href') as $attr) {
				if (isset($bindings[$attr])) {
					$checked++;
					pg_assert_false(in_array($bindings[$attr], $private, true), $page_key . ' binds ' . $bindings[$attr]);
				}
			}
			$props = isset($n['props']) && is_array($n['props']) ? $n['props'] : array();
			unset($props['_bindings']);
			$static = json_encode($props);
			foreach ($private as $field) {
				pg_assert_false(strpos($static, '^^' . $field . '^^') !== false, $page_key . ' prints ^^' . $field . '^^');
			}
		}
	}
	pg_assert_true($checked > 100, 'open bindings were checked');

	// The note is shown where the editors read it.
	$tpl = _test_mag_tpl();
	pg_assert_contains('"note_to_editors"', json_encode($tpl['widgets']['submission_view']['tree']), 'the desk shows the note');
	pg_assert_contains('"note_to_editors"', json_encode($tpl['widgets']['email_article_admin']['tree']), 'the editors\' e-mail shows the note');
}

// -- 14. installer preview -------------------------------------------------------

function test_magazine_preview_html()
{
	$tpl = pg_design_templates()['pinegrap-magazine'];
	$preview = array_keys(pg_design_template_preview_pages($tpl));
	sort($preview);
	$want = array('about', 'accessibility', 'author', 'blog', 'contact', 'culture', 'design_home', 'food', 'home', 'ideas', 'issues',
		'newsletter', 'privacy', 'search', 'terms', 'travel', 'wellbeing', 'write_for_us');
	pg_assert_same($want, $preview, 'the preview pages');

	foreach ($want as $key) {
		pg_assert_true(pg_design_template_preview_html('pinegrap-magazine', $key) !== '', $key . ' renders');
	}
	$html = pg_design_template_preview_html('pinegrap-magazine', 'home');
	pg_assert_contains('<style id="pg-user-styles">', $html, 'home prints User Styles');
	pg_assert_contains('fonts.googleapis.com/css2?family=Fraunces', $html, 'home links Fraunces');
	pg_assert_contains('family=DM%20Sans', $html, 'home links DM Sans');
	pg_assert_contains('pm-wordmark', $html, 'home has the wordmark');
	pg_assert_contains('pm-one', $html, 'home has the single-story lists');
	// An issue link keeps its ^^issue^^ in the preview's addresses.
	pg_assert_false(strpos(preg_replace('/href="[^"]*"/', '', $html), '^^') !== false, 'home has no ^^ outside an address');

	pg_assert_contains(h(lang('Contributors')), pg_design_template_preview_html('pinegrap-magazine', 'author'), 'the contributors page has its h1');
	pg_assert_contains(h(lang('Every issue')), pg_design_template_preview_html('pinegrap-magazine', 'issues'), 'the issues page has its h1');

	foreach (array_keys(pg_design_template_email_pages($tpl)) as $key) {
		$html = pg_design_template_preview_html('pinegrap-magazine', $key);
		pg_assert_true($html !== '', $key . ' renders');
		pg_assert_contains('pg-user-styles', $html, $key . ' prints User Styles');
		pg_assert_false(strpos(preg_replace('/href="[^"]*"/', '', $html), '^^') !== false, $key . ' has no ^^ outside an address');
	}
}

// -- 15. the template's own CSS --------------------------------------------------

function test_magazine_css()
{
	$tpl = _test_mag_tpl();
	$own = _test_mag_own_css();
	pg_assert_true(strlen($own) > 5000, 'own CSS found');
	$plain = preg_replace('~/\*.*?\*/~s', '', $own);
	$nodes = _test_mag_all_nodes();

	// Every pm- class a tree uses has a rule; no pn- class anywhere.
	$used = array();
	foreach ($nodes as $n) {
		foreach (_test_mag_classes($n) as $c) {
			if (strpos($c, 'pm-') === 0) {
				$used[$c] = true;
			}
			pg_assert_false(strpos($c, 'pn-') === 0, 'no news class ' . $c);
		}
	}
	foreach (array('pm-one', 'pm-body', 'pm-dropcap', 'pm-endmark', 'pm-quote', 'pm-river', 'pm-rank', 'pm-paper', 'pm-shelf', 'pm-issue-grid') as $c) {
		pg_assert_true(isset($used[$c]), $c . ' is used');
	}
	foreach (array_keys($used) as $c) {
		pg_assert_same(1, preg_match('/\.' . preg_quote($c, '/') . '(?![\w-])/', $own), $c . ' has a rule');
	}
	pg_assert_same(0, preg_match('/\.pn-/', $own), 'no news class in the CSS');

	// Colours: tokens only, every palette colour with a fallback.
	pg_assert_same(0, preg_match('/var\(\s*--pg-[\w-]*-fg\s*\)/', $own), 'every --pg-*-fg has a fallback');
	$values = preg_replace('/@supports\s*\([^{]*\)\s*\{/', '@supports{', $plain);
	pg_assert_same(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $values), 'no hex colour');
	pg_assert_same(0, preg_match('/\b(rgba?|hsla?)\(/i', $values), 'no rgb() or hsl()');
	pg_assert_same(0, preg_match('/(?<![\w-])(red|blue|green|white|black|gray|grey|silver|maroon|purple|fuchsia|lime|olive|yellow|navy|teal|aqua|orange|pink|brown|gold|transparent)(?![\w-])/i', $values), 'no named colour');
	$fallback = strpos($own, '.pm-paper{background-color:var(--bs-tertiary-bg)');
	$mix = strpos($own, '@supports (background-color:color-mix(');
	pg_assert_true($fallback !== false && $mix !== false && $fallback < $mix, 'the paper falls back to the tertiary background first');
	pg_assert_same(1, preg_match('/@supports \([^{]*\)\{\.pm-paper\{background-color:color-mix\(in srgb,var\(--pm-accent\) 6%,var\(--bs-body-bg\)\)\}\}/', $own), 'the paper mixes tokens under @supports');
	pg_assert_contains('--pm-accent:var(--pg-secondary-fg,var(--bs-secondary-text-emphasis))', $own, 'the accent falls back');

	// Selectors: descendants and pseudo-classes only.
	pg_assert_false(strpos($own, '!important') !== false, 'no !important');
	pg_assert_false(strpos($own, ':first-child') !== false, 'no :first-child (the edit chip may come first)');
	pg_assert_false(strpos($own, ':last-child') !== false, 'no :last-child');
	$selectors = _test_mag_selectors($own);
	pg_assert_true(count($selectors) > 50, 'selectors found');
	foreach ($selectors as $selector) {
		pg_assert_same(0, preg_match('/[>+~]/', $selector), 'no combinator in ' . $selector);
	}

	// The drop cap and the end mark, and their resets, which outrank them.
	$dropcap = '.pm-dropcap p:first-of-type::first-letter';
	$endmark = '.pm-endmark p:last-of-type::after';
	$all = array();
	foreach ($selectors as $selector) {
		foreach (explode(',', $selector) as $one) {
			$all[] = trim($one);
		}
	}
	pg_assert_true(in_array($dropcap, $all, true), 'the drop cap rule');
	pg_assert_true(in_array($endmark, $all, true), 'the end mark rule');
	foreach (array('blockquote', 'li', 'td', 'th', 'dd', 'div') as $tag) {
		foreach (array($dropcap => '.pm-dropcap ' . $tag . ' p:first-of-type::first-letter', $endmark => '.pm-endmark ' . $tag . ' p:last-of-type::after') as $main => $reset) {
			pg_assert_true(in_array($reset, $all, true), 'reset ' . $reset);
			pg_assert_true(_test_mag_specificity($reset) > _test_mag_specificity($main), $reset . ' outranks ' . $main);
		}
	}
	pg_assert_true(_test_mag_specificity('.pm-dropcap blockquote p::first-letter') < _test_mag_specificity($dropcap), 'a reset without the pseudo-class would lose');
	pg_assert_same(1, preg_match('/\.pm-endmark blockquote p:last-of-type::after,[^{]*\{content:none\}/', $own), 'the end mark resets to none');

	// The story text is contained.
	pg_assert_same(1, preg_match('/\.pm-body\{[^}]*position:relative[^}]*\}/', $plain), '.pm-body is positioned');
	pg_assert_same(1, preg_match('/\.pm-body\{[^}]*contain:paint[^}]*\}/', $plain), '.pm-body contains its paint');

	// The shelf keeps a focused title in view, and shows its own focus.
	pg_assert_contains('.pm-shelf:has(a:focus-visible){scroll-padding-inline:', $own, 'the shelf scrolls a focused title into view');
	pg_assert_contains('.pm-shelf:focus-visible{outline:', $own, 'the shelf shows its focus');

	// A kicker on paper or on the tinted surface is ink.
	pg_assert_contains('.pm-paper .pm-kicker,.bg-body-tertiary .pm-kicker{color:var(--bs-emphasis-color)}', $own, 'kickers on paper are ink');
	pg_assert_contains('.pm-one article:not(:first-of-type){display:none}', $own, 'a single-story list shows one row');
	pg_assert_contains('.pm-river .pg-sw-pagination{', $own, 'page links span the river');
	pg_assert_contains('.pm-issue-grid .pg-sw-pagination{', $own, 'page links span the issues');

	// Fraunces is never italic; no float in the story; the pull quote stays in view.
	foreach ($nodes as $n) {
		$classes = _test_mag_classes($n);
		$fraunces = array_intersect(array('pm-display', 'pm-title', 'pm-wordmark'), $classes) || preg_match('/^h[1-6]$/', _test_mag_tag($n));
		if ($fraunces) {
			pg_assert_false(in_array('fst-italic', $classes, true), 'no italic Fraunces: ' . implode(' ', $classes));
		}
	}
	$sticky = false;
	foreach (_test_mag_nodes($tpl['widgets']['blog_post']['tree']) as $n) {
		foreach (_test_mag_classes($n) as $c) {
			pg_assert_same(0, preg_match('/^float-/', $c), 'no float in the story: ' . $c);
		}
		if (in_array('sticky-xl-top', _test_mag_classes($n), true)) {
			$sticky = true;
			pg_assert_contains('"pull_quote"', json_encode($n), 'the sticky column holds the pull quote');
		}
	}
	pg_assert_true($sticky, 'the pull quote stays in view from xl');

	// Buttons, bands and display rules.
	$display_rules = array('pm-hero-img', 'pm-clamp-3', 'pm-brand', 'pm-slot', 'pm-navlink', 'pm-drawer');
	$band_overrides = array('pt-0', 'pb-4', 'pt-4');
	$block_classes = array_keys(_pg_designer_block_css_source()['rules']);
	pg_assert_true(count($block_classes) > 10, 'block rules found');
	foreach ($nodes as $n) {
		$classes = _test_mag_classes($n);
		$outlined = false;
		foreach ($classes as $c) {
			if (strpos($c, 'btn-outline-') === 0) {
				$outlined = true;
			}
			if (in_array($c, $block_classes, true)) {
				pg_assert_true(in_array($c, array('pg-snap', 'pg-snap-item', 'pg-circle-60', 'pg-circle-160'), true), 'only the shipped block rules: ' . $c);
			}
		}
		if ($outlined) {
			pg_assert_same('', _test_mag_visible_text($n), 'an outlined button has no words: ' . implode(' ', $classes));
		}
		if (isset($n['type'], $n['props']['componentType']) && $n['type'] === 'component' && $n['props']['componentType'] === 'language_switcher') {
			$variant = isset($n['props']['variant']) ? $n['props']['variant'] : 'secondary';
			pg_assert_false(!empty($n['props']['outline']) && $variant === 'secondary', 'the language switcher is not an outlined grey button');
		}
		if (in_array('pm-band', $classes, true)) {
			foreach ($classes as $c) {
				pg_assert_same(0, preg_match('/^py-/', $c), 'a band carries no py-*: ' . implode(' ', $classes));
				if (preg_match('/^p[tb]-/', $c)) {
					pg_assert_true(in_array($c, $band_overrides, true), 'a band\'s one-sided padding is pt-0, pb-4 or pt-4: ' . $c);
				}
			}
		}
		if (array_intersect($display_rules, $classes)) {
			foreach ($classes as $c) {
				pg_assert_same(0, preg_match('/^d-/', $c), 'no display utility where a rule sets the display: ' . implode(' ', $classes));
			}
		}
		if (in_array('pm-rank-item', $classes, true)) {
			pg_assert_false(in_array('border-top', $classes, true), 'no rule above the first rank item: the CSS draws them between rows');
		}
	}
	pg_assert_contains('.pm-rank-item:first-of-type{border-top:0}', $own, 'no rule above the first rank item');

	// Articles whose display a rule changes: the river's and the single lists'.
	foreach ($tpl['widgets'] as $key => $w) {
		if (!is_array($w['tree'])) {
			continue;
		}
		foreach (_test_mag_nodes_in_context($w['tree']) as $item) {
			if (_test_mag_tag($item[0]) === 'article' && array_intersect(array('pm-river', 'pm-one'), $item[1])) {
				foreach (_test_mag_classes($item[0]) as $c) {
					pg_assert_same(0, preg_match('/^d-/', $c), $key . ': no display utility on an article the CSS lays out');
				}
			}
		}
		if (isset($w['config']['regionType']) && $w['config']['regionType'] === 'form_list_view') {
			$found = _test_mag_loop_parent($w['tree']);
			if ($found !== null) {
				foreach ($found[1]['children'] as $item) {
					pg_assert_false(in_array('row', _test_mag_classes($item), true) || (isset($item['type']) && $item['type'] === 'row'),
						$key . ': a record\'s first element is not a row (the edit chip goes into it)');
				}
			}
		}
	}
	// A list prints its page links after its rows: only the site search,
	// whose renderer fills it, has a slot for them.
	$slots = array();
	foreach (_test_mag_all_trees() as $name => $tree) {
		foreach (_test_mag_nodes($tree) as $n) {
			if (isset($n['props']['_bindings']['section']) && $n['props']['_bindings']['section'] === 'pagination') {
				$slots[] = $name;
			}
		}
	}
	pg_assert_same(array('widget site_search'), $slots, 'only the site search has a page links slot');
	list($parent) = _test_mag_loop_parent($tpl['widgets']['issue_index']['tree']);
	pg_assert_true(in_array('pm-issue-grid', _test_mag_classes($parent), true), 'the issues row carries pm-issue-grid');

	// Accent glyphs sit on the page colour, never on paper or the tinted surface.
	foreach (_test_mag_pages() as $key => $p) {
		foreach (_test_mag_nodes_in_context(_test_mag_resolve($p['tree'])) as $item) {
			if (array_intersect(array('pm-rank-num', 'pm-dropcap', 'pm-endmark'), _test_mag_classes($item[0]))) {
				pg_assert_false((bool) array_intersect(array('pm-paper', 'bg-body-tertiary'), $item[1]), $key . ': an accent glyph on paper');
			}
		}
	}
}

// -- 16. e-mail colours ----------------------------------------------------------

// Every var() in an e-mail's inline style carries its own fallback.
function test_magazine_email_var_fallbacks()
{
	$tpl = _test_mag_tpl();
	$pages = _test_mag_pages();
	$trees = array($tpl['shared']['email_header']['tree'], $tpl['shared']['email_footer']['tree']);
	foreach (array_keys(pg_design_template_email_pages($tpl)) as $key) {
		$trees[] = $pages[$key]['tree'];
	}
	foreach ($tpl['widgets'] as $w) {
		if (strpos($w['page'], 'email_') === 0 && is_array($w['tree'])) {
			$trees[] = $w['tree'];
		}
	}
	$styles = 0;
	foreach ($trees as $tree) {
		foreach (_test_mag_nodes($tree) as $n) {
			$a = _test_mag_attrs($n);
			if (isset($a['style'])) {
				$styles++;
				pg_assert_same(0, preg_match('/var\(\s*--[\w-]+\s*\)/', $a['style']), 'var() fallback in ' . $a['style']);
				// A mail document is lang="en": CSS capitals would print a
				// Turkish i as a dotless I.
				pg_assert_same(0, preg_match('/text-transform\s*:\s*uppercase/', $a['style']), 'no CSS capitals in ' . $a['style']);
			}
			pg_assert_false(in_array('text-uppercase', _test_mag_classes($n), true), 'no text-uppercase in an e-mail');
		}
	}
	pg_assert_true($styles > 20, 'e-mail styles found');
}

// -- 17. CAPTCHA -----------------------------------------------------------------

// Every form a visitor sends carries the CAPTCHA node: the submit is checked
// whenever the CAPTCHA is on, and a designed form shows it only where its
// tree has that node.
function test_magazine_forms_carry_captcha()
{
	$tpl = _test_mag_tpl();
	$has = function ($tree) {
		foreach (_test_mag_nodes($tree) as $n) {
			if (isset($n['props']['_bindings']['section']) && $n['props']['_bindings']['section'] === 'captcha') {
				return true;
			}
		}
		return false;
	};
	foreach (array('article_form', 'contact_form', 'newsletter_form', 'newsletter_band') as $key) {
		pg_assert_true($has($tpl['widgets'][$key]['tree']), $key . ' has a CAPTCHA');
	}
	foreach (_test_mag_open_trees() as $page_key => $tree) {
		foreach (_test_mag_placed($tree) as $key) {
			if ($tpl['widgets'][$key]['config']['regionType'] === 'custom_form') {
				pg_assert_true($has($tpl['widgets'][$key]['tree']), $page_key . ' ' . $key . ' has a CAPTCHA');
			}
		}
	}
}

// The newsletter forms: the consent and the CAPTCHA come before the
// Subscribe button, so the button is the last thing filled in.
function test_magazine_newsletter_button_last()
{
	$tpl = _test_mag_tpl();
	foreach (array('newsletter_form', 'newsletter_band') as $key) {
		$order = array();
		foreach (_test_mag_nodes($tpl['widgets'][$key]['tree']) as $n) {
			$attrs = _test_mag_attrs($n);
			if (_test_mag_tag($n) === 'input' && isset($attrs['type']) && $attrs['type'] === 'email') {
				$order[] = 'email';
			} elseif (_test_mag_tag($n) === 'input' && isset($attrs['type']) && $attrs['type'] === 'checkbox') {
				$order[] = 'consent';
			} elseif (isset($n['props']['_bindings']['section']) && $n['props']['_bindings']['section'] === 'captcha') {
				$order[] = 'captcha';
			} elseif (_test_mag_tag($n) === 'button' && isset($attrs['type']) && $attrs['type'] === 'submit') {
				$order[] = 'submit';
			}
		}
		pg_assert_same(array('email', 'consent', 'captcha', 'submit'), $order, $key . ': e-mail, consent, CAPTCHA, then Subscribe');
	}
}

// -- 18. comment labels ----------------------------------------------------------

// The label is a singular noun: the platform pluralizes it and puts it in sentences.
function test_magazine_comment_labels()
{
	$found = 0;
	foreach (_test_mag_pages() as $key => $p) {
		if (isset($p['comments'])) {
			$found++;
			pg_assert_true(in_array($p['comments']['label'], array(lang('Letter'), lang('Message')), true), $key . ' comment label');
			pg_assert_true(isset($p['comments']['login']) && $p['comments']['login'] === true, $key . ' comments need sign-in');
		}
	}
	pg_assert_same(3, $found, 'three pages take comments');
	$pages = _test_mag_pages();
	pg_assert_same(lang('Letter'), $pages['blog_post']['comments']['label'], 'readers write letters');
	pg_assert_same(false, $pages['blog_post']['comments']['auto_publish'], 'letters are held for moderation');
	pg_assert_same(lang('Message'), $pages['submission']['comments']['label'], 'the desk writes messages');
}

// -- 19. landmarks, headings and pictures ----------------------------------------

function test_magazine_landmarks_and_alt()
{
	$tpl = _test_mag_tpl();

	// No complementary landmark inside another.
	$nested = function ($node, $inside) use (&$nested) {
		$count = 0;
		if (!is_array($node)) {
			return 0;
		}
		$is = (_test_mag_tag($node) === 'aside');
		if ($is && $inside) {
			$count++;
		}
		foreach (!empty($node['children']) && is_array($node['children']) ? $node['children'] : array() as $child) {
			$count += $nested($child, $inside || $is);
		}
		return $count;
	};
	foreach (_test_mag_pages() as $key => $p) {
		pg_assert_same(0, $nested(_test_mag_resolve($p['tree']), false), $key . ' has no aside inside an aside');
	}

	// A list picture has an empty alt: the title beside it names the story.
	$pictures = 0;
	$in_loop = function ($node, $loop) use (&$in_loop, &$pictures) {
		if (!is_array($node)) {
			return;
		}
		if (isset($node['type']) && $node['type'] === 'loop_area') {
			$loop = true;
		}
		$image = (isset($node['props']['contentType']) && $node['props']['contentType'] === 'image') || _test_mag_tag($node) === 'img';
		if ($loop && $image) {
			$pictures++;
			pg_assert_same('', isset($node['props']['alt']) ? $node['props']['alt'] : '', 'a list picture has an empty alt');
			pg_assert_false(isset($node['props']['_bindings']['alt']), 'a list picture binds no alt');
		}
		foreach (!empty($node['children']) && is_array($node['children']) ? $node['children'] : array() as $child) {
			$in_loop($child, $loop);
		}
	};
	foreach ($tpl['widgets'] as $w) {
		if ($w['config']['regionType'] === 'form_list_view' && is_array($w['tree'])) {
			$in_loop($w['tree'], false);
		}
	}
	pg_assert_true($pictures > 10, 'list pictures found');

	// The pull quote repeats a sentence of the story: screen readers skip it.
	$hidden = false;
	foreach (_test_mag_nodes($tpl['widgets']['blog_post']['tree']) as $n) {
		if (in_array('pm-quote', _test_mag_classes($n), true)) {
			$hidden = (isset(_test_mag_attrs($n)['aria-hidden']) && _test_mag_attrs($n)['aria-hidden'] === 'true');
		}
	}
	pg_assert_true($hidden, 'the pull quote is hidden from screen readers');

	// The staff bar links distinct pages: smart-active ignores a fragment.
	pg_assert_same(0, preg_match('/"href":"[^"]*#/', json_encode($tpl['shared']['staff_bar']['tree'])), 'the staff bar has no # link');

	// Nothing before the page's h1 is a heading: the masthead and its drawer
	// come first in the source.
	foreach (array('masthead', 'staff_bar') as $key) {
		foreach (_test_mag_nodes(_test_mag_resolve($tpl['shared'][$key]['tree'])) as $n) {
			pg_assert_same(0, preg_match('/^h[1-6]$/', _test_mag_tag($n)), $key . ' holds no heading');
		}
	}

	// One h1 per page: exactly one where no starter widget draws its own
	// layout. The story's not-found and found h1 show under exclusive flags.
	foreach (_test_mag_pages() as $key => $p) {
		$resolved = _test_mag_resolve($p['tree']);
		$starter = false;
		$h1 = 0;
		foreach (_test_mag_nodes_in_context($resolved) as $item) {
			if (isset($item[0]['type']) && $item[0]['type'] === 'widget' && !empty($item[0]['props']['starter'])) {
				$starter = true;
			}
			if (_test_mag_tag($item[0]) === 'h1' && !in_array('record_not_found', $item[2], true)) {
				$h1++;
			}
		}
		if ($starter) {
			pg_assert_true($h1 <= 1, $key . ' has at most one h1');
		} else {
			pg_assert_same(1, $h1, $key . ' has one h1');
		}
	}

	// Home: every h3 has an h2 before it in its own section.
	$home = _test_mag_resolve(_test_mag_pages()['home']['tree']);
	$h3 = 0;
	$walk = function ($node, $section) use (&$walk, &$h3) {
		if (!is_array($node)) {
			return;
		}
		$tag = _test_mag_tag($node);
		if ($tag === 'section') {
			$section = new ArrayObject(array('h2' => false));
		}
		if ($tag === 'h2' && $section !== null) {
			$section['h2'] = true;
		}
		if ($tag === 'h3') {
			$h3++;
			pg_assert_true($section !== null && $section['h2'], 'a home h3 follows an h2 of its section: ' . json_encode(isset($node['props']['text']) ? $node['props']['text'] : ''));
		}
		foreach (!empty($node['children']) && is_array($node['children']) ? $node['children'] : array() as $child) {
			$walk($child, $section);
		}
	};
	$walk($home, null);
	pg_assert_true($h3 >= 10, 'home h3 found');

	// Every aside is named: a complementary landmark needs a label.
	foreach (array_merge($tpl['widgets'], $tpl['shared']) as $key => $w) {
		if (!is_array($w['tree'])) {
			continue;
		}
		foreach (_test_mag_nodes($w['tree']) as $n) {
			if (_test_mag_tag($n) === 'aside') {
				$attrs = _test_mag_attrs($n);
				pg_assert_true(isset($attrs['aria-label']) || isset($attrs['aria-labelledby']), $key . ': an aside is named');
			}
		}
	}

	// The letters and the desk messages have an h2 above them, shown with the record.
	foreach (array('blog_post' => lang('Letters'), 'submission_view' => lang('Messages with the editors')) as $key => $title) {
		$found = false;
		foreach (_test_mag_nodes_in_context($tpl['widgets'][$key]['tree']) as $item) {
			if (_test_mag_tag($item[0]) === 'h2' && isset($item[0]['props']['text']) && $item[0]['props']['text'] === $title) {
				$found = in_array('record_found', $item[2], true);
			}
		}
		pg_assert_true($found, $key . ' has the h2 ' . $title . ' with the record');
	}
}

// -- 20. Turkish words ---------------------------------------------------------

// Many News Portal keys say "haber" (news) in Turkish: no text of the
// magazine reads that way, and the trap key "Story" (a tale) is not used.
function test_magazine_turkish_never_says_news()
{
	$tr = _test_mag_tr();
	$literals = _test_mag_lang_literals();
	pg_assert_true(count($literals) > 500, 'lang() literals found');
	$missing = 0;
	foreach ($literals as $key) {
		if (!isset($tr[$key])) {
			$missing++;
			continue;
		}
		pg_assert_same(0, preg_match('/haber/iu', $tr[$key]), 'no "haber": ' . $key);
	}
	pg_assert_same(0, $missing, 'every literal has a Turkish value');
	pg_assert_false(in_array('Story', $literals, true), 'no lang(\'Story\')');
}

// -- 21. single-story lists ----------------------------------------------------

// The installer's preview repeats every list three times: a one-story list
// keeps its rows in a pm-one box, each an article, and the CSS shows one.
function test_magazine_single_record_lists()
{
	$tpl = _test_mag_tpl();
	$found = 0;
	foreach ($tpl['widgets'] as $key => $w) {
		if (!isset($w['config']['regionType'], $w['config']['max_results']) || $w['config']['regionType'] !== 'form_list_view' || (int) $w['config']['max_results'] !== 1) {
			continue;
		}
		$found++;
		list($parent, $loop) = _test_mag_loop_parent($w['tree']);
		pg_assert_true(in_array('pm-one', _test_mag_classes($parent), true), $key . ' sits in pm-one');
		pg_assert_same(1, count($loop['children']), $key . ' has one row element');
		pg_assert_same('article', _test_mag_tag($loop['children'][0]), $key . ' row is an article');
	}
	pg_assert_same(3, $found, 'the cover, the quote and the letter');
	pg_assert_contains('.pm-one article:not(:first-of-type){display:none}', _test_mag_own_css(), 'one row shows');
}

// -- 22. the News Portal contract ----------------------------------------------

// The magazine and the news portal share their authoring contract: the
// statuses, the live rule, the folders, the desk's pages and e-mails, and the
// Stories form's field names with their kinds. Only the named differences
// are allowed.
function test_magazine_shares_the_news_contract()
{
	$news = _test_mag_news_tpl();
	if ($news === null) {
		pg_assert_true(true, 'the News Portal template is not here');
		return;
	}
	$mag = _test_mag_tpl();
	$mag_controls = _pg_cf_controls($mag['widgets']['article_form']['tree'], 0);
	$news_controls = _pg_cf_controls($news['widgets']['article_form']['tree'], 0);

	$options = function ($controls, $name) {
		$out = array();
		foreach ($controls[$name]['options'] as $o) {
			$out[] = $o['value'];
		}
		return $out;
	};
	pg_assert_same($options($news_controls, 'status'), $options($mag_controls, 'status'), 'the same statuses, in order');
	pg_assert_same($news['widgets']['blog_post']['config']['filters'], $mag['widgets']['blog_post']['config']['filters'], 'the same live rule on the story page');

	foreach (array('public', 'registration', 'private', 'contributors') as $key) {
		pg_assert_true(isset($news['folders'][$key], $mag['folders'][$key]), 'folder ' . $key . ' in both');
		pg_assert_same($news['folders'][$key]['access'], $mag['folders'][$key]['access'], 'folder ' . $key . ' access');
		pg_assert_same(isset($news['folders'][$key]['parent']) ? $news['folders'][$key]['parent'] : null,
			isset($mag['folders'][$key]['parent']) ? $mag['folders'][$key]['parent'] : null, 'folder ' . $key . ' parent');
	}

	$news_pages = _test_mag_pages($news);
	$mag_pages = _test_mag_pages($mag);
	foreach (array('blog_new' => 'contributors', 'my_articles' => 'contributors', 'submission' => 'contributors', 'staff' => 'private',
		'email_article_new' => 'private', 'email_article_received' => 'private', 'email_desk_message' => 'private') as $key => $folder) {
		pg_assert_same($folder, isset($news_pages[$key]) ? $news_pages[$key]['folder'] : null, 'news ' . $key);
		pg_assert_same($folder, isset($mag_pages[$key]) ? $mag_pages[$key]['folder'] : null, 'magazine ' . $key);
	}

	// The fields: the same names, kinds and office use, but for the named differences.
	$only_magazine = array('issue', 'pull_quote');
	$only_news = array('dateline', 'breaking');
	foreach (array('section', 'title', 'summary', 'body', 'cover_image', 'image_caption', 'image_credit', 'byline', 'byline_bio',
		'byline_photo', 'tags', 'note_to_editors', 'rights', 'status', 'publish_date', 'placement', 'reading_time', 'correction') as $name) {
		pg_assert_true(isset($mag_controls[$name], $news_controls[$name]), $name . ' in both forms');
	}
	foreach ($mag_controls as $name => $c) {
		if (in_array($name, $only_magazine, true)) {
			pg_assert_false(isset($news_controls[$name]), $name . ' is the magazine\'s own');
			continue;
		}
		pg_assert_true(isset($news_controls[$name]), $name . ' is in the news form too');
		if (isset($news_controls[$name])) {
			pg_assert_same($news_controls[$name]['type'], $c['type'], $name . ' is the same kind of field');
			pg_assert_same($news_controls[$name]['office_use_only'], $c['office_use_only'], $name . ' office use');
			pg_assert_same($news_controls[$name]['wysiwyg'], $c['wysiwyg'], $name . ' rich text');
		}
	}
	foreach ($news_controls as $name => $c) {
		if (!in_array($name, $only_news, true)) {
			pg_assert_true(isset($mag_controls[$name]), 'news field ' . $name . ' is in the magazine form');
		}
	}
	foreach ($only_magazine as $name) {
		pg_assert_true(isset($mag_controls[$name]), $name . ' is in the magazine form');
	}
	pg_assert_true($mag_controls['issue']['office_use_only'], 'the issue is office use only');
	pg_assert_false($mag_controls['pull_quote']['office_use_only'], 'the author suggests the pull quote');

	// The shared fields come in the same order in both forms.
	$shared_order = function ($controls, $skip) {
		return array_values(array_diff(array_keys($controls), $skip));
	};
	pg_assert_same($shared_order($news_controls, $only_news), $shared_order($mag_controls, $only_magazine), 'the shared fields keep the news order');
}

// -- 23. issue links -------------------------------------------------------------

// An issue's name goes into an address unencoded, from a closed list: only
// live public views print it, and the names stay plain.
function test_magazine_issue_links()
{
	$tpl = _test_mag_tpl();
	$issue_href = '{{page:issues}}?issue=^^issue^^';
	$where = array();
	foreach (_test_mag_all_trees() as $name => $tree) {
		foreach (_test_mag_nodes($tree) as $n) {
			foreach (_test_mag_hrefs($n) as $href) {
				if (strpos($href, '^^') === false) {
					continue;
				}
				pg_assert_true($href === $issue_href || preg_match('/\?r=\^\^reference_code\^\^$/', $href) === 1, $name . ' token address ' . $href);
				if ($href === $issue_href) {
					$where[$name] = true;
				}
			}
		}
	}
	$where = array_keys($where);
	sort($where);
	pg_assert_same(array('widget blog_post', 'widget home_cover', 'widget home_issues', 'widget issue_index'), $where, 'issue links only in live public views');
	foreach (array('blog_post', 'home_cover', 'home_issues', 'issue_index') as $key) {
		pg_assert_same(array(), _test_mag_live_problems($tpl['widgets'][$key]['config']), $key . ' is live');
	}

	$tr = _test_mag_tr();
	foreach (_test_mag_issue_options() as $name) {
		foreach (array($name, isset($tr[$name]) ? $tr[$name] : null) as $value) {
			pg_assert_true(is_string($value) && $value !== '', 'issue name and its Turkish: ' . $name);
			pg_assert_same(0, preg_match('/[&#?+%="\'<>]/', (string) $value), 'a plain issue name: ' . $value);
			pg_assert_true(mb_strlen((string) $value, 'UTF-8') <= 100, 'an issue name fits the address parameter: ' . $value);
		}
	}
}

// -- 24. Turkish addresses -------------------------------------------------------

// A page the news portal does not also have gets a Turkish address of its
// own, so two templates on one site never share one.
function test_magazine_turkish_slugs_are_its_own()
{
	$news = _test_mag_news_tpl();
	if ($news === null) {
		pg_assert_true(true, 'the News Portal template is not here');
		return;
	}
	$tr = _test_mag_tr();
	$turkish = function ($slug) use ($tr) {
		return isset($tr[$slug]) ? $tr[$slug] : $slug;
	};
	$news_pages = _test_mag_pages($news);
	$news_slugs = array();
	foreach ($news_pages as $p) {
		$news_slugs[] = $turkish($p['name']);
	}
	$checked = 0;
	foreach (_test_mag_pages() as $key => $p) {
		if (isset($news_pages[$key])) {
			continue;
		}
		$checked++;
		pg_assert_false(in_array($turkish($p['name']), $news_slugs, true), $key . ' has its own Turkish address: ' . $turkish($p['name']));
	}
	pg_assert_true($checked >= 6, 'the magazine\'s own pages were checked');
	$pages = _test_mag_pages();
	foreach (array('author' => 'contributors', 'my_articles' => 'my-stories') as $key => $slug) {
		pg_assert_same($slug, $pages[$key]['name'], $key . ' slug');
		pg_assert_false(in_array($turkish($slug), array($turkish($news_pages[$key]['name'])), true), $key . ' Turkish address differs from the news one');
	}
	foreach ($pages as $key => $p) {
		pg_assert_same(1, preg_match('/^[a-z0-9-]+$/', $turkish($p['name'])), $key . ' Turkish address is plain: ' . $turkish($p['name']));
	}
}

// -- 25. statuses explained ----------------------------------------------------

// "How review works" on the submit page names every status the author sees
// on My Stories, in the Status field's own words.
function test_magazine_review_card_names_every_status()
{
	$pages = _test_mag_pages();
	$texts = array();
	foreach (_test_mag_nodes($pages['blog_new']['tree']) as $n) {
		if (isset($n['props']['text']) && is_string($n['props']['text'])) {
			$texts[] = $n['props']['text'];
		}
	}
	foreach (_test_mag_answers('status') as $status) {
		pg_assert_true(in_array($status, $texts, true), 'How review works names ' . $status);
	}
}

// -- 26. focus rings -----------------------------------------------------------

// A button without a colour variant has no focus ring of its own, so every
// such button in a shared part sits in a part whose own CSS draws one.
function test_magazine_bare_buttons_have_a_focus_ring()
{
	$tpl = _test_mag_tpl();
	$own = _test_mag_own_css();
	$found = 0;
	foreach ($tpl['shared'] as $key => $s) {
		if (!is_array($s['tree'])) {
			continue;
		}
		$root = array();
		foreach (_test_mag_classes($s['tree']) as $c) {
			if (strpos($c, 'pm-') === 0) {
				$root[] = $c;
			}
		}
		foreach (_test_mag_nodes($s['tree']) as $n) {
			$classes = _test_mag_classes($n);
			if (!in_array('btn', $classes, true)) {
				continue;
			}
			$variant = false;
			foreach ($classes as $c) {
				if (preg_match('/^btn-(primary|secondary|success|danger|warning|info|light|dark|link|outline-[a-z]+)$/', $c)) {
					$variant = true;
				}
			}
			if ($variant) {
				continue;
			}
			$found++;
			$covered = false;
			foreach ($root as $c) {
				if (strpos($own, '.' . $c . ' .btn:focus-visible') !== false) {
					$covered = true;
				}
			}
			pg_assert_true($covered, $key . ': a button without a variant has a focus ring (' . implode(' ', $classes) . ')');
		}
	}
	pg_assert_true($found >= 2, 'the masthead\'s menu and search buttons are checked');
}

// -- 27. focus, notices and the reading column ---------------------------------

// Bootstrap's nav-link focus is a faint blue shadow: the section links and
// the underlined staff navs draw an outline of their own; the shelf's ring
// sits inside its box (the row reaches the screen's edges on a phone).
function test_magazine_nav_links_have_a_focus_ring()
{
	$own = _test_mag_own_css();
	pg_assert_same(1, preg_match('/\.pm-navlink:focus-visible\{[^}]*outline:2px solid[^}]*\}/', $own), 'the section links show their focus');
	pg_assert_same(1, preg_match('/\.nav-underline \.nav-link:focus-visible\{[^}]*outline:2px solid[^}]*\}/', $own), 'the underlined navs show their focus');
	pg_assert_same(1, preg_match('/\.pm-shelf:focus-visible\{[^}]*outline-offset:-/', $own), 'the shelf ring is inset');
	foreach (_test_mag_all_trees() as $where => $tree) {
		foreach (_test_mag_nodes_in_context($tree) as $item) {
			$classes = _test_mag_classes($item[0]);
			if (!in_array('nav-link', $classes, true)) {
				continue;
			}
			$covered = in_array('pm-navlink', $classes, true) || in_array('nav-underline', $item[1], true);
			pg_assert_true($covered, $where . ': a nav link has a focus ring of its own');
		}
	}
}

// A notice the platform prints in the letters (sign in to write) has links
// without .alert-link; they take the alert's link colour. The correction's
// rule is the full warning colour, not the faint border tint.
function test_magazine_notice_links_and_correction_rule()
{
	$own = _test_mag_own_css();
	pg_assert_contains('.pm-measure .alert a:not(.btn){color:var(--bs-alert-link-color)}', $own, 'notice links in the letters are readable');
	pg_assert_same(1, preg_match('/\.pm-correction\{[^}]*border-left:3px solid var\(--bs-warning\)[^}]*\}/', $own), 'the correction rule is visible');
}

// From xl the story text starts one column in whether or not the optional
// quote prints, in line with the letters' heading and the letters.
function test_magazine_letters_line_up_with_the_text()
{
	$tpl = _test_mag_tpl();
	$pages = _test_mag_pages();
	$cols = 0;
	foreach (array($tpl['widgets']['blog_post']['tree'], $pages['blog_post']['tree']) as $tree) {
		foreach (_test_mag_nodes($tree) as $n) {
			$classes = _test_mag_classes($n);
			if (in_array('col-xl-7', $classes, true)) {
				$cols++;
				pg_assert_true(in_array('offset-xl-1', $classes, true), 'a reading column starts one column in');
			}
			if (in_array('g-xl-5', $classes, true)) {
				pg_assert_true(in_array('justify-content-xl-start', $classes, true), 'a reading row starts at the left');
			}
		}
	}
	pg_assert_true($cols >= 3, 'the text, the letters heading and the letters are checked');
}

// "Number" reads "Sayı" in Turkish, the word for an issue: no layer has it.
function test_magazine_no_number_layer_name()
{
	foreach (_test_mag_all_nodes() as $n) {
		pg_assert_false(isset($n['props']['_label']) && $n['props']['_label'] === 'Number', 'no layer is named Number');
	}
}

// Letters are comments on a public page: the access note tells editors that
// moderating them needs the public folder.
function test_magazine_staff_note_names_the_public_folder_for_letters()
{
	$pages = _test_mag_pages();
	$found = false;
	foreach (_test_mag_nodes($pages['staff']['tree']) as $n) {
		$text = (isset($n['props']['text']) && is_string($n['props']['text'])) ? $n['props']['text'] : '';
		if (strpos($text, 'letters') !== false && strpos($text, 'public folder') !== false && strpos($text, 'Comments') !== false) {
			$found = true;
		}
	}
	pg_assert_true($found, 'the staff note names the public folder for letters');
}

// -- 28. focus on buttons and fields, e-mail frames, sign-in bands, headings --

// Focus is an ink outline on every button and field, not Bootstrap's
// half-clear shadow, which all but vanishes on paper and in the dark theme.
function test_magazine_buttons_and_fields_have_a_focus_ring()
{
	$own = _test_mag_own_css();
	pg_assert_same(1, preg_match('/(^|\n)body \.btn:focus-visible\{outline:2px solid var\(--bs-emphasis-color\);[^}]*box-shadow:none\}/', $own), 'every button shows its focus');
	pg_assert_same(1, preg_match('/(^|\n)body \.form-control:focus,body \.form-select:focus\{outline:2px solid var\(--bs-emphasis-color\)[^}]*\}/', $own), 'every field shows its focus');
	pg_assert_same(1, preg_match('/(^|\n)body \.form-check-input:focus-visible\{outline:2px solid var\(--bs-emphasis-color\)[^}]*\}/', $own), 'every check box shows its focus');
	// Page links and the close button are not .btn; Bootstrap's own focus
	// rules for them set outline:0 and a faint shadow.
	pg_assert_same(1, preg_match('/(^|\n)body \.page-link:focus-visible,body \.btn-close:focus-visible\{outline:2px solid var\(--bs-emphasis-color\);[^}]*box-shadow:none\}/', $own), 'page links and the close button show their focus');
	pg_assert_true(_test_mag_specificity('body .page-link:focus-visible') > _test_mag_specificity('.page-link:focus'), 'the page link ring outranks Bootstrap');
	// body .btn (0,2,1) outranks the theme's .btn:where(...):focus-visible (0,2,0).
	pg_assert_true(_test_mag_specificity('body .btn:focus-visible') > _test_mag_specificity('.btn:focus-visible'), 'the ring outranks the theme rule');
}

// An e-mail page opened in a browser under the dark theme keeps its light
// frame: its text colours are written for a light card.
function test_magazine_email_frames_stay_light()
{
	$found = 0;
	foreach (_test_mag_pages() as $key => $p) {
		$frame = isset($p['tree']['children'][0]) ? $p['tree']['children'][0] : null;
		if (!isset($frame['props']['_label']) || $frame['props']['_label'] !== lang('E-mail Frame')) {
			continue;
		}
		$found++;
		$attrs = _test_mag_attrs($frame);
		pg_assert_same('light', isset($attrs['data-bs-theme']) ? $attrs['data-bs-theme'] : null, $key . ': the frame is light');
	}
	pg_assert_true($found >= 9, 'every e-mail page is checked');
}

// The sign-in, sign-up and reset forms sit in a band, the same distance
// under the masthead as the other pages.
function test_magazine_sign_forms_sit_in_a_band()
{
	$found = 0;
	foreach (_test_mag_pages() as $key => $p) {
		foreach (_test_mag_nodes_in_context($p['tree']) as $item) {
			$n = $item[0];
			if (isset($n['type'], $n['props']['templateWidget']) && $n['type'] === 'shared_ref'
				&& in_array($n['props']['templateWidget'], array('login', 'register', 'forgot_password'), true)) {
				$found++;
				pg_assert_true(in_array('pm-band', $item[1], true), $key . ': the form sits in a band');
			}
		}
	}
	pg_assert_true($found >= 2, 'the sign-in and sign-up forms are checked');
}

// No two headings of a page say the same words (the footer's Sections
// column included), and the most read box opens with a section rule.
function test_magazine_headings_differ_and_most_read_has_a_rule()
{
	$pages = _test_mag_pages();
	foreach (array('home', 'search', 'blog', 'blog_post') as $key) {
		$seen = array();
		foreach (_test_mag_nodes(_test_mag_resolve($pages[$key]['tree'])) as $n) {
			if (_test_mag_tag($n) === 'h2' && isset($n['props']['text']) && is_string($n['props']['text'])) {
				$text = (string) $n['props']['text'];
				pg_assert_false(isset($seen[$text]), $key . ': the heading "' . $text . '" is used once');
				$seen[$text] = true;
			}
		}
	}
	$tpl = _test_mag_tpl();
	$ruled = false;
	foreach (_test_mag_nodes_in_context($tpl['widgets']['most_read']['tree']) as $item) {
		if (isset($item[0]['props']['id']) && $item[0]['props']['id'] === 'pm-most-read') {
			$ruled = in_array('pm-sec-head', $item[1], true);
		}
	}
	pg_assert_true($ruled, 'the most read heading sits in a section head');
}
