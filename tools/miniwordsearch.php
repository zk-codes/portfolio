<?php

// --- WORD SEARCH GENERATOR - BACKEND LOGIC ---

// Start a session to store puzzle data for download links.
session_start();

// --- CONFIGURATION ---

define('GRID_SIZE', 14);
define('IMAGE_SIZE', 1500);
define('FONT_PATH', realpath(__DIR__ . '/../assets/fonts/ebgaramond.ttf')); 

// --- GLOBAL VARIABLES ---

$errors = [];
$placed_words = [];
$unplaced_words = [];
$generated_content = [];

// --- DOWNLOAD HANDLER ---

if (isset($_GET['download']) && isset($_SESSION['wordsearch_data'])) {
    $data = $_SESSION['wordsearch_data'];
    $filename = "miniwordsearch.png";
    $solution_data = [];

    if ($_GET['download'] === 'solution') {
        $solution_data = $data['placed_words'];
        $filename = "miniwordsearch.png";
    }
    
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    generate_image($data['grid'], $solution_data, 'stream');
    exit;
}

// --- FORM HANDLING (POST REQUEST) ---

$errorMessage = '';
$expectedTitle = 'Mini Word Search Maker'; // Keep this as it is already correct

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['page_title']) && $_POST['page_title'] === $expectedTitle) {

        // 1. PARSE AND VALIDATE WORDS

        $raw_words = $_POST['words'];
        if (empty($raw_words)) {
            $errors[] = "No words were entered. Please provide a list of words.";
        } else {
            $words = preg_split('/[\s,]+/', $raw_words);
            $words = array_filter(array_map(function($word) {
                // Modified: Allow letters (a-z, A-Z) instead of only numbers.
                $clean_word = preg_replace("/[^a-zA-Z]/", "", $word); 
                // Convert to uppercase for consistency in the grid
                $clean_word = strtoupper($clean_word);
                if (strlen($clean_word) > GRID_SIZE) {
                    global $errors;
                    $errors[] = "The word '{$clean_word}' is too long to fit in the " . GRID_SIZE . "x" . GRID_SIZE . " grid.";
                }
                return $clean_word;
            }, $words));

            if (empty($words)) {
                $errors[] = "The input contained no valid words. Please use letters only.";
            }
        }

        // 2. GENERATION LOGIC

        if (empty($errors)) {
            $grid = create_empty_grid();
            list($grid, $placed_words, $unplaced_words) = place_words_on_grid($grid, $words);
            $grid = fill_empty_cells($grid);

            $_SESSION['wordsearch_data'] = [
                'grid' => $grid,
                'placed_words' => $placed_words,
            ];

            $generated_content = [
                'puzzle_uri' => generate_image($grid),
                'solution_uri' => generate_image($grid, $placed_words),
                'word_list' => array_keys($placed_words)
            ];
        }
    
    } else {
        $errorMessage = "Please enter the correct page title to generate the Mini Word Search.";
    }
}

// --- CORE FUNCTIONS ---

function create_empty_grid() {
    return array_fill(0, GRID_SIZE, array_fill(0, GRID_SIZE, null));
}

