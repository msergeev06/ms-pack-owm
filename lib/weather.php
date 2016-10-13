<?php

namespace MSergeev\Packages\Owm\Lib;

use MSergeev\Core\Entity\Query;
use MSergeev\Core\Exception;
use MSergeev\Core\Lib\DateHelper;
use MSergeev\Core\Lib\Options;
use MSergeev\Packages\Owm\Tables\CityTable;
use MSergeev\Packages\Owm\Tables\ForecastTable;
use MSergeev\Packages\Owm\Tables\SunTable;
use MSergeev\Packages\Owm\Tables\TimezoneTable;

class Weather
{
	const CONST_HPA_MM = 0.750062;

	public static function getWeather ($cityID=0)
	{
		$arCity = array();
		if ($cityID<=0)
		{
			//Получаем список городов и для каждого загружаем погоду
			$arCity = CityTable::getList(array());
		}
		else
		{
			//Загружаем погоду только для заданного города
			$arCity = CityTable::getList(array(
				'filter' => array('ID'=>$cityID)
			));
		}


		foreach ($arCity as $city)
		{
			if ($arWeather = self::getWeatherArray($city['ID']))
			{
				msDebug($arWeather);

				//Заполняем значения в таблицу рассветов и закатов
				//self::parseSunRiseSet($city,$arWeather);

				//Заполняем значения forecast в базу данных
				//self::parseForecast ($city,$arWeather);

				//Объединяем данные forecast по временным зонам на сегодня и завтра
				self::forecastMergeToTimezone($city);
			}
		}
	}

	protected static function forecastMergeToTimezone ($city)
	{
		$dateHelper = new DateHelper();
		$todayDate = date("d.m.Y");
		$tomorrowDate = $dateHelper->strToTime($todayDate);

		//Заполняем сегодняшние данные
		self::forecastMergeToTimezoneToDate($city,$todayDate);

		//Заполняем завтрашние данные
		self::forecastMergeToTimezoneToDate($city,$tomorrowDate);

	}

