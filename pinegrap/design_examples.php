<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design examples: every recipe of the panel's look drawn live in both
 * themes, with the classes that draw it and what the look does not cover
 * yet. A screen copies its markup from here (includes/fn/look_examples.php).
 * For the Designer role and above.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/look_examples.php');

$not_done = '';

foreach (pg_look_examples_not_done() as $group) {
    $not_done .= '<div class="pg-look-grow"><h3 class="pg-title-status">' . h($group['title']) . '</h3><ul class="pg-text-2 pg-look-list">';

    foreach ($group['items'] as $item) {
        $not_done .= '<li>' . h($item) . '</li>';
    }

    $not_done .= '</ul></div>';
}

echo
pg_page_shell(array(
        'title'               => lang('Design examples'),
        'extra classes'       => 'pg-look-examples',
        'icon'                => 'setting',
        'heading'             => lang('Design examples'),
        'heading_description' => lang('Every part of the panel\'s look, live in both themes, with the classes that draw it.'),
        'breadcrumb'          => array(
            array('label' => lang('System tools'), 'url' => 'system_tools.php'),
            array('label' => lang('Design examples')),
        ),
    )) . '
<main id="content" class="container-fluid">
    <div class="pg-card pg-look-pad pg-look-intro">
        <p class="pg-text-2">' . h(lang('A screen is built from these parts; its own classes only place them. A part that is missing here is drawn first and asked about before it is built.')) . '</p>
        <div class="pg-look-columns">' . $not_done . '</div>
    </div>
    <div class="pg-look-panes">
        ' . pg_look_examples_pane('light') . '
        ' . pg_look_examples_pane('dark') . '
    </div>
</main>
' .
output_footer();
