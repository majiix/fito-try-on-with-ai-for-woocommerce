# Elfit AI Virtual Try-On for WooCommerce

## Overview
Elfit AI Virtual Try-On for WooCommerce is an enterprise-grade WordPress and WooCommerce plugin that adds an AI-driven virtual fitting room experience to single product pages and catalog loops. Customers upload an existing photo or capture a fresh photo via their mobile/desktop camera to view realistic try-on results of apparel, footwear, eyewear, jewelry, watches, hats, and accessories.

All AI image generations in the free core plugin are powered by an OpenRouter Bring-Your-Own-Key (BYOK) architecture, letting store owners connect their own API keys with zero subscription overhead or middleman fees. The plugin is 100% free, standalone, and fully functional out of the box with zero trialware gating. An optional commercial Pro Addon extends capabilities via extensible WordPress action and filter contracts.

## Tech Stack & Dependencies
- **Language**: PHP 8.0+ (Tested up to PHP 8.3)
- **Core Platform**: WordPress 6.0+, WooCommerce 7.0+
- **Frontend Architecture**: Vanilla JavaScript (ES6+), Canvas API, Web Share API, MediaDevices API
- **Styling**: CSS Custom Properties, modern Flexbox/Grid, clean Light Mode UI, RTL support
- **REST Endpoints**: WordPress REST API (`/wp-json/fito-tryon/v1/...` with legacy alias `/wp-json/ai-tryon/v1/...`)
- **Database Engine**: Custom MySQL tables (`wp_fito_tryon_jobs`, `wp_fito_tryon_logs`) managed via `dbDelta`
- **Charts & Data Visualization**: Chart.js for admin analytics dashboards
- **Internationalization**: WordPress i18n (`__()`, `_e()`, `esc_html__()`), bundled English and Persian (fa_IR) translations

## Architecture
```
elfit-ai-virtual-try-on-for-woocommerce/
├── elfit-ai-virtual-try-on-for-woocommerce.php  # Plugin bootstrap, constants & lifecycle hooks
├── uninstall.php                                # Complete cleanup on deletion
├── includes/
│   ├── class-fito-tryon.php                     # Master orchestrator & provider registration
│   ├── class-fito-tryon-database.php            # dbDelta schema and database queries
│   ├── class-fito-tryon-storage.php             # Upload management & directories
│   ├── class-fito-tryon-openrouter.php          # OpenRouter API client & prompt synthesis
│   ├── class-fito-tryon-rate-limiter.php        # Per-IP and per-user sliding window throttling
│   ├── class-fito-tryon-privacy.php             # WP-Cron auto-retention and GDPR photo purges
│   ├── class-fito-tryon-rest.php                # Nonce-secured REST endpoints & route handlers
│   ├── class-fito-tryon-public.php              # Product button injection, modal markup & placements
│   └── blocks/
│       └── tryon-button/                        # Gutenberg block definition
├── admin/
│   └── class-fito-tryon-admin.php               # Admin menu, dashboards, settings, prompts & logs
├── assets/
│   ├── css/
│   │   ├── admin.css                            # Modern card-based SaaS admin stylesheet
│   │   └── public.css                           # Modern frontend modal & split-slider CSS
│   └── js/
│       ├── admin.js                             # Admin interactions, Chart.js graphs & API tester
│       ├── chart.umd.min.js                     # Chart.js v4.5.1 library
│       └── public.js                            # Upload, camera capture, crop, polling & comparison slider
├── languages/
│   └── elfit-ai-virtual-try-on-for-woocommerce.pot
├── readme.txt                                   # WordPress.org standard documentation & External Services
└── SETUP.md                                     # Setup instructions, API keys config & tuning
```

