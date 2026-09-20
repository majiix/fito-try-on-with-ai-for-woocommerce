=== Fito Try-On with AI for WooCommerce ===
Contributors: micromax2
Tags: try on, virtual fitting, woocommerce, fashion, ai
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered virtual try-on for WooCommerce products. Customers upload a photo or snap a camera shot to preview realistic fittings in real time.

== Description ==

**Fito Try-On with AI for WooCommerce** brings studio-grade artificial intelligence virtual fitting technology directly into your WooCommerce store. Shoppers can upload a personal photo or snap a live selfie with their webcam or mobile camera to preview realistic try-ons of apparel, footwear, eyewear, jewelry, watches, hats, and accessories.

All image generations are powered by a Bring-Your-Own-Key (BYOK) architecture with OpenRouter in the core free plugin, and direct Google Gemini and OpenAI connections in the Pro Addon. Connect directly to upstream AI providers with zero middleman fees, zero recurring SaaS markups, and complete data privacy control.

---

### Free Features (Included in Core Plugin)

The core free version provides a complete, production-ready AI fitting room experience for any WooCommerce store:

* **OpenRouter AI Multi-Model Aggregator**: Connect directly using your personal OpenRouter API key to access leading diffusion and multimodal vision models (including FLUX, Recraft, and xAI Grok).
* **Zero Middleman Fees**: Pay only raw AI compute fees directly to the provider (fractions of a cent per generation).
* **Interactive Comparison Split Slider**: Responsive mouse-drag and mobile touch-friendly split slider allowing shoppers to inspect exact Before and After fitting results.
* **Fullscreen Try-On Stage**: Immersive full-screen view mode with mobile optimization and keyboard Escape key closing.
* **Aspect-Ratio Preserving Presentation**: Displays customer photos and fittings naturally in portrait, landscape, or square aspect ratios without cropping.
* **Camera Capture & File Upload**: Drag-and-drop file upload zone supporting JPG, PNG, and WebP, plus integrated live webcam/mobile camera capture.
* **Universal Single Product Placement**: Render virtual try-on buttons above Add to Cart, below Add to Cart, inside a dedicated custom product tab, or anywhere using the `[fito_tryon]` shortcode or Gutenberg block.
* **Category-Gated Store Filtering**: Enable virtual try-on exclusively for designated product categories with automatic subcategory coverage and parent variation inheritance.
* **Store Access Controls**: Choose between public access for all visitors or restrict try-on strictly to registered users.
* **Guest Showcase Mode**: When restricted to logged-in users, guest clicks display an engaging sample try-on card with Before/After transformations to drive user registrations.
* **Built-In Category Prompt Templates**: Pre-configured prompt cards for Clothing, Shoes, Glasses, Hats, Watches, Bags, and Jewelry with dynamic `{product_title}` placeholder interpolation.
* **Mutually Exclusive Category Mapping**: Categories mapped to one prompt card are dynamically hidden from others, preventing conflicting AI fitting rules.
* **Global System Prompt Customization**: Fine-tune role instructions, photorealism guidance, and lighting rules for your store's brand.
* **Adaptive Rate Limiting & Bot Shield**: Automatic IP and per-user throttling with a shared Wi-Fi safety ceiling to protect against script abuse.
* **Admin KPI Dashboard**: Real-time KPI metrics tracking daily, weekly, monthly, and lifetime try-ons, success rate percentage, average duration, and estimated spend.
* **Local Chart.js Analytics**: Bundled local Chart.js graph tracking try-on volume trends over time without loading external CDN scripts.
* **Top Tried-On Products**: Highlights top-performing products with try-on count and success rates.
* **Searchable Audit Logs**: Filter logs by status, date, and model with configurable pagination (10, 20, 50, 100 items per page) and Before/After hover thumbnail popovers.
* **Prompt & Response Inspector**: Inspect sent AI prompts and received AI JSON metadata directly inside log inspection modals.
* **Automatic Privacy & GDPR Cleanup**: WP-Cron automatically purges temporary customer photos and cleans expired audit logs.
* **Opt-In Store Watermark**: Optional text watermark overlay with position and opacity controls.
* **High-Performance Order Storage (HPOS) Compatible**: Fully isolated from WooCommerce order tables; 100% HPOS and custom order tables compliant.
* **Full RTL & Persian Localization**: Bundled Persian (fa_IR) translation files and native right-to-left layout support.

---

### Pro Addon Features (Take Your Store to the Next Level)

Supercharge your virtual fitting room with the **Fito Try-On Pro Addon**:

