<?php

namespace MSergeev\Packages\Owm\Tables;

use MSergeev\Core\Lib\DataManager;
use MSergeev\Core\Entity;

class ForecastTable extends DataManager
{
	public static function getTableName ()
	{
		return 'ms_owm_forecast';
	}

	public static function getTableTitle ()
	{
		return 'Погода по часам';
	}

	public static function getMap ()
	{
		return array(
			new Entity\IntegerField('ID',array(
				'primary' => true,
				'autocomplete' => true,
				'title' => 'ID записи'
			)),
			new Entity\IntegerField('CITY_ID',array(
				'required' => true,
				'link' => 'ms_owm_city.ID',
				'title' => 'ID города'
			)),
			new Entity\DatetimeField('DATETIME_FROM',array(
				'required' => true,
				'title' => 'Начальное время данных'
			)),
			new Entity\DatetimeField('DATETIME_TO',array(
				'required' => true,
				'title' => 'Конечное время данных'
			)),
			new Entity\IntegerField('SYMBOL_NUMBER',array(
				'title' => 'Номер символа'
			)),
			new Entity\StringField('SYMBOL_NAME',array(
				'title' => 'Имя символа'
			)),
			new Entity\StringField('SYMBOL_VAR',array(
				'size' => 3,
				'title' => 'Имя иконки'
			)),
			new Entity\StringField('PRECIPITATION_UNIT',array(
				'size' => 5,
				'title' => 'Временной промежуток'
			)),
			new Entity\FloatField('PRECIPITATION_VALUE',array(
				'title' => 'Количество осадков'
			)),
			new Entity\StringField('PRECIPITATION_TYPE',array(
				'title' => 'Тип осадков'
			)),
			new Entity\FloatField('WIND_DIRECTION_DEG',array(
				'title' => 'Направление ветра в градусах'
			)),
			new Entity\StringField('WIND_DIRECTION_CODE',array(
				'size' => 4,
				'title' => 'Код направления ветра'
			)),
			new Entity\StringField('WIND_DIRECTION_NAME',array(
				'title' => 'Название направления ветра'
			)),
			new Entity\FloatField('WIND_SPEED_MPS',array(
				'title' => 'Скорость ветра, м/с'
			)),
			new Entity\StringField('WIND_SPEED_NAME',array(
				'title' => 'Описание скорости ветра'
			)),
			new Entity\FloatField('TEMPERATURE_MIN',array(
				'title' => 'Минимальная температура'
			)),
			new Entity\FloatField('TEMPERATURE_MAX',array(
				'title' => 'Максимальная температура'
			)),
			new Entity\FloatField('PRESSURE_HPA_VALUE',array(
				'title' => 'Давление в hPa'
			)),
			new Entity\FloatField('PRESSURE_MM_VALUE',array(
				'title' => 'Давление в мм.рт.ст.'
			)),
			new Entity\FloatField('HUMIDITY_VALUE',array(
				'title' => 'Влажность, %'
			)),
			new Entity\StringField('CLOUDS_VALUE',array(
				'title' => 'Облачность, текст'
			)),
			new Entity\IntegerField('CLOUDS_ALL',array(
				'title' => 'Облачность, %'
			))
		);
	}
}