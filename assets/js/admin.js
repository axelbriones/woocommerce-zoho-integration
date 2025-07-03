(function($) {
    'use strict';
    $(document).ready(function() {
        if (typeof wzi_admin !== 'undefined') {
            console.log('WZI Admin JS loaded successfully.');
            console.log('WZI Admin AJAX URL:', wzi_admin.ajax_url);
            console.log('WZI Admin Nonce:', wzi_admin.nonce);
        } else {
            console.error('WZI Admin JS: wzi_admin object is NOT defined!');
        }
    });
})(jQuery);