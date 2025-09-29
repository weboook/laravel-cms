# API Documentation

Complete reference for the Laravel CMS REST API, including authentication, endpoints, and examples.

## 🔗 Base URL

All API requests are made to:
```
https://your-domain.com/cms/api/
```

## 🔐 Authentication

Laravel CMS API supports multiple authentication methods:

### 1. Session Authentication (Web)

For web applications using Laravel sessions:

```javascript
// CSRF token is required
const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

fetch('/cms/api/content/text', {
    method: 'PUT',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token
    },
    body: JSON.stringify({
        key: 'page.title',
        value: 'New Title'
    })
});
```

### 2. API Token Authentication

For API access, first obtain a token:

```bash
# Login to get token
curl -X POST https://your-site.com/cms/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'
```

Response:
```json
{
  "access_token": "1|abc123...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "permissions": ["cms:edit", "cms:translate"]
  }
}
```

Use the token in subsequent requests:
```bash
curl -X GET https://your-site.com/cms/api/content \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Accept: application/json"
```

### 3. Personal Access Tokens

Generate long-lived tokens for API integrations:

```php
// Generate token
$user = User::find(1);
$token = $user->createToken('API Token', ['cms:edit', 'cms:translate']);

echo $token->plainTextToken; // Use this token for API requests
```

## 🎯 Content Management API

### Text Content

#### Get Text Content

```http
GET /cms/api/content/text/{key}
```

**Parameters:**
- `key` (string, required): Content key
- `language` (string, optional): Language code (defaults to current locale)

**Example:**
```bash
curl -X GET "https://your-site.com/cms/api/content/text/page.title?language=en" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "key": "page.title",
  "value": "Welcome to Our Site",
  "language": "en",
  "type": "text",
  "updated_at": "2024-01-15T10:30:00Z"
}
```

#### Update Text Content

```http
PUT /cms/api/content/text
```

**Request Body:**
```json
{
  "key": "page.title",
  "value": "New Page Title",
  "language": "en"
}
```

**Example:**
```bash
curl -X PUT https://your-site.com/cms/api/content/text \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "key": "page.title",
    "value": "Updated Title",
    "language": "en"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Content updated successfully",
  "data": {
    "key": "page.title",
    "value": "Updated Title",
    "language": "en",
    "previous_value": "Old Title",
    "updated_at": "2024-01-15T10:35:00Z"
  }
}
```

#### Bulk Update Text Content

```http
POST /cms/api/content/text/bulk
```

**Request Body:**
```json
{
  "language": "en",
  "content": {
    "page.title": "New Title",
    "page.subtitle": "New Subtitle",
    "nav.home": "Home",
    "nav.about": "About"
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "4 content items updated successfully",
  "updated": 4,
  "failed": 0,
  "errors": []
}
```

### Rich Text Content

#### Get Rich Content

```http
GET /cms/api/content/rich/{key}
```

**Response:**
```json
{
  "key": "page.content",
  "value": "<h2>Welcome</h2><p>This is <strong>rich</strong> content.</p>",
  "language": "en",
  "type": "rich",
  "sanitized": true,
  "word_count": 12,
  "updated_at": "2024-01-15T10:30:00Z"
}
```

#### Update Rich Content

```http
PUT /cms/api/content/rich
```

**Request Body:**
```json
{
  "key": "page.content",
  "value": "<h2>Updated Content</h2><p>This is the new content.</p>",
  "language": "en",
  "sanitize": true
}
```

### Image Management

#### Upload Image

```http
POST /cms/api/content/image/upload
```

**Form Data:**
- `file` (file, required): Image file
- `key` (string, required): Content key
- `alt` (string, optional): Alt text
- `resize` (boolean, optional): Auto-resize image
- `quality` (integer, optional): Image quality (1-100)

