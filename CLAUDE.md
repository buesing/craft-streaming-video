# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Craft CMS plugin (`buesing/craft-streaming-video`) that automatically converts uploaded videos to HLS (HTTP Live Streaming) format with multiple quality variants. The plugin extends Craft's Asset elements with streaming video capabilities.

## Development Commands

### Installation & Setup
```bash
# Install dependencies
composer install

# Install plugin in Craft CMS
php craft plugin/install craft-streaming-video

# Generate new plugin components (if needed)
vendor/bin/craft-generator
```

### Testing & Development
- No specific test framework is configured
- FFmpeg is required for video processing - ensure it's installed locally or in DDEV
- Check Craft logs at `storage/logs/web.log` for debugging

## Architecture

### Core Components

**Plugin.php** - Main plugin class that:
- Registers template roots for `_streamingvideo/` namespace
- Attaches `StreamingVideoBehavior` to all Asset elements
- Adds `hlsPlaylistUrl` field to GraphQL Asset interface

**StreamingVideoBehavior.php** - Extends Asset elements with:
- `canStreamVideo()` - Checks if asset is a video file
- `getHlsPlaylistUrl()` - Returns URL to HLS master playlist
- Event handlers for automatic HLS generation on save and cleanup on delete

**PrepareHlsJob.php** - Background queue job that:
- Processes video assets using FFmpeg
- Generates multiple quality variants (1080p, 720p, 480p, 240p, 144p, source)
- Creates HLS playlists and segments
- Stores files in `__hls__/{asset-uid}/` folder structure

**templates/player.twig** - Video player template with HLS.js integration

### File Organization
- HLS files stored as: `__hls__/{asset-uid}/master.m3u8`
- Quality variants: `{quality}.m3u8` and `{quality}_%03d.ts` segments
- Template namespace: `@streamingvideo` or `_streamingvideo`

### Key Design Patterns
- Uses Yii2 behaviors to extend Asset functionality
- Queue-based processing to avoid blocking uploads
- Automatic cleanup when assets are deleted
- FFmpeg shell command execution with error handling
- Progressive enhancement for HLS support (native vs HLS.js)

## Dependencies

- **Craft CMS**: ^5.3.0 (PHP framework)
- **FFmpeg**: Required system dependency for video processing
- **mikehaertl/php-shellcommand**: For executing FFmpeg commands
- **HLS.js**: Frontend JavaScript library (loaded via CDN)

## Common Patterns

### Adding New Video Quality Variants
Modify `$variantHeights` array in `PrepareHlsJob.php:44` and corresponding bitrate logic.

### Template Usage
```twig
{% include '@streamingvideo/player.twig' with { asset: video } %}
```

### GraphQL Usage
```graphql
query {
  entries {
    videoField {
      hlsPlaylistUrl
    }
  }
}
```

## Namespace & Autoloading
- PHP namespace: `buesing\streamingvideo`
- PSR-4 autoload: `src/` directory
- Plugin handle: `craft-streaming-video`
