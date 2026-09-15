# RISE plugin templates

Replace `Plugin_Name`, `plugin_name`, and display strings as needed.

## index.html (every subfolder)

```html
<!DOCTYPE html>
<html>
<head>
    <title>403 Forbidden</title>
</head>
<body>
<p>Directory access is forbidden.</p>
</body>
</html>
```

## Root index.php (skeleton)

```php
<?php

defined('PLUGINPATH') or exit('No direct script access allowed');

/*
Plugin Name: Plugin Display Name
Plugin URL: https://github.com/local/rise-pms
Description: Short description.
Version: 1.0.0
Requires at least: 2.8
Author: Rise PMS
Author URL: https://github.com/local/rise-pms
*/

$helper = PLUGINPATH . "Plugin_Name/Helpers/plugin_name_helper.php";
if (file_exists($helper)) {
    require_once($helper);
}

register_installation_hook("Plugin_Name", function ($item_purchase_code) {
    $db = db_connect('default');
    $db_prefix = get_db_prefix();
    $db->query("SET sql_mode = ''");
    // $db->query("CREATE TABLE IF NOT EXISTS `{$db_prefix}plugin_name_items` ( ... )");
});

register_uninstallation_hook("Plugin_Name", function () {
    $db = db_connect('default');
    $db_prefix = get_db_prefix();
    $db->query("SET sql_mode = ''");
    // $db->query("DROP TABLE IF EXISTS `{$db_prefix}plugin_name_items`");
});

register_activation_hook("Plugin_Name", function () {
});

register_deactivation_hook("Plugin_Name", function () {
});

// Example menu filter — always return $sidebar_menu
app_hooks()->add_filter('app_filter_staff_left_menu', function ($sidebar_menu) {
    $sidebar_menu["plugin_name"] = array(
        "name" => "plugin_name",
        "url" => "plugin_name",
        "class" => "check-circle",
        "position" => 10,
    );
    return $sidebar_menu;
});
```

## Language

`Language/english/default_lang.php`:

```php
<?php

$lang["plugin_name"] = "Plugin Display Name";

return $lang;
```

`Language/english/custom_lang.php`:

```php
<?php

/* Copy keys here to override default language values. */

$lang = array();

return $lang;
```

## Config/Routes.php

```php
<?php

namespace Config;

$routes = Services::routes();
$namespace = 'Plugin_Name\Controllers';

$routes->get('plugin_name', 'Plugin_Name::index', ['namespace' => $namespace]);
$routes->get('plugin_name/(:any)', 'Plugin_Name::$1', ['namespace' => $namespace]);
$routes->post('plugin_name/(:any)', 'Plugin_Name::$1', ['namespace' => $namespace]);
```

## Controller

```php
<?php

namespace Plugin_Name\Controllers;

use App\Controllers\Security_Controller;

class Plugin_Name extends Security_Controller {

    function index() {
        return $this->template->rander('Plugin_Name\Views\index');
    }
}
```

## Model

```php
<?php

namespace Plugin_Name\Models;

use App\Models\Crud_model;

class Plugin_Name_Model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "plugin_name_items";
        parent::__construct($this->table);
    }
}
```

## In-repo references

- Hook-focused: `plugins/Remember_Me/index.php`
- Full module: `plugins/Google_Docs_Integration/index.php` + `Config/Routes.php`
