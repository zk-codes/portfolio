<?php

// --- 9x9 Sudoku Generator And Image Renderer ---

class SudokuGenerator {
    private $grid;
    private $solution;

    public function __construct() {
        $this->grid = array_fill(0, 9, array_fill(0, 9, 0));
    }

    // Generates New Puzzle And Its Solution.
    public function generate() {
        $this->fillGrid();
        $this->solution = array_map(function($row) { return $row; }, $this->grid); // Deep copy
        $this->createPuzzle();
    }

    // Fills the grid with a valid, complete Sudoku solution using backtracking.
    private function fillGrid() {
        $this->solve(0, 0);
    }

    // Recursive backtracking solver.
    private function solve($row, $col) {
        if ($row == 9) {
            return true;
        }

        $nextRow = ($col == 8) ? $row + 1 : $row;
        $nextCol = ($col == 8) ? 0 : $col + 1;

        $numbers = range(1, 9);
        shuffle($numbers);

        foreach ($numbers as $num) {
            if ($this->isSafe($row, $col, $num)) {
                $this->grid[$row][$col] = $num;
                if ($this->solve($nextRow, $nextCol)) {
                    return true;
                }
                $this->grid[$row][$col] = 0;
            }
        }
        return false;
    }

    /**
     * Removes digits from the solved grid to create a puzzle.
     * This implementation removes a fixed number of cells for consistent difficulty.
     * It ensures the puzzle has a single unique solution.
     */
    private function createPuzzle() {
        $cellsToRemove = 54; // Adjust for difficulty. 40-50 is a good range.
        $removed = 0;

        while ($removed < $cellsToRemove) {
            $row = rand(0, 8);
            $col = rand(0, 8);

            if ($this->grid[$row][$col] != 0) {
                $backup = $this->grid[$row][$col];
                $this->grid[$row][$col] = 0;

                // Make a copy to test for a unique solution
                $testGrid = array_map(function($r) { return $r; }, $this->grid);
                $solutions = 0;
                $this->countSolutions($testGrid, $solutions);

                if ($solutions != 1) {
                    // If not unique, restore the number and try another cell
                    $this->grid[$row][$col] = $backup;
                } else {
                    $removed++;
                }
            }
        }
    }
    
    /**
     * Counts the number of solutions for a given grid to ensure uniqueness.
     */
    private function countSolutions(&$grid, &$count) {
        for ($row = 0; $row < 9; $row++) {
            for ($col = 0; $col < 9; $col++) {
                if ($grid[$row][$col] == 0) {
                    for ($num = 1; $num <= 9; $num++) {
                        if ($this->isSafeGrid($grid, $row, $col, $num)) {
                            $grid[$row][$col] = $num;
                            $this->countSolutions($grid, $count);
                            $grid[$row][$col] = 0; // Backtrack
                        }
                    }
                    return;
                }
            }
        }
        $count++;
    }


    /**
     * Checks if a number can be placed in a specific cell in the main grid.
     */
    private function isSafe($row, $col, $num) {
        return $this->isSafeGrid($this->grid, $row, $col, $num);
    }

    /**
     * Checks if a number can be placed in a specific cell in any given grid.
     */
    private function isSafeGrid($grid, $row, $col, $num) {
        // Check row
        for ($x = 0; $x < 9; $x++) {
            if ($grid[$row][$x] == $num) return false;
        }
        // Check column
        for ($x = 0; $x < 9; $x++) {
            if ($grid[$x][$col] == $num) return false;
        }
        // Check 3x3 subgrid
        $startRow = $row - $row % 3;
        $startCol = $col - $col % 3;
        for ($i = 0; $i < 3; $i++) {
            for ($j = 0; $j < 3; $j++) {
                if ($grid[$i + $startRow][$j + $startCol] == $num) return false;
            }
        }
        return true;
    }

    public function getPuzzle() {
        return $this->grid;
    }

    public function getSolution() {
        return $this->solution;
    }
}

/**
 * Renders a Sudoku grid as a PNG image.
 *
 * @param array $grid The 9x9 Sudoku grid data.
 * @param string $filename The path to save the PNG file.
 * @param string $fontPath Path to the TTF font file.
 */
