# SNK Tax Updater Magento 2

**Extension for Magento 2**

## Overview

The module allows changing tax rates through magento console. 

## Requirements

Magento 2.2+, PHP >7.1

## Usage

The command can be run as a normal Magento 2 console command with several options:
```
bin/magento tax:rate:update [--id [TAX RATE ID]] [--country [COUNTRY ISO2 CODE]] [--old-rate [OLD RATE] [--new-rate [NEW RATE]] [--dry-run [DRY RUN BOOL]]
```

- _--id_: integer, the ID of tax rate entry to be changed
- _--country_: ISO2 country code
- _--old-rate_: float, percent value of the tax rate that should be looked for
- _--new-rate_: float, the new percent value 
- _--dry-run_: boolean, if true, no data will be actually changed; useful for testing (NOTE: in order to work it must be explicitly set to _true_ or 1)

If option ID is specified, then the script will only look for a tax rate with that ID and try to set the new percent value to it.
If country and old rate are specified then the script will looks for corresponging tax rates and change them. This can be multiple rates, for example for different regions.

Do not forget to run indexing and clean the cache after chaging yout taxes.

### Use cases

A famous use case for the script can be the change of VAT (Mehrwertsteuer, MwSt.) in Germany for 6 months from 01.07.20 to 31.12.20. 

This script when used as a cronjob will automatically set the needed VAT value:
```
# Crontab

# VAT change in Germany

# From 19% to 16% on 01.07
0 0 1 7 * cd /var/www/share/www.yourshop.com/htdocs && bin/magento tax:rate:update --country DE --old-rate 19 --new-rate 16 >/dev/null 2>&1

# From 16% to 19% on 01.01
0 0 1 1 * cd /var/www/share/www.yourshop.com/htdocs && bin/magento tax:rate:update --country DE --old-rate 16 --new-rate 19 >/dev/null 2>&1
```

## Authors

Oleh Kravets <a href="mailto:oleh.kravets@snk.de">oleh.kravets@snk.de</a>

## Lisence

MIT
