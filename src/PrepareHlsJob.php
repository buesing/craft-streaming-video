<?php
namespace buesing\streamingvideo;

use craft\queue\BaseJob;
use craft\elements\Asset;
use Craft;

class PrepareHlsJob extends BaseJob
{
    public int $assetId;

    public function execute($queue): void
    {
        $asset = Asset::find()->id($this->assetId)->one();
        if (!$asset) {
            Craft::error("PrepareHlsJob: Asset not found for ID {$this->assetId}", __METHOD__);
            return;
        }
        if (!$asset->canStreamVideo()) {
            Craft::info("PrepareHlsJob: Asset {$this->assetId} is not a video.", __METHOD__);
            return;
        }

        $inputPath = $asset->getCopyOfFile();
        $baseName = pathinfo($asset->filename, PATHINFO_FILENAME);
        $tempDir = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . uniqid('hls_', true);
        if (!@mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            Craft::error("Failed to create temp directory: $tempDir", __METHOD__);
            return;
        }

        // Detect source resolution
        $ffprobeCmd = 'ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 ' . escapeshellarg($inputPath);
        exec($ffprobeCmd, $output, $returnVar);
        if ($returnVar !== 0 || empty($output[0])) {
            Craft::error("Failed to detect video resolution for asset {$this->assetId}", __METHOD__);
            \craft\helpers\FileHelper::removeDirectory($tempDir);
            @unlink($inputPath);
            return;
        }
        list($srcWidth, $srcHeight) = array_map('intval', explode(',', $output[0]));

        // Define variant heights (can be customized)
        $variantHeights = [1080, 720, 480, 240, 144];
        $variants = [];
        foreach ($variantHeights as $h) {
            if ($srcHeight >= $h) {
                $variants[] = [
                    'name' => "{$h}p",
                    'maxHeight' => $h,
                    'video_bitrate' => $h >= 1080 ? '5000k' : ($h >= 720 ? '3000k' : ($h >= 480 ? '1500k' : ($h >= 240 ? '800k' : '400k'))),
                    'audio_bitrate' => $h >= 720 ? '128k' : ($h >= 480 ? '96k' : '64k'),
                ];
            }
        }
        // Always add a "source" variant at the original resolution
        $variants[] = [
            'name' => 'source',
            'maxHeight' => $srcHeight,
            'video_bitrate' => '8000k', // or estimate
            'audio_bitrate' => '192k',
        ];

        $totalSteps = count($variants) + 1; // +1 for master playlist

        $volume = $asset->getVolume();
        $fs = $volume->getFs();
        $hlsPath = '__hls__/' . $asset->uid . '/';

        $actualResolutions = [];
        $bandwidths = [];

        foreach ($variants as $i => $variant) {
            $this->setProgress($queue, $i / $totalSteps, "Encoding {$variant['name']} stream...");

            $playlist = "{$variant['name']}.m3u8";
            $segmentPattern = "{$variant['name']}_%03d.ts";
            $scaleFilter = "scale=-2:{$variant['maxHeight']}:force_original_aspect_ratio=decrease:force_divisible_by=2";
            $cmd = 'ffmpeg -y -i ' . escapeshellarg($inputPath)
                . ' -vf ' . escapeshellarg($scaleFilter)
                . ' -c:v libx264 -b:v ' . $variant['video_bitrate']
                . ' -c:a aac -b:a ' . $variant['audio_bitrate']
                . ' -ac 2 -ar 48000'
                . ' -hls_time 6 -hls_playlist_type vod'
                . ' -hls_segment_filename ' . escapeshellarg($tempDir . DIRECTORY_SEPARATOR . $segmentPattern)
                . ' ' . escapeshellarg($tempDir . DIRECTORY_SEPARATOR . $playlist);

            $command = new \mikehaertl\shellcommand\Command($cmd);
            if (!$command->execute()) {
                Craft::error("ffmpeg failed: " . $command->getError(), __METHOD__);
                \craft\helpers\FileHelper::removeDirectory($tempDir);
                @unlink($inputPath);
                return;
            }

            // Detect actual output resolution for this variant
            $variantPath = $tempDir . DIRECTORY_SEPARATOR . $playlist;
            $ffprobeOut = [];
            $ffprobeCmd = 'ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 ' . escapeshellarg($variantPath);
            exec($ffprobeCmd, $ffprobeOut, $probeReturn);
            if ($probeReturn === 0 && !empty($ffprobeOut[0])) {
                list($w, $h) = array_map('intval', explode(',', $ffprobeOut[0]));
                $actualResolutions[$variant['name']] = "$w" . 'x' . "$h";
            } else {
                $actualResolutions[$variant['name']] = '';
            }
            // Estimate bandwidth (could be improved by parsing output bitrate)
            $bandwidths[$variant['name']] = (int)filter_var($variant['video_bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000;

            // Upload this variant's files immediately
            foreach (glob($tempDir . DIRECTORY_SEPARATOR . "{$variant['name']}*") as $file) {
                $stream = fopen($file, 'rb');
                $fs->writeFileFromStream($hlsPath . basename($file), $stream);
                fclose($stream);
            }

        }

        // Generate and upload master playlist
        $masterPlaylist = "#EXTM3U\n\n";
        foreach ($variants as $variant) {
            $name = $variant['name'];
            $res = $actualResolutions[$name] ?: '';
            $bw = $bandwidths[$name] ?? 2000000;
            $masterPlaylist .= "#EXT-X-STREAM-INF:BANDWIDTH={$bw}";
            if ($res) {
                $masterPlaylist .= ",RESOLUTION={$res}";
            }
            $masterPlaylist .= "\n{$name}.m3u8\n\n";
        }
        $masterPath = $tempDir . DIRECTORY_SEPARATOR . 'master.m3u8';
        file_put_contents($masterPath, $masterPlaylist);

        $stream = fopen($masterPath, 'rb');
        $fs->writeFileFromStream($hlsPath . 'master.m3u8', $stream);
        fclose($stream);

        $this->setProgress($queue, 1, "Master playlist uploaded");

        \craft\helpers\FileHelper::removeDirectory($tempDir);
        @unlink($inputPath);
    }

    protected function defaultDescription(): string
    {
        return 'Rendering video';
    }
} 
