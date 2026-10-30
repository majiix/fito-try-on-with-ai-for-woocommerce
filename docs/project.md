# Elfit AI Virtual Try-On for WooCommerce

## Overview
Elfit AI Virtual Try-On for WooCommerce is an open-source WordPress and WooCommerce plugin that adds an AI-driven virtual fitting room experience to single product pages. Customers upload an existing photo or capture a fresh photo via their mobile or desktop camera to view realistic try-on results of apparel, footwear, eyewear, jewelry, watches, hats, and accessories.

All features and capabilities of the plugin are 100% native, standalone, and fully functional out of the box with zero subscriptions, zero trialware, and zero recurring SaaS markups. Store owners connect their own developer API key via Bring-Your-Own-Key (BYOK) architecture for OpenRouter.

## Tech Stack & Dependencies
- **Language**: PHP 8.0+ (Tested up to PHP 8.3)
- **Core Platform**: WordPress 6.2+, WooCommerce 7.0+
- **Frontend Architecture**: Vanilla JavaScript (ES6+), Canvas API, Web Share API, MediaDevices API
- **Styling**: CSS Custom Properties, modern Flexbox/Grid, clean Light Mode UI, RTL support
- **REST Endpoints**: WordPress REST API (`/wp-json/fito-tryon/v1/...`)
- **Database Engine**: Custom MySQL tables (`wp_fito_tryon_jobs`, `wp_fito_tryon_logs`) managed via `dbDelta` (Schema Version 1.4.1)
- **Charts & Data Visualization**: Local Chart.js v4.5.1 for admin analytics dashboards
- **Internationalization**: WordPress i18n (`__()`, `_e()`, `esc_html__()`), bundled English and Persian (fa_IR) translations

## Architecture
```
elfit-ai-virtual-try-on-for-woocommerce/
├── elfit-ai-virtual-try-on-for-woocommerce.php  # Plugin bootstrap, constants & lifecycle hooks
├── uninstall.php                                # Data-safe uninstallation (preserves data by default)
├── includes/
│   ├── class-fito-tryon.php                     # Master orchestrator & provider registration
│   ├── class-fito-tryon-database.php            # dbDelta schema and database queries
│   ├── class-fito-tryon-storage.php             # Upload management & directories
│   ├── class-fito-tryon-openrouter.php          # OpenRouter API client & prompt synthesis
│   ├── class-fito-tryon-rate-limiter.php        # Per-IP and per-user sliding window throttling
│   ├── class-fito-tryon-privacy.php             # WP-Cron 24h auto-retention and photo purges
│   ├── class-fito-tryon-rest.php                # Nonce-secured REST endpoints & route handlers
│   ├── class-fito-tryon-public.php              # Product button injection & modal markup
│   └── blocks/
│       └── tryon-button/                        # Gutenberg block definition
├── admin/
│   └── class-fito-tryon-admin.php               # Admin menu, dashboards, settings, prompts & logs
├── assets/
│   ├── css/
│   │   ├── admin.css                            # Admin stylesheet
│   │   └── public.css                           # Frontend modal, split-slider CSS
│   └── js/
│       ├── admin.js                             # Admin interactions, Chart.js & prompt manager
│       ├── chart.umd.min.js                     # Chart.js v4.5.1 library
│       └── public.js                            # Frontend widget, camera capture & comparison slider
├── languages/
│   └── elfit-ai-virtual-try-on-for-woocommerce.pot
├── readme.txt                                   # WordPress.org standard documentation
└── docs/
    └── project.md                               # Project specification and documentation
```

## All-Inclusive Free Features
1. **OpenRouter AI Engine**:
   - Bring-Your-Own-Key (BYOK) architecture with customizable API key and primary model selection.
   - Access to top vision models (such as Kling AI and Fashn AI) through OpenRouter.
   - Live Model Discovery: Asynchronous fetching and transient caching of available image generation models.
   - Direct Execution: Single-model streamlined processing with direct error diagnostics.
2. **Storefront Single Product Placements**:
   - Single product page button (above or below Add to Cart, or in custom product tab).
   - Shortcode `[elfit_tryon]` and native Gutenberg block.
   - Dynamic variation attribute updates.
3. **Adaptive Prompts Engine**:
   - 7 built-in presets (Clothing, Shoes, Eyewear, Watches, Hats, Bags, Jewelry).
   - Fully customizable fitting prompt instructions and category mappings for each preset.
   - Mutually exclusive WooCommerce category mapping with term ID resolution.
   - Dynamic `{product_title}` tag interpolation.
   - Revert instruction to built-in default feature.
4. **Abuse Prevention, Privacy & Rate Limiting**:
   - Adaptive rate limiting per user account or per device cookie & IP.
   - Automated 24-hour photo disk retention auto-cleanup via WP-Cron.
   - Configurable database audit log retention.
   - Manual one-click photo purge tool.
5. **Analytics & Performance Tracking**:
   - Real-time KPI summary (total try-ons, success rate, average duration, estimated spend).
   - 14-day interactive volume graph via local Chart.js.
   - Searchable, filterable audit log table with duration and cost estimates.
   - Sent prompt and received response payload inspector.
6. **Safe Uninstallation**:
   - In-Memory Guard: Checks if the plugin is running in memory (`class_exists('Fito_Tryon') || defined('FITO_TRYON_VERSION')`). Avoids brittle directory sniffing or active plugins scanning.
   - Clean Database & Storage Removal: Drops `wp_fito_tryon_jobs` and `wp_fito_tryon_logs`, removes all registered settings, and deletes temporary upload files.

## Verification & Testing Commands
- **PHP Syntax Verification**:
  ```powershell
  rtk php -l admin/class-fito-tryon-admin.php elfit-ai-virtual-try-on-for-woocommerce.php includes/class-fito-tryon-database.php includes/class-fito-tryon-openrouter.php includes/class-fito-tryon-privacy.php includes/class-fito-tryon-public.php includes/class-fito-tryon-rate-limiter.php includes/class-fito-tryon-rest.php includes/class-fito-tryon-storage.php includes/class-fito-tryon.php uninstall.php tests/test-all-inclusive-free-plugin.php
  ```
- **Unit and Feature Test Suite (31 tests)**:
  ```powershell
  rtk php tests/test-all-inclusive-free-plugin.php
  ```
