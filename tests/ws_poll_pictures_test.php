<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a picture on a poll option in tools/ws_poll.php: the
 * "label :: description :: #N" syntax, the message numbers it names, the
 * file id read from the channel and the body that carries it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/ws_poll.php');

// An option's last part "#N" is the picture of message N; the rest stays.
function test_ws_poll_option_split_picture()
{
	pg_assert_same(array('label' => 'Bar', 'description' => '', 'picture' => 0), ws_poll_option_split('Bar'));
	pg_assert_same(array('label' => 'Bar', 'description' => '', 'picture' => 412), ws_poll_option_split('Bar :: #412'));
	pg_assert_same(array('label' => 'Bar', 'description' => 'One bar a week', 'picture' => 412), ws_poll_option_split('Bar :: One bar a week :: #412'));
	pg_assert_same(array('label' => 'Bar', 'description' => 'a :: b', 'picture' => 0), ws_poll_option_split('Bar :: a :: b'), 'a "::" inside the description stays');
	pg_assert_same(array('label' => 'Bar', 'description' => 'Room #4', 'picture' => 0), ws_poll_option_split('Bar :: Room #4'), 'a "#" inside words is text');
}

// Each named message once, in order.
function test_ws_poll_picture_messages()
{
	pg_assert_same(array(7, 3), ws_poll_picture_messages(array('A :: #7', 'B', 'C :: x :: #3', 'D :: #7')));
	pg_assert_same(array(), ws_poll_picture_messages(array('A', 'B :: x')));
}

// The file id comes from the message's file, only when it is a picture.
function test_ws_poll_picture_file_id()
{
	$answer = array('data' => array(array('id' => 412, 'file' => array('id' => 88, 'kind' => 'image'))));
	pg_assert_same(array('file_id' => 88, 'error' => ''), ws_poll_picture_file_id($answer, 412));
	pg_assert_same('message 413 is not in the channel', ws_poll_picture_file_id($answer, 413)['error'], 'a later message the page held is not it');
	pg_assert_same('message 5 carries no picture', ws_poll_picture_file_id(array('data' => array(array('id' => 5, 'file' => array('id' => 9, 'kind' => 'pdf')))), 5)['error']);
	pg_assert_same('message 5 carries no picture', ws_poll_picture_file_id(array('data' => array(array('id' => 5, 'file' => null))), 5)['error']);
	pg_assert_same(0, ws_poll_picture_file_id(null, 5)['file_id']);
}

// The option sends file_id; a picture with no known file id stops the body.
function test_ws_poll_ask_body_picture()
{
	$body = ws_poll_ask_body('Q', array('Bar :: Weekly :: #412', 'Dots :: #413', 'Boxes'), array('recommend' => true, 'pictures' => array(412 => 88, 413 => 89)))['body'];
	pg_assert_same(array('label' => 'Bar', 'description' => 'Weekly', 'file_id' => 88, 'recommended' => true), $body['options'][0]);
	pg_assert_same(array('label' => 'Dots', 'file_id' => 89), $body['options'][1]);
	pg_assert_same('Boxes', $body['options'][2]);
	pg_assert_same('the picture of message 413 is not known', ws_poll_ask_body('Q', array('A :: #413', 'B'), array('pictures' => array(412 => 88)))['error']);
	pg_assert_same('the picture of message 413 is not known', ws_poll_ask_body('Q', array('A :: #413', 'B'))['error']);
}

// again needs the channel only when an option names a picture.
function test_ws_poll_settings_picture_channel()
{
	$env = array('PG_WS_SITE' => 'https://example.com', 'PG_WS_KEY' => 'k', 'PG_WS_SECRET' => 's');
	pg_assert_same(array(), ws_poll_settings('again', array(), $env)['missing']);
	pg_assert_same(array('PG_WS_CHANNEL'), ws_poll_settings('again', array(), $env, true)['missing']);
	pg_assert_same(array(), ws_poll_settings('again', array('channel' => '4'), $env, true)['missing']);
}

// The usage text names the picture syntax.
function test_ws_poll_usage_picture()
{
	pg_assert_true(strpos(ws_poll_usage(), '"label :: description :: #N"') !== false);
}
