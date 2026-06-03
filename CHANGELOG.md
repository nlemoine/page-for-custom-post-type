# Changelog

## [1.1.0](https://github.com/nlemoine/page-for-custom-post-type/compare/1.0.1...1.1.0) (2026-06-03)


### Features

* **acf:** target PFCPT pages via page_type location filters ([b293aff](https://github.com/nlemoine/page-for-custom-post-type/commit/b293aff15fc50b206179bafc291a4d8bc27138e7))


### Bug Fixes

* **acf:** bootstrap Advanced Custom Fields integration ([45a7042](https://github.com/nlemoine/page-for-custom-post-type/commit/45a704261c652a3d88ea2fcf4c41a2995ed9f07d))

## [1.0.1](https://github.com/nlemoine/page-for-custom-post-type/compare/1.0.0...1.0.1) (2026-05-08)


### Bug Fixes

* drop stale page mapping when CPT becomes ineligible ([021857b](https://github.com/nlemoine/page-for-custom-post-type/commit/021857ba309ca305a3c1ffac0ee420d50a9d5c56)), closes [#12](https://github.com/nlemoine/page-for-custom-post-type/issues/12)

## [1.0.0](https://github.com/nlemoine/page-for-custom-post-type/compare/0.5.0...1.0.0) (2026-05-08)


### ⚠ BREAKING CHANGES

* migrate pre-1.0 installs to opt-in use_slug
* Refactor plugin

### Features

* Add body class filtering for custom post type pages ([2799975](https://github.com/nlemoine/page-for-custom-post-type/commit/27999757e2a22a9b1166597461cce35a796dd029)), closes [#8](https://github.com/nlemoine/page-for-custom-post-type/issues/8)
* Add coverage for WPML in QA workflow ([649739d](https://github.com/nlemoine/page-for-custom-post-type/commit/649739dfb2030104db014b7c5a4df3421e0638aa))
* Add integration for The SEO Framework with custom ACF location rules and breadcrumbs ([ba8e6fe](https://github.com/nlemoine/page-for-custom-post-type/commit/ba8e6fe0d4ed18ea4324783f73a49195d02fa398))
* Add WPML integration ([3436613](https://github.com/nlemoine/page-for-custom-post-type/commit/34366132cd554183f0e5295b8add66baf3569893))
* clean up plugin options on uninstall ([7654442](https://github.com/nlemoine/page-for-custom-post-type/commit/76544422c96db33e5c9a89310bf3157f7da73218))
* Enhance coverage generation with additional test suites and merge functionality ([ea298fd](https://github.com/nlemoine/page-for-custom-post-type/commit/ea298fd55b62b695b706f2f291160e75e92ec814))
* Enhance integration tests for Polylang and Yoast SEO by clearing caches and ensuring indexables ([936b8de](https://github.com/nlemoine/page-for-custom-post-type/commit/936b8de4dd065bf014d94a8737fb5b4d9da1e24b))
* Enhance Polylang integration by setting default language for pages and cleaning up global state in tests ([a245d79](https://github.com/nlemoine/page-for-custom-post-type/commit/a245d79a33784fdd0246c27fdc20b86de0507e0c))
* Enhance Yoast SEO integration with targeted fix for page detection logic ([3aaeaf5](https://github.com/nlemoine/page-for-custom-post-type/commit/3aaeaf58048f25abfee6c70620dbc6d332263c81))
* Ensure default language is set for Polylang to prevent TypeError in post creation ([b4505db](https://github.com/nlemoine/page-for-custom-post-type/commit/b4505db4b9c55c2db274f1c15bb65e2a408390cb))
* i18n with .pot file generation ([a01dcb9](https://github.com/nlemoine/page-for-custom-post-type/commit/a01dcb969f5ec86ac55c1c020c3ce1e8cc0de797))
* Improve integration tests by ensuring taxonomy registration and updating Polylang language setup ([f845ff8](https://github.com/nlemoine/page-for-custom-post-type/commit/f845ff8b21428a3a43108c99e6b71c76e4513e18))
* Improve permalink handling in dropdown and set default language for Polylang ([84125ec](https://github.com/nlemoine/page-for-custom-post-type/commit/84125ecd60b37efea0c8253564908cb6a2d540f9))
* improve PHPStan ([a951c02](https://github.com/nlemoine/page-for-custom-post-type/commit/a951c023361a12e32a6c2cf9d681860ad74da703))
* improve PHPStan ([549594f](https://github.com/nlemoine/page-for-custom-post-type/commit/549594f9f708cabff918a3758655a76e738bf54d))
* Improve type safety and error handling across various components ([1187447](https://github.com/nlemoine/page-for-custom-post-type/commit/11874476f78bd11e0955f7f22833f683ce110462))
* migrate pre-1.0 installs to opt-in use_slug ([86a25c1](https://github.com/nlemoine/page-for-custom-post-type/commit/86a25c169c658e112a0146f7a5faa7a5d3359d5c))
* Refactor language setup in tests to use PLL()-&gt;model directly for improved reliability ([4791882](https://github.com/nlemoine/page-for-custom-post-type/commit/4791882a9be7b009707f3d7cc61093d4b902166e))
* Refactor plugin ([736a7e4](https://github.com/nlemoine/page-for-custom-post-type/commit/736a7e49c0b5e2e535ad76f100d1d9f4a0ce3cc2))
* Set up Polylang languages in tests to prevent TypeError during post creation ([7084b02](https://github.com/nlemoine/page-for-custom-post-type/commit/7084b0201cca7cf89a2358c242ee82dd0d42035d))
* Update function calls to use namespaced versions for improved clarity and maintainability ([a42e71d](https://github.com/nlemoine/page-for-custom-post-type/commit/a42e71d4e1971edc4409c06afa13a0c984834e80))
* Update integration tests to include WordPress version matrix and improve error handling ([a5ce2cf](https://github.com/nlemoine/page-for-custom-post-type/commit/a5ce2cf8bca5a63deeb61eb2d23352cf31dae354))
* Update page slug display in dropdown and improve related descriptions for SEO impact ([ddcd5f5](https://github.com/nlemoine/page-for-custom-post-type/commit/ddcd5f53c6b03f1976bc4f97277508dc325eb438))
* Update README for clarity and enhance bootstrap process for Polylang integration ([cc6f2b9](https://github.com/nlemoine/page-for-custom-post-type/commit/cc6f2b9680f568eab9e0ececa276dfd2aa25df74))
* warn before slug changes for assigned CPT pages ([ca1113e](https://github.com/nlemoine/page-for-custom-post-type/commit/ca1113effbe96e3c3ccd919407bc8c6b9b8d2e1a))


### Bug Fixes

* Flush rewrite rules on any permalink-affecting page update ([d151975](https://github.com/nlemoine/page-for-custom-post-type/commit/d1519758bba749bf13b5aa3c7ed1e6052837142b))
* reset Migrator CURRENT_VERSION to the last released version ([dfbb928](https://github.com/nlemoine/page-for-custom-post-type/commit/dfbb928c7c7206756ed5301f3c895de6ed1e1837))
* use block markers for the Migrator version constant ([9f51bc2](https://github.com/nlemoine/page-for-custom-post-type/commit/9f51bc2b7738fd4d4f1314957fbd4ffc3b2b082a))
* use constant ([08b2903](https://github.com/nlemoine/page-for-custom-post-type/commit/08b29038170b128f3216ad585528d0771aea8e5b))
* wpseo ([a26d8ed](https://github.com/nlemoine/page-for-custom-post-type/commit/a26d8eda007437403f97d5059c183490562e7587))
* wpseo ([35c984b](https://github.com/nlemoine/page-for-custom-post-type/commit/35c984bcdc5e75efec7dc75a72b6d6927b0beee0))
* wpseo ([3fda593](https://github.com/nlemoine/page-for-custom-post-type/commit/3fda593e49e74ba93d4a8db0d11b60332540c648))
