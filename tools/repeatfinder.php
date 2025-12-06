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
        <title>Repeats Finder | Lunaseeker Press</title>
        <link rel="canonical" href="https://lunaseeker.com/tools/repeatfinder">
        <meta name="date" content="2025-00-00">
        <meta name="last-modified" content="2025-00-00">
        <meta name="description" content="Paste your text to analyze repeated phrases, word echoes, and generate a report of your top ten most frequently used words.">
        <style>.phrase-highlight {background-color: #FFD700; border-radius: 3px;} .proximity-highlight {background-color: #ADD8E6; border-radius: 3px;}</style>
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
                    <p class="smalltext">You Are Here ➸ <a href="https://lunaseeker.com">Homepage</a> • <a href="https://lunaseeker.com/sitemap#tools">Tools</a></p>
                    <h1>Repeats Finder</h1>
                    <p class="smalltext">
                        <strong>Written By</strong>: <a href="/about">Zachary Kai</a> »
                        <strong>Published</strong>: <time class="dt-published" datetime="2025-00-00">00 XXX 2025</time> | 
                        <strong>Updated</strong>: <time class="dt-modified" datetime="2025-00-00">00 XXX 2025</time>
                    </p>
                </header>
                
                <!-- Page Body -->
                
                <p>•--♡--•</p>
                
                <p id="top" class="p-summary">Paste your text to analyze repeated phrases, word echoes, and generate a report of your top ten most frequently used words.</p>
                <form action="" method="post">
                    <label for="text_to_analyze">Enter your written piece below:*</label>
                    <textarea name="text_to_analyze">
                    <?php echo isset($_POST['text_to_analyze']) ? htmlspecialchars($_POST['text_to_analyze']) : ''; ?>
                    </textarea>
                    <input type="submit" name="submit" value="Analyze Text">
                </form>

                <?php
                
                if (isset($_POST['submit']) && !empty($_POST['text_to_analyze'])) {
                
                // --- CONFIGURATION & STOP WORDS ---

                $proximity_window = 15;
                $min_phrase_len = 3;
                $max_phrase_len = 5;

                $stop_words = [
                
                    // Articles, Prepositions, Conjunctions, Pronouns...

                    'a','an','the','and','but','or','for','nor','so','yet','in','on','at','to','from','by','with','of','about','as','is','am','are','was','were','be','been','being','have','has','had','do','does','did','i','you','he','she','it','we','they','me','him','her','us','them','my','your','his','its','our','their','that','which','who','what','when','where','why','how','not','so','up','out',
                    
                    // Dialogue Tags

                    'said','asked','replied','shouted','answered','exclaimed', 'muttered','mumbled',

                ];
        
                $stop_words_map = array_flip($stop_words); // Use a map for faster lookups

                // --- INITIALIZATION ---

                $text = $_POST['text_to_analyze'];
                $original_words = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                
                $clean_words = [];
                $original_to_clean_map = []; // Map from original_words index to clean_words index
                $clean_to_original_map = []; // Map from clean_words index to original_words index
                $clean_word_index = 0;

                foreach ($original_words as $i => $word_part) {
                    if (!preg_match('/\s+/', $word_part)) { // If it's not just whitespace
                        $cleaned_word = strtolower(preg_replace('/[^\w]/', '', $word_part));
                        if (!empty($cleaned_word)) {
                            $clean_words[] = $cleaned_word;
                            $original_to_clean_map[$i] = $clean_word_index;
                            $clean_to_original_map[$clean_word_index] = $i;
                            $clean_word_index++;
                        }
                    }
                }

                $word_count = count($clean_words);
                $highlights = array_fill(0, count($original_words), 'none');

                // --- 1. PHRASE HIGHLIGHTING (FILTERED) ---
                
                $phrases = [];
                for ($len = $max_phrase_len; $len >= $min_phrase_len; $len--) {
                    for ($i = 0; $i <= $word_count - $len; $i++) {
                        $phrase_words = array_slice($clean_words, $i, $len);
                        $phrase = implode(' ', $phrase_words);
                        
                        $is_significant_phrase = false;
                        foreach($phrase_words as $p_word) {
                            if (!isset($stop_words_map[$p_word])) {
                                $is_significant_phrase = true;
                                break;
                            }
                        }

                        if ($is_significant_phrase) {
                            if (!isset($phrases[$phrase])) {
                                $phrases[$phrase] = [];
                            }
                            $phrases[$phrase][] = $i;
                        }
                    }
                }
        
                foreach ($phrases as $phrase => $positions) {
                    if (count($positions) > 1) {
                        $phrase_len = count(explode(' ', $phrase));
                        foreach ($positions as $start_pos_clean) {
                            for ($i = 0; $i < $phrase_len; $i++) {
                                $current_clean_word_index = $start_pos_clean + $i;
                                if (isset($clean_to_original_map[$current_clean_word_index])) {
                                    $word_idx_in_original = $clean_to_original_map[$current_clean_word_index];
                                    $highlights[$word_idx_in_original] = 'phrase';
                                }
                            }
                        }
                    }
                }

                // --- 2. PROXIMITY HIGHLIGHTING (FILTERED) ---
                
                $word_positions = [];
                foreach ($clean_words as $i => $word) {
                    if (!isset($stop_words_map[$word])) {
                        $word_positions[$word][] = $i;
                    }
                }

                foreach ($word_positions as $word => $positions) {
                    if (count($positions) > 1) {
                        for ($i = 0; $i < count($positions) - 1; $i++) {
                            if (($positions[$i+1] - $positions[$i]) <= $proximity_window) {
                                $word_idx1_clean = $positions[$i];
                                $word_idx2_clean = $positions[$i+1];

                                if (isset($clean_to_original_map[$word_idx1_clean])) {
                                    $word_idx1_original = $clean_to_original_map[$word_idx1_clean];
                                    if ($highlights[$word_idx1_original] === 'none') {
                                        $highlights[$word_idx1_original] = 'proximity';
                                    }
                                }
                                if (isset($clean_to_original_map[$word_idx2_clean])) {
                                    $word_idx2_original = $clean_to_original_map[$word_idx2_clean];
                                    if ($highlights[$word_idx2_original] === 'none') {
                                        $highlights[$word_idx2_original] = 'proximity';
                                    }
                                }
                            }
                        }
                    }
                }

                // --- 3. TOP 10 WORD REPORT ---

                $word_frequencies = array_count_values($clean_words);
                $filtered_frequencies = array_diff_key($word_frequencies, $stop_words_map);
                arsort($filtered_frequencies);
                $top_10_words = array_slice($filtered_frequencies, 0, 10, true);

                // --- 4. PRODUCE OUTPUT ---

                echo '<section class="container">';

                // Render Highlighted Text

                echo '<section class="results-box">';
                echo '<h3>Analysis Results</h3>';
                $output_html = '';
                $current_highlight = 'none';

                foreach ($original_words as $i => $word) {
                    $highlight_type = $highlights[$i] ?? 'none';
            
                    if ($highlight_type !== $current_highlight) {
                        if ($current_highlight !== 'none') $output_html .= '</span>';
                        if ($highlight_type !== 'none') $output_html .= '<span class="' . $highlight_type . '-highlight">';
                        $current_highlight = $highlight_type;
                    }

                $output_html .= htmlspecialchars($word);
                
                }
                
                if ($current_highlight !== 'none') $output_html .= '</span>';
        
                echo '<p>' . nl2br($output_html) . '</p>';
                echo '</section>';

                // Render Report

                echo '<section class="results-box">';
                echo '<h3>Top 10 Significant Words</h3>';
                echo '<p>Excluding common prepositions, articles, pronouns, and dialogue tags.</p>';
                if (empty($top_10_words)) {
                echo '<p>Not enough text to generate a report.</p>';
                } else {
                    echo '<ol>';
                    foreach ($top_10_words as $word => $count) {
                        echo '<li>' . htmlspecialchars($word) . '&nbsp; &rarr; &nbsp;' . $count . '</li>';
                    }
                echo '</ol>';

                }

                echo '</section>';
                echo '</section>';

                }
                ?>

                <p>•--♡--•</p>
                
                <!-- Footer -->
                <footer>
                    <hr>
                    <section class="acknowledgement">
                        <h2>Acknowledgement Of Country</h2>
                        <p>I acknowledge the folks whose lands I owe my existence to: the <a href="https://kht.org.au/" rel="noopener">Koori people</a>. The traditional owners, storytellers, and first peoples. This land's been tended and lived alongside for millennia with knowledge passed down through generations. What a legacy. May it prevail.</p>
                    </section>
                    <p class="smalltext">Est. 2019 | Have a wonderful <a href="https://indieweb.org/Universal_Greeting_Time" rel="noopener">morning</a>, wherever you are.</p>
                </footer>

            </article>
        </main>
    </body>
</html>