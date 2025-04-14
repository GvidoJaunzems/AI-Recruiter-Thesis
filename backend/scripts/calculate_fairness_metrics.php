<?php
/**
 * Script to calculate fairness metrics based on the AI audit log.
 * Can be run manually or scheduled.
 */

// Set appropriate error reporting for script execution
error_reporting(E_ALL);
ini_set('display_errors', 1); // Display errors for CLI execution
ini_set('log_errors', 1); // Log errors as well
// Optionally set a specific log file if needed
// ini_set('error_log', __DIR__ . '/../logs/metric_calculation_errors.log');

require_once dirname(__DIR__) . '/config/database.php';

echo "Starting Fairness Metric Calculation...\n";

$pdo = get_db_connection();
if (!$pdo) {
    die("Error: Could not connect to the database.\n");
}

// Ensure PDO attributes are suitable for script execution
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Get current timestamp for this run
    $calculationTimestamp = date('Y-m-d H:i:s');
    echo "Calculation Timestamp: {$calculationTimestamp}\n";

    // --- Metric 1: Average AI Score per Job --- 
    echo "Calculating: Average AI Score per Job...\n";
    $sqlAvgScore = "
        SELECT 
            job_id, 
            AVG(ai_score) as average_score
        FROM ai_audit_log
        WHERE ai_score IS NOT NULL
        GROUP BY job_id
    ";
    $stmtAvgScore = $pdo->query($sqlAvgScore);
    $avgScores = $stmtAvgScore->fetchAll(PDO::FETCH_ASSOC);

    // Save results
    $stmtInsertAvg = $pdo->prepare(
        "INSERT INTO fairness_monitoring_results (metric_name, job_id, stratum, value, calculation_timestamp) 
         VALUES (:metric, :job_id, :stratum, :value, :ts)"
    );
    foreach ($avgScores as $row) {
        $stmtInsertAvg->execute([
            ':metric' => 'avg_score_per_job',
            ':job_id' => $row['job_id'],
            ':stratum' => null, // No stratum for this simple metric
            ':value' => $row['average_score'],
            ':ts' => $calculationTimestamp
        ]);
        echo " - Saved avg score for Job ID: {$row['job_id']}\n";
    }
    echo "Average score calculation complete.\n";

    // --- Metric 2: Selection Rate per AI Score Band --- 
    echo "Calculating: Selection Rate per AI Score Band...\n";
    // Define score bands (adjust as needed)
    $bands = [];
    for ($i = 0; $i < 100; $i += 10) {
        $bands[] = ['min' => $i, 'max' => $i + 9.99];
    }
    $bands[] = ['min' => 100, 'max' => 100]; // Handle perfect score

    $selectionRates = [];
    $sqlBand = "
        SELECT 
            COUNT(CASE WHEN decision = 'accepted' THEN 1 END) as accepted_count,
            COUNT(CASE WHEN decision = 'rejected' THEN 1 END) as rejected_count
        FROM ai_audit_log
        WHERE ai_score IS NOT NULL 
          AND ai_score >= :min_score 
          AND ai_score <= :max_score
    ";
    $stmtBand = $pdo->prepare($sqlBand);

    $stmtInsertRate = $pdo->prepare(
        "INSERT INTO fairness_monitoring_results (metric_name, job_id, stratum, value, calculation_timestamp) 
         VALUES (:metric, :job_id, :stratum, :value, :ts)"
    );

    foreach ($bands as $band) {
        $min = $band['min'];
        $max = $band['max'];
        $stratumLabel = "{$min}-{$max}";
        if ($min === 100) {
             $stratumLabel = "100";
        }
        
        $stmtBand->bindParam(':min_score', $min);
        $stmtBand->bindParam(':max_score', $max);
        $stmtBand->execute();
        $counts = $stmtBand->fetch(PDO::FETCH_ASSOC);
        
        $accepted = (int)($counts['accepted_count'] ?? 0);
        $rejected = (int)($counts['rejected_count'] ?? 0);
        $total = $accepted + $rejected;
        
        $rate = ($total > 0) ? ($accepted / $total) : null; // Calculate rate, handle division by zero

        echo " - Band {$stratumLabel}: Accepted={$accepted}, Rejected={$rejected}, Total={$total}, Rate=" . ($rate !== null ? number_format($rate * 100, 1) . '%' : 'N/A') . "\n";

        // Save result only if there were applications in this band
        if ($total > 0 && $rate !== null) {
            $stmtInsertRate->execute([
                ':metric' => 'selection_rate_by_score_band',
                ':job_id' => null, // Overall rate, not job-specific
                ':stratum' => $stratumLabel,
                ':value' => $rate,
                ':ts' => $calculationTimestamp
            ]);
        }
    }
    echo "Selection rate calculation complete.\n";

    // --- Metric 3: Selection Rate by Approx. Years of Experience --- 
    echo "Calculating: Selection Rate by Approx. Years of Experience...\n";
    // Define experience bands (adjust as needed)
    $expBands = [
        ['min' => 0, 'max' => 2, 'label' => '0-2 Years'],
        ['min' => 3, 'max' => 5, 'label' => '3-5 Years'],
        ['min' => 6, 'max' => 10, 'label' => '6-10 Years'],
        ['min' => 11, 'max' => 999, 'label' => '10+ Years'], // Use a high max for "10+"
        ['min' => null, 'max' => null, 'label' => 'Unknown/Not Extracted'] // Handle cases where data is missing
    ];
    $sqlExp = "
        SELECT 
            ai_extracted_data,
            decision
        FROM ai_audit_log
        WHERE decision IN ('accepted', 'rejected')
    "; 
    $stmtExp = $pdo->query($sqlExp);
    $expResults = []; // Store counts per band
    foreach($expBands as $label => $bandData) {
        $expResults[$bandData['label']] = ['accepted' => 0, 'rejected' => 0];
    }

    while ($row = $stmtExp->fetch(PDO::FETCH_ASSOC)) {
        $extractedData = json_decode($row['ai_extracted_data'] ?? 'null', true);
        $yearsExp = $extractedData['experience']['total_years_approx'] ?? null;
        $decision = $row['decision'];
        $bandMatched = false;

        if ($yearsExp !== null && is_numeric($yearsExp)) {
            $yearsExp = (int)$yearsExp;
            foreach ($expBands as $band) {
                 if ($band['min'] === null) continue; // Skip the unknown band here
                 if ($yearsExp >= $band['min'] && $yearsExp <= $band['max']) {
                    $expResults[$band['label']][$decision]++;
                    $bandMatched = true;
                    break;
                 }
            }
        }
        // If no band matched (or yearsExp was null), put in Unknown
        if (!$bandMatched) {
             $expResults['Unknown/Not Extracted'][$decision]++;
        }
    }

    // Calculate and save rates
    foreach ($expResults as $label => $counts) {
        $accepted = $counts['accepted'];
        $rejected = $counts['rejected'];
        $total = $accepted + $rejected;
        $rate = ($total > 0) ? ($accepted / $total) : null;
        echo " - Exp Band [{$label}]: Accepted={$accepted}, Rejected={$rejected}, Total={$total}, Rate=" . ($rate !== null ? number_format($rate * 100, 1) . '%' : 'N/A') . "\n";
        
        // Save result only if there were applications in this band
        if ($total > 0 && $rate !== null) {
             $stmtInsertRate->execute([
                ':metric' => 'selection_rate_by_experience',
                ':job_id' => null, // Overall rate
                ':stratum' => $label,
                ':value' => $rate,
                ':ts' => $calculationTimestamp
            ]);
        }
    }
    echo "Selection rate by experience calculation complete.\n";

    // --- Metric 4: Selection Rate by Highest Education Level (AI Guess) ---
    echo "Calculating: Selection Rate by Highest Education Level (AI Guess)...\n";
    $sqlEdu = "
        SELECT 
            ai_extracted_data,
            decision
        FROM ai_audit_log
        WHERE decision IN ('accepted', 'rejected')
    ";
    $stmtEdu = $pdo->query($sqlEdu);
    $eduResults = []; // Store counts per education level
    $possibleLevels = ['high-school', 'associates', 'bachelors', 'masters', 'phd', 'other', 'unknown']; // Include unknown
    foreach($possibleLevels as $level) {
        $eduResults[$level] = ['accepted' => 0, 'rejected' => 0];
    }

    while ($row = $stmtEdu->fetch(PDO::FETCH_ASSOC)) {
        $extractedData = json_decode($row['ai_extracted_data'] ?? 'null', true);
        $eduLevel = $extractedData['education']['highest_level_guess'] ?? 'unknown';
        $eduLevel = strtolower(trim($eduLevel)); // Normalize
        $decision = $row['decision'];

        // Group potentially unexpected values into 'unknown'
        if (!in_array($eduLevel, $possibleLevels)) {
             $eduLevel = 'unknown';
        }
        $eduResults[$eduLevel][$decision]++;
    }

    // Calculate and save rates
    foreach ($eduResults as $level => $counts) {
        $accepted = $counts['accepted'];
        $rejected = $counts['rejected'];
        $total = $accepted + $rejected;
        $rate = ($total > 0) ? ($accepted / $total) : null;
        echo " - Edu Level [{$level}]: Accepted={$accepted}, Rejected={$rejected}, Total={$total}, Rate=" . ($rate !== null ? number_format($rate * 100, 1) . '%' : 'N/A') . "\n";
        
        // Save result only if there were applications for this level
        if ($total > 0 && $rate !== null) {
             $stmtInsertRate->execute([
                ':metric' => 'selection_rate_by_education',
                ':job_id' => null, // Overall rate
                ':stratum' => $level,
                ':value' => $rate,
                ':ts' => $calculationTimestamp
            ]);
        }
    }
    echo "Selection rate by education calculation complete.\n";

    // --- Metric 5: Selection Rate by AI-Inferred Gender ---
    echo "Calculating: Selection Rate by AI-Inferred Gender...\n";
    $sqlGender = "
        SELECT 
            ai_inferred_gender as gender_group, 
            COUNT(CASE WHEN decision = 'accepted' THEN 1 END) as accepted_count,
            COUNT(CASE WHEN decision = 'rejected' THEN 1 END) as rejected_count
        FROM ai_audit_log
        WHERE decision IN ('accepted', 'rejected')
          AND ai_inferred_gender IS NOT NULL
        GROUP BY gender_group
    ";
    $stmtGender = $pdo->query($sqlGender);
    $genderResults = $stmtGender->fetchAll(PDO::FETCH_ASSOC);

    // Calculate and save rates
    foreach ($genderResults as $row) {
        $group = $row['gender_group'];
        $accepted = (int)$row['accepted_count'];
        $rejected = (int)$row['rejected_count'];
        $total = $accepted + $rejected;
        $rate = ($total > 0) ? ($accepted / $total) : null;
        echo " - Gender [{$group}]: Accepted={$accepted}, Rejected={$rejected}, Total={$total}, Rate=" . ($rate !== null ? number_format($rate * 100, 1) . '%' : 'N/A') . "\n";
        
        if ($total > 0 && $rate !== null) {
             $stmtInsertRate->execute([
                ':metric' => 'selection_rate_by_inferred_gender',
                ':job_id' => null, // Overall rate
                ':stratum' => $group,
                ':value' => $rate,
                ':ts' => $calculationTimestamp
            ]);
        }
    }
    echo "Selection rate by inferred gender calculation complete.\n";
    
    // --- Metric 6: Adverse Impact Ratio (AIR) for AI-Inferred Gender ---
    echo "Calculating: Adverse Impact Ratio (AIR) for AI-Inferred Gender...\n";
    $genderRates = [];
    foreach ($genderResults as $row) {
        $group = $row['gender_group'];
        $total = (int)$row['accepted_count'] + (int)$row['rejected_count'];
        if ($total > 0) {
            $genderRates[$group] = (int)$row['accepted_count'] / $total;
        }
    }
    
    // Find highest selection rate (reference group)
    $maxRate = 0;
    foreach($genderRates as $rate) { $maxRate = max($maxRate, $rate); }
    
    $airResults = [];
    if ($maxRate > 0) { // Avoid division by zero if no group has acceptances
        foreach($genderRates as $group => $rate) {
             $air = $rate / $maxRate;
             $airResults[$group] = $air;
             echo " - AIR for Gender [{$group}]: " . number_format($air, 2) . " (Rate: " . number_format($rate*100, 1) . "% / Max Rate: " . number_format($maxRate*100, 1) . "%)\n";
             // Save AIR result
              $stmtInsertRate->execute([
                ':metric' => 'air_inferred_gender',
                ':job_id' => null, // Overall AIR
                ':stratum' => $group,
                ':value' => $air,
                ':ts' => $calculationTimestamp
            ]);
        }
    } else {
        echo " - Skipping AIR calculation: No group had acceptances (max rate is 0).\n";
    }
    echo "AIR calculation for inferred gender complete.\n";

     // --- Metric 7: Selection Rate by AI-Inferred Ethnicity Context ---
    echo "Calculating: Selection Rate by AI-Inferred Ethnicity Context...\n";
    $sqlEthnicity = "
        SELECT 
            ai_inferred_ethnicity_context as ethnicity_group, 
            COUNT(CASE WHEN decision = 'accepted' THEN 1 END) as accepted_count,
            COUNT(CASE WHEN decision = 'rejected' THEN 1 END) as rejected_count
        FROM ai_audit_log
        WHERE decision IN ('accepted', 'rejected')
          AND ai_inferred_ethnicity_context IS NOT NULL
        GROUP BY ethnicity_group
    ";
    $stmtEthnicity = $pdo->query($sqlEthnicity);
    $ethnicityResults = $stmtEthnicity->fetchAll(PDO::FETCH_ASSOC);

    // Calculate and save rates
    foreach ($ethnicityResults as $row) {
        $group = $row['ethnicity_group'];
        $accepted = (int)$row['accepted_count'];
        $rejected = (int)$row['rejected_count'];
        $total = $accepted + $rejected;
        $rate = ($total > 0) ? ($accepted / $total) : null;
        echo " - Ethnicity Context [{$group}]: Accepted={$accepted}, Rejected={$rejected}, Total={$total}, Rate=" . ($rate !== null ? number_format($rate * 100, 1) . '%' : 'N/A') . "\n";
        
        if ($total > 0 && $rate !== null) {
             $stmtInsertRate->execute([
                ':metric' => 'selection_rate_by_inferred_ethnicity',
                ':job_id' => null, // Overall rate
                ':stratum' => $group,
                ':value' => $rate,
                ':ts' => $calculationTimestamp
            ]);
        }
    }
    echo "Selection rate by inferred ethnicity context calculation complete.\n";

    // --- Add More Metrics Here Later (e.g., avg scores per group) ---

    echo "Fairness Metric Calculation finished successfully.\n";

} catch (PDOException $e) {
    echo "Database Error during metric calculation: " . $e->getMessage() . "\n";
    error_log("Metric Calculation DB Error: " . $e->getMessage());
    exit(1); // Exit with error code
} catch (Exception $e) {
    echo "General Error during metric calculation: " . $e->getMessage() . "\n";
    error_log("Metric Calculation General Error: " . $e->getMessage());
    exit(1); // Exit with error code
}

?> 