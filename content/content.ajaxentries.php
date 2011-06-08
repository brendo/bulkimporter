<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.entrymanager.php');

	class contentExtensionBulkImporterAjaxEntries extends AjaxPage {

		public function view() {
			$entryManager = new EntryManager(Administration::instance());

			$field = $entryManager->fieldManager->fetch($_GET['field']);
			$section = $entryManager->sectionManager->fetch($field->get('parent_section'));

			$entry_column = current($section->fetchVisibleColumns());

			//	Display the first column from every entry in the linked section
			$entries = $entryManager->fetch(null, $field->get('parent_section'));

			foreach($entries as $entry) {
				$values = $entry->getData($entry_column->get('id'));

				$el = new XMLElement("entry", General::sanitize($values['value']));
				$el->setAttribute('id', $entry->get('id'));

				$this->_Result->appendChild($el);
			}
		}

	}

