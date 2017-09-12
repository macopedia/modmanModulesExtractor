#!/usr/bin/env php
<?php
/*
Created by Tymoteusz Motylewski
Macopedia.pl
*/

if (version_compare(PHP_VERSION, '5.3.0') <= 0) {
    throw new Exception('This tool needs at least PHP 5.3');
}

define('EXTRACT_ROOTDIR', dirname(__FILE__));

//we assume that webroot and the folder from which this script is called is "htdocs"
//script will create .modman folder as a sibling to htdocs
define('MODMAN_ROOT', '../.modman/');

function out($message = '')
{
    echo $message . "\n";
}

function createDir($path)
{
    if (!is_dir($path)) {
        $result = mkdir($path, 0777, true);
        if (!$result) {
            out('can not create dir' . $path);
        }
    } else {
        out($path . ' Already exist');
    }
}

/*
 * Coding guidelines:
 * all paths should end with / but should not start with one
 */

/**
 * Class ModulesExtractor
 * What it can do right now:
 *  create .modman folder and basedir file
 *  create modules folders in .modman
 *  copy etc/modules/modul_name.xml files
 *  create modman file with rule to etc/modules
 *  copy files from app/code/ and add a rule to modman
 *  detect language files based on config.xml and copying them to modman module
 *  detect modules template folders with fuzzy logic, interactively asking user to confirm copying the files to appropriate modman module
 *  copy custom themes to configured "theme module"
 *  copy email templates
 *
 * params:
 * move - move files instead of copy
 * module-whitelist=MyModule_Name or module-whitelist=MyModule_.* - allows to run the tool just for modules matching regex
 *
 * theme-module=MyModule_Name - name of the module where script should put non standard theme files
 * interactive=0 - disables interactive mode
 *
 */
class ModulesExtractor
{
    protected $etcModulesPath = 'app/etc/modules/';

    protected $composerNames = array();

    public function run()
    {
        $config = array(
            'mode' => 'copy',
            'moduleWhitelist' => '.*',
            'themeModuleName' => '',
            'interactive' => true

        );
        global $argv;
        foreach ($argv as $arg) {
            $argArray = explode('=', $arg);
            if ($argArray[0] === 'mode' && isset($argArray[1]) && $argArray[1] === 'move') {
                $config['mode'] = 'move';
                continue;
            }
            if ($argArray[0] === 'module-whitelist' && isset($argArray[1])) {
                $config['moduleWhitelist'] = $argArray[1];
                continue;
            }
            if ($argArray[0] === 'theme-module' && isset($argArray[1])) {
                $config['themeModuleName'] = $argArray[1];
                continue;
            }
            if ($argArray[0] === 'interactive' && isset($argArray[1])) {
                $config['interactive'] = (bool)intval(trim($argArray[1]));
                continue;
            }

        }

        $etcModulesPath = $this->etcModulesPath;

        if (!empty($config['themeModuleName'])) {
            $themeModuleEtcFilePath = $etcModulesPath . $config['themeModuleName'] . '.xml';
            if (!file_exists($themeModuleEtcFilePath)) {
                $moduleName = $config['themeModuleName'];
                $content = <<<EOD
<?xml version="1.0"?>
<config>
    <modules>
        <$moduleName>
            <active>true</active>
            <codePool>local</codePool>
        </$moduleName>
    </modules>
</config>
EOD;
                file_put_contents($themeModuleEtcFilePath, $content);
            }
        }

        $allModules = scandir($etcModulesPath);
        $etcModuleFilesToSkipRegex = '/(Mage_.*)|(Phoenix_Moneybookers.*)/i';
        foreach ($allModules as $key => $moduleFileName) {
            $moduleName = substr($moduleFileName, 0, -4);
            if (
                is_dir($moduleFileName)
                || is_link($moduleFileName)
                || empty(trim($moduleFileName))
                || preg_match($etcModuleFilesToSkipRegex, $moduleFileName) === 1
                || preg_match('#' . $config['moduleWhitelist'] . '#', $moduleName) == 0
                || $moduleName == $config['themeModuleName']
            ) {
                unset($allModules[$key]);
            }
        }
        // create modman folder and basedir file
        createDir(MODMAN_ROOT);
        if (!file_exists(MODMAN_ROOT . '.basedir')) {
            file_put_contents(MODMAN_ROOT . '.basedir', 'htdocs');
        }

        // move theme module to the top, so it's processed as the first one
        if (!empty($config['themeModuleName'])) {
            $this->processModule($config['themeModuleName'] . '.xml', $config);
        }

        // handle all registered modules
        foreach ($allModules as $moduleFileName) {
           $this->processModule($moduleFileName, $config);
        }


        out("Done.");
        out("Things you have to take care of manually:");
        out("1. Handling custom Magento core translations and locales, e.g. from de_CH locales");
        out("2. Copy skin folders and files");
        out("3. Extracting code overrides, compare core");
        out("4. Check if core templates were not modified in e.g. base/default");
        out("5. Checking custom error pages and styling from webroot/error folder");
        out("6. copying etc/local.xml, configuration, .htaccess modifications, robots.txt and other custom files from webroot");
        out("7. If you want to add packages to composer call:");
        foreach ($this->composerNames as $composerName) {
            out('composer require '. $composerName . ':1.0');
        }
        out('And add following lines to your project composer.json');
        out('
    "repositories": [
    {
      "type": "path",
      "url": "./modules/*",
        "options": {
          "symlink": true
      }
    }
  ],');

    }

    protected function processModule($moduleFileName, $config) {
            $etcFilePath = $this->etcModulesPath . $moduleFileName;
            out();
            out("\033[1mProcessing Module \"" . $moduleFileName . "\033[0m");

            $module = new Module($config);
            $module->loadDataFromEtcModules($etcFilePath);
            $module->createBasicModmanModuleDirectoryStructure();
            $module->copyToModmanModule($etcFilePath);

            $module->loadDataFromConfigXml();
            $module->copyCodeFolder();
            $module->copyLanguageFiles();
            $module->copyCustomThemes();
            $module->copyLayoutXmlFiles();
            $module->copyEmailTemplates();
            $module->fuzzyFindTemplateFolders();
            $module->saveModman();
            $module->saveComposerJson();
            $this->composerNames[] = $module->composerName;

            if ($config['mode'] === 'move') {
                $module->removeCopiedFiles();
            }
    }
}
/**
 * Class Module
 */
class Module
{
    public $codePool = '';
    public $xmlName = '';
    public $vendorName = '';
    protected $modmanLines = array();
    /**
     * Ending with /
     * @var string
     */
    protected $moduleModmanPath = '';
    protected $active = '';
    protected $magentoModuleCodePath = '';
    protected $config = null;
    protected $layoutFilesFromCustomThemes = array();
    protected $copiedFiles = array();
    protected $appConfig = array();
    public $composerName;