## Current Features
1. **Multi-Provider AI Architecture (OpenRouter, Google Gemini, OpenAI)**:
   - Configurable AI Provider: OpenRouter is included free out of the box as the multi-model aggregator. Direct Google Gemini and direct OpenAI API integrations are unlocked with the Elfit Try-On Pro Addon.
   - Dedicated Credentials & Models per Provider: Separate API keys, primary models, and fallback models are stored independently for each service so settings are preserved when switching providers.
   - Direct Google Gemini Integration (Pro): Connects directly to Google AI Studio API (`/v1beta/models/{model}:generateContent` with `responseModalities: ["IMAGE", "TEXT"]`) supporting the complete Google Nano Banana image generation family (`gemini-3.1-flash-image`, `gemini-3-pro-image`, `gemini-2.5-flash-image`, etc.) and live model discovery.
   - Direct OpenAI Integration (Pro): Connects directly to OpenAI's image generation API (`/v1/images/generations`) supporting modern models (`gpt-image-2.5-flare`, `gpt-image-2.5-sunburst`, `dall-e-3`).
   - OpenRouter Aggregator Integration (Free Core): Connects directly to OpenRouter's API (`/images` for dedicated diffusion models like Flux/Recraft, and `/chat/completions` for multimodal chat image models like Grok and Gemini 2.5).
   - Multimodal Image Extraction: Extracts generated images from both OpenAI-compatible `$choice['images']` arrays (used by xAI Grok and Gemini 2.5 on OpenRouter) and `$choice['content']` (markdown and data URLs), with robust support for array and string content parts.
   - Content Moderation & Safety Handling: Transparently captures and surfaces upstream moderation refusals and safety policy blocks (e.g. intimate apparel/undergarments) directly to the user and log records, while skipping redundant fallback calls to avoid web server FastCGI timeouts.
   - Stale Job Auto-Recovery & Logging: REST polling automatically detects jobs stuck in processing for over 90 seconds, marks them as failed, and writes a failure audit record to `wp_fito_tryon_logs`.
   - Guaranteed Error Logging & Client-Side Error Reporting: Dedicated REST endpoint (`POST /fito-tryon/v1/log-client-error`) and frontend error hook capture every error alert experienced by customers (including network disconnects, server timeouts, and proxy errors) and record them into the MySQL audit logs table with 60-second deduplication.
   - Provider-Aware Connection Testing: Real-time REST testing verifies keys against the selected provider before saving, and automatically triggers live model sync upon successful connection. Gated behind Pro for Gemini and OpenAI.
   - 1-Day Transient Model Caching: Model lists are cached in WordPress transients per provider for 24 hours (`DAY_IN_SECONDS`) to prevent redundant external API calls on page reloads, with a manual refresh button that supports passing active unsaved keys.
   - Localhost & Intranet resilient: Automatically resolves local attachment paths, upload directory mappings, and localhost URLs into base64 Data URLs so upstream cloud providers never fail with 400/private IP network fetch errors.
   - Fallback Model Routing (Pro): Automatically routes to secondary backup models if primary models encounter rate limits or provider downtime (gated behind the Pro Addon). In Free standalone mode, fallback model selection and execution are strictly disabled and hidden from provider settings.
   - Category-adaptive prompting for fashion clothing, shoes, glasses, jewelry, hats, and bags.
2. **Interactive Try-On Experience**:
   - Customizable placement & button label: above/below Add to Cart, custom product tab, Gutenberg block, or shortcode `[elfit_tryon]`. Custom button text configured in settings (`fito_tryon_button_text`) is resolved dynamically with fallback to default and filter contract `fito_tryon_button_text`.
   - Shop Catalog Loops (Pro): quick try-on action buttons directly on product catalog archive loops (`woocommerce_after_shop_loop_item`).
   - Product Gallery Images Try-On (Pro): quick try-on action overlay button on every image in the WooCommerce product gallery (`woocommerce_single_product_image_thumbnail_html`).
   - Product Variation Images Try-On (Pro): dedicated try-on button automatically bound to active variation photos and swatches (`woocommerce_single_variation`).
   - Custom Image CSS Selectors (Pro): store owners can configure custom CSS selectors (`fito_tryon_custom_image_selectors`) to automatically inject floating virtual try-on overlay badges on any matched images across single product pages (supporting Elementor, custom lookbooks, and custom product image sliders).
   - Drag-and-drop file upload zone + live camera capture (webcam/mobile camera).
   - Uncropped photo presentation: displays customer photos and try-on results as-is in any size or aspect ratio (portrait, landscape, square) with adaptive container aspect-ratio fitting.
   - Required privacy consent gate.
   - Interactive Before/After split comparison slider with drag handle.
   - Fullscreen Try-On Experience: users can inspect their try-on result and interactive comparison slider in an immersive full-screen view via a floating stage button or toolbar button, with seamless fallback for mobile devices and keyboard Escape support.
   - Category-Gated Placements across All Surfaces: All try-on entry points (single product buttons, custom product tabs, shortcode `[elfit_tryon]`, Gutenberg block, shop archive loops, product gallery images, variation image buttons, and custom image CSS selectors) strictly respect the "Enabled Product Categories" configuration (`fito_tryon_allowed_categories`), with automatic category hierarchy support (parent categories covering subcategories) and product variation parent inheritance.
   - Server-Side Category Guard: Direct REST submissions (`/fito-tryon/v1/submit`) enforce category restrictions on the server, rejecting disallowed product try-on requests with HTTP 403 Forbidden.
   - Variable product integration: tracks active variation image dynamically.
3. **Async Polling & Caching**:
   - Job queue table with status tracking (pending, processing, completed, failed).
   - Async REST polling with animated progress bar and rotating tip messages.
   - Result Caching (Pro): Cache identical user photo and product combinations to conserve API usage and minimize generation latency. Gated behind the Elfit Try-On Pro Addon. Supports watermark-aware cache keys and manual cache flushing. In the Free version, every generation runs fresh without caching.
