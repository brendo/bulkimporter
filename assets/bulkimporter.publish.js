(function($) {

	/**
	 * This plugin adds Bulk Import option to Subsection Manager field.
	 *
	 * @author: Marcin Konicki, ahwayakchih@neoni.net
	 * @source: http://github.com/brendo/bulkimporter
	 */
	$(document).ready(function() {

		// Language strings
		Symphony.Language.add({
			'Bulk Import': false,
			'Single entry': false
		});

		var root = Symphony.Context.get('root'),
			params = Symphony.Context.get('bulkimporter');

		if (!params || !params['fields']) return;

		// Add submenu only for SubsectionManager fields that have "create" button.
		$('div.field.field-subsectionmanager div.stage:not(.single) div.queue a.create').each(function(){

			var a = $(this),
				parent = a.parent(),
				submenu = $('div.submenu', parent),
				field = a.parents('div.field-subsectionmanager'),
				field_id = field.attr('id').replace(/^field-/, ''),
				section_id = $('input[name="fields\\[subsection_id\\]\\['+field_id+'\\]"]', field).val();

			if (!field_id || !params['fields'][field_id]) return;

			if (!parent.is('div.menu')) {
				a.wrap('<div class="menu"/>');
				parent = a.parent();
			}

			if (submenu.length < 1) {
				submenu = $('<div class="submenu"/>').insertAfter(a);
			}

			if ($('a.option.single', parent).length < 1) {
				a.clone().addClass('option single').html(Symphony.Language.get('Single entry')).appendTo(submenu);
			}

			if ($('a.option.bulkimporter', parent).length < 1) {
				$('<a class="import option bulkimporter">'+Symphony.Language.get('Bulk Import')+'</a>').appendTo(submenu);
			}
		});

		// Load() mostly from subsectionmanager.publish.js
		var load = function(item, editor, iframe) {
			var content = iframe.contents(),
				stage = item.parents('div.stage'),
				selection = stage.find('ul.selection'),
				form = content.find('form');

			// Adjust interface
			content.find('head').prepend('<link rel="stylesheet" type="text/css" media="screen" href="'+root+'/extensions/subsectionmanager/assets/subsection.publish.css">');
			content.find('body').addClass('inline subsection');
			content.find('h1, h2, #nav, #notice:not(.error):not(.success), #notice a, #footer').remove();
			// Move standard settings out of fieldset
			form.find('fieldset.settings fieldset').insertBefore(form.find('div.actions'));
			// Move success and error information fields out of fieldset
			form.find('fieldset.settings label').prependTo(form);
			// Remove fieldsets
			form.find('fieldset.settings').remove();
			content.find('label input:first').focus();

			// Set height
			var height = content.find('#wrapper').outerHeight() || iframe.height();
			iframe.height(height).animate({
				opacity: 1
			}, 'fast');
			editor.animate({
				height: height
			}, 'fast');

			// Handle inline image preview
			if(content.find('body > img').width() > iframe.width()) {
			  content.find('body > img').css({
				'width': iframe.width()
			  });
			}

			// Fetch saving
			content.find('div.actions input').click(function() {
				iframe.animate({
					opacity: 0.01
				}, 'fast');
			});

			// Trigger update 
			if(content.find('#notice.success').size() > 0) {
				// Make Subsection Manager add new items
				// First "disable" form, just in case someone can click fast
				form.bind('submit.bulkimporter', function(){return false;});

				// Get item order
				// For some reason, this will not find new items.
				// So we get "old" items first, and then add new ones below.
				var sortorder = selection.find('li').map(function() {
					return $(this).attr('data-value');
				}).get().join(',');

				var added = 0;
				$('label.bulkimporter.added.files a', form).sort(function(a,b){return a.innerHTML > b.innerHTML ? 1 : -1;}).each(function(){
					var id = $(this).attr('href').match(/\d+/g);

					// Fetch item id
					if($.isArray(id)) {
						id = id[id.length - 1];
					}

					// Subsection manager looks for last number in "action" attribute
					form.attr('action', 'dummy://' + id);
					var uploadeditem = $('<li><span></span></li>').appendTo(selection);
					if (stage.is('.destructable')) {
						$('<a class="destructor">&#215;</a>').appendTo(uploadeditem);
					}
					stage.trigger('edit', [uploadeditem, iframe]);

					sortorder += (sortorder == '' ? '' : ',') + id;
					added++;
				});

				form.unbind('submit.bulkimporter');
				item.trigger('destruct');

				// Save sortorder				
				stage.parents('div.field-subsectionmanager').find('input[name*="sort_order"]').val(sortorder);

				// Remove empty queue message
				if (added > 0) {
					selection.find('li.empty.message').remove();
				}
			}
		};

		$('div.field.field-subsectionmanager div.stage:not(.single) div.queue').delegate('div.menu div.submenu a.import.bulkimporter', 'click.stage', function(){
			event.preventDefault();

			var stage = $(this).parents('div.stage'),
				selection = stage.find('ul.selection');

			// Remove old drawer
			$('li.bulkimporter').trigger('destruct');

			var item = $('<li class="bulkimporter"><span>'+Symphony.Language.get('Bulk Import')+'</span></li>').appendTo(selection);
			if (stage.is('.destructable')) {
				$('<a class="destructor">&#215;</a>').appendTo(item);
			}

			selection.find('li').bind('click.bulkimporter', function(){
				$('li.bulkimporter').trigger('destruct');
			});

			stage.trigger('constructstart', [item]);
			selection.addClass('constructing');

			// Remove empty selection message
			stage.find('li.empty').slideUp('fast', function() {
				stage.find('li.empty').remove();
			});

			// Show item
			item.slideDown('fast', function() {
				selection.removeClass('constructing');
				stage.trigger('constructstop', [item]);
			});

			var drawer = stage.data('templates.stage').templates.filter('.drawer').removeClass('template'),
				subsection_link = drawer.find('iframe').attr('target');

			stage.trigger('createstart', [item]);

			var editor = drawer.clone().hide().addClass('new'),
				env = Symphony.Context.get('env'),
				field = stage.parents('div.field-subsectionmanager'),
				field_id = field.attr('id').replace(/^field-/, ''),
				section_id = $('input[name="fields\\[subsection_id\\]\\['+field_id+'\\]"]', field).val();

			// Prepare iframe
			editor.find('iframe').css('opacity', '0.01').attr('src', root + '/symphony/extension/bulkimporter/import/?prepopulate[target]='+section_id+'&prepopulate[linked-section-field]='+field_id+'&prepopulate[linked-entry]='+env['entry_id']).load(function() {
				iframe = $(this);
				load(item, editor, iframe);
			});

			// Show subsection editor
			editor.insertAfter(item).slideDown('fast');			
			stage.trigger('createstop', [item]);

			stage.trigger('browsestop');
		});

	});

})(jQuery.noConflict());
