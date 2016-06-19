<?php
namespace mospans;

class DynamicPaths {
	private $PAGE_TYPES = array();
	private $REAL_PATHS = array();
	private $URL_PARTS = array();
	private $ADD_TO_CHAIN = array();
	private $CHAIN_NAME = array();
	private $TEMPLATES = array();
	
	private $requestURIParts = array();
	private $getString = '';
	private $chainParameters = array();
	
	public $result = array();
	
	public function __construct($templates = array(), $pageTypes = array(), $sefFolder = '')
	{
		if (class_exists('\CBXShortUri')) {
			$rsData = \CBXShortUri::GetList(Array(), Array());
			global $APPLICATION;
			while ($arRes = $rsData->Fetch()) {
				if ('/' . $arRes["SHORT_URI"] == $APPLICATION->GetCurPage()) {
					LocalRedirect($arRes["URI"]);
				}
			}
		}
        if (!\CModule::IncludeModule('iblock')) {
			die('Module iblock is not found!');
		}
		
		$this->parseTemplates($templates);
		
		foreach ($pageTypes as $strCode => $strItem) {
			if (!in_array($strItem['URL_TEMPLATE'], $this->PAGE_TYPES)) {
				$this->PAGE_TYPES[] = $strItem['URL_TEMPLATE'];
				$this->REAL_PATHS[$strItem['URL_TEMPLATE']] = $strItem['REAL_PATH'];
				$this->URL_PARTS[$strItem['URL_TEMPLATE']] = $this->getParamsFromURI($strItem['URL_TEMPLATE']);
				$this->ADD_TO_CHAIN[$strItem['URL_TEMPLATE']] = (isset($strItem['ADD_TO_CHAIN']) && $strItem['ADD_TO_CHAIN'] == 'Y');
				$this->CHAIN_NAME[$strItem['URL_TEMPLATE']] = (isset($strItem['CHAIN_NAME'])) ? $strItem['CHAIN_NAME'] : '';
			}
		}
		
		$this->requestURIParts = $this->getParamsFromURI($_SERVER['REQUEST_URI']);
		
		foreach ($this->getParamsFromURI($sefFolder) as $sefPart) {
			if ($sefPart === $this->requestURIParts[0]) {
				array_shift($this->requestURIParts);
			} else {
				break;
			}
		}
		
		if (count($this->requestURIParts) > 0) {
			$this->checkURI();
		}
	}
	
	private function parseTemplates($templates)
	{
		if (is_array($templates)) {
			foreach ($templates as $templateName => $templateParameters) {
				$this->TEMPLATES[$templateName] = $templateParameters;
			}
		}
	}

    private function getParamsFromURI($uri)
	{
		$retURI = array();
		$tmpURIParts = explode('?', trim($uri));
		if (isset($tmpURIParts[1])) {
			$this->getString = $tmpURIParts[1];
		}
		$tmpURIParts = explode('/', trim($tmpURIParts[0]));
		$tmpcount = count($tmpURIParts);
		foreach ($tmpURIParts as $pos => $part) {
			if (($pos === 0 || $pos === $tmpcount - 1) && $part === '') {
				continue;
			}
			if($pos === $tmpcount - 1 && $part === 'index.php') {
				continue;
			}
			$retURI[] = $part;
		}
		return $retURI;
    }

    private function getPageByURI($uri)
	{
		if (file_exists($_SERVER["DOCUMENT_ROOT"] . $uri)) {
			global $APPLICATION;
			\CHTTP::SetStatus("200 OK");
			//$this->pagestatus=200;
			$this->updateChain();
			require_once($_SERVER["DOCUMENT_ROOT"] . $uri);
		} else {
			$this->get404Page();
		}
    }
	
