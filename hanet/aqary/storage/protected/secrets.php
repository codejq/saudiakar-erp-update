<?php
/**
 * Centralized Secrets Storage
 * Location: Y:\storage\protected\secrets.php
 *
 * Protected by .htaccess - no web access
 * Contains API keys, tokens, and other sensitive data
 *
 * IMPORTANT: Do not commit this file to version control
 * Add to .gitignore: /storage/protected/secrets.php
 */

return [
    // AI Services
    'ai' => [
        'openrouter_api_key' => '', // Move from admin/ai/config/api_config.hnt
    ],

    // WhatsApp Integration
    'whatsapp' => [
        'api_token' => '',
        'webhook_secret' => '',
    ],

    // Tax Integration (ZATCA)
    'zatca' => [
        'api_key' => '',
        'certificate_path' => '', // Will be set to STORAGE_CERTIFICATES . 'zatca/'
    ],

    // Payment Gateway (MADA)
    'mada' => [
        'terminal_id' => '',
        'secret_key' => '',
    ],

    // Application Encryption
    'app' => [
        'encryption_key' => '', // For encrypting sensitive DB data
    ],
];
