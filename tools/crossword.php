<?php

// Configuration
$outputGridSize = 21;
$targetImageSize = 1500;
$cellSize = floor($targetImageSize / $outputGridSize);
$fontSize = floor(27 * ($cellSize / 83));
$numberFontSize = floor(17 * ($cellSize / 83));

// Define output directory paths
$outputDir = $_SERVER['DOCUMENT_ROOT'] . '/tools/output';
$outputUrlBase = '/tools/output/'; // For web-accessible paths

// Global variables for results, initialized to null or empty
$errorMessage = null;
$emptyFilename = null; // This will store the web-accessible URL
$solutionFilename = null; // This will store the web-accessible URL
$acrossWordsList = []; // Changed from acrossClues
$downWordsList = [];   // Changed from downClues
$placedWordsCount = 0;
$wordsCount = 0; // Changed from wordsAndCluesCount
$actualFontPath = null;
$unplacedWords = [];
$generatedSuccessfully = false;

// Ensure output directory exists and has correct permissions
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true); 
}

// Delete old output files if the directory exists
if (is_dir($outputDir)) {
    $oldFiles = glob($outputDir . '/crossword_*.png');
    foreach ($oldFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}

// Find EB Garamond font
function findEBGaramondFont() {
    // Use the specific path requested
    $fontPath = $_SERVER['DOCUMENT_ROOT'] . '/assets/fonts/ebgaramond.ttf';
    
    if (file_exists($fontPath)) {
        return $fontPath;
    }
    
    // Fallback paths if the main one doesn't exist
    $fallbackPaths = [
        __DIR__ . '/assets/fonts/ebgaramond.ttf',
        '/assets/fonts/ebgaramond.ttf',
        $_SERVER['DOCUMENT_ROOT'] . '/assets/fonts/EBGaramond-Regular.ttf',
        __DIR__ . '/assets/fonts/EBGaramond-Regular.ttf',
    ];
    
    foreach ($fallbackPaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return null;
}

// Crossword generation class
class CrosswordGenerator {
    private $words = [];
    private $grid = [];
    private $placedWords = [];
    private $gridSize = 21; // Working grid size
    
    public function __construct($wordList) { // Changed parameter name
        // Parse and sort words by length (longest first)
        foreach ($wordList as $wordItem) { // Iterating over words directly
            $this->words[] = [
                'word' => strtoupper(trim($wordItem)), // Only word, no clue
                'placed' => false
            ];
        }
        usort($this->words, function($a, $b) {
            return strlen($b['word']) - strlen($a['word']);
        });
        
        // Initialize grid
        $this->grid = array_fill(0, $this->gridSize, array_fill(0, $this->gridSize, null));
    }
    
    public function generate() {
        // Place first word in center
        if (!empty($this->words)) {
            $firstWord = $this->words[0];
            $startRow = floor($this->gridSize / 2);
            $startCol = floor(($this->gridSize - strlen($firstWord['word'])) / 2);
            
            $this->placeWord($firstWord['word'], $startRow, $startCol, 'across', 0);
            $this->words[0]['placed'] = true;
            
            // Try to place remaining words
            $maxAttempts = 100;
            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $allPlaced = true;
                foreach ($this->words as $index => $word) {
                    if (!$word['placed']) {
                        $allPlaced = false;
                        if ($this->tryPlaceWord($index)) {
                            $this->words[$index]['placed'] = true;
                        }
                    }
                }
                if ($allPlaced) break;
            }
        }
        
        return $this->placedWords;
    }
    
    private function tryPlaceWord($wordIndex) {
        $word = $this->words[$wordIndex]['word'];
        
        // Try to intersect with each placed word
        foreach ($this->placedWords as $placedWord) {
            $intersections = $this->findIntersections($word, $placedWord['word']);
            
            foreach ($intersections as $intersection) {
                $newDir = ($placedWord['direction'] == 'across') ? 'down' : 'across';
                
                if ($placedWord['direction'] == 'across') {
                    $newRow = $placedWord['row'] - $intersection['new'];
                    $newCol = $placedWord['col'] + $intersection['placed'];
                } else {
                    $newRow = $placedWord['row'] + $intersection['placed'];
                    $newCol = $placedWord['col'] - $intersection['new'];
                }
                
                if ($this->canPlaceWord($word, $newRow, $newCol, $newDir)) {
                    $this->placeWord($word, $newRow, $newCol, $newDir, $wordIndex);
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function findIntersections($word1, $word2) {
        $intersections = [];
        for ($i = 0; $i < strlen($word1); $i++) {
            for ($j = 0; $j < strlen($word2); $j++) {
                if ($word1[$i] == $word2[$j]) {
                    $intersections[] = ['new' => $i, 'placed' => $j];
                }
            }
        }
        return $intersections;
    }
    
    private function canPlaceWord($word, $row, $col, $direction) {
        // Check bounds
        if ($row < 0 || $col < 0) return false;
        if ($direction == 'across' && $col + strlen($word) > $this->gridSize) return false;
        if ($direction == 'down' && $row + strlen($word) > $this->gridSize) return false;
        
        // Check for conflicts
        for ($i = 0; $i < strlen($word); $i++) {
            if ($direction == 'across') {
                $r = $row;
                $c = $col + $i;
            } else {
                $r = $row + $i;
                $c = $col;
            }
            
            // Check if cell is outside grid bounds
            if ($r >= $this->gridSize || $c >= $this->gridSize || $r < 0 || $c < 0) {
                return false;
            }

            if (isset($this->grid[$r][$c]) && $this->grid[$r][$c] !== null && $this->grid[$r][$c] !== $word[$i]) {
                return false;
            }
            
            // Check adjacent cells (no touching parallel words)
            if ($direction == 'across') {
                if ($r > 0 && isset($this->grid[$r-1][$c]) && $this->grid[$r-1][$c] !== null && $this->grid[$r][$c] === null) return false;
                if ($r < $this->gridSize-1 && isset($this->grid[$r+1][$c]) && $this->grid[$r+1][$c] !== null && $this->grid[$r][$c] === null) return false;
            } else {
                if ($c > 0 && isset($this->grid[$r][$c-1]) && $this->grid[$r][$c-1] !== null && $this->grid[$r][$c] === null) return false;
                if ($c < $this->gridSize-1 && isset($this->grid[$r][$c+1]) && $this->grid[$r][$c+1] !== null && $this->grid[$r][$c] === null) return false;
            }
        }
        
        // Check word boundaries
        if ($direction == 'across') {
            if ($col > 0 && isset($this->grid[$row][$col-1]) && $this->grid[$row][$col-1] !== null) return false;
            if ($col + strlen($word) < $this->gridSize && isset($this->grid[$row][$col+strlen($word)]) && $this->grid[$row][$col+strlen($word)] !== null) return false;
        } else {
            if ($row > 0 && isset($this->grid[$row-1][$col]) && $this->grid[$row-1][$col] !== null) return false;
            if ($row + strlen($word) < $this->gridSize && isset($this->grid[$row+strlen($word)][$col]) && $this->grid[$row+strlen($word)][$col] !== null) return false;
        }
        
        return true;
    }
    
    private function placeWord($word, $row, $col, $direction, $index) {
        for ($i = 0; $i < strlen($word); $i++) {
            if ($direction == 'across') {
                $this->grid[$row][$col + $i] = $word[$i];
            } else {
                $this->grid[$row + $i][$col] = $word[$i];
            }
        }
        
        $this->placedWords[] = [
            'word' => $word,
            'row' => $row,
            'col' => $col,
            'direction' => $direction
        ];
    }
    
    public function getGrid() {
        return $this->grid;
    }

    public function getWords() {
        // Return original words (just the strings) for unplaced word tracking
        return array_column($this->words, 'word');
    }
}

// Function to create crossword puzzle PNG
function createCrosswordPNG($placedWords, $grid, $showSolution = false) {
    global $cellSize, $fontSize, $outputGridSize, $numberFontSize, $actualFontPath;
    
    $actualFontPath = findEBGaramondFont();
    $useBuiltInFont = ($actualFontPath === null);
    
    $gridWidth = $outputGridSize;
    $gridHeight = $outputGridSize;
    
    // Create arrays for the output grid
    $outputGrid = array_fill(0, $gridHeight, array_fill(0, $gridWidth, ''));
    $isBlack = array_fill(0, $gridHeight, array_fill(0, $gridWidth, true)); // Initially all cells are "black" (empty/filler)
    
    // Copy the generated grid data and mark cells as "white" (part of a word)
    for ($r = 0; $r < $gridHeight; $r++) {
        for ($c = 0; $c < $gridWidth; $c++) {
            if (isset($grid[$r][$c]) && $grid[$r][$c] !== null) {
                $outputGrid[$r][$c] = $grid[$r][$c];
                $isBlack[$r][$c] = false; // Mark this cell as part of a word
            }
        }
    }
    
    // NEW LOGIC: Assign numbers by scanning the grid top-left to bottom-right
    $wordNumbersGrid = array_fill(0, $gridHeight, array_fill(0, $gridWidth, 0));
    $wordNumber = 1;
    
    for ($r = 0; $r < $gridHeight; $r++) {
        for ($c = 0; $c < $gridWidth; $c++) {
            if ($isBlack[$r][$c]) { // If it's a "black" square (not part of a word), skip
                continue;
            }

            $isStartAcross = false;
            $isStartDown = false;

            // Check if it's the start of an ACROSS word
            // Condition: has a letter to its right AND does not have a letter to its left
            // Ensure $c+1 is within bounds before checking $isBlack[$r][$c+1]
            if ($c < $gridWidth - 1 && !$isBlack[$r][$c+1]) { // Has a letter to its right
                if ($c == 0 || $isBlack[$r][$c-1]) { // At the start of the row OR has a black square to its left
                    $isStartAcross = true;
                }
            }

            // Check if it's the start of a DOWN word
            // Condition: has a letter below it AND does not have a letter above it
            // Ensure $r+1 is within bounds before checking $isBlack[$r+1][$c]
            if ($r < $gridHeight - 1 && !$isBlack[$r+1][$c]) { // Has a letter below it
                if ($r == 0 || $isBlack[$r-1][$c]) { // At the top of the column OR has a black square above it
                    $isStartDown = true;
                }
            }

            if ($isStartAcross || $isStartDown) {
                $wordNumbersGrid[$r][$c] = $wordNumber;
                $wordNumber++;
            }
        }
    }
    
    // Second pass: Associate assigned numbers with placed words
    // This array will be returned and used to generate the Across/Down lists
    $numberedPlacedWords = [];
    foreach ($placedWords as $word) {
        $row = $word['row'];
        $col = $word['col'];
        // Find the number assigned to this word's starting position
        if (isset($wordNumbersGrid[$row][$col]) && $wordNumbersGrid[$row][$col] > 0) {
            $word['number'] = $wordNumbersGrid[$row][$col];
            $numberedPlacedWords[] = $word;
        }
    }

    // Create image
    $imageWidth = $gridWidth * $cellSize;
    $imageHeight = $gridHeight * $cellSize;
    $image = imagecreatetruecolor($imageWidth, $imageHeight);
    
    // Set up transparency
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    
    // Colors
    $black = imagecolorallocate($image, 0, 0, 0);
    $white = imagecolorallocate($image, 255, 255, 255);
    $lightGray = imagecolorallocate($image, 220, 220, 220);
    $darkGray = imagecolorallocate($image, 150, 150, 150);
    
    // Draw grid
    for ($r = 0; $r < $gridHeight; $r++) {
        for ($c = 0; $c < $gridWidth; $c++) {
            $x = $c * $cellSize;
            $y = $r * $cellSize;
            
            if ($isBlack[$r][$c]) {
                // Fill with light gray instead of black
                imagefilledrectangle($image, $x, $y, $x + $cellSize - 1, $y + $cellSize - 1, $lightGray);
                // Draw border to show it's still a square
                imagerectangle($image, $x, $y, $x + $cellSize - 1, $y + $cellSize - 1, $darkGray);
            } else {
                // Fill white square
                imagefilledrectangle($image, $x, $y, $x + $cellSize - 1, $y + $cellSize - 1, $white);
                
                // Draw cell border
                imagerectangle($image, $x, $y, $x + $cellSize - 1, $y + $cellSize - 1, $black);
                
                // Draw number if present (using the new wordNumbersGrid)
                if ($wordNumbersGrid[$r][$c] > 0) {
                    if ($useBuiltInFont) {
                        imagestring($image, 2, $x + 4, $y + 4, $wordNumbersGrid[$r][$c], $black);
                    } else {
                        // Adjust Y coordinate for baseline of text with TTF
                        // For a number, a simple offset works well for top-left placement
                        imagettftext($image, $numberFontSize, 0, $x + 4, $y + $numberFontSize + 4, $black, $actualFontPath, $wordNumbersGrid[$r][$c]);
                    }
                }
                
                // Draw letter if solution
                if ($showSolution && $outputGrid[$r][$c] != '') {
                    if ($useBuiltInFont) {
                        $fontNum = 5;
                        $charWidth = imagefontwidth($fontNum);
                        $charHeight = imagefontheight($fontNum);
                        $textX = $x + ($cellSize - $charWidth) / 2;
                        $textY = $y + ($cellSize - $charHeight) / 2;
                        imagestring($image, $fontNum, $textX, $textY, $outputGrid[$r][$c], $black);
                    } else {
                        $bbox = imagettfbbox($fontSize, 0, $actualFontPath, $outputGrid[$r][$c]);
                        $textWidth = $bbox[2] - $bbox[0];
                        $textHeight = $bbox[1] - $bbox[7]; // Height from lowest point to highest point
                        // Adjust textX to center horizontally, textY for baseline approximately centered vertically
                        $textX = $x + ($cellSize - $textWidth) / 2;
                        $textY = $y + ($cellSize + $textHeight) / 2; // This tries to center vertically based on font bbox
                        imagettftext($image, $fontSize, 0, $textX, $textY, $black, $actualFontPath, $outputGrid[$r][$c]);
                    }
                }
            }
        }
    }
    
    // Return both the image and the numbered words
    return ['image' => $image, 'numberedWords' => $numberedPlacedWords];
}

// Process form submission

$captcha_error = '';
$expected_captcha_phrase = "Crossword Maker";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_puzzle'])) {

    $user_captcha = trim($_POST['captcha'] ?? '');
    if (empty($user_captcha)) {
        $captcha_error = "Please type the page's title.";
    } elseif (strcasecmp($user_captcha, $expected_captcha_phrase) !== 0) {
        $captcha_error = "Incorrect page title. Please try again.";
    } else {

    // Parse words (one per line)
    $wordList = [];
    $lines = explode("\n", trim($_POST['words_data']));
    foreach ($lines as $line) {
        $word = trim($line);
        if (!empty($word)) {
            $wordList[] = $word;
        }
    }

    $wordsCount = count($wordList);
    
    if ($wordsCount < 2) {
        $errorMessage = 'Please enter at least 2 words to create a puzzle.';
    } else {

        // Generate crossword
        $generator = new CrosswordGenerator($wordList);
        $placedWords = $generator->generate();
        $grid = $generator->getGrid();
        $initialWords = $generator->getWords();

        $placedWordsCount = count($placedWords);
        
        if ($placedWordsCount < 2) {
            $errorMessage = 'Could not generate a valid crossword with the given words. Try different words or add more words that share common letters.';

        } else {
            // Define base filenames and full server paths for saving
            $emptyFileBaseName = 'crossword_empty_' . time() . '.png';
            $solutionFileBaseName = 'crossword_solution_' . time() . '.png';

            $fullEmptyPath = $outputDir . '/' . $emptyFileBaseName;
            $fullSolutionPath = $outputDir . '/' . $solutionFileBaseName;

            // Store web-accessible URLs for display in HTML
            $emptyFilename = $outputUrlBase . $emptyFileBaseName; 
            $solutionFilename = $outputUrlBase . $solutionFileBaseName;
            
            // Create empty puzzle
            $emptyResult = createCrosswordPNG($placedWords, $grid, false);
            $emptyPuzzle = $emptyResult['image'];
            $numberedWords = $emptyResult['numberedWords']; // This now contains correctly numbered words
            imagepng($emptyPuzzle, $fullEmptyPath);
            imagedestroy($emptyPuzzle);
            
            // Create solution (using the same numbering data)
            $solutionResult = createCrosswordPNG($placedWords, $grid, true); // Pass original placedWords, numbering is done inside again
            $solutionPuzzle = $solutionResult['image'];
            imagepng($solutionPuzzle, $fullSolutionPath);
            imagedestroy($solutionPuzzle);
            
            // Generate lists of numbered words for display
            foreach ($numberedWords as $word) { // Use the returned numberedWords
                if ($word['direction'] == 'across') {
                    $acrossWordsList[$word['number']] = [
                        'number' => $word['number'],
                        'word' => $word['word'],
                        'row' => $word['row'], // Add row
                        'col' => $word['col']  // Add col
                    ];
                } else {
                    $downWordsList[$word['number']] = [
                        'number' => $word['number'],
                        'word' => $word['word'],
                        'row' => $word['row'], // Add row
                        'col' => $word['col']  // Add col
                    ];
                }
            }
            
            ksort($acrossWordsList);
            ksort($downWordsList);

            // Determine unplaced words
            $placedWordStrings = array_map(function($w) { return strtoupper($w['word']); }, $placedWords);
            foreach ($initialWords as $initialWord) {
                if (!in_array($initialWord, $placedWordStrings)) {
                    $unplacedWords[] = $initialWord;
                }
            }

            $generatedSuccessfully = true;
        }
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
        <title>Crossword Maker | Lunaseeker Press</title>
        <link rel="canonical" href="https://lunaseeker.com/crosswords">
        <meta name="date" content="2025-07-15">
        <meta name="last-modified" content="2025-07-15">
        <meta name="description" content="Input your words to create a crossword puzzle and its solution!">
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
                    <li><a href="/events/">Events</a></li>
                    <li><a href="/newsletter/">Newsletter</a></li>
                    <li><a href="/press">Press</a></li>
                    <li><a href="/tools/">Tools</a></li><li>
                </ul>
                <!-- Pages -->
                <p>Pages</p>
                <ul>
                    <li><a href="/about">About</a></li>
                    <li><a href="/catalog/">Catalog</a></li>
                    <li><a href="/colophon">Colophon</a></li>
                    <li><a href="/cv">CV</a></li>
                    <li><a href="/press">Press</a></li>
                </ul>
            </nav>

            <!-- Main Content Area -->
            <article id="main">

                <!-- Page Header -->
                <header>
                    <p class="smalltext">You Are Here ➸ <a href="https://lunaseeker.com">Homepage</a> • Tools ↴</p>
                    <h1>Crossword Maker</h1>
                    <p class="smalltext">
                        <strong>Written By</strong>: <a href="/about">Zachary Kai</a> »
                        <strong>Published</strong>: <time class="dt-published" datetime="2025-07-15">15 Jul 2025</time> | 
                        <strong>Updated</strong>: <time class="dt-modified" datetime="2025-07-15">15 Jul 2025</time>
                    </p>
                </header>
                
                <!-- Page Body -->
                <p>Input your words to create a crossword puzzle and its solution!</p>

                <!-- Introduction --->
                <p><strong>Instructions:</strong> Enter one word per line. The generator will place words that share common letters in a 21×21 grid.</p>

                <!-- Generation Form -->
                <form method="post" class="puzzle-form">
                    <label for="words_data">Words:</label>
                    <br>
                    <textarea id="words_data" name="words_data" required><?php echo isset($_POST['words_data']) ? htmlspecialchars($_POST['words_data']) : ''; ?></textarea>
                    <br>
                    <label for="captcha">Type in this page's title: what the H1 at the top says...</label>
                    <br>
                    <input type="text" id="captcha" name="captcha" required>
                    <br>
                    <button type="submit" name="create_puzzle">Generate Puzzle</button>
                </form>

                <!-- Error Messages -->
                <?php if ($errorMessage): ?>
                    <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>
                
                <!-- Puzzle Generation Results -->
                <?php if ($generatedSuccessfully): ?>

                    <div class="puzzle-preview">
                        <h2>Puzzle Created Successfully!</h2>
                        <p><strong>Words placed:</strong> <?php echo $placedWordsCount; ?> out of <?php echo $wordsCount; ?></p>
                        
                        <!-- Puzzle -->
                        <h3>Empty Puzzle</h3>
                        <img src="<?php echo htmlspecialchars($emptyFilename); ?>" alt="Empty crossword puzzle.">
                                   
                        <!-- Solution -->
                        <details>
                            <summary><strong>Click here to reveal the solution...</strong></summary>
                            <h3>Solution</h3>
                            <img src="<?php echo htmlspecialchars($solutionFilename); ?>" alt="Crossword puzzle solution.">

                            <div class="clues-section">
                                <h4>Words</h4>
                                <div class="clues-columns">
                                    <div class="clues-column">
                                        <p>Across</p>
                                        <ol class="clues-list">
                                            <?php foreach ($acrossWordsList as $num => $item): ?>
                                            <li data-number="<?php echo $num; ?>">
                                                <?php echo $num; ?>. <strong><?php echo htmlspecialchars($item['word']); ?></strong> (Row <?php echo $item['row'] + 1; ?>, Col <?php echo $item['col'] + 1; ?>)
                                            </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </div>
                                    <div class="clues-column">
                                        <p>Down</p>
                                        <ol class="clues-list">
                                            <?php foreach ($downWordsList as $num => $item): ?>
                                            <li data-number="<?php echo $num; ?>">
                                                <?php echo $num; ?>. <strong><?php echo htmlspecialchars($item['word']); ?></strong> (Row <?php echo $item['row'] + 1; ?>, Col <?php echo $item['col'] + 1; ?>)
                                            </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </details>

                        <?php if (!empty($unplacedWords)): ?>
                        <div class="clues-section">
                            <h4>Words that couldn't be placed:</h4>
                            <ul>
                                <?php foreach ($unplacedWords as $word): ?>
                                    <li><strong><?php echo htmlspecialchars($word); ?></strong></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
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