<?php

use \Innomatic\Core\InnomaticContainer;
use \Innomatic\Wui\Widgets;
use \Innomatic\Wui\Dispatch;
use \Innomatic\Domain\User;
use \Shared\Wui;

class ImpagemanagerPanelActions extends \Innomatic\Desktop\Panel\PanelActions
{
    protected $localeCatalog;

    public function __construct(\Innomatic\Desktop\Panel\PanelController $controller)
    {
        parent::__construct($controller);
    }

    public function beginHelper()
    {
        $this->localeCatalog = new \Innomatic\Locale\LocaleCatalog(
            'innomedia-page-manager::pagemanager_panel',
            InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getLanguage()
        );
    }

    public function endHelper()
    {
    }

    public static function ajaxAddContent($module, $page)
    {
        $pageid = 0;
        $scope_page = 'backend';
        $contentPage = new \Innomedia\Page($module, $page, $pageid, $scope_page);
        $contentPage->addContent();
        $xml = '<vertgroup><children>
            <horizbar />
            <impagemanager>
            <args>
              <module>'.WuiXml::cdata($module).'</module>
              <page>'.WuiXml::cdata($page).'</page>
              <pageid>'.$contentPage->getId().'</pageid>
            </args>
            </impagemanager>
            </children></vertgroup>';

        $objResponse = new XajaxResponse();
        $objResponse->addAssign("pageeditor", "innerHTML", \Shared\Wui\WuiXml::getContentFromXml('pageeditor', $xml));

        return $objResponse;
    }

    public static function ajaxLoadContentList($module, $page)
    {
        $localeCatalog = new \Innomatic\Locale\LocaleCatalog(
            'innomedia-page-manager::pagemanager_panel',
            InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getLanguage()
        );

        $domainDa = InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')
            ->getCurrentDomain()
            ->getDataAccess();

        $pagesQuery = $domainDa->execute(
            "SELECT id, name
            FROM innomedia_pages
            WHERE page=".$domainDa->formatText($module.'/'.$page)."
            ORDER BY name"
        );

        $pages = array();
        $pages[] = '';

        while (!$pagesQuery->eof) {
            $pages[$pagesQuery->getFields('id')] = $pagesQuery->getFields('name');
            $pagesQuery->moveNext();
        }

        $xml = '<horizgroup><children>
            <label><args><label>'.WuiXml::cdata($localeCatalog->getStr('content_item_label')).'</label></args></label>
            <combobox>
            <args>
            <id>pageid</id>
            <elements type="array">'.\Shared\Wui\WuiXml::encode($pages).'</elements>
            </args>
            <events>
            <change>'
            .\Shared\Wui\WuiXml::cdata(
                'var pageid = document.getElementById(\'pageid\').value;
                xajax_LoadContentLang(\''.$module.'\', \''.$page.'\', pageid);
                xajax_WuiImpagemanagerLoadPage(\''.$module.'\', \''.$page.'\', pageid);
                '
            ).'
            </change>
            </events>
            </combobox>
            </children></horizgroup>';


        $objResponse = new XajaxResponse();
        $objResponse->addAssign("content_list", "innerHTML", \Shared\Wui\WuiXml::getContentFromXml('contentlist', $xml));

        return $objResponse;
    }

    public static function ajaxLoadContentLang($module, $page, $pageid)
    {

        $languages = \Innomedia\Locale\LocaleWebApp::getListLanguagesAvailable();

        $localeCatalog = new \Innomatic\Locale\LocaleCatalog(
            'innomedia-page-manager::pagemanager_panel',
            InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getLanguage()
        );

        $currentLanguage = \Innomedia\Locale\LocaleWebApp::getCurrentLanguage('backend');
        $xml = '
            <horizgroup><children>
            <label><args><label>'.WuiXml::cdata($localeCatalog->getStr('lang_select_label')).'</label></args></label>
            <combobox><args><id>lang</id><default>'.WuiXml::cdata($currentLanguage).'</default><elements type="array">'.WuiXml::encode($languages).'</elements></args>
              <events>
              <change>'
                .\Shared\Wui\WuiXml::cdata(
                    'var lang = document.getElementById(\'lang\');
                    var langvalue = lang.options[lang.selectedIndex].value;
                    xajax_SetLangForEditContext(\''.$module.'\', \''.$page.'\', \''.$pageid.'\', langvalue);
                    '
                ).'
              </change>
              </events>
            </combobox>
            </children></horizgroup>
        ';
        $objResponse = new XajaxResponse();
        $objResponse->addAssign("lang_list", "innerHTML", \Shared\Wui\WuiXml::getContentFromXml('', $xml));

        return $objResponse;
    }

    public static function ajaxSetLangForEditContext($module, $page, $pageid, $lang)
    {
        $session = DesktopFrontController::instance('\Innomatic\Desktop\Controller\DesktopFrontController')->session;
        $session->put('innomedia_lang_for_edit_context', $lang);
        
        $objResponse = new XajaxResponse();
        $objResponse->addScript("xajax_WuiImpagemanagerLoadPage('$module', '$page', '$pageid');");
     
        return $objResponse;
    }

    public static function ajaxSaveGlobalParameters($parameters)
    {
        $decodedParams = array();
        foreach (explode('&', $parameters) as $chunk) {
            $param = explode("=", $chunk);

            if ($param) {
                $moduleName = $blockName = '';

                $keys = explode('_', urldecode($param[0]));
                if (count($keys) < 4) {
                    // Key is not valid
                    continue;
                }

                $moduleName = array_shift($keys);
                $blockName = array_shift($keys);
                $blockCounter = array_shift($keys);
                $paramName = implode('_', $keys);
                $decodedParams[$moduleName][$blockName][$blockCounter][$paramName] = urldecode($param[1]);
            }
        }

        $context = \Innomedia\Context::instance('\Innomedia\Context');
        $modulesList = $context->getModulesList();

        foreach ($modulesList as $module) {
            $moduleObj = new \Innomedia\Module($module);
            $moduleBlocks = $moduleObj->getBlocksList();
            foreach ($moduleBlocks as $block) {
                $scopes = \Innomedia\Block::getScopes($context, $module, $block);
                if (in_array('global', $scopes)) {
                    $fqcn = \Innomedia\Block::getClass($context, $module, $block);
                    if (class_exists($fqcn)) {
                        if ($fqcn::hasBlockManager()) {
                            $hasBlockManager = true;
                            $headers['0']['label'] = ucfirst($module).': '.ucfirst($block);
                            $managerClass = $fqcn::getBlockManager();
                            if (class_exists($managerClass)) {
                                $manager = new $managerClass('', 1, 0);
                                $manager->saveBlock($decodedParams[$module][$block][1]);
                           }
                        }
                    }
                }
            }
        }

        $xml = \ImpagemanagerPanelController::getGlobalParametersXml();

        $objResponse = new XajaxResponse();
        $objResponse->addAssign("global_parameters", "innerHTML", \Shared\Wui\WuiXml::getContentFromXml('contentlist', $xml));

        return $objResponse;
    }

    public static function ajaxRevertGlobalParameters()
    {
        $xml = \ImpagemanagerPanelController::getGlobalParametersXml();

        $objResponse = new XajaxResponse();
        $objResponse->addAssign("global_parameters", "innerHTML", \Shared\Wui\WuiXml::getContentFromXml('contentlist', $xml));

        return $objResponse;
    }

}
