<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

if (strpos($_SERVER['REQUEST_URI'], $arParams["SEF_FOLDER"]) !== 0)
	return;

if (!isset($arParams["PAGE_TYPES"]) || !is_array($arParams["PAGE_TYPES"]))
	return;

if (!isset($arParams["TEMPLATES"]) || !is_array($arParams["TEMPLATES"]))
	$arParams["TEMPLATES"] = array();

require_once 'classes/DynamicPaths.php';

$dpages = new \mospans\DynamicPaths($arParams["TEMPLATES"], $arParams["PAGE_TYPES"], $arParams['SEF_FOLDER']);

//$this->IncludeComponentTemplate();
?>