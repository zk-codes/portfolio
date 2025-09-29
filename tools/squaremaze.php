<?php

// --- Maze Generation & Drawing Functions ---

/**
 * Generates maze data using the Recursive Backtracker algorithm.
 * @param int $width The number of cells horizontally.
 * @param int $height The number of cells vertically.
 * @return array The grid representing the maze.
 */

function generateMazeData(int $width, int $height): array {
    $maze = array_fill(0, $height, array_fill(0, $width, []));
    $stack = [];

    // Start carving from a random cell
    $startX = rand(0, $width - 1);
    $startY = rand(0, $height - 1);
    $visited = array_fill(0, $height, array_fill(0, $width, false));
    $visited[$startY][$startX] = true;
    
    array_push($stack, [$startX, $startY]);

    while (count($stack) > 0) {
        [$cx, $cy] = end($stack);
        $directions = ['N' => [0, -1], 'S' => [0, 1], 'W' => [-1, 0], 'E' => [1, 0]];
        $neighbors = [];

        foreach ($directions as $dir => $move) {
            $nx = $cx + $move[0];
            $ny = $cy + $move[1];
            if ($nx >= 0 && $nx < $width && $ny >= 0 && $ny < $height && !$visited[$ny][$nx]) {
                $neighbors[$dir] = [$nx, $ny];
            }
        }

        if (count($neighbors) > 0) {
            $dir = array_rand($neighbors);
            [$nx, $ny] = $neighbors[$dir];
            
            // Carve path
            $maze[$cy][$cx][$dir] = true;
            $oppositeDir = ['N' => 'S', 'S' => 'N', 'E' => 'W', 'W' => 'E'];
            $maze[$ny][$nx][$oppositeDir[$dir]] = true;
            
            $visited[$ny][$nx] = true;
            array_push($stack, [$nx, $ny]);
        } else {
            array_pop($stack);
        }
    }
    return $maze;
}

/**
 * Solves the maze using a breadth-first search.
 * @return array The path from start to end.
 */
function solveMaze(array &$maze, int $startX, int $startY, int $endX, int $endY): array {
    $width = count($maze[0]);
    $height = count($maze);
    $queue = [[[$startX, $startY]]];
    $visited = array_fill(0, $height, array_fill(0, $width, false));
    $visited[$startY][$startX] = true;

    while (count($queue) > 0) {
        $path = array_shift($queue);
        [$x, $y] = end($path);

        if ($x === $endX && $y === $endY) {
            return $path;
        }

        foreach ($maze[$y][$x] as $dir => $isOpen) {
            if ($isOpen) {
                $move = ['N' => [0, -1], 'S' => [0, 1], 'W' => [-1, 0], 'E' => [1, 0]][$dir];
                $nx = $x + $move[0];
                $ny = $y + $move[1];
                if (!$visited[$ny][$nx]) {
                    $visited[$ny][$nx] = true;
                    $newPath = $path;
                    array_push($newPath, [$nx, $ny]);
                    array_push($queue, $newPath);
                }
            }
        }
    }
    return [];
}

/**
 * Draws the maze image and returns it as a Base64 encoded string.
 * @param array $maze The maze data.
 * @param array|null $solutionPath The solution path, or null for the puzzle.
 * @param int $startX Start X cell.
 * @param int $startY Start Y cell.
 * @param int $endX End X cell.
 * @param int $endY End Y cell.
 * @return string The Base64 encoded PNG image data.
 */

