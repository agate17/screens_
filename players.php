<?php
require_once 'config.php';

$message = '';
$messageType = '';

// Handle AJAX requests first (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pdo = getDBConnection();
    
    // Handle regular form submissions
    switch ($_POST['action']) {
        case 'add':
            $name = trim($_POST['name'] ?? '');
            $score = intval($_POST['score'] ?? 0);
            
            if (!empty($name) && $score >= 0) {
                $stmt = $pdo->prepare("INSERT INTO players (name, score) VALUES (?, ?)");
                if ($stmt->execute([$name, $score])) {
                    $message = 'Spēlētājs veiksmīgi pievienots!';
                    $messageType = 'success';
                } else {
                    $message = 'Neizdevās pievienot spēlētāju datubāzē.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Lūdzu, norādiet derīgu spēlētāja vārdu un rezultātu.';
                $messageType = 'error';
            }
            break;
            
        case 'edit':
            $id = intval($_POST['id']);
            $name = trim($_POST['name'] ?? '');
            $score = intval($_POST['score'] ?? 0);
            
            if ($id > 0 && !empty($name) && $score >= 0) {
                $stmt = $pdo->prepare("UPDATE players SET name = ?, score = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                if ($stmt->execute([$name, $score, $id])) {
                    $message = 'Spēlētāja dati veiksmīgi atjaunināti!';
                    $messageType = 'success';
                } else {
                    $message = 'Neizdevās atjaunināt spēlētāja datus.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Lūdzu, norādiet derīgus datus.';
                $messageType = 'error';
            }
            break;
            
        case 'delete':
            $id = intval($_POST['id']);
            
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $message = 'Spēlētājs veiksmīgi dzēsts!';
                    $messageType = 'success';
                } else {
                    $message = 'Neizdevās dzēst spēlētāju.';
                    $messageType = 'error';
                }
            }
            break;
    }
}

// Fetch all players
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM players ORDER BY score DESC, name ASC");
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get player count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM players");
    $totalPlayers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch(PDOException $e) {
    $players = [];
    $totalPlayers = 0;
    $message = "Datubāzes kļūda: " . $e->getMessage();
    $messageType = 'error';
}

