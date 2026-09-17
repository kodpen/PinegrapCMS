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
                            <li class="breadcrumb-item"><a class="link-secondary " data-loading-content="<?=lang('Loading')?>" href="<?=OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY?>/view_auto_dialogs.php"><?=lang('All Auto Dialogs')?></a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php if ($screen == 'create'): ?><?=lang('Create Auto Dialog')?><?php else: ?><?=lang('Edit Auto Dialog')?><?php endif ?></li>
                        </ol> 
                    </nav>
                    <h2 
						class="d-inline-block text-break header-content-for-add-page" 
						data-bs-content="<?php if ($screen == 'create'): ?><?=lang('Create a new auto dialog that can automatically popup for visitors. You may preview the auto dialog on the next screen and then enable it for all visitors when desired.')?><?php else: ?><?=lang('Edit an auto dialog that can automatically popup for visitors.')?><?php endif ?>" 
						title="<?php if ($screen == 'create'): ?><?=lang('Create Auto Dialog')?><?php else: ?><?=lang('Edit Auto Dialog')?><?php endif ?>">
						[<?php if ($screen == 'create'): ?><?=lang('Auto Dialog Name')?><?php else: ?><?=h($auto_dialog['name'])?><?php endif ?>]
					</h2>
					<?php if ($screen == 'edit'): ?>
						<nav id="button_bar" class="navigation " aria-label="Button Bar">
                			<div class=" btn-group btn-group-sm flex-wrap">
								<a class="btn btn-link link-secondary py-0 mb-2 bi bi-link bi-me-2" href="<?=h($preview_url)?>" target="_blank"><?=lang('Preview')?></a>
							</div>
						</nav>
					<?php endif ?>
                </div>
            </div>
            <form method="post">
				<?=get_token_field()?>
                <div class="row">
                    <div class="col-12">
                        <div class="card my-4">
                            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                <?=lang('Dialog Options')?>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 col-sm-6 col-md-4 my-2">
                                        <label for="name" class="form-label"><?=lang('Name')?></label>
										<?=$liveform->output_field(array(
											'type' => 'text',
											'id' => 'name',
											'name' => 'name',
											'placeholder'=>lang('Auto Dialog Name'),
											'class' => 'form-control add-header-content-updater',
											'maxlength' => '50'))?>
										<div class="invalid-feedback"><?=lang('Required Area')?></div>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-8 my-2">
                                        <label for="url" class="form-label"><?=lang('URL')?></label>
										<?=$liveform->output_field(array(
											'type' => 'text',
											'id' => 'url',
											'name' => 'url',
											'class' => 'form-control',
											'maxlength' => '255'))?>
										<div class="invalid-feedback"><?=lang('Required Area')?></div>
                                    </div>
									<?php if ($screen == 'edit'): ?>
										<div class="col-12 my-3">
											<div class="form-check form-switch">
											<?=$liveform->output_field(array(
												'type' => 'checkbox',
												'id' => 'enabled',
												'name' => 'enabled',
												'value' => '1',
												'class' => 'form-check-input'))?>
                    						    <label class="form-check-label" for="enabled"><?=lang('Enable Auto Dialog')?></label>
                    						</div>
                    					</div>
									<?php endif ?>

									<div class="col-12 col-md-6 col-lg-6 col-xl-3 my-2">
                            		    <label for="width" class="form-label"><?=lang('Width')?></label>
                            		    <div class="input-group">
										<?=$liveform->output_field(array(
											'type' => 'text',
											'id' => 'width',
											'name' => 'width',
											'class' => 'form-control text-end',
											'maxlength'=>'4',
											'inputmode'=>'numeric',
											'data-inputmask-alias'=>'decimal'))?>
                            		        <label for="width"  class="input-group-text"><?=lang('pixels')?></label>
                            		    </div>
                                    	<div class="form-text text-end"><?=lang('leave blank for auto')?></div>
                            		</div>
									<div class="col-12 col-md-6 col-lg-6 col-xl-3 my-2">
                            		    <label for="height" class="form-label"><?=lang('Height')?></label>
                            		    <div class="input-group">
										<?=$liveform->output_field(array(
											'type' => 'text',
											'id' => 'height',
											'name' => 'height',
											'class' => 'form-control text-end',
											'maxlength'=>'4',
											'inputmode'=>'numeric',
											'data-inputmask-alias'=>'decimal'))?>
                            		        <label for="height"  class="input-group-text"><?=lang('pixels')?></label>
                            		    </div>
                                    	<div class="form-text text-end"><?=lang('leave blank for auto')?></div>
                            		</div>
									<div class="col-12 col-md-6 col-lg-6 col-xl-3 my-2">
                            		    <label for="delay" class="form-label"><?=lang('Delay')?></label>
                            		    <div class="input-group">
										<?=$liveform->output_field(array(
											'type' => 'text',
											'id' => 'delay',
											'name' => 'delay',
											'class' => 'form-control text-end',
											'inputmode'=>'numeric',
											'data-inputmask-alias'=>'decimal'))?>
                            		        <label for="delay"  class="input-group-text"><?=lang('seconds')?></label>
                            		    </div>
                                    	<div class="form-text text-end"><?=lang('leave blank for instant')?></div>
                            		</div>
									<div class="col-12 col-md-6 col-lg-6 col-xl-3 my-2">
                            		    <label for="frequency" class="form-label"><?=lang('Frequency')?></label>
                            		    <div class="input-group">
										<?=$liveform->output_field(array(
											'type' => 'text',
											'id' => 'frequency',
											'name' => 'frequency',
											'class' => 'form-control text-end',
											'inputmode'=>'numeric',
											'data-inputmask-alias'=>'decimal'))?>
                            		        <label for="frequency"  class="input-group-text"><?=lang('hour(s)')?></label>
                            		    </div>
                                    	<div class="form-text text-end"><?=lang('leave blank for one-time')?></div>
                            		</div>
                                    <div class="col-12 col-sm-6 col-md-8 my-2">
                                        <label for="page" class="form-label"><?=lang('Only on Page')?></label>
										<?=$liveform->output_field(array(
											'type' => 'text',
											'id' => 'page',
											'name' => 'page',
											'class' => 'form-control',
											'maxlength' => '100'))?>
										<div class="invalid-feedback"><?=lang('Required Area')?></div>
										<div class="form-text text-end"><?=lang('leave blank to open on any page')?></div>
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
								<button type="submit" name="delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="<?=lang(array('string'=>'Deleting') )?>" data-confirm-content="<?=lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('auto dialog'))))?>"><span class="material-icons me-2">delete</span><span class="btn-text" ><?=lang(array('string'=>'Delete') )?></span></button>
							<?php endif ?>
						</div>
                    </div>
                </nav>
			</form>
        </div>
    </div>
</main>