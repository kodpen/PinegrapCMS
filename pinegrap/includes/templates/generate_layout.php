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
    <div class="row ">
        <div class="col-12">
            <div class="row mb-2  flex-wrap">
                <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                    <h2 class="d-inline-block text-break" data-bs-content="<?php echo lang('Copy & paste the code below into your custom layout.')?>" title="<?php echo lang('Generate Layout')?>">[<?=h($page['name'])?>]</h2>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 my-2">
                                    <?=$form->field(array(
                                        'type' => 'textarea',
                                        'name' => 'layout',
                                        'id' => 'layout',
                                        'class' => 'd-none h-100'))?>
                                    <?=get_codemirror_includes()?>
                                    <?=get_codemirror_javascript(array(
                                        'id' => 'layout',
                                        'code_type' => 'php',
                                        'readonly' => true))?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
