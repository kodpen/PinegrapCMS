<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The short link form, shared by the create and the edit screens
 * (add_short_link.php, edit_short_link.php): the destination, name and
 * function option cards, the checks of a posted name, destination and
 * answer, and the column a destination is saved in. The screens keep what
 * differs: the create screen offers the link modes and inserts the row; the
 * edit screen loads the record, shows the QR code and the Visit link,
 * offers delete and updates the row.
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
 * The destination type picker's options.
 *
 * @return array  Label => value
 */
function pg_short_link_form_destination_options()
{
    return array(
        '-' . lang(array('string'=>'Select {var:1}','vars'=>lang('Destination Type'))) . '-' => '',
        lang('Page') => 'page',
        lang('Product Group') => 'product_group',
        lang('Product') => 'product',
        lang('URL') => 'url',
        lang('File') => 'file');
}

/**
 * Which function option rows a destination type opens with.
 *
 * @param string $destination_type
 * @return array  Row => '' (shown) or ' style="display: none"'
 */
function pg_short_link_form_row_styles($destination_type)
{
    $shown = array(
        'page' => array('page_id', 'tracking_code'),
        'product_group' => array('catalog_page_id', 'or', 'catalog_detail_page_id', 'product_group_id', 'tracking_code'),
        'product' => array('catalog_detail_page_id', 'product_id', 'tracking_code'),
        'url' => array('url'),
        'file' => array('file'),
    );

    $styles = array();

    foreach (array('page_id', 'catalog_page_id', 'or', 'catalog_detail_page_id', 'product_group_id', 'product_id', 'url', 'tracking_code', 'file') as $row) {
        $styles[$row] = ' style="display: none"';
    }

    foreach ($shown[(string) $destination_type] ?? array() as $row) {
        $styles[$row] = '';
    }

    return $styles;
}

/**
 * The column a destination type saves its target in and the form field it
 * comes from; a page destination keeps its target in page_id only.
 *
 * @param string $destination_type
 * @return array|null  array(column, field), null when there is none
 */
function pg_short_link_form_destination_column($destination_type)
{
    switch ((string) $destination_type) {
        case 'product_group':
            return array('product_group_id', 'product_group_id');

        case 'product':
            return array('product_id', 'product_id');

        case 'url':
            return array('url', 'url');

        case 'file':
            return array('file_id', 'file');
    }

    return null;
}

/**
 * The "How it answers" row (2026.11.0, 11.0.12), '' before the upgrade that
 * adds its column.
 *
 * @param liveform $liveform
 * @return string
 */
function pg_short_link_form_redirect_code_row($liveform)
{
    if (!pg_short_link_redirects_ready()) {
        return '';
    }

    return '
                                                <div class="col-12 md:col-8 lg:col-6 my-3" id="redirect_code_row">
                                                    <label for="redirect_code" class="form-label">' . lang('How it answers') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'redirect_code', 'id'=>'redirect_code', 'class'=>'form-control', 'options'=>pg_short_link_answer_options())) . '
                                                    <div class="form-text">' . lang('A redirect sends visitors to the destination\'s own address and keeps their query string: 301 for the old address of a page that moved, 302 for a while.') . '</div>
                                                </div>';
}

/**
 * The product group picker's options: the groups, with a labelled first
 * option as the page, product and file pickers have.
 *
 * @param array $keep_ids Groups that stay on the list while in the Recycle Bin
 * @return array  Rows of 'label' and 'value'
 */
function pg_short_link_form_product_group_options($keep_ids)
{
    $options = get_product_group_options(0, 0, 0, 0, array(), TRUE, 'array', TRUE, TRUE, FALSE, $keep_ids);

    return pg_short_link_form_label_blank($options, '-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Product Group')))) . '-');
}

/**
 * Gives an option list's unlabelled blank first option a label. Pure.
 *
 * @param array  $options Rows of 'label' and 'value'
 * @param string $label
 * @return array
 */
