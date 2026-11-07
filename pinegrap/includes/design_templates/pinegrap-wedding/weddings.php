<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Wedding Planner and Venue", its Real Weddings: the staff
 * page that adds a wedding story (the Real Weddings form, a collection,
 * with the sample weddings written in when it is made), the list of every
 * wedding with its space and season filters, the wedding's own page, and
 * the list the home page places. Included after parts.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A wedding is a record of the Real Weddings form. Its space and season
 * are pick lists whose answers are the words parts.php gives, so a link
 * with ?space= or ?season= filters the list by the words a visitor sees.
 * Guests is a number field, so a list can sort on it. The photos are image
 * fields kept in the public folder; the suppliers are one line each.
 * Featured is the planner's own choice (office use only): the home page
 * shows the featured weddings.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$wedding_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$wedding_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a wedding for the home.
$wedding_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$wedding_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list's settings: the Real Weddings form, the wedding page, the newest
// story first.
$wedding_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:wedding_new}}',
        'detail_page_id'      => '{{tab:wedding}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No wedding matches these choices yet. Try another space or season.'),
    ), $more);
};
// The cards of a list, in rows of three.
$wedding_grid = function () use ($widget_root, $row, $loop, $wedding_card) {
    return $widget_root(array($row(array($loop(array($wedding_card()))), '4')));
};

// The links above the list that narrow it: one per space and one per
// season, each opening the list with the filter set.
$wedding_filter_bar = function () use ($el, $link, $span, $query, $space_titles, $season_titles) {
    $chips = function ($label, $param, $values) use ($el, $link, $span, $query) {
        $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:weddings}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($values as $v) {
            $children[] = $link($v, '{{page:weddings}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        return $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $label, $children);
    };
    return $el('nav', 'mb-4', lang('Filters'), array(
        $chips(lang('Venue Space'), 'space', $space_titles),
        $chips(lang('Season'), 'season', $season_titles),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every wedding story, narrowed by space and season from the address.
$wedding_pages[] = array(
    'key'              => 'weddings',
    'name'             => lang('real-weddings'),
    'folder'           => 'public',
    'title'            => lang('Real Weddings'),
    'meta_description' => lang('Weddings at the estate told by their couples: the spaces they chose, the season, the details and the people who made the day.'),
    'tree'             => $page(array(
        $page_header(lang('Real Weddings'), lang('Couples who married at the estate and the days they made their own. Choose a space or a season to narrow the list.')),
        $el('section', 'py-5', lang('Real Weddings'), array(
            $container(array($wedding_filter_bar(), $widget('weddings_list'))),
        ), $searchable('wd-real-weddings', lang('wedding stories, inspiration, photos, couples, ideas'))),
    )),
);

// One wedding: the photos, the couple, the space and season, the story,
// the couple's words, the suppliers and the way to ask for the same space.
$wedding_pages[] = array(
    'key'              => 'wedding',
    'name'             => lang('real-wedding'),
    'folder'           => 'public',
    'title'            => lang('Real Wedding'),
    'meta_description' => lang('A wedding at the estate: the couple\'s story, the photos, the space, the season and the suppliers.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('wedding_detail'),
    )),
);

// The staff page that adds a wedding story. The form is the Real Weddings
// collection; the sample weddings are written in when the form is made,
// so the lists do not open empty (pg_cf_seed_sample_records()).
$sample_weddings = array();
foreach ($weddings as $key => $w) {
    $sample_weddings[] = array(
        'couple'    => $w['couple'],
        'space'     => $spaces[$w['space']]['title'],
        'season'    => $seasons[$w['season']]['title'],
        'guests'    => (string)$w['guests'],
        'summary'   => $w['summary'],
        'story'     => $w['story'],
        'quote'     => $w['quote'],
        'suppliers' => $suppliers_text($key),
        'photo_1'   => $photo('wedding-' . $key, 1200, 900),
        'photo_2'   => $photo('wedding-' . $key . '-2', 1200, 900),
        'photo_3'   => $photo('wedding-' . $key . '-3', 1200, 900),
        'photo_4'   => $photo('wedding-' . $key . '-4', 1200, 900),
        'featured'  => $w['featured'] ? $wedding_featured_choices[0] : $wedding_featured_choices[1],
    );
}
$wedding_pages[] = array(
    'key'     => 'wedding_new',
    'name'    => lang('new-real-wedding'),
    'folder'  => 'private',
    'title'   => lang('New Real Wedding'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Real Weddings'),
        'collection'           => 1,
        'confirmation_message' => lang('The wedding story is published.'),
        'confirmation_page_id' => '{{tab:weddings}}',
        'sample_records'       => $sample_weddings,
        // The first photo is the story's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Real Wedding'), lang('Add a couple\'s story with their photos once they agree to share it; it appears in the list as soon as you send it. Mark it featured from the story\'s Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('wedding_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widget pieces ───────────────────────────────────────────────────────

// A photo of the wedding page, in a frame that leaves when the record has
// none.
$wedding_photo = function ($field, $seed, $ratio = '4x3') use ($optional_image, $photo) {
    return $optional_image($field, 'couple', 'rounded-4 object-fit-cover', 'ratio ratio-' . $ratio, $photo($seed, 1200, 900), lang('Photo'));
};

// Beside the story: the space, the season and the guests, and the way to
// ask about the same space or book a visit.
$wedding_aside = function ($sample_key) use ($el, $heading, $para, $link, $span, $icon, $bind, $wedding_page_link, $weddings, $spaces, $seasons) {
    $w = $weddings[$sample_key];
    $fact = function ($icon_name, $label, $value, $token) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-flex gap-2 mb-2', $label, array(
            $icon($icon_name, 'text-primary mt-1'),
            $el('div', '', lang('Text'), array(
                $span($label, 'd-block small text-body-secondary', lang('Label')),
                $span($value, 'fw-semibold', lang('Value'), $bind($token)),
            )),
        ));
    };
    return $el('aside', 'card border-0 shadow-sm rounded-4', lang('Wedding Facts'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $heading('h2', lang('The day'), 'pwd-display h5 mb-3'),
            $el('ul', 'list-unstyled mb-3', lang('Facts'), array(
                $fact('bi-geo-alt', lang('Venue Space'), $spaces[$w['space']]['title'], 'space'),
                $fact('bi-flower2', lang('Season'), $seasons[$w['season']]['title'], 'season'),
                $fact('bi-people', lang('Guests'), (string)$w['guests'], 'guests'),
            )),
            // Opens the enquiry with this wedding's space chosen (?space=).
            $link(lang('Ask about this space'), '#', 'btn btn-primary rounded-pill w-100 mb-2', array(),
                $wedding_page_link('enquiry', array('space' => 'space'))),
            $link(lang('Book a Venue Visit'), '{{page:book_visit}}', 'btn btn-outline-primary rounded-pill w-100'),
        )),
    ));
};

