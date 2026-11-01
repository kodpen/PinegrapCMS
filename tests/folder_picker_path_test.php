<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Folder pickers filtered by access type (private-access pickers): a picked
 * folder whose parents are left out is named with their path, so eight
 * "private" folders under different parents can be told apart.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_folder_option_label_without_path()
{
	pg_assert_same('private', pg_folder_option_label('private', array()), 'a folder whose parents are listed keeps its name');
}

function test_folder_option_label_with_path()
{
	pg_assert_same('pinegrap_cafe › private', pg_folder_option_label('private', array('pinegrap_cafe')), 'the left-out parent leads the name');
	pg_assert_same('Site › pinegrap_cafe › private', pg_folder_option_label('private', array('Site', 'pinegrap_cafe')), 'every left-out parent is named in order');
}

function test_select_folder_passes_left_out_parents()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/auth.php');

	pg_assert_contains('pg_folder_option_label(', $source, 'select_folder() names a folder through the path helper');
}
