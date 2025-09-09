<?php

// Site Index
$pages = [];
// Site's Base URL
$base_url = 'https://zacharykai.net';
// Site's XML Sitemap
$sitemapPath = $_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml';

// Initialize the categorized_pages structure
// This will be populated dynamically from the sitemap
$categorized_pages = [];

// Check Sitemap
if (file_exists($sitemapPath)) {
    $dom = new DOMDocument();

    if ($dom->load($sitemapPath)) {
        $xpath = new DOMXPath($dom);

        // Register Standard Sitemap Namespace And Custom Namespace
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('zk', 'https://zacharykai.net/sitemap-ext');

        // Query All URL Elements
        $urlNodes = $xpath->query('//s:url');

        foreach ($urlNodes as $urlNode) {
            $url = '';
            $title = '';
            $content = '';
            $category_key = '';
            $subcategory_key = '';

            // Get URL
            $locNode = $xpath->query('s:loc', $urlNode)->item(0);
            if ($locNode) {
                $fullUrl = trim($locNode->nodeValue);
                // Convert full URL to relative path
                $url = str_replace($base_url, '', $fullUrl);

                // Handle the root URL specifically
                if (empty($url) || $url === '') {
                    $url = '/';
                } elseif (substr($url, -1) === '/' && $url !== '/') {
                    // Remove trailing slash for non-root directories like /zines/ -> /zines
                    // We'll normalize the Zines index back to /zines/ for internal consistency later if needed.
                    $url = rtrim($url, '/');
                }
            }

            // Get the custom title (zk:title)
            $titleNode = $xpath->query('zk:title', $urlNode)->item(0);
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

            // Get the custom content (zk:content)
            $contentNode = $xpath->query('zk:content', $urlNode)->item(0);
            if ($contentNode) {
                $content = trim($contentNode->nodeValue);
            }

            // Get the custom category (zk:category)
            $categoryNode = $xpath->query('zk:category', $urlNode)->item(0);
            if ($categoryNode) {
                $category_key = trim($categoryNode->nodeValue);
            }

            // Get the custom subcategory (zk:subcategory)
            $subcategoryNode = $xpath->query('zk:subcategory', $urlNode)->item(0);
            if ($subcategoryNode) {
                $subcategory_key = trim($subcategoryNode->nodeValue);
            }

            // Add the page to the list if URL and title are available
            if (!empty($url) && !empty($title) && !empty($category_key)) {
                $page_data = [
                    'title' => $title,
                    'url' => $url,
                    'content' => $content
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

                // Special handling for Zines root page: store it separately
                if ($category_key === 'zines' && ($url === '/zines/' || $url === '/zines')) {
                    $page_data['url'] = '/zines/'; // Ensure consistent Zines index URL
                    $categorized_pages[$category_key]['index_page'] = $page_data;
                } elseif (!empty($subcategory_key)) {
                    // Ensure the subcategory exists and has a title
                    if (!isset($categorized_pages[$category_key]['subcategories'][$subcategory_key])) {
                        $categorized_pages[$category_key]['subcategories'][$subcategory_key] = [
                            'title' => ucwords(str_replace('_', ' ', $subcategory_key)),
                            'urls' => []
                        ];
                    }
                    $categorized_pages[$category_key]['subcategories'][$subcategory_key]['urls'][] = $page_data;
                } else {
                    // If no subcategory, add to the main category's urls
                    $categorized_pages[$category_key]['urls'][] = $page_data;
                }
            }
        }
    } else {
        error_log("HTML SITEMAP ERROR: Failed to load sitemap.xml from $sitemapPath");
    }
} else {
    error_log("HTML SITEMAP ERROR: Sitemap.xml not found at $sitemapPath");
}

// Sort main categories alphabetically by title (e.g., Lists, Notes, Pages)
uasort($categorized_pages, function($a, $b) {
    return strcmp($a['title'], $b['title']);
});


// Sort category and subcategory URLs alphabetically by title
foreach ($categorized_pages as $cat_key => &$category) {
    if (isset($category['urls']) && is_array($category['urls'])) {
        usort($category['urls'], function($a, $b) {
            return strcmp($a['title'], $b['title']);
        });
    }
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
        unset($subcat); // Unset reference to avoid issues
    }
}
unset($category); // Unset reference to avoid issues


