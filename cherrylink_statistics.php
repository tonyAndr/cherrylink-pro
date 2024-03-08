<?php
/*
 * CherryLink Plugin
 */

// Disable direct access
defined('ABSPATH') || exit;
// Define lib name
define('LINKATE_STATISTICS_LIBRARY', true);

// ========================================================================================= //
// ============================== CherryLink Editor Stats  ============================== //
// ========================================================================================= //

// In-Editor incoming links statistics
add_action('wp_ajax_linkate_generate_json', 'linkate_generate_json');
function linkate_generate_json()
{
    // get rows from db
    global $wpdb, $table_prefix;
    $table_name = $table_prefix . 'linkate_scheme';
    $gutenberg_data = json_decode(file_get_contents('php://input'), true);
    if (isset($gutenberg_data['this_id'])) {
        $this_id = $gutenberg_data['this_id'];
        $this_type = $gutenberg_data['this_type'];
    } else {
        $this_id = $_POST['this_id'];
        $this_type = $_POST['this_type'];
    }

    $this_type = $this_type == 'post' ? 0 : 1;
    $links = $wpdb->get_results("SELECT * FROM $table_name WHERE target_id = $this_id AND target_type = $this_type", ARRAY_A);
    if ($links != null && sizeof($links) > 0) {
        reset($links);
        $total_count = sizeof($links);

        $output_array = array();
        foreach ($links as $link) {
            // get source url and target url
            $source_url = '';
            if ($link['source_type'] == 0) { //post
                $source_url = get_permalink((int)$link['source_id']);
            } elseif ($link['source_type'] == 1) {
                $source_url = get_term_link((int)$link['source_id']);
            }

            $output_array[] = array(
                'source_id' => $link['source_id'],
                'source_url' => $source_url,
                'ankor' => $link['ankor_text']
            );
        }
        $json_array = array();
        $json_array['links'] = $output_array;
        $json_array['count'] = $total_count;
    } else {
        $json_array = array();
        $json_array['links'] = '';
        $json_array['count'] = 0;
    }
    unset($links);
    echo json_encode($json_array);
    wp_die();
}

// Get all needed posts type count to split csv generating into batches for better performance
add_action('wp_ajax_linkate_get_all_posts_count', 'linkate_get_all_posts_count');
function linkate_get_all_posts_count()
{
    global $wpdb, $table_prefix;
    $types = array_map(function ($el) {
        return "'" . $el . "'";
    }, $_POST['export_types']);
    $types = implode(",", $types);
    $count = 0;
    $count = $wpdb->get_var("SELECT COUNT(*) from " . $table_prefix . "posts WHERE post_type IN (" . $types . ")");
    echo $count;
    linkate_stats_remove_old(false); //remove old stats csv files
    wp_die();
}

