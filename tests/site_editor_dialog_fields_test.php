<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The fields of CKEditor's dialogs on a site page in Edit mode (#9117):
 * livesite.src.css and livesite.min.css set the editor skin's own width,
 * padding and select arrow again over a theme's rules, instead of an
 * inherited width that left a text field at 157 px in a 450 px column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The rules of a stylesheet with every space dropped, so the source and the
// minified file read alike.
function site_editor_dialog_fields_css($file)
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$css = preg_replace('~/\*.*?\*/~s', '', $css);

	return str_replace(';}', '}', preg_replace('/\s+/', '', $css));
}

function test_site_editor_dialog_fields_take_the_skin_values()
{
	foreach (array('livesite.src.css', 'livesite.min.css') as $file) {
		$css = site_editor_dialog_fields_css($file);

		$rules = array(
			'input.cke_dialog_ui_input_text{width:100%!important;height:auto!important;padding:4px6px!important}' => 'a text field fills its column',
			'input.cke_dialog_ui_input_text:focus{padding-left:5px!important}' => 'the focus border as in the skin',
			'select.cke_dialog_ui_input_select{width:calc(100%-2px)!important;height:28px!important;padding:3px3px3px6px!important;background-image:none!important;-webkit-appearance:menulist!important;-moz-appearance:menulist!important;appearance:auto!important}' => 'a select with its arrow',
			'background-color:#fff!important' => 'a white field over a theme\'s grey',
		);

		foreach ($rules as $rule => $label) {
			pg_assert_true(strpos($css, $rule) !== false, $file . ': ' . $label);
		}

		pg_assert_false(strpos($css, 'width:inherit!important') !== false, $file . ': no inherited width');
		pg_assert_false(strpos($css, 'padding:0!important;text-transform') !== false, $file . ': no zero padding');
	}
}
