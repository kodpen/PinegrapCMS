<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - settings
 *
 * Behind its own right: numbering and where documents are sent are not everyday choices.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user, 'settings')) {
    exit();
}

include_once('liveform.class.php');
$liveform = new liveform('erp_settings');

echo pg_page_shell(array(
    'title'               => lang('ERP Settings'),
    'extra classes'       => 'erp erp_settings',
    'icon'                => 'store',
    'heading'             => lang('ERP Settings'),
    'heading_description' => lang('Numbering, default accounts, automatic invoicing and the Parasut connection.'),
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
                    <i class="bi bi-sliders fs-1 d-block mb-3"></i>
                    ' . h(lang('This screen is not built yet.')) . '
                </div>
            </div>
        </div>
    </div>
</main>' . output_footer();

$liveform->remove_form();
