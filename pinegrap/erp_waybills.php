<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - delivery notes
 *
 * Delivery notes only for now. Whether they can be filed as e-documents depends on an endpoint that is not confirmed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

include_once('liveform.class.php');
$liveform = new liveform('erp_waybills');

echo pg_page_shell(array(
    'title'               => lang('Delivery Notes'),
    'extra classes'       => 'erp erp_waybills',
    'icon'                => 'store',
    'heading'             => lang('Delivery Notes'),
    'heading_description' => lang('Delivery notes and the invoices they turn into.'),
    'cancel'              => false,
)) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-truck fs-1 d-block mb-3"></i>
                    ' . h(lang('This screen is not built yet.')) . '
                </div>
            </div>
        </div>
    </div>
</main>' . output_footer();

$liveform->remove_form();
