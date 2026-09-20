# Fito Try-On with AI for WooCommerce

## Overview
Fito Try-On with AI for WooCommerce is an enterprise-grade WordPress and WooCommerce plugin that adds an AI-driven virtual fitting room experience to single product pages and catalog loops. Customers upload an existing photo or capture a fresh photo via their mobile/desktop camera to view realistic try-on results of apparel, footwear, eyewear, jewelry, watches, hats, and accessories.

All AI image generations are powered by multi-provider AI integrations (OpenRouter, Google Gemini, OpenAI), letting store owners connect their own API keys with zero subscription overhead or middleman fees.

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
fito-try-on-with-ai-for-woocommerce/
├── fito-try-on-with-ai-for-woocommerce.php  # Plugin bootstrap, constants & lifecycle hooks
├── uninstall.php                           # Complete cleanup on deletion
├── includes/
│   ├── class-fito-tryon.php                # Master orchestrator and autoloader
│   ├── class-fito-tryon-database.php       # dbDelta schema and database queries
│   ├── class-fito-tryon-storage.php        # Upload directory management & watermark overlay
│   ├── class-fito-tryon-openrouter.php     # OpenRouter API client, prompt synthesis & fallback routing
│   ├── class-fito-tryon-gemini.php         # Direct Google Gemini & Imagen 3 API client
│   ├── class-fito-tryon-openai.php         # Direct OpenAI image generation API client
│   ├── class-fito-tryon-rate-limiter.php   # Per-IP and per-user sliding window throttling
│   ├── class-fito-tryon-cache.php          # Hash-based try-on result caching
│   ├── class-fito-tryon-privacy.php        # WP-Cron auto-retention and GDPR photo purges
│   ├── class-fito-tryon-rest.php           # Nonce-secured REST endpoints
│   ├── class-fito-tryon-public.php         # Product button injection, modal markup & account tab
│   └── blocks/
│       └── tryon-button/                   # Gutenberg block definition
├── admin/
│   └── class-fito-tryon-admin.php          # Admin menu, dashboards, settings, logs & pagination
├── assets/
│   ├── css/
│   │   ├── admin.css                       # Modern card-based SaaS admin stylesheet
│   │   └── public.css                      # Modern frontend modal & split-slider CSS
│   └── js/
│       ├── admin.js                        # Admin interactions, Chart.js graphs & API tester
│       └── public.js                       # Upload, camera capture, crop, polling & comparison slider
├── languages/
│   ├── fito-try-on-with-ai-for-woocommerce.pot
│   ├── fito-try-on-with-ai-for-woocommerce-fa_IR.po
│   └── fito-try-on-with-ai-for-woocommerce-fa_IR.mo
├── readme.txt                              # WordPress.org standard documentation
└── SETUP.md                                # Setup instructions, OpenRouter key config & tuning
```

## Current Features
1. **Multi-Provider AI Architecture (OpenRouter, Google Gemini, OpenAI)**:
   - Configurable AI Provider: Store administrators can select their preferred backend provider via `fito_tryon_ai_provider` (`openrouter`, `gemini`, or `openai`).
   - Dedicated Credentials & Models per Provider: Separate API keys, primary models, and fallback models are stored independently for each service so settings are preserved when switching providers.
   - Direct Google Gemini Integration: Connects directly to Google AI Studio API (`/v1beta/models/{model}:generateContent` with `responseModalities: ["IMAGE", "TEXT"]`) supporting the complete Google Nano Banana image generation family (`gemini-3.1-flash-image`, `gemini-3-pro-image`, `gemini-2.5-flash-image`, etc.) and live model discovery.
   - Direct OpenAI Integration: Connects directly to OpenAI's image generation API (`/v1/images/generations`) supporting modern models (`gpt-image-2.5-flare`, `gpt-image-2.5-sunburst`, `dall-e-3`).
   - OpenRouter Aggregator Integration: Connects directly to OpenRouter's API (`/images` for dedicated diffusion models like Flux/Recraft, and `/chat/completions` for multimodal chat image models like Grok and Gemini 2.5).
   - Multimodal Image Extraction: Extracts generated images from both OpenAI-compatible `$choice['images']` arrays (used by xAI Grok and Gemini 2.5 on OpenRouter) and `$choice['content']` (markdown and data URLs), with robust support for array and string content parts.
   - Content Moderation & Safety Handling: Transparently captures and surfaces upstream moderation refusals and safety policy blocks (e.g. intimate apparel/undergarments) directly to the user and log records, while skipping redundant fallback calls to avoid web server FastCGI timeouts.
   - Stale Job Auto-Recovery & Logging: REST polling automatically detects jobs stuck in processing for over 90 seconds, marks them as failed, and writes a failure audit record to `wp_fito_tryon_logs`.
   - Guaranteed Error Logging & Client-Side Error Reporting: Dedicated REST endpoint (`POST /fito-tryon/v1/log-client-error`) and frontend error hook capture every error alert experienced by customers (including network disconnects, server timeouts, and proxy errors) and record them into the MySQL audit logs table with 60-second deduplication.
   - Provider-Aware Connection Testing: Real-time REST testing verifies keys against the selected provider before saving, and automatically triggers live model sync upon successful connection.
   - 1-Day Transient Model Caching: Model lists are cached in WordPress transients per provider for 24 hours (`DAY_IN_SECONDS`) to prevent redundant external API calls on page reloads, with a manual refresh button that supports passing active unsaved keys.
   - Localhost & Intranet resilient: Automatically resolves local attachment paths, upload directory mappings, and localhost URLs into base64 Data URLs so upstream cloud providers never fail with 400/private IP network fetch errors.
   - Automatic fallback model routing if the primary model encounters rate limits or provider downtime.
   - Category-adaptive prompting for fashion clothing, shoes, glasses, jewelry, hats, and bags.
2. **Interactive Try-On Experience**:
   - Customizable placement: above/below Add to Cart, custom product tab, or shortcode `[ai_tryon]`.
   - Product Gallery Images Try-On: optional quick try-on action overlay button on every image in the WooCommerce product gallery (`woocommerce_single_product_image_thumbnail_html`).
   - Product Variation Images Try-On: dedicated try-on button automatically bound to active variation photos and swatches (`woocommerce_single_variation`).
   - Custom Image CSS Selectors: store owners can configure custom CSS selectors (`fito_tryon_custom_image_selectors`) to automatically inject floating virtual try-on overlay badges on any matched images across single product pages (supporting Elementor, custom lookbooks, and custom product image sliders).
   - Drag-and-drop file upload zone + live camera capture (webcam/mobile camera).
   - Uncropped photo presentation: displays customer photos and try-on results as-is in any size or aspect ratio (portrait, landscape, square) with adaptive container aspect-ratio fitting.
   - Required privacy consent gate.
   - Interactive Before/After split comparison slider with drag handle.
   - Fullscreen Try-On Experience: users can inspect their try-on result and interactive comparison slider in an immersive full-screen view via a floating stage button or toolbar button, with seamless fallback for mobile devices and keyboard Escape support.
   - Category-Gated Placements across All Surfaces: All try-on entry points (single product buttons, custom product tabs, shortcode `[ai_tryon]`, Gutenberg block, shop archive loops, product gallery images, variation image buttons, and custom image CSS selectors) strictly respect the "Enabled Product Categories" configuration (`fito_tryon_allowed_categories`), with automatic category hierarchy support (parent categories covering subcategories) and product variation parent inheritance.
   - Server-Side Category Guard: Direct REST submissions (`/fito-tryon/v1/submit`) enforce category restrictions on the server, rejecting disallowed product try-on requests with HTTP 403 Forbidden.
   - Variable product integration: tracks active variation image dynamically.
3. **Async Polling & Caching**:
   - Job queue table with status tracking (pending, processing, completed, failed).
   - Async REST polling with animated progress bar and rotating tip messages.
   - Result Caching (Pro feature): Cache identical user photo and product combinations to conserve API usage and minimize generation latency. Free tier always requires fresh generation.
4. **Account & Guest Flow**:
   - Access Control Settings: Store owners can configure whether virtual try-on is open to everyone (guests and registered users) or restricted to logged-in users only (`fito_tryon_access_level`).
   - Guest Prompt Modal & Sample Showcase: When restricted to logged-in users, guest clicks on the try-on button open the modal displaying a dedicated signup / login prompt step (`fito-step-login-required`) featuring a compelling sample virtual try-on preview card (`.fito-login-sample-card`) with a "Virtual Try-On Preview" badge and Before/After fitting transformation to encourage visitor signups.
   - Custom Guest Sample Image (Pro feature): Store owners with Pro can upload a custom sample try-on image via the WordPress Media Uploader (`fito_tryon_sample_image_id`) to display tailor-made model transformations inside the guest login prompt modal. The Free tier displays the built-in high-resolution sample showcase graphic (`assets/images/sample-tryon.jfif`).
   - Dynamic Login URL configuration with a "Use My Account URL" helper button (`fito_tryon_login_url`).
   - Server-side REST API enforcement rejecting guest submissions with HTTP 401 when access is restricted.
   - Save to Account History (Pro feature): Store owners can enable/disable the "Save to account" option in plugin settings (`fito_tryon_save_to_account`). When enabled in Pro, logged-in customers see the "Save to account" button in the try-on result modal and can review their saved try-on sessions in the "My Try-Ons" tab under WooCommerce My Account. In the Free tier or when disabled, the button is hidden, the My Account menu item is removed, and the REST `/save-account` endpoint returns 403 Forbidden.
   - Guest browsing session persistence.
5. **Privacy & Retention / Abuse Prevention**:
   - Adaptive Rate Limiting Architecture: Rate limit enforcement dynamically adapts based on Access Control (`fito_tryon_access_level`):
     - When restricted to logged-in users, limits are enforced **per user account** (`fito_rl_{period}_u_{user_id}`) so authorized customers have consistent allowances regardless of IP changes.
     - When set to allow everyone, limits are enforced **per browser device & IP** via a persistent device identifier (`fito_device_uuid` cookie and `localStorage` token transmitted via `X-Fito-Device-ID` header and payload). Multiple family members or colleagues sharing the same NAT home/office Wi-Fi IP address retain independent try-on limits.
     - Bot & Script Abuse Ceiling: Guest mode incorporates an automated network safety ceiling on the shared IP (`max(15, limit * 3)`) to block automated bot attacks from rotating random device IDs.
   - Dynamic Settings Admin UI: Real-time dynamic toggling in Admin Settings between "Enforce try-on limits per user account" and "Enforce try-on limits per device cookie & IP" with contextual guidance depending on the selected Access Control.
   - Multi-Period Rate Limiting: Store owners can configure max try-on limits across customizable time windows: per minute (60s), per hour (3600s), or per day (86400s), with isolated transient windows and tailored error messages.
   - Photo Retention Window (Free vs Pro):
     - Free tier enforces a strict maximum photo retention window of 30 minutes, automatically purging unassigned temporary customer photos older than 30 minutes on every cleanup cycle.
     - Pro tier allows flexible retention customization from 1 hour to 30 days (720 hours) for long-term customer draft fitting comparisons.
   - Configurable Database Log Retention: Store owners can configure how long audit log records persist in the MySQL database via `fito_tryon_log_retention` (Do not log / none, 1 day, 3 days, 1 week, 30 days, or never clear logs; default: 30 days). Selecting "Do not log" completely disables database logging in `add_log`.
   - Automated Log & Image Cleanup: The scheduled WP-Cron task (`twicedaily`) automatically purges expired log rows and deletes associated stored Before/After image files from disk via `delete_file_by_url`.
   - Admin one-click purge of all stored photos.
   - Protected uploads folder (`.htaccess` and `index.php`).
6. **Prompts Management & Category Mapping**:
   - Dedicated "Prompts" admin submenu (`admin.php?page=fito-tryon-prompts`).
   - Global AI System Prompt: store owners can customize the high-level role, photorealism instructions, identity preservation, and lighting consistency for vision models, with a one-click "Reset to Default" button.
   - Category-Specific Fitting Prompts Repeater: dynamic repeater interface containing built-in presets (Clothing, Footwear, Eyewear, Hats, Watches, Bags, Jewelry) and custom-created fitting rules.
   - Custom Prompts Pro Gating: Adding new custom category prompts and duplicating presets is exclusive to Pro users (`Fito_Tryon::is_pro_active()`). Free users retain full editing freedom over the global AI system prompt and all 7 built-in presets (instructions and category associations). To strictly comply with WordPress.org Guideline 5, the Free tier omits locked/disabled buttons (`fito-btn-pro-locked`, `fito-prompt-duplicate-locked`) entirely rather than displaying crippled button controls; an informative showcase card provides seamless context and links to the Pro comparison page.
   - Collapsed Accordion View: all prompt cards load in a clean, collapsed view by default for rapid scanning and organization, expanding smoothly on toggle.
   - Mutually Exclusive Category Selection: when a store category is connected to any prompt card, it is immediately hidden in real time from all other prompt cards to prevent assigning multiple fitting prompts to the same category. Unchecking a category or deleting a custom prompt card immediately restores the category availability across other cards.
   - One-Click Prompt Duplication (Pro feature): dedicated duplication action on each prompt card that clones the title (appending Copy) and instruction template into a new custom row ready for editing, leaving category checkboxes unselected to maintain category exclusivity.
   - Protected System Presets: built-in presets cannot be deleted to ensure store fitting stability, but their prompt instructions and connected categories can be customized freely. Each built-in preset displays its original default prompt with an instant "Revert to Default" button.
   - Custom Fitting Profiles (Pro feature): admins with Pro can create additional custom prompt profiles for niche product lines (e.g. Scarf, Belt, Hijab, Swimwear) and delete those custom entries at any time.
   - Multi-Language & Language-Independent Category Mapping: connects WooCommerce categories by term ID and slug, allowing stores in Persian, German, Spanish, French, or any other language to map their local categories directly to fitting prompts without relying on English keyword matching.
   - Real-time Category Search, Notice and Counter: filter WooCommerce categories easily within each prompt card, view contextual empty notices when all categories are connected elsewhere, and view real-time connected category counters.
   - Server-Side Deduplication & Cache Invalidation: `handle_save_prompts` enforces category exclusivity across all prompt rows at the backend level, blocks unauthorized custom prompts if Pro is inactive, and automatically flushes the try-on cache so updated category mappings take effect immediately.
   - Placeholder Tag Injection: one-click `{product_title}` placeholder tag button for instant insertion into prompt instructions, dynamically replaced with the product name at generation time.
   - Default Fallback Strategy: automatically routes to the default Clothing & Apparel prompt if a product has no categories assigned or does not match any custom mapped profile.
7. **Admin Dashboard & Logs**:
    - SaaS-grade UI with metrics (volume, success rate, average duration, estimated cost).
    - Chart.js visual volume chart.
    - Top tried-on products with conversion indicators.
    - Searchable, filterable audit log table with Previews (Before/After) thumbnail column, interactive floating hover preview popover in enlarged size (240px), one-click opening in a new tab, inspection modal with side-by-side visual comparison, and comprehensive pagination with configurable per-page options (10, 20, 50, 100 items per page).
    - User Profile Tracking: Dedicated User column in audit logs linking directly to the WordPress user profile (`user-edit.php?user_id=...`) for registered customers with username/email subtitle, and clear "Guest" labels for anonymous sessions.
    - Product Frontend & Admin Links: Product column displays the full product title, an explicit frontend "View" link (store permalink), and an admin "Edit" link (WooCommerce edit post URL) in new tabs. Never outputs generic "Product #0" fallback; handles deleted products and zero product IDs gracefully.
    - Save Images in Logs (Pro feature): Pro tier can optionally store Before/After customer images in log records (`fito_tryon_log_images`), respecting customer photo retention window auto-cleanup. The Free tier logs all metadata, execution tokens, and prompt text without saving images on disk.
    - Sent Prompt & Received AI Response Audit Logging: Captures and persists the full sent prompt (`prompt_text`) and the full received AI JSON response (`response_text`) in the MySQL `wp_fito_tryon_logs` table across all AI providers (OpenRouter, Gemini, OpenAI). Strips out heavy base64 image data strings to keep database rows lightweight while preserving all envelope metadata (tokens, models, finish reasons, refusals, safety ratings). Displays them in the Admin Log Details inspection modal with formatted, scrollable code blocks and one-click "Copy" buttons.
    - Comprehensive Error Logging: Logs all failure conditions into the database, including cases where the AI model returns text or errors without generating an image, storage image write errors, network timeouts, safety policy blocks, and unexpected runtime exceptions. Automatically guarantees `response_text` is populated with structured JSON for all failures.
    - Watermark & Branding Overlay: store logo image, custom website address / text watermark (e.g. www.blabla.com) with "Use Site Address" quick-fill button, adjustable transparency/opacity slider (10% to 100%), 7-point position controls (bottom-right, bottom-left, bottom-center, top-right, top-left, top-center, center), true alpha-channel blending for transparent PNG logos, watermark-aware cache keys with automated cache invalidation, and instant "Test & Preview Overlay" sample composite renderer in admin settings.
## Code Simplifications & Optimizations (Ponytail Audit)
- **Dead Code Pruning**:
  - Removed orphaned `/email-result` REST endpoint and uncalled `emailResult()` client JavaScript method.
  - Pruned unused `get_job(int $job_id)` from database layer (replaced by token-based queries).
  - Pruned 18 unreferenced i18n keys and `'privacyNote'` string from public frontend localization.
  - Pruned unreferenced `'deleted'` string from admin script localization.
  - Pruned dead `fallback_image_render()` method and redundant provider checks from `Fito_Tryon_OpenRouter`.
  - Removed unreferenced CSS selectors (`.fito-single-stage`, `.fito-login-prompt-icon`) and unused custom properties (`--fito-admin-primary-hover`, `--fito-admin-bg`).
  - Removed duplicate modal dialog CSS rules in `assets/css/admin.css`.
- **Bloat & Over-Engineering Elimination**:
  - Simplified `Fito_Tryon_Cache::flush()` to execute a single atomic SQL query deleting transients rather than two-step SELECT query + loop over `delete_transient()`.
  - Reused singleton instances (`Fito_Tryon::instance()->db` and `storage`) in `Fito_Tryon_Privacy` instead of creating redundant `new` objects.
  - Extracted `get_login_config()` helper in `Fito_Tryon_Public` to eliminate duplicated login settings retrieval between asset enqueueing and modal template markup.
  - Aligned `uninstall.php` options list by purging 3 non-existent ghost options and adding active options (`fito_tryon_system_prompt`, `fito_tryon_prompts_config`, `fito_tryon_db_version`).
  - Fixed undeclared `self` reference bug in `FitoAdmin.bindConnectionTest()` to ensure reliable live model loading.

## Architectural Quality & WordPress.org Standards Compliance
- **Plugin Dependencies Header**: Added `Requires Plugins: woocommerce` to the main plugin file header, leveraging native WordPress 6.5+ dependency management.
- **Shortcode Unique Prefixing**: Standardized primary shortcode to `[fito_tryon]` (4+ character unique prefix) across public renderer, admin UI, and documentation.
- **Conditional Asset Enqueuing**: Updated `enqueue_assets()` to dynamically detect `has_shortcode($post->post_content, 'fito_tryon')` and `has_block('fito-tryon/button', $post)` on non-WooCommerce posts.
- **SSL Security**: Replaced hardcoded `'sslverify' => false` with environment-aware filterable check (`fito_tryon_sslverify`), ensuring strict SSL verification in production environments.
- **Database Caching**: Implemented `wp_cache_get()` / `wp_cache_set()` on `get_analytics_summary()` with automated invalidation when logs are cleared.
- **Date Standardization**: Replaced native `date()` calls with `gmdate()` for deterministic timezone-independent date arithmetic.
- **Autoload Memory Optimization**: Set `autoload => false` when saving large option payloads (`fito_tryon_prompts_config`, `fito_tryon_system_prompt`) to avoid bloat in the `alloptions` cache.
- **i18n & Translation Comments**: Added standard `// translators:` explanatory comments preceding all placeholder translation strings across all PHP classes.
- **WordPress.org Readme Compliance**: Enforced maximum 5 tags limit on `readme.txt` and added the mandatory `== External Services ==` disclosure section documenting OpenRouter, Google Gemini, and OpenAI.

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
  - `Fito_Tryon_Database::save_job_to_account()` restricts claiming try-on jobs strictly to unassigned records (`user_id = 0`) or records already belonging to the requesting user (`user_id = $user_id`), preventing attackers from stealing other users' fitting history via token reuse.
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
  - Added descriptive `// translators:` comments clarifying placeholder variables across `class-fito-tryon-admin.php`, `class-fito-tryon-openrouter.php`, `class-fito-tryon-rate-limiter.php`, and `class-fito-tryon-storage.php`.
  - Converted multiple translatable string placeholders from unordered (`%d, %d`, `%s, %s`) to ordered syntax (`%1$d, %2$d`, `%1$s, %2$s, %3$s`) to enable accurate localization across RTL/LTR languages.
