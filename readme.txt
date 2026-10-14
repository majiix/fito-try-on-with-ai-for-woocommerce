=== Elfit AI Virtual Try-On for WooCommerce ===
Contributors: micromax2
Tags: try on, virtual fitting, woocommerce, fashion, ai
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered virtual try-on for WooCommerce products. Customers upload a photo or snap a camera shot to preview realistic fittings in real time.

== Description ==

**Elfit AI Virtual Try-On for WooCommerce** brings studio-grade artificial intelligence virtual fitting technology directly into your WooCommerce store. Shoppers can upload a personal photo or snap a live selfie with their webcam or mobile camera to preview realistic try-ons of apparel, footwear, eyewear, jewelry, watches, hats, and accessories.

All image generations are powered by a Bring-Your-Own-Key (BYOK) architecture with OpenRouter. Connect directly to upstream AI vision models with zero middleman fees, zero recurring SaaS markups, and complete data privacy control.

---

### Key Features (100% Free Core)

* **OpenRouter Bring-Your-Own-Key (BYOK)**: Connect directly to open multimodal vision and diffusion models on OpenRouter (such as FLUX, Recraft, and Gemini via OpenRouter) using your personal developer API key.
* **Zero Middleman Fees**: Pay only raw AI compute fees directly to the provider (fractions of a cent per generation).
* **Interactive Comparison Split Slider**: Responsive mouse-drag and mobile touch-friendly split slider allowing shoppers to inspect exact Before and After fitting results.
* **Fullscreen Try-On Stage**: Immersive full-screen view mode with mobile optimization and keyboard Escape key closing.
* **Storefront Button Placements**: Flexible button display on single product pages (Above Add to Cart, Below Add to Cart, Custom Product Tab, or `[elfit_tryon]` shortcode).
* **Camera Capture & File Upload**: Drag-and-drop file upload zone supporting JPG, PNG, and WebP, plus integrated live webcam/mobile camera capture.
* **Category-Gated Store Filtering**: Enable virtual try-on storewide or exclusively for designated product categories with automatic subcategory coverage.
* **Store Access Controls & Guest Showcase**: Choose between public access for all visitors or restrict try-on strictly to registered users with custom guest Before/After showcase images and login prompts.
* **Advanced AI Fitting Prompts Manager**:
  - Built-in prompt presets for Clothing, Shoes, Eyewear, Watches, Hats, Bags, and Jewelry.
  - Mutually exclusive category mapping to prevent conflicting fitting instructions.
  - Dynamic `{product_title}` placeholder interpolation.
  - Global AI system prompt configuration for photorealism and identity fidelity.
* **Abuse Prevention & Privacy Controls**:
  - Adaptive rate limiting per customer account or per device cookie & IP (minute, hour, or day).
  - Configurable audit log retention in database (1 day, 3 days, 1 week, 30 days, or never).
  - One-click manual photo purge tool.
* **Admin KPI Dashboard & Analytics**: Real-time KPI metrics tracking daily, weekly, monthly, and lifetime try-ons, success rate, duration, spend, and bundled local Chart.js graph.
* **High-Performance Order Storage (HPOS) Compatible**: Fully isolated database tables compliant with WooCommerce HPOS.
* **Full RTL & Persian Localization**: Bundled Persian (fa_IR) translation files and native right-to-left layout support.

---

An optional commercial Pro Addon is available for advanced features such as additional direct AI providers, watermarking, caching, and extended storefront placements. See the Upgrade section below for details.

== Upgrade ==

The commercial **Elfit Try-On Pro Addon** extends Elfit AI Virtual Try-On with advanced features:
* Direct Google Gemini API integration (Gemini 2.5 & 3.1 Flash Image via Google AI Studio).
* Direct OpenAI API integration (GPT Image and DALL-E models).
* Automatic Fallback Model Routing across providers with secondary failover protection.
* Unlimited custom fitting prompts and 1-click prompt duplication for specialized apparel lines.
* GD brand watermarking and logo transparency overlay.
* Smart transient result caching to prevent duplicate generation costs.
* Storefront placements across shop catalog archive loops and product gallery thumbnails.
* Dynamic variation swatch and image synchronization.
* Customer Account try-on history tab ("My Try-Ons") in WooCommerce My Account.
* Visual audit logging with customer and result photo storage.
* Dedicated priority support and regular updates.

Learn more and purchase the Pro Addon: https://violo.ir/?p=721

== External Services ==

This plugin connects to external artificial intelligence APIs to generate realistic virtual try-on images. All connections use a Bring-Your-Own-Key (BYOK) architecture where the store administrator provides their own developer API key. No personal or customer data is transmitted until a shopper explicitly clicks the Try-On button and submits a fitting request, or when an administrator tests their API credentials in settings.

= 1. OpenRouter (Core Free Provider) =
* **What it is and what it does**: OpenRouter (openrouter.ai) is an AI model aggregator and routing service that processes virtual try-on requests using multimodal vision and diffusion models (such as FLUX, Recraft, and Gemini models via OpenRouter). Upstream generated fitting images returned by OpenRouter endpoints are downloaded via secure server-side HTTP GET requests into the store's local uploads directory for customer display and split-slider comparison.
* **When data is transmitted**: Data is transmitted when a customer submits a try-on request from a single product page or shortcode, when completed result images are retrieved from OpenRouter to the web server, or when the administrator clicks "Test Connection" in settings to verify API key validity.
* **What data is transmitted**: When a try-on is requested, the customer's uploaded or camera-captured photo, the WooCommerce product image, and category-specific prompt instructions are transmitted for AI inference. When retrieving completed images, standard HTTP GET request headers are sent to fetch the image bytes. During connection tests, only the API key is verified without sending customer data.
* **Terms of Service**: https://openrouter.ai/terms
* **Privacy Policy**: https://openrouter.ai/privacy

