jQuery(document).ready(function($){

	/*
	--- === General Admin Hooks === ---
	*/

	// todo - do smth with them later
	// default templates
	var link_alt_temp = "<div style=\"padding:10px;margin:10px;border-top:1px solid lightgrey;border-bottom:1px solid lightgrey;\"><span style=\"color:lightgrey;font-size:smaller;\">Читайте также</span><div style=\"position:relative;max-width: 660px;margin: 0 auto;padding: 0 20px 20px 20px;display:flex;flex-wrap: wrap;\"><div style=\"width: 35%; min-width: 180px; height: auto; box-sizing: border-box;padding-right: 5%;\"><img src=\"{imagesrc}\" style=\"width:100%;\"></div><div style=\"width: 60%; min-width: 180px; height: auto; box-sizing: border-box;\"><strong>{title}</strong><br>{anons}</div><a target=\"_blank\" href=\"{url}\"><span style=\"position:absolute;width:100%;height:100%;top:0;left: 0;z-index: 1;\">&nbsp;</span></a></div></div>";
	var term_alt_temp = "<div style=\"padding:10px;margin:10px;border-top:1px solid lightgrey;border-bottom:1px solid lightgrey;\">Больше интересной информации по данной теме вы найдете в разделе нашего сайта \"<a href=\"{url}\"><strong>{title}</strong></a>\".</div>";

	var def_temp_before = "<a href=\"{url}\" title=\"{title}\">";
	var def_temp_after = "</a>";

	$("#restore_templates").on("click", function (e) {
		e.preventDefault();
		$("#link_before").val(def_temp_before);
		$("#link_after").val(def_temp_after);
		$("#term_before").val(def_temp_before);
		$("#term_after").val(def_temp_after);
		$("#link_temp_alt").val(link_alt_temp);
		$("#term_temp_alt").val(term_alt_temp);

		alert("Шаблоны восстановлены, не забудьте сохранить настройки")
    });

	/*
	--- === SCHEME === ---
	*/

	// Show form and checkboxes if scheme exists
	// scheme variable comes from main file wp_localize_script
	if (scheme.state) {
		$('#form_generate_csv').css('display', 'block');
	} else {
		$('#form_generate_csv').css('display', 'none');
	}

	$('input[type="checkbox"]').on("change", function () {
		$('#btn_csv_dload').remove();
		$('#generate_csv').show();
	})


    /*
	--- === DB COLLATION UPDATE === ---
	*/

    function update_collations() {
        $("#update_collation_btn").hide();
        $("#update_collation_btn").after("Обновляем, подождите...")
        $.ajax({
            type: "GET",
            url: ajaxurl + "?action=update_collation",
            datatype: 'json',
            success: function (response) {
                console.log(response)
                if (!response["result"]) {
                    $(".plugin-update-warning").html("<p>Произошла ошибка при обновлении: " + response["error"] + "</p>");
                } else {
                    $(".plugin-update-warning").html("<p>Обновление прошло успешно.</p>")
                }
            }
        });
    }
	
    // $.ajax({
    //     type: "GET",
    //     url: ajaxurl + "?action=check_collation",
    //     datatype: 'json',
    //     success: function (response) {
    //         let result = JSON.parse(response);
    //         // console.log(result)
    //         if (!result) {
    //             $(".plugin-update-warning").html("<p>Рекоммендуется обновить таблицы плагина в БД для полной поддержки символов и эмодзи Unicode. Обновить сейчас?</p><p><button id='update_collation_btn' class='button button-secondary'>Обновить таблицы</button></p>");
    //             $("#update_collation_btn").click(function (e) {
    //                 update_collations();
    //             })
    //             $(".plugin-update-warning").show();
    //         }
    //     }
    // });

});