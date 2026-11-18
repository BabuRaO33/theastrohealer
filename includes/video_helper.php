<?php
/**
 * Video Helper — The Astro Healer
 * Handles parsing, normalizing, and rendering videos from:
 * - Direct server uploads (MP4, WebM, MOV)
 * - Google Drive (auto-converts /view, /edit, uc?id, open?id, iframe embed codes into /preview)
 * - YouTube (watch?v=, youtu.be, shorts, embeds)
 * - Vimeo (links and embeds)
 * - Direct remote MP4/WebM URLs
 */

if (!function_exists('parseVideoSource')) {
    /**
     * Parses any video source into structured playback details
     * 
     * @param string $source File path or URL
     * @param string $videoType 'file' or 'url'
     * @return array
     */
    function parseVideoSource($source, $videoType = 'url') {
        $source = trim((string)$source);
        if (empty($source)) {
            return [
                'kind' => 'empty',
                'embed_url' => '',
                'file_url' => '',
                'drive_id' => null,
                'raw' => '',
            ];
        }

        // If user pasted raw iframe HTML (e.g. from Google Drive Embed or YouTube Embed)
        if (stripos($source, '<iframe') !== false) {
            if (preg_match('/src=["\']([^"\']+)["\']/i', $source, $m)) {
                $source = htmlspecialchars_decode(trim($m[1]));
            }
        }

        // Decode HTML entities if stored encoded
        $decoded = htmlspecialchars_decode($source);

        // 1. Direct Local File Upload
        if ($videoType === 'file' || strpos($decoded, 'uploads/lectures/') !== false || strpos($decoded, 'uploads/') === 0) {
            $relativePath = ltrim($decoded, '/');
            // If BASE_URL is already prepended, avoid double prepending
            if (defined('BASE_URL') && strpos($decoded, BASE_URL) === 0) {
                $fullUrl = $decoded;
            } else {
                $fullUrl = (defined('BASE_URL') ? BASE_URL : '') . '/' . $relativePath;
            }
            return [
                'kind' => 'file',
                'embed_url' => '',
                'file_url' => $fullUrl,
                'relative_path' => $relativePath,
                'drive_id' => null,
                'raw' => $source,
            ];
        }

        // 2. Google Drive Links
        // Formats:
        // drive.google.com/file/d/{ID}/view
        // drive.google.com/file/d/{ID}/preview
        // drive.google.com/file/d/{ID}
        // drive.google.com/open?id={ID}
        // drive.google.com/uc?id={ID}
        // docs.google.com/file/d/{ID}/...
        if (preg_match('#(?:drive|docs)\.google\.com/(?:file/d/|open\?id=|uc\?id=)([a-zA-Z0-9_-]+)#i', $decoded, $m)) {
            $driveId = $m[1];
            return [
                'kind' => 'drive',
                'embed_url' => "https://drive.google.com/file/d/{$driveId}/preview",
                'direct_view_url' => "https://drive.google.com/file/d/{$driveId}/view?usp=sharing",
                'file_url' => '',
                'drive_id' => $driveId,
                'raw' => $source,
            ];
        }

        // 3. YouTube Links
        // Formats: youtube.com/watch?v={ID}, youtu.be/{ID}, youtube.com/embed/{ID}, youtube.com/shorts/{ID}
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([a-zA-Z0-9_-]{11})#i', $decoded, $m)) {
            $ytId = $m[1];
            return [
                'kind' => 'youtube',
                'embed_url' => "https://www.youtube.com/embed/{$ytId}?rel=0&modestbranding=1",
                'file_url' => '',
                'youtube_id' => $ytId,
                'raw' => $source,
            ];
        }

        // 4. Vimeo Links
        // Formats: vimeo.com/{ID}, player.vimeo.com/video/{ID}
        if (preg_match('#(?:vimeo\.com/|player\.vimeo\.com/video/)([0-9]+)#i', $decoded, $m)) {
            $vimeoId = $m[1];
            return [
                'kind' => 'vimeo',
                'embed_url' => "https://player.vimeo.com/video/{$vimeoId}",
                'file_url' => '',
                'vimeo_id' => $vimeoId,
                'raw' => $source,
            ];
        }

        // 5. Remote Direct Video File URL (e.g. .mp4, .webm, .mov)
        $cleanPath = parse_url($decoded, PHP_URL_PATH) ?? '';
        if (preg_match('/\.(mp4|webm|mov|mkv|m4v)$/i', $cleanPath)) {
            return [
                'kind' => 'direct_video',
                'embed_url' => '',
                'file_url' => $decoded,
                'drive_id' => null,
                'raw' => $source,
            ];
        }

        // 6. Generic Embed URL Fallback
        return [
            'kind' => 'iframe',
            'embed_url' => $decoded,
            'file_url' => '',
            'drive_id' => null,
            'raw' => $source,
        ];
    }
}

