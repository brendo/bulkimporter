<?php
	require_once('content/class.file.php');

	class Extension_BulkImporter extends Extension {

		protected $target = '/uploads/bulkimporter';
		protected $supported_fields = array('upload');
		protected $exempt = array(".","..","__MACOSX");
		public $targetSection = null;
		public $files = array();

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
			return General::realiseDirectory(WORKSPACE.$this->upload);
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'preferences'
				)
			);
		}


		public function fetchNavigation() {
			return array(
				array(
					'location'	=> 200,
					'name'	=> 'Bulk Importer',
					'link'	=> '/import/'
				)
			);
		}

	/*-------------------------------------------------------------------------
		Utility functions:
	-------------------------------------------------------------------------*/
		public function getTarget() {
			return WORKSPACE . $this->target . "/" . $this->targetSection->get('handle') . "/";
		}

		public function getSupportedFields() {
			return $this->supported_fields;
		}

		public function beginProcess() {
			if(empty($_FILES)) return false;

			foreach($_FILES['fields']['error'] as $key => $error) {
				if ($error == UPLOAD_ERR_OK) {
					$tmp = $_FILES['fields']['tmp_name'][$key];

					$target = $this->getTarget().DateTimeObj::get('d-m-y');
					$file = DateTimeObj::get('h-i-s') . "-" . $_FILES['fields']['name'][$key];

					if(!file_exists($target)) {
						General::realiseDirectory($target);
					}

					if(!General::uploadFile($target,$file,$tmp)) return false;
				}
			}

			/* Makes this easier */
			$uploaded = $target . "/" . $file;

			/* 	Open zip file */
			$extracted = $target . "/" . DateTimeObj::get('h-i-s');
			$zipManager = new ZipArchive;

			$zip = $zipManager->open($uploaded);
			$zipManager->extractTo($extracted);
			$zipManager->close();

			/*	Tidy up */
			General::deleteFile($uploaded);

			/* 	Add the extracted files to the $files array */
			$this->openExtracted($extracted);

			return (bool)count($this->files) != 0;
		}


		public function openExtracted($folder) {
			if ($extractManager = opendir($folder)) {
				if(is_dir($extractManager) === FALSE) {
					while(($file = readdir($extractManager)) !== FALSE) {
						if(!in_array($file,$this->exempt)) {
							$this->files[] = new BI_File($file);
						}
					}
					closedir($extractManager);
				} else {
					$this->openExtracted($folder);
				}
			}
		}

		/*	Creates a new entry foreach valid file in the $targetSection */
		public function commitFiles() {
			foreach($this->files as $file) {
				if($file->isValid) {
					/* Add new entry */
					echo "wootah: adding";
				}
			}
		}
	}
?>