    public function __construct($config = array())
    {
        if (isset($config['mode'])) {
            $this->mode = $config['mode'];
        }
        $this->appConfig = $config;
    }

    public function loadDataFromEtcModules($path)
    {
        $moduleXmlConfiguration = simplexml_load_file($path);

        /** @var SimpleXMLElement $module */
        $module = $moduleXmlConfiguration->modules->children()[0];
        $this->xmlName = $module->getName();
        $this->codePool = (string)$module->codePool;
        $this->active = (string)$module->active;
        $this->moduleModmanPath = MODMAN_ROOT . $this->xmlName . '/';
        $this->magentoModuleCodePath = 'app/code/' . $this->codePool . '/' . str_replace('_', '/', $this->xmlName) . '/';
    }

    public function loadDataFromConfigXml()
    {
        $path = $this->magentoModuleCodePath . 'etc/config.xml';
        if (file_exists($path)) {
            $this->config = simplexml_load_file($path);
        }
    }

    public function copyEmailTemplates() {
        if (!$this->config) {
           return;
        }
        $filePaths = $this->config->xpath('//template/email/*/file');
        $files = array();
        foreach ($filePaths as $filePath) {
            $path = (string)$filePath;
            if (empty(trim($path))) {
                continue;
            }
            $files[$path] = $path;
        }
        foreach ($files as $file) {
            $this->findAndCopyEmailTemplateFiles($file);
        }
    }
    protected function findAndCopyEmailTemplateFiles($file) {
        $emails = glob('app/locale/*/template/email/' . $file);
        foreach ($emails as $template) {
            $templateFolder = dirname($template);
            if (is_dir($templateFolder)) {
                if (file_exists($template)) {
                    $this->createDirInModmanModule($templateFolder);
                    $this->copyToModmanModule($template);
                }
            }
        }
    }

    public function copyLayoutXmlFiles()
    {
        if (!$this->config) {
           return;
        }
        $filePaths = $this->config->xpath('//layout/updates/*');
        $files = array();
        foreach ($filePaths as $filePath) {
            $path = (string)$filePath->children();
            $files[$path] = $path;
        }
        foreach ($files as $file) {
            $this->copyLayoutFilesFromBuiltInThemes($file);
        }
    }

