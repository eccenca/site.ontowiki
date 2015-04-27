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
        $helper = OntoWiki::getInstance()->extensionManager->getComponentHelper('site');
        $siteConfig = $helper->getSiteConfig();

        // FIXME is it ok to change selectedModel/selectedResource and site helper stuff here?
        $store = OntoWiki::getInstance()->erfurt->getStore();
        $model = $store->getModel($siteConfig['model']);
        OntoWiki::getInstance()->selectedModel = $model;

        $helper->setUrlBase($workload->urlBase);
        
        // dump the sitemap.xml
        
        if (
            isset($workload->sitemapUri) &&
            $workload->sitemapUri && 
            $sitemapXml = $helper->createSitemapXml()
           )
        {
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

        // queue jobs to dump all resources with types that have templates configured

        $uris   = $helper->getAllURIs();
        $count  = count($uris);
        foreach ($uris as $nr => $uri) {
            OntoWiki::getInstance()->callJob('exportPage', array(
                'resourceUri'   => $uri,
                'urlBase'       => $helper->getUrlBase(),
                'targetPath'    => $workload->targetPath,
                'msg'           => sprintf('(%d/%d)', $nr + 1, $count),
            ));
        }

        echo sprintf('%s resources', $count) . PHP_EOL;
        $this->logSuccess(sprintf('%s resources', $count));
    }
}
