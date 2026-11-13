<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Florist", its Our Work collection: the staff page that
 * adds a wedding or event the florist dressed (the Our Work form, a
 * collection, with the sample work written in when it is made), the list
 * of every work with its kind and season filters, the work's own page, and
 * the list the home page places. Included after parts.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A work is a record of the Our Work form. Its kind and season are pick
 * lists whose answers are the words parts.php gives, so a link with ?type=
 * or ?season= filters the list by the words a visitor sees (the footer
 * sends ?type=). The photos are image fields kept in the public folder;
 * the flowers are one line each. Featured is the florist's own choice
 * (office use only): the home page shows the featured work.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$work_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$work_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a work for the home.
$work_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$work_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list's settings: the Our Work form, the work page, the newest first.
$work_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:work_new}}',
        'detail_page_id'      => '{{tab:work_item}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No work matches these choices yet. Try another kind or season.'),
    ), $more);
};
// The cards of a list, in rows of three.
$work_grid = function () use ($widget_root, $row, $loop, $work_card) {
    return $widget_root(array($row(array($loop(array($work_card()))), '4')));
};

// The links above the list that narrow it: one per kind and one per
// season, each opening the list with the filter set.
$work_filter_bar = function () use ($el, $link, $span, $query, $event_titles, $season_titles) {
    $chips = function ($label, $param, $values) use ($el, $link, $span, $query) {
        $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:work}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($values as $v) {
            $children[] = $link($v, '{{page:work}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        return $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $label, $children);
    };
    return $el('nav', 'mb-4', lang('Filters'), array(
        $chips(lang('Kind'), 'type', $event_titles),
        $chips(lang('Season'), 'season', $season_titles),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every work, narrowed by kind and season from the address.
$work_pages[] = array(
    'key'              => 'work',
    'name'             => lang('our-work'),
    'folder'           => 'public',
    'title'            => lang('Our Work'),
    'meta_description' => lang('Weddings, events and business flowers we have made: pictures, the season and the flowers we used.'),
    'tree'             => $page(array(
        $page_header(lang('Our Work'), lang('Weddings, events and spaces we have filled with flowers. Choose a kind or a season to narrow the list.')),
        $el('section', 'py-5', lang('Our Work'), array(
            $container(array($work_filter_bar(), $widget('work_list'))),
        ), $searchable('fl-our-work', lang('portfolio, gallery, wedding flowers, event flowers, inspiration, ideas'))),
    )),
);

// One work: the photos, the title, the kind and season, the story, the
// flowers used and the way to ask for the same.
$work_pages[] = array(
    'key'              => 'work_item',
    'name'             => lang('our-work-item'),
    'folder'           => 'public',
    'title'            => lang('Work Details'),
    'meta_description' => lang('A wedding or event we dressed in flowers: the story, the photos, the season and the flowers we used.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('work_detail'),
    )),
);

// The staff page that adds a work. The form is the Our Work collection;
// the sample work is written in when the form is made, so the lists do not
// open empty (pg_cf_seed_sample_records()).
$sample_works = array();
foreach ($works as $key => $w) {
    $sample_works[] = array(
        'title'    => $w['title'],
        'type'     => $event_services[$w['type']]['title'],
        'season'   => $seasons[$w['season']]['title'],
        'summary'  => $w['summary'],
        'story'    => $w['story'],
        'flowers'  => $work_flowers_text($key),
        'photo_1'  => $photo('work-' . $key, 1200, 900),
        'photo_2'  => $photo('work-' . $key . '-2', 1200, 900),
        'photo_3'  => $photo('work-' . $key . '-3', 1200, 900),
        'photo_4'  => $photo('work-' . $key . '-4', 1200, 900),
        'featured' => $w['featured'] ? $work_featured_choices[0] : $work_featured_choices[1],
    );
}
$work_pages[] = array(
    'key'     => 'work_new',
    'name'    => lang('new-work'),
    'folder'  => 'private',
    'title'   => lang('New Work'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Our Work'),
        'collection'           => 1,
        'confirmation_message' => lang('The work is published.'),
        'confirmation_page_id' => '{{tab:work}}',
        'sample_records'       => $sample_works,
        // The first photo is the work's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Work'), lang('Add a wedding or event you dressed, with its photos; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('work_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widget pieces ───────────────────────────────────────────────────────

// A photo of the work page, in a frame that leaves when the record has
// none.
$work_photo = function ($field, $seed, $ratio = '4x3') use ($optional_image, $photo) {
    return $optional_image($field, 'title', 'rounded-4 object-fit-cover', 'ratio ratio-' . $ratio, $photo($seed, 1200, 900), lang('Photo'));
};

// Beside the story: the kind and the season, and the way to ask about the
// same kind of event or book a consultation.
$work_aside = function ($sample_key) use ($el, $heading, $link, $span, $icon, $bind, $work_page_link, $works, $event_services, $seasons) {
    $w = $works[$sample_key];
    $fact = function ($icon_name, $label, $value, $token) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-flex gap-2 mb-2', $label, array(
            $icon($icon_name, 'text-primary mt-1'),
            $el('div', '', lang('Text'), array(
                $span($label, 'd-block small text-body-secondary', lang('Label')),
                $span($value, 'fw-semibold', lang('Value'), $bind($token)),
            )),
        ));
    };
    return $el('aside', 'card border-0 shadow-sm rounded-4', lang('Work Facts'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $heading('h2', lang('At a glance'), 'pfl-display h5 mb-3'),
            $el('ul', 'list-unstyled mb-3', lang('Facts'), array(
                $fact('bi-stars', lang('Kind'), $event_services[$w['type']]['title'], 'type'),
                $fact('bi-flower2', lang('Season'), $seasons[$w['season']]['title'], 'season'),
            )),
            // Opens the event enquiry with this work's kind chosen (?event=).
            $link(lang('Ask about your event'), '#', 'btn btn-primary rounded-pill w-100 mb-2', array(),
                $work_page_link('event_enquiry', array('event' => 'type'))),
            $link(lang('Book a Consultation'), '{{page:consultation}}', 'btn btn-outline-primary rounded-pill w-100'),
        )),
    ));
};