	protected static function forecastMergeToTimezoneToDate ($city, $toDate)
	{
		$dateHelper = new DateHelper();

		$forecastRes = ForecastTable::getList(array(
			'filter' => array(
				'CITY_ID' => $city['ID'],
				'>=DATETIME_FROM' => $toDate.' 00:00:00',
				'<=DATETIME_FROM' => $toDate.' 23:59:59'
			)
		));
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
		msDebug($forecastRes);
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
/*				if ($i>=0 && $i<=9)
				{
					$hourFrom = '0'.$i;
				}
				else
				{
					$hourFrom = $i;
				}
				if ($i6>=0 && $i6<=9)
				{
					$hourTo = '0'.$i6;
				}
				else
				{
					$hourTo = $i6;
				}*/
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

				$checkIsset = TimezoneTable::getList(array(
					'select' => array('ID'),
					'filter' => array(
						'CITY_ID' => $city['ID'],
						'DATETIME_FROM' => $arUpdateInsert['DATETIME_FROM'],
						'DATETIME_TO' => $arUpdateInsert['DATETIME_TO']
					)
				));
				if ($checkIsset)
				{
					//Update
					unset($arUpdateInsert['DATETIME_FROM']);
					unset($arUpdateInsert['DATETIME_TO']);
					$query = new Query('update');
					$query->setUpdateParams(
						$arUpdateInsert,
						$checkIsset[0]['ID'],
						TimezoneTable::getTableName(),
						TimezoneTable::getMapArray()
					);
					$query->exec();
				}
				else
				{
					//Insert
					$query = new Query('insert');
					$query->setInsertParams(
						$arUpdateInsert,
						TimezoneTable::getTableName(),
						TimezoneTable::getMapArray()
					);
					$query->exec();
				}

				//msDebug($arUpdateInsert);

			}


		}
	}

	protected static function parseForecast ($city, $arWeather)
	{
		$dateHelper = new DateHelper();

		//msDebug($city['UTC_HOUR']);
		foreach ($arWeather['forecast']['time'] as $arForecast)
		{
			$arInsertUpdate = array('CITY_ID'=>$city['ID']);

			//msDebug($arForecast['@attributes']['from']);
			list($date, $time) = explode('T',$arForecast['@attributes']['from']);
			$date = $dateHelper->convertDateFromDB($date);
			$arTime = explode(':',$time);
			$arTime[0] += $city['UTC_HOUR'];
			if ($arTime[0]>23)
			{
				$arTime[0] -= 24;
				$date = $dateHelper->strToTime($date);
			}
			$dateFrom = $date.' '.$arTime[0].':'.$arTime[1].':'.$arTime[2];
			//msDebug($dateFrom);
			list($date, $time) = explode('T',$arForecast['@attributes']['to']);
			//msDebug($arForecast['@attributes']['to']);
			$date = $dateHelper->convertDateFromDB($date);
			$arTime = explode(':',$time);
			$arTime[0] += $city['UTC_HOUR'];
			if ($arTime[0]>23)
			{
				$arTime[0] -= 24;
				$date = $dateHelper->strToTime($date);
			}
			$dateTo = $date.' '.$arTime[0].':'.$arTime[1].':'.$arTime[2];
			//msDebug($dateTo);
			//msDebug('-------------');
			if (isset($arForecast['symbol']))
			{
				$arInsertUpdate['SYMBOL_NUMBER'] = $arForecast['symbol']['@attributes']['number'];
				$arInsertUpdate['SYMBOL_NAME'] = iconv('windows-1251','utf-8',$arForecast['symbol']['@attributes']['name']);
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



			$forecastRes = ForecastTable::getList(array(
				'select' => array('ID'),
				'filter' => array(
					'CITY_ID' => $city['ID'],
					'DATETIME_FROM' => $dateFrom,
					'DATETIME_TO' => $dateTo
				)
			));
			if ($forecastRes)
			{
				//Update
				$query = new Query('update');
				$query->setUpdateParams(
					$arInsertUpdate,
					$forecastRes[0]['ID'],
					ForecastTable::getTableName(),
					ForecastTable::getMapArray()
				);
				$query->exec();
			}
			else
			{
				//Insert
				$arInsertUpdate['DATETIME_FROM'] = $dateFrom;
				$arInsertUpdate['DATETIME_TO'] = $dateTo;

				$query = new Query('insert');
				$query->setInsertParams(
					$arInsertUpdate,
					ForecastTable::getTableName(),
					ForecastTable::getMapArray()
				);
				$query->exec();
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
		$sunSetDate = $dateHelper->convertDateFromDB($sunSetDate);
		$sunSetTime = explode(':',$sunSetTime);
		$sunSetTime[0] += $city['UTC_HOUR'];
		if ($sunSetTime[0]>23)
		{
			$sunSetTime[0] -= 24;
			$sunSetDate = $dateHelper->strToTime($sunSetDate);
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

		$arSunRise = SunTable::getList(array(
			'filter' => array(
				'CITY_ID' => $city['ID'],
				'DATE' => $sunRiseDate
			)
		));
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
				$arSunRise[0]['ID'],
				SunTable::getTableName(),
				SunTable::getMapArray()
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
				SunTable::getTableName(),
				SunTable::getMapArray()
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

	protected static function getWeatherArray ($cityID=null)
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

		$appID = Options::getOptionStr('OWM_APP_ID');

		//TODO: В рабочей версии восстановить
		//if ($weatherXML = simplexml_load_file("http://api.openweathermap.org/data/2.5/forecast?id=".$cityID."&appid=".$appID."&mode=xml&units=metric&lang=ru"))
		if ($weatherXML = simplexml_load_string('<weatherdata><location><name>Moscow</name><type></type><country>RU</country><timezone></timezone><location altitude="0" latitude="55.75222" longitude="37.615555" geobase="geonames" geobaseid="0"></location></location><credit></credit><meta><lastupdate></lastupdate><calctime>0.0056</calctime><nextupdate></nextupdate></meta><sun rise="2016-10-04T03:39:56" set="2016-10-04T14:54:57"></sun><forecast><time from="2016-10-04T12:00:00" to="2016-10-04T15:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.055" type="rain"></precipitation><windDirection deg="11.0186" code="N" name="North"></windDirection><windSpeed mps="3.46" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="14.84" min="9.71" max="14.84"></temperature><pressure unit="hPa" value="1011.28"></pressure><humidity value="96" unit="%"></humidity><clouds value="broken clouds" all="80" unit="%"></clouds></time><time from="2016-10-04T15:00:00" to="2016-10-04T18:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.26" type="rain"></precipitation><windDirection deg="29.5031" code="NNE" name="North-northeast"></windDirection><windSpeed mps="3.21" name="Light breeze"></windSpeed><temperature unit="celsius" value="12.79" min="8.94" max="12.79"></temperature><pressure unit="hPa" value="1011.89"></pressure><humidity value="96" unit="%"></humidity><clouds value="broken clouds" all="76" unit="%"></clouds></time><time from="2016-10-04T18:00:00" to="2016-10-04T21:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.17" type="rain"></precipitation><windDirection deg="28.0061" code="NNE" name="North-northeast"></windDirection><windSpeed mps="3.58" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="11.2" min="8.63" max="11.2"></temperature><pressure unit="hPa" value="1011.58"></pressure><humidity value="96" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-04T21:00:00" to="2016-10-05T00:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="1.215" type="rain"></precipitation><windDirection deg="47.5038" code="NE" name="NorthEast"></windDirection><windSpeed mps="3.46" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="11.16" min="9.88" max="11.16"></temperature><pressure unit="hPa" value="1011.72"></pressure><humidity value="96" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-05T00:00:00" to="2016-10-05T03:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.405" type="rain"></precipitation><windDirection deg="58.0037" code="ENE" name="East-northeast"></windDirection><windSpeed mps="3.61" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="11.27" min="11.27" max="11.27"></temperature><pressure unit="hPa" value="1012.59"></pressure><humidity value="99" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-05T03:00:00" to="2016-10-05T06:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.025" type="rain"></precipitation><windDirection deg="62.5028" code="ENE" name="East-northeast"></windDirection><windSpeed mps="4.02" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="12.14" min="12.14" max="12.14"></temperature><pressure unit="hPa" value="1013.47"></pressure><humidity value="99" unit="%"></humidity><clouds value="broken clouds" all="56" unit="%"></clouds></time><time from="2016-10-05T06:00:00" to="2016-10-05T09:00:00"><symbol number="802" name="слегка облачно" var="03d"></symbol><precipitation></precipitation><windDirection deg="68.0068" code="ENE" name="East-northeast"></windDirection><windSpeed mps="5.1" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="14.15" min="14.15" max="14.15"></temperature><pressure unit="hPa" value="1014.51"></pressure><humidity value="95" unit="%"></humidity><clouds value="scattered clouds" all="32" unit="%"></clouds></time><time from="2016-10-05T09:00:00" to="2016-10-05T12:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.04" type="rain"></precipitation><windDirection deg="66.5046" code="ENE" name="East-northeast"></windDirection><windSpeed mps="5.67" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="15" min="15" max="15"></temperature><pressure unit="hPa" value="1015.35"></pressure><humidity value="87" unit="%"></humidity><clouds value="broken clouds" all="64" unit="%"></clouds></time><time from="2016-10-05T12:00:00" to="2016-10-05T15:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.05" type="rain"></precipitation><windDirection deg="58.5007" code="ENE" name="East-northeast"></windDirection><windSpeed mps="6.33" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="13.36" min="13.36" max="13.36"></temperature><pressure unit="hPa" value="1016.53"></pressure><humidity value="83" unit="%"></humidity><clouds value="overcast clouds" all="88" unit="%"></clouds></time><time from="2016-10-05T15:00:00" to="2016-10-05T18:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.21" type="rain"></precipitation><windDirection deg="59.5087" code="ENE" name="East-northeast"></windDirection><windSpeed mps="6.53" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="11.56" min="11.56" max="11.56"></temperature><pressure unit="hPa" value="1017.88"></pressure><humidity value="87" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-05T18:00:00" to="2016-10-05T21:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.28" type="rain"></precipitation><windDirection deg="59.0015" code="ENE" name="East-northeast"></windDirection><windSpeed mps="6.38" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="10.08" min="10.08" max="10.08"></temperature><pressure unit="hPa" value="1019.14"></pressure><humidity value="89" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-05T21:00:00" to="2016-10-06T00:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.21" type="rain"></precipitation><windDirection deg="58.0012" code="ENE" name="East-northeast"></windDirection><windSpeed mps="6.36" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="8.95" min="8.95" max="8.95"></temperature><pressure unit="hPa" value="1020.09"></pressure><humidity value="92" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-06T00:00:00" to="2016-10-06T03:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.19" type="rain"></precipitation><windDirection deg="61.002" code="ENE" name="East-northeast"></windDirection><windSpeed mps="6.24" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="8.05" min="8.05" max="8.05"></temperature><pressure unit="hPa" value="1020.95"></pressure><humidity value="91" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-06T03:00:00" to="2016-10-06T06:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.08" type="rain"></precipitation><windDirection deg="65.5018" code="ENE" name="East-northeast"></windDirection><windSpeed mps="6.41" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="7.78" min="7.78" max="7.78"></temperature><pressure unit="hPa" value="1021.36"></pressure><humidity value="88" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-06T06:00:00" to="2016-10-06T09:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.09" type="rain"></precipitation><windDirection deg="66.5026" code="ENE" name="East-northeast"></windDirection><windSpeed mps="6.18" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="8.37" min="8.37" max="8.37"></temperature><pressure unit="hPa" value="1021.75"></pressure><humidity value="84" unit="%"></humidity><clouds value="broken clouds" all="80" unit="%"></clouds></time><time from="2016-10-06T09:00:00" to="2016-10-06T12:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.05" type="rain"></precipitation><windDirection deg="65.002" code="ENE" name="East-northeast"></windDirection><windSpeed mps="6.31" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="8.68" min="8.68" max="8.68"></temperature><pressure unit="hPa" value="1021.11"></pressure><humidity value="80" unit="%"></humidity><clouds value="broken clouds" all="80" unit="%"></clouds></time><time from="2016-10-06T12:00:00" to="2016-10-06T15:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.03" type="rain"></precipitation><windDirection deg="64.5009" code="ENE" name="East-northeast"></windDirection><windSpeed mps="5.77" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="8.08" min="8.08" max="8.08"></temperature><pressure unit="hPa" value="1020.54"></pressure><humidity value="75" unit="%"></humidity><clouds value="overcast clouds" all="88" unit="%"></clouds></time><time from="2016-10-06T15:00:00" to="2016-10-06T18:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.05" type="rain"></precipitation><windDirection deg="66.5008" code="ENE" name="East-northeast"></windDirection><windSpeed mps="5.46" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="7.4" min="7.4" max="7.4"></temperature><pressure unit="hPa" value="1019.95"></pressure><humidity value="76" unit="%"></humidity><clouds value="overcast clouds" all="88" unit="%"></clouds></time><time from="2016-10-06T18:00:00" to="2016-10-06T21:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.03" type="rain"></precipitation><windDirection deg="69.5045" code="ENE" name="East-northeast"></windDirection><windSpeed mps="5.06" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="6.7" min="6.7" max="6.7"></temperature><pressure unit="hPa" value="1018.75"></pressure><humidity value="76" unit="%"></humidity><clouds value="overcast clouds" all="88" unit="%"></clouds></time><time from="2016-10-06T21:00:00" to="2016-10-07T00:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.02" type="rain"></precipitation><windDirection deg="69.0002" code="ENE" name="East-northeast"></windDirection><windSpeed mps="5.22" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="6.12" min="6.12" max="6.12"></temperature><pressure unit="hPa" value="1017.54"></pressure><humidity value="78" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-07T00:00:00" to="2016-10-07T03:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.03" type="rain"></precipitation><windDirection deg="68.5041" code="ENE" name="East-northeast"></windDirection><windSpeed mps="5.71" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="5.42" min="5.42" max="5.42"></temperature><pressure unit="hPa" value="1015.96"></pressure><humidity value="79" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-07T03:00:00" to="2016-10-07T06:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.1" type="rain"></precipitation><windDirection deg="68.5001" code="ENE" name="East-northeast"></windDirection><windSpeed mps="6.23" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="5.3" min="5.3" max="5.3"></temperature><pressure unit="hPa" value="1014.52"></pressure><humidity value="82" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-07T06:00:00" to="2016-10-07T09:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.11" type="rain"></precipitation><windDirection deg="69.0056" code="ENE" name="East-northeast"></windDirection><windSpeed mps="5.9" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="6.1" min="6.1" max="6.1"></temperature><pressure unit="hPa" value="1013.57"></pressure><humidity value="79" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-07T09:00:00" to="2016-10-07T12:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.45" type="rain"></precipitation><windDirection deg="68.0005" code="ENE" name="East-northeast"></windDirection><windSpeed mps="5.76" name="Moderate breeze"></windSpeed><temperature unit="celsius" value="6.38" min="6.38" max="6.38"></temperature><pressure unit="hPa" value="1012.41"></pressure><humidity value="82" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-07T12:00:00" to="2016-10-07T15:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="1.25" type="rain"></precipitation><windDirection deg="66.008" code="ENE" name="East-northeast"></windDirection><windSpeed mps="5.17" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="4.98" min="4.98" max="4.98"></temperature><pressure unit="hPa" value="1011.8"></pressure><humidity value="94" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-07T15:00:00" to="2016-10-07T18:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="1.91" type="rain"></precipitation><windDirection deg="68.5035" code="ENE" name="East-northeast"></windDirection><windSpeed mps="5.07" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="4.08" min="4.08" max="4.08"></temperature><pressure unit="hPa" value="1011.41"></pressure><humidity value="99" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-07T18:00:00" to="2016-10-07T21:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="1.67" type="rain"></precipitation><windDirection deg="70.0028" code="ENE" name="East-northeast"></windDirection><windSpeed mps="4.92" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="3.71" min="3.71" max="3.71"></temperature><pressure unit="hPa" value="1010.59"></pressure><humidity value="99" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-07T21:00:00" to="2016-10-08T00:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="1.09" type="rain"></precipitation><windDirection deg="74.5028" code="ENE" name="East-northeast"></windDirection><windSpeed mps="5.05" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="3.77" min="3.77" max="3.77"></temperature><pressure unit="hPa" value="1009.95"></pressure><humidity value="99" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-08T00:00:00" to="2016-10-08T03:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="1.72" type="rain"></precipitation><windDirection deg="82.0013" code="E" name="East"></windDirection><windSpeed mps="4.66" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="4.05" min="4.05" max="4.05"></temperature><pressure unit="hPa" value="1009.7"></pressure><humidity value="99" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-08T03:00:00" to="2016-10-08T06:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="1.05" type="rain"></precipitation><windDirection deg="88.5" code="E" name="East"></windDirection><windSpeed mps="4.28" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="4.83" min="4.83" max="4.83"></temperature><pressure unit="hPa" value="1010.07"></pressure><humidity value="100" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-08T06:00:00" to="2016-10-08T09:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.42" type="rain"></precipitation><windDirection deg="99.0016" code="E" name="East"></windDirection><windSpeed mps="3.51" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="6.29" min="6.29" max="6.29"></temperature><pressure unit="hPa" value="1010.94"></pressure><humidity value="100" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-08T09:00:00" to="2016-10-08T12:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.12" type="rain"></precipitation><windDirection deg="91.0023" code="E" name="East"></windDirection><windSpeed mps="3.11" name="Light breeze"></windSpeed><temperature unit="celsius" value="7.68" min="7.68" max="7.68"></temperature><pressure unit="hPa" value="1011.45"></pressure><humidity value="100" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-08T12:00:00" to="2016-10-08T15:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.09" type="rain"></precipitation><windDirection deg="84.5023" code="E" name="East"></windDirection><windSpeed mps="3.55" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="7.57" min="7.57" max="7.57"></temperature><pressure unit="hPa" value="1012.13"></pressure><humidity value="96" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-08T15:00:00" to="2016-10-08T18:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.28" type="rain"></precipitation><windDirection deg="87.0088" code="E" name="East"></windDirection><windSpeed mps="3.66" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="7.46" min="7.46" max="7.46"></temperature><pressure unit="hPa" value="1012.84"></pressure><humidity value="97" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-08T18:00:00" to="2016-10-08T21:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="2.39" type="rain"></precipitation><windDirection deg="90.5005" code="E" name="East"></windDirection><windSpeed mps="3.46" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="7.22" min="7.22" max="7.22"></temperature><pressure unit="hPa" value="1013.75"></pressure><humidity value="98" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-08T21:00:00" to="2016-10-09T00:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="1.13" type="rain"></precipitation><windDirection deg="73.0004" code="ENE" name="East-northeast"></windDirection><windSpeed mps="3.36" name="Gentle Breeze"></windSpeed><temperature unit="celsius" value="6.97" min="6.97" max="6.97"></temperature><pressure unit="hPa" value="1013.71"></pressure><humidity value="98" unit="%"></humidity><clouds value="broken clouds" all="68" unit="%"></clouds></time><time from="2016-10-09T00:00:00" to="2016-10-09T03:00:00"><symbol number="500" name="легкий дождь" var="10n"></symbol><precipitation unit="3h" value="0.04" type="rain"></precipitation><windDirection deg="56.5034" code="ENE" name="East-northeast"></windDirection><windSpeed mps="3.32" name="Light breeze"></windSpeed><temperature unit="celsius" value="6.44" min="6.44" max="6.44"></temperature><pressure unit="hPa" value="1013.93"></pressure><humidity value="99" unit="%"></humidity><clouds value="broken clouds" all="64" unit="%"></clouds></time><time from="2016-10-09T03:00:00" to="2016-10-09T06:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.16" type="rain"></precipitation><windDirection deg="69.0006" code="ENE" name="East-northeast"></windDirection><windSpeed mps="3.16" name="Light breeze"></windSpeed><temperature unit="celsius" value="7.95" min="7.95" max="7.95"></temperature><pressure unit="hPa" value="1014.1"></pressure><humidity value="100" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-09T06:00:00" to="2016-10-09T09:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.38" type="rain"></precipitation><windDirection deg="85.0033" code="E" name="East"></windDirection><windSpeed mps="2.5" name="Light breeze"></windSpeed><temperature unit="celsius" value="9.81" min="9.81" max="9.81"></temperature><pressure unit="hPa" value="1014.6"></pressure><humidity value="100" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time><time from="2016-10-09T09:00:00" to="2016-10-09T12:00:00"><symbol number="500" name="легкий дождь" var="10d"></symbol><precipitation unit="3h" value="0.25" type="rain"></precipitation><windDirection deg="91.0057" code="E" name="East"></windDirection><windSpeed mps="2.51" name="Light breeze"></windSpeed><temperature unit="celsius" value="10.93" min="10.93" max="10.93"></temperature><pressure unit="hPa" value="1014.65"></pressure><humidity value="100" unit="%"></humidity><clouds value="overcast clouds" all="92" unit="%"></clouds></time></forecast></weatherdata>'))
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
}