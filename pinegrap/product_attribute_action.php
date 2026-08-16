<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

/**
 * JSON endpoint for creating product attributes and options without leaving the
 * screen you are on.
 *
 * The v2 product screen asks the operator to tick the options a product comes
 * in. When the option they need does not exist yet, sending them to
 * add_product_attribute.php throws away a half-filled product form. This
 * endpoint exists so the same work can happen in a modal.
 *
 * Validation deliberately mirrors add_product_attribute.php exactly: same
 * uniqueness rule on the name, same "at least one option" requirement. Two
 * screens creating the same row with different rules is how a table ends up
 * with records one screen refuses to display.
 *
 * Everything here answers in JSON, including failures — the callers are fetch()
 * calls, and an HTML error page arriving where JSON is expected surfaces to the
 * operator as "nothing happened".
 */

include('init.php');
include_once('product_builder.php');


/**
 * Send a JSON response and stop.
 *
 * @param array $data
 * @return void
 */
function pg_paa_respond($data)
{
    // The buffer may already hold a warning or notice from the bootstrap; a
    // stray byte before "{" makes the response unparseable at the other end.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    print encode_json($data);
    exit();
}


/**
 * Send an error and stop.
 *
 * @param string $message already translated, shown to the operator as-is
 * @return void
 */
function pg_paa_error($message)
{
    pg_paa_respond(array('error' => $message));
}


$user = validate_user();

// Same rule as validate_ecommerce_access(), but answering in JSON. Calling the
// shared helper would emit an HTML error page into a JSON response.
if (!($user['role'] < 3 || $user['manage_ecommerce'] == TRUE)) {
    log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
    pg_paa_error(lang('Access denied'));
}

// State changes only. A GET that creates rows is reachable from an <img> tag.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pg_paa_error(lang('Access denied'));
}

// Same check validate_token_field() makes, minus the HTML output.
$posted_token = isset($_POST['token']) ? $_POST['token'] : '';

if (($_SESSION['software']['token'] == '') || ($posted_token !== $_SESSION['software']['token'])) {
    pg_paa_error(lang('Sorry, we could not accept your request because it appears that your session expired or you logged out.'));
}

$action = isset($_POST['action']) ? $_POST['action'] : '';


/**
 * Next sort order for an attribute's options.
 *
 * @param int $attribute_id
 * @return int
 */
