<?php
// ---- SETUP ----
$packageName = "owm";
// ---------------

//error_reporting( E_ERROR );
use \MSergeev\Core\Lib\Config;
use \MSergeev\Core\Lib\Loader;

$packageNameToUpper = strtoupper($packageName);
Config::addConfig($packageNameToUpper.'_ROOT',Config::getConfig('PACKAGES_ROOT').$packageName."/");
//Config::addConfig($packageNameToUpper.'_PUBLIC_ROOT',Config::getConfig('PUBLIC_ROOT').$packageName."/");
//Config::addConfig($packageNameToUpper.'_TOOLS_ROOT',str_replace(Config::getConfig("SITE_ROOT"),"",Config::getConfig('PACKAGES_ROOT').$packageName."/tools/"));


//***** Tables ********
Loader::includeFiles(Config::getConfig($packageNameToUpper.'_ROOT')."tables/");

//***** Lib ********
Loader::includeFiles(Config::getConfig($packageNameToUpper.'_ROOT')."lib/");



