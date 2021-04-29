$('#widgetSummaryModal').off('click', '#bt_removeJcWidgetSummary').on('click', '#bt_removeJcWidgetSummary', function() {

    var myData = $('#table_JcWidgetSummary .removeWidget:checked');
    var count = myData.length;
    if ( count > 0){
        
        var warning = "<i source='md' name='alert-outline' style='color:#ff0000' class='mdi mdi-alert-outline'></i>" ;
        if ( count == 1 )
        {
            msg = warning + '  Voulez vous supprimer ce widget ? '  + warning ;
        }
        else{
            msg = warning + '  Voulez vous supprimer ces ' + count +' widgets ? '  + warning ;
        }


        getSimpleModal({title: "Confirmation", fields:[{type: "string",
        value: msg  }] }, function(result) {
            $('#alert_JcWidgetSummary').hideAlert();
        
            myData.each(function() {
                var widgetId = $(this).closest('.tr_object').data('widget_id');
                $.ajax({
                    type: "POST",
                    url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
                    data: {
                        action: "removeWidgetConfig",
                        eqId: widgetId
                    },
                    dataType: 'json',
                    error: function(error) {
                        $('#alert_JcWidgetSummary').showAlert({message: error.message, level: 'danger'});
                    },
                    success: function(data) {
                        if (data.state != 'ok') {
                            $('#alert_JcWidgetSummary').showAlert({
                                message: data.result,
                                level: 'danger'
                            });
                        }
                        else{
                            $('.tr_object[data-widget_id='+widgetId+']').remove();
                            $('#widgetSummaryModal').attr('data-has-changes', true);
                        }
                    }
                })
            });

            $('#alert_JcWidgetSummary').showAlert({
                message: 'Suppression effectuée',
                level: 'success'
            });
            $('#bt_removeJcWidgetSummary').css('display', 'none');
        });
    }


});

$('#bt_saveJcWidgetSummary').off('click').on('click', function() {
    $('#div_alert').hideAlert();
    $('#alert_JcWidgetSummary').hideAlert();

    myData = $('#table_JcWidgetSummary .tr_object[data-changed=true]').getValues('.objectAttr');

    if (myData.length > 0 ) {
        $.post({
                url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
                data: {
                    action: 'updateWidgetMass',
                    widgetsObj: myData
                },
                cache: false,
                dataType: 'json',
                async: false,
                success: function( data ) {
                    if (data.state != 'ok') {
                        $('#alert_JcWidgetSummary').showAlert({
                            message: data.result,
                            level: 'danger'
                        });
                    }
                    else{
                        $('#alert_JcWidgetSummary').showAlert({
                            message: 'Sauvegarde effectuée',
                            level: 'success'
                        });
                        $('#widgetSummaryModal').attr('data-has-changes', true);
                    }
                    
                }
            });
    }
    else{
        $('#alert_JcWidgetSummary').showAlert({
            message: 'Aucun changement',
            level: 'warning' 
        });
    }

});


$("#table_JcWidgetSummary").on('change', ' .objectAttr', function() {
    $(this).closest('.tr_object').attr('data-changed', true);
  });


$('#bt_updateWidgetSummary').off('click').on('click', function() {

    var myData = $('#table_JcWidgetSummary .tr_object[data-updated=true]');
    myData.each(function() {
        var id = $(this).attr('data-widget_id');
        
        //update data
        displayWidgetSummaryData(id);

        //highlight line in orange
        $(this).attr('style', 'background-color : #FF7B00 !important');
        //fadeout
        $(this).animate({
            'opacity': '0.5'
            }, 3000, function () {
                $(this).css({'backgroundColor': '#fff','opacity': '1'});
            }
        );
        //remove updated attr
        $(this).removeAttr( "data-updated" );
    });

    //hide update button
    $('#bt_updateWidgetSummary').css('display', 'none');
    
})


$('#widgetSummaryModal').off('click', '.removeWidget').on('click', '.removeWidget', function() {

    var myData = $('#table_JcWidgetSummary .removeWidget:checked');
    var count = myData.length;
    if ( count > 0 ){
        $('#bt_removeJcWidgetSummary').css('display', '');
    }
    else{
        $('#bt_removeJcWidgetSummary').css('display', 'none');
    }
});

$('#table_JcWidgetSummary').off('click', '.bt_openWidget').on('click', '.bt_openWidget', function() {

    var eqId = $(this).closest('.tr_object').data('widget_id');
    editWidgetModal(eqId, false, false, false);
    $(this).closest('.tr_object').attr('data-updated', true);
    $('#bt_updateWidgetSummary').css('display', 'block');
    

})

function displayWidgetSummaryData(myId = ''){

    $.post({
        url: "plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php",
        data: {
            action: 'getWidgetMass',
            id: myId
        },
        cache: false,
        dataType: 'json',
        async: false,
        success: function( data ) {
            if (data.state != 'ok') {
                $('#alert_JcWidgetSummary').showAlert({
                    message: data.result,
                    level: 'danger'
                });
            }
            else{
                if ( myId == ''){
                    $('#table_JcWidgetSummary tbody').html(data.result);
                    $("#table_JcWidgetSummary").trigger("update");

                }
                else{
                    $('#table_JcWidgetSummary .tr_object[data-widget_id='+myId+']').html(data.result);
                    $("#table_JcWidgetSummary").trigger("update");
                }
            }
        }
    });

    

}

function check_before_closing(){
    var hasChanges = $('#widgetSummaryModal').attr('data-has-changes');
    if ( hasChanges == 'true' ){
        var vars = getUrlVars()
        var url = 'index.php?'
        delete vars['id']
        delete vars['saveSuccessFull']
        delete vars['removeSuccessFull']
        
        url = getCustomParamUrl(url, vars);
        modifyWithoutSave = false
        loadPage(url);
    }
    $("#widgetSummaryModal").dialog('destroy').remove();
}

$(document).ready(function(){
    $("#table_JcWidgetSummary").tablesorter({
        widthFixed: true,
        sortLocaleCompare: true,
        sortList: [ [2,0],[3,0] ],
        theme : 'bootstrap',
        headerTemplate: '{content} {icon}',
        widgets: ["zebra", "filter", "uitheme", 'stickyHeaders'],
        widgetOptions: {
           resizable : false,
           resizable_addLastColumn : true
        }
    })
    displayWidgetSummaryData();
    
});