<?php

// Setting Up Variables
$pages = [];
$base_url = 'https://lunaseeker.com';
$sitemapPath = $_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml';
$categorized_pages = [];

// Check Sitemap
if (file_exists($sitemapPath)) {
    $dom = new DOMDocument();

    if ($dom->load($sitemapPath)) {
        $xpath = new DOMXPath($dom);

        // Register Namespaces
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('ls', 'https://lunaseeker.com/sitemap-ext');

        // Query All URL Elements
        $urlNodes = $xpath->query('//s:url');

        foreach ($urlNodes as $urlNode) {
            $url = '';
            $title = '';
            $category_key = '';
            $subcategory_key = '';

            // Get URL
            $locNode = $xpath->query('s:loc', $urlNode)->item(0);
            if ($locNode) {
                $fullUrl = trim($locNode->nodeValue);
                $url = str_replace($base_url, '', $fullUrl);
                if (empty($url) || $url === '') {
                    $url = '/';
                } elseif (substr($url, -1) === '/' && $url !== '/') {
                    $url = rtrim($url, '/');
                }
            }

            // Get the custom title (zk:title)
            $titleNode = $xpath->query('ls:title', $urlNode)->item(0);
            if ($titleNode) {
                $title = trim($titleNode->nodeValue);
            } else {
                // Fallback: derive title from URL if zk:title is not present
                // This logic is simplified as we expect zk:title to be present for sitemap.php
                if (!empty($url)) {
                    $cleanedUrl = ltrim($url, '/');
                    $cleanedUrl = preg_replace('/\.(php|html|htm)$/i', '', $cleanedUrl);
                    $parts = explode('/', $cleanedUrl);
                    $lastPart = end($parts);
                    if (empty($lastPart) || $lastPart === 'index') {
                        if (count($parts) > 1) {
                            $title = str_replace('-', ' ', prev($parts));
                        } else {
                            $title = 'Homepage';
                        }
                    } else {
                        $title = str_replace('-', ' ', $lastPart);
                    }
                    $title = ucwords($title);
                    if ($title === '') $title = 'Homepage';
                }
            }

            // Get the custom category (zk:category)
            $categoryNode = $xpath->query('ls:category', $urlNode)->item(0);
            if ($categoryNode) {
                $category_key = trim($categoryNode->nodeValue);
            }

            // Get the custom subcategory (zk:subcategory)
            $subcategoryNode = $xpath->query('ls:subcategory', $urlNode)->item(0);
            if ($subcategoryNode) {
                $subcategory_key = trim($subcategoryNode->nodeValue);
            }

            // Add the page to the list if URL and title are available
            if (!empty($url) && !empty($title) && !empty($category_key)) {
                $page_data = [
                    'title' => $title,
                    'url' => $url,
                ];

                // Ensure the main category exists and has a title
                if (!isset($categorized_pages[$category_key])) {
                    // Determine category title from key (e.g., 'notes' -> 'Notes')
                    $categorized_pages[$category_key] = [
                        'title' => ucwords(str_replace('_', ' ', $category_key)),
                        'urls' => [],
                        'subcategories' => []
                    ];
                }

                // Check if this page is a category index page (e.g., /category-name/ or /category-name)
                // and it doesn't have a subcategory.
                $is_category_index = (
                    (trim($url, '/') === $category_key) ||
                    (trim($url, '/') === $category_key . '/index') // covers cases like /category/index.html
                ) && empty($subcategory_key);

                if ($is_category_index) {
                    $categorized_pages[$category_key]['index_page'] = $page_data;
                } elseif (!empty($subcategory_key)) {
                    // Special handling for Zines root page: store it separately
                    if (!isset($categorized_pages[$category_key]['subcategories'][$subcategory_key])) {
                        $categorized_pages[$category_key]['subcategories'][$subcategory_key] = [
                            'title' => ucwords(str_replace('_', ' ', $subcategory_key)),
                            'urls' => []
                        ];
                    }
                    $categorized_pages[$category_key]['subcategories'][$subcategory_key]['urls'][] = $page_data;
                } else {
                    // If no subcategory and not an index page, add to the main category's urls
                    $categorized_pages[$category_key]['urls'][] = $page_data;
                }
            }
        }
    }
}

// Sort Categories Alphabetically
uasort($categorized_pages, function($a, $b) {
    return strcmp($a['title'], $b['title']);
});

