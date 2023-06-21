# Page for custom post type

WordPress custom post type archive are dynamically generated pages that can't really be edited. 

If you dealed with it, you probably faced some recurring issues, among those:
- You might want to give your client the ability to add some content to that page (cover, title, excerpt, content, custom fields, etc.) just like any other page.
- You might want to customize SEO settings

There has been lots of efforts to circumvent this issues:
- https://github.com/highrisedigital/post-type-archive-pages
- https://github.com/DarrenTheDev/wp-post-type-archive-pages
- https://github.com/humanmade/page-for-post-type
- https://github.com/statenweb/page-for-post-type

Although these plugins provided great inspiration to design this plugin, none of them were really satisfying.

## Approach

This plugin tries to solve this problem by taking advantage of the native WordPress behavior, just like it does for the posts page. Which means (almost) no extra query or new function to get your page object.

In a posts page request (`show_on_front=page`, `page_for_posts={id}`), the `$wp_query` will contain both objects:
- `$wp_query->queried_object`: the custom post type archive page (`WP_Post`)
- `$wp_query->posts`: the custom post type posts (`WP_Post[]`)

The whole idea is to mimic this behavior for custom post types, hence the name.

Once activated, your custom post type will appear in Settings > Reading admin page.

![Capture d’écran 2023-06-16 à 12 01 40](https://github.com/nlemoine/page-for-custom-post-type/assets/2526939/c725b560-ef7c-468e-9607-ef1617154c1c)

Choose any page you want to set your page for custom post type.

## Key differences with CPT archive


|                    | CPT archive                                       | Home for CPT                                                                      |
| ------------------ |:-------------------------------------------------:|:---------------------------------------------------------------------------------:|
| Conditionals       | is_post_type_archive = `true`<br>is_archive = `true`  | is_home = `true`<br>is_$posttype_page = `true`<br>is_page_for_custom_type = `$posttype` |
| Queried object     | `WP_Post_Type`                                    | `WP_Post`                                                                         |
| Template hierarchy | archive-$posttype.php<br>archive.php<br>index.php | home-$posttype.php<br>home.php<br>index.php                                       |

## API

To be documented, in the meantime, check the `src/functions.php` file for getting an overview of available functions.

## Integrations

This plugin provides integrations with:
- ACF: add a new condition rule
- WordPress SEO
- Polylang
