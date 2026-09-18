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
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();



// Get page info via the per-request cached helper. output_header() will
// trigger output_toolbar() further down, which reuses the same cached row
// instead of issuing a duplicate page+folder JOIN query.
$row = get_toolbar_page_row((int) ($_GET['page_id'] ?? 0));

if (!$row) {
    output_error(lang('Sorry, the page could not be found.'), 404);
}

$page_id = $row['page_id'];
$page_name = $row['page_name'];
$page_folder = $row['page_folder'];
$page_home = $row['page_home'];
$page_search = $row['page_search'];
$page_search_keywords = $row['page_search_keywords'];
$page_style = $row['page_style'];
$mobile_style_id = $row['mobile_style_id'];
$page_type = $row['page_type'];
$comments = $row['comments'];
$comments_automatic_publish = $row['comments_automatic_publish'];
$comments_administrator_email_to_email_address = $row['comments_administrator_email_to_email_address'];
$seo_score = $row['seo_score'];
$sitemap = $row['sitemap'];
$folder_archived = $row['folder_archived'];

// The bar describes a page of the site, so it is shown under the same test
// the site applies before serving that page: whoever may not view the page's
// folder gets the access error here as well, not the page's name and actions.
if (check_view_access($page_folder) == false) {
    output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

if ($page_home == 'yes') {
    $page_name = '<span class="bi bi-house text-success ms-2" title="' . lang('Homepage') . '"> ' . $page_name . '</span>';
} else {
    $page_name = '<span class="bi bi-window pages-color ms-2" title="' . lang('Page') . '"> ' . $page_name . '</span>';
}

// Unlike every other screen, this heading is a record rather than a screen
// name, so it links to where that page is edited - the visual editor for its
// own pages, the properties screen for the rest (pg_page_edit_url). The link
// is offered under the same gate as the page's properties: the toolbar is also
// drawn for a user who only manages calendars or forms, and for them this
// would lead to an access error.
//
// target=_parent because this shell runs inside the toolbar iframe;
// no-popover so the title stays a plain browser tooltip - the heading's own
// popover already names the page, and two of them stacked is noise.
if (pg_page_properties_visible($page_folder, $user)) {

    $page_edit_url = pg_page_edit_url_by_id($page_id);

    if ($page_edit_url !== '') {
        $page_edit_url .= (strpos($page_edit_url, '?') === false ? '?' : '&')
                        . 'send_to=' . urlencode(($_GET['send_to'] ?? ''));

        $page_name =
            '<a class="no-popover pg-navhead-link" target="_parent" href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $page_edit_url) . '"
                title="' . h(lang('Edit Page')) . '">' . $page_name . '</a>';
    }
}

// The very panel the page carries behind the SEO ring, drawn a second time in
// here - score, page description, actions and all. Not a second renderer and
// not a second id either: the toolbar is its own document, so the same markup
// and the same rules can be reused whole, and the two cannot drift apart.
//
// The ring is unreachable while this bar is open, and this is the surface a
// hand reaches for. It stands open and has nothing to close it with; the bar
// is only on screen when somebody asked for it.
require_once(dirname(__FILE__) . '/seo.php');

// send_to has to be handed over: the block works it out from REQUEST_URL when
// nobody says otherwise, and in here that is toolbar.php\'s own address rather
// than the page it describes. Every screen opened from the panel would then
// come back to the bare bar - and "Open Page Designer" would open the designer
// on it.
$pg_toolbar_panel = pg_seo_page_toolbar($page_id, $_GET['style_id'] ?? 0, $user, array(
    'close'   => false,
    'hidden'  => false,
    'send_to' => ($_GET['send_to'] ?? ''),
));

// The same actions again as a single row of icons, for when the column is
// more than is wanted. Both are drawn and a class on the document decides
// which is on screen; the choice is remembered. The score goes with it because
// the button that switches back to the panel is what carries it.
$pg_toolbar_bar = pg_page_action_bar(
    $page_id,
    $_GET['style_id'] ?? 0,
    $user,
    ($_GET['send_to'] ?? ''),
    $pg_toolbar_panel['score']);

$pg_toolbar_panel_output = '';

if ($pg_toolbar_panel['panel'] !== '') {

    $pg_toolbar_panel_output = $pg_toolbar_panel['panel'] . $pg_toolbar_bar['html'] . '
        <style>
            ' . $pg_toolbar_panel['css'] . $pg_toolbar_bar['css'] . '

            /* Everything above is the panel as the page draws it. What follows
               is the only thing that differs in here: what it is coloured with
               and where it hangs. Written after, so that these win on equal
               specificity. */

            /* Out on the site the panel floats over somebody else\'s stylesheet
               and has to bring its own palette. In here it hangs off the admin
               bar, in a document that loads the admin stylesheet - so it is
               painted with the bar\'s own tokens and the two cannot drift
               apart. The dark selector is named alongside because the panel
               script still puts that class on (it is what dresses the copy out
               on the site) and id+class would otherwise outrank a plain id. */
            #software_seo_panel,
            #software_seo_panel.pg_seo_tb_dark{
                --pg-sp-bg: rgb(var(--bs-navbar-bg-rgb));
                --pg-sp-fg: var(--bs-body-color);
                --pg-sp-muted: var(--bs-secondary-color);
                --pg-sp-faint: var(--bs-tertiary-color);
                --pg-sp-line: var(--bs-border-color-translucent);
                --pg-sp-line-soft: var(--bs-border-color-translucent);
                --pg-sp-border: var(--bs-border-color);
                --pg-sp-hover: var(--bs-tertiary-bg);
                --pg-sp-link: var(--bs-link-color);
                --pg-sp-track: var(--bs-secondary-bg);
            }

            #software_seo_panel{
                --pg-tbp-w: 330px;

                /* Its own column, from under the bar to the foot of the
                   screen. Anchored top and bottom rather than given a height:
                   the bar is the only thing above it and the viewport is the
                   only thing below, so neither needs calculating.
                   The pixel it sits high swallows the bar\'s hairline, the way
                   the sidebar does against the navbar. */
                top: var(--pg-navbar-h, 50px);
                margin-top: -1px;
                bottom: 0;
                width: var(--pg-tbp-w);
                max-width: 100vw;
                max-height: none;
                border-bottom: 0;
                border-bottom-left-radius: 0;
                box-shadow: none;

                /* Zero, and the order still comes out right - because of where
                   this sits rather than what the number is.

                   The script below moves the column inside #header. That
                   element is position:sticky, and sticky alone makes a
                   stacking context whatever its z-index says, so from outside
                   it the bar and the menus that drop out of it are one sealed
                   layer that no number can get between - which is why a column
                   appended after it covered the account menu and the
                   notifications. Inside the seal the ordering is ordinary
                   again: a positioned child paints over the bar\'s background
                   and over the inset hairline it draws along its bottom edge,
                   which is what the pixel this column sits high is for, while
                   the menus keep their own 1000 and come down over the column
                   where they belong. */
                z-index: 0;
            }

            /* The corner curves OUT of the bar, not into the panel: the two
               have to read as one surface. Same trick the sidebar uses against
               the navbar (.software-sidebar::after) - a box one corner square
               set beside the join, filled in the panel colour, with the arc cut
               from a hard radial stop, which is the one way to get a concave
               hairline out of a single box.
               Fixed rather than absolute because the panel scrolls its contents
               and this belongs to the join, not to what is written below it. */
            #software_seo_panel::after{
                content: "";
                position: fixed;
                top: calc(var(--pg-navbar-h, 50px) - 1px);
                right: calc(var(--pg-tbp-w) - var(--bs-border-width, 1px));
                width: var(--pg-corner, 16px);
                height: var(--pg-corner, 16px);
                pointer-events: none;
                background: radial-gradient(
                    circle at 0% 100%,
                    transparent calc(var(--pg-corner, 16px) - var(--bs-border-width, 1px)),
                    var(--pg-sp-border) calc(var(--pg-corner, 16px) - var(--bs-border-width, 1px)),
                    var(--pg-sp-border) var(--pg-corner, 16px),
                    var(--pg-sp-bg) var(--pg-corner, 16px)
                );
            }
            /* Two views of one thing, and the document says which. Written
               on the root element rather than on either of them so the answer
               is there before the browser reaches the markup below.

               The strip is what an unanswered question gets: the bar is
               opened to reach a tool, and a column that covers a third of the
               page being edited is a lot to put in front of somebody who
               asked for a button. The panel is one click away and stays once
               it is picked. */
            #software_seo_panel{ display: none; }
            html.pg-pv-panel #software_seo_panel{ display: block; }
            html.pg-pv-panel #pg_page_bar{ display: none; }

            /* The way back, put in the panel head beside the title by the
               script below. Shaped like the close button that is deliberately
               not drawn in here - same corner, same weight, different job. */
            #software_seo_panel .pg_seo_tb_view{
                flex: 0 0 auto;
                border: 0;
                background: none;
                cursor: pointer;
                padding: 0 2px;
                font-size: 14px;
                line-height: 1;
                color: var(--pg-sp-muted);
            }
            #software_seo_panel .pg_seo_tb_view:hover{ color: var(--pg-sp-fg); }
        </style>
        <script>
            // Into the bar\'s own stacking context, so the menus that open from
            // the bar keep coming down over these. Run here rather than on
            // ready: the bar is already in the document at this point, so
            // neither one paints in the wrong layer even for a frame.
            (function () {
                var bar = document.getElementById("header")
                       || document.querySelector(".software-navbar");
                var panel = document.getElementById("software_seo_panel");
                var strip = document.getElementById("pg_page_bar");
                var root = document.documentElement;
                var key = "pinegrap toolbar page view";

                if (bar) {
                    if (panel) { bar.appendChild(panel); }
                    if (strip) { bar.appendChild(strip); }
                }

                if (!panel) {
                    return;
                }

                // No strip means nothing to switch to - it stands down for the
                // length of a theme preview, whose controls are pick lists
                // rather than buttons and only fit in the column. The panel is
                // then the only view there is, so it is the one shown, and the
                // stored choice is left alone for when the preview ends.
                if (!strip) {
                    root.classList.add("pg-pv-panel");
                    return;
                }

                function apply(view) {
                    root.classList.toggle("pg-pv-panel", view === "panel");
                }

                var stored = null;

                try {
                    stored = localStorage.getItem(key);
                } catch (error) {
                    stored = null;
                }

                // Nothing stored means the strip: see the rules above.
                apply(stored || "bar");

                var head = panel.querySelector(".pg_seo_tb_top");

                if (head) {
                    var button = document.createElement("button");

                    button.type = "button";
                    button.className = "pg_seo_tb_view no-popover";
                    button.setAttribute("data-pg-page-view", "bar");
                    button.title = ' . json_encode(lang('Button Bar')) . ';
                    button.setAttribute("aria-label", button.title);
                    button.innerHTML = \'<i class="bi bi-chevron-double-up" aria-hidden="true"></i>\';
                    head.appendChild(button);
                }

                document.addEventListener("click", function (event) {
                    var trigger = event.target.closest("[data-pg-page-view]");

                    if (!trigger) {
                        return;
                    }

                    var view = trigger.getAttribute("data-pg-page-view");

                    apply(view);

                    try {
                        localStorage.setItem(key, view);
                    } catch (error) {
                    }
                });
            })();
        </script>';
}

print pg_page_shell(
    array(
        'toolbar' => true,
        'title' => lang('Toolbar'),
        'extra classes' => 'page toolbar',
        'icon' => 'page',
        'heading' => $page_name,
    )
) . $pg_toolbar_panel_output . '
        <script>
            var loaded = false;
            $(window).on("load", function() {
                loaded = true;
            });

            jQuery("body.toolbar main").dblclick(function(event){
                parent.document.getElementById(\'software_fullscreen_toggle\').click();
            });

            // The page panel has no switch and no memory: this bar is only on
            // screen when somebody has just asked for it, and this is what
            // they came for.
        </script>';
?>
