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
 * (product_builder.php) so this screen and edit_product.php cannot disagree
 * about it.
 *
 * Replaces the screen of the same name. The old one is
 * untouched and remain the reference for correct behaviour.
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
include_once('product_builder.php');

$liveform = new liveform('add_product');

if ($_POST) {

    validate_token_field();

    // Deliberately output_error + "go back" rather than a liveform redirect:
    // this form is long, and a redirect would throw away everything typed. The
    // browser's back button keeps it. Same choice the previous screen made.
    if (trim($_POST['name']) === '') {
        output_error(
            lang(array('string' => '{var:1} is required', 'vars' => array(lang('Product ID / SKU')))) .
            '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // Same three checks the previous screen made. The contact group one matters
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

    pg_pb_validate_product_post($user);


    $result = pg_pb_save_new_product();

    // A product form is drawn on the field editor, which needs the row to exist
    // first. When the operator asked for one, the save lands there instead of on
    // a list screen — same trip the previous screen made, extended to a set.
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
    // same place the previous screen ended.
    if ($result['mode'] === 'group') {
        $liveform_target = new liveform('view_products');
        $liveform_target->add_notice(
            lang(array(
                'string' => '{var:1} variant product{suffix:1} were created.',
                'vars'   => array(count($result['product_ids'])),
                'suffix' => count($result['product_ids']) == 1 ? '' : 's')));

        go(PATH . SOFTWARE_DIRECTORY . '/edit_product_group.php?id=' . (int) $result['group_id']);
    }

    $liveform_target = new liveform('view_products');
    $liveform_target->add_notice(lang(array('string' => 'product ({var:1}) was created', 'vars' => array($result['name']))));

    go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');
}


/* ------------------------------------------------------------------ *
 * Form
 * ------------------------------------------------------------------ */

// The screen itself lives in product_builder.php so that the edit screen draws
// the same one. This file owns the create half: what a new product starts as,
// and what happens when the button is pressed.
pg_pb_render_product_screen(array(), array('mode' => 'create'));
