jQuery(document).on( 'click', '.borgun-rpg-notice .notice-dismiss', function() {
    jQuery.ajax({
        url: ajaxurl,
        data: {
            action: 'borgun_rpg_notice_dismiss'
        }
    })

})