?>

<!DOCTYPE html>
<html lang="en-US">

    <!-- Head -->
    <head>
        <!-- Meta Tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- File Links -->
        <link rel="shortcut icon" href="/assets/icon.ico" type="image/x-icon">
        <link rel="stylesheet" href="/assets/style.css">
        <link rel="stylesheet" href="/assets/print.css" media="print">
        <link rel="alternate" type="application/rss+xml" title="Zachary Kai" href="/assets/rss.xml">
        <link rel="webmention" href="https://webmention.io/zacharykai.net/webmention" />
        <link rel="canonical" href="https://zacharykai.net/sitemap">
        <!-- Page Info -->
        <title>Sitemap | Zachary Kai</title>
        <meta name="date" content="2024-06-26">
        <meta name="last-modified" content="2025-06-22">
        <meta name="description" content="Herein are all pages, notes, and assorted miscellanea on this site so far. Always in progress. Enjoy your perusal and I hope you find (something) useful.">
    </head>

    <!-- Body -->
    <body>

        <!-- Skip Link -->
        <p><a href="#top" class="essentials">Begin reading...</a></p>

        <!-- Site Header -->
        <header><nav><a href="/">Zachary Kai</a></nav></header>

        <!-- Main Content -->
        <main class="h-entry e-content">

            <!-- Page Header -->
            <header>
                <p class="essentials"><a href="/">Homepage</a> • <a href="/sitemap#pages">Pages</a></p>
                <h1 class="p-name">Sitemap</h1>
                <p class="essentials">
                    <strong>Published</strong>: <time class="dt-published" datetime="2024-06-26">26 Jun 2024</time> | 
                    <strong>Updated</strong>: <time class="dt-modified" datetime="2025-06-22">22 Jun 2025</time>
                </p>
            </header>

            <!-- Page Body -->
            <section class="e-content">
                <p id="top" class="p-summary">Everything on my site (so far.) Please enjoy.</p>
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
                                        <?php if ($cat_key === 'zines' && isset($category['index_page'])): ?>
                                            <a href="<?php echo htmlspecialchars($category['index_page']['url']); ?>"><?php echo htmlspecialchars($category['index_page']['title']); ?></a>
                                        <?php else: ?>
                                            <a href="#<?php echo htmlspecialchars($cat_key); ?>"><?php echo htmlspecialchars($category['title']); ?></a>
                                        <?php endif; ?>

                                        <?php 
                                        // Check if there are any sub-items (direct URLs or subcategories) to list under this category
                                        $has_sub_items = (isset($category['urls']) && count($category['urls']) > (isset($category['index_page']) ? 1 : 0)) || $has_subcat_urls;

                                        if ($has_sub_items): ?>
                                            <ul>
                                                <?php 
                                                // List direct URLs of the category (excluding the index page if it exists and is already linked)
                                                if (isset($category['urls']) && !empty($category['urls'])): ?>
                                                    <?php foreach ($category['urls'] as $page): ?>
                                                        <?php if (!($cat_key === 'zines' && isset($category['index_page']) && $page['url'] === $category['index_page']['url'])): // Avoid duplicating zines index ?>
                                                            <li><a href="<?php echo htmlspecialchars($page['url']); ?>"><?php echo htmlspecialchars($page['title']); ?></a></li>
                                                        <?php endif; ?>
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
                        <hr>
                        <section id="<?php echo htmlspecialchars($cat_key); ?>">
                            <h2>
                                <?php if ($cat_key === 'zines' && isset($category['index_page'])): ?>
                                    <a href="<?php echo htmlspecialchars($category['index_page']['url']); ?>"><?php echo htmlspecialchars($category['index_page']['title']); ?></a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($category['title']); ?>
                                <?php endif; ?>
                            </h2>
                            <?php if (isset($category['urls']) && !empty($category['urls'])): ?>
                                <ul>
                                    <?php foreach ($category['urls'] as $page): ?>
                                        <li><a href="<?php echo htmlspecialchars($page['url']); ?>"><?php echo htmlspecialchars($page['title']); ?></a>: <?php echo htmlspecialchars($page['content']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (isset($category['subcategories'])): ?>
                                <?php foreach ($category['subcategories'] as $subcat_key => $subcat): ?>
                                    <?php if (!empty($subcat['urls'])): ?>
                                        <h3 id="<?php echo htmlspecialchars($subcat_key); ?>"><?php echo htmlspecialchars($subcat['title']); ?></h3>
                                        <ul>
                                            <?php foreach ($subcat['urls'] as $page): ?>
                                                <li><a href="<?php echo htmlspecialchars($page['url']); ?>"><?php echo htmlspecialchars($page['title']); ?></a>: <?php echo htmlspecialchars($page['content']); ?></li>
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
           
        </main>

        <!-- Back To Top Link -->
        <p><a href="#top" class="essentials">Read again...</a></p>

        <!-- H-Card -->
        <section class="h-card vcard">
            <section class="h-card-image">
                <picture>
                    <source srcset="/assets/icon.webp" type="image/webp">
                    <img class="u-photo" loading="lazy" src="/assets/icon.png" alt="Zachary Kai's digital drawing: 5 stacked books (blue/teal/green/purple, black spine designs), green plant behind top book, purple heart on either side.">
                </picture>
            </section>
            <section class="h-card-content">
                <p><strong><a class="u-url u-id p-name" href="https://zacharykai.net" rel="me"><span class="fn">Zachary Kai</span></a></strong> — <span class="p-pronouns">he/him</span> | <a class="u-email email" href="mailto:hi@zacharykai.net" rel="me">hi@zacharykai.net</a></p>
                <p class="p-note">Zachary Kai is a space fantasy writer, offbeat queer, traveler, zinester, and avowed generalist. The internet is his livelihood and lifeline.</p>
            </section>
        </section>

        <!-- Footer -->
        <footer>

            <!-- Acknowledgement Of Country -->
            <p style="margin-top: 0.25em;"><strong>Acknowledgement Of Country</strong>: I owe my existence to the <a href="https://kht.org.au/" rel="noopener">Koori people's</a> lands: tended for millennia by the traditional owners and storytellers. What a legacy. May it prevail.</p>

            <!-- Reply Via -->
            <p>
                <strong>Reply Via</strong>:
                <a href="/contact">Email</a> | 
                <a href="/guestbook">Guestbook</a> |
                <a href="/unoffice-hours">UnOffice Hours</a> | 
                <a href="/webmention" rel="noopener">Webmention</a>
            </p>

            <!-- Footer Menu -->
            <p>
                <strong>Est. 2024</strong> || 
                <a href="/about">About</a> | 
                <a href="/art">Art</a> | 
                <a href="/colophon">Accessibility & Colophon</a> | 
                <a href="/changelog">Changelog</a> |
                <a href="/jots">Jots</a> | 
                <a href="/now">Now</a> |
                <a href="/random">Random</a> | 
                <a href="/assets/rss.xml">RSS</a> |  
                <a href="/sitemap">Sitemap</a> | 
                <a href="/uses">Uses</a>
            </p>

            <!-- Elsewhere Links -->
            <p style="margin-bottom: 0;">
                <strong>Elsewhere</strong>:
                <a href="https://roadlessread.com" rel="noopener">Blog</a> |
                <a href="https://lunaseeker.com/catalog/" rel="noopener">Books</a> |
                <a href="https://lunaseeker.com/cv" rel="noopener">CV</a> |
                <a href="/github" rel="noopener">Github</a> | 
                <a href="/linkedin" rel="noopener">Linkedin</a> |
                <a href="https://lunaseeker.com/newsletter/" rel="noopener">Newsletter</a> |
                <a href="https://lunaseeker.com/offerings" rel="noopener">Offerings</a> |
                <a href="https://lunaseeker.com" rel="noopener">Portfolio</a>
            </p>
        </footer>
        
    </body>
</html>