**Example:**
```bash
curl -X POST https://your-site.com/cms/api/content/image/upload \
  -H "Authorization: Bearer {token}" \
  -F "file=@/path/to/image.jpg" \
  -F "key=hero.banner" \
  -F "alt=Hero Banner Image" \
  -F "resize=true" \
  -F "quality=85"
```

**Response:**
```json
{
  "success": true,
  "message": "Image uploaded successfully",
  "data": {
    "key": "hero.banner",
    "url": "https://your-site.com/storage/cms/images/hero-banner.jpg",
    "alt": "Hero Banner Image",
    "size": 245760,
    "dimensions": {
      "width": 1200,
      "height": 600
    },
    "thumbnails": {
      "small": "https://your-site.com/storage/cms/images/thumbs/hero-banner-300x150.jpg",
      "medium": "https://your-site.com/storage/cms/images/thumbs/hero-banner-600x300.jpg"
    }
  }
}
```

#### Get Image Info

```http
GET /cms/api/content/image/{key}
```

#### Update Image Properties

```http
PUT /cms/api/content/image
```

**Request Body:**
```json
{
  "key": "hero.banner",
  "alt": "Updated alt text",
  "title": "Hero Banner",
  "caption": "Main website banner"
}
```

### Link Management

#### Get Link

```http
GET /cms/api/content/link/{key}
```

**Response:**
```json
{
  "key": "nav.about",
  "url": "/about-us",
  "title": "About Us",
  "target": "_self",
  "rel": null,
  "type": "internal",
  "valid": true,
  "updated_at": "2024-01-15T10:30:00Z"
}
```

#### Update Link

```http
PUT /cms/api/content/link
```

**Request Body:**
```json
{
  "key": "nav.about",
  "url": "https://example.com/about",
  "title": "About Our Company",
  "target": "_blank",
  "rel": "noopener"
}
```

#### Validate Links

```http
POST /cms/api/content/links/validate
```

**Request Body:**
```json
{
  "keys": ["nav.home", "nav.about", "footer.contact"]
}
```

**Response:**
```json
{
  "results": {
    "nav.home": {
      "valid": true,
      "status_code": 200,
      "response_time": 120
    },
    "nav.about": {
      "valid": false,
      "status_code": 404,
      "error": "Page not found"
    },
    "footer.contact": {
      "valid": true,
      "status_code": 200,
      "response_time": 95
    }
  },
  "summary": {
    "total": 3,
    "valid": 2,
    "invalid": 1
  }
}
```

## 🌐 Translation Management API

### Get Translations

#### Get Single Translation

```http
GET /cms/api/translations/{key}
```

**Parameters:**
- `key` (string, required): Translation key
- `languages` (string, optional): Comma-separated language codes

**Response:**
```json
{
  "key": "welcome.message",
  "translations": {
    "en": "Welcome to our website",
    "es": "Bienvenido a nuestro sitio web",
    "fr": "Bienvenue sur notre site"
  },
  "updated_at": "2024-01-15T10:30:00Z"
}
```

#### Get All Translations

```http
GET /cms/api/translations
```

**Parameters:**
- `language` (string, optional): Filter by language
- `prefix` (string, optional): Filter by key prefix
- `page` (integer, optional): Page number for pagination
- `per_page` (integer, optional): Items per page (max 100)

**Response:**
```json
{
  "data": [
    {
      "key": "nav.home",
      "translations": {
        "en": "Home",
        "es": "Inicio",
        "fr": "Accueil"
      }
    },
    {
      "key": "nav.about",
      "translations": {
        "en": "About",
        "es": "Acerca de",
        "fr": "À propos"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 150,
    "per_page": 20,
    "last_page": 8
  }
}
```

### Update Translations

#### Update Single Translation

```http
PUT /cms/api/translations/{key}
```

**Request Body:**
```json
{
  "translations": {
    "en": "Updated English text",
    "es": "Texto actualizado en español",
    "fr": "Texte mis à jour en français"
  }
}
```

#### Bulk Update Translations

```http
POST /cms/api/translations/bulk
```