$wedding_widgets = array(

    // The staff's Real Weddings form: one story. Featured starts as No.
    'wedding_form' => array(
        'page'   => 'wedding_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The wedding')),
            $field('wd-rw-couple', lang('Couple'), pg_cf_with_role($input('wd-rw-couple', 'text', 'couple', 'off'), 'title'), 'mb-3',
                lang('As the couple would like to be named, for example Selin and Emre.')),
            $row(array(
                $col(array($field('wd-rw-space', lang('Venue Space'), pg_cf_with_role($select('wd-rw-space', 'space', lang('Choose a space…'), $space_titles), 'category'))), '6'),
                $col(array($field('wd-rw-season', lang('Season'), $select('wd-rw-season', 'season', lang('Choose a season…'), $season_titles))), '6'),
                $col(array($field('wd-rw-guests', lang('Guests'), $input('wd-rw-guests', 'number', 'guests', 'off', true, array('kind' => 'number'),
                    array($attr('min', '1'), $attr('step', '1'))))), '6'),
            ), '3'),
            $form_step(2, lang('Their story')),
            $field('wd-rw-summary', lang('Summary'), pg_cf_with_role($textarea('wd-rw-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards of the lists.')),
            $field('wd-rw-story', lang('Story'), $textarea('wd-rw-story', 'story', 8)),
            $field('wd-rw-quote', lang('In their words'), $textarea('wd-rw-quote', 'quote', 3, false), 'mb-3',
                lang('A line from the couple about their day.')),
            $field('wd-rw-suppliers', lang('Suppliers'), $textarea('wd-rw-suppliers', 'suppliers', 4, false), 'mb-3',
                lang('One on each line: the role and the name, for example Flowers: Wild Stem Florals.')),
            $form_step(3, lang('Photos')),
            $field('wd-rw-photo-1', lang('First Photo'), pg_cf_with_role($file('wd-rw-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $row(array(
                $col(array($field('wd-rw-photo-2', lang('Second Photo'), $file('wd-rw-photo-2', 'photo_2', 'public', 'image/*'))), '4'),
                $col(array($field('wd-rw-photo-3', lang('Third Photo'), $file('wd-rw-photo-3', 'photo_3', 'public', 'image/*'))), '4'),
                $col(array($field('wd-rw-photo-4', lang('Fourth Photo'), $file('wd-rw-photo-4', 'photo_4', 'public', 'image/*'))), '4'),
            ), '3'),
            $field('wd-rw-featured', lang('Featured'), $select('wd-rw-featured', 'featured', '', $wedding_featured_choices, false, $wedding_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured wedding is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every wedding, filtered by the address (space, season), with a search
    // over the words of the stories.
    'weddings_list' => array(
        'page'   => 'weddings',
        'slug'   => lang('form-list'),
        'config' => $wedding_list_config(array(
            'items_per_page' => 12,
            'filters'        => array(
                $wedding_filter('space', 'is equal to', '', 'space'),
                $wedding_filter('season', 'is equal to', '', 'season'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('couple', 'space', 'season', 'summary', 'story', 'suppliers'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the stories: a garden ceremony, a winter party, a photographer...'),
        )),
        'tree'   => $wedding_grid(),
    ),

    // The home page's featured weddings, three at most.
    'home_weddings' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $wedding_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($wedding_filter('featured', 'is equal to', $wedding_featured_choices[0])),
            'empty_message'  => lang('No featured weddings yet.'),
        )),
        'tree'   => $wedding_grid(),
    ),
);

// One wedding: the couple, the summary, the photos, the story, the
// couple's words, the suppliers and the facts with the way to ask.
$wedding_sample_key = 'selin-emre';
$wedding_sample = $weddings[$wedding_sample_key];
$wedding_widgets['wedding_detail'] = array(
    'page'   => 'wedding',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:wedding_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This wedding story could not be found. It may have been taken down at the couple\'s wish.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-balloon-heart', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Wedding story not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This wedding story could not be found. It may have been taken down at the couple\'s wish.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the Real Weddings'), '{{page:weddings}}', 'btn btn-primary rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Real Wedding'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Real Weddings'), '', lang('Link Text')),
                    ), array('href' => '{{page:weddings}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                    $span($spaces[$wedding_sample['space']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Venue Space'), $bind('space')),
                    $span($seasons[$wedding_sample['season']]['title'], 'badge rounded-pill text-bg-light border', lang('Season'), $bind('season')),
                )),
                $heading('h1', $wedding_sample['couple'], 'pwd-display display-5 mb-2', $bind('couple')),
                $para($wedding_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                // The photos: the first large, the next two beside it, the
                // fourth wide under them.
                $row(array(
                    $col(array($wedding_photo('photo_1', 'wedding-' . $wedding_sample_key)), '', '8'),
                    $col(array(
                        $el('div', 'd-flex flex-column gap-3 h-100', lang('Photos'), array(
                            $wedding_photo('photo_2', 'wedding-' . $wedding_sample_key . '-2'),
                            $wedding_photo('photo_3', 'wedding-' . $wedding_sample_key . '-3'),
                        )),
                    ), '', '4'),
                ), '3', '', 'mb-5'),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty mb-4', lang('Story'), array(
                            $heading('h2', lang('Their story'), 'pwd-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $wedding_sample['story']), $bind('story'))),
                        )),
                        $el('figure', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('In Their Words'), array(
                            $el('blockquote', 'mb-0', lang('Quote'), array(
                                $el('p', 'pg-field-value pwd-quote fs-5 mb-0', lang('Value'), array(), array_merge(array('text' => $wedding_sample['quote']), $bind('quote'))),
                            )),
                        )),
                        $wedding_photo('photo_4', 'wedding-' . $wedding_sample_key . '-4', '16x9'),
                        $el('div', 'pg-hide-if-empty mt-4', lang('Suppliers'), array(
                            $heading('h2', lang('The people who made the day'), 'pwd-display h5 mb-2'),
                            $el('div', 'pg-field-value lh-lg small', lang('Value'), array(), array_merge(array('text' => $suppliers_text($wedding_sample_key)), $bind('suppliers'))),
                        )),
                    ), '', '8'),
                    $col(array($wedding_aside($wedding_sample_key)), '', '4'),
                ), '4', '', 'g-lg-5 mb-5'),
                $el('section', '', lang('Packages'), array(
                    $section_header(lang('Your own day'), lang('Plan a wedding like this'), lang('See the Packages'), '{{page:packages}}'),
                    $para(lang('Every wedding here began with an enquiry. Tell us your date and your guests and we will answer within a day.'), 'text-body-secondary mb-3'),
                    $link(lang('Enquire'), '{{page:enquiry}}', 'btn btn-primary rounded-pill px-4'),
                )),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $wedding_pages, 'widgets' => $wedding_widgets);
