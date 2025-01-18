<?php

namespace Brickhouse\Http;

readonly final class ContentType
{
    public const Header = "Content-Type";

    /**
     * Application types
     */

    public const BIN = "application/octet-stream";
    public const BZ = "application/x-bzip";
    public const BZ2 = "application/x-bzip2";
    public const JSON = "application/json";
    public const JSONLD = "application/ld+json";
    public const OGX = "application/ogg";
    public const PDF = "application/pdf";
    public const PHP = "application/x-httpd-php";
    public const RAR = "application/vnd.rar";
    public const RTF = "application/rtf";
    public const XHTML = "application/xhtml+xml";
    public const XML = "application/xml";

    /**
     * Audio types
     */

    public const AAC = "audio/aac";
    public const OGA = "audio/ogg";
    public const OPUS = "audio/opus";
    public const WAV = "audio/wav";
    public const WEBA = "audio/webm";

    /**
     * Font types
     */

    public const OTF = "font/otf";
    public const TTF = "font/ttf";
    public const WOFF = "font/woff";
    public const WOFF2 = "font/woff2";

    /**
     * Image types
     */

    public const AVIF = "image/avif";
    public const BMP = "image/bmp";
    public const GIF = "image/gif";
    public const JPEG = "image/jpeg";
    public const PNG = "image/png";
    public const SVG = "image/svg+xml";
    public const TIF = "image/tiff";
    public const WEBP = "image/webp";

    /**
     * Text types
     */

    public const CSS = "text/css";
    public const CSV = "text/csv";
    public const HTML = "text/html";
    public const JS = "text/javascript";
    public const TXT = "text/plain";

    /**
     * Video types
     */

    public const AVI = "video/x-msvideo";
    public const OGV = "video/ogg";
    public const WEBM = "video/webm";
}
