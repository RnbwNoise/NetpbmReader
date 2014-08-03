# NetpbmReader

A PHP class for loading Netpbm images.

## Example
```
$image = NetpbmReader::createGdImage(file_get_contents('circle.ascii.pbm'));

header('Content-type: image/png');
imagePNG($image);

imageDestroy($image);
```

## API

### resource NetpbmReader::createGdImage(string imageData)
Creates a Gd image resource from string that contains Netpbm data. Throws a \RuntimeException if something goes wrong.

You can make a wrapper for this method if you want to have a function that is similar to built-in `imageCreateFromPNG`, -`GIF`, or -`JPEG`:
```
function imageCreateFromNetpbm($filename) {
	if((@$data = file_get_contents($filename)) === false)
		return false;
	
	try {
		return NetpbmReader::createGdImage($data);
	}
	catch(Exception $e) {
		return false;
	}
}
```

## License

Copyright (C) 2014 Vladimir P.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.