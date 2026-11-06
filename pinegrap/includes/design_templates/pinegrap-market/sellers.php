<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Marketplace", its makers: the staff page that adds a
 * maker (the Sellers form, a collection, with the sample makers written in
 * when it is made), the list of makers with its category and city filters,
 * a maker's own page with the reviews under it, and the list the home page
 * places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A maker is a profile, never an account: a record of the Sellers form
 * that the staff add by hand once an application is approved. Its city and
 * category are pick lists whose answers are the words parts.php gives, so
 * ?category= and ?city= filter the list by the words a visitor sees (the
 * category tiles open it that way, $makers_in). The logo and the cover are
 * image fields kept in the public folder. The shop address is the maker's
 * product group in the shop; its button leaves the page while the record
 * has none. Featured is the staff's own pick list: the home page lists the
 * featured makers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$seller_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// The answers of the pick lists, as the records store them.
$seller_category_titles = array();
foreach ($categories as $c) $seller_category_titles[] = $c['title'];
$seller_city_names = array_values($cities);
$seller_featured_answers = array('yes' => lang('Yes'), 'no' => lang('No'));

// The address of a maker's product group on the shop page (the group
// under "Shops" whose key is the maker's key in $sellers): the sample of
// the record's shop address. Empty on a site without the shop, so the
// shop button leaves the maker's page.
$seller_shop_url = function ($key) {
    return '{{shop_group_url:' . $key . '}}';
};

// A filter row of a list's settings: the field, how it compares and the
// value, or the address parameter the value comes from (left out while the
// parameter is not in the address).
$seller_filter = function ($field, $value, $param = '') {
    $row = array('field' => $field, 'operator' => 'is equal to', 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};

// A list's settings: the Sellers form, the maker page, by shop name.
$seller_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:seller_new}}',
        'detail_page_id'      => '{{tab:seller}}',
        'order_by_field'      => 'shop_name',
        'order_by_direction'  => 'ASC',
        'empty_message'       => lang('No makers match these choices yet. New shops open every month.'),
    ), $more);
};

// A maker in a list: the cover, the city and category, the shop's name
// (opening its page), its line and the maker's name.
$seller_list_card = function () use ($el, $para, $link, $span, $icon, $col, $bind, $optional_image, $photo, $sellers, $categories, $cities) {
    $s = $sellers['clay'];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative pmk-lift', lang('Maker Card'), array(
            $optional_image('cover', 'shop_name', 'object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary',
                $photo('shop-clay', 800, 450), $s['name']),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mb-2', lang('Place and Category'), array(
                    $icon('bi-geo-alt'),
                    $span($cities[$s['city']], '', lang('City'), $bind('city')),
                    $span($categories[$s['category']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Category'), $bind('category')),
                )),
                $el('h3', 'h5 fw-bold mb-1 position-static', lang('Title'), array(
                    $link($s['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'shop_name', 'href' => 'form_item_view'))),
                )),
                $para($s['tagline'], 'text-body-secondary mb-3', $bind('tagline')),
                $el('p', 'd-flex align-items-center gap-2 small mt-auto mb-0', lang('Maker'), array(
                    $icon('bi-person', 'text-primary'),
                    $span($s['maker'], 'fw-semibold', lang('Value'), $bind('maker')),
                )),
            )),
        )),
    ), '6', '4');
};
// The cards of a list, in rows of three.
$seller_grid = function () use ($widget_root, $row, $loop, $seller_list_card) {
    return $widget_root(array($row(array($loop(array($seller_list_card()))), '4')));
};

// The links above the list that narrow it: one per category and one per
// city, each opening the list with the filter set.
$seller_filter_bar = function () use ($el, $link, $span, $attr, $seller_category_titles, $seller_city_names) {
    $chips = function ($label, $param, $values) use ($el, $link, $span) {
        $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:sellers}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($values as $v) {
            $children[] = $link($v, '{{page:sellers}}?' . $param . '=' . rawurlencode($v), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        return $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $label, $children);
    };
    return $el('nav', 'mb-4', lang('Filters'), array(
        $chips(lang('Category'), 'category', $seller_category_titles),
        $chips(lang('City'), 'city', $seller_city_names),
    ), array('_attrs' => array($attr('aria-label', lang('Filter the makers')))));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every maker, by shop name, narrowed by category or city, with a search.
$seller_pages[] = array(
    'key'              => 'sellers',
    'name'             => lang('all-makers'),
    'folder'           => 'public',
    'title'            => lang('Makers'),
    'meta_description' => lang('The independent makers whose shops we carry: potters, silversmiths, printmakers, weavers, woodworkers and small producers.'),
    'tree'             => $page(array(
        $page_header(lang('Makers'), lang('Meet the people behind the shops. Choose a category or a city, or search for a maker.')),
        $el('section', 'py-5', lang('Makers'), array(
            $container(array($seller_filter_bar(), $widget('sellers_list'))),
        )),
        $shared('maker_band'),
    )),
);

// One maker: the cover and logo, the shop, its story, the way to its
// products, and the reviews under it (stars and words, newest first).
$seller_pages[] = array(
    'key'              => 'seller',
    'name'             => lang('maker'),
    'folder'           => 'public',
    'title'            => lang('Maker'),
    'meta_description' => lang('A maker of the market: their story, their shop and what buyers say about it.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Review'), 'allow_new' => true, 'rating' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new review of a maker'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('seller_detail'),
        $comments_section(lang('Reviews'), 'seller_reviews'),
        $shared('maker_band'),
    )),
);

