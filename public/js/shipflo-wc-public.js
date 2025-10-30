console.log("🚀 ShipFlo public JS loaded");

(function( $ ) {
    'use strict';
    const actionSlug = 'track';

    $(document).ready(function() {
        $('a.' + actionSlug).each(function(){
            $(this)
                .attr('target', '_blank')
                .addClass('shipflo-track-button'); 
        });
    });

})( jQuery );