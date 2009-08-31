<?php
	require_once('content/class.file.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');

	class Extension_BulkImporter extends Extension {

		protected $target = '/uploads/bulkimporter';
		protected $supported_fields = array('upload');
		protected $exempt = array(".","..","__MACOSX");
		public $targetSection = null;
		public $files = array();
		protected $_Parent = null;

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
							$this->files[] = new BI_File($file,$folder);
						}
					}
					closedir($extractManager);
				} else {
					$this->openExtracted($folder);
				}
			}
		}

		/*	Creates a new entry foreach valid file in the $targetSection */
		public function commitFiles($parent) {
			$this->_Parent = $parent;
			$entryManager = new EntryManager($this->_Parent);

			foreach($this->files as $file) {
				if($file->isValid) {

					$entry = $entryManager->create();
					$entry->set('section_id',$this->targetSection->get('id'));
					$entry->set('author_id', $parent->Author->get('id'));

					$section = $this->targetSection;
					/*	Get the sections fields and add
					**
					**	TODO:		allow all fields to be filled with data
					**	CURRENT:	name,upload
					*/
					$_data = $_post = array();
					$_data[] = $_post[] = $this->targetSection->get('id');

					foreach($section->fetchFields() as $field) {

						switch($field->get('type')) {
							case "textbox":
								$_data[$field->get('element_name')] = $_post[$field->get('element_name')] = $file->get();
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

								if(rename(
									$file->get('loc') . "/" . $file->get(),
									DOCROOT . "/workspace/" . $final_destination
									)) {
									chmod(DOCROOT . "/workspace/" . $final_destination, intval(0755, 8));
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
			$section = $this->targetSection;
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