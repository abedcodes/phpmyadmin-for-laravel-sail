## phpMyAdmin for Laravel Sail <br><br>

### Quick Setup

- If you already installed laravel sail using `sail:install` command,
place `install-pma` in the root of your laravel project,
then open your project in terminal & run `php install-pma`.
then run `sail down && sail up -d`,
to bring up the newly defined phpmyadmin container.
you can access phpmyadmin at `localhost:8080` through browser.
**username= sail** & **password= password** to access admin panel 
based on your `.env` definitions


- If you haven't installed laravel sail using `sail:install` command,
run `php install-pma --add` in your project directory.
then run `sail:install` & select phpmyadmin along mysql 
& other required services. then `sail up -d`
<br>


### Backups

you can restore your original files in case something goes wrong. 
backups are created automatically before changing files. 
in order to use backups run `php install-pma --restore`.<br>

### Configurations
##### Changing port:
You can access phpMyAdmin on a port other than `8080` if not already in use.
to do that run the script like `php install-pma --port=8008`. 
now after bringing up the network using `sail up -d`, 
phpmyadmin is available at `localhost:8008`

##### Changing version:
The Default phpMyAdmin image version is `5.2.1`. If you want to use a specific version,
run the script with `version` flag like `php install-pma --version=5.0`

of course, you can use both flags together like `php install-pma --version=5.0 --port=8008`