- **Output Escaping & Sanitization**:
  - Escaped admin table pagination links via `wp_kses_post(paginate_links([...]))`.
  - Added recursive unslashing and sanitization on complex `$_POST` array structures (`$_POST['prompts']`, `$_POST['fito_tryon_allowed_categories']`).
  - Added unslashing and sanitization on server superglobals (`$_SERVER['REMOTE_ADDR']`, `$_SERVER['HTTP_CF_CONNECTING_IP']`, `$_SERVER['HTTP_X_FORWARDED_FOR']`, `$_COOKIE['fito_device_uuid']`).
- **Filesystem Security**:
  - Replaced all direct `unlink()` calls in `Fito_Tryon_Storage` and test suites with standard WordPress `wp_delete_file()`.
  - Replaced PHP directory iteration recursion (`rmdir`/`unlink`) in `uninstall.php` with standard `WP_Filesystem()->delete()`.
- **Database & Query Hygiene**:
  - Replaced cross-file direct `$wpdb` queries in `Fito_Tryon_REST` and `Fito_Tryon_Privacy` with encapsulated database methods (`has_recent_failed_log()`, `delete_unsaved_jobs()`).
  - Added explicit phpcs documentation and class-level disable directives on custom table query operations in `Fito_Tryon_Database`.
- **Asset Bundling & Scope Compliance**:
  - Replaced offloaded CDN script enqueue (`cdn.jsdelivr.net/npm/chart.js`) with locally bundled `assets/js/chart.umd.min.js`.
  - Replaced assignments to global `$product` in `class-fito-tryon-public.php` with local `$current_product` variables to eliminate `PrefixAllGlobals` violations.
  - Prefixed all global variables in `uninstall.php` with `fito_tryon_`.
  - Removed discouraged `load_plugin_textdomain()` call to comply with WordPress 4.6+ auto-loading standard.
  - Shortened `readme.txt` short description to under 150 characters to satisfy WordPress.org directory constraints.

