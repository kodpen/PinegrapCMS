<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the comments widget in the visual designer: the page comment
 * settings the editor reads and writes (pg_comment_editor_keys(), the save,
 * the templates), the Preview's page settings and audience, the canvas
 * states, sample comments and thread flag twin (under Node), the save
 * warnings about comments that are not drawn, and turning the fixed
 * comments block into the widget and back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comments_widget_designer_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Whether the functions a test calls exist; a missing one fails the test
// once instead of stopping the run.
function comments_widget_designer_test_has($names)
{
	$ok = true;

	foreach ((array) $names as $name) {
		$ok = pg_assert_true(function_exists($name), $name . '() exists') && $ok;
	}

	return $ok;
}

// The source of a PHP function.
function comments_widget_designer_test_php_body($name)
{
	if (!function_exists($name)) {
		return '';
	}

	$fn = new ReflectionFunction($name);
	$lines = file($fn->getFileName());

	return implode('', array_slice($lines, $fn->getStartLine() - 1, $fn->getEndLine() - $fn->getStartLine() + 1));
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function comments_widget_designer_test_js_function($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs functions of style_designer.js and a script under Node. Returns the
// decoded JSON the script prints, or null when Node is missing (the test is
// then skipped) or a function is missing (counted as a failure).
function comments_widget_designer_test_node($names, $script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$js = comments_widget_designer_test_source('assets/js/style_designer.js');
	$code_js = '';
	$found = true;

	foreach ($names as $name) {
		$body = comments_widget_designer_test_js_function($js, $name);
		$found = pg_assert_true($body !== '', 'style_designer.js has ' . $name . '()') && $found;
		$code_js .= $body . "\n";
	}

	if (!$found) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgcwd');
	file_put_contents($file, $code_js . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);
	pg_assert_true(is_array($res), 'the script answers');

	return is_array($res) ? $res : null;
}

// The keys a JS array literal `var NAME = [...]` lists, in order.
function comments_widget_designer_test_js_list($js, $name)
{
	if (preg_match('/var ' . preg_quote($name, '/') . ' = \[(.*?)\];/s', $js, $m) !== 1) {
		return array();
	}

	preg_match_all("/'([a-z_]+)'/", $m[1], $keys);

	return $keys[1];
}

// The keys a JS object literal `var NAME = {...};` lists.
function comments_widget_designer_test_js_object_keys($js, $name)
{
	if (preg_match('/var ' . preg_quote($name, '/') . ' = \{(.*?)\};/s', $js, $m) !== 1) {
		return array();
	}

	preg_match_all('/\b([a-z_]+):/', $m[1], $keys);

	return $keys[1];
}

// -- the panel --------------------------------------------------------------------

// The comment settings section is shared by the fixed block and the widget,
// the widget's display options go through the generic handler, inner nodes
// get a shortcut to the settings, and a changed setting reaches the canvas
// and the live preview.
function test_comments_widget_designer_panel_wiring()
{
	$js = comments_widget_designer_test_source('assets/js/style_designer.js');

	$sect = comments_widget_designer_test_js_function($js, '_sdCommentSettingsSect');
	pg_assert_true($sect !== '', '_sdCommentSettingsSect is found');
	foreach (array("_cmtSwitch('pg_comments',", 'pg_comments_email_page', 'pg_comments_notify_email', 'sd-cmt-settings',
		"_sdT('Comment settings for {var}'", "_sdT('Saved with this page; other pages that show this widget keep their own.')") as $needle) {
		pg_assert_contains($needle, $sect, 'the settings section has ' . $needle);
	}

	$display = comments_widget_designer_test_js_function($js, '_sdCommentsDisplaySect');
	pg_assert_true($display !== '', '_sdCommentsDisplaySect is found');
	pg_assert_false(strpos($display, "_sdT('Order')") !== false, 'the order row is not the shop\'s "Order"');

	$region = comments_widget_designer_test_js_function($js, 'propsRegion');
	pg_assert_contains('_sdCommentSettingsSect(', $region, 'the region panel uses the shared section');
	pg_assert_false(strpos($region, 'data-cmt-prop') !== false, 'and keeps no copy of it');

	$ref = comments_widget_designer_test_js_function($js, 'propsSharedRef');
	pg_assert_contains('_sdCommentSettingsSect({ widget: true })', $ref, 'the widget panel uses the shared section');
	pg_assert_contains('_sdCommentsDisplaySect(', $ref, 'and the display options');

	$props = comments_widget_designer_test_js_function($js, 'renderProperties');
	pg_assert_contains('_sdCommentsSettingsShortcut(', $props, 'the shortcut is offered on inner nodes');
	pg_assert_contains("'sd-cmt-settings'", $props, 'and opens the settings section');
	pg_assert_contains("_sdOpenWidgetSection(sid, 'sd-sw-cf-source')", comments_widget_designer_test_js_function($js, '_cfOpenFormSettings'), 'the form shortcut shares the opener');

	pg_assert_contains('_sdLivePreviewSchedule()', comments_widget_designer_test_js_function($js, '_setCmtVal'), 'a changed setting refreshes the live preview');
	pg_assert_contains('_sdCommentSettingsChanged(', comments_widget_designer_test_js_function($js, 'bindPropEvents'), 'and redraws the canvas');
}

// The display options as the panel draws them: every option a select, the
// stored value selected (as pg_comments_widget_options() reads it), the
// reply depth only for threaded replies.
function test_comments_widget_designer_display_section()
{
	$script = <<<'JS'
function _sdT(s) { return s; }
function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); }
function sect(icon, title, body) { return '<section data-icon="' + icon + '" data-title="' + title + '">' + body + '</section>'; }
function row(label, ctrl) { return '<div class="row" data-label="' + label + '">' + ctrl + '</div>'; }
var out = {
    defaults: _sdCommentsDisplaySect(5, { regionType: 'comments' }),
    stored: _sdCommentsDisplaySect(5, { regionType: 'comments', order: 'newest', featured: 'filter', layout: 'threaded', max_depth: 7, per_page: '20' }),
    odd: _sdCommentsDisplaySect(5, { regionType: 'comments', order: 'x', featured: 3, layout: 'threaded', max_depth: '2', per_page: 15 })
};
process.stdout.write(JSON.stringify(out));
JS;

	$res = comments_widget_designer_test_node(array('_sdCommentsDisplaySect'), $script);
	if ($res === null) {
		return;
	}

	$selected = function ($html, $key) {
		$from = strpos($html, 'data-sw-cfg="' . $key . '"');
		if ($from === false) {
			return null;
		}
		$select = substr($html, $from, strpos($html, '</select>', $from) - $from);
		return (preg_match('/<option value="([^"]*)" selected>/', $select, $m) === 1) ? $m[1] : '';
	};

	foreach (array('order' => 'oldest,newest', 'featured' => 'first,filter,none', 'layout' => 'flat,threaded', 'per_page' => '0,10,20,50') as $key => $choices) {
		pg_assert_contains('data-sw-id="5" data-sw-cfg="' . $key . '" data-sw-cfg-kind="choice" data-sw-cfg-choices="' . $choices . '"', $res['defaults'], $key . ' is a choice');
	}
	pg_assert_contains('data-sw-cfg="max_depth" data-sw-cfg-kind="choice" data-sw-cfg-choices="1,2,3"', $res['stored'], 'the depth is a choice');
	pg_assert_contains('data-title="Display"', $res['defaults'], 'the section');
	pg_assert_contains('data-label="Comment order"', $res['defaults'], 'the order row is not the shop\'s "Order"');

	pg_assert_same(array('oldest', 'first', 'flat', null, '0'), array($selected($res['defaults'], 'order'), $selected($res['defaults'], 'featured'),
		$selected($res['defaults'], 'layout'), $selected($res['defaults'], 'max_depth'), $selected($res['defaults'], 'per_page')), 'the defaults; no depth for flat replies');
	pg_assert_same(array('newest', 'filter', 'threaded', '3', '20'), array($selected($res['stored'], 'order'), $selected($res['stored'], 'featured'),
		$selected($res['stored'], 'layout'), $selected($res['stored'], 'max_depth'), $selected($res['stored'], 'per_page')), 'stored values; the depth clamped');
	pg_assert_same(array('oldest', 'first', '2', '0'), array($selected($res['odd'], 'order'), $selected($res['odd'], 'featured'),
		$selected($res['odd'], 'max_depth'), $selected($res['odd'], 'per_page')), 'what the server reads as the default');
}

