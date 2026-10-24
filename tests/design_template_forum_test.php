<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Community Forum & Docs design template
 * (includes/design_templates/pinegrap-forum.php): the Forum Topics,
 * Community Docs and Forum Reports forms and their office-use-only fields,
 * the live rules every list and record view a visitor or a member opens
 * carries (a topic while its Status is not Hidden, a guide while it is
 * Published, a report never), the members' own lists, the moderators'
 * pages, the one vocabulary of boards, kinds, statuses, sections and tags,
 * the sample topics and guides, the archives read from the address
 * (?board=, ?tag=, ?member=, ?section=), the links that carry a record's
 * value, the unanswered lists, the replies and the other comments, the
 * e-mail pages, the template's own CSS, its landmarks and headings, its
 * Turkish words and the installer preview. Pure: the template file is read
 * as the template loader reads it, and the trees are walked, with no
 * database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// -- helpers -----------------------------------------------------------------

// The template file.
function _test_forum_file()
{
	return PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-forum.php';
}

// The template as the loader reads it, read once per run.
function _test_forum_tpl()
{
	static $tpl = null;
	if ($tpl === null) {
		$tpl = _pg_tpl_include(_test_forum_file());
	}
	return $tpl;
}

// The Turkish translations, read as the file is.
function _test_forum_tr()
{
	static $tr = null;
	if ($tr === null) {
		$tr = json_decode((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr.json'), true);
		if (!is_array($tr)) {
			$tr = array();
		}
	}
	return $tr;
}

// The pages of the template by key.
function _test_forum_pages()
{
	$out = array();
	foreach (_test_forum_tpl()['pages'] as $p) {
		$out[$p['key']] = $p;
	}
	return $out;
}

// -- the vocabulary, written here once more ------------------------------------

// The boards in menu order: key => the name the Board field stores.
function _test_forum_boards()
{
	return array(
		'getting_started' => lang('Getting Started'),
		'help' => lang('Questions & Help'),
		'ideas' => lang('Ideas & Feedback'),
		'show_and_tell' => lang('Show & Tell'),
		'tips' => lang('Tips & Tricks'),
		'general' => lang('General Discussion'),
	);
}

function _test_forum_kinds()
{
	return array(lang('Question'), lang('Discussion'));
}

function _test_forum_topic_statuses()
{
	return array(lang('Open'), lang('Solved'), lang('Closed'), lang('Hidden'));
}

// The sections of the docs in menu order: key => the name the Section field stores.
function _test_forum_sections()
{
	return array(
		'getting_started' => lang('Getting Started'),
		'how_to' => lang('How-to Guides'),
		'reference' => lang('Reference'),
		'troubleshooting' => lang('Troubleshooting'),
		'integrations' => lang('Integrations'),
		'release_notes' => lang('Release Notes'),
	);
}

function _test_forum_doc_statuses()
{
	return array(lang('Awaiting review'), lang('In review'), lang('Changes requested'), lang('Published'), lang('Archived'), lang('Not accepted'));
}

function _test_forum_report_reasons()
{
	return array(lang('Spam'), lang('Harassment or abuse'), lang('Off-topic'), lang('Wrong board'), lang('Something else'));
}

function _test_forum_report_statuses()
{
	return array(lang('Open'), lang('Resolved'), lang('Dismissed'));
}

function _test_forum_yes_no()
{
	return array(lang('No'), lang('Yes'));
}

// The people the editor and the preview show beside a sample row: names
// stay out of the sample texts.
function _test_forum_people()
{
	return array('Jordan Lee', 'Priya Raman', 'Marcus Bell', 'Elena Ortiz', 'Sam Whitaker');
}

// The rows that keep a record public.
function _test_forum_topic_live()
{
	return array('field' => 'status', 'operator' => 'is not equal to', 'value' => lang('Hidden'), 'dynamic_value' => '');
}

function _test_forum_doc_live()
{
	return array('field' => 'status', 'operator' => 'is equal to', 'value' => lang('Published'), 'dynamic_value' => '');
}

// -- trees ---------------------------------------------------------------------

// The attributes of a node: name => value.
function _test_forum_attrs($node)
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
function _test_forum_classes($node)
{
	$class = (is_array($node) && isset($node['props']['cssClass']) && is_string($node['props']['cssClass'])) ? $node['props']['cssClass'] : '';
	return preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY);
}

// The tag of a node ('' when it has none).
function _test_forum_tag($node)
{
	return (is_array($node) && isset($node['props']['tag']) && is_string($node['props']['tag'])) ? strtolower($node['props']['tag']) : '';
}

// The flag a node shows under ('' when it always shows).
function _test_forum_flag($node)
{
	return (is_array($node) && isset($node['props']['_bindings']['eo_visible_if'])) ? (string) $node['props']['_bindings']['eo_visible_if'] : '';
}

// Every node of a tree, in document order.
function _test_forum_nodes($node)
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
			$out = array_merge($out, _test_forum_nodes($child));
		}
	}
	return $out;
}

// Every node of a tree with the classes and the flags of its ancestors:
// list of array(node, ancestor classes, ancestor flags, ancestor tags).
function _test_forum_nodes_in_context($node, $classes = array(), $flags = array(), $tags = array())
{
	$out = array();
	if (!is_array($node)) {
		return $out;
	}
	$out[] = array($node, $classes, $flags, $tags);
	$classes = array_merge($classes, _test_forum_classes($node));
	if (_test_forum_flag($node) !== '') {
		$flags[] = _test_forum_flag($node);
	}
	if (_test_forum_tag($node) !== '') {
		$tags[] = _test_forum_tag($node);
	}
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) {
			$out = array_merge($out, _test_forum_nodes_in_context($child, $classes, $flags, $tags));
		}
	}
	return $out;
}

// A tree with every placed shared component and widget put in its place: a
// shared component by its tree, a widget by a 'widget' node holding its tree
// (none for a starter tree), named in props._widget.
function _test_forum_resolve($node)
{
	if (!is_array($node)) {
		return $node;
	}
	$tpl = _test_forum_tpl();
	if (isset($node['type']) && $node['type'] === 'shared_ref') {
		if (isset($node['props']['templateShared'])) {
			$key = $node['props']['templateShared'];
			return isset($tpl['shared'][$key]['tree']) ? _test_forum_resolve($tpl['shared'][$key]['tree']) : null;
		}
		if (isset($node['props']['templateWidget'])) {
			$key = $node['props']['templateWidget'];
			$tree = isset($tpl['widgets'][$key]['tree']) ? $tpl['widgets'][$key]['tree'] : null;
			return array('type' => 'widget', 'props' => array('_widget' => $key, 'starter' => !is_array($tree)),
				'children' => is_array($tree) ? array(_test_forum_resolve($tree)) : array());
		}
	}
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $i => $child) {
			$node['children'][$i] = _test_forum_resolve($child);
		}
	}
	return $node;
}

// The keys of the widgets a resolved tree places, in order, once each.
function _test_forum_placed($resolved)
{
	$out = array();
	foreach (_test_forum_nodes($resolved) as $n) {
		if (isset($n['type']) && $n['type'] === 'widget' && !in_array($n['props']['_widget'], $out, true)) {
			$out[] = $n['props']['_widget'];
		}
	}
	return $out;
}

// The pages every visitor or member opens (every folder but 'private':
// 'public' and 'registration'), with their trees resolved.
function _test_forum_open_trees()
{
	$out = array();
	foreach (_test_forum_pages() as $key => $p) {
		if ($p['folder'] !== 'private') {
			$out[$key] = _test_forum_resolve($p['tree']);
		}
	}
	return $out;
}

