<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the long settings texts kept outside the config row
 * (includes/fn/config_text.php): which names are taken, and that the table is
 * created by a migration step and kept by a reinstall.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A name is lower-case letters, digits and underscores, 1 to 64 characters.
function test_config_text_name_valid()
{
	pg_assert_true(pg_config_text_name_valid('ecommerce_kvkk_consent_text'), 'a setting name');
	pg_assert_true(pg_config_text_name_valid(str_repeat('a', 64)), 'the key column width');
	pg_assert_false(pg_config_text_name_valid(str_repeat('a', 65)), 'too long');
	pg_assert_false(pg_config_text_name_valid(''), 'empty');
	pg_assert_false(pg_config_text_name_valid('Name'), 'upper case');
	pg_assert_false(pg_config_text_name_valid("a' OR '1"), 'quotes');
	pg_assert_false(pg_config_text_name_valid(array('a')), 'not a string');
}

// A bad name reads as '' and is never written, without touching the database.
function test_config_text_refuses_bad_names()
{
	pg_assert_same('', pg_config_text('Bad Name'));
	pg_assert_false(pg_config_text_save('Bad Name', 'x'));
}

// The table comes from the open version's step and is one of the software's
// tables, so a reinstall into the same database replaces it.
function test_config_text_table_is_created_and_listed()
{
	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');

	pg_assert_contains("install_create_table('config_text'", $migration, 'created by a migration step');
	pg_assert_contains("\t\t'config_text',\n", $install, 'listed in get_tables()');
}
