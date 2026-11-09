<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the remaining native date and time inputs of the panel are
 * drawn on the one date-and-time control: the Cloudflare report ranges, an
 * API application's expiry, the Paraşüt inbox range, the Workspace
 * holidays, the maintenance end time, a folder's access expiry, the
 * accessibility statement date, a product's pre-order date, a store credit's
 * last day and a pickup's time window. Each native input keeps its ISO store
 * and each screen prints the site's date order next to the field script. No
 * native input of the product tree outside the site renderers is left on the
 * browser's own picker.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function other_date_fields_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_other_date_fields_native_inputs_are_hooked()
{
	foreach (array('cloudflare.php' => 4, 'api_settings.php' => 1, 'view_parasut_inbox.php' => 2, 'includes/workspace/workdays.php' => 2) as $file => $count) {
		preg_match_all('/<input [^>]*type="date"[^>]*>/', other_date_fields_test_source($file), $inputs);

		pg_assert_same($count, count($inputs[0]), $file . ' native date inputs');

		foreach ($inputs[0] as $input) {
			pg_assert_true(strpos($input, ' data-pg-date') !== false, $file . ': ' . substr($input, 0, 90) . ' is hooked');
		}
	}

	pg_assert_contains('<input type="datetime-local" data-pg-datetime name="maintenance_until"', other_date_fields_test_source('includes/settings/maintenance.php'), 'the maintenance end keeps its Y-m-d\TH:i store on the control');
}

function test_other_date_fields_screens_print_the_date_order_with_the_script()
{
	foreach (array('cloudflare.php', 'api_settings.php', 'view_parasut_inbox.php', 'includes/workspace/workdays.php', 'includes/settings/maintenance.php', 'view_folders.php', 'includes/settings/privacy.php', 'product_builder.php', 'includes/fn/store_credit_staff.php', 'includes/fn/shipment_labels.php') as $file) {
		pg_assert_true(strpos(other_date_fields_test_source($file), 'get_date_picker_format() . pg_fields_script()') !== false, $file . ' prints the date order with the field script');
	}
}

// The last fields that opened the browser's picker. Each keeps the ISO text
// its handler reads: pg_a11y_statement_clean() and pg_preorder_date_parse()
// take Y-m-d, pg_credit_last_day_expires() Y-m-d or '' for never, and
// pg_shipment_label_pickup_window() H:i on the 24-hour clock.
function test_other_date_fields_last_browser_pickers_are_hooked()
{
	$fields = array(
		'includes/settings/privacy.php'      => array('<input type="date" data-pg-date class="form-control" id="a11y_statement_date" name="a11y_statement_date"'),
		'product_builder.php'                => array('<input type="date" data-pg-date name="preorder_date" id="preorder_date" class="form-control" min="'),
		'includes/fn/store_credit_staff.php' => array('<input type="date" data-pg-date name="credit_last_day" id="credit_last_day" class="form-control"/>'),
		'includes/fn/shipment_labels.php'    => array(
			'<input type="time" data-pg-time class="form-control" id="\' . h($id) . \'_from" name="pickup_from" value="09:00"',
			'<input type="time" data-pg-time class="form-control" id="\' . h($id) . \'_to" name="pickup_to" value="17:00"',
		),
	);

	foreach ($fields as $file => $inputs) {
		foreach ($inputs as $input) {
			pg_assert_contains($input, other_date_fields_test_source($file), $file . ' draws ' . substr($input, 0, 60) . ' on the control');
		}
	}

	// pgBindFieldHelp() skips date hooks, so the statement date names its help
	// itself and the box takes it over (drawParts() in pg_fields.js).
	$privacy = other_date_fields_test_source('includes/settings/privacy.php');
	pg_assert_contains('aria-describedby="a11y_statement_date_help"/>', $privacy, 'the statement date is tied to its help');
	pg_assert_contains('<div class="form-text" id="a11y_statement_date_help">', $privacy, 'the help carries the id');

	// The pickup dialog prints the date order and the field script itself:
	// the orders list prints them only with its advanced filters open, so its
	// date (hooked before) and times opened the browser's picker there.
	$dialog = other_date_fields_test_source('includes/fn/shipment_labels.php');
	$start = strpos($dialog, 'function pg_shipment_label_pickup_dialog(');
	$body = substr($dialog, (int) $start, (int) strpos($dialog, "\n}\n", (int) $start) - (int) $start);
	pg_assert_true($start !== false, 'shipment_labels.php defines the pickup dialog');
	pg_assert_contains('name="pickup_to" value="17:00"\' . $owner . \'>\' . get_date_picker_format() . pg_fields_script() . \'', $body, 'the pickup dialog prints the date order with the field script');
}