// The staff page that adds a maker. The form is the Sellers collection; the
// sample makers are written in when the form is made, so the lists do not
// open empty (pg_cf_seed_sample_records()).
$sample_sellers = array();
foreach ($sellers as $key => $s) {
    $sample_sellers[] = array(
        'shop_name' => $s['name'],
        'maker'     => $s['maker'],
        'city'      => $cities[$s['city']],
        'category'  => $categories[$s['category']]['title'],
        'tagline'   => $s['tagline'],
        'story'     => $s['story'],
        'logo'      => $photo('logo-' . $key, 400, 400),
        'cover'     => $photo('shop-' . $key, 1200, 675),
        'shop_url'  => $seller_shop_url($key),
        'featured'  => $s['featured'] ? $seller_featured_answers['yes'] : $seller_featured_answers['no'],
    );
}
$seller_pages[] = array(
    'key'     => 'seller_new',
    'name'    => lang('new-maker'),
    'folder'  => 'private',
    'title'   => lang('New Maker'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Sellers'),
        'collection'           => 1,
        'confirmation_message' => lang('The maker\'s page is published.'),
        'confirmation_page_id' => '{{tab:sellers}}',
        'sample_records'       => $sample_sellers,
        // The cover is the maker page's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Maker'), lang('Add an approved maker with the shop\'s pictures; the page appears in the list as soon as you send it. Feature it on the home page from the page\'s Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('seller_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

// The shop's button on the maker page: it opens the maker's products in the
// shop, and leaves with its box while the record has no shop address (a
// site without the shop, a maker whose products are not in yet). The
// hidden value is what the box is judged by.
$seller_shop_button = function () use ($el, $span, $icon, $bind) {
    return $el('div', 'pg-hide-if-empty', lang('Shop Button'), array(
        $span(lang('Shop Address'), 'pg-field-value d-none', lang('Value'), $bind('shop_url')),
        $el('a', 'btn btn-primary btn-lg rounded-pill d-inline-flex align-items-center gap-2 px-4', lang('Link'), array(
            $icon('bi-bag'),
            $span(lang('Shop the collection'), '', lang('Link Text')),
        ), array('href' => '#', '_bindings' => array('href' => 'shop_url'))),
    ));
};

$seller_sample = $sellers['clay'];
$seller_widgets = array(

    // The staff's Sellers form: one maker. Featured starts No and is set
    // from the record's Edit button.
    'seller_form' => array(
        'page'   => 'seller_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The shop')),
            $field('mk-sel-name', lang('Shop Name'), pg_cf_with_role($input('mk-sel-name', 'text', 'shop_name', 'off'), 'title')),
            $field('mk-sel-maker', lang('Maker'), $input('mk-sel-maker', 'text', 'maker', 'off'), 'mb-3',
                lang('The name of the person, or people, who make the work.')),
            $row(array(
                $col(array($field('mk-sel-category', lang('Category'), pg_cf_with_role($select('mk-sel-category', 'category', lang('Choose a category…'), $seller_category_titles), 'category'))), '6'),
                $col(array($field('mk-sel-city', lang('City'), $select('mk-sel-city', 'city', lang('Choose a city…'), $seller_city_names))), '6'),
            ), '3'),
            $field('mk-sel-tagline', lang('Tagline'), $input('mk-sel-tagline', 'text', 'tagline', 'off'), 'mb-3',
                lang('One line under the shop\'s name, on the cards and the maker\'s page.')),
            $field('mk-sel-story', lang('Story'), pg_cf_with_role($textarea('mk-sel-story', 'story', 6), 'description')),
            $form_step(2, lang('Pictures and the shop')),
            $row(array(
                $col(array($field('mk-sel-logo', lang('Logo'), $file('mk-sel-logo', 'logo', 'public', 'image/*'), 'mb-3', lang('A square picture.'))), '6'),
                $col(array($field('mk-sel-cover', lang('Cover Picture'), pg_cf_with_role($file('mk-sel-cover', 'cover', 'public', 'image/*'), 'media'), 'mb-3', lang('A wide picture of the work or the workshop.'))), '6'),
            ), '3'),
            $field('mk-sel-shop', lang('Shop Address'), $input('mk-sel-shop', 'url', 'shop_url', 'off', false), 'mb-3',
                lang('The address of the maker\'s product group in the shop. Leave it empty until the products are in.')),
            $field('mk-sel-featured', lang('Featured'), $select('mk-sel-featured', 'featured', '', array_values($seller_featured_answers), false,
                $seller_featured_answers['no'], array('office_use_only' => true))),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every maker, filtered by the address (category, city), with a search
    // over the words of the profiles.
    'sellers_list' => array(
        'page'   => 'sellers',
        'slug'   => lang('form-list'),
        'config' => $seller_list_config(array(
            'items_per_page' => 12,
            'filters'        => array($seller_filter('category', '', 'category'), $seller_filter('city', '', 'city')),
            'search_enabled' => true,
            'search_fields'  => array('shop_name', 'maker', 'city', 'category', 'tagline', 'story'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the makers: a name, a craft, a city...'),
        )),
        'tree'   => $seller_grid(),
    ),

    // The home page's featured makers, three at most.
    'home_sellers' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $seller_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($seller_filter('featured', $seller_featured_answers['yes'])),
            'empty_message'  => lang('No makers yet.'),
        )),
        'tree'   => $seller_grid(),
    ),

    // One maker: the cover with the logo, the shop's name, maker, city and
    // category, the line, the story and the shop's button.
    'seller_detail' => array(
        'page'   => 'seller',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:seller_new}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('This maker could not be found. The shop may have closed.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $el('div', 'text-center py-5', lang('Not Found'), array(
                    $icon('bi-shop', 'display-5 text-body-tertiary'),
                    $heading('h2', lang('Maker not found'), 'h3 fw-semibold mt-3'),
                    $para(lang('This maker could not be found. The shop may have closed.'), 'text-body-secondary mb-4', $bind('not_found')),
                    $link(lang('See the makers'), '{{page:sellers}}', 'btn btn-primary rounded-pill px-4'),
                ), $show_when('record_not_found')),
                $el('article', '', lang('Maker'), array(
                    $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                            $icon('bi-arrow-left'),
                            $span(lang('All Makers'), '', lang('Link Text')),
                        ), array('href' => '{{page:sellers}}')),
                        $edit_button(),
                    )),
                    $optional_image('cover', 'shop_name', 'rounded-4 object-fit-cover', 'ratio ratio-21x9 mb-4',
                        $photo('shop-clay', 1200, 675), $seller_sample['name']),
                    $row(array(
                        $col(array(
                            $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Shop'), array(
                                $el('div', 'pg-hide-if-empty flex-shrink-0', lang('Logo'), array(
                                    $el('span', 'pg-field-value d-block', lang('Picture Frame'), array(
                                        $image($photo('logo-clay', 400, 400), lang('Logo'), 'rounded-circle border object-fit-cover',
                                            array('_attrs' => array($attr('width', '72'), $attr('height', '72')), '_bindings' => array('src' => 'logo', 'alt' => 'shop_name'))),
                                    )),
                                )),
                                $el('div', '', lang('Title'), array(
                                    $heading('h1', $seller_sample['name'], 'display-6 fw-semibold mb-1', $bind('shop_name')),
                                    $el('p', 'd-flex flex-wrap align-items-center gap-2 text-body-secondary mb-0', lang('Maker and Place'), array(
                                        $icon('bi-person'),
                                        $span($seller_sample['maker'], 'fw-semibold', lang('Maker'), $bind('maker')),
                                        $icon('bi-geo-alt', 'ms-2'),
                                        $span($cities[$seller_sample['city']], '', lang('City'), $bind('city')),
                                        $span($categories[$seller_sample['category']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Category'), $bind('category')),
                                    )),
                                )),
                            )),
                            $para($seller_sample['tagline'], 'lead mb-4', $bind('tagline')),
                            $el('div', 'pg-hide-if-empty mb-4', lang('Story'), array(
                                $heading('h2', lang('The story'), 'h5 fw-semibold mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $seller_sample['story']), $bind('story'))),
                            )),
                        ), '', '8'),
                        $col(array(
                            $el('aside', 'card border-0 bg-body-tertiary rounded-4', lang('Shop Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $para(lang('Every order is sold and sent by us, in one cart with the other shops.'), 'small text-body-secondary mb-3'),
                                    $seller_shop_button(),
                                )),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                ), $show_when('record_found')),
            ), 'py-5'),
        )),
    ),

    // A maker's reviews: the maker page's own Comments widget, under the
    // maker view (whose record the reviews belong to). Newest first, with
    // stars; a review can be answered, and the maker's answer sits under it.
    'seller_reviews' => array(
        'page'   => 'seller',
        'slug'   => lang('reviews'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'newest',
            'featured'   => 'none',
            'layout'     => 'threaded',
            'max_depth'  => 1,
            'per_page'   => 10,
        ),
        'tree'   => $comment_thread(array(
            'title'     => lang('Reviews'),
            'empty'     => lang('No reviews yet. Bought from this maker? Tell others what you think.'),
            'heading'   => lang('Review this maker'),
            'label'     => lang('Your review'),
            'sign_in'   => lang('Sign in to write a review.'),
            'submit'    => lang('Submit review'),
            'watch_box' => lang('Notify me when a new review is added.'),
            'follow'    => lang('Notify me of new reviews'),
            'unfollow'  => lang('Stop notifying me'),
            'close'     => lang('Stop new reviews'),
            'reopen'    => lang('Allow new reviews'),
            'reply'     => true,
            'watch'     => true,
        )),
    ),
);

return array('pages' => $seller_pages, 'widgets' => $seller_widgets);
