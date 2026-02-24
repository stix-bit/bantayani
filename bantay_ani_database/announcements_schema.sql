-- Announcements Table
CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    announcement_type ENUM('General', 'Weather', 'System', 'Event', 'Alert') DEFAULT 'General',
    priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
    target_audience ENUM('All', 'Farmers', 'Buyers') DEFAULT 'All',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    is_active TINYINT(1) DEFAULT 1,
    image_path VARCHAR(255) NULL,
    views_count INT DEFAULT 0,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_active (is_active, created_at),
    INDEX idx_audience (target_audience),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Announcement Views Tracking (optional)
CREATE TABLE IF NOT EXISTS announcement_views (
    view_id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_view (announcement_id, user_id),
    INDEX idx_user_views (user_id, viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
