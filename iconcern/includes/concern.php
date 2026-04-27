<?php
/**
 * Concern Management Handler
 * Handles concern submission, classification, and routing
 */

require_once __DIR__ . '/../config/config.php';

class Concern {
    private $db;
    private const OFFICE_CATEGORIES = [
        'MIS Office',
        'IMCO Office',
        'Registrar Office',
        'Internet Laboratory',
        'Cashier Office',
        'Maintenance Office',
        'ISSC Office',
        'Faculty Office',
        'Service Inefficiency - cashier office',
        'Service Inefficiency - registrar office',
    ];

    public function __construct() {
        $this->db = getDB();
    }

    private function preprocessText($text) {
        $text = strtolower((string)$text);
        $replacements = [
            'wara' => 'waray',
            'san' => 'an',
            'cr' => 'comfort room',
            'kabaho' => 'mabaho',
            'narubat' => 'rubat',
            'kahugaw' => 'mahugaw',
            'kapaso' => 'mapaso',
        ];
        // Replace only whole-word tokens to avoid collisions like "creation" containing "cr".
        foreach ($replacements as $k => $v) {
            $text = preg_replace('/\b' . preg_quote((string)$k, '/') . '\b/i', (string)$v, $text);
        }
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', trim($text));
        return $text;
    }

