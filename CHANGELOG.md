# Changelog

## 1.0.0 - 2025-08-20

Initial release!

### Added
- **Automatic HLS Generation**: Converts uploaded videos to HLS format with multiple quality variants
- **Adaptive Streaming**: Automatically switches quality based on viewer's bandwidth and device capabilities
- **Multiple Quality Levels**: Generates 1080p, 720p, 480p, 240p, and 144p variants plus source quality
- **Aspect Ratio Preservation**: Maintains original video proportions for any format (not just 16:9)
- **Smart Resolution Detection**: Only generates variants at or below the source video resolution
- **Clean File Management**: Automatically cleans up HLS files when assets are deleted
- **Cross-Platform Storage**: Works with local storage, AWS S3, and other Craft volume types
- **Frontend Integration**: Includes Twig template with HLS.js integration
- **GraphQL Support**: Adds `hlsPlaylistUrl` field to Asset GraphQL interface
- **Background Processing**: Uses Craft's queue system for non-blocking video processing
- **FFmpeg Integration**: Automatic detection and validation of FFmpeg availability
- **Control Panel Status**: Shows streaming conversion status in asset metadata
- **Configurable Player**: Template supports autoplay, muted, loop, poster images, and CSS classes
- **Automatic Asset Behavior**: Extends all Asset elements with streaming video capabilities
- **Error Handling**: Comprehensive error handling and logging for troubleshooting
- **Retry Logic**: Built-in retry mechanism for upload failures
- **Processing Status Tracking**: Database tracking of conversion progress and status