## Pro Addon Companion Architecture
- **Pro Addon Scaffolding (`fito-try-on-with-ai-for-woocommerce-pro-addon`)**: Standalone companion plugin adhering to WordPress.org Guideline 5 and the Searchips/KooKoo Pro Addon architectural pattern.
- **Handshake & Version Parity Verification**: `Fito_Tryon::is_pro_active()` provides static memoized detection of the Pro Addon, requiring matching version numbers (`FITO_TRYON_VERSION === FITO_TRYON_PRO_VERSION`), class existence (`Fito_Tryon_Pro_Addon`), and active plugin status.
- **Compatibility Admin Notices**: The Pro Addon automatically verifies core plugin presence and version alignment on `plugins_loaded` (priority 25), surfacing dismissible admin notices if the core plugin is inactive or if versions diverge.
- **Lifecycle & Extension Hooks**: Core plugin emits `fito_tryon_loaded` and `fito_tryon_init`, while the Pro Addon emits `fito_tryon_pro_addon_init`, creating clean decoupled extension points for future feature migrations.
- **Pro Appearance & Frontend Placement Gating**:
  - 5 advanced appearance options are gated exclusively for Pro users: Custom Button Text (`fito_tryon_button_text`), Shop Archive Loops (`fito_tryon_archive_button`), Product Gallery Images (`fito_tryon_gallery_buttons`), Product Variation Images (`fito_tryon_variation_images`), and Custom Image CSS Selectors (`fito_tryon_custom_image_selectors`).
  - Core single product **Button Placement** (`fito_tryon_button_position`) remains 100% functional and free.
  - **WordPress.org Guideline 5 Compliance**: When Pro is inactive, the 5 settings are replaced with informative showcase cards (`.fito-pro-features-grid`) with feature explanations and Pro badges, avoiding disabled `<input disabled>` fields on active forms.
  - Server-side saving of the 5 options is strictly guarded by `Fito_Tryon::is_pro_active()` in `handle_save_settings()`.
