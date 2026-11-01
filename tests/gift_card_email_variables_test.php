<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The product screen's gift card e-mail draws one button per variable the
 * sending code replaces, so the operator can put ^^code^^ into the message.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/product_builder.php');

function test_gift_card_email_variables_match_the_sender()
{
	$names = array('code', 'amount', 'from_name', 'recipient_email_address', 'message', 'delivery_date');

	pg_assert_same($names, pg_pb_gift_card_variable_names(), 'the six variables submit_order replaces');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php');

	foreach ($names as $name) {
		pg_assert_true(strpos($source, "'name' => '" . $name . "'") !== false, $name . ' is replaced when the card is sent');
	}
}

function test_gift_card_email_variable_buttons_are_drawn()
{
	$html = pg_pb_gift_card_variable_buttons();

	pg_assert_same(6, substr_count($html, 'class="pg-pb-giftvar '), 'one button per variable');
	pg_assert_true(strpos($html, 'data-pg-pb-giftvar="code"') !== false, 'the code button names its variable');
	pg_assert_true(strpos($html, '>^^code^^</button>') !== false, 'the button shows the token');
	pg_assert_same(6, substr_count($html, 'type="button"'), 'a button never submits the form');
}

function test_gift_card_email_screen_uses_the_buttons()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');

	pg_assert_true(strpos($source, "\$output_gift_card_variables = '';") === false, 'the variable list is no longer left empty');
	pg_assert_same(2, substr_count($source, "' . \$output_gift_card_variables . '"), 'subject and body both list the variables');
}
