jQuery(document).ready(function ($) {
    // cl_ prefix for variables
    // fcl_ prefix for functions
    const T_WAIT_EDITOR_INPUT = 200; // look timeOutChecker() 
    const T_WAIT_FILTER = 200; // delay on filter input
    const T_WAIT_FILTER_CB = 0; // delay on checkbox change
    const T_WAIT_TOTAL_LINKS = 100; // delay because function called twice
    const T_WAIT_SHOW_PANEL = 100; // delay on open panel

    let cl_total_links = 0; // total links from list or all from text
    let cl_urls_in_content; // array of urls
    const cl_allow_multilink = cherrylink_options['multilink'] == "checked" ? true : false;
    const cl_exists_class = cl_allow_multilink ? 'link-exists-multi' : 'link-exists';

    let cl_list_links = $('div[class*="linkate-link"]:not(.link-term)');
    let cl_list_terms = $('div[class*="link-term"]');

    let cl_individual_stats = false;
    let incoming_links_array = []; // to mark links which point to the current article
    let cl_whitelist = [];
    let cl_blacklist = [];

    const cl_open_button = $('.linkate-button');
    // var cl_editor_textarea = $("#content")[0];
    const cl_editor_textarea = $("textarea"); // all textareas

    let cl_editor_lastfocus = $("#content")[0]; // "#content" is a placeholder in case, if value will be empty 
    let cl_editor_lastfocus_html_content; // for suggestions
    let cl_suggestion_template_object;

    const snowball = new Snowball('Russian');
    let stem_cache = [];

    const cl_quick_cat_select = $('#quick_cat_filter');
    let cl_articles_scrolltop = 0;

    // offset equals to skip, when data is loading from server SKIP, LIMIT
    let fcl_get_data_offset = 0;

    // timers for listeners/filters/panel updates
    let cl_timerCheck;
    let cl_timerFilter;
    let cl_timerTotalLinks;

    /* ===================       ==================== */
    /* =================== CRB Meta ==================== */
    /* ===================       ==================== */

    const cl_crb_metabox = $('#crb-meta-links');

    function fcl_update_meta_visual() {
        if (cl_crb_metabox.length > 0) {
            let meta_text = $(cl_crb_metabox).val();
            if (meta_text.length > 0) {
                meta_text = meta_text.split("\n");
                let visual_content = '<p>Для данной записи выбраны ссылки:</p>';
                if ($('#crb-meta-use-manual').is(':checked')) {
                    meta_text.forEach(function (row) {
                        let split = row.split('[|]');
                        let row_id = split[0];
                        let row_title = split[1];
                        let row_m_title = split[2] === undefined ? "" : split[2];

                        visual_content += "<div title='Намите на крестик, чтобы удалить из списка' class='crb-meta-visual-item crb-editable' data-postid='" + row_id + "' data-title='" + row_title + "'><div class='crb-remove-item'></div><span class='crb-manual-id'>Задайте свой анкор к статье: [ID: " + row_id + "] <strong>" + row_title + "</strong></span><div><input type='text' class='crb-manual-input " + row_id + "' value='" + row_m_title + "' /></div></div>";
                    });
                } else {
                    meta_text.forEach(function (row) {
                        let split = row.split('[|]');
                        let row_id = split[0];
                        let row_title = split[1];
                        visual_content += "<div title='Намите, чтобы удалить из списка' class='crb-meta-visual-item' data-postid='" + row_id + "' data-title='" + row_title + "'><div class='crb-remove-item'></div>[ " + row_id + " ] " + row_title + "</div>";
                    });
                }

                $('.crb-meta-visual').html(visual_content);
            } else {
                let host = window.location.href.replace(window.location.href.slice(window.location.href.indexOf('/wp-admin/') + 10), 'options-general.php?page=linkate-posts&subpage=output_block');
                let visual_content = '<p>Ссылки не выбраны - будут показаны похожие статьи в соответствии с <a href="' + host + '">настройками плагина</a>.</p>';
                $('.crb-meta-visual').html(visual_content);
            }
        }
        $('.crb-remove-item').unbind().on('click', function (e) {
            crb_remove_from_block($(this).parent().attr('data-postid'));
        })

        $('.crb-manual-input').on('change', function (e) {
            let m_text = e.target.value;
            let post_id = e.target.classList[1];
            let meta_links = $(cl_crb_metabox).val();

            meta_links = meta_links.length > 0 ? meta_links.split("\n").map(x => {
                let y = x.split("[|]");
                if (y[0] === post_id) {
                    return y[0] + "[|]" + y[1] + "[|]" + m_text + "[|]" + y[3];
                } else {
                    return y[0] + "[|]" + y[1] + "[|]" + y[2] + "[|]" + y[3];
                }
            }) : [];

            meta_links = meta_links.length > 0 ? meta_links.join("\n") : "";
            $(cl_crb_metabox).val(meta_links).trigger('change');
        })
    }

    fcl_update_meta_visual();

    // for related block
    function crb_remove_from_block(post_id) {
        // let this_string = post_id + "[|]" + title;
        if (cl_crb_metabox.length > 0) {
            let meta_links = $(cl_crb_metabox).val();
            let remIndex = crb_link_exist_in_metabox(meta_links, post_id);

            if (remIndex !== false) {
                meta_links = meta_links.split('\n');
                meta_links.splice(remIndex, 1);
                meta_links = meta_links.join('\n');
                $(cl_crb_metabox).val(meta_links).trigger('change');
            }

        }
    }

    function crb_link_exist_in_metabox(crb_textarea_text, post_id) {
        if (crb_textarea_text.length > 0) {
            let meta_links = crb_textarea_text.split('\n');
            let remIndex = false;

            meta_links.some((x, i) => {
                if (x.includes(post_id + "[|]")) {
                    remIndex = i;
                    return true;
                }
                return false;
            })
            return remIndex;
        }

        return false;
    }

    function crb_detect_show_options_edited() {
        if ($('#crb-meta-show').length > 0) {
            $('#crb-meta-show').change(function () {
                $('#crb-meta-show-edited').prop('checked', true);
            })
        }
    }
    crb_detect_show_options_edited();

    // main function - reload listeners to re-attach actions to the views
    // I will never be true software engineer with shit like this
    function reLoad_when_data_received() {
        /* =================== Input/change listeners ==================== */

        // recollect links
        cl_list_links = $('div[class*="linkate-link"]:not(.link-term)');

        timeOutLinksChecker(T_WAIT_SHOW_PANEL);

        $(cl_crb_metabox).unbind().on('change', function () {
            timeOutLinksChecker(0);
            fcl_update_meta_visual();
        })

        $('#crb-meta-use-manual').unbind().on('change', function () {
            fcl_update_meta_visual();
        })

        // incoming stats
        $('#links-count-targets').unbind().click(function () {
            fcl_toggle_links_stats();
        })

        $('.link-suggestions').unbind().click(function (e) {
            // remember scroll position
            cl_articles_scrolltop = $('#cherrylink_meta_inside').scrollTop();
            // item contains all the data about the article: titles, url, etc
            let item = fcl_getDataAttrs(e, false, true);
            let suggestions = item.suggestions.trim();

            // if no tokens provided, then open empty panel
            if (suggestions == '')
                fcl_toggle_suggestions_tab(this, [], []);
            
            cl_suggestion_template_object = extractLinkTemplate(item, e);

            fcl_cleanSuggentionSpans();
            
            let {nearest_single, nearest_multi} = fcl_find_suggestions_from_text(suggestions);

            // open suggestions panel
            fcl_toggle_suggestions_tab(this, nearest_single, nearest_multi);
            return false;
        });

        // View article front
        // $('.link-preview').unbind().click(function () {
        //     let url = $(this).parent().parent().find('div.linkate-link').attr('data-url');
        //     window.open(url, '_blank');
        // });

        // Edit article
        $('.link-preview').unbind().click(function () {
            let id = $(this).parent().parent().find('div.linkate-link').attr('data-postid');
            let url = '/wp-admin/post.php?action=edit&post=' + id;
            window.open(url, '_blank');
        });

        $('.link-counter').unbind().click(function () {
            if ($(this).hasClass('link-counter-good') || $(this).hasClass('link-counter-bad')) {
                let parent = $(this).parent()[0];
                let url = '';
                if (parent.tagName === 'LI') {
                    url = $(parent).find('div.linkate-link').attr('data-url');
                } else {
                    url = $(parent).parent().find('div.linkate-link').attr('data-url');
                }
                if (fcl_isTinyMCE()) {
                    selectExistingTinyMCE(url);
                }
                else {
                    selectExistingTextarea(url);
                }
            }
        });

        if ($('.link-add-to-block').length > 0) { // For Related Block
            $('.link-add-to-block').unbind().click(function () {
                let post_id = $(this).parent().parent().find('div.linkate-link').attr('data-postid');
                let title = $(this).parent().parent().find('div.linkate-link').attr('data-title');
                let this_string = post_id + "[|]" + title + "[|]";
                if (cl_crb_metabox.length > 0) {
                    let meta_links = $(cl_crb_metabox).val();
                    if (meta_links.length > 0) {
                        meta_links = meta_links.split('\n');
                        if (!meta_links.includes(this_string)) {
                            meta_links.push(this_string);
                            meta_links = meta_links.join('\n');
                            $(cl_crb_metabox).val(meta_links).trigger('change');
                        }
                    } else {
                        $(cl_crb_metabox).val(this_string).trigger('change');
                    }
                }
            });
        }
        if ($('.link-del-from-block').length > 0) { // For Related Block
            $('.link-del-from-block').unbind().click(function () {
                let post_id = $(this).parent().parent().find('div.linkate-link').attr('data-postid');
                let title = $(this).parent().parent().find('div.linkate-link').attr('data-title');
                crb_remove_from_block(post_id)
            });
        }

        $('#hide_that_exists').change(function () {
            timeOutLinksFilter(T_WAIT_FILTER_CB);
        });
        $('#show_that_exists').change(function () {
            timeOutLinksFilter(T_WAIT_FILTER_CB);
        });
        $(cl_quick_cat_select).change(function () {
            timeOutLinksFilter(T_WAIT_FILTER_CB);
        });
        $('#filter_by_title').on('input propertychange', function () {
            timeOutLinksFilter(T_WAIT_FILTER);
        });
        $('.filter-clear-box').click(function (e) {
            $('#filter_by_title').val("");
            $('#filter_by_title').trigger("propertychange");
        })

        if (cl_editor_textarea)
            fcl_setListeners(); // if there is no editor - don't bother to load shit

       
        function fcl_setListeners() {

            $("textarea").on('input propertychange', function (e) {
                timeOutLinksChecker(T_WAIT_EDITOR_INPUT);

            });
            $("textarea").on('focus keyup mousedown', function (e) {
                cl_editor_lastfocus = e.target;
            });
            if (cherrylink_options['quickfilter_dblclick'] === "true")
                $("textarea").on('mouseup', function (e) {
                    var start = this.selectionStart;
                    var finish = this.selectionEnd;
                    var sel = this.value.substring(start, finish);
                    if (sel) {
                        $('#filter_by_title').val(sel);
                        $('#filter_by_title').trigger("propertychange");
                    }

                });
            try {
                for (let editor of tinymce.editors) {
                    editor.on('ExecCommand change', function (event) {
                        timeOutLinksChecker(T_WAIT_EDITOR_INPUT);
                    });
                    editor.on('focus mousedown keyup change', function (event) {
                        cl_editor_lastfocus = 'RICH_EDITOR';
                    });
                    if (cherrylink_options['quickfilter_dblclick'] === "true")
                        editor.on('mouseup', function (event) {
                            if (editor.selection.getContent()) {
                                $('#filter_by_title').val(editor.selection.getContent({ format: 'text' }));
                                $('#filter_by_title').trigger("propertychange");
                            }
                        })
                }
            } catch (err) {
                console.log(err)
            }
        }

        cl_list_links.unbind().click(function (e) {
            prepareLinkTemplate(e, false);
        });

        cl_list_terms.unbind().click(function (e) {
            prepareLinkTemplate(e, true);
        });

        fcl_markIncomingLinks();


        /* =================== END listeners ==================== */

        /* =================== SUGGESTIONS ====================== */

        function fcl_toggle_suggestions_tab(div, single_words, multi_words) {
            let ankors_in_use = fcl_getAllAnkorsInUse(cl_editor_lastfocus_html_content);
            let insert_button = '<div class="suggestion-insert-anywhere" data-url="' + $(div).parent().parent().find('.linkate-link').attr('data-url') + '" title="Запасная кнопка, может пригодится, если вы выделили кусок текста вручную">&#9088; Вставить вокруг выделения</div>';
            let reindex_link = window.location.href.replace(window.location.href.slice(window.location.href.indexOf('/wp-admin/') + 10), 'options-general.php?page=linkate-posts&subpage=other');
            let panel_header = (single_words.length > 0 || multi_words.length > 0) ? 'Найдены предполагаемые анкоры для: <strong>' + $(div).parent().parent().find('.linkate-link').attr('data-titleseo') + '</strong>' + insert_button : '<strong>Подсказки не найдены<br>Если вы видите эту надпись у всех ссылок, возможно вам нужно изменить <a target="_blank" href="' + reindex_link + '">настройки доноров подсказок</a>.</strong>';
            let panel = '<div class="suggestions-panel"><div class="suggestions-panel-content"><div class="suggestions-panel-header">' + panel_header + '</div>';
            if (ankors_in_use.length > 0) {
                panel += '<div class="suggestions-panel-words"><div class="suggestions-panel-words-in-use-header"> > Использованные анкоры в статье</div><div class="suggestions-panel-words-in-use-text"><ul>';
                let c = 0; // to hide used ankors if many
                ankors_in_use.forEach(function (el) {
                    if (c > 2) {
                        panel += '<li class="suggestions-word-in-use-hide">' + el.ankor + '</li>';
                    } else {
                        panel += '<li>' + el.ankor + '</li>';
                    }
                    c++;
                })
                if (ankors_in_use.length > 3) {
                    panel += '</ul><a class="words-in-use-show-btn">Показать все ' + c + '...</a><div style="clear:both;"></div></div></div>';
                } else {
                    panel += '</ul></div></div>';
                }
            }
            if (multi_words.length > 0) {
                panel += '<div class="suggestions-panel-frases"><div class="suggestions-panel-frases-header"> > Фразы-анкоры (найдено: ' + multi_words.length + ') </div>';
                panel = fcl_generateSuggestionsTemplateDropDown(panel, multi_words, false);
                panel += '</div>';
            }
            if (single_words.length > 0) {
                panel += '<div class="suggestions-panel-words"><div class="suggestions-panel-words-header"> > Простые анкоры (найдено: ' + single_words.length + ')</div>';
                panel = fcl_generateSuggestionsTemplateDropDown(panel, single_words, true);
                panel += '</div>';
            }

            panel += '</div></div>';
            $(div).parent().parent().parent().parent().before(panel);
            fcl_enableSuggestionSwitches();
            if (cherrylink_options["suggestions_switch_action"] === "true")
                fcl_findSuggestionOnHover();
            fcl_toggleOnOffGeneralUI(true);
        }

        /* ================== SUGGESTIONS VIEWS AND LOGIC =============== */

        function fcl_generateSuggestionsTemplateDropDown(panel, words_array, is_single) {
            let fast_action_class = ""; // do fast insert on click, if option set
            let stop_btn = is_single ? "<div><a title=\"В стоп слова\" class=\"suggestion-stop\"> </a></div>" : "";
            if (cherrylink_options["suggestions_switch_action"] === "true") {
                fast_action_class = " suggestion-fast-insert";
            }
            panel += "<div class=\"suggestion-group\"><div class=\"suggestion-select-list\">"
            words_array.sort((a,b) => (a[3] - b[3])).forEach(function (el, id, array) {
                // let relative_position = Math.round((el[0] / cl_editor_lastfocus_html_content.length) * 100);
                panel += "<div class=\"suggestion-select-option \" data-start=\"" + el[0] + "\" data-end=\"" + el[1] + "\" data-text=\"" + el[2] + "\"><div class=\"suggestion-buttons\"><a title=\"Найти в тексте\" class=\"suggestion-find\"> </a><a class=\"suggestion-insert\" title=\"Вставить ссылку\">&#9088;</a></div><div class=\"suggestion-select-option-text" + fast_action_class + "\">" + fcl_strip(el[2]) + " <span style='font-size: smaller; color:lightgrey'>(" + el[3] + "%)</span> </div>" + stop_btn + "</div>";
            });
            panel += "</div></div>";
            return panel;
        }

        function fcl_findSuggestionOnHover() {
            $(".suggestion-select-option").off("mouseenter mouseleave").hover(function () {
                fcl_findAndSelectSuggestionDropDown(this);
            })
            $(".suggestion-select-option-text").click(function () {
                fcl_suggestionFindandInsertLink($(this).parent());
            })
        }

        // Switch between suggestion ui and links/terms lists
        function fcl_toggleOnOffGeneralUI(is_suggestions) {
            if (is_suggestions) {
                $('.linkate-filter-bar').hide();
                $('.linkate-tabs').hide();
                $('.container-articles').hide();
                $('.suggestions-panel').fadeIn();
                $('.suggestions-panel-back').fadeIn();
            } else {
                $('.suggestions-panel-back').hide();
                $('.linkate-filter-bar').fadeIn();
                $('.linkate-tabs').fadeIn();
                $('.container-articles').fadeIn('fast', function () {
                    $('#cherrylink_meta_inside').scrollTop(cl_articles_scrolltop);
                });
            }
        }

        function fcl_enableSuggestionSwitches() {
            $('.words-in-use-show-btn').unbind().click(function () {
                $(this).prev().find('li[class*="suggestions-word-in-use-hide"]').show();
                $(this).remove();
            })

            $('.suggestions-panel-back').unbind().click(function () {
                $('.suggestions-panel').remove();
                fcl_toggleOnOffGeneralUI(false);
                fcl_cleanSuggentionSpans();
                return false;
            })
            $('.suggestion-find').unbind().click(function (e) {
                fcl_findAndSelectSuggestionDropDown($(this).parent().parent());
            })
            $('.suggestion-insert').unbind().click(function (e) {
                let el = $(this).parent().parent();
                fcl_suggestionFindandInsertLink(el);
            })

            $('.suggestion-stop').unbind().click(function (e) {
                let reindex_link = window.location.href.replace(window.location.href.slice(window.location.href.indexOf('/wp-admin/') + 10), 'options-general.php?page=linkate-posts&subpage=other');
                let word = $(this).parent().parent().attr("data-text");
                e.preventDefault();

                let ajax_data = {
                    words: [word],
                    action: 'linkate_add_stopwords',
                    is_white: 0
                };
                $.each($(this).parent().parent().parent().children(), function (ind, val) {
                    if ($(val).attr('data-text').toLowerCase() === word.toLowerCase()) {
                        $(val).css('background', 'tomato');
                        $(val).find('.suggestion-stop').remove();
                    }
                });

                $.ajax({
                    type: "POST",
                    url: ajaxurl,
                    data: ajax_data,
                    datatype: 'json',
                    success: function (response) {
                        if ($('.suggestions-panel-content').find('.suggestions-reindex-notice').length === 0)
                            $('.suggestions-panel-header').before('<div class="suggestions-reindex-notice">Для учета новых стоп-слов необходимо сделать реиндексацию в <a target="_blank" href="' + reindex_link + '">настройках</a>.</div>')
                    }
                });
            })

            $('.suggestion-insert-anywhere').unbind().click(function (e) {
                if (fcl_isTinyMCE()) {
                    fcl_insertSuggestionTinyMCE(cl_suggestion_template_object.temp_before, cl_suggestion_template_object.temp_after);
                    tinymce.activeEditor.fire('change');
                } else {
                    fcl_insertSuggestionTextarea(cl_suggestion_template_object.temp_before, cl_suggestion_template_object.temp_after);
                    $(cl_editor_lastfocus).trigger('propertychange');
                }
                $('.suggestions-panel').remove();
                fcl_toggleOnOffGeneralUI(false);
            })
        }

        function fcl_suggestionFindandInsertLink(element) {
            let start = $(element).attr('data-start');
            let end = $(element).attr('data-end');
            let word = $(element).attr('data-text');

            if (fcl_isTinyMCE()) {
                fcl_selectSuggestionTinyMCE(start, end, word);
                fcl_insertSuggestionTinyMCE(cl_suggestion_template_object.temp_before, cl_suggestion_template_object.temp_after);
                tinymce.activeEditor.fire('change');
            } else {
                fcl_selectSuggestionTextArea(start, end);
                fcl_insertSuggestionTextarea(cl_suggestion_template_object.temp_before, cl_suggestion_template_object.temp_after);
                $(cl_editor_lastfocus).trigger('propertychange');
            }

            $('.suggestions-panel').remove();
            fcl_cleanSuggentionSpans();
            fcl_toggleOnOffGeneralUI(false);
        }

        function fcl_findAndSelectSuggestionDropDown(element) {
            let start = $(element).attr('data-start');
            let end = $(element).attr('data-end');
            let word = $(element).attr('data-text');

            if (fcl_isTinyMCE()) {
                fcl_selectSuggestionTinyMCE(start, end, word);
            } else {
                fcl_selectSuggestionTextArea(start, end);
            }
        }

        $('.suggestions-panel-close').unbind().click(function () {
            $(this).remove();
        })

        /* =================== END SUGGESTIONS ================== */

        // When pressed link which is already in text, it's url will be found and selected in the editor
        function selectExistingTextarea(url) {
            let regex = new RegExp("href=[\"\']([^\"\']*?" + url + ")[\"\']", "i");
            let start = cl_editor_lastfocus.value.search(regex);
            if (start !== -1) {
                start = start + 6;
                let m = cl_editor_lastfocus.value.match(regex);
                let end = start + m[1].length;

                cl_editor_lastfocus.setSelectionRange(start, end);

                let charsPerRow = cl_editor_lastfocus.cols;
                let selectionRow = (start - (start % charsPerRow)) / charsPerRow;
                let lineHeight = cl_editor_lastfocus.clientHeight / cl_editor_lastfocus.rows;

                // scroll !!
                cl_editor_lastfocus.scrollTop = lineHeight * selectionRow;
                cl_editor_lastfocus.focus();
            }
        }

        function selectExistingTinyMCE(url) {
            let selection = tinyMCE.activeEditor.dom.select('a[href$="' + url + '"]')[0];
            if (selection) {
                tinyMCE.activeEditor.selection.select(selection);
                selection.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
            }
        }

        // Select suggestion
        function fcl_selectSuggestionTextArea(start, end) {
            cl_editor_lastfocus.setSelectionRange(start, end);

            let charsPerRow = cl_editor_lastfocus.cols;
            let selectionRow = (start - (start % charsPerRow)) / charsPerRow;
            let lineHeight = cl_editor_lastfocus.clientHeight / cl_editor_lastfocus.rows;

            // scroll !!
            cl_editor_lastfocus.scrollTop = lineHeight * selectionRow;
            cl_editor_lastfocus.focus();
        }

        function fcl_selectSuggestionTinyMCE(start, end, word) {
            let content = cl_editor_lastfocus_html_content.substring(0, start) + '<span id="sugg_' + start + '_' + end + '">' + word + '</span>' + cl_editor_lastfocus_html_content.substring(end, cl_editor_lastfocus_html_content.length);
            tinyMCE.activeEditor.setContent(content);

            let selection = tinyMCE.activeEditor.dom.select('span[id="sugg_' + start + '_' + end + '"]')[0];

            if (selection) {
                tinyMCE.activeEditor.selection.select(selection);
                selection.scrollIntoView({ behavior: "instant", block: "center", inline: "nearest" });
            }

        }

        // Clean the text if we left any selection spans in it
        function fcl_cleanSuggentionSpans() {
            let txt = fcl_suggestions_getEditorContent();
            //console.log(txt);
            txt = txt.replace(/<span id="sugg_\d*_\d*">([^>]+)<\/span>/gi, "\$1");
            //console.log(txt);
            if (fcl_isTinyMCE()) {
                tinymce.activeEditor.setContent(txt);
            } else {
                cl_editor_lastfocus.value = txt;
            }
        }

        function extractLinkTemplate(i, event) {
            let before_param = 'data-before';
            if (event.ctrlKey || event.metaKey) {
                before_param = 'data-temp-alt';
            }
            let temp_before = decodeURIComponent(atob($('#link_template').attr(before_param)));
            let temp_after = event.ctrlKey || event.metaKey ? '' : decodeURIComponent(atob($('#link_template').attr('data-after')));

            if (temp_before) {
                temp_before = fcl_replace_placeholders_post(i, temp_before);
            }
            if (temp_after) {
                temp_after = fcl_replace_placeholders_post(i, temp_after);
            }
            return {
                temp_before,
                temp_after
            }
        }

        function fcl_replace_placeholders_post(i, template) {
            return template.replace(/{url}/g, i.url)
                .replace(/{title}/g, i.title)
                .replace(/{title_seo}/g, i.title_seo)
                .replace(/{categorynames}/g, i.categorynames)
                .replace(/{date}/g, i.date)
                .replace(/{author}/g, i.author)
                .replace(/{postid}/g, i.postid)
                .replace(/{imagesrc}/g, i.imagesrc)
                .replace(/{anons}/g, i.anons)
                .replace(/\+/g, ' ')
                .replace(/\\/g, '');
        }

        function replaceSelectedLink(i, event) {

            // decode from base64 link template
            let template_obj = extractLinkTemplate(i, event);
            let temp_before = template_obj.temp_before;
            let temp_after = template_obj.temp_after;

            if (fcl_isTinyMCE()) {
                replaceSelectedTinyMCE(temp_before, temp_after, event, i, false);
                tinymce.activeEditor.fire('change');
            } else {
                replaceSelectedTextarea(temp_before, temp_after, event, i, false);
                $(cl_editor_lastfocus).trigger('propertychange');
            }
        }

        function replaceSelectedTerm(i, event) {
            let before_param = 'data-before';
            if (event.ctrlKey || event.metaKey) {
                before_param = 'data-term-temp-alt';
            }
            // decode from base64 link template
            let temp_before = decodeURIComponent(atob($('#term_template').attr(before_param)))
                .replace(/{url}/g, i.url)
                .replace(/{title}/g, i.title)
                .replace(/{taxonomy}/g, i.taxonomy)
                .replace(/\+/g, ' ')
                .replace(/\\/g, '');
            let temp_after = event.ctrlKey || event.metaKey ? '' : decodeURIComponent(atob($('#term_template').attr('data-after')))
                .replace(/{url}/g, i.url)
                .replace(/{title}/g, i.title)
                .replace(/{taxonomy}/g, i.taxonomy)
                .replace(/\+/g, ' ')
                .replace(/\\/g, '');

            if (fcl_isTinyMCE()) {
                replaceSelectedTinyMCE(temp_before, temp_after, event, i, true);
                tinymce.activeEditor.fire('change');
            } else {
                replaceSelectedTextarea(temp_before, temp_after, event, i, true);
                $(cl_editor_lastfocus).trigger('propertychange');
            }
        }

        function replaceSelectedTextarea(temp_before, temp_after, event, item, is_term) {
            let start = cl_editor_lastfocus.selectionStart;
            // obtain the index of the last selected character
            let finish = cl_editor_lastfocus.selectionEnd;

            let before = cl_editor_lastfocus.value.substring(0, start);
            let between = cl_editor_lastfocus.value.substring(start, finish);
            let after = cl_editor_lastfocus.value.substring(finish, cl_editor_lastfocus.value.length);


            if (!(event.ctrlKey || event.metaKey) && (between == undefined || between.length == 0)) {
                switch (cherrylink_options['no_selection_action']) {
                    case 'title': between = item.title_seo; break;
                    case 'h1': between = item.title; break;
                    case 'placeholder': between = 'ТЕКСТ_ССЫЛКИ'; break;
                    case 'empty': between = '&nbsp;'; break;
                }
            }

            let arr = fcl_tinymce_move_trailing_space(between);
            let text;

            if (arr['hasSpaces'] == true) {
                text = before + arr['first'] + temp_before + arr['selection'] + temp_after + arr['last'] + after;
            } else {
                text = before + temp_before + between + temp_after + after;
            }

            cl_editor_lastfocus.value = text;
        }

        function replaceSelectedTinyMCE(temp_before, temp_after, event, item, is_term) {
            let selection = tinymce.activeEditor.selection.getContent();

            if (selection) {
                let arr = fcl_tinymce_move_trailing_space(selection);
                if (arr['hasSpaces'] == true) {
                    tinymce.activeEditor.selection.setContent(arr['first'] + temp_before + arr['selection'] + temp_after + arr['last']);
                } else {
                    tinymce.activeEditor.selection.setContent(temp_before + selection + temp_after);
                }
            }
            else {
                let between = '';
                if (!(event.ctrlKey || event.metaKey) && (between == undefined || between.length == 0)) {
                    switch (cherrylink_options['no_selection_action']) {
                        case 'title': between = item.title_seo; break;
                        case 'h1': between = item.title; break;
                        case 'placeholder': between = 'ТЕКСТ_ССЫЛКИ'; break;
                        case 'empty': between = '&nbsp;'; break;
                    }
                }
                tinymce.activeEditor.execCommand('mceInsertContent', false, temp_before + between + temp_after);
            }

        }

        // insert suggestion
        function fcl_insertSuggestionTextarea(temp_before, temp_after) {
            let start = cl_editor_lastfocus.selectionStart;
            // obtain the index of the last selected character
            let finish = cl_editor_lastfocus.selectionEnd;

            let before = cl_editor_lastfocus.value.substring(0, start);
            let between = cl_editor_lastfocus.value.substring(start, finish);
            let after = cl_editor_lastfocus.value.substring(finish, cl_editor_lastfocus.value.length);

            let arr = fcl_tinymce_move_trailing_space(between);
            let text;

            if (arr['hasSpaces'] == true) {
                text = before + arr['first'] + temp_before + arr['selection'] + temp_after + arr['last'] + after;
            } else {
                text = before + temp_before + between + temp_after + after;
            }

            cl_editor_lastfocus.value = text;
        }

        function fcl_insertSuggestionTinyMCE(temp_before, temp_after) {
            let selection = tinymce.activeEditor.selection.getContent();

            if (selection) {
                let arr = fcl_tinymce_move_trailing_space(selection);
                if (arr['hasSpaces'] == true) {
                    tinymce.activeEditor.selection.setContent(arr['first'] + temp_before + arr['selection'] + temp_after + arr['last']);
                } else {
                    tinymce.activeEditor.selection.setContent(temp_before + selection + temp_after);
                }
            }
        }

        function fcl_getDataAttrs(event, is_term, is_suggestion) {
            let element = is_suggestion ? $(event.target).parent().parent().find('.linkate-link')[0] : event.target;
            let url = fcl_getAttr(element, 'data-url');
            let title = fcl_getAttr(element, 'data-title');
            let exists = fcl_hasClassExists(element);
            if (is_term) {
                let taxonomy = fcl_getAttr(element, 'data-taxonomy');
                return { url, title, taxonomy, exists };
            } else {
                let title_seo = fcl_getAttr(element, 'data-titleseo');
                let categorynames = fcl_getAttr(element, 'data-category');
                let date = fcl_getAttr(element, 'data-date');
                let author = fcl_getAttr(element, 'data-author');
                let postid = fcl_getAttr(element, 'data-postid');
                let imagesrc = fcl_getAttr(element, 'data-imagesrc');
                let anons = fcl_getAttr(element, 'data-anons');
                let suggestions = fcl_getAttr(element, 'data-suggestions');
                return { 
                    url, 
                    title, 
                    title_seo, 
                    categorynames, 
                    date, 
                    author, 
                    postid, 
                    imagesrc, 
                    anons, 
                    suggestions,
                    exists
                };
            }
        }

        function prepareLinkTemplate(e, is_term) {
            let item = fcl_getDataAttrs(e, is_term, false);

            if (item.exists && !cl_allow_multilink) {
                if (fcl_isTinyMCE()) {
                    selectExistingTinyMCE(item.url)
                }
                else {
                    selectExistingTextarea(item.url);
                }
            } else {
                is_term ? replaceSelectedTerm(item, e) : replaceSelectedLink(item, e);
            }
        }
    }

    /* ===================       ==================== */
    /* =================== Tabs ==================== */
    /* ===================       ==================== */

    $('.container-articles').show();
    $('.container-taxonomy').hide();

    $('div.tab').unbind().click(function () {
        if ($(this).hasClass('tab-articles')) {
            $('.container-articles').show();
            $('.container-taxonomy').hide();
            $('div.tab-articles').addClass('linkate-tab-selected');
            $('div.tab-taxonomy').removeClass('linkate-tab-selected');
            // $(cl_quick_cat_select).show();
        } else {
            $('.container-articles').hide();
            $('.container-taxonomy').show();
            $('div.tab-articles').removeClass('linkate-tab-selected');
            $('div.tab-taxonomy').addClass('linkate-tab-selected');
            // $(cl_quick_cat_select).hide();
        }
    });

    /* ===================       ==================== */
    /* =================== Utils ==================== */
    /* ===================       ==================== */

    function fcl_is_term() {
        return window.location.href.indexOf('term.php') > -1 ? 1 : 0;
    }

    function fcl_get_stemm(word) {
        word = word.replace('ё', 'е').toLowerCase();
        if (stem_cache[word])
            return stem_cache[word];
        snowball.setCurrent(word);
        snowball.stem();
        var stem = snowball.getCurrent();
        if (stem.length < 3) stem = word;
        stem_cache[word] = stem;
        return stem;
    }

    function fcl_hasClassExists(element) {
        let exists;
        if (element.classList.contains('linkate-link')) {
            exists = element.querySelector('.link-title').classList.contains(cl_exists_class);
        } else {
            exists = fcl_hasClassExists(element.parentElement);
        }
        return exists;
    }

    function fcl_getAttr(element, attr) {
        let val;
        if (element.classList.contains('linkate-link')) {
            val = element.getAttribute(attr);
        } else {
            val = fcl_getAttr(element.parentElement, attr);
        }
        return val;
    }

    function fcl_convertRelativeUrl(url) {
        let parser = document.createElement('a');
        parser.href = url;
        url = parser.href;
        return url;
    }

    function fcl_collectAllLinksFromContent(text) {
        let reg = /href=['"](.*?)['"]/gi;
        let match;
        let matches = [];
        let cnt = 0;

        while (match = reg.exec(text)) {
            match[1] = fcl_convertRelativeUrl(match[1]);
            if (!fcl_fileTypeChecker(match[1])) {
                cnt++;
                matches.push(match[1]);
            }
        }

        cl_urls_in_content = matches;
        return cnt;
    }

    function fcl_countLinksInContent(url) {
        url = fcl_convertRelativeUrl(url);

        if (cl_urls_in_content) {
            let cnt = 0;
            for (let i = 0; i < cl_urls_in_content.length; i++) {
                // count occurrences
                if (cl_urls_in_content[i] === url) {
                    cnt++;
                }
            }
            return cnt;
        }
        return 0;
    }

    // when open suggesions panel - check which ankors are currently in use to exclude them from suggestions
    function fcl_getAllAnkorsInUse(content) {
        let ankors = [];
        // get dom
        // get all links <a>
        var a_links = $('<div>' + content + '</div>').find('a');
        if (a_links.length > 0) {

            $.each(a_links, function (i, el) {
                if (!fcl_fileTypeChecker($(el).attr("href"))) {
                    let url = fcl_convertRelativeUrl($(el).attr("href"));
                    let ankor = $(el).text();
                    if (ankor.trim().length === 0) {
                        ankor = "_Без анкора_";
                    }
                    ankors.push({ ankor, url });
                }
            })
        }
        // console.log(ankors);
        return ankors;
    }

    function fcl_fileTypeChecker(url) { // cuz we don't want to count media as int/ext links
        if (url === undefined)
            return true;
        let prohibited = ['.jpg', '.jpeg', '.tiff', '.bmp', '.psd', '.png', '.gif', '.webp', '.doc', '.docx', '.xlsx', '.xls', '.odt', '.pdf', '.ods', '.odf', '.ppt', '.pptx', '.txt', '.rtf', '.mp3', '.mp4', '.wav', '.avi', '.ogg', '.zip', '.7z', '.tar', '.gz', '.rar'];

        for (let i = prohibited.length - 1; i >= 0; i--) {
            if (url.indexOf(prohibited[i]) != -1) {
                return true;
            }
        }
        // omit some navigational links, which starts with #
        if (url.indexOf('#') === 0) {
            return true;
        }
        return false;
    }

    function fcl_levenshtein_similar(a, b) {
        if (a.length === 0) return false;
        if (b.length === 0) return false;
        if (a === b) return true;
        let min_len = Math.min(a.length, b.length);
        if (min_len < 4) return false; // one of the words is too short
        let koef = min_len < 7 ? 1 : 2;
        if (Math.abs(a.length - b.length) > koef) return false; // too big diff in length
        let matrix = [];

        // increment along the first column of each row
        for (let i = 0; i <= b.length; i++) {
            matrix[i] = [i];
        }

        // increment each column in the first row
        for (let j = 0; j <= a.length; j++) {
            matrix[0][j] = j;
        }

        // Fill in the rest of the matrix
        for (i = 1; i <= b.length; i++) {
            for (j = 1; j <= a.length; j++) {
                if (b.charAt(i - 1) === a.charAt(j - 1)) {
                    matrix[i][j] = matrix[i - 1][j - 1]
                } else {
                    matrix[i][j] = Math.min(matrix[i - 1][j - 1] + 1, // substitution
                        Math.min(matrix[i][j - 1] + 1, // insertion
                            matrix[i - 1][j] + 1)) // deletion
                }
            }
        }
        let result = matrix[b.length][a.length];
        return result <= koef;
    }

    function fcl_getAllAnkorPositions(text, ankor, offset, pos) {
        if (pos == undefined) {
            pos = [];
            offset = 0;
        }
        offset = text.toLowerCase().indexOf(ankor, offset === 0 ? 0 : offset + ankor.length);
        if (offset > -1) {
            let dont_add = false;
            if (offset - 1 > 0) {
                dont_add = fcl_is_letter(text.charAt(offset - 1));
            }
            if (!dont_add && offset + ankor.length < text.length) {
                dont_add = fcl_is_letter(text.charAt(offset + ankor.length));
            }
            if (!dont_add)
                pos.push(offset);

            if (offset < text.length) {
                pos = fcl_getAllAnkorPositions(text, ankor, offset, pos);
            }
        }
        return pos;
    }

    // set lastfocus to something at the beginning, of #content wasn't found
    function fcl_initial_lastfocus_setup() {
        if (cl_editor_lastfocus == null || cl_editor_lastfocus === undefined) {
            if (tinymce.activeEditor)
                cl_editor_lastfocus = 'RICH_EDITOR';
            else {
                cl_editor_lastfocus = $('.wp-editor-area')[0];
            }
        }
    }

    // check if in header or in <a> or in img
    function fcl_inHeaderOrLink(pos) {
        let txt = cl_editor_lastfocus_html_content;
        for (var i = pos - 1; i >= 0; i--) {
            if (!fcl_is_letter(txt.charAt(i)) && !fcl_is_numeric(txt.charAt(i))) {
                if (txt.charAt(i) == "<") {
                    let close_bracket_pos = txt.indexOf(">", i);
                    let tag = txt.substring(i, close_bracket_pos + 1);
                    if (tag.search(/<h[1-6].*>/i) > -1) {
                        // we are probably in the header, but we have to be sure
                        // get whole tag <h*>...</h*>, and check if we are inside
                        // let whole_tag = txt.match(/<h[1-6].*<\/h[1-6]>/i)[0];
                        let next_close_tag_pos = txt.indexOf("</" + tag.substring(1, 3) + ">", close_bracket_pos);
                        if (pos > i && pos < next_close_tag_pos) {
                            return true;
                        }

                    }
                    if (tag.search(/<a.*>/i) > -1) {
                        // let whole_tag = txt.match(/<a.*<\/a>/i)[0];
                        let next_close_tag_pos = txt.indexOf("</a>", close_bracket_pos);
                        if (pos > i && pos < next_close_tag_pos) {
                            return true;
                        }
                    }
                    // this one is easier, it doesn't have a closing tag
                    if (tag.search(/<img.*>/i) > -1 && (pos > i && pos < close_bracket_pos)) {
                        return true;
                    }
                    // if we are here, than we have a suggestion
                    return false;
                }
            }
        }
        return false;
    }
    
    // some checkers
    function fcl_is_letter(c) {
        return c.toLowerCase() != c.toUpperCase();
    }
    function fcl_is_numeric(n) {
        return !isNaN(parseFloat(n)) && isFinite(n);
    }

    // extract plain text from html
    function fcl_strip(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        return doc.body.textContent || "";
    }

    // check for spaces before/after, move space out from link
    function fcl_tinymce_move_trailing_space(selection) {
        let arr = [];
        if (selection) {
            selection.charAt(0) === ' ' ? arr['first'] = ' ' : arr['first'] = ''
            selection.charAt(selection.length - 1) === ' ' ? arr['last'] = ' ' : arr['last'] = ''
            arr['hasSpaces'] = false;
            if (arr['first'] == " " || arr['last'] == " ") {
                arr['hasSpaces'] = true;
                arr['selection'] = selection.trim();
            }
            return arr;
        }
        arr['hasSpaces'] = false;
        return arr;
    }

    // to remove item from array
    function fcl_removePos(arr, item) {
        for (var i = arr.length; i--;) {
            if (arr[i] === item) {
                arr.splice(i, 1);
            }
        }
    }
    // to get text between positions
    function fcl_getAnkorText(start, end) {
        let txt = cl_editor_lastfocus_html_content;
        let st = start, en = end;
        txt = txt.substring(st, en);
        // add text pos percentage
        let relative_position = Math.round((start / cl_editor_lastfocus_html_content.length) * 100);
        return [st, en, txt, relative_position];
    }

    function fcl_isTinyMCE() {
        if (cl_editor_lastfocus == "RICH_EDITOR" || $(cl_editor_lastfocus).hasClass('tmce-active')) {
            return true;
        }
        if ($(cl_editor_textarea[0]).hasClass('wp-editor-area') && $('#wp-' + cl_editor_textarea[0].id + '-wrap').hasClass('tmce-active')) {
            return true;
        }
        return false;
    }

    function fcl_getEditorContent() {
        let content = "";
        let i = 0;

        while (cl_editor_textarea[i]) {
			try {
                if ($(cl_editor_textarea[i]).hasClass('wp-editor-area') && $('#wp-' + cl_editor_textarea[i].id + '-wrap').hasClass('tmce-active')) {
                    content += tinymce.get(cl_editor_textarea[i].id).getContent();
                } else {
                    content += cl_editor_textarea[i].value;
                }			
            } catch (err) {
                console.log('Unable to get editors content by id: ', cl_editor_textarea[i].id);
            }
            i++;
        }

        return content;
    }

    // Get html from last focused editor or default
    function fcl_suggestions_getEditorContent() {
        if (fcl_isTinyMCE()) {
            if (fcl_is_term() === 0 && tinymce.editors.content) {
                tinymce.editors.content.focus();
                return tinymce.editors.content.getContent();
            }
            else {
                tinymce.activeEditor.focus();
                return tinymce.activeEditor.getContent();
            }

        } else if (cl_editor_lastfocus && fcl_is_term() === 1) {
            cl_editor_lastfocus.focus();
            return cl_editor_lastfocus.value;
        } else {
            cl_editor_textarea[0].focus();
            return cl_editor_textarea[0].value;
        }
    }

    function fcl_find_suggestions_from_text(suggestions) {
        // result arrays for single and multi-word ankors
        let nearest_single = [];
        let nearest_multi = [];
        let nearest_positions = [];

        // get current article's content
        cl_editor_lastfocus_html_content = fcl_suggestions_getEditorContent();
        // remove everything but text
        let curr_content = fcl_strip(cl_editor_lastfocus_html_content).toLowerCase();
        let punctuationless = curr_content.replace(/[\.,-\/#!$%\^&\*;\":{}=\-_`~()@\+\?><\[\]\+]/g, ' ');
        let finalString = punctuationless.replace(/\n/g, " ").replace(/\s+/g, " ");

        let coincidence = [];
        let sug_arr = suggestions.trim().toLowerCase().split(' ');
        let text_arr = finalString.trim().split(' ');

        // Filter content words by length and stoplists
        text_arr = text_arr.filter(function (w) {
            return cl_whitelist.includes(w) || (w.length > cherrylink_options['term_length_limit'] && !cl_blacklist.includes(w));
        });

        // looking for ankors in text using stemming and levenstein
        for (let i = sug_arr.length - 1; i >= 0; i--) {
            let count = 0;
            let words = [];

            // Stemmer part
            for (let j = text_arr.length - 1; j >= 0; j--) {

                if (fcl_get_stemm(sug_arr[i]) === fcl_get_stemm(text_arr[j]) || fcl_levenshtein_similar(sug_arr[i], text_arr[j])) {
                    count++;
                    if (!words.includes(text_arr[j]))
                        words.push(text_arr[j]);
                }
            }
            // End stemmer

            // remove some duplicates
            let similar_coincidence_exists = false;
            for (let k = 0; k < coincidence.length; k++) {
                if (coincidence[k].count === count && JSON.stringify(coincidence[k].words) === JSON.stringify(words)) {
                    similar_coincidence_exists = true;
                    break;
                }
            }
            if (!similar_coincidence_exists)
                coincidence.push({ suggestion: sug_arr[i], count: count, words: words });
        }
        // sort: less used words in text will be first
        coincidence.sort(function (a, b) { return a.count - b.count });
        // not found? - remove
        coincidence = coincidence.filter(function (obj) {
            return obj.words.length > 0;
        });

        // Get positions of all ankors
        let pos_matrix = [];
        for (var i = 0; i < coincidence.length; i++) {
            let set_arr = [];
            let max_len = 0;
            for (var j = 0; j < coincidence[i].words.length; j++) {
                let word_pos = fcl_getAllAnkorPositions(cl_editor_lastfocus_html_content, coincidence[i].words[j], 0, []);
                if (word_pos.length !== 0) {
                    let this_len = coincidence[i].words[j].length;
                    if (this_len > max_len) max_len = this_len;
                    word_pos.forEach(function (el) {
                        let contains = false;
                        set_arr.forEach(function (sa) {
                            if (sa.pos === el) {
                                contains = true;
                            }
                        });
                        set_arr.push({ pos: el, len: this_len });
                    })
                }
            }
            pos_matrix.push(set_arr);
        }

        // get words, frases, all positions and text
        if (pos_matrix.length > 0) {
            for (var i = 0; i < pos_matrix.length; i++) {
                for (var j = 0; j < pos_matrix[i].length; j++) {
                    for (var k = 0; k < pos_matrix.length; k++) {
                        if (i !== k) {
                            for (var n = 0; n < pos_matrix[k].length; n++) {
                                let start, end, diff = 100, max_diff = 30;
                                let add = false;
                                if (pos_matrix[i][j].pos > pos_matrix[k][n].pos) {
                                    diff = pos_matrix[i][j].pos - (pos_matrix[k][n].pos + pos_matrix[k][n].len);
                                    if (diff < max_diff) {
                                        start = pos_matrix[k][n].pos;
                                        end = pos_matrix[i][j].pos + pos_matrix[i][j].len;
                                        add = true;
                                    }
                                } else {
                                    diff = pos_matrix[k][n].pos - (pos_matrix[i][j].pos + pos_matrix[i][j].len);
                                    if (diff < max_diff && diff > 0) {
                                        start = pos_matrix[i][j].pos;
                                        end = pos_matrix[k][n].pos + pos_matrix[k][n].len;
                                        add = true
                                    }
                                }
                                if (add) {
                                    let contains = false;
                                    nearest_positions.forEach(function (el) {
                                        if (el[0] === start && el[1] === end) {
                                            contains = true;
                                        }
                                    })
                                    if (!contains)
                                        nearest_positions.push([start, end]);
                                }
                            }
                        }
                        let contains = false;
                        nearest_single.forEach(function (el) {
                            if (el[0] === pos_matrix[i][j].pos && el[1] === pos_matrix[i][j].pos + pos_matrix[i][j].len) {
                                contains = true;
                            }
                        })
                        if (!contains && !fcl_inHeaderOrLink(pos_matrix[i][j].pos)) {
                            let el = fcl_getAnkorText(pos_matrix[i][j].pos, pos_matrix[i][j].pos + pos_matrix[i][j].len);
                            if (!cl_blacklist.includes(el[2].toLowerCase()))
                                nearest_single.push(el);
                        }
                    }
                }
            }

            // Remove suggestions if more complex ankor exists at the same spot
            let n_p = nearest_positions.slice();
            for (var i = 0; i < n_p.length; i++) {
                for (var j = 0; j < n_p.length; j++) {
                    if (n_p[i] !== n_p[j] && (n_p[i][0] >= n_p[j][0] && n_p[i][1] <= n_p[j][1])) {
                        fcl_removePos(nearest_positions, n_p[i])
                    }
                }
            }
            // see definition below
            nearest_multi = fcl_removeBadSuggestions(nearest_positions);
            // get text for frases
            // nearest_positions.forEach(function (el) {
            //     nearest_multi.push(fcl_getAnkorText(el[0], el[1]));
            // })
        }

        // hide singleword if set
        if (cherrylink_options['singleword_suggestions'] === "false")
            nearest_single = [];

        return {nearest_single, nearest_multi};
    }

    // Remove bad suggestions with puntuation or short ones (<=3) or in h1-6

    function fcl_removeBadSuggestions(nearest_positions) {
        nearest_positions = nearest_positions.map((v, i) => {
            let item = fcl_getAnkorText(v[0], v[1]);
            if (item[2].search(/[\.,\/#!$%\^&\*;\":{}=`~()@\+\?\n\[\]\+]/i) > -1 || item[2].search(/ - /) > -1 || item[2].length <= 3) {
                //console.log(item[2]);
                return false;
            }
            if (fcl_inHeaderOrLink(v[0])) {
                return false;
            }
            return item;
        });
        return nearest_positions.filter(x => x);
    }

    /* ===================       ==================== */
    /* =================== UI Utils ==================== */
    /* ===================       ==================== */


    function fcl_markIncomingLinks() {
        if (!incoming_links_array && !incoming_links_array.length) return;

        let inc_ids = incoming_links_array.map((el) => {
            return el['source_id'];
        }) 

        for (let i = 0; i < cl_list_links.length; i++) {
            const link = cl_list_links[i];
            if (link.classList.contains('linkate-link')) {
                let post_id = link.getAttribute('data-postid');

                let title = $(link).find('.link-title')[0]
                if (inc_ids.includes(post_id)) {
                    title.classList.add('incoming-link');
                } else {
                    title.classList.remove('incoming-link');

                }
            }
        }
    }

    // link counter styles
    function fcl_markNumber(el, count) {
        let num = el.parentElement.querySelector('.link-counter');
        let title = el.querySelector('.link-title');

        if (count > 0) {
            if (!title.classList.contains(cl_exists_class)) {
                title.classList.add(cl_exists_class);
            }
            num.innerText = count;
            if (count > 1) {
                num.classList.remove('link-counter-good');
                num.classList.add('link-counter-bad');
            } else {
                num.classList.remove('link-counter-bad');
                num.classList.add('link-counter-good');
            }
        } else {
            if (title.classList.contains(cl_exists_class)) {
                num.innerText = '0';
                title.classList.remove(cl_exists_class);
                num.classList.remove('link-counter-bad');
                num.classList.remove('link-counter-good');
            }
        }
    }

    // check if some links already exist
    function fcl_checkTextLinks(list) {
        let content = fcl_getEditorContent();
        let url;
        let curr_post_id;
        let limit = list.length;

        let hide_exist = $('#hide_that_exists').is(':checked');
        let show_exist = $('#show_that_exists').is(':checked');
        let filter_word = $('#filter_by_title').val().toLowerCase();

        let el, text, contains, hide;
        for (let i = limit - 1; i >= 0; i--) {
            el = list[i];
            if (el.classList.contains('linkate-link')) {
                url = el.getAttribute('data-url');
                curr_post_id = el.getAttribute('data-postid');
                if (cl_individual_stats && curr_post_id != null && cl_individual_stats.hasOwnProperty(JSON.stringify("id_" + curr_post_id))) {
                    el.parentElement.lastChild.children[0].innerText = cl_individual_stats[JSON.stringify("id_" + curr_post_id)][1];
                    el.parentElement.lastChild.children[1].innerText = cl_individual_stats[JSON.stringify("id_" + curr_post_id)][0];
                }
                // let count = fcl_getAllIndexes(content, url, 0, 0);
                fcl_collectAllLinksFromContent(content);
                let count = fcl_countLinksInContent(url);
                cl_total_links = cl_total_links + count; // вроде не используется
                fcl_markNumber(el, count);

                if (fcl_hideItem(el, hide_exist, show_exist, filter_word)) {
                    el.parentElement.classList.add('link-hidden');
                } else {
                    el.parentElement.classList.remove('link-hidden');
                }

                // check for related block
                if (cl_crb_metabox.length > 0 && !el.classList.contains('link-term')) { // if metabox exists (plugin activated)

                    let meta_links = $(cl_crb_metabox).val();
                    let inDaBox = crb_link_exist_in_metabox(meta_links, el.getAttribute('data-postid'));

                    if (inDaBox === false) {
                        // show add button
                        el.parentElement.querySelector(".link-add-to-block").classList.remove('btn-hidden');
                        el.parentElement.querySelector(".link-del-from-block").classList.add('btn-hidden');
                    } else {
                        // show delete button
                        el.parentElement.querySelector(".link-add-to-block").classList.add('btn-hidden');
                        el.parentElement.querySelector(".link-del-from-block").classList.remove('btn-hidden');
                    }
                }
            }
        }
        timeOutTotalCount(T_WAIT_TOTAL_LINKS, content);
    }

    function fcl_filterLinks(list) {
        let el;

        let hide_exist = $('#hide_that_exists').is(':checked');
        let show_exist = $('#show_that_exists').is(':checked');
        let filter_word = $('#filter_by_title').val().toLowerCase().replace(/Ё/g, 'Е').trim();

        for (let i = list.length - 1; i >= 0; i--) {
            el = list[i];
            if (el.classList.contains('linkate-link') && !el.classList.contains('linkate-terms-devider')) {
                if (fcl_hideItem(el, hide_exist, show_exist, filter_word)) {
                    el.parentElement.classList.add('link-hidden');
                } else {
                    el.parentElement.classList.remove('link-hidden');
                }
            }
        }
    }

    function fcl_hideItem(el, hide_exist, show_exist, filter_word) {
        let text, contains, hide, hide_not_exist, dont_show_cat;
        let cat_el = $("#quick_cat_filter option:selected")[0]; // selected item
        let parent = cat_el.parentElement;
        let childrenSize = parent.childElementCount;
        let cat = $(cat_el).val(); // selected val + sub val
        if (cat !== "0" && cat_el.index < childrenSize - 1) {
            let cat_class_ind = parseInt($(cat_el).attr("class").substr(11)); // selected class index
            let next_el = $(cat_el).next(); // next sibling
            while (parseInt($(next_el).attr("class").substr(11)) > cat_class_ind) { // while next element has greater sub_index - concat
                cat += "," + $(next_el).val();
                next_el = $(next_el).next();
            }
            cat = cat.split(",");
        }

        text = el.querySelector('.link-title').innerHTML.toUpperCase().replace(/Ё/g, 'Е');

        hide = !hide_exist && el.querySelector('.link-title').classList.contains(cl_exists_class); // if we checked hide cb and link exists in text

        // contains = text.indexOf(filter_word) !== -1; // if we are using quick filtering and found smth       
        contains = improvedFilterContains(el, filter_word);

        hide_not_exist = !show_exist && !el.querySelector('.link-title').classList.contains(cl_exists_class);

        let cat_found = false;
        if (!el.classList.contains("link-term")) {
            let link_cats = $(el).attr("data-category").split(", ");
            link_cats.forEach(function (item, index) {
                if (cat.includes(item)) {
                    cat_found = true;
                }
            });
        }

        dont_show_cat = !el.classList.contains("link-term") && cat !== "0" && !cat_found;

        return hide || !contains || hide_not_exist || dont_show_cat;
    }

    // Use title, seotitle, multiple words and stemming
    function improvedFilterContains(el, filter) {
        // combine all possible words from the link (titles + suggestion?)
        let h1 = $(el).attr("data-title")
        let seo = $(el).attr("data-titleseo")
        let sugg = $(el).attr("data-suggestions")

        let combine = h1;
        if (seo) combine += ' ' + seo;
        if (sugg) combine += ' ' + sugg;

        let combinedStemms = textToSetOfStemms(combine);
        let filterStemms = textToSetOfStemms(filter);

        let contains = true;
        filterStemms.forEach(stemm => {
            if (!combinedStemms.includes(stemm) && combinedStemms.join(' ').indexOf(stemm) === -1) {
                contains = false;
            }
        });
        return contains;
    }

    function textToSetOfStemms(text) {
        if (!text) return [];
        text = text.replaceAll(/[^A-ZА-Я0-9\s]/ig, '').replaceAll(/\s+/g, ' ').toLowerCase().trim().split(/\s/);
        text = [...new Set(text)]; // removes duplicates
        for (let i = 0; i < text.length; i++) {
            const word = text[i];
            text[i] = fcl_get_stemm(word);
        }
        text = [...new Set(text)]; // removes duplicates again
        return text;
    }

    function timeOutLinksChecker(delay) { // wait after input some time, if input repeats - null timer and wait again, then call func
        if (cl_timerCheck) {
            clearTimeout(cl_timerCheck);
        }
        cl_timerCheck = setTimeout(function () {
            cl_total_links = 0;
            fcl_checkTextLinks(cl_list_links);
            fcl_checkTextLinks(cl_list_terms);
        }, delay);
    }

    function timeOutLinksFilter(delay) { // wait after input some time, if input repeats - null timer and wait again, then call func
        if (cl_timerFilter) {
            clearTimeout(cl_timerFilter);
        }
        cl_timerFilter = setTimeout(function () {
            fcl_filterLinks(cl_list_links);
            fcl_filterLinks(cl_list_terms);
        }, delay);
    }

    function timeOutTotalCount(delay, content) { // to prevent second call of the function (it's called for links and terms separately)
        if (cl_timerTotalLinks) {
            clearTimeout(cl_timerTotalLinks);
        }
        cl_timerTotalLinks = setTimeout(function () {
            let ankors = fcl_getAllAnkorsInUse(content);
            let links_title = 'Ссылки в тексте [анкор:url]<ul>';

            for (var i = 0; i <= ankors.length - 1; i++) {
                links_title += '<li><span class=\'tooltip-ankor-text\'>' + ankors[i].ankor + '</span>: <span class=\'tooltip-url\'><a target="_blank" href="' + ankors[i].url + '">' + ankors[i].url + '</a></span></li>';
            }
            links_title += '</ul>';
            $("#links-count-total").html('<div class=\'cherry-adm-tooltip\'>' + ankors.length + '<div class=\'tooltiptext\'>' + links_title + '</div></div>');

        }, delay);
    }

    function fcl_setup_main_btns() {
        // set action to the media button to open cherrylink panel
        cl_open_button.click(function () {
            // Looking for links in text
            if (fcl_get_data_offset === 0) { // load only first time
                ajax_get_data_from_server();
                fcl_toggle_links_stats();
                //fcl_individual_links_stats();

                // Use_stemming in this case determines whether we use stoplists or no. 
                // Stemming for suggestions in js still applies, but not in php, if === false
                if (cherrylink_options["use_stemming"] === 'true') {
                    fcl_get_stop_lists();
                }
            }
            $('#linkate-box').toggleClass('hide-or-show');
        });
        $(".linkate-close-btn").click(function () {
            $('#linkate-box').toggleClass('hide-or-show');
        });
        // set action to the 'load more' button
        $('.load-more-text').click(function () {
            ajax_get_data_from_server();
        });
    }

    /* ===================       ==================== */
    /* =================== AJAX ==================== */
    /* ===================       ==================== */

    function ajax_get_data_from_server() {
        // here we get post or taxonomy id
        var this_id = cherrylink_options['post_id'].length === 0 || cherrylink_options['post_id'] == 0 ? window.location.href.match(/tag_ID=(\d+)\&/i)[1] : cherrylink_options['post_id'];
        var ajax_data = {
            'action': 'get_linkate_links',
            'post_id': this_id,
            'offset': fcl_get_data_offset,
            'is_term': fcl_is_term(),
        };
        // show loading css
        $('.lds-ellipsis').removeClass('lds-ellipsis-hide');
        $('.load-more-text').addClass('lds-ellipsis-hide');

        $.post(ajax_obj.ajaxurl, ajax_data, function (response) {
            // append if we have result, decide when to append 'not found' text
            if (response['count'] === 0) {
                if (fcl_get_data_offset === 0)
                    $('#linkate-links-list').append(response['links']);
            } else {
                $('#linkate-links-list').append(response['links']);
            }
            fcl_get_data_offset += response['count'];
            // hide 'load more' button if we have reached the end
            if (response['count'] < cherrylink_options['get_data_limit']) {
                $('.linkate-load-more').hide();
            } else {
                $('.linkate-load-more').show();
            }

            fcl_individual_links_stats(response['links']);

            // reload listeners and stuff
            reLoad_when_data_received();
        }).fail(function (errorObject, textStatus, errorThrown) {
            $('#linkate-links-list').append("<p>Произошла ошибка при загрузке ссылок, подробности для разработчика плагина ниже: </p><hr>")
            $('#linkate-links-list').append(errorObject.responseText)
            console.log(errorObject);
            
        }).always(function () {
            // hide loading css
            $('.lds-ellipsis').addClass('lds-ellipsis-hide');
            $('.load-more-text').removeClass('lds-ellipsis-hide');
        });

    }
    // Incoming links stats
    function fcl_toggle_links_stats() {

        if (cherrylink_options['linkate_scheme_exists']) {
            let this_id = cherrylink_options['post_id'].length === 0 || cherrylink_options['post_id'] == 0 ? window.location.href.match(/tag_ID=(\d+)\&/i)[1] : cherrylink_options['post_id'];

            let ajax_data = {
                'action': 'linkate_generate_json',
                'this_id': this_id,
                'this_type': cherrylink_options['post_id'].length === 0 || cherrylink_options['post_id'] == 0 ? 'term' : 'post'
            };

            $.post(ajax_obj.ajaxurl, ajax_data, function (response) {
                let resp = JSON.parse(response); // All done!
                // console.log(resp['count']);
                incoming_links_array = resp['links'];
                let links_title = 'Входящие ссылки [анкор:url]<ul>';
                for (var i = resp['links'].length - 1; i >= 0; i--) {
                    links_title += '<li><span class=\'tooltip-ankor-text\'>' + resp['links'][i]['ankor'] + '</span>: <span class=\'tooltip-url\'><a target="_blank" href="' + resp['links'][i]['source_url'] + '">' + resp['links'][i]['source_url'] + '</a></span></li>';
                }
                links_title += '</ul>';
                $('#links-count-targets').html('<div class=\'cherry-adm-tooltip\'>' + resp['count'] + '<div class=\'tooltiptext\'>' + links_title + '</div></div>');

                fcl_markIncomingLinks();
            });
        } else {
            let host = window.location.href.replace(window.location.href.slice(window.location.href.indexOf('/wp-admin/') + 10), 'options-general.php?page=linkate-posts');
            $('#links-count-targets').html('<a style="font-weight:bold;color:white;" href="' + host + '" title="Нет данных, перейдите по ссылки для настройки">??</a>');
        }

    }

    // links stats in/out
    function fcl_individual_links_stats(links) {
        if (cherrylink_options['linkate_scheme_exists']) {
            links = links.replace(/\"/g, "'");
            let results = links.matchAll(/data-postid='(\d+)'/g);
            results = Array.from(results);
            let ids = [];
            results.forEach((el) => {
                ids.push(el[1])
            });
            ids = ids.join(",");

            let ajax_data = {
                'action': 'linkate_generate_csv_or_json_prettyfied',
                'from_editor': true,
                'post_ids': ids
            };
            $.ajax({
                type: "POST",
                url: ajax_obj.ajaxurl,
                data: ajax_data,
                datatype: 'json',
                success: function (response) {
                    if (cl_individual_stats === false)
                        cl_individual_stats = response;
                    else {
                        cl_individual_stats = $.extend({}, cl_individual_stats, response);
                    }

                    reLoad_when_data_received();
                }
            });
        } else {
            console.log("Таблица схемы не создана")
        }
    }

    // Stop words
    function fcl_get_stop_lists() {
        let ajax_data = {
            'action': 'linkate_get_whitelist'
        };
        $.ajax({
            type: "POST",
            url: ajax_obj.ajaxurl,
            data: ajax_data,
            datatype: 'json',
            success: function (response) {
                cl_whitelist = response;
            }
        });
        ajax_data.action = 'linkate_get_blacklist';
        $.ajax({
            type: "POST",
            url: ajax_obj.ajaxurl,
            data: ajax_data,
            datatype: 'json',
            success: function (response) {
                cl_blacklist = response;
            }
        });
    }

    /* ===================       ==================== */
    /* =================== Init ==================== */
    /* ===================       ==================== */

    function fcl_classic_init() {
        // set listeners to open/close/load data...
        fcl_setup_main_btns();

        // determine which editor is active
        fcl_initial_lastfocus_setup(); 

        // initial load of listeners and stuff
        reLoad_when_data_received();
    }

    fcl_classic_init();
});