// -- the page settings the editor reads and writes ------------------------------

// Every comment setting of the editor names its page columns.
function test_comments_widget_designer_editor_keys_name_the_columns()
{
	if (!comments_widget_designer_test_has('pg_comment_editor_keys')) {
		return;
	}

	$keys = pg_comment_editor_keys();
	pg_assert_same(15, count($keys), 'fifteen settings');

	$columns = array();
	foreach ($keys as $key => $def) {
		pg_assert_true(strpos($key, 'pg_comments') === 0, $key . ' is an editor key');
		pg_assert_true(in_array($def['kind'], array('flag', 'page', 'text'), true), $key . ' has a kind');
		foreach ($def['columns'] as $column) {
			$columns[$column] = $key;
		}
	}

	foreach (array(
		'comments_allow_file_attachments' => 'pg_comments_attachments',
		'comments_allow_user_to_select_name' => 'pg_comments_select_name',
		'comments_message' => 'pg_comments_message',
		'comments_disallow_new_comment_message' => 'pg_comments_closed_message',
		'comments_watchers_managed_by_submitter' => 'pg_comments_watchers_managed',
		'comments_submitter_email_page_id' => 'pg_comments_email_page',
		'comments_watcher_email_page_id' => 'pg_comments_email_page',
		'comments_submitter_email_subject' => 'pg_comments_email_subject',
		'comments_watcher_email_subject' => 'pg_comments_email_subject',
	) as $column => $key) {
		pg_assert_same($key, isset($columns[$column]) ? $columns[$column] : null, $column);
	}

	pg_assert_same('flag', $keys['pg_comments_attachments']['kind'], 'attachments is a switch');
	pg_assert_same('text', $keys['pg_comments_closed_message']['kind'], 'the closed message is text');
	pg_assert_same(255, $keys['pg_comments_message']['max'], 'the intro message is cut at 255');
	pg_assert_same(100, $keys['pg_comments_label']['max'], 'the label is cut at 100');
}

// A switch is 1 only for 1, '1' and true.
function test_comments_widget_designer_editor_columns_flags()
{
	if (!comments_widget_designer_test_has('pg_comment_editor_columns')) {
		return;
	}

	foreach (array(1, '1', true) as $on) {
		pg_assert_same(array('comments_rating' => 1), pg_comment_editor_columns(array('pg_comments_rating' => $on)), 'on: ' . var_export($on, true));
	}
	foreach (array(0, '0', 'x', '', array(1), null, false, 2) as $off) {
		pg_assert_same(array('comments_rating' => 0), pg_comment_editor_columns(array('pg_comments_rating' => $off)), 'off: ' . var_export($off, true));
	}

	pg_assert_same(array('comments_allow_file_attachments' => 1), pg_comment_editor_columns(array('pg_comments_attachments' => '1')), 'attachments');
	pg_assert_same(array('comments_allow_user_to_select_name' => 1), pg_comment_editor_columns(array('pg_comments_select_name' => 1)), 'name choice');
	pg_assert_same(array('comments_watchers_managed_by_submitter' => 0), pg_comment_editor_columns(array('pg_comments_watchers_managed' => '0')), 'watchers');
}

// The e-mail page fills both page columns; an unsaved tab, a negative
// number and anything that is not a value read as none.
function test_comments_widget_designer_editor_columns_page()
{
	if (!comments_widget_designer_test_has('pg_comment_editor_columns')) {
		return;
	}

	$both = function ($id) {
		return array('comments_submitter_email_page_id' => $id, 'comments_watcher_email_page_id' => $id);
	};

	pg_assert_same($both(12), pg_comment_editor_columns(array('pg_comments_email_page' => 12)), 'a page id');
	pg_assert_same($both(12), pg_comment_editor_columns(array('pg_comments_email_page' => '12')), 'a page id as text');
	foreach (array('tab:p3', -4, array(5), null, 'x', '') as $none) {
		pg_assert_same($both(0), pg_comment_editor_columns(array('pg_comments_email_page' => $none)), 'none: ' . var_export($none, true));
	}
}

// Texts are trimmed and cut, by characters, at the column's length.
function test_comments_widget_designer_editor_columns_texts()
{
	if (!comments_widget_designer_test_has('pg_comment_editor_columns')) {
		return;
	}

	$subject = pg_comment_editor_columns(array('pg_comments_email_subject' => '  New reply  '));
	pg_assert_same(array('comments_submitter_email_subject' => 'New reply', 'comments_watcher_email_subject' => 'New reply'), $subject, 'subject in both columns');

	$long = pg_comment_editor_columns(array('pg_comments_email_subject' => str_repeat('a', 300)));
	pg_assert_same(255, strlen($long['comments_submitter_email_subject']), 'subject cut at 255');

	$notify = pg_comment_editor_columns(array('pg_comments_notify_email' => str_repeat('b', 120)));
	pg_assert_same(100, strlen($notify['comments_administrator_email_to_email_address']), 'notify cut at 100');

	$label = pg_comment_editor_columns(array('pg_comments_label' => ' ' . str_repeat('c', 120)));
	pg_assert_same(100, strlen($label['comments_label']), 'label cut at 100');

	$message = pg_comment_editor_columns(array('pg_comments_message' => '  ' . str_repeat('ş', 300) . '  '));
	pg_assert_same(255, mb_strlen($message['comments_message']), 'intro message cut at 255 characters');
	pg_assert_same(str_repeat('ş', 255), $message['comments_message'], 'a multibyte text is cut on a character');

	$closed = pg_comment_editor_columns(array('pg_comments_closed_message' => "  Closed for now.\n"));
	pg_assert_same(array('comments_disallow_new_comment_message' => 'Closed for now.'), $closed, 'closed message trimmed');

	pg_assert_same(array('comments_message' => ''), pg_comment_editor_columns(array('pg_comments_message' => array('x'))), 'not a value reads empty');
	pg_assert_same(array('comments_message' => '12'), pg_comment_editor_columns(array('pg_comments_message' => 12)), 'a number reads as its text');
}

// Only the keys sent are written; a key that is not a comment setting is
// left alone.
function test_comments_widget_designer_editor_columns_unknown_and_absent()
{
	if (!comments_widget_designer_test_has('pg_comment_editor_columns')) {
		return;
	}

	pg_assert_same(array(), pg_comment_editor_columns(array()), 'nothing sent');
	pg_assert_same(array(), pg_comment_editor_columns('x'), 'not an array');
	pg_assert_same(array('comments_label' => 'Reply'), pg_comment_editor_columns(array('pg_comments_label' => 'Reply', 'page_name' => 'x', 'comments' => 1)), 'unknown keys ignored');
}

// A new page gets an empty closed message unless the save writes one: the
// INSERT never names the column twice.
function test_comments_widget_designer_page_insert_defaults()
{
	if (!comments_widget_designer_test_has('pg_designer_page_insert_defaults')) {
		return;
	}

	pg_assert_same(array('comments_disallow_new_comment_message' => "''"), pg_designer_page_insert_defaults(array()), 'no closed message sent');
	pg_assert_same(array('comments_disallow_new_comment_message' => "''"), pg_designer_page_insert_defaults(array('comments_message' => 'x')), 'another column sent');
	pg_assert_same(array(), pg_designer_page_insert_defaults(array('comments_disallow_new_comment_message' => 'Closed')), 'the save writes it');
	pg_assert_same(array(), pg_designer_page_insert_defaults(array('comments_disallow_new_comment_message' => '')), 'an empty one too');
}

