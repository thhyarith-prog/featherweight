=== Featherweight ===
Tags: webp, webm, image optimization, video, performance
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lighter media in one click: every image to WebP and every video to WebM, served to visitors automatically. Originals are never deleted.

== Description ==

Featherweight is the media optimizer by Prasat Digital (formerly Ahnakot Media Optimizer).

* Converts JPG, PNG, GIF, BMP, TIFF and HEIC images to WebP, including every thumbnail size, with your choice of quality.
* Converts MP4, MOV, AVI, MKV, WMV and other videos to WebM (VP9 + Opus) in the background with ffmpeg.
* Serves the optimized files on the front end automatically; videos keep the original as a fallback.
* Keeps the original when the converted file would be bigger.
* New uploads are converted automatically; "Optimize everything" handles the existing library with a live progress ring and activity log.
* Originals are never deleted.

Pairs with Slipstream (page cache and speed) and Bastion Security by Prasat Digital.

== Upgrading from Ahnakot Media Optimizer ==

Activate Featherweight: it switches the old plugin off, carries over its settings and remembers which media were already converted. The .webp and .webm files keep the same names, so nothing is converted twice. You can then delete Ahnakot Media Optimizer.

== Frequently Asked Questions ==

= Videos say "needs ffmpeg" =
Video conversion uses ffmpeg with VP9 support on the server. Many hosts provide it; if yours installed it in an unusual place, enter the path under Settings. Images work without it.

== Credits ==

* Interface fonts: Inter (The Inter Project Authors) and Noto Sans Khmer (Google), SIL Open Font License 1.1, bundled in assets/fonts.

== Changelog ==

= 2.1.3 =
* Fix: declares "Update URI", so WordPress never offers an unrelated WordPress.org plugin with the same folder name as an update for this one.

= 2.1.2 =
* New: reports to the one-click "Check my website" on the Prasat Digital page.

= 2.1.1 =
* New: a new app icon for Featherweight and for the Prasat Digital menu, plus a short motto and credit (Made by Prasat Digital, founded by Thyyarith) at the bottom of every Prasat Digital screen.

= 2.1.0 =
* Renamed to Featherweight, by Prasat Digital. New Prasat Digital interface.
* Settings and conversion progress are carried over from Ahnakot Media Optimizer.

= 2.0.0 =
* Video to WebM conversion.
