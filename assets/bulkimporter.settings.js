(function($) {

	$(document).ready(function() {
		// Language strings
		Symphony.Language.add({
			'{$checkbox} Enable Bulk Importer': false
		});

		var params = Symphony.Context.get('bulkimporter'),
		    supported = 'li.field-subsectionmanager';

		function inject(index, element) {
			var pos = $('input[type="hidden"]', $(this)).attr('name').replace(/fields\[(-?\d+)\](.*)/, '$1');
			if (pos != '') {
				var id = $('input[name="fields\\['+pos+'\\]\\[id\\]"]').val();
				var value = {'field_id': '', 'section_id': ''};
				if (id && params && params['fields'] && params['fields'][id]) {
					value = params['fields'][id];
				}
				var s = Symphony.Language.get('{$checkbox} Enable Bulk Importer', { 
							'checkbox': '<input type="checkbox" name="fields['+pos+'][bulkimporter][enabled]" value="Yes" class="bulkimporter enabled" '+(value.field_id ? 'checked="checked"' : '')+'/>'
						});
				$('div.content', $(this)).append('<div class="bulkimporter settings"><label>'+s+'</label></div>');
			}
		}

		$('#fields-duplicator').bind('construct', function(target, field){
			if ($(field).is(supported)) $(field).each(inject);
		});

		$(supported).each(inject);

	});
})(jQuery.noConflict());
