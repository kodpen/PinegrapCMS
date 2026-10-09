<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure helpers of includes/fn/update.php that the Repair
 * Software screen uses: reading the version history from the source of
 * versions.php, the versions the upgrade steps can be run again from, and
 * whether the update server's version allows a repair.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The real versions.php, read as text: oldest first, the 2026 entries in it,
// the open version last.
function test_update_versions_from_real_source()
{
	$versions = pg_upgrade_versions_from_source((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/versions.php'));

	pg_assert_same('2017.2', $versions[0], 'first version');
	pg_assert_contains('2026', $versions, 'the 2026 entry');
	pg_assert_same('2026.4.8', $versions[count($versions) - 1], 'last version');
	pg_assert_same(count($versions), count(array_unique($versions)), 'each version once');
	pg_assert_same($versions, pg_upgrade_versions_list(), 'the list of this installation');
}

// Only the single-quoted numbers of the returned array count: not the ones
// in comments, not a double-quoted one, and the order is kept.
function test_update_versions_from_fake_source()
{
	$source = "<?php\n"
		. "// Legacy versions (2017.2 - 2023.3.1) live in legacy.php; '2019.1' is an example.\n"
		. "if (!defined('INSTALL_OR_UPDATE')) { exit; }\n"
		. "return array(\n"
		. "\t'2020.1',\n"
		. "\t'2026', // 2025.9 was skipped\n"
		. "\t\"2026.0.5\",\n"
		. "\t'2026.1.10',\n"
		. "\t'2026.1.9',\n"
		. ");\n"
		. "// '2030.1' after the array\n";

	pg_assert_same(array('2020.1', '2026', '2026.1.10', '2026.1.9'), pg_upgrade_versions_from_source($source));

	pg_assert_same(array(), pg_upgrade_versions_from_source('<?php echo 1;'), 'no array');
}

// From the first 2026 version up to the one before the installed version,
// later years included.
function test_update_rerun_choices()
{
	$versions = array('2017.2', '2023.3.1', '2026', '2026.1', '2026.4.7', '2026.4.8');

	pg_assert_same(array('2026', '2026.1', '2026.4.7'), pg_upgrade_rerun_choices($versions, '2026.4.8'), 'from 2026.4.8');
	pg_assert_same(array('2026', '2026.1'), pg_upgrade_rerun_choices($versions, '2026.4.7'), 'from 2026.4.7');
	pg_assert_same(array(), pg_upgrade_rerun_choices($versions, '2026'), 'nothing before 2026 is offered');
	pg_assert_same(array(), pg_upgrade_rerun_choices($versions, '2099.1'), 'a version outside the list');

	// A later year stays in the list.
	pg_assert_same(array('2026', '2026.1', '2027.1'), pg_upgrade_rerun_choices(array('2023.3.1', '2026', '2026.1', '2027.1', '2027.2'), '2027.2'), 'from 2027.2');

	$real = pg_upgrade_rerun_choices(null, '2026.4.8');

	pg_assert_same('2026', $real[0], 'the real list starts at 2026');
	pg_assert_same('2026.4.7', $real[count($real) - 1], 'the real list ends before 2026.4.8');
}

// version_compare() orders the numbers the way the repair needs, also where
// a part has two digits.
function test_update_version_compare_orders_release_numbers()
{
	pg_assert_true(version_compare('2026.4.10', '2026.4.9', '>'), '2026.4.10 > 2026.4.9');
	pg_assert_true(version_compare('2026.4', '2026.4.1', '<'), '2026.4 < 2026.4.1');
	pg_assert_true(version_compare('2026.4.8', '2026.4.8', '=='), 'equal');
}

// Refused with checks off, without a version and with an older one; the same
// version repairs, a newer one repairs and updates.
function test_update_repair_decision()
{
	$off = pg_update_repair_decision('2026.4.8', '2026.4.8', false);

	pg_assert_false($off['ok'], 'checks off');
	pg_assert_contains('SOFTWARE_UPDATE_CHECK', $off['message']);

	$none = pg_update_repair_decision('', '2026.4.8', true);

	pg_assert_false($none['ok'], 'no version');

	$garbage = pg_update_repair_decision('<html>', '2026.4.8', true);

	pg_assert_false($garbage['ok'], 'not a version');

	$older = pg_update_repair_decision('2026.4.7', '2026.4.8', true);

	pg_assert_false($older['ok'], 'older');
	pg_assert_contains('2026.4.7', $older['message']);
	pg_assert_contains('2026.4.8', $older['message']);

	$same = pg_update_repair_decision(' 2026.4.8 ', '2026.4.8', true);

	pg_assert_true($same['ok'], 'same');
	pg_assert_false($same['newer'], 'same is not newer');
	pg_assert_contains('2026.4.8', $same['message']);

	$newer = pg_update_repair_decision('2026.4.10', '2026.4.9', true);

	pg_assert_true($newer['ok'], 'newer');
	pg_assert_true($newer['newer'], 'newer is newer');
	pg_assert_contains('2026.4.10', $newer['message']);
}
