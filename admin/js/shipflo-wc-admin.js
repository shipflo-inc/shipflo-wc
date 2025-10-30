console.log("🚀 ShipFlo admin JS loaded");

(function( $ ) {
    'use strict';

    $(document).ready(function() {
        $('a.track').each(function(){
            $(this)
                .attr('target', '_blank')
                .addClass('shipflo-track-button'); 
        });

        $('a.push-to-shipflo').each(function(){
            $(this).click((e) => pushToShipFlo(e)); 
        });
    });
    
})( jQuery );