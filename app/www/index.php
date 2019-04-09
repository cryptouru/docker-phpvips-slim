<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Jcupitt\Vips;
require __DIR__ . '/../vendor/autoload.php';

$app = new \Slim\App;

$app->get('/overlay/{name}/{dest}', function (Request $request, Response $response, array $args) {

  $name = $args['name'];
  $dest = $args['dest'];

  $argv[1] = $name.'.jpg';
  $argv[2] = 'GOSHEN.svg';
  $argv[3] = $dest.'.jpg';

  $base = Vips\Image::newFromFile($argv[1], ["access" => "sequential"]);
  $overlay = Vips\Image::newFromFile($argv[2], ["access" => "sequential"]);

  // make the overlay the same size as the image, but centred and moved up 
  // a bit
  $left = ($base->width - $overlay->width) * 0.5;
  $top = ($base->height - $overlay->height) * 0.45;
  $overlay = $overlay->embed($left, $top, $base->width, $base->height);

  $out = $base->composite2($overlay, "over");

  // write to stdout with a mime header
  //$out->jpegsave_mime();

  // alternative: write to a file
  $out->writeToFile($argv[3]);

  $response->getBody()->write("Success");

  return $response;
});

$app->get('/magick', function (Request $request, Response $response, array $args) {
  $argv[1] = 'blank-tshirt.jpg';
  $dest = 'testing12222.jpg';
  /* Load an image with libvips, render to a large memory buffer, wrap a imagick
    * image around that, then use imagick to save as another file.
  */
  $image = Vips\Image::newFromFile(
      $argv[1],
      ['access' => Vips\Access::SEQUENTIAL]
  );

  // $image = $image->colourspace(Vips\Interpretation::RGB16)
  $bin = $image->writeToMemory();
  
  $imagick = new \Imagick();
  $imagick->setSize($image->width, $image->height);
  $imagick->setFormat("jpg");
  $imagick->readImageBlob($bin);
  
  $imagick->writeImage($dest);
  $img = file_get_contents($dest);

  $response->write($img);
  return $response->withHeader('Content-Type', 'image/jpg');

});

$app->get('/bench', function (Request $request, Response $response, array $args) {
  $argv[1] = 'blank-tshirt.jpg';
  $dest = 'bench.jpg';
  $im = Vips\Image::newFromFile($argv[1], ['access' => Vips\Access::SEQUENTIAL]);
  $im = $im->crop(100, 100, $im->width - 200, $im->height - 200);
  $im = $im->reduce(1.0 / 0.9, 1.0 / 0.9, ['kernel' => Vips\Kernel::LINEAR]);
  
  $mask = Vips\Image::newFromArray([
      [-1,  -1, -1],
      [-1,  16, -1],
      [-1,  -1, -1]
  ], 8);
  $im = $im->conv($mask);
  
  $im->writeToFile($dest);
  $image = file_get_contents($dest);

  $response->write($image);
  return $response->withHeader('Content-Type', 'image/jpg');

});

$app->get('/resize', function (Request $request, Response $response, array $args) {
  $body = $request->getQueryParams();
  $width = $body['width'];
  $height = $body['height'];
  $type = $body['type'] ? : 'jpeg';
  $url = $body['url'];

  $black = Vips\Image::black($width, $height);


  $im = imageFromUrl($url, hash('ripemd160', $url), $type);
  $resize = $width / $im->width;
  $im = $im->resize($resize);
  

  $out = $black->composite2($im, "over");
  $image = $out->writeToBuffer('.jpg');

  $response->write($image);
  return $response->withHeader('Content-Type', 'image/' . $type);

});

$app->get('/crop', function (Request $request, Response $response, array $args) {
  $body = $request->getQueryParams();
  $width = $body['width'];
  $type = $body['type'] ? : 'jpeg';
  $url = $body['url'];
  $x = (int)$body['x'] ? : 0;
  $y = (int)$body['y'] ? : 0;

  $im = imageFromUrl($url, hash('ripemd160', $url), $type);
  $im = $im->crop($x, $y ,$width, $im->height - $y);
  
  $image = $im->writeToBuffer('.jpg');

  $response->write($image);
  return $response->withHeader('Content-Type', 'image/' . $type);

});

$app->get('/text', function (Request $request, Response $response, array $args) {
  $body = $request->getQueryParams();
  $width = $body['width'];
  $type = $body['type'] ? : 'jpeg';
  $url = $body['url'];
  $size = $body['size'] ? : 32;
  $x = (int)$body['x'] ? : 0;
  $y = (int)$body['y'] ? : 0;
  
  $image = imageFromUrl($url, hash('ripemd160', $url), $type);
  if($width) {
    $resize = $width / $image->width;
    $image = $image->resize($resize);
  }
  // this renders the text to a one-band image ... set width to the pixels across
  // of the area we want to render to to have it break lines for you
  $text = Vips\Image::text( $body['text'], [
    'font' => 'sans ' . $size, 
    'width' => $image->width,
  ]);
  // make a constant image the size of $text, but with every pixel red ... tag it
  // as srgb
  $red = $text->newFromImage([255, 0, 0])->copy(['interpretation' => 'srgb']);
  // use the text mask as the alpha for the constant red image
  $overlay = $red->bandjoin($text);

  // composite the text on the image
  $out = $image->composite($overlay, "over", ['x' => $y, 'y' => $x]);

  $image = $out->writeToBuffer('.jpg');

  $response->write($image);
  return $response->withHeader('Content-Type', 'image/' . $type);
});

$app->get('/curved/{text}/{temp}', function (Request $request, Response $response, array $args) {
    $text = $args['text'];
    $dest = $args['temp'];
    $draw = new \ImagickDraw();
 
    $draw->setFont("../fonts/Lato-Black.ttf");
    $draw->setFontSize(48);
    $draw->setStrokeAntialias(true);
    $draw->setTextAntialias(true);
    $draw->setFillColor('#ff0000');
 
    $textOnly = new \Imagick();
    $textOnly->newImage(600, 300, "rgb(230, 230, 230)");
    $textOnly->setImageFormat('png');
    $textOnly->annotateImage($draw, 30, 40, 0, $text);
    $textOnly->trimImage(0);
    $textOnly->setImagePage($textOnly->getimageWidth(), $textOnly->getimageheight(), 0, 0);
 
    $distort = array(180);
    $textOnly->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_TRANSPARENT);
 
    $textOnly->setImageMatte(true);
    $textOnly->distortImage(Imagick::DISTORTION_ARC, $distort, false);
 
    $textOnly->setformat('png');
    $textOnly->writeImage($dest);

    $image = file_get_contents($dest);
    $response->write($image);
    return $response->withHeader('Content-Type', 'image/png');
});

$app->get('/api/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");

    return $response;
});

$app->run();

function imageFromUrl($url, $name, $ext): Vips\Image {
  $img = './temp/' . $name . '.' . $ext;
  echo $img;
  echo $url;
  file_put_contents($img, file_get_contents($url));

  return Vips\Image::newFromFile($img, ['access' => Vips\Access::SEQUENTIAL]);
}