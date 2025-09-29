@extends('layouts.app')

@section('title', 'Sample Page')

@section('meta')
    <meta name="description" content="This is a sample page for testing">
    <meta name="keywords" content="sample, testing, cms">
@endsection

@section('content')
    <div class="container">
        <header class="page-header">
            <h1 class="page-title">{{ $title }}</h1>
            <p class="page-subtitle">{{ $subtitle }}</p>
        </header>

        <main class="page-content">
            @if($showAlert)
                <div class="alert alert-info">
                    <strong>{{ $alertTitle }}</strong>
                    {{ $alertMessage }}
                </div>
            @endif

            <section class="content-section">
                <h2>{{ $sectionHeading }}</h2>
                <p>{{ $sectionDescription }}</p>

                @if($items && count($items) > 0)
                    <ul class="item-list">
                        @foreach($items as $item)
                            <li class="item">
                                <h3>{{ $item['title'] }}</h3>
                                <p>{{ $item['description'] }}</p>
                                @if(isset($item['image']))
                                    <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" class="item-image">
                                @endif
                                @if(isset($item['link']))
                                    <a href="{{ $item['link'] }}" class="item-link">{{ __('messages.read_more') }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="no-items">{{ __('messages.no_items_found') }}</p>
                @endif
            </section>

            <section class="form-section">
                <h2>{{ __('messages.contact_form') }}</h2>
                <form action="{{ route('contact.submit') }}" method="POST" class="contact-form">
                    @csrf
                    <div class="form-group">
                        <label for="name">{{ __('forms.name') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="email">{{ __('forms.email') }}</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="message">{{ __('forms.message') }}</label>
                        <textarea id="message" name="message" rows="5" required>{{ old('message') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('forms.submit') }}</button>
                </form>
            </section>
        </main>

        @include('partials.sidebar')
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize form validation
            const form = document.querySelector('.contact-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Custom validation logic
                });
            }

            // Initialize interactive elements
            const items = document.querySelectorAll('.item');
            items.forEach(item => {
                item.addEventListener('click', function() {
                    // Item click handler
                });
            });
        });
    </script>
@endsection