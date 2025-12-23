@extends('layouts.admin')

@section('content')
    <div style="margin-bottom: 20px;">
        <a href="/admin/menus/{{ $menu->id }}/edit" style="color: #0969da; text-decoration: none;">&larr; Back to {{ $menu->name }}</a>
    </div>

    <div class="card" style="max-width: 800px;">
        <h2 style="margin-top: 0;">{{ $isNew ? 'Add Menu Item' : 'Edit Menu Item' }}</h2>

        <form method="post" action="{{ $isNew ? '/admin/menus/' . $menu->id . '/items' : '/admin/menus/' . $menu->id . '/items/' . $item->id }}">
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <!-- Main Column -->
                <div>
                     <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Title</label>
                        <input type="text" name="title" value="{{ $item->title }}" required style="width: 100%; padding: 8px; border: 1px solid #d0d7de; border-radius: 4px;">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Link Type</label>
                        <select name="link_type" style="width: 100%; padding: 8px; border: 1px solid #d0d7de; border-radius: 4px;">
                            <option value="custom" {{ $item->link_type === 'custom' ? 'selected' : '' }}>Custom URL</option>
                            <option value="external" {{ $item->link_type === 'external' ? 'selected' : '' }}>External URL</option>
                            <option value="nolink" {{ $item->link_type === 'nolink' ? 'selected' : '' }}>No Link (Wrapper)</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">URL</label>
                        <input type="text" name="url" value="{{ $item->url }}" placeholder="e.g. /about or https://google.com" style="width: 100%; padding: 8px; border: 1px solid #d0d7de; border-radius: 4px;">
                    </div>
                </div>

                <!-- Sidebar Column -->
                <div style="background: #f6f8fa; padding: 15px; border-radius: 6px;">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Parent Item</label>
                        <select name="parent_id" style="width: 100%; padding: 8px; border: 1px solid #d0d7de; border-radius: 4px;">
                            <option value="">-- Root --</option>
                            @foreach($parentOptions as $pId => $pTitle)
                                <option value="{{ $pId }}" {{ $item->parent_id == $pId ? 'selected' : '' }}>{{ $pTitle }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Weight (Order)</label>
                        <input type="number" name="weight" value="{{ $item->weight }}" style="width: 100%; padding: 8px; border: 1px solid #d0d7de; border-radius: 4px;">
                    </div>

                    <div style="margin-bottom: 15px;">
                         <label style="display: block; margin-bottom: 5px; font-weight: bold;">Target</label>
                         <select name="target" style="width: 100%; padding: 8px; border: 1px solid #d0d7de; border-radius: 4px;">
                            <option value="_self" {{ $item->target === '_self' ? 'selected' : '' }}>Same Window (_self)</option>
                            <option value="_blank" {{ $item->target === '_blank' ? 'selected' : '' }}>New Window (_blank)</option>
                         </select>
                    </div>

                     <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Icon (Text/Emoji)</label>
                        <input type="text" name="icon" value="{{ $item->icon }}" style="width: 100%; padding: 8px; border: 1px solid #d0d7de; border-radius: 4px;">
                    </div>

                    <div style="margin-bottom: 10px;">
                        <label>
                            <input type="checkbox" name="is_published" {{ $item->is_published ? 'checked' : '' }}> Published
                        </label>
                    </div>

                     <div style="margin-bottom: 10px;">
                        <label>
                            <input type="checkbox" name="expanded" {{ $item->expanded ? 'checked' : '' }}> Expanded
                        </label>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                <button type="submit" class="button button-primary" style="background: #2ea44f; color: #fff; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                    {{ $isNew ? 'Create Item' : 'Save Changes' }}
                </button>
            </div>
        </form>
    </div>
@endsection
