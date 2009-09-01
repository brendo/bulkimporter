(function($) {
	$(document).ready(function() {
		$("#linked-section, #linked-entry").parent().hide();
	});

	/*----	Section Box Change ----*/

	$("#context").live('change', function() {
		var self = $(this);
		var sectionID = $('option:selected', self).val();
		var linked = $('#linked-section');
		
		linked.parent().fadeIn();
		
		linked.attr("disabled",false)
			.empty();

		$.get("../ajaxfields/", {section: sectionID}, function(data) {			
			$(data).find('field').each(function() {
				linked.prepend($("<option value='" + $(this).attr('id') + "'>" + $(this).text() + "</option>"));
			});
			linked.prepend($("<option value='' selected='selected'></option>"));
		}, "xml");
		
		$("#linked-section").trigger("change");
	});

	/*---- Bilink Field Change ----*/

	$("#linked-section").live('change', function() {		
		var self = $(this);
				
		var bilinkID = $('option:selected', $("#linked-section")).val();	
		var sectionID = $('option:selected', $('#context')).val();
		var entries = $("#linked-entry");
		
		entries.parent().fadeIn();
		
		entries.attr("disabled",false)
				.empty();

		$.get("../ajaxentries/", {section: sectionID, field: bilinkID}, function(data) {			
			$(data).find('entry').each(function() {
				entries.prepend($("<option value='" + $(this).attr('id') + "'>" + $(this).text() + "</option>"));
			});
		}, "xml");
	});

})(jQuery);
