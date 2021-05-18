<?php

namespace Drupal\news_send\Services;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Lass.
 */
class SendMail {
  use StringTranslationTrait;

  /**
   * Contain MailManagerInterface object for mailing email.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailer;

  /**
   * Contain LanguageManagerInterface object for get site language.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Contain LoggerChannelFactory object for saving log email send status.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected LoggerChannelFactory $loggerFactory;

  /**
   * Constructor.
   */
  public function __construct(MailManagerInterface $mailer,
  LanguageManagerInterface $language_manager,
  LoggerChannelFactory $loggerFactory) {
    $this->mailer = $mailer;
    $this->languageManager = $language_manager;
    $this->loggerFactory = $loggerFactory;
  }

  /**
   * Sends the mails.
   *
   * @param array $params
   *   Contain sending parameters.
   * @param string $check_daily
   *   Contain send type.
   */
  public function sendMail(array $params, string $check_daily) {
    $result = FALSE;
    $module = 'news_send';
    $key = 'news_send';
    $lang_code = $this->languageManager->getCurrentLanguage()->getId();

    // Send emails.
    $users = $params['users'];
    $user_count = count($users);
    foreach ($users as $user) {
      if ($user[2] === $check_daily) {
        $params['uuid'] = $user[1];
        $params['daily'] = $user[2];
        $result = $this->mailer->mail($module, $key, $user[0], $lang_code, $params, NULL, TRUE);
      }
    }
    $message = $this->loggerFactory->get('news_send');
    if ($result['result'] === TRUE) {
      $message->notice($user_count . ' ' . $this->t('user(s) notified successfully.'));
    }
    else {
      $message->error($this->t('Unable to send emails, please contact administrator!'));
    }
  }

}
