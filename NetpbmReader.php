<?php
	/**
	 * NetpbmReader
	 * Copyright (c) 2014 Vladimir P.
	 * 
	 * Permission is hereby granted, free of charge, to any person obtaining a copy
	 * of this software and associated documentation files (the "Software"), to deal
	 * in the Software without restriction, including without limitation the rights
	 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	 * copies of the Software, and to permit persons to whom the Software is
	 * furnished to do so, subject to the following conditions:
	 * 
	 * The above copyright notice and this permission notice shall be included in
	 * all copies or substantial portions of the Software.
	 * 
	 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	 * THE SOFTWARE.
	 */
	
	final class NetpbmReader {
		// Creates a Gd image resource from data encoded in Netpbm (PNM) format.
		public static function createGdImage($data) {
			$scanner = new NetpbmDataScanner($data);
			
			$magicNumber = $scanner->nextWord();
			if($magicNumber === null)
				throw new \RuntimeException('File does not have a magic number!');
			
			$version = self::getFormatVersion($magicNumber);
			if($version < 0)
				throw new \RuntimeException('Unsupported file format!');
			
			$width = $scanner->nextUnsignedInteger();
			$height = $scanner->nextUnsignedInteger();
			if($width === null || $height === null)
				throw new \RuntimeException('File does not have width and/or height specified!');
			if($width < 0 || $height < 0)
				throw new \RuntimeException('Invalid image dimensions!');
			
			$maxValue = null;
			if($version >= 2 && $version <= 6 && $version !== 4) {
				$maxValue = $scanner->nextUnsignedInteger();
				if($maxValue === null)
					throw new \RuntimeException('File does not have max value specified!');
				if($maxValue <= 0)
					throw new \RuntimeException('Invalid max value!');
			}
			
			$image = imageCreateTrueColor($width, $height);
			
			$imageData = '';
			if($version === 1)
				$imageData = self::packDigits($scanner->getRemainingData());
			elseif($version === 4)
				$imageData = self::packBits($scanner->getRemainingData(), $width);
			elseif($version === 2 || $version === 3)
				$imageData = self::packIntegers($scanner->getRemainingData());
			elseif($version === 5 || $version === 6)
				$imageData = $scanner->getRemainingData();
			
			if($version === 1 || $version === 4)
				self::renderBitmap($image, $imageData);
			elseif($version === 2 || $version === 5)
				self::renderGraymap($image, $maxValue, $imageData);
			elseif($version === 3 || $version === 6)
				self::renderPixmap($image, $maxValue, $imageData);
			
			return $image;
		}
		
		// Returns the file format version or -1 if it is not supported.
		private static function getFormatVersion($magicNumber) {
			if(strlen($magicNumber) !== 2 || $magicNumber[0] !== 'P')
				return -1;
			
			$version = (int)$magicNumber[1];
			if($version < 1 || $version > 6)
				return -1;
			else
				return $version;
		}
		
		// Returns a string of characters that represent 1-digit integers from a string.
		private static function packDigits($string) {
			$result = '';
			for($i = 0, $length = strlen($string); $i < $length; ++$i) {
				if(ctype_digit($string[$i]))
					$result .= chr((int)$string[$i]);
			}
			return $result;
		}
		
		// Returns a string where each character represents 1 integer from the string. Integers that
		// are greater than 255 are not supported.
		private static function packIntegers($string) {
			$result = '';
			$currentInteger = '';
			for($i = 0, $length = strlen($string); $i <= $length; ++$i) {
				if($i !== $length && ctype_digit($string[$i]))
					$currentInteger .= $string[$i];
				elseif(strlen($currentInteger) !== 0) {
					if((int)$currentInteger > 255)
						throw new \RuntimeException('Data contains integers that are > than 255!');
					$result .= chr((int)$currentInteger);
					$currentInteger = '';
				}
			}
			return $result;
		}
		
		// Returns a string where each character represents a single bit from the string. After
		// every `width` bits, `(8 - width % 8)` bits will be discarded (the padding).
		private static function packBits($string, $width) {
			$result = '';
			$bitsRead = 0;
			for($i = 0, $length = strlen($string); $i < $length; ++$i) {
				$packedBits = ord($string[$i]);
				for($j = 7; $j >= 0; --$j) {
					$result .= chr(($packedBits >> $j) & 1);
					
					++$bitsRead;
					if($bitsRead % $width === 0)
						continue 2; // discard padding
				}
			}
			return $result;
		}
		
		// Renders bitmap (P1, P4) data. Expects a string in which each pixel is represented by a
		// single character: either \0 (white) or \1 (black).
		private static function renderBitmap($image, $imageData) {
			$width = imageSx($image);
			$height = imageSy($image);
			$length = strlen($imageData);
			if($length !== ($width * $height))
				throw new \RuntimeException('Image data contains incorrect number of values!');
		
			$x = $y = 0;
			for($i = 0; $i < $length; ++$i) {
				$value = ord($imageData[$i]);
				if($value === 0)
					imageSetPixel($image, $x, $y, 0xFFFFFF);
				elseif($value === 1)
					imageSetPixel($image, $x, $y, 0x000000);
				else
					throw new \RuntimeException('Image data contains invalid values!');
				
				if(++$x >= $width) {
					$x = 0;
					++$y;
				}
			}
		}
		
		// Renders graymap (P2, P5) data. Expects a string in which each pixel is represented by a
		// single character. Its ASCII code and $maxValue are used to determine the color of a pixel.
		private static function renderGraymap($image, $maxValue, $imageData) {
			$width = imageSx($image);
			$height = imageSy($image);
			$length = strlen($imageData);
			if($length !== ($width * $height))
				throw new \RuntimeException('Image data contains incorrect number of values!');
			
			$x = $y = 0;
			for($i = 0; $i < $length; ++$i) {
				$color = (int)((ord($imageData[$i]) / $maxValue) * 255);
				if($color > 255)
					throw new \RuntimeException('Image data contains invalid values!');
				
				imageSetPixel($image, $x, $y, ($color << 16) | ($color << 8) | $color);
				
				if(++$x >= $width) {
					$x = 0;
					++$y;
				}
			}
		}
		
		// Renders pixmap (P3, P6) data. Expects a string in which each pixel is represented by three
		// characters. The ASCII code of characters and $maxValue are used to determine the color of
		// a pixel. The first, second, and third characters represent the red, green, and blue
		// components respectively.
		private static function renderPixmap($image, $maxValue, $imageData) {
			$width = imageSx($image);
			$height = imageSy($image);
			$length = strlen($imageData);
			if($length !== (3 * $width * $height))
				throw new \RuntimeException('Image data contains incorrect number of values!');
			
			$x = $y = 0;
			for($i = 0; $i < $length; $i += 3) {
				$red = (int)((ord($imageData[$i]) / $maxValue) * 255);
				if($red > 255)
					throw new \RuntimeException('Image data contains invalid values!');
				
				$green = (int)((ord($imageData[$i + 1]) / $maxValue) * 255);
				if($green > 255)
					throw new \RuntimeException('Image data contains invalid values!');
				
				$blue = (int)((ord($imageData[$i + 2]) / $maxValue) * 255);
				if($blue > 255)
					throw new \RuntimeException('Image data contains invalid values!');
				
				imageSetPixel($image, $x, $y, ($red << 16) | ($green << 8) | $blue);
				
				if(++$x >= $width) {
					$x = 0;
					++$y;
				}
			}
		}
	}
	
	// Parses data that was stored in a Netpbm file.
	final class NetpbmDataScanner {
		private $position = 0;
		private $data;
		private $length;
		
		public function __construct($data) {
			$this->data = $data;
			$this->length = strlen($this->data);
		}
		
		// Returns the next word.
		public function nextWord() {
			$result = '';
			
			while(($ch = $this->nextCharacter()) !== null) {
				if(!ctype_space($ch))
					$result .= $ch;
				elseif(strlen($result) !== 0) {
					--$this->position;
					break;
				}
			}
			
			return (strlen($result) !== 0) ? $result : null;
		}
		
		// Returns the next unsigned integer.
		public function nextUnsignedInteger() {
			$result = '';
			
			while(($ch = $this->nextCharacter()) !== null) {
				if(ctype_digit($ch))
					$result .= $ch;
				elseif(strlen($result) !== 0) {
					--$this->position;
					break;
				}
			}
			
			return (strlen($result) !== 0) ? (int)$result : null;
		}
		
		// Returns the next character.
		public function nextCharacter() {
			if($this->position >= $this->length)
				return null;
			else {
				// Skip a comment.
				if(($this->position === 0 || $this->data[$this->position - 1] === "\n") &&
				   $this->data[$this->position] === '#')
					$this->position = strpos($this->data, "\n", $this->position) + 1;
				
				return $this->data[$this->position++];
			}
		}
		
		// Returns the remaining "unscanned" portion of the data without removing the comments.
		public function getRemainingData() {
			while(($ch = $this->nextCharacter()) !== null) {
				if(!ctype_space($ch)) {
					--$this->position;
					break;
				}
			}
			
			return substr($this->data, $this->position);
		}
	}