function pg_paa_next_sort_order($attribute_id)
{
    $max = db_value(
        "SELECT MAX(sort_order)
        FROM product_attribute_options
        WHERE product_attribute_id = '" . (int) $attribute_id . "'");

    return ((int) $max) + 1;
}


/**
 * Insert one option row.
 *
 * @param int    $attribute_id
 * @param string $label
 * @param bool   $no_value
 * @param int    $sort_order
 * @return array id + label, in the shape the browser expects
 */
function pg_paa_insert_option($attribute_id, $label, $no_value, $sort_order)
{
    db(
        "INSERT INTO product_attribute_options (
            product_attribute_id,
            label,
            no_value,
            sort_order)
        VALUES (
            '" . (int) $attribute_id . "',
            '" . e($label) . "',
            '" . ($no_value ? '1' : '') . "',
            '" . (int) $sort_order . "')");

    return array(
        'id'    => (int) mysqli_insert_id(db::$con),
        'label' => $label,
    );
}


switch ($action) {

    /* ------------------------------------------------ create an attribute */

    case 'create_attribute':

        $name  = isset($_POST['name'])  ? trim($_POST['name'])  : '';
        $label = isset($_POST['label']) ? trim($_POST['label']) : '';

        if ($name === '') {
            pg_paa_error(lang(array('string' => '{var:1} is required', 'vars' => array(lang('Name')))));
        }

        if (db_value("SELECT COUNT(*) FROM product_attributes WHERE name = '" . e($name) . "'") != 0) {
            pg_paa_error(lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
        }

        // Options arrive as parallel arrays from the modal's repeatable rows.
        // Blank rows are dropped rather than rejected: the editor starts with
        // two empty rows and filling only one of them is normal.
        $posted_labels   = (isset($_POST['option_label']) && is_array($_POST['option_label'])) ? $_POST['option_label'] : array();
        $posted_no_value = (isset($_POST['option_no_value']) && is_array($_POST['option_no_value'])) ? $_POST['option_no_value'] : array();

        $options = array();

        foreach ($posted_labels as $index => $option_label) {

            $option_label = trim($option_label);

            if ($option_label === '') {
                continue;
            }

            $options[] = array(
                'label'    => $option_label,
                'no_value' => !empty($posted_no_value[$index]),
            );
        }

        if (!$options) {
            pg_paa_error(lang('Please add an option.'));
        }

        db(
            "INSERT INTO product_attributes (
                name,
                label,
                created_user_id,
                created_timestamp,
                last_modified_user_id,
                last_modified_timestamp)
            VALUES (
                '" . e($name) . "',
                '" . e($label) . "',
                '" . (defined('USER_ID') ? USER_ID : 0) . "',
                UNIX_TIMESTAMP(),
                '" . (defined('USER_ID') ? USER_ID : 0) . "',
                UNIX_TIMESTAMP())");

        $attribute_id = (int) mysqli_insert_id(db::$con);

        $output_options = array();
        $sort_order     = 0;

        foreach ($options as $option) {
            $sort_order++;
            $output_options[] = pg_paa_insert_option($attribute_id, $option['label'], $option['no_value'], $sort_order);
        }

        log_activity(
            lang(array('string' => 'product attribute ({var:1}) was created', 'vars' => array($name))),
            $_SESSION['sessionusername']);

        // The card comes back rendered rather than as data the browser has to
        // rebuild. A JavaScript copy of pg_pb_render_attribute_card() would be
        // a second definition of the same markup, and the two drift the first
        // time one of them is touched.
        //
        // Every option is ticked: the operator typed exactly these options
        // while describing this product. Untick is one click, and the summary
        // line states the consequence out loud.
        $selected_ids = array();

        foreach ($output_options as $output_option) {
            $selected_ids[] = (int) $output_option['id'];
        }

        pg_paa_respond(array(
            'id'      => $attribute_id,
            'name'    => $name,
            'label'   => $label,
            'options' => $output_options,
            'html'    => pg_pb_render_attribute_card(
                array('id' => $attribute_id, 'name' => $name, 'options' => $output_options),
                $selected_ids,
                0),
        ));

        break;

    /* --------------------------------------------------- create an option */

    case 'create_option':

        $attribute_id = isset($_POST['attribute_id']) ? (int) $_POST['attribute_id'] : 0;
        $label        = isset($_POST['label']) ? trim($_POST['label']) : '';

        if ($label === '') {
            pg_paa_error(lang(array('string' => '{var:1} is required', 'vars' => array(lang('Label')))));
        }

        $attribute_name = db_value("SELECT name FROM product_attributes WHERE id = '" . $attribute_id . "'");

        if ($attribute_name === NULL) {
            pg_paa_error(lang('Page not found.'));
        }

        // Two options with the same label on one attribute cannot be told apart
        // in the variant picker, and produce two combinations that look
        // identical in the matrix.
        $duplicate = db_value(
            "SELECT COUNT(*)
            FROM product_attribute_options
            WHERE product_attribute_id = '" . $attribute_id . "'
                AND label = '" . e($label) . "'");

        if ($duplicate != 0) {
            pg_paa_error(lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
        }

        $option = pg_paa_insert_option(
            $attribute_id,
            $label,
            !empty($_POST['no_value']),
            pg_paa_next_sort_order($attribute_id));

        db(
            "UPDATE product_attributes
            SET
                last_modified_user_id = '" . (defined('USER_ID') ? USER_ID : 0) . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . $attribute_id . "'");

        log_activity(
            lang(array(
                'string' => 'product attribute ({var:1}) was updated',
                'vars'   => array($attribute_name))),
            $_SESSION['sessionusername']);

        // Rendered by the same function that drew the chips already on screen,
        // and ticked — the operator added this option in order to use it.
        $option['html'] = pg_pb_render_option_chip($option, TRUE);

        pg_paa_respond($option);

        break;

    default:
        pg_paa_error(lang('Page not found.'));
}
