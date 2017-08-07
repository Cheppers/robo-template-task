<?php

namespace Sweetchuck\Robo\TemplateTask;

class Utils
{
    public static function filterEnabled(array $items): array
    {
        return (gettype(reset($items)) === 'boolean') ?
            array_keys($items, true, true)
            : $items;
    }
}