4. **Account & Guest Flow**:
   - Access Control Settings: Store owners can configure whether virtual try-on is open to everyone (guests and registered users) or restricted to logged-in users only (`fito_tryon_access_level`).
   - Guest Prompt Modal & Sample Showcase: When restricted to logged-in users, guest clicks on the try-on button open the modal displaying a dedicated signup / login prompt step (`fito-step-login-required`) featuring a compelling sample virtual try-on preview card (`.fito-login-sample-card`) with a "Virtual Try-On Preview" badge and Before/After fitting transformation to encourage visitor signups.
   - Custom Guest Sample Image: Store owners can upload a custom sample try-on image via the WordPress Media Uploader (`fito_tryon_sample_image_id`) to display tailor-made model transformations inside the guest login prompt modal, or fallback to the built-in sample showcase graphic (`assets/images/sample-tryon.jfif`).
   - Dynamic Login URL configuration with a "Use My Account URL" helper button (`fito_tryon_login_url`).
   - Server-side REST API enforcement rejecting guest submissions with HTTP 401 when access is restricted.
   - My Account Try-On History (Pro): Dedicated "Virtual Fitting Room" endpoint and tab in WooCommerce My Account allowing logged-in customers to view, inspect, and manage their saved fitting results.
   - Save to Account Button (Pro): Toolbar action inside the try-on completion modal enabling customers to associate guest or current fitting results with their account.
5. **Watermark & Branding Engine (Pro)**:
   - Exclusively unlocked via the Elfit Try-On Pro Addon.
   - Brand logo image upload via standard WordPress Media Library.
   - Website address / custom text watermark (e.g. www.store.com) with "Use Site Address" quick-fill button.
   - Configurable transparency / opacity slider (10% to 100%).
   - 7-point position controls (bottom-right, bottom-left, bottom-center, top-right, top-left, top-center, center).
   - True alpha-channel blending for transparent PNG logos.
   - Live preview renderer via `/preview-watermark` REST endpoint for real-time testing in settings (server-side gated).
   - Settings card hidden and watermarking strictly suppressed at storage and REST layers when Pro is inactive.
6. **Adaptive Prompts Engine**:
   - Card-based accordion UI with visual category badges and prompt instructions.
   - Mutually Exclusive Category Selection: when a store category is connected to any prompt card, it is immediately hidden in real time from all other prompt cards to prevent assigning multiple fitting prompts to the same category.
   - One-Click Prompt Duplication (Pro): dedicated duplication action on each prompt card that clones the title and instruction template into a new custom row ready for editing. Gated behind the Elfit Try-On Pro Addon.
   - Protected System Presets with Revert option: 7 built-in presets (Clothing, Shoes, Eyewear, Watches, Hats, Bags, Jewelry) cannot be deleted to ensure store fitting stability, but their prompt instructions and connected categories can be customized freely in both Free and Pro versions.
   - Custom Fitting Profiles (Pro): admins can create unlimited custom prompt profiles for niche product lines (e.g. Scarf, Belt, Hijab, Swimwear) and duplicate existing presets. Adding new custom prompts and prompt duplication are strictly gated behind the Elfit Try-On Pro Addon; in Free mode, only the 7 built-in system presets are displayed and editable, with 'Add New Prompt' and 'Duplicate' controls completely concealed.
   - Multi-Language & Language-Independent Category Mapping: connects WooCommerce categories by term ID and slug, allowing stores in Persian, German, Spanish, French, or any other language to map their local categories directly to fitting prompts without relying on English keyword matching.
   - Server-Side Deduplication & Input Sanitization: `handle_save_prompts` enforces category exclusivity across all prompt rows at the backend level.
   - Placeholder Tag Injection: `{product_title}` placeholder tag dynamically replaced with the product name at generation time.
   - Default Fallback Strategy: automatically routes to the default Clothing & Apparel prompt if a product has no categories assigned or does not match any custom mapped profile.
