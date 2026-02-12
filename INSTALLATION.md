# MultiChat GPT - Installation & Setup

## Prerequisites

- WordPress 5.6 or higher
- WordPress Multisite enabled
- WPML (WordPress Multilingual Plugin) installed and activated
- PHP 7.4 or higher
- OpenAI API account with GPT-4 access

## Installation Steps

### 1. Install the Plugin

1. Download the `multichat-gpt` folder
2. Upload to `/wp-content/plugins/` via SFTP or WordPress admin
3. Go to **Plugins** → **Installed Plugins**
4. Click **Activate** on "MultiChat GPT"

### 2. Get Your OpenAI API Key

1. Visit [OpenAI Platform](https://platform.openai.com/api-keys)
2. Create a new API key (or use an existing one)
3. Copy the key to a secure location

### 3. Configure the Plugin

1. Go to **Settings** → **MultiChat GPT**
2. Paste your OpenAI API key in the **OpenAI API Key** field
3. Select your preferred **Widget Position** (Bottom Right or Bottom Left)
4. Click **Save Settings**

### 4. Customize the Knowledge Base

Open `multichat-gpt.php` and find the `get_knowledge_base_chunks()` method (around line 240).

Replace the hard-coded knowledge base with your content:

```php
$kb_data = [
    'en' => [
        'Your question here?',
        'Your answer here.',
        // Add more Q&A pairs
    ],
    'ar' => [
        'سؤالك هنا؟',
        'إجابتك هنا.',
        // Add more Q&A pairs
    ],
];```

### 4. Build Dynamic Knowledge Base (NEW!)

The plugin now features an automatic knowledge base builder that scans your website:

1. Enter your **Sitemap URL** in settings (e.g., `https://yoursite.com/sitemap_index.xml`)
2. Click **Save Settings**
3. Scroll down to **Knowledge Base Management** section
4. Click **Scan Sitemap Now** button
5. Wait for the scan to complete (typically 1-2 minutes)
6. View the statistics showing pages indexed and knowledge chunks created
7. The chatbot will now use this dynamic content to answer questions!

**Note**: The knowledge base is cached and automatically refreshes based on your cache duration setting (default: 7 days). You can manually trigger a rescan or clear the cache anytime.

### 5. (Optional) Customize Static Fallback

The plugin maintains a static fallback knowledge base that is used when dynamic scanning hasn't been performed or cache is empty. This is now optional since the dynamic KB is the primary source.

## Features

### Dynamic Knowledge Base
- Automatically scans your website sitemap
- Extracts content from all pages
- Creates semantic chunks for better context
- Caches results for optimal performance  
- Manual or automatic refresh options

### Multilingual Support
- Works with WPML for automatic language detection
- Supports English, Arabic, Spanish, and French
- Easy to extend for additional languages

### ChatGPT Integration
- Uses OpenAI's GPT-3.5-turbo model
- Contextual responses based on website content
- Fallback to static knowledge base if needed

For more details about the dynamic knowledge base features, see [KB_README.md](KB_README.md).
