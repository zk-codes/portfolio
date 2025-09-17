<?php

header('Content-Type: application/xml; charset=utf-8');

$notesDirectory = $_SERVER['DOCUMENT_ROOT'] . '/newsletter/';

$siteBaseUrl = 'https://lunaseeker.com/';
$feedTitle = 'Lunaseeker Press';
$feedLink = $siteBaseUrl;
$feedDescription = 'Wandering Words: a monthly missive of interesting/esoteric reads, links, interviews, and curiosities.';
$feedLanguage = 'en-us';
$feedImageUrl = $siteBaseUrl . 'assets/icon.png';
$feedImageTitle = 'Lunaseeker Press';
$feedImageLink = $siteBaseUrl;

$sourceFiles = [];

// Add files from the notes directory
if (is_dir($notesDirectory)) {
    $iterator = new DirectoryIterator($notesDirectory);
    foreach ($iterator as $fileinfo) {
        if ($fileinfo->isFile() && $fileinfo->getExtension() === 'html') {
            $filePath = $fileinfo->getPathname();
            $sourceFiles[] = $filePath;
        }
    }
}

/**
 * Extracts content from a standard HTML file (e.g., from the /notes directory) for RSS.
 */
function extractHtmlContent($htmlContent, $filePath, $siteBaseUrl) {
    $dom = new DOMDocument();
    
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $htmlContent);
    libxml_clear_errors();
    
    $entry = [];
    $xpath = new DOMXPath($dom);
    
    // --- Extract Title ---
    $titleNodes = $dom->getElementsByTagName('title');
    if ($titleNodes->length > 0) {
        $entry['title'] = trim($titleNodes->item(0)->textContent);
    } else {
        $h1Nodes = $dom->getElementsByTagName('h1');
        if ($h1Nodes->length > 0) {
            $entry['title'] = trim($h1Nodes->item(0)->textContent);
        } else {
            $entry['title'] = 'Untitled Entry';
        }
    }
    
    // --- Extract Published Date ---
    $entry['published'] = null;
    $metaNodes = $xpath->query('//meta[@name="date" or @name="published" or @property="article:published_time"]');
    if ($metaNodes->length > 0) {
        $entry['published'] = $metaNodes->item(0)->getAttribute('content');
    } else {
        $timeNodes = $xpath->query('//time[@datetime]');
        if ($timeNodes->length > 0) {
            $entry['published'] = $timeNodes->item(0)->getAttribute('datetime');
        }
    }
    
    // --- Extract Content (e-content) ---
    $contentNode = null;
    $eContentNodes = $xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " e-content ")]');
    if ($eContentNodes->length > 0) {
        $contentNode = $eContentNodes->item(0);
    }
    $entry['content'] = $contentNode ? $dom->saveHTML($contentNode) : '';
    
    // --- Construct URL ---
    $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $filePath);
    $entry['url'] = $siteBaseUrl . ltrim($relativePath, '/');
    // Remove .html from the URL if present for clean URLs
    if (substr($entry['url'], -5) === '.html') {
         $entry['url'] = substr($entry['url'], 0, -5);
    }
    
    $entry['sourceFilePath'] = $filePath;
    
    return $entry;
}

$allEntries = [];

foreach ($sourceFiles as $filePath) {
    $htmlContent = file_get_contents($filePath);
    if ($htmlContent === false) {
        error_log("Could not read file: " . $filePath);
        continue;
    }
    
    // Check if the current file is the /jots file to apply h-feed parsing
    // We're checking if the full path contains '/jots'
    if (strpos($filePath, $_SERVER['DOCUMENT_ROOT'] . '/jots') === 0) { 
        $jotEntries = extractHFeedEntries($htmlContent, $filePath, $siteBaseUrl);
        $allEntries = array_merge($allEntries, $jotEntries);
    } else {
        // Otherwise, process as a standard HTML content file (e.g., from /notes)
        $entry = extractHtmlContent($htmlContent, $filePath, $siteBaseUrl);
        $allEntries[] = $entry;
    }
}

// Sort all entries by date, newest first
usort($allEntries, function($a, $b) {
    // Determine published time, falling back to file modification time if 'published' isn't available or valid
    $timeA = !empty($a['published']) ? strtotime($a['published']) : false;
    if ($timeA === false) $timeA = filemtime($a['sourceFilePath']);

    $timeB = !empty($b['published']) ? strtotime($b['published']) : false;
    if ($timeB === false) $timeB = filemtime($b['sourceFilePath']);
    
    return $timeB <=> $timeA; // Sort in descending order (newest first)
});

// --- RSS Feed Generation ---
$dom = new DOMDocument('1.0', 'UTF-8');
$rss = $dom->createElement('rss');
$rss->setAttribute('version', '2.0');
$rss->setAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
$rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
$dom->appendChild($rss);

$channel = $dom->createElement('channel');
$rss->appendChild($channel);

$channel->appendChild($dom->createElement('title', $feedTitle));
$channel->appendChild($dom->createElement('link', $feedLink));
$channel->appendChild($dom->createElement('description', $feedDescription));
$channel->appendChild($dom->createElement('language', $feedLanguage));
$channel->appendChild($dom->createElement('lastBuildDate', date(DATE_RSS)));

$atomLink = $dom->createElement('atom:link');
$atomLink->setAttribute('href', $siteBaseUrl . 'assets/rss.xml');
$atomLink->setAttribute('rel', 'self');
$atomLink->setAttribute('type', 'application/rss+xml');
$channel->appendChild($atomLink);

$image = $dom->createElement('image');
$image->appendChild($dom->createElement('url', $feedImageUrl));
$image->appendChild($dom->createElement('title', $feedImageTitle));
$image->appendChild($dom->createElement('link', $feedImageLink));
$channel->appendChild($image);

foreach ($allEntries as $entry) {
    // Use the entry's published date, or fallback to file modification time
    $pubTimestamp = !empty($entry['published']) ? strtotime($entry['published']) : false;
    if ($pubTimestamp === false) {
        $pubTimestamp = filemtime($entry['sourceFilePath']);
    }
    $pubDate = date(DATE_RSS, $pubTimestamp);
    
    $item = $dom->createElement('item');
    $channel->appendChild($item);
    
    $item->appendChild($dom->createElement('title', htmlspecialchars($entry['title'])));
    $item->appendChild($dom->createElement('link', htmlspecialchars($entry['url'])));
    
    $contentEncoded = $dom->createElement('content:encoded');
    $contentEncoded->appendChild($dom->createCDATASection($entry['content']));
    $item->appendChild($contentEncoded);
    $item->appendChild($dom->createElement('pubDate', $pubDate));
    
    $guid = $dom->createElement('guid', htmlspecialchars($entry['url']));
    $guid->setAttribute('isPermaLink', 'true');
    $item->appendChild($guid);
}

$dom->formatOutput = true;
$rssOutput = $dom->saveXML();

echo $rssOutput;

?>