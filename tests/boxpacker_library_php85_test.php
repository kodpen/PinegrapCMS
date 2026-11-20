<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the bundled BoxPacker (includes/boxpacker, 1.x) stays quiet on
 * PHP 8.5 where the product uses it: the real-time shipping rates in
 * shipping.php pack an order's items into the site's containers. The library
 * is patched locally: TestBox and TestItem declare their properties, the
 * heaps' compare() methods carry #[\ReturnTypeWillChange] and VolumePacker's
 * nullable parameters are marked "?". The test runner lets a deprecation
 * raised inside a bundled library through, so the library is loaded and
 * exercised in a PHP process of its own that reports every error.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_boxpacker_packs_as_shipping_does_without_notices()
{
	$script = tempnam(sys_get_temp_dir(), 'pg_boxpacker');
	$code = <<<'PHP'
<?php
error_reporting(E_ALL);
ini_set("display_errors", "1");
set_error_handler(function ($severity, $message, $file, $line) {
	echo "ERROR " . $message . " (" . basename($file) . ":" . $line . ")\n";
	return true;
});
require $argv[1] . "/init.php";

// The calls of shipping.php: a container's outer and inner sizes are the
// same, its maximum weight is large and every item is kept flat. Sizes are
// width, length, height and weight.
function pg_pack($containers, $items)
{
	$packer = new \DVDoug\BoxPacker\Packer();

	foreach ($containers as $key => $container) {
		$packer->addBox(new \DVDoug\BoxPacker\TestBox($key, $container[0], $container[1], $container[2], $container[3], $container[0], $container[1], $container[2], 9999999));
	}

	foreach ($items as $key => $item) {
		$packer->addItem(new \DVDoug\BoxPacker\TestItem($key, $item[0], $item[1], $item[2], $item[3], true));
	}

	try {
		$boxes = $packer->pack();
	} catch (Exception $e) {
		// shipping.php logs the packer with print_r() after a failure.
		$dump = print_r($packer, true);

		return $e->getMessage() . " (dump: " . substr_count($dump, "[keepFlat] => 1") . " items, " . substr_count($dump, "[maxWeight] => 9999999") . " containers)";
	}

	$lines = array();

	foreach ($boxes as $box) {
		$keys = array();

		foreach (clone $box->getItems() as $item) {
			$keys[] = $item->getDescription();
		}

		sort($keys);
		$lines[] = $box->getBox()->getReference() . " " . $box->getBox()->getOuterWidth() . "x" . $box->getBox()->getOuterLength() . "x" . $box->getBox()->getOuterDepth() . " " . $box->getWeight() . " [" . implode(",", $keys) . "]";
	}

	sort($lines);

	return implode(" / ", $lines);
}

// Items of the same size differ in weight by whole units: the heaps order
// them by weight, so the result does not hang on how a PHP version breaks
// ties.
$out = array();
$out[] = "one box: " . pg_pack(
	array(array(40, 60, 30, 0.5), array(20, 30, 20, 0.25)),
	array(array(10, 15, 10, 1.25), array(10, 15, 10, 2.25), array(18, 28, 8, 0.5)));
$out[] = "two boxes: " . pg_pack(
	array(array(100, 100, 50, 0.5)),
	array(array(50, 50, 50, 1.25), array(50, 50, 50, 2.25), array(50, 50, 50, 3.25), array(50, 50, 50, 4.25), array(50, 50, 50, 5.25)));
$out[] = "too large: " . pg_pack(
	array(array(100, 100, 20, 0.5)),
	array(array(10, 10, 90, 2), array(10, 10, 10, 1)));
$out[] = preg_replace("/\s+/", " ", print_r(new \DVDoug\BoxPacker\TestBox("b", 1, 2, 3, 4, 5, 6, 7, 8), true));
$out[] = preg_replace("/\s+/", " ", print_r(new \DVDoug\BoxPacker\TestItem("i", 1, 2, 3, 4, true), true));
echo "OUT " . implode("\n", $out) . "\n";
PHP;
	file_put_contents($script, $code);
	$output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg(PG_FUNCTIONS_DIR . '/includes/boxpacker') . ' 2>&1');
	unlink($script);

	// The packing results, and the print_r() dumps with public fields in the
	// order the constructors set them, are those of the unpatched library.
	pg_assert_same(implode("\n", array(
		'OUT one box: 1 20x30x20 4.25 [0,1,2]',
		'two boxes: 0 100x100x50 10.25 [1,2,3] / 0 100x100x50 7 [0,4]',
		'too large: Item 0 is too large to fit into any box (dump: 1 items, 1 containers)',
		'DVDoug\BoxPacker\TestBox Object ( [reference] => b [outerWidth] => 1 [outerLength] => 2 [outerDepth] => 3 [emptyWeight] => 4 [innerWidth] => 5 [innerLength] => 6 [innerDepth] => 7 [maxWeight] => 8 [innerVolume] => 210 ) ',
		'DVDoug\BoxPacker\TestItem Object ( [description] => i [width] => 1 [length] => 2 [depth] => 3 [weight] => 4 [keepFlat] => 1 [volume] => 6 )',
	)), trim($output));
}
