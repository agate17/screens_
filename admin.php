<?php
require_once 'config.php';

$message = '';
$messageType = '';

// Handle AJAX requests first (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pdo = getDBConnection();
    
    // Handle AJAX requests that need JSON response
    if ($_POST['action'] === 'update_order') {
        header('Content-Type: application/json');
        
        $orders = json_decode($_POST['orders'] ?? '[]', true);
        if ($orders && is_array($orders)) {
            $pdo->beginTransaction();
            try {
                foreach ($orders as $order) {
                    if (isset($order['id']) && isset($order['order'])) {
                        $stmt = $pdo->prepare("UPDATE screens SET display_order = ? WHERE id = ?");
                        $stmt->execute([$order['order'], $order['id']]);
                    }
                }
                $pdo->commit();
                echo json_encode(['status' => 'success']);
            } catch (Exception $e) {
                $pdo->rollback();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Nederīgi pasūtījuma dati']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'toggle_status') {
        header('Content-Type: text/plain');
        
        $id = intval($_POST['id'] ?? 0);
        $is_enabled = intval($_POST['is_enabled'] ?? 0);
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE screens SET is_enabled = ? WHERE id = ?");
            if ($stmt->execute([$is_enabled, $id])) {
                echo 'success';
            } else {
                echo 'error';
            }
        } else {
            echo 'error';
        }
        exit;
    }
    
    // Handle regular form submissions
    switch ($_POST['action']) {
        case 'add':
            $title = $_POST['title'] ?? '';
            $highlight_color = $_POST['highlight_color'] ?? '#ffffff';
            $text = $_POST['text'] ?? '';
            $display_time = intval($_POST['display_time']) ?? 5000;
            $content_type = $_POST['content_type'] ?? 'image'; // 'image', 'video', or 'leaderboard'
            
            // Handle file upload (image or video)
            $media_filename = '';
            if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = UPLOAD_DIR;
                $file_extension = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
                
                if ($content_type === 'video') {
                    $allowed_extensions = ['mp4', 'webm', 'ogg', 'mov'];
                    $error_message = 'Nederīgs faila tips. Lūdzu, augšupielādējiet MP4, WebM, OGV vai MOV video failus.';
                } else {
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $error_message = 'Nederīgs faila tips. Lūdzu, augšupielādējiet JPG, PNG, GIF vai WebP attēlus.';
                }
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $media_filename = uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $media_filename;
                    
                    if (!move_uploaded_file($_FILES['media']['tmp_name'], $upload_path)) {
                        $message = 'Neizdevās augšupielādēt failu.';
                        $messageType = 'error';
                        break;
                    }
                } else {
                    $message = $error_message;
                    $messageType = 'error';
                    break;
                }
            }
            
            // Validation based on content type
            if ($content_type === 'video') {
                $validation_passed = !empty($media_filename);
                $validation_message = 'Lūdzu, augšupielādējiet video failu.';
            } elseif ($content_type === 'leaderboard') {
                $validation_passed = !empty($title);
                $validation_message = 'Lūdzu, norādiet leaderboard nosaukumu.';
            } else {
                $validation_passed = !empty($media_filename) && !empty($text) && !empty($title);
                $validation_message = 'Lūdzu, norādiet nosaukumu, attēlu un tekstu.';
            }
            
            if ($validation_passed) {
                // Get next display order
                $stmt = $pdo->query("SELECT MAX(display_order) as max_order FROM screens");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $next_order = ($result['max_order'] ?? 0) + 1;
                
                $stmt = $pdo->prepare("INSERT INTO screens (title, highlight_color, photo, text, display_time, display_order, content_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $highlight_color, $media_filename, $text, $display_time, $next_order, $content_type])) {
                    $message = 'Ekrāns veiksmīgi pievienots!';
                    $messageType = 'success';
                } else {
                    $message = 'Neizdevās pievienot ekrānu datubāzē.';
                    $messageType = 'error';
                }
            } else {
                $message = $validation_message;
                $messageType = 'error';
            }
            break;
            
        case 'edit':
            $id = intval($_POST['id']);
            $title = $_POST['title'] ?? '';
            $highlight_color = $_POST['highlight_color'] ?? '#ffffff';
            $text = $_POST['text'] ?? '';
            $display_time = intval($_POST['display_time']) ?? 5000;
            $content_type = $_POST['content_type'] ?? 'image';
            
            // Get current media
            $stmt = $pdo->prepare("SELECT photo, content_type FROM screens WHERE id = ?");
            $stmt->execute([$id]);
            $current_screen = $stmt->fetch(PDO::FETCH_ASSOC);
            $media_filename = $current_screen['photo'] ?? '';
            
            // Handle new file upload
            if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
                $file_extension = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
                
                if ($content_type === 'video') {
                    $allowed_extensions = ['mp4', 'webm', 'ogg', 'mov'];
                    $error_message = 'Nederīgs faila tips. Lūdzu, augšupielādējiet MP4, WebM, OGV vai MOV video failus.';
                } else {
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $error_message = 'Nederīgs faila tips. Lūdzu, augšupielādējiet JPG, PNG, GIF vai WebP attēlus.';
                }
                
                if (in_array($file_extension, $allowed_extensions)) {
                    // Delete old media file
                    if ($media_filename && file_exists(UPLOAD_DIR . $media_filename)) {
                        unlink(UPLOAD_DIR . $media_filename);
                    }
                    
                    $media_filename = uniqid() . '.' . $file_extension;
                    $upload_path = UPLOAD_DIR . $media_filename;
                    
                    if (!move_uploaded_file($_FILES['media']['tmp_name'], $upload_path)) {
                        $message = 'Neizdevās augšupielādēt jaunu failu.';
                        $messageType = 'error';
                        break;
                    }
                } else {
                    $message = $error_message;
                    $messageType = 'error';
                    break;
                }
            }
            
            $stmt = $pdo->prepare("UPDATE screens SET title = ?, highlight_color = ?, photo = ?, text = ?, display_time = ?, content_type = ? WHERE id = ?");
            if ($stmt->execute([$title, $highlight_color, $media_filename, $text, $display_time, $content_type, $id])) {
                $message = 'Ekrāns veiksmīgi atjaunināts!';
                $messageType = 'success';
            } else {
                $message = 'Neizdevās atjaunināt ekrānu.';
                $messageType = 'error';
            }
            break;
            
        case 'delete':
            $id = intval($_POST['id']);
            
            // Get media filename before deleting
            $stmt = $pdo->prepare("SELECT photo FROM screens WHERE id = ?");
            $stmt->execute([$id]);
            $screen = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($screen && !empty($screen['photo'])) {
                $media_path = UPLOAD_DIR . $screen['photo'];
                if (file_exists($media_path)) {
                    unlink($media_path);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM screens WHERE id = ?");
            if ($stmt->execute([$id])) {
                $message = 'Ekrāns veiksmīgi dzēsts!';
                $messageType = 'success';
            } else {
                $message = 'Neizdevās dzēst ekrānu.';
                $messageType = 'error';
            }
            break;
    }
}

