<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bakery", its baking classes: the staff page that adds a
 * class (the Baking Classes form, a collection, with the sample classes
 * written in when it is made), the list of every class with its level
 * filter, the class's own page and the list the home page places.
 * Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A class is a record of the Baking Classes form. Its level is a pick list
 * whose answers are the words parts.php gives, so a link with ?level=
 * filters the list by the words a visitor sees. Length, places and price
 * are words as the bakery writes them ("180 min", "8 places"), the photo
 * an image field kept in the public folder. Featured is the bakery's
 * choice (office use only): the home page shows the featured classes.
 *
 * A place is asked for with the class place request (a later part of the
 * template): the class page's button opens it with the class chosen
 * (?class=, the renderer's record-value page link).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$class_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$bk_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a class for the home.
$bk_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$bk_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list's settings: the Baking Classes form, the class page, A to Z, and
// the words shown when nothing matches.
$class_list_config = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:class_new}}',
        'detail_page_id'      => '{{tab:class}}',
        'order_by_field'      => 'name',
        'order_by_direction'  => 'ASC',
        'empty_message'       => $empty,
    ), $more);
};
// The cards of a list, in rows of four.
$class_grid = function () use ($widget_root, $row, $loop, $class_card) {
    return $widget_root(array($row(array($loop(array($class_card()))), '4')));
};

