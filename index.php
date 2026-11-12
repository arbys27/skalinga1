<?php
require_once __DIR__ . '/api/auth.php';

$auth = currentAuth();
if ($auth !== null) {
    $destination = $auth['role'] === 'youth' ? 'youth-portal.html' : 'admin-dashboard.html';
    header('Location: ' . $destination);
    exit;
}

$escapeHtml = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

// ---- SK office location (edit these) ----
$officeName    = 'SK San Antonio Office';
$officeAddress = '1582 Kaimito Street, Garcia Subdivision, Barangay San Antonio, City of Biñan, Laguna'; // shown as text under the name
$officeHours   = ''; // optional, e.g. 'Mon to Fri, 8:00 AM to 5:00 PM'. Leave empty to hide.
$officeLat     = '14.3344666';
$officeLng     = '121.0880925';
$mapEmbed      = 'https://www.google.com/maps?q=' . $officeLat . ',' . $officeLng . '&z=17&output=embed';
$mapOpen       = 'https://maps.app.goo.gl/PQzuhCUR4Bjhbz9r6';
$mapDirections = 'https://www.google.com/maps/dir/?api=1&destination=' . $officeLat . ',' . $officeLng;

$imageRoot = __DIR__ . '/assets/images';
$imageFiles = [];
$excludedImages = [];

$scanImages = static function (string $directory, string $relativeDirectory = '') use (&$scanImages, &$imageFiles, &$excludedImages, $escapeHtml): void {
    $entries = scandir($directory);
    if ($entries === false) {
        error_log('Unable to scan landing page image directory: ' . $directory);
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $absolutePath = $directory . DIRECTORY_SEPARATOR . $entry;
        $relativePath = $relativeDirectory === '' ? $entry : $relativeDirectory . '/' . $entry;
        if (is_dir($absolutePath)) {
            $scanImages($absolutePath, $relativePath);
            continue;
        }

        if (!is_file($absolutePath) || !preg_match('/\.(?:jpe?g|png|webp)$/i', $entry)) {
            continue;
        }

        if (preg_match('/(?:logo|icon|avatar|favicon)/i', $entry)) {
            $excludedImages[] = $relativePath;
            continue;
        }

        $label = preg_replace('/\.(?:jpe?g|png|webp)$/i', '', basename($entry));
        $label = preg_replace('/^event_\d+_/', '', $label);
        $label = trim(preg_replace('/[\s_-]+/', ' ', $label));
        if ($label === '' || preg_match('/^[a-f0-9-]{20,}$/i', $label)) {
            $label = 'Community moment';
        } else {
            $label = ucwords($label);
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $relativePath)));
        $imageFiles[] = [
            'path' => $relativePath,
            'src' => 'assets/images/' . $encodedPath,
            'label' => $label,
            'escapedPath' => $escapeHtml($relativePath),
            'escapedSrc' => $escapeHtml('assets/images/' . $encodedPath),
            'escapedLabel' => $escapeHtml($label),
        ];
    }
};

if (is_dir($imageRoot)) {
    $scanImages($imageRoot);
}

