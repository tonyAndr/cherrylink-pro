<?php
/*
Plugin Name: CherryLink Pro
Plugin URI: http://seocherry.ru/
Description: Плагин для упрощения ручной внутренней перелинковки. Поиск релевантных ссылок, ускорение монотонных действий, гибкие настройки, удобная статистика и экспорт.
Version: 0.9.2
Author: Anton SeoCherry.ru
Author URI: http://seocherry.ru/
Text Domain: cherrylink-td
*/

function linkate_posts($args = '')
{
    return LinkatePosts::execute($args);
}

function linkate_posts_mark_current()
{
    global $post, $linkate_posts_current_ID;
    $linkate_posts_current_ID = $post->ID;
}


// ========================================================================================= //
// ============================== Defines and Imports ============================== //
// ========================================================================================= //

define('CHERRYLINK_INITIAL_LIMIT', 100);
define('CHERRYLINK_TEXT_DOMAIN', 'cherrylink-td');
define('CHERRYLINK_DIR', plugin_dir_path(__FILE__));
define('CHERRYLINK_DIR_URL', plugin_dir_url(__FILE__));

if (!defined('LINKATE_DEBUG')) require(CHERRYLINK_DIR . '/cherrylink_debug.php');

if (!defined('LINKATE_INSTALL_LIBRARY')) require(CHERRYLINK_DIR . '/cherrylink_install.php');
if (!defined('LINKATE_EF_LIBRARY')) require(CHERRYLINK_DIR . '/cherrylink_editor_functions.php');

if (!defined('LINKATE_CF_LIBRARY')) require(CHERRYLINK_DIR . '/cherrylink_cf.php');
if (!defined('LINKATE_ACF_LIBRARY')) require(CHERRYLINK_DIR . '/cherrylink_acf.php');
if (!defined('LP_OT_LIBRARY')) require(CHERRYLINK_DIR . '/cherrylink_output_tags.php');
if (!defined('LP_ADMIN_SUBPAGES_LIBRARY')) require(CHERRYLINK_DIR . '/cherrylink_admin_subpages.php');
if (!defined('LINKATE_TERMS_LIBRARY')) require(CHERRYLINK_DIR . '/cherrylink_terms.php');
if (!defined('LINKATE_INDEX_LIBRARY')) require(CHERRYLINK_DIR . '/cherrylink_index.php');
if (!defined('LINKATE_STATISTICS_LIBRARY')) require(CHERRYLINK_DIR . '/cherrylink_statistics.php');
if (!defined('LINKATE_STOPWORDS_LIBRARY')) require(CHERRYLINK_DIR . '/cherrylink_stopwords.php');

if (!defined('LINKATE_STATS_COLUMN_LIBRARY')) require(CHERRYLINK_DIR . '/cherrylink_stats_column.php');
// if (!defined('LINKATE_CRB_LIBRARY')) require(CHERRYLINK_DIR . '/crb_main.php');

if (!defined('LINKATE_GUTENBERG_ASSETS')) require(CHERRYLINK_DIR . '/cherrylink_gutenberg.php');
if (!defined('LINKATE_TAILWIND_CONSTANTS')) require(CHERRYLINK_DIR . '/cherrylink_tailwind_constants.php');
if (!defined('LINKATE_STEMMER_RU')) require(CHERRYLINK_DIR . "/cherrylink_stemmer_ru.php");
if (!defined('LINKATE_INDEX_HELPERS')) require(CHERRYLINK_DIR . "/cherrylink_index_helpers.php");
if (!defined('LINKATE_STATS_SCHEME')) require(CHERRYLINK_DIR . "/cherrylink_stats_scheme.php");


// ========================================================================================= //
// ============================== Main Class ============================== //
// ========================================================================================= //

class LinkatePosts
{
    static $version = 0;

    static function get_linkate_version()
    {
        $plugin_data = get_file_data(__FILE__, array('version' => 'Version'), 'plugin');
        LinkatePosts::$version = $plugin_data['version'];

        return $plugin_data['version'];
    } // get_linkate_version

