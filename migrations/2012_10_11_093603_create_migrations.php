<?php

class Moover_Create_Migrations {
	/**
	 * Make changes to the database.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('moover_migrations', function ($table)
		{
			$table->increments('id');

			$table->string('name');
			$table->integer('source_id')->unsigned();
			$table->integer('destination_id')->unsigned();
		});
	}

	/**
	 * Revert the changes to the database.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('moover_migrations');
	}
}