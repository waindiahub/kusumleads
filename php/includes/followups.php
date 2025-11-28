<?php
require_once 'config.php';
require_once 'whatsapp_cloud.php';

function scheduleAbandonedFollowUps($db, $hours = null) {
    $threshold = (int)(getSetting('followup_inactivity_hours') ?: ($hours ?: 6));
    $q = $db->query('SELECT id, phone_number, last_incoming_at FROM whatsapp_conversations WHERE status = "open"');
    $rows = $q->fetchAll();
    foreach ($rows as $r) {
        $cid = (int)$r['id'];
        $phone = $r['phone_number'];
        $lastIn = $r['last_incoming_at'] ? strtotime($r['last_incoming_at']) : 0;
        if ($lastIn > 0 && (time() - $lastIn) >= ($threshold * 3600)) {
            $st = $db->prepare('SELECT id FROM whatsapp_messages WHERE conversation_id = ? AND direction = "outgoing" AND timestamp > FROM_UNIXTIME(?) LIMIT 1');
            $st->execute([$cid, $lastIn]);
            if (!$st->fetch()) {
                $leadId = getLeadIdByConversation($db, $cid);
                if ($leadId) {
                    $ins = $db->prepare('INSERT INTO followup_reminders (lead_id, agent_id, reminder_time, reminder_note, status) VALUES (?, (SELECT assigned_agent_id FROM whatsapp_conversations WHERE id = ?), DATE_ADD(NOW(), INTERVAL 5 MINUTE), ?, "pending")');
                    $ins->execute([$leadId, $cid, 'Auto follow-up']);
                }
            }
        }
    }
    return true;
}

function processFollowUps($db) {
    $st = $db->prepare('SELECT fr.id, fr.lead_id, fr.agent_id, l.phone_number FROM followup_reminders fr JOIN leads l ON fr.lead_id = l.id WHERE fr.status = "pending" AND fr.reminder_time <= NOW()');
    $st->execute();
    $rows = $st->fetchAll();
    foreach ($rows as $r) {
        $to = $r['phone_number'];
        $res = whatsappSendTemplate($to, getSetting('followup_template_name') ?: 'follow_up', getSetting('followup_language') ?: 'en_US', []);
        $db->prepare('UPDATE followup_reminders SET status = "completed", completed_at = NOW() WHERE id = ?')->execute([$r['id']]);
    }
    return ['sent' => count($rows)];
}

function getLeadIdByConversation($db, $conversationId) {
    $q = $db->prepare('SELECT lead_id FROM whatsapp_conversations WHERE id = ?');
    $q->execute([$conversationId]);
    $r = $q->fetch();
    return $r ? (int)$r['lead_id'] : null;
}

?>
