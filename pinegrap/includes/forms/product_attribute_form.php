<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The product attribute form, shared by the create and the edit screens
 * (add_product_attribute.php, edit_product_attribute.php): the main
 * information and options cards, the options list the cards start with,
 * and the checks of a posted name and options. The screens keep what
 * differs: the edit screen loads the record and its options, deletes, and
 * updates, adds and removes options on save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The options the options card starts with: the posted or stored JSON
 * list, or two empty options when there is none. The list is decoded and
 * encoded again with only id, label and no value, and with <, >, &, ' and
 * " written as \u escapes, so a posted label that holds </script> cannot
 * close the script it is printed in.
 *
 * @param string $options  JSON list of options
 * @return string  JavaScript array literal
 */
function pg_product_attribute_form_output_options($options)
{
    $decoded = json_decode((string) $options, true);
    $output = array();

    if (is_array($decoded)) {
        foreach ($decoded as $option) {
            if (!is_array($option)) {
                continue;
            }

            $output[] = array(
                'id' => isset($option['id']) && is_scalar($option['id']) ? (string) $option['id'] : '',
                'label' => isset($option['label']) && is_scalar($option['label']) ? (string) $option['label'] : '',
                'no_value' => !empty($option['no_value']) ? '1' : '',
            );
        }
    }

    if (!$output) {
        return '[{label: ""},{label: ""}]';
    }

    return json_encode($output, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

/**
 * The posted options ready to save: labels trimmed, rows without a label
 * dropped (the form starts with two empty rows and filling only one is
 * normal), the id an integer (0 for a new option), no value '1' or ''.
 *
 * @param mixed $options  Decoded options list
 * @return array
 */
function pg_product_attribute_form_clean_options($options)
{
    $clean = array();

    if (!is_array($options)) {
        return $clean;
    }

    foreach ($options as $option) {
        if (!is_array($option)) {
            continue;
        }

        $label = isset($option['label']) && is_scalar($option['label']) ? trim((string) $option['label']) : '';

        if ($label === '') {
            continue;
        }

        $clean[] = array(
            'id' => isset($option['id']) && is_scalar($option['id']) ? (int) $option['id'] : 0,
            'label' => $label,
            'no_value' => !empty($option['no_value']) ? '1' : '',
        );
    }

    return $clean;
}

/**
 * The first label given to a second option, compared without case: two
 * options with one label cannot be told apart in the variant picker.
 *
 * @param array $options  Cleaned options
 * @return string  The repeated label, '' when every label differs
 */
function pg_product_attribute_form_duplicate_label($options)
{
    $seen = array();

    foreach ($options as $option) {
        $label = (string) $option['label'];
        // mb_strtolower() turns the Turkish dotted capital into i plus a
        // combining dot, so it is mapped to a plain i first.
        $key = str_replace('İ', 'i', $label);
        $key = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);

        if (isset($seen[$key])) {
            return $label;
        }

        $seen[$key] = true;
    }

    return '';
}

/**
 * The Main Informations card (name, label) and the Options card with the
 * option list script.
 *
 * @param liveform $liveform
 * @return string
 */
function pg_product_attribute_form_cards($liveform)
{
    $output_options = pg_product_attribute_form_output_options($liveform->get_field_value('options'));

    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 sm:col-4 my-3">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            ' . $liveform->output_field(array(
                                                'type' => 'text',
                                                'name' => 'name',
                                                'id' => 'name',
                                                'placeholder'=>lang('Attribute Name'),
                                                'class'=>'form-control',
                                                'maxlength' => '100',
                                                'required' => 'required')) . '
                                        </div>
                                        <div class="col-12 sm:col-8 my-3">
                                            <label for="label" class="form-label">' . lang('Label') . '</label>
                                            ' . $liveform->output_field(array(
                                                'type' => 'text',
                                                'name' => 'label',
                                                'id' => 'label',
                                                'placeholder'=>lang('Attribute Label'),
                                                'class'=>'form-control',
                                                'maxlength' => '255')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-1 mb-3">
                                            <label class="form-label" >' . lang('Attribute Options') . '</label>
                                            <div class="options">
                                                <div class="option_list row" ></div>
                                                <button type="button" class="add_option btn btn-solid theme-primary mt-3"><span class="bi bi-plus-circle me-3"></span>' . lang('Add Option') . '</button>
                                            </div>
                                            <script>
                                                init_product_attribute_options({
                                                    options: ' . $output_options . ',
                                                    labels:{
                                                        "\'No Thanks\' Option":"' . lang('\'No Thanks\' Option') . '",
                                                        "Option":"' . lang('Option') . '",
                                                        "Move Up":"' . lang('Move Up') . '",
                                                        "Move Down":"' . lang('Move Down') . '",
                                                        "Remove":"' . lang('Remove') . '"
                                                    }
                                                });
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
}

