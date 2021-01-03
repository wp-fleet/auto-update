# wp-fleet/auto-update

PHP Package to handle the WordPress plugins and themes auto update functionality.

## Getting Started

### Minimum requirements and dependencies

AutoUpdate requires:

* PHP >= 7.4
* WP Fleet plugin to be installed from https://wp-fleet.com/
* WordPress - latest
* Composer to be installed

### Installation

Install via composer

```
composer require wp-fleet/auto-update
```

## Usage

### Basic usage
In your plugin's main file, require vendor file and call the auto update loader class as follows:
```
// Define update plugin info and call the function to manage it.
$update_args = [
    'api_url' => 'https://your-website.tld/wp-json/wp-fleet/v1/plugin/',
    'plugin_full_path' => __FILE__,
    'allowed_hosts' => [
        'your-website.tld'
    ],
    'plugin_name' => 'Plugin Name',
    'license_key' => 'required'
];
( new WpFleet\AutoUpdate\Loader( $update_args ) );
```

Parameters and arguments:
```
api_url - the WP REST api url on your website where WP Fleet plugin is installed.
```
```
plugin_full_path - the full path of the current plugin (that will be updated automatically)
```
```
allowed_hosts - the url of the allowed external hosts to allow plugin updated (where WP Fleet plugin is installed)
```
```
plugin_name - the name of the current plugin (that will be updated automatically) 
```
```
license_key - if true|1|required, a new page will be added under WP Admin -> Plugins -> License Keys and user will 
have to submit a valid license key to be able to automatically update plugin. If no license key is required, set it 
to false.  
```

## License
WP Mail Helper code is licensed under MIT license.