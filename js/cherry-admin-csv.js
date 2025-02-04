jQuery(document).ready(function ($) {

	/*
	--- === STATS === ---
	*/
    let stats_interval_check, stats_serialized_form;
    let stats_admin_preview = false, stats_object = {};
    let stats_offset = 0, stats_limit = 300, stats_posts_count = 0, in_progress = false;
    let process_type = 'csv'; // csv or preview

    $('#generate_csv').on("click", function (e) {
        e.preventDefault();
        console.time('overall_time');
        $('#csv_progress').show();
        $('#generate_csv').hide();
        stats_admin_preview = false;
        stats_offset = 0
        stats_posts_count = 0
        stats_object = {}
        stats_serialized_form = $("#form_generate_csv").serialize();
        $("input").prop('disabled', true);
        process_type = 'csv'
        stats_get_posts_count();
    })

    $('#generate_preview').on("click", function (e) {
        e.preventDefault();
        console.time('overall_time');
        $('#link_check_progress').show();
        $('#generate_preview').hide();
        stats_admin_preview = true;
        stats_offset = 0
        stats_posts_count = 0
        stats_object = {}
        stats_serialized_form = $("#form_generate_stats").serialize() + "&admin_preview_stats=true";
        $("input").prop('disabled', true);
        process_type = 'preview'
        stats_get_posts_count();
    })

    $('#links_direction').on("change", function () {
        $("#links_direction_outgoing").toggle()
        $("#links_direction_incoming").toggle()
    })

    // Get posts count to know how many requests we have to make
    function stats_get_posts_count() {
        let ajax_data = stats_serialized_form + '&action=linkate_get_all_posts_count';

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: ajax_data,
            datatype: 'text',
            success: function (response) {
                // response = JSON.parse(response);
                console.log("Starting process with " + response + " posts found");
                stats_posts_count = parseInt(response);
                // update stats_posts_count
                stats_interval_check = setInterval(stats_process_next, 500);
            },
            error: function (jqXHR, textStatus, errorThrown ) {
                handle_errors(errorThrown, jqXHR.responseText);
            }
        });
    }

    // Process next batch of posts
    function stats_process_next() {
        if (stats_offset >= stats_posts_count) {
            console.timeEnd('overall_time');
            clearInterval(stats_interval_check);

            $('#csv_progress').hide();
            $('#link_check_progress').hide();
            $("input").prop('disabled', false);
            console.log("Stats created successfully")
            if (!stats_admin_preview) {
                stats_get_file();
            } else {
                console.log("Resulting object")
                console.log(stats_object)
                create_preview_html();
            }
            return;
        }

        if (in_progress)
            return;

        let direction = $('#links_direction option:selected').val();
        let ajax_action = '';
        if (direction == "incoming")
            ajax_action = "linkate_generate_csv_or_json_prettyfied_backwards";
        else
            ajax_action = "linkate_generate_csv_or_json_prettyfied";

        let ajax_data = stats_serialized_form
            + '&action=' + ajax_action
            + '&stats_offset=' + stats_offset
            + '&stats_limit=' + stats_limit;

        in_progress = true;
        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: ajax_data,
            datatype: 'json',
            success: function (response) {
                console.log(JSON.parse(response));
                stats_object = Object.assign(stats_object, JSON.parse(response));
                stats_offset += stats_limit;
                in_progress = false;
                stats_update_progress();
            },
            error: function (jqXHR, textStatus, errorThrown ) {
                handle_errors(errorThrown, jqXHR.responseText);
            }
        });
    }

    // Display CSV creation progress
    function stats_update_progress() {
        let current = 0;
        current = Math.round(stats_offset / stats_posts_count * 100)
        if (process_type === 'preview') {

            $('#link_check_progress').prop('max', 100);
            $('#link_check_progress').val(current);
        } else {
            $('#csv_progress').prop('max', 100);
            $('#csv_progress').val(current);
            
        }
    }

    // Create results file
    function stats_get_file() {
        let ajax_data = 'action=linkate_merge_csv_files';

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: ajax_data,
            datatype: 'json',
            success: function (response) {
                response = JSON.parse(response);
                console.log(response);
                // $('#generate_csv').after('<a id="btn_csv_dload" class="button button-download" href="' + response['url'] + '" download>Скачать файл</a>');
                location.href= response['url'];
            },
            error: function (jqXHR, textStatus, errorThrown ) {
                handle_errors(errorThrown, jqXHR.responseText);
            }
        });
    }

    // Stats preview

    function create_preview_summary (posts_obj) {
        let output = '';
        let no_inc = posts_obj.no_incoming.length;
        let no_out = posts_obj.no_outgoing.length;
        let has_rep = posts_obj.has_repeats.length;
        let has_404 = posts_obj.has_404.length;
        let has_rec = posts_obj.has_recursion.length;
        let links_count = $("#cherry_preview_stats_summary").attr('data-linkscount');
        output += '<p>Было проверено <strong>' + stats_posts_count + '</strong> записей. Всего ссылок найдено: <strong>' + links_count + '</strong>.</p>';
        output += '<ol class="list-decimal list-inside">';
        output += '<li>Записи с повторами ссылок: <strong>' + (has_rep > 0 ? has_rep : 'Не обнаружены') + '</strong></li>';
        output += '<li>Записи без входящих ссылок: <strong>' + (no_inc > 0 ? no_inc : 'Не обнаружены') + '</strong></li>';
        output += '<li>Записи без исходящих ссылок: <strong>' + (no_out > 0 ? no_out : 'Не обнаружены') + '</strong></li>';
        output += '<li>Записи cо сломанными ссылками: <strong>' + (has_404 > 0 ? has_404 : 'Не обнаружены') + '</strong></li>';
        output += '<li>Записи ссылающиеся на себя: <strong>' + (has_rec > 0 ? has_rec : 'Не обнаружены') + '</strong></li>';
        output += '</ol>';
        if (no_inc > 0 || no_out > 0 || has_rep > 0 || has_404 > 0 || has_rec > 0) {
        } else if (parseInt(links_count) === 0) {
            output += '<p>Перелинковка не обнаружена.</p>';
        } else {
            output += '<p>Проблем с текущей перелинковкой не найдено. </p>';
        }
        $("#cherry_preview_stats_summary").html(output);
    }

    function create_preview_html () {
        let output = "";
        let posts = {
            no_incoming: [],
            no_outgoing: [],
            has_repeats: [],
            has_recursion: [],
            has_404: []
        }

        for (const key in stats_object) {
            if (stats_object.hasOwnProperty(key)) {
                const element = stats_object[key];
                if (element.has_repeats) {
                    posts.has_repeats.push(Object.assign({id: key}, element))
                }
                if (!element.has_outgoing) {
                    posts.no_outgoing.push(Object.assign({id: key}, element))
                }
                if (!element.has_incoming) {
                    posts.no_incoming.push(Object.assign({id: key}, element))
                }
                if (Object.values(element.err_404).length > 0) {
                    posts.has_404.push(Object.assign({id: key}, element))
                }
                if (element.recursion.length > 0) {
                    posts.has_recursion.push(Object.assign({id: key}, element))
                }
            }
        }

        create_preview_summary(posts);

        let table_limit = 150;
        let open_spoiler_id = false;
        let out_repeats = '';
        if (posts.has_repeats.length > 0) {
            out_repeats = posts.has_repeats.map((v, k) => {
                let repeats = '';
                for (const target in v.targets) {
                    if (v.targets.hasOwnProperty(target)) {
                        const count = v.targets[target];
                        repeats += "<li><a href=\""+target+"\" target=\"_blank\">" + target + "</a> <strong>("+count+")</strong>";
                    }
                }
                repeats = "<ol>" + repeats + "</ol>";
                return `<tr><td>${v.id}</td><td><a href="${v.url}" target="_blank">${v.url}</a></td><td>${repeats}</td><td><a href="/wp-admin/post.php?post=${v.id}&action=edit" target="_blank">В редактор</a></td></tr>`;
            }).join('\n');
            $("#label_spoiler_has_repeats").html("Найдены повторы ("+posts.has_repeats.length+")");
            $("div.spoiler_has_repeats").html("<table class='cherry-stats-preview-table'><thead><tr><th>Post ID</th><th>URL</th><th>Ссылается на (количество)</th><th>Действия</th></tr></thead><tbody>" + out_repeats + "</tbody></table>")
            open_spoiler_id = "#label_spoiler_has_repeats";
        } else {
            $("#label_spoiler_has_repeats").hide();
            $("div.spoiler_has_repeats").hide();
        }
        
        let out_incoming = '';
        if (posts.no_incoming.length > 0) {
            out_incoming = posts.no_incoming.slice(0,table_limit).map((v, k) => {
                return `<tr><td>${v.id}</td><td><a href="${v.url}" target="_blank">${v.url}</a></td><td><a href="/wp-admin/post.php?post=${v.id}&action=edit" target="_blank">В редактор</a></td></tr>`;
            }).join('\n');
            $("#label_spoiler_no_incoming").html("Статьи без входящих ссылок ("+posts.no_incoming.length+")");
            out_incoming = "<table class='cherry-stats-preview-table'><thead><tr><th>Post ID</th><th>URL</th><th>Действия</th></tr></thead><tbody>" + out_incoming + "</tbody></table>";
            if (posts.no_incoming.length > table_limit) {
                out_incoming += "<p>Показано только 50 первых результатов.</p>";
            }
            $("div.spoiler_no_incoming").html(out_incoming);
            if (!open_spoiler_id) open_spoiler_id = "#label_spoiler_no_incoming";
        } else {
            $("#label_spoiler_no_incoming").hide();
            $("div.spoiler_no_incoming").html("Повторы ссылок не обнаружены")
        }
        let out_outgoing = '';
        if (posts.no_outgoing.length > 0) {
            out_outgoing = posts.no_outgoing.slice(0,table_limit).map((v, k) => {
                return `<tr><td>${v.id}</td><td><a href="${v.url}" target="_blank">${v.url}</a></td><td><a href="/wp-admin/post.php?post=${v.id}&action=edit" target="_blank">В редактор</a></td></tr>`;
            }).join('\n');
            $("#label_spoiler_no_outgoing").html("Статьи, которые никуда не ссылаются ("+posts.no_outgoing.length+")");
            out_outgoing = "<table class='cherry-stats-preview-table'><thead><tr><th>Post ID</th><th>URL</th><th>Действия</th></tr></thead><tbody>" + out_outgoing + "</tbody></table>";
            if (posts.no_outgoing.length > table_limit) {
                out_outgoing += "<p>Показано только 50 первых результатов.</p>";
            }
            $("div.spoiler_no_outgoing").html(out_outgoing);
            if (!open_spoiler_id) open_spoiler_id = "#label_spoiler_no_outgoing";
        } else {
            $("#label_spoiler_no_outgoing").hide();
            $("div.spoiler_no_outgoing").hide();
        }
        let out_404 = '';
        if (posts.has_404.length > 0) {
            out_404 = posts.has_404.slice(0,table_limit).map((v, k) => {
                let bad_urls = '';
                for (const bu in v.err_404) {
                    if (v.err_404.hasOwnProperty(bu)) {
                        const ankor = v.err_404[bu];
                        bad_urls += "<li><a href=\""+bu+"\" target=\"_blank\">" + bu + "</a> <strong>("+ankor+")</strong>";
                    }
                }
                bad_urls = "<ol>" + bad_urls + "</ol>";
                return `<tr><td>${v.id}</td><td><a href="${v.url}" target="_blank">${v.url}</a></td><td>${bad_urls}</td><td><a href="/wp-admin/post.php?post=${v.id}&action=edit" target="_blank">В редактор</a></td></tr>`;
            }).join('\n');
            $("#label_spoiler_has_404").html("Статьи cо сломанными ссылками ("+posts.has_404.length+")");
            out_404 = "<table class='cherry-stats-preview-table'><thead><tr><th>Post ID</th><th>URL</th><th>Нерабочие ссылки (анкор)</th><th>Действия</th></tr></thead><tbody>" + out_404 + "</tbody></table>";
            if (posts.has_404.length > table_limit) {
                out_404 += "<p>Показано только 50 первых результатов.</p>";
            }
            $("div.spoiler_has_404").html(out_404);
            if (!open_spoiler_id) open_spoiler_id = "#label_spoiler_has_404";
        } else {
            $("#label_spoiler_has_404").hide();
            $("div.spoiler_has_404").hide();
        }

        let out_recursion = '';
        if (posts.has_recursion.length > 0) {
            out_recursion = posts.has_recursion.slice(0,table_limit).map((v, k) => {
                let bad_urls = '';
                for (const ankor of v.recursion) {
                        bad_urls += "<li><strong>"+ankor+"</strong>";
                }
                bad_urls = "<ol>" + bad_urls + "</ol>";
                return `<tr><td>${v.id}</td><td><a href="${v.url}" target="_blank">${v.url}</a></td><td>${bad_urls}</td><td><a href="/wp-admin/post.php?post=${v.id}&action=edit" target="_blank">В редактор</a></td></tr>`;
            }).join('\n');
            $("#label_spoiler_has_recursion").html("Статьи, которые ссылаются на себя ("+posts.has_recursion.length+")");
            out_recursion = "<table class='cherry-stats-preview-table'><thead><tr><th>Post ID</th><th>URL</th><th>Анкор</th><th>Действия</th></tr></thead><tbody>" + out_recursion + "</tbody></table>";
            if (posts.has_recursion.length > table_limit) {
                out_recursion += "<p>Показано только 50 первых результатов.</p>";
            }
            $("div.spoiler_has_recursion").html(out_recursion);
            if (!open_spoiler_id) open_spoiler_id = "#label_spoiler_has_recursion";
        } else {
            $("#label_spoiler_has_recursion").hide();
            $("div.spoiler_has_recursion").hide();
        }

        $("#form_generate_stats").hide();
        $("#cherry_preview_stats_container").show();
        $(open_spoiler_id).trigger("click");
    }

    function handle_errors (error_msg, error_details) {
        console.log(error_msg);
        $('#csv_progress').hide();
        $('#link_check_progress').hide();
        $("input").prop('disabled', false);
        if (process_type === 'preview') {
            $('#link_check_progress').parent().append("<p>Что-то пошло не так, возникла ошибка при обработке запроса: <strong>" + error_msg +"</strong>.</p>" + error_details);
        } else {
            $('#csv_progress').parent().append("<p>Что-то пошло не так, возникла ошибка при обработке запроса: <strong>" + error_msg +"</strong>.</p>" + error_details);
        }
    }
});