    /**
     * Detect college code from free-text concern (PHP fallback).
     * This helps routing even when Python classifier is unavailable.
     */
    private function detectCollegeCodePhp($text): ?string {
        $t = strtolower((string)$text);

        // Use specific, low-collision keywords to avoid false matches.
        $collegeKeywords = [
            'COED' => ['coed', 'college of education', 'education'],
            'CCIS' => ['ccis', 'computing', 'computer science', 'it college', 'information sciences', 'it courses'],
            'CCJS' => ['ccjs', 'criminology', 'criminal justice', 'police science', 'criminal'],
            'COM' => ['college of management', 'management', 'business', 'commerce'],
            'CEA' => ['cea', 'engineering', 'architecture', 'civil', 'mechanical', 'electrical'],
            // Avoid matching "con" inside "cannot": use token keywords for matching.
            'CON' => ['nursing', 'nurse', 'healthcare'],
            'CAT' => ['cat', 'arts and technology', 'arts', 'technology'],
        ];

        foreach ($collegeKeywords as $code => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($kw, ' ') !== false || strlen($kw) > 4) {
                    if (strpos($t, strtolower($kw)) !== false) {
                        return $code;
                    }
                } else {
                    // Token match (prevents "con" in "cannot", etc.)
                    if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $t)) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    /**
     * High-precision rule-based classifier.
     * This runs before TF-IDF and is designed to capture the common exact phrases
     * that map strongly to a single office.
     */
    private function classifyConcernPhpRules($concern_text): ?array {
        $p = $this->preprocessText($concern_text);

        // Registrar: very distinctive academic record phrases
        if (
            preg_match('/\b(transcript|tor)\b/i', $p) ||
            preg_match('/\b(grades|records|academic record|certificate)\b/i', $p) ||
            preg_match('/\bform\s*137\b/i', $p) ||
            preg_match('/\bform\s*138\b/i', $p) ||
            preg_match('/\b(enrollment|registration|enrolled|petition|schedule|units|subjects?)\b/i', $p)
        ) {
            return ['category' => 'Registrar Office', 'confidence' => 0.93];
        }

        // Maintenance: distinctive facilities / repair / electrical issue terms
        if (
            preg_match('/comfort room|comfort\b/i', $p) ||
            preg_match('/\bcr\b/i', $p) ||
            preg_match('/\b(mabaho|mahugaw|rubat|narubat|waray tubig)\b/i', $p) ||
            preg_match('/\b(broken|repair|fix|leak|plumbing|electric|electricity|hdmi|tv|fan|light)\b/i', $p)
        ) {
            return ['category' => 'Maintenance Office', 'confidence' => 0.92];
        }

        // MIS: network / system / login / database / website / email
        // Guard: if it's clearly about lab access / id card creation, don't mark as MIS.
        if (
            !preg_match('/(internet lab|computer lab|pc lab|lab computer|laboratory computer|id card|student id|school id)/i', $p) &&
            (
                preg_match('/\b(wifi|internet|network|server|portal|website|email|database|lms)\b/i', $p) ||
                preg_match('/\b(login|log in)\b/i', $p) ||
                preg_match('/\b(cannot login|cannot access|network problem|network outage|internet slow|slow connection|disconnect)\b/i', $p) ||
                preg_match('/\b(utp|ethernet|cable|router)\b/i', $p)
            )
        ) {
            return ['category' => 'MIS Office', 'confidence' => 0.92];
        }

        // Cashier: payments / tuition / receipt / billing / refund
        if (
            preg_match('/\b(cashier|payment|pay|tuition|fee|billing|refund|receipt)\b/i', $p) ||
            // Keep "bayad/resibo" as stronger payment signals.
            // "baraydan" can appear in non-payment contexts, so don't treat it as a standalone trigger.
            preg_match('/\b(bayad|resibo|fee clearance)\b/i', $p)
        ) {
            return ['category' => 'Cashier Office', 'confidence' => 0.93];
        }

        // Waray long-queue complaints (service inefficiency)
        if (
            preg_match('/\b(kadamo|kahaba|maiha)\b/i', $p) &&
            preg_match('/\b(pila|linya|line|queue)\b/i', $p) &&
            preg_match('/\b(cashier|bayad|resibo|baraydan)\b/i', $p)
        ) {
            return ['category' => 'Service Inefficiency - cashier office', 'confidence' => 0.95];
        }
        if (
            preg_match('/\b(kadamo|kahaba|maiha)\b/i', $p) &&
            preg_match('/\b(pila|linya|line|queue)\b/i', $p) &&
            preg_match('/\b(registrar|record|records|transcript|tor|cor|enrollment|registration|papel)\b/i', $p)
        ) {
            return ['category' => 'Service Inefficiency - registrar office', 'confidence' => 0.95];
        }

        // Internet Laboratory: lab access / computer lab / id card creation
        if (
            preg_match('/(internet lab|computer lab|pc lab|lab computer|laboratory computer)/i', $p) ||
            preg_match('/\b(id card|student id|school id)\b/i', $p)
        ) {
            return ['category' => 'Internet Laboratory', 'confidence' => 0.90];
        }

        // Faculty: teacher / grading / unfair / rude / poor teaching
        if (
            preg_match('/\b(teacher|professor|instructor|faculty)\b/i', $p) ||
            preg_match('/\b(unfair|rude|late|absent|grading|bias|poor teaching)\b/i', $p)
        ) {
            return ['category' => 'Faculty Office', 'confidence' => 0.88];
        }

        // ISSC: council / club / org / orientation / handbook / event/activity
        if (
            preg_match('/\b(issc)\b/i', $p) ||
            preg_match('/student council|student government|student handbook|handbook|organization|club|orientation|event|activity/i', $p)
        ) {
            return ['category' => 'ISSC Office', 'confidence' => 0.87];
        }

        // IMCO: announcements / memo / notice / dissemination
        if (
            preg_match('/\b(imco)\b/i', $p) ||
            preg_match('/announcement|memo|notice|information|dissemination/i', $p)
        ) {
            return ['category' => 'IMCO Office', 'confidence' => 0.86];
        }

        return null;
    }

