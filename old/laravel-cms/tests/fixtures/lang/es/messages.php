<?php

return [
    'welcome' => 'Bienvenido a nuestro sitio web',
    'about' => 'Acerca de Nosotros',
    'contact' => 'Contáctanos',
    'home' => 'Inicio',
    'services' => 'Nuestros Servicios',
    'blog' => 'Blog',
    'news' => 'Últimas Noticias',

    // Navigation
    'navigation' => [
        'home' => 'Inicio',
        'about' => 'Acerca de',
        'services' => 'Servicios',
        'portfolio' => 'Portafolio',
        'blog' => 'Blog',
        'contact' => 'Contacto',
    ],

    // Common actions
    'actions' => [
        'read_more' => 'Leer Más',
        'learn_more' => 'Saber Más',
        'get_started' => 'Comenzar',
        'download' => 'Descargar',
        'subscribe' => 'Suscribirse',
        'submit' => 'Enviar',
        'cancel' => 'Cancelar',
        'save' => 'Guardar',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
        'view' => 'Ver',
        'search' => 'Buscar',
    ],

    // Forms
    'forms' => [
        'name' => 'Nombre',
        'first_name' => 'Nombre',
        'last_name' => 'Apellido',
        'email' => 'Correo Electrónico',
        'phone' => 'Número de Teléfono',
        'message' => 'Mensaje',
        'subject' => 'Asunto',
        'company' => 'Empresa',
        'website' => 'Sitio Web',
        'address' => 'Dirección',
        'city' => 'Ciudad',
        'state' => 'Estado',
        'zip_code' => 'Código Postal',
        'country' => 'País',
    ],

    // Status messages
    'status' => [
        'success' => '¡Éxito!',
        'error' => 'Ocurrió un error',
        'warning' => 'Advertencia',
        'info' => 'Información',
        'loading' => 'Cargando...',
        'saving' => 'Guardando...',
        'saved' => 'Guardado exitosamente',
        'deleted' => 'Eliminado exitosamente',
        'updated' => 'Actualizado exitosamente',
        'created' => 'Creado exitosamente',
    ],

    // Content management
    'content' => [
        'title' => 'Título',
        'subtitle' => 'Subtítulo',
        'description' => 'Descripción',
        'content' => 'Contenido',
        'excerpt' => 'Resumen',
        'category' => 'Categoría',
        'tags' => 'Etiquetas',
        'author' => 'Autor',
        'date' => 'Fecha',
        'published' => 'Publicado',
        'draft' => 'Borrador',
        'featured' => 'Destacado',
        'image' => 'Imagen',
        'gallery' => 'Galería',
    ],

    // Time and dates
    'time' => [
        'today' => 'Hoy',
        'yesterday' => 'Ayer',
        'tomorrow' => 'Mañana',
        'this_week' => 'Esta Semana',
        'last_week' => 'La Semana Pasada',
        'this_month' => 'Este Mes',
        'last_month' => 'El Mes Pasado',
        'this_year' => 'Este Año',
        'ago' => 'hace :time',
        'in' => 'en :time',
    ],

    // Pagination
    'pagination' => [
        'previous' => 'Anterior',
        'next' => 'Siguiente',
        'first' => 'Primero',
        'last' => 'Último',
        'showing' => 'Mostrando :from a :to de :total resultados',
        'no_results' => 'No se encontraron resultados',
    ],

    // Errors
    'errors' => [
        'not_found' => 'Página no encontrada',
        'unauthorized' => 'Acceso no autorizado',
        'forbidden' => 'Acceso prohibido',
        'server_error' => 'Error interno del servidor',
        'validation_failed' => 'Falló la validación',
        'file_not_found' => 'Archivo no encontrado',
        'permission_denied' => 'Permiso denegado',
    ],

    // CMS specific
    'cms' => [
        'editor' => 'Editor de Contenido',
        'preview' => 'Vista Previa',
        'publish' => 'Publicar',
        'unpublish' => 'Despublicar',
        'draft' => 'Guardar como Borrador',
        'auto_save' => 'Guardado automático',
        'manual_save' => 'Guardar manualmente',
        'restore' => 'Restaurar',
        'backup' => 'Respaldo',
        'history' => 'Historial de Versiones',
        'undo' => 'Deshacer',
        'redo' => 'Rehacer',
        'cut' => 'Cortar',
        'copy' => 'Copiar',
        'paste' => 'Pegar',
        'find' => 'Buscar',
        'replace' => 'Reemplazar',
        'settings' => 'Configuración',
        'preferences' => 'Preferencias',
        'theme' => 'Tema',
        'layout' => 'Diseño',
        'sidebar' => 'Barra Lateral',
        'toolbar' => 'Barra de Herramientas',
        'menu' => 'Menú',
        'widget' => 'Widget',
        'component' => 'Componente',
        'template' => 'Plantilla',
        'page' => 'Página',
        'post' => 'Publicación',
        'media' => 'Medios',
        'files' => 'Archivos',
        'images' => 'Imágenes',
        'videos' => 'Videos',
        'documents' => 'Documentos',
        'upload' => 'Subir',
        'download' => 'Descargar',
        'optimize' => 'Optimizar',
        'compress' => 'Comprimir',
        'resize' => 'Redimensionar',
        'crop' => 'Recortar',
        'filter' => 'Filtrar',
        'sort' => 'Ordenar',
        'group' => 'Agrupar',
        'archive' => 'Archivar',
        'trash' => 'Papelera',
        'permanent_delete' => 'Eliminar Permanentemente',
    ],

    // Nested translations for testing
    'nested' => [
        'level1' => [
            'level2' => [
                'level3' => [
                    'deep_value' => 'Este es un valor de traducción profundamente anidado',
                    'another_deep' => 'Otro valor profundamente anidado para pruebas',
                ],
                'value' => 'Valor del nivel 2',
            ],
            'value' => 'Valor del nivel 1',
        ],
        'simple' => 'Valor anidado simple',
        'complex' => [
            'array' => [
                'item1' => 'Primer elemento',
                'item2' => 'Segundo elemento',
                'item3' => 'Tercer elemento',
            ],
            'object' => [
                'property1' => 'Valor de propiedad 1',
                'property2' => 'Valor de propiedad 2',
            ],
        ],
    ],

    // Pluralization examples
    'items' => '{0} Sin elementos|{1} Un elemento|[2,*] :count elementos',
    'users' => '{0} Sin usuarios|{1} Un usuario|[2,*] :count usuarios',
    'files' => '{0} Sin archivos|{1} Un archivo|[2,*] :count archivos',
    'comments' => '{0} Sin comentarios|{1} Un comentario|[2,*] :count comentarios',

    // With parameters
    'welcome_user' => '¡Bienvenido de vuelta, :name!',
    'user_profile' => 'Perfil de :name',
    'last_login' => 'Último inicio de sesión: :date a las :time',
    'file_size' => 'Tamaño del archivo: :size KB',
    'upload_progress' => 'Subiendo... :percent% completo',
    'remaining_time' => ':minutes minutos y :seconds segundos restantes',
];