/**
 * Checks the posted name is free, at least one option has a label and no
 * label is given twice; errors are marked on the form. The required check stays in the screens, which
 * word it differently.
 *
 * @param liveform $liveform
 * @param string|null $id  Edited attribute's id, null on create
 * @return array  The posted options, cleaned (pg_product_attribute_form_clean_options())
 */
function pg_product_attribute_form_check($liveform, $id)
{
    if ($liveform->check_field_error('name') == false) {
        $query = "SELECT COUNT(*) AS count FROM product_attributes WHERE (name = ?)";
        $values = array((string) $liveform->get_field_value('name'));

        if ($id !== null) {
            $query .= " AND (id != ?)";
            $values[] = (string) $id;
        }

        $result = db_q($query, $values) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        if ((int) $row['count'] != 0) {
            $liveform->mark_error('name', lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
        }
    }

    $options = pg_product_attribute_form_clean_options(json_decode((string) $liveform->get_field_value('options'), true));

    if (count($options) == 0) {
        $liveform->mark_error('', lang('Please add an option.'));
    } else {
        $duplicate = pg_product_attribute_form_duplicate_label($options);

        if ($duplicate !== '') {
            $liveform->mark_error('', lang(array('string' => 'The option {var:1} is entered more than once.', 'vars' => array(h($duplicate)))));
        }
    }

    return $options;
}

/**
 * How many products and product groups use an attribute; a delete removes
 * the attribute and its options from all of them.
 *
 * @param int $attribute_id
 * @return array list of (products, product groups)
 */
function pg_product_attribute_form_delete_counts($attribute_id)
{
    $first = function ($result) {
        $row = $result ? mysqli_fetch_row($result) : null;

        return $row ? (int) $row[0] : 0;
    };
    $attribute_id = (int) $attribute_id;

    return array(
        $first(db_q("SELECT COUNT(DISTINCT product_id) FROM products_attributes_xref WHERE attribute_id = ?", array($attribute_id))),
        $first(db_q("SELECT COUNT(DISTINCT product_group_id) FROM product_groups_attributes_xref WHERE attribute_id = ?", array($attribute_id))),
    );
}

/**
 * The Delete confirmation of Edit Product Attribute as lang() arguments.
 *
 * @param int $products
 * @param int $groups
 * @return array list of lang() arguments, one per sentence
 */
function pg_product_attribute_form_delete_confirm($products, $groups)
{
    $products = (int) $products;
    $groups = (int) $groups;

    if ($products > 0 && $groups > 0) {
        return array(array(
            'string' => 'WARNING: This product attribute and its options will be permanently deleted and removed from {var:1} product{suffix:1} and {var:2} product group{suffix:2}.',
            'vars' => array($products, $groups),
            'suffix' => array($products == 1 ? '' : 's', $groups == 1 ? '' : 's'),
        ));
    }

    if ($products > 0) {
        return array(array(
            'string' => 'WARNING: This product attribute and its options will be permanently deleted and removed from {var:1} product{suffix:1}.',
            'vars' => array($products),
            'suffix' => array($products == 1 ? '' : 's'),
        ));
    }

    if ($groups > 0) {
        return array(array(
            'string' => 'WARNING: This product attribute and its options will be permanently deleted and removed from {var:1} product group{suffix:1}.',
            'vars' => array($groups),
            'suffix' => array($groups == 1 ? '' : 's'),
        ));
    }

    return array(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('product attribute'))));
}