// Sort category and subcategory URLs alphabetically by title
foreach ($categorized_pages as $cat_key => &$category) {
    // Exclude the index_page from sorting with other direct URLs if it exists
    $direct_urls_to_sort = [];
    foreach ($category['urls'] as $page) {
        if (!isset($category['index_page']) || $page['url'] !== $category['index_page']['url']) {
            $direct_urls_to_sort[] = $page;
        }
    }
    usort($direct_urls_to_sort, function($a, $b) {
        return strcmp($a['title'], $b['title']);
    });
    $category['urls'] = $direct_urls_to_sort;


    if (isset($category['subcategories']) && is_array($category['subcategories'])) {
        // Sort subcategories by their title
        uasort($category['subcategories'], function($a, $b) {
            return strcmp($a['title'], $b['title']);
        });
        foreach ($category['subcategories'] as $subcat_key => &$subcat) {
            if (isset($subcat['urls']) && is_array($subcat['urls'])) {
                usort($subcat['urls'], function($a, $b) {
                    return strcmp($a['title'], $b['title']);
                });
            }
        }
        unset($subcat);
    }
}
unset($category);

?>

<!DOCTYPE html>
<html lang="en-US">

    <head>
        <!-- Meta Tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- Stylesheets & Files -->
        <link rel="shortcut icon" href="/assets/icon.ico" type="image/x-icon">
        <link rel="stylesheet" href="/assets/style.css">
        <link rel="stylesheet" href="/assets/print.css" media="print">
        <link rel="alternate" type="application/rss+xml" title="Lunaseeker Press" href="/assets/rss.xml">
        <link rel="webmention" href="https://webmention.io/zacharykai.net/webmention"/>
        <!-- Page Details -->
        <title>Sitemap | Lunaseeker Press</title>
        <link rel="canonical" href="https://lunaseeker.com/sitemap">
        <meta name="date" content="2025-09-13">
        <meta name="last-modified" content="2025-09-13">
        <meta name="description" content="Everything on Lunaseeker Press (so far.) Please enjoy.">
    </head>

    <body>

        <main>

            <!-- Header + Sidebar Navigation -->
            <nav>
                <ul>
                    <li><a href="https://lunaseeker.com" class="site-name">Lunaseeker Press</a></li>
                    <li><a href="/about">About</a></li>
                    <li><a href="/catalog/">Catalog</a></li>
                    <li><a href="/colophon">Colophon</a></li>
                    <li><a href="/cv">CV</a></li>
                    <li><a href="/events/">Events</a></li>
                    <li><a href="/newsletter/">Newsletter</a></li>
                    <li><a href="/offerings">Offerings</a></li>
                    <li><a href="/press">Press</a></li>
                    <li><a href="/sitemap">Sitemap</a></li>
                    <li><a href="/tools/">Tools</a></li>
                </ul>
            </nav>

            <!-- Main Content Area -->
            <article id="main">

                <!-- Page Header -->
                <header>
                    <p class="smalltext">You Are Here ➸ <a href="https://lunaseeker.com">Homepage</a> ↴</p>
                    <h1 class="p-name">Sitemap</h1>
                    <p class="smalltext">
                        <strong>Published</strong>: <time class="dt-published" datetime="2025-09-13">13 Sep 2025</time> | 
                        <strong>Updated</strong>: <time class="dt-modified" datetime="2025-09-13">13 Sep 2025</time>
                    </p>
                </header>
                
                <!-- Page Body -->

                <!-- Introduction -->
                <p id="top" class="p-summary">Everything on Lunaseeker Press (so far.) Please enjoy.</p>

                    <section id="table-of-contents">
                        <details>
                            <summary><strong>Table Of Contents</strong></summary>
                            <ul>
                                <?php foreach ($categorized_pages as $cat_key => $category): ?>
                                    <?php 
                                    $has_urls = !empty($category['urls']);
                                    $has_subcat_urls = false;
                                    if (isset($category['subcategories']) && is_array($category['subcategories'])) {
                                        foreach ($category['subcategories'] as $subcat) {
                                            if (!empty($subcat['urls'])) {
                                                $has_subcat_urls = true;
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                    <?php if ($has_urls || $has_subcat_urls || isset($category['index_page'])): ?>
                                        <li>
                                            <?php if (isset($category['index_page'])): ?>
                                                <a href="<?php echo htmlspecialchars($category['index_page']['url']); ?>"><?php echo htmlspecialchars($category['index_page']['title']); ?></a>
                                            <?php else: ?>
                                                <a href="#<?php echo htmlspecialchars($cat_key); ?>"><?php echo htmlspecialchars($category['title']); ?></a>
                                            <?php endif; ?>

                                            <?php 
                                            // Check if there are any sub-items (direct URLs or subcategories) to list under this category
                                            $has_sub_items = (isset($category['urls']) && count($category['urls']) > 0) || $has_subcat_urls;

                                            if ($has_sub_items): ?>
                                                <ul>
                                                    <?php 
                                                    // List direct URLs of the category (excluding the index page if it exists and is already linked)
                                                    if (isset($category['urls']) && !empty($category['urls'])): ?>
                                                        <?php foreach ($category['urls'] as $page): ?>
                                                            <?php // The index_page is already linked as the category header, so we skip it here.
                                                            if (isset($category['index_page']) && $page['url'] === $category['index_page']['url']) continue;
                                                            ?>
                                                            <li><a href="<?php echo htmlspecialchars($page['url']); ?>"><?php echo htmlspecialchars($page['title']); ?></a></li>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>

                                                    <?php 
                                                    // List subcategories
                                                    if (isset($category['subcategories']) && is_array($category['subcategories'])): ?>
                                                        <?php foreach ($category['subcategories'] as $subcat_key => $subcat): ?>
                                                            <?php if (!empty($subcat['urls'])): ?>
                                                                <li><a href="#<?php echo htmlspecialchars($subcat_key); ?>"><?php echo htmlspecialchars($subcat['title']); ?></a></li>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    </section>

                    <?php foreach ($categorized_pages as $cat_key => $category): ?>
                        <?php 
                        // Check if the category has any URLs directly or within its subcategories
                        $has_urls = !empty($category['urls']);
                        $has_subcat_urls = false;
                        if (isset($category['subcategories']) && is_array($category['subcategories'])) {
                            foreach ($category['subcategories'] as $subcat) {
                                if (!empty($subcat['urls'])) {
                                    $has_subcat_urls = true;
                                    break;
                                }
                            }
                        }
                        ?>
                        <?php if ($has_urls || $has_subcat_urls || isset($category['index_page'])): ?>
                            <section id="<?php echo htmlspecialchars($cat_key); ?>">
                                <h2>
                                    <?php if (isset($category['index_page'])): ?>
                                        <a href="<?php echo htmlspecialchars($category['index_page']['url']); ?>"><?php echo htmlspecialchars($category['index_page']['title']); ?></a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($category['title']); ?>
                                    <?php endif; ?>
                                </h2>
                                <?php if (isset($category['urls']) && !empty($category['urls'])): ?>
                                    <ul>
                                        <?php foreach ($category['urls'] as $page): ?>
                                            <?php // The index_page is already linked as the category header, so we skip it here.
                                            if (isset($category['index_page']) && $page['url'] === $category['index_page']['url']) continue;
                                            ?>
                                            <li><a href="<?php echo htmlspecialchars($page['url']); ?>"><?php echo htmlspecialchars($page['title']); ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <?php if (isset($category['subcategories'])): ?>
                                    <?php foreach ($category['subcategories'] as $subcat_key => $subcat): ?>
                                        <?php if (!empty($subcat['urls'])): ?>
                                            <h3 id="<?php echo htmlspecialchars($subcat_key); ?>"><?php echo htmlspecialchars($subcat['title']); ?></h3>
                                            <ul>
                                                <?php foreach ($subcat['urls'] as $page): ?>
                                                    <li><a href="<?php echo htmlspecialchars($page['url']); ?>"><?php echo htmlspecialchars($page['title']); ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </section>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                <p>•--♡--•</p>

                </section>
                
                <!-- Footer -->
                <footer>
                    <hr>
                    <section class="acknowledgement">
                        <h2>Acknowledgement Of Country</h2>
                        <p>I owe my existence to the <a href="https://kht.org.au/" rel="noopener">Koori people's</a> lands: tended for millennia by the traditional owners and storytellers. What a legacy. May it prevail.</p>
                    </section>
                    <p class="smalltext">Est. 2024 | Have a wonderful <a href="https://indieweb.org/Universal_Greeting_Time" rel="noopener">morning</a>, wherever you are.</p>
                </footer>

            </article>
        </main>
    </body>
</html>