<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ad form shared by the create and the edit screens
 * (includes/forms/ad_form.php): the values a save writes, and that both
 * screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/ad_form.php');

function ad_form_test_liveform($fields)
{
	return new class($fields) {
		private $fields;

		public function __construct($fields)
		{
			$this->fields = $fields;
		}

		public function get_field_value($name)
		{
			return isset($this->fields[$name]) ? $this->fields[$name] : null;
		}
	};
}

function test_ad_form_values_keep_the_insert_and_update_order()
{
	$liveform = ad_form_test_liveform(array(
		'name' => 'Spring',
		'content' => '<a href="/site/sale">Sale</a>',
		'caption' => '<img src="/site/banner.png">',
		'ad_region_id' => 7,
		'label' => 'Tab',
		'sort_order' => '',
	));

	pg_assert_same(
		array('Spring', '<a href="{path}sale">Sale</a>', '<img src="{path}banner.png">', '7', 'Tab', ''),
		pg_ad_form_values($liveform, '/site/'),
		'name, content, caption, region, label, sort order; content and caption store the site path as {path}');
}

function test_ad_form_screens_use_shared_form()
{
	foreach (array('add_ad.php', 'edit_ad.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/ad_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_ad_form_cards($liveform)', $source, $file . ' prints the shared cards');
		pg_assert_contains('pg_ad_form_validate($liveform, $user, ', $source, $file . ' runs the shared checks');
		pg_assert_contains('array_merge(pg_ad_form_values($liveform), ', $source, $file . ' saves the shared values');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('get_token_field()', $source, $file . ' prints the token');
		pg_assert_false(strpos($source, 'Dynamic Ad Region Display Options') !== false, $file . ' keeps no copy of the cards');
	}

	pg_assert_contains('pg_ad_form_validate($liveform, $user, null)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_ad.php'), 'create checks the name against every ad');
	pg_assert_contains("pg_ad_form_validate(\$liveform, \$user, (string) \$liveform->get_field_value('id'))", (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_ad.php'), 'edit leaves its own ad out of the name check');
}