    private function classifyConcernPhpFallback($concern_text) {
        static $cache = null;
        $trainingFile = __DIR__ . '/../ml/training_data.csv';
        $detectedCollegeCode = $this->detectCollegeCodePhp($concern_text);

        // High-precision deterministic layer first.
        $rule = $this->classifyConcernPhpRules($concern_text);
        if ($rule) {
            return [$rule['category'], $rule['confidence'], $detectedCollegeCode];
        }

        $trainingMtime = @filemtime($trainingFile);
        if ($cache === null || ($cache['training_mtime'] ?? null) !== $trainingMtime) {
            $cache = [
                'ready' => false,
                'total_docs' => 0,
                'labels' => [],
                'idf' => [],
                'centroids' => [],
                'centroid_norms' => [],
                'training_mtime' => $trainingMtime,
            ];

            if (is_readable($trainingFile)) {
                $docs = []; // each: ['label'=>..., 'tf'=>[ngram=>count], 'total'=>int]
                $df = [];   // document frequency: ngram => count

                if (($fh = fopen($trainingFile, 'r')) !== false) {
                    $header = fgetcsv($fh);
                    $idxText = is_array($header) ? array_search('concern_text', $header) : false;
                    $idxLabel = is_array($header) ? array_search('label', $header) : false;
                    if ($idxText === false) $idxText = 0;
                    if ($idxLabel === false) $idxLabel = 1;

                    while (($row = fgetcsv($fh)) !== false) {
                        $text = $row[$idxText] ?? '';
                        $label = trim((string)($row[$idxLabel] ?? ''));
                        if ($label === '' || !in_array($label, self::OFFICE_CATEGORIES, true)) {
                            continue;
                        }

                        $processed = $this->preprocessText($text);
                        $s = str_replace(' ', '', $processed);
                        $len = strlen($s);
                        if ($len < 3) {
                            continue;
                        }

                        $tf = [];
                        for ($n = 3; $n <= 5; $n++) {
                            if ($len < $n) continue;
                            for ($i = 0; $i + $n <= $len; $i++) {
                                $ng = substr($s, $i, $n);
                                if ($ng === '') continue;
                                $tf[$ng] = ($tf[$ng] ?? 0) + 1;
                            }
                        }

                        if (empty($tf)) continue;

                        $cache['labels'][$label] = true;
                        $cache['total_docs']++;

                        foreach (array_keys($tf) as $ng) {
                            $df[$ng] = ($df[$ng] ?? 0) + 1;
                        }

                        $docs[] = ['label' => $label, 'tf' => $tf, 'total' => array_sum($tf)];
                    }
                    fclose($fh);
                }

                $cache['ready'] = ($cache['total_docs'] > 0 && count($cache['labels']) >= 2);
                if ($cache['ready']) {
                    $cache['labels'] = array_keys($cache['labels']);
                    foreach ($df as $ng => $docFreq) {
                        $cache['idf'][$ng] = log((($cache['total_docs'] + 1) / ($docFreq + 1))) + 1.0;
                    }

                    $labelSums = [];
                    $labelDocCount = [];
                    foreach ($docs as $doc) {
                        $label = $doc['label'];
                        $tf = $doc['tf'];
                        $total = max(1, (int)$doc['total']);

                        $labelDocCount[$label] = ($labelDocCount[$label] ?? 0) + 1;

                        foreach ($tf as $ng => $count) {
                            $idf = $cache['idf'][$ng] ?? 0.0;
                            if ($idf <= 0) continue;
                            $tfNorm = $count / $total;
                            $w = $tfNorm * $idf;
                            if (!isset($labelSums[$label])) $labelSums[$label] = [];
                            $labelSums[$label][$ng] = ($labelSums[$label][$ng] ?? 0.0) + $w;
                        }
                    }

                    $cache['centroids'] = [];
                    $cache['centroid_norms'] = [];
                    foreach ($labelSums as $label => $sumVec) {
                        $docCount = max(1, (int)($labelDocCount[$label] ?? 1));
                        $centroid = [];
                        $norm = 0.0;
                        foreach ($sumVec as $ng => $sumW) {
                            $avgW = $sumW / $docCount;
                            $centroid[$ng] = $avgW;
                            $norm += $avgW * $avgW;
                        }
                        $cache['centroids'][$label] = $centroid;
                        $cache['centroid_norms'][$label] = sqrt($norm);
                    }
                }
            }
        }

        if (empty($cache['ready'])) {
            $text = strtolower((string)$concern_text);
            $keywords = [
                'MIS Office' => ['wifi','internet','network','portal','system','login','cannot','slow','server','website','email','lms'],
                'Cashier Office' => ['cashier','payment','pay','tuition','fee','receipt','refund','billing','bayad','baraydan'],
                'Maintenance Office' => ['comfort room','cr','mabaho','mahugaw','waray tubig','broken','repair','fix','electric','tv','hdmi','fan','light','leak','plumbing'],
                'Registrar Office' => ['registrar','transcript','tor','grades','records','certificate','form 137','form 138','enrollment','registration','schedule','units','subject'],
                'Internet Laboratory' => ['internet lab','computer lab','laboratory','id card','student id','school id','pc','computers','lab computer'],
                'Faculty Office' => ['teacher','professor','instructor','faculty','unfair','grading','rude','late','absent','kabastos','kaisog'],
                'ISSC Office' => ['issc','student council','handbook','organization','club','orientation','event','activity'],
                'IMCO Office' => ['imco','announcement','memo','notice','information','dissemination','update'],
            ];

            $best = 'Registrar Office';
            $bestScore = -1;
            foreach ($keywords as $label => $list) {
                $score = 0;
                foreach ($list as $kw) {
                    if (strpos($text, $kw) !== false) $score++;
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $label;
                }
            }
            $confidence = $bestScore <= 0 ? 0.55 : min(0.95, 0.55 + ($bestScore * 0.07));
            return [$best, $confidence, $detectedCollegeCode];
        }

        $processed = $this->preprocessText($concern_text);
        $s = str_replace(' ', '', $processed);
        $len = strlen($s);
        if ($len < 3) {
            return ['Registrar Office', 0.55, $detectedCollegeCode];
        }

        $inputTf = [];
        for ($n = 3; $n <= 5; $n++) {
            if ($len < $n) continue;
            for ($i = 0; $i + $n <= $len; $i++) {
                $ng = substr($s, $i, $n);
                if ($ng === '') continue;
                $inputTf[$ng] = ($inputTf[$ng] ?? 0) + 1;
            }
        }

        if (empty($inputTf)) {
            return ['Registrar Office', 0.55, $detectedCollegeCode];
        }

        $inputTotal = max(1, array_sum($inputTf));
        $inputWeights = [];
        $inputNormSq = 0.0;
        foreach ($inputTf as $ng => $count) {
            $idf = $cache['idf'][$ng] ?? 0.0;
            if ($idf <= 0) continue;
            $tfNorm = $count / $inputTotal;
            $w = $tfNorm * $idf;
            if ($w <= 0) continue;
            $inputWeights[$ng] = $w;
            $inputNormSq += $w * $w;
        }

        $inputNorm = sqrt($inputNormSq);
        if ($inputNorm <= 0) {
            return ['Registrar Office', 0.55, $detectedCollegeCode];
        }

        $sims = [];
        foreach ($cache['labels'] as $label) {
            $centroid = $cache['centroids'][$label] ?? [];
            $centroidNorm = $cache['centroid_norms'][$label] ?? 0.0;
            if ($centroidNorm <= 0) {
                $sims[$label] = 0.0;
                continue;
            }

            $dot = 0.0;
            foreach ($inputWeights as $ng => $w) {
                if (isset($centroid[$ng])) {
                    $dot += $w * $centroid[$ng];
                }
            }

            $sims[$label] = $dot / ($inputNorm * $centroidNorm);
        }

        if (empty($sims)) {
            return ['Registrar Office', 0.55, $detectedCollegeCode];
        }

        arsort($sims);
        $bestLabel = array_key_first($sims);

        // Confidence via softmax over cosine similarities.
        $temperature = 6.0;
        $sumExp = 0.0;
        $expVals = [];
        foreach ($sims as $label => $sim) {
            $e = exp($sim * $temperature);
            $expVals[$label] = $e;
            $sumExp += $e;
        }
        $probBest = $sumExp > 0 ? ($expVals[$bestLabel] / $sumExp) : 0.0;
        $confidence = max(0.55, min(0.95, 0.55 + ($probBest * 0.40)));

        return [$bestLabel, $confidence, $detectedCollegeCode];
    }

