<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for screen instructions: no panel heading keeps its text in a
 * hover-only popover (an h2 cannot take focus, so keyboard, touch and screen
 * reader users never get it); the instructions print as visible text, and the
 * image picker's tile details open on focus as well as on hover.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function screen_help_visible_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_screen_help_visible_no_heading_popover()
{
	$root = dirname(__DIR__) . '/pinegrap/';

	// view_fields.php's heading popover is held elsewhere; router.php's error
	// page sits outside the panel frame.
	$exempt = array('view_fields.php', 'router.php');

	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	$offenders = array();

	foreach ($iterator as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}

		$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));

		if (in_array($relative, $exempt, true) || strpos($relative, 'assets/lib/') === 0 || strpos($relative, 'data/') === 0) {
			continue;
		}

		if (!preg_match_all('/<h2\b[^>]*>/s', (string) file_get_contents($file->getPathname()), $matches)) {
			continue;
		}

		foreach ($matches[0] as $tag) {
			if (strpos($tag, 'data-bs-content') !== false) {
				$offenders[] = $relative;
			}
		}
	}

	pg_assert_same(array(), $offenders, 'h2 with a hover-only data-bs-content');
}

function test_screen_help_visible_navbar_titles()
{
	foreach (array('send_email_campaign.php') as $path) {
		pg_assert_true(!preg_match('/<span[^>]*class="navbar-text[^"]*"[^>]*data-bs-content/s', screen_help_visible_source($path)), $path . ': navbar title keeps a hover-only popover');
	}
}

function test_screen_help_visible_instructions_printed()
{
	$visible = array(
		'includes/templates/duplicate_folder.php' => "lang('Duplicate creates a new folder with a copy of each page",
		'includes/templates/edit_auto_dialog.php' => "lang('Create a new auto dialog that can automatically popup for visitors.",
		'includes/templates/edit_key_code.php' => "lang('Create one or more key codes",
		'includes/templates/generate_layout.php' => "lang('Copy & paste the code below into your custom layout.')",
		'edit_custom_style.php' => "'heading_description' => lang('Update this HTML template that is applied to all associated pages.')",
		'add_submitted_form.php' => "'heading_description' => lang('Manually create a submitted form for this custom form.",
		'preview_form.php' => "lang('The form layout will look like this when displayed within a page.')",
	);

	foreach ($visible as $path => $needle) {
		$source = screen_help_visible_source($path);
		$at = strpos($source, $needle);
		pg_assert_true($at !== false, $path . ': instruction missing');

		// The screen's description under the heading is printed as is.
		if (strpos($needle, "'heading_description'") === 0) {
			continue;
		}

		// The instruction sits inside a visible form-text paragraph.
		$open = strrpos(substr($source, 0, (int) $at), '<p');
		pg_assert_true($open !== false && strpos(substr($source, $open, (int) $at - $open), 'form-text') !== false, $path . ': instruction is not a visible form-text paragraph');
	}
}

