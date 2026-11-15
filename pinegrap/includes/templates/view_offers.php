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
    <div class="pg-toolbar mb-5" id="pg-offers-toolbar">
        <a class="btn btn-sm btn-solid theme-primary rounded-pill px-5" href="edit_offer.php"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?=lang('New Offer')?></a>
        <?php
        // One chip per filter; the count rides in a badge so the active chip
        // stays readable when it fills in. A filter that may be empty is drawn
        // hidden at 0, so recount() can show it once a toggle gives it a row.
        $filters = array(
            array('key' => 'all',        'label' => lang('All'),        'count' => $counts['all'],        'always' => true),
            array('key' => 'active',     'label' => lang('Active'),     'count' => $counts['active'],     'always' => true),
            array('key' => 'disabled',   'label' => lang('Disabled'),   'count' => $counts['disabled'],   'always' => true),
            array('key' => 'scheduled',  'label' => lang('Planned'),    'count' => $counts['scheduled'],  'always' => false),
            array('key' => 'expired',    'label' => lang('Expired'),    'count' => $counts['expired'],    'always' => false),
            array('key' => 'code',       'label' => lang('With code'),  'count' => $counts['code'],       'always' => true),
            array('key' => 'auto',       'label' => lang('Automatic'),  'count' => $counts['auto'],       'always' => true),
            array('key' => 'incomplete', 'label' => lang('Incomplete'), 'count' => $counts['incomplete'], 'always' => false, 'badge' => 'theme-warning'));
        ?>
        <?php // Each chip is a filter of the list table driver on the Status cell's groups (pg_offer_list_filter()); All shows every row. ?>
        <div class="d-flex flex-wrap align-items-center gap-1" role="group" aria-label="<?=lang('Filter')?>">
            <?php foreach ($filters as $f): ?>
                <button type="button" class="btn btn-sm btn-outline theme-secondary rounded-pill px-5 d-inline-flex align-items-center gap-3<?php if ($f['key'] == 'all'): ?> active<?php endif ?><?php if (!$f['always'] && $f['count'] == 0): ?> d-none<?php endif ?>" data-filter="<?=$f['key']?>" data-pg-list-filter="view_offers" data-pg-filter-column="enabled" data-pg-filter-value="<?=$f['key'] == 'all' ? '' : $f['key']?>"<?php if (!$f['always']): ?> data-optional="1"<?php endif ?> aria-pressed="<?=$f['key'] == 'all' ? 'true' : 'false'?>">
                    <?=h($f['label'])?><span class="badge rounded-pill <?=isset($f['badge']) ? $f['badge'] : 'theme-secondary'?>" data-count><?=$f['count']?></span>
                </button>
            <?php endforeach ?>
        </div>
        <?=pg_list_table_search_box('view_offers')?>
        <div class="pg-toolbar-grow"></div>
        <div class="pg-menu-wrap ms-auto">
            <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="menu" data-bs-placement="bottom-end" aria-expanded="false" title="<?=lang('More')?>"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
            <ul class="menu pg-menu-end">
                <li><a class="menu-item pg-link-body-emphasis" href="view_key_codes.php"><i class="bi bi-key me-3" aria-hidden="true"></i><?=lang('All Key Codes')?></a></li>
                <li><hr class="menu-divider"></li>
                <li><a class="menu-item pg-link-body-emphasis<?php if ($orphan_count == 0): ?> disabled<?php endif ?>" href="#" id="pg-offers-cleanup" data-count="<?=$orphan_count?>"><i class="bi bi-trash me-3" aria-hidden="true"></i><?=lang('Delete unused rule and result records')?> <span class="fg-secondary small">(<?=$orphan_count?>)</span></a></li>
            </ul>
        </div>
    </div>

    <?php // The offers, printed whole: the driver sorts, searches and filters them in the browser; its quick edit writes through pg_offer_quick_update(). ?>
    <?=pg_list_table_html($list['source'], $list['page'], lang('All Offers'))?>

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
        var root = document.querySelector('.pg-list-table[data-pg-list-table="view_offers"]');

        function driver() { return root ? root.pgListTable : null; }
        function rows() { return root ? Array.prototype.slice.call(root.querySelectorAll('tbody tr[data-pg-id]')) : []; }
        function groups(tr) {
            var cell = tr ? tr.querySelector('td[data-pg-col="enabled"]') : null;
            return cell ? String(cell.getAttribute('data-pg-filter') || '').split(' ') : [];
        }
        // The chips narrow the list through the driver (data-pg-list-filter);
        // after a change of a row's groups the driver applies the chip in
        // use again, so a row that left it is hidden and counted out.
        function applyFilter() {
            var d = driver();
            if (d && d.browser) { d.showHere(); }
        }

        function api(body) {
            body.action = 'offer_editor';
            body.token = token;
            return fetch(apiUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
                .then(function (r) { return r.json(); });
        }
        function toast(message, variant) {
            if (window.pgToast) { window.pgToast({ message: message, variant: variant || 'info' }); }
        }

        // The filter chips carry counts; recount from the rows after a change
        // so they keep matching the list. Every row is in the page, hidden or
        // not, so the count is taken over all of them.
        function recount() {
            var all = rows();
            var counts = { all: all.length, active: 0, disabled: 0, scheduled: 0, expired: 0, code: 0, auto: 0, incomplete: 0 };
            all.forEach(function (tr) {
                groups(tr).forEach(function (name) { if (counts.hasOwnProperty(name)) { counts[name]++; } });
            });
            // An optional chip shows while it has rows; the chip in use stays
            // so the operator is not left on a filter they cannot see.
            $('#pg-offers-toolbar [data-filter]').each(function () {
                var chip = $(this), n = counts[chip.data('filter')] || 0;
                chip.find('[data-count]').text(n);
                if (chip.attr('data-optional') === '1') {
                    chip.toggleClass('d-none', n === 0 && !chip.hasClass('active'));
                }
            });
        }

        // A row's groups follow a change: the Status cell carries them.
        function regroup(tr, value) {
            var cell = tr ? tr.querySelector('td[data-pg-col="enabled"]') : null;
            if (cell) { cell.setAttribute('data-pg-filter', value); }
        }

        // The backend turns every title into a hover popover, so the button's
        // label lives in the popover instance once the page has loaded; the
        // spoken name is the aria-label, which nothing else updates.
        function relabel(btn, text) {
            btn.attr('title', text).attr('data-bs-original-title', text).attr('aria-label', text);
            var popover = window.pgUI ? pgUI.popover.find(btn[0]) : null;
            if (popover) {
                popover.hide();
                popover.setContent({ '.popover-header': text });
            }
        }

        // Enable / disable in place; the row's badge and groups follow. In the
        // quick edit mode the cell shows its field, so the badge it shows
        // after the mode is changed instead (pgQuickHtml).
        $(document).on('click', '.pg-offer-toggle', function () {
            var btn = $(this), id = btn.data('id'), tr = btn.closest('tr')[0];
            btn.prop('disabled', true);
            api({ type: 'offer_toggle_status', id: id }).then(function (res) {
                btn.prop('disabled', false);
                if (res.status !== 'success') { toast(res.message || labels.request_failed, 'danger'); return; }
                var cls = { active: 'theme-success', scheduled: 'theme-info', expired: 'theme-warning', disabled: 'theme-secondary' }[res.offer_status] || 'theme-secondary';
                var cell = tr.querySelector('td[data-pg-col="enabled"]');
                if (cell) {
                    var shown = document.createElement('div');
                    shown.innerHTML = cell.pgQuick ? (cell.pgQuickHtml || '') : cell.innerHTML;
                    $(shown).find('.pg-offer-status').attr('class', 'badge rounded-pill pg-offer-status fw-normal ' + cls).text(res.label);
                    if (cell.pgQuick) {
                        cell.pgQuickHtml = shown.innerHTML;
                        cell.pgQuick.control.value = res.enabled ? '1' : '0';
                    } else {
                        cell.innerHTML = shown.innerHTML;
                    }
                    cell.setAttribute('data-pg-edit-value', res.enabled ? '1' : '0');
                }
                var kept = groups(tr).slice(1);
                regroup(tr, [res.offer_status].concat(kept).join(' '));
                relabel(btn, res.enabled ? labels.disable : labels.enable);
                // A narrowing chip can hide the row, and focus would go with
                // it: it moves to the toggle of the next row shown, else the
                // last row shown, else the active chip.
                var focused = document.activeElement === btn[0] || !document.activeElement || document.activeElement === document.body;
                applyFilter();
                recount();
                if (focused && tr.hidden) {
                    var shownRows = rows().filter(function (r) { return !r.hidden; });
                    var after = shownRows.filter(function (r) { return r.compareDocumentPosition(tr) & Node.DOCUMENT_POSITION_PRECEDING; });
                    var next = $(after.length ? after[0] : shownRows[shownRows.length - 1]).find('.pg-offer-toggle');
                    (next.length ? next : $('#pg-offers-toolbar [data-filter].active')).first().trigger('focus');
                }
            }).catch(function () { btn.prop('disabled', false); toast(labels.request_failed, 'danger'); });
        });

        // A value changed in the driver's quick edit mode: the row's groups
        // and its toggle's name follow the answer, and a row the chip in use
        // no longer matches is hidden.
        if (root) {
            root.addEventListener('pg-list-table:saved', function (event) {
                var row = (event.detail && event.detail.data) ? event.detail.data.row : null;
                var tr = event.detail ? event.detail.row : null;
                if (!row || !tr) { return; }
                regroup(tr, row.filter);
                relabel($(tr).find('.pg-offer-toggle'), row.enabled ? labels.disable : labels.enable);
                recount();
                applyFilter();
            });
        }

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
