<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the News Portal design template
 * (includes/design_templates/pinegrap-news.php): the article form and its
 * office-use-only fields, the live rule every public list and the article
 * page carry, the one vocabulary of sections, statuses, placements and
 * writers, the sample articles, the breaking strip's ticker, the e-mail
 * pages, the template's own CSS and the installer preview. Pure: the
 * template file is read as the template loader reads it, and the trees are
 * walked, with no database.
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

// The template as the loader reads it, read once per run.
function _test_news_tpl()
{
	static $tpl = null;
	if ($tpl === null) {
		$tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-news.php');
	}
	return $tpl;
}

// The pages by key.
function _test_news_pages()
{
	$out = array();
	$tpl = _test_news_tpl();
	foreach ($tpl['pages'] as $p) {
		$out[$p['key']] = $p;
	}
	return $out;
}

// The sections in menu order: page key => the title the Section field stores.
function _test_news_sections()
{
	return array(
		'us' => lang('U.S.'),
		'world' => lang('World'),
		'politics' => lang('Politics'),
		'economy' => lang('Economy'),
		'tech' => lang('Tech'),
		'science' => lang('Science'),
		'health' => lang('Health'),
		'sports' => lang('Sports'),
		'arts' => lang('Arts'),
		'opinion' => lang('Opinion'),
	);
}

// The writers' names as the byline stores them.
function _test_news_writer_names()
{
	$out = array();
	foreach (array('Jane Cooper', 'Michael Brooks', 'Emily Turner', 'Daniel Ross', 'Samuel Reyes', 'Nora Whitfield') as $name) {
		$out[] = lang($name);
	}
	return $out;
}

// The attributes of a node: name => value.
function _test_news_attrs($node)
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
function _test_news_classes($node)
{
	$class = (is_array($node) && isset($node['props']['cssClass']) && is_string($node['props']['cssClass'])) ? $node['props']['cssClass'] : '';
	return preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY);
}

// The tag of a node ('' when it has none).
function _test_news_tag($node)
{
	return (is_array($node) && isset($node['props']['tag']) && is_string($node['props']['tag'])) ? strtolower($node['props']['tag']) : '';
}

// Every node of a tree, in document order.
function _test_news_nodes($node)
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
			$out = array_merge($out, _test_news_nodes($child));
		}
	}
	return $out;
}

// A tree with every placed shared component and widget put in its place: a
// shared component by its tree, a widget by a 'widget' node holding its tree
// (none for a starter tree), named in props._widget.
function _test_news_resolve($node)
{
	if (!is_array($node)) {
		return $node;
	}
	$tpl = _test_news_tpl();
	if (isset($node['type']) && $node['type'] === 'shared_ref') {
		if (isset($node['props']['templateShared'])) {
			$key = $node['props']['templateShared'];
			return isset($tpl['shared'][$key]['tree']) ? _test_news_resolve($tpl['shared'][$key]['tree']) : null;
		}
		if (isset($node['props']['templateWidget'])) {
			$key = $node['props']['templateWidget'];
			$tree = isset($tpl['widgets'][$key]['tree']) ? $tpl['widgets'][$key]['tree'] : null;
			return array('type' => 'widget', 'props' => array('_widget' => $key), 'children' => is_array($tree) ? array(_test_news_resolve($tree)) : array());
		}
	}
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $i => $child) {
			$node['children'][$i] = _test_news_resolve($child);
		}
	}
	return $node;
}

// The keys of the widgets a resolved tree places, in order, once each.
function _test_news_placed($resolved)
{
	$out = array();
	foreach (_test_news_nodes($resolved) as $n) {
		if (isset($n['type']) && $n['type'] === 'widget' && !in_array($n['props']['_widget'], $out, true)) {
			$out[] = $n['props']['_widget'];
		}
	}
	return $out;
}

// The pages in folder 'public', with their trees resolved.
function _test_news_public_trees()
{
	$out = array();
	foreach (_test_news_pages() as $key => $p) {
		if ($p['folder'] === 'public') {
			$out[$key] = _test_news_resolve($p['tree']);
		}
	}
	return $out;
}

// The form control named $name in a tree, null when there is none.
function _test_news_control($tree, $name)
{
	foreach (_test_news_nodes($tree) as $n) {
		if (in_array(_test_news_tag($n), array('input', 'select', 'textarea'), true)) {
			$a = _test_news_attrs($n);
			if (isset($a['name']) && $a['name'] === $name) {
				return $n;
			}
		}
	}
	return null;
}

// The options of a select node: value => true when selected.
function _test_news_options($select)
{
	$out = array();
	foreach (is_array($select) && isset($select['children']) ? $select['children'] : array() as $o) {
		if (_test_news_tag($o) !== 'option') {
			continue;
		}
		$a = _test_news_attrs($o);
		$value = isset($a['value']) ? $a['value'] : (isset($o['props']['text']) ? (string) $o['props']['text'] : '');
		$out[] = array($value, array_key_exists('selected', $a));
	}
	return $out;
}

// The answers of the article form's pick list $name, prompt left out.
function _test_news_answers($name)
{
	$tpl = _test_news_tpl();
	$out = array();
	foreach (_test_news_options(_test_news_control($tpl['widgets']['article_form']['tree'], $name)) as $o) {
		if ($o[0] !== '') {
			$out[] = $o[0];
		}
	}
	return $out;
}

// The live rule as the template writes it.
function _test_news_live_rows()
{
	$statuses = _test_news_answers('status');
	return array(
		array('field' => 'status', 'operator' => 'is equal to', 'value' => isset($statuses[3]) ? $statuses[3] : '?', 'dynamic_value' => ''),
		array('field' => 'publish_date', 'operator' => 'is greater than or equal to', 'value' => '2000-01-01 00:00:00', 'dynamic_value' => ''),
		array('field' => 'publish_date', 'operator' => 'is less than or equal to', 'value' => '', 'dynamic_value' => 'current date and time'),
	);
}

