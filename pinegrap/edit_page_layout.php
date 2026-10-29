<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The custom layout of a page whose style is not a visual design (a classic
 * style, a style on a PHP template file, an older cell-based system style),
 * edited on its own: the PHP view template render_layout() includes for the
 * page (includes/fn/layouts.php). A page of a visual design edits it in the
 * designer's layout tab and is sent there (pg_layout_edit_url()).
 *
 * The screen draws the editor only; the text is read, regenerated and saved
 * through api.php's layout_code action, the one the designer's tab uses,
 * which checks the role, the form token and the page's custom layout again.
 * Designers and administrators, as the classic layout editor; read only on
 * a hosted site, which draws the generated layout and ignores the file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

$page_id = is_scalar($_GET['id'] ?? null) ? (int) $_GET['id'] : 0;
$page = ($page_id > 0)
    ? db_item(
        "SELECT
            page_id AS id,
            page_name AS name,
            page_type AS type,
            page_style AS style_id,
            layout_type
        FROM page
        WHERE page_id = '" . $page_id . "'")
    : false;

if (!$page) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// Only a page that draws a custom layout has one to edit.
if (($page['layout_type'] !== 'custom') || !check_if_page_type_supports_layout($page['type'])) {
    output_error(lang('This page does not use a custom layout.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// A page of a visual design edits its layout in the designer.
$target = pg_layout_edit_url(
    $page['id'],
    $page['style_id'],
    pg_page_is_visual_design(array('page_style' => $page['style_id'])),
    USER_ROLE,
    pg_hosted());

if (strpos($target, 'edit_page_layout.php') !== 0) {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/' . $target);
    exit();
}

$read_only = pg_hosted();
$back_url = pg_safe_back_url(isset($_GET['send_to']) ? $_GET['send_to'] : '', 'edit_page.php?id=' . (int) $page['id']);

$output_read_only = '';

if ($read_only) {
    $output_read_only = '<div class="alert theme-info">' . h(lang('Custom layouts are not available on this site.')) . ' ' . h(lang('The page draws the generated layout, shown here read only.')) . '</div>';
}

$output_toolbar = '';

if (!$read_only) {
    $output_toolbar = '
    <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
        <button type="button" id="pg_layout_save" class="btn btn-sm btn-solid theme-primary rounded-pill px-5" disabled><i class="bi bi-floppy me-1" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Save')) . '</span></button>
        <button type="button" id="pg_layout_regenerate" class="btn btn-sm btn-text theme-secondary" disabled><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Regenerate layout')) . '</span></button>
    </nav>';
}

$editor = array(
    'pageId' => (int) $page['id'],
    'token' => (string) ($_SESSION['software']['token'] ?? ''),
    'apiUrl' => PATH . SOFTWARE_DIRECTORY . '/api.php',
    'readOnly' => $read_only,
    'labels' => array(
        'saved' => lang('The layout has been saved.'),
        'generated' => lang('The layout is the generated one again: it follows the page\'s fields.'),
        'regenerate' => lang('Replace the layout in the editor with a newly generated one? Nothing is saved until you save the file.'),
        'network' => lang('Network error'),
        'unsaved' => lang('Unsaved changes'),
    ),
);

print
    pg_page_shell(array(
        'title' => h($page['name']) . ' | ' . lang('Edit Layout'),
        'icon' => 'design',
        'heading' => lang('Edit Layout'),
        'heading_description' => lang('The PHP view template that draws this page\'s system region.'),
        'cancel' => array('enable' => 'true', 'url' => $back_url),
        'breadcrumb' => array(
            array('label' => lang('All My Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
            array('label' => $page['name'], 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_page.php?id=' . (int) $page['id']),
            array('label' => lang('Edit Layout')),
        ),
    )) . '
<main id="content" class="container-fluid">' . $output_toolbar . '
    ' . $output_read_only . '
    <div class="card my-7">
        <div class="card-body">
            <label class="form-label" for="pg_layout_code">' . h(lang('Layout (PHP)')) . '</label>
            <textarea id="pg_layout_code" rows="25" cols="60" wrap="off"' . ($read_only ? ' readonly' : '') . '></textarea>
            ' . pg_code_editor_attach(array('id' => 'pg_layout_code', 'code_type' => 'php', 'readonly' => $read_only, 'height' => '70vh')) . '
        </div>
    </div>
</main>
<script>
(function () {
    var D = ' . pg_code_editor_json($editor) . ';
    var L = D.labels;
    var field = document.getElementById("pg_layout_code");
    var save = document.getElementById("pg_layout_save");
    var regenerate = document.getElementById("pg_layout_regenerate");
    var saved = null;

    function editor() { return (window.pgCodeEditors && window.pgCodeEditors[field.id]) || null; }
    function value() { var ed = editor(); return ed ? ed.getValue() : field.value; }
    function show(text) {
        field.value = text;
        var ed = editor();
        if (ed) { ed.setValue(text); }
    }
    function say(message, variant) {
        if (window.pgToast) { window.pgToast({ message: message, variant: variant }); }
        else if (variant === "danger") { window.alert(message); }
    }
    function busy(on) {
        if (save) { save.disabled = on; }
        if (regenerate) { regenerate.disabled = on; }
    }
    function post(sub, extra) {
        var body = { action: "layout_code", sub_action: sub, page_id: D.pageId, token: D.token };
        if (extra) { Object.keys(extra).forEach(function (k) { body[k] = extra[k]; }); }
        return fetch(D.apiUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data && data.status === "success") { return data; }
            throw new Error((data && data.message) || L.network);
        }, function () { throw new Error(L.network); });
    }

    // Warn before leaving with text that differs from the last read or save.
    window.addEventListener("beforeunload", function (e) {
        if (D.readOnly || (saved === null) || (value() === saved)) { return; }
        e.preventDefault();
        e.returnValue = L.unsaved;
    });

    // The editor arrives after the text when its bundle is still loading; it
    // takes the textarea value then, so only an editor already there is set.
    post("read").then(function (data) {
        saved = String(data.content || "");
        show(saved);
        busy(false);
    }, function (err) { say(err.message, "danger"); });

    if (save) {
        save.addEventListener("click", function () {
            var text = value();
            busy(true);
            post("save", { content: text }).then(function (data) {
                saved = text;
                say(data.modified ? L.saved : L.generated, "success");
                busy(false);
            }, function (err) {
                say(err.message, "danger");
                busy(false);
            });
        });
    }

    if (regenerate) {
        regenerate.addEventListener("click", function () {
            var go = function () {
                busy(true);
                post("regenerate").then(function (data) {
                    show(String(data.content || ""));
                    busy(false);
                }, function (err) {
                    say(err.message, "danger");
                    busy(false);
                });
            };
            if (window.pgConfirm) {
                window.pgConfirm({ message: L.regenerate, variant: "warning" }).then(function (ok) { if (ok) { go(); } });
            } else if (window.confirm(L.regenerate)) {
                go();
            }
        });
    }
})();
</script>' . output_footer();