// A file's code without its comments: PHP comments through the tokenizer,
// then the whole-line // and * comments of a .js file or of the scripts
// inside the markup.
function other_date_fields_test_code($source, $php)
{
	if ($php) {
		$code = '';

		foreach (token_get_all($source) as $token) {
			if (is_array($token) && (($token[0] === T_COMMENT) || ($token[0] === T_DOC_COMMENT))) {
				continue;
			}

			$code .= is_array($token) ? $token[1] : $token;
		}

		$source = $code;
	}

	return (string) preg_replace('/^[ \t]*(\/\/|\/\*|\*).*$/m', '', $source);
}

// The panel hooks nothing by type (pg_fields.js hooks native inputs only on
// site pages), so a native date, date-and-time or time input without its
// hook opens the browser's own picker in Chrome: "gg.aa.yyyy" with Temizle
// and Bugün. Every one in the product tree carries the hook of its type, and
// no script sets a type the control cannot draw. Left out: files that draw
// site markup, whose native inputs the site page hooks by type
// (docs/memory/ui/site-native-date-inputs.md): the system widgets, the
// design templates and site templates, and the designer's canvas previews
// and palette parts (its own fields: designer_datetime_fields_test.php).
function test_other_date_fields_no_native_input_left_on_the_browser_picker()
{
	$root = PG_FUNCTIONS_DIR . '/';
	$site = array('includes/fn/widgets_', 'includes/design_templates/', 'includes/templates/', 'assets/js/style_designer.src.js', 'frontend.src.js');
	$hooks = array('date' => 'data-pg-date', 'datetime-local' => 'data-pg-datetime', 'time' => 'data-pg-time');
	$native = '(date|datetime-local|time|month|week)';
	$found = array();
	$checked = 0;
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

	foreach ($iterator as $file) {
		$extension = $file->getExtension();

		if ((($extension !== 'php') && ($extension !== 'js')) || (substr($file->getFilename(), -7) === '.min.js')) {
			continue;
		}

		$path = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));

		if ((strpos($path, 'assets/lib/') === 0) || (strpos($path, 'data/') === 0)) {
			continue;
		}

		$source = (string) file_get_contents($file->getPathname());

		if (!preg_match('/type\s*=\s*\\\\?["\']' . $native . '\\\\?["\']|setAttribute\(\s*["\']type["\']\s*,\s*["\']' . $native . '["\']/i', $source)) {
			continue;
		}

		foreach ($site as $prefix) {
			if (strpos($path, $prefix) === 0) {
				continue 2;
			}
		}

		$checked++;
		$code = other_date_fields_test_code($source, $extension === 'php');
		preg_match_all('/<input\b[^>]*>/i', $code, $tags);

		foreach ($tags[0] as $tag) {
			if (!preg_match('/\btype=\\\\?["\']' . $native . '\\\\?["\']/i', $tag, $type)) {
				continue;
			}

			$kind = strtolower($type[1]);

			if (!isset($hooks[$kind]) || !preg_match('/\b' . preg_quote($hooks[$kind], '/') . '\b(?!-)/', $tag)) {
				$found[] = $path . ': ' . substr(preg_replace('/\s+/', ' ', $tag), 0, 100);
			}
		}

		// A script that builds the input itself sets the type in a statement
		// of its own; it goes through a helper that adds the hook with it
		// (pgDateField() in workspace.js), never as a literal.
		if (preg_match_all('/(\.type\s*=\s*|setAttribute\(\s*["\']type["\']\s*,\s*)["\']' . $native . '["\']/i', $code, $literal)) {
			foreach ($literal[0] as $statement) {
				$found[] = $path . ': ' . $statement;
			}
		}
	}

	pg_assert_true($checked >= 10, 'the product tree was read (' . $checked . ' files with a native date or time input)');
	pg_assert_same(array(), $found, 'no native date or time input of the panel opens the browser picker');
}

// The drawer fills the expiry of the application it opens: through the
// wrapper, so the box shows it too.
function test_other_date_fields_api_expiry_set_through_the_wrapper()
{
	$source = other_date_fields_test_source('api_settings.php');

	pg_assert_contains('window.pgDatePicker.set(document.getElementById("d_expires"), app.expires || "")', $source, 'the expiry is written through pgDatePicker');
	pg_assert_false(strpos($source, 'document.getElementById("d_expires").value = app.expires') !== false, 'not into the hidden store alone');
}

// The folder access rows are built by script: the expiry is hooked, its
// wrapper (not the hidden store) is shown and hidden with the rights, and the
// new rows are drawn.
function test_other_date_fields_folder_access_expiry()
{
	$source = other_date_fields_test_source('view_folders.php');

	pg_assert_contains('<input type="date" data-pg-date id="\' + dateId + \'" class="form-control form-control-sm access-expiration"', $source, 'the expiry is hooked');
	pg_assert_contains("select.closest('.access-user').querySelector('.access-expiration-wrap').hidden", $source, 'the rights select shows and hides the wrapper');
	pg_assert_contains('window.pgFields.init(body)', $source, 'the rows are drawn once in the document');
}
