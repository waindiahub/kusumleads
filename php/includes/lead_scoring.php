<?php
require_once 'config.php';

class LeadScoring {
    
    public static function calculateScore($lead) {
        $score = 50; // Base score
        $factors = [];
        $db = getDB();
        $metroBonus = (int)(getSetting('score_city_bonus') ?: 10);
        $freshBonus = (int)(getSetting('score_fresh_bonus') ?: 15);
        $veryFreshBonus = (int)(getSetting('score_very_fresh_bonus') ?: 25);
        $olderPenalty = (int)(getSetting('score_old_penalty') ?: 10);
        
        // Campaign scoring
        if ($lead['campaign_name']) {
            $highValueCampaigns = ['medical', 'healthcare', 'premium', 'vip'];
            foreach ($highValueCampaigns as $keyword) {
                if (stripos($lead['campaign_name'], $keyword) !== false) {
                    $score += 20;
                    $factors[] = 'High-value campaign';
                    break;
                }
            }
        }
        
        // Time-based scoring (fresher leads score higher)
        $leadAge = time() - strtotime($lead['created_time']);
        $hoursOld = $leadAge / 3600;
        
        if ($hoursOld < 1) {
            $score += $veryFreshBonus;
            $factors[] = 'Very fresh lead';
        } elseif ($hoursOld < 6) {
            $score += $freshBonus;
            $factors[] = 'Fresh lead';
        } elseif ($hoursOld > 48) {
            $score -= $olderPenalty;
            $factors[] = 'Older lead';
        }
        
        // City-based scoring
        $highValueCities = ['mumbai', 'delhi', 'bangalore', 'pune', 'hyderabad', 'chennai'];
        if ($lead['city'] && in_array(strtolower($lead['city']), $highValueCities)) {
            $score += $metroBonus;
            $factors[] = 'Metro city';
        }
        
        // Platform scoring
        if ($lead['platform'] === 'Facebook') {
            $score += 5;
            $factors[] = 'Facebook lead';
        } elseif ($lead['platform'] === 'Google') {
            $score += 10;
            $factors[] = 'Google lead';
        }
        
        // Question response scoring
        if ($lead['question_text']) {
            if (stripos($lead['question_text'], 'yes') !== false || 
                stripos($lead['question_text'], 'interested') !== false) {
                $score += 15;
                $factors[] = 'Positive response';
            }
        }
        
        // Ensure score is within bounds
        $score = max(0, min(100, $score));
        
        // Determine priority level
        $priority = 'medium';
        if ($score >= 80) $priority = 'hot';
        elseif ($score >= 65) $priority = 'high';
        elseif ($score < 40) $priority = 'low';
        
        return [
            'score' => $score,
            'factors' => $factors,
            'priority' => $priority
        ];
    }
    
    public static function updateLeadScore($leadId, $leadData) {
        $db = getDB();
        
        $scoring = self::calculateScore($leadData);
        
        $stmt = $db->prepare("
            UPDATE leads 
            SET lead_score = ?, score_factors = ?, priority_level = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $scoring['score'],
            json_encode($scoring['factors']),
            $scoring['priority'],
            $leadId
        ]);
        
        return $scoring;
    }
    
    public static function scoreAllLeads() {
        $db = getDB();
        
        $stmt = $db->query("SELECT * FROM leads WHERE lead_score = 0 OR lead_score IS NULL LIMIT 100");
        $leads = $stmt->fetchAll();
        
        $updated = 0;
        foreach ($leads as $lead) {
            self::updateLeadScore($lead['id'], $lead);
            $updated++;
        }
        
        return $updated;
    }
}
?>
