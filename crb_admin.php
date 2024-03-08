<?php
/*
 * CherryLink Plugin
 */

// Disable direct access
defined('ABSPATH') || exit;

class CL_RB_Admin_Area
{
    # Admin Page Options
    static function output_admin_options()
    {
        $options = get_option('linkate-posts');
        if (isset($_POST['update_options'])) {
            check_admin_referer('linkate-posts-update-options');

            $options = link_cf_options_from_post($options, array(
                'crb_show_after_content',
                'crb_show_for_pages',
                'crb_hide_existing_links',
                'crb_num_of_links_to_show',
                'crb_default_offset',
                'crb_temp_before',
                'crb_temp_link',
                'crb_temp_after',
                'crb_show_latest',
                'crb_cache_minutes',
                'crb_css_tuning',
                'crb_image_size',
                'crb_choose_template',
                'crb_css_override',
                'crb_placeholder_path',
                'crb_content_filter',
                'debug_enabled'
            ));
            update_option('linkate-posts', $options);
            // Show a message to say we've done something
            echo '<div class="updated settings-error notice crb-update"><p>' . __('<b>Настройки обновлены.</b>', CHERRYLINK_TEXT_DOMAIN) . '</p></div>';

            CL_Related_Block::clear_cache();
            // Show a message to say we've done something
            echo '<div class="updated settings-error notice"><p>' . __('<b>Кэш очищен.</b>', CHERRYLINK_TEXT_DOMAIN) . '</p></div>';
        }

        if (isset($_POST['crb_defaults'])) {
            check_admin_referer('linkate-posts-update-options');

            CL_Related_Block::fill_options();
            // Show a message to say we've done something
            echo '<div class="updated settings-error notice"><p>' . __('<b>Настройки блоков ссылок вернулись к заводским.</b>', CHERRYLINK_TEXT_DOMAIN) . '</p></div>';

            CL_Related_Block::clear_cache();
            // Show a message to say we've done something
            echo '<div class="updated settings-error notice"><p>' . __('<b>Кэш очищен.</b>', CHERRYLINK_TEXT_DOMAIN) . '</p></div>';
        }


        if (isset($_POST['clear_cache'])) {
            check_admin_referer('linkate-posts-update-options');

            CL_Related_Block::clear_cache();
            // Show a message to say we've done something
            echo '<div class="updated settings-error notice"><p>' . __('<b>Кэш очищен.</b>', CHERRYLINK_TEXT_DOMAIN) . '</p></div>';
        }
?>
        <h2>Настройки вывода для блока релевантных ссылок</h2>
        <div class="crb-admin-info">
            <h3>Ручной вывод</h3>
            <p>Для вывода блока ссылок в коде PHP прямо в файлах темы используйте код:</p>
            <code>if (function_exists('cherrylink_related_block')) echo cherrylink_related_block();</code>
            <p>Также ссылки можно вывести с помощью шорткода:</p>
            <code>[crb_show_block]</code>
            <br>
            <br>
            <input type="checkbox" id="spoiler_block" />
            <label for="spoiler_block" id="label_spoiler_block">Вывод нескольких блоков с разными ссылками</label>

            <div class="spoiler_block">
                <p>Используйте дополнительные параметры для вывода разных ссылок в блоках:</p>
                <code>if (function_exists('cherrylink_related_block')) echo cherrylink_related_block(array(<strong>'offset' => 2</strong>,<strong>'num_links' => 3</strong>,<strong>'rel_type' => 'new'</strong>,<strong>'ignore_sorting' => 'true'</strong> ));</code>
                <p>или так:</p>
                <code>
                    [crb_show_block offset=2 num_links=3 rel_type="new" ignore_sorting="true" excluded_cats="142,130"]</code> - берем свежие записи, перые 2 пропускаем и показываем всего 3 ссылки.
                <p>Возможные параметры:</p>
                <ol>
                    <li><strong>offset</strong> - отступ от начала, т.е. пропускаем заданное количество ссылок [по умолчанию 0];</li>
                    <li><strong>num_links</strong> - максимальное количество ссылок в блоке (может быть меньше или вообще ничего, если плагин ничего релевантного не нашел, учитывая offset) [если не задано берется из настроек].</li>
                    <li><strong>rel_type</strong> - значения: вывести похожие - <strong>rel</strong>; вывести новые записи - <strong>new</strong> [по умолчанию rel].</li>
                    <li><strong>ignore_sorting</strong> - отключение сортировки, напр. чтобы сохранить порядок вручную заданных ссылок.</li>
                    <li><strong>excluded_cats</strong> - исключить статьи из рубрик (перечень ID рубрик через запятую).</li>
                </ol>
                <p>Пример: чтобы вывести 3 блока и у каждого было по 3 разные ссылки, берем 3 шорткода с такими параметрами:</p>
                <code>
                    [crb_show_block num_links=3] // берем первые 3 ссылки<br>
                    [crb_show_block offset=3 num_links=3] // отступаем 3 ссылки<br>
                    [crb_show_block offset=6 num_links=3] // отступаем 6 ссылок
                </code>
                <p>Вставьте эти шорткоды в нужное место в теле статьи.</p>
            </div>

            <p><strong>Совет</strong>: выбрать ссылки или вкл/откл вывод блоков можно индивидуально для каждой статьи при ее редактировании.</p>
        </div>
        <hr>
        <form method="post" action="">
            <h3>Автоматический вывод</h3>
            <table class="optiontable form-table">
                <?php
                CL_RB_Admin_Area::show_after_content($options['crb_show_after_content']);
                CL_RB_Admin_Area::show_for_pages($options['crb_show_for_pages']);
                ?>
            </table>
            <h3>Параметры отображения</h3>
            <table class="optiontable form-table">
                <?php
                CL_RB_Admin_Area::num_of_links_to_show($options['crb_num_of_links_to_show']);
                CL_RB_Admin_Area::hide_existing_links($options['crb_hide_existing_links']);
                CL_RB_Admin_Area::show_latest($options['crb_show_latest']);
                CL_RB_Admin_Area::image_size($options['crb_image_size']);
                ?>
            </table>
            <h3>Настройка шаблона</h3>
            <table class="optiontable form-table">
                <?php
                CL_RB_Admin_Area::output_templates($options['crb_temp_before'], $options['crb_temp_link'], $options['crb_temp_after']);
                CL_RB_Admin_Area::choose_template($options['crb_choose_template']);

                ?>
            </table>
            <!-- <h3>Дополнительные параметры</h3> -->
            <input type="checkbox" id="spoiler_block_more" />
            <label for="spoiler_block_more" id="label_spoiler_block_more">Дополнительные параметры, кэш</label>

            <div class="spoiler_block_more">
                <table class="optiontable form-table">
                    <?php
                    CL_RB_Admin_Area::css_tuning($options['crb_css_tuning']);
                    CL_RB_Admin_Area::default_offset($options['crb_default_offset']);
                    CL_RB_Admin_Area::placeholder_path($options['crb_placeholder_path']);
                    CL_RB_Admin_Area::content_filter($options['crb_content_filter']);
                    CL_RB_Admin_Area::debug_enabled($options['debug_enabled']);
                    CL_RB_Admin_Area::cache_setup($options['crb_cache_minutes']);
                    CL_RB_Admin_Area::css_override($options['crb_css_override']);
                    ?>
                </table>
            </div>
            <hr>

            <h3>Превью блоков</h3>
            <p>Примерно так будут выглядеть блоки на вашем сайте.</p>
            <!--            <label for="preview_width"><strong>Ширина контейнера</strong></label><br>-->
            <!--            <input id="preview_width" type="range" min="200" max="1000" step="1" value="750"><span id="range_number"></span>-->
            <div id="preview_container" style="margin: 0 auto; max-width:750px">
                <?php echo cherrylink_related_block(array('offset' => false, 'num_links' => false, 'rel_type' => 'new')); ?>
            </div>
            <hr>
            <div class="submit"><input type="submit" class="button button-cherry" name="update_options" value="<?php _e('Сохранить настройки', CHERRYLINK_TEXT_DOMAIN) ?>" /><input type="submit" id="crb_defaults" name="crb_defaults" class="button button-download" style="float: right;" value="<?php _e(' Вернуть настройки блоков по умолчанию', CHERRYLINK_TEXT_DOMAIN) ?>" /></div>
            <?php if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
        </form>

    <?php
    }

