$(document).ready(function() {

    $(".datetime").each(function () {
        $(this).datetimepicker({
            step: 1,
            // mask: '9999-19-39 29:59',
            format: 'Y-m-d H:i',
            formatDate: 'Y-m-d',
            formatTime: 'H:i',
            defaultDate: $('#defaultDate').val(),
            defaultTime: $('#defaultTime').val(),
            onChangeDateTime: function () {
                var momentFormat = "YYYY-MM-DD HH:mm";
                var er_arrival_time_str = $('#er_arrival_time').val();
                var er_arrival_time = moment(er_arrival_time_str, momentFormat);
                var call_received_time_str = $('#call_received_time').val();
                var call_received_time = moment(call_received_time_str, momentFormat);

                var admitToCallRequestTimeMinutes = call_received_time.diff(er_arrival_time, "minutes");
                if (admitToCallRequestTimeMinutes >= 0) {
                    $("#admit_to_call_request_time").val(admitToCallRequestTimeMinutes);
                }

                var dispatch_time_str = $('#dispatch_time').val();
                var dispatch_time = moment(dispatch_time_str, momentFormat);
                var time_in_datetime_str = $('#time_in').val();
                var time_out_datetime_str = $('#time_out').val();
                var time_in_datetime = moment(time_in_datetime_str, momentFormat);
                var time_out_datetime = moment(time_out_datetime_str, momentFormat);

                var dispatchedTimeMinutes = dispatch_time.diff(call_received_time, "minutes");
                if (dispatchedTimeMinutes >= 0) {
                    $("#dispatched_time").val(dispatchedTimeMinutes);
                }

                var arrivalTimeMinutes = time_in_datetime.diff(dispatch_time, "minutes");
                if (arrivalTimeMinutes >= 0) {
                    $("#arrival_time").val(arrivalTimeMinutes);
                }

                var onSceneTimeMinutes = time_out_datetime.diff(time_in_datetime, "minutes");
                if (onSceneTimeMinutes >= 0) {
                    $("#onscene_time").val(onSceneTimeMinutes);
                }

                var totalCallTimeMinutes = time_out_datetime.diff(call_received_time, "minutes");
                if (totalCallTimeMinutes >= 0) {
                    $("#total_call_time").val(totalCallTimeMinutes);
                }

                // Set the date of service to be the date part of time-in
                if (time_in_datetime_str &&
                    time_in_datetime_str != '____-__-__ __:__') {
                    var date_of_service = time_in_datetime_str.substring(0, 10);
                    $("#created_datetime").val(date_of_service);
                }

            }
        });
    });

    $("#p21, #disposition").change(function () {
        var p21 = $("#p21").val();
        if (p21 == 'YES' || p21 == 0) {
            var initialDispo = $("#disposition").val();
            // If yes, make final dispo the same
            $("#p21-row").hide();
            $("#final_dispo").val(initialDispo);
        } else {
            // Otherwise show option to set final dispo
            $("#p21-row").show();
        }
    });

    $("#p21").change();

    $("#next_step").change(function () {
        var next_step = $("#next_step").val();
        if (next_step == 'YES') {
            $("#next-step-id-row").show();
        } else {
            $("#next-step-id-row").hide();
        }
    });

    $("#next_step").change();

    $('input[type=radio][name=encounter_type]').change(function () {
        var val = $('input[name=encounter_type]:checked').val();
        if (val == 'Floor') {
            var date = null;
            if ($('#hospital_admit_date').val()) {
                date = $('#hospital_admit_date').val();
            } else {
                date = $('#defaultDate').val();
            }
            $('#hospital_admit_date').val(date);
            $('.floor_call_exist').show();
        } else {
            $('.floor_call_exist').hide();
            $('#hospital_admit_date').val('');
        }
    });

    $('input[type=radio][name=encounter_type]').change();
});
