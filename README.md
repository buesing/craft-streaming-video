# Craft Streaming Video Plugin

A Craft CMS plugin that automatically generates HLS (HTTP Live Streaming) variants for video assets, enabling adaptive streaming with multiple quality levels.

The plugin handles generating the variants, and includes a basic template that renders the video using [hls.js](https://github.com/video-dev/hls.js/). It doesn't apply any styling to the video, that is left up to the developer. You can also use GraphQL to handle the entire frontend yourself.

This is useful for websites hosting longer videos, which don't want to use video services to embed videos due to privacy or design concerns.

## Features

- 🎥 **Automatic HLS Generation**: Converts uploaded videos to HLS format with multiple quality variants
- 📱 **Adaptive Streaming**: Automatically switches quality based on viewer's bandwidth and device
- 🎛️ **Multiple Quality Levels**: Generates 1080p, 720p, 480p, 240p, and 144p variants (plus source quality)
- 📐 **Aspect Ratio Preservation**: Maintains original video proportions for any format (not just 16:9)
- 🔧 **Smart Resolution Detection**: Only generates variants at or below the source video resolution
- 🗂️ **Clean File Management**: Automatically cleans up HLS files when assets are deleted
- 🌐 **Cross-Platform Storage**: Works with local storage, AWS S3, and other Craft volume types
- 🎮 **Frontend Integration**: Includes Twig template and GraphQL support
- ⚡ **Background Processing**: Uses Craft's queue system for non-blocking video processing

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later
- FFmpeg installed on the server

## Installation

### 1. Install the Plugin

```bash
# Via Composer
composer require buesing/craft-streaming-video

# Install via Craft CLI
php craft plugin/install streaming-video
```

### 2. Install FFmpeg

The plugin requires FFmpeg to be installed on your server.

#### DDEV (Recommended for Development)

Add to your `.ddev/config.yaml`:

```yaml
webimage_extra_packages:
  - ffmpeg
```

Then restart DDEV:

```bash
ddev restart
```

#### Other Environments

- **Ubuntu/Debian**: `sudo apt-get install ffmpeg`
- **CentOS/RHEL**: `sudo yum install ffmpeg`
- **macOS**: `brew install ffmpeg`
- **Docker**: Include FFmpeg in your container image

## How It Works

1. **Upload Detection**: When a video asset is uploaded or saved, the plugin automatically detects it
2. **Queue Processing**: An HLS preparation job is queued to avoid blocking the upload
3. **Quality Generation**: FFmpeg generates multiple quality variants based on the source resolution
4. **File Storage**: HLS files are stored in a separate `__hls__/{asset-uid}/` folder in the same volume
5. **Cleanup**: When an asset is deleted, all associated HLS files are automatically removed

## Usage

### Frontend Templates

#### Basic Video Player

```twig
{% set video = entry.video.one() %}
{% if video and video.hlsPlaylistUrl %}
    {% include '_streamingvideo/player.twig' with { asset: video } %}
{% else %}
    <video controls>
        <source src="{{ video.url }}" type="{{ video.mimeType }}">
    </video>
{% endif %}
```

#### All Player Options

The player template supports the following options (all optional):

| Option                   | Type                | Default | Description                                                      |
|--------------------------|---------------------|---------|------------------------------------------------------------------|
| `asset`                  | Asset               | —       | The Craft Asset element (required)                               |
| `autoplay`               | bool                | false   | Autoplay the video                                               |
| `muted`                  | bool                | false   | Mute the video                                                   |
| `loop`                   | bool                | false   | Loop the video                                                   |
| `playsinline`            | bool                | false   | Play inline on mobile devices                                    |
| `poster`                 | Asset or string     | null    | Craft Asset (uses its URL) or a URL string for the poster image  |
| `classname`              | string              | ''      | CSS class(es) for the video element                              |
| `disablepictureinpicture`| bool                | false   | Disable Picture-in-Picture button                                |
| `id`                     | string              | random  | The video element's ID (random if not provided)                  |

**Example:**

```twig
{# Using a Craft Asset as poster #}
{% include '@streamingvideo/player.twig' with {
  asset: entry.video.one(),
  poster: entry.posterImage.one(),
  autoplay: true,
  muted: true,
  loop: false,
  playsinline: true,
  classname: 'rounded shadow',
  disablepictureinpicture: true,
  id: 'my-custom-video-player'
} %}

{# Using a URL as poster #}
{% include '@streamingvideo/player.twig' with {
  asset: entry.video.one(),
  poster: 'https://example.com/poster.jpg'
} %}
```

### GraphQL

The plugin adds an `hlsPlaylistUrl` field to the Asset GraphQL type:

```graphql
query {
  entries {
    ... on MySection {
      videoField {
        url
        hlsPlaylistUrl
        title
      }
    }
  }
}
```

The field will only be available once all variants have finished
rendering. Until then, you can show a fallback or the original video.

### Checking Stream Availability

```twig
{% set video = entry.video.one() %}
{% if video.canStreamVideo %}
    <p>This video supports streaming</p>
    {% if video.hlsPlaylistUrl %}
        <p>HLS streaming is available</p>
    {% else %}
        <p>HLS encoding in progress...</p>
    {% endif %}
{% endif %}
```

## Configuration

### CORS

If assets are hosted on another origin than the main site, ensure CORS access is properly configured for HLS streaming to work.

### Quality Variants

The plugin generates the following quality variants by default:

| Quality | Resolution | Video Bitrate | Audio Bitrate |
|---------|------------|---------------|---------------|
| 1080p   | 1920×1080  | 5000k         | 128k          |
| 720p    | 1280×720   | 3000k         | 128k          |
| 480p    | 854×480    | 1500k         | 96k           |
| 240p    | ~427×240   | 800k          | 64k           |
| 144p    | ~256×144   | 400k          | 64k           |
| Source  | Original   | 8000k         | 192k          |

*Note: Only variants with resolution ≤ source video are generated*

### Processing Existing Videos

If you're adding this plugin to an existing site with video assets, you'll need to resave all existing video assets to trigger HLS conversion:

```bash
# Standard Craft installation
php craft resave/assets

# Using DDEV
ddev craft resave/assets
```

This will process all existing video assets and generate HLS streaming variants in the background.

## Troubleshooting

### FFmpeg Not Found

**Error**: `ffmpeg: not found`

**Solution**: Install FFmpeg on your server (see Installation section above)

### Permission Issues

**Error**: `Failed to create temp directory`

**Solution**: Ensure the web server has write permissions to Craft's temp directory

### Large File Processing

For very large video files, you may need to:

1. Increase PHP memory limit: `ini_set('memory_limit', '1G')`
2. Increase max execution time: `ini_set('max_execution_time', 0)`
3. Configure your queue system for long-running jobs

### Debug Information

Check Craft's logs at `storage/logs/web.log` for detailed processing information:

```bash
# Follow logs in real-time
tail -f storage/logs/web.log | grep streamingvideo
```

## License

This plugin is licensed under the Craft License.

## Support

For issues and feature requests, please use the GitHub issue tracker. 
