<!-- Announcements Widget Component -->
<!-- Include this in your dashboard (index.php) to show latest announcements -->

<?php
// Map user role to announcement target audience values used in DB
$audience = $user_role;
if ($user_role === 'Farmer') {
    $audience = 'Farmers';
} elseif ($user_role === 'Buyer') {
    $audience = 'Buyers';
}

// Fetch latest unread announcements for current user
$announcement_widget_query = "
    SELECT a.announcement_id, a.title, a.announcement_type, a.priority, a.created_at, a.deleted_at
    FROM announcements a
    LEFT JOIN announcement_views av ON a.announcement_id = av.announcement_id AND av.user_id = ?
    WHERE a.is_active = 1
    AND (a.target_audience = 'All' OR a.target_audience = ?)
    AND (a.expires_at IS NULL OR a.expires_at > NOW())
    AND av.view_id IS NULL
    AND a.deleted_at IS NULL
    ORDER BY 
        CASE a.priority 
            WHEN 'Urgent' THEN 1
            WHEN 'High' THEN 2
            WHEN 'Medium' THEN 3
            ELSE 4
        END,
        a.created_at DESC
    LIMIT 5
";

$stmt = $conn->prepare($announcement_widget_query);
$stmt->bind_param("is", $user_id, $audience);
$stmt->execute();
$widget_announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<style>
.announcement-widget {
    background: white;
    border-radius: var(--radius-md, 20px);
    padding: 24px;
    box-shadow: var(--shadow-sm, 0 2px 8px rgba(12, 92, 76, 0.08));
}

.announcement-widget h3 {
    color: var(--green-dark, #0c5c4c);
    margin-bottom: 16px;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.announcement-widget-item {
    padding: 12px;
    margin-bottom: 8px;
    border-radius: 12px;
    border: 1px solid var(--border, #e4e7eb);
    transition: all 0.3s;
    cursor: pointer;
    text-decoration: none;
    display: block;
    color: inherit;
}

.announcement-widget-item:hover {
    border-color: var(--green, #1f8a70);
    background: rgba(31, 138, 112, 0.02);
    transform: translateX(4px);
}

.announcement-widget-item.urgent {
    border-left: 3px solid #dc2626;
    background: rgba(239, 68, 68, 0.02);
}

.announcement-widget-title {
    font-weight: 600;
    color: var(--text, #1f2933);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.announcement-widget-meta {
    font-size: 0.85rem;
    color: var(--text-light, #4c5662);
}

.announcement-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-urgent { background: rgba(220, 38, 38, 0.1); color: #dc2626; }
.badge-high { background: rgba(249, 115, 22, 0.1); color: #ea580c; }
.badge-weather { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
.badge-alert { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
.badge-event { background: rgba(236, 72, 153, 0.1); color: #db2777; }

.view-all-announcements {
    display: block;
    text-align: center;
    margin-top: 12px;
    padding: 10px;
    background: rgba(31, 138, 112, 0.05);
    border-radius: 12px;
    text-decoration: none;
    color: var(--green, #1f8a70);
    font-weight: 600;
    transition: all 0.3s;
}

.view-all-announcements:hover {
    background: rgba(31, 138, 112, 0.1);
}

.no-announcements {
    text-align: center;
    padding: 20px;
    color: var(--text-light, #4c5662);
    font-size: 0.9rem;
}
</style>

<div class="announcement-widget">
    <h3>📢 Latest Announcements</h3>
    
    <?php if (empty($widget_announcements)): ?>
        <div class="no-announcements">
            No new announcements
        </div>
    <?php else: ?>
        <?php foreach ($widget_announcements as $widget_ann): ?>
            <a href="announcements.php?view=<?= $widget_ann['announcement_id'] ?>" 
               class="announcement-widget-item <?= $widget_ann['priority'] === 'Urgent' ? 'urgent' : '' ?>">
                <div class="announcement-widget-title">
                    <span><?= htmlspecialchars(substr($widget_ann['title'], 0, 50)) ?><?= strlen($widget_ann['title']) > 50 ? '...' : '' ?></span>
                    <?php if ($widget_ann['priority'] === 'Urgent' || $widget_ann['priority'] === 'High'): ?>
                        <span class="announcement-badge badge-<?= strtolower($widget_ann['priority']) ?>">
                            <?= $widget_ann['priority'] ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="announcement-widget-meta">
                    <span class="announcement-badge badge-<?= strtolower($widget_ann['announcement_type']) ?>">
                        <?= $widget_ann['announcement_type'] ?>
                    </span>
                    • <?= date('M d, h:i A', strtotime($widget_ann['created_at'])) ?>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <a href="announcements.php" class="view-all-announcements">
        View All Announcements →
    </a>
</div>
