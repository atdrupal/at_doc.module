(function($){

Drupal.behaviors.atDocReport = {};
Drupal.behaviors.atDocReport.attach = function(context, settings) {
    $('.at-doc-reports').tabs();
    $('.at-doc-reports-entity')
            .tabs().addClass( "ui-tabs-vertical ui-helper-clearfix" )
    ;

    $('.at-doc-reports-entity li')
        .removeClass( "ui-corner-top" )
        .addClass( "ui-corner-left" )
    ;
};

})(jQuery);
