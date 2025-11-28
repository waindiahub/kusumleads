<?php
require_once 'config.php';
require_once 'whatsapp_cloud.php';

function runIncomingFlows($db, $conversationId, $payload, $from) {
    $st = $db->query('SELECT id, name, active, definition_json FROM whatsapp_flows WHERE active = 1');
    $flows = $st->fetchAll();
    $text = null;
    if (isset($payload['text']['body'])) $text = $payload['text']['body'];
    $vars = [
        'text' => $text,
        'phone' => $from
    ];
    foreach ($flows as $f) {
        $def = json_decode($f['definition_json'] ?? '[]', true);
        if (!$def || !isset($def['nodes']) || !isset($def['edges'])) continue;
        $nodes = indexById($def['nodes']);
        // If waiting for user input for this conversation, resume
        $waiting = $db->prepare('SELECT id, flow_id, state_json, next_node_id FROM flow_runs WHERE conversation_id = ? AND next_node_id IS NOT NULL AND resume_at IS NULL LIMIT 1');
        $waiting->execute([$conversationId]);
        $w = $waiting->fetch();
        if ($w) {
            $vars = json_decode($w['state_json'] ?? '[]', true) ?: [];
            $vars['user_input'] = $text;
            $edges = $def['edges'] ?? [];
            executeFlowFromNode($db, $conversationId, $nodes, $edges, $w['next_node_id'], $vars);
            $db->prepare('DELETE FROM flow_runs WHERE id = ?')->execute([(int)$w['id']]);
            continue;
        }
        $start = findKeywordTrigger($nodes, $text);
        if (!$start) continue;
        executeFlowFromNode($db, $conversationId, $nodes, $def['edges'], $start, $vars);
    }
}

function indexById($nodes) {
    $map = [];
    foreach ($nodes as $n) { if (isset($n['id'])) $map[$n['id']] = $n; }
    return $map;
}

function findKeywordTrigger($nodes, $text) {
    if (!$text) return null;
    foreach ($nodes as $id => $n) {
        if (($n['type'] ?? '') === 'keyword_trigger') {
            $keywords = $n['config']['keywords'] ?? [];
            foreach ($keywords as $kw) { if (stripos($text, $kw) !== false) return $id; }
        }
    }
    return null;
}

function nextNodeId($edges, $fromId, $vars) {
    foreach ($edges as $e) {
        if (($e['from'] ?? null) === $fromId) {
            return $e['to'] ?? null;
        }
    }
    return null;
}

function executeFlowFromNode($db, $conversationId, $nodes, $edges, $nodeId, $vars) {
    $safe = 0;
    $current = $nodeId;
    while ($current && $safe < 50) {
        $node = $nodes[$current] ?? null;
        if (!$node) break;
        executeNode($db, $conversationId, $node, $vars);
        $current = nextNodeId($edges, $current, $vars);
        $safe++;
    }
}