* **Direct Google Gemini Integration**: Connect directly to Google AI Studio API for ultra-fast generation with the Gemini Flash Image family, Imagen 3, and Nano Banana models.
* **Direct OpenAI API Integration**: Connect directly to OpenAI's official image generation API supporting modern vision models.
* **Automatic Fallback Model Routing**: Never lose a customer to provider downtime. Automatically routes requests to your backup model if the primary model hits rate limits or maintenance.
* **Catalog & Shop Loop Quick Try-On**: Floating quick try-on buttons on product cards across shop catalog loops and archive pages.
* **Product Gallery Thumbnail Buttons**: Floating try-on action badges on individual product gallery thumbnails, allowing customers to try specific photos.
* **Variable Products Dynamic Integration**: Dedicated variation try-on button dynamically syncing with active product swatches and variation photos.
* **Custom CSS Image Selectors**: Place floating try-on badges on custom images across your theme, Elementor layouts, lookbooks, and product carousels.
* **Save to Account & Fitting History**: Customers can save their favorite fitting results to their account and review them anytime in a dedicated "My Try-Ons" tab in WooCommerce My Account.
* **Custom Guest Sample Image**: Upload custom branded try-on showcase photography via the WordPress Media Uploader to display in the guest login modal.
* **Unlimited Custom Prompts**: Create, duplicate, and manage unlimited custom category prompt templates for unique product lines.
* **Smart Result Caching**: Cache identical user photo and product combinations to eliminate duplicate API calls and deliver instant previews.
* **Custom Store Logo Watermark**: Upload your custom store logo PNG with transparent alpha blending, custom branding text, and 7-point positioning controls.
* **Audit Log Image Archival**: Option to permanently archive customer Before and After images directly inside your admin audit logs.
* **Extended Photo Retention**: Configure customer photo retention periods up to 24 hours, 7 days, or 30 days.

---

### Why Upgrade to Pro?

1. **Higher Conversion Rates Across the Catalog**: Put try-on buttons everywhere shoppers browse: on shop archive loops, product gallery thumbnails, and variation swatches.
2. **Customer Loyalty & Saved Outfits**: Let shoppers save their looks to their WooCommerce customer accounts to compare styles and complete checkout later.
3. **Multi-Provider Redundancy**: Avoid lost sales during API downtime by pairing OpenRouter, Gemini, and OpenAI with automatic fallback routing.
4. **Branded Watermarks & Protection**: Stamp your official store logo PNG onto fitting images to boost brand recognition when customers share results on social media.
5. **Instant Previews with Caching**: Eliminate duplicate API spend and delight return shoppers with cached try-on results.

