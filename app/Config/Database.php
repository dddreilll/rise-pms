<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration
 */
class Database extends Config
{
	/**
	 * The directory that holds the Migrations
	 * and Seeds directories.
	 *
	 * @var string
	 */
	public $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

	/**
	 * Lets you choose which connection group to
	 * use if no other is specified.
	 *
	 * @var string
	 */
	public $defaultGroup = 'default';

	/**
	 * The default database connection.
	 *
	 * @var array
	 */
	public $default = [
		'DSN'      => '',
		'hostname' => 'db',
		'username' => 'rise_user',
		'password' => 'rise_password',
		'database' => 'rise_pms',
		'DBDriver' => 'MySQLi',
		'DBPrefix' => 'rise_',
		'pConnect' => false,
		'DBDebug'  => (ENVIRONMENT !== 'production'),
		'charset'  => 'utf8',
		'DBCollat' => 'utf8_general_ci',
		'swapPre'  => '',
		'encrypt'  => false,
		'compress' => false,
		'strictOn' => false,
		'failover' => [],
		'port'     => 3306,
	];

	/**
	 * This database connection is used when
	 * running PHPUnit database tests.
	 *
	 * @var array
	 */
	public $tests = [
		'DSN'      => '',
		'hostname' => '127.0.0.1',
		'username' => '',
		'password' => '',
		'database' => ':memory:',
		'DBDriver' => 'SQLite3',
		'DBPrefix' => 'db_',  // Needed to ensure we're working correctly with prefixes live. DO NOT REMOVE FOR CI DEVS
		'pConnect' => false,
		'DBDebug'  => (ENVIRONMENT !== 'production'),
		'charset'  => 'utf8',
		'DBCollat' => 'utf8_general_ci',
		'swapPre'  => '',
		'encrypt'  => false,
		'compress' => false,
		'strictOn' => false,
		'failover' => [],
		'port'     => 3306,
	];

	//--------------------------------------------------------------------

	public function __construct()
	{
		parent::__construct();

		// Prefer Railway / container env vars; fall back to local Docker defaults above.
		$this->default['hostname'] = $this->env('MYSQLHOST', $this->default['hostname']);
		$this->default['username'] = $this->env('MYSQLUSER', $this->default['username']);
		$this->default['password'] = $this->env('MYSQLPASSWORD', $this->default['password']);
		$this->default['database'] = $this->env('MYSQLDATABASE', $this->default['database']);
		$this->default['port']     = (int) $this->env('MYSQLPORT', (string) $this->default['port']);

		$dbPrefix = $this->env('DB_PREFIX', '');
		if ($dbPrefix !== '') {
			$this->default['DBPrefix'] = $dbPrefix;
		}

		// Ensure that we always set the database group to 'tests' if
		// we are currently running an automated test suite, so that
		// we don't overwrite live data on accident.
		if (ENVIRONMENT === 'testing')
		{
			$this->defaultGroup = 'tests';
		}
	}

	/**
	 * Read an environment variable from getenv / $_ENV / $_SERVER.
	 */
	private function env(string $key, string $default = ''): string
	{
		$value = getenv($key);
		if ($value === false || $value === '') {
			$value = $_ENV[$key] ?? $_SERVER[$key] ?? '';
		}

		return ($value === '' || $value === false) ? $default : (string) $value;
	}

	//--------------------------------------------------------------------

}
