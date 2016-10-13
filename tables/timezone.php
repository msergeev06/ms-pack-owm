<?php

namespace MSergeev\Packages\Owm\Tables;

use MSergeev\Core\Lib\DataManager;
use MSergeev\Core\Entity;

class TimezoneTable extends DataManager
{
	public static function getTableName ()
	{
		return 'ms_owm_timezone';
	}

	public static function getTableTitle ()
	{
		return 'Погода разбитая на временные зоны';
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
				'title' => 'Дата начала временной зоны'
			)),
			new Entity\DatetimeField('DATETIME_TO',array(
				'required' => true,
				'title' => 'Дата окончания временной зоны'
			)),
			new Entity\StringField('SYMBOL_VAR',array(
				'size' => 3,
				'title' => 'Имя иконки'
			)),
			new Entity\FloatField('WIND_DIRECTION_DEG',array(
				'title' => 'Направление ветра в градусах'
			)),
			new Entity\StringField('WIND_DIRECTION_CODE',array(
				'size' => 4,
				'title' => 'Код направления ветра'
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
			new Entity\FloatField('PRESSURE_MM_VALUE',array(
				'title' => 'Давление в мм.рт.ст.'
			)),
			new Entity\FloatField('HUMIDITY_VALUE',array(
				'title' => 'Влажность, %'
			))
		);
	}
}