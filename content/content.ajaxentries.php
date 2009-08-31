<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');

	class contentExtensionBulkImporterAjaxEntries extends AjaxPage {
		protected $_driver;
		
		public function view() {
			$this->_driver = $this->_Parent->ExtensionManager->create('bulkimporter');
			
			$target = $_GET['section'];
			$fieldID = $_GET['field'];
			
			$sectionManager = new SectionManager($this->_Parent);
			$entryManager = new EntryManager($this->_Parent);			

			/*	Fetch sections & populate a dropdown	*/
			$section = $sectionManager->fetch($target);
			$fields = $section->fetchFields("bilink");
			
			foreach($fields as $field) {
				$linked_section_id = $field->get("linked_section_id");
				$linked_field_id = $field->get("linked_field_id");
			}

			$entries = $entryManager->fetch(null,$linked_section_id);
			
			$options = array();
			$linked_section = $sectionManager->fetch($linked_section_id);
			$li_section = $linked_section->fetchFields();
			
			foreach($li_section as $field) {
				if($field->get('element_name') == "name") {
					$linked_field_name = $field->get('id');
				}
			}

			foreach($entries as $entry) {
				
				$data = current($entryManager->fetch($entry->get('id'),$linked_section_id));
				$values = $data->getData($linked_field_name);
				
				$el = new XMLElement("entry", $values['value']);
				$el->setAttribute('id', $entry->get('id'));
				
				$this->_Result->appendChild($el);	
			}
		}
	
	}
?>