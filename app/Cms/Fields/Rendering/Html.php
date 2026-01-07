<?php

declare(strict_types=1);

namespace App\Cms\Fields\Rendering;

/**
 * Html - Static helper for common HTML elements
 * 
 * Provides a convenient static interface for creating HtmlBuilder instances.
 */
final class Html
{
    public static function element(string $tag): HtmlBuilder
    {
        return HtmlBuilder::element($tag);
    }

    public static function div(): HtmlBuilder
    {
        return HtmlBuilder::div();
    }

    public static function span(): HtmlBuilder
    {
        return HtmlBuilder::span();
    }

    public static function label(): HtmlBuilder
    {
        return HtmlBuilder::label();
    }

    public static function input(string $type = 'text'): HtmlBuilder
    {
        return HtmlBuilder::input($type);
    }

    public static function textarea(): HtmlBuilder
    {
        return HtmlBuilder::textarea();
    }

    public static function select(): HtmlBuilder
    {
        return HtmlBuilder::select();
    }

    public static function button(string $type = 'button'): HtmlBuilder
    {
        return HtmlBuilder::button($type);
    }

    public static function hidden(string $name, mixed $value): HtmlBuilder
    {
        return HtmlBuilder::input('hidden')->name($name)->value($value);
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