    static function show_after_content($crb_show_after_content)
    {
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_show_after_content">Показывать блок после текста записи</label></th>
            <td>
                <select name="crb_show_after_content" id="crb_show_after_content">
                    <option <?php if ($crb_show_after_content == 'false') {
                                echo 'selected="selected"';
                            } ?> value="false">Нет</option>
                    <option <?php if ($crb_show_after_content == 'true') {
                                echo 'selected="selected"';
                            } ?> value="true">Да</option>
                </select>
            </td>
        </tr>
    <?php
    }
    static function hide_existing_links($crb_hide_existing_links)
    {
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_hide_existing_links">Скрыть ссылки, которые уже есть в тексте</label></th>
            <td>
                <select name="crb_hide_existing_links" id="crb_hide_existing_links">
                    <option <?php if ($crb_hide_existing_links == 'false') {
                                echo 'selected="selected"';
                            } ?> value="false">Нет</option>
                    <option <?php if ($crb_hide_existing_links == 'true') {
                                echo 'selected="selected"';
                            } ?> value="true">Да</option>
                </select>
            </td>
        </tr>
    <?php
    }
    static function show_latest($crb_show_latest)
    {
        if (!isset($crb_show_latest)) $crb_show_latest = 'false';
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_show_latest">Вывести свежие записи вместо релевантных</label></th>
            <td>
                <select name="crb_show_latest" id="crb_show_latest">
                    <option <?php if ($crb_show_latest == 'false') {
                                echo 'selected="selected"';
                            } ?> value="false">Нет</option>
                    <option <?php if ($crb_show_latest == 'true') {
                                echo 'selected="selected"';
                            } ?> value="true">Да</option>
                </select>
            </td>
        </tr>
    <?php
    }
    static function show_for_pages($crb_show_for_pages)
    {
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_show_for_pages">Показывать блок после текста страницы</label></th>
            <td>
                <select name="crb_show_for_pages" id="crb_show_for_pages">
                    <option <?php if ($crb_show_for_pages == 'false') {
                                echo 'selected="selected"';
                            } ?> value="false">Нет</option>
                    <option <?php if ($crb_show_for_pages == 'true') {
                                echo 'selected="selected"';
                            } ?> value="true">Да</option>
                </select>
            </td>
        </tr>
    <?php
    }
    static function num_of_links_to_show($crb_num_of_links_to_show)
    {
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_num_of_links_to_show">Макс. количество ссылок в блоке</label></th>
            <td>
                <input type="number" name="crb_num_of_links_to_show" id="crb_num_of_links_to_show" min="0" value="<?php echo intval($crb_num_of_links_to_show); ?>">
            </td>
            <td><?php link_cf_prepare_tooltip("Значение игнорируется, если ссылки заданы для статьи вручную в редакторе, или, если количество указано в шорткоде/функции."); ?></td>
        </tr>
    <?php
    }
    static function default_offset($crb_default_offset)
    {
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_default_offset">Пропустить N ссылок с начала (отступ, он же offset)</label></th>
            <td>
                <input type="number" name="crb_default_offset" id="crb_default_offset" min="0" value="<?php echo intval($crb_default_offset); ?>">
            </td>
            <td><?php link_cf_prepare_tooltip("Значение игнорируется, если отступ указан в шорткоде/функции."); ?></td>
        </tr>
    <?php
    }
    static function output_templates($crb_temp_before, $crb_temp_link, $crb_temp_after)
    {
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_temp_before">Код блока ПЕРЕД ссылками</label></th>
            <td><textarea name="crb_temp_before" id="crb_temp_before" rows="5" cols="45"><?php echo htmlspecialchars(stripslashes(urldecode(base64_decode($crb_temp_before)))); ?></textarea></td>

        </tr>
        <tr valign="top">
            <th scope="row"><label for="crb_temp_link">Код разметки ссылки</label></th>
            <td><textarea name="crb_temp_link" id="crb_temp_link" rows="5" cols="45"><?php echo htmlspecialchars(stripslashes(urldecode(base64_decode($crb_temp_link)))); ?></textarea></td>
            <td><?php link_cf_prepare_tooltip(link_cf_get_available_tags(false)); ?></td>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="crb_temp_after">Код блока ПОСЛЕ ссылок</label></th>
            <td><textarea name="crb_temp_after" id="crb_temp_after" rows="5" cols="45"><?php echo htmlspecialchars(stripslashes(urldecode(base64_decode($crb_temp_after)))); ?></textarea></td>
        </tr>
    <?php
    }
    static function cache_setup($crb_cache_minutes)
    {
        if (!isset($crb_cache_minutes)) $crb_cache_minutes = 1440;
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_cache_minutes">Время хранения кэша, в минутах </label></th>
            <td>
                <input type="number" name="crb_cache_minutes" id="crb_cache_minutes" min="0" value="<?php echo intval($crb_cache_minutes); ?>"> (1440 минут = 24 часа) / <strong>0 - отключить кэш</strong>
            </td>
        </tr>
        <tr valign="top">
            <td colspan=3><input type="submit" id="clear_cache" name="clear_cache" class="button button-download" value="<?php _e('Сбросить кэш', CHERRYLINK_TEXT_DOMAIN) ?>" /></td>
        </tr>
    <?php
    }
    static function css_tuning($crb_css_tuning)
    {
        if (!isset($crb_css_tuning) || $crb_css_tuning == 'no') $crb_css_tuning = 'default';
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_css_tuning">Едет верстка блоков? Включи опцию !important</label></th>
            <td>
                <select name="crb_css_tuning" id="crb_css_tuning">
                    <!--                    <option --><?php //if($crb_css_tuning == 'no') { echo 'selected="selected"'; } 
                                                        ?><!-- value="no">Нет загружать</option>-->
                    <option <?php if ($crb_css_tuning == 'default') {
                                echo 'selected="selected"';
                            } ?> value="default">Нет, все хорошо</option>
                    <option <?php if ($crb_css_tuning == 'important') {
                                echo 'selected="selected"';
                            } ?> value="important">Опция !important</option>
                </select>
            </td>
            <td><?php link_cf_prepare_tooltip("Включите опцию !important в случае, если наложились стили от вашей темы и поехала верстка блоков."); ?></td>
        </tr>
    <?php
    }

