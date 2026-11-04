<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Site Settings dialog is printed for role <= 2 only, settings screens
 * admit managers and above, and add_dynamic_region.php admits
 * administrators. Screens that admit others draw the settings links and the
 * Create button only for the roles that can open them. These checks pin the
 * source of those places.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_links_by_role_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_settings_links_by_role_add_order_settings_item_is_for_managers()
{
	$source = settings_links_by_role_source('add_order.php');
	pg_assert_contains("\$output_can_settings = \$can_erp && ((int) \$user['role'] < 3);", $source, 'the ERP card item is for role < 3');
	pg_assert_same(false, strpos($source, "manage_erp_settings"), 'the ERP settings right does not open the commerce settings');
}

function test_settings_links_by_role_erp_settings_links_only_for_managers()
{
	$source = settings_links_by_role_source('erp_settings.php');
	pg_assert_contains("\$can_site_settings = ((int) \$user['role'] < 3);", $source, 'the settings links follow the dialog gate');

	// Role < 3 gets one link to the Accounting pane; the Seller Information
	// card is drawn only for role 3, who has no Site Settings: plain text.
	pg_assert_contains("<a href=\"' . h(pg_settings_link('accounting')) . '\">", $source, 'role < 3 is linked to the pane');
	foreach (array('organization', 'settings', 'edoc') as $name) {
		pg_assert_contains("' . \$" . $name . "_place . '", $source, $name . ' place named as text');
		pg_assert_same(false, strpos($source, '$' . $name . '_url'), $name . ' is not linked from the role 3 card');
	}
}


function test_settings_links_by_role_dynamic_regions_create_for_administrators()
{
	$source = settings_links_by_role_source('view_regions.php');
	$gate = strpos($source, "if ((\$kind !== 'dynamic') || (\$user['role'] < 1)) {");
	$hidden = strpos($source, "\$create_url = '';", (int) $gate);
	pg_assert_true(($gate !== false) && ($hidden !== false), 'below administrator the dynamic regions list has no Create button and no rows');
	pg_assert_contains("return (\$kind === 'dynamic') ? (\$user['role'] < 1) : (\$user['role'] <= 1);", $source, 'a dynamic region changes in place for administrators only');
}
