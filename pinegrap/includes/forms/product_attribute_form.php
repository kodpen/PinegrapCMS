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
 * list, or two empty options when there is none.
 *
 * @param string $options  JSON list of options
 * @return string  JavaScript array literal
 */
function pg_product_attribute_form_output_options($options)
{
    if (($options != '') && ($options != '[]')) {
        return $options;
    }

    return '[{label: ""},{label: ""}]';
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
 * Checks the posted name is free and at least one option was added; errors
 * are marked on the form. The required check stays in the screens, which
 * word it differently.
 *
 * @param liveform $liveform
 * @param string|null $id  Edited attribute's id, null on create
 * @return array  The posted options
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

    $options = decode_json($liveform->get_field_value('options'));

    if (count($options) == 0) {
        $liveform->mark_error('', lang('Please add an option.'));
    }

    return $options;
}