// Every tree of the template: pages, widgets and shared components.
function _test_forum_all_trees()
{
	$tpl = _test_forum_tpl();
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
function _test_forum_all_nodes()
{
	$nodes = array();
	foreach (_test_forum_all_trees() as $tree) {
		$nodes = array_merge($nodes, _test_forum_nodes($tree));
	}
	return $nodes;
}

// The text a visitor sees in a node: its own and its children's, leaving
// out what only a screen reader hears.
function _test_forum_visible_text($node)
{
	if (!is_array($node) || in_array('visually-hidden', _test_forum_classes($node), true)) {
		return '';
	}
	$text = isset($node['props']['text']) && is_string($node['props']['text']) ? $node['props']['text'] : '';
	foreach (!empty($node['children']) && is_array($node['children']) ? $node['children'] : array() as $child) {
		$text .= _test_forum_visible_text($child);
	}
	return trim($text);
}

// The addresses a node links to: its href prop and its href attribute.
function _test_forum_hrefs($node)
{
	$out = array();
	if (isset($node['props']['href']) && is_string($node['props']['href'])) {
		$out[] = $node['props']['href'];
	}
	$a = _test_forum_attrs($node);
	if (isset($a['href'])) {
		$out[] = $a['href'];
	}
	return $out;
}

// The loop of a widget tree and its parent: array(parent, loop), null when
// it has none.
function _test_forum_loop_parent($tree)
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

// -- forms and views -----------------------------------------------------------

// The form widget each form page owns: its {{tab:}} => the widget's key.
function _test_forum_forms()
{
	return array(
		'{{tab:new_topic}}' => 'topic_form',
		'{{tab:doc_new}}' => 'doc_form',
		'{{tab:report}}' => 'report_form',
		'{{tab:contact}}' => 'contact_form',
		'{{tab:staff_entry}}' => 'staff_form',
	);
}

// Whether a widget config lists records or shows one.
function _test_forum_is_view($cfg)
{
	return isset($cfg['regionType']) && in_array($cfg['regionType'], array('form_list_view', 'form_item_view'), true);
}

// The key of the form widget whose records a view shows ('' for none).
function _test_forum_form_of($cfg)
{
	$forms = _test_forum_forms();
	if (!_test_forum_is_view($cfg) || !isset($cfg['custom_form_page_id'], $forms[$cfg['custom_form_page_id']])) {
		return '';
	}
	return $forms[$cfg['custom_form_page_id']];
}

// Whether a list only counts: its loop area is empty, so it prints its
// surround with the number and reads no row.
function _test_forum_is_count_list($w)
{
	if (!isset($w['config']['regionType']) || $w['config']['regionType'] !== 'form_list_view' || !is_array($w['tree'])) {
		return false;
	}
	$found = _test_forum_loop_parent($w['tree']);
	return $found !== null && empty($found[1]['children']);
}

// Whether a view config carries a filter row.
function _test_forum_has_row($cfg, $row)
{
	foreach (isset($cfg['filters']) && is_array($cfg['filters']) ? $cfg['filters'] : array() as $f) {
		if (isset($f['field'], $f['operator'], $f['value']) && $f['field'] === $row['field'] && $f['operator'] === $row['operator']
			&& $f['value'] === $row['value'] && (isset($f['dynamic_value']) ? $f['dynamic_value'] : '') === $row['dynamic_value']) {
			return true;
		}
	}
	return false;
}

// The form control named $name in a tree, null when there is none.
function _test_forum_control($tree, $name)
{
	foreach (_test_forum_nodes($tree) as $n) {
		if (in_array(_test_forum_tag($n), array('input', 'select', 'textarea'), true)) {
			$a = _test_forum_attrs($n);
			if (isset($a['name']) && $a['name'] === $name) {
				return $n;
			}
		}
	}
	return null;
}

// The options of a select node: list of array(value, selected).
function _test_forum_options($select)
{
	$out = array();
	foreach (is_array($select) && isset($select['children']) ? $select['children'] : array() as $o) {
		if (_test_forum_tag($o) !== 'option') {
			continue;
		}
		$a = _test_forum_attrs($o);
		$value = isset($a['value']) ? $a['value'] : (isset($o['props']['text']) ? (string) $o['props']['text'] : '');
		$out[] = array($value, array_key_exists('selected', $a));
	}
	return $out;
}

// A form's controls as the form stores them.
function _test_forum_controls($form)
{
	return _pg_cf_controls(_test_forum_tpl()['widgets'][$form]['tree'], 0);
}

// The stored answers of a form's pick list or radio group $name.
function _test_forum_answers($form, $name)
{
	$controls = _test_forum_controls($form);
	$out = array();
	foreach (isset($controls[$name]) ? $controls[$name]['options'] : array() as $o) {
		$out[] = $o['value'];
	}
	return $out;
}

// The folder a form's file field keeps what is sent in, as the template
// names it.
function _test_forum_upload_folder($form, $name)
{
	$n = _test_forum_control(_test_forum_tpl()['widgets'][$form]['tree'], $name);
	return isset($n['props']['_cf']['upload_folder_id']) ? $n['props']['_cf']['upload_folder_id'] : null;
}

// The sample records of the form a page owns.
function _test_forum_samples($page_key)
{
	$pages = _test_forum_pages();
	return $pages[$page_key]['form']['sample_records'];
}

// The template's own CSS: the User Styles entry without the block rules.
function _test_forum_own_css()
{
	$tpl = _test_forum_tpl();
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
function _test_forum_selectors($css)
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
function _test_forum_specificity($selector)
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

// The lang() literals of the template file, as written (\' unescaped).
function _test_forum_lang_literals()
{
	$source = (string) file_get_contents(_test_forum_file());
	preg_match_all("/\\blang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $source, $m);
	$out = array();
	foreach ($m[1] as $literal) {
		$out[] = strtr($literal, array("\\'" => "'", '\\\\' => '\\'));
	}
	return array_values(array_unique($out));
}

// The texts of a sample's rich text: its paragraphs, subheads, list items
// and code, decoded.
function _test_forum_rich_parts($html)
{
	preg_match_all('~<(p|h2|li|code)>(.*?)</\1>~s', $html, $m);
	$out = array();
	foreach ($m[2] as $part) {
		$out[] = html_entity_decode($part, ENT_QUOTES, 'UTF-8');
	}
	return $out;
}

// -- 1. the file ---------------------------------------------------------------

// The file loads without a notice (the runner turns one into an error),
// carries the template keys, and its order, look, palette and thumbnail are
// known to the platform and its own.
function test_forum_template_loads()
{
	$tpl = _test_forum_tpl();
	pg_assert_true(is_array($tpl), 'the template is an array');
	foreach (array('name', 'version', 'framework', 'order', 'icon', 'thumb', 'description', 'look', 'palette', 'highlights', 'folders', 'pages', 'widgets', 'shared', 'assets') as $key) {
		pg_assert_true(array_key_exists($key, $tpl), 'key ' . $key);
	}
	pg_assert_same(lang('Community Forum & Docs'), $tpl['name'], 'name');
	pg_assert_same(1, preg_match('/^\d+\.\d+\.\d+$/', $tpl['version']), 'version x.y.z');
	pg_assert_same('bootstrap5', $tpl['framework'], 'framework');
	pg_assert_same(90, $tpl['order'], 'order');
	pg_assert_same('bi-chat-square-text', $tpl['icon'], 'icon');
	pg_assert_same('forum', $tpl['thumb'], 'thumb');
	pg_assert_same(array('look' => 'minimal', 'palette' => 'aurora'), pg_design_template_theme($tpl), 'a built-in look and palette');
	pg_assert_true(function_exists('_pg_design_thumb_forum'), 'the thumbnail is drawn by its own function');
	pg_assert_contains('<svg class="pg-design-thumb"', pg_design_thumb_svg('minimal', 'aurora', '', '', 'forum'), 'the thumbnail draws');
	pg_assert_same(4, count($tpl['highlights']), 'four highlights');
	pg_assert_true(trim($tpl['description']) !== '', 'a description');

	$orders = array();
	$themes = array();
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php') as $file) {
		$other = _pg_tpl_include($file);
		if (!is_array($other)) {
			continue;
		}
		if (isset($other['order'])) {
			$orders[basename($file, '.php')] = (int) $other['order'];
		}
		$themes[basename($file, '.php')] = (isset($other['look']) ? $other['look'] : '') . '/' . (isset($other['palette']) ? $other['palette'] : '');
	}
	pg_assert_same(array('pinegrap-forum'), array_keys($orders, 90, true), 'order 90 is the forum\'s alone');
	pg_assert_same(array('pinegrap-forum'), array_keys($themes, 'minimal/aurora', true), 'no other template suggests minimal with aurora');
	pg_assert_true(isset(pg_design_templates()['pinegrap-forum']), 'the template is listed');
	pg_assert_same(55, count($tpl['pages']), '55 pages');

	// The folders: the template's root and its three folders, the private
	// one for the moderators.
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']), 'the folders');
	pg_assert_same(array('name' => 'pinegrap_forum', 'access' => 'public'), $tpl['folders']['root'], 'the root folder');
	foreach (array('public' => 'public', 'registration' => 'registration', 'private' => 'private') as $key => $access) {
		pg_assert_same($access, $tpl['folders'][$key]['access'], $key . ' access');
		pg_assert_same('root', $tpl['folders'][$key]['parent'], $key . ' parent');
	}
}

// -- 2. placeholders -----------------------------------------------------------

// Every page, tab, folder, widget and shared component a placeholder names
// exists, and every widget sits on a page or on none.
function test_forum_placeholders_resolve()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
	$json = json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']));

	preg_match_all('/\{\{(page|tab):([a-z0-9_-]+)\}\}/', $json, $m, PREG_SET_ORDER);
	pg_assert_true(count($m) > 100, 'page and tab placeholders found');
	foreach ($m as $x) {
		pg_assert_true(isset($pages[$x[2]]), '{{' . $x[1] . ':' . $x[2] . '}} names a page');
	}
	preg_match_all('/\{\{folder:([a-z0-9_-]+)\}\}/', $json, $m, PREG_SET_ORDER);
	pg_assert_true(count($m) >= 3, 'folder placeholders found');
	foreach ($m as $x) {
		pg_assert_true(isset($tpl['folders'][$x[1]]), '{{folder:' . $x[1] . '}} names a folder');
	}
	pg_assert_same(0, preg_match('/\{\{contact_group:/', $json), 'no contact group is named: the template makes none');
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
	foreach ($tpl['shared'] as $key => $s) {
		pg_assert_true(in_array($key, $m[1], true), 'shared ' . $key . ' is placed');
	}
	$names = array();
	foreach ($pages as $key => $p) {
		pg_assert_true(isset($tpl['folders'][$p['folder']]), 'page ' . $key . ' folder');
		pg_assert_false(isset($names[$p['name']]), 'page ' . $key . ' has an address of its own: ' . $p['name']);
		$names[$p['name']] = true;
	}
	pg_assert_same(count($tpl['pages']), count($pages), 'every page key is used once');
}

// -- 3. the live rules ---------------------------------------------------------

// Every list and record view placed on a page a visitor or a member opens
// shows topics only while they are not Hidden and guides only while they
// are Published; a members' page shows a guide in another status only to
// the member who sent it; no such page shows a report or the staff
// directory; and nothing on a public page is anyone's own records.
function test_forum_public_views_carry_live_rules()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
	$checked = array();
	foreach (_test_forum_open_trees() as $page_key => $tree) {
		$folder = $pages[$page_key]['folder'];
		foreach (_test_forum_placed($tree) as $key) {
			$cfg = $tpl['widgets'][$key]['config'];
			if (!_test_forum_is_view($cfg)) {
				continue;
			}
			$form = _test_forum_form_of($cfg);
			$submitter_only = isset($cfg['access_control']) && $cfg['access_control'] === 'submitter_only';
			$own = !empty($cfg['viewer_filter']) || $submitter_only;
			$where = $page_key . ' ' . $key;
			pg_assert_true($form !== '', $where . ' shows a form of the template');
			if ($folder === 'public') {
				pg_assert_false($own, $where . ': a public page shows nobody\'s own records');
			}
			pg_assert_false($form === 'report_form', $where . ': no report outside the moderators\' pages');
			pg_assert_false($form === 'staff_form', $where . ': the staff directory stays with the moderators');
			if ($form === 'topic_form') {
				pg_assert_true(_test_forum_has_row($cfg, _test_forum_topic_live()), $where . ' leaves hidden topics out');
				pg_assert_false($submitter_only, $where . ': a hidden topic opens for nobody, its author included');
				$checked[$key] = true;
			}
			if ($form === 'doc_form') {
				if ($folder === 'public') {
					pg_assert_true(_test_forum_has_row($cfg, _test_forum_doc_live()), $where . ' shows published guides only');
					$checked[$key] = true;
				} else {
					pg_assert_true($own, $where . ': a guide on the desk is the member\'s own');
				}
			}
			if (in_array($form, array('topic_form', 'doc_form'), true) && !empty($cfg['viewer_filter'])) {
				pg_assert_same(false, $cfg['viewer_filter_editor'], $where . ': a moderator\'s own page lists their own records too');
				$checked[$key] = true;
			}
		}
	}
	$want = array('home_count_topics', 'home_count_solved', 'home_count_guides', 'home_announcements', 'home_latest', 'home_unanswered',
		'home_popular', 'home_guides', 'forum_count', 'forum_pinned', 'forum_topics', 'board_head', 'board_pinned', 'board_topics',
		'tag_topics', 'member_topics', 'unanswered_topics', 'docs_count', 'docs_featured', 'docs_latest', 'docs_all', 'section_guides',
		'topic_view', 'topic_aside', 'doc_view', 'my_topics_started', 'my_topics_followed', 'my_docs_list', 'my_docs_live');
	foreach (array_keys(_test_forum_boards()) as $board) {
		$want[] = 'board_count_' . $board;
	}
	foreach (array_keys(_test_forum_sections()) as $section) {
		$want[] = 'doc_count_' . $section;
	}
	foreach ($want as $key) {
		pg_assert_true(isset($checked[$key]), $key . ' is checked');
	}

	// The check itself: a list without the live row is caught.
	$cfg = $tpl['widgets']['home_latest']['config'];
	$cfg['filters'] = array();
	pg_assert_false(_test_forum_has_row($cfg, _test_forum_topic_live()), 'a missing live row is caught');
}

// Every filter, search field and order of the views of the template's forms
// names a field of the form or a field every record has, with an operator
// and a dynamic value the renderer knows, and compares a pick list with one
// of its stored answers: a list whose filter misses shows nothing.
function test_forum_view_settings_name_real_fields()
{
	$tpl = _test_forum_tpl();
	$operators = array('contains', 'does not contain', 'is equal to', 'is not equal to',
		'is less than', 'is less than or equal to', 'is greater than', 'is greater than or equal to');
	$standard = pg_sw_standard_fields();
	$counters = pg_sw_form_order_counters();
	$rows = 0;
	foreach ($tpl['widgets'] as $key => $w) {
		$cfg = $w['config'];
		$form = _test_forum_form_of($cfg);
		if ($form === '') {
			continue;
		}
		$controls = _test_forum_controls($form);
		foreach (isset($cfg['filters']) ? $cfg['filters'] : array() as $f) {
			$rows++;
			pg_assert_true(isset($controls[$f['field']]) || isset($standard[$f['field']]), $key . ' filters on a field: ' . $f['field']);
			pg_assert_true(in_array($f['operator'], $operators, true), $key . ' filters with a known operator: ' . $f['operator']);
			$dynamic = isset($f['dynamic_value']) ? $f['dynamic_value'] : '';
			pg_assert_true(in_array($dynamic, array('', 'url parameter', 'days ago'), true), $key . ' dynamic value ' . $dynamic);
			if ($dynamic === 'url parameter') {
				pg_assert_same($f['dynamic_value_attribute'], pg_sw_url_param_name($f['dynamic_value_attribute']), $key . ' address parameter is accepted as it is');
			} elseif ($dynamic === '' && isset($controls[$f['field']]) && $controls[$f['field']]['options']) {
				$values = array();
				foreach ($controls[$f['field']]['options'] as $o) {
					$values[] = $o['value'];
				}
				pg_assert_true(in_array($f['value'], $values, true), $key . ' compares ' . $f['field'] . ' with a stored answer: ' . $f['value']);
			}
		}
		foreach (isset($cfg['search_fields']) ? $cfg['search_fields'] : array() as $field) {
			pg_assert_true(isset($controls[$field]), $key . ' searches a field of the form: ' . $field);
		}
		foreach (array('order_by_field', 'order_by_2_field') as $order) {
			$field = isset($cfg[$order]) ? (string) $cfg[$order] : '';
			if ($field === '') {
				continue;
			}
			pg_assert_true(isset($controls[$field]) || isset($standard[$field]) || in_array($field, $counters, true), $key . ' orders by a field: ' . $field);
			if (in_array($field, array('number_of_views', 'number_of_comments', 'recent_views', 'newest_activity_date_and_time'), true)) {
				pg_assert_true(!empty($cfg['detail_page_id']), $key . ' reads ' . $field . ' for its detail page');
			}
		}
	}
	pg_assert_true($rows > 80, 'filter rows were checked');
}

// -- 4. private views ----------------------------------------------------------

// A guide on the desk, a report, a conversation and the e-mails open only
// for whoever sent the record and the moderators; the topic and the guide
// pages show their record by the live rule alone; the forms sit on the
// members' pages, with addresses by reference code; the moderators' pages
// are private.
function test_forum_private_views()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
	$w = $tpl['widgets'];

	foreach (array('doc_submission_view' => 'registration', 'conversation' => 'registration', 'staff_report_view' => 'private') as $key => $folder) {
		pg_assert_same('submitter_only', $w[$key]['config']['access_control'], $key . ' access');
		pg_assert_true(empty($w[$key]['config']['filters']), $key . ' has no filters');
		pg_assert_same($folder, $pages[$w[$key]['page']]['folder'], $key . ' page folder');
	}
	foreach (array('topic_view' => _test_forum_topic_live(), 'topic_aside' => _test_forum_topic_live(), 'doc_view' => _test_forum_doc_live()) as $key => $live) {
		pg_assert_same('public', $w[$key]['config']['access_control'], $key . ' is public');
		pg_assert_same(array($live), $w[$key]['config']['filters'], $key . ' filters are the live rule');
		pg_assert_same('public', $pages[$w[$key]['page']]['folder'], $key . ' page folder');
	}
	pg_assert_same('title', $w['topic_view']['config']['title_field'], 'a topic\'s title is its page\'s title');
	pg_assert_same('title', $w['doc_view']['config']['title_field'], 'a guide\'s title is its page\'s title');

	foreach (array('new_topic', 'doc_new', 'report') as $key) {
		pg_assert_same('registration', $pages[$key]['folder'], $key . ' folder');
		pg_assert_same(0, $pages[$key]['form']['pretty_urls'], $key . ' addresses use the reference code');
	}
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'inbox', 'conversation',
		'my_topics', 'my_docs', 'doc_submission') as $key) {
		pg_assert_same('registration', $pages[$key]['folder'], $key . ' folder');
	}
	foreach (array('staff', 'staff_topics', 'staff_docs', 'staff_reports', 'staff_report', 'staff_directory', 'staff_entry') as $key) {
		pg_assert_same('private', $pages[$key]['folder'], $key . ' folder');
		pg_assert_contains('"templateShared":"staff_bar"', json_encode($pages[$key]['tree']), $key . ' has the moderators\' bar');
	}

	// The members' own lists: their own records only, never every record
	// for whoever may edit the form.
	$own = array('my_topics_started' => array(true, false), 'my_topics_followed' => array(false, true), 'my_docs_list' => array(true, false), 'my_docs_live' => array(true, false));
	foreach ($own as $key => $want) {
		$cfg = $w[$key]['config'];
		pg_assert_true(!empty($cfg['viewer_filter']), $key . ' lists the viewer\'s own');
		pg_assert_same($want[0], $cfg['viewer_filter_submitter'], $key . ' submitter');
		pg_assert_same($want[1], $cfg['viewer_filter_watcher'], $key . ' watcher');
		pg_assert_same(false, $cfg['viewer_filter_editor'], $key . ' editor');
		pg_assert_same('registration', $pages[$w[$key]['page']]['folder'], $key . ' page folder');
	}

	// A report is shown on the moderators' pages and in their e-mails
	// alone, and a viewer's own records on the members' pages alone.
	foreach ($w as $key => $widget) {
		$cfg = $widget['config'];
		if ($widget['page'] === '') {
			continue;
		}
		$folder = $pages[$widget['page']]['folder'];
		if (_test_forum_form_of($cfg) === 'report_form') {
			pg_assert_same('private', $folder, $key . ': a report view is private');
		}
		if (!empty($cfg['viewer_filter'])) {
			pg_assert_same('registration', $folder, $key . ': a viewer\'s own list is on a members\' page');
		}
	}
}

// -- 5. the forms --------------------------------------------------------------