// A template's per-page comments: the old keys keep their meaning, the
// four new ones map through; the watcher setting is not a template key.
function test_comments_widget_designer_template_page_comments()
{
	if (!comments_widget_designer_test_has('pg_design_template_page_comments')) {
		return;
	}

	$vars = array('tabs' => array('replies' => 'tplabc_replies'), 'pages' => array(), 'folders' => array(), 'site_email' => 'hello@example.com');

	$none = pg_design_template_page_comments(array(), $vars);
	pg_assert_same(0, $none['pg_comments'], 'no comments array: off');
	pg_assert_same(1, $none['pg_comments_allow_new'], 'new comments allowed by default');
	pg_assert_same(0, $none['pg_comments_attachments'], 'no attachments');
	pg_assert_same(0, $none['pg_comments_select_name'], 'no name choice');
	pg_assert_same('', $none['pg_comments_message'], 'no intro message');
	pg_assert_same('', $none['pg_comments_closed_message'], 'no closed message');
	pg_assert_same(0, $none['pg_comments_email_page'], 'no e-mail page');
	pg_assert_false(array_key_exists('pg_comments_watchers_managed', $none), 'watchers managed is not a template key');

	$c = pg_design_template_page_comments(array(
		'label' => 'Reply', 'allow_new' => 0, 'rating' => 1, 'auto_publish' => 1, 'show_date' => 1, 'login' => 1,
		'email_page' => '{{tab:replies}}', 'email_subject' => 'New reply', 'notify_email' => '{{site_email}}',
		'attachments' => 1, 'select_name' => true, 'message' => 'Be kind.', 'closed_message' => 'Closed.',
	), $vars);
	pg_assert_same(1, $c['pg_comments'], 'a comments array: on');
	pg_assert_same('Reply', $c['pg_comments_label']);
	pg_assert_same(0, $c['pg_comments_allow_new']);
	pg_assert_same(1, $c['pg_comments_rating']);
	pg_assert_same(1, $c['pg_comments_auto_publish']);
	pg_assert_same(1, $c['pg_comments_show_date']);
	pg_assert_same(1, $c['pg_comments_login']);
	pg_assert_same('tab:tplabc_replies', $c['pg_comments_email_page'], 'the e-mail page is filled from the tabs');
	pg_assert_same('New reply', $c['pg_comments_email_subject']);
	pg_assert_same('hello@example.com', $c['pg_comments_notify_email'], 'the staff address is filled');
	pg_assert_same(1, $c['pg_comments_attachments']);
	pg_assert_same(1, $c['pg_comments_select_name']);
	pg_assert_same('Be kind.', $c['pg_comments_message']);
	pg_assert_same('Closed.', $c['pg_comments_closed_message']);
}

// The editor's lists of the settings follow the PHP map: one list for the
// hidden inputs, every key among the page fields, the switches among the
// boolean fields.
function test_comments_widget_designer_js_field_lists()
{
	if (!comments_widget_designer_test_has('pg_comment_editor_keys')) {
		return;
	}

	$js = comments_widget_designer_test_source('assets/js/style_designer.js');
	$keys = pg_comment_editor_keys();
	$cmt = comments_widget_designer_test_js_list($js, '_PG_CMT_FIELDS');
	pg_assert_same(array_keys($keys), $cmt, '_PG_CMT_FIELDS follows pg_comment_editor_keys()');

	$page_fields = comments_widget_designer_test_js_list($js, '_PG_PAGE_FIELDS');
	foreach (array_keys($keys) as $key) {
		pg_assert_true(in_array($key, $page_fields, true), $key . ' is a page field');
	}

	$bool = array_values(array_filter(comments_widget_designer_test_js_object_keys($js, '_PG_BOOL_FIELDS'), function ($k) {
		return strpos($k, 'pg_comments') === 0;
	}));
	$flags = array_keys(array_filter($keys, function ($def) {
		return $def['kind'] === 'flag';
	}));
	sort($bool);
	sort($flags);
	pg_assert_same($flags, $bool, '_PG_BOOL_FIELDS holds the switches');

	foreach (array('_pgReadFieldsIntoPage', '_pgLoadPageIntoFields') as $name) {
		$body = comments_widget_designer_test_js_function($js, $name);
		pg_assert_contains('_PG_CMT_FIELDS.forEach', $body, $name . ' reads the one list');
		pg_assert_false(strpos($body, "'pg_comments_notify_email'") !== false, $name . ' keeps no list of its own');
	}
}

// The editor screen has a hidden input for every setting, and both page
// lists it sends carry every one.
function test_comments_widget_designer_screen_wiring()
{
	if (!comments_widget_designer_test_has('pg_comment_editor_keys')) {
		return;
	}

	$screen = comments_widget_designer_test_source('includes/designer_screen.php');
	$parts = explode('$js_pages[] = array(', $screen);
	pg_assert_same(3, count($parts), 'two page lists');

	foreach (array_keys(pg_comment_editor_keys()) as $key) {
		pg_assert_contains('<input type="hidden" name="' . $key . '"', $screen, 'hidden input ' . $key);
		for ($i = 1; $i < count($parts); $i++) {
			$literal = substr($parts[$i], 0, (int) strpos($parts[$i], "\n        );"));
			pg_assert_contains("'" . $key . "'", $literal, 'page list ' . $i . ' carries ' . $key);
		}
	}
}

// Every place the editor builds a page object names the new settings.
function test_comments_widget_designer_page_literals()
{
	$js = comments_widget_designer_test_source('assets/js/style_designer.js');
	$new = array('pg_comments_attachments', 'pg_comments_select_name', 'pg_comments_message', 'pg_comments_closed_message', 'pg_comments_watchers_managed');

	foreach (array('_pgTabsAddNew', '_pgTabsDuplicateActive', '_pgTabsAddCopy', '_pgTabsAttachLoaded', '_pgImportApplyPages', '_pgTemplateApply', '_pgTabsInit') as $name) {
		$body = comments_widget_designer_test_js_function($js, $name);
		pg_assert_true($body !== '', $name . ' is found');
		foreach ($new as $key) {
			pg_assert_true(preg_match('/\b' . $key . ': /', $body) === 1, $name . ' names ' . $key);
		}
	}

	// _pgTabsInit builds the stored pages and the blank one.
	$init = comments_widget_designer_test_js_function($js, '_pgTabsInit');
	foreach ($new as $key) {
		pg_assert_true(preg_match_all('/\b' . $key . ': /', $init) >= 2, '_pgTabsInit names ' . $key . ' in both pages');
	}
}

// The pages the editor opens carry every setting; the save writes the new
// columns, and its INSERT names the closed message only through the
// defaults.
function test_comments_widget_designer_load_and_save_wiring()
{
	if (!comments_widget_designer_test_has('pg_comment_editor_keys')) {
		return;
	}

	$load = comments_widget_designer_test_php_body('pg_designer_load_pages');
	foreach (array_keys(pg_comment_editor_keys()) as $key) {
		pg_assert_contains("'" . $key . "'", $load, 'pg_designer_load_pages returns ' . $key);
	}
	foreach (array('page.comments_message', 'page.comments_disallow_new_comment_message', 'page.comments_allow_file_attachments',
		'page.comments_allow_user_to_select_name', 'page.comments_watchers_managed_by_submitter') as $column) {
		pg_assert_contains($column, $load, 'pg_designer_load_pages reads ' . $column);
	}

	$save = comments_widget_designer_test_php_body('pg_designer_save_page');
	pg_assert_contains('pg_comment_editor_columns(', $save, 'the save writes through the key map');
	pg_assert_contains('pg_designer_page_insert_defaults(', $save, 'the INSERT takes its defaults');
	$insert = substr($save, (int) strpos($save, 'INSERT INTO page'));
	pg_assert_false(strpos($insert, 'comments_disallow_new_comment_message') !== false, 'the INSERT does not name the closed message itself');

	$export = comments_widget_designer_test_php_body('pg_dtc_page_comments');
	foreach (array('attachments', 'select_name', 'message', 'closed_message') as $key) {
		pg_assert_contains("'" . $key . "'", $export, 'the export writes ' . $key);
	}
}

// Opening a template copies every comment setting prepare gives a page.
function test_comments_widget_designer_template_install_copies_keys()
{
	if (!comments_widget_designer_test_has('pg_design_template_page_comments')) {
		return;
	}

	$install = comments_widget_designer_test_php_body('pg_design_template_install');
	$prepare = comments_widget_designer_test_php_body('pg_design_template_prepare');
	pg_assert_contains('pg_design_template_page_comments(', $prepare, 'prepare builds the comments with the helper');

	foreach (array_keys(pg_design_template_page_comments(array(), array())) as $key) {
		pg_assert_contains("'" . $key . "'", $install, 'install copies ' . $key);
	}
}

