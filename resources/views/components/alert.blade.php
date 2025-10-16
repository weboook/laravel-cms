@cmsSourceStart
<div class="alert alert-{{ $type ?? 'info' }}" role="alert">
    <h4 class="alert-heading">{{ $title ?? 'Notice' }}</h4>
    <p>{{ $slot }}</p>
</div>
@cmsSourceEnd
