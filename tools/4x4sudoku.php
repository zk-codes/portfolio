<?php

// --- Sudoku Generator and Image Renderer ---
// Author: Zachary Kai
// Website: zacharykai.net
// Date: 09 Jul 2024 (Modified: 15 Jul 2025 for 4x4 Sudoku)

// This script generates a Sudoku puzzle and its solution, renders them as PNG images,
// and displays them on a webpage. It is designed to be a single, self-contained file.

class SudokuGenerator {
    private $grid;
    private $solution;
    private $gridSize; // Will be 4 for 4x4
    private $subgridSize; // Will be 2 for 4x4 (2x2 subgrids)

    public function __construct() {
        $this->gridSize = 4;
        $this->subgridSize = 2;
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
     * Removes digits from the solved grid to create a puzzle,
     * ensuring exactly 6 numbers are left, with max 2 per 2x2 subgrid.
     * This method prioritizes stability and uniqueness checks.
     */
    private function createPuzzle() {
        $attempts = 0;
        $maxAttempts = 100; // Limit attempts to prevent infinite loops

        while ($attempts < $maxAttempts) {
            $currentPuzzle = array_map(function($row) { return $row; }, $this->solution); // Start with a fresh solved grid
            $cellsToKeep = []; // To store coordinates of cells we've decided to keep

            // Step 1: Ensure exactly 2 numbers are kept in each of the four 2x2 subgrids
            for ($sr = 0; $sr < $this->subgridSize; $sr++) { // subgrid row index
                for ($sc = 0; $sc < $this->subgridSize; $sc++) { // subgrid col index
                    $subgridCells = [];
                    // Collect all coordinates within this subgrid
                    for ($r = 0; $r < $this->subgridSize; $r++) {
                        for ($c = 0; $c < $this->subgridSize; $c++) {
                            $row = $sr * $this->subgridSize + $r;
                            $col = $sc * $this->subgridSize + $c;
                            $subgridCells[] = [$row, $col];
                        }
                    }
                    shuffle($subgridCells); // Randomize cells within this subgrid

                    // Keep exactly 2 cells from this subgrid
                    for ($i = 0; $i < 2; $i++) {
                        if (isset($subgridCells[$i])) {
                            $cellsToKeep[] = $subgridCells[$i];
                        }
                    }
                }
            }

            // At this point, $cellsToKeep has 8 coordinates (2 per 4 subgrids).
            // We need to reduce this to 6 randomly, while still respecting the 2 per subgrid constraint
            // (meaning we can't remove a cell if its subgrid is already at 2 or less).

            // Calculate which 2 cells from these 8 to remove to get to 6 total.
            // This needs to be carefully chosen not to drop any subgrid below 1 if possible,
            // or violate the max 2 rule.
            // A simpler approach might be to just take 6 distinct cells from the 8, ensuring distribution.

            // Reset current puzzle to empty
            $this->grid = array_fill(0, $this->gridSize, array_fill(0, $this->gridSize, 0));
            
            // Randomly select 6 cells from the initial 8 $cellsToKeep ensuring uniqueness
            // A more robust way to select 6 from 8 with distribution:
            // Temporarily store counts to ensure we don't pick more than 2 from one subgrid.
            $finalCellsToKeep = [];
            $subgridCurrentCounts = array_fill(0, ($this->gridSize / $this->subgridSize) * ($this->gridSize / $this->subgridSize), 0); // 4 subgrids for 4x4

            shuffle($cellsToKeep); // Shuffle the 8 candidate cells

            foreach ($cellsToKeep as $coords) {
                list($row, $col) = $coords;
                $subgridRow = floor($row / $this->subgridSize);
                $subgridCol = floor($col / $this->subgridSize);
                $subgridIndex = $subgridRow * ($this->gridSize / $this->subgridSize) + $subgridCol;

                if ($subgridCurrentCounts[$subgridIndex] < 2 && count($finalCellsToKeep) < 6) {
                    $finalCellsToKeep[] = $coords;
                    $subgridCurrentCounts[$subgridIndex]++;
                }
                if (count($finalCellsToKeep) === 6) {
                    break; // We have our 6 cells
                }
            }

            // Populate the grid with the selected 6 numbers from the solution
            foreach ($finalCellsToKeep as $coords) {
                list($row, $col) = $coords;
                $this->grid[$row][$col] = $this->solution[$row][$col];
            }

            // Check for unique solution
            $testGrid = array_map(function($r) { return $r; }, $this->grid);
            $solutions = 0;
            $this->countSolutions($testGrid, $solutions);

            if ($solutions === 1) {
                return; // Found a unique puzzle with the desired constraints!
            }

            $attempts++;
        }
        
        // Fallback if max attempts reached without finding a suitable puzzle
        // This should ideally not be reached often for 4x4 with these constraints.
        // For robustness, you might want to log this or provide a default puzzle.
        error_log("SudokuGenerator: Could not find a unique 4x4 puzzle with 6 numbers and max 2 per subgrid after $maxAttempts attempts.");
        // Optionally, reset grid to empty or a known valid, but not constrained, puzzle
        $this->grid = array_fill(0, $this->gridSize, array_fill(0, $this->gridSize, 0)); // Ensure it doesn't display a partial failure
    }


    /**
     * Counts the number of solutions for a given grid to ensure uniqueness.
     */
    private function countSolutions(&$grid, &$count) {
        // Limit the number of solutions to count to avoid excessive computation
        if ($count > 1) {
            return;
        }

        for ($row = 0; $row < $this->gridSize; $row++) {
            for ($col = 0; $col < $this->gridSize; $col++) {
                if ($grid[$row][$col] == 0) {
                    for ($num = 1; $num <= $this->gridSize; $num++) {
                        if ($this->isSafeGrid($grid, $row, $col, $num)) {
                            $grid[$row][$col] = $num;
                            $this->countSolutions($grid, $count);
                            $grid[$row][$col] = 0; // Backtrack
                        }
                    }
                    return; // No number can be placed, this path leads to no solution
                }
            }
        }
        $count++; // Found a solution
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
        // Check subgrid (2x2 for 4x4 Sudoku)
        $startRow = $row - $row % $this->subgridSize;
        $startCol = $col - $col % $this->subgridSize;
        for ($i = 0; $i < $this->subgridSize; $i++) {
            for ($j = 0; $j < $this->subgridSize; $j++) {
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
 * @param array $grid The 4x4 Sudoku grid data.
 * @param string $filename The path to save the PNG file.
 * @param string $fontPath Path to the TTF font file.
 */
function renderSudokuImage($grid, $filename, $fontPath) {
    // --- Image Setup ---
    $gridSize = count($grid); // This will be 4
    $imageSize = 1500; // Keep image size at 1500x1500 as requested
    $cellSize = $imageSize / $gridSize; // Cell size for a 4x4 grid

    $image = imagecreatetruecolor($imageSize, $imageSize);

    // --- Enable transparency ---
    imagealphablending($image, false);
    imagesavealpha($image, true);

    // --- Colors ---
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127); // Fully transparent
    $black = imagecolorallocate($image, 0, 0, 0);
    $lightGray = imagecolorallocate($image, 204, 204, 204); // Not explicitly used but good to keep for consistency

    imagefill($image, 0, 0, $transparent); // Fill with transparent background

    // --- Draw Grid Lines ---
    $thinLineThickness = 1;
    $thickLineThickness = 5;

    // Draw all internal and outer lines initially
    for ($i = 0; $i <= $gridSize; $i++) {
        $pos = $i * $cellSize;
        // For a 4x4 grid with 2x2 blocks, thick lines are at every 2nd line (0, 2, 4)
        $thickness = ($i % 2 == 0) ? $thickLineThickness : $thinLineThickness;

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
    $fontSize = $cellSize / 2.5; // Adjust font size relative to cell size
    
    if (!file_exists($fontPath)) {
        // Fallback if font is not found
        // Draw a simple error message on the image
        imagestring($image, 5, 50, $imageSize / 2, "Error: Font file not found at " . $fontPath, $black);
    } else {
        for ($row = 0; $row < $gridSize; $row++) {
            for ($col = 0; $col < $gridSize; $col++) {
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

// File Paths
$fontFile = $_SERVER['DOCUMENT_ROOT'] . '/assets/fonts/ebgaramond.ttf';
$puzzleImageFile = $_SERVER['DOCUMENT_ROOT'] . '/tools/output/4x4puzzle.png';
$solutionImageFile = $_SERVER['DOCUMENT_ROOT'] . '/tools/output/4x4solution.png';

$showSudoku = false;
$errorMessage = '';
$expectedTitle = '4x4 Sudoku Maker';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['page_title']) && $_POST['page_title'] === $expectedTitle) {
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
        <link rel="webmention" href="https://webmention.io/zacharykai.net/webmention"/>
        <!-- Page Details -->
        <title>4x4 Sudoku Maker | Lunaseeker Press</title>
        <link rel="canonical" href="https://lunaseeker.com/tools/4x4sudoku">
        <meta name="date" content="2025-07-15">
        <meta name="last-modified" content="2025-07-15">
        <meta name="description" content="A dynamically generated 4x4 Sudoku puzzle and its solution.">
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
                    <p class="smalltext">You Are Here ➸ <a href="https://lunaseeker.com">Homepage</a></p>
                    <h1>4x4 Sudoku Maker</h1>
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
                        <input type="text" id="page_title" name="page_title" required>
                        <br>
                        <button type="submit">Generate Sudoku</button>
                        <?php if ($errorMessage): ?>
                            <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
                        <?php endif; ?>
                    </form>

                    <?php if ($showSudoku): ?>
                        <section>
                            <h2>Puzzle</h2>
                            <p>A new 4x4 Sudoku grid. Good luck!</p>
                            <img src="/tools/output/4x4puzzle.png<?php echo '?v=' . filemtime($_SERVER['DOCUMENT_ROOT'] . '/tools/output/4x4puzzle.png'); ?>" alt="4x4 Sudoku Puzzle">
                        </section>            
                        <section>
                            <details>
                                <summary><strong>Click here to reveal the solution.</strong></summary>
                                <h2>Solution</h2>
                                <p>Stuck? Here's the solution to the puzzle above.</p>
                                <img src="/tools/output/4x4solution.png<?php echo '?v=' . filemtime($_SERVER['DOCUMENT_ROOT'] . '/tools/output/4x4solution.png'); ?>" alt="4x4 Sudoku Solution">
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