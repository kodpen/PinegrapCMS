<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the express order's recipient loop (includes/fn/widgets_cart.php,
 * _eo_render_recipient_loop() in includes/fn/widgets_express_order.php):
 * the per-recipient address rows are built after the messages node has taken
 * the form from the session, so they read the copy taken before it, and a
 * recipient's name and address stay in their boxes when the page shows an
 * error or a notice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One address box bound to the recipient's first name.
function _eorl_template()
{
	return array(array('type' => 'semantic', 'props' => array(
		'tag' => 'input',
		'_attrs' => array(array('name' => 'type', 'value' => 'text')),
		'_bindings' => array('eo_field' => 'shipping_first_name'),
	), 'children' => array()));
}

// The copy keeps the recipient's value after the session entry is gone: the
// loop's field walkers read it like the liveform.
function test_express_order_recipient_loop_reads_the_copy()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION['software']['liveforms']['express_order'][0] = array('shipping_7_first_name' => array('value' => 'Ayşe'));
	$copy = new pg_liveform_snapshot($_SESSION['software']['liveforms']['express_order'][0]);
	unset($_SESSION['software']['liveforms']['express_order']);
	$wrap = array('type' => 'root', 'props' => array(), 'children' => _eorl_template());
	_eo_apply_field_bindings($wrap, $copy, 7);
	$_SESSION = $saved;
	$attrs = array();
	foreach ($wrap['children'][0]['props']['_attrs'] as $a) {
		$attrs[$a['name']] = $a['value'];
	}
	pg_assert_same('shipping_7_first_name', $attrs['name'] ?? '', 'box renamed for the recipient');
	pg_assert_same('Ayşe', $attrs['value'] ?? '', 'value kept');
}

// The designed tree hands the loop the copy, not the consumed liveform.
function test_express_order_recipient_loop_gets_the_copy()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');
	$at = strpos($src, '_eo_render_recipient_loop($eo_recipient_tmpl');
	pg_assert_true($at !== false, 'loop call found');
	$call = substr($src, $at, 120);
	pg_assert_true(strpos($call, '$_eo_lf_rows)') !== false, 'loop reads the copy');
}
