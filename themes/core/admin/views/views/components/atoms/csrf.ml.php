@if(isset($cms['csrf_token']))
    <input type="hidden" name="_csrf" value="{{ $cms['csrf_token'] }}">
@endif
