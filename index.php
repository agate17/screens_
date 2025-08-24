<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM screens WHERE is_enabled = 1 ORDER BY display_order ASC, id ASC");
    $screens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch top 10 players for leaderboard screens
    $stmt = $pdo->query("SELECT * FROM players ORDER BY score DESC, name ASC LIMIT 6");
    $topPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $screens = [];
    $topPlayers = [];
    $error = "Datubāzes kļūda: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sponsoru ekrāni</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <button id="fullscreenBtn" class="fullscreen-btn" onclick="toggleFullscreen()">
        <svg class="fullscreen-icon" viewBox="0 0 24 24">
            <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
        </svg>
        Pilnekrāns
    </button>

    <?php if (isset($error)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php elseif (empty($screens)): ?>
        <div class="no-screens">
            <h2>Nav konfigurēti ekrāni</h2>
            <p>Lūdzu, pievienojiet ekrānus administrācijas panelī.</p>
            <a href="admin.php" class="admin-link">Doties uz administrācijas paneli</a>
        </div>
    <?php else: ?>
        <!-- Progress bar -->
        <div class="progress-bar" id="progressBar"></div>
        
        <!-- Screen counter -->
        <div class="screen-counter" id="screenCounter">
            <span id="currentScreenNum">1</span> / <?php echo count($screens); ?>
        </div>

        <?php foreach ($screens as $index => $screen): ?>
            <?php 
            $content_type = $screen['content_type'] ?? 'image';
            $is_video = $content_type === 'video';
            $is_leaderboard = $content_type === 'leaderboard';
            ?>
            <div class="screen <?php echo $index === 0 ? 'active' : ''; ?> <?php echo $is_video ? 'video-only' : ''; ?> <?php echo $is_leaderboard ? 'leaderboard-screen' : ''; ?>" 
                 data-display-time="<?php echo $screen['display_time']; ?>"
                 data-screen-index="<?php echo $index; ?>"
                 data-content-type="<?php echo $content_type; ?>"
                 style="--highlight-color: <?php echo htmlspecialchars($screen['highlight_color']); ?>;">
                
                <?php if ($is_video): ?>
                    <!-- Video-only content -->
                    <div class="screen-content video-content">
                        <?php if (!empty($screen['photo']) && file_exists(UPLOAD_DIR . $screen['photo'])): ?>
                            <video class="screen-video" 
                                   id="video-<?php echo $index; ?>"
                                   muted
                                   playsinline
                                   preload="auto">
                                <source src="<?php echo UPLOAD_DIR . htmlspecialchars($screen['photo']); ?>" 
                                        type="video/<?php echo pathinfo($screen['photo'], PATHINFO_EXTENSION) === 'mov' ? 'quicktime' : pathinfo($screen['photo'], PATHINFO_EXTENSION); ?>">
                                Jūsu pārlūks neatbalsta video tagu.
                            </video>
                        <?php endif; ?>
                    </div>
                    <div class="video-controls-overlay" id="videoOverlay-<?php echo $index; ?>">
                        Atskaņo video...
                    </div>
                
                <?php elseif ($is_leaderboard): ?>
                    <!-- Leaderboard content -->
                    <div class="screen-content leaderboard-content">
                        <h1 class="leaderboard-title">
                            🏆 
                            <?php echo !empty($screen['title']) ? htmlspecialchars($screen['title']) : 'LABĀKIE SPĒLĒTĀJI'; ?> 🏆
                        </h1>
                        
                        <?php if (!empty($topPlayers)): ?>
                            <table class="leaderboard-table">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">Vieta</th>
                                        <th>Spēlētājs</th>
                                        <th style="width: 150px; text-align: right;">Rezultāts</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topPlayers as $playerIndex => $player): ?>
                                        <tr class="rank-<?php echo $playerIndex + 1; ?>">
                                            <td>
                                                <div class="rank-position">
                                                    <?php if ($playerIndex === 0): ?>
                                                        <span class="rank-medal">🥇</span>
                                                    <?php elseif ($playerIndex === 1): ?>
                                                        <span class="rank-medal">🥈</span>
                                                    <?php elseif ($playerIndex === 2): ?>
                                                        <span class="rank-medal">🥉</span>
                                                    <?php else: ?>
                                                        <span><?php echo $playerIndex + 1; ?>.</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="player-name"><?php echo htmlspecialchars($player['name']); ?></span>
                                            </td>
                                            <td>
                                                <span class="player-score"><?php echo number_format($player['score']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-players-message">
                                Nav reģistrētu spēlētāju
                            </div>
                        <?php endif; ?>
                    </div>
                
                <?php else: ?>
                    <!-- Traditional image + text content -->
                    <div class="screen-content">
                        <h1 class="screen-title" 
                            style="text-decoration-color: <?php echo htmlspecialchars($screen['highlight_color']); ?>;">
                            <?php echo htmlspecialchars($screen['title'] ?? 'Bez nosaukuma'); ?>
                        </h1>
                        
                        <?php if (!empty($screen['photo']) && file_exists(UPLOAD_DIR . $screen['photo'])): ?>
                            <img src="<?php echo UPLOAD_DIR . htmlspecialchars($screen['photo']); ?>" 
                                 alt="Ekrāna attēls" 
                                 class="screen-image">
                        <?php endif; ?>
                        
                        <div class="screen-text">
                            <?php echo nl2br(htmlspecialchars($screen['text'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <style>
            <?php foreach ($screens as $index => $screen): ?>
                <?php if (($screen['content_type'] ?? 'image') !== 'video' && ($screen['content_type'] ?? 'image') !== 'leaderboard'): ?>
                .screen[data-screen-index="<?php echo $index; ?>"]::before {
                    background: 
                        linear-gradient(90deg, <?php echo htmlspecialchars($screen['highlight_color']); ?>25 0%, transparent 15%, transparent 85%, <?php echo htmlspecialchars($screen['highlight_color']); ?>25 100%),
                        linear-gradient(0deg, <?php echo htmlspecialchars($screen['highlight_color']); ?>15 0%, transparent 12%, transparent 88%, <?php echo htmlspecialchars($screen['highlight_color']); ?>15 100%);
                }
                <?php endif; ?>
            <?php endforeach; ?>
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const screens = document.querySelectorAll('.screen');
                const progressBar = document.getElementById('progressBar');
                const screenCounter = document.getElementById('screenCounter');
                const currentScreenNum = document.getElementById('currentScreenNum');
                
                let currentScreen = 0;
                let transitionTimeout;
                let progressInterval;
                let isTransitioning = false;
                let currentVideo = null;
                
                if (screens.length <= 1) {
                    if (progressBar) progressBar.style.display = 'none';
                    if (screenCounter) screenCounter.style.display = 'none';
                    return;
                }
                
                function updateProgressBar(duration) {
                    if (!progressBar) return;
                    
                    const currentColor = screens[currentScreen].style.getPropertyValue('--highlight-color') || '#4ecdc4';
                    progressBar.style.backgroundColor = currentColor;
                    progressBar.style.width = '0%';
                    
                    let progress = 0;
                    const intervalTime = 50; // Update every 50ms
                    const totalSteps = duration / intervalTime;
                    const stepSize = 100 / totalSteps;
                    
                    if (progressInterval) {
                        clearInterval(progressInterval);
                    }
                    
                    progressInterval = setInterval(() => {
                        progress += stepSize;
                        if (progress >= 100) {
                            progress = 100;
                            clearInterval(progressInterval);
                        }
                        progressBar.style.width = progress + '%';
                    }, intervalTime);
                }
                
                function updateScreenCounter() {
                    if (currentScreenNum) {
                        currentScreenNum.textContent = currentScreen + 1;
                    }
                }
                
                function pauseAllVideos() {
                    const allVideos = document.querySelectorAll('video');
                    allVideos.forEach(video => {
                        video.pause();
                        video.currentTime = 0;
                    });
                }
                
                function playCurrentVideo() {
                    const currentScreenElement = screens[currentScreen];
                    const contentType = currentScreenElement.getAttribute('data-content-type');
                    
                    if (contentType === 'video') {
                        const video = currentScreenElement.querySelector('video');
                        if (video) {
                            currentVideo = video;
                            
                            // Set up video event listeners
                            video.addEventListener('ended', function() {
                                // Video ended, move to next screen
                                showNextScreen();
                            }, { once: true });
                            
                            video.addEventListener('loadedmetadata', function() {
                                // Update progress bar to match video duration
                                const duration = video.duration * 1000; // Convert to milliseconds
                                updateProgressBar(duration);
                            });
                            
                            video.addEventListener('timeupdate', function() {
                                // Update progress bar based on video progress
                                if (progressBar && video.duration > 0) {
                                    const progress = (video.currentTime / video.duration) * 100;
                                    progressBar.style.width = progress + '%';
                                }
                            });
                            
                            // Play the video
                            video.play().catch(error => {
                                console.error('Kļūda atskaņojot video:', error);
                                // If video fails to play, fall back to timer
                                const fallbackTime = 10000; // 10 seconds
                                updateProgressBar(fallbackTime);
                                transitionTimeout = setTimeout(showNextScreen, fallbackTime);
                            });
                        }
                    }
                }
                
                function showNextScreen() {
                    if (isTransitioning) return;
                    
                    isTransitioning = true;
                    const currentScreenElement = screens[currentScreen];
                    const nextScreenIndex = (currentScreen + 1) % screens.length;
                    const nextScreenElement = screens[nextScreenIndex];
                    
                    // Clear any existing intervals and timeouts
                    if (progressInterval) {
                        clearInterval(progressInterval);
                    }
                    if (transitionTimeout) {
                        clearTimeout(transitionTimeout);
                    }
                    
                    // Pause all videos
                    pauseAllVideos();
                    currentVideo = null;
                    
                    // Start fade out animation for current screen
                    currentScreenElement.classList.add('fade-out');
                    
                    // After fade out completes, switch screens
                    setTimeout(() => {
                        // Remove active class from current screen
                        currentScreenElement.classList.remove('active', 'fade-out');
                        
                        // Update current screen index
                        currentScreen = nextScreenIndex;
                        
                        // Add active class to next screen and start fade in
                        nextScreenElement.classList.add('active', 'fade-in');
                        
                        // Update counter
                        updateScreenCounter();
                        
                        // Remove fade-in class after animation
                        setTimeout(() => {
                            nextScreenElement.classList.remove('fade-in');
                            isTransitioning = false;
                        }, 1000); // Match the CSS transition duration
                        
                        // Handle next screen based on content type
                        const nextContentType = nextScreenElement.getAttribute('data-content-type');
                        
                        if (nextContentType === 'video') {
                            // For videos, play the video and let it control timing
                            playCurrentVideo();
                        } else {
                            // For images and leaderboards, use the display time
                            const displayTime = parseInt(nextScreenElement.getAttribute('data-display-time'));
                            updateProgressBar(displayTime);
                            
                            // Schedule next transition
                            transitionTimeout = setTimeout(showNextScreen, displayTime);
                        }
                    }, 1000); // Match the CSS transition duration
                }
                
                // Initialize first screen
                updateScreenCounter();
                const firstScreenElement = screens[0];
                const firstContentType = firstScreenElement.getAttribute('data-content-type');
                
                if (firstContentType === 'video') {
                    playCurrentVideo();
                } else {
                    const firstDisplayTime = parseInt(firstScreenElement.getAttribute('data-display-time'));
                    updateProgressBar(firstDisplayTime);
                    transitionTimeout = setTimeout(showNextScreen, firstDisplayTime);
                }
                
                // Pause/resume on click (optional feature)
                let isPaused = false;
                document.addEventListener('click', function(e) {
                    // Don't pause if clicking on fullscreen button
                    if (e.target.closest('.fullscreen-btn')) return;
                    
                    const currentContentType = screens[currentScreen].getAttribute('data-content-type');
                    
                    if (isPaused) {
                        // Resume
                        if (currentContentType === 'video' && currentVideo) {
                            currentVideo.play();
                        } else {
                            const remainingTime = parseInt(screens[currentScreen].getAttribute('data-display-time'));
                            transitionTimeout = setTimeout(showNextScreen, remainingTime);
                            updateProgressBar(remainingTime);
                        }
                        isPaused = false;
                        document.body.style.cursor = '';
                    } else {
                        // Pause
                        if (currentContentType === 'video' && currentVideo) {
                            currentVideo.pause();
                        } else {
                            clearTimeout(transitionTimeout);
                        }
                        clearInterval(progressInterval);
                        isPaused = true;
                        document.body.style.cursor = 'pointer';
                    }
                });
                
                // Show pause indicator
                document.addEventListener('mousemove', function() {
                    if (isPaused) {
                        document.body.style.cursor = 'pointer';
                    }
                });
            });

            // Fullscreen functionality
            function toggleFullscreen() {
                const btn = document.getElementById('fullscreenBtn');
                
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().then(() => {
                        btn.innerHTML = `
                            <svg class="fullscreen-icon" viewBox="0 0 24 24">
                                <path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/>
                            </svg>
                            Iziet no pilnekrāna
                        `;
                    });
                } else {
                    document.exitFullscreen().then(() => {
                        btn.innerHTML = `
                            <svg class="fullscreen-icon" viewBox="0 0 24 24">
                                <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                            </svg>
                            Pilnekrāns
                        `;
                    });
                }
            }

            // Listen for fullscreen changes (ESC key, etc.)
            document.addEventListener('fullscreenchange', function() {
                const btn = document.getElementById('fullscreenBtn');
                if (!document.fullscreenElement) {
                    btn.innerHTML = `
                        <svg class="fullscreen-icon" viewBox="0 0 24 24">
                            <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                        </svg>
                        Pilnekrāns
                    `;
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>