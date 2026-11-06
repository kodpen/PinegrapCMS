<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Photography Studio", its galleries and the online
 * booking of a studio mini session: the staff page that adds a gallery
 * (the Galleries form, a collection, with the sample galleries written in
 * when it is made), the list of every gallery with its category filter,
 * the gallery's own page, the list the home page places, and the page
 * that books a free studio time with the booking widget. Included after
 * parts.php in the same scope; returns array('pages' => ..., 'widgets' =>
 * ...).
 *
 * A gallery is a record of the Galleries form. Its category is a pick list
 * whose answers are the words parts.php gives, so a link with ?category=
 * filters the list by the words a visitor sees. The cover and the six
 * photos are image fields kept in the public folder. Featured is the
 * studio's own choice (office use only): the home page shows the featured
 * galleries.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$gallery_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$gallery_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a gallery for the home.
$gallery_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$gallery_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list's settings: the Galleries form, the gallery page, the newest
// gallery first.
$gallery_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:gallery_new}}',
        'detail_page_id'      => '{{tab:gallery}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No gallery in this category yet. Try another category, or ask us about the shoot you have in mind.'),
    ), $more);
};
// The cards of a list, in rows of three.
$gallery_grid = function () use ($widget_root, $row, $loop, $gallery_card) {
    return $widget_root(array($row(array($loop(array($gallery_card()))), '4')));
};