    // check if plugin's admin page is shown
    static function linkate_is_plugin_admin_page($page = 'settings')
    {
        $current_screen = get_current_screen();

        if ($page == 'settings' && $current_screen->id == 'settings_page_cherrylink-pro') {
            return true;
        }

        return false;
    } // linkate_is_plugin_admin_page

    // add settings link to plugins page
    static function linkate_plugin_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('options-general.php?page=cherrylink-pro') . '" title="Настройки CherryLink">Настройки</a>';

        array_unshift($links, $settings_link);

        return $links;
    } // linkate_plugin_action_links

    // ========================================================================================= //
    // ============================== Main function [Get Posts] ============================== //
    // ========================================================================================= //

    static function execute($args = '', $option_key = 'linkate-posts')
    {
        global $table_prefix, $wpdb;
        $table_name = $table_prefix . 'linkate_posts';
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        // First we process any arguments to see if any defaults have been overridden
        $arg_options = link_cf_parse_args($args);
        $is_term = isset($arg_options['is_term']) ? $arg_options['is_term'] : 0;
        $offset = isset($arg_options['offset']) ? $arg_options['offset'] : 0;
        $linkate_posts_current_ID = isset($arg_options['manual_ID']) ? intval($arg_options['manual_ID']) : -1;

        // switch output between editors (classic/gutenberg) and related block if empty
        $presentation_mode = (isset($arg_options['mode']) && !empty($arg_options['mode'])) ? $arg_options['mode'] : 'related_block';
        $postid = link_cf_current_post_id($linkate_posts_current_ID);

        // Next we retrieve the stored options and use them unless a value has been overridden via the arguments
        $options = link_cf_set_options($option_key, $arg_options);

        try {
            $use_stemming = $options['use_stemming'] === "true";
            $stemmer = new Stem\LinguaStemRu();
            $stemmer->enable_stemmer($use_stemming);
        } catch (Exception $e) {
        }

        $index_helpers = new CL_Index_Helpers($stemmer, $options, $wpdb);
        if ($postid && 0 < $options['limit_ajax']) {
            $match_tags = ($options['match_tags'] !== 'false');
            $exclude_cats = ($options['excluded_cats'] !== '');
            $include_cats = ($options['included_cats'] !== '');
            $exclude_authors = ($options['excluded_authors'] !== '');
            $include_authors = ($options['included_authors'] !== '');
            $exclude_posts = (trim($options['excluded_posts']) !== '');
            $exclude_posts = implode(",", array_filter(explode(",", $exclude_posts)));
            $include_posts = (trim($options['included_posts']) !== '');
            $include_posts = implode(",", array_filter(explode(",", $include_posts)));
            $match_category = ($options['match_cat'] === 'true');
            $match_author = ($options['match_author'] === 'true');
            $use_tag_str = ('' != trim($options['tag_str']));
            $omit_current_post = ($options['omit_current_post'] !== 'false');
            $ignore_relevance = ($options['ignore_relevance'] !== 'false');
            $match_against_title = ($options['match_all_against_title'] !== 'false');
            $hide_pass = ($options['show_private'] === 'false');
            $check_age = ('none' !== $options['age']['direction']);
            $check_custom = (trim($options['custom']['key']) !== '');
            $limit = $offset . ', ' . $options['limit_ajax'];
            $consider_max_incoming_links = (isset($options['consider_max_incoming_links']) && $options['consider_max_incoming_links'] !== 'false');
            //get the terms to do the matching

            // check if user typed in custom filter text
            if (isset($arg_options['custom_text']) && !empty(trim($arg_options['custom_text']))) {
                $cleaned_custom_text = $index_helpers->linkate_sp_mb_clean_words($arg_options['custom_text']);
                // replace everything with custom text
                $contentterms = $cleaned_custom_text;
                $titleterms = $cleaned_custom_text;
                $customterms = $cleaned_custom_text;
                $suggestions = $cleaned_custom_text;
            } else { // proceed the normal way
                list($contentterms, $titleterms, $customterms, $suggestions) = $index_helpers->linkate_sp_terms_by_freq($postid, $options['num_terms'], $is_term);
            }

            // these should add up to 1.0
            $weight_content = $options['weight_content'];
            $weight_title = $options['weight_title'];
            $weight_custom = $options['weight_custom'];
            // below a threshold we ignore the weight completely and save some effort
            if ($weight_content < 0.001) $weight_content = (int) 0;
            if ($weight_title < 0.001) $weight_title = (int) 0;
            if ($weight_custom < 0.001) $weight_custom = (int) 0;

            $count_content = mb_substr_count($contentterms, ' ') + 1;
            $count_title = mb_substr_count($titleterms, ' ') + 1;
            $count_custom  = mb_substr_count($customterms, ' ') + 1;
            if ($weight_content) $weight_content = 57.0 * $weight_content / $count_content;
            if ($weight_title) $weight_title = 18.0 * $weight_title / $count_title;
            if ($weight_custom) $weight_custom = 24.0 * $weight_custom / $count_custom;

            $rel_ids = false;
            $in_relevant_clause = '';

            // for relevant results we get ids of matching posts from linkate_posts using fulltext search
            if (!$ignore_relevance) {
                if (isset($cleaned_custom_text)) {
                    $words = explode(' ', $cleaned_custom_text);
                    $regex = implode('|', $words);

                    $query = "SELECT ID FROM $wpdb->posts WHERE post_content REGEXP '{$regex}' OR post_title REGEXP '{$regex}' LIMIT 0, 1000";
                    $rel_ids = $wpdb->get_col($query);
                } else {

                    $sql = "SELECT pID FROM (SELECT pID, ";
                    $sql .= link_cf_score_fulltext_match($table_name, $weight_title, $titleterms, $weight_content, $contentterms, $weight_custom, $customterms, $match_against_title);
                    $sql .= " WHERE " . link_cf_where_fulltext_match($weight_title, $titleterms, $weight_content, $contentterms, $weight_custom, $customterms, $match_against_title);
                    $sql .= " AND pID <> $postid AND is_term = 0 ORDER BY score DESC LIMIT 0, 1000) as linkate_table";
                    _cherry_debug(__FUNCTION__, $sql, 'wp_linkate_posts SQL query');
                    $EXEC_TIME = microtime(true);
                    $rel_ids = $wpdb->get_col($sql);
                    $time_elapsed_secs = microtime(true) - $EXEC_TIME;
                    _cherry_debug(__FUNCTION__, count($rel_ids), 'Результат SELECT wp_linkate_posts, время выполнения: ' . $time_elapsed_secs);
                }
                if ($rel_ids) {
                    $in_relevant_clause = " ID IN (" . implode(",", $rel_ids) . ") ";
                }
            }

            // build and execute main query
            if ($ignore_relevance || $rel_ids) {
                $sql = "SELECT ID,post_content,post_title,post_author,post_excerpt,post_date FROM $wpdb->posts ";

                if ($check_custom) $sql .= "LEFT JOIN $wpdb->postmeta wpp ON post_id = ID ";

                // build the 'WHERE' clause
                $where = array();
                if ($in_relevant_clause) $where[] = $in_relevant_clause; // add relevant ids
                $where[] = link_cf_where_show_status($options['status']);

                if ($is_term == 0) { // we don't need these if we are editing taxonomies
                    if ($match_category) $where[] = link_cf_where_match_category($postid);
                    if ($match_tags) $where[] = link_cf_where_match_tags($options['match_tags']);
                    if ($match_author) $where[] = link_cf_where_match_author();
                    if ($omit_current_post) $where[] = link_cf_where_omit_post($linkate_posts_current_ID);
                    if ($check_custom) $where[] = link_cf_where_check_custom($options['custom']['key'], $options['custom']['op'], $options['custom']['value']);
                }
                $where[] = link_cf_where_show_pages($options['show_pages'], $options['show_customs']);
                if ($include_cats) $where[] = link_cf_where_included_cats($options['included_cats']);
                if ($exclude_cats) $where[] = link_cf_where_excluded_cats($options['excluded_cats']);
                if ($exclude_authors) $where[] = link_cf_where_excluded_authors($options['excluded_authors']);
                if ($include_authors) $where[] = link_cf_where_included_authors($options['included_authors']);
                if ($exclude_posts) $where[] = link_cf_where_excluded_posts(trim($options['excluded_posts']));
                if ($include_posts) $where[] = link_cf_where_included_posts(trim($options['included_posts']));
                if ($use_tag_str) $where[] = link_cf_where_tag_str($options['tag_str']);
                if ($hide_pass) $where[] = link_cf_where_hide_pass();
                if ($check_age) $where[] = link_cf_where_check_age($options['age']['direction'], $options['age']['length'], $options['age']['duration']);

                $sql .= "WHERE " . implode(' AND ', $where);
                if ($check_custom) $sql .= " GROUP BY $wpdb->posts.ID";

                // Save original order
                if ($options['ignore_sorting'] && $ignore_relevance) {
                    $sql .= " ORDER BY FIND_IN_SET(ID,'" . trim($options['included_posts']) . "')";
                }

                if ($ignore_relevance) {
                    $sql .= " LIMIT $limit";
                } else {
                    // sorting by fulltext match score
                    $sql .= " ORDER BY FIELD(ID, " . implode(",", $rel_ids) . ") LIMIT $limit";
                }



                _cherry_debug(__FUNCTION__, $sql, 'wp_posts SQL query');
                $EXEC_TIME = microtime(true);
                $results = $wpdb->get_results($sql);
                $time_elapsed_secs = microtime(true) - $EXEC_TIME;
                _cherry_debug(__FUNCTION__, count($results), 'Результат SELECT wp_posts, время выполнения: ' . $time_elapsed_secs);

                // remove duplicates
                $ids_list = array();
                $duplicates = array();
                foreach ($results as $k => $v) {
                    if (in_array($v->ID, $ids_list)) {
                        $duplicates[] = $k;
                    } else {
                        $ids_list[] = $v->ID;
                    }
                }
                $results = array_filter($results, function ($k) use ($duplicates) {
                    return !in_array($k, $duplicates);
                }, ARRAY_FILTER_USE_KEY);

                _cherry_debug(__FUNCTION__, count($results), 'После фильтра дублей');


                // filter buy max incoming links
                if ($consider_max_incoming_links && $presentation_mode !== 'related_block') {
                    $exclude_result = [];
                    foreach ($results as $k => $v) {
                        $id_to_check = $v->ID;
                        $in_cnt = intval(get_post_meta($id_to_check, "cherry_income", true));
                        if ($in_cnt >= intval($options['max_incoming_links'])) {
                            $exclude_result[] = $k;
                        }
                    }
                    $results = array_filter($results, function ($k) use ($exclude_result) {
                        return !in_array($k, $exclude_result);
                    }, ARRAY_FILTER_USE_KEY);
                }
            } else {
                $results = false;
            }
        } else {
            $results = false;
        }

        _cherry_debug(__FUNCTION__, $presentation_mode, 'Как обработать результаты?');
        switch ($presentation_mode) {
            // case 'related_block':
            //     return CL_Related_Block::prepare_related_block($postid, $results, $option_key, $options);
            //     break;
            case 'gutenberg':
                return LinkatePosts::prepare_for_cherry_gutenberg($results, $option_key, $options);
                break;
            case 'classic':
            default:
                return LinkatePosts::prepare_for_cherrylink_panel($results, $option_key, $options);
                break;
        }
    }

    static function prepare_for_cherry_gutenberg($results, $option_key, $options)
    {
        $output_template = '"data-url":"{url}","data-titleseo":"{title_seo}","data-title":"{title}","data-category":"{categorynames}","data-date":"{date}","data-author":"{author}","data-postid":"{postid}","data-imagesrc":"{imagesrc}","data-anons":"{anons}","data-suggestions":"{suggestions}"';

        $results_count = 0;
        if ($results) {
            $results = link_cf_get_suggestions_for_ids($results);
            $out_final = $output_template;
            $translations = link_cf_prepare_template($out_final);

            foreach ($results as $result) {
                $items[] = "{" . link_cf_expand_template($result, $out_final, $translations, $option_key) . "}";
            }

            if ($options['sort']['by1'] !== '') $items = link_cf_sort_items($options['sort'], $results, $option_key, $options['group_template'], $items);
            $output = "[" . implode(",", str_replace("\n", "", $items)) . "]";

            $results_count = sizeof($results);
            $send_data['links'] = $output;
            $send_data['count'] = $results_count;
        } else {
            $send_data['links'] = [];
            $send_data['count'] = -1;
        }
        return $send_data;
    }

    static function prepare_for_cherrylink_panel($results, $option_key, $options)
    {
        $output_template_item_prefix = '
		<div class="linkate-item-container">
			<div class="linkate-controls">
				<div class="link-counter" title="Найдено в тексте / переход к ссылке">0</div>
				<div class="link-preview" title="Редактировать статью в новой вкладке"></div>
				
			</div>
			<div class="linkate-link" title="Нажмите для вставки в текст" data-url="{url}" data-titleseo="{title_seo}" data-title="{title}" data-category="{categorynames}" data-date="{date}" data-author="{author}" data-postid="{postid}" data-imagesrc="{imagesrc}" data-anons="{anons}" data-suggestions="{suggestions}"><span class="link-title" >';
        // <div class="link-add-to-block" title="Добавить в блок релевантных ссылок"></div><div class="link-del-from-block btn-hidden" title="Убрать из блока ссылок"></div>

        $output_template_item_suffix = '</span></div>
			<div class="link-right-controls"><div class="link-individual-stats-income" title="Сколько раз сослались на эту статью">?</div><div class="link-individual-stats-out" title="Сколько исходящих ссылок содержит статья">?</div><div class="link-suggestions" title="Подсказка"></div></div></div>';

        $results_count = 0;
        if ($results) {
            $results = link_cf_get_suggestions_for_ids($results);
            $output_template = (!isset($options['output_template']) || $options['output_template'] === 'h1' || $options['output_template'] === '{title}') ? '{title}' : '{title_seo}';
            $out_final = $output_template_item_prefix . $output_template . $output_template_item_suffix;
            $translations = link_cf_prepare_template($out_final);
            foreach ($results as $result) {
                $items[] = link_cf_expand_template($result, $out_final, $translations, $option_key);
            }
            if ($options['sort']['by1'] !== '') $items = link_cf_sort_items($options['sort'], $results, $option_key, $options['group_template'], $items);
            $output = implode(($options['divider']) ? $options['divider'] : "\n", $items);

            $results_count = sizeof($results);
        } else {
            // we display the blank message, with tags expanded if necessary
            $translations = link_cf_prepare_template($options['none_text']);
            $output = "<p>" . link_cf_expand_template(array(), $options['none_text'], $translations, $option_key) . "</p>";
        }

        $send_data['links'] = trim($output);
        $send_data['count'] = $results_count;
        return $send_data;
    }

    // save some info
    static function lp_activate()
    {
        global $wpdb;
        $options = get_option('linkate_posts_meta', array());

        if (empty($options['first_version'])) {
            $options['first_version'] = LinkatePosts::get_linkate_version();
            $options['first_install'] = current_time('timestamp');
            update_option('linkate_posts_meta', $options);

            $index_helper = new CL_Index_Helpers(null, $options, $wpdb);
            $amount_of_db_rows = $index_helper->get_indexable_posts_count();
            if ($amount_of_db_rows > CHERRYLINK_INITIAL_LIMIT)
                set_transient('cherry-manual-indexation-needed', true, 20);
        }
    } // lp_activate

} // linkateposts class


