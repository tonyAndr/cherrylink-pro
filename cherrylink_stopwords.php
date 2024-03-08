<?php 
/*
 * CherryLink Plugin
 */

// Disable direct access
defined( 'ABSPATH' ) || exit;
// Define lib name
define('LINKATE_STOPWORDS_LIBRARY', true);

// ========================================================================================= //
    // ============================== CherryLink StopWords ============================== //
// ========================================================================================= //

add_action("wp_ajax_linkate_get_stopwords", "linkate_get_stopwords");
function linkate_get_stopwords() {
    global $wpdb;
    $table_name = $wpdb->prefix . "linkate_stopwords";
    $rows = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    wp_send_json($rows);
}

add_action("wp_ajax_linkate_get_whitelist", "linkate_get_whitelist");
function linkate_get_whitelist() {

	if ( false === ( $output = get_transient( "cherry_stop_whitelist" ) ) ) {
            // It wasn't there, so regenerate the data and save the transient
		global $wpdb;
		$table_name = $wpdb->prefix . "linkate_stopwords";
		$rows = $wpdb->get_col("SELECT word FROM $table_name WHERE is_white = 1");
		$output = json_encode($rows);
        set_transient( "cherry_stop_whitelist", $output, 1440 * MINUTE_IN_SECONDS * 7 );
	}
	
	echo $output;
	wp_die();
}
add_action("wp_ajax_linkate_get_blacklist", "linkate_get_blacklist");
function linkate_get_blacklist($from_reindex = true) {
	
	if (wp_doing_ajax() && !$from_reindex) {

		if ( false === ( $output = get_transient( "cherry_stop_blacklist" ) ) ) {
				// It wasn't there, so regenerate the data and save the transient
			global $wpdb;
			$table_name = $wpdb->prefix . "linkate_stopwords";
			$rows = $wpdb->get_col("SELECT word FROM $table_name WHERE is_white = 0");
			$output = json_encode($rows);
			set_transient( "cherry_stop_blacklist", $output, 1440 * MINUTE_IN_SECONDS * 7 );
		}
		
		echo $output;
		wp_die();

	} else {
		global $wpdb;
		$table_name = $wpdb->prefix . "linkate_stopwords";
		$rows = $wpdb->get_col("SELECT word FROM $table_name WHERE is_white = 0");
	    return $rows;
    }
}

function linkate_stoplist_clear_cache() {
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->options WHERE `option_name` LIKE ('%cherry\_stop\_%')");
}

add_action("wp_ajax_linkate_add_stopwords", "linkate_add_stopwords");
function linkate_add_stopwords() {
    global $wpdb;
	$table_name = $wpdb->prefix . "linkate_stopwords";

	$is_stemm = isset($_POST['is_stemm']) && intval($_POST['is_stemm']) === 1; // if we quick-add from stopword suggestions

	if (isset($_POST['words']) && !empty($_POST['words']) ) {
		$words = $_POST['words'];
    } else {
	    return;
    }

	$is_white = isset($_POST['is_white']) ? intval($_POST['is_white']) : 0;

	$stemmer = new Stem\LinguaStemRu();

	$query = "INSERT INTO $table_name (stemm, word, is_white, is_custom) VALUES ";
	foreach ($words as $word) {
		$values = $wpdb->prepare("(%s,%s,%d,1)", $is_stemm ? $word : $stemmer->stem_word($word), mb_strtolower(trim($word)), $is_white);
		if ($values) {
			$wpdb->query($query . $values);
		}
	}
	linkate_stoplist_clear_cache();
}

add_action("wp_ajax_linkate_delete_stopword", "linkate_delete_stopword");
function linkate_delete_stopword() {
	global $wpdb;
	$table_name = $wpdb->prefix . "linkate_stopwords";
	$id = isset($_POST["id"]) ? intval($_POST["id"]) : false;
	$all = isset($_POST["all"]) ? intval($_POST["all"]) : false;

	if ($all) {
	    $wpdb->query("TRUNCATE TABLE $table_name");
    } else if ($id >= 0) {
	    $wpdb->delete($table_name, array('ID' => $id));
	}
	linkate_stoplist_clear_cache();
}
add_action("wp_ajax_linkate_update_stopword", "linkate_update_stopword");
function linkate_update_stopword() {
	global $wpdb;
	$table_name = $wpdb->prefix . "linkate_stopwords";

	if (isset($_POST["id"])) {
		$id = intval($_POST["id"]);
        $is_white = intval($_POST["is_white"]);

        $wpdb->update($table_name,array('is_white' => $is_white), array("ID" => $id));
	}
	linkate_stoplist_clear_cache();
}