// Fetch all screens
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM screens ORDER BY display_order ASC, id ASC");
    $screens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $screens = [];
    $message = "Datubāzes kļūda: " . $e->getMessage();
    $messageType = 'error';
}

// Handle edit mode
$editScreen = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    foreach ($screens as $screen) {
        if ($screen['id'] == $editId) {
            $editScreen = $screen;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrācijas panelis - Ekrānu pārvaldība</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            margin-bottom: 30px;
            border-radius: 10px;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .nav-links {
            margin-top: 15px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            padding: 8px 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover {
            background-color: rgba(255,255,255,0.2);
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.8em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        .form-group input[type="text"],
        .form-group input[type="color"],
        .form-group input[type="number"],
        .form-group input[type="file"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .color-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .color-input-group input[type="color"] {
            width: 60px;
            height: 40px;
            padding: 0;
            border: none;
            cursor: pointer;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .btn-primary {
            background-color: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background-color: #5a67d8;
        }

        .btn-success {
            background-color: #48bb78;
            color: white;
        }

        .btn-success:hover {
            background-color: #38a169;
        }

        .btn-danger {
            background-color: #f56565;
            color: white;
        }

        .btn-danger:hover {
            background-color: #e53e3e;
        }

        .btn-secondary {
            background-color: #718096;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #4a5568;
        }

        .screens-list {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .screen-item {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .screen-item:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .screen-preview {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .screen-image {
            width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            flex-shrink: 0;
        }

        .screen-video {
            width: 200px;
            height: 150px;
            border-radius: 8px;
            flex-shrink: 0;
        }

        .leaderboard-preview {
            width: 200px;
            height: 150px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 8px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            flex-direction: column;
        }

        .screen-details {
            flex: 1;
        }

        .screen-details h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .screen-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 10px;
            font-size: 14px;
            color: #666;
        }

        .color-preview {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 5px;
            vertical-align: middle;
        }

        .screen-actions {
            margin-top: 15px;
        }

        .content-type-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .content-type-btn {
            padding: 10px 20px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .content-type-btn.active {
            border-color: #667eea;
            background-color: #667eea;
            color: white;
        }

        .conditional-field {
            transition: opacity 0.3s ease;
        }

        .conditional-field.hidden {
            display: none;
        }

        .media-type-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }

        .media-type-image {
            background-color: #e3f2fd;
            color: #1565c0;
        }

        .media-type-video {
            background-color: #fce4ec;
            color: #c2185b;
        }

        .media-type-leaderboard {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }

        @media (max-width: 768px) {
            .screen-preview {
                flex-direction: column;
            }
            
            .screen-image,
            .screen-video,
            .leaderboard-preview {
                width: 100%;
                height: 200px;
            }
            
            .screen-meta {
                flex-direction: column;
                gap: 5px;
            }

            .content-type-toggle {
                flex-direction: column;
            }
        }

        .sortable-list {
            list-style: none;
            padding: 0;
        }

        .screen-item {
            cursor: move;
            user-select: none;
            position: relative;
        }

        .screen-item.dragging {
            opacity: 0.5;
            transform: rotate(5deg);
        }

        .screen-item::before {
            content: '⋮⋮';
            position: absolute;
            left: -30px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #999;
            line-height: 0.5;
        }

        .status-toggle {
            position: absolute;
            top: 15px;
            right: 15px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #48bb78;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .screen-item.disabled {
            opacity: 0.5;
            background-color: #f8f8f8;
        }

        .drag-indicator {
            text-align: center;
            color: #999;
            font-style: italic;
            margin-bottom: 20px;
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Ekrānu pārvaldības panelis</h1>
            <p>Pārvaldiet savus dinamiskos displeja ekrānus</p>
            <div class="nav-links">
                <a href="index.php" target="_blank">Skatīt displeju</a>
                <a href="players.php">🏆 Spēlētāju pārvaldība</a>
                <a href="admin.php">Atsvaidzināt</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h2><?php echo $editScreen ? 'Rediģēt ekrānu' : 'Pievienot jaunu ekrānu'; ?></h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $editScreen ? 'edit' : 'add'; ?>">
                <?php if ($editScreen): ?>
                    <input type="hidden" name="id" value="<?php echo $editScreen['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Satura tips:</label>
                    <div class="content-type-toggle">
                        <button type="button" class="content-type-btn <?php echo (!$editScreen || ($editScreen['content_type'] ?? 'image') === 'image') ? 'active' : ''; ?>" onclick="selectContentType('image')">
                            🖼️ Attēls + Teksts
                        </button>
                        <button type="button" class="content-type-btn <?php echo ($editScreen && ($editScreen['content_type'] ?? 'image') === 'video') ? 'active' : ''; ?>" onclick="selectContentType('video')">
                            🎥 Video
                        </button>
                        <button type="button" class="content-type-btn <?php echo ($editScreen && ($editScreen['content_type'] ?? 'image') === 'leaderboard') ? 'active' : ''; ?>" onclick="selectContentType('leaderboard')">
                            🏆 Leaderboard
                        </button>
                    </div>
                    <input type="hidden" id="content_type" name="content_type" value="<?php echo $editScreen ? ($editScreen['content_type'] ?? 'image') : 'image'; ?>">
                </div>
                
                <div class="form-group conditional-field" id="title_field">
                    <label for="title">Ekrāna nosaukums:</label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           value="<?php echo $editScreen ? htmlspecialchars($editScreen['title']) : ''; ?>" 
                           placeholder="Ievadiet ekrāna nosaukumu...">
                </div>
                
                <div class="form-group conditional-field" id="color_field">
                    <label for="highlight_color">Izceļuma krāsa:</label>
                    <div class="color-input-group">
                        <input type="color" 
                               id="highlight_color" 
                               name="highlight_color" 
                               value="<?php echo $editScreen ? htmlspecialchars($editScreen['highlight_color']) : '#ffffff'; ?>" 
                               required>
                        <input type="text" 
                               id="color_hex" 
                               value="<?php echo $editScreen ? htmlspecialchars($editScreen['highlight_color']) : '#ffffff'; ?>" 
                               readonly>
                    </div>
                </div>

                <div class="form-group conditional-field" id="media_field">
                    <label for="media" id="media_label">Fotoattēls:</label>
                    <input type="file" 
                           id="media" 
                           name="media" 
                           accept="image/*">
                    <?php if ($editScreen && !empty($editScreen['photo'])): ?>
                        <p style="margin-top: 10px; color: #666;">Pašreizējais fails: <?php echo htmlspecialchars($editScreen['photo']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group conditional-field" id="text_field">
                    <label for="text">Teksta saturs:</label>
                    <textarea id="text" 
                              name="text" 
                              placeholder="Ievadiet tekstu, kas tiks rādīts šajā ekrānā..."><?php echo $editScreen ? htmlspecialchars($editScreen['text']) : ''; ?></textarea>
                </div>

                <div class="form-group conditional-field" id="time_field">
                    <label for="display_time">Parādīšanas laiks (milisekundes):</label>
                    <input type="number" 
                           id="display_time" 
                           name="display_time" 
                           min="1000" 
                           max="60000" 
                           step="100" 
                           value="<?php echo $editScreen ? $editScreen['display_time'] : '5000'; ?>" 
                           required>
                    <small style="color: #666; display: block; margin-top: 5px;">1000ms = 1 sekunde</small>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?php echo $editScreen ? 'Atjaunināt ekrānu' : 'Pievienot ekrānu'; ?>
                </button>
                
                <?php if ($editScreen): ?>
                    <a href="admin.php" class="btn btn-secondary">Atcelt</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="screens-list">
            <h2>Esošie ekrāni (<?php echo count($screens); ?>) - <?php echo count(array_filter($screens, function($s) { return $s['is_enabled']; })); ?> aktīvi</h2>
            
            <?php if (empty($screens)): ?>
                <p style="color: #666; text-align: center; padding: 40px;">Vēl nav izveidoti ekrāni. Pievienojiet savu pirmo ekrānu, izmantojot augstāk esošo formu.</p>
            <?php else: ?>
                <div class="drag-indicator">
                    💡 Velciet ekrānus uz augšu un uz leju, lai mainītu to rādīšanas secību. Izmantojiet slēdžus, lai iespējotu/atspējotu ekrānus.
                </div>
                
                <ul class="sortable-list" id="screensList">
                    <?php foreach ($screens as $screen): ?>
                        <li class="screen-item <?php echo !$screen['is_enabled'] ? 'disabled' : ''; ?>" data-id="<?php echo $screen['id']; ?>">
                            <div class="status-toggle">
                                <label class="toggle-switch">
                                    <input type="checkbox" 
                                           <?php echo $screen['is_enabled'] ? 'checked' : ''; ?>
                                           onchange="toggleScreenStatus(<?php echo $screen['id']; ?>, this.checked)">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="screen-preview">
                                <?php 
                                $content_type = $screen['content_type'] ?? 'image';
                                if ($content_type === 'leaderboard'): 
                                ?>
                                    <div class="leaderboard-preview" style="border: 3px solid <?php echo htmlspecialchars($screen['highlight_color']); ?>;">
                                        <div>🏆</div>
                                        <div>TOP 10</div>
                                        <div>PLAYERS</div>
                                    </div>
                                <?php elseif (!empty($screen['photo']) && file_exists(UPLOAD_DIR . $screen['photo'])): ?>
                                    <?php 
                                    $file_extension = strtolower(pathinfo($screen['photo'], PATHINFO_EXTENSION));
                                    $is_video = in_array($file_extension, ['mp4', 'webm', 'ogg', 'mov']);
                                    ?>
                                    
                                    <?php if ($is_video): ?>
                                        <video class="screen-video" 
                                               style="border: 3px solid <?php echo htmlspecialchars($screen['highlight_color']); ?>;"
                                               controls
                                               muted>
                                            <source src="<?php echo UPLOAD_DIR . htmlspecialchars($screen['photo']); ?>" type="video/<?php echo $file_extension === 'mov' ? 'quicktime' : $file_extension; ?>">
                                            Your browser does not support the video tag.
                                        </video>
                                    <?php else: ?>
                                        <img src="<?php echo UPLOAD_DIR . htmlspecialchars($screen['photo']); ?>" 
                                             alt="Ekrāna attēls" 
                                             class="screen-image"
                                             style="border: 3px solid <?php echo htmlspecialchars($screen['highlight_color']); ?>;">
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="screen-image" style="background-color: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;">
                                        Nav faila
                                    </div>
                                <?php endif; ?>
                                
                                <div class="screen-details">
                                    <h3>
                                        <?php echo htmlspecialchars($screen['title'] ?? 'Bez nosaukuma'); ?>
                                        <span class="media-type-indicator <?php 
                                            $type = $screen['content_type'] ?? 'image';
                                            echo $type === 'video' ? 'media-type-video' : ($type === 'leaderboard' ? 'media-type-leaderboard' : 'media-type-image'); 
                                        ?>">
                                            <?php 
                                            $type = $screen['content_type'] ?? 'image';
                                            echo $type === 'video' ? '🎥 VIDEO' : ($type === 'leaderboard' ? '🏆 LEADERBOARD' : '🖼️ ATTĒLS'); 
                                            ?>
                                        </span>
                                        <?php if (!$screen['is_enabled']): ?>
                                            <span style="color: #999; font-weight: normal; font-size: 0.8em;">(Atspējots)</span>
                                        <?php endif; ?>
                                    </h3>
                                    
                                    <div class="screen-meta">
                                        <span>
                                            <span class="color-preview" style="background-color: <?php echo htmlspecialchars($screen['highlight_color']); ?>;"></span>
                                            Krāsa: <?php echo htmlspecialchars($screen['highlight_color']); ?>
                                        </span>
                                        <?php if (($screen['content_type'] ?? 'image') === 'video'): ?>
                                            <span>Rāda: Video garums</span>
                                        <?php else: ?>
                                            <span>Rāda: <?php echo $screen['display_time']; ?>ms</span>
                                        <?php endif; ?>
                                        <span>Kārtība: #<?php echo $screen['display_order']; ?></span>
                                        <span>Izveidots: <?php echo date('j.M.Y G:i', strtotime($screen['created_at'])); ?></span>
                                    </div>
                                    
                                    <?php if (!empty($screen['text']) && ($screen['content_type'] ?? 'image') !== 'video' && ($screen['content_type'] ?? 'image') !== 'leaderboard'): ?>
                                        <p><strong>Teksts:</strong> <?php echo nl2br(htmlspecialchars(substr($screen['text'], 0, 200))); ?><?php echo strlen($screen['text']) > 200 ? '...' : ''; ?></p>
                                    <?php elseif (($screen['content_type'] ?? 'image') === 'video'): ?>
                                        <p><strong>Veids:</strong> Video saturs (automātiski pārslēdzas pēc video beigām)</p>
                                    <?php elseif (($screen['content_type'] ?? 'image') === 'leaderboard'): ?>
                                        <p><strong>Veids:</strong> Spēlētāju leaderboard (rāda top 10 spēlētājus)</p>
                                    <?php endif; ?>
                                    
                                    <div class="screen-actions">
                                        <a href="?edit=<?php echo $screen['id']; ?>" class="btn btn-success">Rediģēt</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Vai tiešām vēlaties dzēst šo ekrānu?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $screen['id']; ?>">
                                            <button type="submit" class="btn btn-danger">Dzēst</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize content type selection
            updateContentTypeFields();
            
            // Sync color picker with hex input - only if elements exist
            const highlightColorInput = document.getElementById('highlight_color');
            const colorHexInput = document.getElementById('color_hex');
            
            if (highlightColorInput && colorHexInput) {
                highlightColorInput.addEventListener('input', function() {
                    colorHexInput.value = this.value;
                });
            }

            // Drag and drop functionality - only if screensList exists
            const screensList = document.getElementById('screensList');
            if (screensList) {
                initializeDragAndDrop();
            }
        });

        function selectContentType(type) {
            // Update hidden input
            document.getElementById('content_type').value = type;
            
            // Update button states
            const buttons = document.querySelectorAll('.content-type-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update form fields based on content type
            updateContentTypeFields();
        }

        function updateContentTypeFields() {
            const contentType = document.getElementById('content_type').value;
            const mediaInput = document.getElementById('media');
            const mediaLabel = document.getElementById('media_label');
            const mediaField = document.getElementById('media_field');
            const titleField = document.getElementById('title_field');
            const colorField = document.getElementById('color_field');
            const textField = document.getElementById('text_field');
            const timeField = document.getElementById('time_field');
            const titleInput = document.getElementById('title');
            const textInput = document.getElementById('text');

            if (contentType === 'video') {
                // Video mode
                mediaLabel.textContent = 'Video fails:';
                mediaInput.setAttribute('accept', 'video/*');
                mediaInput.setAttribute('required', 'required');
                
                // Hide/show fields and make them optional
                mediaField.classList.remove('hidden');
                titleField.classList.add('hidden');
                colorField.classList.add('hidden');
                textField.classList.add('hidden');
                timeField.classList.add('hidden');
                
                titleInput.removeAttribute('required');
                textInput.removeAttribute('required');
            } else if (contentType === 'leaderboard') {
                // Leaderboard mode
                mediaField.classList.add('hidden');
                titleField.classList.remove('hidden');
                colorField.classList.remove('hidden');
                textField.classList.add('hidden');
                timeField.classList.remove('hidden');
                
                titleInput.setAttribute('required', 'required');
                textInput.removeAttribute('required');
                mediaInput.removeAttribute('required');
            } else {
                // Image mode
                mediaLabel.textContent = 'Fotoattēls:';
                mediaInput.setAttribute('accept', 'image/*');
                mediaInput.setAttribute('required', 'required');
                
                // Show all fields and make them required
                mediaField.classList.remove('hidden');
                titleField.classList.remove('hidden');
                colorField.classList.remove('hidden');
                textField.classList.remove('hidden');
                timeField.classList.remove('hidden');
                
                titleInput.setAttribute('required', 'required');
                textInput.setAttribute('required', 'required');
            }
        }

        // Drag and drop functionality
        let draggedElement = null;

        function initializeDragAndDrop() {
            const screensList = document.getElementById('screensList');
            if (!screensList) return;
            
            // Add drag event listeners to all screen items
            const screenItems = screensList.querySelectorAll('.screen-item');
            screenItems.forEach(item => {
                item.addEventListener('dragstart', handleDragStart);
                item.addEventListener('dragover', handleDragOver);
                item.addEventListener('drop', handleDrop);
                item.addEventListener('dragend', handleDragEnd);
                item.draggable = true;
            });
        }

        function handleDragStart(e) {
            draggedElement = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.outerHTML);
        }

        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }

        function handleDrop(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }

            if (draggedElement !== this) {
                const rect = this.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;
                
                if (e.clientY < midpoint) {
                    this.parentNode.insertBefore(draggedElement, this);
                } else {
                    this.parentNode.insertBefore(draggedElement, this.nextSibling);
                }
                
                updateScreenOrder();
            }
            return false;
        }

        function handleDragEnd(e) {
            this.classList.remove('dragging');
            draggedElement = null;
        }

        function updateScreenOrder() {
            const items = document.querySelectorAll('#screensList .screen-item');
            if (!items.length) return;
            
            const orders = [];
            
            items.forEach((item, index) => {
                const id = parseInt(item.dataset.id);
                if (!isNaN(id)) {
                    orders.push({
                        id: id,
                        order: index + 1
                    });
                }
            });

            if (orders.length === 0) return;

            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=update_order&orders=' + encodeURIComponent(JSON.stringify(orders))
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    // Update display order numbers in the UI
                    items.forEach((item, index) => {
                        const orderSpan = item.querySelector('.screen-meta span:nth-child(3)');
                        if (orderSpan) {
                            orderSpan.textContent = 'Kārtība: #' + (index + 1);
                        }
                    });
                } else {
                    alert('Neizdevās atjaunināt kārtību: ' + (data.message || 'Nezināma kļūda'));
                    location.reload(); // Reload to restore original order
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Neizdevās atjaunināt kārtību: ' + error.message);
                location.reload();
            });
        }

        function toggleScreenStatus(id, enabled) {
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=toggle_status&id=' + id + '&is_enabled=' + (enabled ? 1 : 0)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                if (data.trim() === 'success') {
                    // Find the screen item and update its appearance
                    const screenItem = document.querySelector(`[data-id="${id}"]`);
                    if (screenItem) {
                        if (enabled) {
                            screenItem.classList.remove('disabled');
                            // Remove (Disabled) text from title
                            const title = screenItem.querySelector('h3');
                            if (title) {
                                title.innerHTML = title.innerHTML.replace(' <span style="color: #999; font-weight: normal; font-size: 0.8em;">(Atspējots)</span>', '');
                            }
                        } else {
                            screenItem.classList.add('disabled');
                            // Add (Disabled) text to title
                            const title = screenItem.querySelector('h3');
                            if (title && !title.innerHTML.includes('(Atspējots)')) {
                                title.innerHTML += ' <span style="color: #999; font-weight: normal; font-size: 0.8em;">(Atspējots)</span>';
                            }
                        }
                    }
                    
                    // Update the header count
                    updateActiveCount();
                } else {
                    throw new Error('Server returned error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Neizdevās atjaunināt ekrāna statusu: ' + error.message);
                // Revert the toggle
                const toggle = document.querySelector(`[data-id="${id}"] input[type="checkbox"]`);
                if (toggle) {
                    toggle.checked = !enabled;
                }
            });
        }

        function updateActiveCount() {
            const totalScreens = document.querySelectorAll('#screensList .screen-item').length;
            const activeScreens = document.querySelectorAll('#screensList .screen-item:not(.disabled)').length;
            const header = document.querySelector('.screens-list h2');
            if (header) {
                header.textContent = `Esošie ekrāni (${totalScreens}) - ${activeScreens} aktīvi`;
            }
        }
    </script>
</body>
</html>