// call install func on activation
add_action('activate_' . str_replace('-admin', '', link_cf_plugin_basename(__FILE__)), 'linkate_posts_install');
// call on update
add_action('upgrader_process_complete', 'linkate_on_update', 10, 2);
// call after update
add_action('plugins_loaded', 'linkate_redirectToUpdatePlugin');

// call this when plugin updates
function linkate_on_update($upgrader_object, $options)
{
    $current_plugin_path_name = str_replace('-admin', '', link_cf_plugin_basename(__FILE__));

    if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        foreach ($options['plugins'] as $each_plugin) {
            if ($each_plugin == $current_plugin_path_name) {
                // set to 1 - we need it to run update script after plugin was updated by WP
                set_transient('cherrylink_updated', 1);
                break;
            }
        }
    }
}
// call this after plugin updates
function linkate_redirectToUpdatePlugin()
{
    if (get_transient('cherrylink_updated') && current_user_can('update_plugins')) {
        linkate_posts_install();
        set_transient('cherrylink_updated', 0);
    } // endif;
} // redirectToUpdatePlugin


function cherrylink_activation_notice()
{
    $options_meta = get_option('linkate_posts_meta', []);
    $index_process_status = (isset($options_meta['indexing_process']) && !empty($options_meta['indexing_process'])) ? $options_meta['indexing_process'] : 'VALUE_NOT_EXIST';


    /* Check transient, if available display notice */
    if (get_transient('cherry-manual-indexation-needed')) {
?>
        <div class="notice notice-warning is-dismissable">
            <p><strong>CherryLink Pro</strong> установлен!</p>
            <p>Для начала работы с ним <strong>необходимо просканировать ваш сайт</strong>.</p>
            <p>Перейдите в настройки плагина на вкладку <a href="<?php echo site_url() . '/wp-admin/options-general.php?page=cherrylink-pro&subpage=scan'; ?>">Сканирование</a>, и нажмите на кнопку "<strong>Начать сканирование</strong>".</p>
        </div>
    <?php
        /* Delete transient, only display this notice once. */
        delete_transient('cherry-manual-indexation-needed');
    } else if ($index_process_status === 'VALUE_NOT_EXIST' || $index_process_status === 'IN_PROGRESS') {
    ?>
        <div class="notice notice-warning is-dismissable">
            <p><strong>CherryLink Pro</strong>: сканирование не завершено!</p>
            <p>Плагин может работать некорректно или не видеть все записи, <strong>необходимо завершить сканирование</strong>.</p>
            <p>Перейдите в настройки плагина на вкладку <a href="<?php echo site_url() . '/wp-admin/options-general.php?page=cherrylink-pro&subpage=scan'; ?>">Сканирование</a>, и нажмите на кнопку "<strong>Начать сканирование</strong>".</p>
        </div>
<?php
    }
}