    /**
     * Submit a new concern
     */
    public function submitConcern($user_id, $college_id, $concern_text, $attachment = null, $department_id = null) {
        if (empty($concern_text)) {
            return ['success' => false, 'message' => 'Concern text is required'];
        }
        
        $autoRouteRequested = ($department_id === '__AUTO__' || $department_id === null || $department_id === '');
        if (!$autoRouteRequested && empty($department_id)) {
            return ['success' => false, 'message' => 'Department selection is required (or choose Auto-route by AI)'];
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO concerns (user_id, college_id, concern_text, attachment, status) VALUES (?, ?, ?, ?, 'Pending')");
            $stmt->execute([$user_id, $college_id, $concern_text, $attachment]);
            $concern_id = $this->db->lastInsertId();

            // Classify the concern using SVM (automatic categorization only)
            $classification = $this->classifyConcern($concern_id, $concern_text);

            if ($autoRouteRequested) {
                // Route using predicted category + detected college (if available)
                $this->autoRoute(
                    $concern_id,
                    $classification['category'],
                    $classification['detected_college'] ?? null,
                    $user_id
                );
            } else {
                // Manual routing to selected department
                $this->manualRoute($concern_id, $user_id, null, $department_id);
            }

            return [
                'success' => true,
                'message' => 'Concern submitted successfully',
                'concern_id' => $concern_id,
                'classification' => $classification
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to submit concern: ' . $e->getMessage()];
        }
    }

    /**
     * Classify concern using SVM model
     */
    public function classifyConcern($concern_id, $concern_text) {
        // Call Python SVM script
        $pythonScript = __DIR__ . '/../ml/classify.py';
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'concern_' . $concern_id . '.txt';
        file_put_contents($tempFile, $concern_text);

        // Execute Python script (try python3 first, fallback to python)
        $pythonCmd = 'python3';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $pythonCmd = 'python';
        }
        
        // Check if python3 exists, otherwise use python
        $testCmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'python' : 'python3';
        $testOutput = @shell_exec("$testCmd --version 2>&1");
        if ($testOutput) {
            $pythonCmd = $testCmd;
        }
        
        // Capture STDOUT only. If Python isn't available, output may be empty -> fallback will handle it.
        $command = $pythonCmd . " " . escapeshellarg($pythonScript) . " " . escapeshellarg($tempFile);
        $output = @shell_exec($command);
        
        // Clean up temp file
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }

