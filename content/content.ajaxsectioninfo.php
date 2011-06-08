<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.extensionmanager.php');

	class contentExtensionBulkImporterAjaxSectionInfo extends AjaxPage {

		public function view() {
			$sectionManager = new SectionManager(Administration::instance());
			$fieldManager = new FieldManager(Administration::instance());

			// Fetch sections & populate a dropdown with the available upload fields
			$section = $sectionManager->fetch($_GET['section']);

			foreach($section->fetchFields() as $field) {
				if(!preg_match(Extension_BulkImporter::$supported_fields['upload'], $field->get('type'))) continue;

				$element = new XMLElement("field", General::sanitize($field->get('label')), array(
					'id' => $field->get('id'),
					'type' => $field->get('type')
				));

				$this->_Result->appendChild($element);
			}

			// Check to see if any Sections link to this using the Section Associations table
			$associations = Symphony::Database()->fetch(sprintf("
					SELECT
						`child_section_field_id`
					FROM
						`tbl_sections_association`
					WHERE
						`parent_section_id` = %d
				", Symphony::Database()->cleanValue($_GET['section'])
			));

			if(is_array($associations) && !empty($associations)) foreach($associations as $related_field) {
				$field = $fieldManager->fetch($related_field['child_section_field_id']);

				if(!preg_match(Extension_BulkImporter::$supported_fields['section'], $field->get('type'))) continue;

				$element = new XMLElement("section", General::sanitize($field->get('label')), array(
					'id' => $field->get('id'),
					'type' => $field->get('type'),
					'section' => $sectionManager->fetch($field->get('parent_section'))->get('name')
				));

				$this->_Result->appendChild($element);
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
					", Symphony::Database()->cleanValue($_GET['section'])
				));

				if(is_array($associations) && !empty($associations)) foreach($associations as $related_field) {
					$field = $fieldManager->fetch($related_field['field_id']);

					if(!preg_match(Extension_BulkImporter::$supported_fields['section'], $field->get('type'))) continue;

					$element = new XMLElement("section", General::sanitize($field->get('label')), array(
						'id' => $field->get('id'),
						'type' => $field->get('type'),
						'section' => $sectionManager->fetch($field->get('parent_section'))->get('name')
					));

					$this->_Result->appendChild($element);
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
					", Symphony::Database()->cleanValue($_GET['section'])
				));

				if(is_array($associations) && !empty($associations)) foreach($associations as $related_field) {
					$field = $fieldManager->fetch($related_field['field_id']);

					if(!preg_match(Extension_BulkImporter::$supported_fields['section'], $field->get('type'))) continue;

					$element = new XMLElement("section", General::sanitize($field->get('label')), array(
						'id' => $field->get('id'),
						'type' => $field->get('type'),
						'section' => $sectionManager->fetch($field->get('parent_section'))->get('name')
					));

					$this->_Result->appendChild($element);
				}
			}

		}

	}
