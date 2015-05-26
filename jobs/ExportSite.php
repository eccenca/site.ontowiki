<?php
/**
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Cache generation job
 */

class Site_Job_ExportSite extends Erfurt_Worker_Job_Abstract
{
    public function run($workload)
    {
        $datawiki = OntoWiki::getInstance();
        $helper = $datawiki->extensionManager->getComponentHelper('site');
        $siteConfig = $helper->getSiteConfig();

        // FIXME is it ok to change selectedModel/selectedResource and site helper stuff here?
        $store = $datawiki->erfurt->getStore();
        $model = $store->getModel($siteConfig['model']);
        $datawiki->selectedModel = $model;

        $helper->setUrlBase($workload->urlBase);
        
        // dump the sitemap.xml
        
        if (
            isset($workload->sitemapUri) &&
            $workload->sitemapUri && 
            $sitemapXml = $helper->createSitemapXml()
           )
        {
            if (isset($workload->dumpBase) && trim($workload->dumpBase)) {
                $sitemapXml = str_replace(trim($workload->urlBase), trim($workload->dumpBase), $sitemapXml);
            }
        
            $sm_name = substr($workload->sitemapUri, strlen($workload->urlBase));
            $sm_nameparts = explode('/', $sm_name);
            $sm_filename = array_pop($sm_nameparts);
            $sm_dirname = $workload->targetPath . implode('/', $sm_nameparts);
            
            if (!is_dir($sm_dirname)) {
                mkdir($sm_dirname, 0755, TRUE);
            }
            
            if (file_put_contents($sm_dirname . '/' . $sm_filename, $sitemapXml )) {
                echo sprintf('%s', 'Write ' . $sm_dirname . '/' . $sm_filename) . PHP_EOL;
                $this->logSuccess(sprintf('%s', 'Write ' . $sm_dirname . '/' . $sm_filename));
            }
            else {
                echo sprintf('%s', 'Cannot write ' . $sm_dirname . '/' . $sm_filename) . PHP_EOL;
                $this->logFailure(sprintf('%s', 'Cannot write ' . $sm_dirname . '/' . $sm_filename));
            }
            
        }

        // queue jobs to dump all resources with types that
        // * part of the sitemap, or
        // * have templates configured

        $uris   = $helper->getAllURIs();
        shuffle($uris); // randomize order
        $count  = count($uris);
        foreach ($uris as $nr => $uri) {
            OntoWiki::getInstance()->callJob('exportPage', array(
                'resourceUri'   => $uri,
                'urlBase'       => $helper->getUrlBase(),
                'targetPath'    => $workload->targetPath,
                'msg'           => sprintf('(%d/%d)', $nr + 1, $count),
            ));
        }

        $datawiki = null; unset($datawiki);
        $helper = null; unset($helper);
        $siteConfig = null; unset($siteConfig);
        $store = null; unset($store);
        $model = null; unset($model);
        $uris = null; unset($uris);
        
        echo sprintf('%s resources', $count) . PHP_EOL;
        $this->logSuccess(sprintf('%s resources', $count));
        
        return;
    }
}