// What a member fills in: the fields, their kinds, roles and limits, the
// pick lists' answers and the upload folders.
function test_forum_author_fields()
{
	$topic = _test_forum_controls('topic_form');
	pg_assert_same(array('board', 'kind', 'title', 'body', 'tags', 'attachment', 'guidelines', 'status', 'pinned', 'announcement', 'staff_note'), array_keys($topic), 'the Forum Topics fields, in order');
	pg_assert_same(array('pick list', true, 'category'), array($topic['board']['type'], $topic['board']['required'], $topic['board']['rss_field']), 'board');
	pg_assert_same(array_values(_test_forum_boards()), _test_forum_answers('topic_form', 'board'), 'the boards, in order');
	pg_assert_same(array('radio button', true), array($topic['kind']['type'], $topic['kind']['required']), 'kind');
	pg_assert_same(_test_forum_kinds(), _test_forum_answers('topic_form', 'kind'), 'the kinds');
	pg_assert_same(array('text box', true, 'title', 120), array($topic['title']['type'], $topic['title']['required'], $topic['title']['rss_field'], $topic['title']['maxlength']), 'title');
	pg_assert_same(array('text area', true, true), array($topic['body']['type'], $topic['body']['required'], $topic['body']['wysiwyg']), 'body is required rich text');
	pg_assert_same(array('text box', false), array($topic['tags']['type'], $topic['tags']['required']), 'tags');
	pg_assert_same(array('file upload', false, '{{folder:public}}'), array($topic['attachment']['type'], $topic['attachment']['required'], _test_forum_upload_folder('topic_form', 'attachment')), 'attachment');
	pg_assert_same(array('check box', true), array($topic['guidelines']['type'], $topic['guidelines']['required']), 'the guidelines box');

	$doc = _test_forum_controls('doc_form');
	pg_assert_same(array('section', 'title', 'summary', 'body', 'version', 'tags', 'attachment', 'byline', 'rights', 'status', 'featured', 'doc_order'), array_keys($doc), 'the Community Docs fields, in order');
	pg_assert_same(array('pick list', true, 'category'), array($doc['section']['type'], $doc['section']['required'], $doc['section']['rss_field']), 'section');
	pg_assert_same(array_values(_test_forum_sections()), _test_forum_answers('doc_form', 'section'), 'the sections, in order');
	pg_assert_same(array(true, 'title'), array($doc['title']['required'], $doc['title']['rss_field']), 'title');
	pg_assert_same(array('text area', true, 'description'), array($doc['summary']['type'], $doc['summary']['required'], $doc['summary']['rss_field']), 'summary');
	pg_assert_same(array('text area', true, true), array($doc['body']['type'], $doc['body']['required'], $doc['body']['wysiwyg']), 'body is required rich text');
	pg_assert_same(array('text box', false), array($doc['version']['type'], $doc['version']['required']), 'version');
	pg_assert_same(array('file upload', false, '{{folder:public}}'), array($doc['attachment']['type'], $doc['attachment']['required'], _test_forum_upload_folder('doc_form', 'attachment')), 'attachment');
	pg_assert_same(array('text box', true), array($doc['byline']['type'], $doc['byline']['required']), 'byline');
	pg_assert_same(array('check box', true), array($doc['rights']['type'], $doc['rights']['required']), 'the rights box');

	$report = _test_forum_controls('report_form');
	pg_assert_same(array('post', 'reason', 'details', 'status', 'staff_note'), array_keys($report), 'the Forum Reports fields, in order');
	pg_assert_same(array('text box', true, 'post'), array($report['post']['type'], $report['post']['required'], $report['post']['url_param']), 'post, filled from ?post=');
	pg_assert_same('post', pg_sw_url_param_name('post'), 'the post parameter is accepted as it is');
	pg_assert_same(array('pick list', true), array($report['reason']['type'], $report['reason']['required']), 'reason');
	pg_assert_same(_test_forum_report_reasons(), _test_forum_answers('report_form', 'reason'), 'the reasons');
	pg_assert_same(array('text area', false), array($report['details']['type'], $report['details']['required']), 'details');

	$contact = _test_forum_controls('contact_form');
	pg_assert_same(array('first_name', 'last_name', 'email', 'topic', 'message'), array_keys($contact), 'the contact fields');
	pg_assert_same('email_address', $contact['email']['contact_field'], 'the e-mail is the contact\'s');

	// Every label names a control of its form, and every hint the control
	// it describes.
	foreach (array('topic_form', 'doc_form', 'report_form', 'contact_form', 'staff_form') as $form) {
		$tree = _test_forum_tpl()['widgets'][$form]['tree'];
		$ids = array();
		foreach (_test_forum_nodes($tree) as $n) {
			if (!empty($n['props']['id'])) {
				$ids[$n['props']['id']] = _test_forum_tag($n);
			}
		}
		foreach (_test_forum_nodes($tree) as $n) {
			$a = _test_forum_attrs($n);
			if (_test_forum_tag($n) === 'label') {
				pg_assert_true(isset($a['for'], $ids[$a['for']]) && in_array($ids[$a['for']], array('input', 'select', 'textarea'), true), $form . ': a label names its control');
			}
			if (isset($a['aria-describedby'])) {
				pg_assert_true(isset($ids[$a['aria-describedby']]), $form . ': a hint is there for ' . $a['aria-describedby']);
			}
		}
	}
}

// The fields the moderators fill are office use only, each alone in a box
// with its label, store a default a member cannot change (a real option: an
// empty one is never stored, and the control panel would save the first
// answer on the next change), and leave nothing of themselves on a
// member's form.
function test_forum_office_fields()
{
	$tpl = _test_forum_tpl();
	$office = array(
		'topic_form' => array('status' => array('pick list', lang('Open')), 'pinned' => array('pick list', lang('No')),
			'announcement' => array('pick list', lang('No')), 'staff_note' => array('text area', '')),
		'doc_form' => array('status' => array('pick list', lang('Awaiting review')), 'featured' => array('pick list', lang('No')),
			'doc_order' => array('text box', '')),
		'report_form' => array('status' => array('pick list', lang('Open')), 'staff_note' => array('text area', '')),
	);
	$author = array(
		'topic_form' => array('board', 'kind', 'title', 'body', 'tags', 'attachment', 'guidelines'),
		'doc_form' => array('section', 'title', 'summary', 'body', 'version', 'tags', 'attachment', 'byline', 'rights'),
		'report_form' => array('post', 'reason', 'details'),
	);
	pg_assert_same(_test_forum_topic_statuses(), _test_forum_answers('topic_form', 'status'), 'the topic statuses');
	pg_assert_same(_test_forum_yes_no(), _test_forum_answers('topic_form', 'pinned'), 'pinned');
	pg_assert_same(_test_forum_yes_no(), _test_forum_answers('topic_form', 'announcement'), 'announcement');
	pg_assert_same(_test_forum_doc_statuses(), _test_forum_answers('doc_form', 'status'), 'the desk statuses');
	pg_assert_same(_test_forum_yes_no(), _test_forum_answers('doc_form', 'featured'), 'featured');
	pg_assert_same(_test_forum_report_statuses(), _test_forum_answers('report_form', 'status'), 'the report statuses');

	foreach ($office as $form => $fields) {
		$tree = $tpl['widgets'][$form]['tree'];
		$controls = _pg_cf_controls($tree, 0);
		foreach ($fields as $name => $want) {
			$n = _test_forum_control($tree, $name);
			pg_assert_true(is_array($n), $form . ' ' . $name . ' exists');
			pg_assert_true(isset($n['props']['_cf']['office_use_only']) && $n['props']['_cf']['office_use_only'] === true, $form . ' ' . $name . ' is office use only');
			pg_assert_false(array_key_exists('required', _test_forum_attrs($n)), $form . ' ' . $name . ' is not required');
			pg_assert_same($want[0], $controls[$name]['type'], $form . ' ' . $name . ' type');
			pg_assert_same($want[1], $controls[$name]['default_value'], $form . ' ' . $name . ' default');
			if (_test_forum_tag($n) === 'select') {
				$selected = 0;
				foreach (_test_forum_options($n) as $o) {
					pg_assert_true($o[0] !== '', $form . ' ' . $name . ' has no option whose value is empty');
					$selected += $o[1] ? 1 : 0;
				}
				pg_assert_same(1, $selected, $form . ' ' . $name . ' has one selected option');
			}
			pg_assert_false(isset(_test_forum_attrs($n)['aria-describedby']), $form . ' ' . $name . ' has no hint');
		}

		// Each office control sits alone in a box with its label.
		$walk = function ($node) use (&$walk, $fields, $form) {
			if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) {
				return;
			}
			foreach ($node['children'] as $child) {
				$a = _test_forum_attrs($child);
				if (in_array(_test_forum_tag($child), array('input', 'select', 'textarea'), true) && isset($a['name'], $fields[$a['name']])) {
					pg_assert_same('div', _test_forum_tag($node), $form . ' ' . $a['name'] . ' sits in a box');
					pg_assert_same(2, count($node['children']), $form . ' ' . $a['name'] . ' is alone with its label');
					$label = $node['children'][0];
					pg_assert_same('label', _test_forum_tag($label), $form . ' ' . $a['name'] . ' label first');
					pg_assert_same($child['props']['id'], _test_forum_attrs($label)['for'], $form . ' ' . $a['name'] . ' label names it');
				}
				$walk($child);
			}
		};
		$walk($tree);

		// What a member sees: the office controls, their labels and their
		// answers are gone, and nothing of them stands between the member's
		// last field and the CAPTCHA.
		$words = array();
		$map = array();
		foreach ($fields as $name => $want) {
			$words[] = $controls[$name]['label'];
			foreach ($controls[$name]['options'] as $o) {
				$words[] = $o['value'];
				$words[] = $o['label'];
			}
			$map[$name] = array('id' => count($map) + 1, 'office_use_only' => true, 'type' => $want[0], 'default_value' => '', 'folder_default' => false);
		}
		$member = $tree;
		pg_cf_apply_field_bindings($member, $map, null, 0, false);
		$nodes = _test_forum_nodes($member);
		$last_field = -1;
		$last_own = array();
		$captcha = -1;
		$submit = -1;
		foreach ($nodes as $i => $n) {
			if (isset($n['props']['text']) && is_string($n['props']['text'])) {
				pg_assert_false(in_array($n['props']['text'], $words, true), $form . ': no office label or answer left: ' . $n['props']['text']);
			}
			if (in_array(_test_forum_tag($n), array('input', 'select', 'textarea'), true)) {
				$a = _test_forum_attrs($n);
				pg_assert_false(isset($a['name']) && isset($fields[$a['name']]), $form . ': no office control left');
				if ($captcha < 0) {
					$last_field = $i;
					// The field's own label and hint may follow it.
					$last_own = array_filter(array(isset($n['props']['id']) ? $n['props']['id'] : '', isset($a['aria-describedby']) ? $a['aria-describedby'] : ''));
				}
			}
			if (isset($n['props']['_bindings']['section']) && $n['props']['_bindings']['section'] === 'captcha') {
				$captcha = $i;
			}
			if (_test_forum_tag($n) === 'button' && (_test_forum_attrs($n)['type'] ?? '') === 'submit') {
				$submit = $i;
			}
		}
		pg_assert_true($last_field >= 0 && $captcha > $last_field, $form . ': the CAPTCHA follows the member\'s fields');
		pg_assert_true($submit > $captcha, $form . ': the send button comes after the CAPTCHA');
		for ($i = $last_field + 1; $i < $captcha; $i++) {
			$tag = _test_forum_tag($nodes[$i]);
			$a = _test_forum_attrs($nodes[$i]);
			if (($tag === 'label' && isset($a['for']) && in_array($a['for'], $last_own, true))
				|| (isset($nodes[$i]['props']['id']) && in_array($nodes[$i]['props']['id'], $last_own, true))) {
				continue;
			}
			pg_assert_false(in_array($tag, array('fieldset', 'legend', 'label'), true), $form . ': no ' . $tag . ' before the CAPTCHA');
			pg_assert_false(in_array('form-text', _test_forum_classes($nodes[$i]), true), $form . ': no help text before the CAPTCHA');
		}
		foreach ($author[$form] as $name) {
			pg_assert_true(is_array(_test_forum_control($member, $name)), $form . ' ' . $name . ' stays on the member\'s form');
		}
	}
}

// Every form a visitor or a member sends carries the CAPTCHA node: a
// designed form shows the CAPTCHA only where its tree has that node.
function test_forum_forms_carry_captcha()
{
	$tpl = _test_forum_tpl();
	$has = function ($tree) {
		foreach (_test_forum_nodes($tree) as $n) {
			if (isset($n['props']['_bindings']['section']) && $n['props']['_bindings']['section'] === 'captcha') {
				return true;
			}
		}
		return false;
	};
	foreach (array('topic_form', 'doc_form', 'report_form', 'contact_form') as $key) {
		pg_assert_true($has($tpl['widgets'][$key]['tree']), $key . ' has a CAPTCHA');
	}
	$forms = 0;
	foreach (_test_forum_open_trees() as $page_key => $tree) {
		foreach (_test_forum_placed($tree) as $key) {
			if ($tpl['widgets'][$key]['config']['regionType'] === 'custom_form') {
				$forms++;
				pg_assert_true($has($tpl['widgets'][$key]['tree']), $page_key . ' ' . $key . ' has a CAPTCHA');
				pg_assert_same('page', $tpl['widgets'][$key]['config']['form_source'], $key . ' is its page\'s form');
			}
		}
	}
	pg_assert_same(4, $forms, 'four forms on the open pages');
}

// -- 6. one vocabulary -------------------------------------------------------

