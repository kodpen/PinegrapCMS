<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what an auto dialog shows (content, component_id): an address
 * or a designed pop-up drawn from a shared component. The reader and its
 * defaults, the page's guard and its <template>, the API's values and
 * refusals, the migration step and the dialog script in both scripts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/auto_dialogs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/auto_dialog_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/design_dialogs_regions.php');

function test_auto_dialog_content_reads_as_an_address_when_unset()
{
	$address = array('content' => 'url', 'component_id' => 0);

	pg_assert_same($address, pg_auto_dialog_content(array()), 'a row stored before the columns keeps its address');
	pg_assert_same($address, pg_auto_dialog_content(array('content' => 'banner', 'component_id' => '-3')), 'an unknown value and a negative id');
	pg_assert_same($address, pg_auto_dialog_content(array('content' => array('component'), 'component_id' => '1.5')), 'a list and a decimal');
	pg_assert_same(array('content' => 'component', 'component_id' => 12), pg_auto_dialog_content(array('content' => ' Component ', 'component_id' => '12')), 'trimmed and case-blind');
	pg_assert_same(0, pg_auto_dialog_content(array('component_id' => '99999999999'))['component_id'], 'past INT UNSIGNED');
	pg_assert_same(array('url', 'component'), pg_auto_dialog_content_choices(), 'the address first, as the default');
}

function test_auto_dialog_designed_popup_is_printed_only_with_its_component()
{
	$rows = array(
		array('id' => '1', 'url' => '/offer', 'content' => 'url'),
		array('id' => '2', 'url' => '', 'content' => 'component', 'component_id' => '7'),
		array('id' => '3', 'url' => '/kept', 'content' => 'component', 'component_id' => '8'),
		array('id' => '4', 'url' => 'javascript:alert(1)', 'content' => 'component', 'component_id' => '7'),
	);

	pg_assert_same(array('1'), array_column(pg_auto_dialogs_for_page($rows), 'id'), 'no component found: only the address is printed');
	pg_assert_same(array('1', '2', '4'), array_column(pg_auto_dialogs_for_page($rows, array('7')), 'id'), 'a designed pop-up needs its component, not its address');

	$page = pg_auto_dialogs_on_page(pg_auto_dialogs_for_page($rows, array(7)), 'home');
	pg_assert_same('', $page[1]['url'], 'a designed pop-up opens no address');
	pg_assert_same('', $page[2]['url'], 'not even a stored one');
	pg_assert_same(array('component', 7), array($page[1]['content'], $page[1]['component_id']), 'the script reads what it shows');
	pg_assert_same('/offer', $page[0]['url'], 'an address stays');
}

function test_auto_dialog_templates_draw_each_designed_popup_once()
{
	$rows = array(
		array('id' => 1, 'content' => 'url', 'component_id' => 0),
		array('id' => 2, 'content' => 'component', 'component_id' => 7),
		array('id' => 5, 'content' => 'component', 'component_id' => 7),
	);
	$drawn = array();
	$html = pg_auto_dialog_templates($rows, function ($id) use (&$drawn) {
		$drawn[] = $id;

		return '<form data-c="' . $id . '"></form>';
	});

	pg_assert_same('<template id="pg-popup-2"><form data-c="7"></form></template><template id="pg-popup-5"><form data-c="7"></form></template>', $html, 'one template per pop-up, keyed by its id');
	pg_assert_same(array(7, 7), $drawn, 'the address kind draws nothing');
	pg_assert_same('', pg_auto_dialog_templates(array(), 'strlen'), 'no rows');
}

function test_auto_dialog_form_content_problems()
{
	$found = function ($rows) {
		return array(7);
	};

	pg_assert_same(array(), pg_auto_dialog_form_content_problems(array('content' => '', 'component_id' => ''), $found), 'blank is the address');
	pg_assert_same(array(), pg_auto_dialog_form_content_problems(array('content' => 'url', 'component_id' => '99'), $found), 'an address keeps any component unchecked');
	pg_assert_same(array(), pg_auto_dialog_form_content_problems(array('content' => 'component', 'component_id' => '7'), $found), 'a component that exists');
	pg_assert_same(array('component_id'), array_keys(pg_auto_dialog_form_content_problems(array('content' => 'component', 'component_id' => '8'), $found)), 'one that does not, or is a widget');
	pg_assert_same(array('component_id'), array_keys(pg_auto_dialog_form_content_problems(array('content' => 'component', 'component_id' => ''), $found)), 'none given');
	pg_assert_same(array('content'), array_keys(pg_auto_dialog_form_content_problems(array('content' => 'banner'), $found)), 'a value not offered');
	pg_assert_same(array('content'), array_keys(pg_auto_dialog_form_content_problems(array('content' => array()), $found)), 'a list');
}

