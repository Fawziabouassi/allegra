jQuery(document).ready(function($) {
    $('#allegra-commerce-sync-products').on('click', function(e) {
        e.preventDefault();

        var data = {
            'action': 'allegra_commerce_sync_products',
            'nonce': allegra_commerce_vars.nonce
        };

        $.post(allegra_commerce_vars.ajax_url, data, function(response) {
            $('#allegra-commerce-sync-result').html(response);
        });
    });
});