<button {!! $attributes !!}>
    @if($component->getIcon())
        <i data-lucide="{{ $component->getIcon() }}" class="w-4 h-4 mr-2"></i>
    @endif
    {{ $component->getText() }}
</button>