    static function debug_enabled($debug_enabled)
    {
    ?>
        <tr valign="top">
            <th scope="row"><label for="debug_enabled">Включить режим отладки</label></th>
            <td>
                <select name="debug_enabled" id="debug_enabled">
                    <option <?php if ($debug_enabled == 'false') {
                                echo 'selected="selected"';
                            } ?> value="false">Нет</option>
                    <option <?php if ($debug_enabled == 'true') {
                                echo 'selected="selected"';
                            } ?> value="true">Да</option>
                </select>
            </td>
            <td><?php link_cf_prepare_tooltip("Опция для отладки. Не включайте, если не знаете что это."); ?></td>
        </tr>
    <?php
    }

    static function choose_template($crb_choose_template)
    {
        $templates = CL_RB_Admin_Area::get_templates();
        if (empty($crb_choose_template)) $crb_choose_template = 'crb-template-simple.css';
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_choose_template">Стиль блоков (см. превью ниже)</label></th>
            <td>
                <select name="crb_choose_template" id="crb_choose_template">
                    <option <?php if ($crb_choose_template == 'none') {
                                echo 'selected="selected"';
                            } ?> value="none">Не загружать стили</option>
                    <?php
                    foreach ($templates as $file) {
                    ?>
                        <option <?php if ($crb_choose_template == $file) echo "selected='selected'"; ?> value='<?php echo $file; ?>'><?php echo explode('-', explode('.', $file)[0])[2]; ?></option>
                    <?php
                    }
                    ?>
                </select>
            </td>
            <td><?php link_cf_prepare_tooltip("Применяется только если вы используете стандартные классы в шаблоне. Вы можете задать свои стили в файле темы и любой произвольный шаблон на ваше усмотрение."); ?></td>
        </tr>
    <?php
    }

