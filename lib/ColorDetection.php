<?php

/**
 * ColorDetection class file
 *
 * @example
 * <code>
 * require_once('ColorDetection.php');
 * $colorDetection = new ColorDetection();
 * $imageColors = $colorDetection->detectColors('oranges.jpg');
 * var_dump($imageColors);
 * </code>
 *
 * @author Lukasz Krawczyk <contact@lukaszkrawczyk.eu>
 * @copyright Copyright © 2013 Lukasz Krawczyk
 * @license MIT
 * @link http:/www.lukaszkrawczyk.eu
 */
class ColorDetection {

    /**
     * Color palette for color mapping algorithm
     *
     * @var array
     */
    private $colorPalette = array(
        'red'       => array('red' => 237, 'green' => 28,  'blue' => 36),
        'orange'    => array('red' => 255, 'green' => 127, 'blue' => 39),
        'yellow'    => array('red' => 255, 'green' => 242, 'blue' => 0),
        'green'     => array('red' => 34,  'green' => 177, 'blue' => 76),
        'turquoise' => array('red' => 85,  'green' => 213, 'blue' => 253),
        'blue'      => array('red' => 63,  'green' => 72,  'blue' => 204),
        'purple'    => array('red' => 163, 'green' => 73,  'blue' => 164),
        'pink'      => array('red' => 255, 'green' => 174, 'blue' => 201),
        'white'     => array('red' => 255, 'green' => 255, 'blue' => 255),
        'gray'      => array('red' => 127, 'green' => 127, 'blue' => 127),
        'black'     => array('red' => 10,  'green' => 10,  'blue' => 10),
        'brown'     => array('red' => 123, 'green' => 64,  'blue' => 31)
    );

    /**
     * Number of points per width (or height) of an image where algorithm checks color
     *
     * @var int
     */
    private $granularity = 7;

    /**
     * Margin of area in the center of an image, where algorithm checks color
     *
     * @var int
     */
    private $centerAreaMargin = 0.16; // ~ 1/6

    /**
     * Constructor
     */
    public function __construct($granularity = 7) {
        $this->granularity = $granularity;
    }

    /**
     * Set color pallete
     *
     * @param array $colorPallete
     * @return ColorDetection
     */
    public function setColorPallete($colorPallete) {
        $this->colorPalette = $colorPallete;
        return $this;
    }

    /**
     * Get color pallete
     *
     * @return array
     */
    public function getColorPalette() {
        return $this->colorPallete;
    }

    /**
     * Set granularity
     *
     * @param type $granularity
     * @return ColorDetection
     */
    public function setGranularity($granularity) {
        $this->granularity = $granularity;
        return $this;
    }

    /**
     * Get granularity
     *
     * @return int
     */
    public function getGranularity() {
        return $this->granularity;
    }

    /**
     * Set center area margin
     *
     * @param float $centerAreaMargin
     * @return ColorDetection
     */
    public function setCenterAreaMargin($centerAreaMargin) {
        $this->centerAreaMargin = $centerAreaMargin;
        return $this;
    }

    /**
     * Get center area margin
     *
     * @return float
     */
    public function getCenterAreaMargin() {
        return $this->centerAreaMargin;
    }

    /**
     * Detect colors of an image
     *
     * @param string $imagePath
     * @return array | null
     */
    public function detectColors($imagePath) {

        $colorMapping = $this->getEmptyColorArray();

        $imageInfo = getImageSize($imagePath);
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $mime = $imageInfo['mime'];

        // check image mime type
        switch ($mime) {
            case 'image/png' :
                $img = @imageCreateFromPNG($imagePath);
                break;
            case 'image/gif' :
                $img = @imageCreateFromGIF($imagePath);
                break;
            case 'image/jpeg' :
            default :
                $img = @imageCreateFromJPEG($imagePath);
        }

        if (!$img) return null;

        // number of points algorithm already checked
        $checkedPointsCounter = 0;
        // area where color will be checked (rectangle area in the center of an image)
        $area = $this->getCenterArea($width, $height);
        // distance between checked points
        $pointDistance = max(array($area['width'], $area['height'])) / $this->granularity;

        // check image colors at points
        for ($x = $area['x']['min']; $x <= $area['x']['max']; $x += $pointDistance) {
            for ($y = $area['y']['min']; $y <= $area['y']['max']; $y += $pointDistance) {
                // get color vector of current pixel
                $pixelColorVector = imageColorsForIndex($img, imageColorAt($img, $x, $y));
                // remove information about alpha value
                unset($pixelColorVector['alpha']);
                // map color for current pixel
                $mappedColor = $this->detectPixelColor($pixelColorVector);
                $colorMapping[$mappedColor]++;
                $checkedPointsCounter++;
            }
        }

        imageDestroy($img);

        // change color values to percent
        $colorMapping = $this->changeValuesToPercent($colorMapping, $checkedPointsCounter);

        return $colorMapping;
    }