- **Pro AI Providers & Fallback Model Gating**:
  - Direct Google Gemini API and Direct OpenAI API connections are gated exclusively for Pro users. The Free tier includes the OpenRouter aggregator with access to 100+ vision models.
  - The Fallback Model failover routing feature is gated exclusively for Pro users across all provider panels.
  - When Pro is inactive, the AI Provider select shows OpenRouter, accompanied by a Pro showcase card detailing Direct Gemini & OpenAI APIs with a deep-linked badge to `#pro-feature-direct-apis`.
  - In the OpenRouter panel, the Fallback Model input row is replaced with an informative showcase card detailing automated failover routing with a deep-linked badge to `#pro-feature-fallback-model`.
  - Server-side enforcement: `get_active_provider()` automatically normalizes `gemini` and `openai` to `openrouter` when Pro is inactive, and `get_fallback_model()` returns an empty string (disabling failover retries). Direct settings updates for these options are guarded in `handle_save_settings()`.
- **Pro Watermark & Branding Overlay Gating**:
  - All watermark customization controls (Enable Watermark, Watermark Image upload/preview/remove, Watermark Text / Site Address, Watermark Position, Watermark Opacity, and Test & Preview Overlay) are moved to Pro.
  - **Free Tier Default Branding**: In the Free tier, all generated virtual try-on photos automatically include a clean, semi-transparent "Made with Fito Try-On with AI for WooCommerce" branding watermark at the bottom of each image (`bottom_center`).
  - **Pro Tier Freedom**: Pro users enjoy clean, 100% watermark-free high-definition output by default, with the option to enable and fully customize their own store logo image, site address/text watermark, 7-point canvas positioning, opacity slider, and live composite preview.
  - **WordPress.org Guideline 5 Compliance**: When Pro is inactive, Card 3 in settings is replaced with an informative showcase card explaining free default branding vs Pro custom branding with a deep-linked "Pro" badge to `#pro-feature-watermark`.
  - **Server-Side Security**: Saving of watermark options is strictly guarded by `Fito_Tryon::is_pro_active()`, and the REST endpoint `/preview-watermark` rejects free tier requests with HTTP 403 Forbidden.
  - **Cache Fingerprint Isolation**: `Fito_Tryon_Cache::get_cache_key()` produces separate cache fingerprints for free branded images (`free_default_wm`) versus pro images (`nowm` or custom settings), ensuring cached free branded images are never served to Pro stores.

