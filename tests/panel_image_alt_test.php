<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for panel pictures: decorative thumbnails and icons carry an
 * empty alt so a screen reader never reads their file name or repeats the
 * text beside them, and the Paraşüt emblem says what it means.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_image_alt_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Every <img> start tag in the source that carries no alt attribute.
function panel_image_alt_missing($source)
{
	preg_match_all('/<img\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/', $source, $matches);
	$missing = array();
	foreach ($matches[0] as $tag) {
		if (!preg_match('/\salt=/', $tag)) {
			$missing[] = $tag;
		}
	}
	return $missing;
}

function test_contacts_list_photo()
{
	$source = panel_image_alt_source('includes/fn/contact_list.php');

	pg_assert_contains('lazy" alt="" src=', $source, 'photo alt is empty: the name is in the row');
}

function test_order_view_customer_photo()
{
	$source = panel_image_alt_source('view_order.php');

	pg_assert_same(array(), panel_image_alt_missing($source), 'img without alt');
}

function test_parasut_emblem_names_its_meaning()
{
	$source = panel_image_alt_source('includes/fn/ecommerce.php');

	pg_assert_contains('alt="\' . h($title) . \'" src="assets/images/parasut-emblem.png"', $source, 'emblem alt is its meaning');
}

function test_warning_heading_reads_warning_once()
{
	$source = panel_image_alt_source('liveform.class.php');

	pg_assert_false(strpos($source, 'alt="Warning"') !== false, 'English alt before the translated heading');
	// One icon serves both headings (site markup; the panel uses a Bootstrap icon).
	pg_assert_same(1, substr_count($source, 'icon_warning.png" alt=""'), 'warning icon silent');
}
