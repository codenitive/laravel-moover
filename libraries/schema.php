<?php namespace Moover;

use Closure, 
	Exception,
	InvalidArgumentException,
	Config, 
	DB;

class Schema 
{
	public static $connection = null;
	public static $limits     = 200;

	/**
	 * Run Schema migration
	 * 
	 * Moover\Schema::create('old.members', array(
	 *     'key'  => ''
	 *     'find' => function ($query) {
	 *          // only migrate user with an email.
	 *          $query->where_not_null('email_address');
	 *     },
	 *     'save' => function ($result) {
	 *         $user = User::create(array(
	 *             'email'    => $result->email_address,
	 *             'password' => Hash::make($result->password),
	 *         ));
	 *
	 *         return $user->id;
	 *     }
	 * ));
	 *
	 * @static          
	 * @access public
	 * @param  string   $id
	 * @param  mixed    $options
	 * @return void
	 */
	public static function create($id, $options = null)
	{
		$defaults = array(
			'connection'     => static::$connection,
			'table'          => null,
			'key'            => 'id',
			'find'           => null, 
			'save'           => null,
			'ignore_last_id' => false,
		);

		// Second parameters might be optional for complex migration.
		if (($id instanceof Closure) or is_array($id))
		{
			$options = $id;
			$id = null;
		}

		if ($options instanceof Closure)
		{
			$options = array_merge($defaults, array(
				'save' => $options,
			));
		}
		else
		{
			$options = array_merge($defaults, $options);
		}

		if (empty($options['table']))
		{   
			if (str_contains('.', $id))
			{
				list($connection, $table) = explode('.', $id, 2);
			}
			else
			{
				$table      = $id;
				$connection = $options['connection'] ?: Config::get('database.default');
			}

			if (empty($table))
			{
				throw new InvalidArgumentException("Source table name is not defined.");
			}

			$options['connection'] = $connection;
			$options['table']      = $table;
		}

		return new static($options);
	}
	
	private function __construct($options)
	{
		extract($options);

		if ( ! is_callable($save))
		{
			throw new Exception('[save] should be a closure.');
		}

		$previous = DB::table('moover_migrations')
			->where('name', '=', $table)
			->order_by('source_id', 'DESC')
			->first();

		$last_id = (is_null($previous) ? 0 : $previous->source_id);

		$query = DB::connection($connection)->table($table);

		if ( ! $ignore_last_id) $query->where($key, '>', $last_id);

		if (is_callable($find)) $find($query);

		$results = $query->take(static::$limits)->get();

		if (empty($results)) return true;

		foreach ($results as $result)
		{
			$source_id = $result->{$key};

			$destination_id = $save($result, $this);

			if ( ! is_numeric($destination_id) and $destination_id < 1)
			{
				throw new Exception('[save] action require a valid id');
			}

			DB::table('moover_migrations')->insert(array(
				'name'           => $table,
				'source_id'      => $source_id,
				'destination_id' => $destination_id,
			));
		}

		return false;
	}

	/**
	 * Get destination_id based on source_id
	 *
	 * @access public
	 * @param  string   $table
	 * @param  int      $source_id
	 * @return int
	 */
	public function key($table, $source_id)
	{
		$result = DB::table('moover_migrations')
				->where('name', '=', $table)
				->where('source_id', '=', $source_id)
				->first();

		return $result->destination_id ?: $source_id;
	}
}