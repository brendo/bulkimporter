<?php

	class BI_File {
		protected $file;
		protected $valid = '/\.(?:bmp|gif|jpe?g|png)$/i';
		public $isValid = false;

		public function __construct($file) {
			$this->file = $file;

			$this->isValid = $this->isValid();
		}

		public function isValid() {
			return General::validateString($file, $this->valid);
		}
	}