// The canvas fades the attachment box, the name select and the date while
// the page has them off, as the site leaves them out.
function test_comments_widget_designer_binding_off_page_fields()
{
	$script = <<<'JS'
var dragData = null, _parentIndex = null, _sysWidgetRenderTreeRoot = null, sdDesign = { siteFlags: {} };
function _sdT(s) { return s; }
var _cmt = { pg_comments: '1', pg_comments_attachments: '0', pg_comments_select_name: '0', pg_comments_show_date: '0' };
function _getCmtVal(k) { return _cmt[k] || ''; }
function n(type, props, kids) { return { type: type, props: props || {}, children: kids || [] }; }
var file = n('semantic', { tag: 'input', _bindings: { value: 'comment_file' } });
var filesBox = n('semantic', { tag: 'div', _bindings: { eo_visible_if: 'files_on' } }, [n('semantic', { tag: 'span', text: 'x' })]);
var select = n('semantic', { tag: 'select', _bindings: { value: 'comment_name_type' } });
var date = n('semantic', { tag: 'div', _bindings: { text: '__comment_date' } });
var datetime = n('semantic', { tag: 'span', _bindings: { text: '__comment_datetime' } });
var message = n('semantic', { tag: 'div', _bindings: { text: '__comment_message_html' } });
var tree = n('root', {}, [file, filesBox, select, date, datetime, message]);
_sysWidgetRenderTreeRoot = tree;
var cfg = { regionType: 'comments' };
var run = function () { return [file, filesBox, select, date, datetime, message].map(function (x) { return _sdCommentsBindingOff(x, cfg); }); };
var out = { off: run() };
_cmt.pg_comments_attachments = '1'; _cmt.pg_comments_select_name = '1'; _cmt.pg_comments_show_date = '1';
out.on = run();
process.stdout.write(JSON.stringify(out));
JS;

	$res = comments_widget_designer_test_node(array('_parentIndexFor', 'findParent', '_cfNodeAttr', '_sdFindByPropId', '_sdCommentsPlace', '_sdCommentsBindingOff'), $script);
	if ($res === null) {
		return;
	}

	$page = 'Off — this page\'s comment settings';
	pg_assert_same(array($page, $page, $page, $page, $page, ''), $res['off'], 'off: file, its box, name select, date, date and time; the message stays');
	pg_assert_same(array('', '', '', '', '', ''), $res['on'], 'on: nothing fades');
}

// The settings section offers the five settings the designer could not set
// before, on the fixed block and on the widget alike.
function test_comments_widget_designer_settings_rows()
{
	$sect = comments_widget_designer_test_js_function(comments_widget_designer_test_source('assets/js/style_designer.js'), '_sdCommentSettingsSect');
	foreach (array("_cmtSwitch('pg_comments_attachments',", "_cmtSwitch('pg_comments_select_name',", 'data-cmt-txt="pg_comments_message"',
		'data-cmt-txt="pg_comments_closed_message"', "_cmtSwitch('pg_comments_watchers_managed',") as $needle) {
		pg_assert_contains($needle, $sect, 'the settings section has ' . $needle);
	}
}

// -- the canvas and the Preview ---------------------------------------------------

// The editor's unsaved settings take the place of the stored ones for the
// page being previewed, and only the comment columns.
function test_comments_widget_designer_page_override()
{
	if (!comments_widget_designer_test_has('pg_comments_page_override')) {
		return;
	}

	$row = array('page_id' => '7', 'page_name' => 'topic', 'comments' => '1', 'comments_message' => 'old', 'comments_rating' => '0');
	$override = array('page_id' => 7, 'columns' => array(
		'comments_message' => 'New intro', 'comments_rating' => 1, 'page_name' => 'hacked', 'comments_label' => 'Reply',
	));

	$out = pg_comments_page_override($row, $override);
	pg_assert_same('New intro', $out['comments_message'], 'the intro is the editor\'s');
	pg_assert_same('1', $out['comments_rating'], 'stars on, as a string');
	pg_assert_same('topic', $out['page_name'], 'a column outside the comment settings is never replaced');
	pg_assert_false(array_key_exists('comments_label', $out), 'a column the row does not have is not added');
	pg_assert_same('1', $out['comments'], 'a column not sent is kept');

	pg_assert_same($row, pg_comments_page_override($row, array('page_id' => 8, 'columns' => array('comments_message' => 'x'))), 'another page');
	pg_assert_same($row, pg_comments_page_override($row, null), 'no override');
	pg_assert_same($row, pg_comments_page_override($row, 'x'), 'not an array');
	pg_assert_same($row, pg_comments_page_override($row, array('page_id' => 7)), 'no columns');
}

// The override is kept for the request until it is cleared.
function test_comments_widget_designer_preview_page_registry()
{
	if (!comments_widget_designer_test_has('pg_comments_preview_page')) {
		return;
	}

	$before = pg_comments_preview_page(array());
	pg_assert_same(null, pg_comments_preview_page(), 'cleared');

	$set = array('page_id' => 7, 'columns' => array('comments_rating' => 1));
	pg_assert_same(null, pg_comments_preview_page($set), 'the old value comes back');
	pg_assert_same($set, pg_comments_preview_page(), 'read');
	pg_assert_same($set, pg_comments_preview_page(false), 'a clear hands back the value');
	pg_assert_same(null, pg_comments_preview_page(), 'cleared again');
	pg_assert_same(null, pg_comments_preview_page(array('page_id' => 0, 'columns' => array())), 'no page clears');

	if (is_array($before) && $before) {
		pg_comments_preview_page($before);
	}
}

// Who the widget draws for: a live visitor uses the session; the search
// index and a background render (the Preview, the SEO pass) are anonymous.
function test_comments_widget_designer_audience_table()
{
	if (!comments_widget_designer_test_has('pg_comments_widget_audience')) {
		return;
	}

	pg_assert_same(array('staff' => true, 'signed_in' => true, 'session' => true), pg_comments_widget_audience(false, false, true, true), 'live staff');
	pg_assert_same(array('staff' => false, 'signed_in' => false, 'session' => false), pg_comments_widget_audience(true, false, true, true), 'search index');
	pg_assert_same(array('staff' => false, 'signed_in' => false, 'session' => false), pg_comments_widget_audience(false, true, true, true), 'background render');
	pg_assert_same(array('staff' => false, 'signed_in' => false, 'session' => true), pg_comments_widget_audience(false, false, false, false), 'signed-out visitor');
	pg_assert_same(array('staff' => false, 'signed_in' => true, 'session' => true), pg_comments_widget_audience(false, false, false, true), 'member');
}

// The Preview draws the comments of the saved page with the editor's
// settings; the renderer asks who it draws for; the endpoint ignores a page
// the user may not know.
function test_comments_widget_designer_preview_wiring()
{
	$preview = comments_widget_designer_test_php_body('pg_designer_preview_widgets');
	pg_assert_true($preview !== '', 'pg_designer_preview_widgets is found');
	foreach (array('pg_sw_render_context(', 'pg_comments_widget_pages(', '_pg_preview_newest_record_code(', 'pg_comment_editor_columns(') as $call) {
		pg_assert_contains($call, $preview, 'the Preview calls ' . $call);
	}
	pg_assert_true(substr_count($preview, 'pg_comments_preview_page(') >= 2, 'the Preview sets the page settings and clears them');
	pg_assert_true(function_exists('_pg_preview_newest_record_code'), 'the newest record helper exists');
	if (function_exists('pg_designer_preview_widgets')) {
		$fn = new ReflectionFunction('pg_designer_preview_widgets');
		pg_assert_same(3, $fn->getNumberOfParameters(), 'the Preview takes the comment settings');
	}

	$render = comments_widget_designer_test_php_body('_render_system_widget_comments');
	pg_assert_contains('pg_comments_widget_audience(', $render, 'the renderer asks who it draws for');
	pg_assert_contains('pg_comments_page_override(', $render, 'the renderer takes the Preview settings');
	pg_assert_contains('pg_seo_rendering()', $render, 'a background render is anonymous');

	$panel = comments_widget_designer_test_source('includes/panel/designer.php');
	$case = substr($panel, (int) strpos($panel, "case 'preview_widgets':"), 900);
	pg_assert_contains("comment_settings", $case, 'the endpoint reads the comment settings');
	pg_assert_contains('pg_designer_page_access(', $case, 'the endpoint checks the page');
}

