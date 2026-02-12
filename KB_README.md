# MultiChat GPT - Dynamic Knowledge Base

## Overview

The MultiChat GPT plugin now features a dynamic knowledge base system that automatically scans your website's sitemap and extracts content from discovered pages. This eliminates the need for manual Q&A updates and keeps your chatbot informed about all website content.

## Features

### 1. Automatic Sitemap Scanning
- Reads XML sitemap files (including sitemap index files)
- Discovers all page URLs automatically
- Filters content by post type (pages, posts, etc.)
- Handles nested sitemaps recursively

### 2. Intelligent Content Extraction
- Uses DOM parsing for reliable HTML content extraction
- Removes scripts, styles, navigation, headers, and footers
- Extracts main content from `<main>`, `<article>`, or content divs
- Preserves semantic structure for better context

### 3. Smart Caching System
- Stores knowledge base in WordPress transients
- Configurable cache duration (default: 7 days)
- Includes metadata: scan timestamp, page count, chunk count
- Manual cache clearing option

### 4. Admin Interface
- Settings page with KB Management section
- "Scan Sitemap Now" button for manual scans
- Real-time progress and status updates
- KB statistics display (pages indexed, chunks, last updated)
- "Clear Cache" button to reset the knowledge base

## Installation & Setup

### Step 1: Install the Plugin
1. Upload the plugin to `/wp-content/plugins/multichat-gpt/`
2. Activate the plugin through WordPress admin
3. Go to **Settings** → **MultiChat GPT**

### Step 2: Configure Settings
1. **OpenAI API Key**: Enter your OpenAI API key
2. **Widget Position**: Choose bottom-right or bottom-left
3. **Sitemap URL**: Enter your sitemap URL (e.g., `https://yoursite.com/sitemap_index.xml`)
4. **Cache Duration**: Set cache duration in seconds (default: 604800 = 7 days)
5. Click **Save Changes**

### Step 3: Build Knowledge Base
1. Scroll down to **Knowledge Base Management** section
2. Click **Scan Sitemap Now** button
3. Wait for the scan to complete (may take 1-2 minutes)
4. View the KB statistics showing indexed pages and chunks

## How It Works

### Sitemap Scanning Flow
```
1. Fetch sitemap URL
2. Parse XML (handle sitemap index if present)
3. Discover page URLs
4. Filter by post type
5. Crawl each URL (up to 50 by default)
6. Extract text content from each page
7. Create semantic chunks
8. Store in WordPress transients
9. Display scan results
```

### Content Processing
- **HTML Cleaning**: Removes scripts, styles, nav, header, footer
- **Main Content Extraction**: Uses DOMDocument to find `<main>`, `<article>`, or content divs
- **Text Extraction**: Strips remaining HTML tags
- **Chunking**: Splits content into semantic chunks (~500 chars each)
- **Metadata**: Stores URL, title, and hash for each chunk

### ChatGPT Integration
- Dynamic KB takes priority over static fallback
- Top 5 relevant chunks are selected based on user query
- Chunks are included in system message to ChatGPT
- Chatbot can reference website content in responses

## API Endpoints

### Scan Sitemap (Admin Only)
```
POST /wp-json/multichat/v1/scan-sitemap
```

**Parameters:**
- `force_refresh` (boolean): Clear cache before scanning
- `post_types` (array): Post types to include (default: ['page'])

**Response:**
```json
{
  "success": true,
  "total_pages": 25,
  "total_chunks": 120,
  "failed_urls": 2
}
```

### Chat Request (Public)
```
POST /wp-json/multichat/v1/ask
```

**Parameters:**
- `message` (string, required): User's message
- `language` (string): Language code (default: 'en')

**Response:**
```json
{
  "success": true,
  "message": "ChatGPT's response"
}
```

## Configuration Options

### Crawl Settings
You can customize crawl behavior by modifying class properties:

```php
// Set custom crawl limit (default: 50)
$crawler = new MultiChat_Content_Crawler();
$crawler->set_max_crawl_limit( 100 );

// Set custom timeout (default: 5 seconds)
$crawler->set_timeout( 10 );
```