function linkate_create_statistics_query($table_prefix, $is_term, $is_outgoing, $ids_query, $post_types, $bounds, $post_status = '')
{
    if (!$is_term) { //post
        if (!$is_outgoing) { //forward
            return "
                    SELECT " . $table_prefix . "posts.ID as source_id, " . $table_prefix . "posts.post_type, 
                    COALESCE(COUNT(scheme1.target_id), 0) AS count_targets, 
                    GROUP_CONCAT(scheme1.target_id SEPARATOR ';') AS targets, 
                    GROUP_CONCAT(scheme1.target_type SEPARATOR ';') AS target_types, 
                    GROUP_CONCAT(scheme1.ankor_text SEPARATOR ';') AS ankors, 
                    GROUP_CONCAT(scheme1.external_url SEPARATOR ';') AS ext_links, 
                    COALESCE(scheme2.count_sources, 0) AS count_sources
                    FROM
                        " . $table_prefix . "posts
                    LEFT JOIN
                        " . $table_prefix . "linkate_scheme AS scheme1 ON " . $table_prefix . "posts.ID = scheme1.source_id
                        AND (scheme1.source_type = 0 OR scheme1.source_type IS NULL)
                    LEFT JOIN
                        (
                            SELECT COUNT(*) as count_sources, target_id, target_type
                            FROM " . $table_prefix . "linkate_scheme
                            GROUP BY target_id, target_type
                            ) AS scheme2 ON " . $table_prefix . "posts.ID = scheme2.target_id AND (scheme2.target_type = 0 OR scheme2.target_type IS NULL)
                    WHERE " . $ids_query . " " // selected post IDs
                . $post_status
                . $post_types // post types
                . " GROUP BY " . $table_prefix . "posts.ID ORDER BY " . $table_prefix . "posts.ID ASC"
                . $bounds; // LIMIT X,Y
        } else {    // backwards
            return "
                    SELECT " . $table_prefix . "posts.ID as target_id, " . $table_prefix . "posts.post_type, COALESCE(COUNT(scheme1.source_id), 0) AS count_sources, GROUP_CONCAT(scheme1.source_id SEPARATOR ';') AS sources, GROUP_CONCAT(scheme1.source_type SEPARATOR ';') AS source_types, GROUP_CONCAT(scheme1.ankor_text SEPARATOR ';') AS ankors, GROUP_CONCAT(scheme1.external_url SEPARATOR ';') AS ext_links, COALESCE(scheme2.count_targets, 0) AS count_targets
                    FROM
                        " . $table_prefix . "posts
                    LEFT JOIN
                        " . $table_prefix . "linkate_scheme AS scheme1 ON " . $table_prefix . "posts.ID = scheme1.target_id
                        AND (scheme1.target_type = 0 OR scheme1.target_type IS NULL)
                    LEFT JOIN
                        (
                            SELECT COUNT(*) as count_targets, source_id, source_type
                            FROM " . $table_prefix . "linkate_scheme
                            GROUP BY source_id, source_type
                            ) AS scheme2 ON " . $table_prefix . "posts.ID = scheme2.source_id AND (scheme2.source_type = 0 OR scheme2.source_type IS NULL)
                    WHERE " . $ids_query . " " // selected post IDs
                . $post_types // post types
                . " GROUP BY " . $table_prefix . "posts.ID ORDER BY " . $table_prefix . "posts.ID ASC "
                . $bounds; // LIMIT X,Y
        }
    } else {    // terms
        if (!$is_outgoing) { //forward
            return "
                    SELECT " . $table_prefix . "terms.term_id as source_id, COALESCE(COUNT(scheme1.target_id), 0) AS count_targets, GROUP_CONCAT(scheme1.target_id SEPARATOR ';') AS targets, GROUP_CONCAT(scheme1.target_type SEPARATOR ';') AS target_types, GROUP_CONCAT(scheme1.ankor_text SEPARATOR ';') AS ankors, GROUP_CONCAT(scheme1.external_url SEPARATOR ';') AS ext_links, COALESCE(scheme2.count_sources, 0) AS count_sources
                    FROM
                        " . $table_prefix . "terms
                    LEFT JOIN
                        " . $table_prefix . "linkate_scheme AS scheme1 ON " . $table_prefix . "terms.term_id = scheme1.source_id
                        AND (scheme1.source_type = 1 OR scheme1.source_type IS NULL)
                    LEFT JOIN
                        (
                            SELECT COUNT(*) as count_sources, target_id, target_type
                            FROM " . $table_prefix . "linkate_scheme
                            GROUP BY target_id, target_type
                            ) AS scheme2 ON " . $table_prefix . "terms.term_id = scheme2.target_id AND (scheme2.target_type = 1 OR scheme2.target_type IS NULL)
                    GROUP BY " . $table_prefix . "terms.term_id
                    ORDER BY " . $table_prefix . "terms.term_id ASC";
        } else { // backwards
            return "
                    SELECT " . $table_prefix . "terms.term_id as target_id, COALESCE(COUNT(scheme1.source_id), 0) AS count_sources, GROUP_CONCAT(scheme1.source_id SEPARATOR ';') AS sources, GROUP_CONCAT(scheme1.source_type SEPARATOR ';') AS source_types, GROUP_CONCAT(scheme1.ankor_text SEPARATOR ';') AS ankors, GROUP_CONCAT(scheme1.external_url SEPARATOR ';') AS ext_links, COALESCE(scheme2.count_targets, 0) AS count_targets
                    FROM
                        " . $table_prefix . "terms
                    LEFT JOIN
                        " . $table_prefix . "linkate_scheme AS scheme1 ON " . $table_prefix . "terms.term_id = scheme1.target_id
                        AND (scheme1.target_type = 1 OR scheme1.target_type IS NULL)
                    LEFT JOIN
                        (
                            SELECT COUNT(*) as count_targets, source_id, source_type
                            FROM " . $table_prefix . "linkate_scheme
                            GROUP BY source_id, source_type
                            ) AS scheme2 ON " . $table_prefix . "terms.term_id = scheme2.source_id AND (scheme2.source_type = 1 OR scheme2.source_type IS NULL)
                    GROUP BY " . $table_prefix . "terms.term_id
                    ORDER BY " . $table_prefix . "terms.term_id ASC";
        }
    }
}

