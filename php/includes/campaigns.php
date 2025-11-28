<?php
require_once 'config.php';
require_once 'whatsapp_cloud.php';
require_once 'audience_filters.php';

function calculateDailyQuota($db, $campaign) {
    if ((int)($campaign['warmup_enabled'] ?? 0) === 1) {
        $current = (int)($campaign['current_quota'] ?? 0);
        $base = (int)($campaign['daily_quota'] ?? (getSetting('wa_default_quota') ?: 100));
        if ($current <= 0) $current = max(20, (int)($base / 5));
        $quality = (int)(getSetting('wa_quality_score') ?: 100);
        if ($quality < 50) { $current = max(10, (int)($current / 2)); }
        $next = min($base, $current * 2);
        $db->prepare('UPDATE whatsapp_campaigns SET current_quota = ? WHERE id = ?')->execute([$next, $campaign['id']]);
        return $current;
    }
    $q = (int)($campaign['daily_quota'] ?? 0);
    if ($q > 0) return $q;
    $tier = getSetting('wa_default_quota') ?: 100;
    return (int)$tier;
}

function queueCampaignRecipients($db, $campaignId) {
    $stmt = $db->prepare('SELECT * FROM whatsapp_campaigns WHERE id = ?');
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        return ['success' => false, 'message' => 'Campaign not found'];
    }

    $existing = $db->prepare('SELECT COUNT(*) FROM whatsapp_campaign_recipients WHERE campaign_id = ?');
    $existing->execute([$campaignId]);
    $existingCount = (int)$existing->fetchColumn();
    if ($existingCount > 0) {
        return ['success' => true, 'message' => 'Recipients already prepared', 'data' => ['recipients' => $existingCount]];
    }

    $filters = json_decode($campaign['filters_json'] ?? '[]', true) ?: [];
    $manualNumbers = array_values(array_filter(array_map('trim', (array)($filters['numbers'] ?? []))));
    $audienceFilter = buildAudienceFilter($filters);
    $where = $audienceFilter['where'] ? 'WHERE ' . $audienceFilter['where'] : '';
    $params = $audienceFilter['params'];

    $sql = "SELECT DISTINCT l.id, l.phone_number
        FROM leads l
        LEFT JOIN lead_assignments la ON la.lead_id = l.id
        LEFT JOIN whatsapp_conversations wc ON wc.lead_id = l.id
        LEFT JOIN whatsapp_conversation_tags wct ON wct.conversation_id = wc.id
        $where";
    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
    if (!$rows && !$manualNumbers) {
        return ['success' => false, 'message' => 'No leads match the selected filters'];
    }

    $ins = $db->prepare('INSERT INTO whatsapp_campaign_recipients (campaign_id, lead_id, phone_number) VALUES (?, ?, ?)');
    $seen = [];
    $inserted = 0;
    foreach ($rows as $row) {
        $phone = preg_replace('/\s+/', '', $row['phone_number'] ?? '');
        if (!$phone || isset($seen[$phone])) {
            continue;
        }
        $seen[$phone] = true;
        $ins->execute([$campaignId, $row['id'], $phone]);
        $inserted++;
    }
    foreach ($manualNumbers as $num) {
        $phone = preg_replace('/\s+/', '', $num);
        if (!$phone || isset($seen[$phone])) continue;
        $seen[$phone] = true;
        $ins->execute([$campaignId, null, $phone]);
        $inserted++;
    }

    $db->prepare('UPDATE whatsapp_campaigns SET status = "scheduled" WHERE id = ?')->execute([$campaignId]);
    return ['success' => true, 'message' => 'Audience queued', 'data' => ['recipients' => $inserted]];
}

function dispatchCampaign($db, $campaignId) {
    $stmt = $db->prepare('SELECT * FROM whatsapp_campaigns WHERE id = ?');
    $stmt->execute([$campaignId]);
    $c = $stmt->fetch();
    if (!$c) return ['success' => false, 'message' => 'Not found'];
    $quota = calculateDailyQuota($db, $c);
    $perMin = (int)(getSetting('wa_campaign_per_minute') ?: 30);
    $lang = $c['language_code'] ?? 'en_US';
    $tpl = $c['template_name'] ?? null;
    if (!$tpl) return ['success' => false, 'message' => 'Template missing'];
    $st = $db->prepare('SELECT id, phone_number, attempts FROM whatsapp_campaign_recipients WHERE campaign_id = ? AND status = "queued" LIMIT ?');
    $st->execute([$campaignId, $quota]);
    $recips = $st->fetchAll();
    if (!$recips) {
        $build = queueCampaignRecipients($db, $campaignId);
        if (!$build['success']) {
            return $build;
        }
        $st->execute([$campaignId, $quota]);
        $recips = $st->fetchAll();
    }
    $upd = $db->prepare('UPDATE whatsapp_campaign_recipients SET status = ?, wa_message_id = ? WHERE id = ?');
    $sent = 0;
    $filters = json_decode($c['filters_json'] ?? '[]', true) ?: [];
    $defaultVars = (array)($filters['variables'] ?? []);
    foreach ($recips as $r) {
        $to = $r['phone_number'];
        // Build components from local template table if available
        $row = $db->prepare('SELECT * FROM whatsapp_templates WHERE name = ? LIMIT 1');
        $row->execute([$tpl]);
        $tplRow = $row->fetch();
        if ($tplRow) {
            $components = buildTemplateComponentsFromRow($tplRow, $defaultVars);
            $res = whatsappSendTemplate($to, $tplRow['name'], $lang, $components);
        } else {
            $res = whatsappSendTemplate($to, $tpl, $lang, []);
        }
        $waId = $res['data']['messages'][0]['id'] ?? null;
        $upd->execute([$res['success'] ? 'sent' : 'failed', $waId, $r['id']]);
        $db->prepare('UPDATE whatsapp_campaign_recipients SET attempts = attempts + 1, last_attempt_at = NOW() WHERE id = ?')->execute([$r['id']]);
        $sent++;
    }
    if ($sent > 0) {
        $db->prepare('UPDATE whatsapp_campaigns SET status = "running" WHERE id = ?')->execute([$campaignId]);
    } else {
        $db->prepare('UPDATE whatsapp_campaigns SET status = "completed" WHERE id = ?')->execute([$campaignId]);
    }
    return ['success' => true, 'data' => ['sent' => $sent]];
}

function retryFailedRecipients($db, $campaignId) {
    $st = $db->prepare('SELECT id, phone_number, attempts, last_attempt_at FROM whatsapp_campaign_recipients WHERE campaign_id = ? AND status = "failed"');
    $st->execute([$campaignId]);
    $rows = $st->fetchAll();
    $retryCount = 0;
    foreach ($rows as $r) {
        $wait = 60; // default 1m
        if ((int)$r['attempts'] >= 1) $wait = 300; // 5m
        if ((int)$r['attempts'] >= 3) $wait = 1800; // 30m
        $last = $r['last_attempt_at'] ? strtotime($r['last_attempt_at']) : 0;
        if ($last === 0 || (time() - $last) >= $wait) {
            $db->prepare('UPDATE whatsapp_campaign_recipients SET status = "queued" WHERE id = ?')->execute([(int)$r['id']]);
            $retryCount++;
        }
    }
    return ['queued' => $retryCount];
}

?>
