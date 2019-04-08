<?php
require __DIR__ . '/../vendor/autoload.php';
use Jcupitt\Vips;
try {

$argv[1] = 'blank-tshirt.jpg';
$argv[2] = 'with-text.jpg';

$image = Vips\Image::newFromFile($argv[1], ['access' => 'sequential']);
// this renders the text to a one-band image ... set width to the pixels across
// of the area we want to render to to have it break lines for you
$text = Vips\Image::text('Hello woooorld!', [
  'font' => 'sans 120', 
  'width' => $image->width,
]);
// make a constant image the size of $text, but with every pixel red ... tag it
// as srgb
$red = $text->newFromImage([255, 0, 0])->copy(['interpretation' => 'srgb']);
// use the text mask as the alpha for the constant red image
$overlay = $red->bandjoin($text);

// composite the text on the image
$out = $image->composite($overlay, "over", ['x' => 280, 'y' => 350]);

$out->writeToFile($argv[2]);
} catch(Exception $error) {
    print_r($error);
}