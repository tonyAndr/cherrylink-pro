<?php
/*
 * CherryLink Plugin
 */

// Disable direct access
defined('ABSPATH') || exit;


// ========================================================================================= //
// ============================== CherryLink Scheme Creation  ============================== //
// ========================================================================================= //

class CL_Stats_Scheme
{

    public $options;
    public $wpdb;
    public $table_prefix;

    public function __construct($options, $wpdb)
    {
        $this->options = $options;
        $this->wpdb = $wpdb;

        $this->table_prefix = $wpdb->prefix;
    }

    //add_action('wp_ajax_linkate_create_links_scheme', 'linkate_create_links_scheme');
    public function linkate_create_links_scheme($offset = 0, $batch = 200)
    {
        $options = get_option('linkate-posts');

        $table_name_scheme = $this->table_prefix . 'linkate_scheme';
        // Truncate on first call
        if ($offset == 0) {
            $this->wpdb->query("TRUNCATE `$table_name_scheme`");
        }

        // TERM SCHEME on FIRST CALL ONLY
        // !!! DISABLED FOR TERMS COMPLETELY
        // if ($offset == 0) {
        //     // $amount_of_db_rows = $amount_of_db_rows + $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->terms");
        //     //doing the same with terms (category, tag...)
        //     $start = 0;
        //     while ($terms = $this->wpdb->get_results("SELECT `term_id` FROM $this->wpdb->terms LIMIT $start, $batch", ARRAY_A)) {
        //         $query_values = array();
        //         reset($terms);
        //         foreach ($terms as $term) {
        //             $termID = $term['term_id'];

        //             $descr = '';
        //             $descr .= term_description($termID); // standart
        //             // custom plugins sp-category && f-cattxt
        //             $opt = get_option('category_' . $termID);
        //             if ($opt && (function_exists('show_descr_top') || function_exists('contents_sp_category'))) {
        //                 $descr .= $opt['descrtop'] ? ' ' . $opt['descrtop'] : '';
        //                 $descr .= $opt['descrbottom'] ? ' ' . $opt['descrbottom'] : '';
        //             }

        //             $query_values[] = $this->linkate_scheme_get_add_row_query($descr, $termID, 1);
        //         }
        //         $query_values = array_filter($query_values);

        //         if (!empty($query_values)) {
        //             $query_values = implode(",", $query_values);
        //             $this->wpdb->query("INSERT INTO `$table_name_scheme` (source_id, source_type, target_id, target_type, ankor_text, external_url) VALUES $query_values");
        //         }

        //         $start += $batch;
        //     }
        //     unset($terms);
        //     $this->wpdb->flush();
        // }

        $posts = $this->wpdb->get_results("SELECT `ID`, `post_content`, `post_type` 
									FROM $this->wpdb->posts 
									WHERE `post_type` not in ('attachment', 'revision', 'nav_menu_item') 
									LIMIT $offset, $batch", ARRAY_A);
        reset($posts);

        $query_values = array();
        foreach ($posts as $post) {
            $postID = $post['ID'];
            $query_values[] = $this->linkate_scheme_get_add_row_query($post['post_content'], $postID, 0);
        }
        $query_values = array_filter($query_values);

        if (!empty($query_values)) {
            $query_values = implode(",", $query_values);
            $this->wpdb->query("INSERT INTO `$table_name_scheme` (source_id, source_type, target_id, target_type, ankor_text, external_url) VALUES $query_values");
        }
        unset($options);
        unset($query_values);
        unset($posts);
        $this->wpdb->flush();
    }

    public function linkate_scheme_update_option_timestamp()
    {
        $options = get_option('linkate-posts');
        $options['linkate_scheme_exists'] = true;
        $options['linkate_scheme_time'] = time();

        update_option('linkate-posts', $options);
    }


    // Manipulate DB
    public function linkate_scheme_delete_record($id, $type)
    {
        // delete record by post ID or term ID
        global $wpdb, $table_prefix;
        $table_name = $table_prefix . 'linkate_scheme';
        $wpdb->query("DELETE FROM $table_name WHERE source_id = $id AND source_type = $type");
        return $id;
    }

    public function linkate_scheme_add_row($str, $post_id, $is_term)
    {
        global $wpdb, $table_prefix;
        $table_name = $table_prefix . 'linkate_scheme';


        $values_string = $this->linkate_scheme_get_add_row_query($str, $post_id, $is_term);

        if (!empty($values_string))
            $wpdb->query("INSERT INTO `$table_name` (source_id, source_type, target_id, target_type, ankor_text, external_url) VALUES $values_string");
    }

    public function linkate_scheme_get_add_row_query($str, $post_id, $is_term)
    {
        // quit if there is no content
        if (empty($str) || $str === false)
            return;
        // set error level, get rid of some warnings
        $internalErrors = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8'));
        // Restore error level
        libxml_use_internal_errors($internalErrors);
        $selector = new DOMXPath($doc);
        $result = $selector->query('//a'); //get all <a>

        if (!$result || count($result) === 0) {
            unset($internalErrors);
            libxml_clear_errors();
            unset($doc);
            unset($selector);
            unset($result);
            unset($prohibited);
            return '';
        }

        $target_id = 0;
        $target_type = 0;
        $values_string = '';
        $prohibited = array('.jpg', '.jpeg', '.tiff', '.bmp', '.psd', '.png', '.gif', '.webp', '.doc', '.docx', '.xlsx', '.xls', '.odt', '.pdf', '.ods', '.odf', '.ppt', '.pptx', '.txt', '.rtf', '.mp3', '.mp4', '.wav', '.avi', '.ogg', '.zip', '.7z', '.tar', '.gz', '.rar', 'attachment');

        $outgoing_count = 0;
        // loop through all found items
        foreach ($result as $node) {
            $href = $node->getAttribute('href');
            if (empty($href)) continue; // no href - no need

            // if its doc,file or img - skip
            $is_doc = false;
            foreach ($prohibited as $v) {
                if (strpos($href, $v) !== false) {
                    $is_doc = true;
                    break;
                }
            }

            if ($is_doc) continue;

            // remove some escaping stuff
            $href = trim(str_replace("\"", "", str_replace("\\", "", $href)));
            if (empty($href)) continue; // no href - no need

            $href = linkate_unparse_url($href, "full");
            if (empty($href)) continue; // no href - no need

            $ext_url = '';
            $ankor = esc_sql(trim($node->textContent));
            $ankor = empty($ankor) ? "_NOT_FOUND_" : $ankor;
            if (strpos($href, $_SERVER['HTTP_HOST']) !== false) {
                $target_id = url_to_postid($href); //target_post_id
                if ((strpos($href, '#') !== false) && ((int)$target_id === (int) $post_id)) {
                    // target same as post, internal navigation, omit
                    continue;
                }
                $target_type = 0;
                if ($target_id === 0) { // term_id
                    $target_id = $this->linkate_get_term_id_from_slug($href);
                    $target_type = 1;
                }
                if ($target_id === 0) {
                    $target_type = 255; // post not found, bit type limitation
                    $ext_url = esc_sql($href); // not external, but will save it for admin links preview
                }
            } else {
                $target_type = 2;
                $ext_url = esc_sql($href);
            }

            // add count to update post meta with outgoing links
            $outgoing_count++;

            if (!empty($values_string)) $values_string .= ',';
            $values_string .= "($post_id, $is_term, $target_id, $target_type, \"$ankor\", \"$ext_url\")";
            unset($href);
        }

        // for stats column
        update_post_meta((int) $post_id, "cherry_outgoing", $outgoing_count);
        //wp_cache_delete( (int) $post_id, 'post_meta' );

        unset($internalErrors);
        libxml_clear_errors();
        unset($doc);
        unset($selector);
        unset($result);
        unset($prohibited);

        return $values_string;
    }
    public function linkate_get_term_id_from_slug($url)
    {
        if (!isset($url)) {
            $url = '';
        }
        $current_url = rtrim($url, "/");
        $arr_current_url = explode("/", $current_url);
        $thecategory = get_category_by_slug(end($arr_current_url));
        if (!$thecategory) {
            unset($thecategory);
            return 0;
        }

        $catid = $thecategory->term_id;
        return $catid;
    }
}