// WorkHorse
add_action('wp_ajax_linkate_generate_csv_or_json_prettyfied', 'linkate_generate_csv_or_json_prettyfied');
function linkate_generate_csv_or_json_prettyfied($is_custom_column = false, $custom_id = 0)
{
    // get rows from db
    global $wpdb, $table_prefix;
    $gutenberg_data = json_decode(file_get_contents('php://input'), true);
    $admin_preview_stats = false;
    $from_editor = false;
    $ids_query = "";
    $bounds = "";
    $post_status = "";
    $types = $table_prefix . "posts.post_type NOT IN ('attachment', 'nav_menu_item', 'revision', 'wp_block')";

    if (isset($_POST['admin_preview_stats'])) $admin_preview_stats = true;

    if (isset($_POST['post_ids'])) {
        $from_editor = true;
        $ids_query = $table_prefix . "posts.ID IN (" . $_POST['post_ids'] . ") AND ";
    } else if (isset($gutenberg_data['post_ids'])) {
        $from_editor = true;
        $ids_query = $table_prefix . "posts.ID IN (" . $gutenberg_data['post_ids'] . ") AND ";
    } else if ($is_custom_column) {
        $ids_query = $table_prefix . "posts.ID IN (" . $custom_id . ") AND ";
    }

    if (!isset($_POST['post_ids']) && !isset($gutenberg_data['post_ids']) && $custom_id === 0) {
        return false;
    }


    if (isset($_POST['stats_offset'])) {
        $bounds = " LIMIT " .  $_POST['stats_offset'] . "," . $_POST['stats_limit'];
    }

    if (isset($_POST['export_types'])) {
        $types = array_map(function ($el) {
            return "'" . $el . "'";
        }, $_POST['export_types']);
        $types = $table_prefix . "posts.post_type IN ( " . implode(",", $types) . ")";
    }

    if (isset($_POST['export_status'])) {
        $post_status = array_map(function ($el) {
            return "'" . $el . "'";
        }, $_POST['export_status']);
        $post_status = $table_prefix . "posts.post_status IN ( " . implode(",", $post_status) . ") AND ";
    }

    // POSTS STATS
    $wpdb->query('SET @@group_concat_max_len = 100000;');
    $links_post = $wpdb->get_results(
        linkate_create_statistics_query($table_prefix, false, false, $ids_query, $types, $bounds, $post_status),
        ARRAY_A
    ); //

    reset($links_post);

    // FOR POSTS LIST STATS COLUMNS, not ajax call
    if ($is_custom_column) {
        if (is_array($links_post)) {
            if (isset($links_post[0]["count_sources"])) {
                return $links_post[0]["count_sources"];
            }
        }
        return false;
    }

    $output_array = linkate_queryresult_to_array($links_post, $from_editor, 0);
    unset($links_post);

    // TERMS STATS
    if (
        !($from_editor)
        && !$admin_preview_stats
        // checking stats offset to add terms only once
        && (isset($_POST['stats_offset']) && intval($_POST['stats_offset']) === 0)
    ) {
        $links_term = $wpdb->get_results(
            linkate_create_statistics_query($table_prefix, true, false, $ids_query, $types, $bounds),
            ARRAY_A
        ); //
        reset($links_term);

        $output_array = array_merge($output_array, linkate_queryresult_to_array($links_term, $from_editor, 1));
        unset($links_term);
    }

    // OUTPUT
    if ($from_editor) {
        // classic & gutenberg
        wp_send_json($output_array);
    } else if ($admin_preview_stats) {
        // plugin options / scheme stats 
        $output_array = linkate_admin_preview_stats($output_array);
        echo json_encode($output_array);
    } else {
        // admin generate csv, download file [NOT CUSTOM COL, NOT FROM EDITOR, NOT FOR ADMIN STATISTICS]
        linkate_query_to_csv($output_array, 'cherrylink_stats_' . $_POST['stats_offset'] . '.csv');
        $response = array();
        $response['status'] = 'OK';
        $response['url'] = CHERRYLINK_DIR_URL . '/stats/cherrylink_stats_' . $_POST['stats_offset'] . '.csv';
        echo json_encode($response);
    }

    unset($output_array);
    wp_die();
}

