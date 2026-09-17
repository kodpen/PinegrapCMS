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
    <?php // What the editor left behind after a save or a delete. ?>
    <?=$liveform->output_errors()?>
    <?=$liveform->get_warnings()?>
    <?=$liveform->output_notices()?>
    <div class="pg-toolbar mb-3" id="pg-offers-toolbar">
        <a class="btn btn-sm btn-primary rounded-pill px-3" href="edit_offer.php" data-loading-content="<?=lang('Loading')?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?=lang('New Offer')?></a>
        <?php
        // One chip per filter; the count rides in a badge so the active chip
        // stays readable when it fills in. A filter with nothing to show is
        // left out rather than drawn empty.
        $filters = array(
            array('key' => 'all',        'label' => lang('All'),        'count' => $counts['all'],        'always' => true),
            array('key' => 'active',     'label' => lang('Active'),     'count' => $counts['active'],     'always' => true),
            array('key' => 'disabled',   'label' => lang('Disabled'),   'count' => $counts['disabled'],   'always' => true),
            array('key' => 'scheduled',  'label' => lang('Planned'),    'count' => $counts['scheduled'],  'always' => false),
            array('key' => 'expired',    'label' => lang('Expired'),    'count' => $counts['expired'],    'always' => false),
            array('key' => 'code',       'label' => lang('With code'),  'count' => $counts['code'],       'always' => true),
            array('key' => 'auto',       'label' => lang('Automatic'),  'count' => $counts['auto'],       'always' => true),
            array('key' => 'incomplete', 'label' => lang('Incomplete'), 'count' => $counts['incomplete'], 'always' => false, 'badge' => 'text-bg-warning'));
        ?>
        <div class="d-flex flex-wrap align-items-center gap-1" role="group" aria-label="<?=lang('Filter')?>">
            <?php foreach ($filters as $f): ?>
                <?php if (!$f['always'] && $f['count'] == 0) { continue; } ?>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-inline-flex align-items-center gap-2<?php if ($f['key'] == 'all'): ?> active<?php endif ?>" data-filter="<?=$f['key']?>" aria-pressed="<?=$f['key'] == 'all' ? 'true' : 'false'?>">
                    <?=h($f['label'])?><span class="badge rounded-pill <?=isset($f['badge']) ? $f['badge'] : 'text-bg-secondary'?>" data-count><?=$f['count']?></span>
                </button>
            <?php endforeach ?>
        </div>
        <div class="pg-toolbar-grow"></div>
        <div class="dropdown ms-auto">
            <button type="button" class="btn btn-sm btn-ghost" data-bs-toggle="dropdown" aria-expanded="false" title="<?=lang('More')?>"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item link-body-emphasis" href="view_key_codes.php"><i class="bi bi-key me-2" aria-hidden="true"></i><?=lang('All Key Codes')?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item link-body-emphasis<?php if ($orphan_count == 0): ?> disabled<?php endif ?>" href="#" id="pg-offers-cleanup" data-count="<?=$orphan_count?>"><i class="bi bi-trash me-2" aria-hidden="true"></i><?=lang('Delete unused rule and result records')?> <span class="text-body-secondary small">(<?=$orphan_count?>)</span></a></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0 position-relative disable_shortcut">
            <table class="chart table-hover table" style="width:100%;display:none">
                <thead>
                    <tr>
                        <th class="noVis"><?=lang('Action')?></th>
                        <th class="w-25"><?=lang('Offer')?></th>
                        <th><?=lang('Flow')?></th>
                        <th><?=lang('How')?></th>
                        <th><?=lang('Status')?></th>
                        <th><?=lang('Validity')?></th>
                        <th><?=lang('Last Modified')?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offers as $offer): ?>
                    <tr data-id="<?=$offer['id']?>" data-status="<?=$offer['status']?>" data-how="<?=$offer['require_code'] ? 'code' : 'auto'?>" data-incomplete="<?=$offer['incomplete'] ? '1' : '0'?>">
                        <td class="align-middle text-start text-nowrap">
                            <a class="m-1 btn-data-control btn btn-outline-primary border-2" href="edit_offer.php?id=<?=$offer['id']?>" title="<?=lang('Edit')?>" data-loading-content=" "><i class="bi bi-pencil"></i></a>
                            <a class="m-1 btn-data-control btn btn-outline-secondary border-2" href="edit_offer.php?duplicate=<?=$offer['id']?>" title="<?=lang('Duplicate')?>" data-loading-content=" "><i class="bi bi-copy"></i></a>
                            <button type="button" class="m-1 btn-data-control btn btn-outline-secondary border-2 pg-offer-toggle" data-id="<?=$offer['id']?>" title="<?=$offer['enabled'] ? lang('Disable offer') : lang('Enable offer')?>"><i class="bi bi-power"></i></button>
                        </td>
                        <td class="align-middle">
                            <a class="fw-semibold link-body-emphasis text-decoration-none" href="edit_offer.php?id=<?=$offer['id']?>"><?=h($offer['code'])?></a>
                            <?php if ($offer['description'] != ''): ?><div class="small text-body-secondary"><?=h($offer['description'])?></div><?php endif ?>
                        </td>
                        <td class="align-middle" title="<?=h($offer['sentence'])?>">
                            <div class="d-flex flex-wrap align-items-center gap-1">
                                <?php if ($offer['condition_chips']): ?>
                                    <?php foreach ($offer['condition_chips'] as $chip): ?><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle fw-normal"><i class="bi bi-funnel" aria-hidden="true"></i> <?=h($chip)?></span><?php endforeach ?>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-body-tertiary text-body-secondary border fw-normal"><?=lang('Every order')?></span>
                                <?php endif ?>
                                <span class="text-body-secondary small">→</span>
                                <?php if ($offer['action_chips']): ?>
                                    <?php foreach ($offer['action_chips'] as $chip): ?><span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle fw-normal"><i class="bi <?=$chip['icon']?>" aria-hidden="true"></i> <?=h($chip['text'])?></span><?php endforeach ?>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?=lang('No result')?></span>
                                <?php endif ?>
                                <?php if ($offer['upsell']): ?><span class="badge rounded-pill bg-body-tertiary text-body-secondary border fw-normal" title="<?=lang('Up-sell message')?>"><i class="bi bi-chat-square-text" aria-hidden="true"></i></span><?php endif ?>
                            </div>
                        </td>
                        <td class="align-middle text-nowrap">
                            <?php if ($offer['require_code']): ?>
                                <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle fw-normal"><i class="bi bi-tag" aria-hidden="true"></i> <?=lang('Code')?></span>
                            <?php else: ?>
                                <span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle fw-normal"><i class="bi bi-lightning-charge" aria-hidden="true"></i> <?=lang('Automatic')?></span>
                            <?php endif ?>
                        </td>
                        <td class="align-middle text-nowrap">
                            <span class="badge rounded-pill pg-offer-status fw-normal <?php if ($offer['status'] == 'active'): ?>text-bg-success<?php elseif ($offer['status'] == 'scheduled'): ?>text-bg-info<?php elseif ($offer['status'] == 'expired'): ?>text-bg-warning<?php else: ?>text-bg-secondary<?php endif ?>"><?=h($offer['status_label'])?></span>
                            <?php if ($offer['incomplete']): ?><span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-normal" title="<?=lang('This offer cannot do anything at checkout yet.')?>"><?=lang('Incomplete')?></span><?php endif ?>
                        </td>
                        <td class="align-middle text-nowrap text-body-secondary"><?=h($offer['period'])?></td>
                        <td class="align-middle text-nowrap text-body-secondary">
                            <?=$offer['modified']?>
                            <?php if ($offer['modified_by'] != ''): ?><?=lang(array('string' => 'by {var:1}', 'vars' => array(h($offer['modified_by']))))?><?php endif ?>
                        </td>
                    </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    $(function () {
        var token = <?=encode_json($_SESSION['software']['token'])?>;
        var apiUrl = <?=encode_json(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/api.php')?>;
        var labels = <?=encode_json(array(
            'request_failed' => lang('Sorry, we could not accept your request.'),
            'enable'         => lang('Enable offer'),
            'disable'        => lang('Disable offer'),
            'cleanup_title'  => lang('Delete unused records'),
            'cleanup_text'   => lang('Rule and result records that no offer uses will be permanently deleted.'),
            'delete'         => lang('Delete'),
            'cancel'         => lang('Cancel')))?>;
        var filter = 'all';
        var hooked = false;

        function matches(tr) {
            if (!tr || filter === 'all') { return true; }
            if (filter === 'code' || filter === 'auto') { return tr.getAttribute('data-how') === filter; }
            if (filter === 'incomplete') { return tr.getAttribute('data-incomplete') === '1'; }
            return tr.getAttribute('data-status') === filter;
        }
        function table() {
            if ($.fn.dataTable && $.fn.dataTable.isDataTable('table.chart')) { return $('table.chart').DataTable(); }
            return null;
        }
        // The chips narrow the table through DataTables so paging, sorting and
        // the search box keep working on the narrowed set. DataTables is not
        // always on the page yet when this runs, so the hook is registered
        // both now and on the table's own init event, and the plain jQuery
        // fallback keeps the chips working on a page without it.
        //
        // The hook recognises its own rows by data-status rather than by the
        // table element: scrollX makes DataTables clone the table for the
        // scrolling header, so "table.chart" matches three tables on this page
        // and the clone is the first of them.
        function hook() {
            if (hooked || !$.fn.dataTable) { return; }
            hooked = true;
            $.fn.dataTable.ext.search.push(function (settings, data, index) {
                var tr = settings.aoData[index] ? settings.aoData[index].nTr : null;
                if (!tr || !tr.getAttribute('data-status')) { return true; }
                return matches(tr);
            });
        }
        hook();
        // A chip clicked while the table is still initialising would be undone
        // by the first draw, so the filter is applied again once init lands.
        $(document).on('init.dt', function () { hook(); applyFilter(); });

        function applyFilter() {
            var dt = table();
            if (dt) { hook(); dt.draw(); return; }
            $('table.chart tbody tr[data-status]').each(function () { $(this).toggle(matches(this)); });
        }
        $('#pg-offers-toolbar [data-filter]').on('click', function () {
            filter = $(this).data('filter');
            $('#pg-offers-toolbar [data-filter]').removeClass('active').attr('aria-pressed', 'false');
            $(this).addClass('active').attr('aria-pressed', 'true');
            applyFilter();
        });

        function api(body) {
            body.action = 'offer_editor';
            body.token = token;
            return fetch(apiUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
                .then(function (r) { return r.json(); });
        }
        function toast(message, variant) {
            if (window.pgToast) { window.pgToast({ message: message, variant: variant || 'info' }); }
        }

        // The filter chips carry counts; recount from the rows after a toggle so
        // they keep matching the table. DataTables detaches rows on other
        // pages, so the rows are taken from its API rather than the DOM.
        function recount() {
            var dt = table();
            var rows = dt ? dt.rows().nodes().toArray() : $('table.chart tbody tr[data-status]').toArray();
            var counts = { all: rows.length, active: 0, disabled: 0, scheduled: 0, expired: 0, code: 0, auto: 0, incomplete: 0 };
            rows.forEach(function (tr) {
                counts[tr.getAttribute('data-status')]++;
                counts[tr.getAttribute('data-how')]++;
                if (tr.getAttribute('data-incomplete') === '1') { counts.incomplete++; }
            });
            $('#pg-offers-toolbar [data-filter]').each(function () {
                $(this).find('[data-count]').text(counts[$(this).data('filter')] || 0);
            });
        }

        // The backend turns every title into a hover popover, so the button's
        // label lives in the popover instance once the page has loaded.
        function relabel(btn, text) {
            btn.attr('title', text).attr('data-bs-original-title', text);
            var popover = window.bootstrap && bootstrap.Popover ? bootstrap.Popover.getInstance(btn[0]) : null;
            if (popover) {
                popover.hide();
                popover.setContent({ '.popover-header': text });
            }
        }

        // Enable / disable in place; the row's badge and filter data follow.
        $(document).on('click', '.pg-offer-toggle', function () {
            var btn = $(this), id = btn.data('id'), tr = btn.closest('tr');
            btn.prop('disabled', true);
            api({ type: 'offer_toggle_status', id: id }).then(function (res) {
                btn.prop('disabled', false);
                if (res.status !== 'success') { toast(res.message || labels.request_failed, 'danger'); return; }
                var cls = { active: 'text-bg-success', scheduled: 'text-bg-info', expired: 'text-bg-warning', disabled: 'text-bg-secondary' }[res.offer_status] || 'text-bg-secondary';
                tr.find('.pg-offer-status').attr('class', 'badge rounded-pill pg-offer-status fw-normal ' + cls).text(res.label);
                tr.attr('data-status', res.offer_status);
                relabel(btn, res.enabled ? labels.disable : labels.enable);
                var dt = table();
                if (dt) { dt.draw(false); }
                recount();
            }).catch(function () { btn.prop('disabled', false); toast(labels.request_failed, 'danger'); });
        });

        $('#pg-offers-cleanup').on('click', function (e) {
            e.preventDefault();
            var link = $(this);
            if (link.hasClass('disabled')) { return; }
            var run = function () {
                api({ type: 'offer_cleanup_orphans' }).then(function (res) {
                    if (res.status !== 'success') { toast(res.message || labels.request_failed, 'danger'); return; }
                    toast(res.message, 'success');
                    link.addClass('disabled').find('span').text('(0)');
                }).catch(function () { toast(labels.request_failed, 'danger'); });
            };
            if (window.pgConfirm) {
                window.pgConfirm({ title: labels.cleanup_title, message: labels.cleanup_text, confirmText: labels.delete, cancelText: labels.cancel, variant: 'warning' }).then(function (ok) { if (ok) { run(); } });
            } else if (window.confirm(labels.cleanup_text)) {
                run();
            }
        });
    });
    </script>
</main>