        // Parse output (expected format: category|confidence|college_code)
        // In case warnings still appear in output, extract the LAST valid pipe-delimited triple.
        // If parsing fails (common when Python is missing / blocked / outputs unexpected text),
        // we must use the PHP fallback classifier.
        $category = null;
        $confidence = 0.5;
        $detected_college_code = null;
        $pythonParsed = false;

        $raw = trim((string)$output);
        if ($raw !== '') {
            if (preg_match_all('/([^|\r\n]+)\|([0-9]+(?:\.[0-9]+)?)\|([^|\r\n]*)/', $raw, $matches, PREG_SET_ORDER) && !empty($matches)) {
                $last = $matches[count($matches) - 1];
                $category = trim($last[1]);
                $confidence = floatval($last[2]);
                $detected_college_code = isset($last[3]) ? trim($last[3]) : null;
                if ($detected_college_code === '') {
                    $detected_college_code = null;
                }
                $pythonParsed = true;
            }
        }

        // If Python didn't produce anything parseable (or produced an unknown category),
        // classify using PHP fallback (trained from CSV).
        if (!$pythonParsed || !in_array($category, self::OFFICE_CATEGORIES, true)) {
            [$category, $confidence, $detectedCollegeCodeFallback] = $this->classifyConcernPhpFallback($concern_text);
            // Only override if Python didn't give us anything.
            if (!$detected_college_code && $detectedCollegeCodeFallback) {
                $detected_college_code = $detectedCollegeCodeFallback;
            }
        }

        // Get college_id from detected college code
        $detected_college_id = null;
        if ($detected_college_code) {
            $stmt = $this->db->prepare("SELECT college_id FROM colleges WHERE college_code = ?");
            $stmt->execute([$detected_college_code]);
            $college = $stmt->fetch();
            if ($college) {
                $detected_college_id = $college['college_id'];
            }
        }