add_action('wp_ajax_linkate_generate_csv_or_json_prettyfied_backwards', 'linkate_generate_csv_or_json_prettyfied_backwards');
function linkate_generate_csv_or_json_prettyfied_backwards()
{
    // get rows from db
    global $wpdb, $table_prefix;
    $ids_query = ""; // keep it for now, only useful for forward-way stats
    $bounds = "";
    $types = $table_prefix . "posts.post_type NOT IN ('attachment', 'nav_menu_item', 'revision', 'wp_block')";

    if (isset($_POST['stats_offset'])) {
        $bounds = " LIMIT " .  $_POST['stats_offset'] . "," . $_POST['stats_limit'];
    }

    if (isset($_POST['export_types'])) {
        $types = array_map(function ($el) {
            return "'" . $el . "'";
        }, $_POST['export_types']);
        $types = $table_prefix . "posts.post_type IN ( " . implode(",", $types) . ") ";
    }

    // POSTS 
    $wpdb->query('SET @@group_concat_max_len = 100000;');
    $links_post = $wpdb->get_results(
        linkate_create_statistics_query($table_prefix, false, true, $ids_query, $types, $bounds),
        ARRAY_A
    ); //

    reset($links_post);
    $output_array = linkate_queryresult_to_array_backwards($links_post, 0);
    unset($links_post);

    // TERMS
    if (isset($_POST['stats_offset']) && intval($_POST['stats_offset']) === 0) { // only include once
        $links_term = $wpdb->get_results(
            linkate_create_statistics_query($table_prefix, true, true, $ids_query, $types, $bounds),
            ARRAY_A
        ); //
        reset($links_term);

        $output_array = array_merge($output_array, linkate_queryresult_to_array_backwards($links_term, 1));
        unset($links_term);
    }


    linkate_query_to_csv($output_array, 'cherrylink_stats_' . $_POST['stats_offset'] . '.csv');
    $response = array();
    $response['status'] = 'OK';
    $response['url'] = CHERRYLINK_DIR_URL . '/stats/cherrylink_stats_' . $_POST['stats_offset'] . '.csv';
    echo json_encode($response);

    unset($output_array);
    wp_die();
}
function linkate_queryresult_to_array_backwards($links, $target_type)
{
    $include_types = $_POST['export_types'] ? $_POST['export_types'] : array();
    $output_array = array();
    //echo sizeof($links);
    foreach ($links as $link) {
        // get source url and target url
        $target_url = '';
        $target_categories = array();
        if ($target_type == 0) { //post
            $target_url = get_permalink((int)$link['target_id']);
            if (false === in_array($link['post_type'], $include_types) && !isset($_POST["from_editor"]))
                continue; // skip, if not in our list
            // get post's categories
            $post_categories = get_the_terms((int)$link['target_id'], 'category');
            if (!empty($post_categories) && !is_wp_error($post_categories)) {
                $target_categories = wp_list_pluck($post_categories, 'name');
            }
        } elseif ($target_type == 1) { // term
            $target_url = get_term_link((int)$link['target_id']);
            $term_obj = get_term((int)$link['target_id']);
            if ($term_obj == null || $term_obj instanceof WP_Error) {
                $term_type = 'cat/tag';
                $term_name = 'taxonomy';
            } else {
                $term_type = $term_obj->taxonomy;
                $term_name = $term_obj->name;
            }
            if (!in_array($term_type, $include_types) && $term_type != 'cat/tag')
                continue; // skip, if not in our list
        }

        $sources = explode(';', $link['sources']);
        $source_types = explode(';', $link['source_types']);
        $ext_links = explode(';', $link['ext_links']);
        $ankors = explode(';',  $link['ankors']);

        for ($i = 0; $i < sizeof($sources); $i++) {
            $source_url = '';
            if ($source_types[$i] == 0) { //post
                $source_url = get_permalink((int)$sources[$i]);
            } elseif ($source_types[$i] == 1) {
                $source_url = get_term_link((int)$sources[$i]);
            } else {
                $source_url = $ext_links[$i];
            }
            // check POST options
            $buf_array = array();
            if (isset($_POST["from_editor"]) && $_POST["from_editor"] == true) {
                if ($i > 0)
                    break;
                $buf_array[] = $link['count_targets'];
                $buf_array[] = $link['count_sources'];
            } else { //from admin panel
                if ($i == 0 || isset($_POST['duplicate_fields'])) {
                    if (isset($_POST['target_id']))     $buf_array[] = $link['target_id'];
                    if (isset($_POST['target_type']))   $buf_array[] = $target_type == 0 ? $link['post_type'] : $term_type;
                    if (isset($_POST['target_cats']))   $buf_array[] = $target_type == 0 ? implode(", ", $target_categories) : $term_name;
                    if (isset($_POST['target_url']))    $buf_array[] = $target_url;
                    if (isset($_POST['source_url']))    $buf_array[] = $source_url;
                    if (isset($_POST['ankor']))         $buf_array[] = $ankors[$i];
                    if (isset($_POST['count_out']))     $buf_array[] = $link['count_targets'];
                    if (isset($_POST['count_in']))      $buf_array[] = $link['count_sources'];
                } else { // by default, we don't repeat the same data
                    if (isset($_POST['target_id'])) $buf_array[] = '';
                    if (isset($_POST['target_type'])) $buf_array[] = '';
                    if (isset($_POST['target_cats'])) $buf_array[] = '';
                    if (isset($_POST['target_url'])) $buf_array[] = '';
                    if (isset($_POST['source_url'])) $buf_array[] = $source_url;
                    if (isset($_POST['ankor'])) $buf_array[] = $ankors[$i];
                    if (isset($_POST['count_out'])) $buf_array[] = '';
                    if (isset($_POST['count_in'])) $buf_array[] = '';
                }
            }
            if (isset($_POST["from_editor"]) && ($_POST["from_editor"] === 'true' || $_POST["from_editor"] === true)) {
                $output_array["\"id_" . $link['source_id'] . "\""] = $buf_array;
            } else {
                $output_array[] = $buf_array;
            }
        }
    }
    return $output_array;
}
function linkate_queryresult_to_array($links, $from_editor, $source_type)
{
    $error_level = error_reporting();
    error_reporting(E_ALL & ~E_DEPRECATED);
    $include_types = isset($_POST['export_types']) ? $_POST['export_types'] : array();
    $output_array = array();
    //echo sizeof($links);
    foreach ($links as $link) {
        // get source url and target url
        $source_url = '';
        $source_categories = array();
        if (intval($source_type) === 0) { //post
            $source_url = get_permalink((int)$link['source_id']);
            if (false === in_array($link['post_type'], $include_types) && !isset($from_editor))
                continue; // skip, if not in our list
            // get post's categories
            $post_categories = get_the_terms((int)$link['source_id'], 'category');
            if (!empty($post_categories) && !is_wp_error($post_categories)) {
                $source_categories = wp_list_pluck($post_categories, 'name');
            }
        } elseif (intval($source_type) === 1) { // term
            $source_url = get_term_link((int)$link['source_id']);
            // if ($source_url instanceof WP_Error) $source_url = $source_url->get_error_message();
            $term_obj = get_term((int)$link['source_id']);
            if ($term_obj == null || $term_obj instanceof WP_Error) {
                $term_type = 'cat/tag';
                $term_name = 'taxonomy';
            } else {
                $term_type = $term_obj->taxonomy;
                $term_name = $term_obj->name;
            }
            if (!in_array($term_type, $include_types) && $term_type != 'cat/tag')
                continue; // skip, if not in our list
        }

        $targets = array_key_exists('targets', $link) ? explode(';', $link['targets']) : [];
        $target_types = array_key_exists('target_types', $link) ? explode(';', $link['target_types']) : [];
        $ext_links = array_key_exists('ext_links', $link) ? explode(';', $link['ext_links']) : [];
        $ankors = array_key_exists('ankors', $link) ? explode(';',  $link['ankors']) : [];

        $buf_array = array();
        if (isset($from_editor) && $from_editor == true) {
            $buf_array[] = $link['count_targets'];
            $buf_array[] = $link['count_sources'];
            $output_array["\"id_" . $link['source_id'] . "\""] = $buf_array;
        } else {
            for ($i = 0; $i < sizeof($targets); $i++) {
                $target_url = '';
                if (intval($target_types[$i]) === 0) { //post
                    $target_url = get_permalink((int)$targets[$i]);
                } elseif (intval($target_types[$i]) === 1) {
                    $target_url = get_term_link((int)$targets[$i]);
                } else {
                    $target_url = $ext_links[$i];
                }
                // check POST options
                $buf_array = array();
                if ($i === 0 || isset($_POST['duplicate_fields'])) {
                    if (isset($_POST['source_id']))     $buf_array[] = $link['source_id'];
                    if (isset($_POST['source_type']))   $buf_array[] = $source_type == 0 ? $link['post_type'] : $term_type;
                    if (isset($_POST['source_cats']))   $buf_array[] = $source_type == 0 ? implode(", ", $source_categories) : $term_name;
                    if (isset($_POST['source_url']))    $buf_array[] = $source_url;
                    if (isset($_POST['target_url']))    $buf_array[] = $target_url;
                    if (isset($_POST['ankor']))         $buf_array[] = $ankors[$i];
                    if (isset($_POST['count_out']))     $buf_array[] = $link['count_targets'];
                    if (isset($_POST['count_in']))      $buf_array[] = $link['count_sources'];
                    $buf_array[] = intval($target_types[$i]) === 255 ? 1 : 0;
                } else { // by default, we don't repeat the same data
                    if (isset($_POST['source_id'])) $buf_array[] = '';
                    if (isset($_POST['source_type'])) $buf_array[] = '';
                    if (isset($_POST['source_cats'])) $buf_array[] = '';
                    if (isset($_POST['source_url'])) $buf_array[] = '';
                    if (isset($_POST['target_url'])) $buf_array[] = $target_url;
                    if (isset($_POST['ankor'])) $buf_array[] = $ankors[$i];
                    if (isset($_POST['count_out'])) $buf_array[] = '';
                    if (isset($_POST['count_in'])) $buf_array[] = '';
                    $buf_array[] = intval($target_types[$i]) === 255 ? 1 : 0;
                }

                $output_array[] = $buf_array;
            }
        }
    }
    
    // revert it back
    error_reporting($error_level);
    return $output_array;
}

