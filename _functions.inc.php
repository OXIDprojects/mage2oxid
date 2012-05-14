<?php

/**
 * Magento shop import script.
 *
 * This file includes helper functions used by Magento importer.
 * Do not run this script directly.
 *
 * @copyright © OXID eSales AG 2009
 * @link http://www.oxid-esales.com/
 *
 */


/**
 * Show base path
 *
 * @return string
 */
function getShopBasePath()
{
    global $sOxidConfigDir;
    return $sOxidConfigDir . "/";
}

/**
 * Prints out the line
 *
 * @param string $sOut
 */
function printLine($sOut)
{
    echo $sOut . "\n";
    flush();
}

/**
 * Dumps var to file
 *
 * @param mixed $mVar var to be dumped
 */
function exportVar($mVar)
{
    ob_start();
    var_dump($mVar);
    $sDump = ob_get_contents();
    ob_end_clean();

    file_put_contents('out.txt', $sDump."\n\n", FILE_APPEND);
}

/**
 * Magento to OXID import handler
 *
 */
class importHandler
{
    /**
     * Shop id
     *
     * @var string
     */
    protected $_sShopId;

    /**
     * Constructs by setting shop id
     *
     * @param int $sShopId ShopId
     */
    public function __construct($sShopId)
    {
        $this->_sShopId = $sShopId;
    }

    /**
     * Deletes all categories
     *
     */
    public function deleteCategories()
    {
        $sQ = "delete from oxcategories where oxshopid = '".$this->_sShopId."'";
        oxDb::getDb()->Execute($sQ);
    }

    /**
     * Recursively imports Magento categories
     *
     * @param array $aCategories Imported category array
     */
    public function importCategories($aMagCategories, $sParentId = null)
    {
        if (!is_array($aMagCategories))
            return;

        foreach ($aMagCategories as $aMagCat)
        {
            //not importing 'Root' and 'Root Catalog levels'
            if ($aMagCat['level'] >= 2)
            {
                $oNewCat = oxNew('oxcategory');
                $oNewCat->setId($aMagCat['category_id']);
                $oNewCat->oxcategories__oxtitle->value = $aMagCat['name'];
                $oNewCat->oxcategories__oxactive->value = $aMagCat['is_active'];

                if ($aMagCat['level'] == 2) {
                    $oNewCat->oxcategories__oxparentid->value = "oxrootid";
                    $oNewCat->oxcategories__oxactive->value = 1;
                }
                else
                    $oNewCat->oxcategories__oxparentid->value = $sParentId?$sParentId:$aMagCat['parent_id'];

                $oNewCat->oxcategories__oxsort->value = $aMagCat['position'];
                printLine("Saving " . $aMagCat['name']);
                $oNewCat->save();
            }
            $this->importCategories($aMagCat['children'], $aMagCat['category_id']);
        }
    }

    /**
     * Rebuilds category tree.
     *
     */
    public function rebuildCategoryTree()
    {
        printLine("Rebuilding category tree..");
        $oCatTree = oxNew("oxcategorylist");
        $oCatTree->updateCategoryTree(false);
    }

    /**
     * Saves a product
     *
     * @param array $aArtInfo product info to be imported
     */
    public function addProduct($aArtInfo)
    {
        $oArticle = oxNew('oxarticle');

        $oArticle->setId($aArtInfo['product_id']);
        $oArticle->oxarticles__oxartnum->setValue($aArtInfo['sku']);
        $oArticle->oxarticles__oxshopid->setValue(1);
        $oArticle->oxarticles__oxinsert->setValue($aArtInfo['created_at']);
        $oArticle->oxarticles__oxtitle->setValue($aArtInfo['name']);
        $oArticle->oxarticles__oxvendorid->setValue($aArtInfo['manufacturer']);
        $oArticle->oxarticles__oxprice->setValue($aArtInfo['price']);
        $oArticle->oxarticles__oxsearchkeys->setValue($aArtInfo['meta_keyword']);
        $oArticle->oxarticles__oxissearch->setValue(1);
        $oArticle->oxarticles__oxshortdesc->setValue($aArtInfo['short_description']);
        $oArticle->oxarticles__oxlongdesc->setValue($aArtInfo['description']);

        //weight
        if (isset($aArtInfo['weight']))
            $oArticle->oxarticles__oxweight->setValue($aArtInfo['weight']);

        //temporary
        $oArticle->oxarticles__oxean->setValue($aArtInfo['type_id']);
        if ($aArtInfo['in_depth'])
            $oArticle->oxarticles__oxlongdesc->setValue($aArtInfo['description'] . " " . $aArtInfo['in_depth']);

        $oArticle->save();

        //adding search tags
        if ($aArtInfo['meta_keyword']) {
            $sTags = str_replace(',', '', $aArtInfo['meta_keyword']);
            $oArticle->saveTags($sTags);
        }
    }

