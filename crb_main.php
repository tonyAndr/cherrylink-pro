<?php
/*
 * CherryLink Plugin
 */

// Disable direct access
defined( 'ABSPATH' ) || exit;
// Define lib name
define('LINKATE_CRB_LIBRARY', true);

class CL_Related_Block {

	const TEMP_BEFORE = "<span class='crb-header'>Читайте далее:</span><div class='crb-container'>";
	const TEMP_AFTER = "</div>";
	const TEMP_LINK = "<div class='crb-item-container'><a href='{url}' target='_blank'><img src='{imagesrc}'><p>{title}</p></a></div>";

    static function get_version() {
        $plugin_data = get_file_data(__FILE__, array('version' => 'Version'), 'plugin');
        return $plugin_data['version'];
    }

    static function get_links($offset = false, $num_links = false, $rel_type = false, $ignore_sorting = false, $excluded_cats = false) {
        global $post;
        if ($post) {
            $post_id = $post->ID;
        } else {
            $post_id = -666;
        }
           
        $options = get_option('linkate-posts');
        $is_term = 0;
        
        $hide_existing = $options['crb_hide_existing_links'] == 'true' ? 1 : 0;

        // might come from options or shortcode/func
        if ($offset === false) {
            if (isset($options['crb_default_offset'])) {
                $offset = intval($options['crb_default_offset']);
            } else {
                $offset = 0;
            }
        }
        if ($num_links === false) {
            $num_of_links = intval($options['crb_num_of_links_to_show']);
        } else {
            $num_of_links = $num_links;
        }
        // only posts to include, if set
        $included_posts = '';
        $excluded = '';
        if ($post) {
            $included_posts = CL_RB_Metabox::get_custom_posts($post_id);
            if ($hide_existing) {
                $excluded = CL_Related_Block::get_posts_to_exclude($post_id);
            }
        }
        // show latest if has args provided from func
        $show_latest = 0;
        if ($rel_type === false) {
            $show_latest = $options['crb_show_latest'] == 'true' ? 1 : 0;
        } else if ($rel_type === 'new') {
            $show_latest = 1;
            $included_posts = false;
        } else {
            $show_latest = 0;
        }

        $args = '';
        if ($included_posts) { // if custom selection
            $args = "manual_ID=" . $post_id . "&is_term=" . $is_term . "&offset=" . $offset . "&included_posts=" . $included_posts . "&ignore_relevance=true&";
            if ($num_links !== false) {
                $args .= "&limit_ajax=".$num_of_links."&";
            }
            if ($ignore_sorting !== false && $ignore_sorting !== "false") {
                $args .= "&ignore_sorting=true&";
            }
        } else if (!$included_posts && $show_latest) { // show latest
            $ids = self::get_latest_posts_ids($post_id, $options, $num_of_links);
            $args = "manual_ID=" . $post_id . "&is_term=" . $is_term . "&offset=" . $offset . "&included_posts=" . $ids . "&ignore_relevance=true&";
        } else { // show related links
            $args = "manual_ID=".$post_id."&is_term=".$is_term."&offset=".$offset."&excluded_posts=".$excluded."&limit_ajax=".$num_of_links."&";
        }

        if ($excluded_cats) {
            $args .= "&excluded_cats=".$excluded_cats."&";
        }
        
        _cherry_debug(__FUNCTION__, explode("&", $args), 'Аргументы для query');

        if (!isset($options['crb_cache_minutes'])) $options['crb_cache_minutes'] = 1440;
        $cache_delay_time = $options['crb_cache_minutes'];
        // To disable cache or convert to minutes
        if ($options['crb_cache_minutes'] === 0) {
            $cache_delay_time = 0; // check later
        } else {
            $cache_delay_time = $options['crb_cache_minutes'] * MINUTE_IN_SECONDS;
        }
        $output = '';

        if (!$show_latest) {
            // Get relevant results
            if ( false === ( $output = get_transient( "crb__".$args ) ) ) {
                // It wasn't there, so regenerate the data and save the transient
                _cherry_debug(__FUNCTION__, false, 'Релевантный поиск, в кэше не нашли');
                $output = linkate_posts($args);
                if ($cache_delay_time !== 0) {
                    set_transient( "crb__".$args, $output, $cache_delay_time );
                }
            }
        }

        // ignore relevance
        if (!$output || empty($output)) {
            $args .= "ignore_relevance=true&show_pages=false&";
            if ( false === ( $output = get_transient( "non_rel_crb__".$args ) ) ) {
                // It wasn't there, so regenerate the data and save the transient
                _cherry_debug(__FUNCTION__, false, 'НЕ релевантный поиск, в кэше не нашли');
                $output = linkate_posts($args);
                if ($cache_delay_time !== 0) {
                    set_transient( "non_rel_crb__".$args, $output, $cache_delay_time );
                }
            }
        }
        return $output;
    }

