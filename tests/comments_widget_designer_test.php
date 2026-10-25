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
	pg_assert_contains("esc(_sdT('Comment settings for {var}', ", $sect, 'the heading is escaped once, page name included');

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

// An empty closed message: the fixed block prints none, the widget prints
// the standard sentence. Each panel says what its own renderer does.
function test_comments_widget_designer_closed_message_by_renderer()
{
	$script = <<<'JS'
function _sdT(s, v) { return (v === undefined) ? s : String(s).replace(/\{var(?::1)?\}/g, v); }
function esc(s) { return s ? String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') : ''; }
function sect(icon, title, body) { return '<section>' + body + '</section>'; }
function row(label, ctrl) { return '<div class="row" data-label="' + label + '">' + ctrl + '</div>'; }
function _getCmtVal(k) { return k === 'pg_comments' ? '1' : ''; }
function _pgActivePage() { return { page_name: 'topic' }; }
function _swPageOptions() { return ''; }
var _allPagesCache = [];
var closed = function (html) {
    var at = html.indexOf('data-label="Closed message"');
    return html.substring(at, html.indexOf('data-label="Sender manages watchers"'));
};
var out = { block: closed(_sdCommentSettingsSect({})), widget: closed(_sdCommentSettingsSect({ widget: true })) };
process.stdout.write(JSON.stringify(out));
JS;

	$res = comments_widget_designer_test_node(array('_sdCommentSettingsSect'), $script);
	if ($res === null) {
		return;
	}

	$sentence = 'We&#39;re sorry. New comments are no longer being accepted.';
	$plain = "We're sorry. New comments are no longer being accepted.";
	pg_assert_true(strpos($res['block'], 'data-cmt-txt="pg_comments_closed_message"') !== false, 'the block has the box');
	pg_assert_false(strpos($res['block'], $plain) !== false || strpos($res['block'], $sentence) !== false, 'the block offers no sentence it would not print');
	pg_assert_contains('Leave it empty to show no message.', $res['block'], 'the block says an empty one prints nothing');
	pg_assert_contains('placeholder="' . $plain . '"', $res['widget'], 'the widget shows the sentence it prints for an empty one');
	pg_assert_false(strpos($res['widget'], 'Leave it empty to show no message.') !== false, 'and does not say it prints none');
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
	pg_assert_contains('pg_designer_preview_input(', $case, 'and decides what the Preview may use by the access');
}

// The Preview may draw a page the user may not edit, but only with its
// stored comment settings: the editor's are taken for a page they may edit.
// A page they may not know is drawn as an unsaved one.
function test_comments_widget_designer_preview_input()
{
	if (!comments_widget_designer_test_has('pg_designer_preview_input')) {
		return;
	}

	$settings = array('pg_comments' => '1', 'pg_comments_rating' => '1');

	pg_assert_same(array('page_id' => 12, 'comment_settings' => $settings), pg_designer_preview_input(12, $settings, 'edit'), 'a page they may edit takes the editor\'s settings');
	pg_assert_same(array('page_id' => 12, 'comment_settings' => array()), pg_designer_preview_input(12, $settings, 'locked'), 'a locked page keeps its stored settings');
	pg_assert_same(array('page_id' => 0, 'comment_settings' => array()), pg_designer_preview_input(12, $settings, 'hidden'), 'a hidden page is drawn as an unsaved one');
	pg_assert_same(array('page_id' => 12, 'comment_settings' => array()), pg_designer_preview_input('12', 'x', 'edit'), 'settings that are not a list are none');
	pg_assert_same(array('page_id' => 0, 'comment_settings' => array()), pg_designer_preview_input(-3, $settings, 'edit'), 'no page below 1');
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
		'email' => array('email' => true, 'count' => 1, 'allow_new' => true, 'rating_on' => 1),
		'email, everything on' => array('email' => 1, 'count' => 1, 'allow_new' => true, 'signed_in' => true, 'staff' => true, 'require_login' => true,
			'replying' => true, 'is_watcher' => true, 'manage_watchers' => true, 'featured_view' => true, 'has_featured' => true, 'pages' => 3,
			'rating_on' => true, 'files_on' => true, 'watching_on' => true, 'name_mode' => 'typed'),
		'email closed' => array('email' => '1', 'count' => 1, 'allow_new' => false, 'watching_on' => 1),
	);

	$res = comments_widget_designer_test_node(array('_sdCommentsThreadFlags'),
		'var sets = ' . json_encode($sets) . ";\nvar out = {};\nObject.keys(sets).forEach(function (k) { out[k] = _sdCommentsThreadFlags(sets[k]); });\nprocess.stdout.write(JSON.stringify(out));\n");
	if ($res === null) {
		return;
	}

	foreach ($sets as $name => $facts) {
		$php = pg_comments_widget_thread_flags($facts);
		pg_assert_same(23, count($php), $name . ': 23 flags');
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
    keep(n('semantic', { tag: 'div' }, [], 'card text')),
    keep(n('semantic', { tag: 'button' }, [], 'card button'))
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
        keep(flag('name_is_chosen', [], 'name select')),
        keep(n('semantic', { tag: 'button' }, [], 'submit'))
    ], 'form')),
    keep(flag('is_staff', [], 'staff controls')),
    keep(n('semantic', { tag: 'div' }, [], 'plain')),
    keep(flag('is_email', [], 'email note')),
    keep(flag('watching_on', [keep(n('semantic', { tag: 'input' }, [], 'input'))], 'watch box')),
    keep(n('component', { componentType: 'btn', btnElement: 'button' }, [], 'btn button')),
    keep(n('component', { componentType: 'btn', btnElement: 'a' }, [], 'btn link')),
    keep(n('content', { contentType: 'link', _bindings: { action: 'comment_reply_cancel' } }, [], 'cancel'))
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
['comments', 'closed', 'empty', 'staff', 'member', 'sign_in', 'replying', 'email'].forEach(function (st) {
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
    shown_already: pick('member', named['form']),
    email_note: pick('comments', named['email note']),
    card_button_from_email: pick('email', named['card button']),
    submit_from_email: pick('email', named['submit']),
    input_from_email: pick('email', named['input']),
    card_text_in_email: pick('email', named['card text']),
    card_button: pick('comments', named['card button'])
};
JS;

	$res = comments_widget_designer_test_node(
		array('_sdCommentsThreadFlags', '_sdCommentsCanvasStates', '_sdCommentsCanvasFacts', '_sdCommentsCanvasFlags', '_sdCommentsEmailDrops', '_sdCommentsFlagOff', '_sdCommentsStateFor'),
		comments_widget_designer_test_canvas_script($body));
	if ($res === null) {
		return;
	}

	pg_assert_same(array('comments', 'replying', 'empty', 'member', 'sign_in', 'closed', 'staff', 'email'), $res['states'], 'the states in order');

	// The e-mail state draws what a comment e-mail gets: the comment, no
	// form, field, button, replying box or cancel control.
	foreach (array('form', 'submit', 'input', 'btn button', 'cancel', 'card button', 'replying', 'add heading', 'closed', 'sign in', 'staff controls', 'staff name', 'empty') as $gone) {
		pg_assert_true($res['email'][$gone], 'the e-mail hides ' . $gone);
	}
	foreach (array('email note', 'loop', 'heading', 'card text', 'btn link', 'plain', 'rating', 'watch box', 'quote', 'status') as $kept) {
		pg_assert_false($res['email'][$kept], 'the e-mail shows ' . $kept);
	}
	foreach (array('comments', 'closed', 'empty', 'staff', 'member', 'sign_in', 'replying') as $state) {
		pg_assert_true($res[$state]['email note'], 'only the e-mail shows is_email: ' . $state);
		pg_assert_false($res[$state]['btn button'] || $res[$state]['card button'] || $res[$state]['cancel'], 'buttons and cancel are drawn outside the e-mail: ' . $state);
	}
	pg_assert_same('email', $res['pick']['email_note'], 'an is_email node opens the e-mail state');
	pg_assert_same('comments', $res['pick']['card_button_from_email'], 'a button the e-mail drops moves off the e-mail state');
	pg_assert_same('comments', $res['pick']['submit_from_email'], 'and so does the form');
	pg_assert_same('comments', $res['pick']['input_from_email'], 'and a field');
	pg_assert_same('email', $res['pick']['card_text_in_email'], 'the card stays in the e-mail state');
	pg_assert_same('comments', $res['pick']['card_button'], 'a button keeps a state that draws it');

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

// The canvas drops in its e-mail state exactly what the e-mail branch strips
// (pg_comments_email_drops()), and draws no sample comment there: an e-mail
// is about one comment.
function test_comments_widget_designer_email_drops_twin()
{
	if (!comments_widget_designer_test_has('pg_comments_email_drops')) {
		return;
	}

	$n = function ($type, $props) {
		return array('type' => $type, 'props' => $props, 'children' => array());
	};
	$nodes = array(
		'form' => $n('semantic', array('tag' => 'form')),
		'Form' => $n('semantic', array('tag' => 'FORM')),
		'input' => $n('semantic', array('tag' => 'input')),
		'select' => $n('semantic', array('tag' => 'select')),
		'textarea' => $n('semantic', array('tag' => 'textarea')),
		'button' => $n('semantic', array('tag' => 'button')),
		'div' => $n('semantic', array('tag' => 'div')),
		'label' => $n('semantic', array('tag' => 'label')),
		'a' => $n('semantic', array('tag' => 'a')),
		'no tag' => $n('semantic', array()),
		'tag not text' => $n('semantic', array('tag' => array('form'))),
		'btn button' => $n('component', array('componentType' => 'btn', 'btnElement' => 'button')),
		'btn input' => $n('component', array('componentType' => 'btn', 'btnElement' => 'input')),
		'btn a' => $n('component', array('componentType' => 'btn', 'btnElement' => 'a')),
		'btn default' => $n('component', array('componentType' => 'btn')),
		'other component' => $n('component', array('componentType' => 'card', 'btnElement' => 'button')),
		'content input' => $n('content', array('contentType' => 'input', 'tag' => 'input')),
		'replying' => $n('semantic', array('tag' => 'div', '_bindings' => array('eo_visible_if' => 'is_replying'))),
		'replying link' => $n('content', array('contentType' => 'link', '_bindings' => array('eo_visible_if' => 'is_replying'))),
		'cancel' => $n('content', array('contentType' => 'link', '_bindings' => array('action' => 'comment_reply_cancel'))),
		'reply' => $n('content', array('contentType' => 'link', '_bindings' => array('action' => 'comment_reply'))),
		'share' => $n('semantic', array('tag' => 'a', '_bindings' => array('action' => 'comment_share'))),
		'staff' => $n('semantic', array('tag' => 'div', '_bindings' => array('eo_visible_if' => 'is_staff'))),
		'bindings not a list' => $n('semantic', array('tag' => 'div', '_bindings' => 'is_replying')),
		'no props' => array('type' => 'semantic'),
	);

	$res = comments_widget_designer_test_node(array('_sdCommentsEmailDrops'),
		'var nodes = ' . json_encode($nodes) . ";\nvar out = {};\nObject.keys(nodes).forEach(function (k) { out[k] = _sdCommentsEmailDrops(nodes[k]); });\n"
		. "out.__null = _sdCommentsEmailDrops(null);\nprocess.stdout.write(JSON.stringify(out));\n");
	if ($res === null) {
		return;
	}

	foreach ($nodes as $name => $node) {
		pg_assert_same(pg_comments_email_drops($node), isset($res[$name]) ? $res[$name] : null, 'the twins agree: ' . $name);
	}
	pg_assert_false($res['__null'], 'no node');
	pg_assert_true($res['btn button'] && $res['cancel'] && $res['replying'] && $res['form'], 'what the e-mail drops');
	pg_assert_false($res['btn a'] || $res['reply'] || $res['share'] || $res['staff'], 'links and flagged boxes stay (their flags decide)');

	$js = comments_widget_designer_test_source('assets/js/style_designer.js');
	$samples = comments_widget_designer_test_js_function($js, '_sdAppendCommentSamples');
	$no_comments = strpos($samples, 'if (!thread.has_comments) return;');
	$email = strpos($samples, 'if (thread.is_email) return;');
	pg_assert_true(($no_comments !== false) && ($email !== false) && ($no_comments < $email), 'no sample comments in the e-mail state');
	pg_assert_contains('_sdCommentsEmailDrops(node)', comments_widget_designer_test_js_function($js, '_sdCommentsFlagOff'), 'the e-mail state hides what the e-mail drops');
	pg_assert_contains('_sdCommentsEmailDrops', comments_widget_designer_test_js_function($js, '_sdCommentsStateFor'), 'and a node it hides opens another state');
	pg_assert_contains("['is_email', _sdT('Drawn in a comment e-mail')]", $js, 'the designer offers is_email');
	pg_assert_contains("['email', _sdT('E-mail')]", comments_widget_designer_test_js_function($js, '_sdCommentsCanvasStates'), 'the e-mail state has a chip');
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

// The sample people are different people in Turkish too: a reply never
// reads as an answer to its own author.
function test_comments_widget_designer_sample_names_differ_in_turkish()
{
	$body = <<<'JS'
var list = _sdCommentSamples({ regionType: 'comments' }, { is_staff: false });
out.parent = list[0].values.__comment_parent_name;
out.names = list.map(function (s) { return s.values.__comment_name; });
JS;

	$res = comments_widget_designer_test_node(array('_sdCommentSamples'), comments_widget_designer_test_canvas_script($body));
	if ($res === null) {
		return;
	}

	$tr = json_decode((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr.json'), true);
	$names = array_merge(array($res['parent']), $res['names']);
	$turkish = array();

	foreach ($names as $name) {
		pg_assert_true(isset($tr[$name]), $name . ' is translated');
		$turkish[] = isset($tr[$name]) ? $tr[$name] : $name;
	}

	pg_assert_same(4, count(array_unique($names)), 'the one answered and three samples: four people');
	pg_assert_same(4, count(array_unique($turkish)), 'four people in Turkish too: ' . implode(', ', $turkish));

	// The designer's sample people (designer-block-sample-content.md), so a
	// team block and the comments read as one site's people.
	foreach ($names as $name) {
		pg_assert_true(in_array($name, array('Jane Cooper', 'Michael Brooks', 'Emily Turner', 'Daniel Ross'), true), $name . ' is one of the sample people');
	}
}

// A comments token fills a rich-text node's slot escaped, as the page fills
// it: the page's own words (the intro, typed by an operator) and a sample's
// status never reach the canvas as markup. A semantic node's text is a text
// node, so it gets the words as they are; the HTML tokens stay markup.
function test_comments_widget_designer_rich_text_slot_escapes_page_words()
{
	$script = <<<'JS'
function _sdT(s, v) { return (v === undefined) ? s : String(s).replace(/\{var(?::1)?\}/g, v); }
function esc(s) { return s ? String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') : ''; }
function _sdDecodeEntities(s) { return String(s); }
var _cmt = { pg_comments_message: '<img src=x onerror="alert(1)">', pg_comments_label: '' };
function _getCmtVal(k) { return _cmt[k] || ''; }
var _sdCommentSample = null;
var _SD_AVATAR_TOKENS = {};
var _SD_RICH_TEXT_CONTENT = { heading: 1, link: 1, paragraph: 1, span: 1, text: 1 };
var _SD_HTML_PREVIEW_TOKENS = { __comment_message_html: function () { return '<p>Comment text</p>'; } };
var SW_COMMENTS_TOKEN_GROUPS = [{ label: 'Thread', tokens: [['__comments_intro', 'Intro'], ['__comments_label', 'Label']] },
                                { label: 'Comment', tokens: [['__comment_status', 'Status'], ['__comment_message_html', 'Text']] }];
var _sdCommentsTokenSet = null;
function content(type, text, tok) { return { type: 'content', props: { contentType: type, text: text, _bindings: { text: tok } }, children: [] }; }
var out = {};
out.paragraph = _propsWithBindings(content('paragraph', 'Intro', '__comments_intro')).text;
out.heading_markup = _propsWithBindings(content('heading', '<b>Intro</b>', '__comments_intro')).text;
out.semantic = _propsWithBindings({ type: 'semantic', props: { tag: 'p', text: '', _bindings: { text: '__comments_intro' } }, children: [] }).text;
out.html = _propsWithBindings(content('paragraph', 'x', '__comment_message_html')).text;
_sdCommentSample = { values: { __comment_status: 'Your <b>answer</b> is awaiting approval' } };
out.sample = _propsWithBindings(content('span', 'Status', '__comment_status')).text;
_sdCommentSample = null;
process.stdout.write(JSON.stringify(out));
JS;

	$res = comments_widget_designer_test_node(array('_sdBindTextSlot', '_propsWithBindings', '_displayValueForBinding',
		'_sdCommentsPageToken', '_sdIsHtmlPreviewToken', '_sdCommentsTextToken'), $script);
	if ($res === null) {
		return;
	}

	pg_assert_same('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $res['paragraph'], 'the intro fills a paragraph as text');
	pg_assert_same('<b>&lt;img src=x onerror=&quot;alert(1)&quot;&gt;</b>', $res['heading_markup'], 'inside the markup the heading keeps');
	pg_assert_same('<img src=x onerror="alert(1)">', $res['semantic'], 'a semantic node gets the words as they are (a text node)');
	pg_assert_same('<p>Comment text</p>', $res['html'], 'an HTML token stays markup');
	pg_assert_same('Your &lt;b&gt;answer&lt;/b&gt; is awaiting approval', $res['sample'], 'a sample\'s status, with the page\'s label, is text');
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

	// A page another page names as its comment e-mail page: it draws no live
	// thread, so only the two-widgets warning applies to it.
	pg_assert_same(array(), pg_comments_save_warning_codes($f(array('email_page' => true))), 'an e-mail page, one widget');
	pg_assert_same(array(), pg_comments_save_warning_codes($f(array('email_page' => true, 'blocks' => 1, 'nested_record' => true, 'comments_on' => false))), 'an e-mail page: no block, record or off warning');
	pg_assert_same(array('widgets'), pg_comments_save_warning_codes($f(array('email_page' => true, 'widgets' => 2, 'blocks' => 1, 'comments_on' => false))), 'an e-mail page with two widgets');
	pg_assert_same(array(), pg_comments_save_warning_codes($f(array('email_page' => true, 'widgets' => 0))), 'an e-mail page without a widget');
	pg_assert_same(array('off'), pg_comments_save_warning_codes($f(array('email_page' => false, 'comments_on' => false))), 'not an e-mail page');

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
	pg_assert_same(array(), pg_comments_save_warnings($tree, false, 'p', 12), 'a block alone on a saved page reads nothing');
}

// The nested record reader, the e-mails' widget check and the save share the
// census; the save asks whether the page is a comment e-mail page only for a
// page whose tree reaches a comments widget.
function test_comments_widget_designer_census_wiring()
{
	$facts = comments_widget_designer_test_php_body('pg_comments_page_facts');
	pg_assert_contains('pg_comments_tree_census(', $facts, 'the page\'s facts take the census');
	pg_assert_contains('pg_comments_component_census(', $facts, 'and read the components through it');
	pg_assert_contains('static $cache', $facts, 'cached per page');

	$nested = comments_widget_designer_test_php_body('pg_comments_page_nested_record');
	pg_assert_contains('pg_comments_page_facts(', $nested, 'the nested record reader reads them');
	pg_assert_contains('pg_comments_page_facts(', comments_widget_designer_test_php_body('pg_comments_page_has_widget'), 'and so does the e-mails\' widget check');

	$census = comments_widget_designer_test_php_body('pg_comments_component_census');
	pg_assert_contains('500', $census, 'at most 500 components');
	pg_assert_contains('8', $census, 'at most 8 levels');

	$warnings = comments_widget_designer_test_php_body('pg_comments_save_warnings');
	$refs = strpos($warnings, "if (!\$census['refs'])");
	$widgets = strpos($warnings, "\$facts['widgets'] > 0");
	$email = strpos($warnings, 'pg_comments_is_email_page(');
	pg_assert_true(($refs !== false) && ($widgets !== false) && ($email !== false) && ($refs < $widgets) && ($widgets < $email), 'the e-mail page is asked about only once a widget is found');

	$save = comments_widget_designer_test_php_body('pg_designer_save_page');
	pg_assert_contains('pg_comments_save_warnings($cw_tree, $cw_on, $name, $page_id)', $save, 'the save passes the page');
	pg_assert_false(strpos($save, 'pg_comments_is_email_page('), 'and does not ask for every page itself');
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
out.spare = _sdCommentsPickWidget([{ tree: root([ref(7)]) }], cfgOf, treeOf, {}, 3, [0, 7, 8, 6]);
out.spare_after_placed = _sdCommentsPickWidget([{ tree: root([ref(5)]) }], cfgOf, treeOf, {}, 3, [8]);
out.spare_none = _sdCommentsPickWidget([], cfgOf, treeOf, {}, 3, [7, 9, 0]);
out.named = _sdCommentsNamedRows([
    { id: '21', name: 'design-comments-widget', system_region_config: '{"regionType":"comments"}' },
    { id: 22, name: 'design-comments-widget [2]', system_region_config: '{"regionType":"comments"}' },
    { id: 23, name: 'design-comments-widget', system_region_config: '{"regionType":"form_item_view"}' },
    { id: 24, name: 'topic-comments-widget', system_region_config: '{"regionType":"comments"}' },
    { id: 25, name: 'design-comments-widget-old', system_region_config: '{"regionType":"comments"}' },
    { id: 26, name: 'design-comments-widget', system_region_config: 'not json' },
    null
], 'design-comments-widget');
out.named_none = _sdCommentsNamedRows(null, 'design-comments-widget');
out.ref = _sdCommentsRefNode(5, 'design-comments-widget');
out.block = _sdCommentsBlockNode();
process.stdout.write(JSON.stringify(out));
JS;

	$res = comments_widget_designer_test_node(array('_sdCommentsPickWidget', '_sdCommentsNamedRows', '_sdCommentsRefNode', '_sdCommentsBlockNode'), $script);
	if ($res === null) {
		return;
	}

	pg_assert_same(8, $res['spare'], 'no placement: the first spare comments widget (not a record widget)');
	pg_assert_same(5, $res['spare_after_placed'], 'a placed one comes first');
	pg_assert_same(0, $res['spare_none'], 'no spare comments widget');
	pg_assert_same(array(21, 22), $res['named'], 'the comments rows named after the design, also with a number');
	pg_assert_same(array(), $res['named_none'], 'no rows');

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

// The editor caches the configs and layouts of the tabs opened so far; the
// conversion reads the other tabs' widgets from the site's widget rows and
// loads the components those tabs place, so a Comments widget placed only
// on a tab not opened yet is still the design's.
function test_comments_widget_designer_parked_tab_lookups()
{
	$script = <<<'JS'
function ref(sid) { return { type: 'shared_ref', props: { sharedId: sid, sharedName: 'w' + sid }, children: [] }; }
function root(kids) { return { type: 'root', props: {}, children: kids }; }
var out = {};
out.configs = _sdCommentsRowConfigs([
    { id: '5', name: 'a', system_region_config: '{"regionType":"comments","featured":"first"}' },
    { id: 6, name: 'b', system_region_config: '' },
    { id: 7, name: 'c', system_region_config: null },
    { id: 8, name: 'd', system_region_config: 'not json' },
    { id: 9, name: 'e', system_region_config: '[1]' },
    { id: 0, name: 'f', system_region_config: '{"regionType":"comments"}' },
    null
]);
out.configs_none = _sdCommentsRowConfigs(null);
var cfgs = { 5: { regionType: 'comments' }, 6: { regionType: 'comments' }, 7: { regionType: 'form_item_view' } };
var trees = { 9: root([ref(6), ref(12)]), 10: root([ref(11)]), 11: root([ref(10)]) };
var cfgOf = function (sid) { return cfgs[sid] || null; };
var treeOf = function (sid) { return trees[sid] || null; };
out.unloaded = _sdCommentsUnloadedRefs([
    { tree: root([ref(5), ref(7), ref(9), ref(13), { type: 'container', props: {}, children: [ref(13)] }]) },
    { tree: root([ref(14)]) }
], cfgOf, treeOf);
out.unloaded_loop = _sdCommentsUnloadedRefs([{ tree: root([ref(10)]) }], cfgOf, treeOf);
out.unloaded_none = _sdCommentsUnloadedRefs(null, cfgOf, treeOf);
// A placement on a parked tab: no cache entry, its kind only in the rows.
var rowCfg = _sdCommentsRowConfigs([{ id: '31', system_region_config: '{"regionType":"comments"}' }]);
var parkedPages = [{ tree: root([ref(7)]) }, { tree: root([ref(31)]) }];
out.pick_cache_only = _sdCommentsPickWidget(parkedPages, function () { return null; }, treeOf, {}, 3);
out.pick_with_rows = _sdCommentsPickWidget(parkedPages, function (s) { return cfgs[s] || rowCfg[s] || null; }, treeOf, {}, 3);
process.stdout.write(JSON.stringify(out));
JS;

	$res = comments_widget_designer_test_node(array('_sdCommentsPickWidget', '_sdCommentsRowConfigs', '_sdCommentsUnloadedRefs'), $script);
	if ($res === null) {
		return;
	}

	pg_assert_same(array(5 => array('regionType' => 'comments', 'featured' => 'first')), $res['configs'], 'only a row with an id and a config object');
	pg_assert_same(array(), $res['configs_none'], 'no rows');
	pg_assert_same(array(12, 13, 14), $res['unloaded'], 'components with no cached layout, each once, also inside a loaded one; widgets left out');
	pg_assert_same(array(), $res['unloaded_loop'], 'a loop between loaded components ends');
	pg_assert_same(array(), $res['unloaded_none'], 'no pages');
	pg_assert_same(0, $res['pick_cache_only'], 'a parked tab\'s widget is missed without its config');
	pg_assert_same(31, $res['pick_with_rows'], 'and found with the config its row carries');
}


// Make designable and Back to the fixed block as the operator runs them:
// each is one undo step on the page's own tree, a widget made for it shows
// only the featured comments until Show all, a page held by a colleague is
// refused with the read-only nudge, a content-level operator is not offered
// either, and a toast's words are escaped once.
function test_comments_widget_designer_conversion_runs()
{
	$script = <<<'JS'
var _seq = 0;
function gid() { return 'sd_' + (++_seq); }
function _sdT(s, v) { return (v === undefined) ? s : String(s).replace(/\{var(?::1)?\}/g, v); }
function esc(s) { return s ? String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') : ''; }
function findParent(node, root) {
    var hit = null;
    (function walk(n) {
        if (hit || !n || !Array.isArray(n.children)) return;
        if (n.children.indexOf(node) !== -1) { hit = n; return; }
        n.children.forEach(walk);
    })(root);
    return hit;
}
var log = [];
var _collab = { mayEdit: true };
var _access = 'full';
function _sdIsContentLevel() { return _access === 'content'; }
function _sdReadOnlyNudge() { log.push('nudge'); }
function saveState() { log.push('undo'); }
function render() { log.push('render'); }
function sdToast(msg, type) { log.push('toast:' + type + ':' + msg); }
function expandAncestorsAnywhere() {}
function _refreshSystemTab() {}
var server = {};
function prefetchShared(ids, cb) {
    log.push('fetch:' + ids.join(','));
    ids.forEach(function (id) { if (server[id]) _sharedCache[id] = server[id]; });
    if (cb) cb();
}
function _buildStarterTree(kind) { return { type: 'root', props: {}, children: [], starter: kind }; }
function _swTypeDefaults(cfg, type) { cfg.regionType = type; cfg.featured = 'first'; return cfg; }
function _swAutoName(type, page, where) { return (where && where.designWide) ? 'design-' + type + '-widget' : 'page-' + type + '-widget'; }
var created = [];
function _swCreateRow(name, cfg, starter, cb) { created.push({ name: name, cfg: cfg, starter: starter.starter }); cb(77, name); }
var rows = [];
function _sdCommentsFetchRows(cb) { log.push('list'); cb(rows); }
var _sdCommentsSentBack = 0;
var _sharedCache = {}, _sharedUsage = {}, _design = { styleId: 3 };
function _cfCfgOf(sid) {
    if (sid === 5 || sid === 77) return { regionType: 'comments', featured: _sharedCache[sid] ? _sharedCache[sid].featured : 'filter' };
    var c = _sharedCache[sid];
    return (c && c.system_region_config) ? JSON.parse(c.system_region_config) : null;
}
var tree, selectedNode = null, selectedNodes = [], otherPage = null;
function _pgAllPageTrees() { return [{ tree: tree }].concat(otherPage ? [{ tree: otherPage }] : []); }
function block() { return { _id: gid(), type: 'region', props: { regionType: 'comments_block', regionName: '' }, children: [] }; }
function page(kids) { return { type: 'root', props: {}, children: [{ type: 'container', props: {}, children: kids }] }; }
var out = {};
var run = function (setup, act) {
    log = []; created = []; _seq = 100; selectedNode = null; otherPage = null; _collab.mayEdit = true; _access = 'full';
    _sharedCache = {}; rows = []; server = {}; _sdCommentsSentBack = 0;
    var b = block();
    tree = page([b]);
    var ctx = { block: b };
    setup(ctx);
    act(ctx);
    return { tree: JSON.stringify(tree), log: log, created: created, selected: selectedNode ? selectedNode.type : null };
};
out.read_only_make = run(function (c) { _collab.mayEdit = false; }, function (c) { _sdCommentsMakeDesignable(c.block); });
out.content_make = run(function (c) { _access = 'content'; }, function (c) { _sdCommentsMakeDesignable(c.block); });
out.made = run(function (c) {}, function (c) { _sdCommentsMakeDesignable(c.block); });
out.reused = run(function (c) {
    _sharedCache[5] = { name: 'Talk <b>', tree: { type: 'root', props: {}, children: [] }, featured: 'first' };
    otherPage = page([{ type: 'shared_ref', props: { sharedId: 5, sharedName: 'Talk <b>' }, children: [] }]);
}, function (c) { _sdCommentsMakeDesignable(c.block); });
out.already = run(function (c) {
    tree.children[0].children.push({ type: 'shared_ref', props: { sharedId: 5, sharedName: 'x' }, children: [] });
}, function (c) { _sdCommentsMakeDesignable(c.block); });
var ref = function () { return { _id: 'r1', type: 'shared_ref', props: { sharedId: 5, sharedName: 'w' }, children: [] }; };
out.read_only_back = run(function (c) { c.ref = ref(); tree = page([c.ref]); _collab.mayEdit = false; }, function (c) { _sdCommentsBackToBlock(c.ref); });
out.back = run(function (c) { c.ref = ref(); tree = page([c.ref]); }, function (c) { _sdCommentsBackToBlock(c.ref); });
// Back, then Make designable again: the widget sent back is placed again,
// even when no page places it any more and it was renamed.
out.round_trip = run(function (c) {
    c.ref = { _id: 'r2', type: 'shared_ref', props: { sharedId: 66, sharedName: 'Forum replies' }, children: [] };
    tree = page([c.ref]);
    _sharedCache[66] = { name: 'Forum replies', tree: null, system_region_config: '{"regionType":"comments","featured":"filter"}' };
}, function (c) {
    _sdCommentsBackToBlock(c.ref);
    _sdCommentsMakeDesignable(tree.children[0].children[0]);
});
// A later visit: no page places the design's widget and nothing was sent
// back here; its row, named after the design, is found and placed.
out.named_row = run(function (c) {
    rows = [{ id: '88', name: 'design-comments-widget', system_region_config: '{"regionType":"comments","featured":"filter"}' },
            { id: '89', name: 'other-comments-widget', system_region_config: '{"regionType":"comments"}' }];
}, function (c) { _sdCommentsMakeDesignable(c.block); });
// The design's widget is placed only on a tab not opened in this editor:
// the cache knows nothing of it, its row does. It is placed, under its own
// name, before a row named after the design and before making one.
out.parked = run(function (c) {
    otherPage = page([{ type: 'shared_ref', props: { sharedId: 91, sharedName: 'Forum talk' }, children: [] }]);
    rows = [{ id: '88', name: 'design-comments-widget', system_region_config: '{"regionType":"comments"}' },
            { id: '91', name: 'Forum talk', system_region_config: '{"regionType":"comments","featured":"first"}' }];
}, function (c) { _sdCommentsMakeDesignable(c.block); });
// It sits inside a component that tab places: the component is loaded
// once, then the widget inside it is placed.
out.parked_component = run(function (c) {
    otherPage = page([{ type: 'shared_ref', props: { sharedId: 40, sharedName: 'Discussion' }, children: [] }]);
    rows = [{ id: '40', name: 'Discussion', system_region_config: null },
            { id: '92', name: 'Thread', system_region_config: '{"regionType":"comments","featured":"filter"}' }];
    server[40] = { name: 'Discussion', tree: { type: 'root', props: {}, children: [{ type: 'shared_ref', props: { sharedId: 92, sharedName: 'Thread' }, children: [] }] }, system_region_config: null };
}, function (c) { _sdCommentsMakeDesignable(c.block); });
// The rows cannot be read (the server answered nothing): the placements
// the cache does not know are loaded once, and the widget among them is
// placed.
out.parked_no_list = run(function (c) {
    otherPage = page([{ type: 'shared_ref', props: { sharedId: 93, sharedName: 'Q&A' }, children: [] }]);
    server[93] = { name: 'Q&A', tree: { type: 'root', props: {}, children: [] }, system_region_config: '{"regionType":"comments","featured":"filter"}' };
}, function (c) { _sdCommentsMakeDesignable(c.block); });
// A component the server no longer has is asked for once; a widget is
// made then.
out.parked_gone = run(function (c) {
    otherPage = page([{ type: 'shared_ref', props: { sharedId: 42, sharedName: 'Gone' }, children: [] }]);
}, function (c) { _sdCommentsMakeDesignable(c.block); });
process.stdout.write(JSON.stringify(out));
JS;

	$res = comments_widget_designer_test_node(array('_sdCommentsPickWidget', '_sdCommentsNamedRows', '_sdCommentsRowConfigs', '_sdCommentsUnloadedRefs',
		'_sdCommentsTreeOf', '_sdCommentsDesignWidget', '_sdCommentsRefNode', '_sdCommentsBlockNode', '_sdCommentsSwapOffered', '_sdCommentsMakeDesignable',
		'_sdCommentsBackToBlock'), $script);
	if ($res === null) {
		return;
	}

	$block_tree = '{"type":"root","props":{},"children":[{"type":"container","props":{},"children":[{"_id":"sd_101","type":"region","props":{"regionType":"comments_block","regionName":""},"children":[]}]}]}';

	pg_assert_same($block_tree, $res['read_only_make']['tree'], 'a page held by a colleague keeps its block');
	pg_assert_same(array('nudge'), $res['read_only_make']['log'], 'and the operator is told why');
	pg_assert_same(array(), $res['read_only_make']['created'], 'no widget is made for it');
	pg_assert_same($block_tree, $res['content_make']['tree'], 'a content-level operator is not offered the change');
	pg_assert_same(array(), $res['content_make']['log']);

	$made = $res['made'];
	pg_assert_same(1, count($made['created']), 'with no widget in the design one is made');
	pg_assert_same('design-comments-widget', $made['created'][0]['name'], 'named after the design');
	pg_assert_same('filter', $made['created'][0]['cfg']['featured'], 'showing only the featured comments until Show all, as the block does');
	pg_assert_same('comments', $made['created'][0]['starter'], 'from the starter layout');
	pg_assert_contains('"type":"shared_ref","props":{"sharedId":77,"sharedName":"design-comments-widget"},"children":[]', $made['tree'], 'its placement takes the block\'s place');
	pg_assert_false(strpos($made['tree'], 'comments_block') !== false, 'the block is gone');
	pg_assert_same('shared_ref', $made['selected'], 'the placement is selected');
	pg_assert_same(1, count(array_keys($made['log'], 'undo', true)), 'one undo step');

	$reused = $res['reused'];
	pg_assert_same(array(), $reused['created'], 'the design\'s widget is reused');
	pg_assert_contains('"sharedId":5', $reused['tree'], 'its placement takes the block\'s place');
	$toast = '';
	foreach ($reused['log'] as $line) {
		if (strpos($line, 'toast:success:') === 0) {
			$toast = $line;
		}
	}
	pg_assert_contains('&quot;Talk &lt;b&gt;&quot;', $toast, 'the toast names the widget, escaped once');
	pg_assert_false(strpos($toast, '&amp;') !== false, 'and not twice');
	pg_assert_contains('Its display options are shared with the other pages that show it.', $toast, 'a reused widget that does not filter says its options are shared');

	pg_assert_contains('toast:warning:This page already shows a Comments widget.', implode("\n", $res['already']['log']), 'a page with a widget already is refused');
	pg_assert_contains('comments_block', $res['already']['tree'], 'and keeps its block');

	pg_assert_contains('"sharedId":5', $res['read_only_back']['tree'], 'a page held by a colleague keeps its widget');
	pg_assert_same(array('nudge'), $res['read_only_back']['log'], 'and the operator is told why');
	pg_assert_contains('"type":"region","props":{"regionType":"comments_block","regionName":""},"children":[]', $res['back']['tree'], 'back to the fixed block');
	pg_assert_false(strpos($res['back']['tree'], 'shared_ref') !== false, 'the placement is gone');
	pg_assert_same('region', $res['back']['selected'], 'the block is selected');
	pg_assert_same(1, count(array_keys($res['back']['log'], 'undo', true)), 'one undo step');

	$trip = $res['round_trip'];
	pg_assert_same(array(), $trip['created'], 'back and again: no second widget is made');
	pg_assert_contains('"sharedId":66,"sharedName":"Forum replies"', $trip['tree'], 'the widget sent back is placed again');
	pg_assert_false(strpos($trip['tree'], 'comments_block') !== false, 'in the block\'s place');
	pg_assert_false(in_array('list', $trip['log'], true), 'found without asking the server');

	$named = $res['named_row'];
	pg_assert_same(array(), $named['created'], 'the design\'s unplaced widget is reused');
	pg_assert_contains('"sharedId":88,"sharedName":"design-comments-widget"', $named['tree'], 'the row named after the design is placed');
	pg_assert_true(in_array('list', $named['log'], true), 'after asking the server for the rows');
	pg_assert_true(in_array('fetch:88', $named['log'], true), 'its layout is fetched to draw it');
	pg_assert_same(1, count(array_keys($named['log'], 'undo', true)), 'one undo step');

	$parked = $res['parked'];
	pg_assert_same(array(), $parked['created'], 'a widget placed only on a tab not opened yet is reused, no second one is made');
	pg_assert_contains('"sharedId":91,"sharedName":"Forum talk"', $parked['tree'], 'it takes the block\'s place under its own name');
	pg_assert_false(strpos($parked['tree'], 'comments_block') !== false, 'the block is gone');
	pg_assert_true(in_array('list', $parked['log'], true), 'its kind is read from the rows');
	pg_assert_true(in_array('fetch:91', $parked['log'], true), 'its layout is fetched to draw it');
	$parked_toast = '';
	foreach ($parked['log'] as $line) {
		if (strpos($line, 'toast:success:') === 0) {
			$parked_toast = $line;
		}
	}
	pg_assert_contains('&quot;Forum talk&quot;', $parked_toast, 'the toast names it');
	pg_assert_contains('Its display options are shared with the other pages that show it.', $parked_toast, 'and says its options are shared, read from its row');
	pg_assert_same(1, count(array_keys($parked['log'], 'undo', true)), 'one undo step');

	$in_component = $res['parked_component'];
	pg_assert_same(array(), $in_component['created'], 'a widget inside a component of a parked tab is reused');
	pg_assert_contains('"sharedId":92,"sharedName":"Thread"', $in_component['tree'], 'it takes the block\'s place');
	pg_assert_same(1, count(array_keys($in_component['log'], 'fetch:40', true)), 'the component is loaded once');

	$no_list = $res['parked_no_list'];
	pg_assert_same(array(), $no_list['created'], 'without the rows a parked tab\'s widget is still reused');
	pg_assert_contains('"sharedId":93,"sharedName":"Q&A"', $no_list['tree'], 'it takes the block\'s place under its own name');
	pg_assert_same(1, count(array_keys($no_list['log'], 'fetch:93', true)), 'it is loaded once, to learn its kind');
	pg_assert_same(1, count(array_keys($no_list['log'], 'undo', true)), 'one undo step');

	$gone = $res['parked_gone'];
	pg_assert_same(1, count($gone['created']), 'nothing found: one widget is made');
	pg_assert_same(1, count(array_keys($gone['log'], 'fetch:42', true)), 'a component the server lacks is asked for once');
	pg_assert_contains('"sharedId":77', $gone['tree'], 'the new widget is placed');
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
