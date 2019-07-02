define(['jquery'], function($) {

    return {
        init: function() {

            $(document).on('change', '#groupfilter', function() {
                var group = $(this).val();
                $('.group_' + group).show();
                $('.group_0').not('.group_' + group).hide();
            });
        }
    };
});