    static function image_size($crb_image_size)
    {
        $sizes = CL_RB_Admin_Area::get_image_sizes();
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_image_size">Размер изображения миниатюр</label></th>
            <td>
                <select name="crb_image_size" id="crb_image_size">
                    <option <?php if (empty($crb_image_size)) {
                                echo 'selected="selected"';
                            } ?> value="">Оригинальный размер</option>
                    <?php
                    foreach ($sizes as $k => $arr) {
                    ?>
                        <option <?php if ($crb_image_size == $k) echo "selected='selected'"; ?> value='<?php echo $k; ?>'><?php echo $k . " (" . $arr['width'] . "x" . $arr['height'] . ")"; ?></option>
                    <?php
                    }
                    ?>
                </select>
            </td>
            <td><?php link_cf_prepare_tooltip("Выберите размер изображения из доступных вариантов для тега {imagesrc}."); ?></td>
        </tr>
    <?php
    }

    static function placeholder_path($crb_placeholder_path)
    {
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_placeholder_path">Путь до картинки placeholder</label></th>

            <td>
                <input type="text" name="crb_placeholder_path" id="crb_placeholder_path" value="<?php echo $crb_placeholder_path; ?>">
            </td>

            <td><?php link_cf_prepare_tooltip("Эта картинка будет показана в том случае, если плагину не удалось найти изображения в записи. Нужно указать полный путь, например, https://domain.ru/wp-content/uploads/placeholder.jpg. Можете оставить поле пустым и выведется стандартная картинка из плагина."); ?></td>
        </tr>
    <?php
    }
    static function content_filter($crb_content_filter)
    {
    ?>
        <tr valign="top">
            <th scope="row"><label for="crb_content_filter">Поиск картинок в записи</label></th>
            <td>
                <select name="crb_content_filter" id="crb_content_filter">
                    <option <?php if ($crb_content_filter == 0) {
                                echo 'selected="selected"';
                            } ?> value="0">Обычный режим</option>
                    <option <?php if ($crb_content_filter == 1) {
                                echo 'selected="selected"';
                            } ?> value="1">Применить фильтры</option>
                </select>
            </td>
            <td><?php link_cf_prepare_tooltip("Если плагин не может найти картинки в постах, а они там есть, попробуйте режим _Применить фильтры_. В коде будет вызван метод apply_filters('the_content', ...), который обрабатывает все хуки от плагинов и шорткоды в контенте записи."); ?></td>
        </tr>
    <?php
    }

