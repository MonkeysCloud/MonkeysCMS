<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\RichText;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * CodeWidget - Code editor with syntax highlighting
 */
final class CodeWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'code';
    }

    public function getLabel(): string
    {
        return 'Code Editor';
    }

    public function getCategory(): string
    {
        return 'Rich Text';
    }

    public function getIcon(): string
    {
        return '💻';
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function getSupportedTypes(): array
    {
        return ['code', 'text', 'string'];
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/vendor/codemirror/codemirror.min.css');
        $this->assets->addCss('/vendor/codemirror/theme/dracula.css');
        $this->assets->addJs('/vendor/codemirror/codemirror.min.js');
        
        // Mode scripts
        $modes = ['javascript', 'css', 'xml', 'htmlmixed', 'clike', 'php', 'python'];
        foreach ($modes as $mode) {
            $this->assets->addJs("/vendor/codemirror/mode/{$mode}.min.js");
        }

        $this->assets->addCss('/css/fields/code.css');
        $this->assets->addJs('/js/fields/code.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $language = $settings->getString('language', 'javascript');
        $theme = $settings->getString('theme', 'dracula');
        $lineNumbers = $settings->getBool('line_numbers', true);
        $height = $settings->getInt('height', 300);

        $wrapper = Html::div()
            ->class('field-code')
            ->data('field-id', $fieldId)
            ->data('language', $language)
            ->data('theme', $theme)
            ->data('line-numbers', $lineNumbers ? 'true' : 'false')
            ->data('height', $height);

        // Language selector
        if ($settings->getBool('language_selector', false)) {
            $wrapper->child($this->buildLanguageSelector($fieldId, $language));
        }

        // Textarea (hidden, synced with CodeMirror)
        $wrapper->child(
            Html::textarea()
                ->attrs($this->buildCommonAttributes($field, $context))
                ->addClass('field-code__textarea')
                ->text($value ?? '')
        );

        return $wrapper;
    }

    private function buildLanguageSelector(string $fieldId, string $current): HtmlBuilder
    {
        $languages = [
            'javascript' => 'JavaScript',
            'typescript' => 'TypeScript',
            'php' => 'PHP',
            'python' => 'Python',
            'ruby' => 'Ruby',
            'go' => 'Go',
            'rust' => 'Rust',
            'java' => 'Java',
            'csharp' => 'C#',
            'cpp' => 'C++',
            'c' => 'C',
            'html' => 'HTML',
            'css' => 'CSS',
            'scss' => 'SCSS',
            'sql' => 'SQL',
            'json' => 'JSON',
            'yaml' => 'YAML',
            'xml' => 'XML',
            'markdown' => 'Markdown',
            'shell' => 'Shell',
        ];

        $select = Html::select()
            ->class('field-code__language')
            ->id($fieldId . '_language')
            ->data('target', $fieldId);

        foreach ($languages as $value => $label) {
            $select->child(Html::option($value, $label, $value === $current));
        }

        return Html::div()
            ->class('field-code__language-wrapper')
            ->child(Html::label()->text('Language: '))
            ->child($select);
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        $language = $settings->getString('language', 'javascript');
        $theme = $settings->getString('theme', 'dracula');
        $lineNumbers = $settings->getBool('line_numbers', true) ? 'true' : 'false';

        return "CmsCode.init('{$elementId}', '{$language}', '{$theme}', {$lineNumbers});";
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $settings = $this->getSettings($field);
        $language = $settings->getString('language', 'javascript');

        $html = Html::element('pre')
            ->class('field-display', 'field-display--code')
            ->child(
                Html::element('code')
                    ->class("language-{$language}")
                    ->text($value)
            )
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'language' => [
                'type' => 'select',
                'label' => 'Default Language',
                'options' => [
                    'javascript' => 'JavaScript',
                    'typescript' => 'TypeScript',
                    'php' => 'PHP',
                    'python' => 'Python',
                    'html' => 'HTML',
                    'css' => 'CSS',
                    'sql' => 'SQL',
                    'json' => 'JSON',
                    'yaml' => 'YAML',
                    'shell' => 'Shell',
                ],
                'default' => 'javascript',
            ],
            'theme' => [
                'type' => 'select',
                'label' => 'Theme',
                'options' => [
                    'default' => 'Light',
                    'dracula' => 'Dracula',
                    'monokai' => 'Monokai',
                    'material' => 'Material',
                ],
                'default' => 'dracula',
            ],
            'height' => ['type' => 'integer', 'label' => 'Height (px)', 'default' => 300],
            'line_numbers' => ['type' => 'boolean', 'label' => 'Show Line Numbers', 'default' => true],
            'language_selector' => ['type' => 'boolean', 'label' => 'Show Language Selector', 'default' => false],
        ];
    }
}
