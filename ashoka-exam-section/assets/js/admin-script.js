/**
 * Ashoka Exam Section – Admin Scripts
 *
 * Features:
 *  - Auto-calculate MID total on internal-marks forms
 *  - AJAX student loading on Enter Marks page
 */
/* global ashokaAjax, jQuery */
(function ($) {
    'use strict';

    /* ── Internal marks: live total calculation ── */
    function calcInternalTotal(mid1, mid2) {
        mid1 = parseFloat(mid1) || 0;
        mid2 = parseFloat(mid2) || 0;
        return (Math.max(mid1, mid2) * 0.8 + Math.min(mid1, mid2) * 0.2).toFixed(2);
    }

    // Single-record add/edit form (class-ashoka-internal-marks.php)
    $(document).on('input', '#mid1_marks, #mid2_marks', function () {
        var mid1  = $('#mid1_marks').val();
        var mid2  = $('#mid2_marks').val();
        var total = calcInternalTotal(mid1, mid2);
        $('#ashoka-int-total').text(total);
    });

    // Bulk entry table (class-ashoka-faculty-marks.php)
    $(document).on('input', '.ashoka-mid-input', function () {
        var $row  = $(this).closest('tr');
        var mid1  = $row.find('input[name="mid1[]"]').val();
        var mid2  = $row.find('input[name="mid2[]"]').val();
        var total = calcInternalTotal(mid1, mid2);
        $row.find('.ashoka-total-cell').text(total);
    });

    /* ── AJAX: dynamically load students on Enter Marks page ── */
    // (Currently the page uses a POST form submit for filter; this AJAX handler
    //  is available for future dynamic updates without a full page reload.)
    $(document).on('change', '#filter_sub_code, #filter_branch, #filter_year, #filter_sem', function () {
        var subCode = $('#filter_sub_code').val();
        var branch  = $('#filter_branch').val();
        var year    = $('#filter_year').val();
        var sem     = $('#filter_sem').val();

        if (!subCode || !branch || !year || !sem) {
            return;
        }

        $.post(ashokaAjax.ajaxurl, {
            action:   'ashoka_get_students_for_marks',
            nonce:    ashokaAjax.nonce,
            sub_code: subCode,
            branch:   branch,
            year:     year,
            sem:      sem
        }, function (response) {
            if (!response.success || !response.data.length) {
                return;
            }
            // Update existing table rows if they exist
            // (page-level submit is the primary mechanism; this is a progressive enhancement).
        });
    });

}(jQuery));