function pg_short_link_form_label_blank($options, $label)
{
    if (isset($options[0]) && is_array($options[0]) && ((string) $options[0]['value'] === '') && ((string) $options[0]['label'] === '')) {
        $options[0]['label'] = $label;
    }

    return $options;
}

/**
 * The form column (name and destination cards) and the sticky status column
 * (a summary of the link and the screen's own cards) of the edit screen
 * layout (ui/edit-screen-wide-layout.md); pg_short_link_form_layout() wraps
 * them in their row with the save bar.
 *
 * @param liveform $liveform
 * @param array $form  'extra_rows' (markup after the name row), 'status'
 *                     (cards under the summary in the status column),
 *                     'keep' (the page_id, product_group_id and product_id
 *                     the link points at, which stay on their lists while in
 *                     the Recycle Bin; nothing on create). The File field
 *                     is the picker field (pg_picker_field(), kind file):
 *                     its chosen file marked when it is in the bin, the
 *                     rest searched or browsed
 * @return string
 */
function pg_short_link_form_cards($liveform, $form)
{
    $form = array_merge(array('extra_rows' => '', 'status' => '', 'keep' => array()), $form);
    $keep = array_merge(array('page_id' => 0, 'product_group_id' => array(), 'product_id' => 0), $form['keep']);
    $style = pg_short_link_form_row_styles($liveform->get_field_value('destination_type'));
    $address_base = URL_SCHEME . HOSTNAME . OUTPUT_PATH;
    $name = (string) $liveform->get_field_value('name');
    $destination_label = (string) array_search((string) $liveform->get_field_value('destination_type'), pg_short_link_form_destination_options(), true);

    if ((string) $liveform->get_field_value('destination_type') === '') {
        $destination_label = '';
    }

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-8 lg:col-6 my-3" id="name_row">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            <div class="input-group">
                                                <span class="input-group-text material-icons" aria-hidden="true">public</span>
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>pg_short_link_name_max(), 'placeholder'=>lang('Short Link Name'), 'required'=>'required')) . '
                                            </div>
                                            <div class="form-text">' . lang('This option determines the url address of the short link.') . ' <code>' . h(URL_SCHEME . HOSTNAME . OUTPUT_PATH . '{' . lang('Short Link Name') . '}') . '</code></div>
                                            <div class="form-text">' . pg_short_link_name_rule_text() . '</div>
                                            <div class="form-text">' . lang('An old address can be pasted as it is: only its path is kept. A name with a * is tried only where no page answers, and it always redirects.') . '</div>
                                        </div>
                                        ' . $form['extra_rows'] . '
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Destination') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 lg:col-4 my-3">
                                            <label for="destination_type" class="form-label">' . lang('Destination Type') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'id'=>'destination_type', 'class'=>'form-control collapse-if-selected', 'name'=>'destination_type', 'options'=>pg_short_link_form_destination_options(), 'onchange'=>'change_short_link_destination_type(this.options[this.selectedIndex].value)', 'data-bs-target'=>'#options_row', 'required'=>'required')) . '
                                        </div>
                                    </div>
                                    <div class="collapse" id="options_row">
                                        <div class="row">
                                            <div class="col-12 md:col-6 lg:col-4 my-3" id="page_id_row"' . $style['page_id'] . '>
                                                <label for="page_id" class="form-label">' . lang('Page') . '</label>
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'page_id', 'id'=>'page_id', 'class'=>'form-control', 'options'=>get_page_options('', '', 'edit', FALSE, $keep['page_id']))) . '
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-5 my-3" id="catalog_page_id_row"' . $style['catalog_page_id'] . '>
                                                <label for="catalog_page_id" class="form-label">' . lang('Catalog Page') . '</label>
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'catalog_page_id', 'id'=>'catalog_page_id', 'class'=>'form-control', 'options'=>get_page_options('', 'catalog', 'edit', FALSE, $keep['page_id']), 'onclick'=>'if (this.selectedIndex != 0) {document.getElementById(\'catalog_detail_page_id\').selectedIndex = 0}' )) . '
                                            </div>
                                            <div class="col-12 lg:col-2 my-3 lg:text-center align-self-end" id="or_row"' . $style['or'] . '>
                                                -' . lang('or') . '-
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-5 my-3" id="catalog_detail_page_id_row"' . $style['catalog_detail_page_id'] . '>
                                                <label for="catalog_detail_page_id" class="form-label">' . lang('Catalog Detail Page') . '</label>
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'catalog_detail_page_id', 'id'=>'catalog_detail_page_id', 'class'=>'form-control', 'options'=>get_page_options('', 'catalog detail', 'edit', FALSE, $keep['page_id']), 'onclick'=>'if (this.selectedIndex != 0) {document.getElementById(\'catalog_page_id\').selectedIndex = 0}' )) . '
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-5 my-3" id="product_group_id_row"' . $style['product_group_id'] . '>
                                                <label for="product_group_id" class="form-label">' . lang('Product Group') . '</label>
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'product_group_id', 'id'=>'product_group_id', 'class'=>'form-control', 'options'=>pg_short_link_form_product_group_options($keep['product_group_id']))) . '
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-7 my-3" id="product_id_row"' . $style['product_id'] . '>
                                                <label for="product_id" class="form-label">' . lang('Product') . '</label>
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'product_id', 'id'=>'product_id', 'class'=>'form-control', 'options'=>get_product_options(true, $keep['product_id']))) . '
                                            </div>
                                            <div class="col-12 md:col-8 lg:col-6 my-3" id="url_row"' . $style['url'] . '>
                                                <label for="url" class="form-label">' . lang('Destination URL') . '</label>
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'url', 'id'=>'url', 'class'=>'form-control', 'maxlength'=>pg_short_link_url_max(), 'placeholder' => lang('Enter URL that visitor should be redirected to'))) . '
                                                <div class="form-text">' . lang('With a * in the name, $1 to $9 put here what each * matched, after the host name.') . '</div>
                                            </div>
                                            <div class="col-12 md:col-6 lg:col my-3" id="tracking_code_row"' . $style['tracking_code'] . '>
                                                <label for="tracking_code" class="form-label">' . lang('Tracking Code') . '</label>
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'tracking_code', 'id'=>'tracking_code', 'class'=>'form-control', 'maxlength'=>pg_short_link_form_tracking_code_max())) . '
                                            </div>
                                            <div class="col-12 lg:col-8 my-3" id="file_row"' . $style['file'] . '>
                                                <label for="file" class="form-label">' . lang('File') . '</label>
                                                ' . pg_picker_field('file', 'file', $liveform->get_field_value('file'), array('title' => lang('Choose a file'), 'invalid' => $liveform->check_field_error('file'))) . pg_picker_script() . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">
                                            <div class="py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('Address') . '</dt>
                                                <dd class="mb-0 fw-bold text-break" id="short_link_summary_address" data-base="' . h($address_base) . '">' . h($address_base . $name) . '</dd>
                                            </div>
                                            <div class="d-flex justify-content-between py-2">
                                                <dt class="fw-normal">' . lang('Destination Type') . '</dt>
                                                <dd class="mb-0 fw-bold" id="short_link_summary_destination">' . h($destination_label) . '</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>' . $form['status'] . '
                            </div>
                        </div>
                        <script>
                            // A name with a * always redirects, so "Open as a short link" is
                            // not offered for it; a chosen 0 moves to 301, as the save would.
                            function pg_short_link_pattern_answer() {
                                var $name = $(\'#name\');
                                var $open = $(\'#redirect_code option[value="0"]\');
                                if (!$name.length || !$open.length) {
                                    return;
                                }
                                var pattern = String($name.val()).indexOf(\'*\') !== -1;
                                $open.prop(\'disabled\', pattern);
                                if (pattern && $(\'#redirect_code\').val() === \'0\') {
                                    $(\'#redirect_code\').val(\'301\');
                                }
                            }
                            $(document).ready(function() {
                                change_short_link_destination_type($("select#destination_type option:selected").val());
                                pg_short_link_pattern_answer();
                            });
                            $(document).on(\'input\', \'#name\', function () {
                                var $address = $(\'#short_link_summary_address\');
                                $address.text($address.data(\'base\') + this.value);
                                pg_short_link_pattern_answer();
                            });
                            $(document).on(\'change\', \'#destination_type\', function () {
                                $(\'#short_link_summary_destination\').text(this.value === \'\' ? \'\' : $(this).find(\'option:selected\').text());
                            });
                        </script>';
}

/**
 * The screen row: the cards (pg_short_link_form_cards()) and the sticky save
 * bar with the given buttons.
 *
 * @param string $cards   pg_short_link_form_cards()
 * @param string $buttons The buttons inside the bar's group
 * @return string
 */
function pg_short_link_form_layout($cards, $buttons)
{
    return '
                    <div class="row">' . $cards . '
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">' . $buttons . '
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>';
}

/**
 * The longest tracking code the short_links.tracking_code column holds.
 *
 * @return int
 */
function pg_short_link_form_tracking_code_max()
{
    return 100;
}

/**
 * Checks the posted tracking code against its column: a longer one is
 * refused rather than cut by the database.
 *
 * @param liveform $liveform
 * @return void
 */
function pg_short_link_form_validate_tracking_code($liveform)
{
    $problem = pg_short_link_form_tracking_code_problem($liveform->get_field_value('tracking_code'));

    if ($problem !== '') {
        $liveform->mark_error('tracking_code', h($problem));
    }
}

/**
 * Why a tracking code cannot be saved, '' when it can. Characters, not bytes,
 * as the column counts them. Pure.
 *
 * @param mixed $tracking_code
 * @return string
 */
function pg_short_link_form_tracking_code_problem($tracking_code)
{
    $max = pg_short_link_form_tracking_code_max();

    if (mb_strlen((string) $tracking_code, 'UTF-8') > $max) {
        return lang(array('string' => '{var:1} can be at most {var:2} characters long.', 'vars' => array(lang('Tracking Code'), $max)));
    }

    return '';
}

/**
 * Checks the posted name: required, read as a pasted address, by the name
 * rule and free. The cleaned name goes back into the form.
 *
 * @param liveform $liveform
 * @param mixed $ignore_id  The edited link's id, which may keep its name;
 *                          null on create
 * @return string  The cleaned name
 */
function pg_short_link_form_validate_name($liveform, $ignore_id)
{
    $liveform->validate_required_field('name', lang('Name is required.'));

    // An old address pasted whole keeps only its path; spaces become
    // underscores (pg_short_link_name_from_address()).
    $name = pg_short_link_name_from_address($liveform->get_field_value('name'));

    // Update name in liveform.
    $liveform->assign_field_value('name', $name);

    // If there is not already an error for the name field and the name breaks the rule, then add error.
    if ($liveform->check_field_error('name') == FALSE) {
        $name_problem = pg_short_link_name_problem($name);

        if ($name_problem !== '') {
            $liveform->mark_error('name', h($name_problem));
        }
    }

    $availability = array('name' => $name);

    if ($ignore_id !== null) {
        $availability['ignore_item_id'] = $ignore_id;
        $availability['ignore_item_type'] = 'short_link';
    }

    // If there is not already an error for the name field,
    // and the name is already in use, then add error.
    if (
        ($liveform->check_field_error('name') == false)
        && (check_name_availability($availability) == false)
    ) {
        // A short link in the Recycle Bin keeps its name and is not on the
        // list, so a plain "in use" would point nowhere.
        if (pg_short_link_form_name_binned($name, $ignore_id)) {
            $liveform->mark_error('name', lang('A short link in the Recycle Bin has this name: restore it, or delete it for good, in the File Manager.'));
        } else {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        }
    }

    return $name;
}

/**
 * Whether a short link in the Recycle Bin holds the name.
 *
 * @param string $name
 * @param mixed  $ignore_id The edited link's id, null on create
 * @return bool
 */
function pg_short_link_form_name_binned($name, $ignore_id)
{
    if (!pg_schema_has('short_links', 'recycled')) {
        return false;
    }

    $result = db_q(
        "SELECT COUNT(*) FROM short_links WHERE name = ? AND recycled = '1' AND id <> ?",
        array((string) $name, (string) (int) $ignore_id));

    if (!$result) {
        return false;
    }

    $row = mysqli_fetch_row($result);

    return ((int) $row[0] > 0);
}

/**
 * Checks the posted destination type and its target. A page, product group,
 * product or file in the Recycle Bin is refused (a form drawn before it was
 * deleted can still post it), unless the edited link already points at it:
 * its lists keep it, marked.
 *
 * @param liveform $liveform
 * @param array|null $current  The stored link (page_id, product_group_id,
 *                             product_id, file_id); null on create
 * @return mixed  The page_id to save, 0 when the destination has none
 */
function pg_short_link_form_validate_destination($liveform, $current)
{
    // Whether the posted value is what the edited link already points at.
    $kept = function ($value, $column) use ($current) {
        return ($current !== null) && ((int) $value === (int) $current[$column]);
    };

    $liveform->validate_required_field('destination_type', lang('Destination Type is required.'));

    // The column holds 100 characters; the database would cut a longer code
    // without a word (the panel session's sql_mode is not strict).
    pg_short_link_form_validate_tracking_code($liveform);

    // If there is not already an error for the destination type and the destination type is invalid, then add error.
    if (
        ($liveform->check_field_error('destination_type') == FALSE)
        &&
        (
            ($liveform->get_field_value('destination_type') != 'page')
            && ($liveform->get_field_value('destination_type') != 'product_group')
            && ($liveform->get_field_value('destination_type') != 'product')
            && ($liveform->get_field_value('destination_type') != 'url')
            && ($liveform->get_field_value('destination_type') != 'file')
        )
    ) {
        $liveform->mark_error('destination_type', lang('The destination type is not valid.'));
    }

    // If there is not already an error for the destination type, then validate other fields.
    if ($liveform->check_field_error('destination_type') == FALSE) {
        // Validate other fields differently based on the destination type.
        switch ($liveform->get_field_value('destination_type')) {
            case 'page':
                $liveform->validate_required_field('page_id', lang('Page is required.'));
                break;

            case 'product_group':
                // If neither a catalog page or a catalog detail page was selected, then add error.
                if (
                    ($liveform->get_field_value('catalog_page_id') == '')
                    && ($liveform->get_field_value('catalog_detail_page_id') == '')
                ) {
                    $liveform->mark_error('catalog_page_id', lang('Catalog Page or Catalog Detail Page is required.'));
                    $liveform->mark_error('catalog_detail_page_id', '');
                }

                // If both a catalog page and a catalog detail page were selected, then add error.
                if (
                    ($liveform->get_field_value('catalog_page_id') != '')
                    && ($liveform->get_field_value('catalog_detail_page_id') != '')
                ) {
                    $liveform->mark_error('catalog_page_id', lang('Please select either a Catalog Page or a Catalog Detail Page, not both.'));
                    $liveform->mark_error('catalog_detail_page_id', '');
                }

                $liveform->validate_required_field('product_group_id', lang('Product Group is required.'));

                // If there is not already an error for the product group, then check if product group exists.
                if ($liveform->check_field_error('product_group_id') == FALSE) {
                    $query = "SELECT COUNT(*) FROM product_groups WHERE id = ?";
                    $result = db_q($query, array((string) $liveform->get_field_value('product_group_id'))) or output_error('Query failed.');
                    $row = mysqli_fetch_row($result);

                    // If the selected product group does not exist, then add error.
                    if ($row[0] == 0) {
                        $liveform->mark_error('product_group_id', lang('The product group does not exist.'));
                    }
                }

                if (
                    ($liveform->check_field_error('product_group_id') == FALSE)
                    && !$kept($liveform->get_field_value('product_group_id'), 'product_group_id')
                    && pg_catalog_group_binned($liveform->get_field_value('product_group_id'))
                ) {
                    $liveform->mark_error('product_group_id', lang('The product group is in the Recycle Bin. Restore it in the Catalog Manager or choose another product group.'));
                }

                break;

            case 'product':
                $liveform->validate_required_field('catalog_detail_page_id', lang('Catalog Detail Page is required.'));
                $liveform->validate_required_field('product_id', lang('Product is required.'));

                // If there is not already an error for the product, then check if product exists.
                if ($liveform->check_field_error('product_id') == FALSE) {
                    $query = "SELECT COUNT(*) FROM products WHERE id = ?";
                    $result = db_q($query, array((string) $liveform->get_field_value('product_id'))) or output_error('Query failed.');
                    $row = mysqli_fetch_row($result);

                    // If the selected product does not exist, then add error.
                    if ($row[0] == 0) {
                        $liveform->mark_error('product_id', lang('The product does not exist.'));
                    }
                }

                if (
                    ($liveform->check_field_error('product_id') == FALSE)
                    && !$kept($liveform->get_field_value('product_id'), 'product_id')
                    && pg_catalog_product_binned($liveform->get_field_value('product_id'))
                ) {
                    $liveform->mark_error('product_id', lang('The product is in the Recycle Bin. Restore it in the Catalog Manager or choose another product.'));
                }

                break;

            case 'url':
                $liveform->validate_required_field('url', lang('Destination URL is required.'));

                // If there is not already an error for the url field
                // and the url contains white-space, then output error.
                // We have to do this to prevent outputting invalid code to the rewrite file
                // which would cause all URL's at the site to stop working.
                if (
                    ($liveform->check_field_error('url') == false)
                    && (preg_match('/\s/', $liveform->get_field_value('url')) == 1)
                ) {
                    $liveform->mark_error('url', lang('Sorry, you may not enter a space in the URL.'));
                }

                // The rule the external API and the CSV import apply: a web
                // address or a path on this site, within the column. Another
                // scheme, a protocol-relative address or a backslash would
                // send visitors to a broken redirect.
                if ($liveform->check_field_error('url') == false) {
                    $problem = pg_short_link_url_problem($liveform->get_field_value('url'));

                    if ($problem !== '') {
                        $liveform->mark_error('url', $problem);
                    }
                }

                break;

            case 'file':
                $liveform->validate_required_field('file', lang('File is required.'));

                // The file pickers search the server, so the posted id is
                // checked here: a file that exists and is no attachment.
                if ($liveform->check_field_error('file') == FALSE) {
                    $result = db_q("SELECT COUNT(*) FROM files WHERE id = ? AND attachment = 0 AND name != ''", array((string) $liveform->get_field_value('file'))) or output_error('Query failed.');
                    $row = mysqli_fetch_row($result);

                    if ($row[0] == 0) {
                        $liveform->mark_error('file', lang('The chosen file could not be found.'));
                    }
                }

                if (
                    ($liveform->check_field_error('file') == FALSE)
                    && !$kept($liveform->get_field_value('file'), 'file_id')
                    && pg_recycle_file_binned($liveform->get_field_value('file'))
                ) {
                    $liveform->mark_error('file', lang('The file is in the Recycle Bin. Restore it in the File Manager or choose another file.'));
                }

                break;
        }
    }

    $page_id = 0;

    // If this is a certain destination type then check selected page in different ways.
    if (
        ($liveform->get_field_value('destination_type') == 'page')
        || ($liveform->get_field_value('destination_type') == 'product_group')
        || ($liveform->get_field_value('destination_type') == 'product')
    ) {
        // If there is not already an error, then prepare values that we will need below.
        if ($liveform->check_form_errors() == FALSE) {
            switch ($liveform->get_field_value('destination_type')) {
                case 'page':
                    $page_field_name = 'page_id';
                    break;

                case 'product_group':
                    // If a catalog page was selected, then remember that.
                    if ($liveform->get_field_value('catalog_page_id') != '') {
                        $page_field_name = 'catalog_page_id';

                    // Otherwise a catalog detail page was selected, so remember that.
                    } else {
                        $page_field_name = 'catalog_detail_page_id';
                    }

                    break;

                case 'product':
                    $page_field_name = 'catalog_detail_page_id';
                    break;
            }

            $page_id = $liveform->get_field_value($page_field_name);
        }

        // If there is not already an error, then check if page exists.
        if ($liveform->check_form_errors() == FALSE) {
            $query = "SELECT COUNT(*) FROM page WHERE page_id = ?";
            $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
            $row = mysqli_fetch_row($result);

            // If the selected page does not exist, then add error.
            if ($row[0] == 0) {
                $liveform->mark_error($page_field_name, lang('The page does not exist.'));
            }
        }

        if (
            ($liveform->check_form_errors() == FALSE)
            && !$kept($page_id, 'page_id')
            && pg_recycle_page_binned($page_id)
        ) {
            $liveform->mark_error($page_field_name, lang('The page is in the Recycle Bin. Restore it in the File Manager or choose another page.'));
        }
    }

    return $page_id;
}

/**
 * The answer a form posted, before pg_short_link_answer_for() settles it.
 * A pattern name cannot show its destination, so a posted 0 (the select
 * offers it before the script disables it, or a hand-made post) keeps the
 * stored 301 or 302 instead of being turned into 301 without a word.
 *
 * @param int|null $posted  The posted answer, null when the form had no field
 * @param string $name
 * @param int $current_code  The stored answer, 0 on create
 * @return int|null
 */
function pg_short_link_form_posted_answer($posted, $name, $current_code)
{
    if (($posted === 0)
        && pg_short_link_is_pattern($name)
        && in_array((int) $current_code, array(301, 302), true)
    ) {
        return (int) $current_code;
    }

    return $posted;
}

/**
 * How it answers, checked with the name and the destination it goes with:
 * an address may not lead back to the link or round a loop. Run only on a
 * form without errors.
 *
 * @param liveform $liveform
 * @param int $short_link_id  0 on create
 * @param string $name
 * @param int $current_code  The stored answer, 0 on create
 * @return array  pg_short_link_redirect_columns()'s columns, problem
 */
function pg_short_link_form_answer($liveform, $short_link_id, $name, $current_code)
{
    $short_link_answer = array('columns' => array(), 'problem' => null);

    if ($liveform->check_form_errors() == FALSE) {
        $short_link_answer = pg_short_link_redirect_columns(
            array(
                'id' => (int) $short_link_id,
                'name' => $name,
                'destination_type' => $liveform->get_field_value('destination_type'),
                'url' => ($liveform->get_field_value('destination_type') == 'url') ? $liveform->get_field_value('url') : ''),
            pg_short_link_form_posted_answer(pg_short_link_redirects_ready() ? (int) $liveform->get_field_value('redirect_code') : null, $name, $current_code),
            (int) $current_code);

        if ($short_link_answer['problem'] !== null) {
            $liveform->mark_error($short_link_answer['problem']['field'], h($short_link_answer['problem']['message']));
        }
    }

    return $short_link_answer;
}

/**
 * The form's fields filled from a stored link, the way the edit screen
 * opens it: the page goes to the picker of its destination type (a product
 * group's to the catalog or the catalog detail picker, by the page's type).
 *
 * @param liveform $liveform
 * @param array $short_link  short_links row with page_type (the page's)
 * @return void
 */
function pg_short_link_form_fill($liveform, $short_link)
{
    foreach (pg_short_link_form_values($short_link) as $field => $value) {
        $liveform->assign_field_value($field, $value);
    }
}

/**
 * The form's field values for a stored link (pg_short_link_form_fill()).
 * Pure.
 *
 * @param array $short_link  short_links row with page_type (the page's)
 * @return array field => value
 */
function pg_short_link_form_values($short_link)
{
    $type = (string) ($short_link['destination_type'] ?? '');
    $values = array(
        'name' => (string) ($short_link['name'] ?? ''),
        'destination_type' => $type,
    );

    switch ($type) {
        case 'page':
            $values['page_id'] = $short_link['page_id'];
            break;

        case 'product_group':
            $values[(($short_link['page_type'] ?? '') === 'catalog') ? 'catalog_page_id' : 'catalog_detail_page_id'] = $short_link['page_id'];
            break;

        case 'product':
            $values['catalog_detail_page_id'] = $short_link['page_id'];
            break;

        case 'url':
            $values['url'] = $short_link['url'];
            break;

        case 'file':
            $values['file'] = $short_link['file_id'];
            break;
    }

    $values['product_group_id'] = $short_link['product_group_id'] ?? '';
    $values['product_id'] = $short_link['product_id'] ?? '';
    $values['tracking_code'] = $short_link['tracking_code'] ?? '';

    if (isset($short_link['redirect_code'])) {
        $values['redirect_code'] = (string) (int) $short_link['redirect_code'];
    }

    return $values;
}

/**
 * Writes a checked form to the link: the name, the destination (only the
 * destination type's own column keeps its target, the others are cleared),
 * the tracking code, the answer columns and the change stamp. Run once
 * pg_short_link_form_validate_name(), pg_short_link_form_validate_destination()
 * and pg_short_link_form_answer() left no error.
 *
 * @param liveform $liveform
 * @param int $id
 * @param string $name     pg_short_link_form_validate_name()'s name
 * @param mixed $page_id   pg_short_link_form_validate_destination()'s page
 * @param array $answer    pg_short_link_form_answer()
 * @param int $user_id
 * @return bool
 */
function pg_short_link_form_save($liveform, $id, $name, $page_id, $answer, $user_id)
{
    $sql_answer = '';
    $answer_values = array();

    foreach ($answer['columns'] as $answer_column => $answer_value) {
        $sql_answer .= $answer_column . " = ?,
                ";
        $answer_values[] = (string) (int) $answer_value;
    }

    $destination = array('product_group_id' => '', 'product_id' => '', 'url' => '', 'file_id' => '');
    $destination_column = pg_short_link_form_destination_column($liveform->get_field_value('destination_type'));

    if ($destination_column !== null) {
        $destination[$destination_column[0]] = $liveform->get_field_value($destination_column[1]);
    }

    return (bool) db_q(
        "UPDATE short_links
        SET
            name = ?,
            destination_type = ?,
            page_id = ?,
            product_group_id = ?,
            product_id = ?,
            url = ?,
            tracking_code = ?,
            file_id = ?,
            " . $sql_answer . "last_modified_user_id = ?,
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = ?",
        array_merge(
            array((string) $name, (string) $liveform->get_field_value('destination_type'), (string) $page_id, (string) $destination['product_group_id'], (string) $destination['product_id'], (string) $destination['url'], (string) $liveform->get_field_value('tracking_code'), (string) $destination['file_id']),
            $answer_values,
            array((string) (int) $user_id, (string) (int) $id)
        ));
}
