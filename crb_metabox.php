<?php
/*
 * CherryLink Plugin
 */

// Disable direct access
defined( 'ABSPATH' ) || exit;

class CL_RB_Metabox {
    /**
     * Adds a meta box to the post editing screen
     */
    static function crb_meta_box() {
        add_meta_box( 'crb_meta', __( 'CherryLink Блок ссылок', CHERRYLINK_TEXT_DOMAIN ), array('CL_RB_Metabox','crb_meta_callback'), null, 'normal', 'high', array(
            '__back_compat_meta_box' => true,
        ) );
    }


    /**
     * Outputs the content of the meta box
     */
    static function crb_meta_callback( $post ) {
        wp_nonce_field( basename( __FILE__ ), 'crb_nonce' );
        $crb_stored_meta = get_post_meta( $post->ID );
        ?>

        <p>
            <label for="crb-meta-show" class="row-title"><?php _e( 'Показывать блок ссылок для этой статьи?', CHERRYLINK_TEXT_DOMAIN )?></label>
            <input type="checkbox" name="crb-meta-show" id="crb-meta-show" value="crb-meta-show" <?php if ( isset ( $crb_stored_meta['crb-meta-show'] ) ) echo $crb_stored_meta[ 'crb-meta-show' ][0]; else echo 'checked' ?>>
        </p>
        <p style="display: none;">
            <label for="crb-meta-show-edited" class="row-title"><?php _e( 'Проверочный чекбокс для сохранения индивидуальных опций вывода', CHERRYLINK_TEXT_DOMAIN )?></label>
            <input type="checkbox" name="crb-meta-show-edited" id="crb-meta-show-edited" value="crb-meta-show-edited" <?php if ( isset ( $crb_stored_meta['crb-meta-show-edited'] ) ) echo $crb_stored_meta[ 'crb-meta-show-edited' ][0]; ?>>
        </p>

        <p>
            <label for="crb-meta-use-manual" class="row-title"><?php _e( 'Редактировать анкоры ссылок', CHERRYLINK_TEXT_DOMAIN )?></label>
            <input type="checkbox" name="crb-meta-use-manual" id="crb-meta-use-manual" value="crb-meta-use-manual" <?php if ( isset ( $crb_stored_meta['crb-meta-use-manual'] ) ) echo $crb_stored_meta[ 'crb-meta-use-manual' ][0]; ?>>
        </p>

        <p>
            Данный блок также можно вывести с помощью шорткода:
            <code>[crb_show_block]</code>
        </p>
        <p style="display: none;">
            <label for="crb-meta-links" class="row-title"><?php _e( 'Это надо скрыть', CHERRYLINK_TEXT_DOMAIN )?></label>
            <textarea readonly name="crb-meta-links" id="crb-meta-links" cols="60" rows="8"><?php if ( isset ( $crb_stored_meta['crb-meta-links'] ) ) echo $crb_stored_meta['crb-meta-links'][0]; ?></textarea>
        </p>
        <div class="crb-meta-visual"><p>Ссылки не выбраны.</p></div>

        <?php
    }

    /**
     * Saves the custom meta input
     */
    static function crb_meta_save( $post_id ) {

        // Checks save status
        $is_autosave = wp_is_post_autosave( $post_id );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = ( isset( $_POST[ 'crb_nonce' ] ) && wp_verify_nonce( $_POST[ 'crb_nonce' ], basename( __FILE__ ) ) ) ? true : false;

        // Exits script depending on save status
        if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
            return;
        }

        // Checks for input and sanitizes/saves if needed
        if( isset( $_POST[ 'crb-meta-links' ] ) ) {
            update_post_meta( $post_id, 'crb-meta-links',  $_POST[ 'crb-meta-links' ]  );
        }
        
        if( isset( $_POST[ 'crb-meta-use-manual' ] )) {
            update_post_meta( $post_id, 'crb-meta-use-manual',  'checked'  );
        } else {
            update_post_meta( $post_id, 'crb-meta-use-manual',  'off'  );
        }

        if( isset( $_POST[ 'crb-meta-show' ] ) && isset( $_POST[ 'crb-meta-show-edited' ] )) {
            update_post_meta( $post_id, 'crb-meta-show',  'checked'  );
            update_post_meta( $post_id, 'crb-meta-show-edited',  'checked'  );
        } else if (!isset( $_POST[ 'crb-meta-show' ] ) && isset( $_POST[ 'crb-meta-show-edited' ] )) {
            update_post_meta( $post_id, 'crb-meta-show',  'off'  );
            update_post_meta( $post_id, 'crb-meta-show-edited',  'checked'  );
        }

    }

    static function get_custom_posts($post_id) {
        // Retrieves the stored value from the database
        $meta_value = get_post_meta( $post_id, 'crb-meta-links', true );

        // Checks and displays the retrieved value
        if( !empty( $meta_value ) ) {
            $meta_value = explode("\n", $meta_value);
            $ids = array();
            foreach ($meta_value as $row) {
                $ids[] = explode("[|]", $row)[0];
            }
            return implode(",", $ids);
        }
        return false;
    }

    static function get_custom_show($post_id) {
        // Retrieves the stored value from the database
        $meta_value = get_post_meta( $post_id, 'crb-meta-show', true );
        _cherry_debug(__FUNCTION__, $meta_value, 'post meta [crb-meta-show] для ID: ' . $post_id);
        // Checks and displays the retrieved value
        if( $meta_value === "checked" || empty($meta_value)) {
            return true;
        }
        return false;
    }

    static function meta_assets($hook_suffix) {
        // Taxonomy editor [custom plugins, TinyMCE]

        // Post editor
        if ('post.php' === $hook_suffix || 'post-new.php' === $hook_suffix) {
            wp_register_style( 'crb-admin', plugins_url( '/css/crb-admin.css', __FILE__ ), '', CL_Related_Block::get_version() );
            wp_enqueue_style ('crb-admin');
        }
        return;
    }

}

function _crb_metabox_pages() {
    $options = get_option('linkate-posts');
    $screens = array();

    // if (isset($options['crb_show_for_pages']) && $options['crb_show_for_pages'] === 'true')
    // Выведем для страниц в любом случае (будет работать при ручном и авто-показе блока ссылок)
    $screens[] = 'page';

    // Проверим какие есть произвольные типы в основных настроках фильтрации
    if (isset($options['show_customs']))
        $screens = array_merge($screens, explode(",",$options['show_customs']));

    if (sizeof($screens) > 0) {
        foreach ($screens as $type) {
            add_action( 'add_meta_boxes_'.$type, array('CL_RB_Metabox','crb_meta_box'));
        }
    }
    // always add for single post type
    add_action( 'add_meta_boxes_post', array('CL_RB_Metabox','crb_meta_box'));
}

function _crb_metabox_init() {
    _crb_metabox_pages();
    add_action( 'save_post', array('CL_RB_Metabox', 'crb_meta_save' ));
    add_action( 'admin_enqueue_scripts', array('CL_RB_Metabox', 'meta_assets' ), 20);
}