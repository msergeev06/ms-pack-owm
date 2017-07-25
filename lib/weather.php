<?php

namespace MSergeev\Packages\Owm\Lib;

use MSergeev\Core\Entity\Query;
use MSergeev\Core\Exception;
use MSergeev\Core\Lib\DateHelper;
use MSergeev\Core\Lib\Options;
use MSergeev\Core\Lib\Events;
use MSergeev\Core\Lib\SqlHelper;
use MSergeev\Packages\Owm\Tables;

class Weather
{
	const CONST_HPA_MM = 0.750062;

	public static function getWeather ($cityID=0, $appID=false)
	{
		if (!$appID || strlen($appID)<=0)
		{
			$appID = Options::getOptionStr('OWM_APP_ID');
			if (!$appID)
			{
				return false;
			}
		}

		if (intval($cityID)<=0)
		{
			//Получаем список городов и для каждого загружаем погоду
			$arCity = Tables\CityTable::getList(array());
		}
		else
		{
			//Загружаем погоду только для заданного города
			$arCity = Tables\CityTable::getList(array(
				'filter' => array('ID'=>$cityID)
			));
		}


		foreach ($arCity as $city)
		{
			if ($arWeather = self::getWeatherArray($city['ID'],$appID))
			{
				//Заполняем значения в таблицу рассветов и закатов
				self::parseSunRiseSet($city,$arWeather);

				//Заполняем значения forecast в базу данных
				self::parseForecast ($city,$arWeather);

				//Объединяем данные forecast по временным зонам на сегодня и завтра
				self::forecastMergeToTimezone($city);
			}
		}
	}

	public static function getMinTempDate ($cityID=0, $date=null)
	{
		if (intval($cityID)<=0)
		{
			//Получаем список городов и для каждого загружаем погоду
			$arCity = Tables\CityTable::getList(array());
		}
		else
		{
			//Загружаем погоду только для заданного города
			$arCity = Tables\CityTable::getList(
				array(
					'filter' => array('ID'=>$cityID)
				)
			);
		}

		if (is_null($date))
		{
			$date = date("d.m.Y");
		}

		$arReturn = array();
		$sqlHelper = new SqlHelper(Tables\ForecastTable::getTableName());
		$dateHelper = new DateHelper();
		foreach ($arCity as $city)
		{
			$query = new Query('select');
			$sql = "SELECT\n\t"
				.$sqlHelper->wrapFieldQuotes('CITY_ID').",\n\t"
				.$sqlHelper->getMinFunction('TEMPERATURE_MIN','TEMPERATURE_MIN')."\n"
				."FROM\n\t"
				.$sqlHelper->wrapTableQuotes()."\n"
				."WHERE\n\t"
				.$sqlHelper->wrapFieldQuotes('CITY_ID').' = '.$city['ID']." AND\n\t"
				.$sqlHelper->wrapFieldQuotes('DATETIME_FROM')." >= '".$dateHelper->convertDateToDB($date)." 00:00:00' AND\n\t"
				.$sqlHelper->wrapFieldQuotes('DATETIME_FROM')." <= '".$dateHelper->convertDateToDB($date)." 18:00:00'\n";
			$query->setQueryBuildParts($sql);
			$res = $query->exec();
			if ($ar_res = $res->fetch())
			{
				$arReturn[$ar_res['CITY_ID']] = round($ar_res['TEMPERATURE_MIN'],2);
			}
		}
		if (!empty($arReturn))
		{
			return $arReturn;
		}
		else
		{
			return false;
		}
	}