// The canvas twin of the thread flags gives what the server gives.
function test_comments_widget_designer_thread_flags_twin()
{
	$sets = array(
		'default' => array(),
		'closed' => array('allow_new' => false, 'count' => 3),
		'must sign in' => array('allow_new' => true, 'require_login' => true, 'signed_in' => false),
		'member, chosen name' => array('allow_new' => 1, 'signed_in' => 1, 'name_mode' => 'chosen', 'watching_on' => 1, 'count' => '4'),
		'member, fixed name, watching' => array('allow_new' => '1', 'signed_in' => true, 'name_mode' => 'fixed', 'watching_on' => true, 'is_watcher' => true),
		'staff with watchers' => array('allow_new' => true, 'signed_in' => true, 'staff' => true, 'watching_on' => true, 'manage_watchers' => true, 'name_mode' => 'typed', 'count' => 2),
		'search index' => array('allow_new' => true, 'signed_in' => true, 'staff' => true, 'search_index' => true, 'count' => 5, 'watching_on' => true),
		'replying' => array('allow_new' => true, 'replying' => true, 'name_mode' => 'typed', 'count' => 1),
		'featured none' => array('featured_mode' => 'none', 'has_featured' => true, 'count' => 2, 'featured_view' => '0'),
		'pages 3' => array('pages' => 3, 'count' => 40, 'featured_view' => true, 'has_featured' => 1, 'rating_on' => 1, 'files_on' => ''),
		'odd values' => array('pages' => '2', 'count' => '0', 'allow_new' => 'yes', 'submitter_notified' => 1, 'signed_in' => 1, 'watching_on' => 1),
	);

	$res = comments_widget_designer_test_node(array('_sdCommentsThreadFlags'),
		'var sets = ' . json_encode($sets) . ";\nvar out = {};\nObject.keys(sets).forEach(function (k) { out[k] = _sdCommentsThreadFlags(sets[k]); });\nprocess.stdout.write(JSON.stringify(out));\n");
	if ($res === null) {
		return;
	}

	foreach ($sets as $name => $facts) {
		$php = pg_comments_widget_thread_flags($facts);
		pg_assert_same(22, count($php), $name . ': 22 flags');
		$js = isset($res[$name]) ? $res[$name] : array();
		ksort($php);
		ksort($js);
		pg_assert_same($php, $js, $name);
	}
}

