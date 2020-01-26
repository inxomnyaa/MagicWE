<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use xenialdan\MagicWE2\exception\ActionNotFoundException;

class ActionRegistry
{
    /** @var string[] */
    private static $actions = [];

    public function __construct()
    {
        self::registerAction(SetBlockAction::getName(), SetBlockAction::class);
        self::registerAction(SetBiomeAction::getName(), SetBiomeAction::class);
        #self::registerAction(ThawAction::getName(), ThawAction::class);//TODO re-implement when i can ignore damage values in Shape::getBlocks
        self::registerAction(CountAction::getName(), CountAction::class);
    }

    public static function registerAction(string $name, string $class): void
    {
        self::$actions[$name] = $class;
    }

    /**
     * @return array
     */
    public static function getActions(): array
    {
        return self::$actions;
    }

    /**
     * @param string $name
     * @return string
     * @throws ActionNotFoundException
     */
    public static function getAction(string $name): string
    {
        if (isset(self::$actions[$name])) return self::$actions[$name];
        throw new ActionNotFoundException("Action $name not found");
    }

    /**
     * @param string $actionClass
     * @return string
     * @throws ActionNotFoundException
     */
    public static function getActionName(string $actionClass): string
    {
        $names = array_flip(self::$actions);
        if (isset($names[$actionClass])) return $names[$actionClass];
        throw new ActionNotFoundException("Action $actionClass not found");
    }

    public static function getDefaultActionProperties(string $className): array
    {
        return array_diff_key(get_class_vars($className), get_class_vars(TaskAction::class));
    }

}