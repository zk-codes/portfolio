<?php

// --- Sudoku Generator and Image Renderer ---
// Author: Zachary Kai
// Website: zacharykai.net
// Date: 09 Jul 2024 (Modified: 15 Jul 2025 for 6x6 Sudoku)

// This script generates a Sudoku puzzle and its solution, renders them as PNG images,
// and displays them on a webpage. It is designed to be a single, self-contained file.

class SudokuGenerator {
    private $grid;
    private $solution;
    private $gridSize; // Will be 6 for 6x6
    private $subgridWidth; // Will be 3 for 6x6 (3 columns per subgrid)
    private $subgridHeight; // Will be 2 for 6x6 (2 rows per subgrid)

    public function __construct() {
        $this->gridSize = 6;
        $this->subgridWidth = 3; // Standard 6x6 blocks are 2x3 or 3x2. We'll use 2x3 for consistency.
        $this->subgridHeight = 2;
        $this->grid = array_fill(0, $this->gridSize, array_fill(0, $this->gridSize, 0));
    }

    /**
     * Generates a new puzzle and its solution.
     */
    public function generate() {
        $this->fillGrid();
        $this->solution = array_map(function($row) { return $row; }, $this->grid); // Deep copy
        $this->createPuzzle();
    }

    /**
     * Fills the grid with a valid, complete Sudoku solution using backtracking.
     */
    private function fillGrid() {
        // Ensure the grid is empty before filling
        $this->grid = array_fill(0, $this->gridSize, array_fill(0, $this->gridSize, 0));
        $this->solve(0, 0);
    }

    /**
     * Recursive backtracking solver.
     */
    private function solve($row, $col) {
        if ($row == $this->gridSize) {
            return true; // Grid is filled
        }

        $nextRow = ($col == $this->gridSize - 1) ? $row + 1 : $row;
        $nextCol = ($col == $this->gridSize - 1) ? 0 : $col + 1;

        $numbers = range(1, $this->gridSize);
        shuffle($numbers); // Randomize to get a new puzzle each time

        foreach ($numbers as $num) {
            if ($this->isSafe($row, $col, $num)) {
                $this->grid[$row][$col] = $num;
                if ($this->solve($nextRow, $nextCol)) {
                    return true;
                }
                $this->grid[$row][$col] = 0; // Backtrack
            }
        }
        return false;
    }

    /**
     * Removes digits from the solved grid to create a puzzle.
     * Aims for a unique solution with around 8-12 numbers,
     * ensuring no more than two numbers per 2x3 subgrid.
     */

    private function createPuzzle() {
    // Start with a full solution and try to remove numbers
    $this->grid = array_map(function($row) { return $row; }, $this->solution);

    // Get all cell coordinates and shuffle them
    $allCells = [];
    for ($r = 0; $r < $this->gridSize; $r++) {
        for ($c = 0; $c < $this->gridSize; $c++) {
            $allCells[] = [$r, $c];
        }
    }
    shuffle($allCells);

    // Define clue constraints
    $minClues = 8;
    $maxCluesPerSubgrid = 2;
    $cluesRemoved = 0;
    $totalCells = $this->gridSize * $this->gridSize;

    // Iterate through each cell and try to remove it
    foreach ($allCells as $coords) {
        list($row, $col) = $coords;
        
        // If we've already removed enough to hit the minimum, stop
        if (($totalCells - $cluesRemoved) <= $minClues) {
            break;
        }

        $originalValue = $this->grid[$row][$col];
        $this->grid[$row][$col] = 0; // Temporarily remove the number
        
        // Make a copy to test for uniqueness
        $tempGrid = array_map(function($r) { return $r; }, $this->grid);
        $solutions = 0;
        $this->countSolutions($tempGrid, $solutions);

        // Check if removing the number breaks the puzzle's uniqueness
        if ($solutions !== 1) {
            // If it's no longer unique, put the number back
            $this->grid[$row][$col] = $originalValue;
        } else {
            // The removal was successful, so increment our counter
            $cluesRemoved++;
        }
    }

    // After attempting to remove cells, perform a final check on subgrid counts
    // This part is optional but good for puzzle quality
    $subgridClueCounts = $this->countCluesPerSubgrid($this->grid);
    foreach ($subgridClueCounts as $count) {
        if ($count > $maxCluesPerSubgrid) {
            // This puzzle might be too clustered. For this fix, we'll allow it.
            // In a more advanced version, you might regenerate if this happens.
            error_log("Generated a puzzle with more than $maxCluesPerSubgrid clues in a subgrid.");
            break;
        }
    }
}