// Handle edit mode
$editPlayer = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    foreach ($players as $player) {
        if ($player['id'] == $editId) {
            $editPlayer = $player;
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
    <title>Spēlētāju pārvaldība - Leaderboard sistēma</title>
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
        .form-group input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
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

        .players-list {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: linear-gradient(135deg, #667eea20, #764ba220);
            border-radius: 8px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .leaderboard-table th,
        .leaderboard-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .leaderboard-table th {
            background-color: #667eea;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }

        .leaderboard-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .leaderboard-table tr:hover {
            background-color: #e3f2fd;
        }

        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            font-weight: bold;
            color: white;
            margin-right: 10px;
        }

        .rank-1 { background-color: #ffd700; color: #333; }
        .rank-2 { background-color: #c0c0c0; color: #333; }
        .rank-3 { background-color: #cd7f32; color: white; }
        .rank-other { background-color: #667eea; }

        .player-name {
            font-weight: bold;
            color: #333;
        }

        .player-score {
            font-weight: bold;
            color: #667eea;
            font-size: 1.1em;
        }

        .player-date {
            color: #666;
            font-size: 0.9em;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 4px;
        }

        .no-players {
            text-align: center;
            color: #666;
            padding: 40px;
            font-style: italic;
        }

        .preview-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .leaderboard-preview {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
        }

        .preview-title {
            text-align: center;
            font-size: 1.5em;
            margin-bottom: 15px;
        }

        .preview-table {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        .preview-table th,
        .preview-table td {
            padding: 8px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .preview-table th {
            background: rgba(255,255,255,0.2);
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-bar {
                flex-direction: column;
                gap: 10px;
            }
            
            .leaderboard-table {
                font-size: 14px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏆 Spēlētāju pārvaldības panelis</h1>
            <p>Pārvaldiet leaderboard spēlētājus un rezultātus</p>
            <div class="nav-links">
                <a href="index.php" target="_blank">Skatīt displeju</a>
                <a href="admin.php">Ekrānu panelis</a>
                <a href="players.php">Atsvaidzināt</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h2><?php echo $editPlayer ? '✏️ Rediģēt spēlētāju' : '➕ Pievienot jaunu spēlētāju'; ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $editPlayer ? 'edit' : 'add'; ?>">
                <?php if ($editPlayer): ?>
                    <input type="hidden" name="id" value="<?php echo $editPlayer['id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Spēlētāja vārds:</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="<?php echo $editPlayer ? htmlspecialchars($editPlayer['name']) : ''; ?>" 
                               placeholder="Ievadiet spēlētāja vārdu..."
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="score">Rezultāts:</label>
                        <input type="number" 
                               id="score" 
                               name="score" 
                               min="0"
                               max="999999"
                               value="<?php echo $editPlayer ? $editPlayer['score'] : '0'; ?>" 
                               placeholder="0"
                               required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?php echo $editPlayer ? '✅ Atjaunināt spēlētāju' : '➕ Pievienot spēlētāju'; ?>
                </button>
                
                <?php if ($editPlayer): ?>
                    <a href="players.php" class="btn btn-secondary">❌ Atcelt</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($players)): ?>
            <div class="preview-section">
                <h2>📺 Leaderboard priekšskatījums (Top 10)</h2>
                <p>Šāds leaderboard tiks rādīts displeja sistēmā</p>
                
                <div class="leaderboard-preview">
                    <div class="preview-title">🏆 TOP SPĒLĒTĀJI</div>
                    <table class="preview-table" style="width: 100%; color: white;">
                        <thead>
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Spēlētājs</th>
                                <th style="width: 100px;">Rezultāts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($players, 0, 10) as $index => $player): ?>
                                <tr>
                                    <td>
                                        <?php if ($index < 3): ?>
                                            <?php echo $index === 0 ? '🥇' : ($index === 1 ? '🥈' : '🥉'); ?>
                                        <?php else: ?>
                                            <?php echo $index + 1; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($player['name']); ?></td>
                                    <td><?php echo number_format($player['score']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="players-list">
            <h2>📊 Visi spēlētāji (<?php echo $totalPlayers; ?>)</h2>
            
            <?php if ($totalPlayers > 0): ?>
                <?php
                $topScore = !empty($players) ? $players[0]['score'] : 0;
                $avgScore = !empty($players) ? round(array_sum(array_column($players, 'score')) / count($players)) : 0;
                ?>
                
                <div class="stats-bar">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $totalPlayers; ?></div>
                        <div class="stat-label">Kopā spēlētāji</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($topScore); ?></div>
                        <div class="stat-label">Augstākais rezultāts</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($avgScore); ?></div>
                        <div class="stat-label">Vidējais rezultāts</div>
                    </div>
                </div>

                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Pozīcija</th>
                            <th>Spēlētājs</th>
                            <th style="width: 120px;">Rezultāts</th>
                            <th style="width: 150px;">Pievienots</th>
                            <th style="width: 150px;">Atjaunināts</th>
                            <th style="width: 150px;">Darbības</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $index => $player): ?>
                            <tr>
                                <td>
                                    <span class="rank-badge rank-<?php echo $index < 3 ? ($index + 1) : 'other'; ?>">
                                        <?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="player-name"><?php echo htmlspecialchars($player['name']); ?></span>
                                </td>
                                <td>
                                    <span class="player-score"><?php echo number_format($player['score']); ?></span>
                                </td>
                                <td>
                                    <span class="player-date"><?php echo date('j.M.Y G:i', strtotime($player['created_at'])); ?></span>
                                </td>
                                <td>
                                    <span class="player-date"><?php echo date('j.M.Y G:i', strtotime($player['updated_at'])); ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?edit=<?php echo $player['id']; ?>" class="btn btn-success btn-small">✏️ Rediģēt</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Vai tiešām vēlaties dzēst šo spēlētāju?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $player['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-small">🗑️ Dzēst</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-players">
                    <h3>📝 Vēl nav pievienoti spēlētāji</h3>
                    <p>Pievienojiet savus pirmos spēlētājus, izmantojot augstāk esošo formu.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>