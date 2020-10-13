## Tech Stack
| App | Version |
| :------------- |:-------------:|
| PHP | 7.2.*, 7.3.* |
| MySQL | 5.7 |
| Node | v13.8.0 |
| Npm | 6.13.7 |

#Install Module
composer require vincentle89/magento2-change-password
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy

#Using
Command:

php bin/magento customer:changepassword --customer-id=[user-id] --customer-password=[expected-password]

[user-id]: customer id who want to update password
[expected-password]: new password