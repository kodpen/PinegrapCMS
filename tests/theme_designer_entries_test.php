<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that no panel entry opens the theme designer. Its client script is
 * gone, so the screen stopped with a script error on load; a new theme, the
 * "Edit Theme" buttons of a system theme and the preview's edit link open the
 * theme's CSS editor instead. theme_designer.php stays on the disk, and the
 * menu still maps it to its section.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// No file of the product links theme_designer.php, apart from the screen
// itself and the menu's section mapping.
function test_theme_designer_entries_no_panel_link()
{
	$skip = array(
		'theme_designer.php' => true,
		'get_theme_designer_module.php' => true,
	);
	$found = array();
	$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($directory as $file) {
		$name = $file->getFilename();

		if (isset($skip[$name]) || (substr($name, -7) === '.min.js')) {
			continue;
		}

		if ((substr($name, -4) !== '.php') && (substr($name, -3) !== '.js')) {
			continue;
		}

		$source = str_replace("case 'theme_designer.php':", '', (string) file_get_contents($file->getPathname()));

		if (strpos($source, 'theme_designer.php') !== false) {
			$found[] = substr($file->getPathname(), strlen(PG_FUNCTIONS_DIR) + 1);
		}
	}

	sort($found);

	pg_assert_same(array(), $found, 'no panel entry links theme_designer.php');
}

// A new theme opens in the CSS editor, which shows the "created" notice and
// goes back to the theme list.
function test_theme_designer_entries_new_theme_opens_css_editor()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/add_theme_file.php');

	pg_assert_true(strpos($source, "new liveform('edit_theme_css')") !== false, 'the notice goes to the CSS editor\'s form');
	pg_assert_true(strpos($source, "/edit_theme_css.php?id=' . \$theme_id . '&send_to=' . urlencode(PATH . SOFTWARE_DIRECTORY . '/view_themes.php')") !== false, 'a new theme opens in the CSS editor and returns to the theme list');
}