	public static function getMaxTempDate ($cityID=0, $date=null)
	{
		if (intval($cityID)<=0)
		{
			//Получаем список городов и для каждого загружаем погоду
			$arCity = Tables\CityTable::getList(array());
		}
		else
		{
			//Загружаем погоду только для заданного города
			$arCity = Tables\CityTable::getList(
				array(
					'filter' => array('ID'=>$cityID)
				)
			);
		}

		if (is_null($date))
		{
			$date = date("d.m.Y");
		}

		$arReturn = array();
		$sqlHelper = new SqlHelper(Tables\ForecastTable::getTableName());
		$dateHelper = new DateHelper();
		foreach ($arCity as $city)
		{
			$query = new Query('select');
			$sql = "SELECT\n\t"
				.$sqlHelper->wrapFieldQuotes('CITY_ID').",\n\t"
				.$sqlHelper->getMaxFunction('TEMPERATURE_MAX','TEMPERATURE_MAX')."\n"
				."FROM\n\t"
				.$sqlHelper->wrapTableQuotes()."\n"
				."WHERE\n\t"
				.$sqlHelper->wrapFieldQuotes('CITY_ID').' = '.$city['ID']." AND\n\t"
				.$sqlHelper->wrapFieldQuotes('DATETIME_FROM')." >= '".$dateHelper->convertDateToDB($date)." 00:00:00' AND\n\t"
				.$sqlHelper->wrapFieldQuotes('DATETIME_FROM')." <= '".$dateHelper->convertDateToDB($date)." 18:00:00'\n";
			$query->setQueryBuildParts($sql);
			$res = $query->exec();
			if ($ar_res = $res->fetch())
			{
				$arReturn[$ar_res['CITY_ID']] = round($ar_res['TEMPERATURE_MAX'],2);
			}
		}
		if (!empty($arReturn))
		{
			return $arReturn;
		}
		else
		{
			return false;
		}
	}

	public static function getAvgTempDate ($cityID=0, $date=null)
	{
		if (intval($cityID)<=0)
		{
			//Получаем список городов и для каждого загружаем погоду
			$arCity = Tables\CityTable::getList(array());
		}
		else
		{
			//Загружаем погоду только для заданного города
			$arCity = Tables\CityTable::getList(array(
				'filter' => array('ID'=>$cityID)
			));
		}

		if (is_null($date))
		{
			$date = date("d.m.Y");
		}

		$arReturn = array();
		foreach ($arCity as $city)
		{
			$minTemp = self::getMinTempDate($city['ID'],$date)[$city['ID']];
			$maxTemp = self::getMaxTempDate($city['ID'],$date)[$city['ID']];
			if ($minTemp && $maxTemp)
			{
				$arReturn[$city['ID']] = round((($minTemp+$maxTemp)/2),2);
			}
		}
		if (!empty($arReturn))
		{
			return $arReturn;
		}
		else
		{
			return false;
		}
	}

	protected static function forecastMergeToTimezone ($city)
	{
		$dateHelper = new DateHelper();
		$todayDate = date("d.m.Y");
		$tomorrowDate = $dateHelper->strToTime($todayDate);

		//Заполняем сегодняшние данные
		self::forecastMergeToTimezoneToDate($city,$todayDate);//$dateHelper->convertDateToDB($todayDate));

		//Заполняем завтрашние данные
		self::forecastMergeToTimezoneToDate($city,$tomorrowDate);//$dateHelper->convertDateToDB($tomorrowDate));

	}

