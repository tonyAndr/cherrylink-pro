jQuery(document).ready(function ($) {

    // Prevent accidental removing stopwords by pressing Enter
    $(document).on("keydown", ":input:not(textarea)", function (event) {
        return event.key != "Enter";
    });

    // ========================= STOPWORDS TABLE =========================
    //create Tabulator on DOM element with id "example-table"
    if ($("#example-table").length) {
        var table = new Tabulator("#example-table", {
            ajaxURL: ajaxurl,
            ajaxParams: { action: "linkate_get_stopwords" }, //ajax parameters
            ajaxConfig: "POST", //ajax HTTP request type
            ajaxResponse:function(url, params, response){
                //url - the URL of the request
                //params - the parameters passed with the request
                //response - the JSON object returned in the body of the response.
        
                return response; //return the tableData property of a response json object
            },
            pagination: "local",
            paginationSize: 10,
            addRowPos: "top",          //when adding a new row, add it to the top of the table
            history: true,
            initialSort: [
                { column: "ID", dir: "desc" }, //sort by this first
            ],
            columnHeaderSortMulti: true,
            responsiveLayout: "collapse",
            paginationSizeSelector: [10, 25, 100, 250, 1000],
            layout: "fitColumns", //fit columns to width of table (optional)
            langs: {
                "ru-ru": {
                    "ajax": {
                        "loading": "Загрузка", //ajax loader text
                        "error": "Ошибка", //ajax error text
                    },
                    "pagination": {
                        "page_size": "Кол-во строк", //label for the page size select element
                        "first": "Первая", //text for the first page button
                        "first_title": "Первая", //tooltip text for the first page button
                        "last": "Последняя",
                        "last_title": "Последняя",
                        "prev": "Назад",
                        "prev_title": "Назад",
                        "next": "Вперед",
                        "next_title": "Вперед",
                    },
                    "headerFilters": {
                        "default": "фильтр...", //default header filter placeholder text
                    }
                }
            },
            columns: [
                { title: '#', formatter: "rownum", width: '5%', headerSort: false },
                { title: 'db_id', field: "ID", width: '5%', headerSort: false, visible: false },

                { title: "Слово", field: "word", widthGrow: 2, headerFilter: "input" },
                { title: "Корень", field: "stemm", widthGrow: 2, headerFilter: "input" , visible: false },
                {
                    title: "Источник", field: "is_custom", widthGrow: 1, headerFilter: 'select', headerFilterParams: { values: { "": "Все", 0: "Стандарт", 1: "Произвольное" } }, formatter: function (cell, formatterParams) {
                        var value = cell.getValue();
                        if (value > 0) {
                            return "Произв.";
                        } else {
                            return "Станд.";
                        }
                    }
                },
                {
                    title: "Список", field: "is_white", editor: "select", widthGrow: 1, editorParams: { values: { 0: "Черный список", 1: "Белый список" } }, headerFilter: true, headerFilterParams: { values: { "": "Все", 0: "Черный список", 1: "Белый список" } }, formatter: function (cell, formatterParams) {
                        var value = cell.getValue();
                        if (value > 0) {
                            return "Бел. Сп.";
                        } else {
                            return "Чер. Сп.";
                        }
                    }, cellEdited: function (cell) {
                        //cell - cell component
                        let ajax_data = {
                            id: cell.getRow().getData().ID,
                            action: 'linkate_update_stopword',
                            is_white: cell.getValue()
                        };
                        $.ajax({
                            type: "POST",
                            url: ajaxurl,
                            data: ajax_data,
                            datatype: 'json',
                            success: function (response) {
                                //table.setData();
                            }
                        });
                    },
                },
                {
                    title: '', width: '3%', headerSort: false, formatter: "buttonCross", cellClick: function (e, cell) {

                        let ajax_data = {
                            id: cell.getRow().getData().ID,
                            action: 'linkate_delete_stopword'
                        };
                        cell.getRow().delete();
                        $.ajax({
                            type: "POST",
                            url: ajaxurl,
                            data: ajax_data,
                            datatype: 'json',
                            success: function (response) {
                                //table.setData();

                            }
                        });

                    },
                }
            ]
        });

        table.setLocale('ru-ru');

        $("#stopwords-add").click(function (event) {
            event.preventDefault();
            if ($("#custom_stopwords").val().trim().length === 0) {
                alert("Поле пустое!")
            } else {
                let words = $("#custom_stopwords").val().trim().replace("\r", "").split("\n");
                let ajax_data = {
                    words: words,
                    action: 'linkate_add_stopwords',
                    is_white: $("#is_white").is(':checked') ? 1 : 0
                };
                $.ajax({
                    type: "POST",
                    url: ajaxurl,
                    data: ajax_data,
                    datatype: 'json',
                    success: function (response) {
                        table.setData();
                    }
                });
            }
        })
        $("#stopwords-defaults").click(function (event) {
            event.preventDefault();
            let conf = confirm("Это действие удалит все стоп-слова из таблицы и вернет стандартные. Хотите продолжить?");
            if (!conf)
                return false;
            let ajax_data = {
                action: 'fill_stopwords',
                restore_ajax: 'yes'
            };
            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: ajax_data,
                datatype: 'json',
                success: function (response) {
                    table.setData();
                }
            });
        })
        $("#stopwords-remove-all").click(function (event) {
            event.preventDefault();
            let conf = confirm("Вы точно хотите удалить все стоп слова?");
            if (!conf)
                return false;
            let ajax_data = {
                action: 'linkate_delete_stopword',
                all: 1
            };
            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: ajax_data,
                datatype: 'json',
                success: function (response) {
                    table.setData();
                }
            });
        })

    }
});