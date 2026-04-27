<?php
/**
 * College and Department Management
 */

require_once __DIR__ . '/../config/config.php';

class College {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Get all colleges
     */
    public function getColleges() {
        $stmt = $this->db->query("SELECT * FROM colleges ORDER BY college_name");
        return $stmt->fetchAll();
    }

    /**
     * Get all departments
     */
    public function getDepartments() {
        $stmt = $this->db->query("SELECT * FROM departments ORDER BY department_name");
        return $stmt->fetchAll();
    }

    /**
     * Get college by ID
     */
    public function getCollege($college_id) {
        $stmt = $this->db->prepare("SELECT * FROM colleges WHERE college_id = ?");
        $stmt->execute([$college_id]);
        return $stmt->fetch();
    }

    /**
     * Get department by ID
     */
    public function getDepartment($department_id) {
        $stmt = $this->db->prepare("SELECT * FROM departments WHERE department_id = ?");
        $stmt->execute([$department_id]);
        return $stmt->fetch();
    }

    /**
     * Get college departments only (CCIS, CCJS, COED, CON, CAT, CEA, COM)
     * These are departments that represent colleges
     * Creates them from colleges table if they don't exist as departments
     * Ensures all 7 colleges are always returned, with no duplicates
     */
    public function getCollegeDepartments() {
        $collegeCodes = ['CCIS', 'CCJS', 'COED', 'CON', 'CAT', 'CEA', 'COM'];
        
        // First, ensure all college departments exist
        $this->ensureCollegeDepartmentsExist();
        
        // Get all departments and filter by college codes
        // Use a more direct SQL query to get exactly one department per college code
        $collegeDepts = [];
        $foundCodes = [];
        
        // Query directly for college departments, one per college code
        foreach ($collegeCodes as $code) {
            $stmt = $this->db->prepare("SELECT * FROM departments 
                                       WHERE department_name LIKE ? 
                                       ORDER BY department_id ASC 
                                       LIMIT 1");
            $stmt->execute(['%' . $code . '%']);
            $dept = $stmt->fetch();
            
            if ($dept) {
                // Only add if we haven't seen this department_id before
                $alreadyAdded = false;
                foreach ($collegeDepts as $existing) {
                    if ($existing['department_id'] == $dept['department_id']) {
                        $alreadyAdded = true;
                        break;
                    }
                }
                
                if (!$alreadyAdded) {
                    $collegeDepts[] = $dept;
                    $foundCodes[] = $code;
                }
            }
        }
        
        // Check if we have all 7 colleges
        $missingCodes = array_diff($collegeCodes, array_unique($foundCodes));
        
        // If any are missing, create them
        if (!empty($missingCodes)) {
            $colleges = $this->getColleges();
            foreach ($colleges as $college) {
                if (in_array($college['college_code'], $missingCodes)) {
                    $deptName = $college['college_name'] . ' (' . $college['college_code'] . ')';
                    
                    // Check if it exists with different name format
                    $stmt = $this->db->prepare("SELECT * FROM departments WHERE department_name LIKE ? LIMIT 1");
                    $stmt->execute(['%' . $college['college_code'] . '%']);
                    $existing = $stmt->fetch();
                    
                    if (!$existing) {
                        // Create department
                        $stmt = $this->db->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
                        $stmt->execute([$deptName, $college['college_name'] . ' department']);
                        $deptId = $this->db->lastInsertId();
                        
                        // Fetch the created department
                        $stmt = $this->db->prepare("SELECT * FROM departments WHERE department_id = ?");
                        $stmt->execute([$deptId]);
                        $newDept = $stmt->fetch();
                        
                        if ($newDept) {
                            $collegeDepts[] = $newDept;
                        }
                    } else {
                        // Check if already added
                        $alreadyAdded = false;
                        foreach ($collegeDepts as $existingDept) {
                            if ($existingDept['department_id'] == $existing['department_id']) {
                                $alreadyAdded = true;
                                break;
                            }
                        }
                        if (!$alreadyAdded) {
                            $collegeDepts[] = $existing;
                        }
                    }
                }
            }
        }
        
        // Final deduplication: Remove duplicates based on department_id
        $uniqueDepts = [];
        $seenIds = [];
        foreach ($collegeDepts as $dept) {
            if (!in_array($dept['department_id'], $seenIds)) {
                $uniqueDepts[] = $dept;
                $seenIds[] = $dept['department_id'];
            }
        }
        $collegeDepts = $uniqueDepts;
        
        // Ensure we have exactly one department per college code
        $finalDepts = [];
        $usedCodes = [];
        foreach ($collegeDepts as $dept) {
            foreach ($collegeCodes as $code) {
                if (stripos($dept['department_name'], $code) !== false && !in_array($code, $usedCodes)) {
                    $finalDepts[] = $dept;
                    $usedCodes[] = $code;
                    break;
                }
            }
        }
        
        // Sort by college code order
        usort($finalDepts, function($a, $b) use ($collegeCodes) {
            $aIndex = 999;
            $bIndex = 999;
            foreach ($collegeCodes as $index => $code) {
                if (stripos($a['department_name'], $code) !== false) $aIndex = $index;
                if (stripos($b['department_name'], $code) !== false) $bIndex = $index;
            }
            return $aIndex - $bIndex;
        });
        
        return $finalDepts;
    }
    
    /**
     * Ensure college departments exist in the database
     */
    private function ensureCollegeDepartmentsExist() {
        $colleges = $this->getColleges();
        $collegeCodes = ['CCIS', 'CCJS', 'COED', 'CON', 'CAT', 'CEA', 'COM'];
        
        foreach ($colleges as $college) {
            if (in_array($college['college_code'], $collegeCodes)) {
                // Check if department exists
                $deptName = $college['college_name'] . ' (' . $college['college_code'] . ')';
                $stmt = $this->db->prepare("SELECT department_id FROM departments WHERE department_name = ? OR department_name LIKE ?");
                $stmt->execute([$deptName, '%' . $college['college_code'] . '%']);
                
                if (!$stmt->fetch()) {
                    // Create department from college
                    $stmt = $this->db->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
                    $stmt->execute([$deptName, $college['college_name'] . ' department']);
                }
            }
        }
    }
    
    /**
     * Create college departments from colleges table if departments don't exist
     */
    private function createCollegeDepartmentsFromColleges() {
        $colleges = $this->getColleges();
        $collegeCodes = ['CCIS', 'CCJS', 'COED', 'CON', 'CAT', 'CEA', 'COM'];
        $departments = [];
        
        foreach ($colleges as $college) {
            if (in_array($college['college_code'], $collegeCodes)) {
                $deptName = $college['college_name'] . ' (' . $college['college_code'] . ')';
                
                // Check if exists, if not create it
                $stmt = $this->db->prepare("SELECT * FROM departments WHERE department_name = ? OR department_name LIKE ?");
                $stmt->execute([$deptName, '%' . $college['college_code'] . '%']);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $departments[] = $existing;
                } else {
                    // Create new department
                    $stmt = $this->db->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
                    $stmt->execute([$deptName, $college['college_name'] . ' department']);
                    $deptId = $this->db->lastInsertId();
                    $departments[] = [
                        'department_id' => $deptId,
                        'department_name' => $deptName,
                        'description' => $college['college_name'] . ' department'
                    ];
                }
            }
        }
        
        // Sort by college code order
        usort($departments, function($a, $b) use ($collegeCodes) {
            $aCode = '';
            $bCode = '';
            foreach ($collegeCodes as $code) {
                if (strpos($a['department_name'], $code) !== false) $aCode = $code;
                if (strpos($b['department_name'], $code) !== false) $bCode = $code;
            }
            $aIndex = array_search($aCode, $collegeCodes);
            $bIndex = array_search($bCode, $collegeCodes);
            return ($aIndex !== false ? $aIndex : 999) - ($bIndex !== false ? $bIndex : 999);
        });
        
        return $departments;
    }
}

