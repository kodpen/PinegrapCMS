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
 * Create a product — v2.
 *
 * One screen for both shapes a product can take. The attribute picker decides
 * which one: pick options that produce more than one combination and the save
 * writes a product group (display_type 'select') plus one product per
 * combination; pick one or none and it writes a single product with no group.
 *
 * The decision itself lives in pg_pb_mode() / pg_pb_save_new_product()
 * (product_builder.php) so this screen and edit_product2.php cannot disagree
 * about it.
 *
 * Development screen. add_product.php and add_product_variants.php are
 * untouched and remain the reference for correct behaviour.
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
include_once('product_builder.php');

$liveform = new liveform('add_product2');

if ($_POST) {

    validate_token_field();

    // Deliberately output_error + "go back" rather than a liveform redirect:
    // this form is long, and a redirect would throw away everything typed. The
    // browser's back button keeps it. Same choice add_product.php makes.
    if (trim($_POST['name']) === '') {
        output_error(
            lang(array('string' => '{var:1} is required', 'vars' => array(lang('Product ID / SKU')))) .
            '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // Same three checks add_product.php makes. The contact group one matters
    // most: the select only offers groups this user can reach, but the posted
    // value is not bound by what the select offered.
    if (!empty($_POST['contact_group_id'])
        && (validate_contact_group_access($user, $_POST['contact_group_id']) == FALSE)) {
        log_activity(
            lang('access denied because user does not have access to contact group that user selected for product'),
            $_SESSION['sessionusername']);
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    foreach (array('order_receipt_bcc_email_address', 'email_bcc') as $email_field) {

        $email_address = isset($_POST[$email_field]) ? trim($_POST[$email_field]) : '';

        if (($email_address !== '') && (validate_email_address($email_address) == FALSE)) {
            output_error(
                lang('The order receipt bcc e-mail address is invalid. <a href="javascript:history.go(-1);">Go back</a>.'));
        }
    }

    $result = pg_pb_save_new_product();

    // A product form is drawn on the field editor, which needs the row to exist
    // first. When the operator asked for one, the save lands there instead of on
    // a list screen — same trip add_product.php makes, extended to a set.
    //
    // Group mode goes to the set's template: one form, copied to every variant.
    // Single mode goes to the product's own fields, exactly as before.
    if (!empty($result['form'])) {

        $liveform->remove_form();

        if ($result['mode'] === 'group') {
            log_activity(
                lang(array(
                    'string' => '{var:1} variant product{suffix:1} were created in group ({var:2})',
                    'vars'   => array(count($result['product_ids']), $result['name']),
                    'suffix' => count($result['product_ids']) == 1 ? '' : 's')),
                $_SESSION['sessionusername']);

            go(PATH . SOFTWARE_DIRECTORY . '/view_fields.php?product_group_id=' . (int) $result['group_id']);
        }

        log_activity(
            lang(array('string' => 'product ({var:1}) was created', 'vars' => array($result['name']))),
            $_SESSION['sessionusername']);

        go(PATH . SOFTWARE_DIRECTORY . '/view_fields.php?product_id=' . (int) $result['product_ids'][0]);
    }

    if ($result['mode'] === 'group') {
        log_activity(
            lang(array(
                'string' => '{var:1} variant product{suffix:1} were created in group ({var:2})',
                'vars'   => array(count($result['product_ids']), $result['name']),
                'suffix' => count($result['product_ids']) == 1 ? '' : 's')),
            $_SESSION['sessionusername']);
    } else {
        log_activity(
            lang(array('string' => 'product ({var:1}) was created', 'vars' => array($result['name']))),
            $_SESSION['sessionusername']);
    }

    $liveform->remove_form();

    // Group mode lands on the set that was just created, so the operator can
    // check the variants together. Single mode lands on the product list, the
    // same place add_product.php ends.
    if ($result['mode'] === 'group') {
        $liveform_target = new liveform('view_products2');
        $liveform_target->add_notice(
            lang(array(
                'string' => '{var:1} variant product{suffix:1} were created.',
                'vars'   => array(count($result['product_ids'])),
                'suffix' => count($result['product_ids']) == 1 ? '' : 's')));

        go(PATH . SOFTWARE_DIRECTORY . '/edit_product2.php?group_id=' . (int) $result['group_id']);
    }

    $liveform_target = new liveform('view_products');
    $liveform_target->add_notice(lang(array('string' => 'product ({var:1}) was created', 'vars' => array($result['name']))));

    go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');
}


/* ------------------------------------------------------------------ *
 * Form
 * ------------------------------------------------------------------ */

// Switch defaults follow the store configuration: a shop with tax on should not
// need the operator to remember to tick "taxable" on every product.
$tax_checked       = (defined('ECOMMERCE_TAX') && ECOMMERCE_TAX == TRUE) ? ' checked="checked"' : '';
$shippable_checked = (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING == TRUE) ? ' checked="checked"' : '';

// Shipping fields are noise in a store that does not ship at all.
$shipping_card_class = (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING == TRUE) ? '' : ' d-none';

$image_code_template = db_value("SELECT product_image_code_template FROM config");

$has_groups = pg_pb_has_product_groups();

// Parent group (group mode) and catalog groups (single mode) are two different
// questions, so they are two different controls — see the note in
// pg_pb_save_new_product() about why a variant does not join browse categories.
$output_parent_group_field = '';
$output_catalog_group_field = '';

if ($has_groups) {

    $output_parent_group_field =
        '<div class="col-12 col-lg-8 my-2 pg-pb-group-only d-none">
            <label for="parent_group_id" class="form-label">' . lang('Parent Product Group') . '</label>
            <select style="width:100%" class="select2 form-select" id="parent_group_id" name="parent_group_id" data-placeholder="' . lang('Select Parent Group') . '">
                <option value="0">-' . lang('None') . '-</option>
                ' . get_product_group_options(0, 0, 0, 0, array(), FALSE) . '
            </select>
            <div class="form-text">' . lang('The new variant group is placed under this group in the catalog.') . '</div>
        </div>';

    $output_catalog_group_field =
        '<div class="col-12 col-lg-8 my-2 pg-pb-single-only">
            <label for="catalog_group_ids" class="form-label">' . lang('Include product in to selected groups') . '</label>
            <select style="width:100%" class="select2 form-select" id="catalog_group_ids" name="catalog_group_ids[]" multiple="multiple" data-placeholder="' . lang('Include product in to selected groups') . '">
                ' . get_product_group_options(0, 0, 0, 0, array(), TRUE) . '
            </select>
            <div class="form-text">' . lang('A single product can sit in several groups.') . '</div>
        </div>';
} else {
    $output_parent_group_field = '<div class="col-12">' .
        pg_pb_render_empty_state(
            'bi-folder',
            lang('No product groups'),
            lang('The catalog has no groups yet, so this product is not placed under one.')) . '</div>';
}

$output_zone_options = pg_pb_render_zone_options();

$output_zones_field = '';

if ($output_zone_options !== '') {
    $output_zones_field =
        '<div class="col-12 my-2">
            <label for="allowed_zones" class="form-label">' . lang('Allowed Zones') . '</label>
            <select style="width:100%" class="select2 form-select" data-placeholder="' . lang('Click to select shipping zone(s)') . '" id="allowed_zones" name="allowed_zones[]" multiple="multiple">' . $output_zone_options . '</select>
        </div>';
}

/* ------------------------------------------------------------------ *
 * Blocks behind a configuration gate
 *
 * Each of these is absent, not disabled, when its gate is closed. A greyed-out
 * control for a payment gateway the store does not use is a question the
 * operator cannot answer.
 * ------------------------------------------------------------------ */

$output_commissionable = '';

if (defined('AFFILIATE_PROGRAM') && AFFILIATE_PROGRAM == TRUE) {
    $output_commissionable = pg_pb_render_switch_row(
        pg_pb_render_switch(array(
            'id'    => 'commissionable',
            'name'  => 'commissionable',
            'label' => lang('Commissionable'),
            'panel' =>
                '<div class="col-12 col-sm-6 col-lg-4">
                    <label for="commission_rate_limit" class="form-label">' . lang('Commission Rate Limit') . '</label>
                    <div class="input-group">
                        <input type="text" name="commission_rate_limit" id="commission_rate_limit" class="form-control" size="3" maxlength="3" inputmode="numeric" style="text-align:right;" />
                        <label for="commission_rate_limit" class="input-group-text">%</label>
                    </div>
                    <div class="form-text">(' . lang('leave blank for no limit') . ')</div>
                </div>',
        )));
}

$output_sage_group_id = '';

if (defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD == TRUE
    && defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY == 'Sage') {
    $output_sage_group_id =
        '<div class="col-12 col-sm-6 col-lg-4 my-2">
            <label class="form-label" for="sage_group_id">' . lang('Sage Group ID') . '</label>
            <input type="text" name="sage_group_id" id="sage_group_id" class="form-control" maxlength="50" />
        </div>';
}

// Recurring start day: ClearCommerce starts a profile immediately and has no
// concept of a delay, so the field would be a lie there.
$output_recurring_start = '';

if (defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY != 'ClearCommerce') {
    $output_recurring_start =
        '<div class="col-12 col-sm-6 col-lg-4 my-1">
            <label for="start" class="form-label">' . lang('Start (days)') . '</label>
            <div class="input-group">
                <input type="text" name="start" id="start" class="form-control" value="0" size="7" maxlength="7" inputmode="numeric" style="text-align:right;" />
                <span class="input-group-text" title="' . lang('day(s) from order date') . '">' . lang('day(s)') . '</span>
            </div>
            <div class="form-text text-end">' . lang('0 to start immediately') . '</div>
        </div>';
}

$output_recurring_profile_disabled = '';

if (defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD == TRUE
    && defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY == 'PayPal Payments Pro') {
    $output_recurring_profile_disabled = pg_pb_render_switch_row(
        pg_pb_render_switch(array(
            'id'    => 'recurring_profile_disabled_perform_actions',
            'name'  => 'recurring_profile_disabled_perform_actions',
            'label' => lang('Perform action(s) if profile is disabled'),
            'panel' =>
                '<div class="col-12">
                    <div class="alert alert-warning">' . lang('requires recurring payment job') . '</div>
                </div>' .
                pg_pb_render_switch_row(
                    pg_pb_render_switch(array(
                        'id'    => 'recurring_profile_disabled_expire_membership',
                        'name'  => 'recurring_profile_disabled_expire_membership',
                        'label' => lang('Expire Membership'),
                    )) .
                    pg_pb_render_switch(array(
                        'id'    => 'recurring_profile_disabled_revoke_private_access',
                        'name'  => 'recurring_profile_disabled_revoke_private_access',
                        'label' => lang('Revoke Private Access'),
                    )), 'mt-0') .
                pg_pb_render_switch_row(
                    pg_pb_render_switch(array(
                        'id'    => 'recurring_profile_disabled_email',
                        'name'  => 'recurring_profile_disabled_email',
                        'label' => lang('Send E-mail to Customer'),
                        'panel' =>
                            '<div class="col-12 col-lg-6">
                                <label class="form-label" for="recurring_profile_disabled_email_subject">' . lang('Subject') . '</label>
                                <input type="text" id="recurring_profile_disabled_email_subject" name="recurring_profile_disabled_email_subject" class="form-control" maxlength="100" />
                            </div>
                            <div class="col-12 col-lg-6">
                                <label class="form-label" for="recurring_profile_disabled_email_page_id">' . lang('Page') . '</label>
                                <select name="recurring_profile_disabled_email_page_id" id="recurring_profile_disabled_email_page_id" class="form-select">
                                    <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Page')))) . '-</option>' . select_page() . '
                                </select>
                            </div>',
                    ))),
        )));
}

// What actually happens when a membership product is bought is spread across
// submit_order.php and membership_job.php. The operator types a number of days
// into a box and has no way of knowing the rest, so the screen states it.
$output_membership_effects = '';

$membership_contact_group_name = '';

if (defined('MEMBERSHIP_CONTACT_GROUP_ID') && (int) MEMBERSHIP_CONTACT_GROUP_ID !== 0) {
    $membership_contact_group_name = db_value(
        "SELECT name FROM contact_groups WHERE id = '" . (int) MEMBERSHIP_CONTACT_GROUP_ID . "' LIMIT 1");
}

$membership_effects = array(
    // submit_order.php extends from the later of today and the current expiry,
    // so buying early does not cost the customer the days they already paid for.
    lang('An existing membership is extended rather than restarted.'),
    // The days are multiplied by the ordered quantity.
    lang('The number of days is multiplied by the ordered quantity.'),
    // contacts.member_id is set from the order reference code the first time,
    // and every contact sharing that member id is extended together.
    lang('Customers who share a membership number are extended together.'),
);

if ($membership_contact_group_name != '') {
    $membership_effects[] = lang(array(
        'string' => 'The customer is added to the "{var:1}" contact group.',
        'vars'   => array($membership_contact_group_name)));
}

if (defined('MEMBERSHIP_EXPIRATION_WARNING_EMAIL') && MEMBERSHIP_EXPIRATION_WARNING_EMAIL == TRUE) {
    $membership_effects[] = lang('A warning e-mail is sent before the membership expires.');
} else {
    $membership_effects[] = lang('No expiry warning e-mail is configured in settings.');
}

foreach ($membership_effects as $membership_effect) {
    $output_membership_effects .= '<li>' . $membership_effect . '</li>';
}

// The category list is a file shipped with the software. Saying how many
// entries were loaded is the difference between "the picker is broken" and
// "the file for this language is missing".
$taxonomy_status = pg_pb_google_taxonomy_status();

if ($taxonomy_status['installed']) {
    $output_taxonomy_status = h(lang(array(
        'string' => '{var:1} categories loaded ({var:2}). Pick one, or type a category yourself.',
        'vars'   => array($taxonomy_status['count'], $taxonomy_status['locale']))));
} else {
    $output_taxonomy_status = h(lang(array(
        'string' => 'assets/google_taxonomy/google_taxonomy_{var:1}.json is missing, so there is nothing to pick from. Type the category or its number yourself.',
        'vars'   => array($taxonomy_status['locale']))));
}

// Custom product fields are named by the operator in settings; an unnamed one
// is an unused one and does not appear.
$output_custom_product_fields = '';

for ($custom_field_number = 1; $custom_field_number <= 4; $custom_field_number++) {

    $custom_field_constant = 'ECOMMERCE_CUSTOM_PRODUCT_FIELD_' . $custom_field_number . '_LABEL';

    if (!defined($custom_field_constant) || constant($custom_field_constant) == '') {
        continue;
    }

    $output_custom_product_fields .=
        '<div class="col-12 col-lg-6 my-2">
            <label for="custom_field_' . $custom_field_number . '" class="form-label">' . h(constant($custom_field_constant)) . '</label>
            <input class="form-control" type="text" id="custom_field_' . $custom_field_number . '" name="custom_field_' . $custom_field_number . '" maxlength="255" />
        </div>';
}

// Every string the builder script renders comes from here, so nothing escapes
// lang(). {count} is substituted client-side.
$labels = array(
    'Product ID / SKU'   => lang('Product ID / SKU'),
    'Short Description'  => lang('Short Description'),
    'Unit Price'         => lang('Unit Price'),
    'Inventory Quantity' => lang('Inventory Quantity'),
    'Images'             => lang('Images'),
    'Add Image'          => lang('Add Image'),
    'Remove'             => lang('Remove'),
    'Cover'              => lang('Cover'),
    'Select All'         => lang('Select All'),
    'Clear'              => lang('Clear'),
    'Combinations'       => lang('Combinations'),
    'Label'              => lang('Label'),
    "'No Thanks' Option" => lang('\'No Thanks\' Option'),
    'required_sku'       => lang(array('string' => '{var:1} is required', 'vars' => array(lang('Product ID / SKU')))),
    'request_failed'     => lang('Sorry, we could not accept your request.'),
    'no_images_to_apply' => lang('There are no images to apply.'),
    'images_applied'     => lang('Images applied to {count} variants.'),
    'Create'             => lang('Create'),
    'Create & Continue'  => lang('Create & Continue'),
    'membership_summary' => lang('Buying this extends the customer\'s membership by {days} days.'),
    'Google Product Category' => lang('Google Product Category'),
    'single_summary'     => lang('A single product will be created. No catalog group is added.'),
    'group_summary'      => lang('{count} products and one product group covering them will be created.'),
);

// The nav lists only sections that are actually rendered — a link to a section
// that is not on the page is a dead control.
// Inventory sits above Variants on purpose: the matrix seeds every row's stock
// from the quantity typed here, so entering it afterwards means generating the
// rows, scrolling down, typing, scrolling back and pressing "apply to all".
// Parent groups sit below Variants for the same reason in reverse — whether
// this is one product in several groups or several products under one group is
// only settled once the attribute selection is made.
$sections = array(
    array('id' => 'pg_pb_sec_images',    'label' => lang('Image Options'),       'icon' => 'bi-images'),
    array('id' => 'pg_pb_sec_basic',     'label' => lang('Main Informations'),   'icon' => 'bi-info-circle'),
    array('id' => 'pg_pb_sec_variants',  'label' => lang('Variants'),            'icon' => 'bi-diagram-3'),
    array('id' => 'pg_pb_sec_groups',    'label' => lang('Parent Product Groups'), 'icon' => 'bi-folder'),
);

if ($shipping_card_class === '') {
    $sections[] = array('id' => 'pg_pb_sec_shipping', 'label' => lang('Shipping'), 'icon' => 'bi-truck');
}

// A variant set's form template needs the 2026.4 columns. Without them the
// switch would create a set whose form has nowhere to live, so the section stays
// off the screen entirely rather than offering something that cannot work.
// A single product's form is older than that and always available.
$form_feature_ready = pg_pb_form_template_ready();

$sections[] = array('id' => 'pg_pb_sec_checkout',  'label' => lang('Checkout Options'),       'icon' => 'bi-credit-card');
$sections[] = array('id' => 'pg_pb_sec_membership','label' => lang('Membership'),            'icon' => 'bi-person-badge');

// Gift cards are a store-wide feature. With it switched off in settings the
// whole section goes, rather than offering a switch that produces a product
// nothing in the checkout knows how to sell.
$gift_cards_enabled = (defined('ECOMMERCE_GIFT_CARD') && ECOMMERCE_GIFT_CARD == TRUE);

if ($gift_cards_enabled) {
    $sections[] = array('id' => 'pg_pb_sec_giftcard', 'label' => lang('Email Gift Card'), 'icon' => 'bi-gift');
}
$sections[] = array('id' => 'pg_pb_sec_complete',  'label' => lang('Order Complete Options'), 'icon' => 'bi-bag-check');

if ($form_feature_ready) {
    $sections[] = array('id' => 'pg_pb_sec_form', 'label' => lang('Product Form'), 'icon' => 'bi-ui-checks');
}

$sections[] = array('id' => 'pg_pb_sec_seo',         'label' => lang('Site Search & SEO'), 'icon' => 'bi-search');
$sections[] = array('id' => 'pg_pb_sec_identifiers', 'label' => lang('RSS Feed'),         'icon' => 'bi-upc-scan');
$sections[] = array('id' => 'pg_pb_sec_helpful',     'label' => lang('Helpful Contents'), 'icon' => 'bi-journal-text');

print
pg_page_shell(array(
    'title'         => lang('Create Product'),
    'extra classes' => 'products',
    'icon'          => 'store',
    'heading'       => lang('Create Product'),
    'cancel'        => array('enable' => 'true', 'url' => 'view_products.php'),
    'breadcrumb'    => array(
        array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php'),
        array('label' => lang('Create Product'))),
)) .
get_wysiwyg_editor_code(array('full_description', 'details', 'out_of_stock_message', 'order_receipt_message')) .
pg_pb_render_styles() . '

<div class="row">
    <div class="col-12">
        ' . $liveform->output_errors() . '
        ' . $liveform->get_warnings() . '
        ' . $liveform->output_notices() . '
    </div>
</div>

<form name="form" id="pg_pb_form" action="add_product2.php" method="post">
    ' . get_token_field() . '
    <input type="hidden" id="variants_json" name="variants_json" value="" />
    <input type="hidden" id="attributes_meta_json" name="attributes_meta_json" value="" />

    <div class="row">

        <!-- ------------------------------------------------------- nav -->
        <div class="col-12 col-lg-3 col-xxl-2">
            ' . pg_pb_render_section_nav($sections) . '
        </div>

        <div class="col-12 col-lg-9 col-xxl-10">

            <div class="row">

                <!-- ----------------------------------------------- images -->
                <!-- No card: images are the first thing an operator drops in and
                     the picker is already a bordered drop area. Wrapping it in a
                     second bordered surface with a header just adds chrome
                     around a field. -->
                <div class="col-12 mb-4" id="pg_pb_sec_images">
                    ' . pg_pb_render_image_picker(
                            array(),
                            '<button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-bs-toggle="modal" data-bs-target="#image_code" title="' . lang('Code') . '"><i class="bi bi-code-slash"></i></button>') . '

                    <div class="modal fade" id="image_code" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">' . lang('Code') . '</h5>
                                    <button type="button" title="' . lang('Close') . '" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <div class="alert alert-primary">' . lang('Tags') . ': <span>^^image_loop_start^^</span>, <span>^^image_alt^^</span>, <span>^^image_url^^</span>, <span>^^image_loop_end^^</span></div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <textarea id="code" name="code">' . h($image_code_template) . '</textarea>
                                            ' . get_codemirror_includes() . '
                                            ' . get_codemirror_javascript(array('id' => 'code', 'code_type' => 'mixed')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ---------------------------------------------- product -->
                <div class="col-12" id="pg_pb_sec_basic">
                    <div class="card mb-4">
                        <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle text-primary"></i>
                            <span class="h5 mb-0 text-primary fw-bold">' . lang('Main Informations') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">

                                <div class="col-12 col-lg-4 my-2">
                                    <label for="name" class="form-label">*' . lang('Product ID / SKU') . '</label>
                                    <input type="text" name="name" id="name" class="form-control" required />
                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                    <div class="form-text pg-pb-group-only d-none">' . lang('Used as the prefix of every variant SKU.') . '</div>
                                </div>

                                <div class="col-12 col-lg-5 my-2">
                                    <label for="short_description" class="form-label">' . lang('Short Description') . '</label>
                                    <input type="text" name="short_description" id="short_description" class="form-control" maxlength="255" />
                                    <div class="form-text pg-pb-group-only d-none">' . lang('Becomes the product group name shown in the catalog.') . '</div>
                                </div>

                                <div class="col-12 col-lg-3 my-2">
                                    <label for="price" class="form-label">' . lang('Unit Price') . '</label>
                                    <div class="input-group">
                                        <input value="0" type="text" name="price" id="price" class="form-control" maxlength="12" inputmode="numeric" style="text-align:right;" />
                                        <label class="input-group-text" for="price">' . BASE_CURRENCY_SYMBOL . '</label>
                                    </div>
                                    <div class="form-text pg-pb-group-only d-none">' . lang('Starting price for every variant; each row can differ.') . '</div>
                                </div>

                                <div class="col-6 col-lg-3 my-2">
                                    <label for="selection_type" class="form-label">' . lang('Selection Type') . '</label>
                                    <select name="selection_type" id="selection_type" class="form-select">' . select_selection_type('quantity') . '</select>
                                </div>
                                <div class="col-6 col-lg-3 my-2">
                                    <label for="default_quantity" class="form-label">' . lang('Default Quantity') . '</label>
                                    <input class="form-control" value="1" type="number" min="0" name="default_quantity" id="default_quantity" />
                                </div>
                                <div class="col-6 col-lg-3 my-2">
                                    <label for="minimum_quantity" class="form-label">' . lang('Min. Quantity') . '</label>
                                    <input class="form-control" value="" type="number" min="0" name="minimum_quantity" id="minimum_quantity" />
                                </div>
                                <div class="col-6 col-lg-3 my-2">
                                    <label for="maximum_quantity" class="form-label">' . lang('Max. Quantity') . '</label>
                                    <input class="form-control" value="" type="number" min="0" name="maximum_quantity" id="maximum_quantity" />
                                </div>

                                <div class="col-12 mt-3 mb-2">
                                    <label for="full_description" class="form-label">' . lang('Full Description') . '</label>
                                    <textarea id="full_description" name="full_description"></textarea>
                                </div>

                                <div class="col-12 my-2">
                                    <label for="details" class="form-label">' . lang('Details') . '</label>
                                    <textarea id="details" name="details"></textarea>
                                </div>

                                ' . pg_pb_render_switch_row(
                                        pg_pb_render_switch(array(
                                            'id'      => 'enabled',
                                            'name'    => 'enabled',
                                            'label'   => lang('Enabled'),
                                            'checked' => TRUE,
                                        )) .
                                        pg_pb_render_switch(array(
                                            'id'      => 'taxable',
                                            'name'    => 'taxable',
                                            'label'   => lang('Taxable'),
                                            'checked' => ($tax_checked !== ''),
                                        )) .
                                        // Recurring lives here rather than in a card of its own:
                                        // it changes what the variant rows offer, so it has to be
                                        // decided before the operator gets to them.
                                        pg_pb_render_switch_row(
                                            pg_pb_render_switch(array(
                                                'id'    => 'recurring',
                                                'name'  => 'recurring',
                                                'label' => lang('Recurring Payment'),
                                                'help'  => lang('Charge the customer on a schedule instead of once.'),
                                                'panel' =>
                                                    pg_pb_render_switch_row(
                                                        pg_pb_render_switch(array(
                                                            'id'    => 'recurring_schedule_editable_by_customer',
                                                            'name'  => 'recurring_schedule_editable_by_customer',
                                                            'label' => lang('Allow customer to set schedule'),
                                                            'help'  => lang('You may select default values for the schedule below'),
                                                        )), 'mt-0') .
    
                                                    $output_recurring_start .
    
                                                    '<div class="col-12 col-sm-6 col-lg-4 my-1">
                                                        <label for="number_of_payments" class="form-label">' . lang('Number of Payments') . '</label>
                                                        <input type="text" name="number_of_payments" id="number_of_payments" class="form-control" size="7" maxlength="7" inputmode="numeric" />
                                                        <div class="form-text">' . get_number_of_payments_message() . '</div>
                                                    </div>
                                                    <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                        <label for="payment_period" class="form-label">' . lang('Payment Period') . '</label>
                                                        <select name="payment_period" id="payment_period" class="form-select">' . select_payment_period('Monthly') . '</select>
                                                    </div>' .
    
                                                    $output_recurring_profile_disabled,
                                            ))) .
                                        // Inventory tracking sits with the other
                                        // product-wide switches: like recurring, it
                                        // decides what the variant rows are seeded
                                        // with, so it belongs above them.
                                        pg_pb_render_switch(array(
                                            'id'    => 'inventory',
                                            'name'  => 'inventory',
                                            'label' => lang('Inventory Tracking'),
                                            'panel' =>
                                                '<div class="col-12 col-sm-6 col-lg-4">
                                                    <label for="inventory_quantity" class="form-label">' . lang('Inventory Quantity') . '</label>
                                                    <input type="number" min="0" name="inventory_quantity" id="inventory_quantity" class="form-control" value="" />
                                                    <div class="form-text pg-pb-group-only d-none">' . lang('Starting stock for every variant; each row can differ.') . '</div>
                                                </div>
                                                <div class="col-12 mt-3">
                                                    <label for="out_of_stock_message" class="form-label">' . lang('Out of Stock Message') . '</label>
                                                    <textarea id="out_of_stock_message" name="out_of_stock_message"></textarea>
                                                </div>
                                                ' . pg_pb_render_switch_row(
                                                        pg_pb_render_switch(array(
                                                            'id'    => 'backorder',
                                                            'name'  => 'backorder',
                                                            'label' => lang('Backorder'),
                                                            'help'  => lang('Let customers order this even when stock reaches zero.'),
                                                        ))),
                                        ))) . '

                            </div>
                        </div>
                    </div>
                </div>

                <!-- --------------------------------------------- variants -->
                <div class="col-12" id="pg_pb_sec_variants">
                    <div class="card mb-4">
                        <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                            <i class="bi bi-diagram-3 text-primary"></i>
                            <span class="h5 mb-0 text-primary fw-bold">' . lang('Variants') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                ' . pg_pb_render_switch_row(
                                        pg_pb_render_switch(array(
                                            // No name: this only reveals UI. What the server
                                            // acts on is the combination count, and that is
                                            // read from the matrix, not from this switch.
                                            'id'    => 'pg_pb_has_variants',
                                            'label' => lang('This product has variants'),
                                            'help'  => lang('Tick the options this product comes in. Two or more combinations create a variant group; one or none creates a single product.'),
                                            'panel' =>
                                                '<div class="col-12 d-flex justify-content-end mb-2">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#pg_pb_attribute_modal">
                                                        <i class="bi bi-plus-circle me-2"></i>' . lang('New Attribute') . '</button>
                                                </div>
                                                <div class="col-12">
                                                    ' . pg_pb_render_dimensions() . '
                                                </div>
                                                <div class="col-12 mt-3 d-none" id="pg_pb_combo_preview"></div>

                                                <div class="col-12 col-lg-6 mt-3 pg-pb-group-only d-none">
                                                    <label for="sku_template" class="form-label">' . lang('SKU Template') . '</label>
                                                    <input type="text" id="sku_template" name="sku_template" class="form-control" placeholder="' . lang('e.g. {Color}-{Size}') . '" />
                                                    <div class="pg-pb-tokens d-flex flex-wrap gap-1 mt-1" data-pg-pb-target="sku_template"></div>
                                                    <div class="form-text">' . lang('Added after the SKU above. Use attribute names in curly braces.') . '</div>
                                                </div>
                                                <div class="col-12 col-lg-6 mt-3 pg-pb-group-only d-none">
                                                    <label for="short_description_template" class="form-label">' . lang('Short Description Template') . '</label>
                                                    <input type="text" id="short_description_template" name="short_description_template" class="form-control" placeholder="' . lang('e.g. T-Shirt {Color}-{Size}') . '" />
                                                    <div class="pg-pb-tokens d-flex flex-wrap gap-1 mt-1" data-pg-pb-target="short_description_template"></div>
                                                    <div class="form-text">' . lang('The whole description. Leave blank to use the one above plus the combination.') . '</div>
                                                </div>

                                                <!-- The matrix belongs to this switch, so it lives
                                                     inside its panel rather than in a card of its
                                                     own further down the page. -->
                                                <div class="col-12 mt-4 d-none" id="pg_pb_matrix_wrapper">
                                                    <hr class="mt-0" />
                                                    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                                        <i class="bi bi-grid-3x3-gap text-primary"></i>
                                                        <span class="h6 mb-0 fw-bold">' . lang('Variants to Create') . '</span>
                                                        <span id="pg_pb_variant_count" class="badge text-bg-primary">0</span>
                                                        <div class="d-flex flex-wrap gap-1 ms-auto">
                                                            <button type="button" id="pg_pb_apply_price" class="btn btn-sm btn-outline-secondary"><i class="bi bi-currency-exchange me-1"></i>' . lang('Apply Price to All') . '</button>
                                                            <button type="button" id="pg_pb_apply_stock" class="btn btn-sm btn-outline-secondary"><i class="bi bi-boxes me-1"></i>' . lang('Apply Stock to All') . '</button>
                                                            <button type="button" id="pg_pb_apply_images" class="btn btn-sm btn-outline-secondary"><i class="bi bi-images me-1"></i>' . lang('Apply Images to All') . '</button>
                                                        </div>
                                                    </div>

                                                    <!-- Below lg the header row is hidden and every cell becomes a
                                                         block, so a row reads as a small card; each cell carries its
                                                         own label for that width. Bootstrap display utilities only,
                                                         no table-responsive: the table is not overflowing, it is
                                                         being squeezed. -->
                                                    <table class="table table-hover align-middle mb-0 pg-pb-vtable">
                                                        <thead class="d-none d-lg-table-header-group">
                                                            <tr>
                                                                <th>' . lang('Combinations') . '</th>
                                                                <th style="width:16%">' . lang('Product ID / SKU') . '</th>
                                                                <th style="width:22%">' . lang('Short Description') . '</th>
                                                                <th style="width:12rem">' . lang('Unit Price') . '</th>
                                                                <th style="width:7rem">' . lang('Inventory Quantity') . '</th>
                                                                <th style="width:11rem">' . lang('Images') . '</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="pg_pb_matrix"></tbody>
                                                    </table>
                                                </div>',
                                        ))) . '
                            </div>
                        </div>
                    </div>
                </div>

                <!-- --------------------------------------- parent groups -->
                <!-- Below Variants, not above: which question this control is
                     even asking depends on the attribute selection. One product
                     joins several groups; a set of products gets one group of
                     its own placed under a parent. -->
                <div class="col-12" id="pg_pb_sec_groups">
                    <div class="card mb-4">
                        <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                            <i class="bi bi-folder text-primary"></i>
                            <span class="h5 mb-0 text-primary fw-bold">' . lang('Parent Product Groups') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                ' . $output_parent_group_field . '
                                ' . $output_catalog_group_field . '
                            </div>
                        </div>
                    </div>
                </div>

                <!-- --------------------------------------------- shipping -->
                <div class="col-12' . $shipping_card_class . '" id="pg_pb_sec_shipping">
                    <div class="card mb-4">
                        <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                            <i class="bi bi-truck text-primary"></i>
                            <span class="h5 mb-0 text-primary fw-bold">' . lang('Shipping') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">

                                ' . pg_pb_render_switch_row(
                                        pg_pb_render_switch(array(
                                            'id'      => 'shippable',
                                            'name'    => 'shippable',
                                            'label'   => lang('Shippable'),
                                            'checked' => ($shippable_checked !== ''),
                                            'panel'   =>

                                                pg_pb_render_switch_row(
                                                    pg_pb_render_switch(array(
                                                        'id'    => 'convert_to_metric_system',
                                                        'name'  => 'convert_to_metric_system',
                                                        'label' => lang('Convert to metric system'),
                                                        'help'  => lang('Weight and dimensions are stored in pounds and inches; tick this to type them in kilograms and centimetres.'),
                                                    )), 'mt-0') .

                                                '<div class="col-6 col-lg-3 my-1">
                                                    <label for="weight" class="form-label">' . lang('Weight') . '</label>
                                                    <input value="0" type="number" step="0.01" min="0" name="weight" id="weight" class="form-control" />
                                                </div>
                                                <div class="col-6 col-lg-3 my-1">
                                                    <label for="length" class="form-label">' . lang('Length') . '</label>
                                                    <input value="0" type="number" step="0.01" min="0" name="length" id="length" class="form-control" />
                                                </div>
                                                <div class="col-6 col-lg-3 my-1">
                                                    <label for="width" class="form-label">' . lang('Width') . '</label>
                                                    <input value="0" type="number" step="0.01" min="0" name="width" id="width" class="form-control" />
                                                </div>
                                                <div class="col-6 col-lg-3 my-1">
                                                    <label for="height" class="form-label">' . lang('Height') . '</label>
                                                    <input value="0" type="number" step="0.01" min="0" name="height" id="height" class="form-control" />
                                                </div>

                                                <div class="col-6 col-lg-3 my-1">
                                                    <label for="primary_weight_points" class="form-label">' . lang('Primary Weight Points') . '</label>
                                                    <input value="0" type="number" step="0.01" min="0" name="primary_weight_points" id="primary_weight_points" class="form-control" />
                                                </div>
                                                <div class="col-6 col-lg-3 my-1">
                                                    <label for="secondary_weight_points" class="form-label">' . lang('Secondary Weight Points') . '</label>
                                                    <input value="0" type="number" step="0.01" min="0" name="secondary_weight_points" id="secondary_weight_points" class="form-control" />
                                                </div>
                                                <div class="col-6 col-lg-3 my-1">
                                                    <label for="preparation_time" class="form-label">' . lang('Preparation Time') . '</label>
                                                    <input type="number" min="0" name="preparation_time" id="preparation_time" class="form-control" value="" />
                                                </div>
                                                <div class="col-6 col-lg-3 my-1">
                                                    <label for="extra_shipping_cost" class="form-label">' . lang('Extra Shipping Cost') . '</label>
                                                    <div class="input-group">
                                                        <input value="0" type="text" name="extra_shipping_cost" id="extra_shipping_cost" class="form-control" style="text-align:right;" />
                                                        <label class="input-group-text" for="extra_shipping_cost">' . BASE_CURRENCY_SYMBOL . '</label>
                                                    </div>
                                                </div>

                                                ' . $output_zones_field .

                                                pg_pb_render_switch_row(
                                                    pg_pb_render_switch(array(
                                                        'id'    => 'free_shipping',
                                                        'name'  => 'free_shipping',
                                                        'label' => lang('Free Shipping'),
                                                    )) .
                                                    pg_pb_render_switch(array(
                                                        'id'    => 'container_required',
                                                        'name'  => 'container_required',
                                                        'label' => lang('Container Required'),
                                                    ))),
                                        ))) . '

                            </div>
                        </div>
                    </div>
                </div>

                <!-- ------------------------------------- checkout options -->
                <div class="col-12" id="pg_pb_sec_checkout">
                    <div class="card mb-4">
                        <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                            <i class="bi bi-credit-card text-primary"></i>
                            <span class="h5 mb-0 text-primary fw-bold">' . lang('Checkout Options') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">

                                <div class="col-12 col-lg-8 my-2">
                                    <label class="form-label" for="required_product">' . lang('Requires Product') . '</label>
                                    <select class="form-select" id="required_product" name="required_product">
                                        <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Product')))) . '-</option>' . select_product() . '
                                    </select>
                                    <div class="form-text">' . lang('The customer must also have this product in the cart.') . '</div>
                                </div>

                                ' . $output_sage_group_id . '
                                ' . $output_commissionable . '

                            </div>
                        </div>
                    </div>
                </div>

                <!-- ------------------------------------------- membership -->
                <div class="col-12" id="pg_pb_sec_membership">
                    <div class="card mb-4">
                        <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                            <i class="bi bi-person-badge text-primary"></i>
                            <span class="h5 mb-0 text-primary fw-bold">' . lang('Membership') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">

                                <div class="col-12 col-lg-4 my-2">
                                    <label for="membership_renewal" class="form-label">' . lang('Add Days to Customer\'s Membership') . '</label>
                                    <div class="input-group">
                                        <input type="text" name="membership_renewal" id="membership_renewal" class="form-control" size="7" maxlength="7" inputmode="numeric" style="text-align:right;" />
                                        <span class="input-group-text">' . lang('day(s)') . '</span>
                                    </div>
                                    <div class="form-text text-end">' . lang('0 for none') . '</div>
                                </div>

                                <!-- What buying this actually does lives in submit_order.php and
                                     membership_job.php. The operator types a number into a box
                                     and cannot see the rest, so it is written out here. Hidden
                                     until the box has a value: with none of this happening, the
                                     list would be describing a thing that does not occur. -->
                                <div class="col-12 col-lg-8 my-2 d-none" id="pg_pb_membership_effects">
                                    <div class="alert alert-secondary mb-0">
                                        <div class="fw-semibold mb-1" id="pg_pb_membership_summary"></div>
                                        <ul class="mb-0 ps-3 small">' . $output_membership_effects . '</ul>
                                    </div>
                                </div>

                                ' . pg_pb_render_switch_row(
                                        pg_pb_render_switch(array(
                                            'id'    => 'grant_private_access',
                                            'name'  => 'grant_private_access',
                                            'label' => lang('Grant Private Access to Customer'),
                                            'panel' =>
                                                '<div class="col-12 col-md-6 col-lg-4">
                                                    <label class="form-label" for="private_folder">' . lang('Set "View" Access to Folder') . '</label>
                                                    <select class="form-select" id="private_folder" name="private_folder">
                                                        <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Folder')))) . '-</option>' . select_folder(0, 0, 0, 0, array(), array(), 'private') . '
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-4">
                                                    <label for="private_days" class="form-label">' . lang('Length') . '</label>
                                                    <div class="input-group">
                                                        <input type="text" name="private_days" id="private_days" class="form-control" size="7" maxlength="7" inputmode="numeric" style="text-align:right;" />
                                                        <span class="input-group-text">' . lang('day(s)') . '</span>
                                                    </div>
                                                    <div class="form-text text-end">' . lang('leave blank for no expiration') . '</div>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-4">
                                                    <label class="form-label" for="send_to_page">' . lang('Set Customer\'s Start Page to') . '</label>
                                                    <select class="form-select" id="send_to_page" name="send_to_page">
                                                        <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Page')))) . '-</option>' . select_page() . '
                                                    </select>
                                                </div>',
                                        ))) . '


                            </div>
                        </div>
                    </div>
                </div>

                <!-- ------------------------------------------ gift card -->
                <div class="col-12' . ($gift_cards_enabled ? '' : ' d-none') . '" id="pg_pb_sec_giftcard">
                    <div class="card mb-4">
                        <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                            <i class="bi bi-gift text-primary"></i>
                            <span class="h5 mb-0 text-primary fw-bold">' . lang('Email Gift Card') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">

                                <!-- What ordering this does lives in submit_order.php and the
                                     e-mail queue; none of it is guessable from a switch. -->
                                <div class="col-12 d-none" id="pg_pb_giftcard_effects">
                                    <div class="alert alert-secondary mb-0">
                                        <ul class="mb-0 ps-3 small">' . $output_gift_card_effects . '</ul>
                                    </div>
                                </div>

                                ' . pg_pb_render_switch_row(
                                            pg_pb_render_switch(array(
                                                'id'    => 'gift_card',
                                                'name'  => 'gift_card',
                                                'label' => lang('Email Gift Card'),
                                                'panel' =>
                                                    '<div class="col-12 col-lg-8">
                                                        <label class="form-label" for="gift_card_email_subject">' . lang('Subject') . '</label>
                                                        <input type="text" id="gift_card_email_subject" name="gift_card_email_subject" class="form-control" maxlength="100" />
                                                        <div class="d-flex flex-wrap gap-1 mt-1" data-pg-pb-giftvar-target="gift_card_email_subject">' . $output_gift_card_variables . '</div>
                                                    </div>
    
                                                    <div class="col-12 mt-3">
                                                        <label class="form-label">' . lang('Format') . '</label>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input collapse-switcher" type="radio" id="gift_card_email_format_plain_text" name="gift_card_email_format" checked="checked" value="plain_text" data-bs-target="#gift_card_email_format_plain_text_row" />
                                                            <label class="form-check-label" for="gift_card_email_format_plain_text">' . lang('Plain Text') . '</label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input collapse-switcher" type="radio" id="gift_card_email_format_html" name="gift_card_email_format" value="html" data-bs-target="#gift_card_email_format_html_row" />
                                                            <label class="form-check-label" for="gift_card_email_format_html">' . lang('HTML') . '</label>
                                                        </div>
                                                    </div>
    
                                                    <div class="col-12 collapse popover fade bs-popover-bottom p-0 w-100 mb-2 mt-2" id="gift_card_email_format_plain_text_row">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <label for="gift_card_email_body" class="form-label">' . lang('Body') . '</label>
                                                            <textarea class="form-control" id="gift_card_email_body" name="gift_card_email_body" rows="4"></textarea>
                                                            <div class="form-label small text-muted mt-2 mb-1">' . lang('Insert a variable') . '</div>
                                                            <div class="d-flex flex-wrap gap-1" data-pg-pb-giftvar-target="gift_card_email_body">' . $output_gift_card_variables . '</div>
                                                        </div>
                                                    </div>
    
                                                    <div class="col-12 collapse popover fade bs-popover-bottom p-0 w-100 mb-2 mt-2" id="gift_card_email_format_html_row">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12 col-lg-8">
                                                                    <label class="form-label" for="gift_card_email_page_id">' . lang('Page') . '</label>
                                                                    <select class="form-select" id="gift_card_email_page_id" name="gift_card_email_page_id">
                                                                        <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Page')))) . '-</option>' . select_page() . '
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>',
                                            ))) . '
                            </div>
                        </div>
                    </div>
                </div>

                <!-- -------------------------------- order complete options -->
                <div class="col-12" id="pg_pb_sec_complete">
                    <div class="card mb-4">
                        <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                            <i class="bi bi-bag-check text-primary"></i>
                            <span class="h5 mb-0 text-primary fw-bold">' . lang('Order Complete Options') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">

                                <div class="col-12 my-2">
                                    <label class="form-label" for="order_receipt_message">' . lang('Order Receipt Page Message') . '</label>
                                    <textarea id="order_receipt_message" name="order_receipt_message"></textarea>
                                </div>

                                <div class="col-12 col-lg-4 my-2">
                                    <label class="form-label" for="order_receipt_bcc_email_address">' . lang('Order Receipt BCC E-mail Address') . '</label>
                                    <input type="text" class="form-control" id="order_receipt_bcc_email_address" name="order_receipt_bcc_email_address" maxlength="100" inputmode="email" />
                                </div>
                                <div class="col-12 col-lg-4 my-2">
                                    <label class="form-label" for="email_page">' . lang('E-mail Additional Page to Customer') . '</label>
                                    <select class="form-select" id="email_page" name="email_page">
                                        <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Page')))) . '-</option>' . select_page() . '
                                    </select>
                                </div>
                                <div class="col-12 col-lg-4 my-2">
                                    <label class="form-label" for="email_bcc">' . lang('BCC E-mail Address') . '</label>
                                    <input type="text" class="form-control" id="email_bcc" name="email_bcc" maxlength="100" inputmode="email" />
                                </div>

                                <div class="col-12 col-lg-4 my-2">
                                    <label class="form-label" for="contact_group_id">' . lang('Add to Contact Group') . '</label>
                                    <select class="form-select" id="contact_group_id" name="contact_group_id">
                                        <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Contact Group')))) . '-</option>' . select_contact_group(0, $user) . '
                                    </select>
                                </div>
                                <div class="col-12 col-lg-4 my-2">
                                    <label for="reward_points" class="form-label">' . lang('Reward Points') . '</label>
                                    <input type="text" name="reward_points" id="reward_points" class="form-control" size="5" maxlength="9" inputmode="numeric" style="text-align:right;" />
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- ----------------------------------------- product form -->
                <div class="col-12' . ($form_feature_ready ? '' : ' d-none') . '" id="pg_pb_sec_form">
                    <div class="card mb-4">
                        <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                            <i class="bi bi-ui-checks text-primary"></i>
                            <span class="h5 mb-0 text-primary fw-bold">' . lang('Product Form') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                ' . pg_pb_render_switch_row(
                                        pg_pb_render_switch(array(
                                            'id'    => 'product_form',
                                            'name'  => 'product_form',
                                            'label' => lang('Enable Product Form'),
                                            'help'  => lang('Ask the customer for information when they order this. The fields are drawn on the next screen.'),
                                            'panel' =>
                                                '<div class="col-12 mb-3">
                                                    <div class="alert alert-primary mb-0 pg-pb-group-only d-none">
                                                        <i class="bi bi-info-circle me-2"></i>' . lang('One form is drawn for the whole set and written to every variant.') . '
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-5">
                                                    <label class="form-label" for="form_name">' . lang('Form Title for Display') . '</label>
                                                    <input type="text" id="form_name" name="form_name" class="form-control" maxlength="100" />
                                                </div>
                                                <div class="col-12 col-lg-3">
                                                    <label class="form-label" for="form_label_column_width">' . lang('Label Column Width') . '</label>
                                                    <div class="input-group">
                                                        <input type="text" id="form_label_column_width" name="form_label_column_width" class="form-control" size="3" maxlength="3" inputmode="numeric" />
                                                        <label class="input-group-text" for="form_label_column_width">%</label>
                                                    </div>
                                                    <div class="form-text">' . lang('leave blank for auto') . '</div>
                                                </div>
                                                <div class="col-12 col-lg-4">
                                                    <label class="form-label">' . lang('Quantity Type') . '</label>
                                                    <div class="form-check">
                                                        <input value="One Form per Quantity" class="form-check-input" type="radio" id="form_quantity_type_one_form_per_quantity" name="form_quantity_type" checked="checked" />
                                                        <label class="form-check-label" for="form_quantity_type_one_form_per_quantity">' . lang('One form per quantity') . '</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input value="One Form per Product" class="form-check-input" type="radio" id="form_quantity_type_one_form_per_product" name="form_quantity_type" />
                                                        <label class="form-check-label" for="form_quantity_type_one_form_per_product">' . lang('One form per product') . '</label>
                                                    </div>
                                                </div>',
                                        ))) . '
                            </div>
                        </div>
                    </div>
                </div>

                <!-- -------------------------------------------------- seo -->
                <div class="col-12" id="pg_pb_sec_seo">
                    <div class="card mb-4">
                        <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                            <i class="bi bi-search text-primary"></i>
                            <span class="h5 mb-0 text-primary fw-bold">' . lang('Site Search & SEO') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">

                                <!-- Site search, not a search engine: these words feed the
                                     on-site search and the tag cloud. Full width and a tag
                                     input because it is a list, not a sentence. -->
                                <div class="col-12 my-2">
                                    <label for="keywords" class="form-label">' . lang('Search Keywords') . '</label>
                                    <input type="text" name="keywords" id="keywords" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add tags') . '" maxlength="255" />
                                    <script>
                                        if (document.body.contains(document.querySelector("input#keywords"))) {
                                            tagin(document.querySelector("#keywords"));
                                        }
                                    </script>
                                    <div class="form-text">' . lang('Used by the search on your own site, not by search engines.') . '</div>
                                </div>

                                <div class="col-12 col-lg-6 my-2">
                                    <label for="address_name" class="form-label">' . lang('Catalog Name') . '</label>
                                    <input type="text" name="address_name" id="address_name" class="form-control" />
                                    <div class="form-text">' . lang('This option determines the url address of the product. Automatically assigned if left blank.') . '</div>
                                </div>
                                <div class="col-12 col-lg-6 my-2">
                                    <label for="title" class="form-label">' . lang('Web Browser Title') . '</label>
                                    <input type="text" name="title" id="title" class="form-control" />
                                </div>
                                <div class="col-12 my-2">
                                    <label for="meta_description" class="form-label">' . lang('Web Browser Description') . '</label>
                                    <textarea name="meta_description" id="meta_description" class="form-control" maxlength="255"></textarea>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- ------------------------------------------ identifiers -->
                <div class="col-12" id="pg_pb_sec_identifiers">
                    <div class="card mb-4">
                        <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                            <i class="bi bi-upc-scan text-primary"></i>
                            <span class="h5 mb-0 text-primary fw-bold">' . lang('RSS Feed') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-lg-3 my-2">
                                    <label for="brand" class="form-label">' . lang('Brand') . '</label>
                                    <input type="text" name="brand" id="brand" class="form-control" maxlength="100" />
                                </div>
                                <div class="col-12 col-lg-3 my-2">
                                    <label for="gtin" class="form-label">' . lang('GTIN') . '</label>
                                    <input type="text" name="gtin" id="gtin" class="form-control" maxlength="50" placeholder="' . lang('e.g. UPC') . '" />
                                </div>
                                <div class="col-12 col-lg-3 my-2">
                                    <label for="mpn" class="form-label">' . lang('MPN') . '</label>
                                    <input type="text" name="mpn" id="mpn" class="form-control" maxlength="50" placeholder="' . lang('i.e. manufacturer product number') . '" />
                                </div>
                                <div class="col-12 col-lg-3 my-2">
                                    <label for="google_product_category" class="form-label">' . lang('Google Product Category') . '</label>
                                    <select name="google_product_category" id="google_product_category" class="form-select" style="width:100%"></select>
                                    <div class="form-text" id="pg_pb_taxonomy_status">' . $output_taxonomy_status . '</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- ------------------------------------- helpful contents -->
                <!-- Custom product fields are labelled in settings and mean
                     whatever that store decided they mean, so they sit with the
                     internal note rather than among the checkout controls. -->
                <div class="col-12" id="pg_pb_sec_helpful">
                    <div class="card mb-4">
                        <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                            <i class="bi bi-journal-text text-primary"></i>
                            <span class="h5 mb-0 text-primary fw-bold">' . lang('Helpful Contents') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                ' . $output_custom_product_fields . '
                                <div class="col-12 my-2">
                                    <label for="notes" class="form-label">' . lang('Notes') . '</label>
                                    <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
                                    <div class="form-text">' . lang('Internal note. Customers never see it.') . '</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="alert alert-secondary">
                        <i class="bi bi-info-circle me-2"></i>
                        ' . lang('Memberships, gift cards and recurring payments are set from the product edit screen after the product is created.') . '
                    </div>
                </div>

            </div>

            <!-- Summary and save are one element on purpose. The screen has to
                 say what the button will do, and saying it somewhere the
                 operator has scrolled past is the same as not saying it. -->
            <div class="position-sticky pb-3" style="bottom:0; z-index:3;">
                <div id="pg_pb_summary" class="alert alert-secondary shadow d-flex align-items-center flex-wrap gap-2 mb-0" role="status" aria-live="polite">
                    <i id="pg_pb_summary_icon" class="bi bi-box me-1 fs-5"></i>
                    <span id="pg_pb_summary_text">' . lang('A single product will be created. No catalog group is added.') . '</span>
                    <button type="submit" id="pg_pb_create" name="submit_create" value="Create" class="btn btn-success ms-auto" data-loading-content="' . lang('Creating') . '">
                        <i class="bi bi-plus-circle me-2"></i><span class="btn-text">' . lang('Create') . '</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
</form>

<!-- Outside the form on purpose — see pg_pb_render_attribute_modal(). -->
' . pg_pb_render_attribute_modal() . '
</main>
<script>
    /* "<\/" is escaped below because this JSON sits inside a script element: a
       translation containing a literal closing script tag would end the block
       early and dump the rest of the page as markup.
       The same applies to this comment, which is why the tag is written with a
       backslash here — spelling it out plainly terminated the block and left
       window.PinegrapProductBuilder undefined, so every request went out with
       an empty CSRF token. */
    window.PinegrapProductBuilder = {
        currencySymbol: ' . str_replace('</', '<\/', encode_json(BASE_CURRENCY_SYMBOL)) . ',
        token: ' . str_replace('</', '<\/', encode_json($_SESSION['software']['token'])) . ',
        labels: ' . str_replace('</', '<\/', encode_json($labels)) . '
    };
</script>
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/product_builder.js?v=' . @filemtime(dirname(__FILE__) . '/assets/product_builder.js') . '"></script>
' . output_footer();

$liveform->remove_form();