// The script that runs the canvas states: a small tree in the shape of the
// starter, the stubs the functions read, and the twin.
function comments_widget_designer_test_canvas_script($body)
{
	return <<<'JS'
function _sdT(s, v) { return (v === undefined) ? s : String(s).replace(/\{var(?::1)?\}/g, v); }
function esc(s) { return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"); }
var _cmt = { pg_comments: '1', pg_comments_rating: '1', pg_comments_select_name: '0', pg_comments_label: '' };
function _getCmtVal(k) { return _cmt[k] || ''; }
var _sdCommentsCanvasState = 'comments';
var _sdCommentSample = null;
function n(type, props, kids, name) { props = props || {}; props.customName = name; return { type: type, props: props, children: kids || [] }; }
function flag(f, kids, name) { return n('semantic', { tag: 'div', _bindings: { eo_visible_if: f } }, kids, name); }
var named = {};
function keep(node) { named[node.props.customName] = node; return node; }
var loop = keep(n('loop_area', {}, [n('semantic', { tag: 'div' }, [
    keep(flag('comment_is_pending', [], 'status')),
    keep(flag('comment_has_quote', [], 'quote')),
    keep(flag('is_staff', [], 'staff name')),
    keep(n('semantic', { tag: 'div' }, [], 'card text'))
], 'card')], 'loop'));
var tree = n('root', {}, [n('container', {}, [
    keep(flag('has_comments', [], 'heading')),
    n('semantic', { tag: 'div' }, [loop], 'list'),
    keep(flag('no_comments', [], 'empty')),
    keep(flag('comments_open', [], 'add heading')),
    keep(flag('comments_closed', [], 'closed')),
    keep(flag('must_sign_in', [], 'sign in')),
    keep(n('semantic', { tag: 'form', _bindings: { action: 'comment_form', eo_visible_if: 'can_comment' } }, [
        keep(flag('is_replying', [keep(n('semantic', { tag: 'span' }, [], 'replying text'))], 'replying')),
        keep(flag('rating_on', [], 'rating')),
        keep(flag('name_is_chosen', [], 'name select'))
    ], 'form')),
    keep(flag('is_staff', [], 'staff controls')),
    keep(n('semantic', { tag: 'div' }, [], 'plain'))
])]);
var cfg = { regionType: 'comments', featured: 'first', per_page: 0, layout: 'flat' };
var out = {};
JS
		. "\n" . $body . "\nprocess.stdout.write(JSON.stringify(out));\n";
}

// Each state hides what that visitor would not get; a switched-off feature
// is never hidden (it fades); a row flag hides only in a sample comment.
function test_comments_widget_designer_canvas_flag_off_and_state_for()
{
	$body = <<<'JS'
out.states = _sdCommentsCanvasStates().map(function (s) { return s[0]; });
['comments', 'closed', 'empty', 'staff', 'member', 'sign_in', 'replying'].forEach(function (st) {
    _sdCommentsCanvasState = st;
    var r = {};
    Object.keys(named).forEach(function (k) { r[k] = _sdCommentsFlagOff(named[k], cfg); });
    out[st] = r;
});
_cmt.pg_comments_select_name = '1';
_sdCommentsCanvasState = 'member';
out.member_chosen = _sdCommentsFlagOff(named['name select'], cfg);
_cmt.pg_comments_select_name = '0';
_sdCommentsCanvasState = 'comments';
_sdCommentSample = { key: 'pending', flags: { comment_has_quote: false, comment_is_pending: true }, values: {} };
out.sample_quote = _sdCommentsFlagOff(named['quote'], cfg);
out.sample_status = _sdCommentsFlagOff(named['status'], cfg);
_sdCommentSample = null;
out.other_kind = _sdCommentsFlagOff(named['closed'], { regionType: 'login_region' });
var foreign = n('semantic', { tag: 'div', _bindings: { eo_visible_if: 'is_staff' } }, [], 'foreign');
var pick = function (current, node) { _sdCommentsCanvasState = current; return _sdCommentsStateFor(tree, node, cfg); };
out.pick = {
    form_from_closed: pick('closed', named['form']),
    replying: pick('comments', named['replying text']),
    sign_in: pick('comments', named['sign in']),
    staff: pick('comments', named['staff controls']),
    card_from_empty: pick('empty', named['card text']),
    plain: pick('closed', named['plain']),
    foreign: pick('staff', foreign),
    shown_already: pick('member', named['form'])
};
JS;

	$res = comments_widget_designer_test_node(
		array('_sdCommentsThreadFlags', '_sdCommentsCanvasStates', '_sdCommentsCanvasFacts', '_sdCommentsCanvasFlags', '_sdCommentsFlagOff', '_sdCommentsStateFor'),
		comments_widget_designer_test_canvas_script($body));
	if ($res === null) {
		return;
	}

	pg_assert_same(array('comments', 'replying', 'empty', 'member', 'sign_in', 'closed', 'staff'), $res['states'], 'the states in order');

	pg_assert_true($res['closed']['add heading'], 'closed hides comments_open');
	pg_assert_true($res['closed']['form'], 'closed hides the form');
	pg_assert_false($res['closed']['closed'], 'closed shows comments_closed');
	pg_assert_true($res['comments']['closed'], 'open hides comments_closed');
	pg_assert_false($res['comments']['form'], 'open shows the form');

	pg_assert_true($res['empty']['loop'], 'no comments hides the loop area');
	pg_assert_true($res['empty']['heading'], 'no comments hides has_comments');
	pg_assert_false($res['empty']['empty'], 'no comments shows no_comments');
	pg_assert_false($res['comments']['loop'], 'comments show the loop area');
	pg_assert_true($res['comments']['empty'], 'comments hide no_comments');

	pg_assert_false($res['staff']['staff controls'], 'staff shows is_staff');
	pg_assert_true($res['comments']['staff controls'], 'a visitor does not get is_staff');
	pg_assert_true($res['comments']['staff name'], 'nor the staff name in the card');
	pg_assert_false($res['staff']['staff name'], 'staff get the staff name');

	pg_assert_false($res['comments']['quote'], 'the card shows every row part');
	pg_assert_false($res['comments']['status'], 'the card shows the status');
	pg_assert_true($res['sample_quote'], 'a sample without a quote hides it');
	pg_assert_false($res['sample_status'], 'a pending sample shows its status');

	foreach (array('comments', 'closed', 'empty', 'staff', 'member', 'sign_in', 'replying') as $state) {
		pg_assert_false($res[$state]['rating'], 'rating_on is never hidden: ' . $state);
		pg_assert_false($res[$state]['plain'], 'an unflagged node shows: ' . $state);
	}

	pg_assert_true($res['sign_in']['form'], 'must sign in hides the form');
	pg_assert_false($res['sign_in']['sign in'], 'must sign in shows the note');
	pg_assert_true($res['comments']['replying'], 'not replying hides the reply line');
	pg_assert_false($res['replying']['replying'], 'replying shows it');
	pg_assert_true($res['member']['name select'], 'a member without a name choice has a fixed name');
	pg_assert_false($res['member_chosen'], 'with the choice on, a member picks a name');
	pg_assert_false($res['other_kind'], 'another widget kind is left alone');

	pg_assert_same('comments', $res['pick']['form_from_closed'], 'the form opens the comments state');
	pg_assert_same('replying', $res['pick']['replying'], 'the reply line opens replying');
	pg_assert_same('sign_in', $res['pick']['sign_in'], 'the sign-in note opens sign in');
	pg_assert_same('staff', $res['pick']['staff'], 'staff controls open staff');
	pg_assert_same('comments', $res['pick']['card_from_empty'], 'the card opens a state with comments');
	pg_assert_same('closed', $res['pick']['plain'], 'an unflagged node keeps the state');
	pg_assert_same('staff', $res['pick']['foreign'], 'a node outside the widget keeps the state');
	pg_assert_same('member', $res['pick']['shown_already'], 'a state that shows the node is kept');
}

// The three sample comments after the card: a reply with a quote, one
// awaiting approval, one with stars and a file.
function test_comments_widget_designer_samples()
{
	$body = <<<'JS'
var by = function (list) { var o = {}; list.forEach(function (s) { o[s.key] = s; }); return o; };
out.flat = by(_sdCommentSamples({ regionType: 'comments', layout: 'flat' }, { is_staff: false }));
out.threaded = by(_sdCommentSamples({ regionType: 'comments', layout: 'threaded' }, { is_staff: false }));
out.staff = by(_sdCommentSamples({ regionType: 'comments', layout: 'flat' }, { is_staff: true }));
_cmt.pg_comments_rating = '0';
_cmt.pg_comments_label = 'Answer';
out.no_stars = by(_sdCommentSamples({ regionType: 'comments' }, { is_staff: false }));
out.keys = _sdCommentSamples({ regionType: 'comments' }, { is_staff: false }).map(function (s) { return s.key; });
Object.keys(out).forEach(function (k) {
    if (k === 'keys') return;
    Object.keys(out[k]).forEach(function (s) {
        Object.keys(out[k][s].values).forEach(function (v) {
            if (typeof out[k][s].values[v] === 'function') out[k][s].values[v] = out[k][s].values[v]();
        });
    });
});
JS;

	$res = comments_widget_designer_test_node(array('_sdCommentSamples'), comments_widget_designer_test_canvas_script($body));
	if ($res === null) {
		return;
	}

	pg_assert_same(array('reply', 'pending', 'rated'), $res['keys'], 'three samples');

	$flat = $res['flat']['reply'];
	pg_assert_true($flat['flags']['comment_is_root'], 'a flat reply is drawn as a top-level comment');
	pg_assert_false($flat['flags']['comment_is_reply'], 'not indented');
	pg_assert_true($flat['flags']['comment_has_quote'], 'it quotes the comment answered');
	pg_assert_same('0', $flat['values']['__comment_depth'], 'depth 0');
	pg_assert_same('Jane Cooper', $flat['values']['__comment_parent_name'], 'the name answered');
	pg_assert_contains('Thanks, that answered my question.', $flat['values']['__comment_message_html'], 'the message');

	$threaded = $res['threaded']['reply'];
	pg_assert_true($threaded['flags']['comment_is_reply'], 'a threaded reply is a reply');
	pg_assert_false($threaded['flags']['comment_is_root'], 'not top-level');
	pg_assert_true($threaded['flags']['comment_has_quote'], 'still quoted');
	pg_assert_same('1', $threaded['values']['__comment_depth'], 'depth 1');

	$pending = $res['flat']['pending'];
	pg_assert_true($pending['flags']['comment_is_pending'], 'pending');
	pg_assert_true($pending['flags']['comment_is_own_pending'], 'the visitor\'s own');
	pg_assert_same('Your comment is awaiting approval', $pending['values']['__comment_status'], 'its status');
	pg_assert_false($pending['flags']['comment_has_quote'], 'no quote');
	pg_assert_false($res['staff']['pending']['flags']['comment_is_own_pending'], 'staff read someone else\'s');
	pg_assert_same('Not Published', $res['staff']['pending']['values']['__comment_status'], 'staff status');
	pg_assert_same('Your answer is awaiting approval', $res['no_stars']['pending']['values']['__comment_status'], 'the page label');

	$rated = $res['flat']['rated'];
	pg_assert_true($rated['flags']['comment_has_file'], 'a file');
	pg_assert_true($rated['flags']['comment_has_rating'], 'stars while the page has them');
	pg_assert_false($res['no_stars']['rated']['flags']['comment_has_rating'], 'no stars while the page has none');
	pg_assert_same('photo.jpg', $rated['values']['__comment_file_name'], 'the file name');

	pg_assert_false($flat['flags']['comment_can_edit'], 'a visitor cannot edit');
	pg_assert_true($res['staff']['rated']['flags']['comment_can_edit'], 'staff can');
	foreach (array('comment_is_featured', 'comment_is_scheduled', 'comment_has_seller_reply', 'comment_has_badge') as $off) {
		pg_assert_false($rated['flags'][$off], $off . ' is off');
	}
}


// The login region's two chips are built as before.
function test_comments_widget_designer_session_chips_unchanged()
{
	$script = <<<'JS'
function _sdT(s) { return s; }
var _sdSessionPreview = 'signed_out';
function _sdSetSessionPreview() {}
function el(tag) {
    return { tag: tag, attrs: {}, kids: [], className: '', textContent: '', type: '',
        setAttribute: function (k, v) { this.attrs[k] = String(v); },
        appendChild: function (c) { this.kids.push(c); return c; },
        addEventListener: function () {} };
}
var doc = { createElement: el };
var w = _sdSessionChips(doc, {});
var out = { className: w.className, role: w.attrs.role, label: w.attrs['aria-label'],
    chips: w.kids.map(function (b) { return [b.tag, b.type, b.className, b.attrs['aria-pressed'], b.textContent]; }) };
process.stdout.write(JSON.stringify(out));
JS;

	$res = comments_widget_designer_test_node(array('_sdStateChips', '_sdSessionChips'), $script);
	if ($res === null) {
		return;
	}

	pg_assert_same('sd-session-chips', $res['className'], 'the group');
	pg_assert_same('group', $res['role']);
	pg_assert_same('Canvas shows', $res['label']);
	pg_assert_same(array(
		array('button', 'button', 'sd-session-chip is-on', 'true', 'Signed out'),
		array('button', 'button', 'sd-session-chip', 'false', 'Signed in'),
	), $res['chips'], 'two chips');
}

// The panel's canvas preview section and the Preview's request.
function test_comments_widget_designer_canvas_wiring()
{
	$js = comments_widget_designer_test_source('assets/js/style_designer.js');

	pg_assert_contains('_sdCommentsCanvasSect(', comments_widget_designer_test_js_function($js, 'propsSharedRef'), 'the widget panel has the canvas preview');
	$canvas = comments_widget_designer_test_js_function($js, '_sdCommentsCanvasSect');
	pg_assert_contains('sd-cmt-canvas-state', $canvas, 'the state select');
	pg_assert_contains('sd-sw-ghosts', $canvas, 'the sample switch');
	pg_assert_contains('sd-cmt-canvas-state', comments_widget_designer_test_js_function($js, '_wireSystemWidgetProps'), 'the state select is wired');

	$payload = comments_widget_designer_test_js_function($js, '_sdPreviewWidgetPayload');
	pg_assert_contains('comment_settings', $payload, 'the Preview sends the unsaved settings');
	pg_assert_contains('_sdCommentSettingsValues()', $payload, 'read from the hidden inputs');

	$ref = comments_widget_designer_test_js_function($js, 'buildSharedRefEl');
	pg_assert_contains('_sdAppendCommentSamples(', $ref, 'the samples follow the card');
	pg_assert_contains('_sdCommentsCanvasStates()', $ref, 'the band carries the state chips');
	pg_assert_contains('_sdCommentsFlagOff(', comments_widget_designer_test_js_function($js, 'buildNodeEl'), 'the canvas hides what a state leaves out');
	pg_assert_contains('_sdCommentsStateFor(', comments_widget_designer_test_js_function($js, '_sdSelectionNeedsCanvas'), 'a hidden node picked switches the state');
	pg_assert_contains('_sdCommentSample', comments_widget_designer_test_js_function($js, '_displayValueForBinding'), 'a sample\'s values are drawn');
}

// -- turning the fixed block into the widget, and the save warnings ---------------

// One tree: placements of each component and the fixed comments blocks; a
// placement's own children are not read.
function test_comments_widget_designer_tree_census()
{
	if (!comments_widget_designer_test_has('pg_comments_tree_census')) {
		return;
	}

	$ref = function ($sid, $kids = array()) {
		return array('type' => 'shared_ref', 'props' => array('sharedId' => $sid, 'sharedName' => 'w'), 'children' => $kids);
	};
	$region = function ($type) {
		return array('type' => 'region', 'props' => array('regionType' => $type, 'regionName' => ''), 'children' => array());
	};
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		$ref(5),
		array('type' => 'container', 'props' => array(), 'children' => array(
			array('type' => 'row', 'props' => array(), 'children' => array(
				array('type' => 'col', 'props' => array(), 'children' => array($ref(5), $region('comments_block'))),
			)),
			$region('page'),
		)),
		$ref(9, array($ref(11), $region('comments_block'))),
		$ref('12'),
		$ref(0),
	));

	$census = pg_comments_tree_census($tree);
	pg_assert_same(array(5 => 2, 9 => 1, 12 => 1), $census['refs'], 'placements counted, children of a placement not read');
	pg_assert_same(1, $census['blocks'], 'a block nested in containers counts; other regions do not');
	pg_assert_same(array('refs' => array(), 'blocks' => 0), pg_comments_tree_census(null), 'no tree');
	pg_assert_same(array('refs' => array(), 'blocks' => 0), pg_comments_tree_census('x'), 'not a tree');
}

