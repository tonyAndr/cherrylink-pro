jQuery(document).ready(function($){
    // let loaded_css = $('link#crb-template-css').attr('href');
    // let chosen_css = $('#crb_choose_template').val();
    // if (loaded_css && !loaded_css.includes(chosen_css) && !loaded_css.includes(chosen_css.replace('.css', '-important.css')))
    //     location.reload();

    if (window.location.href.includes('page=linkate-posts&subpage=output_block') && $('div.crb-update').length > 0)
        location.reload();
});