* **Get the Pro Addon**: [Buy Fito Try-On Pro](https://violo.ir/?p=721)
* Or upgrade directly from your WordPress admin dashboard under **AI Try-On > Go Pro**.

---

== External Services ==

This plugin connects to third-party AI image generation APIs using the store administrator's personal API keys:

1. OpenRouter (openrouter.ai):
   - Service: Routes virtual try-on requests to multimodal vision models (such as FLUX, Grok, and Gemini).
   - Data sent: Base64-encoded customer try-on photo, product image, and category fitting prompts.
   - Terms of Service: https://openrouter.ai/terms
   - Privacy Policy: https://openrouter.ai/privacy

2. Google Gemini / Google AI Studio (ai.google.dev):
   - Service: Direct Google AI Studio API for image generation (Gemini Flash Image family, Imagen 3).
   - Data sent: Base64-encoded customer try-on photo, product image, and category fitting prompts.
   - Terms of Service: https://ai.google.dev/terms
   - Privacy Policy: https://policies.google.com/privacy

3. OpenAI (platform.openai.com):
   - Service: Direct OpenAI API for virtual fitting image generation.
   - Data sent: Base64-encoded customer try-on photo, product image, and category fitting prompts.
   - Terms of Service: https://openai.com/policies/terms-of-use
   - Privacy Policy: https://openai.com/policies/privacy-policy

== Installation ==

1. Upload the `fito-try-on-with-ai-for-woocommerce` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **AI Try-On > Settings** in your WordPress admin menu.
4. Select your AI Provider (OpenRouter in Free, with Google Gemini and OpenAI in Pro), enter your API Key, and click **Test Connection** to verify connectivity.
5. Choose your Primary Model from the synced model list (with secondary Fallback Model failover in Pro).
6. Configure button placement, category access rules, and branding watermarks as desired.
7. Navigate to **AI Try-On > Prompts** to customize fitting prompt instructions for your product categories.

== Frequently Asked Questions ==

= Which AI providers are supported? =
The core free version supports OpenRouter (openrouter.ai) with access to dozens of leading vision and image generation models. With the Pro Addon, you can also connect directly to Google AI Studio (Google Gemini / Imagen 3) and OpenAI (DALL-E / GPT vision).

= How does customer photo privacy work? =
Customer photos are stored in an isolated uploads directory protected by index.php files and server restrictions. Uploaded customer photos are automatically purged by scheduled background tasks after your configured retention window. Store owners can also trigger a manual photo purge at any time from the settings screen.

= Can I restrict virtual try-on to logged-in customers only? =
Yes. Set the Access Level to "Logged-In Users Only". When guests click the try-on button, a modal appears showcasing a sample Before/After fitting preview card along with your store's signup and login links. Direct REST submissions from guests are rejected with HTTP 401 Unauthorized.

= How does adaptive rate limiting work? =
When restricted to logged-in customers, limits are enforced per user account so users have consistent rate limits regardless of device or IP changes. When set to allow everyone, limits are enforced per browser device and IP address, allowing multiple people on the same household or office Wi-Fi network to enjoy separate try-on limits.

= Can I limit virtual try-on to specific product categories? =
Yes. In **AI Try-On > Settings**, select the enabled product categories. The try-on button will only appear for products in those categories, with automatic subcategory coverage and parent variation inheritance. Submissions for non-allowed products are rejected on the server.

= Does this work with variable products? =
Yes. On variable products, the try-on button dynamically updates the reference garment image as the shopper selects different variations. The Pro Addon adds a dedicated variation button directly bound to variation swatches.

= How are category prompts kept exclusive? =
In **AI Try-On > Prompts**, each WooCommerce category can only be mapped to a single fitting prompt card. As soon as a category is selected in one card, it is hidden from all other prompt cards to prevent conflicting instructions.

= Are third-party scripts loaded from external CDNs? =
No. All assets, including Chart.js for analytics, are bundled locally within the plugin to ensure full compliance with WordPress.org security guidelines and offloading policies.

= What features are included in the Pro Addon? =
The Pro Addon adds direct Google Gemini and OpenAI integrations, automatic fallback model routing, custom button text and tab titles, shop loop archive buttons, gallery thumbnail try-on badges, variable product swatch buttons, custom CSS image selectors, "Save to account" with a My Account history tab, custom guest showcase image uploads, watermark removal and custom store logo branding, visual Before and After photo logs, extended photo retention, unlimited custom prompt templates, and smart result caching.
You can purchase the Pro Addon directly at: https://violo.ir/?p=721

== Screenshots ==

1. Customer try-on modal with camera capture, photo upload, and privacy consent.
2. Interactive Before/After split comparison slider with drag handle.
3. Immersive full-screen try-on stage preview.
4. Admin analytics dashboard with try-on metrics and Chart.js activity graph.
5. Searchable audit logs with Before/After hover popovers and prompt/response code inspector.
6. AI Prompts manager with mutually exclusive category mapping and accordion cards.
7. Watermark and brand overlay settings with real-time test preview.
8. Guest signup prompt modal with sample Before/After preview card.

== Changelog ==

= 1.3.0 =
* Security: Hardened REST API submit endpoint against SSRF with strict product gallery and upload directory validation.
* Security: Added URL and private IP range validation before outbound image resolution.
* Compliance: Made watermark branding strictly opt-in (disabled by default) per WordPress.org Guideline 10.
* Compliance: Cleaned admin menu titles and action links per WordPress.org dashboard integration standards.
* Routing: Registered My Account rewrite endpoint on activation and added proper rule flushing on activation/deactivation.
* API: Promoted fito-tryon/v1 as canonical REST namespace with argument validation schemas and nonce verification.
* Performance: Primed post and postmeta caches with _prime_post_caches to eliminate N+1 database queries on admin logs and My Account views.
* Performance: Added static memoization for category access lookups in high-traffic catalog loops.
* Database: Schema version bumped to 1.3.0 with composite indexes on jobs and logs tables.
* Database: Implemented atomic batch job deletion for background cleanup tasks.
* Compliance: Bundled Chart.js locally to comply with WordPress.org external asset offloading rules.
* Compliance: Full WordPress Coding Standards and PluginCheck verification with clean escaping, unslashing, and translators comments.

= 1.2.0 =
* Security: Implemented IDOR protection on job account assignment.
* Security: Switched Google Gemini API key transmission to standard x-goog-api-key HTTP headers.
* Security: Hardened client IP resolution and throttled client error reporting endpoint.
* Feature: Added Sent Prompt and Received AI Response audit inspection in log details modal.
* Feature: Added configurable database log retention options (1 day, 3 days, 1 week, 30 days, or never).

= 1.1.0 =
* Feature: Added direct Google Gemini / Imagen 3 API integration with live model discovery.
* Feature: Added direct OpenAI API integration with modern vision models.
* Feature: Added Category Prompts manager with mutually exclusive category mappings and prompt duplication.
* Feature: Added Guest Sample Showcase modal card and access control gating.
* Feature: Added store watermark branding overlay with opacity and position controls.
* Feature: Added Fullscreen try-on inspection stage.

= 1.0.0 =
* Initial production release.
* OpenRouter API key validation and model selection.
* Async job queue and real-time REST polling with animated progress bar.
* Interactive Before/After split comparison slider.
* WooCommerce My Account tab integration.
* Full RTL support and Persian (fa_IR) localization.
