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
];