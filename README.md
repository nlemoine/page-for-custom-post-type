# Page for Custom Post Type

Assign any WordPress page as the archive page for a custom post type — just like the native "page for posts" setting.

## The problem

WordPress custom post type archives are dynamically generated and can't be edited as regular pages. This creates recurring issues:

- **No editable content**: clients can't add a title, excerpt, cover image, or custom fields to archive pages.
- **No SEO control**: archive pages lack the metadata management that pages offer through SEO plugins.
- **No page builder support**: archive templates can't leverage the page editor or custom fields.

Several plugins have attempted to solve this:

- [post-type-archive-pages](https://github.com/highrisedigital/post-type-archive-pages)
- [wp-post-type-archive-pages](https://github.com/DarrenTheDev/wp-post-type-archive-pages)
- [page-for-post-type (humanmade)](https://github.com/humanmade/page-for-post-type)
- [page-for-post-type (statenweb)](https://github.com/statenweb/page-for-post-type)

While these provided inspiration, none fully replicate WordPress's native behavior.

## Approach

This plugin mimics how WordPress handles the **posts page** (`show_on_front=page`, `page_for_posts={id}`). On a posts page request, `$wp_query` contains both:

- `$wp_query->queried_object` — the page itself (`WP_Post`)
- `$wp_query->posts` — the post type's posts (`WP_Post[]`)

This plugin replicates this exact behavior for custom post types, with no extra queries or new functions needed to get your page object.

## Setup

Once activated, your public custom post types appear in **Settings > Reading**.

![Settings > Reading](https://github.com/nlemoine/page-for-custom-post-type/assets/2526939/c725b560-ef7c-468e-9607-ef1617154c1c)

Select any published page to serve as the archive page for each custom post type.

### Use page slug as rewrite slug

An optional checkbox lets you use the assigned page's slug as the post type's rewrite slug. This means your archive URL and single post URLs will share the same base path (e.g., `/products/` for the archive and `/products/my-product/` for a single post).

> **Warning**: Enabling this option changes all single post URLs for the post type. Consider the SEO implications before toggling it on an existing site.

## Key differences with native CPT archives

|                    | CPT archive                                                | Page for CPT                                                                                      |
| ------------------ | ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| Conditionals       | `is_post_type_archive` = `true`<br>`is_archive` = `true`  | `is_home` = `true`<br>`is_{posttype}_page` = `true`<br>`is_page_for_custom_post_type` = `$posttype` |
| Queried object     | `WP_Post_Type`                                             | `WP_Post`                                                                                         |
| Template hierarchy | `archive-{posttype}.php`<br>`archive.php`<br>`index.php`  | `home-{posttype}.php`<br>`home.php`<br>`index.php`                                                |

## API

### Functions

```php
// Check if the current page is a "page for custom post type"
is_page_for_custom_post_type(?string $postType = null): bool

// Get the custom post type associated with a page ID
get_custom_post_type_for_page(int $pageId): ?string

// Get the page ID assigned to a custom post type
get_page_id_for_custom_post_type(?string $postType = null): ?int

// Get the URL for a custom post type's archive page
get_page_url_for_custom_post_type(?string $postType = null): ?string
```

All functions are available both in the `n5s\PageForCustomPostType` namespace and in the global namespace.

### Query properties

```php
// The post type slug, or false if not a PFCPT page
$wp_query->is_page_for_custom_post_type

// Boolean for a specific post type (e.g., is_product_page)
$wp_query->is_{posttype}_page
```

### Hooks

#### Filters

| Filter | Description |
| ------ | ----------- |
| `pfcpt/page_ids` | Modify the array of page ID / post type mappings |
| `pfcpt/post_type_from_id/page_id` | Filter page ID resolution for a post type |
| `pfcpt/dropdown_page_args` | Customize the page dropdown arguments in Settings |

#### Actions

| Action | Description |
| ------ | ----------- |
| `pfcpt/template_redirect` | Fires on `template_redirect` when on a PFCPT page |
| `pfcpt/flush_rewrite_rules` | Fires before rewrite rules are flushed |

## Integrations

### Polylang

Full multilingual support:

- Each language can have its own assigned page
- Archive URLs are automatically translated
- Page slugs are translated when using the "use page slug" option
- Settings dropdown only shows pages in the default language

Requires Polylang 3.4+.

### Yoast SEO (WordPress SEO)

- Full SEO metadata support on archive pages
- Correct breadcrumb trails (archive page appears in single post and taxonomy breadcrumbs)
- Proper `CollectionPage` schema markup
- Pages are indexed as pages, not archives

Requires Yoast SEO 26+.

### The SEO Framework

- SEO metadata support
- Correct breadcrumb trails
- Proper query type detection (page, not archive)

Requires The SEO Framework 5.1+.

### Advanced Custom Fields

- Adds a `is_page_for_custom_post_type` location rule
- Allows field groups to be conditionally displayed on PFCPT pages

Requires ACF 6+.

## Requirements

- PHP 8.2+
- WordPress with publicly queryable custom post types

## Installation

Install via Composer:

```bash
composer require n5s/page-for-custom-post-type
```

## License

GPL-3.0-or-later
