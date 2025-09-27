jQuery(document).ready(function($) {
    // Timeline Modal Logic
    var modal = $('#timeline-modal');
    var modalContent = $('#timeline-modal-content');
    var closeModal = $('#timeline-modal-close');

    // Open modal
    $('.wp-list-table').on('click', '.view-timeline', function(e) {
        e.preventDefault();

        var requestId = $(this).data('id');
        modalContent.html('<p>Loading...</p>');
        modal.show();

        $.post(boilerAdmin.ajax_url, {
            action: 'get_request_timeline',
            nonce: boilerAdmin.nonce,
            request_id: requestId
        }, function(response) {
            if (response.success) {
                modalContent.html(response.data.html);
            } else {
                modalContent.html('<p>Error: ' + response.data.message + '</p>');
            }
        });
    });

    // Close modal
    closeModal.on('click', function() {
        modal.hide();
    });

    // Close modal if clicking outside the content area
    $(window).on('click', function(e) {
        if (e.target == modal[0]) {
            modal.hide();
        }
    });
});