function place_words_on_grid($grid, $words) {
    $directions = [[0, 1], [0, -1], [1, 0], [-1, 0], [1, 1], [-1, -1], [1, -1], [-1, 1]];
    $placed = [];
    $unplaced = [];
    usort($words, function($a, $b) { return strlen($b) - strlen($a); });

    foreach ($words as $word) {
        $word_len = strlen($word);
        $is_placed = false;
        $attempts = 0;
        while (!$is_placed && $attempts < 100) {
            $direction = $directions[array_rand($directions)];
            $start_row = rand(0, GRID_SIZE - 1);
            $start_col = rand(0, GRID_SIZE - 1);
            $end_row = $start_row + ($word_len - 1) * $direction[0];
            $end_col = $start_col + ($word_len - 1) * $direction[1];

            if ($end_row >= 0 && $end_row < GRID_SIZE && $end_col >= 0 && $end_col < GRID_SIZE) {
                $can_place = true;
                for ($i = 0; $i < $word_len; $i++) {
                    $row = $start_row + $i * $direction[0];
                    $col = $start_col + $i * $direction[1];
                    if ($grid[$row][$col] !== null && $grid[$row][$col] !== $word[$i]) {
                        $can_place = false;
                        break;
                    }
                }
                if ($can_place) {
                    for ($i = 0; $i < $word_len; $i++) {
                        $row = $start_row + $i * $direction[0];
                        $col = $start_col + $i * $direction[1];
                        $grid[$row][$col] = $word[$i];
                    }
                    $placed[$word] = ['start_row' => $start_row, 'start_col' => $start_col, 'end_row' => $end_row, 'end_col' => $end_col];
                    $is_placed = true;
                }
            }
            $attempts++;
        }
        if (!$is_placed) {
            $unplaced[] = $word;
        }
    }
    return [$grid, $placed, $unplaced];
}

function fill_empty_cells($grid) {
    // Modified: Fill with random uppercase letters (A-Z)
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    for ($r = 0; $r < GRID_SIZE; $r++) {
        for ($c = 0; $c < GRID_SIZE; $c++) {
            if ($grid[$r][$c] === null) {
                $grid[$r][$c] = $alphabet[rand(0, strlen($alphabet) - 1)];
            }
        }
    }
    return $grid;
}

