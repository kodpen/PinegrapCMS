<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A picture the panel draws by a file's bare address carries the file's
 * change stamp (pg_stamped_url(), pg_file_picture_url()), so a picture saved
 * again from the Design Canvas shows its new pixels at once: the header's
 * face, Workspace and chat people, the contact and user screens, the
 * contacts list and the order's customer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_picture_stamp_url()
{
	pg_assert_same('/face.gif?v=1794493938', pg_stamped_url('/face.gif', 1794493938), 'a stamp after the address');
	pg_assert_same('/get_file.php?n=face.gif&v=12', pg_stamped_url('/get_file.php?n=face.gif', 12), 'after a query with &');
	pg_assert_same('/face.gif', pg_stamped_url('/face.gif', 0), 'no stamp, no change');
	pg_assert_same('/face.gif', pg_stamped_url('/face.gif', -5), 'nor a negative one');
	pg_assert_same('', pg_stamped_url('', 12), 'no address stays empty');
}

function test_picture_stamp_follows_the_file()
{
	pg_assert_same('', pg_file_picture_url(''), 'no name, no address');
	pg_assert_same('/site/no-such-picture-xyz.gif', pg_file_picture_url('no-such-picture-xyz.gif', '/site/'), 'a missing file keeps its bare address');

	$folder = defined('FILE_DIRECTORY_PATH') ? FILE_DIRECTORY_PATH : PG_FUNCTIONS_DIR . '/data/files';
	$name = 'pg-stamp-test-' . getmypid() . '.gif';
	$path = $folder . '/' . $name;

	if (!is_dir($folder) || (@file_put_contents($path, 'GIF89a') === false)) {
		return;
	}

	touch($path, 1794000000);
	clearstatcache();
	pg_assert_same('/site/' . $name . '?v=1794000000', pg_file_picture_url($name, '/site/'), 'the file\'s change time');

	touch($path, 1794000600);
	clearstatcache();
	pg_assert_same('/site/' . $name . '?v=1794000600', pg_file_picture_url($name, '/site/'), 'a save moves the stamp');

	unlink($path);
}

function test_picture_stamp_where_the_panel_draws_people()
{
	$read = function ($file) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	};

	pg_assert_contains("return pg_file_picture_url(\$row['image_file_name']);", $read('includes/workspace/people.php'), 'ws_avatar_url()');
	pg_assert_contains('return pg_file_picture_url($image_file_name);', $read('chat.php'), 'pg_chat_avatar_src(), the chat and Who is online');
	pg_assert_contains("\$picture = pg_file_picture_url(\$person['file_name']);", $read('includes/fn/output.php'), 'the header\'s face');
	pg_assert_contains("pg_file_picture_url(\$row['image_file_name'], \$output)", $read('includes/fn/widgets_account.php'), 'the site\'s account widget');
	pg_assert_contains('h(pg_file_picture_url($contact_picture_name))', $read('view_order.php'), 'the order\'s customer');
	pg_assert_contains('h(pg_file_picture_url($picture_name))', $read('edit_contact.php'), 'Edit Contact');
	pg_assert_contains('pg_file_picture_url($contact_picture_name)', $read('edit_user.php'), 'Edit User');
	pg_assert_contains('h(pg_file_picture_url($file_name))', $read('includes/fn/contact_list.php'), 'the contacts list');
}
