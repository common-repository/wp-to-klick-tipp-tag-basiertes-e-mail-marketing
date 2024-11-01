jQuery(document).ready(function($) {
    function createBackground() {

        console.log('stargin bg');
        
        var data = {
            'action': 'wptkp_init_bg',
            'security': wptkp_init_bg.ajax_nonce
        };
            
        $.ajax({
            url : wptkp_init_bg.ajax_url,
            type : 'post',
            data : data,
            success : function( response ) {
                createBackground();                
            },
            error : function( ) {
                createBackground();
            },
        });
    }

    createBackground();
});