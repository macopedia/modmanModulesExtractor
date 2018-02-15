# Magento Modman/Composer Modules Extractor

This is a console tool for extracting custom Magento modules from the core to modman folder or for usage with Composer.

## What it can do right now:
 
 - create .modman folder and basedir file
 - create modules folders in .modman (or other folder defined with `destinationFolder` parameter)
 - copy etc/modules/modul_name.xml files
 - create modman file with rule to etc/modules
 - copy files from app/code/ and add a rule to modman file
 - create composer.json file for a module
 - detect language files based on config.xml and copying them to modman module
 - detect modules template folders with fuzzy logic, interactively asking user to confirm copying the files to appropriate modman module
 - copy custom themes to configured "theme module"
 - copy email templates

## How to run it?

The script assumes that webroot (and the folder from which this script is run from) is called "htdocs". The script will create .modman folder as a sibling to htdocs. 

To run it, just enter the folder where you have Magento installed and run:
```
php extractToModman.php
```

## Script arguments:

 - mode=move - move files instead of copy, default is "copy"
 - module-whitelist=MyModule_Name or module-whitelist=MyModule_.* - allows to run the tool just for modules matching regex
 - theme-module=MyModule_Name - name of the module where script should put non standard theme files
 - interactive=0 - disables interactive mode
 - webroot=web - sets project webroot to "web" folder, by default Extractor uses "htdocs" folder as a webroot
 - destinationFolder=../modules/ - sets folder path where extrated modules will be stored. By default script uses `../.modman/` as a destination folder. For composer usage use `../modules/`
 - nomodman=1 - disables creation of the "modman" configuration file in extracted modules. Useful when extracting modules for Composer
 

## Authors

[Macopedia.com](http://macopedia.com/) is a software house providing enterprise level web applications and ecommerce solutions.

