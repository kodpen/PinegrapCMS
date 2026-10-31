<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * An ERP screen builds its page at the file's top level, so its helper
 * closures and its values share one scope. A name given to a closure and,
 * elsewhere in the same file, to a value lets one overwrite the other:
 * edit_erp_expense.php kept the expense's receipt row in $kept and later
 * made $kept a closure, so the receipt card indexed a Closure and every
 * expense screen answered HTTP 500. These checks read the ERP files' source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Returns the variables a file assigns at its top level, as two maps of name
// to line numbers: 'closure' where a closure is assigned, 'value' where
// anything else is. Function and closure bodies are their own scope and are
// skipped.
function erp_screen_variables_assignments($source)
{
	$tokens = token_get_all($source);
	$count = count($tokens);
	$found = array('closure' => array(), 'value' => array());

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];

		if (is_array($token) && ($token[0] === T_FUNCTION)) {
			// Up to the body's opening brace (an abstract method has none),
			// then past its balanced closing brace; "{$" in a string opens a
			// brace too.
			while (($i < $count) && ($tokens[$i] !== '{') && ($tokens[$i] !== ';')) {
				$i++;
			}

			if (($i < $count) && ($tokens[$i] === '{')) {
				$depth = 0;

				for (; $i < $count; $i++) {
					$inner = $tokens[$i];

					if (($inner === '{') || (is_array($inner) && in_array($inner[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
						$depth++;
					} elseif (($inner === '}') && (--$depth === 0)) {
						break;
					}
				}
			}

			continue;
		}

		if (!is_array($token) || ($token[0] !== T_VARIABLE)) {
			continue;
		}

		$next = $i + 1;
		while (($next < $count) && is_array($tokens[$next]) && ($tokens[$next][0] === T_WHITESPACE)) {
			$next++;
		}

		if (($next >= $count) || ($tokens[$next] !== '=')) {
			continue;
		}

		// What is assigned: a closure starts with function, static function
		// or fn.
		$value = $next + 1;
		while (($value < $count) && is_array($tokens[$value]) && in_array($tokens[$value][0], array(T_WHITESPACE, T_STATIC), true)) {
			$value++;
		}

		$is_closure = ($value < $count) && is_array($tokens[$value])
			&& (($tokens[$value][0] === T_FUNCTION) || (defined('T_FN') && ($tokens[$value][0] === T_FN)));
		$found[$is_closure ? 'closure' : 'value'][$token[1]][] = $token[2];
	}

	return $found;
}

function test_erp_screen_variables_closure_names_are_not_reused()
{
	$files = array_merge(
		(array) glob(PG_FUNCTIONS_DIR . '/*erp*.php'),
		(array) glob(PG_FUNCTIONS_DIR . '/includes/erp/*.php'),
		(array) glob(PG_FUNCTIONS_DIR . '/includes/erp/*/*.php'));
	pg_assert_true(count($files) > 100, 'the ERP files are found');

	$clashes = array();

	foreach ($files as $file) {
		$found = erp_screen_variables_assignments((string) file_get_contents($file));

		foreach ($found['closure'] as $name => $lines) {
			if (isset($found['value'][$name])) {
				$clashes[] = basename($file) . ': ' . $name . ' is a closure on line ' . implode(', ', $lines)
					. ' and a value on line ' . implode(', ', $found['value'][$name]);
			}
		}
	}

	pg_assert_same(array(), $clashes, 'no ERP file gives a closure\'s name to a value');
}

function test_erp_screen_variables_expense_receipt_stays_the_row()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_expense.php');
	$found = erp_screen_variables_assignments($source);

	pg_assert_contains("\$kept = erp_archive_file('expense', \$id);", $source, 'the receipt row is read into $kept');
	pg_assert_same(1, count(isset($found['value']['$kept']) ? $found['value']['$kept'] : array()), '$kept is assigned once');
	pg_assert_false(isset($found['closure']['$kept']), '$kept is never a closure');
}
