<?php

namespace MSergeev\Packages\Owm\Tables;

use MSergeev\Core\Lib\DataManager;
use MSergeev\Core\Entity;

class CityTable extends DataManager
{
	public static function getTableName ()
	{
		return 'ms_owm_city';
	}

	public static function getTableTitle ()
	{
		return 'Погода в городах';
	}

	public static function getTableLinks ()
	{
		return array(
			'ID' => array(
				'ms_owm_forecast' => 'CITY_ID',
				'ms_owm_sun' => 'CITY_ID',
				'ms_owm_timezone' => 'CITY_ID'
			)
		);
	}

	public static function getMap ()
	{
		return array(
			new Entity\IntegerField('ID',array(
				'primary' => true,
				'title' => 'ID города'
			)),
			new Entity\IntegerField('UTC_HOUR',array(
				'required' => true,
				'default_value' => 0,
				'title' => 'Смещение времени относительно UTC'
			)),
			new Entity\StringField('NAME',array(
				'title' => 'Название города'
			))
		);
	}

	public static function getArrayDefaultValues ()
	{
		return array(
			array(
				'ID' => 524901,
				'UTC_HOUR' => 3,
				'NAME' => 'Москва'
			)
		);
	}
}