// Organize data for preview
function linkate_admin_preview_stats($stats_array)
{
    // indices
    $source_id = 0;
    $source_url = 3;
    $target_url = 4;
    $ankor = 5;
    $count_out = 6;
    $count_in = 7;
    $is_404 = 8;
    $new_array = array();

    $prev_id = '';
    foreach ($stats_array as $i => $row) {
        // keep only links of targets which have repeats (i.e. problematics)
        if (!empty($prev_id) && $prev_id !== $row[$source_id]) {
            $new_array[$prev_id]['targets'] = array_filter($new_array[$prev_id]['targets'], function ($element) {
                return $element > 1;
            });
        }
        // arrange data, count repeats
        if (!isset($new_array[$row[$source_id]])) {
            $new_array[$row[$source_id]] = array(
                'url' => $row[$source_url],
                'targets' => array(
                    $row[$target_url] => 1
                ),
                'recursion' => array(),
                'err_404' => array(),
                'has_outgoing' => intval($row[$count_out]) > 0,
                'has_incoming' => intval($row[$count_in]) > 0,
                'has_repeats' => false
            );
        } else {
            if (isset($new_array[$row[$source_id]]['targets'][$row[$target_url]])) {
                $new_array[$row[$source_id]]['targets'][$row[$target_url]]++;
                $new_array[$row[$source_id]]['has_repeats'] = true;
            } else {
                $new_array[$row[$source_id]]['targets'][$row[$target_url]] = 1;
            }
        }

        // recursion
        if ($row[$source_url] === $row[$target_url]) {
            $new_array[$row[$source_id]]['recursion'][] = $row[$ankor];
        }

        // 404
        if ($row[$source_url] !== $row[$target_url] && $row[$is_404]) {
            $new_array[$row[$source_id]]['err_404'][$row[$target_url]] = $row[$ankor];
        }


        $prev_id = $row[$source_id];
    }
    $new_array = array_filter($new_array, function ($element) {
        return $element['has_repeats'] || !$element['has_outgoing'] || !$element['has_incoming'] || count($element['err_404']) > 0 || count($element['recursion']) > 0;
    });
    return $new_array;
}

