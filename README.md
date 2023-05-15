#phpMyAdmin for Laravel Sail

#Quick Setup

If you already installed laravel sail using `sail:install` command,
place `install.php` in the root of your laravel project,
then open your project in terminal & run `php install.php`.
then run `sail down && sail up -d`,
to bring up the newly defined phpmyadmin container.
you can access phpmyadmin at `localhost:8080` through browser.
**username= sail** & **password= password** to access admin panel 
based on your `.env` definitions

If you haven't installed laravel sail using `sail:install` command,
open `install.php` file & comment line 13 & uncomment line 14,
now run `php install.php` in your project directory.
then run `sail:install` & select phpmyadmin along mysql & other required services.
then `sail up -d`

