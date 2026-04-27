<?php
/**
 * Concern Management Handler
 * Handles concern submission, classification, and routing
 */

require_once __DIR__ . '/../config/config.php';

class Concern {
    private $db;
    private const STAGE1_CATEGORIES = [
        'Technical/IT',
        'General Inquiry / School Updates',
        'Academic Concern',
        'Grade Inquiry / Correction',
        'Class Schedule Issue',
        'Sectioning / Enrollment Issue',
        'Registration Issue',
        'ID Replacement',
        'Records Correction',
        'Enrollment Verification',
        'Academic Records Request',
        'Document Request',
        'Financial Concern',
        'Scholarship / Financial Aid',
        'Facility Concern',
        'Sexual / Gender-Based Harassment',
        'Student Misconduct',
        'Student Discipline',
        'Safety & Discipline',
        'Student Affairs Concern',
        'Administrative Concern',
    ];
    private const STAGE2_BY_STAGE1 = [
        'Technical/IT' => ['MIS Office'],
        'General Inquiry / School Updates' => ['IMCO Office'],
        'Academic Concern' => ['Faculty Office', 'Registrar Office'],
        'Grade Inquiry / Correction' => ['Faculty Office', 'Registrar Office'],
        'Class Schedule Issue' => ['Registrar Office'],
        'Sectioning / Enrollment Issue' => ['Registrar Office'],
        'Registration Issue' => ['Registrar Office'],
        'ID Replacement' => ['Registrar Office'],
        'Records Correction' => ['Registrar Office'],
        'Enrollment Verification' => ['Registrar Office'],
        'Academic Records Request' => ['Registrar Office'],
        'Document Request' => ['Registrar Office'],
        'Financial Concern' => ['Cashier Office', 'Accounting Office'],
        'Scholarship / Financial Aid' => ['SAS Office'],
        'Facility Concern' => ['Maintenance Office'],
        'Sexual / Gender-Based Harassment' => ['GAD Office', 'CODI Office'],
        'Student Misconduct' => ['SAS Office'],
        'Student Discipline' => ['SAS Office'],
        'Safety & Discipline' => ['Campus Security Office', 'Student Affairs Office', 'Guidance Office'],
        'Student Affairs Concern' => ['ISSC Office'],
        'Administrative Concern' => ['Registrar Office'],
    ];
    private const OFFICE_CATEGORIES = [
        'MIS Office',
        'IMCO Office',
        'Registrar Office',
        'Internet Laboratory',
        'Cashier Office',
        'Maintenance Office',
        'ISSC Office',
        'Faculty Office',
        'GAD Office',
        'CODI Office',
        'Accounting Office',
        'SAS Office',
        'Campus Security Office',
        'Student Affairs Office',
        'Guidance Office',
        'Service Inefficiency - cashier office',
        'Service Inefficiency - registrar office',
    ];

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Normalize confidence into 0.00..1.00 range.
     * Handles legacy values that might be stored as percentages (e.g. 88 instead of 0.88).
     */
    private function normalizeConfidence($value): float {
        $c = (float)$value;
        if ($c > 1.0) {
            $c = $c / 100.0;
        }
        if ($c < 0.0) {
            $c = 0.0;
        }
        if ($c > 1.0) {
            $c = 1.0;
        }
        return $c;
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
            'di' => 'not',
            'diri' => 'not',
            'sayop' => 'wrong',
            'kulang' => 'missing',
            'hinay' => 'slow',
            'problema' => 'problem',
            'waray tubig' => 'no water',
            'waray internet' => 'no internet',
            'resibo' => 'receipt',
            'baraydan' => 'payment',
            'marka' => 'grade',
            'grado' => 'grade',
            'eskwela' => 'school',
            'eskwelahan' => 'school',
            'kwarta' => 'money',
            'bayad' => 'payment',
            'linya' => 'queue',
            'hulat' => 'wait',
            'dugay' => 'long',
            'guba' => 'broken',
            'naruba' => 'broken',
            'signal' => 'network',
            'magtutdo' => 'teacher',
            'magturutdo' => 'teacher',
            'maestra' => 'teacher',
            'maestro' => 'teacher',
        ];
        // Replace only whole-word tokens to avoid collisions like "creation" containing "cr".
        foreach ($replacements as $k => $v) {
            $text = preg_replace('/\b' . preg_quote((string)$k, '/') . '\b/i', (string)$v, $text);
        }
        // Keep digits (e.g. "form 137") for registrar-related concerns
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
            // Avoid matching "con" inside "cannot": use word-boundary match for token-like keywords.
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
            preg_match('/\b(transcript|tor|diploma)\b/i', $p) ||
            preg_match('/\b(grades|records|academic record|certificate|cor|credentials|wrong section|wrong name|encoded|encoding|grade slip|wrong grade|missing grade|waray grade|sayop nga grado|sayop nga ngaran)\b/i', $p) ||
            preg_match('/\bform\s*137\b/i', $p) ||
            preg_match('/\bform\s*138\b/i', $p) ||
            preg_match('/\b(enrollment|registration|enrolled|petition|schedule|units|subjects?|add|drop|adding|dropping|enrolment|enrol|shift|section|not enrolled subject|enrollment status)\b/i', $p)
        ) {
            return ['category' => 'Registrar Office', 'confidence' => 0.93];
        }

        // Refunds are Accounting-facing.
        if (
            preg_match('/\b(refund|reimburse|reimbursement|return payment|overpayment|sobra nga bayad)\b/i', $p)
        ) {
            return ['category' => 'Accounting Office', 'confidence' => 0.94];
        }

        // Scholarship and financial aid.
        if (
            preg_match('/\b(scholarship|financial aid|grant|scholar|stipend|allowance)\b/i', $p) ||
            preg_match('/\b(iskolar|scholarship concern|waray stipend)\b/i', $p)
        ) {
            return ['category' => 'SAS Office', 'confidence' => 0.93];
        }

        // Maintenance: distinctive facilities / repair / electrical issue terms
        if (
            preg_match('/comfort room|comfort\b/i', $p) ||
            preg_match('/\bcr\b/i', $p) ||
            preg_match('/\b(mabaho|mahugaw|rubat|narubat|waray tubig|no water|init nga room|mapaso|waray suga|waray kuryente)\b/i', $p) ||
            preg_match('/\b(broken|repair|fix|leak|plumbing|electric|electricity|hdmi|tv|fan|light|aircon|window|door|ceiling|flood|kuryente|suga)\b/i', $p)
        ) {
            return ['category' => 'Maintenance Office', 'confidence' => 0.92];
        }

        // Sexual/Gender-based: harassment by instructor/teacher/professor
        if (
            preg_match('/\b(sexual harassment|sexual harassed|sexually harassed|sexual abuse|molest|molested|gender based|gender-based|manyak|malicious touch|inappropriate touch|inappropriate message|catcalling|catcall|rape joke|sexist|misogyn|gin bastos|ginhikap|ginhikapan|malaw ay nga mensahe)\b/i', $p) &&
            preg_match('/\b(instructor|teacher|professor|faculty|sir|maam|magtutdo|maestra|maestro)\b/i', $p)
        ) {
            return ['category' => 'GAD Office', 'confidence' => 0.94];
        }

        // Severe harassment/abuse can be escalated to CODI.
        if (
            preg_match('/\b(sexual harassment|sexual abuse|molest|molested|rape joke|ginhikap|ginhikapan|gin bastos)\b/i', $p)
        ) {
            return ['category' => 'CODI Office', 'confidence' => 0.93];
        }

        // Safety & Discipline: security threat / unsafe environment / guard incidents
        if (
            preg_match('/\b(guard|security|campus security|threat|unsafe|danger|weapon|violence|intruder|trespass|threat between students|unsafe environment)\b/i', $p) ||
            preg_match('/\b(gwardya|seguridad|delikado|panarhog|panghadlok|hadlok|diri na safe|waray safety|ginpanarhog|ginhulga|nahadlok)\b/i', $p)
        ) {
            return ['category' => 'Campus Security Office', 'confidence' => 0.92];
        }

        // Safety & Discipline: student misconduct / discipline cases
        if (
            preg_match('/\b(misconduct|discipline|disciplinary|violation|student misconduct|argument|misunderstanding|verbal conflict|physical fight|fight|fighting|bullying|cyberbullying|harassment between students|harassment|cheating|vandalism)\b/i', $p) ||
            preg_match('/\b(pasaway|away|pagbalasahis|pagdinagko|pangulit|pangabuso|paglalis|panmiminsaray|pakigbais|ginsisigawan|ginkukulit)\b/i', $p)
        ) {
            return ['category' => 'SAS Office', 'confidence' => 0.91];
        }

        // Safety & Discipline: emotional / trauma / counseling concerns
        if (
            preg_match('/\b(emotional distress|emotional|trauma|traumatized|anxiety|depression|stress|panic|mental health|counseling|counselling|guidance)\b/i', $p) ||
            preg_match('/\b(kabaraka|kapoy na gud|nalulumo|na trauma|naguguluhan|nasasakitan|masubo|ginkukulbaan|nababaraka|nababalisaka)\b/i', $p)
        ) {
            return ['category' => 'Guidance Office', 'confidence' => 0.90];
        }

        // MIS: network / system / login / database / website / email
        // Guard: if it's clearly about lab access / ID creation, don't mark as MIS.
        if (
            !preg_match('/(internet lab|computer lab|pc lab|lab computer|laboratory computer|id card|student id|school id|identification card|\bid\b)/i', $p) &&
            (
                preg_match('/\b(wifi|internet|network|server|portal|website|email|database|lms|error|bug|not loading)\b/i', $p) ||
                preg_match('/\b(login|log in)\b/i', $p) ||
                preg_match('/\b(cannot login|cannot access|network problem|network outage|internet slow|slow connection|disconnect|di maka login|diri maka login|waray internet|hinay internet|waray signal|putol putol connection)\b/i', $p) ||
                preg_match('/\b(utp|ethernet|cable|router)\b/i', $p)
            )
        ) {
            return ['category' => 'MIS Office', 'confidence' => 0.92];
        }

        // Cashier: payments / tuition / receipt / billing / refund
        if (
            preg_match('/\b(cashier|payment|pay|tuition|fee|billing|refund|receipt|assessment|downpayment|discount|penalty)\b/i', $p) ||
            // Keep "bayad/resibo" as stronger payment signals.
            // "baraydan" can appear in non-payment contexts, so don't treat it as a standalone trigger.
            preg_match('/\b(bayad|resibo|baraydan|fee clearance|kwarta|utang|balanse|sobra nga bayad|kulang nga bayad)\b/i', $p)
        ) {
            return ['category' => 'Cashier Office', 'confidence' => 0.93];
        }

        // Waray long-queue complaints (service inefficiency)
        if (
            preg_match('/\b(kadamo|kahaba|maiha|dugay)\b/i', $p) &&
            preg_match('/\b(pila|linya|line|queue)\b/i', $p) &&
            preg_match('/\b(cashier|bayad|resibo|baraydan)\b/i', $p)
        ) {
            return ['category' => 'Service Inefficiency - cashier office', 'confidence' => 0.95];
        }
        // Common Waray phrasing: "kadamo pila sa cashier"
        if (preg_match('/\b(kadamo|kahaba|maiha|dugay)\s+(pila|linya)\s+sa\s+(cashier|bayad|resibo|baraydan)\b/i', $p)) {
            return ['category' => 'Service Inefficiency - cashier office', 'confidence' => 0.95];
        }
        if (
            preg_match('/\b(kadamo|kahaba|maiha|dugay)\b/i', $p) &&
            preg_match('/\b(pila|linya|line|queue)\b/i', $p) &&
            preg_match('/\b(registrar|record|records|transcript|tor|cor|enrollment|registration|papel)\b/i', $p)
        ) {
            return ['category' => 'Service Inefficiency - registrar office', 'confidence' => 0.95];
        }
        // Common Waray phrasing: "kadamo pila sa registrar"
        if (preg_match('/\b(kadamo|kahaba|maiha|dugay)\s+(pila|linya)\s+sa\s+(registrar|record|records|enrollment|registration|papel)\b/i', $p)) {
            return ['category' => 'Service Inefficiency - registrar office', 'confidence' => 0.95];
        }

        // Internet Laboratory: lab access / computer lab / ID-related concerns
        if (
            preg_match('/(internet lab|computer lab|pc lab|lab computer|laboratory computer)/i', $p) ||
            preg_match('/\b(id card|student id|school id|identification card|id)\b/i', $p)
        ) {
            return ['category' => 'Internet Laboratory', 'confidence' => 0.90];
        }

        // Faculty: teacher / grading / unfair / rude / poor teaching
        if (
            preg_match('/\b(teacher|professor|instructor|faculty|lesson|discussion|magtutdo|maestra|maestro)\b/i', $p) ||
            preg_match('/\b(unfair|rude|late|absent|grading|bias|poor teaching|attendance|reconsider)\b/i', $p)
        ) {
            return ['category' => 'Faculty Office', 'confidence' => 0.88];
        }

        // ISSC: council / club / org / orientation / handbook / event/activity
        if (
            preg_match('/\b(issc)\b/i', $p) ||
            preg_match('/student council|student government|student handbook|handbook|organization|club|orientation|event|activity|bullying|harassment|guidance|student welfare/i', $p)
        ) {
            return ['category' => 'ISSC Office', 'confidence' => 0.87];
        }

        // IMCO: announcements / memo / notice / dissemination (avoid generic "update")
        if (
            preg_match('/\b(imco)\b/i', $p) ||
            preg_match('/announcement|memo|notice|information|dissemination|calendar|holiday|suspension|school update/i', $p)
        ) {
            return ['category' => 'IMCO Office', 'confidence' => 0.86];
        }

        return null;
    }

    /**
     * Fallback classifier in PHP (works even if Python is missing).
     * Uses TF-IDF cosine similarity over character n-grams trained from `ml/training_data.csv`.
     */
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
                'centroids' => [],        // label => [ngram => avg_tfidf_weight]
                'centroid_norms' => [],  // label => norm
                'training_mtime' => $trainingMtime,
            ];

            if (is_readable($trainingFile)) {
                $docs = []; // each: ['label'=>..., 'tf'=>[ngram=>count], 'total'=>int]
                $df = [];   // document frequency: ngram => count

                // Build document term frequencies for character n-grams.
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
                        // Character n-grams: 3..5
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

                        // Update document frequency once per doc.
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

                    // Compute IDF for all n-grams.
                    foreach ($df as $ng => $docFreq) {
                        // Smooth to avoid division by zero
                        $cache['idf'][$ng] = log((($cache['total_docs'] + 1) / ($docFreq + 1))) + 1.0;
                    }

                    // Build centroid vectors as average tf-idf per label.
                    $labelSums = []; // label => [ngram => sum_tfidf]
                    $labelDocCount = []; // label => docs count

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

        // If training data isn't available, fall back to a broader keyword scorer.
        if (empty($cache['ready'])) {
            $text = strtolower((string)$concern_text);
            $keywords = [
                'MIS Office' => ['wifi','internet','network','portal','system','login','cannot','slow','server','website','email','lms'],
                'Cashier Office' => ['cashier','payment','pay','tuition','fee','receipt','refund','billing','bayad','baraydan'],
                'Maintenance Office' => ['comfort room','cr','mabaho','mahugaw','waray tubig','broken','repair','fix','electric','tv','hdmi','fan','light','leak','plumbing'],
                'GAD Office' => ['sexual harassment','sexual harassed','sexually harassed','sexual abuse','molest','molested','gender based','gender-based','manyak','inappropriate touch','inappropriate message','catcalling','catcall','sexist','misogyn','instructor harassment','teacher harassment','professor harassment'],
                'Campus Security Office' => ['guard','security','campus security','threat','threat between students','unsafe','unsafe environment','danger','weapon','violence','intruder','gwardya','delikado','hadlok','waray safety'],
                'Student Affairs Office' => ['misconduct','discipline','disciplinary','violation','student misconduct','argument','misunderstanding','verbal conflict','physical fight','fight','fighting','bullying','cyberbullying','harassment between students','harassment','cheating','vandalism','pasaway','away','paglalis','pakigbais'],
                'Guidance Office' => ['emotional distress','emotional','trauma','traumatized','anxiety','depression','stress','panic','mental health','counseling','guidance','kabaraka','nalulumo','masubo','ginkukulbaan'],
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

        // Input TF counts for char n-grams.
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

        // Cosine similarity vs each label centroid.
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
        $bestSim = $sims[$bestLabel];

        // Convert similarity scores to a calibrated confidence via softmax.
        // Temperature: higher = more confident on the best category.
        $temperature = 6.0;
        $exp = [];
        $sumExp = 0.0;
        foreach ($sims as $label => $sim) {
            $e = exp($sim * $temperature);
            $exp[$label] = $e;
            $sumExp += $e;
        }
        $probBest = $sumExp > 0 ? ($exp[$bestLabel] / $sumExp) : 0.0;

        // Clamp to a user-friendly range.
        $confidence = max(0.55, min(0.95, 0.55 + ($probBest * 0.40)));
        return [$bestLabel, $confidence, $detectedCollegeCode];
    }

    /**
     * Map model output to Stage 1 hierarchical category.
     */
    private function classifyStage1($predictedCategory, $concernText): string {
        $category = trim((string)$predictedCategory);
        // Text-first classification to avoid misrouting from wrong office predictions.
        $text = $this->preprocessText($concernText);
        // Hard guard: registrar/records concerns should not go to IMCO.
        if (preg_match('/\b(grade inquiry|grade correction|wrong grade|missing grade|waray grade|sayop nga grado|grade reconsider)\b/i', $text)) {
            return 'Grade Inquiry / Correction';
        }
        if (preg_match('/\b(wrong section|sayop nga section|incorrect section|wrong block|wrong class section|sayop section|waray section)\b/i', $text)) {
            return 'Sectioning / Enrollment Issue';
        }
        if (preg_match('/\b(class schedule|schedule issue|wrong schedule|schedule conflict|conflict schedule|no schedule|kulang schedule|schedule ko|waray schedule|nagbabangga schedule)\b/i', $text)) {
            return 'Class Schedule Issue';
        }
        if (preg_match('/\b(not enrolled subject|registration issue|enrollment issue|failed enrollment|not enrolled|waray enrollment)\b/i', $text)) {
            return 'Registration Issue';
        }
        if (preg_match('/\b(student id lost|lost id|nawara id|id replacement|replace id)\b/i', $text)) {
            return 'ID Replacement';
        }
        if (preg_match('/\b(student id wrong info|wrong info id|id wrong name|records correction|sayop nga ngaran)\b/i', $text)) {
            return 'Records Correction';
        }
        if (preg_match('/\b(enrollment status|enrollment verification|verify enrollment|enrolled ba ako)\b/i', $text)) {
            return 'Enrollment Verification';
        }
        if (preg_match('/\b(transcript of records|tor|diploma request|academic records request)\b/i', $text)) {
            return 'Academic Records Request';
        }
        if (preg_match('/\b(certificate request|document request|request certificate|request document)\b/i', $text)) {
            return 'Document Request';
        }
        if (preg_match('/\b(registrar|tor|cor|transcript|record|records|grade encoding|encoded|encoding|enrollment|registration|add|drop|form\s*137|form\s*138|credentials?|document|documents|requirements?|wrong name|papel|waray grade|missing grade|wrong grade|sayop nga ngaran)\b/i', $text)) {
            return 'Administrative Concern';
        }
        if (preg_match('/\b(comfort room|cr|repair|broken|maintenance|leak|plumbing|electric|facility|fan|light|toilet|water|waray tubig|mahugaw|mabaho|guba|naruba|narubat|kuryente|suga|mapaso)\b/i', $text)) {
            return 'Facility Concern';
        }
        if (
            preg_match('/\b(sexual harassment|sexual harassed|sexually harassed|sexual abuse|molest|molested|gender based|gender-based|manyak|malicious touch|inappropriate touch|inappropriate message|catcalling|catcall|rape joke|sexist|misogyn|gin bastos|ginhikap|ginhikapan|malaw ay nga mensahe)\b/i', $text) &&
            preg_match('/\b(instructor|teacher|professor|faculty|sir|maam|magtutdo|maestra|maestro)\b/i', $text)
        ) {
            return 'Sexual / Gender-Based Harassment';
        }
        if (preg_match('/\b(bullying|cyberbullying|student misconduct|pasaway|away|ginkukulit)\b/i', $text)) {
            return 'Student Misconduct';
        }
        if (preg_match('/\b(discipline case|disciplinary|violation|student discipline)\b/i', $text)) {
            return 'Student Discipline';
        }
        if (preg_match('/\b(guard|security|campus security|threat|unsafe|danger|weapon|violence|intruder|student misconduct|misconduct|discipline|disciplinary|violation|argument|misunderstanding|verbal conflict|physical fight|fight|bullying|cyberbullying|harassment|emotional distress|emotional|trauma|anxiety|depression|mental health|guidance|gwardya|delikado|hadlok|pasaway|kabaraka|nalulumo|paglalis|pakigbais|ginpanarhog|ginhulga|nahadlok)\b/i', $text)) {
            return 'Safety & Discipline';
        }
        if (preg_match('/\b(payment|tuition|fee|receipt|refund|cashier|billing|bayad|resibo|baraydan|kwarta|utang|balanse)\b/i', $text)) {
            return 'Financial Concern';
        }
        if (preg_match('/\b(scholarship|financial aid|grant|stipend|allowance|iskolar|waray stipend)\b/i', $text)) {
            return 'Scholarship / Financial Aid';
        }
        if (preg_match('/\b(wifi|internet|network|portal|system|login|website|server|technical|it|email|database|cannot login|di maka login|waray internet|hinay internet|waray signal|putol putol connection)\b/i', $text)) {
            return 'Technical/IT';
        }
        if (preg_match('/\b(faculty|teacher|professor|instructor|grades?|grade computation|grade reconsider|reconsider|lesson|discussion|records?|enrollment|registration|subject|attendance|marka|grado|magtutdo|maestra|maestro)\b/i', $text)) {
            return 'Academic Concern';
        }
        if (preg_match('/\b(issc|student council|student government|organization|club|event|activity|orientation|student affairs)\b/i', $text)) {
            return 'Student Affairs Concern';
        }
        if (preg_match('/\b(registrar|certificate|clearance|document|request|admin|administrative)\b/i', $text)) {
            return 'Administrative Concern';
        }
        if (preg_match('/\b(imco|announcement|memo|notice|school updates?|update)\b/i', $text)) {
            return 'General Inquiry / School Updates';
        }

        // Fallback to office prediction only when text has no strong signal.
        $officeToStage1 = [
            'Faculty Office' => 'Academic Concern',
            'Registrar Office' => 'Academic Concern',
            'Service Inefficiency - registrar office' => 'Academic Concern',
            'Cashier Office' => 'Financial Concern',
            'Service Inefficiency - cashier office' => 'Financial Concern',
            'Maintenance Office' => 'Facility Concern',
            'GAD Office' => 'Sexual / Gender-Based Harassment',
            'CODI Office' => 'Sexual / Gender-Based Harassment',
            'Accounting Office' => 'Financial Concern',
            // Text-first rules above decide specific SAS concerns (scholarship, misconduct, discipline).
            'SAS Office' => 'Student Affairs Concern',
            'Campus Security Office' => 'Safety & Discipline',
            'Student Affairs Office' => 'Safety & Discipline',
            'Guidance Office' => 'Safety & Discipline',
            'MIS Office' => 'Technical/IT',
            'IT Support' => 'Technical/IT',
            'Internet Laboratory' => 'Technical/IT',
            'IMCO Office' => 'General Inquiry / School Updates',
            'ISSC Office' => 'Student Affairs Concern',
        ];
        if (isset($officeToStage1[$category])) {
            return $officeToStage1[$category];
        }

        return 'General Inquiry / School Updates';
    }

    /**
     * Get Stage 2 routing options from Stage 1 category.
     */
    private function getStage2Candidates($stage1Category): array {
        $key = in_array($stage1Category, self::STAGE1_CATEGORIES, true) ? $stage1Category : 'General Inquiry / School Updates';
        return self::STAGE2_BY_STAGE1[$key] ?? self::STAGE2_BY_STAGE1['General Inquiry / School Updates'];
    }

    /**
     * ID-related concerns must pass SAS before Internet Laboratory.
     */
    private function isIdProcessingConcern($concernText): bool {
        $text = $this->preprocessText((string)$concernText);
        return (bool)preg_match('/\b(id|id card|student id|school id|identification card|id processing|id creation|id making|id application|id claim|id release|id issue)\b/i', $text);
    }

    /**
     * Choose best Stage 2 destination from allowed candidates.
     */
    private function selectBestStage2Candidate(array $candidates, $concernText, $predictedCategory = null): string {
        if (empty($candidates)) {
            return 'Help Desk';
        }

        // Follow the first system strictly:
        // Only Academic has multiple Stage 2 options (Faculty, Registrar).
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $text = $this->preprocessText((string)$concernText);
        $predicted = trim((string)$predictedCategory);

        // Academic split (STRICT):
        // - TEACHING/INSTRUCTOR concerns -> Faculty Office
        // - RECORDS/SYSTEM/DOCUMENT concerns -> Registrar Office
        if (in_array('Registrar Office', $candidates, true) && in_array('Faculty Office', $candidates, true)) {
            if (preg_match('/\b(registrar|record|records|transcript|tor|cor|enrollment|registration|encoded|encoding|system record|student record|documents?|credentials?|add|drop|adding|dropping|form\s*137|form\s*138|certificate|clearance|name wrong|wrong section|wrong course|wrong subject|class schedule|schedule issue|wrong schedule|schedule conflict|conflict schedule|schedule ko)\b/i', $text)) {
                return 'Registrar Office';
            }
            if (preg_match('/\b(faculty|teacher|professor|instructor|teaching|lesson|discussion|grading|grade|exam|class|attendance|unfair|bias|reconsider|marka|grado)\b/i', $text)) {
                return 'Faculty Office';
            }
            // If predicted category is strongly academic-specific and no explicit record token, honor faculty.
            if ($predicted === 'Faculty Office') {
                return 'Faculty Office';
            }
            return 'Registrar Office';
        }

        // Financial split:
        // - refunds/reimbursements -> Accounting Office
        // - tuition/payment/receipt -> Cashier Office
        if (in_array('Accounting Office', $candidates, true) && in_array('Cashier Office', $candidates, true)) {
            if (preg_match('/\b(refund|reimburse|reimbursement|return payment|overpayment|sobra nga bayad)\b/i', $text)) {
                return 'Accounting Office';
            }
            return 'Cashier Office';
        }

        // Sexual/Gender-based split:
        // - severe complaint keywords -> CODI Office, otherwise GAD Office
        if (in_array('GAD Office', $candidates, true) && in_array('CODI Office', $candidates, true)) {
            if (preg_match('/\b(sexual abuse|molest|molested|rape joke|ginhikap|ginhikapan)\b/i', $text)) {
                return 'CODI Office';
            }
            return 'GAD Office';
        }

        // Safety & Discipline split:
        // - emotional/trauma/mental health -> Guidance Office
        // - misconduct/discipline/student behavior -> Student Affairs Office
        // - threat/unsafe/guard/security -> Campus Security Office
        if (
            in_array('Campus Security Office', $candidates, true) &&
            in_array('Student Affairs Office', $candidates, true) &&
            in_array('Guidance Office', $candidates, true)
        ) {
            if (preg_match('/\b(emotional distress|emotional|trauma|traumatized|anxiety|depression|stress|panic|mental health|counseling|counselling|guidance|kabaraka|nalulumo|na trauma|masubo|ginkukulbaan)\b/i', $text)) {
                return 'Guidance Office';
            }
            if (preg_match('/\b(misconduct|discipline|disciplinary|violation|student misconduct|argument|misunderstanding|verbal conflict|physical fight|fight|fighting|bullying|cyberbullying|harassment between students|harassment|cheating|vandalism|pasaway|away|pangabuso|paglalis|pakigbais)\b/i', $text)) {
                return 'Student Affairs Office';
            }
            return 'Campus Security Office';
        }

        return $candidates[0];
    }

    /**
     * Normalize office labels from legacy/short names into canonical names.
     */
    private function canonicalizeOfficeName($office): string {
        $raw = trim((string)$office);
        if ($raw === '') {
            return '';
        }

        $key = strtolower($raw);
        $aliases = [
            'instructor' => 'Faculty Office',
            'faculty' => 'Faculty Office',
            'faculty office' => 'Faculty Office',
            'registrar' => 'Registrar Office',
            'registrar office' => 'Registrar Office',
            'gad' => 'GAD Office',
            'gad office' => 'GAD Office',
            'codi' => 'CODI Office',
            'codi office' => 'CODI Office',
            'gad / codi' => 'GAD Office',
            'mis' => 'MIS Office',
            'mis office' => 'MIS Office',
            'imco' => 'IMCO Office',
            'imco office' => 'IMCO Office',
            'cashier' => 'Cashier Office',
            'cashier office' => 'Cashier Office',
            'accounting' => 'Accounting Office',
            'accounting office' => 'Accounting Office',
            'scholarship' => 'SAS Office',
            'scholarship office' => 'SAS Office',
            'maintenance' => 'Maintenance Office',
            'maintenance office' => 'Maintenance Office',
            'issc' => 'ISSC Office',
            'issc office' => 'ISSC Office',
            'guidance' => 'Guidance Office',
            'guidance office' => 'Guidance Office',
            'student affairs' => 'Student Affairs Office',
            'student affairs office' => 'Student Affairs Office',
            'campus security' => 'Campus Security Office',
            'campus security office' => 'Campus Security Office',
            'sas' => 'SAS Office',
            'sas office' => 'SAS Office',
            'internet laboratory' => 'Internet Laboratory',
            'help desk' => 'Help Desk',
        ];

        return $aliases[$key] ?? $raw;
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

            $officeRouteCandidates = $classification['stage2_candidates'] ?? [];
            if (!empty($classification['stage2_selected'])) {
                $officeRouteCandidates = array_values(array_unique(array_merge([$classification['stage2_selected']], $officeRouteCandidates)));
            }
            $officeRouteCandidates = array_values(array_unique(array_map(function ($name) {
                return $this->canonicalizeOfficeName($name);
            }, $officeRouteCandidates)));

            // Enforce ID-processing route flow regardless of manual selection:
            // first SAS Office, then Internet Laboratory.
            if (!empty($classification['requires_sas_flow'])) {
                $this->autoRoute(
                    $concern_id,
                    $classification['category'],
                    $classification['detected_college'] ?? null,
                    $user_id,
                    ['SAS Office']
                );
                $this->autoRoute(
                    $concern_id,
                    $classification['category'],
                    $classification['detected_college'] ?? null,
                    $user_id,
                    ['Internet Laboratory']
                );
                if (!$autoRouteRequested) {
                    // Keep selected school department routing alongside office-category routing.
                    $this->manualRoute($concern_id, $user_id, null, $department_id);
                }
            } elseif ($autoRouteRequested) {
                // Route using predicted category + detected college (if available)
                $this->autoRoute(
                    $concern_id,
                    $classification['category'],
                    $classification['detected_college'] ?? null,
                    $user_id,
                    $officeRouteCandidates
                );
            } else {
                // Manual routing to selected school department
                $this->manualRoute($concern_id, $user_id, null, $department_id);
                // Also route to main office category queue.
                $this->autoRoute(
                    $concern_id,
                    $classification['category'],
                    $classification['detected_college'] ?? null,
                    $user_id,
                    $officeRouteCandidates
                );
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
        
        // Capture STDOUT only. scikit-learn warnings go to STDERR and should not corrupt parsing.
        // If Python is missing/unavailable, output may be empty -> we will fall back to PHP classifier.
        $command = $pythonCmd . " " . escapeshellarg($pythonScript) . " " . escapeshellarg($tempFile);
        $output = @shell_exec($command);
        
        // Clean up temp file
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }

        // Parse output:
        // - category|confidence
        // - category|confidence|college_code
        // In case warnings still appear in output, extract the LAST valid pipe-delimited triple.
        // If parsing fails (common when Python is missing / blocked / outputs unexpected text),
        // we must use the PHP fallback classifier.
        $category = null;
        $confidence = 0.5;
        $detected_college_code = null;
        $pythonParsed = false;

        $raw = trim((string)$output);
        if ($raw !== '') {
            if (preg_match_all('/([^|\r\n]+)\|([0-9]+(?:\.[0-9]+)?)(?:\|([^|\r\n]*))?/', $raw, $matches, PREG_SET_ORDER) && !empty($matches)) {
                $last = $matches[count($matches) - 1];
                $category = trim((string)$last[1]);
                $confidence = floatval($last[2]);
                $detected_college_code = isset($last[3]) ? trim((string)$last[3]) : null;
                if ($detected_college_code === '') {
                    $detected_college_code = null;
                }
                $pythonParsed = ($category !== '');
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

        // Ensure confidence is always consistently stored as 0..1.
        $confidence = $this->normalizeConfidence($confidence);

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

            $stage1Category = $this->classifyStage1($category, $concern_text);
            $stage2Candidates = $this->getStage2Candidates($stage1Category);
            $stage2Selected = $this->selectBestStage2Candidate($stage2Candidates, $concern_text, $category);
            $requiresSasFlow = $this->isIdProcessingConcern($concern_text);

            // Enforce: ID concerns route to SAS first, then Internet Laboratory.
            if ($requiresSasFlow) {
                $stage1Category = 'Administrative Concern';
                $stage2Candidates = ['SAS Office', 'Internet Laboratory'];
                $stage2Selected = 'SAS Office';
            }

            return [
                'category' => $category,
                'stage1_category' => $stage1Category,
                'stage2_candidates' => $stage2Candidates,
                'stage2_selected' => $stage2Selected,
                'requires_sas_flow' => $requiresSasFlow,
                'confidence' => $confidence,
                'detected_college' => $detected_college_code,
                'detected_college_id' => $detected_college_id,
            ];
        } catch (PDOException $e) {
            return [
                'category' => 'Registrar Office',
                'stage1_category' => 'General Inquiry / School Updates',
                'stage2_candidates' => self::STAGE2_BY_STAGE1['General Inquiry / School Updates'],
                'stage2_selected' => 'IMCO Office',
                'requires_sas_flow' => false,
                'confidence' => 0.5,
                'detected_college' => null,
                'detected_college_id' => null,
            ];
        }
    }

    /**
     * Auto-route concern based on classification and detected college
     */
    private function autoRoute($concern_id, $category, $detected_college_code = null, $routed_by_user_id = null, $stage2Candidates = []) {
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

        // Route using Stage 2 candidates from hierarchical classification.
        if (!$department_id) {
            if (empty($stage2Candidates)) {
                $stage2Candidates = ['Help Desk'];
            }

            // Exact name match first.
            foreach ($stage2Candidates as $name) {
                $stmt = $this->db->prepare("SELECT department_id, department_name FROM departments WHERE department_name = ? LIMIT 1");
                $stmt->execute([$name]);
                $dept = $stmt->fetch();
                if ($dept) {
                    $department_id = $dept['department_id'];
                    $route_to = $dept['department_name'];
                    break;
                }

                // If office department does not exist yet, create it on-demand
                // so AI office routing (e.g., SAS/Internet Laboratory) always works.
                if (in_array($name, [
                    'MIS Office',
                    'IMCO Office',
                    'Registrar Office',
                    'Internet Laboratory',
                    'SAS Office',
                    'Cashier Office',
                    'Accounting Office',
                    'Maintenance Office',
                    'ISSC Office',
                    'Faculty Office',
                    'GAD Office',
                    'CODI Office',
                    'Campus Security Office',
                    'Student Affairs Office',
                    'Guidance Office',
                    'Help Desk',
                ], true)) {
                    $desc = $name . " department (auto-created for concern routing)";
                    $ins = $this->db->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
                    $ins->execute([$name, $desc]);
                    $department_id = (int)$this->db->lastInsertId();
                    $route_to = $name;
                    break;
                }
            }

            // Then LIKE-based fallback.
            if (!$department_id && !empty($stage2Candidates)) {
                foreach ($stage2Candidates as $candidate) {
                    $likeCandidates = ['%' . trim((string)$candidate) . '%'];
                    if (strcasecmp((string)$candidate, 'SAS Office') === 0) {
                        $likeCandidates = ['%SAS%', '%OSAS%', '%Student Affairs and Services%'];
                    }

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

                    if ($department_id) {
                        break;
                    }
                }
            }
        }

        if (!$department_id) {
            // Cannot route if departments are missing. (They are usually auto-created in submit_concern.php.)
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
            // Use latest routing record per concern (current destination)
            $joins[] = "INNER JOIN routing r ON c.concern_id = r.concern_id
                        AND r.routed_at = (SELECT MAX(r2.routed_at) FROM routing r2 WHERE r2.concern_id = c.concern_id)";
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
     * Build display-friendly hierarchical classification for UI pages.
     */
    public function getDisplayClassification($concernData, $routing = []) {
        $text = (string)($concernData['concern_text'] ?? '');
        $predictedCategory = trim((string)($concernData['predicted_category'] ?? ''));
        $confidence = isset($concernData['confidence_score']) ? $this->normalizeConfidence($concernData['confidence_score']) : 0.5;

        $stage1Category = $this->classifyStage1($predictedCategory, $text);
        $stage2Candidates = $this->getStage2Candidates($stage1Category);
        $stage2Selected = $this->selectBestStage2Candidate($stage2Candidates, $text, $predictedCategory);
        $stage2Selected = $this->canonicalizeOfficeName($stage2Selected);
        $requiresSasFlow = $this->isIdProcessingConcern($text);

        if ($requiresSasFlow) {
            $stage1Category = 'Administrative Concern';
            $stage2Candidates = ['SAS Office', 'Internet Laboratory'];
            $stage2Selected = 'SAS Office';
        }

        // If routing exists and it is an office-type department, prefer that for Stage 2 display.
        if (!empty($routing) && !empty($routing[0]['department_name'])) {
            $routedDepartment = $this->canonicalizeOfficeName((string)$routing[0]['department_name']);
            $officeLike = [
                'MIS Office',
                'IMCO Office',
                'Registrar Office',
                'Service Inefficiency - registrar office',
                'Internet Laboratory',
                'Cashier Office',
                'Service Inefficiency - cashier office',
                'Maintenance Office',
                'ISSC Office',
                'Faculty Office',
                'GAD Office',
                'CODI Office',
                'Accounting Office',
                'Campus Security Office',
                'Student Affairs Office',
                'Guidance Office',
                'SAS Office',
                'Help Desk',
            ];
            if (in_array($routedDepartment, $officeLike, true)) {
                $stage2Selected = $routedDepartment;
            }
        }

        // Keep display confidence exactly aligned with stored model confidence.
        // Only estimate when no stored confidence is available.
        if ($confidence <= 0.0) {
            $confidence = $this->estimateDisplayConfidence($text, $stage2Selected, 0.5);
        }

        return [
            'stage1_category' => $stage1Category,
            'stage2_selected' => $stage2Selected,
            'stage2_candidates' => $stage2Candidates,
            'category' => $predictedCategory,
            'confidence' => $confidence,
            'requires_sas_flow' => $requiresSasFlow,
        ];
    }

    private function estimateDisplayConfidence($text, $office, $fallback = 0.5): float {
        $t = $this->preprocessText((string)$text);
        $map = [
            'Maintenance Office' => ['comfort room', 'broken', 'repair', 'leak', 'plumbing', 'water', 'dirty', 'mabaho', 'mahugaw', 'fan', 'light'],
            'Cashier Office' => ['payment', 'tuition', 'fee', 'receipt', 'billing', 'refund', 'cashier', 'bayad', 'resibo', 'baraydan'],
            'Accounting Office' => ['refund', 'reimburse', 'reimbursement', 'overpayment', 'return payment', 'sobra nga bayad'],
            'SAS Office' => ['scholarship', 'financial aid', 'grant', 'stipend', 'allowance', 'iskolar'],
            'Registrar Office' => ['registrar', 'transcript', 'tor', 'cor', 'record', 'records', 'enrollment', 'registration', 'add', 'drop', 'form 137', 'form 138'],
            'Faculty Office' => ['teacher', 'professor', 'instructor', 'grading', 'grade', 'lesson', 'discussion', 'attendance', 'exam'],
            'MIS Office' => ['internet', 'network', 'login', 'portal', 'system', 'server', 'website', 'email', 'database', 'error', 'bug'],
            'ISSC Office' => ['issc', 'student council', 'organization', 'event', 'activity', 'orientation', 'bullying', 'harassment'],
            'GAD Office' => ['sexual harassment', 'sexual harassed', 'sexually harassed', 'sexual abuse', 'molest', 'molested', 'gender based', 'gender-based', 'manyak', 'inappropriate touch', 'inappropriate message', 'catcalling', 'catcall', 'sexist', 'misogyn', 'instructor', 'teacher', 'professor'],
            'CODI Office' => ['sexual abuse', 'molest', 'molested', 'rape joke', 'ginhikap', 'ginhikapan', 'gin bastos'],
            'Campus Security Office' => ['guard', 'security', 'threat', 'unsafe', 'danger', 'weapon', 'violence', 'intruder', 'gwardya', 'delikado', 'hadlok'],
            'Student Affairs Office' => ['misconduct', 'discipline', 'disciplinary', 'violation', 'student misconduct', 'fighting', 'cheating', 'vandalism', 'bullying', 'harassment', 'pasaway', 'away'],
            'Guidance Office' => ['emotional', 'trauma', 'traumatized', 'anxiety', 'depression', 'stress', 'panic', 'mental health', 'counseling', 'guidance', 'kabaraka', 'nalulumo'],
            'IMCO Office' => ['announcement', 'memo', 'notice', 'update', 'advisory', 'calendar', 'suspension', 'information'],
            'Internet Laboratory' => ['internet lab', 'computer lab', 'laboratory', 'student id', 'id card'],
            'SAS Office' => ['student id', 'id processing', 'id application', 'id card'],
            'Help Desk' => ['help', 'assist', 'inquiry', 'question'],
        ];

        $keywords = $map[$office] ?? [];
        if (empty($keywords)) {
            return max(0.5, min(0.98, $this->normalizeConfidence($fallback)));
        }

        $hits = 0;
        foreach ($keywords as $kw) {
            if (strpos($t, $kw) !== false) {
                $hits++;
            }
        }

        $score = min(0.98, 0.50 + ($hits * 0.03));
        $fallbackNorm = min(0.98, max(0.5, $this->normalizeConfidence($fallback)));
        return max($score, $fallbackNorm);
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
            // Use latest routing record per concern (current destination)
            $joins[] = "INNER JOIN routing r ON c.concern_id = r.concern_id
                        AND r.routed_at = (SELECT MAX(r2.routed_at) FROM routing r2 WHERE r2.concern_id = c.concern_id)";
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
            // Use latest routing record per concern (current destination)
            $categoryJoin = "INNER JOIN routing r ON co.concern_id = r.concern_id
                              AND r.routed_at = (SELECT MAX(r2.routed_at) FROM routing r2 WHERE r2.concern_id = co.concern_id)";
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