	protected static function forecastMergeToTimezoneToDate ($city, $toDate)
	{
		$dateHelper = new DateHelper();

		$forecastRes = Tables\ForecastTable::getList(array(
			'filter' => array(
				'CITY_ID' => $city['ID'],
				'>=DATETIME_FROM' => $toDate.' 00:00:00',
				'<=DATETIME_FROM' => $toDate.' 23:59:59'
			),
			'order' => array('DATETIME_FROM'=>'ASC')
		));
		//msEchoVar($forecastRes['SQL']);
		//msDebug($forecastRes);

		$arTemp = $forecastRes;
		$forecastRes = array();
		foreach ($arTemp as $ar_temp)
		{
			$key = '';
			list($date,$time) = explode(' ',$ar_temp['DATETIME_FROM']);
			$time = explode(':',$time);
			$time[0] = intval($time[0]);
			$key .= $time[0].'_';
			list($date,$time) = explode(' ',$ar_temp['DATETIME_TO']);
			$time = explode(':',$time);
			$time[0] = intval($time[0]);
			$key .= $time[0];
			$forecastRes[$key] = $ar_temp;
		}

		for ($i=0; $i<24; $i+=6)
		{
			$i3 = $i + 3;
			$i6 = $i + 6;
			if ($i6 == 24) $i6 = 0;

			if (isset($forecastRes[$i.'_'.$i3]) && isset($forecastRes[$i3.'_'.$i6]))
			{
				$forecastFirst = $forecastRes[$i.'_'.$i3];
				$forecastSecond = $forecastRes[$i3.'_'.$i6];
			}
			elseif (isset($forecastRes[$i.'_'.$i3]) && !isset($forecastRes[$i3.'_'.$i6]))
			{
				$forecastFirst = $forecastSecond = $forecastRes[$i.'_'.$i3];
			}
			elseif (!isset($forecastRes[$i.'_'.$i3]) && isset($forecastRes[$i3.'_'.$i6]))
			{
				$forecastFirst = $forecastSecond = $forecastRes[$i3.'_'.$i6];
			}
			else
			{
				$forecastFirst = $forecastSecond = false;
			}


			if ($forecastFirst)
			{
				$arUpdateInsert = array('CITY_ID'=>$city['ID']);

				//SYMBOL_VAR
				if (intval($forecastFirst['SYMBOL_VAR'])>intval($forecastSecond['SYMBOL_VAR']))
				{
					$arUpdateInsert['SYMBOL_VAR'] = $forecastFirst['SYMBOL_VAR'];
				}
				else
				{
					$arUpdateInsert['SYMBOL_VAR'] = $forecastSecond['SYMBOL_VAR'];
				}

				//WIND_DIRECTION_DEG //WIND_DIRECTION_CODE //WIND_SPEED_MPS //WIND_SPEED_NAME
				if ($forecastFirst['WIND_SPEED_MPS'] > $forecastSecond['WIND_SPEED_MPS'])
				{
					$arUpdateInsert['WIND_DIRECTION_DEG'] = $forecastFirst['WIND_DIRECTION_DEG'];
					$arUpdateInsert['WIND_DIRECTION_CODE'] = $forecastFirst['WIND_DIRECTION_CODE'];
					$arUpdateInsert['WIND_SPEED_MPS'] = $forecastFirst['WIND_SPEED_MPS'];
					$arUpdateInsert['WIND_SPEED_NAME'] = $forecastFirst['WIND_SPEED_NAME'];
				}
				else
				{
					$arUpdateInsert['WIND_DIRECTION_DEG'] = $forecastSecond['WIND_DIRECTION_DEG'];
					$arUpdateInsert['WIND_DIRECTION_CODE'] = $forecastSecond['WIND_DIRECTION_CODE'];
					$arUpdateInsert['WIND_SPEED_MPS'] = $forecastSecond['WIND_SPEED_MPS'];
					$arUpdateInsert['WIND_SPEED_NAME'] = $forecastSecond['WIND_SPEED_NAME'];
				}

				//TEMPERATURE_MIN
				if ($forecastFirst['TEMPERATURE_MIN'] < $forecastSecond['TEMPERATURE_MIN'])
				{
					$arUpdateInsert['TEMPERATURE_MIN'] = $forecastFirst['TEMPERATURE_MIN'];
				}
				else
				{
					$arUpdateInsert['TEMPERATURE_MIN'] = $forecastSecond['TEMPERATURE_MIN'];
				}

				//TEMPERATURE_MAX
				if ($forecastFirst['TEMPERATURE_MAX'] > $forecastSecond['TEMPERATURE_MAX'])
				{
					$arUpdateInsert['TEMPERATURE_MAX'] = $forecastFirst['TEMPERATURE_MAX'];
				}
				else
				{
					$arUpdateInsert['TEMPERATURE_MAX'] = $forecastSecond['TEMPERATURE_MAX'];
				}

				//PRESSURE_MM_VALUE
				if ($forecastFirst['PRESSURE_MM_VALUE'] > $forecastSecond['PRESSURE_MM_VALUE'])
				{
					$arUpdateInsert['PRESSURE_MM_VALUE'] = $forecastFirst['PRESSURE_MM_VALUE'];
				}
				else
				{
					$arUpdateInsert['PRESSURE_MM_VALUE'] = $forecastSecond['PRESSURE_MM_VALUE'];
				}

				//HUMIDITY_VALUE
				if ($forecastFirst['HUMIDITY_VALUE'] > $forecastSecond['HUMIDITY_VALUE'])
				{
					$arUpdateInsert['HUMIDITY_VALUE'] = $forecastFirst['HUMIDITY_VALUE'];
				}
				else
				{
					$arUpdateInsert['HUMIDITY_VALUE'] = $forecastSecond['HUMIDITY_VALUE'];
				}

				$arUpdateInsert['DATETIME_FROM'] = $toDate.' '.$i.':00:00';
				if ($i6 == 0)
				{
					$nextToDate = $dateHelper->strToTime($toDate);
					$arUpdateInsert['DATETIME_TO'] = $nextToDate.' '.$i6.':00:00';
				}
				else
				{
					$arUpdateInsert['DATETIME_TO'] = $toDate.' '.$i6.':00:00';
				}

				$checkIsset = Tables\TimezoneTable::getList(array(
					'select' => array('ID'),
					'filter' => array(
						'CITY_ID' => $city['ID'],
						'DATETIME_FROM' => $arUpdateInsert['DATETIME_FROM'],
						'DATETIME_TO' => $arUpdateInsert['DATETIME_TO']
					),
					'limit' => 1
				));
				if ($checkIsset && isset($checkIsset[0]))
				{
					$checkIsset = $checkIsset[0];
				}
				if ($checkIsset)
				{
					//Update
					unset($arUpdateInsert['DATETIME_FROM']);
					unset($arUpdateInsert['DATETIME_TO']);
					$query = new Query('update');
					$query->setUpdateParams(
						$arUpdateInsert,
						$checkIsset['ID'],
						Tables\TimezoneTable::getTableName(),
						Tables\TimezoneTable::getMapArray()
					);
					$query->exec();
				}
				else
				{
					//Insert
					$query = new Query('insert');
					$query->setInsertParams(
						$arUpdateInsert,
						Tables\TimezoneTable::getTableName(),
						Tables\TimezoneTable::getMapArray()
					);
					$query->exec();
				}
			}
		}
		//msDebug($forecastRes);
	}