function test_auto_dialog_content_over_the_api()
{
	$row = array('id' => 4, 'name' => 'Welcome', 'enabled' => 1, 'url' => '/welcome', 'width' => 400, 'height' => 0, 'delay' => 5, 'frequency' => 24, 'page' => '', 'content' => 'component', 'component_id' => '7', 'created_timestamp' => 0, 'last_modified_timestamp' => 0);

	$values = api_design_auto_dialog_values($row, array());
	pg_assert_same(array('component', 7), array($values['content'], $values['component_id']), 'an update without them keeps what it shows');
	pg_assert_same('url', api_design_auto_dialog_values($row, array('content' => 'url'))['content'], 'the sent value is laid over');

	$out = api_design_auto_dialog_present($row);
	pg_assert_same(array('component', 7), array($out['content'], $out['component_id']), 'the answer carries them');
	pg_assert_same(null, api_design_auto_dialog_present(array('component_id' => 0) + $row)['component_id'], 'null for none');

	$prepare = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/design_dialogs_regions.php');
	$prepare = substr($prepare, strpos($prepare, "\nfunction api_design_auto_dialog_prepare("));
	pg_assert_true(strpos($prepare, 'pg_auto_dialog_form_content_problems($content)') < strpos($prepare, "foreach (array('name' => 100, 'url' => 255)"), 'what it shows is checked before the URL is asked for');
	pg_assert_contains("(\$field === 'name') || (\$content['content'] === 'url')", $prepare, 'a designed pop-up needs no URL');

	$schema = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
	pg_assert_same(2, substr_count($schema, "array('name' => 'content',   'in' => 'body', 'type' => 'enum', 'values' => array('url', 'component')"), 'create and update take content');
	pg_assert_same(2, substr_count($schema, "array('name' => 'component_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'nullable' => true"), 'and the component');
}

function test_auto_dialog_content_migration_step()
{
	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains("upgrade_2026_11_0_auto_dialog_content();     // 11.0.242", $migration, 'called in the open version');
	pg_assert_contains("install_add_column('auto_dialogs', 'content', \"VARCHAR(10) NOT NULL DEFAULT 'url' AFTER place\");", $migration, 'content, an address by default');
	pg_assert_contains("install_add_column('auto_dialogs', 'component_id', \"INT UNSIGNED NOT NULL DEFAULT 0 AFTER content\");", $migration, 'the component, none by default');
	pg_assert_true(strpos($migration, "'auto_dialog_content'") === false, 'no table');
}

function test_auto_dialog_page_prints_the_templates_before_the_script()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	pg_assert_same(2, substr_count($page, '$auto_dialog_templates = pg_auto_dialog_templates('), 'the preview and the enabled dialogs both draw their templates');
	pg_assert_same(2, substr_count($page, 'return pg_common_region_component_html($component_id, $mode, $email);'), 'drawn as a converted region draws its component');
	pg_assert_contains("\$output_js = \$auto_dialog_templates . '<script>' . \$output_js . '</script>';", $page, 'before the script that looks for them');
}

function test_auto_dialog_script_shows_designed_content()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js');

	foreach (array(
		"if (auto_dialog.content === 'component') {" => 'a designed pop-up',
		"document.getElementById('pg-popup-' + auto_dialog.id);" => 'finds its template',
		"dialog = {content: document.importNode(template.content, true)};" => 'shows a copy of it',
		"if (!content && !software.is_web_address(url)) {" => 'only an address is checked as one',
		"var frame = document.createElement(content ? 'div' : 'iframe');" => 'no iframe for content',
		"event.target.closest('[data-pg-popup-close]')" => 'its marked close button closes it',
		"event.target.closest('[data-pg-popup-take]')" => 'its marked action counts taken',
		"opened.addEventListener('submit', function() {" => 'a form sent inside counts taken',
		"dialog.pg-dialog-content{padding:0;font:inherit;" => 'the page\'s own type',
	) as $needle => $why) {
		pg_assert_contains($needle, $src, 'frontend.src.js: ' . $why);
	}

	$min = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.min.js');

	foreach (array(
		'if("component"===a.content){var c=document.getElementById("pg-popup-"+a.id);if(!c||!c.content)return;t={content:document.importNode(c.content,!0)}}',
		'if(!k&&!software.is_web_address(a))return!1;',
		'c=document.createElement(k?"div":"iframe")',
		'e.target.closest("[data-pg-popup-close]")&&(e.preventDefault(),f())',
		'e.target.closest("[data-pg-popup-take]")&&software.count_auto_dialog(a,"taken")',
		'i.addEventListener("submit",function(){software.count_auto_dialog(a,"taken")})',
		'dialog.pg-dialog-content{padding:0;font:inherit;',
	) as $needle) {
		pg_assert_contains($needle, $min, 'frontend.min.js: ' . $needle);
	}
}