if (!function_exists('renderLectureVideoPlayerHtml')) {
    /**
     * Renders complete, responsive HTML for playing the video
     * 
     * @param string $videoType 'file' or 'url'
     * @param string $videoSource
     * @param string $title
     * @param array $options
     * @return string
     */
    function renderLectureVideoPlayerHtml($videoType, $videoSource, $title = '', $options = []) {
        $parsed = parseVideoSource($videoSource, $videoType);
        $showDriveFallback = $options['show_drive_fallback'] ?? true;
        $autoplay = !empty($options['autoplay']) ? 1 : 0;

        ob_start();

        if ($parsed['kind'] === 'file' || $parsed['kind'] === 'direct_video') {
            $fileUrl = htmlspecialchars($parsed['file_url']);
            ?>
            <video controls playsinline controlslist="nodownload" preload="metadata" <?= $autoplay ? 'autoplay' : '' ?> style="width: 100%; height: 100%; background: #000; object-fit: contain;">
                <source src="<?= $fileUrl ?>" type="video/mp4">
                Your browser does not support HTML5 video playback.
            </video>
            <?php
        } elseif ($parsed['kind'] === 'drive') {
            $embedUrl = htmlspecialchars($parsed['embed_url']);
            $directUrl = htmlspecialchars($parsed['direct_view_url']);
            ?>
            <iframe 
                src="<?= $embedUrl ?>" 
                allow="autoplay; fullscreen; encrypted-media; picture-in-picture" 
                allowfullscreen 
                style="width: 100%; height: 100%; border: none;"
                loading="lazy">
            </iframe>
            <?php if ($showDriveFallback): ?>
                <div class="gdrive-video-bar" style="position: absolute; bottom: 8px; right: 8px; z-index: 5; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(212,175,55,0.3); font-size: 0.72rem;">
                    <a href="<?= $directUrl ?>" target="_blank" rel="noopener noreferrer" style="color: #ffd700; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa-brands fa-google-drive" style="color: #4285F4;"></i>
                        <span>Open in Google Drive <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.65rem;"></i></span>
                    </a>
                </div>
            <?php endif; ?>
            <?php
        } elseif ($parsed['kind'] === 'youtube' || $parsed['kind'] === 'vimeo' || $parsed['kind'] === 'iframe') {
            $embedUrl = $parsed['embed_url'];
            if ($autoplay && strpos($embedUrl, 'autoplay=') === false) {
                $embedUrl .= (strpos($embedUrl, '?') === false ? '?' : '&') . 'autoplay=1';
            }
            ?>
            <iframe 
                src="<?= htmlspecialchars($embedUrl) ?>" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                allowfullscreen 
                style="width: 100%; height: 100%; border: none;">
            </iframe>
            <?php
        } else {
            ?>
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--dash-muted); padding: 2rem; text-align: center;">
                <i class="fa-solid fa-video-slash" style="font-size: 2.5rem; color: #ff5252; margin-bottom: 0.8rem;"></i>
                <h4 style="color: #fff; margin-bottom: 0.4rem;">Video Not Available</h4>
                <p style="font-size: 0.85rem; max-width: 400px;">No valid video link or file has been configured for this class lecture yet.</p>
            </div>
            <?php
        }

        return ob_get_clean();
    }
}

if (!function_exists('getIniSizeInBytes')) {
    /**
     * Converts ini size shorthand (e.g. 1024M, 2G, 512K) to bytes
     */
    function getIniSizeInBytes($val) {
        $val = trim((string)$val);
        if (empty($val)) return 0;
        $last = strtolower($val[strlen($val)-1]);
        $num = (int)$val;
        switch($last) {
            case 'g': $num *= 1024 * 1024 * 1024; break;
            case 'm': $num *= 1024 * 1024; break;
            case 'k': $num *= 1024; break;
        }
        return $num;
    }
}

if (!function_exists('getServerMaxUploadBytes')) {
    /**
     * Returns the effective max upload size in bytes based on both upload_max_filesize and post_max_size
     */
    function getServerMaxUploadBytes() {
        $uploadMax = getIniSizeInBytes(ini_get('upload_max_filesize') ?: '1024M');
        $postMax = getIniSizeInBytes(ini_get('post_max_size') ?: '1024M');
        $effective = min($uploadMax ?: PHP_INT_MAX, $postMax ?: PHP_INT_MAX);
        return $effective > 0 ? $effective : (1024 * 1024 * 1024);
    }
}

if (!function_exists('normalizeVideoInputForStorage')) {
    /**
     * Sanitizes and normalizes video URL input before saving to database
     * Converts Google Drive /view to /preview, strips raw iframe tags, cleans YouTube URLs
     * 
     * @param string $videoType
     * @param string $source
     * @return string
     */
    function normalizeVideoInputForStorage($videoType, $source) {
        $source = trim((string)$source);
        if ($videoType === 'file' || empty($source)) {
            return $source;
        }

        $parsed = parseVideoSource($source, 'url');
        if (!empty($parsed['embed_url'])) {
            return $parsed['embed_url'];
        }
        if (!empty($parsed['file_url'])) {
            return $parsed['file_url'];
        }
        return $source;
    }
}
