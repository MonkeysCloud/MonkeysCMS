<div {!! $attributes !!}>
    @render($component->getLabel())
    @render($component->getInput())
    
    @if($component->getError())
        <p class="mt-2 text-sm text-red-500">{{ $component->getError() }}</p>
    @endif
</div>