// The links above the list that narrow it: one per level, each opening the
// class list with the filter set.
$class_filter_bar = function () use ($el, $link, $span, $query, $level_titles) {
    $children = array($span(lang('Level'), 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:classes}}', 'btn btn-sm btn-outline-secondary rounded-pill');
    foreach ($level_titles as $v) {
        $children[] = $link($v, '{{page:classes}}?' . $query(array('level' => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every class, narrowed by level from the address, with how a class day
// goes under it.
$class_pages[] = array(
    'key'              => 'classes',
    'name'             => lang('baking-classes'),
    'folder'           => 'public',
    'title'            => lang('Baking Classes'),
    'meta_description' => lang('Sourdough, croissants, cake decorating and classes for children, taught by our bakers at the long table in the bakehouse.'),
    'tree'             => $page(array(
        $page_header(lang('Baking Classes'), lang('Learn to bake the way we do, in small groups at the long table. Choose a level to narrow the list.')),
        $el('section', 'py-5', lang('Baking Classes'), array(
            $container(array($class_filter_bar(), $widget('classes_list'))),
        ), $searchable('bky-classes', lang('workshop, course, lesson, learn to bake, sourdough class'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How a class day goes'), 'pbk-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Ask for your place'), lang('From the class you choose; we confirm the date and your place within a day.')),
                            $step(2, lang('Come with an apron'), lang('Everything else is at the bench: flour, tools, recipes and coffee.')),
                            $step(3, lang('Take it all home'), lang('What you bake, the recipes and, after the sourdough class, a starter of your own.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 bg-primary-subtle', lang('Text'), array(
                            $heading('h2', lang('A class as a gift'), 'pbk-display h4 mb-2'),
                            $para(lang('Ask for a place in someone else\'s name and say so in the request; we send a card to give with it.'), 'mb-0'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('bky-classes-how', lang('what to bring, apron, gift voucher, age'))),
    )),
);

// One class: the photo, the level, the name, the facts, the words and the
// way to ask for a place.
$class_pages[] = array(
    'key'              => 'class',
    'name'             => lang('baking-class'),
    'folder'           => 'public',
    'title'            => lang('Baking Class'),
    'meta_description' => lang('A baking class at the bakery: what you bake, who it is for, how long it lasts and how to ask for a place.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('class_detail'),
    )),
);

// The staff page that adds a class. The form is the Baking Classes
// collection; the sample classes are written in when the form is made, so
// the lists do not open empty (pg_cf_seed_sample_records()).
$class_details = array(
    'sourdough' => lang('You feed and read a starter, mix and fold a dough, shape a country loaf and bake it in our oven. You take home your loaf, a jar of starter and the method on one page.'),
    'croissant' => lang('You laminate a butter dough over the morning, then roll, cut and shape croissants and pains au chocolat, and bake a tray of each. You take home a dozen pastries and a block of dough for your own freezer.'),
    'cakes'     => lang('You level and fill a sponge, crumb-coat and ice it smooth, pipe a border and finish it with fresh flowers. You take home the cake in a box and the recipes for the sponge and the buttercream.'),
    'kids'      => lang('Children roll, cut and bake biscuits, knot cinnamon buns and decorate everything with icing. They take home a box of what they baked and a little recipe book.'),
);
$sample_classes = array();
foreach ($baking_classes as $key => $c) {
    $sample_classes[] = array(
        'name'     => $c['name'],
        'level'    => $levels[$c['level']],
        'length'   => sprintf(lang('%d min'), $c['minutes']),
        'seats'    => sprintf(lang('%d places'), $c['seats']),
        'price'    => $money($c['price']),
        'teacher'  => $c['teacher'],
        'summary'  => $c['summary'],
        'details'  => $class_details[$key],
        'photo'    => $photo('class-' . $key, 1200, 900),
        'featured' => $bk_featured_choices[0],
    );
}
$class_pages[] = array(
    'key'     => 'class_new',
    'name'    => lang('new-baking-class'),
    'folder'  => 'private',
    'title'   => lang('New Baking Class'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Baking Classes'),
        'collection'           => 1,
        'confirmation_message' => lang('The baking class is published.'),
        'confirmation_page_id' => '{{tab:classes}}',
        'sample_records'       => $sample_classes,
        // The photo is the class's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Baking Class'), lang('Add the class with a photo and a few words; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('class_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$class_widgets = array(

    // The staff's Baking Classes form: one class.
    'class_form' => array(
        'page'   => 'class_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The class')),
            $field('bky-class-name', lang('Name'), pg_cf_with_role($input('bky-class-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('bky-class-level', lang('Level'), pg_cf_with_role($select('bky-class-level', 'level', lang('Choose a level…'), $level_titles), 'category'))), '6'),
                $col(array($field('bky-class-teacher', lang('Taught by'), $input('bky-class-teacher', 'text', 'teacher', 'off'))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('bky-class-length', lang('Length'), $input('bky-class-length', 'text', 'length', 'off'), 'mb-3',
                    lang('As visitors read it, for example 180 min.'))), '4'),
                $col(array($field('bky-class-seats', lang('Places at the Table'), $input('bky-class-seats', 'text', 'seats', 'off'), 'mb-3',
                    lang('For example 8 places.'))), '4'),
                $col(array($field('bky-class-price', lang('Price'), $input('bky-class-price', 'text', 'price', 'off'), 'mb-3',
                    lang('The price of a place, with the currency.'))), '4'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('bky-class-summary', lang('Summary'), pg_cf_with_role($textarea('bky-class-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards of the lists.')),
            $field('bky-class-details', lang('What you bake and take home'), $textarea('bky-class-details', 'details', 5)),
            $form_step(3, lang('Photo')),
            $field('bky-class-photo', lang('Photo'), pg_cf_with_role($file('bky-class-photo', 'photo', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A landscape photo works best; it is also the share image.')),
            $field('bky-class-featured', lang('Featured'), $select('bky-class-featured', 'featured', '', $bk_featured_choices, false, $bk_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured class is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every class, filtered by the address (level), with a search over the
    // words of the classes.
    'classes_list' => array(
        'page'   => 'classes',
        'slug'   => lang('form-list'),
        'config' => $class_list_config(lang('No class at this level just now. Choose another, or write to us about the next dates.'), array(
            'items_per_page' => 12,
            'filters'        => array($bk_filter('level', 'is equal to', '', 'level')),
            'search_enabled' => true,
            'search_fields'  => array('name', 'level', 'teacher', 'summary', 'details'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the classes: sourdough, croissants, children...'),
        )),
        'tree'   => $class_grid(),
    ),

    // The home page's featured classes, four at most.
    'home_classes' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $class_list_config(lang('No featured baking classes yet.'), array(
            'items_per_page' => 4,
            'max_results'    => 4,
            'filters'        => array($bk_filter('featured', 'is equal to', $bk_featured_choices[0])),
        )),
        'tree'   => $class_grid(),
    ),
);

// One class: the photo, the level, the name, the facts, the words and the
// buttons that ask for a place or show the classes of the same level.
$class_sample = $baking_classes['sourdough'];
$class_fact = function ($icon_name, $label, $text, $token) use ($el, $icon, $span, $bind) {
    return $el('li', 'd-flex align-items-center gap-2 py-2 border-bottom', $label, array(
        $icon($icon_name, 'text-primary'),
        $span($label, 'text-body-secondary', lang('Label')),
        $span($text, 'pbk-price fw-semibold ms-auto text-end', lang('Value'), $bind($token)),
    ));
};
$class_widgets['class_detail'] = array(
    'page'   => 'class',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:class_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This baking class could not be found. It may have ended, or the page was renamed.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-search', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Baking class not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This baking class could not be found. It may have ended, or the page was renamed.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the baking classes'), '{{page:classes}}', 'btn btn-primary rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Baking Class'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All baking classes'), '', lang('Link Text')),
                    ), array('href' => '{{page:classes}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo', 'name', 'rounded-4 object-fit-cover', 'ratio ratio-4x3 mb-4',
                            $photo('class-sourdough', 1200, 900), lang('Photo')),
                        $span($levels[$class_sample['level']], 'badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Level'), $bind('level')),
                        $heading('h1', $class_sample['name'], 'pbk-display display-6 mb-3', $bind('name')),
                        $para($class_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $el('div', 'pg-hide-if-empty mb-4', lang('What you bake and take home'), array(
                            $heading('h2', lang('What you bake and take home'), 'pbk-display h5 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $class_details['sourdough']), $bind('details'))),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-4', lang('Booking Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('ul', 'list-unstyled mb-4', lang('Baking Class Facts'), array(
                                    $class_fact('bi-stopwatch', lang('Length'), sprintf(lang('%d min'), $class_sample['minutes']), 'length'),
                                    $class_fact('bi-people', lang('Places at the Table'), sprintf(lang('%d places'), $class_sample['seats']), 'seats'),
                                    $class_fact('bi-person', lang('Taught by'), $class_sample['teacher'], 'teacher'),
                                    $class_fact('bi-tag', lang('Price'), $money($class_sample['price']), 'price'),
                                )),
                                // Opens the place request with this class
                                // chosen (?class=).
                                $link(lang('Ask for a place'), '#', 'btn btn-primary rounded-pill w-100 mb-2', array(),
                                    $bk_page_link('class_booking', array('class' => 'name'))),
                                // Opens the list on this class's level (?level=).
                                $link(lang('More classes at this level'), '#', 'btn btn-outline-secondary rounded-pill w-100', array(),
                                    $bk_page_link('classes', array('level' => 'level'))),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $class_pages, 'widgets' => $class_widgets);
