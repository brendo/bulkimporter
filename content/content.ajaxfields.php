<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');

	class contentExtensionBulkImporterAjaxFields extends AjaxPage {
		protected $_driver;
		
		public function view() {
			$this->_driver = $this->_Parent->ExtensionManager->create('bulkimporter');
			
			$target = $_GET['section'];
			
			$sectionManager = new SectionManager($this->_Parent);			

			/*	Fetch sections & populate a dropdown	*/
			$section = $sectionManager->fetch($target);
			
			$options = array();

			foreach($section->fetchFields() as $field) {
				if($field->get('type') == "bilink") {
					
					$el = new XMLElement("field", $field->get('label'));
					
					$el->setAttribute('id', $field->get('id'));
					$el->setAttribute('type', $field->get('type'));
					
					$this->_Result->appendChild($el);
				}			
			}
		}
	
	}
?>