    static function css_override($crb_css_override)
    {
        if (!isset($crb_css_override))
            $crb_css_override = array('desc' => array('columns' => 3, 'gap' => 20), 'mob' => array('columns' => 2, 'gap' => 10));
    ?>
        <tr valign="top">
            <th scope="row" colspan="3" style="">
                <h3>Параметры сетки</h3>
            </th>
        </tr>
        <tr valign="top">
            <th scope="row"><label>Для десктопа</label></th>
            <td>
                Колонки: <input type="number" name="crb_css_override[desc][columns]" min="1" max="20" size="50" value="<?php echo intval($crb_css_override['desc']['columns']); ?>">
                Отступ между: <input type="number" name="crb_css_override[desc][gap]" min="1" max="100" size="50" value="<?php echo intval($crb_css_override['desc']['gap']); ?>">
            </td>
            <td>
                <?php link_cf_prepare_tooltip("Значения в пикселях."); ?>

            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label>Для мобильных</label></th>
            <td>
                Колонки: <input type="number" name="crb_css_override[mob][columns]" min="1" max="20" size="50" value="<?php echo intval($crb_css_override['mob']['columns']); ?>">
                Отступ между: <input type="number" name="crb_css_override[mob][gap]" min="1" max="100" size="50" value="<?php echo intval($crb_css_override['mob']['gap']); ?>">
            </td>
            <td>
                <?php link_cf_prepare_tooltip("Значения в пикселях."); ?>

            </td>
        </tr>
<?php
    }

    static function get_image_sizes()
    {
        global $_wp_additional_image_sizes;

        $sizes = array();

        foreach (get_intermediate_image_sizes() as $_size) {
            if (in_array($_size, array('thumbnail', 'medium', 'medium_large', 'large'))) {
                $sizes[$_size]['width']  = get_option("{$_size}_size_w");
                $sizes[$_size]['height'] = get_option("{$_size}_size_h");
                $sizes[$_size]['crop']   = (bool) get_option("{$_size}_crop");
            } elseif (isset($_wp_additional_image_sizes[$_size])) {
                $sizes[$_size] = array(
                    'width'  => $_wp_additional_image_sizes[$_size]['width'],
                    'height' => $_wp_additional_image_sizes[$_size]['height'],
                    'crop'   => $_wp_additional_image_sizes[$_size]['crop'],
                );
            }
        }

        return $sizes;
    }

    static function get_templates()
    {
        $path = CHERRYLINK_DIR . '/css/';
        $files = array_diff(scandir($path), array('.', '..'));
        $files = array_filter($files, function ($v, $k) {
            return strpos($v, 'template') && !strpos($v, 'important') && !strpos($v, 'admin');
        }, ARRAY_FILTER_USE_BOTH);
        return $files;
    }
}