function drawMazeImage(array $maze, ?array $solutionPath, int $startX, int $startY, int $endX, int $endY): string {
    $imgSize = 1500;
    $padding = 30;
    $lineThickness = 4;

    $rows = count($maze);
    $cols = count($maze[0]);
    $cellSize = floor(($imgSize - 2 * $padding) / max($rows, $cols));
    
    $width = $cols * $cellSize;
    $height = $rows * $cellSize;
    $xOffset = ($imgSize - $width) / 2;
    $yOffset = ($imgSize - $height) / 2;

    $image = imagecreatetruecolor($imgSize, $imgSize);
    
    // Allocate colors
    $bgColor = imagecolorallocate($image, 255, 255, 255);
    $wallColor = imagecolorallocate($image, 18, 18, 18);
    $solutionColor = imagecolorallocate($image, 220, 38, 127);
    $arrowColor = $wallColor;

    imagefill($image, 0, 0, $bgColor);
    imagesetthickness($image, $lineThickness);

    // Draw maze walls
    for ($r = 0; $r < $rows; $r++) {
        for ($c = 0; $c < $cols; $c++) {
            $x1 = $xOffset + $c * $cellSize;
            $y1 = $yOffset + $r * $cellSize;
            $x2 = $x1 + $cellSize;
            $y2 = $y1 + $cellSize;
            if (!isset($maze[$r][$c]['N'])) imageline($image, $x1, $y1, $x2, $y1, $wallColor); // Top wall
            if (!isset($maze[$r][$c]['S'])) imageline($image, $x1, $y2, $x2, $y2, $wallColor); // Bottom wall
            if (!isset($maze[$r][$c]['W'])) imageline($image, $x1, $y1, $x1, $y2, $wallColor); // Left wall
            if (!isset($maze[$r][$c]['E'])) imageline($image, $x2, $y1, $x2, $y2, $wallColor); // Right wall
        }
    }
    
    // Carve entrance and exit from the outer border
    imageline($image, $xOffset + $startX * $cellSize, $yOffset, $xOffset + ($startX + 1) * $cellSize, $yOffset, $bgColor);
    imageline($image, $xOffset + $endX * $cellSize, $yOffset + $height, $xOffset + ($endX + 1) * $cellSize, $yOffset + $height, $bgColor);

    // Draw solution path if provided
    if ($solutionPath) {
        $dashStyle = [$solutionColor, $solutionColor, $solutionColor, $solutionColor, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT];
        imagesetstyle($image, $dashStyle);
        imagesetthickness($image, $lineThickness + 1.25);

        for ($i = 0; $i < count($solutionPath) - 1; $i++) {
            [$c1, $r1] = $solutionPath[$i];
            [$c2, $r2] = $solutionPath[$i+1];
            $sx1 = $xOffset + $c1 * $cellSize + $cellSize / 2;
            $sy1 = $yOffset + $r1 * $cellSize + $cellSize / 2;
            $sx2 = $xOffset + $c2 * $cellSize + $cellSize / 2;
            $sy2 = $yOffset + $r2 * $cellSize + $cellSize / 2;
            imageline($image, $sx1, $sy1, $sx2, $sy2, IMG_COLOR_STYLED);
        }
    }

    // Draw Entry Arrow
    $arrowSize = $cellSize / 4;
    $entry_ax = $xOffset + $startX * $cellSize + $cellSize / 2;
    $entry_ay = $yOffset - $padding / 2;
    $entry_arrow_points = [$entry_ax, $entry_ay, $entry_ax - $arrowSize, $entry_ay - $arrowSize, $entry_ax + $arrowSize, $entry_ay - $arrowSize];
    imagefilledpolygon($image, $entry_arrow_points, 3, $arrowColor);
    
    // Draw Exit Arrow
    $exit_ax = $xOffset + $endX * $cellSize + $cellSize / 2;
    $exit_ay = $yOffset + $height + $padding / 2;
    // For a downward arrow, the tip's y-value is the largest
    $exit_arrow_points = [$exit_ax, $exit_ay, $exit_ax - $arrowSize, $exit_ay - $arrowSize, $exit_ax + $arrowSize, $exit_ay - $arrowSize];
    imagefilledpolygon($image, $exit_arrow_points, 3, $arrowColor);
    
    // Capture image data to a variable
    ob_start();
    imagepng($image);
    $imageData = ob_get_contents();
    ob_end_clean();
    imagedestroy($image);

    return 'data:image/png;base64,' . base64_encode($imageData);
}

// --- Main Script Logic ---
$maze_image_data = null;
$solution_image_data = null;

$showSudoku = false;
$errorMessage = '';
$captcha = 'Square Maze Maker';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['captcha']) && $_POST['captcha'] === $captcha) {
    $grid_cols = 40;
    $grid_rows = 40;
    
    // Define start and end points
    $startX = rand(1, $grid_cols - 2);
    $startY = 0;
    $endX = rand(1, $grid_cols - 2);
    $endY = $grid_rows - 1;

    // 1. Generate the maze structure
    $maze = generateMazeData($grid_cols, $grid_rows);
    
    // 2. Solve the maze
    $solutionPath = solveMaze($maze, $startX, $startY, $endX, $endY);

    // 3. Draw the images
    $maze_image_data = drawMazeImage($maze, null, $startX, $startY, $endX, $endY);
    $solution_image_data = drawMazeImage($maze, $solutionPath, $startX, $startY, $endX, $endY);

    } else {
        $errorMessage = "Please enter the correct page title to generate the maze.";
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
        <title>Square Maze Maker | Lunaseeker Press</title>
        <link rel="canonical" href="https://lunaseeker.com/tools/squaremaze">
        <meta name="date" content="2025-07-15">
        <meta name="last-modified" content="2025-09-29">
        <meta name="description" content="A simple tool to generate a random square maze and its solution.">
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
                    <p class="smalltext">You Are Here ➸ <a href="https://lunaseeker.com">Homepage</a> • <a href="/tools/">Tools</a> ↴</p>
                    <h1>Square Maze Maker</h1>
                    <p class="smalltext">
                        <strong>Published</strong>: <time class="dt-published" datetime="2025-07-15">15 Jul 2025</time> | 
                        <strong>Updated</strong>: <time class="dt-modified" datetime="2025-09-29">29 Sep 2025</time>
                    </p>
                </header>
                
                <!-- Page Body -->
                <p>•--♡--•</p>
                
                <p class="p-summary">A simple tool to generate a random square maze. Click the button below to create a new puzzle and its solution.</p>

                <section class="maze-form">
                    <form action="/tools/squaremaze" method="post">
                        <label for="captcha">Type in this page's title: what the H1 at the top says...</label>
                        <br>
                        <input type="text" id="captcha" name="captcha" required>
                        <br>
                        <button type="submit">Generate New Maze</button>
                    </form>
                </section>

                <?php if ($maze_image_data && $solution_image_data): ?>
                <section class="maze-container">
                    <h2>Your Maze</h2>
                    <p>Right-click or long-press the image to save it.</p>
                    <img src="<?= htmlspecialchars($maze_image_data) ?>" alt="Generated square maze puzzle">
                    <hr>
                    <details>
                        <summary><strong>Click to reveal the solution</strong></summary>
                        <h2>Solution</h2>
                        <img src="<?= htmlspecialchars($solution_image_data) ?>" alt="Solution to the square maze">
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