    static function clear_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE `option_name` LIKE ('%crb\_\_%')");
    }

    static function get_latest_posts_ids($curr_id, $options, $limit) {
        global $wpdb;
        if ($curr_id == null) $curr_id = 0;
        $show_customs = $options['show_customs'];
        if (!empty($show_customs)) {
            $customs = explode(',', $show_customs);
            foreach ($customs as $value) {
                $typelist[] = "'".$value."'";
            }
        }
        $typelist[] = "'post'";

        if (count($typelist) === 1) {
            $sql = " AND post_type=$typelist[0]";
        } else {
            $sql = " AND post_type IN (" . implode(',',$typelist). ")";
        }

        $sql .= " AND post_status='publish' ";

        $ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE ID <> $curr_id $sql ORDER BY ID DESC LIMIT $limit");

        if ($ids) {
            return implode(",", $ids);
        } else {
            return '';
        }
    }

    static function prepare_related_block($postid, $results, $option_key, $options) {
        // TEMPLATES
        $output_template_item_prefix = isset($options['crb_temp_before']) ? stripslashes(urldecode(base64_decode($options['crb_temp_before']))) : self::TEMP_BEFORE;
        $output_template_item_suffix = isset($options['crb_temp_after']) ? stripslashes(urldecode(base64_decode($options['crb_temp_after']))) : self::TEMP_AFTER;
        $item_template = isset($options['crb_temp_link']) ? stripslashes(urldecode(base64_decode($options['crb_temp_link']))) : self::TEMP_LINK;

        $item_template = str_replace('imagesrc', 'imagesrc:crb', $item_template);
        $item_template = str_replace('imgtag', 'imgtag:crb', $item_template);

        if ($results) {
            // IF CUSTOM MANUAL ANKORS - REPLACE {title} or {title_seo} with them HERE
            $use_manual_titles = get_post_meta( $postid, "crb-meta-use-manual", true);
            if ($use_manual_titles === "checked") {
                $meta_titles = explode("\n", get_post_meta( $postid, "crb-meta-links", true));
                foreach($meta_titles as $line) {
                    $temp = explode("[|]", $line);

                    if ($temp[2] === "undefined" || !isset($temp[2]) || empty(trim($temp[2]))) {
                        // manual title is not present, using standart title/h1
                        $id_titles[$temp[0]] = $temp[1];
                    } else {
                        $id_titles[$temp[0]] = $temp[2];
                    }
                }
            }

            $translations = link_cf_prepare_template($item_template);
            $items = array();
            foreach ($results as $result) {
                if (isset($id_titles))
                    $result->manual_title = $id_titles[$result->ID];
                $items[] = link_cf_expand_template($result, $item_template, $translations, $option_key);
            }
            if ($options['ignore_sorting'] && $options['sort']['by1'] !== '') $items = link_cf_sort_items($options['sort'], $results, $option_key, $options['group_template'], $items);
            $output = $output_template_item_prefix.implode("\n", $items).$output_template_item_suffix;

        } else {
            // we display the blank message, with tags expanded if necessary
            $translations = link_cf_prepare_template($options['none_text']);
            $output = "";
        }
        return $output;
    }

    // If table exists we can exclude used links
    static function scheme_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix."linkate_scheme";
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
            return true;
        } else {
            return false;
        }
    }

    static function get_posts_to_exclude($post_id) {
        global $wpdb;
        if (CL_Related_Block::scheme_table_exists()) {
            $tablename = $wpdb->prefix."linkate_scheme";
            $results = $wpdb->get_col("SELECT target_id FROM $tablename WHERE source_id = $post_id AND target_id > 0");
            if ($results) {
                $results = array_filter($results, function ($el) {
                    return !empty(trim($el));
                });
                return implode(",", $results);
            } else {
                return '';
            }
        }
        return '';
    }

    // add links after content for single posts
    static function add_after_content( $content ) {
        global $post;
        $options = get_option('linkate-posts');
        _cherry_debug(__FUNCTION__, $options, "Содержимое переменной options");
        if( (is_single() || ($options['crb_show_for_pages'] == 'true' && is_page()) ) && ! empty( $GLOBALS['post'] ) && in_the_loop() && is_main_query() ) {
            if ( $GLOBALS['post']->ID == get_the_ID()) {
                if ($options['crb_show_after_content'] == 'true' && CL_RB_Metabox::get_custom_show(get_the_ID())) {
                    $content .= CL_Related_Block::get_links();
                    //remove_filter( current_filter(), __FUNCTION__ );
                }
            }
        }
        return $content;
    }

    static function fill_options($after_update = false) {
        $options = get_option('linkate-posts');
        if (!isset($options['crb_installed']) || isset($_POST['crb_defaults'])) {
            $options['crb_show_after_content'] = "false";
            $options['crb_hide_existing_links'] = "true";
            $options['crb_show_for_pages'] = "false";
            $options['crb_show_latest'] = "false";
            $options['crb_css_tuning'] = "default";
            $options['crb_num_of_links_to_show'] = 5;
            $options['crb_default_offset'] = 0;
            $options['crb_cache_minutes'] = 1440;
            $options['crb_temp_before'] = base64_encode(urlencode(self::TEMP_BEFORE));
            $options['crb_temp_link'] = base64_encode(urlencode(self::TEMP_LINK));
            $options['crb_temp_after'] = base64_encode(urlencode(self::TEMP_AFTER));
            $options['crb_image_size'] = 'thumbnail';
            $options['crb_placeholder_path'] = '';
            $options['crb_content_filter'] = 0;
            $options['crb_choose_template'] = 'crb-template-simple.css';
            $options['crb_css_override'] = array('desc' => array('columns' => 3, 'gap' => 20), 'mob' => array('columns'=> 2, 'gap' => 10));

            $options['crb_installed'] = "true";
            update_option('linkate-posts', $options);
        }

        // on plugin update check existing options and add missing
        if ($after_update == true) {
            $options['crb_show_after_content'] = isset($options['crb_show_after_content']) ? $options['crb_show_after_content'] : "false";
            $options['crb_hide_existing_links'] =  isset($options['crb_hide_existing_links']) ? $options['crb_hide_existing_links'] : "true";
            $options['crb_show_for_pages'] = isset($options['crb_show_for_pages']) ? $options['crb_show_for_pages'] : "false";
            $options['crb_show_latest'] = isset($options['crb_show_latest']) ? $options['crb_show_latest'] : "false";
            $options['crb_css_tuning'] = isset($options['crb_css_tuning']) ? $options['crb_css_tuning'] : "default";
            $options['crb_num_of_links_to_show'] = isset($options['crb_num_of_links_to_show']) ? $options['crb_num_of_links_to_show'] : 5;
            $options['crb_default_offset'] = isset($options['crb_default_offset']) ? $options['crb_default_offset'] : 0;
            $options['crb_cache_minutes'] = isset($options['crb_cache_minutes']) ? $options['crb_cache_minutes'] : 1440;
            $options['crb_temp_before'] = isset($options['crb_temp_before']) ? $options['crb_temp_before'] : base64_encode(urlencode(self::TEMP_BEFORE));
            $options['crb_temp_link'] = isset($options['crb_temp_link']) ? $options['crb_temp_link'] : base64_encode(urlencode(self::TEMP_LINK));
            $options['crb_temp_after'] = isset($options['crb_temp_after']) ? $options['crb_temp_after'] : base64_encode(urlencode(self::TEMP_AFTER));
            $options['crb_image_size'] = isset($options['crb_image_size']) ? $options['crb_image_size'] : 'thumbnail';
            $options['crb_placeholder_path'] = isset($options['crb_placeholder_path']) ? $options['crb_placeholder_path'] : '';
            $options['crb_content_filter'] = isset($options['crb_content_filter']) ? $options['crb_content_filter'] : 0;
            $options['crb_choose_template'] = isset($options['crb_choose_template']) ? $options['crb_choose_template'] : 'crb-template-old.css';
            $options['crb_css_override'] = isset($options['crb_css_override']) ? $options['crb_css_override'] : array('desc' => array('columns' => 3, 'gap' => 20), 'mob' => array('columns'=> 2, 'gap' => 10));

            update_option('linkate-posts', $options);
        }
    }

    static function meta_assets() {
        $options = get_option('linkate-posts');

        $template = isset($options['crb_choose_template']) ? $options['crb_choose_template'] : 'crb-template-simple.css';
        if ($template == 'none')
            return false; // don't load any

        if ($options['crb_css_tuning'] == 'important')
            $template = str_replace('.css', '-important.css', $template);

        wp_register_style( 'crb-template', plugins_url( '/css/'.$template, __FILE__ ), '', CL_Related_Block::get_version() );
        wp_enqueue_style ('crb-template');
    }

    static function meta_assets_override() {
        $options = get_option('linkate-posts');
        $template = isset($options['crb_choose_template']) ? $options['crb_choose_template'] : 'crb-template-simple.css';;
        if (!$template || $template == 'none')
            return false; // don't load any

        wp_register_style( 'crb-template-override', plugins_url( '/css/crb-template-admin-options.css', __FILE__ ), '', CL_Related_Block::get_version() );
        wp_enqueue_style ('crb-template-override');

        $desc_cols = array();
        for ($i = 0; $i < intval($options['crb_css_override']['desc']['columns']); $i++) {
            $desc_cols[] = '1fr';
        }
        $mob_cols = array();
        for ($i = 0; $i < intval($options['crb_css_override']['mob']['columns']); $i++) {
            $mob_cols[] = '1fr';
        }
        $custom_css = "
                .crb-container {
                    display: grid !important;
                    grid-template-columns: ". implode(' ', $desc_cols) ." !important;
                    grid-column-gap: " . $options['crb_css_override']['desc']['gap'] ."px !important; 
                }
                @media screen and (max-width: 40em) {
                .crb-container {
                    grid-template-columns: ". implode(' ', $mob_cols) ." !important;
                    grid-column-gap: " . $options['crb_css_override']['mob']['gap'] ."px !important; 
                }
            }";
        wp_add_inline_style( 'crb-template-override', $custom_css );
    }

    static function admin_assets() {
        self::meta_assets();
        wp_register_script( 'crb-script-admin', plugins_url( '/js/crb-admin.js', __FILE__ ), array( 'jquery' ), CL_Related_Block::get_version() . '-' . rand(1000, 10000) );
        wp_enqueue_script( 'crb-script-admin' );
    }
}

