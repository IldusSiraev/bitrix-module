<?php

namespace bitrix_module\data;

/******
Параметры компонента:

$arComponentParameters = [
    'PARAMETERS' => [
        'FILTER_PARAMS' => [
            'NAME' => 'Параметры фильтрации (массив)',
            'PARENT' => 'DATA_SOURCE',
            'TYPE' => 'STRING',
        ],
        'SORTER_PARAMS' => [
            'NAME' => 'Параметры сортировки (массив)',
            'PARENT' => 'DATA_SOURCE',
            'TYPE' => 'STRING',
        ],
        'PAGER_PARAMS' => [
            'NAME' => 'Параметры пагинации (массив)',
            'PARENT' => 'DATA_SOURCE',
            'TYPE' => 'STRING',
        ],
    ],
];

******/

abstract class DataSetComponent extends \CBitrixComponent
{
	abstract public function getDataSet();

	abstract public function getLastUpdate();

	public function getCacheLastUpdate($cacheTime, $cacheAdditionalId, $cachePath)
	{
		$cache = \Bitrix\Main\Data\Cache::createInstance();
		$cacheId = $this->getCacheID($cacheAdditionalId).'_lastUpdate';
		if ($cache->initCache($cacheTime, $cacheId, $cachePath)) {
			$vars = $cache->getVars();
			return $vars['lastUpdate'] ?? null;
		}
		return null;
	}

	public function setCacheLastUpdate($cacheTime, $cacheAdditionalId, $cachePath, $value)
	{
		$cache = \Bitrix\Main\Data\Cache::createInstance();
		$cacheId = $this->getCacheID($cacheAdditionalId).'_lastUpdate';
		$cache->clean($cacheId, $cachePath);
		$cache->startDataCache($cacheTime, $cacheId, $cachePath, [
			'lastUpdate' => $value,
		]);
		$cache->endDataCache();
	}

	private function getInternalLastUpdate($cacheTime, $cacheAdditionalId, $cachePath)
	{
		$cache = \Bitrix\Main\Data\Cache::createInstance();
		$cacheId = $this->getCacheID($cacheAdditionalId).'_lastUpdateReal';

		if ($cache->initCache($cacheTime, $cacheId, $cachePath)) {
			$lastUpdate = $cache->getVars()['lastUpdate'] ?? null;
		}
		else if ($cache->startDataCache($cacheTime, $cacheId, $cachePath)) {
			$lastUpdate = $this->getLastUpdate();
			$cache->endDataCache([
				'lastUpdate' => $lastUpdate,
			]);
		}
		return $lastUpdate;
	}

	public function executeComponent()
	{
		$isCaching = $this->arParams['CACHE_TYPE'] !== 'N';
		if ($isCaching === false) {
			$this->run();
		}
		else {
			$cacheTime = $this->arParams['CACHE_TIME'] ?? 360000000;
			$cacheAdditionalId = null;
			$cachePath = preg_replace('/[^a-z0-9]/i', '_', get_class($this));

			$realLastUpdate = $this->getInternalLastUpdate(60, $cacheAdditionalId, $cachePath);
			$cacheLastUpdate = $this->getCacheLastUpdate($cacheTime, $cacheAdditionalId, $cachePath);
			if ($realLastUpdate !== $cacheLastUpdate) {
				$this->clearResultCache($cacheAdditionalId, $cachePath);
			}

			if ($this->startResultCache($cacheTime, $cacheAdditionalId, $cachePath)) {
				$this->run();
				$this->endResultCache();
				$this->setCacheLastUpdate($cacheTime, $cacheAdditionalId, $cachePath, $realLastUpdate);
			}
		}
	}

	public function run()
	{
		\CModule::includeModule('iblock');

		$dataSet = $this->getDataSet();
		$filterRequest = $this->getFilterRequest();
		if ($filterRequest) {
			$filterRequest->load($_GET);
			$dataSet->setFilter($filterRequest->filter);
		}

		$sorterRequest = $this->getSorterRequest();
		if ($sorterRequest) {
			$sorterRequest->load($_GET);
			$dataSet->setSorter($sorterRequest->sorter);
		}

		$pagerRequest = $this->getPagerRequest(
			$dataSet->getTotalCount()
		);
		if ($pagerRequest) {
			$pagerRequest->load($_GET);
			$dataSet->setPager($pagerRequest->pager);
		}

		$this->arResult['DATA_SET'] = $dataSet;
		$this->arResult['PAGER_REQUEST'] = $pagerRequest;
		$this->arResult['SORTER_REQUEST'] = $sorterRequest;
		$this->arResult['FILTER_REQUEST'] = $filterRequest;
		$this->includeComponentTemplate();
	}

	public function getFilterRequest()
	{
		$params = $this->arParams['FILTER_PARAMS'] ?? null;
		if (!$params) {
			return null;
		}
		return FilterRequestBuildByComponentParams::runStatic($params);
	}

	public function getSorterRequest()
	{
		$params = $this->arParams['SORTER_PARAMS'] ?? null;
		if (!$params) {
			return null;
		}
		return SorterRequestBuildByComponentParams::runStatic($params);
	}

	public function getPagerRequest($totalCount)
	{
		$params = $this->arParams['PAGER_PARAMS'] ?? null;
		if (!$params) {
			return null;
		}
		if (!isset($params['PAGER_REQUEST'])) {
			$params['TOTAL_COUNT'] = $totalCount;
		}
		return PagerRequestBuildByComponentParams::runStatic($params);
	}
}
