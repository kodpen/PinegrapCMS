<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_file_stamped_address() (includes/fn/contacts.php): a panel
 * picture drawn by a file's own address carries the file's change stamp, so
 * a picture saved again under its name is not shown from a browser's copy;
 * and the places that draw a person's face use it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_file_stamped_address_carries_the_change_time()
{
	pg_assert_same('/face.gif?v=1762950000', pg_file_stamped_address('/', 'face.gif', 1762950000));
	pg_assert_same('/site/face.gif?v=12', pg_file_stamped_address('/site/', 'face.gif', '12'), 'a stamp read as text');
}

function test_file_stamped_address_without_a_stamp_stays_bare()
{
	pg_assert_same('/face.gif', pg_file_stamped_address('/', 'face.gif', 0));
	pg_assert_same('/face.gif', pg_file_stamped_address('/', 'face.gif', null), 'no files row');
	pg_assert_same('/face.gif', pg_file_stamped_address('/', 'face.gif', -5));
	pg_assert_same('/face.gif', pg_file_stamped_address('/', 'face.gif', 'x'));
}

// Each place that draws a person's face by its file reads the stamp with
// the name.
function test_file_stamped_address_used_by_the_faces()
{
	$root = PG_FUNCTIONS_DIR . '/';
	$places = array(
		'includes/fn/output.php' => array('files.timestamp AS file_timestamp', "pg_file_stamped_address(PATH, \$person['file_name']"),
		'includes/workspace/people.php' => array('f.timestamp AS image_file_timestamp', "pg_file_stamped_address(PATH, \$row['image_file_name']"),
		'chat.php' => array('files.timestamp AS image_file_timestamp', 'pg_file_stamped_address(PATH, $image_file_name, $image_file_timestamp)'),
		'includes/dashboard/widgets/widget_4.php' => array('files.timestamp AS image_file_timestamp', "\$eg_person['image_file_timestamp'] ?? 0"),
		'includes/fn/widgets_account.php' => array('files.timestamp AS image_file_timestamp', "pg_file_stamped_address(\$output, \$row['image_file_name']"),
		'includes/fn/contact_list.php' => array('SELECT id, name, timestamp FROM files', 'pg_file_stamped_address(PATH, $file_name'),
		'edit_user.php' => array('pg_contact_picture_address($contact_file_id)'),
		'edit_contact.php' => array("pg_contact_picture_address(\$row['file_id'])"),
		'view_order.php' => array('pg_contact_picture_address($contact_file_id)'),
	);

	foreach ($places as $file => $needles) {
		$source = (string) file_get_contents($root . $file);

		foreach ($needles as $needle) {
			pg_assert_contains($needle, $source, $file);
		}
	}

	$chat = (string) file_get_contents($root . 'chat.php');
	pg_assert_same(3, substr_count($chat, 'files.timestamp AS image_file_timestamp'), 'every chat query that reads the picture reads its stamp');
}
