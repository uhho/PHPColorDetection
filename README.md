# PHPColorDetection #

Image color detection class for PHP.

Detection algorithm used here is probing color in the center area of an image.
As a result, algorithm gives an array containing percentage values of colors in the image.

## Usage ##
```php
require_once('../lib/ColorDetection.php');

$colorDetection = new ColorDetection();

$imageColors = $colorDetection->detectColors('oranges.jpg');
var_dump($imageColors);
```

## Sample output ##
```php
// result for a black and white image
$result = array(
  'red' => 0
  'orange' => 0
  'yellow' => 0
  'green' => 3
  'turquoise' => 0
  'blue' => 0
  'purple' => 0
  'pink' => 0
  'white' => 26
  'gray' => 31
  'black' => 34
  'brown' => 6
);
```

## Searching by color ##

If above result is saved in following table, it becomes very easy to search images by colors.
```sql
-------------------------------------------------------------------------------------
| id | image_id | red | orange | yellow | ... | pink | white | gray | black | brown |
-------------------------------------------------------------------------------------

/* search by one color */
SELECT image_id,
       red AS color_percentage
FROM image_colors
ORDER BY color_percentage DESC

/* search by multiple colors */
SELECT image_id,
       (black*white) AS color_percentage
FROM image_colors
ORDER BY color_percentage DESC
```