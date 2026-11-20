=== Featherweight ===
Tags: webp, webm, image optimization, video, performance
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.8
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

= 2.1.8 =
* New: "Optimize with AI" now fixes what is safe to fix, not just lists it. It backs up first with Lifeboat, makes the safe fixes, shows each change under "Fixed for you" with an Undo button, then the AI writes a plan for what is left.

= 2.1.7 =
* Improved: the "Optimize with AI" button has a new Prasat temple design that builds up while your plan is being written and shows a tick when it is ready.
* Improved: the plan now says which AI wrote it.

= 2.1.6 =
* New: Smart AI Optimize (Prasat Premium). Add your Premium key on the Prasat Digital dashboard, then "Optimize with AI" runs a fresh check and Claude writes a step-by-step plan for your site, with a button to each fix. Only scores and check titles are sent: no user names, emails, IP addresses or site address.
* Improved: "Check again" on Dashboard > Updates now looks for new Prasat releases straight away.

= 2.1.5 =
* New: the Prasat Digital overview is now a full dashboard: website health with a severity breakdown, "What to fix first", live tiles for every plugin, recent activity, 7-day charts and the Smart AI Optimize (Premium) card.
* New: Prasat temple loading animations for checks, scans, backups and every progress bar.
* New: how much of the media library is optimized appears on the Prasat Digital dashboard.

= 2.1.4 =
* New: updates now come from the plugin's GitHub releases (github.com/thhyarith-prog), with the normal one-click "Update now" and automatic updates.

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