// The links above the list that narrow it: one per category, each opening
// the gallery list with the filter set.
$gallery_filter_bar = function () use ($el, $link, $span, $query, $category_titles) {
    $children = array($span(lang('Category'), 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:galleries}}', 'btn btn-sm btn-outline-secondary rounded-pill');
    foreach ($category_titles as $v) {
        $children[] = $link($v, '{{page:galleries}}?' . $query(array('category' => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'mb-4', lang('Filters'), array(
        $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('Category'), $children),
    ), array('_attrs' => array(array('name' => 'aria-label', 'value' => lang('Filters')))));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every gallery, narrowed by category from the address.
$gallery_pages[] = array(
    'key'              => 'galleries',
    'name'             => lang('galleries'),
    'folder'           => 'public',
    'title'            => lang('Galleries'),
    'meta_description' => lang('Galleries of weddings, portraits, families, newborns, events and brands photographed by the studio.'),
    'tree'             => $page(array(
        $page_header(lang('Galleries'), lang('A few of the stories we have photographed. Choose a category to narrow the list.')),
        $el('section', 'py-5', lang('Galleries'), array(
            $container(array($gallery_filter_bar(), $widget('galleries_list'))),
        )),
    )),
);

// One gallery: the cover, the story and the photos.
$gallery_pages[] = array(
    'key'              => 'gallery',
    'name'             => lang('gallery'),
    'folder'           => 'public',
    'title'            => lang('Gallery'),
    'meta_description' => lang('A gallery of the studio: the story of the shoot and its photos.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('gallery_detail'),
    )),
);

// The staff page that adds a gallery. The form is the Galleries
// collection; the sample galleries are written in when the form is made,
// so the lists do not open empty (pg_cf_seed_sample_records()). Each
// sample photo is the picture its card and grid draw on the canvas.
$sample_galleries = array();
foreach ($galleries as $key => $g) {
    $record = array(
        'title'    => $g['title'],
        'category' => $categories[$g['category']]['title'],
        'location' => $g['location'],
        'summary'  => $g['summary'],
        'story'    => $g['story'],
        'cover'    => $photo('cover-' . $key, 1600, 1200),
    );
    for ($n = 1; $n <= 6; $n++) $record['photo_' . $n] = $photo($key . '-' . $n, 1600, 1200);
    $record['featured'] = $g['featured'] ? $gallery_featured_choices[0] : $gallery_featured_choices[1];
    $sample_galleries[] = $record;
}
$gallery_pages[] = array(
    'key'     => 'gallery_new',
    'name'    => lang('new-gallery'),
    'folder'  => 'private',
    'title'   => lang('New Gallery'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Galleries'),
        'collection'           => 1,
        'confirmation_message' => lang('The gallery is published.'),
        'confirmation_page_id' => '{{tab:galleries}}',
        'sample_records'       => $sample_galleries,
        // The cover is the gallery's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Gallery'), lang('Add the gallery with its cover and photos; it appears in the list as soon as you send it. Mark it featured from the gallery\'s Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('gallery_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The online booking of a studio mini session: the booking widget's own
// layout (services, photographer, day and time, details) beside the way
// to ask for any other shoot and the studio's hours.
$gallery_pages[] = array(
    'key'              => 'book_online',
    'name'             => lang('book-online'),
    'folder'           => 'public',
    'title'            => lang('Book Online'),
    'meta_description' => lang('Book a studio mini session at a free time: choose the day and the hour and confirm in a minute.'),
    'tree'             => $page(array(
        $page_header(lang('Book a Studio Mini Session'), sprintf(lang('%1$s, %2$s. Choose one of the free times; it is yours as soon as you confirm.'), $packages['mini']['length'], $images_text('mini'))),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Booking Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('booking_slots'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Ask First'), array(
                            $heading('h2', lang('Another kind of shoot?'), 'h5 fw-semibold mb-2'),
                            $para(lang('Weddings, families, newborns and brand shoots are planned with you: send a booking request and we answer within a day.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book a Shoot'), '{{page:book}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('The Studio'), array(
                            $heading('h2', lang('The Studio'), 'h5 fw-semibold mb-3'),
                            $check_list(array($studio['address'], $studio['hours'])),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('ph-book-online', lang('online booking, free time, appointment, mini session, studio'))),
    )),
);

// ── Widget pieces ───────────────────────────────────────────────────────

// A photo field of the Galleries form: an image kind kept in the public
// folder.
$gallery_photo_field = function ($n) use ($field, $file) {
    return $field('ph-gal-photo-' . $n, sprintf(lang('Photo %d'), $n), $file('ph-gal-photo-' . $n, 'photo_' . $n, 'public', 'image/*'));
};

// Beside the gallery's story: the way to book a shoot like it and the
// packages.
$gallery_aside = function () use ($el, $heading, $para, $link, $gallery_page_link) {
    return $el('aside', 'card border-0 shadow-sm rounded-4', lang('Booking Card'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $heading('h2', lang('Love this style?'), 'pph-display h4 mb-2'),
            $para(lang('Tell us about your day or your people; we answer within a day with dates and ideas.'), 'small text-body-secondary mb-3'),
            // Opens the booking request with this gallery's category chosen.
            $link(lang('Book a Shoot like This'), '#', 'btn btn-primary rounded-pill w-100 mb-2', array(),
                $gallery_page_link('book', array('category' => 'category'))),
            $link(lang('See the Packages'), '{{page:packages}}', 'btn btn-outline-primary rounded-pill w-100'),
        )),
    ));
};

$gallery_photo_fields = array();
for ($n = 1; $n <= 6; $n++) $gallery_photo_fields[] = $col(array($gallery_photo_field($n)), '6');

$gallery_widgets = array(

    // The staff's Galleries form: one gallery. Featured starts as No.
    'gallery_form' => array(
        'page'   => 'gallery_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The shoot')),
            $field('ph-gal-title', lang('Title'), pg_cf_with_role($input('ph-gal-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('ph-gal-category', lang('Category'), pg_cf_with_role($select('ph-gal-category', 'category', lang('Choose a category…'), $category_titles), 'category'))), '6'),
                $col(array($field('ph-gal-location', lang('Location'), $input('ph-gal-location', 'text', 'location', 'off'))), '6'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('ph-gal-summary', lang('Summary'), pg_cf_with_role($textarea('ph-gal-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards of the lists.')),
            $field('ph-gal-story', lang('The Story'), $textarea('ph-gal-story', 'story', 5, false), 'mb-3',
                lang('How the day went and what you photographed, for the gallery page.')),
            $form_step(3, lang('Photos')),
            $field('ph-gal-cover', lang('Cover Photo'), pg_cf_with_role($file('ph-gal-cover', 'cover', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $row($gallery_photo_fields, '3'),
            $field('ph-gal-featured', lang('Featured'), $select('ph-gal-featured', 'featured', '', $gallery_featured_choices, false, $gallery_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured gallery is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every gallery, filtered by the address (category), with a search over
    // the words of the galleries.
    'galleries_list' => array(
        'page'   => 'galleries',
        'slug'   => lang('form-list'),
        'config' => $gallery_list_config(array(
            'items_per_page' => 12,
            'filters'        => array($gallery_filter('category', 'is equal to', '', 'category')),
            'search_enabled' => true,
            'search_fields'  => array('title', 'category', 'location', 'summary', 'story'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the galleries: a beach wedding, a studio portrait, a café...'),
        )),
        'tree'   => $gallery_grid(),
    ),

    // The home page's featured galleries, three at most.
    'home_galleries' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $gallery_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($gallery_filter('featured', 'is equal to', $gallery_featured_choices[0])),
            'empty_message'  => lang('No featured galleries yet.'),
        )),
        'tree'   => $gallery_grid(),
    ),

    // The booking widget with the editor's starter layout; it shows its
    // no-services text until Settings › Bookings has a service.
    'booking_slots' => array(
        'page'   => 'book_online',
        'slug'   => lang('booking'),
        'config' => array('regionType' => 'booking'),
        'tree'   => 'starter',
    ),
);

// One gallery: the category, the title, the location, the summary, the
// cover, the story with the way to book a shoot like it, and the photos.
$gallery_sample_key = 'lakeside';
$gallery_sample = $galleries[$gallery_sample_key];
$gallery_widgets['gallery_detail'] = array(
    'page'   => 'gallery',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:gallery_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This gallery could not be found. It may have been renamed or taken down.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-images', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Gallery not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This gallery could not be found. It may have been renamed or taken down.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the galleries'), '{{page:galleries}}', 'btn btn-primary rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Gallery'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Galleries'), '', lang('Link Text')),
                    ), array('href' => '{{page:galleries}}')),
                    $edit_button(),
                )),
                $span($categories[$gallery_sample['category']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Category'), $bind('category')),
                $heading('h1', $gallery_sample['title'], 'pph-display display-6 mb-2', $bind('title')),
                $el('p', 'd-flex align-items-center gap-1 fw-semibold mb-2', lang('Location'), array(
                    $icon('bi-geo-alt', 'text-primary'),
                    $span($gallery_sample['location'], '', lang('Location'), $bind('location')),
                )),
                $para($gallery_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                $optional_image('cover', 'title', 'rounded-4 object-fit-cover', 'ratio ratio-16x9 mb-5',
                    $photo('cover-' . $gallery_sample_key, 1600, 900), lang('Cover Photo')),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty', lang('The Story'), array(
                            $heading('h2', lang('The Story'), 'pph-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $gallery_sample['story']), $bind('story'))),
                        )),
                    ), '', '8'),
                    $col(array($gallery_aside()), '', '4'),
                ), '4', '', 'g-lg-5 mb-5'),
                $photo_grid($gallery_sample_key),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $gallery_pages, 'widgets' => $gallery_widgets);