// Alias to use in theme templates
if (!function_exists("cherrylink_related_block")) {
    function cherrylink_related_block($atts = []) {
        $EXEC_TIME = microtime(true);
        $options = get_option('linkate-posts');
        _cherry_debug(__FUNCTION__, $options, 'Содержимое options в php/шорткоде');
        // check individual settings
        $output = '';
        $post_id = get_the_ID();
        $custom_show = CL_RB_Metabox::get_custom_show($post_id);
        _cherry_debug(__FUNCTION__, $custom_show, 'Вызов из php/шорткода. Результат get_custom_show для ID: ' . $post_id);
        if ($custom_show) {
            // normalize attribute keys, lowercase
            $atts = array_change_key_case( (array) $atts, CASE_LOWER );
 
            // override default attributes with user attributes
            $short_atts = shortcode_atts(
                array(
                    'offset' => false,
                    'num_links' => false,
                    'rel_type' => false,
                    'ignore_sorting' => false,
                    'excluded_cats' => false
                ), $atts
            );
            $output = CL_Related_Block::get_links(
                $short_atts['offset'], 
                $short_atts['num_links'], 
                $short_atts['rel_type'], 
                $short_atts['ignore_sorting'], 
                $short_atts['excluded_cats']
            );
        } 
        $time_elapsed_secs = microtime(true) - $EXEC_TIME;
        _cherry_debug(__FUNCTION__, $output, 'Переменная $output - это выводим на экран CRB MicroTime:'. $time_elapsed_secs);
        return $output;
    }
}

