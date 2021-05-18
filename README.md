#*How install News Send module*#

##**Before installing**##

You need install two modules:

1. Swift Mailer - https://www.drupal.org/project/swiftmailer

2. Mail System - https://www.drupal.org/project/mailsystem

##**Module settings**##

After installing you need copy file swiftmailer--news-send.html.twig from
MODULE/templates in themes/custom/your_theme/templates.

After transferring the file, you need to go to the Mail System settings
via /admin/config/system/mailsystem.
Then you need to select in the settings:

1. Formatter - Swift Mailer
2. Sender - Default PHP mailer
3. Theme to render the emails - your custom theme

In case of incorrect operation, it is recommended to completely clear the
site cache.