// The boards, the sections, the tags and the statuses are the same words in
// the forms, the links, the lists' filters and the sample records.
function test_forum_one_vocabulary()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
	$boards = _test_forum_boards();
	$sections = _test_forum_sections();
	$all = json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

	// Every board link names a board, every section link a section, every
	// status link a topic status.
	foreach (array('board' => array('board', $boards), 'docs_section' => array('section', $sections), 'staff_topics' => array('status', _test_forum_topic_statuses())) as $page => $x) {
		preg_match_all('/\{\{page:' . $page . '\}\}\?' . $x[0] . '=([^"#&]+)/', $all, $m);
		pg_assert_true(count($m[1]) >= 4, $page . ' links found');
		foreach ($m[1] as $encoded) {
			pg_assert_true(in_array(rawurldecode($encoded), $x[1], true), $page . ' link names one: ' . rawurldecode($encoded));
			pg_assert_same(rawurlencode(rawurldecode($encoded)), $encoded, $page . ' link is encoded: ' . $encoded);
		}
	}
	// Home, the board page and the forum's side column link every board;
	// the docs, a section and a guide every section.
	foreach (array('home' => 'board', 'board' => 'board', 'forum' => 'board', 'docs' => 'docs_section', 'docs_section' => 'docs_section', 'doc' => 'docs_section') as $page_key => $target) {
		$tree = json_encode(_test_forum_resolve($pages[$page_key]['tree']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$param = ($target === 'board') ? 'board' : 'section';
		foreach (($target === 'board') ? $boards : $sections as $name) {
			pg_assert_contains('"{{page:' . $target . '}}?' . $param . '=' . rawurlencode($name) . '"', $tree, $page_key . ' links ' . $name);
		}
	}
	// Each board's and each section's count is its own.
	foreach ($boards as $key => $name) {
		pg_assert_true(_test_forum_has_row($tpl['widgets']['board_count_' . $key]['config'], array('field' => 'board', 'operator' => 'is equal to', 'value' => $name, 'dynamic_value' => '')), 'board_count_' . $key . ' counts its board');
	}
	foreach ($sections as $key => $name) {
		pg_assert_true(_test_forum_has_row($tpl['widgets']['doc_count_' . $key]['config'], array('field' => 'section', 'operator' => 'is equal to', 'value' => $name, 'dynamic_value' => '')), 'doc_count_' . $key . ' counts its section');
	}

	// The tags to pick from are words the sample topics carry: in English
	// each is one of a line's words as written there, and in Turkish it is
	// found in a line the way the tag page's "contains" finds it (the
	// Turkish of "tasks", görev, finds görevler).
	$tr = _test_forum_tr();
	$lines = array();
	foreach (_test_forum_samples('new_topic') as $r) {
		$lines[] = $r['tags'];
	}
	preg_match_all('/\{\{page:tag\}\}\?tag=([^"#&]+)/', $all, $m);
	$tags = array_values(array_unique(array_map('rawurldecode', $m[1])));
	pg_assert_true(count($tags) >= 8, 'tag links found');
	foreach ($tags as $tag) {
		foreach (array('en' => null, 'tr' => $tr) as $lang => $map) {
			$word = ($map === null) ? $tag : (isset($map[$tag]) ? $map[$tag] : '');
			$found = false;
			foreach ($lines as $line) {
				$line = ($map === null) ? $line : (isset($map[$line]) ? $map[$line] : '');
				$words = array_map('trim', explode(',', $line));
				$found = $found || (($map === null) ? in_array($word, $words, true) : mb_stripos($line, $word, 0, 'UTF-8') !== false);
			}
			pg_assert_true($word !== '' && $found, 'the tag ' . $tag . ' is a word of a sample topic (' . $lang . ')');
		}
	}

	// The masthead links the forum, the docs, the guidelines and the search
	// in its bar and its drawer; every menu link names a page.
	$masthead = json_encode($tpl['shared']['masthead']['tree']);
	foreach (array('forum', 'docs', 'guidelines', 'search') as $key) {
		pg_assert_true(substr_count($masthead, '"{{page:' . $key . '}}"') >= 2, 'the masthead links ' . $key . ' in the bar and the drawer');
	}
	preg_match_all('/\{\{page:([a-z0-9_]+)\}\}/', $masthead . json_encode($tpl['shared']['footer']['tree']), $m);
	foreach (array_unique($m[1]) as $key) {
		pg_assert_true(isset($pages[$key]), 'a menu link names a page: ' . $key);
	}
}

// -- 7. sample records -------------------------------------------------------

// Eighteen topics across every board and both kinds: five solved, one
// closed, none hidden, two pinned, two announcements. No name, no date and
// no brand: the author is the account that sends a topic, and the seeder
// dates the samples when the template is opened.
function test_forum_sample_topics()
{
	$samples = _test_forum_samples('new_topic');
	$controls = _test_forum_controls('topic_form');
	$tr = _test_forum_tr();
	pg_assert_same(18, count($samples), 'eighteen topics');
	pg_assert_true(count($samples) <= 24, 'within the seeder\'s cap');

	$count = array('board' => array(), 'kind' => array(), 'status' => array(), 'pinned' => array(), 'announcement' => array());
	$titles = array();
	foreach ($samples as $i => $r) {
		foreach ($r as $field => $value) {
			pg_assert_true(isset($controls[$field]), 'topic ' . $i . ' ' . $field . ' is a field of the form');
			pg_assert_true(empty($controls[$field]['options']) || $controls[$field]['type'] === 'check box'
				|| in_array($value, array_column($controls[$field]['options'], 'value'), true), 'topic ' . $i . ' ' . $field . ' is an answer: ' . $value);
		}
		foreach ($controls as $name => $c) {
			if ($c['required']) {
				pg_assert_true(isset($r[$name]) && $r[$name] !== '', 'topic ' . $i . ' fills ' . $name);
			}
		}
		pg_assert_same('yes', $r['guidelines'], 'topic ' . $i . ' agreed to the guidelines');
		pg_assert_true(!isset($r['staff_note']) || $r['staff_note'] === '', 'topic ' . $i . ' has no staff note');
		foreach (array_keys($count) as $field) {
			$count[$field][$r[$field]] = isset($count[$field][$r[$field]]) ? $count[$field][$r[$field]] + 1 : 1;
		}
		pg_assert_false(isset($titles[$r['title']]), 'topic ' . $i . ' has a title of its own');
		$titles[$r['title']] = true;
		pg_assert_true(mb_strlen($r['title'], 'UTF-8') <= 120, 'topic ' . $i . ' title fits');
		pg_assert_true(isset($tr[$r['title']], $tr[$r['tags']]), 'topic ' . $i . ' title and tags have Turkish values');
		pg_assert_same(0, strpos($r['body'], '<p>'), 'topic ' . $i . ' starts with a paragraph');
		pg_assert_same(0, preg_match('~<(?!/?(p|h2|ul|ol|li|pre|code)>)~', $r['body']), 'topic ' . $i . ' has no other markup (escaped words)');
		foreach (_test_forum_rich_parts($r['body']) as $part) {
			pg_assert_true(isset($tr[$part]), 'topic ' . $i . ' text has a Turkish value: ' . $part);
		}
	}
	pg_assert_same(array_values(_test_forum_boards()), array_values(array_intersect(array_values(_test_forum_boards()), array_keys($count['board']))), 'every board has a topic');
	foreach (_test_forum_kinds() as $kind) {
		pg_assert_true(isset($count['kind'][$kind]), 'a ' . $kind . ' topic');
	}
	$statuses = _test_forum_topic_statuses();
	pg_assert_same(12, $count['status'][$statuses[0]], 'twelve open');
	pg_assert_same(5, $count['status'][$statuses[1]], 'five solved');
	pg_assert_same(1, $count['status'][$statuses[2]], 'one closed');
	pg_assert_false(isset($count['status'][$statuses[3]]), 'none hidden');
	pg_assert_same(2, $count['pinned'][lang('Yes')], 'two pinned');
	pg_assert_same(2, $count['announcement'][lang('Yes')], 'two announcements');
}

// Twelve published guides across every section, two featured, each with a
// two-digit order of its own in its section.
function test_forum_sample_guides()
{
	$samples = _test_forum_samples('doc_new');
	$controls = _test_forum_controls('doc_form');
	$tr = _test_forum_tr();
	pg_assert_same(12, count($samples), 'twelve guides');

	$sections = array();
	$featured = 0;
	$orders = array();
	foreach ($samples as $i => $r) {
		foreach ($r as $field => $value) {
			pg_assert_true(isset($controls[$field]), 'guide ' . $i . ' ' . $field . ' is a field of the form');
			pg_assert_true(empty($controls[$field]['options']) || $controls[$field]['type'] === 'check box'
				|| in_array($value, array_column($controls[$field]['options'], 'value'), true), 'guide ' . $i . ' ' . $field . ' is an answer: ' . $value);
		}
		foreach ($controls as $name => $c) {
			if ($c['required']) {
				pg_assert_true(isset($r[$name]) && $r[$name] !== '', 'guide ' . $i . ' fills ' . $name);
			}
		}
		pg_assert_same(lang('Published'), $r['status'], 'guide ' . $i . ' is published');
		pg_assert_same('yes', $r['rights'], 'guide ' . $i . ' agreed to the rights');
		pg_assert_same(1, preg_match('/^\d{2}$/', $r['doc_order']), 'guide ' . $i . ' has a two-digit order');
		pg_assert_false(isset($orders[$r['section']][$r['doc_order']]), 'guide ' . $i . ' order is its own in its section');
		$orders[$r['section']][$r['doc_order']] = true;
		$sections[$r['section']] = true;
		$featured += ($r['featured'] === lang('Yes')) ? 1 : 0;
		pg_assert_true(in_array($r['byline'], array_merge(array(lang('The docs team')), array_map('lang', _test_forum_people())), true), 'guide ' . $i . ' is signed by the docs team or a member');
		foreach (array('title', 'summary', 'tags') as $field) {
			pg_assert_true(isset($tr[$r[$field]]), 'guide ' . $i . ' ' . $field . ' has a Turkish value');
		}
		pg_assert_same(0, strpos($r['body'], '<p>'), 'guide ' . $i . ' starts with a paragraph');
		pg_assert_same(0, preg_match('~<(?!/?(p|h2|ul|ol|li|pre|code)>)~', $r['body']), 'guide ' . $i . ' has no other markup (escaped words)');
		foreach (_test_forum_rich_parts($r['body']) as $part) {
			pg_assert_true(isset($tr[$part]), 'guide ' . $i . ' text has a Turkish value: ' . $part);
		}
	}
	pg_assert_same(count(_test_forum_sections()), count($sections), 'every section has a guide');
	pg_assert_same(2, $featured, 'two featured');
}

// No sample names a month, a year or a season, and no text of the template
// names a real brand; a person's name stays in the guides' byline.
function test_forum_samples_never_go_stale()
{
	$tr = _test_forum_tr();
	// Months as names ("may" is a verb), seasons and years.
	$dates = '/\b(January|February|March|April|May|June|July|August|September|October|November|December|(?i:spring|summer|autumn|winter)|(19|20)\d{2})\b/';
	$people = array();
	foreach (_test_forum_people() as $name) {
		foreach (array($name, isset($tr[$name]) ? $tr[$name] : $name) as $written) {
			foreach (preg_split('/\s+/u', $written) as $w) {
				$people[] = $w;
			}
		}
	}
	$checked = 0;
	foreach (array('new_topic', 'doc_new') as $page) {
		foreach (_test_forum_samples($page) as $i => $r) {
			foreach ($r as $field => $value) {
				$checked++;
				pg_assert_same(0, preg_match($dates, $value), $page . ' sample ' . $i . ' ' . $field . ' names no month, year or season');
				$turkish = isset($tr[$value]) ? $tr[$value] : '';
				pg_assert_same(0, preg_match('/\b(Ocak|Şubat|Mart|Nisan|Mayıs|Haziran|Temmuz|Ağustos|Eylül|Ekim|Kasım|Aralık|(19|20)\d{2})\b/u', $turkish), $page . ' sample ' . $i . ' ' . $field . ' names no month or year in Turkish');
				if ($field === 'byline') {
					continue;
				}
				foreach ($people as $w) {
					pg_assert_same(0, preg_match('/\b' . preg_quote($w, '/') . '\b/u', $value), $page . ' sample ' . $i . ' ' . $field . ' names ' . $w);
				}
			}
		}
	}
	pg_assert_true($checked > 200, 'sample values were checked');

	$brands = '/\b(Evernote|Notion|Obsidian|OneNote|Todoist|Trello|Slack|Asana|Jira|Google|Gmail|Microsoft|Outlook|Apple|iPhone|iPad|iCloud|Android|Windows|macOS|Dropbox|GitHub|Discord|Reddit|Zoom|Chrome|Firefox|Safari)\b/';
	foreach (_test_forum_lang_literals() as $literal) {
		pg_assert_same(0, preg_match($brands, $literal), 'no brand in ' . $literal);
		if (isset($tr[$literal])) {
			pg_assert_same(0, preg_match($brands, $tr[$literal]), 'no brand in the Turkish of ' . $literal);
		}
	}
}

// -- 8. pagination -------------------------------------------------------------

// Lists on one page share the page number: one list per page pages at most.
// A count reads no row and never pages; a list that does not page shows as
// many records as it holds.
function test_forum_one_paginated_list_per_page()
{
	$tpl = _test_forum_tpl();
	$counts = 0;
	foreach ($tpl['widgets'] as $key => $w) {
		if (_test_forum_is_count_list($w)) {
			$counts++;
			pg_assert_same(0, $w['config']['max_results'], $key . ': a count is never capped');
			$found = _test_forum_loop_parent($w['tree']);
			pg_assert_same('has_records', _test_forum_flag($found[0]), $key . ': a count of nothing prints no empty sentence');
			pg_assert_contains('"__result_count"', json_encode($w['tree']), $key . ' prints its number');
		}
	}
	// The front page's three, a board's and a section's six each, the
	// forum's, a board's, the docs' and the dashboard's six.
	pg_assert_same(24, $counts, 'twenty-four counts');
	foreach (_test_forum_pages() as $page_key => $p) {
		$paged = array();
		foreach (_test_forum_placed(_test_forum_resolve($p['tree'])) as $key) {
			$w = $tpl['widgets'][$key];
			$cfg = $w['config'];
			if (!isset($cfg['regionType']) || $cfg['regionType'] !== 'form_list_view' || _test_forum_is_count_list($w)) {
				continue;
			}
			$max = isset($cfg['max_results']) ? (int) $cfg['max_results'] : 0;
			$ipp = isset($cfg['items_per_page']) ? (int) $cfg['items_per_page'] : 10;
			if ($max === 0 || $max > $ipp) {
				$paged[] = $key;
			} else {
				pg_assert_same($ipp, $max, $page_key . ' ' . $key . ': a list that does not page shows all it holds');
			}
		}
		pg_assert_true(count($paged) <= 1, $page_key . ' pages one list at most: ' . implode(', ', $paged));
	}
	foreach (array('forum_topics' => 20, 'board_topics' => 20, 'tag_topics' => 20, 'member_topics' => 20, 'unanswered_topics' => 20,
		'docs_all' => 12, 'section_guides' => 20, 'my_topics_started' => 20, 'my_docs_list' => 20, 'staff_topics_list' => 20,
		'staff_docs_all' => 20, 'staff_reports_all' => 20) as $key => $ipp) {
		pg_assert_same(0, $tpl['widgets'][$key]['config']['max_results'], $key . ' pages through');
		pg_assert_same($ipp, $tpl['widgets'][$key]['config']['items_per_page'], $key . ' per page');
	}
}

// -- 9. the front page, the forum and the feeds ------------------------------

// A page's feed is its first list: the count of every live topic on the
// front page and the forum, a board's count on the board page, every
// published guide on the docs page. Then the front page's bands.
function test_forum_home_and_feeds()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
	$w = $tpl['widgets'];
	$first = function ($page_key) use ($tpl, $pages) {
		foreach (_test_forum_placed(_test_forum_resolve($pages[$page_key]['tree'])) as $key) {
			if (isset($tpl['widgets'][$key]['config']['regionType']) && $tpl['widgets'][$key]['config']['regionType'] === 'form_list_view') {
				return $key;
			}
		}
		return '';
	};
	pg_assert_same('home_count_topics', $first('home'), 'the front page\'s feed is every live topic');
	pg_assert_same('forum_count', $first('forum'), 'the forum\'s feed is every live topic');
	pg_assert_same('board_head', $first('board'), 'a board\'s feed is the board\'s topics');
	pg_assert_same('docs_count', $first('docs'), 'the docs\' feed is every published guide');
	foreach (array('home_count_topics', 'forum_count') as $key) {
		pg_assert_same(array(_test_forum_topic_live()), $w[$key]['config']['filters'], $key . ' counts every live topic');
	}
	pg_assert_same(array(_test_forum_doc_live()), $w['docs_count']['config']['filters'], 'docs_count counts every published guide');
	pg_assert_contains('"{{page:board}}?rss=true"', json_encode($tpl['shared']['footer']['tree'], JSON_UNESCAPED_SLASHES), 'the footer links the topics\' feed');

	// The numbers strip, the announcements, the latest activity, the
	// unanswered topics, the week's most viewed and the featured guides.
	$home = _test_forum_resolve($pages['home']['tree']);
	$placed = _test_forum_placed($home);
	foreach (array('home_count_topics', 'home_count_solved', 'home_count_guides', 'home_announcements', 'home_latest', 'home_unanswered', 'home_popular', 'home_guides') as $key) {
		pg_assert_true(in_array($key, $placed, true), 'home places ' . $key);
		pg_assert_same('home', $w[$key]['page'], $key . ' is on home');
	}
	pg_assert_true(_test_forum_has_row($w['home_count_solved']['config'], array('field' => 'status', 'operator' => 'is equal to', 'value' => lang('Solved'), 'dynamic_value' => '')), 'the solved count');
	pg_assert_true(_test_forum_has_row($w['home_announcements']['config'], array('field' => 'announcement', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), 'the announcements');
	pg_assert_true(_test_forum_has_row($w['home_guides']['config'], array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), 'the featured guides');
	foreach (array('home_announcements' => 3, 'home_latest' => 10, 'home_unanswered' => 5, 'home_popular' => 5, 'home_guides' => 3) as $key => $n) {
		pg_assert_same($n, $w[$key]['config']['max_results'], $key . ' max results');
	}
	pg_assert_same('newest_activity_date_and_time', $w['home_latest']['config']['order_by_field'], 'the latest activity first');
	$popular = $w['home_popular']['config'];
	pg_assert_same(array('recent_views', 7, '{{tab:topic}}'), array($popular['order_by_field'], $popular['order_recent_days'], $popular['detail_page_id']), 'the week\'s most viewed, counted on the topic page');
	pg_assert_true(_test_forum_has_row($popular, array('field' => 'number_of_views', 'operator' => 'is greater than', 'value' => '0', 'dynamic_value' => '')), 'a topic nobody opened is not popular');
	pg_assert_true(in_array('recent_views', pg_sw_form_order_counters(), true), 'recent_views is a counter order');

	// The bands of a list that may be empty leave the page with it.
	foreach (array('home_announcements', 'home_guides') as $key) {
		$band = null;
		foreach (_test_forum_nodes_in_context($home) as $item) {
			if (isset($item[0]['type']) && $item[0]['type'] === 'widget' && $item[0]['props']['_widget'] === $key) {
				$band = $item[1];
			}
		}
		pg_assert_true(is_array($band) && in_array('pf-vanish', $band, true), $key . ' sits in a band that leaves while it is empty');
	}
	pg_assert_contains('.pf-vanish:has(.pg-sw-empty){display:none}', _test_forum_own_css(), 'an empty band leaves');

	// The hero's search goes to the search page; Start a topic to its form.
	$form = null;
	foreach (_test_forum_nodes($home) as $n) {
		if (_test_forum_tag($n) === 'form' && (_test_forum_attrs($n)['action'] ?? '') === '{{page:search}}') {
			$form = $n;
			break;
		}
	}
	pg_assert_true(is_array($form), 'the hero searches the site');
	pg_assert_same(array('{{page:search}}', 'get', 'search'), array(_test_forum_attrs($form)['action'], _test_forum_attrs($form)['method'], _test_forum_attrs($form)['role']), 'the search form');
	pg_assert_true(is_array(_test_forum_control($form, 'query')), 'the search asks ?query=');
	pg_assert_contains('"href":"{{page:new_topic}}"', json_encode($pages['home']['tree'], JSON_UNESCAPED_SLASHES), 'the hero starts a topic');

	// The forum: pinned first, then every other live topic with a search.
	pg_assert_true(_test_forum_has_row($w['forum_pinned']['config'], array('field' => 'pinned', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), 'the pinned topics');
	pg_assert_true(_test_forum_has_row($w['forum_topics']['config'], array('field' => 'pinned', 'operator' => 'is not equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), 'every other topic');
	pg_assert_same(array('title', 'body', 'tags', 'board'), $w['forum_topics']['config']['search_fields'], 'the forum searches the title, the text, the tags and the board');
	pg_assert_same('newest_activity_date_and_time', $w['forum_topics']['config']['order_by_field'], 'the forum by newest activity');
	$lists = array();
	foreach (_test_forum_placed(_test_forum_resolve($pages['forum']['tree'])) as $key) {
		if ($w[$key]['config']['regionType'] === 'form_list_view') {
			$lists[] = $key;
		}
	}
	pg_assert_same(array('forum_count', 'forum_pinned', 'forum_topics'), $lists, 'the forum\'s lists, in order');

	// A pinned topic is marked: its rows sit on the tint under a pin.
	foreach (array('forum_pinned', 'board_pinned') as $key) {
		$json = json_encode($w[$key]['tree']);
		pg_assert_contains('pf-pinned pf-tint', $json, $key . ' rows sit on the tint');
		pg_assert_contains('bi-pin-angle-fill', $json, $key . ' shows the pin');
		pg_assert_true(_test_forum_has_row($w[$key]['config'], array('field' => 'pinned', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $key . ' lists the pinned topics');
	}
}

// -- 10. ids -------------------------------------------------------------------

// An id goes in props.id: the renderer drops one written as an attribute.
function test_forum_no_ids_in_attrs()
{
	$found = array();
	foreach (_test_forum_all_nodes() as $n) {
		if (isset(_test_forum_attrs($n)['id'])) {
			$found[] = _test_forum_attrs($n)['id'];
		}
	}
	pg_assert_same(array(), $found, 'no id in _attrs');
}

// -- 11. the archives ----------------------------------------------------------

// A board, a member, a tag and a section are read from the address; the
// name prints only in its own flagged block and the page's h1 stays static.
function test_forum_archives()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
	$want = array(
		'board_head' => array('board', 'board', 'is equal to', 'board'),
		'board_pinned' => array('board', 'board', 'is equal to', 'board'),
		'board_topics' => array('board', 'board', 'is equal to', 'board'),
		'member_topics' => array('submitter_name', 'member', 'is equal to', 'member'),
		'tag_topics' => array('tags', 'tag', 'contains', 'tag'),
		'section_guides' => array('section', 'section', 'is equal to', 'docs_section'),
		'staff_topics_list' => array('status', 'status', 'is equal to', 'staff_topics'),
	);
	foreach ($want as $key => $x) {
		$w = $tpl['widgets'][$key];
		pg_assert_same($x[3], $w['page'], $key . ' page');
		$row = null;
		foreach ($w['config']['filters'] as $f) {
			if (isset($f['dynamic_value']) && $f['dynamic_value'] === 'url parameter') {
				$row = $f;
			}
		}
		pg_assert_true(is_array($row), $key . ' reads the address');
		pg_assert_same(array($x[0], $x[2], $x[1]), array($row['field'], $row['operator'], $row['dynamic_value_attribute']), $key . ' field, operator and parameter');
		pg_assert_same($x[1], pg_sw_url_param_name($x[1]), $key . ' parameter name is accepted as it is');
	}
	pg_assert_same(lang('Board: {value}'), $tpl['widgets']['board_head']['config']['filter_title'], 'a board\'s page title');
	pg_assert_same(lang('Topics started by {value}'), $tpl['widgets']['member_topics']['config']['filter_title'], 'a member\'s page title');
	pg_assert_same(lang('Docs: {value}'), $tpl['widgets']['section_guides']['config']['filter_title'], 'a section\'s page title');
	pg_assert_false(isset($tpl['widgets']['tag_topics']['config']['filter_title']), 'a tag is never a title ("contains" prints no value)');

	// The name heads its list only while the address names it: one flagged
	// block holds __filter_value, and the empty states are flagged too.
	foreach (array('board_head' => array('has_filter_value', 'has_records', 'no_records'), 'member_topics' => array('has_filter_value', 'no_records', 'no_filter_value'),
		'section_guides' => array('has_filter_value', 'no_records')) as $key => $flags) {
		$found = array();
		$name_in = '';
		foreach (_test_forum_nodes_in_context($tpl['widgets'][$key]['tree']) as $item) {
			if (_test_forum_flag($item[0]) !== '') {
				$found[] = _test_forum_flag($item[0]);
			}
			if (isset($item[0]['props']['_bindings']['text']) && $item[0]['props']['_bindings']['text'] === '__filter_value') {
				$name_in = implode(',', $item[2]);
				pg_assert_same('h2', _test_forum_tag($item[0]), $key . ' names the value in an h2');
			}
		}
		sort($found);
		sort($flags);
		pg_assert_same($flags, $found, $key . ' flagged blocks');
		pg_assert_same('has_filter_value', $name_in, $key . ' prints the value only while the address names it');
		pg_assert_false(isset($tpl['widgets'][$key]['config']['empty_message']), $key . ' has no empty sentence: its flagged block says it');
	}

	// The archive pages' h1 is static.
	foreach (array('board', 'member', 'tag', 'docs_section') as $page_key) {
		$h1 = 0;
		foreach (_test_forum_nodes_in_context(_test_forum_resolve($pages[$page_key]['tree'])) as $item) {
			if (_test_forum_tag($item[0]) === 'h1') {
				$h1++;
				pg_assert_same(array(), $item[2], $page_key . ' h1 is not flagged');
				pg_assert_false(isset($item[0]['props']['_bindings']), $page_key . ' h1 is static');
			}
		}
		pg_assert_same(1, $h1, 'one h1 on ' . $page_key);
	}

	// Every address of the tag page lists topics the other lists list.
	foreach (array('search' => false, 'sitemap' => false, 'noindex' => true) as $setting => $value) {
		pg_assert_same($value, $pages['tag'][$setting], 'tag ' . $setting);
	}
	pg_assert_same(false, $pages['member']['sitemap'], 'member pages come from the lists, not the sitemap');
	pg_assert_contains(json_encode(lang('The name on a topic comes from the profile of the member who started it.')), json_encode($pages['member']['tree']), 'the member page says where names come from');

	// A section's guides in reading order.
	$cfg = $tpl['widgets']['section_guides']['config'];
	pg_assert_same(array('doc_order', 'ASC', 'title', 'ASC'), array($cfg['order_by_field'], $cfg['order_by_direction'], $cfg['order_by_2_field'], $cfg['order_by_2_direction']), 'a section by its order, then by title');
}

// A link that carries a record's value to another page names a page of the
// template, a parameter the page reads and a value the record has, and sits
// where a record is drawn.
function test_forum_record_value_links()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
	$standard = pg_sw_standard_fields();
	$reads = array('board' => array('board', 'board'), 'member' => array('member', 'member'), 'docs_section' => array('section', 'section'), 'report' => array('post', 'post'));
	$found = array();
	foreach ($tpl['widgets'] as $key => $w) {
		if (!is_array($w['tree'])) {
			continue;
		}
		$form = _test_forum_form_of($w['config']);
		foreach (_test_forum_nodes($w['tree']) as $n) {
			if (!isset($n['props']['_bindings']['href']) || $n['props']['_bindings']['href'] !== '__page_link') {
				continue;
			}
			pg_assert_true($form !== '', $key . ': a value link sits in a list or a record view');
			$spec = isset($n['props']['_bindParams']['href']) ? $n['props']['_bindParams']['href'] : array();
			pg_assert_same(1, preg_match('/^\{\{tab:([a-z0-9_]+)\}\}$/', isset($spec['page_id']) ? $spec['page_id'] : '', $m), $key . ': a value link names a template page');
			$target = $m[1];
			pg_assert_true(isset($pages[$target], $reads[$target]), $key . ': a value link opens a page that reads it: ' . $target);
			pg_assert_true(count($spec['params']) >= 1 && count($spec['params']) <= pg_sw_page_link_max(), $key . ': a value link sends a parameter');
			$controls = _test_forum_controls($form);
			foreach ($spec['params'] as $param) {
				pg_assert_same($reads[$target][0], $param['name'], $key . ': ' . $target . ' reads ?' . $reads[$target][0] . '=');
				pg_assert_same($param['name'], pg_sw_url_param_name($param['name']), $key . ' parameter name accepted');
				pg_assert_true(isset($controls[$param['token']]) || isset($standard[$param['token']]), $key . ': the value is the record\'s: ' . $param['token']);
			}
			$found[$target] = true;
		}
	}
	ksort($found);
	pg_assert_same(array('board' => true, 'docs_section' => true, 'member' => true, 'report' => true), $found, 'value links to a board, a member, a section and the report form');
	// The report form takes the address it is opened with as its Post.
	pg_assert_same('post', _test_forum_controls('report_form')['post']['url_param'], 'the post field reads ?post=');
	// A member's name links to their topics in every topic row.
	foreach (array('home_latest', 'forum_topics', 'board_topics', 'tag_topics', 'unanswered_topics', 'topic_view') as $key) {
		pg_assert_contains('"page_id":"{{tab:member}}"', json_encode($tpl['widgets'][$key]['tree']), $key . ' links the member\'s topics');
		pg_assert_contains('"page_id":"{{tab:board}}"', json_encode($tpl['widgets'][$key]['tree']), $key . ' links the board');
	}
}

// -- 12. unanswered --------------------------------------------------------

// No reply yet is "number of comments is less than 1": a topic nobody has
// opened has no counter row, and "is equal to 0" would miss it.
function test_forum_unanswered_lists()
{
	$tpl = _test_forum_tpl();
	$users = array();
	foreach ($tpl['widgets'] as $key => $w) {
		foreach (isset($w['config']['filters']) ? $w['config']['filters'] : array() as $f) {
			if ($f['field'] !== 'number_of_comments') {
				continue;
			}
			pg_assert_same(array('is less than', '1'), array($f['operator'], $f['value']), $key . ': no reply is "less than 1"');
			pg_assert_false($f['operator'] === 'is equal to', $key . ' never "is equal to 0"');
			$users[] = $key;
		}
	}
	sort($users);
	pg_assert_same(array('home_unanswered', 'staff_count_unanswered', 'staff_unanswered', 'unanswered_topics'), $users, 'the unanswered lists');
	$cfg = $tpl['widgets']['unanswered_topics']['config'];
	pg_assert_same(array('submitted_date_and_time', 'ASC'), array($cfg['order_by_field'], $cfg['order_by_direction']), 'the oldest unanswered first');
	pg_assert_same('unanswered', $tpl['widgets']['unanswered_topics']['page'], 'the unanswered page');
}

// -- 13. e-mail pages ----------------------------------------------------------

function test_forum_email_pages()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
	$keys = array_keys(pg_design_template_email_pages($tpl));
	sort($keys);
	$want = array('email_account_welcome', 'email_doc_message', 'email_doc_new', 'email_doc_received', 'email_message_received', 'email_new_message',
		'email_password_reset', 'email_reply', 'email_report_new', 'email_report_received', 'email_topic_new');
	pg_assert_same($want, $keys, 'the eleven e-mail pages');
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

	// The mail record views open only for the sender or a moderator, and
	// filter nothing (the server names the record).
	$mails = 0;
	foreach ($tpl['widgets'] as $key => $w) {
		if (strpos($w['page'], 'email_') === 0 && in_array($w['config']['regionType'], array('form_item_view', 'form_list_view'), true)) {
			$mails++;
			pg_assert_same('submitter_only', $w['config']['access_control'], $key . ' access');
			pg_assert_true(empty($w['config']['filters']), $key . ' filters');
		}
	}
	pg_assert_same(7, $mails, 'seven form e-mails');

	// Who gets which e-mail.
	$form = function ($key, $setting) use ($pages) {
		return isset($pages[$key]['form'][$setting]) ? $pages[$key]['form'][$setting] : null;
	};
	pg_assert_same('{{site_email}}', $form('new_topic', 'notify_email'), 'a new topic goes to the moderators');
	pg_assert_same('{{tab:email_topic_new}}', $form('new_topic', 'notify_page_id'), 'the new topic e-mail');
	pg_assert_same(null, $form('new_topic', 'confirm_page_id'), 'a topic\'s author gets no copy: it is live at once');
	pg_assert_same('{{tab:topic}}', $form('new_topic', 'confirmation_page_id'), 'a new topic opens on its own page');
	pg_assert_same('{{tab:email_doc_new}}', $form('doc_new', 'notify_page_id'), 'a new guide goes to the moderators');
	pg_assert_same('{{tab:email_doc_received}}', $form('doc_new', 'confirm_page_id'), 'its author gets a copy');
	pg_assert_same('{{tab:doc_submission}}', $form('doc_new', 'confirmation_page_id'), 'a guide\'s author lands on the desk');
	pg_assert_same('{{tab:email_report_new}}', $form('report', 'notify_page_id'), 'a report goes to the moderators');
	pg_assert_same('{{tab:email_report_received}}', $form('report', 'confirm_page_id'), 'the member who reported gets a copy');
	pg_assert_same('{{tab:email_new_message}}', $form('contact', 'notify_page_id'), 'a message goes to the moderators');
	pg_assert_same('{{tab:email_message_received}}', $form('contact', 'confirm_page_id'), 'its sender gets a copy');
	pg_assert_same('{{tab:conversation}}', $form('contact', 'confirmation_page_id'), 'a message opens its conversation');
	foreach (array('new_topic', 'doc_new', 'report', 'contact') as $key) {
		pg_assert_same('{{site_email}}', $form($key, 'notify_email'), $key . ' notifies the site address');
	}
}

// -- 14. private fields -------------------------------------------------------

// The moderators' own fields and the user name of an account never show on
// a page a visitor or a member opens, nor in an e-mail a member gets.
function test_forum_private_fields_stay_private()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
	$office = array('submitter', 'last_modifier', 'staff_note', 'pinned', 'announcement', 'featured', 'doc_order');
	$trees = array();
	foreach (_test_forum_open_trees() as $key => $tree) {
		// A report's words are the moderators' alone on an open page.
		$trees[$key] = array($tree, array_merge($office, array('post', 'details')));
	}
	// An e-mail a member gets may repeat what they sent, nothing more.
	foreach (array('email_report_received', 'email_doc_received', 'email_message_received', 'email_reply', 'email_doc_message') as $key) {
		$trees[$key] = array(_test_forum_resolve($pages[$key]['tree']), $office);
	}
	$checked = 0;
	foreach ($trees as $page_key => $pair) {
		list($tree, $private) = $pair;
		foreach (_test_forum_nodes($tree) as $n) {
			$bindings = isset($n['props']['_bindings']) && is_array($n['props']['_bindings']) ? $n['props']['_bindings'] : array();
			foreach (array('text', 'html', 'src', 'href', 'value') as $attr) {
				if (isset($bindings[$attr])) {
					$checked++;
					pg_assert_false(in_array($bindings[$attr], $private, true), $page_key . ' binds ' . $bindings[$attr]);
				}
			}
			foreach (isset($n['props']['_bindParams']) && is_array($n['props']['_bindParams']) ? $n['props']['_bindParams'] : array() as $spec) {
				foreach (isset($spec['params']) ? $spec['params'] : array() as $param) {
					pg_assert_false(in_array($param['token'], $private, true), $page_key . ' sends ' . $param['token'] . ' in an address');
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
	pg_assert_true($checked > 300, 'open bindings were checked');

	// The moderators read them where they work.
	pg_assert_contains('"staff_note"', json_encode($tpl['widgets']['staff_report_view']['tree']), 'the report shows the staff note');
	pg_assert_contains('"submitter"', json_encode($tpl['widgets']['email_topic_staff']['tree']), 'the moderators\' e-mail names the account');
	pg_assert_true(in_array('staff_note', $tpl['widgets']['staff_topics_list']['config']['search_fields'], true), 'every topic searches the staff note');
}

// -- 15. comments -------------------------------------------------------------

// Replies, feedback, messages and notes are comments of their record pages:
// each label a singular noun (the platform puts it in plural and into
// sentences), signed in, placed as a comments block in the page's own tree
// right under its record, and headed by the record view while it shows a
// record.
function test_forum_comments()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
	$want = array(
		'topic' => array(lang('Reply'), 'topic_view', lang('Replies')),
		'doc' => array(lang('Feedback'), 'doc_view', lang('Feedback')),
		'doc_submission' => array(lang('Message'), 'doc_submission_view', lang('Messages with the moderators')),
		'conversation' => array(lang('Message'), 'conversation', lang('Replies')),
		'staff_report' => array(lang('Note'), 'staff_report_view', lang('Notes')),
	);
	$tr = _test_forum_tr();
	$found = array();
	foreach ($pages as $key => $p) {
		if (!isset($p['comments'])) {
			continue;
		}
		$found[] = $key;
		$c = $p['comments'];
		pg_assert_true(isset($want[$key]), $key . ' takes comments');
		pg_assert_same($want[$key][0], $c['label'], $key . ' comment label');
		pg_assert_true(isset($tr[$c['label']]), $key . ' label has a Turkish value');
		pg_assert_same(true, $c['login'], $key . ' comments need sign-in');
		pg_assert_same(true, $c['allow_new'], $key . ' takes new comments');
		pg_assert_same(true, $c['show_date'], $key . ' dates them');

		// The comments block sits in the page's own tree, right after the
		// record view, never inside a widget.
		$order = array();
		foreach (_test_forum_nodes($p['tree']) as $n) {
			if (isset($n['type'], $n['props']['templateWidget']) && $n['type'] === 'shared_ref') {
				$order[] = $n['props']['templateWidget'];
			}
			if (isset($n['type'], $n['props']['regionType']) && $n['type'] === 'region' && $n['props']['regionType'] === 'comments_block') {
				$order[] = '#comments';
			}
		}
		$at = array_search('#comments', $order, true);
		pg_assert_true($at !== false, $key . ' places a comments block');
		pg_assert_same($want[$key][1], $at > 0 ? $order[$at - 1] : null, $key . ': the comments come right under the record');
		pg_assert_same(1, count(array_keys($order, '#comments', true)), $key . ': one comments block');

		// The heading over them leaves with a missing record.
		$headed = false;
		foreach (_test_forum_nodes_in_context($tpl['widgets'][$want[$key][1]]['tree']) as $item) {
			if (_test_forum_tag($item[0]) === 'h2' && isset($item[0]['props']['text']) && $item[0]['props']['text'] === $want[$key][2]) {
				$headed = in_array('record_found', $item[2], true);
			}
		}
		pg_assert_true($headed, $key . ' has the h2 ' . $want[$key][2] . ' with the record');
	}
	sort($found);
	pg_assert_same(array('conversation', 'doc', 'doc_submission', 'staff_report', 'topic'), $found, 'five pages take comments');

	// Replies: published at once, mailed to the topic's followers; the
	// moderators follow them on their dashboard, not by e-mail.
	$c = $pages['topic']['comments'];
	pg_assert_same(true, $c['auto_publish'], 'replies are published at once');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'replies mail the followers');
	pg_assert_true(trim($c['email_subject']) !== '', 'the reply e-mail has a subject');
	pg_assert_false(isset($c['notify_email']), 'replies do not mail the moderators');
	pg_assert_true(empty($c['rating']), 'replies take no rating');
	// Feedback: a rating, held for the moderators, who hear of it.
	$c = $pages['doc']['comments'];
	pg_assert_same(array(true, false, '{{site_email}}', '{{tab:email_reply}}'), array($c['rating'], $c['auto_publish'], $c['notify_email'], $c['email_page']), 'feedback is rated and held');
	// The desk: messages mailed to the author and the moderators.
	$c = $pages['doc_submission']['comments'];
	pg_assert_same(array(true, '{{site_email}}', '{{tab:email_doc_message}}'), array($c['auto_publish'], $c['notify_email'], $c['email_page']), 'the desk\'s messages');
	// A report's notes are the moderators' own: never mailed.
	$c = $pages['staff_report']['comments'];
	pg_assert_same(true, $c['auto_publish'], 'notes are published at once');
	pg_assert_false(isset($c['email_page']) || isset($c['notify_email']), 'notes are never mailed');
}

// The topic page shows the topic, its replies and, beside them, what is
// known about it: two record views of the same topic under the same rule,
// the first giving the page its title, with the replies between them.
function test_forum_topic_page()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
	$order = array();
	foreach (_test_forum_nodes($pages['topic']['tree']) as $n) {
		if (isset($n['type'], $n['props']['templateWidget']) && $n['type'] === 'shared_ref' && isset($tpl['widgets'][$n['props']['templateWidget']])
			&& $tpl['widgets'][$n['props']['templateWidget']]['config']['regionType'] === 'form_item_view') {
			$order[] = $n['props']['templateWidget'];
		}
		if (isset($n['props']['regionType']) && $n['props']['regionType'] === 'comments_block') {
			$order[] = '#comments';
		}
	}
	pg_assert_same(array('topic_view', '#comments', 'topic_aside'), $order, 'the topic, its replies, then the column about it');
	pg_assert_false(isset($tpl['widgets']['topic_aside']['config']['title_field']), 'only the first view names the page');

	// The column: the facts, a way to the board, Report a post with the
	// topic's address, and the guidelines; with no topic, ways to find one.
	$aside = $tpl['widgets']['topic_aside']['tree'];
	$json = json_encode($aside);
	foreach (array('"board"', '"status"', '"submitted_date_and_time"', '"newest_activity_date_and_time"', '"newest_comment_name"', '"number_of_comments"', '"number_of_views"') as $token) {
		pg_assert_contains($token, $json, 'the column binds ' . $token);
	}
	pg_assert_contains('"page_id":"{{tab:report}}","params":[{"name":"post","token":"form_item_view"}]', $json, 'Report a post opens with the topic\'s address');
	pg_assert_contains('"{{page:guidelines}}"', $json, 'the column links the guidelines');
	$flags = array();
	foreach (_test_forum_nodes($aside) as $n) {
		if (_test_forum_flag($n) !== '') {
			$flags[] = _test_forum_flag($n);
		}
	}
	pg_assert_same(array('record_not_found', 'record_found'), $flags, 'the column with a topic, and without one');

	// The topic: badges, the moderators' button, the author, the text, the
	// attachment and the tags.
	$json = json_encode($tpl['widgets']['topic_view']['tree']);
	foreach (array('"kind"', '"status"', '"title"', '"submitter_name"', '"body"', '"attachment"', '"tags"', '"__edit_url"', '"can_edit"') as $token) {
		pg_assert_contains($token, $json, 'the topic binds ' . $token);
	}
}

// -- 16. installer preview ----------------------------------------------------

function test_forum_preview_html()
{
	$tpl = pg_design_templates()['pinegrap-forum'];
	$preview = array_keys(pg_design_template_preview_pages($tpl));
	sort($preview);
	$want = array('about', 'accessibility', 'board', 'contact', 'docs', 'docs_section', 'faq', 'forum', 'guidelines', 'home', 'privacy',
		'search', 'terms', 'unanswered');
	pg_assert_same($want, $preview, 'the preview pages');

	foreach ($want as $key) {
		$html = pg_design_template_preview_html('pinegrap-forum', $key);
		pg_assert_true($html !== '', $key . ' renders');
		pg_assert_false(strpos(preg_replace('/href="[^"]*"/', '', $html), '^^') !== false, $key . ' has no ^^ outside an address');
	}
	$html = pg_design_template_preview_html('pinegrap-forum', 'home');
	pg_assert_contains('<style id="pg-user-styles">', $html, 'home prints User Styles');
	pg_assert_contains('fonts.googleapis.com/css2?family=Figtree', $html, 'home links Figtree');
	pg_assert_contains('family=JetBrains%20Mono', $html, 'home links JetBrains Mono');
	pg_assert_contains('pf-brand', $html, 'home has the brand');
	pg_assert_contains('pf-stat', $html, 'home has the numbers');
	pg_assert_contains('pf-board', $html, 'home has the boards');
	pg_assert_contains(h(lang('How can we help?')), $html, 'home has its h1');
	pg_assert_contains(h(lang('Forum')), pg_design_template_preview_html('pinegrap-forum', 'forum'), 'the forum has its h1');
	pg_assert_contains(h(lang('Boards')), pg_design_template_preview_html('pinegrap-forum', 'board'), 'the board page has its h1');

	foreach (array_keys(pg_design_template_email_pages($tpl)) as $key) {
		$html = pg_design_template_preview_html('pinegrap-forum', $key);
		pg_assert_true($html !== '', $key . ' renders');
		pg_assert_contains('pg-user-styles', $html, $key . ' prints User Styles');
		pg_assert_false(strpos(preg_replace('/href="[^"]*"/', '', $html), '^^') !== false, $key . ' has no ^^ outside an address');
	}
}

// -- 17. the template's own CSS ----------------------------------------------

function test_forum_css()
{
	$tpl = _test_forum_tpl();
	$own = _test_forum_own_css();
	pg_assert_true(strlen($own) > 8000, 'own CSS found');
	$plain = preg_replace('~/\*.*?\*/~s', '', $own);
	$nodes = _test_forum_all_nodes();

	// Every pf- class a tree uses has a rule, and every rule is used; no
	// class of another template; no block rule is shipped or used.
	$used = array();
	foreach ($nodes as $n) {
		foreach (_test_forum_classes($n) as $c) {
			if (strpos($c, 'pf-') === 0) {
				$used[$c] = true;
			}
			pg_assert_same(0, preg_match('/^(pn|pm)-/', $c), 'no class of another template: ' . $c);
		}
	}
	foreach (array('pf-topic', 'pf-topics', 'pf-badge', 'pf-card', 'pf-tint', 'pf-post', 'pf-vanish', 'pf-stat', 'pf-board', 'pf-fact', 'pf-sticky', 'pf-person') as $c) {
		pg_assert_true(isset($used[$c]), $c . ' is used');
	}
	foreach (array_keys($used) as $c) {
		pg_assert_same(1, preg_match('/\.' . preg_quote($c, '/') . '(?![\w-])/', $own), $c . ' has a rule');
	}
	preg_match_all('/\.(pf-[a-z0-9-]+)/', $plain, $m);
	foreach (array_unique($m[1]) as $c) {
		pg_assert_true(isset($used[$c]), 'the rule for ' . $c . ' is used');
	}
	pg_assert_same(0, preg_match('/\.(pn|pm)-/', $own), 'no class of another template in the CSS');
	$block_classes = array_keys(_pg_designer_block_css_source()['rules']);
	pg_assert_true(count($block_classes) > 10, 'block rules found');
	foreach ($nodes as $n) {
		foreach (_test_forum_classes($n) as $c) {
			pg_assert_false(in_array($c, $block_classes, true), 'no block rule is used: ' . $c);
		}
	}

	// Fonts: the two the font picker offers.
	$fonts = json_decode($tpl['assets']['fonts'], true);
	$families = array();
	foreach ($fonts as $font) {
		$families[] = $font['family'];
	}
	pg_assert_same(array('Figtree', 'JetBrains Mono'), $families, 'Figtree and JetBrains Mono');
	$picker = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	foreach ($families as $family) {
		pg_assert_contains("'" . $family . "'", $picker, $family . ' is in the font picker');
	}
	pg_assert_contains('code,kbd,pre,samp{font-family:var(--pf-mono)}', $own, 'code is set in the mono font');

	// Colours: tokens only, every palette colour with a fallback, a mix
	// only under @supports, after its plain fallback.
	pg_assert_same(0, preg_match('/var\(\s*--pg-[\w-]*-fg\s*\)/', $own), 'every --pg-*-fg has a fallback');
	$values = preg_replace('/@supports\s*\([^{]*\)\s*\{/', '@supports{', $plain);
	pg_assert_same(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $values), 'no hex colour');
	pg_assert_same(0, preg_match('/\b(rgba?|hsla?)\(/i', $values), 'no rgb() or hsl()');
	pg_assert_same(0, preg_match('/(?<![\w-])(red|blue|green|white|black|gray|grey|silver|maroon|purple|fuchsia|lime|olive|yellow|navy|teal|aqua|orange|pink|brown|gold|transparent)(?![\w-])/i', $values), 'no named colour');
	foreach (explode("\n", $plain) as $line) {
		if (strpos($line, 'color-mix(') !== false) {
			pg_assert_same(0, strpos($line, '@supports'), 'color-mix() only under @supports: ' . $line);
		}
	}
	$fallback = strpos($own, '.pf-tint{background-color:var(--bs-tertiary-bg)}');
	$mix = strpos($own, '{.pf-tint{background-color:color-mix(');
	pg_assert_true($fallback !== false && $mix !== false && $fallback < $mix, 'the tint falls back to the tertiary background first');
	pg_assert_true(strpos($own, '.pf-card{') < $fallback, 'the tint comes after the card, so a tinted card keeps it');
	pg_assert_contains('--pf-accent:var(--pg-primary-fg,var(--bs-link-color))', $own, 'the accent falls back');

	// Selectors: descendants and pseudo-classes only.
	pg_assert_false(strpos($own, '!important') !== false, 'no !important');
	pg_assert_false(strpos($own, ':first-child') !== false, 'no :first-child (the edit chip may come first)');
	pg_assert_false(strpos($own, ':last-child') !== false, 'no :last-child');
	$selectors = _test_forum_selectors($own);
	pg_assert_true(count($selectors) > 80, 'selectors found');
	foreach ($selectors as $selector) {
		pg_assert_same(0, preg_match('/[>+~]/', $selector), 'no combinator in ' . $selector);
	}

	// What a member writes stays in its box; the links raised over a row's
	// stretched title stay clickable.
	pg_assert_same(1, preg_match('/\.pf-post\{[^}]*position:relative[^}]*contain:paint[^}]*\}/', $plain), '.pf-post contains what a member writes');
	foreach (array('.pf-badge-board', '.pf-person') as $raised) {
		pg_assert_same(1, preg_match('/' . preg_quote($raised, '/') . '\{[^}]*position:relative;z-index:2/', $plain), $raised . ' is raised over the row\'s link');
	}
	pg_assert_contains('.pf-replies .alert a:not(.btn){color:var(--bs-alert-link-color)}', $own, 'notice links in the replies are readable');
	pg_assert_contains('.pf-topic.pg-edit-host,', $own, 'a row with the moderators\' pencil makes room for it');

	// Outlined buttons carry no words; the language switcher is filled; a
	// record's first element is no grid row (the edit chip goes into it).
	foreach ($nodes as $n) {
		$classes = _test_forum_classes($n);
		foreach ($classes as $c) {
			if (strpos($c, 'btn-outline-') === 0) {
				pg_assert_same('', _test_forum_visible_text($n), 'an outlined button has no words: ' . implode(' ', $classes));
			}
		}
		if (isset($n['type'], $n['props']['componentType']) && $n['type'] === 'component' && $n['props']['componentType'] === 'language_switcher') {
			pg_assert_false(!empty($n['props']['outline']), 'the language switcher is not an outlined button');
		}
	}
	foreach ($tpl['widgets'] as $key => $w) {
		if (!is_array($w['tree']) || !isset($w['config']['regionType']) || $w['config']['regionType'] !== 'form_list_view') {
			continue;
		}
		$found = _test_forum_loop_parent($w['tree']);
		foreach ($found !== null ? $found[1]['children'] : array() as $item) {
			pg_assert_false(in_array('row', _test_forum_classes($item), true) || (isset($item['type']) && $item['type'] === 'row'),
				$key . ': a record\'s first element is not a row');
		}
	}
	// A list prints its page links after its rows: only the site search,
	// whose renderer fills it, has a slot for them.
	$slots = array();
	foreach (_test_forum_all_trees() as $name => $tree) {
		foreach (_test_forum_nodes($tree) as $n) {
			if (isset($n['props']['_bindings']['section']) && $n['props']['_bindings']['section'] === 'pagination') {
				$slots[] = $name;
			}
		}
	}
	pg_assert_same(array('widget site_search'), $slots, 'only the site search has a page links slot');
}

// Focus is an ink outline on every button, field, check box, page link and
// close button, on the main links and the underlined navs, and on the
// masthead's bare buttons: Bootstrap's half-clear shadow all but vanishes
// on the tint and in the dark theme.
function test_forum_focus_rings()
{
	$tpl = _test_forum_tpl();
	$own = _test_forum_own_css();
	pg_assert_same(1, preg_match('/(^|\n)body \.btn:focus-visible\{outline:2px solid var\(--bs-emphasis-color\);[^}]*box-shadow:none\}/', $own), 'every button shows its focus');
	pg_assert_same(1, preg_match('/(^|\n)body \.form-control:focus,body \.form-select:focus\{outline:2px solid var\(--bs-emphasis-color\)[^}]*\}/', $own), 'every field shows its focus');
	pg_assert_same(1, preg_match('/(^|\n)body \.form-check-input:focus-visible\{outline:2px solid var\(--bs-emphasis-color\)[^}]*\}/', $own), 'every check box shows its focus');
	pg_assert_same(1, preg_match('/(^|\n)body \.page-link:focus-visible,body \.btn-close:focus-visible\{outline:2px solid var\(--bs-emphasis-color\);[^}]*box-shadow:none\}/', $own), 'page links and the close button show their focus');
	pg_assert_true(_test_forum_specificity('body .btn:focus-visible') > _test_forum_specificity('.btn:focus-visible'), 'the ring outranks the theme rule');
	pg_assert_same(1, preg_match('/\.pf-navlink:focus-visible\{[^}]*outline:2px solid[^}]*\}/', $own), 'the main links show their focus');
	pg_assert_same(1, preg_match('/\.nav-underline \.nav-link:focus-visible\{[^}]*outline:2px solid[^}]*\}/', $own), 'the underlined navs show their focus');
	pg_assert_contains('.pf-tag:focus-visible{outline:2px solid', $own, 'a tag shows its focus');

	// Every nav link is a main link or sits in an underlined nav.
	$links = 0;
	foreach (_test_forum_all_trees() as $where => $tree) {
		foreach (_test_forum_nodes_in_context($tree) as $item) {
			$classes = _test_forum_classes($item[0]);
			if (!in_array('nav-link', $classes, true)) {
				continue;
			}
			$links++;
			pg_assert_true(in_array('pf-navlink', $classes, true) || in_array('nav-underline', $item[1], true), $where . ': a nav link has a focus ring of its own');
		}
	}
	pg_assert_true($links >= 10, 'nav links checked');

	// A button without a colour variant in a shared part sits in a part
	// whose own CSS draws its ring.
	$found = 0;
	foreach ($tpl['shared'] as $key => $s) {
		$root = array();
		foreach (_test_forum_classes($s['tree']) as $c) {
			if (strpos($c, 'pf-') === 0) {
				$root[] = $c;
			}
		}
		foreach (_test_forum_nodes($s['tree']) as $n) {
			$classes = _test_forum_classes($n);
			if (!in_array('btn', $classes, true) || preg_grep('/^btn-(primary|secondary|success|danger|warning|info|light|dark|link|outline-[a-z]+)$/', $classes)) {
				continue;
			}
			$found++;
			$covered = false;
			foreach ($root as $c) {
				$covered = $covered || strpos($own, '.' . $c . ' .btn:focus-visible') !== false;
			}
			pg_assert_true($covered, $key . ': a button without a variant has a focus ring (' . implode(' ', $classes) . ')');
		}
	}
	pg_assert_true($found >= 1, 'the masthead\'s menu button is checked');
}

// -- 18. e-mail colours and frames -------------------------------------------

// Every var() in an e-mail's inline style carries its own fallback, no
// e-mail sets capitals by CSS, and every frame stays light in the dark
// theme.
function test_forum_email_var_fallbacks()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
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
		foreach (_test_forum_nodes($tree) as $n) {
			$a = _test_forum_attrs($n);
			if (isset($a['style'])) {
				$styles++;
				pg_assert_same(0, preg_match('/var\(\s*--[\w-]+\s*\)/', $a['style']), 'var() fallback in ' . $a['style']);
				// A mail document is lang="en": CSS capitals would print a
				// Turkish i as a dotless I.
				pg_assert_same(0, preg_match('/text-transform\s*:\s*uppercase/', $a['style']), 'no CSS capitals in ' . $a['style']);
			}
			foreach (array('text-uppercase', 'pf-kicker', 'pf-label') as $c) {
				pg_assert_false(in_array($c, _test_forum_classes($n), true), 'no ' . $c . ' in an e-mail');
			}
		}
	}
	pg_assert_true($styles > 60, 'e-mail styles found');

	$frames = 0;
	foreach (array_keys(pg_design_template_email_pages($tpl)) as $key) {
		$frame = isset($pages[$key]['tree']['children'][0]) ? $pages[$key]['tree']['children'][0] : null;
		pg_assert_same(lang('E-mail Frame'), isset($frame['props']['_label']) ? $frame['props']['_label'] : null, $key . ' has the e-mail frame');
		pg_assert_same('light', _test_forum_attrs($frame)['data-bs-theme'] ?? null, $key . ': the frame is light');
		$frames++;
	}
	pg_assert_same(11, $frames, 'every e-mail page is checked');

	// A report's post reaches the moderators as text: it is what a member typed.
	foreach (_test_forum_all_trees() as $name => $tree) {
		foreach (_test_forum_nodes($tree) as $n) {
			if (isset($n['props']['_bindings']['href'])) {
				pg_assert_false(in_array($n['props']['_bindings']['href'], array('post', 'details', 'message', 'tags', 'version'), true), $name . ' links a typed value');
			}
			foreach (_test_forum_hrefs($n) as $href) {
				pg_assert_false(strpos($href, '^^post^^') !== false, $name . ' links ^^post^^');
			}
		}
	}
}

// -- 19. landmarks, headings and pictures ------------------------------------

// Landmarks are named and never nested; every page has one h1 where no
// starter widget draws its own layout, with no heading level skipped and no
// h2 said twice; nothing before the h1 is a heading; every name, label,
// description and jump link points at an element the page has; a list
// picture has an empty alt.
function test_forum_landmarks_and_headings()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();

	foreach ($pages as $key => $p) {
		$resolved = _test_forum_resolve($p['tree']);
		$items = _test_forum_nodes_in_context($resolved);
		$starter = false;
		$h1 = 0;
		$last = 0;
		$h2 = array();
		$ids = array();
		foreach ($items as $item) {
			$n = $item[0];
			if (isset($n['type']) && $n['type'] === 'widget' && !empty($n['props']['starter'])) {
				$starter = true;
			}
			if (!empty($n['props']['id'])) {
				$ids[$n['props']['id']] = true;
			}
			$tag = _test_forum_tag($n);
			// A complementary landmark is named, and never inside another.
			if ($tag === 'aside') {
				$a = _test_forum_attrs($n);
				pg_assert_true(isset($a['aria-label']) || isset($a['aria-labelledby']), $key . ': an aside is named');
				pg_assert_false(in_array('aside', $item[3], true), $key . ': no aside inside an aside');
			}
			if ($tag === 'nav') {
				$a = _test_forum_attrs($n);
				pg_assert_true(isset($a['aria-label']) || isset($a['aria-labelledby']), $key . ': a nav is named');
			}
			if (!preg_match('/^h([1-6])$/', $tag, $m) || array_intersect(array('record_not_found', 'no_records', 'no_filter_value'), $item[2])) {
				continue;
			}
			$level = (int) $m[1];
			$h1 += ($level === 1) ? 1 : 0;
			if (!$starter && strpos($key, 'email_') !== 0) {
				pg_assert_true($last > 0 || $level === 1, $key . ': the first heading is the h1, not h' . $level . ' ' . _test_forum_visible_text($n));
			}
			pg_assert_true($last === 0 || $level <= $last + 1, $key . ': no heading level skipped before ' . _test_forum_visible_text($n));
			$last = $level;
			if ($level === 2) {
				$text = _test_forum_visible_text($n);
				pg_assert_false($text !== '' && isset($h2[$text]), $key . ': the heading "' . $text . '" is used once');
				$h2[$text] = true;
			}
		}
		if ($starter) {
			pg_assert_true($h1 <= 1, $key . ' has at most one h1');
		} else {
			pg_assert_same(1, $h1, $key . ' has one h1');
		}

		// Every reference names an element of the page.
		foreach (_test_forum_nodes($resolved) as $n) {
			$a = _test_forum_attrs($n);
			foreach (array('aria-labelledby', 'aria-describedby', 'aria-controls', 'for') as $ref) {
				if (isset($a[$ref])) {
					pg_assert_true(isset($ids[$a[$ref]]), $key . ': ' . $ref . ' ' . $a[$ref] . ' is on the page');
				}
			}
			foreach (_test_forum_hrefs($n) as $href) {
				if (strlen($href) > 1 && $href[0] === '#') {
					pg_assert_true(isset($ids[substr($href, 1)]), $key . ': the jump link ' . $href . ' has its target');
				}
			}
			if ((isset($n['props']['contentType']) && $n['props']['contentType'] === 'image') || _test_forum_tag($n) === 'img') {
				pg_assert_true(array_key_exists('alt', $n['props']), $key . ': a picture has an alt');
			}
		}
		// The skip link lands on the main content.
		if (strpos($key, 'email_') !== 0) {
			pg_assert_true(isset($ids['main']), $key . ' has #main');
		}
	}

	// Nothing before the page's h1 is a heading: the masthead and the bar
	// come first in the source. The bar links distinct pages.
	foreach (array('masthead', 'staff_bar') as $key) {
		foreach (_test_forum_nodes(_test_forum_resolve($tpl['shared'][$key]['tree'])) as $n) {
			pg_assert_same(0, preg_match('/^h[1-6]$/', _test_forum_tag($n)), $key . ' holds no heading');
		}
	}
	pg_assert_same(0, preg_match('/"href":"[^"]*#/', json_encode($tpl['shared']['staff_bar']['tree'])), 'the staff bar has no # link');
	pg_assert_contains('"href":"#main"', json_encode($tpl['shared']['masthead']['tree']), 'the masthead skips to the main content');

	// A list picture has an empty alt: the name beside it says who it is.
	$pictures = 0;
	foreach ($tpl['widgets'] as $w) {
		if ($w['config']['regionType'] !== 'form_list_view' || !is_array($w['tree'])) {
			continue;
		}
		foreach (_test_forum_nodes_in_context($w['tree']) as $item) {
			$n = $item[0];
			if ((isset($n['props']['contentType']) && $n['props']['contentType'] === 'image') || _test_forum_tag($n) === 'img') {
				$pictures++;
				pg_assert_same('', isset($n['props']['alt']) ? $n['props']['alt'] : null, 'a list picture has an empty alt');
				pg_assert_false(isset($n['props']['_bindings']['alt']), 'a list picture binds no alt');
			}
		}
	}
	pg_assert_true($pictures >= 1, 'list pictures found');
}

