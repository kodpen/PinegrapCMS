<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the back arrow and list breadcrumb of the contact, user and ad
 * screens opened with send_to: the arrow goes where Save goes, and the list
 * breadcrumb keeps the filtered view only when send_to is that same list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of a panel screen.
function back_arrow_send_to_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function back_arrow_send_to_with($send_to, $callback)
{
	$had = array_key_exists('send_to', $_REQUEST);
	$old = $had ? $_REQUEST['send_to'] : null;

	if ($send_to === null) {
		unset($_REQUEST['send_to']);
	} else {
		$_REQUEST['send_to'] = $send_to;
	}

	try {
		return $callback();
	} finally {
		if ($had) {
			$_REQUEST['send_to'] = $old;
		} else {
			unset($_REQUEST['send_to']);
		}
	}
}

function test_send_to_list_url_keeps_the_same_list_only()
{
	$list = '/pinegrap/view_contacts.php';

	pg_assert_same('/pinegrap/view_contacts.php?filter=all_subscribers', back_arrow_send_to_with('/pinegrap/view_contacts.php?filter=all_subscribers', function () use ($list) {
		return pg_send_to_list_url($list);
	}), 'the filtered list it came from');
	pg_assert_same($list, back_arrow_send_to_with('/pinegrap/edit_user.php?id=4', function () use ($list) {
		return pg_send_to_list_url($list);
	}), 'another screen leaves the breadcrumb on the list');
	pg_assert_same($list, back_arrow_send_to_with('/pinegrap/edit_user.php?send_to=/pinegrap/view_contacts.php', function () use ($list) {
		return pg_send_to_list_url($list);
	}), 'the list named in a query string is not the list');
	pg_assert_same($list, back_arrow_send_to_with(null, function () use ($list) {
		return pg_send_to_list_url($list);
	}), 'no send_to');
	pg_assert_same($list, back_arrow_send_to_with('//evil.example/view_contacts.php', function () use ($list) {
		return pg_send_to_list_url($list);
	}), 'an address pg_send_to_url() refuses');
}

function test_back_arrow_contact_and_user_screens_follow_send_to()
{
	$screens = array(
		'edit_contact.php' => array('view_contacts.php', 'Contacts'),
		'add_contact.php' => array('view_contacts.php', 'Contacts'),
		'import_contacts.php' => array('view_contacts.php', 'Contacts'),
		'add_user.php' => array('view_users.php', 'Users'),
		'import_users.php' => array('view_users.php', 'Users'),
	);

	foreach ($screens as $file => $screen) {
		$source = back_arrow_send_to_source($file);
		$url = "OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/" . $screen[0] . "'";

		pg_assert_contains("'cancel' => array('enable' => 'true', 'url' => pg_send_to_url(" . $url . "))", $source, $file . ' goes back where Save goes');
		pg_assert_contains("array('label' => lang('" . $screen[1] . "'), 'url' => pg_send_to_list_url(" . $url . "))", $source, $file . ' breadcrumb keeps the filtered list');
	}
}

function test_back_arrow_edit_ad_follows_send_to()
{
	$source = back_arrow_send_to_source('edit_ad.php');

	pg_assert_contains("'cancel' => array('enable' => 'true', 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_ads.php'))", $source, 'Edit Ad goes back to the site page or list it came from');
	pg_assert_contains("'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_ads.php?filter=all_ad_regions'", $source, 'the Edit Ad breadcrumb stays on its list');
}

function test_back_arrow_edit_comment_follows_send_to()
{
	$source = back_arrow_send_to_source('edit_comment.php');

	pg_assert_contains("'cancel' => array('enable' => 'true', 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_comments.php'))", $source, 'Edit Comment goes back to the site page or dashboard it came from');
	pg_assert_contains("array('label' => lang('Comments'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_comments.php')", $source, 'the Edit Comment breadcrumb stays on its list');
	pg_assert_contains("'/edit_comment.php?id=' . \$liveform->get_field_value('id') . '&send_to=' . urlencode(\$liveform->get_field_value('send_to'))", $source, 'a refused Save keeps send_to for the arrow');
}
