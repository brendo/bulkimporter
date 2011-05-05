<?php
	require_once('lib/class.file.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');

	class Extension_BulkImporter extends Extension {

		protected $_Parent = null;

		protected static $target = '/uploads/bulkimporter';
		public $extracted_directory = null;
		public $extracted_archive = null;

		public static $supported_fields = array(
			'upload' => '/upload/i',
			'name' => '/textbox|input/i',
			'section' => '/selectbox_link|referencelink|subsectionmanager|bilink/i'
		);

		public $preserve_subdirectories = false;
		public $archive_is_parent = false;

		public $target_section = null;
		public $target_field = null;
		public $linked_entry = null;
		public $files = array();

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		public function about() {
			return array(
				'name'			=> __('Bulk Importer'),
				'version'		=> '0.9.2',
				'release-date'	=> '2010-03-15',
				'author'		=> array(
					'name'			=> 'Brendan Abbott',
					'website'		=> 'http://www.bloodbone.ws',
					'email'			=> 'brendan@bloodbone.ws'
				),
				'description'	=> __('Imports an archive of files into a chosen section as individual entries
				with the option to link the newly created entries with another entry')
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
					'location'	=> __('System'),
					'name'	=> __('Bulk Importer'),
					'link'	=> '/import/'
				)
			);
		}

		public function initaliseAdminPageHead($context) {
			$page = $context['parent']->Page;

			if ($page instanceof contentExtensionBulkImporterImport) {
				$page->addStylesheetToHead(URL . '/extensions/bulkimporter/assets/default.css','screen', 100);
				$page->addScriptToHead(URL . '/extensions/bulkimporter/assets/default.js', 101);
			}
	    }

	/*-------------------------------------------------------------------------
		Utility functions:
	-------------------------------------------------------------------------*/
		public function getTarget() {
			return WORKSPACE . Extension_BulkImporter::$target . "/";
		}

		/**
		 * Given a directory, recursively find all the files and add them to
		 * the $this->files if they aren't a hidden directory.
		 *
	     * @param string $dir
	 	 * @return boolean
		 */
		public function openExtracted($dir) {
			if (PHP_VERSION_ID >= 50300) {
				try {
					$files = new RecursiveIteratorIterator(
					    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
					    RecursiveIteratorIterator::CHILD_FIRST
					);

					foreach($files as $file) {
						if($file->isDir() || preg_match('/^\./', $file->getFilename())) continue;

						$this->files[] = new BulkImporterFile($file);
					}
				}
				catch (Exception $ex) {
					return false;
				}
			}
			// PHP 5.2.x fallback
			else {
				if ($extractManager = opendir($dir)) {
					while(($file = readdir($extractManager)) !== FALSE) {
						if(in_array($file,  array('.', '..', '__MACOSX'))) continue;

						$file = $dir . '/' . $file;
						if(is_dir($file)) {
							$this->openExtracted($file);
						}
						else {
							$this->files[] = new BulkImporterFile(new SplFileInfo($file));
						}
					}
					closedir($extractManager);
				}
			}

			return true;
		}

		/**
		 * Recursively deletes all files and the directories given a parent
		 * directory. Taken from StackOverflow
		 *
		 * @link http://stackoverflow.com/questions/3338123/how-do-i-recursively-delete-a-directory-and-its-entire-contents-filessub-dirs/3352564#3352564
		 * @param string $dir
		 * @return boolean
		 */
		public static function deleteDirectory($dir) {
			if (PHP_VERSION_ID >= 50300) {
				try {
					$files = new RecursiveIteratorIterator(
					    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
					    RecursiveIteratorIterator::CHILD_FIRST
					);

					foreach ($files as $fileinfo) {
						if($fileinfo->getFilename() == "log.txt") continue;

					    $remove = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
					    $remove($fileinfo->getRealPath());
					}

					// Remove current directory
					rmdir($dir);
				}
				catch (Exception $ex) {
					return false;
				}
			}
			else {
				if (!file_exists($dir)) return true;

				if (!is_dir($dir)) return unlink($dir);

				foreach (scandir($dir) as $item) {
					if ($item == '.' || $item == '..') continue;
					if (!Extension_BulkImporter::deleteDirectory($dir.DIRECTORY_SEPARATOR.$item)) return false;
				}

				return rmdir($dir);
			}
			return true;
		}

	/*-------------------------------------------------------------------------*/

		/**
		 * Uploads the zip file to a target directory using the current date. The function
		 * then extracts the content of the zip to the same folder, removes the zip file
		 * after extraction and calls the openExtracted function to append the files to the
		 * $files array.
		 *
		 * @return boolean
		 *  True if the $files array is not empty, false otherwise
		 */
		public function beginProcess() {
			if(empty($_FILES['fields']['name']['file'])) return false;

			$target = $this->getTarget() . DateTimeObj::get('d-m-Y');

			foreach($_FILES['fields']['error'] as $key => $error) {
				if ($error == UPLOAD_ERR_OK) {
					$tmp = $_FILES['fields']['tmp_name'][$key];

					// Upload files to /workspace/uploads/bulkimporter/11-11-2010
					$file = $_FILES['fields']['name'][$key];

					if(!file_exists($target)) General::realiseDirectory($target);

					if(!General::uploadFile($target,$file,$tmp)) return false;

					$uploadedZipPath = $target . "/" . $file;
				}
			}

			$zipManager = new ZipArchive;
			$zip = $zipManager->open($uploadedZipPath);

			// The directory where the extracted zip contents should go to.
			$this->extracted_directory = $target;

			$path = '';
			if ($this->archive_is_parent) {
				$path = '/' . preg_replace('/\.[^\.]+$/', '', basename($uploadedZipPath));
				$this->extracted_archive = basename($path);
				if(!file_exists($this->extracted_directory . $path)) General::realiseDirectory($this->extracted_directory . $path);
			}

			$zipManager->extractTo($this->extracted_directory . $path);
			$zipManager->close();

			// Delete the zip file
			General::deleteFile($uploadedZipPath);

			// Add the extracted files to the $files array
			$this->openExtracted($this->extracted_directory);

			return count($this->files) != 0;
		}

		public function cleanUp($log) {
			$this->deleteDirectory($this->extracted_directory);

			$entry = implode(" :: ", array(
				DateTimeObj::get(__SYM_DATETIME_FORMAT__),
				$this->target_section->get('name'),
				$log[0] . " uploaded",
				$log[1] . " failed"
			)) . PHP_EOL;

			return (boolean)file_put_contents($this->getTarget() . "log.txt", $entry, FILE_APPEND);
		}

		/*	Creates a new entry foreach valid file in the $target_section */
		public function commitFiles($parent) {
			$this->_Parent = $parent;
			$entryManager = new EntryManager($this->_Parent);
			$section = $this->target_section;

			// This is the default field instances that will populated with data.
			$entries = array();
			$fields = array(
				'upload' => $this->target_field,
				'name' => null,
				'section' => null
			);

			foreach($section->fetchFields() as $field) {
				if(
					General::validateString($field->get('type'), Extension_BulkImporter::$supported_fields['name']) &&
					is_null($fields['name'])
				) {
					$fields['name'] = $field;
				}

				if(
					General::validateString($field->get('type'), Extension_BulkImporter::$supported_fields['section']) &&
					is_null($fields['section'])
				) {
					$fields['section'] = $field;
				}
			}

			foreach($this->files as $file) {
				$path = '/';
				if ($this->preserve_subdirectories) {
					$path = dirname(substr($file->location, strlen($this->extracted_directory))) . '/';
				}
				else if ($this->archive_is_parent) {
					$path = '/' . $this->extracted_archive . '/';
				}

				$final_destination = preg_replace("/^\/workspace/", '', $this->target_field->get('destination')) . $path . $file->rawname;
				if(!$file->isValid($this->target_field, $final_destination)) continue;

				$_post = array();
				$entry = $entryManager->create();
				$entry->set('section_id', $section->get('id'));
				$entry->set('author_id', Administration::instance()->Author->get('id'));

				// TODO: was this needed for anything?
				//$_data[] = $section->get('id');

				// Set the Name
				if(!is_null($fields['name'])) {
					$_post[$fields['name']->get('element_name')] = $file->name;
				}

				// Set the Upload Field
				if(is_null($fields['upload'])) {
					throw new Exception(__('No valid upload field found in the <code>%</code>', array($section->get('name'))));
				}

				$_post[$this->target_field->get('element_name')] = $final_destination;

				// Move the image from it's bulk-imported location
				$path = WORKSPACE . dirname($final_destination);
				if(!file_exists($path)) {
					General::realiseDirectory($path);
					chmod($path, intval(0755, 8));
				}

				if(rename($file->location, WORKSPACE . $final_destination)) {
					chmod(WORKSPACE . $final_destination, intval(0755, 8));
				}

				$errors = array();

				//	Check all the fields that they are correct
				if(__ENTRY_FIELD_ERROR__ == $entry->checkPostData($_post, $errors)) continue;

				if(__ENTRY_OK__ == $entry->setDataFromPost($_post, $errors, false, false)) {
					//	Because we can't upload the file using the inbuilt function
					//	we have to fake the expected output
					$upload = $entry->getData($this->target_field->get('id'));
					if (empty($upload['size'])) $upload['size'] = $file->size;
					if (empty($upload['mimetype'])) $upload['mimetype'] = $file->mimetype;
					if (empty($upload['meta'])) $upload['meta'] = serialize($this->target_field->getMetaInfo(WORKSPACE . $upload['file'], $file->mimetype));
					$entry->setData($this->target_field->get('id'), $upload);

					if($entry->commit()) {
						$file->hasUploaded();
						$entries[] = $entry->get('id');
					}
				}
			}

			// Set the Section Association
			if(!empty($entries) && !is_null($this->linked_entry['linked-entry'])) {
				$entry = current($entryManager->fetch($this->linked_entry['linked-entry']));

				// Linked field, process the array of ID's to add
				$field = $entryManager->fieldManager->fetch($this->linked_entry['linked-field']);
				$result = $field->processRawFieldData($entries, $s, false, $entry->get('id'));

				// Get the current linked entries and merge with the new ones
				$existing_values = $entry->getData($this->linked_entry['linked-field']);
				if(is_array($existing_values['relation_id'])) {
					$result['relation_id'] = array_merge_recursive($result['relation_id'], $existing_values['relation_id']);
				}

				$entry->setData($this->linked_entry['linked-field'], $result);
				$entry->commit();
			}
		}
	}

