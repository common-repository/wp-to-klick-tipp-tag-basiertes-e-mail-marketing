jQuery(document).ready(function() {
    
    // show activity indicator
    jQuery('.show-activity-indicator').click(function() {
        jQuery(this).parent().find('img').show();
    });
});