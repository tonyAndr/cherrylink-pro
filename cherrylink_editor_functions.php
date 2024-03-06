<?php 
/*
 * CherryLink Plugin
 */

// Disable direct access
defined( 'ABSPATH' ) || exit;
// Define lib name
define('LINKATE_EF_LIBRARY', true);
require_once (WP_PLUGIN_DIR . "/cherrylink/cherrylink_stemmer_ru.php");

function linkate_sp_terms_by_freq($ID, $num_terms = 50, $is_term = 0) {
	if (!$ID) return array('', '', '', '');
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';
	$terms = '';
	$results = $wpdb->get_results("SELECT title, content, tags, suggestions FROM $table_name WHERE pID=$ID AND is_term=$is_term LIMIT 1", ARRAY_A);
	if ($results) {
		$word = strtok($results[0]['content'], ' ');
		$n = 0;
		$wordtable = array();
		while ($word !== false) {
			if(!array_key_exists($word,$wordtable)){
				$wordtable[$word]=0;
			}
			$wordtable[$word] += 1;
			$word = strtok(' ');
		}
		arsort($wordtable);
		if ($num_terms < 1) $num_terms = 1;
		$wordtable = array_slice($wordtable, 0, $num_terms);

		foreach ($wordtable as $word => $count) {
			$terms .= ' ' . $word;
		}

		$res[] = $terms;
		$res[] = $results[0]['title'];
		$res[] = $results[0]['tags'];
		$res[] = $results[0]['suggestions'];
 	}
	return $res;
}

// Extract the most popular words to make ankor suggestions 
function linkate_sp_terms_by_freq_ankor($content) {
	if (empty($content))
		return "";
	$terms = "";
	$num_terms = 3; // max words num
	$word = strtok($content, ' ');
	$n = 0;
	$wordtable = array();
	while ($word !== false) {
		if(!array_key_exists($word,$wordtable)){
			$wordtable[$word]=0;
		}
		$wordtable[$word] += 1;
		$word = strtok(' ');
	}
	arsort($wordtable);
	if ($num_terms < 1) $num_terms = 1;
	$wordtable = array_slice($wordtable, 0, $num_terms);

	foreach ($wordtable as $word => $count) {
		$terms .= ' ' . $word;
	}
	return $terms;
}

// Update post index

function linkate_sp_save_index_entry($postID, $postObj, $updated) {
    if ($postObj->post_type === 'revision') return $postID;
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';
    
	$options = get_option('linkate-posts');

    $use_stemming = $options['use_stemming'] === "true";
    $stemmer = new Stem\LinguaStemRu();
    $stemmer->enable_stemmer($use_stemming);

	// wp_linkate_scheme, create new scheme for this post
	if ($options['linkate_scheme_exists']) {
		linkate_scheme_delete_record($postID, 0);
		linkate_scheme_add_row($postObj->post_content, $postID, 0); 
		$options['linkate_scheme_time'] = time();
		update_option('linkate-posts', $options);
	}

    $seo_meta_source = $options['seo_meta_source'];

	$suggestions_donors_src = $options['suggestions_donors_src'];
    $suggestions_donors_join = $options['suggestions_donors_join'];
	$clean_suggestions_stoplist = $options['clean_suggestions_stoplist'];
	$min_len = intval($options['term_length_limit']);

    $words_table = $table_prefix."linkate_stopwords";
    $black_words = $wpdb->get_col("SELECT stemm FROM $words_table WHERE is_white = 0 GROUP BY stemm");
    $white_words = $wpdb->get_col("SELECT word FROM $words_table WHERE is_white = 1");
    $linkate_overusedwords["black"] = array_flip(array_filter($black_words));
    $linkate_overusedwords["white"] = array_flip(array_filter($white_words));

    $content_words_list = mb_split("\W+", linkate_sp_mb_clean_words($postObj->post_content));
	list($content, $content_sugg) = linkate_sp_get_post_terms($content_words_list, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist);
    $content = iconv("UTF-8", "UTF-8//IGNORE", $content); // convert broken symbols
    if (!$content)
        $content = '';
	// Seo title is more relevant, usually
	// Extracting terms from the custom titles, if present
	// Check SEO Fields
    $seotitle = linkate_get_post_seo_title($postObj, $seo_meta_source);

    // anti-memory leak
    wp_cache_delete( $postID, 'post_meta' );

    if (!empty($seotitle) && $seotitle !== $postObj->post_title) {
        $title = $postObj->post_title . " " . $seotitle;
    } else {
        $title = $postObj->post_title;
    }
    list($title, $title_sugg) = linkate_sp_get_title_terms( $title, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist );

    // Extract ancor terms
	$suggestions = linkate_sp_prepare_suggestions($title_sugg, $content_sugg, $suggestions_donors_src, $suggestions_donors_join);

	$tags = linkate_sp_get_tag_terms($postID);
	//check to see if the field is set
	$pid = $wpdb->get_var("SELECT pID FROM $table_name WHERE pID=$postID limit 1");
	//then insert if empty
	if (is_null($pid)) {
		$wpdb->query("INSERT INTO $table_name (pID, content, title, tags, suggestions) VALUES ($postID, \"$content\", \"$title\", \"$tags\", \"$suggestions\")");
	} else {
		$wpdb->query("UPDATE $table_name SET content=\"$content\", title=\"$title\", tags=\"$tags\", suggestions=\"$suggestions\" WHERE pID=$postID" );
	}
	return $postID;
}