### Cache Settings
Configure cache duration in admin settings or programmatically:

```php
// Set custom cache duration (default: 7 days)
$kb_builder = new MultiChat_KB_Builder();
$kb_builder->set_cache_duration( 86400 ); // 1 day
```

### Chunk Settings
Customize chunk size for better context:

```php
// Set custom chunk size (default: 500 chars)
$kb_builder = new MultiChat_KB_Builder();
$kb_builder->set_max_chunk_size( 1000 );
```

## Troubleshooting

### Scan Fails or Times Out
- **Increase AJAX timeout**: Default is 2 minutes, may need more for large sites
- **Check sitemap URL**: Ensure it's accessible and returns valid XML
- **Verify server resources**: Large scans require adequate PHP memory and execution time

### Content Not Extracted Properly
- **Check page structure**: Plugin looks for `<main>`, `<article>`, or `.content` divs
- **Review extracted chunks**: Use `get_transient('multichat_gpt_kb_cache')` to inspect
- **Adjust selectors**: Modify `extract_text()` method if needed for your theme

### ChatGPT Not Using Dynamic KB
- **Verify scan completed**: Check KB metadata shows pages indexed
- **Clear cache and rescan**: Use "Clear Cache" button then rescan
- **Check fallback**: Plugin uses static KB if dynamic KB is empty

## Performance Considerations

### Caching Strategy
- Knowledge base is cached in WordPress transients
- Cache expires after configured duration (default: 7 days)
- Manual scans refresh the cache
- Cache size depends on website content (typically 100KB-1MB)

### Server Impact
- Initial scan may take 1-2 minutes for 50 pages
- 0.1 second delay between page requests to avoid overwhelming server
- Crawl limit prevents excessive resource usage
- Subsequent requests use cached data (no performance impact)

### Optimization Tips
1. **Limit crawl scope**: Use post type filtering to scan only pages
2. **Set appropriate cache duration**: Longer cache = fewer scans
3. **Schedule scans**: Use cron job for automatic updates instead of manual
4. **Monitor cache size**: Very large sites may need chunk size adjustment

## Security

### Access Control
- Scan endpoint requires admin permissions (`manage_options` capability)
- Chat endpoint is public (for frontend widget)
- AJAX requests use WordPress nonces for CSRF protection

### Input Validation
- All URLs are validated and sanitized
- User input is sanitized before processing
- HTML content is stripped to prevent XSS

### Rate Limiting
- 0.1 second delay between page crawls
- Maximum 50 URLs crawled per scan by default
- Timeouts prevent long-running requests

## Advanced Usage

### Custom Post Types
Filter different post types:

```php
// Scan only blog posts
$urls = $scanner->scan( $sitemap_url, ['post'] );

// Scan multiple post types
$urls = $scanner->scan( $sitemap_url, ['page', 'post', 'product'] );
```

### Manual KB Building
Build KB from custom content:

```php
$custom_content = [
    [
        'url'     => 'https://example.com/page',
        'title'   => 'Page Title',
        'content' => 'Page content here...',
        'hash'    => md5('content'),
    ],
];

$kb_data = $kb_builder->build( $custom_content );
$kb_builder->save_to_cache( $kb_data );
```

### Filtering Knowledge Base
Use WordPress filters to customize KB:

```php
add_filter( 'multichat_gpt_knowledge_base', function( $kb_chunks, $language ) {
    // Add custom chunks
    $kb_chunks[] = 'Custom knowledge chunk';
    return $kb_chunks;
}, 10, 2 );
```

## Changelog

### Version 1.1.0
- ✅ Added dynamic knowledge base system
- ✅ Sitemap scanner with recursive support
- ✅ Content crawler with DOM-based extraction
- ✅ Knowledge base builder with chunking
- ✅ Admin interface for KB management
- ✅ Configurable crawl limits and timeouts
- ✅ Smart caching with transients
- ✅ REST API endpoint for programmatic scanning

## Support

For issues or questions, please open an issue on GitHub or contact support@example.com.

## License

GPL v2 or later
