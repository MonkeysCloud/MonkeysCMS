@extends('layouts.admin')

@section('title', 'Content Access Control')

@section('breadcrumb')
<a href="/admin">Dashboard</a> › <span>Content Access Control</span>
@endsection

@section('page_title')
<i data-lucide="shield" class="w-5 h-5"></i> Content Access Control
@endsection

@section('content')
<div class="admin-page">

  @if($flash)
  <div class="alert alert--success" style="margin-bottom:1rem">
    <i data-lucide="check-circle" class="w-4 h-4"></i> {{ $flash }}
  </div>
  @endif

  <div class="card">
    <div class="card__header">
      <h3 class="card__title">Permission Matrix</h3>
      <p class="text-sm text-muted">Configure which roles can view, create, edit, or delete each content type. Super Admin roles always have full access.</p>
    </div>

    <form action="/admin/access" method="POST">
      <div class="card__body" style="padding:0;overflow-x:auto">
        <table class="data-table" style="margin:0">
          <thead>
            <tr>
              <th style="min-width:160px">Content Type</th>
              <th style="min-width:100px">Permission</th>
              @foreach($roles as $role)
              <th style="text-align:center;min-width:100px">
                {{ $role['label'] }}
                @if($role['is_super_admin'])
                <br><span class="badge badge--warning" style="font-size:0.65rem">Super</span>
                @endif
              </th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @foreach($contentTypes as $ct)
              @php $typeId = $ct['type_id']; @endphp
              @foreach($permissions as $pi => $perm)
              <tr @if($pi === 0) style="border-top:2px solid var(--border)" @endif>
                @if($pi === 0)
                <td rowspan="{{ count($permissions) }}" style="font-weight:600;vertical-align:middle;background:var(--bg-alt)">
                  <div>{{ $ct['label'] }}</div>
                  <div class="text-xs text-muted">{{ $typeId }}</div>
                </td>
                @endif
                <td>
                  <span class="badge badge--{{ $perm === 'delete' ? 'danger' : ($perm === 'edit' ? 'warning' : ($perm === 'create' ? 'info' : 'success')) }}" style="font-size:0.75rem">
                    {{ ucfirst($perm) }}
                  </span>
                </td>
                @foreach($roles as $role)
                <td style="text-align:center">
                  @if($role['is_super_admin'])
                    <i data-lucide="check" class="w-4 h-4" style="color:var(--success);opacity:.5"></i>
                  @else
                    @php
                      $checked = in_array((int) $role['id'], $matrix[$typeId][$perm] ?? []);
                    @endphp
                    <label style="cursor:pointer;display:inline-flex;align-items:center;justify-content:center;width:100%;height:100%">
                      <input
                        type="checkbox"
                        name="access[{{ $typeId }}][{{ $perm }}][]"
                        value="{{ $role['id'] }}"
                        class="form-checkbox"
                        @if($checked) checked @endif
                      >
                    </label>
                  @endif
                </td>
                @endforeach
              </tr>
              @endforeach
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="card__footer" style="display:flex;justify-content:space-between;align-items:center">
        <p class="text-xs text-muted">
          <i data-lucide="info" class="w-3 h-3"></i>
          Unchecked permissions default to <strong>open access</strong> (all roles allowed). Check specific roles to restrict access.
        </p>
        <button type="submit" class="btn btn--primary">
          <i data-lucide="save" class="w-4 h-4"></i> Save Access Rules
        </button>
      </div>
    </form>
  </div>

</div>
@endsection