function linkate_sp_prepare_suggestions($title, $content, $suggestions_donors_src, $suggestions_donors_join) {
	if (empty($suggestions_donors_src))
	    return '';

	$suggestions_donors_src = explode(',', $suggestions_donors_src);

	// change old settings
	if (!in_array('title', $suggestions_donors_src) && !in_array('content', $suggestions_donors_src)) {
        $suggestions_donors_src = array('title');
    }

    $array = array();
    if (in_array('title',$suggestions_donors_src))
	    $array[] = array_filter($title);
	if (in_array('content', $suggestions_donors_src)) {
	    // get most used words from content
        $wordlist = array_count_values($content);
        arsort($wordlist);
        $wordlist = array_slice($wordlist, 0, 20);
        $wordlist = array_keys($wordlist);
        $array[] = array_filter($wordlist);
	}
    $array = array_filter($array);
    if (empty($array))
        return '';

    $array = array_values($array);
    if (sizeof($array) === 1) {
        return implode(' ', array_unique($array[0]));
    }

    if ($suggestions_donors_join == 'intersection') {
        $result = array_unique(array_intersect(...$array));
        return  implode(' ', $result);
    } else { //join
        $result = array_unique(array_merge(...$array));
        return  implode(' ', $result);
    }
}

function linkate_sp_delete_index_entry($postID) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';
	$wpdb->query("DELETE FROM $table_name WHERE pID = $postID ");
	return $postID;
}

// Update term index