**Request Body:**
```json
{
  "translations": {
    "nav.home": {
      "en": "Home",
      "es": "Inicio",
      "fr": "Accueil"
    },
    "nav.about": {
      "en": "About",
      "es": "Acerca de",
      "fr": "À propos"
    }
  }
}
```

### Import/Export Translations

#### Export Translations

```http
GET /cms/api/translations/export
```

**Parameters:**
- `format` (string): Export format (`json`, `csv`, `xlsx`, `po`)
- `language` (string, optional): Specific language to export
- `prefix` (string, optional): Export only keys with prefix

**Response:**
```json
{
  "success": true,
  "download_url": "https://your-site.com/cms/exports/translations-2024-01-15.json",
  "expires_at": "2024-01-15T11:30:00Z"
}
```

#### Import Translations

```http
POST /cms/api/translations/import
```

**Form Data:**
- `file` (file, required): Translation file
- `format` (string, required): File format (`json`, `csv`, `xlsx`, `po`)
- `merge` (boolean, optional): Merge with existing translations
- `overwrite` (boolean, optional): Overwrite existing translations

**Response:**
```json
{
  "success": true,
  "message": "Translations imported successfully",
  "imported": 45,
  "updated": 12,
  "skipped": 3,
  "errors": []
}
```

### Auto-Translation

#### Translate Content

```http
POST /cms/api/translations/auto-translate
```

**Request Body:**
```json
{
  "key": "page.title",
  "source_language": "en",
  "target_languages": ["es", "fr", "de"],
  "service": "google"
}
```

**Response:**
```json
{
  "success": true,
  "translations": {
    "es": "Título de la página",
    "fr": "Titre de la page",
    "de": "Seitentitel"
  },
  "service_used": "google",
  "characters_used": 45,
  "cost": 0.002
}
```

## 📁 File Management API

### List Files

```http
GET /cms/api/files
```

**Parameters:**
- `type` (string, optional): Filter by file type (`image`, `document`, `video`)
- `path` (string, optional): Directory path
- `search` (string, optional): Search filename
- `page` (integer, optional): Page number
- `per_page` (integer, optional): Items per page

**Response:**
```json
{
  "data": [
    {
      "id": "img_123",
      "name": "hero-banner.jpg",
      "path": "images/hero-banner.jpg",
      "url": "https://your-site.com/storage/cms/images/hero-banner.jpg",
      "type": "image",
      "size": 245760,
      "mime_type": "image/jpeg",
      "dimensions": {
        "width": 1200,
        "height": 600
      },
      "alt": "Hero banner image",
      "uploaded_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 25,
    "per_page": 20
  }
}
```

### Upload File

```http
POST /cms/api/files/upload
```

**Form Data:**
- `file` (file, required): File to upload
- `path` (string, optional): Upload directory
- `alt` (string, optional): Alt text for images
- `title` (string, optional): File title

### Delete File

```http
DELETE /cms/api/files/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "File deleted successfully"
}
```

## 📊 Analytics API

### Content Statistics

```http
GET /cms/api/analytics/content
```

**Response:**
```json
{
  "total_keys": 150,
  "total_translations": 450,
  "languages": {
    "en": 150,
    "es": 148,
    "fr": 152
  },
  "content_types": {
    "text": 120,
    "rich": 25,
    "image": 15,
    "link": 30
  },
  "last_updated": "2024-01-15T10:30:00Z"
}
```

### Usage Analytics

```http
GET /cms/api/analytics/usage
```

**Parameters:**
- `period` (string): Time period (`day`, `week`, `month`, `year`)
- `start_date` (date, optional): Start date
- `end_date` (date, optional): End date

**Response:**
```json
{
  "period": "week",
  "total_edits": 45,
  "unique_editors": 3,
  "most_edited": [
    {
      "key": "page.title",
      "edits": 8
    },
    {
      "key": "nav.home",
      "edits": 5
    }
  ],
  "daily_activity": [
    {
      "date": "2024-01-14",
      "edits": 12,
      "editors": 2
    },
    {
      "date": "2024-01-15",
      "edits": 8,
      "editors": 1
    }
  ]
}
```

