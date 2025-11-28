<?php
require_once __DIR__ . '/config.php';

function normalizeFilterArray(null|array|string $value): array
{
    if ($value === null) {
        return [];
    }
    $list = is_array($value) ? $value : [$value];
    $clean = [];
    foreach ($list as $item) {
        $item = trim((string)$item);
        if ($item !== '') {
            $clean[] = $item;
        }
    }
    return $clean;
}

function buildAudienceFilter(array $filters): array
{
    $conditions = ["l.phone_number IS NOT NULL"];
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $conditions[] = "(l.full_name LIKE ? OR l.phone_number LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $fieldMap = [
        'campaign' => 'l.campaign_name',
        'city' => 'l.city',
        'form' => 'l.form_name',
        'platform' => 'l.platform',
        'priority' => 'l.priority_level',
        'status' => 'la.status',
    ];

    foreach ($fieldMap as $key => $column) {
        $values = normalizeFilterArray($filters[$key] ?? null);
        if ($values) {
            $conditions[] = sprintf(
                '%s IN (%s)',
                $column,
                implode(',', array_fill(0, count($values), '?'))
            );
            array_push($params, ...$values);
        }
    }

    $tags = normalizeFilterArray($filters['tags'] ?? null);
    if ($tags) {
        $conditions[] = sprintf(
            "EXISTS (
                SELECT 1 FROM whatsapp_conversations wc_tag
                JOIN whatsapp_conversation_tags wct_tag ON wct_tag.conversation_id = wc_tag.id
                WHERE wc_tag.lead_id = l.id
                  AND wct_tag.tag IN (%s)
            )",
            implode(',', array_fill(0, count($tags), '?'))
        );
        array_push($params, ...$tags);
    }

    $scoreMin = isset($filters['score_min']) ? (int)$filters['score_min'] : null;
    if ($scoreMin !== null) {
        $conditions[] = 'l.lead_score >= ?';
        $params[] = $scoreMin;
    }
    $scoreMax = isset($filters['score_max']) ? (int)$filters['score_max'] : null;
    if ($scoreMax !== null) {
        $conditions[] = 'l.lead_score <= ?';
        $params[] = $scoreMax;
    }

    $createdFrom = trim((string)($filters['created_from'] ?? ''));
    if ($createdFrom !== '') {
        $conditions[] = 'DATE(l.created_at) >= ?';
        $params[] = $createdFrom;
    }
    $createdTo = trim((string)($filters['created_to'] ?? ''));
    if ($createdTo !== '') {
        $conditions[] = 'DATE(l.created_at) <= ?';
        $params[] = $createdTo;
    }

    if (!empty($filters['only_unassigned'])) {
        $conditions[] = 'la.lead_id IS NULL';
    }
    if (!empty($filters['only_hot'])) {
        $conditions[] = "l.priority_level IN ('hot','high')";
    }

    $where = $conditions ? implode(' AND ', $conditions) : '1';
    return ['where' => $where, 'params' => $params];
}

