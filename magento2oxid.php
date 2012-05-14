<?php

/**
 * Magento shop import script.
 *
 * Set configuration params in _config.inc.php
 * and run this script from command line (recommended):
 * >php magento2oxid.php
 *
 * @copyright � OXID eSales AG 2009
 * @link http://www.oxid-esales.com/
 *
 */

$iStartTime = time();

//CONFIGURATION
require_once("_config.inc.php");
require_once("_functions.inc.php");

//------------------------------------------------------------------------------------
//IMPLEMENTATION
set_time_limit(0);

//init OXID framework
@include_once(getShopBasePath() . "/_version_define.php");
require_once(getShopBasePath(). "/core/oxfunctions.php");
require_once(getShopBasePath(). "/core/adodblite/adodb.inc.php");

//default OXID shop id
$sShopId = oxConfig::getInstance()->getBaseShopId();

$oIHandler = new importHandler($sShopId);

printLine("<pre>");

//connect to Magento
$proxy = new SoapClient($sMagentoUrl . '/api/soap/?wsdl');
$sSessionId = $proxy->login($sMagentoApiUsername, $sMagentoApiPassword);



//--- CATEGORIES ---------------------------------------------------------
printLine("IMPORTING CATEGORIES");
//importing categories
try {
    $aAllMagCategories = $proxy->call($sSessionId, 'category.tree'); // Get all categories.
} catch (Exception $e) {
    printLine("Import fault\n" . "Error: " . $e->getMessage());
    die();
}

//exportVar($aAllMagCategories);

$oIHandler->importCategories(array($aAllMagCategories));
$oIHandler->rebuildCategoryTree();
printLine("Done.\n");

//------------------------------------------------------------------------


//--- ARTICLES -----------------------------------------------------------
printLine("IMPORTING ARTICLES");
try {
    $aAllMagArticles = $proxy->call($sSessionId, 'product.list'); // Get all articles
} catch (Exception $e) {
    printLine("Article import fault");
    die();
}

//exportVar($aAllMagArticles[0]);

$aAllArticles = array();
$aAllParents = array();

$i = 0;
foreach ($aAllMagArticles as $aArticle)
{
    $i++;
    $aArtInfo = $proxy->call($sSessionId, 'product.info', $aArticle['product_id']);
    //exportVar($aArtInfo);
    $oIHandler->addProduct($aArtInfo);
    $oIHandler->assignToCategory($aArticle['product_id'], $aArticle['category_ids']);

    if ($blImportImages) {
        $aMedia = $proxy->call($sSessionId, 'product_media.list', $aArticle['product_id']);
        $oIHandler->addImages($aArticle['product_id'], $aMedia);
    }

    $aListInfo = $proxy->call($sSessionId, 'product_link.list', array('cross_sell', $aArticle['product_id']));
    $oIHandler->assignCrosseling($aArticle['product_id'], $aListInfo);

    $aListInfo = $proxy->call($sSessionId, 'product_link.list', array('up_sell', $aArticle['product_id']));
    $oIHandler->assignAccesoires($aArticle['product_id'], $aListInfo);

    //assign stock
    $aStockInfo = $proxy->call($sSessionId, 'product_stock.list', $aArticle['product_id']);
    $oIHandler->assignStock($aArticle['product_id'], $aStockInfo[0]["qty"]);

    printLine("$i done.");

    if ($aArtInfo['type_id'] == 'configurable') {
        $aAllParents[$aArticle['product_id']] = $aArtInfo['sku'];
    } else {
        $aAllArticles[$aArticle['product_id']] = $aArtInfo['sku'];
    }

}

printLine("Done.\n");

//--- VARIANTS -----------------------------------------------------------

//handle variants
//that's experimental part
printLine("HANDLING VARIANTS");
foreach ($aAllParents as $sParentId => $sParentNr)
{
    foreach($aAllArticles as $sId => $sProductNr)
        if (strpos($sProductNr, $sParentNr . "_") === 0 || strpos($sProductNr, $sParentNr . "-") === 0)
            $oIHandler->assignParent($sId, $sParentId);
}
printLine("Done.\n");


printLine("Import finished!");
$iTotalTime = time() - $iStartTime;
printLine("Total time: " . sprintf("%02d:%02d:%02d", ($iTotalTime/3600), ($iTotalTime/60)%60, $iTotalTime%60));

//------------------------------------------------------------------------
printLine("</pre>");