- **Comprehensive Defense-in-Depth Pro Access Controls**:
  - REST endpoints `/test-connection` and `/models` return HTTP 403 Forbidden for `gemini` and `openai` when Pro is inactive.
  - Direct class execution in `Fito_Tryon_Gemini::generate_tryon()` and `Fito_Tryon_OpenAI::generate_tryon()` immediately fails with a Pro-required error when Pro is inactive.
  - Placement rendering methods (`render_archive_button`, `filter_gallery_image_html`, `render_variation_button`) return immediately without output when Pro is inactive.
  - Internal watermark preview generator `Fito_Tryon_Storage::generate_watermark_preview()` returns failure when Pro is inactive.
  - Custom category prompts are excluded from synthesis in `build_category_prompt()`, filtered from view in `render_prompts_page()`, and discarded during `handle_save_prompts()` when Pro is inactive, ensuring only the 7 built-in presets are operational in the free tier.

## Go Pro Showcase & Comparison Architecture
- **Dedicated Admin Submenu (`fito-tryon-pro`)**:
  - Registered under AI Try-On menu with distinct `Go Pro ★` highlighted submenu entry when Pro is inactive.
  - Automatically hidden from the WordPress admin menu when the Pro addon is active (`Fito_Tryon::is_pro_active()`).
  - Accessible via direct URL `admin.php?page=fito-tryon-pro`.