7. **Admin Dashboard & Audit Logs**:
   - SaaS-grade UI with metrics (volume, success rate, average duration, estimated cost).
   - Chart.js visual volume chart.
   - Top tried-on products with conversion indicators.
   - Searchable, filterable audit log table with Previews (Before/After) thumbnail column, interactive floating hover preview popover in enlarged size (240px), one-click opening in a new tab, inspection modal with side-by-side visual comparison, and comprehensive pagination with configurable per-page options (10, 20, 50, 100 items per page).
   - User Profile Tracking: Dedicated User column in audit logs linking directly to the WordPress user profile (`user-edit.php?user_id=...`) for registered customers with username/email subtitle, and clear "Guest" labels for anonymous sessions.
   - Product Frontend & Admin Links: Product column displays the full product title, an explicit frontend "View" link (store permalink), and an admin "Edit" link (WooCommerce edit post URL) in new tabs. Never outputs generic "Product #0" fallback; handles deleted products and zero product IDs gracefully.
   - Save Images in Logs (Pro): Store owners can choose whether to store Before/After customer images in log records (`fito_tryon_log_images`), respecting customer photo retention window auto-cleanup. In the Free version, logs record metadata and error text only.
   - Configurable Photo Retention Window (Pro): Automatic WP-Cron cleanup of expired photos with custom durations from 1 to 720 hours (`fito_tryon_retention_hours`) for privacy and disk management. The Free version enforces a fixed 24-hour cleanup cycle.
   - Sent Prompt & Received AI Response Audit Logging: Captures and persists the full sent prompt (`prompt_text`) and the full received AI JSON response (`response_text`) in the MySQL `wp_fito_tryon_logs` table across all AI providers (OpenRouter, Gemini, OpenAI). Strips out heavy base64 image data strings to keep database rows lightweight while preserving all envelope metadata.
   - Comprehensive Error Logging: Logs all failure conditions into the database, including cases where the AI model returns text or errors without generating an image, storage image write errors, network timeouts, safety policy blocks, and unexpected runtime exceptions.
   - `fito_tryon_loaded` lifecycle hook dispatched on initialization to let the Pro Addon sync boot order.
   - `fito_tryon_allowed_providers` filter allowing Pro Addon to register premium direct API providers (`gemini`, `openai`).
   - `fito_tryon_resolve_provider_client` filter dispatching client resolution to Pro Addon for direct Gemini and OpenAI requests.
   - `fito_tryon_test_connection` filter delegating real-time API connection testing to Pro Addon for non-core providers.
   - `fito_tryon_fetch_models` filter delegating live model discovery to Pro Addon for non-core providers.
   - `fito_tryon_admin_provider_extra_fields` action hook allowing Pro Addon to inject secondary fallback model configuration rows into AI provider tables. Initial markup renders visible by default with the saved fallback model selected (`style="display: none;"` on skeleton), populates model choices silently from primary model load event (`fito_models_loaded`) with zero separate requests, and only refreshes skeleton when AI provider changes or manual sync is clicked.
   - `fito_tryon_fallback_model` filter allowing Pro Addon to supply secondary failover model routing for providers during generation (defaults cleanly to empty string in Free).
   - `fito_tryon_handle_generation_failover` filter delegating failover secondary model execution to Pro Addon upon primary attempt failure, completely removing retry loops and internal model stubs from Free (Option B).
   - `fito_tryon_purge_jobs_where` filter hook allowing Pro Addon to protect customer account-claimed fitting records during privacy purge while Free purges all temporary jobs standalone (`1=1`).
   - `fito_tryon_flush_cache` action hook notifying Pro Addon to flush transient cached results upon settings updates, category prompt mapping changes, and photo purges.
   - `fito_tryon_admin_prompts_list` filter allowing Pro Addon to provide custom fitting profiles (defaults cleanly to the 7 built-in system presets in Free).
   - `fito_tryon_save_prompts_config` filter allowing Pro Addon to sanitize and append custom prompts during configuration persistence without Free knowing about custom prompt formats.
   - `fito_tryon_admin_prompts_header_actions`, `fito_tryon_admin_prompt_row_actions`, `fito_tryon_admin_prompts_repeater_bottom`, `fito_tryon_admin_prompts_page_bottom` action hooks allowing Pro Addon to inject "Add New Prompt" buttons, row duplication actions, custom prompt deletion controls, and the custom prompt row template.
   - `fito_tryon_is_product_allowed` filter contract allowing Pro Addon catalog placements to query product eligibility without direct class coupling.
   - `fito_tryon_public_init` action hook invoked during storefront frontend initialization.
   - `fito_tryon_public_script_data` filter enabling Pro Addon to pass additional localized script parameters.
   - `fito_tryon_modal_result_actions` action hook injecting buttons into the try-on result modal (e.g. Save to Account).
   - `fito_tryon_after_result_image_saved` action hook notifying extensions of generated image disk path (used for GD watermarking).
   - `fito_tryon_get_cached_result` filter proxying transient cached try-on result lookups directly to Pro Addon without any intermediate stub class in Free (Option B).
   - `fito_tryon_set_cached_result` action hook proxying transient cached try-on storage directly to Pro Addon without any intermediate stub class in Free (Option B).
   - `fito_tryon_admin_appearance_settings` action hook enabling Pro Addon to render active placement and appearance options (Shop Archive Loops, Product Gallery Images, Product Variation Images, Custom Image CSS Selectors, Save to Account) directly under Section 2 ("Frontend Widget & Button Appearance") of the core settings screen.
   - `fito_tryon_admin_privacy_settings` action hook enabling Pro Addon to render active privacy, caching, and visual logging settings (Smart Result Caching, Visual Audit Logs, Photo Disk Retention Window) directly under Section 3 ("Abuse Prevention & Data Privacy") of the core settings screen.
   - `fito_tryon_admin_provider_panels` action hook enabling Pro Addon to dynamically render AI provider configuration tables (Gemini, OpenAI).
   - `fito_tryon_admin_save_provider_settings` action hook dispatching submitted provider credentials and settings to Pro Addon.
   - `fito_tryon_admin_logs_table_header` and `fito_tryon_admin_logs_table_cell` action hooks allowing Pro Addon to render the Previews column and photo thumbnails when visual logging is active.
   - `fito_tryon_after_log_created` lifecycle action enabling Pro Addon to store customer photo and result preview image URLs in audit logs when visual logging is enabled, without latent columns in Free schema.
   - `fito_tryon_admin_submenus` action hook allowing Pro to register the dedicated "Pro Features" submenu item under Elfit Try-On.
   - `fito_tryon_settings_form_bottom` action hook allowing Pro to render the complete Watermark & Branding Overlay configuration card (with Logo Uploader, Text/Site Address, Position, Opacity, and Live Preview) directly on the core settings screen.
   - `fito_tryon_save_settings_post` action hook allowing Pro Addon to intercept settings updates during core form submission and persist active appearance, privacy, caching, visual logging, and watermark options cleanly into Pro Addon storage (`fito_tryon_pro_settings`).
   - `fito_tryon_show_pro_card` filter gating upsells and the "Go Pro" submenu page, auto-suppressed when the Pro Addon is active (defaults to true).
   - Dedicated "Go Pro" submenu page with feature cards reflecting actual active Pro modules (Direct Gemini & OpenAI APIs, Brand Watermark & Logo Overlay, Archive Loops & Gallery & Variations, Custom Selectors, Variation Swatches, Result Caching & Retention, Customer Account History & Visual Logs), an authentic Free vs Pro comparison matrix, and direct link to official purchase page (`https://violo.ir/?p=721`).
   - Full compliance with WordPress.org Guideline 5 (Trialware) and Guideline 6 (SaaS): zero disabled inputs, zero filters defaulting to false/null to gate features, zero discarded user inputs, and clean BYOK SaaS integration documented in `readme.txt`.