function cherrylink_related_block_shortcode ($atts = [], $content = null, $tag = '') {
    return cherrylink_related_block($atts);
}

function cherrylink_init_shortcode() {
    // Register shortcode
    add_shortcode( 'crb_show_block', 'cherrylink_related_block_shortcode' );
}

function _crb_init() {
    // Initial setup
    CL_Related_Block::fill_options();

    // Append to content if needed
    add_filter('the_content', array('CL_Related_Block','add_after_content'));
    
    add_action('init', 'cherrylink_init_shortcode');

    // Include styles & scripts
	add_action('wp_enqueue_scripts', array('CL_Related_Block','meta_assets'), 100);
	add_action('admin_enqueue_scripts', array('CL_Related_Block','admin_assets'), 100);

	add_action('wp_enqueue_scripts', array('CL_Related_Block','meta_assets_override'), 200);
	add_action('admin_enqueue_scripts', array('CL_Related_Block','meta_assets_override'), 200);

    // Include deps
    include('crb_metabox.php');
    include('crb_admin.php');

    // Enable Metaboxes
    _crb_metabox_init();

    // Setup updater
    // crb_config_updater();

    // After update action
    // add_action( 'upgrader_process_complete', 'crb_upgrade_function', 10, 2);
}


// Run plugin
_crb_init();

