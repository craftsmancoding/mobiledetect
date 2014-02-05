<?php
/**
 * @name MobileDetect
 * @description Dynamically change the template used to render a page depending on the browser detected (desktop, mobile or tablet). Each variation is independently cached.
 * @PluginEvents OnLoadWebDocument,OnBeforeCacheUpdate
 *
 * When valid a request comes in, we have to let MODX do its regular caching, but in
 * addition, we grab a copy of the processed output and store it in a custom 
 * cache directory -- one file per browser per page. When a resource is loaded from cache, 
 * we intercept it and override its _content with the custom cached content.
 *
 *
 * USAGE:
 * For regular usage, enable the plugin.
 * For advanced usage, there are some URL parameters that affect behavior:
 *
 *  &refresh=1 : custom caching will be bypassed; page is rendered fresh for each view.
 *  &template=desktop|tablet|mobile : manually override the template
 *  &tpldebug=1 : will force debugging messages to be sent to the MODX log.
 * 
 *
 * Sets the [[+browser_detected]] placeholder so that other scripts can read which view 
 * was used.
 *
 * Authors: everett@fireproofsocks.com
 * Last Modified: 9/14/2013
 */

//------------------------------------------------------------------------------
// CONFIG
//------------------------------------------------------------------------------
// How long should pages be cached for? (in seconds)
// 0 = indefinite (until cache is cleared manually) 
$lifetime = 0;

// Custom directory for cached files relative to core/cache/
$cache_dir = 'resource_custom';

// TVs used to store alternate templates
$mobile_template_tv = 'MobileTemplate';
$tablet_template_tv = 'TabletTemplate';

//------------------------------------------------------------------------------
// Check for manual overrides
$debugLevel = $modx->getOption('tpldebug',$_GET,modX::LOG_LEVEL_DEBUG); 
$refresh = $modx->getOption('refresh',$_GET,false); 
$template_override = $modx->getOption('template',$_GET,false); 

$cache_opts = array(xPDO::OPT_CACHE_KEY => $cache_dir); 

$modx->log($debugLevel, '[MobileDetect] '.$modx->event->name);

//------------------------------------------------------------------------------
// Dynamically set template(s) and cache all of them
//------------------------------------------------------------------------------
if ($modx->event->name == 'OnLoadWebDocument') {
    
    $template_slug = 'desktop';
    $template = $modx->resource->get('template'); // Desktop Template (default)
    $mobile_template = $modx->resource->getTVValue($mobile_template_tv);
    $tablet_template = $modx->resource->getTVValue($tablet_template_tv);

    // Exit if the alternate templates are not set.
    if (empty($mobile_template) || empty($tablet_template)) {
        $modx->log($debugLevel, '[MobileDetect] '.$mobile_template_tv.' and '.$tablet_template_tv.' require values.');
        return;
    }

    // Manual overrides
    if ($template_override) {
        if ($template_override == 'desktop') {
            $modx->log($debugLevel, "[MobileDetect] Manual override to desktop template ($template)");
        }
        elseif ($template_override == 'tablet') {
            $modx->log($debugLevel, "[MobileDetect] Manual override to tablet template ($tablet_template)");
            $template_slug = 'tablet';
            $template = $tablet_template;
        }
        elseif ($template_override == 'mobile') {
            $modx->log($debugLevel, "[MobileDetect] Manual override to mobile template ($mobile_template)");
            $template_slug = 'mobile';            
            $template = $mobile_template;
        }
    }
    // Load the library only when necessary
    else {
        require_once MODX_CORE_PATH.'components/mobile/includes/Mobile_Detect.php';
        $detect = new Mobile_Detect();
            
        if($detect->isTablet()) {
            $modx->log($debugLevel, '[MobileDetect] Tablet detected.');
            $template_slug = 'tablet';
            $template = $tablet_template;
        }

        else
        if ($detect->isMobile()) {
            $modx->log($debugLevel, '[MobileDetect] Mobile detected.');
            $template_slug = 'mobile';
            $template = $mobile_template;
        }
    }

    $modx->setPlaceholder('browser_detected',$template_slug);
    $fingerprint = $modx->resource->get('id').'.'.$template_slug;
        
    $modx->resource->set('template',$template); // So that we can grab the output from $modx->resource->process()

    $out = $modx->cacheManager->get($fingerprint, $cache_opts);
    
    // Cache our custom browser-specific version of the page.
    if ($refresh || empty($out)) {
        $modx->log($debugLevel, '[MobileDetect] rendering '.$fingerprint);
    	// Disable built-in caching, otherwise the process method will return the cached version of the page
    	$modx->resource->set('cacheable',false);
        $out = $modx->resource->process();
    	$modx->cacheManager->set($fingerprint, $out, $lifetime, $cache_opts);
    }

    $modx->resource->_content = $out;
    
}
// Clear Custom Cache
elseif ($modx->event->name == 'OnBeforeCacheUpdate') {
    $modx->cacheManager->clean(array(xPDO::OPT_CACHE_KEY => $cache_dir));
    break;
}