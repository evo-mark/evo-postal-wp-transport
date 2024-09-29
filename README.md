<p align="center">
    <a href="https://evomark.co.uk" target="_blank" alt="Link to evoMark's website">
        <picture>
          <source media="(prefers-color-scheme: dark)" srcset="https://evomark.co.uk/wp-content/uploads/static/evomark-logo--dark.svg">
          <source media="(prefers-color-scheme: light)" srcset="https://evomark.co.uk/wp-content/uploads/static/evomark-logo--light.svg">
          <img alt="evoMark company logo" src="https://evomark.co.uk/wp-content/uploads/static/evomark-logo--light.svg" width="500">
        </picture>
    </a>
</p>
<p align="center">
    <a href="https://packagist.org/packages/evo-mark/evo-postal-wp-transport"><img src="https://img.shields.io/packagist/v/evo-mark/evo-postal-wp-transport?logo=packagist&logoColor=white" alt="Build status" /></a>
    <a href="https://packagist.org/packages/evo-mark/evo-postal-wp-transport"><img src="https://img.shields.io/packagist/dt/evo-mark/evo-postal-wp-transport" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/evo-mark/evo-postal-wp-transport"><img src="https://img.shields.io/packagist/l/evo-mark/evo-postal-wp-transport" alt="License"></a>
</p>
<br />

# Evo Postal WP Transport

A base-level integration for Postal on Wordpress applications. This package should be consumed by other plugins/themes

```php
use EvoMark\EvoPostalWpTransport\Postal;

class MyPlugin
{
    public static function register()
    {
        $settings = self::getSettings();

        if ($settings['enabled']) {
            add_filter('pre_wp_mail', [__CLASS__, 'process'], 10, 2);
        }
    }

    public static function process($null, $atts)
    {
        $settings = self::getSettings();
        $transport = new Postal($settings, $atts);
        $result = $transport->send();
        return $result;
    }
}
```

## Required settings

The `$settings` variable used above is an associative array with the following structure:

```php
$settings = [
    'host' => '',
    'api_key' => '',
    'from_address' => '',
    'from_name' => ''
];
```