// The sign-in, sign-up and reset forms sit in a band, the same distance
// under the masthead as the other pages.
function test_forum_sign_forms_sit_in_a_band()
{
	$found = 0;
	foreach (_test_forum_pages() as $key => $p) {
		foreach (_test_forum_nodes_in_context($p['tree']) as $item) {
			$n = $item[0];
			if (isset($n['type'], $n['props']['templateWidget']) && $n['type'] === 'shared_ref'
				&& in_array($n['props']['templateWidget'], array('login', 'register', 'forgot_password', 'set_password'), true)) {
				$found++;
				pg_assert_true(in_array('pf-band', $item[1], true), $key . ': the form sits in a band');
			}
		}
	}
	pg_assert_same(4, $found, 'the sign-in, sign-up, reset and set password forms are checked');
}

// -- 20. search sections ------------------------------------------------------

// Every part of a page the site search points at is marked once, on its own
// page, and a page the search leaves out marks none.
function test_forum_search_sections()
{
	$pages = _test_forum_pages();
	$source = (string) file_get_contents(_test_forum_file());
	preg_match_all("/'#([a-z0-9-]+)'\\s*=>\\s*array\\('\\1'/", $source, $m);
	pg_assert_true(count($m[1]) >= 15, 'search sections found');
	$marked = array();
	foreach ($pages as $key => $p) {
		foreach (_test_forum_nodes($p['tree']) as $n) {
			if (!empty($n['props']['searchSection'])) {
				pg_assert_true(!empty($n['props']['id']), $key . ': a search section has an anchor');
				pg_assert_false(isset($marked[$n['props']['id']]), $key . ': the search section ' . $n['props']['id'] . ' is marked once');
				$marked[$n['props']['id']] = $key;
				pg_assert_false(isset($p['search']) && $p['search'] === false, $key . ' is left out of the search, yet marks a part');
			}
		}
	}
	foreach ($m[1] as $id) {
		pg_assert_true(isset($marked[$id]), 'the search section #' . $id . ' is placed');
	}
	pg_assert_same('contact', isset($marked['contact-details']) ? $marked['contact-details'] : null, 'the contact details are found by their layer name');
	for ($n = 1; $n <= 12; $n++) {
		pg_assert_same('faq', isset($marked['pf-faq-' . $n . '-item']) ? $marked['pf-faq-' . $n . '-item'] : null, 'question ' . $n . ' of the FAQ is a search section');
	}
	pg_assert_same('home', $marked['boards'], 'the boards of the front page');
	pg_assert_same('board', $marked['all-boards'], 'the boards of the board page');
}