function linkate_sp_save_index_entry_term($term_id, $tt_id, $taxonomy) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';
	$options = get_option('linkate-posts');
    
    $use_stemming = $options['use_stemming'] === "true";
    $stemmer = new Stem\LinguaStemRu();
    $stemmer->enable_stemmer($use_stemming);

	$term = $wpdb->get_row("SELECT `term_id`, `name` FROM $wpdb->terms WHERE term_id = $term_id", ARRAY_A);


	$suggestions_donors_src = $options['suggestions_donors_src'];
    $suggestions_donors_join = $options['suggestions_donors_join'];
	$clean_suggestions_stoplist = $options['clean_suggestions_stoplist'];
	$min_len = $options['term_length_limit'];

    $words_table = $table_prefix."linkate_stopwords";
    $black_words = $wpdb->get_col("SELECT stemm FROM $words_table WHERE is_white = 0 GROUP BY stemm");
    $white_words = $wpdb->get_col("SELECT word FROM $words_table WHERE is_white = 1");
    $black_words = array_filter($black_words);
    $white_words = array_filter($white_words);
    $linkate_overusedwords["black"] = array_flip($black_words);
    $linkate_overusedwords["white"] = array_flip($white_words);

	$descr = '';
	$descr .= term_description($term_id); // standart 
	// custom plugins sp-category && f-cattxt
	$opt = get_option('category_'.$term_id);
	if ($opt && (function_exists('contents_sp_category') || function_exists('show_descr_top'))) {
		$descr .= $opt['descrtop'] ? ' '.$opt['descrtop'] : '';  
		$descr .= $opt['descrbottom'] ? ' '.$opt['descrbottom'] : '';  
		$aio_title = $opt['title'];
	}

	// wp_linkate_scheme, create new scheme for this term
	if ($options['linkate_scheme_exists']) {
		linkate_scheme_delete_record($term_id, 1);
		linkate_scheme_add_row($descr, $term_id, 1); 
		$options['linkate_scheme_time'] = time();
		update_option('linkate-posts', $options);
	}
    $descr_words_list = mb_split("\W+", linkate_sp_mb_clean_words($descr));
    list($content, $content_sugg) = linkate_sp_get_post_terms($descr_words_list, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist);
	//Seo title is more relevant, usually
	//Extracting terms from the custom titles, if present
	$seotitle = '';

    $yoast_opt = get_option('wpseo_taxonomy_meta');
    if ($yoast_opt && $yoast_opt['category'] && function_exists('wpseo_init')) {
        $seotitle = $yoast_opt['category'][$term_id]['wpseo_title'];
    }
    if (!$seotitle && $aio_title && function_exists('show_descr_top'))
        $seotitle = $aio_title;

    if (!empty($seotitle) && $seotitle !== $term['name']) {
        $title = $term['name'] . " " . $seotitle;
    } else {
        $title = $term['name'];
    }

    list($title, $title_sugg) = linkate_sp_get_title_terms( $title, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist );

	// Extract ancor terms
	$suggestions = linkate_sp_prepare_suggestions($title_sugg, $content_sugg, $suggestions_donors_src, $suggestions_donors_join);
	$tags = "";
	//check to see if the field is set
	$pid = $wpdb->get_var("SELECT pID FROM $table_name WHERE pID=$term_id AND is_term=1 limit 1");
	//then insert if empty
	if (is_null($pid)) {
		$wpdb->query("INSERT INTO $table_name (pID, content, title, tags, is_term, suggestions) VALUES ($term_id, \"$content\", \"$title\", \"$tags\", 1, \"$suggestions\")");
	} else {
		$wpdb->query("UPDATE $table_name SET content=\"$content\", title=\"$title\", tags=\"$tags\", suggestions=\"$suggestions\" WHERE pID=$term_id AND is_term=1" );
	}
	//return $postID;
}

function linkate_sp_delete_index_entry_term($term_id, $term_taxonomy_ID, $taxonomy_slug, $already_deleted_term) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';
	$wpdb->query("DELETE FROM $table_name WHERE pID = $term_id AND is_term = 1");
	//return $term_id;
}


function linkate_decode_yoast_variables($post_id, $is_term = false) {
    $string =  WPSEO_Meta::get_value( 'title', $post_id );
    if ($string !== '') {
        $replacer = new WPSEO_Replace_Vars();

        return $replacer->replace( $string, get_post($post_id) );
    } else {
        return '';
    }
}

function linkate_sp_mb_clean_words($text) {
	mb_regex_encoding('UTF-8');
	mb_internal_encoding('UTF-8');
	$text = strip_tags($text);
	$text = mb_strtolower($text);
	$text = str_replace("’", "'", $text); // convert MSWord apostrophe
	$text = preg_replace(array('/\[(.*?)\]/u', '/&[^\s;]+;/u', '/‘|’|—|“|”|–|…/u', "/'\W/u"), ' ', $text); //anything in [..] or any entities
	return 	$text;
}

function linkate_sp_get_post_terms($wordlist, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist) {
    mb_regex_encoding('UTF-8');
	mb_internal_encoding('UTF-8');
    $stemms = '';
    $words = array();

	reset($wordlist);

    if ($stemmer->Stem_Enabled) {
        foreach ($wordlist as $word) {
            if ( mb_strlen($word) > $min_len || array_key_exists($word ,$linkate_overusedwords["white"])) {
                $stemm = $stemmer->stem_word($word);
                if (mb_strlen($stemm) <= 1) continue;
                if (!array_key_exists($stemm, $linkate_overusedwords["black"]))
                    $stemms .= $stemm . ' ';
                if ($clean_suggestions_stoplist == 'false' || ($clean_suggestions_stoplist == 'true' && !array_key_exists($stemm, $linkate_overusedwords["black"])))
                    $words[] = $word;
            }
        }
    } else {
        foreach ($wordlist as $word) {
            if ( mb_strlen($word) > $min_len) {
                $words[] = $word;
            }
        }
    }

    unset($wordlist);
    if (empty($stemms) && !$stemmer->Stem_Enabled)
        $stemms = implode(' ', $words);
	return array($stemms, $words);
}

