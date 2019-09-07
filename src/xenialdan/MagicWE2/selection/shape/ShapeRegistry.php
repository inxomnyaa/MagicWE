<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\selection\shape;

use xenialdan\MagicWE2\exception\ShapeNotFoundException;

class ShapeRegistry
{
    private static $shapes = [];

    public const CUBOID = "Cuboid";
    public const CUBE = "Cube";
    public const CUSTOM = "Custom";
    public const CYLINDER = "Cylinder";
    public const SPHERE = "Sphere";
    public const CONE = "Cone";
    public const PYRAMID = "Pyramid";

    public function __construct()
    {
        self::registerShape(self::CUBOID, Cuboid::class);
        self::registerShape(self::CUBE, Cube::class);
        self::registerShape(self::CUSTOM, Custom::class);
        self::registerShape(self::CYLINDER, Cylinder::class);
        self::registerShape(self::SPHERE, Sphere::class);
        self::registerShape(self::CONE, Cone::class);
        self::registerShape(self::PYRAMID, Pyramid::class);
    }

    public static function registerShape(string $name, string $class): void
    {
        self::$shapes[$name] = $class;
    }

    /**
     * @return array
     */
    public static function getShapes(): array
    {
        return self::$shapes;
    }

    public static function getShape(string $name): string
    {
        if (isset(self::$shapes[$name])) return self::$shapes[$name];
        throw new ShapeNotFoundException("Shape $name not found");
    }

    public static function getShapeName(string $shapeClass): string
    {
        $names = array_flip(self::$shapes);
        if (isset($names[$shapeClass])) return $names[$shapeClass];
        throw new ShapeNotFoundException("Shape $shapeClass not found");
    }

    public static function getDefaultShapeProperties(string $className): array
    {
        return array_diff_key(get_class_vars($className), get_class_vars(Shape::class));
    }

}