usort($imageFiles, static function (array $first, array $second): int {
    $byName = strcasecmp(basename($first['path']), basename($second['path']));
    return $byName !== 0 ? $byName : strcasecmp($first['path'], $second['path']);
});
sort($excludedImages, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>SKalinga – SK Barangay San Antonio, Biñan</title>
<link rel="icon" type="image/x-icon" href="/SKalinga/assets/icons/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/SKalinga/assets/icons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/SKalinga/assets/icons/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/SKalinga/assets/icons/apple-touch-icon.png">
<link rel="manifest" href="/SKalinga/assets/icons/site.webmanifest">
<meta name="msapplication-config" content="/SKalinga/assets/icons/browserconfig.xml">
<meta name="theme-color" content="#FF6B9D">
<link rel="stylesheet" href="assets/css/loader.css?v=3">
<meta name="description" content="#YanAngSKko SK San Antonio: projects, events and youth services in one place. Sign in to SKalinga.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<?php if ($imageFiles !== []): ?>
<link rel="preload" as="image" href="<?= $imageFiles[0]['escapedSrc'] ?>" fetchpriority="high">
<?php endif; ?>
<style>
:root{
  --green:#228B57; --green-deep:#0F4D31; --mint:#A8E6CF; --pink:#FF6B9D; --pink-deep:#E04F7E;
  --ink:#16303F; --muted:#5F7788; --paper:#FFF8FB; --line:color-mix(in srgb,var(--ink) 10%,white);
  --pink-tint:color-mix(in srgb,var(--pink) 14%,white); --green-tint:color-mix(in srgb,var(--mint) 32%,white);
  --h:"Bricolage Grotesque",system-ui,sans-serif; --b:"Plus Jakarta Sans",system-ui,sans-serif;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:84px}
body{margin:0;font:400 16px/1.55 var(--b);color:var(--ink);background:var(--paper);-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}
:focus-visible{outline:3px solid var(--pink-deep);outline-offset:3px}
.wrap{width:min(1180px,100% - 40px);margin-inline:auto}
h1,h2,h3{font-family:var(--h);letter-spacing:-.035em;line-height:1.08;margin:0}
.icon{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:none}

/* buttons */
.btn{display:inline-flex;align-items:center;gap:8px;min-height:48px;padding:0 22px;border:0;border-radius:999px;font:700 15px var(--b);cursor:pointer;transition:transform .15s,background .15s}
.btn:active{transform:scale(.97)}
.btn-pink{background:var(--pink);color:var(--ink);box-shadow:0 8px 24px color-mix(in srgb,var(--pink) 45%,transparent)}
.btn-pink:hover{background:color-mix(in srgb,var(--pink) 85%,var(--pink-deep))}
.btn-ghost{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.4);backdrop-filter:blur(8px)}
.btn-dark{background:var(--ink);color:#fff}

/* nav */
.nav{position:fixed;inset:0 0 auto;z-index:30;padding:12px 0;transition:background .25s,box-shadow .25s}
.nav.solid{background:rgba(255,248,251,.88);backdrop-filter:blur(16px);box-shadow:0 1px 0 var(--line)}
.nav .wrap{display:flex;align-items:center;justify-content:space-between;gap:16px}
.brand{display:flex;align-items:center;gap:10px;color:#fff;font:800 20px var(--h);letter-spacing:-.04em}
.nav.solid .brand{color:var(--ink)}
.brand img{width:38px;height:38px;border-radius:50%;border:2px solid #fff;background:#fff;object-fit:contain}
.brand img+img{margin-left:-14px}
.links{display:none;gap:4px}
.links a{padding:9px 14px;border-radius:999px;font-weight:600;font-size:14px;color:#fff}
.nav.solid .links a{color:var(--muted)}
.links a:hover{background:rgba(255,255,255,.16)}
.nav.solid .links a:hover{background:var(--pink-tint);color:var(--ink)}
@media(min-width:860px){.links{display:flex}}

/* hero carousel */
.hero{position:relative;min-height:100svh;min-height:max(640px,100svh);display:grid;grid-template-rows:minmax(0,1fr) auto;color:#fff;overflow:hidden;background:linear-gradient(135deg,var(--green),var(--green-deep))}
.hero::after{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(100deg,rgba(15,77,49,.94) 8%,rgba(15,77,49,.82) 60%,rgba(15,77,49,.74) 100%),linear-gradient(100deg,transparent 48%,rgba(224,79,126,.32) 100%);pointer-events:none}
.slide{position:absolute;inset:0;z-index:0;overflow:hidden;opacity:0;background:linear-gradient(135deg,var(--green),var(--green-deep));transition:opacity .8s ease}
.slide.on{z-index:0;opacity:1}
.slide img{width:100%;height:100%;object-fit:cover;transform:scale(1);will-change:transform}
.slide.on img{animation:ken-burns 12s ease-out both}
.slide.image-failed img{display:none}
@keyframes ken-burns{from{transform:scale(1)}to{transform:scale(1.1)}}
#hero-carousel > .wrap{position:relative;z-index:2;align-self:center;padding:120px 0 28px}
.tag{display:inline-flex;align-items:center;gap:8px;padding:7px 14px;border-radius:999px;background:var(--pink);color:var(--ink);font:700 13px var(--b)}
.hero h1{font-size:clamp(40px,8vw,88px);margin:20px 0 16px;max-width:12ch}
.hero h1 em{font-style:normal;color:var(--mint)}
.hero p.lead{max-width:50ch;font-size:clamp(16px,2vw,19px);color:rgba(255,255,255,.88);margin:0 0 28px}
.cta{display:flex;flex-wrap:wrap;gap:12px}
.hero .tag,.hero h1,.hero .lead,.hero .cta{animation:hero-reveal .7s cubic-bezier(.2,.7,.25,1) both}
.hero h1{animation-delay:.08s}
.hero .lead{animation-delay:.16s}
.hero .cta{animation-delay:.24s}
@keyframes hero-reveal{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.caption{position:absolute;z-index:2;left:0;right:0;bottom:84px}
.ctrl{position:relative;z-index:3;align-self:end;width:100%;padding:0 0 24px}
.ctrl .wrap{display:flex;align-items:center;gap:10px;padding:0}
.dots{display:flex;gap:4px;flex:1;min-width:0;overflow-x:auto;scrollbar-width:none}
.dots::-webkit-scrollbar{display:none}
.dot{width:34px;height:44px;flex:0 0 34px;border:0;padding:0;border-radius:9px;background:transparent;cursor:pointer;overflow:hidden;position:relative}
.dot::before{content:"";position:absolute;inset-inline:0;top:50%;height:5px;border-radius:9px;background:rgba(255,255,255,.35);transform:translateY(-50%)}
.dot.on::after{content:"";position:absolute;inset-inline:0;top:calc(50% - 2.5px);height:5px;border-radius:9px;background:var(--pink);animation:fill 5.5s linear}
.hero.is-paused .dot.on::after{animation-play-state:paused}
@keyframes fill{from{transform:translateX(-100%)}to{transform:none}}
.arrow{width:46px;height:46px;border-radius:50%;border:1px solid rgba(255,255,255,.4);background:rgba(255,255,255,.12);color:#fff;cursor:pointer;display:grid;place-items:center}
.arrow:hover:not(:disabled){background:var(--pink);color:var(--ink)}
.arrow:disabled{opacity:.5;cursor:not-allowed}
.slide-count{min-width:64px;color:#fff;font-size:12px;font-variant-numeric:tabular-nums;font-weight:700;text-align:center}
.slide-note{font-size:13px;color:rgba(255,255,255,.8);font-weight:600}

/* sections */
section.s{padding:clamp(64px,9vw,120px) 0}
.head{max-width:640px;margin-bottom:40px}
.head h2{font-size:clamp(30px,5vw,52px)}
.head p{color:var(--muted);margin:14px 0 0}

/* achievements band */
.band{background:var(--green-deep);color:#fff}
.stats{display:grid;grid-template-columns:repeat(2,1fr);gap:28px 16px}
.stat b{display:block;font:800 clamp(36px,6vw,60px) var(--h);letter-spacing:-.04em;color:var(--mint)}
.stat span{font-size:14px;color:rgba(255,255,255,.85)}
@media(min-width:760px){.stats{grid-template-columns:repeat(4,1fr)}}

/* featured project */
.feature{display:grid;gap:28px;align-items:center}
.feature .shot{border-radius:28px;overflow:hidden;aspect-ratio:4/3;background:var(--pink) var(--bg) center/cover;box-shadow:0 20px 50px color-mix(in srgb,var(--pink) 35%,transparent)}
.feature .shot img{width:100%;height:100%;object-fit:cover}
.feature h3{font-size:clamp(28px,4vw,42px);margin:10px 0 14px}
.feature p{color:var(--muted);max-width:52ch}
.pill{display:inline-block;padding:5px 12px;border-radius:999px;background:var(--pink-tint);color:var(--green-deep);font:700 12px var(--b)}
@media(min-width:860px){.feature{grid-template-columns:1.05fr 1fr;gap:56px}}

/* activities slider */
.rail{display:flex;gap:16px;overflow-x:auto;scroll-snap-type:x mandatory;padding-bottom:14px;scrollbar-width:thin;scrollbar-color:var(--pink) transparent}
.card{flex:0 0 min(82%,330px);scroll-snap-align:start;border-radius:24px;overflow:hidden;background:#fff;border:1px solid var(--line)}
.card .pic{aspect-ratio:4/3;background:var(--bg) center/cover,linear-gradient(135deg,var(--mint),var(--pink))}
.card div.t{padding:16px 18px 20px}
.card h3{font-size:20px;margin-bottom:6px}
.card p{margin:0;font-size:14px;color:var(--muted)}

/* moments center-focus carousel */
.gallery{position:relative;isolation:isolate;overflow:hidden;padding-top:clamp(44px,7vw,84px)!important}
.gallery::before{position:absolute;z-index:-1;inset:-10%;content:"";background-image:linear-gradient(120deg,color-mix(in srgb,var(--mint) 58%,transparent),color-mix(in srgb,var(--pink) 25%,transparent)),var(--moments-image,linear-gradient(120deg,var(--mint),var(--pink)));background-position:center;background-size:cover;filter:blur(60px) saturate(1.1);opacity:.22;transform:scale(1.1);transition:background-image .5s ease,opacity .5s ease}
.moments-shell{position:relative}
.moments-track{display:flex;align-items:center;gap:clamp(14px,2.2vw,28px);overflow-x:auto;overflow-y:hidden;padding:24px max(24px,calc((100% - min(78vw,760px))/2)) 32px;scroll-snap-type:x mandatory;scroll-behavior:smooth;scrollbar-width:none;-webkit-overflow-scrolling:touch;touch-action:pan-x;cursor:grab}
.moments-track::-webkit-scrollbar{display:none}
.moments-track.is-dragging{cursor:grabbing;scroll-snap-type:none;scroll-behavior:auto;user-select:none}
.moments-slide{flex:0 0 min(78vw,760px);min-width:0;aspect-ratio:16/10;overflow:hidden;border:1px solid rgba(255,255,255,.48);border-radius:clamp(24px,3vw,32px);background:linear-gradient(135deg,var(--mint),var(--green));box-shadow:0 24px 64px color-mix(in srgb,var(--green-deep) 22%,transparent);opacity:.55;filter:blur(2px) brightness(.78);transform:scale(.86);scroll-snap-align:center;transition:transform .6s cubic-bezier(.22,1,.36,1),opacity .6s cubic-bezier(.22,1,.36,1),filter .6s cubic-bezier(.22,1,.36,1),border-color .6s ease,box-shadow .6s ease}
.moments-slide.is-active{border-color:rgba(255,255,255,.92);box-shadow:0 30px 80px color-mix(in srgb,var(--green-deep) 28%,transparent),0 0 0 1px rgba(255,255,255,.3);opacity:1;filter:none;transform:scale(1)}
.moments-image{display:block;width:100%;height:100%;overflow:hidden;padding:0;border:0;border-radius:inherit;background:transparent;cursor:zoom-in}
.moments-image img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .8s cubic-bezier(.22,1,.36,1)}
.moments-slide.image-failed .moments-image{background:linear-gradient(135deg,var(--green),var(--green-deep))}
.moments-slide.image-failed .moments-image img{visibility:hidden}
.moments-slide:focus-within{outline:3px solid var(--pink-deep);outline-offset:5px}
.moments-controls{display:flex;align-items:center;gap:12px;width:min(760px,100%);margin:0 auto;padding:0 4px}
.moments-control{display:grid;flex:0 0 48px;width:48px;height:48px;place-items:center;border:1px solid rgba(255,255,255,.82);border-radius:50%;color:var(--green-deep);background:rgba(255,255,255,.62);backdrop-filter:blur(14px);box-shadow:0 8px 24px color-mix(in srgb,var(--green-deep) 12%,transparent);cursor:pointer;transition:transform .2s ease,background .2s ease}
.moments-control:hover{transform:scale(1.06);background:rgba(255,255,255,.9)}
.moments-control:active{transform:scale(.96)}
.moments-control:disabled{opacity:.45;cursor:not-allowed}
.moments-progress{height:4px;min-width:30px;flex:1;overflow:hidden;border-radius:999px;background:color-mix(in srgb,var(--pink) 24%,white)}
.moments-progress-fill{display:block;width:100%;height:100%;border-radius:inherit;background:var(--pink-deep);transform:scaleX(.1);transform-origin:left center;transition:transform .6s cubic-bezier(.22,1,.36,1)}
.moments-count{min-width:66px;color:var(--ink);font-size:12px;font-weight:700;font-variant-numeric:tabular-nums;text-align:center}
.moments-lightbox{position:fixed;inset:0;z-index:60;display:grid;place-items:center;padding:20px;background:rgba(15,77,49,.96);backdrop-filter:blur(16px)}
.moments-lightbox[hidden]{display:none}
.moments-lightbox-panel{position:relative;display:grid;width:min(100%,1440px);height:min(92svh,1000px);grid-template-rows:minmax(0,1fr) auto;gap:16px}
.moments-lightbox-image-wrap{display:grid;min-height:0;place-items:center;overflow:hidden;border:1px solid rgba(255,255,255,.26);border-radius:28px;background:linear-gradient(135deg,var(--green),var(--green-deep))}
.moments-lightbox-image{display:block;width:100%;height:100%;object-fit:contain}
.moments-lightbox-actions{display:flex;align-items:center;justify-content:center;gap:14px}
.moments-lightbox-actions .moments-count{color:#fff}
.moments-lightbox-close{position:absolute;top:16px;right:16px;z-index:1}
.moments-lightbox .moments-control{color:#fff;background:rgba(15,77,49,.64);border-color:rgba(255,255,255,.7)}
.moments-lightbox .moments-control:hover{background:rgba(255,255,255,.22)}
.moments-control:focus-visible{outline:3px solid var(--pink);outline-offset:4px}
@media(hover:hover) and (pointer:fine){.moments-slide.is-active:hover .moments-image img{transform:scale(1.035)}}
@media(max-width:600px){.moments-track{padding-top:16px;padding-bottom:24px}.moments-controls{gap:8px}.moments-control{flex-basis:46px;width:46px;height:46px}.moments-count{min-width:58px}.moments-lightbox{padding:10px}.moments-lightbox-panel{height:88svh}.moments-lightbox-image-wrap{border-radius:22px}}
@media(prefers-reduced-motion:reduce){.moments-track{scroll-behavior:auto}.moments-slide,.moments-image img,.moments-progress-fill,.moments-control,.gallery::before{transition:none!important}}

/* services */
.services{background:var(--green-tint)}
.grid{display:grid;gap:14px;grid-template-columns:repeat(2,1fr)}
.svc{display:flex;flex-direction:column;gap:10px;padding:20px;border-radius:24px;background:#fff;border:1px solid var(--line);transition:transform .2s,box-shadow .2s}
.svc:hover{transform:translateY(-3px);box-shadow:0 16px 34px color-mix(in srgb,var(--green) 16%,transparent)}
.svc .ic{width:48px;height:48px;border-radius:16px;display:grid;place-items:center;background:var(--green-tint);color:var(--green)}
.svc:nth-child(even) .ic{background:var(--pink-tint);color:var(--pink-deep)}
.svc h3{font-size:18px}
.svc p{margin:0;font-size:13.5px;color:var(--muted)}
@media(min-width:860px){.grid{grid-template-columns:repeat(3,1fr);gap:18px}.svc{padding:26px}}

/* sign-in CTA */
.signin-band{background:var(--pink);color:var(--ink);border-radius:32px;padding:clamp(32px,6vw,64px);display:grid;gap:20px;align-items:center}
.signin-band h2{font-size:clamp(30px,5vw,50px)}
.signin-band p{margin:8px 0 0;color:var(--ink);max-width:48ch}
@media(min-width:760px){.signin-band{grid-template-columns:1fr auto}}

/* footer */
footer{background:var(--green-deep);color:rgba(255,255,255,.85);padding:56px 0 32px;font-size:14px}
footer .wrap{display:grid;gap:32px}
footer b{display:inline-flex;align-items:center;gap:10px;font:800 22px var(--h);color:#fff;margin-bottom:8px}
.footer-heading{font:700 12px var(--b);text-transform:uppercase;letter-spacing:.08em;color:var(--mint);margin:0 0 14px}
.footer-links{display:flex;flex-direction:column;gap:8px;align-items:flex-start}
.footer-link{
  display:inline-flex;align-items:center;gap:10px;
  padding:8px 14px;border-radius:12px;
  background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);
  color:#fff;font-weight:600;
  transition:background .2s ease,border-color .2s ease,transform .15s ease,color .2s ease;
}
.footer-link .icon{width:18px;height:18px;color:var(--mint);transition:transform .2s ease,color .2s ease}
.footer-link:hover{
  background:rgba(255,255,255,.16);border-color:var(--mint);
  color:var(--mint);transform:translateY(-2px);
}
.footer-link:hover .icon{color:var(--pink);transform:scale(1.08)}
.footer-link:active{transform:scale(.98)}
.footer-bottom{
  margin-top:36px;padding-top:20px;
  border-top:1px solid rgba(255,255,255,.12);
  display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;
  font-size:13px;color:rgba(255,255,255,.65);
}
@media(min-width:760px){footer .wrap{grid-template-columns:1.3fr 1fr 1.2fr}}

@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}}
@media(prefers-reduced-motion:reduce){.slide.on img{transform:none!important;animation:none!important}}
.hero{min-height: min(800px, 100svh);min-height:max(620px,min(800px,100svh))}
#hero-carousel > .wrap{padding-top:140px;padding-bottom:180px}
.slides{position:absolute;inset:0}
.slide{background:linear-gradient(135deg,var(--green),var(--green-deep))}
.slide img{width:100%;height:100%;object-fit:cover}
.slide-note{min-width:0;flex:0 1 auto;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ctrl .wrap{gap:10px}
.arrow{width:44px;height:44px;flex:0 0 44px}
.arrow:focus-visible,.dot:focus-visible{outline-color:var(--mint);outline-offset:4px}
.pause-label{font-size:18px;font-weight:700;line-height:1}
.content-state{display:flex;align-items:flex-end;min-height:320px;width:100%;padding:20px;border:1px solid var(--line);border-radius:24px;color:var(--muted);background:linear-gradient(105deg,#fff 25%,color-mix(in srgb,var(--mint) 32%,white) 45%,#fff 65%);background-size:220% 100%;animation:skeleton-shimmer 1.5s ease-in-out infinite}
@keyframes skeleton-shimmer{to{background-position:-220% 0}}
.card{display:flex;flex-direction:column}
.card .pic{width:100%;flex:0 0 auto;background:linear-gradient(135deg,var(--mint),var(--green))}
.card .pic img{width:100%;height:100%;object-fit:cover}
.card .t{flex:1}
.card .meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;color:var(--muted);font-size:12px;font-variant-numeric:tabular-nums}
.card .meta span{display:inline-flex;align-items:center;gap:5px}
.card .meta .icon{width:16px;height:16px}
.empty-activities{width:100%;padding:24px;border:1px dashed color-mix(in srgb,var(--green) 35%,white);border-radius:20px;color:var(--muted)}

/* Archived Yan Ang SK Ko content */
.legacy-signup{background:linear-gradient(135deg,color-mix(in srgb,var(--mint) 36%,white),#fff)}
.legacy-signup .feature h2,.legacy-join h2{font-size:clamp(30px,5vw,48px);margin:10px 0 14px}
.legacy-signup .feature p,.legacy-join p{color:var(--muted);max-width:60ch}
.legacy-updates{background:var(--green-tint)}
.legacy-posts{display:grid;grid-template-columns:1fr;gap:16px}
.legacy-post{display:flex;flex-direction:column;align-items:flex-start;padding:clamp(20px,3vw,30px);border:1px solid var(--line);border-radius:24px;background:#fff;box-shadow:0 12px 30px color-mix(in srgb,var(--green-deep) 6%,transparent)}
.legacy-meta{display:flex;flex-wrap:wrap;gap:8px 12px;margin-bottom:14px;color:var(--muted);font-size:12px;font-weight:700}
.legacy-meta span{color:var(--green-deep);text-transform:uppercase;letter-spacing:.06em}
.legacy-post h3{font-size:clamp(20px,2.4vw,25px);margin-bottom:10px}
.legacy-post p{margin:0 0 18px;color:var(--muted);font-size:14px}
.legacy-post{min-height:100%}
@media(min-width:700px){.legacy-posts{grid-template-columns:repeat(2,minmax(0,1fr))}}
.legacy-month-grid{display:grid;grid-template-columns:1fr;gap:16px}
.legacy-month{overflow:hidden;border:1px solid var(--line);border-radius:24px;background:#fff}
.legacy-month img{width:100%;aspect-ratio:16/10;object-fit:cover;background:var(--green-tint)}
.legacy-month>div{padding:18px 20px 22px}
.legacy-month-label{color:var(--pink-deep);font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.legacy-month h3{margin:8px 0;font-size:21px}
.legacy-month p{margin:0;color:var(--muted);font-size:14px}
@media(min-width:700px){.legacy-month-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1000px){.legacy-month-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.legacy-join-card{display:grid;overflow:hidden;align-items:center;border-radius:30px;background:var(--green-deep);color:#fff}
.legacy-join-card>img{width:100%;height:100%;min-height:240px;max-height:420px;object-fit:cover}
.legacy-join-card>div{padding:clamp(26px,5vw,54px)}
.legacy-join-card p{color:rgba(255,255,255,.82)}
.legacy-join-card .pill{background:var(--mint)}
@media(min-width:860px){.legacy-join-card{grid-template-columns:1fr 1fr}}
.legacy-office-photo{display:block;width:112px;height:112px;object-fit:contain;align-self:flex-start;border-radius:16px;background:var(--green-tint)}
.legacy-contact-row a{color:inherit;text-decoration:underline;text-decoration-color:color-mix(in srgb,var(--green) 42%,transparent);text-underline-offset:3px}
.legacy-contact-row a:hover{color:var(--pink-deep)}
@media(max-width:600px){
  .wrap{width:min(100% - 32px,1180px)}
  .nav{padding:10px 0}
  .nav .wrap{gap:8px}
  .brand{gap:8px;font-size:18px}
  .brand img{width:34px;height:34px}
  .brand img+img{margin-left:-13px}
  .nav>.wrap>.btn{min-height:44px;padding:0 14px;font-size:13px}
  .hero{min-height:max(660px,100svh)}
  #hero-carousel > .wrap{padding-top:118px;padding-bottom:24px}
  .hero h1{font-size:clamp(40px,12vw,62px)}
  .hero .tag{max-width:100%;font-size:12px}
  .hero p.lead{max-width:100%;font-size:16px;overflow-wrap:anywhere}
  .hero .cta{width:100%;flex-direction:column;align-items:flex-start;gap:10px}
  .hero .cta .btn{width:fit-content;max-width:100%;min-height:50px;padding:0 18px;white-space:normal}
  .hero .cta .btn-ghost{justify-content:center;text-align:center}
  .ctrl{padding-bottom:18px}
  .ctrl .wrap{gap:4px}
  .slide-note{display:none}
  .arrow{width:44px;height:44px;flex-basis:44px}
  .slide-count{min-width:52px;font-size:12px}
  .dot{width:24px;flex-basis:24px}
  .feature .shot{min-height:240px}
  .grid{gap:10px}
  .svc{padding:16px;border-radius:20px}
  .svc h3{font-size:16px}
  .svc p{font-size:13px}
  .head{margin-bottom:24px}
  #activities .head p{font-size:14px}
  .rail{width:100%;max-width:100%;gap:12px;padding-bottom:10px}
  .card{flex-basis:min(76vw,270px);border-radius:18px}
  .card .pic{aspect-ratio:16/10}
  .card .t{padding:13px 14px 15px}
  .card h3{font-size:17px}
  .card p{display:-webkit-box;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2;font-size:13px;line-height:1.45}
  .card .meta{gap:6px;margin-top:10px;font-size:11px}
}
@media(max-width:360px){
  .wrap{width:calc(100% - 28px)}
  .brand{font-size:16px}
  .nav>.wrap>.btn{padding:0 11px}
  .grid{grid-template-columns:1fr}
  .hero .cta .btn{min-height:48px;padding-inline:15px;font-size:14px}
  .feature .shot{min-height:0}
  .ctrl .wrap{gap:4px}
  .dot{width:24px;flex-basis:24px}
}
/* office location */
.locate{display:grid;gap:20px;align-items:stretch}
.locate-info{padding:clamp(24px,4vw,40px);border-radius:28px;background:#fff;border:1px solid var(--line);display:flex;flex-direction:column;gap:14px;justify-content:center}
.locate-info h2{font-size:clamp(28px,4.5vw,44px)}
.locate-info p{margin:0;color:var(--muted)}
.locate-row{display:flex;gap:12px;align-items:flex-start;color:var(--ink);font-weight:600}
.locate-row .icon{margin-top:2px;color:var(--pink-deep)}
.locate-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:6px}
.map-frame{position:relative;min-height:340px;border-radius:28px;overflow:hidden;border:1px solid var(--line);background:linear-gradient(135deg,var(--mint),var(--green));box-shadow:0 20px 50px color-mix(in srgb,var(--green) 18%,transparent)}
.map-frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
@media(min-width:860px){.locate{grid-template-columns:.8fr 1.2fr;gap:28px}.map-frame{min-height:420px}}
</style>
</head>
<body>
<script src="assets/js/loader.js?v=3"></script>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-cal" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/></symbol>
  <symbol id="i-box" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 8 9 5 9-5M3 8v9l9 5 9-5V8M12 13v9"/></symbol>
  <symbol id="i-print" viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/></symbol>
  <symbol id="i-heart" viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/></symbol>
  <symbol id="i-bell" viewBox="0 0 24 24"><path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2ZM18 16v-5a6 6 0 0 0-5-5.9V4a1 1 0 0 0-2 0v1.1A6 6 0 0 0 6 11v5l-2 2h16l-2-2Z"/></symbol>
  <symbol id="i-file" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></symbol>
  <symbol id="i-id" viewBox="0 0 24 24"><rect x="2.5" y="5" width="19" height="14" rx="3"/><circle cx="9" cy="11" r="2"/><path d="M5.5 16a3.8 3.8 0 0 1 7 0M15 10h3M15 14h3"/></symbol>
  <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
  <symbol id="i-left" viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
  <symbol id="i-fb" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></symbol>
  <symbol id="i-phone" viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.2-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.8 2.1Z"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></symbol>
  <symbol id="i-ext" viewBox="0 0 24 24"><path d="M7 17 17 7M7 7h10v10"/></symbol>
</svg>

<header class="nav" id="nav">
  <div class="wrap">
    <a class="brand" href="#top" aria-label="SKalinga home">
      <img src="assets/images/logosanantonio.jpg" alt="San Antonio logo" width="38" height="38">
      <img src="assets/images/sklogo.jpg" alt="Sangguniang Kabataan logo" width="38" height="38">
      <span>SKalinga</span>
    </a>
    <nav class="links" aria-label="Main">
<a href="#projects">Projects</a><a href="#activities">Activities</a><a href="#youth-updates">Youth updates</a><a href="#services">Services</a><a href="#location">Find us</a><a href="#contact">Contact</a>    </nav>
    <a class="btn btn-pink" href="login.html">Sign in</a>
  </div>
</header>

<main id="top">
  <section class="hero" id="hero-carousel" role="region" aria-roledescription="carousel" aria-label="SK San Antonio highlights" tabindex="0">
    <div class="slides" id="slides" aria-live="off">
      <?php if ($imageFiles !== []): ?>
        <?php foreach ($imageFiles as $index => $image): ?>
          <div class="slide<?= $index === 0 ? ' on' : '' ?>" role="group" aria-roledescription="slide" aria-label="<?= $index + 1 ?> of <?= count($imageFiles) ?>: <?= $image['escapedLabel'] ?>" aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>" data-index="<?= $index ?>">
            <img src="<?= $image['escapedSrc'] ?>" alt="<?= $image['escapedLabel'] ?> — SK San Antonio community activity" width="1600" height="900"<?= $index === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?> decoding="async">
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="slide on" role="group" aria-roledescription="slide" aria-label="1 of 1: SK San Antonio community highlights" aria-hidden="false" data-index="0"></div>
      <?php endif; ?>
    </div>

    <div class="wrap">
      <span class="tag">#YanAngSKko SKSanAntonio!</span>
      <h1>Kabataan na <em>kumikilos</em> para sa San Antonio.</h1>
      <p class="lead">Join events, borrow equipment, request prints, get health updates and see how your SK works for you, all in one portal.</p>
      <div class="cta">
        <a class="btn btn-pink" href="login.html">Sign in to SKalinga <svg class="icon"><use href="#i-arrow"/></svg></a>
        <a class="btn btn-ghost" href="#youth-updates">Explore youth updates</a>
      </div>
    </div>

    <div class="ctrl"><div class="wrap">
      <div class="dots" id="dots"></div>
      <span class="slide-count" id="slide-count" aria-live="polite" aria-atomic="true">01 / <?= str_pad((string)max(1, count($imageFiles)), 2, '0', STR_PAD_LEFT) ?></span>
      <button class="arrow" id="pause" type="button" aria-label="Pause carousel" aria-pressed="false"><span aria-hidden="true">Ⅱ</span></button>
      <button class="arrow" id="prev" type="button" aria-label="Previous slide"><svg class="icon"><use href="#i-left"/></svg></button>
      <button class="arrow" id="next" type="button" aria-label="Next slide"><svg class="icon"><use href="#i-arrow"/></svg></button>
    </div></div>
  </section>

  <section class="s gallery" id="moments">
    <div class="wrap">
      <div class="head"><h2>Moments and activities</h2><p>A glimpse of the people, projects, and places that make San Antonio youth strong.</p></div>
      <div class="moments-shell" id="moments-shell">
        <div class="moments-track" id="moments-track" role="region" aria-roledescription="carousel" aria-label="Moments and activities photo carousel" tabindex="0">
        <?php if ($imageFiles !== []): ?>
          <?php foreach ($imageFiles as $index => $image): ?>
            <article class="moments-slide<?= $index === 0 ? ' is-active' : '' ?>" role="group" aria-roledescription="slide" aria-label="<?= $index + 1 ?> of <?= count($imageFiles) ?>" aria-current="<?= $index === 0 ? 'true' : 'false' ?>">
              <button class="moments-image" type="button" aria-label="Open community photo <?= $index + 1 ?> of <?= count($imageFiles) ?>" aria-haspopup="dialog">
                <img src="<?= $image['escapedSrc'] ?>" alt="Community activity: <?= $image['escapedLabel'] ?>" width="1200" height="750" loading="<?= $index < 3 ? 'eager' : 'lazy' ?>"<?= $index === 0 ? ' fetchpriority="high"' : '' ?> decoding="async">
              </button>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="empty-activities">New community photos will appear here soon.</p>
        <?php endif; ?>
        </div>
        <?php if ($imageFiles !== []): ?>
          <div class="moments-controls" aria-label="Gallery controls">
            <button class="moments-control" id="moments-prev" type="button" aria-label="Previous photo"<?= count($imageFiles) < 2 ? ' disabled' : '' ?>><svg class="icon"><use href="#i-left"/></svg></button>
            <div class="moments-progress" role="progressbar" aria-label="Gallery position" aria-valuemin="1" aria-valuemax="<?= count($imageFiles) ?>" aria-valuenow="1"><span class="moments-progress-fill" id="moments-progress-fill"></span></div>
            <span class="moments-count" id="moments-count" aria-live="polite" aria-atomic="true">01 / <?= str_pad((string)count($imageFiles), 2, '0', STR_PAD_LEFT) ?></span>
            <button class="moments-control" id="moments-next" type="button" aria-label="Next photo"<?= count($imageFiles) < 2 ? ' disabled' : '' ?>><svg class="icon"><use href="#i-arrow"/></svg></button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ACHIEVEMENTS: edit the numbers to your real data -->
  <section class="s band" aria-label="Achievements">
    <div class="wrap stats">
      <div class="stat"><b data-count="25">0</b><span>Projects delivered</span></div>
      <div class="stat"><b data-count="40">0</b><span>Youth events and activities</span></div>
      <div class="stat"><b data-count="1200">0</b><span>Kabataan reached</span></div>
      <div class="stat"><b data-count="6">0</b><span>Online services in SKalinga</span></div>
    </div>
  </section>

  <section class="s" id="projects">
    <div class="wrap feature">
      <div class="shot"><img src="assets/images/slides/alertoboard.jpg" alt="SK members posting the Kabataan Alerto Board" loading="lazy" width="960" height="720"></div>
      <div>
        <span class="pill">Featured project</span>
        <h3>Kabataan Alerto Board</h3>
        <p>Emergency hotlines for police, fire, health and barangay response, posted in stores, homes and public spaces across San Antonio so help is one call away for everyone.</p>
        <p>Made by SK members who put every board up themselves.</p>
        <a class="btn btn-dark" href="#activities">More SK activities <svg class="icon"><use href="#i-arrow"/></svg></a>
      </div>
    </div>
  </section>

  <section class="s" id="activities" style="padding-top:0">
    <div class="wrap">
      <div class="head"><h2>What we've been up to</h2><p>Sports, health drives, clean-ups and learning sessions. Swipe through recent SK activities.</p></div>
      <div class="rail" id="activity-cards" tabindex="0" aria-label="SK activities" aria-live="polite">
        <p class="content-state" role="status">Loading youth activities…</p>
      </div>
    </div>
  </section>

  <section class="s legacy-updates" id="youth-updates">
    <div class="wrap">
      <div class="head">
        <span class="pill">Mga kuwento ng kabataan</span>
        <h2>San Antonio Youth Updates</h2>
        <p>Mga balita, tagumpay, at gawaing pangkabataan na nagbigay-buhay sa mga programa ng Sangguniang Kabataan ng Barangay San Antonio.</p>
      </div>
      <div class="legacy-posts">
        <article class="legacy-post">
          <div class="legacy-meta"><span>SK Sport Update</span><time datetime="2025-07-13">July 13, 2025</time></div>
          <h3>Tuloy-tuloy na Pag-arangkada!</h3>
          <p>Noong Hulyo 13, 2025, wagi ang San Antonio Under-21 laban sa Barangay San Francisco sa iskor na 81–76 sa Biñan City Inter-Barangay Basketball League 2025 sa Brgy. Tubigan Covered Court. Ipinakita ng koponan ang lakas, talino, at disiplina sa dikdikang laban. Pasasalamat din sa mga manlalaro, coach, at komunidad sa kanilang suporta.</p>
        </article>
        <article class="legacy-post">
          <div class="legacy-meta"><span>SK Advisory</span><time datetime="2025-07-11">July 11, 2025</time></div>
          <h3>Laging Ligtas Exhibit 2025</h3>
          <p>Nakiisa ang SK San Antonio sa ikalimang Laging Handa, Laging Ligtas: Survival and Rescue Night Exhibit, sa pangunguna ni Kapitan Michael “Moro” Gonzales. Bahagi ito ng National Disaster Resilience Month 2025 at paalala na mahalaga ang paghahanda at kaalaman sa sakuna upang mapatatag at mapangalagaan ang komunidad.</p>
        </article>
        <article class="legacy-post">
          <div class="legacy-meta"><span>SK Achievements</span><time datetime="2025-07-11">July 11, 2025</time></div>
          <h3>SK Noel Marcellana Kinilala bilang Bagong Pangulo ng Biñan Leo Club</h3>
          <p>Isang karangalan para sa SK San Antonio ang pagkakahalal ni SK Chairperson Noel L. Marcellana Jr. bilang Pangulo ng Biñan Leo Club para sa taong 2025–2026. Kasama sa bagong yugto ng paglilingkod ang pakikipagtulungan sa Leo at Lions community, at pagpapatuloy ng mga gawaing naglilingkod sa kabataan sa loob at labas ng barangay.</p>
        </article>
        <article class="legacy-post">
          <div class="legacy-meta"><span>SK Project</span><time datetime="2025-07-11">July 11, 2025</time></div>
          <h3>Alagang Bata With Cong. Len Alonte</h3>
          <p>Noong Hulyo 11, 2025, nakiisa ang SK San Antonio sa partnership outreach program sa Jollibee Umbria bilang pagdiriwang sa kaarawan ni Congresswoman Len Alonte. Sa tulong ng mga katuwang at boluntaryo, naghatid ang programa ng ligaya, pag-asa, at serbisyong may malasakit para sa mga kabataan.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="s legacy-months">
    <div class="wrap">
      <div class="head">
        <h2>Maraming Salamat sa Inyong Tiwala</h2>
        <p>Magkakasama tayong nagtagumpay sa mga programa na ating itinaguyod para sa ating Barangay. Silipin ang mga highlight ng unang anim na buwan ng 2025.</p>
      </div>
      <div class="legacy-month-grid">
        <article class="legacy-month">
          <img src="assets/legacy/yanangskko/june-2025.jpeg" alt="SK San Antonio June 2025 community highlights" loading="lazy" width="1200" height="800">
          <div><span class="legacy-month-label">June 2025</span><h3>Celebrating a Colorful JUNE with Pride</h3><p>Isang matagumpay na buwan para sa SK San Antonio, sunod-sunod na naisakatuparan ang mga proyektong naglalayong itaguyod ang kalusugan, kaayusan, at kapakanan ng kabataan sa ating komunidad.</p></div>
        </article>
        <article class="legacy-month">
          <img src="assets/legacy/yanangskko/may-2025.jpeg" alt="SK San Antonio May 2025 community highlights" loading="lazy" width="1200" height="800">
          <div><span class="legacy-month-label">May 2025</span><h3>Mayo na may Kulay at Saysay</h3><p>Isang makulay at makabuluhang buwan. Buong puso kaming nagpapasalamat sa patuloy ninyong suporta at pakikiisa sa mga programang isinulong para sa kabataan at komunidad.</p></div>
        </article>
        <article class="legacy-month">
          <img src="assets/legacy/yanangskko/april-2025.jpeg" alt="SK San Antonio April 2025 community highlights" loading="lazy" width="1200" height="800">
          <div><span class="legacy-month-label">April 2025</span><h3>Apir, para sa Mainit at Makabuluhang Abril</h3><p>Sunod-sunod na naisakatuparan ang mga aktibidad at proyektong naglalayong itaguyod ang malasakit, partisipasyon, at kapakanan ng kabataan sa ating komunidad.</p></div>
        </article>
        <article class="legacy-month">
          <img src="assets/legacy/yanangskko/march-2025.jpeg" alt="SK San Antonio March 2025 community highlights" loading="lazy" width="1200" height="800">
          <div><span class="legacy-month-label">March 2025</span><h3>Marching Forward with a Mission</h3><p>Isang buwan ng pagkatuto at paglinang ng kakayahan sa pamamagitan ng aktibong pakikibahagi sa mga programa at pagsasanay para sa mas mahusay na paglilingkod.</p></div>
        </article>
        <article class="legacy-month">
          <img src="assets/legacy/yanangskko/february-2025.jpeg" alt="SK San Antonio February 2025 community highlights" loading="lazy" width="1200" height="800">
          <div><span class="legacy-month-label">February 2025</span><h3>Makabuluhan at Produktibong Pebrero</h3><p>Mula sa pagdiriwang ng anibersaryo ng Biñan, hanggang sa mga aral mula sa webinar at mga inisyatibong pangkalikasan—lahat ay bahagi ng patuloy na paglilingkod.</p></div>
        </article>
        <article class="legacy-month">
          <img src="assets/legacy/yanangskko/january-2025.jpeg" alt="SK San Antonio January 2025 community highlights" loading="lazy" width="1200" height="800">
          <div><span class="legacy-month-label">January 2025</span><h3>Masigasig at Produktibong Enero</h3><p>Sinimulan ang taon nang may sigasig sa paglilingkod. Sa bawat aktibidad at programang naisakatuparan, dama ang pagkakaisa at malasakit para sa mas maunlad na komunidad.</p></div>
        </article>
      </div>
    </div>
  </section>

  <section class="s legacy-join">
    <div class="wrap legacy-join-card">
      <img src="assets/legacy/yanangskko/join-sk-cta.png" alt="Youth leaders of Barangay San Antonio" loading="lazy" width="1200" height="800">
      <div>
        <span class="pill">Makiisa sa Sangguniang Kabataan</span>
        <h2>Samahan ninyo kami.</h2>
        <p>Samahan ninyo kami sa layuning bigyang-kapangyarihan ang kabataan at lumikha ng pangmatagalang pagbabago sa ating komunidad.</p>
        <a class="btn btn-pink" href="https://www.facebook.com/sksanantonio2023" target="_blank" rel="noopener noreferrer">Connect on Facebook <svg class="icon"><use href="#i-arrow"/></svg></a>
      </div>
    </div>
  </section>

  <section class="s services" id="services">
    <div class="wrap">
      <div class="head"><h2>Everything you need, after one sign in</h2><p>Six services built for the youth of San Antonio.</p></div>
      <div class="grid">
        <a class="svc" href="login.html"><span class="ic"><svg class="icon"><use href="#i-cal"/></svg></span><h3>SK Announcements and Events</h3><p>Browse and register for upcoming youth events.</p></a>
        <a class="svc" href="login.html"><span class="ic"><svg class="icon"><use href="#i-box"/></svg></span><h3>Borrow Resources</h3><p>Request equipment and community resources.</p></a>
        <a class="svc" href="login.html"><span class="ic"><svg class="icon"><use href="#i-print"/></svg></span><h3>Print Services</h3><p>Send a document printing request.</p></a>
        <a class="svc" href="login.html"><span class="ic"><svg class="icon"><use href="#i-heart"/></svg></span><h3>Health Services</h3><p>Find health information and SK programs.</p></a>
        <a class="svc" href="login.html"><span class="ic"><svg class="icon"><use href="#i-bell"/></svg></span><h3>Report an Issue</h3><p>Share a concern or ask for urgent help.</p></a>
        <a class="svc" href="login.html"><span class="ic"><svg class="icon"><use href="#i-file"/></svg></span><h3>Public Disclosure</h3><p>Explore SK reports and budget updates.</p></a>
      </div>
    </div>
  </section>

    <section class="s" id="location" style="padding-top:0">
    <div class="wrap locate">
      <div class="locate-info">
        <span class="pill">Visit us</span>
        <h2>Find the SK office</h2>
        <p>Drop by for services, questions or to join an activity.</p>
        <div class="locate-row">
          <svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z"/><circle cx="12" cy="10" r="2.5"/></svg>
          <span><?= $escapeHtml($officeName) ?><br><small style="font-weight:400;color:var(--muted)"><?= $escapeHtml($officeAddress) ?></small></span>
        </div>
        <div class="locate-row legacy-contact-row">
          <svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.2-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.8 2.1Z"/></svg>
          <span><a href="tel:+639912633774">+63 991-263-3774</a><br><a href="tel:+639368674299">+63 936-867-4299</a></span>
        </div>
        <div class="locate-row legacy-contact-row">
          <svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
          <a href="mailto:sksanantonio23@gmail.com">sksanantonio23@gmail.com</a>
        </div>
        <?php if ($officeHours !== ''): ?>
        <div class="locate-row">
          <svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
          <span><?= $escapeHtml($officeHours) ?></span>
        </div>
        <?php endif; ?>
        <div class="locate-actions">
          <a class="btn btn-pink" href="<?= $escapeHtml($mapDirections) ?>" target="_blank" rel="noopener">Get directions <svg class="icon"><use href="#i-arrow"/></svg></a>
          <a class="btn btn-dark" href="<?= $escapeHtml($mapOpen) ?>" target="_blank" rel="noopener">Open in Google Maps</a>
        </div>
      </div>
      <div class="map-frame">
        <iframe src="<?= $escapeHtml($mapEmbed) ?>" title="Map showing the location of the SK office" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
      </div>
    </div>
  </section>

  <section class="s">
    <div class="wrap">
      <div class="signin-band">
        <div><h2>Your Digital ID is waiting.</h2><p>Sign in to register for events, request services and keep track of everything you've joined.</p></div>
        <a class="btn btn-dark" href="login.html"><svg class="icon"><use href="#i-id"/></svg> Sign in now</a>
      </div>
    </div>
  </section>
</main>

<footer id="contact">
  <div class="wrap">
    <div>
      <b>SKalinga</b>
      <p style="margin:0;max-width:34ch;color:rgba(255,255,255,.78)">Sangguniang Kabataan ng Barangay San Antonio, City of Biñan, Laguna.</p>
    </div>

    <div>
      <p class="footer-heading">Connect With Us</p>
      <div class="footer-links">
        <a class="footer-link" href="https://www.facebook.com/sksanantonio2023" target="_blank" rel="noopener noreferrer">
          <svg class="icon" aria-hidden="true"><use href="#i-fb"/></svg>
          <span>SKSanAntonio2023</span>
          <svg class="icon" aria-hidden="true" style="width:14px;height:14px;opacity:.7"><use href="#i-ext"/></svg>
        </a>
      </div>
    </div>

    <div>
      <p class="footer-heading">Contact Directory</p>
      <div class="footer-links">
        <a class="footer-link" href="tel:+639912633774">
          <svg class="icon" aria-hidden="true"><use href="#i-phone"/></svg>
          <span>+63 991-263-3774</span>
        </a>
        <a class="footer-link" href="tel:+639368674299">
          <svg class="icon" aria-hidden="true"><use href="#i-phone"/></svg>
          <span>+63 936-867-4299</span>
        </a>
        <a class="footer-link" href="mailto:sksanantonio23@gmail.com">
          <svg class="icon" aria-hidden="true"><use href="#i-mail"/></svg>
          <span>sksanantonio23@gmail.com</span>
        </a>
      </div>
    </div>
  </div>

  <div class="wrap footer-bottom">
    <span>&copy; <span id="yr"></span> SK San Antonio. All rights reserved.</span>
    <span>#YanAngSKko</span>
  </div>
</footer>

<div class="moments-lightbox" id="moments-lightbox" role="dialog" aria-modal="true" aria-label="Community photo viewer" hidden>
  <div class="moments-lightbox-panel">
    <button class="moments-control moments-lightbox-close" id="moments-lightbox-close" type="button" aria-label="Close photo viewer"><svg class="icon"><use href="#i-x"/></svg></button>
    <div class="moments-lightbox-image-wrap"><img class="moments-lightbox-image" id="moments-lightbox-image" src="" alt="" width="1600" height="1000"></div>
    <div class="moments-lightbox-actions">
      <button class="moments-control" id="moments-lightbox-prev" type="button" aria-label="Previous photo"><svg class="icon"><use href="#i-left"/></svg></button>
      <span class="moments-count" id="moments-lightbox-count" aria-live="polite" aria-atomic="true"></span>
      <button class="moments-control" id="moments-lightbox-next" type="button" aria-label="Next photo"><svg class="icon"><use href="#i-arrow"/></svg></button>
    </div>
  </div>
</div>

<script>
(() => {
  const nav = document.getElementById('nav');
  const carousel = document.getElementById('hero-carousel');
  const slidesContainer = document.getElementById('slides');
  const slides = [...slidesContainer.children];
  const dots = document.getElementById('dots');
  const counter = document.getElementById('slide-count');
  const pauseButton = document.getElementById('pause');
  const activityCards = document.getElementById('activity-cards');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let activeSlide = Math.max(0, slides.findIndex(slide => slide.classList.contains('on')));
  let carouselTimer = null;
  let userPaused = reducedMotion.matches;
  let hoverPaused = false;
  let focusPaused = false;
  let touchStartX = null;
  const carouselDuration = 5500;
  const momentsShell = document.getElementById('moments-shell');
  const momentsSection = document.getElementById('moments');
  const momentsTrack = document.getElementById('moments-track');
  const momentsSlides = momentsTrack ? [...momentsTrack.querySelectorAll('.moments-slide')] : [];
  const momentsProgress = document.getElementById('moments-progress-fill');
  const momentsCount = document.getElementById('moments-count');
  const momentsProgressBar = document.querySelector('.moments-progress');
  const momentsLightbox = document.getElementById('moments-lightbox');
  const momentsLightboxImage = document.getElementById('moments-lightbox-image');
  const momentsLightboxCount = document.getElementById('moments-lightbox-count');
  let activeMoment = 0;
  let momentsTimer = null;
  let momentsPausedByHover = false;
  let momentsPausedByFocus = false;
  let momentsDragging = false;
  let dragStartX = 0;
  let dragStartScroll = 0;
  let dragMoved = false;
  let lightboxStartX = null;
  let lightboxOpener = null;

  addEventListener('scroll', () => nav.classList.toggle('solid', scrollY > 30), { passive: true });
  document.getElementById('yr').textContent = new Date().getFullYear();

  function makeIcon(name) {
    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.classList.add('icon');
    icon.setAttribute('aria-hidden', 'true');
    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', `#i-${name}`);
    icon.appendChild(use);
    return icon;
  }

  function makeDots() {
    dots.replaceChildren();
    slides.forEach((slide, index) => {
      const dot = document.createElement('button');
      dot.className = 'dot';
      dot.type = 'button';
      dot.setAttribute('aria-label', `Show slide ${index + 1} of ${slides.length}`);
      dot.addEventListener('click', () => showSlide(index));
      dots.appendChild(dot);
    });
  }

  function renderSlideState() {
    slides.forEach((slide, index) => {
      const active = index === activeSlide;
      slide.classList.toggle('on', active);
      slide.setAttribute('aria-hidden', String(!active));
    });
    [...dots.children].forEach((dot, index) => {
      dot.classList.toggle('on', index === activeSlide);
      dot.setAttribute('aria-current', index === activeSlide ? 'true' : 'false');
    });
    counter.textContent = `${String(activeSlide + 1).padStart(2, '0')} / ${String(slides.length).padStart(2, '0')}`;
    document.getElementById('prev').disabled = slides.length < 2;
    document.getElementById('next').disabled = slides.length < 2;
    pauseButton.disabled = slides.length < 2 || reducedMotion.matches;
    carousel.classList.toggle('is-paused', userPaused || hoverPaused || focusPaused || reducedMotion.matches);
  }

  function scheduleAutoplay() {
    clearInterval(carouselTimer);
    carouselTimer = null;
    if (slides.length < 2 || userPaused || hoverPaused || focusPaused || reducedMotion.matches || document.hidden) return;
    carouselTimer = setInterval(() => showSlide(activeSlide + 1), carouselDuration);
  }

  function showSlide(index) {
    if (!slides.length) return;
    activeSlide = (index + slides.length) % slides.length;
    renderSlideState();
    scheduleAutoplay();
  }

  makeDots();
  slides.forEach(slide => {
    const image = slide.querySelector('img');
    if (image) image.addEventListener('error', () => slide.classList.add('image-failed'), { once: true });
  });
  renderSlideState();
  scheduleAutoplay();

  document.getElementById('next').addEventListener('click', () => showSlide(activeSlide + 1));
  document.getElementById('prev').addEventListener('click', () => showSlide(activeSlide - 1));
  pauseButton.addEventListener('click', () => {
    userPaused = !userPaused;
    pauseButton.setAttribute('aria-pressed', String(userPaused));
    pauseButton.setAttribute('aria-label', userPaused ? 'Play carousel' : 'Pause carousel');
    pauseButton.querySelector('span').textContent = userPaused ? '▶' : 'Ⅱ';
    renderSlideState();
    scheduleAutoplay();
  });
  carousel.addEventListener('mouseenter', () => {
    hoverPaused = true;
    renderSlideState();
    scheduleAutoplay();
  });
  carousel.addEventListener('mouseleave', () => {
    hoverPaused = false;
    renderSlideState();
    scheduleAutoplay();
  });
  carousel.addEventListener('focusin', () => {
    focusPaused = true;
    renderSlideState();
    scheduleAutoplay();
  });
  carousel.addEventListener('focusout', event => {
    if (!carousel.contains(event.relatedTarget)) {
      focusPaused = false;
      renderSlideState();
      scheduleAutoplay();
    }
  });
  carousel.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      showSlide(activeSlide - 1);
    } else if (event.key === 'ArrowRight') {
      event.preventDefault();
      showSlide(activeSlide + 1);
    }
  });
  carousel.addEventListener('pointerdown', event => {
    if (event.pointerType === 'touch') touchStartX = event.clientX;
  }, { passive: true });
  carousel.addEventListener('pointerup', event => {
    if (touchStartX === null) return;
    const delta = event.clientX - touchStartX;
    touchStartX = null;
    if (Math.abs(delta) > 48) showSlide(activeSlide + (delta < 0 ? 1 : -1));
  }, { passive: true });
  carousel.addEventListener('pointercancel', () => { touchStartX = null; });
  document.addEventListener('visibilitychange', scheduleAutoplay);
  document.addEventListener('visibilitychange', scheduleMomentAutoplay);
  if (typeof reducedMotion.addEventListener === 'function') {
    reducedMotion.addEventListener('change', event => {
      if (event.matches) userPaused = true;
      renderSlideState();
      scheduleAutoplay();
      scheduleMomentAutoplay();
    });
  }

  function setActiveMoment(index) {
    if (!momentsSlides.length) return;
    activeMoment = (index + momentsSlides.length) % momentsSlides.length;
    momentsSlides.forEach((slide, slideIndex) => {
      const active = slideIndex === activeMoment;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-current', String(active));
    });
    const image = momentsSlides[activeMoment].querySelector('img');
    momentsSection.style.setProperty('--moments-image', `url("${image.currentSrc || image.src}")`);
    momentsCount.textContent = `${String(activeMoment + 1).padStart(2, '0')} / ${String(momentsSlides.length).padStart(2, '0')}`;
    momentsProgress.style.transform = `scaleX(${(activeMoment + 1) / momentsSlides.length})`;
    momentsProgressBar.setAttribute('aria-valuenow', String(activeMoment + 1));
  }

  function momentFromScroll() {
    if (!momentsSlides.length) return;
    const trackCenter = momentsTrack.getBoundingClientRect().left + momentsTrack.clientWidth / 2;
    let closestIndex = 0;
    let closestDistance = Infinity;
    momentsSlides.forEach((slide, index) => {
      const rect = slide.getBoundingClientRect();
      const distance = Math.abs(rect.left + rect.width / 2 - trackCenter);
      if (distance < closestDistance) {
        closestDistance = distance;
        closestIndex = index;
      }
    });
    if (closestIndex !== activeMoment) setActiveMoment(closestIndex);
  }

  function goToMoment(index) {
    if (!momentsSlides.length) return;
    const targetIndex = (index + momentsSlides.length) % momentsSlides.length;
    const slide = momentsSlides[targetIndex];
    const trackRect = momentsTrack.getBoundingClientRect();
    const slideRect = slide.getBoundingClientRect();
    const targetScroll = momentsTrack.scrollLeft + slideRect.left - trackRect.left - (momentsTrack.clientWidth - slideRect.width) / 2;
    momentsTrack.scrollTo({ left: targetScroll, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
    setActiveMoment(targetIndex);
    scheduleMomentAutoplay();
  }

  function scheduleMomentAutoplay() {
    clearTimeout(momentsTimer);
    momentsTimer = null;
    if (momentsSlides.length < 2 || reducedMotion.matches || document.hidden || momentsPausedByHover || momentsPausedByFocus || momentsDragging || !momentsLightbox.hidden) return;
    momentsTimer = setTimeout(() => goToMoment(activeMoment + 1), 4700);
  }

  if (momentsSlides.length) {
    momentsSlides.forEach((slide, index) => {
      const image = slide.querySelector('img');
      image.addEventListener('error', () => slide.classList.add('image-failed'), { once: true });
      slide.querySelector('.moments-image').addEventListener('click', () => {
        if (dragMoved) return;
        lightboxOpener = slide.querySelector('.moments-image');
        showMomentInLightbox(index);
        momentsLightbox.hidden = false;
        document.body.style.overflow = 'hidden';
        document.getElementById('moments-lightbox-close').focus();
        clearTimeout(momentsTimer);
      });
    });

    momentsTrack.addEventListener('scroll', () => requestAnimationFrame(momentFromScroll), { passive: true });
    momentsTrack.addEventListener('keydown', event => {
      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        goToMoment(activeMoment - 1);
      } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        goToMoment(activeMoment + 1);
      }
    });
    momentsTrack.addEventListener('wheel', event => {
      if (Math.abs(event.deltaY) > Math.abs(event.deltaX)) {
        event.preventDefault();
        momentsTrack.scrollLeft += event.deltaY;
      }
    }, { passive: false });

    momentsTrack.addEventListener('pointerdown', event => {
      if (event.pointerType !== 'mouse') return;
      momentsDragging = true;
      dragMoved = false;
      dragStartX = event.clientX;
      dragStartScroll = momentsTrack.scrollLeft;
      momentsTrack.classList.add('is-dragging');
      momentsTrack.setPointerCapture(event.pointerId);
      clearTimeout(momentsTimer);
    });
    momentsTrack.addEventListener('pointermove', event => {
      if (!momentsDragging) return;
      const delta = event.clientX - dragStartX;
      if (Math.abs(delta) > 5) dragMoved = true;
      momentsTrack.scrollLeft = dragStartScroll - delta;
    });
    const endMomentDrag = () => {
      if (!momentsDragging) return;
      momentsDragging = false;
      momentsTrack.classList.remove('is-dragging');
      scheduleMomentAutoplay();
      if (dragMoved) setTimeout(() => { dragMoved = false; }, 0);
    };
    momentsTrack.addEventListener('pointerup', endMomentDrag);
    momentsTrack.addEventListener('pointercancel', endMomentDrag);
    momentsTrack.addEventListener('lostpointercapture', endMomentDrag);

    momentsShell.addEventListener('mouseenter', () => {
      momentsPausedByHover = true;
      clearTimeout(momentsTimer);
    });
    momentsShell.addEventListener('mouseleave', () => {
      momentsPausedByHover = false;
      scheduleMomentAutoplay();
    });
    momentsShell.addEventListener('focusin', () => {
      momentsPausedByFocus = true;
      clearTimeout(momentsTimer);
    });
    momentsShell.addEventListener('focusout', event => {
      if (!momentsShell.contains(event.relatedTarget)) {
        momentsPausedByFocus = false;
        scheduleMomentAutoplay();
      }
    });
    document.getElementById('moments-prev').addEventListener('click', () => goToMoment(activeMoment - 1));
    document.getElementById('moments-next').addEventListener('click', () => goToMoment(activeMoment + 1));
    setActiveMoment(0);
    scheduleMomentAutoplay();
  }

  function showMomentInLightbox(index) {
    if (!momentsSlides.length) return;
    activeMoment = (index + momentsSlides.length) % momentsSlides.length;
    const thumbnail = momentsSlides[activeMoment].querySelector('img');
    momentsLightboxImage.src = thumbnail.currentSrc || thumbnail.src;
    momentsLightboxImage.alt = thumbnail.alt;
    momentsLightboxCount.textContent = `${String(activeMoment + 1).padStart(2, '0')} / ${String(momentsSlides.length).padStart(2, '0')}`;
    document.getElementById('moments-lightbox-prev').disabled = momentsSlides.length < 2;
    document.getElementById('moments-lightbox-next').disabled = momentsSlides.length < 2;
  }

  function closeMomentLightbox() {
    momentsLightbox.hidden = true;
    document.body.style.overflow = '';
    lightboxOpener?.focus();
    scheduleMomentAutoplay();
  }

  document.getElementById('moments-lightbox-close').addEventListener('click', closeMomentLightbox);
  document.getElementById('moments-lightbox-prev').addEventListener('click', () => showMomentInLightbox(activeMoment - 1));
  document.getElementById('moments-lightbox-next').addEventListener('click', () => showMomentInLightbox(activeMoment + 1));
  momentsLightbox.addEventListener('click', event => {
    if (event.target === momentsLightbox) closeMomentLightbox();
  });
  momentsLightbox.querySelector('.moments-lightbox-image-wrap').addEventListener('pointerdown', event => {
    if (event.pointerType === 'touch') lightboxStartX = event.clientX;
  }, { passive: true });
  momentsLightbox.querySelector('.moments-lightbox-image-wrap').addEventListener('pointerup', event => {
    if (lightboxStartX === null) return;
    const delta = event.clientX - lightboxStartX;
    lightboxStartX = null;
    if (Math.abs(delta) > 48) showMomentInLightbox(activeMoment + (delta < 0 ? 1 : -1));
  }, { passive: true });

  document.addEventListener('keydown', event => {
    if (!momentsLightbox.hidden) {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeMomentLightbox();
      } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        showMomentInLightbox(activeMoment - 1);
      } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        showMomentInLightbox(activeMoment + 1);
      } else if (event.key === 'Tab') {
        const focusable = [...momentsLightbox.querySelectorAll('button:not(:disabled)')];
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
      return;
    }
  });

  function safeImagePath(value) {
    if (typeof value !== 'string' || !value.trim()) return '';
    try {
      const imageUrl = new URL(value, window.location.href);
      return imageUrl.origin === window.location.origin && /\.(?:jpe?g|png|gif|webp)$/i.test(imageUrl.pathname) ? imageUrl.href : '';
    } catch {
      return '';
    }
  }

  function normalizeEvents(rows) {
    return rows.map(row => {
      const date = new Date(`${row.date || ''}T00:00:00`);
      if (Number.isNaN(date.getTime()) || ['cancelled', 'canceled', 'deleted', 'hidden'].includes(String(row.status || '').toLowerCase())) return null;
      return {
        title: String(row.title || 'SK youth activity'),
        date,
        dateLabel: Number.isNaN(date.getTime()) ? 'Date to be announced' : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }),
        location: String(row.location || ''),
        type: String(row.type || 'Youth activity'),
        description: String(row.description || ''),
        image: safeImagePath(row.imagePath)
      };
    }).filter(Boolean);
  }

  function renderActivities(events) {
    activityCards.replaceChildren();
    if (!events.length) {
      const empty = document.createElement('p');
      empty.className = 'empty-activities';
      empty.textContent = 'There are no public activities to show right now. Check back soon for updates.';
      activityCards.appendChild(empty);
      return;
    }
    [...events].sort((a, b) => b.date - a.date).slice(0, 8).forEach(eventData => {
      const card = document.createElement('article');
      card.className = 'card';
      const picture = document.createElement('div');
      picture.className = 'pic';
      if (eventData.image) {
        const image = document.createElement('img');
        image.src = eventData.image;
        image.alt = `${eventData.title} activity`;
        image.loading = 'lazy';
        image.width = 800;
        image.height = 600;
        picture.appendChild(image);
      } else {
        picture.setAttribute('aria-hidden', 'true');
      }
      const copy = document.createElement('div');
      copy.className = 't';
      const badge = document.createElement('span');
      badge.className = 'pill';
      badge.textContent = eventData.type;
      const title = document.createElement('h3');
      title.textContent = eventData.title;
      const description = document.createElement('p');
      description.textContent = eventData.description || 'A youth activity organized by SK San Antonio.';
      const metadata = document.createElement('div');
      metadata.className = 'meta';
      const date = document.createElement('span');
      date.append(makeIcon('cal'), document.createTextNode(eventData.dateLabel));
      metadata.appendChild(date);
      if (eventData.location) {
        const location = document.createElement('span');
        location.append(makeIcon('id'), document.createTextNode(eventData.location));
        metadata.appendChild(location);
      }
      copy.append(badge, title, description, metadata);
      card.append(picture, copy);
      activityCards.appendChild(card);
    });
  }

  async function loadPublicActivities() {
    try {
      const response = await fetch('api/get_events.php', {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      });
      if (!response.ok) throw new Error(`Events request failed (${response.status})`);
      const result = await response.json();
      if (!result.success || !Array.isArray(result.data)) throw new Error(result.message || result.error || 'Events data is unavailable');
      renderActivities(normalizeEvents(result.data));
    } catch (error) {
      console.error('Unable to load public youth activities:', error);
      activityCards.replaceChildren();
      const errorMessage = document.createElement('p');
      errorMessage.className = 'empty-activities';
      errorMessage.setAttribute('role', 'status');
      errorMessage.textContent = 'Activities are temporarily unavailable. Please check back later.';
      activityCards.appendChild(errorMessage);
    }
  }

  loadPublicActivities();

  if ('IntersectionObserver' in window && !reducedMotion.matches) {
    const counterObserver = new IntersectionObserver(entries => entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      counterObserver.unobserve(entry.target);
      const element = entry.target;
      const target = Number(element.dataset.count);
      const start = performance.now();
      (function animate(now) {
        const progress = Math.min((now - start) / 1200, 1);
        element.textContent = Math.round(target * (1 - Math.pow(1 - progress, 3))).toLocaleString() + (progress === 1 ? '+' : '');
        if (progress < 1) requestAnimationFrame(animate);
      })(start);
    }), { threshold: 0.5 });
    document.querySelectorAll('[data-count]').forEach(element => counterObserver.observe(element));
  }
})();
</script>
</body>
</html>