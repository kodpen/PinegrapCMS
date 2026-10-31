<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for Site Log lines on actions that change data: the two order
 * exports, every Cloudflare Tools action and the Site Log's own Delete
 * buttons write a translated line on their success path only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function site_log_writes_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

/**
 * The text between the first $start after $from and the first $end after it.
 */
function site_log_writes_slice($source, $start, $end, $from = 0)
{
	$a = strpos($source, $start, $from);
	if ($a === false) {
		return '';
	}
	$b = strpos($source, $end, $a + strlen($start));
	return ($b === false) ? substr($source, $a) : substr($source, $a, $b - $a);
}

// Both exports count the orders the UPDATE changed and the card data cleared,
// and write the lines after the loop, never one per order.
function test_site_log_writes_order_exports()
{
	$source = site_log_writes_source('view_orders.php');

	$multiple = site_log_writes_slice($source, "UPDATE orders SET status = 'exported'", "get all custom shipping form data");
	$single_from = strpos($source, "'Export Orders (single file)'");
	pg_assert_true($single_from !== false, 'single-file branch found');
	$single = site_log_writes_slice($source, "UPDATE orders SET status = 'exported'", "else user did not request to export orders", (int) $single_from);

	foreach (array('multiple files' => $multiple, 'single file' => $single) as $label => $block) {
		pg_assert_true($block !== '', $label . ' export block found');
		pg_assert_true((bool) preg_match('/mysqli_affected_rows\(db::\$con\) > 0\)\s*\{\s*\$number_of_exported_orders\+\+;/', $block), $label . ': counts the orders the UPDATE changed');
		pg_assert_true((bool) preg_match('/if \(pg_order_card_data_clear\(\$order\[\'id\'\]\)\) \{\s*\$number_of_card_data_cleared\+\+;/', $block), $label . ': counts the card data cleared');
		pg_assert_contains("'{var:1} order(s) were exported.'", $block, $label . ': export line');
		pg_assert_contains("'Card data was removed from {var:1} order(s).'", $block, $label . ': card data line');
		pg_assert_same(2, substr_count($block, 'log_activity(lang('), $label . ': two lines');
	}
}

// Each Cloudflare action logs inside its success branch, next to its notice.
function test_site_log_writes_cloudflare_actions()
{
	$source = site_log_writes_source('cloudflare.php');

	$notices = array(
		"'Record added successfully.'",
		"'Record updated successfully.'",
		"'Record deleted successfully.'",
		"'Development Mode updated to {var:1}.'",
		"'Under Attack Mode updated to {var:1}.'",
		"'Always Use HTTPS updated to {var:1}.'",
		"'Brotli updated to {var:1}.'",
		"'Rocket Loader updated to {var:1}.'",
		"'Cache successfully purged.'",
	);

	foreach ($notices as $notice) {
		$at = strpos($source, $notice);
		pg_assert_true($at !== false, $notice . ' notice found');
		$else = strrpos(substr($source, 0, (int) $at), '} else {');
		$branch = site_log_writes_slice($source, '} else {', "\n        }", (int) $else);
		pg_assert_contains('log_activity(lang(', $branch, $notice . ' branch writes a line');
	}

	pg_assert_same(9, substr_count($source, 'log_activity(lang('), 'one line per action, none on failure');

	// A deleted record is named by its type and name, read before the DELETE.
	$delete = site_log_writes_slice($source, "if (\$action === 'delete_record') {", "// Development Mode toggle");
	$get = strpos($delete, "cf_request('GET'");
	$del = strpos($delete, "cf_request('DELETE'");
	pg_assert_true(($get !== false) && ($del !== false) && ($get < $del), 'the record is read before it is deleted');
}

// The Site Log's own Delete buttons leave a line after the delete.
function test_site_log_writes_site_log_deletes()
{
	$source = site_log_writes_source('view_log.php');

	$site = site_log_writes_slice($source, "isset(\$_POST['delete_all_site_logs']) && (MASS_DELETION === true)", "No site log were deleted");
	$delete = strpos($site, 'DELETE FROM log');
	$line = strpos($site, "log_activity(lang(array(\n                'string' => '{var:1} site log(s) were deleted.'");
	pg_assert_true(($delete !== false) && ($line !== false) && ($delete < $line), 'site logs: line written after the DELETE');

	$errors = site_log_writes_slice($source, "isset(\$_POST['delete_all_error_logs'])", "No error_log files were deleted.");
	$unlink = strrpos($errors, '@unlink(');
	$line = strpos($errors, "log_activity(lang(array('string' => '{var:1} error_log file(s) deleted.'");
	pg_assert_true(($unlink !== false) && ($line !== false) && ($unlink < $line), 'error logs: line written after the unlink loop');
}
