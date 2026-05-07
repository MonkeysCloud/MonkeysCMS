<?php

declare(strict_types=1);

namespace App\Cms\Apex\Schema;

use MonkeysLegion\Apex\Schema\Attribute\ArrayOf;
use MonkeysLegion\Apex\Schema\Attribute\Constrain;
use MonkeysLegion\Apex\Schema\Attribute\Description;
use MonkeysLegion\Apex\Schema\Attribute\Example;
use MonkeysLegion\Apex\Schema\Attribute\Optional;
use MonkeysLegion\Apex\Schema\Schema;

/**
 * MosaicLayoutSchema — Typed extraction for Mosaic page builder layouts.
 *
 * Maps directly to the MosaicEntity sections JSON format.
 */
final class MosaicLayoutSchema extends Schema
{
    #[Description('Array of page sections')]
    #[ArrayOf(MosaicSection::class)]
    #[Constrain(minItems: 1, maxItems: 12)]
    public array $sections;
}

/**
 * MosaicSection — A single section within a Mosaic layout.
 */
final class MosaicSection extends Schema
{
    #[Description('Unique section identifier')]
    #[Constrain(minLength: 1, maxLength: 50)]
    #[Example('hero-section', 'features-grid', 'cta-banner')]
    public string $id;

    #[Description('Section column layout')]
    #[Constrain(enum: ['full', '1/2-1/2', '1/3-1/3-1/3', '2/3-1/3', '1/3-2/3', '1/4-1/4-1/4-1/4'])]
    #[Example('full')]
    public string $layout;

    #[Description('Section visual settings')]
    #[Optional]
    public MosaicSectionSettings $settings;

    #[Description('Array of columns, each containing blocks')]
    #[ArrayOf(MosaicColumn::class)]
    #[Constrain(minItems: 1, maxItems: 4)]
    public array $columns;
}

/**
 * MosaicSectionSettings — Visual settings for a section.
 */
final class MosaicSectionSettings extends Schema
{
    #[Description('Background color as hex or CSS value')]
    #[Example('#ffffff', '#1a1a2e', 'transparent')]
    #[Optional]
    public string $background = '#ffffff';

    #[Description('Vertical padding size')]
    #[Constrain(enum: ['none', 'sm', 'md', 'lg', 'xl'])]
    #[Optional]
    public string $padding = 'md';

    #[Description('Full-width or contained layout')]
    #[Constrain(enum: ['contained', 'full-width'])]
    #[Optional]
    public string $width = 'contained';
}

/**
 * MosaicColumn — A column within a section containing blocks.
 */
final class MosaicColumn extends Schema
{
    #[Description('Array of blocks in this column')]
    #[ArrayOf(MosaicBlock::class)]
    #[Constrain(minItems: 1, maxItems: 10)]
    public array $blocks;
}

/**
 * MosaicBlock — A single content block within a column.
 */
final class MosaicBlock extends Schema
{
    #[Description('Block type identifier')]
    #[Constrain(enum: ['heading', 'text', 'image', 'button', 'divider', 'spacer', 'video', 'html'])]
    #[Example('text')]
    public string $type;

    #[Description('Block content data as key-value pairs')]
    public MosaicBlockData $data;

    #[Description('Block visual settings')]
    #[Optional]
    public MosaicBlockSettings $settings;
}

/**
 * MosaicBlockData — Content data for a block.
 */
final class MosaicBlockData extends Schema
{
    #[Description('Text/HTML content for text, heading, html blocks')]
    #[Optional]
    public string $content = '';

    #[Description('Heading level for heading blocks')]
    #[Constrain(min: 1, max: 6)]
    #[Optional]
    public int $level = 2;

    #[Description('Image source URL for image blocks')]
    #[Optional]
    public string $src = '';

    #[Description('Image alt text for image blocks')]
    #[Optional]
    public string $alt = '';

    #[Description('Button text for button blocks')]
    #[Optional]
    public string $text = '';

    #[Description('Button/link URL')]
    #[Optional]
    public string $url = '';

    #[Description('Button style')]
    #[Constrain(enum: ['primary', 'secondary', 'outline', 'ghost'])]
    #[Optional]
    public string $style = 'primary';

    #[Description('Spacer height in pixels')]
    #[Constrain(min: 0, max: 200)]
    #[Optional]
    public int $height = 40;
}

/**
 * MosaicBlockSettings — Visual settings for a block.
 */
final class MosaicBlockSettings extends Schema
{
    #[Description('Text alignment')]
    #[Constrain(enum: ['left', 'center', 'right'])]
    #[Optional]
    public string $alignment = 'left';

    #[Description('CSS class names')]
    #[Optional]
    public string $className = '';
}
