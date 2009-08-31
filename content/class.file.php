<?php

	class BI_File {
		protected $file;
		protected $loc;
		protected $valid = '/\.(?:bmp|gif|jpe?g|png)$/i';
		public $isValid = false;
		public $isUploaded = false;

		public function __construct($file,$loc) {
			$this->file = $file;
			$this->loc = $loc;

			$this->isValid = $this->isValid();
		}

		public function hasUploaded() {
			$this->isUploaded = true;
		}

		public function get($q = 'name') {
			switch ($q) {
				case "ext":
					return General::getExtension($this->file);
					break;
				case "loc":
					return $this->loc;
					break;
				case "uploaded":
					return $this->isUploaded;
					break;
				default:
					return $this->file;
			}
		}

		public function isValid() {
			return General::validateString($this->file, $this->valid);
		}
	}