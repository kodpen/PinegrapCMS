<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what system widgets print: the editor's Preview and the canvas's
 * ghost records resolve the address placeholders the page resolves last
 * (pg_designer_resolve_output_placeholders()); rich text written for the
 * classic theme keeps its pictures in their column on a Bootstrap 5 page
 * (pg_img_fluid_alias()); the starter layouts put a long description in a
 * div and the account menu behind a button; the calendar's empty message
 * names no period (pg_sw_calendar_empty_message()); the product carousel's
 * links are named (pg_carousel_image_label()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// -- address placeholders in preview and ghost HTML ----------------------------

function test_designer_widget_output_resolves_path()
{
	pg_assert_same(
		'<p><img src="/cover26.jpg" class="w-100"> <a href="/blog">x</a></p>',
		pg_designer_resolve_output_placeholders('<p><img src="{path}cover26.jpg" class="w-100"> <a href="{PATH}blog">x</a></p>', '/', 'pinegrap'));
	pg_assert_same(
		'<img src="/site/pinegrap/images/x.png">',
		pg_designer_resolve_output_placeholders('<img src="{path}{software_directory}/images/x.png">', '/site/', 'pinegrap'));
}

// A replacement with $ or \ is copied as written, not read as a back reference.
function test_designer_widget_output_path_is_literal()
{
	pg_assert_same('<img src="/a$1\\b/x.jpg">', pg_designer_resolve_output_placeholders('<img src="{path}x.jpg">', '/a$1\\b/', ''));
}

function test_designer_widget_output_without_placeholders()
{
	pg_assert_same('', pg_designer_resolve_output_placeholders('', '/', ''));
	pg_assert_same('<p>No braces</p>', pg_designer_resolve_output_placeholders('<p>No braces</p>', '/', ''));
}

// The preview function resolves what it returns, so the ghost records cut
// from its answer are resolved as well.
function test_designer_widget_output_preview_resolves()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains('$out[(int)$sid] = pg_designer_resolve_output_placeholders((string)$html);', $source);
}

// -- img-responsive on a Bootstrap 5 page -------------------------------------

function test_designer_widget_output_fluid_alias()
{
	pg_assert_same(
		'<p><img src="{path}cover26.jpg" class="img-responsive img-fluid" alt=""></p>',
		pg_img_fluid_alias('<p><img src="{path}cover26.jpg" class="img-responsive" alt=""></p>'), 'the only class');
	pg_assert_same(
		"<img class='rounded img-responsive img-fluid shadow' src='/a.jpg'>",
		pg_img_fluid_alias("<img class='rounded img-responsive shadow' src='/a.jpg'>"), 'between others, single quotes');
	pg_assert_same(
		'<IMG SRC="/a.jpg" CLASS="img-responsive img-fluid" />',
		pg_img_fluid_alias('<IMG SRC="/a.jpg" CLASS="img-responsive" />'), 'upper-case markup');
}

function test_designer_widget_output_fluid_alias_leaves_the_rest()
{
	$kept = array(
		'<img class="img-responsive img-fluid" src="/a.jpg">'  => 'already fluid',
		'<img class="img-fluid img-responsive" src="/a.jpg">'  => 'fluid first',
		'<img class="img-responsive-lg" src="/a.jpg">'         => 'a longer class name',
		'<div class="img-responsive"><img src="/a.jpg"></div>' => 'not an image',
		'<p>img-responsive</p>'                                => 'a word in the text',
		'<img src="/a.jpg">'                                   => 'no class',
	);
	foreach ($kept as $html => $why) {
		pg_assert_same($html, pg_img_fluid_alias($html), $why);
	}
	pg_assert_same(
		'<img class="img-responsive img-fluid" src="/a.jpg"><img src="/b.jpg" class="x img-responsive img-fluid">',
		pg_img_fluid_alias('<img class="img-responsive" src="/a.jpg"><img src="/b.jpg" class="x img-responsive">'), 'every image');
}

// -- starter layouts -----------------------------------------------------------

// A product's long description is rich text with its own paragraphs: the
// catalog detail starter binds it to a div, never to a paragraph.
function test_designer_widget_output_description_in_a_div()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(preg_match("/createNode\('semantic', \{ tag: 'div'[^}]*\n\s*customName: _sdT\('Description'\),\n\s*_bindings: \{ text: '__description' \} \}\)/", $source) === 1, 'a div');
	pg_assert_true(preg_match("/contentType: 'paragraph',[^}]*\n[^}]*_bindings: \{ text: '__description' \}/", $source) === 0, 'no paragraph');
}