// -- 21. the moderators -------------------------------------------------------

// The dashboard counts what is waiting and lists it; its note says which
// folders a moderator needs and names the control panel's own words, in
// English and in Turkish; a hidden topic's row opens no page.
function test_forum_moderation()
{
	$tpl = _test_forum_tpl();
	$pages = _test_forum_pages();
	$tr = _test_forum_tr();
	$staff = _test_forum_resolve($pages['staff']['tree']);
	$placed = _test_forum_placed($staff);
	foreach (array('staff_count_open', 'staff_count_unanswered', 'staff_count_solved', 'staff_count_hidden', 'staff_count_reports', 'staff_count_guides',
		'staff_reports_waiting', 'staff_guides_waiting', 'staff_new_topics', 'staff_unanswered', 'staff_popular', 'staff_solved', 'staff_hidden') as $key) {
		pg_assert_true(in_array($key, $placed, true), 'the dashboard places ' . $key);
	}
	$w = $tpl['widgets'];
	pg_assert_same(array(array('field' => 'submitted_date_and_time', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'days ago', 'dynamic_value_attribute' => 7)),
		$w['staff_new_topics']['config']['filters'], 'new topics: the last seven days, in any status');
	pg_assert_same(array('recent_views', 7), array($w['staff_popular']['config']['order_by_field'], $w['staff_popular']['config']['order_recent_days']), 'most viewed this week');
	pg_assert_true(_test_forum_has_row($w['staff_hidden']['config'], array('field' => 'status', 'operator' => 'is equal to', 'value' => lang('Hidden'), 'dynamic_value' => '')), 'the hidden topics');
	pg_assert_true(_test_forum_has_row($w['staff_reports_waiting']['config'], array('field' => 'status', 'operator' => 'is equal to', 'value' => lang('Open'), 'dynamic_value' => '')), 'the open reports');
	pg_assert_true(_test_forum_has_row($w['staff_guides_waiting']['config'], array('field' => 'status', 'operator' => 'is equal to', 'value' => lang('Awaiting review'), 'dynamic_value' => '')), 'the guides awaiting review');
	foreach (_test_forum_nodes($w['staff_hidden']['tree']) as $n) {
		pg_assert_false(isset($n['props']['_bindings']['href']) && $n['props']['_bindings']['href'] === 'form_item_view', 'a hidden topic\'s row opens no page that would not show it');
	}
	foreach (array('staff_docs_waiting', 'staff_docs_in_review', 'staff_docs_changes', 'staff_docs_published', 'staff_docs_all') as $key) {
		pg_assert_same('staff_docs', $w[$key]['page'], $key . ' is on the docs desk');
	}
	foreach (array('staff_reports_open', 'staff_reports_resolved', 'staff_reports_dismissed', 'staff_reports_all') as $key) {
		pg_assert_same('staff_reports', $w[$key]['page'], $key . ' is on the reported posts');
		pg_assert_same('{{tab:staff_report}}', $w[$key]['config']['detail_page_id'], $key . ' opens the report\'s page');
	}

	// The shortcuts: the panel's Comments screen and its submitted forms.
	$json = json_encode($pages['staff']['tree'], JSON_UNESCAPED_SLASHES);
	foreach (array('/view_comments.php', '/view_submitted_forms.php', '/welcome.php') as $screen) {
		pg_assert_contains($screen . '"', $json, 'the dashboard opens ' . $screen);
	}

	// How moderation works.
	$note = '';
	foreach (_test_forum_nodes($pages['staff']['tree']) as $n) {
		if (_test_forum_tag($n) === 'li' && isset($n['props']['_label']) && $n['props']['_label'] === lang('Note') && isset($n['props']['text'])) {
			$note .= ' ' . $n['props']['text'];
		}
	}
	pg_assert_true(strlen($note) > 2000, 'the note is found');
	foreach (array('private', 'registration', 'public', 'Content and Forms', 'Reach the data submitted through forms', 'Users',
		'Hidden', 'Solved', 'Pinned', 'Announcement', 'Comments', 'doc_order', 'two digits', 'Update Search Index', 'user name', 'profile',
		'Hide from the visitor (staff only)') as $words) {
		pg_assert_contains($words, $note, 'the note says ' . $words);
	}
	// Every field the note says never to rename is a field of a form.
	pg_assert_same(1, preg_match('/fields named ([a-z_, ]+) or ([a-z_]+), nor/', $note, $m), 'the note names the fields');
	$named = array_merge(array_map('trim', explode(',', $m[1])), array($m[2]));
	$fields = array_merge(_test_forum_controls('topic_form'), _test_forum_controls('doc_form'), _test_forum_controls('report_form'));
	foreach ($named as $field) {
		pg_assert_true(isset($fields[$field]), 'the note names a field of a form: ' . $field);
	}
	// In Turkish, the note names the panel in the panel's own words.
	$turkish = '';
	foreach (_test_forum_nodes($pages['staff']['tree']) as $n) {
		if (_test_forum_tag($n) === 'li' && isset($n['props']['_label']) && $n['props']['_label'] === lang('Note') && isset($tr[$n['props']['text']])) {
			$turkish .= ' ' . $tr[$n['props']['text']];
		}
	}
	foreach (array('Content and Forms', 'Reach the data submitted through forms', 'Update Search Index', 'Comments', 'Users') as $label) {
		pg_assert_true(isset($tr[$label]) && mb_stripos($turkish, $tr[$label], 0, 'UTF-8') !== false, 'the Turkish note says ' . (isset($tr[$label]) ? $tr[$label] : $label));
	}
}