function linkate_sp_get_title_terms( $text, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist ) {
	mb_regex_encoding('UTF-8');
	mb_internal_encoding('UTF-8');
	$wordlist = mb_split("\W+", linkate_sp_mb_clean_words($text));
	$stemms = '';
    $words = array();
    if ($stemmer->Stem_Enabled) {
        foreach ($wordlist as $word) {
            if ( mb_strlen($word) > $min_len || array_key_exists($word ,$linkate_overusedwords["white"])) {
                $stemm = $stemmer->stem_word($word);
                if (mb_strlen($stemm) <= 1) continue;
                if (!array_key_exists($stemm, $linkate_overusedwords["black"]))
                    $stemms .= $stemm . ' ';
                if ($clean_suggestions_stoplist == 'false' || ($clean_suggestions_stoplist == 'true' && !array_key_exists($stemm, $linkate_overusedwords["black"])))
                    $words[] = $word;		
            }
        }
    } else {
        foreach ($wordlist as $word) {
            if ( mb_strlen($word) > $min_len) 
                $words[] = $word;		
        }
    }

    unset($wordlist);
    if (empty($stemms) && !$stemmer->Stem_Enabled)
        $stemms = implode(' ', $words);
	return array($stemms, $words);
}

function linkate_sp_get_tag_terms($ID) {
	global $wpdb;
	if (!function_exists('get_object_term_cache')) return '';
	$tags = array();
	$query = "SELECT t.name FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = t.term_id INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy = 'post_tag' AND tr.object_id = '$ID'";
	$tags = $wpdb->get_col($query);
	if (!empty ($tags)) {
        mb_internal_encoding('UTF-8');
        foreach ($tags as $tag) {
            $newtags[] = mb_strtolower(str_replace('"', "'", $tag));
        }
		$newtags = str_replace(' ', '_', $newtags);
		$tags = implode (' ', $newtags);
	} else {
		$tags = '';
	}
	return $tags;
}

// Manipulate DB
function linkate_scheme_delete_record($id, $type) {
	// delete record by post ID or term ID
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_scheme';
	$wpdb->query("DELETE FROM $table_name WHERE source_id = $id AND source_type = $type");
	return $id;
}

function linkate_scheme_add_row($str, $post_id, $is_term) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_scheme';


    $values_string = linkate_scheme_get_add_row_query($str, $post_id, $is_term);

	if (!empty($values_string))
		$wpdb->query("INSERT INTO `$table_name` (source_id, source_type, target_id, target_type, ankor_text, external_url) VALUES $values_string");
}

function linkate_scheme_get_add_row_query($str, $post_id, $is_term) {
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
	$prohibited = array('.jpg','.jpeg','.tiff','.bmp','.psd', '.png', '.gif','.webp', '.doc', '.docx', '.xlsx', '.xls', '.odt', '.pdf', '.ods','.odf', '.ppt', '.pptx', '.txt', '.rtf', '.mp3', '.mp4', '.wav', '.avi', '.ogg', '.zip', '.7z', '.tar', '.gz', '.rar', 'attachment');

    $outgoing_count = 0;
	// loop through all found items
	foreach($result as $node) {
        $href = $node->getAttribute('href');
        if (empty($href)) continue; // no href - no need

		// if its doc,file or img - skip
		$is_doc = false;
		foreach ($prohibited as $v) {
			if (strpos($href, $v) !== false){
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
        if (strpos($href, $_SERVER['HTTP_HOST']) !== false ) {
            $target_id = url_to_postid($href); //target_post_id
            if ((strpos($href, '#') !== false) && ((int)$target_id === (int) $post_id)) { 
                // target same as post, internal navigation, omit
                continue;
            }
            $target_type = 0;
            if ($target_id === 0) { // term_id
                $target_id = linkate_get_term_id_from_slug($href);
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
    update_post_meta( (int) $post_id, "cherry_outgoing", $outgoing_count );
    //wp_cache_delete( (int) $post_id, 'post_meta' );

    unset($internalErrors);
    libxml_clear_errors();
    unset($doc);
    unset($selector);
    unset($result);
    unset($prohibited);
    
    return $values_string;
}

function linkate_get_term_id_from_slug($url) {
    if (!isset($url)) {
        $url = ''; 
    }  
	$current_url = rtrim($url, "/");
	$arr_current_url = explode("/", $current_url);
	$thecategory = get_category_by_slug( end($arr_current_url) );
	if (!$thecategory) {
        unset($thecategory);
		return 0;
    } 
    
    $catid = $thecategory->term_id;
	return $catid;
}