    /**
     * Counts the number of clues in each subgrid.
     * @param array $grid The grid to count clues from.
     * @return array An array of clue counts for each subgrid.
     */
    private function countCluesPerSubgrid($grid) {
        $subgridCounts = array_fill(0, ($this->gridSize / $this->subgridHeight) * ($this->gridSize / $this->subgridWidth), 0);
        for ($r = 0; $r < $this->gridSize; $r++) {
            for ($c = 0; $c < $this->gridSize; $c++) {
                if ($grid[$r][$c] !== 0) {
                    $subgridRowIndex = floor($r / $this->subgridHeight);
                    $subgridColIndex = floor($c / $this->subgridWidth);
                    $subgridIndex = $subgridRowIndex * ($this->gridSize / $this->subgridWidth) + $subgridColIndex;
                    $subgridCounts[$subgridIndex]++;
                }
            }
        }
        return $subgridCounts;
    }


    /**
     * Counts the number of solutions for a given grid to ensure uniqueness.
     * This method is called with a temporary grid copy.
     */
    private function countSolutions(&$grid, &$count) {
        // Optimization: if we already found more than 1 solution, no need to continue
        if ($count > 1) {
            return;
        }

        // Find the next empty cell
        $foundEmpty = false;
        for ($row = 0; $row < $this->gridSize; $row++) {
            for ($col = 0; $col < $this->gridSize; $col++) {
                if ($grid[$row][$col] == 0) {
                    $foundEmpty = true;
                    break 2; // Exit both loops
                }
            }
        }

        // If no empty cell is found, a solution has been completed
        if (!$foundEmpty) {
            $count++;
            return;
        }

        $numbers = range(1, $this->gridSize);
        shuffle($numbers); // Randomize to find solutions in different orders

        foreach ($numbers as $num) {
            if ($this->isSafeGrid($grid, $row, $col, $num)) {
                $grid[$row][$col] = $num;
                $this->countSolutions($grid, $count);
                $grid[$row][$col] = 0; // Backtrack
            }
        }
        return; // No number can be placed, this path leads to no solution
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
        for ($x = 0; $x < $this->gridSize; $x++) {
            if ($grid[$row][$x] == $num) return false;
        }
        // Check column
        for ($x = 0; $x < $this->gridSize; $x++) {
            if ($grid[$x][$col] == $num) return false;
        }
        // Check subgrid (2x3 for 6x6 Sudoku)
        $startRow = $row - ($row % $this->subgridHeight);
        $startCol = $col - ($col % $this->subgridWidth);
        for ($i = 0; $i < $this->subgridHeight; $i++) {
            for ($j = 0; $j < $this->subgridWidth; $j++) {
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
 * @param array $grid The 6x6 Sudoku grid data.
 * @param string $filename The path to save the PNG file.
 * @param string $fontPath Path to the TTF font file.
 */
function renderSudokuImage($grid, $filename, $fontPath) {
    // --- Image Setup ---
    $gridSize = count($grid); // This will be 6
    $imageSize = 1500;
    $cellSize = $imageSize / $gridSize;

    // Subgrid dimensions for line drawing (2x3 blocks for 6x6)
    $subgridHeight = 2;
    $subgridWidth = 3;

    $image = imagecreatetruecolor($imageSize, $imageSize);

    // --- Enable transparency ---
    imagealphablending($image, false);
    imagesavealpha($image, true);

    // --- Colors ---
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127); // Fully transparent
    $black = imagecolorallocate($image, 0, 0, 0);

    // Fill the background with transparent color
    imagefill($image, 0, 0, $transparent);

    // --- Draw Grid Lines ---
    $thinLineThickness = 1;
    $thickLineThickness = 5;

    for ($i = 0; $i <= $gridSize; $i++) {
        $pos = $i * $cellSize;
        
        // Horizontal lines: thick every $subgridHeight rows (0, 2, 4, 6)
        $thicknessH = ($i % $subgridHeight == 0) ? $thickLineThickness : $thinLineThickness;
        imagesetthickness($image, $thicknessH);
        imageline($image, 0, $pos, $imageSize, $pos, $black);

        // Vertical lines: thick every $subgridWidth columns (0, 3, 6)
        $thicknessV = ($i % $subgridWidth == 0) ? $thickLineThickness : $thinLineThickness;
        imagesetthickness($image, $thicknessV);
        imageline($image, $pos, 0, $pos, $imageSize, $black);
    }
    
    // Explicitly draw the outer border to ensure it's always thick and crisp
    imagesetthickness($image, $thickLineThickness);
    imageline($image, 0, 0, $imageSize, 0, $black); // Top
    imageline($image, 0, $imageSize - 1, $imageSize, $imageSize - 1, $black); // Bottom
    imageline($image, 0, 0, 0, $imageSize, $black); // Left
    imageline($image, $imageSize - 1, 0, $imageSize - 1, $imageSize, $black); // Right


    // --- Draw Numbers ---
    $fontSize = $cellSize / 2.5; // Adjust font size relative to cell size
    
    if (!file_exists($fontPath)) {
        // If font file not found, log error and draw a placeholder message
        error_log("Error: Font file not found at " . $fontPath);
        imagestring($image, 5, 50, $imageSize / 2, "Font Error! Check logs.", $black);
    } else {
        for ($row = 0; $row < $gridSize; $row++) {
            for ($col = 0; $col < $gridSize; $col++) {
                if ($grid[$row][$col] != 0) { // Only draw if the cell is not empty (0)
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
$puzzleImageFile = $_SERVER['DOCUMENT_ROOT'] . '/tools/output/6x6puzzle.png';
$solutionImageFile = $_SERVER['DOCUMENT_ROOT'] . '/tools/output/6x6solution.png';

$showSudoku = false;
$errorMessage = '';
$expectedTitle = '6x6 Sudoku Maker';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['page_title']) && $_POST['page_title'] === $expectedTitle) {
        $sudoku = new SudokuGenerator();
        $sudoku->generate();

        $puzzleGrid = $sudoku->getPuzzle();
        $solutionGrid = $sudoku->getSolution();

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
        <title>6x6 Sudoku Maker | Lunaseeker Press</title>
        <link rel="canonical" href="https://lunaseeker.com/tools/6x6sudoku">
        <meta name="date" content="2025-07-15">
        <meta name="last-modified" content="2025-07-15">
        <meta name="description" content="A dynamically generated 6x6 Sudoku puzzle and its solution.">
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
                    <p class="smalltext">You Are Here ➸ <a href="https://lunaseeker.com">Homepage</a> • <a href="https://lunaseeker.com/sitemap#tools">Tools</a></p>
                    <h1>6x6 Sudoku Maker</h1>
                    <p class="smalltext">
                        <strong>Written By</strong>: <a href="/about">Zachary Kai</a> »
                        <strong>Published</strong>: <time class="dt-published" datetime="2025-07-15">15 Jul 2025</time> | 
                        <strong>Updated</strong>: <time class="dt-modified" datetime="2025-07-15">15 Jul 2025</time>
                    </p>
                </header>
                
                <!-- Page Body -->
                
                <p id="top" class="p-summary">Here's a Sudoku puzzle generator. To generate a new puzzle, enter the exact page title in the field below and click 'Generate'.</p>

                <section class="e-content">
                    <form method="post">
                        <label for="page_title">Type in this page's title: what the H1 at the top says...</label>
                        <br>
                        <input type="text" id="page_title" name="page_title" size="50" required>
                        <br>
                        <button type="submit">Generate Sudoku</button>
                        <?php if ($errorMessage): ?>
                            <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
                        <?php endif; ?>
                    </form>

                    <?php if ($showSudoku): ?>
                        <section>
                            <h2>Puzzle</h2>
                            <p>A new 6x6 Sudoku grid. Good luck!</p>
                            <img src="/tools/output/6x6puzzle.png?v=<?php echo filemtime(__DIR__ . '/tools/output/6x6puzzle.png'); ?>" alt="6x6 Sudoku Puzzle">
                        </section>            
                        <section>
                            <details>
                                <summary><strong>Click here to reveal the solution.</strong></summary>
                                <h2>Solution</h2>
                                <p>Stuck? Here's the solution to the puzzle above.</p>
                                <img src="/tools/output/6x6solution.png?v=<?php echo filemtime(__DIR__ . '/tools/output/6x6solution.png'); ?>" alt="6x6 Sudoku Solution">
                            </details>
                        </section>
                        <?php else: ?>
                            <p>Sudoku will appear here after successful generation.</p>
                    <?php endif; ?>

                </section>

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