    /**
     * Assigns article to category
     *
     * @param string $sProductId
     * @param array  $aCategoryIds
     */
    public function assignToCategory($sProductId, $aCategoryIds)
    {
        $sProductId = mysql_real_escape_string($sProductId);
        oxDb::getDb()->Execute( "delete from oxobject2category where oxobjectid = '$sProductId'");
        $iPos = 0;
        $i = 0;
        foreach ($aCategoryIds as $sCategoryId) {
            $sId = oxUtilsObject::getInstance()->generateUID();
            $sArtId = mysql_real_escape_string($sProductId);
            $sCategoryId = mysql_real_escape_string($sCategoryId);
            $sPos++;
            $iTime = $i++ * 10;
            $sQ = "insert into oxobject2category (oxid,   oxobjectid, oxcatnid,        oxpos, oxtime) values
                                                 ('$sId', '$sArtId' , '$sCategoryId',  '$sPos', '$iTime')";
            oxDb::getDb()->Execute( $sQ);
        }
    }

    /**
     * Downloads, resizes and applies images to product
     *
     * @param string $sProductId Product id
     * @param array  $aMedia     Media info
     */
    public function addImages($sProductId, $aMedia)
    {
        $myConfig = oxConfig::getInstance();
        $i = 0;
        foreach ($aMedia as $aMedium) {
            $i++;
            $sImageUrl = $aMedium["url"];
            $sImageName = basename($aMedium["file"]);
            $sIcoName = str_replace(".", "_ico.", $sImageName);

            //copy images
            $sFile = file_get_contents($sImageUrl);
            file_put_contents("tmp/$sImageName", $sFile);

            if ($i == 1){
                //save ico and thumb
                list( $sIcoW, $sIcoH ) = explode('*', $myConfig->getConfigParam('sIconsize'));
                list( $sThumbW, $sThumbH ) = explode('*', $myConfig->getConfigParam('sThumbnailsize'));
                $sThumbName = str_replace(".", "_th.", $sImageName);
                oxUtilspic::getInstance()->resizeImage( "tmp/$sImageName", $myConfig->getAbsDynImageDir() . "/icon/$sIcoName", $sIcoW, $sIcoH );
                oxUtilspic::getInstance()->resizeImage( "tmp/$sImageName", $myConfig->getAbsDynImageDir() . "/0/$sThumbName", $sThumbW, $sThumbH );

                $sQ = "update oxarticles set oxicon = '$sIcoName', oxthumb = '$sThumbName' where oxid = '$sProductId'";
                oxDb::getDb()->Execute($sQ);
            }

            $sPicFieldName = "oxpic$i";
            $sPicSizes = $myConfig->getConfigParam('aDetailImageSizes');
            list( $sPicW, $sPicH ) = explode('*', $sPicSizes[$sPicFieldName]);
            oxUtilspic::getInstance()->resizeImage( "tmp/$sImageName", $myConfig->getAbsDynImageDir() . "/$i/$sImageName", $sPicW, $sPicH );
            oxUtilspic::getInstance()->resizeImage( "tmp/$sImageName", $myConfig->getAbsDynImageDir() . "/$i/$sIcoName", $sIcoW, $sIcoH );
            //?
            oxUtilspic::getInstance()->resizeImage( "tmp/$sImageName", $myConfig->getAbsDynImageDir() . "/icon/$sIcoName", $sIcoW, $sIcoH );

            if ($i <= 4) {
                $sZoomFieldName = "oxzoom$i";
                $sZoomSizes = $myConfig->getConfigParam('aZoomImageSizes');
                list( $sZoomW, $sZoomH ) = explode('*', $sZoomSizes[$sZoomFieldName]);
                oxUtilspic::getInstance()->resizeImage( "tmp/$sImageName", $myConfig->getAbsDynImageDir() . "/z$i/$sImageName", $sZoomW, $sZoomH );
                oxUtilspic::getInstance()->resizeImage( "tmp/$sImageName", $myConfig->getAbsDynImageDir() . "/z$i/$sIcoName", $sIcoW, $sIcoH );
                //do we import more than 4 zoom images
                $sZoomUpdate = ", $sZoomFieldName = '$sImageName'";
            } else {
                $sZoomUpdate = '';
            }

            $sQ = "update oxarticles set $sPicFieldName = '$sImageName' $sZoomUpdate where oxid = '$sProductId'";
            oxDb::getDb()->Execute($sQ);

            unlink("tmp/$sImageName");
        }
    }