// -- 22. Turkish ----------------------------------------------------------------

// Every text has a Turkish value; the answers of every pick list stay
// apart in Turkish (a filter compares the stored word); a board's, a
// section's and a tag's name go into an address whole, in both languages;
// every page has a plain Turkish address of its own.
function test_forum_turkish()
{
	$tr = _test_forum_tr();
	$literals = _test_forum_lang_literals();
	pg_assert_true(count($literals) > 900, 'lang() literals found');
	$missing = array();
	foreach ($literals as $key) {
		if (!isset($tr[$key]) || trim($tr[$key]) === '') {
			$missing[] = $key;
		}
	}
	pg_assert_same(array(), $missing, 'every literal has a Turkish value');

	foreach (array('board' => array_values(_test_forum_boards()), 'kind' => _test_forum_kinds(), 'topic status' => _test_forum_topic_statuses(),
		'section' => array_values(_test_forum_sections()), 'desk status' => _test_forum_doc_statuses(), 'reason' => _test_forum_report_reasons(),
		'report status' => _test_forum_report_statuses(), 'yes or no' => _test_forum_yes_no()) as $list => $values) {
		$turkish = array();
		foreach ($values as $value) {
			$turkish[] = mb_strtolower(isset($tr[$value]) ? $tr[$value] : $value, 'UTF-8');
		}
		pg_assert_same(count($values), count(array_unique($turkish)), 'the ' . $list . ' answers differ in Turkish');
	}

	$tpl = _test_forum_tpl();
	$json = json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	preg_match_all('/\{\{page:tag\}\}\?tag=([^"#&]+)/', $json, $m);
	$names = array_merge(array_values(_test_forum_boards()), array_values(_test_forum_sections()), array_map('rawurldecode', $m[1]));
	foreach ($names as $name) {
		foreach (array($name, isset($tr[$name]) ? $tr[$name] : $name) as $value) {
			pg_assert_same($value, pg_sw_url_param_value(array('x' => $value), 'x'), 'a name goes into an address whole: ' . $value);
		}
	}

	$seen = array();
	foreach (_test_forum_pages() as $key => $p) {
		$slug = isset($tr[$p['name']]) ? $tr[$p['name']] : $p['name'];
		pg_assert_same(1, preg_match('/^[a-z0-9-]+$/', $slug), $key . ' Turkish address is plain: ' . $slug);
		pg_assert_false(isset($seen[$slug]), $key . ' Turkish address is its own: ' . $slug);
		$seen[$slug] = true;
	}
}
