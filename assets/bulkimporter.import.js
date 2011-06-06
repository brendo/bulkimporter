(function($) {
	$(document).ready(function() {
		// Language strings
		Symphony.Language.add({
			'No valid upload field found': false
		});

		$("select.hidden-default").parent().hide();
		$("h3.hidden-default").hide();

		var section = $('#target-section'),
			fields = $('#target-field'),
			linkedh3 = $('#linked-message'),
			linked = $('#linked-section'),
			entries = $('#linked-entry');

		// Section Box Change
		$("#target-section").live('change', function() {
			var $self = $(this),
				sectionID = $('option:selected', $self).val();

			fields.attr('disabled', false).empty();
			linked.attr('disabled', false).empty();

			$.get('../ajaxsectioninfo/', {
					section: sectionID
				},
				function(data) {
					var field_opts = "",
						section_opts = "",
						can_proceed = true;

					// Fields
					$(data).find('field').each(function() {
						field_opts += "<option value='" + $(this).attr('id') + "'>" + $(this).text() + "</option>";
					});

					if(field_opts == "") {
						field_opts += "<option value=''>" + Symphony.Language.get('No valid upload field found') + "</option>";
						can_proceed = false;
					}

					fields.parent().slideDown("fast", function() {
						fields.append(field_opts);
					});

					if(can_proceed === false) return;

					// Related Sections
					$(data).find('section').each(function() {
						var $self = $(this);
						section_opts += "<option value='" + $self.attr('id') + "'>" + $self.attr('section') + ' : ' + $self.text() + "</option>";
					});

					if(section_opts != "") {
						linkedh3.show();
						linked.prepend("<option value=''></option>");
						linked.parent().slideDown("fast", function() {
							linked.append(section_opts);
						});
					}
				},
				'xml'
			);
		});

		// Section Link change
		$("#linked-section").live('change', function() {
			var $self = $(this),
				fieldID = $('option:selected', $("#linked-section")).val(),
				sectionID = $('option:selected', $('#target-section')).val(),
				entries = $("#linked-entry");

			entries.attr("disabled",false).empty();

			$.get("../ajaxentries/", {
					section: sectionID,
					field: fieldID
				},
				function(data) {
					var entry_opts = "";

					$(data).find('entry').each(function() {
						entry_opts += "<option value='" + $(this).attr('id') + "'>" + $(this).text() + "</option>";
					});

					entries.parent().slideDown("fast", function () {
						entries.append(entry_opts);
					});
				},
				"xml"
			);

		});
	});
})(jQuery);
