<?php

	class Extension_BulkImporter extends Extension {
		
		protected $target = '/uploads/bulkimporter';
		protected $supported_fields = array('upload');
		
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		public function about() {
			return array(
				'name'			=> 'Bulk Importer',
				'version'		=> '0.1',
				'release-date'	=> '2009-08-27',
				'author'		=> array(
					'name'			=> 'Brendan Abbott',
					'website'		=> 'http://www.bloodbone.ws',
					'email'			=> 'brendan@bloodbone.ws'
				),
				'description'	=> 'Imports an archive of images into a chosen section as individual entries.'
	 		);
		}
		
		public function uninstall() {
			$this->_Parent->Configuration->remove('bulkimporter');
		}
		
		public function install() {
			if (file_exists(WORKSPACE.$this->upload)) return true;
			if (!General::realiseDirectory(WORKSPACE.$this->upload)) ? return false : return true;
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/system/bulk-importer/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'bulkimport'
				));
		}

		
		public function fetchNavigation() {
			return array(
				array(
					'location'	=> 200,
					'name'	=> 'Bulk Importer',
					'link'	=> '/bulk-importer/'
				)
			);
		}		
			
	/*-------------------------------------------------------------------------
		Utility functions:
	-------------------------------------------------------------------------*/
		public function getTarget() {
			return $this->upload;
		}
		
		public function getValidFields() {
			return $this->supported_fields;
		}
	}
?>