## Code Simplifications & Optimizations (Ponytail Audit)
- **Dead Code Pruning**:
  - Removed orphaned `/email-result` REST endpoint and uncalled `emailResult()` client JavaScript method.
  - Pruned unused `get_job(int $job_id)`, `save_job_to_account()`, and `get_user_jobs()` from Free database layer (user account queries are self-contained in Pro Addon).
  - Deleted `Fito_Tryon_Cache` stub class from Free in favor of clean direct filter/action hooks (`fito_tryon_get_cached_result`, `fito_tryon_set_cached_result`, `fito_tryon_flush_cache`).
  - Moved watermark upload/preview JavaScript and CSS from Free assets into Pro Addon assets.
  - Introduced self-contained `Fito_Tryon_Pro_Storage` in Pro Addon, eliminating cross-plugin internal method calls.
  - Removed orphaned prompt translation strings (`Add New Prompt`, `Duplicate Prompt`) from Free POT catalog.
  - Pruned 18 unreferenced i18n keys and `'privacyNote'` string from public frontend localization.
  - Pruned unreferenced `'deleted'` string from admin script localization.
  - Pruned dead `fallback_image_render()` method and redundant provider checks from `Fito_Tryon_OpenRouter`.
  - Removed unreferenced CSS selectors (`.fito-single-stage`, `.fito-login-prompt-icon`) and unused custom properties (`--fito-admin-primary-hover`, `--fito-admin-bg`).
  - Removed duplicate modal dialog CSS rules in `assets/css/admin.css`.
- **Bloat & Over-Engineering Elimination**:
  - Simplified transient flush in `Fito_Tryon_Pro_Cache::flush()` to execute a single atomic SQL query deleting transients rather than two-step SELECT query + loop over `delete_transient()`.
  - Reused singleton instances (`Fito_Tryon::instance()->db` and `storage`) in `Fito_Tryon_Privacy` instead of creating redundant `new` objects.
  - Extracted `get_login_config()` helper in `Fito_Tryon_Public` to eliminate duplicated login settings retrieval between asset enqueueing and modal template markup.
  - Aligned `uninstall.php` options list by purging ghost options and including all active configuration options.
  - Fixed undeclared `self` reference bug in `FitoAdmin.bindConnectionTest()` to ensure reliable live model loading.

## Architectural Quality & WordPress.org Standards Compliance
- **Plugin Dependencies Header**: Added `Requires Plugins: woocommerce` to the main plugin file header, leveraging native WordPress 6.5+ dependency management.
- **Shortcode Unique Prefixing**: Standardized primary shortcode to `[elfit_tryon]` (4+ character unique prefix) across public renderer, admin UI, and documentation.
- **Conditional Asset Enqueuing**: Updated `enqueue_assets()` to dynamically detect `has_shortcode($post->post_content, 'elfit_tryon')` and `has_block('fito-tryon/button', $post)` on non-WooCommerce posts.
- **SSL Security**: Replaced hardcoded `'sslverify' => false` with environment-aware filterable check (`fito_tryon_sslverify`), ensuring strict SSL verification in production environments.
- **Database Caching**: Implemented `wp_cache_get()` / `wp_cache_set()` on `get_analytics_summary()` with automated invalidation when logs are cleared.
- **Date Standardization**: Replaced native `date()` calls with `gmdate()` for deterministic timezone-independent date arithmetic.
- **Autoload Memory Optimization**: Set `autoload => false` when saving large option payloads (`fito_tryon_prompts_config`, `fito_tryon_system_prompt`) to avoid bloat in the `alloptions` cache.
- **i18n & Translation Comments**: Added standard `// translators:` explanatory comments preceding all placeholder translation strings across all PHP classes.
## WordPress.org Strict Compliance Audit Remediation
- **Database Schema Cleansing (Guideline 5)**:
  - Removed dormant `is_saved_to_account` column and index from Free plugin `wp_fito_tryon_jobs` schema.
  - Removed dormant `user_image_url` and `result_image_url` columns from Free plugin `wp_fito_tryon_logs` schema.
  - Delegated column creation and migration to Pro Addon (`maybe_migrate_db()`) on activation and version upgrade.
