<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');

	class contentExtensionBulkImporterImport extends AdministrationPage {
		protected $_driver;
		protected $_errors;

		public function __viewIndex() {
			$this->_driver = $this->_Parent->ExtensionManager->create('bulkimporter');

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

			$attr = array('accesskey' => 's');
			$div->appendChild(Widget::Input('action[save]', __('Import'), 'submit', $attr));

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

		public function __viewIndexSectionName($wrapper) {
			$sectionManager = new SectionManager($this->_Parent);

			// Label
			$label = Widget::Label(__('Import files to this section:'));

			// Fetch sections & populate a dropdown
			$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$options = array();

			if(is_array($sections) && !empty($sections)) foreach($sections as $s) {
				$options[] = array(
					$s->get('id'), false, $s->get('name')
				);
			}

			$label->appendChild(Widget::Select('fields[target]', $options, array('id' => 'target-section')));

			$wrapper->appendChild($label);
		}

		public function __viewIndexSectionFields($wrapper) {
			$sectionManager = new SectionManager($this->_Parent);

			// Label
			$label = Widget::Label(__('Import the files to this field:'));
			$label->appendChild(
				Widget::Select('fields[target-field]', null, array(
					'id' => 'target-field',
					'class' => 'hidden-default'
				))
			);
			$wrapper->appendChild($label);
		}

		public function __viewIndexSectionLinks($wrapper) {
			$sectionManager = new SectionManager($this->_Parent);

			// Label
			$label = Widget::Label(__('Associate imported files to entries in this section:'));
			$label->appendChild(
				Widget::Select('fields[linked-section-field]', null, array(
					'id' => 'linked-section',
					'class' => 'hidden-default'
				))
			);

			$wrapper->appendChild($label);
		}

		public function __viewIndexLinkedEntries($wrapper) {
			$sectionManager = new SectionManager($this->_Parent);

			// Label
			$label = Widget::Label(__('Choose the entry that you want these files to be associated with:'));
			$label->appendChild(
				Widget::Select('fields[linked-entry]', null, array(
					'id' => 'linked-entry',
					'class' => 'hidden-default'
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
			if (empty($this->_driver)) {
				$this->_driver = $this->_Parent->ExtensionManager->create('bulkimporter');
			}

			if (isset($_POST['action']) && array_key_exists('save', $_POST['action'])) {
				$this->prepareUpload($_POST['fields']);
			}
		}

		public function prepareUpload($post) {
			$sectionManager = new SectionManager($this->_Parent);
			$fieldManager = new FieldManager($this->_Parent);
			$section = $sectionManager->fetch($post['target']);

			$this->_driver->target_section = $section;
			$this->_driver->linked_entry = array(
				"linked-field" => $post['linked-section-field'],
				"linked-entry" => $post['linked-entry']
			);
			$this->_driver->preserve_subdirectories = ($post['preserve_subdirectories'] == 'yes' ? true : false);
			$this->_driver->archive_is_parent = ($post['archive_is_parent'] == 'yes' ? true : false);

			$field = (isset($post['target-field'])) ? $fieldManager->fetch($post['target-field']) : null;

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
				$this->_driver->commitFiles($this->_Parent);
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
					__("No files were uploaded, %d failed", array($failed)),
					Alert::ERROR
				);
			}
			else {
				$this->pageAlert(
					__('Bulk import complete to <code>%s</code>, %d were uploaded, %d failed.', array(
						$section->get('handle'), $uploaded, $failed
					)),
					Alert::SUCCESS
				);
			}

			$this->_driver->cleanUp(array($uploaded,$failed));
		}
	}

