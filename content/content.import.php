<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.entrymanager.php');

	class contentExtensionBulkImporterImport extends AdministrationPage {

		protected $_driver;
		protected $_errors;

		protected static $sectionManager = null;
		protected static $fieldManager = null;
		protected static $entryManager = null;

		public function __construct(Administration &$parent){
			parent::__construct($parent);

			self::$entryManager = new EntryManager(Administration::instance());
			self::$sectionManager = self::$entryManager->sectionManager;
			self::$fieldManager = self::$entryManager->fieldManager;

			$this->_driver = Symphony::ExtensionManager()->create('bulkimporter');
		}

		public function __viewIndex() {
			$this->setPageType('form');
			$this->Form->setAttribute('enctype', 'multipart/form-data');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Bulk Importer'))));

			$this->appendSubheading(__('Import'));

		// Previous errors -------------------------------------------------

			if (!empty($this->_errors) && is_array($this->_errors)) {
				$container = new XMLElement('fieldset');
				$container->setAttribute('class', 'settings');
				$container->appendChild(
					new XMLElement('legend', __('Errors'))
				);
				$p = new XMLElement('p', __('Previous attempt encountered some errors'));
				$p->setAttribute('class', 'help');
				$container->appendChild($p);

				$this->__viewIndexErrors($container, $this->_errors);

				$this->Form->appendChild($container);
			}

		// Previous success ------------------------------------------------

			if (!empty($this->_driver->entries) && is_array($this->_driver->entries)) {
				$container = new XMLElement('fieldset');
				$container->setAttribute('class', 'settings');
				$container->appendChild(
					new XMLElement('legend', __('Success'))
				);
				$p = new XMLElement('p', __('Created %d entries', array(count($this->_driver->entries))));
				$p->setAttribute('class', 'help');
				$container->appendChild($p);

				$this->__viewIndexCreatedEntries($container, $this->_driver->entries);

				$this->Form->appendChild($container);
			}

		// Settings --------------------------------------------------------

			$container = new XMLElement('fieldset');
			$container->setAttribute('class', 'settings');
			$container->appendChild(
				new XMLElement('legend', __('Upload archive'))
			);
			$p = new XMLElement('p', __('Select <code>.zip</code> to import'));
			$p->setAttribute('class', 'help');
			$container->appendChild($p);

			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'primary');

			$this->__viewIndexFileInterface($group);
			$this->__viewIndexSectionName($group);
			$this->__viewIndexSectionFields($group);

			$container->appendChild($group);

			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'secondary');

			$group->appendChild(
				new XMLElement('h3', __('The BulkImporter allows you to associate the newly imported files with another entry. If you don\'t need this feature, feel free to ignore this column'), array(
					'class' =>'hidden-default',
					'id' => 'linked-message'
				))
			);

			$this->__viewIndexSectionLinks($group);
			$this->__viewIndexLinkedEntries($group);

			$container->appendChild($group);

			$this->Form->appendChild($container);

		//---------------------------------------------------------------------

			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Input('action[save]', __('Import'), 'submit', array('accesskey' => 's'))
			);

			$this->Form->appendChild($div);
		}

	/*-------------------------------------------------------------------------
		Sections:
	-------------------------------------------------------------------------*/
		public function __viewIndexErrors($wrapper, array $errors) {
			if (empty($errors) || !is_array($errors)) return;

			$label = Widget::Label('');
			$label->setAttribute('class', 'bulkimporter failed files');

			$failed = 0;
			foreach ($errors as $error => $paths) {
				$failed += count($paths);
				$e = new XMLElement('div');
				$e->appendChild(new XMLElement('p', __('(%d) %s', array(count($paths), $error))));
				$e->appendChild(new XMLElement('ul', '<li>' . implode('</li><li>', $paths) . '</li>'));
				$label->appendChild($e);
			}

			$wrapper->appendChild(Widget::wrapFormElementWithError($label, __('%d failed.', array($failed))));
		}

		public function __viewIndexCreatedEntries($wrapper, array $entries) {
			if (empty($entries) || !is_array($entries)) return;

			$label = Widget::Label('');
			$label->setAttribute('class', 'bulkimporter added files');

			$div = new XMLElement('div');

			$section_handle = NULL;
			$ul = new XMLElement('ul');
			foreach ($entries as $path => $entry_id) {
				// Get field id from first entry
				if (empty($section_handle)) {
					$section_id = self::$entryManager->fetchEntrySectionID($entry_id);
					$section = self::$sectionManager->fetch($section_id);
					$section_handle = $section->get('handle');
				}

				$link = Widget::Anchor($path, sprintf('%s/symphony/publish/%s/edit/%d/', URL, $section_handle, $entry_id));

				$li = new XMLElement('li', $link->generate());
				$ul->appendChild($li);
			}

			$div->appendChild($ul);
			$label->appendChild($div);
			$wrapper->appendChild($label);
		}

		public function __viewIndexSectionName($wrapper) {
			// Label
			$label = Widget::Label(__('Import files to this section:'));

			// Fetch sections & populate a dropdown
			$sections = self::$sectionManager->fetch(NULL, 'ASC', 'name');
			$options = array();

			if(is_array($sections) && !empty($sections)) foreach($sections as $s) {
				$options[] = array(
					$s->get('id'), ($_GET['prepopulate']['target'] == $s->get('id')), $s->get('name')
				);
			}

			$label->appendChild(Widget::Select('fields[target]', $options, array('id' => 'target-section')));

			$wrapper->appendChild($label);
		}

		public function __viewIndexSectionFields($wrapper) {
			$options = array();

			if (!empty($_GET['prepopulate']['target'])) {
				// Fetch sections & populate a dropdown with the available upload fields
				$section = self::$sectionManager->fetch($_GET['prepopulate']['target']);
				if (!empty($section)) {
					foreach($section->fetchFields() as $field) {
						if(!preg_match(Extension_BulkImporter::$supported_fields['upload'], $field->get('type'))) continue;
						$options[] = array(
							$field->get('id'), ($_GET['prepopulate']['target-field'] == $field->get('id')), $field->get('label')
						);
					}
				}
			}

			// Label
			$label = Widget::Label(__('Import the files to this field:'));
			$label->appendChild(Widget::Select('fields[target-field]', $options, array(
					'id' => 'target-field',
					'class' => (empty($options) ? 'hidden-default' : '')
				))
			);
			$wrapper->appendChild($label);
		}

		public function __viewIndexSectionLinks($wrapper) {
			$options = array();
			if (!empty($_GET['prepopulate']['target'])) {
				$options = $this->getLinkedSections($_GET['prepopulate']['target'], $_GET['prepopulate']['linked-section-field']);
			}

			// Label
			$label = Widget::Label(__('Associate imported files to entries in this section:'));
			$label->appendChild(
				Widget::Select('fields[linked-section-field]', $options, array(
					'id' => 'linked-section',
					'class' => (empty($options) ? 'hidden-default' : '')
				))
			);

			$wrapper->appendChild($label);
		}

		public function __viewIndexLinkedEntries($wrapper) {
			$options = array();
			if (!empty($_GET['prepopulate']['linked-section-field'])) {
				$options = $this->getLinkedEntries($_GET['prepopulate']['linked-section-field'], $_GET['prepopulate']['linked-entry']);
			}

			// Label
			$label = Widget::Label(__('Choose the entry that you want these files to be associated with:'));
			$label->appendChild(
				Widget::Select('fields[linked-entry]', $options, array(
					'id' => 'linked-entry',
					'class' => (empty($options) ? 'hidden-default' : '')
				))
			);

			$wrapper->appendChild($label);
		}

	/*-------------------------------------------------------------------------
		File Interface:
	-------------------------------------------------------------------------*/

		public function __viewIndexFileInterface($context) {
			$label = Widget::Label(__('File'));
			$label->appendChild(Widget::Input('fields[file]', NULL, 'file'));
			$context->appendChild($label);

			$label = new XMLElement('label');
			$input = Widget::Input('fields[preserve_subdirectories]', 'yes', 'checkbox');
			$label->setValue(__('%s Preserve subdirectories', array($input->generate())));
			$context->appendChild($label);

			$label = new XMLElement('label');
			$input = Widget::Input('fields[archive_is_parent]', 'yes', 'checkbox');
			$label->setValue(__('%s Use archive name as subdirectory', array($input->generate())));
			$context->appendChild($label);
		}

		public function __actionIndex() {
			if (isset($_POST['action']) && array_key_exists('save', $_POST['action'])) {
				$this->prepareUpload($_POST['fields']);
			}
		}

		public function prepareUpload($post) {
			$section = self::$sectionManager->fetch($post['target']);

			$this->_driver->target_section = $section;
			$this->_driver->linked_entry = array(
				"linked-field" => $post['linked-section-field'],
				"linked-entry" => $post['linked-entry']
			);
			$this->_driver->preserve_subdirectories = ($post['preserve_subdirectories'] == 'yes' ? true : false);
			$this->_driver->archive_is_parent = ($post['archive_is_parent'] == 'yes' ? true : false);

			$field = (isset($post['target-field'])) ? self::$fieldManager->fetch($post['target-field']) : null;

			if(is_null($field)) {
				$this->pageAlert(
					__("There was an error locating a target field in the <code>%s</code> section", array($section->get('name'))),
					Alert::ERROR
				);

				return false;
			}
			else {
				$this->_driver->target_field = $field;
			}

			// Start to upload the files to the file system
			if(!$this->_driver->beginProcess()) {
				$this->pageAlert(__("No <code>.zip</code> file was provided"), Alert::ERROR);
			}

			// Create entries in the target section for each of the	uploaded files
			try {
				$this->_driver->commitFiles();
			}
			catch (Exception $ex) {
				$this->pageAlert(
					$ex->getMessage() . ' in ' . $ex->getFile() . ' on line ' . $ex->getLine(),
					Alert::ERROR
				);

				return false;
			}

			$uploaded = $failed = 0;
			$this->_errors = array();

			foreach($this->_driver->files as $file) {
				if ($file->imported) {
					$uploaded++;
				}
				else {
					$failed++;
					$this->_errors[$file->errors[0]][] = str_replace($this->_driver->extracted_directory, '', $file->location);
				}
			}

			if($uploaded == 0) {
				$this->pageAlert(
					__("No files were uploaded, %d failed. <a href='#error'>See below for details.</a>", array($failed)),
					Alert::ERROR
				);
			}
			else {
				$this->pageAlert(
					__('Bulk import complete to <code>%s</code>, %d were uploaded, %d failed. <a href="%s">Import another?</a> <a href="%s">View section</a>', array(
						$section->get('handle'), $uploaded, $failed, URL . '/symphony/extension/bulkimporter/import/', URL . '/symphony/publish/' . $section->get('handle') . '/'
					)),
					Alert::SUCCESS
				);
			}

			$this->_driver->cleanUp(array($uploaded,$failed));
		}

		public function getLinkedSections($section, $selected) {
			if (empty($section)) return array();

			$options = array();

			// Check to see if any Sections link to this using the Section Associations table
			$associations = Symphony::Database()->fetch(sprintf("
					SELECT
						`child_section_field_id`
					FROM
						`tbl_sections_association`
					WHERE
						`parent_section_id` = %d
				", Symphony::Database()->cleanValue($section)
			));

			if(is_array($associations) && !empty($associations)) foreach($associations as $related_field) {
				$field = self::$fieldManager->fetch($related_field['child_section_field_id']);

				if(!preg_match(Extension_BulkImporter::$supported_fields['section'], $field->get('type'))) continue;

				$options[] = array(
					$field->get('id'), ($selected == $field->get('id')), self::$sectionManager->fetch($field->get('parent_section'))->get('name') . ': ' . General::sanitize($field->get('label'))
				);
			}

			// Check for Subsection Manager
			if(Symphony::ExtensionManager()->fetchStatus('subsectionmanager') == EXTENSION_ENABLED) {
				$associations = Symphony::Database()->fetch(sprintf("
						SELECT
							`field_id`
						FROM
							`tbl_fields_subsectionmanager`
						WHERE
							`subsection_id` = %d
					", Symphony::Database()->cleanValue($section)
				));

				if(is_array($associations) && !empty($associations)) foreach($associations as $related_field) {
					$field = self::$fieldManager->fetch($related_field['field_id']);

					if(!preg_match(Extension_BulkImporter::$supported_fields['section'], $field->get('type'))) continue;

					$options[] = array(
						$field->get('id'), ($selected == $field->get('id')), self::$sectionManager->fetch($field->get('parent_section'))->get('name') . ': ' . General::sanitize($field->get('label'))
					);
				}
			}

			// Check for BiLink
			if(Symphony::ExtensionManager()->fetchStatus('bilinkfield') == EXTENSION_ENABLED) {
				$associations = Symphony::Database()->fetch(sprintf("
						SELECT
							`field_id`
						FROM
							`tbl_fields_bilink`
						WHERE
							`linked_section_id` = %d
					", Symphony::Database()->cleanValue($section)
				));

				if(is_array($associations) && !empty($associations)) foreach($associations as $related_field) {
					$field = self::$fieldManager->fetch($related_field['field_id']);

					if(!preg_match(Extension_BulkImporter::$supported_fields['section'], $field->get('type'))) continue;

					$options[] = array(
						$field->get('id'), ($selected == $field->get('id')), self::$sectionManager->fetch($field->get('parent_section'))->get('name') . ': ' . General::sanitize($field->get('label'))
					);
				}
			}

			return $options;
		}

		public function getLinkedEntries($field, $selected) {
			if (empty($field)) return array();

			$options = array();

			$field = self::$entryManager->fieldManager->fetch($field);
			$section = self::$sectionManager->fetch($field->get('parent_section'));

			$entry_column = current($section->fetchVisibleColumns());

			//	Display the first column from every entry in the linked section
			$entries = self::$entryManager->fetch(null, $field->get('parent_section'));

			foreach($entries as $entry) {
				$values = $entry->getData($entry_column->get('id'));

				$options[] = array(
					$entry->get('id'), ($selected == $entry->get('id')), General::sanitize($values['value'])
				);
			}

			return $options;
		}
	}

