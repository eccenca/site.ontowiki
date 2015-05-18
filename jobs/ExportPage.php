<?php
/**
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Cache generation job
 */

class Site_Job_ExportPage extends Erfurt_Worker_Job_Abstract
{

    protected $urlBase  = "";
    protected $uri      = "";

    protected function callbackRelativeLink( $match ){
        $path   = Erfurt_Uri::getPathTo( $this->uri, $match[3] );
        return $match[1].$match[2].$path.$match[4];
    }

    public function run($workload)
    {
        $helper = OntoWiki::getInstance()->extensionManager->getComponentHelper('site');
        $siteConfig = $helper->getSiteConfig();

        // FIXME is it ok to change selectedModel/selectedResource and site helper stuff here?
        $store = OntoWiki::getInstance()->erfurt->getStore();
        $model = $store->getModel($siteConfig['model']);
        OntoWiki::getInstance()->selectedModel = $model;
        OntoWiki::getInstance()->selectedResource = new OntoWiki_Resource($workload->resourceUri, $model);

        $helper->setSite($siteConfig['id']);
        $helper->setUrlBase($workload->urlBase);

        // FIXME, actual uri logic is in onShouldLinkedDataRedirect & onBuildUrl, needs refactoring to be accessible
        $uri = preg_replace('~^https?://(.*)$~', '$1.html', $workload->resourceUri);

        // set internal origin url, e.g. for link helper
        $helper->originUrl = $workload->resourceUri;

        // TODO: add config option to use valid/invalid caches
        // for now always re-generate cache
        $cache = $helper->makeCache($uri);
        
        // TODO: we need parameter to overwrite invalidation time
        // otherwise this won't work if frontend caching is disabled
        // in datawiki configuration
        //$cache  = $helper->loadCache($uri);
        
        echo sprintf('%s %d %s', $workload->msg, $cache['code'], $uri) . PHP_EOL;
        $this->logSuccess(sprintf('%s %d %s', $workload->msg, $cache['code'], $uri));

        if (isset($workload->useDeprecatedLinkRewrite) && $workload->useDeprecatedLinkRewrite) {
            // rewriting links to relative URLs in worker is now deprecated
            // use relative option in template helper,
            // or turn it on here via special configuration ``useDeprecatedLinkRewrite=true``
            $this->urlBase  = $workload->urlBase;
            $this->uri      = $workload->resourceUri;
            $pattern        = "/(href=|src=)(\"|')(".str_replace("/", "\/", $workload->urlBase ).".+)(\"|')/U";
            $cache['body']  = preg_replace_callback($pattern, array($this, 'callbackRelativeLink'), $cache['body']);
            $pattern        = "/()(')(".str_replace("/", "\/", $workload->urlBase ).".+)(')/U";
            $cache['body']  = preg_replace_callback($pattern, array($this, 'callbackRelativeLink'), $cache['body']);
        }
        
        if (strpos($workload->resourceUri, $workload->urlBase) !== 0) {
            // resource uri does not contain base url
            echo sprintf('%s', $uri . ' does not start with ' . $workload->urlBase) . PHP_EOL;
            $this->logFailure(sprintf('%s', $uri . ' does not start with ' . $workload->urlBase));
        }
        else {
            // remove base url and add extension to create relative file name
            $name = substr($workload->resourceUri, strlen($workload->urlBase)) . '.html';
            $nameparts = explode('/', $name);
            $filename = array_pop($nameparts);
            $dirname = $workload->targetPath . implode('/', $nameparts);
            
            if (!is_dir($dirname)) {
                mkdir($dirname, 0755, TRUE);
            }

            if (file_put_contents($dirname . '/' . $filename, $cache['body'] )) {
                echo sprintf('%s', 'Write ' . $dirname . '/' . $filename) . PHP_EOL;
                $this->logSuccess(sprintf('%s', 'Write ' . $dirname . '/' . $filename));
            }
            else {
                echo sprintf('%s', 'Cannot write ' . $dirname . '/' . $filename) . PHP_EOL;
                $this->logFailure(sprintf('%s', 'Cannot write ' . $dirname . '/' . $filename));
            }
            
        }
        
        return;
    }
}
