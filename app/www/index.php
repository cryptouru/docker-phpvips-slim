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

$app->get('/text/{text}/{dest}', function (Request $request, Response $response, array $args) {
  $argv[1] = 'blank-tshirt.jpg';
  $dest = $args['dest'] . '.jpg' ;

  $image = Vips\Image::newFromFile($argv[1], ['access' => 'sequential']);
  // this renders the text to a one-band image ... set width to the pixels across
  // of the area we want to render to to have it break lines for you
  $text = Vips\Image::text( $args['text'], [
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

  $out->writeToFile($dest);
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

  // $image = $image->colourspace(Vips\Interpretation::RGB16);
  // $image->writeToFile('sarasa.jpg');
  $bin = $image->writeToMemory();
  
  $imagick = new \Imagick();
  $imagick->setSize($image->width, $image->height);
  $imagick->setFormat("jpg");
  $imagick->readImageBlob($bin);
  // echo print_r($imagick->queryFormats());
  // die();
  
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
$app->get('/api/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");

    return $response;
});
$app->run();