    /**
     * Assigns crosseling products
     *
     * @param string $sProductId Product id
     * @param array  $aLinkInfo  Link info
     */
    public function assignCrosseling($sProductId, $aLinkInfo)
    {
        $sProductId = mysql_real_escape_string($sProductId);
        oxDb::getDb()->Execute( "delete from oxobject2article where oxarticlenid = '$sProductId'");

        foreach ($aLinkInfo as $aLink) {
            $sId = oxUtilsObject::getInstance()->generateUID();
            $sCrossSell = $aLink["product_id"];
            $sQ = "insert into oxobject2article (oxid, oxarticlenid, oxobjectid) values ('$sId', '$sProductId', '$sCrossSell')";
            oxDb::getDb()->Execute($sQ);
        }
    }

    /**
     * Assigns accesoires
     *
     * @param string $sProductId Product id
     * @param array  $aLinkInfo  Link info
     */
    public function assignAccesoires($sProductId, $aLinkInfo)
    {
        $sProductId = mysql_real_escape_string($sProductId);
        oxDb::getDb()->Execute( "delete from oxaccessoire2article where oxarticlenid = '$sProductId'");

        foreach ($aLinkInfo as $aLink) {
            $sId = oxUtilsObject::getInstance()->generateUID();
            $sCrossSell = $aLink["product_id"];
            $sQ = "insert into oxaccessoire2article (oxid, oxarticlenid, oxobjectid) values ('$sId', '$sProductId', '$sCrossSell')";
            oxDb::getDb()->Execute($sQ);
        }
    }

    /**
     * Assigns stock
     *
     * @param string $sProductId Product id
     * @param int    $iStock     on stock count
     */
    public function assignStock($sProductId, $iStock)
    {
        $sProductId = mysql_real_escape_string($sProductId);
        $iStock = (int)$iStock;
        $sQ = "update oxarticles set oxstock = '$iStock' where oxid = '$sProductId'";
        oxDb::getDb()->Execute($sQ);
    }

    /**
     * Assigns parent to article
     *
     * @param string $sProductId Product id
     * @param string $sParentId  Parent id
     */
    public function assignParent($sProductId, $sParentId)
    {
        $sProductId = mysql_real_escape_string($sProductId);
        $sParentId = mysql_real_escape_string($sParentId);
        //set parent article
        $sQ = "update oxarticles set oxparentid = '$sParentId' where oxid = '$sProductId'";
        oxDb::getDb()->Execute($sQ);
        //update stock and variant info
        $sVarStock = oxDb::getDb()->getOne("select oxstock from oxarticles where oxid = '$sProductId'");
        $sQ = "update oxarticles set oxvarcount = oxvarcount + 1, oxvarstock = oxvarstock + '$sVarStock' where oxid = '$sParentId'";
        oxDb::getDb()->Execute($sQ);

        //delete direct variant asignment to category
        oxDb::getDb()->Execute( "delete from oxobject2category where oxobjectid = '$sProductId'");

        //delete tags
        oxDb::getDb()->Execute( "update oxartextends set oxtags = '' where oxid = '$sProductId'");
    }

}