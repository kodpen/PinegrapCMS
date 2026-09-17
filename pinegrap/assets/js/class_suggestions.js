/*
 * PineGrap Style Designer — class suggestions
 *
 * Exposes window.PG_CLASS_SUGGESTIONS — a curated list of commonly used
 * Bootstrap 5 utility + component classes. Used by the Attrs panel class
 * input to render an autocomplete dropdown.
 *
 * Not exhaustive. Covers the 80% of utilities designers actually type.
 * Extend as needed.
 */
(function () {
    'use strict';

    var breakpoints = ['sm', 'md', 'lg', 'xl', 'xxl'];

    // Helper: expand `d-flex` → ['d-flex','d-sm-flex',...]
    function bp(prefix, suffixes) {
        var out = [];
        suffixes.forEach(function (s) {
            out.push(prefix + '-' + s);
            breakpoints.forEach(function (b) { out.push(prefix + '-' + b + '-' + s); });
        });
        return out;
    }

    var list = [];

    // Display
    list = list.concat(bp('d', ['none','inline','inline-block','block','grid','inline-grid','table','table-row','table-cell','flex','inline-flex']));

    // Flex
    list = list.concat(
        bp('flex', ['row','row-reverse','column','column-reverse','wrap','nowrap','wrap-reverse','fill','grow-0','grow-1','shrink-0','shrink-1']),
        bp('justify-content', ['start','end','center','between','around','evenly']),
        bp('align-items', ['start','end','center','baseline','stretch']),
        bp('align-self', ['start','end','center','baseline','stretch','auto']),
        bp('align-content', ['start','end','center','between','around','stretch']),
        bp('order', ['first','0','1','2','3','4','5','last'])
    );

    // Spacing — m/p × t/b/s/e/x/y × 0..5 + auto
    ['m','p'].forEach(function (mp) {
        ['','t','b','s','e','x','y'].forEach(function (side) {
            ['0','1','2','3','4','5','auto'].forEach(function (v) {
                if (v === 'auto' && mp === 'p') return; // no p-auto
                list.push(mp + side + '-' + v);
                breakpoints.forEach(function (b) { list.push(mp + side + '-' + b + '-' + v); });
            });
            ['n1','n2','n3','n4','n5'].forEach(function (v) {
                if (mp === 'p') return; // only margin has negatives
                list.push(mp + side + '-' + v);
                breakpoints.forEach(function (b) { list.push(mp + side + '-' + b + '-' + v); });
            });
        });
    });

    // Gap
    list = list.concat(bp('gap', ['0','1','2','3','4','5']));
    list = list.concat(bp('row-gap', ['0','1','2','3','4','5']));
    list = list.concat(bp('column-gap', ['0','1','2','3','4','5']));

    // Sizing
    ['w','h'].forEach(function (wh) {
        ['25','50','75','100','auto'].forEach(function (v) { list.push(wh + '-' + v); });
    });
    ['mw','mh','vw','vh','min-vw','min-vh'].forEach(function (k) { list.push(k + '-100'); });

    // Colors — text-*
    var colorNames = ['primary','secondary','success','danger','warning','info','light','dark','body','muted','white','black','body-emphasis','body-secondary','body-tertiary'];
    colorNames.forEach(function (c) {
        list.push('text-' + c);
        list.push('bg-' + c);
        list.push('border-' + c);
        list.push('link-' + c);
        list.push('btn-' + c);
        list.push('btn-outline-' + c);
        list.push('text-bg-' + c);
    });
    list = list.concat([
        'text-opacity-25','text-opacity-50','text-opacity-75','text-opacity-100',
        'bg-opacity-10','bg-opacity-25','bg-opacity-50','bg-opacity-75','bg-opacity-100',
        'bg-gradient','bg-transparent','text-transparent',
        'text-reset','text-decoration-none','text-decoration-underline','text-decoration-line-through',
        'text-break','text-truncate','text-wrap','text-nowrap',
        'text-lowercase','text-uppercase','text-capitalize',
        'fw-light','fw-lighter','fw-normal','fw-medium','fw-semibold','fw-bold','fw-bolder',
        'fst-italic','fst-normal',
        'font-monospace','lh-1','lh-sm','lh-base','lh-lg',
        'fs-1','fs-2','fs-3','fs-4','fs-5','fs-6','small'
    ]);
    // Text alignment
    list = list.concat(bp('text', ['start','end','center']));

    // Border
    list = list.concat([
        'border','border-0','border-top','border-top-0','border-end','border-end-0','border-bottom','border-bottom-0','border-start','border-start-0',
        'border-1','border-2','border-3','border-4','border-5'
    ]);

    // Border radius
    list = list.concat([
        'rounded','rounded-0','rounded-1','rounded-2','rounded-3','rounded-4','rounded-5',
        'rounded-top','rounded-end','rounded-bottom','rounded-start',
        'rounded-circle','rounded-pill','rounded-sm','rounded-lg'
    ]);

    // Shadow
    list = list.concat(['shadow','shadow-none','shadow-sm','shadow-lg','shadow-inset']);

    // Position
    list = list.concat([
        'position-static','position-relative','position-absolute','position-fixed','position-sticky',
        'top-0','top-50','top-100','start-0','start-50','start-100','end-0','end-50','end-100','bottom-0','bottom-50','bottom-100',
        'translate-middle','translate-middle-x','translate-middle-y',
        'sticky-top','sticky-bottom','fixed-top','fixed-bottom'
    ]);

    // Float / overflow / visibility
    list = list.concat(bp('float', ['start','end','none']));
    list = list.concat(['overflow-auto','overflow-hidden','overflow-visible','overflow-scroll','overflow-x-auto','overflow-x-hidden','overflow-y-auto','overflow-y-hidden','visible','invisible']);

    // Buttons
    list = list.concat(['btn','btn-sm','btn-lg','btn-close','btn-link','btn-group','btn-group-sm','btn-group-lg','btn-group-vertical','btn-toolbar']);

    // Forms
    list = list.concat([
        'form-control','form-control-sm','form-control-lg','form-control-plaintext','form-control-color',
        'form-select','form-select-sm','form-select-lg',
        'form-check','form-check-input','form-check-label','form-check-inline','form-switch',
        'form-range','form-text','form-label','form-floating','col-form-label','col-form-label-sm','col-form-label-lg',
        'input-group','input-group-sm','input-group-lg','input-group-text',
        'is-valid','is-invalid','valid-feedback','invalid-feedback','valid-tooltip','invalid-tooltip',
        'was-validated','needs-validation','disabled','readonly'
    ]);

    // Grid
    list = list.concat(['container','container-fluid','container-sm','container-md','container-lg','container-xl','container-xxl','row','row-cols-auto','row-cols-1','row-cols-2','row-cols-3','row-cols-4','row-cols-5','row-cols-6']);
    for (var _c = 1; _c <= 12; _c++) {
        list.push('col-' + _c);
        breakpoints.forEach(function (b) { list.push('col-' + b + '-' + _c); });
    }
    list.push('col'); list.push('col-auto');
    breakpoints.forEach(function (b) { list.push('col-' + b); list.push('col-' + b + '-auto'); });
    list = list.concat(bp('offset', ['0','1','2','3','4','5','6','7','8','9','10','11']));

    // Components
    list = list.concat([
        // Accordion
        'accordion','accordion-item','accordion-header','accordion-button','accordion-collapse','accordion-body','accordion-flush','collapsed',
        // Alerts
        'alert','alert-primary','alert-secondary','alert-success','alert-danger','alert-warning','alert-info','alert-light','alert-dark','alert-dismissible','alert-link','alert-heading',
        // Badge
        'badge','rounded-pill',
        // Breadcrumb
        'breadcrumb','breadcrumb-item',
        // Card
        'card','card-body','card-title','card-subtitle','card-text','card-link','card-header','card-footer','card-img','card-img-top','card-img-bottom','card-img-overlay','card-group',
        // Carousel
        'carousel','carousel-inner','carousel-item','carousel-item-next','carousel-item-prev','carousel-item-start','carousel-item-end','carousel-control-prev','carousel-control-next','carousel-control-prev-icon','carousel-control-next-icon','carousel-indicators','carousel-caption','carousel-fade','carousel-dark',
        // Close
        'btn-close-white',
        // Collapse
        'collapse','collapsing','show',
        // Dropdown
        'dropdown','dropdown-toggle','dropdown-toggle-split','dropdown-menu','dropdown-menu-start','dropdown-menu-end','dropdown-menu-dark','dropdown-divider','dropdown-header','dropdown-item','dropdown-item-text','dropup','dropstart','dropend',
        // List group
        'list-group','list-group-item','list-group-item-action','list-group-flush','list-group-numbered','list-group-horizontal','list-group-item-primary','list-group-item-secondary','list-group-item-success','list-group-item-danger','list-group-item-warning','list-group-item-info','list-group-item-light','list-group-item-dark',
        // Modal
        'modal','modal-dialog','modal-content','modal-header','modal-title','modal-body','modal-footer','modal-dialog-scrollable','modal-dialog-centered','modal-sm','modal-lg','modal-xl','modal-fullscreen','fade',
        // Nav / Navbar
        'nav','nav-item','nav-link','nav-tabs','nav-pills','nav-underline','nav-fill','nav-justified','tab-content','tab-pane','active',
        'navbar','navbar-brand','navbar-nav','navbar-text','navbar-collapse','navbar-toggler','navbar-toggler-icon','navbar-expand','navbar-expand-sm','navbar-expand-md','navbar-expand-lg','navbar-expand-xl','navbar-expand-xxl','navbar-light','navbar-dark',
        // Offcanvas
        'offcanvas','offcanvas-start','offcanvas-end','offcanvas-top','offcanvas-bottom','offcanvas-header','offcanvas-title','offcanvas-body','offcanvas-backdrop',
        // Pagination
        'pagination','pagination-sm','pagination-lg','page-item','page-link',
        // Placeholder
        'placeholder','placeholder-glow','placeholder-wave','placeholder-xs','placeholder-sm','placeholder-lg',
        // Popover / Tooltip
        'popover','popover-arrow','popover-header','popover-body','tooltip','tooltip-arrow','tooltip-inner',
        // Progress
        'progress','progress-bar','progress-bar-striped','progress-bar-animated','progress-stacked',
        // Spinner
        'spinner-border','spinner-border-sm','spinner-grow','spinner-grow-sm',
        // Table
        'table','table-striped','table-striped-columns','table-hover','table-bordered','table-borderless','table-sm','table-lg','table-responsive','table-primary','table-secondary','table-success','table-danger','table-warning','table-info','table-light','table-dark','caption-top',
        // Toast
        'toast','toast-header','toast-body','toast-container',
        // Focus ring
        'focus-ring','focus-ring-primary','focus-ring-secondary','focus-ring-success','focus-ring-danger','focus-ring-warning','focus-ring-info','focus-ring-light','focus-ring-dark',
        // Ratio
        'ratio','ratio-1x1','ratio-4x3','ratio-16x9','ratio-21x9',
        // Interaction
        'user-select-all','user-select-auto','user-select-none','pe-none','pe-auto',
        // Screen readers
        'visually-hidden','visually-hidden-focusable','stretched-link',
        // Misc
        'clearfix','vr','hstack','vstack','link-underline-opacity-0','link-underline-opacity-25','link-underline-opacity-50','link-underline-opacity-75','link-underline-opacity-100','link-offset-1','link-offset-2','link-offset-3','link-underline'
    ]);

    // Deduplicate
    var seen = {};
    var unique = [];
    for (var i = 0; i < list.length; i++) {
        if (!seen[list[i]]) { seen[list[i]] = 1; unique.push(list[i]); }
    }

    window.PG_CLASS_SUGGESTIONS = unique;
})();