- **Free vs. Pro Comparison Matrix Table**:
  - Clean SaaS comparison table with 17 feature rows organized into 7 distinct categories (AI Engine & Performance, Storefront & Placement, Customer Account & Guest Experience, Watermark & Branding, Abuse Prevention & Privacy, AI Prompts, and Updates & Priority Support). All non-existent feature claims (such as WP-CLI, user role quotas, and funnel analytics) were purged to maintain 100% truth in advertising.
  - Includes visual check indicators (included, excluded, partial/basic), feature subtitles, and upgrade buttons.
- **Comprehensive Pro Feature Deep Dives**:
  - 15 distinct feature cards covering Direct Gemini & OpenAI APIs, Automatic Fallback Model Failover Routing, Smart Result Caching, Button Text Customization, Shop Archive Loops, Product Gallery Images, Product Variations & Swatches, Custom Image CSS Selectors, Customer Account Try-On History ("Save to account"), Custom Guest Sample Try-On Image, Watermark Removal & Custom Branding, Visual Before & After Photo Logs, Flexible Photo Retention Window, Unlimited Custom Category Fitting Prompts, and Dedicated VIP Support.
  - Each card details "What it is", "How it works", and "Why it matters".
  - Interactive `:target` CSS animations with highlight glow when navigating directly from settings badges.
