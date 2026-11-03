/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Google Fonts catalogue the visual designer's font picker and the
 * image editor's Text tool share: about two hundred popular families by
 * category (sans-serif, serif, display, handwriting, monospace), most
 * popular first. It ships in the code, not fetched live, so the pickers
 * work without an API key; the faces themselves load from Google as the
 * site's own fonts do. "Google Sans" and "Product Sans" are not in the
 * public catalogue; Geist, Outfit, Inter and DM Sans come closest.
 *
 * Bundled first into style_designer.min.js and pg_image_editor.min.js
 * (pg_js_bundles()), so both read the same list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    var CATEGORIES = {
        sans: [
            'Inter', 'Roboto', 'Roboto Condensed', 'Roboto Flex', 'Open Sans', 'Open Sans Condensed', 'Lato',
            'Montserrat', 'Poppins', 'Raleway', 'Nunito', 'Nunito Sans', 'Ubuntu', 'Ubuntu Condensed',
            'Oswald', 'Source Sans 3', 'Source Sans Pro', 'Fira Sans', 'Fira Sans Condensed',
            'Fira Sans Extra Condensed', 'PT Sans', 'PT Sans Caption', 'PT Sans Narrow', 'Noto Sans',
            'Noto Sans Display', 'Josefin Sans', 'Quicksand', 'Exo', 'Exo 2', 'Mulish', 'Work Sans', 'Rubik',
            'DM Sans', 'Space Grotesk', 'Plus Jakarta Sans', 'Sora', 'Outfit', 'Figtree', 'Lexend',
            'Lexend Deca', 'Manrope', 'Karla', 'Heebo', 'Hind', 'Hind Madurai', 'Hind Siliguri', 'Cabin',
            'Cabin Condensed', 'Asap', 'Asap Condensed', 'Barlow', 'Barlow Condensed',
            'Barlow Semi Condensed', 'Catamaran', 'Comfortaa', 'Dosis', 'Kanit', 'Maven Pro', 'Mukta',
            'Public Sans', 'Saira', 'Saira Condensed', 'Saira Extra Condensed', 'Saira Semi Condensed',
            'Signika', 'Signika Negative', 'Titillium Web', 'Yantramanav', 'Inria Sans', 'Onest',
            'Bricolage Grotesque', 'Geologica', 'Schibsted Grotesk', 'Geist', 'Geist Mono', 'IBM Plex Sans',
            'IBM Plex Sans Condensed', 'IBM Plex Serif', 'Syne', 'Urbanist', 'Albert Sans', 'Be Vietnam Pro',
            'Reddit Sans', 'Wix Madefor Display', 'Wix Madefor Text'
        ],
        serif: [
            'Merriweather', 'Playfair Display', 'Playfair', 'PT Serif', 'PT Serif Caption', 'Source Serif 4',
            'Source Serif Pro', 'Noto Serif', 'Noto Serif Display', 'Cardo', 'Crimson Pro', 'Crimson Text',
            'Cormorant', 'Cormorant Garamond', 'Cormorant Infant', 'Cormorant SC', 'EB Garamond', 'Domine',
            'Vollkorn', 'Vollkorn SC', 'Volkhov', 'Tinos', 'Faustina', 'Frank Ruhl Libre', 'Inria Serif',
            'Marcellus', 'Marcellus SC', 'Old Standard TT', 'Sorts Mill Goudy', 'Tenor Sans', 'Yeseva One',
            'Slabo 13px', 'Slabo 27px', 'Rufina', 'Libre Baskerville', 'Libre Caslon Text', 'Lora',
            'Spectral', 'Bitter', 'Zilla Slab', 'Arvo', 'Roboto Slab', 'DM Serif Display', 'DM Serif Text',
            'Fraunces'
        ],
        display: [
            'Bebas Neue', 'Anton', 'Abril Fatface', 'Alfa Slab One', 'Archivo Black', 'Big Shoulders Display',
            'Cabin Sketch', 'Concert One', 'Fjalla One', 'Fugaz One', 'Limelight', 'Mr Dafoe',
            'Permanent Marker', 'Righteous', 'Rozha One', 'Russo One', 'Shrikhand', 'Stalemate',
            'Staatliches', 'Bowlby One', 'Bowlby One SC', 'Eczar', 'Krona One', 'Black Ops One', 'Monoton',
            'Press Start 2P'
        ],
        hand: [
            'Pacifico', 'Lobster', 'Dancing Script', 'Caveat', 'Satisfy', 'Great Vibes', 'Sacramento',
            'Parisienne', 'Allura', 'Amatic SC', 'Architects Daughter', 'Bad Script', 'Bilbo', 'Caveat Brush',
            'Cookie', 'Courgette', 'Damion', 'Gloria Hallelujah', 'Homemade Apple', 'Indie Flower',
            'Italianno', 'Just Another Hand', 'Kalam', 'Kaushan Script', 'La Belle Aurore', 'Mr De Haviland',
            'Patrick Hand', 'Reenie Beanie', 'Rouge Script', 'Shadows Into Light', 'Tangerine', 'Yellowtail'
        ],
        mono: [
            'JetBrains Mono', 'Fira Code', 'Source Code Pro', 'Space Mono', 'IBM Plex Mono', 'Cutive Mono',
            'Inconsolata', 'Major Mono Display', 'Nova Mono', 'Overpass Mono', 'PT Mono', 'Roboto Mono',
            'Share Tech Mono', 'Ubuntu Mono', 'VT323', 'Anonymous Pro', 'Courier Prime', 'DM Mono'
        ]
    };

    var api = {
        // The category keys in the pickers' order.
        order: ['sans', 'serif', 'display', 'hand', 'mono'],

        categories: CATEGORIES,

        // Every family once, in category order.
        all: function () {
            var out = [];

            api.order.forEach(function (key) {
                out = out.concat(CATEGORIES[key]);
            });

            return out;
        }
    };

    if ((typeof module === 'object') && module.exports) {
        module.exports = api;
    }

    if (root) {
        root.PgGoogleFonts = api;
    }
}((typeof window !== 'undefined') ? window : null));
