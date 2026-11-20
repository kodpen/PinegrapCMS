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
                                    <div class="col-12 my-3">
                                        <label for="name" class="form-label"><?=lang('Name')?></label>
                                        <?=$liveform->output_field(array(
                                            'type' => 'text',
                                            'id' => 'name',
                                            'name' => 'name',
                                            'placeholder'=>lang('Auto Dialog Name'),
                                            'class' => 'form-control',
                                            'maxlength' => '100',
                                            'required' => 'required'))?>
                                    </div>
                                    <?php
                                    // What it shows: an address or a designed pop-up (a shared
                                    // component). Asked only with the content columns and a visual
                                    // design; otherwise the screen keeps the address kind.
                                    $content_asked = pg_auto_dialog_form_content_asked();
                                    $content_kind = pg_auto_dialog_content(pg_auto_dialog_form_content_sent($liveform));
                                    $shows_component = $content_asked && ($content_kind['content'] === 'component');
                                    ?>
                                    <?php if ($content_asked): ?>
                                        <?php
                                        require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');
                                        $designer_links = pg_auto_dialog_form_designer_links($content_kind['component_id'], array_keys(pg_visual_design_style_ids()), pg_designer_is_full($user));
                                        ?>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="content" class="form-label"><?=lang('What does it show?')?></label>
                                            <?=$liveform->output_field(array(
                                                'type' => 'select',
                                                'id' => 'content',
                                                'name' => 'content',
                                                'class' => 'form-control',
                                                'options' => pg_auto_dialog_form_content_kinds()))?>
                                        </div>
                                        <div class="col-12 md:col-6 my-3<?php if (!$shows_component): ?> d-none<?php endif ?>" data-pg-auto-dialog-shows="component">
                                            <label for="component_id" class="form-label"><?=lang('Shared Component')?></label>
                                            <?=$liveform->output_field(array(
                                                'type' => 'select',
                                                'id' => 'component_id',
                                                'name' => 'component_id',
                                                'class' => 'form-control',
                                                'options' => pg_auto_dialog_form_component_options(pg_auto_dialog_component_rows())))?>
                                            <div class="form-text d-flex flex-wrap justify-content-end gap-3">
                                                <a id="auto_dialog_component_open" href="<?=h($designer_links['open'])?>" target="_blank" data-href="edit_system_style.php?component="<?php if ($designer_links['open'] === ''): ?> class="d-none"<?php endif ?>><?=lang('Open in the visual designer')?></a>
                                                <?php if ($designer_links['new'] !== ''): ?>
                                                    <a href="<?=h($designer_links['new'])?>" target="_blank"><?=lang('Design a new pop-up')?></a>
                                                <?php endif ?>
                                            </div>
                                        </div>
                                    <?php endif ?>
                                    <div class="col-12 my-3<?php if ($shows_component): ?> d-none<?php endif ?>" data-pg-auto-dialog-shows="url">
                                        <label for="url" class="form-label"><?=lang('URL')?></label>
                                        <?php
                                        $url_field = array(
                                            'type' => 'text',
                                            'id' => 'url',
                                            'name' => 'url',
                                            'class' => 'form-control',
                                            'maxlength' => '255');

                                        if (!$shows_component) {
                                            $url_field['required'] = 'required';
                                        }
                                        ?>
                                        <?=$liveform->output_field($url_field)?>
                                    </div>
                                    <div class="col-12 md:col-6 my-3">
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
                                        <div class="col-12 md:col-3 my-3">
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
                                        <div class="col-12 md:col-3 my-3">
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
                                        <div class="col-12 md:col-3 my-3">
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
                                        <div class="col-12 md:col-3 my-3">
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
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 pg-title-section">
                                <?=lang('Opening')?>
                            </h2>
                            <div class="card-body pt-0">
                                <?php $open_fields = pg_auto_dialog_form_open_fields(); ?>
                                <div class="row">
                                    <?php foreach ($open_fields as $open_field => $open_rule): ?>
                                        <?php // A select takes 6 columns, the scroll share a count's 3 beside Opens. ?>
                                        <div class="col-12 <?=($open_field === 'open_scroll') ? 'md:col-3' : 'md:col-6'?> my-3">
                                            <label for="<?=$open_field?>" class="form-label"><?=h($open_rule[0])?></label>
                                            <?php if ($open_rule[1] === null): ?>
                                                <div class="input-group">
                                                    <?=$liveform->output_field(array(
                                                        'type' => 'text',
                                                        'id' => $open_field,
                                                        'name' => $open_field,
                                                        'class' => 'form-control text-end',
                                                        'maxlength' => '3',
                                                        'inputmode' => 'numeric'))?>
                                                    <label for="<?=$open_field?>" class="input-group-text">%</label>
                                                </div>
                                                <div class="form-text text-end"><?=lang('of the page, when it opens on scroll; blank for 50')?></div>
                                            <?php else: ?>
                                                <?=$liveform->output_field(array(
                                                    'type' => 'select',
                                                    'id' => $open_field,
                                                    'name' => $open_field,
                                                    'class' => 'form-control',
                                                    'options' => $open_rule[1]))?>
                                                <?php if ($open_field === 'open_on'): ?>
                                                    <div class="form-text text-end"><?=lang('on a touch screen, leaving opens after the delay')?></div>
                                                <?php endif ?>
                                            <?php endif ?>
                                        </div>
                                    <?php endforeach ?>
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
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        <?=lang('Last 30 Days')?>
                                    </h2>
                                    <div class="card-body pt-0">
                                        <?php // The pop-up's counts (includes/fn/auto_dialog_counts.php) as look A's stand-alone figures, three in a row. ?>
                                        <div class="pg-figures mb-0" style="--pg-figures: 3;">
                                            <?php foreach (array('shown' => lang('Times shown'), 'closed' => lang('Times closed'), 'taken' => lang('Times taken')) as $count_field => $count_label): ?>
                                                <div data-pg-auto-dialog-count="<?=$count_field?>">
                                                    <span class="pg-figure-label"><?=h($count_label)?></span>
                                                    <span class="pg-figure-value"><?=h(pg_format_number($auto_dialog_counts[$count_field], 0))?></span>
                                                </div>
                                            <?php endforeach ?>
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
                                            <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                                                <dt class="fw-normal"><?=h($summary_row[1])?></dt>
                                                <dd class="mb-0 fw-bold text-end text-break" id="auto_dialog_summary_<?=$summary_row[0]?>" data-blank="<?=h($summary_row[2])?>" data-unit="<?=h($summary_row[3])?>"><?=h(($summary_value === '') ? $summary_row[2] : trim($summary_value . ' ' . $summary_row[3]))?></dd>
                                            </div>
                                        <?php endforeach ?>
                                        <?php // When, to whom and where it opens: the chosen option of each select. ?>
                                        <?php foreach (pg_auto_dialog_form_open_fields() as $open_field => $open_rule): ?>
                                            <?php if ($open_rule[1] === null) { continue; } ?>
                                            <?php $open_chosen = array_search(pg_auto_dialog_open_settings(array($open_field => $liveform->get_field_value($open_field)))[$open_field], $open_rule[1], true); ?>
                                            <div class="d-flex justify-content-between gap-3 py-2<?php if (($open_field !== 'place') || ($screen == 'edit')): ?> border-bottom<?php endif ?>">
                                                <dt class="fw-normal"><?=h($open_rule[0])?></dt>
                                                <dd class="mb-0 fw-bold text-end text-break" id="auto_dialog_summary_<?=$open_field?>"><?=h((string) $open_chosen)?></dd>
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
                        // What it shows: the address row or the shared component row,
                        // the URL required only with the address; the open link follows
                        // the component chosen.
                        (function () {
                            var kind = document.getElementById("content");
                            var component = document.getElementById("component_id");
                            var open = document.getElementById("auto_dialog_component_open");
                            var url = document.getElementById("url");

                            if (!kind || !component || !open) {
                                return;
                            }

                            var show = function () {
                                var designed = (kind.value === "component");

                                document.querySelectorAll("[data-pg-auto-dialog-shows]").forEach(function (row) {
                                    row.classList.toggle("d-none", row.getAttribute("data-pg-auto-dialog-shows") !== (designed ? "component" : "url"));
                                });
                                url.required = !designed;
                                open.setAttribute("href", open.getAttribute("data-href") + encodeURIComponent(component.value));
                                open.classList.toggle("d-none", component.value === "");
                            };

                            kind.addEventListener("change", show);
                            component.addEventListener("change", show);
                        })();
                        // A select's row shows the option chosen.
                        ["open_on", "audience", "place"].forEach(function (name) {
                            var box = document.getElementById(name);
                            var cell = document.getElementById("auto_dialog_summary_" + name);
                            box.addEventListener("change", function () {
                                cell.textContent = box.options[box.selectedIndex] ? box.options[box.selectedIndex].text : "";
                            });
                        });
                    </script>
                    <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                        <nav class="buttons navigation text-center mb-7" aria-label="<?= h(lang('data edit buttons')) ?>">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="save_button" data-pg-save-shortcut name="submit_button" value="<?php if ($screen == 'create'): ?>Create<?php else: ?>Save<?php endif ?>" class="btn my-1 btn-solid theme-success "><i class="bi <?php if ($screen == 'create'): ?>bi-plus-circle<?php else: ?>bi-floppy<?php endif ?> me-3" aria-hidden="true"></i><span class="pg-btn-label" ><?php if ($screen == 'create'): ?><?=lang(array('string'=>'Create') )?><?php else: ?><?=lang(array('string'=>'Save') )?><?php endif ?></span></button>
                                    <?php if ($screen == 'edit'): ?>
                                        <button type="submit" name="delete" value="Delete" formnovalidate class="btn my-1  btn-solid theme-danger " data-confirm-content="<?=lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('auto dialog'))))?>"><i class="bi bi-trash me-3" aria-hidden="true"></i><span class="pg-btn-label" ><?=lang(array('string'=>'Delete') )?></span></button>
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