- **Seamless Cross-Plugin Navigation**:
  - Pro badges across the settings panel and headers directly link to their specific feature anchors on the Go Pro page.
  - Plugin action links on the WordPress Plugins screen (`add_action_links`) include a `Go Pro ★` link when Pro is inactive, and omit it when Pro is active.
- **Official Purchase CTA**:
  - All purchase actions link directly to the official purchase page: `https://violo.ir/?p=721`.

## Verification Commands
- Automated Unit Tests:
  - Complete Pro Gating & Access Control: `php -d extension=mysqli tests/test-complete-pro-gating.php`
  - Save to Account Pro Gating & Option: `php -d extension_dir="C:\Users\Espadana\AppData\Roaming\Local\lightning-services\php-8.3.0+1\bin\win64\ext" -d extension=mysqli tests/test-save-to-account-pro-gating.php`
  - AI Try-On Prompts Pro Gating: `php -d extension_dir="C:\Users\Espadana\AppData\Roaming\Local\lightning-services\php-8.3.0+1\bin\win64\ext" -d extension=mysqli tests/test-prompts-pro-gating.php`
  - Privacy, Caching & Retention Pro Gating: `php -d extension_dir="C:\Users\Espadana\AppData\Roaming\Local\lightning-services\php-8.3.0+1\bin\win64\ext" -d extension=mysqli tests/test-privacy-pro-gating.php`
  - Pro Providers & Fallback Model Gating: `php -d extension_dir="C:\Users\Espadana\AppData\Roaming\Local\lightning-services\php-8.3.0+1\bin\win64\ext" -d extension=mysqli tests/test-pro-provider-gating.php`
  - Go Pro Page, Comparison Table & Badges: `php -d extension_dir="C:\Users\Espadana\AppData\Roaming\Local\lightning-services\php-8.3.0+1\bin\win64\ext" -d extension=mysqli tests/test-go-pro-page.php`
  - Pro Appearance Options Gating: `php -d extension_dir="C:\Users\Espadana\AppData\Roaming\Local\lightning-services\php-8.3.0+1\bin\win64\ext" -d extension=mysqli tests/test-pro-appearance-gating.php`
  - Pro Addon Handshake & Version Parity: `php -d extension_dir="C:\Users\Espadana\AppData\Roaming\Local\lightning-services\php-8.3.0+1\bin\win64\ext" -d extension=mysqli tests/test-pro-addon-handshake.php`
  - Log Details Prompt & Response Persistence: `php tests/test-log-details-prompt-response.php`
  - Category-Gated Placements: `php tests/test-category-placements.php`
  - Multi-Provider AI & Text Neutrality: `php tests/test-multi-provider.php`
  - Adaptive Rate Limiting: `php tests/test-adaptive-rate-limiting.php`
  - Guest Sample Try-On Image: `php tests/test-sample-tryon-image.php`
  - Database Log Retention: `php tests/test-log-retention.php`
  - Logs Pagination & Export Route Removal: `php tests/test-logs-pagination.php`
  - Watermark & Cache: `php tests/test-watermark.php`
  - Category Exclusivity & Deduplication: `php tests/test-categories-exclusivity.php`
- Syntax checks (PHP): `php -l <file.php>`
- PHP linting on entire codebase: `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`
- Frontend JavaScript syntax validation: `node -c assets/js/public.js`, `node -c assets/js/admin.js`, `node -c includes/blocks/tryon-button/index.js`
- Em dash guard verification: `rtk powershell -Command "Select-String -Path 'admin/*.php', 'assets/js/*.js', 'assets/css/*.css' -Pattern '[\u2014]'"`
