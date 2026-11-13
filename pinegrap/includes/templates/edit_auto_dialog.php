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
            <div class="mb-3 text-center md:text-start">
                    <nav class="navigation" aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center md:justify-content-start "> 
                            <li class="breadcrumb-item"><a class="pg-link-secondary " href="<?=OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY?>/view_auto_dialogs.php"><?=lang('All Auto Dialogs')?></a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php if ($screen == 'create'): ?><?=lang('Create Auto Dialog')?><?php else: ?><?=lang('Edit Auto Dialog')?><?php endif ?></li>
                        </ol> 
                    </nav>
                    <h2 class="d-inline-block text-break header-content-for-add-page">
						[<?php if ($screen == 'create'): ?><?=lang('Auto Dialog Name')?><?php else: ?><?=h($auto_dialog['name'])?><?php endif ?>]
					</h2>
					<p class="form-text mt-0"><?php if ($screen == 'create'): ?><?=lang('Create a new auto dialog that can automatically popup for visitors. You may preview the auto dialog on the next screen and then enable it for all visitors when desired.')?><?php else: ?><?=lang('Edit an auto dialog that can automatically popup for visitors.')?><?php endif ?></p>
            </div>
            <?php if ($screen == 'edit'): ?>
                <?=pg_screen_toolbar(array(
                    'secondary' => '<a class="btn btn-sm btn-text theme-secondary" href="' . h($preview_url) . '" target="_blank"><i class="bi bi-link me-1" aria-hidden="true"></i>' . lang('Preview') . '</a>',
                ))?>
            <?php endif ?>
            <form method="post">
                <?=get_token_field()?>
                <div class="row">
                    <div class="col-12 lg:col-8 2xl:col-9 order-1">
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 pg-title-section">
                                <?=lang('Dialog Options')?>
                            </h2>
                            <div class="card-body pt-0">
                                <div class="row">
                                    <div class="col-12 md:col-5 my-3">
                                        <label for="name" class="form-label"><?=lang('Name')?></label>
                                        <?=$liveform->output_field(array(
                                            'type' => 'text',
                                            'id' => 'name',
                                            'name' => 'name',
                                            'placeholder'=>lang('Auto Dialog Name'),
                                            'class' => 'form-control add-header-content-updater',
                                            'maxlength' => '100',
                                            'required' => 'required'))?>
                                    </div>
                                    <div class="col-12 md:col-7 my-3">
                                        <label for="url" class="form-label"><?=lang('URL')?></label>
                                        <?=$liveform->output_field(array(
                                            'type' => 'text',
                                            'id' => 'url',
                                            'name' => 'url',
                                            'class' => 'form-control',
                                            'maxlength' => '255',
                                            'required' => 'required'))?>
                                    </div>
                                    <div class="col-12 my-3">
                                        <label for="page" class="form-label"><?=lang('Only on Page')?></label>
                                        <?=$liveform->output_field(array(
                                            'type' => 'text',
                                            'id' => 'page',
                                            'name' => 'page',
                                            'class' => 'form-control',
                                            'maxlength' => '100'))?>
                                        <div class="form-text text-end"><?=lang('leave blank to open on any page')?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 pg-title-section">
                                <?=lang('Display')?>
                            </h2>
                            <div class="card-body pt-0">
                                <?php
                                // The four number boxes take no input mask: the integer mask
                                // dropped a typed "." or "-" before the save saw it, so 1.5
                                // hours was stored as 15 and -5 seconds as 5. The save reads
                                // thousands groups and refuses a sign, a decimal part or
                                // letters with its message (pg_auto_dialog_form_whole_number());
                                // maxlength leaves room for the separators (9.999, 2.147.483).
                                ?>
                                <div class="row">
                                        <div class="col-12 sm:col-6 2xl:col-3 my-3">
                                            <label for="width" class="form-label"><?=lang('Width')?></label>
                                            <div class="input-group">
                                                <?=$liveform->output_field(array(
                                                    'type' => 'text',
                                                    'id' => 'width',
                                                    'name' => 'width',
                                                    'class' => 'form-control text-end',
                                                    'maxlength'=>'5',
                                                    'inputmode'=>'numeric'))?>
                                                <label for="width" class="input-group-text"><?=lang('pixels')?></label>
                                            </div>
                                            <div class="form-text text-end"><?=lang('leave blank for auto')?></div>
                                        </div>
                                        <div class="col-12 sm:col-6 2xl:col-3 my-3">
                                            <label for="height" class="form-label"><?=lang('Height')?></label>
                                            <div class="input-group">
                                                <?=$liveform->output_field(array(
                                                    'type' => 'text',
                                                    'id' => 'height',
                                                    'name' => 'height',
                                                    'class' => 'form-control text-end',
                                                    'maxlength'=>'5',
                                                    'inputmode'=>'numeric'))?>
                                                <label for="height" class="input-group-text"><?=lang('pixels')?></label>
                                            </div>
                                            <div class="form-text text-end"><?=lang('leave blank for auto')?></div>
                                        </div>
                                        <div class="col-12 sm:col-6 2xl:col-3 my-3">
                                            <label for="delay" class="form-label"><?=lang('Delay')?></label>
                                            <div class="input-group">
                                                <?=$liveform->output_field(array(
                                                    'type' => 'text',
                                                    'id' => 'delay',
                                                    'name' => 'delay',
                                                    'class' => 'form-control text-end',
                                                    'maxlength'=>'9',
                                                    'inputmode'=>'numeric'))?>
                                                <label for="delay" class="input-group-text"><?=lang('seconds')?></label>
                                            </div>
                                            <div class="form-text text-end"><?=lang('leave blank for instant')?></div>
                                        </div>
                                        <div class="col-12 sm:col-6 2xl:col-3 my-3">
                                            <label for="frequency" class="form-label"><?=lang('Frequency')?></label>
                                            <div class="input-group">
                                                <?=$liveform->output_field(array(
                                                    'type' => 'text',
                                                    'id' => 'frequency',
                                                    'name' => 'frequency',
                                                    'class' => 'form-control text-end',
                                                    'maxlength'=>'7',
                                                    'inputmode'=>'numeric'))?>
                                                <label for="frequency" class="input-group-text"><?=lang('hour(s)')?></label>
                                            </div>
                                            <div class="form-text text-end"><?=lang('leave blank for one-time')?></div>
                                        </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 lg:col-4 2xl:col-3 order-2">
                        <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                        <div class="position-sticky" style="top:3.3rem;">
                            <?php if ($screen == 'edit'): ?>
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        <?=lang('Status')?>
                                    </h2>
                                    <div class="card-body pt-0">
                                        <div class="form-field">
                                            <?=$liveform->output_field(array(
                                                'type' => 'checkbox',
                                                'id' => 'enabled',
                                                'name' => 'enabled',
                                                'value' => '1',
                                                'class' => 'check'))?>
                                            <label class="" for="enabled"><?=lang('Enable Auto Dialog')?></label>
                                        </div>
                                    </div>
                                </div>
                            <?php endif ?>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    <?=lang('Summary')?>
                                </h2>
                                <div class="card-body pt-0">
                                    <dl class="mb-0">
                                        <?php foreach (array(
                                            array('page', lang('Page'), lang('All pages'), ''),
                                            array('width', lang('Width'), lang('Auto'), lang('pixels')),
                                            array('height', lang('Height'), lang('Auto'), lang('pixels')),
                                            array('delay', lang('Delay'), lang('Immediately'), lang('seconds')),
                                            array('frequency', lang('Frequency'), lang('Once'), lang('hour(s)')),
                                        ) as $summary_row): ?>
                                            <?php $summary_value = trim((string) $liveform->get_field_value($summary_row[0])); ?>
                                            <div class="d-flex justify-content-between gap-3 py-2<?php if ($summary_row[0] != 'frequency' || $screen == 'edit'): ?> border-bottom<?php endif ?>">
                                                <dt class="fw-normal"><?=h($summary_row[1])?></dt>
                                                <dd class="mb-0 fw-bold text-end text-break" id="auto_dialog_summary_<?=$summary_row[0]?>" data-blank="<?=h($summary_row[2])?>" data-unit="<?=h($summary_row[3])?>"><?=h(($summary_value === '') ? $summary_row[2] : trim($summary_value . ' ' . $summary_row[3]))?></dd>
                                            </div>
                                        <?php endforeach ?>
                                        <?php if ($screen == 'edit'): ?>
                                            <div class="d-flex justify-content-between gap-3 py-2">
                                                <dt class="fw-normal"><?=lang('Last Modified')?></dt>
                                                <dd class="mb-0 fw-bold text-end text-break"><?php if ((int) $auto_dialog['last_modified_timestamp'] > 0): ?><?=get_relative_time(array('timestamp' => (int) $auto_dialog['last_modified_timestamp']))?><?php if ((string) $auto_dialog['user_username'] !== ''): ?> <?=h($auto_dialog['user_username'])?><?php endif ?><?php else: ?>-<?php endif ?></dd>
                                            </div>
                                        <?php endif ?>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        // The Summary follows the boxes as they are typed; the input mask
                        // may set a value without an input event, so change is read too.
                        ["page", "width", "height", "delay", "frequency"].forEach(function (name) {
                            var box = document.getElementById(name);
                            var cell = document.getElementById("auto_dialog_summary_" + name);
                            var show = function () {
                                var value = box.value.trim();
                                cell.textContent = (value === "") ? cell.getAttribute("data-blank") : (value + " " + cell.getAttribute("data-unit")).trim();
                            };
                            box.addEventListener("input", show);
                            box.addEventListener("change", show);
                        });
                    </script>
                    <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                        <nav class="buttons navigation text-center mb-7" aria-label="<?= h(lang('data edit buttons')) ?>">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="save_button" data-pg-save-shortcut name="submit_button" value="<?php if ($screen == 'create'): ?>Create<?php else: ?>Save<?php endif ?>" class="btn my-1 btn-solid theme-success "><span class="material-icons me-3"><?php if ($screen == 'create'): ?>add<?php else: ?>save<?php endif ?></span><span class="pg-btn-label" ><?php if ($screen == 'create'): ?><?=lang(array('string'=>'Create') )?><?php else: ?><?=lang(array('string'=>'Save') )?><?php endif ?></span></button>
                                    <?php if ($screen == 'edit'): ?>
                                        <button type="submit" name="delete" value="Delete" formnovalidate class="btn my-1  btn-solid theme-danger " data-confirm-content="<?=lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('auto dialog'))))?>"><span class="material-icons me-3">delete</span><span class="pg-btn-label" ><?=lang(array('string'=>'Delete') )?></span></button>
                                    <?php endif ?>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>