<?php

	class File {
		protected $file;
		protected $valid = '/\.(?:bmp|gif|jpe?g|png)$/i';
		
		public function __construct($file) {
			$this->file = $file;
			
			return $this->isValid();
		}
		
		public function isValid() {
			return preg_match($this->valid, $file);
		}
	} 