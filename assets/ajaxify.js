(function($) {
	
	$("#context").live('change', function() {
		var self = $(this);
		
		var sectionID = $('option:selected', self).val();	
		
		var linked = $('#linked-section');
		
		linked.attr("disabled",false);
		linked.empty();
		
		$.get("../ajaxfields/", {section: sectionID}, function(data) {
			$(data).find('field').each(function() {
				linked.prepend($("<option value='" + $(this).attr('id') + "'>" + $(this).text() + "</option>"));
			});	
		}, "xml");
		
	})
	
})(jQuery);