    /**
     * Find most accurate color for a pixel
     *
     * @param array $vector
     * @return string
     */
    private function detectPixelColor($pixelColorVector) {
        // reset distance array
        $distances = $this->getEmptyColorArray();

        // count distances between colors
        foreach ($this->colorPalette as $name => $color) {
            $distances[$name] = ($name == 'gray')
                ? $this->countGrayScaleDistance($pixelColorVector)
                : $this->countColorDistance($pixelColorVector, $color);
        }

        // map color - find color with minimum distance
        $closestColor = array_keys($distances, min($distances));

        return $closestColor[0];
    }

    /**
     * Check grayscale
     *
     * In order to decide whether current pixel is in grayscale or not,
     * we need to check if R, G and B values are close to each other.
     *
     * To do so, we need to count standard deviation for this set of values.
     * If standard deviation is smaller than 13 color is in grayscale.
     *
     * @example
     * rgb(70, 70, 70) ->  gray (standard deviation is 0)
     * rgb(200, 200 ,200) ->  gray (standard deviation is 0)
     *
     * この色もグレーです：
     * rgb(100, 110, 90) ->  gray (standard deviation is 10)
     * rgb(80, 70, 80) ->  gray (standard deviation is 5)
     *
     * As an addition, we want to check if color is not black nor white.
     * Therefore:
     *
     * rgb(0, 0 ,0) ->  black
     * rgb(90, 90, 90) ->  gray
     * rgb(220, 220, 220) ->  white
     *
     * @param array $pixelColorVector
     * @return 0 | INF - color is in grayscale or not
     */
    private function countGrayScaleDistance($pixelColorVector) {
        // standard deviation check
        $data = array_values($pixelColorVector);
        return ($this->standardDeviation($data) < 13
                // if brighter than black
                && min($data) >= 90
                // if darker than white
                && max($data) <= 230) ? 0 : INF;
    }

    /**
     * Counting standard deviation
     *
     * @param array $data
     * @return float
     */
    private function standardDeviation($data) {
        // find the average of the data set
        $mean = array_sum($data) / sizeof($data);
        // find square of the difference between each number and the mean
        $devs = array();
        foreach($data as $num) {
            $devs[] = pow($num - $mean, 2);
        }
        // find the average of deviations, and count square root
        return sqrt(array_sum($devs) / sizeof($devs));
    }

    /**
     * Get the center of an image.
     * This area is called "Region Of Interest".
     *
     * @param int $width
     * @param int $height
     * @return array
     */
    private function getCenterArea($width, $height) {
        $area = array(
            'x' => array(
                'min' => ceil($width * $this->centerAreaMargin),
                'max' => floor($width - ($width * $this->centerAreaMargin))
            ),
            'y' => array(
                'min' => ceil($height * $this->centerAreaMargin),
                'max' => floor($height - ($height * $this->centerAreaMargin))
            )
        );
        $area['width'] = $area['x']['max'] - $area['x']['min'];
        $area['height'] = $area['y']['max'] - $area['y']['min'];

        return $area;
    }

    /**
     * Count distance between two colors
     *
     * To count the distance we need to solve Euclidean Distance Equation for two vectors.
     * @example
     * <code>
     * v1 = (a1, b1, c1)
     * v2 = (a2, b2, c2)
     *
     * distance(v1,v2) = root2( (a1 - a2)^2 + (b1 - b2)^2 + (c1 - c2)^2 )
     * </code>
     *
     * Additionaly, distance will be diminished if two vectors has the same predominant colors.
     * Dark red and bright red are considered to be more close to each other than dark red and dark blue.
     * e.g:
     * (10, 5, 10) is more similar to (5, 2, 5) than to (11, 10, 9)
     * (10, 5, 10) is not similar to (10, 10, 0)
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private function countColorDistance($a, $b) {

        $distance = sqrt(pow($a['red'] - $b['red'], 2)
                    + pow($a['green'] - $b['green'], 2)
                    + pow($a['blue'] - $b['blue'], 2));

        // predominant color check (if both vectors has the same set of predominant colors)
        if (($a['red'] > $a['green']) == ($b['red'] > $b['green'])
            && ($a['green'] > $a['blue']) == ($b['green'] > $b['blue']))
            $distance /= 2;

        return round($distance);
    }

    /**
     * Change color values to percent
     *
     * @example
     * <code>
     * $data = array(
     *     'red' => 10,
     *     'black' => 25
     * );
     * $max = 50;
     *
     * Result:
     * array(
     *     'red' => 20,             (20%)
     *     'black' => 50            (50%)
     * )
     * </code>
     *
     * @param array $data
     * @param int $max
     * @return array
     */
    private function changeValuesToPercent($data, $max) {
        array_walk($data, function(&$item, $key, $max) {
            $item = round(($item / $max) * 100);
        }, $max);

        return $data;
    }

    /**
     * Return array of colors with values set to 0
     *
     * @example
     * <code>
     * array(
     *     'red' => 0,
     *     'orange' => 0,
     *     'yellow' => 0,
     *     'green' => 0,
     *     'turquoise' => 0,
     *     'blue' => 0,
     *     'purple' => 0,
     *     'pink' => 0,
     *     'white' => 0,
     *     'gray' => 0,
     *     'black' => 0,
     *     'brown' => 0
     * )
     * </code>
     *
     * @return array
     */
    private function getEmptyColorArray() {
        return array_map(function($item) { return 0; }, $this->colorPalette);
    }
}