- **Visual Audit Log & Comparison Viewer Decoupling**:
  - Replaced Free filter `fito_tryon_log_insert_data` with standard lifecycle action `fito_tryon_after_log_created`.
  - Replaced hardcoded Previews table headers and cells with `fito_tryon_admin_logs_table_header` and `fito_tryon_admin_logs_table_cell` action hooks.
  - Moved thumbnail hover zoom preview, side-by-side modal comparison viewer, and related styling completely into Pro Addon.
- **Custom Prompts Engine Decoupling**:
  - Removed custom prompt persistence, deletion handlers, and custom badge styles from Free plugin.
  - Replaced dormant masking filter with neutral lifecycle hooks `fito_tryon_admin_prompts_list` and `fito_tryon_save_prompts_config`, allowing Pro Addon to sanitize and append custom prompts independently.
  - Moved `.fito-badge-custom`, `.fito-prompt-duplicate`, and `.button.fito-btn-add-prompt-action` CSS into `assets/css/pro-admin.css`.
- **Clean Separation of Uninstall Handlers**:
  - Removed Pro options `fito_tryon_fallback_model` and `fito_tryon_retention_hours` from Free `uninstall.php`.
  - Pro Addon `uninstall.php` is solely responsible for cleaning up its own options.
- **Neutralized AI Error Messages**:
  - Replaced marketing upsell copy in AI provider HTTP 503/504 error response with neutral error text: 'The AI model provider is temporarily unavailable. Please try again in a few moments.'.
- **Submenu Branding Compliance**:
  - Removed decorative star emoji from the Go Pro submenu title (`__('Go Pro', 'elfit-ai-virtual-try-on-for-woocommerce')`).
- **Readme & Asset Transparency**:
  - Retained `Tested up to: 7.1` header per configuration requirements.
  - Removed Pro Addon upsell teaser from installation instructions in `readme.txt`.
  - Added `== Third-Party Assets ==` section explicitly documenting bundled Chart.js v4.4.1 (MIT license, authors, source URL).

## High-Traffic Performance & Database Scalability Optimizations
- **N+1 Query Elimination in Admin & Customer Account**:
  - `_prime_post_caches($log_product_ids, true, true)` warms WordPress post and postmeta caches in a single SQL query prior to rendering up to 100 log entries on the admin Try-On Logs page.
  - `_prime_post_caches($product_ids, true, true)` warms post and postmeta caches in a single SQL query before rendering customer try-on history on the My Account tab.
  - `_prime_post_caches` primes product caches in `get_top_products()`, eliminating repeated post queries for dashboard widgets.
- **In-Memory Category Permission Lookup**:
  - `Fito_Tryon_Public::is_product_allowed()` statically memoizes the enabled category array once per HTTP request.
  - Added per-request product evaluation cache (`static $memo = []`) and direct `WC_Product` instance acceptance, avoiding redundant option reads and taxonomy traversals in high-traffic catalog loops (`woocommerce_after_shop_loop_item`).
- **Database Index Optimization (dbDelta)**:
  - Bumped schema version to `1.3.0` in `Fito_Tryon_Database::DB_VERSION`.
  - Added `KEY created_at (created_at)` and composite `KEY user_status_created (user_id, status, created_at)` to `wp_fito_tryon_jobs` for fast cron purges and customer account lookups.
  - Added composite `KEY ip_product_status_created (ip_address, product_id, status, created_at)` to `wp_fito_tryon_logs` for optimized rate-limit and analytics filtering.
- **Batch Database Cleanup**:
  - Added `Fito_Tryon_Database::delete_expired_jobs(array $job_ids): int` executing atomic `DELETE FROM {$table} WHERE id IN (...)`.
  - Replaced iterative `$wpdb->delete()` in `Fito_Tryon_Privacy::run_cleanup()` with batch deletion, reducing up to hundreds of SQL round-trips to a single query.
  - Restricted `get_expired_jobs()` query to retrieve only needed columns (`id, user_image_path, result_image_path`) instead of `SELECT *`.
- **Admin Asset & Option Autoload Optimization**:
  - Restricted Chart.js bundle enqueueing and `get_chart_data(14)` database execution exclusively to the main Dashboard page hook (`toplevel_page_fito-tryon`), avoiding script overhead and database reads on Settings, Prompts, and Logs pages.
  - Implemented `wp_cache_get()` / `wp_cache_set()` for `get_chart_data()` and `get_top_products()` with automatic cache eviction on `clear_all_logs()`.
  - Configured `autoload => false` on admin-only and sensitive configuration options in `handle_save_settings()` to prevent swelling the global WordPress `alloptions` cache on frontend requests.