	protected static function parseForecast ($arCity, $arWeather)
	{
		$dateHelper = new DateHelper();

		foreach ($arWeather['forecast']['time'] as $arForecast)
		{
			$arInsertUpdate = array('CITY_ID'=>$arCity['ID']);

			list($date, $time) = explode('T',$arForecast['@attributes']['from']);
			$date = $dateHelper->convertDateFromDB($date);
			$arTime = explode(':',$time);
			$arTime[0] += $arCity['UTC_HOUR'];
			if ($arTime[0]>23)
			{
				$arTime[0] -= 24;
				$date = $dateHelper->strToTime($date);
			}
			$dateFrom = $date.' '.$arTime[0].':'.$arTime[1].':'.$arTime[2];

			list($date, $time) = explode('T',$arForecast['@attributes']['to']);
			$date = $dateHelper->convertDateFromDB($date);
			$arTime = explode(':',$time);
			$arTime[0] += $arCity['UTC_HOUR'];
			if ($arTime[0]>23)
			{
				$arTime[0] -= 24;
				$date = $dateHelper->strToTime($date);
			}
			$dateTo = $date.' '.$arTime[0].':'.$arTime[1].':'.$arTime[2];
			if (isset($arForecast['symbol']))
			{
				$arInsertUpdate['SYMBOL_NUMBER'] = $arForecast['symbol']['@attributes']['number'];
				//$arInsertUpdate['SYMBOL_NAME'] = iconv('windows-1251','utf-8',$arForecast['symbol']['@attributes']['name']);
				$arInsertUpdate['SYMBOL_NAME'] = $arForecast['symbol']['@attributes']['name'];
				$arInsertUpdate['SYMBOL_VAR'] = $arForecast['symbol']['@attributes']['var'];
			}
			if (isset($arForecast['precipitation']))
			{
				$arInsertUpdate['PRECIPITATION_UNIT'] = $arForecast['precipitation']['@attributes']['unit'];
				$arInsertUpdate['PRECIPITATION_VALUE'] = $arForecast['precipitation']['@attributes']['value'];
				$arInsertUpdate['PRECIPITATION_TYPE'] = $arForecast['precipitation']['@attributes']['type'];
			}
			if (isset($arForecast['windDirection']))
			{
				$arInsertUpdate['WIND_DIRECTION_DEG'] = $arForecast['windDirection']['@attributes']['deg'];
				$arInsertUpdate['WIND_DIRECTION_CODE'] = $arForecast['windDirection']['@attributes']['code'];
				$arInsertUpdate['WIND_DIRECTION_NAME'] = $arForecast['windDirection']['@attributes']['name'];
			}
			if (isset($arForecast['windSpeed']))
			{
				$arInsertUpdate['WIND_SPEED_MPS'] = $arForecast['windSpeed']['@attributes']['mps'];
				$arInsertUpdate['WIND_SPEED_NAME'] = $arForecast['windSpeed']['@attributes']['name'];
			}
			if (isset($arForecast['temperature']))
			{
				$arInsertUpdate['TEMPERATURE_MIN'] = $arForecast['temperature']['@attributes']['min'];
				$arInsertUpdate['TEMPERATURE_MAX'] = $arForecast['temperature']['@attributes']['max'];
			}
			if (isset($arForecast['pressure']))
			{
				$arInsertUpdate['PRESSURE_HPA_VALUE'] = $arForecast['pressure']['@attributes']['value'];
				$arInsertUpdate['PRESSURE_MM_VALUE'] = $arInsertUpdate['PRESSURE_HPA_VALUE'] * self::CONST_HPA_MM;
			}
			if (isset($arForecast['humidity']))
			{
				$arInsertUpdate['HUMIDITY_VALUE'] = $arForecast['humidity']['@attributes']['value'];
			}
			if (isset($arForecast['clouds']))
			{
				$arInsertUpdate['CLOUDS_VALUE'] = $arForecast['clouds']['@attributes']['value'];
				$arInsertUpdate['CLOUDS_ALL'] = $arForecast['clouds']['@attributes']['all'];
			}

			$forecastRes = Tables\ForecastTable::getList(array(
				'select' => array('ID'),
				'filter' => array(
					'CITY_ID' => $arCity['ID'],
					'DATETIME_FROM' => $dateFrom,
					'DATETIME_TO' => $dateTo
				)
			));
			if ($forecastRes && isset($forecastRes[0]))
			{
				$forecastRes = $forecastRes[0];
			}
			if ($forecastRes)
			{
				//Update
				$query = new Query('update');
				$query->setUpdateParams(
					$arInsertUpdate,
					$forecastRes['ID'],
					Tables\ForecastTable::getTableName(),
					Tables\ForecastTable::getMapArray()
				);
				$query->exec();

				$arInsertUpdate['DATETIME_FROM'] = $dateFrom;
				$arInsertUpdate['DATETIME_TO'] = $dateTo;

/*				if ($arEvents = Events::getPackageEvents('owm','OnAfterUpdateForecast'))
				{
					foreach ($arEvents as $sort=>$ar_events)
					{
						foreach ($ar_events as $arEvent)
						{
							Events::executePackageEvent($arEvent,array($arInsertUpdate));
						}
					}
				}*/
				Events::runEvents('owm','OnAfterUpdateForecast',array($arInsertUpdate));
			}
			else
			{
				//Insert
				$arInsertUpdate['DATETIME_FROM'] = $dateFrom;
				$arInsertUpdate['DATETIME_TO'] = $dateTo;

				$query = new Query('insert');
				$query->setInsertParams(
					$arInsertUpdate,
					Tables\ForecastTable::getTableName(),
					Tables\ForecastTable::getMapArray()
				);
				$query->exec();

/*				if ($arEvents = Events::getPackageEvents('owm','OnAfterInsertForecast'))
				{
					foreach ($arEvents as $sort=>$ar_events)
					{
						foreach ($ar_events as $arEvent)
						{
							Events::executePackageEvent($arEvent,array($arInsertUpdate));
						}
					}
				}*/
				Events::runEvents('owm','OnAfterInsertForecast',array($arInsertUpdate));
			}
		}
	}

