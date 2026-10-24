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
