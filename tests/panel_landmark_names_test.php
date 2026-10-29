<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pin for the panel's landmark names: the toolbar (Button Bar) and the
 * sticky save bar (data edit buttons) are named through lang(), never with a
 * literal English aria-label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_panel_landmark_names_are_translated()
{
	// Files still printing the literal because an open PR held them at the time.
	$allowed = array(
		'add_email_campaign.php' => true,  // held by an open PR at the time
		'edit_email_campaign.php' => true, // held by an open PR at the time
		'view_contacts.php' => true,       // held by an open PR at the time
	);

	$root = dirname(__DIR__) . '/pinegrap/';
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	$found = array();

	foreach ($iterator as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}

		$path = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));

		if (isset($allowed[$path])) {
			continue;
		}

		if (preg_match('/aria-label="(Button Bar|data edit buttons)/', (string) file_get_contents($file->getPathname()))) {
			$found[] = $path;
		}
	}

	sort($found);
	pg_assert_same(array(), $found, 'literal landmark aria-label');
}
