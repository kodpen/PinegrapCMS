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
    <?=get_date_picker_format()?>
    <div id="pg-offer-editor">

        <div class="pg-toolbar mb-3">
            <span class="badge rounded-pill text-bg-secondary" id="pg-if-status"></span>
            <div class="pg-toolbar-grow"></div>
            <?= $output_workspace_button ?? '' ?>
            <div class="dropdown ms-auto">
                <button type="button" class="btn btn-sm btn-ghost" data-bs-toggle="dropdown" aria-expanded="false" title="<?=lang('More')?>"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php // Only meaningful once the offer has an id; the script reveals them after the first save. ?>
                    <li data-saved-only<?php if ($is_new): ?> class="d-none"<?php endif ?>><a class="dropdown-item link-body-emphasis" href="#" data-on="duplicate"><i class="bi bi-copy me-2" aria-hidden="true"></i><?=lang('Duplicate')?></a></li>
                    <li data-saved-only<?php if ($is_new): ?> class="d-none"<?php endif ?>><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item link-body-emphasis" href="<?=OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY?>/view_offers.php"><i class="bi bi-list-ul me-2" aria-hidden="true"></i><?=lang('All Offers')?></a></li>
                </ul>
            </div>
        </div>

        <div class="card border-0 border-start border-4 rounded-0 border-primary shadow-sm mb-3">
            <div class="card-body py-3 px-3">
                <div class="small fw-bold text-uppercase text-body-secondary mb-1"><?=lang('Summary')?></div>
                <div class="fs-6 lh-base" id="pg-if-sentence"></div>
            </div>
        </div>

        <?php

        // The picker only shows on an offer that has nothing in it yet; a
        // template fills the rows below, it does not save anything.
        
        $templates = array(
            array('key' => 'code',     'icon' => 'bi-tag',      'title' => lang('Discount with a code'),          'text' => lang('The customer types a code and a percentage comes off the order.')),
            array('key' => 'basket',   'icon' => 'bi-cart3',    'title' => lang('Discount over a cart amount'),   'text' => lang('The order is discounted once the cart passes an amount.')),
            array('key' => 'shipping', 'icon' => 'bi-truck',    'title' => lang('Free shipping over an amount'),  'text' => lang('Shipping is free once the cart passes an amount.')),
            array('key' => 'gift',     'icon' => 'bi-gift',     'title' => lang('Buy X, get Y free'),             'text' => lang('A number of one product in the cart brings another one free.')),
            array('key' => 'product',  'icon' => 'bi-percent',  'title' => lang('Discount on a product'),         'text' => lang('A percentage off one product, on every order.')));
        if (_pg_offer_group_discount_column()) {
            $templates[] = array('key' => 'group', 'icon' => 'bi-collection', 'title' => lang('Discount on a product group'), 'text' => lang('A percentage off every product in one group.'));
        }
        // The rest need the 2026.4.4 schema. A template that cannot be saved is
        // not offered, so the picker never promises something the site cannot do.
        if (_pg_offer_target_column()) {
            $templates[] = array('key' => 'cheapest', 'icon' => 'bi-scissors',    'title' => lang('Buy 2, cheapest is free'),  'text' => lang('Two items in the cart and the least expensive one costs nothing.'));
        }
        if (_pg_offer_conditions_table()) {
            $templates[] = array('key' => 'tiered',   'icon' => 'bi-bar-chart-steps', 'title' => lang('Tiered cart discount'), 'text' => lang('The more the cart holds, the bigger the percentage.'));
            $templates[] = array('key' => 'bigcart',  'icon' => 'bi-boxes',       'title' => lang('Free shipping on a full cart'), 'text' => lang('A number of items in the cart, whatever they are, ships free.'));
            $templates[] = array('key' => 'welcome',  'icon' => 'bi-person-plus', 'title' => lang('Welcome discount'),       'text' => lang('A discount for a customer who has never ordered, once each.'));
            $templates[] = array('key' => 'oncecode', 'icon' => 'bi-ticket-perforated', 'title' => lang('One-time code'),    'text' => lang('A code every customer can use exactly once.'));
            $templates[] = array('key' => 'firstorders', 'icon' => 'bi-trophy',   'title' => lang('First orders only'),      'text' => lang('The offer runs until it has been used a set number of times, once per customer.'));
            $templates[] = array('key' => 'groupbuy', 'icon' => 'bi-bag-check',   'title' => lang('Buy from a group, save'), 'text' => lang('A product from one group discounts the whole order.'));
            if (_pg_offer_target_column()) {
                $templates[] = array('key' => 'groupcheapest', 'icon' => 'bi-basket', 'title' => lang('Buy 3 from a group, cheapest is free'), 'text' => lang('Three items from one group and the least expensive one costs nothing.'));
            }
        }
        $templates[] = array('key' => 'blank', 'icon' => 'bi-file-earmark', 'title' => lang('Blank offer'), 'text' => lang('Add the condition and the result yourself.'));
        ?>
        <div class="mb-3 d-none" id="pg-if-quick">
            <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
                <span class="small fw-bold text-uppercase text-body-secondary"><?=lang('Template')?></span>
                <span class="text-body-secondary small"><?=lang('Pick a template, or start from scratch.')?></span>
            </div>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 row-cols-xxl-5 row-cols-xxl-3 g-3 overflow-x-scroll flex-nowrap pb-3 pt-3">
                <?php foreach ($templates as $t): ?>
                <div class="col">
                    <button type="button" class="list-group-item list-group-item-action rounded border h-100 text-start p-2" data-on="quick" data-template="<?=$t['key']?>">
                        <span class="d-flex align-items-center gap-2 fw-semibold"><i class="bi <?=$t['icon']?> text-primary" aria-hidden="true"></i><?=h($t['title'])?></span>
                        <span class="d-block small text-body-secondary mt-1"><?=h($t['text'])?></span>
                    </button>
                </div>
                <?php endforeach ?>
            </div>
        </div>

        <div id="pg-if-steps" class="my-5">

            <!-- 1 · Offer -->
            <div class="pg-step" id="pg-if-step-offer">
                <div class="pg-step-rail"><span class="pg-step-node">1</span></div>
                <div class="card mb-5">
                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                        <span class="h5 text-uppercase text-primary fw-bold mb-0"><?=lang('Offer')?></span>
                        <span class="text-body-secondary small"><?=lang('What does the customer see?')?></span>
                        <div class="ms-auto form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="pg-if-enabled" data-on="enabled">
                            <label class="form-check-label" for="pg-if-enabled"><?=lang('Enabled')?></label>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-lg-5 col-xxl-4">
                                <label for="pg-if-code" class="form-label"><?=lang('Offer Code')?> <span class="text-body-secondary small">· <?=lang('the name shown in reports and at checkout')?></span></label>
                                <input type="text" class="form-control" id="pg-if-code" maxlength="50" data-on="code">
                                <div class="small text-danger-emphasis mt-1 d-none" id="pg-if-code-error"></div>
                            </div>
                            <div class="col-12 col-lg-7 col-xxl-8">
                                <label for="pg-if-description" class="form-label"><?=lang('Message')?> <span class="text-body-secondary small">· <?=lang('shown in the cart and on the receipt')?></span></label>
                                <input type="text" class="form-control" id="pg-if-description" maxlength="255" data-on="description">
                            </div>
                            <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="pg-if-require-code" data-on="require-code">
                                    <label class="form-check-label" for="pg-if-require-code"><?=lang('The customer must enter this code')?></label>
                                </div>
                                <span class="text-body-secondary small" id="pg-if-require-code-hint"></span>
                            </div>
                            <div class="col-12 d-none" id="pg-if-orders">
                                <span class="small text-body-secondary"><i class="bi bi-receipt me-1" aria-hidden="true"></i><span id="pg-if-orders-count"></span></span>
                            </div>
                            <div class="col-12 d-none" id="pg-if-keycodes">
                                <div class="row g-2 align-items-center">
                                    <div class="col-auto d-flex align-items-center gap-2 small">
                                        <i class="bi bi-key text-body-secondary" aria-hidden="true"></i>
                                        <span id="pg-if-keycodes-count"></span>
                                    </div>
                                    <?php // The stepper the other screens use (backend.src.js binds .number-controls). ?>
                                    <div class="col-auto">
                                        <div class="input-group input-group-sm number-controls" style="width: 7.5rem">
                                            <button class="btn btn-outline-secondary material-icons minus border-end-0 disabled" type="button" tabindex="-1" aria-label="<?=lang('Remove')?>">remove</button>
                                            <input type="text" class="form-control text-center border-start-0 border-end-0" id="pg-if-keycodes-quantity" value="1" min="1" max="100" inputmode="numeric" aria-label="<?=lang('Quantity')?>">
                                            <button class="btn btn-outline-secondary material-icons plus border-start-0" type="button" tabindex="-1" aria-label="<?=lang('Add')?>">add</button>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-on="keycodes-generate"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?=lang('Generate codes')?></button>
                                    </div>
                                    <?php // Only meaningful once codes exist; the script shows it then. ?>
                                    <div class="col-auto d-none" id="pg-if-keycodes-delete-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-on="keycodes-delete"><i class="bi bi-trash" aria-hidden="true"></i> <?=lang('Delete all codes')?></button>
                                    </div>
                                    <div class="col-auto">
                                        <a class="btn btn-sm btn-ghost" href="<?=OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY?>/view_key_codes.php"><?=lang('Manage key codes')?></a>
                                    </div>
                                    <div class="col-12 col-xl small text-body-secondary"><?=lang('single-use or per-customer aliases of this code')?></div>
                                    <div class="col-12 d-none" id="pg-if-keycodes-new">
                                        <label class="form-label small" for="pg-if-keycodes-list"><?=lang('The codes just created')?></label>
                                        <textarea class="form-control form-control-sm" id="pg-if-keycodes-list" rows="4" readonly></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2 · If -->
            <div class="pg-step" id="pg-if-step-conditions">
                <div class="pg-step-rail"><span class="pg-step-node">2</span></div>
                <div class="card mb-5">
                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                        <span class="h5 text-uppercase text-primary fw-bold mb-0"><?=lang('If')?></span>
                        <span class="text-body-secondary small"><?=lang('the cart meets these conditions')?><span class="d-none" id="pg-if-conditions-all"> · <?=lang('all of them')?></span></span>
                        <div class="dropdown ms-auto">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?=lang('Add condition')?></button>
                            <ul class="dropdown-menu dropdown-menu-end" id="pg-if-add-condition-menu"></ul>
                        </div>
                    </div>
                    <div class="list-group list-group-flush" id="pg-if-conditions"></div>
                </div>
            </div>

            <!-- 3 · Then -->
            <div class="pg-step" id="pg-if-step-actions">
                <div class="pg-step-rail"><span class="pg-step-node">3</span></div>
                <div class="card mb-5">
                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                        <span class="h5 text-uppercase text-primary fw-bold mb-0"><?=lang('Then')?></span>
                        <span class="text-body-secondary small"><?=lang('these results apply')?> · <?=lang('all of them')?></span>
                        <div class="dropdown ms-auto">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?=lang('Add result')?></button>
                            <ul class="dropdown-menu dropdown-menu-end" id="pg-if-add-action-menu"></ul>
                        </div>
                    </div>
                    <div class="list-group list-group-flush" id="pg-if-actions"></div>
                </div>
            </div>

            <!-- 4 · Options -->
            <div class="pg-step" id="pg-if-step-options">
                <div class="pg-step-rail"><span class="pg-step-node">4</span></div>
                <div class="card mb-5">
                    <div class="card-header p-0">
                        <button type="button" class="btn btn-link text-decoration-none link-body-emphasis d-flex flex-wrap align-items-center gap-2 w-100 text-start px-3 py-2" data-bs-toggle="collapse" data-bs-target="#pg-if-options" aria-expanded="false" aria-controls="pg-if-options">
                            <span class="h5 text-uppercase text-primary fw-bold mb-0"><?=lang('Options')?></span>
                            <span class="text-body-secondary small text-truncate" id="pg-if-options-summary"></span>
                            <i class="bi bi-chevron-down ms-auto text-body-secondary" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="collapse" id="pg-if-options">
                        <div class="card-body">

                            <div class="row g-2 align-items-start">
                                <div class="col-12 col-md-3 col-xl-2 text-body-secondary small pt-1"><?=lang('Validity')?></div>
                                <div class="col-12 col-md-9 col-xl-10">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-auto">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-secondary" id="pg-if-period-open" data-on="period" data-period="open"><?=lang('Open-ended')?></button>
                                                <button type="button" class="btn btn-outline-secondary" id="pg-if-period-range" data-on="period" data-period="range"><?=lang('Date range')?></button>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-7 col-md-6 col-lg-5 col-xl-4 d-none" id="pg-if-dates">
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control" id="pg-if-start" maxlength="10" autocomplete="off" data-on="start" placeholder="<?=lang('Start Date')?>">
                                                <span class="input-group-text">–</span>
                                                <input type="text" class="form-control" id="pg-if-end" maxlength="10" autocomplete="off" data-on="end" placeholder="<?=lang('End Date')?>">
                                            </div>
                                        </div>
                                        <div class="col-12 small text-danger-emphasis d-none" id="pg-if-dates-error"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 align-items-start border-top mt-2 pt-2" id="pg-if-upsell-row">
                                <div class="col-12 col-md-3 col-xl-2 text-body-secondary small pt-1"><?=lang('Up-sell message')?></div>
                                <div class="col-12 col-md-9 col-xl-10">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="pg-if-upsell" data-on="upsell">
                                        <label class="form-check-label" for="pg-if-upsell"><?=lang('Show a message when the conditions are not met but close')?></label>
                                    </div>
                                    <div class="row g-3 mt-0 d-none" id="pg-if-upsell-body">
                                        <div class="col-12 col-xl-8">
                                            <label class="form-label" for="pg-if-upsell-message"><?=lang('Message')?></label>
                                            <input type="text" class="form-control form-control-sm" id="pg-if-upsell-message" maxlength="255" data-on="upsell-message">
                                        </div>
                                        <div class="col-12 col-sm-6 col-xl-4">
                                            <label class="form-label" for="pg-if-upsell-subtotal"><?=lang('Show when the subtotal is within')?> <span class="text-body-secondary small">· <?=lang('of the required subtotal')?></span></label>
                                            <div class="input-group input-group-sm"><input type="text" class="form-control text-end" id="pg-if-upsell-subtotal" inputmode="decimal" data-on="upsell-subtotal"><span class="input-group-text"><?=h(_pg_offer_currency())?></span></div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-xl-4" id="pg-if-upsell-quantity-wrap">
                                            <label class="form-label" for="pg-if-upsell-quantity"><?=lang('Show when the quantity is within')?> <span class="text-body-secondary small">· <?=lang('of the required quantity')?></span></label>
                                            <input type="text" class="form-control form-control-sm" id="pg-if-upsell-quantity" inputmode="numeric" data-on="upsell-quantity">
                                        </div>
                                        <div class="col-12 col-sm-6 col-xl-4">
                                            <label class="form-label" for="pg-if-upsell-button"><?=lang('Button label')?> <span class="text-body-secondary small">· <?=lang('leave blank for no button')?></span></label>
                                            <input type="text" class="form-control form-control-sm" id="pg-if-upsell-button" maxlength="50" data-on="upsell-button">
                                        </div>
                                        <div class="col-12 col-sm-6 col-xl-4">
                                            <label class="form-label" for="pg-if-upsell-page"><?=lang('Button page')?></label>
                                            <select class="form-select form-select-sm" id="pg-if-upsell-page" data-on="upsell-page"><option value="">-<?=lang(array('string' => 'Select {var:1}', 'vars' => array(lang('page'))))?>-</option><?=$editor['pages_html']?></select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 align-items-start border-top mt-2 pt-2 d-none" id="pg-if-best-row">
                                <div class="col-12 col-md-3 col-xl-2 text-body-secondary small pt-1"><?=lang('Offers sharing this code')?></div>
                                <div class="col-12 col-md-9 col-xl-10">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" id="pg-if-best" data-on="best">
                                        <label class="form-check-label" for="pg-if-best"><?=lang(array('string' => '{var:1} other offer(s) share this code — apply only the best one', 'vars' => '<span id="pg-if-best-count"></span>'))?></label>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 align-items-start border-top mt-2 pt-2 d-none" id="pg-if-scope-row">
                                <div class="col-12 col-md-3 col-xl-2 text-body-secondary small pt-1"><?=lang('Scope')?></div>
                                <div class="col-12 col-md-9 col-xl-10">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-auto">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-secondary" id="pg-if-scope-order" data-on="scope" data-scope="order"><?=lang('Order')?></button>
                                                <button type="button" class="btn btn-outline-secondary" id="pg-if-scope-recipient" data-on="scope" data-scope="recipient"><?=lang('Recipient')?></button>
                                            </div>
                                        </div>
                                        <div class="col-auto d-none" id="pg-if-multiple-wrap">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" id="pg-if-multiple" data-on="multiple">
                                                <label class="form-check-label" for="pg-if-multiple"><?=lang('Allow offer to be applied to multiple recipients')?></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>

        <?php // Same sticky footer the other edit screens use, so Save is where the operator expects it. ?>
        <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="<?=lang('Save')?>">
            <div class="container">
                <div class="btn-group flex-wrap justify-content-center">
                    <button type="button" id="pg-if-save" data-on="save" class="btn my-1 btn-success"><span class="material-icons me-2"><?php if ($is_new): ?>add<?php else: ?>save<?php endif ?></span><span class="btn-text"><?php if ($is_new): ?><?=lang('Create')?><?php else: ?><?=lang('Save')?><?php endif ?></span></button>
                    <button type="button" data-on="delete" data-saved-only<?php if ($is_new): ?> class="btn my-1 btn-danger d-none"<?php else: ?> class="btn my-1 btn-danger"<?php endif ?>><span class="material-icons me-2">delete</span><span class="btn-text"><?=lang('Delete')?></span></button>
                </div>
            </div>
        </nav>
    </div>
    <script type="application/json" id="pg-offer-editor-data"><?=str_replace('</', '<\/', encode_json($editor))?></script>
    <script src="<?=OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY?>/assets/js/offer_editor.js?v=<?=@filemtime(dirname(dirname(__DIR__)) . '/assets/js/offer_editor.js')?>"></script>
</main>