== Third-Party Assets ==

This plugin bundles the following open-source third-party library:

* **Chart.js** (v4.5.1)
  * Purpose: Powers analytics and try-on activity data visualization in the admin dashboard.
  * Source: https://github.com/chartjs/Chart.js/releases/tag/v4.5.1
  * License: MIT License (https://github.com/chartjs/Chart.js/blob/master/LICENSE.md)
  * Copyright: (c) 2025 Chart.js Contributors

== Installation ==

1. Upload the `elfit-ai-virtual-try-on-for-woocommerce` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **AI Try-On > Settings** in your WordPress admin menu.
4. Enter your OpenRouter API Key and click **Test Connection** to verify connectivity.
5. Choose your Primary Model from the available image generation models.
6. Configure button placement, store access controls, and enabled product categories as desired.
7. Navigate to **AI Try-On > Prompts** to customize fitting prompt instructions for your product categories.

== Frequently Asked Questions ==

= Which AI provider is supported in the free plugin? =
The free core plugin supports OpenRouter (openrouter.ai) out of the box with Bring-Your-Own-Key (BYOK) architecture, giving you access to dozens of vision models with zero middleman fees. Additional providers can be registered via the `fito_tryon_allowed_providers` filter or via the official Pro Addon.

= How does customer photo privacy work? =
Customer photos are stored in an isolated uploads directory protected by index.php files and server restrictions. Store owners can trigger a manual photo purge at any time from the settings screen or configure automatic log retention windows.

= Can I restrict virtual try-on to logged-in customers only? =
Yes. Set the Access Level to "Logged-In Users Only". When guests click the try-on button, a modal appears showcasing a sample Before/After fitting preview card along with your store's signup and login links. Direct REST submissions from guests are rejected with HTTP 401 Unauthorized.

= How does adaptive rate limiting work? =
When restricted to logged-in customers, limits are enforced per user account so users have consistent rate limits regardless of device or IP changes. When set to allow everyone, limits are enforced per browser device and IP address, allowing multiple people on the same household or office Wi-Fi network to enjoy separate try-on limits.

= Can I limit virtual try-on to specific product categories? =
Yes. In **AI Try-On > Settings**, select the enabled product categories. The try-on button will only appear for products in those categories, with automatic subcategory coverage. Submissions for non-allowed products are rejected on the server.

= Does this work with variable products? =
Yes. On variable products, the try-on button on the product page uses the active product image.

= How are category prompts kept exclusive? =
In **AI Try-On > Prompts**, each WooCommerce category can only be mapped to a single fitting prompt card. As soon as a category is selected in one card, it is hidden from all other prompt cards to prevent conflicting instructions.

= Are third-party scripts loaded from external CDNs? =
No. All assets, including Chart.js for analytics, are bundled locally within the plugin to ensure full compliance with WordPress.org security guidelines and offloading policies.

== Screenshots ==

1. Customer try-on modal with camera capture, photo upload, and privacy consent.
2. Interactive Before/After split comparison slider with drag handle.
3. Immersive full-screen try-on stage preview.
4. Admin analytics dashboard with try-on metrics and Chart.js activity graph.
5. Searchable audit logs with prompt and response code inspector.
6. AI Prompts manager with mutually exclusive category mapping and accordion cards.
7. Abuse prevention, rate limiting, and customer data privacy settings.
8. Guest signup prompt modal with sample Before/After preview card.

== Changelog ==

= 1.4.0 =
* Architecture: Established extensible action and filter contracts for Elfit Try-On Pro Addon.
* Feature: Added dedicated "Go Pro" submenu page featuring a comprehensive Free vs Pro comparison matrix.
* Extensibility: Added modular action and filter hooks for optional extension plugins.
* Enhancement: Added fito_tryon_loaded, fito_tryon_admin_submenus, and fito_tryon_settings_form_bottom hook contracts.
* Maintenance: Bumped core plugin version to 1.4.0.

= 1.3.0 =
* Security: Hardened REST API submit endpoint against SSRF with strict product gallery and upload directory validation.
* Security: Added URL and private IP range validation before outbound image resolution.
* Compliance: Cleaned admin menu titles and action links per WordPress.org dashboard integration standards.
* API: Promoted fito-tryon/v1 as canonical REST namespace with argument validation schemas and nonce verification.
* Performance: Primed post and postmeta caches with _prime_post_caches to eliminate N+1 database queries on admin logs.
* Performance: Added static memoization for category access lookups in high-traffic catalog loops.
* Database: Schema version bumped to 1.3.0 with composite indexes on jobs and logs tables.
* Database: Implemented atomic batch job deletion for background cleanup tasks.
* Compliance: Bundled Chart.js locally to comply with WordPress.org external asset offloading rules.
* Compliance: Full WordPress Coding Standards and PluginCheck verification with clean escaping, unslashing, and translators comments.

= 1.2.0 =
* Security: Hardened client IP resolution and throttled client error reporting endpoint.
* Feature: Added Sent Prompt and Received AI Response audit inspection in log details modal.
* Feature: Added configurable database log retention options (1 day, 3 days, 1 week, 30 days, or never).

= 1.1.0 =
* Feature: Added Category Prompts manager with mutually exclusive category mappings for 7 built-in apparel categories.
* Feature: Added Guest Sample Showcase modal card and access control gating.
* Feature: Added Fullscreen try-on inspection stage.

= 1.0.0 =
* Initial production release.
* OpenRouter API key validation and model selection.
* Async job queue and real-time REST polling with animated progress bar.
* Interactive Before/After split comparison slider.
* Full RTL support and Persian (fa_IR) localization.
