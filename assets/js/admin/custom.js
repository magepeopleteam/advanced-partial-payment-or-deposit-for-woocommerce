(function($) {
    $(document).ready(function() {
        if ($('#mepp_storewide_deposit_enabled').val() === 'no') {
            const parentTr = $('#mepp_storewide_deposit_enabled').closest('tr');
            parentTr.siblings('tr').hide();
        }

        $('#mepp_storewide_deposit_enabled').on('change', function() {
            const parentTr = $(this).closest('tr');
            if ($(this).val() === 'yes') {
                parentTr.siblings('tr').show();
            } else {
                parentTr.siblings('tr').hide();
            }
        });
    });
})(jQuery);