function generate_image($grid, $solution_words = [], $output_mode = 'base64') {

    if (!FONT_PATH || !file_exists(FONT_PATH)) {

        $im = imagecreatetruecolor(IMAGE_SIZE, 300);
        $bg = imagecolorallocate($im, 255, 255, 255);
        $red = imagecolorallocate($im, 220, 0, 0);
        imagefill($im, 0, 0, $bg);
        imagestring($im, 5, 20, 20, "FATAL ERROR: Font file not found!", $red);
        imagestring($im, 5, 20, 40, "Check the FONT_PATH in the PHP script.", $red);
        imagestring($im, 5, 20, 60, "Expected resolved path: " . (FONT_PATH ?: "Not resolved"), $red);

    } else {

        $im = imagecreatetruecolor(IMAGE_SIZE, IMAGE_SIZE);
        $bg_color = imagecolorallocate($im, 255, 255, 255);
        $text_color = imagecolorallocate($im, 18, 18, 18);
        $solution_highlight_color = imagecolorallocatealpha($im, 173, 216, 230, 65);
        
        imagefill($im, 0, 0, $bg_color);
        $margin = 60;
        $cell_size = (IMAGE_SIZE - (2 * $margin)) / GRID_SIZE;
        $font_size = $cell_size * 0.6; // Keep font size proportional to cell
        $test_bbox = imagettfbbox($font_size, 0, FONT_PATH, 'X'); 
        $font_ascent = abs($test_bbox[7]); // The absolute value of upper-left Y
        $font_descent = abs($test_bbox[1]); // The absolute value of lower-left Y
        $font_total_height = $font_ascent + $font_descent;

        for ($r = 0; $r < GRID_SIZE; $r++) {
            for ($c = 0; $c < GRID_SIZE; $c++) {
                $letter = $grid[$r][$c]; // Variable name 'letter' is now appropriate
                
                // Calculate the center of the cell
                $center_x = $c * $cell_size + $margin + ($cell_size / 2);
                $cell_top_y = $r * $cell_size + $margin; // Top edge of the current cell

                // Get bounding box for the specific letter (still needed for individual width)
                $bbox = imagettfbbox($font_size, 0, FONT_PATH, $letter);
                
                // Calculate text width for horizontal centering
                $text_width = $bbox[2] - $bbox[0]; // x2 - x1
                
                // Final X position: center_x minus half the text width
                $final_x = $center_x - ($text_width / 2);
                $final_y = $cell_top_y + ($cell_size / 2) + ($bbox[1] + $bbox[7]) / 2;
                $baseline_offset_from_cell_top = ($cell_size / 2) + ($font_ascent / 2) - ($font_descent / 2);
                $final_y = $cell_top_y + $baseline_offset_from_cell_top;

                imagettftext($im, $font_size, 0, $final_x, $final_y, $text_color, FONT_PATH, $letter);
            }
        }
        if (!empty($solution_words)) {
            imagesetthickness($im, $cell_size * 0.7);
            foreach ($solution_words as $word => $pos) {
                $start_x = $pos['start_col'] * $cell_size + $margin + ($cell_size / 2);
                $start_y = $pos['start_row'] * $cell_size + $margin + ($cell_size / 2);
                $end_x = $pos['end_col'] * $cell_size + $margin + ($cell_size / 2);
                $end_y = $pos['end_row'] * $cell_size + $margin + ($cell_size / 2);
                imageline($im, $start_x, $start_y, $end_x, $end_y, $solution_highlight_color);
            }
        }
    }

    if ($output_mode === 'stream') {
        imagepng($im);
    } else {
        ob_start();
        imagepng($im);
        $image_data = ob_get_contents();
        ob_end_clean();
        imagedestroy($im);
        return 'data:image/png;base64,' . base64_encode($image_data);
    }
    imagedestroy($im);
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
        <title>Mini Word Search Maker | Lunaseeker Press</title>
        <link rel="canonical" href="https://lunaseeker.com/tools/miniwordsearch">
        <meta name="date" content="2025-07-13">
        <meta name="last-modified" content="2025-07-13">
        <meta name="description" content="Input a list of words and click the button to create a mini word search and its solution!">
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
                    <p class="smalltext">You Are Here ➸ <a href="https://lunaseeker.com">Homepage</a> ➸ Tools</p>
                    <h1>Mini Word Search Maker</h1>
                    <p class="smalltext">
                        <strong>Written By</strong>: <a href="/about">Zachary Kai</a> »
                        <strong>Published</strong>: <time class="dt-published" datetime="2025-07-15">15 Jul 2025</time> | 
                        <strong>Updated</strong>: <time class="dt-modified" datetime="2025-07-15">15 Jul 2025</time>
                    </p>
                </header>
                
                <!-- Page Body -->
                <p id="top" class="p-summary">Input a list of words and click the button to create a mini word search and its solution!</p>
                
                <section>
                    <h2>Create Your Mini Word Search</h2>
                    <form action="/tools/miniwordsearch" method="post" class="word-search-form">
                        <label for="page_title">Enter Page Title:</label>
                        <br>
                        <input type="text" id="page_title" name="page_title" size="50" required>
                        <br>
                        <label for="words">Enter your words (separated by new lines or commas):</label><br>
                        <textarea id="words" name="words" required><?php echo isset($_POST['words']); ?></textarea>
                        <br>
                        <button type="submit">Generate Puzzle</button>
                    </form>
                </section>

                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    
                    <?php if (!empty($generated_content)): ?>
                        <section class="word-search-results">
                            <h2>Your Mini Word Search</h2>
                            <?php if (!empty($unplaced_words)): ?>
                            <div class="warning-box">
                                <strong>Note:</strong> The following words could not be placed (try fewer or shorter words):
                                <?php echo htmlspecialchars(implode(', ', $unplaced_words)); ?>.
                            </div>
                            <?php endif; ?>
                            <h3>Words To Find</h3>
                            <ul class="word-list">
                                <?php foreach ($generated_content['word_list'] as $word): ?>
                                    <li><?php echo htmlspecialchars($word); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <hr>
                            <h3>Puzzle</h3>
                            <img src="<?php echo $generated_content['puzzle_uri']; ?>" alt="Mini Word Search">
                            <hr>
                            <details style="padding-top: 1.2em;">
                                <summary><strong>Click To Reveal The Solution</strong></summary>
                                <h3>Solution</h3>
                                <img src="<?php echo $generated_content['solution_uri']; ?>" alt="Mini Word Search Solution">
                            </details>
                            
                        </section>
                    <?php endif; ?>
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