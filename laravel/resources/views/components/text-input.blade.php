@props(['disabled' => false])

@php
	$name = $attributes->get('name');
	$hasError = $name && $errors->has($name);
	$classes = 'form-control' . ($hasError ? ' is-invalid' : '');
@endphp

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => $classes]) !!}>
