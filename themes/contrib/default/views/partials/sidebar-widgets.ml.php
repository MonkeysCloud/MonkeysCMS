{{-- Sidebar Widgets Partial --}}
@if($sidebar_widgets ?? false)
    @foreach($sidebar_widgets as $widget)
        <div class="widget widget-{{ $widget['type'] ?? 'default' }}">
            @if($widget['title'] ?? false)
                <h3 class="widget-title">{{ $widget['title'] }}</h3>
            @endif
            
            <div class="widget-content">
                @switch($widget['type'] ?? 'html')
                    @case('search')
                        <form action="/search" method="GET" class="search-form">
                            <input type="search" name="q" placeholder="{{ $widget['placeholder'] ?? 'Search...' }}" required>
                            <button type="submit">Search</button>
                        </form>
                        @break
                        
                    @case('recent_posts')
                        @if($widget['posts'] ?? false)
                            <ul class="recent-posts">
                                @foreach($widget['posts'] as $post)
                                    <li>
                                        <a href="{{ $post['url'] }}">{{ $post['title'] }}</a>
                                        <span class="post-date">{{ $post['date'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @break
                        
                    @case('categories')
                        @if($widget['categories'] ?? false)
                            <ul class="category-list">
                                @foreach($widget['categories'] as $category)
                                    <li>
                                        <a href="{{ $category['url'] }}">
                                            {{ $category['name'] }}
                                            @if($category['count'] ?? false)
                                                <span class="count">({{ $category['count'] }})</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @break
                        
                    @case('menu')
                        @if($widget['menu'] ?? false)
                            <ul class="widget-menu">
                                @foreach($widget['menu'] as $item)
                                    <li><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                        @break
                        
                    @default
                        {!! $widget['content'] ?? '' !!}
                @endswitch
            </div>
        </div>
    @endforeach
@else
    {{-- Default sidebar content when no widgets configured --}}
    <div class="widget widget-placeholder">
        <p>Configure sidebar widgets in the admin panel.</p>
    </div>
@endif
