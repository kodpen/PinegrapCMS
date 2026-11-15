<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Messages node's settings (includes/fn/designer_messages.php):
 * the alert look and the dismiss button, the page-level placeholder that
 * carries them, the unchanged output of a node with neither set, and the
 * editor's twin in assets/js/style_designer.src.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// With no setting the box is the one liveform::output_errors() prints, so a
// published page keeps its output.
function test_designer_messages_settings_default_output_unchanged()
{
	$html = pg_messages_alert_html('error', '<p class="error">Wrong code</p>', pg_messages_settings(array()), 'mt-2');
	pg_assert_same(
		'<div class="software_error alert alert-danger alert-dismissible show mt-2" role="alert">'
		. '<button type="button" style="display:none;" class="btn-close no-popover" data-bs-dismiss="alert" aria-label="' . h(lang('Close')) . '"></button>'
		. '<h5 class="alert-heading description">' . lang('An error occurred') . ':</h5>'
		. '<p class="error">Wrong code</p></div>',
		$html
	);
	$notice = pg_messages_alert_html('notice', '<p class="notice">Saved</p>');
	pg_assert_contains('class="software_notice alert alert-success alert-dismissible show" role="alert"', $notice);
	pg_assert_same(false, strpos($notice, 'alert-heading'), 'a notice has no heading');
	pg_assert_contains('class="software_warning alert alert-warning alert-dismissible show"', pg_messages_alert_html('warning', ''));
}

function test_designer_messages_settings_looks_and_button()
{
	$accent = pg_messages_alert_html('error', '', array('look' => 'accent', 'dismiss' => false));
	pg_assert_contains('alert-dismissible show border-0 border-start border-4 border-danger"', $accent);
	$solid = pg_messages_alert_html('notice', '', array('look' => 'solid', 'dismiss' => true));
	pg_assert_contains('show text-bg-success"', $solid);
	pg_assert_contains('<button type="button" class="btn-close no-popover btn-close-white" data-bs-dismiss="alert"', $solid, 'shown, white on a filled box');
	$solid_warning = pg_messages_alert_html('warning', '', array('look' => 'solid', 'dismiss' => true));
	pg_assert_same(false, strpos($solid_warning, 'btn-close-white'), 'the yellow box keeps the dark button');
	pg_assert_contains('show py-2 small"', pg_messages_alert_html('notice', '', array('look' => 'compact')));
	pg_assert_contains('alert-dismissible show"', pg_messages_alert_html('notice', '', array('look' => 'bogus" onclick="x')), 'an unknown look is the standard one');
	pg_assert_contains('mt-2 &quot;x"', pg_messages_alert_html('notice', '', array(), 'mt-2 "x'), 'the CSS class is escaped');
}

function test_designer_messages_settings_from_props()
{
	pg_assert_same(array('look' => '', 'dismiss' => false), pg_messages_settings(null));
	pg_assert_same(array('look' => 'solid', 'dismiss' => true), pg_messages_settings(array('alertLook' => 'solid', 'dismissButton' => true)));
	pg_assert_same(array('look' => '', 'dismiss' => false), pg_messages_settings(array('alertLook' => array('x'), 'dismissButton' => '')));
}

function test_designer_messages_settings_marker()
{
	pg_assert_same(array('look' => '', 'dismiss' => false), pg_messages_marker_settings('<!--pg-messages-placeholder-->'));
	pg_assert_same(array('look' => 'accent', 'dismiss' => true), pg_messages_marker_settings('<!--pg-messages-placeholder:accent:1-->'));
	pg_assert_same(array('look' => '', 'dismiss' => true), pg_messages_marker_settings('<!--pg-messages-placeholder::1-->'));
	pg_assert_same(array('look' => '', 'dismiss' => false), pg_messages_marker_settings('<!--pg-messages-placeholder:evil:0-->'));
	pg_assert_same(array('look' => '', 'dismiss' => false), pg_messages_marker_settings('<!--pg-other-->'));
}

// A widget's Messages node carries its settings into the boxes it prints.
function test_designer_messages_settings_tree_node()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('software' => array('liveforms' => array(
		'login' => array(0 => array('email' => array('error' => true, 'error_message' => 'Enter your e-mail'))),
	)));
	$node = array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => '', 'formName' => 'login',
		'alertLook' => 'accent', 'dismissButton' => true), 'children' => array());
	$html = _render_tree_node($node, 0);
	pg_assert_contains('alert-danger alert-dismissible show border-0 border-start border-4 border-danger"', $html);
	pg_assert_contains('<button type="button" class="btn-close no-popover" data-bs-dismiss="alert"', $html);
	pg_assert_contains('Enter your e-mail', $html);
	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// The editor's content registry knows the same looks and writes the
// placeholder the server reads.
function test_designer_messages_settings_editor_twin()
{
	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$at = strpos($js, "        messages: {\n");
	pg_assert_true($at !== false, 'messages entry found');
	$entry = substr($js, $at, strpos($js, "        breadcrumb: {\n", $at) - $at);
	pg_assert_contains("looks: ['', 'accent', 'solid', 'compact']", $entry);
	pg_assert_contains("defaultProps: { cssClass: '', alertLook: '', dismissButton: false }", $entry);
	pg_assert_contains("' border-0 border-start border-4 border-' + colour", $entry);
	pg_assert_contains("'<!--pg-messages-placeholder:' + look + ':' + (p.dismissButton ? '1' : '0') + '-->", $entry);
	pg_assert_contains("return pad + '<!--pg-messages-placeholder-->\\n';", $entry, 'no setting: the old placeholder');
}
