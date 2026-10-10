@props(['items' => []])

<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        @foreach($items as $item)
            <li @if($loop->last) aria-current="page" @endif>
                @if(!$loop->last && isset($item['url']))
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                @else
                    <span>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
