<?php
	require_once('content/class.file.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');

	class Extension_BulkImporter extends Extension {

		protected $_Parent = null;
		protected $target = '/uploads/bulkimporter';
		protected $supported_fields = array('upload');
		protected $exempt = array(".","..","__MACOSX");

		public $uploaded_target = null;
		public $target_section = null;
		public $linked_entry = null;
		public $files = array();


	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		public function about() {
			return array(
				'name'			=> 'Bulk Importer',
				'version'		=> '0.2',
				'release-date'	=> '2009-09-02',
				'author'		=> array(
					'name'			=> 'Brendan Abbott',
					'website'		=> 'http://www.bloodbone.ws',
					'email'			=> 'brendan@bloodbone.ws'
				),
				'description'	=> 'Imports an archive of images into a chosen section as individual entries.'
	 		);
		}

		public function uninstall() {
			return true;
		}

		public function install() {
			if (file_exists(WORKSPACE.$this->target)) return true;
			return General::realiseDirectory(WORKSPACE.$this->target);
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'preferences'
				),
				array(
		       	 	'page'    => '/backend/',
			        'delegate'  => 'InitaliseAdminPageHead',
			        'callback'  => 'initaliseAdminPageHead'
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

		public function initaliseAdminPageHead($context) {
			$page = $context['parent']->Page;

			if ($page instanceof contentExtensionBulkImporterImport) {
				$page->addScriptToHead(URL . '/extensions/bulkimporter/assets/bi.ajaxify.js',100100992);
				$page->addStylesheetToHead(URL . '/extensions/bulkimporter/assets/bi.default.css','screen', 100100992);
			}
	    }

	/*-------------------------------------------------------------------------
		Utility functions:
	-------------------------------------------------------------------------*/
		public function getTarget() {
			return WORKSPACE . $this->target . "/" . $this->target_section->get('handle') . "/";
		}

		public function getSupportedFields() {
			return $this->supported_fields;
		}

		public function openExtracted($folder) {
			if ($extractManager = opendir($folder)) {
				if(is_dir($extractManager) === FALSE) {
					while(($file = readdir($extractManager)) !== FALSE) {
						if(!in_array($file,$this->exempt)) {
							$this->files[] = new BI_File($file,$folder);
						}
					}
					closedir($extractManager);
				} else {
					$this->openExtracted($extractManager);
				}
			}
		}

		/* 	Inbuild Symphony rmdirr doesn't work..
		**	This was taken from http://au.php.net/rmdir  */
		function deleteDirectory($dir) {
		    if (!file_exists($dir)) return true;
		    if (!is_dir($dir)) return unlink($dir);
		    foreach (scandir($dir) as $item) {
		        if ($item == '.' || $item == '..') continue;
		        if (!$this->deleteDirectory($dir.DIRECTORY_SEPARATOR.$item)) return false;
		    }
		    return rmdir($dir);
		}

	/*-------------------------------------------------------------------------*/

		public function beginProcess() {
			if(empty($_FILES)) return false;

			foreach($_FILES['fields']['error'] as $key => $error) {
				if ($error == UPLOAD_ERR_OK) {
					$tmp = $_FILES['fields']['tmp_name'][$key];

					$target = $this->getTarget() . DateTimeObj::get('d-m-y');
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
			$this->uploaded_target = $extracted;
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

		public function cleanUp($log) {
			$this->deleteDirectory($this->uploaded_target);

			/* Write a logfile */
			$file = $this->getTarget() . "log.txt";

			$entry = implode(" :: ", array(
                                DateTimeObj::get('dS M, Y \a\t h:ia'),
                                $this->target_section->get('name'),
                                $log[0] . " uploaded",
                                $log[1] . " failed")) . "\r\n";

			if(!$handle = fopen($file, 'a+')) return false;

			if(fwrite($handle,$entry) === FALSE) return false;

			fclose($handle);

			return true;
		}

		/*	Creates a new entry foreach valid file in the $target_section */
		public function commitFiles($parent) {
			$this->_Parent = $parent;
			$entryManager = new EntryManager($this->_Parent);

			foreach($this->files as $file) {
				if($file->isValid) {

					$entry = $entryManager->create();
					$entry->set('section_id',$this->target_section->get('id'));
					$entry->set('author_id', $parent->Author->get('id'));

					$section = $this->target_section;
					/*	Get the sections fields and add
					**
					**	TODO:		allow all fields to be filled with data
					**	CURRENT:	name, upload, 1 linked field ('bilink')
					*/
					$_data = $_post = array();
					$_data[] = $this->target_section->get('id');

					foreach($section->fetchFields() as $field) {

						switch($field->get('type')) {
							case "textbox":
								$_data[$field->get('element_name')] = $file->get("name");
								break;

							case "bilink":
								$_data[$field->get('element_name')] = array($this->linked_entry["linked-entry"]);
								break;

							case "upload":
								$full_file = $file->get('loc') . "/" . $file->get();
								$final_destination = preg_replace("/^\/workspace/", '', $field->get('destination')) .
													'/' . DateTimeObj::get('d-m-y-h-m') .
													'/' . $file->get();

								$_data[$field->get('element_name')] = array(
									'name' => $file->get(),
									'type' => $file->get('ext'),
									'tmp_name' => $full_file,
									'error' => 0,
									'size' => filesize($full_file)
								);

								/*	Because we can't upload the file using the inbuilt function
								**	we have to fake the expected output */
								$_post[$field->get('element_name')] = array(
									'file' => $final_destination,
									'size' => $_data[$field->get('element_name')]['size'],
									'mimetype' => $_data[$field->get('element_name')]['type'],
									'meta' => serialize(
												$field->getMetaInfo($final_destination,
												$_data[$field->get('element_name')]['type'])
											)
								);

								/* Move the image from it's bulk-imported location */
								General::realiseDirectory(
										DOCROOT . $field->get('destination') .
										'/' . DateTimeObj::get('d-m-y-h-m')
								);
								chmod(DOCROOT . $field->get('destination') . '/' . DateTimeObj::get('d-m-y-h-m'), intval(0755, 8));

								if(rename(
									$file->get('loc') . "/" . $file->get(),
									DOCROOT . "/workspace" . $final_destination
									)) {
									chmod(DOCROOT . "/workspace" . $final_destination, intval(0755, 8));
								}

								break;
						}

					}
					$errors = array();

					/*	Check all the fields that they are correct */
					if(__ENTRY_FIELD_ERROR__ == $entry->checkPostData($_data,$errors)) {
						continue;
					}

					/*	Okay, so all the fields have been validated individually, we are pretty much
					**	right to push the data into the database
					*/
					$_post = array_merge($_data,$_post);

					if(__ENTRY_OK__ == $this->setDataFromPost($entry, $_post, $errors, false, false)) {
						if($entry->commit()){
							$file->hasUploaded();
						}
					}

				}
			}

			return true;
		}

		/*	Can't use the full Symphony set because field->processRawData will fail as the uploaded file
		**	returns false, because it's not uploaded in this step, it's already been extracted.
		*/
		private function setDataFromPost($entry, $data, &$error, $simulate=false, $ignore_missing_fields=false){

			$error = NULL;

			$status = __ENTRY_OK__;

			// Entry has no ID, create it:
			if(!$entry->get('id') && $simulate == false) {

				$entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
				$entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));

				Symphony::Database()->insert($entry->get(), 'tbl_entries');
				if(!$entry_id = Symphony::Database()->getInsertID()) return __ENTRY_FIELD_ERROR__;
				$entry->set('id', $entry_id);
			}

			$entryManager = new EntryManager($this->_Parent);
			$section = $this->target_section;
			$schema = $section->fetchFieldsSchema();

			foreach($schema as $info){
				$result = NULL;
				$field = $entryManager->fieldManager->fetch($info['id']);

				if($ignore_missing_fields && !isset($data[$field->get('element_name')])) continue;

				/*	If this field is an upload, don't call it's inherit functions, as they'll fail */
				if(!in_array($field->get('type'),$this->getSupportedFields())) {

					$result = $field->processRawFieldData(
						(isset($data[$info['element_name']]) ? $data[$info['element_name']] : NULL), $s, $m, false, $entry->get('id')
					);

					if($s != Field::__OK__){
						$status = __ENTRY_FIELD_ERROR__;
						$error = array('field_id' => $info['id'], 'message' => $m);
					}

				} else {
					$status = __ENTRY_OK__;
					$result = $data[$field->get('element_name')];
				}

				$entry->setData($info['id'], $result);
			}

			// Failed to create entry, cleanup
			if($status != __ENTRY_OK__ and !is_null($entry_id)) {
				Symphony::Database()->delete('tbl_entries', " `id` = '$entry_id' ");
			}

			return $status;
		}
	}
?>