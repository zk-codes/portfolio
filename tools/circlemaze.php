<?php
session_start(); // Start the session at the very beginning

// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Maze Generation & Pathfinding Functions ---

/**
 * Generates maze data using the Recursive Backtracker algorithm.
 * @param int $width The number of cells circumferentially.
 * @param int $height The number of cells radially.
 * @return array The grid representing the maze.
 */
function generateMazeData(int $width, int $height): array {
    $maze = array_fill(0, $height, array_fill(0, $width, []));
    $stack = [];
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
            $nx = (int)($cx + $move[0]);
            $ny = (int)($cy + $move[1]);
            if ($dir === 'W' && $cx === 0) $nx = $width - 1;
            if ($dir === 'E' && $cx === $width - 1) $nx = 0;
            if ($nx >= 0 && $nx < $width && $ny >= 0 && $ny < $height && !$visited[$ny][$nx]) {
                $neighbors[$dir] = [$nx, $ny];
            }
        }
        if (count($neighbors) > 0) {
            $dir = array_rand($neighbors);
            [$nx, $ny] = $neighbors[$dir];
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
 * Makes the maze harder by removing walls, with a strong bias towards the inner rings.
 * @param array &$maze The maze data (passed by reference).
 * @param int $complexity The number of extra walls to remove.
 */
function addLoops(array &$maze, int $complexity = 15): void {
    $height = count($maze);
    if ($height === 0) return;
    $width = count($maze[0]);
    if ($width === 0) return;
    $wallsRemoved = 0;
    $maxAttempts = $complexity * 10;
    $attempts = 0;
    while ($wallsRemoved < $complexity && $attempts < $maxAttempts) {
        $attempts++;
        if ($height <= 2) {
            break;
        }
        $randomFloat = mt_rand() / mt_getrandmax();
        $biasedFloat = pow($randomFloat, 3.0); // Increased bias to 3.0
        $r = (int)floor(1 + $biasedFloat * ($height - 3));
        $c = rand(0, $width - 1);
        $possibleDirs = [];
        $allDirs = ['N', 'S', 'W', 'E'];
        foreach ($allDirs as $dir) {
            if (!isset($maze[$r][$c][$dir])) {
                $possibleDirs[] = $dir;
            }
        }
        if (count($possibleDirs) > 0) {
            $dirToRemove = $possibleDirs[array_rand($possibleDirs)];
            $nr = $r;
            $nc = $c;
            $oppositeDir = ['N' => 'S', 'S' => 'N', 'W' => 'E', 'E' => 'W'];
            switch ($dirToRemove) {
                case 'N': $nr--; break;
                case 'S': $nr++; break;
                case 'W': $nc = ($c > 0) ? $c - 1 : $width - 1; break;
                case 'E': $nc = ($c < $width - 1) ? $c + 1 : 0; break;
            }
            if (isset($maze[$nr]) && isset($maze[$nr][$nc])) {
                $maze[$r][$c][$dirToRemove] = true;
                $maze[$nr][$nc][$oppositeDir[$dirToRemove]] = true;
                $wallsRemoved++;
            }
        }
    }
}

/**
 * --- NEW FUNCTION ---
 * Finds the point on the outer edge that is furthest from the start point.
 * @return array The coordinates [x, y] of the optimal endpoint.
 */
function findLongestPathEndpoint(array &$maze, int $startX, int $startY): array {
    $width = count($maze[0]);
    $height = count($maze);
    $queue = [[$startX, $startY]];
    $distances = array_fill(0, $height, array_fill(0, $width, -1));
    $distances[$startY][$startX] = 0;
    $head = 0;
    while ($head < count($queue)) {
        [$x, $y] = $queue[$head++];
        foreach ($maze[$y][$x] as $dir => $isOpen) {
            if ($isOpen) {
                $move = ['N' => [0, -1], 'S' => [0, 1], 'W' => [-1, 0], 'E' => [1, 0]][$dir];
                $nx = (int)($x + $move[0]);
                $ny = (int)($y + $move[1]);
                if ($dir === 'W' && $x === 0) $nx = $width - 1;
                if ($dir === 'E' && $x === $width - 1) $nx = 0;
                if (isset($distances[$ny][$nx]) && $distances[$ny][$nx] === -1) {
                    $distances[$ny][$nx] = $distances[$y][$x] + 1;
                    $queue[] = [$nx, $ny];
                }
            }
        }
    }
    $outerRingIndex = $height - 1;
    $maxDist = -1;
    $endPoint = [$startX, $outerRingIndex];
    for ($c = 0; $c < $width; $c++) {
        if ($distances[$outerRingIndex][$c] > $maxDist) {
            $maxDist = $distances[$outerRingIndex][$c];
            $endPoint = [$c, $outerRingIndex];
        }
    }
    return $endPoint;
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
                $nx = (int)($x + $move[0]);
                $ny = (int)($y + $move[1]);
                if ($dir === 'W' && $x === 0) $nx = $width - 1;
                if ($dir === 'E' && $x === $width - 1) $nx = 0;
                if (isset($visited[$ny][$nx]) && !$visited[$ny][$nx]) {
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
 * Draws the circular maze with all fixes implemented.
 */
function drawCircularMazeImage(array $maze, ?array $solutionPath, int $startX, int $startY, int $endX, int $endY): string {
    $imgSize = 1500;
    $padding = 50;
    $lineThickness = 2;
    $rings = count($maze);
    $sectors = count($maze[0]);
    $centerX = (int)($imgSize / 2);
    $centerY = (int)($imgSize / 2);
    $outerRadius = (int)($imgSize / 2 - $padding);
    $sectorAngle = 360 / $sectors;
    $image = imagecreatetruecolor($imgSize, $imgSize);
    imageantialias($image, true);
    $bgColor = imagecolorallocatealpha($image, 255, 255, 255, 127); // Alpha value 127 for full transparency
    $wallColor = imagecolorallocate($image, 18, 18, 18);
    $solutionColor = imagecolorallocate($image, 220, 38, 127);
    $arrowColor = imagecolorallocate($image, 18, 18, 18);
    $startColor = imagecolorallocate($image, 18, 18, 18);

    // Ensure transparency is saved and then fill with the transparent background color
    imagesavealpha($image, true);
    imagefill($image, 0, 0, $bgColor);
    
    imagesetthickness($image, $lineThickness);
    $innerRadius = $outerRadius * 0.1; // Smaller hole for more maze
    $totalRadialDistance = $outerRadius - $innerRadius;
    $power = 1.15;
    $radii = [];
    for ($r = 0; $r <= $rings; $r++) {
        $radii[$r] = $innerRadius + $totalRadialDistance * pow($r / $rings, $power);
    }

    // --- FIX: Robust Wall Drawing Logic ---
    // This logic draws every wall exactly once, preventing gaps or accidental closures.
    for ($r = 0; $r < $rings; $r++) {
        for ($c = 0; $c < $sectors; $c++) {
            $angle_start = $c * $sectorAngle;
            $angle_end = ($c + 1) * $sectorAngle;

            // Draw the Western radial wall if there's no path West.
            if (!isset($maze[$r][$c]['W'])) {
                $rad_in = $radii[$r];
                $rad_out = $radii[$r + 1];
                imageline($image, (int)($centerX + $rad_in * cos(deg2rad($angle_start))), (int)($centerY + $rad_in * sin(deg2rad($angle_start))), (int)($centerX + $rad_out * cos(deg2rad($angle_start))), (int)($centerY + $rad_out * sin(deg2rad($angle_start))), $wallColor);
            }
            
            // Draw the Southern circular wall if there's no path South.
            $isExitPoint = ($r === $endY && $c === $endX);
            if (!isset($maze[$r][$c]['S'])) {
                // Also, don't draw the wall segment if it's the exit point.
                if (!($r === $rings - 1 && $isExitPoint)) {
                    $rad_out = $radii[$r + 1];
                    imagearc($image, $centerX, $centerY, (int)($rad_out * 2), (int)($rad_out * 2), (int)$angle_start, (int)$angle_end, $wallColor);
                }
            }
        }
    }
    // Draw the solid innermost boundary wall.
    imagearc($image, $centerX, $centerY, (int)($radii[0] * 2), (int)($radii[0] * 2), 0, 360, $wallColor);

    // Draw solution path (if provided)
    if ($solutionPath) {
        imagesetthickness($image, $lineThickness + 2);
        for ($i = 0; $i < count($solutionPath) - 1; $i++) {
            [$c1, $r1] = $solutionPath[$i];
            [$c2, $r2] = $solutionPath[$i + 1];
            if ($r1 === $r2) {
                $path_radius = ($radii[$r1] + $radii[$r1 + 1]) / 2;
                $path_diameter = $path_radius * 2;
                $angle1_deg = ($c1 + 0.5) * $sectorAngle;
                $angle2_deg = ($c2 + 0.5) * $sectorAngle;
                if (abs($c1 - $c2) > 1) {
                    if ($c1 > $c2) {
                        imagearc($image, $centerX, $centerY, (int)$path_diameter, (int)$path_diameter, (int)$angle1_deg, 360, $solutionColor);
                        imagearc($image, $centerX, $centerY, (int)$path_diameter, (int)$path_diameter, 0, (int)$angle2_deg, $solutionColor);
                    } else {
                        imagearc($image, $centerX, $centerY, (int)$path_diameter, (int)$path_diameter, (int)$angle2_deg, 360, $solutionColor);
                        imagearc($image, $centerX, $centerY, (int)$path_diameter, (int)$path_diameter, 0, (int)$angle1_deg, $solutionColor);
                    }
                } else {
                    imagearc($image, $centerX, $centerY, (int)$path_diameter, (int)$path_diameter, (int)min($angle1_deg, $angle2_deg), (int)max($angle1_deg, $angle2_deg), $solutionColor);
                }
            } else {
                $path_angle = deg2rad(($c1 + 0.5) * $sectorAngle);
                $path_rad1 = ($radii[min($r1, $r2)] + $radii[min($r1, $r2) + 1]) / 2;
                $path_rad2 = ($radii[max($r1, $r2)] + $radii[max($r1, $r2) + 1]) / 2;
                imageline($image, (int)($centerX + $path_rad1 * cos($path_angle)), (int)($centerY + $path_rad1 * sin($path_angle)), (int)($centerX + $path_rad2 * cos($path_angle)), (int)($centerY + $path_rad2 * sin($path_angle)), $solutionColor);
            }
        }
    }
    
    // Draw Start Marker at the Center
    $start_angle_rad = deg2rad(($startX + 0.5) * $sectorAngle);
    $start_radius = ($radii[0] + $radii[1]) / 2;
    $marker_cx = (int)($centerX + $start_radius * cos($start_angle_rad));
    $marker_cy = (int)($centerY + $start_radius * sin($start_angle_rad));
    $marker_size = (int)(($radii[1] - $radii[0]) * 0.6);
    imagefilledellipse($image, $marker_cx, $marker_cy, $marker_size, $marker_size, $startColor);

    // Draw Exit Arrow
    $arrowSideLength = 25;
    $angle_exit = deg2rad(($endX + 0.5) * $sectorAngle);
    $r_tip_exit = $outerRadius + 10;
    $p1x_exit = (int)($centerX + $r_tip_exit * cos($angle_exit));
    $p1y_exit = (int)($centerY + $r_tip_exit * sin($angle_exit));
    $p2x_exit = (int)($p1x_exit - $arrowSideLength * cos($angle_exit + deg2rad(30)));
    $p2y_exit = (int)($p1y_exit - $arrowSideLength * sin($angle_exit + deg2rad(30)));
    $p3x_exit = (int)($p1x_exit - $arrowSideLength * cos($angle_exit - deg2rad(30)));
    $p3y_exit = (int)($p1y_exit - $arrowSideLength * sin($angle_exit - deg2rad(30)));
    imagefilledpolygon($image, [$p1x_exit, $p1y_exit, $p2x_exit, $p2y_exit, $p3x_exit, $p3y_exit], $arrowColor);

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
$captcha_error = '';
$expected_captcha_phrase = "Circular Maze Maker";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_captcha = trim($_POST['captcha'] ?? '');
    if (empty($user_captcha)) {
        $captcha_error = "Please type the page's title.";
    } elseif (strcasecmp($user_captcha, $expected_captcha_phrase) !== 0) {
        $captcha_error = "Incorrect page title. Please try again.";
    } else {
        // --- EDIT: Increased maze density for final tuning ---
        $grid_cols = 40;
        $grid_rows = 15;

        // Start at a random cell in the center ring.
        $startX = rand(0, $grid_cols - 1);
        $startY = 0;

        $maze = generateMazeData($grid_cols, $grid_rows);
        
        // Add even more loops for a chaotic center.
        addLoops($maze, 10);
        
        // --- EDIT: Find the objectively hardest exit point ---
        [$endX, $endY] = findLongestPathEndpoint($maze, $startX, $startY);
        
        // Now solve for the longest path.
        $solutionPath = solveMaze($maze, $startX, $startY, $endX, $endY);
        
        $maze_image_data = drawCircularMazeImage($maze, null, $startX, $startY, $endX, $endY);
        $solution_image_data = drawCircularMazeImage($maze, $solutionPath, $startX, $startY, $endX, $endY);
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
        <title>Circular Maze Maker</title>
        <link rel="canonical" href="https://lunaseeker.com/tools/circlemaze">
        <meta name="date" content="2025-07-09">
        <meta name="last-modified" content="2025-07-09">
        <meta name="description" content="Click the button to create a random circular maze and its solution!">
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
                    <li><a href="/sitemap">Sitemap</a></li>
                </ul>
            </nav>

            <!-- Main Content Area -->
            <article id="main">

                <!-- Page Header -->
                <header>
                    <p class="smalltext">You Are Here ➸ <a href="https://lunaseeker.com">Homepage</a> • <a href="/sitemap#tools">Tools</a></p>
                    <h1>Circular Maze Maker</h1>
                    <p class="smalltext">
                        <strong>Written By</strong>: <a href="/about">Zachary Kai</a> »
                        <strong>Published</strong>: <time class="dt-published" datetime="2025-07-09">09 Jul 2025</time> | 
                        <strong>Updated</strong>: <time class="dt-modified" datetime="2025-07-09">09 Jul 2025</time>
                    </p>
                </header>
                
                <!-- Page Body -->
                <p>Click the button to create a random circular maze and its solution!</p>

                <section class="maze-form">
                    <form action="/tools/circlemaze" method="post">
                        <label for="captcha">Type in this page's title:</label><br>
                        <input type="text" id="captcha" name="captcha" required>
                        <button type="submit">Make A Maze</button>
                    </form>
                </section>

                <?php if ($maze_image_data && $solution_image_data): ?>
                <section class="maze-container">
                    <h2>Your Circular Maze</h2>
                    <p>Happy puzzling! You can right-click or long-press on the image to save it.</p>
                    <img src="<?= htmlspecialchars($maze_image_data) ?>" alt="Generated circular maze puzzle">
                    <hr>
                    <h2>Solution</h2>
                    <details>
                        <summary><strong>Click To Reveal The Solution</strong></summary>
                        <img src="<?= htmlspecialchars($solution_image_data) ?>" alt="Solution to the circular maze" style="padding-top: 1.2em;">
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