    /**
     * @param $file
     */
    protected function copyLayoutFilesFromBuiltInThemes($file)
    {
        $layouts = glob('app/design/*/*/*/layout/' . $file);

        foreach ($layouts as $layout) {
            $layoutFolder = dirname($layout);
            if ($this->isBuiltInTheme($layout)) {
                if (is_dir($layoutFolder)) {
                    if (file_exists($layout)) {
                        $this->createDirInModmanModule($layoutFolder);
                        $this->copyToModmanModule($layout);
                    }
                }
            } else {
                    out('found custom layout file: ' . $layout);
            }
        }
    }

    protected function isBuiltInTheme($path)
    {
        $defaultThemes = array(
            'base/default/',
            'default/blank/',
            'default/default/',
            'default/iphone/',
            'default/modern/',
            'rwd/default/',
            'adminhtml/default/',
            'enterprise/default/'
        );
        foreach ($defaultThemes as $theme) {
            if (strpos($path . '/', $theme) !== FALSE) {
                return true;
            }
        }
        return false;
    }

    public function createDirInModmanModule($path)
    {
        createDir($this->moduleModmanPath . $path);
    }

    public function copyToModmanModule($path)
    {
        $result = copy($path, $this->moduleModmanPath . $path);
        if ($result) {
            $this->copiedFiles[] = $path;
        } else {
            out("couldn't copy file " . $path . " to: " . $this->moduleModmanPath . $path);
        }
        $this->addModmanLine($path, $path);
    }

    public function addModmanLine($source, $target = '')
    {
        $this->modmanLines[] = array($source, $target);
    }

    public function isThemeModule() {
        return isset($this->appConfig['themeModuleName']) && $this->appConfig['themeModuleName'] === $this->xmlName;
    }

    public function copyCustomThemes()
    {
        if (!$this->isThemeModule()) {
            return;
        }
        $themes = glob('app/design/frontend/*/*');
        foreach ($themes as $theme) {
            if (is_dir($theme) && !$this->isBuiltInTheme($theme)) {
                $this->copyFolderToModman($theme);
            }
        }
    }
//TODO: custom themes are not copied.
    /**
     * @param $path string path relative to webroot
     */
    protected function copyFolderToModman($path)
    {
        if (is_dir($path)) {
            $path = rtrim($path,'/') . '/';
            createDir($this->moduleModmanPath . $path);
            //workaround for the recursive directory copy, PHP doesn't have sth like that
            shell_exec("cp -r " . $path . " " . $this->moduleModmanPath . $path . '../');
            $this->copiedFiles[] = $path;
            $this->addModmanLine($path, $path);
        } else {
            out('folder: ' . $path . ' does not exist');
        }
    }

    public function fuzzyFindTemplateFolders()
    {
        $templateFolders = glob('app/design/*/*/*/template/*');
        foreach ($templateFolders as $templateFolder) {

            if (!is_dir($templateFolder) || !$this->isBuiltInTheme($templateFolder) || $this->isBuiltInTemplateFolder(basename($templateFolder))) {
                continue;
            }
            $similarity = $this->fuzzyMatch($templateFolder);
            if ($similarity > 50.0) {
                $parentFolder = dirname($this->moduleModmanPath . $templateFolder);
                out();
                out("\033[1mTemplate folder \"" . $templateFolder . '" might origin from ' . $this->xmlName . " module. Similarity: " . round($similarity, 2) . "%\033[0m");
                out('if you want to move it, run following commands:');
                out("\t" . 'mkdir -p ' . $parentFolder . ' ; mv ' . $templateFolder . ' ' . $this->moduleModmanPath . $templateFolder);
                out("\t" . 'printf "\\n' . $templateFolder . ' \\t' . $templateFolder . '"  >> ' . $this->moduleModmanPath . 'modman');
                out();
                if (isset($this->appConfig['interactive']) &&  $this->appConfig['interactive'] === true) {
                    out('Should above commands be executed right away? [Y/N]');
                    $line = trim(fgets(STDIN));
                    if (strtolower($line) == 'y') {
                        $this->copyFolderToModman($templateFolder);
                        out('Done.');
                    } else {
                        out("Skipped.");
                    }
                }
            }
        }
    }

    protected function fuzzyMatch($templateFolder)
    {
        $word = strtolower($this->xmlName);
        $folderName = strtolower(basename($templateFolder));
        //   out('cehcking similarity of '. $word . ' and ' . $folderName);
        $percentage = 0.0;
        similar_text($word, $folderName, $percentage);

        if (soundex($word) == soundex($folderName)) {
            out('Soundex: MATCH');
        } else {
            // out('Soundex: no match');
        }
        return $percentage;
    }

