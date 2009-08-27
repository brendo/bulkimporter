<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');

	class contentExtensionBulkImporterImport extends AdministrationPage {
		protected $_driver;

		public function __viewIndex() {
			$this->_driver = $this->_Parent->ExtensionManager->create('bulkimporter');
					
			$this->setPageType('form');
			$this->Form->setAttribute('enctype', 'multipart/form-data');
			$this->setTitle('Symphony &ndash; Bulk Importer');

			$this->appendSubheading('Import');

		// Events --------------------------------------------------------

			$container = new XMLElement('fieldset');
			$container->setAttribute('class', 'settings');
			$container->appendChild(
				new XMLElement('legend', 'Select <code>.zip</code> to import')
			);

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

			$this->__viewIndexFileInterface($group);
			$this->__viewIndexSectionName($group);

			$container->appendChild($group);
			$this->Form->appendChild($container);

		//---------------------------------------------------------------------

			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');

			$attr = array('accesskey' => 's');
			$div->appendChild(Widget::Input('action[save]', 'Import', 'submit', $attr));

			$this->Form->appendChild($div);
		}

	/*-------------------------------------------------------------------------
		Sections:
	-------------------------------------------------------------------------*/
		public function __viewIndexSectionName($context) {
			$sectionManager = new SectionManager($this->_Parent);

			/*	Label	*/
			$label = Widget::Label(__('Target Section'));

			/*	Fetch sections & populate a dropdown	*/
			$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$options = array();

			if(is_array($sections) && !empty($sections)){
				foreach($sections as $s) {
					$options[] = array(
						$s->get('id'),
						($fields['target'] == $s->get('id')),
						$s->get('name')
					);
				}
			}

			$label->appendChild(Widget::Select('fields[target]', $options, array('id' => 'context')));

			$context->appendChild($label);

		}

	/*-------------------------------------------------------------------------
		File Interface:
	-------------------------------------------------------------------------*/
		public function __viewIndexFileInterface($context) {
			$label = Widget::Label(__('File'));

			$label->appendChild(Widget::Input('fields[file]', NULL, 'file'));

			$context->appendChild($label);
		}
		
		
		
		public function __actionIndex() {		
			if (empty($this->_driver)) {
				$this->_driver = $this->_Parent->ExtensionManager->create('bulkimporter');
			}

			if (@isset($_POST['action']['save'])) {
				$this->prepareUpload($_POST['fields']);
			}
		}
		
		public function prepareUpload($post) {		
			$sectionManager = new SectionManager($this->_Parent);
			$section = $sectionManager->fetch($post['target'], 'ASC', 'name');
			$this->_driver->targetSection = $section;
			$fields = array();
			
			foreach($this->_driver->getSupportedFields() as $field) {
				$fields = $section->fetchFields($field);
			}
			
			if(is_array($fields)) {
				foreach($fields as $f) {
					$this->_driver->beginProcess();
				}
			} else {
				$error = 'An error occured, are you sure <code>%1$s</code> has a valid upload field? Available: <code>%2$s</code>';
				
				$this->pageAlert(
					__($error,
						array(
							$this->_driver->targetSection->get('handle'),
							implode(", ",$this->_driver->getSupportedFields())
						),
						Alert::ERROR
					)
				);
			}			
		}
		
	}
?>