function renderSudokuImage($grid, $filename, $fontPath) {
    // --- Image Setup ---
    $imageSize = 1500;
    $cellSize = $imageSize / 9;
    $image = imagecreatetruecolor($imageSize, $imageSize);

    // --- Colors ---
    $black = imagecolorallocate($image, 0, 0, 0);
    $lightGray = imagecolorallocate($image, 204, 204, 204);

    // Set transparency
    imagealphablending($image, false); // Do not blend colors
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127); // Allocate a fully transparent color
    imagefill($image, 0, 0, $transparent); // Fill the background with the transparent color
    imagesavealpha($image, true); // Save alpha channel

    // --- Draw Grid Lines ---
    $thinLineThickness = 1;
    $thickLineThickness = 5;

    // Draw all internal and outer lines initially
    for ($i = 0; $i <= 9; $i++) {
        $pos = $i * $cellSize;
        $thickness = ($i % 3 == 0) ? $thickLineThickness : $thinLineThickness;

        // Draw horizontal line
        imagesetthickness($image, $thickness);
        imageline($image, 0, $pos, $imageSize, $pos, $black);

        // Draw vertical line
        imageline($image, $pos, 0, $pos, $imageSize, $black);
    }

    // Explicitly draw the outer border to ensure it's always thick and crisp
    imagesetthickness($image, $thickLineThickness);
    // Top border
    imageline($image, 0, 0, $imageSize, 0, $black);
    // Bottom border
    imageline($image, 0, $imageSize - 1, $imageSize, $imageSize - 1, $black); // Adjusted for 0-indexing
    // Left border
    imageline($image, 0, 0, 0, $imageSize, $black);
    // Right border
    imageline($image, $imageSize - 1, 0, $imageSize - 1, $imageSize, $black); // Adjusted for 0-indexing


    // --- Draw Numbers ---
    $fontSize = $cellSize / 2.5;
    
    if (!file_exists($fontPath)) {
        // Fallback if font is not found
        // Draw a simple error message on the image
        imagestring($image, 5, 50, 725, "Error: Font file not found at " . $fontPath, $black);
    } else {
        for ($row = 0; $row < 9; $row++) {
            for ($col = 0; $col < 9; $col++) {
                if ($grid[$row][$col] != 0) {
                    $number = $grid[$row][$col];
                    
                    // Calculate text position for centering
                    $bbox = imagettfbbox($fontSize, 0, $fontPath, $number);
                    $textWidth = $bbox[2] - $bbox[0];
                    $textHeight = $bbox[1] - $bbox[7];
                    
                    $x = ($col * $cellSize) + ($cellSize - $textWidth) / 2;
                    $y = ($row * $cellSize) + ($cellSize + $textHeight) / 2;

                    imagettftext($image, $fontSize, 0, $x, $y, $black, $fontPath, $number);
                }
            }
        }
    }
    
    // --- Save and Clean Up ---
    imagepng($image, $filename);
    imagedestroy($image);
}

// --- Main Execution ---

// Define file paths.
$fontFile = $_SERVER['DOCUMENT_ROOT'] . '/assets/fonts/ebgaramond.ttf';
$puzzleImageFile = $_SERVER['DOCUMENT_ROOT'] . '/tools/output/9x9puzzle.png';
$solutionImageFile = $_SERVER['DOCUMENT_ROOT'] . '/tools/output/9x9solution.png';

$showSudoku = false;
$errorMessage = '';
$expectedTitle = '9x9 Sudoku Maker';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['page_title']) && $_POST['page_title'] === $expectedTitle) {

        // Delete old images before generating new ones
        if (file_exists($puzzleImageFile)) {
            unlink($puzzleImageFile);
        }
        if (file_exists($solutionImageFile)) {
            unlink($solutionImageFile);
        }

        // 1. Generate the Sudoku
        $sudoku = new SudokuGenerator();
        $sudoku->generate();

        // 2. Get the puzzle and solution grids
        $puzzleGrid = $sudoku->getPuzzle();
        $solutionGrid = $sudoku->getSolution();

        // 3. Render the images
        renderSudokuImage($puzzleGrid, $puzzleImageFile, $fontFile);
        renderSudokuImage($solutionGrid, $solutionImageFile, $fontFile);
        $showSudoku = true;

    } else {
        $errorMessage = "Please enter the correct page title to generate the Sudoku.";
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
        <title>9x9 Sudoku Maker | Lunaseeker Press</title>
        <link rel="canonical" href="https://lunaseeker.com/tools/9x9sudoku">
        <meta name="date" content="2025-07-15">
        <meta name="last-modified" content="2025-09-13">
        <meta name="description" content="Click the button to create a random 9x9 Sudoku and its solution!">
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
                </ul>
            </nav>

            <!-- Main Content Area -->
            <article id="main">

                <!-- Page Header -->
                <header>
                    <p class="smalltext">You Are Here ➸ <a href="https://lunaseeker.com">Homepage</a> • <a href="/tools/">Tools</a> ↴</p>
                    <h1>9x9 Sudoku Maker</h1>
                    <p class="smalltext">
                        <strong>Written By</strong>: <a href="/about">Zachary Kai</a> »
                        <strong>Published</strong>: <time class="dt-published" datetime="2025-07-15">15 Jul 2025</time> | 
                        <strong>Updated</strong>: <time class="dt-modified" datetime="2025-09-13">13 Sep 2025</time>
                    </p>
                </header>
                
                <!-- Page Body -->
                <p>Click the button to create a random 9x9 Sudoku and its solution!</p>

                <!-- Creating A Sudoku -->
                <form method="post">
                    <label for="page_title">Type in this page's title: what the H1 at the top says...</label>
                    <br>
                    <input type="text" id="page_title" name="page_title" required>
                    <br>
                    <button type="submit">Generate Sudoku</button>
                    <?php if ($errorMessage): ?>
                        <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
                    <?php endif; ?>
                </form>

                <!-- Displaying Created Sudoku -->
                <?php if ($showSudoku): ?>
                    <?php $timestamp = time(); ?>
                    <!-- Puzzle -->           
                    <section>
                        <h2>Puzzle</h2>
                        <p>A new 9x9 Sudoku grid. Good luck!</p>
                        <img src="/tools/output/9x9puzzle.png?v=<?php echo $timestamp; ?>" alt="9x9 Sudoku Puzzle">
                    </section>
                    <hr>
                    <!-- Solution -->
                    <details>
                        <summary><strong>Click here to see the solution...</strong></summary>
                        <section>
                            <h2>Solution</h2>
                            <p>Stuck? Here's the solution to the puzzle above.</p>
                            <img src="/tools/output/9x9solution.png?v=<?php echo $timestamp; ?>" alt="9x9 Sudoku Solution">
                        </section>
                    </details>
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