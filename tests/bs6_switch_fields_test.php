<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for tools/bs6_switch_fields.php: the v6 class written into liveform
 * select, checkbox and radio calls of panel files that name no Bootstrap
 * class, a second run leaving the result as it is, and the calls left for a
 * hand.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__DIR__) . '/tools/bs6_switch.php');

function test_bs6_switch_fields_writes_the_v6_class()
{
	$source = "<?php\n\$a = \$liveform->output_field(array('type' => 'select', 'name' => 's', 'options' => array('a' => 'b')));\n"
		. "\$b = \$liveform->output_field(array('type'=>'checkbox', 'name'=>'c', 'class'=>''));\n"
		. "\$c = \$liveform->output_field(['type' => 'radio', 'name' => 'r', 'class' => 'collapse-switcher']);\n"
		. "\$d = '<div class=\"switch\">' . \$liveform->output_field(array('type' => 'checkbox', 'name' => 'w', 'class' => 'collapse-switcher'));\n"
		. "\$e = \$liveform->output_field(array('type' => 'text', 'name' => 't'));\n"
		. "\$f = \$liveform->output_field(array('type' => 'select', 'name' => 'f', 'class' => 'form-control ' . \$extra));\n";
	$result = bs6_switch_fields($source);

	pg_assert_same('applied', $result['status']);
	pg_assert_true(strpos($result['source'], "output_field(array('class' => 'form-control', 'type' => 'select', 'name' => 's'") !== false);
	pg_assert_true(strpos($result['source'], "'name'=>'c', 'class'=>'check'") !== false);
	pg_assert_true(strpos($result['source'], "'class' => 'collapse-switcher radio']") !== false);
	pg_assert_true(strpos($result['source'], "'name' => 'w', 'class' => 'collapse-switcher no-bs'") !== false);
	pg_assert_true(strpos($result['source'], "output_field(array('type' => 'text', 'name' => 't'))") !== false);
	pg_assert_true(strpos($result['source'], "'class' => 'form-control ' . \$extra") !== false);
	pg_assert_same(array('source' => $result['source'], 'status' => 'done'), bs6_switch_fields($result['source']));
}

function test_bs6_switch_fields_leaves_built_classes_for_a_hand()
{
	$source = "<?php\n\$a = \$liveform->output_field(array('type' => 'checkbox', 'class' => 'x ' . \$y));\n"
		. "\$b = \$liveform->output_field(array('type' => \$type, 'name' => 'n'));\n";
	$result = bs6_switch_fields($source);

	pg_assert_same('done', $result['status']);
	$needs = array();
	foreach (bs6_field_calls($source) as $call) {
		$needs[] = bs6_field_needs_class($call);
	}
	pg_assert_same(array(true, true), $needs);
}

function test_bs6_switch_fields_reads_multiline_calls()
{
	$source = "<?php\n\$o = '<div class=\"form-field\"><div class=\"switch\">' .\n"
		. "    \$liveform->output_field(array(\n        'type' => 'checkbox',\n        'name' => 'published',\n        'class' => 'collapse-switcher',\n        'data-bs-target'=>'#r')) . '</div>';\n";
	$result = bs6_switch_fields($source);

	pg_assert_true(strpos($result['source'], "'class' => 'collapse-switcher no-bs'") !== false);
}