## 🔄 Version Control API

### Get Content History

```http
GET /cms/api/history/{key}
```

**Parameters:**
- `limit` (integer, optional): Number of versions to return
- `page` (integer, optional): Page number

**Response:**
```json
{
  "key": "page.title",
  "versions": [
    {
      "id": "v_123",
      "value": "Current Title",
      "language": "en",
      "user": {
        "id": 1,
        "name": "John Doe"
      },
      "created_at": "2024-01-15T10:30:00Z",
      "is_current": true
    },
    {
      "id": "v_122",
      "value": "Previous Title",
      "language": "en",
      "user": {
        "id": 2,
        "name": "Jane Smith"
      },
      "created_at": "2024-01-14T15:20:00Z",
      "is_current": false
    }
  ]
}
```

### Restore Version

```http
POST /cms/api/history/{key}/restore
```

**Request Body:**
```json
{
  "version_id": "v_122"
}
```

### Compare Versions

```http
GET /cms/api/history/{key}/compare
```

**Parameters:**
- `version1` (string, required): First version ID
- `version2` (string, required): Second version ID

**Response:**
```json
{
  "key": "page.title",
  "comparison": {
    "version1": {
      "id": "v_122",
      "value": "Previous Title",
      "created_at": "2024-01-14T15:20:00Z"
    },
    "version2": {
      "id": "v_123",
      "value": "Current Title",
      "created_at": "2024-01-15T10:30:00Z"
    },
    "diff": {
      "type": "text_change",
      "changes": [
        {
          "type": "delete",
          "text": "Previous"
        },
        {
          "type": "insert",
          "text": "Current"
        }
      ]
    }
  }
}
```

## 🛠️ System API

### System Status

```http
GET /cms/api/system/status
```

**Response:**
```json
{
  "status": "healthy",
  "version": "2.0.1",
  "uptime": 86400,
  "cache": {
    "status": "connected",
    "driver": "redis",
    "hit_rate": 85.2
  },
  "storage": {
    "status": "available",
    "disk": "local",
    "free_space": "15.2 GB"
  },
  "database": {
    "status": "connected",
    "connection": "mysql",
    "query_time": 12.5
  }
}
```

### Clear Cache

```http
POST /cms/api/system/cache/clear
```

**Request Body:**
```json
{
  "types": ["content", "translations", "images"]
}
```

### Health Check

```http
GET /cms/api/system/health
```

**Response:**
```json
{
  "status": "healthy",
  "checks": {
    "database": {
      "status": "pass",
      "response_time": "15ms"
    },
    "cache": {
      "status": "pass",
      "response_time": "3ms"
    },
    "storage": {
      "status": "pass",
      "writable": true
    },
    "external_services": {
      "status": "pass",
      "google_translate": "available"
    }
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

## 📝 Webhooks API

### List Webhooks

```http
GET /cms/api/webhooks
```

### Create Webhook

```http
POST /cms/api/webhooks
```

**Request Body:**
```json
{
  "name": "Content Update Notification",
  "url": "https://your-app.com/webhook/cms-update",
  "events": ["content.updated", "translation.updated"],
  "secret": "your-webhook-secret",
  "active": true
}
```

### Webhook Events

Available webhook events:

- `content.created` - New content created
- `content.updated` - Content updated
- `content.deleted` - Content deleted
- `translation.created` - New translation created
- `translation.updated` - Translation updated
- `translation.deleted` - Translation deleted
- `file.uploaded` - File uploaded
- `file.deleted` - File deleted
- `user.login` - User logged in to CMS
- `system.backup` - System backup created

## 🚨 Error Handling

### HTTP Status Codes

| Code | Description |
|------|-------------|
| `200` | Success |
| `201` | Created |
| `400` | Bad Request |
| `401` | Unauthorized |
| `403` | Forbidden |
| `404` | Not Found |
| `422` | Validation Error |
| `429` | Rate Limit Exceeded |
| `500` | Internal Server Error |

### Error Response Format

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "key": ["The key field is required."],
    "value": ["The value must be a string."]
  },
  "code": "VALIDATION_ERROR",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Common Error Codes

| Code | Description |
|------|-------------|
| `VALIDATION_ERROR` | Request validation failed |
| `PERMISSION_DENIED` | Insufficient permissions |
| `CONTENT_NOT_FOUND` | Content key not found |
| `FILE_TOO_LARGE` | Uploaded file exceeds size limit |
| `UNSUPPORTED_FORMAT` | File format not supported |
| `RATE_LIMIT_EXCEEDED` | API rate limit exceeded |
| `TRANSLATION_FAILED` | Auto-translation service error |

## 📊 Rate Limiting

API requests are rate limited:

- **Authenticated users**: 1000 requests per hour
- **Unauthenticated**: 100 requests per hour
- **File uploads**: 50 requests per hour
- **Auto-translation**: 100 requests per hour

Rate limit headers:
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1642248000
```

