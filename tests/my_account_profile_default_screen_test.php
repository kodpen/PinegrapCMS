<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the default My Account Profile screen (get_my_account_profile(),
 * drawn by my_account_profile.php when the site has no My Account Profile
 * page): it is called with no properties, so the page id is read with a
 * default; its labels go through lang(); and its country list is named and
 * ordered in the site's language like the other country lists.
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

function my_account_profile_default_screen_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_my_account_profile.php');
}

// get_my_account_profile_screen() calls it with no properties: an unguarded
// read wrote "Undefined array key page_id" on every open.
function test_my_account_profile_default_screen_page_id_has_a_default()
{
	$source = my_account_profile_default_screen_test_source();

	pg_assert_contains("\$page_id = \$properties['page_id'] ?? 0;", $source, 'page id read with a default');
	pg_assert_same(0, substr_count($source, "\$page_id = \$properties['page_id'];"), 'no unguarded page id');

	$forms = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/forms.php');
	pg_assert_contains('get_my_account_profile() . output_footer_secure()', $forms, 'the screen still calls it with no properties');
}

// Salutation, Suffix, Fax and Organization were printed as plain English.
function test_my_account_profile_default_screen_labels_are_translated()
{
	$source = my_account_profile_default_screen_test_source();
	$tr = pg_test_language_map('tr');

	foreach (array('Salutation', 'Suffix', 'Fax', 'Organization') as $label) {
		pg_assert_contains("<td>' . lang('" . $label . "') . '</td>", $source, $label . ' through lang()');
		pg_assert_same(0, substr_count($source, '<td>' . $label . '</td>'), $label . ' not plain text');
		pg_assert_true(isset($tr[$label]) && ($tr[$label] !== $label), $label . ' has a Turkish text');
	}
}

// The country select listed the stored English names in their order
// ("Tunisia, Turkey, Turkmenistan") while checkout shows "Tunus, Türkiye".
function test_my_account_profile_default_screen_countries_in_the_site_language()
{
	$source = my_account_profile_default_screen_test_source();

	pg_assert_contains("pg_geo_country_label(\$country['name'], \$country['code'])", $source, 'countries named in the site language');
	pg_assert_contains('pg_geo_sort_by_label($country_labels)', $source, 'countries ordered by that name');
	pg_assert_contains("\$business_country_options[\$label] = \$country['code'];", $source, 'the option value stays the code');
	pg_assert_same(0, substr_count($source, "\$business_country_options[\$country['name']]"), 'no option keyed by the stored name');
}