if (is_admin()) {
    require(dirname(__FILE__) . '/cherrylink_admin_options.php');

    if (linkate_callDelay() && linkate_lastStatus()) {
        $r = true;
    }
    if (linkate_callDelay() && !linkate_lastStatus()) {
        $r = false;
    }
    if (!linkate_callDelay()) {
        $r = linkate_checkNeededOption();
    }
    if ($r) {
        require(CHERRYLINK_DIR . '/cherrylink_editor_ui.php');
    }
}

function linkate_posts_wp_admin_style()
{
    if (LinkatePosts::linkate_is_plugin_admin_page('settings')) {
        wp_register_style('cherrylink-css-admin', plugins_url('', __FILE__) . '/css/cherry-admin.css', false, LinkatePosts::$version);
        wp_register_style('cherrylink-css-admin-table', plugins_url('', __FILE__) . '/css/tabulator.css', false, LinkatePosts::$version);
        wp_enqueue_style('cherrylink-css-admin');
        wp_enqueue_style('cherrylink-css-admin-table');

        wp_register_script('cherrylink-js-tailwind', plugins_url('/js/tailwind.js', __FILE__), array(), LinkatePosts::get_linkate_version());
        wp_register_script('cherrylink-js-admin', plugins_url('/js/cherry-admin.js', __FILE__), array('jquery'), LinkatePosts::get_linkate_version());
        wp_register_script('cherrylink-js-admin-csv', plugins_url('/js/cherry-admin-csv.js', __FILE__), array('jquery'), LinkatePosts::get_linkate_version());

        wp_register_script('cherrylink-js-admin-table', plugins_url('/js/tabulator.min.js', __FILE__), array('jquery'), LinkatePosts::get_linkate_version());
        // wp_register_script( 'cherrylink-js-admin-table-wrapper', plugins_url( '/js/jquery_wrapper.js', __FILE__ ), array( 'jquery', 'cherrylink-js-admin-table' ), LinkatePosts::get_linkate_version() );

        wp_register_script('cherrylink-js-admin-stopwords', plugins_url('/js/cherry-admin-stopwords.js', __FILE__), array('jquery'), LinkatePosts::get_linkate_version());
        wp_register_script('cherrylink-js-admin-index', plugins_url('/js/cherry-admin-index.js', __FILE__), array('jquery', 'cherrylink-js-admin-stopwords'), LinkatePosts::get_linkate_version());

        $options = (array) get_option('linkate-posts', []);
        $scheme_exists = array("state" => isset($options['linkate_scheme_exists']) ? true : false);
        wp_localize_script('cherrylink-js-admin', 'scheme', $scheme_exists);

        wp_enqueue_script('cherrylink-js-tailwind');
        wp_enqueue_script('cherrylink-js-admin');
        wp_enqueue_script('cherrylink-js-admin-table');
        wp_enqueue_script('cherrylink-js-admin-stopwords');
        wp_enqueue_script('cherrylink-js-admin-index');
        wp_enqueue_script('cherrylink-js-admin-csv');
    }
}

