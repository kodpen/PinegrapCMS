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
	<?=$form->get_messages()?>
	<form method="post">
		<?=get_token_field()?>
		<div class="row">
			<div class="col-12 lg:col-8 2xl:col-9 order-1">
				<div class="card mb-7">
					<h2 class="card-header bg-reset border-0 pg-title-section">
						<?=lang('Duplicate Options')?>
					</h2>
					<div class="card-body pt-0">
						<div class="form-field my-4">
							<div class="switch"><input value="1" name="find_and_replace_check" id="find_and_replace_check" class="collapse-switcher" type="checkbox" role="switch" data-bs-target="#find_and_replace_row"></div>
							<label class="" for="find_and_replace_check"><?=lang('Find & Replace')?></label>
						</div>
						<div class="collapse" id="find_and_replace_row">
							<div class="find_replace">
								<?php for ($number = 1; $number <= 10; $number++): ?>
								<div class="find_replace_row row position-relative border bg-1 p-3 my-5 rounded-4">
									<span class="position-absolute top-0 start-50 badge rounded-pill translate-middle badge-subtle theme-secondary border-top fw-light"><?=$number?></span>
									<div class="col-12 md:col-6 my-1">
										<label for="find_<?=$number?>" class="form-label"><?=lang('Find')?></label>
										<input placeholder="<?=lang('Find')?>" type="text" class="form-control" id="find_<?=$number?>" name="find_<?=$number?>">
									</div>
									<div class="col-12 md:col-6 my-1">
										<label for="replace_<?=$number?>" class="form-label"><?=lang('Replace')?></label>
										<input placeholder="<?=lang('Replace')?>" type="text" class="form-control" id="replace_<?=$number?>" name="replace_<?=$number?>">
									</div>
								</div>
								<?php endfor ?>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-12 lg:col-4 2xl:col-3 order-2">
				<!-- 3.3rem: the fixed control panel header covers the top of the window. -->
				<div class="position-sticky" style="top:3.3rem;">
					<div class="card mb-7">
						<h2 class="card-header bg-reset border-0 pg-title-section">
							<?=lang('Summary')?>
						</h2>
						<div class="card-body pt-0">
							<dl class="mb-0">
								<div class="d-flex justify-content-between gap-3 py-2 border-bottom">
									<dt class="fw-normal"><?=h(lang('Folder'))?></dt>
									<dd class="mb-0 fw-bold text-end text-break"><a href="view_folders.php?folder=<?=(int) $folder['id']?>"><?=h($folder['name'])?></a></dd>
								</div>
								<?php if ($parent_name !== ''): ?>
								<div class="d-flex justify-content-between gap-3 py-2 border-bottom">
									<dt class="fw-normal"><?=h(lang('Parent Folder'))?></dt>
									<dd class="mb-0 fw-bold text-end text-break"><a href="view_folders.php?folder=<?=(int) $folder['parent']?>"><?=h($parent_name)?></a></dd>
								</div>
								<?php endif ?>
								<div class="d-flex justify-content-between gap-3 py-2">
									<dt class="fw-normal"><?=h(lang('Pages'))?></dt>
									<dd class="mb-0 fw-bold text-end"><?=(int) $page_count?></dd>
								</div>
							</dl>
							<p class="form-text mb-0 mt-3"><?=lang('Duplicate creates a new folder with a copy of each page directly inside this one. Files and sub-folders are not copied; use Duplicate in the File Manager to copy the whole folder. With Find & Replace on, the words in each row are replaced in the new folder name and in the copied pages.')?></p>
						</div>
					</div>
				</div>
			</div>
			<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
				<nav class="buttons navigation text-center mb-7" aria-label="<?= h(lang('data edit buttons')) ?>">
					<div class="container">
						<div class="btn-group flex-wrap justify-content-center">
							<button type="submit" id="submit_duplicate" name="submit_duplicate" value="Duplicate" class="btn my-1 btn-solid theme-success"><i class="bi bi-copy me-3" aria-hidden="true"></i><span class="pg-btn-label"><?=lang(array('string'=>'Duplicate') )?></span></button>
						</div>
					</div>
				</nav>
			</div>
		</div>
	</form>
</main>