// What a view config lacks of the live rule: one line per missing row.
function _test_news_live_problems($cfg)
{
	$problems = array();
	$filters = (isset($cfg['filters']) && is_array($cfg['filters'])) ? $cfg['filters'] : array();
	foreach (_test_news_live_rows() as $row) {
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

// The template's own CSS: the User Styles entry without the block rules.
function _test_news_own_css()
{
	$tpl = _test_news_tpl();
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

// The text a visitor sees in a node: its own and its children's, leaving
// out what only a screen reader hears.
function _test_news_visible_text($node)
{
	if (!is_array($node) || in_array('visually-hidden', _test_news_classes($node), true)) {
		return '';
	}
	$text = isset($node['props']['text']) && is_string($node['props']['text']) ? $node['props']['text'] : '';
	foreach (!empty($node['children']) && is_array($node['children']) ? $node['children'] : array() as $child) {
		$text .= _test_news_visible_text($child);
	}
	return trim($text);
}

// -- 1. the file ---------------------------------------------------------------

// The file loads without a notice (the runner turns one into an error),
// carries the template keys, and its order, look, palette and thumbnail are
// known to the platform.
function test_news_template_loads()
{
	$tpl = _test_news_tpl();
	pg_assert_true(is_array($tpl), 'the template is an array');
	foreach (array('name', 'version', 'framework', 'order', 'icon', 'thumb', 'description', 'look', 'palette', 'highlights', 'folders', 'contact_groups', 'pages', 'widgets', 'shared', 'assets') as $key) {
		pg_assert_true(array_key_exists($key, $tpl), 'key ' . $key);
	}
	pg_assert_same(1, preg_match('/^\d+\.\d+\.\d+$/', $tpl['version']), 'version x.y.z');
	pg_assert_same('bootstrap5', $tpl['framework'], 'framework');
	pg_assert_same(70, $tpl['order'], 'order');
	pg_assert_same('news', $tpl['thumb'], 'thumb');
	pg_assert_true(isset(pg_design_looks()[$tpl['look']]), 'a built-in look');
	pg_assert_true(isset(pg_design_palettes()[$tpl['palette']]), 'a built-in palette');
	pg_assert_true(function_exists('_pg_design_thumb_news'), 'the thumbnail is drawn by its own function');
	pg_assert_true(count($tpl['highlights']) > 0, 'highlights');

	$orders = array();
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php') as $file) {
		$other = _pg_tpl_include($file);
		if (is_array($other) && isset($other['order'])) {
			$orders[basename($file, '.php')] = (int) $other['order'];
		}
	}
	pg_assert_same(array('pinegrap-news'), array_keys($orders, 70, true), 'order 70 is the news template\'s alone');
	pg_assert_true(isset(pg_design_templates()['pinegrap-news']), 'the template is listed');
}

// -- 2. placeholders -----------------------------------------------------------

// Every page, tab, folder, contact group, widget and shared component a
// placeholder names exists, and every widget sits on a page or on none.
function test_news_placeholders_resolve()
{
	$tpl = _test_news_tpl();
	$pages = _test_news_pages();
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

// Every list and record view of the articles placed on a public page (or
// in a shared component placed there) carries all three live rows; none of
// them is a submitter or viewer view.
function test_news_public_views_carry_live_rule()
{
	$tpl = _test_news_tpl();
	$checked = array();
	foreach (_test_news_public_trees() as $page_key => $tree) {
		foreach (_test_news_placed($tree) as $key) {
			$cfg = $tpl['widgets'][$key]['config'];
			pg_assert_true(empty($cfg['viewer_filter']), $page_key . ' ' . $key . ' has no viewer filter');
			pg_assert_true(!isset($cfg['access_control']) || $cfg['access_control'] !== 'submitter_only', $page_key . ' ' . $key . ' is not submitter only');
			$type = isset($cfg['regionType']) ? $cfg['regionType'] : '';
			if (!in_array($type, array('form_list_view', 'form_item_view'), true) || !isset($cfg['custom_form_page_id']) || $cfg['custom_form_page_id'] !== '{{tab:blog_new}}') {
				continue;
			}
			pg_assert_same(array(), _test_news_live_problems($cfg), $page_key . ' ' . $key . ' carries the live rule');
			$checked[$key] = true;
		}
	}
	foreach (array('home_lead', 'home_featured', 'home_us', 'home_world', 'home_politics', 'home_arts', 'home_opinion', 'home_posts',
		'most_read', 'breaking_main', 'breaking_copy', 'section_us', 'section_arts', 'opinion_list', 'blog_list', 'author_list',
		'article_more', 'blog_post') as $key) {
		pg_assert_true(isset($checked[$key]), $key . ' is checked');
	}

	// The check itself: a view without the empty-date row is caught.
	$cfg = $tpl['widgets']['home_posts']['config'];
	foreach ($cfg['filters'] as $i => $f) {
		if ($f['field'] === 'publish_date' && $f['value'] === '2000-01-01 00:00:00') {
			unset($cfg['filters'][$i]);
		}
	}
	pg_assert_same(array('publish_date is greater than or equal to 2000-01-01 00:00:00'), _test_news_live_problems($cfg), 'a missing empty-date row is caught');
}

// -- 4. private views ------------------------------------------------------------

// The author's own article page and the article e-mails are submitter
// views in private folders; the public article page filters on the live
// rule; the article addresses keep their reference code; the staff
// directory is staff only.
function test_news_private_views()
{
	$tpl = _test_news_tpl();
	$pages = _test_news_pages();

	$sv = $tpl['widgets']['submission_view'];
	pg_assert_same('submitter_only', $sv['config']['access_control'], 'submission_view access');
	pg_assert_true(empty($sv['config']['filters']), 'submission_view has no filters');
	pg_assert_same('contributors', $pages[$sv['page']]['folder'], 'submission page folder');

	foreach (array('email_article_admin', 'email_article_author') as $key) {
		$w = $tpl['widgets'][$key];
		pg_assert_same('submitter_only', $w['config']['access_control'], $key . ' access');
		pg_assert_same('private', $pages[$w['page']]['folder'], $key . ' page folder');
	}

	pg_assert_same(_test_news_live_rows(), $tpl['widgets']['blog_post']['config']['filters'], 'the article page filters are the live rule');
	pg_assert_same('public', $tpl['widgets']['blog_post']['config']['access_control'], 'the article page is public');
	pg_assert_same(0, $pages['blog_new']['form']['pretty_urls'], 'article addresses use the reference code');
	foreach (array('blog_new', 'my_articles', 'submission') as $key) {
		pg_assert_same('contributors', $pages[$key]['folder'], $key . ' folder');
	}
	foreach (array('staff', 'staff_directory', 'staff_entry') as $key) {
		pg_assert_same('private', $pages[$key]['folder'], $key . ' folder');
	}
	pg_assert_same('private', $tpl['folders']['contributors']['parent'], 'contributors sits under private');
	pg_assert_same('private', $tpl['folders']['contributors']['access'], 'contributors is private');
}

// -- 5. office-use-only fields ---------------------------------------------------

// The fields the editors fill are office use only, store a default an
// author cannot change, and leave nothing of themselves on an author's form.
function test_news_office_fields()
{
	$tpl = _test_news_tpl();
	$tree = $tpl['widgets']['article_form']['tree'];
	$office = array('status' => 'pick list', 'publish_date' => 'date and time', 'placement' => 'pick list',
		'breaking' => 'pick list', 'reading_time' => 'text box', 'correction' => 'text area');

	foreach ($office as $name => $type) {
		$n = _test_news_control($tree, $name);
		pg_assert_true(is_array($n), $name . ' exists');
		pg_assert_true(isset($n['props']['_cf']['office_use_only']) && $n['props']['_cf']['office_use_only'] === true, $name . ' is office use only');
		pg_assert_false(array_key_exists('required', _test_news_attrs($n)), $name . ' is not required');
		if (_test_news_tag($n) === 'select') {
			$selected = 0;
			foreach (_test_news_options($n) as $o) {
				pg_assert_true($o[0] !== '', $name . ' has no empty prompt');
				$selected += $o[1] ? 1 : 0;
			}
			pg_assert_same(1, $selected, $name . ' has one selected option');
		}
	}

	$controls = _pg_cf_controls($tree, 0);
	pg_assert_same(lang('Awaiting review'), $controls['status']['default_value'], 'status default');
	pg_assert_same(lang('Standard'), $controls['placement']['default_value'], 'placement default');
	pg_assert_same(lang('No'), $controls['breaking']['default_value'], 'breaking default');
	foreach ($office as $name => $type) {
		pg_assert_same($type, $controls[$name]['type'], $name . ' field type');
	}

	$publish = _test_news_control($tree, 'publish_date');
	pg_assert_same('datetime-local', _test_news_attrs($publish)['type'], 'publish_date is a date and time');

	$body = _test_news_control($tree, 'body');
	pg_assert_same('textarea', _test_news_tag($body), 'body is a text area');
	pg_assert_true(isset($body['props']['_cf']['wysiwyg']) && $body['props']['_cf']['wysiwyg'] === true, 'body is rich text');
	pg_assert_true(array_key_exists('required', _test_news_attrs($body)), 'body is required');

	foreach (array('title' => 'title', 'summary' => 'description', 'section' => 'category', 'cover_image' => 'media') as $name => $role) {
		$n = _test_news_control($tree, $name);
		pg_assert_same($role, isset($n['props']['_cf']['rss_field']) ? $n['props']['_cf']['rss_field'] : '', $name . ' role');
	}
	pg_assert_true(isset($tpl['widgets']['article_form']['tree']) && strpos(json_encode($tree), '"section":"captcha"') !== false, 'the form has a CAPTCHA');

	// What an author sees: the office controls and their labels are gone,
	// and nothing of them stands between the author's last field and the
	// CAPTCHA.
	$labels = array();
	foreach ($office as $name => $type) {
		$labels[] = $controls[$name]['label'];
	}
	// The six office rows as the form stores them; no pick list reads its
	// options from a table, so nothing here needs the database.
	$map = array();
	foreach ($office as $name => $type) {
		$map[$name] = array('id' => count($map) + 1, 'office_use_only' => true, 'type' => $type, 'default_value' => '', 'folder_default' => false);
	}
	$author = $tree;
	pg_cf_apply_field_bindings($author, $map, null, 0, false);
	$nodes = _test_news_nodes($author);
	$last_field = -1;
	$captcha = -1;
	foreach ($nodes as $i => $n) {
		if (isset($n['props']['text']) && is_string($n['props']['text'])) {
			pg_assert_false(in_array($n['props']['text'], $labels, true), 'no office label left: ' . $n['props']['text']);
		}
		if (in_array(_test_news_tag($n), array('input', 'select', 'textarea'), true)) {
			$a = _test_news_attrs($n);
			pg_assert_false(isset($a['name']) && isset($office[$a['name']]), 'no office control left');
			if ($captcha < 0) {
				$last_field = $i;
			}
		}
		if (isset($n['props']['_bindings']['section']) && $n['props']['_bindings']['section'] === 'captcha') {
			$captcha = $i;
		}
	}
	pg_assert_true($last_field >= 0 && $captcha > $last_field, 'the CAPTCHA follows the author\'s fields');
	for ($i = $last_field + 1; $i < $captcha; $i++) {
		$tag = _test_news_tag($nodes[$i]);
		pg_assert_false(in_array($tag, array('fieldset', 'legend'), true), 'no ' . $tag . ' before the CAPTCHA');
		pg_assert_false(in_array('form-text', _test_news_classes($nodes[$i]), true), 'no help text before the CAPTCHA');
	}
	foreach (array('title', 'summary', 'body', 'byline', 'note_to_editors', 'rights') as $name) {
		pg_assert_true(is_array(_test_news_control($author, $name)), $name . ' stays on the author\'s form');
	}
}

// -- 6. one vocabulary -----------------------------------------------------------

// Options, pages, menus, filters, writer links and the sample articles use
// the same section titles, statuses, placements and writer names.
function test_news_one_vocabulary()
{
	$tpl = _test_news_tpl();
	$pages = _test_news_pages();
	$sections = _test_news_sections();
	$writers = _test_news_writer_names();

	pg_assert_same(array_values($sections), _test_news_answers('section'), 'the Section options are the section titles, in order');
	pg_assert_same(array(lang('Awaiting review'), lang('In review'), lang('Changes requested'), lang('Live on the site'), lang('Not accepted'), lang('Withdrawn')), _test_news_answers('status'), 'statuses');
	pg_assert_same(array(lang('Standard'), lang('Lead Story'), lang('Featured')), _test_news_answers('placement'), 'placements');
	pg_assert_same(array(lang('No'), lang('Yes')), _test_news_answers('breaking'), 'breaking');

	foreach ($sections as $key => $title) {
		pg_assert_true(isset($pages[$key]), 'section page ' . $key);
		pg_assert_same($title, isset($pages[$key]) ? $pages[$key]['title'] : null, 'section page title ' . $key);
	}

	// The menu bar, the pill strip and the drawer link every section; so does the footer.
	$nav = json_encode($tpl['shared']['sections_nav']['tree']);
	$footer = json_encode($tpl['shared']['footer']['tree']);
	foreach ($sections as $key => $title) {
		pg_assert_true(substr_count($nav, '"{{page:' . $key . '}}"') >= 3, 'the sections menu links ' . $key . ' in the bar, the strip and the drawer');
		pg_assert_true(substr_count($footer, '"{{page:' . $key . '}}"') >= 1, 'the footer links ' . $key);
	}

	// Every filter on the section compares with a section title.
	foreach ($tpl['widgets'] as $key => $w) {
		foreach (isset($w['config']['filters']) ? $w['config']['filters'] : array() as $f) {
			if ($f['field'] === 'section') {
				pg_assert_true(in_array($f['value'], $sections, true), $key . ' filters on a section title');
			}
		}
	}

	// Every writer link names a writer.
	preg_match_all('/\{\{page:author\}\}\?author=([^"#&]+)/', json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $m);
	pg_assert_true(count($m[1]) >= 9, 'writer links found');
	foreach ($m[1] as $encoded) {
		pg_assert_true(in_array(rawurldecode($encoded), $writers, true), 'writer link ' . $encoded);
	}

	// The sample articles: bylines are the writers, values are options.
	$samples = $pages['blog_new']['form']['sample_records'];
	$bylines = array();
	foreach ($samples as $i => $r) {
		$bylines[$r['byline']] = true;
		foreach (array('section', 'status', 'placement', 'breaking') as $field) {
			pg_assert_true(in_array($r[$field], _test_news_answers($field), true), 'sample ' . $i . ' ' . $field);
		}
	}
	$names = array_keys($bylines);
	sort($names);
	sort($writers);
	pg_assert_same($writers, $names, 'the sample bylines are the writers');
}

// -- 7. sample articles ----------------------------------------------------------

// Twenty-four live articles that fill every slot of the front page and
// every section, with no date or weather that would go stale.
function test_news_sample_articles()
{
	$tpl = _test_news_tpl();
	$pages = _test_news_pages();
	$samples = $pages['blog_new']['form']['sample_records'];
	pg_assert_same(24, count($samples), 'the seeder\'s cap');

	$controls = _pg_cf_controls($tpl['widgets']['article_form']['tree'], 0);
	$dates = '/\b(January|February|March|April|May|June|July|August|September|October|November|December|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday|spring|summer|autumn|winter)\b/i';
	$weather = '/\b(weather|forecast|temperature|°F|mph)\b/iu';
	$lead = 0;
	$featured = 0;
	$breaking = 0;
	$captions = 0;
	$by_section = array();
	$not_lead = array();
	foreach ($samples as $i => $r) {
		foreach ($r as $key => $value) {
			pg_assert_true(isset($controls[mb_strtolower($key, 'UTF-8')]), 'sample ' . $i . ' ' . $key . ' is a field of the form');
		}
		$paragraphs = preg_split('/\n\s*\n/', trim($r['body']));
		pg_assert_true(count($paragraphs) >= 3 && count($paragraphs) <= 5, 'sample ' . $i . ' has 3 to 5 paragraphs');
		pg_assert_same(1, preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $r['publish_date']), 'sample ' . $i . ' publish_date format');
		pg_assert_true(strtotime($r['publish_date']) <= time(), 'sample ' . $i . ' is published');
		pg_assert_same(lang('Live on the site'), $r['status'], 'sample ' . $i . ' is live');
		pg_assert_same(lang('Sample photo'), $r['image_credit'], 'sample ' . $i . ' credit');
		$captions += ($r['image_caption'] !== '') ? 1 : 0;
		$lead += ($r['placement'] === lang('Lead Story')) ? 1 : 0;
		$featured += ($r['placement'] === lang('Featured')) ? 1 : 0;
		$breaking += ($r['breaking'] === lang('Yes')) ? 1 : 0;
		foreach (array('title', 'summary', 'image_caption', 'correction', 'body') as $field) {
			if (isset($r[$field])) {
				pg_assert_same(0, preg_match($dates, $r[$field]), 'sample ' . $i . ' ' . $field . ' names no month, weekday or season');
				pg_assert_same(0, preg_match($weather, $r[$field]), 'sample ' . $i . ' ' . $field . ' has no weather');
			}
		}
		$by_section[$r['section']][] = $i;
		if ($r['placement'] !== lang('Lead Story')) {
			$not_lead[$r['section']][] = $i;
		}
	}
	pg_assert_same(3, $lead, 'three Lead Story articles');
	pg_assert_same(4, $featured, 'four Featured articles');
	pg_assert_true($breaking >= 1, 'a breaking article');
	pg_assert_true($captions <= 1, 'at most one caption');

	$sections = _test_news_sections();
	foreach ($sections as $key => $title) {
		if ($key !== 'opinion') {
			pg_assert_true(isset($by_section[$title]) && count($by_section[$title]) >= 2, 'section ' . $key . ' has two articles');
		}
	}
	foreach (array('politics', 'economy', 'tech', 'science', 'health', 'sports', 'arts') as $key) {
		pg_assert_true(isset($not_lead[$sections[$key]]) && count($not_lead[$sections[$key]]) >= 2, 'front page tile ' . $key . ' has two articles');
	}
}

// -- 8. pagination ---------------------------------------------------------------

// Lists on one page share the page number: one list per page pages at most.
function test_news_one_paginated_list_per_page()
{
	$tpl = _test_news_tpl();
	foreach (_test_news_pages() as $page_key => $p) {
		$paged = array();
		foreach (_test_news_placed(_test_news_resolve($p['tree'])) as $key) {
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
}

// -- 9. the breaking strip -------------------------------------------------------

// The ready-made News Ticker's contract: two lists with one config, the
// copy hidden from screen readers and out of the tab order, and one pause
// box named by its label.
function test_news_ticker_contract()
{
	$tpl = _test_news_tpl();
	$main = $tpl['widgets']['breaking_main'];
	$copy = $tpl['widgets']['breaking_copy'];
	pg_assert_same($main['config'], $copy['config'], 'the twins share one config');
	pg_assert_same('', $main['page'], 'main sits in the shared strip');
	pg_assert_same('', $copy['page'], 'copy sits in the shared strip');

	$group = function ($tree) {
		foreach (_test_news_nodes($tree) as $n) {
			if (in_array('pg-marquee-group', _test_news_classes($n), true)) {
				return $n;
			}
		}
		return null;
	};
	$links = function ($tree) {
		$out = array();
		foreach (_test_news_nodes($tree) as $n) {
			if (_test_news_tag($n) === 'a' || (isset($n['props']['contentType']) && $n['props']['contentType'] === 'link')) {
				$out[] = $n;
			}
		}
		return $out;
	};

	$g = $group($copy['tree']);
	pg_assert_true(in_array('pg-marquee-copy', _test_news_classes($g), true), 'the copy group is marked');
	pg_assert_same('true', isset(_test_news_attrs($g)['aria-hidden']) ? _test_news_attrs($g)['aria-hidden'] : null, 'the copy group is hidden from screen readers');
	pg_assert_true(count($links($copy['tree'])) > 0, 'the copy has links');
	foreach ($links($copy['tree']) as $a) {
		pg_assert_same('-1', isset(_test_news_attrs($a)['tabindex']) ? _test_news_attrs($a)['tabindex'] : null, 'a copy link is out of the tab order');
	}

	$g = $group($main['tree']);
	pg_assert_false(in_array('pg-marquee-copy', _test_news_classes($g), true), 'the main group is not the copy');
	pg_assert_false(isset(_test_news_attrs($g)['aria-hidden']), 'the main group is read');
	foreach ($links($main['tree']) as $a) {
		pg_assert_false(isset(_test_news_attrs($a)['tabindex']), 'a main link stays in the tab order');
	}

	$strip = $tpl['shared']['breaking']['tree'];
	$pauses = array();
	$fors = array();
	foreach (_test_news_nodes($strip) as $n) {
		if (_test_news_tag($n) === 'input' && in_array('pg-marquee-pause', _test_news_classes($n), true)) {
			$pauses[] = $n;
		}
		if (_test_news_tag($n) === 'label' && isset(_test_news_attrs($n)['for'])) {
			$fors[] = _test_news_attrs($n)['for'];
		}
	}
	pg_assert_same(1, count($pauses), 'one pause box');
	$id = isset($pauses[0]['props']['id']) ? $pauses[0]['props']['id'] : '';
	pg_assert_true($id !== '' && in_array($id, $fors, true), 'the pause label names the box by props.id');
	pg_assert_true(in_array('pg-marquee', _test_news_classes($strip), true) && in_array('pn-breaking', _test_news_classes($strip), true), 'the strip is the marquee');
	pg_assert_same(array('breaking_main', 'breaking_copy'), _test_news_placed(_test_news_resolve($strip)), 'the strip places the twins');
}

// -- 10. ids ---------------------------------------------------------------------

// An id goes in props.id: the renderer drops one written as an attribute.
function test_news_no_ids_in_attrs()
{
	$tpl = _test_news_tpl();
	$found = array();
	$trees = array();
	foreach ($tpl['pages'] as $p) {
		$trees[] = $p['tree'];
	}
	foreach ($tpl['widgets'] as $w) {
		$trees[] = $w['tree'];
	}
	foreach ($tpl['shared'] as $s) {
		$trees[] = $s['tree'];
	}
	foreach ($trees as $tree) {
		foreach (_test_news_nodes($tree) as $n) {
			if (isset(_test_news_attrs($n)['id'])) {
				$found[] = _test_news_attrs($n)['id'];
			}
		}
	}
	pg_assert_same(array(), $found, 'no id in _attrs');
}

// -- 11. most read and the writers' archive -------------------------------------

function test_news_most_read_and_archive()
{
	$tpl = _test_news_tpl();
	$cfg = $tpl['widgets']['most_read']['config'];
	pg_assert_same('recent_views', $cfg['order_by_field'], 'ordered by recent views');
	pg_assert_same(7, $cfg['order_recent_days'], 'over a week');
	pg_assert_same('{{tab:blog_post}}', $cfg['detail_page_id'], 'counted on the article page');
	pg_assert_same(5, $cfg['max_results'], 'five');
	pg_assert_same(5, $cfg['items_per_page'], 'one page');
	pg_assert_true(in_array('recent_views', pg_sw_form_order_counters(), true), 'recent_views is a counter order');

	$author = $tpl['widgets']['author_list'];
	pg_assert_same('author', $author['page'], 'the archive is on the writers page');
	$row = null;
	foreach ($author['config']['filters'] as $f) {
		if ($f['field'] === 'byline') {
			$row = $f;
		}
	}
	pg_assert_true(is_array($row), 'the archive filters on the byline');
	pg_assert_same('is equal to', $row['operator'], 'an exact byline');
	pg_assert_same('url parameter', $row['dynamic_value'], 'read from the address');
	pg_assert_same('author', $row['dynamic_value_attribute'], 'parameter author');
	if (function_exists('pg_sw_url_param_name')) {
		pg_assert_same($row['dynamic_value_attribute'], pg_sw_url_param_name($row['dynamic_value_attribute']), 'the parameter name is accepted as it is');
	}
	pg_assert_false(isset($author['config']['empty_message']), 'no empty message (an unmatched writer cuts the block)');
	pg_assert_true(isset($author['config']['filter_title']) && strpos($author['config']['filter_title'], '{value}') !== false, 'the page title names the writer');
	pg_assert_true(in_array('has_filter_value', array_map(function ($n) {
		return isset($n['props']['_bindings']['eo_visible_if']) ? $n['props']['_bindings']['eo_visible_if'] : '';
	}, _test_news_nodes($author['tree'])), true), 'the writer heading shows with a matched value only');

	// The page heading does not depend on the archive's flags.
	$pages = _test_news_pages();
	$h1 = 0;
	foreach (_test_news_nodes($pages['author']['tree']) as $n) {
		if (_test_news_tag($n) === 'h1') {
			$h1++;
			pg_assert_false(isset($n['props']['_bindings']), 'the writers page h1 is static');
		}
	}
	pg_assert_same(1, $h1, 'one h1 on the writers page');
}

// -- 12. e-mail pages ------------------------------------------------------------

function test_news_email_pages()
{
	$tpl = _test_news_tpl();
	$pages = _test_news_pages();
	$keys = array_keys(pg_design_template_email_pages($tpl));
	sort($keys);
	$want = array('email_account_welcome', 'email_article_new', 'email_article_received', 'email_desk_message', 'email_message_received',
		'email_new_message', 'email_password_reset', 'email_reply', 'email_tip_new', 'email_tip_received', 'email_welcome');
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
}

// -- 13. private fields ----------------------------------------------------------

// Notes to editors and the desk's own fields never show on a public page.
function test_news_private_fields_stay_private()
{
	$private = array('note_to_editors', 'status', 'placement', 'breaking');
	$checked = 0;
	foreach (_test_news_public_trees() as $page_key => $tree) {
		foreach (_test_news_nodes($tree) as $n) {
			$bindings = isset($n['props']['_bindings']) && is_array($n['props']['_bindings']) ? $n['props']['_bindings'] : array();
			foreach (array('text', 'html', 'src', 'href') as $attr) {
				if (isset($bindings[$attr])) {
					$checked++;
					pg_assert_false(in_array($bindings[$attr], $private, true), $page_key . ' binds ' . $bindings[$attr]);
				}
			}
		}
	}
	pg_assert_true($checked > 100, 'public bindings were checked');

	// The note is shown where the editors read it.
	$tpl = _test_news_tpl();
	pg_assert_contains('"note_to_editors"', json_encode($tpl['widgets']['submission_view']['tree']), 'the desk shows the note');
	pg_assert_contains('"note_to_editors"', json_encode($tpl['widgets']['email_article_admin']['tree']), 'the editors\' e-mail shows the note');
}

// -- 14. installer preview -------------------------------------------------------

function test_news_preview_html()
{
	$tpl = pg_design_templates()['pinegrap-news'];
	$preview = array_keys(pg_design_template_preview_pages($tpl));
	sort($preview);
	$want = array('about', 'accessibility', 'arts', 'author', 'blog', 'contact', 'economy', 'health', 'home', 'newsletter', 'opinion',
		'politics', 'privacy', 'science', 'search', 'sports', 'standards', 'tech', 'terms', 'tips', 'us', 'world');
	pg_assert_same($want, $preview, 'the preview pages');

	$html = pg_design_template_preview_html('pinegrap-news', 'home');
	pg_assert_true($html !== '', 'home renders');
	pg_assert_contains('<style id="pg-user-styles">', $html, 'home prints User Styles');
	pg_assert_contains('fonts.googleapis.com/css2?family=Barlow%20Condensed', $html, 'home links the fonts');
	pg_assert_contains('pn-lead-grid', $html, 'home has the lead grid');
	pg_assert_contains('pg-marquee-copy', $html, 'home has the breaking strip');
	pg_assert_false(strpos($html, '^^') !== false, 'home has no ^^');

	foreach (array_keys(pg_design_template_email_pages($tpl)) as $key) {
		$html = pg_design_template_preview_html('pinegrap-news', $key);
		pg_assert_true($html !== '', $key . ' renders');
		pg_assert_contains('pg-user-styles', $html, $key . ' prints User Styles');
		pg_assert_contains('fonts.googleapis.com/css2?family=Barlow%20Condensed', $html, $key . ' links the fonts');
		// A button's address keeps its ^^reference_code^^ in the preview.
		pg_assert_false(strpos(preg_replace('/href="[^"]*"/', '', $html), '^^') !== false, $key . ' has no ^^ outside an address');
	}
}

// -- 15. the template's own CSS --------------------------------------------------

function test_news_css()
{
	$tpl = _test_news_tpl();
	$own = _test_news_own_css();
	pg_assert_true(strlen($own) > 2000, 'own CSS found');

	// Every pn- class a tree uses has a rule.
	$used = array();
	$trees = array();
	foreach ($tpl['pages'] as $p) {
		$trees[] = $p['tree'];
	}
	foreach ($tpl['widgets'] as $w) {
		$trees[] = $w['tree'];
	}
	foreach ($tpl['shared'] as $s) {
		$trees[] = $s['tree'];
	}
	$nodes = array();
	foreach ($trees as $tree) {
		$nodes = array_merge($nodes, _test_news_nodes($tree));
	}
	foreach ($nodes as $n) {
		foreach (_test_news_classes($n) as $c) {
			if (strpos($c, 'pn-') === 0) {
				$used[$c] = true;
			}
		}
	}
	foreach (array('pn-brand', 'pn-min-0', 'pn-scrim', 'pn-tip', 'pn-body', 'pn-lead-grid', 'pn-stack') as $c) {
		pg_assert_true(isset($used[$c]), $c . ' is used');
	}
	foreach (array_keys($used) as $c) {
		pg_assert_same(1, preg_match('/\.' . preg_quote($c, '/') . '(?![\w-])/', $own), $c . ' has a rule');
	}

	pg_assert_same(0, preg_match('/var\(--pg-primary-fg\s*\)/', $own), '--pg-primary-fg always has a fallback');
	pg_assert_false(strpos($own, '!important') !== false, 'no !important');
	pg_assert_false(strpos($own, ':first-child') !== false, 'no :first-child (the edit chip comes first for editors)');

	// Selectors only: a + inside clamp() is a value.
	$plain = preg_replace('~/\*.*?\*/~s', '', $own);
	preg_match_all('/([^{}]+)\{/', $plain, $m);
	pg_assert_true(count($m[1]) > 30, 'selectors found');
	foreach ($m[1] as $selector) {
		$selector = trim($selector);
		if (strpos($selector, '@') === 0) {
			continue;
		}
		pg_assert_same(0, preg_match('/[>+~]/', $selector), 'no combinator in ' . $selector);
	}
	pg_assert_same(1, preg_match('/\.pn-body\{[^}]*position:relative[^}]*\}/', $plain), '.pn-body is positioned');
	pg_assert_same(1, preg_match('/\.pn-body\{[^}]*contain:paint[^}]*\}/', $plain), '.pn-body contains its paint');
	// A tip's value carries a <br> at each line break: breaking again on the
	// kept newline would print a blank line after every line.
	pg_assert_same(0, preg_match('/\.pn-tip\{[^}]*white-space:pre/', $plain), '.pn-tip keeps the default white-space');
	// The pill strip: Chromium does not scroll a partly visible pill into
	// view on focus, so the padding narrows the visible area to the middle
	// and the fade drops while a pill has focus.
	pg_assert_same(1, preg_match('/\.pn-strip\{[^}]*scroll-padding-inline:(3\d|4\d|50)%/', $plain), 'the strip scrolls a focused pill towards the centre');
	pg_assert_same(1, preg_match('/\.pn-strip:focus-within\{[^}]*(?<!-webkit-)mask-image:none/', $plain), 'the strip drops its fade while a pill has focus');

	// No block rule the template does not ship, no utility Bootstrap lacks,
	// and no outlined button with words on it (too faint in the dark scheme
	// of the plain palette); the icon-only theme toggle and pause may be.
	foreach ($nodes as $n) {
		$classes = _test_news_classes($n);
		pg_assert_false(in_array('pg-shade-bottom', $classes, true), 'no pg-shade-bottom');
		pg_assert_false(in_array('min-w-0', $classes, true), 'no min-w-0');
		$outlined = false;
		foreach ($classes as $c) {
			if (strpos($c, 'btn-outline-') === 0) {
				$outlined = true;
			}
		}
		if ($outlined) {
			pg_assert_same('', _test_news_visible_text($n), 'an outlined button has no words: ' . implode(' ', $classes));
		}
		// The language switcher's button carries the language's name, so it
		// is not an outlined grey one either.
		if (isset($n['type'], $n['props']['componentType']) && $n['type'] === 'component' && $n['props']['componentType'] === 'language_switcher') {
			$variant = isset($n['props']['variant']) ? $n['props']['variant'] : 'secondary';
			pg_assert_false(!empty($n['props']['outline']) && $variant === 'secondary', 'the language switcher is not an outlined grey button');
		}
	}
}

// -- 16. e-mail colours ----------------------------------------------------------

// Every var() in an e-mail's inline style carries its own fallback.
function test_news_email_var_fallbacks()
{
	$tpl = _test_news_tpl();
	$pages = _test_news_pages();
	$trees = array($tpl['shared']['email_header']['tree'], $tpl['shared']['email_footer']['tree']);
	foreach (array_keys(pg_design_template_email_pages($tpl)) as $key) {
		$trees[] = $pages[$key]['tree'];
	}
	foreach ($tpl['widgets'] as $w) {
		if (strpos($w['page'], 'email_') === 0) {
			$trees[] = $w['tree'];
		}
	}
	$styles = 0;
	foreach ($trees as $tree) {
		foreach (_test_news_nodes($tree) as $n) {
			$a = _test_news_attrs($n);
			if (isset($a['style'])) {
				$styles++;
				pg_assert_same(0, preg_match('/var\(\s*--[\w-]+\s*\)/', $a['style']), 'var() fallback in ' . $a['style']);
				// A mail document is lang="en": CSS capitals would print a
				// Turkish i as a dotless I.
				pg_assert_same(0, preg_match('/text-transform\s*:\s*uppercase/', $a['style']), 'no CSS capitals in ' . $a['style']);
			}
			pg_assert_false(in_array('text-uppercase', _test_news_classes($n), true), 'no text-uppercase in an e-mail');
		}
	}
	pg_assert_true($styles > 20, 'e-mail styles found');
}

// -- 17. CAPTCHA -----------------------------------------------------------------

// Every form a signed-out visitor can send carries the CAPTCHA node: the
// submit is checked whenever the CAPTCHA is on, and a designed form shows it
// only where its tree has that node.
function test_news_forms_carry_captcha()
{
	$tpl = _test_news_tpl();
	$has = function ($tree) {
		foreach (_test_news_nodes($tree) as $n) {
			if (isset($n['props']['_bindings']['section']) && $n['props']['_bindings']['section'] === 'captcha') {
				return true;
			}
		}
		return false;
	};
	foreach (array('tips_form', 'contact_form', 'newsletter_form', 'newsletter_box', 'newsletter_band', 'article_form') as $key) {
		pg_assert_true($has($tpl['widgets'][$key]['tree']), $key . ' has a CAPTCHA');
	}
	foreach (_test_news_public_trees() as $page_key => $tree) {
		foreach (_test_news_placed($tree) as $key) {
			if ($tpl['widgets'][$key]['config']['regionType'] === 'custom_form') {
				pg_assert_true($has($tpl['widgets'][$key]['tree']), $page_key . ' ' . $key . ' has a CAPTCHA');
			}
		}
	}
}

// -- 18. comment labels ----------------------------------------------------------

// The label is a singular noun: the platform pluralizes it and puts it in sentences.
function test_news_comment_labels()
{
	$found = 0;
	foreach (_test_news_pages() as $key => $p) {
		if (isset($p['comments'])) {
			$found++;
			pg_assert_true(in_array($p['comments']['label'], array(lang('Comment'), lang('Message')), true), $key . ' comment label');
			pg_assert_true(isset($p['comments']['login']) && $p['comments']['login'] === true, $key . ' comments need sign-in');
		}
	}
	pg_assert_same(3, $found, 'three pages take comments');
	$pages = _test_news_pages();
	pg_assert_same(false, $pages['blog_post']['comments']['auto_publish'], 'reader comments are held for moderation');
}

// -- 19. landmarks and pictures --------------------------------------------------

function test_news_landmarks_and_alt()
{
	$tpl = _test_news_tpl();

	// No complementary landmark inside another.
	$nested = function ($node, $inside) use (&$nested) {
		$count = 0;
		if (!is_array($node)) {
			return 0;
		}
		$is = (_test_news_tag($node) === 'aside');
		if ($is && $inside) {
			$count++;
		}
		foreach (!empty($node['children']) && is_array($node['children']) ? $node['children'] : array() as $child) {
			$count += $nested($child, $inside || $is);
		}
		return $count;
	};
	foreach (_test_news_pages() as $key => $p) {
		pg_assert_same(0, $nested(_test_news_resolve($p['tree']), false), $key . ' has no aside inside an aside');
	}

	// A list picture has an empty alt: the headline beside it names the story.
	$pictures = 0;
	$in_loop = function ($node, $loop) use (&$in_loop, &$pictures) {
		if (!is_array($node)) {
			return;
		}
		if (isset($node['type']) && $node['type'] === 'loop_area') {
			$loop = true;
		}
		$image = (isset($node['props']['contentType']) && $node['props']['contentType'] === 'image') || _test_news_tag($node) === 'img';
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

	// The staff bar links distinct pages: smart-active ignores a fragment.
	pg_assert_same(0, preg_match('/"href":"[^"]*#/', json_encode($tpl['shared']['staff_bar']['tree'])), 'the staff bar has no # link');

	// The comments of the article and the messages under a submission have
	// an h2 above them, shown with the record (the region itself starts at
	// h3 while it holds no comment).
	foreach (array('blog_post' => lang('Comments'), 'submission_view' => lang('Messages with the desk')) as $key => $title) {
		$found = false;
		foreach (_test_news_nodes($tpl['widgets'][$key]['tree']) as $n) {
			if (isset($n['props']['contentType'], $n['props']['tag'], $n['props']['text']) && $n['props']['contentType'] === 'heading'
				&& $n['props']['tag'] === 'h2' && $n['props']['text'] === $title) {
				$found = true;
			}
		}
		pg_assert_true($found, $key . ' has the h2 ' . $title);
	}

	// Every aside is named: a complementary landmark needs a label.
	foreach (array_merge($tpl['widgets'], $tpl['shared']) as $key => $w) {
		if (!is_array($w['tree'])) {
			continue;
		}
		foreach (_test_news_nodes($w['tree']) as $n) {
			if (_test_news_tag($n) === 'aside') {
				$attrs = _test_news_attrs($n);
				pg_assert_true(isset($attrs['aria-label']) || isset($attrs['aria-labelledby']), $key . ': an aside is named');
			}
		}
	}
}

// -- 20. statuses explained ---------------------------------------------------------

// "How review works" on the submit page names every status the author sees
// on My Articles, in the Status field's own words.
function test_news_review_card_names_every_status()
{
	$pages = _test_news_pages();
	$texts = array();
	foreach (_test_news_nodes($pages['blog_new']['tree']) as $n) {
		if (isset($n['props']['text']) && is_string($n['props']['text'])) {
			$texts[] = $n['props']['text'];
		}
	}
	foreach (_test_news_answers('status') as $status) {
		pg_assert_true(in_array($status, $texts, true), 'How review works names ' . $status);
	}
	// There is no "published" e-mail: the editor's message under the piece
	// is it. A piece taken off the site stays in search until the next
	// index update.
	pg_assert_true(in_array(lang('When an editor sets your article live, the editor tells you so under it: that message is the e-mail you get, and no other e-mail is sent when it goes live.'), $texts, true), 'How review works says how going live is told');
	pg_assert_true(in_array(lang('An article taken off the site can still show in the site search until the editors update the search index.'), $texts, true), 'How review works mentions search after a retraction');
}

// -- 21. focus rings -----------------------------------------------------------

// A button without a colour variant has no focus ring of its own (Bootstrap's
// ring colour comes from the variant), so every such button in a shared part
// sits in a part whose own CSS draws one.
function test_news_bare_buttons_have_a_focus_ring()
{
	$tpl = _test_news_tpl();
	$own = _test_news_own_css();
	$found = 0;
	foreach ($tpl['shared'] as $key => $s) {
		if (!is_array($s['tree'])) {
			continue;
		}
		$root = array();
		foreach (_test_news_classes($s['tree']) as $c) {
			if (strpos($c, 'pn-') === 0) {
				$root[] = $c;
			}
		}
		foreach (_test_news_nodes($s['tree']) as $n) {
			$classes = _test_news_classes($n);
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

// -- 22. page shapes -------------------------------------------------------------

// The article page: the most read sits beside the article inside the record
// view's own row (shown with a found article), so the right third is not
// empty from lg; the comments stay in the article's column below, alone in
// their row, and the page places the most read once.
function test_news_article_row()
{
	$tpl = _test_news_tpl();
	$rows = array();
	foreach (_test_news_nodes($tpl['widgets']['blog_post']['tree']) as $n) {
		if (isset($n['type']) && $n['type'] === 'row') {
			$rows[] = $n;
		}
	}
	pg_assert_same(1, count($rows), 'the record view has one row');
	$cols = array();
	foreach ($rows[0]['children'] as $c) {
		$lg = '';
		foreach (_test_news_classes($c) as $cls) {
			if (preg_match('/^col-lg-(\d+)$/', $cls, $m)) {
				$lg = $m[1];
			}
		}
		if (isset($c['type']) && $c['type'] === 'col') {
			$lg = (string) $c['props']['lg'];
		}
		$widgets = array();
		foreach (_test_news_nodes($c) as $n) {
			if (isset($n['props']['templateWidget'])) {
				$widgets[] = $n['props']['templateWidget'];
			}
		}
		$cols[] = array(
			'tag'     => _test_news_tag($c),
			'lg'      => $lg,
			'flag'    => isset($c['props']['_bindings']['eo_visible_if']) ? $c['props']['_bindings']['eo_visible_if'] : '',
			'widgets' => $widgets,
		);
	}
	$article = null;
	foreach ($cols as $i => $c) {
		if ($c['tag'] === 'article') {
			$article = $i;
		}
	}
	pg_assert_true($article !== null, 'the row holds the article');
	pg_assert_same('8', $cols[$article]['lg'], 'the article is col-lg-8');
	pg_assert_true(isset($cols[$article + 1]), 'a column follows the article');
	$side = $cols[$article + 1];
	pg_assert_same('4', $side['lg'], 'the column beside the article is col-lg-4');
	pg_assert_same(array('most_read'), $side['widgets'], 'the column beside the article holds the most read');
	pg_assert_same('record_found', $side['flag'], 'the most read shows with a found article');

	$pages = _test_news_pages();
	$placed = array();
	foreach (_test_news_nodes(_test_news_resolve($pages['blog_post']['tree'])) as $n) {
		if (isset($n['type']) && $n['type'] === 'widget') {
			$placed[] = $n['props']['_widget'];
		}
	}
	pg_assert_same(1, count(array_keys($placed, 'most_read', true)), 'the article page places the most read once');
	$comment_cols = null;
	foreach (_test_news_nodes($pages['blog_post']['tree']) as $n) {
		if (isset($n['type']) && $n['type'] === 'row') {
			foreach (_test_news_nodes($n) as $m) {
				if (isset($m['props']['regionType']) && $m['props']['regionType'] === 'comments_block') {
					$comment_cols = $n['children'];
				}
			}
		}
	}
	pg_assert_true(is_array($comment_cols), 'the comments sit in a row');
	pg_assert_same(1, count($comment_cols), 'the comments are alone in their row');
	pg_assert_same('8', (string) $comment_cols[0]['props']['lg'], 'the comments keep the article column');
}

// The newsletter forms: the consent and the CAPTCHA come before the
// Subscribe button, so the button is the last thing filled in.
function test_news_newsletter_button_last()
{
	$tpl = _test_news_tpl();
	foreach (array('newsletter_form', 'newsletter_box', 'newsletter_band') as $key) {
		$order = array();
		foreach (_test_news_nodes($tpl['widgets'][$key]['tree']) as $n) {
			$attrs = _test_news_attrs($n);
			if (_test_news_tag($n) === 'input' && isset($attrs['type']) && $attrs['type'] === 'checkbox') {
				$order[] = 'consent';
			} elseif (isset($n['props']['_bindings']['section']) && $n['props']['_bindings']['section'] === 'captcha') {
				$order[] = 'captcha';
			} elseif (_test_news_tag($n) === 'button' && isset($attrs['type']) && $attrs['type'] === 'submit') {
				$order[] = 'submit';
			}
		}
		pg_assert_same(array('consent', 'captcha', 'submit'), $order, $key . ': consent, CAPTCHA, then Subscribe');
	}
}

// The front page asks for tips once: the tile among the sections.
function test_news_home_one_tip_call()
{
	$pages = _test_news_pages();
	$calls = 0;
	foreach (_test_news_nodes($pages['home']['tree']) as $n) {
		if (isset($n['props']['href']) && $n['props']['href'] === '{{page:tips}}') {
			$calls++;
		}
	}
	pg_assert_same(1, $calls, 'one link to the tips page in the front page\'s own layout');
}