function executeNode($db, $conversationId, $node, $vars) {
    $type = $node['type'] ?? '';
    if ($type === 'send_text') {
        $to = getConvPhone($db, $conversationId);
        $text = interpolate($node['config']['text'] ?? '', $vars);
        if ($to && $text) {
            $res = whatsappSendText($to, $text);
            $waId = $res['data']['messages'][0]['id'] ?? null;
        insertMessage($db, $conversationId, 'outgoing', 'text', $text, $waId, null, $to, $res['success'] ? 'sent' : 'failed', json_encode($res['data'] ?? []), null);
            notifyConversationUpdate($db, $conversationId, 'outgoing', $res['data'] ?? []);
        }
    } elseif ($type === 'delay') {
        $minutes = (int)($node['config']['minutes'] ?? 5);
        $db->prepare('INSERT INTO flow_runs (conversation_id, flow_id, state_json, next_node_id, resume_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))')
          ->execute([$conversationId, (int)($node['config']['flow_id'] ?? 0), json_encode($vars), $node['config']['next'] ?? null, $minutes]);
        return;
    } elseif ($type === 'send_template') {
        $to = getConvPhone($db, $conversationId);
        $templateId = $node['config']['template_id'] ?? null;
        $language = $node['config']['language'] ?? 'en_US';
        if ($to && $templateId) {
            $stmt = $db->prepare('SELECT * FROM whatsapp_templates WHERE id = ?');
            $stmt->execute([(int)$templateId]);
            $row = $stmt->fetch();
            if ($row) {
                $components = buildTemplateComponentsFromRow($row, $vars);
                $res = whatsappSendTemplate($to, $row['name'], $language, $components);
                $waId = $res['data']['messages'][0]['id'] ?? null;
            insertMessage($db, $conversationId, 'outgoing', 'template', $row['name'], $waId, null, $to, $res['success'] ? 'sent' : 'failed', json_encode($res['data'] ?? []), null);
                notifyConversationUpdate($db, $conversationId, 'outgoing', $res['data'] ?? []);
            }
        }
    } elseif ($type === 'save_variable') {
        $key = $node['config']['key'] ?? null;
        $value = $node['config']['value'] ?? null;
        if ($key) {
            $val = interpolate($value, $vars);
            $db->prepare('INSERT INTO flow_variables (run_id, var_key, var_value) VALUES (0, ?, ?)')->execute([$key, $val]);
            $vars[$key] = $val;
        }
    } elseif ($type === 'tag_add') {
        $tag = $node['config']['tag'] ?? null;
        if ($tag) {
            $db->prepare('INSERT INTO whatsapp_conversation_tags (conversation_id, tag) VALUES (?, ?)')->execute([$conversationId, $tag]);
        }
    } elseif ($type === 'tag_remove') {
        $tag = $node['config']['tag'] ?? null;
        if ($tag) {
            $db->prepare('DELETE FROM whatsapp_conversation_tags WHERE conversation_id = ? AND tag = ?')->execute([$conversationId, $tag]);
        }
    } elseif ($type === 'assign_agent') {
        $agentId = (int)($node['config']['agent_id'] ?? 0);
        if ($agentId > 0) {
            $db->prepare('UPDATE whatsapp_conversations SET assigned_agent_id = ? WHERE id = ?')->execute([$agentId, $conversationId]);
        }
    } elseif ($type === 'condition') {
        $left = interpolate($node['config']['left'] ?? '', $vars);
        $op = $node['config']['op'] ?? 'eq';
        $right = interpolate($node['config']['right'] ?? '', $vars);
        $ok = ($op === 'eq') ? ($left == $right) : ($left != $right);
        if (!$ok) return;
    } elseif ($type === 'jump_to') {
        $target = $node['config']['target'] ?? null;
        if ($target) {
            $flow = $db->prepare('SELECT definition_json FROM whatsapp_flows WHERE id = ?');
            $flow->execute([(int)($node['config']['flow_id'] ?? 0)]);
            $def = $flow->fetch();
            $definition = json_decode($def['definition_json'] ?? '[]', true);
            $map = indexById($definition['nodes'] ?? []);
            $edges = $definition['edges'] ?? [];
            if (isset($map[$target])) executeFlowFromNode($db, $conversationId, $map, $edges, $target, $vars);
        }
        return;
    } elseif ($type === 'api_call') {
        $method = strtoupper($node['config']['method'] ?? 'GET');
        $url = $node['config']['url'] ?? '';
        $saveKey = $node['config']['save_key'] ?? null;
        if (!preg_match('/^https?:\/\//', $url)) return;
        $headers = $node['config']['headers'] ?? [];
        $body = $node['config']['body'] ?? null;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $hdrs = [];
        foreach ($headers as $h) { if (is_string($h)) $hdrs[] = $h; }
        if ($hdrs) curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body)?$body:json_encode($body)); }
        $resp = curl_exec($ch);
        curl_close($ch);
        if ($saveKey) {
            $db->prepare('INSERT INTO flow_variables (run_id, var_key, var_value) VALUES (0, ?, ?)')->execute([$saveKey, $resp]);
            $vars[$saveKey] = $resp;
        }
    } elseif ($type === 'user_input') {
        $expectVar = $node['config']['key'] ?? 'user_input';
        $next = $node['config']['next'] ?? null;
        $db->prepare('INSERT INTO flow_runs (conversation_id, flow_id, state_json, next_node_id) VALUES (?, ?, ?, ?)')
          ->execute([$conversationId, (int)($node['config']['flow_id'] ?? 0), json_encode($vars), $next]);
        return;
    }
}

function processFlowDelays($db) {
    $st = $db->query('SELECT id, conversation_id, flow_id, state_json, next_node_id FROM flow_runs WHERE resume_at IS NOT NULL AND resume_at <= NOW() LIMIT 50');
    $rows = $st->fetchAll();
    foreach ($rows as $r) {
        $vars = json_decode($r['state_json'] ?? '[]', true) ?: [];
        $flow = $db->prepare('SELECT definition_json FROM whatsapp_flows WHERE id = ?');
        $flow->execute([(int)$r['flow_id']]);
        $def = $flow->fetch();
        $definition = json_decode($def['definition_json'] ?? '[]', true);
        $map = indexById($definition['nodes'] ?? []);
        $edges = $definition['edges'] ?? [];
        $startId = $r['next_node_id'] ?? null;
        if ($startId && isset($map[$startId])) {
            executeFlowFromNode($db, (int)$r['conversation_id'], $map, $edges, $startId, $vars);
        }
        $db->prepare('DELETE FROM flow_runs WHERE id = ?')->execute([(int)$r['id']]);
    }
    return ['processed' => count($rows)];
}

function getConvPhone($db, $conversationId) {
    $q = $db->prepare('SELECT phone_number FROM whatsapp_conversations WHERE id = ?');
    $q->execute([$conversationId]);
    $r = $q->fetch();
    return $r ? $r['phone_number'] : null;
}

function interpolate($text, $vars) {
    if (!$text) return $text;
    foreach ($vars as $k => $v) { $text = str_replace('{{'.$k.'}}', $v, $text); }
    return $text;
}

?>