function linkate_posts_init()
{
    global $wpdb;
    load_plugin_textdomain(CHERRYLINK_TEXT_DOMAIN);

    LinkatePosts::get_linkate_version();

    add_action('admin_notices', 'cherrylink_activation_notice');

    $cl_index_helper = new CL_Index_Helpers(null, null, $wpdb);
    //install the actions to keep the index up to date
    // add_action('rest_after_insert_post', 'linkate_sp_save_index_entry', 1000000000, 3);
    add_action('wp_insert_post', 'linkate_sp_save_index_entry', 100000, 3);
    add_action('delete_post', array($cl_index_helper, 'linkate_sp_delete_index_entry'), 1);

    // add_action('create_term', 'linkate_sp_save_index_entry_term', 1, 3);
    // add_action('edited_term', 'linkate_sp_save_index_entry_term', 1, 3);
    // add_action('delete_term', array('CL_Index_Helpers','linkate_sp_delete_index_entry'), 1, 4);

    add_action('admin_enqueue_scripts', 'linkate_posts_wp_admin_style', 1);

    // additional links in plugin description
    add_filter(
        'plugin_action_links_' . basename(dirname(__FILE__)) . '/' . basename(__FILE__),
        array('LinkatePosts', 'linkate_plugin_action_links')
    );
} // init

function linkate_check_update()
{

    require 'updater/plugin-update-checker.php';

    $update_checker = Puc_v4_Factory::buildUpdateChecker(
        'https://github.com/tonyAndr/cherrylink-pro',
        __FILE__,
        'cherrylink-pro'
    );

    $update_checker->setBranch('main');
}

// linkate_check_update();
add_action('init', 'linkate_posts_init', 1);
register_activation_hook(__FILE__, array('LinkatePosts', 'lp_activate'));
add_action('plugins_loaded', 'linkate_check_update');


function cherry_write_log($data)
{
    if (true === WP_DEBUG) {
        if (is_array($data) || is_object($data)) {
            error_log(print_r($data, true));
        } else {
            error_log($data);
        }
    }
}
