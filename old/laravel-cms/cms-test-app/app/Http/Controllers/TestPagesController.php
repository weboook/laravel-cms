<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TestPagesController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth')->except(['simple', 'translated']);
    }

    /**
     * Display a simple test page with basic text content.
     *
     * This page contains plain text, headers, paragraphs, and lists
     * for testing basic CMS content editing functionality.
     */
    public function simple(Request $request)
    {
        $data = [
            'title' => 'Simple Test Page',
            'subtitle' => 'Basic Content Testing',
            'description' => 'This page contains simple HTML elements for testing the CMS editor functionality.',
            'heading' => 'Welcome to the Simple Test Page',
            'intro_text' => 'This page is designed to test basic content editing features of the Laravel CMS package.',
            'features' => [
                'Plain text editing',
                'Header modification',
                'Paragraph content updates',
                'List item management',
                'Link editing'
            ],
            'sections' => [
                [
                    'title' => 'Content Section 1',
                    'content' => 'This is the first content section that can be edited through the CMS interface.'
                ],
                [
                    'title' => 'Content Section 2',
                    'content' => 'This is the second content section with different text content for testing purposes.'
                ],
                [
                    'title' => 'Content Section 3',
                    'content' => 'This third section provides additional content for comprehensive testing scenarios.'
                ]
            ],
            'footer_text' => 'This is footer content that can be modified through the CMS.',
            'last_updated' => Carbon::now()->format('F j, Y'),
            'test_mode' => config('cms-test.enabled', true),
            'debug_info' => $request->get('debug') ? [
                'timestamp' => now()->toISOString(),
                'locale' => App::getLocale(),
                'session_id' => Session::getId(),
                'user_agent' => $request->userAgent()
            ] : null
        ];

        return view('test-pages.simple', $data);
    }

    /**
     * Display a translated test page with multi-language content.
     *
     * This page uses Laravel's localization features with @lang and __() helpers
     * to test translation functionality.
     */
    public function translated(Request $request)
    {
        $locale = $request->get('locale', App::getLocale());

        // Set locale if provided
        if ($locale && in_array($locale, config('cms-test.supported_locales', ['en', 'es', 'fr']))) {
            App::setLocale($locale);
            Session::put('locale', $locale);
        }

        $data = [
            'current_locale' => App::getLocale(),
            'available_locales' => config('cms-test.supported_locales', ['en', 'es', 'fr']),
            'locale_names' => [
                'en' => 'English',
                'es' => 'Español',
                'fr' => 'Français'
            ],
            'test_translations' => [
                'simple_key' => __('test.simple_message'),
                'parameterized_key' => __('test.welcome_user', ['name' => 'Test User']),
                'pluralized_key' => trans_choice('test.item_count', 5, ['count' => 5]),
                'nested_key' => __('test.nested.deep.value')
            ],
            'content_sections' => [
                [
                    'title_key' => 'test.section_1_title',
                    'content_key' => 'test.section_1_content'
                ],
                [
                    'title_key' => 'test.section_2_title',
                    'content_key' => 'test.section_2_content'
                ]
            ],
            'navigation_items' => [
                ['key' => 'test.nav.home', 'url' => route('test.simple')],
                ['key' => 'test.nav.translated', 'url' => route('test.translated')],
                ['key' => 'test.nav.complex', 'url' => route('test.complex')]
            ],
            'metadata' => [
                'page_title' => __('test.page_titles.translated'),
                'meta_description' => __('test.meta.description'),
                'meta_keywords' => __('test.meta.keywords')
            ]
        ];

        return view('test-pages.translated', $data);
    }

    /**
     * Display a complex test page with mixed content types.
     *
     * This page includes images, links, various HTML elements,
     * and Blade directives for comprehensive testing.
     */
    public function complex(Request $request)
    {
        $data = [
            'title' => 'Complex Content Test Page',
            'hero' => [
                'heading' => 'Advanced CMS Testing',
                'subheading' => 'Complex Content Management',
                'background_image' => '/images/test/hero-bg.jpg',
                'cta_text' => 'Explore Features',
                'cta_url' => route('test.components')
            ],
            'media_gallery' => [
                [
                    'type' => 'image',
                    'src' => '/images/test/gallery-1.jpg',
                    'alt' => 'Test gallery image 1',
                    'caption' => 'This is a test image for gallery testing'
                ],
                [
                    'type' => 'image',
                    'src' => '/images/test/gallery-2.jpg',
                    'alt' => 'Test gallery image 2',
                    'caption' => 'Another test image for CMS functionality'
                ],
                [
                    'type' => 'video',
                    'src' => '/videos/test/demo.mp4',
                    'poster' => '/images/test/video-poster.jpg',
                    'caption' => 'Test video content for media management'
                ]
            ],
            'links' => [
                'internal' => [
                    ['text' => 'Home Page', 'url' => route('test.simple')],
                    ['text' => 'Translation Test', 'url' => route('test.translated')],
                    ['text' => 'Component Test', 'url' => route('test.components')]
                ],
                'external' => [
                    ['text' => 'Laravel Documentation', 'url' => 'https://laravel.com/docs'],
                    ['text' => 'GitHub Repository', 'url' => 'https://github.com/laravel/laravel'],
                    ['text' => 'Community Forum', 'url' => 'https://laracasts.com/discuss']
                ]
            ],
            'content_blocks' => [
                [
                    'type' => 'text',
                    'title' => 'Rich Text Content',
                    'content' => 'This block contains <strong>rich text</strong> with <em>formatting</em> and <a href="#test">internal links</a>.'
                ],
                [
                    'type' => 'quote',
                    'content' => 'This is a blockquote for testing quote content editing in the CMS.',
                    'author' => 'Test Author',
                    'source' => 'CMS Testing Guide'
                ],
                [
                    'type' => 'code',
                    'title' => 'Code Example',
                    'language' => 'php',
                    'content' => '<?php\n\necho "Hello, CMS World!";\n\n// This is a code block for testing'
                ]
            ],
            'forms' => [
                [
                    'id' => 'contact-form',
                    'title' => 'Contact Form',
                    'action' => route('test.forms'),
                    'method' => 'POST',
                    'fields' => [
                        ['type' => 'text', 'name' => 'name', 'label' => 'Full Name', 'required' => true],
                        ['type' => 'email', 'name' => 'email', 'label' => 'Email Address', 'required' => true],
                        ['type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'required' => true]
                    ]
                ]
            ],
            'sidebar_widgets' => [
                [
                    'type' => 'recent_posts',
                    'title' => 'Recent Updates',
                    'items' => [
                        ['title' => 'CMS Update 1.0', 'date' => '2024-01-15', 'url' => '#'],
                        ['title' => 'New Features Released', 'date' => '2024-01-10', 'url' => '#'],
                        ['title' => 'Bug Fixes and Improvements', 'date' => '2024-01-05', 'url' => '#']
                    ]
                ],
                [
                    'type' => 'social_links',
                    'title' => 'Follow Us',
                    'links' => [
                        ['platform' => 'twitter', 'url' => 'https://twitter.com/test', 'icon' => 'fab fa-twitter'],
                        ['platform' => 'facebook', 'url' => 'https://facebook.com/test', 'icon' => 'fab fa-facebook'],
                        ['platform' => 'linkedin', 'url' => 'https://linkedin.com/test', 'icon' => 'fab fa-linkedin']
                    ]
                ]
            ],
            'metadata' => [
                'page_title' => 'Complex Content Test - CMS Testing',
                'meta_description' => 'Complex content page for testing advanced CMS features including media, forms, and rich content.',
                'canonical_url' => $request->url(),
                'og_image' => '/images/test/og-complex.jpg'
            ],
            'performance_data' => $this->getPerformanceData(),
            'cache_enabled' => Cache::getStore() instanceof \Illuminate\Cache\ArrayStore ? false : true
        ];

        return view('test-pages.complex', $data);
    }

    /**
     * Display a test page with Blade components.
     *
     * This page showcases custom Blade components, slots,
     * and component composition for testing component-based content.
     */
    public function components(Request $request)
    {
        $data = [
            'title' => 'Blade Components Test Page',
            'components' => [
                'cards' => [
                    [
                        'title' => 'Feature Card 1',
                        'description' => 'This is a test card component with editable content.',
                        'image' => '/images/test/card-1.jpg',
                        'link' => ['text' => 'Learn More', 'url' => '#card1']
                    ],
                    [
                        'title' => 'Feature Card 2',
                        'description' => 'Another card component for testing component editing.',
                        'image' => '/images/test/card-2.jpg',
                        'link' => ['text' => 'Discover', 'url' => '#card2']
                    ],
                    [
                        'title' => 'Feature Card 3',
                        'description' => 'Third card component with different content structure.',
                        'image' => '/images/test/card-3.jpg',
                        'link' => ['text' => 'Explore', 'url' => '#card3']
                    ]
                ],
                'alerts' => [
                    ['type' => 'success', 'message' => 'This is a success alert component for testing.'],
                    ['type' => 'warning', 'message' => 'This is a warning alert with editable content.'],
                    ['type' => 'info', 'message' => 'Information alert component for CMS testing.']
                ],
                'tabs' => [
                    [
                        'id' => 'tab1',
                        'title' => 'Tab One',
                        'content' => 'Content for the first tab. This content can be edited through the CMS.',
                        'active' => true
                    ],
                    [
                        'id' => 'tab2',
                        'title' => 'Tab Two',
                        'content' => 'Second tab content with different information for testing.',
                        'active' => false
                    ],
                    [
                        'id' => 'tab3',
                        'title' => 'Tab Three',
                        'content' => 'Third tab with additional content for comprehensive testing.',
                        'active' => false
                    ]
                ],
                'modals' => [
                    [
                        'id' => 'test-modal-1',
                        'title' => 'Test Modal 1',
                        'content' => 'This is modal content that can be edited through the CMS interface.',
                        'trigger_text' => 'Open Modal 1'
                    ],
                    [
                        'id' => 'test-modal-2',
                        'title' => 'Test Modal 2',
                        'content' => 'Another modal with different content for component testing.',
                        'trigger_text' => 'Open Modal 2'
                    ]
                ]
            ],
            'nested_components' => [
                'accordion' => [
                    'items' => [
                        [
                            'title' => 'Accordion Item 1',
                            'content' => 'Content inside the first accordion item.',
                            'expanded' => true
                        ],
                        [
                            'title' => 'Accordion Item 2',
                            'content' => 'Second accordion item with different content.',
                            'expanded' => false
                        ]
                    ]
                ]
            ],
            'component_data' => [
                'testimonials' => [
                    [
                        'name' => 'John Doe',
                        'company' => 'Test Company',
                        'content' => 'This CMS is amazing for content management. Easy to use and very powerful.',
                        'rating' => 5,
                        'avatar' => '/images/test/avatar-1.jpg'
                    ],
                    [
                        'name' => 'Jane Smith',
                        'company' => 'Another Company',
                        'content' => 'Great experience with this content management system. Highly recommended.',
                        'rating' => 5,
                        'avatar' => '/images/test/avatar-2.jpg'
                    ]
                ]
            ],
            'slots_examples' => [
                'header_slot' => 'Custom header content for slot testing',
                'footer_slot' => 'Custom footer content that can be edited',
                'sidebar_slot' => 'Sidebar content for component slot testing'
            ]
        ];

        return view('test-pages.components', $data);
    }

    /**
     * Display a dynamic content test page.
     *
     * This page demonstrates dynamic content generation,
     * real-time data, and conditional content rendering.
     */
    public function dynamic(Request $request)
    {
        $data = [
            'title' => 'Dynamic Content Test Page',
            'timestamp' => now(),
            'server_time' => Carbon::now()->format('Y-m-d H:i:s T'),
            'user_info' => [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'locale' => App::getLocale(),
                'timezone' => config('app.timezone')
            ],
            'dynamic_content' => [
                'random_number' => random_int(1000, 9999),
                'quote_of_the_day' => $this->getRandomQuote(),
                'weather_widget' => $this->getMockWeatherData(),
                'visitor_count' => $this->getVisitorCount(),
                'latest_news' => $this->getLatestNews()
            ],
            'conditional_content' => [
                'show_promotion' => random_int(1, 100) <= 30, // 30% chance
                'user_type' => $request->user() ? 'authenticated' : 'guest',
                'feature_flags' => [
                    'new_design' => config('cms-test.features.new_design', false),
                    'beta_features' => config('cms-test.features.beta_features', false),
                    'analytics' => config('cms-test.features.analytics', true)
                ]
            ],
            'real_time_data' => [
                'system_load' => $this->getSystemLoad(),
                'memory_usage' => $this->getMemoryUsage(),
                'cache_stats' => $this->getCacheStats()
            ],
            'user_personalization' => [
                'preferred_theme' => Session::get('theme', 'light'),
                'language_preference' => Session::get('locale', 'en'),
                'recent_pages' => Session::get('recent_pages', []),
                'user_settings' => $request->user() ? $request->user()->settings ?? [] : []
            ],
            'api_data' => [
                'placeholder_posts' => $this->getPlaceholderPosts(),
                'sample_users' => $this->getSampleUsers()
            ]
        ];

        // Store this page visit in recent pages
        $recentPages = Session::get('recent_pages', []);
        array_unshift($recentPages, [
            'title' => 'Dynamic Content',
            'url' => $request->url(),
            'timestamp' => now()->toISOString()
        ]);
        Session::put('recent_pages', array_slice($recentPages, 0, 5));

        return view('test-pages.dynamic', $data);
    }

    /**
     * Display a forms test page with validation.
     *
     * This page contains various form types and validation
     * scenarios for testing form content management.
     */
    public function forms(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->handleFormSubmission($request);
        }

        $data = [
            'title' => 'Forms Test Page',
            'forms' => [
                'contact' => [
                    'title' => 'Contact Form',
                    'description' => 'A simple contact form for testing form content editing.',
                    'action' => route('test.forms'),
                    'method' => 'POST',
                    'fields' => [
                        [
                            'type' => 'text',
                            'name' => 'name',
                            'label' => 'Full Name',
                            'placeholder' => 'Enter your full name',
                            'required' => true,
                            'validation' => 'required|string|max:255'
                        ],
                        [
                            'type' => 'email',
                            'name' => 'email',
                            'label' => 'Email Address',
                            'placeholder' => 'your.email@example.com',
                            'required' => true,
                            'validation' => 'required|email'
                        ],
                        [
                            'type' => 'tel',
                            'name' => 'phone',
                            'label' => 'Phone Number',
                            'placeholder' => '+1 (555) 123-4567',
                            'required' => false,
                            'validation' => 'nullable|string|max:20'
                        ],
                        [
                            'type' => 'select',
                            'name' => 'subject',
                            'label' => 'Subject',
                            'required' => true,
                            'options' => [
                                '' => 'Select a subject',
                                'general' => 'General Inquiry',
                                'support' => 'Technical Support',
                                'sales' => 'Sales Question',
                                'feedback' => 'Feedback'
                            ],
                            'validation' => 'required|in:general,support,sales,feedback'
                        ],
                        [
                            'type' => 'textarea',
                            'name' => 'message',
                            'label' => 'Message',
                            'placeholder' => 'Enter your message here...',
                            'rows' => 5,
                            'required' => true,
                            'validation' => 'required|string|min:10|max:1000'
                        ],
                        [
                            'type' => 'checkbox',
                            'name' => 'newsletter',
                            'label' => 'Subscribe to newsletter',
                            'value' => '1',
                            'required' => false
                        ]
                    ]
                ],
                'survey' => [
                    'title' => 'Feedback Survey',
                    'description' => 'Help us improve by providing your feedback.',
                    'action' => route('test.forms', ['type' => 'survey']),
                    'method' => 'POST',
                    'fields' => [
                        [
                            'type' => 'radio',
                            'name' => 'satisfaction',
                            'label' => 'How satisfied are you with our service?',
                            'required' => true,
                            'options' => [
                                'very_satisfied' => 'Very Satisfied',
                                'satisfied' => 'Satisfied',
                                'neutral' => 'Neutral',
                                'dissatisfied' => 'Dissatisfied',
                                'very_dissatisfied' => 'Very Dissatisfied'
                            ]
                        ],
                        [
                            'type' => 'checkbox_group',
                            'name' => 'features[]',
                            'label' => 'Which features do you use most? (Select all that apply)',
                            'options' => [
                                'content_editing' => 'Content Editing',
                                'translation' => 'Translation Management',
                                'file_management' => 'File Management',
                                'user_management' => 'User Management',
                                'analytics' => 'Analytics'
                            ]
                        ],
                        [
                            'type' => 'range',
                            'name' => 'recommendation',
                            'label' => 'How likely are you to recommend us? (0-10)',
                            'min' => 0,
                            'max' => 10,
                            'value' => 5
                        ]
                    ]
                ]
            ],
            'form_validation_rules' => [
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'phone' => 'nullable|string|max:20',
                'subject' => 'required|in:general,support,sales,feedback',
                'message' => 'required|string|min:10|max:1000',
                'newsletter' => 'boolean',
                'satisfaction' => 'required_if:type,survey|in:very_satisfied,satisfied,neutral,dissatisfied,very_dissatisfied',
                'features' => 'array',
                'recommendation' => 'integer|min:0|max:10'
            ],
            'error_messages' => [
                'name.required' => 'Please enter your full name.',
                'email.required' => 'Email address is required.',
                'email.email' => 'Please enter a valid email address.',
                'message.required' => 'Please enter your message.',
                'message.min' => 'Message must be at least 10 characters long.',
                'subject.required' => 'Please select a subject.'
            ]
        ];

        return view('test-pages.forms', $data);
    }

    /**
     * Handle form submissions for testing.
     */
    private function handleFormSubmission(Request $request)
    {
        $type = $request->get('type', 'contact');

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'required|string|min:10|max:1000'
        ];

        if ($type === 'survey') {
            $rules['satisfaction'] = 'required|in:very_satisfied,satisfied,neutral,dissatisfied,very_dissatisfied';
        }

        $request->validate($rules);

        // Store submission in session for demo purposes
        Session::flash('form_success', 'Thank you! Your ' . $type . ' has been submitted successfully.');

        return redirect()->route('test.forms');
    }

    /**
     * Get performance data for testing.
     */
    private function getPerformanceData()
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - LARAVEL_START,
            'included_files' => count(get_included_files())
        ];
    }

    /**
     * Get a random quote for dynamic content.
     */
    private function getRandomQuote()
    {
        $quotes = [
            'The best time to plant a tree was 20 years ago. The second best time is now.',
            'Code is like humor. When you have to explain it, it\'s bad.',
            'First, solve the problem. Then, write the code.',
            'Experience is the name everyone gives to their mistakes.',
            'In order to be irreplaceable, one must always be different.'
        ];

        return $quotes[array_rand($quotes)];
    }

    /**
     * Get mock weather data.
     */
    private function getMockWeatherData()
    {
        $conditions = ['Sunny', 'Cloudy', 'Rainy', 'Partly Cloudy', 'Windy'];

        return [
            'condition' => $conditions[array_rand($conditions)],
            'temperature' => random_int(15, 30) . '°C',
            'humidity' => random_int(30, 80) . '%',
            'wind_speed' => random_int(5, 25) . ' km/h'
        ];
    }

    /**
     * Get visitor count (mock).
     */
    private function getVisitorCount()
    {
        return Cache::remember('visitor_count', 60, function () {
            return random_int(1000, 5000);
        });
    }

    /**
     * Get latest news (mock).
     */
    private function getLatestNews()
    {
        return [
            ['title' => 'CMS Update Released', 'time' => '2 hours ago'],
            ['title' => 'New Features Available', 'time' => '1 day ago'],
            ['title' => 'Performance Improvements', 'time' => '3 days ago']
        ];
    }

    /**
     * Get system load (mock).
     */
    private function getSystemLoad()
    {
        return [
            'cpu' => random_int(10, 80) . '%',
            'memory' => random_int(40, 90) . '%',
            'disk' => random_int(20, 70) . '%'
        ];
    }

    /**
     * Get memory usage information.
     */
    private function getMemoryUsage()
    {
        return [
            'current' => number_format(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'peak' => number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
        ];
    }

    /**
     * Get cache statistics.
     */
    private function getCacheStats()
    {
        return [
            'driver' => config('cache.default'),
            'enabled' => Cache::getStore() instanceof \Illuminate\Cache\ArrayStore ? false : true,
            'items' => random_int(50, 200)
        ];
    }

    /**
     * Get placeholder posts from API (mock).
     */
    private function getPlaceholderPosts()
    {
        return [
            ['id' => 1, 'title' => 'Sample Post 1', 'excerpt' => 'This is a sample post excerpt...'],
            ['id' => 2, 'title' => 'Sample Post 2', 'excerpt' => 'Another sample post excerpt...'],
            ['id' => 3, 'title' => 'Sample Post 3', 'excerpt' => 'Third sample post excerpt...']
        ];
    }

    /**
     * Get sample users (mock).
     */
    private function getSampleUsers()
    {
        return [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'role' => 'Editor'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'role' => 'Admin'],
            ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'role' => 'Contributor']
        ];
    }
}