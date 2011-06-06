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
		public $entries = array();

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function about() {
			return array(
				'name'			=> __('Bulk Importer'),
				'version'		=> '0.9.3pre3',
				'release-date'	=> 'unreleased',
				'author'		=> array(
					array(
						'name'			=> 'Brendan Abbott',
						'website'		=> 'http://www.bloodbone.ws',
						'email'			=> 'brendan@bloodbone.ws'
					),
					array(
						'name'			=> 'Marcin Konicki',
						'email'			=> 'ahwayakchih@neoni.net'
					)
				),
				'description'	=> __('Imports an archive of files into a chosen section as individual entries with the option to link the newly created entries with another entry')
			);
		}

		public function install() {
			if (!Symphony::Database()->query('
				CREATE TABLE IF NOT EXISTS tbl_bulkimporter_fields (
					`field_id` int unsigned,
					`section_id` int unsigned,
					PRIMARY KEY (`field_id`),
					INDEX section_id (`section_id`)
				)
			')) return false;

			if (file_exists(WORKSPACE.$this->target)) return true;
			return General::realiseDirectory(WORKSPACE.$this->target);
		}

		public function update($previousVersion=false) {
			if (!$this->install()) return false;

			return true;
		}

		public function uninstall() {
			return true;
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'preferences'
				),
				array(
					'page' => '/backend/',
					'delegate' => 'InitaliseAdminPageHead',
					'callback' => 'initaliseAdminPageHead'
				),
				array(
					'page' => '/blueprints/sections/',
					'delegate' => 'FieldPostCreate',
					'callback' => 'fieldPostEdit',
				),
				array(
					'page' => '/blueprints/sections/',
					'delegate' => 'FieldPostEdit',
					'callback' => 'fieldPostEdit',
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
				$page->addStylesheetToHead(URL . '/extensions/bulkimporter/assets/bulkimporter.import.css','screen', 100);
				$page->addScriptToHead(URL . '/extensions/bulkimporter/assets/bulkimporter.import.js', 101);
			}
			else {
				$callback = Symphony::Engine()->getPageCallback();

				if (!is_array($callback['context'])) return;

				if ($callback['driver'] == 'publish' && !empty($callback['context']['section_handle']) && !empty($callback['context']['entry_id'])) {
					$sm = new SectionManager(Symphony::Engine());
					$section_id = $sm->fetchIDFromHandle($callback['context']['section_handle']);
					$values = Symphony::Database()->fetch('
						SELECT *
						FROM tbl_bulkimporter_fields
						WHERE `section_id` = '.intval($section_id),
						'field_id'
					);
					if (!empty($values)) {
						Administration::instance()->Page->addElementToHead(
							new XMLElement(
								'script',
								"Symphony.Context.add('bulkimporter', " . json_encode(array('fields' => $values)) . ");",
								array('type' => 'text/javascript')
							), 100
						);
						$page->addStylesheetToHead(URL . '/extensions/bulkimporter/assets/bulkimporter.publish.css','screen', 200);
						$page->addScriptToHead(URL . '/extensions/bulkimporter/assets/bulkimporter.publish.js', 201);
					}
				}
				else if ($callback['driver'] == 'blueprintssections') {
					if ($callback['context'][0] == 'edit' && is_numeric($callback['context'][1])) {
						$values = Symphony::Database()->fetch('
							SELECT *
							FROM tbl_bulkimporter_fields
							WHERE `section_id` = '.intval($callback['context'][1]),
							'field_id'
						);
						if (!empty($values)) {
							Administration::instance()->Page->addElementToHead(
								new XMLElement(
									'script',
									"Symphony.Context.add('bulkimporter', " . json_encode(array('fields' => $values)) . ");",
									array('type' => 'text/javascript')
								), 100
							);
						}
					}

					//$page->addStylesheetToHead(URL . '/extensions/bulkimporter/assets/bulkimporter.settings.css','screen', 200);
					$page->addScriptToHead(URL . '/extensions/bulkimporter/assets/bulkimporter.settings.js', 201);
				}
			}
		}

		public function fieldPostEdit($ctx) {
			// context array contains: &$field, &$data
			if (!isset($ctx['data']) || !in_array($ctx['data']['type'], array('subsectionmanager'/* TODO: add other supported field types here */))) return;

			$fields = array(
				'field_id' => intval($ctx['field']->get('id')),
				'section_id' => intval($ctx['field']->get('parent_section')),
			);

			Symphony::Database()->query("DELETE FROM tbl_bulkimporter_fields WHERE section_id = {$fields['section_id']} AND field_id = {$fields['field_id']}");
			if (is_array($ctx['data']['bulkimporter']) && $ctx['data']['bulkimporter']['enabled'] == 'Yes') {
				Symphony::Database()->insert($fields, 'tbl_bulkimporter_fields');
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
						if(in_array($file,	array('.', '..', '__MACOSX'))) continue;

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
		 *	True if the $files array is not empty, false otherwise
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

		/**
		 * Creates a new entry for each valid file in the `$target_section`
		 */
		public function commitFiles() {
			$entryManager = new EntryManager(Administration::instance());
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
					$path = dirname(substr($file->location, strlen($this->extracted_directory)));
					if ($path != '/') $path .= '/';
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

				// Set the Name
				if(!is_null($fields['name'])) {
					$_post[$fields['name']->get('element_name')] = $file->name;
				}

				// Set the Upload Field
				if(is_null($fields['upload'])) {
					throw new Exception(__('No valid upload field found in the <code>%s</code>', array($section->get('name'))));
				}

				$_post[$this->target_field->get('element_name')] = $final_destination;

				// Cache some info, before we move file
				// https://github.com/brendo/bulkimporter/pull/7#issuecomment-1105691
				$meta = array(
					'size' => $file->size,
					'mimetype' => $file->mimetype,
					'meta' => serialize($this->target_field->getMetaInfo($file->location, $file->mimetype))
				);

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
				if(__ENTRY_FIELD_ERROR__ == $entry->checkPostData($_post, $errors)) {
					if (!empty($errors)) $file->setErrors($errors);
					continue;
				}

				if(__ENTRY_OK__ == $entry->setDataFromPost($_post, $errors, false, false)) {
					//	Because we can't upload the file using the inbuilt function
					//	we have to fake the expected output
					$upload = $entry->getData($this->target_field->get('id'));
					foreach ($meta as $key => $value) {
						if (empty($upload[$key])) $upload[$key] = $value;
					}
					$entry->setData($this->target_field->get('id'), $upload);

					/**
					 * Just prior to creation of an Entry
					 *
					 * @delegate EntryPreCreate
					 * @param string $context
					 * '/publish/new/'
					 * @param Section $section
					 * @param Entry $entry
					 * @param array $fields
					 */
					Symphony::ExtensionManager()->notifyMembers('EntryPreCreate', '/publish/new/', array('section' => $section, 'entry' => &$entry, 'fields' => &$_post));

					if($entry->commit()) {
						$file->hasUploaded();
						$entries[$final_destination] = $entry->get('id');

						/**
						 * Creation of an Entry. New Entry object is provided.
						 *
						 * @delegate EntryPostCreate
						 * @param string $context
						 * '/publish/new/'
						 * @param Section $section
						 * @param Entry $entry
						 * @param array $fields
						 */
						Symphony::ExtensionManager()->notifyMembers('EntryPostCreate', '/publish/new/', array('section' => $section, 'entry' => $entry, 'fields' => $_post));
					}
				}
				else {
					$file->setErrors(__('Could not save entry in the <code>%s</code>', array($section->get('name'))));
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

			$this->entries = $entries;
		}
	}
