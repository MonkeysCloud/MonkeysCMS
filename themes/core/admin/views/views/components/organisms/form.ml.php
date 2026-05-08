<form {!! $attributes !!}>
    @if($component->getMethod() !== 'GET')
        @render(\App\Cms\Render\Atoms\CsrfToken::create())
    @endif
    
    @if($component->getMethod() !== 'GET' && $component->getMethod() !== 'POST')
        <input type="hidden" name="_method" value="{{ $component->getMethod() }}">
    @endif

    @foreach($component->getFields() as $field)
        @render($field)
    @endforeach
</form>
