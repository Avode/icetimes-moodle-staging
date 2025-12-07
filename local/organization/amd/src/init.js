
define(['jquery'], function($) {
    return {
        init: function() {
            $('#orgtable').DataTable({
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                pageLength: 25
            });
        }
    };
});
