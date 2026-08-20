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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_ecommerce_access($user);

$form = new liveform('edit_offer_rule');

$offer_rule = db_item("
    SELECT
        id,
        name,
        required_subtotal,
        required_quantity
    FROM offer_rules
    WHERE id = '" . e($_REQUEST['id']) . "'");

// If the form was not just submitted, then output it.
if (!$_POST) {

    // If the form has not been submitted yet, then pre-populate fields with data.
    if ($form->is_empty()) {

        $form->set('name', $offer_rule['name']);
        
        // If there is a subtotal, then convert to dollars and show it. Otherwise, show blank field.
        if ($offer_rule['required_subtotal']) {
            $form->set('required_subtotal', number_format($offer_rule['required_subtotal'] / 100, 2, '.', ''));
        }

        $required_products = db_values("
            SELECT product_id FROM offer_rules_products_xref
            WHERE offer_rule_id = '" . e($offer_rule['id']) . "'");

        $form->set('required_products', $required_products);

        // If there are required products and a quantity, then show quantity. Otherwise, show blank.
        if ($required_products and $offer_rule['required_quantity']) {
            $form->set('required_quantity', $offer_rule['required_quantity']);
        }
    }

    $form->set('required_products', 'options', get_product_options($include_blank_option = false));

    echo pg_page_shell([
        'title'=> lang('Edit Offer Rule'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Edit Offer Rule'),
        'cancel'=>array('enable'=>'true','url'=>'view_offer_rules.php'),
        'breadcrumb' => array(
            array('label' => lang('Offer Rules'), 'url' => 'view_offer_rules.php'),
            array('label' => $offer_rule['name']),
        ),
        'auto_main'=>false,
    ]);

    $content = render(array(
        'template' => 'edit_offer_rule.php',
        'form' => $form,
        'screen' => 'edit',
        'offer_rule' => $offer_rule));

    echo $form->prepare($content);

    echo output_footer();
    
    $form->remove();

// Otherwise the form was just submitted so process it.
} else {

    validate_token_field();

    $form->add_fields_to_session();
    
    // If the user selected to delete this offer rule, then delete it.
    if ($form->field_in_session('delete')) {

        db("DELETE FROM offer_rules WHERE id = '" . e($form->get('id')) . "'");
        db("DELETE FROM offer_rules_products_xref WHERE offer_rule_id = '" . e($form->get('id')) . "'");

        log_activity(lang(array('string'=>'Offer rule ({var:1}) was deleted.','vars'=>$offer_rule['name'])) );
        $form->remove();
        $form_view_offer_rules = new liveform('view_offer_rules');
        $form_view_offer_rules->add_notice(lang('The offer rule has been deleted.'));

    // Otherwise the user selected to save the offer rule, so save it.
    } else {

        $form->validate_required_field('name', lang('Name is required.'));

        // If there is not already an error for the name field, and that name is already in use,
        // then show error.
        if (
            (!$form->check_field_error('name'))
            and (db_value("
                SELECT COUNT(*) FROM offer_rules
                WHERE
                    (name = '" . e($form->get('name')) . "')
                    AND (id != '" . e($form->get('id')) . "')") != 0)
        ) {
            $form->mark_error('name', lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
        }

        // If the user did not enter a subtotal or select required products, then show error.
        if (!$form->get('required_subtotal') and !$form->get('required_products')) {
            $form->mark_error('', lang('Sorry, you must enter a required subtotal or select a required product.'));
        }

        // If there are required products, but no quantity was entered, then show error.
        if ($form->get('required_products') and !$form->get('required_quantity')) {
            $form->mark_error('required_quantity', lang('You have selected a required product, so please select a required quantity.'));
        }

        // If there is an error, forward user back to previous screen.
        if ($form->check_form_errors()) {
            go($_SERVER['PHP_SELF'] . '?id=' . $form->get('id'));
        }

        // remove commas and spaces from price
        $required_subtotal = str_replace(',', '', $form->get('required_subtotal'));
        $required_subtotal = str_replace(' ', '',$required_subtotal); 
        // convert price from dollars to cents
        $required_subtotal = $required_subtotal * 100;

        db("UPDATE offer_rules
            SET
                name = '" . e($form->get('name')) . "',
                required_subtotal = '" . e($required_subtotal) . "',
                required_quantity = '" . e($form->get('required_quantity')) . "',
                user = '" . e(USER_ID) . "',
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . e($form->get('id')) . "'");

        // Delete existing required products.
        db("DELETE FROM offer_rules_products_xref WHERE offer_rule_id = '" . e($form->get('id')) . "'");

        // Add new required products.
        foreach ($form->get('required_products') as $product_id) {
            db("INSERT INTO offer_rules_products_xref (offer_rule_id, product_id)
                VALUES ('" . e($form->get('id')) . "', '" . e($product_id) . "')");
        }
        
        log_activity(lang(array('string'=>'Offer rule ({var:1}) was modified.','vars'=>$form->get('name'))) );
        $form->remove();
        $form_view_offer_rules = new liveform('view_offer_rules');
        $form_view_offer_rules->add_notice(lang('The offer rule has been saved.'));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_offer_rules.php');
}