<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Visual Page Editor home: start a design (blank / HTML import / template)
 * or open one of the existing designs. Designs are style rows made by the
 * visual editor; the HTML-template styles keep their own list in
 * view_styles.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
include_once('includes/designer_access.php');
// The editor is no longer designer-only. A manager or a user with content
// rights gets in at `content` level: text anywhere they may edit, plus the
// blocks an administrator marked as editable areas. What they may actually
// change is decided per node (client) and per save (server) — see
// includes/designer_access.php.
if (pg_designer_access($user) === PG_DESIGNER_ACCESS_NONE) {
    validate_area_access($user, 'designer');
}

include_once('liveform.class.php');
include_once('includes/designer_screen.php');

$liveform = new liveform('view_system_styles');

pg_designer_start_screen(array(
    'liveform'   => $liveform,
    'user'       => $user,
    'from_pages' => (isset($_GET['from']) && $_GET['from'] === 'pages'),
));

$liveform->remove_form();
?>