function linkate_is_target_404($url)
{
    // $opts['http']['timeout'] = 2;

    // $headers = null;
    // if (version_compare(PHP_VERSION, '7.1.0', '>=')) {
    //     $context = stream_context_create($opts);
    //     $headers =  get_headers($url, 0, $context);
    // } else {
    //     $defaultOptions = stream_context_get_options(stream_context_get_default());
    //     stream_context_set_default($opts);
    //     $headers = get_headers($url);
    //     stream_context_set_default($defaultOptions);
    // }

    $headers = get_headers($url);
    $code = substr($headers[0], 9, 3);
    return $code === 404 || $code === "404";
}

// ========================================================================================= //
// ============================== CherryLink Generate CSV File  ============================== //
// ========================================================================================= //

// Change encoding if possible
function linkate_encode_csv(&$value, $key)
{
    if ($value instanceof WP_Error)
        $value = 'NULL_ERROR';
    else
        $value = iconv('UTF-8', 'Windows-1251', $value);
}
// Custom fputcsv, punctuation and EOL for windows
function linkate_fputcsv_eol($handle, $array, $delimiter = ',', $enclosure = '"', $eol = "\n")
{
    $return = fputcsv($handle, $array, $delimiter, $enclosure);
    if ($return !== FALSE && "\n" != $eol && 0 === fseek($handle, -1, SEEK_CUR)) {
        fwrite($handle, $eol);
    }
    return $return;
}
// Write csv file
function linkate_query_to_csv($array, $filename)
{
    $arr_post_id =             'ID';
    $arr_post_type =         'Тип';
    $arr_post_cats =         $_POST["links_direction"] == "outgoing" ? 'Рубрики_источника' : 'Рубрики_цели';
    $arr_source_url =         $_POST["links_direction"] == "outgoing" ? 'URL_источника' : 'URL_цели';
    $arr_target =             $_POST["links_direction"] == "outgoing" ? 'URL_цели' : 'URL_источника';
    $arr_ankor =             'Анкор';
    $arr_targets_count =     'Исходящих_ссылок';
    $arr_sources_count =     'Входящих_ссылок';
    $headers = array($arr_post_id, $arr_post_type, $arr_post_cats, $arr_source_url, $arr_target, $arr_ankor, $arr_targets_count, $arr_sources_count);

    // create dir
    if (!file_exists(CHERRYLINK_DIR . '/stats')) {
        mkdir(CHERRYLINK_DIR . '/stats', 0755, true);
    }
    //////////////////
    $fp = fopen(CHERRYLINK_DIR . '/stats/' . $filename, 'w');

    // output header row (if at least one row exists)
    array_walk($headers, 'linkate_encode_csv');
    linkate_fputcsv_eol($fp, $headers, ',', '"', "\r\n");

    foreach ($array as $row) {
        array_walk($row, 'linkate_encode_csv');
        linkate_fputcsv_eol($fp, $row, ',', '"', "\r\n");
    }

    fclose($fp);
}