function test_auto_dialog_screens_ask_what_it_shows_only_with_a_visual_design()
{
	pg_assert_true(pg_auto_dialog_form_asks_content(true, array(4)), 'columns and a design');
	pg_assert_false(pg_auto_dialog_form_asks_content(true, array()), 'a site with no visual design keeps the address kind');
	pg_assert_false(pg_auto_dialog_form_asks_content(true, array(0)), 'no real design id');
	pg_assert_false(pg_auto_dialog_form_asks_content(false, array(4)), 'the columns not added yet');
	pg_assert_same(array('url', 'component'), array_values(pg_auto_dialog_form_content_kinds()), 'the address first, then a designed pop-up');
	pg_assert_same(pg_auto_dialog_content_choices(), array_values(pg_auto_dialog_form_content_kinds()), 'the kinds the reader takes');
}

function test_auto_dialog_url_is_asked_only_with_the_address_kind()
{
	pg_assert_true(pg_auto_dialog_form_url_required(null), 'the screen does not ask');
	pg_assert_true(pg_auto_dialog_form_url_required('url'), 'an address');
	pg_assert_true(pg_auto_dialog_form_url_required(''), 'blank is the address');
	pg_assert_true(pg_auto_dialog_form_url_required(array('component')), 'a list is no kind');
	pg_assert_false(pg_auto_dialog_form_url_required('component'), 'a designed pop-up');
	pg_assert_false(pg_auto_dialog_form_url_required(' Component '), 'read case-blind');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/auto_dialog_form.php');
	pg_assert_contains('pg_auto_dialog_form_url_required($asked ? $liveform->get_field_value(\'content\') : null)', $source, 'the save asks for the URL by kind');
	pg_assert_contains('pg_auto_dialog_form_content_problems(pg_auto_dialog_form_content_sent($liveform))', $source, 'and checks the component sent');
}

function test_auto_dialog_form_content_values_leave_the_columns_when_not_asked()
{
	pg_assert_same(null, pg_auto_dialog_form_content_values(array('content' => 'component', 'component_id' => '7'), false), 'not asked: as stored');
	pg_assert_same(array('content' => 'component', 'component_id' => 7), pg_auto_dialog_form_content_values(array('content' => 'component', 'component_id' => '7'), true), 'a designed pop-up');
	pg_assert_same(array('content' => 'url', 'component_id' => 7), pg_auto_dialog_form_content_values(array('content' => 'url', 'component_id' => '7'), true), 'an address keeps the component chosen');
	pg_assert_same(array('content' => 'url', 'component_id' => 0), pg_auto_dialog_form_content_values(array('content' => '', 'component_id' => ''), true), 'blank is the address');
	pg_assert_same(array('content' => '', 'component_id' => ''), pg_auto_dialog_form_content_sent(array()), 'nothing sent');
}

function test_auto_dialog_form_component_options_escape_the_names()
{
	$options = pg_auto_dialog_form_component_options(array(
		array('id' => '3', 'name' => 'Offer <b>'),
		array('id' => '5', 'name' => 'Offer <b>'),
		array('id' => '0', 'name' => 'none'),
	));

	pg_assert_same(array('' ), array_slice(array_values($options), 0, 1), 'a blank prompt first');
	pg_assert_same(3, count($options), 'two components, a name used twice kept twice, no id 0');
	pg_assert_same(array('label' => 'Offer &lt;b&gt;', 'value' => '3'), $options['c3'], 'the name escaped');
	pg_assert_same('5', $options['c5']['value'], 'the second one');
}

function test_auto_dialog_form_designer_links()
{
	pg_assert_same(array('open' => 'edit_system_style.php?component=7', 'new' => 'edit_system_style.php?id=4'), pg_auto_dialog_form_designer_links('7', array(9, 4), true), 'open the chosen one, design a new one in the first design');
	pg_assert_same(array('open' => '', 'new' => ''), pg_auto_dialog_form_designer_links(0, array(4), false), 'none chosen; a manager gets no new link');
	pg_assert_same('', pg_auto_dialog_form_designer_links(7, array(), true)['new'], 'no design, no new link');
}

function test_auto_dialog_screens_draw_what_it_shows()
{
	$template = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/edit_auto_dialog.php');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_auto_dialog.php');

	pg_assert_contains("lang('What does it show?')", $template, 'the question');
	pg_assert_contains('$content_asked = pg_auto_dialog_form_content_asked();', $template, 'asked only with a visual design');
	pg_assert_contains('pg_auto_dialog_form_component_options(pg_auto_dialog_component_rows())', $template, 'the shared components offered');
	pg_assert_contains('pg_designer_is_full($user)', $template, 'the new link for those who may design');
	pg_assert_contains('data-pg-auto-dialog-shows="url"', $template, 'the URL row follows the kind');
	pg_assert_contains('url.required = !designed;', $template, 'the URL required only with the address');
	pg_assert_contains("assign_field_value('content', \$content['content'])", $edit, 'the edit screen loads the kind');
	pg_assert_contains("assign_field_value('component_id'", $edit, 'and the component');
}
