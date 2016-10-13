<?php

namespace MSergeev\Packages\Owm\Tables;

use MSergeev\Core\Lib\DataManager;
use MSergeev\Core\Entity;

class SunTable extends DataManager
{
	public static function getTableName ()
	{
		return 'ms_owm_sun';
	}

	public static function getTableTitle ()
	{
		return 'Время восхода и заката';
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
			new Entity\DateField('DATE',array(
				'required' => true,
				'title' => 'Дата'
			)),
			new Entity\TimeField('SUNRISE',array(
				'required' => true,
				'title' => 'Время восхода'
			)),
			new Entity\TimeField('SUNSET',array(
				'required' => true,
				'title' => 'Время заката'
			)),
			new Entity\TimeField('DAYTIME',array(
				'required' => true,
				'title' => 'Долгота дня'
			))
		);
	}
}