$work_widgets = array(

    // The staff's Our Work form: one work. Featured starts as No.
    'work_form' => array(
        'page'   => 'work_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('About the work')),
            $field('fl-ow-title', lang('Title'), pg_cf_with_role($input('fl-ow-title', 'text', 'title', 'off'), 'title'), 'mb-3',
                lang('A short name, for example A Garden Wedding in Blush.')),
            $row(array(
                $col(array($field('fl-ow-type', lang('Kind'), pg_cf_with_role($select('fl-ow-type', 'type', lang('Choose a kind…'), $event_titles), 'category'))), '6'),
                $col(array($field('fl-ow-season', lang('Season'), $select('fl-ow-season', 'season', lang('Choose a season…'), $season_titles))), '6'),
            ), '3'),
            $form_step(2, lang('The story')),
            $field('fl-ow-summary', lang('Summary'), pg_cf_with_role($textarea('fl-ow-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards of the lists.')),
            $field('fl-ow-story', lang('Story'), $textarea('fl-ow-story', 'story', 8)),
            $field('fl-ow-flowers', lang('Flowers'), $textarea('fl-ow-flowers', 'flowers', 4, false), 'mb-3',
                lang('One flower on each line, for example Garden roses.')),
            $form_step(3, lang('Photos')),
            $field('fl-ow-photo-1', lang('First Photo'), pg_cf_with_role($file('fl-ow-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $row(array(
                $col(array($field('fl-ow-photo-2', lang('Second Photo'), $file('fl-ow-photo-2', 'photo_2', 'public', 'image/*'))), '4'),
                $col(array($field('fl-ow-photo-3', lang('Third Photo'), $file('fl-ow-photo-3', 'photo_3', 'public', 'image/*'))), '4'),
                $col(array($field('fl-ow-photo-4', lang('Fourth Photo'), $file('fl-ow-photo-4', 'photo_4', 'public', 'image/*'))), '4'),
            ), '3'),
            $field('fl-ow-featured', lang('Featured'), $select('fl-ow-featured', 'featured', '', $work_featured_choices, false, $work_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured work is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every work, filtered by the address (type, season), with a search
    // over the words of the stories.
    'work_list' => array(
        'page'   => 'work',
        'slug'   => lang('form-list'),
        'config' => $work_list_config(array(
            'items_per_page' => 12,
            'filters'        => array(
                $work_filter('type', 'is equal to', '', 'type'),
                $work_filter('season', 'is equal to', '', 'season'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('title', 'type', 'season', 'summary', 'story', 'flowers'),
            'search_width'   => 'full',
            'search_label'   => lang('Search our work: peonies, a winter dinner, a hotel lobby...'),
        )),
        'tree'   => $work_grid(),
    ),

    // The home page's featured work, three at most.
    'home_work' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $work_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($work_filter('featured', 'is equal to', $work_featured_choices[0])),
            'empty_message'  => lang('No featured work yet.'),
        )),
        'tree'   => $work_grid(),
    ),
);

// One work: the title, the summary, the photos, the story, the flowers
// and the facts with the way to ask.
$work_sample_key = 'garden-wedding';
$work_sample = $works[$work_sample_key];
$work_widgets['work_detail'] = array(
    'page'   => 'work_item',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:work_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This work could not be found. It may have been taken down.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-flower1', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Work page not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This work could not be found. It may have been taken down.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See Our Work'), '{{page:work}}', 'btn btn-primary rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Our Work'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Our Work'), '', lang('Link Text')),
                    ), array('href' => '{{page:work}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                    $span($event_services[$work_sample['type']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Kind'), $bind('type')),
                    $span($seasons[$work_sample['season']]['title'], 'badge rounded-pill bg-body text-body-emphasis border', lang('Season'), $bind('season')),
                )),
                $heading('h1', $work_sample['title'], 'pfl-display display-5 mb-2', $bind('title')),
                $para($work_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                // The photos: the first large, the next two beside it, the
                // fourth wide under the story.
                $row(array(
                    $col(array($work_photo('photo_1', 'work-' . $work_sample_key)), '', '8'),
                    $col(array(
                        $el('div', 'd-flex flex-column gap-3 h-100', lang('Photos'), array(
                            $work_photo('photo_2', 'work-' . $work_sample_key . '-2'),
                            $work_photo('photo_3', 'work-' . $work_sample_key . '-3'),
                        )),
                    ), '', '4'),
                ), '3', '', 'mb-5'),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty mb-4', lang('Story'), array(
                            $heading('h2', lang('The story'), 'pfl-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $work_sample['story']), $bind('story'))),
                        )),
                        $work_photo('photo_4', 'work-' . $work_sample_key . '-4', '16x9'),
                        $el('div', 'pg-hide-if-empty mt-4', lang('Flowers'), array(
                            $heading('h2', lang('The flowers we used'), 'pfl-display h5 mb-2'),
                            $el('div', 'pg-field-value lh-lg small', lang('Value'), array(), array_merge(array('text' => $work_flowers_text($work_sample_key)), $bind('flowers'))),
                        )),
                    ), '', '8'),
                    $col(array($work_aside($work_sample_key)), '', '4'),
                ), '4', '', 'g-lg-5 mb-5'),
                $el('section', '', lang('Weddings and Events'), array(
                    $section_header(lang('Your own day'), lang('Flowers like these for your day'), lang('Wedding and event flowers'), '{{page:events}}'),
                    $para(lang('Every work here began with an enquiry. Tell us your date, the place and your guests and we answer within a day.'), 'text-body-secondary mb-3'),
                    $link(lang('Ask about your event'), '{{page:event_enquiry}}', 'btn btn-primary rounded-pill px-4'),
                )),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $work_pages, 'widgets' => $work_widgets);