## Application Security & Hardening (OWASP / CWE Audit)
- **IDOR Prevention on Job Account Association**:
  - `Fito_Tryon_Pro_Account::save_job_to_account()` restricts claiming try-on jobs strictly to unassigned records (`user_id = 0`) or records already belonging to the requesting user (`user_id = $user_id`), preventing attackers from stealing other users' fitting history via token reuse.
- **IP Spoofing & Rate Limiting Hardening**:
  - `Fito_Tryon_Rate_Limiter::get_client_ip()` trusts `REMOTE_ADDR` by default, only inspecting proxy headers (`HTTP_CF_CONNECTING_IP`, `HTTP_X_FORWARDED_FOR`) when Cloudflare is detected or when filtered via `fito_tryon_trust_proxy_headers`.
- **Client Error Endpoint Throttling**:
  - `handle_log_client_error()` enforces transient-based rate limiting (maximum 10 error log submissions per minute per IP), mitigating log table flooding and database bloat.
- **Secret API Key Transmission Security**:
  - `Fito_Tryon_Gemini` transmits API credentials using the standard `x-goog-api-key` HTTP header across all endpoints (`models`, `generateContent`, `predict`) instead of URL query strings, preventing token leakage in proxy and server access logs.
- **Access Control Consistency**:
  - `handle_status()` enforces the `logged_in_only` access control policy (`fito_tryon_access_level`), blocking unauthorized guests from probing try-on job status.

## WordPress Coding Standards & VIP Compliance Remediation
- **Internationalization (i18n)**:
  - Added descriptive `// translators:` comments clarifying placeholder variables across all PHP classes.
  - Converted multiple translatable string placeholders from unordered (`%d, %d`, `%s, %s`) to ordered syntax (`%1$d, %2$d`, `%1$s, %2$s, %3$s`) to enable accurate localization across RTL/LTR languages.
- **Output Escaping & Input Sanitization**:
  - Added recursive unslashing and sanitization on complex `$_POST` array structures (`$_POST['prompts']`, `$_POST['fito_tryon_allowed_categories']`).
  - Added `sanitize_post_array()` recursive sanitization helper in `Fito_Tryon_Admin` ensuring raw superglobal `$_POST` data is never passed directly into hooks; `do_action('fito_tryon_admin_save_provider_settings', $ai_provider, $sanitized_post)` now receives pre-sanitized payload data.
  - Hardened all `handle_save_settings()` configuration inputs with `wp_unslash()`, explicit integer casting (`absint()`), key sanitization (`sanitize_key()`), and strict allowlists for button positions, log retentions, and rate limit periods.
  - Added unslashing and sanitization on server superglobals (`$_SERVER['REMOTE_ADDR']`, `$_SERVER['HTTP_CF_CONNECTING_IP']`, `$_SERVER['HTTP_X_FORWARDED_FOR']`, `$_COOKIE['fito_device_uuid']`).
- **Filesystem Security**:
  - Refactored `Fito_Tryon_Storage::handle_upload()` to use standard WordPress `wp_handle_upload()` instead of direct `move_uploaded_file()`, passing `test_form => false` overrides for REST API compatibility, dynamically routing storage via the `upload_dir` filter to the secure `.htaccess`-protected `fito-tryon/uploads` directory, and validating file mime types, dimensions, and unique anonymized filenames.
  - Replaced all direct `unlink()` calls in `Fito_Tryon_Storage` and test suites with standard WordPress `wp_delete_file()`.
  - Replaced PHP directory iteration recursion (`rmdir`/`unlink`) in `uninstall.php` with standard `WP_Filesystem()->delete()`.
- **Database & Query Hygiene**:
  - Replaced cross-file direct `$wpdb` queries in `Fito_Tryon_REST` and `Fito_Tryon_Privacy` with encapsulated database methods (`has_recent_failed_log()`, `purge_temporary_jobs()`).
  - Eliminated raw SQL string interpolation in `Fito_Tryon_Database::get_expired_jobs()`: replaced with 100% prepared SQL statements and strongly typed PHP filter contracts (`fito_tryon_expired_jobs_to_purge`, `fito_tryon_handle_purge_temporary_jobs`).
  - Decoupled visual audit log images from Free REST controllers via `fito_tryon_log_data` filter, ensuring Free REST controllers contain zero dormant Pro schema keys (`user_image_url`, `result_image_url`).
  - Replaced class-level phpcs:disable directives with targeted, line-specific `phpcs:ignore` annotations on custom table query operations in `Fito_Tryon_Database`, eliminating class-level suppressions of PluginCheck rules.
  - Hardened uninstaller transient cleanup in `uninstall.php` using `$wpdb->prepare()` with `$wpdb->esc_like()`.