	private function checkURI()
	{
		$successExecution = false;
		foreach ($this->PAGE_TYPES as $pageType) {
			if (count($this->URL_PARTS[$pageType]) !== count($this->requestURIParts)) {
				continue;
			}
			$countConstantParameters = 0;
			$countVariableParameters = 0;
			$countFoundedVariableParameters = 0;
			foreach ($this->URL_PARTS[$pageType] as $URLTemplatePos => $URLTemplatePart) {
				if ($this->requestURIParts[$URLTemplatePos] === '') {
					break;
				}
				
				$chainCurrentName = '';
				
				$URLTemplatePartIsVariable = false;
				if (substr($URLTemplatePart, 0, 1) == '#' && substr($URLTemplatePart, strlen($URLTemplatePart) - 1, 1) == '#') {
					$URLTemplatePartIsVariable = true;
				}
				
				if ($URLTemplatePart == $this->requestURIParts[$URLTemplatePos]) {
					$countConstantParameters++;
				}
				
				if (isset($this->TEMPLATES[$URLTemplatePart]) && ($URLTemplatePart == $this->requestURIParts[$URLTemplatePos] || $URLTemplatePartIsVariable)) {
					// then need to search by template filter
					if ($URLTemplatePartIsVariable) {
						$countVariableParameters++;
					}
					
					$arFilterForElem = array();
					$arSelectForElem = array("IBLOCK_ID", "ID", "NAME", "CODE");
					
					if (isset($this->TEMPLATES[$URLTemplatePart]['FILTER']) && is_array($this->TEMPLATES[$URLTemplatePart]['FILTER'])) {
						$arFilterForElem = $this->TEMPLATES[$URLTemplatePart]['FILTER'];
					}
					if (isset($this->TEMPLATES[$URLTemplatePart]['TARGET_PARAMETER']) && $URLTemplatePartIsVariable) {
						$arFilterForElem[$this->TEMPLATES[$URLTemplatePart]['TARGET_PARAMETER']] = $this->requestURIParts[$URLTemplatePos];
					}
					
					if (isset($this->TEMPLATES[$URLTemplatePart]['FIELDS']) && is_array($this->TEMPLATES[$URLTemplatePart]['FIELDS'])) {
						foreach ($this->TEMPLATES[$URLTemplatePart]['FIELDS'] as $field) {
							$arSelectForElem[] = $field;
						}
					}
					if (isset($this->TEMPLATES[$URLTemplatePart]['PROPERTIES']) && is_array($this->TEMPLATES[$URLTemplatePart]['PROPERTIES'])) {
						foreach ($this->TEMPLATES[$URLTemplatePart]['PROPERTIES'] as $property) {
							$arSelectForElem[] = 'PROPERTY_' . $property;
						}
					}
					$res = \CIBlockElement::GetList(Array('ID' => 'ASC'), $arFilterForElem, false, false, $arSelectForElem);
					if ($arFields = $res->GetNext()) {
						if ($URLTemplatePartIsVariable) {
							$countFoundedVariableParameters++;
						}
						$this->result[str_replace('#', '', $URLTemplatePart)] = $arFields;
						$chainCurrentName = $arFields['NAME'];
					}
				}
				
				$currentPageTypeTemplate = $this->getPageTypeByURLTemplate(array_slice($this->URL_PARTS[$pageType], 0, $URLTemplatePos + 1));
				if($this->ADD_TO_CHAIN[$currentPageTypeTemplate] == 'Y') {
					if ($this->CHAIN_NAME[$currentPageTypeTemplate] !== '') {
						$chainCurrentName = $this->CHAIN_NAME[$currentPageTypeTemplate];
					}
					if ($chainCurrentName != '') {
						$this->chainParameters[$this->createURLFromArray(array_slice($this->requestURIParts, 0, $URLTemplatePos + 1))] = $chainCurrentName;
					}
				}
			}
			if ($countFoundedVariableParameters === $countVariableParameters && $countFoundedVariableParameters + $countConstantParameters == count($this->requestURIParts)) {//full match
				$this->getPageByURI($this->REAL_PATHS[$pageType]);
				$successExecution = true;
				break;
			}
		}
		if (!$successExecution) {
			$this->get404Page();
		}
	}
	
	private function get404Page()
	{
		global $APPLICATION;
		$APPLICATION->RestartBuffer();
		\CHTTP::SetStatus("404 Not Found");
		include($_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . "/header.php");
		include($_SERVER["DOCUMENT_ROOT"] . '/404.php');
		//include($_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . "/footer.php");
		die();
	}
	
	private function updateChain()
	{
		global $APPLICATION;
		foreach ($this->chainParameters as $url => $name) {
			$APPLICATION->AddChainItem($name, $url);
		}
	}
	
	private function createURLFromArray($parts)
	{
		return '/' . implode('/', $parts) . '/';
	}
	
	private function getPageTypeByURLTemplate($checkingURLParts)
	{
		foreach ($this->URL_PARTS as $pageType => $URLParts) {
			if ($checkingURLParts == $URLParts) {
				return $pageType;
			}
		}
	}
}