add_action('wp_ajax_linkate_merge_csv_files', 'linkate_merge_csv_files');
function linkate_merge_csv_files()
{
    $directory = CHERRYLINK_DIR . '/stats/*'; // CSV Files Directory Path

    // Open and Write Master CSV File
    $masterCSVFile = fopen(CHERRYLINK_DIR . '/stats/cherrylink_stats.csv', "w+");
    $first_file = true;
    // Process each CSV file inside root directory
    foreach (glob($directory) as $file) {
        $data = []; // Empty Data

        // Allow only CSV files
        if (strpos($file, 'cherrylink_stats_') !== false) {

            // Open and Read individual CSV file
            if (($handle = fopen($file, 'r')) !== false) {
                // Collect CSV each row records
                while (($dataValue = fgetcsv($handle, 1000)) !== false) {
                    $data[] = $dataValue;
                }
            }

            fclose($handle); // Close individual CSV file 

            if (!$first_file)
                unset($data[0]); // Remove first row of CSV, commonly tends to CSV header

            // Check whether record present or not
            if (count($data) > 0) {

                foreach ($data as $value) {
                    try {
                        // Insert record into master CSV file
                        linkate_fputcsv_eol($masterCSVFile, $value, ',', '"', "\r\n");
                    } catch (Exception $e) {
                        echo $e->getMessage();
                    }
                }
            }
            $first_file = false;
        }
    }

    // Close master CSV file 
    fclose($masterCSVFile);
    linkate_stats_remove_old(true);
    $response = array();
    $response['status'] = 'OK';
    $response['url'] = CHERRYLINK_DIR_URL . '/stats/cherrylink_stats.csv';
    echo json_encode($response);
    wp_die();
}

function linkate_stats_remove_old($onlytemp_files = false)
{
    if (!file_exists(CHERRYLINK_DIR . '/stats')) {
        return;
    }

    $files = glob(CHERRYLINK_DIR . '/stats/*'); // get all file names
    foreach ($files as $file) { // iterate files
        if (is_file($file)) {
            if ($onlytemp_files && strpos($file, 'cherrylink_stats_') !== false) {
                unlink($file); // delete file
            }
            if (!$onlytemp_files)
                unlink($file); // delete file
        }
    }
}