- **Packaging & Distribution Hygiene**:
  - Added `.distignore` to the Free plugin root to automatically exclude tests, docs, and development artifacts (`/tests`, `/docs`, `/.codegraph`) from WordPress.org SVN release zips.
- **Asset Bundling & Scope Compliance**:
  - Replaced offloaded CDN script enqueue (`cdn.jsdelivr.net/npm/chart.js`) with locally bundled `assets/js/chart.umd.min.js` (Chart.js v4.5.1).
  - Updated `readme.txt` `== Third-Party Assets ==` Chart.js attribution to version v4.5.1 with exact release URL (`https://github.com/chartjs/Chart.js/releases/tag/v4.5.1`) and 2025 copyright matching bundled file header.
  - Replaced assignments to global `$product` in `class-fito-tryon-public.php` with local `$current_product` variables to eliminate `PrefixAllGlobals` violations.
  - Prefixed all global variables in `uninstall.php` with `fito_tryon_`.
  - Removed discouraged `load_plugin_textdomain()` call to comply with WordPress 4.6+ auto-loading standard.
  - Shortened `readme.txt` short description to under 150 characters to satisfy WordPress.org directory constraints.
  - Refined `readme.txt` `== External Services ==` section per Guideline 6 to document OpenRouter as the primary AI inference provider, and added Section 2 disclosing outbound HTTP requests made to retrieve generated images from provider CDNs and remote product images.
  - Enforced WordPress.org Guideline 5 (No Trialware / No Dead or Locked Code) via Option A: verified complete removal of all direct Gemini/OpenAI API handlers (`class-fito-tryon-gemini.php`, `class-fito-tryon-openai.php`) and result-caching logic (`class-fito-tryon-cache.php`) from the free repository plugin, isolating them strictly inside the commercial Pro Addon. Confirmed zero license checks, phone-home routines, or disabled controls exist in the free core plugin.
  - Decoupled visual audit log thumbnail cleanup from Free core: `Fito_Tryon_Database::get_expired_logs()` queries only native Free column `id`, and `Fito_Tryon_Privacy::run_cleanup()` fires `fito_tryon_before_delete_expired_logs` lifecycle hook, enabling Pro Addon to clean up stored thumbnail files before database row deletion.
  - Sanitized suggested privacy policy content in Free core by removing Pro customer account saving references in favor of a static generic statement.
  - Pre-Submission Directory Audit Remediation (Guideline 5 & 15 Cleanups):
    - Purged all latent Pro stylesheet rules from Free's `assets/css/admin.css` (`.fito-log-thumb*`, `.fito-modal-compare-grid*`) and relocated them to Pro's `assets/css/pro-admin.css`.
    - Purged all latent Pro frontend styling from Free's `assets/css/public.css` (`.fito-tryon-btn--archive`, `.fito-custom-image-wrapper`, `.fito-tryon-btn--overlay`, `.fito-account-tryons*`, `.fito-tryon-btn--gallery`, `.fito-variation-tryon-wrapper`, `.fito-tryon-btn--variation`) and relocated them into Pro's new `assets/css/pro-public.css` stylesheet, enqueued via Pro's `Fito_Tryon_Pro_Placements`.
    - Purged dormant variation wrapper animation lookups (`#fito-variation-tryon-wrapper`) and archive button conditionals from Free's `assets/js/public.js`, relocating the variation button handler to Pro's `assets/js/pro-public.js`.
    - Corrected `readme.txt` version 1.1.0 changelog entry to eliminate the inadvertent claim that prompt duplication was previously shipped in Free.
    - Added explicit source repository URL in `assets/js/chart.umd.min.js` header for Guideline 4 human-readable source compliance.
    - Removed dormant method `delete_unsaved_jobs()` from `includes/class-fito-tryon-database.php`.
    - Removed dormant helper method `delete_file_by_url()` from `includes/class-fito-tryon-storage.php`, migrating it into `Fito_Tryon_Pro_Storage::delete_file_by_url()` and updating `includes/class-fito-tryon-pro-account.php`.
    - Synchronized Gutenberg block metadata version to `1.4.0` in `includes/blocks/tryon-button/block.json`.
    - Stated User Assumptions: Retained `Contributors: micromax2` (vs Author `micromax`) and `Tested up to: 7.1` in `readme.txt` per explicit developer directive.

## Verification & Automated Testing
- **Unit test suite (Handshake & Free Standalone - 39 assertions)**: `rtk php tests/test-free-standalone-and-pro-handshake.php`
- **Syntax checks (PHP)**: `rtk php -l <file.php>`
- **PHP linting on entire codebase**: `rtk powershell -Command "Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l `$_.FullName }"`
- **Frontend JavaScript syntax validation**: `rtk powershell -Command "node -c assets/js/public.js; node -c assets/js/admin.js"`
- **Em dash guard verification**: `rtk powershell -Command "Select-String -Path 'admin/*.php', 'assets/js/*.js', 'assets/css/*.css', 'docs/*.md' -Pattern '[\u2014]'"`
