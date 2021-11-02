# Description

The module allows a developer updating password via a command. 

Magento 2 - update password for a specified user
# Tech Stack
| App | Version |
| :------------- |:-------------:|
| PHP | 7.2.x, 7.3.x, 7.4.x |
| MySQL | 5.7 |

# Install Module

composer require vincentle89/magento2-change-password

php bin/magento setup:upgrade

php bin/magento setup:static-content:deploy

# Using

## Command:

php bin/magento customer:changepassword --customer-id=[user-id] --customer-password=[expected-password]

[user-id]: customer id who want to update password

[expected-password]: new password
