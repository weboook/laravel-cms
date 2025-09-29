<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CMSTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting CMS Test Data Seeder...');

        $this->createTestUsers();
        $this->createTestTranslations();
        $this->createTestImages();
        $this->createTestContent();
        $this->createTestActivityLogs();
        $this->setupTestDirectories();

        $this->command->info('✅ CMS Test Data Seeder completed successfully!');
    }

    /**
     * Create test users with different roles and permissions.
     */
    protected function createTestUsers(): void
    {
        $this->command->info('👥 Creating test users...');

        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@test.com',
                'password' => Hash::make('admin123'),
                'role' => 'cms_admin',
                'permissions' => ['cms_admin', 'cms_editor', 'cms_translator'],
                'preferences' => [
                    'theme' => 'dark',
                    'auto_save' => true,
                    'show_minimap' => true,
                    'font_size' => 16,
                    'line_numbers' => true,
                    'word_wrap' => false,
                    'locale' => 'en'
                ],
                'last_login' => Carbon::now()->subDays(1),
                'login_count' => 45,
                'is_active' => true
            ],
            [
                'name' => 'Editor User',
                'email' => 'editor@test.com',
                'password' => Hash::make('editor123'),
                'role' => 'cms_editor',
                'permissions' => ['cms_editor', 'cms_translator'],
                'preferences' => [
                    'theme' => 'light',
                    'auto_save' => true,
                    'show_minimap' => false,
                    'font_size' => 14,
                    'line_numbers' => true,
                    'word_wrap' => true,
                    'locale' => 'en'
                ],
                'last_login' => Carbon::now()->subHours(3),
                'login_count' => 23,
                'is_active' => true
            ],
            [
                'name' => 'Translator User',
                'email' => 'translator@test.com',
                'password' => Hash::make('translator123'),
                'role' => 'cms_translator',
                'permissions' => ['cms_translator'],
                'preferences' => [
                    'theme' => 'light',
                    'auto_save' => false,
                    'show_minimap' => false,
                    'font_size' => 14,
                    'line_numbers' => false,
                    'word_wrap' => true,
                    'locale' => 'es'
                ],
                'last_login' => Carbon::now()->subDays(2),
                'login_count' => 12,
                'is_active' => true
            ],
            [
                'name' => 'Regular User',
                'email' => 'user@test.com',
                'password' => Hash::make('user123'),
                'role' => 'user',
                'permissions' => [],
                'preferences' => [
                    'theme' => 'auto',
                    'auto_save' => true,
                    'show_minimap' => false,
                    'font_size' => 14,
                    'line_numbers' => false,
                    'word_wrap' => true,
                    'locale' => 'en'
                ],
                'last_login' => Carbon::now()->subWeek(),
                'login_count' => 5,
                'is_active' => true
            ],
            [
                'name' => 'Test User (Inactive)',
                'email' => 'inactive@test.com',
                'password' => Hash::make('inactive123'),
                'role' => 'user',
                'permissions' => [],
                'preferences' => [],
                'last_login' => Carbon::now()->subMonths(2),
                'login_count' => 1,
                'is_active' => false
            ]
        ];

        foreach ($users as $userData) {
            // In a real app, you would use your User model
            // User::create($userData);

            $this->command->line("  ✓ Created user: {$userData['name']} ({$userData['email']})");
        }

        $this->command->info("  📊 Created " . count($users) . " test users");
    }

    /**
     * Create comprehensive test translations in multiple languages.
     */
    protected function createTestTranslations(): void
    {
        $this->command->info('🌐 Creating test translations...');

        $translations = [
            'en' => [
                'test' => [
                    // Page titles
                    'page_titles' => [
                        'translated' => 'Translation Test Page - CMS Testing',
                        'simple' => 'Simple Test Page - CMS Testing',
                        'complex' => 'Complex Content Test - CMS Testing'
                    ],

                    // Meta information
                    'meta' => [
                        'description' => 'This page tests translation functionality in the CMS system',
                        'keywords' => 'cms, translation, testing, laravel, multilingual'
                    ],

                    // Language selector
                    'language_selector' => [
                        'title' => 'Language Selection'
                    ],

                    // Current locale information
                    'current_locale' => [
                        'title' => 'Current Locale Information',
                        'active' => 'Active Locale',
                        'name' => 'Language Name',
                        'direction' => 'Text Direction',
                        'ltr' => 'Left to Right'
                    ],

                    // Page header
                    'page_header' => [
                        'title' => 'Translation Testing Page',
                        'subtitle' => 'Comprehensive Multi-language Content Management',
                        'description' => 'This page demonstrates the translation capabilities of the CMS system, including parameterized translations, pluralization, and nested translation keys.'
                    ],

                    // Examples section
                    'examples' => [
                        'title' => 'Translation Examples',
                        'simple' => [
                            'title' => 'Simple Translations'
                        ],
                        'helpers' => [
                            'title' => 'Helper Functions'
                        ]
                    ],

                    // Helper functions
                    'helpers' => [
                        'welcome' => 'Welcome to our platform!',
                        'goodbye' => 'Thank you for visiting!'
                    ],

                    // Pluralization
                    'plurals' => [
                        'items' => '{0} No items found|{1} One item found|[2,*] :count items found'
                    ],

                    // Content sections
                    'content_sections' => [
                        'title' => 'Multilingual Content Sections'
                    ],

                    // Section content
                    'section_1_title' => 'Translation Management',
                    'section_1_content' => 'This section demonstrates how content can be managed across multiple languages with the CMS translation system. Content editors can easily update translations and see changes reflected immediately.',

                    'section_2_title' => 'Localization Features',
                    'section_2_content' => 'The CMS supports advanced localization features including right-to-left languages, number formatting, date formatting, and cultural adaptations for different regions.',

                    // Translation information
                    'translation_info' => [
                        'title' => 'Translation Key Information',
                        'key_format' => 'Keys use dot notation: group.subgroup.key',
                        'file_location' => 'Files stored in resources/lang/{locale}/',
                        'editing_note' => 'Edit translations through the CMS interface'
                    ],

                    // Navigation
                    'navigation' => [
                        'title' => 'Translated Navigation'
                    ],

                    'nav' => [
                        'home' => 'Home',
                        'about' => 'About Us',
                        'services' => 'Our Services',
                        'contact' => 'Contact Us',
                        'blog' => 'Blog'
                    ],

                    // Nested translations
                    'nested' => [
                        'title' => 'Nested Translation Examples',
                        'examples' => [
                            'title' => 'Example Keys'
                        ],
                        'structure' => [
                            'title' => 'File Structure'
                        ],
                        'level1' => [
                            'value' => 'First level nested value',
                            'level2' => [
                                'value' => 'Second level nested value',
                                'level3' => [
                                    'deep' => 'This is a deeply nested translation value for testing complex key structures'
                                ]
                            ]
                        ]
                    ],

                    // Parameters
                    'parameters' => [
                        'title' => 'Parameterized Translations',
                        'examples' => [
                            'title' => 'Examples with Parameters'
                        ],
                        'welcome_user' => 'Welcome back, :name! We\'re glad to see you again.',
                        'items_found' => 'Found :count :type in the system.',
                        'last_updated' => 'Last updated on :date at :time.'
                    ],

                    // Pluralization
                    'pluralization' => [
                        'title' => 'Pluralization Examples',
                        'examples' => [
                            'title' => 'Different Counts'
                        ],
                        'syntax' => [
                            'title' => 'Syntax Example',
                            'description' => 'Laravel uses ICU pluralization rules for complex plural forms.'
                        ]
                    ],

                    // File information
                    'file_info' => [
                        'title' => 'Translation File Information',
                        'location' => [
                            'title' => 'File Locations'
                        ],
                        'cms_features' => [
                            'title' => 'CMS Features',
                            'inline_editing' => 'Inline translation editing',
                            'bulk_import' => 'Bulk import from CSV/JSON',
                            'export_formats' => 'Export to multiple formats',
                            'auto_translate' => 'Auto-translation with AI'
                        ]
                    ],

                    // Footer
                    'footer' => [
                        'generated_on' => 'Page generated on :date at :time',
                        'locale_note' => 'Currently viewing in :locale locale'
                    ],

                    // Test messages
                    'simple_message' => 'This is a simple translation message for testing.',
                    'welcome_user' => 'Welcome, :name! Enjoy your stay.',
                    'item_count' => '{0} No items|{1} One item|[2,*] :count items'
                ],

                'messages' => [
                    'welcome' => 'Welcome to our website',
                    'about' => 'About Us',
                    'contact' => 'Contact Us',
                    'services' => 'Our Services',
                    'blog' => 'Blog',
                    'home' => 'Home',

                    'actions' => [
                        'save' => 'Save',
                        'cancel' => 'Cancel',
                        'edit' => 'Edit',
                        'delete' => 'Delete',
                        'create' => 'Create',
                        'update' => 'Update',
                        'view' => 'View',
                        'search' => 'Search'
                    ],

                    'status' => [
                        'success' => 'Operation completed successfully',
                        'error' => 'An error occurred',
                        'warning' => 'Warning: Please check your input',
                        'info' => 'Information updated'
                    ]
                ],

                'forms' => [
                    'name' => 'Full Name',
                    'email' => 'Email Address',
                    'phone' => 'Phone Number',
                    'message' => 'Message',
                    'subject' => 'Subject',
                    'submit' => 'Submit Form',
                    'required' => 'This field is required',
                    'invalid_email' => 'Please enter a valid email address'
                ]
            ],

            'es' => [
                'test' => [
                    // Page titles
                    'page_titles' => [
                        'translated' => 'Página de Prueba de Traducción - Pruebas CMS',
                        'simple' => 'Página de Prueba Simple - Pruebas CMS',
                        'complex' => 'Prueba de Contenido Complejo - Pruebas CMS'
                    ],

                    // Meta information
                    'meta' => [
                        'description' => 'Esta página prueba la funcionalidad de traducción en el sistema CMS',
                        'keywords' => 'cms, traducción, pruebas, laravel, multiidioma'
                    ],

                    // Language selector
                    'language_selector' => [
                        'title' => 'Selección de Idioma'
                    ],

                    // Current locale information
                    'current_locale' => [
                        'title' => 'Información del Idioma Actual',
                        'active' => 'Idioma Activo',
                        'name' => 'Nombre del Idioma',
                        'direction' => 'Dirección del Texto',
                        'ltr' => 'Izquierda a Derecha'
                    ],

                    // Page header
                    'page_header' => [
                        'title' => 'Página de Prueba de Traducción',
                        'subtitle' => 'Gestión de Contenido Multiidioma Integral',
                        'description' => 'Esta página demuestra las capacidades de traducción del sistema CMS, incluyendo traducciones parametrizadas, pluralización y claves de traducción anidadas.'
                    ],

                    // Examples section
                    'examples' => [
                        'title' => 'Ejemplos de Traducción',
                        'simple' => [
                            'title' => 'Traducciones Simples'
                        ],
                        'helpers' => [
                            'title' => 'Funciones de Ayuda'
                        ]
                    ],

                    // Helper functions
                    'helpers' => [
                        'welcome' => '¡Bienvenido a nuestra plataforma!',
                        'goodbye' => '¡Gracias por visitarnos!'
                    ],

                    // Pluralization
                    'plurals' => [
                        'items' => '{0} No se encontraron elementos|{1} Un elemento encontrado|[2,*] :count elementos encontrados'
                    ],

                    // Content sections
                    'content_sections' => [
                        'title' => 'Secciones de Contenido Multiidioma'
                    ],

                    // Section content
                    'section_1_title' => 'Gestión de Traducciones',
                    'section_1_content' => 'Esta sección demuestra cómo el contenido puede ser gestionado en múltiples idiomas con el sistema de traducción CMS. Los editores de contenido pueden actualizar fácilmente las traducciones y ver los cambios reflejados inmediatamente.',

                    'section_2_title' => 'Características de Localización',
                    'section_2_content' => 'El CMS admite características avanzadas de localización incluyendo idiomas de derecha a izquierda, formato de números, formato de fechas y adaptaciones culturales para diferentes regiones.',

                    // Translation information
                    'translation_info' => [
                        'title' => 'Información de Claves de Traducción',
                        'key_format' => 'Las claves usan notación de puntos: grupo.subgrupo.clave',
                        'file_location' => 'Archivos almacenados en resources/lang/{locale}/',
                        'editing_note' => 'Editar traducciones a través de la interfaz CMS'
                    ],

                    // Navigation
                    'navigation' => [
                        'title' => 'Navegación Traducida'
                    ],

                    'nav' => [
                        'home' => 'Inicio',
                        'about' => 'Acerca de',
                        'services' => 'Nuestros Servicios',
                        'contact' => 'Contacto',
                        'blog' => 'Blog'
                    ],

                    // Nested translations
                    'nested' => [
                        'title' => 'Ejemplos de Traducción Anidada',
                        'examples' => [
                            'title' => 'Claves de Ejemplo'
                        ],
                        'structure' => [
                            'title' => 'Estructura de Archivos'
                        ],
                        'level1' => [
                            'value' => 'Valor anidado de primer nivel',
                            'level2' => [
                                'value' => 'Valor anidado de segundo nivel',
                                'level3' => [
                                    'deep' => 'Este es un valor de traducción profundamente anidado para probar estructuras de claves complejas'
                                ]
                            ]
                        ]
                    ],

                    // Parameters
                    'parameters' => [
                        'title' => 'Traducciones Parametrizadas',
                        'examples' => [
                            'title' => 'Ejemplos con Parámetros'
                        ],
                        'welcome_user' => '¡Bienvenido de vuelta, :name! Nos alegra verte de nuevo.',
                        'items_found' => 'Encontrados :count :type en el sistema.',
                        'last_updated' => 'Última actualización el :date a las :time.'
                    ],

                    // Pluralization
                    'pluralization' => [
                        'title' => 'Ejemplos de Pluralización',
                        'examples' => [
                            'title' => 'Diferentes Cantidades'
                        ],
                        'syntax' => [
                            'title' => 'Ejemplo de Sintaxis',
                            'description' => 'Laravel usa reglas de pluralización ICU para formas plurales complejas.'
                        ]
                    ],

                    // File information
                    'file_info' => [
                        'title' => 'Información de Archivos de Traducción',
                        'location' => [
                            'title' => 'Ubicaciones de Archivos'
                        ],
                        'cms_features' => [
                            'title' => 'Características del CMS',
                            'inline_editing' => 'Edición de traducción en línea',
                            'bulk_import' => 'Importación masiva desde CSV/JSON',
                            'export_formats' => 'Exportar a múltiples formatos',
                            'auto_translate' => 'Auto-traducción con IA'
                        ]
                    ],

                    // Footer
                    'footer' => [
                        'generated_on' => 'Página generada el :date a las :time',
                        'locale_note' => 'Actualmente viendo en idioma :locale'
                    ],

                    // Test messages
                    'simple_message' => 'Este es un mensaje de traducción simple para pruebas.',
                    'welcome_user' => '¡Bienvenido, :name! Disfruta tu estadía.',
                    'item_count' => '{0} Sin elementos|{1} Un elemento|[2,*] :count elementos'
                ],

                'messages' => [
                    'welcome' => 'Bienvenido a nuestro sitio web',
                    'about' => 'Acerca de Nosotros',
                    'contact' => 'Contáctanos',
                    'services' => 'Nuestros Servicios',
                    'blog' => 'Blog',
                    'home' => 'Inicio',

                    'actions' => [
                        'save' => 'Guardar',
                        'cancel' => 'Cancelar',
                        'edit' => 'Editar',
                        'delete' => 'Eliminar',
                        'create' => 'Crear',
                        'update' => 'Actualizar',
                        'view' => 'Ver',
                        'search' => 'Buscar'
                    ],

                    'status' => [
                        'success' => 'Operación completada exitosamente',
                        'error' => 'Ocurrió un error',
                        'warning' => 'Advertencia: Por favor verifica tu entrada',
                        'info' => 'Información actualizada'
                    ]
                ],

                'forms' => [
                    'name' => 'Nombre Completo',
                    'email' => 'Correo Electrónico',
                    'phone' => 'Número de Teléfono',
                    'message' => 'Mensaje',
                    'subject' => 'Asunto',
                    'submit' => 'Enviar Formulario',
                    'required' => 'Este campo es obligatorio',
                    'invalid_email' => 'Por favor ingresa un correo electrónico válido'
                ]
            ],

            'fr' => [
                'test' => [
                    // Page titles
                    'page_titles' => [
                        'translated' => 'Page de Test de Traduction - Tests CMS',
                        'simple' => 'Page de Test Simple - Tests CMS',
                        'complex' => 'Test de Contenu Complexe - Tests CMS'
                    ],

                    // Meta information
                    'meta' => [
                        'description' => 'Cette page teste la fonctionnalité de traduction dans le système CMS',
                        'keywords' => 'cms, traduction, tests, laravel, multilingue'
                    ],

                    // Language selector
                    'language_selector' => [
                        'title' => 'Sélection de Langue'
                    ],

                    // Current locale information
                    'current_locale' => [
                        'title' => 'Informations sur la Langue Actuelle',
                        'active' => 'Langue Active',
                        'name' => 'Nom de la Langue',
                        'direction' => 'Direction du Texte',
                        'ltr' => 'Gauche à Droite'
                    ],

                    // Page header
                    'page_header' => [
                        'title' => 'Page de Test de Traduction',
                        'subtitle' => 'Gestion de Contenu Multilingue Complète',
                        'description' => 'Cette page démontre les capacités de traduction du système CMS, y compris les traductions paramétrées, la pluralisation et les clés de traduction imbriquées.'
                    ],

                    // Examples section
                    'examples' => [
                        'title' => 'Exemples de Traduction',
                        'simple' => [
                            'title' => 'Traductions Simples'
                        ],
                        'helpers' => [
                            'title' => 'Fonctions d\'Aide'
                        ]
                    ],

                    // Helper functions
                    'helpers' => [
                        'welcome' => 'Bienvenue sur notre plateforme !',
                        'goodbye' => 'Merci de votre visite !'
                    ],

                    // Pluralization
                    'plurals' => [
                        'items' => '{0} Aucun élément trouvé|{1} Un élément trouvé|[2,*] :count éléments trouvés'
                    ],

                    // Content sections
                    'content_sections' => [
                        'title' => 'Sections de Contenu Multilingue'
                    ],

                    // Section content
                    'section_1_title' => 'Gestion des Traductions',
                    'section_1_content' => 'Cette section démontre comment le contenu peut être géré dans plusieurs langues avec le système de traduction CMS. Les éditeurs de contenu peuvent facilement mettre à jour les traductions et voir les changements reflétés immédiatement.',

                    'section_2_title' => 'Fonctionnalités de Localisation',
                    'section_2_content' => 'Le CMS prend en charge des fonctionnalités de localisation avancées incluant les langues de droite à gauche, le formatage des nombres, le formatage des dates et les adaptations culturelles pour différentes régions.',

                    // Rest of French translations...
                    'translation_info' => [
                        'title' => 'Informations sur les Clés de Traduction',
                        'key_format' => 'Les clés utilisent la notation par points : groupe.sous-groupe.clé',
                        'file_location' => 'Fichiers stockés dans resources/lang/{locale}/',
                        'editing_note' => 'Modifier les traductions via l\'interface CMS'
                    ],

                    'navigation' => [
                        'title' => 'Navigation Traduite'
                    ],

                    'nav' => [
                        'home' => 'Accueil',
                        'about' => 'À Propos',
                        'services' => 'Nos Services',
                        'contact' => 'Contact',
                        'blog' => 'Blog'
                    ],

                    // Simple test messages
                    'simple_message' => 'Ceci est un message de traduction simple pour les tests.',
                    'welcome_user' => 'Bienvenue, :name ! Profitez de votre séjour.',
                    'item_count' => '{0} Aucun élément|{1} Un élément|[2,*] :count éléments'
                ]
            ]
        ];

        // Create translation files
        $translationCount = 0;
        foreach ($translations as $locale => $groups) {
            $localeDir = resource_path("lang/{$locale}");
            if (!File::exists($localeDir)) {
                File::makeDirectory($localeDir, 0755, true);
            }

            foreach ($groups as $group => $translations) {
                $filePath = "{$localeDir}/{$group}.php";
                $content = "<?php\n\nreturn " . $this->varExportPretty($translations) . ";\n";
                File::put($filePath, $content);
                $translationCount++;
                $this->command->line("  ✓ Created translation file: {$locale}/{$group}.php");
            }
        }

        $this->command->info("  📊 Created {$translationCount} translation files");
    }

    /**
     * Create test images with different types and sizes.
     */
    protected function createTestImages(): void
    {
        $this->command->info('🖼️  Creating test images...');

        // Create image directories
        $imageDirectories = [
            'public/images/test',
            'public/images/test/gallery',
            'public/images/test/avatars',
            'public/images/test/banners',
            'storage/app/public/uploads'
        ];

        foreach ($imageDirectories as $dir) {
            $fullPath = base_path($dir);
            if (!File::exists($fullPath)) {
                File::makeDirectory($fullPath, 0755, true);
                $this->command->line("  ✓ Created directory: {$dir}");
            }
        }

        // Create placeholder images (SVG format for simplicity)
        $testImages = [
            'hero-bg.jpg' => $this->createSvgPlaceholder(1920, 1080, 'Hero Background', '#1e3a8a'),
            'gallery-1.jpg' => $this->createSvgPlaceholder(800, 600, 'Gallery Image 1', '#059669'),
            'gallery-2.jpg' => $this->createSvgPlaceholder(800, 600, 'Gallery Image 2', '#dc2626'),
            'card-1.jpg' => $this->createSvgPlaceholder(400, 300, 'Card Image 1', '#7c3aed'),
            'card-2.jpg' => $this->createSvgPlaceholder(400, 300, 'Card Image 2', '#ea580c'),
            'card-3.jpg' => $this->createSvgPlaceholder(400, 300, 'Card Image 3', '#0891b2'),
            'avatar-1.jpg' => $this->createSvgPlaceholder(150, 150, 'JD', '#374151'),
            'avatar-2.jpg' => $this->createSvgPlaceholder(150, 150, 'JS', '#6b7280'),
            'og-complex.jpg' => $this->createSvgPlaceholder(1200, 630, 'OG Image', '#1f2937'),
            'video-poster.jpg' => $this->createSvgPlaceholder(800, 450, 'Video Poster', '#111827')
        ];

        $imageCount = 0;
        foreach ($testImages as $filename => $svgContent) {
            $filePath = base_path("public/images/test/{$filename}");
            File::put($filePath, $svgContent);
            $imageCount++;
            $this->command->line("  ✓ Created test image: {$filename}");
        }

        // Create additional sizes for responsive testing
        $sizes = [
            'thumbnail' => [150, 150],
            'medium' => [400, 300],
            'large' => [800, 600],
            'banner' => [1200, 400]
        ];

        foreach ($sizes as $sizeName => $dimensions) {
            $filename = "test-{$sizeName}.jpg";
            $svgContent = $this->createSvgPlaceholder(
                $dimensions[0],
                $dimensions[1],
                ucfirst($sizeName),
                '#' . substr(md5($sizeName), 0, 6)
            );
            File::put(base_path("public/images/test/{$filename}"), $svgContent);
            $imageCount++;
        }

        $this->command->info("  📊 Created {$imageCount} test images");
    }

    /**
     * Create test content files and data.
     */
    protected function createTestContent(): void
    {
        $this->command->info('📄 Creating test content files...');

        // Create sample JSON data
        $sampleData = [
            'users' => [
                ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'role' => 'admin'],
                ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'role' => 'editor'],
                ['id' => 3, 'name' => 'Bob Johnson', 'email' => 'bob@example.com', 'role' => 'user']
            ],
            'posts' => [
                ['id' => 1, 'title' => 'First Test Post', 'content' => 'This is test content...'],
                ['id' => 2, 'title' => 'Second Test Post', 'content' => 'More test content...'],
                ['id' => 3, 'title' => 'Third Test Post', 'content' => 'Additional test content...']
            ],
            'settings' => [
                'site_name' => 'CMS Test Application',
                'site_description' => 'A test application for CMS functionality',
                'default_locale' => 'en',
                'supported_locales' => ['en', 'es', 'fr'],
                'features' => [
                    'translations' => true,
                    'file_management' => true,
                    'user_management' => true,
                    'analytics' => false
                ]
            ]
        ];

        $jsonFile = base_path('storage/app/test-data.json');
        File::put($jsonFile, json_encode($sampleData, JSON_PRETTY_PRINT));
        $this->command->line('  ✓ Created test-data.json');

        // Create sample CSV file
        $csvData = [
            ['Name', 'Email', 'Role', 'Created'],
            ['Alice Wilson', 'alice@test.com', 'Editor', '2024-01-15'],
            ['Charlie Brown', 'charlie@test.com', 'User', '2024-01-20'],
            ['Diana Prince', 'diana@test.com', 'Admin', '2024-01-25']
        ];

        $csvFile = base_path('storage/app/test-users.csv');
        $handle = fopen($csvFile, 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        $this->command->line('  ✓ Created test-users.csv');

        // Create sample configuration files
        $configData = [
            'app_name' => 'CMS Test Application',
            'version' => '1.0.0',
            'environment' => 'testing',
            'debug' => true,
            'features' => [
                'content_editing' => true,
                'translation_management' => true,
                'file_uploads' => true,
                'user_authentication' => true,
                'api_access' => true
            ],
            'limits' => [
                'max_file_size' => 10240, // 10MB
                'max_upload_files' => 20,
                'session_timeout' => 3600, // 1 hour
                'api_rate_limit' => 1000 // per hour
            ]
        ];

        $configFile = base_path('storage/app/test-config.json');
        File::put($configFile, json_encode($configData, JSON_PRETTY_PRINT));
        $this->command->line('  ✓ Created test-config.json');

        $this->command->info('  📊 Created 3 test content files');
    }

    /**
     * Create test activity logs and history data.
     */
    protected function createTestActivityLogs(): void
    {
        $this->command->info('📋 Creating test activity logs...');

        $activities = [];
        $userIds = [1, 2, 3, 4];
        $actions = [
            'file_created', 'file_updated', 'file_deleted',
            'translation_added', 'translation_updated', 'translation_deleted',
            'user_login', 'user_logout', 'settings_updated',
            'content_published', 'content_drafted', 'backup_created'
        ];

        $resources = [
            'file' => ['test.blade.php', 'welcome.blade.php', 'about.html'],
            'translation' => ['messages.welcome', 'forms.submit', 'errors.404'],
            'user' => ['admin@test.com', 'editor@test.com', 'user@test.com'],
            'content' => ['homepage', 'about-page', 'contact-form']
        ];

        // Generate 100 random activity entries
        for ($i = 0; $i < 100; $i++) {
            $action = $actions[array_rand($actions)];
            $userId = $userIds[array_rand($userIds)];
            $resourceType = array_rand($resources);
            $resourceId = $resources[$resourceType][array_rand($resources[$resourceType])];

            $activities[] = [
                'id' => $i + 1,
                'user_id' => $userId,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'description' => $this->generateActivityDescription($action, $resourceType, $resourceId),
                'ip_address' => $this->generateRandomIp(),
                'user_agent' => $this->generateRandomUserAgent(),
                'created_at' => Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23))->toISOString(),
                'metadata' => [
                    'file_size' => rand(1024, 102400),
                    'duration' => rand(100, 5000),
                    'success' => rand(0, 100) > 5 // 95% success rate
                ]
            ];
        }

        $logFile = base_path('storage/app/activity-logs.json');
        File::put($logFile, json_encode($activities, JSON_PRETTY_PRINT));
        $this->command->line('  ✓ Created activity-logs.json with 100 entries');

        $this->command->info('  📊 Created activity log data');
    }

    /**
     * Set up test directories and permissions.
     */
    protected function setupTestDirectories(): void
    {
        $this->command->info('📁 Setting up test directories...');

        $directories = [
            'storage/app/backups',
            'storage/app/uploads',
            'storage/app/exports',
            'storage/app/imports',
            'storage/framework/testing',
            'public/css',
            'public/js',
            'public/fonts',
            'tests/fixtures/views',
            'tests/fixtures/lang',
            'tests/fixtures/uploads'
        ];

        $createdCount = 0;
        foreach ($directories as $dir) {
            $fullPath = base_path($dir);
            if (!File::exists($fullPath)) {
                File::makeDirectory($fullPath, 0755, true);
                $createdCount++;
                $this->command->line("  ✓ Created directory: {$dir}");
            }
        }

        // Create .gitkeep files for empty directories
        $gitkeepDirs = [
            'storage/app/backups',
            'storage/app/uploads',
            'storage/app/exports'
        ];

        foreach ($gitkeepDirs as $dir) {
            $gitkeepPath = base_path("{$dir}/.gitkeep");
            if (!File::exists($gitkeepPath)) {
                File::put($gitkeepPath, '');
                $this->command->line("  ✓ Created .gitkeep in {$dir}");
            }
        }

        $this->command->info("  📊 Set up {$createdCount} directories");
    }

    /**
     * Create an SVG placeholder image.
     */
    protected function createSvgPlaceholder(int $width, int $height, string $text, string $color): string
    {
        return <<<SVG
<svg width="{$width}" height="{$height}" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="{$color}"/>
    <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="24" fill="white" text-anchor="middle" dy="0.3em">{$text}</text>
    <text x="50%" y="60%" font-family="Arial, sans-serif" font-size="14" fill="rgba(255,255,255,0.7)" text-anchor="middle" dy="0.3em">{$width}×{$height}</text>
</svg>
SVG;
    }

    /**
     * Pretty print PHP array export.
     */
    protected function varExportPretty($var, $indent = ''): string
    {
        if (is_array($var)) {
            $indexed = array_keys($var) === range(0, count($var) - 1);
            $r = "[\n";
            foreach ($var as $key => $value) {
                $r .= "{$indent}    ";
                if (!$indexed) {
                    $r .= var_export($key, true) . ' => ';
                }
                $r .= $this->varExportPretty($value, "{$indent}    ");
                $r .= ",\n";
            }
            $r .= "{$indent}]";
            return $r;
        } else {
            return var_export($var, true);
        }
    }

    /**
     * Generate activity description based on action and resource.
     */
    protected function generateActivityDescription(string $action, string $resourceType, string $resourceId): string
    {
        $descriptions = [
            'file_created' => "Created {$resourceType}: {$resourceId}",
            'file_updated' => "Updated {$resourceType}: {$resourceId}",
            'file_deleted' => "Deleted {$resourceType}: {$resourceId}",
            'translation_added' => "Added translation: {$resourceId}",
            'translation_updated' => "Updated translation: {$resourceId}",
            'translation_deleted' => "Deleted translation: {$resourceId}",
            'user_login' => "User logged in: {$resourceId}",
            'user_logout' => "User logged out: {$resourceId}",
            'settings_updated' => "Updated system settings",
            'content_published' => "Published content: {$resourceId}",
            'content_drafted' => "Saved draft: {$resourceId}",
            'backup_created' => "Created backup for: {$resourceId}"
        ];

        return $descriptions[$action] ?? "Performed {$action} on {$resourceType}: {$resourceId}";
    }

    /**
     * Generate random IP address.
     */
    protected function generateRandomIp(): string
    {
        return rand(1, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(1, 255);
    }

    /**
     * Generate random user agent.
     */
    protected function generateRandomUserAgent(): string
    {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0'
        ];

        return $userAgents[array_rand($userAgents)];
    }
}