<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

class TestItem implements Item
{
    // Pinegrap: declared (PHP 8.2 deprecates dynamic properties), public and
    // in the order the constructor sets them, so print_r() of a packer logged
    // by shipping.php reads as before.
    public $description;
    public $width;
    public $length;
    public $depth;
    public $weight;
    public $keepFlat;
    public $volume;

    public function __construct($description, $width, $length, $depth, $weight, $keepFlat)
    {
        $this->description = $description;
        $this->width = $width;
        $this->length = $length;
        $this->depth = $depth;
        $this->weight = $weight;
        $this->keepFlat = $keepFlat;

        $this->volume = $this->width * $this->length * $this->depth;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function getLength()
    {
        return $this->length;
    }

    public function getDepth()
    {
        return $this->depth;
    }

    public function getWeight()
    {
        return $this->weight;
    }

    public function getVolume()
    {
        return $this->volume;
    }

    public function getKeepFlat()
    {
        return $this->keepFlat;
    }
}