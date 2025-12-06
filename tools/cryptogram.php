<?php
// === PHP LOGIC FOR CRYPTOGRAM GENERATION ===

$puzzleImagePath = null;
$solutionImagePath = null;
$captcha_error = '';
$expected_captcha_phrase = "Cryptogram Maker";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sentence']) && !empty(trim($_POST['sentence']))) {

    $user_captcha = trim($_POST['captcha'] ?? '');
    if (empty($user_captcha)) {
        $captcha_error = "Please type the page's title.";
    } elseif (strcasecmp($user_captcha, $expected_captcha_phrase) !== 0) {
        $captcha_error = "Incorrect page title. Please try again.";
    } else {

    // --- 1. SETUP & CONFIGURATION ---
    $fontFile = $_SERVER['DOCUMENT_ROOT'] . '/assets/fonts/ebgaramond.ttf';
    $tempDir = $_SERVER['DOCUMENT_ROOT'] . '/tools/output/';
    $tempUrlDir = '/tools/output/';
    
    if (!is_dir($tempDir)) { @mkdir($tempDir, 0755, true); }
    if (!file_exists($fontFile)) { die("Error: Font file not found at: " . htmlspecialchars($fontFile)); }

    $uniqueId = uniqid();
    $puzzleImagePath = $tempUrlDir . 'puzzle_' . $uniqueId . '.png';
    $solutionImagePath = $tempUrlDir . 'solution_' . $uniqueId . '.png';
    $fullPuzzlePath = $tempDir . 'puzzle_' . $uniqueId . '.png';
    $fullSolutionPath = $tempDir . 'solution_' . $uniqueId . '.png';

    // Layout metrics
    $imageWidth = 1500;
    $imageHeight = 1800;
    $margin = 50;
    $numberFontSize = 60;
    $solutionLetterFontSize = 55;
    $itemWidth = 100;
    $boxHeight = 90;
    $itemSpacing = 5;
    $wordSpacing = 40;
    $itemHeight = ($boxHeight * 2) + $itemSpacing;
    $lineHeight = $itemHeight + 40;

    // --- 2. CRYPTOGRAM LOGIC ---
    $originalSentence = strtoupper(trim($_POST['sentence']));
    $alphabet = range('A', 'Z');
    $numbers = range(1, 26);
    shuffle($numbers);
    $cipherMap = array_combine($alphabet, $numbers);

    $words = preg_split('/\s+/', $originalSentence, -1, PREG_SPLIT_NO_EMPTY);
    $structuredWords = [];
    foreach ($words as $word) {
        $wordData = ['letters' => []];
        for ($i = 0; $i < strlen($word); $i++) {
            $char = $word[$i];
            $isLetter = ctype_alpha($char);
            $wordData['letters'][] = [
                'original' => $char,
                'cipher' => $isLetter ? $cipherMap[$char] : $char,
                'is_letter' => $isLetter,
            ];
        }
        $wordData['width'] = count($wordData['letters']) * $itemWidth;
        $structuredWords[] = $wordData;
    }
    
    // Flatten array of letters and filter for only actual letters to pick a hint from
    $allLetters = [];
    foreach ($structuredWords as $word) {
        foreach ($word['letters'] as $item) {
            if ($item['is_letter']) {
                $allLetters[] = $item;
            }
        }
    }
    $hint = !empty($allLetters) ? $allLetters[array_rand($allLetters)] : null;
    
    // --- 3. LAYOUT & IMAGE GENERATION ---

    // CORRECTED calculateLayout FUNCTION
    function calculateLayout($structuredWords, $maxWidth, $wordSpacing) {
        $lines = [];
        $currentLine = [];
        $currentLineActualWidth = 0; // Tracks the width of words + inter-word spacing on the current line

        foreach ($structuredWords as $word) {
            $wordWidth = $word['width'];
            $spacingForThisWord = !empty($currentLine) ? $wordSpacing : 0;

            // Check if adding the current word (and its preceding spacing) would exceed maxWidth
            if ($currentLineActualWidth + $spacingForThisWord + $wordWidth > $maxWidth && !empty($currentLine)) {
                // Current word doesn't fit, finalize current line
                $lines[] = ['items' => $currentLine, 'width' => $currentLineActualWidth];

                // Start a new line with the current word
                $currentLine = [$word];
                $currentLineActualWidth = $wordWidth; // No leading spacing for the first word on a new line
            } else {
                // Word fits, add to current line
                if (!empty($currentLine)) {
                    $currentLineActualWidth += $spacingForThisWord;
                }
                $currentLine[] = $word;
                $currentLineActualWidth += $wordWidth;
            }
        }
        // Add any remaining words to the lines array
        if (!empty($currentLine)) {
            $lines[] = ['items' => $currentLine, 'width' => $currentLineActualWidth];
        }
        return $lines;
    }
    
    $layoutLines = calculateLayout($structuredWords, $imageWidth - (2 * $margin), $wordSpacing);

function drawKey($image, $isSolution, $config) {
    extract($config);
    $black = imagecolorallocate($image, 0, 0, 0);

    // --- Controls for the Dynamic Layout ---
    $keyFontSize = 37;
    $keyPadding = 20;
    $keyBoxWidth = 65;
    $keyBoxHeight = 45;

    $keyRows = [range('A', 'I'), range('J', 'R'), range('S', 'Z')];
    $lineSpacing = 70;
    $y = $margin;

    // --- Dynamic Layout Calculation (First Pass) ---
    $allRowsLayout = [];
    foreach ($keyRows as $row) {
        $currentRowWidth = 0;
        foreach ($row as $letter) {
            $labelText = $letter . " = ";
            $labelBox = imagettfbbox($keyFontSize, 0, $fontFile, $labelText);
            $labelWidth = $labelBox[2] - $labelBox[0]; // width
            $currentRowWidth += $labelWidth + $keyBoxWidth;
        }
        $currentRowWidth += ($keyPadding * (count($row) - 1));
        $allRowsLayout[] = $currentRowWidth;
    }

    // --- Drawing the Key (Second Pass) ---
    foreach ($keyRows as $rowIndex => $row) {
        $rowWidth = $allRowsLayout[$rowIndex];
        $x = ($imageWidth - $rowWidth) / 2; // Center the row

        foreach ($row as $letter) {
            $isHint = ($hint && $letter === $hint['original']);
            $labelText = $letter . " = ";
            $labelBox = imagettfbbox($keyFontSize, 0, $fontFile, $labelText);
            $labelWidth = $labelBox[2] - $labelBox[0];
            $labelHeight = $labelBox[1] - $labelBox[7]; // Height from imagettfbbox

            // Calculate the Y position for the label text
            // It should be vertically centered with the key box
            $boxY = $y; // The top Y coordinate of the key box
            $labelY = $boxY + (($keyBoxHeight - $labelHeight) / 2) - $labelBox[7];
            
            imagettftext($image, $keyFontSize, 0, $x, $labelY, $black, $fontFile, $labelText);

            $boxX = $x + $labelWidth;

            imagesetthickness($image, 2);
            imagerectangle($image, $boxX, $boxY, $boxX + $keyBoxWidth, $boxY + $keyBoxHeight, $black);
            
            if ($isSolution || ($isHint && !$isSolution)) { // If it's the solution image OR if it's the puzzle image and this is the hint
                $textColor = $black;
                $content = ''; // Initialize content

                // Determine content based on whether it's solution or a hint on the puzzle
                if ($isSolution) {
                    $content = (string)$cipherMap[$letter]; // Show number on solution
                } elseif (!$isSolution && $isHint) {
                    $content = (string)$hint['cipher']; // Show hint number on puzzle
                }

                if (!empty($content)) {
                    $contentBox = imagettfbbox($keyFontSize, 0, $fontFile, $content);
                    $contentWidth = $contentBox[2] - $contentBox[0];
                    $contentHeight = $contentBox[1] - $contentBox[7];
                    
                    // Corrected vertical centering for imagettftext within the key box
                    $contentY = $boxY + (($keyBoxHeight - $contentHeight) / 2) - $contentBox[7];
                    $contentX = $boxX + ($keyBoxWidth - $contentWidth) / 2;
                    
                    imagettftext($image, $keyFontSize, 0, $contentX, $contentY, $textColor, $fontFile, $content);
                }
            }
            
            $x += $labelWidth + $keyBoxWidth + $keyPadding;
        }
        $y += $lineSpacing;
    }
    
    return $y + $margin; // Return the current Y position as the key's total height
}

    function drawCryptogramImage($filePath, $isSolution, $config) {
        extract($config);
        // Create a true color image for full alpha channel support
        $image = imagecreatetruecolor($imageWidth, $imageHeight);
        
        // Disable alpha blending and enable saving of alpha channel
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $black = imagecolorallocate($image, 0, 0, 0);
        // Allocate a fully transparent color
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        // Fill the image with the transparent color
        imagefill($image, 0, 0, $transparent);

        $keyHeight = drawKey($image, $isSolution, $config);

        $puzzleBlockHeight = count($layoutLines) * $lineHeight;
        $y = $keyHeight + (($imageHeight - $keyHeight - $puzzleBlockHeight) / 2); // Start Y for puzzle block

        // Make sure text appears centered on the line, using font's natural height
        $lineTextCenterOffset = 0; // Default, will calculate per box
        
        foreach ($layoutLines as $line) {
            // CORRECTED: Use the 'width' property pre-calculated in calculateLayout
            $x = ($imageWidth - $line['width']) / 2;
            
            foreach ($line['items'] as $word) {
                foreach ($word['letters'] as $item) {
                    $isHint = ($hint && $item['original'] === $hint['original']);
                    
                    if ($item['is_letter']) {
                        $topBoxY = $y;
                        $bottomBoxY = $y + $boxHeight + $itemSpacing;
                        imagesetthickness($image, 3);
                        imagerectangle($image, $x, $topBoxY, $x + $itemWidth, $topBoxY + $boxHeight, $black);
                        
                        $cipherText = (string) $item['cipher'];
                        $cipherBox = imagettfbbox($numberFontSize, 0, $fontFile, $cipherText);
                        $cipherWidth = $cipherBox[2] - $cipherBox[0];
                        $cipherHeight = $cipherBox[1] - $cipherBox[7];
                        $cipherY = $topBoxY + (($boxHeight - $cipherHeight) / 2) - $cipherBox[7];
                        $cipherX = $x + ($itemWidth - $cipherWidth) / 2;
                        imagettftext($image, $numberFontSize, 0, $cipherX, $cipherY, $black, $fontFile, $cipherText);

                        imagerectangle($image, $x, $bottomBoxY, $x + $itemWidth, $bottomBoxY + $boxHeight, $black);
                        
                        if ($isSolution || ($isHint && !$isSolution)) {
                            $solutionChar = $item['original'];
                            $solutionBox = imagettfbbox($solutionLetterFontSize, 0, $fontFile, $solutionChar);
                            $solutionWidth = $solutionBox[2] - $solutionBox[0];
                            $solutionHeight = $solutionBox[1] - $solutionBox[7];
                            $solutionY = $bottomBoxY + (($boxHeight - $solutionHeight) / 2) - $solutionBox[7];
                            $solutionX = $x + ($itemWidth - $solutionWidth) / 2;
                            imagettftext($image, $solutionLetterFontSize, 0, $solutionX, $solutionY, $black, $fontFile, $solutionChar);
                        }
                    } else {
                        $puncBox = imagettfbbox($numberFontSize, 0, $fontFile, $item['original']);
                        $puncWidth = $puncBox[2] - $puncBox[0];
                        $puncHeight = $puncBox[1] - $puncBox[7];
                        $puncY = $y + (($itemHeight - $puncHeight) / 2) - $puncBox[7];
                        $puncX = $x + ($itemWidth - $puncWidth) / 2;
                        imagettftext($image, $numberFontSize, 0, $puncX, $puncY, $black, $fontFile, $item['original']);
                    }
                    $x += $itemWidth;
                }
                $x += $wordSpacing;
            }
            $y += $lineHeight;
        }

        imagepng($image, $filePath);
        imagedestroy($image);
    }
    
    $config = compact(
        'imageWidth', 'imageHeight', 'fontFile', 'layoutLines', 'margin', 'cipherMap', 'hint',
        'numberFontSize', 'solutionLetterFontSize', 'itemWidth', 'boxHeight', 'itemSpacing', 'itemHeight', 'lineHeight', 'wordSpacing'
    );
    
    drawCryptogramImage($fullPuzzlePath, false, $config);
    drawCryptogramImage($fullSolutionPath, true, $config);

    if ($handle = opendir($tempDir)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") { // Exclude . and ..
                if (strpos($file, 'puzzle_') === 0 || strpos($file, 'solution_') === 0) {
                    // Check if the file is older than 10 minutes (600 seconds)
                    if (filemtime($tempDir . $file) < time() - 600) { @unlink($tempDir . $file); }
                }
            }
        }
        closedir($handle);
    }
}

}
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
        <link rel="alternate" type="application/rss+xml" title="Lunaseeker Press" href="/assets/rss.xml">
        <link rel="webmention" href="https://webmention.io/lunaseeker.com/webmention"/>
        <!-- Page Details -->
        <title>Cryptogram Maker | Lunaseeker Press</title>
        <link rel="canonical" href="https://lunaseeker.com/tools/cryptogram">
        <meta name="date" content="2025-07-15">
        <meta name="last-modified" content="2025-09-05">
        <meta name="description" content="Input a sentence and click the button to create a cryptogram and its solution!">
    </head>

    <body>

        <main>

            <!-- Header + Sidebar Navigation -->
            <nav>
                <!-- Home -->
                <ul>
                    <li><a href="https://lunaseeker.com" class="site-name">Lunaseeker Press</a>
                </ul>
                <!-- Collections -->
                <p>Collections</p>
                <ul>
                    <li><a href="/catalog/">Catalog</a></li>
                    <li><a href="/events/">Events</a></li>
                    <li><a href="/newsletter/">Newsletter</a></li>
                    <li><a href="/notes/">Notes</a></li>
                    <li><a href="/press">Press</a></li>
                    <li><a href="/tools/">Tools</a></li><li>
                </ul>
                <!-- Pages -->
                <p>Pages</p>
                <ul>
                    <li><a href="/about">About</a></li>
                    <li><a href="/colophon">Colophon</a></li>
                    <li><a href="/cv">CV</a></li>
                    <li><a href="/press">Press</a></li>
                    <li><a href="/sitemap">Sitemap</a></li>
                </ul>
            </nav>
            
            <!-- Main Content Area -->
            <article id="main">

                <!-- Page Header -->
                <header>
                    <p class="smalltext">You Are Here ➸ <a href="https://lunaseeker.com">Homepage</a> • Tools ↴</p>
                    <h1>Cryptogram Maker</h1>
                    <p class="smalltext">
                        <strong>Written By</strong>: <a href="/about">Zachary Kai</a> »
                        <strong>Published</strong>: <time class="dt-published" datetime="2025-07-15">15 Jul 2025</time> | 
                        <strong>Updated</strong>: <time class="dt-modified" datetime="2025-09-05">5 Sep 2025</time>
                    </p>
                </header>
                
                <!-- Page Body -->
                <section class="e-content">

                </section>
                <p id="top" class="p-summary">Input a sentence and click the button to create a cryptogram and its solution!</p>
                <form action="/tools/cryptogram" method="post">
                    <label for="sentence">Enter your sentence:</label>
                    <br>
                    <textarea id="sentence" name="sentence" required><?php echo isset($_POST['sentence']) ? htmlspecialchars($_POST['sentence']) : ''; ?></textarea>
                    <br>
                    <label for="captcha">Type in this page's title: what the H1 at the top says...</label>
                    <br>
                    <input type="text" id="captcha" name="captcha" required>
                    <br>
                    <button type="submit">Create Cryptogram</button>
                </form>
                
                <?php if ($puzzleImagePath && $solutionImagePath): ?>
                    <section class="cryptogram-output">
                        <h2>Your Cryptogram</h2>
                        <p>Right-click or long-press the images to save and share them!</p>
                        <h3>Puzzle</h3>
                        <img src="<?php echo htmlspecialchars($puzzleImagePath); ?>" alt="Cryptogram Puzzle">
                        <details>
                            <summary><strong>Click to view the solution...</strong></summary>
                            <img src="<?php echo htmlspecialchars($solutionImagePath); ?>" alt="Cryptogram Solution">
                        </details>
                    </section>
                <?php endif; ?>
                
                <p>•--♡--•</p>
                
                <!-- Footer -->
                <footer>
                    <hr>
                    <section class="acknowledgement">
                        <h2>Acknowledgement Of Country</h2>
                        <p>I owe my existence to the <a href="https://kht.org.au/" rel="noopener">Koori people's</a> lands: tended for millennia by the traditional owners and storytellers. What a legacy. May it prevail.</p>
                    </section>
                    <p class="smalltext">Est. 2019 | Have a wonderful <a href="https://indieweb.org/Universal_Greeting_Time" rel="noopener">morning</a>, wherever you are.</p>
                </footer>

            </article>
        </main>
    </body>
</html>