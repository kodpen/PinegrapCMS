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
            <div class="row mb-3  flex-wrap">
                <div class="col-12 sm:col-12 text-center md:text-start">
                    <nav class="navigation" aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center md:justify-content-start "> 
                            <li class="breadcrumb-item"><a class="pg-link-secondary " href="<?=OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY?>/view_key_codes.php"><?=lang('All Key Codes')?></a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php if ($screen == 'create'): ?><?=lang('Create Key Code')?><?php else: ?><?=lang('Edit Key Code')?><?php endif ?></li>
                        </ol> 
                    </nav>
                    <h2 class="d-inline-block text-break header-content-for-add-page">
						[<?php if ($screen == 'create'): ?><?=lang('Key Code')?><?php else: ?><?=h($key_code['code'])?><?php endif ?>]
					</h2>
					<p class="form-text mt-0"><?php if ($screen == 'create'): ?><?=lang('Create one or more key codes (offer code alias) to allow redeemed offers to be tracked by customer segments.')?><?php else: ?><?=lang('Edit a key code (offer code alias) to allow redeemed offers to be tracked by customer segments.')?><?php endif ?></p>
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
                        <div class="card my-7">
                            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                <?=lang('Main Informations')?>
                            </div>
                            <div class="card-body">
                                <div class="row">
									<?php if ($screen == 'create'): ?>
                                    	<div class="col-12 md:col-4 my-3">
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
													'data-inputmask-placeholder'=>'0',
													'required' => 'required'))?>
                                               
                                            </div>
											<div class="text-end form-text"><?=lang('Increase Quantity to Create Multiple Key Codes')?></div>
                                    	</div>
									<?php endif ?>
                                    <div class="col-12 md:col-4 my-3 collapse show" id="code_row">
                                        <label for="code" class="form-label"><?=lang('Key Code')?></label>
                                        <?php // A new key code may be left blank (or is collapsed for several codes), so only edit asks for it. ?>
                                        <?=$liveform->output_field(array_merge(array(
											'type' => 'text',
											'id' => 'code',
											'name' => 'code',
											'class' => 'form-control add-header-content-updater',
											'maxlength' => '50'), $screen == 'edit' ? array('required' => 'required') : array()))?>
										<div class="text-end form-text"><?=lang('New Key Code for Redemption & Order Reporting')?></div>
                                    </div>
                                    <div class="col-12 md:col-4 my-3">
                                        <label for="offer_code" class="form-label"><?=lang('Offer Code')?></label>
                                        <?=$liveform->output_field(array(
											'type' => 'text',
											'id' => 'offer_code',
											'name' => 'offer_code',
											'class' => 'form-control',
											'maxlength' => '50',
											'required' => 'required'))?>
										<div class="text-end form-text"><?=lang('Alias of Existing Offer Code')?></div>
                                    </div>
								</div>
							</div>
						</div>
					</div>
                    <div class="col-12">
                        <div class="card my-7">
                            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                <?=lang('Availability')?>
                            </div>
                            <div class="card-body">
                                <div class="row">
									<div class="col-12 my-3">
                                        <div class="form-field">
										<?=$liveform->output_field(array(
											'type' => 'checkbox',
											'id' => 'enabled',
											'name' => 'enabled',
											'value' => '1',
											'class' => 'check'))?>
                                            <label class="" for="enabled"><?=lang('Enabled')?></label>
                                        </div>
                                    </div>
									<div class="col-12 md:col-6 lg:col-4 my-3">
                                         <label for="expiration_date" class="form-label"><?=lang('Expiration Date')?></label>
                                         <?=$liveform->output_field(array(
											'type' => 'text',
											'id' => 'expiration_date',
											'name' => 'expiration_date',
											'class' => 'form-control',
											'autocomplete'=>'off',
											'maxlength' => '10',
											'data-pg-date' => ''))?>
										<div class="text-end form-text"><?=lang('leave blank for no expiration')?></div>
										<?=get_date_picker_format() . pg_fields_script()?>
                                    </div>
                                    <div class="col-12 my-3">
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
                        <div class="card my-7">
                            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                <?=lang('Other Options')?>
                            </div>
                            <div class="card-body">
                                <div class="row">
									<div class="col-12 my-3">
                                        <div class="form-field">
										<?=$liveform->output_field(array(
											'type' => 'checkbox',
											'id' => 'single_use',
											'name' => 'single_use',
											'value' => '1',
											'class' => 'check'))?>
                                            <label class="" for="single_use"><?=lang('Single-Use')?></label>
											<div class="form-text"><?=lang('Prevent Code from Being Used in Multiple Orders')?></div>
											
                                        </div>
                                    </div>
                                    <fieldset class="col-12 my-3">
                                        <legend class="form-label fs-md"><?=lang('Report')?></legend>
                                        <div class="form-field">
											<?=$liveform->output_field(array(
												'type' => 'radio',
												'id' => 'report_key_code',
												'name' => 'report',
												'value' => 'key_code',
												'class' => 'radio'))?>
                                            <label class="" for="report_key_code"><?=lang('Key Code')?></label>
                                        </div>
                                        <div class="form-field">
											<?=$liveform->output_field(array(
												'type' => 'radio',
												'id' => 'report_offer_code',
												'name' => 'report',
												'value' => 'offer_code',
												'class' => 'radio'))?>
                                            <label class="" for="report_offer_code"><?=lang('Offer Code')?></label>
                                        </div>
										<div class="form-text"><?=lang('Code for Order Reporting & Exporting')?></div>
                                    </fieldset>
                                </div>
							</div>
						</div>
					</div>
				</div>
				<nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="<?=h(lang('data edit buttons'))?>">
                    <div class="container">
                        <div class=" btn-group flex-wrap justify-content-center">
                            <button type="submit" id="save_button" name="submit_button" value="<?php if ($screen == 'create'): ?>Create<?php else: ?>Save<?php endif ?>" class="btn my-1 btn-solid theme-success "><span class="material-icons me-3" aria-hidden="true"><?php if ($screen == 'create'): ?>add<?php else: ?>save<?php endif ?></span><span class="pg-btn-label" ><?php if ($screen == 'create'): ?><?=lang(array('string'=>'Create') )?><?php else: ?><?=lang(array('string'=>'Save') )?><?php endif ?></span></button>
                            <?php if ($screen == 'edit'): ?>
								<button type="submit" name="delete" value="Delete" formnovalidate class="btn my-1  btn-solid theme-danger " data-confirm-content="<?=lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('key code'))))?>"><span class="material-icons me-3" aria-hidden="true">delete</span><span class="pg-btn-label" ><?=lang(array('string'=>'Delete') )?></span></button>
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