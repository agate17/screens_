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
    <link rel="stylesheet" href="css/admin.css">
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