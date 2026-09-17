<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// This file is a view partial. It is required from a page that has already run
// init.php and it depends on the constants and variables that bootstrap sets up.
// Requested directly over the web it runs with none of them and dies on the first
// constant it touches -- which is exactly how it turned up in the error log.
if (!defined('PG_INIT_LOADED')) {
    http_response_code(403);
    exit;
}
?>
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
			<?=$liveform->get_messages()?>
            <div class="row mb-2  flex-wrap">
                <div class="col-12 col-sm-12 text-center text-md-start">
                    <nav class="navigation" aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-md-start "> 
                            <li class="breadcrumb-item"><a class="link-secondary " data-loading-content="<?=lang('Loading')?>" href="<?=OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY?>/view_key_codes.php"><?=lang('All Key Codes')?></a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php if ($screen == 'create'): ?><?=lang('Create Key Code')?><?php else: ?><?=lang('Edit Key Code')?><?php endif ?></li>
                        </ol> 
                    </nav>
                    <h2 
						class="d-inline-block text-break header-content-for-add-page" 
						data-bs-content="<?php if ($screen == 'create'): ?><?=lang('Create one or more key codes (offer code alias) to allow redeemed offers to be tracked by customer segments.')?><?php else: ?><?=lang('Edit a key code (offer code alias) to allow redeemed offers to be tracked by customer segments.')?><?php endif ?>" 
						title="<?php if ($screen == 'create'): ?><?=lang('Create Key Code')?><?php else: ?><?=lang('Edit Key Code')?><?php endif ?>">
						[<?php if ($screen == 'create'): ?><?=lang('Key Code')?><?php else: ?><?=h($key_code['code'])?><?php endif ?>]
					</h2>
                </div>
            </div>
            <form method="post">
				<?=get_token_field()?>
				<?php if ($screen == 'create'): ?>
				<?=$liveform->field(array(
					'type' => 'hidden',

					'name' => 'limit'))?>
				<?php endif ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card my-4">
                            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                <?=lang('Main Informations')?>
                            </div>
                            <div class="card-body">
                                <div class="row">
									<?php if ($screen == 'create'): ?>
                                    	<div class="col-12 col-md-4 my-2">
											<label for="quantity" class="form-label "><?=lang('Quantity')?></label>
                                            <div class="input-group number-controls">
                                                
                                                <?=$liveform->output_field(array(
													'type' => 'number',
													'id' => 'quantity',
													'name' => 'quantity',
													'value' => '1',
													'min' => '1',
													'max' => $quantity_max,
													'class' => 'form-control text-center ',
													'maxlength'=>'4',
													'onchange'=>'update_key_code();',
													'inputmode'=>'numeric',
													'data-inputmask-alias'=>'decimal',
													'data-inputmask-placeholder'=>'0'))?>
                                               
                                            </div>
											<div class="text-end form-text"><?=lang('Increase Quantity to Create Multiple Key Codes')?></div>
                                    	</div>
									<?php endif ?>
                                    <div class="col-12 col-md-4 my-2 collapse show" id="code_row">
                                        <label for="code" class="form-label"><?=lang('Key Code')?></label>
                                        <?=$liveform->output_field(array(
											'type' => 'text',
											'id' => 'code',
											'name' => 'code',
											'class' => 'form-control add-header-content-updater',
											'maxlength' => '50'))?>
										<div class="invalid-feedback"><?=lang('Required Area')?></div>
										<div class="text-end form-text"><?=lang('New Key Code for Redemption & Order Reporting')?></div>
                                    </div>
                                    <div class="col-12 col-md-4 my-2">
                                        <label for="offer_code" class="form-label"><?=lang('Offer Code')?></label>
                                        <?=$liveform->output_field(array(
											'type' => 'text',
											'id' => 'offer_code',
											'name' => 'offer_code',
											'class' => 'form-control',
											'maxlength' => '50'))?>
										<div class="invalid-feedback"><?=lang('Required Area')?></div>
										<div class="text-end form-text"><?=lang('Alias of Existing Offer Code')?></div>
                                    </div>
								</div>
							</div>
						</div>
					</div>
                    <div class="col-12">
                        <div class="card my-4">
                            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                <?=lang('Availability')?>
                            </div>
                            <div class="card-body">
                                <div class="row">
									<div class="col-12 my-2">
                                        <div class="form-check form-switch">
										<?=$liveform->output_field(array(
											'type' => 'checkbox',
											'id' => 'enabled',
											'name' => 'enabled',
											'value' => '1',
											'class' => 'form-check-input'))?>
                                            <label class="form-check-label" for="enabled"><?=lang('Enabled')?></label>
                                        </div>
                                    </div>
									<div class="col-12 col-md-6 col-lg-4 my-2">
                                         <label for="expiration_date" class="form-label"><?=lang('Expiration Date')?></label>
                                         <?=$liveform->output_field(array(
											'type' => 'text',
											'id' => 'expiration_date',
											'name' => 'expiration_date',
											'class' => 'form-control',
											'autocomplete'=>'off',
											'maxlength' => '10'))?>
										<div class="text-end form-text"><?=lang('leave blank for no expiration')?></div>
										<?=get_date_picker_format()?>
										<script>
											$('#expiration_date').datepicker(datetimepicker_options);
										</script>
                                    </div>
                                    <div class="col-12 my-2">
                                        <label for="notes" class="form-label"><?=lang('Notes')?></label>
										<?=$liveform->output_field(array(
											'type' => 'textarea',
											'name' => 'notes',
											'id' => 'notes',
											'class' => 'form-control'))?>
                                    </div>
								</div>
							</div>
						</div>
					</div>
                    <div class="col-12">
                        <div class="card my-4">
                            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                <?=lang('Other Options')?>
                            </div>
                            <div class="card-body">
                                <div class="row">
									<div class="col-12 my-2">
                                        <div class="form-check form-switch">
										<?=$liveform->output_field(array(
											'type' => 'checkbox',
											'id' => 'single_use',
											'name' => 'single_use',
											'value' => '1',
											'class' => 'form-check-input'))?>
                                            <label class="form-check-label" for="single_use"><?=lang('Single-Use')?></label>
											<div class="form-text"><?=lang('Prevent Code from Being Used in Multiple Orders')?></div>
											
                                        </div>
                                    </div>
                                    <div class="col-12 my-2">
                                        <label class="form-label" for=""><?=lang('Report')?></label>
                                        <div class="form-check">
											<?=$liveform->output_field(array(
												'type' => 'radio',
												'id' => 'report_key_code',
												'name' => 'report',
												'value' => 'key_code',
												'class' => 'form-check-input'))?>
                                            <label class="form-check-label" for="report_key_code"><?=lang('Key Code')?></label>
                                        </div>
                                        <div class="form-check">
											<?=$liveform->output_field(array(
												'type' => 'radio',
												'id' => 'report_offer_code',
												'name' => 'report',
												'value' => 'offer_code',
												'class' => 'form-check-input'))?>
                                            <label class="form-check-label" for="report_offer_code"><?=lang('Offer Code')?></label>
                                        </div>
										<div class="form-text"><?=lang('Code for Order Reporting & Exporting')?></div>
                                    </div>
                                </div>
							</div>
						</div>
					</div>
				</div>
				<nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                    <div class="container">
                        <div class=" btn-group flex-wrap justify-content-center">
                            <button type="submit" id="save_button" name="submit_button" value="<?php if ($screen == 'create'): ?>Create<?php else: ?>Save<?php endif ?>" class="btn my-1 btn-success " data-loading-content="<?=lang(array('string'=>'Saving') )?>"><span class="material-icons me-2"><?php if ($screen == 'create'): ?>add<?php else: ?>save<?php endif ?></span><span class="btn-text" ><?php if ($screen == 'create'): ?><?=lang(array('string'=>'Create') )?><?php else: ?><?=lang(array('string'=>'Save') )?><?php endif ?></span></button>
                            <?php if ($screen == 'edit'): ?>
								<button type="submit" name="delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="<?=lang(array('string'=>'Deleting') )?>" data-confirm-content="<?=lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('key code'))))?>"><span class="material-icons me-2">delete</span><span class="btn-text" ><?=lang(array('string'=>'Delete') )?></span></button>
							<?php endif ?>
						</div>
                    </div>
                </nav>
				<?php if ($screen == 'create'): ?>
				<script>
					function update_key_code() {

					    if ($('#quantity').val() == 1) {

					        $('#code_row').addClass('show');
					    } else {
					        $('#code_row').removeClass('show');
					    }
					}

				</script>
				<?php endif ?>
			</form>
        </div>
    </div>
</main>