## 🔧 SDKs and Libraries

### JavaScript/TypeScript

```bash
npm install @webook/laravel-cms-js
```

```javascript
import { CMSClient } from '@webook/laravel-cms-js';

const cms = new CMSClient({
  baseUrl: 'https://your-site.com',
  token: 'your-api-token'
});

// Update content
await cms.content.updateText('page.title', 'New Title');

// Get translations
const translations = await cms.translations.get('nav.home');
```

### PHP

```bash
composer require webook/laravel-cms-client
```

```php
use Webook\LaravelCMS\Client\CMSClient;

$cms = new CMSClient([
    'base_url' => 'https://your-site.com',
    'token' => 'your-api-token'
]);

// Update content
$cms->content()->updateText('page.title', 'New Title');

// Get translations
$translations = $cms->translations()->get('nav.home');
```

### Python

```bash
pip install laravel-cms-python
```

```python
from laravel_cms import CMSClient

cms = CMSClient(
    base_url='https://your-site.com',
    token='your-api-token'
)

# Update content
cms.content.update_text('page.title', 'New Title')

# Get translations
translations = cms.translations.get('nav.home')
```

## 📚 Code Examples

### Complete Content Management

```javascript
// Comprehensive content management example
class ContentManager {
    constructor(apiToken, baseUrl) {
        this.api = new CMSClient({ token: apiToken, baseUrl });
    }

    async updatePageContent(pageData) {
        try {
            // Update multiple content types
            await Promise.all([
                this.api.content.updateText('page.title', pageData.title),
                this.api.content.updateRich('page.content', pageData.content),
                this.api.content.updateImage('page.banner', pageData.banner),
                this.api.content.updateLink('page.cta', pageData.ctaUrl)
            ]);

            return { success: true };
        } catch (error) {
            console.error('Content update failed:', error);
            return { success: false, error: error.message };
        }
    }

    async translatePage(pageKey, targetLanguages) {
        const translations = {};

        for (const lang of targetLanguages) {
            try {
                const result = await this.api.translations.autoTranslate(
                    pageKey,
                    'en',
                    [lang],
                    'google'
                );
                translations[lang] = result.translations[lang];
            } catch (error) {
                console.error(`Translation to ${lang} failed:`, error);
            }
        }

        return translations;
    }
}
```

## 📞 Next Steps

Now that you understand the API:

1. **Secure Your API**: [Security Guide](security.md)
2. **Build Custom Tools**: [Extension Guide](extending.md)
3. **Deploy to Production**: [Deployment Guide](deployment.md)
4. **Monitor Performance**: [Performance Guide](performance.md)

---

**Need API help?** Check our [Troubleshooting Guide](troubleshooting.md) or join the [Discord community](https://discord.gg/laravel-cms).