// What a page shows, read through the components it places.
function test_comments_widget_designer_census_facts()
{
	if (!comments_widget_designer_test_has('pg_comments_page_census_facts')) {
		return;
	}

	$c = function ($type, $refs = array(), $blocks = 0) {
		return array('type' => $type, 'census' => array('refs' => $refs, 'blocks' => $blocks));
	};
	$top = function ($refs, $blocks = 0) {
		return array('refs' => $refs, 'blocks' => $blocks);
	};
	$components = array(
		5 => $c('comments'),
		6 => $c('comments'),
		9 => $c('', array(5 => 1)),
		20 => $c('catalog_item_view', array(), 1),
		30 => $c('form_item_view'),
		40 => $c('', array(30 => 1)),
	);

	$facts = pg_comments_page_census_facts($top(array(5 => 1)), $components);
	pg_assert_same(1, $facts['widgets'], 'one widget on the page');
	pg_assert_same(0, $facts['blocks'], 'no block');
	pg_assert_false($facts['nested_record'], 'no nested record widget');

	pg_assert_same(2, pg_comments_page_census_facts($top(array(5 => 2)), $components)['widgets'], 'placed twice');
	pg_assert_same(2, pg_comments_page_census_facts($top(array(5 => 1, 6 => 1)), $components)['widgets'], 'two widgets');
	pg_assert_same(1, pg_comments_page_census_facts($top(array(9 => 1)), $components)['widgets'], 'in a header');
	pg_assert_same(2, pg_comments_page_census_facts($top(array(9 => 1, 5 => 1)), $components)['widgets'], 'in a header and on the page');
	pg_assert_same(1, pg_comments_page_census_facts($top(array(9 => 2)), $components)['widgets'], 'a header placed twice counts its widget once');
	pg_assert_same(1, pg_comments_page_census_facts($top(array(5 => 1), 1), $components)['blocks'], 'a block on the page');
	pg_assert_same(1, pg_comments_page_census_facts($top(array(20 => 1)), $components)['blocks'], 'a block in a product widget');
	pg_assert_true(pg_comments_page_census_facts($top(array(40 => 1)), $components)['nested_record'], 'a record widget in a header');
	pg_assert_false(pg_comments_page_census_facts($top(array(30 => 1)), $components)['nested_record'], 'a record widget on the page');
	pg_assert_true(pg_comments_page_census_facts($top(array(30 => 1)), $components)['top_record'], 'which decides the thread');
	pg_assert_false(pg_comments_page_census_facts($top(array(40 => 1)), $components)['top_record'], 'one in a header does not');
	pg_assert_same(0, pg_comments_page_census_facts($top(array(77 => 1)), $components)['widgets'], 'a component that is not read');
	pg_assert_same(array('widgets' => 0, 'blocks' => 0, 'nested_record' => false, 'top_record' => false), pg_comments_page_census_facts(null, null), 'nothing');

	// A chain of components: 8 deep the widget is still counted, 9 deep not.
	$chain = function ($length) {
		$list = array(5 => array('type' => 'comments', 'census' => array('refs' => array(), 'blocks' => 0)));
		for ($i = 1; $i <= $length; $i++) {
			$list[100 + $i] = array('type' => '', 'census' => array('refs' => array((($i === $length) ? 5 : 101 + $i) => 1), 'blocks' => 0));
		}
		return $list;
	};
	pg_assert_same(1, pg_comments_page_census_facts($top(array(101 => 1)), $chain(8))['widgets'], 'eight levels down');
	pg_assert_same(0, pg_comments_page_census_facts($top(array(101 => 1)), $chain(9))['widgets'], 'nine levels down');

	// A loop between two components ends.
	$loop = array(
		5 => $c('comments'),
		60 => $c('', array(61 => 1, 5 => 1)),
		61 => $c('', array(60 => 1)),
	);
	pg_assert_same(1, pg_comments_page_census_facts($top(array(60 => 1)), $loop)['widgets'], 'a loop ends');
}

