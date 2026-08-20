<main id="content" class="container">
	<div class="row">
		<div class="col-12">
			<?=$form->get_messages()?>
			<div class="row mb-2  flex-wrap">
				<div class="col-12 col-sm-12 text-center text-md-start">
					<nav class="navigation" aria-label="breadcrumb">
						<ol class="breadcrumb justify-content-center justify-content-md-start ">
							<?php if(isset($_GET['send_to']) && ($_GET['send_to'] ?? '') != ''): ?>
								<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="<?=lang('Loading')?>" href="<?=OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY?>/explorer.php"><?=lang('Explorer')?></a></li>
								<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="<?=lang('Loading')?>" href="<?=OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY?>/edit_folder.php?id=<?=h(escape_javascript($_GET['id']))?>&send_to=<?=($_GET['send_to'] ?? '')?>"><?=lang('Edit Folder')?></a></li>
							<?php else:?>
								<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="<?=lang('Loading')?>" href="<?=OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY?>/view_folders.php"><?=lang('All Folders')?></a></li>
								<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="<?=lang('Loading')?>" href="<?=OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY?>/edit_folder.php?id=<?=h(escape_javascript($_GET['id']))?>"><?=lang('Edit Folder')?></a></li>
							<?php endif;?>
							<li class="breadcrumb-item active" aria-current="page"><?=lang('Duplicate Folder')?></li>
						</ol>
					</nav>
					<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="<?=lang('Press Duplicate to duplicate this folder and pages directly inside of it.  Optionally, you may configure content that you want the system to find and replace in the new duplicated items.')?>" title="<?=lang('Duplicate Folder')?>">[<?=h($folder['name'])?>]</h2>
				</div>
			</div>
			<form method="post">
				<?=get_token_field()?>
				<div class="row">
					<div class="col-12">
						<div class="card my-4">
							<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
								<?=lang('Duplicate Options')?>
							</div>
							<div class="card-body">
								<div class="row">
									<div class="col-12 my-1">
										<div class="form-check form-switch">
											<input value="1" name="find_and_replace_check" id="find_and_replace_check" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#find_and_replace_row">
											<label class="form-check-label" for="find_and_replace_check"><?=lang('Find & Replace')?></label>
										</div>
										<div class="collapse popover  fade bs-popover-bottom p-0 mb-2  pe-3" id="find_and_replace_row">
											<div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(49px, 0px);"></div>
											<div class="popover-body">
												<div class="row">
													<div class="find_replace col-12 my-1">
														<?php for ($number = 1; $number <= 10; $number++): ?>
														<div class="find_replace_row row position-relative border bg-light p-2 my-3 rounded" >
															<div class="col-12 col-md-6 my-1 ">
																<span class="position-absolute top-0 start-50 badge rounded-pill translate-middle bg-light text-dark border-top fw-light"><?=$number?></span>
																<label for="find_<?=$number?>" class="form-label"><?=lang('Find')?></label>
																<input placeholder="<?=lang('Find')?>" type="text" class="form-control" id="find_<?=$number?>" name="find_<?=$number?>" >
															</div>
															<div class="col-12 col-md-6 my-1">
																<label for="replace_<?=$number?>" class="form-label"><?=lang('Replace')?></label>
																<input placeholder="<?=lang('Replace')?>" type="text" class="form-control" id="replace_<?=$number?>" name="replace_<?=$number?>">
															</div>
														</div>
														<?php endfor ?>
														<input type="hidden" name="find_replace">
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
						<div class="container">
							<div class=" btn-group flex-wrap justify-content-center">
								<button type="submit" id="submit_duplicate" name="submit_duplicate" value="Duplicate" class="btn my-1  btn-success " data-loading-content="<?=lang(array('string'=>'Duplicating') )?>"><span class="material-icons me-2">control_point_duplicate</span><span class="btn-text" ><?=lang(array('string'=>'Duplicate') )?></span></button>
							</div>
						</div>
					</nav>
				</div>
			</form>
		</div>
	</div>
</main>