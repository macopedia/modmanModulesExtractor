# Magento Modman Modules Extractor

This is a console tool for extracting custom Magento modules from the core to modman folder

## What it can do right now:
 
 - create .modman folder and basedir file
 - create modules folders in .modman
 - copy etc/modules/modul_name.xml files
 - create modman file with rule to etc/modules
 - copy files from app/code/ and add a rule to modman
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

 - mode=move - move files instead of copy
 - module-whitelist=MyModule_Name or module-whitelist=MyModule_.* - allows to run the tool just for modules matching regex
 - theme-module=MyModule_Name - name of the module where script should put non standard theme files
 - interactive=0 - disables interactive mode
 

## Authors

[Macopedia.co](http://macopedia.co/en) is a software house providing enterprise level web applications and ecommerce.

