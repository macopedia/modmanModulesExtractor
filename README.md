# modmanModulesExtractor
Tool for extracting custom Magento modules from the core to modman folder

 What it can do right now:
 - create .modman folder and basedir file
 - create modules folders in .modman
 - copy etc/modules/modul_name.xml files
 - create modman file with rule to etc/modules
 - copy files from app/code/ and add a rule to modman
 - detect language files based on config.xml and copying them to modman module
 - detect modules template folders with fuzzy logic, interactively asking user to confirm copying the files to appropriate modman module
 - copy custom themes to configured "theme module"
 - copy email templates
 
 Params:
 - move - move files instead of copy
 - module-whitelist=MyModule_Name or module-whitelist=MyModule_.* - allows to run the tool just for modules matching regex
 - theme-module=MyModule_Name - name of the module where script should put non standard theme files
 - interactive=0 - disables interactive mode



