///////////////////////////////////////////////////
// Update the graphs with new data
// depended on patient, startDate, endDate
///////////////////////////////////////////////////

function updateGraph(patientId, startDate, endDate) {
    // Set the current route
    var route;
    if ($("#piechart").length && $('#piechartTable').length) {
        route = 'vizhaal_visplat_homepage';
    }
    else if ($("#chordDiagram").length && $('#chordDiagram').length) {
        route = 'vizhaal_visplat_dependency';
    }
    $.ajax({
        type: "POST",
        url: Routing.generate('vizhaal_visplat_ajax_update_patient'),
        data: JSON.stringify({id: patientId, startDate: startDate, endDate: endDate, route: route}),
        dataType: "json",
        success: function (data) {
            // Verify an existence of #piechart
            if ($("#piechart").length && $('#piechartTable').length) {
                // Remove old graphs
                document.getElementById('piechart').innerHTML = '';
                document.getElementById('piechartTable').innerHTML = '';
                createPieChart(data['pieChart']);
            }
            if ($('#ganttchart').length) {
                document.getElementById('ganttchart').innerHTML = '';
                createGanttChart(data['ganttChart']);
            }
            if ($('#chordDiagram').length) {
                document.getElementById('chordDiagram').innerHTML = '';
                createChordDiagram(data['events'], data['matrix']);
            }
            if ($('#statustable').length) {
                document.getElementById('statustable').innerHTML = '';
                createStatusTable(data['statusTable']);
            }
            // Create responsive
            createResponsive();
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            alert('Error : ' + errorThrown);
        }
    });
}
///////////////////////////////////////////////////
// Update date field when the patientId is changed
///////////////////////////////////////////////////
function updateDateField(patientId) {
    $.ajax({
        type: "POST",
        url: Routing.generate('vizhaal_visplat_ajax_update_date'),
        data: JSON.stringify({id: patientId}),
        dataType: "json",
        async: false,
        success: function (data) {
            // Declared the data as global variable
            // Remove all the options inside the date selector
            $('#form_startDate').empty();
            $('#form_endDate').empty();
            // Reappend them
            for (i = 0; i < data.length; i++) {
                $('#form_startDate').append(
                    $('<option></option>').attr('value', data[i]).text(data[i])
                );
                $('#form_endDate').append(
                    $('<option></option>').attr('value', data[i]).text(data[i])
                )
            }
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            alert('Error : ' + errorThrown);
        }
    });
}
///////////////////////////////////////////////////
// Update the end date field depended on
// start date field, should be more than
// the start date field.
///////////////////////////////////////////////////
function updateEndDateField(patientId) {
    $.ajax({
        type: "POST",
        url: Routing.generate('vizhaal_visplat_ajax_update_date'),
        data: JSON.stringify({id: patientId}),
        dataType: "json",
        async: false,
        success: function (data) {
            $('#form_endDate').empty();
            for (i = 0; i < data.length; i++) {
                if (parseDate(data[i]) >= parseDate($('#form_startDate').val())) {
                    $('#form_endDate').append(
                        $('<option></option>').attr('value', data[i]).text(data[i])
                    );
                }
            }
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            alert('Error : ' + errorThrown);
        }
    });
}

///////////////////////////////////////////////////
// Parse date in the format '
///////////////////////////////////////////////////
function parseDate(date) {
    var dateArray = date.split('/');
    // Transform month into number
    var month = "JanFebMarAprMayJunJulAugSepOctNovDec".indexOf(dateArray[1]) / 3 + 1;
    // Month index stars from 0
    var newDate = new Date(dateArray[2], month - 1, dateArray[0]);
    return newDate;
}

$(document).ready(function () {
    $('#form_patient').change(function () {
//        // Disabled the end date selector when the user is changed.
//        $('#form_endDate').attr('disabled', 'disabled');
        var patientId = $(this).val();
        // Update date field
        updateDateField(patientId);
        // Force the selector to select the first date
//        $('#form_date').val($('#form_date option:first').val());
        var startDate = $('#form_startDate').val();
        var endDate = $('#form_endDate').val();
        updateGraph(patientId, startDate, endDate)
    });
    $('#form_startDate').change(function () {
        // Enable endDate selector
//        if ($('#form_endDate').attr('disabled') != undefined) {
//            $('#form_endDate').removeAttr('disabled');
//        }
        var patientId = $('#form_patient').val();
        var startDate = $(this).val();
        updateEndDateField(patientId);
        var endDate = $('#form_endDate').val();
        updateGraph(patientId, startDate, endDate);
    });
    $('#form_endDate').change(function () {
        var patientId = $('#form_patient').val();
        var startDate = $('#form_startDate').val();
        var endDate = $(this).val();
        updateGraph(patientId, startDate, endDate);


    });
});
