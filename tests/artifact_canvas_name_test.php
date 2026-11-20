<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's name on the panel's screens: the texts that
 * name it say Artifact Canvas, in Turkish "Yapıt Tuvali", and no screen of
 * these files says Design Canvas any more.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

/**
 * The files whose texts name the image editor by its new name.
 *
 * @return array
 */
function artifact_canvas_name_files()
{
	return array(
		'view_folders.php',
		'edit_design_file.php',
		'edit_file.php',
		'translations.php',
		'includes/workspace/file_edits.php',
		'includes/settings/features.php',
		'includes/fn/system_status.php',
		'includes/fn/design_file_list_edit.php',
		'includes/fn/content.php',
		'includes/dashboard/widgets/widget_18.php',
	);
}

function test_artifact_canvas_name_replaces_design_canvas_in_the_screens()
{
	foreach (artifact_canvas_name_files() as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_true(strpos($source, 'Design Canvas') === false, $file . ' no longer says Design Canvas');
		pg_assert_true(strpos($source, 'Artifact Canvas') !== false, $file . ' says Artifact Canvas');
	}
}

function test_artifact_canvas_name_is_translated()
{
	$map = pg_test_language_map('tr');

	pg_assert_same('Yapıt Tuvali', $map['Artifact Canvas'] ?? null, 'the name');
	pg_assert_same('Bu görseli Yapıt Tuvali\'nde düzenle', $map['Edit this image in the Artifact Canvas'] ?? null, 'a sentence is built around the name');
	pg_assert_same('Yapıt Tuvali yükleniyor…', $map['The Artifact Canvas is loading…'] ?? null, 'the channel\'s loading text');

	foreach ($map as $english => $turkish) {
		if (strpos($english, 'Artifact Canvas') !== false) {
			pg_assert_true(strpos($turkish, 'Yapıt Tuvali') !== false, $english . ' names Yapıt Tuvali');
			pg_assert_true(strpos($turkish, 'Tasarım Tuvali') === false, $english . ' keeps no old name');
		}
	}
}

function test_artifact_canvas_name_retires_the_old_keys_of_these_screens()
{
	$map = pg_test_language_map('tr');

	foreach (array('Edit this image in the Design Canvas', 'Edit in the Design Canvas', 'The Design Canvas is loading…', 'Largest Video Or Sound In The Design Canvas (MB)') as $old) {
		pg_assert_true(!isset($map[$old]), $old . ' is removed');
	}
}
