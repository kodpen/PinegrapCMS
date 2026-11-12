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
        <div class="col-12 lg:col-8 2xl:col-9 order-1">
            <div class="card my-7">
                <h2 class="card-header bg-reset border-0 pg-title-section"><?=h(lang('Layout'))?></h2>
                <div class="card-body pt-0">
                    <p class="form-text mt-0"><?=h(lang('Copy & paste the code below into your custom layout.'))?></p>
                    <?=$form->field(array(
                        'type' => 'textarea',
                        'name' => 'layout',
                        'id' => 'layout',
                        'aria-label' => lang('Layout'),
                        'class' => 'd-none h-100'))?>
                    <?=pg_code_editor_attach(array(
                        'id' => 'layout',
                        'code_type' => 'php',
                        'readonly' => true))?>
                </div>
            </div>
        </div>
        <div class="col-12 lg:col-4 2xl:col-3 order-2">
            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
            <div class="position-sticky" style="top:3.3rem;">
                <div class="card my-7">
                    <h2 class="card-header bg-reset border-0 pg-title-section"><?=h(lang('Summary'))?></h2>
                    <div class="card-body pt-0">
                        <dl class="mb-0">
                            <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                                <dt class="fw-normal"><?=h(lang('Page'))?></dt>
                                <dd class="mb-0 fw-bold text-end text-break"><?=h($page['name'])?></dd>
                            </div>
                            <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                                <dt class="fw-normal"><?=h(lang('Page Type'))?></dt>
                                <dd class="mb-0 fw-bold text-end text-break"><?=h((string) get_page_type_name($page['type']))?></dd>
                            </div>
                            <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                                <dt class="fw-normal"><?=h(lang('Layout Type'))?></dt>
                                <dd class="mb-0 fw-bold text-end text-break"><?=h(($page['layout_type'] === 'custom') ? lang('Custom') : lang('System'))?></dd>
                            </div>
                            <div class="d-flex justify-content-between gap-3 py-2">
                                <dt class="fw-normal"><?=h(lang('Lines of Code'))?></dt>
                                <dd class="mb-0 fw-bold text-end text-break"><?=(int) $layout_lines?></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
            <nav class="buttons navigation text-center mb-7" aria-label="<?=h(lang('data edit buttons'))?>">
                <div class="container">
                    <div class="btn-group flex-wrap justify-content-center">
                        <button type="button" id="generate_layout_copy" class="btn my-1 btn-solid theme-primary"><i class="bi bi-clipboard me-3" aria-hidden="true"></i><span class="pg-btn-label"><?=h(lang('Copy the code'))?></span></button>
                        <?php if ($edit_layout_url !== ''): ?>
                        <a href="<?=h($edit_layout_url)?>" class="btn my-1 btn-outline theme-secondary"><i class="bi bi-code-slash me-3" aria-hidden="true"></i><span class="pg-btn-label"><?=h(lang('Edit Layout'))?></span></a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
        </div>
    </div>
    <script>
        // The code sits in the read-only textarea behind the editor; the
        // button keeps its label and a toast says the copy is done.
        document.getElementById("generate_layout_copy").addEventListener("click", function () {
            var field = document.getElementById("layout");
            if (!field || !navigator.clipboard || !navigator.clipboard.writeText) {
                return;
            }
            navigator.clipboard.writeText(field.value.replace(/^\n/, "")).then(function () {
                if (typeof window.pgToast === "function") {
                    window.pgToast({ message: <?=json_encode(lang('Copied to clipboard'), JSON_HEX_TAG | JSON_HEX_AMP)?>, variant: "success" });
                }
            });
        });
    </script>
</main>
