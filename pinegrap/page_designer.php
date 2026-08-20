<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_area_access($user, 'designer');



$starting_item_type = '';
if(isset($_GET['type'])){
    $starting_item_type = escape_javascript($_GET['type']);
}

$starting_item_id = '';
if(isset($_GET['id'])){
    $starting_item_id = escape_javascript($_GET['id']);
}
$page_designer_query = '';
if(isset($_SESSION['software']['page_designer']['query'])){
    $page_designer_query = escape_javascript(($_SESSION['software']['page_designer']['query'] ?? ''));
}
$dynamic_regions = 0;
if (($user['role'] < 1) && ((defined('DYNAMIC_REGIONS') == true) && (DYNAMIC_REGIONS == true))) {
    $dynamic_regions = 1;
}


echo pg_page_shell(
    array(
        'title'=> lang('Page Designer'),
        'extra classes'=>'page_designer',
        'icon'=>'design',
        'replace_home_with'=>'close',
        'hide_menu'=>true,
        'heading'=>'<span class="pagelink me-2"></span><span id="previewpaneltitle"></span>',
        'auto_main'=>false,
    )
) . '  

    

    <link rel="stylesheet" type="text/css" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/page_designer.css?v=' . @filemtime(dirname(__FILE__) . '/assets/page_designer.css') . '" />
    <script>
    // ── Early console interceptor: queues messages until pdConsoleAdd is ready ──
    (function() {
        var _q = [];
        function _add(type, msg) {
            if (typeof window.pdConsoleAdd === "function") { window.pdConsoleAdd(type, msg); }
            else { _q.push({ type: type, msg: msg }); }
        }
        function _fmt(a) {
            if (!a.length) return "";
            var s = a[0], i = 1, r;
            if (typeof s !== "string" || !/%(c|s|d|i|f|o|O)/.test(s)) {
                return Array.prototype.slice.call(a).map(function(x) {
                    try { return typeof x === "object" ? JSON.stringify(x) : String(x); } catch(e) { return String(x); }
                }).join(" ");
            }
            r = s.replace(/%([csdifOo%])/g, function(m, t) {
                if (t === "%") return "%";
                if (i >= a.length) return m;
                var v = a[i++];
                if (t === "c") return "";
                if (t === "s") return String(v);
                if (t === "d" || t === "i") return parseInt(v, 10);
                if (t === "f") return parseFloat(v);
                try { return typeof v === "object" ? JSON.stringify(v) : String(v); } catch(e) { return String(v); }
            });
            while (i < a.length) {
                try { r += " " + (typeof a[i] === "object" ? JSON.stringify(a[i]) : String(a[i])); } catch(e) { r += " " + String(a[i]); } i++;
            }
            return r;
        }
        ["error","warn","log","info"].forEach(function(m) {
            var _o = console[m].bind(console);
            console[m] = function() {
                _add(m, "[page] " + _fmt(arguments));
                _o.apply(null, arguments);
            };
        });
        window.addEventListener("error", function(e) {
            if (e.message !== undefined) {
                var src = e.filename ? e.filename.replace(/^.*[\\/]/, "") : "";
                _add("error", "[page] " + (e.message || "Error") + (src ? " [" + src + ":" + e.lineno + "]" : ""));
            } else {
                var el  = e.target || e.srcElement;
                var url = (el && (el.src || el.href || el.currentSrc)) || "";
                var tag = el && el.tagName ? el.tagName.toLowerCase() : "resource";
                _add("error", "[page] Failed to load " + tag + (url ? ": " + url : ""));
            }
        }, true);
        window.addEventListener("unhandledrejection", function(e) {
            var r = e.reason;
            _add("error", "[page] Unhandled Promise: " + (r && r.message ? r.message : String(r)));
        });
        window._pdConsoleFlushQueue = function() {
            _q.forEach(function(item) { window.pdConsoleAdd(item.type, item.msg); });
            _q = [];
        };
    })();
    </script>
    <script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/page_designer.js?v=' . @filemtime(dirname(__FILE__) . '/assets/page_designer.js') . '"></script>
    ' . get_codemirror_includes() . '
    <script>
        init_page_designer({
            url: "' . escape_javascript($_GET['url']) . '",
            starting_item_type: "' . $starting_item_type . '",
            starting_item_id: "' . $starting_item_id . '",
            query: "' . $page_designer_query . '",
            role : ' . $user['role'] . ',
            dynamic_regions: ' . $dynamic_regions . ',
            labels:{
                "Tools":"' . lang('Tools') . '",
                "Preview":"' . lang('Preview') . '",
                "Code Editor":"' . lang('Code Editor') . '",
                "Code":"' . lang('Code') . '",
                "maximise":"' . lang('maximise') . '",
                "minimise":"' . lang('minimise') . '",
                "Close Page Designer":"' . lang('Close Page Designer') . '",
                "Refresh Page":"' . lang('Refresh Page') . '",
                "No Page":"' . lang('No Page') . '",
                "Layout":"' . lang('Layout') . '",
                "Set in Page Properties":"' . lang('Set in Page Properties') . '",
                "no page style":"' . lang('no page style') . '",
                "no theme":"' . lang('no theme') . '",
                "Sorry, please save or cancel your changes first.":"' . lang('Sorry, please save or cancel your changes first.') . '",
                "Page Style":"' . lang('Page Style') . '",
                "Design File":"' . lang('Design File') . '",
                "Designer Region":"' . lang('Designer Region') . '",
                "Dynamic Region":"' . lang('Dynamic Region') . '",
                "Save":"' . lang('Save') . '",
                "Saving":"' . lang('Saving') . '",
                "Saved":"' . lang('Saved') . '",
                "Undo Changes":"' . lang('Undo Changes') . '",
                "read-only":"' . lang('read-only') . '",
                "System Page Style":"' . lang('System Page Style') . '",
                "System Theme":"' . lang('System Theme') . '",
                "Search Results":"' . lang('Search Results') . '",
                "Search all designs components":"' . lang('Search all designs components') . '",
                "No Search Result":"' . lang('No Search Result') . '",
                "Search":"' . lang('Search') . '",
                "Clear":"' . lang('Clear') . '",
                "WARNING: If you leave this page, then your unsaved changes will be lost.":"' . lang('WARNING: If you leave this page, then your unsaved changes will be lost.') . '",
                "Reset panel layout if there is bug":"' . lang('Reset panel layout if there is bug') . '",
                "Create Page Region":"' . lang(array('string'=>'Create {var:1}','vars'=>lang('Page Region'))) . '",
                "Create Dynamic Region":"' . lang(array('string'=>'Create {var:1}','vars'=>lang('Dynamic Region'))) . '",
                "Create Designer Region":"' . lang(array('string'=>'Create {var:1}','vars'=>lang('Designer Region'))) . '",
                "Dynamic Region Name":"' . lang(array('string'=>'{var:1} Name','vars'=>lang('Dynamic Region'))) . '",
                "Designer Region Name":"' . lang(array('string'=>'{var:1} Name','vars'=>lang('Designer Region'))) . '",
                "Shared HTML Content to appear on associated Pages":"' . lang('Shared HTML Content to appear on associated Pages') . '",
                "Shared PHP Content to appear on associated Pages":"' . lang('Shared PHP Content to appear on associated Pages') . '",
                "HTML Code Snippet":"' . lang('HTML Code Snippet') . '",
                "PHP Code Snippet":"' . lang('PHP Code Snippet') . '",
                "Creating":"' . lang('Creating') . '",
                "Create & Add":"' . lang('Create & Add') . '",
                "Please choose a region type to create":"' . lang('Please choose a region type to create') . '",

             
            }
        })
            
    </script>' . output_footer_secure();