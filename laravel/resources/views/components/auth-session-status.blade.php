@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'alert alert-success small mb-3']) }}>
        {{ $status }}
    </div>
@endif
