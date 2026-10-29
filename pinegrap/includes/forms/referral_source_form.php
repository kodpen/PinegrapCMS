<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The referral source form, shared by the create and the edit screens
 * (add_referral_source.php, edit_referral_source.php): the information card
 * (name, sort order, code) and the posted values both screens write. The
 * screens keep what differs: the edit screen loads the source, posts its id
 * and offers delete; each writes its own row.
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
 * The posted name, code and sort order as text, in INSERT/UPDATE order.
 * A missing or non-scalar value is empty.
 *
 * @param array $post The posted form ($_POST)
 * @return array  'name', 'code', 'sort_order'
 */
function pg_referral_source_form_values($post)
{
    $values = array();

    foreach (array('name', 'code', 'sort_order') as $key) {
        $values[$key] = (isset($post[$key]) && is_scalar($post[$key])) ? (string) $post[$key] : '';
    }

    return $values;
}

/**
 * The information card, from the opening row to its closing tag; the
 * screens print the form tag, the hidden fields and the buttons around it.
 *
 * @param array|null $values 'name', 'code', 'sort_order' of a stored source
 *                           (plain text); null on create, which prints no values
 * @return string  HTML
 */
function pg_referral_source_form_card($values)
{
    if ($values === null) {
        $name_value = '';
        $code_value = '';
        $sort_order = '';
    } else {
        $name_value = ' value="' . h($values['name']) . '"';
        $code_value = ' value="' . h($values['code']) . '"';
        $sort_order = h($values['sort_order']);
    }

    return '
                        <div class="row">
                            <div class="col-12">
                                <div class="card my-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('New Referral Source Information') . '
                                    </h2>
                                    <div class="card-body">
                                        <div class="row">
                                            <h3 class="h5">' . lang('Order Preview Page Display Options') . '</h3>
                                            
                                            <div class="col-12 md:col-6 my-3">
                                                <label for="name" class="form-label">' . lang('Referral Source Name') . '</label>
                                                <input type="text" id="name" name="name" class="form-control" maxlength="50"' . $name_value . ' required />
                                                <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                            </div>
                                            <div class="col-12 sm:col-2 my-3">
                                                <label for="sort_order" class="form-label">' . lang('Sort Order') . '</label>
                                                <div class="input-group number-controls">
                                                    <button class="btn material-icons minus border border-end-0" type="button" aria-label="' . h(lang('Decrease')) . '">remove</button>
                                                    <input class="form-control text-center border-start-0 border-end-0" value="' . $sort_order . '" type="text" name="sort_order" id="sort_order" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"/>
                                                    <button class="btn material-icons plus border border-start-0" type="button" aria-label="' . h(lang('Increase')) . '">add</button>
                                                </div>
                                            </div>


                                        </div>
                                        <div class="row mt-7">
                                            <h3 class="h5">' . lang('New Referral Source Code for Order Reporting') . '</h3>
                                            <div class="col-12 md:col-4 my-3">
                                                <label for="code" class="form-label">' . lang('Referral Source Code') . '</label>
                                                <input type="text" id="code" name="code" class="form-control" maxlength="50"' . $code_value . ' required />
                                                <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
';
}
