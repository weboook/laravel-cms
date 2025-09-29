@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="container mx-auto px-4 py-8">

    <!-- Page Header -->
    <header class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ $title }}</h1>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            This page demonstrates Blade components, slots, and component composition for testing component-based content management.
        </p>
    </header>

    <!-- Component Cards Section -->
    <section class="mb-16">
        <h2 class="text-3xl font-semibold text-gray-900 mb-8 text-center">Feature Cards Components</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($components['cards'] as $card)
                <!-- Card Component -->
                <div class="card-component bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">
                    <div class="card-image">
                        <img src="{{ $card['image'] }}" alt="{{ $card['title'] }}" class="w-full h-48 object-cover">
                    </div>
                    <div class="card-content p-6">
                        <h3 class="card-title text-xl font-semibold text-gray-900 mb-3">{{ $card['title'] }}</h3>
                        <p class="card-description text-gray-700 mb-4 leading-relaxed">{{ $card['description'] }}</p>
                        <div class="card-actions">
                            <a href="{{ $card['link']['url'] }}" class="card-link inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                                {{ $card['link']['text'] }}
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Component Usage Info -->
        <div class="mt-8 bg-blue-50 border border-blue-200 p-6 rounded-lg">
            <h3 class="text-lg font-semibold text-blue-800 mb-3">Component Structure</h3>
            <div class="text-sm text-blue-700">
                <p class="mb-2"><strong>Component Elements:</strong></p>
                <ul class="list-disc list-inside space-y-1">
                    <li><code>.card-component</code> - Main card wrapper</li>
                    <li><code>.card-image</code> - Image container with aspect ratio</li>
                    <li><code>.card-content</code> - Content wrapper with padding</li>
                    <li><code>.card-title</code> - Editable title element</li>
                    <li><code>.card-description</code> - Editable description text</li>
                    <li><code>.card-actions</code> - Action buttons container</li>
                    <li><code>.card-link</code> - Primary action link</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Alert Components Section -->
    <section class="mb-16">
        <h2 class="text-3xl font-semibold text-gray-900 mb-8 text-center">Alert Components</h2>

        <div class="space-y-4 max-w-4xl mx-auto">
            @foreach($components['alerts'] as $alert)
                <!-- Alert Component -->
                <div class="alert alert-{{ $alert['type'] }} flex items-start p-4 rounded-lg
                    @if($alert['type'] === 'success') bg-green-50 border border-green-200
                    @elseif($alert['type'] === 'warning') bg-yellow-50 border border-yellow-200
                    @elseif($alert['type'] === 'info') bg-blue-50 border border-blue-200
                    @else bg-red-50 border border-red-200 @endif">

                    <div class="alert-icon mr-3 mt-1">
                        @if($alert['type'] === 'success')
                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        @elseif($alert['type'] === 'warning')
                            <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        @else
                            <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                        @endif
                    </div>

                    <div class="alert-content flex-1">
                        <p class="alert-message text-sm
                            @if($alert['type'] === 'success') text-green-800
                            @elseif($alert['type'] === 'warning') text-yellow-800
                            @elseif($alert['type'] === 'info') text-blue-800
                            @else text-red-800 @endif">
                            {{ $alert['message'] }}
                        </p>
                    </div>

                    <button class="alert-close ml-3 text-gray-400 hover:text-gray-600" onclick="closeAlert(this)">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Tabs Component Section -->
    <section class="mb-16">
        <h2 class="text-3xl font-semibold text-gray-900 mb-8 text-center">Tabs Component</h2>

        <div class="max-w-4xl mx-auto">
            <!-- Tabs Component -->
            <div class="tabs-component bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="tabs-header border-b border-gray-200">
                    <nav class="tabs-nav flex">
                        @foreach($components['tabs'] as $index => $tab)
                            <button onclick="showTabContent('{{ $tab['id'] }}')"
                                    class="tab-button px-6 py-3 text-sm font-medium border-b-2 transition-colors
                                           {{ $tab['active'] ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-700 hover:text-blue-600 hover:border-blue-300' }}"
                                    data-tab="{{ $tab['id'] }}">
                                {{ $tab['title'] }}
                            </button>
                        @endforeach
                    </nav>
                </div>

                <div class="tabs-content p-6">
                    @foreach($components['tabs'] as $tab)
                        <div id="{{ $tab['id'] }}" class="tab-panel {{ $tab['active'] ? '' : 'hidden' }}">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">{{ $tab['title'] }} Content</h3>
                            <p class="text-gray-700 leading-relaxed">{{ $tab['content'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- Modal Components Section -->
    <section class="mb-16">
        <h2 class="text-3xl font-semibold text-gray-900 mb-8 text-center">Modal Components</h2>

        <div class="flex justify-center space-x-4">
            @foreach($components['modals'] as $modal)
                <button onclick="openModalComponent('{{ $modal['id'] }}')"
                        class="modal-trigger bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition-colors">
                    {{ $modal['trigger_text'] }}
                </button>

                <!-- Modal Component -->
                <div id="{{ $modal['id'] }}" class="modal-component fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
                    <div class="modal-dialog bg-white rounded-lg shadow-xl max-w-md mx-4 transform transition-all">
                        <div class="modal-header border-b border-gray-200 p-6 pb-4">
                            <div class="flex items-center justify-between">
                                <h3 class="modal-title text-lg font-semibold text-gray-900">{{ $modal['title'] }}</h3>
                                <button onclick="closeModalComponent('{{ $modal['id'] }}')" class="modal-close text-gray-400 hover:text-gray-600">
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="modal-body p-6">
                            <p class="modal-content text-gray-700">{{ $modal['content'] }}</p>
                        </div>
                        <div class="modal-footer border-t border-gray-200 p-6 pt-4">
                            <div class="flex justify-end space-x-3">
                                <button onclick="closeModalComponent('{{ $modal['id'] }}')"
                                        class="modal-cancel px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                                    Cancel
                                </button>
                                <button onclick="closeModalComponent('{{ $modal['id'] }}')"
                                        class="modal-confirm bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                                    Confirm
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Nested Components Section -->
    <section class="mb-16">
        <h2 class="text-3xl font-semibold text-gray-900 mb-8 text-center">Nested Components</h2>

        <div class="max-w-4xl mx-auto">
            <!-- Accordion Component (Nested) -->
            <div class="accordion-component bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="accordion-header bg-gray-50 p-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Accordion Component</h3>
                </div>

                <div class="accordion-body divide-y divide-gray-200">
                    @foreach($nested_components['accordion']['items'] as $index => $item)
                        <div class="accordion-item">
                            <button onclick="toggleAccordionItem('nested-acc-{{ $index }}')"
                                    class="accordion-trigger w-full p-4 text-left hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition-colors">
                                <div class="flex justify-between items-center">
                                    <span class="accordion-title font-medium text-gray-900">{{ $item['title'] }}</span>
                                    <svg class="accordion-icon w-5 h-5 text-gray-500 transform transition-transform {{ $item['expanded'] ? 'rotate-180' : '' }}"
                                         id="nested-acc-{{ $index }}-icon" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </button>
                            <div id="nested-acc-{{ $index }}" class="accordion-content {{ $item['expanded'] ? '' : 'hidden' }} p-4 bg-gray-50">
                                <p class="accordion-text text-gray-700">{{ $item['content'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Component Section -->
    <section class="mb-16">
        <h2 class="text-3xl font-semibold text-gray-900 mb-8 text-center">Testimonials Component</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-6xl mx-auto">
            @foreach($component_data['testimonials'] as $testimonial)
                <!-- Testimonial Component -->
                <div class="testimonial-component bg-white p-8 rounded-lg shadow-lg">
                    <div class="testimonial-content mb-6">
                        <p class="testimonial-text text-gray-700 italic text-lg leading-relaxed">
                            "{{ $testimonial['content'] }}"
                        </p>
                    </div>

                    <div class="testimonial-rating mb-4">
                        <div class="flex items-center space-x-1">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-5 h-5 {{ $i <= $testimonial['rating'] ? 'text-yellow-400' : 'text-gray-300' }}"
                                     fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            @endfor
                        </div>
                    </div>

                    <div class="testimonial-author flex items-center">
                        <img src="{{ $testimonial['avatar'] }}" alt="{{ $testimonial['name'] }}"
                             class="testimonial-avatar w-12 h-12 rounded-full mr-4 object-cover">
                        <div class="testimonial-info">
                            <h4 class="testimonial-name font-semibold text-gray-900">{{ $testimonial['name'] }}</h4>
                            <p class="testimonial-company text-sm text-gray-600">{{ $testimonial['company'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Slot Examples Section -->
    <section class="mb-16">
        <h2 class="text-3xl font-semibold text-gray-900 mb-8 text-center">Component Slots Example</h2>

        <div class="max-w-4xl mx-auto">
            <!-- Layout Component with Slots -->
            <div class="layout-component bg-white rounded-lg shadow-lg overflow-hidden">
                <!-- Header Slot -->
                <header class="layout-header bg-blue-600 text-white p-6">
                    <div class="slot-content">
                        <h3 class="text-xl font-semibold">{{ $slots_examples['header_slot'] }}</h3>
                    </div>
                </header>

                <div class="layout-body flex">
                    <!-- Main Content Area -->
                    <main class="layout-main flex-1 p-6">
                        <div class="slot-content">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Main Content Slot</h3>
                            <p class="text-gray-700 mb-4">
                                This is the main content area that demonstrates how slots work in Blade components.
                                The content here can be dynamically passed to the component.
                            </p>
                            <div class="bg-gray-100 p-4 rounded">
                                <code class="text-sm">
                                    &lt;x-layout&gt;<br>
                                    &nbsp;&nbsp;&lt;x-slot name="header"&gt;Header Content&lt;/x-slot&gt;<br>
                                    &nbsp;&nbsp;&lt;x-slot name="sidebar"&gt;Sidebar Content&lt;/x-slot&gt;<br>
                                    &nbsp;&nbsp;Main content goes here<br>
                                    &lt;/x-layout&gt;
                                </code>
                            </div>
                        </div>
                    </main>

                    <!-- Sidebar Slot -->
                    <aside class="layout-sidebar w-64 bg-gray-50 p-6 border-l border-gray-200">
                        <div class="slot-content">
                            <h4 class="font-semibold text-gray-900 mb-3">Sidebar Slot</h4>
                            <p class="text-sm text-gray-700">{{ $slots_examples['sidebar_slot'] }}</p>
                        </div>
                    </aside>
                </div>

                <!-- Footer Slot -->
                <footer class="layout-footer bg-gray-800 text-white p-6">
                    <div class="slot-content text-center">
                        <p class="text-sm">{{ $slots_examples['footer_slot'] }}</p>
                    </div>
                </footer>
            </div>
        </div>
    </section>

    <!-- Component Documentation -->
    <section class="mb-12">
        <h2 class="text-3xl font-semibold text-gray-900 mb-8 text-center">Component Documentation</h2>

        <div class="bg-gray-50 p-8 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="component-doc">
                    <h3 class="font-semibold text-gray-900 mb-3">Card Component</h3>
                    <ul class="text-sm text-gray-700 space-y-1">
                        <li><strong>Props:</strong> title, description, image, link</li>
                        <li><strong>Slots:</strong> None (data-driven)</li>
                        <li><strong>Classes:</strong> .card-component, .card-content</li>
                        <li><strong>Events:</strong> Click, hover</li>
                    </ul>
                </div>

                <div class="component-doc">
                    <h3 class="font-semibold text-gray-900 mb-3">Alert Component</h3>
                    <ul class="text-sm text-gray-700 space-y-1">
                        <li><strong>Props:</strong> type, message, dismissible</li>
                        <li><strong>Slots:</strong> icon, content</li>
                        <li><strong>Classes:</strong> .alert, .alert-{type}</li>
                        <li><strong>Events:</strong> Close, dismiss</li>
                    </ul>
                </div>

                <div class="component-doc">
                    <h3 class="font-semibold text-gray-900 mb-3">Modal Component</h3>
                    <ul class="text-sm text-gray-700 space-y-1">
                        <li><strong>Props:</strong> title, size, closable</li>
                        <li><strong>Slots:</strong> header, body, footer</li>
                        <li><strong>Classes:</strong> .modal-component, .modal-dialog</li>
                        <li><strong>Events:</strong> Open, close, confirm</li>
                    </ul>
                </div>

                <div class="component-doc">
                    <h3 class="font-semibold text-gray-900 mb-3">Tabs Component</h3>
                    <ul class="text-sm text-gray-700 space-y-1">
                        <li><strong>Props:</strong> tabs array, active tab</li>
                        <li><strong>Slots:</strong> tab content</li>
                        <li><strong>Classes:</strong> .tabs-component, .tab-panel</li>
                        <li><strong>Events:</strong> Tab change</li>
                    </ul>
                </div>

                <div class="component-doc">
                    <h3 class="font-semibold text-gray-900 mb-3">Accordion Component</h3>
                    <ul class="text-sm text-gray-700 space-y-1">
                        <li><strong>Props:</strong> items array, multiple open</li>
                        <li><strong>Slots:</strong> item content</li>
                        <li><strong>Classes:</strong> .accordion-component, .accordion-item</li>
                        <li><strong>Events:</strong> Expand, collapse</li>
                    </ul>
                </div>

                <div class="component-doc">
                    <h3 class="font-semibold text-gray-900 mb-3">Testimonial Component</h3>
                    <ul class="text-sm text-gray-700 space-y-1">
                        <li><strong>Props:</strong> content, author, rating, avatar</li>
                        <li><strong>Slots:</strong> content, author info</li>
                        <li><strong>Classes:</strong> .testimonial-component</li>
                        <li><strong>Events:</strong> None</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection

@section('scripts')
<script>
// Tab component functionality
function showTabContent(tabId) {
    // Hide all tab panels
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.add('hidden');
    });

    // Remove active state from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-blue-600', 'text-blue-600');
        button.classList.add('border-transparent', 'text-gray-700');
    });

    // Show selected tab panel
    document.getElementById(tabId).classList.remove('hidden');

    // Add active state to clicked button
    const activeButton = document.querySelector(`[data-tab="${tabId}"]`);
    if (activeButton) {
        activeButton.classList.remove('border-transparent', 'text-gray-700');
        activeButton.classList.add('border-blue-600', 'text-blue-600');
    }

    console.log('Tab changed to:', tabId);
}

// Accordion component functionality
function toggleAccordionItem(itemId) {
    const content = document.getElementById(itemId);
    const icon = document.getElementById(itemId + '-icon');

    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        icon.style.transform = 'rotate(180deg)';
    } else {
        content.classList.add('hidden');
        icon.style.transform = 'rotate(0deg)';
    }

    console.log('Accordion item toggled:', itemId);
}

// Modal component functionality
function openModalComponent(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    console.log('Modal opened:', modalId);
}

function closeModalComponent(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = 'auto';

    console.log('Modal closed:', modalId);
}

// Alert component functionality
function closeAlert(button) {
    const alert = button.closest('.alert');
    alert.style.transition = 'opacity 0.3s ease';
    alert.style.opacity = '0';

    setTimeout(() => {
        alert.remove();
    }, 300);

    console.log('Alert closed');
}

// Component interaction tracking
document.addEventListener('DOMContentLoaded', function() {
    console.log('Components page loaded with components:', {
        cards: {{ count($components['cards']) }},
        alerts: {{ count($components['alerts']) }},
        tabs: {{ count($components['tabs']) }},
        modals: {{ count($components['modals']) }},
        testimonials: {{ count($component_data['testimonials']) }}
    });

    // Add click tracking to all components
    const components = document.querySelectorAll('[class*="-component"]');
    components.forEach((component, index) => {
        component.addEventListener('click', function(e) {
            console.log('Component clicked:', {
                type: this.className.split(' ').find(cls => cls.includes('-component')),
                index: index,
                target: e.target.tagName.toLowerCase(),
                timestamp: new Date().toISOString()
            });
        });
    });

    // Track card component interactions
    const cardLinks = document.querySelectorAll('.card-link');
    cardLinks.forEach((link, index) => {
        link.addEventListener('click', function(e) {
            console.log('Card link clicked:', {
                card_index: index,
                text: this.textContent.trim(),
                href: this.href
            });
        });
    });

    // Track testimonial component views
    const testimonials = document.querySelectorAll('.testimonial-component');
    testimonials.forEach((testimonial, index) => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    console.log('Testimonial viewed:', {
                        index: index,
                        name: testimonial.querySelector('.testimonial-name')?.textContent,
                        rating: testimonial.querySelectorAll('.text-yellow-400').length
                    });
                }
            });
        }, { threshold: 0.5 });

        observer.observe(testimonial);
    });

    // Component state management
    window.componentStates = {
        activeTab: 'tab1',
        openAccordions: [],
        openModals: [],
        dismissedAlerts: []
    };

    // Auto-close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-component')) {
            const modalId = e.target.id;
            closeModalComponent(modalId);
        }
    });

    // Keyboard navigation for components
    document.addEventListener('keydown', function(e) {
        // Escape key closes modals
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal-component.flex');
            openModals.forEach(modal => {
                closeModalComponent(modal.id);
            });
        }

        // Tab navigation within tabs component
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
            const focusedTab = document.activeElement;
            if (focusedTab.classList.contains('tab-button')) {
                e.preventDefault();
                const tabs = Array.from(document.querySelectorAll('.tab-button'));
                const currentIndex = tabs.indexOf(focusedTab);
                let nextIndex;

                if (e.key === 'ArrowRight') {
                    nextIndex = (currentIndex + 1) % tabs.length;
                } else {
                    nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                }

                tabs[nextIndex].focus();
                tabs[nextIndex].click();
            }
        }
    });

    // Component performance monitoring
    const componentObserver = new PerformanceObserver((list) => {
        list.getEntries().forEach((entry) => {
            if (entry.name.includes('component')) {
                console.log('Component performance:', {
                    name: entry.name,
                    duration: entry.duration,
                    startTime: entry.startTime
                });
            }
        });
    });

    if (window.PerformanceObserver) {
        componentObserver.observe({ entryTypes: ['measure'] });
    }
});

// Component utility functions
window.ComponentUtils = {
    getComponentState: function(componentId) {
        return window.componentStates || {};
    },

    updateComponentState: function(componentId, state) {
        if (!window.componentStates) {
            window.componentStates = {};
        }
        window.componentStates[componentId] = state;
    },

    refreshComponent: function(componentId) {
        const component = document.getElementById(componentId);
        if (component) {
            // Trigger a refresh event
            component.dispatchEvent(new CustomEvent('refresh', {
                detail: { componentId }
            }));
        }
    }
};
</script>
@endsection