        // Save classification to database (automatic categorization only, no auto-routing)
        try {
            $stmt = $this->db->prepare("INSERT INTO classifications (concern_id, predicted_category, confidence_score) VALUES (?, ?, ?)");
            $stmt->execute([$concern_id, $category, $confidence]);

            // Note: Auto-routing is disabled. Routing is now manual (student selects department)
            // Categories are automatically determined: MIS Office, IMCO Office, Registrar Office, Internet Laboratory, Cashier Office, Maintenance Office, ISSC Office

            return [
                'category' => $category,
                'confidence' => $confidence,
                'detected_college' => $detected_college_code,
                'detected_college_id' => $detected_college_id,
            ];
        } catch (PDOException $e) {
            return [
                'category' => 'Registrar Office',
                'confidence' => 0.5,
                'detected_college' => null,
                'detected_college_id' => null,
            ];
        }
    }

    /**
     * Auto-route concern based on classification and detected college
     */
    private function autoRoute($concern_id, $category, $detected_college_code = null, $routed_by_user_id = null) {
        if ($routed_by_user_id === null) {
            $routed_by_user_id = $_SESSION['user_id'] ?? 1;
        }

        $college_id = null;
        $department_id = null;
        $route_to = null;

        // Optionally store detected college_id for traceability (department routing is category-based).
        if ($detected_college_code) {
            $stmt = $this->db->prepare("SELECT college_id FROM colleges WHERE college_code = ? LIMIT 1");
            $stmt->execute([$detected_college_code]);
            $col = $stmt->fetch();
            if ($col) {
                $college_id = $col['college_id'];
            }
        }

        // Otherwise route to the office department matching the predicted category.
        if (!$department_id) {
            $nameCandidates = [];
            $likeCandidates = [];

            switch ($category) {
                case 'MIS Office':
                    $nameCandidates = ['MIS Office'];
                    $likeCandidates = ['%MIS%'];
                    break;
                case 'IMCO Office':
                    // Some databases may contain "IMC Office" typo.
                    $nameCandidates = ['IMCO Office', 'IMC Office'];
                    $likeCandidates = ['%IMCO%', '%IMC%'];
                    break;
                case 'Registrar Office':
                    $nameCandidates = ['Registrar Office'];
                    $likeCandidates = ['%Registrar%'];
                    break;
                case 'Internet Laboratory':
                    $nameCandidates = ['Internet Laboratory'];
                    $likeCandidates = ['%Internet Laboratory%', '%Internet Lab%', '%Computer Lab%', '%Laboratory%'];
                    break;
                case 'Cashier Office':
                    // Schema may have "Cashiers Office" (plural).
                    $nameCandidates = ['Cashier Office', 'Cashiers Office'];
                    $likeCandidates = ['%Cashier%', '%Cashiers%'];
                    break;
                case 'Service Inefficiency - cashier office':
                    $nameCandidates = ['Cashier Office', 'Cashiers Office'];
                    $likeCandidates = ['%Cashier%', '%Cashiers%'];
                    break;
                case 'Maintenance Office':
                    $nameCandidates = ['Maintenance Office'];
                    $likeCandidates = ['%Maintenance%'];
                    break;
                case 'Service Inefficiency - registrar office':
                    $nameCandidates = ['Registrar Office'];
                    $likeCandidates = ['%Registrar%'];
                    break;
                case 'ISSC Office':
                    $nameCandidates = ['ISSC Office'];
                    $likeCandidates = ['%ISSC%', '%Student Council%'];
                    break;
                case 'Faculty Office':
                    $nameCandidates = ['Faculty Office'];
                    $likeCandidates = ['%Faculty%', '%Instructor%', '%Teacher%'];
                    break;
            }

            foreach ($nameCandidates as $name) {
                $stmt = $this->db->prepare("SELECT department_id, department_name FROM departments WHERE department_name = ? LIMIT 1");
                $stmt->execute([$name]);
                $dept = $stmt->fetch();
                if ($dept) {
                    $department_id = $dept['department_id'];
                    $route_to = $dept['department_name'];
                    break;
                }
            }

            if (!$department_id && !empty($likeCandidates)) {
                foreach ($likeCandidates as $like) {
                    $stmt = $this->db->prepare("SELECT department_id, department_name FROM departments WHERE department_name LIKE ? ORDER BY department_id ASC LIMIT 1");
                    $stmt->execute([$like]);
                    $dept = $stmt->fetch();
                    if ($dept) {
                        $department_id = $dept['department_id'];
                        $route_to = $dept['department_name'];
                        break;
                    }
                }
            }
        }

        if (!$department_id) {
            return;
        }

        // Insert routing record
        $stmt = $this->db->prepare("INSERT INTO routing (concern_id, college_id, department_id, routed_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$concern_id, $college_id, $department_id, $routed_by_user_id]);

        // Notify the student
        $concern = $this->getConcern($concern_id);
        if ($concern && !empty($concern['user_id'])) {
            $route_to_text = $route_to ?: $category;
            $this->createNotification(
                $concern['user_id'],
                "Your concern has been routed to: " . $route_to_text,
                "concern.php?id=$concern_id"
            );
        }
    }

    /**
     * Get all concerns (with filters)
     */
    public function getConcerns($filters = []) {
        $where = [];
        $params = [];
        $joins = [];

        if (isset($filters['user_id'])) {
            $where[] = "c.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (isset($filters['status'])) {
            $where[] = "c.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['college_id'])) {
            $where[] = "c.college_id = ?";
            $params[] = $filters['college_id'];
        }

        // Filter by department_id from routing table
        if (isset($filters['department_id'])) {
            $joins[] = "INNER JOIN routing r ON c.concern_id = r.concern_id";
            $where[] = "r.department_id = ?";
            $params[] = $filters['department_id'];
        }

        $joinClause = !empty($joins) ? implode(" ", $joins) : "";
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT DISTINCT c.*, u.full_name, u.email, col.college_name, cl.predicted_category, cl.confidence_score
                FROM concerns c
                LEFT JOIN users u ON c.user_id = u.user_id
                LEFT JOIN colleges col ON c.college_id = col.college_id
                LEFT JOIN classifications cl ON c.concern_id = cl.concern_id
                $joinClause
                $whereClause
                ORDER BY c.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get single concern details
     */
    public function getConcern($concern_id) {
        $stmt = $this->db->prepare("SELECT c.*, u.full_name, u.email, col.college_name, cl.predicted_category, cl.confidence_score
                                    FROM concerns c
                                    LEFT JOIN users u ON c.user_id = u.user_id
                                    LEFT JOIN colleges col ON c.college_id = col.college_id
                                    LEFT JOIN classifications cl ON c.concern_id = cl.concern_id
                                    WHERE c.concern_id = ?");
        $stmt->execute([$concern_id]);
        return $stmt->fetch();
    }

    /**
     * Update concern status
     */
    public function updateStatus($concern_id, $new_status, $updated_by, $notes = null) {
        try {
            // Get current status
            $concern = $this->getConcern($concern_id);
            $old_status = $concern['status'] ?? 'Pending';

            // Update concern status
            $stmt = $this->db->prepare("UPDATE concerns SET status = ? WHERE concern_id = ?");
            $stmt->execute([$new_status, $concern_id]);

            // Add to status history
            $stmt = $this->db->prepare("INSERT INTO status_history (concern_id, old_status, new_status, updated_by, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$concern_id, $old_status, $new_status, $updated_by, $notes]);

            // Create notification for student
            $this->createNotification($concern['user_id'], "Your concern status has been updated to: $new_status", "concern.php?id=$concern_id");

            return ['success' => true, 'message' => 'Status updated successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()];
        }
    }

    /**
     * Create notification
     */
    private function createNotification($user_id, $message, $link = null) {
        $stmt = $this->db->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $message, $link]);
    }

    /**
     * Get routing information for a concern
     */
    public function getRouting($concern_id) {
        $stmt = $this->db->prepare("SELECT r.*, col.college_name, d.department_name, u.full_name as routed_by_name
                                    FROM routing r
                                    LEFT JOIN colleges col ON r.college_id = col.college_id
                                    LEFT JOIN departments d ON r.department_id = d.department_id
                                    LEFT JOIN users u ON r.routed_by = u.user_id
                                    WHERE r.concern_id = ?
                                    ORDER BY r.routed_at DESC");
        $stmt->execute([$concern_id]);
        return $stmt->fetchAll();
    }

    /**
     * Get status history for a concern
     */
    public function getStatusHistory($concern_id) {
        $stmt = $this->db->prepare("SELECT sh.*, u.full_name as updated_by_name
                                    FROM status_history sh
                                    LEFT JOIN users u ON sh.updated_by = u.user_id
                                    WHERE sh.concern_id = ?
                                    ORDER BY sh.updated_at DESC");
        $stmt->execute([$concern_id]);
        return $stmt->fetchAll();
    }

    /**
     * Manual routing - Route concern to college or department
     */
    public function manualRoute($concern_id, $routed_by, $college_id = null, $department_id = null) {
        try {
            $stmt = $this->db->prepare("INSERT INTO routing (concern_id, college_id, department_id, routed_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$concern_id, $college_id, $department_id, $routed_by]);
            
            // Create notification
            $concern = $this->getConcern($concern_id);
            $route_to = '';
            if ($college_id) {
                $stmt = $this->db->prepare("SELECT college_name FROM colleges WHERE college_id = ?");
                $stmt->execute([$college_id]);
                $college = $stmt->fetch();
                $route_to = $college['college_name'];
            }
            if ($department_id) {
                $stmt = $this->db->prepare("SELECT department_name FROM departments WHERE department_id = ?");
                $stmt->execute([$department_id]);
                $dept = $stmt->fetch();
                $route_to = $dept['department_name'];
            }
            
            $this->createNotification($concern['user_id'], "Your concern has been routed to: $route_to", "concern.php?id=$concern_id");
            
            return ['success' => true, 'message' => 'Concern routed successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to route concern: ' . $e->getMessage()];
        }
    }

    /**
     * Reclassify concern (force re-classification)
     */
    public function reclassifyConcern($concern_id) {
        $concern = $this->getConcern($concern_id);
        if (!$concern) {
            return ['success' => false, 'message' => 'Concern not found'];
        }

        // Delete old classification
        $stmt = $this->db->prepare("DELETE FROM classifications WHERE concern_id = ?");
        $stmt->execute([$concern_id]);

        // Re-classify
        $classification = $this->classifyConcern($concern_id, $concern['concern_text']);
        
        return ['success' => true, 'classification' => $classification];
    }

    /**
     * Get concern statistics
     */
    public function getStatistics($filters = []) {
        $stats = [];
        
        // Build WHERE clause for filters
        $where = [];
        $params = [];
        $joins = [];
        
        if (isset($filters['college_id'])) {
            $where[] = "c.college_id = ?";
            $params[] = $filters['college_id'];
        }
        
        if (isset($filters['department_id'])) {
            $joins[] = "INNER JOIN routing r ON c.concern_id = r.concern_id";
            $where[] = "r.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        $joinClause = !empty($joins) ? implode(" ", $joins) : "";
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Total concerns
        $sql = "SELECT COUNT(DISTINCT c.concern_id) as count FROM concerns c $joinClause $whereClause";
        if (!empty($params)) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } else {
            $stmt = $this->db->query($sql);
        }
        $stats['total'] = $stmt->fetch()['count'];
        
        // By status
        $sql = "SELECT c.status, COUNT(DISTINCT c.concern_id) as count FROM concerns c $joinClause $whereClause GROUP BY c.status";
        if (!empty($params)) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } else {
            $stmt = $this->db->query($sql);
        }
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Today's concerns
        $todayWhere = !empty($where) ? " AND " . implode(" AND ", $where) : "";
        $todayJoin = !empty($joins) ? implode(" ", $joins) : "";
        $sql = "SELECT COUNT(DISTINCT c.concern_id) as count FROM concerns c $todayJoin WHERE DATE(c.created_at) = CURDATE()" . $todayWhere;
        if (!empty($params)) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } else {
            $stmt = $this->db->query($sql);
        }
        $stats['today'] = $stmt->fetch()['count'];
        
        // This week
        $weekWhere = !empty($where) ? " AND " . implode(" AND ", $where) : "";
        $weekJoin = !empty($joins) ? implode(" ", $joins) : "";
        $sql = "SELECT COUNT(DISTINCT c.concern_id) as count FROM concerns c $weekJoin WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" . $weekWhere;
        if (!empty($params)) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } else {
            $stmt = $this->db->query($sql);
        }
        $stats['this_week'] = $stmt->fetch()['count'];
        
        // By category
        $categoryWhere = "";
        $categoryJoin = "";
        if (isset($filters['department_id'])) {
            $categoryJoin = "INNER JOIN routing r ON co.concern_id = r.concern_id";
            $categoryWhere = " AND r.department_id = " . intval($filters['department_id']);
        } elseif (isset($filters['college_id'])) {
            $categoryWhere = " AND co.college_id = " . intval($filters['college_id']);
        }
        $sql = "SELECT cl.predicted_category, COUNT(*) as count 
                FROM classifications cl
                INNER JOIN concerns co ON cl.concern_id = co.concern_id
                $categoryJoin
                WHERE cl.predicted_category IS NOT NULL" . $categoryWhere . "
                GROUP BY cl.predicted_category";
        $stmt = $this->db->query($sql);
        $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return $stats;
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus($concern_ids, $new_status, $updated_by, $notes = null) {
        $success_count = 0;
        $errors = [];
        
        foreach ($concern_ids as $concern_id) {
            $result = $this->updateStatus($concern_id, $new_status, $updated_by, $notes);
            if ($result['success']) {
                $success_count++;
            } else {
                $errors[] = "Concern #$concern_id: " . $result['message'];
            }
        }
        
        return [
            'success' => $success_count > 0,
            'success_count' => $success_count,
            'total' => count($concern_ids),
            'errors' => $errors
        ];
    }

    /**
     * Delete concern (admin only)
     */
    public function deleteConcern($concern_id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM concerns WHERE concern_id = ?");
            $stmt->execute([$concern_id]);
            return ['success' => true, 'message' => 'Concern deleted successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to delete concern: ' . $e->getMessage()];
        }
    }
}

