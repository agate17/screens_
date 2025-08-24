-- Create database
CREATE DATABASE IF NOT EXISTS screens_db;
USE screens_db;

-- Create screens table with all required columns including video support
CREATE TABLE screens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    highlight_color VARCHAR(7) NOT NULL DEFAULT '#ffffff',
    photo VARCHAR(255) NOT NULL,
    text TEXT,
    display_time INT NOT NULL DEFAULT 5000,
    display_order INT NOT NULL DEFAULT 1,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    content_type ENUM('image', 'video') NOT NULL DEFAULT 'image',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_display_order (display_order),
    INDEX idx_is_enabled (is_enabled),
    INDEX idx_content_type (content_type)
);

-- If you already have the table, run this ALTER statement to add the content_type column:
-- ALTER TABLE screens ADD COLUMN content_type ENUM('image', 'video') NOT NULL DEFAULT 'image';
-- ALTER TABLE screens ADD INDEX idx_content_type (content_type);
-- ALTER TABLE screens MODIFY COLUMN text TEXT NULL; -- Make text optional for videos

-- Insert sample data
INSERT INTO screens (title, highlight_color, photo, text, display_time, display_order, is_enabled, content_type) VALUES
('Welcome Screen', '#ff6b6b', 'sample1.jpg', 'Welcome to our digital display system!', 5000, 1, 1, 'image'),
('Feature Showcase', '#4ecdc4', 'sample2.jpg', 'Dynamic content management made easy.', 7000, 2, 1, 'image'),
('Customization Demo', '#45b7d1', 'sample3.jpg', 'Customize colors, images, and timing.', 6000, 3, 1, 'image'),
('Sample Video', '#ff9500', 'sample_video.mp4', '', 0, 4, 1, 'video');