    public function copyLanguageFiles() {
        if (!$this->config) {
           return;
        }
        $filePaths = $this->config->xpath('//translate/modules/*/*');
        $files = array();
        foreach ($filePaths as $filePath) {
            $path = (string)$filePath->children();
            $files[$path] = $path;
        }

        foreach ($files as $file) {
            $this->copyFileFromAllLocales($file);
        }
    }

    protected function copyFileFromAllLocales($fileName)
    {
        $allLocales = scandir('app/locale');
        foreach ($allLocales as $locale) {
            $localeFolder = 'app/locale/' . $locale . '/';
            if (is_dir($localeFolder)) {
                if (file_exists($localeFolder . $fileName)) {
                    createDir($this->moduleModmanPath . $localeFolder);
                    $this->copyToModmanModule($localeFolder . $fileName);
                }
            }
        }
    }

    public function saveModman()
    {
        $lines = "#Autogenerated Modman file " . date('YYYY-m-d H:i:s');
        foreach ($this->modmanLines as $modmanLine) {
            $lines .= "\n" . implode(" \t", $modmanLine);
        }
        file_put_contents($this->moduleModmanPath . 'modman', $lines, FILE_APPEND);
    }

    public function saveComposerJson()
    {
        $composerJson = $this->getComposerJson();
        file_put_contents($this->moduleModmanPath . 'composer.json', $composerJson);
    }

    public function copyCodeFolder()
    {
        $folderPath = $this->magentoModuleCodePath;
        $this->copyFolderToModman($folderPath);
    }

    public function createBasicModmanModuleDirectoryStructure()
    {
        $this->createDirInModmanModule('app/etc/modules/');
    }

    public function removeCopiedFiles($verbose = false)
    {
        foreach ($this->copiedFiles as $file) {
            if (is_dir($file)) {
//                rmdir($file);
                $out = shell_exec("rm -r " . $file);
                out($out);
            } else {
                $result = unlink($file);
                if (!$result) {
                    out("Couldn't remove file: ". $file);
                }
            }
        }
    }

    protected function isBuiltInTemplateFolder($folder)
    {
        $builtInAdminhtmlTemplateFolders = array(
            'api', 'api2', 'authorizenet', 'backup', 'bundle', 'captcha', 'catalog', 'centinel', 'cms', 'compiler', 'connect',
            'currencysymbol', 'customer', 'dashboard', 'directory', 'downloadable', 'eav', 'email', 'giftmessage', 'googlebase',
            'importexport', 'index', 'media', 'moneybookers', 'newsletter', 'notification', 'oauth', 'page', 'pagecache', 'paygate',
            'payment', 'paypal', 'permissions', 'poll', 'promo', 'rating', 'report', 'review', 'sales', 'store', 'system', 'tag',
            'tax', 'urlrewrite', 'usa', 'weee', 'widget', 'xmlconnect',
        );
        $builtInFrontendTemplateFolders = array(
            'authorizenet', 'bundle', 'callouts', 'captcha', 'catalog', 'cataloginventory', 'catalogsearch', 'centinel',
            'checkout', 'cms', 'contacts', 'core', 'customer', 'directory', 'downloadable', 'email', 'giftmessage', 'googleanalytics',
            'moneybookers', 'newsletter', 'oauth', 'page', 'pagecache', 'paygate', 'payment', 'paypal', 'persistent', 'poll',
            'productalert', 'rating', 'reports', 'review', 'rss', 'sales', 'sendfriend', 'shipping', 'tag', 'tax', 'wishlist', 'xmlconnect',
        );
        $allFolders = array_merge($builtInAdminhtmlTemplateFolders, $builtInFrontendTemplateFolders);
        if (array_search($folder, $allFolders) !== FALSE) {
            return true;
        }
        return false;
    }

    protected function getComposerJson() {

        $mapping = array();
        foreach ($this->modmanLines as $modmanLine) {
            $mapping[] = $modmanLine;
        }
        $composerName = strtolower(str_replace('_', '/', $this->xmlName));
        $json = array(
            'name' => $composerName,
            'type' => "magento-module",
            'version' => '1.0',
            'extra' => array(
                'map' => array()
            )
        );
        $this->composerName = $composerName;
        $json['extra']['map'] = $mapping;
        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}



$moduleExtractor = new ModulesExtractor();
$moduleExtractor->run();