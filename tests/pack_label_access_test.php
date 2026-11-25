<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the label door of a "Packing only" user
 * (pg_pack_label_user_can(), includes/fn/pick_pack.php): only the outbound
 * label of an address whose pack that user checked opens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/pick_pack.php');

function test_pack_label_opens_for_the_packer_of_a_checked_pack()
{
	$packer = array('id' => 7, 'role' => 3, 'manage_ecommerce' => 0, 'manage_packing' => 1);
	$label = array('id' => 40, 'ship_to_id' => 12, 'is_return' => 0);
	$pack = array('ship_to_id' => 12, 'status' => 1, 'packer_id' => 7);

	pg_assert_true(pg_pack_label_user_can($packer, $label, $pack), 'the packer opens the label of the pack they checked');
	pg_assert_true(pg_pack_label_user_can($packer, $label, array('ship_to_id' => 12, 'status' => '1', 'packer_id' => '7')), 'database strings read as numbers');
}

function test_pack_label_stays_closed_otherwise()
{
	$packer = array('id' => 7, 'role' => 3, 'manage_ecommerce' => 0, 'manage_packing' => 1);
	$label = array('id' => 40, 'ship_to_id' => 12, 'is_return' => 0);
	$pack = array('ship_to_id' => 12, 'status' => 1, 'packer_id' => 7);

	pg_assert_false(pg_pack_label_user_can($packer, $label, null), 'no pack for the address');
	pg_assert_false(pg_pack_label_user_can($packer, $label, array('status' => 0) + $pack), 'a pack still open');
	pg_assert_false(pg_pack_label_user_can($packer, $label, array('status' => 2) + $pack), 'a short pack');
	pg_assert_false(pg_pack_label_user_can($packer, $label, array('packer_id' => 8) + $pack), 'another packer checked it');
	pg_assert_false(pg_pack_label_user_can($packer, $label, array('ship_to_id' => 13) + $pack), 'the pack of another address');
	pg_assert_false(pg_pack_label_user_can($packer, array('is_return' => 1) + $label, $pack), 'a return label');
	pg_assert_false(pg_pack_label_user_can($packer, array('label_removed_at' => 1700000000) + $label, $pack), 'a cancelled label');
	pg_assert_false(pg_pack_label_user_can(array('manage_packing' => 0) + $packer, $label, $pack), 'no Packing only right');
	pg_assert_false(pg_pack_label_user_can(array('id' => 0) + $packer, $label, $pack), 'no user id');
	pg_assert_false(pg_pack_label_user_can(array('role' => 4) + $packer, $label, $pack), 'a visitor role');
}
