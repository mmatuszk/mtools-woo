jQuery(document).ready(function($) {
    $('#normalize-title-button').on('click', function() {
        let product_id = $('input#post_ID').val(); // Get the product ID
        console.log(product_id);
        $.ajax({
            type: 'POST',
            url: normalizeTitleParams.ajax_url,
            data: {
                action: 'mtoolswoo_normalize_title',
                nonce: normalizeTitleParams.nonce,
                product_id: product_id
            },
            success: function(response) {
                if (response.success) {
                    console.log(response.data);
                    $('#title').val(response.data);
                } else {
                    alert('Failed to normalize product name.');
                }
            }
        });
    });
});