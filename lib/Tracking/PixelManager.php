<?php
namespace NGN\Lib\Tracking;

/**
 * PixelManager - Centralized tracking pixel configuration
 * 
 * Manages GA4, Meta Pixel, and TikTok Pixel configurations.
 * Reads from environment variables for easy configuration.
 * 
 * Environment variables:
 * - GA4_MEASUREMENT_ID (e.g., G-XXXXXXXXXX)
 * - META_PIXEL_ID (e.g., 1496589657660545)
 * - TIKTOK_PIXEL_ID (e.g., XXXXXXXXXX)
 * - TRACKING_ENABLED (true/false, defaults to true in production)
 */
class PixelManager
{
    private static ?self $instance = null;
    
    private string $ga4Id;
    private string $metaPixelId;
    private string $tiktokPixelId;
    private bool $enabled;
    
    private function __construct()
    {
        $this->ga4Id = (string)(getenv('GA4_MEASUREMENT_ID') ?: 'G-LHGQG7HXKH');
        $this->metaPixelId = (string)(getenv('META_PIXEL_ID') ?: '1496589657660545');
        $this->tiktokPixelId = (string)(getenv('TIKTOK_PIXEL_ID') ?: '');
        
        $trackingEnv = getenv('TRACKING_ENABLED');
        $this->enabled = $trackingEnv === false || $trackingEnv === 'true' || $trackingEnv === '1';
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    public function getGA4Id(): string
    {
        return $this->ga4Id;
    }
    
    public function getMetaPixelId(): string
    {
        return $this->metaPixelId;
    }
    
    public function getTikTokPixelId(): string
    {
        return $this->tiktokPixelId;
    }
    
    public function hasGA4(): bool
    {
        return $this->ga4Id !== '';
    }
    
    public function hasMetaPixel(): bool
    {
        return $this->metaPixelId !== '';
    }
    
    public function hasTikTokPixel(): bool
    {
        return $this->tiktokPixelId !== '';
    }
    
    /**
     * Render GA4 tracking script
     */
    public function renderGA4(): string
    {
        if (!$this->enabled || !$this->hasGA4()) {
            return '';
        }
        
        $id = htmlspecialchars($this->ga4Id, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$id}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '{$id}');
</script>
HTML;
    }
    
    /**
     * Render Meta Pixel tracking script
     */
    public function renderMetaPixel(): string
    {
        if (!$this->enabled || !$this->hasMetaPixel()) {
            return '';
        }
        
        $id = htmlspecialchars($this->metaPixelId, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!-- Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$id}');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={$id}&ev=PageView&noscript=1"/></noscript>
HTML;
    }
    
    /**
     * Render TikTok Pixel tracking script
     */
    public function renderTikTokPixel(): string
    {
        if (!$this->enabled || !$this->hasTikTokPixel()) {
            return '';
        }
        
        $id = htmlspecialchars($this->tiktokPixelId, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!-- TikTok Pixel -->
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
  ttq.load('{$id}');
  ttq.page();
}(window, document, 'ttq');
</script>
HTML;
    }
    
    /**
     * Render all configured tracking pixels
     */
    public function renderAll(): string
    {
        if (!$this->enabled) {
            return '<!-- Tracking disabled -->';
        }
        
        return $this->renderGA4() . "\n" . $this->renderMetaPixel() . "\n" . $this->renderTikTokPixel();
    }
    
    /**
     * Get configuration status for admin display
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->enabled,
            'ga4' => [
                'configured' => $this->hasGA4(),
                'id' => $this->ga4Id ? substr($this->ga4Id, 0, 6) . '...' : null,
            ],
            'meta_pixel' => [
                'configured' => $this->hasMetaPixel(),
                'id' => $this->metaPixelId ? substr($this->metaPixelId, 0, 6) . '...' : null,
            ],
            'tiktok_pixel' => [
                'configured' => $this->hasTikTokPixel(),
                'id' => $this->tiktokPixelId ? substr($this->tiktokPixelId, 0, 6) . '...' : null,
            ],
        ];
    }
}

