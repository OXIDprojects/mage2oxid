This script helps to merge data from Magento to OXID eShop.


-----------------
What is imported?
-----------------

The module imports categories, products, the product assignments to categories, product pictures (resized, if needed), crosssellings and accessoires.


-------------
How to import
-------------

1) Edit Settings in _config.inc.php

    In the Magento importer, there is a file named _config.inc.php. In this file, several settings have to be made:

    * $sOxidConfigDir
      This is the path to your OXID eShop on the Server (not the URL!). You can find this path in the eShop admin:
          1) Log in to eShop Admin.
          2) Go to Service -> System Info.
          3) Search the setting _SERVER["DOCUMENT_ROOT"]. The value shown on the right is the path to your eShop.
   
    * $sMagentoUrl
      The URL of your Magento Shop, e.g. http://MyMagentoShop.com
   
    * $sMagentoApiUsername and $sMagentoApiPassword
      The Magento API Username and Password. You can find out how to set up an API user in the Magento Forums: http://www.magentocommerce.com/boards/viewthread/23208/.
   
    * $blImportImages
      This setting defines if product images are imported or not. Set the value to true if you want the pictures imported, to false if not.



2) Copy all files to the eShop server

     Copy all files from the magento importer to the server the eShop runs on.



3) Run magento2oxid.php

    Next, the import script has to be run. As the import may take quite long (up to several hours), the script should be called from the command line of your server with: php magento2oxid.php
    If you don't know how to access the command line of your server, please ask your web host for assistance.
    After calling the script, the import is started. Depending on the amount of products in your Magento shop, this may take a few hours. In the command line, the different steps of the import are shown. When the import is finished, the total time the import needed is shown.