// Which warnings a page's save gives, in order, and what they say.
function test_comments_widget_designer_save_warning_codes_and_text()
{
	if (!comments_widget_designer_test_has(array('pg_comments_save_warning_codes', 'pg_comments_save_warning_text', 'pg_comments_save_warnings'))) {
		return;
	}

	$f = function ($over) {
		return $over + array('widgets' => 1, 'blocks' => 0, 'nested_record' => false, 'comments_on' => true);
	};

	pg_assert_same(array(), pg_comments_save_warning_codes($f(array('widgets' => 0, 'blocks' => 2, 'comments_on' => false, 'nested_record' => true))), 'no widget, no warning');
	pg_assert_same(array(), pg_comments_save_warning_codes($f(array())), 'one widget, all well');
	pg_assert_same(array('block'), pg_comments_save_warning_codes($f(array('blocks' => 1))), 'widget and block');
	pg_assert_same(array('widgets'), pg_comments_save_warning_codes($f(array('widgets' => 2))), 'two widgets');
	pg_assert_same(array('nested_record'), pg_comments_save_warning_codes($f(array('nested_record' => true))), 'a nested record widget');
	pg_assert_same(array(), pg_comments_save_warning_codes($f(array('nested_record' => true, 'top_record' => true))), 'a record widget on the page decides the thread');
	pg_assert_same(array('off'), pg_comments_save_warning_codes($f(array('comments_on' => false))), 'comments off');
	pg_assert_same(array('block', 'widgets', 'nested_record', 'off'),
		pg_comments_save_warning_codes($f(array('blocks' => 1, 'widgets' => 3, 'nested_record' => true, 'comments_on' => false))), 'all four, in order');
	pg_assert_same(array(), pg_comments_save_warning_codes('x'), 'not facts');

	foreach (array('block', 'widgets', 'nested_record', 'off') as $code) {
		pg_assert_contains('"topic-page"', pg_comments_save_warning_text($code, 'topic-page', 3), $code . ' names the page');
	}
	pg_assert_contains('3 Comments widgets', pg_comments_save_warning_text('widgets', 'topic-page', 3), 'the count');
	pg_assert_same('', pg_comments_save_warning_text('other', 'topic-page', 1), 'an unknown code');

	// A tree with no comments widget reads no component.
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'region', 'props' => array('regionType' => 'comments_block'), 'children' => array()),
	));
	pg_assert_same(array(), pg_comments_save_warnings($tree, false, 'p'), 'a block alone');
	pg_assert_same(array(), pg_comments_save_warnings(null, true, 'p'), 'no tree');
}

// The nested record reader and the save share the census.
function test_comments_widget_designer_census_wiring()
{
	$nested = comments_widget_designer_test_php_body('pg_comments_page_nested_record');
	pg_assert_contains('pg_comments_tree_census(', $nested, 'the nested record reader takes the census');
	pg_assert_contains('pg_comments_component_census(', $nested, 'and reads the components through it');
	pg_assert_contains('static $cache', $nested, 'cached per page');

	$census = comments_widget_designer_test_php_body('pg_comments_component_census');
	pg_assert_contains('500', $census, 'at most 500 components');
	pg_assert_contains('8', $census, 'at most 8 levels');
}

// The design's comments widget, the placement and the block the conversion
// puts in.
function test_comments_widget_designer_pick_widget_and_nodes()
{
	$script = <<<'JS'
var _seq = 0;
function gid() { return 'sd_' + (++_seq); }
function ref(sid, kids) { return { type: 'shared_ref', props: { sharedId: sid, sharedName: 'w' + sid }, children: kids || [] }; }
function root(kids) { return { type: 'root', props: {}, children: kids }; }
var cfgs = { 5: { regionType: 'comments' }, 6: { regionType: 'comments' }, 7: { regionType: 'form_item_view' }, 8: { regionType: 'comments' }, 9: { regionType: '' } };
var trees = { 9: root([ref(6)]), 10: root([ref(11)]), 11: root([ref(10)]) };
var cfgOf = function (sid) { return cfgs[sid] || null; };
var treeOf = function (sid) { return trees[sid] || null; };
var out = {};
out.page = _sdCommentsPickWidget([{ tree: root([ref(7), { type: 'container', props: {}, children: [ref(5)] }]) }], cfgOf, treeOf, {}, 3);
out.header = _sdCommentsPickWidget([{ tree: root([ref(9)]) }], cfgOf, treeOf, {}, 3);
out.second_page = _sdCommentsPickWidget([{ tree: root([ref(7)]) }, { tree: root([ref(5)]) }], cfgOf, treeOf, {}, 3);
out.usage = _sdCommentsPickWidget([{ tree: root([ref(7)]) }], cfgOf, treeOf, { 7: [{ style_id: 3 }], 8: [{ style_id: '3' }] }, 3);
out.usage_other_design = _sdCommentsPickWidget([{ tree: root([ref(7)]) }], cfgOf, treeOf, { 8: [{ style_id: 4 }] }, 3);
out.nothing = _sdCommentsPickWidget([], cfgOf, treeOf, {}, 3);
out.loop = _sdCommentsPickWidget([{ tree: root([ref(10)]) }], cfgOf, treeOf, {}, 3);
out.ref = _sdCommentsRefNode(5, 'design-comments-widget');
out.block = _sdCommentsBlockNode();
process.stdout.write(JSON.stringify(out));
JS;

	$res = comments_widget_designer_test_node(array('_sdCommentsPickWidget', '_sdCommentsRefNode', '_sdCommentsBlockNode'), $script);
	if ($res === null) {
		return;
	}

	pg_assert_same(5, $res['page'], 'a comments placement on a page; a record widget is ignored');
	pg_assert_same(6, $res['header'], 'one inside a header');
	pg_assert_same(5, $res['second_page'], 'one on another page of the design');
	pg_assert_same(8, $res['usage'], 'one the design used, by the usage list');
	pg_assert_same(0, $res['usage_other_design'], 'not one another design used');
	pg_assert_same(0, $res['nothing'], 'none');
	pg_assert_same(0, $res['loop'], 'a loop between components ends');

	pg_assert_same('shared_ref', $res['ref']['type'], 'a placement');
	pg_assert_same(array('sharedId' => 5, 'sharedName' => 'design-comments-widget'), $res['ref']['props'], 'only the id and the name');
	pg_assert_same(array(), $res['ref']['children'], 'no children');
	pg_assert_true(is_string($res['ref']['_id']) && $res['ref']['_id'] !== '', 'an id');
	pg_assert_same('region', $res['block']['type'], 'the fixed block');
	pg_assert_same(array('regionType' => 'comments_block', 'regionName' => ''), $res['block']['props']);
	pg_assert_same(array(), $res['block']['children']);
}


// A template may ship a comments widget with the editor's starter layout.
function test_comments_widget_designer_templates_ship_the_kind()
{
	$tpl = array(
		'pages' => array(array('key' => 'topic')),
		'widgets' => array(
			array('key' => 'comments', 'page' => 'topic', 'tree' => 'starter', 'config' => array('regionType' => 'comments', 'featured' => 'filter')),
		),
	);
	pg_assert_same(array('comments'), pg_design_template_starter_kinds($tpl), 'a comments starter is a starter kind');

	$js = comments_widget_designer_test_source('assets/js/style_designer.js');
	$starter = comments_widget_designer_test_js_function($js, '_buildStarterTree');
	pg_assert_contains("case 'comments':", $starter, 'the editor builds the starter');
	pg_assert_contains("{ type: 'comments',", $js, 'and knows the kind');
}

// The conversion buttons and the save's warnings are wired.
function test_comments_widget_designer_conversion_wiring()
{
	$js = comments_widget_designer_test_source('assets/js/style_designer.js');

	pg_assert_contains('sd-cmt-make-designable', comments_widget_designer_test_js_function($js, 'propsRegion'), 'the region offers Make designable');
	pg_assert_contains('sd-cmt-make-designable', comments_widget_designer_test_js_function($js, 'bindPropEvents'), 'and it is wired');
	pg_assert_contains('sd-cmt-back-to-block', comments_widget_designer_test_js_function($js, 'propsSharedRef'), 'the widget offers Back to the fixed block');
	pg_assert_contains('sd-cmt-back-to-block', comments_widget_designer_test_js_function($js, '_wireSystemWidgetProps'), 'and it is wired');
	pg_assert_contains('where.designWide', comments_widget_designer_test_js_function($js, '_swBelongsToDesign'), 'a widget made for the design is named after it');

	$make = comments_widget_designer_test_js_function($js, '_sdCommentsMakeDesignable');
	pg_assert_contains("featured = 'filter'", $make, 'a widget made from the starter keeps the fixed block\'s featured filter');
	pg_assert_contains('saveState()', $make, 'one undo step');
	pg_assert_contains('saveState()', comments_widget_designer_test_js_function($js, '_sdCommentsBackToBlock'), 'back is one undo step');

	pg_assert_contains('pg_comments_save_warnings(', comments_widget_designer_test_php_body('pg_designer_save_page'), 'the save warns');
}