// The signed-in account menu of the login region opens from a real button
// (Space opens it as well as Enter), in the editor's starter layout and in
// every design template's own.
function test_designer_widget_output_account_toggle_is_a_button()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(preg_match("/createNode\('semantic', \{ tag: 'button', cssClass: 'btn btn-link [^']*dropdown-toggle',\n\s*customName: _sdT\('Account Button'\),\n\s*_attrs: \[\{ name: 'type', value: 'button' \}, \{ name: 'data-bs-toggle', value: 'dropdown' \}/", $source) === 1, 'starter layout');

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php') as $file) {
		$template = (string) file_get_contents($file);
		if (strpos($template, "lang('Account Button')") === false) continue;
		pg_assert_false(strpos($template, "\$el('a', 'd-flex align-items-center gap-2 link-body-emphasis text-decoration-none dropdown-toggle") !== false, basename($file) . ': no link toggle');
		pg_assert_true(preg_match("/\\\$el\('button', 'btn btn-link [^']*dropdown-toggle[^']*', lang\('Account Button'\)/", $template) === 1, basename($file) . ': a button');
	}
}

// -- calendar empty state ------------------------------------------------------

// One setting serves the month, the week and the upcoming list: the default
// names no period, and the month sentence the editor used to store reads as
// that default in either language.
function test_designer_widget_output_calendar_empty_message()
{
	$default = lang('There are no events to show.');
	pg_assert_same($default, pg_sw_calendar_empty_message(''), 'nothing set');
	pg_assert_same($default, pg_sw_calendar_empty_message(null), 'no setting');
	pg_assert_same($default, pg_sw_calendar_empty_message('There are no events this month.'), 'the old default');
	pg_assert_same($default, pg_sw_calendar_empty_message('Bu ay etkinlik yok.'), 'the old default, in Turkish');
	pg_assert_same('Nothing on the agenda.', pg_sw_calendar_empty_message('Nothing on the agenda.'), 'the operator\'s own');
}

// The editor writes and shows the same default, through its twin.
function test_designer_widget_output_calendar_empty_message_editor()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_same(0, substr_count($source, "_sdT('There are no events this month.')"), 'the month sentence is not written any more');
	pg_assert_contains("if (cfg.empty_message === undefined) cfg.empty_message = _sdT('There are no events to show.');", $source);
	pg_assert_contains("var _cvNoEvents   = _swCalendarEmptyMessage(_cfg.empty_message);", $source);
	pg_assert_contains("stored === 'There are no events this month.' || stored === 'Bu ay etkinlik yok.'", $source);
}

// -- carousel links are named ---------------------------------------------------

function test_designer_widget_output_carousel_image_label()
{
	pg_assert_same('Image 2 of 5', pg_carousel_image_label(2, 5));
}

// The slide links, the thumbnail links and the indicators wrap or are
// pictures with an empty alt: each carries the image's name, on the server
// and where the variant chooser rebuilds them.
function test_designer_widget_output_carousel_links_named()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_same(0, substr_count($php, 'aria-label="Slide '), 'no English slide label');
	pg_assert_same(3, substr_count($php, "aria-label=\"' . h(pg_carousel_image_label(\$_si + 1, count("), 'slide link, thumbnail link, indicator');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_civ_variants.js');
	pg_assert_same(0, substr_count($js, 'aria-label="Slide '), 'no English slide label in the variant chooser');
	pg_assert_same(3, substr_count($js, "aria-label=\"' + escAttr(imageLabel(i, gallery.length)) + '\""), 'the variant chooser names the same three');

	$catalog = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	pg_assert_contains("'image_of'           => lang(array('string' => 'Image {var:1} of {var:2}', 'vars' => array('{n}', '{total}'))),", $catalog, 'the words reach the script');
}

// Every widget's finished output goes through the alias before its srcset
// pass, outside an e-mail (after the finish pass, widgets_finish.php).
function test_designer_widget_output_fluid_alias_on_widgets()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains('if ($email) return pg_sw_finish_widget_html($out, $scope);', $source);
	pg_assert_contains('return pg_img_responsive_html(pg_img_fluid_alias(pg_sw_finish_widget_html($out, $scope)));', $source);
}
