Refresh Bundle
==============

This is a bundle for [Contao CMS] (version 4.9.x).

Dies ist ein Bundle für [Contao CMS] (Version 4.9.x).

Installation
------------

Install the extension via composer: [trilobit-gmbh/contao-refresh-bundle](https://packagist.org/packages/trilobit-gmbh/contao-refresh-bundle).

Kompatibilität / Getestet / Compatibility / Tested
--------------------------------------------------

- Contao version ~4.9
- Contao version ~4.13

Configuration
-------------

You can define the following configuration parameters via your `config.yml` file:

```yaml
# example
trilobit_refresh:
  config:
    php: "/my/path/to/php -d memory_limit=3G"
    symfony: "##php## ./vendor/bin/contao-console"
    contao-manager: "contao-manager.phar.php"
    composer: "##contao-manager## composer"

    environments:
      dev2stage:
        name: "EXAMPLE - dev 2 stage"
        dumps_path: "%kernel.project_dir%/system/tmp"

        exclude:
          database:
            tables:
              - "tl_crawl_queue"
              - "tl_cron_job"
              - "tl_log"
              - "tl_opt_in"
              - "tl_opt_in_related"
              - "tl_remember_me"
              - "tl_search"
              - "tl_search_index"
              - "tl_trusted_device"
              - "tl_undo"
              - "tl_user"
              - "tl_user_group"
              - "tl_version"

        source:
          path: "%kernel.project_dir%"
          db_host: "my_dev_database_host"
          db_name: "my_dev_database_name"
          db_user: "my_dev_database_user"
          db_password: "my_dev_database_password"

        target:
          path: "%kernel.project_dir%/../stage"
          db_host: "my_stage_database_host"
          db_name: "my_stage_database_name"
          db_user: "my_stage_database_user"
          db_password: "my_stage_database_password"

        steps:
          - "clear old dump(s)": "cd ##dumps_path## && find source.sql target.sql -mtime +1 -exec rm {} \;"
          - "contao-manager self-update": "cd ##source.path##/web && ##php## ##source.path##/web/##contao-manager## self-update"

          - "dump source database": "mysqldump --default-character-set=utf8 --add-drop-table ##exclude.database.tables|source.db_name## -h##source.db_host## -u##source.db_user## -p##source.db_password## ##source.db_name## -r##dumps_path##/source.$(date +"%F").sql"
          - "dump target database": "mysqldump --default-character-set=utf8 --add-drop-table ##exclude.database.tables|target.db_name## -h##target.db_host## -u##target.db_user## -p##target.db_password## ##target.db_name## -r##dumps_path##/target.$(date +"%F").sql"

          - "maintenance lock": "cd ##target.path## && ##symfony## lexik:maintenance:lock -n"

          - "copy composer": "cd ##source.path## && rsync -avPuz composer.* ##target.path##/"
          - "copy contao-manager": "cd ##source.path##/web && rsync -avPuz contao-manager.phar.php ##target.path##/web/"

          - "copy system/config": "cd ##source.path##/system  && mkdir -p ##target.path##/system/config && rsync -avPuz config/ ##target.path##/system/config"
          - "copy templates": "cd ##source.path## && mkdir -p ##target.path##/templates && rsync -avPuz templates/ ##target.path##/templates"
          - "copy layout": "cd ##source.path## && mkdir -p ##target.path##/layout && rsync -avPuz layout/ ##target.path##/layout"
          - "copy web/layout": "cd ##source.path##/web && mkdir -p ##target.path##/web/layout && rsync -avPuz layout/ ##target.path##/web/layout"
          - "copy assets": "cd ##source.path## && mkdir -p ##target.path##/assets && rsync -avPuz assets/ ##target.path##/assets"
          - "copy files": "cd ##source.path## && mkdir -p ##target.path##/files && rsync -avPuz files/ ##target.path##/files"

          - "composer install": "cd ##target.path## && ##php## ##target.path##/web/##composer## install"

          - "push source database": "cd ##dumps_path## && mysql --default-character-set=utf8 -h##target.db_host## -u##target.db_user## -p##target.db_password## ##target.db_name## < source.$(date +"%F").sql"
          - "push target database common updates": "cd ##dumps_path## && mysql --default-character-set=utf8 -h##target.db_host## -u##target.db_user## -p##target.db_password## ##target.db_name## < source.commonupdates.sql"

          - "cache clear prod": "cd ##target.path##/var/cache && rm -rf prod/"
          - "cache clear dev": "cd ##target.path##/var/cache && rm -rf dev/"
          - "cache warmup": "cd ##target.path##/ && ##symfony## cache:warmup"

          - "maintenance unlock": "cd ##target.path## && ##symfony## lexik:maintenance:unlock -n"

      stage2prod:
        name: "EXAMPLE - stage 2 prod"
        dumps_path: "%kernel.project_dir%/system/tmp"

        exclude:
          database:
            tables:
              - "tl_crawl_queue"
              - "tl_cron_job"
              - "tl_log"
              - "tl_opt_in"
              - "tl_opt_in_related"
              - "tl_remember_me"
              - "tl_search"
              - "tl_search_index"
              - "tl_trusted_device"
              - "tl_undo"
              - "tl_user"
              - "tl_user_group"
              - "tl_version"

        source:
          path: "%kernel.project_dir%"
          db_host: "my_stage_database_host"
          db_name: "my_stage_database_name"
          db_user: "my_stage_database_user"
          db_password: "my_stage_database_password"

        target:
          path: "%kernel.project_dir%/../prod"
          db_host: "my_prod_database_host"
          db_name: "my_prod_database_name"
          db_user: "my_prod_database_user"
          db_password: "my_prod_database_password"

        steps:
          - "clear old dump(s)": "cd ##dumps_path## && find source.sql target.sql -mtime +1 -exec rm {} \;"
          - "contao-manager self-update": "cd ##source.path##/web && ##php## ##source.path##/web/##contao-manager## self-update"

          - "dump source database": "mysqldump --default-character-set=utf8 --add-drop-table ##exclude.database.tables|source.db_name## -h##source.db_host## -u##source.db_user## -p##source.db_password## ##source.db_name## -r##dumps_path##/source.$(date +"%F").sql"
          - "dump target database": "mysqldump --default-character-set=utf8 --add-drop-table ##exclude.database.tables|target.db_name## -h##target.db_host## -u##target.db_user## -p##target.db_password## ##target.db_name## -r##dumps_path##/target.$(date +"%F").sql"

          - "maintenance lock": "cd ##target.path## && ##symfony## lexik:maintenance:lock -n"

          - "copy composer": "cd ##source.path## && rsync -avPuz composer.* ##target.path##/"
          - "copy contao-manager": "cd ##source.path##/web && rsync -avPuz contao-manager.phar.php ##target.path##/web/"

          - "copy system/config": "cd ##source.path##/system  && mkdir -p ##target.path##/system/config && rsync -avPuz config/ ##target.path##/system/config"
          - "copy templates": "cd ##source.path## && mkdir -p ##target.path##/templates && rsync -avPuz templates/ ##target.path##/templates"
          - "copy layout": "cd ##source.path## && mkdir -p ##target.path##/layout && rsync -avPuz layout/ ##target.path##/layout"
          - "copy web/layout": "cd ##source.path##/web && mkdir -p ##target.path##/web/layout && rsync -avPuz layout/ ##target.path##/web/layout"
          - "copy assets": "cd ##source.path## && mkdir -p ##target.path##/assets && rsync -avPuz assets/ ##target.path##/assets"
          - "copy files": "cd ##source.path## && mkdir -p ##target.path##/files && rsync -avPuz files/ ##target.path##/files"

          - "composer install": "cd ##target.path## && ##php## ##target.path##/web/##composer## install"

          - "push source database": "cd ##dumps_path## && mysql --default-character-set=utf8 -h##target.db_host## -u##target.db_user## -p##target.db_password## ##target.db_name## < source.$(date +"%F").sql"
          - "push target database common updates": "cd ##dumps_path## && mysql --default-character-set=utf8 -h##target.db_host## -u##target.db_user## -p##target.db_password## ##target.db_name## < source.commonupdates.sql"

          - "cache clear prod": "cd ##target.path##/var/cache && rm -rf prod/"
          - "cache clear dev": "cd ##target.path##/var/cache && rm -rf dev/"
          - "cache warmup": "cd ##target.path##/ && ##symfony## cache:warmup"

          - "maintenance unlock": "cd ##target.path## && ##symfony## lexik:maintenance:unlock -n"
```
example configuration
