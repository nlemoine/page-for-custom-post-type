<?php

use Mantle\Testkit\Test_Case as Testkit_Test_Case;
use n5s\PageForCustomPostType\Plugin;
use Yoast\WP\SEO\Memoizers\Meta_Tags_Context_Memoizer;

class TestHomeForCustomPostType extends Testkit_Test_Case
{
    private string $home_for_book_title = 'Home for Books';

    private int $home_for_book_id;

    private array $book_ids;

    private string $book_post_type = 'book';

    private string $genre_taxonomy = 'genre';

    public function setUp(): void
    {
        parent::setUp();

        // Clear WordPress SEO cache
        $memoizer = YoastSEO()->classes->get(Meta_Tags_Context_Memoizer::class);
        $memoizer->clear();

        $this->set_permalink_structure('/%postname%/');
        $this->setup_data();
    }

    public function test_pfcpt_query()
    {
        $this->get(get_permalink($this->home_for_book_id));

        global $wp_query;
        $this->assertEquals($this->home_for_book_id, $wp_query->queried_object_id);
        $this->assertEquals($this->book_post_type, $wp_query->is_page_for_custom_post_type);
        $this->assertTrue($wp_query->is_book_page);
        $this->assertEquals(
            array_column($wp_query->posts, 'ID'),
            array_slice(array_reverse($this->book_ids), 0, get_option('posts_per_page'))
        );
    }

    public function test_pfcpt_api()
    {
        $this->get(get_permalink($this->home_for_book_id));

        $this->assertTrue(is_page_for_custom_post_type($this->book_post_type));
        $this->assertFalse(is_page_for_custom_post_type('post'));
        $this->assertEquals('book', get_custom_post_type_for_page(get_option("page_for_{$this->book_post_type}")));
        $this->assertEquals('http://example.org/home-for-books/', get_page_url_for_custom_post_type($this->book_post_type));
    }

    public function test_pfcpt_wordpress_seo()
    {
        $this->get(get_permalink($this->home_for_book_id));
        /** @var \Yoast\WP\SEO\Surfaces\Values\Meta $surface */
        $page_meta = YoastSEO()->meta->for_current_page();

        $this->assertEquals('http://example.org/home-for-books/', $page_meta->canonical);
        // $this->assertEquals('Home for Books', $page_meta->title); // TODO fix
    }

    public function test_pfcpt_wordpress_seo_breadcrumbs_on_home_for_cpt()
    {
        $this->get(get_permalink($this->home_for_book_id));

        /** @var \Yoast\WP\SEO\Surfaces\Values\Meta $surface */
        $page_meta = YoastSEO()->meta->for_current_page();

        $expected = [
            [
                'url'  => 'http://example.org/',
                'text' => 'Home',
            ],
            [
                'url'  => get_permalink($this->home_for_book_id),
                'text' => $this->home_for_book_title,
                'id'   => $this->home_for_book_id,
            ],
        ];

        $this->assertEquals($expected, $page_meta->breadcrumbs);
    }

    public function test_pfcpt_wordpress_seo_breadcrumbs_on_post_for_cpt()
    {
        $books = get_posts([
            'post_type'      => $this->book_post_type,
            'posts_per_page' => 1,
            'order'          => 'RAND',
        ]);
        $current_book = $books[0];
        $this->get(get_permalink($current_book));

        /** @var \Yoast\WP\SEO\Surfaces\Values\Meta $surface */
        $book_meta = YoastSEO()->meta->for_current_page();

        $expected = [
            [
                'url'  => 'http://example.org/',
                'text' => 'Home',
            ],
            [
                'url'  => get_permalink($this->home_for_book_id),
                'text' => 'Home for Books',
                'id'   => $this->home_for_book_id,
            ],
            [
                'url'  => get_permalink($current_book),
                'text' => $current_book->post_title,
                'id'   => $current_book->ID,
            ],
        ];

        $this->assertEquals($expected, $book_meta->breadcrumbs);
    }

    public function test_pfcpt_wordpress_seo_breadcrumbs_on_taxonomy_for_cpt()
    {
        $genres = get_terms([
            'taxonomy'   => $this->genre_taxonomy,
            'hide_empty' => false,
        ]);

        $current_genre = $genres[0];
        $this->get(get_term_link($current_genre));

        /** @var \Yoast\WP\SEO\Surfaces\Values\Meta $surface */
        $genre_meta = YoastSEO()->meta->for_current_page();

        $expected = [
            [
                'url'  => 'http://example.org/',
                'text' => 'Home',
            ],
            [
                'url'  => get_permalink($this->home_for_book_id),
                'text' => 'Home for Books',
                'id'   => $this->home_for_book_id,
            ],
            [
                'url'      => get_term_link($current_genre),
                'text'     => $current_genre->name,
                'term_id'  => $current_genre->term_id,
                'taxonomy' => $this->genre_taxonomy,
            ],
        ];

        $this->assertEquals($expected, $genre_meta->breadcrumbs);
    }

    private function setup_data()
    {
        $this->book_ids = get_posts([
            'post_type'      => $this->book_post_type,
            'posts_per_page' => -1,
        ]);
        YoastSEO()->helpers->options->set('post_types-' . $this->book_post_type . '-maintax', 'genre');
        if (!count($this->book_ids)) {
            $genre_id = self::factory()->term->create([
                'taxonomy' => 'genre',
                'name'     => 'Fantasy',
            ]);
            $this->book_ids = self::factory()->post->create_many(30, [
                'post_type' => $this->book_post_type,
            ]);
            foreach ($this->book_ids as $book_id) {
                wp_set_object_terms($book_id, $genre_id, $this->genre_taxonomy);
            }
        }
        $home_for_book = get_page_by_path('home-for-books');
        if (!$home_for_book) {
            $this->home_for_book_id = self::factory()->post->create([
                'post_type'  => 'page',
                'post_title' => $this->home_for_book_title,
                'post_name'  => 'home-for-books',
            ]);
        } else {
            $this->home_for_book_id = $home_for_book->ID;
        }
        update_option('page_for_book', $this->home_for_book_id);
        update_option(Plugin::OPTION_PAGE_IDS, [
            $this->book_post_type => $this->home_for_book_id,
        ]);
    }
}
