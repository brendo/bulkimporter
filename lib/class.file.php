<?php

	class BulkImporterFile {
		public $file;
		protected $name;
		protected $location;
		protected $imported = false;
		protected $valid = true;

		public function __construct(SplFileInfo $file) {
			$this->file = $file;
			$this->location = $file->getRealPath();

			$this->name = preg_replace(
				'/\.' . preg_quote(General::getExtension($file)) . '$/',
				null, $file->getFilename()
			);
		}

		/**
		 * If the file's entry has been committed to Symphony successfully,
		 * this function will be called
		 */
		public function hasUploaded() {
			$this->imported = true;
		}

		/**
		 * Check that the file matches the validator as specified by the field
		 *
		 * @param Field $field
		 * @return boolean
		 */
		public function isValid(Field $field) {
			if(is_null($field->get('validator'))) return $this->valid;

			$this->valid = General::validateString($this->extension, $field->get('validator'));

			return $this->valid;
		}

		public function __get($name) {
			if($name == "extension") {
				return "." . General::getExtension($this->file);
			}
			
			if($name == "mimetype") {
				return mime_content_type($this->location);
			}

			if($name == "size") {
				return $this->file->getSize();
			}
			
			if($name == "rawname") {
				return $this->file->getFilename();
			}

			return $this->$name;
		}
	}