	protected static function parseSunRiseSet ($city, $arWeather)
	{
		$dateHelper = new DateHelper();

		list($sunRiseDate,$sunRiseTime) = explode('T',$arWeather['sun']['@attributes']['rise']);
		$sunRiseDate = $dateHelper->convertDateFromDB($sunRiseDate);
		$sunRiseTime = explode(':',$sunRiseTime);
		$sunRiseTime[0] += $city['UTC_HOUR'];
		if ($sunRiseTime[0]>23)
		{
			$sunRiseTime[0] -= 24;
			$sunRiseDate = $dateHelper->strToTime($sunRiseDate);
		}
		list($sunSetDate,$sunSetTime) = explode('T',$arWeather['sun']['@attributes']['set']);
		unset($sunSetDate);
		$sunSetTime = explode(':',$sunSetTime);
		$sunSetTime[0] += $city['UTC_HOUR'];
		if ($sunSetTime[0]>23)
		{
			$sunSetTime[0] -= 24;
		}
		$sunRiseMin = $sunRiseTime[0] * 60 + $sunRiseTime[1];
		$sunSetMin = $sunSetTime[0] * 60 + $sunSetTime[1];
		if($sunSetMin>=$sunRiseMin)
		{
			$sunDayMin = $sunSetMin - $sunRiseMin;
		}
		else
		{
			$sunDayMin = ($sunSetMin + 24 * 60) - $sunRiseMin;
		}
		$sunDayHour = floor($sunDayMin/60);
		$sunDayMin -= $sunDayHour*60;

		$arSunRise = Tables\SunTable::getList(array(
			'filter' => array(
				'CITY_ID' => $city['ID'],
				'DATE' => $sunRiseDate
			),
			'limit' => 1
		));
		if ($arSunRise && isset($arSunRise[0]))
		{
			$arSunRise = $arSunRise[0];
		}
		$arInsertUpdate = array(
			'CITY_ID' => $city['ID'],
			'DATE' => $sunRiseDate,
			'SUNRISE' => $sunRiseTime[0].':'.$sunRiseTime[1].':'.$sunRiseTime[2],
			'SUNSET' => $sunSetTime[0].':'.$sunSetTime[1].':'.$sunSetTime[2],
			'DAYTIME' => $sunDayHour.':'.$sunDayMin.':00'
		);

		if ($arSunRise)
		{
			//Update
			$query = new Query('update');
			$query->setUpdateParams(
				$arInsertUpdate,
				$arSunRise['ID'],
				Tables\SunTable::getTableName(),
				Tables\SunTable::getMapArray()
			);
			$res = $query->exec();
			if ($res->getResult())
			{
				return $res->getInsertId();
			}
			else
			{
				return false;
			}
		}
		else
		{
			//Insert
			$query = new Query('insert');
			$query->setInsertParams(
				$arInsertUpdate,
				Tables\SunTable::getTableName(),
				Tables\SunTable::getMapArray()
			);
			$res = $query->exec();
			if ($res->getResult())
			{
				return $res->getAffectedRows();
			}
			else
			{
				return false;
			}
		}
	}

	protected static function getWeatherArray ($cityID=null, $appID=false)
	{
		try
		{
			if (is_null($cityID))
			{
				throw new Exception\ArgumentNullException('cityID');
			}

			if ($cityID <= 0)
			{
				throw new Exception\ArgumentOutOfRangeException('cityID','>0');
			}
		}
		catch (Exception\ArgumentNullException $e)
		{
			$e->showException();
			return false;
		}
		catch (Exception\ArgumentOutOfRangeException $e2)
		{
			$e2->showException();
			return false;
		}

		if ($appID)
		{
			if ($weatherXML = @simplexml_load_file("http://api.openweathermap.org/data/2.5/forecast?id=".$cityID."&appid=".$appID."&mode=xml&units=metric&lang=ru"))
			{
				$json = json_encode($weatherXML);
				$arWeather = json_decode($json,TRUE);
				return $arWeather;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}

	}
}