<?php

/**
 * Magento shop import script.
 *
 * Configuration file for magento import script
 * Edit this file before running the importer.
 *
 * @copyright © OXID eSales AG 2009
 * @link http://www.oxid-esales.com/
 *
 */


//the path of fully installed OXID eShop
$sOxidConfigDir = "/www/htdocs/oxidshop/";

//Magento shop URL
$sMagentoUrl = "http://MyMagentoShop.com/";

//authorisation info
//(more info on creating API accounts: http://www.magentocommerce.com/boards/viewthread/23208/)
$sMagentoApiUsername = "apiuser";
$sMagentoApiPassword = "apikey";

//Include images in import?
//(thta's slow) as it resizes them
$blImportImages = true;

//that's it!
//now run the import script.